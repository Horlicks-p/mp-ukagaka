# mp-ukagaka / Frieren SVG Migration Hardening Review

対象ブランチ:

```text
plan/frieren-svg-migration-hardening
```

対象リポジトリ:

```text
Horlicks-p/mp-ukagaka
```

本レビューは、`main` と `plan/frieren-svg-migration-hardening` の差分および、主に以下の実装を静的に確認した結果をまとめたものです。

- `ghost/Frieren/frieren-animation.js`
- `ghost/Frieren/frieren-decorations.js`
- `ghost/Frieren/frieren-interactions.js`
- `ghost/Frieren/frieren.js`
- `ghost/Frieren/shell/Frieren/assets.json`
- `ghost/Frieren/shell/Frieren/**/*.svg`
- `ghost/Frieren/decorations/*.svg`
- `js/ukagaka-anime.js`
- `includes/core/ukagaka-functions.php`
- `includes/core/frontend-functions.php`
- `css/mpu_style.css`
- `tests/Unit/ShellImageListTest.php`
- `tools/node/test-frieren-shell-smoke.js`
- `tools/frieren-svg/*.py`
- `.gitattributes`
- `.github/workflows/release.yml`
- `tools/node/package.json`

---

# 1. 総評

今回の変更は単なる PNG → SVG 置換ではなく、Frieren shell の表示資産モデル全体を再設計した大規模な rendering migration です。

特に以下の点は非常に良いです。

- `assets.json` を Frieren の表示資産 source of truth にした
- idle / sleep / book_flip / wake をすべて同じ SVG sequence renderer へ統一した
- フレームごとの `duration_ms` を manifest 側へ移した
- 38 枚すべての body frame を共通 `viewBox` へ統一した
- 装飾も SVG-only 化した
- SVG 安全性検査を smoke test に組み込んだ
- runtime asset に raster fallback を残さない方針が明確
- development 用 reference / tool 群を `.gitattributes export-ignore` で release ZIP から除外している
- `ShellImageListTest` で SVG 優先と自然順を回帰テストしている
- `test-frieren-shell-smoke.js` で sequence timing / manifest validation / missing frame failure まで検査している

現時点で設計そのものはかなり堅いです。

ただし、merge 前に修正を強く推奨する項目が 2 件あります。

---

# 2. 優先度一覧

| 優先度 | 内容 | 推奨 |
|---|---|---|
| P1 | stale async load によるキャラ切替 race condition | merge 前に修正推奨 |
| P1 | 初回表示が sequence 全フレーム decode 完了まで待つ | merge 前に改善推奨 |
| P1/P2 | source と `dist` の同期を CI / verify が保証していない | release 前に修正推奨 |
| P2 | cleanup 後も SVG Image / Promise / manifest 参照が manager に残る | 改善推奨 |
| P2 | 背面 decoration が body 不透明部越しに click を奪う可能性 | 改善推奨 |
| P2 | mousemove 毎の pixel hit / layout read | 実測後に最適化 |
| P2 | Python SVG build pipeline の依存 version が固定されていない | 再現性改善 |

---

# 3. P1: キャラ切替中の stale async load race

## 問題

現在の Frieren 初期化は概ね以下の流れです。

```text
initFrierenMode
  ↓
loadFrierenAssets(baseUrl)
  ↓
fetch assets.json
  ↓
applyFrierenAssets()
  ↓
loadFrierenImages()
  ↓
applyFrierenBodyLayout(canvas)
```

一方、ユーザーが Frieren から別キャラへ切り替えた場合、

```text
cleanupFrierenElements()
  ↓
clearFrierenBodyLayout(canvas)
  ↓
generic character init
```

が走ります。

しかし、`assets.json` の `fetch()` や、その後の sequence load Promise はキャンセルされていません。

そのため以下が発生する可能性があります。

