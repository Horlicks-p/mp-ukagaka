# Phase A 報告 — REST Chat 測試可行性與重構評估

> 依據：`plan/mp_ukagaka_conservative_refactor_evaluation_plan_v3.md`（含 2026-10-13 對 P0a fixture 與 Integrity 分層的兩處修訂）
> 執行者：Claude Code ／ 日期：2026-10-13 ／ 性質：唯讀評估，未修改任何 production code 或測試

---

## 8.1 Baseline

| 項目 | 值 |
| --- | --- |
| Branch / HEAD | `main` / `afadcdd8419611b05c0bcf925b6d5b9b406bcf97` |
| Version | 2.37.4（`mp-ukagaka.php:6`） |
| Working tree | 乾淨；只有本計畫 v3 與本報告是 untracked |
| PHP | 8.5.0 CLI（NTS, x64） |
| PHPUnit baseline | **PASS**：159 tests / 1212 assertions / 5.9 s |
| `npm run verify` | **NOT RUN**（本輪只需 PHPUnit baseline） |
| E2E `test:interaction` | **NOT RUN**（PR 1 為純 PHP 測試，不在本輪必要範圍） |
| Mutation 驗證 | **NOT RUN**（屬 PR 1 範圍） |

**可行性探針**：在 scratchpad（repo 之外）寫了一次性腳本，直接 require `tests/bootstrap.php` 與 production 模組，透過 subclass 呼叫 `prepare_user_chat_args()`。執行前後 `git status` 不變，`logs/` 內沒有新增檔案。

檢查範圍：`includes/rest/class-mpu-rest-chat.php` 全檔、`class-mpu-rest-base.php`、`chat-integrity.php`、`class-mpu-chat-history-service.php`、`class-mpu-chat-lock.php`、`tests/bootstrap.php`、`tests/Unit/*`、`tools/node/test-chat-integrity-window-smoke.js`。

---

## 8.2 Maintenance evidence

採樣期間：**2026-04-13 ～ 2026-10-13**，排除 merge commit。行範圍統計用 `git log -L <起>,<訖>`，會跟隨行號漂移，但仍可能因 rename 或大規模重排失準，數字只能當**線索**。另外，`git log -L :prepare_user_chat_args:…` 實測回傳 `no match`，與 v3 的預期相符。

| 候選 | 半年 file commits | 半年行範圍 commits | 實際修改原因 | 建議 |
| --- | ---: | ---: | --- | --- |
| B `prepare_user_chat_args()`（L518–950） | 23（全檔） | **17** | 見下方分類 | **TESTS FIRST → GO（PR 1a）** |
| `chat_context()`（L136–362） | 同檔 | 5 | 功能擴充與機械性修改 | HOLD |
| `chat_greet()`（L363–517） | 同檔 | 4 | 功能擴充與機械性修改 | HOLD |
| JSON/SSE callers（L1082–1292） | 同檔 | 11 | SSE 錯誤處理、emotion parser、lock | 不在本輪範圍 |
| A1 Observation（core L114–231） | 26（全檔） | **2** | 功能新增（`0c7229b`）、SPA 修正（`f7cdfdb`），之後再沒動過 | **NO-GO** |
| A2 `mpu_checkSpamEvent()`（core L435–561） | 26（全檔） | 4 | emotion tag、checksum、i18n、安全強化 | **NO-GO** |
| C Ghost ZIP（admin L797–1156） | 12（全檔） | 3 | 路徑邊界修正、hardening、i18n | **Later** |

### B 的 17 次修改分類

| 類型 | Commits（範圍內 +/− 行數） |
| --- | --- |
| **history/integrity/checksum 的 bug fix** | `8cac617`（+5/−2，**修的正是「slice 太早」**）、`195bb27`（+8/−27，allowlist 收斂到 service）、`89a45e4`（+2/−10，orphan filter 收斂到 service）、`1bcc804`（+7/−5，debug_mcp args 漏了 `integrity_history`） |
| **`/debug_mcp` 早退的 bug fix** | `6ea2ee0`（+10/−3，early return 早於 normalize） |
| **角色與語言決議的 bug fix** | `90a2d85`（+1/−1，cur_num）、`5241591`（+8/−2，language fallback） |
| 功能擴充 | `0aa04a2` lock（+23）、`34ff992` role resolver（+14/−1）、`e58b543` gift（+11/−2）、`0c7229b` 與 `3fdc736` observation（+7、+7）、`1956606` runtime state（+2） |
| 機械性修改 | `26eb4cb` hardening（+6/−9）、`944960a` 拒絕文案、`8715d4d` model ID、`3542300` i18n |

