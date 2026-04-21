<?php
/**
 * Admin 2FA Class
 *
 * When enabled, intercepts WordPress's default password-based admin login and
 * forces a second factor (OTP via email / WhatsApp) before completing auth.
 *
 * Implementation strategy:
 *  - Hook the `authenticate` filter AFTER WP's built-in password check (priority 30).
 *  - If the authenticated user has the administrator role AND the 2FA option is on,
 *    we DO NOT complete the login. Instead we stash `user_id` in a short-lived
 *    transient keyed by a random pending-token and redirect to /secure-login/?2fa=TOKEN.
 *  - Our login page detects `?2fa=TOKEN`, sends an OTP to the admin's registered
 *    email (or billing WhatsApp if set), and on verify, logs the user in via the
 *    normal wp_set_auth_cookie path.
 *
 * @package WeeWoo_Auth_Pro
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class WW_Admin_2FA
{
    private static ?WW_Admin_2FA $instance = null;
    private const TRANSIENT_PREFIX = 'ww_auth_2fa_pending_';
    private const TRANSIENT_TTL = 300; // 5 min

    public static function instance(): WW_Admin_2FA
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        // Run AFTER core password check (priority 30) so $user is either WP_User or WP_Error.
        add_filter('authenticate', [$this, 'maybe_require_2fa'], 30, 3);

        // REST endpoints for the 2FA step
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function is_enabled(): bool
    {
        return (bool) get_option('ww_auth_admin_2fa_enabled', false);
    }

    /**
     * Intercept successful password auth for administrators.
     */
    public function maybe_require_2fa($user, $username, $password)
    {
        if (!$this->is_enabled()) return $user;
        if (is_wp_error($user) || !($user instanceof WP_User)) return $user;
        if (empty($password)) return $user; // cookie/2FA requests — let them through
        if (!in_array('administrator', (array) $user->roles, true)) return $user;

        // Issue a pending-2FA token and redirect the browser to our secure login in 2FA mode.
        $token = bin2hex(random_bytes(20));
        set_transient(self::TRANSIENT_PREFIX . $token, $user->ID, self::TRANSIENT_TTL);

        $redirect_to = isset($_REQUEST['redirect_to']) ? esc_url_raw((string) $_REQUEST['redirect_to']) : admin_url();
        $url = add_query_arg([
            'ww_2fa' => $token,
            'redirect_to' => urlencode($redirect_to),
        ], home_url('/secure-login/'));

        wp_safe_redirect($url);
        exit;
    }

    public function register_routes(): void
    {
        register_rest_route('ww-auth/v1', '/admin-2fa/send', [
            'methods' => 'POST',
            'callback' => [$this, 'send_code'],
            'permission_callback' => '__return_true',
        ]);
        register_rest_route('ww-auth/v1', '/admin-2fa/verify', [
            'methods' => 'POST',
            'callback' => [$this, 'verify_code'],
            'permission_callback' => '__return_true',
        ]);
    }

    public function send_code(WP_REST_Request $request): WP_REST_Response
    {
        $token = sanitize_text_field((string) $request->get_param('token'));
        $channel = sanitize_text_field((string) $request->get_param('channel')); // email|whatsapp

        $user_id = (int) get_transient(self::TRANSIENT_PREFIX . $token);
        if ($user_id <= 0) {
            return new WP_REST_Response(['success' => false, 'error' => 'Session expired, please sign in again.'], 400);
        }

        $user = get_user_by('id', $user_id);
        if (!$user) return new WP_REST_Response(['success' => false, 'error' => 'User not found'], 400);

        if ($channel === 'whatsapp') {
            $phone = get_user_meta($user->ID, 'billing_phone', true);
            if (empty($phone)) {
                return new WP_REST_Response(['success' => false, 'error' => 'No WhatsApp number on file for this admin.'], 400);
            }
            $wa = WW_WhatsApp::instance();
            if (!$wa->is_enabled()) {
                return new WP_REST_Response(['success' => false, 'error' => 'WhatsApp OTP is not configured.'], 400);
            }
            $r = $wa->send_otp($phone);
            if (empty($r['success'])) {
                return new WP_REST_Response(['success' => false, 'error' => $r['error'] ?? 'Send failed'], 500);
            }
            update_user_meta($user->ID, '_ww_2fa_channel', 'whatsapp');
            update_user_meta($user->ID, '_ww_2fa_phone', $phone);
            return new WP_REST_Response(['success' => true, 'dest' => $this->mask($phone, 'phone'), 'channel' => 'whatsapp'], 200);
        }

        // default: email — we generate the OTP here and store it ourselves
        $otp = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        set_transient('ww_auth_2fa_otp_' . $token, $otp, 5 * MINUTE_IN_SECONDS);

        $subject = sprintf('[%s] Admin 2FA code: %s', get_bloginfo('name'), $otp);
        $body = sprintf("Your administrator 2FA code is: %s\n\nIt expires in 5 minutes. If you did not request this, reset your password immediately.", $otp);
        $sent = wp_mail($user->user_email, $subject, $body);
        if (!$sent) return new WP_REST_Response(['success' => false, 'error' => 'Could not send email.'], 500);

        update_user_meta($user->ID, '_ww_2fa_channel', 'email');
        return new WP_REST_Response(['success' => true, 'dest' => $this->mask($user->user_email, 'email'), 'channel' => 'email'], 200);
    }

    public function verify_code(WP_REST_Request $request): WP_REST_Response
    {
        $token = sanitize_text_field((string) $request->get_param('token'));
        $otp   = sanitize_text_field((string) $request->get_param('otp'));

        $user_id = (int) get_transient(self::TRANSIENT_PREFIX . $token);
        if ($user_id <= 0) return new WP_REST_Response(['success' => false, 'error' => 'Session expired.'], 400);

        $user = get_user_by('id', $user_id);
        if (!$user) return new WP_REST_Response(['success' => false, 'error' => 'User not found.'], 400);

        $channel = (string) get_user_meta($user->ID, '_ww_2fa_channel', true);

        $ok = false;
        if ($channel === 'whatsapp') {
            $phone = (string) get_user_meta($user->ID, '_ww_2fa_phone', true);
            $r = WW_WhatsApp::instance()->verify_otp($phone, $otp);
            $ok = !empty($r['success']);
            $err = $r['error'] ?? 'Incorrect code.';
        } else {
            $expected = (string) get_transient('ww_auth_2fa_otp_' . $token);
            $ok = ($expected !== '' && hash_equals($expected, $otp));
            $err = 'Incorrect code.';
            if ($ok) delete_transient('ww_auth_2fa_otp_' . $token);
        }

        if (!$ok) return new WP_REST_Response(['success' => false, 'error' => $err], 400);

        delete_transient(self::TRANSIENT_PREFIX . $token);
        delete_user_meta($user->ID, '_ww_2fa_channel');
        delete_user_meta($user->ID, '_ww_2fa_phone');

        wp_set_current_user($user->ID);
        wp_set_auth_cookie($user->ID, true);
        do_action('wp_login', $user->user_login, $user);

        $redirect = isset($_REQUEST['redirect_to']) ? esc_url_raw((string) $_REQUEST['redirect_to']) : admin_url();
        return new WP_REST_Response([
            'success' => true,
            'redirect' => $redirect,
        ], 200);
    }

    private function mask(string $value, string $type): string
    {
        if ($type === 'email') {
            $p = explode('@', $value);
            if (count($p) !== 2) return $value;
            $local = $p[0];
            $len = strlen($local);
            if ($len <= 3) return str_repeat('*', $len) . '@' . $p[1];
            return substr($local, 0, 2) . str_repeat('*', max(3, $len - 4)) . substr($local, -2) . '@' . $p[1];
        }
        // phone
        $d = preg_replace('/\D/', '', $value);
        $len = strlen($d);
        if ($len < 6) return str_repeat('*', $len);
        return substr($d, 0, 2) . str_repeat('*', max(3, $len - 6)) . substr($d, -4);
    }
}
