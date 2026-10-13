# mp-ukagaka — 保守型重構評估與 REST Chat 測試先行計畫（v3）

> **給 Claude Code／Codex CLI 的正式工作指示書（先評估，後決策）**  
> 專案：[Horlicks-p/mp-ukagaka](https://github.com/Horlicks-p/mp-ukagaka)  
> 討論基準：2026-10-13，`main`／`afadcdd84196`／v2.37.4。**執行時必須重新確認 HEAD；本文行號僅為定位參考。**  
> 版本目的：根據 Claude Code 與 Codex CLI 對 v2 的審查，修正 integrity 契約、Chat lock 釋放責任、test harness 成本估算、測試分級與 Git 歷史統計方法。  
> **總原則：測試品質 > 測試數量；可驗證的維護收益 > 原始碼行數縮減；不強制重構。**

---

## 0. 最高優先指令：本次僅執行 Phase A

**收到此文件後，先做唯讀分析，提交一份 Phase A 報告，然後停止。不得直接修改 production code、建立測試 PR 或開始重構；必須取得開發者明確同意。**

- 允許判定：**GO／HOLD（含 TESTS FIRST）／NO-GO**。即使結論是「不值得建立 REST Controller test harness」，亦為合格成果。
- 本輪候選的優先順序：**B REST Chat 測試可行性 > 其他候選**。A1 Observation **HOLD，偏 NO-GO**；A2 Security Events **NO-GO**；C Ghost ZIP **Later**。
- 測試只能表徵既有行為（characterization），不得將期待的新功能、安全修正、文字清理、logger keys 變更混入測試 PR。
- **Phase A 必須先量化 test harness 成本與真實度**：不可為了勉強跑 PHPUnit 搭建龐大的「假 WordPress」。
- **PR 1（僅測試）完成後必須再次停止**。PR 2 的任何 private helper extraction 必須另外獲批；沒有 PR 2 也算成功。
- 如 working tree 有使用者改動或 HEAD 已變更，先記錄並回報；不得覆寫或自動整理。

### v2 → v3 的必要更正

1. **Integrity mismatch 分三模式**：預設 `audit`、`warn` 不阻擋；`block` 才阻擋（通常為 HTTP 409），且要考慮 `mpu_chat_integrity_should_block` filter 的覆寫。
2. **Lock 責任分層**：`prepare_user_chat_args()` 只負責取得／早退時釋放；成功時把 lock 放進 `$args['chat_lock']`。`user_chat()`／`user_chat_stream()` 才處理正常流程的 release／shutdown。
3. **Test harness 可行性是 Gate 0**：先估算 classes、functions、mock、載入次序與 diff；不適合就縮小 PHPUnit 範圍或採用真 WordPress／Playground 整合測試。
4. **避免假綠燈**：`function_exists()`、`class_exists()` 的 fallback 在測試中可能與 production 不同；要明確斷言關鍵模組已載入。
5. **PR 1 測試分 P0a／P0b／P1**，可拆成獨立的 test-only PR；不能讓測試基建規模失控。
6. **已知 `git log -L :prepare_user_chat_args:...` 可能無法辨識 PHP `protected` 方法**；改用行號範圍、`git blame` 與逐 commit diff 交叉驗證，且不得把結果當作絕對精確數字。
7. **`MPU_Observation_Buffer::drain()` 是 prepare 的副作用**：必須記錄其位置與發生條件，之後不可因抽 helper 而改變時機。

---

## 1. 現況及候選：只對具體痛點動手

| 對象 | v2.37.4 觀察值（約） | 判斷 | 理由 |
| --- | ---: | --- | --- |
| `includes/rest/class-mpu-rest-chat.php` | 1,308 行 | **B：TESTS FIRST** | 高頻修改，混合輸入、history、lock、prompt、JSON/SSE |
| `prepare_user_chat_args()` | 518–950，430+ 行 | **本輪唯一主題** | JSON 與 SSE 共用；直接 controller-level 測試缺口 |
| `chat_context()` | 136–362，約 227 行 | 僅檢查真正重複 | 已有 `prepare_auto_chat_context()` 共用 helper |
| `chat_greet()` | 363–517，約 155 行 | 僅檢查真正重複 | 與 Context 的語境／限制並不完全相同 |
| `js/ukagaka-core.js` Observation | 114–231，約 118 行 | **A1：HOLD／偏 NO-GO** | 穩定、跨 SPA caller、抽檔涉及 build/enqueue/listener |
| `mpu_checkSpamEvent()` | core 約 435 行起 | **A2：NO-GO** | 高度耦合 auto-talk、history、Canvas、typewriter、request |
| `includes/admin-functions.php` Ghost ZIP | 約 797 行起 | **C：Later** | 有相對完整邊界，但目前沒有足夠拆分收益 |

以前的工具審查指出，半年內 REST Chat 全檔約 23 次、`prepare_user_chat_args()` 約 17 次、Observation 約 2 次變更。**這是待 Claude 重新量測的歷史主張，不是本文件獨立驗證過的精確統計**。特別區分檔案提交次數、行範圍提交次數、真正有維護成本的修改次數。

### 請勿因「檔案長」而動到以下模組

`js/ukagaka-base.js`、`js/ukagaka-core.js` 的 async 競態區、`ghost/Frieren/frieren-interactions.js`、`includes/core/frontend-functions.php`、`includes/llm/llm-context-builder.php`、`tools/e2e/interaction-e2e.js` 等，不自動進入拆檔清單。

---

## 2. Phase A — 唯讀評估（第一且唯一的即刻交付物）

### A. 基準及歷史證據

1. 核對分支／commit／version／working tree／可用測試命令；不要動 untracked 檔案。
2. 統計半年內的文件與方法變更；按 bug fix／功能新增／格式化／重構分類，避免將單純 touch 次數視為痛點。
3. 確認 `prepare_user_chat_args()`、`chat_context()`、`chat_greet()` 的確切界線與 caller；特別辨認哪些共用邏輯已由 `prepare_auto_chat_context()` 承擔。
4. 查看現有 `tests/Unit/ChatLockTest.php`、`ChatIntegrityTest.php`、`RequestUkagakaTest.php`、Node smoke 與 E2E；**service test 不等於 controller test**。

建議指令（若 `git log -L` 不支援即走 fallback）：

```bash
git status --short
git branch --show-current
git rev-parse HEAD
git log -5 --oneline

git log --since="6 months ago" --format='%h %as %s' -- includes/rest/class-mpu-rest-chat.php

# 在目前 Git/PHP diff driver 下，symbolic -L 可能得到 "no match"；失敗不代表沒有歷史。
git log -L :prepare_user_chat_args:includes/rest/class-mpu-rest-chat.php --since="6 months ago"

# 行號僅為目前版本的起點，歷史中可能漂移；不能直接視為精確函數統計。
git log -L 518,950:includes/rest/class-mpu-rest-chat.php --since="6 months ago"
git blame -L 518,950 includes/rest/class-mpu-rest-chat.php

git log --since="6 months ago" --oneline -- js/ukagaka-core.js
rg -n 'prepare_user_chat_args|prepare_auto_chat_context|chat_context\(|chat_greet\(' includes tests tools
rg -n 'MPU_Chat_Lock|MPU_Chat_History_Service|register_chat_lock_shutdown|release_chat_lock' includes tests tools
```

`-L` 為追蹤線索，需與個別 commit diff／PR patch 交叉驗證。半年區間須記錄起訖日期，rename 與 merge 需標註。

### B. REST Chat 早退／狀態生命週期圖

建立一份表格，逐點記錄：**前置條件、return 類型與 status、是否已取得鎖、prepare 是否釋放、caller 是否負責釋放、是否可能發生其他副作用**。至少包括：

| 情境 | 相對於取得鎖 | 目前預期契約（需實測） |
| --- | --- | --- |
| Session token 缺失／無效 | 之前 | `check_session_token()` 擋下；未取鎖 |
| Rate limit | 之前 | rate-limit response；未取鎖 |
| 空訊息 | 之前 | 輸入錯誤；未取鎖 |
| 管理員 `/debug_mcp` | **之前** | 提前返回特殊 args；`chat_lock = null`；非管理員不應走此分支 |
| Lock busy | `acquire()` 階段 | 回傳相應錯誤（通常 429）；不得釋放別人取得的鎖 |
| Checksum mismatch：`audit`／`warn` | 取得鎖後 | **non-blocking**，繼續後續準備；保留事件／診斷副作用 |
| Checksum mismatch：`block` | 取得鎖後 | 阻擋，通常 **409**；若有鎖須由 prepare 釋放 |
| 非 Ollama Provider 無 API key | 取得鎖後 | 提前返回錯誤；若有鎖須由 prepare 釋放 |
| 準備成功 | 取得鎖後（或無需鎖） | 回傳 `$args['chat_lock']`，**prepare 不註冊 shutdown／不做正常 release** |
| `user_chat()` 成功／失敗 | caller | 登記 shutdown cleanup；JSON 路徑由 `finally` 釋放 |
| `user_chat_stream()` 成功／失敗 | caller | 登記 shutdown cleanup；SSE 依各個 error／done／exit 分支顯式釋放 |

**注意：** `MPU_Chat_Lock::acquire()` 不是每條路徑都必定執行；它受非空 session ID 及 class 可用性影響。測試環境應優先覆蓋 production 的正常 class 已載入路徑，不要因缺 class 而意外略過鎖。

### C. History／Integrity／Prompt 的副作用圖

需特別記錄並在可能範圍內保護：

```text
request history
  → MPU_Chat_History_Service::normalize_history()
  → filter_orphan_assistants()
  → integrity_history（完整的 normalized transport history）
  → chat_history（最後 20 筆，供 LLM context）
  → /debug_mcp early return（管理員）或 lock acquire
  → MPU_Chat_History_Service::verify(session_id, integrity_history)
  → provider / API key
  → personality / language / prompt assembly
  → MPU_Observation_Buffer::drain(runtime_session_token)（存在且符合條件時）
  → return args to JSON/SSE caller
```

- 完整 integrity history **不是**最後 20 筆 LLM context；但 `verify()` 內部仍使用既有 checksum window／filter 規則，**不能把兩者混為一談**。
- `MPU_Observation_Buffer::drain()` 的呼叫會消耗 buffer。**其相對於早退、API key 驗證與 prompt 組裝的位置也是既有契約**。不要在重構中移到 function 開頭／離開 prepare 或令其重複執行。
- 只記錄實際可觀察的效果，不為簡化測試而改 production 副作用。

### D. Gate 0 — Controller 測試環境可行性（必須交代）

**請先做 dependency inventory 和成本估算，再決定可不可寫直接 controller PHPUnit。**

目前確認的前提：

- `tests/bootstrap.php` 是 minimal WordPress stub bootstrap，載入了真實 Chat History Service、Chat Lock、Input Role 等部分模組，但**沒有預先載入 `MPU_REST_Base`／`MPU_REST_Chat`**。
- 共用 bootstrap **沒有正式可共用的 `WP_REST_Request`、`WP_REST_Response` stub**；`tests/Unit/SpamEventResponseTest.php` 有局部的 `WP_REST_Request` 定義。若另加全域同名 class，須處理 redeclare／測試檔載入順序問題。
- `MPU_REST_Base` 是專案自身抽象類別，不直接繼承 WordPress REST class；但其 method type hints、session/rate-limit/helper 和 controller 的大量函數呼叫仍需要正確運行環境。
- `prepare_user_chat_args()` 涉及 provider 選擇、WordPress info、role／personality、prompt、Observation Buffer 與其他 helper；不得假設目前的 bootstrap 已載入所有 production 必要函數，也**不得假設完全沒載入**。逐一清點。

Phase A 需交付以下表格（填實際結果）：

| 必要依賴 | 已在 bootstrap 載入？ | 真模組可安全載入？ | 可替身化？ | 缺少時是否改走 fallback？ | 估計行數／風險 |
| --- | --- | --- | --- | --- | --- |
| `WP_REST_Request`／`WP_REST_Response` | 待確認 | WP runtime 才有 | 限定測試替身 | 可能導致型別與 API 問題 | 待估 |
| `MPU_REST_Base`／`MPU_REST_Chat` | 待確認 | 通常可 require | subclass 代理 | N/A | 待估 |
| `MPU_Chat_History_Service`／`MPU_Chat_Lock` | 待確認 | 優先真實載入 | 避免替身核心契約 | 是 | 待估 |
| `MPU_Input_Role`／`mpu_set_request_input_context` | 待確認 | 優先真實載入 | 視需要 | **是** | 待估 |
| provider／personality／language／prompt functions | 待確認 | 視依賴 | 容易全域衝突 | **是** | 待估 |
| rate-limit／session token helpers | 待確認 | 視依賴 | 需受控 | 是 | 待估 |
| observation drain／log side effects | 待確認 | 優先真實載入 | 需隔離副作用 | 是 | 待估 |

**比較至少兩條路，必要時給第三條：**

1. **最小 PHPUnit controller harness**：專用 test-support、特定測試 process isolation、有限 request/response stubs、盡可能載入真正 service；評估會碰哪些 `tests/bootstrap.php`、既有測試，總新增多少行。
2. **實際 WP/Playground 整合測試**：少量可決定性 fixture、受控 Provider、REST HTTP status／history／lock 觀察；評估啟動時間、穩定性與 CI 可用性。
3. **混合方案／縮小測試**：共用 service tests 鎖定 history／integrity，僅用極少量 controller integration assertions 覆蓋關鍵 early return。

**門檻：** 如建一套虛假的 WP runtime、到處覆蓋全域函數、或大量 test-only stubs 才能測到成功路徑，判定 **HOLD／縮小 PR 1**，而不是硬湊測試覆蓋率。

**避免假綠燈：** 若 production 會載入 `MPU_Input_Role`、`mpu_set_request_input_context`、`mpu_resolve_personality_id` 等必要模組，測試須先 assert 真正的 class/function 存在，且走的是 production 正常分支。測 fallback 時才刻意隔離並標示。**不要用 `function_exists()` fallback 被動「讓測試通過」。**

**隔離要求：**

- 不應直接在共用 bootstrap 全域增加易與現有局部 `WP_REST_Request` 宣告衝突的 test class。建議先比較獨立 test-support／process isolation 的可行性；確有需要時，設計一處共同來源，再協調既有測試。
- 禁止在正式程式碼增加測試專用 public method、hook 或 instrumentation。
- Match production class loading order；控制 `$_COOKIE`、options、transients、filters、session ID、current user、rate limit 的清理。
- Integrity mismatch 會寫診斷 log（`logs/checksum-mismatch.log`）；測試需保護開發者／CI 日誌，使用可隔離的環境或安全清理，不可向真實網站外洩或覆蓋現有 log。
- `MPU_CHAT_INTEGRITY_MODE` 是常量優先於 option 的模式來源，且 WordPress filters 可覆寫行為；跨測試不可殘留常量／filter 污染。
- 在無金鑰／不連真實外部 LLM 的條件下測試，禁止把秘密寫進 fixture 或 log。

---

## 3. 只有獲批准才啟動 PR 1 — 測試分層與可縮減範圍

**PR 1 的目的是增加可靠的行為防護，不是保證取得 100% controller coverage。**

### P0a — 最小、最高性價比的核心（首選）

若 Gate 0 可行，優先選下列直接且可觀察的契約：

- [ ] token 與 rate limit 的阻擋發生在 lock acquire 之前；保留既有 response status／shape。
- [ ] 空訊息的既有 early return；一般長度上限（500 字元）的效果，Unicode 多位元／組合字元細節可留 P1。
- [ ] History 先 normalize，再 orphan-filter，**完整** `integrity_history` 與末尾 20 筆 `chat_history` 不混用。
  - **Fixture 必要條件**：checksum 只取「最後 40 筆 raw → `type=chat` 的 assistant → 最後 10 筆」（`mpu_chat_integrity_slice_for_store()`）。一般 user/assistant 交替的 20 筆已含 10 筆 chat assistant，提前 slice 也不會改變 checksum。因此 fixture 必須在最後 20 筆 raw 內放入足夠的非 chat 訊息（`auto_talk`、`give`、`synthetic` 等），使第 10 筆 chat assistant 落在 -20 之前。
  - Mismatch／match 測試需先寫入 checksum transient 作為基準；無基準時 `verify` 直接放行，測不到目標路徑。
  - **必做 mutation 驗證**：本機臨時將 `array_slice(-20)` 提前到 `integrity_history` 之前，確認此測試會失敗，再完整還原；mutation 不得進入 PR。
- [ ] lock busy 的錯誤／status（通常 429）不釋放他人 lock；非空 session 正常取得 lock 時保留在成功 `$args['chat_lock']`。
- [ ] provider 無 API key 的 early return：若已取得 lock，prepare 必須釋放；保留既有 status／error code。
- [ ] Integrity mismatch 各模式（如下）在已有可控 session／checksum fixture 下，驗證是否阻擋與 early return lock release。

#### Integrity 必測模式（無覆寫 filter 的基準行為）

| 模式 | `verify()` 的預期結果 | Controller 行為 | Lock 斷言 |
| --- | --- | --- | --- |
| `audit` | 記錄 mismatch、回傳 `null` | **繼續**準備參數 | 若取得，成功時仍帶在 args |
| `warn` | 記錄 warning、回傳 `null` | **繼續**準備參數 | 若取得，成功時仍帶在 args |
| `block` | `WP_Error`，錯誤資料 `status=409` | **早退**，對外通常 HTTP 409 | **prepare 釋放已取得的鎖** |

**分層要求：** 若完整 controller 成功路徑的 harness 成本過高，可由 service-level tests 完整覆蓋 audit／warn／block 三模式；controller-level 最低要求則是驗證 block mismatch 會早退並釋放 lock，以及至少一個 non-blocking mismatch 能越過 verify。不得用純原始碼字串斷言代替。

額外注意：`mpu_chat_integrity_should_block` filter **可以覆寫阻擋決定**。如測試成本可接受，另加一個 filter override case，切忌把「mode=block 永遠必擋」當成不可變契約。Mismatch 日誌、action／filter side effects 應只驗證必要特性，避免脆弱的全文 log snapshot。

> 核心評估標準：能否用少量可信的測試**捕捉 history 提前 slice、API key early return 忘記 release、block／audit 判斷錯置**等高風險回歸，而不是僅證明函數存在。

### P0b — 次優先、依 Gate 0 成本選擇

- [ ] 管理員 `/debug_mcp` 的特殊 args 提前返回（在 lock 前）；非管理員 `/debug_mcp` 不得進入管理員路徑。
- [ ] `cur_num`、visitor cookie、站長預設值與人格選擇之基本對應；已有 `RequestUkagakaTest.php` 的低階測試，controller 不必重做完整 fixture。
- [ ] 若模擬 provider／prompt 需要大量全域 stub，改用一個最小 WP/Playground case 或先記錄缺口，不強制塞進 P0a。

P0b 卡住不能阻止高品質 P0a 獨立成立；必要時拆成另一個 test-only PR，仍需要開發者核准。

### P1 — 可選的延伸／整合測試

- [ ] Unicode、日文／繁體中文 500 字元截斷與頁面 context 限制。
- [ ] JSON `user_chat()` 成功／異常時 shutdown 登記與 `finally` release（**在 caller 層測**）。
- [ ] SSE `user_chat_stream()` 的多個退出分支、stream abort、error 與 release（**在 caller 層／受控獨立進程或 WP 整合測**，不要用會直接 `exit` 的方法硬跑同一 PHPUnit process）。
- [ ] 語言／時間／人格 System Prompt、dynamic prompts，避免將隨機內容或即時時間全文作 golden snapshot。
- [ ] `MPU_Observation_Buffer::drain()` 的調用時機和「只消耗一次」副作用，以小型受控觀察測試或 integration 驗證。
- [ ] `chat_context()`／`chat_greet()` 與既有 `prepare_auto_chat_context()` 的共享行為：只有有真實維護痛點才擴充。
- [ ] WordPress filter 對 integrity 阻擋決策的 override，前提是可隔離測試狀態。

### PR 1 嚴格界線

允許調整僅與測試有關的檔案（經 Gate 0 核准）：

```text
tests/Unit/*
tests/support/* 或既有相容的 test-support 位置
tests/bootstrap.php（只有經過相依盤點、確認不會污染其他測試時）
tools/e2e/*（僅在選擇 WP/Playground 路線且事前批准）
```

**禁止修改** production PHP／JS、REST route/status/payload、cookie、option schema、LLM provider、SVG/CSS、build pipeline。若測試揭露 bug，記錄並另立 issue／bug-fix PR，**不可修在測試 PR**。

新測試需能獨立、不同順序運行，並驗證關鍵模組真的被載入。若 harness 衝突、複雜度失控或需改 production 可測性，立即停下來交開發者裁決。

**完成 PR 1 後停止，提交成本、測試回歸捕獲能力與測試環境品質評估。**

---

## 4. PR 2 — 條件式、最小規模重構（不是必交成果）

只有下列條件同時成立才向開發者提出 PR 2：

1. PR 1 已獨立驗收，tests 真正測到關鍵契約；
2. 有 commit／issue／修改痛點證據證明重構有持續收益；
3. 可以在 **同一個 `MPU_REST_Chat` class** 內抽出一個責任清楚、低副作用的 private helper；
4. 不需新增跨 class 抽象層、DTO、Service Container，且 diff 易還原；
5. 開發者再次**明確批准**。

首選只評估 `prepare_user_chat_args()` 裡的**純計算區塊**，例如 keyword flags（統計／系統／外掛／主題關鍵字）或一小段 prompt block 組裝。不可先碰：

```text
session/token/rate-limit 的先後順序
history normalize → orphan filter → full integrity → -20 context
MPU_Chat_Lock acquire/verify/early release 與 callers' release
MPU_Observation_Buffer::drain() 的副作用時間點
JSON/SSE frame、error status、lock cleanup、provider transport
角色切換 generation/retry、gift/touch、對話表情與睡眠
```

一次抽一段；只做行為等價的搬移、不重新命名對外 API、不順手修 bug。若 helper 需要傳入大量隱藏狀態或反而增加認知負擔，**NO-GO 即停止**。

---

## 5. 其餘候選的預設處置

### A1 Observation — HOLD／偏 NO-GO

`js/ukagaka-core.js` 的 Observation 函數群約 118 行；`js/ukagaka-features.js` 的 SPA navigation 亦會呼叫 `mpuInitObservationTracking()`。其 `beforeunload` cleanup、ready 初始化、post ID／timer、403 retry 彼此關聯。若近期無擴充或測試隔離痛點，拆出來的主要收益只剩「檔案變短」。

**本輪不抽檔、不預設補 Observation frontend tests。** 日後若要改，另案評估 singular/archive、重複初始化、SPA 換 post、舊 timer 清理、token 403 refresh、ready/beforeunload lifecycle，並同步維護 JS source enqueue 與 bundle。

### A2 Security Events — NO-GO

`mpu_checkSpamEvent()` 涉及 auto-talk、checksum、history、動畫／打字機及 request。抽檔只是把耦合搬走，暫不拆。

### C Ghost ZIP — Later

Ghost ZIP 安裝／覆蓋／清理與安全驗證有獨立邊界，但是否值得拆取決於實際需求。若未來改進，應先測 ZIP Slip、文件限制、overwrite、rollback 與 include order；**不混入 REST Chat 工作**。

---

## 6. 禁止混入本次 PR 的變更

本輪不碰：

```text
mpu_nextmsg / mpu_nextmsg_fallback / auto-talk queue
mpuChange / mpuCharacterGeneration / change tokens / retry chain
loadExternalDialog / TXT-JSON emotion parser / dialogue indexes
SSE parser / frames / transport / streaming protocol
chat history checksum type allowlist、store/verify 語意、session schema
gift transaction、touch/decorations、cooldown、emoji loading
多 Ghost /init config 同步與 /change cookie 邊界
AVG dialog DOM、CSS 主題、SVG geometry、動畫時序
3 個待補 log keys（留一般維護，不混 PR）
```

如果 tests 揭露 bug，只回報 reproduction／風險與建議單獨 PR，不在本輪修正。

---

## 7. 測試、驗收與 build 注意事項

### Mandatory（直接受影響）

- Phase A：可以只做唯讀檢查及必要的基線命令，不要因為分析就修改檔案。
- PR 1a：新增的 P0a tests 必須獨立穩定；既有 `ChatLockTest`、`ChatIntegrityTest`、`RequestUkagakaTest` 等相關測試仍通過。
- PR 1b／PR 2（若另行批准）：測試與已確認的行為基線一致，且模組載入與副作用沒有偷偷漂移。
- 報告需標注 **PASS／FAIL／NOT RUN**，不能用 `verify` 綠燈聲稱已完成未執行的 E2E。

### Regression smoke

```bash
npm --prefix tools/node run verify
```

`verify` 現有執行順序為多數 Node smoke tests 先執行、build 接近尾端、PHP tests 在 build 後。**若新增測試直接讀 `js/dist`，可能讀到舊 bundle**。應測 source，或顯式 `build → bundle parity tests`；純 PHP tests PR 不必額外拆 JS。

### Risk-based E2E（有相關改動才跑）

```bash
npm --prefix tools/node run test:interaction
```

- 純 PHP controller tests PR 不強制跑完整 E2E，但如選 WP/Playground integration 路線，需跑直接相關的 REST Chat case。
- PR 2 若改了 prompt 或 input processing，要跑 chat JSON、SSE、checksum、lock 與角色選擇相關情境；高風險擴散才跑完整 suite。
- JS 拆檔（若將來另案批准）才要求 `SCRIPT_DEBUG=true` 的逐檔 enqueue 與 `SCRIPT_DEBUG=false` 的 `tools/node/build.js` `coreFiles` 雙模式驗證。

---

## 8. Phase A 報告格式（只交一份，不重複）

### 8.1 Baseline

- Branch／HEAD／version／working tree；測試環境與當前失敗項。
- 本次檢查過哪些檔案、函數範圍、callers 與期間。

### 8.2 Maintenance evidence

| 候選 | 半年 file commits | 半年函數／行範圍 edits | 實際修改原因／痛點 | 建議 |
| --- | ---: | ---: | --- | --- |
| B `prepare_user_chat_args()` | 待實測 | 待實測 | 待實測 | TESTS FIRST（可推翻） |
| `chat_context()`／`chat_greet()` | 同檔 | 待實測 | 先查既有共用 helper | HOLD |
| A1 Observation | 待實測 | 待實測 | SPA caller／低改動頻率 | HOLD／NO-GO |
| A2 Security Events | 待實測 | 待實測 | 高耦合 | NO-GO |
| C Ghost ZIP | 待實測 | 待實測 | 使用需求低 | Later |

如果 Git 無法準確統計，寫 `N/A` 加原因，禁止造數字。

### 8.3 Control-flow / side-effect map

- `prepare_user_chat_args()` 的 early return 與狀態圖：acquire 前後／status／release 誰做。
- **`audit`、`warn`、`block`** 的差異與 filter 覆寫；lock 測試分 prepare 與 caller。
- normalize、full integrity、20-message context、verify 的順序。
- `MPU_Observation_Buffer::drain()` 的條件／時機。
- `chat_context()`／`chat_greet()` 的既有共用點與仍有差異之處。

### 8.4 Gate 0 Test harness 可行性（最重要）

請具體列出：

- 需要新增／載入的 class／function stub 清單與測試隔離方式。
- 兩種以上方案比較：minimal PHPUnit controller、WP/Playground integration、或兩者混合。
- 對 `tests/bootstrap.php`、`SpamEventResponseTest.php` 及其他既有測試的相容性影響。
- 哪些 production 正常路徑確實被測到，哪些只是 fallback／mock；如何避免假綠燈。
- 預估新增測試行數、fixture 數、CI 執行時間／穩定性、清理負擔；若無法可靠估算，明說未知。
- 建議 PR 1a 的 **最小可驗收 case 清單**、PR 1b/P1 optional 清單、未覆蓋風險。

### 8.5 GO / HOLD / NO-GO 與停止點

- Phase A 是否建議開始 PR 1a？原因與替代方案。
- 測試環境成本是否過高？若高，應縮小或停止，非強制實作。
- 是否有足夠實證主張 PR 2？只列條件，**不代表授權**。
- 明確說明本輪不該做的事。
- **此處停止，等待使用者批准。**

---

## 9. 最終驗收與操作原則

- **Phase A 做得好，即使沒有任何 code diff，也是合格交付。**
- PR 1（或 PR 1a）只寫測試，不動 production。新的測試應能抓住至少一種真實回歸，而不只掃原始碼字串。
- 如果 test harness 的複雜性比 controller 原始碼問題更大，應回報 HOLD，不打造長期需維護的假 WordPress。
- PR 1 後停下來，開發者另行決定是否要做 PR 2。
- 即使最後只有 characterization tests 而完全不重構，也是符合目標的成功結果。

> **結論：`Phase A（唯讀＋Gate 0 可行性） → 開發者批准 → PR 1a（最小 REST Chat tests） → 停止 → PR 1b／PR 2 僅於另行批准時執行`。**
>
> 「容易拆」≠「值得拆」；「可以寫測試」≠「值得模擬整套 WordPress」。
