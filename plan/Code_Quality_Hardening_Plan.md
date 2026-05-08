# MP Ukagaka Code Quality Hardening Plan

本文整理目前外掛代碼的嚴格評估與改善計畫，供 Claude / Gemini / Codex 交叉 review。目標不是一次大改，而是用可驗證、可回退的小步驟，把目前「可運作、有安全意識」的代碼提升到更穩定、可維護、可長期擴張的狀態。

## Current Verdict

目前代碼整體是 OK 的：主 bootstrap 清楚、REST controller 已集中化、AI provider 已有抽象層、安全檢查也不是事後補丁式拼湊。`php -l` 全 PHP 檔通過，沒有語法層級問題。

但它還不到「很乾淨、很穩、可放心快速擴張」的狀態。主要風險集中在三個方向：

1. 公開 REST AI 端點可能被外部直接消耗 API quota。
2. 管理員自訂 JS、ZIP 上傳覆蓋等高權限功能安全邊界偏寬。
3. 部分核心檔案過大，職責混雜，後續修改成本會快速上升。

## Principles

- 不做一次性大重構，避免破壞既有前端行為、REST response shape、checksum 流程與多 provider 相容性。
- 優先處理安全與成本風險，再處理編排與可維護性。
- 所有變更都要有明確驗證：PHP lint、JS build、REST smoke test、前台基本互動測試。
- 保持向後相容，尤其是 REST error code、前端依賴的 JSON 欄位、cookie 名稱、dialog/personality 檔案格式。
- 結構拆分採漸進策略：先抽出 helper function 或單一 service，確認行為不變後，再決定是否搬到多個 class 檔案。

## Review Feedback Integration

Claude 與 Gemini 的 review 共識如下，已整合進後續 phase：

1. 公開 AI REST endpoint 的防禦應以「前台初始化 token / session token」為主，而不是只依賴 WordPress nonce。原因是匿名訪客與登入使用者的 session 模型不同，冷 request 直接打 REST 時，頁面初始化 token 搭配 transient 驗證更穩。
2. Turnstile 不應成為每次聊天的基本門檻；它更適合作為高頻、異常、或匿名濫用時的升級驗證。
3. `js_area` 應保留，但保存與輸出 raw JS 應要求 `unfiltered_html`。在單站 WordPress 中，這通常不會比 `manage_options` 縮小太多權限範圍；主要價值在 multisite 與更細權限模型。
4. ZIP 覆蓋既有 personality 不應永久禁止，因為更新 personality 是合理需求；但必須保護內建/保留 ID，且覆蓋非保留 ID 時要求二次確認與專屬 nonce。
5. Chat controller 拆分先處理 history/checksum，因為它最獨立、最容易測試，也最容易造成行為回歸。
6. PHPUnit 不急著全量導入；先建立 lightweight smoke scripts 與 `php -l`/build gate。PHPCS 可較早加入，但若既有代碼風格差異大，應先設 baseline 或只檢查新改檔案。

## Phase 1: Security And Abuse Hardening

### 1. Public AI REST Endpoint Guard

目標檔案：

- `includes/rest/class-mpu-rest-chat.php`
- `includes/core/frontend-functions.php`
- 可能涉及 `js/ukagaka-chat.js`、`js/ukagaka-context.js`、`js/ukagaka-greeting.js`

目前狀態：

- `/chat/context`
- `/chat/greet`
- `/chat/user`
- `/chat/user-stream`

這些端點使用 `permission_callback => '__return_true'`，符合前台訪客互動需求，但也代表任何人可以直接對 REST endpoint 發 request。雖已有 rate limit、長度限制與 history checksum，仍可能造成 API quota 消耗。

建議方案：

1. 保留公開端點，不要求登入。
2. 前台頁面初始化時產生一次性或短 TTL 的 session token，寫入 transient，並嵌入前端設定。
3. Chat/Greet/Context/SSE request 必須帶 token；後端驗證 token、session id、IP hash 或 visitor key 的合理關聯。
4. WordPress REST nonce 可繼續用於登入使用者與既有 nonce refresh 流程，但匿名濫用防禦以 session token 為主。
5. 對匿名訪客與登入管理員採不同配額。
6. 對 AI provider 消耗型 endpoint 設更嚴格的 per-session + per-IP 雙層限制。
7. 若 Turnstile 已啟用，只在高頻、異常、或配額接近上限時要求通過 Turnstile，不作為每次請求的硬性前置。

