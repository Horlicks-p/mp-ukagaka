# MP Ukagaka 版本歷史

> 📋 所有版本的更新記錄

---

## [2.18.0] - 2026-05-18

### 🧪 測試基礎建設（PHPUnit + verify pipeline）

- **新增 PHPUnit 單元測試**：建立 `tests/` 目錄與最小 WordPress mock bootstrap（`tests/bootstrap.php`），讓純函式測試不必啟動完整 WordPress 即可運行。初始 6 個測試套件涵蓋 `chat-integrity`（filter / checksum / slice）、加密 round-trip、input role 解析、session event envelope、template 渲染與新增的 chat lock — 合計 22 tests / 51 assertions。
- **Composer-based 工具鏈**：dev 依賴（`phpunit/phpunit ^9.6`、`brain/monkey ^2.6`）寫在 `tools/php/composer.json`，vendor 隔離在 `tools/php/vendor/`。外掛本身沒有 runtime composer 依賴。
- **`npm run verify` 串接**：`tools/node/package.json` 現在依序執行 `lint:php` → `build` → `test:php`。build script 在 minify 失敗時改回非 0 exit code，CI 不會再假通過。
- **PHPUnit `cacheResult="false"`**：避免在受限 sandbox 中嘗試寫入 `.phpunit.result.cache` 時觸發 permission warning。
- **filter / action mock 改為真正執行 callback**：`tests/bootstrap.php` 現在會按 priority 排序、依 `accepted_args` 截取參數。未來測 `mpu_chat_integrity_mode` 之類的 filter 才會可靠。

### 🔒 Chat Lifecycle Lock（並發 LLM 防護）

- **新增 `MPU_Chat_Lock` 類別**（`includes/llm/class-mpu-chat-lock.php`）：用 `add_option($key, $payload, '', 'no')` 作為 atomic check-and-set primitive。沒有用 transient 是因為 `get_transient()` + `set_transient()` 在並行請求下不是 atomic，會讓 lock 本身產生它要防的 race。
- **過期 lock retry 流程**：`add_option()` 失敗時若現有 lock 已過期，會先 `delete_option()` 再嘗試一次 acquire。崩潰的 PHP worker 留下的 stale lock 會在下一個請求週期自我修復。
- **token 驗證的 release**：`release($session_id, $token)` 用 `hash_equals()` 比對 token，避免某個請求誤釋放別人持有的 lock。是 double-finally bug 的防禦層。
- **60 秒 TTL、走 `mpu_chat_lock_ttl` filter**，範圍 clamp 到 `[10, 300]` 秒。
- **三個 action hook**：`mpu_chat_lock_acquired` / `mpu_chat_lock_released` / `mpu_chat_lock_conflict`，可用於 metrics、audit log，或未來 approval-hub 整合。
- **只接在 `/chat/user` 與 `/chat/user-stream`**：`/chat/greet`、`/chat/context` 與 `/debug_mcp` 故意不鎖（既有路由 rate limit 已足夠）。
- **衝突回 HTTP 429**：直接用既有的 `$this->fail()` envelope，前端錯誤路徑不需要改動。
- **SSE 安全的 release**：lock 取得後立刻 `register_shutdown_function()`，stream loop 在 chunk 之間透過 `exit_if_stream_aborted()` 檢查 `connection_aborted()`。客戶端中途斷線時 lock 仍會釋放，token 驗證確保 shutdown fallback 不會誤殺後續請求的 lock。
- **lock context 記錄** `route`、`input_role` 與 `ip_hash`（`sha256(client_ip)` 前 12 字元）— 不存原始 IP 即可協助 triage。

### 🧹 REST Chat 重複消除

- **新增 `prepare_auto_chat_context()` helper**（`MPU_REST_Chat`）：把 `chat_context()` 與 `chat_greet()` 共用的設定（`ai_enabled` / `ai_greet_first_visit` 預檢、provider + API key、`wp_info`、ukagaka 身分、language、personality、time context、13 個 variable map、解析後的 system prompt）集中起來。
- **`require_first_visit_greeting` 選項 flag** 用來區分 `chat_greet` 的額外檢查 — 不需要拆成兩個 helper。
- **response shape 完全不變**。checksum 寫入（`store_after_auto` 的 `'context'` / `'greet'` kind）刻意不動。`prepare_user_chat_args()`、chat lock、SSE 流程都不受影響。

### 🏷️ SSE Stream 狀態 Badge（runtime 驗收 UI）

- **`#ukagaka_msgbox` 右上角的可見 `.mpu-state-badge`**：在 2.17.0 已加的 `data-mpu-stream-state` 屬性之上加可見層。沒有新的 state machine，只是把屬性視覺化。
- **六個可見狀態**：`thinking`、`streaming`、`tool`、`error`、`timeout`、`busy`。`status` 為避免空 badge 而對應到與 `streaming` 同一個 label。
- **chat lock 衝突時的 `busy` 狀態**：`handleStreamFailure()` 從 SSE 的 JSON fallback path 偵測 `error.code === "mpu_chat_lock_busy"` 或 `error.data.status === 429`，顯示在地化的「混雑中…」訊息而非通用錯誤。
- **第一個 delta 時設為 `streaming`**：`onDelta` 不再清除屬性，改為 `setStreamState("streaming")`，文字串流期間 badge 仍會持續顯示。
- **走既有 `mpuL10n` 機制做 i18n**：新增的 `mpuL10n.streamStates` map（`考え中…` / `応答中…` / `調べてる…` / `エラー` / `タイムアウト` / `混雑中…`）可透過既有 `.po` / `.mo` workflow 翻譯。

### 🐛 錯誤修正

- **Ollama 空陣列 Tool Calls 解析修正**：修復 Ollama 在回傳不帶參數的 tool call（如 `get-bot-blocker-stats`）時，PHP `json_decode` 與 `json_encode` 會將空物件 `{}` 轉為空陣列 `[]`，導致 Ollama 引擎拋出 `"Value looks like object, but can't find closing '}' symbol"` 錯誤的問題。現已透過 `mpu_normalize_ollama_assistant_message()` 強制將空陣列轉為 `stdClass` (`{}`)，並新增 5 個 PHPUnit 測試案例涵蓋所有邊界狀況。
- **Provider HTTP 錯誤日誌強化**：遇到 HTTP >= 400 錯誤時，現在會嘗試提取 Provider JSON 的 `error` 欄位以提供更乾淨的錯誤訊息，並透過 `error_log` 匯出包含 tool_calls 結構與 4KB response tail 的完整除錯資訊。
- **UI 狀態 Badge 位置微調**：修正 `.mpu-state-badge` 樣式，使其更貼合對話框右上角內側，避免被裁切。

### ✅ 驗證

- `npm run verify`：PHP lint、bundle build、PHPUnit（22 tests / 51 assertions）全綠。
- `js/dist/ukagaka-bundle.js` 與 `.min.js` 已重建（169.7 KB → 79.0 KB）。
- `git diff --check`：無 whitespace error。

---

## [2.17.0] - 2026-05-15

### 🛡️ Input Role Resolver 與伺服器端 Tool Gate

- **新增 `MPU_Input_Role` 類別**（`includes/core/class-mpu-input-role.php`）：把 LLM 輸入身分（`admin` / `system` / `subscriber` / `visitor`）從 WordPress capability 切分出來。`resolve()` 從 request context 與當前 WP 狀態解析角色；`can_use_ability()` 採用 hardcoded whitelist，確保 prompt injection 抵抗力。
- **Request-scoped input context**（`request-state.php`）：新增 `mpu_set_request_input_context()` / `mpu_get_request_input_context()`，讓 chat endpoint、tool 曝光與 tool 執行三處看到同一份 context。
- **兩段式 tool gate**（`abilities-integration.php`）：`mpu_get_mcp_tools_for_llm()` 在送進 LLM 前按角色過濾工具列表，`mpu_execute_mcp_tool()` 在執行前再驗證一次以攔截 out-of-band 工具呼叫。內建 ability（`visitor-pulse`、`ai-crawler`、`wp-postviews`、`wp-bot-blocker` ×3）全部改用 `MPU_Input_Role::current_can_use_ability()`，並保留 `current_user_can('manage_options')` 安全 fallback。

### 📡 Session Event Envelope（SSE）

- **新增 `MPU_Session_Event` 類別**（`includes/llm/class-mpu-session-event.php`）：定義事件類型（`stream.delta / status / done / error`、`tool.request / result`、`nonce.refresh`），並以 `eventId + ts + kind + payload` 結構包覆 payload。
- **向後相容的 SSE**：`streaming-helpers.php` 透過 `kind_for_legacy_event()` 把 legacy 名稱轉成新 kind，再包進 envelope。既有端點不需修改即可運作。
- **前端 dispatcher**（`js/ukagaka-chat.js`）：`window.MPU_EVENTS` 常數 + `mpuNormalizeSseEvent()` 自動辨識新 envelope 與 legacy event，switch 同時處理兩種型式。

### ⏱️ Client-side SSE Watchdog（zombie state 防護）

- **45 秒逾時** + `AbortController.abort()`：45 秒內沒收到任何 SSE event 即 abort 串流、從 `mpuChatHistory` rollback 未送出的 user message、恢復輸入框，並透過 `mpuShowBalloon()` 顯示在地化逾時訊息。
- **統一的 `onEvent` hook**：所有 legacy / 新 envelope event 都會重置 watchdog，避免 per-handler 漏寫導致的 bug。
- **Typewriter 安全**：`stream.done` 與 `stream.error` 在 `onEvent` 層清除 watchdog。後端完成但前端 typewriter 仍在吐字時不會誤殺。
- **`data-mpu-stream-state` 屬性**掛在 `#ukagaka_msgbox`：暴露 `thinking / tool / status / timeout / error` 給 CSS 主題或 debug 使用。
- **`tool.request` handler**：未來後端發 `tool.request` 時，前端會顯示「工具執行中…」狀態。

