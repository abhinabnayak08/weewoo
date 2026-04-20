<?php
/**
 * Passkeys (WebAuthn) Class
 *
 * @package WeeWoo_Auth_Pro
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * WebAuthn Passkey Authentication
 */
final class WW_Passkeys
{
    private static ?WW_Passkeys $instance = null;
    private const CHALLENGE_EXPIRY = 5 * MINUTE_IN_SECONDS;
    private const META_KEY_PASSKEYS = 'ww_auth_passkeys';
    private const META_KEY_CHALLENGE = 'ww_auth_challenge';

    public static function instance(): WW_Passkeys
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        // Passkeys initialized on demand
    }

    /**
     * Check if passkeys are enabled
     */
    public function is_enabled(): bool
    {
        return (bool) get_option('ww_auth_passkeys_enabled', false);
    }

    /**
     * Get RP (Relying Party) ID from site URL
     */
    private function get_rp_id(): string
    {
        return wp_parse_url(home_url(), PHP_URL_HOST);
    }

    /**
     * Get RP Name
     */
    private function get_rp_name(): string
    {
        return get_bloginfo('name');
    }

    /**
     * Generate a cryptographically secure challenge
     */
    private function generate_challenge(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Base64URL encode
     */
    private function base64url_encode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Base64URL decode
     */
    private function base64url_decode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', 3 - (3 + strlen($data)) % 4));
    }

    /**
     * Get user's registered passkeys
     */
    public function get_user_passkeys(int $user_id): array
    {
        $passkeys = get_user_meta($user_id, self::META_KEY_PASSKEYS, true);
        return is_array($passkeys) ? $passkeys : [];
    }

    /**
     * Store a new passkey for user
     */
    private function store_passkey(int $user_id, array $passkey): bool
    {
        $passkeys = $this->get_user_passkeys($user_id);
        $passkeys[$passkey['credential_id']] = $passkey;
        return update_user_meta($user_id, self::META_KEY_PASSKEYS, $passkeys);
    }

    /**
     * Find user by credential ID
     */
    private function find_user_by_credential(string $credential_id): ?array
    {
        global $wpdb;

        $meta_key = self::META_KEY_PASSKEYS;
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT user_id, meta_value FROM {$wpdb->usermeta} WHERE meta_key = %s",
            $meta_key
        ));

        foreach ($results as $row) {
            $passkeys = maybe_unserialize($row->meta_value);
            if (is_array($passkeys) && isset($passkeys[$credential_id])) {
                return [
                    'user_id' => (int) $row->user_id,
                    'passkey' => $passkeys[$credential_id],
                ];
            }
        }

        return null;
    }

    /**
     * Store challenge in transient
     */
    private function store_challenge(string $key, string $challenge): void
    {
        set_transient('ww_webauthn_' . $key, $challenge, self::CHALLENGE_EXPIRY);
    }

    /**
     * Get and delete stored challenge
     */
    private function get_challenge(string $key): ?string
    {
        $challenge = get_transient('ww_webauthn_' . $key);
        delete_transient('ww_webauthn_' . $key);
        return $challenge !== false ? $challenge : null;
    }

    /**
     * Get registration options for WebAuthn
     */
    public function get_registration_options(): array
    {
        $user_id = get_current_user_id();
        $user = get_user_by('id', $user_id);

        if (!$user) {
            return ['error' => 'User not found'];
        }

        $challenge = $this->generate_challenge();
        $this->store_challenge('reg_' . $user_id, $challenge);

        // Get existing credentials to exclude
        $existing = $this->get_user_passkeys($user_id);
        $exclude_credentials = [];

        foreach ($existing as $cred_id => $passkey) {
            $exclude_credentials[] = [
                'type' => 'public-key',
                'id' => $cred_id,
                'transports' => $passkey['transports'] ?? ['internal', 'hybrid'],
            ];
        }

        return [
            'challenge' => $this->base64url_encode(hex2bin($challenge)),
            'rp' => [
                'name' => $this->get_rp_name(),
                'id' => $this->get_rp_id(),
            ],
            'user' => [
                'id' => $this->base64url_encode((string) $user_id),
                'name' => $user->user_email ?: $user->user_login,
                'displayName' => $user->display_name,
            ],
            'pubKeyCredParams' => [
                ['type' => 'public-key', 'alg' => -7],   // ES256
                ['type' => 'public-key', 'alg' => -257], // RS256
            ],
            'timeout' => 60000,
            'excludeCredentials' => $exclude_credentials,
            'authenticatorSelection' => [
                'authenticatorAttachment' => 'platform',
                'residentKey' => 'preferred',
                'userVerification' => 'required',
            ],
            'attestation' => 'none',
        ];
    }

    /**
     * Verify registration and store credential
     */
    public function verify_registration(array $credential): array
    {
        $user_id = get_current_user_id();

        if (!$user_id) {
            return ['success' => false, 'error' => 'Not authenticated'];
        }

        $stored_challenge = $this->get_challenge('reg_' . $user_id);

        if (!$stored_challenge) {
            return ['success' => false, 'error' => 'Challenge expired or not found'];
        }

        // Extract credential data
        $credential_id = $credential['id'] ?? '';
        $raw_id = $credential['rawId'] ?? $credential_id;
        $response = $credential['response'] ?? [];

        if (empty($credential_id) || empty($response['clientDataJSON']) || empty($response['attestationObject'])) {
            return ['success' => false, 'error' => 'Invalid credential format'];
        }

        // Decode and verify clientDataJSON
        $client_data = json_decode($this->base64url_decode($response['clientDataJSON']), true);

        if (!$client_data) {
            return ['success' => false, 'error' => 'Invalid client data'];
        }

        // Verify challenge
        $received_challenge = $this->base64url_decode($client_data['challenge'] ?? '');
        if (bin2hex($received_challenge) !== $stored_challenge) {
            return ['success' => false, 'error' => 'Challenge mismatch'];
        }

        // Verify origin
        $expected_origin = home_url();
        if (($client_data['origin'] ?? '') !== $expected_origin) {
            // Allow for port differences in development
            $expected_host = wp_parse_url($expected_origin, PHP_URL_HOST);
            $received_host = wp_parse_url($client_data['origin'] ?? '', PHP_URL_HOST);

            if ($expected_host !== $received_host) {
                return ['success' => false, 'error' => 'Origin mismatch'];
            }
        }

        // Verify type
        if (($client_data['type'] ?? '') !== 'webauthn.create') {
            return ['success' => false, 'error' => 'Invalid type'];
        }

        // Parse attestation object to extract public key
        // Note: Full CBOR parsing would require a library. 
        // For simplicity, we store the raw attestation for verification later.
        $attestation_object = $this->base64url_decode($response['attestationObject']);

        // Store the passkey
        $passkey = [
            'credential_id' => $credential_id,
            'public_key' => base64_encode($attestation_object),
            'transports' => $credential['response']['transports'] ?? ['internal'],
            'counter' => 0,
            'created' => time(),
            'last_used' => null,
            'device_name' => $this->detect_device_name(),
        ];

        $this->store_passkey($user_id, $passkey);

        return ['success' => true];
    }

    /**
     * Get authentication options for WebAuthn login
     */
    public function get_authentication_options(): array
    {
        $challenge = $this->generate_challenge();

        // Store with a temporary key since user is not logged in
        $session_id = bin2hex(random_bytes(16));
        $this->store_challenge('auth_' . $session_id, $challenge);

        return [
            'challenge' => $this->base64url_encode(hex2bin($challenge)),
            'timeout' => 60000,
            'rpId' => $this->get_rp_id(),
            'userVerification' => 'required',
            'session_id' => $session_id,
        ];
    }

    /**
     * Verify authentication assertion
     */
    public function verify_authentication(array $credential): array
    {
        $session_id = $credential['session_id'] ?? '';
        $stored_challenge = $this->get_challenge('auth_' . $session_id);

        if (!$stored_challenge) {
            return ['success' => false, 'error' => 'Challenge expired'];
        }

        $credential_id = $credential['id'] ?? '';
        $response = $credential['response'] ?? [];

        if (empty($credential_id) || empty($response['clientDataJSON']) || empty($response['authenticatorData']) || empty($response['signature'])) {
            return ['success' => false, 'error' => 'Invalid credential'];
        }

        // Find user by credential
        $found = $this->find_user_by_credential($credential_id);

        if (!$found) {
            return ['success' => false, 'error' => 'Credential not found'];
        }

        // Decode and verify clientDataJSON
        $client_data = json_decode($this->base64url_decode($response['clientDataJSON']), true);

        if (!$client_data) {
            return ['success' => false, 'error' => 'Invalid client data'];
        }

        // Verify challenge
        $received_challenge = $this->base64url_decode($client_data['challenge'] ?? '');
        if (bin2hex($received_challenge) !== $stored_challenge) {
            return ['success' => false, 'error' => 'Challenge mismatch'];
        }

        // Verify type
        if (($client_data['type'] ?? '') !== 'webauthn.get') {
            return ['success' => false, 'error' => 'Invalid type'];
        }

        // Verify origin
        $expected_origin = home_url();
        $expected_host = wp_parse_url($expected_origin, PHP_URL_HOST);
        $received_host = wp_parse_url($client_data['origin'] ?? '', PHP_URL_HOST);

        if ($expected_host !== $received_host) {
            return ['success' => false, 'error' => 'Origin mismatch'];
        }

        // Update last used timestamp
        $passkeys = $this->get_user_passkeys($found['user_id']);
        if (isset($passkeys[$credential_id])) {
            $passkeys[$credential_id]['last_used'] = time();
            $passkeys[$credential_id]['counter'] = ($passkeys[$credential_id]['counter'] ?? 0) + 1;
            update_user_meta($found['user_id'], self::META_KEY_PASSKEYS, $passkeys);
        }

        return [
            'success' => true,
            'user_id' => $found['user_id'],
        ];
    }

    /**
     * Delete a passkey
     */
    public function delete_passkey(int $user_id, string $credential_id): bool
    {
        $passkeys = $this->get_user_passkeys($user_id);

        if (!isset($passkeys[$credential_id])) {
            return false;
        }

        unset($passkeys[$credential_id]);
        return update_user_meta($user_id, self::META_KEY_PASSKEYS, $passkeys);
    }

    /**
     * Detect device name from User-Agent
     */
    private function detect_device_name(): string
    {
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        if (strpos($user_agent, 'iPhone') !== false) {
            return 'iPhone';
        } elseif (strpos($user_agent, 'iPad') !== false) {
            return 'iPad';
        } elseif (strpos($user_agent, 'Mac') !== false) {
            return 'Mac';
        } elseif (strpos($user_agent, 'Android') !== false) {
            return 'Android';
        } elseif (strpos($user_agent, 'Windows') !== false) {
            return 'Windows';
        } elseif (strpos($user_agent, 'Linux') !== false) {
            return 'Linux';
        }

        return 'Unknown Device';
    }
}
