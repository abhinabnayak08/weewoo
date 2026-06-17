<?php
/**
 * WooCommerce payment gateway: WeeWoo Pay (IMB UPI).
 *
 * @package WeeWoo_IMB_Pay
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class WW_IMB_Gateway extends WC_Payment_Gateway
{
    public const GATEWAY_ID = 'weewoo_imb';

    public function __construct()
    {
        $this->id                 = self::GATEWAY_ID;
        $this->method_title       = __('WeeWoo Pay — IMB UPI', 'weewoo-imb-pay');
        $this->method_description = __('Branded UPI QR checkout powered by IMB. Customers scan our QR; orders auto-confirm via IMB verification.', 'weewoo-imb-pay');
        $this->has_fields         = false;
        $this->supports           = ['products'];

        $this->init_form_fields();
        $this->init_settings();

        $this->title       = $this->get_option('title', 'UPI / QR (WeeWoo Pay)');
        $this->description = $this->get_option('description', 'Pay instantly with any UPI app. You will see a QR code on the next screen.');
        $this->icon        = apply_filters('ww_imb_icon', WW_IMB_URL . 'assets/img/upi.svg');

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
    }

    public function init_form_fields(): void
    {
        $this->form_fields = [
            'enabled' => [
                'title'   => __('Enable/Disable', 'weewoo-imb-pay'),
                'type'    => 'checkbox',
                'label'   => __('Enable WeeWoo Pay (IMB UPI)', 'weewoo-imb-pay'),
                'default' => 'no',
            ],
            'title' => [
                'title'       => __('Title', 'weewoo-imb-pay'),
                'type'        => 'text',
                'description' => __('Shown to customers at checkout.', 'weewoo-imb-pay'),
                'default'     => 'UPI / QR (WeeWoo Pay)',
                'desc_tip'    => true,
            ],
            'description' => [
                'title'   => __('Description', 'weewoo-imb-pay'),
                'type'    => 'textarea',
                'default' => 'Pay instantly with any UPI app. You will see a QR code on the next screen.',
            ],
            'user_token' => [
                'title'       => __('IMB User Token', 'weewoo-imb-pay'),
                'type'        => 'password',
                'description' => __('From IMB dashboard → API Credentials. Stored in your database, never exposed to customers.', 'weewoo-imb-pay'),
                'default'     => '',
            ],
            'api_base' => [
                'title'       => __('IMB API Base URL', 'weewoo-imb-pay'),
                'type'        => 'text',
                'description' => __('Current recommended host (fixes QR/Airtel issues). Legacy: https://pay.imb.org.in', 'weewoo-imb-pay'),
                'default'     => 'https://api.imbpay.in',
            ],
            'debug' => [
                'title'   => __('Debug logging', 'weewoo-imb-pay'),
                'type'    => 'checkbox',
                'label'   => __('Log IMB requests/responses to WooCommerce → Status → Logs', 'weewoo-imb-pay'),
                'default' => 'no',
            ],
        ];
    }

    /**
     * Safety-net reconciler (wp-cron): re-check recent unpaid orders against IMB
     * so payments still complete even if both the webhook and the on-page poll
     * were missed (e.g. customer closed the tab and a webhook delivery failed).
     */
    public static function reconcile_pending(): void
    {
        if (!function_exists('wc_get_orders')) {
            return;
        }
        $orders = wc_get_orders([
            'payment_method' => self::GATEWAY_ID,
            'status'         => ['pending', 'on-hold'],
            'limit'          => 50,
            'date_created'   => '>' . (time() - DAY_IN_SECONDS),
        ]);
        foreach ($orders as $order) {
            self::confirm_payment($order);
        }
    }

    /**
     * Build a client from the saved settings (usable from static contexts).
     */
    public static function make_client(): WW_IMB_Client
    {
        $opts = get_option('woocommerce_' . self::GATEWAY_ID . '_settings', []);
        return new WW_IMB_Client(
            (string) ($opts['user_token'] ?? ''),
            (string) ($opts['api_base'] ?? 'https://api.imbpay.in')
        );
    }

    public static function log(string $message): void
    {
        $opts = get_option('woocommerce_' . self::GATEWAY_ID . '_settings', []);
        if (($opts['debug'] ?? 'no') !== 'yes' || !function_exists('wc_get_logger')) {
            return;
        }
        wc_get_logger()->info($message, ['source' => self::GATEWAY_ID]);
    }

    /**
     * Persist an error so the dashboard can surface it (capped, newest first).
     */
    public static function log_error(string $context, string $message, int $order_id = 0): void
    {
        $log = get_option('ww_imb_error_log', []);
        if (!is_array($log)) {
            $log = [];
        }
        array_unshift($log, [
            'time'     => current_time('mysql'),
            'context'  => $context,
            'message'  => $message,
            'order_id' => $order_id,
        ]);
        update_option('ww_imb_error_log', array_slice($log, 0, 50), false);
        self::log('ERROR [' . $context . '] ' . $message);
    }

    /**
     * Branded QR page URL for an order (guest-safe via order key).
     */
    public static function qr_page_url(WC_Order $order): string
    {
        return add_query_arg(
            ['ww_imb_pay' => $order->get_id(), 'key' => $order->get_order_key()],
            home_url('/')
        );
    }

    public function process_payment($order_id): array
    {
        $order = wc_get_order($order_id);
        if (!$order) {
            wc_add_notice(__('Order not found.', 'weewoo-imb-pay'), 'error');
            return ['result' => 'failure'];
        }

        $mobile = preg_replace('/\D+/', '', (string) $order->get_billing_phone());
        if (strlen($mobile) > 10) {
            $mobile = substr($mobile, -10);
        }
        // IMB requires a 10-digit mobile. If the order has none (e.g. phone not a
        // required checkout field), allow a store-level fallback so create-order
        // doesn't fail. Filter ww_imb_fallback_mobile to set a default.
        if (strlen($mobile) < 10) {
            $mobile = preg_replace('/\D+/', '', (string) apply_filters('ww_imb_fallback_mobile', '', $order));
            if (strlen($mobile) < 10) {
                self::log_error('missing-mobile', 'Order has no valid 10-digit billing phone; IMB may reject create-order.', $order->get_id());
            }
        }

        // Unique IMB order id mapped back to the WC order via meta.
        $imb_order_id = 'WW' . $order->get_id() . 'T' . time();

        $client = self::make_client();
        $res = $client->create_order([
            'customer_mobile' => $mobile,
            'amount'          => number_format((float) $order->get_total(), 2, '.', ''),
            'order_id'        => $imb_order_id,
            'redirect_url'    => $this->get_return_url($order),
            'remark1'         => $order->get_billing_email(),
            'remark2'         => 'WC#' . $order->get_id(),
        ]);

        self::log('create-order ' . $imb_order_id . ' => ' . wp_json_encode($res));

        if (!$res['ok'] || empty($res['result'])) {
            self::log_error('create-order', $res['message'], $order->get_id());
            wc_add_notice(
                __('Could not start the payment. Please try again. ', 'weewoo-imb-pay') . esc_html($res['message']),
                'error'
            );
            return ['result' => 'failure'];
        }

        $result = $res['result'];
        $order->update_meta_data('_ww_imb_order_id', $imb_order_id);
        $order->update_meta_data('_ww_imb_bhim', (string) ($result['bhim_link'] ?? ''));
        $order->update_meta_data('_ww_imb_paytm', (string) ($result['paytm_link'] ?? ''));
        $order->update_meta_data('_ww_imb_payurl', (string) ($result['payment_url'] ?? ''));
        $order->update_meta_data('_ww_imb_check', (string) ($result['check_link'] ?? ''));
        $order->update_status('pending', __('Awaiting UPI payment via IMB.', 'weewoo-imb-pay'));
        $order->save();

        return [
            'result'   => 'success',
            'redirect' => self::qr_page_url($order),
        ];
    }

    /**
     * Authoritatively confirm an order against IMB. Idempotent.
     *
     * Used by both the webhook and the status poll. Marks the order paid ONLY
     * after check-order-status confirms SUCCESS and the amount matches.
     *
     * @return string One of WW_IMB_Client::STATUS_*.
     */
    public static function confirm_payment(WC_Order $order): string
    {
        if ($order->is_paid() || $order->has_status(['processing', 'completed'])) {
            return WW_IMB_Client::STATUS_SUCCESS; // already handled
        }

        $imb_order_id = (string) $order->get_meta('_ww_imb_order_id');
        if ($imb_order_id === '') {
            return WW_IMB_Client::STATUS_PENDING;
        }

        // Concurrency lock: webhook + poll can fire together; never let two
        // requests complete (and double-reduce stock) for the same order.
        $lock = 'ww_imb_lock_' . $order->get_id();
        if (get_transient($lock)) {
            return WW_IMB_Client::STATUS_PENDING; // another request is verifying; poll again
        }
        set_transient($lock, 1, 20);

        try {
            $client = self::make_client();
            $body   = $client->check_order_status($imb_order_id);
            $status = $client->normalize_status($body);
            self::log('confirm ' . $imb_order_id . ' => ' . $status . ' ' . wp_json_encode($body));

            // Re-read fresh in case another process completed it meanwhile.
            $fresh = wc_get_order($order->get_id());
            if ($fresh && ($fresh->is_paid() || $fresh->has_status(['processing', 'completed']))) {
                return WW_IMB_Client::STATUS_SUCCESS;
            }

            if ($status === WW_IMB_Client::STATUS_SUCCESS) {
                $result = is_array($body['result'] ?? null) ? $body['result'] : [];

                // Amount guard — never complete on a mismatched amount.
                if (isset($result['amount']) && is_numeric($result['amount'])) {
                    if (abs((float) $result['amount'] - (float) $order->get_total()) > 0.01) {
                        self::log_error('amount-mismatch', 'IMB=' . $result['amount'] . ' WC=' . $order->get_total(), $order->get_id());
                        $order->add_order_note(__('IMB reported a different amount than the order total — not auto-completing. Please verify manually.', 'weewoo-imb-pay'));
                        return WW_IMB_Client::STATUS_PENDING;
                    }
                }

                $utr = (string) ($result['utr'] ?? '');
                if ($utr !== '') {
                    $order->update_meta_data('_ww_imb_utr', $utr);
                }
                $order->payment_complete($utr);
                $order->add_order_note(sprintf(
                    /* translators: %s: UPI transaction reference */
                    __('Payment confirmed by IMB. UTR: %s', 'weewoo-imb-pay'),
                    $utr !== '' ? $utr : 'N/A'
                ));
                $order->save();
                return WW_IMB_Client::STATUS_SUCCESS;
            }

            if ($status === WW_IMB_Client::STATUS_FAILED) {
                if (!$order->has_status('failed')) {
                    $order->update_status('failed', __('IMB reported the payment as failed/expired.', 'weewoo-imb-pay'));
                }
                return WW_IMB_Client::STATUS_FAILED;
            }

            return WW_IMB_Client::STATUS_PENDING;
        } finally {
            delete_transient($lock);
        }
    }
}
