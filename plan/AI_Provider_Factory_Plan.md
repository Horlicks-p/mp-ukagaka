# AI Provider Factory Pattern 實作計畫（Provider Helper 後續第二階段）

此計畫旨在將 `mp-ukagaka` 目前分散在 `includes/llm/ai-functions.php` 與 `includes/ajax/chat-api-handlers.php` 的 Provider 分支邏輯（`switch ($provider)`），重構為可擴充的 AI Provider 工廠模式（Factory Pattern）。

此重構是承接既有 **Provider 共用 Helper（第一階段）** 的自然下一步，目標是在不改變對外行為的前提下，完成 Provider 流程收斂、降低重複、提升後續功能（SSE / Loop Guard / 新 Provider）導入效率。

---

## ✅ 施作進度摘要 (2026-02-26)

- **狀態**：全部完成 (Completed)
- **外掛版本**：2.10.0
- **核心變更**：
  1. 建立 `includes/llm/providers/` OO 架構。
  2. `MPU_AI_Provider_Factory` 接管所有 AI 實例化需求。
  3. `MPU_REST_Test` 完成重構，大幅簡化 Controller 邏輯。
  4. `mpu_call_ai_api()` 與 `mpu_call_ai_api_with_messages()` 已透過 Factory 轉發。
  5. 8 個舊 API 函式已轉為相容層 Wrapper。
  6. 修正了 `handle_api_error` 的參數 bug 並保留了 provider-specific 錯誤碼。
  7. 增加了呼叫端 Slug 正規化防禦。

---

## 🏗️ 核心架構設計

### 1. Provider 介面（Interface）- ✅ 已完成

- 檔案：`includes/llm/providers/interface-mpu-ai-provider.php`
- 方法：`get_slug()`, `supports()`, `generate_text()`, `generate_chat()`, `test_connection()`

### 2. Base Provider（共用骨架）- ✅ 已完成

- 檔案：`includes/llm/providers/class-mpu-ai-provider-base.php`
- 功能：標準化錯誤處理（`handle_api_error`）、FEATURE 常數定義。

### 3. Provider 實作類別 - ✅ 已完成

- `Gemini`: `class-mpu-ai-provider-gemini.php` (預設 gemini-2.5-flash)
- `OpenAI`: `class-mpu-ai-provider-openai.php`
- `Claude`: `class-mpu-ai-provider-claude.php`
- `Ollama`: `class-mpu-ai-provider-ollama.php` (含思考標籤過濾與 endpoint 驗證)

### 4. Provider Factory（唯一建立入口）- ✅ 已完成

- 檔案：`includes/llm/providers/class-mpu-ai-provider-factory.php`

---

## 🛠️ 實作步驟執行紀錄

### 步驟 1：建立骨架 - ✅ 已完成

- 建立 `providers/` 目錄、Interface、Base 與 Factory。
- 建立 `bootstrap.php` 並於 `mp-ukagaka.php` 註冊。

### 步驟 2：接管 REST Test - ✅ 已完成

- 遷移 provider-specific 測試邏輯至類別方法。
- `MPU_REST_Test` 現在能一致地處理 `http_status` 與 `raw_body` 偵錯資訊。

### 步驟 3：接管單輪呼叫入口 `mpu_call_ai_api()` - ✅ 已完成

- 重構主流程，保留快取與統計邏輯。
- 補強 Slug 正規化防止匹配失敗。

### 步驟 4：接管多輪呼叫入口 `mpu_call_ai_api_with_messages()` - ✅ 已完成

- 遷移 Tool Call Loop 邏輯至各類別。
- 統一參數陣列結構。

### 步驟 5：清理與收斂 - ✅ 已完成

- 建立薄包裝 (Thin Wrappers) 確保向下相容性。
- 更新預設模型配置（如 Gemini 2.5 系列）。
- 統一版本號與參考連結。

---

## ⚠️ 後續擴充指引

若要新增 Provider（例如 `deepseek`）：

1. 建立 `class-mpu-ai-provider-deepseek.php` 繼承 `MPU_AI_Provider_Base`。
2. 在 `MPU_AI_Provider_Factory` 中註冊該 slug。
3. 在 `bootstrap.php` 中 `require_once` 該檔案。