```text
Frieren 初期化開始
  ↓
assets.json fetch 中
  ↓
別キャラへ切替
  ↓
generic character 初期化
  ↓
古い Frieren fetch が完了
  ↓
applyFrierenAssets()
  ↓
loadFrierenImages()
  ↓
applyFrierenBodyLayout(canvas)
  ↓
generic character の canvas layout が Frieren 用へ上書きされる
```

さらに `loadFrierenImages()` 内では、

```js
window.mpuCanvasManager.imagesLoaded = true;
```

も書き換えるため、generic character 側の状態まで stale async continuation が触る可能性があります。

これは典型的な generation race です。

---

## 推奨修正

manager に generation token を持たせるのが安全です。

例:

```js
_frierenAssetGeneration: 0,
```

Frieren 初期化時:

```js
const generation = ++this._frierenAssetGeneration;
```

cleanup 時:

```js
++this._frierenAssetGeneration;
```

各 async continuation で:

```js
if (
  generation !== this._frierenAssetGeneration ||
  !this.isFrierenMode
) {
  return;
}
```

を確認します。

対象候補:

```text
fetch assets.json
applyFrierenAssets 後
loadFrierenSequence resolve/reject
showThenLoadRest
wake load completion
book_flip lazy load completion
```

可能なら `AbortController` を併用し、`assets.json` fetch 自体を cleanup で abort するとさらに安全です。

例:

```js
this._frierenAssetAbortController?.abort();
this._frierenAssetAbortController = new AbortController();

fetch(url, {
  credentials: "same-origin",
  signal: this._frierenAssetAbortController.signal,
});
```

---

## 追加テスト推奨

smoke test に以下を追加。

```text
1. Frieren 初期化開始
2. assets.json / idle sequence の load を意図的に遅延
3. load 完了前に cleanupFrierenElements()
4. generic mode を初期化
5. Frieren load を resolve
6. generic canvas の style / state が書き換わらないことを確認
```

確認項目:

- `isFrierenMode === false`
- generic canvas に Frieren の `mpuBodyBox` が付かない
- Frieren layout の inline style が復活しない
- `imagesLoaded` を stale Frieren loader が上書きしない
- Frieren `<img>` が再生成されない

---

# 4. P1: 初回表示が全フレーム load 完了待ち

## 現状

`loadFrierenImages()` は最初に必要な sequence 全体を、

```js
this.loadFrierenSequence(first)
```

で preload + decode してから表示しています。

idle の場合、12 フレーム全部が load/decode 完了するまで初回表示されません。

現在の branch では body SVG の raw size はおおよそ以下です。

```text
idle        約 1.60 MB
sleep       約 1.34 MB
book_flip   約 1.56 MB
wake        約 0.67 MB
-----------------------
合計        約 5.17 MB
```

gzip / Brotli 配信時はかなり縮む可能性がありますが、cold load の体感は sequence 全体の decode 待ちに左右されます。

---

## 推奨方式

初回 frame のみ先に表示し、残りを後から preload します。

理想:

```text
assets.json
  ↓
idle-00 のみ load
  ↓
即表示
  ↓
idle-01 ～ 11 preload
  ↓
sequence 全体 ready
  ↓
idle loop 開始
  ↓
book_flip preload
```

sleep 時も同じ。

```text
sleep-00 のみ
  ↓
即表示
  ↓
残り sleep frames
```

---

## 利点

### 1. First visual が早い

現在:

```text
12 frames ready
  ↓
Frieren visible
```

改善後:

```text
1 frame ready
  ↓
Frieren visible
```

### 2. resilience 向上

現在は idle sequence の 1 フレームが欠けるだけでも sequence 全体が `failed` になります。

初回 frame を独立扱いすれば、

```text
idle-00 は表示可能
残り sequence は failed
```

という安全な degradation が可能です。

### 3. book_flip / wake の厳格 failure policy は維持可能

book_flip / wake は animation なので、

```text
1 frame 欠損
  ↓
sequence 全体再生しない
```

でよいです。

つまり:

