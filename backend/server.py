from fastapi import FastAPI, APIRouter, HTTPException, Request
from fastapi.responses import HTMLResponse
from dotenv import load_dotenv
from starlette.middleware.cors import CORSMiddleware
from motor.motor_asyncio import AsyncIOMotorClient
import os
import logging
from pathlib import Path
from pydantic import BaseModel, Field, ConfigDict
from typing import List, Optional, Any
import uuid
from datetime import datetime, timezone

from imb_gateway import (
    IMBGateway,
    IMBConfig,
    IMBConfigError,
    IMBError,
    generate_order_id,
    normalize_status,
    parse_webhook,
    make_qr_data_uri,
    STATUS_SUCCESS,
    STATUS_FAILED,
    STATUS_PENDING,
)


ROOT_DIR = Path(__file__).parent
load_dotenv(ROOT_DIR / '.env')

# MongoDB connection
mongo_url = os.environ['MONGO_URL']
client = AsyncIOMotorClient(mongo_url)
db = client[os.environ['DB_NAME']]

# Create the main app without a prefix
app = FastAPI()

# Create a router with the /api prefix
api_router = APIRouter(prefix="/api")


# Define Models
class StatusCheck(BaseModel):
    model_config = ConfigDict(extra="ignore")  # Ignore MongoDB's _id field
    
    id: str = Field(default_factory=lambda: str(uuid.uuid4()))
    client_name: str
    timestamp: datetime = Field(default_factory=lambda: datetime.now(timezone.utc))

class StatusCheckCreate(BaseModel):
    client_name: str

# Add your routes to the router instead of directly to app
@api_router.get("/")
async def root():
    return {"message": "Hello World"}

@api_router.post("/status", response_model=StatusCheck)
async def create_status_check(input: StatusCheckCreate):
    status_dict = input.model_dump()
    status_obj = StatusCheck(**status_dict)
    
    # Convert to dict and serialize datetime to ISO string for MongoDB
    doc = status_obj.model_dump()
    doc['timestamp'] = doc['timestamp'].isoformat()
    
    _ = await db.status_checks.insert_one(doc)
    return status_obj

@api_router.get("/status", response_model=List[StatusCheck])
async def get_status_checks():
    # Exclude MongoDB's _id field from the query results
    status_checks = await db.status_checks.find({}, {"_id": 0}).to_list(1000)
    
    # Convert ISO string timestamps back to datetime objects
    for check in status_checks:
        if isinstance(check['timestamp'], str):
            check['timestamp'] = datetime.fromisoformat(check['timestamp'])
    
    return status_checks

# ---------------------------------------------------------------------------
# IMB Payment Gateway
# ---------------------------------------------------------------------------

# Single reusable gateway instance, built lazily so the app still boots when
# IMB_USER_TOKEN is not yet configured (routes will 503 with a clear message).
_imb_gateway: Optional[IMBGateway] = None


def get_gateway() -> IMBGateway:
    global _imb_gateway
    if _imb_gateway is None:
        try:
            _imb_gateway = IMBGateway(IMBConfig.from_env())
        except IMBConfigError as exc:
            raise HTTPException(status_code=503, detail=str(exc)) from exc
    return _imb_gateway


class CreatePaymentRequest(BaseModel):
    amount: float = Field(..., gt=0, description="Amount in INR")
    customer_mobile: str = Field(..., min_length=10, max_length=10)
    customer_email: Optional[str] = Field(default="", description="Stored in IMB remark1")
    reference: Optional[str] = Field(default="", description="Internal ref, stored in remark2")


class PaymentRecord(BaseModel):
    model_config = ConfigDict(extra="ignore")

    order_id: str
    imb_order_id: Optional[str] = None
    amount: float
    customer_mobile: str
    customer_email: str = ""
    reference: str = ""
    status: str = STATUS_PENDING
    payment_url: Optional[str] = None
    bhim_link: Optional[str] = None
    paytm_link: Optional[str] = None
    check_link: Optional[str] = None
    utr: str = ""
    created_at: str = Field(default_factory=lambda: datetime.now(timezone.utc).isoformat())
    updated_at: str = Field(default_factory=lambda: datetime.now(timezone.utc).isoformat())


async def _save_payment(doc: dict[str, Any]) -> None:
    doc["updated_at"] = datetime.now(timezone.utc).isoformat()
    await db.payments.update_one(
        {"order_id": doc["order_id"]}, {"$set": doc}, upsert=True
    )


async def _get_payment(order_id: str) -> Optional[dict[str, Any]]:
    return await db.payments.find_one({"order_id": order_id}, {"_id": 0})


