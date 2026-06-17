# WeeWoo Pay (IMB UPI) — Engineering Handoff

A complete, self-contained reference for the **WeeWoo Pay — IMB UPI Gateway**
WooCommerce plugin: how it works, every file, the payment/verification flow, the
security model, settings/filters, data it stores, deployment, and a debugging
runbook for someone with **SSH access to the Hostinger server (`weewoo.in`)**.

> TL;DR — A customer pays with UPI by scanning our own branded QR (built from
> IMB's `bhim_link`). The order is marked paid **only** after our server confirms
> with IMB's `check-order-status` API (server-to-server). Three independent paths
> guarantee confirmation: on-page polling, IMB webhook, and a 5-minute cron
> reconciler. No browser/client input can ever mark an order paid.

---

## 1. Where it lives

- Repo: `abhinabnayak08/weewoo`, branch `claude/imb-gateway-integration-l4idj7`
- Plugin folder in repo: `wordpress-plugin/weewoo-imb-pay/`
- On the server: `…/public_html/wp-content/plugins/weewoo-imb-pay/`
- There is also a **Python reference** implementation at `backend/imb_gateway.py`
  (+ `backend/server.py`) — NOT used in production; the WooCommerce plugin is the
  live system. Python unit tests: `tests/test_imb_gateway.py`.

---

## 2. File-by-file

```
weewoo-imb-pay/
├── weewoo-imb-pay.php              # Bootstrap: constants, HPOS decl, gateway+blocks
│                                   #   registration, admin load, cron schedule,
│                                   #   activation/deactivation hooks.
├── includes/
│   ├── class-ww-imb-client.php     # IMB API client (create-order, check-order-status),
│   │                               #   status normalization, webhook parsing.
│   ├── class-ww-imb-gateway.php    # WC_Payment_Gateway subclass. process_payment(),
│   │                               #   confirm_payment() (authoritative), reconcile_pending(),
│   │                               #   settings, logging.
│   ├── class-ww-imb-endpoints.php  # Webhook receiver, status-poll AJAX, branded QR
│   │                               #   page renderer, asset enqueue.
│   ├── class-ww-imb-blocks.php     # WooCommerce Checkout-block (Store API) integration.
│   └── class-ww-imb-admin.php      # Premium admin dashboard (stats, transactions,
│                                   #   error log, connection test).
├── templates/
│   └── qr-checkout.php             # Branded QR checkout page (full standalone HTML).
├── assets/
│   ├── css/checkout.css            # Checkout page styles (lime theme, responsive).
│   ├── css/admin.css               # Dashboard styles.
│   ├── js/checkout.js              # QR render, tabs, countdown, auto-verify poll, download.
│   ├── js/admin.js                 # Dashboard: test connection, copy webhook, clear errors.
│   ├── js/blocks.js                # Registers the method on block checkout (no build step).
│   └── img/upi.svg                 # Gateway icon.
└── readme.txt                      # WP.org-style readme.
```

---

## 3. IMB API contract (as implemented)

Default host: `https://api.imbpay.in` (setting `api_base`). Endpoints are derived
from it and overridable via filters.

### 3.1 Create order  →  `POST {api_base}/v2/create-order`
- **Body: `application/x-www-form-urlencoded`** (WP `wp_remote_post` with an array body).
- Fields: `customer_mobile, user_token, amount, order_id, redirect_url, remark1, remark2`.
  - `amount` = rupees, 2dp, no thousands sep (`number_format($total,2,'.','')`).
  - `order_id` = `'WW' . {wc_order_id} . 'T' . time()` (unique; mapped back via meta).
  - `remark1` = customer email, `remark2` = `'WC#' . {wc_order_id}`.
- Success response: `{"status":true,"message":...,"result":{orderId, payment_url,
  paytm_link, phonepe_link, bhim_link, check_link}}`.
- Failure: `{"status":"false","message":"Order_id Already Exist"}` (note `status`
  is the **string** `"false"` — handled by `WW_IMB_Client::truthy()`).

### 3.2 Check order status  →  `POST {api_base}/api/check-order-status`
- We send **JSON first** (`{"user_token","order_id"}`), and if that doesn't return
  a usable status, **retry form-encoded** (robustness — IMB's exact parsing is
  unconfirmed). See `check_order_status()` + `has_status()`.
- Response: `{"status":"COMPLETED","message":...,"result":{txnStatus, status,
  amount, utr, customer_mobile, remark1, remark2, date}}`.
- We use this (not the simpler `check_link`) because it returns **amount + UTR**,
  needed for the amount guard and the receipt.

### 3.3 Webhook (IMB → us)  →  `POST https://weewoo.in/?wc-api=weewoo_imb_webhook`
- Set this URL in the **IMB dashboard → API Credentials → Update Webhook URL**.
- Form-encoded; `result` may be a JSON **string**. Fields: `status`, `order_id`,
  `result.{txnStatus, amount, utr, ...}`. Paid iff `status==SUCCESS` AND
  `result.txnStatus==COMPLETED` — but see §5: we **re-verify** before trusting it.

### Status normalization (`WW_IMB_Client::normalize_status`)
- `SUCCESS` if any of top `status` / `result.status` / `result.txnStatus` ∈
  {COMPLETED, SUCCESS, PAID} (case-insensitive).
- `FAILED` if ∈ {FAILED, FAILURE, EXPIRED, CANCELLED, CANCELED, DECLINED}.
- else `PENDING`.

---

## 4. End-to-end payment flow

1. **Checkout (classic shortcode).** Customer picks "UPI / QR (WeeWoo Pay)" →
   Place Order → `WW_IMB_Gateway::process_payment($order_id)`:
   - Builds `customer_mobile` (last 10 digits of billing phone; filterable
     fallback `ww_imb_fallback_mobile` if missing).
   - Calls `create_order`. On failure → `wc_add_notice` + logs error + returns
     `['result'=>'failure']`.
   - Stores meta: `_ww_imb_order_id, _ww_imb_bhim, _ww_imb_paytm, _ww_imb_phonepe,
     _ww_imb_payurl, _ww_imb_check`. Sets status `pending`.
   - Returns `['result'=>'success','redirect'=> qr_page_url($order)]`.
2. **Branded QR page** = `home_url('/?ww_imb_pay={order_id}&key={order_key}')`.
   `WW_IMB_Endpoints::maybe_render_qr_page()` validates the order key (guest-safe),
   enqueues assets, renders `templates/qr-checkout.php`, and `exit`s.
   - `checkout.js` renders the QR from `bhim_link` (qrcodejs, error-correction H),
     runs a countdown, a "Download QR" (canvas→PNG), and **auto-polls** the status
     endpoint every 4s. No button needed.
3. **Verification** (any of three paths → all call `confirm_payment`):
   - **Poll:** `GET admin-ajax.php?action=ww_imb_status&order_id&key` →
     `ajax_status()` validates key → `confirm_payment()` → returns
     `{status, paid, redirect}`. On SUCCESS the page shows the success screen and
     redirects to the WooCommerce **order-received** (thank-you) page.
   - **Webhook:** `handle_webhook()` acks **HTTP 200 immediately**, then (after
     `fastcgi_finish_request`) calls `confirm_payment()`.
   - **Cron reconciler:** `ww_imb_reconcile` (every 5 min) →
     `reconcile_pending()` re-checks pending orders from the last 24h.
4. **`confirm_payment($order)`** (the single authoritative path):
   - Idempotent: returns early if already paid; takes a transient lock
     (`ww_imb_lock_{id}`, 20s) to prevent webhook+poll double-completion; re-reads
     the order fresh.
   - Calls `check_order_status`. If `SUCCESS`: **amount guard** (reject if IMB
     amount ≠ order total within ₹0.01 → logs `amount-mismatch`, stays pending),
     stores `_ww_imb_utr`, calls `$order->payment_complete($utr)` (→ Processing,
     stock reduced, emails sent), adds an order note. If `FAILED` → mark failed.

---

## 5. Security model (anti-tamper)  ← read this

**An order can be completed ONLY inside `confirm_payment()`, which requires a
real `SUCCESS` from a server-to-server call to IMB's `check-order-status`.**

- The browser success animation is **cosmetic**; it never changes order status.
- No endpoint accepts "mark this paid" from client input. Burp/inspect-element,
  request replay, or a **forged webhook** all just trigger a real re-check against
  IMB, which returns "not paid" for an unpaid order.
- Reaching the thank-you page does **not** complete the order; fulfilment is gated
  on order status, which is gated on IMB.
- **Amount guard** prevents completing if the paid amount ≠ order total.
- Status/QR endpoints are guarded by the per-order **order key** (same model as
  WooCommerce order-pay).

---

## 6. Settings, filters, data

### Settings (WooCommerce → Settings → Payments → "WeeWoo Pay — IMB UPI")
Stored in option `woocommerce_weewoo_imb_settings`:
`enabled, title, description, user_token, api_base, debug`.

### Filters (for theming / overrides)
| Filter | Default | Purpose |
|---|---|---|
| `ww_imb_create_order_url` | `{api_base}/v2/create-order` | Override create-order URL |
| `ww_imb_status_url` | `{api_base}/api/check-order-status` | Override status URL |
| `ww_imb_brand_name` | site title | Brand shown on QR page |
| `ww_imb_primary_color` | `#bee63c` | Lime accent |
| `ww_imb_qr_logo_html` | brand name span | Center QR badge HTML |
| `ww_imb_qr_expiry_seconds` | `600` | Countdown length |
| `ww_imb_fallback_mobile` | `''` | Mobile when billing phone missing |
| `ww_imb_icon` | upi.svg | Checkout method icon |

### Order meta keys
`_ww_imb_order_id` (IMB id), `_ww_imb_bhim`, `_ww_imb_paytm`, `_ww_imb_phonepe`,
`_ww_imb_payurl`, `_ww_imb_check`, `_ww_imb_utr`.

### Options / cron / transients
- Option `ww_imb_error_log` — last 50 errors (shown on dashboard).
- Cron `ww_imb_reconcile` on schedule `ww_imb_5min` (300s).
- Transient `ww_imb_lock_{order_id}` — per-order completion lock.

### Admin dashboard
Top-level menu **WeeWoo Pay** (`admin.php?page=weewoo-imb`): 30-day stats,
recent transactions, connection panel (copy webhook URL), **Test connection**
(AJAX `ww_imb_test`), error log (clear via `ww_imb_clear_errors`).

---

## 7. Deployment

### Option A — WP admin (manual)
Zip the `weewoo-imb-pay/` folder → Plugins → Add New → Upload Plugin → Activate.

### Option B — GitHub Actions → Hostinger (set up)
`.github/workflows/deploy-hostinger.yml` (manual `workflow_dispatch`). Requires
repo secrets: `HOSTINGER_SSH_HOST, HOSTINGER_SSH_USER, HOSTINGER_SSH_PORT,
HOSTINGER_SSH_KEY, HOSTINGER_PLUGIN_PATH`. rsyncs
`wordpress-plugin/weewoo-imb-pay/` → server plugins dir.

### Option C — SSH (for the SSH-enabled assistant)
Typical Hostinger paths/commands:
```bash
# plugin dir (confirm the uXXXX id in hPanel File Manager):
cd ~/domains/weewoo.in/public_html/wp-content/plugins/weewoo-imb-pay

# if WP-CLI is available:
wp plugin activate weewoo-imb-pay
wp plugin list | grep weewoo

# pull latest from git (if a clone/worktree is set up there), or rsync/scp the folder.
```

---

## 8. Debugging runbook (SSH)

```bash
WP=~/domains/weewoo.in/public_html       # adjust to real path

# 1. Plugin active?
wp --path=$WP plugin list | grep weewoo

# 2. Gateway settings (token set? api_base? enabled?)
wp --path=$WP option get woocommerce_weewoo_imb_settings --format=json

# 3. Turn on debug logging (or via the gateway settings UI), then watch logs:
ls -t $WP/wp-content/uploads/wc-logs/weewoo_imb-*.log | head
tail -f $WP/wp-content/uploads/wc-logs/weewoo_imb-*.log

# 4. Persisted error log (dashboard source):
wp --path=$WP option get ww_imb_error_log --format=json

# 5. Cron reconciler scheduled & running?
wp --path=$WP cron event list | grep ww_imb_reconcile
wp --path=$WP cron event run ww_imb_reconcile        # force a run

# 6. Inspect a specific order's IMB meta + status:
wp --path=$WP post meta list <ORDER_ID> | grep _ww_imb     # (or 'wp wc' / HPOS tools)

# 7. Live API smoke test from the server (PHP):
wp --path=$WP eval '
  $c = WW_IMB_Gateway::make_client();
  var_dump($c->check_order_status("PINGTEST123"));
'
```

### Common production issues & fixes
| Symptom | Likely cause | Fix |
|---|---|---|
| Method not at checkout | not enabled / **block** checkout w/o JS | enable in settings; block integration is in `class-ww-imb-blocks.php` — verify it registers |
| Order stuck **pending** after paying | status call failing / wrong endpoint | check WC logs `confirm …`; verify `check-order-status` URL + token; try `ww_imb_status_url` filter |
| create-order fails | bad/empty `user_token` or missing mobile | dashboard error log shows `create-order` / `missing-mobile`; make billing phone required |
| Webhook "failed delivery" in IMB | slow response / IP blocked | we already ack 200 fast; check firewall/ModSecurity for IMB IPs |
| Auto-verify never fires | **wp-cron disabled** and tab closed | ensure real cron or `DISABLE_WP_CRON` handled; poll still covers open tabs |
| ₹ shows as `&#8377;` | (fixed) double-escaped entity | ensure latest build |
| Amount mismatch note on order | IMB amount ≠ total | investigate; never auto-completes (by design) |

---

## 9. Open items / before-launch checklist

- [ ] **Live ₹1 test** end-to-end (needs real token + a real UPI payment). Paste
      the `check-order-status` JSON back to confirm field values match the
      normalizer 100%.
- [ ] Confirm checkout is **classic shortcode** (it is, per merchant). If any page
      uses the **Checkout block**, test that the method renders (blocks integration
      added but unverified live).
- [ ] Set the **Webhook URL** in the IMB dashboard:
      `https://weewoo.in/?wc-api=weewoo_imb_webhook`.
- [ ] Make **billing phone required** at checkout (IMB needs a 10-digit mobile).
- [ ] Ensure **WP cron** actually runs (or a real system cron is configured) for
      the reconciler safety net.
- [ ] Optional: surface Paytm/PhonePe intent buttons on the QR page (links are
      captured in meta but hidden per the approved design).
- [ ] Decide on `api_base` if the account needs a non-default host; both endpoint
      paths are filter-overridable.

---

## 10. Quick mental model for the next engineer

> `process_payment` creates the IMB order and parks the WC order as **pending**,
> then sends the buyer to our QR page. Everything after that converges on one
> function — **`WW_IMB_Gateway::confirm_payment()`** — which is the only place an
> order becomes paid, and only when IMB's server says SUCCESS with a matching
> amount. Poll, webhook, and cron are just three triggers for that one function.
> If something's wrong in production, it's almost always: (a) wrong/missing token,
> (b) the status endpoint not returning what the normalizer expects, or (c) cron
> not running. Start with the WC logs (`weewoo_imb-*.log`) and the dashboard error
> log.