驗證：

- 前台正常載入角色。
- 首次 greet 可用。
- 一般訪客聊天可用。
- 管理員 `/debug_mcp` 邏輯不變。
- 無 token 或偽造 token 的直接 REST request 被拒絕或降級。
- SSE endpoint 仍可正常完成並刷新 nonce。
- 匿名訪客重新整理頁面後取得新 token，正常互動不中斷。
- 高頻匿名請求能被 throttle，必要時觸發 Turnstile challenge。

### 2. Restrict Raw JavaScript Extension

目標檔案：

- `includes/admin-functions.php`
- `includes/core/frontend-functions.php`
- `options/options_extend.php`

目前狀態：

- `extend[js_area]` 由 `manage_options` 管理者保存。
- 前台直接輸出到 `<script>`。

風險：

- 這是刻意的自訂 JS 功能，可以接受。
- 但在 WordPress 權限模型裡，未必所有 `manage_options` 使用者都應能輸出 raw JS。

建議方案：

1. 保存與顯示 raw JS 時要求 `current_user_can('unfiltered_html')`。
2. 沒有 `unfiltered_html` 權限時，保留欄位但禁用保存，或清楚顯示權限不足。
3. 多站點環境下特別小心，因為 `unfiltered_html` 通常更受限制。
4. 在設定頁註明此功能會輸出 raw frontend JavaScript，僅適合可信任管理者使用。

實作備註：

- 單站環境中，`manage_options` 使用者通常也具備 `unfiltered_html`，所以此變更主要強化 multisite 與自訂角色環境。
- 不應在沒有 `unfiltered_html` 權限時自動清空既有 `js_area`，避免權限或站點模式切換造成資料遺失。

驗證：

- 一般單站管理員可保存自訂 JS。
- 無 `unfiltered_html` 的使用者不能保存 raw JS。
- 既有已保存 JS 不被誤刪，除非使用者明確保存空值。

### 3. Safer Ghost ZIP Upload And Overwrite

目標檔案：

- `includes/admin-functions.php`

目前狀態：

- ZIP 有副檔名白名單、Zip Slip 檢查、檔案數量上限。
- 若 manifest id 對應目錄已存在，會刪除後覆蓋。

風險：

- 管理員操作仍可能意外覆蓋內建或現有 personality。
- `extractTo()` 依賴前置驗證，最好補 extraction 後檢查。

建議方案：

1. 拒絕覆蓋保留 ID，例如 `Frieren`、`default_1` 對應 personality。
2. 既有目錄存在時，要求明確 `confirm_overwrite` nonce/action。
3. 解壓後再掃一次實際檔案，確認仍在 target dir 內。
4. 可考慮先解壓到暫存目錄，驗證完整後再 move 到正式 personality 目錄。
5. 最終方案優先採「暫存目錄 -> 驗證 -> 原子式替換/搬移」，避免失敗後留下半套 personality。

驗證：

- 正常新 personality ZIP 可上傳。
- 含 `../`、絕對路徑、非法副檔名、過多檔案的 ZIP 被拒絕。
- 覆蓋既有 ID 時需二次確認。
- 失敗時不留下半套 personality 目錄。

## Phase 2: Structural Refactor

### 4. Split Chat Controller Responsibilities

目標檔案：

- `includes/rest/class-mpu-rest-chat.php`

目前問題：

- 檔案約 1164 行。
- 同時處理 REST routing、input normalization、prompt building、history checksum、provider call、debug command、SSE 收尾。

建議拆分：

- 第一步：先抽出 history/checksum helper 或 `MPU_Chat_History_Service`，集中 verify/store/slice/normalize 行為。
- 第二步：抽出 request normalization helper，集中 message/history/session/page context 的 sanitize 與長度限制。
- 第三步：再評估 prompt builder 是否值得獨立成檔，避免過早拆出過多 class。
- 第四步：只有當 response finalization 重複明顯時，才抽出 response finalizer。

