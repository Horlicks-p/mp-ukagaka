# 統一歷史記憶計畫：讓芙莉蓮記住所有互動

**建立日期：** 2026-02-28（v2 修訂：2026-02-28，採納 Codex 審查意見；v3 補充：2026-02-28，加入 SPA 生命週期條件）
**優先度：** 中（功能增強，非緊急修復）
**目標：** 使用者在任何時間點打開對話視窗時，芙莉蓮都能記得最近 20 則互動（含 auto-talk、touch、greet、bot 感知、user chat），且時序完整保留。

**生命週期策略：**

- 同一頁生命週期（SPA 內路由切換）→ 保留記憶（最多 40 entries）
- F5 / 瀏覽器重整（reload）→ 清空 mpu_chat_history 與 mpu_chat_session_id，重新開始

---

## 0. 需求情境（使用者確認的 5 個場景）

| #   | 場景                                                                              |
| --- | --------------------------------------------------------------------------------- |
| 1   | 自言自語 → Bot 感知 → user 回饋 → 芙莉蓮記得 bot 感知時說的話 → 對話持續          |
| 2   | 自言自語 → 頁面感知 → user 回饋 → 芙莉蓮記得感知時說的話 → 對話持續               |
| 3   | 自言自語 → user 觸摸裝飾品/身體 → 芙莉蓮反饋 → user 根據反饋再回 → 對話持續       |
| 4   | 自言自語 → user 接著回饋 → 芙莉蓮記得自語內容 → 對話持續                          |
| 5   | Bot 感知 → user 回饋 → 芙莉蓮記得 → user 觸摸 → 芙莉蓮反饋 → user 再回 → 對話持續 |

---

## 1. v1 計畫缺陷（Codex 審查）

| 編號 | 問題                                                                                | 嚴重度   |
| ---- | ----------------------------------------------------------------------------------- | -------- |
| C1   | 「記住 20 則」目標 vs 實作「chat 10 + 非 chat 8」，數字不一致                       | 需求對齊 |
| C2   | chatTurns / activity_memory 分兩通道，模型只看到兩段摘要，時序丟失，情境 5 無法支援 | 架構錯誤 |
| C3   | activity_memory 完全由前端送入，直接注入 system prompt，來源真實性無機制            | 信任邊界 |

---

## 2. 核心設計修訂：單一統一時間軸 + Synthetic User 錨點

### 2.1 根本問題

LLM API 要求嚴格交替 `user → assistant → user → ...`。auto-talk / touch 的 assistant 回應前面沒有 user，normalize 會把它移除，LLM 永遠看不到這些記憶。

### 2.2 解法：Synthetic User 錨點

每個非 user-chat 互動，**在 assistant 回應前插入一個 synthetic user 訊息**作為錨點：

```
（synthetic）user: "（芙莉蓮の独り言）"
              ↓ 正常的 user→assistant pair
assistant: "今日のお茶は美味しい..."    ← 不再是孤立 assistant，normalize 保留

（synthetic）user: "（装飾品に触れた）"
assistant: "あ、それは私が昔持っていた…"

user: "さっきお茶が美味しいって言ってたけど..."   ← 真實 user 訊息
assistant: "うん、今日のは特別美味しかった…"      ← LLM 看到了完整時序！
```

這讓情境 5 的複雜交錯也能完整保留：LLM 收到的是**有序的、語義清晰的單一 messages 陣列**。

### 2.3 Synthetic 訊息標籤（依角色語言設定）

| 互動類型           | Synthetic user 標籤（日語）  | 中文備選             |
| ------------------ | ---------------------------- | -------------------- |
| `auto_talk`        | `（芙莉蓮の独り言）`         | `（芙莉蓮自言自語）` |
| `greet`            | `（訪問者が来た）`           | `（訪客到來）`       |
| `context`          | `（ページの内容を感知した）` | `（感知到頁面內容）` |
| `event`            | `（イベントを感知した）`     | `（感知到系統事件）` |
| `touch_decoration` | `（装飾品に触れた）`         | `（裝飾品被觸摸）`   |
| `touch_zone`       | `（身体に触れた）`           | `（身體被觸碰）`     |

