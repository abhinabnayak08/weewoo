<?php
/**
 * Plugin Name: WeeWoo Auth Pro
 * Plugin URI: https://weewoo.io/auth-pro
 * Description: Premium mobile-first authentication plugin with WhatsApp OTP, Passkeys, QR Login, and WooCommerce Guest Pay bypass.
 * Version: 1.0.0
 * Author: WeeWoo
 * Author URI: https://weewoo.io
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: weewoo-auth-pro
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * WC requires at least: 7.0
 * WC tested up to: 8.5
 *
 * @package WeeWoo_Auth_Pro
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

// Plugin Constants
define('WW_AUTH_VERSION', '1.0.0');
define('WW_AUTH_PLUGIN_FILE', __FILE__);
define('WW_AUTH_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WW_AUTH_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WW_AUTH_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Custom PSR-4 Autoloader for /includes/ directory
 */
spl_autoload_register(function (string $class): void {
    $prefix = 'WeeWoo\\Auth\\';
    $base_dir = WW_AUTH_PLUGIN_DIR . 'includes/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $file = $base_dir . 'class-ww-' . strtolower(str_replace(['\\', '_'], ['-', '-'], $relative_class)) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

/**
 * Main Plugin Class - Singleton Pattern
 */
final class WeeWoo_Auth_Pro
{
    private static ?WeeWoo_Auth_Pro $instance = null;

    /**
     * Get singleton instance
     */
    public static function instance(): WeeWoo_Auth_Pro
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Private constructor - Singleton
     */
    private function __construct()
    {
        $this->load_dependencies();
        $this->init_hooks();
    }

    /**
     * Prevent cloning
     */
    private function __clone()
    {
    }

    /**
     * Prevent unserialization
     */
    public function __wakeup()
    {
        throw new \Exception('Cannot unserialize singleton');
    }

    /**
     * Load required files
     */
    private function load_dependencies(): void
    {
        // Core includes
        require_once WW_AUTH_PLUGIN_DIR . 'includes/class-ww-rate-limiter.php';
        require_once WW_AUTH_PLUGIN_DIR . 'includes/class-ww-turnstile.php';
        require_once WW_AUTH_PLUGIN_DIR . 'includes/class-ww-whatsapp.php';
        require_once WW_AUTH_PLUGIN_DIR . 'includes/class-ww-auth-api.php';
        require_once WW_AUTH_PLUGIN_DIR . 'includes/class-ww-passkeys.php';
        require_once WW_AUTH_PLUGIN_DIR . 'includes/class-ww-qr-handshake.php';
        require_once WW_AUTH_PLUGIN_DIR . 'includes/class-ww-guest-pay.php';
        require_once WW_AUTH_PLUGIN_DIR . 'includes/class-ww-frontend.php';

        // Admin
        if (is_admin()) {
            require_once WW_AUTH_PLUGIN_DIR . 'admin/class-ww-auth-settings.php';
        }
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks(): void
    {
        add_action('init', [$this, 'register_secure_login_endpoint']);
        add_action('template_redirect', [$this, 'handle_secure_login_page']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
        add_action('rest_api_init', [$this, 'register_rest_routes']);

        // Initialize modules
        add_action('plugins_loaded', [$this, 'init_modules']);

        // Declare WooCommerce HPOS (Custom Order Tables) compatibility
        add_action('before_woocommerce_init', [$this, 'declare_wc_compatibility']);
    }

    /**
     * Declare WooCommerce feature compatibility (HPOS, cart/checkout blocks)
     */
    public function declare_wc_compatibility(): void
    {
        if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
        }
    }

    /**
     * Initialize plugin modules
     */
    public function init_modules(): void
    {
        WW_Rate_Limiter::instance();
        WW_Turnstile::instance();
        WW_WhatsApp::instance();
        WW_Auth_API::instance();
        WW_Passkeys::instance();
        WW_QR_Handshake::instance();
        WW_Guest_Pay::instance();
        WW_Frontend::instance();

        if (is_admin()) {
            WW_Auth_Settings::instance();
        }
    }

    /**
     * Register the /secure-login/ rewrite endpoint
     */
    public function register_secure_login_endpoint(): void
    {
        add_rewrite_rule(
            '^secure-login/?$',
            'index.php?ww_secure_login=1',
            'top'
        );
        add_rewrite_tag('%ww_secure_login%', '([^&]+)');
    }

    /**
     * Handle the secure login page display
     */
    public function handle_secure_login_page(): void
    {
        if (get_query_var('ww_secure_login')) {
            // Prevent caching
            nocache_headers();

            // Load the custom login template
            include WW_AUTH_PLUGIN_DIR . 'templates/login-page.php';
            exit;
        }
    }

    /**
     * Enqueue frontend assets
     *
     * The /secure-login/ template is fully self-contained with inline CSS + JS
     * for maximum compatibility and performance. External files are intentionally
     * NOT enqueued on the login page to avoid theme-style conflicts.
     */
    public function enqueue_frontend_assets(): void
    {
        // Intentionally left empty — see templates/login-page.php for styles/scripts.
        return;
    }

    /**
     * Register REST API routes
     */
    public function register_rest_routes(): void
    {
        WW_Auth_API::instance()->register_routes();
    }

    /**
     * Get plugin option with default fallback
     */
    public static function get_option(string $key, $default = null)
    {
        return get_option('ww_auth_' . $key, $default);
    }

    /**
     * Update plugin option
     */
    public static function update_option(string $key, $value): bool
    {
        return update_option('ww_auth_' . $key, $value);
    }
}

/**
 * Activation Hook
 */
function ww_auth_activate(): void
{
    // Set default options only if they don't exist
    $defaults = [
        'ww_auth_whatsapp_enabled' => false,
        'ww_auth_email_enabled' => true,
        'ww_auth_passkeys_enabled' => false,
        'ww_auth_qr_enabled' => true,
        'ww_auth_admin_2fa_enabled' => false,
        'ww_auth_rate_limit_attempts' => 5,
        'ww_auth_rate_limit_duration' => 60,
        'ww_auth_primary_color' => '#10B981',
        'ww_auth_secondary_color' => '#111827',
        'ww_auth_logo_url' => '',
    ];

    foreach ($defaults as $key => $value) {
        if (get_option($key) === false) {
            add_option($key, $value);
        }
    }

    // Register the rewrite rule
    WeeWoo_Auth_Pro::instance()->register_secure_login_endpoint();

    // Flush rewrite rules
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'ww_auth_activate');

/**
 * Deactivation Hook
 */
function ww_auth_deactivate(): void
{
    // Flush rewrite rules on deactivation
    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'ww_auth_deactivate');

/**
 * Initialize the plugin
 */
function ww_auth_pro(): WeeWoo_Auth_Pro
{
    return WeeWoo_Auth_Pro::instance();
}

// Start the plugin
ww_auth_pro();