原本可選的最終檔案形態：

- `includes/chat/class-mpu-chat-history-service.php`
- `includes/chat/class-mpu-chat-request-normalizer.php`
- `includes/chat/class-mpu-chat-prompt-builder.php`
- `includes/chat/class-mpu-chat-response-finalizer.php`

但不要求一次完成。第一個 commit 應只搬最獨立的 history/checksum 邏輯，並保持 controller 對外行為完全不變。

驗證：

- `/chat/user` response shape 不變。
- `/chat/user-stream` SSE event 名稱與順序不變。
- checksum verify/store 與前端歷史仍一致。
- `/debug_mcp` 管理員限定不變。

### 5. Split Admin Save Handler

目標檔案：

- `includes/admin-functions.php`

目前問題：

- `mpu_handle_options_save()` 承擔所有設定頁提交。
- AI、LLM、日記、Bot blocker、ZIP、一般設定都在同一函式。

建議拆分：

- 第一步：在同檔或鄰近檔案中先抽出具名 helper function，例如 `mpu_save_general_settings()`、`mpu_save_ai_settings()`、`mpu_save_llm_settings()`。
- 第二步：將 ZIP 上傳/驗證/解壓獨立成 `GhostZipService` 或一組 `mpu_ghost_zip_*` helper，因為它是安全邊界最清楚的子領域。
- 第三步：若 helper 穩定後仍需要更清楚的 ownership，再引入 class router/saver。

原本可選的最終檔案形態：

- `includes/admin/class-mpu-admin-options-save-router.php`
- `includes/admin/savers/class-mpu-general-settings-saver.php`
- `includes/admin/savers/class-mpu-ai-settings-saver.php`
- `includes/admin/savers/class-mpu-llm-settings-saver.php`
- `includes/admin/savers/class-mpu-diary-settings-saver.php`
- `includes/admin/savers/class-mpu-bot-blocker-settings-saver.php`
- `includes/admin/class-mpu-ghost-zip-service.php`

但第一輪不應一次建立 6 個 saver class。重點是降低 `mpu_handle_options_save()` 的分支複雜度。

驗證：

- 每個設定 tab 保存後仍 redirect 回原 tab。
- nonce/capability 行為不變。
- API key 加密保存不回退成明文。
- reset setting 行為不誤刪人格與必要預設。

### 6. Move Utility File Toward Domain Helpers

目標檔案：

- `includes/core/utility-functions.php`

目前問題：

- 同時包含字串、檔案、加密、WP info、IP、HTTP API、rate limit、REST nonce refresh、prompt helper。

建議拆分：

- `includes/core/file-functions.php`
- `includes/core/crypto-functions.php`
- `includes/core/network-functions.php`
- `includes/core/rate-limit-functions.php`
- `includes/core/wp-info-functions.php`

注意：

- 拆分時要非常小心 `mp-ukagaka.php` 的載入順序。
- 優先保留原函式名稱，避免破壞外部相容性。
- 這是 Phase 2 中風險最高的一項，應排在 Chat/Admin 拆分之後，且必須先有 PHP lint gate。

## Phase 3: Verification And Tooling

### 7. Add Minimal Test Gates

目前狀態：

- `package.json` 的 `test` 是 placeholder。
- 沒有 PHPUnit / PHPCS / REST smoke test。

建議：

1. 新增 `npm run lint:php`，在 Windows 下可執行全部 `php -l`。
2. 新增 `npm run build` 已存在，確保 dist bundle 可重建。
3. 補一份 REST smoke checklist 或可執行腳本。
4. PHPCS 可早期導入，但建議先設 baseline 或只檢查本次修改檔案，避免既有風格一次爆量。
5. PHPUnit / Brain Monkey 先針對新抽出的 service 補測，例如 chat history/checksum；不要求一開始覆蓋整個 WordPress 外掛。

最低驗證命令：

```powershell
Get-ChildItem -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }
npm run build
git diff --check
```

### 8. Release Discipline

建議每個 phase 都獨立 commit：

