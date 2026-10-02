# mp-ukagaka / Frieren SVG 全面移行計画書

## 1. 概要

本計画書は、`mp-ukagaka` プロジェクト内の `Frieren` シェルについて、現在の PNG / APNG ベースの画像資産を、**本体全フレームおよび装飾画像を含めて SVG 化**し、将来的には SVG を前提とした高解像度・高一貫性・高拡張性の表示基盤へ移行するための方針を定めるものである。

本移行の第一目的は、単なるファイル形式の変更ではなく、以下の課題を解消することである。

- 拡大・縮小時の画質劣化
- フレームごとの色味・輪郭・位置の微差による一貫性の揺れ
- Web サイト全体の SVG ベース UI / アイコンとの統一感不足
- 将来の瞬き・翻書・装飾発光などの高度な演出に向けた拡張性不足

本計画では、まず **既存の見た目・再生順・再生タイミングを維持したまま全視覚状態を SVG 化**し、その後必要に応じて **一部をパーツ分解型の SVG アニメーションへ発展**させる二段階方針を採用する。

移行作業中は比較・検証のため PNG / APNG と SVG が作業ブランチ上で一時的に共存してよい。ただし正式な切替は一括で行い、全 SVG と新しいローダーの検証完了後、`Frieren` 本体および装飾の旧 PNG / APNG を削除する。最終成果物では PNG fallback を持たない。

---

## 2. 背景と目的

### 2.1 背景

現状の `Frieren` シェルは、動画生成 → APNG 化 → 必要フレーム抽出 → 背景除去という工程を経て作成された画像を使用している。

この方式は短期的には有効だが、以下の性質を持つ。

1. 元フレーム間に微小な差異が残りやすい
2. 背景除去や縮小後は気付きにくいが、原寸または拡大時に一貫性の揺れが見えやすい
3. PNG / APNG である以上、将来の UI / サイト全体とのベクター統一が難しい
4. アニメーションの追加・調整がフレーム差し替え前提になりやすい

一方で、現サイトでは既にメニューやアイコンなどに SVG が採用されており、プロジェクト全体としては SVG ベースの表現と高い親和性を持つ。

### 2.2 本移行の目的

本移行の目的は以下の通り。

- `Frieren` 本体の全フレームを SVG 化する
- 装飾画像（books / staff / suitcase 等）もすべて SVG 化する
- 既存の表示ロジックを可能な限り維持しつつ、SVG 資産をそのまま利用できる状態にする
- フレーム全体に共通パレット・共通座標系・共通変換ルールを適用し、見た目の一貫性を改善する
- 将来的なパーツアニメーション化（瞬き、ページめくり、微動作）へ繋がる基盤を構築する

---

## 3. 対象範囲

### 3.1 本計画の対象

#### 本体フレーム

- `frieren[0]`
- `frieren[1]` ～ `frieren[11]`
- `frieren[s]`
- `frieren[w1]` ～ `frieren[w5]`

#### 装飾画像

- `books`
- `staff`
- `suitcase`
- `evil_horns`
- `dark_dragon_horn`
- `potion`

#### 関連コード / 設定

- `ghost/Frieren/shell/Frieren/`
- `ghost/Frieren/decorations/`
- `ghost/Frieren/decorations.json`
- `ghost/Frieren/frieren.js`
- `ghost/Frieren/frieren-animation.js`
- `ghost/Frieren/frieren-decorations.js`
- `js/ukagaka-anime.js`
- `includes/core/ukagaka-functions.php`
- `css/mpu_style.css`
- `tools/node/build.js`
- `ghost/Frieren/dist/`
- `js/dist/`
- `tools/e2e/interaction-e2e.js`
- `docs-en/CANVAS_CUSTOMIZATION.md`
- `docs-en/GHOST_CREATE_GUIDE.md`
- shell 読込部 / 画像列挙部
- Canvas / Image 読込処理

### 3.2 本計画の対象外（第一段階では行わないもの）

- 既存の会話ロジック変更
- キャラクター人格の変更
- 3D 化 / Live2D 化
- フリーレン以外の全人格への即時横展開
- すべてのアニメーションを最初からパーツアニメへ再設計すること

---

## 4. 現状整理

### 4.1 画像構成

現行の `Frieren` は以下の構成で動作している。

- `frieren[0].png` : idle
- `frieren[s].png` : sleep
- `frieren[w1..w5].png` : wake up animation
- `frieren[1..11].png` : book flip animation

装飾は `decorations.json` で定義され、画像ファイル名・位置・サイズ・z-index・クリック用 prompt が外部化されている。

