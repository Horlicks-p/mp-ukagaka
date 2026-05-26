# Console Log i18n — Phase 1 / 1.5 / 2 落地進度 + Handoff

> 2026-05-25 公司端整理 — 給家裡御三家審 + 接手用。
> 凍結文件仍是 `Console_Log_i18n_Plan.md`（設計凍結，不動）；本文件只記**執行狀態**與**待決策事項**。
> 接手者拉到 `feature/code-quality-hardening` 後從本文件入口。

---

## TL;DR

- Phase 1（infrastructure）已完成、Phase 1.5（inventory + 18 條 production-visible 翻譯）已完成、Phase 2 production-visible migration 已完成：anime 5 條、Frieren 11 條、core bundle 收尾 2 條。
- 公司端 reviewer 已逐 commit 審查通過，無阻斷問題。
- silent-drop 隱患已採 **方案 A** 落地：migrated overrides 刪除、generator 加 unused-override verify pass；目前對照表為 195 included rows + 0 backlog。
- production-visible 已清零；Phase 3（約 195 條 debug-gated `mpuLogger.log/warn/info`）留給公司端御三家處理。

---

## Commit 序列（feature/code-quality-hardening；2026-05-25 session 後已 push 至 origin）

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
| 0e1904f | Document Phase 1/1.5/2 progress and handoff   | 進度文件 + handoff |
| 0241ecc | Migrate Frieren decoration-config console logs to i18n | Phase 2 第二個 migration PR（+ 方案 A 工具落地：verify pass、刪 anime overrides） |
| 9d7a2f0 | Migrate Frieren canvas/image/pixel/touch console logs to i18n | Phase 2 第三個 migration PR（7 條） |
| ad68012 | Migrate core-bundle console logs to i18n and finish Phase 2 production-visible | Phase 2 收尾（features.js + base.js:99 + rebuild dist；首條 logsDebug） |
| this commit | Migrate Frieren decoration dialog error to i18n | Phase 2.5 收尾（既有日文 production-visible backlog 歸零） |
| next commit | Stabilize console log inventory override lookup | Phase 3-pre（override lookup 改為 `relativePath::zhOriginal`，解掉行號漂移） |
| next commit | Add console log inventory semantic key gate | Phase 3-0 guard（每次 inventory 自動擋 semantic key 衝突） |
| next commit | Translate core debug console log inventory | Phase 3-0 commit 1（`js/ukagaka-core.js` 57 條） |
| next commit | Translate base chat and anime debug console log inventory | Phase 3-0 commit 2（base/chat/anime 41 rows，38 overrides） |
| next commit | Translate features and context debug console log inventory | Phase 3-0 commit 3（features/context 46 rows） |
| next commit | Translate Frieren debug console log inventory | Phase 3-0 commit 4（Frieren/Frieren emoji 32 rows） |

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
- Inventory generator：`tools/node/generate-console-log-inventory.js`（359 行，含括弧 / quote / template literal 巢狀解析，並有 unused-override verify pass）
- 對照表：`plan/translation-tables/console-logs-zh-to-ja.md`
- Inventory 統計：Phase 1.5 翻譯完成時為 **213 included rows + 1 backlog**；Phase 2.5 production-visible backlog 清理後，目前為 **195 included rows + 0 backlog**
  - logs:console.error: 0（Phase 1.5 時為 11；anime 4 條、Frieren 7 條已 migrate）
  - logs:console.warn: 0（Phase 1.5 時為 5；anime 1 條、Frieren 4 條已 migrate）
  - logs:mpuLogger.error: 0（`jqueryCookieInitFailed` 已 migrate）
  - logsDebug:mpuLogger.log: 159
  - logsDebug:mpuLogger.warn: 36
