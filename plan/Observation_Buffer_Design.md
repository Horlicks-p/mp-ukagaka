# Observation Buffer 設計（揮發性 MVP）

> 📋 2026-05-20 — `Engineering_Quality_Improvement_Plan.md` v2.22+ 表格 #10 的設計凍結文件
>
> **狀態**：設計階段。MVP 範圍已釘死，待 User Memory v2 完成後再升級為持久化版本。
> **不在此 milestone 實作。** 本文件鎖住 scope，避免未來實作時 over-engineering 或踩 privacy 雷。

---

## 目標一句話

讓 LLM 在訪客**主動發起 chat** 時，能拿到「這個 session 內剛剛發生了什麼」的短期 context，回應更貼合當下情境，但**不**做自發觸發、**不**做長期記憶、**不**允許跨訪客洩漏。

範例：訪客剛看了《平方根的計算》並摸了角色 3 次，下一句 chat 是「你最近在忙什麼？」，LLM 收到 system prompt 末尾的 observation 注入後，可以選擇回應「剛剛看你在算平方根？」之類的 context-aware 對話。

---

## 與既有 `Visitor_Signals_Plan.md` 的關係

**這兩個是分開的系統，不重疊、不互相替代。**

| 維度 | Visitor Signals（既有）| Observation Buffer（本案）|
|---|---|---|
| 觸發模型 | **Push** — 偵測到事件 → 自動觸發 LLM 生成 auto_talk | **Pull/Drain** — 累積事件 → 訪客主動 chat 時注入 system prompt |
| 對話路徑 | `auto_talk` / `mpu_common_msg()` | `/chat/user` + `/chat/user-stream` |
| 事件來源 | AI crawler UA / foreign visitor / late night / bot blocker | page view / stay duration / touch / lifecycle / bot signal |
| Scope | Frieren 整站 global（同站訪客共享 pulse 冷卻）| Per-session/per-user |
| 觸發 LLM？ | 是（autonomous）| 否（passive） |
| 持久化？ | transient 冷卻、Slimstat 永久 log | transient 短 TTL，drain 後刪除 |
| 對應 sleep mode？ | 是（dream pool fallback）| 否（drain 前 sleep 檢查由 caller 負責）|

兩者**完全可共存**：訪客 pulse 觸發 auto_talk 是「Frieren 注意到」；Observation Buffer 是「Frieren 想起剛才」。語意不同，互不干擾。

---

## Scope：揮發性 MVP（hard limits）

1. **只做被動累積 + 注入**：不做 autonomous LLM trigger
2. **Session/user scoped，禁止 global key**：跨訪客洩漏 = 嚴重 privacy bug
3. **Transient-based 揮發儲存**：drain 後立刻刪除，TTL 過期也自動消失
4. **最多 5 筆 ring buffer**：超過第 6 筆推進來，最舊那筆 drop
5. **TTL = 1 小時**：可由 `mpu_observation_buffer_ttl` filter 調，無 admin UI
6. **5 個事件類型固定**：不開 type registration API，不開 filter
7. **drain 入口只有一個**：`/chat/user(-stream)` 的 prompt 構築期，其他 LLM 入口不 drain
8. **bot 確定者不收，只收軟信號**：硬擋在 Turnstile/Akismet 層，buffer 只記「可疑但放行」
9. **不做 admin UI / 設定頁**：站長要關掉就靠 hook 或常數
10. **不做 ability tool**：LLM 不能主動 query buffer，buffer 是 system prompt 注入的單向流

---

## 5 個 sharpened 決策（設計凍結）

### 決策 1 — Session key 推導

```php
function mpu_observation_scope_key(): string {
    $user_id = get_current_user_id();
    if ( $user_id > 0 ) {
        return 'user_' . $user_id;
    }
    // 沿用既有 chat session token（v2.18 chat-integrity 已建立）
    $token = mpu_chat_integrity_get_session_token();
    if ( ! empty( $token ) ) {
        return 'session_' . $token;
    }
    // fallback：不可用 IP/referrer/fingerprint（會 collide 或被偽造）
    return '';  // 空字串 = 不寫入、不讀取，buffer 對此訪客等同停用
}
```

**禁止項**：IP、referrer header、user agent hash、Cookie ID（除 chat session token 外）。

**fallback 行為**：拿不到 session key 時，`push()` 直接 return（無聲），`drain()` 回 `[]`。**寧可不收觀測，也不允許錯配。**

### 決策 2 — TTL = 1 小時

```php
const MPU_OBSERVATION_BUFFER_TTL = HOUR_IN_SECONDS;
// 站長可透過 filter 調整：
// add_filter( 'mpu_observation_buffer_ttl', fn() => 30 * MINUTE_IN_SECONDS );
```

