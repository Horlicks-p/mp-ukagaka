# SSE Streaming 實作計畫（#6）

## 目的

為 `mp-ukagaka` 的互動對話模式加入 **伺服器發送事件串流（SSE）**，在後端解析模型 Streaming chunk 後，即時推送到前端，改善長回覆與思考型模型的等待體驗（打字機效果 / 漸進顯示）。

本計畫以「**低風險分階段導入**」為原則，優先保留現有同步路徑相容性。

---

## 現況摘要（已確認）

- REST 聊天主路徑為 `POST /mp-ukagaka/v1/chat/user`
  - 註冊位置：`includes/rest/class-mpu-rest-chat.php:42`
  - 處理入口：`includes/rest/class-mpu-rest-chat.php:466`
- 前端互動聊天目前為一次性 `fetch` + JSON 回應（非串流）
  - `js/ukagaka-chat.js:329`
- 通用 `mpuFetch()` 目前是 JSON/文字回應模型，並會從 JSON 的 `new_token` 更新 REST nonce
  - `js/ukagaka-base.js:737`
  - `js/ukagaka-base.js:772`
  - `js/ukagaka-base.js:826`
- 多輪聊天 wrapper 目前為同步 `generate_chat()`
  - `includes/ajax/chat-api-handlers.php:26`
- Provider base 已預留 `FEATURE_STREAMING` 常數，但尚未實作
  - `includes/llm/providers/class-mpu-ai-provider-base.php:24`
- Request-scoped state 已預留 `streaming` 狀態欄位（目前僅 buffer）
  - `includes/llm/request-state.php:42`
- `chat integrity checksum` 流程目前是：
  - 請求前驗證 history
  - AI 回應完成後寫入下一輪 checksum
  - SSE 必須保留相同安全順序

---

## 核心設計決策（建議）

1. 新增獨立端點 `POST /mp-ukagaka/v1/chat/user-stream`
2. 前端使用 `fetch + ReadableStream` 讀取 SSE（不使用 `EventSource`）
3. 第一版僅導入互動聊天（`chat/user`），不擴散到 `chat/context` / `chat/greet`
4. 保留同步 fallback（provider 不支援 streaming 時自動退回舊流程）
5. 第一階段優先支援 `OpenAI` + `Ollama`，`Claude` / `Gemini` 延後
6. REST SSE 掛點採 **Option A：在 callback 內直接 `echo + flush + exit;`**（不使用 `rest_pre_serve_request`）

### 為何不用 EventSource

- 現有請求是 `POST + FormData + X-WP-Nonce`
- `EventSource` 不適合此請求模型（偏向 GET，且 headers 客製困難）
- `fetch + ReadableStream` 更符合現有前端架構與 nonce 注入需求

---

## 分階段實施計畫

## Phase 0（先決條件）：抽出 user_chat 共用前置準備流程（避免邏輯複製）

`REST user_chat()` 目前邏輯很長，SSE 若直接複製會造成維護風險。

### 建議抽出函式（明確命名）

```php
function mpu_prepare_user_chat_args(WP_REST_Request $request): array|WP_Error
```

### 建議回傳內容（至少）

- `provider`
- `api_key`
- `system_prompt`
- `messages`
- `max_tokens`
- `language`
- `personality_id`
- `ukagaka_name`
- `session_id`（已完成 checksum 驗證）
- `chat_history`
- `user_message`
- `mpu_opt`

### 目標

- 同步 `user_chat()` 與新 `user_chat_stream()` 共用同一份前置流程
- 降低未來安全修補（sanitize/checksum/rate-limit）漏改風險

---

## Phase 1：定義插件內部 SSE 協議（標準事件）

後端統一輸出 `text/event-stream`，事件格式固定，payload 為 JSON：

- `event: start`
  - 請求已接受（provider / model / request_id）
- `event: nonce`
  - 傳遞 `new_token`（可加上 `new_nonce` 同值欄位，保留未來擴充命名空間）
  - SSE 路徑不會自動走 `rest_post_dispatch`
- `event: delta`
  - 文字增量（前端即時拼接）
- `event: status`
  - 狀態通知（思考中、工具執行中等）
- `event: tool_call`
  - 工具名稱（可選，偏 debug/UI）
- `event: tool_result`
  - 工具執行完成（可選）
- `event: done`
  - 完整結果、emoji、收尾資訊（checksum / 統計完成）
- `event: error`
  - 錯誤碼與訊息
- `event: ping`
  - heartbeat（避免長時間無輸出遭 proxy 中斷）

### 設計原則

- 對前端暴露的是「插件標準事件」，不是各 provider 原生格式
- provider chunk 差異（OpenAI SSE / Ollama JSON Lines）由後端吸收

---

## Phase 2：新增 SSE 傳輸共用 Helper

建議新增檔案（擇一）：

- `includes/rest/sse-helpers.php`
- 或 `includes/llm/streaming-helpers.php`

### 職責

- 設定 SSE 標頭
  - `Content-Type: text/event-stream`
  - `Cache-Control: no-cache, no-transform`
  - `X-Accel-Buffering: no`
  - `X-Content-Type-Options: nosniff`
- 安全地關閉/清理 output buffering
- SSE handler 進入點設置：
  - `ignore_user_abort(true);`
  - `set_time_limit(0);`
- 提供通用函式：
  - `mpu_sse_send_event($event, $data)`
  - `mpu_sse_flush()`
  - `mpu_sse_heartbeat_if_needed()`
  - `mpu_sse_client_disconnected()`

### 注意

- 這一層只負責「傳輸」，不處理聊天業務邏輯
- Output buffering 必須清多層（不能只清一層）：

```php
while (ob_get_level() > 0) {
    ob_end_clean();
}
```

- 若未清乾淨，XAMPP / Apache / PHP 多層 buffering 會導致「看似串流、實際整包輸出」

---

## Phase 3：新增 REST 路由 `POST /chat/user-stream`

檔案：`includes/rest/class-mpu-rest-chat.php`

### 變更

- 在 `register_routes()` 增加 `/chat/user-stream`
- 新增 callback：`user_chat_stream(WP_REST_Request $request)`

### 行為要求（需與 `/chat/user` 一致）

- Rate limit 一致（30 次 / 60 秒）
- 使用現有 request-state reset 機制（`rest_pre_dispatch` 已有）
- `chat integrity verify` 前置檢查一致
- 成功時走 SSE 輸出（非 `WP_REST_Response`）

### 重要差異

- 由於 SSE callback 可能直接輸出並提前結束，`rest_post_dispatch` 的自動 nonce refresh 不一定會套用
- 必須在 SSE 流程中自行發送 `event: nonce`

### 掛點決策（本計畫採用）

