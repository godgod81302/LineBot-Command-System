"""LINE Bot FastAPI 主程式：webhook、媒體託管、狀態機、每日訂閱推播。

啟動：uvicorn bot.main:app --host 127.0.0.1 --port 8090

訊息原則：互動回應一律優先 reply（免費），失敗才 fallback push；
每日訂閱推播無 replyToken 可用，是本系統唯一主動 push 的地方（圖+文打包一則）。
"""
from __future__ import annotations

import asyncio
import time
from datetime import datetime

import httpx
from fastapi import FastAPI, Request, Response
from fastapi.responses import FileResponse, JSONResponse

from . import config, elder_picker, gen_service, image_api, line_api, storage
from .commands import general as _general_cmds  # noqa: F401  觸發指令註冊
from .commands import photo as _photo_cmds  # noqa: F401
from .commands.base import Context, match_command

app = FastAPI(title="LINE AI Image Bot")


@app.on_event("startup")
async def _startup() -> None:
    storage.init_db()
    asyncio.create_task(_daily_push_loop())


@app.get("/linebot/health")
async def health() -> dict:
    return {"ok": True, "time": datetime.now().isoformat()}


@app.get("/linebot/media/{name}")
async def media(name: str) -> FileResponse:
    if "/" in name or ".." in name:
        return JSONResponse({"error": "bad name"}, status_code=400)
    path = config.MEDIA_DIR / name
    if not path.exists():
        return JSONResponse({"error": "not found"}, status_code=404)
    return FileResponse(path, media_type="image/jpeg",
                        headers={"Cache-Control": "public, max-age=86400"})


@app.post("/linebot/webhook")
async def webhook(request: Request) -> Response:
    body = await request.body()
    signature = request.headers.get("X-Line-Signature")
    if not line_api.verify_signature(body, signature):
        return JSONResponse({"error": "invalid signature"}, status_code=403)

    payload = await request.json()
    events = payload.get("events", [])
    for event in events:
        asyncio.create_task(_handle_event(event))
    return Response(content="ok", media_type="text/plain")


# ---------------------------------------------------------------- 事件處理

WELCOME = (
    "歡迎使用 AI 長輩圖＆生圖機器人 🎨\n\n"
    "打「#幫助」看所有指令。\n"
    "最推薦：先打「#設定」上傳你的正面照片，\n"
    "之後打「#長輩圖」每天都能收到有你臉孔、風格不重複的創意長輩圖！"
)


async def _handle_event(event: dict) -> None:
    etype = event.get("type")
    source = event.get("source", {})
    user_id = source.get("userId")
    if not user_id:
        return
    is_group = source.get("type") in ("group", "room")
    reply_target = source.get("groupId") or source.get("roomId") or user_id
    reply_token = event.get("replyToken")

    async with httpx.AsyncClient() as client:
        if etype == "follow":
            name = await line_api.get_profile(client, user_id)
            storage.touch_user(user_id, name)
            await line_api.send_text(client, reply_target, reply_token, WELCOME)
            return

        if etype != "message":
            return

        storage.touch_user(user_id)
        message = event.get("message", {})
        mtype = message.get("type")

        if mtype == "text":
            await _handle_text(client, user_id, reply_target, reply_token, is_group,
                               message.get("text", "").strip())
        elif mtype == "image":
            await _handle_image(client, user_id, reply_target, reply_token, is_group, message)


async def _handle_text(client: httpx.AsyncClient, user_id: str, reply_target: str,
                       reply_token: str | None, is_group: bool, text: str) -> None:
    # 狀態機：等待照片中
    state = storage.get_state(user_id)
    if state and state[0] in ("awaiting_photos", "elder_pending_photo"):
        if text in ("完成", "好了", "done"):
            photos = storage.get_user_photos(user_id)
            if photos:
                storage.clear_state(user_id)
                await line_api.send_text(client, reply_target, reply_token,
                                         f"✅ 已儲存 {len(photos)} 張照片！\n現在可以打「#長輩圖」或「#改圖」了。")
            else:
                await line_api.send_text(client, reply_target, reply_token,
                                         "還沒收到照片喔，請直接傳送圖片給我。")
            return
        if not text.startswith("#"):
            await line_api.send_text(client, reply_target, reply_token,
                                     "請直接「傳送圖片」給我，上傳完打「完成」。")
            return
        storage.clear_state(user_id)  # 打指令就離開上傳狀態

    if not text.startswith("#"):
        if not is_group:
            await line_api.send_text(client, reply_target, reply_token,
                                     "打「#幫助」可以看我能做什麼 🎨")
        return

    matched = match_command(text[1:])
    if not matched:
        await line_api.send_text(client, reply_target, reply_token,
                                 "看不懂這個指令，打「#幫助」看指令一覽。")
        return

    cmd, args = matched
    if cmd.private_only and is_group:
        await line_api.send_text(client, reply_target, reply_token,
                                 "這個指令涉及個人照片，請在一對一私聊中使用 🔒")
        return

    ctx = Context(client=client, user_id=user_id, reply_target=reply_target,
                  is_group=is_group, text=text, args=args, reply_token=reply_token)
    try:
        await cmd.run(ctx)
    except Exception as exc:  # 不讓單一指令炸掉整個 bot
        await line_api.send_text(client, reply_target, reply_token,
                                 f"😥 指令執行出錯了，請稍後再試。（{str(exc)[:150]}）")