### 🔐 Chat Integrity 三段模式

- **新增 `chat_integrity_mode` 選項**（預設 `audit`）：`audit` 只 log mismatch、不中斷請求（既有行為）；`warn` 在 log 上以 WARN 標示並觸發 `mpu_chat_integrity_mismatch` action hook；`block` 在 expected checksum 存在且 actual 不符時回 `WP_Error`（HTTP 409）。
- **三個控制點**：常數 `MPU_CHAT_INTEGRITY_MODE` > option `chat_integrity_mode` > filter `mpu_chat_integrity_mode`。expected checksum 缺失時一律放行 — `block` 模式也不會在首次請求或 session reset 時誤殺。
- **`mpu_chat_integrity_should_block` filter**：可在 `block` 模式下進行 per-session 放行或灰度測試。
- **`mpu_chat_integrity_mismatch` action hook** 在 audit / warn / block 三個模式全部觸發，payload 為 `{ session_id, expected, actual, mode, decision, source, history_count }` — 即使在 `audit` 模式也能掛 metrics 收集或 webhook。
- **REST chat 正確傳遞 WP_Error status**：`class-mpu-rest-chat.php` 改成讀取 `error_data['status']`（409），不再硬編碼 400。
- **最小切口**：完全不動 store / normalize / slice 流程，只改 verify mismatch 的決策層。

### 🛠️ Hardening Patches（從 2.16.1–2.16.4 收編）

- **後台 output escaping 全面稽核**。
- **Bot-blocker 掃描器豁免**：對 security scanner 完整豁免、對已登入請求略過 rate-limit，避免錯誤的 reputation flag；抽出 `mpu_bb_is_logged_in_request()` 共用 helper。
- **`MPU_PLUGIN_DIR` 修正**：將未定義的常數參照改為 `plugin_dir_path(MPU_MAIN_FILE)`。

### ✅ 驗證

- 所有觸動的 PHP 檔案皆通過 `php -l`（`chat-integrity.php` / `chat-history-service.php` / REST chat / `core-functions.php` / ability 群）。
- JS 語法檢查通過（`ukagaka-chat.js` / `dist/ukagaka-bundle.js`）。
- Bundle 已重新建構（`js/dist/ukagaka-bundle.js` + `.min.js`）。
- `git diff --check` 無 whitespace error。

---

## [2.16.0] - 2026-05-13

### 🧠 User Memory MVP

- **新增 `/remember` 管理員指令**：管理員可在前台聊天框輸入 `/remember`，從最近 20 則對話歷史萃取穩定的管理人事實並保存到 `mpu_user_memory` usermeta。
- **新增 Memory REST Controller**：新增 `MPU_REST_Memory` 與 `POST /memory/extract`，使用 `manage_options` 權限、WordPress REST nonce、60 秒 transient 節流，以及 defensive cleanup。
- **記憶注入 system prompt**：`mpu_resolve_system_prompt()` 會把保存的記憶注入為「管理人についての記憶（参考メモ）」，並明確標示為參考資訊、不可解讀為指令，降低 prompt injection 風險。
- **後台 AI 設定頁記憶管理**：AI tab 底部新增記憶卡片，可查看目前 facts、最後更新時間，並透過 nonce-protected 表單清除目前管理員的記憶。

### ✅ 發版品質與 Runtime Info 改善

- **新增發版驗證工具鏈**：`npm run lint:php` 補掃主外掛檔，並新增 `npm run verify` 串接 PHP lint 與 JS build。
- **新增 REST smoke test checklist**：新增 `docs/REST_SMOKE_TEST.md`、`docs-en/REST_SMOKE_TEST.md` 與 `docs-jp/REST_SMOKE_TEST.md`，涵蓋 baseline、session token、token enforcement、chat round-trip、admin guard 與 SSE headers。
- **GitHub 自動更新支援**：內建 Plugin Update Checker v5.6（`vendor/plugin-update-checker/`），新增 `includes/updater/github-updater.php`。外掛現在會偵測新的 GitHub Release，並在 WordPress 後台顯示更新通知。每次發版時上傳 `mp-ukagaka.zip` 作為 release asset 即可啟用一鍵更新。
- **Runtime Info 微調**：調整天氣溫度觸發閾值，並在 Frieren `instructions.md` 補上感情觸發規則，避免過度反應但提升角色細節。
- **i18n debt cleanup**：補齊繁中與日文翻譯，更新 `mp-ukagaka-zh_TW.po/.mo` 與 `mp-ukagaka-ja.po/.mo`。

---

## [2.15.0] - 2026-05-08

### 🔒 安全與防濫用硬化

- **公開 AI REST 端點加上 Session Token 防護**：新增匿名訪客專用的 IP-bound session token，`/chat/context`、`/chat/greet`、`/chat/user`、`/chat/user-stream` 均需帶有效 token 才能請求，降低外部直接打 API 造成 quota 消耗的風險。
- **Session Token 採 lazy-fetch**：前端不再把 token 直接嵌入 HTML，改由 `/session-token` 端點在首次請求前取得，並設定 `no-store` 快取標頭，避免 full-page cache 洩漏第一位訪客的 token。
- **前端請求統一注入 token**：`mpuFetch` 與 `mpuFetchSSE` 會自動帶入 `X-MPU-Session-Token`，並加入 helper 存在性防護，讓一般 REST 與 SSE 流程一致。
- **Raw JavaScript 擴充權限收緊**：`extend[js_area]` 的儲存與顯示需具備 `unfiltered_html` 權限，避免只有 `manage_options` 但不應輸出 raw JS 的環境誤開前端腳本注入能力。
- **Personality ZIP 覆蓋流程硬化**：ZIP 先解壓到暫存目錄，覆蓋前加入二次確認、backup→rename→rollback 流程、保留 ID 大小寫不敏感保護，以及 `realpath()` + `DIRECTORY_SEPARATOR` 邊界檢查，降低 Zip Slip、symlink 逃逸與錯誤覆蓋風險。

### 🧱 結構整理與維護性改善

- **Chat history/checksum 邏輯抽出**：新增 `MPU_Chat_History_Service`，集中處理 session id、history parsing、checksum verify/store，以及 user-chat / streaming 回覆後的 integrity 更新。
- **Admin save handler 拆分**：`mpu_handle_options_save()` 拆出通用設定、偽春菜設定、AI、LLM、日記與 Bot Blocker 等保存 helper，降低單一函式承擔過多分支的維護成本。
- **保留既有 response shape**：REST 路由、錯誤碼、SSE event 名稱與 checksum 行為維持向後相容，這次以小步驟硬化為主，不做大規模重構。

### ✅ 驗證與文件

- **JS bundle 已重建**：同步更新 `js/dist/ukagaka-bundle.js` 與 `js/dist/ukagaka-bundle.min.js`。
- **語法與建置驗證**：已通過 PHP lint 與 Node build 驗證。
- **新增硬化計畫文件**：新增 `plan/Code_Quality_Hardening_Plan.md`，記錄安全風險評估、分階段改善順序、實作摘要與 post-review fixes。

---

## [2.14.1] - 2026-04-30

### ✨ 管理人個人化設定後台化

- **後台欄位新增**：在 **通用設定** 頁面新增「Admin full nickname」、「Admin short name」、「Admin birthday」三個欄位（`options_general.php`），管理人的暱稱、簡稱和生日改為直接在 WordPress 後台設定，不再需要手動編輯 `personality.md` 和 `calendar.json`。
- **System Prompt 自動注入**：`core-functions.php` 新增 `mpu_get_admin_profile_prompt_block()`，在 System Prompt 渲染時自動將後台設定的 `{{admin_nickname}}`、`{{admin_name}}`、`{{admin_birthday}}` 替換為真實值，並附加覆寫指示段落。
- **calendar.json 動態覆蓋**：`personality-prompts.php` 新增邏輯，當後台設定了生日時，自動移除 `calendar.json` 中的 `"MM-DD"` 佔位符並以實際生日替換，無需手動修改檔案。
- **生日格式驗證**：`admin-functions.php` 新增 `mpu_normalize_admin_birthday()`，在儲存時強制驗證並標準化為 `MM-DD` 格式。

### 🎌 節日期間（Holiday Periods）支援

- **新增 holiday_periods 機制**：在 `calendar.json` 中支援特定期間的節日定義，系統會自動判斷當天日期是否落在指定期間內並觸發對應的節日反應。
- **內建節日期間**：
  - **ゴールデンウィーク**（4/29–5/5）：黃金週期間角色會有專屬的節日反應
  - **お正月**（1/1–1/7）：新年期間角色會有專屬的節日反應
- **Prompt 與 Weight 支援**：在 `prompts.json` 和 `weights.json` 中新增對應節日期間的 prompt 類別和權重設定。

### 📖 文件更新

- **USER_GUIDE 追加 Abilities 說明**：在三語版本（繁中、英文、日文）的 USER_GUIDE 互動對話模式章節中，新增「Abilities」子章節，說明內建的 6 個 Abilities（人氣文章查詢、IP 封鎖、Bot Blocker 統計/清除、AI 爬蟲偵測、訪客脈動），附使用範例與所需外掛清單。
- **README 更新**：三語版 README 更新管理人設定說明，改為後台設定方式。
- **新增截圖**：`screenshot7.PNG` — Abilities 功能展示（角色以角色口吻回報 Bot Blocker 統計）。