- Generator 設計：`overrides` table 注入 semantic key / jaSource / translatorComment，lookup 用 `relativePath::normalized zhOriginal`，raw inventory 仍從 source 重新生成（determinism 與翻譯成果共存）
- 18 條 production-visible（含 1 條 TODO bucket）全部翻成日文 + translator comment：
  - `frieren*` 11 條（shellInfo、Image/Draw CanvasManager、ImageLoad、DecorationConfig×4、PixelCanvas、PixelData、TouchZoneDialog）
  - `anime*` 5 條（CanvasElement、CanvasContext、FrierenManager、ImageLoad、FrameImageLoad）
  - `jqueryCookieInitFailed` 1 條
  - `pageReloadClearedChatSession` 1 條（原 TODO bucket；已拍板改為 debug-only 並完成 migration）
- Backlog 已清零；既有日文 source `frierenDecorationDialogRequestFailed` 已於 Phase 2.5 納入 `logs` bucket。
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

### Phase 2 第二個 migration PR — `ghost/Frieren/frieren.js` decoration config 4 條（working tree）
- `console.warn` + 動態 fallback ×1 → `mpuLogger.warnAlwaysF`（line 493）— 保留 production always-output 行為
- `console.error` + 動態 arg ×1 → `mpuLogger.errorF`（line 497）
- `console.warn` 純字串 ×2 → `mpuLogger.warnAlways`（line 501 / 512）
- PHP 端 4 條 log strings 全部 `$log_i18n->always(..., ['scope' => 'frieren'])` 註冊，另補 1 條完整 unknown-error log key `frierenDecorationConfigLoadFailedUnknown`（`frontend-functions.php:554-563`），同時驗證 `frieren*` prefix guard；call site 不直接呼叫 `mpuLogger.t()`，維持 `t()` / `tFormat()` internal-only 規格
- 每條都有 `/* translators: ... */` 註解（英文寫，與日文 fallback 分開）
- fallback 字串無 `[MP Ukagaka]` prefix（Hard Limit #10）
- 依方案 A 刪除對應 4 條 overrides，translation table 重生為 204 included rows + 1 backlog

### Phase 2 第三個 migration PR — `ghost/Frieren/frieren.js` Canvas/Image/Pixel/Touch 7 條（working tree）
- `console.error` 純字串 ×3 → `mpuLogger.errorL`（line 52 / 86 / 298）
- `console.error` + 動態字串值 ×2 → `mpuLogger.errorF`（line 114 / 732）
- `console.warn` + 動態字串值 ×1 → `mpuLogger.warnAlwaysF`（line 803）— 保留 production always-output 行為
- `console.error` + Error 物件 dump ×1 → `mpuLogger.errorL(..., err)`（line 1510）— 保留 Error object 作為 console 額外參數，不強制字串化
- PHP 端 7 條全部 `$log_i18n->always(..., ['scope' => 'frieren'])` 註冊（`frontend-functions.php:564-577`），同時驗證 `frieren*` prefix guard
- `frierenPixelDataUnavailable` 順手採用較自然日文格式：`タイプ=%1$s、メッセージ=%2$s`
- 每條都有 `/* translators: ... */` 註解（英文寫，與日文 fallback 分開）
- fallback 字串無 `[MP Ukagaka]` prefix（Hard Limit #10）
- 依方案 A 刪除對應 7 條 overrides，translation table 重生為 197 included rows + 1 backlog

### Phase 2 core bundle 收尾 — `features.js:7` + `base.js:99` 2 條（working tree）
- `js/ukagaka-features.js:7`：`mpuLogger.error` → `mpuLogger.errorL`，PHP 端 `$log_i18n->always('jqueryCookieInitFailed', ...)`
- `js/ukagaka-base.js:99`：direct `console.log` → `mpuLogger.logL`，PHP 端 `$log_i18n->debug('pageReloadClearedChatSession', ...)`
- `base.js:99` 已拍板改為 debug-only；這是 milestone 第一條 debug-gated migration
- 為避免 `mpuLogger` 尚未定義，reload cleanup IIFE 改成 `mpuClearReloadChatSession()`，於 `mpuLogger` 定義後呼叫
- 依方案 A 刪除對應 2 條 overrides，translation table 重生為 195 included rows + 1 backlog
- source 改動位於 core bundle，已執行 `node tools/node/build.js` 重建 `js/dist/ukagaka-bundle.js` / `.min.js`

