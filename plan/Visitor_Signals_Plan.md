# 訪客脈動與 AI 爬蟲訊號實作計畫（Frieren 自發對話多樣性擴充）

此計畫從 `atlant-security` 借鑑「會產生事件/訊號」的功能切片，補進 mp-ukagaka 的自發對話素材源，目的是**增加 Frieren `auto_talk` 的對話多樣性**，而**非**把 mp-ukagaka 變成半套安全外掛。

---

## ✅ 施作進度摘要 (2026-04-27)

- **狀態**：實作完成（待實機驗證）；已根據 reviewer 回饋修正三項實質問題；已自行修正 sleep mode 與深夜訊號的人格衝突
- **PHP lint**：所有變更與新增檔案 `php -l` 皆通過
- **JSON 結構**：dynamics / prompts / weights / sleep_mode 四檔皆通過 `json.load` 驗證
- **核心變更**：
  1. 擴充 `includes/llm/llm-slimstat.php`：新增 AI 爬蟲偵測與訪客脈動查詢函式群
  2. 擴充 `includes/integrations/akismet-integration.php` 的 `/check-spam-event` REST handler，新增兩個事件分支
  3. 擴充 `ghost/Frieren/` 的 dynamics / prompts / weights / sleep_mode 四個 JSON
  4. 新增兩個 abilities tools 給 LLM 主動查詢使用
  5. 新增 sleep mode 共用 helper `mpu_pick_sleep_dream_line()`，讓事件推送在睡眠時段短路到夢話池（避免人格設定矛盾，詳見 [💤 Sleep mode 分支](#-sleep-mode-分支避免人格設定矛盾)）

### Reviewer 回饋修正 (2026-04-27)

第一輪 reviewer 指出三個實質問題與一個措辭問題，皆已修正：

1. **foreign_visitor 會被冷卻吃掉** — `mpu_detect_visitor_pulse_event()` 原本在偵測階段就 `set_transient('mpu_visitor_pulse_seen_countries', ...)`，但若冷卻仍在則不會真的播報，新國家被誤標為已看過、之後永遠不再播報。
   - **修法**：detect 改為純查詢，回傳值多帶一個 `commit_on_success.seen_countries` 欄位；新增 `mpu_visitor_pulse_commit_seen_countries()` 由 REST handler 在 LLM 成功生成後才呼叫。Handler 順序也改為「先檢查冷卻、再呼叫 detect」，省掉冷卻中的無謂查詢。
2. **abilities 權限過鬆** — 兩個新 ability 原本 `permission_callback => true`，搭配 `show_in_rest => true` 會讓 Core Abilities API 的 REST 端點無認證即可被外部直接打。原文件以為 `mpu_get_mcp_tools_for_llm()` 的 admin 守門能擋下，但那只擋「LLM 看不看得到工具定義」，不擋外部 REST 呼叫。
   - **修法**：兩個 ability 的 `permission_callback` 改為 `current_user_can('manage_options')`，與 `Wp_Bot_Blocker_Ability` 一致。
3. **weights.json 的 `is_foreign_visitor` / `is_late_night` 不會生效** — `prompt-categories.php` 沒有通用 context_adjustments 讀取邏輯，只硬編碼 `is_bot` 特判（位於 `prompt-categories.php:266`）。新增的兩個 context block 是死配置。
   - **修法**：從 `weights.json` 移除這兩個 context block 避免誤導；保留 `base_weights` 與 `is_bot` 內的新類別權重（這些路徑會生效）。
4. **標題計數錯誤** — 「三種新訊號」實列四種，已改為「四種新訊號」。

### Reviewer 回饋修正（第二輪） (2026-04-27)

第二輪 reviewer 確認前一輪三個實質問題皆已修到位、無同等級 blocking 問題；剩下三點為前端 debug 分流與文件一致性，皆已收尾：

1. **前端 console log 會把新 action 誤標為 Akismet** — `js/ukagaka-core.js` 的 `mpu_checkSpamEvent()` 內，原本 `bot_alert / turnstile_block / bot_blocker_alert` 之外的 action 全落入 else 分支顯示為 Akismet spam 事件，且 `res.spam_count` 對新 action 是空的。
   - **修法**：明確新增 `ai_crawler_alert`（顯示 crawler / company）與 `visitor_pulse_alert`（顯示 pulse_type）兩條 else if；`spam_alert` 從 fallback 改為明確分支；保留一條「未分類 action」fallback 以防未來新增 action 時忘記分流。
2. **步驟紀錄與修正說明矛盾** — 步驟 4「weights.json 加類別權重 + 兩個 context_adjustments」與後續修正描述衝突（已說明 `is_foreign_visitor` / `is_late_night` 不會生效並移除）。
   - **修法**：步驟 4 改寫為明確列出生效的權重（`base_weights` 新類別 + `is_bot` 內 `ai_crawler_reactions: 18`），並備註原本的兩個 context_adjustments 後於步驟 6 移除。
3. **睡眠時段描述與實作不符** — 多處寫成固定 23–07 / 23:00–07:00，但 `mpu_is_deep_sleep_time()` 預設 `deep_sleep_start=0` / `deep_sleep_end=6`，再加可變 oversleep。`late_night_visitor` 在 0–4 確實會撞到，核心判斷沒錯，但描述不精準。
   - **修法**：相關描述改為「Frieren 的 deep sleep window（預設 00:00–06:00，可加 oversleep 延伸）」，並指向 `includes/llm/llm-context-builder.php:363` 的實作位置。

### CODEX 安全審查修正 (2026-04-27)

CODEX 對本次變更與相鄰路徑做安全審查，未發現接管級漏洞（無未授權改設定 / RCE / 任意檔案寫入）。三項發現中兩項屬必修：

1. **High：`mpu_get_client_ip()` 可被 spoofed header 繞過 rate limit**（`includes/core/utility-functions.php:753`） — 原實作只檢查 `candidate IP` 是否為公開 IP，但攻擊者偽造的 IP **本來就是公開 IP**（別人的）。對策應該是檢查 `REMOTE_ADDR`：只有當連線來自本機/CDN（REMOTE_ADDR 為 private/reserved）才信任 forwarded header。受影響的是公開 LLM endpoint 的 rate limit，可被燒爆 OpenAI/Gemini/Claude 額度。
2. **Medium：`/visitor-info` 同源風險** — `class-mpu-rest-dialog.php:296` 用同一個 helper 以 IP 反查 Slimstat（country / city / referer）。攻擊者若知道或猜中曾出現過的 IP，可探詢該 IP 的隱私資料。
3. **Medium/Low：`Wp_PostViews_Ability` 仍是 public** — 經討論後判定為「公開內容性質」（熱門文章排名類似 sitemap），與本次新增的 visitor pulse / AI crawler abilities（站點營運元資料）性質不同，**暫不修改**。

#### 設計：分流而非全域替換

直接把 `mpu_get_client_ip()` 改成 strict 會悄悄改變所有依賴它的功能行為（CDN 環境下訪客 country 顯示會半失效）。改採分流：

| 函式 | 用途 | 行為 |
|---|---|---|
| `mpu_get_client_ip()`（保留現行） | 顯示給訪客自己看、log 追蹤、context enrichment | 盡可能識別真實使用者 IP |
| `mpu_get_client_ip_strict()`（**新增**） | rate limit、用 IP 反查 DB、IP 黑名單比對 | REMOTE_ADDR 須為 private/reserved 才信 forwarded header；否則用 REMOTE_ADDR |

`mpu_get_client_ip_strict()` 邏輯與既有 `includes/integrations/bot-blocker-integration.php` 的 `mpu_bb_get_ip()` 一致。

#### 變更檔案

| 檔案 | 變更 |
|---|---|
| `includes/core/utility-functions.php` | (1) 新增 `mpu_get_client_ip_strict()`（在 `mpu_get_client_ip()` 之後）(2) `mpu_check_rate_limit()` 切到 strict 版 (3) `mpu_enforce_rate_limit()` 的 debug log 同步切到 strict（與計數鍵一致） |
| `includes/rest/class-mpu-rest-dialog.php` | `get_visitor_info()` 切到 strict 版（用 `function_exists` 守門以容錯舊版） |

#### CDN 取捨提醒

Cloudflare 等 CDN 後面的網站，REMOTE_ADDR 是 CDN 出口 IP（公開 IP），strict 版會回傳 CDN IP 而非真實使用者 IP。對 rate limit 等於「CDN 出口共用配額」，可能誤殺合法使用者；但這比被 spoof 燒爆 API 額度可接受。完整解法是建立可信 proxy/CDN IP 白名單（admin 設定），列為**未來擴充**。

#### 不在本次處理

- **ZIP persona 上傳的解壓總大小上限**（admin-only 操作，嚴重度低）
- **Wp_PostViews_Ability 權限收緊**（產品意圖決定，安全角度非必修）

---

### 自我發現修正：Sleep mode 衝突 (2026-04-27)

第一輪 reviewer 通過後，作者自行發現一個更深層的人格設定衝突：

- **問題**：`late_night_visitor` 訊號的觸發時段（0–4 點）完全落在 Frieren 的 deep sleep window（預設 00:00–06:00，可加 oversleep 延伸到更晚）內，**100% 撞時段**。事件推送繞過 `mpu_common_msg()` 的睡眠檢查，會讓「設定上在睡覺的 Frieren」突然開始用 LLM 分析國際情勢，人格全垮。AI 爬蟲訊號雖然不必然撞睡眠時段，但深夜觸發時也不應喚醒；依新版世界觀它屬於低風險的「同族」氣息，不該沿用 bot / 魔族夢話池。
- **修法**：生成函式進入時先檢查睡眠狀態：
  - AI 爬蟲：睡著 → 直接跳過低優先度事件，**不呼叫 LLM、不抽夢話**
  - 訪客脈動：睡著 → 抽 `sleep_mode.json` 的 `visitor_dreams`，**不呼叫 LLM**
  - 醒著 → 走原本 LLM 路徑
- 詳見 [💤 Sleep mode 分支](#-sleep-mode-分支避免人格設定矛盾) 章節。

---

## 🎯 設計原則

### 「借資料、不借行為」

從 `atlant-security` **借**：
- AI 爬蟲 UA 對照表（19 個簽名，純資料）

**不借**：
- 任何 `init` priority 1 的攔截行為
- WAF / IP 封鎖 / Rate Limit / 加密模組
- atlant-security 自有的 events 資料表

### 「不重做既有功能」

| 既有 | 為何不重做 |
|---|---|
| Visitor Log + GeoIP | Slimstat 的 `wp_slim_stats` 已記錄 IP / country / browser / browser_type / referer，重做 = 重複寫入 |
| Honeypot 偵測 | `includes/integrations/bot-blocker-integration.php` 已實作 7 層防護（Cookie 陷阱 / IP 黑名單 / Hot-transient / Rate Limit / UA 異常 / Slimstat REST 攔截 / JS Beacon），事件已透過 `moelog_bot_blocker_event` transient 推送並由 `mpu_generate_mbb_reaction_llm()` 消費 |
| 一般 bot 偵測 | `mpu_check_recent_bot_visit()` 已存在，透過 `browser_type=1` 查詢 |

### 「兩條路徑都鋪」

mp-ukagaka 已有兩種對話觸發機制，新訊號兩條都接：

- **事件推送（被動，主目的）**：`/check-spam-event` REST endpoint 被前端 auto_talk 計時器輪詢，偵測到訊號 → 抽 dynamics 模板 + prompts 參考 → 呼叫 LLM → 寫 checksum → 回傳台詞 → 設冷卻
- **Abilities tool（主動，加值）**：LLM 在跟訪客對話時自行呼叫工具拉資料當話題

---

## 📐 四種新訊號

### 1. AI 爬蟲來訪 `ai_crawler_alert`

- **偵測**：查 `wp_slim_stats` 過去 60 秒內 `browser_type=1` 的記錄，將 `user_agent` 與 `browser` 欄位用 `preg_match` 對照 `mpu_get_ai_crawler_definitions()` 的 19 個 UA pattern
- **冷卻**：30 分鐘（`mpu_ai_crawler_cooldown` transient）
- **支援識別**：GPTBot / ChatGPT-User / OAI-SearchBot / Google-Extended / ClaudeBot / Claude-Web / anthropic-ai / CCBot / Bytespider / PerplexityBot / Amazonbot / Meta-ExternalAgent / Applebot-Extended / cohere-ai / AI2Bot / Diffbot / YouBot / ImagesiftBot / Omgilibot
- **角色設計呼應**：Frieren 是 AI 角色，遇到 AI 爬蟲（同類）會帶複雜情感，這是這個訊號最有戲的地方

### 2. 罕見國家來訪 `visitor_pulse_alert / foreign_visitor`

- **偵測**：查過去 60 分鐘的 `country` 欄位 distinct 清單，與 transient `mpu_visitor_pulse_seen_countries`（24h TTL，最多 50 國）比對；發現新國家觸發
- **冷卻**：60 分鐘（`mpu_visitor_pulse_cooldown` transient）
- **去重設計**：detect 函式**不寫入** seen 清單，只回傳 `commit_on_success.seen_countries` 候選；REST handler 在 LLM 成功生成台詞後才呼叫 `mpu_visitor_pulse_commit_seen_countries()` 寫入。這是為了避免「冷卻中偵測命中卻被誤標 seen → 永遠不再播報」的 race（reviewer 第 1 點修正）。

### 3. 流量峰 `visitor_pulse_alert / traffic_spike`

- **偵測**：過去 1 小時不重複人類 IP（`browser_type != 1`） vs 上一個 1 小時，倍率 ≥ 1.5 且當前 ≥ 5
- **冷卻**：與訪客脈動共用 60 分鐘冷卻（同一 transient key）

### 4. 深夜訪客 `visitor_pulse_alert / late_night_visitor`

- **偵測**：伺服器時區 `current_time('G')` 在 0–4 之間，過去 30 分鐘人類訪客 ≥ 1
- **冷卻**：與訪客脈動共用 60 分鐘冷卻

> 訊號優先序：罕見國家 → 流量峰 → 深夜訪客（在 `mpu_detect_visitor_pulse_event()` 內依序 return）

---

## 💤 Sleep mode 分支（避免人格設定矛盾）

Frieren 的 `sleep_mode.json` 有 `enabled: true`，當 `mpu_is_deep_sleep_time($personality_id)` 命中時（`includes/llm/llm-context-builder.php:363` 起；deep_sleep_start / deep_sleep_end 由 sleep settings 決定，預設 00:00–06:00，並可由 `oversleep_enabled` 把賴床時段延伸到更晚）會走「夢話模式」由 `mpu_common_msg()` 直接抽 sleep_mode.json 字串，**不呼叫 LLM**。但事件推送走 `/check-spam-event` REST，繞過該機制——若不處理，會出現「Frieren 設定上在睡覺、但事件推送照樣產生清醒對話」的矛盾。

特別是 `late_night_visitor` 必然在 0–4 觸發，**完全落在 deep sleep window 內**，100% 會撞上。

### 解法：事件生成函式進入時先處理睡眠狀態

新增共用 helper `mpu_pick_sleep_dream_line($personality_id, $category, $merge_basic = true)`（位於 `includes/personality/personality-prompts.php`）：

- 檢查 `mpu_is_deep_sleep_time($personality_id)` 與 sleep_mode.json `enabled`
- 命中時從指定子類別 + `basic` 池合併隨機抽一條，附 `<!-- mpu-sleep -->` 標記
- 未命中（醒著或未啟用）回 `false`，呼叫端走原本 LLM 路徑

### 訊號 → 睡眠時段映射

| 訊號 | 醒著 | 睡著 |
|---|---|---|
| `ai_crawler_alert` | LLM + `ai_crawler_report` 模板 | 直接跳過（低優先度事件，不播報） |
| `visitor_pulse_alert / foreign_visitor` | LLM + `visitor_pulse_report.foreign_visitor` 模板 | 抽 `visitor_dreams`（新增） |
| `visitor_pulse_alert / traffic_spike` | LLM + `visitor_pulse_report.traffic_spike` 模板 | 抽 `visitor_dreams` |
| `visitor_pulse_alert / late_night_visitor` | （不會發生，必在睡眠時段） | 抽 `visitor_dreams`（**主要路徑**） |

### 副效益

- **節省 API 成本**：睡眠時段直接抽預設池，不打 LLM
- **人格一致性**：Frieren 在睡覺就是該說夢話，不是分析國際情勢
- **與既有 `mpu_common_msg()` 行為對稱**：marker 一致，前端 `ukagaka-core.js:99` 與 `frieren.js:861` 已支援檢測

### 既有訊號（spam / mbb / bot_alert）為何不一併處理

這些訊號的人格模板（魔族侵入、防衛魔法）在「醒著時觸發」與「睡著時觸發」差異不像深夜訪客那麼極端，且改動會破壞既有行為。本次只處理新訊號；若未來要全面套用，helper 已就位，呼叫端各自加幾行即可。

---

## 📁 變更檔案清單

### 新增

| 檔案 | 用途 |
|---|---|
| `includes/mcp-tools/abilities/class-visitor-pulse-ability.php` | 註冊 `mp-ukagaka/get-visitor-pulse` ability |
| `includes/mcp-tools/abilities/class-ai-crawler-ability.php` | 註冊 `mp-ukagaka/get-recent-ai-crawlers` ability |
| `plan/Visitor_Signals_Plan.md` | 本文件 |

### 修改

| 檔案 | 變更內容 |
|---|---|
| `includes/llm/llm-slimstat.php` | 新增 5 個函式：`mpu_get_ai_crawler_definitions()` / `mpu_check_recent_ai_crawler($seconds)` / `mpu_get_recent_ai_crawlers($seconds, $limit)` / `mpu_get_visitor_pulse($minutes)` / `mpu_detect_visitor_pulse_event()` |
| `includes/integrations/akismet-integration.php` | (1) `mpu_rest_check_spam_event()` 在 bot_alert 分支後追加 AI 爬蟲與訪客脈動兩個分支 (2) 新增 `mpu_generate_ai_crawler_reaction_llm()` 與 `mpu_generate_visitor_pulse_reaction_llm()` 兩個 LLM 生成函式 |
| `includes/mcp-tools/manager.php` | `$abilities` 陣列追加兩個新類別 |
| `ghost/Frieren/dynamics.json` | 新增 `ai_crawler_report`（7 條平坦陣列）與 `visitor_pulse_report`（按 type 分子鍵：`foreign_visitor` / `traffic_spike` / `late_night_visitor` 各 5 條） |
| `ghost/Frieren/prompts.json` | 新增 `ai_crawler_reactions`（6 條）與 `visitor_pulse_reactions`（6 條）對話池 |
| `ghost/Frieren/weights.json` | base_weights 新增 `ai_crawler_reactions: 1` / `visitor_pulse_reactions: 2`；`is_bot` context 新增 `ai_crawler_reactions: 18`。⚠️ 不另增 context_adjustments：原計畫的 `is_foreign_visitor` / `is_late_night` 因 `prompt-categories.php` 沒有通用 context 讀取（只硬編碼 `is_bot`），無法生效，已移除（reviewer 第 3 點修正） |
| `ghost/Frieren/sleep_mode.json` | 新增 `visitor_dreams`（8 條夢話），給訪客脈動三個子訊號在睡眠時段共用；AI 爬蟲睡眠時段直接略過，不使用夢話池 |
| `includes/personality/personality-prompts.php` | 新增共用 helper `mpu_pick_sleep_dream_line($personality_id, $category, $merge_basic)` |

---

## 🔌 整合點細節

### REST 事件分支順序（`mpu_rest_check_spam_event`）

既有 → 新增的執行順序：

1. spam（既有）
2. moelog_bot_blocker（既有）
3. bot_alert（既有）
4. **ai_crawler_alert**（新增，30 分鐘冷卻）
5. **visitor_pulse_alert**（新增，60 分鐘冷卻）
6. fallback `has_event => false`

每個分支都會：
- 檢查冷卻 transient
- 設新冷卻
- 呼叫對應 `*_reaction_llm()` 生成台詞
- 呼叫 `mpu_record_conversation('auto_talk')`
- 呼叫 `mpu_store_spam_event_checksum()` 寫聊天 checksum
- 呼叫 `mpu_debug_log()` 記錄事件

### LLM 生成函式設計

兩個新函式 `mpu_generate_ai_crawler_reaction_llm()` 與 `mpu_generate_visitor_pulse_reaction_llm()` **完全比照** 既有 `mpu_generate_mbb_reaction_llm()` 的模式：

1. 取 `$mpu_opt`、provider、language、personality_id
2. 用 `mpu_build_optimized_system_prompt()` 建 system prompt
3. 從 `dynamics.json` 取模板（變數替換用 `mpu_replace_single_prompt_variables()`）
4. 從 `prompts.json` 取參考台詞做 `【参考セリフ】`
5. 拼 `【状況】` + `【指示】` + `【参考セリフ】` + `【制約】` 的 user prompt
6. 呼叫 `mpu_call_ai_api()`
7. `mpu_filter_thinking_content()` 過濾思考標籤
8. 用 `mpu_get_personality_max_response_length()` 限制長度

訪客脈動的特殊處：`visitor_pulse_report` 在 dynamics.json 是按 type 分子鍵，函式內依 `$event['type']` 抓對應子陣列；若不存在會 fallback 到平坦陣列模式以保留向後相容。

### Abilities tool 權限

兩個新 ability 的 `permission_callback` 為 `current_user_can('manage_options')`，與既有 `Wp_Bot_Blocker_Ability` 一致。

> ⚠️ 設計決策變更（reviewer 第 2 點修正）：原本比照 `Wp_PostViews_Ability` 用 `return true;`，並假設 `mpu_get_mcp_tools_for_llm()` 的 admin 守門器足以保護資料。但那只擋「LLM 看不看得到工具定義」，不擋外部直接打 Core Abilities API 的 REST 端點。訪客脈動含流量、地理分佈等聚合元資料，對攻擊者有偵察價值，故收緊為 admin 限定。

---

## 🔍 驗收 / 偵錯觀察點

### Transient 鍵名

| 鍵 | TTL | 用途 | 寫入時機 |
|---|---|---|---|
| `mpu_ai_crawler_cooldown` | 30 min | AI 爬蟲事件冷卻 | 進入分支立即寫 |
| `mpu_visitor_pulse_cooldown` | 60 min | 訪客脈動事件冷卻 | 進入分支立即寫 |
| `mpu_visitor_pulse_seen_countries` | 24 h | 已播報國家清單（最多保留 50 國） | **僅在 LLM 成功生成 message 後寫**（避免冷卻吃掉新國家的問題） |

### Debug log prefix

啟用 `WP_DEBUG_LOG` 後可在 log 中搜尋：

- `AI Crawler:` — AI 爬蟲事件觸發、LLM 失敗、**睡眠時段跳過低優先度事件**
- `Visitor Pulse:` — 訪客脈動事件觸發、LLM 失敗、**睡眠時段抽 visitor_dreams 替代 LLM（含 type）**

### 手動觸發測試

```php
// 強制清冷卻後測試訪客脈動
delete_transient('mpu_visitor_pulse_cooldown');
delete_transient('mpu_visitor_pulse_seen_countries');
var_dump( mpu_detect_visitor_pulse_event() );
var_dump( mpu_get_visitor_pulse(60) );

// AI 爬蟲偵測（自己改 UA 模擬 ClaudeBot 後重新訪問，等 slimstat 寫入）
delete_transient('mpu_ai_crawler_cooldown');
var_dump( mpu_check_recent_ai_crawler(3600) );
var_dump( mpu_get_recent_ai_crawlers(3600) );

// Sleep mode 分支驗證（深夜時段執行）
//   - AI crawler：睡著時應直接跳過，不抽 bot_dreams
//   - visitor pulse：睡著時應回含 <!-- mpu-sleep --> marker 的字串
$pid = mpu_get_personality_id_from_ukagaka_name( mpu_get_option()['cur_ukagaka'] ?? 'default_1' );
var_dump( mpu_is_deep_sleep_time($pid) );
var_dump( mpu_pick_sleep_dream_line($pid, 'visitor_dreams') );
```

### REST 端點直接打

```
POST /wp-json/mp-ukagaka/v1/check-spam-event
```

回傳 `action` 欄位若為 `ai_crawler_alert` 或 `visitor_pulse_alert` 即代表新分支命中。

---

## ⚠️ 風險與取捨

1. **Slimstat 依賴**：所有訊號偵測都先 `class_exists('wp_slimstat')`，未啟用時靜默回傳空值，不會 fatal。但這也代表沒裝 Slimstat 的網站完全用不到新訊號。
2. **流量峰閾值**：`current >= 5 && current >= previous * 1.5` 是經驗值，小流量網站可能永遠觸發不到、大流量網站可能太頻繁。後續可調或移到 option。
3. **罕見國家 transient 失效**：`mpu_visitor_pulse_seen_countries` 24h TTL；若 object cache flush 或 transient 被外部清，可能重新播報已知國家。風險可接受（最差情況是 Frieren 重提一次）。
4. **時區**：深夜判定用 `current_time('G')`（站點時區）。這是有意的——「深夜」應該用站長/讀者的時區體感，而非 UTC。
5. **AI 爬蟲遺漏**：UA 表是固定清單，新 AI 爬蟲（例如 2026 年後出現的）需手動加進 `mpu_get_ai_crawler_definitions()`。比對是 `preg_match` 大小寫不敏感，已盡量寬容。
6. **冷卻共用**：訪客脈動三個子訊號共用 `mpu_visitor_pulse_cooldown`，意味著「剛報完罕見國家，1 小時內就算遇到流量峰也不會講」。這是有意的反囉嗦設計。
7. **LLM 失敗的副作用**：進入分支會立即寫冷卻 transient，即使 LLM 後續失敗，冷卻仍計入。foreign_visitor 因有 commit_on_success 機制，下次冷卻過後仍可重試該國家；其他訊號則直接吃掉這 30/60 分鐘。與既有 spam / mbb / bot_alert 行為一致，未調整。
8. **睡眠時段不寫 chat history checksum 的選擇**：當前實作**有**寫 checksum（與醒著路徑一致），所以 sleep marker `<!-- mpu-sleep -->` 也會進 chat history。理論上若該歷史日後被當 LLM context，模型會看到 marker——但既有 `mpu_common_msg()` 也是同樣行為，前端 `ukagaka-core.js:99` / `frieren.js:861` 已能處理。風險可接受。
9. **Sleep mode 行為差異**：本次只處理新訊號（ai_crawler / visitor_pulse），既有 spam / mbb / bot_alert 在睡眠時段仍會走 LLM 清醒對話。helper 已就位，未來若要全面套用，呼叫端各自加 3–5 行即可（見 [Sleep mode 分支](#-sleep-mode-分支避免人格設定矛盾) 章節結尾）。

---

## 🛠️ 實作步驟執行紀錄

### 步驟 1：研究既有架構 — ✅ 已完成
- 確認 mp-ukagaka 既有 5 個對話觸發訊號（spam / bot_blocker / bot / akismet 內路徑）
- 確認 `wp_slim_stats` schema（ip / country / browser / browser_type / user_agent / referer / dt）
- 確認 abilities API 註冊機制（`Manager::init` + glob 載入）

### 步驟 2：擴充 llm-slimstat.php — ✅ 已完成
- 加入 19 個 AI 爬蟲 UA 對照表
- 加入 5 個查詢函式（單筆爬蟲偵測 / 多筆爬蟲聚合 / 訪客脈動 / 訊號偵測）

### 步驟 3：接入事件推送 — ✅ 已完成
- 在 `mpu_rest_check_spam_event` 加 2 個事件分支
- 新增 2 個 LLM 生成函式（共 ~250 行，比照既有 `mpu_generate_mbb_reaction_llm()`）

### 步驟 4：擴充人格對話池 — ✅ 已完成
- dynamics.json 加 `ai_crawler_report` + `visitor_pulse_report`
- prompts.json 加 `ai_crawler_reactions` + `visitor_pulse_reactions`
- weights.json 加類別權重 + `is_bot` 內的 `ai_crawler_reactions: 18`（原本另加 `is_foreign_visitor` / `is_late_night` 兩個 context_adjustments，後於步驟 6 移除——`prompt-categories.php` 沒有通用 context 讀取，這兩塊不會生效）

### 步驟 5：新增 abilities tools — ✅ 已完成
- 兩個 ability class（admin 限定，`permission_callback` 為 `current_user_can('manage_options')`）
- 註冊到 `Manager::$abilities`

### 步驟 6：Reviewer 回饋修正 — ✅ 已完成
- 將 `mpu_detect_visitor_pulse_event()` 改為純查詢；新增 `mpu_visitor_pulse_commit_seen_countries()`
- REST handler 改為「先檢查冷卻、後呼叫 detect」，並在 LLM 成功後 commit seen
- 兩個 ability 權限收緊為 `manage_options`
- 移除 weights.json 內無法生效的 `is_foreign_visitor` / `is_late_night` context

### 步驟 7：Sleep mode 分支 — ✅ 已完成
- 解決 `late_night_visitor` 與 Frieren deep sleep window 必然衝突的問題
- 新增 `mpu_pick_sleep_dream_line()` 共用 helper（personality-prompts.php）
- AI 爬蟲生成函式在睡眠時段直接跳過低優先度事件
- 訪客脈動生成函式在睡眠時段短路到 `visitor_dreams`
- sleep_mode.json 新增 `visitor_dreams` 池（AI 爬蟲不使用夢話池）

---

## 🔮 後續可能擴充（暫未實作）

- **referer 訊號**：來自特定社群（HN / Reddit / 微博）或搜尋引擎的訪客，可變成獨立訊號類別
- **時段對比**：「今天比上週同時段多／少」
- **device / language 訊號**：slimstat 也有 platform / language，可衍生「行動裝置佔比突然暴增」等訊號
- **訊號閾值移到 admin 設定**：流量峰倍率、冷卻時間等讓站長可調
- **日語以外的 reactions 對話池**：目前 `prompts.json` 的新對話池為日文（與既有風格一致），未來多語言時可同步擴充

---

## 📝 給 Reviewer 的快速理解路徑

如果只看 4 個檔案最快：

1. `includes/llm/llm-slimstat.php` 的 `mpu_get_ai_crawler_definitions()` 到檔案結尾 — 看訊號偵測邏輯與 commit/detect 拆分
2. `includes/integrations/akismet-integration.php` 的 `mpu_rest_check_spam_event` 中段（bot_alert 之後）— 看事件推送整合點；以及兩個 `*_reaction_llm()` 函式開頭的 sleep mode 處理（AI crawler 跳過、visitor pulse 抽夢話）
3. `includes/personality/personality-prompts.php` 的 `mpu_pick_sleep_dream_line()` — 看睡眠夢話池抽取共用邏輯
4. `ghost/Frieren/dynamics.json` 的 `ai_crawler_report` 與 `visitor_pulse_report` 區塊；以及 `ghost/Frieren/sleep_mode.json` 的 `visitor_dreams` — 看對話模板、夢話池、變數約定（`{crawler_name}` / `{company}` / `{purpose}` / `{country_name}` / `{current}` / `{previous}` / `{ratio}` / `{count}` / `{hour}`）

### 三種行為路徑速查表

| 訊號 | 醒著且 LLM 可用 | 醒著但 LLM 失敗 | 睡眠時段 |
|---|---|---|---|
| `ai_crawler_alert` | LLM 生成（dynamics + prompts） | 跳過分支，下次冷卻過再試 | 跳過低優先度事件，不播報 |
| `visitor_pulse_alert` | LLM 生成（按 type 分模板） | 同上；foreign_visitor 不 commit seen，下次仍可偵測 | 抽 `visitor_dreams` + `basic` 池 |
