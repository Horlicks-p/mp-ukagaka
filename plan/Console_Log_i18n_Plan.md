# Console Log i18n 計畫（草案）

> 2026-05-22 草案。**狀態：設計討論中，尚未凍結，尚未實作。**
>
> 本文件記錄「將 ~164 條 JS console log 硬編碼字串改為 i18n 化」的需求盤點、架構選項與分批策略，作為未來 milestone（暫定 #X）的設計基礎。在凍結之前，所有決策可調整。

---

## 起源

v2.21.1 patch 撰寫 CHANGELOG 時注意到 console log 字串全部 hard-coded 為繁體中文，不隨 WordPress locale 切換，英日使用者除錯時體驗欠佳。當下評估「順手做」但發現實際規模遠超預期（161 處 call site，11 個檔案），決定獨立 milestone 處理，避免污染 patch 範圍。

---

## 目標

讓 console log 字串能依 WordPress locale 切換語言，同時：

- 不破壞既有 `mpuLogger` debug-mode gating 行為
- 不引入新前端 i18n 框架
- 翻譯成本可分階段攤提，不強求一次補齊
- 至少維持日文 fallback，翻譯缺漏時不出現 `undefined` 或英文 key

本案明確**不做**：

- 不做 server-side PHP log i18n（`mpu_log_*` 留繁中，那是站長/開發者後端 log，不影響訪客）
- 不改變 emoji 慣例（☀️ 🌙 📖 等保留）
- 不引入 i18next / formatjs / vue-i18n 等前端 i18n library
- 不做 ghost-specific log 的第三方擴充 hook（如 `frieren.js` 的 log 由 plugin 統一管，第三方 ghost 自己負責 i18n）
- 不在 production user UI 顯示 log（這些是 console only）

---

## 現狀盤點（2026-05-22，Codex 覆核後修正）

### Call site 計數（source files，不含 dist bundle）

| 檔案                             | mpuLogger 中文 log | direct console 中文 log |
| -------------------------------- | -----------------: | ----------------------: |
| `js/ukagaka-core.js`             |                 32 |                       0 |
| `js/ukagaka-features.js`         |                 25 |                       0 |
| `js/ukagaka-base.js`             |                 20 |                       1 |
| `ghost/Frieren/frieren.js`       |                 20 |                  **10** |
| `js/ukagaka-chat.js`             |                 18 |                       0 |
| `js/ukagaka-context.js`          |                 15 |                       0 |
| `ghost/Frieren/frieren-emoji.js` |                  6 |                       0 |
| `js/ukagaka-emoji.js`            |                  5 |                       0 |
| `js/ukagaka-anime.js`            |                  1 |                       5 |
| `js/ukagaka-greeting.js`         |                  4 |                       0 |
| `js/ukagaka-dialog.js`           |                  2 |                       0 |
| **合計**                         |            **148** |                  **16** |
| **總計**                         |                    |                 **164** |

初版草案誤列 `frieren.js` direct console 為 0，實際有 10 條（`shellInfo 無效` / `Canvas 管理器未初始化` / `芙莉蓮圖片載入失敗` / `裝飾配置載入失敗` / `觸摸區域對話請求失敗` 等）。grep regex `[`'"][^`'"]_[一-鿿]`要求字串首字為中文，漏掉`console.error("[MP Ukagaka] 芙莉蓮…")`這種前綴為英文方括號的型態。已用`console\.(...)\s_\([^)]\*[一-鿿]` 修正盤點。

### Direct console 的特殊性

16 條 direct console 全部是 **`console.error` / `console.warn`**，沒有 `console.log` debug 用途。這代表這 16 條目前**在 production 永遠輸出**（瀏覽器 native），跟 `mpuLogger.warn` debug-gated 的行為截然不同。i18n migration 必須保留此行為差異（見 §架構決策 1.5 與 Hard Limit #2）。

### Logger 現狀（`js/ukagaka-base.js:316-332`）

```js
const mpuLogger = {
  _isDebug: function () {
    return mpuIsDebugMode();
  },
  log: function (...args) {
    if (this._isDebug()) console.log("[MP Ukagaka]", ...args);
  },
  warn: function (...args) {
    if (this._isDebug()) console.warn("[MP Ukagaka]", ...args);
  },
  error: function (...args) {
    console.error("[MP Ukagaka ERROR]", ...args);
  },
  info: function (...args) {
    if (this._isDebug()) console.info("[MP Ukagaka]", ...args);
  },
};
```

特性：

- `error` 永遠輸出；其餘三者只在 debug mode 輸出
- 自動加 `[MP Ukagaka]` / `[MP Ukagaka ERROR]` prefix
- 支援 rest args，可帶物件 / 變數一起 dump

### Log 字串型態（取樣）

| 型態             | 範例                                                                | 比例估計 |
| ---------------- | ------------------------------------------------------------------- | -------- |
| 純字串           | `mpuLogger.log("jQuery ready 已執行")`                              | ~60%     |
| 字串 + 變數 args | `mpuLogger.log("閒置偵測已初始化，閾值：", threshold / 1000, "秒")` | ~25%     |
| 字串拼接條件     | `"☀️ 芙莉蓮被喚醒了！" + (isForced ? " (forceWakeUp)" : "")`        | ~5%      |
| 字串 + 物件 dump | `mpuLogger.warn("mpu_get_settings: 無效的回應", res)`               | ~10%     |

純字串占大宗，i18n 替換相對容易；後三類需要保留 args 傳遞語意。

---

## 架構決策

### 0. Source-language convention：日文 source / fallback

本案採用 Claude 家裡審查提出的選項 A：**log source string 與 JS fallback 字串都統一使用日文**。

理由：

- 既有 `mpuL10n` source string 已是日文；同一 `mp-ukagaka` textdomain 不應混入日文 UI source 與中文 log source。
- 本作品本身源於日文語境；其他語言是附加翻譯層。
- `.pot` / `.po` 對譯者的 source-language convention 必須一致，避免譯者無法判斷哪些字串是原文、哪些字串是待譯。

實作影響：

- 164 條既有中文 log 在 i18n migration 時，應先改成日文 source / fallback，再由 `.po` 補繁中與英文翻譯。
- JS call site 的第二參數 fallback 不再寫中文原文，而是日文原文。
- 中文原文可保留在 PR review notes 或 migration 對照表，但不進 `__()` source string，也不作為 runtime fallback。

### 1. 字串來源：`mpuL10n.logs` 子物件

PHP 端（`includes/core/frontend-functions.php` 的 `wp_localize_script`）：

```php
'logs' => [
    'jqueryReady'          => __('jQuery ready を実行しました', 'mp-ukagaka'),
    'wakeUpFrieren'        => __('☀️ フリーレンが目を覚ましました！', 'mp-ukagaka'),
    'wakeUpFrierenForced'  => __('☀️ フリーレンが目を覚ましました！(forceWakeUp)', 'mp-ukagaka'),
    'skipBookFlipAfterWake'=> __('📖 起床後のページめくりアニメーションをスキップしました', 'mp-ukagaka'),
    // ... 約 150 條
]
```