標籤語言可依 `mpu_opt['ai_language']` 動態選擇。

---

## 3. 前端設計（單一統一 history）

### 3.1 History entry 格式

```js
// 舊格式（user chat）
{ role: "user",      content: "使用者說的話",   timestamp: 12345 }
{ role: "assistant", content: "芙莉蓮回應",      timestamp: 12346 }

// 新格式（所有類型統一）
{ role: "user",      content: "使用者說的話",    type: "chat",             timestamp: 12345 }
{ role: "assistant", content: "芙莉蓮回應",       type: "chat",             timestamp: 12346 }
{ role: "user",      content: "（芙莉蓮の独り言）", type: "synthetic",       timestamp: 12347 }
{ role: "assistant", content: "今日のお茶は...",  type: "auto_talk",         timestamp: 12348 }
{ role: "user",      content: "（装飾品に触れた）", type: "synthetic",       timestamp: 12349 }
{ role: "assistant", content: "あ、それは...",    type: "touch_decoration",  timestamp: 12350 }
```

`type` 值表：

| type                 | 描述                                |
| -------------------- | ----------------------------------- |
| `"chat"`             | 真實 user 輸入或對應 assistant 回應 |
| `"synthetic"`        | 非 user 互動的錨點 user 訊息        |
| `"auto_talk"`        | 自言自語 assistant 回應             |
| `"greet"`            | 打招呼 assistant 回應               |
| `"context"`          | 頁面感知 assistant 回應             |
| `"event"`            | Bot/Akismet 事件 assistant 回應     |
| `"touch_decoration"` | 裝飾物觸摸 assistant 回應           |
| `"touch_zone"`       | 身體觸摸 assistant 回應             |

### 3.2 各路徑 push 方式（以 frieren.js 為例）

```js
// handleDecorationClick 成功回應後
const syntheticLabel = mpuL10n?.touchDecorationLabel || "（装飾品に触れた）";
window.mpuChatHistory.push({
  role: "user",
  content: syntheticLabel,
  type: "synthetic",
  timestamp: Date.now(),
});
window.mpuChatHistory.push({
  role: "assistant",
  content: res.msg,
  type: "touch_decoration",
  timestamp: Date.now(),
});
mpu_saveChatHistory();
```

```js
// ukagaka-core.js mpu_nextmsg（auto-talk）回應後
const syntheticLabel = mpuL10n?.autoTalkLabel || "（芙莉蓮の独り言）";
window.mpuChatHistory.push({
  role: "user",
  content: syntheticLabel,
  type: "synthetic",
  timestamp: Date.now(),
});
window.mpuChatHistory.push({
  role: "assistant",
  content: autoTalkMsg,
  type: "auto_talk",
  timestamp: Date.now(),
});
mpu_saveChatHistory();
```

### 3.3 Chat 送出：單一 history 通道（修訂 v1 的兩通道方案）

```js
// ukagaka-chat.js mpu_sendChatMessage（修訂後）
// ─── 移除 v1 的 chatTurns / activity_memory 分流 ───

// 取最近 20 則（含所有類型），保持時序
const historyToSend = window.mpuChatHistory.slice(-20);

formData.append("history", JSON.stringify(historyToSend));
// 不再有 activity_memory 通道
```

### 3.4 `MPU_MAX_CHAT_HISTORY`

從目前的 `20` **改為 `40`**（synthetic + assistant 各佔一則，20 個互動 = 40 entries）。

### 3.5 頁面生命週期管理：F5 清空

**背景：** 部落格為 SPA 模式。使用者在 SPA 內切換頁面（pushState 路由）時不會觸發 reload，記憶應保留。F5 / 強制重整才清空，給芙莉蓮一個乾淨的新開始。