- 採用 **callback 內直接輸出 SSE 並 `exit;`**
- 不使用 `rest_pre_serve_request`
- 理由：
  - `rest_pre_dispatch`（request-state reset）已在 callback 前執行
  - `rest_post_dispatch` nonce refresh 可由 `event: nonce` 補齊
  - 避免 sentinel 回傳值與額外 filter 協調成本

---

## Phase 4：Provider Streaming 介面與能力宣告

### 建議擴充 Provider contract

在 Provider 介面增加串流方法（命名可討論）：

- `generate_chat_stream(array $args, callable $emit, array $context = [])`

其中：

- `$emit`：provider 將解析後事件回傳給上層（REST SSE handler）
- `$context`：傳遞 request-level metadata（例如 `provider`、`request_id`），避免 provider 依賴 REST 層細節

### `$emit` callable 簽名（建議先定義）

```php
// $emit(string $event_name, array $data): void
```

範例：

```php
$emit('delta', ['text' => '你好']);
$emit('done', ['full_text' => '...', 'emoji' => null]);
$emit('error', ['code' => 'stream_failed', 'message' => '...']);
```

### `supports()` 能力宣告

- `supports(FEATURE_STREAMING)` 明確回報 provider 是否支援
- 第一階段：
  - `OpenAI`：`true`
  - `Ollama`：`true`
  - `Claude`：`false`（或 stub）
  - `Gemini`：`false`（或 stub）

### 相容性要求

- 所有 provider 先補 stub，避免 interface 改動造成 fatal error
- 實作需維持 PHP 7.4 相容（不可使用 PHP 8.0 union types）

### PHP 7.4 相容性提醒（實作時）

本計畫中的函式簽名示意屬於 pseudocode，實作時請勿直接使用 PHP 8+ union type，例如：

- `array|WP_Error`
- `void|WP_Error`

請改用 PHPDoc + PHP 7.4 相容簽名，例如：

```php
/** @return array|WP_Error */
function mpu_prepare_user_chat_args(WP_REST_Request $request) {
    // ...
}
```

---

## Phase 5：新增低階串流 HTTP Client（關鍵）

現有 provider 皆以 `wp_remote_post()` 收整包回應，不適合真正 chunk relay。

### 建議新增 helper

- `includes/llm/provider-stream-http.php`

### 建議實作方式

- 使用 PHP cURL low-level streaming（`CURLOPT_WRITEFUNCTION`）
- 在 callback 中即時接收 chunk，交給 provider parser
- 支援：
  - headers / body / timeout
  - client disconnect 時中止上游請求
  - 錯誤標準化回傳

### 斷線處理關鍵（必須）

- SSE handler 進入點第一行就呼叫：

```php
ignore_user_abort(true);
```

- 否則 client 斷線時 PHP 可能直接中止 script，執行不到 `connection_aborted()` 檢查點
- 然後在 streaming loop / callback 中定期檢查 `connection_aborted()`，必要時停止上游 cURL 串流

### 降級策略

- 若環境無 cURL 或串流建立失敗：
  - 回 `error` event（明確訊息）
  - 或 fallback 到同步 `chat/user`（依實作選擇）

> 這是 #6 成敗關鍵，請 Claude / Gemini 特別審視（尤其 `ignore_user_abort(true)` 與 cURL callback 的中止策略）。

---

## Phase 6：Provider 串流解析器（第一階段：OpenAI + Ollama）

## 7.1 OpenAI Streaming

### 上游格式

- OpenAI 為 SSE（`data: ...`，最終 `[DONE]`）

### 後端解析重點

- 解析 `choices[].delta.content` 作為文字增量
- 若出現 `tool_calls` delta，需以 `index` 組裝跨多 chunk 的 tool call（不能直接當文字輸出）
- 將 provider chunk 轉為插件標準事件（`delta/status/tool_call/...`）

### OpenAI `tool_calls` 串流組裝（高風險區，需獨立測試）

OpenAI 的 `tool_calls` 常跨多個 delta，以 `index` 拼接，不會一次給完整 arguments：

```text
delta: {"tool_calls":[{"index":0,"id":"call_abc","function":{"name":"get-pop"}}]}
delta: {"tool_calls":[{"index":0,"function":{"arguments":"{\"lim"}}]}
delta: {"tool_calls":[{"index":0,"function":{"arguments":"it\":5}"}}]}
```

### 建議組裝策略

- 維護 `$tool_calls_buffer[$index]`
- 持續拼接：
  - `id`
  - `function.name`
  - `function.arguments`（字串增量）
- 僅在 `finish_reason === 'tool_calls'` 時，才將 buffer 視為可執行工具呼叫
- 之後再進入既有 Loop Guard / MCP execution 流程

> 此段建議列為 Phase 6 的獨立測試項目（最容易出錯）。

## 7.2 Ollama Streaming

### 上游格式

- 常見為 JSON Lines（每行一個 JSON，不是 SSE）

### 後端解析重點

- 逐行解析 JSON
- 抽取 `message.content` / `message.thinking` / `done`
- 再轉成插件標準 SSE 事件
- `thinking` 內容（如 `<think>...</think>`）需在第一版明確策略：
  - 建議預設過濾，不顯示給使用者
  - 如需 debug，可改走 `status` 事件摘要而非原文輸出

## 7.3 共通要求

- 累積最終文字（供 checksum / emoji / store_history 使用）
- 工具呼叫回合維持既有 Loop Guard 與 request-state 標記
- 在適當時機送 heartbeat（`ping`）

---

## Phase 7：工具呼叫（MCP）與串流策略（第一版建議）

### 第一版策略

- 僅串流「最終 assistant 文字回合」
- 工具回合不輸出工具結果全文，僅發送狀態事件
  - `status`
  - `tool_call`
  - `tool_result`

### 狀態事件建議 payload

- `status` 可帶：
  - `thinking: true`
  - `executing_tool: "tool_name"`
  - `message: "正在搜尋網頁..."`（可選）

### 優點

- 保留目前 MCP 安全邊界
- 不必讓前端處理複雜 tool schema / partial JSON
- 與現有 `Loop Guard` / checksum 流程相容性高

### 可選（之後）

- debug mode 下額外輸出工具結果摘要（非預設）

---

## Phase 8：前端 SSE Reader（新函式，不改 `mpuFetch()`）

檔案：`js/ukagaka-chat.js`

### 新增函式（命名可調整）

- `mpuFetchSSE(...)`
- 或 `mpu_chat_stream_request(...)`

### 實作方式

- 使用 `fetch(..., { method: "POST", body: FormData, headers: { "X-WP-Nonce": mpuRestNonce } })`
- 讀取 `response.body.getReader()`
- 使用 `TextDecoder` 解析 chunk
- 自行處理 SSE frame 邊界（chunk 可能切斷 event）