---

## [2.14.0] - 2026-04-29

### LLM 串流支援擴充：Gemini / Claude

- **新增 Gemini streaming**：`gemini` provider 追加 `generate_chat_stream()`，使用 `streamGenerateContent?alt=sse` 端點，純文字回覆會以 SSE `delta` 逐步送回前端。
- **新增 Claude streaming**：`claude` provider 追加 `generate_chat_stream()`，支援 Anthropic Messages API 的 `content_block_start`、`content_block_delta`、`content_block_stop` 事件，並能累積 `input_json_delta` 完成 tool call 參數。
- **保留 tool call 安全性**：Claude 在啟用 MCP tools 時會先緩衝文字，若本輪發生 tool call，不會先輸出 pre-tool text，避免前端顯示內容、後端 checksum 與最終工具結果不一致。
- **Gemini tools fallback**：Gemini streaming 目前先支援純文字。偵測到 MCP tools 可用時會 fallback 到同步 `generate_chat()`，再以單一 `delta` 回傳結果，避免 tool 能力在 streaming 模式下靜默失效。

### Streaming 穩定性與前端顯示

- **共用 SSE parser**：新增 `mpu_stream_sse_events()`，統一處理跨 chunk 行合併、多行 `data:` 拼接、空行 dispatch，以及串流末尾未以空行結束時的最後 event flush。
- **Claude tool loop 防護**：Claude streaming 達到 `MPU_MAX_TOOL_TURNS` 時會回傳 `max_turns_exceeded`，不再以空白成功回應結束。
- **Claude tool 參數防護**：Claude streaming 解析 tool input JSON 失敗時會記錄 debug log，並以空參數交由工具回傳錯誤結果，避免使用不完整或錯誤參數執行。
- **前端串流打字佇列**：對話模式的 streaming 顯示改為局部 timer 與 pending queue，遵守後台 `typewriter_speed` 設定，並加入完成保護避免重複 finalize。
- **Bundle 已重建**：同步更新 `js/dist/ukagaka-bundle.js` 與 `js/dist/ukagaka-bundle.min.js`。

---

## [2.13.9] - 2026-04-29

### 🧹 死碼清除（第一、二階段）

移除所有確認在 PHP 與 JS source 中完全無 runtime 呼叫者的孤兒函數，無任何行為變更。

**PHP 移除（共 21 個函數，涵蓋 8 個檔案）：**

- `utility-functions.php`：`mpu_enforce_rate_limit`、`mpu_verify_ajax_nonce`、`mpu_input_filter`
- `ai-functions.php`：`mpu_call_gemini_api`、`mpu_call_openai_api`、`mpu_call_claude_api`、`mpu_call_ollama_api`、`mpu_get_allowed_conditional_tags`
- `chat-api-handlers.php`：`mpu_call_ollama_with_messages`、`mpu_call_openai_with_messages`、`mpu_call_claude_with_messages`、`mpu_call_gemini_with_messages`（統一入口 `mpu_call_ai_api_with_messages` 保留）
- `llm-context-builder.php`：`mpu_compress_context_info`、`mpu_get_context_label`（依賴不存在的 `mpu_load_personality_dynamics`）
- `llm-functions.php`：`mpu_get_ollama_settings`、`mpu_debug_system_prompt`
- `personality-prompts.php`：`mpu_get_dynamic_prompt_templates`
- `personality-decorations.php`：`mpu_get_personality_all_decorations`
- `personality-loader.php`：`mpu_is_frieren_personality`、`mpu_get_personality_trait`
- `weather-functions.php`：`mpu_get_weather_info`

**JS 移除（共 4 個函數，涵蓋 3 個 source 檔案）：**

- `ukagaka-base.js`：`mpu_init_visit_tracking`
- `ukagaka-core.js`：`mpu_hideMsgText`、`mpuMoe`
- `ukagaka-chat.js`：`mpu_escapeHTML`

---

## [2.13.8] - 2026-04-27

### ✨ 新功能：訪客脈動與 AI 爬蟲訊號

- **新增 AI 爬蟲偵測**：在 `llm-slimstat.php` 新增 AI crawler UA 對照表與查詢函式，能從 Slimstat 最近的 bot 記錄中辨識 GPTBot、ClaudeBot、Google-Extended、PerplexityBot 等 AI / LLM crawler。
- **新增 Visitor Pulse 訊號**：加入三種以 Slimstat 為資料源的訪客脈動事件：
  - `foreign_visitor`：最近 60 分鐘首次出現的國家
  - `traffic_spike`：最近 1 小時人類訪客相較前 1 小時顯著上升
  - `late_night_visitor`：深夜時段仍有真人訪客
- **新 auto-talk 事件分支**：`/check-spam-event` REST handler 新增 `ai_crawler_alert` 與 `visitor_pulse_alert` 兩條分支，讓 Frieren 可對這些站點訊號產生自發對話。
- **新 Abilities tools**：新增 `mp-ukagaka/get-recent-ai-crawlers` 與 `mp-ukagaka/get-visitor-pulse` 兩個唯讀工具，供 LLM 主動查詢近期 AI crawler 與訪客脈動摘要。

### 💤 人格一致性修正：Sleep mode 與事件推送整合

- **睡眠時段夢話分支**：新增 `mpu_pick_sleep_dream_line()` helper。當角色處於 deep sleep window（預設 00:00–06:00，並可延伸 oversleep）時，新事件不再呼叫 LLM，而是改從 `sleep_mode.json` 抽夢話。
- **新增 `visitor_dreams` 夢話池**：`ghost/Frieren/sleep_mode.json` 新增訪客脈動專用夢話；AI 爬蟲在睡眠時段視為低優先度事件，直接略過不播報。
- **避免人格矛盾**：修正深夜訪客事件在睡眠時段仍產生清醒分析台詞的問題，讓 sleep mode 與事件推送邏輯一致。

### 🔒 安全性與穩定性修正

- **修正 foreign visitor 去重時機**：`mpu_detect_visitor_pulse_event()` 改為純查詢，不再在偵測階段直接寫入 seen countries。只有在訊息成功生成後，才由 `mpu_visitor_pulse_commit_seen_countries()` 提交，避免新國家被冷卻「吃掉」後永遠不再播報。
- **Abilities 權限收緊**：兩個新 abilities 的 `permission_callback` 改為 `current_user_can('manage_options')`，避免透過 Core Abilities API REST 端點未授權讀取訪客脈動與 crawler 資訊。
- **強化 IP spoofing 防護**：新增 `mpu_get_client_ip_strict()`，並將 rate limit 與 `/visitor-info` 的 Slimstat 反查改用嚴格 IP 判定，避免攻擊者偽造 `X-Forwarded-For` / `CF-Connecting-IP` 繞過 LLM endpoint 配額或探詢任意 IP 的訪客資訊。
- **移除無效權重配置**：`weights.json` 保留有效的 `ai_crawler_reactions` / `visitor_pulse_reactions` 類別權重與 `is_bot` 調整，移除原先不會生效的 `is_foreign_visitor` / `is_late_night` context 配置。
- **前端事件分流補完**：`ukagaka-core.js` 為 `ai_crawler_alert`、`visitor_pulse_alert`、`spam_alert` 新增明確 log 分支，不再把新事件誤標成 Akismet spam。

---

## [2.13.7] - 2026-04-25

### 🐛 錯誤修正：Akismet 5.7 相容性 — AI 對話無法生成

- **根本原因**：Akismet 5.7 新增了 `akismet/get-stats` ability（WordPress Abilities API），其 input schema 使用了 JSON Schema 聯合型別：`type: ['object', 'null']`。mp-ukagaka 透過 `wp_get_abilities()` 收集所有已註冊的 ability 並作為 tool 定義送給 LLM，此聯合型別陣列導致整個 API 呼叫被拒絕——Gemini、OpenAI、Claude 均要求 `type` 為純字串（如 `"object"`），不接受陣列。
- **症狀**：啟用 Akismet 後 AI 對話完全無法生成，角色退回使用內建靜態對話。關閉 Akismet 後 AI 對話立即恢復正常。
- **修正**：在 `abilities-integration.php` 新增 `mpu_normalize_schema_for_llm()`，遞迴走訪 ability input schema，將聯合型別陣列（如 `['object', 'null']`）轉換為單一字串（取第一個非 null 型別），在送給任何 LLM provider 之前套用。

### 🐛 錯誤修正：SPA 模式下 gotop 按鈕被攔截

- **症狀**：當 WordPress 主題啟用 SPA（Single Page Application）模式時，偽春菜 dock 上的「回到頂部」（gotop）按鈕點擊事件會被 SPA 路由攔截，導致按鈕無法正常運作。
- **修正**：在 `frontend-functions.php` 為 `#toTop` 錨點加上 `data-spa-ignore` 屬性，並在 `ukagaka-features.js` 的點擊處理中加入 `e.stopPropagation()`，防止 SPA 框架攔截此錨點點擊。

---

## [2.13.6] - 2026-04-24

### 📖 文件更新：開發文件補完與多語同步

