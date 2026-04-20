<?php
/**
 * QR Handshake Class
 * 
 * Desktop-to-mobile bridge for QR code login
 *
 * @package WeeWoo_Auth_Pro
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * QR Code Login Handshake
 */
final class WW_QR_Handshake
{
    private static ?WW_QR_Handshake $instance = null;
    private const SESSION_EXPIRY = 5 * MINUTE_IN_SECONDS;
    private const TRANSIENT_PREFIX = 'ww_qr_session_';

    public static function instance(): WW_QR_Handshake
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        // QR Handshake initialized on demand
    }

    /**
     * Check if QR login is enabled
     */
    public function is_enabled(): bool
    {
        return (bool) get_option('ww_auth_qr_enabled', true);
    }

    /**
     * Generate a 32-character hex session ID
     */
    private function generate_session_id(): string
    {
        return bin2hex(random_bytes(16)); // 32 hex characters
    }

    /**
     * Create a new QR session
     */
    public function create_session(): array
    {
        $session_id = $this->generate_session_id();
        $created = time();

        $session_data = [
            'id' => $session_id,
            'created' => $created,
            'authorized' => false,
            'user_id' => null,
            'ip' => WW_Rate_Limiter::instance()->get_client_ip(),
        ];

        // Store in transient
        set_transient(self::TRANSIENT_PREFIX . $session_id, $session_data, self::SESSION_EXPIRY);

        // Build QR data URL (deep link for mobile app or web)
        $qr_data = add_query_arg([
            'action' => 'ww_qr_auth',
            'session' => $session_id,
        ], home_url('/secure-login/'));

        return [
            'id' => $session_id,
            'qr_data' => $qr_data,
            'expires_in' => self::SESSION_EXPIRY,
        ];
    }

    /**
     * Get session status
     */
    public function get_session_status(string $session_id): ?array
    {
        // Validate session ID format
        if (!preg_match('/^[a-f0-9]{32}$/', $session_id)) {
            return null;
        }

        $session = get_transient(self::TRANSIENT_PREFIX . $session_id);

        if ($session === false) {
            return null;
        }

        return $session;
    }

    /**
     * Authorize a QR session (called from mobile)
     */
    public function authorize_session(string $session_id, int $user_id): array
    {
        // Validate session ID format
        if (!preg_match('/^[a-f0-9]{32}$/', $session_id)) {
            return [
                'success' => false,
                'error' => __('Invalid session ID.', 'weewoo-auth-pro'),
            ];
        }

        $session = get_transient(self::TRANSIENT_PREFIX . $session_id);

        if ($session === false) {
            return [
                'success' => false,
                'error' => __('Session expired or not found.', 'weewoo-auth-pro'),
            ];
        }

        if ($session['authorized']) {
            return [
                'success' => false,
                'error' => __('Session already authorized.', 'weewoo-auth-pro'),
            ];
        }

        // Authorize the session
        $session['authorized'] = true;
        $session['user_id'] = $user_id;
        $session['authorized_at'] = time();

        // Update transient with remaining time
        $remaining = self::SESSION_EXPIRY - (time() - $session['created']);
        set_transient(self::TRANSIENT_PREFIX . $session_id, $session, max(60, $remaining));

        return ['success' => true];
    }

    /**
     * Clear a session
     */
    public function clear_session(string $session_id): bool
    {
        return delete_transient(self::TRANSIENT_PREFIX . $session_id);
    }

    /**
     * Generate QR code SVG
     */
    public function generate_qr_svg(string $data, int $size = 200): string
    {
        // Use a simple QR code generation approach
        // For production, you might want to use a library like 'endroid/qr-code'
        // This generates a placeholder that can be replaced with actual QR

        $encoded_data = urlencode($data);
        $qr_api_url = "https://api.qrserver.com/v1/create-qr-code/?size={$size}x{$size}&data={$encoded_data}&format=svg";

        return $qr_api_url;
    }

    /**
     * Render QR code HTML for frontend
     */
    public function render_qr_code(string $session_id, string $qr_data): string
    {
        $qr_url = $this->generate_qr_svg($qr_data);

        return sprintf(
            '<div class="ww-qr-container" data-session-id="%s">
                <img src="%s" alt="QR Code" class="ww-qr-image" />
                <p class="ww-qr-hint">%s</p>
            </div>',
            esc_attr($session_id),
            esc_url($qr_url),
            esc_html__('Scan with your phone to log in', 'weewoo-auth-pro')
        );
    }
}
