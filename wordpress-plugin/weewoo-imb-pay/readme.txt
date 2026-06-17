=== WeeWoo Pay — IMB UPI Gateway ===
Contributors: weewoo
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
WC requires at least: 7.0
WC tested up to: 10.7
Stable tag: 1.0.0
License: GPLv2 or later

A custom-branded UPI payment gateway for WooCommerce, powered by IMB. Customers
see your own high-end QR checkout page; orders confirm automatically via IMB's
check-order-status (webhook + polling).

== How it works ==

1. Customer selects "UPI / QR (WeeWoo Pay)" at checkout and clicks Place Order.
2. The gateway calls IMB create-order and redirects to the branded QR page.
3. Customer scans our QR (rendered from IMB's UPI link) or taps a UPI app.
4. Payment is confirmed two ways, both authoritative:
   - Webhook: IMB POSTs to the webhook URL below.
   - Polling: the QR page polls every few seconds.
   Either way we re-verify with check-order-status and check the amount before
   marking the order paid (idempotent — never double-credits).
5. WooCommerce moves the order to Processing/Completed and emails the customer.
   The UPI UTR is stored on the order.

== Setup ==

1. Install & activate the plugin (WooCommerce must be active).
2. WooCommerce → Settings → Payments → "WeeWoo Pay — IMB UPI" → Manage.
3. Enable it and paste your IMB User Token (IMB dashboard → API Credentials).
4. In the IMB dashboard, set your Webhook URL to:
       https://YOURDOMAIN/?wc-api=weewoo_imb_webhook
   (Exact URL is shown in WooCommerce logs and can be filtered.)
5. Place a test order.

== Security ==

* The IMB User Token is stored in the WordPress database (gateway settings) and
  is never exposed to the browser.
* Orders are marked paid only after check-order-status confirms SUCCESS and the
  amount matches — a spoofed webhook cannot complete an order.

== Changelog ==

= 1.0.0 =
* Initial release: branded UPI QR checkout, webhook + polling verification,
  amount guard, idempotent confirmation, HPOS + blocks compatible.
