# 初期化時序同步 設計プラン（visual-ready latch）

> 📅 初稿：2026-06-22
> 📋 進版／F5 時，主對話框尚未顯示，LLM 回應就先寫進隱藏的主框，造成文字突兀出現／已打到一半。
> 🎯 想定読者：実装担当
> 🔖 対象バージョン基点：v2.27.1
> 🧩 来源：家 CODEX 診断 + 家 CLAUDE 実コード突合せレビュー

---

## ⚠️ ステータス

**実装・検証完了（2026-06-22）。** R-1〜R-3 を反映し、visual-ready smoke test と `npm run verify` が通過済み。

---

## 1. 問題（症状）

進版または F5 時、ネットワークが遅いと以下が高確率で再現する：

- 芙莉蓮の画像・氣泡・主對話框が出る前に、LLM の返信が先に主框へ書き込まれる。
- 画像読み込み完了 → 主框 `visibility` 解除のタイミングで、文字が突然出現／既に打ち終わっている。

データ・設定そのものに誤りはない。**初期化に単一の同期点が無く、LLM 表示フローが「視覚初期化の完了」を待っていない**ことが根因。

## 2. 根因（実コードで確認済み）

| # | 内容 | 位置 |
|---|---|---|
| 1 | `/init` が 500ms を超えると独立 `/settings` fallback へ。芙莉蓮初期化前に startup LLM を排程しうる | `js/ukagaka-features.js:337` → `processSettings()` |
| 2 | startup は「打字機完了」だけ待つ。初期メッセージが system placeholder のときは打字機が走らず、待機は即 resolve → 1.5 秒カウントダウンへ | `js/ukagaka-features.js:213` |
| 3 | 芙莉蓮は 12 枚（idle + book-flip）が全て load/fail してから idle 表示・主框 `visibility` 解除 | `ghost/Frieren/frieren-animation.js:43-45`（完了判定）／`:177`（`#ukagaka_msgbox` を visible に） |
| 4 | LLM が先に戻ると、隠れた主框へ直接書き込み `fadeIn()`。`:629` の guard は `mpuMessageBlocking`／`mpuAiContextInProgress` のみで、視覚就緒チェックは無い | `js/ukagaka-core.js:626`（`.then()`）／`:629`（guard）／`:634`（render） |

> 補足：startup／首訪問候／頁面感知の間の相互排他フラグ（`mpuContextPending`／`mpuGreetInProgress`／`mpuMessageBlocking`／`mpuAiContextInProgress`）は概ね正しい。欠けているのは「視覚就緒」という同期点のみ。

---

## 3. 方針

**LLM リクエストは従来どおり早めに送る。遅延させるのは正式回応の画面レンダリングのみ。**

共有の visual-ready latch（Promise + DOM event、タイムアウト保底つき）を 1 つ用意し、startup / greet / context の正式回応はそれを `await` してから主框へ書く。

---

## 4. 改動計畫（節 1〜9）

### 4.1 共有 visual-ready latch を新設

**ファイル：`js/ukagaka-base.js`**（モジュール読込順で最初＝早期に存在する）

新規：
- `window.mpuVisualReadyPromise`
- `window.mpuMarkVisualReady(source)`
- `window.mpuWaitForVisualReady()`
- `window.mpuForceVisualReadyFallback()`

行為：
- `mpuMarkVisualReady()` は**一次性・重複呼出し安全**。
- 最初の waiter が 12 秒タイムアウトを起動。
- タイムアウト時は強制：
  - `#ukagaka_img.style.visibility = "visible"`
  - `#ukagaka_msgbox.style.visibility = "visible"`
  - ready を resolve（永久ブロック回避）。
- `mpuVisualReady` の DOM event を発火（デバッグ／拡張用）。
- 来源を記録：`frieren` / `generic-single` / `generic-multi` / `timeout`。
- **jQuery に依存しない**（初期化段階の依存問題回避）。

### 4.2 芙莉蓮が ready を発する

**ファイル：`ghost/Frieren/frieren-animation.js`** — `showFrierenIdle()` の `finalizeIdle()`。

順序を固定：
1. idle 画像設定
2. `frieren_idle_apng` 表示
3. canvas 非表示
4. `#ukagaka_img` を visible
5. `#ukagaka_msgbox` を visible
6. `window.mpuMarkVisualReady("frieren")`

