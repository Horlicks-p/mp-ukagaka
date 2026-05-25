# Console Log i18n — Phase 1 / 1.5 / 2 落地進度 + Handoff

> 2026-05-25 公司端整理 — 給家裡御三家審 + 接手用。
> 凍結文件仍是 `Console_Log_i18n_Plan.md`（設計凍結，不動）；本文件只記**執行狀態**與**待決策事項**。
> 接手者拉到 `feature/code-quality-hardening` 後從本文件入口。

---

## TL;DR

- Phase 1（infrastructure）已完成、Phase 1.5（inventory + 18 條 production-visible 翻譯）已完成、Phase 2 第一個 migration PR（`js/ukagaka-anime.js` 5 條）已完成。
- 公司端 reviewer 已逐 commit 審查通過，無阻斷問題。
- **下一個 migration PR 開工前必須先處理一個 silent-drop 隱患**（generator 不抓糖衣 → migrated row 在 inventory 中消失但 overrides 變 orphan）。需家裡決策 A / B / C 三方案（見下方）。
- 公司端建議：Phase 2 第二個 PR 走 `ghost/Frieren/frieren.js` decoration config 4 條。最終由家裡決定。

---

## Commit 序列（feature/code-quality-hardening，9 個未 push commit）

| Commit  | 主旨                                          | 階段       |
| ------- | --------------------------------------------- | ---------- |
| e13f234 | Expose frontend debug mode flag               | Blocking defect 前置 PR |
| ad6581a | Freeze console log i18n plan                  | Plan 凍結  |
| ff75040 | Add console log i18n infrastructure           | Phase 1    |
| 8e9432e | Align console log i18n fallback behavior      | Phase 1 hotfix（spec 對齊） |
| 54f6bbf | Use REST fail helper for observation auth     | 不相關（observation 收尾） |
| c5989d8 | Inventory console log translation table       | Phase 1.5 Commit A |
| 45f0625 | Translate production console log inventory    | Phase 1.5 Commit B（18 條） |
| 3a1f283 | Refine production console log translations    | Phase 1.5 hotfix（emoji + Canvas msgid） |
| b256574 | Migrate anime console logs to i18n            | Phase 2 第一個 migration PR |

---

## 已落地項目（凍結）

### Phase 0 — Blocking defect（e13f234）
- 新增 `mpu_is_frontend_debug_mode()` at `includes/core/frontend-functions.php:587`
- 條件：`defined('WP_DEBUG') && WP_DEBUG && current_user_can('manage_options')`
- filter：`mpu_frontend_debug_mode`
- `mpu_head()` inline 注入 `window.mpuDebugMode = <bool>`
- Plan §-1 hard requirement，已 merge 在 i18n 之前

### Phase 1 — Infrastructure（ff75040 + 8e9432e）
- `MPU_Log_I18n_Builder` 類別 + `mpu_console_log_i18n_builder()` factory（per-request stateless instance）
- `includes/core/class-mpu-log-i18n-builder.php`
- Module load 順序在 `network-functions.php` 之後、`personality/*` 之前（`mp-ukagaka.php:76`）
- `mpuLogger` 12 個新方法：`t / tFormat / logL / warnL / errorL / infoL / logF / warnF / errorF / infoF / warnAlways / warnAlwaysF`
- `t()` 用 `hasOwnProperty` 嚴格判定、無 fallback 時回 `String(key)`、missing key 用 `console.debug` per-key 去重
- `tFormat()` 用 `/%\d+\$[sd]/.test(tpl)` 偵測 positional placeholder（不裸用 `includes("$")`）
- `mpuL10n.logs` 一律注入、`mpuL10n.logsDebug` 在 `mpu_is_frontend_debug_mode()` 為 true 才注入（payload 與輸出條件同源）
- Smoke test：`tools/node/test-logger-smoke.js`，用 `vm.runInContext` 抽 `js/ukagaka-base.js` 實際 logger block（不是複製假 logger）
- `npm run verify` 已串入 `npm run test:logger`

### Phase 1.5 — Translation table（c5989d8 + 45f0625 + 3a1f283）
- Inventory generator：`tools/node/generate-console-log-inventory.js`（332 行，含括弧 / quote / template literal 巢狀解析）
- 對照表：`plan/translation-tables/console-logs-zh-to-ja.md`
- Inventory 統計：**213 included rows + 1 backlog**
  - TODO:console.log: 1
  - logs:console.error: 11
  - logs:console.warn: 5
  - logs:mpuLogger.error: 1
  - logsDebug:mpuLogger.log: 159
  - logsDebug:mpuLogger.warn: 36