```text
idle/sleep
  初回 frame は tolerant
  loop は all-or-nothing

book_flip/wake
  sequence 全体 all-or-nothing
```

が合理的です。

---

# 5. P1/P2: source と production bundle の同期保証

## 現状

`tools/node/package.json` の `verify` は、

```text
version check
PHP lint
PHPCS
CSS lint
smoke tests
Frieren shell test
build
PHP tests
```

まで行っています。

しかし build 後に、

```text
git diff --exit-code
```

していません。

release workflow は build を実行せず、

```bash
git archive ...
```

で tag 内容をそのまま ZIP にしています。

つまり理論上、

```text
source JS 更新
  ↓
dist bundle 更新忘れ
  ↓
commit/tag
  ↓
release
  ↓
古い bundle 配布
```

が可能です。

---

## 推奨

`verify` の build 後に generated bundle の差分検査を追加。

概念:

```bash
npm run build
git diff --exit-code -- \
  js/dist \
  ghost/Frieren/dist
```

または専用 script:

```json
"verify:dist": "git diff --exit-code -- js/dist ghost/Frieren/dist"
```

を追加して、

```json
"verify": "... && npm run build && npm run verify:dist && npm run test:php"
```

とします。

---

# 6. P2: cleanup 後も frame Image 等が参照されたまま

## 現状

`cleanupFrierenElements()` では、

- animation timer
- body `<img>`
- decorations
- body layout
- DOM

は cleanup されています。

しかし以下は manager に残ります。

```text
frierenAssets
frierenLayout
frierenSequences
frierenSequenceLoads
frierenSequenceState
frierenIdleImage
frierenSleepImage
frierenWakeUpImages
frierenBookFlipImages
_bodyHitCanvas
_bodyHitCtx
_bodyHitSrc
```

特に各 frame に、

```js
frame.img = img;
```

が格納されています。

38 frame すべて decode 済みの場合、browser cache とは別に JS reference が残り続けます。

208 × 328 × RGBA × 38 の単純な decoded pixel estimate だけでも、

```text
約 10.4 MB
```

です。

実際には SVG parser / decode cache の管理はブラウザ依存ですが、明示的に解放する方が lifecycle としてきれいです。

---

## 推奨 cleanup

```js
this.frierenAssets = null;
this.frierenLayout = null;
this.frierenSequences = {};
this.frierenSequenceLoads = {};
this.frierenSequenceState = {};

this.frierenIdleImage = null;
this.frierenSleepImage = null;
this.frierenWakeUpImages = [];
this.frierenBookFlipImages = [];

this._bodyHitCanvas = null;
this._bodyHitCtx = null;
this._bodyHitSrc = "";
```

加えて、P1 の AbortController / generation token も cleanup で invalidate します。

---

# 7. P2: decoration click-through と body z-order の不一致可能性

## 現状

装飾の z-index は前後に分かれています。

例:

```text
背面装飾
  evil_horns  5
  books       6
  potion      7
  staff       8

Frieren body
  z-index 99

前面装飾
  suitcase   100
  dragon     101
```

これは視覚的には正しいです。

一方、body `<img>` が click target だった場合でも `setupDecorationClickThrough()` は、

```js
const type = this.findDecorationAt(e);
```

で背面 decoration を探索します。

このため理屈上、

```text
staff pixel
  ↓
Frieren の不透明 body
  ↓
mouse
```

でも `staff` が hit する可能性があります。

現在の配置では目立たない可能性がありますが、視覚 z-order と input z-order が一致していません。

---

## 推奨

body が click target の場合、最初に body pixel hit を確認します。

概念:

```js
if (
  target &&
  target.id === "frieren_idle_apng" &&
  this.isCharacterPixelHit &&
  this.isCharacterPixelHit(e, target)
) {
  return;
}
```

body が透明な pixel の時だけ:

```js
const type = this.findDecorationAt(e);
```

へ進めます。

これにより、