### Phase 2.5 production-visible backlog 收尾 — `ghost/Frieren/frieren.js` 1 條（this commit）
- `ghost/Frieren/frieren.js:1201`：既有日文 `mpuLogger.error` → `mpuLogger.errorL('frierenDecorationDialogRequestFailed', ..., error)`
- PHP 端 `$log_i18n->always('frierenDecorationDialogRequestFailed', ..., ['scope' => 'frieren'])`
- 這條是 always-output production log，屬 `logs` bucket，不屬 Phase 3 `logsDebug`
- 依方案 A 刪除最後一條 backlog override，translation table 重生為 195 included rows + 0 backlog

### Phase 3-pre generator lookup 重構 — `tools/node/generate-console-log-inventory.js`（next commit）
- override lookup 從 `relativePath:line:channel` 改為 `relativePath::normalized zhOriginal`
- 解掉 Phase 3-0 一次填 195 條 debug 翻譯時的行號漂移風險；後續 migration 改動同檔案行數時，不會讓未遷移 override 因 line number 失配而 orphan
- `usedOverrides` / unused-override verify pass 保持原邏輯；一條 override 可命中多列，適用於重複 log 文字共用翻譯
- Phase 3-0 已知重複原文應共用單一 override：`js/ukagaka-base.js::請求已取消: ${requestId}`、`js/ukagaka-chat.js::裝飾物對話進行中，忽略按鈕點擊`、`js/ukagaka-chat.js::訊息被阻擋，忽略按鈕點擊`
- override key 建議直接照抄對照表的 `Source file` 欄 + `::` + `zh-TW original` 欄；含 `${...}` 的 key 用一般引號，不用 backtick

### Phase 3-0 semantic key gate — `tools/node/generate-console-log-inventory.js`（next commit）
- generator 每次 inventory 都會檢查最終 semantic key 衝突
- 同一 key 可被多列共用，但所有列必須來自同一個 `overrideKeyFor(sourceFile, zhOriginal)`；這放行同檔相同 log text 的合法共用翻譯
- 不同 override entry 若產生同一 key，或 override key 撞到其他 row 的 draftKey，會在寫表前 fail-fast
- no-TODO / no-draftKey 屬 Phase 3-0 最終驗收 gate，不在每個 staged commit 中強制，因為中途仍會有未填檔案

### Phase 3-0 commit 1 — `js/ukagaka-core.js` 57 條（next commit）
- 只填 generator overrides 與 translation table；不改 JS call site
- `js/ukagaka-core.js` 57 條 `logsDebug` 已改為 semantic key、日文 fallback、英文 translator comment
- `npm run inventory:logs` 仍為 195 included rows + 0 backlog，semantic-key gate 通過
- 剩餘 Phase 3-0 staged commits：base/chat/anime（41）、features/context（46）、Frieren/Frieren emoji（32）、dialog/greeting/emoji（19）

### Phase 3-0 commit 2 — `js/ukagaka-base.js` + `js/ukagaka-chat.js` + `js/ukagaka-anime.js` 41 rows（next commit）
- 只填 generator overrides 與 translation table；不改 JS call site
- `ukagaka-base.js` 20 rows、`ukagaka-chat.js` 20 rows、`ukagaka-anime.js` 1 row 已改為 semantic key、日文 fallback、英文 translator comment
- 實際新增 38 個 overrides；3 組 duplicate 共用 key：
  - `requestCancelled` covers `ukagaka-base.js` lines 1035 / 1042
  - `chatButtonIgnoredDecorationDialogActive` covers `ukagaka-chat.js` lines 974 / 1070
  - `chatButtonIgnoredMessageBlocking` covers `ukagaka-chat.js` lines 980 / 1076
- `npm run inventory:logs` 仍為 195 included rows + 0 backlog，semantic-key gate 通過