### 4.2 動作構成

- 本体画像は `frieren.js` / `frieren-animation.js` により管理される
- フリップや起床アニメは、**複数画像の順次切替**によって表現されている
- 装飾は `frieren-decorations.js` にて `<img>` として配置される
- 装飾クリックは pixel hit detection によって実現されている
- 汎用の shell 読込は `ukagaka-anime.js` 側で画像群を扱う

### 4.3 現状の課題

#### 課題1：解像度依存
PNG / APNG のため、拡大時の印象が固定解像度に依存する。

#### 課題2：フレーム間の微差
元動画ベースゆえ、フレームごとの輪郭・色・位置の小さな差が残りやすい。

#### 課題3：Web全体との統一感
UI / icon が SVG ベースであるのに対し、キャラクターと装飾だけ raster ベースである。

#### 課題4：将来的な動きの拡張性
瞬きやページめくりのような演出を追加する際、全フレーム差し替え方式では保守負荷が高い。

---

## 5. 基本方針

本移行では、以下の 6 原則を採用する。

### 原則1：一括変換・共通ルール
1枚ずつ場当たり的に SVG 化するのではなく、**全素材を同一条件のパイプラインで一括変換**する。

### 原則2：ドット絵らしさを維持
本変換は「なめらかなベジェ再解釈」ではなく、**ピクセルアートをピクセルアートのまま SVG 化**する方式を採用する。

### 原則3：明示的な資産定義と安定した命名
既存の論理状態名は維持する。ただし idle / sleep の APNG 内部フレームは独立 SVG になるため、専用の資産 manifest で再生順・時間・用途を明示する。ファイルシステムの偶然の列挙順だけに依存しない。

例：

```text
idle/frieren-idle-00.svg
sleep/frieren-sleep-00.svg
book/frieren-book-01.svg
wake/frieren-wake-01.svg
```

### 原則4：段階移行
最初からすべてをパーツアニメへ作り替えるのではなく、

1. まず全フレーム SVG 化
2. manifest と共通 sequence renderer で安定表示
3. 必要部分のみパーツアニメへ昇格

という順序で進める。

### 原則5：装飾も含めて統一
本体だけではなく、装飾群も同一ポリシーで SVG 化し、プロジェクト全体の統一感を高める。

### 原則6：検証後の一括切替
PoC と比較中は旧資産を保持するが、正式切替時には次を同一変更セットで行う。

1. SVG-only の資産 manifest とローダーを有効化
2. 全 PNG / APNG を削除
3. `.png` 固定参照、fallback、形式依存ログを削除
4. source と production bundle を同時更新

これにより、最終状態に同名 PNG / SVG の重複や、到達不能な fallback 分岐を残さない。

---

## 6. 移行アーキテクチャ

### 6.1 第一段階の目標アーキテクチャ

#### 本体
- `shell/Frieren/` 内の全視覚状態を SVG ファイル化する
- book flip / wake up に加え、idle / sleep も JavaScript 管理の SVG フレーム列として再生する
- 再生順、各フレーム時間、loop、用途は専用の資産 manifest に記録する
- Canvas と idle 表示用 `<img>` は同一の論理座標・CSS 表示寸法を使用する

#### 装飾
- `decorations/` 内の各装飾画像を SVG 化
- `decorations.json` の `image` 指定を `.png` から `.svg` に変更可能にする

#### 読込系
- shell 画像スキャン対象拡張子に `.svg` を追加する
- 同じ basename の raster / SVG が存在する場合は一つの論理フレームとして扱い、SVG を優先する
- Frieren の再生順はディレクトリスキャンではなく専用 manifest を正とする
- `Image()` / Canvas / `<img>` の各経路で SVG 読込を検証する
- 最終切替後の Frieren ローダーは SVG-only とし、PNG fallback を持たない

`Image()` と `<img>` はブラウザ標準で SVG を扱えるため、「拡張子を許可する」だけの特別処理を追加するのではなく、URL 解決、寸法、エラー終了、Canvas 描画の契約を実装対象とする。

### 6.2 第二段階の目標アーキテクチャ

将来拡張として、以下のような構成へ移行できるようにする。

- idle は 1 枚の inline SVG
- 瞬きは目パーツのみ切替
- 翻書はページパーツのみ morph / transform
- 装飾は必要に応じて発光・浮遊・反応演出

ただしこれは第一段階の必須要件ではない。

---

## 7. SVG化の技術方針

### 7.1 変換方式

変換方式は以下を想定する。

