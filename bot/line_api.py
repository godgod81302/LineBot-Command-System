"""LINE Messaging API 包裝：簽名驗證、回覆/推播、讀取動畫、下載用戶媒體。

訊息發送原則：優先 reply（免費、不計額度），reply 不可用或失敗才 fallback push。
一個 call 最多 5 個訊息物件，對同一對象只算 1 則，所以盡量打包成一批。
"""
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


# ---------------------------------------------------------------- 訊息物件

def text_msg(text: str) -> dict:
    return {"type": "text", "text": text[:4900]}


def image_msg(url: str) -> dict:
    return {"type": "image", "originalContentUrl": url, "previewImageUrl": url}


# ---------------------------------------------------------------- 發送

async def reply_messages(client: httpx.AsyncClient, reply_token: str | None, messages: list[dict]) -> bool:
    """reply 免費；token 缺失/過期/已用過會失敗，回 False 讓呼叫方決定 fallback。"""
    if not reply_token:
        return False
    try:
        resp = await client.post(
            f"{LINE_API}/message/reply",
            headers=_headers(),
            json={"replyToken": reply_token, "messages": messages[:5]},
            timeout=15,
        )
        return resp.status_code == 200
    except Exception:
        return False


async def push_messages(client: httpx.AsyncClient, target: str, messages: list[dict]) -> None:
    await client.post(
        f"{LINE_API}/message/push",
        headers=_headers(),
        json={"to": target, "messages": messages[:5]},
        timeout=15,
    )


async def send_messages(client: httpx.AsyncClient, target: str, reply_token: str | None,
                        messages: list[dict]) -> None:
    """reply 優先（免費）；失敗才 push（計額度）。"""
    if await reply_messages(client, reply_token, messages):
        return
    await push_messages(client, target, messages)


async def send_text(client: httpx.AsyncClient, target: str, reply_token: str | None, text: str) -> None:
    await send_messages(client, target, reply_token, [text_msg(text)])


async def start_loading(client: httpx.AsyncClient, chat_id: str, seconds: int = 60) -> None:
    """一對一聊天顯示「輸入中…」動畫（免費），生圖等待期間的回饋。僅支援一對一。"""
    try:
        await client.post(
            f"{LINE_API}/chat/loading/start",
            headers={**_headers(), "Content-Type": "application/json"},
            json={"chatId": chat_id, "loadingSeconds": max(5, min(60, seconds))},
            timeout=10,
        )
    except Exception:
        pass  # 動畫失敗不影響主流程


# ---------------------------------------------------------------- 讀取

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
