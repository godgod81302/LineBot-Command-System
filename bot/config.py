"""環境設定（全部走環境變數，見 .env.example）。"""
from __future__ import annotations

import os
from datetime import timedelta, timezone
from pathlib import Path

# 業務時區固定台灣（VPS 主機是歐洲時區，問候語/每日推播都要以台灣時間為準）
TZ = timezone(timedelta(hours=8), "Asia/Taipei")

BASE_DIR = Path(__file__).resolve().parent
DATA_DIR = Path(os.getenv("LINEBOT_DATA_DIR", BASE_DIR / "data"))
MEDIA_DIR = DATA_DIR / "media"
PHOTO_DIR = DATA_DIR / "photos"
DB_PATH = DATA_DIR / "linebot.db"
STYLES_PATH = Path(os.getenv("LINEBOT_STYLES_PATH", BASE_DIR / "styles" / "elder_styles.json"))

LINE_CHANNEL_SECRET = os.getenv("LINE_CHANNEL_SECRET", "")
LINE_CHANNEL_ACCESS_TOKEN = os.getenv("LINE_CHANNEL_ACCESS_TOKEN", "")

CHATGPT2API_BASE_URL = os.getenv("CHATGPT2API_BASE_URL", "http://127.0.0.1:3000").rstrip("/")
CHATGPT2API_AUTH_KEY = os.getenv("CHATGPT2API_AUTH_KEY", "")
CHATGPT2API_IMAGE_MODEL = os.getenv("CHATGPT2API_IMAGE_MODEL", "gpt-image-2")

# 對外 HTTPS 根網址（nginx 反代到本服務），LINE 抓圖用
PUBLIC_BASE_URL = os.getenv("LINEBOT_PUBLIC_BASE_URL", "").rstrip("/")

# 生成逾時（秒），ChatGPT 生圖高峰期可能很久
IMAGE_TIMEOUT_SECS = float(os.getenv("LINEBOT_IMAGE_TIMEOUT_SECS", "300"))

# 每日訂閱推送時間（HH:MM，台灣時間）
DAILY_PUSH_TIME = os.getenv("LINEBOT_DAILY_PUSH_TIME", "07:00")

# 長輩圖風格去重：同用戶最近 N 次用過的風格不再抽
ELDER_STYLE_DEDUP = int(os.getenv("LINEBOT_ELDER_STYLE_DEDUP", "12"))

MAX_USER_PHOTOS = int(os.getenv("LINEBOT_MAX_USER_PHOTOS", "3"))

# 未訂閱用戶的終身試用張數；訂閱戶（#訂閱）每日無上限
TRIAL_IMAGE_LIMIT = int(os.getenv("LINEBOT_TRIAL_IMAGE_LIMIT", "10"))

for _d in (DATA_DIR, MEDIA_DIR, PHOTO_DIR):
    _d.mkdir(parents=True, exist_ok=True)
