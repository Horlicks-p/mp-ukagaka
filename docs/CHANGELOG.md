# MP Ukagaka 版本歷史

> 📋 所有版本的更新記錄

---

## [Unreleased] - Console log 國際化遷移

### 🌐 Frontend console log i18n

- 前端 console log 字串正逐步從硬編碼繁中遷移為日文 source，並透過 WordPress locale 與 `mpuL10n.logs` 顯示對應翻譯。
- 已遷移 `js/ukagaka-anime.js` 的 5 條 production-visible error/warn，以及 `ghost/Frieren/frieren.js` 的 11 條 production-visible error/warn（裝飾配置、Canvas/Image、Pixel、Touch Zone）；輸出時機維持不變，direct `console.warn` 仍使用 always-output 路徑。

## [2.23.2] - 2026-05-24

### 🐛 錯誤修正

#### Observation Buffer — SPA 導航追蹤

- 觀察追蹤（`page_view` / `stay_duration`）現在不只在單篇文章為初次載入時啟動，連透過前端（SPA）導航進入單篇文章後也會啟動。
- 文章 ID 改由 DOM 重新偵測（`postid-` / `page-id-` body class、`data-post-id`、`article[id^="post-"]`），並在 PHP 注入的 `mpuPageContext.postId` 存在時優先採用。
- 列表、彙整與首頁皆已加上誤啟動防護，且每次 SPA 導航會在重新偵測前先清除舊的文章 ID。

#### 頁面感知自動發話 — 自動對話復原

- 移除頁面感知自動發話顯示回呼中脆弱的 `!mpuAutoTalkTimer` 判斷。在生產環境的 API 延遲下，殘留的計時器參照可能使自動對話永久卡住（本機無法重現）。
- `startAutoTalk()` 內部會先停止既有計時器，因此只要自動對話啟用，復原即為無條件執行（不會疊計時器，並維持原本的發話節奏）。

---

## [2.23.1] - 2026-05-24

### 🌐 語言設定統一與 fallback 邏輯修正

- 修正了角色語言設定衝突的問題，現在語言解析的優先順序為：後台明確設定 > 角色專屬 `manifest.json` > 最終預設日文 (`ja`)。
- 在後台「通用設定」與「AI 設定」的語言下拉選單中新增「預設」選項，允許管理員將語言決定權交回給角色的 `manifest.json`。
- 移除了未使用的簡體中文與韓文映射，精簡語言清單。

---

## [2.23.0] - 2026-05-23

### 🧠 Observation Buffer (觀察緩衝區)

#### 🧩 Session-scoped 觀察提示

新增 `MPU_Observation_Buffer`，以 session token 為 scope 暫存最近的訪客行為，並在下一次使用者主動聊天時注入為「情境資訊」。

- Observation Buffer 使用 WordPress transient 儲存，scope key 由 session token 雜湊產生，不會保存原始 token。
- 每個 session 最多保留 5 筆觀察，每筆內容限制 200 bytes；預設 TTL 為 1 小時，並可透過 `mpu_observation_buffer_ttl` filter 調整（限制在 5 分鐘到 2 小時之間）。
- Buffer 會在 `/chat/user` 組 system prompt 時 `drain()`，同一批觀察最多只會被注入一次，避免重複影響後續對話。

#### 🔌 REST API 與前端追蹤

- 新增 `POST /mp-ukagaka/v1/observation/push`，目前允許 `page_view` 與 `stay_duration` 兩種前端觀察類型。
- REST endpoint 需要有效 session token，並套用 `20 次 / 60 秒` 的 rate limit。
- 前端新增 `mpuObservationPush()` 與 `mpuInitObservationTracking()`，在文章頁記錄頁面瀏覽，並於停留 10 / 30 / 60 / 180 / 600 秒時推送停留時間。
- 若 session token 過期導致 403，前端會重新取得 token 後重試一次，降低 SPA 或長時間停留頁面的漏記機率。

#### 👻 互動事件整合

- 觸摸角色與裝飾物時會累積 `touch` 類型觀察，並對同一部位進行去重與計數合併。
- 角色被喚醒、從睡眠中被喚醒、頁面情境觸發等 lifecycle event 會進入 buffer，讓下一次對話能自然知道剛剛發生過什麼。
- `/chat/user` 會把觀察資料格式化為「最近訪客活動」區塊，明確標示為情境資訊而非指令，讓 LLM 可以自然引用但不被強制牽引。

#### 🛡️ 安全與邊界

- 觀察內容會經過白名單類型檢查、格式驗證、HTML 清理與長度裁切；非公開文章或密碼文章只會以 `[non-public]` 表示，不暴露標題。
- Buffer 採短期 transient 設計，不寫入 User Memory，也不建立長期訪客檔案。
- `bot_signal` 類型保留為後續 phase 2 擴充，目前會被拒絕寫入。

---

## [2.22.1] - 2026-05-22

### 👻 角色睡眠喚醒抱怨機制（v2.22.1）

當訪客在角色處於「深眠（deep_sleep）」或「賴床（oversleep）」狀態下點擊喚醒按鈕（`/wake-ghost`）時，後端現在會透過 LLM 生成一段角色專屬的日文起床氣/抱怨台詞，前端並以打字機效果展示，取代原有的預設歡迎語。

### ⚙️ 後端 REST API 與 LLM 生成（`includes/rest/class-mpu-rest-dialog.php`）

- 新增 `get_wake_sleep_phase()` 函數，在寫入喚醒狀態前判定當前處於 `deep_sleep` 還是 `oversleep` 階段。
- 新增 `generate_wake_reaction()` 函數，動態挑選對應的日文 AI 提示詞調用 `mpu_call_ai_api`（限制 `max_tokens=120`），並處理 AI 回覆的 `<think>` 內容與過濾 HTML 標籤。若失敗則回傳空字串 `''` 提供安全降級 fallback，並輸出除錯日誌。
- 喚醒 API 回傳結果新增 `sleep_phase` 與 `wake_reaction` 欄位。
- Ollama 本地模型路徑採用 `busy-lock` 互斥鎖保護，防止並行請求過載。

### 📐 人格配置與提示詞（`ghost/Frieren/sleep_mode.json` & `includes/personality/personality-prompts.php`）

- 於 `sleep_mode.json` 新增 `wake_reaction_prompts` 配置，為 `deep_sleep` 與 `oversleep` 各自定義 3 個隨機 AI 提示指令。
- 於 `personality-prompts.php` 新增 `mpu_pick_wake_reaction_prompt()` 安全過濾並隨機抽取提示詞。

### 🔌 前端對話整合（`js/ukagaka-chat.js`）

- `mpu_send_wake_up_request()` 改為回傳 Promise，並使用 `window.mpuWakeRequestPromise` 防止重複點擊，並將超時時間延長至 60 秒以容納 LLM 生成延遲。
- 新增 `mpu_display_wake_reaction()`，透過打字機效果輸出抱怨語，並將 `{ type: 'wake_reaction' }` 推入 `window.mpuChatHistory` 歷史對話，確保後續 LLM 對話能連貫起床氣上下文。
- 整合 `mpu_toggleChatMode()` 與 OK 按鈕流程，在喚醒動畫完成後再發送請求；若無抱怨語生成則安全 fallback 至原迎賓語路徑。

### 📦 變更檔案與建置

- 更新 `mp-ukagaka.php` 主外掛檔與版本常量為 `2.22.1`。
- 重新建置前端打包檔（`js/dist/ukagaka-bundle.js` 與 `js/dist/ukagaka-bundle.min.js`）。

---

## [2.22.0] - 2026-05-22

### 👻 Ghost Runtime State Helper（v2.22.0 #7 milestone）

新增 transient-based helper，記錄「某個前台 session 目前的角色 runtime state」，作為後續 runtime UI / 觀測整合的後端基礎。對齊 `plan/Engineering_Quality_Improvement_Plan.md` §v2.22.0 #7 hard limits —— 不注入 LLM prompt、不做 Observation Buffer、不寫 User / Visitor Memory。

### 📐 Public API（`includes/core/runtime-state-functions.php`）

State whitelist（9 個）：`idle` / `thinking` / `speaking` / `chatting` / `sleeping` / `waking` / `tool_running` / `suspended` / `error`。Payload shape：`['state' => string, 'ts' => int]`。

| Function | 用途 |
|---|---|
| `mpu_runtime_state_allowed_states(): array` | 回傳白名單 |
| `mpu_runtime_state_scope_key(?$session_token): ?string` | 解析 transient key；raw token 經 `sha256` hash，不會原樣進入 key |
| `mpu_set_runtime_state(string $state, ?$session_token): bool` | 寫入；invalid state 或無法 resolve scope → `false` |
| `mpu_get_runtime_state(?$session_token): ?array` | 讀取；缺失、格式錯誤、state 不在白名單 → `null` |
| `mpu_clear_runtime_state(?$session_token): void` | 刪除 transient |
| `mpu_runtime_state_ttl(): int` | TTL（秒），預設 300，可透過 `mpu_runtime_state_ttl` filter 調整，clamp 在 `[60, 900]` |

