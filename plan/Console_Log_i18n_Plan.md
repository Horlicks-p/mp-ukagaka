# Console Log i18n 計畫（草案）

> 2026-05-22 草案。**狀態：設計討論中，尚未凍結，尚未實作。**
>
> 本文件記錄「將 ~161 條 JS console log 中文字串改為 i18n 化」的需求盤點、架構選項與分批策略，作為未來 milestone（暫定 #X）的設計基礎。在凍結之前，所有決策可調整。

---

## 起源

v2.21.1 patch 撰寫 CHANGELOG 時注意到 console log 字串全部 hard-coded 為繁體中文，不隨 WordPress locale 切換，英日使用者除錯時體驗欠佳。當下評估「順手做」但發現實際規模遠超預期（161 處 call site，11 個檔案），決定獨立 milestone 處理，避免污染 patch 範圍。

---

## 目標

讓 console log 字串能依 WordPress locale 切換語言，同時：

- 不破壞既有 `mpuLogger` debug-mode gating 行為
- 不引入新前端 i18n 框架
- 翻譯成本可分階段攤提，不強求一次補齊
- 至少維持中文 fallback，翻譯缺漏時不出現 `undefined` 或英文 key

本案明確**不做**：

- 不做 server-side PHP log i18n（`mpu_log_*` 留繁中，那是站長/開發者後端 log，不影響訪客）
- 不改變 emoji 慣例（☀️ 🌙 📖 等保留）
- 不引入 i18next / formatjs / vue-i18n 等前端 i18n library
- 不做 ghost-specific log 的第三方擴充 hook（如 `frieren.js` 的 log 由 plugin 統一管，第三方 ghost 自己負責 i18n）
- 不在 production user UI 顯示 log（這些是 console only）

---

## 現狀盤點（2026-05-22）

### Call site 計數（source files，不含 dist bundle）

| 檔案 | mpuLogger 中文 log | console 中文 log |
|---|---:|---:|
| `js/ukagaka-core.js` | 32 | - |
| `js/ukagaka-features.js` | 25 | - |
| `js/ukagaka-base.js` | 20 | 1 |
| `ghost/Frieren/frieren.js` | 20 | - |
| `js/ukagaka-chat.js` | 18 | - |
| `js/ukagaka-context.js` | 15 | - |
| `ghost/Frieren/frieren-emoji.js` | 6 | - |
| `js/ukagaka-emoji.js` | 5 | - |
| `js/ukagaka-anime.js` | 1 | 5 |
| `js/ukagaka-greeting.js` | 4 | - |
| `js/ukagaka-dialog.js` | 2 | - |
| **合計** | **148** | **6** |
| **總計** | | **154** |

（先前快速估算的 161 含部分 dist bundle 雙重計算，去除後實際 source 約 154。）

### Logger 現狀（`js/ukagaka-base.js:316-332`）

```js
const mpuLogger = {
    _isDebug: function () { return mpuIsDebugMode(); },
    log:   function (...args) { if (this._isDebug()) console.log('[MP Ukagaka]', ...args); },
    warn:  function (...args) { if (this._isDebug()) console.warn('[MP Ukagaka]', ...args); },
    error: function (...args) { console.error('[MP Ukagaka ERROR]', ...args); },
    info:  function (...args) { if (this._isDebug()) console.info('[MP Ukagaka]', ...args); }
};
```

特性：

- `error` 永遠輸出；其餘三者只在 debug mode 輸出
- 自動加 `[MP Ukagaka]` / `[MP Ukagaka ERROR]` prefix
- 支援 rest args，可帶物件 / 變數一起 dump

### Log 字串型態（取樣）

| 型態 | 範例 | 比例估計 |
|---|---|---|
| 純字串 | `mpuLogger.log("jQuery ready 已執行")` | ~60% |
| 字串 + 變數 args | `mpuLogger.log("閒置偵測已初始化，閾值：", threshold / 1000, "秒")` | ~25% |
| 字串拼接條件 | `"☀️ 芙莉蓮被喚醒了！" + (isForced ? " (forceWakeUp)" : "")` | ~5% |
| 字串 + 物件 dump | `mpuLogger.warn("mpu_get_settings: 無效的回應", res)` | ~10% |

純字串占大宗，i18n 替換相對容易；後三類需要保留 args 傳遞語意。

---

## 架構決策

### 1. 字串來源：`mpuL10n.logs` 子物件