每條都用 `__()` 包裹，落到 `mp-ukagaka` textdomain。emoji 保留在字串內，不抽出（emoji 是語言中性，翻譯時譯者直接複製到目標語言版本即可）。

### 1.5 Direct console 與 mpuLogger 的語意差異（Codex 提示後新增）

兩種 logging 方式在 production 行為上**截然不同**：

| 通道                 | `log`       | `warn`          | `error` | `info`      |
| -------------------- | ----------- | --------------- | ------- | ----------- |
| `mpuLogger.*`        | debug-gated | **debug-gated** | always  | debug-gated |
| `console.*` (direct) | always      | **always**      | always  | always      |

最大陷阱是 `warn`：**direct `console.warn(...)` 永遠輸出，但 `mpuLogger.warn(...)` 只在 debug mode 輸出**。如果 mechanical replace 把 frieren.js 那 4 條 `console.warn("[MP Ukagaka] 無法載入裝飾配置…")` 改成 `mpuLogger.warnL('decorationConfigLoadFailed', ...)`，會把 production 永遠可見的警告靜默化，使用者回報 bug 時失去重要線索。

i18n migration 必須維持原 production 輸出時機。具體規則（純字串 vs 含 placeholder 各自分支）：

| 原型                                        | 字串型態                        | 遷移目標                                                                           |
| ------------------------------------------- | ------------------------------- | ---------------------------------------------------------------------------------- |
| direct `console.error("中文")`              | 純字串（可帶 object dump args） | `mpuLogger.errorL`                                                                 |
| direct `console.error("中文 %s", v)` 或拼接 | 含 placeholder / 變數           | `mpuLogger.errorF`                                                                 |
| direct `console.warn("中文")`               | 純字串（可帶 object dump args） | `mpuLogger.warnAlways`（**不可**用 `warnL`，會把 production 永遠輸出的警告靜默化） |
| direct `console.warn("中文 %d", n)` 或拼接  | 含 placeholder / 變數           | `mpuLogger.warnAlwaysF`                                                            |
| direct `console.log("中文")`                | 純字串                          | `mpuLogger.logL`（先逐條判斷是否真該 debug-only）                                  |
| direct `console.log("中文 %s", v)` 或拼接   | 含 placeholder / 變數           | `mpuLogger.logF`（同上）                                                           |
| `mpuLogger.warn("中文")`                    | 純字串                          | `mpuLogger.warnL` ✓                                                                |
| `mpuLogger.warn("中文 %d", n)` 或拼接       | 含 placeholder / 變數           | `mpuLogger.warnF`                                                                  |
| `mpuLogger.log` / `info` 同理               | 純字串 vs 含 placeholder        | `logL` / `infoL` vs `logF` / `infoF`                                               |

判斷「是否含 placeholder」的具體訊號：

- 字串內含 `%s` / `%d` → 必走 `*F`
- 字串拼接 `"閾值：" + n + "秒"` → 必改為含 `%s`/`%d` 的 template + `*F`
- 字串後接 object dump（如 `console.error("失敗：", err)`，err 是 Error 物件）→ 用 `*L`，err 自動成為 console 第三 arg dump 出物件詳情

實作時若無法判斷，預設先用 `*L`，code review 時抓出來改成 `*F`。

**Prefix 處理規則**（Codex 第 4 點防呆）：

很多 direct console 原始碼是 `console.error("[MP Ukagaka] Canvas 管理器未初始化")`，字串本身已含 `[MP Ukagaka]` prefix。遷移到 `mpuLogger.*L / *F / warnAlways` 時，糖衣自己會加 `[MP Ukagaka]` / `[MP Ukagaka ERROR]` prefix，所以：

- **i18n key 的 localized string 與 fallback 字串**：只放 **message body**，**不含** `[MP Ukagaka]` / `[MP Ukagaka ERROR]` 等 prefix
- PHP 端 `'canvasManagerMissing' => __('Canvas 管理器未初始化', 'mp-ukagaka')` — 不寫 prefix
- JS 端 `mpuLogger.errorL('canvasManagerMissing', 'Canvas 管理器未初始化')` — fallback 不寫 prefix

否則輸出會變 `[MP Ukagaka ERROR] [MP Ukagaka] Canvas 管理器未初始化`，雙 prefix。

**Prefix Normalization 是 deliberate**（Codex 第三輪建議）：

direct console 原本可能寫 `[MP Ukagaka]` prefix，遷移到 `mpuLogger.errorL` 後 prefix 會自動變成 `[MP Ukagaka ERROR]`（mpuLogger.error 的 console.error 慣例）。這是 **deliberate output prefix normalization**，不是 unintended change：

- 統一所有 error 都用 `[MP Ukagaka ERROR]`，warn 都用 `[MP Ukagaka]`，方便 console 過濾與 GitHub issue 搜尋
- 既有 `mpuLogger.error()` 已經採用 ERROR prefix，遷移後一致
- code review 時不需質疑「為何 prefix 變了」，這是規格

如果某些 call site 必須維持原始 `[MP Ukagaka]` prefix（極罕見），保留 direct console call，列入「例外清單」處理。

### 2. Logger API 擴展（向下相容）

`mpuLogger` 新增 i18n 取值方法，不取代現有 `log/warn/error/info` API：