```text
body opaque pixel
  → body 優先

body transparent pixel
  → back decoration へ click-through

front decoration
  → decoration 自身が event target
```

となり、視覚と入力の z-order が一致します。

---

# 8. P2: mousemove hit test の performance

## 現状

`mousemove` ごとに:

```text
detectTouchZone()
  ↓
mpuGetCharacterRect()
  ↓
getBoundingClientRect()
  ↓
isCharacterPixelHit()
  ↓
getImageData(1x1)
```

body transparent 部分ではさらに:

```text
findDecorationAt()
  ↓
getComputedStyle × decoration
  ↓
sort
  ↓
getBoundingClientRect()
  ↓
isPixelHit()
  ↓
getImageData(1x1)
```

が走ります。

---

## 良い点

body hit canvas は frame src 単位で cache されており、

```js
if (this._bodyHitSrc !== src) {
  drawImage(...)
}
```

なので、mousemove 毎に SVG 全体を rasterize しているわけではありません。

また、

```js
getContext("2d", { willReadFrequently: true })
```

も使われています。

6 decorations 程度なら実機上問題ない可能性が高いです。

---

## 推奨

今すぐ rewrite は不要。

まず Chrome Performance で確認。

もし mousemove 時の CPU cost が目立つなら:

### 第1候補

`requestAnimationFrame` throttle。

```text
mousemove 多発
  ↓
latest pointer position だけ保存
  ↓
1 frame 1 回だけ hit test
```

### 第2候補

decoration の以下を load / resize 時に cache:

- z-index
- rect
- hit canvas metadata

---

# 9. P2: Python build pipeline の version pin

## 現状

`tools/frieren-svg` は以下を利用しています。

```text
numpy
Pillow
scipy
```

処理内容もかなり重要です。

- white matte removal
- premultiplied resampling
- palette quantization
- binary alpha
- connected component
- morphology
- motion mask
- outline repair
- per-frame recipe
- hair / eye repair
- suitcase recolor / resample

しかし Python dependency version を固定するファイルが見当たりません。

---

## リスク

将来、

```text
Pillow version変更
SciPy version変更
NumPy version変更
```

により resampling / morphology の微差が出る可能性があります。

この pipeline は 1px 単位の差が成果物へ直結するため、再現性を確保した方がよいです。

---

## 推奨

最低限:

```text
tools/frieren-svg/requirements.txt
```

を追加。

例:

```text
numpy==...
Pillow==...
scipy==...
```

または `pyproject.toml`。

---

# 10. 良い点の詳細

## 10.1 assets.json

非常に良い設計です。

```text
file name
```

から animation の意味を推測せず、

```json
{
  "src": "...",
  "duration_ms": 50
}
```

で再生順・時間を manifest 化しています。

idle / sleep / book_flip / wake の loop flag も明示されており、runtime と asset generation の責任分離ができています。

---

## 10.2 SVG safety smoke test

`test-frieren-shell-smoke.js` はかなり強力です。

body/decor SVG に対し以下を機械検査しています。

```text
<script> 禁止
foreignObject 禁止
event handler attribute 禁止
embedded <image> 禁止
href 禁止
stylesheet 禁止
external url() 禁止
base64 禁止
mask 禁止
approved drop-shadow 以外 filter 禁止
viewBox 統一
preserveAspectRatio="none" 強制
透明 hole 禁止
isolated pixel 禁止
38 body files 数確認
raster decoration 残存禁止
```

さらに runtime sequence について:

```text
manifest validator
loop timing
one-shot timing
missing frame failure
book flip → idle handoff
wake callback timing
```

まで fake clock で確認しています。

これはかなり信頼できます。

---

## 10.3 release packaging

`.gitattributes` に:

```text
tools/ export-ignore
tests/ export-ignore
plan/ export-ignore
.github/ export-ignore
```

があるため、今回追加した大量の:

```text
reference PNG
conversion tools
QA scripts
development-only files
```