理由：
- **太短（5–10 min）**：訪客看完文章 → 滑到底 → 開始 chat 的常見 flow 撐不到 drain
- **太長（6 hr 以上）**：早上看過的文章扔進晚上的 chat = LLM 雜訊
- **1 小時**：覆蓋一般 session 活躍期，跟 chat history 的活躍視窗近似

### 決策 3 — Ring buffer 容量 = 5

```php
$buf = get_transient( $key ) ?: [];
$buf[] = $new_entry;
$buf = array_slice( $buf, -5 );  // 保留最近 5 筆，舊的自然 drop
set_transient( $key, $buf, $ttl );
```

理由：5 筆夠 LLM 拿到「最近一陣子發生了什麼」，再多 token cost 不划算。

### 決策 4 — bot signal 收軟不收硬

| 信號類型 | 來源 | 是否進 buffer |
|---|---|---|
| Turnstile token 通過但低分 | Turnstile integration | ✅ 軟信號，例：`'turnstile_score_low:0.3'` |
| Honeypot 沒填、UA 正常但 typing 過快 | bot-blocker heuristic | ✅ 軟信號，例：`'typing_too_fast'` |
| Akismet 標 spam（false positive 可能） | Akismet | ✅ 軟信號，例：`'akismet_suspect'` |
| Turnstile token 完全失敗 | Turnstile | ❌ **應 hard block，不進 buffer** |
| Bot-blocker IP blacklist 命中 | bot-blocker | ❌ **應 hard block，不進 buffer** |
| AI crawler UA match | `Visitor_Signals_Plan` 既有 | ❌ 走既有 auto_talk push 路徑，不重複收 |

LLM 看到軟信號可選擇回應更謹慎（不洩個資、不答深問題），但 buffer **不是 access control**——硬擋責任在 REST 層的 nonce / Turnstile / rate limit。

### 決策 5 — drain 入口只有 `/chat/user(-stream)`

| 入口 | drain？ | 理由 |
|---|---|---|
| `/chat/user` + `/chat/user-stream` | ✅ | 訪客主動發起，context 最相關 |
| `/chat/greet` | ❌ | 首訪 buffer 為空，且 greet 不該帶上次 session 殘留 |
| `/chat/context` | ❌ | 自動觸發的 AI dialogue，已被 page context 主導，不需 buffer 二度注入 |
| diary (Frieren 日記) | ❌ | admin/system 用途，永遠不該沾染訪客 buffer |

未來 v2 若要擴大 drain 入口（例如 greet），須**同時驗證**：
- buffer 是否該在 greet 之後 drain（清空）or 保留
- 訪客 A 上次 session 殘留 buffer 是否該注入訪客 B 的 greet（答：絕對不可，但 session key 切換已天然阻擋）

---

## 5 個事件類型 schema

每筆 entry 結構固定為 `['type' => string, 'content' => string, 'ts' => int]`。`content` 是縮略 string，**不存物件、不存全文、不存 PII**。

| Type | Content 格式 | Push 觸發點 | 範例 |
|---|---|---|---|
| `page_view` | `'post:{id}:{slug_or_title_60chars}'` | 前端 SPA page change event → REST `/observation/push` | `'post:123:平方根的計算-入門指南'` |
| `stay_duration` | `'post:{id}:{seconds}s'` | 前端 `beforeunload` / SPA leave → REST `/observation/push` | `'post:123:240s'` |
| `touch` | `'{part}:{count}'` | 既有 `/touch` endpoint 內部 hook | `'head:3'` / `'decoration_hat:1'` |
| `lifecycle_event` | `'{event}'` 或 `'{event}:{detail}'` | wake_ghost / sleep helper / context handler 內 hook | `'wake'` / `'sleep'` / `'context_triggered'` |
| `bot_signal` | `'{signal_type}:{score_or_detail}'` | Turnstile / honeypot / Akismet handler 內 hook | `'turnstile_low:0.3'` / `'typing_too_fast'` / `'akismet_suspect'` |

**Content size cap**：每筆 entry 序列化後不超過 200 bytes，防止意外塞長字串。`push()` 內部 hard truncate。

---

## Public API

