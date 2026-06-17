"""IMB Payment Gateway client.

Thin async wrapper around the two IMB endpoints we depend on:

    POST {base}/v2/create-order         (form-encoded)
    POST {base}/api/check-order-status  (json)

Plus helpers to normalize IMB's status fields and to render the UPI deep link
(``bhim_link``) into a QR-code data URI for our own branded checkout page.

The merchant ``user_token`` is read from the environment only — never hardcoded.
See ``docs/imb/API.md`` for the full contract.
"""

from __future__ import annotations

import base64
import io
import os
import secrets
import time
from dataclasses import dataclass
from typing import Any, Optional

import httpx
import qrcode

# Internal, normalized payment states.
STATUS_PENDING = "PENDING"
STATUS_SUCCESS = "SUCCESS"
STATUS_FAILED = "FAILED"

_SUCCESS_TOKENS = {"COMPLETED", "SUCCESS", "PAID"}
_FAILED_TOKENS = {"FAILED", "FAILURE", "EXPIRED", "CANCELLED", "CANCELED", "DECLINED"}


class IMBConfigError(RuntimeError):
    """Raised when required IMB configuration is missing."""


class IMBError(RuntimeError):
    """Raised when IMB returns an error or an unparseable response."""


@dataclass(frozen=True)
class IMBConfig:
    user_token: str
    api_base: str = "https://api.imbpay.in"
    redirect_url: str = "https://api.imbpay.in"
    timeout: float = 20.0

    @classmethod
    def from_env(cls) -> "IMBConfig":
        token = os.environ.get("IMB_USER_TOKEN", "").strip()
        if not token:
            raise IMBConfigError(
                "IMB_USER_TOKEN is not set. Add it to backend/.env "
                "(see backend/.env.example)."
            )
        base = os.environ.get("IMB_API_BASE", "https://api.imbpay.in").strip().rstrip("/")
        redirect = os.environ.get("IMB_REDIRECT_URL", base).strip()
        timeout = float(os.environ.get("IMB_HTTP_TIMEOUT", "20"))
        return cls(user_token=token, api_base=base, redirect_url=redirect, timeout=timeout)


def generate_order_id() -> str:
    """Generate a unique, numeric-ish order id.

    IMB order ids in the docs are long numeric strings and must be unique per
    order. We combine millisecond epoch with random digits to avoid collisions.
    """
    ms = int(time.time() * 1000)
    rand = secrets.randbelow(10_000)
    return f"{ms}{rand:04d}"


def normalize_status(payload: dict[str, Any]) -> str:
    """Map IMB's various status fields to one of our internal states."""
    candidates: list[str] = []
    top = payload.get("status")
    if isinstance(top, str):
        candidates.append(top)
    result = payload.get("result")
    if isinstance(result, dict):
        for key in ("status", "txnStatus"):
            val = result.get(key)
            if isinstance(val, str):
                candidates.append(val)

    upper = {c.strip().upper() for c in candidates if c}
    if upper & _SUCCESS_TOKENS:
        return STATUS_SUCCESS
    if upper & _FAILED_TOKENS:
        return STATUS_FAILED
    return STATUS_PENDING


def parse_webhook(payload: dict[str, Any]) -> dict[str, Any]:
    """Normalize an IMB webhook body into a predictable dict.

    Handles both JSON objects and form-encoded bodies where ``result`` is a
    JSON-encoded string. Returns ``order_id``, the decoded ``result`` dict, the
    reported ``amount``/``utr``, and ``paid`` — which is True only when IMB's
    rule holds: top-level ``status == SUCCESS`` AND ``result.txnStatus == COMPLETED``.
    Callers should still re-verify via :meth:`IMBGateway.check_order_status`.
    """
    import json

    result = payload.get("result")
    if isinstance(result, str):
        try:
            result = json.loads(result)
        except (ValueError, TypeError):
            result = {}
    if not isinstance(result, dict):
        result = {}

    order_id = str(
        payload.get("order_id")
        or payload.get("orderId")
        or result.get("orderId")
        or result.get("order_id")
        or ""
    )

    paid = (
        str(payload.get("status", "")).strip().upper() == "SUCCESS"
        and str(result.get("txnStatus", "")).strip().upper() == "COMPLETED"
    )

    amount: Optional[float] = None
    if result.get("amount") is not None:
        try:
            amount = float(result["amount"])
        except (TypeError, ValueError):
            amount = None

    return {
        "order_id": order_id,
        "paid": paid,
        "amount": amount,
        "utr": str(result.get("utr") or ""),
        "result": result,
    }


def make_qr_data_uri(upi_string: str, box_size: int = 10, border: int = 2) -> str:
    """Render a UPI deep link (or any string) into a base64 PNG data URI."""
    qr = qrcode.QRCode(
        error_correction=qrcode.constants.ERROR_CORRECT_M,
        box_size=box_size,
        border=border,
    )
    qr.add_data(upi_string)
    qr.make(fit=True)
    img = qr.make_image(fill_color="black", back_color="white")
    buf = io.BytesIO()
    img.save(buf, format="PNG")
    encoded = base64.b64encode(buf.getvalue()).decode("ascii")
    return f"data:image/png;base64,{encoded}"


class IMBGateway:
    """Async IMB API client. One instance can be reused across requests."""

    def __init__(self, config: Optional[IMBConfig] = None) -> None:
        self.config = config or IMBConfig.from_env()

    async def create_order(
        self,
        *,
        amount: str | float | int,
        customer_mobile: str,
        order_id: str,
        redirect_url: Optional[str] = None,
        remark1: str = "",
        remark2: str = "",
    ) -> dict[str, Any]:
        """Create an IMB collection order. Returns the parsed JSON ``result``-rich body."""
        form = {
            "customer_mobile": str(customer_mobile),
            "user_token": self.config.user_token,
            "amount": str(amount),
            "order_id": str(order_id),
            "redirect_url": redirect_url or self.config.redirect_url,
            "remark1": remark1,
            "remark2": remark2,
        }
        url = f"{self.config.api_base}/v2/create-order"
        async with httpx.AsyncClient(timeout=self.config.timeout) as client:
            resp = await client.post(url, data=form)
        body = self._parse(resp)
        if not _truthy(body.get("status")):
            raise IMBError(body.get("message") or "IMB create-order failed")
        if not isinstance(body.get("result"), dict):
            raise IMBError("IMB create-order response missing 'result'")
        return body

    async def check_order_status(self, order_id: str) -> dict[str, Any]:
        """Query the status of an order. Returns the parsed JSON body."""
        payload = {"user_token": self.config.user_token, "order_id": str(order_id)}
        url = f"{self.config.api_base}/api/check-order-status"
        async with httpx.AsyncClient(timeout=self.config.timeout) as client:
            resp = await client.post(url, json=payload)
        return self._parse(resp)

    @staticmethod
    def _parse(resp: httpx.Response) -> dict[str, Any]:
        try:
            data = resp.json()
        except ValueError as exc:  # non-JSON body
            raise IMBError(
                f"IMB returned non-JSON (HTTP {resp.status_code}): {resp.text[:200]}"
            ) from exc
        if not isinstance(data, dict):
            raise IMBError("IMB returned a non-object JSON body")
        return data


def _truthy(value: Any) -> bool:
    """IMB sometimes returns booleans, sometimes 'true'/'COMPLETED' strings."""
    if isinstance(value, bool):
        return value
    if isinstance(value, str):
        return value.strip().lower() in {"true", "1", "success", "completed", "ok"}
    return bool(value)
