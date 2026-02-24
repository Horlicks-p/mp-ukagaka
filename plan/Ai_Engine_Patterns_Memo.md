# AI Engine 參考模式備忘錄

這份備忘錄整合了從 `ai-engine` 分析出對 `mp-ukagaka` 最有參考價值的架構與安全模式，並依據改動成本進行分類排序。

## ✅ 已實裝

- **自動更新 Nonce 機制 (Automatic Nonce Refresh)**
  全域攔截 `/mp-ukagaka/v1/` 請求的 `rest_post_dispatch`，當 nonce 進入老化期時回傳 `new_token`，低成本且完美解決長時間掛機的 403 錯誤。

---

## 🟢 低成本（可直接移植）

### ✅ 1. UTF-8 Safe JSON Encoding

[json_encode()](file:///d:/XAMPP/htdocs/wordpress/wp-content/plugins/ai-engine/classes/engines/core.php#47-99) 加上 `JSON_INVALID_UTF8_SUBSTITUTE` 旗標，防止因為使用者貼上 Word 特殊文字或奇怪字元時，API 請求發生靜默失敗。

- **改動位置**：[ai-functions.php](file:///d:/XAMPP/htdocs/wordpress/wp-content/plugins/mp-ukagaka/includes/llm/ai-functions.php) 的 [json_encode](file:///d:/XAMPP/htdocs/wordpress/wp-content/plugins/ai-engine/classes/engines/core.php#47-99) 呼叫統一換成 [json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE)](file:///d:/XAMPP/htdocs/wordpress/wp-content/plugins/ai-engine/classes/engines/core.php#47-99)。

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

### 5. AI Provider 的工廠模式 (Factory Pattern)

建立 `MPU_AI_Provider_Factory::get_provider()`，讓所有 LLM 供應商（OpenAI, Gemini, Claude, Ollama）實作標準介面。這能統一 [rest-test.php](file:///d:/XAMPP/htdocs/wordpress/wp-content/plugins/mp-ukagaka/includes/rest/rest-test.php) 與 [ai-functions.php](file:///d:/XAMPP/htdocs/wordpress/wp-content/plugins/mp-ukagaka/includes/llm/ai-functions.php)，未來新增模型（如 DeepSeek）會非常容易。

### 6. 伺服器發送事件串流 (SSE Streaming)

在後端解析模型回傳的 Streaming chunk，並透過 `text/event-stream` 傳遞給前端，實現「打字機效果」，解決長訊息（如思考時間長的模型）的等待體驗問題。

### 7. Object-Oriented Routing (物件導向路由結構)

將目前的 procedural 寫法改為 Base Class 繼承，將權限檢查、錯誤回傳格式與 Nonce 邏輯封裝在基礎的 REST Controller 中。

### 8. Request-Scoped State Reset

確保每次 API 請求前（或開始時）清空前一次請求殘留的狀態（如 Streaming buffer、Function call 結果）。如果未來加上 SSE Streaming，這個模式必須建立以防狀態污染。

### 9. Messages Integrity Checksum (對話防篡改)

Chat 送出前計算非使用者訊息（assistant、system role）的 MD5 並存入 transient，下次請求驗證。防止前端修改 AI 的歷史回答來誘發 Prompt Injection。

- **改動位置**：[user-chat-handler.php](file:///d:/XAMPP/htdocs/wordpress/wp-content/plugins/mp-ukagaka/includes/rest/chat/user-chat-handler.php) 中計算 checksum
  ```php
  $checksum = md5(json_encode(array_filter($messages, fn($m) => $m['role'] !== 'user')));
  set_transient('mpu_chat_checksum_' . $session_id, $checksum, HOUR_IN_SECONDS);
  ```

### 10. Recursive Tool Call 的 maxDepth 迴圈防護

目前 Abilities API 若一再要求呼叫工具，可能會無限迴圈。需在 `abilities-integration.php` 的工具執行循環加上 `maxDepth`（如預設 3 次）計數器與 `loop_detected()` 防護。

---

## 🔴 高成本（僅供參考）

### 11. 型別化的 Query 物件體系

將文字、圖像、Embedding 等 API 請求分別封裝成獨立 Class，以解決目前 [mpu_call_ai_api()](file:///d:/XAMPP/htdocs/wordpress/wp-content/plugins/mp-ukagaka/includes/llm/ai-functions.php#13-97) 參數過於肥大且混雜的問題。

### 12. 動態 Model 載入

模型列表由 API 端點自動載入或透過各 Engine 的 [retrieve_models()](file:///d:/XAMPP/htdocs/wordpress/wp-content/plugins/ai-engine/classes/engines/core.php#671-674) 抓取，廢棄目前在 `options_page_llm.php` 硬編碼模型選單的作法。
