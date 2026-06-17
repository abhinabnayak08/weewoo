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
            $this->respond_200('NO_ORDER_ID');
        }

        $order = $this->find_order_by_imb_id($event['order_id']);
        if (!$order instanceof WC_Order) {
            $this->respond_200('IGNORED'); // unknown order — ack so IMB doesn't retry forever
        }

        // Per IMB's Callback Report guidance: acknowledge with 200 FAST, then do
        // the heavy work (outbound check-order-status verification) after the
        // response is flushed — so the gateway never sees a slow/failed delivery.
        $order_id = $order->get_id();
        $this->respond_200('OK', false); // send 200 but don't exit yet
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }
        // Authoritative, idempotent re-verification (never trusts payload alone).
        $fresh = wc_get_order($order_id);
        if ($fresh instanceof WC_Order) {
            WW_IMB_Gateway::confirm_payment($fresh);
        }
        exit;
    }

    /**
     * Send a quick HTTP 200 acknowledgement to the gateway.
     */
    private function respond_200(string $body, bool $exit = true): void
    {
        if (!headers_sent()) {
            status_header(200);
            nocache_headers();
        }
        echo esc_html($body);
        // Flush output buffers so the body is on the wire immediately.
        if (function_exists('wp_ob_end_flush_all')) {
            wp_ob_end_flush_all();
        }
        flush();
        if ($exit) {
            exit;
        }
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
        if ($imb_order_id === '') {
            return null;
        }
        // meta_query is HPOS-safe (works on custom order tables and legacy posts).
        $orders = wc_get_orders([
            'limit'      => 1,
            'meta_query' => [
                ['key' => '_ww_imb_order_id', 'value' => $imb_order_id, 'compare' => '='],
            ],
        ]);
        return !empty($orders) ? $orders[0] : null;
    }
}
