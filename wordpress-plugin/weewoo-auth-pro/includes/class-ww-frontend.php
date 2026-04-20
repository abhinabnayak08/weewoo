<?php
/**
 * Frontend Handler Class
 *
 * @package WeeWoo_Auth_Pro
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Frontend login page handler
 */
final class WW_Frontend
{
    private static ?WW_Frontend $instance = null;

    public static function instance(): WW_Frontend
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        // Handle QR authorization from mobile
        add_action('init', [$this, 'handle_qr_authorization']);
    }

    /**
     * Handle QR authorization when user scans QR on mobile
     */
    public function handle_qr_authorization(): void
    {
        if (!isset($_GET['action']) || $_GET['action'] !== 'ww_qr_auth') {
            return;
        }

        if (!isset($_GET['session'])) {
            return;
        }

        $session_id = sanitize_text_field($_GET['session']);

        // If user is logged in, show authorization prompt
        if (is_user_logged_in()) {
            // The template will handle showing the authorization UI
            return;
        }

        // If not logged in, redirect to login with session preserved
        $login_url = add_query_arg([
            'qr_session' => $session_id,
        ], home_url('/secure-login/'));

        wp_redirect($login_url);
        exit;
    }

    /**
     * Get branding settings for frontend
     */
    public static function get_branding(): array
    {
        return [
            'logo_url' => get_option('ww_auth_logo_url', ''),
            'primary_color' => get_option('ww_auth_primary_color', '#10B981'),
            'secondary_color' => get_option('ww_auth_secondary_color', '#111827'),
            'page_title' => get_option('ww_auth_page_title', 'Secure Login'),
            'welcome_text' => get_option('ww_auth_welcome_text', 'Welcome back'),
        ];
    }

    /**
     * Get enabled authentication methods
     */
    public static function get_auth_methods(): array
    {
        return [
            'whatsapp' => WW_WhatsApp::instance()->is_enabled(),
            'email' => (bool) get_option('ww_auth_email_enabled', true),
            'passkeys' => (bool) get_option('ww_auth_passkeys_enabled', false),
            'qr' => (bool) get_option('ww_auth_qr_enabled', true),
        ];
    }

    /**
     * Check if Turnstile should be rendered
     */
    public static function should_render_turnstile(): bool
    {
        return WW_Turnstile::instance()->is_enabled();
    }

    /**
     * Get CSS variables from branding settings
     */
    public static function get_css_variables(): string
    {
        $branding = self::get_branding();

        return sprintf(
            ':root {
                --ww-primary: %s;
                --ww-secondary: %s;
                --ww-primary-hover: %s;
                --ww-secondary-hover: %s;
            }',
            esc_attr($branding['primary_color']),
            esc_attr($branding['secondary_color']),
            esc_attr(self::adjust_brightness($branding['primary_color'], -20)),
            esc_attr(self::adjust_brightness($branding['secondary_color'], 20))
        );
    }

    /**
     * Adjust color brightness
     */
    private static function adjust_brightness(string $hex, int $steps): string
    {
        $hex = str_replace('#', '', $hex);

        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        $r = max(0, min(255, $r + $steps));
        $g = max(0, min(255, $g + $steps));
        $b = max(0, min(255, $b + $steps));

        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }
}
