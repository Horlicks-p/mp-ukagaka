# 死亡代碼清理後的文件待辦事項

> 執行日期：2026-03-06
> 對應版本：2.12.5（下次版號再議）

## 背景

本次清理移除了已遷移至 OO REST Controller（v2.10.0）後遺留的死亡代碼，
共刪除 6 個無效檔案、移除 26 個無呼叫點的函數。

---

## 一、需從文件中移除的函數條目

以下函數已從代碼庫中刪除，各語言文件中仍有記載，需逐一清除。

### docs/DEVELOPER_GUIDE.md（繁體中文）

| 行號 | 函數 | 原所在檔案 |
|------|------|-----------|
| 267  | `mpu_html_decode()` | `core/utility-functions.php` |
| 278  | `mpu_is_browser()` | `core/utility-functions.php` |
| 557  | `mpu_should_trigger_ai()` | `llm/ai-functions.php` |
| 829  | `mpu_ukagaka_list()` | `core/ukagaka-functions.php` |
| 869  | `mpu_get_default_msg()` | `core/ukagaka-functions.php` |
| 890  | `mpu_get_next_msg()` | `core/ukagaka-functions.php` |
| 905  | `mpu_get_msg_key()` | `core/ukagaka-functions.php` |
| 912  | `mpu_count_msg()` | `core/ukagaka-functions.php` |

### docs/API_REFERENCE.md（繁體中文）

| 行號 | 函數 |
|------|------|
| 402  | `mpu_should_trigger_ai()` |

### docs-en/DEVELOPER_GUIDE.md（英文）

| 行號 | 函數 |
|------|------|
| 255  | `mpu_html_decode()` |
| 266  | `mpu_is_browser()` |
| 398  | `mpu_should_trigger_ai()` |
| 679  | `mpu_ukagaka_list()` |
| 719  | `mpu_get_default_msg()` |
| 740  | `mpu_get_next_msg()` |
| 755  | `mpu_get_msg_key()` |
| 762  | `mpu_count_msg()` |

### docs-en/API_REFERENCE.md（英文）

| 行號 | 函數 |
|------|------|
| 302  | `mpu_should_trigger_ai()` |

### docs-jp/DEVELOPER_GUIDE.md（日文）

| 行號 | 函數 |
|------|------|
| 390  | `mpu_html_decode()` |
| 547  | `mpu_ukagaka_list()` |
| 587  | `mpu_get_default_msg()` |
| 608  | `mpu_get_next_msg()` |

### docs-jp/API_REFERENCE.md（日文）

| 行號 | 函數 |
|------|------|
| 240  | `mpu_should_trigger_ai()` |
| 665  | `mpu_ukagaka_list()` |

---

## 二、刪除的檔案目錄（文件中如有提及架構圖需更新）

| 刪除的目錄/檔案 | 說明 |
|----------------|------|
| `includes/rest/chat/*.php`（3 個） | 已被 `class-mpu-rest-chat.php` 取代 |
| `includes/ajax/chat/*.php`（3 個） | 從未被載入的 AJAX handlers |

> 若 DEVELOPER_GUIDE 的目錄結構圖有列出這兩個 `chat/` 子目錄，需刪除。

---

## 三、移除的函數完整清單（供文件搜尋比對）

```
mpu_ukagaka_list
mpu_get_personality_shell_url
mpu_get_personality_decorations_url
mpu_get_msg
mpu_get_random_msg
mpu_get_default_msg
mpu_get_next_msg
mpu_get_msg_key
mpu_count_msg
mpu_get_stats_summary
mpu_get_weekly_trend
mpu_get_monthly_trend
mpu_get_top_topics
mpu_get_provider_distribution
mpu_get_conversation_distribution
mpu_sse_ping
mpu_sse_is_disconnected
mpu_html_decode
mpu_is_browser
mpu_reset_rate_limit
mpu_should_trigger_ai
mpu_is_stats_enabled
mpu_build_prompt_variables
mpu_get_personality_decoration_config
mpu_clear_diary_cron
mpu_log_info
mpu_log_api_call
mpu_log_prompt_stats
```

---

## 四、文件更新優先度

| 優先度 | 項目 |
|--------|------|
| 高 | `docs/DEVELOPER_GUIDE.md`、`docs-en/DEVELOPER_GUIDE.md` — 函數 API 條目最多 |
| 高 | `docs/API_REFERENCE.md`、`docs-en/API_REFERENCE.md` — `mpu_should_trigger_ai` |
| 中 | `docs-jp/DEVELOPER_GUIDE.md`、`docs-jp/API_REFERENCE.md` |
| 低 | 目錄結構圖（若有列出 `chat/` 子目錄） |