- 入力：背景透過済み PNG
- 前処理：共通サイズ・共通位置・共通パレットへの正規化
- 中間処理：同色隣接ピクセルの統合
- 出力：`<path>` または `<rect>` ベースの SVG
- 描画特性：`shape-rendering="crispEdges"`

### 7.2 変換ルール

#### ルール1：キャンバスサイズ統一
全フレームは共通 `viewBox` を持つ。

#### ルール2：座標系統一
頭頂・椅子・本・足先などの基準位置を揃える。

#### ルール3：色パレット統一
全素材共通のマスターパレットを作成し、各フレームはそのパレットへ量子化する。

#### ルール4：微小ノイズ抑制
孤立ピクセル、1フレームのみ出現する極小ノイズは除去対象とする。

#### ルール5：透過処理統一
半透明の扱いを統一し、不必要なアンチエイリアス差を減らす。

透明度を単純に二値化してはならない。現行 PNG は輪郭に多数の部分透明ピクセルを持つため、完全不透明な path のみに置換する場合は、明背景・暗背景の双方で輪郭品質が同等以上であることを PoC で確認する。二値化が不合格なら、`fill-opacity` を含む共通 alpha 段階を定義する。

#### ルール6：表示寸法と内部座標を分離

- master `viewBox`：`0 0 208 328`
- SVG intrinsic size：`208 x 328` を基準とし、制作確認用の `832 x 1312` を runtime asset に残さない
- 初期 CSS 表示寸法：`height: 249px; width: auto`
- Canvas backing size：`208 x 328`
- Canvas CSS size：idle `<img>` と同じ外形寸法
- device pixel ratio を上げる場合も、論理座標とクリック座標は `208 x 328` に保つ

`shape-rendering="crispEdges"` は非整数倍率で完全なピクセル均一性を保証しない。`328 -> 249px` の縮小表示を PoC で確認し、線の欠落・太さの不均一・フレーム間の shimmer が出る場合は、表示高または座標系を再決定してから一括制作する。

#### ルール7：SVG安全性

runtime asset は self-contained な静的 SVG とし、次を禁止する。

- `<script>`、`foreignObject`、イベント属性
- 外部画像、外部フォント、外部 stylesheet
- 外部 URL を参照する `href` / `xlink:href`
- 承認済みの `character-drop-shadow` 以外の filter / mask 依存

変換後は機械検査を行い、違反する SVG を build / verify で失敗させる。

#### ルール8：共通のドロップシャドウ

`sample.png` を視覚基準として、人物全体の alpha silhouette の後方へ、下方向にずれた暗いドロップシャドウを追加する。輪郭膨張だけの border や足元の ellipse ではない。

- 全 foreground path を `<g id="character-art">` で囲む
- `<filter id="character-drop-shadow">` を `character-art` 全体へ一度だけ適用する
- `SourceAlpha` に `feGaussianBlur` を適用し、`feOffset dx="0" dy="5"` で正確に 5 SVG unit 下へ移動する
- 暗色 shadow を opacity 20% ～ 28% で生成する
- shadow を `SourceGraphic` より先に merge し、元の character path と内部色を変更しない
- 全 38 状態で同一の dx、dy、stdDeviation、色、opacity、filter bounds を使用する
- filter bounds は下方向の offset と blur を裁切しない余白を持たせる
- pixel-art 感を保つため blur は最小限とし、`stdDeviation="0"` または `0.6` ～ `0.8` の範囲に限定する
- book flip / wake / idle / sleep の切替時に shadow の距離・濃度・柔らかさが変化しないことを比較する
- 装飾には一律の shadow を追加しない。既存の z-index、透明領域、pixel hit detection を維持する

PoC 初期値は `dx="0"`、`dy="5"`、`stdDeviation="0.7"`、`flood-color="#000000"`、`flood-opacity="0.24"`、filter bounds `x/y=-10%`, `width=120%`, `height=130%` とする。調整する場合も `dy="5"` は固定し、全本体 SVG の filter 定義へ同じ値を機械的に反映する。

### 7.3 資産 manifest

`ghost/Frieren/shell/Frieren/assets.json` を Frieren 表示資産の source of truth とする。少なくとも次を保持する。

```json
{
  "format_version": 1,
  "view_box": [0, 0, 208, 328],
  "display_height": 249,
  "sequences": {
    "idle": { "loop": true, "frames": [] },
    "sleep": { "loop": true, "frames": [] },
    "book_flip": { "loop": false, "frames": [] },
    "wake": { "loop": false, "frames": [] }
  }
}
```

