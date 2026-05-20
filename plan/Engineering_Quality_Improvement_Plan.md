# MP Ukagaka Engineering Quality Improvement Plan

> 📋 2026-05-18 — 基於專業程式碼審查的改善計畫
>
> 定位：`Code_Quality_Hardening_Plan.md` 著重安全性與拆分，本文件著重**工程品質**——
> 即「讓程式碼從能用 → 像專業工程師寫的」的改善項目。

---

## Current Assessment: B−

> 註：此評分為 **2026-05-18 初始審查快照**，當時尚無 PHPUnit。v2.18 引入測試骨架（22 tests / 51 assertions）、v2.19 補到 27 tests / 59 assertions，「自動化測試 F」評分已不再準確。其餘維度的後續改善見下方「v2.18 完成報告」、「v2.19 完成報告」段落。

| 維度 | 評分 | 一句話 |
|------|:----:|--------|
| 架構與模組化 | B | REST OO + Provider Factory 很好；但 5 個檔案 >1000 行 |
| 安全性 | A− | 多層防護、AES 加密、IP spoofing 防護，超出預期 |
| PHP 程式碼品質 | B− | 可讀但重複多，型別宣告不足，function_exists 散彈槍 |
| 前端 JS 品質 | C+ | 30+ 全域變數、無模組系統、jQuery 依賴 |
| 文件與註解 | A− | [Fix] 標籤解釋 why，docblock 完整 |
| 自動化測試 | F | 184 個檔案，零測試 |

## Principles

- 不做一次性大重構。每個 phase 可獨立完成、獨立驗證、獨立回退。
- 優先處理「外行一眼就看出」的問題（測試、上帝檔案），再處理「內行仔細看才發現」的問題（型別、命名）。
- 向後相容：REST response shape、checksum 流程、前端 localStorage key、dialog 格式不變。
- 與既有 `Code_Quality_Hardening_Plan.md` 互補不重疊。

---

## Execution Decision (2026-05-18)

> 本計畫已與 `Avatar_UI_Learnings.md` 合併排序。經 Claude + CODEX 討論定案：
> **#1–#4 為 `v2.18 — chat lifecycle 固化` milestone，#5 以後為後續工程整理。**
> 此順序已凍結，後續不再爭論。新項目應在 #5 之後插入或建立新 milestone。

### 最終執行順序

| # | 項目 | 來源 | milestone |
|:-:|------|------|:---------:|
| 1 | PHPUnit 骨架 + verify 串接 | Eng. Phase 1.1 + 1.2 | **v2.18** |
| 2 | 並行 LLM lock（chat lifecycle 行為規格） | Avatar §2 | **v2.18** |
| 3 | REST Chat 小範圍重複消除 | Eng. Phase 2.1 | **v2.18** |
| 4 | UI 狀態 badge（runtime 驗收工具） | Avatar §3 | **v2.18** |
| 5 | 核心 class 型別宣告 | Eng. Phase 3.1 | v2.19+ |
| 6 | utility-functions 拆分 | Eng. Phase 2.2 | v2.19+ |
| 7 | runtime_state helper | Avatar §4 | v2.19+ |
| 8 | JS 全域狀態封裝 | Eng. Phase 2.3 | v2.19+ |
| 9 | CSS theme / i18n hot swap | Avatar §7 + §8 | v2.19+ |
| 10 | observation buffer（低侵入 MVP） | Avatar §9 + 補充 D | v2.20+ |

### v2.18 範圍邊界（hard limits）

避免 milestone 膨脹的硬性限制 — 這些是上界，不是目標：

1. **PHPUnit**：只做到能跑、能保護核心純函式，**不**追求完整覆蓋率
2. **LLM lock**：只保護 `/chat/user` 和 `/chat/user-stream`，**不**碰 `/chat/greeting` 和 `/chat/context`
3. **REST Chat 重構**：只抽共用 `prepare_auto_chat_context()` helper，**不**拆 service class，**不**改 response shape
4. **UI badge**：只顯示 `thinking / streaming / tool / error / timeout / busy` 六個狀態，**不**做 theme / i18n 熱切換

### 關鍵決策理由

| 決策 | 理由 |
|------|------|
| #2 LLM lock 在 #3 REST 重構**之前** | lock 是 lifecycle 行為規格，先定行為才能抽出正確的 helper 邊界 |
| #4 UI badge 在 #6 utility 拆分**之前** | badge 是 #2 #3 的 runtime 驗收工具，先用眼睛驗收再動大檔案 |
| Phase 3.1 型別宣告限縮到核心 class | 廣加型別會踩 WordPress filter（mixed 回傳）/ 舊序列化 option 資料 / 外部 abilities |
| Phase 2.2 utility 拆分保守進行 | 只搬不改邏輯，避免單一 PR 同時搬家+重構造成 review/bisect 困難 |

### v2.18 milestone 完成條件

- [x] `composer test` 可執行並通過核心純函式測試 — `npm --prefix tools/node run test:php` → 22 tests / 51 assertions
- [x] `/chat/user` 和 `/chat/user-stream` 在連續快速請求時回 HTTP 429（lock 生效）— `MPU_Chat_Lock` 接入兩個入口
- [x] `chat_context()` 和 `chat_greet()` 共用 `prepare_auto_chat_context()`，response shape 不變 — 已抽 helper
- [x] 前端在 SSE 進行中可見狀態 badge，timeout/error 時正確顯示 — `.mpu-state-badge` 渲染六狀態 + busy 對應 429

---

## ✅ v2.18 完成報告（2026-05-18 夜間更新）

> 公司端 CLAUDE / CODEX 接手前必讀。本 milestone 全部 4 項在 2026-05-18 當日完成（家用機實作）。
> 版本已 bump 到 **2.18.0**，三份 CHANGELOG + readme.txt 已寫；可直接 tag 發版。
> 完整變更內容看 `docs-en/CHANGELOG.md` 的 `[2.18.0]` 條目。

### 項目盤點

| # | 項目 | 主要產出 | 規模 |
|:-:|------|---------|------|
| 1 | PHPUnit 骨架 + verify 串接 | `tests/`、`tools/php/composer.json`、6 個 Unit test | 22 tests / 51 assertions |
| 2 | 並行 LLM lock | `includes/llm/class-mpu-chat-lock.php` + REST 接入 | ~150 行 + 7 個測試 case |
| 3 | REST Chat dedup | `MPU_REST_Chat::prepare_auto_chat_context()` | 抽 ~55 行共用 helper |
| 4 | UI 狀態 badge | `.mpu-state-badge` + CSS + `mpuL10n.streamStates` | 6 個可見狀態 |

### 關鍵決策（與原計畫不同或需註記之處）

**#2 鎖選 `add_option` 而非 `transient`** — 原計畫只說「lock」沒指定 primitive。實作前發現 `get_transient()` + `set_transient()` 兩步操作不是 atomic，並行下會出現「兩個 request 都看到沒鎖、各自 set 自己 token、第二個覆蓋第一個」的 race — 也就是 lock 本身會有它要防的 bug。改用 `add_option($key, $payload, '', 'no')`：底層 MySQL `INSERT` + UNIQUE key 為 atomic check-and-set，autoload='no' 避免污染 autoload 快取。

**#2 TTL 60s（而非 90s）** — PR4 watchdog 是 45s，95p LLM 回應應 < 45s。60s 已涵蓋；90s 萬一 release 失敗會多擋使用者 30 秒重試，看起來像「壞了」而非「忙線中」。`mpu_chat_lock_ttl` filter 可調，clamp 到 [10, 300] 秒。

**#2 SSE 雙保險 release** — `register_shutdown_function` 在客戶端中途關瀏覽器分頁時不保證即時觸發。所以 SSE chunk loop 每個 emit 後都跑 `connection_aborted()` 檢查（`exit_if_stream_aborted()`）。token 驗證確保 shutdown fallback 不會誤殺後續請求的 lock。

**#2 `/debug_mcp` 早 return 不走 lock** — `prepare_user_chat_args()` 在 line 542 對 `/debug_mcp` 早 return，根本到不了 lock acquire（CODEX 原本要求「不鎖 debug_mcp」自動成立，沒有額外 guard）。

**#3 helper 用 `$options` flag 而非拆兩函式** — `chat_greet` 需要額外的 `ai_greet_first_visit` 預檢，但前處理 90% 相同。用 `require_first_visit_greeting` flag 而不是拆兩個 helper，集中度高。

**#4 `status` state 對應 `streaming` 同 label** — 既有 `setStreamState('status')` 在「非 tool 但有 message」時使用，CODEX 列的 6 個 visible state 沒包含它。為避免空 badge，CSS 把 `status` 跟 `streaming` 用同樣 selector 群（同綠色 pill、同 label）。