```js
const mpuLogger = {
  _isDebug: () => mpuIsDebugMode(),

  // 既有 API 不動
  log: (...args) => {
    if (mpuIsDebugMode()) console.log("[MP Ukagaka]", ...args);
  },
  warn: (...args) => {
    if (mpuIsDebugMode()) console.warn("[MP Ukagaka]", ...args);
  },
  error: (...args) => console.error("[MP Ukagaka ERROR]", ...args),
  info: (...args) => {
    if (mpuIsDebugMode()) console.info("[MP Ukagaka]", ...args);
  },

  // 新增 i18n 取值 helper
  t: function (key, fallback) {
    const logs =
      (typeof mpuL10n !== "undefined" && mpuL10n && mpuL10n.logs) || {};
    const debugLogs =
      (typeof mpuL10n !== "undefined" && mpuL10n && mpuL10n.logsDebug) || {};
    if (Object.prototype.hasOwnProperty.call(logs, key)) {
      return logs[key];
    }
    if (Object.prototype.hasOwnProperty.call(debugLogs, key)) {
      return debugLogs[key];
    }
    if (this._isDebug && this._isDebug()) {
      this._debugMissingI18nKey(key);
    }
    return fallback || key;
  },
  _missingI18nKeys: new Set(),
  _debugMissingI18nKey: function (key) {
    if (this._missingI18nKeys.has(key)) return;
    this._missingI18nKeys.add(key);
    console.debug("[MP Ukagaka i18n missing]", key);
  },

  // 含 placeholder 的 i18n 取值（與 PHP sprintf 一致，支援 %s/%d 與 %1$s/%2$s 等位置定位符）
  // values 固定用 rest args，不接受 array（避免 String([1,2]) 變 "1,2" 的陷阱）
  tFormat: function (key, fallback, ...values) {
    const tpl = this.t(key, fallback);
    // 優先支援 %1$s, %2$s 等位置定位符（解決不同語言間語序顛倒的翻譯痛點）
    if (tpl.includes("$")) {
      return tpl.replace(/%(\d+)\$[sd]/g, (match, index) => {
        const idx = parseInt(index, 10) - 1;
        return idx >= 0 && idx < values.length ? String(values[idx]) : "";
      });
    }
    // 否則退回順序替換
    let i = 0;
    return tpl.replace(/%[sd]/g, () =>
      i < values.length ? String(values[i++]) : "",
    );
  },

  // i18n 糖衣（純字串，無 placeholder）—— args 直接傳給 console 當第三+參數，可用於 object dump
  logL: function (key, fallback, ...args) {
    if (this._isDebug())
      console.log("[MP Ukagaka]", this.t(key, fallback), ...args);
  },
  warnL: function (key, fallback, ...args) {
    if (this._isDebug())
      console.warn("[MP Ukagaka]", this.t(key, fallback), ...args);
  },
  errorL: function (key, fallback, ...args) {
    console.error("[MP Ukagaka ERROR]", this.t(key, fallback), ...args);
  },
  infoL: function (key, fallback, ...args) {
    if (this._isDebug())
      console.info("[MP Ukagaka]", this.t(key, fallback), ...args);
  },

  // i18n 糖衣（含 placeholder）—— values 餵給 tFormat 做 %s/%d 替換，無 object dump 能力
  logF: function (key, fallback, ...values) {
    if (this._isDebug())
      console.log("[MP Ukagaka]", this.tFormat(key, fallback, ...values));
  },
  warnF: function (key, fallback, ...values) {
    if (this._isDebug())
      console.warn("[MP Ukagaka]", this.tFormat(key, fallback, ...values));
  },
  errorF: function (key, fallback, ...values) {
    console.error("[MP Ukagaka ERROR]", this.tFormat(key, fallback, ...values));
  },
  infoF: function (key, fallback, ...values) {
    if (this._isDebug())
      console.info("[MP Ukagaka]", this.tFormat(key, fallback, ...values));
  },

  // always-output warn，專門收 direct console.warn 的 migration（不做 debug-gated）
  warnAlways: function (key, fallback, ...args) {
    console.warn("[MP Ukagaka]", this.t(key, fallback), ...args);
  },
  warnAlwaysF: function (key, fallback, ...values) {
    console.warn("[MP Ukagaka]", this.tFormat(key, fallback, ...values));
  },
};
```

**API 選擇規則**：

- 純字串、無 placeholder → 用 `*L` 系列（`logL` / `warnL` / `errorL` / `infoL`）
- 含 `%s` / `%d` placeholder → 用 `*F` 系列（`logF` / `warnF` / `errorF` / `infoF`）
- 從 direct `console.warn`（production always-output）遷移 → `warnAlways` / `warnAlwaysF`
- `*L` 與 `*F` 後續 args 行為差異：`*L` 的 args 直接給 console 第三+參數（可 dump object），`*F` 的 values 餵給 tFormat（純 placeholder 替換，不 dump object）

**禁止寫法**（Codex 第 3 點防呆）：

```js
// ❌ 雙 prefix —— mpuLogger.log() 自己已加 [MP Ukagaka]
mpuLogger.log("[MP Ukagaka]", mpuLogger.tFormat("key", "fallback %d", n));

// ✓ 正確（直接用 logF）
mpuLogger.logF("key", "fallback %d", n);

// ✓ 也可以（如果一定要混用，至少不要重複 prefix）
mpuLogger.log(mpuLogger.tFormat("key", "fallback %d", n));
```

### 2.5 含變數/單位的 log 必須用 template

mechanical 拼接如 `mpuLogger.log('閒置偵測已初始化，閾值：', 60, '秒')` 對翻譯不友善：

- 只能翻第一段「閒置偵測已初始化，閾值：」，「秒」會殘留中文
- 日文語序可能是「アイドル検知初期化、閾値 60 秒で実行」，無法用拼接表達

**規則**：含變數、單位（秒/分/個/筆）、或順序可能因語言改變的 log，**必須用 template + `tFormat`**：

```js
// Before
mpuLogger.log('閒置偵測已初始化，閾值：', threshold / 1000, '秒');

// After（PHP 端）
/* translators: 開発者向け console log。%d は秒数。 */
'idleDetectionInit' => __('アイドル検知を初期化しました。しきい値：%d 秒', 'mp-ukagaka'),

// After（JS 端 — 用 logF 含 placeholder 糖衣，最簡潔）
mpuLogger.logF('idleDetectionInit', 'アイドル検知を初期化しました。しきい値：%d 秒', threshold / 1000);

// ⚠️ 不要用 logL —— logL 不會跑 tFormat，會輸出「しきい値：%d 秒 60」而不是「しきい値：60 秒」：
// mpuLogger.logL('idleDetectionInit', 'アイドル検知を初期化しました。しきい値：%d 秒', threshold / 1000); // BAD
```

對純字串 log（無變數）則用一般 `logL/warnL/errorL/infoL`，不需要 tFormat。

Call site 改寫範例：

```js
// Before
mpuLogger.warn("mpu_get_settings: 無效的回應", res);

// After（使用 warnL 糖衣，res 物件作為 console.warn 第三 arg，i18n 不處理物件 dump）
mpuLogger.warnL(
  "getSettingsInvalidResponse",
  "mpu_get_settings: 無効なレスポンス",
  res,
);
```

### 3. Fallback 策略

`mpuLogger.t(key, fallback)` 三層 fallback：

1. `mpuL10n.logs[key]` 存在 → 用翻譯後的字串
2. 翻譯缺漏（key 不存在於 `mpuL10n.logs` / `logsDebug`）→ 用 caller 提供的 `fallback`（必須寫**日文 source string**）
3. caller 沒給 fallback → 退回 key 本身（顯示如 `getSettingsInvalidResponse`，明顯是 i18n 漏網之魚，方便除錯）

**Fallback 字串必須寫日文 source string**，不寫中文或英文。理由：

- 與既有 `mpuL10n` source-language convention 對齊
- 翻譯漏網時，console 顯示日文 source，符合作品語境與既有前端 UI source
- `mpuL10n.logs` 若整個 PHP 端忘記注入，整個 plugin 仍可運作

`t()` 必須用 key existence 判斷（`hasOwnProperty`），不可用 truthy fallback。debug mode 下若找不到 key，允許 per-key `console.debug('[MP Ukagaka i18n missing]', key)` 一次，方便發現 key 拼錯或 PHP 端漏注入。

### 4. Key 命名規範

- camelCase
- 結構：`<domain><Action><Context>` 或 `<domain><Event>`
- 範例：
  - `wakeUpFrieren` / `wakeUpFrierenForced`
  - `skipBookFlipAfterWake`
  - `getSettingsInvalidResponse`
  - `idleDetectionInit` / `idleDetectionUpdateFailed`
- 內建 Ghost 專屬 log 必須加上 Ghost 名稱前綴以區分核心（例如 `frierenCanvasInitFailed`、`frierenDecoConfigLoadFailed`），防止與外掛核心 Key 命名衝突。
- 不用點分隔（避免 `mpuL10n.logs['wake.up.frieren']` 這種 JS access pattern 醜陋）
- 不用底線
- 縮寫小寫優先：`api`、`llm`、`ai`（不寫 `API`/`LLM`/`AI`）