各 frame は `src` と `duration_ms` を持つ。必要な場合のみ disposal / composition 情報も保持する。ローダーはこの manifest の順序を使用し、ファイル名の自然順からアニメーション意味を推測しない。

---

## 8. 画像資産の再構成方針

### 8.1 本体資産

#### 現行

```text
frieren[0].png
frieren[1].png
...
frieren[11].png
frieren[s].png
frieren[w1].png
...
frieren[w5].png
```

#### 移行後（第一段階）

```text
assets.json
idle/frieren-idle-00.svg ... frieren-idle-11.svg
sleep/frieren-sleep-00.svg ... frieren-sleep-09.svg
book/frieren-book-01.svg ... frieren-book-11.svg
wake/frieren-wake-01.svg ... frieren-wake-05.svg
```

作業中の比較に限り PNG / APNG を保持してよいが、正式切替時にすべて削除する。最終ディレクトリには SVG と `assets.json` のみを残す。

### 8.2 装飾資産

#### 現行

```text
books.png
staff.png
suitcase.png
evil_horns.png
dark_dragon_horn.png
potion.png
```

#### 移行後

```text
books.svg
staff.svg
suitcase.svg
evil_horns.svg
dark_dragon_horn.svg
potion.svg
```

---

## 9. コード改修方針

### 9.1 shell 読込対応

#### 改修項目
- フォルダスキャン対象拡張子に `.svg` を追加
- 同一 basename の複数形式を一つへ正規化し、SVG を優先する
- 通常人格の自然順を維持する回帰テストを追加する
- Frieren は `assets.json` の順序を使用し、汎用スキャン結果をアニメーション順として使用しない

#### 期待結果
汎用 shell は既存 raster と新しい SVG の双方を安全に列挙でき、Frieren は 38 状態を明示された順序と時間で再生できる。

### 9.2 本体アニメ読込部

`frieren.js` / `frieren-animation.js` の `.png` 固定 URL 組立を廃止し、`assets.json` を一度読み込んで検証した後、各 sequence を Image object として preload / lazy-load する。

- idle：12 SVG、無限 loop
- sleep：10 SVG、無限 loop
- book flip：11 SVG、非 loop
- wake：5 SVG、非 loop
- idle は初期表示時に preload
- sleep は睡眠表示が必要な場合に preload
- book flip は初期表示後に preload
- wake は睡眠表示時に preload し、起床操作前に ready にする

ローダーは sequence 単位で成功・失敗を管理する。欠損フレームを飛ばして誤った動きを再生してはならず、必須 sequence の読込失敗時はアニメーションを停止して既に読み込めた安全な SVG フレームを表示し、エラーを記録する。最終成果物には PNG fallback を実装しない。

#### APNG から保持する時序

現行 APNG の `fcTL` を基準として、少なくとも次の時間を `assets.json` に保持する。

- idle：`4800, 50, 120, 50, 4800, 120, 50, 50, 50, 50, 4800, 120 ms`
- sleep：`300, 300, 600, 300, 600, 300, 300, 300, 300, 300 ms`

idle / sleep はどちらも無限 loop である。APNG の disposal / blend に依存して見えるフレームは、抽出時に各 SVG を完全合成済みの独立フレームとして書き出し、runtime renderer に APNG disposal の再実装を要求しない。

### 9.3 装飾表示部

`frieren-decorations.js` は `<img>` を生成する構造のため、SVG への置換が比較的容易である。

#### 改修項目
- `decorations.json` の `image` を `.svg` に切り替える
- pixel hit detection が SVG でも正常に動作するか確認
- 同一オリジン / self-contained SVG を前提にする
- 装飾サイズ指定が SVG でも意図通り反映されるか確認
- intrinsic size を制作確認用の 4 倍寸法にせず、hidden canvas の不要なメモリ増加を防ぐ

### 9.4 クリック判定

現行では装飾画像を hidden canvas に描画して alpha を判定している。

SVG でも、同一オリジンかつ外部参照なしの構成なら動作継続できる可能性が高い。

#### 注意点
- 外部画像参照を含む SVG は避ける
- 本体の承認済み `character-drop-shadow` 以外では filter / mask を避ける
- SVG の `naturalWidth` / `naturalHeight` と CSS 表示寸法の座標変換を確認する
- 必要なら hit map 専用の簡略 Canvas mask を生成するが、PNG hit map は最終成果物に残さない

### 9.5 表示サイズと描画

現在の idle `<img>` は `naturalWidth` / `naturalHeight` を参照し、Canvas も Image の寸法を backing size に使用する。SVG 移行時は次へ統一する。