```php
// includes/core/class-mpu-observation-buffer.php（新檔）

class MPU_Observation_Buffer {

    const MAX_ENTRIES = 5;
    const MAX_CONTENT_BYTES = 200;
    const VALID_TYPES = [
        'page_view',
        'stay_duration',
        'touch',
        'lifecycle_event',
        'bot_signal',
    ];

    /**
     * 推送一筆觀察。scope 由 mpu_observation_scope_key() 自動決定。
     * 無法取得 scope 時靜默返回，不報錯。
     *
     * @param string $type    必須是 VALID_TYPES 之一
     * @param string $content 縮略字串，超過 MAX_CONTENT_BYTES 會被 truncate
     * @return bool 是否成功 push（false = scope 取不到 / type 不合法）
     */
    public static function push( string $type, string $content ): bool;

    /**
     * 取出 + 清空 buffer。回傳結構 [['type'=>..., 'content'=>..., 'ts'=>...], ...]，
     * 最多 5 筆，時間戳由舊到新。
     *
     * @return array 觀察列表，空陣列代表無 observation
     */
    public static function drain(): array;

    /**
     * 不清空地 peek。debug / admin dashboard 用，本 MVP 不暴露 REST。
     */
    public static function peek(): array;
}
```

**沒有的 method**（不要加）：
- `MPU_Observation_Buffer::clear()` — 沒有 drain 不清空的用例，drain 本身就清
- `MPU_Observation_Buffer::push_for_scope( $scope, ... )` — 不允許 caller 指定 scope，必經 helper 推導
- `MPU_Observation_Buffer::push_many( $entries )` — 批次推送等於 by-pass type 驗證，禁
- 任何 filter hook 讓外部修改 buffer 內容 — 信任邊界要清楚

---

## llm-context-builder 整合點

```php
// includes/llm/llm-context-builder.php 內既有 prompt 構築函數末尾
function mpu_build_chat_user_system_prompt( /* ... */ ): string {
    $prompt = mpu_resolve_system_prompt( /* ... */ );
    // ... 既有的 personality、time context、variables 注入 ...

    // 揮發性觀察 buffer 注入（僅限 /chat/user 路徑）
    $observations = MPU_Observation_Buffer::drain();
    if ( ! empty( $observations ) ) {
        $prompt .= "\n\n## 剛才的觀察（最近 1 小時內）\n";
        foreach ( $observations as $obs ) {
            $prompt .= sprintf(
                "- [%s] %s\n",
                $obs['type'],
                $obs['content']
            );
        }
        $prompt .= "\n（這些是訪客剛剛的行為，可在回應中自然引用，不強求每條都提）";
    }

    return $prompt;
}
```

**注意**：drain 必須在 `/chat/user(-stream)` 的 prompt 構築期呼叫，**且只呼叫一次**。多次呼叫第二次後 buffer 已空。

---

## 不做清單（anti-scope，鎖死避免 over-engineering）

### 不做的功能

| 項目 | 為什麼不做 |
|---|---|
| Autonomous LLM trigger | 違反 passive-only 原則；cost / privacy / UX 三高風險 |
| 持久化 memory（DB table）| 前置是 User Memory v2，未到位 |
| Visitor 長期記憶 | 同上 |
| 跨 session 持久 | TTL 1 小時保證 session 結束就消失 |
| Global key | privacy bug（跨訪客洩漏）— 即使「方便 debug」也不開 |
| Hard bot detection 入 buffer | 硬擋責任在上游 |
| LLM ability tool 查詢 buffer | buffer 是單向注入流，不是 query surface |
| Admin UI 設定 | 設定數量沒爆炸前不開 UI |
| Filter hook 讓外部改 buffer 內容 | 信任邊界要硬 |
| 跨 LLM 入口 drain | 只 `/chat/user(-stream)`，其他保留給 v2 |
| 與既有 visitor pulse 合併 | 兩個系統故意分開（push vs pull 模型） |

### 不做的工程細節

- 不引入第三方 PHP package
- 不建新 DB table
- 不改 `mpu_get_session_key` 或既有 chat session token 邏輯
- 不改 `mpuChatSessionId` 前端 contract
- 不改 REST API namespace（沿用 `mp-ukagaka/v1`，僅新增 `/observation/push` 入口）

---

## 升級路徑（User Memory v2 完成後）

當 User Memory v2 支援匿名訪客 hash 寫入後，Observation Buffer 可選擇性升級：

### 階段 1：保留揮發性 MVP 不動

User Memory v2 解決「長期記憶寫入」，但「session 內最近 5 筆」這個 use case 仍適合揮發性。**繼續用 transient 即可。**

### 階段 2：drain 時順帶寫入 User Memory v2（如果有價值）

```php
// llm-context-builder.php 內，drain 之後
$observations = MPU_Observation_Buffer::drain();
if ( ! empty( $observations ) && mpu_user_memory_v2_available() ) {
    // 只挑「值得長期記住」的 type 寫入持久化 memory
    $memorable = array_filter( $observations, fn($o) =>
        in_array( $o['type'], [ 'page_view', 'touch' ], true )
    );
    if ( ! empty( $memorable ) ) {
        mpu_user_memory_v2_append( $scope_key, $memorable );
    }
}
```