PHP 端（`includes/core/frontend-functions.php` 的 `wp_localize_script`）：

```php
'logs' => [
    'jqueryReady'          => __('jQuery ready 已執行', 'mp-ukagaka'),
    'wakeUpFrieren'        => __('☀️ 芙莉蓮被喚醒了！', 'mp-ukagaka'),
    'wakeUpFrierenForced'  => __('☀️ 芙莉蓮被喚醒了！(forceWakeUp)', 'mp-ukagaka'),
    'skipBookFlipAfterWake'=> __('📖 喚醒後跳過翻書動畫', 'mp-ukagaka'),
    // ... 約 150 條
]
```

每條都用 `__()` 包裹，落到 `mp-ukagaka` textdomain。emoji 保留在字串內，不抽出（emoji 是語言中性，翻譯時譯者直接複製到目標語言版本即可）。

### 2. Logger API 擴展（向下相容）

`mpuLogger` 新增 i18n 取值方法，不取代現有 `log/warn/error/info` API：

```js
const mpuLogger = {
    _isDebug: () => mpuIsDebugMode(),

    // 既有 API 不動
    log:   (...args) => { if (mpuIsDebugMode()) console.log('[MP Ukagaka]', ...args); },
    warn:  (...args) => { if (mpuIsDebugMode()) console.warn('[MP Ukagaka]', ...args); },
    error: (...args) => console.error('[MP Ukagaka ERROR]', ...args),
    info:  (...args) => { if (mpuIsDebugMode()) console.info('[MP Ukagaka]', ...args); },

    // 新增 i18n 取值 helper
    t: function (key, fallback) {
        const logs = (typeof mpuL10n !== 'undefined' && mpuL10n && mpuL10n.logs) || {};
        return logs[key] || fallback || key;
    },

    // 新增 i18n 化 log 方法（可選糖衣）
    logL: function (key, fallback, ...args) {
        if (this._isDebug()) console.log('[MP Ukagaka]', this.t(key, fallback), ...args);
    },
    warnL: function (key, fallback, ...args) {
        if (this._isDebug()) console.warn('[MP Ukagaka]', this.t(key, fallback), ...args);
    },
    errorL: function (key, fallback, ...args) {
        console.error('[MP Ukagaka ERROR]', this.t(key, fallback), ...args);
    },
};
```

Call site 改寫範例：

```js
// Before
mpuLogger.warn("mpu_get_settings: 無效的回應", res);

// After（使用 logL/warnL 糖衣）
mpuLogger.warnL('getSettingsInvalidResponse', 'mpu_get_settings: 無効な応答', res);

// After（不用糖衣，平鋪）
mpuLogger.warn(mpuLogger.t('getSettingsInvalidResponse', 'mpu_get_settings: 無効な応答'), res);
```

兩種寫法並存，避免大規模 mechanical replace 出錯時整批失效。建議優先用糖衣形式（`logL`/`warnL`/`errorL`），call site 較簡潔。

### 3. Fallback 策略

`mpuLogger.t(key, fallback)` 三層 fallback：

1. `mpuL10n.logs[key]` 存在 → 用翻譯後的字串
2. 翻譯缺漏（key 不存在於 `mpuL10n.logs`）→ 用 caller 提供的 `fallback`（建議寫**原中文字串**）
3. caller 沒給 fallback → 退回 key 本身（顯示如 `getSettingsInvalidResponse`，明顯是 i18n 漏網之魚，方便除錯）

**Fallback 字串建議寫原中文**，不寫英文。理由：

- 翻譯漏網時，UI 顯示中文 ≥ 顯示英文（既有行為是中文，符合最小驚訝原則）
- caller 端閱讀程式碼時，中文 fallback 比 key 更易理解該 log 的語境
- `mpuL10n.logs` 若整個 PHP 端忘記注入，整個 plugin 仍可運作

### 4. Key 命名規範

- camelCase
- 結構：`<domain><Action><Context>` 或 `<domain><Event>`
- 範例：
  - `wakeUpFrieren` / `wakeUpFrierenForced`
  - `skipBookFlipAfterWake`
  - `getSettingsInvalidResponse`
  - `idleDetectionInit` / `idleDetectionUpdateFailed`
- 不用點分隔（避免 `mpuL10n.logs['wake.up.frieren']` 這種 JS access pattern 醜陋）
- 不用底線
- 縮寫小寫優先：`api`、`llm`、`ai`（不寫 `API`/`LLM`/`AI`）

