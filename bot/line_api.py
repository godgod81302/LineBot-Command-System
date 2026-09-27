"""LINE Messaging API 包裝：簽名驗證、回覆/推播、下載用戶媒體。"""
from __future__ import annotations

import base64
import hashlib
import hmac

import httpx

from . import config

LINE_API = "https://api.line.me/v2/bot"
LINE_DATA_API = "https://api-data.line.me/v2/bot"


def verify_signature(body: bytes, signature: str | None) -> bool:
    if not signature or not config.LINE_CHANNEL_SECRET:
        return False
    digest = hmac.new(config.LINE_CHANNEL_SECRET.encode(), body, hashlib.sha256).digest()
    return hmac.compare_digest(base64.b64encode(digest).decode(), signature)


def _headers() -> dict[str, str]:
    return {"Authorization": f"Bearer {config.LINE_CHANNEL_ACCESS_TOKEN}"}


async def push_text(client: httpx.AsyncClient, user_id: str, text: str) -> None:
    await client.post(
        f"{LINE_API}/message/push",
        headers=_headers(),
        json={"to": user_id, "messages": [{"type": "text", "text": text[:4900]}]},
        timeout=15,
    )


async def push_image(client: httpx.AsyncClient, user_id: str, image_url: str) -> None:
    await client.post(
        f"{LINE_API}/message/push",
        headers=_headers(),
        json={
            "to": user_id,
            "messages": [{
                "type": "image",
                "originalContentUrl": image_url,
                "previewImageUrl": image_url,
            }],
        },
        timeout=15,
    )


async def get_message_content(client: httpx.AsyncClient, message_id: str) -> bytes:
    resp = await client.get(
        f"{LINE_DATA_API}/message/{message_id}/content",
        headers=_headers(),
        timeout=60,
    )
    resp.raise_for_status()
    return resp.content


async def get_profile(client: httpx.AsyncClient, user_id: str) -> str | None:
    try:
        resp = await client.get(f"{LINE_API}/profile/{user_id}", headers=_headers(), timeout=10)
        resp.raise_for_status()
        return resp.json().get("displayName")
    except Exception:
        return None