**判讀**

- 修改**頻繁但幅度很小**：17 次中有 13 次在範圍內的增刪合計 ≤ 14 行，最大一次是 `195bb27` 的 35 行（多為刪除）。
- 修改**集中在 L597–690**，也就是 history、integrity、debug_mcp、lock 這段最敏感的區域。
- 半年內至少 **7 次是 bug fix**，其中 5 次落在 history/integrity/debug_mcp。

這說明痛點是「**常改、易錯、沒有 controller 層的測試網**」，而不是「方法太長，改不動」。結論是先補測試，而不是先抽 helper。

> A2 更正：最初的範圍 L435–643 誤含相鄰函數，曾得出 6 次；以函數實際結尾 L561 重新統計後為 4 次，結論不變。

---

## 8.3 Control-flow / side-effect map

### `prepare_user_chat_args()` 早退表（已逐行核對 HEAD）

| # | 行 | 情境 | 相對於取鎖 | 回傳型別 / status | 誰釋放鎖 | 其他副作用 |
| --- | --- | --- | --- | --- | --- | --- |
| 1 | 520 | session token 缺失或無效（已登入者會略過檢查） | 之前 | **`WP_REST_Response`** 403，`code=missing_session_token` | 無鎖 | 無 |
| 2 | 524 | rate limit（`user_chat`，30 次 / 60 秒） | 之前 | **`WP_REST_Response`**（由 `mpu_rest_check_rate_limit` 決定） | 無鎖 | 計數 transient |
| 3 | 541 | 空訊息 | 之前 | `WP_Error` 400 `rest_error` | 無鎖 | 已呼叫 `mpu_set_request_input_context` |
| 4 | 634 | 管理員 `/debug_mcp` | **之前**（位於 normalize 與 slice 之後） | args 陣列，`chat_lock=null`，含 `integrity_history` | 無鎖 | 無 |
| 5 | 663 | lock busy | `acquire()` 回傳錯誤 | `WP_Error`，取 error data 的 status（預設 429） | **不釋放**（鎖屬於別人；探針已確認原鎖仍在） | 無 |
| 6 | 667–673 | integrity：`block`，或被 filter 判定為阻擋 | 之後 | `WP_Error`，取 error data 的 status（mismatch 為 409） | **prepare 釋放**（L669） | mismatch log、`mpu_chat_integrity_mismatch` action |
| 6′ | 667 | integrity：`audit`／`warn` | 之後 | 繼續往下 | — | **同樣會寫** mismatch log 與觸發 action |
| 7 | 689–692 | 非 Ollama 且沒有 API key | 之後 | `WP_Error` 400 | **prepare 釋放**（L690） | 無 |
| 8 | 928 | 成功 | 之後（或沒有 session ID，不取鎖） | args 陣列，鎖在 `chat_lock` | **caller**：`user_chat()` 在 L1086 註冊 shutdown、在 `finally` 釋放；`user_chat_stream()` 在 L1152 註冊 shutdown，並於各個 `exit` 前釋放 | `drain()` |

取鎖條件（L646）：`$chat_session_id !== '' && class_exists('MPU_Chat_Lock')`。session ID 來自 request param `session_id` 或 `chat_session_id`，**不是 header**。

**回傳型別不一致**：#1 和 #2 回傳 `WP_REST_Response`，其他早退回傳 `WP_Error`。兩個 caller 都兩種都接受（L1085），所以測試應該**依路徑斷言具體型別**，不能一律檢查 `is_wp_error`。

**觀察到的風險（記錄，不修）**：取鎖（L659）之後到 return（L928）之間，若 personality、prompt 或 wp_info 拋出例外，鎖不會被釋放，因為 shutdown 要等 caller 拿到 args 後才註冊。鎖會在 TTL 到期後失效，不會永久鎖死：預設情況下最多殘留約 60 秒；若站點透過 `mpu_chat_lock_ttl` filter 調高，最多可能到 300 秒（`MPU_Chat_Lock::ttl()` 限制在 10–300 秒）。目前沒有重現案例，建議另開 issue 評估，**不併入測試 PR**。

### 資料流