### 5. Payload 大小評估

平均每條 key + 中文字串約 50 bytes（key ~25b + 字串 ~25b + JSON 包裝 ~5b）。154 條 ≈ 7.7 KB inline JSON。

對比：目前 `mpuL10n` 已 inline 各種設定字串約 2-4 KB。新增 8 KB 是一倍以上的 payload 增長。

優化選項（**MVP 不做，列為未來改善**）：

- 切分 `mpuL10n.logs` 與 `mpuL10n.logsDebug`，僅在 `WP_DEBUG` 為 true 時送出 debug logs
- 從 `mpu_localize_settings()` 改為 REST endpoint lazy fetch，僅在 console 觸發第一條 log 時才下載
- 用 sub-resource 拆分 inline payload

---

## 分批策略

**不允許一個 PR 改 154 條**。風險太高，code review 不可能徹底。分階段：

### 階段 1：基礎設施（無 behavior change）

- 新增 `mpuLogger.t()` / `logL()` / `warnL()` / `errorL()` 方法
- 新增 `mpuL10n.logs` 空物件 placeholder（PHP 端發送 `[]`）
- 不改任何 call site
- 補單元測試：`t()` 三層 fallback 行為

預估：1 個 PR，~50 行 diff。

### 階段 2：高優先 call site（error / warn）

只改 `mpuLogger.error()` 與 `mpuLogger.warn()` call site。原因：

- `error` 永遠輸出，使用者實際看得到，i18n 價值最高
- `warn` 通常代表預期外狀況，使用者可能回報，i18n 後易於跨語言溝通
- 估計 ~40 條（154 中約 25-30%）

預估：3-5 個 PR，每 PR 改 1-2 個檔案，方便 review。

### 階段 3：debug log（mpuLogger.log / info）

剩餘 ~110 條，純除錯用。i18n 價值低於 error/warn，但完成後一致性高。

預估：5-8 個 PR，按檔案分批。

### 階段 4：translation

`.po` / `.mo` 補日英翻譯。可由社群 / 機器翻譯 + 校對。

**不阻擋階段 1-3**：階段 1-3 完成後，所有 call site 已 i18n 化，缺翻譯時 fallback 為中文（=原行為），不影響任何使用者。

---

## Hard Limits

1. 不破壞 `mpuLogger.error/warn/log/info` 既有 API 簽章。新方法以 `L` 後綴並存。
2. 所有 i18n 化的 call site 必須提供 fallback 字串（中文原文）。禁止傳 `mpuLogger.t('key')` 不帶 fallback。
3. `mpuL10n.logs` 缺失（PHP 端意外沒注入）時，logger 仍可運作，落到 fallback 字串。
4. 不引入新 npm dependency。
5. 不改變既有 debug-mode gating 行為（log/warn/info 仍 gated，error 仍永遠輸出）。
6. emoji 留在字串內，不抽出。
7. PHP 端 `mpu_log_*` 不在本案範圍。

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

### Jest / Unit

- `mpuLogger.t('existingKey', 'fallback')` → 回 `mpuL10n.logs.existingKey`
- `mpuLogger.t('missingKey', 'fallback')` → 回 `'fallback'`
- `mpuLogger.t('missingKey')` → 回 `'missingKey'`
- `mpuLogger.t('key')` with `mpuL10n` undefined → 不 throw，回 `'key'`

### Manual

- 切換 WP locale 為 `ja`，重整頁面，觸發 wake-up，console 出現日文版 log（假設翻譯已補）
- 故意拔掉 `mpuL10n.logs.wakeUpFrieren`，觸發 wake-up，console 出現中文 fallback
- production mode（`mpuIsDebugMode()` 回 false），所有 `logL` / `warnL` / `infoL` 不輸出，但 `errorL` 仍輸出

---

## 與其他 Plan 文件的關係

| 文件 | 關係 |
|---|---|
| `Engineering_Quality_Improvement_Plan.md` | 本案待加入指標項 |
| `Code_Quality_Hardening_Plan.md` | 本案可視為 hardening 延伸，但獨立 milestone |
| `Observation_Buffer_Design.md` | 無直接關係，但同為前端 i18n 補強的相鄰議題 |

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

_Last updated: 2026-05-22 — 草案，待審查後凍結。_