### 附帶完成的工程修正（不在原計畫但順手修了）

- `tests/phpunit.xml.dist` 加 `cacheResult="false"` — 防 `.phpunit.result.cache` 在受限環境寫入失敗
- `tools/node/build.js` 修為失敗時 exit 非 0 — 原本 minify 失敗只 `console.error` 不 throw，verify 會假通過
- `tests/bootstrap.php` 的 `apply_filters` / `add_action` mock 改為真正執行 callback（按 priority 排序、依 `accepted_args` 截參數） — 為將來測 `mpu_chat_integrity_mode` 這類 filter 鋪路
- `tools/node/package.json` 的 `test:php` 改為 `cd ../../tests && php ../tools/php/vendor/bin/phpunit` — Windows + PHPUnit 9.6 在 `--configuration tests/phpunit.xml.dist` 模式下 bootstrap path 解析失敗的繞路，Linux/Mac 也能跑

### v2.19+ 起跑線

`v2.18` milestone 凍結。下一階段照本文件「執行順序」表續做：

- **#5 核心 class 型別宣告**（Eng. Phase 3.1）— 限縮到 `MPU_REST_Base` / `MPU_Input_Role` / `MPU_Session_Event` / `chat-integrity`；**不**對 utility-functions 大舉加型別
- **#6 utility-functions 拆分**（Eng. Phase 2.2）— 拆 encryption / network / wp-info / template / file 五檔，每拆一檔跑一次 verify
- **#7 runtime_state helper**（Avatar §4）— transient-based ghost runtime state；**MPU_Config 仍維持否決**（Avatar X-1）

`prepare_auto_chat_context()` 已抽，後續若想 dedup 其他 REST handler 邊界已清楚；`MPU_Chat_Lock` 已釘住 lifecycle 邊界，#5 加型別不會誤切到 lock 行為。

### 驗證指令

```bash
npm --prefix tools/node run verify
```

預期（截至 v2.19.1）：lint pass → bundle 重建 → PHPUnit `OK (27 tests, 59 assertions)`。

---

## ✅ v2.19 完成報告（2026-05-19 / 2026-05-20）

> 公司端 CLAUDE / CODEX 接手前必讀。本 milestone 在 2026-05-19 完成（家用機 + 公司機協作），v2.19.2 sleep 分鐘精度 patch 於 2026-05-20 補上。
> 已 tag 並 release **v2.19.0** + **v2.19.1** + **v2.19.2**，三份 CHANGELOG + readme.txt 已寫；`mp-ukagaka.zip` 已自動附 release asset。

### 項目盤點

| Version | 項目 | 主要產出 | 規模 |
|:-:|------|---------|------|
| v2.19.0 | #5 核心 class 型別宣告 | 4 個核心 class / 函數族加 scalar / nullable return type hints（PHP 7.4 相容，未使用 typed properties 等 7.4 新語法） | ~30 行 net |
| v2.19.1 | Frieren 動態 `deep_sleep_start`（character feature） | manifest schema + `mpu_get_daily_deep_sleep_start()` + 2 call site | +50 行 |
| v2.19.2 | Sleep 系統分鐘精度（character feature） | `_mod` 版本函數 + cache key 遷移 + 全比較升級 minutes-of-day | +209 行 net |

### v2.19.0 關鍵決策

- **限縮在四個核心 class**：`MPU_Session_Event` / `MPU_Input_Role` / `MPU_REST_Base` / `chat-integrity.php` — 不對 utility-functions 大舉加型別（避免踩 WP filter mixed 回傳 / 舊序列化 option / 外部 abilities）
- **兩個 method 故意不加 return type**（PHP 7.4 無 union types）：`MPU_REST_Base::check_admin()` (true|WP_Error)、`chat-integrity::verify_history()` (true|null|WP_Error)
- **內部 `(string) $xxx` cast 保留作為防禦層** — 不動邏輯，純 type hint 補強
- **`MPU_REST_Base::ok($data, ...)` 的 `$data` 保留 mixed**：response 可以是 array/string/object，加型別會收緊得太死
- **`chat-integrity::_store_history(): bool`** 兩條 return path（`return false` + `set_transient()`）對齊
- 27 tests / 59 assertions 全綠（含 v2.18 + ollama bugfix 補的 5 個 case）

### #6 utility-functions 拆分前置審查（v2.19.0 順帶完成）

確認 `chat-integrity.php` / `provider-helpers.php` / `utility-functions.php` 在 `rest/bootstrap.php` 前載入，REST 與 chat history 內的 `function_exists('mpu_chat_integrity_*')` / `function_exists('mpu_rest_check_rate_limit')` 是載入順序保證下的冗餘防護。**v2.19 不動**，留到 #6 與檔案搬移同 PR 進行（兩件事是同一前提的兩面，拆檔可能改變載入順序，與「移除 function_exists 防護」是同一前提的表裡）。

### v2.19.1 額外項目（非 Engineering plan 範圍）

Frieren 動態 `deep_sleep_start` 是 character feature，不在本 plan 範圍但順帶說明：
- manifest `deep_sleep_start` 支援 `[start, end]` 整點 array（Frieren 改 `[22, 23]`、`oversleep_probability: 1.0`）
- 新 `mpu_get_daily_deep_sleep_start()` 每日抽一次、transient 對齊次日午夜（比照 `mpu_get_daily_oversleep_end()`）
- array 防呆：`random_int(min, max)` 自動修正範圍倒置、`count !== 2` 走 `mpu_log_warning` + fallback、`24 → 0` 顯式處理
- **入睡時刻仍對齊整點**，分鐘級隨機排定在 v2.19.2

### v2.19.2 額外項目（非 Engineering plan 範圍）

Sleep 系統分鐘精度延伸 v2.19.1 動態整點機制，仍屬 character feature。歷史 commit message 標為 `feat(v2.20.0)` 是因為原規劃凍結表把它列為 v2.20.0；release 時降版為 v2.19.2 patch，後續 milestone 整批往前推一版（詳見「v2.20+ 執行順序」）。

#### 範圍邊界（hard limits）

1. **升級到 minutes-of-day 精度**：`mpu_get_daily_deep_sleep_start_mod()` / `mpu_get_daily_oversleep_end_mod()` 都回傳 0–1439
2. **cache key 加 `_mod` 後綴**：強制 cache miss 重抽避免讀到舊「小時」值；**不**做 backward-compat 讀舊值
3. **manifest schema 不變**：仍寫整點 hour（`[22, 23]` / `deep_sleep_end: 7`），分鐘化是實作層
4. **抽籤範圍 inclusive 整段**：`[22, 23]` 解為「22:00 ~ 23:59」即 `random_int(22*60, 23*60+59) = random_int(1320, 1439)`
5. **不**動賴床機率 / IP 記錄機制 / wake_ghost endpoint 整體邏輯（只升級內部比較的單位）
6. **不**順手做其他 character feature 改動

#### 完成條件

- [x] `mpu_get_daily_deep_sleep_start_mod()` 抽 minutes-of-day (0–1439)
- [x] `mpu_get_daily_oversleep_end_mod()` 同樣升級（`random_int(deep_sleep_end*60, oversleep_max_hour*60)`）
- [x] `mpu_is_deep_sleep_time()` 全部比較換 minutes-of-day（含跨午夜：`$start_mod > $end_mod` 走 OR 分支）
- [x] `mpu_is_ip_woken_today()` / `mpu_mark_ip_as_woken()` 邊界檢查升級為 minutes-of-day
- [x] `wake_ghost()` 同步更新（含 `function_exists` fallback 對應修正）
- [x] cache key 全部加 `_mod` 後綴
- [x] `frontend-functions.php` caller 同步升級（原 plan checklist 漏列、實作補上）
- [x] 27 tests / 59 assertions 全綠
- [ ] manual smoke test：跨日切換、同日 cache hit、賴床+IP、設定錯誤、舊 cache 過渡（`_mod` 後綴強制 miss）— 上線後 soak

#### 已知限制 / Future Hardening

**Sleep settings input clamping deferred** — 當前 `_start_mod` / `_end_mod` 函數只用 `% 1440` 處理正向跨日溢位（`[22, 24]` 抽到 1499 → 59 ✓），**不** clamp 負數或極大值。PHP `%` 對負數保留 dividend sign（`-100 % 1440 = -100`），會 break 後續 minutes-of-day 比較。

實務上 manifest 由 plugin author / 角色作者手寫，沒 admin UI，不會踩雷。