### 🔌 REST Wiring（`MPU_REST_Base` + chat/dialog controllers）

- **`/chat/user`** —— LLM 呼叫前 `thinking`，成功 return 前 `speaking`，`finally` 拉回 `idle`；error 分支 → `error` 再 → `idle`。`register_shutdown_function` 在 request 異常中斷時保底寫 `idle`。
- **`/chat/user-stream`** —— SSE start 寫 `thinking`；stream callback 偵測到 `status` 事件且 `type=executing_tool` 時切 `tool_running`，遇到 `delta` 事件切 `speaking`。`exit_if_stream_aborted()` 在 client 中途斷線 `exit` 前先寫 `idle` 釋放狀態。`done` → `idle`；stream 中途出錯 → `error` 再 → `idle`。一樣有 shutdown fallback。
- **`wake_ghost`** —— 取得 token 後立刻 `waking`，整段 endpoint 包在 `try/finally`，不論哪個早 return `WP_Error` 分支，最終都會回到 `idle`。

新增 `MPU_REST_Base::runtime_session_token(WP_REST_Request)` 統一從 `X-MPU-Session-Token` header / `session_token` 參數解析並透過 `mpu_validate_session_token()` 驗證，所有 state 寫入路徑共用同一條 token resolver。

### 🛡️ Plan §v2.22.0 #7 Hard Limits 合規檢查

| 限制 | 狀態 |
|---|---|
| 不用 `session_start()` / `session_id()` | ✅ word-boundary regrep 0 hit |
| 不用 IP / referrer / fingerprint 作 scope | ✅ 僅 token-based（外加可選的 logged-in `user_id` fallback，見下） |
| 不寫 `mpu_opt` / `update_option(...runtime...)` | ✅ 0 hit |
| 不改 REST response shape | ✅ wiring 純 write-only，payload 無新欄位 |
| TTL clamp，預設 5 分鐘 | ✅ `[60, 900]` clamp |
| State whitelist 強制 | ✅ 未知值寫入回 `false`，讀取回 `null` |
| error/abort/done 後清除 | ✅ `finally` + SSE abort + shutdown fallback 三重保險 |

### 🟡 Plan 之外的延伸（明確標註）

`mpu_runtime_state_scope_key()` 新增 **logged-in user fallback**：當 caller 沒傳 session token（或 token 驗證失敗）且 `is_user_logged_in()` 為 true，helper 退回 `mpu_runtime_state_user_{user_id}` 作為 transient key。Plan 只規定 token-based scope，但這個 user-id 分支：

- 沒踩 IP / referrer / fingerprint 紅線（§scope rule 4 仍滿足）
- 與 token-hashed key 用不同 prefix，無 collision
- 讓 admin 從 wp-admin 直連等沒走前台 session-token bootstrap 的場景也能寫狀態

若實務證明這個 fallback 不需要，可在不破壞 public signature 的前提下移除。

### 🧪 測試（`tests/Unit/RuntimeStateTest.php`）

8 個 case：valid 寫入/讀取 round-trip、invalid state 拒絕、invalid token 不建 scope、匿名無 token 無 scope、scope key 確實 hash token 不洩漏、`clear` 確實刪 transient、TTL filter 上下界 clamp（`999999 → 900`、`1 → 60`）、logged-in user 無 token 也能寫入。`tests/bootstrap.php` 補 `wp_salt()` 與 `get_current_user_id()` mock 支援新 fixture。

### ✅ 驗證

- `npm --prefix tools/node run lint:php`：所有 PHP 檔乾淨
- `npm --prefix tools/node run test:php`：**35 tests / 76 assertions 全綠**（v2.22.0 起點 27/59，本 milestone 新增 +8 tests / +17 assertions）
- Red-line greps（`session_start(` / `session_id(` word-boundary、`update_option(...runtime` / `mpu_opt['runtime_state']`、`mpu_get_session_key`）：source 全部 0 hit

### 📦 Commit 配置

一個 feature commit `feat(v2.22.0): ghost runtime state helper (#7)` + 本 CHANGELOG commit。本 milestone 為 PHP-only，無 build artifact 變更。

### 📋 Milestone Notes

關閉 v2.22.0 freeze 表。下一站：**#10 Observation Buffer MVP** 仍在設計階段（`plan/Observation_Buffer_Design.md`），實作門檻為 User Memory v2。

---

## [2.21.1] - 2026-05-22

### 🐛 賴床中芙莉蓮喚醒流程修正

點擊「開啟對話視窗」按鈕喚醒賴床中的芙莉蓮時，原本設計應為「睜開眼睛 + 開啟對話視窗」，實際卻是「睜開眼睛 + 翻書動畫」且對話視窗沒開。

### 🔍 根因分析

兩條並存的問題鏈：

**喚醒競態（race condition）**

- `mpu_toggleChatMode` 在喚醒分支先呼叫 `mpu_send_wake_up_request()` 後端 AJAX，接著 `$msgbox.fadeOut(1000, ...)`，最後在 callback 內才呼叫 `triggerCharacterAnimation`。
- 1 秒 fadeOut 延遲中，後端喚醒回應可能改變 `isSleepMessage()` 判定（清掉 `data-initial-msg` 或翻轉 `mpuInfo.isTemporaryWakeUp`）。
- 結果：`wakeUp()` 回傳 false → 跳過喚醒動畫分支 → 落入 `triggerFrierenSpeaking` 後段翻書路徑，且 `onWakeUpComplete` 從未被呼叫，對話視窗永遠打不開。

**OK 按鈕路徑的旗標殘留**

- OK 按鈕喚醒後設 `mpuSkipNextManualBookFlip = true`，預期由 `mpu_nextmsg` 在後續手動互動時消費。
- 但 chat 模式下 `handleOkAction` 走 `mpu_sendUserMessage()`（SSE 流），完全不會碰 `mpu_nextmsg`，旗標永遠不會被消費。
- 下一次手動互動會誤跳過翻書動畫。

### 🛠️ 修正

| Commit | 內容 |
|---|---|
| `2433729` | (1) 加 `window.mpuForceWakeUpNextTime` 旗標，在 fadeOut 之前鎖定「這次一定要喚醒」，避免被後端回應改寫；(2) `triggerFrierenSpeaking` 加 `skipBookFlip` 早退路徑，確保即使 `sleepModeAwoken` 已為 true 也不誤播翻書，並仍呼叫 `onWakeUpComplete` 讓對話視窗開啟；(3) OK 按鈕路徑改為等 wake-up 動畫完成後才呼叫 `handleOkAction`，避免立即觸發 LLM 對話導致連續動畫衝突 |
| `6ceb36f` | (1) `wakeUp()` 開頭無條件清掉 `mpuForceWakeUpNextTime`，避免 `sleepModeAwoken` 已為 true 時旗標殘留；(2) `mpuSkipNextManualBookFlip` 加 `Date.now()` token + 8 秒 timeout 後備清理；(3) consumer 端正常消費時把 token 清為 null，讓 timeout 觸發時自然 no-op，避免「舊 timeout 誤清新旗標」 |

### 📦 變更檔案

- `ghost/Frieren/frieren.js` — `wakeUp()` / `triggerFrierenSpeaking()`
- `js/ukagaka-chat.js` — `mpu_toggleChatMode` / OK 按鈕 handler
- `js/ukagaka-core.js` — `mpu_nextmsg` 兩處 manual animation 觸發點
- `js/dist/ukagaka-bundle.js` / `ukagaka-bundle.min.js` — 透過 `tools/node/build.js` 重建

### ✅ 驗證指引

實機檢查三種情境，console log 應如下：

- 賴床中點「開啟對話視窗」按鈕：`☀️ 芙莉蓮被喚醒了！(forceWakeUp)` + `📖 喚醒後跳過翻書動畫` + 對話視窗打開
- 賴床中點 OK 按鈕：喚醒動畫完成後才生成 LLM 回應，不出現翻書動畫
- 已醒著直接互動：正常播放翻書動畫，不被誤跳過

---

## [2.21.0] - 2026-05-20

### 🏗️ JS 全域狀態封裝（v2.21.0 #8 milestone）

前端 runtime state 原本散落在 19 個 file-level `let` 與 9 個 `window.*` 全域，現在收進結構化的 `window.MPU_STATE` namespace，透過 31 個 setter/getter helper function 存取（外加 `mpuState` const alias 共 32 個 entry）。**無演算法改動、無 REST payload 改動、無 UI 行為改動** — 純結構性 refactor，重整 mutable state 的擁有權與存取方式。

**遷移分層**：

| 層級 | 變數 | 策略 |
|---|---|---|
| 完全遷移（無 `window.*` 殘留）| `__mpu_retry_count` / `__mpu_fallback_retry_count` / `mpuContextPending` / `mpuSettingsProcessed` / `mpuSettingsLoaded` / `mpuEnableChatMode` / `debugMode` | 讀寫只走 MPU_STATE |
| 保留相容橋 | `mpuMsgList` / `mpuBaseAutoTalkInterval` | Helper 雙寫 `window.*` + MPU_STATE；getter 以 `window.*` 為 canonical |
| Dual-write helper | 17 個 primitive（autoTalk / typewriter / AI / LLM / dialog / greet state）| Helper 同時更新舊 `let` 與 MPU_STATE；reader 兩者皆可 |
| 同物件 ref | `mpuLLMResponseHistory` / `mpuOllamaRequestQueue` / `__mpuStorage` | Array/object 共享，mutation 自動同步 |

