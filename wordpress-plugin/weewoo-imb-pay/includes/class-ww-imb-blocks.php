<?php
/**
 * WooCommerce Blocks (Checkout block / Store API) integration so the gateway
 * also renders on block-based checkouts, not just the classic shortcode.
 *
 * @package WeeWoo_IMB_Pay
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

final class WW_IMB_Blocks extends AbstractPaymentMethodType
{
    protected $name = 'weewoo_imb';

    public function initialize(): void
    {
        $this->settings = get_option('woocommerce_' . $this->name . '_settings', []);
    }

    public function is_active(): bool
    {
        return ($this->settings['enabled'] ?? 'no') === 'yes'
            && trim((string) ($this->settings['user_token'] ?? '')) !== '';
    }

    public function get_payment_method_script_handles(): array
    {
        wp_register_script(
            'ww-imb-blocks',
            WW_IMB_URL . 'assets/js/blocks.js',
            ['wc-blocks-registry', 'wp-element', 'wc-settings', 'wp-html-entities'],
            WW_IMB_VERSION,
            true
        );
        return ['ww-imb-blocks'];
    }

    public function get_payment_method_data(): array
    {
        return [
            'title'       => $this->settings['title'] ?? 'UPI / QR (WeeWoo Pay)',
            'description' => $this->settings['description'] ?? '',
            'supports'    => ['products'],
        ];
    }
}
