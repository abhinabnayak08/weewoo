<?php
/**
 * WhatsApp Gateway Class
 * 
 * Meta Cloud API v19+ integration for Authentication templates
 *
 * @package WeeWoo_Auth_Pro
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * WhatsApp OTP via Meta Cloud API
 */
final class WW_WhatsApp
{
    private static ?WW_WhatsApp $instance = null;
    private const API_VERSION = 'v19.0';
    private const API_BASE_URL = 'https://graph.facebook.com/';
    private const OTP_EXPIRY = 10 * MINUTE_IN_SECONDS; // 10 minutes
    private const OTP_LENGTH = 4;

    public static function instance(): WW_WhatsApp
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        // WhatsApp gateway initialized on demand
    }

    /**
     * Check if WhatsApp is enabled and configured
     */
    public function is_enabled(): bool
    {
        return (bool) get_option('ww_auth_whatsapp_enabled', false)
            && !empty($this->get_phone_id())
            && !empty($this->get_access_token());
    }

    /**
     * Get Phone Number ID from settings
     */
    private function get_phone_id(): string
    {
        return (string) get_option('ww_auth_meta_phone_id', '');
    }

    /**
     * Get Access Token from settings
     */
    private function get_access_token(): string
    {
        return (string) get_option('ww_auth_meta_access_token', '');
    }

    /**
     * Get Template Name from settings
     */
    private function get_template_name(): string
    {
        return (string) get_option('ww_auth_meta_template_name', 'authentication_otp');
    }

    /**
     * Get Template Language from settings
     */
    private function get_template_language(): string
    {
        return (string) get_option('ww_auth_meta_template_lang', 'en');
    }

    /**
     * Generate a secure 4-digit OTP
     */
    public function generate_otp(): string
    {
        return str_pad((string) random_int(0, 9999), self::OTP_LENGTH, '0', STR_PAD_LEFT);
    }

    /**
     * Format phone number for WhatsApp API (E.164 without +)
     */
    public function format_phone(string $phone): string
    {
        // Remove all non-numeric characters except leading +
        $phone = preg_replace('/[^0-9+]/', '', $phone);

        // Remove leading + if present
        $phone = ltrim($phone, '+');

        return $phone;
    }

    /**
     * Store OTP in transient for verification
     */
    private function store_otp(string $phone, string $otp): void
    {
        $key = 'ww_otp_' . md5($phone);
        set_transient($key, [
            'otp' => $otp,
            'attempts' => 0,
            'created' => time(),
        ], self::OTP_EXPIRY);
    }

    /**
     * Verify OTP for a phone number
     */
    public function verify_otp(string $phone, string $otp): array
    {
        $phone = $this->format_phone($phone);
        $key = 'ww_otp_' . md5($phone);
        $data = get_transient($key);

        if ($data === false) {
            return [
                'success' => false,
                'error' => __('OTP expired or not found. Please request a new code.', 'weewoo-auth-pro'),
            ];
        }

        // Check max verification attempts (3)
        if (($data['attempts'] ?? 0) >= 3) {
            delete_transient($key);
            return [
                'success' => false,
                'error' => __('Too many failed attempts. Please request a new code.', 'weewoo-auth-pro'),
            ];
        }

        // Increment attempts
        $data['attempts'] = ($data['attempts'] ?? 0) + 1;
        set_transient($key, $data, self::OTP_EXPIRY);

        // Verify OTP
        if ($data['otp'] !== $otp) {
            return [
                'success' => false,
                'error' => sprintf(
                    __('Invalid code. %d attempts remaining.', 'weewoo-auth-pro'),
                    3 - $data['attempts']
                ),
            ];
        }

        // Success - delete the OTP
        delete_transient($key);

        return ['success' => true];
    }

    /**
     * Send OTP via WhatsApp
     *
     * @param string $phone Phone number in E.164 format
     * @return array{success: bool, error?: string, message_id?: string}
     */
    public function send_otp(string $phone): array
    {
        if (!$this->is_enabled()) {
            return [
                'success' => false,
                'error' => __('WhatsApp authentication is not configured.', 'weewoo-auth-pro'),
            ];
        }

        $phone = $this->format_phone($phone);

        if (strlen($phone) < 10) {
            return [
                'success' => false,
                'error' => __('Invalid phone number format.', 'weewoo-auth-pro'),
            ];
        }

        // Generate OTP
        $otp = $this->generate_otp();

        // Build API URL
        $url = self::API_BASE_URL . self::API_VERSION . '/' . $this->get_phone_id() . '/messages';

        // Build request body for Authentication template with Copy Code button
        $body = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $phone,
            'type' => 'template',
            'template' => [
                'name' => $this->get_template_name(),
                'language' => [
                    'code' => $this->get_template_language(),
                ],
                'components' => [
                    [
                        'type' => 'body',
                        'parameters' => [
                            [
                                'type' => 'text',
                                'text' => $otp,
                            ],
                        ],
                    ],
                    [
                        'type' => 'button',
                        'sub_type' => 'url',
                        'index' => '0',
                        'parameters' => [
                            [
                                'type' => 'text',
                                'text' => $otp,
                            ],
                        ],
                    ],
                ],
            ],
        ];

        // Send request
        $response = wp_remote_post($url, [
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->get_access_token(),
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode($body),
        ]);

        if (is_wp_error($response)) {
            error_log('WeeWoo Auth WhatsApp Error: ' . $response->get_error_message());
            return [
                'success' => false,
                'error' => __('Failed to send WhatsApp message. Please try again.', 'weewoo-auth-pro'),
            ];
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($status_code !== 200) {
            $error_message = $body['error']['message'] ?? 'Unknown error';
            error_log('WeeWoo Auth WhatsApp API Error: ' . $error_message);

            // User-friendly error messages
            if (strpos($error_message, 'Invalid OAuth') !== false) {
                return [
                    'success' => false,
                    'error' => __('WhatsApp service configuration error. Please contact support.', 'weewoo-auth-pro'),
                ];
            }

            return [
                'success' => false,
                'error' => __('Failed to send verification code. Please try again.', 'weewoo-auth-pro'),
            ];
        }

        // Store OTP for verification
        $this->store_otp($phone, $otp);

        return [
            'success' => true,
            'message_id' => $body['messages'][0]['id'] ?? null,
        ];
    }

    /**
     * Find or create user by phone number
     */
    public function get_or_create_user(string $phone): ?WP_User
    {
        $phone = $this->format_phone($phone);

        // Search for existing user with this phone
        $users = get_users([
            'meta_key' => 'ww_auth_phone',
            'meta_value' => $phone,
            'number' => 1,
        ]);

        if (!empty($users)) {
            return $users[0];
        }

        // Check if phone might be stored as billing_phone in WooCommerce
        if (class_exists('WooCommerce')) {
            $users = get_users([
                'meta_key' => 'billing_phone',
                'meta_value' => $phone,
                'number' => 1,
            ]);

            if (!empty($users)) {
                // Store normalized phone for future lookups
                update_user_meta($users[0]->ID, 'ww_auth_phone', $phone);
                return $users[0];
            }
        }

        return null;
    }

    /**
     * Create a new user from phone number
     */
    public function create_user_from_phone(string $phone): ?int
    {
        $phone = $this->format_phone($phone);

        // Generate username from phone
        $username = 'user_' . substr($phone, -8);
        $counter = 1;
        $base_username = $username;

        while (username_exists($username)) {
            $username = $base_username . '_' . $counter;
            $counter++;
        }

        // Generate secure password
        $password = wp_generate_password(16, true, true);

        // Create user
        $user_id = wp_insert_user([
            'user_login' => $username,
            'user_pass' => $password,
            'role' => 'customer', // WooCommerce default role
        ]);

        if (is_wp_error($user_id)) {
            error_log('WeeWoo Auth: Failed to create user - ' . $user_id->get_error_message());
            return null;
        }

        // Store phone number
        update_user_meta($user_id, 'ww_auth_phone', $phone);
        update_user_meta($user_id, 'billing_phone', '+' . $phone);

        return $user_id;
    }
}