**何時補**：未來若開放 admin UI 讓 site owner 自訂 sleep hours，需做**完整** input validation，**不只是** clamp：

- `mpu_clamp_minutes_of_day($mod): int` helper：`return (($mod % 1440) + 1440) % 1440;`，handle 任意 int 包含負數
- 三處 return 前呼叫：`_start_mod` / `_end_mod` / `_is_deep_sleep_time` 的 `$current_mod`
- Hour range validation (0–23) at admin save handler
- 衝突檢查：`deep_sleep_end > deep_sleep_start` 不能跨午夜矛盾、`oversleep_max_hour > deep_sleep_end`
- UI form validation

這些都應跟 **admin UI PR 同 milestone** 一起做（input validation 是 admin UI 的一部分，孤立 clamp 沒意義）。本項列入 plan 是 future-proof 備忘，不在 v2.19.x patch 範圍。

#### 舊 `mpu_get_daily_deep_sleep_start()` / `_oversleep_end()` 變死碼

升級為 `_mod` 後，舊整點版本只剩 `wake_ghost` fallback 引用，邏輯上是 dead code。清理已隨 **v2.20.0 #6 utility 拆分**完成（同 PR 移除冗餘函數，與檔案重組同前提）。

---

## ✅ v2.20.0 完成報告（2026-05-20）

> #6 utility-functions 拆分 milestone，2026-05-20 在 `feature/code-quality-hardening` 完成、release tag `v2.20.0`。
> 三份 CHANGELOG + readme.txt 已寫；`mp-ukagaka.zip` release asset 由 GitHub Actions 自動掛上。

### 項目盤點

| Commit | 內容 | 規模 |
|---|---|---|
| `refactor(utility): split utility-functions.php into 5 domain files` | 5 個新 domain 檔（合計 ~1,179 行）+ `utility-functions.php` 剩 36 行常數 + `mp-ukagaka.php` $core_modules 載入順序 + `tests/bootstrap.php` 同步 | +1,192 / -1,132 net 約等量搬移 |
| `chore(cleanup): remove redundant function_exists + dead sleep helpers` | 5 個檔的 `function_exists` 守衛清理（10+ 處）+ 整點 sleep helper 兩函數刪除 + `wake_ghost` 三層 fallback 簡化 | +15 / -146 |

兩 commit 分開是為了 bisect — 若上線後出 bug 可快速隔離「拆檔搬移」vs「移除防護」。

### 拆檔歸屬決定（plan #5 動手前定案）

| 檔 | 主題 | 雜項函數 |
|---|---|---|
| `template-functions.php` (142 行) | 字串 / template / output filter | `array2str` / `str2array` / `output_filter` / `js_filter` / `render_prompt_template` / `build_user_info_prompt` |
| `file-functions.php` (174 行) | 安全檔案 I/O | `is_path_within_allowed_dir` / `secure_file_read` / `secure_file_write` / `get_dialogs_dir` / `ensure_dialogs_dir` |
| `encryption-functions.php` (190 行) | API key 加密 | `get_encryption_key` / `encrypt_api_key` / `decrypt_api_key` / `is_api_key_encrypted` / `get_provider_api_key` / `get_current_provider` |
| `wp-info-functions.php` (284 行) | WP 環境 / 使用者 / personality | `get_wordpress_info` / `get_current_user_info` / `country_code_to_name` / `resolve_personality_id` |
| `network-functions.php` (389 行) | HTTP / cache / rate limit / session | `get_client_ip` / `get_client_ip_strict` / `fetch_external_api` / `clear_api_cache` / `check_rate_limit` / `rest_check_rate_limit` / `generate_session_token` / `validate_session_token` |

### 載入順序（`mp-ukagaka.php`）

```
debug → core → utility(常數) → template → file → encryption → wp-info → network → input-role → personality → ...
```

- encryption 在 wp-info 之前：`mpu_get_provider_api_key()` 依賴 encrypt/decrypt helpers
- network 最後：`fetch_external_api` 等同時用到 cache、rate limit、session helpers，所有上游必須就緒

### 清理範圍（commit 2）

- 5 個檔移除冗餘 `function_exists` 守衛：`chat/class-mpu-chat-history-service.php` / `integrations/akismet-integration.php` / `llm/class-mpu-chat-lock.php` / `rest/class-mpu-rest-base.php` / `rest/class-mpu-rest-dialog.php` — 目標函數 `mpu_chat_integrity_*` / `mpu_rest_check_rate_limit` 全部因載入順序保證存在
- chat-lock `normalize_session_id()` 移除內聯 fallback — chat-integrity 版本與 fallback byte-equivalent，drop 無行為差
- `llm-context-builder.php` 移除整點 `mpu_get_daily_deep_sleep_start()` / `mpu_get_daily_oversleep_end()`
- `rest-dialog.php` `wake_ghost()` 移除 3 層 `function_exists` fallback，直接呼叫 `_mod` 版本

### 驗證

- `npm run verify` 全綠（27 tests / 59 assertions）— 兩 commit 各自跑 `test:php` 自包綠
- `grep -h "^function mpu_" includes/core/*.php | sort | uniq -d` 為空 — 拆檔無重複定義
- code-path grep 對舊整點 helper 只剩 doc/changelog/plan 歷史記錄

### v2.20.0 起跑線 / 下一站

- `feature/code-quality-hardening` 與 `main` 同步至 `v2.20.0` tag
- 下一版 **v2.21.0 = #8 JS 全域狀態封裝**（surface 大、manual smoke test 必要、不與後端打包）

---

## ✅ v2.21.0 完成報告（2026-05-20）

> #8 JS 全域狀態封裝 milestone，2026-05-20 在 `feature/code-quality-hardening` 完成。
> Release pending：smoke test 通過後 bump version 並寫 CHANGELOG / readme.txt 三份。

### 項目盤點

| Commit | 內容 | 規模 |
|---|---|---|
| `refactor(js): encapsulate runtime state into window.MPU_STATE (v2.21.0 #8)` | 7 個 source 檔遷移；新建 `MPU_STATE` shape + 31 個 helper function（base.js，含 `mpuState` const alias 共 32 個 entry）+ 19 個 file-level `let` 重新指向 + 9 個 `window.*` 全域中 7 個完全消滅、2 個保留 compat bridge | +388 / -174 net |
| `chore(build): rebuild dist bundle for v2.21.0 #8 MPU_STATE migration` | 純 `tools/node/build.js` 輸出 | +390 / -176 |

兩 commit 拆分為「source 改動」+「dist 重建」，目的同 v2.20.0：bisect 時可精準隔離程式碼變更 vs build-tool 副作用。

### 與 plan §2.3 「漸進式提交順序」的偏離

Plan 列了 7 步 commit（Inventory / Namespace / Base+core / Dialog+context+greeting / Features / Chat boundary / Bundle）。實作期間 6 個 source step 因協作流程沒有逐步落 commit，最後一次性 squash 成單一 source commit。

**影響**：bisect granularity 從「7 步可精準定位」降為「整個 milestone 是 atomic」。Trade-off：

- 缺點：若 smoke 抓到 regression，無法用 bisect 把問題收斂到單一 step
- 優點：每步驟之間的 dist 不存在中間狀態（CODEX 全程只在收尾重建一次），若硬要 reconstruct 7 commit 反而會留下「source 已搬但 bundle 仍是舊」的不一致 commit，bisect 不正確
- 補救：若上線後出 regression，整個 v2.21.0 revert 後重新 cherry-pick by file（base/core/dialog/context/greeting/features/chat 各自為 boundary）

### 遷移分層

| 層級 | 變數/全域 | 數量 |
|---|---|---|
| **完全遷移**（無 legacy 殘留）| `__mpu_retry_count` / `__mpu_fallback_retry_count` / `mpuContextPending` / `mpuSettingsProcessed` / `mpuSettingsLoaded` / `mpuEnableChatMode` / `debugMode` | 7 |
| **MPU_STATE primary + `window.*` compat bridge** | `mpuMsgList` / `mpuBaseAutoTalkInterval` | 2 |
| **Dual-write helper（`let` 仍在 base.js scope 暴露給跨檔讀取）** | `mpuAutoTalk` / `mpuAutoTalkInterval` / `mpuAutoTalkTimer` / `mpuTypewriterTimer` / `mpuTypewriterSpeed` / `mpuAiTextColor` / `mpuAiDisplayDuration` / `mpuAiDisplayTimer` / `mpuOllamaReplaceDialogue` / `mpuAiContextInProgress` / `mpuMessageBlocking` / `mpuLastLLMResponse` / `mpuOllamaRequesting` / `mpuLastUserActionTime` / `mpuGreetInProgress` / `mpuNextMode` / `mpuDefaultMsg` | 17 |
| **同物件 ref**（mutation 自動同步，無需 helper）| `mpuLLMResponseHistory` / `mpuOllamaRequestQueue` / `__mpuStorage` | 3 |
| **不搬**（plan §2.3 明文保留）| chat shared state × 5 + PHP localized data × 9 + manager objects × 4 + SPA events × 2 | 20+ |

