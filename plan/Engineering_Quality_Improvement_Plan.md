# MP Ukagaka Engineering Quality Improvement Plan

> 📋 2026-05-18 — 基於專業程式碼審查的改善計畫
>
> 定位：`Code_Quality_Hardening_Plan.md` 著重安全性與拆分，本文件著重**工程品質**——
> 即「讓程式碼從能用 → 像專業工程師寫的」的改善項目。

---

## Current Assessment: B−

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
| 7 | runtime_state helper + MPU_Config | Avatar §4 + §5 | v2.19+ |
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

預期：lint pass → bundle 重建（169.7 → 79.0 KB）→ PHPUnit `OK (22 tests, 51 assertions)`。

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

**現狀**：`ukagaka-base.js` 開頭有 30+ 個全域變數。

**步驟**（漸進式，不一次大改）：

1. **第一步**：將狀態變數收進一個命名空間物件：
   ```javascript
   // Before: 30+ 散落的 let/const
   // After: 收進 window.MPU_STATE
   window.MPU_STATE = {
     autoTalk: false,
     autoTalkInterval: 12000,
     autoTalkTimer: null,
     aiTextColor: "#000000",
     aiDisplayDuration: 8,
     aiDisplayTimer: null,
     typewriterTimer: null,
     typewriterSpeed: 40,
     ollamaReplaceDialogue: false,
     ollamaRequesting: false,
     messageBlocking: false,
     lastLLMResponse: '',
     llmResponseHistory: [],
     lastUserActionTime: Date.now(),
     // ...
   };
   ```
2. **第二步**：全域搜尋替換引用（`mpuAutoTalk` → `MPU_STATE.autoTalk`）。
3. **第三步**：保留 `window.mpuChatHistory` 和 `window.mpuChatModeActive`，因為跨模組依賴太深，短期內不動。

**風險**：中。需要更新所有 JS 檔案中的引用，且 bundle 必須重建。
**建議**：搭配 Phase 1 的測試做，至少有 manual smoke test checklist。

---

## Phase 3: Code Quality Polish

### 3.1 PHP 型別宣告

**現狀**：只有 `mpu_save_*` 系列有 return type (`: string`)，其餘函式幾乎沒有。

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

*Last updated: 2026-05-18（夜間）— v2.18 milestone 全 4 項完成，新增「v2.18 完成報告」段落供公司端 CLAUDE / CODEX 接手參考*
