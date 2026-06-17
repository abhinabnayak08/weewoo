<?php
/**
 * Premium admin dashboard for the IMB gateway.
 *
 * Overview stats, recent transactions, error log, connection test, and the
 * webhook URL — a real payment-gateway control panel inside WP admin.
 *
 * @package WeeWoo_IMB_Pay
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class WW_IMB_Admin
{
    private static ?WW_IMB_Admin $instance = null;
    private const MENU = 'weewoo-imb';

    public static function instance(): WW_IMB_Admin
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        add_action('admin_menu', [$this, 'menu']);
        add_action('admin_enqueue_scripts', [$this, 'assets']);
        add_action('wp_ajax_ww_imb_test', [$this, 'ajax_test']);
        add_action('wp_ajax_ww_imb_clear_errors', [$this, 'ajax_clear_errors']);
    }

    public function menu(): void
    {
        $cap = current_user_can('manage_woocommerce') ? 'manage_woocommerce' : 'manage_options';
        add_menu_page(
            __('WeeWoo Pay', 'weewoo-imb-pay'),
            __('WeeWoo Pay', 'weewoo-imb-pay'),
            $cap,
            self::MENU,
            [$this, 'render'],
            'dashicons-money-alt',
            56
        );
    }

    public function assets(string $hook): void
    {
        if ($hook !== 'toplevel_page_' . self::MENU) {
            return;
        }
        wp_enqueue_style('ww-imb-admin', WW_IMB_URL . 'assets/css/admin.css', [], WW_IMB_VERSION);
        wp_enqueue_script('ww-imb-admin', WW_IMB_URL . 'assets/js/admin.js', [], WW_IMB_VERSION, true);
        wp_localize_script('ww-imb-admin', 'WW_IMB_ADMIN', [
            'ajax'  => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ww_imb_admin'),
        ]);
    }

    /* ------------------------------------------------------------------ data */

    private function settings(): array
    {
        $o = get_option('woocommerce_' . WW_IMB_Gateway::GATEWAY_ID . '_settings', []);
        return is_array($o) ? $o : [];
    }

    /** @return WC_Order[] */
    private function recent_orders(int $limit = 1000): array
    {
        if (!function_exists('wc_get_orders')) {
            return [];
        }
        return wc_get_orders([
            'payment_method' => WW_IMB_Gateway::GATEWAY_ID,
            'limit'          => $limit,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'date_created'   => '>' . (time() - 30 * DAY_IN_SECONDS),
        ]);
    }

    private function stats(array $orders): array
    {
        $s = ['collected' => 0.0, 'success' => 0, 'pending' => 0, 'failed' => 0, 'count' => 0];
        foreach ($orders as $o) {
            $s['count']++;
            if ($o->has_status(['processing', 'completed'])) {
                $s['success']++;
                $s['collected'] += (float) $o->get_total();
            } elseif ($o->has_status(['failed', 'cancelled'])) {
                $s['failed']++;
            } else {
                $s['pending']++;
            }
        }
        $done = $s['success'] + $s['failed'];
        $s['rate'] = $done ? round($s['success'] / $done * 100) : 0;
        return $s;
    }

    /* ---------------------------------------------------------------- render */

    public function render(): void
    {
        $opts    = $this->settings();
        $orders  = $this->recent_orders();
        $stats   = $this->stats($orders);
        $errors  = get_option('ww_imb_error_log', []);
        $errors  = is_array($errors) ? $errors : [];
        $token   = trim((string) ($opts['user_token'] ?? ''));
        $enabled = ($opts['enabled'] ?? 'no') === 'yes';
        $webhook = WW_IMB_Endpoints::webhook_url();
        $settings_url = admin_url('admin.php?page=wc-settings&tab=checkout&section=' . WW_IMB_Gateway::GATEWAY_ID);
        $cur = function ($n) {
            return function_exists('wc_price') ? wp_kses_post(wc_price($n)) : esc_html(number_format((float) $n, 2));
        };
        ?>
        <div class="wrap ww-adm">
          <div class="ww-adm-top">
            <div class="ww-adm-brand">
              <span class="dot"></span>
              <div><b>WeeWoo Pay</b><span><?php esc_html_e('IMB UPI Gateway · Dashboard', 'weewoo-imb-pay'); ?></span></div>
            </div>
            <div class="ww-adm-actions">
              <span class="ww-chip <?php echo $enabled ? 'on' : 'off'; ?>"><?php echo $enabled ? esc_html__('Live', 'weewoo-imb-pay') : esc_html__('Disabled', 'weewoo-imb-pay'); ?></span>
              <button class="ww-adm-btn ghost" id="ww-test"><?php esc_html_e('Test connection', 'weewoo-imb-pay'); ?></button>
              <a class="ww-adm-btn primary" href="<?php echo esc_url($settings_url); ?>"><?php esc_html_e('Settings', 'weewoo-imb-pay'); ?></a>
            </div>
          </div>

          <div id="ww-test-result" class="ww-adm-note" style="display:none"></div>

          <?php if ($token === '') : ?>
            <div class="ww-adm-note warn">
              <?php
              printf(
                  /* translators: %s settings link */
                  wp_kses_post(__('No IMB User Token set. Add it in <a href="%s">Settings</a> to start accepting payments.', 'weewoo-imb-pay')),
                  esc_url($settings_url)
              );
              ?>
            </div>
          <?php endif; ?>

          <!-- stat cards -->
          <div class="ww-adm-cards">
            <div class="ww-adm-card">
              <span class="lbl"><?php esc_html_e('Collected (30d)', 'weewoo-imb-pay'); ?></span>
              <span class="val"><?php echo $cur($stats['collected']); // phpcs:ignore ?></span>
            </div>
            <div class="ww-adm-card ok">
              <span class="lbl"><?php esc_html_e('Successful', 'weewoo-imb-pay'); ?></span>
              <span class="val"><?php echo (int) $stats['success']; ?></span>
            </div>
            <div class="ww-adm-card warn">
              <span class="lbl"><?php esc_html_e('Pending', 'weewoo-imb-pay'); ?></span>
              <span class="val"><?php echo (int) $stats['pending']; ?></span>
            </div>
            <div class="ww-adm-card bad">
              <span class="lbl"><?php esc_html_e('Failed', 'weewoo-imb-pay'); ?></span>
              <span class="val"><?php echo (int) $stats['failed']; ?></span>
            </div>
            <div class="ww-adm-card">
              <span class="lbl"><?php esc_html_e('Success rate', 'weewoo-imb-pay'); ?></span>
              <span class="val"><?php echo (int) $stats['rate']; ?>%</span>
            </div>
          </div>

          <div class="ww-adm-grid">
            <!-- transactions -->
            <div class="ww-adm-panel">
              <div class="ww-adm-phead"><h2><?php esc_html_e('Recent transactions', 'weewoo-imb-pay'); ?></h2><span><?php echo (int) $stats['count']; ?> <?php esc_html_e('in 30 days', 'weewoo-imb-pay'); ?></span></div>
              <?php if (empty($orders)) : ?>
                <p class="ww-adm-empty"><?php esc_html_e('No transactions yet.', 'weewoo-imb-pay'); ?></p>
              <?php else : ?>
              <table class="ww-adm-table">
                <thead><tr>
                  <th><?php esc_html_e('Order', 'weewoo-imb-pay'); ?></th>
                  <th><?php esc_html_e('Amount', 'weewoo-imb-pay'); ?></th>
                  <th><?php esc_html_e('Status', 'weewoo-imb-pay'); ?></th>
                  <th><?php esc_html_e('UTR', 'weewoo-imb-pay'); ?></th>
                  <th><?php esc_html_e('Date', 'weewoo-imb-pay'); ?></th>
                </tr></thead>
                <tbody>
                <?php foreach (array_slice($orders, 0, 25) as $o) :
                    $st = $o->has_status(['processing', 'completed']) ? 'ok' : ($o->has_status(['failed', 'cancelled']) ? 'bad' : 'warn'); ?>
                  <tr>
                    <td><a href="<?php echo esc_url($o->get_edit_order_url()); ?>">#<?php echo esc_html($o->get_order_number()); ?></a></td>
                    <td><?php echo $cur($o->get_total()); // phpcs:ignore ?></td>
                    <td><span class="ww-badge <?php echo esc_attr($st); ?>"><?php echo esc_html(wc_get_order_status_name($o->get_status())); ?></span></td>
                    <td class="mono"><?php echo esc_html($o->get_meta('_ww_imb_utr') ?: '—'); ?></td>
                    <td><?php echo esc_html($o->get_date_created() ? $o->get_date_created()->date_i18n('M j, H:i') : '—'); ?></td>
                  </tr>
                <?php endforeach; ?>
                </tbody>
              </table>
              <?php endif; ?>
            </div>

            <!-- side: connection + errors -->
            <div class="ww-adm-side">
              <div class="ww-adm-panel">
                <div class="ww-adm-phead"><h2><?php esc_html_e('Connection', 'weewoo-imb-pay'); ?></h2></div>
                <div class="ww-adm-kv"><span><?php esc_html_e('API host', 'weewoo-imb-pay'); ?></span><b class="mono"><?php echo esc_html($opts['api_base'] ?? 'https://api.imbpay.in'); ?></b></div>
                <div class="ww-adm-kv"><span><?php esc_html_e('Token', 'weewoo-imb-pay'); ?></span><b><?php echo $token ? '••••' . esc_html(substr($token, -4)) : esc_html__('not set', 'weewoo-imb-pay'); ?></b></div>
                <label class="ww-adm-field"><?php esc_html_e('Webhook URL (set this in IMB dashboard)', 'weewoo-imb-pay'); ?></label>
                <div class="ww-adm-copy">
                  <input type="text" readonly value="<?php echo esc_attr($webhook); ?>" id="ww-webhook">
                  <button class="ww-adm-btn ghost" id="ww-copy"><?php esc_html_e('Copy', 'weewoo-imb-pay'); ?></button>
                </div>
              </div>

              <div class="ww-adm-panel">
                <div class="ww-adm-phead">
                  <h2><?php esc_html_e('Error log', 'weewoo-imb-pay'); ?></h2>
                  <?php if (!empty($errors)) : ?><button class="ww-adm-link" id="ww-clear"><?php esc_html_e('Clear', 'weewoo-imb-pay'); ?></button><?php endif; ?>
                </div>
                <?php if (empty($errors)) : ?>
                  <p class="ww-adm-empty"><?php esc_html_e('No errors. All good. ✓', 'weewoo-imb-pay'); ?></p>
                <?php else : ?>
                  <ul class="ww-adm-errs">
                    <?php foreach (array_slice($errors, 0, 15) as $e) : ?>
                      <li>
                        <span class="tag"><?php echo esc_html($e['context'] ?? 'error'); ?></span>
                        <span class="msg"><?php echo esc_html($e['message'] ?? ''); ?></span>
                        <span class="t"><?php echo esc_html($e['time'] ?? ''); ?><?php echo !empty($e['order_id']) ? ' · #' . esc_html((string) $e['order_id']) : ''; ?></span>
                      </li>
                    <?php endforeach; ?>
                  </ul>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>
        <?php
    }

    /* ------------------------------------------------------------------ ajax */

    public function ajax_test(): void
    {
        check_ajax_referer('ww_imb_admin', 'nonce');
        if (!current_user_can('manage_woocommerce') && !current_user_can('manage_options')) {
            wp_send_json(['ok' => false, 'msg' => 'Permission denied'], 403);
        }
        $opts = $this->settings();
        if (trim((string) ($opts['user_token'] ?? '')) === '') {
            wp_send_json(['ok' => false, 'msg' => __('No User Token set. Add it in Settings first.', 'weewoo-imb-pay')]);
        }
        // A ping: a random order id returns a valid JSON error from IMB, which
        // still proves the host is reachable and the token is being processed.
        $client = WW_IMB_Gateway::make_client();
        $resp   = $client->check_order_status('PINGTEST' . wp_rand(10000, 99999));
        if (is_array($resp)) {
            wp_send_json(['ok' => true, 'msg' => __('Connected to IMB ✓ — API reachable and responding.', 'weewoo-imb-pay')]);
        }
        wp_send_json(['ok' => false, 'msg' => __('Could not reach IMB. Check the API host and your server\'s outbound network.', 'weewoo-imb-pay')]);
    }

    public function ajax_clear_errors(): void
    {
        check_ajax_referer('ww_imb_admin', 'nonce');
        if (!current_user_can('manage_woocommerce') && !current_user_can('manage_options')) {
            wp_send_json(['ok' => false], 403);
        }
        delete_option('ww_imb_error_log');
        wp_send_json(['ok' => true]);
    }
}