### Helper 一覽（base.js）

- **State access**：`mpuGetState` / `mpuState` (const alias)
- **Debug**：`mpuIsDebugMode`
- **AutoTalk**：`mpuSetAutoTalkTimer` / `mpuSetAutoTalkEnabled` / `mpuSetAutoTalkInterval` / `mpuSetBaseAutoTalkInterval` / `mpuGetBaseAutoTalkInterval`
- **Typewriter**：`mpuSetTypewriterTimer`
- **LLM/AI**：`mpuSetAiTextColor` / `mpuSetAiDisplayDuration` / `mpuSetAiDisplayTimer` / `mpuSetAiContextInProgress` / `mpuSetMessageBlocking` / `mpuSetOllamaReplaceDialogue` / `mpuSetLastLLMResponse` / `mpuResetLLMResponseHistory` / `mpuSetOllamaRequesting` / `mpuSetLastUserActionTime`
- **Dialog**：`mpuSetDialogStore` / `mpuGetDialogStore` / `mpuSetDialogNextMode` / `mpuSetDialogDefaultMsg`
- **Flags**：`mpuSetGreetInProgress` / `mpuSetContextPending` / `mpuIsContextPending` / `mpuSetSettingsProcessed` / `mpuIsSettingsProcessed` / `mpuSetSettingsLoaded` / `mpuIsSettingsLoaded` / `mpuSetEnableChatMode` / `mpuIsChatModeEnabled`

合計 31 個 helper function（+1 個 `mpuState` const alias = 32 個 entry），全部集中在 `js/ukagaka-base.js`。`mpuSetGreetInProgress` 對應 `MPU_STATE.flags.greetInProgress`，歸類 Flags 而非 LLM/AI。

### 與原 plan §2.3 #6 的偏離（實作優於計畫）

Plan 原文：「`mpuAutoTalk` / `mpuAutoTalkTimer` / `mpuMessageBlocking` 這類高引用變數應先建立區域短別名（例如 `const autoTalkState = MPU_STATE.autoTalk;`），再分段替換，避免一次性 search-replace 造成 scope 錯誤。」

實作改用 **setter helper function** 取代「區域短別名 + 分段替換」：

| 比較項 | 區域短別名（plan 原方案）| Setter helper（實作方案）|
|---|---|---|
| Dual-write 邏輯位置 | 散落各 call site | 單點集中於 helper body |
| Grep friendliness | `MPU_STATE.autoTalk.timer = ` 多種寫法 | `mpuSetAutoTalkTimer(` 唯一格式 |
| Scope 錯誤風險 | 局部別名重名/遮蔽 | 函數呼叫無 scope 問題 |
| 重構成本 | 每 call site 改 2 行 | 每 call site 改 1 行 |

Intent 完全相同（避免 search-replace 失誤），執行更穩。

### 行為調整（intentional，非純 refactor）

- **`window.mpuDebugMode = true` 即時生效**：原 `let debugMode` 在腳本載入時 capture 一次 `window.mpuDebugMode`，console 修改不會立即生效。新版 logger 一律走 `mpuIsDebugMode()`，每次呼叫即時讀 MPU_STATE flag 與 window flag，console 切換立即生效。Plan §2.3 #4 已明文標記為行為調整。

### 外部相容橋設計（compat bridge）

兩個 legacy `window.*` 全域**刻意保留**：

- `window.mpuMsgList`：可能被外部 debug console 直接讀取或主題覆寫；`mpuSetDialogStore(store)` helper 雙寫 `window.mpuMsgList = store; MPU_STATE.dialog.msgList = store;`。`mpuGetDialogStore()` 以 `window.mpuMsgList` 為 canonical，fallback `MPU_STATE.dialog.msgList`，附 defensive comment 說明 fallback 用途。
- `window.mpuBaseAutoTalkInterval`：同上保留 compat bridge；`mpuGetBaseAutoTalkInterval()` 內建 `> 0 ? interval : mpuAutoTalkInterval` fallback 語義，保留原 `typeof !== "undefined" && > 0` 三段檢查的核心行為。

未來若確認無外部讀取，可在後續 milestone 完全刪除這兩個 `window.*` 寫入。當前不刪。

### TDZ guard 順手簡化（step 6 → final cleanup 間自動成為冗餘）

Step 5（features.js 遷移期間），`mpuSetEnableChatMode` 必須處理「`chat.js` 的 `let mpuEnableChatMode` 仍存在」的 cross-script-scope 雙寫場景，採用 `typeof X !== "undefined"` defensive guard。Step 6 刪除 `let` 後該 guard 變冗餘，helper 簡化為純 MPU_STATE write。一次到位，無死碼殘留。

### 驗證

- `npm --prefix tools/node run verify` 全綠（27 tests / 59 assertions）—— source commit + dist commit 各自獨立 verify 通過
- Source grep 確認：`window.mpuContextPending` / `mpuSettingsProcessed` / `mpuSettingsLoaded` / `__mpu_retry_count` / `__mpu_fallback_retry_count` / `let mpuEnableChatMode` / `let debugMode` 全部為 0 hit
- `window.mpuMsgList` / `window.mpuBaseAutoTalkInterval` 只剩 base.js helper internal compat bridge 寫入點 + `mpuGetDialogStore` defensive fallback
- `git diff --check` 乾淨

### 已知限制 / Future Hardening

**Manual smoke test 必要**：PHPUnit 無法測 JS runtime；plan §2.3 #6 列出必驗收 path（auto-talk / chat / context / SSE / typewriter / wake_ghost / first-visit greeting / SPA navigation / sleep-mode interval），release 前必須跑過。本 milestone source/dist commit 已落，**release commit + tag 等 smoke 通過後再下**。

**Bisect granularity 折衷**：見上方「與 plan §2.3 偏離」段落。若上線後出 regression，無法用 7-commit 精準定位，需 file-level cherry-pick。

**`mpuGetDialogStore()` defensive fallback 為實質 dead code**：`typeof window.mpuMsgList !== "undefined"` 在 base.js init 將其設為 `null` 後永遠為 truthy（`typeof null === "object"`），fallback branch 不會走到。**刻意保留**作為「未來若外部 script unset window.mpuMsgList = undefined」的安全網，附 inline comment 說明。

### v2.22+ 起跑線 / 下一站

- v2.21.0 已 tag 並推上 origin，等生產驗證後同步 main
- 下一站照下方執行順序表：**v2.22.0 = #7 Ghost Runtime State helper**；**#9 已刪除**，**#10 Observation Buffer MVP** 只保留設計文件，未列入 v2.22.0 實作範圍

---

## v2.21+ 執行順序（2026-05-20 更新）

> 經 Claude + CODEX 討論定案，supersede 頂部「Execution Decision」表格 #5–#10 的 milestone 標記。
> 原 v2.20.0 sleep minute precision 已 patch 為 **v2.19.2**，後續 milestone 整批往前推一版。v2.20.0 #6 utility 拆分於 2026-05-20 完成。
> 此順序已凍結，新項目應在 v2.22 之後插入或建立新 milestone。

### 最終執行順序

| Version | 內容 | 來源 | 風險 |
|:-:|------|------|:----:|
| ~~v2.19.2~~ | ~~Sleep minute precision~~（已完成，見 v2.19 完成報告） | v2.19.1 延伸 | — |
| ~~v2.20.0~~ | ~~**#6 utility-functions 拆分** + 順手清冗餘 `function_exists` + 清理 v2.19.2 舊整點 sleep 函數~~（已完成，見 v2.20.0 完成報告） | Eng. Phase 2.2 | — |
| ~~v2.21.0~~ | ~~**#8 JS 全域狀態封裝**~~（已 tag `v2.21.0`，等生產驗證後同步 main，見 v2.21.0 完成報告） | Eng. Phase 2.3 | 中 |
| **v2.22.0** | **#7 Ghost Runtime State helper**（transient-based，跨 request 角色狀態） | Avatar §4 / P2-1 | 中 |
| ⏸️ 設計階段 | **#10 Observation Buffer MVP**（揮發性、session-scoped，等 User Memory v2 才升級為持久化）—— 詳見 [`plan/Observation_Buffer_Design.md`](Observation_Buffer_Design.md) | Avatar §9 / P2-3 + 補充 D | 中 |
| ❌ 已刪除 | ~~#9 CSS theme / i18n hot swap~~（i18n hot-swap 已被 Avatar plan X-2 卻下；CSS theme 為「保留」低 ROI 項，無立即價值） | — | — |

