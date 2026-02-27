# SSE Streaming Checksum 400 漏洞審計報告

我已完整審閱以下檔案的 checksum 生命週期：

- [chat-integrity.php](file:///d:/XAMPP/htdocs/wordpress/wp-content/plugins/mp-ukagaka/includes/llm/chat-integrity.php)（核心 checksum 邏輯）
- [class-mpu-rest-chat.php](file:///d:/XAMPP/htdocs/wordpress/wp-content/plugins/mp-ukagaka/includes/rest/class-mpu-rest-chat.php)（[prepare_user_chat_args](file:///d:/XAMPP/htdocs/wordpress/wp-content/plugins/mp-ukagaka/includes/rest/class-mpu-rest-chat.php#473-849) verify 端 + [user_chat](file:///d:/XAMPP/htdocs/wordpress/wp-content/plugins/mp-ukagaka/includes/rest/class-mpu-rest-chat.php#850-959) / [user_chat_stream](file:///d:/XAMPP/htdocs/wordpress/wp-content/plugins/mp-ukagaka/includes/rest/class-mpu-rest-chat.php#960-1072) store 端）
- [ukagaka-chat.js](file:///d:/XAMPP/htdocs/wordpress/wp-content/plugins/mp-ukagaka/js/ukagaka-chat.js)（前端歷史管理 + SSE reader）

---

## 整體流程摘要

```
前端送出 history (slice -10) + user_message
       ↓
後端 verify：sanitize_textarea_field(history) → normalize → slice -10 → compute_checksum → compare
       ↓
後端 store：chat_history + user + assistant → slice_for_store(10) → compute_checksum → set_transient
       ↓
前端 onDone：push assistant to mpuChatHistory → saveChatHistory (slice -20)
       ↓
下一輪：前端 slice -10 → 送出 → 後端 verify
```

---

## 🔴 高風險漏洞（極可能造成 400）

### 漏洞 1：前端存 `timestamp`，但後端 verify 端只保留 `role` + `content`

**位置**：[ukagaka-chat.js:426-430](file:///d:/XAMPP/htdocs/wordpress/wp-content/plugins/mp-ukagaka/js/ukagaka-chat.js#L426-L430) / [ukagaka-chat.js:496-500](file:///d:/XAMPP/htdocs/wordpress/wp-content/plugins/mp-ukagaka/js/ukagaka-chat.js#L496-L500)

前端 `mpuChatHistory.push()` 時附帶 `timestamp` 欄位：
```js
mpuChatHistory.push({ role: "user", content: message, timestamp: Date.now() });
mpuChatHistory.push({ role: "assistant", content: finalMsg, timestamp: Date.now() });
```

**前端送出的 FormData：**
```js
formData.append("history", JSON.stringify(mpuChatHistory.slice(-10)));
```

這意味著每條歷史訊息都帶有 `timestamp` 欄位。

**後端 verify 端**（[prepare_user_chat_args](file:///d:/XAMPP/htdocs/wordpress/wp-content/plugins/mp-ukagaka/includes/rest/class-mpu-rest-chat.php#473-849) L563-576）：只取 `role` + `content`（sanitize 後），丟棄 `timestamp`。

**後端 store 端**（[user_chat](file:///d:/XAMPP/htdocs/wordpress/wp-content/plugins/mp-ukagaka/includes/rest/class-mpu-rest-chat.php#850-959) L946-948 / [user_chat_stream](file:///d:/XAMPP/htdocs/wordpress/wp-content/plugins/mp-ukagaka/includes/rest/class-mpu-rest-chat.php#960-1072) L1053-1055）：組裝的 `$raw_history` 來自 `$args['chat_history']`（已清除 `timestamp`），新增的 user/assistant 條目也不含 `timestamp`。

**結論**：`timestamp` 欄位在 verify 端被丟棄，而 store 端本來就沒存。**兩端一致，不會造成 checksum 差異。** ✅ 安全。

---

### 漏洞 2：SSE streaming 的截斷 `$result` 與前端 [onDone](file:///d:/XAMPP/htdocs/wordpress/wp-content/plugins/mp-ukagaka/js/ukagaka-chat.js#489-517) 存入歷史的 `data.msg` 不對稱

**風險等級：🔴🔴🔴 高**

**位置**：
- 後端 [user_chat_stream L1029-1038](file:///d:/XAMPP/htdocs/wordpress/wp-content/plugins/mp-ukagaka/includes/rest/class-mpu-rest-chat.php#L1029-L1038)：長度截斷
- 後端 [user_chat_stream L1052](file:///d:/XAMPP/htdocs/wordpress/wp-content/plugins/mp-ukagaka/includes/rest/class-mpu-rest-chat.php#L1052)：`sanitize_textarea_field($result)` 寫入 checksum
- 後端 [user_chat_stream L1065-1068](file:///d:/XAMPP/htdocs/wordpress/wp-content/plugins/mp-ukagaka/includes/rest/class-mpu-rest-chat.php#L1065-L1068)：`event: done` 發送截斷後的 `$result`
- 前端 [ukagaka-chat.js:494-498](file:///d:/XAMPP/htdocs/wordpress/wp-content/plugins/mp-ukagaka/js/ukagaka-chat.js#L494-L498)：[onDone](file:///d:/XAMPP/htdocs/wordpress/wp-content/plugins/mp-ukagaka/js/ukagaka-chat.js#489-517) 存入 `data.msg`

**流程分析**（SSE 路徑）：

```
後端：$full_response_content = 完整串流累積
      → thinking 過濾
      → $result = $full_response_content
      → 截斷到 max_length（附帶 "..."）
      → $integrity_result = sanitize_textarea_field($result)       ← checksum 用的
      → store_history(..., $integrity_result)
      → event:done → { msg: $result }                              ← 前端收到截斷版本
前端：onDone → push { role:"assistant", content: data.msg }        ← 存截斷版本
```

**下一輪前端送出：** [history](file:///d:/XAMPP/htdocs/wordpress/wp-content/plugins/mp-ukagaka/includes/llm/chat-integrity.php#93-100) 內 assistant 的 content 是截斷後版本 ✅

**下一輪後端 verify：** `sanitize_textarea_field(wp_unslash(msg['content']))` → 對截斷版本做 sanitize ✅

**潛在問題：`sanitize_textarea_field` 的幂等性。** 如果 `$result`（截斷版）經過一次 `sanitize_textarea_field` 後存入 checksum，前端再送回時後端又 `sanitize_textarea_field(wp_unslash(...))` 一次 — 只要 `sanitize_textarea_field` 是幂等的（它應該是），就安全。

> [!WARNING]
> **但 `wp_unslash()` 可能造成差異！** 如果 AI 回覆包含反斜線 `\`，`wp_unslash()` 會吃掉它（例如 `\\n` → `\n`，`\\` → `\`）。後端 store 時做 `sanitize_textarea_field($result)` 但**沒有先做 `wp_unslash()`**，而 verify 端是 `sanitize_textarea_field(wp_unslash(msg['content']))`。

**確認**：verify 端（L570）：
```php
$content = sanitize_textarea_field(wp_unslash($msg['content']));
```

store 端（L945/L1052）：
```php
$integrity_result = sanitize_textarea_field($result);
```

store 端**沒有 `wp_unslash()`**。如果 `$result` 本身不含 WordPress magic quotes（它不應該，因為它來自 AI API 回應，不是用戶輸入），那麼 `wp_unslash` 不會改變它。**但前端送回來的 `msg['content']` 經過 `JSON.stringify` → POST → WordPress 的 magic quotes**，所以 `wp_unslash()` 在 verify 端移除 WordPress 加上的引號是正確的。

**結論：** 只要 AI 回覆不包含反斜線字元，此處安全。但如果 AI 回覆包含 `\`（例如 code block、正則表達式），有可能造成 mismatch。**風險中等**。

---

### 漏洞 3：前端 `MPU_MAX_CHAT_HISTORY = 20` vs 後端 `slice -10` 的窗口差

**風險等級：✅ 安全（無問題）**

前端儲存最多 20 條歷史，但送出時只取最後 10 條。後端 verify 和 store 都統一用 10 條窗口。沒有不對稱。

---

### 漏洞 4：SSE 串流中斷後，前端仍存入部分回應到歷史

**風險等級：🔴 高**

**位置**：[ukagaka-chat.js:517-523](file:///d:/XAMPP/htdocs/wordpress/wp-content/plugins/mp-ukagaka/js/ukagaka-chat.js#L517-L523)（`onError`）

```js
onError: (error) => {
  mpuChatRequesting = false;
  $input.prop("disabled", false);
  if (mpuChatModeActive) $input.focus();
  mpu_typewriter("（…連線好像有點問題…）", "#ukagaka_msg");
  mpuLogger.error("SSE Error:", error);
}
```

**問題**：`onError` 不會從 `mpuChatHistory` 中移除已經 push 的 user 訊息（L426-430 在請求發出前就 push 了）。

**場景：**
1. 用戶發送訊息 → `mpuChatHistory` push `{ role: "user", content: "xxx" }`
2. SSE 串流啟動，收到部分 delta
3. 串流中途出錯 → `onError` 觸發
4. 後端因為 `connection_aborted()` 或錯誤而**不寫 checksum**
5. 前端歷史裡多了一條 user 訊息**但沒有對應的 assistant 回覆**
6. 下一輪發送時，歷史包含這條「孤立 user」訊息
7. 後端 verify 時用的歷史（含孤立 user）與上一輪 store 的歷史（不含孤立 user）不一致
8. → **400 Checksum mismatch！**

> [!CAUTION]
> 這是目前最嚴重的漏洞。只要 SSE 串流中途任何失敗（網路斷線、伺服器超時、provider 錯誤），下一輪必然 400。

**修復建議：** 在 `onError` 中 pop 掉最後一條 user 訊息（如果它沒有對應的 assistant 回覆）：

```js
onError: (error) => {
  // 移除已 push 但未成功的 user 訊息
  if (mpuChatHistory.length > 0 && mpuChatHistory[mpuChatHistory.length - 1].role === 'user') {
    mpuChatHistory.pop();
    mpu_saveChatHistory();
  }
  // ... 原有錯誤處理
}
```

---

### 漏洞 5：SSE `onDone` 使用 `data.msg`（截斷後），但 `fullResponse`（未截斷）已被前端即時顯示

**風險等級：✅ 安全（已處理）**

前端 `onDone`（L494-505）存的是 `data.msg`（= 後端截斷後版本），與後端 checksum 一致。前端即時顯示用的是 `fullResponse`（累積 delta），但不會寫入歷史。✅

---

### 漏洞 6：同步 fallback 路徑 (`chat/user`) 的歷史 push 時機與 SSE 不同

**風險等級：✅ 安全（不涉及跨路徑切換）**

同步路徑也是先 push user（L426），再在 `.then()` 中 push assistant。如果 `.catch()` 被觸發，同樣有漏洞 4 的問題。但同步路徑 400 機率低（不涉及串流中斷），且兩條路徑的 session_id 一樣，checksum 邏輯也一樣，不會互相干擾。

**同步路徑也有漏洞 4 的問題**：`.catch()` 時也沒清除 user 訊息。但同步路徑的 error 主要來自完全性的網路失敗（此時後端也不會寫 checksum），風險較 SSE 低但仍存在。

---

### 漏洞 7：`mpu_chat_integrity_filter_messages()` 只計算非 user 訊息的 checksum

**風險等級：🟡 中（間接風險）**

[chat-integrity.php:38-58](file:///d:/XAMPP/htdocs/wordpress/wp-content/plugins/mp-ukagaka/includes/llm/chat-integrity.php#L38-L58)

```php
if ($role === '' || $role === 'user') continue;  // 跳過 user 訊息
```

checksum 只基於 assistant 訊息。這意味著**如果 user 訊息數量變化但 assistant 不變**，checksum 仍會匹配。

**但**，`slice_for_store()` 和 verify 端的 normalization 會影響哪些 assistant 訊息進入計算。如果前端多了一條 user 訊息（漏洞 4），可能導致 slice 窗口不同，進而影響哪些 assistant 訊息在窗口內。

---

### 漏洞 8：`AbortController` abort 後的前端處理

**風險等級：🟡 中**

[ukagaka-chat.js:420-423](file:///d:/XAMPP/htdocs/wordpress/wp-content/plugins/mp-ukagaka/js/ukagaka-chat.js#L420-L423)

```js
if (mpuChatAbortController) {
  mpuChatAbortController.abort();
}
```

用戶快速連點時，前一個請求被 abort。`mpuFetchSSE` 的 catch 會捕獲 `AbortError` 並 log，**但不會觸發 `onError`**（L369-370），這意味著不會清除已 push 的 user 訊息。

然而注意：`abort` 發生時，新的 user 訊息已經被 push（L426-430），所以歷史中會有兩條連續的 user 訊息（舊的被 abort 的 + 新的）。如果後端舊請求在 abort 時沒有寫 checksum，那下一輪 verify 時歷史中有多餘的未配對 user 訊息，但因為 checksum 只看 assistant，所以除非窗口滑動改變了 assistant 的可見範圍，否則可能不受影響。

**但是：** 如果 abort 的舊請求其實已經在後端完成了 store_history（因為 `ignore_user_abort(true)` 讓後端繼續執行），而前端沒有收到 `onDone`，前端歷史會缺少那條 assistant 回覆，造成不對稱 → **400！**

> [!IMPORTANT]
> `ignore_user_abort(true)` + `AbortController.abort()` 的組合可能造成：後端寫了 checksum（包含 assistant），但前端歷史缺少該 assistant。下一輪必然 400。

---

## 📋 漏洞總結

| # | 漏洞描述 | 風險 | 是否需修復 |
|---|---------|------|-----------|
| 4 | SSE `onError` 不移除已 push 的 user 訊息 | 🔴 高 | **是** |
| 8 | `abort` + `ignore_user_abort` 造成後端寫 checksum 但前端缺 assistant | 🔴 高 | **是** |
| 2 | `wp_unslash()` 不對稱（store 端無、verify 端有） | 🟡 中 | 建議修復 |
| 7 | `filter_messages` 只算 assistant，間接受窗口偏移影響 | 🟡 中 | 追蹤 |
| 1 | `timestamp` 欄位差異 | ✅ 安全 | 否 |
| 3 | `MAX_HISTORY 20` vs `slice 10` | ✅ 安全 | 否 |
| 5 | `fullResponse` vs `data.msg` | ✅ 安全 | 否 |
| 6 | 同步 fallback 也有 push user 問題 | 🟡 低 | 可順帶修復 |

---

## 🔧 建議修復方向

### 修復 1：前端錯誤回退 user 訊息（修漏洞 4 + 6）

在 `mpu_sendUserMessage()` 中，無論 SSE 或同步路徑，在 error/catch 時 pop 最後一條 user 訊息：

```js
// 在 onError / catch 中加入
if (mpuChatHistory.length > 0 && mpuChatHistory[mpuChatHistory.length - 1].role === 'user') {
  mpuChatHistory.pop();
  mpu_saveChatHistory();
}
```

### 修復 2：abort 時也 pop user 訊息（修漏洞 8）

在 `mpuFetchSSE` 的 `AbortError` catch 中也通知外層清除：

可在 handlers 新增 `onAbort` callback，或直接在 abort catch 中 pop。

### 修復 3（可選）：後端 abort 時不寫 checksum

目前後端已有 `!connection_aborted()` 檢查。但由於 `ignore_user_abort(true)`，`connection_aborted()` 可能無法即時反映前端 abort 狀態（取決於檢查時機）。如果 provider streaming 已完成且 checksum 寫入在 abort 之前完成，此檢查不會起作用。

建議在 streaming 完成後、checksum 寫入前再次檢查 `connection_aborted()`，目前的代碼已有此檢查（L1050），這部分已正確。

