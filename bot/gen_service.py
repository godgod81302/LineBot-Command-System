"""生成流程共用：存檔、託管 URL、任務包裝（收到→生成中→推播結果）。"""
from __future__ import annotations

import uuid
from typing import Awaitable, Callable

from . import config, line_api, storage
from .commands.base import Context


def save_media(data: bytes, ext: str = "jpg") -> str:
    name = f"{uuid.uuid4().hex}.{ext}"
    (config.MEDIA_DIR / name).write_bytes(data)
    return name


def public_media_url(name: str) -> str:
    return f"{config.PUBLIC_BASE_URL}/linebot/media/{name}"


async def run_generation(
    ctx: Context,
    kind: str,
    prompt: str,
    produce: Callable[[], Awaitable[list[bytes]]],
    label: str | None = None,
    ack: str = "🎨 收到，生成中，大約需要 1～2 分鐘，好了會馬上傳給你…",
) -> None:
    await line_api.push_text(ctx.client, ctx.reply_target, ack)
    try:
        images = await produce()
    except Exception as exc:
        storage.log_generation(ctx.user_id, kind, prompt, None, "failed", str(exc)[:500])
        await line_api.push_text(
            ctx.client, ctx.reply_target,
            "😥 生成失敗了，可能是上游忙碌或內容被擋下。\n"
            "請稍後再試一次，或換個說法。\n"
            f"（技術訊息：{str(exc)[:200]}）",
        )
        return

    name = save_media(images[0])
    storage.log_generation(ctx.user_id, kind, prompt, name, "succeeded")
    await line_api.push_image(ctx.client, ctx.reply_target, public_media_url(name))
    if label:
        await line_api.push_text(ctx.client, ctx.reply_target, label)