**Plan §2.3「不搬清單」刻意保留**：`window.mpuChatHistory`, `window.mpuChatModeActive`, `window.mpuChatSessionId`, `window.mpuChatRequesting`, `mpuChatAbortController`（chat shared state）；`mpuInfo`, `mpuSettings`, `mpuPreSettings`, `mpuRestUrl`, `mpuRestNonce`, `mpuL10n`, `mpuInitData`, `mpuInitParams`, `mpuPersonalityId`（PHP localized data 契約）；`mpuCanvasManager`, `mpuEmojiManager`, `mpuFrierenManager`, `mpuEmojiConfig`（manager objects）；`MPU_EVENTS`, `mpuSpaEvents`（event surface）。

### ✨ 行為調整（intentional）

- **`window.mpuDebugMode = true` 在 console 即時生效**。原 `let debugMode` 在腳本載入時 capture 一次，console 修改不會立即生效。新 `mpuIsDebugMode()` helper 每次呼叫都即時讀 `MPU_STATE.flags.debugMode` 與 `window.mpuDebugMode` 雙旗，console 切換立即啟用 log。

### 📐 Helper 一覽（`js/ukagaka-base.js`）

合計 31 個 function（+1 個 `mpuState` const alias = 32 個 entry）：

- **State access**：`mpuGetState`, `mpuState`（const alias）
- **Debug**：`mpuIsDebugMode`
- **AutoTalk**：`mpuSetAutoTalkTimer`, `mpuSetAutoTalkEnabled`, `mpuSetAutoTalkInterval`, `mpuSetBaseAutoTalkInterval`, `mpuGetBaseAutoTalkInterval`
- **Typewriter**：`mpuSetTypewriterTimer`
- **LLM/AI**：`mpuSetAiTextColor`, `mpuSetAiDisplayDuration`, `mpuSetAiDisplayTimer`, `mpuSetAiContextInProgress`, `mpuSetMessageBlocking`, `mpuSetOllamaReplaceDialogue`, `mpuSetLastLLMResponse`, `mpuResetLLMResponseHistory`, `mpuSetOllamaRequesting`, `mpuSetLastUserActionTime`
- **Dialog**：`mpuSetDialogStore`, `mpuGetDialogStore`, `mpuSetDialogNextMode`, `mpuSetDialogDefaultMsg`
- **Flags**：`mpuSetGreetInProgress`, `mpuSetContextPending`, `mpuIsContextPending`, `mpuSetSettingsProcessed`, `mpuIsSettingsProcessed`, `mpuSetSettingsLoaded`, `mpuIsSettingsLoaded`, `mpuSetEnableChatMode`, `mpuIsChatModeEnabled`

### 🔁 與 plan §2.3 #6 的偏離（實作優於計畫）

Plan 原本建議「區域短別名 + 分段替換」避免 refactor 期間的 scope 錯誤。實作改用 **setter helper function** —— dual-write 邏輯單點集中、grep 友好、無 scope 問題。Intent 完全相同（避免 search-replace 失誤），執行更穩。

### 📦 Commit 配置

兩個 commit 完成本 milestone：

1. `refactor(js): encapsulate runtime state into window.MPU_STATE` — 7 個 source 檔變更
2. `chore(build): rebuild dist bundle for v2.21.0 #8 MPU_STATE migration` — `tools/node/build.js` 純輸出

Plan §2.3 原列 7 步 commit（Inventory / Namespace / Base+core / Dialog+context+greeting / Features / Chat boundary / Bundle）。6 個 source step 實作時未逐步落 commit，最後一次性合併為單一 source commit。Trade-off：bisect 可隔離「v2.21.0 vs pre-v2.21.0」但無法定位 milestone 內單一 step。緩解：source 改動按檔分 boundary（base/core/dialog/context/greeting/features/chat 各自為界），上線出問題可 file-level cherry-pick。

### ✅ 驗證

- `npm run verify`：lint + bundle + PHPUnit（27 tests / 59 assertions）兩 commit 各自獨立通過。
- Source grep 確認完全遷移變數 0 hit。
- `window.mpuMsgList` / `window.mpuBaseAutoTalkInterval` 在 source 只剩 base.js helper 內部 compat bridge 寫入與 defensive fallback。
- `git diff --check` 乾淨。

### ⚠️ 已知限制

- **Release 前必須跑 manual smoke test**。PHPUnit 只涵蓋後端，JS runtime regression 無法自動偵測。Plan §2.3 #6 必驗收 path：auto-talk / chat / context / SSE / typewriter / wake_ghost / first-visit greeting / SPA navigation / sleep-mode interval。
- **`mpuGetDialogStore()` defensive fallback 為實質 dead code**：`typeof window.mpuMsgList !== "undefined"` 在 base.js init 後永遠為真（`typeof null === "object"`），fallback branch 不會走到。**刻意保留**作為「外部 script 將 window.mpuMsgList unset 為 undefined」的安全網，inline comment 已說明用途。

### 📋 Milestone 註記

本版收尾凍結表中的 v2.21.0。下一站：**v2.22.0 = #7 runtime_state helper**（Avatar §4）或 **#9 CSS theme / i18n hot swap**（Avatar §7 + §8），看實作複雜度決定。

---

## [2.20.0] - 2026-05-20

### 🔧 utility-functions.php 領域拆分（v2.20.0 #6 milestone）

純檔案搬移，無邏輯改動。原本 ~1,143 行 catch-all 拆成五個領域檔；`utility-functions.php` 留 36 行常數。

| 新檔 | 行數 | 函數 |
|---|---|---|
| `core/template-functions.php` | 142 | `array2str` / `str2array` / `output_filter` / `js_filter` / `render_prompt_template` / `build_user_info_prompt` |
| `core/file-functions.php` | 174 | `is_path_within_allowed_dir` / `secure_file_read` / `secure_file_write` / `get_dialogs_dir` / `ensure_dialogs_dir` |
| `core/encryption-functions.php` | 190 | `get_encryption_key` / `encrypt_api_key` / `decrypt_api_key` / `is_api_key_encrypted` / `get_provider_api_key` / `get_current_provider` |
| `core/wp-info-functions.php` | 284 | `get_wordpress_info` / `get_current_user_info` / `country_code_to_name` / `resolve_personality_id` |
| `core/network-functions.php` | 389 | `get_client_ip` / `get_client_ip_strict` / `fetch_external_api` / `clear_api_cache` / `check_rate_limit` / `rest_check_rate_limit` / `generate_session_token` / `validate_session_token` |

- **`mp-ukagaka.php` 載入順序**：`debug → core → utility (常數) → template → file → encryption → wp-info → network → ...` — encryption 在 wp-info 之前（provider key resolver 依賴 encryption），network 最後（最重的下游消費者）。
- **tests/bootstrap.php**：PHPUnit 同步載入。
- **雜項函數歸屬決定**（plan #5 動手前決定）：`array2str` / `str2array` / `output_filter` / `js_filter` → template；`fetch_external_api` / `clear_api_cache` → network；`resolve_personality_id` → wp-info。

### 🧹 冗餘防護清理

`mp-ukagaka.php` 載入順序保證下列 helper 在 caller 之前已存在，原本 `function_exists` 守衛屬冗餘：

- `chat/class-mpu-chat-history-service.php`：3 個 `mpu_chat_integrity_normalize_session_id` / `verify_history` / `store_history` 守衛移除。
- `integrations/akismet-integration.php`：4 個 `mpu_chat_integrity_*` 與 `mpu_rest_check_rate_limit` 守衛移除。
- `llm/class-mpu-chat-lock.php`：`normalize_session_id()` 內聯 fallback 移除 — chat-integrity 版本與 fallback byte-equivalent（`is_scalar` → `sanitize_key` → `substr 64`）。
- `rest/class-mpu-rest-base.php`：`rate_limit()` 守衛移除。
- `rest/class-mpu-rest-dialog.php`：2 個 `mpu_chat_integrity_*` 守衛移除。

### 🪦 舊 Sleep helper 死碼移除（v2.19.2 follow-up）

v2.19.2 把整點 sleep helper 標記為 dead code（僅 `wake_ghost` fallback 引用）。本版正式移除：

- `llm/llm-context-builder.php`：`mpu_get_daily_deep_sleep_start()` 與 `mpu_get_daily_oversleep_end()` 刪除。`_mod` 變體繼續使用。
- `rest/class-mpu-rest-dialog.php`：`wake_ghost()` 三層 `function_exists` fallback 簡化為直接呼叫 `_mod`。

### ✅ 驗證

