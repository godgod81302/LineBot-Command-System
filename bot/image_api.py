"""chatgpt2api（OpenAI 相容圖片 API）客戶端：文生圖、圖片編輯。

回傳皆為圖片 bytes 列表；上層負責存檔與託管。
"""
from __future__ import annotations

import base64

import httpx

from . import config


def _headers() -> dict[str, str]:
    return {"Authorization": f"Bearer {config.CHATGPT2API_AUTH_KEY}"}


class ImageGenError(RuntimeError):
    """上游生圖失敗（已分類）。user_message 可直接給使用者看；detail 供記錄。"""

    def __init__(self, user_message: str, detail: str = "") -> None:
        super().__init__(detail or user_message)
        self.user_message = user_message


_REFUSAL_KEYWORDS = (
    "抱歉", "不能", "無法協助", "無法幫", "不適合", "違反", "sorry",
    "can't", "cannot", "unable to", "not able", "policy", "refuse",
)


def _check_response(resp: httpx.Response) -> None:
    """把上游 4xx/5xx 轉成分類過的 ImageGenError，保留 ChatGPT 的拒絕原因。"""
    if resp.status_code < 400:
        return
    detail = ""
    code = ""
    try:
        err = resp.json().get("error", {})
        detail = str(err.get("message") or "").strip()
        code = str(err.get("code") or "")
    except Exception:
        pass
    short = detail[:150]

    if code == "upstream_text_reply" or "text description" in detail:
        raise ImageGenError(
            "😅 上游 AI 這次沒有產生圖片（只回了文字說明）。\n"
            "通常再試一次就好了；如果一直這樣，換個說法試試。",
            detail=f"upstream_text_reply: {short}",
        )
    if detail and any(kw in detail.lower() for kw in _REFUSAL_KEYWORDS):
        raise ImageGenError(
            "🚫 這個主題被上游 AI 的安全機制擋下了：\n"
            f"「{short}」\n"
            "換個主題或說法再試試。",
            detail=f"refused: {short}",
        )
    if resp.status_code == 429 or any(kw in detail.lower() for kw in ("rate", "quota", "額度", "limit")):
        raise ImageGenError(
            "⏳ 上游帳號暫時忙碌或額度用盡，請晚一點再試。",
            detail=f"rate_limited({resp.status_code}): {short}",
        )
    raise ImageGenError(
        "😥 生成失敗了，可能是上游忙碌。\n請稍後再試一次，或換個說法。",
        detail=f"http_{resp.status_code}: {short}" if short else f"http_{resp.status_code}",
    )


def _extract_images(payload: dict) -> list[bytes]:
    images: list[bytes] = []
    for item in payload.get("data", []):
        b64 = item.get("b64_json")
        if b64:
            images.append(base64.b64decode(b64))
    return images


async def generate_image(prompt: str, size: str | None = None) -> list[bytes]:
    body = {
        "prompt": prompt,
        "model": config.CHATGPT2API_IMAGE_MODEL,
        "n": 1,
        "response_format": "b64_json",
    }
    if size:
        body["size"] = size
    async with httpx.AsyncClient(timeout=config.IMAGE_TIMEOUT_SECS) as client:
        resp = await client.post(
            f"{config.CHATGPT2API_BASE_URL}/v1/images/generations",
            headers=_headers(),
            json=body,
        )
        _check_response(resp)
        images = _extract_images(resp.json())
    if not images:
        raise ImageGenError(
            "😅 上游 AI 這次沒有回傳圖片，請再試一次。",
            detail="empty_image_list",
        )
    return images


async def edit_image(prompt: str, image_paths: list[str]) -> list[bytes]:
    files = []
    handles = []
    try:
        for path in image_paths:
            fh = open(path, "rb")
            handles.append(fh)
            files.append(("image[]", (path.rsplit("/", 1)[-1], fh, "image/jpeg")))
        data = {
            "prompt": prompt,
            "model": config.CHATGPT2API_IMAGE_MODEL,
            "response_format": "b64_json",
        }
        async with httpx.AsyncClient(timeout=config.IMAGE_TIMEOUT_SECS) as client:
            resp = await client.post(
                f"{config.CHATGPT2API_BASE_URL}/v1/images/edits",
                headers=_headers(),
                data=data,
                files=files,
            )
            _check_response(resp)
            images = _extract_images(resp.json())
    finally:
        for fh in handles:
            fh.close()
    if not images:
        raise ImageGenError(
            "😅 上游 AI 這次沒有回傳圖片，請再試一次。",
            detail="empty_image_list",
        )
    return images
