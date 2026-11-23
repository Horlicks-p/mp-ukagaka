# MP-Ukagaka Story Talk v0.7 — 補充審查意見與計畫修訂指示

**文件性質：** v0.7 附加審查意見（Review Addendum），**不是** v0.7 的替代計畫書，也不是實作授權。  
**日期：** 2026-11-19  
**提供對象：** Claude Code／Codex CLI 及專案維護者  
**審查對象：** `mp-ukagaka-story-talk-implementation-plan-v0.7.md`（787 行）  
**對照原始碼：** `Horlicks-p/mp-ukagaka`，`main` @ `f5e497165c1dfe7be045596203837bf0f7cd5a41`（審查時確認）  
**目的：** 將本輪新增的 3 個程式風險、1 個產品決策，以及 1 個邊界測試，**最小幅度**整合回計畫。原定產品語意與已決策事項一律保留。

> **給執行者：** 請先閱讀 v0.7 全文、`AGENTS.md`、`CLAUDE.md` 及最新程式碼。這份文件是「修改計畫書」的指示，不是要求直接修改程式碼、建立 PR、合併或發布。請明確標示哪些事項可由現有原始碼確認，哪些仍待 Phase 0 驗證。不要為了完成本文件而額外擴大架構。

---

## 0. 審查結論與優先度

**總評：v0.7 可以開始 Phase 0 Audit；Phase 1 前建議先修訂下列內容。**

v0.4 以來確立的「一次觸發、整篇連續演出」方向合理。v0.7 的 Typewriter run ID、session 隔離、動態 Watchdog、Context Pending owner，以及 Bot／AI crawler 播報風險的維護者決策都應保留，**不要重啟這些議題的全面設計**。

| 編號 | 優先度 | 發現 | 建議處置 | 主要關聯章節 |
|---|---|---|---|---|
| R1 | **P0／規格矛盾** | `/dialog` 現況會拒絕空對話陣列，但計畫要求 LLM 模式下空內建對話檔仍能播放 Story | **建議縮小第一版範圍**：不要求空對話檔，改寫 E2E 11c；若維護者堅持支援，先另行批准 REST 語意調整 | §4.1、§10、§11、§13（11c）、§14 |
| R2 | **P0／操作語意** | `#show_msg`、`#ukagaka_img` 可在播放期間顯示／隱藏氣泡，Story 可能在隱藏後繼續推進與記歷史 | 明確定義隱藏／角色點擊行為，補 Story 專用 Guard／中斷與 E2E | §3.5、§6.3、§6.5、§10、§13 |
| R3 | **P1／非同步覆寫** | `mpu_extend` 在 Fetch 回應後直接 `.empty().append()`，早於 Story 的舊請求可能晚到並覆寫 Story | 在入口與回應真正寫 DOM 時都處理 ownership／過期判斷，補決定性測試 | §6.3、§6.5、§10、§11、§13 |
| R4 | **產品決策** | 播放中切換「停止自動對話」，原 timer 停了，但 Story 的分段 timer 可繼續執行 | **建議停止 Auto Talk 立即取消整篇 Story**；確認後加入中斷表及測試 | §3.5、§6.5、§13、§14.2 |
| R5 | **邊界驗證** | 第 1 段 Typewriter 完成可能早於氣泡淡入完成 | 定義 history 的「實際顯示完成」判準，補最快打字與淡入中斷測試 | §3.3、§6.2、§6.4、§7.1、§11、§13 |

**重要：** R1 是可由目前 REST 程式直接證實的契約矛盾；R2、R3 是可由目前事件與寫入路徑推導的競態風險；R4 是尚未定案的產品操作政策；R5 是應以測試釐清的時間邊界。不要把尚未跑過的情境寫成「已在實站重現」。

---

## R1. LLM 模式＋空內建對話檔：`/dialog` 契約矛盾

### 程式依據

`includes/rest/class-mpu-rest-dialog.php` 的 `load_dialog()` 在 JSON 與 TXT 載入路徑，對 `messages` 為空或解析後無訊息的情況直接回傳錯誤。成功回應的組裝在**空資料檢查之後**。因此，即使前端 `js/ukagaka-dialog.js` 的 `msg.length === 0` 分支保留 `resp.stories`，單靠這個前端修改也無法讓真正的空內建對話檔取得 `stories`。

### 建議方案（優先採用 A）