1. `Harden public chat REST access`
2. `Restrict raw frontend script customization`
3. `Harden ghost ZIP overwrite flow`
4. `Extract chat request services`
5. `Split admin option save handlers`
6. `Add project verification scripts`

每個 commit 應包含：

- 修改摘要。
- 行為是否相容。
- 測試命令與結果。
- 若有 REST response 變動，列出欄位差異。

## Questions For Claude And Gemini

已收到 Claude 與 Gemini review，整合後決策如下：

1. 公開 AI REST endpoint：前台 transient/session token 優先；WP nonce 保留給登入與既有流程；Turnstile 作為高頻升級驗證。
2. `js_area`：保留，但限制 `unfiltered_html`，並註明主要強化 multisite/自訂角色場景。
3. ZIP 覆蓋：保護內建/保留 ID；非保留 ID 允許二次確認後覆蓋。
4. Chat controller：先拆 history/checksum service 或 helper，再拆 request normalization，prompt builder 放第二階段。
5. 測試工具：先 smoke scripts + PHP lint + build；PHPCS 可早期加入但需 baseline；PHPUnit 只針對新 service 按需導入。

## Recommended Order

最建議順序：

1. Public AI REST 防濫用。
2. Raw JS 權限收緊。
3. ZIP 覆蓋流程收緊。
4. 補 PHP lint / build / smoke scripts，作為後續拆分的安全網。
5. Chat history/checksum helper/service 拆分。
6. Admin save handler 先抽 helper，再評估 class 化。
7. Utility functions 最後拆分。

原因：前三項是風險控制，後三項是維護成本控制。先降低外部風險，再處理內部結構，整體回報最高。

---

## v2.15 Implementation Log

### Implemented — 2026-05-08

Phase 1（Security）與 Phase 2（Structural）的核心項目已在 commit `26eb4cb` 一次實作完成。12 個檔案，+793 / −463 行。

#### Phase 1: Security

| # | 項目 | 狀態 | 修改檔案 |
|---|---|:---:|---|
| 1 | Session Token（IP-bound） | ✅ | `utility-functions.php`, `class-mpu-rest-chat.php`, `frontend-functions.php`, `ukagaka-base.js`, `ukagaka-chat.js` |
| 2 | Raw JS save gate (`unfiltered_html`) | ✅ | `admin-functions.php`, `options_extend.php` |
| 3 | ZIP overwrite: backup→rename→rollback | ✅ | `admin-functions.php`, `options_create.php` |
| 3a | Reserved ghost IDs (case-insensitive) | ✅ | `admin-functions.php` |

**Session Token 設計摘要：**

- `mpu_generate_session_token()`: `random_bytes(16)` → 128-bit token，與 `md5(IP + wp_salt('auth'))` 綁定，TTL 2 小時。
- `GET /session-token` 端點：10/60s per-IP rate limit，`Cache-Control: private, no-store, no-cache`。
- 前端不嵌入 token（`var mpuSessionToken = null`），由 `mpuEnsureSessionToken()` lazy-fetch，Promise 快取避免重複請求。
- `check_session_token()`: 登入使用者豁免；匿名訪客需 header `X-MPU-Session-Token` 或 param `session_token`。
- 所有 chat 端點（context / greet / user / user-stream）均加上 token 驗證，缺少/無效統一回 403。

**ZIP Overwrite 設計摘要：**

- 解壓到 `_tmp_{ghost_id}_{uniqid}` 暫存目錄，失敗時 `mpu_recursive_rmdir` 清理。
- 解壓後 `realpath()` 二次掃描，確認所有檔案在暫存目錄內（防 symlink 逃逸）。
- 保留 ID（Frieren, default_1）大小寫不敏感拒絕覆蓋。
- 既有目錄存在時存 transient、轉跳確認頁，管理員確認後原子搬移：`target→backup` → `temp→target` → 清除 backup。

#### Phase 2: Structural

| # | 項目 | 狀態 | 修改檔案 |
|---|---|:---:|---|
| 4 | `MPU_Chat_History_Service` | ✅ | `class-mpu-chat-history-service.php`（新增 152L）, `bootstrap.php`, `class-mpu-rest-chat.php` |
| 5 | `mpu_handle_options_save()` 拆分 | ✅ | `admin-functions.php` |

