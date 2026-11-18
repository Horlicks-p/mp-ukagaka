# MP-Ukagaka：Story Talk（連續劇情自發對話）詳細實作計畫

**版本**：Draft v0.4｜2026-11-18  
**狀態**：提案／尚未授權實作、合併、發版或部署  
**對象**：Claude Code／Codex CLI 及專案維護者  
**目標分支**：從目前 `mp-ukagaka` 2.x `main` 建立獨立功能分支（實際名稱由執行者確認）  
**原始碼審查基準**：`main` @ `f5e4971`（PR #52 合併後）；開工前須重新記錄 HEAD SHA 與現況  
**靈感來源**：華和梨（KAWARI）[ストーリートーク](https://github.com/ukatech/kawari-docs/blob/main/tips/story-talk.md)  
**與 3.0 的關係**：**完全獨立**。Mesh Avatar 3.0 暫停中；Story Talk 不需變更 Shell、Renderer、RO 風格或人物尺寸。

---

## v0.4 變更摘要：產品語意改為「一次觸發，整篇連續演出」

維護者改變了產品語意。v0.1～v0.3 是「一段一段分散在多次自發對話中講，進度跨頁保存」；v0.4 改成：

1. 從**故事袋（Shuffle Bag）**抽一篇。
2. 各段**連續播放**：上一段打完後，停一小段閱讀時間，接著講下一段。中間不插入一般自發對話或 LLM 台詞。
3. 播放期間，OK、❌、觸摸、裝飾、送禮等角色操作**無效**；**只有開啟聊天仍然有效**。
4. 開啟聊天、切換分頁、SPA 換頁、關閉分頁、換角色，都會**放棄這次故事**。不保存進度，下次抽到時從第一段講起。
5. 每段完整顯示後，**立刻**以 `synthetic` + `auto_talk` 寫入聊天歷史，不等整篇結束。

因此 v0.3 的下列設計全部移除：

| 移除 | 原因 |
|---|---|
| 每個故事的跨頁進度（`activeStoryId`／`nextStep`） | 不再接續 |
| 90 秒段落間隔、40% 接續機率 | 各段連續播放 |
| 7 天未完成過期、revision 修復 | 沒有未完成狀態要保存 |
| 多分頁完整快照比對、「先 State 後 history」提交順序、降級後記憶體權威 | 唯一需要保存的是故事袋，衝突的後果只是同一篇多講一次 |
| `isLive()` 略過 tick、`storyCanOwnBubble()` 的長串旗標 | 改用 message block（§6.3），自發對話與大部分操作由既有 Guard 自動擋下 |

保留：Typewriter run id、`onComplete`、舊 run 延遲寫入失效（§6.4）；資料透過 `/dialog` 載入並由 PHP 驗證（§4）；storage key 加站點範圍（§5）；LLM 置換模式下不呼叫 LLM（§3.1）。

> v0.3 及以前的變更摘要請見舊版檔案，這裡不再重複。

---

## 0. 給 Agent 的首要指示（必讀）

本文件是**功能實驗與實作規格**，不是要求立即發布。請先閱讀專案 `AGENTS.md`、`CLAUDE.md`、相關互動流程文件、最新原始碼與測試，再盤點本計畫是否仍適用。所有「目前」的描述均以編寫當時的主線為準。

**產品原則**：用現代可靠性工程，保留早期伺か／RO 文化的手工台詞魅力。**不要**把它做成聊天機器人劇情平台。

嚴格界線：

1. **不准**為 Story Talk 建立 KIS/SHIORI 解譯器、腳本語言、通用任務系統、好感度系統、視覺化編輯器、抽象 Event Bus 或新的對話排程器。
2. **不准**為此全面重構 LLM、REST、SSE、聊天歷史、`MPU_STATE`、Frieren Shell、WordPress 設定頁或外掛發布流程。
3. **不准**把固定劇情送交 LLM 重新生成、翻譯或改寫。
4. **不准**直接修改正式站、在 `main` 工作、合併 PR、建立 Release 或推送部署。
5. 第一版只為**芙莉蓮 Frieren** 撰寫故事；啟用條件是「該人格目錄有 `stories.json`」，不寫死角色名稱。
6. 保持原本的使用者操作、睡眠、頁面感知、聊天、觸摸、送禮、安全事件、API Fallback、角色切換及自發對話時序。**故事結束或中斷後，一切必須恢復原狀**，不可讓角色卡住。
7. 共用 Typewriter 只能做**向下相容的最小接點**（§6.4），必須附測試證明既有呼叫端行為未變。
8. **Story 不呼叫 `startAutoTalk()`／`stopAutoTalk()`**。計時器照常跑，故事播放期間的 tick 被 message block 擋下（§6.3）。
9. **段間停頓不讀後台「自發對話間隔」設定**（§3.3）。

**執行方式**：先交一份極短的 Phase 0 核對報告（§10）；得到維護者核准後，才開始核心程式修改。每個階段獨立提交、能單獨回退。

## 1. 目標、範圍及成功標準

### 1.1 產品定義

Story Talk 是「由作者撰寫的短篇、分段、連續演出的自發對話」。每個故事 2～5 段，偶爾在 Auto Talk 的時機被觸發，一口氣講完，讓訪客有「她一句接一句說話」的感覺。

例如「宝箱の誘惑」：

1. `この宝箱、何が入っているんだろう……`（thinking）
2. `ミミックの可能性はあるけど……`（what）
3. `でも、開けない理由にはならないよね。`（smirk）

以上為**提案示例台詞**，不是官方原作台詞轉錄；維護者可自行編修。

### 1.2 必須達成

- LLM 置換模式開啟或關閉，都能由 Auto Talk 低機率觸發；觸發時**不呼叫 LLM**，沒觸發時完整走舊流程。
- 觸發後各段依序連續播放，段間停頓固定節奏，不受後台間隔影響。
- 播放期間只有「開啟聊天」有效；其他角色操作無效。
- 任何中斷路徑都會**釋放鎖定**、恢復一般自發對話，且不保存進度。
- 每段完整顯示後恰好寫入 history 一次；沒播完的段落不寫。
- 故事袋保證：每個故事都被抽出過一次之後，才會再抽到同一個（被中斷的也算抽過）。
- 無故事檔、壞 JSON、storage 不可用時安全降級到既有流程。

### 1.3 本次不做

- 不做跨頁接續、手動「下一段」按鈕、分歧、條件式任務、腳本語法、音效、獎勵或故事編輯 UI。
- 不做伺服器端跨裝置同步，不使用 IP 或 Cookie 作為身份。
- 不修改 Non-overlap Random（PR #52）。Story 不從內建台詞的袋子抽、不寫 `#ukagaka_msgnum`。
- 不改變**頁面感知期間**觸摸／裝飾仍可點擊的既有行為（§6.3 只擋 Story 播放期間）。
- 不修正聊天歷史 key 沒有站點範圍的既有限制。

## 2. 現有程式核對結果（開工前再次確認）

| 元件／檔案 | 已確認的現況 | 對本功能的影響 |
|---|---|---|
| `js/ukagaka-core.js` `startAutoTalk()` | 單一 `setTimeout`。tick：閒置超過 60 秒跳過 → 睡眠檢查 → `mpu_checkSpamEvent()` → 沒有安全事件才 `mpu_nextmsg("auto")` → **立刻**重排 | 故事播放期間 tick 照跑，但被 block 擋下（§6.3）；安全事件檢查要另外略過（§6.6） |
| `js/ukagaka-core.js` `mpu_nextmsg()` | Guard 依序：**blocking**、聊天、互動、auto 關閉、睡眠、`stopAutoTalk()`、頁面感知、問候、Ollama busy | blocking 是第一個 Guard，且在 `stopAutoTalk()` 之前 → 被擋時計時器不會停。Story 開始的決策放在全部 Guard 之後 |
| `js/ukagaka-core.js` LLM／後備路徑的延遲 callback（約 814、840、989、1142 行） | 檢查 `mpuMessageBlocking` 後才繼續 | 故事開始取得 block 後，還沒顯示的 LLM 回應會自行放棄 |
| `js/ukagaka-core.js` `showBuiltInMessage()`（約 1018 行）及其 Frieren 動畫 callback（約 1080 行） | **只檢查角色世代（`isStaleForCharacter()`），不檢查 blocking**；放棄時呼叫 `resumeAutoTalkAfterTypewriter()` | 上一個 tick 或手動 OK 排定的內建台詞，若在故事開始後才到達，會**蓋掉故事**。必須補 Story 判斷（§6.3） |
| `js/ukagaka-base.js` `mpuAcquireMessageBlock(owner)`／`mpuReleaseMessageBlock(owner)` | owner 集合；只是設定旗標，沒有其他副作用 | Story 使用 owner `"story-talk"`，不得釋放其他 owner |
| `js/ukagaka-chat-events.js` | OK 鈕、❌（非聊天模式）檢查 `mpuMessageBlocking`；**聊天鈕 `#mpu_chat_toggle` 不檢查**，直接 `mpu_toggleChatMode()` | 正好符合「播放期間只能開聊天」 |
| `js/ukagaka-chat-mode.js` `mpu_toggleChatMode(true)` | `mpuChatGeneration += 1`、`stopAutoTalk()`、釋放 `chat-exit`、以 `mpu_typewriter()` 顯示歡迎詞 | 在這裡呼叫 `storyTalk.cancel("chat")`（§6.5）。離開聊天 5 秒後若 `mpuMessageBlocking` 仍為真就不恢復，所以 Story 的 block 一定要在開聊天時釋放 |
| `ghost/Frieren/frieren-interactions.js` | `handleDecorationClick`（約 42 行）、`handleTouchZone`（約 408 行）**只檢查自己的進行中旗標，不檢查 `mpuMessageBlocking`**；送禮（約 941 行）有檢查 | 觸摸與裝飾要加 Story 專用判斷（§6.3） |
| `js/ukagaka-features.js` `mpu_handleSpaNavigation()` | SPA 事件（含 `moelogContentSwap`）→ 1 秒後依機率呼叫 `mpu_chat_context()` | Story 自己監聽同一組事件（`window.mpuSpaEvents`），立即中斷故事；1 秒後頁面感知照常執行 |
| `js/ukagaka-context.js` `mpu_chat_context()` | 開始時只檢查聊天模式、進行中與 60 秒冷卻，**不檢查 `mpuMessageBlocking`**，然後取得 `page-context` block | 頁面感知不會被 Story 擋住，所以必須在換頁時先中斷 Story |
| `js/ukagaka-features.js` blur／focus／`visibilitychange` | 只 `stopAutoTalk()`／`startAutoTalk()`，不取消打字 | Story 自己監聽 `visibilitychange`，隱藏即中斷 |
| `js/ukagaka-base.js` `mpu_typewriter()` | 第 3 參數速度、第 4 參數布林或 options；沒有完成 callback；沒有回傳值 | 加 run id 與 `onComplete`（§6.4） |
| `js/ukagaka-base.js` `mpu_cancelTypewriter()` | 只 `clearTimeout` | 改成同時讓 run id +1 |
| `js/ukagaka-base.js` `mpu_waitForTypewriterComplete()` | 取消、逾時都當成完成 | **不能**當成「播完」證據 |
| `js/ukagaka-core.js` `mpuDisplayBuiltInMessage()` | 第 99 行把 `options.callback` 傳進 `mpu_typewriter()` 第 3 參數（速度）；目前沒有呼叫端用 | 若 Story 重用此 helper，一併修正（§6.4） |
| `js/ukagaka-chat-send.js` 串流聊天 | 用自己的計時器直接寫入對話框（約 160 行） | 開聊天時 Story 已中斷；舊 run 的延遲寫入失效（§6.4）防止殘字回寫 |
| `includes/rest/class-mpu-rest-dialog.php` `load_dialog()` | 解析 `personality_id`，產生 `msg` 與 `msg_emojis` | 在同一回應加 `stories` 欄位（§4.1） |
| `js/ukagaka-dialog.js` `loadExternalDialog()` | 有 load token 與角色世代檢查；LLM 置換模式也會載入 | 故事資料隨 dialog store 一起換 |
| `includes/llm/response-normalizer.php` | `mpu_normalize_resolve_emotion_tag()`、`mpu_normalize_get_supported_tags()` | 伺服器端把 `emotion` 解析成檔名 |
| `includes/chat/class-mpu-chat-history-service.php` | `ALLOWED_MSG_TYPES` 含 `synthetic`、`auto_talk` | 不需要新 type |
| `includes/llm/chat-integrity.php` | checksum 只取 `type='chat'` | 不動 checksum 規則；仍須回歸驗證 |

## 3. 方案決策

### 3.1 LLM 共存

- 觸發故事的 tick 直接播放固定台詞，**零次** `/nextmsg` 請求。
- 未觸發時走原有 LLM 或內建台詞路線。
- 只對 `trigger === 'auto'` 有效。`startup`、手動 OK（播放期間本來就被擋）、睡眠喚醒、聊天及互動事件都不會開始故事。

### 3.2 觸發頻率（低）

所有數字為**初版預設值**，集中在 `stories.json` 的 `config`。

| 參數 | 預設 | 說明 |
|---|---|---|
| `start_chance` | **0.05** | 每個「有效 auto tick」開始故事的機率 |
| `cooldown_minutes` | **15** | 一個故事結束（講完**或中斷**）後，至少隔多久才可能開始下一個 |

「有效 auto tick」指通過閒置、睡眠、安全事件與 `mpu_nextmsg()` 全部 Guard 的 tick。以預設 8 秒間隔估算：冷卻結束後，平均約 160 秒活躍瀏覽會開始一個故事；後台間隔改成 30 秒時大約慢 4 倍。

**中斷也計冷卻**：避免訪客關閉聊天後馬上又被同一篇故事打擾。

> 維護者若覺得太頻繁或太少，只改 `config`，不改程式。

### 3.3 段間節奏（不讀後台間隔）

- 逐字速度：沿用既有 Typewriter 速度（與其他台詞一致）。
- **段間停頓**：從「這一段打完」起算，依這一段的字數決定，讓訪客有時間讀完：

  ```text
  pause_ms = clamp(base_ms + per_char_ms × 這段字數, min_ms, max_ms)
  預設：base_ms 1200、per_char_ms 50、min_ms 1500、max_ms 4000
  ```

  例：15 字 → 1950 ms；40 字 → 3200 ms；80 字 → 4000 ms。
- **最後一段**打完後，同樣停一段 `pause_ms` 才釋放鎖定，避免最後一句一打完就被下一個 tick 蓋掉。
- 第 2 段起**不淡出對話框、不播放 Frieren 動畫**，只換表情並直接打下一句，維持「一句接一句說」的感覺。第 1 段沿用內建台詞的淡出→動畫→表情→打字→顯示流程。
- 這些參數放在 `config.step_pause`，**與後台「自發對話間隔」（3～30 秒）完全無關**。

### 3.4 故事袋（Shuffle Bag）

- 袋子裡是「這一輪還沒**抽出**的故事」。
- 開始時從袋子隨機抽一篇，**抽出就立即消耗名額**，不管之後講完還是被中斷。被中斷的故事這一輪不會再出現，等下一輪抽到時從第 1 段重講。
- 袋子空了就重新裝滿；新一輪的第一篇避開上一輪最後抽出的那篇（故事只有一篇時除外）。
- 新增或移除故事時自動調整：袋子 = 目前所有故事 − 這一輪已抽出的。
- 冷卻（§3.2）在**開始時**先設一次，結束（講完或中斷）時再從結束時間重設。這樣播放中途 F5 或關閉分頁，沒有走到收尾也已經有冷卻（從開始時間起算，§5.3 第 4 點）。

### 3.5 優先順序

| 事件 | 播放期間的結果 |
|---|---|
| 開啟聊天 | **中斷故事**、釋放鎖定；聊天照常 |
| 頁面隱藏（切分頁、最小化）、關閉分頁 | 中斷 |
| SPA 換頁 | 中斷；頁面感知 1 秒後照常 |
| 角色切換 | 中斷 |
| auto tick | 被 block 擋下（tick 本身照常重排） |
| 安全事件 | 這次 tick 不檢查，等故事結束後的 tick 再處理（§6.6） |
| OK、❌、送禮 | 被 block 擋下（既有行為） |
| 觸摸、裝飾 | 被 Story 專用判斷擋下（新增，§6.3） |
| 首次問候、Ollama 請求進行中 | 這時不會開始故事（既有 Guard） |
| 訪客閒置、睡眠時段開始 | 不影響已開始的故事；講完後由既有邏輯接手 |

## 4. `stories.json` 資料契約

### 4.1 路徑與載入

```text
ghost/
  Frieren/
    stories.json              # 新增：純資料，不是 JS 腳本
```

- 由 PHP 讀取，附在既有 `GET /dialog`（`load_dialog()`）回應的新欄位 `stories`。不新增 REST 路由，前端不另外 fetch。
- 新增 `includes/personality/personality-stories.php`（或併入既有人格模組），照 `items.json` 的讀法：`mpu_get_personalities_dir() . '/' . $personality_id . '/stories.json'`，經 `mpu_secure_file_read()` 讀取。若新增檔案，依 `mpu_load_modules()` 載入順序登記。
- 檔案不存在、`enabled` 不是 `true`、驗證失敗 → `stories` 為 `null`。
- `emotion` 在伺服器端解析成 `xxx.png`（與 `msg_emojis` 相同格式）；解析不到就是 `null`，台詞照常顯示。
- 前端在 `loadExternalDialog()` 收到回應時，`stories` 隨 dialog store 一起存放、一起替換。

回應中的 `stories` 形狀：

```json
{
  "personality": "Frieren",
  "config": {
    "start_chance": 0.05,
    "cooldown_minutes": 15,
    "step_pause": { "base_ms": 1200, "per_char_ms": 50, "min_ms": 1500, "max_ms": 4000 }
  },
  "stories": [
    { "id": "treasure_chest", "steps": [ { "text": "この宝箱、何が入っているんだろう……", "emoji": "thinking.png" } ] }
  ]
}
```

### 4.2 原始檔格式（`enabled:false` 表示預設關閉）

```json
{
  "format_version": 1,
  "enabled": false,
  "config": {
    "start_chance": 0.05,
    "cooldown_minutes": 15,
    "step_pause": { "base_ms": 1200, "per_char_ms": 50, "min_ms": 1500, "max_ms": 4000 }
  },
  "stories": [
    {
      "id": "treasure_chest",
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

- `title` 只供作者辨識，不送到前端。不需要 `revision`（沒有跨頁進度）。
- 第一版不新增 WordPress 後台開關。
- `ghost/` 隨外掛發布，外掛更新會覆蓋站點上手改的 `stories.json`。「開啟功能」等於「發一個 `enabled:true` 的版本」，由維護者決定。

### 4.3 驗證規則（PHP 端；PHPUnit 覆蓋）

- `format_version` 必須是支援的正整數；`enabled` 必須是布林 `true`。
- `config` 缺欄位用預設；`start_chance` 限 `[0, 1]`；`cooldown_minutes` 限 `[0, 1440]`；`step_pause` 各值為非負整數、`min_ms ≤ max_ms`、`max_ms ≤ 10000`；不接受 NaN 或非數字。
- `id` 唯一且符合 `^[a-z][a-z0-9_-]{0,63}$`。
- 每個故事 **2～5 段**；`text` 為非空字串，**1～120** 個 Unicode 字元（連續演出時，每段要能在對話框內一次讀完）。
- **`text` 含 `<`、`>`、`&` 任一字元 → 該故事整個略過**。Typewriter 以 `innerHTML` 輸出並把 `<...>` 當標籤，entity 會被逐字打出。
- `emotion` 只能是字串 tag，不接受路徑或 URL。
- 不支援任意 HTML、JavaScript、URL 或自訂指令；未知屬性忽略。

前端只做防禦性檢查（形狀不對就當作沒有故事）。

## 5. 故事袋的保存

### 5.1 保存位置

```text
localStorage key：mpu_story_bag_v1_<siteKey>_<personality>
```

- `siteKey`：把 `mpuRestUrl` 正規化（host 轉小寫、去掉結尾斜線）後取短雜湊（如 FNV-1a 32-bit，8 碼 hex），隔離同一 origin 下的多個 WordPress。這是**站點**識別，不是訪客識別。
- `personality`：`stories.personality`。
- 不使用 `mpu_setLocal()`（24h TTL）；Story 有自己的小型讀寫函式。**這不構成通用 Storage Framework。**

### 5.2 Schema

```json
{ "v": 1, "drawn": ["treasure_chest"], "lastDrawn": "treasure_chest", "cooldownUntil": 1794900000000 }
```

- `drawn`：這一輪已抽出的故事 ID（講完或中斷都算）。
- `lastDrawn`：最近一次抽出的故事，用來在換輪時避開。
- `cooldownUntil`：下一個故事最早可以開始的時間。
- 只存 ID 與時間戳，不存台詞、不存進度。

### 5.3 規則

1. key 不存在、JSON 壞掉、欄位不對 → 當作空袋（`drawn: []`、無冷卻）。
2. **開始一篇**：在同一個 JS 任務內同步完成以下步驟，中間不再讀 storage：

   ```text
   讀取 storage（一次）
   → 清理：drawn 中已不存在於目前故事的 ID 移除
   → 冷卻中 => 結束（不寫回、不消耗亂數）
   → 機率沒中 => 結束（不寫回）
   → 換輪：可抽 = 目前故事 − drawn；若為空，drawn = []，可抽 = 全部故事，
           故事多於一篇時排除 lastDrawn
   → 選故事
   → drawn 加入該 ID、lastDrawn = 該 ID、cooldownUntil = now + 冷卻
   → 寫回
   → 開播第 1 段
   ```

   換輪結果和抽中的故事在同一次寫回中保存，不會被重新讀取覆蓋。
3. **結束**（講完或中斷）：讀取 → 只把 `cooldownUntil` 改成「結束時間 + 冷卻」→ 寫回。`drawn` 與 `lastDrawn` 照讀到的值寫回，不修改。
4. **明確例外**：F5、關閉分頁、整頁換頁時不會執行第 3 點，保留的冷卻是「開始時間 + 冷卻」，不是「關閉時間 + 冷卻」。這是刻意的設計，不另外監聽 `pagehide`／`beforeunload` 補寫。
5. 讀寫失敗（被拒、配額滿）：本頁改用記憶體中的袋子，不報錯。重新載入後袋子可能回到舊狀態，後果只是某篇故事提早重講，可以接受。
6. 多分頁：用「重新讀取再寫回」合併；同時開兩分頁仍可能講到同一篇，屬已知限制，不加鎖。

## 6. 播放生命週期

### 6.1 進入點

在 `mpu_nextmsg(trigger)` 中，既有 Guard 全部通過之後、`mpu_hidemsg()` 與 LLM／內建分支之前：

```text
if trigger === 'auto' and storyTalk.tryStart(store, now, rng):
    return            // 故事已接手；計時器照常由外層 tick 重排
// 原有 LLM／內建流程
```

確切行號由 Phase 0 確認。不改既有 Guard 順序、不複製 LLM／內建流程。

### 6.2 主流程（偽碼）

```text
tryStart(store, now, rng):
  stories 不存在 / 形狀不對          => false
  story = 袋子 drawAndCommit(now, rng)  // §5.3 第 2 點的單一同步流程：
                                       // 冷卻中或機率沒中回 null；否則換輪、選故事、
                                       // 加入 drawn、設冷卻、寫回後回傳（抽出即消耗）
  story === null                     => false
  start(story); return true

start(story):
  session = { story, step: 0, token: 新物件, generation: mpuCharacterGeneration,
              runId: null, timers: [], historyWritten: Set() }
  mpuAcquireMessageBlock("story-talk")
  開始整體看門狗（§6.7）
  playStep(session, 0, firstStep=true)

playStep(session, i, firstStep):
  firstStep:  mpu_hidemsg(600) → 淡出後 alive 檢查 → Frieren 動畫 → 動畫 callback alive 檢查
              → 表情 → typewrite → mpu_showmsg(400)
  否則:       alive 檢查 → 表情 → typewrite（不淡出、不播放動畫）
  typewrite:  session.runId = mpu_typewriter(text, "#ukagaka_msg", null,
                                            { onComplete: () => stepDone(session, i) })
              開始這一段的看門狗（§6.7）

stepDone(session, i):
  alive 檢查失敗，或 runId 已不是目前的 => return
  若 i 尚未寫入 history：寫入 synthetic + auto_talk，mpu_saveChatHistory()（§7）
  等待 pause_ms(text)（§3.3），之後 alive 檢查：
    還有下一段 => playStep(session, i + 1, false)
    最後一段   => finish(session, "completed")

alive(session):           // 每個延遲 callback 第一件事
  session 是目前的 session
  && session.generation === mpuCharacterGeneration
  && !mpuChatModeActive
  && !document.hidden
  && mpuHasMessageBlock("story-talk")
  && （打字中）session.runId === mpuGetTypewriterRunId()
  失敗 => cancel(session, "lost-ownership")

finish(session, outcome):    // 唯一的收尾點；重複呼叫無效果
  清掉 session 的所有計時器
  目前 session 設為 null
  mpuReleaseMessageBlock("story-talk")
  從結束時間重設冷卻（§5.3 第 3 點；講完、中斷相同）

cancel(session, reason) = finish(session, reason)   // 不取消打字、不清畫面、不回收 history
```

注意：

- 內建台詞流程在動畫 callback 會先 `mpu_cancelTypewriter()`；Story 不得在自己的 alive 檢查之前呼叫它（取消也會讓 run id +1，§6.4）。
- Story 不呼叫 `mpu_selectNextMessage()`／`mpuDrawBuiltInIndex()`，不寫 `#ukagaka_msgnum`，不呼叫 `startAutoTalk()`／`stopAutoTalk()`。
- `cancel` 不清除畫面上的文字：開聊天時由聊天歡迎詞取代；切到背景時保留到下次一般台詞。

### 6.3 播放期間的鎖定

使用 `mpuAcquireMessageBlock("story-talk")`，與頁面感知的 `page-context` 是同一套機制：

| 操作 | 如何被擋 |
|---|---|
| auto tick | `mpu_nextmsg()` 第一個 Guard（在 `stopAutoTalk()` 之前，所以計時器不停） |
| OK 鈕、❌（非聊天模式）、送禮 | 既有的 `mpuMessageBlocking` 檢查 |
| 還沒顯示的 LLM／後備回應 | 既有的 `mpuMessageBlocking` 檢查，會自行放棄 |
| **已排定的內建台詞**（`showBuiltInMessage()` 與其動畫 callback） | **新增**：在既有 `isStaleForCharacter()` 檢查旁邊，加上 `mpuStoryTalkIsPlaying()` 判斷；為真就照既有放棄方式呼叫 `resumeAutoTalkAfterTypewriter()` 並 return。在動畫 callback 中，判斷必須放在 `mpu_cancelTypewriter()`、`.html("")`、顯示、寫入 history 與 `#ukagaka_msgnum` 之前。效果分兩種：**在 `showBuiltInMessage()` 開頭被擋**時還沒抽句，內建台詞袋完全不變；**在動畫 callback 被擋**時，`mpu_selectNextMessage()`（約 1055 行）已經抽出索引，這次的抽取不撤銷，防護只保證不再抽、不取消故事的打字、不顯示、不寫 history、不更新 `#ukagaka_msgnum` |
| **觸摸、裝飾** | **新增**：`handleDecorationClick`、`handleTouchZone` 開頭檢查 `mpuStoryTalkIsPlaying()`，為真就直接 return（不播放反應、不進入冷卻） |
| 聊天鈕 | **不擋**（既有行為，正好符合需求） |

內建台詞、觸摸、裝飾都**只檢查 Story**，不改成檢查 `mpuMessageBlocking`，以免改變頁面感知等其他 block 持有者期間的既有行為。若維護者之後希望這些路徑也尊重所有 block，另案處理。

### 6.4 Typewriter 最小接點

`mpu_typewriter()` 加三件事，皆向下相容：

1. **run id**：模組層級計數器，每次呼叫 `mpu_typewriter()`（含空字串清除）都 +1，並**回傳**這次的 id。`mpu_cancelTypewriter()` 也讓計數器 +1。另提供 `mpuGetTypewriterRunId()`。
2. **舊 run 的延遲寫入失效**：`flushUpdate()`、rAF callback 與 `processNextChar()` 在寫入 DOM 或排下一步前，確認自己的 run id 仍是目前的；不符就結束，不寫 `innerHTML`。這修掉「開聊天後，故事的舊 rAF 把殘字寫回串流聊天的對話框」。
3. **`options.onComplete`**：只在這次 run 自然走到結尾分支並 flush 之後呼叫，且呼叫前確認 run id 仍是目前的。最多一次。

不需要 `onCancel`：被取代或取消的 run 永遠走不到結尾分支。

同時修正 `mpuDisplayBuiltInMessage()`（`js/ukagaka-core.js:99`）把 `options.callback` 當成速度傳入的錯位；目前沒有呼叫端傳 `callback`，行為不變。

### 6.5 中斷點

| 中斷來源 | 在哪裡呼叫 `storyTalk.cancel()` |
|---|---|
| 開啟聊天 | `mpu_toggleChatMode(true)` 開頭（在 `stopAutoTalk()` 與歡迎詞之前） |
| 頁面隱藏 | Story 自己的 `visibilitychange` listener（`document.hidden` 為真）。不看 window `blur` |
| SPA 換頁 | Story 自己對 `window.mpuSpaEvents` 每個事件加 listener，立即中斷；頁面感知在 1 秒後照常執行 |
| 角色切換 | 切換流程開頭呼叫；另外 `alive()` 的世代檢查作為後備 |
| 關閉分頁、整頁換頁 | 不需處理：頁面消失，block 與計時器一起消失；故事袋沒有被改動 |

### 6.6 安全事件

`startAutoTalk()` 的 tick 在 `mpu_nextmsg()` **之前**呼叫 `mpu_checkSpamEvent()`，block 擋不到它。做法：tick 開頭若 `mpuStoryTalkIsPlaying()` 為真，**整個 tick 略過**（不檢查安全事件、不呼叫 `mpu_nextmsg()`），只重排計時器。

這是 Story 對 `startAutoTalk()` 唯一的改動，只加一個判斷，不改計時器責任。Phase 0 必須確認：`mpu_checkSpamEvent()` 的事件延後 20～60 秒處理不會遺失（例如不是「只看最近 N 秒」或「取一次就刪除」）。若會遺失，STOP 回報維護者。

### 6.7 鎖定一定會被釋放

「播放期間持有 block」的風險是：漏一個收尾路徑，OK 鈕與自發對話就會永遠被擋住。保證方式：

1. **單一收尾點**：所有結束（講完、中斷、例外）都走 `finish()`，且可重複呼叫。
2. **每段看門狗**：每次 `typewrite` 時設定 `字數 × 逐字速度 + 10 秒`；時間到還沒 `onComplete` → `cancel("watchdog")`。
3. **整體上限**：故事開始後 **90 秒**一律 `cancel("timeout")`。
4. **例外**：`playStep`／`stepDone` 以 try/catch 包住，例外時 `cancel("error")` 並記 debug log。
5. **alive 檢查失敗也呼叫 `cancel()`**，不是只 return。

## 7. 歷史與 LLM

### 7.1 歷史格式

每段**完整顯示後立刻**寫入兩筆，與內建自言自語相同：

```js
[
  { role: "user", content: "（独り言）", type: "synthetic", timestamp: Date.now() },
  { role: "assistant", content: "この宝箱、何が入っているんだろう……", type: "auto_talk", timestamp: Date.now() }
]
```

- `content` 是純對話文字，沒有表情、HTML 或 Story metadata；不需要 `type:'story'`。
- 由 `mpu_saveChatHistory()` 存儲，遵守 40 entries 上限；不改 F5 清除政策。
- 每段**只寫一次**（`session.historyWritten`）。
- **不要**加入 `mpuLLMResponseHistory`。
- 中斷時**不回收**已寫入的段落：講完前兩段後開聊天，LLM 會看到前兩段；沒播的段落不會被當成她說過的話。
- 這份記憶沿用聊天歷史的保存與清除政策，不是永久角色記憶。

### 7.2 LLM 模式語義

- 觸發故事的 tick：零次 `/nextmsg` 請求。故事播放期間的 tick 都被擋下，也是零次。
- 之後的 LLM 請求照原規則帶最近歷史；**不插入額外 Story Prompt**。

### 7.3 Integrity 測試

- Story（完整）→ 開聊天 → JSON chat → SSE chat，在 integrity `block` 模式下成功。
- Story 講到第 2 段時開聊天 → 送 chat：history 只有前兩段，checksum 通過。
- history 超過 40 entries 後插入 Story，checksum 無錯位。

## 8. 啟用與安全

- 只有 `/dialog` 回應帶有效 `stories` 才進入 Story 決策；沒有故事時零額外網路請求，前端只多一次 `null` 判斷。
- Story 內容是資料，不是指令；`<`、`>`、`&` 在伺服器端就拒絕。
- 表情只接受 tag，由伺服器解析成既有檔名。
- 不存個資、不存 IP、不依賴 Cookie；故事袋只含故事 ID 與時間戳。
- `stories.json` 是公開素材，勿放密鑰或管理員資訊。

## 9. 建議最小實作組件（非強制檔名）

| 範圍 | 候選位置 | 責任 |
|---|---|---|
| 素材 | `ghost/Frieren/stories.json` | 故事、台詞、表情 tag、參數 |
| 讀取／驗證 | `includes/personality/personality-stories.php`（新）＋ `load_dialog()` | 讀檔、驗證、解析表情；PHPUnit |
| 前端 | `js/ukagaka-story-talk.js`（新，加入 `tools/node/build.js` 的 `coreFiles`，位置依實際依賴決定） | 故事袋、`tryStart`、播放、`cancel`／`finish`、看門狗、visibility／SPA listener；時間、亂數、storage 可注入 |
| Auto Talk 接點 | `js/ukagaka-core.js` `mpu_nextmsg()`、`startAutoTalk()` tick | §6.1 的分支；§6.6 的略過判斷 |
| 聊天接點 | `js/ukagaka-chat-mode.js` `mpu_toggleChatMode()` | 開聊天時 `cancel("chat")` |
| 觸摸／裝飾 | `ghost/Frieren/frieren-interactions.js` | `mpuStoryTalkIsPlaying()` 判斷（§6.3） |
| 已排定的內建台詞 | `js/ukagaka-core.js` `showBuiltInMessage()` 與其動畫 callback | `mpuStoryTalkIsPlaying()` 判斷（§6.3） |
| 角色切換 | 切換流程開頭（Phase 0 確認位置） | `cancel("character-switch")` |
| Typewriter | `js/ukagaka-base.js` | run id、舊 run 寫入失效、`onComplete` |
| 參數修正 | `js/ukagaka-core.js` `mpuDisplayBuiltInMessage()` | `options.callback` 改走 `onComplete` |
| Store | `js/ukagaka-dialog.js` `loadExternalDialog()` | 保存 `stories` |
| 測試 | `tests/Unit/StoryTalkLoaderTest.php`、`tools/node/test-story-talk-smoke.js`（加入 `verify`）、`tools/e2e/interaction-e2e.js` | 分層 |

### 9.1 模組 API（概念）

```text
storyTalk.tryStart(store, now, rng) -> boolean
storyTalk.isPlaying() -> boolean          // 對外以 window.mpuStoryTalkIsPlaying() 提供
storyTalk.cancel(reason)
storyTalk.getStateForDebug()              [僅 debug]
```

## 10. Phase 0：現況審計

- [ ] 紀錄 HEAD SHA；確認 §2 表格仍正確（尤其 `mpu_nextmsg()` Guard 順序、內建台詞 callback 的 blocking 檢查、觸摸／裝飾 handler 位置）。
- [ ] **`mpu_checkSpamEvent()` 延後處理是否會遺失事件**（§6.6）。
- [ ] 角色切換流程的入口，決定 `cancel("character-switch")` 放在哪裡。
- [ ] 確認 `mpu_typewriter()` 第 4 參數在 options 形式下如何表示「不播放角色動畫」，第 2 段以後要用它。
- [ ] 確認 Frieren 動畫 callback 的最長時間，決定第 1 段看門狗是否需要額外加上動畫時間。
- [ ] 盤點所有不經 `mpu_typewriter()` 寫入 `#ukagaka_msg` 的地方（已知：串流聊天、`mpuShowSystemPlaceholder()`、`mpu_nextmsg()` 動畫 callback 的 `.html("")`），確認它們在故事播放期間不會執行，或執行前一定會先觸發 §6.5 的中斷。
- [ ] 確認 `auto_talk` 在實際 Provider 組裝中能成為後續聊天上下文。
- [ ] 任何會迫使重構核心的風險：**STOP，回報維護者**。

產出：不超過一頁的摘要。

## 11. Phase 1：資料、純邏輯與 Typewriter 接點

### 工作

- [ ] `stories.json` fixture（一個三段故事，`enabled:false`）。
- [ ] PHP 讀取／驗證／表情解析，`load_dialog()` 輸出 `stories`。
- [ ] 前端故事袋、`tryStart`、停頓計算、`finish`／`cancel`、看門狗；時間、亂數、storage 可注入。
- [ ] Typewriter run id、舊 run 寫入失效、`onComplete`；`mpuDisplayBuiltInMessage()` 參數修正。

### 必過測試

PHPUnit：

1. 檔案不存在、`enabled:false`、壞 JSON、不支援的 `format_version` → `stories: null`。
2. 含 `<`、`>`、`&` 的故事被略過，其他故事保留。
3. 段數（2～5）、字數（1～120）、id 格式、重複 id、`config` 與 `step_pause` 界線。
4. `emotion` 解析成檔名；未知 tag → `null`，台詞保留。

JS smoke：

5. `start_chance` 為 `0`／`1` 的邊界；冷卻中**不消耗亂數**。
6. 故事袋：**三篇故事連跑三輪，且每次抽取之間都從 storage 重新載入袋子**（模擬換頁）：每輪每篇恰好抽出一次；換輪第一篇不等於 `lastDrawn`；換輪後的 `drawn` 沒有被上一輪已滿的值覆蓋。只有一篇時可重複。
7. 抽出即消耗：`drawAndCommit()` 寫回 storage 之後才播放第 1 段；被中斷的故事這一輪不再抽到；開始時與結束時都設冷卻；結束時的寫回不改變 `drawn`／`lastDrawn`；沒有收尾時，冷卻停在「開始時間 + 冷卻」。
8. 新增／移除故事後袋子正確；壞 storage 當空袋；不同 `mpuRestUrl` 得到不同 key；storage 不可用時不崩潰。
9. 停頓計算：最小值、最大值、依字數成長；與後台間隔設定無關。
10. 各段依序播放；每段 `onComplete` 後 history 恰好多一對；重入的 `onComplete` 不重複寫。
11. 第 2 段時 `cancel()`：history 只有第 1 段；block 已釋放；之後不再播放任何一段；故事已從袋子拿出。
12. `finish()` 重複呼叫只收尾一次；看門狗、整體上限、例外都會釋放 block。
13. `onComplete` 只在自然結束時呼叫；被新的 `mpu_typewriter()` 取代或 `mpu_cancelTypewriter()` 後永不呼叫；不傳 options 的呼叫行為與修改前一致（除了多一個回傳值）。
14. 舊 run 失效後，它已排程的 rAF／`flushUpdate()` 不寫 `innerHTML`；一般台詞的速度、批次、標籤處理與最終輸出與修改前相同。
15. **普通台詞已排定 → Story 開始 → 舊 callback 到達**：以「callback 到達前」的內建台詞袋與 `#ukagaka_msgnum` 為基準，分兩種情況驗證：
    - `showBuiltInMessage()` 開頭被擋：不呼叫 `mpu_selectNextMessage()`，袋子與基準相同。
    - Frieren 動畫 callback 被擋：索引已在動畫前抽出（不要求歸還）；callback 本身不再抽取，袋子與基準相同。
    - 兩種情況都要確認：不呼叫 `mpu_cancelTypewriter()`、不寫對話框、不寫 history、`#ukagaka_msgnum` 不變，故事繼續播放。

## 12. Phase 2：整合

### 工作

- [ ] `mpu_nextmsg()` 加入 §6.1；`startAutoTalk()` tick 加入 §6.6。
- [ ] `mpu_toggleChatMode(true)`、角色切換加入 `cancel()`；Story 的 visibility 與 SPA listener。
- [ ] 觸摸、裝飾加入 §6.3 判斷。
- [ ] `showBuiltInMessage()` 開頭及其 Frieren 動畫 callback 加入 §6.3 判斷。
- [ ] `loadExternalDialog()` 保存 `stories`。
- [ ] 在隔離測試啟用故事資料；未經維護者核准前 repo 中保持 `enabled:false`。

### 合併前審查檢查點

- 差異集中在 §9 列出的檔案。
- Story 沒有呼叫 `startAutoTalk()`／`stopAutoTalk()`；只釋放自己的 `"story-talk"` owner。
- 普通台詞、LLM、聊天、頁面感知、送禮的路徑行為不變；觸摸／裝飾只在故事播放期間被擋。
- 不清空 `mpuLLMResponseHistory`、不動 SSE／checksum。

## 13. Phase 3：瀏覽器／WordPress E2E

沿用 `tools/e2e/interaction-e2e.js`＋假 Ollama／Playwright。

| # | 情境 | 必要證據 |
|---:|---|---|
| 1 | 內建模式觸發故事 | 三段依序顯示與表情；段間停頓符合 §3.3；history 恰好三對；之後一般自發對話恢復 |
| 2 | LLM 置換模式觸發故事 | 播放期間假 LLM 請求數 **0**；講完後恢復 LLM 自發對話 |
| 3 | 沒觸發 | LLM／內建行為不變；`#ukagaka_msgnum` 只由普通台詞改變 |
| 4 | **後台間隔設 3 秒** | 故事不被 tick 打斷；段間停頓與設 30 秒時相同 |
| 5 | 播放期間點 OK、❌、送禮、觸摸、裝飾 | 全部無效；故事繼續；觸摸區不進入冷卻 |
| 5a | **普通台詞淡出／動畫中 → 下一個 tick 開始故事 → 舊 callback 到達**（間隔設 3 秒並拉長動畫來重現） | 故事不被蓋掉；`#ukagaka_msgnum` 不變；history 沒有那句普通台詞 |
| 6 | 第 1 段淡出／動畫期間開聊天 | 故事不呼叫 `mpu_typewriter()`；歡迎詞不被覆蓋；block 已釋放 |
| 7 | 第 2 段打字中開聊天（含串流回應） | 中斷；聊天不被殘字覆蓋；history 只有第 1 段；關閉聊天後自發對話照常 |
| 8 | 第 2 段後開聊天問「剛剛在說什麼」 | 送出的上下文含前兩段、不含第 3 段 |
| 9 | 播放期間切到背景分頁再切回 | 中斷；切回後自發對話照常；這篇這一輪不再抽到 |
| 10 | 播放期間 SPA 換頁 | 中斷；頁面感知照常觸發 |
| 11 | 播放期間切換角色 | 中斷；新角色正常 |
| 12 | 播放期間 F5 | 重新載入後沒有任何鎖定殘留；這篇已消耗、冷卻已生效 |
| 13 | 播放期間有安全事件 | 故事講完後的 tick 顯示事件台詞，事件沒有遺失 |
| 14 | 兩個以上故事 | 全部抽過前不重複（含被中斷的）；換輪第一篇不等於上一輪最後一篇 |
| 15 | 講完／中斷後 | 冷卻期間不會開始故事 |
| 16 | 強制讓 `onComplete` 不回來（測試注入） | 看門狗釋放 block，OK 鈕與自發對話恢復 |
| 17 | 壞 JSON、`enabled:false`、storage 禁用 | 沒有 JS unhandled error；原 Auto Talk 正常 |
| 18 | Story → JSON chat → SSE chat | checksum `block` 不拒絕 |
| 19 | history 超過 40 entries 後插入 Story | 邊界一致 |

### 13.1 回歸驗收

- `npm --prefix tools/node run verify`（含新 smoke 與 PHPUnit）。
- **另行**執行 `npm --prefix tools/node run test:interaction`（`verify` 不含 E2E）。
- 報告分開寫明 PHPUnit／JS smoke／瀏覽器 E2E 各自的驗證範圍，不宣稱實站已通過。

## 14. 發布政策、回退、驗收與停止條件

### 14.1 發布策略

- 以 2.x `main` 為基底的獨立 feature branch。
- repo 中的 `stories.json` 保持 `enabled:false`，直到維護者看過實際體驗並同意。
- 回退：設 `enabled:false` 或移除 `stories.json`；殘留的故事袋 key 被忽略。
- 不做資料庫 migration、不動加密 API Key、不改 WordPress 版本需求。

### 14.2 PR 驗收門檻

- [ ] 兩種對話模式下按規格運作，故事播放期間零 LLM 請求。
- [ ] 段間節奏固定，不受後台間隔影響。
- [ ] 播放期間只有聊天鈕有效。
- [ ] 所有中斷與異常路徑都釋放 block，之後自發對話、OK 鈕、觸摸照常。
- [ ] 每段只寫入 history 一次；中斷不回收；checksum `block` 模式通過。
- [ ] 故事袋整輪不重複；抽出即消耗（含中斷）；不同站點不互相影響。
- [ ] 既有內建／LLM／SSE／頁面感知／觸摸／送禮／睡眠／安全事件／角色切換回歸通過。
- [ ] 壞資料安全降級，台詞不可能被當成 HTML。
- [ ] 變更僅涵蓋必要檔案，附 diff、測試結果、未解風險。

### 14.3 STOP 條件

1. 必須重構 Auto Talk 排程器或 SSE／REST 才能插入故事。
2. 無法可靠區分 Typewriter 的自然結束與取消。
3. 無法保證 block 在所有路徑都被釋放。
4. 安全事件延後處理會遺失（§6.6）。
5. 無法在 `block` 模式維持 checksum 一致。
6. Typewriter 修改造成一般台詞或聊天行為改變。
7. 實測顯示故事頻率太高，破壞芙莉蓮原本悠閒的節奏。

## 15. 交付物

1. Phase 0 摘要（一頁內）。
2. 精簡 `stories.json`（一個三段故事，`enabled:false`）與最小程式改動。
3. 測試檔與 pass/fail 證據（含中斷、看門狗、短間隔、LLM 模式、history）。
4. PR 摘要：變更檔案、理由、未涵蓋情境、關閉與回退方式。
5. 明確說明目前只是「實驗成功」，**不可**宣稱已發布、已部署或已通過實站測試。

---

## 16. 可以直接交給 Claude／Codex 的執行提示詞

> 請閱讀 `plan/mp-ukagaka-story-talk-implementation-plan-v0.4.md`、專案 `AGENTS.md`、`CLAUDE.md` 與最新 `main` 原始碼。此案是華和梨式 Story Talk 的小型獨立功能：一次觸發、整篇連續演出、中斷即放棄、故事之間用 Shuffle Bag 輪替。**第一輪只做 Phase 0（§10），不要改程式或發 PR**。
>
> 已決定的事項（`"story-talk"` message block 鎖定、只允許開聊天、中斷不保存進度、抽出即消耗名額、講完或中斷都計冷卻、段間停頓不讀後台間隔、每段完成即寫 history、Typewriter 加 run id＋舊 run 寫入失效＋`onComplete`、資料搭 `/dialog`）除非發現與程式不符，否則不要重新設計。
>
> 請反對過度工程化：不建新 Event Bus、Scheduler、設定面板或通用故事腳本語言。如果需要大改核心，STOP 並提出較小方案，等我決定。

## 17. 來源（供 Agent 對照）

- [華和梨／Story Talk](https://github.com/ukatech/kawari-docs/blob/main/tips/story-talk.md)
- `js/ukagaka-core.js`：`startAutoTalk()`、`mpu_checkSpamEvent()`、`mpu_nextmsg()`、`mpuDisplayBuiltInMessage()`
- `js/ukagaka-base.js`：`mpu_typewriter()`、`mpu_cancelTypewriter()`、`mpuAcquireMessageBlock()`、`mpuReleaseMessageBlock()`、`mpuHasMessageBlock()`
- `js/ukagaka-chat-mode.js`：`mpu_toggleChatMode()`
- `js/ukagaka-chat-events.js`：OK／❌／聊天鈕
- `js/ukagaka-features.js`：`visibilitychange`、`window.mpuSpaEvents`、`mpu_handleSpaNavigation()`
- `js/ukagaka-context.js`：`mpu_chat_context()`
- `ghost/Frieren/frieren-interactions.js`：`handleDecorationClick`、`handleTouchZone`、送禮
- `js/ukagaka-dialog.js`：`loadExternalDialog()`
- `includes/rest/class-mpu-rest-dialog.php`：`load_dialog()`
- `includes/personality/personality-items.php`（人格 JSON 讀取慣例）
- `includes/llm/response-normalizer.php`
- `includes/chat/class-mpu-chat-history-service.php`、`includes/llm/chat-integrity.php`
- `tools/e2e/interaction-e2e.js`

**文件結論**：Story Talk 是「偶爾發生的一小段連續獨白」。觸發時借用既有的訊息鎖定，講完或被打斷就完整歸還；不保存進度、不經 LLM，用故事袋保證每篇都輪得到。