- asset の intrinsic size を表示サイズとして採用しない
- `assets.json` の `view_box` を Canvas backing size に使用する
- idle `<img>` と Canvas に共通 CSS class / custom properties を適用する
- `height: 249px; width: auto` を初期値とし、PoC 承認後に固定する
- 装飾の percentage position は新しいコンテナ外形で再確認する

### 9.6 production bundle・ログ・文書

- source JS の変更後に `npm --prefix tools/node run build` を実行する
- `ghost/Frieren/dist/frieren-bundle.js` / `.min.js` と `js/dist/` を更新する
- `.png` を含む形式依存コメント・console log・翻訳文字列を形式非依存へ変更する
- 翻訳 catalog を更新し `.po` から `.mo` を再生成する
- 一般 shell の SVG 対応を `GHOST_CREATE_GUIDE.md` と `CANVAS_CUSTOMIZATION.md` に反映する

---

## 10. 実施フェーズ

### Phase 0：準備

#### 目的
現状素材・コード・変換条件を整理する。

#### 作業
- 対象ファイル一覧化
- フレーム番号と用途の整理
- 装飾一覧化
- 既存 shell 読込処理の対応拡張子確認
- 比較用スクリーンショット基準を決定
- idle / sleep APNG の全子フレーム、delay、loop、disposal、blend を抽出して記録
- 現行 PNG / APNG / 装飾の総 byte 数、透明領域、表示 bounding box を記録

#### 成果物
- 対象資産一覧
- 比較用基準画像セット
- 変換仕様メモ
- APNG 時序表
- 旧資産の性能比較 baseline

---

### Phase 1：素材正規化

#### 目的
全フレームを同一条件へ揃える。

#### 作業
- キャンバスサイズ統一
- 原点・位置合わせ
- 背景透過の再確認
- 共通パレット作成
- 全フレームを共通パレットへ量子化
- 微小ノイズの確認

#### 成果物
- 一時作業用の正規化済み raster セット（runtime には含めない）
- 共通パレット定義
- 位置合わせ基準
- ドロップシャドウ filter の master 定義

---

### Phase 2：本体全フレームSVG化

#### 目的
本体の全フレームを SVG へ変換する。

#### 作業
- idle 12 状態
- sleep 10 状態
- book flip 11 状態
- wake up 5 状態

を一括変換する。

#### ルール
- 背景透明
- 共通 `viewBox`
- crispEdges
- path 最適化
- 不要な冗長属性削減
- 共通の `character-art` group と `character-drop-shadow` filter
- runtime intrinsic size は `208 x 328`
- 禁止要素・外部参照なし

#### 成果物
- `shell/Frieren/{idle,sleep,book,wake}/*.svg`
- `shell/Frieren/assets.json`
- 変換ログ
- 差分確認レポート

---

### Phase 3：装飾SVG化

#### 目的
装飾群を SVG 化する。

#### 作業
- `books`
- `staff`
- `suitcase`
- `evil_horns`
- `dark_dragon_horn`
- `potion`

を SVG へ変換する。

#### 成果物
- `decorations/*.svg`
- `decorations.json` 更新案
- 見た目比較画像

---

### Phase 4：コード対応

#### 目的
SVG 資産を既存システムで利用可能にする。

#### 作業
- shell スキャンで `.svg` 対応
- 同 basename 形式の重複排除
- `assets.json` の schema validation と URL 解決
- idle / sleep SVG sequence renderer
- 本体・Canvas の共通表示サイズ対応
- 装飾画像読込対応
- デバッグ表示追加

#### 成果物
- 改修済み JS / PHP / 設定
- source と同期した production bundle
- loader / manifest / SVG validator の自動テスト

---

### Phase 5：検証

#### 目的
PNG 版と SVG 版の見た目・挙動差を確認する。

#### 検証観点
- idle 表示
- 睡眠表示
- 起床アニメ
- 翻書アニメ
- APNG baseline と同じ idle / sleep の frame delay と loop
- 全遷移で shadow の 5px offset・濃度・柔らかさが変化しないこと
- 装飾配置
- 装飾クリック
- モバイル / PC 表示
- 拡大縮小時の印象
- 明背景 / 暗背景での透明輪郭
- SVG 欠損・破損時に無限待機せず安全に停止すること
- Chrome / Edge / Firefox / Safari の現行主要版
- source / bundle の挙動一致

#### 性能 budget