- **補齊昨天遺漏的開發文件更新**：完成 `API_REFERENCE.md`、`DEVELOPER_GUIDE.md`、`CANVAS_CUSTOMIZATION.md`、`DEBUG_SLIMSTAT.md`、`ABILITIES_API.md`、`GHOST_CREATE_GUIDE.md` 的全面校對與修正，讓文件內容與目前程式碼結構一致。
- **REST / Abilities / Personality 架構對齊**：
  - 將開發文件中過時的 AJAX、舊 hooks、舊全域變數與舊模組描述，統一更新為現行 REST controller 架構。
  - 明確區分 Abilities API 的對外概念與內部仍保留的 MCP 命名過渡層，避免文件與實作落差。
  - 將人格製作指南更新為以 `instructions.md + personality.md` 為主，並保留 `system_prompt.md` / `manifest.json.system_prompt` 的 legacy fallback 說明。
- **補齊目前實際支援的 personality / frontend 文件內容**：
  - 補上 `touchzones.json`、`sleep_mode.json`、`calendar.json`、`diary.json`、`emoji-keywords.json`、`scripts` 等現行結構。
  - 更新 canvas 裝飾系統、Slimstat/訪客資訊除錯流程、初始化資料與前端腳本載入說明。
- **三語文件同步**：繁中、英文、日文三套開發文件與 changelog 已同步更新，內容保持一致。

---

## [2.13.5] - 2026-04-23

### 📖 文件更新：USER_GUIDE 全面重構

- **三部分架構重組**：將使用者指南重新整理為三大部分，以使用情境為軸而非以設定分頁為軸：
  1. **基本設定**（有無 AI 皆適用）：包含安裝、偽春菜管理、自動對話、自定義表情系統等
  2. **AI 功能設定**（需要 AI 提供商）：包含 LLM 設定、頁面感知、互動對話模式、思考模式、天氣感知、自動日記
  3. **靜態對話功能**（不使用 AI 時）：包含外部對話檔案、會話設定、特殊代碼、擴展功能
- **自定義表情系統說明修正**：明確標注靜態對話和 AI 生成對話皆支援表情功能，並移至第一部分（原文件位置有誤）
- **各語言版本內容同步**：
  - 英文版（`docs-en/`）補上天氣感知功能、ZIP 上傳兩個原本缺少的章節
  - 日文版（`docs-jp/`）補上 ZIP 上傳章節
- **繁中版殘留日文標題修正**：修正繁中版中未翻譯的日文標題（`キャラクター設定` → `角色設定`、`トリガーページ` → `觸發頁面`、`AI会話の表示時間` → `AI 對話顯示時間` 等）
- **移除開發者技術細節**：將 35 個對話類別完整列表、PHP 動態權重程式碼、思考模式 PHP 片段等開發者內容從使用者指南移除（這些內容屬於 DEVELOPER_GUIDE）

---

## [2.13.4] - 2026-04-21

### 🐛 錯誤修正