**A. 第一版維持有效內建對話檔作為 LLM Fallback，移除「真正空檔也能播放 Story」的要求。**

- LLM 置換對話模式依然完整支援 Story；這不等於把 Story 限定在非 LLM 模式。
- 觸發 Story 時，仍然不呼叫 `/nextmsg` 或改寫固定台詞。
- `/dialog` 的既有「空資料回傳錯誤」語意維持不變。
- `loadExternalDialog()` 在 `msg.length === 0` 分支保留 `stories` 的防禦性處理可以留著，但**不可**以此宣稱後端已支援空檔。
- E2E **11c** 改為：`LLM 置換模式＋有效內建 Fallback 對話檔 → Story 可以觸發且無 LLM 請求`；另加入「內建檔無效／空檔時 Story 不觸發、沒有未處理例外、既有錯誤流程不退化」的降級檢查。

**B. 若維護者明確要求支援真正空檔**：需先釐清只有 LLM 置換模式能通過，還是所有模式都能通過；是否會破壞 `/dialog` 既有消費者與錯誤語意；並補 PHP＋前端整合測試。這涉及 REST 資料契約，**不應在 Phase 1 順手放寬驗證**。

### 請修改 v0.7

- **§4.1**：修正「LLM 專用、沒有內建台詞」敘述；保留前端空資料防禦，但說清楚目前 `/dialog` 並不允許真正空檔成功回應。
- **§10**：加入檢查 `/dialog` 空檔行為與是否採用 A 的核對項。
- **§11**：PHPUnit 驗證現有空檔處理不退化。
- **§13 E2E 11c**：依 A 的測試條件改寫，不再要求當前後端尚不支援的狀態。
- **§14／STOP**：若必須改動 REST 契約才能完成已選定的第一版範圍，停止並回報維護者，而非默默擴張。

---

## R2. Story 播放中隱藏／顯示氣泡的操作規則缺口

### 程式依據

`js/ukagaka-features.js` 的 `#show_msg` 點擊會切換 `#ukagaka_msgbox` 顯示狀態，`#ukagaka_img` 點擊亦可能使氣泡顯示或隱藏。這兩個路徑不完全等同 Frieren `handleTouchZone()` 或 `handleDecorationClick()`，不能假設 v0.7 §6.3 已加入的觸摸／裝飾 Story Guard 就能涵蓋。

潛在次序：`Story 開始 → 使用者隱藏對話框 → 分段 Typewriter 仍完成 → stepDone 寫入 history、推進下一段`。這會使「顯示成功才記歷史」變得模糊。

### 建議的產品語意

1. **訪客主動點擊「隱藏氣泡」控制：** 允許隱藏，但**先取消 Story**，停止 Story 自己的 Typewriter，釋放 `"story-talk"` block；已完成的前段照常保留 history，正在打字而未完成的一段不記錄。故事抽取和冷卻遵照 §5 現有規則。
2. **播放中點擊角色本體：** 若這次點擊只會切換氣泡顯示，則不應意外隱藏正在播放的 Story；可直接忽略「氣泡切換」部分。若此點擊屬於現有 Touch Zone，則沿用 v0.7 已定案的「Story 播放期間觸摸無效」。
3. **故事已被取消後：** 後續 `#show_msg`、角色點擊及其他普通顯示控制恢復原本行為。不得留下不可點擊或永久隱藏的介面。
4. **不要**為了這兩個按鈕引入新互動管理器，也不要把所有既有氣泡顯示函式統一改成「遇到任何 message block 就禁止」。只加 Story 專屬的最小接點。

### 請修改 v0.7

- **§3.5**：新增「隱藏氣泡」與「點擊角色本體切換氣泡」兩列。
- **§6.3／§6.5**：寫清楚是 `cancel()` 還是忽略 click，避免在同一事件先隱藏再取消，造成不必要的 UI Flicker。
- **§10**：明確盤點 `#show_msg`、`#ukagaka_img`、Touch Zone 的事件傳播與執行順序（含同一次點擊是否觸發多個 listener）。
- **§13**：新增 E2E：
  - Story 第 2 段打字中 → 使用者點隱藏：故事中止、氣泡隱藏、block 釋放、history 僅含已完整段落。
  - Story 播放中點角色本體：氣泡不被意外隱藏、Story 不被覆蓋／卡住。
  - 中斷後再次顯示氣泡：控制仍正常，不會恢復已放棄的故事。

