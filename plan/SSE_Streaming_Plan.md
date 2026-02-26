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