### 5. Payload 拆分（Codex 提示後從「未來優化」升級為 MVP 必做）

平均每條 key + 日文字串約 50 bytes。164 條全部 inline ≈ 8.2 KB，每位匿名訪客都會在頁面載入時下載 — 不划算。

**MVP 必做拆分**：

| Bucket              | 對應糖衣（純字串）         | 對應糖衣（含 placeholder） |                                           約略條數 | 注入時機                                                       |
| ------------------- | -------------------------- | -------------------------- | -------------------------------------------------: | -------------------------------------------------------------- |
| `mpuL10n.logs`      | `errorL` / `warnAlways`    | `errorF` / `warnAlwaysF`   | ~30-40（16 direct console + mpuLogger.error 系列） | 一律注入                                                       |
| `mpuL10n.logsDebug` | `logL` / `warnL` / `infoL` | `logF` / `warnF` / `infoF` |                                           ~124-134 | **僅當 PHP `defined('WP_DEBUG') && WP_DEBUG === true` 時注入** |

註：`*L` 與 `*F` 共用同一 bucket，因為 i18n key 本身不區分純字串還是含 placeholder（key 是給人讀的識別碼，字串內容才決定是否含 `%s`/`%d`）。`mpuLogger.t()` 與 `mpuLogger.tFormat()` 內部都先查 `logs` 再查 `logsDebug`，無 bucket 預判邏輯。

注入條件選 **`WP_DEBUG`**（PHP standard constant）作為單一來源，理由：

- 既有 plugin 沒有 `mpu_opt['debug_mode']` 設定（grep 全 repo 0 hit）
- 不在本案範圍內新增 PHP-side debug option（避免 scope creep）
- `WP_DEBUG` 是 WordPress 開發者熟悉的標準開關，wp-config.php 容易切換
- 站長若想看 debug log，本來就會開 `WP_DEBUG`

效益：production 預設只送 ~1.5-2 KB（`logs`），debug 模式才送 ~6-7 KB（`logs` + `logsDebug`）。

> **Plan 之外的 observed defect（2026-05-22 grep finding）**：
> 前端 `mpuIsDebugMode()` 讀的是 `window.mpuDebugMode`，但 grep 全 repo PHP 0 hit `mpuDebugMode` / `mpu_debug_mode` — **這個 flag 從來沒被注入過**，永遠是 `undefined`，`mpuIsDebugMode()` 永遠回 `false`。意思是目前所有 `mpuLogger.log/warn/info` call 在 production 都是 dead code，**只有 `mpuLogger.error` 真的會輸出**。
>
> 這跟本案直接相關：實作 PR 前應該先決定要不要修這個 defect。三個選項：
>
> 1. **修 defect**：PHP 端注入 `window.mpuDebugMode = (defined('WP_DEBUG') && WP_DEBUG)`，讓既有 debug-gated log 重新可運作。建議走這條，但需獨立 PR 不混在本案。
> 2. **不修 defect，本案照計畫做**：i18n migration 完成後 debug log 仍然 dead，但 `logsDebug` payload 依 `WP_DEBUG` 條件注入這條規則仍正確（將來修 defect 後立刻生效）。
> 3. **本案順手修 defect**：scope creep，不建議。
>
> 建議先獨立提一個小 PR 修 defect，再走本案。

實作細節：

- `mpuLogger.t(key, fallback)` 先查 `mpuL10n.logs[key]`，沒找到再查 `mpuL10n.logsDebug[key]`，再沒有才用 fallback
- production 訪客執行 debug-gated log 時：`mpuIsDebugMode()` 回 false → log 根本不輸出 → 不會觸發 `t()` 查表 → payload 不存在也無所謂
- debug 模式訪客執行 production log 時：兩個 bucket 都有，`t()` 找得到
- PHP 端決定注入哪個 bucket 是 server-side 行為，前端不需感知

**仍列為未來改善**（不在本 MVP）：

- 從 `mpu_localize_settings()` 改為 REST endpoint lazy fetch
- 用 sub-resource 拆分 inline payload

---

## 分批策略

**不允許一個 PR 改 154 條**。風險太高，code review 不可能徹底。分階段：

### 階段 1：基礎設施（無 behavior change）

- 新增 `mpuLogger` 方法（共 12 個 entry）：
  - 取值 helpers：`t()` / `tFormat()`
  - 純字串 i18n 糖衣（`*L`）：`logL()` / `warnL()` / `errorL()` / `infoL()`
  - 含 placeholder i18n 糖衣（`*F`）：`logF()` / `warnF()` / `errorF()` / `infoF()`
  - always-output warn 專用：`warnAlways()` / `warnAlwaysF()`
- 新增 `mpuL10n.logs` 與 `mpuL10n.logsDebug` placeholder（PHP 端依 `WP_DEBUG` 條件注入空物件 `[]`）
- 不改任何 call site
- 補 Node smoke script：`t()` 兩 bucket fallback、`tFormat()` placeholder 替換、`*L` vs `*F` 對照（含 lint demo）

預估：1 個 PR，~120 行 diff。

### 階段 2：production-visible call site（error + direct console.error/warn）

只改「production 一律輸出」的 log。原因：使用者實際看得到，i18n 價值最高，且全部對應 always-output 糖衣，行為相容。純字串走 `errorL` / `warnAlways`；含 placeholder、字串拼接，或翻譯後語序可能改變的變數內容走 `errorF` / `warnAlwaysF`。

- `mpuLogger.error()` call site（grep `mpuLogger.error` 中文）
- direct `console.error("中文")` call site → 純字串改為 `mpuLogger.errorL`；含 placeholder / 拼接改為 `mpuLogger.errorF`
- direct `console.warn("中文")` call site → 純字串改為 `mpuLogger.warnAlways`；含 placeholder / 拼接改為 `mpuLogger.warnAlwaysF`
- 估計約 30-40 條：16 條 direct console（全部 error/warn）+ 約 15-20 條 `mpuLogger.error`

預估：3-5 個 PR，每 PR 改 1-2 個檔案。frieren.js 因為 ghost-specific，獨立成一個 PR。

### 階段 3：debug-gated call site（mpuLogger.log / warn / info）

剩餘 ~120 條，純除錯用，全部 debug-gated。i18n 價值低於 production-visible 但完成後一致性高。

- `mpuLogger.log` → `mpuLogger.logL`
- `mpuLogger.warn` → `mpuLogger.warnL`
- `mpuLogger.info` → `mpuLogger.infoL`

預估：5-8 個 PR，按檔案分批。

### 階段 4：translation

`.po` / `.mo` 補繁中與英文翻譯。可由社群 / 機器翻譯 + 校對。

**不阻擋階段 1-3**：階段 1-3 完成後，所有 call site 已 i18n 化，缺翻譯時 fallback 為日文 source string，不影響功能。

---

## Hard Limits

1. 不破壞 `mpuLogger.error/warn/log/info` 既有 API 簽章。新方法以 `L` 後綴並存。
2. **任何 i18n migration 不得改變該 log 是否在 production 輸出的行為。**
   - direct `console.error` → `mpuLogger.errorL` ✓（同為 always-output）
   - direct `console.warn` → `mpuLogger.warnAlways`（不可改成 debug-gated 的 `warnL`）
   - direct `console.log` → 須逐條判斷是否 debug-only，再決定 `logL` 或保留 direct
   - `mpuLogger.warn`/`log`/`info` → `mpuLogger.warnL`/`logL`/`infoL` ✓（同為 debug-gated）