---

## R3. `mpu_extend` 的非同步舊回應可能覆蓋新故事

### 程式依據

`js/ukagaka-features.js` 的 `mpu_extend` 會發起非同步請求，並在 `.then()` 成功後直接對 `#ukagaka_msg` 執行 `.empty().append(link)`；這個 DOM 寫入**不是** `mpu_typewriter()`，不會自動被 Typewriter run ID 保護。

單在 Extend 的**點擊入口**呼叫 `storyTalk.cancel()`，仍無法排除「舊 Extend 請求未返回 → Story 才開始 → 舊回應晚到覆寫」的狀況。

### 最小修正要求

- **在 Extend 開始時：** 若 Story 正在播放，先 `cancel("menu")`，優先處理使用者明確的操作。
- **在 Extend 請求待完成期間：** Phase 0 應確認是否需要一個**僅限 Extend 的短暫 pending 標記**，讓 `storyTalk.tryStart()` 暫時返回 `false`。這能避免較早點擊的操作等待期間，Story 反而搶先佔用氣泡。pending 必須在 success、error、取消／被取代路徑都清除；不得變成永久 message block，也不新增通用 Request Registry。
- **在 `.then()` 真正寫入 DOM 前：** 必須再次確認此次請求仍屬目前角色與目前操作，且沒有更新的 Story／聊天／選單接管畫面。若已過期，直接丟棄舊結果，**不應為了顯示過期 Extend 而取消更新的 Story**。
- **如果無法可靠判斷過期：** 回報維護者，在 Phase 0 選擇較小的可證明修正；不要擴張成所有 API 回應的通用仲裁器。

### 請修改 v0.7

- **§6.5**：除「Extend 入口取消 Story」外，補「非同步結果落地前再次驗證 owner／世代」的要求；若引入 pending，說清楚清理路徑。
- **§10**：將 `mpu_extend` 列為必查的「先發出、後寫入」流程；也核對 `mpuChange()` 選單結果及其他明確列於現有盤點的直接寫入來源。
- **§11**：JS smoke 測試：舊 Extend 回應在 Story 已開始後到達，不改寫當前對話框；pending 不殘留。
- **§13**：E2E：製造延遲 Extend Fetch，覆蓋「Extend 先、Story 後、Extend 再回應」及「Story 先、點 Extend、再回應」兩種順序；不要求重寫既有 Extend 功能。

---

## R4. 播放途中關閉 Auto Talk：請明確定案

### 程式依據

`js/ukagaka-features.js` 的 `#toggleAutoTalk` 只切換 `mpuAutoTalk` 並呼叫 `startAutoTalk()`／`stopAutoTalk()`。v0.7 的 Story 一旦開始就由自己的段間 timer 控制；它不直接使用 Auto Talk timer，因此**關閉 Auto Talk 不等於停止正在播放的故事**。

### 建議決策（請維護者確認）

**建議：關閉 Auto Talk 時，立即取消目前 Story；重新開啟時不續播舊劇情。**

- 點擊「停止自動對話」時，如果 Story 正在播放：先取消 Story、釋放自己的 block，再走既有 `mpuSetAutoTalkEnabled(false)`／`stopAutoTalk()` 行為。
- 已完整說完的段落保留 history；未完成的段落不記錄；抽出即消耗、冷卻生效，不例外。
- 恢復開啟 Auto Talk 只是恢復正常排程；不接續同一故事。
- 不從 Story 模組自行呼叫 `startAutoTalk()` 或 `stopAutoTalk()`，繼續遵守 v0.7 §0 的限制；由既有 Auto Talk 開關入口呼叫 `storyTalk.cancel()`。

### 請修改 v0.7

- **§3.5**：新增「Auto Talk 從 ON → OFF」列。
- **§6.5**：新增 `#toggleAutoTalk` 的中斷接點，只在 OFF 轉換時取消。
- **§13**：新增 E2E：第 2 段打字途中關閉 Auto Talk → 不再出現第 3 段、不留下 block、不自動重啟 timer；重新啟用後普通自發對話正常。
- **§14.2**：將此產品語意加入驗收。

若維護者刻意希望「停止 Auto Talk 不影響已開始故事」，也可以選擇相反方案；但**必須在文件中明講且補測試**，不可保留未定義狀態。

---