### SSE frame parser（需明確實作）

前端需維持持久 `lineBuffer`，處理 chunk 被切斷在 event 中間的情況：

```js
let lineBuffer = "";

// 每次 chunk 到達時：
lineBuffer += decoder.decode(chunk, { stream: true });
const frames = lineBuffer.split("\n\n");
lineBuffer = frames.pop() || ""; // 最後一段可能不完整，保留到下一輪

for (const frame of frames) {
  // parse event:/data: lines
}
```

### 補充

- 不可假設 `ReadableStream` chunk 與 SSE event frame 一一對齊
- `data:` JSON 也可能剛好在字串中間被切開

### 事件處理邏輯

- `delta`
  - 累積文字並即時更新 `#ukagaka_msg`
  - 取代「等待整包後再 `mpu_typewriter()`」的模式
- `nonce`
  - 更新 `window.mpuRestNonce`
- `status`
  - 先寫 log（之後可做 UI）
- `done`
  - 寫入 `mpuChatHistory`
  - `mpu_saveChatHistory()`
  - 顯示 emoji
  - 解鎖輸入框 / 聚焦
- `error`
  - 顯示錯誤訊息
  - 解鎖 UI

### 與現有 `mpu_sendUserMessage()` 整合

- 加入分支：
  - 若 SSE 可用且 provider 支援 streaming -> 走 SSE
  - 否則維持既有 `mpuFetch(mpuRestUrl + "chat/user", ...)`

### 注意

- 不建議直接改造 `mpuFetch()` 成 SSE 兩用，風險較高（現有大量 JSON 呼叫依賴）

---

## Phase 9：取消請求與斷線處理

## 前端

- 使用 `AbortController` 中止 SSE 請求
- 觸發情境：
  - 使用者再次送出
  - 離開聊天模式
  - 主動取消（若後續加按鈕）

## 後端

- 以 `connection_aborted()` 偵測 client disconnect
- 中止上游 provider 串流請求（避免資源浪費）
- 停止後續 SSE 輸出與收尾寫入

### 避免「幽靈說話」

- 前端在聊天模式關閉時立即 abort
- 不將中斷中的 partial 回覆寫入歷史

---

## Phase 10：完整收尾（與同步路徑一致）

在串流完成後（final text 已確定）再執行：

- 回應長度限制（保留現有 `mpu_did_request_execute_mcp_tool()` 邏輯）
- `emoji` 分析
- `mpu_record_conversation('interactive')`
- `mpu_chat_integrity_store_history(...)`
- 最後送 `event: done`

### 中途錯誤 / 中止時

- 不寫 checksum（避免污染下一輪 integrity）
- 視情境送 `error` event 或安靜結束（client 已斷線）
- 僅在 `event: done` 前、且連線仍有效時寫入 checksum

---

## Phase 11：測試與驗證計畫（必做）

## 功能測試

- OpenAI：
  - 短回覆 / 長回覆 / 工具呼叫回合
- Ollama：
  - 一般模型 / thinking 模型（長等待）
- 聊天模式關閉中途 abort
- 快速連點送出（cancelPrevious 行為）
- `/reset` / `/clear` 後 session 輪替正常

## 安全與穩定性測試

- Rate limit 仍生效
- nonce 更新（SSE `nonce` event）可正確刷新前端 `mpuRestNonce`
- chat integrity checksum 在正常完成時寫入、在中止時不污染
- 長時間無輸出時 heartbeat 可維持連線
- client 斷線時 `ignore_user_abort(true)` + `connection_aborted()` 路徑可正常中止上游串流

## 相容性測試

- 不支援 streaming 的 provider 自動 fallback 同步流程
- 原有 `chat/user` JSON API 完全不受影響
- 前端打包後正常（`npm run build`）
- Proxy 環境 buffering 測試（例如 Nginx + FastCGI），確認非僅本地 XAMPP 有效

---

## 建議實作順序（實務）

1. **先完成 Phase 0（抽離共用前置邏輯）**，作為 SSE 導入先決條件
2. 完成 SSE helper + `/chat/user-stream` 空殼（回 `start` / `done`）
3. 前端 SSE reader 串起來（先用假資料驗證 parser/UI）
4. 接 `Ollama` streaming（本地易反覆測試）
5. 接 `OpenAI` streaming（含 `tool_calls` index 組裝）
6. 補 nonce event / checksum store / fallback 細節
7. 再評估 `Claude` / `Gemini`

---

## 風險與注意事項

- WordPress REST SSE 掛點本計畫已決策採用「callback 內直接輸出 `echo + flush + exit;`」（詳見 Phase 3）
  - 需注意與 REST lifecycle 的交界行為（例如 `rest_post_dispatch` 不會接手 nonce refresh，因此以 `event: nonce` 補齊）
- `wp_remote_post()` 不適合真串流 relay，需 low-level cURL 支援
- 不同主機環境的 buffering（PHP output buffering / proxy buffering）可能導致「看似 SSE 實際延遲整包」
- 工具呼叫 + 串流若處理不當，容易造成 partial JSON / state 汙染

---

## 請 Claude / Gemini 協助審視的重點

1. 已決策採用 callback 直接輸出 SSE + `exit;`，請審視其細節風險（buffer 清理、nonce event、中止行為）
2. WP 環境中最穩定的 provider streaming transport（cURL callback 設計）
3. 工具呼叫（MCP）與串流事件模型是否需要更細粒度狀態事件
4. chat integrity checksum 在串流中斷時的最佳策略（完全不寫 vs partial 標記）
5. 已將抽離 `REST/AJAX user_chat` 共用前置邏輯提升為 Phase 0 先決條件，請審視切分邊界是否合理

---

## 補充：本計畫的相容性目標

- 不破壞現有 `POST /mp-ukagaka/v1/chat/user`
- 不改壞既有前端 `mpuFetch()` JSON 路徑
- 可逐 provider 漸進開啟 streaming 能力
- 若串流不可用，使用者體驗退回現有同步模式（可接受）

---

## 已實作結果與問題修正紀錄（2026-02-26）

本節記錄 SSE Streaming 實作後的實際落地結果，以及目前已踩過並修復的問題。未來若再出現回歸，可優先對照本節。

### 已完成（實作落地）