```text
history param → json_decode
  → MPU_Chat_History_Service::normalize_history()     (L617，type allowlist 與內容清理)
  → filter_orphan_assistants()                         (L625)
  → $integrity_history = 完整 normalized               (L629)
  → $chat_history = array_slice(-20)                   (L632，只給 LLM 用)
  → [#4 /debug_mcp return]
  → MPU_Chat_Lock::acquire()                           (L659)
  → verify(session, $integrity_history)                (L667)
       └─ 內部：slice_for_store(…, 10) = 最後 40 筆 raw → 只留 type=chat 的 assistant → 最後 10 筆
  → provider / API key                                  (L687)
  → ukagaka/personality/language/time/wp_info/system prompt  (L694–735)
  → 各 prompt block（user、page、stats、system、plugin、theme、會話、非管理員拒絕）
  → MPU_Observation_Buffer::drain()                    (L870–883)
  → implode prompt → 組 messages → max_tokens → model → return
```

- **`drain()` 的時機**：位於 API key 檢查之後，所以 #1 到 #7 的早退都**不會**消耗 buffer，只有成功路徑會。這是既有契約。
- **既有測試覆蓋**：
  - `ChatIntegrityTest` 已在 service 層覆蓋 window 與 filter 的語意（5 個 case）。
  - `ChatLockTest` 已覆蓋 lock 本身（7 個 case）。
  - controller 層只有 `test-chat-integrity-window-smoke.js` 的**原始碼字串斷言**。它**抓不到**「在 `$integrity_history` 賦值之前就先 slice」這種回歸，因為改完後字串依然存在。
  - `tests/` 裡**沒有任何測試**涵蓋 `mpu_chat_integrity_mode()`、`should_block` filter、409 或三種模式。

### `chat_context()`／`chat_greet()`／`prepare_user_chat_args()` 的重複

- `chat_context` 與 `chat_greet` 已共用 `prepare_auto_chat_context()`（L74–134），兩者之間沒有值得再抽的重複。
- **真正的重複**在 `prepare_user_chat_args()` L687–735 與 `prepare_auto_chat_context()` L84–132：provider 與 API key、cur_num、display name、personality、language code、time context、wp_info、prompt language、`$variables`、`mpu_resolve_system_prompt`，幾乎逐行相同。
- **證據顯示兩份會一起改**：`90a2d85`（L91 與 L694）和 `5241591`（L86–100 與 L710–732）都必須同時修改兩份。這是 PR 2 最有力的候選，比 v3 原本建議的 keyword flags 有價值。
- **必須保留的刻意差異**：
  - `ai_enabled` 與 `ai_greet_first_visit` 的檢查只存在於 auto 路徑。
  - user 路徑的 API key 失敗時必須釋放鎖，auto 路徑沒有鎖。
  - user 路徑的 `$variables` 多了 9 個 key：`is_child_theme`、`parent_theme`、`is_block_theme`、`site_name`、`site_description`、`is_multisite`、`active_plugins_count`、`active_plugins_list`、`slimstat_total_visits`。在 `ghost/` 與 `includes/personality/` 裡 grep 不到使用者，但管理員自訂的 prompt 可能會用到。**這只是觀察，不主張是 bug。**

---

## 8.4 Gate 0 — Test harness 可行性（實測）

### 探針結果

| 路徑 | 結果 |
| --- | --- |
| 成功路徑（provider=ollama、`wp_info` transient 預先寫入） | **跑通**：回傳完整 20 個 key；personality 解析為 `Frieren`（讀**真實** ghost 檔），system prompt 1498 字元 |
| 取鎖（給 `session_id`） | **跑通**：`chat_lock` 有值，是真實的 `MPU_Chat_Lock` |
| lock busy（同一 session 再呼叫一次） | **跑通**：`WP_Error` 429，原鎖仍在 |
| 非 Ollama 且沒有 API key | **跑通**：`WP_Error` 400 |

### 依賴盤點

