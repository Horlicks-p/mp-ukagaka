# MP Ukagaka 版本歷史

> 📋 所有版本的更新記錄

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
