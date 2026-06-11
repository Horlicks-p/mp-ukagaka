# A-2 巨大ファイル分割プラン（前端優先・漸進）

> ## Follow-up status（2026-06-11）
>
> - ✅ ① `js_area` timing caveat recorded in the Unreleased changelog entries.
> - ✅ ② Frieren decorations/interactions precondition silent returns now emit `warnAlways` logs where actionable.
> - ✅ ③ Removed dead `mpuDecorationConfigPending`.
> - ⏳ ④ chat SSE / touch manual smoke remains release-gate work; intentionally not run here to avoid LLM quota use.
> - ⏳ ⑤ `ukagaka-chat.js` and backend split remain out of scope for this branch.
>
> ## 📈 進捗（2026-06-10 家チーム、branch `refactor/frontend-split`）
>
> | 刀 | commit | 内容 | 状態 |
> |---|---|---|---|
> | 前置 | `3f8f38f` | frontend-functions.php 全檔 phpcbf + baseline regenerate | ✅ |
> | 刀1 | `0d7a0be` | boot 純データ 6 変数 → `mpu_frontend_boot_inline_js()`（base handle 'before'） | ✅ |
> | 刀2 | `8aac8e9` | bootstrap → `mpu_frontend_bootstrap_inline_js()`（anime handle 'after'） | ✅ |
> | 刀2.1 | `969cb7d` | CODEX finding：extend.js_area も anime 'after' へ（ready 順序復元） | ✅ |
> | 刀3 | `67f3a92` | frieren-decorations.js 拆出（8 methods） | ✅ |
> | 刀4 | `bb37e46` | frieren-interactions.js 拆出（10 methods） | ✅ |
> | 刀5 | `6d22060` | frieren-animation.js 拆出（11 methods）。frieren.js は 83 行（state + init）に | ✅ |
>
> 全刀：php -l / phpcs-baseline gate / PHPUnit 88/88 / git diff --check / Playwright smoke 緑。
> 刀3-5 は逐字節純搬移を diff で実証。工作流＝Claude と CODEX が交替で下刀・相互レビュー。
>
> **残課題（次回）**：④release 前に chat SSE / touch の手動 smoke（LLM 額度節約のため未実施）⑤ukagaka-chat.js（bundle 対象）と後端は未着手

> 📅 作成：2026-06-10（公司CLAUDE(Fable 5) と 家 CODEX の協議をまとめ、実コード v2.25.6 で裏取り）
> 🎯 想定読者：家の御三家（実装担当）
> 🔖 親計画：`Codebase_Review_2026-06-10.md` の A-2
> 📌 性質：**設計のみ／未着手**。S 級（PR #5）と A-1/A-3（PR #6）は merge 済み。本件は A 級の最後の 1 件。
> ⚠️ 着手時は main から新ブランチ（例 `refactor/frontend-split`）を切る。

---

## 0. 方針（合意済み）

**前端から、漸進的に。一次大改はしない。後端の巨大ファイルは「境界を標すだけ」で当面触らない。**

- 「動作を変えず、責務の置き場所だけ動かす」を各刀の基本契約にする。
- 1 刀 = 1 種類の責務。異種を同じ commit に混ぜない。
- 入口を先に作る。最初から class/module の大設計をしない。重複が見えてから helper を抽出。

---

## 1. 事実確認（実測 v2.25.6）

| ファイル | 実行数 | bundle 対象 | 着手の摩擦 |
|---|---|---|---|
| `ghost/Frieren/frieren.js` | 1679 | ❌ **非 bundle**（ghost 資産・独立 enqueue） | **低**（build 不要・dist 触らない） |
| `js/ukagaka-chat.js` | 1301 | ✅ **bundle 対象**（`tools/node/build.js:24`） | 中（改修ごとに `npm run build` + dist 2 ファイル commit） |
| `includes/core/frontend-functions.php` | 1200 | —（inline JS を出力） | **低**（PHP/JS 混在の inline を localize 化） |
| `includes/rest/class-mpu-rest-chat.php` | 1272 | — | **高**（checksum/SSE・行為密集。最後） |
| `includes/admin-functions.php` | 1105 | — | 中（admin のみ・後回し可） |

> **分類が行数より重要**：`frieren.js` は非 bundle なので改修に build 不要。`ukagaka-chat.js` は bundle 対象で、1 刀ごとに rebuild と dist commit が連鎖する。よって**最低摩擦の起手は `frieren.js` と `frontend-functions.php` の inline JS**。chat.js を後回しにする理由は「bundle 連鎖」であって行為密度ではない。

---

## 2. 着手順（漸進・各刀独立 commit）

