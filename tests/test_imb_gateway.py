"""Unit tests for the IMB gateway helpers and client.

Network is mocked — no real IMB calls are made.
"""

import sys
from pathlib import Path

import pytest

sys.path.insert(0, str(Path(__file__).resolve().parents[1] / "backend"))

import imb_gateway as g  # noqa: E402


# --- normalize_status -------------------------------------------------------

def test_normalize_success_top_level():
    assert g.normalize_status({"status": "COMPLETED"}) == g.STATUS_SUCCESS


def test_normalize_success_nested_result():
    body = {"status": "COMPLETED", "result": {"status": "SUCCESS", "txnStatus": "COMPLETED"}}
    assert g.normalize_status(body) == g.STATUS_SUCCESS


def test_normalize_failed():
    assert g.normalize_status({"result": {"status": "FAILED"}}) == g.STATUS_FAILED
    assert g.normalize_status({"status": "EXPIRED"}) == g.STATUS_FAILED


def test_normalize_pending_default():
    assert g.normalize_status({"status": "PENDING"}) == g.STATUS_PENDING
    assert g.normalize_status({}) == g.STATUS_PENDING


# --- order id + qr ----------------------------------------------------------

def test_generate_order_id_unique_and_numeric():
    a, b = g.generate_order_id(), g.generate_order_id()
    assert a != b
    assert a.isdigit() and b.isdigit()


def test_make_qr_data_uri():
    uri = g.make_qr_data_uri("upi://pay?pa=test@bank&am=10.00")
    assert uri.startswith("data:image/png;base64,")
    assert len(uri) > 100


# --- parse_webhook ----------------------------------------------------------

def test_parse_webhook_json_object():
    payload = {
        "status": "SUCCESS",
        "order_id": "TXN00743264723",
        "result": {"txnStatus": "COMPLETED", "amount": 100, "utr": 435644746487},
    }
    ev = g.parse_webhook(payload)
    assert ev["order_id"] == "TXN00743264723"
    assert ev["paid"] is True
    assert ev["amount"] == 100.0
    assert ev["utr"] == "435644746487"


def test_parse_webhook_form_encoded_result_string():
    # As IMB sends it: result is a JSON string in a form-encoded body.
    payload = {
        "status": "SUCCESS",
        "order_id": "TXN1",
        "result": '{"txnStatus": "COMPLETED", "amount": 50}',
    }
    ev = g.parse_webhook(payload)
    assert ev["order_id"] == "TXN1"
    assert ev["paid"] is True
    assert ev["amount"] == 50.0


def test_parse_webhook_not_paid_when_pending():
    ev = g.parse_webhook({"status": "SUCCESS", "order_id": "X", "result": {"txnStatus": "PENDING"}})
    assert ev["paid"] is False


def test_parse_webhook_not_paid_when_status_not_success():
    ev = g.parse_webhook({"status": "FAILED", "order_id": "X", "result": {"txnStatus": "COMPLETED"}})
    assert ev["paid"] is False


def test_parse_webhook_handles_garbage_result():
    ev = g.parse_webhook({"status": "SUCCESS", "order_id": "X", "result": "not-json"})
    assert ev["order_id"] == "X"
    assert ev["paid"] is False
    assert ev["amount"] is None


# --- config -----------------------------------------------------------------

def test_config_requires_token(monkeypatch):
    monkeypatch.delenv("IMB_USER_TOKEN", raising=False)
    with pytest.raises(g.IMBConfigError):
        g.IMBConfig.from_env()


def test_config_from_env(monkeypatch):
    monkeypatch.setenv("IMB_USER_TOKEN", "tok123")
    monkeypatch.setenv("IMB_API_BASE", "https://api.imbpay.in/v2/")
    cfg = g.IMBConfig.from_env()
    assert cfg.user_token == "tok123"
    assert cfg.api_base == "https://api.imbpay.in/v2"  # trailing slash stripped


# --- client (mocked transport) ----------------------------------------------

@pytest.mark.asyncio
async def test_create_order_success(monkeypatch):
    import httpx

    def handler(request: httpx.Request) -> httpx.Response:
        assert request.url.path == "/api/create-order"
        body = request.content.decode()
        assert "user_token=tok" in body or "user_token" in body
        return httpx.Response(
            200,
            json={
                "status": True,
                "message": "Order Created Successfully",
                "result": {
                    "orderId": "123",
                    "payment_url": "https://ekqr.live/abc",
                    "bhim_link": "upi://pay?pa=x@y&am=10.00",
                    "paytm_link": "paytmmp://x",
                    "check_link": "https://check.imb.org.in/x",
                },
            },
        )

    transport = httpx.MockTransport(handler)
    _patch_async_client(monkeypatch, transport)

    cfg = g.IMBConfig(user_token="tok", api_base="https://pay.imb.org.in")
    gw = g.IMBGateway(cfg)
    body = await gw.create_order(
        amount="10", customer_mobile="9876543210", order_id="123"
    )
    assert body["result"]["bhim_link"].startswith("upi://")


@pytest.mark.asyncio
async def test_create_order_failure_raises(monkeypatch):
    import httpx

    def handler(request: httpx.Request) -> httpx.Response:
        return httpx.Response(200, json={"status": False, "message": "Duplicate order id"})

    _patch_async_client(monkeypatch, httpx.MockTransport(handler))
    gw = g.IMBGateway(g.IMBConfig(user_token="tok"))
    with pytest.raises(g.IMBError, match="Duplicate"):
        await gw.create_order(amount="10", customer_mobile="9876543210", order_id="1")


@pytest.mark.asyncio
async def test_check_order_status(monkeypatch):
    import httpx

    def handler(request: httpx.Request) -> httpx.Response:
        assert request.url.path == "/api/check-order-status"
        return httpx.Response(
            200,
            json={
                "status": "COMPLETED",
                "result": {"status": "SUCCESS", "txnStatus": "COMPLETED", "utr": "ABC123"},
            },
        )

    _patch_async_client(monkeypatch, httpx.MockTransport(handler))
    gw = g.IMBGateway(g.IMBConfig(user_token="tok"))
    body = await gw.check_order_status("123")
    assert g.normalize_status(body) == g.STATUS_SUCCESS
    assert body["result"]["utr"] == "ABC123"


def _patch_async_client(monkeypatch, transport):
    """Force httpx.AsyncClient to use the mock transport."""
    import httpx

    orig_init = httpx.AsyncClient.__init__

    def patched_init(self, *args, **kwargs):
        kwargs["transport"] = transport
        orig_init(self, *args, **kwargs)

    monkeypatch.setattr(httpx.AsyncClient, "__init__", patched_init)
