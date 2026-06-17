<?php
/**
 * Branded UPI QR checkout page (premium glass edition, matches WeeWoo Auth Pro).
 *
 * No product/order line items — just the amount and the QR.
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
$primary    = apply_filters('ww_imb_primary_color', '#10B981');
$home       = home_url('/');
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="#0a0f1c">
<title><?php echo esc_html($brand_name); ?> — <?php esc_html_e('Secure Payment', 'weewoo-imb-pay'); ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>:root{--primary:<?php echo esc_attr($primary); ?>;}</style>
<?php wp_head(); ?>
</head>
<body class="ww-imb-body">
<div class="ww-orbs"><div class="ww-orb a"></div><div class="ww-orb b"></div><div class="ww-orb c"></div></div>

<div class="ww-stage">
  <div class="ww-brand"><span class="dot"></span><?php echo esc_html($brand_name); ?></div>

  <div class="ww-card">
    <div class="ww-h">
      <h1><?php esc_html_e('Scan to Pay', 'weewoo-imb-pay'); ?></h1>
      <p><?php esc_html_e('Complete your payment securely with any UPI app', 'weewoo-imb-pay'); ?></p>
    </div>

    <div class="ww-amount">
      <div class="lbl"><?php esc_html_e('Amount Payable', 'weewoo-imb-pay'); ?></div>
      <div class="val"><?php echo wp_kses_post(wc_price($order->get_total(), ['currency' => $order->get_currency()])); ?></div>
      <div class="ord"><?php echo esc_html(sprintf(__('Order #%s', 'weewoo-imb-pay'), $order->get_order_number())); ?></div>
    </div>

    <div class="ww-qr-wrap">
      <div class="box">
        <div class="scan"></div>
        <div id="ww-qr" aria-label="<?php esc_attr_e('UPI QR code', 'weewoo-imb-pay'); ?>"></div>
        <div class="badge">UPI</div>
      </div>
      <div class="qr-hint"><?php esc_html_e('Open any UPI app and scan this code to pay', 'weewoo-imb-pay'); ?></div>
      <div class="ww-apps">
        <span class="ww-appchip"><i style="background:#4285f4"></i>GPay</span>
        <span class="ww-appchip"><i style="background:#5f259f"></i>PhonePe</span>
        <span class="ww-appchip"><i style="background:#00baf2"></i>Paytm</span>
        <span class="ww-appchip"><i style="background:#00a651"></i>BHIM</span>
      </div>
    </div>

    <?php if ($bhim_link) : ?>
      <div class="ww-div"><?php esc_html_e('or pay on this phone', 'weewoo-imb-pay'); ?></div>
      <a class="ww-btn" href="<?php echo esc_url($bhim_link); ?>"><?php esc_html_e('Open UPI App', 'weewoo-imb-pay'); ?> <span class="arrow">→</span></a>
    <?php endif; ?>
    <?php if ($paytm_link) : ?>
      <a class="ww-btn ww-btn-paytm" href="<?php echo esc_url($paytm_link); ?>" style="margin-top:10px"><?php esc_html_e('Pay with Paytm', 'weewoo-imb-pay'); ?></a>
    <?php endif; ?>

    <div class="ww-status" id="ww-status"><span class="ww-spin"></span><span><?php esc_html_e('Waiting for payment…', 'weewoo-imb-pay'); ?></span></div>
    <div class="ww-foot-note">🔒 <?php esc_html_e('Encrypted · Powered by IMB UPI · NPCI · Do not close this page', 'weewoo-imb-pay'); ?></div>

    <div class="ww-success" id="ww-success">
      <div class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M4 12.5l5 5L20 6.5"/></svg></div>
      <h2><?php esc_html_e('Payment received', 'weewoo-imb-pay'); ?></h2>
      <p><?php esc_html_e('Redirecting to your order…', 'weewoo-imb-pay'); ?></p>
    </div>
  </div>

  <div class="ww-foot"><a href="<?php echo esc_url($home); ?>">← <?php echo esc_html(sprintf(__('Return to %s', 'weewoo-imb-pay'), $brand_name)); ?></a></div>
</div>

<?php wp_footer(); ?>
</body>
</html>
