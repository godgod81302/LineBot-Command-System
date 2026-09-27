"""長輩圖風格挑選器：每次都不同、應景、不寫死。

資料驅動：風格全部定義在 styles/elder_styles.json，新增風格不用改程式。
挑選規則：
  1. 節慶視窗內的季節風格才會出現，且權重 × seasonal_boost
  2. 同用戶最近 N 次抽過的風格排除（N 由 LINEBOT_ELDER_STYLE_DEDUP 決定）
  3. 有臉版只挑 supports_face=true 的風格；無臉版全部可挑
  4. 問候語依時段輪替（早安/午安/晚安），祝福語隨機
"""
from __future__ import annotations

import json
import random
from datetime import datetime

from . import config, storage

_cache: dict = {"mtime": 0.0, "data": None}


def load_library() -> dict:
    mtime = config.STYLES_PATH.stat().st_mtime
    if _cache["data"] is None or mtime != _cache["mtime"]:
        _cache["data"] = json.loads(config.STYLES_PATH.read_text(encoding="utf-8"))
        _cache["mtime"] = mtime
    return _cache["data"]


def _in_window(window: list[str], today: datetime) -> bool:
    """window = ['MM-DD', 'MM-DD']；支援跨年（start > end）。"""
    if not window or len(window) != 2:
        return False
    start, end = window
    current = today.strftime("%m-%d")
    if start <= end:
        return start <= current <= end
    return current >= start or current <= end


def greeting_for(hour: int, lib: dict) -> str:
    if 5 <= hour < 11:
        period = "morning"
    elif 11 <= hour < 14:
        period = "noon"
    elif 14 <= hour < 18:
        period = "afternoon"
    else:
        period = "night"
    variants = lib.get("greetings", {}).get(period) or ["早安您好"]
    return random.choice(variants)


def pick_style(line_user_id: str, with_face: bool, now: datetime | None = None) -> dict:
    lib = load_library()
    now = now or datetime.now(config.TZ)
    boost = float(lib.get("seasonal_boost", 3))

    candidates = []
    for style in lib.get("styles", []):
        if with_face and not style.get("supports_face"):
            continue
        weight = float(style.get("weight", 1))
        window = style.get("season_window")
        if window:
            if not _in_window(window, now):
                continue
            weight *= boost
        candidates.append((style, weight))

    if not candidates:
        raise RuntimeError("風格庫裡沒有可用的風格")

    recent = set(storage.recent_elder_styles(line_user_id, config.ELDER_STYLE_DEDUP))
    fresh = [(s, w) for s, w in candidates if s["id"] not in recent]
    pool = fresh or candidates  # 全部用過就重置，允許重複

    styles = [s for s, _ in pool]
    weights = [w for _, w in pool]
    chosen = random.choices(styles, weights=weights, k=1)[0]
    storage.record_elder_style(line_user_id, chosen["id"])
    return chosen


def render_custom_prompt(theme: str, with_face: bool, greeting_text: str | None = None,
                         now: datetime | None = None) -> tuple[str, str]:
    """自訂主題（#長輩圖 中秋節 [圖上文字]）→ 回傳 (prompt, 顯示名)。

    greeting_text：指定圖片上的問候字（上限 8 字，太多會放不下）；
    沒給就依台灣時段自動挑問候語。
    """
    lib = load_library()
    now = now or datetime.now(config.TZ)
    greeting = greeting_text or greeting_for(now.hour, lib)
    blessing = random.choice(lib.get("blessings", ["平安喜樂"]))
    theme = theme.strip()[:60]

    if with_face:
        prompt = (
            FACE_TUNE
            + f"以「{theme}」為主題為這位人物設計造型與場景：服裝、妝髮、背景、色調都要呼應主題，"
            "畫面精緻、溫暖喜氣、適合長輩問候。"
            f"畫面上方以配合主題的美術字體寫著大大的「{greeting}」，下方以較小字體寫著「{blessing}」。"
        )
    else:
        prompt = (
            f"以「{theme}」為主題創作一張長輩問候圖：構圖飽滿、色彩明亮溫暖、喜氣祥和，"
            "適合長輩問候的風格。"
            f"畫面上方以優雅書法字體寫著大大的「{greeting}」，下方以較小的紅色楷書寫著「{blessing}」。"
        )
    label = f"自訂主題・{theme}（{greeting}）"
    return prompt, label


# 有臉風格通用規則：年輕化＋姿勢依風格重設（注入所有 face prompt 前面）
FACE_TUNE = (
    "請將人物的容貌調整回約二十八至三十二歲、人生最好看的成年模樣："
    "膚質緊緻、氣色紅潤有精神，即使是年長的長輩也要回到三十出頭的樣子；"
    "但仍要完整保留五官神韻、讓人一眼認出是本人。"
    "人物的姿勢、表情與構圖請依照主題風格重新設計，自然融入場景，"
    "不要受限於參考照片原本的姿勢、穿著與背景。"
)


def render_prompt(style: dict, with_face: bool, now: datetime | None = None) -> tuple[str, str]:
    """回傳 (prompt, 風格顯示名)。"""
    lib = load_library()
    now = now or datetime.now(config.TZ)
    greeting = greeting_for(now.hour, lib)
    blessing = random.choice(lib.get("blessings", ["平安喜樂"]))

    template = style.get("face_prompt") if with_face else style.get("scene_prompt")
    if not template:
        template = style.get("scene_prompt", "")
    prompt = template.replace("{greeting}", greeting).replace("{blessing}", blessing)
    if with_face:
        prompt = FACE_TUNE + prompt

    label = f"{style.get('category', '')}・{style.get('name', '')}（{greeting}）"
    return prompt, label