**注意**：升級不是「替換」揮發性 buffer，而是「在揮發性之上加一層」。session 內依舊 drain transient，但長期值的 entries 進 User Memory v2。

### 不升級的 type

- `bot_signal` 永遠不寫入 User Memory v2（過期 metadata，無長期 value）
- `lifecycle_event` 寫入 visitor scope 沒意義（這是站全 event，不是訪客特性）

---

## Verification 計畫（實作期）

### PHPUnit（純函數）

- `MPU_Observation_Buffer::push()` 對非法 type 返回 false
- `MPU_Observation_Buffer::push()` 對超長 content truncate 到 MAX_CONTENT_BYTES
- `MPU_Observation_Buffer::push()` 在 scope key 為空時返回 false（不寫 transient）
- `MPU_Observation_Buffer::drain()` 空 buffer 回 `[]`
- `MPU_Observation_Buffer::drain()` 取出後 transient 已刪
- Ring buffer：push 第 6 筆，最舊 drop，剩 5 筆且順序正確

### Manual smoke

- 訪客 A push 3 筆 → 訪客 B drain 應為 `[]`（scope 隔離驗證）
- 同訪客 push → drain → 再 drain，第二次回 `[]`
- TTL 過期後 drain 回 `[]`（手動 expire transient）
- `/chat/greet` / `/chat/context` 內**不**該 drain（看 system prompt 末尾是否無觀察區塊）
- `/chat/user` 內 drain 後，system prompt 末尾出現「剛才的觀察」區塊

### Privacy red-line test

- **必跑**：訪客 A push observation 後登出，訪客 B 用同瀏覽器但不同 chat session token，B 的 `/chat/user` system prompt 不得出現 A 的 observation
- **必跑**：未登入訪客拿不到 session token 時，`push()` 不寫 transient（grep transient 表確認）

---

## 風險清單

| 風險 | 緩解 |
|---|---|
| Session key collision（兩個 user 共用 token）| chat-integrity session token 已是 crypto-random，碰撞機率可忽略 |
| Transient 寫入失敗（object cache 滿）| `push()` 失敗靜默 return，不阻擋訪客體驗 |
| System prompt 變長導致 token cost 上升 | MAX_ENTRIES=5 + MAX_CONTENT_BYTES=200 上限約 1KB，可接受 |
| LLM 把 observation 當「指令」執行（prompt injection） | system prompt 區塊明文標「自然引用，不強求每條都提」，模型 RLHF 訓練本身會降低風險；外加 server-side tool gate（v2.17 已有）防 ability 濫用 |
| 訪客覺得「被監視」 | buffer 內容對應的行為（看文章 / 摸角色 / Frieren 自己的 event）都是公開或自身觸發，沒有 covert tracking；如有疑慮可考慮在 admin UI 加 disclosure 文案（未來 milestone） |

---

## 前置條件 / blocker 一覽

實作 #10 揮發性 MVP 前必須先確認：

| 前置 | 狀態 | 備註 |
|---|---|---|
| chat-integrity session token 機制 | ✅ v2.18 已實作 | `mpu_chat_integrity_get_session_token()` 可用 |
| Transient API 可用 | ✅ WordPress core 內建 | object cache 環境也支援 |
| `MPU_REST_Base` OO 路由 | ✅ v2.9.2 已實作 | `/observation/push` REST endpoint 可掛這上面 |
| `llm-context-builder.php` prompt 構築點明確 | ✅ 既有 | drain 注入點清楚 |
| User Memory v2 | ❌ **未實作** | **但揮發性 MVP 不依賴此**，僅升級階段 2 才需要 |

**結論**：揮發性 MVP 所有技術前置都到位，**可隨時開實作**，只是優先序排在 v2.22.0 #7 Runtime State helper 之後。

---

## 與其他 plan 文件的關係

| 文件 | 關係 |
|---|---|
| `Engineering_Quality_Improvement_Plan.md` v2.22+ #10 行 | 指標項，描述 + 連結到本文件 |
| `Avatar_UI_Learnings.md` P2-3 + 補論 D | 設計藍本，本文件是 MP-Ukagaka 版本的具體化 + scope 收緊 |
| `Visitor_Signals_Plan.md` | **不同系統**，已說明於「與既有 Visitor_Signals_Plan 的關係」段落 |
| `UnifiedHistory_MemoryPlan.md` | User Memory MVP 的記錄，升級階段 2 才相關 |

---

*Last updated: 2026-05-20 — 設計凍結。實作待 v2.22.0 (#7) 完成後評估是否進 v2.23.0 或 v2.24+。MVP 不依賴 User Memory v2，但升級階段 2 需要。*