- 新增 REST SSE 端點：`POST /mp-ukagaka/v1/chat/user-stream`
- 後端 SSE helper 已落地（多層 output buffer 清理、`ignore_user_abort(true)`、`set_time_limit(0)`、`X-Accel-Buffering: no`、`X-Content-Type-Options: nosniff`）
- Provider streaming 已落地（第一階段）
- OpenAI：SSE chunk relay + `tool_calls` index 組裝
- Ollama：JSON Lines relay + 工具呼叫迴圈整合
- 前端 `fetch + ReadableStream` SSE reader 已落地（含 `lineBuffer` 累積）
- SSE 路徑 nonce refresh 已落地（`event: nonce` + 前端更新 `mpuRestNonce`）
- 前端已整合 `AbortController` 避免幽靈說話
- 非串流 Provider（如 Claude/Gemini）已可自動走同步 fallback（前端讀 `mpuPreSettings.streaming_enabled`）

### 已修復問題（重點紀錄）

#### 1. Streaming 參數遺漏（Ollama endpoint / max_tokens / model）

- `prepare_user_chat_args()` 初版未回傳頂層 `endpoint` / `max_tokens` / `model`
- 影響：
- Ollama streaming 永遠打 `localhost`
- `max_tokens` 掉回預設 `1000`
- SSE `start` event 模型名可能顯示 `unknown`
- 修正：在 `prepare_user_chat_args()` 回傳頂層參數，供 streaming provider 與 SSE `start` 共用

#### 2. `/debug_mcp` 打到 `user-stream` 造成 500 Fatal

- 症狀：
- `Undefined array key "provider"`
- `get_provider(null)` -> `WP_Error`
- 呼叫 `WP_Error::supports()` fatal
- 根因：`user_chat_stream()` 在 `/debug_mcp` 特殊回傳（無 `provider`）下，仍先做 provider 檢查
- 修正：
- `user_chat_stream()` 提前攔截 `is_debug_mcp`，轉回同步 `user_chat($request)`
- 增加 `is_wp_error($provider_instance)` guard

#### 3. Windows/Apache 環境下 SSE frame 無法被前端正確解析（HTTP 200 但 UI 卡在「えっと…」）

- 症狀：DevTools 可看到 `event: delta`/`done`，但前端不觸發 `onDelta` / `onDone`
- 根因：前端 parser 只用 `split("\\n\\n")`，未處理 `CRLF`（`\\r\\n\\r\\n`）
- 修正：
- frame 分割改為 `split(/\\r?\\n\\r?\\n/)`
- frame 解析前加 `line = line.replace(/\\r/g, "")`

#### 4. 同步 fallback 分支一度變成空殼 / UX 問題

- 非串流 fallback 曾只剩註解 `.then(...)`，導致不支援 streaming 的 provider 無法正常顯示回覆
- 修正：補回完整 `.then/.catch/.finally`（history 寫入、動畫、emoji、錯誤處理、UI 解鎖）
- SSE `onDone` 用截斷後 `data.msg` 重繪畫面會造成長回覆閃跳
- 修正：若串流過程已有 `fullResponse`，`onDone` 不再重繪，只寫歷史

#### 5. Chat Integrity Checksum 400（歷史驗證失敗）系列問題

- 問題 A：儲存 checksum 的歷史長度與前端下一輪送出的歷史長度不一致
- 修正：同步 / 串流路徑在 `store_history()` 前統一 `array_slice($next_history, -10)`
- 問題 B：`/debug_mcp` 回應會被前端寫入聊天歷史，但 debug 分支未寫 checksum
- 症狀：執行 `/debug_mcp` 後，下一句起互動聊天與一般對話皆可能被污染，`user-stream`/`chat/user` 回 400
- 修正：`/debug_mcp` 分支回傳前，手動將 debug 對話（user + assistant report）寫入 checksum
- 問題 C：Ollama thinking 內容（如 `<think>...</think>`）造成前後端內容不一致
- 症狀：第一句正常、第二句 checksum mismatch（尤其 Ollama / MCP / thinking 模型）
- 根因：後端寫入 checksum 的內容與前端顯示 / 下一輪送回內容在 thinking 標記層級不一致
- 修正：在 `user_chat` / `user_chat_stream` 收尾、寫入 checksum 前，若 provider 為 Ollama，先套 `mpu_filter_thinking_content()` 再存
- 問題 D：跨 Provider 切換（尤其切到/切離 Ollama）後出現 checksum 400
- 根因：不同 provider（特別是 Ollama）對 assistant content 的最終型態不同，導致同一 `session_id` 下 checksum 不一致
- 修正方向（已完成）：統一 checksum 寫入前的內容正規化流程，確保切換 provider 後仍可對齊

##### 生產環境最終確認（2026-02-27）

- 問題 E：滑動窗口造成 store / verify 不對稱（長對話第 6 輪後高機率 400）
- 根因：僅做 `array_slice(..., -10)` 時，窗口左側切掉 `user` 後，首位可能變成孤立 `assistant`；若儲存端與驗證端處理順序不同，checksum 必然不一致
- 修正：
- 在 `includes/llm/chat-integrity.php` 新增並統一使用 `mpu_chat_integrity_slice_for_store($history, 10)`（先 slice，再正規化移除孤立 assistant）
- 三個寫入點（`user_chat` / `user_chat_stream` / `/debug_mcp`）皆改為先組 `$raw_history`，再呼叫 `mpu_chat_integrity_slice_for_store(..., 10)` 後寫入
- 問題 F：長文本與換行在存取流程中的微差導致 checksum mismatch
- 根因：`sanitize_text_field` 會壓平換行；MCP/長回覆情境下，前端回傳內容與後端儲存內容容易出現字串層級差異
- 修正：
- 驗證端歷史清理統一改為 `sanitize_textarea_field(wp_unslash(...))`
- 儲存端 assistant 內容統一改為 `sanitize_textarea_field(...)`
- 問題 H：曾嘗試在 store 端加入 `wp_unslash()`（`sanitize_textarea_field(wp_unslash($result))`）
- 風險：`$result` 來自 AI API，非 WP magic quotes 輸入；額外 `wp_unslash()` 可能吃掉合法反斜線（程式碼/正則/路徑），並與 `done` event 顯示內容產生新不一致
- 最終決策（2026-02-27）：撤回 store 端 `wp_unslash()`；僅 verify 端保留 `wp_unslash()`（因為 verify 讀的是 request POST payload）
- 問題 I：SSE/同步錯誤或中止時，前端已 push 的 user 訊息未回滾
- 風險：前端歷史殘留「無對應 assistant」的 user 訊息，下一輪可能觸發 checksum mismatch
- 最終修正（保留）：前端 `onError` / `onAbort` / 同步 `.catch()` 都會回滾末尾 user 並 `saveChatHistory()`
- 問題 G：連線中止後仍寫入 checksum，污染下一輪驗證
- 修正：三個 checksum 寫入點皆加入 `!connection_aborted()` 防護
- 驗收結果：
- `php -l includes/llm/chat-integrity.php` 通過
- `php -l includes/rest/class-mpu-rest-chat.php` 通過
- `user-stream` 200（`text/event-stream`）與 400（`application/json`）為回應型態差異，已確認核心問題在 checksum 對齊邏輯，非 Cloudflare header 行為本身
-