### Phase 3-0 commit 3 — `js/ukagaka-features.js` + `js/ukagaka-context.js` 46 rows（next commit）
- 只填 generator overrides 與 translation table；不改 JS call site
- `ukagaka-features.js` 27 rows、`ukagaka-context.js` 19 rows 已改為 semantic key、日文 fallback、英文 translator comment
- 本批沒有 duplicate source text；全部 row 皆使用一對一 semantic key
- `npm run inventory:logs` 仍為 195 included rows + 0 backlog，semantic-key gate 通過
- 剩餘 Phase 3-0 staged commits：Frieren/Frieren emoji（32）、dialog/greeting/emoji（19）

### Phase 3-0 commit 4 — `ghost/Frieren/frieren.js` + `ghost/Frieren/frieren-emoji.js` 32 rows（next commit）
- 只填 generator overrides 與 translation table；不改 JS call site
- `frieren.js` 26 rows、`frieren-emoji.js` 6 rows 已改為 semantic key、日文 fallback、英文 translator comment
- 「喚醒」相關 fallback 沿用前批校正後的 `目覚め / 目を覚ます` 詞彙
- `npm run inventory:logs` 仍為 195 included rows + 0 backlog，semantic-key gate 通過
- 剩餘 Phase 3-0 staged commit：dialog/greeting/emoji（19）

---

## ✅ 已決策（2026-05-25 家裡）：silent-drop 隱患採方案 A

> 家裡 Claude 審視 generator 實作後提出反對 B 的論證；家裡 Codex 與 Gemini 看過意見後一致同意採 **方案 A**。本結論待告知公司御三家。

### 決策理由（為何 A 優於公司原建議的 B）

核心在於重新認定這張對照表的本質 —— 它是 **migration 前的 staging 工作表，不是 runtime 翻譯權威**。

1. **權威已不在表內。** generator override 的 lookup key 是 `relativePath:line:source.channel`，callPattern 只抓 `console.*` 與 `mpuLogger.log/warn/error/info`（不抓糖衣），raw inventory 每次從原始碼重生。字串一旦 migrate，權威即搬到 **PHP `__()`**（runtime + `.pot` 抽取來源）與 **`.po`/`.mo`**（譯者回查處）。已比對：`frontend-functions.php:545` 的 `animeCanvasElementMissing` / `Canvas 要素が存在しません` 與 override 表完全一致。B 主打的「集中翻譯權威」在此架構下是假象。

2. **B 累積的是墓碑。** migrate 後 line 65 已不是 `console.error`（變 `errorL`、可能換行），但 override key 仍寫死 `:65:console.error`，永遠不會再被任何 call site 命中。`migrated: true` 是保留這個對不上的死 key、再掛旗子**抑制** orphan 警告；Phase 3 完成後累積 ~213 條指向已不存在 call site 的 fiction key。

3. **B ⊃ A 的有用部分。** B 仍需做 unused-override warning（否則新增 override 拼錯照樣 silent）。`B = A 有用部分 + 旗子與抑制邏輯`，多寫的碼全花在養墓碑。**那條 warning 才是真正防 silent-drop 的機制，旗子只是繞過它。**

4. **A 自清。** override 表只留尚未 migrate 的條目，表變空 = migration 完成，是乾淨的進度訊號。

**A 唯一的損失**：override 表 `translatorComment` 是日文，PHP 實作的 `/* translators */` 是英文；刪 override 後該日文註解只剩 git history。但譯者經 `.pot` 拿到的本就是 PHP 英文註解，日文註解是冗餘的編輯期 metadata，成本低。migrated 字串 audit 改用 `grep '$log_i18n->always('`（直接列出 canonical 清單 + translator comment）+ `.po`，不比 B 差。

### A 的落地動作（已實作）