- 各 SVG：原則 raw 150 KB 以下、gzip 50 KB 以下
- path 数：原則 150 以下、共通 palette：原則 128 色以下
- 全 44 状態の転送量は旧本体・装飾資産合計を超えないことを目標とする
- hidden hit canvas は制作確認用 4 倍寸法を使用しない
- book / wake 再生中に frame deadline を連続して落とさない

budget を超える資産は自動的に不合格とはしないが、理由と実測値を比較レポートへ記録し、切替前に承認する。

#### 成果物
- 検証チェックリスト
- 不具合一覧
- 修正優先度表
- サイズ・path 数・転送量・再生性能レポート

---

### Phase 6：一括切替と旧資産削除

#### 目的
検証済み SVG-only 構成へ原子的に切り替え、旧形式と暫定コードを残さない。

#### 作業
- 本体 18 個の PNG / APNG を削除
- 装飾 6 個の PNG を削除
- `.png` 固定 URL、PNG fallback、比較用 toggle を削除
- `decorations.json` を SVG-only へ更新
- 形式依存 log / comment / translation を更新
- production bundle、文書、asset inventory を更新
- `rg` とテストで Frieren runtime の `.png` 参照がゼロであることを確認

#### ロールバック
runtime fallback は保持せず、問題が見つかった場合は Git commit / branch 単位で旧版へ戻す。

---

### Phase 7：任意の高級化（第二段階）

#### 目的
必要な部分のみパーツアニメーション化する。

#### 候補
- 瞬き
- 書ページのモーフ
- 軽微な揺れ
- 装飾発光
- 薬瓶アニメ
- 本の浮遊

#### 成果物
- SVG パーツ構成案
- 専用 animator 設計
- 試験実装

---

## 11. 期待される効果

### 11.1 視覚品質
- 拡大時の印象改善
- 輪郭のクリーン化
- ドット絵の crisp な保持

### 11.2 一貫性
- フレーム間の色・形・位置の揺れの軽減
- 本体と装飾の統一感向上
- サイト UI とのベクター統一

### 11.3 保守性
- 変換パイプラインの再利用
- 新規フレーム追加時のルール統一
- 将来のパーツアニメへの橋渡し

### 11.4 プロジェクト価値
- mp-ukagaka の個性として「SVGキャラシェル」という強い特徴を持てる
- Web 技術ベースのマスコットとして完成度が上がる

---

## 12. リスクと対策

### リスク1：SVG化しても元フレームのブレが残る
**対策：** SVG化前に正規化工程を必ず入れる。

### リスク2：ファイルサイズが想定以上に増える
**対策：**
- 共通パレット化
- 同色統合
- SVG最適化
- 必要なら一部だけ簡略化
- raw / gzip / path 数の budget を自動集計
- sequence ごとの段階 preload

### リスク3：読み込みや描画互換性
**対策：**
- 作業ブランチ上でのみ PNG/SVG 比較を行う
- ローカル・本番両方で検証
- 主要ブラウザで `<img>` / Canvas の両経路を検証
- runtime fallback ではなく Git 単位でロールバック

### リスク4：装飾の pixel hit detection が崩れる
**対策：**
- self-contained SVG 前提
- SVG から簡略 Canvas mask を生成可能にする
- 必要なら bbox 判定に簡略化

### リスク5：作業量が大きい
**対策：**
- まず本体 → 次に装飾
- 自動変換パイプライン化
- ルール固定で再作業を減らす

### リスク6：非整数縮小で pixel-art が不均一になる
**対策：**
- `208 x 328` から `158 x 249` への実表示を PoC 最優先項目にする
- 明背景・暗背景、100%・125%・150% browser zoom で比較する
- 一括制作前なら viewBox または表示寸法を変更できるものとする

### リスク7：ドロップシャドウがフレームごとに不揃いになる
**対策：**
- master の `character-drop-shadow` filter 定義を全フレームへ機械的に複製する
- dx、dy、stdDeviation、色、opacity、filter bounds を validator で比較する
- sequence 比較で shadow の裁切、距離、濃度、点滅を確認する

---

## 13. 完了条件（Definition of Done）

以下を満たした時点で、第一段階の移行完了とみなす。

