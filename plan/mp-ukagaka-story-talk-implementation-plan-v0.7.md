# MP-Ukagaka：Story Talk（連續劇情自發對話）詳細實作計畫

**版本**：Draft v0.7.1｜2026-11-19（v0.7 補充審查整合）  
**狀態**：提案／尚未授權實作、合併、發版或部署  
**對象**：Claude Code／Codex CLI 及專案維護者  
**目標分支**：從目前 `mp-ukagaka` 2.x `main` 建立獨立功能分支（實際名稱由執行者確認）  
**原始碼審查基準**：`main` @ `f5e4971`（PR #52 合併後；v0.5 於同一基準重新核對）；開工前須重新記錄 HEAD SHA 與現況  
**靈感來源**：華和梨（KAWARI）[ストーリートーク](https://github.com/ukatech/kawari-docs/blob/main/tips/story-talk.md)  
**與 3.0 的關係**：**完全獨立**。Mesh Avatar 3.0 暫停中；Story Talk 不需變更 Shell、Renderer、RO 風格或人物尺寸。

---

## v0.7.1 變更摘要：整合 `plan/mp-ukagaka-story-talk-v0.7-review-addendum.md`

v0.7 的產品語意與已決策事項全部保留。本版只把補充審查的 R1～R5 整合進規格；每項都已對照 `main` @ `f5e4971` 核對。

| # | 補充審查發現 | 核對結果 | 處置 | 章節 |
|---|---|---|---|---|
| R1 | `/dialog` 拒絕空對話檔，與「LLM 置換＋空內建對話檔也能觸發」矛盾 | **成立**：`load_dialog()` 對空 JSON `messages`／空 TXT 直接回 400（`class-mpu-rest-dialog.php:277,283`），成功回應在檢查之後才組裝。v0.5 的 #4 只修前端，真正的空檔到不了那個分支 | **採納方案 A**：第一版不支援真正空檔；LLM 置換模式仍完整支援（需有效內建 Fallback 對話檔）；前端空分支保留防禦性處理，但不據此宣稱支援；不放寬 REST 契約 | §4.1、§10、§11、§13（11c）、§14.3 |
| R2 | `#show_msg`、`#ukagaka_img` 可在播放中隱藏氣泡，故事仍在背後推進、寫 history | **成立**：兩者只切換 `#ukagaka_msgbox` 顯示，不檢查 block（`features.js:378,389`）。另核對：觸摸區以 capture 階段 `stopImmediatePropagation()` 擋下 `#ukagaka_img` 的切換，所以觸摸區點擊本來就不會切換氣泡 | **採納**：`#show_msg` 隱藏＝先 `cancel("hide")` 再隱藏；`#ukagaka_img` 非觸摸區點擊在播放中忽略氣泡切換；觸摸判斷必須留在 `handleTouchZone()` 內 | §2、§3.5、§6.3、§6.5、§13 |
| R3 | `mpu_extend` 的舊 fetch 晚到，以 `.empty().append()` 覆蓋故事 | **成立**：`#mpu_extend` 點擊後 fetch，`.then()` 直接寫入，沒有過期判斷，也不停止 Auto Talk。對照：`mpuChange("")` 選單請求期間會 `stopAutoTalk()`，不會在等待中開始故事，無此問題 | **採納入口取消＋落地檢查**：點擊時 `cancel("menu")`；`.then()` 寫入前若故事正在播放就丟棄結果。**不採納** Extend 專用 pending（理由見 §6.5） | §6.5、§10、§11、§13 |
| R4 | 播放中關閉 Auto Talk，故事仍繼續 | **成立**：`#toggleAutoTalk` 只呼叫 `mpuSetAutoTalkEnabled()`＋`stopAutoTalk()`（`features.js:369`） | **建議採納，待維護者確認**：OFF 時立即取消故事、不續播；`alive()` 加 `mpuAutoTalk` 作後備 | §3.5、§6.2、§6.5、§13、§14.2 |
| R5 | 第 1 段最快打字可能早於氣泡淡入完成 | **邊界定義**，非已確認的錯誤。既有內建台詞甚至在開始打字時就寫 history，Story 已較嚴格 | **採納定義**：history 在「run 自然完成＋session 有效＋氣泡不是被隱藏狀態」時寫入，淡入中算可見；不等淡入結束、不改 Typewriter 或動畫器 | §6.2、§7.1、§11、§13 |

**待維護者確認**：R4（建議 OFF 即取消）。R1 若維護者堅持支援真正空檔（方案 B），屬 REST 契約變更，另案核准。

**刻意不採納**：Extend 專用 pending（R3）、以動畫幀判斷「完整可見」（R5）、把所有氣泡顯示函式改成尊重任何 message block（R2）。理由分別寫在 §6.5、§7.1、§6.3。

---

## v0.7 變更摘要：Codex 第二輪審查（產品語意不變）

維護者已確認 §6.6 決策：**接受 Bot／AI crawler 偶發漏播報，不改後端**。v0.6 其餘內容作為 Phase 0 基準，本版只修準兩處：

| # | 問題 | v0.7 修正 | 章節 |
|---|---|---|---|
| 1 | v0.6 把 transient 型事件寫成「延後不會遺失」，但 transient 有有效期：Turnstile、Akismet 10 分鐘，Moelog Bot Blocker 30 分鐘；「全站共用 transient」也誤把查資料庫的 Bot／AI crawler 算進去 | 改為「在有效期內保留，正常故事播放延後不預期造成額外遺失」；「全站共用 transient」限定為這三類 | §6.6、§13、§14.3 |
| 2 | context pending 是單一布林（`mpuSetContextPending()`，`base.js:386`）。首次載入的 3 秒 pending 與 SPA 的 1 秒 pending 重疊時，先結束的一方會把共用旗標清掉；SPA 的 token 只防連續 SPA | pending 改為 owner 集合（`"startup"`、`"spa"`），比照 message block；任一等待存在時都不開始故事；補重疊測試 | §6.5、§9、§11、§13 |

---

## v0.6 變更摘要：Codex 審查回饋（產品語意不變）

| # | 問題 | v0.6 修正 | 章節 |
|---|---|---|---|
| 1 | Bot Alert／AI Crawler 只查最近 60 秒（`akismet-integration.php`），故事播放期間略過檢查可能漏播報，觸及 v0.5 STOP 條件 4 | 已核對；建議**接受為盡力而為的播報**（既有聊天模式、閒置、背景分頁已有同類空窗），附維護者不接受時的最小替代方案；STOP 4 改為只針對 transient 型事件 | §6.6、§14.3 |
| 2 | `cancel()` 不停打字：角色選單以 `.empty().append()` 寫入不產生新 run，故事的打字與 rAF 會蓋掉選單；SPA 中斷後會繼續打出沒記入 history 的文字 | 中斷時若目前 run 仍屬於此 session 就 `mpu_cancelTypewriter()`；保留已顯示文字、不清畫面，也不誤停已接手的聊天 | §6.2 |
| 3 | 舊 session 的 callback 可能經 `alive()` 失敗 → `finish()` 清掉新 session 並釋放共用的 `"story-talk"` owner | `finish`／`cancel`／`stepDone`／看門狗一律先核對 `session === currentSession` 且未結束；加 session 隔離原則與測試 | §6.2、§11 |
| 4 | `stepDone()` 沒有解除每段看門狗；重複 `onComplete` 只防重寫 history，仍會再排下一段 | 自然完成立即清看門狗；看門狗核對 session／段號／run id；重複 `onComplete` 讓整個 `stepDone()` 無作用 | §6.2、§6.7 |
| 5 | SPA 換頁到頁面感知決策之間有 1 秒空窗，期間 auto tick 仍可開始故事、隨即被蓋掉 | SPA handler 收到事件時設 context pending，1 秒後 callback 開頭解除（單點解除、token 防連續事件） | §6.5、§13 |
| 6 | 「播放期間只有聊天鈕有效」與 §3.5 不一致 | 統一為「OK／❌／觸摸／裝飾／送禮被擋；聊天、角色選單、角色切換可中斷」 | §1.2、§14.2 |
| 7 | §5.3「兩分頁同時播放不會發生」太絕對：不同視窗可同時可見 | 改為「跨分頁不保證嚴格不重複，接受偶發重講」 | §5.3 |

---

## v0.5 變更摘要：對照原始碼的修正（產品語意不變）

v0.4 的產品語意全部保留。v0.5 只修正與程式碼不符之處，並補上專案規範要求的交付項目：

| # | 問題 | v0.5 修正 | 章節 |
|---|---|---|---|
| 1 | auto 觸發時 `triggerFrierenSpeaking()` 只有 `forceAnimation=true`（喚醒）才呼叫 callback；照 v0.4 偽碼「等動畫 callback 再打字」，第 1 段會等到看門狗逾時 | 第 1 段改為「淡出 → 表情 → `mpu_typewriter()`（由預設 `playAnimation()` 觸發翻書）→ 顯示」，Story **不呼叫** `triggerCharacterAnimation()` | §6.2 |
| 2 | 整體上限固定 90 秒；後台打字速度最高 200 ms/字（`admin-functions.php:137`），5 段 × 120 字光打字就 120 秒 | 整體上限改為依故事內容動態計算 | §6.7 |
| 3 | 換角色後 `loadExternalDialog()` 失敗時保留舊 store（`if (!mpuGetDialogStore())`），舊角色的 `stories` 會被新角色使用 | `tryStart` 比對 `stories.personality` 與目前角色；換角色時清掉 `stories` | §4.1、§6.2 |
| 4 | `ukagaka-dialog.js` 在 `msg.length === 0` 時另建 store，沒有帶 `resp.stories`，LLM 專用站永遠不觸發 | 空對話分支也保存 `stories` | §4.1 |
| 5 | `mpuChange("")`（聊天關閉時的第 3 鈕、右鍵選單、extend 連結）不檢查 block，以 `.empty().append()` 直接改寫 `#ukagaka_msg` | 開啟角色選單或切換角色時中斷故事 | §3.5、§6.5 |
| 6 | 首次載入時頁面感知已排定（3 秒 pending），`mpu_chat_context()` 不檢查 block，故事一開始就會被蓋掉 | `tryStart` 前置條件加 `!mpuIsContextPending()` | §6.2 |
| 7 | `SCRIPT_DEBUG` 模式逐一 enqueue 檔案（`frontend-functions.php`），新模組只加 `build.js` 不夠 | 兩處都要登記 | §9 |
| 8 | 新增 console log 須遵守 Console Log i18n 規範 | 補進實作要求與驗收 | §9.2、§13.1 |
| 9 | 新 PHP 檔須過 PHPCS baseline gate、使用標準檔頭 | 補進驗收 | §9.2、§13.1 |
| 10 | `stories.json` 是角色作者用的格式，沒有文件 | 交付物加 `GHOST_CREATE_GUIDE` | §15 |

另有小幅調整：多分頁限制降級為理論限制（§5.3）、註明 history 佔比（§7.1）、明確化「共用 vs 複製」內建顯示流程的取捨（§6.2）、`mpuDisplayBuiltInMessage()` 參數錯位有兩處（§6.4）、Phase 0 清單同步更新（§10）。

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
- 播放期間 OK、❌、觸摸、裝飾、點擊角色切換氣泡、送禮被擋；開啟聊天、開啟角色選單、角色切換、extend、`#show_msg` 隱藏氣泡、關閉 Auto Talk（待確認）仍有效，並會中斷故事（§3.5）。
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
| `js/ukagaka-core.js` `showBuiltInMessage()`（約 1020 行）及其 Frieren 動畫 callback（約 1080 行） | **只檢查角色世代（`isStaleForCharacter()`），不檢查 blocking**；放棄時呼叫 `resumeAutoTalkAfterTypewriter()` | 上一個 tick 或手動 OK 排定的內建台詞，若在故事開始後才到達，會**蓋掉故事**。必須補 Story 判斷（§6.3） |
| `js/ukagaka-base.js` `mpuAcquireMessageBlock(owner)`／`mpuReleaseMessageBlock(owner)` | owner 集合；只是設定旗標，沒有其他副作用 | Story 使用 owner `"story-talk"`，不得釋放其他 owner |
| `js/ukagaka-chat-events.js` | OK 鈕、❌（非聊天模式）檢查 `mpuMessageBlocking`；**聊天鈕 `#mpu_chat_toggle` 不檢查**，聊天功能開啟時 `mpu_toggleChatMode()`，**關閉時改呼叫 `mpuChange("")`（角色選單）** | 聊天開啟時正好符合「播放期間只能開聊天」；聊天關閉時第 3 鈕是角色選單，見下一列 |
| `js/ukagaka-core.js` `mpuChange()`（約 1203 行） | 不檢查 `mpuMessageBlocking`。無參數時（選單）`stopAutoTalk()`、`mpu_hidemsg(200)`，回應後以 `.empty().append()` **直接改寫** `#ukagaka_msg`；有參數時切換角色並 `mpuCharacterGeneration += 1`（約 1277 行）。呼叫端：第 3 鈕（聊天關閉時，`chat-events.js:19`）、右鍵選單（`base.js:1534`）、extend 連結（`features.js:414`）、選單項目 | 開頭呼叫 `storyTalk.cancel()`（§6.5）；同時作為「角色切換」的中斷點 |
| `ghost/Frieren/frieren-animation.js` `triggerFrierenSpeaking()`（約 729 行） | `forceAnimation=true` 且需要喚醒時才播放喚醒動畫並呼叫 `onWakeUpComplete`；auto 觸發（`false`）時播放翻書並回傳 `false`，**不呼叫 callback**。`mpu_typewriter()` 沒有 `skipCharacterAnimation` 時會經 `playAnimation()` → `triggerCharacterAnimation()` 自行觸發 | Story 第 1 段不呼叫 `triggerCharacterAnimation()`、不等 callback；交給 `mpu_typewriter()` 的預設動畫（§6.2） |
| `js/ukagaka-features.js` 首次載入頁面感知 | 機率命中時 `mpuSetContextPending(true)`，3 秒後 `mpu_chat_context()` | 這 3 秒內不開始故事（§6.2） |
| `js/ukagaka-chat-mode.js` `mpu_toggleChatMode(true)` | `mpuChatGeneration += 1`、`stopAutoTalk()`、釋放 `chat-exit`、以 `mpu_typewriter()` 顯示歡迎詞 | 在這裡呼叫 `storyTalk.cancel("chat")`（§6.5）。離開聊天 5 秒後若 `mpuMessageBlocking` 仍為真就不恢復，所以 Story 的 block 一定要在開聊天時釋放 |
| `ghost/Frieren/frieren-interactions.js` | `handleDecorationClick`（約 42 行）、`handleTouchZone`（約 408 行）**只檢查自己的進行中旗標，不檢查 `mpuMessageBlocking`**；送禮（約 941 行）有檢查 | 觸摸與裝飾要加 Story 專用判斷（§6.3） |
| `js/ukagaka-features.js` `mpu_handleSpaNavigation()` | SPA 事件（含 `moelogContentSwap`）→ 1 秒後依機率呼叫 `mpu_chat_context()` | Story 自己監聽同一組事件（`window.mpuSpaEvents`），立即中斷故事；1 秒後頁面感知照常執行 |
| `js/ukagaka-context.js` `mpu_chat_context()` | 開始時只檢查聊天模式、進行中與 60 秒冷卻，**不檢查 `mpuMessageBlocking`**，然後取得 `page-context` block | 頁面感知不會被 Story 擋住，所以必須在換頁時先中斷 Story |
| `js/ukagaka-features.js` blur／focus／`visibilitychange` | 只 `stopAutoTalk()`／`startAutoTalk()`，不取消打字 | Story 自己監聽 `visibilitychange`，隱藏即中斷 |
| `js/ukagaka-base.js` `mpu_typewriter()` | 第 3 參數速度、第 4 參數布林或 options（`{ skipCharacterAnimation: true }` 可跳過角色動畫）；沒有完成 callback；沒有回傳值。後台速度範圍 10～200 ms/字；超過 50 字時批次輸出 | 加 run id 與 `onComplete`（§6.4）；第 2 段起用 `skipCharacterAnimation` |
| `js/ukagaka-base.js` `mpu_cancelTypewriter()` | 只 `clearTimeout` | 改成同時讓 run id +1 |
| `js/ukagaka-base.js` `mpu_waitForTypewriterComplete()` | 取消、逾時都當成完成 | **不能**當成「播完」證據 |
| `js/ukagaka-core.js` `mpuDisplayBuiltInMessage()` | 第 91 行（空字串分支）與第 99 行都把 `options.callback` 傳進 `mpu_typewriter()` 第 3 參數（速度）；目前沒有呼叫端用 | 兩處一併修正（§6.4） |
| `js/ukagaka-chat-send.js` 串流聊天 | 用自己的計時器直接寫入對話框（約 160 行） | 開聊天時 Story 已中斷；舊 run 的延遲寫入失效（§6.4）防止殘字回寫 |
| `includes/rest/class-mpu-rest-dialog.php` `load_dialog()` | 解析 `personality_id`，產生 `msg` 與 `msg_emojis`。**空 JSON `messages` 或空 TXT 直接回 400**（約 277、283 行），成功回應在這之後才組裝 | 在同一回應加 `stories` 欄位（§4.1）；第一版不放寬空檔語意（R1） |
| `js/ukagaka-features.js` `#show_msg`（約 378 行）、`#ukagaka_img`（約 389 行） | 點擊切換 `#ukagaka_msgbox` 顯示／隱藏，不檢查 block。Frieren 觸摸區 handler 以 capture 階段 `stopImmediatePropagation()` 攔截，觸摸成立時 `#ukagaka_img` 的切換不會執行 | §6.3、§6.5 |
| `js/ukagaka-features.js` `#toggleAutoTalk`（約 369 行） | 只 `mpuSetAutoTalkEnabled()` 加 `startAutoTalk()`／`stopAutoTalk()` | OFF 時中斷故事（§6.5，待確認） |
| `js/ukagaka-features.js` `#mpu_extend`（約 396 行） | 點擊後 fetch，`.then()` 以 `.empty().append(link)` 直接寫入 `#ukagaka_msg`；沒有過期判斷，也不停止 Auto Talk | 入口取消＋落地檢查（§6.5） |
| `js/ukagaka-dialog.js` `loadExternalDialog()` | 有 load token 與角色世代檢查；LLM 置換模式也會載入。正常時 `mpuSetDialogStore(resp)`；**`msg.length === 0` 時另建物件（約 103 行），不含其他欄位**；載入失敗時 `if (!mpuGetDialogStore())` 才設空 store，**否則保留舊角色的 store** | 空對話分支要保存 `stories`；換角色時要清掉舊 `stories`（§4.1） |
| `includes/core/frontend-functions.php` | `SCRIPT_DEBUG` 為真時逐一 `wp_enqueue_script()` 各模組（約 467～582 行）；否則載入 bundle | 新 JS 模組要在兩處登記（§9） |
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
| 角色切換、開啟角色選單（第 3 鈕在聊天關閉時、右鍵選單、extend 連結） | 中斷 |
| auto tick | 被 block 擋下（tick 本身照常重排） |
| 安全事件 | 這次 tick 不檢查，等故事結束後的 tick 再處理；Bot／AI crawler 等 60 秒時窗型事件可能錯過播報（§6.6） |
| SPA 換頁後 1 秒內（頁面感知決策前） | 不會開始故事（§6.5） |
| OK、❌、送禮 | 被 block 擋下（既有行為） |
| 觸摸、裝飾 | 被 Story 專用判斷擋下（新增，§6.3） |
| 首次問候、Ollama 請求進行中 | 這時不會開始故事（既有 Guard） |
| 首次載入頁面感知已排定（pending 3 秒） | 這時不會開始故事（`tryStart` 前置條件，§6.2） |
| 訪客閒置、睡眠時段開始 | 不影響已開始的故事；講完後由既有邏輯接手 |
| `#show_msg` 隱藏氣泡 | **中斷**後照常隱藏（§6.5） |
| 點擊角色本體（非觸摸區）切換氣泡 | 忽略這次切換；故事繼續（§6.3） |
| extend 連結 | 點擊時中斷；故事開始後才晚到的舊 extend 回應被丟棄（§6.5） |
| Auto Talk ON → OFF | **中斷**，重新開啟不續播（§6.5，**待維護者確認**） |

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
- 前端在 `loadExternalDialog()` 收到回應時，`stories` 隨 dialog store 一起存放、一起替換。`msg.length === 0` 分支另建的 store 也帶 `stories: resp.stories || null`，但這只是**防禦性處理**：目前 `/dialog` 對空 JSON／TXT 直接回 400（§2），真正的空對話檔不會成功回應，也就不會有 `stories`。
- **第一版範圍（R1 方案 A）**：LLM 置換模式完整支援 Story，前提是有**有效的內建對話檔**作為 Fallback（目前本來就是 `/dialog` 成功的前提）。不支援「完全沒有內建台詞」的設定，也不為此放寬 `/dialog` 的空檔錯誤語意；若維護者要求支援（方案 B），屬 REST 契約變更，另案核准。
- **換角色時不得沿用舊 `stories`**：載入失敗時現有程式會保留舊 store，所以 `mpuChange()` 切換成功（`mpuCharacterGeneration += 1`）時就把 store 的 `stories` 設為 `null`；另外 `tryStart` 比對 `stories.personality` 與目前角色（§6.2），兩道防線擇一失效仍安全。實際取得「目前角色 personality」的方式由 Phase 0 確認。

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
6. 多分頁：用「重新讀取再寫回」合併，不加鎖。被遮住的分頁會 `stopAutoTalk()`（`visibilitychange`），Story 也在隱藏時中斷，所以同一視窗內的分頁不會同時播放；但**不同視窗可能同時可見**，仍可能同時抽故事。跨分頁**不保證嚴格不重複**，接受偶發重講；不為此加鎖或跨分頁通訊。

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
  stories.personality ≠ 目前角色       => false   // §4.1，防舊角色 store 殘留
  mpuIsContextPending()              => false   // 頁面感知已排定，不浪費名額與冷卻
  story = 袋子 drawAndCommit(now, rng)  // §5.3 第 2 點的單一同步流程：
                                       // 冷卻中或機率沒中回 null；否則換輪、選故事、
                                       // 加入 drawn、設冷卻、寫回後回傳（抽出即消耗）
  story === null                     => false
  start(story); return true

start(story):
  session = { story, step: 0, token: 新物件, generation: mpuCharacterGeneration,
              runId: null, timers: [], stepWatchdog: null, finished: false,
              completedSteps: Set(), historyWritten: Set() }
  currentSession = session
  mpuAcquireMessageBlock("story-talk")
  開始整體看門狗（上限依故事計算，§6.7）
  playStep(session, 0, firstStep=true)

playStep(session, i, firstStep):
  firstStep:  mpu_hidemsg(600) → 等淡出真正結束（同 showBuiltInMessage 的
              setTimeout 400 + #ukagaka_msgbox.promise()）→ alive 檢查
              → mpu_showMsgText() → 表情 → typewrite（預設動畫）→ mpu_showmsg(400)
  否則:       alive 檢查 → 表情 → typewrite（skipCharacterAnimation，不淡出）
  typewrite:  session.runId = mpu_typewriter(text, "#ukagaka_msg", null,
                { onComplete: () => stepDone(session, i),
                  skipCharacterAnimation: !firstStep })
              session.stepWatchdog = { step: i, runId: session.runId,
                                       timer: setTimeout(onStepWatchdog(session, i, runId), step_budget(i)) }

stepDone(session, i):
  session.finished 或 session !== currentSession => return   // 過期 session：完全無作用
  i ∈ session.completedSteps                    => return   // 重複 onComplete：整個 stepDone 無作用，
                                                            // 不重寫 history、不再排下一段
  alive 檢查失敗，或 runId 已不是目前的 => return（alive 內已 cancel）
  session.completedSteps 加入 i
  立即清掉 session.stepWatchdog（自然完成）
  若 i 尚未寫入 history：寫入 synthetic + auto_talk，mpu_saveChatHistory()（§7.1 寫入判準）
  等待 pause_ms(text)（§3.3），之後 alive 檢查：
    還有下一段 => playStep(session, i + 1, false)
    最後一段   => finish(session, "completed")

onStepWatchdog(session, i, runId):
  session.finished 或 session !== currentSession => return
  session.stepWatchdog?.step !== i 或 session.stepWatchdog.runId !== runId => return
                                    // 上一段殘留的計時器不得誤殺下一段
  cancel(session, "watchdog")

alive(session):           // 每個延遲 callback 第一件事
  session.finished 或 session !== currentSession => return false（不呼叫 cancel）
  session.generation === mpuCharacterGeneration
  && !mpuChatModeActive
  && !document.hidden
  && mpuAutoTalk                         // R4 後備：OFF 入口漏接時也會中斷
  && mpuHasMessageBlock("story-talk")
  && （打字中）session.runId === mpuGetTypewriterRunId()
  失敗 => cancel(session, "lost-ownership")；回傳 false

finish(session, outcome):    // 唯一的收尾點
  session.finished          => return       // 重複呼叫無效果
  session !== currentSession => return      // 過期 session 不得動到新 session
  session.finished = true
  清掉 session 的所有計時器（含 stepWatchdog、整體上限、段間停頓）
  currentSession = null
  mpuReleaseMessageBlock("story-talk")
  從結束時間重設冷卻（§5.3 第 3 點；講完、中斷相同）

cancel(session, reason):
  session.finished 或 session !== currentSession => return
  若 session.runId === mpuGetTypewriterRunId() 且打字尚未完成：
      mpu_cancelTypewriter()       // 只停自己的 run；run id +1 讓舊 rAF／flush 失效（§6.4）
  finish(session, reason)          // 不清畫面、不回收 history
```

**session 隔離原則**：`finish()`、`cancel()`、`stepDone()`、看門狗與所有延遲 callback，第一步都核對「`session === currentSession` 且未結束」。過期 session 的 callback 不得修改目前 session、不得釋放新故事的 `"story-talk"` block、不得重設冷卻。「可重複呼叫」之外，也保證「不同 session 互不影響」。

注意：

- **第 1 段不呼叫 `triggerCharacterAnimation()`、不等動畫 callback**。auto 觸發時 `triggerFrierenSpeaking(false, cb)` 播翻書後回傳 `false`，不會呼叫 `cb`；等它只會卡到看門狗。翻書由 `mpu_typewriter()` 預設的 `playAnimation()` 觸發，非 Frieren 角色也走同一條路，不需分支。
- 內建台詞流程在動畫 callback 會先 `mpu_cancelTypewriter()`；Story 播放中不呼叫它。**唯一例外是 `cancel()`**：只有目前 Typewriter run 仍屬於這個 session 時才取消，避免誤停已經接手的聊天歡迎詞或其他流程的打字。
- **與內建顯示流程的關係**：第 1 段的「淡出 → 表情 → 打字 → 顯示」與 `showBuiltInMessage()` 的非動畫分支幾乎相同。依 CLAUDE.md「先擴充既有函式」，**優先**重用 `mpuDisplayBuiltInMessage()` 的表情＋打字部分（修好 §6.4 參數錯位後，以 `typewriterOptions` 傳 `onComplete`），Story 只自己處理淡出與 alive 檢查；若重用會改變內建台詞路徑的行為，改為在 Story 內複製這幾行並在 PR 說明理由。不得為此重構 `showBuiltInMessage()`。
- Story 不呼叫 `mpu_selectNextMessage()`／`mpuDrawBuiltInIndex()`，不寫 `#ukagaka_msgnum`，不呼叫 `startAutoTalk()`／`stopAutoTalk()`。
- `cancel` 會停止**自己的**打字（見上方偽碼），但不清除已顯示的文字：開聊天時由聊天歡迎詞取代；切到背景時保留到下次一般台詞。停止打字是必要的：角色選單以 `.empty().append()` 直接寫入、不產生新 run，若不停掉，故事的打字與 rAF 會把選單蓋掉；SPA 中斷後也會繼續打出沒記入 history 的文字。

### 6.3 播放期間的鎖定

使用 `mpuAcquireMessageBlock("story-talk")`，與頁面感知的 `page-context` 是同一套機制：

| 操作 | 如何被擋 |
|---|---|
| auto tick | `mpu_nextmsg()` 第一個 Guard（在 `stopAutoTalk()` 之前，所以計時器不停） |
| OK 鈕、❌（非聊天模式）、送禮 | 既有的 `mpuMessageBlocking` 檢查 |
| 還沒顯示的 LLM／後備回應 | 既有的 `mpuMessageBlocking` 檢查，會自行放棄 |
| **已排定的內建台詞**（`showBuiltInMessage()` 與其動畫 callback） | **新增**：在既有 `isStaleForCharacter()` 檢查旁邊，加上 `mpuStoryTalkIsPlaying()` 判斷；為真就照既有放棄方式呼叫 `resumeAutoTalkAfterTypewriter()` 並 return。在動畫 callback 中，判斷必須放在 `mpu_cancelTypewriter()`、`.html("")`、顯示、寫入 history 與 `#ukagaka_msgnum` 之前。效果分兩種：**在 `showBuiltInMessage()` 開頭被擋**時還沒抽句，內建台詞袋完全不變；**在動畫 callback 被擋**時，`mpu_selectNextMessage()`（約 1055 行）已經抽出索引，這次的抽取不撤銷，防護只保證不再抽、不取消故事的打字、不顯示、不寫 history、不更新 `#ukagaka_msgnum` |
| **觸摸、裝飾** | **新增**：`handleDecorationClick`、`handleTouchZone` 開頭檢查 `mpuStoryTalkIsPlaying()`，為真就直接 return（不播放反應、不進入冷卻）。**判斷必須放在這兩個函式內**，不能提早到 `_touchClickHandler`／裝飾 click handler 的 `stopImmediatePropagation()`／`stopPropagation()` 之前：提早 return 會讓點擊冒泡到 `#ukagaka_img`，把對話框藏起來 |
| **點擊角色本體（非觸摸區）** | **新增**：`#ukagaka_img` click handler 開頭若 `mpuStoryTalkIsPlaying()` 為真就 return，不切換氣泡。忽略而不是中斷：這個點擊通常是無意的，不應讓故事被藏起來或被打斷 |
| 聊天鈕 | **不擋**（既有行為，正好符合需求） |

內建台詞、觸摸、裝飾、角色本體點擊都**只檢查 Story**，不改成檢查 `mpuMessageBlocking`，以免改變頁面感知等其他 block 持有者期間的既有行為。若維護者之後希望這些路徑也尊重所有 block，另案處理。

### 6.4 Typewriter 最小接點

`mpu_typewriter()` 加三件事，皆向下相容：

1. **run id**：模組層級計數器，每次呼叫 `mpu_typewriter()`（含空字串清除）都 +1，並**回傳**這次的 id。`mpu_cancelTypewriter()` 也讓計數器 +1。另提供 `mpuGetTypewriterRunId()`。
2. **舊 run 的延遲寫入失效**：`flushUpdate()`、rAF callback 與 `processNextChar()` 在寫入 DOM 或排下一步前，確認自己的 run id 仍是目前的；不符就結束，不寫 `innerHTML`。這修掉「開聊天後，故事的舊 rAF 把殘字寫回串流聊天的對話框」。
3. **`options.onComplete`**：只在這次 run 自然走到結尾分支並 flush 之後呼叫，且呼叫前確認 run id 仍是目前的。最多一次。

不需要 `onCancel`：被取代或取消的 run 永遠走不到結尾分支。

同時修正 `mpuDisplayBuiltInMessage()`（`js/ukagaka-core.js:91` 空字串分支與 `:99`）把 `options.callback` 當成速度傳入的錯位，兩處都改；目前沒有呼叫端傳 `callback`，行為不變。

### 6.5 中斷點

| 中斷來源 | 在哪裡呼叫 `storyTalk.cancel()` |
|---|---|
| 開啟聊天 | `mpu_toggleChatMode(true)` 開頭（在 `stopAutoTalk()` 與歡迎詞之前） |
| 頁面隱藏 | Story 自己的 `visibilitychange` listener（`document.hidden` 為真）。不看 window `blur` |
| SPA 換頁 | Story 自己對 `window.mpuSpaEvents` 每個事件加 listener，立即中斷；頁面感知在 1 秒後照常執行。**換頁到頁面感知決策之間的 1 秒也不得開始新故事**：`mpu_handleSpaNavigation()` 收到事件時取得 pending owner `"spa"`，在 1 秒後的 callback **開頭**（機率判斷與 `mpu_chat_context()` 之前）釋放。只在單一位置釋放，不觸發、被取消或失敗都不會殘留。連續多個 SPA 事件時，以遞增 token 讓只有最後一次的 callback 能釋放。**pending 必須是 owner 集合，不能是單一布林**：目前 `mpuSetContextPending(bool)`（`base.js:386`）只設一個旗標，首次載入的 3 秒 pending（`features.js:310`）若與 SPA 的 1 秒 pending 重疊，先結束的一方會清掉另一方。做法比照 `mpuAcquireMessageBlock()`：`mpuSetContextPending(isPending, owner = "startup")` 改為操作 owner 集合，`mpuIsContextPending()` 在集合非空時為真；首次載入沿用預設 owner，呼叫端不必改。`mpuIsContextPending()` 目前只被 startup Guard 使用，SPA 期間不會有 startup，所以不影響其他流程 |
| 角色切換、開啟角色選單 | `mpuChange()` 開頭呼叫（有參數 `cancel("character-switch")`、無參數 `cancel("menu")`），涵蓋第 3 鈕（聊天關閉時）、右鍵選單、extend 連結與選單項目。選單以 `.empty().append()` 直接寫入對話框，不經 Typewriter，run id 檢查抓不到，所以必須在這裡主動中斷。另外 `alive()` 的世代檢查作為角色切換的後備 |
| extend 連結（`#mpu_extend`） | 兩個接點：**(1) 入口**：click handler 開頭 `cancel("menu")`，使用者明確操作優先。**(2) 落地檢查**：`.then()` 寫入 `#ukagaka_msg` 前，若 `mpuStoryTalkIsPlaying()` 為真就丟棄這次結果（不為了顯示過期 extend 而取消較新的故事）。這處理「extend 先發出 → 故事開始 → extend 晚到」。**不採納** Extend 專用 pending：它要在 success／error／取消各路徑清理，換來的只是在「fetch 期間剛好抽中 5% 機率」這個罕見情況下讓 extend 優先；丟棄的後果只是訪客再點一次。若 Phase 0 發現 extend 回應常態性很慢，再回報維護者 |
| `#show_msg` 隱藏氣泡 | click handler 中，只在「要隱藏」的分支先 `cancel("hide")` 再 `mpu_hidemsg(400)`；顯示分支不變。先取消再隱藏：`cancel()` 不碰顯示狀態，所以不會閃爍；已完整的前段保留 history，打字中的段落不寫 |
| Auto Talk ON → OFF（**待維護者確認**） | `#toggleAutoTalk` click handler 中，切換成 OFF 時先 `cancel("autotalk-off")`，再走既有 `mpuSetAutoTalkEnabled(false)`／`stopAutoTalk()`。重新開啟只恢復一般排程，不續播。Story 仍不自行呼叫 `startAutoTalk()`／`stopAutoTalk()`（§0 第 8 點）。`alive()` 的 `mpuAutoTalk` 檢查是後備。若維護者選擇相反語意（OFF 不影響已開始的故事），刪除這兩處並改寫 E2E 13f |
| 關閉分頁、整頁換頁 | 不需處理：頁面消失，block 與計時器一起消失；故事袋沒有被改動 |

### 6.6 安全事件

`startAutoTalk()` 的 tick 在 `mpu_nextmsg()` **之前**呼叫 `mpu_checkSpamEvent()`，block 擋不到它。做法：tick 開頭若 `mpuStoryTalkIsPlaying()` 為真，**整個 tick 略過**（不檢查安全事件、不呼叫 `mpu_nextmsg()`），只重排計時器。

這是 Story 對 `startAutoTalk()` 唯一的改動，只加一個判斷，不改計時器責任。

**v0.6 已核對：部分事件延後會遺失。** `includes/integrations/akismet-integration.php` 中，Bot Alert（`mpu_check_recent_bot_visit(60)`）與 AI Crawler（`mpu_check_recent_ai_crawler(60)`）只看最近 60 秒；Turnstile、Akismet、Moelog Bot Blocker 以 transient 保存、讀取時才刪除，**在有效期內保留**（Turnstile `turnstile-integration.php:83`、Akismet `akismet-integration.php:46` 各 10 分鐘；Moelog Bot Blocker `bot-blocker-integration.php:601` 30 分鐘），正常故事播放的延後不預期造成額外遺失；Visitor Pulse（`llm-slimstat.php` `mpu_detect_visitor_pulse_event()`）查最近 60 **分鐘**，遠長於任何故事。故事播放時間依內容與打字速度而定，可能超過 60 秒，期間發生的 Bot／AI crawler 事件可能在恢復檢查前就超出時窗。

**決策（維護者已確認，v0.7）：接受，視為播報盡力而為，不改後端。** 理由：

- 這影響的是角色的**播報台詞**，安全防護本身（擋 Bot、Turnstile、Akismet）不受影響。
- 既有程式已有相同性質的空窗：聊天模式期間 `startAutoTalk()` 直接 return、訪客閒置超過 60 秒跳過 tick、背景分頁 `stopAutoTalk()`、睡眠時段間隔延長 9 倍，這些期間都不檢查事件。Story 只是多一種同類空窗，且有 15 分鐘冷卻，佔比很小。
- Turnstile、Akismet、Moelog Bot Blocker 三類是全站共用的 transient，其他訪客的 tick 仍可能播報同一事件。Bot／AI crawler 是每次查資料庫的最近 60 秒，不屬此類。
- 「恢復後補查播放期間」需要改動後端查詢介面（傳入時間範圍），超出本案「最小改動」範圍。若維護者希望補查，另案處理。

因此 §14.3 STOP 條件 4 改寫為只針對「非時窗型事件」（transient 型）遺失。

若維護者不接受，最小替代方案是：Story 開始時記錄 `startedAt`，結束後的第一個 tick 把 `since = startedAt` 傳給 `/check-spam-event`，後端以 `max(60, now - since)` 作為時窗（上限 10 分鐘）。這需要修改 REST controller 與兩個查詢函式，須先經維護者核准。

### 6.7 鎖定一定會被釋放

「播放期間持有 block」的風險是：漏一個收尾路徑，OK 鈕與自發對話就會永遠被擋住。保證方式：

1. **單一收尾點**：所有結束（講完、中斷、例外）都走 `finish()`，可重複呼叫，且只對目前 session 有效（§6.2 session 隔離原則）。
2. **每段看門狗**：每次 `typewrite` 時設定 `step_budget(i) = 字數 × 目前 mpuTypewriterSpeed + 10 秒`（批次模式平均每字 0.7 倍逐字速度，所以這個上界仍成立）；時間到還沒 `onComplete` → `cancel("watchdog")`。**`stepDone()` 自然完成時立即清掉**該段看門狗；看門狗觸發時核對 session、段號與 run id，三者不符就無作用，避免上一段殘留的計時器誤殺下一段。
3. **整體上限**：故事開始時依內容計算一次，`total_budget = 淡出 1 秒 + Σ(step_budget(i) + pause_ms(i))`，到時一律 `cancel("timeout")`。**不得寫死固定秒數**：後台打字速度可設到 200 ms/字，5 段 × 120 字光打字就要 120 秒，固定 90 秒會誤殺正常故事。
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
- **佔比提醒**：一篇 N 段的故事寫入 2N 筆，5 段就佔上限的 1/4，可能把較早的真實聊天擠出上下文。初版 Frieren 故事建議 2～3 段；若維護者在實測中覺得擠壓太多，再考慮把 `steps` 上限從 5 降到 3（改 §4.3 驗證即可）。
- 每段**只寫一次**（`session.historyWritten`）。
- **寫入判準（R5）**：該段 run 自然完成（`onComplete`）、session 仍有效（`alive()`）、對話框不是被使用者隱藏的狀態（`#show_msg` 隱藏會先 `cancel()`，所以 session 有效即代表沒被隱藏）。第 1 段若打字比 `mpu_showmsg(400)` 淡入更快完成，淡入中**算可見**，照常寫入；不等淡入結束，不以動畫幀判斷使用者是否真的讀到。段間停頓最短 1500 ms，長於淡入 400 ms，下一段開始時氣泡必定已完全顯示。淡入中被中斷時，依中斷時間點：`onComplete` 之前中斷 → 不寫；之後 → 已寫入，不回收（與一般中斷相同）。
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
| 前端 | `js/ukagaka-story-talk.js`（新） | 故事袋、`tryStart`、播放、`cancel`／`finish`、看門狗、visibility／SPA listener；時間、亂數、storage 可注入 |
| SPA pending | `js/ukagaka-features.js` `mpu_handleSpaNavigation()` | 收到事件取得 pending owner `"spa"`，1 秒後 callback 開頭釋放（§6.5） |
| Pending owner 集合 | `js/ukagaka-base.js` `mpuSetContextPending()`／`mpuIsContextPending()` | 單一布林改為 owner 集合，預設 owner `"startup"` 向下相容（§6.5） |
| 載入登記 | `tools/node/build.js` 的 `coreFiles` **與** `includes/core/frontend-functions.php` 的 `SCRIPT_DEBUG` 逐檔 enqueue 清單 | 兩處順序與依賴一致（需在 `ukagaka-base.js` 之後、呼叫端 `ukagaka-core.js` 執行到 `mpu_nextmsg()` 之前可用；以 `typeof` 防護或放在 core 之前，由 Phase 0 決定）。只登記 `build.js` 會讓開發模式缺模組 |
| 角色選單 | `js/ukagaka-core.js` `mpuChange()` 開頭 | `cancel("menu")`／`cancel("character-switch")`；切換成功時清掉 store 的 `stories`（§4.1） |
| Console log i18n | `includes/core/frontend-functions.php`（`$log_i18n->debug()`）、`languages/` | 新 log 的註冊與翻譯（§9.2） |
| 文件 | `docs-en/GHOST_CREATE_GUIDE.md` | `stories.json` 格式、驗證規則、`enabled` 意義（§15） |
| Auto Talk 接點 | `js/ukagaka-core.js` `mpu_nextmsg()`、`startAutoTalk()` tick | §6.1 的分支；§6.6 的略過判斷 |
| 聊天接點 | `js/ukagaka-chat-mode.js` `mpu_toggleChatMode()` | 開聊天時 `cancel("chat")` |
| 觸摸／裝飾 | `ghost/Frieren/frieren-interactions.js` | `mpuStoryTalkIsPlaying()` 判斷（§6.3） |
| 已排定的內建台詞 | `js/ukagaka-core.js` `showBuiltInMessage()` 與其動畫 callback | `mpuStoryTalkIsPlaying()` 判斷（§6.3） |
| Typewriter | `js/ukagaka-base.js` | run id、舊 run 寫入失效、`onComplete` |
| 參數修正 | `js/ukagaka-core.js` `mpuDisplayBuiltInMessage()` | `options.callback` 改走 `onComplete` |
| Store | `js/ukagaka-dialog.js` `loadExternalDialog()` | 保存 `stories`（含 `msg.length === 0` 分支） |
| 測試 | `tests/Unit/StoryTalkLoaderTest.php`、`tools/node/test-story-talk-smoke.js`（加入 `verify`）、`tools/e2e/interaction-e2e.js` | 分層 |

### 9.1 模組 API（概念）

```text
storyTalk.tryStart(store, now, rng) -> boolean
storyTalk.isPlaying() -> boolean          // 對外以 window.mpuStoryTalkIsPlaying() 提供
storyTalk.cancel(reason)
storyTalk.getStateForDebug()              [僅 debug]
```

### 9.2 專案規範（實作時必須遵守）

- **Console log**：一律用 `mpuLogger` sugar（純字串 `logL`／`warnL`，有參數 `logF`／`warnF`），不直接用 `console.*`。每個新 key 在同一個變更中以 `$log_i18n->debug(key, __('日文 msgid', 'mp-ukagaka'))` 註冊；JS fallback 字串必須與 PHP msgid **逐字相同**；不得與既有 key 重複或跨 always／debug bucket（builder 會在 enqueue 時 throw）。新 msgid 加入 `.pot` 與各 `.po`（zh_TW 實譯），以 `python3 languages/compile_po.py` 重編 `.mo`（不要用 `translate-po.js` 產 `.mo`）。
- **新 PHP 檔**：使用 CLAUDE.md 的標準檔頭（`@package MP_Ukagaka`、`@subpackage Personality`、ABSPATH 檢查；`@since` 只在能確認版本時寫）。在 `mpu_load_modules()` 依載入順序登記（須在 `class-mpu-rest-dialog.php` 使用前載入）。
- **PHPCS**：新增或修改的 PHP 必須讓 PHPCS baseline gate 結束碼為 0（先 `phpcbf`，再手修）；不得用重產 baseline 掩蓋新程式碼的違規。
- **沒有使用者可見的新字串**：故事台詞是 ghost 素材，不經 gettext；若實作中出現新的使用者可見字串，依 i18n 流程處理。

## 10. Phase 0：現況審計

- [ ] 紀錄 HEAD SHA；確認 §2 表格仍正確（尤其 `mpu_nextmsg()` Guard 順序、內建台詞 callback 的 blocking 檢查、觸摸／裝飾 handler 位置）。
- [x] §6.6 決策已確認（v0.7）：接受 Bot／AI crawler 播報可能遺失，不改後端。
- [ ] 開工時再確認其餘事件類型的保存方式未變（v0.6 核對：Turnstile／Akismet／Moelog Bot Blocker 為 transient，Visitor Pulse 為 60 分鐘時窗）。
- [ ] 確認 `mpuChange()` 是角色切換與角色選單的唯一入口（v0.5 已知呼叫端：第 3 鈕、右鍵選單、extend 連結、選單項目），以及 `mpu_extend` 是否在呼叫 `mpuChange()` 前就改寫對話框。
- [ ] 確認前端取得「目前角色 personality」的方式，供 `tryStart` 比對 `stories.personality`（§4.1）。
- [ ] 確認 auto 觸發時 `triggerFrierenSpeaking(false, …)` 仍不呼叫 callback、仍由 `mpu_typewriter()` 的 `playAnimation()` 觸發翻書（v0.5 已核對，開工前再看一次）。第 2 段起用 `{ skipCharacterAnimation: true }`。
- [ ] 盤點所有不經 `mpu_typewriter()` 寫入 `#ukagaka_msg` 的地方（已知：串流聊天、`mpuShowSystemPlaceholder()`、`mpu_nextmsg()` 動畫 callback 的 `.html("")`、`mpuChange()` 選單、`mpu_extend`、`loadExternalDialog()` 錯誤時的 `.html(errorMsg)`、安全事件台詞），確認它們在故事播放期間不會執行，或執行前一定會先觸發 §6.5 的中斷。
- [ ] 確認新模組在 bundle 與 `SCRIPT_DEBUG` 兩種載入模式的位置與依賴。
- [ ] **R1**：確認 `/dialog` 對空 JSON／TXT 仍回 400，第一版採方案 A（不放寬）。
- [ ] **R2**：確認 `#show_msg`、`#ukagaka_img`、觸摸區、裝飾的 listener 綁定位置與傳播順序（capture／bubble、`stopImmediatePropagation()`），確保同一次點擊不會同時觸發觸摸與氣泡切換，且 Story 判斷放在正確位置（§6.3）。
- [ ] **R3**：確認 `#mpu_extend` 是唯一「先 fetch、後直接寫入」且期間不停止 Auto Talk 的流程；`mpuChange("")` 選單仍在請求期間 `stopAutoTalk()`。
- [ ] **R4**：向維護者確認 Auto Talk OFF 是否中斷故事。
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
1a. **R1**：空 JSON `messages`／空 TXT 對話檔 → `/dialog` 仍回現有的 400 錯誤，不因加入 `stories` 而改變。
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
11. 第 2 段時 `cancel()`：history 只有第 1 段；block 已釋放；之後不再播放任何一段；故事已從袋子拿出；故事的打字被取消（run id 改變、之後沒有 `innerHTML` 寫入），已顯示的文字保留。若中斷時目前 run 已不屬於此 session（例如聊天歡迎詞已開始），`cancel()` 不呼叫 `mpu_cancelTypewriter()`。
11.1 **session 隔離**：`cooldown_minutes: 0`、`start_chance: 1`；故事 A 被中斷後立刻開始故事 B，再讓 A 的延遲 callback（段間停頓、看門狗、`onComplete`）到達：B 的 session、`"story-talk"` block、冷卻與播放都不受影響。
11.2 **看門狗**：第 1 段自然完成後，該段看門狗已清除；手動觸發第 1 段的舊看門狗 callback，第 2 段不受影響。
11.3 **重複 `onComplete`**：同一段的 `onComplete` 呼叫兩次，history 只多一對，下一段只排一次。
11.4 **pending owner 集合**：取得 `"startup"` 與 `"spa"` 後，任一方釋放時 `mpuIsContextPending()` 仍為真、`tryStart` 回 `false`；兩方都釋放後才為假。重複取得同一 owner 只算一份；不帶 owner 的 `mpuSetContextPending(true/false)` 行為與修改前相同。
12. `finish()` 重複呼叫只收尾一次；看門狗、整體上限、例外都會釋放 block。整體上限依內容計算：打字速度 200 ms/字、5 段 × 120 字的故事不會被 `timeout` 中斷。
12a. `tryStart` 前置條件：`stories.personality` 與目前角色不同 → `false` 且不讀寫袋子；`mpuIsContextPending()` 為真 → `false` 且不讀寫袋子、不消耗亂數。
12b. 第 1 段不呼叫 `triggerCharacterAnimation()`；以假 canvas manager 驗證：第 1 段 `mpu_typewriter()` 沒有 `skipCharacterAnimation`、第 2 段起有。
12c. **R5**：打字速度 10 ms/字、第 1 段 3 字：`onComplete` 早於淡入完成時 history 照常寫入一次；在 `onComplete` 前中斷（聊天、換角色、`#show_msg` 隱藏）→ 不寫、block 釋放、之後沒有 rAF 殘字寫入。
12d. **R3**：extend 落地檢查：模擬 fetch 延遲，回應到達時故事正在播放 → 不改寫 `#ukagaka_msg`、故事繼續；回應到達時沒有故事 → 照常顯示連結。
12e. **R4**：故事播放中把 `mpuAutoTalk` 設為 `false` 而不經入口 → 下一個延遲 callback 的 `alive()` 中斷故事並釋放 block。
13. `onComplete` 只在自然結束時呼叫；被新的 `mpu_typewriter()` 取代或 `mpu_cancelTypewriter()` 後永不呼叫；不傳 options 的呼叫行為與修改前一致（除了多一個回傳值）。
14. 舊 run 失效後，它已排程的 rAF／`flushUpdate()` 不寫 `innerHTML`；一般台詞的速度、批次、標籤處理與最終輸出與修改前相同。
15. **普通台詞已排定 → Story 開始 → 舊 callback 到達**：以「callback 到達前」的內建台詞袋與 `#ukagaka_msgnum` 為基準，分兩種情況驗證：
    - `showBuiltInMessage()` 開頭被擋：不呼叫 `mpu_selectNextMessage()`，袋子與基準相同。
    - Frieren 動畫 callback 被擋：索引已在動畫前抽出（不要求歸還）；callback 本身不再抽取，袋子與基準相同。
    - 兩種情況都要確認：不呼叫 `mpu_cancelTypewriter()`、不寫對話框、不寫 history、`#ukagaka_msgnum` 不變，故事繼續播放。

## 12. Phase 2：整合

### 工作

- [ ] `mpu_nextmsg()` 加入 §6.1；`startAutoTalk()` tick 加入 §6.6。
- [ ] `mpu_toggleChatMode(true)`、`mpuChange()`（角色切換與選單）加入 `cancel()`；切換成功時清掉 `stories`；Story 的 visibility 與 SPA listener；`mpu_handleSpaNavigation()` 的 context pending（§6.5）。
- [ ] `build.js` 與 `frontend-functions.php`（`SCRIPT_DEBUG`）登記新模組；重建 bundle。
- [ ] Console log i18n 註冊與 `.pot`／`.po`／`.mo`（§9.2）。
- [ ] 觸摸、裝飾、`#ukagaka_img` 加入 §6.3 判斷。
- [ ] `#show_msg` 隱藏分支、`#mpu_extend` 入口與落地檢查、`#toggleAutoTalk` OFF（待確認）加入 §6.5 接點。
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
| 11a | 播放期間開啟角色選單（聊天關閉時按第 3 鈕、右鍵角色） | 中斷；選單不被下一段蓋掉；關閉選單後自發對話照常 |
| 11b | 切到另一角色且該角色的 dialog 載入失敗（測試注入） | 新角色不播放舊角色的故事 |
| 11c | **（R1 改寫）** LLM 置換模式＋有效的內建 Fallback 對話檔 | 故事可觸發，觸發的 tick `/nextmsg` 請求數 0。另做降級檢查：內建對話檔為空或無效時故事不觸發、沒有未處理例外、既有錯誤流程不退化 |
| 11d | 首次載入且頁面感知已排定（機率設 100%），間隔設 3 秒 | pending 期間不開始故事；袋子與冷卻未被改動 |
| 11e | `SCRIPT_DEBUG` 開啟 | 逐檔載入模式下功能與 bundle 模式相同，沒有未定義函式錯誤 |
| 11f | 打字速度設 200 ms/字，播放最長的故事 | 不被整體上限中斷 |
| 12 | 播放期間 F5 | 重新載入後沒有任何鎖定殘留；這篇已消耗、冷卻已生效 |
| 13 | 播放期間有安全事件 | transient 型事件（Turnstile、Akismet、Moelog Bot Blocker）在有效期內發生時，於故事講完後的 tick 顯示，沒有遺失；Bot／AI crawler 事件依 §6.6 決策，記錄實際是否播報，不列為失敗 |
| 13a | SPA 換頁 → 1 秒內 auto tick → 頁面感知 | 1 秒內不開始故事、袋子與冷卻未改動；頁面感知正常觸發；pending 在 1 秒後解除（含頁面感知未命中機率的情況） |
| 13c | **首次載入 3 秒 pending 尚未結束就 SPA 換頁**（兩種順序：首次載入先結束、SPA 先結束） | 任一等待仍存在時都不開始故事；先結束的一方不會清掉另一方的 pending；兩者都結束後故事可正常開始 |
| 13d | **（R2）** 第 2 段打字中按 `#show_msg` 隱藏 | 故事中斷、氣泡隱藏、block 釋放、history 只有第 1 段；之後再按 `#show_msg` 顯示，控制正常、不恢復已放棄的故事 |
| 13e | **（R2）** 播放中點擊角色本體：非觸摸區與觸摸區各一次 | 氣泡不被隱藏、故事不被覆蓋或卡住；觸摸區不進入冷卻、沒有觸摸反應 |
| 13f | **（R4，待確認）** 第 2 段打字中關閉 Auto Talk | 不再出現第 3 段、沒有殘留 block、計時器不自動重啟；重新開啟後一般自發對話正常、不續播 |
| 13g | **（R3）** 延遲 extend fetch，兩種順序：(a) extend 先發出 → 故事開始 → extend 回應；(b) 故事播放中點 extend | (a) 舊回應被丟棄，故事不被覆寫；(b) 故事中斷、extend 正常顯示、沒有故事殘字。兩者都不留 block |
| 13h | **（R5）** 打字速度 10 ms/字＋很短的第 1 段，分別在淡入中開聊天、切換角色、`#show_msg` 隱藏，以及不中斷 | history、畫面與 block 最終一致：沒有幽靈 history、沒有殘字、block 已釋放 |
| 13i | **回歸**：上述中斷後開關聊天、正常觸摸、普通內建台詞、LLM／SSE | 行為與原本一致；`chat_integrity_mode=block` 通過 |
| 13b | 播放中開啟角色選單、SPA 換頁 | 中斷後故事的打字立即停止，選單不被殘字覆蓋；SPA 後不再打出沒記入 history 的文字 |
| 14 | 兩個以上故事 | 全部抽過前不重複（含被中斷的）；換輪第一篇不等於上一輪最後一篇 |
| 15 | 講完／中斷後 | 冷卻期間不會開始故事 |
| 16 | 強制讓 `onComplete` 不回來（測試注入） | 看門狗釋放 block，OK 鈕與自發對話恢復 |
| 17 | 壞 JSON、`enabled:false`、storage 禁用 | 沒有 JS unhandled error；原 Auto Talk 正常 |
| 18 | Story → JSON chat → SSE chat | checksum `block` 不拒絕 |
| 19 | history 超過 40 entries 後插入 Story | 邊界一致 |

### 13.1 回歸驗收

- `npm --prefix tools/node run verify`（含新 smoke 與 PHPUnit）。
- **另行**執行 `npm --prefix tools/node run test:interaction`（`verify` 不含 E2E）。
- PHPCS baseline gate 結束碼 0（§9.2）。
- Console log i18n 檢查：新 key 都有 PHP 註冊、JS fallback 與 msgid 一致、`tools/node/test-logger-smoke.js` 通過、`.po` 無缺漏（§9.2）。
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
- [ ] 播放期間 OK、❌、觸摸、裝飾、點擊角色切換氣泡、送禮被擋；聊天、角色選單、角色切換、extend、`#show_msg` 隱藏可中斷故事；Auto Talk OFF 依維護者決定（建議中斷）。
- [ ] 舊 extend 回應不會覆寫播放中的故事。
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
4. transient 型安全事件（Turnstile、Akismet、Moelog Bot Blocker）在**有效期內**因故事延後處理而遺失（§6.6）。Bot／AI crawler 時窗型事件的遺失已依 §6.6 決策接受；若維護者不接受，改走 §6.6 的替代方案，不另行 STOP。
5. 無法在 `block` 模式維持 checksum 一致。
6. Typewriter 修改造成一般台詞或聊天行為改變。
7. 實測顯示故事頻率太高，破壞芙莉蓮原本悠閒的節奏。
8. 完成第一版範圍需要放寬 `/dialog` 的空檔錯誤語意或其他 REST 資料契約（R1）。
9. extend 的過期判斷無法用「故事是否正在播放」這個簡單條件可靠完成，需要通用的請求仲裁機制（R3）。

## 15. 交付物

1. Phase 0 摘要（一頁內）。
2. 精簡 `stories.json`（一個三段故事，`enabled:false`）與最小程式改動。
3. 測試檔與 pass/fail 證據（含中斷、看門狗、短間隔、LLM 模式、history）。
4. PR 摘要：變更檔案、理由、未涵蓋情境、關閉與回退方式。
4a. `docs-en/GHOST_CREATE_GUIDE.md` 新增 `stories.json` 章節：檔案位置、欄位、驗證規則（段數、字數、禁用字元、emotion tag）、`enabled` 的意義、外掛更新會覆蓋手改檔案。`USER_GUIDE` 等維護者決定正式開放（`enabled:true`）時再同步，不在本 PR。
5. 明確說明目前只是「實驗成功」，**不可**宣稱已發布、已部署或已通過實站測試。

---

## 16. 可以直接交給 Claude／Codex 的執行提示詞

> 請閱讀 `plan/mp-ukagaka-story-talk-implementation-plan-v0.7.md`（v0.7.1）與 `plan/mp-ukagaka-story-talk-v0.7-review-addendum.md`、專案 `AGENTS.md`、`CLAUDE.md` 與最新 `main` 原始碼。此案是華和梨式 Story Talk 的小型獨立功能：一次觸發、整篇連續演出、中斷即放棄、故事之間用 Shuffle Bag 輪替。**第一輪只做 Phase 0（§10），不要改程式或發 PR**。
>
> 已決定的事項（`"story-talk"` message block 鎖定、只允許開聊天、中斷不保存進度、抽出即消耗名額、講完或中斷都計冷卻、段間停頓不讀後台間隔、每段完成即寫 history、Typewriter 加 run id＋舊 run 寫入失效＋`onComplete`、資料搭 `/dialog`）除非發現與程式不符，否則不要重新設計。
>
> 請反對過度工程化：不建新 Event Bus、Scheduler、設定面板或通用故事腳本語言。如果需要大改核心，STOP 並提出較小方案，等我決定。

## 17. 來源（供 Agent 對照）

- [華和梨／Story Talk](https://github.com/ukatech/kawari-docs/blob/main/tips/story-talk.md)
- `js/ukagaka-core.js`：`startAutoTalk()`、`mpu_checkSpamEvent()`、`mpu_nextmsg()`、`mpuDisplayBuiltInMessage()`、`mpuChange()`
- `ghost/Frieren/frieren-animation.js`：`triggerFrierenSpeaking()`
- `js/ukagaka-anime.js`：`playAnimation()`、`triggerCharacterAnimation()`
- `includes/core/frontend-functions.php`：`SCRIPT_DEBUG` 逐檔 enqueue、console log i18n 註冊
- `tools/node/build.js`：`coreFiles`
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