async def _refresh_status(gateway: IMBGateway, payment: dict[str, Any]) -> dict[str, Any]:
    """Poll IMB, update the stored record, and return the (possibly) updated record."""
    if payment["status"] in (STATUS_SUCCESS, STATUS_FAILED):
        return payment  # terminal — no need to re-query
    try:
        body = await gateway.check_order_status(payment["order_id"])
    except IMBError as exc:
        logger.warning("IMB status check failed for %s: %s", payment["order_id"], exc)
        return payment

    new_status = normalize_status(body)
    result = body.get("result") if isinstance(body.get("result"), dict) else {}
    payment["status"] = new_status
    if result.get("utr"):
        payment["utr"] = result["utr"]
    await _save_payment(payment)
    return payment


@api_router.post("/payments/create-order")
async def create_payment(req: CreatePaymentRequest):
    gateway = get_gateway()
    order_id = generate_order_id()
    try:
        body = await gateway.create_order(
            amount=f"{req.amount:g}",
            customer_mobile=req.customer_mobile,
            order_id=order_id,
            remark1=req.customer_email or "",
            remark2=req.reference or "",
        )
    except IMBError as exc:
        raise HTTPException(status_code=502, detail=f"IMB error: {exc}") from exc

    result = body["result"]
    record = PaymentRecord(
        order_id=order_id,
        imb_order_id=str(result.get("orderId") or ""),
        amount=req.amount,
        customer_mobile=req.customer_mobile,
        customer_email=req.customer_email or "",
        reference=req.reference or "",
        payment_url=result.get("payment_url"),
        bhim_link=result.get("bhim_link"),
        paytm_link=result.get("paytm_link"),
        check_link=result.get("check_link"),
    )
    doc = record.model_dump()
    await _save_payment(doc)

    # Render IMB's UPI deep link as our own QR. Fall back to payment_url if absent.
    qr_source = record.bhim_link or record.payment_url or ""
    qr_data_uri = make_qr_data_uri(qr_source) if qr_source else None

    return {
        "order_id": order_id,
        "status": record.status,
        "amount": record.amount,
        "qr": qr_data_uri,
        "bhim_link": record.bhim_link,
        "paytm_link": record.paytm_link,
        "payment_url": record.payment_url,
        "check_link": record.check_link,
        "checkout_url": f"/api/payments/{order_id}/checkout",
    }


@api_router.get("/payments/{order_id}/status")
async def payment_status(order_id: str):
    payment = await _get_payment(order_id)
    if not payment:
        raise HTTPException(status_code=404, detail="Unknown order_id")
    payment = await _refresh_status(get_gateway(), payment)
    return {
        "order_id": order_id,
        "status": payment["status"],
        "amount": payment["amount"],
        "utr": payment.get("utr", ""),
        "paid": payment["status"] == STATUS_SUCCESS,
    }


@api_router.post("/payments/webhook")
async def payment_webhook(request: Request):
    """IMB realtime callback receiver.

    Per IMB docs the payload is POSTed as form-encoded fields:
      status, order_id, message, result (a JSON string -> {txnStatus, amount, utr, ...})

    IMB's rule: only treat as paid when status == SUCCESS AND txnStatus == COMPLETED.
    We additionally:
      * stay idempotent — already-terminal orders are acknowledged without re-processing;
      * re-verify the amount against the order we created;
      * confirm with check-order-status (authoritative) before persisting SUCCESS,
        so a spoofed webhook can never mark an order paid.
    Always returns HTTP 200 quickly so IMB does not retry needlessly.
    """
    # Accept either JSON or form-encoded bodies.
    try:
        data = await request.json()
    except Exception:
        data = dict(await request.form())
    if not isinstance(data, dict):
        return {"ok": False, "reason": "unparseable body"}

    event = parse_webhook(data)
    order_id = event["order_id"]
    if not order_id:
        return {"ok": False, "reason": "no order_id in payload"}

    payment = await _get_payment(order_id)
    if not payment:
        return {"ok": False, "reason": "unknown order_id"}

    # Idempotency: never re-process an order already in a terminal state.
    if payment["status"] in (STATUS_SUCCESS, STATUS_FAILED):
        return {"ok": True, "order_id": order_id, "status": payment["status"], "duplicate": True}

    webhook_says_paid = event["paid"]

    # Amount guard: reject mismatched amounts before trusting the event.
    if event["amount"] is not None and event["amount"] != float(payment["amount"]):
        logger.warning(
            "Webhook amount mismatch for %s: webhook=%s stored=%s",
            order_id, event["amount"], payment["amount"],
        )
        webhook_says_paid = False

    # Authoritative confirmation: re-verify via check-order-status before persisting.
    payment = await _refresh_status(get_gateway(), payment)

    return {
        "ok": True,
        "order_id": order_id,
        "status": payment["status"],
        "webhook_paid": webhook_says_paid,
    }