1. `Frieren` 本体 38 状態が独立した SVG として存在する
2. 全 38 状態が共通 `viewBox`、表示基準、palette 方針、`character-drop-shadow` filter 定義を満たす
3. 全装飾 6 点が self-contained SVG になっている
4. `assets.json` が全 sequence、順序、duration、loop を明示し、validator を通過する
5. idle / sleep / wake up / book flip が SVG 資産のみで正常動作する
6. idle / sleep の時序が旧 APNG baseline と一致する
7. 装飾配置、z-index、透明 pixel hit detection が正常に動作する
8. 明背景・暗背景、モバイル・PC、browser zoom で重大な崩れがない
9. 見た目比較において、旧版より解像感・統一感が改善している
10. 性能 budget の実測値が記録され、超過項目が承認されている
11. source、production bundle、翻訳、canonical docs が同期している
12. Frieren 本体 18 個および装飾 6 個の PNG / APNG が削除されている
13. Frieren runtime に `.png` 固定参照、PNG fallback、比較用 toggle が残っていない
14. 自動テスト、E2E、visual regression、`npm --prefix tools/node run verify` が成功する

---

## 14. 推奨実施順序

実作業は、次の順番で一枚ずつ制作・確認する。自動トレースのみで完了とはせず、輪郭、顔、手、書籍、椅子、足元、基準位置を各フレームで目視確認する。

### 14.1 事前確認と master SVG の確定

1. 現行 PNG / APNG と装飾の寸法、透明領域、表示位置を記録する
2. `frieren-idle-00.svg` を master として、共通 `viewBox`、表示サイズ、座標原点、色パレット、輪郭ルールを確定する
3. 初期 SVG の `viewBox="0 0 208 328"` を新しい master 座標系として使用する
4. `width="832"`、`height="1312"` は制作・確認用の拡大寸法であるため、実表示ではそのまま使用しない
5. 全フレームで頭頂、目、手、書籍、椅子、足先の基準座標を共有する
6. 共通 `character-drop-shadow` の dx、dy、stdDeviation、色、opacity、filter bounds を確定する

#### 旧 PNG 寸法に関するメモ

現行本体 PNG の `134 x 249` は、Grok で翻書・瞬きなどの動画を制作し、APNG へ変換して必要フレームを抽出し、各フレームを個別に背景除去した制作工程で得られた最適寸法である。これは新しい SVG 資産が厳密に従うべき仕様ではない。

したがって、初期 SVG を `134 x 249` 用に描き直したり、縦横比を崩して強制変形したりする必要はない。`208 x 328` の master 座標系と縦横比を維持し、実表示サイズは CSS で現行キャラクターとおおむね同じ視覚サイズへ調整する。

初期値としては `height: 249px; width: auto;` を使用でき、この場合の外形寸法は約 `158 x 249` となる。実際の透明余白とキャラクターの見え方を確認し、必要に応じて表示高を前後させる。重要なのは旧 PNG のピクセル寸法との完全一致ではなく、次の項目である。

- ページ上でのキャラクターの視覚サイズが従来と大きく乖離しないこと
- SVG の縦横比を維持すること
- 全フレームで共通 `viewBox` と共通表示サイズを使用すること
- 頭頂・椅子・足底、特に足底の基準線を全フレームで揃えること
- フレーム切替時に位置や大きさが跳ねないこと

初期 SVG 例を現状の `width="832"`、`height="1312"` のままローダーへ渡すと、`naturalWidth` / `naturalHeight` によるサイズ固定の影響で表示が拡大する。runtime SVG は `208 x 328` の intrinsic size に正規化し、ローダー側でも CSS 表示寸法を明示して内部座標と分離する。

### 14.2 PoC セットの制作

以下を優先して一枚ずつ制作し、静止画・Canvas・`<img>` の各経路で比較する。

1. `frieren-idle-00`：本体 master、idle 表示、ドロップシャドウの基準
2. `frieren-idle-01`：短い瞬き区間と shadow パラメータ固定の検証
3. `frieren-book-01`：翻書開始フレーム
4. `frieren-book-05`：翻書中間フレーム
5. `frieren-sleep-00`：睡眠表示基準
6. `frieren-wake-01`：起床アニメ開始フレーム
7. `books`：複雑な装飾と背面 z-index の検証
8. `staff`：縦長装飾、透明領域、pixel hit detection の検証

PoC 完了条件は、サイズ、基準位置、色、輪郭、透明領域、ドロップシャドウ、クリック判定が現行 PNG と同等以上であり、非整数縮小で重大な shimmer がないこととする。

### 14.3 manifest と SVG sequence renderer の実装

1. `assets.json` schema と validator を実装する
2. `frieren.js` の `.png` 固定 URL 組立を manifest 参照へ置換する
3. `frieren-animation.js` に idle / sleep / book / wake 共通の sequence renderer を実装する
4. sequence ごとの preload、停止、切替、エラー終了を実装する
5. runtime の PNG fallback は実装しない
6. 比較期間中は旧ページと SVG テストページを別 build / branch で比較し、同一 runtime で形式を混在させない

