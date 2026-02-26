# AI Engine 參考模式備忘錄

這份備忘錄整合了從 `ai-engine` 分析出對 `mp-ukagaka` 最有參考價值的架構與安全模式，並依據改動成本進行分類排序。

- **AI Provider 的工廠模式 (Factory Pattern)**
  已完成第二階段重構，建立 `MPU_AI_Provider_Factory` 體系，讓所有 LLM 供應商（OpenAI, Gemini, Claude, Ollama）實作標準介面。

- **工具呼叫迴圈防護 (Loop Guard / Loop Detection)**
  已全面實裝於各 Provider 類別中，透過 Signature (MD5 hash) 識別重複呼叫，上限常數名為 `MPU_MAX_TOOL_REPEAT_SAME_CALL`。

- **伺服器發送事件串流 (SSE Streaming)**
  已全面導入 `text/event-stream` 傳輸協定與 `user-stream` 端點，支援 OpenAI (`tool_calls` 組裝) 與 Ollama 的即時輸出，成功解決長回覆與思考型模型的等待焦慮。

---

## 🟢 低成本（可直接移植）

### ✅ 1. UTF-8 Safe JSON Encoding（已擴展為 Provider Helper）

[json_encode()](file:///d:/XAMPP/htdocs/wordpress/wp-content/plugins/ai-engine/classes/engines/core.php#47-99) 加上 `JSON_INVALID_UTF8_SUBSTITUTE` 旗標，防止因為使用者貼上 Word 特殊文字或奇怪字元時，API 請求發生靜默失敗。

- **目前狀態**：已透過 `includes/llm/provider-helpers.php` 的 `mpu_json_encode_safe()` 統一套用於 `ai-functions.php` 與 `chat-api-handlers.php`，並補上 fallback（避免呼叫端拿到 `false`）。

### ✅ 2. Cron 健康狀態追蹤

利用 transient 記錄每次 cron 執行的起訖與錯誤，讓 Admin UI 能顯示「上次日記生成：成功 / 失敗原因」。目前日記 cron 失敗完全無跡可查。

- **改動位置**：`diary-functions.php` 的排程回呼
  ```php
  set_transient('mpu_cron_diary_running', true, 300);
  // ... 執行完後 ...
  set_transient('mpu_cron_diary_last_run', ['status' => 'ok', 'time' => time()], WEEK_IN_SECONDS);
  ```

### ✅ 3. 欄位型別感知的 Sanitization（已部分實裝）

已針對高風險欄位完成型別感知處理（例如 Provider 白名單、Ollama endpoint 驗證、座標/TTL 範圍限制、personality id/key 清洗與 fallback），降低設定污染與回歸風險。

目前 **未統一調整布林欄位寫法**（仍保留 `isset($_POST['xxx'])` 與既有寫法並存），原因是現有 admin checkbox 表單場景下兩者皆可正常運作，暫列為維護性優化而非 bug 修復。

欄位類型仍建議持續區分：

- 純文字欄 → `sanitize_text_field()`
- 允許 HTML 欄 → `wp_kses_post()`
- 布林欄 → `filter_var($v, FILTER_VALIDATE_BOOLEAN)`

### ✅ 4. 未匹配 Placeholder 安全替換

若某個變數取不到值，將沒被替換的 `{{variable}}` 清除，避免 LLM 看到原始 template 語法。

- **改動位置**：`llm-context-builder.php` 收尾處加上：
  `$prompt = preg_replace('/\{\{[a-z_]+\}\}/', '', $prompt);`

---

## 🟡 中成本（值得規劃）

### ✅ 5. AI Provider 的工廠模式 (Factory Pattern) — 已完成

建立 `MPU_AI_Provider_Factory::get_provider()`，讓所有 LLM 供應商（OpenAI, Gemini, Claude, Ollama）實作標準介面。這能統一 [class-mpu-rest-test.php](file:///d:/XAMPP/htdocs/wordpress/wp-content/plugins/mp-ukagaka/includes/rest/class-mpu-rest-test.php) 與 [ai-functions.php](file:///d:/XAMPP/htdocs/wordpress/wp-content/plugins/mp-ukagaka/includes/llm/ai-functions.php)，未來新增模型（如 DeepSeek）會非常容易。

### 6. 伺服器發送事件串流 (SSE Streaming)

在後端解析模型回傳的 Streaming chunk，並透過 `text/event-stream` 傳遞給前端，實現「打字機效果」，解決長訊息（如思考時間長的模型）的等待體驗問題。

### ✅ 7. Object-Oriented Routing (物件導向路由結構) — 已完成

已完成，不再列為待規劃項目。

### ✅ 8. Request-Scoped State Reset

確保每次 API 請求前（或開始時）清空前一次請求殘留的狀態（如 Streaming buffer、Function call 結果）。如果未來加上 SSE Streaming，這個模式必須建立以防狀態污染。

**實裝細節：**

- 新增 `includes/llm/request-state.php`，提供 `mpu_reset_request_state()` / `mpu_ensure_request_state()` / `mpu_mark_request_mcp_tool_executed()` / `mpu_did_request_execute_mcp_tool()` 四個 API。
- `rest_pre_dispatch` filter（priority 5）在所有 `/mp-ukagaka/v1/` 請求前自動 reset。
- AJAX `mpu_ajax_user_chat` 入口明確 reset；`mpu_call_ai_api` 與 `chat-api-handlers.php` 以 `ensure`（不強制覆寫）作 fallback。
- 4 個 Provider 在偵測到 tool call 時改用 `mpu_mark_request_mcp_tool_executed()` 標記。
- 3 處截斷判斷改讀 `mpu_did_request_execute_mcp_tool()`，舊 global 保留為 legacy fallback。

### ✅ 9. Messages Integrity Checksum (對話防篡改)

Chat 送出前計算非使用者訊息（assistant、system role）的 MD5 並存入 transient，下次請求驗證。防止前端修改 AI 的歷史回答來誘發 Prompt Injection。

**實裝細節：**

- 新增 `includes/llm/chat-integrity.php`，提供 `mpu_chat_integrity_normalize_session_id()` / `mpu_chat_integrity_verify_history()` / `mpu_chat_integrity_store_history()` 三個公開 API。
- session_id 可選；無 session_id 時 `verify_history()` 回傳 `null`（向下相容，不影響現有前端）。
- checksum 只針對非 user 訊息（assistant）計算，`hash_equals()` timing-safe 比較，TTL `HOUR_IN_SECONDS`。
- AJAX `mpu_ajax_user_chat` 與 REST `MPU_REST_Chat::user_chat` 均在歷史驗證後呼叫 AI、回應後寫入下一輪 checksum。
- store 前先對 AI 回應套 `sanitize_text_field()`，確保存入 checksum 的內容與下一輪 verify 時客端傳回再 sanitize 後的結果一致，消除 false positive。
- 前端新增 `mpu_getOrCreateChatSessionId()`（localStorage + sessionStorage fallback），`/reset` / `/clear` 輪替 session id，請求時附帶 `session_id` 欄位。

### ✅ 10. 工具呼叫迴圈防護（Loop Guard / Loop Detection）

已全面實裝於各 Provider 類別中。

**實裝細節：**

- 工具呼叫回合上限常數化：`MPU_MAX_TOOL_TURNS`（預設 5）。
- 新增連續重複呼叫偵測：`MPU_MAX_TOOL_REPEAT_SAME_CALL`（預設 2）。
- 使用 Signature (MD5 hash of normalized args) 識別相同呼叫。
- 實作於 `includes/llm/tool-loop-guard.php` 並整合至 Provider Factory 體系。
- 超限時回傳明確錯誤 `tool_call_loop_detected` 並記錄詳細 debug log。

---

## 🔴 高成本（僅供參考）

### 11. 型別化的 Query 物件體系

將文字、圖像、Embedding 等 API 請求分別封裝成獨立 Class，以解決目前 [mpu_call_ai_api()](file:///d:/XAMPP/htdocs/wordpress/wp-content/plugins/mp-ukagaka/includes/llm/ai-functions.php#13-97) 參數過於肥大且混雜的問題。

### 12. 動態 Model 載入

模型列表由 API 端點自動載入或透過各 Engine 的 [retrieve_models()](file:///d:/XAMPP/htdocs/wordpress/wp-content/plugins/ai-engine/classes/engines/core.php#671-674) 抓取，廢棄目前在 `options_page_llm.php` 硬編碼模型選單的作法。