### 關鍵決策

| 決策 | 理由 |
|------|------|
| v2.19.2 sleep minute precision **獨立 patch 而非 minor release** | 純後端 cache key migration，跟 #6/#8 完全 isolated；行為改變僅限分鐘精度（manifest schema 不變、IP 機制不動），語義上是 v2.19.1 動態整點的延伸而非新功能，patch level 較合適。後續 milestone 因此整批往前推一版 |
| #6 **在 #8 之前** | utility-functions 拆分是後端 isolated 動作、邊界最清楚、bisect 容易；JS 封裝需 manual smoke test 較費神，留到後面 |
| #7 落 v2.22.0 而非「穿插」| v2.21.0 完成後重新評估，#7 是後端 isolated 新模組（transient-based），跟 v2.21.0 前端 JS state encapsulation 無耦合，獨立成版 milestone 邊界清楚 |
| **#9 從 plan 刪除**（2026-05-20）| 與 Avatar plan v3 同步：i18n hot-swap 已被 reviewer（CODEX + Gemini）正式卻下為 X-2（WordPress locale 是 per-request，前端切 locale 跟 SSE/admin 文字脫節）；CSS theme 在 Avatar 優先度表標為「保留 — 低 ROI」非排程項。留在 Engineering plan 只會誤導未來實作者 |
| **#10 設計但不立刻做**（2026-05-20）| 揮發性 MVP 設計可以先釘死，但完整實作前置條件是 User Memory v2，而 User Memory MVP (v2.16.0) 目前只支援 admin。建立 design doc 凍結 scope，避免未來 over-engineering 或 privacy regression（global transient = 跨訪客洩漏） |
| **MPU_Config 維持否決** | Avatar X-1 — 沒到「設定數量爆炸到需要抽象層」的點，現在引入只是 over-engineering |

### v2.22.0 #7 Ghost Runtime State helper 範圍邊界（hard limits）

> 來源：Avatar §4 / P2-1。Avatar pseudo-code 使用 `mpu_get_session_key()` 作示意，**本專案目前沒有此函式**；實作時必須以現有 session token 機制或明確新增的小型 helper 為準，不可直接照抄。

**目標**：新增 transient-based helper，記錄「某個前台 session / 使用者目前的角色 runtime state」，作為後續 runtime UI / 觀測整合的基礎。它是後端短期狀態，不是 v2.21.0 的前端 `window.MPU_STATE` 延伸。

**允許的 state set（先凍結）**：

`idle / thinking / speaking / chatting / sleeping / waking / tool_running / suspended / error`

**建議 public API（名稱可微調，但語意不要擴張）**：

```php
mpu_runtime_state_allowed_states(): array
mpu_runtime_state_scope_key(?string $session_token = null): ?string
mpu_set_runtime_state(string $state, ?string $session_token = null): bool
mpu_get_runtime_state(?string $session_token = null): ?array
mpu_clear_runtime_state(?string $session_token = null): void
```

回傳 payload 維持最小 shape：`['state' => string, 'ts' => int]`。v2.22.0 不加 arbitrary metadata，避免它變成 Observation Buffer 或 visitor memory 的替代品。

**Scope / keying 規則**：

1. 優先使用現有 `X-MPU-Session-Token` / `session_token`，且必須通過 `mpu_validate_session_token()` 後才可用於 scope。
2. transient key 必須 hash token，例如 `mpu_runtime_state_{sha256(token)}`；不要把 raw token 放進 key。
3. 不使用 PHP `session_id()` / `session_start()`；會破壞 WordPress page cache。
4. 不使用 IP / referrer / browser fingerprint 作為 scope；這些屬於 Observation / Visitor Signals 隱私邊界，不是 #7。
5. 不寫入 `mpu_opt` / `update_option()`；runtime state 是高頻短期資料，只能用 transient。

**檔案與載入順序**：

- 新檔建議：`includes/core/runtime-state-functions.php`
- 載入位置：`mp-ukagaka.php` 的 `$core_modules` 中，放在 `core/network-functions.php` 之後（可使用 session token helpers），REST controller 之前。
- 若新增 PHPUnit，測試檔放 `tests/Unit/RuntimeStateTest.php`，使用既有 WordPress transient mock pattern；不要引入外部測試框架。

**v2.22.0 hard limits**：

1. 只做 helper + 最小必要 wiring；不做 Observation Buffer、不注入 LLM prompt、不寫 User Memory / Visitor Memory。
2. 不新增 autonomous trigger，不因 state 變化主動呼叫 LLM。
3. 不改 REST response shape；若要讓前端讀 state，必須走獨立小 endpoint 或既有 debug/status route，不能塞進 chat payload。
4. 不搬動 v2.21.0 的 `window.MPU_STATE`，也不把 chat shared state 搬進後端 runtime state。
5. TTL 預設 5 分鐘（沿用 Avatar §4），可加 filter 但需 clamp 在合理範圍（建議 60–900 秒）。
6. state value 必須 whitelist；未知值 return false 或 normalize 為 `error`，不要任意寫入。
7. 清除行為要明確：request 完成 / error / abort 後至少回到 `idle` 或刪 transient；避免 stale `thinking` 卡住。
8. 不做 admin UI、不做 CSS theme、不做 i18n hot swap。

**建議最小 wiring（由實作者按風險切 commit）**：

- `/chat/user`：進入 LLM 前 `thinking`，回覆輸出時 `speaking`，完成後 `idle`，catch 時 `error` → `idle`
- `/chat/user-stream`：SSE start `thinking`，tool event `tool_running`，delta/done `speaking`，done/abort 後 `idle`
- `wake_ghost`：有合法 session token 時可短暫設 `waking`，完成後 `idle`；若 token 不在該 endpoint 流程中，不為了 #7 擴張驗證模型

**驗證條件**：

- `npm --prefix tools/node run verify` 全綠
- PHPUnit 覆蓋：valid state 寫入/讀取、invalid state 拒絕、invalid token 不產生 key、clear 後讀取為 null、TTL/filter clamp（若有 filter）
- Manual smoke：chat normal / SSE stream / SSE abort / tool event / wake_ghost 後 state 不殘留 `thinking` 或 `tool_running`
- Grep red line：不得出現 `session_start(`、`session_id(`、`update_option(.*runtime`、`mpu_opt['runtime_state']`

### v2.21.0 範圍邊界（hard limits）

1. **只封裝 runtime mutable state**：建立 `window.MPU_STATE`，先收 `ukagaka-base.js` 宣告的散落 `let` 狀態與少數 `window.__mpu*` runtime scratch；不改演算法、不改 REST payload、不改 UI 行為
2. **保留外部契約全域**：`mpuInfo` / `mpuSettings` / `mpuPreSettings` / `mpuRestUrl` / `mpuRestNonce` / `mpuL10n` / `mpuInitData` / `mpuInitParams` / `mpuPersonalityId` / `mpuCanvasManager` / `mpuEmojiManager` / `mpuFrierenManager` / `MPU_EVENTS` 不搬，因為它們是 PHP localized data、跨模組 manager、或可被主題/外掛讀取的 integration surface
3. **保留 chat shared state 不動**：`window.mpuChatHistory` / `window.mpuChatModeActive` / `window.mpuChatSessionId` / `window.mpuChatRequesting` 仍維持原位置；本版最多補註解說明為 legacy shared state，不建立 proxy，不搬進 `MPU_STATE.chat`。`mpuEnableChatMode` 不是 chat shared state，而是 settings flag，本版搬入 `MPU_STATE.flags.enableChatMode`
4. **先加相容 helper，再改引用**：新增 `mpuState` / `mpuGetState()`（名稱實作前可微調）後，再逐檔替換引用；禁止一次性 regex 大掃除造成 scope/silent diff
5. **漸進式提交順序**：base namespace + helper → base/core auto-talk/typewriter/context 狀態 → dialog/context/greeting/features 消費點 → chat 只讀取 shared runtime 但不搬 chat state → bundle 重建；每個 commit 都要自包通過 `npm --prefix tools/node run verify`
6. **manual smoke test 是必要驗收**（PHPUnit 無法測 JS）：auto-talk / chat / context / SSE / typewriter / wake_ghost / first-visit greeting / SPA navigation / sleep-mode interval 全流程
7. **不**做 jQuery 移除 / ES Modules 遷移 / manager class 重寫 / localStorage key rename / REST schema 改動（Phase 4.x 或 #7 runtime_state helper 範圍）

