"""生成流程共用：存檔、託管 URL、任務包裝（loading 動畫→生成→一次回覆結果）。"""
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
) -> None:
    """生成圖片並以一次 reply 回覆結果（圖片+說明打包，reply 失敗才 fallback push）。

    replyToken 只能回一次，所以不發「生成中」訊息；一對一改用免費的 loading 動畫。
    """
    if not ctx.is_group:
        await line_api.start_loading(ctx.client, ctx.user_id)
    try:
        images = await produce()
    except Exception as exc:
        storage.log_generation(ctx.user_id, kind, prompt, None, "failed", str(exc)[:500])
        await line_api.send_text(
            ctx.client, ctx.reply_target, ctx.reply_token,
            "😥 生成失敗了，可能是上游忙碌或內容被擋下。\n"
            "請稍後再試一次，或換個說法。\n"
            f"（技術訊息：{str(exc)[:200]}）",
        )
        return

    name = save_media(images[0])
    storage.log_generation(ctx.user_id, kind, prompt, name, "succeeded")
    messages = [line_api.image_msg(public_media_url(name))]
    if label:
        messages.append(line_api.text_msg(label))
    await line_api.send_messages(ctx.client, ctx.reply_target, ctx.reply_token, messages)