- `npm run verify`：lint + bundle + PHPUnit（27 tests / 59 assertions）全綠。
- `grep -h "^function mpu_" includes/core/*.php | sort | uniq -d` 為空 — 無重複定義。
- Code-path grep 對舊整點 helper 只剩 doc / changelog / plan 歷史記錄引用。

### 📋 Milestone 註記

本版收尾凍結表中的 v2.20.0（v2.19.2 patch 後該欄已往前推一格）。下一站：**v2.21.0 = #8 JS 全域狀態封裝** — surface 較大、需 manual smoke test，故獨立成版，不與後端工作打包。

---

## [2.19.2] - 2026-05-20

### ✨ 睡眠系統分鐘精度

延伸 v2.19.1 的「每日整點抽籤」到分鐘精度。純後端 cache key 換代 — manifest schema 不動（仍以整點撰寫）。

- **新增 `_mod` 後綴函數族**：`mpu_get_daily_deep_sleep_start_mod()` / `mpu_get_daily_oversleep_end_mod()` 回傳 minutes-of-day（0–1439）。`[22, 23]` 的 deep_sleep_start 抽籤範圍升級為 `random_int(22*60, 23*60+59) = random_int(1320, 1439)`，即「22:00 ~ 23:59 任一分鐘」inclusive，而非只能抽「22 或 23 整點」。
- **所有比較全部換為 minutes-of-day**：`mpu_is_deep_sleep_time()` 跨午夜判斷改寫為分鐘單位（`$start_mod > $end_mod` 走 OR 分支）；`mpu_is_ip_woken_today()` / `mpu_mark_ip_as_woken()` 邊界檢查也升級為分鐘單位。
- **cache key 加 `_mod` 後綴**：強制 cache miss 重抽，避免讀到 v2.19.1 整點 key 留下的舊值。**不**做 backward-compat 讀舊 key。
- **前端 caller 同步升級**：`includes/core/frontend-functions.php` 原本呼叫整點版本，改呼 `_mod` 版本以對齊前端 chat block 渲染邊界與後端邏輯。
- **`wake_ghost()` 更新**：改讀 `_mod` 函數，保留 `function_exists` fallback 安全網。
- **`oversleep_max_hour` 語義微調**：`9` 從原本「09:00 整點起床」變成「09:59 之前起床」，與分鐘精度意圖一致。IP 記錄視窗對應延長 59 分鐘。

### ⚠️ 已知限制

- **舊整點函數變成 dead code**：`mpu_get_daily_deep_sleep_start()` / `mpu_get_daily_oversleep_end()` 僅剩 `wake_ghost` fallback 引用，邏輯上已死。清理延至 **v2.20.0**（`#6` utility-functions 拆分），因為檔案搬移與 dead code 移除是同一個前提條件。
- **Sleep settings input clamping 延後**：`_mod` 函數只用 `% 1440` 處理正向跨日溢位，**不** clamp 負數。manifest 設負整數會 break minutes-of-day 比較（PHP `%` 對負數保留 dividend sign）。實務上 manifest 由角色作者手寫，沒 admin UI 不會踩雷 — 完整 input validation 應跟未來 admin UI PR 一起做，孤立 clamp 沒意義。

### ✅ 驗證

- `npm run verify`：lint + bundle + PHPUnit（27 tests / 59 assertions）全綠。
- **PHPUnit 不涵蓋新 `_mod` 函數**：跟 v2.19.1 一樣，`set_transient` / `random_int` / `DateTimeImmutable` 在 `tests/bootstrap.php` 中未 mock。Manual smoke test 路徑：跨午夜切換、同日 cache hit、賴床+IP 記錄（已升級為分鐘精度）、設定錯誤、舊 cache 過渡（`_mod` 後綴強制 miss）。

### 📦 版本標記說明

本次 release 的兩個 commit（`b54d96c`, `239a5d1`）commit message 寫成 `feat(v2.20.0):` / `docs(plan): v2.20.0` — 是因為當時凍結表把 sleep-minute-precision 列為 v2.20.0。Release 時降版為 **v2.19.2** patch 是因為行為上是 v2.19.1 的延伸（manifest schema 不變、IP 機制不動，只換內部時間單位）。後續凍結表 milestone 因此整批往前推一版（utility-functions 拆分 → v2.20.0、JS 全域狀態封裝 → v2.21.0，依此類推）。

---

## [2.19.1] - 2026-05-19

### ✨ Frieren 動態睡眠時間（每日抽籤）

- **`deep_sleep_start` 支援 `[start, end]` 整點範圍 array**：原本固定整數（例：`23` 表示每天 23:00 入睡），現在可寫成 `[22, 23]` 由系統每天抽一次。同一天內任何 page load 都拿到同一個值（transient 鎖定到當日午夜），避免「使用者刷新頁面時芙莉蓮突然睡著／突然醒來」的詭異跳動。
- **`oversleep_probability` 提升至 1.0**：Frieren manifest 從 50% 機率賴床改成 100% 賴床；配合 `oversleep_max_hour: 9`，每天 7~9 點之間隨機醒來。被使用者主動 `/wake-ghost` 後 IP 記錄 3 小時，刷新頁面保持清醒。
- **新函數 `mpu_get_daily_deep_sleep_start()`**：比照既有 `mpu_get_daily_oversleep_end()` 寫成，cache key `mpu_deep_sleep_start_{date}_{pid}`、expire 對齊次日午夜（`DateTimeImmutable('tomorrow', wp_timezone())` + `max(60, ...)` clamp）。
- **array 防呆**：`is_array` 分支用 `random_int(min, max)` 自動處理範圍倒置；`count !== 2` 時記 `mpu_log_warning` 並 fallback 到首元素；非 array 設定維持整數讀法。
- **跨日 `24 → 0` 顯式處理**：若有人 manifest 設 `24`，`if ($v === 24) $v = 0` 轉為次日 00:00 的等價時刻（Frieren manifest 用 `[22, 23]` 避開歧義，但函數內保留防禦層）。
- **call site 替換**：`mpu_is_deep_sleep_time()`（`includes/llm/llm-context-builder.php`）與 `wake_ghost()`（`includes/rest/class-mpu-rest-dialog.php`）原本直接讀 `$sleep_settings['deep_sleep_start']`，改為呼叫新函數；後者保留 `function_exists` 防護作為載入順序的安全網。

### ⚠️ 已知限制

- **入睡時刻仍對齊整點**：本版只把「哪一個整點」隨機化。分鐘級隨機（22:43 入睡 / 07:35 起床）排定在 **v2.19.2** 與起床時間分鐘化、cache key 換代一起發佈。

### ✅ 驗證

- `npm run verify`：lint + bundle + PHPUnit（27 tests / 59 assertions）全綠。
- **PHPUnit 不涵蓋新函數**：`mpu_get_daily_deep_sleep_start()` 依賴 `set_transient` / `get_transient` / `wp_date` / `random_int` / `DateTimeImmutable`，這些不在現有 `tests/bootstrap.php` mock 範圍內。「測試通過」只證明 lint OK、syntax OK、沒打破既有測試路徑。新函數行為應透過 manual smoke test 驗證（跨日切換、同日 cache hit、賴床+IP、設定錯誤）。

---

## [2.19.0] - 2026-05-19

### 🏷️ 核心 Class 型別宣告（v2.19 #5 milestone）

- **`MPU_Session_Event`**：`build(string $kind, array $payload = []): array` 與 `kind_for_legacy_event(string $event): string` 補上 PHP 7.4 相容的 type hints。
- **`MPU_Input_Role`**：5 個 static method（`resolve` / `can_use_ability` / `current_can_use_ability` / `normalize_ability_name` / `is_known_role`）標註 `string` / `bool` 型別。內部 `(string)` cast 作為防禦層保留。
- **`MPU_REST_Base`**：`rate_limit` 回傳 `?WP_REST_Response`、`ok` 回傳 `WP_REST_Response`、`fail` 回傳 `WP_Error`；`$data` 故意保留 mixed。**`check_admin()` 不加 return type**：實際 contract 是 `true|WP_Error`，PHP 7.4 沒有 union types 可表達。
- **`chat-integrity.php`**：13 個函數加上 `string` / `array` / `bool` / `void` / `WP_Error` 型別。**`verify_history()` 不加 return type**：實際 contract 是 `true|null|WP_Error`。`_store_history(): bool` 兩條 return path（`return false` 與 `set_transient()` 的 bool）對齊。

### 📋 #6 utility-functions 拆分前置審查

- 確認 `chat-integrity.php` / `provider-helpers.php` / `utility-functions.php` 在 `rest/bootstrap.php` 之前載入，REST 與 chat history 內的 `function_exists('mpu_chat_integrity_*')`、`function_exists('mpu_rest_check_rate_limit')` 是載入順序保證下的冗餘防護候選。
- **本版不動這些防護**。冗餘清除排定在 #6 utility-functions 拆分時與檔案搬移同 PR 進行（拆檔可能改變載入順序，與「移除 function_exists 防護」是同一前提的兩面，分開做 review 容易失焦）。

### ✅ 驗證