3. 所有 i18n 化的 call site 必須提供 fallback 字串（日文 source string）。禁止傳 `mpuLogger.t('key')` 不帶 fallback。
4. `mpuL10n.logs` / `mpuL10n.logsDebug` 缺失（PHP 端意外沒注入）時，logger 仍可運作，落到 fallback 字串。
5. 不引入新 npm dependency。
6. 不改變既有 debug-mode gating 行為（log/warn/info 仍 gated，error 仍永遠輸出，warnAlways 永遠輸出）。
7. emoji 留在字串內，不抽出。
8. PHP 端 `mpu_log_*` 不在本案範圍。
9. 含變數 / 單位 / 順序可能因語言改變的 log 必須用 `*F` 糖衣（`logF` / `warnF` / `errorF` / `infoF`），不拆段 string concat。
10. i18n key 的 localized string 與 fallback 字串**只含 message body**，不寫 `[MP Ukagaka]` / `[MP Ukagaka ERROR]` prefix；prefix 由 `mpuLogger.*L / *F / warnAlways` 自動添加。
11. `tFormat` values 固定用 rest args（`tFormat(key, fallback, v1, v2)`），不接受 array（`tFormat(key, fallback, [v1, v2])` 會變 `String([v1, v2])`）。
12. `mpuLogger.t()` / `tFormat()` 只作為 logger i18n 內部 helper；禁止拿來做一般 UI i18n。UI 字串仍使用既有 `mpuL10n.xxx` contract。
13. MVP 不支援 plural API（例如 `_n()` / `*N` / `*NF` 糖衣）。含計數的 log 先用 `*F`；英文 plural 不完美列為未來獨立改善。
14. i18n source / fallback / translation 禁止內嵌 session token、API key、user_id、IP、email 等敏感資料。動態敏感值不得被寫進 localized string。
15. `__()` log source string 前應提供 `/* translators: ... */` context 註解；production-visible `mpuL10n.logs` 必須提供，debug-only `logsDebug` 由 code review 盡量補齊。

---

## 例外清單（已透過重構消除）

原本預期有以下例外，但經第五輪審查後，已確定可透過調整程式碼結構徹底消除：

- **`mpuLogger` 定義前的 console call**：`js/ukagaka-base.js` 最前方的 reload 偵測 IIFE 原本因為宣告在 `mpuLogger` 定義（行 316）之前而無法使用 logger。
  - **解決方案**：該 IIFE 僅依賴原生瀏覽器環境（`window.performance` 與 `localStorage`），完全不依賴後續定義的任何變數。因此，**直接將該 IIFE 的位置下移到 `mpuLogger` 聲明（原行 332）之後即可**。
  - **結論**：核心 JS 檔案（不含第三方擴充）將實現 **100% 潔淨 logger 替換**，不再殘留任何 direct console 呼叫，例外清單清空。

---

## Open Questions

實作 PR 之前需明確：

1. **Key 命名是否中央集中管理？**
   - 選項 A：每個檔案 call site 自己決定 key 名稱，PHP 端收集
   - 選項 B：另開 `js/log-keys.js` 列出所有 key constant，避免拼錯
   - 建議：B，但對小 plugin 可能 over-engineering，可先用 A，問題出現再升級

2. **翻譯是否要走 WordPress 標準 .po/.mo？**
   - 選項 A：是，用既有 `compile_po.py` flow
   - 選項 B：另做 JSON-based locale file（`languages/logs-en.json`）
   - 建議：A，與既有 i18n 流程一致

3. **第三方 ghost 的 log 怎麼辦？**
   - `ghost/Frieren/frieren.js` 有 20 條中文 log，未來若有第三方 Sakura ghost 也會帶自己的 log
   - 選項 A：第三方 ghost 自己負責 i18n，本案只處理 plugin 內建檔案
   - 選項 B：提供 `mpuLogger.tGhost(ghostName, key, fallback)` 讓 ghost 註冊自己的 `mpuL10n.logs.ghosts.frieren.*`
   - 建議：A，避免第一版就把擴充介面定死

4. **是否要批量工具？**
   - Codemod / ts-morph script 可半自動替換 call site
   - 但 154 條規模未必需要寫工具，手工 + grep 也能完成
   - 建議：先試做階段 2 一個檔案，評估工時，再決定要不要寫工具

5. **debug-only logs 是否要從 production bundle 排除？**
   - `mpuIsDebugMode()` runtime gating 已防止 console 噪音，但字串仍在 bundle 內
   - 若用 build-time strip（如 Terser pure_funcs），可省 production bundle size
   - 建議：本案不做，列為未來 build optimization 議題

---

## Verification（規劃中）

### Node smoke script（階段 1）

本 repo 尚未引入 JS unit test framework（`tools/node/package.json` 只有 build / PHP lint+test，無 Jest / Vitest / Mocha）。階段 1 不引入 Jest，改以一個 standalone Node smoke script 驗證 `mpuLogger.t` 與 `tFormat` 行為，例如 `tools/node/test-logger-smoke.js`：

- `t('existingKey', 'fallback')` 兩 bucket 命中順序：先 `logs` 再 `logsDebug` 再 `fallback` 再 `key`
- `t()` 用 `hasOwnProperty` 判斷 key 是否存在，不用 truthy 判斷
- debug mode 下 missing key 時，每個 key 只輸出一次 `console.debug('[MP Ukagaka i18n missing]', key)`
- `t('missingKey', 'fallback')` → 回 `'fallback'`
- `t('missingKey')` → 回 `'missingKey'`
- `t('key')` with `mpuL10n` undefined → 不 throw，回 `'key'`
- `tFormat('key', 'fmt %d 秒', 60)` → `'fmt 60 秒'`
- `tFormat('key', 'fmt %s %s', 'a', 'b')` → `'fmt a b'`
- `tFormat('key', 'fmt %d', 1, 2, 3)` → `'fmt 1'`（多餘參數忽略，不錯誤）
- `tFormat('key', 'fmt %d %d', 1)` → `'fmt 1 '`（不足參數用空字串填充，不錯誤）
- **Negative test（合約強制）**：`tFormat('key', 'fmt %s %s', [60, 'sec'])` → `'fmt 60,sec '`（`String([60, 'sec'])` 為 `'60,sec'`，整個 array 被視為 `%s` 的第一個 value）。此測試**只是文件化「不支援 array」的後果**，實際 lint / grep 規則應禁止 `tFormat(..., [...])` 寫法。
- `logF('key', 'fmt %d', 60)` debug mode → console.log 輸出 `'[MP Ukagaka] fmt 60'`，**單一 prefix**
- `logL('key', 'fmt %d 秒', 60)` debug mode → console.log 輸出 `'[MP Ukagaka] fmt %d 秒 60'`（demonstrate logL 不會跑 tFormat 的設計後果，供 lint 規則檢測「含 placeholder 字串誤用 logL」用）

