<?php
/**
 * Cloudflare Turnstile Verification Class
 *
 * @package WeeWoo_Auth_Pro
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Turnstile CAPTCHA verification
 */
final class WW_Turnstile
{
    private static ?WW_Turnstile $instance = null;
    private const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    public static function instance(): WW_Turnstile
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        // Turnstile is verified on demand
    }

    /**
     * Check if Turnstile is enabled and configured
     */
    public function is_enabled(): bool
    {
        return (bool) get_option('ww_auth_turnstile_enabled', false)
            && !empty(get_option('ww_auth_turnstile_site_key'))
            && !empty(get_option('ww_auth_turnstile_secret_key'));
    }

    /**
     * Get the site key for frontend
     */
    public function get_site_key(): string
    {
        return (string) get_option('ww_auth_turnstile_site_key', '');
    }

    /**
     * Verify a Turnstile token
     *
     * @param string $token The cf-turnstile-response token from frontend
     * @return array{success: bool, error?: string}
     */
    public function verify(string $token): array
    {
        if (!$this->is_enabled()) {
            // If not enabled, skip verification
            return ['success' => true];
        }

        if (empty($token)) {
            return [
                'success' => false,
                'error' => __('Turnstile verification required.', 'weewoo-auth-pro'),
            ];
        }

        $secret_key = get_option('ww_auth_turnstile_secret_key', '');

        $response = wp_remote_post(self::VERIFY_URL, [
            'timeout' => 10,
            'body' => [
                'secret' => $secret_key,
                'response' => $token,
                'remoteip' => WW_Rate_Limiter::instance()->get_client_ip(),
            ],
        ]);

        if (is_wp_error($response)) {
            // Log the error for debugging
            error_log('WeeWoo Auth: Turnstile verification failed - ' . $response->get_error_message());

            return [
                'success' => false,
                'error' => __('Verification service unavailable. Please try again.', 'weewoo-auth-pro'),
            ];
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (!isset($body['success'])) {
            return [
                'success' => false,
                'error' => __('Invalid verification response.', 'weewoo-auth-pro'),
            ];
        }

        if ($body['success'] !== true) {
            $error_codes = $body['error-codes'] ?? [];
            error_log('WeeWoo Auth: Turnstile failed - ' . implode(', ', $error_codes));

            return [
                'success' => false,
                'error' => __('Verification failed. Please try again.', 'weewoo-auth-pro'),
            ];
        }

        return ['success' => true];
    }

    /**
     * Get the script URL for Turnstile
     */
    public function get_script_url(): string
    {
        return 'https://challenges.cloudflare.com/turnstile/v0/api.js';
    }

    /**
     * Render the Turnstile widget HTML
     */
    public function render_widget(string $callback = ''): string
    {
        if (!$this->is_enabled()) {
            return '';
        }

        $site_key = esc_attr($this->get_site_key());
        $callback_attr = $callback ? ' data-callback="' . esc_attr($callback) . '"' : '';

        return sprintf(
            '<div class="cf-turnstile" data-sitekey="%s" data-theme="light"%s></div>',
            $site_key,
            $callback_attr
        );
    }
}
