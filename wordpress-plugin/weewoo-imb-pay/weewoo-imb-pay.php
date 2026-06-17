<?php
/**
 * Plugin Name: WeeWoo Pay — IMB UPI Gateway
 * Description: Custom-branded UPI payment gateway for WooCommerce, powered by IMB. Shows our own high-end QR checkout page and confirms payment via IMB check-order-status (webhook + polling).
 * Version: 1.0.0
 * Author: WeeWoo
 * Requires PHP: 7.4
 * WC requires at least: 7.0
 * WC tested up to: 10.7
 * Text Domain: weewoo-imb-pay
 *
 * @package WeeWoo_IMB_Pay
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

define('WW_IMB_VERSION', '1.0.0');
define('WW_IMB_FILE', __FILE__);
define('WW_IMB_DIR', plugin_dir_path(__FILE__));
define('WW_IMB_URL', plugin_dir_url(__FILE__));

require_once WW_IMB_DIR . 'includes/class-ww-imb-client.php';
require_once WW_IMB_DIR . 'includes/class-ww-imb-endpoints.php';

// Declare HPOS (custom order tables) + blocks compatibility.
add_action('before_woocommerce_init', function () {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
    }
});

// Register the gateway with WooCommerce.
add_filter('woocommerce_payment_gateways', function (array $gateways): array {
    $gateways[] = 'WW_IMB_Gateway';
    return $gateways;
});

// The gateway class depends on WC_Payment_Gateway, which only exists once
// WooCommerce has loaded its payment classes.
add_action('plugins_loaded', function () {
    if (!class_exists('WC_Payment_Gateway')) {
        add_action('admin_notices', function () {
            echo '<div class="notice notice-error"><p><strong>WeeWoo Pay (IMB)</strong> requires WooCommerce to be installed and active.</p></div>';
        });
        return;
    }
    require_once WW_IMB_DIR . 'includes/class-ww-imb-gateway.php';
    WW_IMB_Endpoints::instance();

    if (is_admin()) {
        require_once WW_IMB_DIR . 'includes/class-ww-imb-admin.php';
        WW_IMB_Admin::instance();
    }
});

// Settings shortcut on the plugins screen.
add_filter('plugin_action_links_' . plugin_basename(__FILE__), function (array $links): array {
    $url = admin_url('admin.php?page=wc-settings&tab=checkout&section=weewoo_imb');
    array_unshift($links, '<a href="' . esc_url($url) . '">' . esc_html__('Settings', 'weewoo-imb-pay') . '</a>');
    return $links;
});
