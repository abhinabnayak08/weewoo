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
        // Check if user exists
        register_rest_route(self::NAMESPACE, '/check-user', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_check_user'],
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

        // Passkeys endpoints
        register_rest_route(self::NAMESPACE, '/passkeys/register/options', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_passkey_register_options'],
            'permission_callback' => [$this, 'check_logged_in'],
        ]);

        register_rest_route(self::NAMESPACE, '/passkeys/register/verify', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_passkey_register_verify'],
            'permission_callback' => [$this, 'check_logged_in'],
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
    }

    public function check_logged_in(): bool
    {
        return is_user_logged_in();
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
        $logo_url = get_option('ww_auth_logo_url', '');
        $primary_color = get_option('ww_auth_primary_color', '#10B981');
        
        $subject = "Your verification code: {$otp}";
        
        $html_message = $this->get_email_template($otp, $magic_link, $site_name, $logo_url, $primary_color);

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
     * Premium HTML Email Template
     */
    private function get_email_template(string $otp, string $magic_link, string $site_name, string $logo_url, string $primary_color): string
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
                        <td style="padding: 40px 40px 24px; text-align: center; background: linear-gradient(135deg, ' . esc_attr($primary_color) . ' 0%, #059669 100%); border-radius: 16px 16px 0 0;">
                            ' . ($logo_url ? '<img src="' . esc_url($logo_url) . '" alt="' . esc_attr($site_name) . '" style="max-height: 48px; width: auto;">' : '<div style="width: 56px; height: 56px; background: rgba(255,255,255,0.2); border-radius: 14px; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 16px;"><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg></div>') . '
                            <h1 style="margin: 16px 0 0; color: #ffffff; font-size: 24px; font-weight: 600;">Verification Code</h1>
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
                                        <div style="width: 52px; height: 60px; background: #f9fafb; border: 2px solid #e5e7eb; border-radius: 12px; font-size: 28px; font-weight: 700; color: #111827; line-height: 56px; text-align: center;">' . $otp_digits[0] . '</div>
                                    </td>
                                    <td style="padding: 0 6px;">
                                        <div style="width: 52px; height: 60px; background: #f9fafb; border: 2px solid #e5e7eb; border-radius: 12px; font-size: 28px; font-weight: 700; color: #111827; line-height: 56px; text-align: center;">' . $otp_digits[1] . '</div>
                                    </td>
                                    <td style="padding: 0 6px;">
                                        <div style="width: 52px; height: 60px; background: #f9fafb; border: 2px solid #e5e7eb; border-radius: 12px; font-size: 28px; font-weight: 700; color: #111827; line-height: 56px; text-align: center;">' . $otp_digits[2] . '</div>
                                    </td>
                                    <td style="padding: 0 6px;">
                                        <div style="width: 52px; height: 60px; background: #f9fafb; border: 2px solid #e5e7eb; border-radius: 12px; font-size: 28px; font-weight: 700; color: #111827; line-height: 56px; text-align: center;">' . $otp_digits[3] . '</div>
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
                                © ' . date('Y') . ' ' . esc_html($site_name) . '. All rights reserved.
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
            'redirect' => $this->get_redirect_url($user),
        ], 200);
    }

    /**
     * Handle magic link login
     */
    public function handle_magic_link(WP_REST_Request $request): WP_REST_Response
    {
        $token = sanitize_text_field($request->get_param('token'));
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
        $passkeys = WW_Passkeys::instance();
        $options = $passkeys->get_authentication_options();

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