| 依賴 | 已在 bootstrap？ | 真模組可載入？ | 做法 | 缺少時會走 fallback？ |
| --- | --- | --- | --- | --- |
| `WP_REST_Request`／`WP_REST_Response` | 否（`SpamEventResponseTest` 只有一個缺 `get_header`/`get_route` 的局部版本） | WP runtime 才有 | 在測試支援檔加入 superset stub（`get_param`/`get_header`/`get_route`；Response 需要 `header()`，供 rate-limit 路徑使用），可與 `class_exists` guard 共存 | — |
| `MPU_REST_Base`／`MPU_REST_Chat` | 否 | **可以**，載入時沒有副作用 | require 後用 subclass 代理 protected 方法 | — |
| History service、Chat Lock、Input Role、Observation Buffer | **是**（真實模組） | — | 用真實模組 | 有 `class_exists` guard，但會走正常分支 |
| network、wp-info、encryption、runtime-state | **是**（真實模組） | — | 用真實模組 | — |
| `core-functions.php`（`mpu_get_option`） | 否 | **可以** | 真實載入，以 `update_option('mp_ukagaka', …)` 控制設定 | — |
| `personality-loader.php`／`personality-prompts.php`／`llm-context-builder.php` | 否 | **可以** | 真實載入，讀真實 ghost 檔 | 有 `function_exists` guard，會走正常分支 |
| `request-state.php` | 否 | **可以**（載入時註冊一個 filter，bootstrap 的 stub 接得住） | 真實載入 | — |
| `debug-functions.php` | 否 | **不行**：`mpu_log_error` 與 bootstrap stub 重複宣告 | **不載入**。`mpu_debug_log` 在 L873 有 `function_exists` guard，會**走 fallback**，但只影響 debug log 的輸出。這是**明確接受的 fallback**，必須在測試中註明 | 是（無害） |
| WordPress 函數 | 部分有 | — | 測試支援檔需再補：`is_wp_error`（**必須是正確實作**）、`plugins_url`、`plugin_dir_path`、`wp_date`、`wp_get_current_user`（要回傳帶 `display_name`/`user_email`/`roles` 的物件） | — |
| `mpu_get_wordpress_info` | 是 | — | 預先寫入 transient `mpu_wordpress_info`。這是 production 本來就有的 **cache hit 路徑**，不是 mock，可避開 `$wpdb` 與 `get_plugins` | — |

### 硬性要求：每個 controller 測試都要 `@runInSeparateProcess`

1. PHPUnit 建構 suite 時會 include 所有 `*Test.php`。`ResponseNormalizerTest` 與 `StreamOutputParserTest` 在檔案層級就宣告了 `mpu_get_option`，`SpamEventResponseTest` 宣告了 `mpu_load_personality_manifest`。在同一個 process 裡 require `core-functions.php` 或 `personality-loader.php`，會**直接因重複宣告而 fatal**。
2. `mpu_get_option()` 有 `static` 快取，跨測試會互相污染。
3. `MPU_CHAT_INTEGRITY_MODE` 是常數，定義後無法撤銷。所以測試應改用 option `chat_integrity_mode` 或 `mpu_chat_integrity_mode` filter 來切換模式。

既有的 `DialogThemeTest` 與 `EmotionTagPromptTest` 已經用同樣的方式處理（在測試方法內 require 加上 separate process），所以這是**沿用既有慣例**。

**成本**：Windows 上每個 separate process 約 0.3–0.6 秒。10–14 個 case 大約讓 suite 增加 4–8 秒（baseline 是 5.9 秒）。

### Mismatch log 的隔離

`mpu_chat_integrity_dump_mismatch()` 的路徑**寫死**在 `<repo>/logs/checksum-mismatch.log`，而且 audit、warn、block **三種模式都會寫**。bootstrap 的 `wp_mkdir_p` 是真的 `mkdir`。

- `logs/` 已在 `.gitignore`，不會污染 git。
  - 更正（PR 1a 實作時發現）：`logs/` **其實已存在**，裡面有一個 2026-03 建立的 `.htaccess`。Phase A 用 `ls` 檢查時沒看到 dotfile，誤以為目錄不存在。log guard 已正確處理這種情況：保留原有的 `.htaccess`，只刪除測試自己建立的 log。
- 但會在開發者機器上留下檔案，或混進真實的 mismatch log。
- **做法**（不修改 production）：
  - `setUp` 時記錄 `logs/`、`.htaccess`、log 檔是否已存在，並保存原始檔案的**完整 bytes**，不能只存大小。
  - 在 `tearDown`／`finally` 中還原原始內容；只刪除本次測試建立的檔案與目錄。
  - 若偵測到測試期間有非預期的外部寫入（內容不是「原始 bytes 加上本測試的寫入」），**讓測試失敗並保留現場**，不要直接截斷。
  - 測試結束後斷言 `logs/` 沒有留下新產物。

### 三條路線比較

