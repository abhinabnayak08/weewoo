<?php
/**
 * Rate Limiter Class
 * 
 * Transient-based IP rate limiting for brute force protection
 *
 * @package WeeWoo_Auth_Pro
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Rate Limiter using WordPress Transients
 */
final class WW_Rate_Limiter
{
    private static ?WW_Rate_Limiter $instance = null;
    private const TRANSIENT_PREFIX = 'ww_auth_rl_';

    public static function instance(): WW_Rate_Limiter
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        // Rate limiter is initialized on demand
    }

    /**
     * Get client IP address
     */
    public function get_client_ip(): string
    {
        $ip = '';

        // Check for Cloudflare
        if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            $ip = sanitize_text_field($_SERVER['HTTP_CF_CONNECTING_IP']);
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            // Get first IP if multiple
            $ips = explode(',', sanitize_text_field($_SERVER['HTTP_X_FORWARDED_FOR']));
            $ip = trim($ips[0]);
        } elseif (!empty($_SERVER['HTTP_X_REAL_IP'])) {
            $ip = sanitize_text_field($_SERVER['HTTP_X_REAL_IP']);
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            $ip = sanitize_text_field($_SERVER['REMOTE_ADDR']);
        }

        // Validate IP
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            $ip = '0.0.0.0';
        }

        return $ip;
    }

    /**
     * Get transient key for an IP
     */
    private function get_transient_key(string $ip): string
    {
        return self::TRANSIENT_PREFIX . md5($ip);
    }

    /**
     * Check if IP is blocked
     */
    public function is_blocked(?string $ip = null): bool
    {
        $ip = $ip ?? $this->get_client_ip();
        $key = $this->get_transient_key($ip);
        $data = get_transient($key);

        if ($data === false) {
            return false;
        }

        $max_attempts = (int) get_option('ww_auth_rate_limit_attempts', 5);

        return isset($data['attempts']) && $data['attempts'] >= $max_attempts;
    }

    /**
     * Record a failed attempt
     */
    public function record_failure(?string $ip = null): array
    {
        $ip = $ip ?? $this->get_client_ip();
        $key = $this->get_transient_key($ip);
        $data = get_transient($key);
        $max_attempts = (int) get_option('ww_auth_rate_limit_attempts', 5);
        $block_duration = (int) get_option('ww_auth_rate_limit_duration', 60) * MINUTE_IN_SECONDS;

        if ($data === false) {
            $data = [
                'attempts' => 1,
                'first_attempt' => time(),
            ];
        } else {
            $data['attempts'] = ($data['attempts'] ?? 0) + 1;
        }

        // Set/update transient
        set_transient($key, $data, $block_duration);

        return [
            'attempts' => $data['attempts'],
            'max_attempts' => $max_attempts,
            'remaining' => max(0, $max_attempts - $data['attempts']),
            'blocked' => $data['attempts'] >= $max_attempts,
            'block_duration' => $block_duration,
        ];
    }

    /**
     * Record a successful login (reset attempts)
     */
    public function record_success(?string $ip = null): void
    {
        $ip = $ip ?? $this->get_client_ip();
        $key = $this->get_transient_key($ip);
        delete_transient($key);
    }

    /**
     * Get remaining attempts for an IP
     */
    public function get_remaining_attempts(?string $ip = null): int
    {
        $ip = $ip ?? $this->get_client_ip();
        $key = $this->get_transient_key($ip);
        $data = get_transient($key);
        $max_attempts = (int) get_option('ww_auth_rate_limit_attempts', 5);

        if ($data === false) {
            return $max_attempts;
        }

        return max(0, $max_attempts - ($data['attempts'] ?? 0));
    }

    /**
     * Get time until IP is unblocked (in seconds)
     */
    public function get_time_remaining(?string $ip = null): int
    {
        $ip = $ip ?? $this->get_client_ip();
        $key = $this->get_transient_key($ip);

        // Get transient timeout
        $timeout = get_option('_transient_timeout_' . $key);

        if ($timeout === false) {
            return 0;
        }

        $remaining = (int) $timeout - time();
        return max(0, $remaining);
    }

    /**
     * Manually unblock an IP (admin function)
     */
    public function unblock_ip(string $ip): bool
    {
        $key = $this->get_transient_key($ip);
        return delete_transient($key);
    }

    /**
     * Get status array for REST API response
     */
    public function get_status(?string $ip = null): array
    {
        $ip = $ip ?? $this->get_client_ip();

        return [
            'ip' => $ip,
            'blocked' => $this->is_blocked($ip),
            'remaining_attempts' => $this->get_remaining_attempts($ip),
            'time_remaining' => $this->get_time_remaining($ip),
        ];
    }
}