### 刀 1 — `frontend-functions.php` inline JS のうち「純データ」を localize 化（最優先・近零リスク）
`mpu_head()`（`frontend-functions.php:1060-1149` 付近）の inline `<script>` から、**純データ**だけを `wp_localize_script` へ移す：
- `var mpuInfo`（robot/msg ラベル・`isDeepSleepTime`）
- `var mpuPreSettings`（ollama_replace / typewriter_speed / streaming_enabled / is_admin / rest_nonce）
- `var mpuPageContext`（postId）
- `var mpuAiEnabled` / `var mpuInitParams` 等

機械的でテストしやすい。**この刀ではロジックを動かさない**。

### 刀 2 — bootstrap ロジックを enqueue 済みモジュールへ（タイミング注意・単独刀）
`jQuery(document).ready(function($){ ... ajax 'init' ... mpuCanvasManager.init ... })`（`:1125-1149` 付近）を正式 JS ファイルへ移す。
- ⚠️ **「位置を動かすだけ＝行為不変」はこの刀では成立しない**：現在は `<head>` で同期実行。enqueue 後は実行タイミングが `mpuCanvasManager` 定義や他の inline 変数と前後する。`wp_add_inline_script` の **dependency handle を正しく掛けないと race**。
- 既存の依存（`frieren.js` は `$anime_handle` のみ依存、chat runtime 依存は非 bundle 時に未明示）も併せて確認（Gift プラン §3⑤ の script dependency 注記と同根）。

### 刀 3 以降 — `frieren.js` の責務を小ファイルへ（build 不要で回しやすい）
1 刀 1 種類で：
- frontend boot/config
- dialog/chat UI helpers
- decoration preload / loading state
- touch/reaction client behavior

新規小ファイルを作り既存入口から参照。bundle/build/テストが安定してから、重複ロジックの helper 抽出を検討。

### 後端 — 当面は「境界を標す」のみ
`class-mpu-rest-chat.php`・`admin-functions.php`・`akismet-integration.php` は大きいが行為リスクが高い。前端が安定してから「純 helper」or「純 endpoint 子流程」だけを抽出。コア流れ（checksum / SSE / dispatch）は最後。

---

## 3. ⚠️ 必守の制約（リスク章）

### (A) per-request 値は静的 JS に入れない
- `mpuRestNonce`（`frontend-functions.php:1062`）と session token の遅延取得（`:1064-1066`）は**意図的に HTML へ埋めず** full-page cache 汚染を避けている。
- 移設時は**静的ロジックはキャッシュ可・nonce/token は per-request inline のまま**にする。nonce を静的ファイルへ書かないこと。

### (B) bootstrap の実行タイミング（刀 2）
`<head>` 同期実行 → enqueue への移行で順序が変わる。`wp_add_inline_script($handle, ..., 'after')` で正しい handle に掛け、`mpuCanvasManager` 準備後に走ることを保証。

### (C) 自動回帰網が薄い ── 手動 smoke が真の gate
`node --check` は**構文のみ**、`test:php` は**前端を触らない**。inline JS / frieren.js の移設に**自動回帰テストは無い**。

---

## 4. 各刀の検証（必須）

各刀で最低限：
1. `php -l`（触った PHP）
2. `node --check`（触った JS）
3. `npm run build`（**bundle 対象を触った時のみ** ＝ ukagaka-chat.js 等。frieren.js / frontend-functions.php inline は build 不要）
4. `npm run test:php`（= `cd tests && php ../tools/php/vendor/bin/phpunit`）
5. `php tools/php/phpcs-baseline.php check`（新規 findings ゼロ）
6. `git diff --check`
7. **手動 smoke（必須・自動網が無いため）**：
   - canvas 初期化（キャラ表示）
   - chat 送受信（SSE 含む）
   - decoration 読み込み・クリック反応
   - touch 反応（`/touch/decoration`・`/touch/zone`、session token guard 込み）
   - 初回訪問の挨拶（greet）

→ **各刀は独立 commit にし、commit body に手動 smoke チェックリストを残す**（問題時に二分で回退しやすい）。

---

## 5. 進め方（PR 戦略）

- A-2 は S 級 / A-1・A-3 と違い**薄カバレッジの漸進リファクタ**。安全修復と混ぜず、**A-2 専用の PR シリーズ**にする。
- 刀 1（純データ localize）は単独で出せる安全な第一歩。
- bundle を触る刀（chat.js）は dist 2 ファイル（`js/dist/ukagaka-bundle.js` / `.min.js`）の再生成 commit を忘れない。

---

## 6. 参考 file:line（着手地図）

- inline JS 本体：`includes/core/frontend-functions.php:1060-1149`
- nonce / token（静的化禁止）：`frontend-functions.php:1062`・`:1064-1066`
- 既存 localize の前例：`frontend-functions.php:1029`（`mpuL10n`）
- bundle 定義：`tools/node/build.js:17-25`
- script 依存の既知の穴：`frontend-functions.php:502`（personality script が `$anime_handle` のみ依存）／ Gift プラン §3⑤ 注記
