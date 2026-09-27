"""chatgpt2api（OpenAI 相容圖片 API）客戶端：文生圖、圖片編輯。

回傳皆為圖片 bytes 列表；上層負責存檔與託管。
"""
from __future__ import annotations

import base64

import httpx

from . import config


def _headers() -> dict[str, str]:
    return {"Authorization": f"Bearer {config.CHATGPT2API_AUTH_KEY}"}


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
        resp.raise_for_status()
        images = _extract_images(resp.json())
    if not images:
        raise RuntimeError("上游沒有回傳圖片")
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
            resp.raise_for_status()
            images = _extract_images(resp.json())
    finally:
        for fh in handles:
            fh.close()
    if not images:
        raise RuntimeError("上游沒有回傳圖片")
    return images