**localStorage vs sessionStorage 比較：**

| 儲存方式                   | SPA 路由切換 | F5 重整           | 關 Tab  |
| -------------------------- | ------------ | ----------------- | ------- |
| localStorage（現況）       | 保留 ✅      | 保留 ❌（不會清） | 保留    |
| sessionStorage             | 保留 ✅      | 保留 ❌（仍不清） | 清空 ✅ |
| localStorage + reload 偵測 | 保留 ✅      | **主動清空 ✅**   | 保留    |

→ **採用 localStorage + reload 偵測**（sessionStorage 在 F5 後仍保留，不符合需求）。

**實作：在 `ukagaka-base.js` 初始化時偵測 reload：**

```js
// ukagaka-base.js — 初始化段落，在 mpuChatHistory / mpuChatSessionId 初始化之前執行
(function () {
  var isReload = false;

  // 方法一：PerformanceNavigationTiming（現代瀏覽器）
  if (window.performance && performance.getEntriesByType) {
    var navEntries = performance.getEntriesByType("navigation");
    if (navEntries.length > 0 && navEntries[0].type === "reload") {
      isReload = true;
    }
  }
  // 方法二：performance.navigation（舊瀏覽器相容）
  if (!isReload && window.performance && performance.navigation) {
    if (performance.navigation.type === 1) {
      isReload = true;
    }
  }

  if (isReload) {
    // 清空 localStorage 中的 chat history 與 session id
    try {
      localStorage.removeItem("mpuChatHistory");
      localStorage.removeItem("mpuChatSessionId");
    } catch (e) {
      // localStorage 不可用時靜默略過
    }
    mpuLogger.log("🔄 偵測到頁面重整，清空對話記憶與 Session ID");
  }
})();

// 之後再初始化（此時 localStorage 已是乾淨的）
window.mpuChatHistory = window.mpuChatHistory || [];
window.mpuChatSessionId = window.mpuChatSessionId || "";
```

**注意事項：**

- IIFE 在初始化之前執行，確保 `window.mpuChatHistory = [] || ...` 讀到的是清空後的空 localStorage
- `performance.getEntriesByType("navigation")` 在所有現代瀏覽器（Chrome / Firefox / Safari / Edge）均支援
- `performance.navigation.type` 是舊 API（已 deprecated），但作為 fallback 保留
- SPA 內 pushState 路由切換不觸發 navigation type=reload → 記憶自然保留
- `mpuLogger` 在 `ukagaka-base.js` 最頂端定義，IIFE 內部呼叫時需確認順序（或改用 `console.log`）

---

## 4. 後端設計

### 4.1 接收與驗證（`class-mpu-rest-chat.php`）

```php
// 允許的 type 白名單
$allowed_types = ['chat', 'synthetic', 'auto_talk', 'greet', 'context', 'event',
                  'touch_decoration', 'touch_zone'];

foreach ($decoded_history as $msg) {
    if (!isset($msg['role'], $msg['content'])) continue;
    $role    = in_array($msg['role'], ['user', 'assistant'], true) ? $msg['role'] : null;
    $type    = isset($msg['type']) && in_array($msg['type'], $allowed_types, true)
               ? $msg['type'] : 'chat';
    $content = sanitize_textarea_field(wp_unslash($msg['content']));

    // 長度限制（防爆 token）
    if (mb_strlen($content, 'UTF-8') > 500) {
        $content = mb_substr($content, 0, 500, 'UTF-8');
    }
    if ($role && !empty(trim($content))) {
        $valid_history[] = ['role' => $role, 'content' => $content, 'type' => $type];
    }
}
// 取最近 20 則（含 synthetic）
$valid_history = array_slice($valid_history, -20);
```

### 4.2 Normalize 規則（`class-mpu-rest-chat.php`）

