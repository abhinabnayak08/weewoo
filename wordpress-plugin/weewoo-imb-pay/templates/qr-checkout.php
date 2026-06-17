<?php
/**
 * Branded UPI QR checkout page (full standalone document).
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
$total       = $order->get_total();
$currency    = get_woocommerce_currency_symbol($order->get_currency());
$store_name  = get_bloginfo('name');
$brand_name  = apply_filters('ww_imb_brand_name', 'WeeWoo Pay');
$logo_letter = mb_substr($brand_name, 0, 1);
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>"/>
<meta name="viewport" content="width=device-width, initial-scale=1"/>
<meta name="robots" content="noindex,nofollow"/>
<title><?php echo esc_html($brand_name . ' — ' . $currency . $total); ?></title>
<?php wp_head(); ?>
</head>
<body class="ww-imb-body">
<div class="ww-orb a"></div><div class="ww-orb b"></div>

<div class="ww-wrap">
  <section class="ww-card ww-summary">
    <div class="ww-brand">
      <div class="ww-logo"><?php echo esc_html($logo_letter); ?></div>
      <div><b><?php echo esc_html($brand_name); ?></b><span><?php esc_html_e('Secure UPI checkout', 'weewoo-imb-pay'); ?></span></div>
    </div>
    <div class="ww-merchant">
      <?php
      /* translators: 1: store name, 2: order number */
      printf(
          esc_html__('Paying %1$s · Order %2$s', 'weewoo-imb-pay'),
          '<b>' . esc_html($store_name) . '</b>',
          '<b>#' . esc_html($order->get_order_number()) . '</b>'
      );
      ?>
    </div>
    <div class="ww-items">
      <?php foreach ($order->get_items() as $item) : ?>
        <div class="ww-item">
          <div class="ww-thumb">🛍️</div>
          <div>
            <div class="t"><?php echo esc_html($item->get_name()); ?></div>
            <div class="s"><?php echo esc_html(sprintf(__('Qty %d', 'weewoo-imb-pay'), $item->get_quantity())); ?></div>
          </div>
          <div class="p"><?php echo wp_kses_post(wc_price($order->get_line_total($item, true))); ?></div>
        </div>
      <?php endforeach; ?>
    </div>
    <div class="ww-total">
      <span class="lbl"><?php esc_html_e('Total payable', 'weewoo-imb-pay'); ?></span>
      <span class="amt"><?php echo wp_kses_post(wc_price($total)); ?></span>
    </div>
    <div class="ww-secure"><span class="ww-dotg"></span><?php esc_html_e('Encrypted · Powered by IMB UPI · NPCI', 'weewoo-imb-pay'); ?></div>
  </section>

  <section class="ww-card ww-pay">
    <div class="ww-pill">⏳ <span><?php esc_html_e('Scan to pay with any UPI app', 'weewoo-imb-pay'); ?></span></div>

    <div class="ww-qrbox">
      <div class="ww-scanline"></div>
      <div id="ww-qr" aria-label="<?php esc_attr_e('UPI QR code', 'weewoo-imb-pay'); ?>"></div>
      <div class="ww-badge">UPI</div>
    </div>

    <div class="ww-howto">
      <?php
      /* translators: %s: amount */
      printf(esc_html__('Open any UPI app and scan, or tap below to pay %s.', 'weewoo-imb-pay'), '<b>' . wp_kses_post(wc_price($total)) . '</b>');
      ?>
    </div>

    <div class="ww-apps">
      <span class="ww-appchip"><i style="background:#4285f4"></i>GPay</span>
      <span class="ww-appchip"><i style="background:#5f259f"></i>PhonePe</span>
      <span class="ww-appchip"><i style="background:#00baf2"></i>Paytm</span>
      <span class="ww-appchip"><i style="background:#00a651"></i>BHIM</span>
    </div>

    <?php if ($bhim_link) : ?>
      <div class="ww-sep"><?php esc_html_e('or on mobile', 'weewoo-imb-pay'); ?></div>
      <a class="ww-btn" href="<?php echo esc_url($bhim_link); ?>"><?php esc_html_e('Pay with UPI app', 'weewoo-imb-pay'); ?></a>
    <?php endif; ?>
    <?php if ($paytm_link) : ?>
      <a class="ww-btn ww-btn-paytm" href="<?php echo esc_url($paytm_link); ?>"><?php esc_html_e('Pay with Paytm', 'weewoo-imb-pay'); ?></a>
    <?php endif; ?>

    <div class="ww-status" id="ww-status"><span class="ww-spin"></span><span><?php esc_html_e('Waiting for payment…', 'weewoo-imb-pay'); ?></span></div>
    <div class="ww-foot"><?php esc_html_e('Do not close this page until payment is confirmed.', 'weewoo-imb-pay'); ?></div>

    <div class="ww-success" id="ww-success">
      <div class="ww-check"><svg viewBox="0 0 24 24" fill="none" stroke="#04210f" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 12.5l5 5L20 6.5"/></svg></div>
      <h2><?php esc_html_e('Payment received', 'weewoo-imb-pay'); ?></h2>
      <div class="det"><?php esc_html_e('Redirecting you to your order…', 'weewoo-imb-pay'); ?></div>
    </div>
  </section>
</div>

<?php wp_footer(); ?>
</body>
</html>