- 已刪 migrate 完成的 `js/ukagaka-anime.js` 5 條 overrides
- `generate-console-log-inventory.js` 已加 **unused-override verify pass**：override lookup key 若在本次 inventory 未被任何 row 命中 → **explicit error**（把 silent drop 升級成 build 失敗），同時防護未來 override 拼錯 / 行號漂移
- `plan/translation-tables/console-logs-zh-to-ja.md` 已於方案 A 首次落地時重生為 **208 included rows + 1 backlog**；Frieren decoration config 第二批 migration 後為 **204 included rows + 1 backlog**；Frieren Canvas/Image/Pixel/Touch 第三批 migration 後為 **197 included rows + 1 backlog**；core bundle 收尾後為 **195 included rows + 1 backlog**；Phase 2.5 backlog 清理後，目前為 **195 included rows + 0 backlog**
- migrated 字串 trace 權威 = PHP `$log_i18n->always(...)` + `.po`

### 補記：三案都沒解到的脆弱點

override 曾用**行號**當 lookup key，這在 Phase 3-0 一次填 195 條 debug 翻譯時會變成持續摩擦：只要後續 migration 推移同檔案行號，未遷移 override 就會 orphan。Phase 3-pre 已改為 `relativePath::normalized zhOriginal`，把 lookup identity 固定在 migration 前穩定存在的 source text 上。

---

### 問題（背景，保留供公司御三家對照）

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

公司端原建議方案 B（改動最小、verify pass 把 silent drop 升級成 explicit error）。**家裡審視 generator 實作後改採方案 A**，理由見本節開頭「決策理由」。

---

## Phase 3 後續 PR 範圍

production-visible console log 與既有日文 production backlog 已清零；後續剩約 195 條 debug-gated `mpuLogger.log/warn/info`，全部屬 Phase 3 / `logsDebug` 大宗。Phase 3-pre 已先完成 generator lookup 重構，Phase 3-0 可開始填 debug 翻譯而不被行號漂移侵蝕。

---

## 未做的 nice-to-have（Commit B2 / Phase 2 後續處理）

從 Phase 1.5 公司端 review 留下的 4 條：

- **(c)** `frierenPixelDataUnavailable` 條 `%1$s / %2$s` 改成 `タイプ=%1$s、メッセージ=%2$s` 自然日語格式 — **已完成**（Phase 2 第三批 migration）
- **(d)** `callPreview()` 還原 surrogate pair escape（`🔄` → 🔄），目前 `extractStrings()` 已有 unescape 邏輯可複用到 callPreview
- **(e)** Generator 加 unused-override verify pass — **已完成**（方案 A 落地）
- **(f)** Generator 內嵌的對照表 status block 已過時（line 392-397 still 寫「This commit intentionally fills raw inventory only」）— **已完成**（方案 A 落地時同步更新為 migration staging table）

debug 195 條翻譯（Phase 3-0）已完成前 4 批共 176 rows：`ukagaka-core.js` 57 rows，base/chat/anime 41 rows，features/context 46 rows，Frieren/Frieren emoji 32 rows；scope 為 `mpuLogger.log` 159 條 + `mpuLogger.warn` 36 條，目前沒有帶 CJK 的 `mpuLogger.info`。
每個 Phase 3-0 staged commit 都應跑 `npm run inventory:logs`，其內建的 unused-override 與 semantic-key gate 必須通過；第 5 個 staged commit 後再跑 no-TODO / no-draftKey 最終驗收。

---

## 接手 SOP

1. **拉新版**：`git pull origin feature/code-quality-hardening`
2. **檢視 9 個未驗 commit** — 公司端已逐 commit 審過，但家裡可獨立 review
3. ~~決策 silent-drop 方案 A / B / C~~ — **已決議採方案 A，且已落地**（刪 5 條 override + unused-override verify pass）
4. ~~決策 Phase 2 第二個 PR 範圍~~ — 已完成 `frieren.js` decoration config 4 條
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

_Last updated: 2026-05-26 — Phase 3-pre 已將 generator override lookup 改為 `relativePath::normalized zhOriginal` 並加上 semantic-key conflict gate；Phase 3-0 前 4 批已補完 176 rows，translation table 仍為 195 included rows + 0 backlog。下一更新點：Phase 3-0 第 5 批 dialog/greeting/emoji 與最終 no-TODO/no-draftKey 驗收。_