| 路線 | 成本 | 真實度 | 評價 |
| --- | --- | --- | --- |
| **1. 最小 PHPUnit controller harness**（separate process、真實模組、約 5 個 WP stub） | 一個 `tests/support/` 支援檔約 60–90 行，加一個測試檔約 300–400 行；**不需要修改 `tests/bootstrap.php`** | 高：鎖、checksum、personality、prompt 都是真的 | **建議採用** |
| 2. WordPress Playground 整合測試 | 需要 fake provider、啟動 Playground，CI 穩定性未驗證 | 最高 | 對 P0a 太重，留給 P1 的 SSE 退出分支 |
| 3. 混合：service 層補 mode tests，controller 只測少量 | 最低 | 中 | **合併到路線 1**：三種模式在 service 層測，controller 只測 block 與一種非阻擋模式（即 v3 的分層要求） |

**不會出現 v3 擔心的「虛假 WordPress」**：stub 數量從 bootstrap 現有的約 30 個增加約 5 個，核心契約全部用真實模組。

### 避免假綠燈

測試開頭要先斷言下列 class 與函數存在：`MPU_Chat_Lock`、`MPU_Input_Role`、`MPU_Observation_Buffer`、`mpu_set_request_input_context`、`mpu_resolve_personality_id`、`mpu_resolve_system_prompt`、`mpu_resolve_language_code`。唯一允許的 fallback 是 `mpu_debug_log`，需在測試中註明。

### 建議的 PR 1a 最小 case 清單

**取鎖前 case 的共同 fixture 規則**：凡是要證明「發生在取鎖之前」的 case（#1、#1b、#2），request 都**必須帶非空的 `session_id`**。結尾斷言該 session 對應的 lock option 不存在。否則一旦有人把取鎖移到更前面，測試仍會因為 session ID 為空、根本沒取鎖而假綠燈。

| # | Case | 保護的契約 | 層級 |
| --- | --- | --- | --- |
| 1 | 沒登入、沒 token、有 `session_id` → `WP_REST_Response` 403，`missing_session_token`；鎖的 option 不存在 | token 檢查在取鎖之前、回傳型別 | controller |
| 1b | 有效 token、有 `session_id`、rate limit 已達上限 → `WP_REST_Response` 429，`rest_rate_limit_exceeded`；鎖的 option 不存在 | rate limit 在取鎖之前、回傳型別 | controller |
| 2 | 有效 token、有 `session_id`、空訊息 → `WP_Error` 400；鎖的 option 不存在 | 輸入早退 | controller |
| 3 | **window fixture**（最後 20 筆 raw 內放足夠的 `auto_talk`/`give`/`synthetic`，讓第 10 筆 chat assistant 落在 −20 之前），先 store checksum 當基準，模式設為 block → 成功；`integrity_history` 數量 > 20，`chat_history` 剛好 20 | 完整 integrity 與 LLM context 分開 | controller |
| 4 | 同 #3，但 fixture 被竄改 → block 回 409，**鎖已釋放** | block 早退時釋放鎖 | controller |
| 5 | 同 #4，但模式為 audit → 成功、帶著鎖、log 有寫入 | 非阻擋模式能越過 verify | controller |
| 6 | lock busy → `WP_Error` 429，原鎖仍在 | 不釋放別人的鎖 | controller |
| 7 | 有 session、provider 不是 ollama、沒有 key → 400，**鎖已釋放** | API key 早退時釋放鎖 | controller |
| 8 | 成功路徑 → 帶著鎖、`drain()` 已消耗 buffer（預先 push 一筆 observation，結束後 buffer 為空） | 成功時鎖交給 caller、drain 的時機 | controller |
| 9 | 早退路徑（#7）→ observation buffer **未被消耗** | drain 在 API key 檢查之後 | controller |
| 10–12 | `mpu_chat_integrity_mode()` 的 audit／warn／block 與無效值 fallback；`should_block` filter 覆寫 | 模式語意 | **service** |

**必做 mutation 驗證**（不得進 commit）：
1. 暫時把 L632 的 `array_slice(-20)` 移到 L629 的 `$integrity_history` 賦值之前，只跑目標測試，確認 #3 轉紅。
2. 完整還原 production。
3. 暫時移除 L690 API key 早退時的 `release_chat_lock`，確認 #7 轉紅。
4. 再次完整還原。
5. 確認 production files 沒有 diff（`git diff --stat -- includes/` 為空），再重跑新增測試與完整 PHPUnit。

> 2026-10-13 依 Codex 交叉檢查補充：新增 rate-limit case #1b、取鎖前 case 的非空 `session_id` 規則、TTL 敘述修正、mismatch log 改以完整 bytes 保存與還原。case 數以契約覆蓋為準，不拘泥於 12 個。

