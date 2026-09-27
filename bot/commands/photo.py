"""照片與生成指令：#設定 #我的照片 #清除照片 #畫 #改圖 #長輩圖。"""
from __future__ import annotations

from .. import config, elder_picker, gen_service, image_api, line_api, storage
from .base import Command, Context, register

NO_PHOTO_HINT = "還沒有你的照片喔！先打「#設定」，再上傳 1～3 張清晰的正面個人照。"


@register
class SetupCommand(Command):
    names = ("設定", "上傳照片", "setup")
    usage = "#設定"
    description = "上傳 1～3 張正面個人照（長輩圖、改圖用）"

    async def run(self, ctx: Context) -> None:
        storage.set_state(ctx.user_id, "awaiting_photos", ttl_secs=900)
        group_note = "（注意：在群組上傳的照片，群組成員都看得到）\n" if ctx.is_group else ""
        await line_api.send_text(
            ctx.client, ctx.reply_target, ctx.reply_token,
            "📷 請直接上傳 1～3 張「清晰正面、光線充足」的個人照片。\n"
            "上傳完打「完成」即可；15 分鐘內有效。\n"
            f"{group_note}"
            "（照片只存在我們的伺服器，用於生成你的專屬圖片）",
        )


@register
class MyPhotosCommand(Command):
    names = ("我的照片",)
    usage = "#我的照片"
    description = "查看已上傳的照片數量"
    private_only = True

    async def run(self, ctx: Context) -> None:
        photos = storage.get_user_photos(ctx.user_id)
        if photos:
            await line_api.send_text(
                ctx.client, ctx.reply_target, ctx.reply_token,
                f"目前存有 {len(photos)} 張照片（最多保留 {config.MAX_USER_PHOTOS} 張，新照片會覆蓋舊的）。\n"
                "想重設請打「#設定」重新上傳，或「#清除照片」刪除。",
            )
        else:
            await line_api.send_text(ctx.client, ctx.reply_target, ctx.reply_token, NO_PHOTO_HINT)


@register
class ClearPhotosCommand(Command):
    names = ("清除照片",)
    usage = "#清除照片"
    description = "刪除所有已上傳的照片"
    private_only = True

    async def run(self, ctx: Context) -> None:
        photos = storage.get_user_photos(ctx.user_id)
        import os
        for path in photos:
            try:
                os.remove(path)
            except OSError:
                pass
        storage.clear_user_photos(ctx.user_id)
        await line_api.send_text(ctx.client, ctx.reply_target, ctx.reply_token, "🗑 已刪除所有上傳的照片。")


@register
class DrawCommand(Command):
    names = ("畫", "生成", "draw")
    usage = "#畫 描述內容"
    description = "文生圖：照你的描述生成一張圖"

    async def run(self, ctx: Context) -> None:
        if not ctx.args:
            await line_api.send_text(ctx.client, ctx.reply_target, ctx.reply_token, "請加上想畫的內容，例如：#畫 一隻在櫻花樹下睡覺的貓")
            return
        prompt = ctx.args

        async def produce():
            return await image_api.generate_image(prompt)

        await gen_service.run_generation(ctx, "draw", prompt, produce)


@register
class EditCommand(Command):
    names = ("改圖", "edit")
    usage = "#改圖 修改指令"
    description = "以你的照片為底，照指令修改（例：#改圖 幫我加上聖誕帽）"
    private_only = True

    async def run(self, ctx: Context) -> None:
        photos = storage.get_user_photos(ctx.user_id)
        if not photos:
            await line_api.send_text(ctx.client, ctx.reply_target, ctx.reply_token, NO_PHOTO_HINT)
            return
        if not ctx.args:
            await line_api.send_text(ctx.client, ctx.reply_target, ctx.reply_token, "請加上修改指令，例如：#改圖 把背景換成巴黎鐵塔")
            return
        prompt = (
            "請以參考照片中的人物為主角（保留五官特徵，讓人認得出是同一個人），"
            f"依照以下指示修改圖片：{ctx.args}"
        )

        async def produce():
            return await image_api.edit_image(prompt, photos[:2])

        await gen_service.run_generation(ctx, "edit", prompt, produce)


@register
class ElderCommand(Command):
    names = ("長輩圖", "早安圖", "elder")
    usage = "#長輩圖（可接主題＋圖上文字，例：#長輩圖 中秋節 花好月圓；加「無臉」變純風景）"
    description = "生成一張有創意的長輩圖，每次都不同"

    async def run(self, ctx: Context) -> None:
        with_face = "無臉" not in ctx.args
        raw = ctx.args.replace("無臉", "").strip()

        # 語法：#長輩圖 主題 [圖上文字(≤4字)]
        theme, custom_text = raw, None
        parts = raw.split()
        if len(parts) >= 2:
            if len(parts[-1]) <= 8:
                custom_text = parts[-1]
                theme = " ".join(parts[:-1])
            else:
                await line_api.send_text(
                    ctx.client, ctx.reply_target, ctx.reply_token,
                    "圖片上的問候字最多 8 個字喔（太多會放不下）！\n"
                    "例如：#長輩圖 中秋節 花好月圓",
                )
                return

        photos = storage.get_user_photos(ctx.user_id) if with_face else []

        if with_face and not photos:
            storage.set_state(ctx.user_id, "elder_pending_photo", ttl_secs=900)
            await line_api.send_text(
                ctx.client, ctx.reply_target, ctx.reply_token,
                "想生成有你臉孔的長輩圖，需要先上傳照片！\n"
                "請直接上傳 1～3 張清晰正面個人照（15 分鐘內有效）。\n"
                "或打「#長輩圖 無臉」先生成純風景祝福圖。",
            )
            return

        if theme:
            prompt, label = elder_picker.render_custom_prompt(theme, with_face, greeting_text=custom_text)
            storage.record_elder_style(ctx.user_id, f"custom:{theme}")
        else:
            style = elder_picker.pick_style(ctx.user_id, with_face)
            prompt, label = elder_picker.render_prompt(style, with_face)
        kind = "elder_face" if with_face else "elder_scene"

        async def produce():
            if with_face:
                return await image_api.edit_image(prompt, photos[:2])
            return await image_api.generate_image(prompt)

        await gen_service.run_generation(
            ctx, kind, prompt, produce,
            label=f"✨ 本次風格：{label}",
        )