修訂 normalize 邏輯，讓 synthetic user 成為合法錨點：

```php
// 修訂後：synthetic user 也算「有效的前置 user」
$normalized_history = [];
$previous_role      = '';
$previous_type      = '';

foreach ($valid_history as $message) {
    $role = $message['role'];
    $type = $message['type'] ?? 'chat';

    if ($role === 'assistant') {
        // 孤立 assistant 判斷：前一則既不是真實 user，也不是 synthetic user → 移除
        $prev_is_user = ($previous_role === 'user');
        if (!$prev_is_user) {
            continue; // 依然移除真正孤立的 assistant
        }
    }

    $normalized_history[] = $message;
    $previous_role = $role;
    $previous_type = $type;
}

$chat_history = array_slice($normalized_history, -20);
```

因為 synthetic user（role='user'）在 push 到 history 時緊接著 assistant，normalize 的 `$previous_role === 'user'` 判斷會通過，assistant 被保留。原有邏輯**不需要大幅修改**，只需確認 synthetic user 的 `role` 欄位是 `"user"` 即可（本設計已保證）。

### 4.3 LLM Messages 組建

LLM 收到的 messages：直接使用 `$chat_history`（normalize 後），**包含 synthetic user 訊息**。

```
[
  { role: "user",      content: "（芙莉蓮の独り言）" },   ← synthetic，模型理解為自語時刻
  { role: "assistant", content: "今日のお茶は美味しい" },
  { role: "user",      content: "（装飾品に触れた）" },
  { role: "assistant", content: "あ、それは…" },
  { role: "user",      content: "さっきお茶が美味しいって..." },  ← 真實 user
  { role: "assistant", content: "うん、今日は..." }
]
```

Synthetic 標籤明確、語義清晰，現代 LLM（Claude / Gemini / GPT）能正確理解這是「角色行為標記」而非真實 user 指令。

### 4.4 Checksum（只計算真實對話輪次）

```php
// mpu_chat_integrity_filter_messages() 只保留 type==="chat" 的 assistant
function mpu_chat_integrity_filter_messages(array $messages) {
    $filtered = [];
    foreach ($messages as $message) {
        if (!is_array($message)) continue;
        $role = $message['role'] ?? '';
        $type = $message['type'] ?? 'chat';
        // 只取真實 assistant 回應（非 synthetic 錨點的 user，非 auto_talk 等非 chat assistant）
        if ($role !== 'assistant') continue;
        if ($type !== 'chat') continue;   // ← 新增：只計入真實對話的 assistant
        if (!isset($message['content'])) continue;
        $content = is_scalar($message['content'])
                   ? (string) $message['content']
                   : wp_json_encode($message['content']);
        $filtered[] = ['role' => 'assistant', 'content' => $content];
    }
    return $filtered;
}
```

### 4.5 Checksum 向下相容

舊格式 entry 無 `type` 欄位 → `$type = $message['type'] ?? 'chat'` → 視為 `"chat"` → 行為與現在一致。

---

## 5. 信任邊界處理（Codex C3）

### 5.1 威脅分析

| 攻擊向量            | 說明                               | 實際風險                                                         |
| ------------------- | ---------------------------------- | ---------------------------------------------------------------- |
| 偽造 assistant 內容 | 前端注入假的「芙莉蓮說過X」        | 低：需要 JS console 存取；攻擊者即本機使用者，影響自己的 session |
| 偽造 synthetic 標籤 | 偽造「（裝飾品被觸摸）」等事件標記 | 低：標籤只是 context hint，不觸發任何伺服器邏輯                  |
| 注入惡意 prompt     | 在 content 裡塞 prompt injection   | 已由 sanitize_textarea_field + 500 字上限緩解                    |

本插件的對話框是前台 JS 功能，歷史本就儲存在瀏覽器端（localStorage）。攻擊者若能修改前端 JS，也能直接操控 DOM，此威脅模型不超過現有邊界。

