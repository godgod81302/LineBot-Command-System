# LINE AI 長輩圖＆生圖機器人（`bot/`）

> 本專案原為 2020 年的 LINE 預約排程指令系統（Laravel 7，見 git 歷史與舊 `app/`）。
> 2026-09 重建為 Python FastAPI AI 生圖機器人，部署於 VPS `/opt/linebot`，
> 透過 nginx `/linebot/` 反代，呼叫同機 chatgpt2api（`127.0.0.1:3000`）生成圖片。

## 功能

- `#幫助` — 指令一覽
- `#設定` — 上傳 1～3 張正面個人照（私聊限定，15 分鐘內有效）
- `#我的照片` / `#清除照片` — 照片管理
- `#畫 <描述>` — 文生圖
- `#改圖 <指令>` — 以本人照片為底修圖
- `#長輩圖` — 有本人臉孔的創意長輩圖；`#長輩圖 無臉` — 純風景祝福圖
- `#風格` — 查看風格庫
- `#訂閱` / `#取消訂閱` — 每天早上 07:00 自動推播長輩圖

## 長輩圖風格系統（不寫死）

- 風格全部定義在 `bot/styles/elder_styles.json`（目前 48 種，6 大類＋節慶限定），
  新增風格只要編輯 JSON，**不用改程式、不用重啟**（每次挑選前重新讀檔）。
- 挑選規則（`bot/elder_picker.py`）：
  1. 同用戶最近 12 次用過的風格不重複（`LINEBOT_ELDER_STYLE_DEDUP` 可調）
  2. 加權隨機；節慶視窗內的季節風格權重 ×3，非視窗期不出現
  3. 問候語依時段輪替（早安/午安/日安/晚安），祝福語 15 句隨機
- 風格類別：自然祝福、吉祥寓意、復古風華（民國旗袍/上海灘/玉女小說封面…）、
  青春回憶（年輕化學士照/舞廳國標/冰果室…）、動漫夢幻（美少女戰士/吉卜力/迪士尼…）、
  生活美學、節慶限定（春節/元宵/端午/母親節/父親節/七夕/中秋/重陽/聖誕/元旦）

## 部署（VPS）

```bash
# 程式：/opt/linebot（bot/ + venv/ + .env）
systemctl status linebot          # FastAPI on 127.0.0.1:8090，MemoryMax=512M CPUQuota=50%
journalctl -u linebot -f          # 看 log
```

- 設定檔 `/opt/linebot/.env`（含 LINE channel secret / access token、chatgpt2api auth-key）
- 對外入口：`https://factory.estateagent-tw.com/linebot/`
  - webhook：`POST /linebot/webhook`
  - 生成圖託管：`GET /linebot/media/<檔名>`（LINE 需要 HTTPS 圖片 URL）
- 資料：SQLite + 照片/生成圖存 `/opt/linebot/bot/data/`（json 檔不上 git）

## 新增長輩圖風格

編輯 VPS 上 `/opt/linebot/bot/styles/elder_styles.json`，往 `styles` 陣列加一筆：

```json
{
  "id": "my_new_style",
  "name": "顯示名稱",
  "category": "分類",
  "weight": 5,
  "supports_face": true,
  "season_window": ["12-15", "12-31"],
  "face_prompt": "…保留參考照片人物五官…{greeting}…{blessing}…",
  "scene_prompt": "…無臉版 prompt…"
}
```

- `supports_face: false` 的風格只會出現在「無臉」池
- `season_window` 可省略；支援跨年（如 `["12-30", "01-05"]`）
- prompt 中可用 `{greeting}`（時段問候語）、`{blessing}`（祝福語）

## 本地開發

```bash
cd bot && pip install -r requirements.txt
LINE_CHANNEL_SECRET=x uvicorn bot.main:app --port 8090
```