- `npm run verify`：PHP lint、bundle build、PHPUnit（27 tests / 59 assertions）全綠。
- **行為不變**：純 type hint 補強，不涉及邏輯修改、檔案搬移或介面變更。

---

## [2.18.0] - 2026-05-18

### 🧪 測試基礎建設（PHPUnit + verify pipeline）

- **新增 PHPUnit 單元測試**：建立 `tests/` 目錄與最小 WordPress mock bootstrap（`tests/bootstrap.php`），讓純函式測試不必啟動完整 WordPress 即可運行。初始 6 個測試套件涵蓋 `chat-integrity`（filter / checksum / slice）、加密 round-trip、input role 解析、session event envelope、template 渲染與新增的 chat lock — 合計 22 tests / 51 assertions。
- **Composer-based 工具鏈**：dev 依賴（`phpunit/phpunit ^9.6`、`brain/monkey ^2.6`）寫在 `tools/php/composer.json`，vendor 隔離在 `tools/php/vendor/`。外掛本身沒有 runtime composer 依賴。
- **`npm run verify` 串接**：`tools/node/package.json` 現在依序執行 `lint:php` → `build` → `test:php`。build script 在 minify 失敗時改回非 0 exit code，CI 不會再假通過。
- **PHPUnit `cacheResult="false"`**：避免在受限 sandbox 中嘗試寫入 `.phpunit.result.cache` 時觸發 permission warning。
- **filter / action mock 改為真正執行 callback**：`tests/bootstrap.php` 現在會按 priority 排序、依 `accepted_args` 截取參數。未來測 `mpu_chat_integrity_mode` 之類的 filter 才會可靠。

### 🔒 Chat Lifecycle Lock（並發 LLM 防護）

- **新增 `MPU_Chat_Lock` 類別**（`includes/llm/class-mpu-chat-lock.php`）：用 `add_option($key, $payload, '', 'no')` 作為 atomic check-and-set primitive。沒有用 transient 是因為 `get_transient()` + `set_transient()` 在並行請求下不是 atomic，會讓 lock 本身產生它要防的 race。
- **過期 lock retry 流程**：`add_option()` 失敗時若現有 lock 已過期，會先 `delete_option()` 再嘗試一次 acquire。崩潰的 PHP worker 留下的 stale lock 會在下一個請求週期自我修復。
- **token 驗證的 release**：`release($session_id, $token)` 用 `hash_equals()` 比對 token，避免某個請求誤釋放別人持有的 lock。是 double-finally bug 的防禦層。
- **60 秒 TTL、走 `mpu_chat_lock_ttl` filter**，範圍 clamp 到 `[10, 300]` 秒。
- **三個 action hook**：`mpu_chat_lock_acquired` / `mpu_chat_lock_released` / `mpu_chat_lock_conflict`，可用於 metrics、audit log，或未來 approval-hub 整合。
- **只接在 `/chat/user` 與 `/chat/user-stream`**：`/chat/greet`、`/chat/context` 與 `/debug_mcp` 故意不鎖（既有路由 rate limit 已足夠）。
- **衝突回 HTTP 429**：直接用既有的 `$this->fail()` envelope，前端錯誤路徑不需要改動。
- **SSE 安全的 release**：lock 取得後立刻 `register_shutdown_function()`，stream loop 在 chunk 之間透過 `exit_if_stream_aborted()` 檢查 `connection_aborted()`。客戶端中途斷線時 lock 仍會釋放，token 驗證確保 shutdown fallback 不會誤殺後續請求的 lock。
- **lock context 記錄** `route`、`input_role` 與 `ip_hash`（`sha256(client_ip)` 前 12 字元）— 不存原始 IP 即可協助 triage。

### 🧹 REST Chat 重複消除

- **新增 `prepare_auto_chat_context()` helper**（`MPU_REST_Chat`）：把 `chat_context()` 與 `chat_greet()` 共用的設定（`ai_enabled` / `ai_greet_first_visit` 預檢、provider + API key、`wp_info`、ukagaka 身分、language、personality、time context、13 個 variable map、解析後的 system prompt）集中起來。
- **`require_first_visit_greeting` 選項 flag** 用來區分 `chat_greet` 的額外檢查 — 不需要拆成兩個 helper。
- **response shape 完全不變**。checksum 寫入（`store_after_auto` 的 `'context'` / `'greet'` kind）刻意不動。`prepare_user_chat_args()`、chat lock、SSE 流程都不受影響。

### 🏷️ SSE Stream 狀態 Badge（runtime 驗收 UI）

- **`#ukagaka_msgbox` 右上角的可見 `.mpu-state-badge`**：在 2.17.0 已加的 `data-mpu-stream-state` 屬性之上加可見層。沒有新的 state machine，只是把屬性視覺化。
- **六個可見狀態**：`thinking`、`streaming`、`tool`、`error`、`timeout`、`busy`。`status` 為避免空 badge 而對應到與 `streaming` 同一個 label。
- **chat lock 衝突時的 `busy` 狀態**：`handleStreamFailure()` 從 SSE 的 JSON fallback path 偵測 `error.code === "mpu_chat_lock_busy"` 或 `error.data.status === 429`，顯示在地化的「混雑中…」訊息而非通用錯誤。
- **第一個 delta 時設為 `streaming`**：`onDelta` 不再清除屬性，改為 `setStreamState("streaming")`，文字串流期間 badge 仍會持續顯示。
- **走既有 `mpuL10n` 機制做 i18n**：新增的 `mpuL10n.streamStates` map（`考え中…` / `応答中…` / `調べてる…` / `エラー` / `タイムアウト` / `混雑中…`）可透過既有 `.po` / `.mo` workflow 翻譯。

### 🐛 錯誤修正

- **Ollama 空陣列 Tool Calls 解析修正**：修復 Ollama 在回傳不帶參數的 tool call（如 `get-bot-blocker-stats`）時，PHP `json_decode` 與 `json_encode` 會將空物件 `{}` 轉為空陣列 `[]`，導致 Ollama 引擎拋出 `"Value looks like object, but can't find closing '}' symbol"` 錯誤的問題。現已透過 `mpu_normalize_ollama_assistant_message()` 強制將空陣列轉為 `stdClass` (`{}`)，並新增 5 個 PHPUnit 測試案例涵蓋所有邊界狀況。
- **Provider HTTP 錯誤日誌強化**：遇到 HTTP >= 400 錯誤時，現在會嘗試提取 Provider JSON 的 `error` 欄位以提供更乾淨的錯誤訊息，並透過 `error_log` 匯出包含 tool_calls 結構與 4KB response tail 的完整除錯資訊。
- **UI 狀態 Badge 位置微調**：修正 `.mpu-state-badge` 樣式，使其更貼合對話框右上角內側，避免被裁切。

### ✅ 驗證

- `npm run verify`：PHP lint、bundle build、PHPUnit（22 tests / 51 assertions）全綠。
- `js/dist/ukagaka-bundle.js` 與 `.min.js` 已重建（169.7 KB → 79.0 KB）。
- `git diff --check`：無 whitespace error。

---

## [2.17.0] - 2026-05-15

### 🛡️ Input Role Resolver 與伺服器端 Tool Gate

- **新增 `MPU_Input_Role` 類別**（`includes/core/class-mpu-input-role.php`）：把 LLM 輸入身分（`admin` / `system` / `subscriber` / `visitor`）從 WordPress capability 切分出來。`resolve()` 從 request context 與當前 WP 狀態解析角色；`can_use_ability()` 採用 hardcoded whitelist，確保 prompt injection 抵抗力。
- **Request-scoped input context**（`request-state.php`）：新增 `mpu_set_request_input_context()` / `mpu_get_request_input_context()`，讓 chat endpoint、tool 曝光與 tool 執行三處看到同一份 context。
- **兩段式 tool gate**（`abilities-integration.php`）：`mpu_get_mcp_tools_for_llm()` 在送進 LLM 前按角色過濾工具列表，`mpu_execute_mcp_tool()` 在執行前再驗證一次以攔截 out-of-band 工具呼叫。內建 ability（`visitor-pulse`、`ai-crawler`、`wp-postviews`、`wp-bot-blocker` ×3）全部改用 `MPU_Input_Role::current_can_use_ability()`，並保留 `current_user_can('manage_options')` 安全 fallback。

### 📡 Session Event Envelope（SSE）

- **新增 `MPU_Session_Event` 類別**（`includes/llm/class-mpu-session-event.php`）：定義事件類型（`stream.delta / status / done / error`、`tool.request / result`、`nonce.refresh`），並以 `eventId + ts + kind + payload` 結構包覆 payload。
- **向後相容的 SSE**：`streaming-helpers.php` 透過 `kind_for_legacy_event()` 把 legacy 名稱轉成新 kind，再包進 envelope。既有端點不需修改即可運作。
- **前端 dispatcher**（`js/ukagaka-chat.js`）：`window.MPU_EVENTS` 常數 + `mpuNormalizeSseEvent()` 自動辨識新 envelope 與 legacy event，switch 同時處理兩種型式。

### ⏱️ Client-side SSE Watchdog（zombie state 防護）

