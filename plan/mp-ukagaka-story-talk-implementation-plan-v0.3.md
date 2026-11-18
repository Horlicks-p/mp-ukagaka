# MP-Ukagaka：Story Talk（連續劇情自發對話）詳細實作計畫

**版本**：Draft v0.3｜2026-11-18  
**狀態**：提案／尚未授權實作、合併、發版或部署  
**對象**：Claude Code／Codex CLI 及專案維護者  
**目標分支**：從目前 `mp-ukagaka` 2.x `main` 建立獨立功能分支（實際名稱由執行者確認）  
**原始碼審查基準**：`main` @ `f5e4971`（PR #52 合併後）；開工前須重新記錄 HEAD SHA 與現況  
**靈感來源**：華和梨（KAWARI）[ストーリートーク](https://github.com/ukatech/kawari-docs/blob/main/tips/story-talk.md)  
**與 3.0 的關係**：**完全獨立**。Mesh Avatar 3.0 暫停中；Story Talk 不需變更 Shell、Renderer、RO 風格或人物尺寸。

---

## v0.3 變更摘要

Codex 審查 v0.2 後補強生命週期，架構不變（Story 不碰計時器、忙碌略過 tick、資料搭 `/dialog`）：

| # | 變更 | 理由（程式碼依據） |
|---|---|---|
| 1 | **預備期搶佔偵測**：reservation 記下保留當下的 Typewriter run id；每個延遲 callback 都用 `storyCanOwnBubble()` 檢查 run id 未變、聊天／互動／頁面感知／問候／blocking 未接手、頁面未隱藏、未逾時 | 淡出與動畫期間 `runId` 還是 `null`，v0.2 只檢查 token、世代與期限；聊天打開後舊 callback 仍可能啟動故事並覆蓋聊天 |
| 2 | **只看 run id 不夠，必須同時檢查狀態旗標** | 串流聊天用自己的計時器直接寫入對話框（`js/ukagaka-chat-send.js:160`，「絕不碰全域 mpuTypewriterTimer」）；頁面感知／問候的 placeholder 也直接 `.html()`。這些都不會改變 run id |
| 3 | **舊 run 的延遲 DOM 寫入一律失效**：`flushUpdate()` 與 rAF 寫入前檢查 run id；`mpu_cancelTypewriter()` 也讓 run id +1 | v0.2 把舊 rAF 回寫排除在範圍外，但 `flushUpdate()`（`js/ukagaka-base.js:891`）直接改 `innerHTML`，遇到不經 Typewriter 的寫入者（串流聊天、placeholder）時，故事殘文會蓋掉新內容。仍不需要 `onCancel` |
| 4 | **storage 降級後以記憶體為權威**，直到重新載入 | 配額滿時常見「讀得到、寫不進」；v0.2 提交前又重讀 localStorage，會讀回舊進度 |
| 5 | **多分頁提交檢查比對完整狀態快照** | v0.2 只比對 ID／revision／nextStep；另一分頁講完故事後 active 回到空值，與「預定第一段」時相同，卻已有新的冷卻 |
| 6 | **Phase 0 新增：盤點所有直接寫入 `#ukagaka_msg` 的地方** | 第 2 點的旗標清單必須完整 |
| 7 | E2E 補「淡出／動畫預備期中開聊天、觸摸、頁面感知」；#17 改為驗證「不回寫且不提交」 | 原矩陣主要測打字中 |

## v0.2 變更摘要

v0.1 經 Claude 與 Codex 對照原始碼審查後修訂。核心原則（不建排程器、不經 LLM、只在完成時提交）不變，主要修正生命週期規則與資料載入方式：

| # | 變更 | 理由（程式碼依據） |
|---|---|---|
| 1 | **計時器責任**：Story 不碰計時器；改為「故事播放期間，auto tick 視為忙碌而略過」 | `startAutoTalk()` 的 tick 在 `mpu_nextmsg("auto")` 之後立刻 `startAutoTalk()`（`js/ukagaka-core.js:397-404`）；間隔可短至 3 秒，下一個 tick 會蓋掉還沒打完的故事 |
| 2 | **取消自我修復**：pending 是否存活由 Typewriter 狀態推導，不需要任何人「釋放」 | `mpu_cancelTypewriter()`、被新台詞取代都只 `clearTimeout`，沒有通知；若靠旗標釋放，漏一處就會讓自發對話永久停止 |
| 3 | **Typewriter 只加 `onComplete`＋run id**，不加 `onCancel` | 取消與取代都會讓舊流程永遠走不到結尾分支，`onComplete` 不會被呼叫，已足以區分 |
| 4 | **頁面隱藏即作廢 pending** | `visibilitychange` 只 `stopAutoTalk()`，不取消打字（`js/ukagaka-features.js:487`）；背景分頁的 setTimeout 被節流，故事會在訪客沒看到時「完成」 |
| 5 | **手動 OK／startup 會作廢故事**，不提交，照常執行 | v0.1 只說不啟動故事，沒規定打斷進行中的故事 |
| 6 | **透過既有 `/dialog` 回應載入**，不在前端另外 fetch | `load_dialog()` 已解析 personality、產生 `msg_emojis`；`loadExternalDialog()` 已有 load token 與角色世代檢查 |
| 7 | **台詞驗證拒絕 `<`、`>`、`&`**，取代 HTML escaping | Typewriter 會把 entity 逐字打出（畫面閃出 `&l`） |
| 8 | **Storage key 加站點範圍** | 同網域多個 WordPress（`/site-a/`、`/site-b/`）會共用 origin 的 localStorage |
| 9 | **明訂提交順序**：先 Story State、後 history | v0.1 §5.4 沒有指定順序與允許的故障結果 |
| 10 | **修正 `mpuDisplayBuiltInMessage()` 的 callback 參數錯位** | `options.callback` 被傳進 `mpu_typewriter()` 的第 3 參數（速度）（`js/ukagaka-core.js:99`）；目前無呼叫端使用，Story 會是第一個 |
| 11 | 頻率改以 tick 描述；補安全事件優先權；更新 Shuffle Bag 現況 | 見 §2、§3 |

---

## 0. 給 Agent 的首要指示（必讀）

本文件是**功能實驗與實作規格**，不是要求立即發布。請先閱讀專案 `AGENTS.md`、`CLAUDE.md`、相關互動流程文件、最新原始碼與測試，再盤點本計畫是否仍適用。所有「目前」的描述均以編寫當時的主線為準；若新版本已有相同機能，優先復用、縮小修改。

**產品原則**：用現代可靠性工程，保留早期伺か／RO 文化的手工台詞魅力。**不要**把它做成聊天機器人劇情平台。

嚴格界線：

1. **不准**為 Story Talk 建立 KIS/SHIORI 解譯器、腳本語言、通用任務系統、好感度系統、視覺化編輯器、抽象 Event Bus 或新的對話排程器。
2. **不准**為此全面重構 LLM、REST、SSE、聊天歷史、`MPU_STATE`、Frieren Shell、WordPress 設定頁或外掛發布流程。
3. **不准**把固定劇情送交 LLM 重新生成、翻譯或改寫；劇情內容是站長親手編寫、原文呈現的資料。
4. **不准**直接修改正式站、在 `main` 工作、合併 PR、建立 Release 或推送部署；功能驗收後由維護者單獨決策。
5. 第一版只為**芙莉蓮 Frieren** 撰寫故事；啟用條件是「該人格目錄有 `stories.json`」，不寫死角色名稱，但不得因此做大規模通用化。
6. 保持原本的使用者操作、睡眠、頁面感知、聊天、觸摸、送禮、安全事件、API Fallback、角色切換及自發對話時序。失敗時回到原有 Auto Talk，不可讓角色卡住。
7. 共用 Typewriter 只能做**向下相容的最小接點**（§6.3），必須附測試證明既有呼叫端行為未變。
8. **Story 不呼叫 `startAutoTalk()`／`stopAutoTalk()`**。計時器維持現有行為（§6.5）。

**執行方式**：先交一份極短的 Phase 0 核對報告（本計畫已回答大半，見 §10）；得到維護者核准後，才開始核心程式修改。每個階段獨立提交、能單獨回退。

## 1. 目標、範圍及成功標準

### 1.1 產品定義

Story Talk 是「由作者撰寫的短篇、分段、自發對話」。每個故事包含 2～5 段台詞，角色偶爾在既有 Auto Talk 時機說出其中一段，下一次合適機會接續。故事間可穿插一般內建台詞或 LLM 自發台詞，因此不像連續彈出的劇情播放器。

例如「宝箱の誘惑」：

1. `この宝箱、何が入っているんだろう……`（thinking）
2. `ミミックの可能性はあるけど……`（what）
3. `でも、開けない理由にはならないよね。`（smirk）

以上為**提案示例台詞**，不是官方原作台詞轉錄；維護者可自行編修。第一版測試資料不必大量創作，先用一個三段故事即可。

### 1.2 必須達成

- LLM 置換模式開啟或關閉，均可由既有 Auto Talk 間歇觸發固定 Story Talk。
- 抽中 Story Talk 時**不呼叫 LLM**；沒抽中時完整走舊流程。
- 下一段必須沿著同一故事順序執行，不能跳段、重複記帳或跨角色、跨站點串線。
- 換頁、F5、關閉／重開瀏覽器後可以接續未完成故事；長期不回訪則能合理重置。
- 只有**在可見頁面上自然打完**的台詞才寫入 `mpuChatHistory` 並推進進度，且每段只記錄一次。
- 使用既有表情／Emoji、Typewriter、訊息框及動畫通道；無法解析表情時仍能顯示文字。
- 觸摸、聊天、睡眠、頁面感知、送禮、安全事件、角色切換、手動 OK，以及原本 LLM Fallback 的行為不退化。
- 任何取消路徑都不會讓自發對話停止（§6.4 自我修復）。
- 無故事檔、壞 JSON、存儲不可用、讀取失敗等情況下安全降級到既有流程。

### 1.3 本次不做

- 不做手動「下一段」按鈕，也不讓 OK 鈕啟動或接續故事。
- 不做多選項、分歧、條件式任務、對話劇本程式語法、任意動作命令、音效、獎勵或故事編輯 UI。
- 不做伺服器端跨裝置／跨訪客故事同步，不使用 IP 作為故事身份。
- 不從遠端 URL 動態執行腳本，不向 LLM 送出故事內容要求它重新寫台詞。
- 不加入 3.0 Mesh Avatar 的依賴，也不更換現有復古 2D 美術。
- 不修改 Non-overlap Random（PR #52 已合併）。Story 的 tick 不從 Shuffle Bag 抽、不寫 `#ukagaka_msgnum`（§6.2）。
- 不修正聊天歷史 key（`mpu_chat_history`）同樣沒有站點範圍的既有限制；只在 Story 自己的 key 處理（§5.1）。

## 2. 現有程式核對結果（開工前再次確認）

| 元件／檔案 | 已確認的現況 | 對本功能的影響 |
|---|---|---|
| `js/ukagaka-core.js` `startAutoTalk()` | 單一 `setTimeout`。tick 時：閒置超過 60 秒跳過 → 睡眠檢查 → `mpu_checkSpamEvent()` → 沒有安全事件才 `mpu_nextmsg("auto")` → **立刻** `startAutoTalk()` 重排 | 下一個 tick 從**上一個 tick** 起算，不等打字結束。**Story 不改這個行為**，改用忙碌略過（§6.5） |
| 自發對話間隔 | 後台設定 3～30 秒（預設 8 秒）；深夜睡眠時段約 ×9；閒置判定只看 `mousemove keydown scroll click` | 機率是每 tick 一次（§3.2）。間隔可能短於故事播放時間 |
| `js/ukagaka-core.js` `mpu_checkSpamEvent()` | 安全事件（Akismet、Bot Blocker、Turnstile、AI Crawler、Visitor Pulse）在 `mpu_nextmsg("auto")` 之前處理；有事件時這次 tick 不進 `mpu_nextmsg` | 安全事件天然優先，Story 不需處理，但事件台詞會取代進行中的故事（視為取消） |
| `js/ukagaka-core.js` `mpu_nextmsg()` | `auto`／`startup`／手動在同一處分支；Guard 依序為 blocking、聊天、互動、auto 關閉、睡眠、`stopAutoTalk()`、頁面感知、問候、Ollama busy | Story 決策放在所有 Guard 之後、`mpu_hidemsg()` 及 LLM／內建分支之前 |
| `js/ukagaka-base.js` `mpu_selectNextMessage()` | PR #52 後，random 模式從共用 Shuffle Bag 抽（`mpuDrawBuiltInIndex()`），整輪不重複 | Story tick 不呼叫它，所以不消耗袋子；也不寫 `#ukagaka_msgnum` |
| `js/ukagaka-core.js` `mpuDisplayBuiltInMessage()` | 第 99 行把 `options.callback` 傳進 `mpu_typewriter()` 第 3 參數（速度）。目前沒有呼叫端傳 `callback` | 參數錯位，Story 若重用此 helper 必須一併修正（§6.3） |
| `js/ukagaka-core.js` 內建自發台詞 | 在**開始顯示時**以 `synthetic` user＋`auto_talk` assistant 寫入 `mpuChatHistory` | Story 沿用相同格式，但改在**完成時**寫入 |
| `includes/rest/class-mpu-rest-dialog.php` `load_dialog()` | 解析 `personality_id`，以 `mpu_build_builtin_dialog_messages()` 產生 `msg` 與 `msg_emojis`（`xxx.png`） | 在同一回應加 `stories` 欄位（§4.1） |
| `js/ukagaka-dialog.js` `loadExternalDialog()` | 有 `loadToken` 與 `mpuCharacterGeneration` 檢查；LLM 置換模式也會載入（作為後備） | 故事資料隨 dialog store 一起換，換角色的競態已有處理 |
| `includes/personality/personality-items.php` | 以 `mpu_get_personalities_dir() . '/' . $personality_id . '/items.json'` 讀取人格 JSON | `stories.json` 照同樣慣例讀取 |
| `includes/llm/response-normalizer.php` | `mpu_normalize_get_supported_tags()`、`mpu_normalize_resolve_emotion_tag()` 解析表情 tag（含別名） | 伺服器端把 `emotion` 解析成檔名，前端不另寫表情驗證 |
| `includes/chat/class-mpu-chat-history-service.php` | `ALLOWED_MSG_TYPES` 含 `synthetic`、`auto_talk` | **不需要新 `type:'story'`** |
| `includes/llm/chat-integrity.php` | checksum 只取 `type='chat'` | Story 維持 `auto_talk`，不動 checksum 規則；仍須回歸驗證 |
| `js/ukagaka-chat-history.js` | key 為 `mpu_chat_history`，經 `mpu_getLocal()`；raw history 上限 40 entries | Story 用既有 history 寫入介面，**進度不放在 history 裡** |
| `js/ukagaka-base.js` `mpu_setLocal()` | 固定 **24 小時 TTL** | 不能拿來保存 7 天進度 |
| `js/ukagaka-base.js` `mpuClearReloadChatSession()` | F5 只移除 `mpu_chat_history`、`mpu_chat_session_id` | Story 獨立 key，F5 不會刪除 |
| `js/ukagaka-base.js` `mpu_typewriter()` | 第 3 參數速度、第 4 參數布林或 options；**沒有完成 callback**；換新台詞時 `clearTimeout` 舊的；沒有回傳值 | 加 `onComplete` 與 run id（§6.3） |
| `js/ukagaka-base.js` `mpu_cancelTypewriter()` | 只 `clearTimeout` 並把 timer 設為 `null` | 取消後舊流程永遠走不到結尾 → 不會 `onComplete` |
| `js/ukagaka-base.js` `mpu_waitForTypewriterComplete()` | timer 為 `null` 就回呼；取消也是 `null`；30 秒強制回呼 | **不能**當成「播放成功」證據 |
| `js/ukagaka-features.js` blur／focus／`visibilitychange` | 只 `stopAutoTalk()`／`startAutoTalk()`，不取消打字 | 頁面隱藏時 Story 自行作廢 pending（§6.6） |
| `tools/e2e/interaction-e2e.js` | Playwright＋WordPress Playground＋假 Ollama | 新增少量 Story 情境，避免呼叫付費 LLM |
| `tools/node/package.json` | `verify` 含 JS smoke 與 PHPUnit，**不含** `test:interaction` | E2E 另外執行，不假稱已包含在 `verify` |

### 2.1 重要澄清：什麼叫「播放完成」

不能用以下事件推進 `nextStep`：

- 成功抽中故事；
- 開始淡出前一句；
- 呼叫 `mpu_typewriter()`；
- 計時器變成 `null`（也可能是取消）；
- `mpu_waitForTypewriterComplete()` 被呼叫（逾時也會強制呼叫）。

本計畫的「完成」是：**這一段由 Story 啟動的那一次 Typewriter（同一個 run id）自然走到結尾分支並 flush，且在那一刻：同一角色世代、pending 沒被作廢、頁面在 pending 期間從未隱藏。**

## 3. 方案決策

### 3.1 LLM 共存：固定台詞，只插入自發對話

- 使用 LLM 置換對話時，抽中 Story 的 tick 直接播放固定台詞，**不進 LLM API**。
- 未抽中則走原有 LLM 路線；關閉 LLM 時未抽中走原有內建台詞。
- **不要**把故事進度 prompt 化後要求 LLM 續寫；若將來需要「受故事影響但自由發揮」可另案評估。
- 只對 `trigger === 'auto'` 有效。`startup`、手動 OK、睡眠喚醒、聊天及互動事件都不消費 step；**手動 OK 與 startup 會作廢進行中的故事**（§6.4）。

### 3.2 觸發策略：每 tick 10% 開始／40% 接續

所有數字為**初版測試預設值**，集中在 `stories.json` 的 `config`，不是散落各處的 Magic Number。

| 條件 | 預設 |
|---|---|
| 無進行中故事、不在冷卻中 | 每個有效 auto tick **10%** 機率開始一個故事 |
| 有進行中故事，且已達最小間隔 | 每個有效 auto tick **40%** 機率接續下一段 |
| 相鄰段落最小間隔 | **90 秒**（從上一段**完成**起算） |
| 講完一個故事後的全域冷卻 | **24 小時** |
| 未完成故事的過期 | 最後一次成功完成後 **7 天** |
| 故事總量 | 初版 **1 個故事、3 段** |
| 節奏 | **不另設 Timer**；只在正常 auto tick 判斷 |

「有效 auto tick」指通過閒置、睡眠、安全事件與 `mpu_nextmsg()` 全部 Guard 的 tick。以預設 8 秒間隔估算：開始一個故事約需 80 秒活躍瀏覽；之後每段約 90 秒＋平均 20 秒；三段約 4 分鐘活躍時間（進度跨頁保存）。後台間隔改成 30 秒時大約慢 3～4 倍。手機上只閱讀不滑動會被判為閒置，故事會暫停推進——這是既有自發對話的行為，記錄即可。

只有一個故事時，冷卻結束後允許重播；多個故事時，下一個盡量避開最後完成者，但不可因此永久無故事可選。

### 3.3 高階優先順序

| 優先權 | 狀態／事件 | Story 對策 |
|---|---|---|
| 最優先 | 角色切換、卸載／重新載入 | pending 因世代不符作廢 |
| 高 | 頁面隱藏（`document.hidden`） | 作廢 pending，不提交（§6.6） |
| 高 | 安全事件（`mpu_checkSpamEvent`） | 在 Story 決策之前處理；事件台詞取代故事 → 作廢 |
| 高 | 聊天、觸摸、裝飾、送禮、喚醒、頁面感知、首次問候 | 不抽、不播放；進行中的故事被它們的台詞取代 → 作廢 |
| 高 | 手動 OK、startup | 作廢 pending，照常執行原流程 |
| 高 | 睡眠未喚醒、auto 關閉、訪客閒置 | 不抽；原 skip 邏輯不變 |
| 高 | LLM 請求進行中 | 不插隊；沿用既有 Ollama busy skip |
| 一般 | 正常 `auto` tick，且有存活的故事播放 | **略過這次 tick**（§6.5） |
| 一般 | 正常 `auto` tick | 決定 Story 或原來台詞 |
| 低 | Story 資料損壞、存儲失敗、過期 | 安全降級到原本對話 |

## 4. `stories.json` 資料契約

### 4.1 路徑與載入

```text
ghost/
  Frieren/
    manifest.json
    items.json
    stories.json              # 新增：純資料，不是 JS 腳本
```

**載入方式**：由 PHP 讀取，附在既有 `GET /dialog`（`load_dialog()`）回應的新欄位 `stories`。不新增 REST 路由，前端不另外 fetch 靜態檔。

- 新增 `includes/personality/personality-stories.php`（或併入既有人格模組，由 Agent 評估），照 `items.json` 的讀法：`mpu_get_personalities_dir() . '/' . $personality_id . '/stories.json'`，經 `mpu_secure_file_read()` 讀取。若新增檔案，依 `mpu_load_modules()` 載入順序登記。
- `load_dialog()` 已有 `$personality_id`；檔案不存在、`enabled` 不是 `true`、驗證失敗 → `stories` 為 `null`（debug 模式可 `mpu_debug_log` 原因）。
- `emotion` 在伺服器端用 `mpu_normalize_resolve_emotion_tag( $emotion, mpu_normalize_get_supported_tags( $personality_id ) )` 解析成 `xxx.png`，格式與 `msg_emojis` 相同；無法解析就是 `null`（台詞照常顯示）。
- 前端在 `loadExternalDialog()` 收到回應時，`stories` 隨 dialog store 一起存放。換角色、重新載入時一起替換，沿用既有 load token 與世代檢查。

回應中的 `stories` 形狀（伺服器已驗證、已解析表情）：

```json
{
  "personality": "Frieren",
  "config": { "start_chance": 0.1, "continue_chance": 0.4, "min_step_gap_seconds": 90, "completed_cooldown_hours": 24, "unfinished_expire_days": 7 },
  "stories": [
    { "id": "treasure_chest", "revision": 1, "steps": [ { "text": "この宝箱、何が入っているんだろう……", "emoji": "thinking.png" } ] }
  ]
}
```

### 4.2 原始檔格式（`enabled:false` 表示實驗開關預設關閉）

```json
{
  "format_version": 1,
  "enabled": false,
  "config": {
    "start_chance": 0.10,
    "continue_chance": 0.40,
    "min_step_gap_seconds": 90,
    "completed_cooldown_hours": 24,
    "unfinished_expire_days": 7
  },
  "stories": [
    {
      "id": "treasure_chest",
      "revision": 1,
      "title": "宝箱の誘惑",
      "steps": [
        { "text": "この宝箱、何が入っているんだろう……", "emotion": "thinking" },
        { "text": "ミミックの可能性はあるけど……", "emotion": "what" },
        { "text": "でも、開けない理由にはならないよね。", "emotion": "smirk" }
      ]
    }
  ]
}
```

`title` 只供作者辨識，不送到前端。**第一版不新增 WordPress 後台開關。**

注意：`ghost/` 隨外掛發布，外掛更新會覆蓋站點上手改的 `stories.json`。因此「開啟功能」等於「發一個 `enabled:true` 的版本」，由維護者決定。

### 4.3 驗證規則（PHP 端，回應前；PHPUnit 覆蓋）

- `format_version` 必須是支援的正整數；不支援 → `stories: null`，原本 Auto Talk 不受影響。
- `enabled` 必須是布林 `true` 才輸出。
- `config` 缺欄位用預設；機率限 `[0, 1]`，時間為非負且有合理上限，不接受 NaN 或非數字。
- `stories` 必須是陣列；`id` 唯一且符合 `^[a-z][a-z0-9_-]{0,63}$`；`revision` 為正整數。
- 每個故事 **2～5 個 steps**；`text` 為非空字串，1～250 Unicode 字元。
- **`text` 含 `<`、`>`、`&` 任一字元 → 該故事整個略過**（fail closed）。Typewriter 以 `innerHTML` 輸出並把 `<...>` 當標籤，而 escape 後的 entity 會被逐字打出造成閃字，因此直接禁止這三個字元。
- `emotion` 只能是字串 tag，解析不到就略過表情；不接受路徑或 URL。
- 不支援任意 HTML、JavaScript、URL、`eval` 或自訂指令；未知屬性忽略、不執行。
- 第一版不加入權重、任務條件、分支或巢狀結構。

前端只做防禦性檢查（形狀不對就當作沒有故事），不重複整套驗證。

### 4.4 與 Emoji／Renderer 解耦

`emotion` 是**語義標籤**，由伺服器映射到現有 Frieren Emoji 檔名；前端用 `mpuDisplayDialogueEmoji()` 顯示。本次不得改動 Shell。

## 5. Story State：保存位置與生命週期

### 5.1 保存位置與隔離

使用**獨立**的 `localStorage` key：

```text
mpu_story_v1_<siteKey>_<personality>
```

- `siteKey`：把 `mpuRestUrl` 正規化（host 轉小寫、去掉結尾斜線）後取短雜湊（如 FNV-1a 32-bit，8 碼 hex）。用來隔離同一 origin 下多個 WordPress（`/site-a/`、`/site-b/`）。這是**站點**識別，不是訪客識別。
- `personality`：`stories.personality`（伺服器回傳的人格 ID）。
- 禁止混入 `mpu_chat_history`／`mpu_chat_session_id`；禁止使用 IP、訪客統計 ID、Cookie 或 WordPress 使用者資料。

不使用 `mpu_setLocal()`（24h TTL）。Story 有自己的小型讀寫函式，自行驗證結構與過期。**這不構成通用 Storage Framework。**

### 5.2 狀態 Schema

```json
{
  "v": 1,
  "activeStoryId": "treasure_chest",
  "activeRevision": 1,
  "nextStep": 1,
  "lastCommittedAt": 1794900000000,
  "cooldownUntil": 0,
  "lastCompletedStoryId": null
}
```

- `nextStep`：下一段要播的 0-based index。
- `lastCommittedAt`：上一段**完成**的時間；用於 90 秒最小間隔與 7 天過期。
- `cooldownUntil`：整個故事池的再次啟動時間。
- 故事講完：`activeStoryId=null`、`nextStep=0`、記錄 `lastCompletedStoryId`、設定冷卻。
- 只存 ID／revision／index／時間戳，不存台詞。

### 5.3 讀取、修復及過期規則

1. key 不存在：初始化空狀態。
2. JSON 解析失敗、欄位無效、索引越界：回空狀態，不中斷普通台詞。
3. `activeStoryId` 在新資料中消失、`revision` 改變：清除 active，**不跳到別的故事第 N 段**。
4. `now - lastCommittedAt > 7 天`：未完成故事過期清除（仍受冷卻限制）。
5. F5、SPA／原生換頁、關閉重開：進度保留。
6. 切換角色：pending 作廢；**不刪**其他角色已提交的進度。
7. `localStorage` 讀取被拒、或任何一次**寫入**失敗（含配額滿）：進入**降級模式**。本頁從此以記憶體狀態為**唯一權威來源**，不再讀 localStorage（避免讀回舊進度），直到重新載入頁面。不報錯、不阻斷原有對話。降級後跨頁會回到 storage 中的舊進度、重播已講過的段落，這是必須接受的限制。
8. 多分頁：第一版不承諾嚴格 exactly-once。reservation 時保存**完整 State 快照**（§5.2 所有欄位）；提交前重新讀 State，與快照**逐欄比對**（含 `lastCommittedAt`、`cooldownUntil`、`lastCompletedStoryId`），任何不同就放棄提交。這可擋下「另一分頁已講完故事、active 回到空值但冷卻已設定」的情況。降級模式下不重讀，比對記憶體即可。同時開兩分頁仍可能重複播報，屬已知 MVP 限制，測試應記錄。不得為此加伺服器端鎖或持久訪客 ID。

### 5.4 提交順序與故障模型

提交只發生在 `onComplete`（§6.3），且全部在同一個 JS 任務內同步完成：

1. 檢查 `storyCanOwnBubble(pending)`（§6.4）且 `committed === false`；不符 → 放棄。
2. 取得目前 State（正常模式重讀 localStorage；降級模式用記憶體），與 reservation 快照逐欄比對（§5.3 第 8 點）；不符 → 放棄。
3. 設 `committed = true`（記憶體去重，防止重入）。
4. **先寫 Story State**（推進 `nextStep`、更新時間戳、必要時設冷卻）：先更新記憶體，再寫 localStorage。寫入失敗 → 進入降級模式（§5.3 第 7 點），繼續第 5 步。
5. **後寫 history**：push `synthetic` + `auto_talk` 兩筆，呼叫 `mpu_saveChatHistory()`。失敗只記 debug log。
6. 清除 pending。

選擇「先 State、後 history」的理由：同步程式中途不會被關分頁打斷，實際可能失敗的只有 storage 例外。允許的故障結果是「進度推進了但 history 少一筆」（LLM 上下文少一句獨白，影響輕微）。**在同一頁內不允許**「history 有了但進度沒推進」（訪客會再看到同一段，history 也重複）；因為記憶體一定會先推進，這在本頁內必然成立。跨頁則受 storage 是否可寫限制：降級模式下重新載入後可能重播，是已知限制（§5.3 第 7 點）。

## 6. 故事播放生命週期（本計畫最重要的一節）

### 6.1 進入點

在 `mpu_nextmsg(trigger)` 中，既有 Guard（blocking、聊天、互動、auto 關閉、睡眠、`stopAutoTalk()`、頁面感知、問候、Ollama busy）全部通過之後，`mpu_hidemsg()` 與 LLM／內建分支之前：

```text
if trigger !== 'auto':
    storyTalk.invalidate('manual-or-startup')     // 手動 OK、startup：作廢，照常往下
else:
    if storyTalk.isLive():  log + return          // 故事還在播：略過這次 tick（§6.5）
    reservation = storyTalk.tryReserve(store, now, rng)
    if reservation: storyTalk.play(reservation); return
// 原有 LLM／內建流程
```

確切行號由 Phase 0 確認；原則是「不改既有 Guard 順序、不複製 LLM／內建流程」。

### 6.2 決策與播放（偽碼）

```text
tryReserve:
  stories 不存在 / 形狀不對              => null
  有 active 且未過期:
     now - lastCommittedAt < min_gap     => null（不消耗亂數）
     rng() < continue_chance             => reserve(active, nextStep)
     else                                 => null
  無 active:
     now < cooldownUntil                  => null（不消耗亂數）
     rng() < start_chance                 => reserve(pick story, 0)
     else                                 => null

reserve => 記憶體 pending = { storyId, revision, stepIndex, generation, token,
                              reservedAt, baseRunId: mpuGetTypewriterRunId(),
                              runId: null, stateSnapshot: {…§5.2 全部欄位},
                              hiddenDuringPending: false, committed: false }
           （不寫 State、不寫 history）

play:
  mpu_hidemsg(600) → 等淡出（與內建台詞相同流程）
  → 每個延遲 callback 第一件事：storyCanOwnBubble(pending)，否則作廢並 return
  → Frieren 動畫（沿用內建台詞的 triggerCharacterAnimation 流程）
  → 動畫 callback：再次 storyCanOwnBubble(pending)
  → mpuDisplayDialogueEmoji(step.emoji)
  → pending.runId = mpu_typewriter(step.text, "#ukagaka_msg", null, { onComplete })
  → mpu_showmsg(400)
  注意：內建台詞流程在動畫 callback 中會先 mpu_cancelTypewriter()；Story 必須在
  自己呼叫它**之前**做 storyCanOwnBubble 檢查，因為取消也會讓 run id +1（§6.3）。
  不呼叫 mpu_selectNextMessage / mpuDrawBuiltInIndex，不寫 #ukagaka_msgnum，
  不呼叫 startAutoTalk / stopAutoTalk。

onComplete => §5.4 提交
```

### 6.3 Typewriter 最小接點

`mpu_typewriter()` 只加三件事，皆向下相容：

1. **run id**：模組層級計數器，每次呼叫 `mpu_typewriter()`（含空字串清除）都 +1，並**回傳**這次的 id（目前沒有回傳值，既有呼叫端不受影響）。`mpu_cancelTypewriter()` 也讓計數器 +1，使被取消的 run 正式失效。另提供 `mpuGetTypewriterRunId()` 讀取目前 id。
2. **舊 run 的延遲寫入失效**：`flushUpdate()`、rAF callback 與 `processNextChar()` 在寫入 DOM 或排下一步前，先確認 `myRunId === 目前 run id`；不符就清掉自己的 rAF 並結束，不寫 `innerHTML`。這修掉「舊 rAF 在新內容出現後回寫舊字」：新內容若來自 Typewriter 本來就會在同一幀蓋回去，但串流聊天、placeholder 等直接寫入者不會，故事殘文會留在畫面上。
3. **`options.onComplete`**：只在**這次 run** 走到 `processNextChar()` 的結尾分支、`flushUpdate()` 之後呼叫，且呼叫前確認 run id 仍是目前的。最多一次。

不需要 `onCancel`：被新台詞取代或取消時，舊 run 的 `setTimeout` 已被清掉，且 run id 已失效，永遠走不到結尾分支，`onComplete` 自然不會發生。「取消」由 §6.4 的判定處理。

第 2 點會改變一個既有行為：舊 run 不再在新內容之後補寫一幀。這原本就是錯誤行為，沒有呼叫端依賴它；仍須以測試證明一般台詞的速度、批次、標籤處理與最終輸出不變。

同時修正 `mpuDisplayBuiltInMessage()`（`js/ukagaka-core.js:99`）：目前把 `options.callback` 傳進 `mpu_typewriter()` 第 3 參數（速度）。改為傳 `null` 作速度，`options.callback` 併入第 4 參數的 `onComplete`。目前沒有呼叫端傳 `callback`，行為不變；Story 若重用此 helper 就能直接拿到正確的完成通知。

### 6.4 存活判定與自我修復（取代 v0.1 的「釋放／交還」責任）

pending 是否還能擁有對話框，**每次都從現況推導**，沒有需要別人記得清除的旗標。

只看 run id 不夠：串流聊天用自己的計時器直接寫入對話框（`js/ukagaka-chat-send.js:160`），頁面感知／問候的 placeholder 也直接 `.html()`，都不改變 run id。所以同時檢查狀態旗標：

```text
storyCanOwnBubble(p):            // 每個延遲 callback、onComplete、提交前都呼叫
  p 不是目前 pending 或 p.committed              => false
  p.generation !== window.mpuCharacterGeneration => false
  p.hiddenDuringPending || document.hidden       => false
  mpuChatModeActive                              => false
  mpuIsInteractionDialogActive()                 => false   // 觸摸、裝飾
  mpuMessageBlocking                             => false
  mpuAiContextInProgress || mpuGreetInProgress   => false   // 頁面感知、首次問候
  （Phase 0 盤點出的其他直接寫入者的旗標）
  p.runId === null:                              // 淡出／動畫預備期
      mpuGetTypewriterRunId() !== p.baseRunId    => false   // 有人用 Typewriter 寫過
      now - p.reservedAt >= 預備期上限（10 秒）   => false
      else                                        => true
  p.runId !== mpuGetTypewriterRunId()            => false   // 被取代或取消
  else                                            => true

isLive():                       // auto tick 用來決定是否略過
  沒有 pending                                   => false
  !storyCanOwnBubble(pending)                    => 作廢, false
  pending.runId !== null && mpuTypewriterTimer === null => 作廢, false   // 已結束
  else                                            => true
```

旗標清單刻意與 `mpu_nextmsg()` 的 Guard 對齊。實作時讀取同一批既有變數，不複製一份狀態；若 Phase 0 發現抽出共用 helper 比較乾淨，可以抽，但不得改變 `mpu_nextmsg()` 的行為。

因此所有取消路徑都不需要 Story 主動「釋放」，也不會讓自發對話停住：

| 情況 | 結果 |
|---|---|
| 淡出／動畫預備期中開聊天、觸摸、頁面感知、問候 | 旗標或 run id 改變 → 下一個 callback 檢查失敗，作廢，不呼叫 `mpu_typewriter()`，不覆蓋新內容 |
| 打字中被聊天、觸摸、送禮、頁面感知、安全事件取代 | run id 或旗標改變 → 作廢；舊 run 的延遲寫入也失效（§6.3）；計時器由那些流程照舊管理 |
| 直接呼叫 `mpu_cancelTypewriter()`、沒有其他流程接手 | run id +1 → 作廢；外層 tick 已經重排計時器，自發對話照常 |
| 手動 OK、startup | `mpu_nextmsg()` 開頭 `invalidate()`，照常執行；不提交 |
| 播放過程拋出例外 | `play()` 以 try/catch 包住：作廢 pending、記 debug log；計時器已由外層重排 |
| 角色切換 | 世代不符 → 作廢；切回來仍可接續已提交進度 |
| 預備期卡住（動畫 callback 沒回來） | 10 秒後判定作廢 |

### 6.5 計時器（不改既有行為）

- Story **不呼叫** `startAutoTalk()`／`stopAutoTalk()`。tick 本身在 `mpu_nextmsg("auto")` 後就會重排，Story 不改變這個責任分配，普通台詞的行為完全不變。
- 間隔短於故事播放時間時（例：間隔 3 秒、台詞較長或動畫較久），下一個 tick 進到 `mpu_nextmsg("auto")` 會因 `isLive()` 為 true 而**略過**，不蓋掉故事、不送 LLM 請求；計時器照常重排，等下一個 tick。
- 故事打完後，下一個 tick 照常決策。
- 不另寫 `mpu_showmsg()`／`mpu_hidemsg()`／裝飾 click 的平行實作。

### 6.6 頁面隱藏

- 監聽 `visibilitychange`：`document.hidden` 為 true 時，若有 pending 就設 `hiddenDuringPending = true`（等同作廢）。不取消打字（畫面行為不變），只是這一段不提交。
- 只看 `document.hidden`，不看 window `blur`（切到其他視窗時頁面可能仍然可見）。
- 計時器的恢復維持既有 `visibilitychange`／`focus` 流程；Story 不參與。
- 結果：訪客切回來時可能看到打完的故事台詞，但這段未提交，之後會再講一次——可接受，比「沒看到卻推進」好。

### 6.7 對話 Owner／世代檢查

沿用 `window.mpuCharacterGeneration`，不新增大型 Store。每個節點都呼叫 `storyCanOwnBubble()`（§6.4）：淡出 callback、Frieren 動畫 callback、呼叫 `mpu_typewriter()` 之前、`onComplete`、提交前（§5.4）。Story 不得釋放其他互動持有的 `messageBlocking` owner。

## 7. 歷史、LLM 與 checksum 契約

### 7.1 歷史格式

已完成的一段，寫入兩筆，與內建自言自語相同：

```js
[
  { role: "user", content: "（独り言）", type: "synthetic", timestamp: Date.now() },
  { role: "assistant", content: "この宝箱、何が入っているんだろう……", type: "auto_talk", timestamp: Date.now() }
]
```

- `content` 是純對話文字，沒有表情、HTML、Story metadata。
- 不需要 `type:'story'`。
- 由 `mpu_saveChatHistory()` 存儲，遵守 40 entries 上限；不改 F5 清除政策。
- 每段**只記錄一次**（§5.4 的 `committed`）。
- **不要**加入 `mpuLLMResponseHistory`（那是限制 LLM 自身重複用的）。
- 與內建台詞的差異：內建台詞在開始顯示時寫入，Story 在完成時寫入。這是刻意的。

### 7.2 LLM 模式語義

- 抽中故事的 tick：**零次** `/nextmsg` 請求。
- 之後的 LLM 請求照原規則帶最近歷史，Story 文字可作為角色前文；**不插入額外 Story Prompt**。
- 未完成或被取消的段落不進 history，也不送 LLM。
- LLM 請求進行中時，既有 Ollama busy skip 先生效，Story 不接手。

### 7.3 Integrity 測試

`auto_talk` 不納入 checksum，但仍須端到端驗證：

- Story → 開聊天 → 送一則 chat（JSON／SSE）→ 第二輪 chat，在 integrity `block` 模式下成功。
- history 超過 40 entries 後插入 Story，checksum 無錯位。
- Story 被聊天／觸摸取代時，不留下多餘 synthetic user 或半筆 assistant。

## 8. 資源載入、啟用及資料安全

### 8.1 啟用與載入

- 只有 `/dialog` 回應帶有效 `stories`（伺服器已確認 `enabled:true` 且驗證通過）才進入 Story 決策。
- 沒有故事時零額外網路請求（資料搭既有 `/dialog`），前端只多一次 `null` 判斷。
- 不得出現空對話框、重試風暴或前台報錯。

### 8.2 安全原則

- Story 內容是資料，不是指令。`<`、`>`、`&` 在伺服器端驗證時就拒絕（§4.3），前端不需 escape。
- 表情只接受 tag，由伺服器解析成既有檔名；不可透過資料指定路徑或 URL。
- 不存個資、不存 IP、不依賴 Cookie；State 只含故事 ID、進度、時間戳。
- `stories.json` 是公開素材，勿放密鑰或管理員資訊。

## 9. 建議最小實作組件（非強制檔名）

| 範圍 | 候選位置 | 責任與限制 |
|---|---|---|
| Story 素材 | `ghost/Frieren/stories.json` | 故事、台詞、表情 tag、機率參數 |
| 讀取／驗證 | `includes/personality/personality-stories.php`（新）＋ `load_dialog()` | 讀檔、驗證、解析表情；PHPUnit |
| 前端決策／State | `js/ukagaka-story-talk.js`（新，加入 `tools/node/build.js` 的 `coreFiles`，放在 `ukagaka-core.js` 之後、`ukagaka-dialog.js` 之前或依實際依賴調整） | State 讀寫、`tryReserve`、`isLive`、`invalidate`、`play`、提交；時間與亂數可注入 |
| Auto Talk 接點 | `js/ukagaka-core.js` `mpu_nextmsg()` | 只加 §6.1 的幾行 |
| Typewriter | `js/ukagaka-base.js` `mpu_typewriter()`、`mpu_cancelTypewriter()` | run id、舊 run 延遲寫入失效、`onComplete`（§6.3） |
| 參數修正 | `js/ukagaka-core.js` `mpuDisplayBuiltInMessage()` | `options.callback` 改走 `onComplete` |
| Store | `js/ukagaka-dialog.js` `loadExternalDialog()` | 保存回應中的 `stories` |
| 頁面隱藏 | `js/ukagaka-story-talk.js` 內自己的 `visibilitychange` listener | 不改 `ukagaka-features.js` 既有 listener |
| History | 既有 `mpuChatHistory`／`mpu_saveChatHistory()` | `synthetic + auto_talk` |
| 測試 | `tests/Unit/StoryTalkLoaderTest.php`、`tools/node/test-story-talk-smoke.js`（加入 `verify`）、`tools/e2e/interaction-e2e.js` | 分層 |

### 9.1 模組 API（概念）

```text
storyTalk.isLive() -> boolean
storyTalk.invalidate(reason)
storyTalk.tryReserve(store, now, rng) -> null | reservation
storyTalk.play(reservation)
storyTalk.getStateForDebug()   [僅 debug]
```

不是公開 API 的要求；現有結構更適合時，用最小可測試入口即可。

## 10. Phase 0：現況審計（本計畫已回答大半）

本計畫已依原始碼回答：插入點（§6.1）、Typewriter 契約（§6.3）、搶佔判定（§6.4）、資料載入（§4.1）、計時器（§6.5）、頁面隱藏（§6.6）。Phase 0 只需：

- [ ] 紀錄 HEAD SHA；確認 §2 表格仍正確（尤其 `mpu_nextmsg()` Guard 順序與行號）。
- [ ] 確認 Frieren 動畫 callback（`triggerCharacterAnimation`）的最長時間，決定預備期上限是否 10 秒足夠。
- [ ] **盤點所有寫入 `#ukagaka_msg` 而不經 `mpu_typewriter()` 的地方**（已知：串流聊天 `js/ukagaka-chat-send.js`、`mpuShowSystemPlaceholder()`、`mpu_nextmsg()` 動畫 callback 中的 `.html("")`），確認每一個在寫入期間都有 §6.4 `storyCanOwnBubble()` 能檢查到的旗標；缺的列出來，由維護者決定是否補旗標。
- [ ] 確認 `auto_talk` 在實際 Provider 組裝中能成為後續聊天上下文。
- [ ] 確認 `ukagaka-story-talk.js` 在 bundle 中的位置與 `mpuChatHistory`、`mpuGetDialogStore()` 的可用時機。
- [ ] 有任何會迫使重構核心的風險：**STOP，回報維護者**。

產出：不超過一頁的摘要。

## 11. Phase 1：資料、純邏輯與 Typewriter 接點

### 工作

- [ ] `stories.json` fixture（三段，`enabled:false`）。
- [ ] PHP 讀取／驗證／表情解析，`load_dialog()` 輸出 `stories`。
- [ ] 前端 State 讀寫、7 天過期、24h 冷卻、revision 修復、站點 key。
- [ ] `tryReserve`／`isLive`／`invalidate`；時間與亂數可注入。
- [ ] Typewriter run id、舊 run 延遲寫入失效、`onComplete`；`mpuDisplayBuiltInMessage()` 參數修正。

### 必過測試

PHPUnit：

1. 檔案不存在、`enabled:false`、壞 JSON、不支援的 `format_version` → `stories: null`。
2. 含 `<`、`>`、`&` 的故事被略過，其他故事保留。
3. steps 數量、id 格式、重複 id、revision、config 界線。
4. `emotion` 解析成檔名；未知 tag → `null`，台詞保留。

JS smoke：

5. 機率 `0`／`1` 邊界；min gap 未到與冷卻中時**不消耗亂數**。
6. step 永遠 0→1→2，不越界；講完設冷卻；只有一個故事時冷卻後可重選。
7. 完成 2/3 段後過了 7 天，從 step 0 重新開始。
8. revision 改變、id 消失、壞 State → 安全 reset。
9. 不同 `mpuRestUrl` 得到不同 key。
10. `onComplete` 只在自然結束時呼叫；被新 `mpu_typewriter()` 取代或 `mpu_cancelTypewriter()` 後永不呼叫；不傳 options 的呼叫行為與回傳前一致（除了多一個回傳值）。
11. 舊 run 失效後，它已排程的 rAF／`flushUpdate()` 不寫 `innerHTML`（以假 DOM 與手動驅動的 rAF 驗證）；一般台詞的速度、批次、標籤處理與最終輸出與修改前相同。
12. `storyCanOwnBubble()`：預備期中 run id 改變、聊天／互動／blocking／頁面感知／問候旗標成立、世代改變、頁面隱藏、逾時，都回 false；打字中被取代或取消也回 false。
13. `isLive()` 在上述情況作廢 pending；打字結束後回 false。
14. 重入的 `onComplete` 只提交一次；提交順序為 State → history；State 寫入拋例外時仍寫 history、記憶體進度推進、進入降級模式。
15. 降級模式：之後不再讀 localStorage（以「讀取成功、寫入失敗」的 storage stub 驗證不會讀回舊進度）。
16. 多分頁：快照任一欄位被改（含另一分頁講完故事後 active 回到空值但 `cooldownUntil` 改變）→ 放棄提交。
17. 無 storage 權限時不崩潰。

## 12. Phase 2：與 Auto Talk 最小整合

### 工作

- [ ] `mpu_nextmsg()` 加入 §6.1 的分支；不動既有 Guard 順序。
- [ ] `loadExternalDialog()` 保存 `stories`；換角色時隨 store 替換。
- [ ] 播放沿用內建台詞的淡出、動畫、表情、Typewriter、`mpu_showmsg()` 流程。
- [ ] 頁面隱藏監聽。
- [ ] 在隔離測試啟用故事資料；未經維護者核准前 repo 中保持 `enabled:false`。

### 合併前審查檢查點

- 差異集中在 §9 列出的檔案；沒有理由的大量改動視為重做。
- Story 沒有呼叫 `startAutoTalk()`／`stopAutoTalk()`。
- 普通台詞、LLM、聊天、觸摸、安全事件的路徑行為不變。
- 不改 `messageBlocking` owner 管理、不清空 `mpuLLMResponseHistory`、不動 SSE／checksum。
- 無故事時幾乎沒有額外 CPU／網路／儲存負擔。

## 13. Phase 3：瀏覽器／WordPress E2E

沿用 `tools/e2e/interaction-e2e.js`＋假 Ollama／Playwright，不需正式站或付費 API。

### 13.1 情境矩陣

| # | 情境 | 必要證據 |
|---:|---|---|
| 1 | 內建模式，抽中一段 | 逐字顯示與表情；完成後 nextStep+1，history 恰好一對 |
| 2 | LLM 置換模式，抽中一段 | 假 LLM 請求數 **0**；台詞原文呈現 |
| 3 | LLM 模式沒抽中 | 照原邏輯送出一個 `/nextmsg` |
| 4 | 內建模式沒抽中 | Shuffle Bag／Sequential 行為不變；`#ukagaka_msgnum` 只由普通台詞改變 |
| 5 | step 0→1→2 | 跨 tick 依序，中間穿插普通台詞仍連貫 |
| 6 | 播出後 F5／SPA／原生換頁 | 進度保留，沒有重複啟動 |
| 7 | 打字中開聊天（含串流回應） | 作廢、不推進；聊天畫面不被故事殘文覆蓋 |
| 7a | **淡出／動畫預備期中開聊天** | 故事不呼叫 `mpu_typewriter()`；聊天歡迎詞不被覆蓋；不推進 |
| 7b | **淡出／動畫預備期中觸摸／裝飾** | 互動台詞不被覆蓋；不推進 |
| 7c | **淡出／動畫預備期中頁面感知／首次問候開始** | placeholder 與回應不被覆蓋；不推進 |
| 8 | 打字中觸摸／裝飾／送禮 | 互動成功；無孤立 history；自發對話之後照常 |
| 9 | 淡出／動畫／打字中切換角色 | 舊 pending 作廢；各角色進度獨立 |
| 10 | 頁面感知／首次問候搶佔 | 原互動優先，無跳段 |
| 11 | 睡眠未喚醒、Auto Talk 停用、訪客閒置 | 不播放、不推進 |
| 12 | 手動 OK 打斷進行中的故事 | 作廢、不提交；正常顯示普通台詞；之後自發對話照常 |
| 13 | startup | 不啟動故事、不消費 step |
| 14 | **後台間隔設 3 秒、故事台詞較長** | 故事播放期間的 tick 被略過；故事完整打完並提交；沒有 LLM 請求插隊；之後 tick 恢復正常 |
| 15 | **打字中直接呼叫 `mpu_cancelTypewriter()`，沒有其他流程接手** | 不提交；下一個 tick 照常產生台詞（自發對話沒有停住） |
| 16 | **打字中切到背景分頁，等打完後切回** | 不提交；計時器依既有流程恢復；之後可再講同一段 |
| 17 | 故事打字中被不經 Typewriter 的寫入者接手（串流聊天、placeholder），舊 rAF 晚到 | **不回寫舊字**、不提交、不寫入 history |
| 18 | 安全事件在故事打字中觸發 | 事件台詞顯示；故事作廢不提交 |
| 19 | 壞 JSON、`enabled:false`、storage 禁用 | 沒有 JS unhandled error；原 Auto Talk 正常 |
| 20 | 故事完成 | 24h 冷卻有效；冷卻後可再選 |
| 21 | Story → JSON chat → SSE chat | `auto_talk` 出現在上下文；checksum `block` 不拒絕 |
| 22 | history 超過 40 entries 後插入 Story | 邊界一致、不誤刪聊天 |
| 23 | 重複完成回呼 | history／nextStep 每段最多一次 |

### 13.2 回歸驗收

- `npm --prefix tools/node run verify`（含新 smoke 與 PHPUnit）。
- **另行**執行 `npm --prefix tools/node run test:interaction`（`verify` 不含 E2E）。
- 報告分開寫明 PHPUnit／JS smoke／瀏覽器 E2E 各自驗證範圍，不宣稱實站已通過。

## 14. 發布政策、回退、驗收與停止條件

### 14.1 實作版本與發布策略

本功能不等待 3.0，但也**不表示自動批准在 2.x stable 推出**。

- 以 2.x `main` 為基底的獨立 feature branch；不直接修改 stable 主線。
- repo 中的 `stories.json` 保持 `enabled:false`，直到維護者看過真實體驗並同意。
- 回退：設 `enabled:false` 或移除 `stories.json`，前端就收不到 `stories`；殘留的 Story State 被忽略。
- 不做資料庫 migration、不動加密 API Key、不改 WordPress 版本需求。

### 14.2 PR 驗收門檻

- [ ] 兩種對話模式下按規格運作，抽中故事時零 LLM 請求。
- [ ] 所有取消路徑都不提交，且自發對話不會停住。
- [ ] 淡出／動畫預備期或打字中被接手時，故事不覆蓋新內容（含舊 rAF 回寫）。
- [ ] storage 降級後本頁進度一致；多分頁提交檢查比對完整快照。
- [ ] 間隔短於播放時間時，故事不被下一個 tick 蓋掉。
- [ ] 頁面隱藏期間不提交。
- [ ] 一段只進 history 一次，checksum `block` 模式通過。
- [ ] F5 與換頁後進度保留，7 天過期／24h 冷卻正確，不同站點不互相影響。
- [ ] 既有內建／LLM／SSE／頁面感知／觸摸／送禮／睡眠／安全事件／角色切換回歸通過。
- [ ] 壞資料安全降級，台詞不可能被當成 HTML。
- [ ] 介面維持現有復古 2D／RO 風格。
- [ ] 變更僅涵蓋必要檔案，附 diff、測試結果、未解風險。

### 14.3 STOP 條件

1. 必須重構 Auto Talk 排程器或 SSE／REST 才能插入故事。
2. 無法可靠區分 Typewriter 的自然結束與取消。
3. 無法在 `block` 模式維持 checksum 一致。
4. 為避免多分頁競態，出現伺服器端帳戶／IP 追蹤或複雜鎖的設計。
5. Typewriter 修改造成一般台詞或聊天行為改變。
6. Story 需要呼叫 `startAutoTalk()`／`stopAutoTalk()` 才能運作。
7. 實測顯示故事頻率太高，破壞芙莉蓮原本悠閒的節奏。

## 15. 交付物

1. Phase 0 摘要（一頁內）。
2. 精簡 `stories.json`（一個三段故事，`enabled:false`）與最小程式改動。
3. 測試檔與 pass/fail 證據（含取消、頁面隱藏、短間隔、LLM 模式、history）。
4. PR 摘要：變更檔案、理由、未涵蓋情境、關閉與回退方式。
5. 明確說明目前只是「實驗成功」，**不可**宣稱已發布、已部署或已通過實站測試。

---

## 16. 可以直接交給 Claude／Codex 的執行提示詞

> 請閱讀 `plan/mp-ukagaka-story-talk-implementation-plan-v0.3.md`、專案 `AGENTS.md`、`CLAUDE.md` 與最新 `main` 原始碼。此案是華和梨式 Story Talk 的小型獨立功能，不是 3.0 Mesh Avatar，也不是 KIS 解譯器。**第一輪只做 Phase 0（§10），不要改程式或發 PR**。
>
> 重點確認：§2 表格是否仍與程式一致；Frieren 動畫 callback 的最長時間；所有不經 `mpu_typewriter()` 寫入 `#ukagaka_msg` 的地方與對應旗標；`ukagaka-story-talk.js` 在 bundle 中的位置。已決定的事項（Story 不碰計時器、`isLive()` 略過 tick、`storyCanOwnBubble()` 同時檢查 run id 與狀態旗標、Typewriter 加 run id＋舊 run 寫入失效＋`onComplete`、資料搭 `/dialog`、先 State 後 history、降級後記憶體為權威、快照比對）除非發現與程式不符，否則不要重新設計。
>
> 請反對過度工程化：不建新 Event Bus、Scheduler、設定面板或通用故事腳本語言。如果需要大改核心，STOP 並提出較小方案，等我決定。

## 17. 來源（供 Agent 對照）

- [華和梨／Story Talk](https://github.com/ukatech/kawari-docs/blob/main/tips/story-talk.md)
- `js/ukagaka-core.js`：`startAutoTalk()`、`mpu_checkSpamEvent()`、`mpu_nextmsg()`、`mpuDisplayBuiltInMessage()`
- `js/ukagaka-base.js`：`mpu_typewriter()`、`mpu_cancelTypewriter()`、`mpu_waitForTypewriterComplete()`、`mpu_setLocal()`、`mpuClearReloadChatSession()`、`mpu_selectNextMessage()`、`mpuDrawBuiltInIndex()`
- `js/ukagaka-dialog.js`：`loadExternalDialog()`
- `js/ukagaka-features.js`：blur／focus／`visibilitychange`
- `js/ukagaka-chat-history.js`
- `includes/rest/class-mpu-rest-dialog.php`：`load_dialog()`
- `includes/personality/personality-items.php`（人格 JSON 讀取慣例）
- `includes/llm/response-normalizer.php`：`mpu_normalize_resolve_emotion_tag()`、`mpu_normalize_get_supported_tags()`
- `includes/chat/class-mpu-chat-history-service.php`
- `includes/llm/chat-integrity.php`
- `tools/e2e/interaction-e2e.js`

**文件結論**：Story Talk 是「正常 Auto Talk 的一個可選、低頻率資料來源」，不是新的對話引擎。它不碰計時器、不經 LLM、只在訪客真的看完一段時才記帳；以既有時序、表情、歷史為中心，守住手工劇情的魅力與復古感。