## R5. 第 1 段最快打字與氣泡淡入的邊界

### 檢查理由

v0.7 的 §6.2 在第 1 段呼叫 `mpu_typewriter()` 後，用 `mpu_showmsg(400)` 淡入對話框；Typewriter 的自然完成與「訪客真的看得見完整文字」並非永遠是同一時間點。打字速度 10 ms／字、短台詞時尤其容易碰到這個邊界。

**這不是已確認的正式站錯誤**，不應直接要求重寫 Typewriter 或動畫器；應先在測試中明確定義「完整顯示」的判準。

### 建議定義

- `onComplete` 仍然代表**該次 run 自然完成並寫入最後字元**，不應改成全域的「使用者確實已閱讀」通知。
- Story 的 history commit 至少要保證：run 自然完成、session 仍有效、當前對話框沒有被其他操作隱藏／取代；如第 1 段仍在淡入，應決定是等淡入完成後再 commit，還是證明目前流程在此時間點已可見且不會被覆蓋。
- **不要求**以動畫幀或視線偵測證明使用者真的閱讀；只保證「完成的台詞在有效可見的氣泡中呈現」。
- 若在淡入期間中斷，未完成／未安全呈現的段落不能被推進或誤記；取消仍要阻止舊 run 的 rAF 殘留寫入。

### 請修改 v0.7

- **§6.2／§7.1**：加入「可見氣泡與 run 自然完成」的 history commit 判準。
- **§11／§13**：補最快打字速度 10 ms／字＋很短的第一段；分別測試自然完成、淡入途中開聊天、淡入途中切換角色／隱藏氣泡。確認 history、畫面與 block 最終一致。
- **§6.4**：保持 Typewriter run ID＋`onComplete` 的小範圍設計，不擴大成跨元件動畫狀態機。

---

## 1. 請整合到 v0.7 的章節對照表

| 原章節 | 本輪應補內容 |
|---|---|
| §0／§1.2 | 說明用戶主動隱藏與停止 Auto Talk 的中斷語意；禁止無理由擴大 REST 契約 |
| §2 | 補 `#show_msg`、`#ukagaka_img`、Auto Talk 開關、Extend 延遲回應、`/dialog` 空陣列拒絕的程式現況 |
| §3.5 | 在優先權表加入上述操作，保持其他優先權原則不變 |
| §4.1 | 修正 LLM 空對話檔的承諾（建議選擇 R1 A） |
| §6.2／§6.3 | 補氣泡可見性、取消規則、需要時對 Extend 進行等待期間保護 |
| §6.5 | 補 `#show_msg`、角色本體、Auto Talk OFF、Extend 的非同步落地檢查 |
| §6.4／§7.1 | 定義最快打字／第 1 段淡入下的 commit 條件，不改變全域 Typewriter 語義 |
| §10 | 新增上述四條實際入口與／dialog 契約核對；報告一頁內 |
| §11 | 補少量可決定性重跑的 JS／PHP 測試 |
| §13 | 改寫 11c；補隱藏、Auto Talk OFF、Extend race、第一段短句中斷 E2E |
| §14.2／§14.3 | 補產品行為驗收，以及需要更改 REST 核心契約時的 STOP 規則 |
| §16 | Phase 0 提示詞要求核對 R1～R5，但不授權直接動程式 |

## 2. 建議的新測試清單（可直接補入 §11／§13）

| ID（暫定） | 層級 | 情境 | 最低驗收證據 |
|---|---|---|---|
| ADD-01 | PHP／REST | 目前 `/dialog` 讀到空 JSON／TXT | 回應符合現有錯誤契約；第一版不宣稱空檔成功提供 `stories` |
| ADD-02 | E2E | LLM 置換模式＋有效 Fallback＋啟用 Story | Story 可觸發，該 tick `/nextmsg` 請求數 0 |
| ADD-03 | E2E | 第 2 段打字中按隱藏對話框 | Story 中斷；隱藏有效；已完成段落入 history；block 釋放 |
| ADD-04 | E2E | 故事期間點角色圖片（含 Touch Zone） | 不意外隱藏氣泡／覆寫劇情；普通觸摸冷卻不變 |
| ADD-05 | JS smoke＋E2E | Extend 先發請求、Story 開始、舊 Extend 回來 | 過期請求不得覆寫故事；不留下 pending 或永久鎖定 |
| ADD-06 | E2E | Story 正在播，使用者啟動 Extend | 正確取消 Story；Extend 正常呈現；無 Story 殘留 rAF |
| ADD-07 | E2E | 第 2 段中關閉 Auto Talk | Story 中斷且不續播；timer 停止，沒有殘留 block |
| ADD-08 | JS smoke＋E2E | 短第一段＋10ms 字速，淡入中遭聊天／角色切換／隱藏打斷 | 沒有幽靈 history、殘字或漏掉的鎖釋放 |
| ADD-09 | 回歸 | 上述中斷後關掉／重開聊天、正常觸摸、正常內建台詞、LLM／SSE | 行為與原本一致，`chat_integrity_mode=block` 通過 |

