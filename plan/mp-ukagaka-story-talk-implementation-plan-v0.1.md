# MP-Ukagaka：Story Talk（連續劇情自發對話）詳細實作計畫

**版本**：Draft v0.1｜2026-11-17  
**狀態**：提案／尚未授權實作、合併、發版或部署  
**對象**：Claude Code／Codex CLI 及專案維護者  
**目標分支**：從目前 `mp-ukagaka` 2.x `main` 建立獨立功能分支（實際名稱由執行者確認）  
**原始碼審查基準**：`Horlicks-p/mp-ukagaka` `main`（本計畫編寫時）；開工前須重新記錄 HEAD SHA 與現況  
**靈感來源**：華和梨（KAWARI）[ストーリートーク](https://github.com/ukatech/kawari-docs/blob/main/tips/story-talk.md)  
**與 3.0 的關係**：**完全獨立**。Mesh Avatar 3.0 暫停中；Story Talk 不需變更 Shell、Renderer、RO 風格或人物尺寸。

---

## 0. 給 Agent 的首要指示（必讀）

本文件是**功能實驗與實作規格**，不是要求立即發布。請先閱讀專案 `AGENTS.md`、`CLAUDE.md`、相關互動流程文件、最新原始碼與測試，再盤點本計畫是否仍適用。所有「目前」的描述均以編寫當時的主線為準；若新版本已有相同機能，優先復用、縮小修改。

**產品原則**：用現代可靠性工程，保留早期伺か／RO 文化的手工台詞魅力。**不要**把它做成聊天機器人劇情平台。

嚴格界線：

1. **不准**為 Story Talk 建立 KIS/SHIORI 解譯器、腳本語言、通用任務系統、好感度系統、視覺化編輯器、抽象 Event Bus 或新的對話排程器。
2. **不准**為此全面重構 LLM、REST、SSE、聊天歷史、`MPU_STATE`、Frieren Shell、WordPress 設定頁或外掛發布流程。
3. **不准**把固定劇情送交 LLM 重新生成、翻譯或改寫；劇情內容是站長親手編寫、原文呈現的資料。
4. **不准**直接修改正式站、在 `main` 工作、合併 PR、建立 Release 或推送部署；功能驗收後由維護者單獨決策。
5. 第一版**只支援芙莉蓮 Frieren**；架構不刻意阻擋將來其他 Ghost，但不得因此做大規模通用化。
6. 保持原本的使用者操作、睡眠、頁面感知、聊天、觸摸、送禮、API Fallback、角色切換及自發對話時序。失敗時回到原有 Auto Talk，不可讓角色卡住。
7. 程式若要更動共用 Typewriter，只能做**向下相容的最小生命週期接點**，必須附測試證明既有呼叫端行為未變。

**執行方式**：先交一份極短的 Phase 0 核對報告，確認插入點與打字完成機制；得到維護者核准後，才開始核心程式修改。每個階段獨立提交、能單獨回退。

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
- 下一段必須沿著同一故事順序執行，不能跳段、重複記帳或跨角色串線。
- 換頁、F5、關閉／重開瀏覽器後可以接續未完成故事；長期不回訪則能合理重置。
- 只有實際**完成播放**的台詞才寫入 `mpuChatHistory`，且每段只記錄一次。
- 使用既有表情／Emoji、Typewriter、訊息框及動畫通道；無法解析表情時仍能顯示文字。
- 觸摸、聊天、睡眠、頁面感知、送禮、角色切換，以及原本 LLM Fallback 的行為不退化。
- 無故事檔、壞 JSON、存儲不可用、讀取失敗等情況下安全降級到既有流程。

### 1.3 本次不做

- 不做手動「下一段」按鈕，也不讓 OK 鈕直接啟動故事。
- 不做多選項、分歧、條件式任務、對話劇本程式語法、任意動作命令、音效、獎勵或故事編輯 UI。
- 不做伺服器端跨裝置／跨訪客故事同步，不使用 IP 作為故事身份。
- 不從遠端 URL 動態執行腳本，不向 LLM 送出故事內容要求它重新寫台詞。
- 不加入 3.0 Mesh Avatar 的依賴，也不更換現有復古 2D 美術。
- **Non-overlap Random** 是另一個獨立提案，**不併入本 PR**。

## 2. 現有程式核對結果（開工前再次確認）

| 元件／檔案 | 已確認的現況 | 對本功能的影響 |
|---|---|---|
| `js/ukagaka-core.js` `startAutoTalk()` | 已有單一自發對話 Timer，依聊天、互動、睡眠、使用者閒置等條件決定是否觸發 `mpu_nextmsg('auto')` | **復用此排程，不建立第二個 Timer** |
| `js/ukagaka-core.js` `mpu_nextmsg()` | `auto`／`startup`／手動觸發在一處分支；原生／LLM 路線切換前有多項 Guard | Story 選擇應在正常 `auto` Guard 全部通過之後、真正開始 LLM 請求之前 |
| `js/ukagaka-base.js` `mpu_selectNextMessage()` | `random` 會避免與當前台詞索引緊接重複，但**不是整輪不重複抽選** | Story 不改此函式；避免與另一功能混在一起 |
| `js/ukagaka-core.js` 內建自發台詞 | 已用 `synthetic` user＋`auto_talk` assistant 寫入 `mpuChatHistory` | Story 直接使用相同資料模型 |
| `includes/chat/class-mpu-chat-history-service.php` | `auto_talk`、`synthetic` 是允許的 history type；供後續 LLM 上下文使用 | **不需要新 `type:'story'`** |
| `includes/llm/chat-integrity.php` | checksum 僅篩選 `type='chat'` 的 assistant | Story 應維持 `auto_talk`，不動 checksum 規則；須回歸驗證 |
| `js/ukagaka-chat-history.js` | History 使用 `mpu_getLocal()`，raw history 保留上限為 40 entries；會在進入聊天時重新讀取 | Story 用既有 history 寫入介面，但**進度不能放在 history 裡** |
| `js/ukagaka-base.js` `mpu_setLocal()` | 固定 **24 小時 TTL** | 不能直接拿來保存跨 7 天的進度 |
| `js/ukagaka-base.js` `mpuClearReloadChatSession()` | F5 時會清除 chat history 和相關 session | Story 獨立 storage key，F5 不能刪除 |
| `js/ukagaka-base.js` `mpu_waitForTypewriterComplete()` | Timer 歸零時回呼；**取消也會歸零，超過 30 秒還會強制回呼** | **不能**直接視為「故事播放成功」證據 |
| `js/ukagaka-base.js` `mpu_typewriter()` | 第 3 參數為速度、第 4 參數為布林或 options，**目前沒有 onComplete callback** | 如需要精確完成事件，最小幅度加可選的完成／取消通知，必須向下相容 |
| `ghost/Frieren/manifest.json` | 有角色識別、腳本列表、可用表情 | 資料與資格綁定 Frieren，不要影響其他 Ghost |
| `ghost/Frieren/frieren-animation.js` | 載入 `assets.json` 已有 fetch、generation 檢查、abort 範例 | 可借鑑非同步資源競態處理，但不與動畫資產載入耦合 |
| `ghost/Frieren/frieren-interactions.js` | 觸摸／裝飾有進行中狀態與冷卻 | Story 需尊重其優先權 |
| `tools/e2e/interaction-e2e.js` | 既有 Playwright＋WordPress Playground＋假 Ollama 測試環境 | 新增少量 Story 情境，避免直接呼叫付費 LLM |
| `tools/node/package.json` | 有 `verify`、JS smoke 與 `test:interaction` | 測試分層使用，E2E 不假稱已包含在 `verify` |

### 2.1 重要澄清：什麼叫「播放完成」

不能用以下事件推進 `nextStep`：

- 成功抽中故事；
- 開始淡出前一句；
- 呼叫 `mpu_typewriter()`；
- 計時器變成 `null`（也可能是取消）；
- `mpu_waitForTypewriterComplete()` 被呼叫（逾時也會強制呼叫）。

本計畫的「完成」是：**該故事這一段的文字確實由此輪 Typewriter 正常輸出到結尾，並且在完成確認當下仍屬同一個 Ghost／同一次對話顯示所有權。**

若先前發生打字取消、另一次台詞覆蓋、聊天模式接手、切換 Ghost、頁面感知搶佔或過期的非同步回呼，必須視為**未完成**，不推進。

## 3. 方案決策（本計畫的預設值）

### 3.1 LLM 共存：採 (a)，但只插入自發對話

- 使用 LLM 置換對話時，符合抽選條件的 Auto Talk 直接播放 Story，**不進 LLM API**。
- 未抽中 Story 則走原有 LLM 路線；故事播放完畢後下一次 Auto Talk 照常選擇。
- 關閉 LLM 時同樣可抽 Story，未抽中則走原有內建台詞。
- **不要**把故事進度 prompt 化後要求 LLM 續寫；若將來需要「受故事影響但自由發揮」可另案評估，不能改變手寫故事本身。
- 只對 `trigger === 'auto'` 有效，`startup`、手動 OK／Next、睡眠喚醒、聊天及互動事件皆不消費 Story step。

### 3.2 觸發策略：10% 開始／40% 接續

所有數字為可由維護者調整的**初版測試預設值**，不是寫死在各處的 Magic Number。

| 條件 | 預設 |
|---|---|
| 無進行中故事、故事池可選 | 每次有效 Auto Talk **10%** 機率開啟一個故事 |
| 有進行中故事，且已達最小間隔 | 每次有效 Auto Talk **40%** 機率接續下一段 |
| 相鄰故事段落最小間隔 | **90 秒**（檢查當前既有 Auto Talk 間隔與感受後可調整） |
| 完整講完一個故事後的全域冷卻 | **24 小時** |
| 未完成故事的狀態過期 | 最後成功完成一步後 **7 天**；過期後重新從可用故事池選 |
| 故事總量 | 初版先 **1 個故事、3 段**；確定自然後再補其他 |
| 限制手動觸發 | 是；不可藉 OK 連點快速刷故事 |
| 節奏 | **不另外設 Timer**；只在正常 Auto Talk tick 做資格判斷 |

注：24 小時冷卻期間，不執行新的開始抽選。若同時存在多個故事，下一個應盡量避開最後完成者；**只有一個故事時仍允許冷卻結束後重播**，不可因避免重複而永久無故事可選。

### 3.3 高階優先順序

| 優先權 | 狀態／事件 | Story 對策 |
|---|---|---|
| 最優先 | 角色切換、卸載／重新載入 | Cancel pending，忽略舊世代回調 |
| 高 | 聊天、觸摸、裝飾、送禮、喚醒、Page-aware、首次問候 | 不抽、不播放、不提交進度；既有流程優先 |
| 高 | 睡眠未喚醒、auto 關閉、使用者長時間閒置 | 不抽；原來的 skip 邏輯不變 |
| 高 | LLM 請求進行中、其他訊息占用主氣泡 | 不插隊、不要打斷已開始的 LLM 回應 |
| 一般 | 正常 `auto` 觸發 | 在 LLM／內建原分支之前決定 Story／原來台詞 |
| 低 | Story 資源損壞、存儲失敗、過期 | 安全降級到原本對話 |

## 4. `stories.json` 資料契約

### 4.1 建議路徑

```text
ghost/
  Frieren/
    manifest.json
    dynamics.json
    weights.json
    stories.json              # 新增：純資料，不是 JS 腳本
    ...
```

資源基底 URL 請從現有 Ghost／Shell 的已解析路徑取得，不用硬寫 `wp-content/plugins/mp-ukagaka/`，也不能假設 WordPress 一定安裝在根目錄。實際載入管線由 Phase 0 核對後決定；**不要新建公開 REST API 只為了取一份靜態 JSON**，除非目前路由限制確有必要。

### 4.2 建議 JSON（僅示例；`enabled:false` 表示實驗開關預設關閉）

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

`enabled` 可以作為 Phase 0／Phase 1 的安全開關；功能驗收且維護者同意才打開。**第一版不新增 WordPress 後台開關**；若要提供使用者設定，應獨立評估。

### 4.3 驗證規則（在讀取後、任何顯示前）

- `format_version` 必須是支援的正整數；不支援的版本直接 fail closed，原本 Auto Talk 不中斷。
- `enabled` 必須是布林。`config` 缺欄位才使用預設，不接受 NaN、負值、超出界線的機率或極端超長時間。
- `stories` 必須是陣列，每個 ID 唯一且格式簡單（如 `^[a-z][a-z0-9_-]{0,63}$`）。
- `revision` 為正整數，用於故事文案／段落數變動時的舊狀態重置。
- 一個故事限 **2～5 個 steps**；每個 step 的 `text` 必須是非空、合理長度的純文字（建議 1～250 Unicode 字元，依現有 Typewriter 性能調整）。
- `emotion` 必須是目前 Ghost 認識的表情 tag（例如 `thinking`、`smirk`）。無效表情**略過表情，台詞仍顯示**。
- 不支援任意 HTML、JavaScript、PHP、URL、`eval` 或自訂指令；Story Text 是文字，不是程式。
- 不自動執行資料中的欄位名稱或未知屬性。可在 debug 模式提供無敏感資訊的錯誤原因。
- 第一版不加入重量、任務條件、分支或任意 nested execution；如需複雜設定，下一版另議。

### 4.4 與 Emoji／Renderer 解耦

`emotion` 是**語義標籤**，不是資源檔案路徑。沿用現有 Frieren Emoji 管道決定圖片，未來即使轉換 Shell，也可另行映射；本次不得改動 Shell。表情應與對應台詞同一個顯示輪次開始／結束，不能在聊天接手後留下舊表情。

## 5. Story State：保存位置與生命周期

### 5.1 保存位置與隔離

使用**獨立**、受同源限制的 `localStorage` key，例如 `mpu_story_state_v1_Frieren`。禁止混入 `mpu_chat_history`／`mpu_chat_session_id`；禁止使用 IP、訪客統計 ID、Cookie 或 WordPress 使用者識別資料。

既有 `mpu_setLocal()`／`mpu_getLocal()` 固定 24 小時 TTL，不能直接復用為 7 天進度。可以建立只有 Story 使用的小型讀寫函式，自行實施結構驗證、TTL 與後備策略。**這不構成新增通用 Storage Framework。**

### 5.2 建議狀態 Schema

```json
{
  "stateVersion": 1,
  "ghostId": "Frieren",
  "activeStoryId": "treasure_chest",
  "activeRevision": 1,
  "nextStep": 1,
  "lastCommittedAt": 1794900000000,
  "nextEligibleAt": 1794900090000,
  "globalCooldownUntil": 0,
  "lastCompletedStoryId": null,
  "lastCompletedAt": 0
}
```

**定義**：`nextStep` 是**下一段要播的 0-based index**；`lastCommittedAt` 只在成功完成一個步驟後更新；`nextEligibleAt` 限制接續間隔；`globalCooldownUntil` 限制整個故事池的再次啟動。

初次沒有故事時：`activeStoryId=null`，`nextStep=0`；故事全部完成後重設 active，紀錄 `lastCompletedStoryId` 並設定 24h 冷卻。

**不要在檔案裡放當前完整台詞**，以免源資料版本更換時難以判定。存 ID／revision／index 即可。

### 5.3 讀取、修復及過期規則

1. `localStorage` 不存在：初始化空狀態。
2. JSON 解析失敗、欄位無效、`ghostId` 不符、索引越界：回空狀態，不中斷普通台詞。
3. `activeStoryId` 在新 `stories.json` 中消失、`revision` 改變：清除 active，**不自行跳到另一故事第 N 段**。
4. `now-lastCommittedAt > 7 天`：未完成故事過期清除；進入一般新故事選擇流程（仍須考慮全域 cooldown）。
5. 關閉網頁、F5、普通 WordPress SPA／原生換頁：進度保留；F5 只應清除現有聊天歷史，不清除 Story State。
6. 切換 Ghost：取消前一個 Ghost 的 pending UI 工作，但**不要刪掉對方已提交進度**。切回來可接著講。
7. `localStorage` 被拒絕、配額滿、無痕環境：退回記憶體 session 狀態或停止 Story 選取，**不報 Fatal、不阻斷原有對話**；不要對訪客展示 storage 錯誤。
8. 多分頁：第一版不承諾跨分頁嚴格 exactly-once。可以用 `storage` 事件重新讀取最新提交進度，並在準備播放前再查版本；**不得為此加入伺服器端鎖或持久訪客 ID**。同時開兩分頁的重複播報屬已知 MVP 限制，測試應記錄。

### 5.4 提交順序與故障模型

- **預定／Reservation（記憶體）**：判斷符合條件後，鎖定本輪 `storyId`、`revision`、`stepIndex`、Ghost generation 和 Story playback token。**不要預先增加 `nextStep`**。
- **顯示**：使用既有主氣泡、表情與 Typewriter。
- **完成確認**：僅當 Typewriter 自然完成且 token／Ghost／互動 owner 仍一致時，向 history 寫入該段一次，並更新持久 Story State。
- **取消／被取代／超時**：丟棄 pending，歷史不記、進度不變。下一次有效 Auto Talk 才有機會重試；不因取消立即再排 Timer。
- **持久化局限**：`mpuChatHistory` 與 Story State 是兩個獨立 localStorage key，無跨 key 原子交易。MVP 以 session 內去重及提交順序最小化意外，明確測試 F5 競態；如需強一致性應另案評估，不能在此擴建資料庫。

## 6. 故事播放生命週期（本計畫最重要的一節）

### 6.1 進入點

在 `mpu_nextmsg(trigger)` 已確定 `trigger === 'auto'` 且以下 Guard 已通過後才進入 Story Decision：

- 有效 Ghost 為 Frieren，且故事資料載入完成／啟用；
- `mpuAutoTalk=true`；
- 非睡眠未喚醒、非聊天、非互動對話、非首訪問候、非頁面感知、非訊息阻擋；
- 非過期角色世代／過期工作；
- 無已進行中的 LLM 或主氣泡 owner（保留既有 busy／queue 原行為）；
- 原有自發對話流程已認定此 tick 可執行，並且沒有使用者 idle skip。

**插入位置須由 Phase 0 實際 code trace 決定**。原則是「所有原有 skip/busy 判斷之後、`mpu_hidemsg()` 及 LLM `mpuFetch()` 之前」。若現有 `stopAutoTalk()` 與 `resume` 是分散的，應明確安排故事分支的成功、取消、失敗收尾，避免 timer 未恢復或雙重恢復。

### 6.2 決策樹（偽碼，非可直接貼用的實作）

```text
existing AutoTalk tick
  -> existing guards (idle, sleep, mode, interaction, pending, busy ...)
  -> story only for trigger==='auto' and selected Ghost
      -> story disabled/not loaded/invalid  => existing AutoTalk
      -> active story not yet eligible      => existing AutoTalk
      -> active story & 40% hit             => reserve next step
      -> no active story & cooldown passed
            -> 10% hit & valid story        => reserve first step
      -> otherwise                          => existing AutoTalk
  -> on reserved step:
      -> validate generation/ownership again after each await/animation
      -> show fixed text + semantic emotion via existing UI
      -> on REAL typewriter completion only:
           append synthetic user + auto_talk assistant exactly once
           commit nextStep and timestamps
           restart existing AutoTalk at original completion boundary
      -> on preemption/cancel/failure:
           discard pending without advance or history
           safely restore/transfer ownership; do not double-start timer
```

### 6.3 完成與取消事件：**不可以直接用原有 wait helper**

目前 `mpu_typewriter(text,target,speed,options)` **沒有回傳完成 Promise，也沒有 `onComplete` 參數**；`mpu_waitForTypewriterComplete()` 透過全域 timer 輪詢，會把「取消」及「30 秒強制結束等待」混成完成。

**建議最小方案**：只在 `mpu_typewriter` 的第 4 參數 options 物件擴充**可選**的 `onComplete`／`onCancel`（或等效生命周期回報）：

- 用 per-call token 標示唯一 Typewriter 工作，正常走到 `processNextChar` 完成分支且輸出 flush 後才呼叫 `onComplete`。
- 原打字被另一則文字取代、`mpu_cancelTypewriter()` 被呼叫、Ghost 切換、Typewriter 被清空時，舊任務應 `onCancel` 且**永遠不呼叫其 `onComplete`**。
- `onComplete` 與 `onCancel` 各最多一次；不能同時觸發。
- 原本未傳遞回調的所有呼叫端**完全不改變舊行為、速度、動畫、返回值與視覺**。
- 只在明確合法的 `StoryPlayer` 需要時使用；其他模組不必逐一改接新 API。
- Typewriter 自身 `requestAnimationFrame` 的最後一幀 flush 須先於完成通知；任何未清除的舊 rAF／setTimeout 不得寫回已被下一個 owner 接手的框。

如果 Agent 提出**不修改共用 Typewriter** 的方案，必須在取消／重入／逾時測試中證明不會誤提交；不能只是看到 timer=null 就當完成。此技術問題是 Phase 0 必須決定的硬門檻。

### 6.4 對話 Owner／世代檢查

沿用 `window.mpuCharacterGeneration` 等既有世代，不新增大型 Store。Story 額外持有少量本輪 pending token 以辨識：「我預定的這一段，現在仍然擁有主氣泡嗎？」

需要重檢的節點：

- 故事 JSON fetch 結束；
- 淡出／淡入（含 jQuery animation）的 callback；
- Frieren 動畫 callback；
- Typewriter 正常完成通知；
- 持久化／歷史提交之前。

若故事已經顯示一部分，訪客突然進入聊天，**新聊天的呈現優先**；舊 Story 不該再改寫對話框，也不能推進步驟。Story 不得直接釋放其他互動持有的 `messageBlocking` owner。

### 6.5 計時器與 UI 狀態收尾

- Story 本身**沒有新 Timer**，使用原有 `startAutoTalk()`／`stopAutoTalk()`。
- 成功時在同一個原生台詞型的「打字自然完成」時機恢復 Auto Talk。
- 取消時，如果已被聊天／觸摸／頁面感知接管，恢復責任**交給其既有流程**；Story 不能擅自 `startAutoTalk()`。
- 若只是 Story 讀取失敗或資料驗證失敗而沒接管氣泡，立即回原本 Auto Talk 支線，不可因錯誤停止 Timer。
- 不要在 Story Player 另寫一份 `mpu_showmsg()`／`mpu_hidemsg()`／裝飾 click 事件的平行實作；復用既有的顯示與相容呼叫點。

## 7. 歷史、LLM 與 checksum 契約

### 7.1 歷史格式

已完成 Story 的一段，採**兩筆**與現有內建自言自語相同的訊息：

```js
[
  {
    role: "user",
    content: "（独り言）",
    type: "synthetic",
    timestamp: Date.now()
  },
  {
    role: "assistant",
    content: "この宝箱、何が入っているんだろう……",
    type: "auto_talk",
    timestamp: Date.now()
  }
]
```

- `content` 應是**純對話文字**，沒有 `emotion`、HTML span、圖片 URL、Story metadata；情緒由視覺通道處理。
- 不需要 `type:'story'`：現有後端 `MPU_Chat_History_Service::ALLOWED_MSG_TYPES` 尚未允許，新增 type 會擴大驗證範圍。
- 由現有 `mpu_saveChatHistory()` 存儲，遵守上限 40 entries；不要為了 Story 改變現有聊天歷史的 F5 清除政策。
- 每個已完成 step **只記錄一次**；同一輪重入、回調重複觸發或舊世代都不能 append。
- **不要**把手寫台詞加入 `mpuLLMResponseHistory`：那個是用於限制 LLM 自己近期重複的軌跡。

### 7.2 LLM 模式語義

- 選中故事的 tick：**零次** `/nextmsg` LLM 請求，不花模型 Token；僅記入本地對話上下文。
- 下一輪未選中故事而呼叫 LLM：現有聊天／自發對話處理按原規則收集最近歷史，Story 文字可作為角色前文（**不要額外插入 Story Prompt 指令**）。
- 如 Story 尚未完成或被取消：不把未說完台詞送給 LLM；更不要把 `nextStep` 當成先前角色已經說過的事情。
- Story 插入時若 LLM 已在進行中：讓 LLM 完成，Story 此 tick 不接手。

### 7.3 Integrity 測試

`mpu_chat_integrity_filter_messages()` 只取真實聊天 `type='chat'` assistant；Story 所用 `auto_talk` 不應納入 checksum。但**「不納入 checksum」不代表可以跳過端到端驗證**：現有 raw history window、孤立 assistant 過濾、正常化與過期回應插入都可能因追加兩筆 history 造成邊界差異。

需要至少驗證：

- Story → 開聊天 → 送一則 chat（JSON／SSE）→ 第二輪 chat，在 integrity `block` 模式下仍成功。
- 長 history（超過 40 entries）邊界插入 Story 之後 checksum 無錯位。
- Story 被 Chat／Touch 接管時，不留下多餘 synthetic user 或半筆 assistant。
- 實際聊天歷史中的 Ghost 語義不應因切換角色而被重新寫成目前 Ghost 的故事。

## 8. 資源載入、啟用及資料安全

### 8.1 啟用與載入時機

- 只有已選擇 Frieren 且 `stories.json.enabled === true` 才進入 Story 選擇器。
- Story JSON 可在 Frieren 初始化時非阻塞載入並快取；在完成之前仍由原本 Auto Talk 工作。
- **沒有任何 Story 資料可用時**不得出現空對話框、重試風暴或報錯給前台訪客。
- 將故事載入放在原生 Ghost 選擇／URL 解決的安全範圍內；不要因 `isFrierenMode` 一次性 true 就忽略同名 Ghost 的資源更新。
- 模仿 Frieren assets 載入對 `AbortController` 和 generation 的已有使用慣例，將舊角色 fetch 回應標示為 stale；必要時 abort。

### 8.2 安全原則

- 所有 Story 內容應視為**資料**，而非可執行指令。現有 Typewriter 使用 `innerHTML`，因此在向其傳入 Story 的文字前，**必須做 HTML escaping**；必要換行只允許受控 `<br>` 格式，不讓 JSON 文本任意注入 HTML／事件處理器。
- 表情欄位只有受限枚舉；不可透過 `emotion` 指定路徑或 URL。
- 不存個資、不存 IP、不依賴 cookie 或訪客追蹤外掛；Story State 僅含故事 ID、進度、時間戳。
- `stories.json` 為公開靜態素材，勿放密鑰或任何管理員資訊。

## 9. 建議最小實作組件（僅供 Agent 評估，非強制檔名）

| 範圍 | 候選位置 | 責任與限制 |
|---|---|---|
| Story 素材 | `ghost/Frieren/stories.json` | 故事、純台詞、表情、固定機率參數 |
| Story 載入／狀態／決策 | 新的小型 `js/ukagaka-story-talk.js` **或** Frieren 局部模組 | 驗證 JSON、讀／寫獨立 State、抽選與 pending token，避免過度拆檔 |
| Auto Talk 接點 | `js/ukagaka-core.js` | 只在正常 Auto Talk 分支呼叫 Story decision，不複製 LLM／內建流程 |
| Typewriter lifecycle | `js/ukagaka-base.js`（若必須） | 向下相容的可選完成／取消事件，不改既有調用 |
| 表情顯示 | 既有 `mpuDisplayDialogueEmoji()`／Frieren Emoji 資產查找 | 盡量復用，不重寫 emotion parser |
| History | 現有 `mpuChatHistory`／`mpu_saveChatHistory()` | 保持 `synthetic + auto_talk` 格式 |
| Bundle | `tools/node/build.js` 的既有 bundling 設定 | 若新增 JS 檔，確保包含於打包及 parity 檢查 |
| 測試 | `tools/node/test-story-talk-smoke.js`、`tools/e2e/interaction-e2e.js` | 純邏輯測試與實際 DOM E2E 分層 |

Agent 必須先檢查 bundle 載入順序與 Frieren 的分離腳本如何初始化，不能盲目追加一個 `window` 全域並假設任何頁面都會載入。

### 9.1 模組 API（概念）

只需要少量內部入口，例：

```text
storyTalk.loadForGhost(ghostId, assetBaseUrl, generation)
storyTalk.tryReserveAutoTalk(context) -> null | reservation
storyTalk.playReservation(reservation, uiHooks)
storyTalk.cancelPending(reason)
storyTalk.getStateForDebug() [僅 debug]
```

**不要把上面的概念介面當成新增公開 API 的要求**。如果現有 manager 更適合，使用最小可測試入口即可。

## 10. Phase 0：先完成現況審計（不得先大改）

### P0.1 代碼路徑核對

- [ ] 紀錄 HEAD SHA／版本。確認 `startAutoTalk()`→`mpu_nextmsg('auto')`→LLM／內建分支的位置。
- [ ] 調查角色切換、SPA 頁面感知、聊天切換、觸摸／送禮時誰取消 Typewriter、誰恢復 Auto Talk。
- [ ] 確認 `mpu_typewriter` 如何保證「自然完成」，其現在沒有 callback；提出最低侵入的完成／取消信號方案。
- [ ] 調查 Browser asset base URL、Frieren Shell 的非同步載入順序、角色資源更新時的 generation。
- [ ] 核對 history 40-entry window、`mpu_getLocal()` 24h TTL、F5 清除政策及 integrity block 測試路徑。
- [ ] 確認 `auto_talk` 在實際 Provider 組裝中能成為後續聊天上下文。
- [ ] 檢查既有 source 設定（`stories.json` 可獨立 fetch，還是應在人格資料 loader 中接入）。

### P0.2 產出

提交**不超過約兩頁**的審計摘要：

1. **唯一候選插入點**（函式、分支、Guard 前後位置）；
2. **Typewriter 完成／取消契約**及必要的最小修改；
3. 資料載入／打包路徑；
4. 已存在的測試、需新增的測試；
5. 任何會迫使本案重構核心的風險：若有，**STOP，回報維護者決定**。

Phase 0 不要求先產生完整架構圖、十份 ADR 或跨模組重構清單。

## 11. Phase 1：純資料、純邏輯和完成通知

目標：不接入真實 UI，也能測試純決策邏輯。

### 工作

- [ ] 加入 1 個三段 `stories.json` fixture（實驗先 `enabled:false`）。
- [ ] Story JSON schema 驗證；失敗時回傳 disabled／fallback，不擲出未捕捉例外。
- [ ] State read/write、版本升級、7 天過期、24h cooldown、revision mismatch 修復。
- [ ] 選擇器：10% start、40% continue、90 秒 min gap；以**可注入時間與 RNG**做確定性測試，不直接在所有函式硬呼叫 `Math.random()`。
- [ ] pending reservation 不持久化、僅完成後 commit；重入不得提交兩次。
- [ ] 若 P0 決定需要，為 Typewriter 增加 optional onComplete/onCancel，並測試其與原行為一致。

### 必過的純邏輯測試

1. `0%`／`100%` 邊界抽選。
2. Story step 永遠按 0→1→2 排序，不越界。
3. 完成 2/3 段後過期 7 天，重新選故事時從 step 0 開始。
4. 改變 `revision`、移除 storyId、壞 JSON／壞 state：安全 reset。
5. 24 小時冷卻有效；只有一個故事時冷卻之後可再次選中。
6. `min_gap` 沒到時不消費亂數、不新增 Timer。
7. 同一 pending 的完成回調重入仍只提交一次；取消之後完成回調無效。
8. Typewriter 自然完成與取消／被新台詞覆蓋／清空／超時可正確分辨。
9. 無 Storage 權限時不崩潰、原本 Auto Talk 仍有內容。
10. 插入無效 `<img onerror>` 等故事文字時必須當純文字顯示，不能執行 HTML。

## 12. Phase 2：與 Auto Talk 最小整合

### 工作

- [ ] 在唯一自發對話入口接 Story selector，不動 startup／manual 路徑。
- [ ] 只在 Frieren／啟用狀態下執行；切換 Ghost 後舊 pending 無效。
- [ ] 抽中時無 LLM request，未抽中按原路線工作。
- [ ] 正常播放既有主氣泡、表情、Typewriter，並遵守先前 `mpu_hidemsg`／動畫流序。
- [ ] 完成後一次性提交 history 及進度；未完成保留 `nextStep`。
- [ ] 成功、取消、失敗都能正確交還既有 Auto Talk 計時與阻擋 owner；避免兩套 Timer。
- [ ] 失敗時 fallback 不重複生成句子，不呼叫 LLM 來「救援」手寫故事內容；可回到原本 Auto Talk 分支。
- [ ] 在隔離測試啟用故事資料；正式開關依維護者決定，尚未核准前保留 disabled。

### 合併前的代碼審查檢查點

- Story 整合差異應集中於少數檔案；無理由的大量改動視為重做。
- 不得因為整合 Story 把現有 `messageBlocking` 改成單一 boolean 或移除 owner 管理。
- 不得任意清空 `mpuLLMResponseHistory`，也不得篡改 `chat/user` SSE／checksum。
- 負載性能：無故事時幾乎沒有額外 CPU／網路／儲存負擔。

## 13. Phase 3：真正瀏覽器／WordPress E2E

建議沿用現有 `tools/e2e/interaction-e2e.js`＋假 Ollama／Playwright（毋須正式站、毋須付費 API）。E2E 不需重寫成全新測試框架。

### 13.1 情境矩陣

| # | 情境 | 必要證據 |
|---:|---|---|
| 1 | **內建模式**，抽中一段 | 正確逐字顯示與表情；該段完成後 nextStep+1，history 恰好一對 |
| 2 | **LLM 置換模式**，抽中一段 | 假 LLM 請求數 **0**；台詞原文完整呈現 |
| 3 | LLM 模式沒抽中 | 依原邏輯送出一個正常 `/nextmsg`；原有聊天仍可用 |
| 4 | 內建模式沒抽中 | 舊 Random／Sequential 行為不變 |
| 5 | 故事 step 0→1→2 | 跨自發對話間隔依序，途中穿插普通台詞仍連貫 |
| 6 | Story 播出後 F5／SPA 換頁／原生換頁 | 進度保留，沒有重複啟動／重複注入元素 |
| 7 | 故事打字進行中打開聊天 | 取消，不推進；聊天畫面不被舊回調覆蓋 |
| 8 | 故事打字進行中觸摸／裝飾／送禮 | 高優先互動成功，Story 不留下孤立 history；既有 auto 恢復時序正常 |
| 9 | 故事淡出／資源讀取／打字中切換 Ghost | 舊回調不污染新 Ghost；各 Ghost 進度獨立 |
| 10 | Page-aware／首次問候搶佔 | 原互動優先，無 Story 跳段或主氣泡殘留 |
| 11 | 睡眠未喚醒、Auto Talk 停用、訪客 idle | 不播放、不推進、不新增 Timer |
| 12 | 手動 OK 與 startup | 不啟動 Story，也不消費 step |
| 13 | 壞 JSON、404、storage 禁用 | 沒有 JS unhandled error；原 Auto Talk 正常執行 |
| 14 | 角色故事已完成 | 24h cooldown 有效；過後可選下一個／同一故事 |
| 15 | Story → JSON chat → SSE chat | type=`auto_talk` 的 Story 可在上下文中出現，checksum `block` 不拒絕 |
| 16 | 超過 40-entry raw history 後插入 Story | verify/store 邊界一致、不意外清掉聊天消息 |
| 17 | Typewriter 被 `mpu_cancelTypewriter()` 取消，或舊 rAF 遲到 | 不觸發 Story 完成／不覆蓋新訊息 |
| 18 | 重複完成回調與雙觸發 | history／nextStep 每段最多一次；Auto Talk 不產生雙 Timer |

### 13.2 回歸驗收

- 執行現有 `npm --prefix tools/node run verify`（含既有 build／JS smoke／PHPUnit 等，以執行環境實際結果為準）。
- **另行**執行 `npm --prefix tools/node run test:interaction` 或針對功能的 `--only`（現有 `verify` **不包含**完整 E2E）。
- 若增加新的 JS smoke，需檢查 `verify` 是否應納入此測試，避免只寫沒跑。
- 測試至少覆蓋內建＋LLM、正常完成＋取消、F5＋角色切換、history integrity。
- 報告要分開寫明 Node 單元／JS 模擬／瀏覽器／WordPress 整合各自驗證範圍，不能宣稱沒跑過的實站已通過。

## 14. 發布政策、回退、驗收與停止條件

### 14.1 實作版本與發布策略

由於 3.0 Mesh Avatar 已暫停，本功能**不等待 3.0**；但這也**不表示自動批准在 2.x stable 推出新功能**。

- 以 2.x `main` 為基底的獨立 feature branch 實驗；不直接修改 stable 主線。
- 保留功能總開關，初次併入前可 disabled；只有維護者看過真實體驗並同意時才考慮啟用與發版。
- 完全不更改 Ghost／對話檔資料時，應仍保留舊對話。
- 回退 feature branch 或設 `enabled:false`，不應影響正常自發對話；保留的 Story State 即使存在也必須被忽略。
- 不執行資料庫 schema migration，不動加密 API Key、不改 WordPress 版本需求。

### 14.2 GA／PR 驗收門檻

- [ ] 功能在兩種對話模式下按規格運作，抽中故事時零 LLM 請求。
- [ ] 事件優先權及時間控制正確，沒有被取消仍跳段的情況。
- [ ] 一段完整播放只進 history 一次，checksum block 模式通過。
- [ ] 打字被取消或逾時不會被誤認為「自然完成」。
- [ ] 正常訪客的 Story 進度 F5 與換頁後保留，7 天過期／24h cooldown 正確。
- [ ] 既有內建／LLM／SSE／Page-aware／Touch／Give／Sleep／Ghost-switch 回歸通過。
- [ ] Story JSON 壞資料安全降級，不把 HTML 當成可執行內容。
- [ ] 介面仍呈現現有復古 2D／RO 風格，不新增不必要的 3D／Mesh 技術。
- [ ] 程式變更僅涵蓋必要檔案，附 PR diff、測試結果、未解風險。

### 14.3 STOP 條件

若遇到任一情況，停止修改並交還維護者確認：

1. 必須重構整套 Auto Talk 排程器或 SSE／REST 才能插入故事。
2. 無法可靠區分 Typewriter 的正常結束與取消，卻仍打算直接改進度。
3. 無法在 `block` 模式維持 history checksum 一致性。
4. 為了避免多分頁競態，出現伺服器端帳戶／IP 追蹤或複雜鎖的設計。
5. Typewriter 修改造成一般台詞或聊天行為改變。
6. 實測顯示故事頻率太高，破壞了芙莉蓮原本悠閒的角色節奏。

## 15. 交付物

Agent 完成後交付以下內容即可：

1. Phase 0 核對摘要（插入點／生命周期／History／資產載入）。
2. 一份精簡的 `stories.json`（一個可測試三段故事），與必要的最小程式改動。
3. 測試檔與清楚的 pass/fail 證據（含取消／歷史／LLM 模式）。
4. 一份 PR／變更摘要：變更檔案、為何需要、未涵蓋情境、關閉功能與回退方式。
5. 明確說明目前是否只是「實驗成功」，**不可**宣稱已發布、已部署或已通過實站測試。

---

## 16. 可以直接交給 Claude／Codex 的執行提示詞

> 請閱讀 `plan/mp-ukagaka-story-talk-implementation-plan-v0.1.md`（或我提供的同名文件）、專案 `AGENTS.md`、`CLAUDE.md` 與最新 `main` 原始碼。此案是華和梨式 Story Talk 的小型獨立功能，不是 3.0 Mesh Avatar，也不是 KIS 解譯器。**第一輪只做 Phase 0 Audit，不要改程式或發 PR**。
>
> 必須回答：1) 正常 Auto Talk 的唯一插入點與現有 Guard；2) `mpu_typewriter()` 如何可靠地判定自然完成／被取消，尤其 `mpu_waitForTypewriterComplete()` 的假完成風險；3) `stories.json` 可如何在 Frieren 切換期間非同步載入；4) Story 存入 `mpuChatHistory` 後與 checksum block、40-entry window 的相容性；5) 最小必要的程式檔案與測試範圍。
>
> 請反對過度工程化：不建新 Event Bus、Scheduler、設定面板或通用故事腳本語言。如果需要大改核心，STOP 並提出可選的較小方案，等我決定。