は release ZIP に混入しません。

この点は問題ありません。

---

## 10.4 SVG build pipeline

`tools/frieren-svg` は単純な auto trace ではありません。

特に `convert.py` / `recipes.py` / `layers.py` 側では:

```text
white matte removal
premultiplied BOX resize
shared palette snap
alpha binarization
frame difference detection
motion mask
static region lock
hair reconstruction
outline repair
eye color correction
frame-specific recipe
```

まで実装されています。

これは今回の「全 SVG 化」において大きな価値があります。

---

# 11. 推奨 merge 前チェックリスト

## 必須寄り

- [ ] stale async generation guard を追加
- [ ] cleanup 時に generation invalidate
- [ ] 可能なら AbortController 導入
- [ ] キャラ切替 race の smoke test 追加
- [ ] first frame first rendering を検討
- [ ] `dist` parity check を verify に追加

## 改善推奨

- [ ] cleanup 時に frame Image / Promise / manifest reference を解放
- [ ] body opaque pixel 時は back decoration へ click-through しない
- [ ] Chrome Performance で mousemove hit test 確認
- [ ] Python dependency pin を追加

---

# 12. 実機確認項目

静的レビューだけでは確認できないため、merge 前に最低限以下を確認推奨。

## Cold load

- Frieren が何 ms で初回表示されるか
- idle 12 frames download/decode 中に blank が続かないか
- DevTools Network で SVG に gzip/Brotli が効いているか

## Character switching

連続で:

```text
Frieren
→ 他キャラ
→ Frieren
→ 他キャラ
```

を高速操作。

確認:

- frame layout 崩れなし
- generic canvas に Frieren margin が残らない
- duplicate `#frieren_idle_apng` がない
- stale animation が復活しない

## Decoration

- body の透明部分越しに背面 decoration を押せる
- body の不透明部分越しには背面 decoration を押せない
- suitcase / dragon 等の前面 decoration は正常
- cursor と click target が一致

## Animation

- idle
- sleep
- wake
- wake → book flip
- book flip → idle
- book_flip preload 前の手動 trigger
- network failure 時の degradation

## Browser

最低限:

- Chrome / Chromium
- Firefox
- Safari が必要なら Safari

---

# 13. Claude への確認依頼ポイント

以下の観点で再評価を推奨します。

1. `loadFrierenAssets()` / `loadFrierenSequence()` / `cleanupFrierenElements()` 間に stale async race が本当に存在するか
2. generation token / AbortController のどちらがこの設計に適しているか
3. first-frame-first rendering を入れる場合、現行 state machine をどう最小変更で保つべきか
4. `frierenSequenceLoads` の Promise cache を cleanup / retry 時どう扱うべきか
5. decoration click-through の z-order arbitration が正しいか
6. body pixel hit cache の invalidation 条件が十分か
7. `npm run build` 後の dist parity verification が必要か
8. Python asset generation dependency を pin すべきか
9. 他に lifecycle / memory / race / rendering performance の見落としがないか
10. 現在の SVG-only 方針を維持したまま、より安全な最小修正案を提示できるか

---

# 14. 最終判定

現時点では、

```text
設計品質: 高い
asset migration: 成功
test coverage: 強い
release packaging: 良い
runtime lifecycle: 追加 hardening 推奨
cold-load UX: 改善余地あり
```

という評価です。

特に merge 前に優先すべきなのは以下の 2 点です。

```text
1. stale async race
2. first frame first rendering
```

この 2 点を処理した後であれば、今回の SVG migration はかなり安心して main へ統合できる状態に近いと考えます。

---

## 注記

本レビューは GitHub 上の branch を対象とした静的コードレビューです。

実際の checkout / browser 実行 / DevTools profiling / WordPress 実環境での E2E 実行までは行っていません。

そのため、最終 merge 判定前には:

```text
npm run verify
Chrome Performance
cold load
rapid character switching
decoration hit test
```

の実機確認を推奨します。