測試須區分 **「有 mock 的 JS smoke」、「隔離 WordPress＋瀏覽器 E2E」與「正式站」**；前兩者通過不等於正式站驗證完成。新 E2E 應重用既有 `tools/e2e/interaction-e2e.js` 與假 Provider，避免付費模型呼叫。

---

## 3. 最小改動與停止條件

本輪的目的只是把規格修準，不重做 Story Talk。請遵守以下限制：

- 保留 v0.7 核心產品定義：**低機率觸發、連續演出、抽出即消耗、不跨頁接續、不經 LLM 改寫、每段自然完成才入 history、固定 15 分鐘冷卻（初版測試值）**。
- 保留既有 `"story-talk"` message block owner、Typewriter run ID、session 隔離、動態 Watchdog、故事袋和 `stories.json` 驗證。
- 保留 v0.7 維護者已批准的「Bot／AI crawler 時窗型事件可能漏播報、無須修改後端」決策。
- 不因本輪建議新增 Event Bus、Scheduler、通用 storage／request 管理框架、後台設定面板、Script Interpreter 或跨頁進度機制。
- 不重寫 SSE／Chat Integrity、RO Shell 或 Mesh Avatar。
- 如實作必須大幅更動 `/dialog` 契約、通用 Typewriter、SPA／互動優先權，**停止並向維護者提出最小替代方案**。
- `stories.json` 繼續 `enabled:false`；本文件不授權正式站部署或發版。

## 4. 交付要求

請 Claude／Codex 在下一輪**僅整合計畫**：

1. 以 v0.7 為基底產出 **v0.7.1**（或維護者另定版本）完整 MD，而非重新從 v0.1 開始編寫。
2. 在文件前方新增「v0.7.1 變更摘要」，列出 R1～R5 與採納／拒絕原因；保留 v0.7 既有決策記錄。
3. 修正所有相互引用的章節、E2E 編號、STOP 條件，避免同一操作在不同章節有不同語意。
4. 若 R4 或 R1 B 等涉及維護者產品選擇，明確標記**待確認**；不要當成已核准決策。
5. 提供短的差異摘要：修改哪些段落、為何修改、哪些項目因避免過度工程化而不採納。
6. **不要修改程式碼、不要直接開 PR、不要合併或部署。** 下一階段仍是 v0.7 所要求的 Phase 0 Audit。

### 可直接貼給 Claude／Codex 的提示詞

> 請以 `mp-ukagaka-story-talk-implementation-plan-v0.7.md` 為唯一主要計畫基準，閱讀本份 `mp-ukagaka-story-talk-v0.7-review-addendum.md`。請先核對最新 `main` 的相關程式，然後**只更新計畫書**為 v0.7.1；不要修改外掛程式碼。請特別處理：R1 `/dialog` 對空檔的拒絕與 E2E 11c 的矛盾（優先採用「第一版不支援真正空檔」）；R2 `#show_msg`／`#ukagaka_img` 在 Story 播放中的控制；R3 Extend 舊 Fetch 晚到覆蓋畫面；R4 Auto Talk OFF 是否立即取消 Story（建議是，但須維護者確認）；R5 第一段淡入與 Typewriter 最快速度下的 history commit。請保留 v0.7 其餘產品決策、Phase 0 先行與最小修改限制，最後附修訂差異摘要、尚待維護者確認事項及 STOP 條件。未經許可不得實作、合併、發版或部署。

---

**文件結論：** v0.7 的 Story Talk 主設計可以保留。本輪只需補齊少數真實程式入口、操作政策與回歸測試，就能讓 Phase 0／後續實作擁有更一致的規格；沒有必要把功能變成更大的系統。
