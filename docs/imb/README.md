# IMB Payment Gateway integration

A custom, self-branded payment gateway powered by IMB under the hood. It creates
IMB collection orders, renders **IMB's UPI QR on our own checkout page**, and
confirms payment via **instant verification** (status polling + an optional
webhook re-check).

- API contract we integrate against: [`API.md`](./API.md)
- Client + helpers: [`backend/imb_gateway.py`](../../backend/imb_gateway.py)
- Routes: [`backend/server.py`](../../backend/server.py)
- Config template: [`backend/.env.example`](../../backend/.env.example)
- Tests: [`tests/test_imb_gateway.py`](../../tests/test_imb_gateway.py)

## Setup

1. `cp backend/.env.example backend/.env` and set `IMB_USER_TOKEN` (from IMB
   dashboard → API Credentials). `.env` is gitignored — never commit it.
2. `pip install -r backend/requirements.txt`
3. Run the API: `uvicorn server:app --reload` (from `backend/`).

## Flow

```
POST /api/payments/create-order
  { "amount": 10, "customer_mobile": "9876543210",
    "customer_email": "buyer@example.com", "reference": "INV-1" }
  → { order_id, qr (data-uri PNG of IMB bhim_link), bhim_link, paytm_link,
      payment_url, check_link, checkout_url }

GET  /api/payments/{order_id}/checkout   → branded HTML page (QR + auto-poll)
GET  /api/payments/{order_id}/status     → { status: PENDING|SUCCESS|FAILED, paid, utr }
POST /api/payments/webhook               → IMB callback receiver (re-verifies via status)
```

The customer scans the QR (or taps the UPI / Paytm app button). The checkout page
polls `/status` every 4s; on `SUCCESS` it shows "Payment received". Verification
always confirms with IMB's `check-order-status` — webhook content alone never
marks an order paid.

## Switching IMB hosts

Set `IMB_API_BASE` in `.env` (default `https://pay.imb.org.in`; alternates
`https://api.imbpay.in/v2`, `https://api.imbx.in`). No code changes needed.