---

## Phase 1: Testing Infrastructure（最高優先）

### 1.1 PHP 單元測試骨架

**現狀**：完全沒有 PHPUnit，`package.json` 的 `test` 是 placeholder。

**目標**：建立最小可用的測試框架，覆蓋最容易出錯的核心邏輯。

**步驟**：

1. 安裝 PHPUnit + Brain Monkey（WordPress mock）到 `composer.json` dev 依賴。
2. 建立 `tests/` 目錄結構：
   ```
   tests/
   ├── bootstrap.php          ← WordPress mock 初始化
   ├── Unit/
   │   ├── ChatIntegrityTest.php
   │   ├── InputRoleTest.php
   │   ├── EncryptionTest.php
   │   ├── TemplateRenderTest.php
   │   └── SessionEventTest.php
   └── phpunit.xml
   ```
3. 優先寫測試的函式（純邏輯、無 DB/HTTP 依賴）：
   - `mpu_chat_integrity_compute_checksum()` — 最容易出 regression
   - `mpu_chat_integrity_filter_messages()` — checksum 的核心過濾
   - `mpu_chat_integrity_slice_for_store()` — normalize + slice 順序
   - `MPU_Input_Role::resolve()` / `can_use_ability()` — 安全邊界
   - `mpu_encrypt_api_key()` / `mpu_decrypt_api_key()` — 加解密對稱性
   - `mpu_render_prompt_template()` — 變數替換
   - `MPU_Session_Event::kind_for_legacy_event()` — legacy 映射
4. 更新 `package.json`：`"test:php": "vendor/bin/phpunit"`

**驗證**：`composer test` 或 `vendor/bin/phpunit` 可執行並通過。

**預估影響**：新增檔案，不動既有程式碼。零風險。

**工作量**：約 2-3 小時。

### 1.2 整合驗證腳本

**現狀**：`npm run verify` 已存在（php lint + js build），但沒有測試。

**目標**：將 `npm run verify` 擴展為完整的 pre-commit gate。

**步驟**：

1. 更新 `package.json` scripts：
   ```json
   {
     "verify": "npm run lint:php && npm run build && npm run test:php",
     "test:php": "php vendor/bin/phpunit --colors=always",
     "lint:php": "node -e \"...existing php -l script...\""
   }
   ```
2. 考慮加入 `npm run verify` 到 `.github/workflows/` CI（如有）。

**驗證**：`npm run verify` 一次跑完 lint + build + test。

---

## Phase 2: God File Decomposition

> 注意：`Code_Quality_Hardening_Plan.md` 已定義 Chat Controller 和 Admin Save Handler 的拆分。
> 本節補充該文件**未涵蓋**的上帝檔案問題。

### 2.1 REST Chat Controller — 消除程式碼重複

**現狀**：`class-mpu-rest-chat.php`（1155 行）。`chat_context()` 和 `chat_greet()` 有 ~70 行幾乎一樣的設定 boilerplate。

**問題程式碼**（兩處幾乎相同）：
```php
$mpu_opt = mpu_get_option();
$provider = mpu_get_current_provider($mpu_opt);
$api_key  = mpu_get_provider_api_key($provider, $mpu_opt);
// ... 驗證 ...
$ukagaka_name = $mpu_opt['cur_ukagaka'] ?? 'default_1';
$ukagaka_display_name = $mpu_opt['ukagakas'][$ukagaka_name]['name'] ?? '偽春菜';
$language = $mpu_opt['ai_language'] ?? 'zh-TW';
$personality_id = mpu_resolve_personality_id($ukagaka_name);
$time_context = mpu_get_time_context($personality_id);
$variables = [ /* 完全相同的 13 個 key */ ];
$system_prompt = mpu_resolve_system_prompt(...);
```

**步驟**：

1. 在 `MPU_REST_Chat` 中新增 `protected` 方法：
   ```php
   protected function prepare_auto_chat_context(WP_REST_Request $request): array|WP_Error
   ```
   回傳 `['mpu_opt', 'provider', 'api_key', 'personality_id', 'system_prompt', 'variables', ...]`。
2. `chat_context()` 和 `chat_greet()` 改為呼叫此方法，只保留各自特有的 prompt 組裝邏輯。
3. `prepare_user_chat_args()` 亦可考慮共用部分（variables 組裝），但它有更多額外邏輯，不強求。

**預估縮減**：~100-140 行。

**驗證**：
- `/chat/context` 與 `/chat/greet` response shape 不變。
- checksum 行為不變。

### 2.2 utility-functions.php 領域拆分

**現狀**：1166 行，混合字串、檔案、加密、WP info、IP、rate limit、prompt template。

**注意**：`Code_Quality_Hardening_Plan.md` 已列此項為「Phase 2 中風險最高，應排在最後」。本文件同意此判斷，但提供更細的拆分建議。

**建議拆分**（按風險從低到高）：

| 新檔案 | 搬移函式 | 行數估計 | 風險 |
|--------|---------|---------|------|
| `includes/core/encryption-functions.php` | `mpu_get_encryption_key`, `mpu_encrypt_api_key`, `mpu_decrypt_api_key`, `mpu_is_api_key_encrypted`, `mpu_get_provider_api_key`, `mpu_get_current_provider` | ~120 行 | 低 |
| `includes/core/network-functions.php` | `mpu_get_client_ip`, `mpu_get_client_ip_strict`, `mpu_generate_session_token`, `mpu_verify_session_token`, rate limit 函式群 | ~200 行 | 低 |
| `includes/core/wp-info-functions.php` | `mpu_get_wordpress_info`, `mpu_get_current_user_info`, `mpu_country_code_to_name` | ~200 行 | 低 |
| `includes/core/template-functions.php` | `mpu_render_prompt_template`, `mpu_build_user_info_prompt` | ~60 行 | 低 |
| `includes/core/file-functions.php` | `mpu_secure_file_read`, `mpu_secure_file_write`, `mpu_get_dialogs_dir`, `mpu_ensure_dialogs_dir`, `mpu_recursive_rmdir` | ~120 行 | 中 |

**關鍵注意事項**：
- `mp-ukagaka.php` 的 `$core_modules` 載入順序需要同步更新。
- 加密函式被 admin-functions.php、llm-functions.php、diary-functions.php 等多處依賴，必須最早載入。
- 每拆一個檔案就跑一次 `npm run verify`。

### 2.3 前端 JS 全域變數封裝

**現狀**：前端仍同時使用三種全域狀態形式：
- `ukagaka-base.js` 檔案級 `let`：`mpuNextMode` / `mpuDefaultMsg` / `mpuAutoTalk` / `mpuAutoTalkInterval` / `mpuAutoTalkTimer` / `debugMode` / `mpuAiTextColor` / `mpuAiDisplayDuration` / `mpuAiDisplayTimer` / `mpuTypewriterTimer` / `mpuTypewriterSpeed` / `mpuOllamaReplaceDialogue` / `mpuAiContextInProgress` / `mpuMessageBlocking` / `mpuLastLLMResponse` / `mpuLLMResponseHistory` / `mpuLastUserActionTime` / `mpuOllamaRequesting` / `mpuOllamaRequestQueue` / `mpuGreetInProgress` 等
- `window.mpu*` runtime scratch：`window.mpuMsgList` / `window.mpuBaseAutoTalkInterval` / `window.mpuContextPending` / `window.mpuSettingsProcessed` / `window.mpuSettingsLoaded` / `window.mpuSessionToken` / `window.__mpuStorage` / `window.__mpu_retry_count` / `window.__mpu_fallback_retry_count`
- 外部契約全域：PHP localized data、REST nonce/url、manager objects、chat shared state、主題可擴充的 SPA events

**目標**：把「外掛自己維護、可變、跨檔共享」的 runtime state 收進 `window.MPU_STATE`，降低散落全域變數數量；外部契約全域保持穩定，避免破壞主題整合、localized script data、聊天歷史與既有 debug workflow。

**不搬清單（明確保留）**：
- `window.mpuChatHistory` / `window.mpuChatModeActive` / `window.mpuChatSessionId` / `window.mpuChatRequesting`：chat 模組跨檔深依賴，且 localStorage/session/requesting 邏輯仍以現名為中心
- `mpuChatAbortController`：chat 模組內部 request lifecycle state；不進 shared `MPU_STATE`，除非後續把 chat state 作為獨立 milestone 處理
- `mpuInfo` / `mpuSettings` / `mpuPreSettings` / `mpuRestUrl` / `mpuRestNonce` / `mpuL10n` / `mpuInitData` / `mpuInitParams` / `mpuPersonalityId`：PHP 注入或 REST/localized data contract
- `window.mpuCanvasManager` / `window.mpuEmojiManager` / `window.mpuEmojiConfig` / `window.mpuFrierenManager` / `window.loadEmojiConfig`：manager/integration API 或 emoji config/cache surface，不是單純 state
- `window.MPU_EVENTS`：SSE event constant namespace 已經乾淨，不混入 runtime state
- `window.mpuSpaEvents`：刻意保留給主題或 SPA framework 擴充

