"""SQLite 儲存：用戶、上傳照片、對話狀態、生成紀錄、風格歷史、訂閱。"""
from __future__ import annotations

import json
import sqlite3
import threading
import time
from contextlib import contextmanager

from . import config

_lock = threading.Lock()

_SCHEMA = """
CREATE TABLE IF NOT EXISTS users (
    line_user_id TEXT PRIMARY KEY,
    display_name TEXT,
    subscribed INTEGER NOT NULL DEFAULT 0,
    created_at REAL NOT NULL,
    updated_at REAL NOT NULL
);
CREATE TABLE IF NOT EXISTS user_photos (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    line_user_id TEXT NOT NULL,
    file_path TEXT NOT NULL,
    created_at REAL NOT NULL
);
CREATE TABLE IF NOT EXISTS user_states (
    line_user_id TEXT PRIMARY KEY,
    state TEXT NOT NULL,
    payload TEXT,
    expires_at REAL NOT NULL
);
CREATE TABLE IF NOT EXISTS generations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    line_user_id TEXT NOT NULL,
    kind TEXT NOT NULL,
    prompt TEXT,
    media_file TEXT,
    status TEXT NOT NULL,
    error TEXT,
    created_at REAL NOT NULL
);
CREATE TABLE IF NOT EXISTS elder_style_history (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    line_user_id TEXT NOT NULL,
    style_id TEXT NOT NULL,
    created_at REAL NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_elder_history_user ON elder_style_history(line_user_id, id DESC);
"""


def init_db() -> None:
    with _connect() as conn:
        conn.execute("PRAGMA journal_mode=WAL")
        conn.executescript(_SCHEMA)


@contextmanager
def _connect():
    with _lock:
        conn = sqlite3.connect(config.DB_PATH, timeout=30)
        conn.row_factory = sqlite3.Row
        try:
            yield conn
            conn.commit()
        finally:
            conn.close()


def touch_user(line_user_id: str, display_name: str | None = None) -> None:
    now = time.time()
    with _connect() as conn:
        conn.execute(
            """INSERT INTO users (line_user_id, display_name, created_at, updated_at)
               VALUES (?, ?, ?, ?)
               ON CONFLICT(line_user_id) DO UPDATE SET
                 display_name = COALESCE(excluded.display_name, users.display_name),
                 updated_at = excluded.updated_at""",
            (line_user_id, display_name, now, now),
        )


def set_subscribed(line_user_id: str, subscribed: bool) -> None:
    touch_user(line_user_id)
    with _connect() as conn:
        conn.execute(
            "UPDATE users SET subscribed = ?, updated_at = ? WHERE line_user_id = ?",
            (1 if subscribed else 0, time.time(), line_user_id),
        )


def list_subscribed() -> list[str]:
    with _connect() as conn:
        rows = conn.execute("SELECT line_user_id FROM users WHERE subscribed = 1").fetchall()
    return [r["line_user_id"] for r in rows]


def add_user_photo(line_user_id: str, file_path: str) -> int:
    with _connect() as conn:
        conn.execute(
            "INSERT INTO user_photos (line_user_id, file_path, created_at) VALUES (?, ?, ?)",
            (line_user_id, file_path, time.time()),
        )
        # 只保留最新 MAX_USER_PHOTOS 張
        conn.execute(
            """DELETE FROM user_photos WHERE line_user_id = ? AND id NOT IN (
                 SELECT id FROM user_photos WHERE line_user_id = ? ORDER BY id DESC LIMIT ?)""",
            (line_user_id, line_user_id, config.MAX_USER_PHOTOS),
        )
        count = conn.execute(
            "SELECT COUNT(*) AS c FROM user_photos WHERE line_user_id = ?", (line_user_id,)
        ).fetchone()["c"]
    return count


def get_user_photos(line_user_id: str) -> list[str]:
    with _connect() as conn:
        rows = conn.execute(
            "SELECT file_path FROM user_photos WHERE line_user_id = ? ORDER BY id DESC",
            (line_user_id,),
        ).fetchall()
    return [r["file_path"] for r in rows]


def clear_user_photos(line_user_id: str) -> None:
    with _connect() as conn:
        conn.execute("DELETE FROM user_photos WHERE line_user_id = ?", (line_user_id,))


def set_state(line_user_id: str, state: str, payload: dict | None = None, ttl_secs: int = 600) -> None:
    with _connect() as conn:
        conn.execute(
            """INSERT INTO user_states (line_user_id, state, payload, expires_at)
               VALUES (?, ?, ?, ?)
               ON CONFLICT(line_user_id) DO UPDATE SET
                 state = excluded.state, payload = excluded.payload, expires_at = excluded.expires_at""",
            (line_user_id, state, json.dumps(payload or {}, ensure_ascii=False), time.time() + ttl_secs),
        )


def get_state(line_user_id: str) -> tuple[str, dict] | None:
    with _connect() as conn:
        row = conn.execute(
            "SELECT state, payload, expires_at FROM user_states WHERE line_user_id = ?",
            (line_user_id,),
        ).fetchone()
        if not row or row["expires_at"] < time.time():
            conn.execute("DELETE FROM user_states WHERE line_user_id = ?", (line_user_id,))
            return None
    return row["state"], json.loads(row["payload"] or "{}")


def clear_state(line_user_id: str) -> None:
    with _connect() as conn:
        conn.execute("DELETE FROM user_states WHERE line_user_id = ?", (line_user_id,))


def log_generation(line_user_id: str, kind: str, prompt: str, media_file: str | None,
                   status: str, error: str | None = None) -> None:
    with _connect() as conn:
        conn.execute(
            """INSERT INTO generations (line_user_id, kind, prompt, media_file, status, error, created_at)
               VALUES (?, ?, ?, ?, ?, ?, ?)""",
            (line_user_id, kind, prompt, media_file, status, error, time.time()),
        )


def record_elder_style(line_user_id: str, style_id: str) -> None:
    with _connect() as conn:
        conn.execute(
            "INSERT INTO elder_style_history (line_user_id, style_id, created_at) VALUES (?, ?, ?)",
            (line_user_id, style_id, time.time()),
        )


def recent_elder_styles(line_user_id: str, limit: int) -> list[str]:
    with _connect() as conn:
        rows = conn.execute(
            "SELECT style_id FROM elder_style_history WHERE line_user_id = ? ORDER BY id DESC LIMIT ?",
            (line_user_id, limit),
        ).fetchall()
    return [r["style_id"] for r in rows]


def is_subscribed(line_user_id: str) -> bool:
    with _connect() as conn:
        row = conn.execute(
            "SELECT subscribed FROM users WHERE line_user_id = ?", (line_user_id,),
        ).fetchone()
    return bool(row and row["subscribed"])


def count_successful_generations(line_user_id: str) -> int:
    """成功生圖累計張數（試用額度計算用）。"""
    with _connect() as conn:
        row = conn.execute(
            "SELECT COUNT(*) AS n FROM generations WHERE line_user_id = ? AND status = 'succeeded'",
            (line_user_id,),
        ).fetchone()
    return int(row["n"] if row else 0)