## 17. 來源（供 Agent 對照）

- [華和梨／Story Talk](https://github.com/ukatech/kawari-docs/blob/main/tips/story-talk.md)
- [mp-ukagaka 專案](https://github.com/Horlicks-p/mp-ukagaka)
- [Auto Talk／`mpu_nextmsg`：`js/ukagaka-core.js`](https://github.com/Horlicks-p/mp-ukagaka/blob/main/js/ukagaka-core.js)
- [Typewriter／Storage：`js/ukagaka-base.js`](https://github.com/Horlicks-p/mp-ukagaka/blob/main/js/ukagaka-base.js)
- [History：`js/ukagaka-chat-history.js`](https://github.com/Horlicks-p/mp-ukagaka/blob/main/js/ukagaka-chat-history.js)
- [後端 History Service](https://github.com/Horlicks-p/mp-ukagaka/blob/main/includes/chat/class-mpu-chat-history-service.php)
- [Checksum Integrity](https://github.com/Horlicks-p/mp-ukagaka/blob/main/includes/llm/chat-integrity.php)
- [Frieren Manifest](https://github.com/Horlicks-p/mp-ukagaka/blob/main/ghost/Frieren/manifest.json)
- [Frieren animation asset loader](https://github.com/Horlicks-p/mp-ukagaka/blob/main/ghost/Frieren/frieren-animation.js)
- [E2E 測試](https://github.com/Horlicks-p/mp-ukagaka/blob/main/tools/e2e/interaction-e2e.js)

**文件結論**：Story Talk 必須是「正常 Auto Talk 的一個可選、低頻率資料來源」，而不是新的對話引擎。以既有時序／表情／歷史為中心，守住手工劇情的魅力與復古感。