**搬入清單（明確納入）**：
- `mpuEnableChatMode`：settings flag，現由 settings processing 寫入、chat toggle 讀取；本版搬到 `MPU_STATE.flags.enableChatMode`，但不連帶搬 chat history / requesting / abort controller

**建議 `MPU_STATE` 初版 shape**（實作時可按現有命名微調，但分類不要發散）：
```javascript
window.MPU_STATE = window.MPU_STATE || {
  dialog: {
    nextMode: "sequential",
    defaultMsg: 0,
    msgList: null,
  },
  autoTalk: {
    enabled: false,
    interval: 12000,
    baseInterval: 0,
    timer: null,
  },
  typewriter: {
    timer: null,
    speed: 40,
  },
  llm: {
    aiTextColor: "#000000",
    aiDisplayDuration: 8,
    aiDisplayTimer: null,
    ollamaReplaceDialogue: false,
    aiContextInProgress: false,
    messageBlocking: false,
    lastResponse: "",
    responseHistory: [],
    lastUserActionTime: Date.now(),
    ollamaRequesting: false,
    ollamaRequestQueue: [],
  },
  request: {
    sessionToken: "",
    sessionTokenPromise: null,
  },
  flags: {
    debugMode: false,
    greetInProgress: false,
    contextPending: false,
    settingsProcessed: false,
    settingsLoaded: false,
    enableChatMode: false,
  },
  retry: {
    nextMessage: 0,
    fallbackMessage: 0,
  },
  storage: {},
};
```

`request.sessionToken` 是 string，`request.sessionTokenPromise` 是 `Promise|null`，初始值必須是 `null`；不要用空字串表示尚未建立的 lazy-load promise。`dialog.nextMode` 命名沿用現有 dialog data 語意，但實際消費點包含 `mpu_selectNextMessage()` 這類 core flow，實作時不要誤解為「只限對話框 UI」。`retry.nextMessage` 是 `window.__mpu_retry_count` 的語意化 rename，`retry.fallbackMessage` 是 `window.__mpu_fallback_retry_count` 的語意化 rename；這個 snake_case → camelCase + 語意命名變更是有意的。

**相容策略**：
1. 新增單一初始化區塊於 `ukagaka-base.js`，在任何消費者執行前建立 `window.MPU_STATE`。
2. 檔案內部優先使用短別名，例如 `const mpuState = window.MPU_STATE;`；跨函式需要最新值時用 `window.MPU_STATE` 或 `mpuGetState()`，避免把 primitive value 解構後失去同步。
3. `window.mpuMsgList` / `window.mpuBaseAutoTalkInterval` / `window.mpuContextPending` 這類舊全域若有外部讀取可能，v2.21.0 可保留 accessor alias 或同步寫入一版；若沒有明確外部契約，改成 `MPU_STATE` 後用 grep 確認只剩 dist bundle。
4. `debugMode` 順手修正 init-only 限制：目前 `debugMode` 只在載入時讀一次 `window.mpuDebugMode`，本版允許 console 內 `window.mpuDebugMode = true` 即時開啟 log；因此 logger 判斷需讀 `MPU_STATE.flags.debugMode || window.mpuDebugMode === true`。
5. `mpuOllamaReplaceDialogue` 目前在腳本載入期間由 `mpuPreSettings` 寫入；`MPU_STATE` 初始化必須早於這段 pre-settings hydrate，避免寫入尚未建立的 `MPU_STATE.llm`。實作時初始化區塊要放在 `ukagaka-base.js` 既有 `mpuPreSettings` hydrate 之前，也就是目前約 line 78 以前；不要把 state 初始化延後到工具函式或 logger 區塊附近。
6. `mpuAutoTalk` / `mpuAutoTalkTimer` / `mpuMessageBlocking` 這類高引用變數應先建立區域短別名（例如 `const autoTalkState = MPU_STATE.autoTalk;`），再分段替換，避免一次性 search-replace 造成 scope 錯誤。
7. 不使用 `Object.freeze()`：runtime 必須持續 mutation（例如 `autoTalk.timer = setTimeout(...)`、`responseHistory.push(...)`、`ollamaRequestQueue.push(...)`），freeze 會破壞現有流程；本次不是建立不可變 store。

**實作步驟**：
1. **Inventory commit**：用 `rg` 列出 `mpuAutoTalk|mpuAutoTalkTimer|mpuMsgList|mpuContextPending|__mpu` 等引用，更新本節清單；不改 runtime code。
   - 2026-05-20 inventory 指令：`rg -n "let mpuNextMode|let mpuDefaultMsg|let mpuAutoTalk|let mpuAutoTalkInterval|let mpuAutoTalkTimer|let debugMode|let mpuAiTextColor|let mpuAiDisplayDuration|let mpuAiDisplayTimer|let mpuTypewriterTimer|let mpuTypewriterSpeed|let mpuOllamaReplaceDialogue|let mpuAiContextInProgress|let mpuMessageBlocking|let mpuLastLLMResponse|let mpuLLMResponseHistory|let mpuLastUserActionTime|let mpuOllamaRequesting|let mpuOllamaRequestQueue|let mpuGreetInProgress|let _mpuSessionTokenPromise|window\.mpuSessionToken|window\.__mpuStorage|window\.__mpu_retry_count|window\.__mpu_fallback_retry_count|window\.mpuMsgList|window\.mpuBaseAutoTalkInterval|window\.mpuContextPending|window\.mpuSettingsProcessed|window\.mpuSettingsLoaded|mpuEnableChatMode" js --glob "*.js" --glob "!**/dist/**"`。
   - 來源檔引用點確認：`ukagaka-base.js` 宣告 runtime `let` 與 `window.__mpuStorage` fallback；`ukagaka-core.js` 消費 auto-talk/base interval/context pending/retry counters/dialog store；`ukagaka-features.js` 寫入 settings processed/loaded/base interval/context pending/`mpuEnableChatMode`；`ukagaka-dialog.js`、`ukagaka-context.js`、`ukagaka-greeting.js` 主要消費 `window.mpuMsgList` 與 auto-talk/message blocking；`ukagaka-chat.js` 只涉及 `mpuEnableChatMode`、session token fallback 與 dialog fallback，不搬 chat shared state。
   - `window.mpuChatHistory` / `window.mpuChatModeActive` / `window.mpuChatSessionId` / `window.mpuChatRequesting` / `mpuChatAbortController` 仍屬不搬清單；inventory 不把它們列入可搬項。
2. **Namespace commit**：在 `ukagaka-base.js` 建立 `window.MPU_STATE` 與 helper；只把初始值搬入 object，保留舊變數暫時指向/讀寫原值，先跑 build。
3. **Base/core commit**：替換 `ukagaka-base.js` / `ukagaka-core.js` 中 auto-talk、typewriter、LLM queue、retry counter 的讀寫；這是最高風險區，做完立刻 rebuild + manual auto-talk/typewriter smoke。
4. **Dialog/context/greeting commit**：替換 `ukagaka-dialog.js` / `ukagaka-context.js` / `ukagaka-greeting.js` 中 dialog store、auto-talk resume、context pending 的引用；確認 LLM fallback、page context、first-visit greeting 都仍會 append history。
5. **Features commit**：替換 `ukagaka-features.js` 中 settings processed/loaded、base interval、toggle auto-talk、`mpuEnableChatMode`、SPA events 的可搬項；`window.mpuSpaEvents` 保留原契約。
6. **Chat boundary commit**：`ukagaka-chat.js` 只改它讀到的 shared runtime（例如 session token / nonce fallback / `mpuInfo` mutation 以外的 helper）與 `MPU_STATE.flags.enableChatMode`，不搬 `window.mpuChatHistory` / `window.mpuChatModeActive` / `window.mpuChatSessionId` / `window.mpuChatRequesting` / `mpuChatAbortController`。
7. **Bundle commit**：`npm --prefix tools/node run build`，確認 dist 只反映 source 變更；實作期間禁止人工編輯 `js/dist/`，所有 dist diff 只能來自本 build commit。