`onload` / `onerror` どちらも同じ `finalizeIdle()` を通り、現行の降級挙動を維持。

### 4.3 一般角色が ready を発する

**ファイル：`js/ukagaka-anime.js`**

内部 helper `markInitialVisualReady(source)` を新設：image container 表示・msgbox visibility 解除 → 共有 ready 呼出し。

呼出し点：
- `loadSingleImage()` の `img.onload` → source `generic-single`
- `loadImages()` の全画像処理完了 → source `generic-multi`
- 複数画像は一部／全失敗でも ready フローを完了させる。
- 単張画像の `onerror` は正常 ready を発さず、12 秒 timeout に委ねる（瞬時エラーを完全初期化と誤判定しないため）。

### 4.4 startup LLM は正式レンダリングだけ gate

**ファイル：`js/ukagaka-core.js`** — `mpu_nextmsg()` の LLM `.then()` 成功分支。

正式表示ロジックを `renderNextMessageResponse(...)` として抽出（animation / `mpu_typewriter` / `mpu_showmsg` / emoji / 打字機完了後の auto-talk 復帰）。

```js
mpuWaitForVisualReady().then(() => {
  renderNextMessageResponse(...);
});
```

注意：
- request 状態は正常に完了してよい。
- visual-ready 待ちの間に `mpu_waitForTypewriterComplete()` を先行させない（即完了し auto-talk を早期起動してしまう）。
- history / lastResponse は正式レンダリングと同区間に置く（画面未表示なのに状態だけ完了宣言しない）。
- timeout 後も通常どおりレンダリング。

### 4.5 首訪問候の正式回応を gate

**ファイル：`js/ukagaka-greeting.js`**

保持：visitor-info / greeting API / system thinking bubble / `mpuGreetInProgress`。

正式 AI 回応を主框へ書く直前に `mpuWaitForVisualReady().then(...)` で包み、`showMainDialog()` → `mpu_typewriter(...)` → animation / emoji / history / 完了回調。`mpuGreetInProgress` の解除は正式レンダリングと既存 typewriter ライフサイクル完了後。

### 4.6 頁面感知の正式回応を gate

**ファイル：`js/ukagaka-context.js`**

保持：3 秒排程 / `mpuContextPending` / `mpuAiContextInProgress` / `mpuMessageBlocking` / API 早期呼出し / system thinking bubble。

API 成功後、`showMainDialog()` / `mpu_typewriter()` / 動畫 / emoji / history / cooldown timestamp / 打字機完了後のフラグ解除を visual-ready に包む。**visual-ready 前に `mpuAiContextInProgress`／`mpuMessageBlocking` を解除しない。**

### 4.7 変更しない挙動

`/settings` の 500ms fallback／`/init` API 構造／startup の 1500ms 遅延／首訪 cookie／睡眠 startup skip／既存の相互排他フラグ／LLM 送信タイミング／F5 の history・session クリア。

### 4.8 Thinking bubble 最短表示

第二段階推奨。本回も併せる場合は 350ms minimum dwell（system thinking bubble の**クリア時間のみ**制限し、API request は遅延させない）。

---

## 5. レビュー指摘（要対応 ＝ 着手前に各節へ反映）

> 家 CLAUDE が実コードと突合せて検出。🔴 は未対応だと不具合が残る／再発する。

### 🔴 R-1：inter-flow guard は `.then(ready)` の**内側**で再評価する（節 4.4 / 4.5 / 4.6）

visual-ready 待機は数秒に及びうる（画像 + 1.5s）。その間に context（3 秒後）が起動しうる。回応が「待機前」に `core.js:629` の `mpuMessageBlocking`／`mpuAiContextInProgress` を通過し、ready resolve 後に描画すると、待機中に始まった context 対話を上書きする＝フラグが防ぐはずの cross-talk が再発。
**対応：guard 判定を ready resolve 後・主框書込み直前にもう一度行う。** 元計画 4.4/4.5/4.6 には未記載。

### 🔴 R-2：`loadImages` の onerror 完了分支は元々 visibility を解除していない（節 4.3）

