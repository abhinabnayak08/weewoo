<?php
/**
 * Endpoints for the IMB gateway: webhook receiver, status poll, and the
 * branded QR checkout page.
 *
 * @package WeeWoo_IMB_Pay
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class WW_IMB_Endpoints
{
    private static ?WW_IMB_Endpoints $instance = null;

    public static function instance(): WW_IMB_Endpoints
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        // IMB → our server webhook:  /?wc-api=weewoo_imb_webhook
        add_action('woocommerce_api_weewoo_imb_webhook', [$this, 'handle_webhook']);

        // Front-end polling (guest-safe via order key).
        add_action('wp_ajax_ww_imb_status', [$this, 'ajax_status']);
        add_action('wp_ajax_nopriv_ww_imb_status', [$this, 'ajax_status']);

        // Branded QR page.
        add_action('template_redirect', [$this, 'maybe_render_qr_page']);
    }

    public static function webhook_url(): string
    {
        return add_query_arg('wc-api', 'weewoo_imb_webhook', home_url('/'));
    }

    /**
     * IMB realtime callback. Always responds 200 fast; confirmation is
     * authoritative via check-order-status (webhook content alone is not trusted).
     */
    public function handle_webhook(): void
    {
        $payload = [];
        if (!empty($_POST)) {
            $payload = wp_unslash($_POST); // phpcs:ignore WordPress.Security.NonceVerification
        } else {
            $raw = file_get_contents('php://input');
            $decoded = json_decode((string) $raw, true);
            if (is_array($decoded)) {
                $payload = $decoded;
            }
        }

        $client = WW_IMB_Gateway::make_client();
        $event  = $client->parse_webhook(is_array($payload) ? $payload : []);
        WW_IMB_Gateway::log('webhook => ' . wp_json_encode($event));

        if ($event['order_id'] === '') {
            status_header(200);
            echo 'NO_ORDER_ID';
            exit;
        }

        $order = $this->find_order_by_imb_id($event['order_id']);
        if ($order instanceof WC_Order) {
            // Idempotent + authoritative re-verification happens inside confirm_payment().
            WW_IMB_Gateway::confirm_payment($order);
        }

        status_header(200);
        echo 'OK';
        exit;
    }

    public function ajax_status(): void
    {
        $order_id = isset($_GET['order_id']) ? absint($_GET['order_id']) : 0; // phpcs:ignore WordPress.Security.NonceVerification
        $key      = isset($_GET['key']) ? sanitize_text_field(wp_unslash($_GET['key'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification

        $order = $order_id ? wc_get_order($order_id) : false;
        if (!$order || !hash_equals($order->get_order_key(), $key)) {
            wp_send_json(['status' => 'ERROR', 'paid' => false], 403);
        }

        $status = WW_IMB_Gateway::confirm_payment($order);
        wp_send_json([
            'status'   => $status,
            'paid'     => $status === WW_IMB_Client::STATUS_SUCCESS,
            'redirect' => $status === WW_IMB_Client::STATUS_SUCCESS ? $order->get_checkout_order_received_url() : '',
        ]);
    }

    public function maybe_render_qr_page(): void
    {
        if (!isset($_GET['ww_imb_pay'])) { // phpcs:ignore WordPress.Security.NonceVerification
            return;
        }
        $order_id = absint($_GET['ww_imb_pay']); // phpcs:ignore WordPress.Security.NonceVerification
        $key      = isset($_GET['key']) ? sanitize_text_field(wp_unslash($_GET['key'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification

        $order = $order_id ? wc_get_order($order_id) : false;
        if (!$order || !hash_equals($order->get_order_key(), $key)) {
            wp_die(esc_html__('Invalid or expired payment link.', 'weewoo-imb-pay'), '', ['response' => 403]);
        }

        // Already paid? Send straight to the thank-you page.
        if ($order->is_paid() || $order->has_status(['processing', 'completed'])) {
            wp_safe_redirect($order->get_checkout_order_received_url());
            exit;
        }

        $this->enqueue_assets($order);

        // Expose data to the template.
        $ctx = [
            'order'       => $order,
            'bhim_link'   => (string) $order->get_meta('_ww_imb_bhim'),
            'paytm_link'  => (string) $order->get_meta('_ww_imb_paytm'),
            'payment_url' => (string) $order->get_meta('_ww_imb_payurl'),
        ];
        require WW_IMB_DIR . 'templates/qr-checkout.php';
        exit;
    }

    private function enqueue_assets(WC_Order $order): void
    {
        wp_enqueue_style('ww-imb-checkout', WW_IMB_URL . 'assets/css/checkout.css', [], WW_IMB_VERSION);

        // QR rendered client-side from the UPI string (qrcodejs).
        wp_enqueue_script(
            'ww-imb-qrcode',
            'https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js',
            [],
            '1.0.0',
            true
        );
        wp_enqueue_script('ww-imb-checkout', WW_IMB_URL . 'assets/js/checkout.js', ['ww-imb-qrcode'], WW_IMB_VERSION, true);

        wp_localize_script('ww-imb-checkout', 'WW_IMB', [
            'statusUrl'    => add_query_arg(
                ['action' => 'ww_imb_status', 'order_id' => $order->get_id(), 'key' => $order->get_order_key()],
                admin_url('admin-ajax.php')
            ),
            'bhim'           => (string) $order->get_meta('_ww_imb_bhim'),
            'paymentUrl'     => (string) $order->get_meta('_ww_imb_payurl'),
            'pollInterval'   => 4000,
            'expirySeconds'  => (int) apply_filters('ww_imb_qr_expiry_seconds', 600),
        ]);
    }

    private function find_order_by_imb_id(string $imb_order_id)
    {
        $orders = wc_get_orders([
            'limit'      => 1,
            'meta_key'   => '_ww_imb_order_id', // phpcs:ignore WordPress.DB.SlowDBQuery
            'meta_value' => $imb_order_id,      // phpcs:ignore WordPress.DB.SlowDBQuery
        ]);
        return !empty($orders) ? $orders[0] : null;
    }
}
