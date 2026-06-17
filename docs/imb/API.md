# IMB Payment Gateway — API Reference

This is the verbatim contract our custom gateway integrates against, captured from
the IMB Payment dashboard / Postman collection. The public docs SPA blocks automated
fetching, so this file is the source of truth checked into the repo.

> **Credentials never live in this file or any committed file.** The `user_token`
> is read from `backend/.env` (`IMB_USER_TOKEN`). The sample token shown in the
> original docs is illustrative only.

## Hosts & endpoints

| Endpoint | URL |
| --- | --- |
| Create order (custom checkout) | `POST https://api.imbpay.in/v2/create-order` |
| Check order status | `POST https://api.imbpay.in/api/check-order-status` |

`https://pay.imb.org.in` and `https://secure-stage.imb.org.in` are legacy/deprecated.
In the WooCommerce plugin the host is set in the gateway settings (default
`https://api.imbpay.in`); the `/v2/create-order` and `/api/check-order-status`
paths are derived from it and can be overridden via the `ww_imb_create_order_url`
/ `ww_imb_status_url` filters.

> The custom-checkout **`/v2/create-order`** endpoint returns the extra
> `phonepe_link` field; the older `/api/create-order` does not.

---

## 1. Create Order

Creates a collection order and returns the payment links + UPI deep links.

```
POST https://api.imbpay.in/v2/create-order
Content-Type: application/x-www-form-urlencoded
```

### Request (form-data)

| Field | Required | Example | Notes |
| --- | --- | --- | --- |
| `customer_mobile` | yes | `9876543210` | 10-digit Indian mobile. |
| `user_token` | yes | `<from .env>` | Merchant API token. |
| `amount` | yes | `10` | In INR (rupees). |
| `order_id` | yes | `9999999911111111` | **Unique** per order. Reuse → duplicate error. |
| `redirect_url` | yes | `https://pay.imb.org.in` | Where IMB sends the customer after payment. |
| `remark1` | no | `your-customer@gmail.com` | Free text — we use it for the customer email. |
| `remark2` | no | `any data` | Free text — we use it for an internal reference. |

### Response

```json
{
  "status": true,
  "message": "Order Created Successfully",
  "result": {
    "orderId": "999999991111166",
    "payment_url": "https://ekqr.live/d57e63cdf7a39afe018da74207f6ed7c...",
    "paytm_link": "paytmmp://...&am=10.00&...",
    "phonepe_link": "phonepe://upi/payment-link",
    "bhim_link": "upi://pay?pa=yespay.qtosno9krs6nrb@yesbankltd&am=10.00&pn=Imb%20Payment%20Collection&tn=UPIx5lns1765635326&tr=UPIx5lns1765635326",
    "check_link": "https://check.imb.org.in/quintustech_status/UPIx5lns1765635326"
  }
}
```

> Per IMB: do **not** redirect to `payment_url` for a custom checkout — build the
> UI from these values. Failed response: `{ "status": "false", "message": "Order_id Already Exist" }`.

- `payment_url` — IMB-hosted payment page (we don't use it for the custom flow).
- `bhim_link` — canonical `upi://pay?...` string. **We render this as the QR code**
  on our own branded checkout page (this is "the QR code of IMB" on our design).
- `paytm_link` / `phonepe_link` — app-intent deep links for direct Paytm/PhonePe buttons.
- `check_link` — per-order status URL (can be polled directly as an alternative
  to `check-order-status`). We use `check-order-status` because it also returns
  the amount + UTR needed for the amount guard and receipt.
- `check_link` — browser status page (human-readable).

On failure `status` is `false` and `message` describes the error
(e.g. duplicate `order_id`).

---

## 2. Check Order Status (instant verification)

Polled until terminal. This is how we confirm payment without trusting only a webhook.

```
POST {IMB_API_BASE}/api/check-order-status
Content-Type: application/json
```

### Request

```json
{
  "user_token": "<from .env>",
  "order_id": "9999999911111111"
}
```

### Response

```json
{
  "status": "COMPLETED",
  "message": "Transaction Successfully",
  "result": {
    "txnStatus": "COMPLETED",
    "resultInfo": "Transaction Success",
    "orderId": "9999999911111111",
    "status": "SUCCESS",
    "amount": 10,
    "date": "2024-12-22 20:37:11",
    "utr": "",
    "customer_mobile": "9876543210",
    "remark1": "your-customer@gmail.com",
    "remark2": "any data"
  }
}
```

### Status normalization

Our gateway maps IMB's several status fields to three internal states:

| Internal | Matches (case-insensitive) |
| --- | --- |
| `SUCCESS` | top-level `status` in {`COMPLETED`,`SUCCESS`} **or** `result.status == SUCCESS` **or** `result.txnStatus == COMPLETED` |
| `FAILED` | any of those fields in {`FAILED`,`FAILURE`,`EXPIRED`,`CANCELLED`,`DECLINED`} |
| `PENDING` | anything else (created / awaiting payment) |

---

## 3. Webhook / Callback (realtime push verification)

IMB POSTs realtime transaction updates to the webhook URL you register on the
dashboard (API Credentials → **Update Webhook URL**).

```
POST {your-webhook-url}
Content-Type: application/x-www-form-urlencoded
```

The body is form-encoded; `result` arrives as a **JSON string** (decode it).

### Sample payload

```json
{
  "status": "SUCCESS",
  "order_id": "TXN00743264723",
  "message": "Transaction Successfully",
  "result": {
    "txnStatus": "COMPLETED",
    "resultInfo": "Transaction Success",
    "orderId": "TXN00743264723",
    "amount": 100,
    "date": "2021-01-01 12:00:00",
    "utr": 435644746487,
    "customer_mobile": 9876543210,
    "remark1": "your-customer@gmail.com",
    "remark2": "Your Data"
  }
}
```

| Field | Description |
| --- | --- |
| `status` | Overall webhook status. |
| `order_id` | Unique order / transaction id. |
| `result.txnStatus` | Actual txn status (`COMPLETED` / `PENDING` / ...). |
| `result.amount` | Amount paid. |
| `result.utr` | UPI transaction reference number. |
| `result.customer_mobile` | Customer mobile. |
| `result.remark1` / `remark2` | The custom values we sent on create-order. |

### IMB's mandatory verification rules

- Only treat as paid when **`status == SUCCESS` AND `result.txnStatus == COMPLETED`**.
- **Idempotency** — never credit / process the same `orderId` twice. Check your DB first.
- Respond quickly with **HTTP 200**.
- Verify the **amount** matches before activating any service.
- Use HTTPS and keep webhook logs.

### Our receiver

`POST /api/payments/webhook` implements all of the above and goes one step
further: even on a well-formed `SUCCESS`/`COMPLETED` event it **re-confirms via
Check Order Status** before persisting `SUCCESS`, so a spoofed webhook can never
mark an order paid. Already-terminal orders are acknowledged as duplicates
without re-processing. Status polling and the webhook share this single
authoritative path.