- **API 金鑰欄位的瀏覽器自動填入問題**：LLM 設定頁面中 Gemini、OpenAI、Claude 三個 API 金鑰輸入欄（`type="password"`）的 `autocomplete="off"` 改為 `autocomplete="new-password"`。部分瀏覽器會忽略 `autocomplete="off"` 而注入已儲存的密碼，導致欄位顯示 `.......' 而非提示文字，已修正。
- **自訂模型輸入欄的使用者名稱自動填入問題**：為三個提供商的自訂模型文字輸入欄加入 `autocomplete="off"`。瀏覽器將密碼欄位旁的文字輸入誤判為帳號欄並自動填入 `admin`，已修正。
- **Gemini 2.5 Pro 的 thinking 區塊未處理**：Gemini 2.5 Pro 在實際回應文字之前，會在 `parts` 陣列中加入內部思考區塊（`"thought": true`）。原本的程式碼僅檢查 `parts[0].text`，當第一個 part 為 thinking 區塊時會解析失敗。已在 `generate_text`、`generate_chat`、`test_connection` 三處修正，改為跳過 `thought: true` 的區塊並取得第一個正常文字。
- **Gemini 2.5 Pro 測試連接發生 MAX_TOKENS**：測試連接請求的 `maxOutputTokens` 設為 `50`，被 Gemini 2.5 Pro 的內部 thinking（47 個 token）全部耗盡，導致實際回應無法輸出、`content.parts` 為空。已調整為 `200`。
- **移除 Gemini 2.5 Pro 預設選項**：從預設模型清單中移除 `gemini-2.5-pro`。由於其必要的 thinking 開銷在現有 token 預算下不穩定，仍可透過自訂模型輸入欄使用。

---

## [2.13.3] - 2026-04-20

### ✨ 增強：自訂模型選擇 & Claude 版本更新

- **自訂模型輸入**：在 **LLM 設定**頁面與**日記 AI 設定**頁面的 Gemini、OpenAI、Claude 三個提供商的模型選擇器中，新增「自訂模型…」選項。選擇後會顯示文字輸入框，可直接輸入任意合法的模型 ID，不再受限於預設清單。儲存方式與選擇預設選項完全一致，後端邏輯無需變更。
- **Claude 模型版本更新**：將 Claude 預設模型清單更新至最新版本：
  - Sonnet 4.5 → **Sonnet 4.6** (`claude-sonnet-4-6`)
  - Opus 4.5 → **Opus 4.7** (`claude-opus-4-7`)
  - Haiku 4.5 維持不變（`claude-haiku-4-5-20251001`，目前尚無 4.6 版本）

---

## [2.13.2] - 2026-04-16

### 🌐 本地化：系統訊息統一為日文

- **全面 i18n 整理**：橫跨 18 個 PHP 文件，將所有硬編碼的繁體中文系統訊息（約 200+ 條）統一替換為日文，確保使用者介面輸出語言一致。
  - `includes/core/`：檔案操作、安全驗證、速率限制錯誤訊息（`utility-functions.php`）；前端 UI 標籤與 `mpuL10n` 本地化字串（`frontend-functions.php`）；對話檔案錯誤訊息（`ukagaka-functions.php`）。
  - `includes/rest/`：所有 REST API 錯誤與狀態訊息（`class-mpu-rest-base.php`、`class-mpu-rest-chat.php`、`class-mpu-rest-dialog.php`、`class-mpu-rest-ghost.php`、`class-mpu-rest-test.php`、`class-mpu-rest-touch.php`）。
  - `includes/llm/`：所有 LLM 提供商錯誤訊息（`class-mpu-ai-provider-gemini.php`、`class-mpu-ai-provider-openai.php`、`class-mpu-ai-provider-claude.php`、`class-mpu-ai-provider-ollama.php`、`class-mpu-ai-provider-base.php`）；日記、Ollama 驗證與工具迴圈訊息（`diary-functions.php`、`llm-functions.php`、`tool-loop-guard.php`）。
  - `includes/admin-functions.php`：所有後台管理通知與 ZIP 上傳/驗證訊息。
- **擴充 `mpuL10n`**：在前端 JavaScript 物件中新增多個本地化 key（`loadingFailed`、`errorOccurred`、`duplicateRequest`、`requestFailed`、`securityVerificationFailed`、`animationLoadFailed`、`connectionError`、`chatExit`），JS 模組不再依賴硬編碼的備用字串。
- 所有字串仍以 `__()` 包裹，支援未來的 `.po`/`.mo` 語言包。

### 🐛 修復 (Bug Fix)

- **`mpuL10n` Key 名稱不一致**：`js/ukagaka-dialog.js` 引用了未定義的 `mpuL10n.loadFailed`（正確應為 `mpuL10n.loadingFailed`），導致靜默回退至內建字串，現已修正。
- **`chatExit` Key 未定義**：`js/ukagaka-chat.js` 引用的 `mpuL10n.chatExit` 從未在 `wp_localize_script` 中註冊，現已在 `frontend-functions.php` 補充此 key。

---

## [2.13.1] - 2026-03-18

### 🔒 安全性強化 (Security)

- **指令存取控制**：`/debug_mcp`、`/reset`、`/clear` 等管理員指令在非登入狀態下不再回傳系統訊息，改由 `dynamics.json` 的 `visitor_rejection` 提示詞引導角色以個性口吻拒絕，體驗與拒絕 MCP 工具呼叫完全一致。
  - 後端：`/debug_mcp` 非管理員請求改為 fall-through 進入一般 AI pipeline；非管理員 system prompt 補充 slash command 拒絕指示。
  - 前端：`mpuPreSettings` 新增 `is_admin` 旗標；`/reset`、`/clear` 加入 admin 判斷，非管理員不攔截，交由 AI 路徑處理。
- **IP 偽造防禦**：`mpu_get_client_ip()`（速率限制）與 `mpu_bb_get_ip()`（Bot Blocker 自動封鎖）對 proxy header（CF-Connecting-IP / X-Forwarded-For）的值加入公開位址驗證（`FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE`），防止攻擊者偽造私有/保留 IP 繞過速率限制，或觸發 auto-ban 誤封合法目標。

### 🐛 修復 (Bug Fix)

- **`fail()` 錯誤資訊遺失**：`class-mpu-rest-base.php` 的 `fail()` 方法新增選用第 4 參數 `$data`，Provider 回傳的 `http_status`、`raw_body` 等除錯欄位不再被靜默丟棄，管理員可直接在 REST response body 中查閱完整錯誤詳情。

---

## [2.13.0] - 2026-03-06

### 🧹 死碼清理 (Dead Code Cleanup)

- **移除未使用的函式與檔案**：移除長期未使用且已無前後端呼叫的廢棄程式碼，確保外掛輕量化與未來維護性。
  - 移除了 `includes/rest/chat/` 與 `includes/ajax/chat/` 目錄下多個已經由最新 OO 架構取代的過期檔案。
  - 移除了 `mpu_html_decode`、`mpu_is_browser`、`mpu_should_trigger_ai`、`mpu_ukagaka_list`、`mpu_get_default_msg`、`mpu_get_next_msg`、`mpu_get_msg_key`、`mpu_count_msg` 等過時函數，並同步由開發者文件中剃除參考。

---

## [2.12.5] - 2026-02-28

### 🚀 重大更新：統一歷史記憶與 SSE 穩定性強化

- **統一歷史記憶計畫 (Unified History)**：現在芙莉蓮能記得所有互動（自言自語、頁面感知、觸摸反應等）。透過實作「Synthetic User 錨點」技術，非對話式的回應現在也能完整保留在對話脈絡中，解決了 LLM 角色扮演斷層。
- **SSE 串流穩定性修復**：修復了 SSE 與 Checksum 之間多項長期導致 400 錯誤的漏洞。
  - **觀測模式**：將 Checksum 從硬性阻斷改為稽核觀測模式，提升長對話與不穩定網路下的體驗。
  - **診斷日誌**：新增 `logs/checksum-mismatch.log` 自動記錄前後端數據差異，便於精準排查。
  - **數據同步**：修復了 store 與 verify 兩端 slice 順序不對稱導致的驗證失敗問題。
- **前端架構轉型**：
  - **全域變數遷移**：將 `mpuChatHistory` 與 `mpuChatModeActive` 等核心狀態遷移至 `window` 物件，提升跨模組訪問穩定性。
  - **記憶容量翻倍**：對話歷史上限從 20 則提升至 40 則，完整包容所有互動事件。
  - **F5 重整偵測**：實作頁面生命週期管理，F5 重整時自動清空記憶與 Session，但在 SPA 內部跳轉時保留記憶。
- **UX 個別優化**：
  - 更新 `/reset` 指令回應，移除冗餘的中文字，保持角色口吻一致。
  - 改進 `mpuFetchSSE` 對 JSON 異常回報的處理，增加自動回退 (Fallback) 機制。

---

### 🚀 重大更新：SSE 串流輸出與打字機效果 (SSE Streaming)

- **伺服器發送事件 (SSE) 串流**：全面導入 `text/event-stream` 傳輸協定，實作即時串流輸出能力。現在 AI 的回應會以「打字機效果」即時呈現在網頁上，大幅改善了長回覆或思考型模型（如 Ollama）的等待體驗。
- **全新串流端點**：新增專屬 REST 路由 `/chat/user-stream`，支援與現行非串流路徑完全一致的安全驗證、率限制與對話完整度檢查。
- **標準化 SSE 協議**：設計了插件專屬的串流事件模型（`delta`, `status`, `nonce`, `done`, `error`），讓前端能精準掌握模型增量、工具執行狀態與連線安全刷新。
- **Provider 串流支援**：
  - **OpenAI**: 支援全功能串流，包含複雜的 `tool_calls` 分段增量組裝。
  - **Ollama**: 支援 JSON Lines 串流轉送，並完美整合思考標記過濾。
- **連線穩定性與防護**：整合了 `ignore_user_abort` 與 cURL 串流回呼機制，當使用者關閉對話或斷線時，系統能主動中斷上游 API 請求，節省資源並防止「幽靈說話」。

### 🏗️ 架構進階優化 (Architecture Upgrades)

- **AI 提供商工廠模式 (Factory Pattern)**：完成第二階段重構，所有 AI 提供商邏輯已完全歸併至 `MPU_AI_Provider_Factory` 進階架構，移除冗餘分支，提升後續擴充性。
- **工具呼叫迴圈防護 (Loop Detection)**：正式實裝迴圈簽名偵測系統，自動攔截 LLM 陷入「相同工具 + 相同參數」的連續重複請求，保障 API 帳單安全。

---

## [2.10.0] - 2026-02-26

### 🚀 重大更新：AI 提供商工廠模式與穩定性強化 (AI Provider Factory & Loop Guard)

- **AI 提供商工廠模式 (Factory Pattern)**：將原先 `ai-functions.php` 與 `chat-api-handlers.php` 中的供應商判斷邏輯，全面重構為物件導向的工廠模式。新增 `includes/llm/providers/` 目錄並實作 `MPU_AI_Provider_Factory`，讓新增如 DeepSeek 等新模型變得輕而易舉。
- **工具呼叫迴圈防護 (Tool Call Loop Detection)**：在多輪對話中實裝了強大的工具呼叫迴圈偵測機制 (`tool-loop-guard.php`)。透過參數 JSON 的雜湊簽名 (Signature)，當偵測到大語言模型連續兩次發出完全相同的工具請求時，會立即中斷執行並回傳 `tool_call_loop_detected`，大幅降低無效的 API 花費。
- **API 相容性包裝**：為確保舊版外掛架構相容，原有的 8 個 API 呼叫入口 (例如 `mpu_call_ai_api`) 皆已轉換為薄包裝 (Thin Wrappers)，底層改由最新的工廠類別處理。
- **穩定性優化**：修復了 `handle_api_error` 的參數問題；增加了 AI 提供商代號 (Slug) 的常規化防禦機制；並將 Gemini 預設模型切換為更新的系列。

---

## [2.9.2] - 2026-02-25

### 🚀 重大更新：REST OO 路由與架構重構 (REST OO Routing Refactoring)

- **物件導向路由結構 (Object-Oriented Routing)**：將 19 條 REST API 全面重構成物件導向架構，引入 `MPU_REST_Base` 基底類別，並按領域切分為 `Chat`, `Dialog`, `Ghost`, `Test`, `Touch` 五大專屬控制器，集中由 `bootstrap.php` 統一註冊，大幅提升路由維護性。
- **Provider 共用 Helper**：抽離 LLM 提供商共用邏輯（如統一的 JSON 編碼、工具結果格式化轉換）至 `provider-helpers.php`，減少重複代碼，並為後續實作工廠模式打好基礎。
- **工具呼叫迴圈防護**：全面強化 `includes/llm/ai-functions.php` 以及多輪對話的工具執行防護，常數化工具呼叫回合上限 (`MPU_MAX_TOOL_TURNS`)，避免模型陷入無限工具請求迴圈。
- **死碼清理與技術債整理**：徹底移除廢棄的程序性 REST 檔案 (`rest-init.php`, `rest-core.php` 等) 以及舊版的傳統 AJAX Handler，全面擁抱優雅的現代化路由結構。

---

## [2.9.1] - 2026-02-24

### 🛡️ 安全性與穩定性強化 (Security & Stability)

- **REST API 自動更新 Nonce 機制**：透過 `rest_post_dispatch` 自動更新老化期 (12-24小時) 的 Nonce，確保長時間開啟網頁後仍能正常請求不發生 403 錯誤。
- **欄位型別感知的 Sanitization**：優化後台設定的欄位過濾邏輯，區分純文字、HTML 與布林值，防止系統提示詞等允許 HTML 的欄位被過度消毒。
- **UTF-8 Safe JSON Encoding**：在 API 呼叫中加入 `JSON_INVALID_UTF8_SUBSTITUTE`，防止因畸形 UTF-8 字元導致 API 請求靜默失敗。
- **未匹配 Placeholder 安全清理**：在發送給 LLM 之前，自動清除未被替換的 `{{variable}}` 變數標籤，避免原始模板語法洩漏給模型。

### ✨ 新功能與改進

- **Cron 健康狀態追蹤**：透過 transient 記錄排程起訖與錯誤，並在後台日記設定頁面新增視覺化的排程狀態面板，讓管理員能隨時掌握自動發表功能的運作狀況與失敗原因。

---

## [2.9.0] - 2026-02-23

### 🚀 重大更新：REST API 全面遷移 (REST API Migration)

- **後端架構重構**：將所有 AJAX 端點從傳統的 `admin-ajax.php` 全面遷移至現代化的 **WordPress REST API** (`wp-json/mp-ukagaka/v1/`)。
- **模組化路由**：將單一的 AJAX 處理中樞拆分為多個模組化的 REST 控制器（如 `rest-init.php`, `rest-core.php`, `rest-chat.php`, `rest-touch.php` 等），大幅提升程式碼維護性與可讀性。
- **安全性升級**：
  - 登入驗證全面改用原生的 `X-WP-Nonce` header，並結合 `permission_callback`。
  - 嚴格綁定 `GET` (唯讀) 與 `POST` (狀態修改) 請求方法，防止未授權的狀態變更。
- **標準化錯誤處理**：全面採用 `WP_Error` 搭配原生 HTTP 狀態碼 (400, 401, 403, 429) 回應，前端更精確解析錯誤。
- **速率限制 (Rate Limit) 優化**：新增專屬於 REST 的速率限制機制 (`mpu_rest_check_rate_limit`)，超限時會回傳標準的 HTTP 429 及 `Retry-After` header。
- **Cookie 處理除錯**：修正 REST 脈絡中直接呼叫 `setcookie()` 的不穩定問題，改由 `WP_REST_Response::header` 安全派發 Cookie。
- **前端重構**：所有 JavaScript AJAX 呼叫 (透過 `mpuFetch`) 已完全去耦合 `admin-ajax.php`，統一改為請求 REST API，並精準處理 5xx 等網路重試邏輯。

## [2.8.3] - 2026-02-21

### 🚀 效能優化與架構重構 (Performance & Refactoring)

- **PHP 後端效能與結構優化**：
  - **O(1) 查表優化**：在 `personality-loader.php` 實作靜態反向對照表 (`static $name_map`)，將遍歷陣列的 O(n) 複雜度降為 O(1) 的 `isset()` 查表。
  - **請求層級快取**：在 `llm-slimstat.php` 實作 `$post_id_cache` 陣列，避免對同一 URL 重複進行高成本的 `url_to_postid()` 查詢。
  - **靜態資源快取合併**：優化 `prompt-categories.php` 的快取機制，使用 `$personality_id ?? '__default__'` 讓所有伺か人格都能受惠於靜態快取，減少跨請求的重複計算。
  - **字串處理優化**：提取 `mpu_normalize_for_similarity`，在迴圈前預先執行正規化，避免重複的 `preg_replace`；並在 `personality-prompts.php` 將多次對同一字串的天氣 Regex (`preg_match`) 合併為單次執行。
  - **迴圈合併**：在 `user-chat-handler.php` 將 4 個分開的 `foreach` 巧妙合併為 1 個單一迴圈，運用動態變數 (`$$flag`) 大幅減少重複代碼。

- **JS 前端效能優化**：
  - **O(n²) → O(n)**：將 `ukagaka-context.js` 和 `ukagaka-greeting.js` 中會造成陣列不斷移位的 `splice` 刪除迴圈，改寫為高效率的 `filter()` 配合數量計數器，大幅解決歷史紀錄很長時的運算效能瓶頸。
  - **jQuery 選擇器快取**：於 `ukagaka-core.js` 的 `mpu_nextmsg` 與 fallback 加入 `const $msgnum` 緩存 DOM 查詢，減少佈局抖動。

### 🛡️ 安全性與穩定性強化 (Security & Stability)

- **ZIP 上傳防護 (ZIP Bomb Mitigation)**：在 `admin-functions.php` 的 ZIP 處理流程中加入檔案數上限 (`1000` 個檔案) 的提早終止機制，有效防止惡意上傳超大數量小檔案導致伺服器記憶體耗盡 (DoS)。
- **安全的隨機數產生器**：使用 WordPress 內建且更安全的 `wp_rand()` 全面取代舊有的 `mt_rand()`。
- **避免遞迴當機 (Fatal Error)**：將 `mpu_recursive_rmdir` 抽取至全域範圍，不再與其他功能產生可能引發 Fatal Error 的巢狀包含衝突。

### 🔧 代碼清理與共用化 (Code Cleanup)

- **大量減少重複代碼**：大幅抽取散落的共用邏輯為獨立 Utility 函數，減少了數百行的重複程式碼：
  - PHP: `mpu_verify_ajax_nonce()`、`mpu_get_current_provider()`、`mpu_get_provider_api_key()`、`mpu_build_user_info_prompt()`。
  - JS: `mpu_isDeepSleepTime()`、`mpu_selectNextMessage()`、統整的 `_isDebug()` 條件。
- **Ollama 思考模型偵測**：改用靈活的 `array_filter + stripos`，取代原本又冗長又難維護的 `strpos(strtolower())` 鏈條。
- **目錄掃描簡化**：`personality-loader.php` 中使用 `glob()` 直接鎖定 `manifest.json`，省去了 `scandir` 附帶的 `.` / `..` 過濾與 `file_exists` 的檢查，邏輯更簡潔。

## [2.8.2] - 2026-02-16

- 新增：`mpu_country_code_to_name` 工具函數，用於將 ISO 3166-1 國碼轉換為完整國家名稱（優先使用 PHP intl 擴展）。
- 優化：在 LLM 上下文（打招呼、對話上下文、Prompt 變數）中，將訪客的國家代碼轉換為完整名稱（如 "JP" -> "日本"），提升 AI 回應的自然度。

## [2.8.1] - 2026-02-16

### 📝 System Prompt 載入機制重構

- **統一載入邏輯**：重構了所有 AJAX 處理器（`mpu_ajax_chat_context`, `mpu_ajax_user_chat`, `mpu_ajax_touch_zone_chat`, `mpu_ajax_decoration_chat`）的 System Prompt 載入方式，確保行為一致。
- **支援模組化 Personality 檔案**：
  - 新增支援將 `system_prompt.md` 拆分為 `personality.md`（角色背景）與 `instructions.md`（行為規範）。
  - 提供更靈活的角色設定管理方式。
- **UI 來源指示器**：
  - 在後台 AI 設定頁面的 System Prompt 區域新增來源指示器。
  - 明確顯示目前使用的是模組化檔案、Legacy 檔案、Manifest 設定還是後台 Textarea 內容。

### 🐛 錯誤修復

- **觸摸反應 System Prompt 修復**：修正了 `ajax-touch-handlers-llm.php` 中的函數名稱錯誤，解決了觸摸與裝飾品互動時無法正確載入角色專屬 System Prompt 的問題。

## [2.8.0] - 2026-02-15

### 🚀 重大更新：Abilities API (工具调用)

- **核心集成**：整合 WordPress Core Abilities API，賦予 AI 角色執行後端操作的能力。
  - 目前實作了 `get_popular_posts` 工具，AI 可查詢網站人氣文章。
  - **權限控制**：嚴格限制僅有管理員權限 (`manage_options`) 的使用者可觸發工具調用。
  - **訪客優化**：對於非管理員訪客，系統自動過濾工具定義，節省 Token 並提升安全性。

- **角色化拒絕回應**：
  - 當訪客嘗試請求執行特權操作（如查詢數據）時，AI 不會報錯，而是以角色口吻拒絕。
  - 在 `dynamics.json` 中新增 `visitor_rejection` 行為指導，確保拒絕方式多樣且符合人設（如芙莉蓮會說是「管理人的秘密」）。

### 🔒 安全性強化

- **全域 Nonce 驗證**：強化所有前端 AJAX 請求的安全性。
  - 修復了 `mpu_nextmsg`, `mpu_change`, `mpu_get_settings`, `mpu_extend`, `mpu_load_dialog` 等請求缺失 `mpu_nonce` 的問題。
  - 確保所有與後端的交互都經過嚴格的 Nonce 驗證，防止 CSRF 攻擊。

- **Token 節省與優化**：
  - 對於無法使用工具的訪客，系統不會將大量工具描述發送給 LLM，顯著減少 Token 消耗。
  - 避免 LLM 嘗試調用工具後被後端攔截的無效交互迴圈。

## [2.7.0] - 2026-02-12

### 🛡️ 外部外掛連動 (Plugin Integrations)

- **Akismet + Turnstile 整合**：若已安裝 Akismet 或 Turnstile，當外掛執行攔截動作時，偽春菜將會觸發對應的反應對話。
  - **冷卻機制**：實作獨立的反應冷卻時間（30 分鐘），避免對話過於頻繁。

### 🤖 BOT 檢知功能

- **即時機器人偵測**：偵測搜尋引擎爬蟲或惡意機器人的訪問。
  - 與人格系統（`bot_detection`）深度整合，觸發專屬的警報對話。
  - 基於 Slimstat 數據，提供更精準的檢測邏輯。

### 🔧 優化與調整

- **自動對話優先權優化**：優化了垃圾留言與 BOT 檢測的優先順序處理。
- **LLM 提示詞擴充**：在 `dynamics.json` 和 `prompts.json` 中新增了針對垃圾留言與 BOT 的專屬提示詞。

---

### 🚀 效能優化

- **前端 JS 合併與壓縮**：7 個 JS 檔案合併為單一 bundle
  - HTTP 請求減少 87.5%（8→1）
  - 檔案大小減少 64.5%（160KB→60KB）
  - 使用 Terser 進行 minification
  - 支援 `SCRIPT_DEBUG` 開發模式切換
  - 新增 `npm run build` 建置指令

### ✨ 新功能

- **API 快取系統**：減少重複 API 請求和費用
  - 基於 WordPress Transient API 實作
  - 可配置 TTL（30分鐘～24小時）
  - 後台設定介面（LLM 設定頁面）
  - 快取統計顯示與一鍵清除功能
- **自動日記功能**：AI 可自動撰寫日記式文章
  - 根據最近瀏覽資料生成內容
  - 支援自訂標題前綴和發布設定
  - 整合人格系統的日記提示詞

### 🔧 代碼重構

- **AJAX Chat Handler 模組化**：拆分 `ajax-chat-handlers-llm.php`
  - `context-handler.php`：頁面感知對話
  - `greet-handler.php`：首訪問候
  - `user-chat-handler.php`：互動對話
  - 原檔案從 1036 行精簡至 18 行載入器
- **表單處理架構統一**：整合分散的處理器至 `admin-functions.php`
  - 將 LLM 設定和日記設定處理邏輯整合至 `mpu_handle_options_save()`
  - 單一入口點，符合 WordPress 最佳實踐

### 📝 文檔更新

- 更新 `DEVELOPER_GUIDE.md` 目錄結構
- 新增 API 快取系統說明

---

## [2.5.2] - 2026-01-11

### ✨ 新功能與改進

- **天氣感知功能**：角色現在可以透過 Open-Meteo API 感知天氣狀況
  - 使用免費的 Open-Meteo API，無需 API Key
  - 天氣設定可在後台進行配置
  - 支援根據天氣狀況調整對話內容

- **睡眠功能**：新增睡眠模式
  - 角色可在指定時段進入睡眠狀態
  - 睡眠時間可透過 `manifest.json` 的 `sleep_settings` 設定
  - 支援深層睡眠時間和賴床（oversleep）設定

- **芙莉蓮人格強化**：
  - 預設角色芙莉蓮的 `system_prompt.md` 獲得強化
  - `prompts.json` 擴充，可說出更多不同種類的台詞
  - 提升對話多樣性和角色扮演品質

- **觸摸互動功能**：
  - 新增觸摸區域（touch zones）功能
  - 角色可對不同身體部位的觸摸做出反應（頭部、臉部、胸部、腿部等）
  - 透過 `touchzones.json` 進行設定
  - 每個區域可定義獨立的反應對話

- **表情符號擴充**：
  - 追加更多表情符號種類
  - 讓角色表達更加豐富多樣

- **新增飾品**：
  - 為芙莉蓮新增兩種飾品：「暗黒竜の角」（暗黑龍之角）與「服だけ溶かす薬」（只溶衣服的藥）
  - 點擊飾品可觸發相關對話

- **代碼重構**：
  - 改進代碼結構與可維護性
  - 優化模組組織

---

## [2.4.0] - 2026-01-03

### 🚀 重大更新：JSON 人格系統

- **Personality 系統**：基於 JSON 的角色配置系統
  - 新增 `ghost/` 資料夾（類似偽春菜的 ghost 資料夾），每個角色可擁有獨立的配置檔案
  - 支援 `manifest.json`（元數據）、`prompts.json`（靜態對話類別）、`dynamics.json`（動態模板）、`weights.json`（類別權重）、`decorations.json`（裝飾物配置）、`emoji-keywords.json`（表情關鍵字）
  - 每個角色可包含專屬的 JavaScript 檔案（如 `frieren.js`）
  - 類似傳統偽春菜的 SHIORI DLL 架構，無需修改 PHP 程式碼即可定義角色人格

- **新增模組**：
  - `personality-loader.php`：Personality 系統載入器，提供 JSON 檔案讀取和快取機制
  - `emoji-mapper.php`：表情映射與情緒分析模組，根據對話內容自動選擇對應表情

- **前端功能擴展**：
  - `ukagaka-chat.js`：聊天功能前端實現
  - `ghost/Frieren/frieren-emoji.js`：Frieren 專屬表情系統前端（RO 風格，僅在 Frieren 人格時載入）

- **架構改進**：
  - `prompt-categories.php` 完全整合 Personality 系統
  - 動態提示詞、權重配置、統計映射均可從 JSON 檔案載入
  - 向後兼容：如果 Personality 系統不可用，自動回退到舊的行為

- **模組載入順序優化**：
  - `personality-loader.php` 在 `prompt-categories.php` 之前載入（必需）
  - `emoji-mapper.php` 在 AJAX 處理器之前載入（必需）

---

## [2.3.1] - 2025-12-30

### 🔧 改進與修復

- **術語統一**：將所有「春菜」更新為「偽春菜」
  - 更新所有 PHP 檔案中的 UI 文字、註釋和訊息
  - 更新中文文檔（USER_GUIDE.md、DEVELOPER_GUIDE.md、README.md）
  - 確保與日文「伺か」術語一致

- **互動對話模式改進**：
  - 退出對話模式後，自動對話延遲 5 秒再恢復
  - 避免對話剛結束時角色立即說話的突兀感

- **裝飾品點擊動畫**：
  - 點擊裝飾品時新增 fade-out/fade-in 動畫效果
  - 與點擊 OK 按鈕取得下一句對話的視覺效果一致
  - 提升整體 UX 流暢度

- **深夜模式**：
  - 新增深夜時段（02:00~06:00）專用對話情境
  - AI 會根據深夜時段調整對話風格和內容

---

## [2.3.0] - 2025-12-27

### 🚀 重大更新：互動對話模式

- **互動對話模式**：將「更換春菜」按鈕改造為即時對話介面
  - 訪客現在可以直接與角色聊天
  - 維持對話歷史以提供上下文回應
  - 可滾動對話區域，長對話自動滾動
  - 輸入框固定在底部，訊息在上方滾動

- **動態上下文注入**：智慧 token 優化
  - WordPress 統計資訊（文章數、留言數、PHP 版本、外掛數量等）僅在使用者查詢中偵測到相關關鍵字時才加入 System Prompt
  - 大幅減少大多數對話的 token 使用量
  - 支援繁體中文、日文、英文關鍵字
  - 關鍵字範例：文章、コメント、comment、php、wordpress、外掛、plugins、主題、theme 等

- **思考模式（預設啟用）**：提升 AI 回應品質
  - **預設行為變更**：支援的模型（Qwen3、DeepSeek）現在預設啟用思考模式
  - **獨白模式**：`ai-functions.php` 中設定 `think = true`，AI 先思考再回覆
  - **會話模式**：`chat-api-handlers.php` 中同樣啟用思考，context window 擴大至 8192 tokens
  - **分離機制**：思考過程與回覆完全分離，只顯示回覆給使用者
  - **品質提升**：回答更精準，特別是會話模式
  - **行為差異**：
    - **之前**：think = false，直接回答，回答較快但可能不準確，思考內容可能混入回覆
    - **現在**：think = true，先思考再回答，回答更精準，思考與回覆分離，只顯示回覆
  - **支援模型**：Qwen3（如 qwen3:8b）、DeepSeek（如 deepseek）等自訂模型
  - **可設定**：可在後台 LLM 設定中透過「關閉思考模式」選項停用

- **角色人格一致性**：改進角色扮演
  - 修正 System Prompt 變數渲染（`{{ukagaka_display_name}}`）
  - 預設 System Prompt 現在明確強調角色扮演：「你必須完全以這個角色的身份說話和行動，絕對不要以 AI 或語言模型的身份回應」
  - 對話模式使用與獨白模式相同的後台 System Prompt，確保一致性

- **代碼重構**：更好的組織結構
  - 將 `ajax-handlers.php` 拆分為 `ajax-handlers.php` 和 `chat-api-handlers.php`
  - 將多輪對話 API 函數（`mpu_call_ai_api_with_messages`、`mpu_call_ollama_with_messages` 等）移至專用模組
  - 改善代碼可維護性和組織結構
  - 移除過多代碼註解，保持代碼庫整潔

- **回應長度控制**：優化 AI 回應
  - 將 AI 回應 token 限制從 200 提升至 300 tokens
  - 應用於所有 AI 提供商（Ollama、Gemini、OpenAI、Claude）

- **UI 改進**：
  - 對話模式滾動條樣式，自訂顏色（`rgba(30, 58, 138, 0.5)`）
  - Flexbox 佈局，更好的訊息區域管理
  - 改進歡迎訊息的國際化（使用 `wp_json_encode()` 安全傳遞翻譯字串）

- **錯誤修復**：
  - 修正獨白模式和對話模式之間的 `ollama_disable_thinking` 鍵值不一致問題
  - 修正頁面感知 AI 與對話模式的衝突（對話模式中自動跳過頁面感知 AI）
  - 修正對話模式中歡迎訊息的翻譯（「有什麼想聊的嗎？」）
  - 修正日文歡迎訊息為芙莉蓮口調（「何か話したいことある？」）

- **語言包更新**：
  - 新增對話模式相關的翻譯字串
  - 更新中英日三種語言的翻譯

---

## [2.2.0] - 2025-12-19

### 🚀 重大更新：通用 LLM 接口

- **多 AI 提供商支援**：統一接口支援四大 AI 服務
  - **Ollama**：本機/遠程免費 LLM（無需 API Key）
  - **Google Gemini**：支援 Gemini 2.5 Flash（推薦）、Gemini 1.5 Pro 等
  - **OpenAI**：支援 GPT-4.1 Mini（推薦）、GPT-4o 等
  - **Claude (Anthropic)**：支援 Claude Sonnet 4.5、Claude Haiku 4.5、Claude Opus 4.5
  - 所有提供商使用統一的設定介面，可隨時切換

- **API Key 加密存儲**：所有 API Key 自動加密儲存，確保安全性
- **連接測試功能**：為所有 AI 提供商新增連接測試按鈕

### 🧠 System Prompt 優化系統

- **XML 結構化設計**：採用 XML 標籤組織 System Prompt，提升 LLM 理解效率
  - `<character>`：角色名稱和核心設定
  - `<knowledge_base>`：壓縮後的 WordPress 資訊
  - `<behavior_rules>`：行為規則（must_do、should_do、must_not_do）
  - `<response_style_examples>`：70+ 個對話範例
  - `<current_context>`：當前情境資訊

- **上下文壓縮機制**：自動壓縮 WordPress、用戶、訪客資訊，減少 token 使用
- **芙莉蓮風格範例系統**：內建 70+ 個實際對話範例，涵蓋 12 個類別
  - 問候類、閒聊類、時間感知類、觀察思考類
  - 魔法研究類、技術觀察類、統計觀察類、回憶類
  - 管理員評語類、意外反應類、BOT 檢測類、沉默類

- **雙層架構設計**：
  - **System Prompt**：定義角色風格、行為規則和對話範例
  - **User Prompt**：每次對話的具體任務指令（與範例類別對應）

### 🎨 UI/UX 全面升級

- **統一卡片式設計**：所有設定頁面採用一致的卡片式佈局
- **動漫風格配色**：參考芙莉蓮網站設計，採用柔和漸層背景
  - 卡片背景：`#E8F4F8`（淡藍綠色）
  - 邊框顏色：`#B8E6E6`（淺青色）
  - 標題顏色：`#4A9EBD`（藍綠色）
  - 文字顏色：`#2C3E50`（深藍灰色）