- Generator 設計：`overrides` table 注入 semantic key / jaSource / translatorComment，lookup 用 `relativePath:line:channel`，raw inventory 仍從 source 重新生成（determinism 與翻譯成果共存）
- 18 條 production-visible（含 1 條 TODO bucket）全部翻成日文 + translator comment：
  - `frieren*` 11 條（shellInfo、Image/Draw CanvasManager、ImageLoad、DecorationConfig×4、PixelCanvas、PixelData、TouchZoneDialog）
  - `anime*` 5 條（CanvasElement、CanvasContext、FrierenManager、ImageLoad、FrameImageLoad）
  - `jqueryCookieInitFailed` 1 條
  - `pageReloadClearedChatSession` 1 條（TODO bucket，console.log 待 migration 時逐條決定 debug-only-or-prod）
- Backlog 1 條（既有日文 source）：`frierenDecorationDialogRequestFailed`
- Hotfix（3a1f283）：
  - `pageReloadClearedChatSession` emoji 🔄 補回（Hard Limit #7）
  - `frierenImageCanvasManagerMissing` → 「画像読み込み前に Canvas マネージャーが…」
  - `frierenDrawCanvasManagerMissing` → 「描画前に Canvas マネージャーが…」
  - 兩條 msgid 差異化，避免 `.po` 工具 merge

### Phase 2 第一個 migration PR — `js/ukagaka-anime.js` 5 條（b256574）
- `console.error` 純字串 ×2 → `mpuLogger.errorL`（line 65 / 72）
- `console.error` + 動態 arg ×2 → `mpuLogger.errorF` `%s`（line 160 / 221）
- `console.warn` 純字串 ×1 → `mpuLogger.warnAlways`（line 89）— **不是 warnL**，遵守 Hard Limit #2
- PHP 端 5 條全部 `$log_i18n->always()` 註冊（`frontend-functions.php:543-554`）
- 每條都有 `/* translators: ... */` 註解（英文寫，與日文 fallback 分開）
- fallback 字串無 `[MP Ukagaka]` prefix（Hard Limit #10）
- dist rebuild、test:logger、PHPUnit 48/108 全過
- 三語 CHANGELOG + DEVELOPER_GUIDE 更新

---

## ⚠️ 待家裡決策：silent-drop 隱患（**進下一個 PR 前必處理**）

### 問題

`generate-console-log-inventory.js:37` 的 `callPattern`：

```js
\b(?:(console)\.(log|warn|error|info)|(mpuLogger)\.(log|warn|error|info))\s*\(
```

**不抓** `errorL` / `errorF` / `warnAlways` / `logL` / `warnL` / `logF` / `warnF` / `infoL` / `infoF` / `warnAlwaysF` 等糖衣。

Phase 2 第一個 PR (b256574) 後 anime.js 5 條 call site 已遷移到糖衣。下次重跑 `npm run inventory:logs`：

1. callPattern 不匹配 → 主表 213 → 208 row（5 條 row 消失，這是 deliberate）
2. **但 generator overrides table 內 5 條條目仍在 → 變 orphan**
3. 因為 unused-override warning 尚未實作（原列為 Phase 1.5 nice-to-have），generator **silent drop** 不警告

每完成一個 migration PR 都會累積 orphan，Phase 3 完成後累積 ~213 條 silent orphan。

### 三方案（家裡擇一）

**方案 A — git history 作 trace 權威，overrides 條目刪除**
- migrate 完成的 5 條 overrides 直接刪
- generator 加 unused-override warning（防護未來新增的 override 拼錯）
- `git show b256574^:plan/translation-tables/...` 看 PR 前狀態
- 優點：最簡單；缺點：翻譯權威分散在 git history，audit 麻煩

**方案 B — overrides 條目加 `migrated: true` flag**（公司端建議）
```js
"js/ukagaka-anime.js:65:console.error": {
    key: "animeCanvasElementMissing",
    jaSource: "Canvas 要素が存在しません",
    translatorComment: "...",
    migrated: true,  // ← 不視為 orphan
},
```
- generator verify pass 看到 `migrated: true` 跳過 orphan warning
- 翻譯權威集中在 overrides table（適合 `.po` 翻譯回查）
- 改動小（generator 加 ~10 行 verify pass + override 加 flag）
- 缺點：overrides table 持續累積（最終 213 條）