### Lint / grep 規則建議（階段 1 補做）

實作時建議補幾條 grep / lint 規則，定期 CI 跑：

- 偵測 `mpuLogger\.(logL|warnL|errorL|infoL|warnAlways)\([^)]*%[sd]` → 警告「含 placeholder 字串應改用 \*F 系列」
- 偵測 `mpuLogger\.tFormat\([^)]*,\s*\[` → 警告「tFormat values 不接受 array，請改 rest args」
- 偵測 `mpuLogger\.(logL|warnL|errorL|infoL|warnAlways|logF|warnF|errorF|infoF|warnAlwaysF)\([^)]*'?\[MP Ukagaka` → 警告「fallback 字串不應含 [MP Ukagaka] prefix」

未來若 repo 引入 Jest / Vitest，可把 smoke script 升級為正式 unit test。本案不阻擋。

### Code review 必查項

- `__()` source string 與 JS fallback 是否都是日文。
- `mpuL10n.logs` production-visible key 上方是否有 `/* translators: ... */` 註解。
- `mpuLogger.t()` / `tFormat()` 是否只由 logger 糖衣使用，沒有被 UI 字串直接呼叫。
- source 改動後是否以 build 重建 `js/dist/*`，且 dist 未手寫修改。

### Manual

- 切換 WP locale 為 `ja`，重整頁面，觸發 wake-up，console 出現日文版 log（假設翻譯已補）
- 故意拔掉 `mpuL10n.logs.wakeUpFrieren`，觸發 wake-up，console 出現日文 fallback
- production mode（`mpuIsDebugMode()` 回 false），所有 `logL` / `warnL` / `infoL` 不輸出，但 `errorL` 與 `warnAlways` 仍輸出
- production mode 下 `mpuL10n.logsDebug` 未注入，但 `mpuL10n.logs` 存在；error log 取得到翻譯，debug log fallback 到中文（但因 debug-gated 也不會真的輸出）
- debug mode 下 `mpuL10n.logsDebug` 注入；所有 log 取得到翻譯

---

## 與其他 Plan 文件的關係

| 文件                                      | 關係                                        |
| ----------------------------------------- | ------------------------------------------- |
| `Engineering_Quality_Improvement_Plan.md` | 本案待加入指標項                            |
| `Code_Quality_Hardening_Plan.md`          | 本案可視為 hardening 延伸，但獨立 milestone |
| `Observation_Buffer_Design.md`            | 無直接關係，但同為前端 i18n 補強的相鄰議題  |

---

## 不做清單（總結）

- 不做 server-side PHP log i18n
- 不引入新前端 i18n library
- 不破壞既有 `mpuLogger` API
- 不在本 milestone 補完所有翻譯（.po 可分階段）
- 不做 ghost 第三方擴充 hook
- 不做 build-time debug-log strip
- 不做 emoji 抽出

---

## 審查整合紀錄

### Codex 公司覆核（2026-05-22昼，第一輪）

- **反證**：初版草案盤點不準，誤列 `frieren.js` direct console 中文 log 為 0。實際 10 條（`shellInfo 無效` / `Canvas 管理器未初始化` / `芙莉蓮圖片載入失敗` / `裝飾配置載入失敗` / `觸摸區域對話請求失敗` 等）。原 grep regex 漏抓「英文 prefix + 中文內容」型態，已修正為 `console\.(...)\s*\([^)]*[一-鿿]`，重新盤點得 direct console 16 條（不是 6 條），總計 164 條（不是 154 條）。
- **修正**：明文區分 **direct `console.warn` (always-output)** 與 **`mpuLogger.warn` (debug-gated)** 的語意差異。原草案隱含把兩者視為等價遷移，會把 frieren.js 4 條 production 永遠輸出的警告靜默化。對應新增 §1.5 與 Hard Limit #2，新增 `mpuLogger.warnAlways(...)` 收 direct console.warn migration。
- **修正**：補 `infoL()` 進 Logger API（與 §Verification 寫的 `infoL` 對齊）。
- **補充**：新增 `mpuLogger.tFormat(key, fallback, ...values)` 支援 `%s` / `%d` placeholder，與 PHP `sprintf` 一致；明訂「含變數 / 單位 / 順序可能因語言改變的 log 必須用 template」，並補 Hard Limit #9。
- **修正**：`mpuL10n.logs` vs `mpuL10n.logsDebug` payload 拆分從「未來優化」升級為 **MVP 必做**。原 154 條 全 inline 等於每位匿名訪客都收 8.2 KB，其中 ~75% 是 debug-only，划不來。拆分後 production 預設只送 ~2 KB。
- **修正**：`Verification` 章節原寫 Jest，但 repo 沒有 Jest framework。改為 Node smoke script，列為階段 1 可選實作；未來引入 Jest / Vitest 時可升級。
- **補充**：新增「例外清單」章節，明列 `js/ukagaka-base.js` 中 `mpuLogger` 定義前的 `console.log` 為已知例外，mechanical replace 必須跳過或先把 mpuLogger 定義前移。

### Codex 公司覆核（2026-05-22昼，第二輪）

- **反證**：第一輪後的 `logsDebug` 注入條件寫「`WP_DEBUG === true` 或 `mpu_opt['debug_mode']`」，但 `mpu_opt['debug_mode']` PHP grep 全 repo 0 hit，並不存在。已修正為「**僅當 `defined('WP_DEBUG') && WP_DEBUG === true` 時注入**」，單一明確來源，本案不在 scope 內新增 PHP debug option。
- **Plan 之外的 observed defect**：grep 進一步發現 `mpuDebugMode` 在 PHP 端 0 hit，意味著 `window.mpuDebugMode` 從未被注入，`mpuIsDebugMode()` 永遠回 `false`，現有所有 `mpuLogger.log/warn/info` 在 production 都是 dead code。已在 §5 加附註並提供三選項處理建議（推薦獨立小 PR 修 defect，再走本案）。
- **修正（Codex #2）**：`logL()` 本身不會跑 `tFormat()`，原草案範例 `mpuLogger.logL('key', 'fmt %d 秒', 60)` 會輸出「fmt %d 秒 60」而不是替換 `%d`。已新增 `logF / warnF / errorF / infoF / warnAlwaysF` 含 placeholder 糖衣系列，並明訂「`*L` 給純字串、`*F` 給含 placeholder」的 API 選擇規則；§2.5 範例同步改為 `logF`，並補一條 ⚠️ 警示避免誤用 `logL`。
- **修正（Codex #3）**：草案範例 `mpuLogger.log('[MP Ukagaka]', mpuLogger.tFormat(...))` 會雙 prefix。已移除手寫 `[MP Ukagaka]` 並補「禁止寫法」程式碼區塊作防呆教材。
- **修正（Codex #4）**：direct console migration 時，原始字串多半已含 `[MP Ukagaka]` prefix（e.g. `console.error("[MP Ukagaka] Canvas 管理器未初始化")`）。若直接搬到 fallback 會雙 prefix。已在 §1.5 補「Prefix 處理規則」明訂 localized string 與 fallback 字串只放 message body，並加 Hard Limit #10。
- **修正（Codex #5）**：原 `tFormat` 註解寫「values 可為單一值或 array」，但實作 `...values` 不 flatten array，傳 array 會變 `String([60, 'sec'])` 即 `"60,sec"`。已刪掉「array」描述，固定 rest args 合約，並加 Hard Limit #11；§Verification 以 negative test 文件化 array 被當成單一 value 的錯誤後果。