- **45 秒逾時** + `AbortController.abort()`：45 秒內沒收到任何 SSE event 即 abort 串流、從 `mpuChatHistory` rollback 未送出的 user message、恢復輸入框，並透過 `mpuShowBalloon()` 顯示在地化逾時訊息。
- **統一的 `onEvent` hook**：所有 legacy / 新 envelope event 都會重置 watchdog，避免 per-handler 漏寫導致的 bug。
- **Typewriter 安全**：`stream.done` 與 `stream.error` 在 `onEvent` 層清除 watchdog。後端完成但前端 typewriter 仍在吐字時不會誤殺。
- **`data-mpu-stream-state` 屬性**掛在 `#ukagaka_msgbox`：暴露 `thinking / tool / status / timeout / error` 給 CSS 主題或 debug 使用。
- **`tool.request` handler**：未來後端發 `tool.request` 時，前端會顯示「工具執行中…」狀態。

### 🔐 Chat Integrity 三段模式

- **新增 `chat_integrity_mode` 選項**（預設 `audit`）：`audit` 只 log mismatch、不中斷請求（既有行為）；`warn` 在 log 上以 WARN 標示並觸發 `mpu_chat_integrity_mismatch` action hook；`block` 在 expected checksum 存在且 actual 不符時回 `WP_Error`（HTTP 409）。
- **三個控制點**：常數 `MPU_CHAT_INTEGRITY_MODE` > option `chat_integrity_mode` > filter `mpu_chat_integrity_mode`。expected checksum 缺失時一律放行 — `block` 模式也不會在首次請求或 session reset 時誤殺。
- **`mpu_chat_integrity_should_block` filter**：可在 `block` 模式下進行 per-session 放行或灰度測試。
- **`mpu_chat_integrity_mismatch` action hook** 在 audit / warn / block 三個模式全部觸發，payload 為 `{ session_id, expected, actual, mode, decision, source, history_count }` — 即使在 `audit` 模式也能掛 metrics 收集或 webhook。
- **REST chat 正確傳遞 WP_Error status**：`class-mpu-rest-chat.php` 改成讀取 `error_data['status']`（409），不再硬編碼 400。
- **最小切口**：完全不動 store / normalize / slice 流程，只改 verify mismatch 的決策層。

### 🛠️ Hardening Patches（從 2.16.1–2.16.4 收編）

- **後台 output escaping 全面稽核**。
- **Bot-blocker 掃描器豁免**：對 security scanner 完整豁免、對已登入請求略過 rate-limit，避免錯誤的 reputation flag；抽出 `mpu_bb_is_logged_in_request()` 共用 helper。
- **`MPU_PLUGIN_DIR` 修正**：將未定義的常數參照改為 `plugin_dir_path(MPU_MAIN_FILE)`。

### ✅ 驗證

- 所有觸動的 PHP 檔案皆通過 `php -l`（`chat-integrity.php` / `chat-history-service.php` / REST chat / `core-functions.php` / ability 群）。
- JS 語法檢查通過（`ukagaka-chat.js` / `dist/ukagaka-bundle.js`）。
- Bundle 已重新建構（`js/dist/ukagaka-bundle.js` + `.min.js`）。
- `git diff --check` 無 whitespace error。

---

## [2.16.0] - 2026-05-13

### 🧠 User Memory MVP

- **新增 `/remember` 管理員指令**：管理員可在前台聊天框輸入 `/remember`，從最近 20 則對話歷史萃取穩定的管理人事實並保存到 `mpu_user_memory` usermeta。
- **新增 Memory REST Controller**：新增 `MPU_REST_Memory` 與 `POST /memory/extract`，使用 `manage_options` 權限、WordPress REST nonce、60 秒 transient 節流，以及 defensive cleanup。
- **記憶注入 system prompt**：`mpu_resolve_system_prompt()` 會把保存的記憶注入為「管理人についての記憶（参考メモ）」，並明確標示為參考資訊、不可解讀為指令，降低 prompt injection 風險。
- **後台 AI 設定頁記憶管理**：AI tab 底部新增記憶卡片，可查看目前 facts、最後更新時間，並透過 nonce-protected 表單清除目前管理員的記憶。

### ✅ 發版品質與 Runtime Info 改善

- **新增發版驗證工具鏈**：`npm run lint:php` 補掃主外掛檔，並新增 `npm run verify` 串接 PHP lint 與 JS build。
- **新增 REST smoke test checklist**：新增 `docs/REST_SMOKE_TEST.md`、`docs-en/REST_SMOKE_TEST.md` 與 `docs-jp/REST_SMOKE_TEST.md`，涵蓋 baseline、session token、token enforcement、chat round-trip、admin guard 與 SSE headers。
- **GitHub 自動更新支援**：內建 Plugin Update Checker v5.6（`vendor/plugin-update-checker/`），新增 `includes/updater/github-updater.php`。外掛現在會偵測新的 GitHub Release，並在 WordPress 後台顯示更新通知。每次發版時上傳 `mp-ukagaka.zip` 作為 release asset 即可啟用一鍵更新。
- **Runtime Info 微調**：調整天氣溫度觸發閾值，並在 Frieren `instructions.md` 補上感情觸發規則，避免過度反應但提升角色細節。
- **i18n debt cleanup**：補齊繁中與日文翻譯，更新 `mp-ukagaka-zh_TW.po/.mo` 與 `mp-ukagaka-ja.po/.mo`。

---

## [2.15.0] - 2026-05-08

### 🔒 安全與防濫用硬化

- **公開 AI REST 端點加上 Session Token 防護**：新增匿名訪客專用的 IP-bound session token，`/chat/context`、`/chat/greet`、`/chat/user`、`/chat/user-stream` 均需帶有效 token 才能請求，降低外部直接打 API 造成 quota 消耗的風險。
- **Session Token 採 lazy-fetch**：前端不再把 token 直接嵌入 HTML，改由 `/session-token` 端點在首次請求前取得，並設定 `no-store` 快取標頭，避免 full-page cache 洩漏第一位訪客的 token。
- **前端請求統一注入 token**：`mpuFetch` 與 `mpuFetchSSE` 會自動帶入 `X-MPU-Session-Token`，並加入 helper 存在性防護，讓一般 REST 與 SSE 流程一致。
- **Raw JavaScript 擴充權限收緊**：`extend[js_area]` 的儲存與顯示需具備 `unfiltered_html` 權限，避免只有 `manage_options` 但不應輸出 raw JS 的環境誤開前端腳本注入能力。
- **Personality ZIP 覆蓋流程硬化**：ZIP 先解壓到暫存目錄，覆蓋前加入二次確認、backup→rename→rollback 流程、保留 ID 大小寫不敏感保護，以及 `realpath()` + `DIRECTORY_SEPARATOR` 邊界檢查，降低 Zip Slip、symlink 逃逸與錯誤覆蓋風險。

### 🧱 結構整理與維護性改善

- **Chat history/checksum 邏輯抽出**：新增 `MPU_Chat_History_Service`，集中處理 session id、history parsing、checksum verify/store，以及 user-chat / streaming 回覆後的 integrity 更新。
- **Admin save handler 拆分**：`mpu_handle_options_save()` 拆出通用設定、偽春菜設定、AI、LLM、日記與 Bot Blocker 等保存 helper，降低單一函式承擔過多分支的維護成本。
- **保留既有 response shape**：REST 路由、錯誤碼、SSE event 名稱與 checksum 行為維持向後相容，這次以小步驟硬化為主，不做大規模重構。

### ✅ 驗證與文件

- **JS bundle 已重建**：同步更新 `js/dist/ukagaka-bundle.js` 與 `js/dist/ukagaka-bundle.min.js`。
- **語法與建置驗證**：已通過 PHP lint 與 Node build 驗證。
- **新增硬化計畫文件**：新增 `plan/Code_Quality_Hardening_Plan.md`，記錄安全風險評估、分階段改善順序、實作摘要與 post-review fixes。

---

## [2.14.1] - 2026-04-30

### ✨ 管理人個人化設定後台化

- **後台欄位新增**：在 **通用設定** 頁面新增「Admin full nickname」、「Admin short name」、「Admin birthday」三個欄位（`options_general.php`），管理人的暱稱、簡稱和生日改為直接在 WordPress 後台設定，不再需要手動編輯 `personality.md` 和 `calendar.json`。
- **System Prompt 自動注入**：`core-functions.php` 新增 `mpu_get_admin_profile_prompt_block()`，在 System Prompt 渲染時自動將後台設定的 `{{admin_nickname}}`、`{{admin_name}}`、`{{admin_birthday}}` 替換為真實值，並附加覆寫指示段落。
- **calendar.json 動態覆蓋**：`personality-prompts.php` 新增邏輯，當後台設定了生日時，自動移除 `calendar.json` 中的 `"MM-DD"` 佔位符並以實際生日替換，無需手動修改檔案。
- **生日格式驗證**：`admin-functions.php` 新增 `mpu_normalize_admin_birthday()`，在儲存時強制驗證並標準化為 `MM-DD` 格式。