## 2026-02-27 決策更新：Checksum 改為觀測模式（治本）

- 背景：
  - SSE Streaming 上線後，`chat history checksum` 在多種邊界情境（分段、fallback、中斷、工具呼叫、thinking）容易出現「理論上不一致，但不代表攻擊」的誤判。
  - 既有硬性策略為：mismatch -> `WP_Error(status=400)` -> REST fail，造成使用體驗受損。

- 新策略（已實作）：
  - mismatch -> 記錄 `[WARN]` log -> `return null`（與「沒有 transient」同級處理）-> 請求繼續。
  - 對齊 -> `return true`，請求繼續。
  - 沒有 transient -> `return null`，請求繼續。

- 影響：
  - checksum 從「阻斷機制」調整為「稽核/觀測機制」。
  - 目標是優先穩定 SSE 體驗，降低誤判造成的 400。

- 回滾方式（保留）：
  - 若未來要恢復硬性阻斷，只要把 mismatch 分支改回 `return new WP_Error(...)` 即可，REST 入口邏輯無需調整。

#### 6. MCP 工具執行狀態訊息語言不同步（模型回日文、狀態提示顯示中文）

- 根因：後端 `$emit('status', ...)` 使用硬編碼中文訊息
- 修正：
- 後端（OpenAI/Ollama）改為 machine-readable status payload（`type: "executing_tool"`, `tool: "..."`）
- 前端 `onStatus` 依 `type` 使用 `mpuL10n.executingTool` 模板格式化
- `frontend-functions.php` 新增 `mpuL10n.executingTool`（走 WP i18n）
- 保留 `data.message` / `executing_tool` 作相容 fallback

#### 7. `/debug_mcp` 經 `user-stream` 回傳 JSON 200，但前端仍以 SSE parser 處理（紅字/錯誤 UI）

- 症狀：`/debug_mcp` 後端已正確回 `application/json` 與診斷內容，但前端仍顯示錯誤樣式或未正確完成流程
- 根因：`mpuFetchSSE()` 預期 `text/event-stream`，未處理 `application/json` fallback
- 修正：在 `mpuFetchSSE()` 先檢查 `content-type`
- 若為 `application/json` 且 `response.ok`，直接 `onDone(json)`
- 若為 `application/json` 且非 200，直接 `onError(json)`

#### 8. SSE 錯誤路徑重複觸發 `onError`（前端）

- 症狀：串流途中發生 `event: error` 時，`onError` 被呼叫兩次，錯誤動畫/Log 疊加
- 根因：`case "error"` 先呼叫 `onError` 後又 `throw`，被外層 `catch` 再次呼叫 `onError`
- 修正：`case "error"` 改為呼叫 `onError` 後直接 `return`，不再 `throw`

#### 9. Ollama provider 與 REST handler 重複送出 SSE `error` event

- 症狀：Ollama 串流錯誤時，provider 與 `user_chat_stream()` 外層都送 `event: error`，前端錯誤處理可能倍增
- 根因：provider 層 `emit('error', ...)` 後仍回傳 `WP_Error`，外層 handler 再送一次 `error` event
- 修正：統一錯誤事件發送責任到 `user_chat_stream()` 外層
- Ollama provider 錯誤時僅 `return WP_Error`，不自行 `emit('error', ...)`

#### 10. `prepare_user_chat_args()` 與 `rate_limit()` 回傳型別交界（`WP_REST_Response`）

- 症狀：若 `rate_limit()` 命中回傳 `WP_REST_Response`，呼叫端若誤當陣列使用，可能導致後續錯誤
- 修正：
- `prepare_user_chat_args()` 內以 `if ($rl !== null) return $rl;` 明確轉傳
- `user_chat()` / `user_chat_stream()` 入口同時檢查 `WP_REST_Response` 與 `WP_Error`

### 實務驗證經驗（可供後續排查）

- 若 `user-stream` 是 HTTP 200 且 body 中可看到 `event: delta`/`event: done`，但 UI 不更新：優先檢查前端 SSE parser（分隔符 / CRLF）
- 若 `/debug_mcp` 後下一句開始所有聊天都 400：優先檢查 debug 分支是否有同步寫入 checksum
- 若僅 Ollama 或切換到/離開 Ollama 後容易 400：優先檢查 thinking content 正規化與 checksum 寫入內容是否對齊
- 若出現 `Undefined array key "provider"` 或 `WP_Error::supports()`：優先檢查 `user_chat_stream()` 是否先攔截 debug 分支與 `is_wp_error($provider_instance)` guard

### 尚需持續觀察（未來可能再踩到）

- Ollama 不同模型的工具呼叫穩定性（有些模型可能輸出文字式工具語法，例如 `::invoke ...`，而非結構化 `tool_calls`）
- 代理環境（Nginx + FastCGI）buffering 對 SSE 真串流體驗的影響
- 長時間工作階段下 nonce refresh 與中斷重連行為
- 多輪對話 + MCP + Provider 切換混合情境的 checksum 一致性

### 維護者備忘（避免重蹈覆轍）

- 涉及 `class-mpu-rest-chat.php` / `ukagaka-chat.js` 的修補請優先使用精準 patch，避免整檔重寫造成編碼或亂碼風險
- 每次調整後至少執行：
- `php -l includes/rest/class-mpu-rest-chat.php`
- `npm run build`（更新 `js/dist/ukagaka-bundle*.js`）
- 若再出現 `對話歷史驗證失敗`（400），優先查看 `MPU Chat Integrity` debug log 的 `expected/actual/history_count`
- **新增**：亦可查看 `mp-ukagaka/logs/checksum-mismatch.log`，內含 store/verify 兩端的 filtered JSON 原文 diff

## 2026-02-27 追加修正：Checksum 診斷日誌 & slice 順序對齊

### 背景

- Checksum 已改為觀測模式（mismatch 不中斷請求），但缺乏有效的事後排查手段。
- `mpu_chat_integrity_slice_for_store()` 的處理順序（slice→normalize）與 verify 端的處理順序（normalize→slice）不對稱，在長對話窗口滑動時可能導致孤立 assistant 被不同方式移除，產生 mismatch。

### 變更內容

#### 1. 新增 `mpu_chat_integrity_dump_mismatch()`（`chat-integrity.php`）