**Chat History Service：** 5 個 static method（`get_session_id`, `parse_history_from_request`, `verify`, `store_after_auto`, `store_after_user_chat`），消除 controller 中 4 處重複的 integrity 邏輯。

**Options Save Helpers：** 6 個命名函式（`mpu_save_general_settings`, `mpu_save_ukagaka_settings`, `mpu_save_ai_settings`, `mpu_save_llm_settings`, `mpu_save_diary_settings`, `mpu_save_bot_blocker_settings`），統一簽名 `(array &$mpu_opt): string`。

### Post-Review Fixes — 2026-05-08

⚠️ 校正紀錄：commit `26eb4cb` 的 log 曾將下表 #1 / #2 / #4 標為 ✅，
但實際程式碼並未包含對應修補。Codex 二次 review（2026-05-08）抓到後，
於 commit `db26b7a`（#4，Medium）與後續 commit（#1 / #2，Low）一起補上。

| # | Issue | 修正 | 說明 |
|---|---|:---:|---|
| 1 | `mpuFetch` 呼叫 `mpuEnsureSessionToken()` 缺少 `typeof` 防護 | ✅ | 補上 `typeof mpuEnsureSessionToken === 'function' ? ...`，與 `mpuFetchSSE` 一致 |
| 2 | `store_after_user_chat` 的 docblock 只寫 `$dedup_user` | ✅ | 補上 `$session_id` / `$prior_history` / `$user_message` / `$assistant_reply` 與何時傳 `false` 的使用情境 |
| 3 | `mpu_save_general_settings` 與 `mpu_save_ai_settings` 的雙向保留邏輯 | — | Pre-existing tech debt，未改。未來可考慮在 `mpu_handle_options_save()` 層級統一處理 |
| 4 | `submit_confirm_ghost_overwrite` 的 `$temp_dir` 缺少 `realpath()` 驗證 | ✅ | 比照 `mpu_extract_ghost_zip()` 既有 pattern（`admin-functions.php:888-899`），確認 `temp_dir` realpath 在 `ghost_dir` 內，否則清理 transient 並回錯 |

### Remaining Items

| Phase | 項目 | 狀態 |
|---|---|:---:|
| 2 | Request normalizer 拆分 | 📌 未開始 |
| 2 | Utility functions 領域拆分 | 📌 未開始 |
| 3 | PHP lint / build gate 腳本 | 📌 未開始 |
| 3 | REST smoke test | 📌 未開始 |
| 3 | PHPCS baseline | 📌 未開始 |

### Follow-up Memo — 2026-05-08

v2.15 release 本身已完整，剩餘項目不需要連著做，應視為下次大改或發版前的安全網工作，而不是本次 release blocker。

| 項目 | Plan 內定位 | 備忘 |
|---|---|---|
| Phase 3 工具鏈（PHP lint / build / smoke scripts、PHPCS baseline） | Recommended Order #4，作為後續拆分的安全網 | ROI 最高的下一步。下次再動 PHP 前，先把目前已驗過的 `php -l` 與 `cmd /c npm run build` 包成 `npm run lint:php` / build gate，再補一份 REST smoke checklist。 |
| Utility functions 領域拆分 | Plan 自註：Phase 2 中風險最高，應排在最後，且必須先有 PHP lint gate | 不急。等 lint/build gate 先存在，再考慮拆分，避免低價值搬移造成回歸。 |
| Chat controller 進階拆分（request normalizer / prompt builder） | Plan 自註：不要求一次完成 | Nice-to-have。只有再次大改 chat 流程、prompt 組裝或 REST request normalization 時才值得順手做。 |
| Pre-existing i18n debt（約 94 字串） | 本 plan 原本未列入，為本次 i18n 掃描發現 | 與 Phase 0 / v2.15 hardening 無直接關係。下次發版前清掉會比較整齊，但不影響本次 release。 |

結論：v2.15 到此可以告一段落。後續優先順序是「先工具鏈安全網，再結構拆分」，不要為了完成 plan 而連續做低回報重構。