### 🎌 節日期間（Holiday Periods）支援

- **新增 holiday_periods 機制**：在 `calendar.json` 中支援特定期間的節日定義，系統會自動判斷當天日期是否落在指定期間內並觸發對應的節日反應。
- **內建節日期間**：
  - **ゴールデンウィーク**（4/29–5/5）：黃金週期間角色會有專屬的節日反應
  - **お正月**（1/1–1/7）：新年期間角色會有專屬的節日反應
- **Prompt 與 Weight 支援**：在 `prompts.json` 和 `weights.json` 中新增對應節日期間的 prompt 類別和權重設定。

### 📖 文件更新

- **USER_GUIDE 追加 Abilities 說明**：在三語版本（繁中、英文、日文）的 USER_GUIDE 互動對話模式章節中，新增「Abilities」子章節，說明內建的 6 個 Abilities（人氣文章查詢、IP 封鎖、Bot Blocker 統計/清除、AI 爬蟲偵測、訪客脈動），附使用範例與所需外掛清單。
- **README 更新**：三語版 README 更新管理人設定說明，改為後台設定方式。
- **新增截圖**：`screenshot7.PNG` — Abilities 功能展示（角色以角色口吻回報 Bot Blocker 統計）。

---

## [2.14.0] - 2026-04-29

### LLM 串流支援擴充：Gemini / Claude

- **新增 Gemini streaming**：`gemini` provider 追加 `generate_chat_stream()`，使用 `streamGenerateContent?alt=sse` 端點，純文字回覆會以 SSE `delta` 逐步送回前端。
- **新增 Claude streaming**：`claude` provider 追加 `generate_chat_stream()`，支援 Anthropic Messages API 的 `content_block_start`、`content_block_delta`、`content_block_stop` 事件，並能累積 `input_json_delta` 完成 tool call 參數。
- **保留 tool call 安全性**：Claude 在啟用 MCP tools 時會先緩衝文字，若本輪發生 tool call，不會先輸出 pre-tool text，避免前端顯示內容、後端 checksum 與最終工具結果不一致。
- **Gemini tools fallback**：Gemini streaming 目前先支援純文字。偵測到 MCP tools 可用時會 fallback 到同步 `generate_chat()`，再以單一 `delta` 回傳結果，避免 tool 能力在 streaming 模式下靜默失效。

### Streaming 穩定性與前端顯示

- **共用 SSE parser**：新增 `mpu_stream_sse_events()`，統一處理跨 chunk 行合併、多行 `data:` 拼接、空行 dispatch，以及串流末尾未以空行結束時的最後 event flush。
- **Claude tool loop 防護**：Claude streaming 達到 `MPU_MAX_TOOL_TURNS` 時會回傳 `max_turns_exceeded`，不再以空白成功回應結束。
- **Claude tool 參數防護**：Claude streaming 解析 tool input JSON 失敗時會記錄 debug log，並以空參數交由工具回傳錯誤結果，避免使用不完整或錯誤參數執行。
- **前端串流打字佇列**：對話模式的 streaming 顯示改為局部 timer 與 pending queue，遵守後台 `typewriter_speed` 設定，並加入完成保護避免重複 finalize。
- **Bundle 已重建**：同步更新 `js/dist/ukagaka-bundle.js` 與 `js/dist/ukagaka-bundle.min.js`。

---

## [2.13.9] - 2026-04-29

### 🧹 死碼清除（第一、二階段）

移除所有確認在 PHP 與 JS source 中完全無 runtime 呼叫者的孤兒函數，無任何行為變更。

**PHP 移除（共 21 個函數，涵蓋 8 個檔案）：**

- `utility-functions.php`：`mpu_enforce_rate_limit`、`mpu_verify_ajax_nonce`、`mpu_input_filter`
- `ai-functions.php`：`mpu_call_gemini_api`、`mpu_call_openai_api`、`mpu_call_claude_api`、`mpu_call_ollama_api`、`mpu_get_allowed_conditional_tags`
- `chat-api-handlers.php`：`mpu_call_ollama_with_messages`、`mpu_call_openai_with_messages`、`mpu_call_claude_with_messages`、`mpu_call_gemini_with_messages`（統一入口 `mpu_call_ai_api_with_messages` 保留）
- `llm-context-builder.php`：`mpu_compress_context_info`、`mpu_get_context_label`（依賴不存在的 `mpu_load_personality_dynamics`）
- `llm-functions.php`：`mpu_get_ollama_settings`、`mpu_debug_system_prompt`
- `personality-prompts.php`：`mpu_get_dynamic_prompt_templates`
- `personality-decorations.php`：`mpu_get_personality_all_decorations`
- `personality-loader.php`：`mpu_is_frieren_personality`、`mpu_get_personality_trait`
- `weather-functions.php`：`mpu_get_weather_info`

**JS 移除（共 4 個函數，涵蓋 3 個 source 檔案）：**

- `ukagaka-base.js`：`mpu_init_visit_tracking`
- `ukagaka-core.js`：`mpu_hideMsgText`、`mpuMoe`
- `ukagaka-chat.js`：`mpu_escapeHTML`

---

## [2.13.8] - 2026-04-27

### ✨ 新功能：訪客脈動與 AI 爬蟲訊號

- **新增 AI 爬蟲偵測**：在 `llm-slimstat.php` 新增 AI crawler UA 對照表與查詢函式，能從 Slimstat 最近的 bot 記錄中辨識 GPTBot、ClaudeBot、Google-Extended、PerplexityBot 等 AI / LLM crawler。
- **新增 Visitor Pulse 訊號**：加入三種以 Slimstat 為資料源的訪客脈動事件：
  - `foreign_visitor`：最近 60 分鐘首次出現的國家
  - `traffic_spike`：最近 1 小時人類訪客相較前 1 小時顯著上升
  - `late_night_visitor`：深夜時段仍有真人訪客
- **新 auto-talk 事件分支**：`/check-spam-event` REST handler 新增 `ai_crawler_alert` 與 `visitor_pulse_alert` 兩條分支，讓 Frieren 可對這些站點訊號產生自發對話。
- **新 Abilities tools**：新增 `mp-ukagaka/get-recent-ai-crawlers` 與 `mp-ukagaka/get-visitor-pulse` 兩個唯讀工具，供 LLM 主動查詢近期 AI crawler 與訪客脈動摘要。

### 💤 人格一致性修正：Sleep mode 與事件推送整合

- **睡眠時段夢話分支**：新增 `mpu_pick_sleep_dream_line()` helper。當角色處於 deep sleep window（預設 00:00–06:00，並可延伸 oversleep）時，新事件不再呼叫 LLM，而是改從 `sleep_mode.json` 抽夢話。
- **新增 `visitor_dreams` 夢話池**：`ghost/Frieren/sleep_mode.json` 新增訪客脈動專用夢話；AI 爬蟲在睡眠時段視為低優先度事件，直接略過不播報。
- **避免人格矛盾**：修正深夜訪客事件在睡眠時段仍產生清醒分析台詞的問題，讓 sleep mode 與事件推送邏輯一致。

### 🔒 安全性與穩定性修正

- **修正 foreign visitor 去重時機**：`mpu_detect_visitor_pulse_event()` 改為純查詢，不再在偵測階段直接寫入 seen countries。只有在訊息成功生成後，才由 `mpu_visitor_pulse_commit_seen_countries()` 提交，避免新國家被冷卻「吃掉」後永遠不再播報。
- **Abilities 權限收緊**：兩個新 abilities 的 `permission_callback` 改為 `current_user_can('manage_options')`，避免透過 Core Abilities API REST 端點未授權讀取訪客脈動與 crawler 資訊。
- **強化 IP spoofing 防護**：新增 `mpu_get_client_ip_strict()`，並將 rate limit 與 `/visitor-info` 的 Slimstat 反查改用嚴格 IP 判定，避免攻擊者偽造 `X-Forwarded-For` / `CF-Connecting-IP` 繞過 LLM endpoint 配額或探詢任意 IP 的訪客資訊。
- **移除無效權重配置**：`weights.json` 保留有效的 `ai_crawler_reactions` / `visitor_pulse_reactions` 類別權重與 `is_bot` 調整，移除原先不會生效的 `is_foreign_visitor` / `is_late_night` context 配置。
- **前端事件分流補完**：`ukagaka-core.js` 為 `ai_crawler_alert`、`visitor_pulse_alert`、`spam_alert` 新增明確 log 分支，不再把新事件誤標成 Akismet spam。

---

## [2.13.7] - 2026-04-25

### 🐛 錯誤修正：Akismet 5.7 相容性 — AI 對話無法生成