- 當 `verify_history()` 偵測到 mismatch 時，自動呼叫此函式
- 寫入 `mp-ukagaka/logs/checksum-mismatch.log`（有 `.htaccess` 保護）
- 記錄內容：
  - 時間戳、session_id、expected/actual checksum
  - **STORE 端** 上一輪寫入時的 filtered JSON（透過 transient snapshot）
  - **VERIFY 端** 本輪驗證時的 filtered JSON
  - VERIFY 端 raw history 的 role + content 前 80 字元預覽
- Log 檔案超過 512KB 時自動截斷保留後半

#### 2. `mpu_chat_integrity_store_history()` 新增 snapshot 保存

- 每次 store 時，同時將 filtered JSON 寫入 `mpu_chat_cs_snapshot_{session_id}` transient
- 供下一次 mismatch dump 時比對用
- 與 checksum transient 同生命週期（1 小時）

#### 3. `mpu_chat_integrity_slice_for_store()` 順序修正

- **舊版**：`array_slice(-$limit)` → normalize（移除孤立 assistant）
- **新版**：normalize（移除孤立 assistant）→ `array_slice(-$limit)`
- 此修正使 store 端與 verify 端（`prepare_user_chat_args` line 647-659）的處理路徑完全對稱
- 影響範圍：所有 checksum 寫入點（`user_chat` / `user_chat_stream` / `debug_mcp` / `chat/context` / `chat/greet` / `akismet-integration`）

#### 4. `.gitignore` 更新

- 新增 `logs/` 目錄排除，防止診斷日誌被 commit

### 已知的潛在不一致點（待觀察）

- `user_message` 在 `prepare_user_chat_args()` 以 `sanitize_text_field()` 處理（會剝除換行），但 verify 端讀取前端歷史時以 `sanitize_textarea_field()` 處理（保留換行）。若使用者訊息含換行符，store 與 verify 可能產生差異。目前此情境較罕見（前端輸入框為單行），但若未來改為多行輸入框則需注意。

### 排查流程更新

- 若出現 mismatch，除了原有的 `[MPU Chat Integrity]` debug log 外，現在可直接查看 `mp-ukagaka/logs/checksum-mismatch.log`
- Log 內會同時顯示 store 端與 verify 端的 JSON 原文，可直接做文字 diff
- 若 log 中 STORE 端顯示 `(no snapshot)`，代表上一輪 store 時尚未啟用 snapshot 功能（首次部署後的第一次 mismatch）

---

## 2026-02-28 根因診斷：前端角色偏置過濾造成 Checksum 崩潰

### 診斷依據

透過 `logs/checksum-mismatch.log` 的實際案例進行分析：

```
verify role counts : {"assistant":1, "user":7}
last store meta    : source=class-mpu-rest-dialog.php:216
store snapshot     : 4 assistants（對應 user[2]~user[5] 的回覆）
verify raw         : 7 user + 1 assistant（僅最後一輪 assistant 殘存）
```

實際對話有完整的 user/assistant 交替序列，但 VERIFY 端只收到 1 條 assistant。

### 根本原因

**`ukagaka-context.js` 與 `ukagaka-greeting.js` 中的 `maxAutoTalkHistory = 3` 過濾邏輯**：

```js
// 問題代碼（已移除）
const assistantMessages = mpuChatHistory.filter(msg => msg.role === "assistant");
if (assistantMessages.length > maxAutoTalkHistory) {
  mpuChatHistory = mpuChatHistory.filter((msg) => {
    if (msg.role === "assistant" && removed < toRemove) { ... return false; }
    return true;  // user 全部保留 ← 問題所在
  });
}
```

- 每次 auto-talk / context / greeting 發話後，若 `mpuChatHistory` 中 assistant 數量 > 3，就**單邊刪除最舊的 assistant**，但對應的 user 訊息全部保留
- 隨著對話進行，assistant 被逐輪刪除，最終形成「很多 user + 極少 assistant」的歷史結構
- STORE（由 dialog endpoint 在某次 auto-talk 觸發時寫入）記錄了當時完整的 4 條 assistant
- VERIFY（由 chat endpoint 在使用者下一次發言時執行）收到的卻是被挖空後只剩 1 條 assistant 的歷史
- 兩端 filtered JSON 不一致 → checksum mismatch

### 關於 `class-mpu-rest-dialog.php` 用同一 session_id 寫 checksum

- 這**不是 bug**，而是設計選擇：讓 auto-talk 與 user chat 共用同一對話脈絡
- 真正出錯的是前端歷史被角色偏置裁切，導致不同時刻的 store/verify 基準不一致
- 修正前端過濾後，此設計仍可保留

### 修正內容（2026-02-28）

移除 `ukagaka-context.js` 與 `ukagaka-greeting.js` 中的 `maxAutoTalkHistory` 角色偏置過濾邏輯，改為純窗口推移策略：

| 檔案 | 變更 |
|---|---|
| `js/ukagaka-context.js:438-454` | 刪除 `maxAutoTalkHistory` 過濾區塊 |
| `js/ukagaka-greeting.js:120-135` | 刪除 `maxAutoTalkHistory` 過濾區塊 |
| `js/ukagaka-chat.js:63` | 確認 `mpu_saveChatHistory()` 使用 `slice(-MPU_MAX_CHAT_HISTORY)`（原本已正確） |

- auto-talk 應答完整保留在 `mpuChatHistory`，不再單邊刪除
- 總量上限由 `mpu_saveChatHistory()` 的 `slice(-20)` 統一截斷
- backend 的 `slice(-10)` normalize 流程不變，orphaned assistant 由後端自然處理
- 執行 `npm run build` 更新 `js/dist/ukagaka-bundle.min.js`

### 設計架構說明（修正後）

```
auto-talk 發話
  → mpuChatHistory.push(assistant)        # 完整保留，不過濾
  → mpu_saveChatHistory() → slice(-20)   # localStorage 上限 20
  ↓
user 發送聊天
  → mpuChatHistory.push(user)
  → formData: slice(-10)                 # backend 只看最近 10 筆
  → backend normalize: 移除孤立 assistant  # orphaned auto-talk 在此自然消除
  → slice(-10) → filter(assistant) → checksum
```

STORE 與 VERIFY 兩端都經由相同的 `slice(-10) + normalize + filter(assistant)` 路徑，一致性恢復。

### 殘留觀察點

- `ukagaka-core.js:691`（`mpu_nextmsg` AJAX 路徑）原本就沒有角色偏置過濾，此次未動
- auto-talk 大量發話時，orphaned assistant 會佔用 `slice(-10)` 的部分窗口，但 backend normalize 會全部移除，不影響 checksum 正確性
- 若使用者長時間放置（auto-talk 累積多則），AI 在 user chat 中可能「不記得」先前的 auto-talk 內容，屬設計上的取捨，非 bug