**驗證清單**：
- `npm --prefix tools/node run verify`
- `rg -n "let mpuAutoTalk|let mpuAutoTalkTimer|window.__mpu|window.mpuContextPending|window.mpuSettingsProcessed|window.mpuSettingsLoaded" js --glob "*.js" --glob "!dist/**"` 應只剩允許項或為空
- manual smoke：初始載入、顯示/隱藏、typewriter、auto-talk start/stop、blur/focus/visibility pause-resume、LLM replace dialogue、page context、first-visit greeting、chat send、SSE streaming + nonce refresh、wake_ghost、sleep-mode interval、SPA navigation event
- console compatibility：`window.mpuDebugMode = true` 後 log 仍即時生效；`window.mpuChatHistory.length` 仍可查看

**Rollback 條件**：若 manual smoke 發現 chat history、SSE streaming、auto-talk timer、sleep-mode wake 任何一項出現非平凡 regression，先 revert 該 commit，不把後續檔案一起混入；本 milestone 允許留下部分已封裝 state，但不允許留下半同步 alias。

**風險**：中。主要風險不是 namespace 本身，而是 primitive 變數改成 object property 後，跨檔讀寫時機不同步；因此實作要按檔案分段，不做一次性 regex 替換。

---

## Phase 3: Code Quality Polish

### 3.1 PHP 型別宣告

> **v2.19.0 更新**：第一批核心 class 型別宣告已完成（`MPU_Session_Event` / `MPU_Input_Role` / `MPU_REST_Base` / `chat-integrity.php`，scalar / nullable return type，PHP 7.4 相容）。**後續 utility-functions 不在 #5 範圍** — v2.20.0 #6 拆分時也未做型別宣告（避免踩 WP filter mixed 回傳 / 舊序列化 option / 外部 abilities）。本節以下內容為原始計畫，僅供歷史參考。

**現狀**（2026-05-18 撰寫）：只有 `mpu_save_*` 系列有 return type (`: string`)，其餘函式幾乎沒有。

**步驟**：

1. 優先為所有 `class` 的 public/protected method 加上參數與回傳型別。
2. 其次為常用的 `mpu_*` 公開函式加型別。
3. 不要一次改完，每次改動一批相關函式即可。

**範例**：
```php
// Before
function mpu_render_prompt_template($template, $variables = [])

// After
function mpu_render_prompt_template(string $template, array $variables = []): string
```

**優先處理的檔案**：
- `class-mpu-input-role.php` — 已經很接近完整型別
- `class-mpu-session-event.php` — 很小，容易做
- `class-mpu-rest-base.php` — 公開 API
- `chat-integrity.php` — 核心邏輯

### 3.2 JS 命名慣例統一

**現狀**：混用三種風格。

| 風格 | 範例 | 出現頻率 |
|------|------|---------|
| `snake_camelCase` 混合 | `mpu_sendUserMessage` | 多 |
| `camelCase` | `mpuFetchSSE` | 中 |
| `snake_case` | `mpu_typewriter` | 多 |

**建議**：WordPress JavaScript Coding Standards 使用 `camelCase`。

**步驟**：
1. 新程式碼統一用 `camelCase`。
2. 既有函式保留舊名稱，加上 alias wrapper 做過渡：
   ```javascript
   // 新名稱
   function mpuTypewriter(text, target, speed, skipAnim) { ... }
   // 向後相容 alias
   const mpu_typewriter = mpuTypewriter;
   ```
3. 逐步在後續版本移除舊名稱。

**優先級**：低。僅在下次大改 JS 時順便做。

### 3.3 消除 `function_exists()` 散彈槍

**現狀**：整個專案有大量的 `if (function_exists('mpu_xxx'))` 防護。

**根本原因**：模組載入順序不確定。

**步驟**：

1. 審查 `mp-ukagaka.php` 的 `$core_modules` 載入順序，確認依賴圖。
2. 對於已確認一定會載入的函式，移除 `function_exists` 檢查。
3. 對於可能被停用的可選模組（如 Slimstat 整合），保留檢查但加註解說明原因。

**範例**：
```php
// ❌ 不需要（core-functions.php 一定在 rest-chat.php 之前載入）
if (function_exists('mpu_resolve_system_prompt')) { ... }

// ✅ 需要保留（Slimstat 是可選外掛）
if (function_exists('mpu_fetch_slimstat_stats')) { ... }
```

---

## Phase 4: 長期目標（Nice-to-have）

這些不急，但記錄下來作為方向。

### 4.1 移除 jQuery 依賴

**現狀**：前端大量使用 jQuery（`jQuery("#ukagaka_msgbox")`、`$input.val()`）。

**方向**：2026 年的瀏覽器原生 API 完全可以替代。但 WordPress 本身仍捆綁 jQuery，且前端改動風險高，ROI 較低。

**建議**：不主動移除，但新功能不再使用 jQuery。

### 4.2 ES Modules 遷移

**現狀**：所有 JS 是全域 script，靠 bundle 打包。

**方向**：改為 ES module import/export，搭配 Vite 或 esbuild。

**建議**：等有重大前端功能新增時一起做，不單獨遷移。

### 4.3 PHPCS WordPress Coding Standards

**方向**：`composer require --dev wp-coding-standards/wpcs`，跑 baseline，逐步修正。

**建議**：先建 baseline ignore list，新改檔案必須通過，舊檔案逐步清理。

---

## Implementation Priority Matrix

```
                 高影響
                   │
     Phase 1.1     │    Phase 2.1
     PHPUnit 骨架  │    消除 REST 重複
                   │
  ─────────────────┼─────────────────
                   │
     Phase 3.1     │    Phase 2.3
     PHP 型別宣告  │    JS 全域封裝
                   │
                 低影響
  低工作量 ──────────────── 高工作量
```

## Recommended Execution Order

> **⚠️ 此區段已被頂部「Execution Decision (2026-05-18)」取代。**
> 原本的 Engineering-only 順序已與 `Avatar_UI_Learnings.md` 合併排序。
> 請以頂部的最終執行順序為準，本表保留純為歷史參考。

<details>
<summary>原始 Engineering-only 順序（已棄用，僅供歷史對照）</summary>

| 順序 | 項目 | 工作量 | 風險 | 備註 |
|:----:|------|:------:|:----:|------|
| 1 | **Phase 1.1** — PHPUnit 骨架 + 核心測試 | 2-3h | 零 | 只新增檔案，不動既有碼 |
| 2 | **Phase 1.2** — 整合 verify 腳本 | 30min | 零 | 更新 package.json |
| 3 | **Phase 2.1** — REST Chat 消除重複 | 1-2h | 低 | 抽出 prepare_auto_chat_context() |
| 4 | **Phase 3.1** — PHP 型別宣告（核心 class） | 1h | 低 | 只加 type hint，不改邏輯 |
| 5 | **Phase 2.2** — utility-functions 拆分 | 2-3h | 中 | 需更新載入順序 |
| 6 | **Phase 3.3** — 清理 function_exists | 1h | 低 | 審查載入圖後移除不需要的 |
| 7 | **Phase 2.3** — JS 全域變數封裝 | 3-4h | 中 | 需全域搜尋替換 |
| 8 | **Phase 3.2** — JS 命名統一 | 隨開發累積 | 低 | 新碼用新慣例 |
| 9 | **Phase 4.x** — jQuery/ESM/PHPCS | 視需求 | 不急 | 大版本才考慮 |

</details>

---

## Relationship to Existing Plans

| 文件 | 涵蓋範圍 | 與本文件關係 |
|------|---------|-------------|
| `Code_Quality_Hardening_Plan.md` | 安全硬化 + Chat/Admin 拆分 | Phase 1-2 大部分已完成；本文件補充剩餘的重複消除 |
| `Avatar_UI_Learnings.md` | 角色 runtime / UX / 安全邊界 | **已與本文件合併排序**（見頂部 Execution Decision）。Avatar §2 §3 §4 §5 §9 對應本文件 #2 #4 #7 #10 |
| `Future_Plan.md` | 功能路線圖 | 本文件不涉及新功能 |
| `SSE_Streaming_Plan.md` | SSE 架構設計 | 已完成；本文件不重複 |
| 本文件 | 工程品質基線提升 + 合併排序權威 | 專注測試、型別、命名、模組化；v2.18 milestone 定義在頂部 |

---

*Last updated: 2026-05-20 — v2.21.0 (#8 JS 全域狀態封裝) 已 tag `v2.21.0` 並推上 origin，等生產驗證後同步 main。v2.22+ 執行順序重整：#9 已刪（與 Avatar X-2 同步），#10 移到設計階段（見 `plan/Observation_Buffer_Design.md`），v2.22.0 = #7 Ghost Runtime State helper。*