`anime.js` は `img.onload` の `loadedCount === totalImages` 分支（`:195-206`）でしか visibility を設定せず、`img.onerror` の完了分支（`:220-239`）では設定しない。つまり多圖角色で「最後に完了したのが error」だと、前のが成功していても主框が永久に出ない（既存バグ）。
**対応：新 helper `markInitialVisualReady()` を onload-complete と onerror-complete の**両分支**から呼ぶ。** onload だけに繋がない。

### 🔴 R-3：greeting.js / context.js は「単一レンダリング区塊」ではない（節 4.5 / 4.6）

`greeting.js` に `mpu_typewriter` が 7 箇所（`:59/109/191/212/237/271` ほか）、`context.js` に 6+ 箇所（`:391/431/513/534/555/586`）あり、`mpuSetMessageBlocking`／`AiContextInProgress` の解除（`context.js:487/522/544/563/575/594`）と交錯。
**対応：どれが『正式 AI 回応を主框へ書く』site かを正確に特定し、その site だけ gate する。** error／fallback／system 行は gate しない。フラグ解除は gate 内へ移す。startup の単点より複雑で、工数・ミスの主因。

### 🟡 註記（許容、ただし周知）

- **N-1**：timeout 保底は画像が真に全失敗のとき空の主框を露出（角色未描画）。fail-open として正しいが最終手段の退化画面。
- **N-2**：単圖 `onerror` は 12 秒 timeout 委ね＝単圖角色の唯一画像 404 で 12 秒空白後に露框。現状（永久隠蔽）より良いが 12 秒は長め。将来特例で短縮検討。
- **N-3**：12 秒保底は **waiter が居るときだけ**起動。LLM replacement off かつ greet/context 無しでは waiter が無く、露框は画像読込のみ依存（回帰なし・hang 改善もなし）。主框を「必ず最終的に出す」なら init 時に timeout を arm する案も。
- **N-4**：後端 `nextmsg` は生成時点で checksum を書く。前端 history push を render 後へ遅延すると front/back 分歧視窗が僅かに拡大。audit モードでは無害・低優先。
- **N-5**：節 4.4 の `mpu_waitForTypewriterComplete` 注意は正しい。auto-talk 再起動は ready 後・実打字機起動後にのみ arm すること。

### ✅ 妥当と確認した点

request 早送り／render 遅延の分離、latch の一次性・再入安全、芙莉蓮の `finalizeIdle` を就緒点とする設計、jQuery 非依存、節 4.7「不変更リスト」、節 6 の検証範囲。

---

## 6. 検証

### 必測情境
- 通常の初回訪問
- F5 リロード
- Disable cache + Slow 3G
- `/init` 遅延 > 500ms
- 芙莉蓮アニメ画像の 1 枚が 404
- `/init` または画像が > 12 秒
- 非 Frieren 単圖角色
- 非 Frieren 多圖角色
- 首訪問候 有効
- 頁面感知 100%
- LLM replacement on / off
- 深夜睡眠・喚醒フロー
- 主框 cookie を hidden に設定

### 自動検証
- `npm run verify`
- **2 つの production bundle が再ビルドされたこと**（`js/dist/ukagaka-bundle.*` と `ghost/Frieren/dist/frieren-bundle.*`）を確認。
- JS smoke test を追加：ready が正常 resolve／重複 signal が安全／12 秒 timeout が発火／**応答が ready 前に描画されない**こと。

---

## 7. 影響ファイル一覧

| ファイル | 変更 | bundle |
|---|---|---|
| `js/ukagaka-base.js` | latch 新設（節 4.1） | core |
| `js/ukagaka-anime.js` | `markInitialVisualReady()`、両 onerror/onload 完了分支から呼出し（節 4.3 / R-2） | core |
| `js/ukagaka-core.js` | `renderNextMessageResponse()` 抽出＋gate＋guard 再評価（節 4.4 / R-1） | core |
| `js/ukagaka-greeting.js` | 正式回応 site のみ gate（節 4.5 / R-3） | core |
| `js/ukagaka-context.js` | 正式回応 site のみ gate＋フラグ解除を gate 内へ（節 4.6 / R-1 / R-3） | core |
| `ghost/Frieren/frieren-animation.js` | `finalizeIdle` で `mpuMarkVisualReady("frieren")`（節 4.2） | frieren |