### Codex 公司覆核（2026-05-22昼，第三輪）

- **修正（Codex #1）**：Phase 1 checklist 漏列 `*F` helpers，原本只寫 `t / tFormat / logL / warnL / errorL / infoL / warnAlways` 共 7 個 method，會讓實作者漏做含 placeholder 系列。已補齊：12 個 entry，含 `logF / warnF / errorF / infoF / warnAlwaysF` 共 5 個新增。
- **修正（Codex #2）**：§5 payload 表格沒同步 `*F` 對應的 bucket，導致格式化 log 可能找不到 key 的隱憂。已重寫表格列出純字串 vs 含 placeholder 兩個糖衣 column，並補註說明 `*L` 與 `*F` 共用同一 bucket 的設計理由（i18n key 識別碼不區分字串型態）。
- **修正（Codex #3）**：§1.5 direct console migration 規則只列了純字串路徑，沒提含 placeholder / 變數的情境。實際很多 direct console 帶 `err` 物件 dump 或字串拼接，需明確走 `*F` 系列。已把規則改成完整表格，列出 8 種來源型態與遷移目標，並補「判斷是否含 placeholder」的具體訊號清單。
- **修正（Codex #4）**：原 Verification 寫的 `tFormat(..., [60]) → 'fmt 60'` 邊界測試會讓讀者誤以為 array 是合法 API。已改寫為 **negative test**：`tFormat('key', 'fmt %s %s', [60, 'sec']) → 'fmt 60,sec '`（明確展示 array 被視為單一 `%s` value 的錯誤後果），並補 lint / grep 規則建議三條，包含「禁止 array 參數」自動偵測。
- **補充（Codex 第三輪非阻擋建議）**：在 §1.5 「Prefix 處理規則」之後新增「**Prefix Normalization 是 deliberate**」子段，明說 `[MP Ukagaka]` → `[MP Ukagaka ERROR]` 是規格內的正規化，不是 unintended output change，避免實作 PR 被 reviewer 誤抓。

### Antigravity 公司覆核（2026-05-22昼，第四輪）

- **修復 `window.mpuDebugMode` 的前置 PR**：同意並強烈支持先以獨立小 PR 修復此 bug，以利後續的 logsDebug 與相關 API 的開發及驗證。
- **維持 `tFormat` 診斷值行為**：同意將 `undefined`/`null` 保持為 `"undefined"`/`"null"` 的 String 輸出，以提供精準的除錯訊號；無需在 MVP 實作過於複雜的 `%%` 轉義。
- **CI 靜態檢查規則修正**：接受並同意 Codex 的修正建議。最初提出的 blanket grep (`[一-鿿]`) 會誤判合法的 JS 內部 fallback 字串（例如 `mpuLogger.logL('key', '中文原文')`）。正確的 CI 檢查規則必須只針對：
  1. 帶有中文且未經 i18n 化的原生 `console.*` 呼叫。
  2. 帶有中文且未經 i18n 化的舊 `mpuLogger.*` 呼叫。
  3. 應排除 `*L` 與 `*F` 糖衣呼叫中的第二參數（即合法 fallback 中文字串）。
  4. 建議透過 AST 解析或 ESLint 規則來處理，以防範註解與多行拼接的誤判。

### Antigravity 家裡審查（2026-05-22夜，第一輪）

- **`tFormat` 支援位置定位符**：在 `tFormat()` 中，我們補充了對位置定位符（位置占位符，如 `%1$s`、`%2$s`）的支援。這是為了解決在不同語言（例如日文或英文）翻譯中，常因文法語序與中文顛倒而需要調整變數順序的痛點。此設計與原有順序替換完全向下相容。
- **徹底消除例外清單**：重新檢視 `js/ukagaka-base.js` 最前方的 reload 偵測 IIFE。因為它不依賴下方的任何全域變數，我們決定直接將該 IIFE 移動至 `mpuLogger` 宣告下方，從而徹底消除例外清單。這使核心 JS 檔案達到 100% 潔淨 logger 替換的標準。
- **Ghost 專屬 Log 的 Key 命名規範**：為防止第三方或未來內建多個 Ghost 時的 log keys 與核心外掛衝突，明訂內建 Ghost（如 Frieren）的專用 log keys 必須加上 Ghost 名稱前綴（如 `frierenCanvasInitFailed`）。
- **CI 靜態檢查與 `*L` 糖衣的變數拼接防呆**：明訂靜態檢查或 CI 規則中，必須警告並禁止在 `*L` 系列（純字串）糖衣的第二個參數（fallback）中使用 ES6 模板字串或 `+` 進行變數拼接，因為這會破壞 i18n 機制。格式化輸出必須強制使用 `*F` 系列。
- **Debug 注入邊界防禦**：提醒未來如果外掛引入 PHP 端 debug_mode 選項，應確保 `logsDebug` 的注入條件改為 `(defined('WP_DEBUG') && WP_DEBUG) || mpu_get_option('debug_mode')`，避免前端 debug mode 開啟時 logsDebug 因 WP_DEBUG 未啟動而未被注入，導致 debug log 退回日文 fallback 的問題。

### Codex 家裡審查（2026-05-22夜，第一輪）

- **前端 debug mode 不應裸等於 `WP_DEBUG`**：目前 plan 建議先修 `window.mpuDebugMode` 未注入的 defect，但若直接令 `window.mpuDebugMode = WP_DEBUG`，只要站上誤開 `WP_DEBUG=true`，匿名訪客也會看到大量 front-end debug console。建議前置 PR 改成較保守的條件，例如 `defined('WP_DEBUG') && WP_DEBUG && current_user_can('manage_options')`，或另設明確常數 / filter 控制「是否對前台訪客啟用 JS debug」。`logsDebug` 的注入條件應與最終 `mpuDebugMode` 條件完全一致，避免 payload 與實際輸出行為錯位。
- **PHP inline front-end console 應列入 scope 或例外清單**：repo 內不只 `js/` 與 `ghost/Frieren/` 有 console。`includes/core/frontend-functions.php` 的 inline script 仍有 `console.error("[MP Ukagaka] Init failed:", ...)` 與 `console.error("[MP Ukagaka] AJAX init failed:", ...)`。雖然目前是英文，但它是前端 console surface；若本案標題是 Console Log i18n，應明確列入盤點、遷移，或放進例外清單，避免 source JS 清乾淨後 PHP inline 仍殘留 direct console。
- **Scope 要明確是「中文 console log」還是「所有 console log」**：目前起源是中文 hard-coded console，但 repo 內也有英文 / 日文 console 字串，例如 `Failed to load emoji config`、`SSE Error`、`REST Nonce refreshed via SSE`。若 milestone 只處理中文硬編碼，請在目標與 hard limits 寫清楚；若要處理所有 console log，call site 數量與 key list 需要重新盤點，scope 會明顯變大。
- **`t()` 建議使用 key existence，而非 truthy fallback**：目前 pseudo-code 用 `logs[key] || debugLogs[key] || fallback || key`。若翻譯值意外為空字串，會直接落到 fallback。log 翻譯空字串雖不常見，但 helper 合約更穩的寫法是 `Object.prototype.hasOwnProperty.call(logs, key)` 判斷 key 是否存在，再取值。
- **`%d` 的數字語意要明講**：目前 `tFormat()` 以 `String(value)` 做替換，`%d` 不會做數字格式化、四捨五入或 locale number formatting。這可以接受，但文件應明說 `%s` / `%d` 在 MVP 中只是 placeholder 類型提示，不等於 PHP `sprintf` 的完整格式化能力。
- **每個 JS source PR 後必須 build dist，且 dist 禁止手改**：本案會大量改 `js/` 與 `ghost/Frieren/` call site。分批策略應補上「source 變更後以 `npm run build` 重建 `js/dist/*`，dist diff 只能來自 build，不手寫修改」，避免 review 時無法判斷 bundle 是否可信。
- **內建 Frieren 與第三方 ghost 的邊界要再硬一點**：同意內建 `ghost/Frieren/*` 納入本案，也同意第三方 ghost 自行負責 i18n。建議在 hard limits 或不做清單明寫「只處理 repo 內建 Frieren，不處理使用者自建 / 第三方 ghost」，避免未來擴張到 ghost extension API。

