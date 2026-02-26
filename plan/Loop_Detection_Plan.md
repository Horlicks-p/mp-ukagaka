# Tool Call Loop Detection 實作計畫（Factory 後續穩定性優化）

此計畫旨在為 `mp-ukagaka` 的工具呼叫迴圈（tool call loop）加入更明確的偵測與防護機制，降低 LLM 在多輪工具調用時陷入重複呼叫（同一工具 + 相同參數反覆執行）的風險。

---

## ✅ 施作進度摘要 (2026-02-26)

- **狀態**：全部完成 (Completed)
- **外掛版本**：2.10.0
- **核心變更**：
  1. 新增 `includes/llm/tool-loop-guard.php` 提供正規化、簽名與偵測邏輯。
  2. 在 `utility-functions.php` 定義 `MPU_MAX_TOOL_REPEAT_SAME_CALL` (預設 2)。
  3. 整合至所有 AI Provider (OpenAI, Gemini, Claude, Ollama) 的 `generate_text` 與 `generate_chat` 方法。
  4. 實作了遞迴排序與數值字串化的正規化策略，確保偵測精準度。
  5. 任一工具觸發迴圈門檻時，即刻中止該輪對話並回傳 `tool_call_loop_detected`。
  6. 更新了開發者指南與模式備忘錄。

---

## 🏗️ 核心設計實作

### 1. 偵測機制 - ✅ 已完成
- 採用 Signature 比對：`{tool_name}:{md5(normalized_args_json)}`。
- 追蹤 `consecutive_count`（連續重複次數）。

### 2. 正規化策略 - ✅ 已完成
- `mpu_normalize_tool_args()`：處理 JSON/Array/Object 轉換。
- `mpu_recursive_normalize_array()`：對 key 進行 `ksort`，將數值與布林轉為字串，確保簽名穩定。

### 3. 門檻與錯誤碼 - ✅ 已完成
- 門檻：2 次連續相同呼叫即中止。
- 錯誤碼：`tool_call_loop_detected`。
- 錯誤資料：包含 `tool_name`, `repeat_count`, `turn`, `provider` 與 `signature`。

---

## 🛠️ 實作步驟執行紀錄

### 步驟 1：建立 loop guard helper - ✅ 已完成
- 建立 `tool-loop-guard.php` 並在 `mp-ukagaka.php` 註冊。
- 定義全域常數。

### 步驟 2：先接 OpenAI 驗證 - ✅ 已完成
- OpenAI 的 `generate_text` 與 `generate_chat` 已受保護。

### 步驟 3：擴展到其餘 Provider - ✅ 已完成
- Gemini, Claude, Ollama 已全面套用相同防護邏輯。

### 步驟 4：收斂與文件補充 - ✅ 已完成
- 更新 `DEVELOPER_GUIDE.md`。
- 更新 `Ai_Engine_Patterns_Memo.md`。
- 本計畫更新為完成狀態。