---

## 2026-02-28 第二輪診斷：Auto-talk 歷史不完整 & window 全域化

### 新 mismatch log 案例

```
source=akismet-integration.php:92  stored_at=2026-02-28 11:57:58
STORE: [{"role":"assistant","content":"いいね、一緒に食べるの楽しみだね。"}]
VERIFY: []   ← verify history 只有 [user]，filter 後無 assistant
```

### 診斷：Touch / Decoration 互動未記錄歷史（根本缺口）

auto-talk 各路徑的 `mpuChatHistory` 覆蓋情況：

| 路徑 | push 到 mpuChatHistory？ | 寫入 checksum？ |
|---|---|---|
| `mpu_nextmsg`（auto-talk） | ✅ | ✅ |
| `mpu_chat_context`（頁面感知） | ✅ | ✅ |
| `mpu_greet_first_visitor`（打招呼） | ✅ | ✅ |
| `mpu_checkSpamEvent`（Bot/Akismet/Turnstile） | ✅ | ✅ |
| `handleDecorationClick`（裝飾物觸摸） | ❌ → **已修正** | ❌（touch 不寫 checksum，正確） |
| `handleTouchZone`（身體觸摸） | ❌ → **已修正** | ❌（touch 不寫 checksum，正確） |

### 修正內容（2026-02-28）

#### 1. `ghost/Frieren/frieren.js` — touch/decoration 加入歷史記錄

- `handleDecorationClick` `.then()` 成功分支加入：
  ```js
  window.mpuChatHistory.push({ role: "assistant", content: res.msg, timestamp: Date.now() });
  mpu_saveChatHistory();
  ```
- `handleTouchZone` `.then()` 成功分支同上

Touch 端點（`/touch/decoration`、`/touch/zone`）本身不寫 checksum，此修正純為讓 chat 模式的 AI 能「記得」觸摸互動。

#### 2. `window.mpuChatHistory` 全域化

由外部工具變更：將 `let mpuChatHistory = []`（`ukagaka-chat.js`）改為 `window.mpuChatHistory`，並在 `ukagaka-base.js` 初始化。

- 對 bundled 情境無功能差異（`let` 在同一份 script scope 已共用）
- 但使全域意圖更明確，並讓 `frieren.js`（獨立載入的 ghost script）也能透過 `window.mpuChatHistory` 正確存取

#### 3. 統一剩餘引用

`ukagaka-chat.js` 殘留的 `mpuChatHistory.*` 引用（line 427, 439, 504, 652, 500 的 `mpuChatModeActive`）全部補上 `window.` 前綴。

### Akismet 特定 mismatch 的可能時序

Akismet path 本身 DOES push 到 `mpuChatHistory`。新 log 中的 mismatch 可能成因：

- **孤立 user message**：mpuChatHistory 在 Akismet 觸發時含有未配對的 `{user}` 訊息（舊請求失敗但 rollback 未執行）→ backend 以 `[{user}, {assistant}]` 計算 checksum；之後某事件清掉了這個 user 訊息，下一輪 verify 時前端歷史中沒有對應 assistant
- checksum 已為觀測模式（非阻斷），此 mismatch 不影響功能，繼續觀察

### 排查提示（更新）

- 若 mismatch source 為 `frieren.js` 相關：確認已部署新版（含 touch push）
- 若 source 為 `akismet-integration.php`：檢查前端歷史在觸發時是否有孤立 user 訊息
- 觀測模式下 mismatch 只記 log 不中斷，可持續觀察 `logs/checksum-mismatch.log`

---

## 2026-02-28 第三輪修正：Checksum STORE type 欄位遺漏 & VERIFY 窗口不對稱

### 診斷依據（`logs/checksum-mismatch.log` 實際案例）

透過 log 比對，發現兩個獨立根因：

#### 根因 A：非 user-chat 端點的 STORE 丟棄 `type` 欄位

前端 push 到 `mpuChatHistory` 時各路徑均已標注正確 type：

| 路徑 | user type | assistant type |
|---|---|---|
| auto-talk（mpu_nextmsg） | `synthetic` | `auto_talk` |
| 頁面感知（chat/context） | `synthetic` | `context` |
| 首次問候（chat/greet） | `synthetic` | `greet` |
| Akismet/Bot 事件 | `synthetic` | `event` |
| 使用者互動（chat/user） | `chat` | `chat` |

`mpu_chat_integrity_filter_messages()` 只計入 `type === 'chat'` 的 assistant，因此 VERIFY 端正確排除 auto-talk / event / context / greet 類型的 assistant。

但以下四個 STORE 端點在重組 `$prior_history` 時，**未保留前端送來的 `type` 欄位**，且新產生的 assistant 也**未標注 type**（預設落回 `'chat'`），導致 STORE 的 checksum 包含了不該計入的 non-chat assistant：

- `class-mpu-rest-dialog.php` `nextmsg`
- `class-mpu-rest-chat.php` `chat/context`
- `class-mpu-rest-chat.php` `chat/greet`
- `akismet-integration.php` `mpu_store_spam_event_checksum`

**影響**：log mismatch #1（source=`class-mpu-rest-dialog.php:216`）中，STORE 記錄了 5 條 assistant（含 auto-talk/event），VERIFY 僅計入 2 條真實 user-chat assistant → checksum 不一致。

#### 根因 B：STORE 使用 10 件窗口，VERIFY 使用 20 件窗口

`prepare_user_chat_args()` 以 `array_slice($normalized_history, -20)` 建立 `$chat_history`，再將完整 20 件傳入 `mpu_chat_integrity_verify_history()`。但所有 STORE 端點皆以 `mpu_chat_integrity_slice_for_store($history, 10)` 只保留 10 件。

當對話超過 10 輪後，VERIFY 的 20 件窗口可看到比 STORE 的 10 件窗口更早的 `type='chat'` assistant → 計入筆數不同 → mismatch。

**確認依據**：log mismatch #2、#3 均顯示 `first diff (win): none`（即 `verify_windowed_checksum` = `expected`），代表只要將 VERIFY 也縮至 10 件，即可對齊。

### 修正內容（2026-02-28）

#### Fix A：四個 STORE 端點補上 `type` 保留邏輯

| 檔案 | 修正位置 | prior_history 循環 | 新 assistant type |
|---|---|---|---|
| `class-mpu-rest-dialog.php` | nextmsg checksum 寫入區 | `$entry['type']` 保留 | `'auto_talk'` |
| `class-mpu-rest-chat.php` | chat/context checksum 寫入區 | `$msg['type']` 保留 | `'context'` |
| `class-mpu-rest-chat.php` | chat/greet checksum 寫入區 | `$msg['type']` 保留 | `'greet'` |
| `akismet-integration.php` | `mpu_store_spam_event_checksum()` | `$hm['type']` 保留 | `'event'` |