- **兩欄式佈局**：主設定頁面採用主內容 + 側邊欄設計
  - 主內容寬度：55%
  - 側邊欄寬度：300px（固定）
  - 側邊欄包含：AI Provider 連結、文檔連結、一般連結

- **自訂滾動條樣式**：為長文字區域（System Prompt 等）添加美觀的滾動條

### 🔧 功能改進

- **頁面認識機能整合**：將「頁面認識機能」設定移至 LLM 設定頁面
  - 統一管理所有 LLM 相關設定
  - 與「使用 LLM 取代內建對話」功能整合

- **AI 設定頁面簡化**：專注於「頁面感知」功能
  - 保留：言語設定、キャラクター設定、頁面感知確率、トリガーページ、AI会話の表示時間、初回訪問者への挨拶
  - 移除：AI 提供商選擇、API Key 設定、模型選擇（移至 LLM 設定頁面）

- **統計比喻優化**：恢復並優化遊戲化統計比喻
  - 魔族遭遇回数 = 文章數 (`post_count`)
  - 最大ダメージ = 留言數量 (`comment_count`)
  - 習得スキル総数 = 分類數量 (`category_count`)
  - アイテム使用回数 = TAG數量 (`tag_count`)
  - 冒険経過日数 = 運營日數 (`days_operating`)

