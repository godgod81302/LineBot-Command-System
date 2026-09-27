"""指令系統（沿用舊專案 CommandManager 的概念：前綴觸發、自動註冊、權限掛點）。

每個指令是一個 Command 子類：
  names    觸發詞（「#畫」的「畫」），可多個別名
  usage    用法說明（#幫助 用）
  async run(ctx)  執行本體
"""
from __future__ import annotations

from dataclasses import dataclass, field
from typing import TYPE_CHECKING

if TYPE_CHECKING:
    import httpx

_REGISTRY: list[type["Command"]] = []


def register(cls: type["Command"]) -> type["Command"]:
    _REGISTRY.append(cls)
    return cls


@dataclass
class Context:
    client: "httpx.AsyncClient"
    user_id: str            # LINE user id
    reply_target: str       # push 目標（私聊=user id，群組=group id）
    is_group: bool
    text: str               # 完整訊息文字（含 # 前綴）
    args: str               # 去掉觸發詞後的內容
    extras: dict = field(default_factory=dict)


class Command:
    names: tuple[str, ...] = ()
    usage: str = ""
    description: str = ""
    # 需要一對一私聊（個人照片隱私）
    private_only: bool = False

    async def run(self, ctx: Context) -> None:  # pragma: no cover - 抽象方法
        raise NotImplementedError

    def matches(self, body: str) -> bool:
        return any(body == name or body.startswith(name + " ") or body.startswith(name + "　")
                   for name in self.names)


def all_commands() -> list[Command]:
    return [cls() for cls in _REGISTRY]


def match_command(body: str) -> tuple[Command, str] | None:
    """回傳 (command, args)；args 是觸發詞之後的文字。"""
    for cmd in all_commands():
        for name in cmd.names:
            if body == name:
                return cmd, ""
            for sep in (" ", "　"):
                if body.startswith(name + sep):
                    return cmd, body[len(name) + len(sep):].strip()
    return None