**PR 1b／P1（可選）**：#13 `/debug_mcp` 管理員與非管理員；#14 `cur_num` 對應 personality；#15 500 字截斷（含多位元組字元）；JSON caller 的 `finally` 釋放鎖；SSE 退出分支（需要 Playground 或子 process，因為有 `exit`）。

**估算**：新增 2 檔，約 400–500 行；不改 bootstrap 和任何既有測試；suite 增加約 5–8 秒；以 `git revert` 單一 PR 即可回滾。

**未覆蓋的風險**：SSE 的 `exit` 分支、shutdown cleanup、取鎖後拋出例外的鎖洩漏、多 Ghost 的 prompt 細節。

---

## 8.5 決策

| 項目 | 決策 | 理由 |
| --- | --- | --- |
| **PR 1a（case 1、1b、2–12）** | **GO** | 痛點有證據（7 次 bug fix 集中在同一區）；harness 已實測可行，成本低，不需要虛假 WordPress；能抓住過去確實發生過的 `8cac617` 型回歸，而現有的字串 smoke test 抓不到 |
| PR 1b | HOLD | 等 1a 驗收後再決定 |
| **PR 2** | **HOLD，列出條件** | 最有力的候選是讓 `prepare_user_chat_args()` 與 `prepare_auto_chat_context()` 共用「角色、語言、時間、prompt」決議，有 2 次同步修改的證據。條件：PR 1a 已合併；保留 `$variables` 差異、`ai_enabled` 檢查、鎖的釋放；在同一 class 內以 private 方法完成；再次取得批准。keyword flags（L556–595）從未被改過，**不值得抽** |
| A1 Observation | **NO-GO** | 半年只改 2 次，自 5/24 起沒再動過；有跨檔 SPA caller，又要維護 build 與 enqueue，收益只剩檔案變短 |
| A2 Security Events | **NO-GO** | 與 auto-talk、checksum、emotion 高度耦合，抽檔只是把耦合搬走 |
| C Ghost ZIP | **Later** | 半年 3 次，全是 hardening 或機械性修改，沒有維護痛點 |
| 其他大型檔案 | 維持現狀 | — |

**本輪不該做**：修改 production（包括上面提到的鎖洩漏風險、`$variables` 差異）、修改 bootstrap、修 `debug-functions.php` 與 bootstrap 的衝突、處理 SSE caller、拆任何 JS。

**此處停止，等待開發者批准 PR 1a。**

---

## PR 1a 實作結果（2026-10-13）

分支 `test/rest-chat-characterization`，commits `d39f375`、`c89ebb5`，**只有測試變更**。

| 項目 | Phase A 估算 | 實際 |
| --- | --- | --- |
| 檔案 | 新增 2 個 | 新增 3 個（`tests/Unit/RestChatPrepareArgsTest.php`、`tests/support/rest-chat-harness.php`、`tests/support/IntegrityLogGuard.php`），修改 1 個（`tests/Unit/ChatIntegrityTest.php`） |
| 行數 | 400–500 | 約 630 |
| 測試數 | — | 159 → **173**（controller 9 個、service 5 個） |
| Suite 時間 | 增加 5–8 秒 | 增加約 **1.7 秒**（5.9 → 7.6 秒） |
| 額外 WP stub | 5 個 | 8 個：多了 `wp_timezone_string`、`wp_timezone`、`wp_rand`。探針的 `wp_date` stub 回傳空字串，所以沒走到節日判斷這條路徑 |

**與 case 清單的對應**：#9（早退時不消耗 observation）併入 #7 的同一個測試。

**Mutation 驗證：RUN，3/3 被抓到**
1. 把 `array_slice(-20)` 提前 → `test_integrity_uses_full_history_while_llm_context_keeps_last_20` 轉紅。
2. 移除 API key 早退時的 release（L690）→ `test_missing_api_key_returns_400_releases_lock_and_keeps_observations` 轉紅。
3. （額外）移除 block 早退時的 release（L669）→ `test_block_mode_mismatch_returns_409_and_releases_lock` 轉紅。

每次驗證後都已還原，`git diff --stat -- includes/` 為空。

**其他驗證**
- 完整 PHPUnit：PASS。
- `--order-by=random`：3 次全數 PASS。
- `npm --prefix tools/node run verify`：PASS。
- `logs/`：只剩原有的 `.htaccess`。
- `test:interaction`：**NOT RUN**（依 v3 §7，純測試 PR 不強制）。
