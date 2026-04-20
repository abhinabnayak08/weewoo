<?php
/**
 * REST API Endpoints Class
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
        // API initialized on demand
    }

    /**
     * Register all REST routes
     */
    public function register_routes(): void
    {
        // WhatsApp OTP endpoints
        register_rest_route(self::NAMESPACE, '/whatsapp/send', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_whatsapp_send'],
            'permission_callback' => '__return_true',
            'args' => [
                'phone' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'turnstile_token' => [
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/whatsapp/verify', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_whatsapp_verify'],
            'permission_callback' => '__return_true',
            'args' => [
                'phone' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'otp' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);

        // Email OTP endpoints
        register_rest_route(self::NAMESPACE, '/email/send', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_email_send'],
            'permission_callback' => '__return_true',
            'args' => [
                'email' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_email',
                ],
                'turnstile_token' => [
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/email/verify', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_email_verify'],
            'permission_callback' => '__return_true',
            'args' => [
                'email' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_email',
                ],
                'otp' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
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
            'args' => [
                'session_id' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/qr/authorize', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_qr_authorize'],
            'permission_callback' => [$this, 'check_logged_in'],
            'args' => [
                'session_id' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
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

    /**
     * Permission callback for logged-in users
     */
    public function check_logged_in(): bool
    {
        return is_user_logged_in();
    }

    /**
     * Pre-check rate limit and Turnstile
     */
    private function pre_checks(WP_REST_Request $request): ?WP_REST_Response
    {
        $rate_limiter = WW_Rate_Limiter::instance();

        // Check rate limit
        if ($rate_limiter->is_blocked()) {
            $remaining = $rate_limiter->get_time_remaining();
            return new WP_REST_Response([
                'success' => false,
                'error' => sprintf(
                    __('Too many attempts. Please try again in %d minutes.', 'weewoo-auth-pro'),
                    ceil($remaining / 60)
                ),
                'blocked_for' => $remaining,
            ], 429);
        }

        // Check Turnstile
        $turnstile = WW_Turnstile::instance();
        if ($turnstile->is_enabled()) {
            $token = $request->get_param('turnstile_token') ?? '';
            $result = $turnstile->verify($token);

            if (!$result['success']) {
                return new WP_REST_Response([
                    'success' => false,
                    'error' => $result['error'],
                ], 400);
            }
        }

        return null;
    }

    /**
     * Handle WhatsApp OTP send
     */
    public function handle_whatsapp_send(WP_REST_Request $request): WP_REST_Response
    {
        $check = $this->pre_checks($request);
        if ($check !== null) {
            return $check;
        }

        $whatsapp = WW_WhatsApp::instance();

        if (!$whatsapp->is_enabled()) {
            return new WP_REST_Response([
                'success' => false,
                'error' => __('WhatsApp authentication is not available.', 'weewoo-auth-pro'),
            ], 400);
        }

        $phone = $request->get_param('phone');
        $result = $whatsapp->send_otp($phone);

        if (!$result['success']) {
            WW_Rate_Limiter::instance()->record_failure();
            return new WP_REST_Response($result, 400);
        }

        return new WP_REST_Response([
            'success' => true,
            'message' => __('Verification code sent to WhatsApp.', 'weewoo-auth-pro'),
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
                'error' => __('Too many attempts. Please try again later.', 'weewoo-auth-pro'),
            ], 429);
        }

        $phone = $request->get_param('phone');
        $otp = $request->get_param('otp');

        $whatsapp = WW_WhatsApp::instance();
        $result = $whatsapp->verify_otp($phone, $otp);

        if (!$result['success']) {
            $rate_limiter->record_failure();
            return new WP_REST_Response($result, 400);
        }

        // OTP verified - find or create user
        $user = $whatsapp->get_or_create_user($phone);

        if ($user === null) {
            // Create new user
            $user_id = $whatsapp->create_user_from_phone($phone);
            if ($user_id === null) {
                return new WP_REST_Response([
                    'success' => false,
                    'error' => __('Failed to create user account.', 'weewoo-auth-pro'),
                ], 500);
            }
            $user = get_user_by('id', $user_id);
        }

        // Log user in
        wp_set_current_user($user->ID);
        wp_set_auth_cookie($user->ID, true);
        do_action('wp_login', $user->user_login, $user);

        $rate_limiter->record_success();

        // Get redirect URL
        $redirect = $this->get_redirect_url($user);

        return new WP_REST_Response([
            'success' => true,
            'user' => [
                'id' => $user->ID,
                'display_name' => $user->display_name,
            ],
            'redirect' => $redirect,
        ], 200);
    }

    /**
     * Handle Email OTP send
     */
    public function handle_email_send(WP_REST_Request $request): WP_REST_Response
    {
        $check = $this->pre_checks($request);
        if ($check !== null) {
            return $check;
        }

        $email = $request->get_param('email');

        if (!is_email($email)) {
            return new WP_REST_Response([
                'success' => false,
                'error' => __('Invalid email address.', 'weewoo-auth-pro'),
            ], 400);
        }

        // Generate OTP
        $otp = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);

        // Store OTP
        $key = 'ww_email_otp_' . md5($email);
        set_transient($key, [
            'otp' => $otp,
            'attempts' => 0,
            'created' => time(),
        ], 10 * MINUTE_IN_SECONDS);

        // Send email
        $site_name = get_bloginfo('name');
        $subject = sprintf(__('[%s] Your verification code', 'weewoo-auth-pro'), $site_name);
        $message = sprintf(
            __("Your verification code is: %s\n\nThis code expires in 10 minutes.\n\nIf you didn't request this code, please ignore this email.", 'weewoo-auth-pro'),
            $otp
        );

        $sent = wp_mail($email, $subject, $message);

        if (!$sent) {
            WW_Rate_Limiter::instance()->record_failure();
            return new WP_REST_Response([
                'success' => false,
                'error' => __('Failed to send verification email.', 'weewoo-auth-pro'),
            ], 500);
        }

        return new WP_REST_Response([
            'success' => true,
            'message' => __('Verification code sent to your email.', 'weewoo-auth-pro'),
        ], 200);
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
                'error' => __('Too many attempts. Please try again later.', 'weewoo-auth-pro'),
            ], 429);
        }

        $email = $request->get_param('email');
        $otp = $request->get_param('otp');

        $key = 'ww_email_otp_' . md5($email);
        $data = get_transient($key);

        if ($data === false) {
            return new WP_REST_Response([
                'success' => false,
                'error' => __('Code expired. Please request a new one.', 'weewoo-auth-pro'),
            ], 400);
        }

        // Check attempts
        if (($data['attempts'] ?? 0) >= 3) {
            delete_transient($key);
            return new WP_REST_Response([
                'success' => false,
                'error' => __('Too many failed attempts. Please request a new code.', 'weewoo-auth-pro'),
            ], 400);
        }

        // Increment attempts
        $data['attempts'] = ($data['attempts'] ?? 0) + 1;
        set_transient($key, $data, 10 * MINUTE_IN_SECONDS);

        if ($data['otp'] !== $otp) {
            $rate_limiter->record_failure();
            return new WP_REST_Response([
                'success' => false,
                'error' => sprintf(
                    __('Invalid code. %d attempts remaining.', 'weewoo-auth-pro'),
                    3 - $data['attempts']
                ),
            ], 400);
        }

        // Delete OTP
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
            ]);

            if (is_wp_error($user_id)) {
                return new WP_REST_Response([
                    'success' => false,
                    'error' => __('Failed to create account.', 'weewoo-auth-pro'),
                ], 500);
            }

            $user = get_user_by('id', $user_id);
        }

        // Log user in
        wp_set_current_user($user->ID);
        wp_set_auth_cookie($user->ID, true);
        do_action('wp_login', $user->user_login, $user);

        $rate_limiter->record_success();

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
     * Handle QR polling (desktop)
     */
    public function handle_qr_poll(WP_REST_Request $request): WP_REST_Response
    {
        $session_id = $request->get_param('session_id');
        $qr = WW_QR_Handshake::instance();
        $status = $qr->get_session_status($session_id);

        if ($status === null) {
            return new WP_REST_Response([
                'success' => false,
                'status' => 'expired',
                'error' => __('Session expired. Please refresh.', 'weewoo-auth-pro'),
            ], 400);
        }

        if ($status['authorized'] && $status['user_id']) {
            // Log the user in
            $user = get_user_by('id', $status['user_id']);
            if ($user) {
                wp_set_current_user($user->ID);
                wp_set_auth_cookie($user->ID, true);
                do_action('wp_login', $user->user_login, $user);

                // Clear session
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
     * Handle QR authorization (mobile)
     */
    public function handle_qr_authorize(WP_REST_Request $request): WP_REST_Response
    {
        $session_id = $request->get_param('session_id');
        $user_id = get_current_user_id();

        $qr = WW_QR_Handshake::instance();
        $result = $qr->authorize_session($session_id, $user_id);

        if (!$result['success']) {
            return new WP_REST_Response($result, 400);
        }

        return new WP_REST_Response([
            'success' => true,
            'message' => __('Login authorized successfully.', 'weewoo-auth-pro'),
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

        return new WP_REST_Response([
            'success' => true,
            'message' => __('Passkey registered successfully.', 'weewoo-auth-pro'),
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

        // Log user in
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
        // Check for redirect_to parameter
        $redirect = isset($_REQUEST['redirect_to']) ? esc_url_raw($_REQUEST['redirect_to']) : '';

        if (empty($redirect)) {
            // WooCommerce my account
            if (class_exists('WooCommerce')) {
                $redirect = wc_get_account_endpoint_url('dashboard');
            } else {
                $redirect = admin_url();
            }
        }

        return apply_filters('ww_auth_login_redirect', $redirect, $user);
    }
}