修正後，STORE 端的 `filter_messages` 將與 VERIFY 端同樣只計入 `type='chat'` 的 assistant。

#### Fix B：VERIFY 窗口對齊至 10 件（不影響 LLM 上下文）

`$chat_history`（20 件）仍照常傳給 LLM，僅 verify 呼叫改用獨立的 10 件窗口：

```php
// before — 20 件傳入 verify，與 store 的 10 件窗口不對稱
$integrity_check = mpu_chat_integrity_verify_history($chat_session_id, $chat_history);

// after — LLM 仍收 20 件；verify 專用 10 件（與 store 對齊）
$history_for_verify = mpu_chat_integrity_slice_for_store($chat_history, 10);
$integrity_check = mpu_chat_integrity_verify_history($chat_session_id, $history_for_verify);
```

修正位置：`class-mpu-rest-chat.php` `prepare_user_chat_args()`（verify 呼叫點前）。

### 關於 `dialogs/Frieren.json` fallback 的釐清

- fallback（`$use_fallback = true`）路徑本身**不寫 checksum**（`!$use_fallback` 條件防護），此行為正確
- 前端在 mpu_nextmsg 成功回應後，不論是 LLM 或 fallback 內建台詞，均以 `type: 'auto_talk'` push 到 mpuChatHistory → 前端類型標注一致
- Fix A 修正後，fallback 前後的 LLM 成功 auto-talk 也會以 `type='auto_talk'` 正確 STORE → 間接影響消除

### 修正後預期行為

- STORE（所有非 user-chat 端點）：只計入前端歷史中 `type='chat'` 的 assistant，新 auto-talk / event / context / greet assistant 不進入 checksum
- VERIFY（user-chat）：同上，使用相同的 10 件窗口 + normalize + filter('chat') 路徑
- 兩端 checksum 邏輯完全對稱，mismatch 應大幅減少（主要殘留來源：孤立 user 訊息、跨 session 邊緣情境）

---

## 2026-02-28 第四輪修正：前端顯示/記憶不一致（fallback / 傳統路徑 / exit dialog 未記錄）

### 診斷依據

透過 `logs/checksum-mismatch.log` 與對話體感分析，確認三個共同造成「使用者看得到、AI 記不住」斷層的前端路徑：

### 根因 A：`mpu_nextmsg_fallback()` 顯示但不記錄

`js/ukagaka-core.js:940`—當 LLM 失敗（rate limit、無 msg、error）時呼叫 fallback，從本地對話清單顯示隨機台詞，但未 push 到 `window.mpuChatHistory`。使用者若因此看見並回應該台詞，下一輪互動對話 AI 完全不知道曾發生什麼。

### 根因 B：傳統非 LLM 對話路徑（`mpuOllamaReplaceDialogue === false`）不記錄

`js/ukagaka-core.js:850`—未啟用 LLM 自發對話時走本地對話清單，同樣只顯示不記錄。

### 根因 C：`mpu_toggleChatMode(false)` exit random dialog 不記錄

`js/ukagaka-chat.js:210`—關閉互動對話模式後 5 秒，會隨機顯示一句本地對話（作為「再見」台詞），但未 push 到 `mpuChatHistory`。使用者若稍後重新開啟對話並回應這句話，AI 無法建立關聯。

### 次要根因 D：LLM 成功路徑存入 `res.msg` 而非顯示用 `out`

`js/ukagaka-core.js:706`—顯示時使用 `out = res.msg + auto_msg`，但 history 只存 `res.msg`，造成「看到的句子比記憶的長」體感差異。

### 修正內容（2026-02-28）

#### Fix 1：LLM 成功路徑 — 統一存入顯示字串

```js
// before
content: res.msg

// after（與 UI 顯示內容 out = res.msg + auto_msg 對齊）
content: out
```

修正位置：`js/ukagaka-core.js` LLM 成功路徑的 `mpuChatHistory.push`（assistant）。

#### Fix 2：傳統非 LLM 路徑 — 補成對 push

```js
// 補在顯示後、auto-talk 計時器啟動前
if (out && typeof window.mpuChatHistory !== "undefined" && Array.isArray(window.mpuChatHistory)) {
  window.mpuChatHistory.push({ role: "user", content: "（独り言）", type: "synthetic", timestamp: Date.now() });
  window.mpuChatHistory.push({ role: "assistant", content: mpu_unescapeHTML(out), type: "auto_talk", timestamp: Date.now() });
  if (typeof mpu_saveChatHistory === "function") mpu_saveChatHistory();
}
```

修正位置：`js/ukagaka-core.js:928`（傳統路徑顯示後）。

#### Fix 3：`mpu_nextmsg_fallback()` — 補成對 push

同 Fix 2 的成對 push，加在 fallback 函式的 `mpu_showmsg(400)` 之後。

修正位置：`js/ukagaka-core.js:1016`。

#### Fix 4：exit random dialog — 補成對 push

```js
const exitContent = mpu_unescapeHTML(msgArr[randomIdx] + auto);
mpu_typewriter(exitContent, "#ukagaka_msg");
if (exitContent && Array.isArray(window.mpuChatHistory)) {
  window.mpuChatHistory.push({ role: "user", content: "（独り言）", type: "synthetic", timestamp: Date.now() });
  window.mpuChatHistory.push({ role: "assistant", content: exitContent, type: "auto_talk", timestamp: Date.now() });
  if (typeof mpu_saveChatHistory === "function") mpu_saveChatHistory();
}
```

修正位置：`js/ukagaka-chat.js:213`（exit random dialog 顯示後）。

### 設計選擇

- **`type: 'auto_talk'` 流用**：不引入新 type，`auto_talk` 已在 `class-mpu-rest-chat.php` 白名單（line 634）且被 `mpu_chat_integrity_filter_messages()` 自動排除於 checksum 計算之外，零副作用。
- **成對寫入原則**：`synthetic user + assistant` 配對，確保 backend normalize 不會因孤立 assistant 被丟棄而失去脈絡。
- **`mpu_unescapeHTML(out)`**：本地對話清單可能含 HTML entities，存入 history 前先展開為純文字，LLM 讀取更自然。

### 修正後預期行為

- 所有前端顯示路徑（LLM 成功 / 傳統 / fallback / exit dialog）均寫入 `mpuChatHistory`
- 使用者看到並回應的任何台詞，下一輪互動對話 AI 均能在上下文中讀到
- Checksum 不受影響（`auto_talk` 不進入 filter）
