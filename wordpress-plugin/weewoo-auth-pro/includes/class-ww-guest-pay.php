<?php
/**
 * WooCommerce Guest Pay Class
 * 
 * Bypasses login requirement for order-pay links with valid order keys
 *
 * @package WeeWoo_Auth_Pro
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Guest Pay functionality for WooCommerce
 */
final class WW_Guest_Pay
{
    private static ?WW_Guest_Pay $instance = null;

    public static function instance(): WW_Guest_Pay
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        // Only initialize if WooCommerce is active
        if (!class_exists('WooCommerce')) {
            return;
        }

        // Hook into WooCommerce pay order login requirement
        add_filter('woocommerce_pay_order_require_login', [$this, 'maybe_bypass_login'], 10, 2);

        // Bypass email verification for valid order keys
        add_filter('woocommerce_order_email_verification_required', [$this, 'bypass_email_verification'], 10, 2);

        // Auto-fill billing fields on successful login
        add_action('ww_auth_after_login', [$this, 'auto_fill_billing_fields'], 10, 2);

        // Handle order key validation on pay page
        add_action('template_redirect', [$this, 'validate_order_key_access'], 5);
    }

    /**
     * Check if order key in URL matches the order
     */
    private function is_valid_order_key(?int $order_id = null): bool
    {
        // Get order key from URL
        $order_key = isset($_GET['key']) ? sanitize_text_field($_GET['key']) : '';

        if (empty($order_key)) {
            return false;
        }

        // Get order ID from URL if not provided
        if ($order_id === null) {
            $order_id = absint(get_query_var('order-pay', 0));
        }

        if (!$order_id) {
            return false;
        }

        // Get order
        $order = wc_get_order($order_id);

        if (!$order) {
            return false;
        }

        // Compare order key
        return $order->get_order_key() === $order_key;
    }

    /**
     * Maybe bypass login requirement for pay order page
     * 
     * Hooks into: woocommerce_pay_order_require_login
     */
    public function maybe_bypass_login(bool $require_login, WC_Order $order): bool
    {
        // If order key matches, don't require login
        if ($this->is_valid_order_key($order->get_id())) {
            return false;
        }

        return $require_login;
    }

    /**
     * Bypass email verification for valid order keys
     * 
     * Hooks into: woocommerce_order_email_verification_required
     */
    public function bypass_email_verification(bool $required, WC_Order $order): bool
    {
        // Check if we're on the pay page with valid order key
        if (is_wc_endpoint_url('order-pay') && $this->is_valid_order_key($order->get_id())) {
            return false;
        }

        // Also check for order-received page
        if (is_wc_endpoint_url('order-received') && $this->is_valid_order_key($order->get_id())) {
            return false;
        }

        return $required;
    }

    /**
     * Validate order key access on template redirect
     */
    public function validate_order_key_access(): void
    {
        // Only on order-pay endpoint
        if (!is_wc_endpoint_url('order-pay')) {
            return;
        }

        $order_id = absint(get_query_var('order-pay', 0));

        if (!$order_id) {
            return;
        }

        // If user is not logged in and has valid key, allow access
        if (!is_user_logged_in() && $this->is_valid_order_key($order_id)) {
            // Store order ID in session for billing auto-fill after login
            if (WC()->session) {
                WC()->session->set('ww_guest_pay_order', $order_id);
            }
        }
    }

    /**
     * Auto-fill billing fields after successful login
     * 
     * @param WP_User $user The logged in user
     * @param array $context Login context data
     */
    public function auto_fill_billing_fields(WP_User $user, array $context = []): void
    {
        // Check if we have a pending order from guest pay
        if (!WC()->session) {
            return;
        }

        $order_id = WC()->session->get('ww_guest_pay_order');

        if (!$order_id) {
            return;
        }

        $order = wc_get_order($order_id);

        if (!$order) {
            return;
        }

        // Clear the session value
        WC()->session->set('ww_guest_pay_order', null);

        // Get billing fields from user meta
        $billing_fields = [
            'billing_first_name',
            'billing_last_name',
            'billing_company',
            'billing_address_1',
            'billing_address_2',
            'billing_city',
            'billing_postcode',
            'billing_country',
            'billing_state',
            'billing_email',
            'billing_phone',
        ];

        $updated = false;

        foreach ($billing_fields as $field) {
            $meta_value = get_user_meta($user->ID, $field, true);

            if (!empty($meta_value)) {
                // Get the order field name (without billing_ prefix for order methods)
                $order_field = str_replace('billing_', '', $field);
                $getter = 'get_billing_' . $order_field;

                // Only update if order field is empty
                if (method_exists($order, $getter) && empty($order->$getter())) {
                    $setter = 'set_billing_' . $order_field;
                    if (method_exists($order, $setter)) {
                        $order->$setter($meta_value);
                        $updated = true;
                    }
                }
            }
        }

        // Assign customer to order if not assigned
        if (!$order->get_customer_id()) {
            $order->set_customer_id($user->ID);
            $updated = true;
        }

        if ($updated) {
            $order->save();
        }
    }

    /**
     * Get guest pay URL for an order
     */
    public static function get_pay_url(WC_Order $order): string
    {
        return add_query_arg(
            ['key' => $order->get_order_key()],
            $order->get_checkout_payment_url()
        );
    }

    /**
     * Check if current page is a valid guest pay page
     */
    public function is_guest_pay_page(): bool
    {
        if (!is_wc_endpoint_url('order-pay')) {
            return false;
        }

        return $this->is_valid_order_key();
    }

    /**
     * Get order from current guest pay context
     */
    public function get_current_order(): ?WC_Order
    {
        if (!$this->is_guest_pay_page()) {
            return null;
        }

        $order_id = absint(get_query_var('order-pay', 0));
        return wc_get_order($order_id) ?: null;
    }
}