### 📝 代碼優化

- **新增函數**：
  - `mpu_build_optimized_system_prompt()`：建構 System Prompt（支援變數替換）
  - `mpu_build_prompt_categories()`：生成 User Prompt 指令類別
  - `mpu_compress_context_info()`：壓縮上下文資訊
  - `mpu_get_visitor_status_text()`：獲取訪客狀態文字

- **函數重構**：
  - `mpu_generate_llm_dialogue()`：使用新的優化 System Prompt 系統
  - 移除舊的冗長 System Prompt 建構邏輯

- **向後兼容**：保持對舊設定的支援，自動遷移設定鍵值

### 🐛 錯誤修復

- 修復統計比喻對應關係
- 優化文字區域寬度設定（統一為 850px）
- 修復主選單底部線條對齊問題
- 修復滾動條樣式問題

### 📚 文檔更新

- 更新 `USER_GUIDE.md`：完整說明通用 LLM 接口和 System Prompt 優化系統
- 更新 `CHANGELOG.md`：記錄 2.2.0 版本所有更新

### 🎉 特別更新（2025-12-19）

- 為慶祝『葬送のフリーレン』第2期於2026年1月16日開始放送，預設角色已從初音變更為芙莉蓮（フリーレン）
- 新安裝的用戶會看到芙莉蓮作為預設角色
- 已安裝的用戶如果預設角色名稱仍為「初音」，系統會自動更新為芙莉蓮