**方案 C — 擴展 generator 抓糖衣 + 加 migration status column**
- callPattern 加 `errorL|errorF|warnAlways|warnAlwaysF|logL|warnL|logF|warnF|infoL|infoF`
- 對照表新增 `Migration status: pending / migrated` column
- migrated row 不消失，可繼續審查
- 改動大（~50 行 generator + 對照表 schema 調整）
- 注意 regex 不要誤抓 `tFormat` / `t(` 等內部 helper

公司端建議方案 B（改動最小、翻譯權威集中、verify pass 把 silent drop 升級成 explicit error）。家裡若偏好別的方案以家裡為準。

---

## 待家裡決策：Phase 2 下一個 PR 範圍

剩 13 條 production-visible（18 - anime 5 = 13）：

| 候選 | 條數 | 評估 |
|---|---|---|
| **`ghost/Frieren/frieren.js` decoration config 4 條** (493/497/501/512) | 4 | **公司端首選**：同一 flow、含 warnAlways + errorF 兩種 pattern、第一個 ghost-specific PR 可驗證 `frieren*` prefix CI lint |
| `js/ukagaka-features.js:7` 單條 (`jqueryCookieInitFailed`) | 1 | 太小，但可當熱身 PR |
| `frieren.js` Canvas/Image/Pixel/Touch 7 條 (52/86/114/298/728/799/1505) | 7 | 跨 flow，建議拆 2 PR |
| `base.js:99` (`pageReloadClearedChatSession`) | 1 | TODO bucket，需先決 debug-only-or-prod |

---

## 未做的 nice-to-have（Commit B2 / Phase 2 後續處理）

從 Phase 1.5 公司端 review 留下的 4 條：

- **(c)** `frierenPixelDataUnavailable` 條 `%1$s / %2$s` 改成 `タイプ=%1$s、メッセージ=%2$s` 自然日語格式
- **(d)** `callPreview()` 還原 surrogate pair escape（`🔄` → 🔄），目前 `extractStrings()` 已有 unescape 邏輯可複用到 callPreview
- **(e)** Generator 加 unused-override verify pass（**已與上述 silent-drop 方案重疊**，做方案 A/B/C 時順手做）
- **(f)** Generator 內嵌的對照表 status block 已過時（line 392-397 still 寫「This commit intentionally fills raw inventory only」），Commit B2 順手更新

debug 195 條翻譯（Commit B2）尚未動工，先處理上述 silent-drop 決策再進。

---

## 接手 SOP

1. **拉新版**：`git pull origin feature/code-quality-hardening`
2. **檢視 9 個未驗 commit** — 公司端已逐 commit 審過，但家裡可獨立 review
3. **決策 silent-drop 方案 A / B / C** — 這是進下一個 migration PR 的前置
4. **決策 Phase 2 第二個 PR 範圍** — 公司端建議 `frieren.js` decoration config 4 條
5. （可選）順手處理 nice-to-have (c)(d)(f)
6. 啟動下一個 migration PR

---

## 驗證指令備忘

```powershell
# Generator + smoke
node tools\node\generate-console-log-inventory.js
npm run inventory:logs          # 從 tools/node/ 目錄跑
npm run test:logger             # 從 tools/node/ 目錄跑
npm run build                   # rebuild dist

# Lint
php -l includes\core\frontend-functions.php
php -l includes\core\class-mpu-log-i18n-builder.php
node --check js\ukagaka-base.js
node --check js\ukagaka-anime.js
node --check tools\node\generate-console-log-inventory.js
node --check tools\node\test-logger-smoke.js

# PHPUnit
tools\php\vendor\bin\phpunit.bat --configuration tests\phpunit.xml.dist --colors=always

# Pre-commit
git diff --check
git status --short
```

---

## 與 Plan 文件的關係

- `Console_Log_i18n_Plan.md`：設計凍結，不動。家裡若決策 A/B/C 後需要更新 Plan §1.5 / §現狀盤點，再開 hotfix commit 同步。
- 本文件（`Console_Log_i18n_Progress.md`）：執行進度與待決策事項，每完成一個 PR 後更新。

---

_Last updated: 2026-05-25 — Phase 2 第一個 migration PR (b256574) 合進後。下一更新點：silent-drop 方案決定 + Phase 2 第二個 PR 啟動前。_
