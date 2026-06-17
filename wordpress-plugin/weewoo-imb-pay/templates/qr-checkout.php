<?php
/**
 * Branded UPI QR checkout page — matches weewoo.in/checkout.
 *
 * Dark header (lime amount + Instant Verification pill), Scan QR / How to pay
 * tabs, QR with centered brand badge, Download QR, lime countdown, and AUTO
 * verification (polls check-order-status; the lime button is an optional
 * "confirm now" accelerator — no click required).
 *
 * @var WC_Order $order
 * @var string   $bhim_link
 * @var string   $paytm_link
 * @var string   $payment_url
 *
 * @package WeeWoo_IMB_Pay
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/** @var WC_Order $order */
$brand_name = apply_filters('ww_imb_brand_name', get_bloginfo('name'));
$accent     = apply_filters('ww_imb_primary_color', '#bee63c');
$home       = home_url('/');
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="#0f1620">
<title><?php echo esc_html($brand_name); ?> — <?php esc_html_e('Checkout', 'weewoo-imb-pay'); ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>:root{--lime:<?php echo esc_attr($accent); ?>;}</style>
<?php wp_head(); ?>
</head>
<body class="ww-imb-body">
<div class="ww-sheet">
  <div class="ww-head">
    <span class="ww-vpill">⚡ <?php esc_html_e('Instant verification', 'weewoo-imb-pay'); ?></span>
    <div class="ww-amt"><span class="rs"><?php echo esc_html(get_woocommerce_currency_symbol($order->get_currency())); ?></span><?php echo esc_html(number_format((float) $order->get_total(), 2)); ?></div>
    <div class="ww-ord"><?php echo esc_html(sprintf(__('Order #%s', 'weewoo-imb-pay'), $order->get_order_number())); ?></div>
  </div>

  <div class="ww-bodyx">
    <div class="ww-tabs" id="ww-tabs" data-active="scan">
      <span class="ww-tab-ind"></span>
      <button class="ww-tab active" data-pane="scan"><?php esc_html_e('Scan QR', 'weewoo-imb-pay'); ?></button>
      <button class="ww-tab" data-pane="how"><?php esc_html_e('How to pay', 'weewoo-imb-pay'); ?></button>
    </div>

    <div class="ww-pane active" id="ww-pane-scan">
      <div class="ww-qr-card">
        <div id="ww-qr" aria-label="<?php esc_attr_e('UPI QR code', 'weewoo-imb-pay'); ?>"></div>
        <div class="ww-qr-logo"><?php echo wp_kses_post(apply_filters('ww_imb_qr_logo_html', '<span class="w">' . esc_html($brand_name) . '</span>')); ?></div>
      </div>

      <p class="ww-hint"><?php esc_html_e("Scan with any UPI app. On mobile, tap Download and open the QR from your app's gallery.", 'weewoo-imb-pay'); ?></p>

      <button class="ww-dl" id="ww-dl">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v12m0 0l-4-4m4 4l4-4M5 21h14"/></svg>
        <?php esc_html_e('Download QR', 'weewoo-imb-pay'); ?>
      </button>

      <div class="ww-valid">
        <span class="lbl"><?php esc_html_e('QR valid for', 'weewoo-imb-pay'); ?></span>
        <span class="track"><span class="fill" id="ww-fill"></span></span>
        <span class="time" id="ww-time">10:00</span>
      </div>

      <div class="ww-autostatus" id="ww-status"><span class="ww-spin"></span> <span><?php esc_html_e('Auto-checking your payment…', 'weewoo-imb-pay'); ?></span></div>

      <div class="ww-info">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M13 2L3 14h7l-1 8 10-12h-7l1-8z"/></svg>
        <p><b><?php esc_html_e('No need to do anything', 'weewoo-imb-pay'); ?></b> — <?php esc_html_e('we check your payment automatically and confirm the moment it arrives. Keep this page open.', 'weewoo-imb-pay'); ?></p>
      </div>
    </div>

    <div class="ww-pane" id="ww-pane-how">
      <div class="ww-steps">
        <div class="ww-step"><span class="n"></span><span class="t"><b><?php esc_html_e('Open any UPI app', 'weewoo-imb-pay'); ?></b><span><?php esc_html_e('Google Pay, PhonePe, Paytm, or BHIM.', 'weewoo-imb-pay'); ?></span></span></div>
        <div class="ww-step"><span class="n"></span><span class="t"><b><?php esc_html_e('Scan the QR code', 'weewoo-imb-pay'); ?></b><span><?php esc_html_e('Or tap "Download QR to pay" and pick it from your gallery.', 'weewoo-imb-pay'); ?></span></span></div>
        <div class="ww-step"><span class="n"></span><span class="t"><b><?php echo esc_html(sprintf(__('Pay %s', 'weewoo-imb-pay'), get_woocommerce_currency_symbol($order->get_currency()) . number_format((float) $order->get_total(), 2))); ?></b><span><?php esc_html_e('Confirm the payment in your UPI app.', 'weewoo-imb-pay'); ?></span></span></div>
        <div class="ww-step"><span class="n"></span><span class="t"><b><?php esc_html_e('Done — we verify automatically', 'weewoo-imb-pay'); ?></b><span><?php esc_html_e('No waiting and nothing to click. Just keep this page open.', 'weewoo-imb-pay'); ?></span></span></div>
      </div>
    </div>
  </div>

  <div class="ww-success" id="ww-success">
    <div class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="#0e1a05" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 13l4 4L19 7"/></svg></div>
    <h2><?php esc_html_e('Payment verified', 'weewoo-imb-pay'); ?></h2>
    <p><?php esc_html_e('Redirecting to your order…', 'weewoo-imb-pay'); ?></p>
  </div>
</div>

<?php wp_footer(); ?>
</body>
</html>
