"""一般指令：幫助、風格一覽、訂閱管理。"""
from __future__ import annotations

from .. import elder_picker, line_api, storage
from .base import Command, Context, all_commands, register


@register
class HelpCommand(Command):
    names = ("幫助", "help", "指令", "說明")
    usage = "#幫助"
    description = "列出所有指令"

    async def run(self, ctx: Context) -> None:
        lines = ["📖 指令一覽", ""]
        for cmd in all_commands():
            if cmd.usage:
                lines.append(f"{cmd.usage}\n　{cmd.description}")
        lines.append("")
        lines.append("💡 上傳個人照片後，就能生成有自己臉孔的長輩圖與改圖。")
        await line_api.send_text(ctx.client, ctx.reply_target, ctx.reply_token, "\n".join(lines))


@register
class StylesCommand(Command):
    names = ("風格", "styles")
    usage = "#風格"
    description = "看看長輩圖有哪些風格"

    async def run(self, ctx: Context) -> None:
        lib = elder_picker.load_library()
        by_cat: dict[str, list[str]] = {}
        for s in lib.get("styles", []):
            by_cat.setdefault(s.get("category", "其他"), []).append(s["name"])
        lines = [f"🎨 長輩圖風格庫（共 {len(lib.get('styles', []))} 種，持續增加）", ""]
        for cat, names in by_cat.items():
            lines.append(f"【{cat}】")
            lines.append("、".join(names))
            lines.append("")
        lines.append("每次生成隨機挑一種，近期用過的不會重複。")
        await line_api.send_text(ctx.client, ctx.reply_target, ctx.reply_token, "\n".join(lines))


@register
class SubscribeCommand(Command):
    names = ("訂閱", "subscribe")
    usage = "#訂閱"
    description = "每天早上自動推播一張長輩圖給你"

    async def run(self, ctx: Context) -> None:
        from .. import config
        storage.set_subscribed(ctx.user_id, True)
        await line_api.send_text(
            ctx.client, ctx.reply_target, ctx.reply_token,
            f"✅ 訂閱成功！每天 {config.DAILY_PUSH_TIME} 會自動送上一張長輩圖。\n"
            "想換口味隨時可打「#長輩圖」手動生成；取消請打「#取消訂閱」。",
        )


@register
class UnsubscribeCommand(Command):
    names = ("取消訂閱", "unsubscribe")
    usage = "#取消訂閱"
    description = "停止每日長輩圖推播"

    async def run(self, ctx: Context) -> None:
        storage.set_subscribed(ctx.user_id, False)
        await line_api.send_text(ctx.client, ctx.reply_target, ctx.reply_token, "已取消訂閱，不會再每日推播。隨時打「#訂閱」可恢復。")