### 5.2 緩解措施

1. **Type 白名單**：後端只接受 8 個預定義 type，未知 type 轉為 `"chat"` 或拒絕
2. **長度截斷**：每則 content 上限 500 字，系統 prompt 注入量可控
3. **Sanitize**：`sanitize_textarea_field` 清除 HTML tags 與危險字元
4. **選配：Transient 驗證（高安全需求）**
   - 後端 touch / greet / context 端點在回應時，除了 checksum，也將 assistant content 存入 session transient（`mpu_chat_mem_{session_id}_{idx}`）
   - User chat 時，後端取出 transient，與前端送來的 assistant content 比對 hash
   - 不符者降為「未知互動，不注入記憶」（不阻斷請求）
   - 此選配複雜度較高，Phase 4 再評估是否實作

---

## 6. 影響範圍總覽

| 層   | 檔案                                                                 | 變更量             | 備注                                                         |
| ---- | -------------------------------------------------------------------- | ------------------ | ------------------------------------------------------------ |
| 前端 | `js/ukagaka-chat.js`                                                 | 中                 | 移除兩通道，改單一 history；`MPU_MAX_CHAT_HISTORY` 調整至 40 |
| 前端 | `js/ukagaka-core.js`                                                 | 小                 | push 改為先 synthetic 後 assistant                           |
| 前端 | `js/ukagaka-context.js`                                              | 小                 | 同上                                                         |
| 前端 | `js/ukagaka-greeting.js`                                             | 小                 | 同上                                                         |
| 前端 | `ghost/Frieren/frieren.js`                                           | 小                 | 同上                                                         |
| 後端 | `includes/rest/class-mpu-rest-chat.php`                              | 中                 | 接收含 type 欄位的統一 history；normalize 確認邏輯           |
| 後端 | `includes/llm/chat-integrity.php`                                    | 小                 | `filter_messages` 加 type === "chat" 判斷                    |
| 後端 | `includes/rest/class-mpu-rest-touch.php`                             | 無（Phase 4 選配） | —                                                            |
| i18n | `ghost/Frieren/frieren.js` 或 `includes/core/frontend-functions.php` | 小                 | synthetic 標籤需 i18n 或語言判斷                             |

---

## 7. 實作順序

```
Phase 1  前端：所有 push 改為先 synthetic 再 assistant（加 type 欄位）
         ─ ukagaka-core.js, ukagaka-context.js, ukagaka-greeting.js, frieren.js
         ─ MPU_MAX_CHAT_HISTORY = 40

Phase 2  前端：chat 送出改用單一 history 通道，slice(-20) 送出
         ─ ukagaka-chat.js

Phase 3  後端：接收含 type 的 history，normalize 確認邏輯（已天然相容 synthetic user）
         後端：filter_messages 加 type === "chat" 限制 checksum 只計真實對話
         ─ class-mpu-rest-chat.php, chat-integrity.php

         ✅ 完成後即可測試全部 5 個情境

Phase 4  選配：Transient 驗證機制（高安全需求時實作）
         選配：touch 端點補 session_id，讓 touch 回應本身也能感知 chat history
```

---

## 8. 情境驗證矩陣

| 情境                                   | 對應設計                             | Phase 1-3 後可支援 |
| -------------------------------------- | ------------------------------------ | ------------------ |
| 1. bot 感知 → user 回饋 → 芙莉蓮記得   | `event` synthetic pair 在 history 中 | ✅                 |
| 2. 頁面感知 → user 回饋                | `context` synthetic pair             | ✅                 |
| 3. touch → user 接著聊 → 芙莉蓮記得    | `touch_*` synthetic pair             | ✅                 |
| 4. 自語 → user 接著聊 → 芙莉蓮記得     | `auto_talk` synthetic pair           | ✅                 |
| 5. 複雜交錯時序（bot→user→touch→user） | 單一統一 history 保持時序            | ✅                 |
