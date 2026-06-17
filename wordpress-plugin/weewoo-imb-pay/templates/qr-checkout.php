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

      <div class="ww-upiapps" aria-label="<?php esc_attr_e('Supported UPI apps', 'weewoo-imb-pay'); ?>">
        <span class="ww-upiapp" title="Google Pay">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48"><path fill="#e64a19" d="M42.858,11.975c-4.546-2.624-10.359-1.065-12.985,3.481L23.25,26.927c-1.916,3.312,0.551,4.47,3.301,6.119l6.372,3.678c2.158,1.245,4.914,0.506,6.158-1.649l6.807-11.789C48.176,19.325,46.819,14.262,42.858,11.975z"/><path fill="#fbc02d" d="M35.365,16.723l-6.372-3.678c-3.517-1.953-5.509-2.082-6.954,0.214l-9.398,16.275c-2.624,4.543-1.062,10.353,3.481,12.971c3.961,2.287,9.024,0.93,11.311-3.031l9.578-16.59C38.261,20.727,37.523,17.968,35.365,16.723z"/><path fill="#43a047" d="M36.591,8.356l-4.476-2.585c-4.95-2.857-11.28-1.163-14.137,3.787L9.457,24.317c-1.259,2.177-0.511,4.964,1.666,6.22l5.012,2.894c2.475,1.43,5.639,0.582,7.069-1.894l9.735-16.86c2.017-3.492,6.481-4.689,9.974-2.672L36.591,8.356z"/><path fill="#1e88e5" d="M19.189,13.781l-4.838-2.787c-2.158-1.242-4.914-0.506-6.158,1.646l-5.804,10.03c-2.857,4.936-1.163,11.252,3.787,14.101l3.683,2.121l4.467,2.573l1.939,1.115c-3.442-2.304-4.535-6.92-2.43-10.555l1.503-2.596l5.504-9.51C22.083,17.774,21.344,15.023,19.189,13.781z"/></svg>
        </span>
        <span class="ww-upiapp" title="Paytm">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48"><path fill="#0d47a1" d="M5.446 18.01H.548c-.277 0-.502.167-.503.502L0 30.519c-.001.3.196.45.465.45.735 0 1.335 0 2.07 0C2.79 30.969 3 30.844 3 30.594 3 29.483 3 28.111 3 27l2.126.009c1.399-.092 2.335-.742 2.725-2.052.117-.393.14-.733.14-1.137l.11-2.862C7.999 18.946 6.949 18.181 5.446 18.01zM4.995 23.465C4.995 23.759 4.754 24 4.461 24H3v-3h1.461c.293 0 .534.24.534.535V23.465zM13.938 18h-3.423c-.26 0-.483.08-.483.351 0 .706 0 1.495 0 2.201C10.06 20.846 10.263 21 10.552 21h2.855c.594 0 .532.972 0 1H11.84C10.101 22 9 23.562 9 25.137c0 .42.005 1.406 0 1.863-.008.651-.014 1.311.112 1.899C9.336 29.939 10.235 31 11.597 31h4.228c.541 0 1.173-.474 1.173-1.101v-8.274C17.026 19.443 15.942 18.117 13.938 18zM14 27.55c0 .248-.202.45-.448.45h-1.105C12.201 28 12 27.798 12 27.55v-2.101C12 25.202 12.201 25 12.447 25h1.105C13.798 25 14 25.202 14 25.449V27.55zM18 18.594v5.608c.124 1.6 1.608 2.798 3.171 2.798h1.414c.597 0 .561.969 0 .969H19.49c-.339 0-.462.177-.462.476v2.152c0 .226.183.396.422.396h2.959c2.416 0 3.592-1.159 3.591-3.757v-8.84c0-.276-.175-.383-.342-.383h-2.302c-.224 0-.355.243-.355.422v5.218c0 .199-.111.316-.29.316H21.41c-.264 0-.409-.143-.409-.396v-5.058C21 18.218 20.88 18 20.552 18c-.778 0-1.442 0-2.22 0C18.067 18 18 18.263 18 18.594z"/><path fill="#00adee" d="M27.038 20.569v-2.138c0-.237.194-.431.43-.431H28c1.368-.285 1.851-.62 2.688-1.522.514-.557.966-.704 1.298-.113L32 18h1.569C33.807 18 34 18.194 34 18.431v2.138C34 20.805 33.806 21 33.569 21H32v9.569C32 30.807 31.806 31 31.57 31h-2.14C29.193 31 29 30.807 29 30.569V21h-1.531C27.234 21 27.038 20.806 27.038 20.569zM42.991 30.465c0 .294-.244.535-.539.535h-1.91c-.297 0-.54-.241-.54-.535v-6.623-1.871c0-1.284-2.002-1.284-2.002 0v8.494C38 30.759 37.758 31 37.461 31H35.54C35.243 31 35 30.759 35 30.465V18.537C35 18.241 35.243 18 35.54 18h1.976c.297 0 .539.241.539.537v.292c1.32-1.266 3.302-.973 4.416.228 2.097-2.405 5.69-.262 5.523 2.375 0 2.916-.026 6.093-.026 9.033 0 .294-.244.535-.538.535h-1.891C45.242 31 45 30.759 45 30.465c0-2.786 0-5.701 0-8.44 0-1.307-2-1.37-2 0v8.44z"/></svg>
        </span>
        <span class="ww-upiapp" title="BHIM UPI">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48"><polygon fill="#388e3c" points="29,4 18,45 40,24"/><polygon fill="#f57c00" points="21,3 10,44 32,23"/></svg>
        </span>
        <span class="ww-upiapp" title="PhonePe">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48"><circle cx="24" cy="24" r="20" fill="#5f259f"/><path fill="#fff" d="M33 18.4c0-.77-.63-1.4-1.4-1.4h-3.05l-2.5-2.86a1.78 1.78 0 0 0-1.5-.64l-2.2.3c-.36.05-.5.5-.22.74l2.66 2.32h-7.2a.7.7 0 0 0-.7.7v.93c0 .39.31.7.7.7h1.62v5.3c0 3.04 1.57 4.83 4.27 4.83.83 0 1.53-.1 2.36-.43v3.43c0 .96.78 1.74 1.74 1.74h1.3c.3 0 .54-.24.54-.54V21.2h2.43c.4 0 .64-.24.64-.64v-2.16zm-7.4 8.6c-.5.23-1.13.33-1.6.33-1.24 0-1.77-.63-1.77-2.03V19.9h3.37v7.1z"/></svg>
        </span>
      </div>

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