### Claude 家裡審查（2026-05-22夜，第一輪）

- **Source-language convention 與既有 `mpuL10n` 慣例衝突（最關鍵、且未被任何前輪發現）**：grep 驗證 `includes/core/frontend-functions.php:539` 後發現，既有 `mpuL10n` 全數 source string 是**日文**（`読み込みに失敗しました` / `考え中…` / `応答中…` / `エラー`），本案 §3 卻寫「Fallback 字串建議寫**原中文**」。同一 `mp-ukagaka` textdomain 同時混入日文 source 與中文 source 後，譯者拿到合併出來的 .pot 會看到 `読み込みに失敗しました` 與 `閒置偵測已初始化，閾值：%d 秒` 並列，**無法判斷哪些是「原文」、哪些是「待譯」**，會直接影響階段 4 翻譯品質與一致性。凍結前必須三選一明文化：
  - (A) log 也以**日文**為 source（與 UI 完全對齊；代價：164 條中文 log 先翻成日才能 i18n 化，前置工作量極大）
  - (B) **log 中文 / UI 日文混存**（保留現提案、最省力；代價：.pot 是 mixed-source，需在譯者導讀中明文化此情況）
  - (C) 統一以**英文**為 source（WP 業界慣例；代價：UI 既有日文要回譯，動到本案 scope 外）

  **Human 決議：採 (A)**。理由：本作品本身是日文語境，其他語言都是附加翻譯層；log source / fallback 應與既有 `mpuL10n` 日文 source convention 完全對齊。此決議已整合到 §0 Source-language convention、§1 範例、§3 Fallback 策略、Hard Limits 與 Verification。

- **`_n()` plural form 完全沒提**：`errorL` / `warnAlways` 系列在 production 永遠輸出，含計數的 log 翻成英文會出現「Retry failed 1 times」/「1 seconds elapsed」這類錯誤，**而且是直接被站長看到的**。MVP 不一定要做 plural（會放大 API 表面，需新增 `*N` / `*NF` 系列糖衣），但 Hard Limits 應明寫「**MVP 不支援 plural；含計數的 production-visible log 暫接受英文 plural 不正確；plural 支援列為未來獨立 milestone**」，否則實作者會誤把含 `%d 次` 的字串硬塞進 `*F` 然後在 .po 翻成 wrong-plural。

- **譯者 context 完全沒給**：164 個 key 純 `__()`、純 message body，譯者拿到 .pot 完全不知道 `idleDetectionInit` 是「前端 UI 提示」還是「dev console log」、單位是「毫秒」還是「秒」。實作 PR checklist 應該要求**每個 PHP key 上方有 `/* translators: ... */` 註解**，至少對 `mpuL10n.logs`（production-visible 的 ~30 條）強制要求。範例：

  ```php
  /* translators: 開發者 console log；訪客看不到；%d 為毫秒換算後的秒數 */
  'idleDetectionInit' => __('閒置偵測已初始化，閾值：%d 秒', 'mp-ukagaka'),
  ```

  此規則 lint / CI 偵測不到（註解語義無法自動驗證），只能 code review 把關。可在「Lint / grep 規則建議」之後另列「Code review 必查項」。

- **`mpuLogger.t` / `tFormat` 對外暴露的 API 灰色地帶**：`mpuLogger.t('uiButtonText', 'fallback')` 也能拿到 `mpuL10n.logs[key]`，未來會有人把它當通用 i18n helper，繞過既有 `mpuL10n.xxx` 直接存取 contract，造成 UI 字串與 log 字串混在同一 bucket。要嘛 Hard Limit 明禁「`t` / `tFormat` 只在 `mpuLogger.*L` / `*F` / `warnAlways` 系列內部使用，不對外暴露作 UI i18n」，要嘛改名為 `_t` / `_tFormat` 用底線標 internal-only。建議前者，免改 API 簽章。

- **Hard Limit 應補 PII / secret 邊界（新增 #12）**：i18n key 的 fallback 字串與 `mpuL10n.logs[key]` 翻譯值禁止內嵌 session token、API key、user_id、IP、email 等敏感資料。i18n migration scope 內理論上不會發生（既有 log 文字本來就不含這些），但 Hard Limits 章節本來就是「明文化常識邊界」抗未來盲改的場域，補一條成本極低。

- **`t()` 找不到 key 時建議 `console.debug` once（i18n drift surface）**：debug mode 下若 key 拼錯或 PHP 端漏注入，目前只能靠肉眼從 console 看 raw key 字串。可在 `t()` 內加：fallback 觸發時，per-key 只 `console.debug('[MP Ukagaka i18n missing]', key)` 一次（用模組層 `Set` 去重），production 不影響（`console.debug` 預設隱藏），但 i18n drift 浮現速度大幅提升。與家裡 Codex 建議的 `hasOwnProperty` 嚴判 key 存在性互補（hasOwnProperty 決定何時觸發 fallback，console.debug 決定如何被觀察到）。

- **文件本身兩處內部不一致需要在凍結前清理**：
  - §分批策略「階段 3.5：早於 mpuLogger 定義的 console（見「例外清單」）」仍在（line 435-437），但 §例外清單已標記「已透過重構消除」（line 467 之後）。應刪除階段 3.5 整段，並把 line 443「不阻擋階段 1-3.5」改為「不阻擋階段 1-3」。**已整合：階段 3.5 已刪除，阻擋文字已改為階段 1-3。**
  - §3「Fallback 策略」line 327 仍寫「建議寫**原中文**」，與本輪 source-language 三選一決議（待定）需要同步更新。**已整合：fallback 改為日文 source string。**

---

_Last updated: 2026-05-22 — 歷經 Codex 多輪、Antigravity 現場與家裡覆核、Codex 與 Claude 家裡夜間覆核；Human 已決議 source-language 採日文 source/fallback，相關規格已整合。_