### 14.4 本体フレームの逐次制作

PoC の仕様を固定した後、以下の順序で制作する。

1. 翻書：`frieren-book-01` ～ `frieren-book-11`
2. 起床：`frieren-wake-01` ～ `frieren-wake-05`
3. idle：`frieren-idle-00` ～ `frieren-idle-11`
4. sleep：`frieren-sleep-00` ～ `frieren-sleep-09`

現行 `frieren[0].png` は 12 フレームの APNG、`frieren[s].png` は 10 フレームの APNG である。したがって、トップレベルの本体ファイル数は 18 だが、実際に保持すべき本体の視覚状態は次の 38 状態となる。

- idle：12 状態
- sleep：10 状態
- book flip：11 状態
- wake up：5 状態

idle / sleep は静止 SVG 一枚や animated SVG 一ファイルへ置換せず、完全合成済みの独立 SVG を JavaScript sequence renderer で再生する。これにより再生時間、停止、再開、Canvas handoff を既存マネージャーから一元管理する。

各フレーム完成時に、直前・直後のフレームと重ね合わせ、輪郭の揺れ、位置ずれ、色差、意図しない孤立ピクセルを確認する。

### 14.5 装飾の逐次制作

本体 PoC の色・輪郭ルールを流用し、次の順序で制作する。

1. `books`
2. `staff`
3. `suitcase`
4. `evil_horns`
5. `dark_dragon_horn`
6. `potion`

各装飾は self-contained SVG とし、外部画像・外部フォント・外部フィルターへ依存させない。表示サイズ、z-index、透明領域、Canvas alpha pixel hit detection を個別に確認する。

### 14.6 統合検証と切替

1. idle / sleep / wake up / book flip を連続再生して遷移を確認する
2. モバイル、PC、拡大・縮小表示を確認する
3. 全本体 sequence で drop-shadow filter 定義が共通で、裁切や点滅がないことを確認する
4. 六種類の装飾について配置とクリック判定を確認する
5. SVG 読込失敗時に無限待機せず安全な表示で停止することを確認する
6. 見た目・透明度・性能比較レポートと未解決事項を記録する
7. 全項目合格後、旧 PNG / APNG と暫定参照を同一変更セットで削除する
8. production bundle、翻訳、文書を更新して full verify を実行する
9. 必要に応じて、瞬き・ページ morph・微動作などのパーツアニメ設計へ進む

以上より、第一段階の制作対象は本体 38 視覚状態と装飾 6 点、合計 44 視覚状態として管理する。

---

## 15. 次の具体アクション

### Step 1
**資産一覧表を作る**

対象ファイル名、用途、優先度、変換状態を一覧化する。

### Step 2
**asset manifest とフォルダ構成を確定する**

`assets.json`、`idle/`、`sleep/`、`book/`、`wake/` の schema と命名を確定する。正式切替は SVG-only とし、旧 PNG / APNG は検証完了後に一括削除する。

### Step 3
**変換仕様書（技術仕様）を作る**

- サイズ
- パレット
- viewBox
- 命名規則
- 共通ドロップシャドウ
- 透明度
- sequence timing
- 最適化ルール
- 性能 budget

### Step 4
**試験変換セットを選ぶ**

例：

- `frieren-idle-00`
- `frieren-idle-01`
- `frieren-book-01`
- `frieren-book-05`
- `frieren-sleep-00`
- `frieren-wake-01`
- `books`
- `staff`

を最初の PoC セットとする。

---

## 16. 将来的な発展案

第一段階完了後、以下の発展が可能である。

- 瞬きの SVG パーツアニメ化
- 翻書のページモーフ化
- 装飾の発光 / 浮遊
- キャラ本体の軽い呼吸 / 揺れ
- クリック反応時の SVG エフェクト
- ほか人格への SVG シェル展開

---

## 17. 結論

`Frieren` の全フレームおよび装飾を SVG 化する方針は、見た目の統一・解像感・将来拡張性の観点から非常に合理的である。

特に本プロジェクトは既にメニューやアイコンが SVG 化されており、キャラクターと装飾を SVG ベースへ移行することは、技術的にも美学的にも一貫した進化である。

本移行では、まず既存の視覚状態・再生順・時序を維持した JavaScript 管理の SVG sequence へ一括移行し、旧 PNG / APNG を削除する。その後に必要な箇所のみをパーツアニメ化する二段階方式を採ることで、runtime を単一形式に保ちながら高品質な刷新を実現できる。
