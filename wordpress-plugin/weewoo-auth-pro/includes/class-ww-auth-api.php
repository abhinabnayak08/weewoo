<?php
/**
 * REST API Endpoints Class - Premium Edition
 *
 * @package WeeWoo_Auth_Pro
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * REST API for authentication operations
 */
final class WW_Auth_API
{
    private static ?WW_Auth_API $instance = null;
    private const NAMESPACE = 'ww-auth/v1';

    public static function instance(): WW_Auth_API
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
    }

    /**
     * Register all REST routes
     */
    public function register_routes(): void
    {
        // Check if user exists (legacy, kept for compat)
        register_rest_route(self::NAMESPACE, '/check-user', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_check_user'],
            'permission_callback' => '__return_true',
            'args' => [
                'identifier' => ['required' => true, 'type' => 'string'],
                'type' => ['required' => true, 'type' => 'string'],
            ],
        ]);

        // Lookup: returns masked email/phone, available methods, passkey status
        register_rest_route(self::NAMESPACE, '/lookup', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_lookup'],
            'permission_callback' => '__return_true',
            'args' => [
                'identifier' => ['required' => true, 'type' => 'string'],
                'type' => ['required' => true, 'type' => 'string'],
            ],
        ]);

        // Email OTP endpoints
        register_rest_route(self::NAMESPACE, '/email/send', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_email_send'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(self::NAMESPACE, '/email/verify', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_email_verify'],
            'permission_callback' => '__return_true',
        ]);

        // WhatsApp OTP endpoints
        register_rest_route(self::NAMESPACE, '/whatsapp/send', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_whatsapp_send'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(self::NAMESPACE, '/whatsapp/verify', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_whatsapp_verify'],
            'permission_callback' => '__return_true',
        ]);

        // Magic link
        register_rest_route(self::NAMESPACE, '/magic-link', [
            'methods' => 'GET',
            'callback' => [$this, 'handle_magic_link'],
            'permission_callback' => '__return_true',
        ]);

        // QR Handshake endpoints
        register_rest_route(self::NAMESPACE, '/qr/generate', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_qr_generate'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(self::NAMESPACE, '/qr/poll', [
            'methods' => 'GET',
            'callback' => [$this, 'handle_qr_poll'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(self::NAMESPACE, '/qr/authorize', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_qr_authorize'],
            'permission_callback' => [$this, 'check_logged_in'],
        ]);

        // Passkeys endpoints — accept either logged-in cookie OR ww_token from login response
        register_rest_route(self::NAMESPACE, '/passkeys/register/options', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_passkey_register_options'],
            'permission_callback' => [$this, 'check_logged_in_or_token'],
        ]);

        register_rest_route(self::NAMESPACE, '/passkeys/register/verify', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_passkey_register_verify'],
            'permission_callback' => [$this, 'check_logged_in_or_token'],
        ]);

        register_rest_route(self::NAMESPACE, '/passkeys/login/options', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_passkey_login_options'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(self::NAMESPACE, '/passkeys/login/verify', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_passkey_login_verify'],
            'permission_callback' => '__return_true',
        ]);

        // Status endpoint
        register_rest_route(self::NAMESPACE, '/status', [
            'methods' => 'GET',
            'callback' => [$this, 'handle_status'],
            'permission_callback' => '__return_true',
        ]);

        // Current-user quick check (used by login page to skip form if already logged in)
        register_rest_route(self::NAMESPACE, '/me', [
            'methods' => 'GET',
            'callback' => [$this, 'handle_me'],
            'permission_callback' => '__return_true',
        ]);
    }

    public function check_logged_in(): bool
    {
        return is_user_logged_in();
    }

    /**
     * Allow either a real cookie-authenticated WP user OR a short-lived
     * ww_token issued at OTP verify time. Solves host/cookie quirks that
     * break REST auth immediately after wp_set_auth_cookie.
     *
     * When the token is valid we also set the current user so the handler
     * sees the right identity.
     */
    public function check_logged_in_or_token(WP_REST_Request $request): bool
    {
        if (is_user_logged_in()) return true;

        $token = $request->get_header('X-WW-Auth-Token') ?: (string) $request->get_param('ww_token');
        if (empty($token)) return false;

        $user_id = (int) get_transient('ww_auth_token_' . $token);
        if ($user_id <= 0) return false;

        $u = get_user_by('id', $user_id);
        if (!$u) return false;

        wp_set_current_user($user_id);
        return true;
    }

    /**
     * Issue a short-lived (15 min) auth token bound to a user_id.
     */
    private function issue_auth_token(int $user_id): string
    {
        $token = bin2hex(random_bytes(24));
        set_transient('ww_auth_token_' . $token, $user_id, 15 * MINUTE_IN_SECONDS);
        return $token;
    }

    /**
     * Check if user exists by email or phone
     */
    public function handle_check_user(WP_REST_Request $request): WP_REST_Response
    {
        $identifier = sanitize_text_field($request->get_param('identifier'));
        $type = sanitize_text_field($request->get_param('type'));

        $user = null;

        if ($type === 'email') {
            $user = get_user_by('email', $identifier);
        } elseif ($type === 'phone') {
            // Clean phone number
            $phone = preg_replace('/[^0-9+]/', '', $identifier);
            
            // Search in user meta
            $users = get_users([
                'meta_query' => [
                    'relation' => 'OR',
                    ['key' => 'billing_phone', 'value' => $phone, 'compare' => 'LIKE'],
                    ['key' => 'ww_auth_phone', 'value' => $phone, 'compare' => 'LIKE'],
                ],
                'number' => 1,
            ]);
            
            if (!empty($users)) {
                $user = $users[0];
            }
        }

        if ($user) {
            $has_passkey = !empty(get_user_meta($user->ID, 'ww_auth_passkeys', true));
            
            return new WP_REST_Response([
                'exists' => true,
                'user_id' => $user->ID,
                'has_passkey' => $has_passkey,
            ], 200);
        }

        return new WP_REST_Response([
            'exists' => false,
        ], 200);
    }

    /**
     * Lookup user by email OR phone, return masked contact info + available methods.
     *
     * Matches against:
     *   - email  -> user_email, billing_email
     *   - phone  -> billing_phone, ww_auth_phone (digits only, suffix match)
     *
     * Never leaks full email/phone — always returns masked values so arbitrary
     * numbers cannot be used to reveal another customer's contact details.
     */
    public function handle_lookup(WP_REST_Request $request): WP_REST_Response
    {
        $identifier = sanitize_text_field($request->get_param('identifier'));
        $type = sanitize_text_field($request->get_param('type'));

        $user = $this->find_user_by_identifier($identifier, $type);

        if (!$user) {
            return new WP_REST_Response(['exists' => false], 200);
        }

        $passkeys = get_user_meta($user->ID, 'ww_auth_passkeys', true);
        $has_passkey = is_array($passkeys) && !empty($passkeys);

        $email = $user->user_email ?: get_user_meta($user->ID, 'billing_email', true);
        $phone = get_user_meta($user->ID, 'billing_phone', true)
               ?: get_user_meta($user->ID, 'ww_auth_phone', true);

        $email_enabled = (bool) get_option('ww_auth_email_enabled', true);
        $whatsapp_enabled = (bool) get_option('ww_auth_whatsapp_enabled', false)
                         && WW_WhatsApp::instance()->is_enabled();

        return new WP_REST_Response([
            'exists' => true,
            'user_id' => $user->ID,
            'display_name' => $user->display_name,
            'has_passkey' => $has_passkey,
            'masked_email' => $email ? $this->mask_email($email) : '',
            'masked_phone' => $phone ? $this->mask_phone($phone) : '',
            'can_email' => $email_enabled && !empty($email),
            'can_whatsapp' => $whatsapp_enabled && !empty($phone),
        ], 200);
    }

    /**
     * Find a WP user by email-or-phone identifier.
     *
     * For phones we match the last 10 digits against billing_phone or ww_auth_phone
     * using LIKE '%suffix' so numbers stored with country codes still resolve.
     */
    private function find_user_by_identifier(string $identifier, string $type): ?WP_User
    {
        global $wpdb;

        if ($type === 'email') {
            $email = sanitize_email($identifier);
            if (empty($email)) return null;

            $user = get_user_by('email', $email);
            if ($user) return $user;

            // Also check billing_email meta
            $q = get_users([
                'meta_key' => 'billing_email',
                'meta_value' => $email,
                'number' => 1,
                'search_columns' => [],
            ]);
            return !empty($q) ? $q[0] : null;
        }

        if ($type === 'phone') {
            $digits = preg_replace('/\D/', '', $identifier);
            if (strlen($digits) < 10) return null;

            // Match against the last 10 digits (tolerates stored format variations).
            $suffix = substr($digits, -10);
            $like_suffix = '%' . $wpdb->esc_like($suffix);

            $q = get_users([
                'meta_query' => [
                    'relation' => 'OR',
                    ['key' => 'billing_phone',  'value' => $like_suffix, 'compare' => 'LIKE'],
                    ['key' => 'ww_auth_phone', 'value' => $like_suffix, 'compare' => 'LIKE'],
                ],
                'number' => 1,
            ]);
            if (!empty($q)) return $q[0];

            // Last-chance: raw SQL scan across usermeta for any phone-like field.
            $row = $wpdb->get_row($wpdb->prepare(
                "SELECT user_id FROM {$wpdb->usermeta}
                 WHERE (meta_key = 'billing_phone' OR meta_key = 'ww_auth_phone' OR meta_key = 'phone')
                   AND REPLACE(REPLACE(REPLACE(REPLACE(meta_value,' ',''),'-',''),'+',''),'(','') LIKE %s
                 LIMIT 1",
                '%' . $wpdb->esc_like($suffix)
            ));
            if ($row && !empty($row->user_id)) return get_user_by('id', (int) $row->user_id);
        }

        return null;
    }

    /**
     * Mask email: "tryhardweew@gmail.com" -> "try*****weew@gmail.com"
     * Keeps first 3 chars, reveals last 4 chars before @, hides middle.
     */
    private function mask_email(string $email): string
    {
        $parts = explode('@', $email);
        if (count($parts) !== 2) return $email;

        [$local, $domain] = $parts;
        $len = strlen($local);

        if ($len <= 4) {
            $masked_local = substr($local, 0, 1) . str_repeat('*', max(1, $len - 1));
        } elseif ($len <= 7) {
            $masked_local = substr($local, 0, 2) . str_repeat('*', 3) . substr($local, -2);
        } else {
            $masked_local = substr($local, 0, 3) . str_repeat('*', 5) . substr($local, -4);
        }

        return $masked_local . '@' . $domain;
    }

    /**
     * Mask phone: "+919876543210" -> "+91 98***43210"
     */
    private function mask_phone(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone);
        $len = strlen($digits);
        if ($len < 6) return str_repeat('*', $len);

        $prefix = substr($digits, 0, 2);
        $suffix = substr($digits, -4);
        $middle = str_repeat('*', max(3, $len - 6));

        // Try to prepend country code if the number starts with one
        if ($len >= 12) {
            // e.g. 919876543210 -> +91 98***43210
            $cc = substr($digits, 0, 2);
            return '+' . $cc . ' ' . substr($digits, 2, 2) . $middle . $suffix;
        }

        return $prefix . $middle . $suffix;
    }

    /**
     * Handle Email OTP send with premium HTML email
     */
    public function handle_email_send(WP_REST_Request $request): WP_REST_Response
    {
        $rate_limiter = WW_Rate_Limiter::instance();

        if ($rate_limiter->is_blocked()) {
            return new WP_REST_Response([
                'success' => false,
                'error' => 'Too many attempts. Please try again later.',
            ], 429);
        }

        $email = sanitize_email($request->get_param('email'));
        $name = sanitize_text_field($request->get_param('name') ?? '');
        $phone = sanitize_text_field($request->get_param('phone') ?? '');
        $user_id = (int) $request->get_param('user_id');

        // If a user_id is provided (from lookup flow), use the canonical email
        // from the DB — never trust a client-provided email for an existing user.
        if ($user_id > 0) {
            $u = get_user_by('id', $user_id);
            if ($u) {
                $email = $u->user_email;
            }
        }

        if (!is_email($email)) {
            return new WP_REST_Response([
                'success' => false,
                'error' => 'Invalid email address.',
            ], 400);
        }

        // Generate OTP
        $otp = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        
        // Generate magic link token
        $magic_token = bin2hex(random_bytes(32));

        // Store OTP and magic token
        $key = 'ww_email_otp_' . md5($email);
        set_transient($key, [
            'otp' => $otp,
            'magic_token' => $magic_token,
            'attempts' => 0,
            'created' => time(),
            'name' => $name,
            'phone' => $phone,
        ], 10 * MINUTE_IN_SECONDS);

        // Build magic link URL
        $magic_link = add_query_arg([
            'ww_magic' => $magic_token,
            'email' => urlencode($email),
        ], home_url('/secure-login/'));

        // Send premium HTML email
        $site_name = get_bloginfo('name');
        $company_name = get_option('ww_auth_email_company_name', '') ?: $site_name;
        $primary_color = get_option('ww_auth_primary_color', '#10B981');
        
        $subject = "Your verification code: {$otp}";
        
        $html_message = $this->get_email_template($otp, $magic_link, $site_name, $company_name, $primary_color);

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $site_name . ' <noreply@' . parse_url(home_url(), PHP_URL_HOST) . '>',
        ];

        $sent = wp_mail($email, $subject, $html_message, $headers);

        if (!$sent) {
            $rate_limiter->record_failure();
            return new WP_REST_Response([
                'success' => false,
                'error' => 'Failed to send verification email.',
            ], 500);
        }

        return new WP_REST_Response([
            'success' => true,
            'message' => 'Verification code sent to your email.',
        ], 200);
    }

    /**
     * Premium HTML Email Template (bold company-name header, no logo image).
     */
    private function get_email_template(string $otp, string $magic_link, string $site_name, string $company_name, string $primary_color): string
    {
        $otp_digits = str_split($otp);
        
        return '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verification Code</title>
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif; background-color: #f3f4f6;">
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color: #f3f4f6; padding: 40px 20px;">
        <tr>
            <td align="center">
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="max-width: 480px; background-color: #ffffff; border-radius: 16px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);">
                    <!-- Header -->
                    <tr>
                        <td style="padding: 44px 40px 28px; text-align: center; background: linear-gradient(135deg, ' . esc_attr($primary_color) . ' 0%, #059669 100%); border-radius: 16px 16px 0 0;">
                            <div style="color: #ffffff; font-size: 28px; font-weight: 800; letter-spacing: -0.5px; line-height: 1.2;">
                                ' . esc_html($company_name) . '
                            </div>
                            <h1 style="margin: 14px 0 0; color: rgba(255,255,255,0.95); font-size: 18px; font-weight: 500;">Verification Code</h1>
                        </td>
                    </tr>
                    
                    <!-- Body -->
                    <tr>
                        <td style="padding: 32px 40px;">
                            <p style="margin: 0 0 24px; color: #4b5563; font-size: 15px; line-height: 1.6; text-align: center;">
                                Enter this code to verify your identity and complete your sign in:
                            </p>
                            
                            <!-- OTP Code Boxes -->
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" align="center" style="margin: 0 auto 24px;">
                                <tr>
                                    <td style="padding: 0 6px;">
                                        <table role="presentation" cellspacing="0" cellpadding="0" border="0"><tr><td width="56" height="64" align="center" valign="middle" style="background: #ffffff; border: 2px solid ' . esc_attr($primary_color) . '; border-radius: 14px; font-size: 32px; font-weight: 900; color: #0f172a; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', sans-serif; line-height: 60px; text-align:center;">' . $otp_digits[0] . '</td></tr></table>
                                    </td>
                                    <td style="padding: 0 6px;">
                                        <table role="presentation" cellspacing="0" cellpadding="0" border="0"><tr><td width="56" height="64" align="center" valign="middle" style="background: #ffffff; border: 2px solid ' . esc_attr($primary_color) . '; border-radius: 14px; font-size: 32px; font-weight: 900; color: #0f172a; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', sans-serif; line-height: 60px; text-align:center;">' . $otp_digits[1] . '</td></tr></table>
                                    </td>
                                    <td style="padding: 0 6px;">
                                        <table role="presentation" cellspacing="0" cellpadding="0" border="0"><tr><td width="56" height="64" align="center" valign="middle" style="background: #ffffff; border: 2px solid ' . esc_attr($primary_color) . '; border-radius: 14px; font-size: 32px; font-weight: 900; color: #0f172a; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', sans-serif; line-height: 60px; text-align:center;">' . $otp_digits[2] . '</td></tr></table>
                                    </td>
                                    <td style="padding: 0 6px;">
                                        <table role="presentation" cellspacing="0" cellpadding="0" border="0"><tr><td width="56" height="64" align="center" valign="middle" style="background: #ffffff; border: 2px solid ' . esc_attr($primary_color) . '; border-radius: 14px; font-size: 32px; font-weight: 900; color: #0f172a; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', sans-serif; line-height: 60px; text-align:center;">' . $otp_digits[3] . '</td></tr></table>
                                    </td>
                                </tr>
                            </table>
                            
                            <p style="margin: 0 0 8px; color: #9ca3af; font-size: 13px; text-align: center;">
                                This code expires in 10 minutes
                            </p>
                            
                            <!-- Divider -->
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin: 28px 0;">
                                <tr>
                                    <td style="border-top: 1px solid #e5e7eb;"></td>
                                    <td style="padding: 0 16px; color: #9ca3af; font-size: 13px; white-space: nowrap;">or use magic link</td>
                                    <td style="border-top: 1px solid #e5e7eb;"></td>
                                </tr>
                            </table>
                            
                            <!-- Magic Link Button -->
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" align="center">
                                <tr>
                                    <td style="background: linear-gradient(135deg, ' . esc_attr($primary_color) . ' 0%, #059669 100%); border-radius: 10px;">
                                        <a href="' . esc_url($magic_link) . '" style="display: inline-block; padding: 14px 32px; color: #ffffff; font-size: 15px; font-weight: 600; text-decoration: none;">
                                            Sign In Instantly →
                                        </a>
                                    </td>
                                </tr>
                            </table>
                            
                            <p style="margin: 24px 0 0; color: #9ca3af; font-size: 13px; text-align: center; line-height: 1.5;">
                                Click the button above to sign in without entering the code.
                            </p>
                        </td>
                    </tr>
                    
                    <!-- Footer -->
                    <tr>
                        <td style="padding: 24px 40px; background: #f9fafb; border-radius: 0 0 16px 16px; text-align: center;">
                            <p style="margin: 0 0 8px; color: #6b7280; font-size: 13px;">
                                Didn\'t request this? You can safely ignore this email.
                            </p>
                            <p style="margin: 0; color: #9ca3af; font-size: 12px;">
                                © ' . date('Y') . ' <strong>' . esc_html($company_name) . '</strong>. All rights reserved.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>';
    }

    /**
     * Handle Email OTP verification
     */
    public function handle_email_verify(WP_REST_Request $request): WP_REST_Response
    {
        $rate_limiter = WW_Rate_Limiter::instance();

        if ($rate_limiter->is_blocked()) {
            return new WP_REST_Response([
                'success' => false,
                'error' => 'Too many attempts. Please try again later.',
            ], 429);
        }

        $email = sanitize_email($request->get_param('email'));
        $otp = sanitize_text_field($request->get_param('otp'));
        $user_id = (int) $request->get_param('user_id');

        // If client can't provide a real email (e.g. user came in via phone lookup
        // and we sent OTP to their account email), resolve it server-side.
        if (!is_email($email) && $user_id > 0) {
            $u = get_user_by('id', $user_id);
            if ($u) $email = $u->user_email;
        }

        $key = 'ww_email_otp_' . md5($email);
        $data = get_transient($key);

        if ($data === false) {
            return new WP_REST_Response([
                'success' => false,
                'error' => 'Code expired. Please request a new one.',
            ], 400);
        }

        if (($data['attempts'] ?? 0) >= 3) {
            delete_transient($key);
            return new WP_REST_Response([
                'success' => false,
                'error' => 'Too many failed attempts. Please request a new code.',
            ], 400);
        }

        $data['attempts'] = ($data['attempts'] ?? 0) + 1;
        set_transient($key, $data, 10 * MINUTE_IN_SECONDS);

        if ($data['otp'] !== $otp) {
            $rate_limiter->record_failure();
            return new WP_REST_Response([
                'success' => false,
                'error' => sprintf('Invalid code. %d attempts remaining.', 3 - $data['attempts']),
            ], 400);
        }

        delete_transient($key);

        // Find or create user
        $user = get_user_by('email', $email);

        if (!$user) {
            // Create new user
            $username = sanitize_user(strstr($email, '@', true));
            $counter = 1;
            $base_username = $username;

            while (username_exists($username)) {
                $username = $base_username . '_' . $counter;
                $counter++;
            }

            $user_id = wp_insert_user([
                'user_login' => $username,
                'user_email' => $email,
                'user_pass' => wp_generate_password(16, true, true),
                'role' => 'customer',
                'display_name' => $data['name'] ?? $username,
            ]);

            if (is_wp_error($user_id)) {
                return new WP_REST_Response([
                    'success' => false,
                    'error' => 'Failed to create account.',
                ], 500);
            }

            // Store phone if provided
            if (!empty($data['phone'])) {
                update_user_meta($user_id, 'billing_phone', $data['phone']);
                update_user_meta($user_id, 'ww_auth_phone', preg_replace('/[^0-9]/', '', $data['phone']));
            }

            $user = get_user_by('id', $user_id);
        }

        // Log user in
        wp_set_current_user($user->ID);
        wp_set_auth_cookie($user->ID, true);
        do_action('wp_login', $user->user_login, $user);

        $rate_limiter->record_success();

        $has_passkey = !empty(get_user_meta($user->ID, 'ww_auth_passkeys', true));

        return new WP_REST_Response([
            'success' => true,
            'user' => [
                'id' => $user->ID,
                'display_name' => $user->display_name,
                'has_passkey' => $has_passkey,
            ],
            'nonce' => wp_create_nonce('wp_rest'),
            'ww_token' => $this->issue_auth_token($user->ID),
            'redirect' => $this->get_redirect_url($user),
        ], 200);
    }

    /**
     * Handle magic link login
     *
     * Accepts either `?token=` or `?ww_magic=` (the email's URL uses ww_magic).
     */
    public function handle_magic_link(WP_REST_Request $request): WP_REST_Response
    {
        $token = sanitize_text_field($request->get_param('token') ?? '');
        if (empty($token)) {
            $token = sanitize_text_field($request->get_param('ww_magic') ?? '');
        }
        $email = sanitize_email($request->get_param('email'));

        if (empty($token) || empty($email)) {
            return new WP_REST_Response(['success' => false, 'error' => 'Invalid link.'], 400);
        }

        $key = 'ww_email_otp_' . md5($email);
        $data = get_transient($key);

        if ($data === false || ($data['magic_token'] ?? '') !== $token) {
            return new WP_REST_Response(['success' => false, 'error' => 'Link expired or invalid.'], 400);
        }

        delete_transient($key);

        // Find or create user (same as verify)
        $user = get_user_by('email', $email);

        if (!$user) {
            $username = sanitize_user(strstr($email, '@', true));
            $counter = 1;
            $base_username = $username;

            while (username_exists($username)) {
                $username = $base_username . '_' . $counter;
                $counter++;
            }

            $user_id = wp_insert_user([
                'user_login' => $username,
                'user_email' => $email,
                'user_pass' => wp_generate_password(16, true, true),
                'role' => 'customer',
            ]);

            if (is_wp_error($user_id)) {
                return new WP_REST_Response(['success' => false, 'error' => 'Failed to create account.'], 500);
            }

            $user = get_user_by('id', $user_id);
        }

        wp_set_current_user($user->ID);
        wp_set_auth_cookie($user->ID, true);
        do_action('wp_login', $user->user_login, $user);

        return new WP_REST_Response([
            'success' => true,
            'nonce' => wp_create_nonce('wp_rest'),
            'ww_token' => $this->issue_auth_token($user->ID),
            'redirect' => $this->get_redirect_url($user),
        ], 200);
    }

    /**
     * Handle WhatsApp OTP send
     */
    public function handle_whatsapp_send(WP_REST_Request $request): WP_REST_Response
    {
        $rate_limiter = WW_Rate_Limiter::instance();

        if ($rate_limiter->is_blocked()) {
            return new WP_REST_Response([
                'success' => false,
                'error' => 'Too many attempts. Please try again later.',
            ], 429);
        }

        $whatsapp = WW_WhatsApp::instance();

        if (!$whatsapp->is_enabled()) {
            return new WP_REST_Response([
                'success' => false,
                'error' => 'WhatsApp authentication is not available.',
            ], 400);
        }

        $phone = sanitize_text_field($request->get_param('phone'));
        $result = $whatsapp->send_otp($phone);

        if (!$result['success']) {
            $rate_limiter->record_failure();
            return new WP_REST_Response($result, 400);
        }

        return new WP_REST_Response([
            'success' => true,
            'message' => 'Verification code sent to WhatsApp.',
        ], 200);
    }

    /**
     * Handle WhatsApp OTP verification
     */
    public function handle_whatsapp_verify(WP_REST_Request $request): WP_REST_Response
    {
        $rate_limiter = WW_Rate_Limiter::instance();

        if ($rate_limiter->is_blocked()) {
            return new WP_REST_Response([
                'success' => false,
                'error' => 'Too many attempts. Please try again later.',
            ], 429);
        }

        $phone = sanitize_text_field($request->get_param('phone'));
        $otp = sanitize_text_field($request->get_param('otp'));

        $whatsapp = WW_WhatsApp::instance();
        $result = $whatsapp->verify_otp($phone, $otp);

        if (!$result['success']) {
            $rate_limiter->record_failure();
            return new WP_REST_Response($result, 400);
        }

        $user = $whatsapp->get_or_create_user($phone);

        if ($user === null) {
            $user_id = $whatsapp->create_user_from_phone($phone);
            if ($user_id === null) {
                return new WP_REST_Response([
                    'success' => false,
                    'error' => 'Failed to create user account.',
                ], 500);
            }
            $user = get_user_by('id', $user_id);
        }

        wp_set_current_user($user->ID);
        wp_set_auth_cookie($user->ID, true);
        do_action('wp_login', $user->user_login, $user);

        $rate_limiter->record_success();

        $has_passkey = !empty(get_user_meta($user->ID, 'ww_auth_passkeys', true));

        return new WP_REST_Response([
            'success' => true,
            'user' => [
                'id' => $user->ID,
                'display_name' => $user->display_name,
                'has_passkey' => $has_passkey,
            ],
            'nonce' => wp_create_nonce('wp_rest'),
            'ww_token' => $this->issue_auth_token($user->ID),
            'redirect' => $this->get_redirect_url($user),
        ], 200);
    }

    /**
     * Handle QR session generation
     */
    public function handle_qr_generate(WP_REST_Request $request): WP_REST_Response
    {
        $qr = WW_QR_Handshake::instance();
        $session = $qr->create_session();

        return new WP_REST_Response([
            'success' => true,
            'session_id' => $session['id'],
            'qr_data' => $session['qr_data'],
            'expires_in' => $session['expires_in'],
        ], 200);
    }

    /**
     * Handle QR polling
     */
    public function handle_qr_poll(WP_REST_Request $request): WP_REST_Response
    {
        $session_id = sanitize_text_field($request->get_param('session_id'));
        $qr = WW_QR_Handshake::instance();
        $status = $qr->get_session_status($session_id);

        if ($status === null) {
            return new WP_REST_Response([
                'success' => false,
                'status' => 'expired',
                'error' => 'Session expired. Please refresh.',
            ], 400);
        }

        if ($status['authorized'] && $status['user_id']) {
            $user = get_user_by('id', $status['user_id']);
            if ($user) {
                wp_set_current_user($user->ID);
                wp_set_auth_cookie($user->ID, true);
                do_action('wp_login', $user->user_login, $user);

                $qr->clear_session($session_id);

                return new WP_REST_Response([
                    'success' => true,
                    'status' => 'authorized',
                    'user' => [
                        'id' => $user->ID,
                        'display_name' => $user->display_name,
                    ],
                    'redirect' => $this->get_redirect_url($user),
                ], 200);
            }
        }

        return new WP_REST_Response([
            'success' => true,
            'status' => 'pending',
        ], 200);
    }

    /**
     * Handle QR authorization
     */
    public function handle_qr_authorize(WP_REST_Request $request): WP_REST_Response
    {
        $session_id = sanitize_text_field($request->get_param('session_id'));
        $user_id = get_current_user_id();

        $qr = WW_QR_Handshake::instance();
        $result = $qr->authorize_session($session_id, $user_id);

        if (!$result['success']) {
            return new WP_REST_Response($result, 400);
        }

        return new WP_REST_Response([
            'success' => true,
            'message' => 'Login authorized successfully.',
        ], 200);
    }

    /**
     * Handle Passkey registration options
     */
    public function handle_passkey_register_options(WP_REST_Request $request): WP_REST_Response
    {
        $passkeys = WW_Passkeys::instance();
        $options = $passkeys->get_registration_options();

        return new WP_REST_Response([
            'success' => true,
            'options' => $options,
        ], 200);
    }

    /**
     * Handle Passkey registration verification
     */
    public function handle_passkey_register_verify(WP_REST_Request $request): WP_REST_Response
    {
        $passkeys = WW_Passkeys::instance();
        $credential = $request->get_json_params();
        $result = $passkeys->verify_registration($credential);

        if (!$result['success']) {
            return new WP_REST_Response($result, 400);
        }

        // Update user meta to indicate passkey is set up
        $user_id = get_current_user_id();
        update_user_meta($user_id, 'ww_auth_passkey_enabled', true);
        update_user_meta($user_id, 'ww_auth_passkey_setup_date', current_time('mysql'));

        return new WP_REST_Response([
            'success' => true,
            'message' => 'Passkey registered successfully.',
        ], 200);
    }

    /**
     * Handle Passkey login options
     */
    public function handle_passkey_login_options(WP_REST_Request $request): WP_REST_Response
    {
        $user_id = (int) $request->get_param('user_id');
        $passkeys = WW_Passkeys::instance();
        $options = $passkeys->get_authentication_options($user_id > 0 ? $user_id : null);

        return new WP_REST_Response([
            'success' => true,
            'options' => $options,
        ], 200);
    }

    /**
     * Handle Passkey login verification
     */
    public function handle_passkey_login_verify(WP_REST_Request $request): WP_REST_Response
    {
        $passkeys = WW_Passkeys::instance();
        $credential = $request->get_json_params();
        $result = $passkeys->verify_authentication($credential);

        if (!$result['success']) {
            WW_Rate_Limiter::instance()->record_failure();
            return new WP_REST_Response($result, 400);
        }

        $user = get_user_by('id', $result['user_id']);
        wp_set_current_user($user->ID);
        wp_set_auth_cookie($user->ID, true);
        do_action('wp_login', $user->user_login, $user);

        WW_Rate_Limiter::instance()->record_success();

        return new WP_REST_Response([
            'success' => true,
            'user' => [
                'id' => $user->ID,
                'display_name' => $user->display_name,
            ],
            'nonce' => wp_create_nonce('wp_rest'),
            'ww_token' => $this->issue_auth_token($user->ID),
            'redirect' => $this->get_redirect_url($user),
        ], 200);
    }

    /**
     * Handle status check
     */
    public function handle_status(WP_REST_Request $request): WP_REST_Response
    {
        return new WP_REST_Response([
            'success' => true,
            'authenticated' => is_user_logged_in(),
            'methods' => [
                'whatsapp' => WW_WhatsApp::instance()->is_enabled(),
                'email' => (bool) get_option('ww_auth_email_enabled', true),
                'passkeys' => (bool) get_option('ww_auth_passkeys_enabled', false),
                'qr' => (bool) get_option('ww_auth_qr_enabled', true),
            ],
            'turnstile' => WW_Turnstile::instance()->is_enabled(),
            'rate_limit' => WW_Rate_Limiter::instance()->get_status(),
        ], 200);
    }

    /**
     * Return the currently-logged-in user (or logged_in:false).
     */
    public function handle_me(WP_REST_Request $request): WP_REST_Response
    {
        if (!is_user_logged_in()) {
            return new WP_REST_Response(['logged_in' => false], 200);
        }
        $u = wp_get_current_user();
        $has_pk = !empty(get_user_meta($u->ID, 'ww_auth_passkeys', true));
        return new WP_REST_Response([
            'logged_in' => true,
            'user' => [
                'id' => $u->ID,
                'display_name' => $u->display_name,
                'email' => $u->user_email,
                'has_passkey' => $has_pk,
            ],
        ], 200);
    }

    /**
     * Get redirect URL after login
     */
    private function get_redirect_url(WP_User $user): string
    {
        $redirect = isset($_REQUEST['redirect_to']) ? esc_url_raw($_REQUEST['redirect_to']) : '';

        if (empty($redirect)) {
            if (class_exists('WooCommerce')) {
                $redirect = wc_get_account_endpoint_url('dashboard');
            } else {
                $redirect = home_url('/');
            }
        }

        return apply_filters('ww_auth_login_redirect', $redirect, $user);
    }
}