@api_router.get("/payments/{order_id}/checkout", response_class=HTMLResponse)
async def checkout_page(order_id: str):
    payment = await _get_payment(order_id)
    if not payment:
        raise HTTPException(status_code=404, detail="Unknown order_id")
    qr_source = payment.get("bhim_link") or payment.get("payment_url") or ""
    qr = make_qr_data_uri(qr_source) if qr_source else ""
    return HTMLResponse(_render_checkout_html(payment, qr))


def _render_checkout_html(payment: dict[str, Any], qr_data_uri: str) -> str:
    amount = payment["amount"]
    oid = payment["order_id"]
    bhim = payment.get("bhim_link") or ""
    paytm = payment.get("paytm_link") or ""
    return f"""<!doctype html>
<html lang="en"><head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1"/>
<title>WeeWoo Pay · ₹{amount:g}</title>
<style>
  :root {{ color-scheme: dark; }}
  * {{ box-sizing: border-box; }}
  body {{ margin:0; min-height:100vh; display:grid; place-items:center;
    font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;
    background:radial-gradient(1200px 600px at 50% -10%,#1e293b,#0f172a); color:#e2e8f0; }}
  .card {{ width:min(380px,92vw); background:rgba(255,255,255,.04);
    border:1px solid rgba(255,255,255,.08); border-radius:20px; padding:28px;
    box-shadow:0 30px 80px rgba(0,0,0,.45); backdrop-filter:blur(12px); text-align:center; }}
  h1 {{ font-size:18px; margin:0 0 4px; letter-spacing:.3px; }}
  .amt {{ font-size:34px; font-weight:700; margin:6px 0 18px; }}
  .qr {{ background:#fff; border-radius:16px; padding:14px; display:inline-block; }}
  .qr img {{ display:block; width:220px; height:220px; }}
  .hint {{ color:#94a3b8; font-size:13px; margin:16px 0 18px; }}
  .btn {{ display:block; text-decoration:none; padding:13px; border-radius:12px;
    font-weight:600; margin-top:10px; }}
  .btn.bhim {{ background:#22c55e; color:#04210f; }}
  .btn.paytm {{ background:#1e88e5; color:#fff; }}
  .status {{ margin-top:18px; font-size:14px; min-height:20px; }}
  .status.ok {{ color:#22c55e; font-weight:700; }}
  .status.fail {{ color:#ef4444; font-weight:700; }}
  .spin {{ display:inline-block; width:14px; height:14px; border:2px solid #475569;
    border-top-color:#e2e8f0; border-radius:50%; animation:s 1s linear infinite;
    vertical-align:-2px; margin-right:6px; }}
  @keyframes s {{ to {{ transform:rotate(360deg); }} }}
  .oid {{ color:#64748b; font-size:11px; margin-top:14px; word-break:break-all; }}
</style></head>
<body>
  <div class="card">
    <h1>WeeWoo Pay</h1>
    <div class="amt">₹{amount:g}</div>
    <div class="qr"><img alt="Scan to pay" src="{qr_data_uri}"/></div>
    <div class="hint">Scan with any UPI app, or tap below on mobile</div>
    {f'<a class="btn bhim" href="{bhim}">Pay with UPI app</a>' if bhim else ''}
    {f'<a class="btn paytm" href="{paytm}">Pay with Paytm</a>' if paytm else ''}
    <div id="status" class="status"><span class="spin"></span>Waiting for payment…</div>
    <div class="oid">Order {oid}</div>
  </div>
<script>
  const orderId = {oid!r};
  const el = document.getElementById('status');
  let tries = 0;
  async function poll() {{
    tries++;
    try {{
      const r = await fetch('/api/payments/' + orderId + '/status');
      const d = await r.json();
      if (d.status === '{STATUS_SUCCESS}') {{
        el.className = 'status ok'; el.textContent = '✓ Payment received';
        return;
      }}
      if (d.status === '{STATUS_FAILED}') {{
        el.className = 'status fail'; el.textContent = '✗ Payment failed or expired';
        return;
      }}
    }} catch (e) {{ /* keep polling */ }}
    if (tries < 150) setTimeout(poll, 4000);
    else {{ el.textContent = 'Stopped checking. Refresh to resume.'; }}
  }}
  setTimeout(poll, 4000);
</script>
</body></html>"""


# Include the router in the main app
app.include_router(api_router)

app.add_middleware(
    CORSMiddleware,
    allow_credentials=True,
    allow_origins=os.environ.get('CORS_ORIGINS', '*').split(','),
    allow_methods=["*"],
    allow_headers=["*"],
)

# Configure logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s'
)
logger = logging.getLogger(__name__)

@app.on_event("shutdown")
async def shutdown_db_client():
    client.close()