- **根本原因**：Akismet 5.7 新增了 `akismet/get-stats` ability（WordPress Abilities API），其 input schema 使用了 JSON Schema 聯合型別：`type: ['object', 'null']`。mp-ukagaka 透過 `wp_get_abilities()` 收集所有已註冊的 ability 並作為 tool 定義送給 LLM，此聯合型別陣列導致整個 API 呼叫被拒絕——Gemini、OpenAI、Claude 均要求 `type` 為純字串（如 `"object"`），不接受陣列。
- **症狀**：啟用 Akismet 後 AI 對話完全無法生成，角色退回使用內建靜態對話。關閉 Akismet 後 AI 對話立即恢復正常。
- **修正**：在 `abilities-integration.php` 新增 `mpu_normalize_schema_for_llm()`，遞迴走訪 ability input schema，將聯合型別陣列（如 `['object', 'null']`）轉換為單一字串（取第一個非 null 型別），在送給任何 LLM provider 之前套用。

### 🐛 錯誤修正：SPA 模式下 gotop 按鈕被攔截

- **症狀**：當 WordPress 主題啟用 SPA（Single Page Application）模式時，偽春菜 dock 上的「回到頂部」（gotop）按鈕點擊事件會被 SPA 路由攔截，導致按鈕無法正常運作。
- **修正**：在 `frontend-functions.php` 為 `#toTop` 錨點加上 `data-spa-ignore` 屬性，並在 `ukagaka-features.js` 的點擊處理中加入 `e.stopPropagation()`，防止 SPA 框架攔截此錨點點擊。

---

## [2.13.6] - 2026-04-24

### 📖 文件更新：開發文件補完與多語同步

- **補齊昨天遺漏的開發文件更新**：完成 `API_REFERENCE.md`、`DEVELOPER_GUIDE.md`、`CANVAS_CUSTOMIZATION.md`、`DEBUG_SLIMSTAT.md`、`ABILITIES_API.md`、`GHOST_CREATE_GUIDE.md` 的全面校對與修正，讓文件內容與目前程式碼結構一致。
- **REST / Abilities / Personality 架構對齊**：
  - 將開發文件中過時的 AJAX、舊 hooks、舊全域變數與舊模組描述，統一更新為現行 REST controller 架構。
  - 明確區分 Abilities API 的對外概念與內部仍保留的 MCP 命名過渡層，避免文件與實作落差。
  - 將人格製作指南更新為以 `instructions.md + personality.md` 為主，並保留 `system_prompt.md` / `manifest.json.system_prompt` 的 legacy fallback 說明。
- **補齊目前實際支援的 personality / frontend 文件內容**：
  - 補上 `touchzones.json`、`sleep_mode.json`、`calendar.json`、`diary.json`、`emoji-keywords.json`、`scripts` 等現行結構。
  - 更新 canvas 裝飾系統、Slimstat/訪客資訊除錯流程、初始化資料與前端腳本載入說明。
- **三語文件同步**：繁中、英文、日文三套開發文件與 changelog 已同步更新，內容保持一致。

---

## [2.13.5] - 2026-04-23

### 📖 文件更新：USER_GUIDE 全面重構

- **三部分架構重組**：將使用者指南重新整理為三大部分，以使用情境為軸而非以設定分頁為軸：
  1. **基本設定**（有無 AI 皆適用）：包含安裝、偽春菜管理、自動對話、自定義表情系統等
  2. **AI 功能設定**（需要 AI 提供商）：包含 LLM 設定、頁面感知、互動對話模式、思考模式、天氣感知、自動日記
  3. **靜態對話功能**（不使用 AI 時）：包含外部對話檔案、會話設定、特殊代碼、擴展功能
- **自定義表情系統說明修正**：明確標注靜態對話和 AI 生成對話皆支援表情功能，並移至第一部分（原文件位置有誤）
- **各語言版本內容同步**：
  - 英文版（`docs-en/`）補上天氣感知功能、ZIP 上傳兩個原本缺少的章節
  - 日文版（`docs-jp/`）補上 ZIP 上傳章節
- **繁中版殘留日文標題修正**：修正繁中版中未翻譯的日文標題（`キャラクター設定` → `角色設定`、`トリガーページ` → `觸發頁面`、`AI会話の表示時間` → `AI 對話顯示時間` 等）
- **移除開發者技術細節**：將 35 個對話類別完整列表、PHP 動態權重程式碼、思考模式 PHP 片段等開發者內容從使用者指南移除（這些內容屬於 DEVELOPER_GUIDE）

---

## [2.13.4] - 2026-04-21

### 🐛 錯誤修正

- **API 金鑰欄位的瀏覽器自動填入問題**：LLM 設定頁面中 Gemini、OpenAI、Claude 三個 API 金鑰輸入欄（`type="password"`）的 `autocomplete="off"` 改為 `autocomplete="new-password"`。部分瀏覽器會忽略 `autocomplete="off"` 而注入已儲存的密碼，導致欄位顯示 `.......' 而非提示文字，已修正。
- **自訂模型輸入欄的使用者名稱自動填入問題**：為三個提供商的自訂模型文字輸入欄加入 `autocomplete="off"`。瀏覽器將密碼欄位旁的文字輸入誤判為帳號欄並自動填入 `admin`，已修正。
- **Gemini 2.5 Pro 的 thinking 區塊未處理**：Gemini 2.5 Pro 在實際回應文字之前，會在 `parts` 陣列中加入內部思考區塊（`"thought": true`）。原本的程式碼僅檢查 `parts[0].text`，當第一個 part 為 thinking 區塊時會解析失敗。已在 `generate_text`、`generate_chat`、`test_connection` 三處修正，改為跳過 `thought: true` 的區塊並取得第一個正常文字。
- **Gemini 2.5 Pro 測試連接發生 MAX_TOKENS**：測試連接請求的 `maxOutputTokens` 設為 `50`，被 Gemini 2.5 Pro 的內部 thinking（47 個 token）全部耗盡，導致實際回應無法輸出、`content.parts` 為空。已調整為 `200`。
- **移除 Gemini 2.5 Pro 預設選項**：從預設模型清單中移除 `gemini-2.5-pro`。由於其必要的 thinking 開銷在現有 token 預算下不穩定，仍可透過自訂模型輸入欄使用。

---

## [2.13.3] - 2026-04-20

### ✨ 增強：自訂模型選擇 & Claude 版本更新

- **自訂模型輸入**：在 **LLM 設定**頁面與**日記 AI 設定**頁面的 Gemini、OpenAI、Claude 三個提供商的模型選擇器中，新增「自訂模型…」選項。選擇後會顯示文字輸入框，可直接輸入任意合法的模型 ID，不再受限於預設清單。儲存方式與選擇預設選項完全一致，後端邏輯無需變更。
- **Claude 模型版本更新**：將 Claude 預設模型清單更新至最新版本：
  - Sonnet 4.5 → **Sonnet 4.6** (`claude-sonnet-4-6`)
  - Opus 4.5 → **Opus 4.7** (`claude-opus-4-7`)
  - Haiku 4.5 維持不變（`claude-haiku-4-5-20251001`，目前尚無 4.6 版本）

---

## [2.13.2] - 2026-04-16

### 🌐 本地化：系統訊息統一為日文

- **全面 i18n 整理**：橫跨 18 個 PHP 文件，將所有硬編碼的繁體中文系統訊息（約 200+ 條）統一替換為日文，確保使用者介面輸出語言一致。
  - `includes/core/`：檔案操作、安全驗證、速率限制錯誤訊息（`utility-functions.php`）；前端 UI 標籤與 `mpuL10n` 本地化字串（`frontend-functions.php`）；對話檔案錯誤訊息（`ukagaka-functions.php`）。
  - `includes/rest/`：所有 REST API 錯誤與狀態訊息（`class-mpu-rest-base.php`、`class-mpu-rest-chat.php`、`class-mpu-rest-dialog.php`、`class-mpu-rest-ghost.php`、`class-mpu-rest-test.php`、`class-mpu-rest-touch.php`）。
  - `includes/llm/`：所有 LLM 提供商錯誤訊息（`class-mpu-ai-provider-gemini.php`、`class-mpu-ai-provider-openai.php`、`class-mpu-ai-provider-claude.php`、`class-mpu-ai-provider-ollama.php`、`class-mpu-ai-provider-base.php`）；日記、Ollama 驗證與工具迴圈訊息（`diary-functions.php`、`llm-functions.php`、`tool-loop-guard.php`）。
  - `includes/admin-functions.php`：所有後台管理通知與 ZIP 上傳/驗證訊息。
- **擴充 `mpuL10n`**：在前端 JavaScript 物件中新增多個本地化 key（`loadingFailed`、`errorOccurred`、`duplicateRequest`、`requestFailed`、`securityVerificationFailed`、`animationLoadFailed`、`connectionError`、`chatExit`），JS 模組不再依賴硬編碼的備用字串。
- 所有字串仍以 `__()` 包裹，支援未來的 `.po`/`.mo` 語言包。

### 🐛 修復 (Bug Fix)

- **`mpuL10n` Key 名稱不一致**：`js/ukagaka-dialog.js` 引用了未定義的 `mpuL10n.loadFailed`（正確應為 `mpuL10n.loadingFailed`），導致靜默回退至內建字串，現已修正。
- **`chatExit` Key 未定義**：`js/ukagaka-chat.js` 引用的 `mpuL10n.chatExit` 從未在 `wp_localize_script` 中註冊，現已在 `frontend-functions.php` 補充此 key。

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