---

## [2.1.7] - 2025-12-15

### 🚀 效能優化

- **JavaScript 檔案結構重構**：將 10 個 JS 檔案合併為 4 個，減少 HTTP 請求
  - `ukagaka-base.js`：合併 config + utils + ajax（基礎層）
  - `ukagaka-core.js`：合併 ui + dialogue + core（核心功能）
  - `ukagaka-features.js`：合併 ai + external + events（功能模組）
  - `ukagaka-anime.js`：保持獨立（動畫模組）
  - 所有檔案統一使用 `ukagaka-` 前綴命名

- **優化 mousemove 日誌**：移除頻繁觸發的日誌記錄，避免控制台被洗版
  - 註解掉 `mousemove` 事件中的日誌輸出
  - 提升 debug 模式下的調試體驗

### 🔧 功能改進

- **LLM 請求優化**：改用 POST 方式傳遞資料，避免 URL 長度限制
  - 使用 `FormData` 傳遞所有參數（`cur_num`、`cur_msgnum`、`last_response`、`response_history`）
  - 後端支援 POST 和 GET 兩種方式（向後兼容）
  - 使用 `wp_unslash()` 正確處理 WordPress 的 JSON 資料

- **防止 LLM 請求連點**：加入 `cancelPrevious: true` 選項
  - 當使用者快速連續點擊「下一句」時，自動取消前一個未完成的請求
  - 避免多個並行請求互相覆蓋打字機效果

### 🐛 錯誤處理優化

- **Canvas 動畫錯誤處理**：在 `mpuChange` 函數開始時檢查 Canvas Manager
  - 提前檢查 `window.mpuCanvasManager` 是否存在
  - 避免在 Ajax 成功後才發現錯誤，提供更一致的體驗

- **LLM 錯誤視覺提示**：在 debug 模式下顯示錯誤訊息
  - 顯示格式：`[LLM 錯誤: 錯誤訊息]`
  - 2 秒後自動切換到後備對話
  - 非 debug 模式下直接使用後備對話，不影響一般使用者

### 📝 其他改進

- 統一檔名命名規範：所有 JavaScript 檔案使用 `ukagaka-` 前綴
  - `jquery.textarearesizer.compressed.js` → `ukagaka-textarearesizer.js`

---

## [2.1.6] - 2025-12-13

### ✨ 新功能

- **WordPress 資訊整合**：LLM 自發對話現在可以獲取並評論網站資訊
  - 整合 WordPress 版本、主題資訊（名稱、版本、作者）、PHP 版本、網站名稱
  - 統計資訊：文章數、留言數、分類數、標籤數、運營日數
  - 使用 transient 快取機制（5 分鐘），提升效能
  - 新增 `wordpress_info` 和 `statistics` 兩類提示詞分類

- **RPG 風格統計資訊**：統計資訊使用遊戲化術語
  - 魔族遭遇回数（文章數）
  - 最大ダメージ（留言數）
  - 習得スキル総数（分類數）
  - アイテム使用回数（TAG數）
  - 冒険日数（運營日數）

- **防止重複對話機制**：避免「廢話迴圈」問題
  - 追蹤上一次 LLM 生成的回應
  - 提示詞中加入避免重複的指令
  - 自動生成不同的閒聊內容或保持沉默

- **閒置偵測功能**：自動暫停自動對話以節省資源
  - 偵測使用者活動（滑鼠、鍵盤、滾動、點擊）
  - 60 秒閒置閾值（可調整）
  - 使用者返回時自動恢復
  - 有效節省 GPU 和網路資源

### 🔧 改進

- **LLM 系統提示詞增強**：加入 WordPress 網站資訊作為背景知識
- **提示詞多樣性提升**：新增 WordPress 相關和統計資訊相關的提示詞
- **效能優化**：減少不必要的 LLM 請求
- **資源管理**：更好的 GPU 和網路資源使用控制

### 📝 技術細節

- 新增 `mpu_get_wordpress_info()` 函數（位於 `includes/utility-functions.php`）
- 修改 `mpu_generate_llm_dialogue()` 函數，整合 WordPress 資訊
- 前端 JavaScript 加入閒置偵測邏輯（`ukagaka-core.js`）
- AJAX 處理器支援 `last_response` 參數

---

## [2.1.0] - 2025-11-26

### ✨ 新功能

- **可配置打字速度**：新增打字效果速度設定（10-200 毫秒/字）
- **API Key 加密存儲**：使用 AES-256-CBC 加密所有 API Key
- **安全文件操作**：使用 WordPress Filesystem API 進行所有文件讀寫
- **目錄遍歷防護**：驗證所有文件路徑，防止未授權存取

### 🔧 改進

- 已設定的 API Key 會顯示綠色勾勾指示器
- 改善文件操作的錯誤訊息
- 向下相容：支援現有的明文 API Key 自動加密

### 🔒 安全性

- 所有 API Key 使用 AES-256-CBC 加密
- 文件操作使用 WordPress Filesystem API
- 新增路徑驗證防止目錄遍歷攻擊

---

## [2.0.0] - 2025-11-22

### 🏗️ 架構改進

- **完全模組化重構**：將單一檔案拆分為 7 個獨立模組
- **主程式精簡**：`mp-ukagaka.php` 精簡至約 85 行
- **依賴順序載入**：模組按依賴關係順序載入

### ✨ 新功能

- **AI 頁面感知**：根據文章內容自動生成 AI 評論
- **多 AI 提供商支援**：
  - Google Gemini（gemini-2.5-flash、gemini-2.5-pro）
  - OpenAI GPT（GPT-4o、GPT-4o-mini、GPT-3.5-turbo）
  - Anthropic Claude（Claude Sonnet 4.5）
- **首次訪客打招呼**：對新訪客顯示個性化歡迎訊息
- **Slimstat 整合**：獲取訪客來源、地區等資訊
- **AI 文字顏色**：可自訂 AI 回應的文字顏色
- **AI 顯示時間控制**：設定 AI 訊息顯示時長

### 🔧 改進

- **JSON 對話檔案支援**：除 TXT 外，新增 JSON 格式支援
- **改善錯誤處理**：更詳細的錯誤日誌
- **效能優化**：設定讀取使用快取機制

### 📁 模組結構

```
includes/
├── core-functions.php      # 核心功能
├── utility-functions.php   # 工具函數
├── ai-functions.php        # AI 功能
├── ukagaka-functions.php   # 春菜管理
├── ajax-handlers.php       # AJAX 處理
├── frontend-functions.php  # 前端功能
└── admin-functions.php     # 後台功能
```

---

## 回報問題

如發現問題，請提供以下資訊：

1. WordPress 版本
2. PHP 版本
3. 外掛版本
4. 錯誤訊息（如有）
5. 瀏覽器控制台錯誤（按 F12 查看）

---

## 貢獻者

- **原作者**：Ariagle _(原站點已停止運營)_
- **維護者**：Horlicks ([MoeLog](https://www.moelog.com/))

---

**感謝所有使用者的支持與回饋！** ❤️