async def _handle_image(client: httpx.AsyncClient, user_id: str, reply_target: str,
                        reply_token: str | None, is_group: bool, message: dict) -> None:
    state = storage.get_state(user_id)
    if not state or state[0] not in ("awaiting_photos", "elder_pending_photo"):
        await line_api.send_text(
            client, reply_target, reply_token,
            "收到照片了！想做什麼呢？\n・「#設定」→ 存成你的專屬照片\n・打「#幫助」看更多玩法",
        )
        return

    data = await line_api.get_message_content(client, message["id"])
    path = config.PHOTO_DIR / f"{user_id}_{int(time.time())}.jpg"
    path.write_bytes(data)
    count = storage.add_user_photo(user_id, str(path))

    if state[0] == "elder_pending_photo":
        storage.clear_state(user_id)
        # 直接生成第一張專屬長輩圖，結果連同說明一次回覆
        ctx = Context(client=client, user_id=user_id, reply_target=reply_target,
                      is_group=is_group, text="", args="", reply_token=reply_token)
        await _generate_elder(ctx, greeting="✅ 照片收到了！這是你的第一張專屬長輩圖：")
    else:
        await line_api.send_text(
            client, reply_target, reply_token,
            f"📥 已收到第 {count} 張（最多 {config.MAX_USER_PHOTOS} 張）。\n繼續傳或打「完成」。",
        )


# ---------------------------------------------------------------- 長輩圖生成

async def _generate_elder(ctx: Context, greeting: str | None = None) -> None:
    """產生一張長輩圖並回覆（reply 優先）。greeting 會併入同一批訊息。"""
    if not await gen_service.check_quota(ctx):
        return
    photos = storage.get_user_photos(ctx.user_id)
    with_face = bool(photos)
    try:
        style = elder_picker.pick_style(ctx.user_id, with_face)
        prompt, label = elder_picker.render_prompt(style, with_face)
        if not ctx.is_group:
            await line_api.start_loading(ctx.client, ctx.user_id)
        if with_face:
            images = await image_api.edit_image(prompt, photos[:2])
            kind = "elder_face"
        else:
            images = await image_api.generate_image(prompt)
            kind = "elder_scene"
    except Exception as exc:
        storage.log_generation(ctx.user_id, "elder_push", "", None, "failed", str(exc)[:500])
        await line_api.send_text(ctx.client, ctx.reply_target, ctx.reply_token,
                                 "😥 長輩圖生成失敗，請稍後手動打「#長輩圖」再試。")
        return

    name = gen_service.save_media(images[0])
    storage.log_generation(ctx.user_id, kind, prompt, name, "succeeded")
    messages = []
    if greeting:
        messages.append(line_api.text_msg(greeting))
    messages.append(line_api.image_msg(gen_service.public_media_url(name)))
    messages.append(line_api.text_msg(f"✨ 本次風格：{label}"))
    await line_api.send_messages(ctx.client, ctx.reply_target, ctx.reply_token, messages)


async def _daily_push_loop() -> None:
    """每天 DAILY_PUSH_TIME 推播長輩圖給訂閱用戶（無 replyToken，唯一走 push 的地方）。"""
    last_run_date = None
    while True:
        await asyncio.sleep(30)
        now = datetime.now(config.TZ)
        hhmm = now.strftime("%H:%M")
        today = now.date()
        if hhmm != config.DAILY_PUSH_TIME or last_run_date == today:
            continue
        last_run_date = today
        for user_id in storage.list_subscribed():
            try:
                async with httpx.AsyncClient() as client:
                    ctx = Context(client=client, user_id=user_id, reply_target=user_id,
                                  is_group=False, text="", args="", reply_token=None)
                    await _generate_elder(ctx)
                await asyncio.sleep(1)  # 避免瞬間打滿 LINE/上游
            except Exception:
                continue
