# mp-ukagaka Dialog Color Theme System 計画

Repository:

```text
Horlicks-p/mp-ukagaka
```

Target:

```text
main
```

目的:

> 現在の AVG / RPG 風「旅の魔導書」ダイアログ UI の構造・操作感はそのまま維持し、  
> 後台から選択できる複数の配色テーマを追加する。

初期テーマは 4 種類:

```text
default   = Traveler's Grimoire / 旅の魔導書
sapphire  = Sapphire / 蒼藍魔導書
crimson   = Crimson / 深紅魔導書
forest    = Forest / 翠緑魔導書
```

重要:

- 今回は **レイアウト変更ではなく palette/theme system の追加**
- Frieren renderer / generic renderer / chat lifecycle は触らない
- 現在の AVG 風 UI 構造を崩さない
- 初版は global theme とする
- 将来 character-specific theme を追加できる構造にする

---

# 1. 現在の実装前提

現在のダイアログ UI は既に theme 化しやすい構造になっている。

`css/mpu_style.css` の `#mp_ukagaka` には以下の dialog token が存在する:

```css
--mpu-internal-dialog-frame-dark
--mpu-internal-dialog-frame-mid
--mpu-internal-dialog-accent
--mpu-internal-dialog-paper
--mpu-internal-dialog-paper-translucent
--mpu-internal-dialog-paper-light
--mpu-internal-dialog-paper-dark
--mpu-internal-dialog-text
--mpu-internal-dialog-muted
--mpu-internal-dialog-link
--mpu-internal-dialog-link-hover
--mpu-internal-dialog-shadow
--mpu-internal-dialog-state-accent
--mpu-internal-dialog-glow
```

現在の main UI asset:

```text
images/msgbox-frame.svg
images/msgbox-nameplate.svg
images/msgbox-sparkle.svg
images/msgbox-hexagram.svg
```

注意:

これら SVG の色は SVG ファイル内に直接書かれている。

そのため CSS custom property だけを変更しても、

```text
frame
nameplate
sparkle
hexagram
```

の色は変わらない。

完全な theme switch には:

```text
CSS palette
+
theme-specific SVG palette
```

の両方が必要。

---

# 2. Theme selection architecture

Front-end root:

```html
<div id="mp_ukagaka" data-mpu-dialog-theme="default">
```

Blue example:

```html
<div id="mp_ukagaka" data-mpu-dialog-theme="sapphire">
```

Theme は server-side で最初から HTML に出力する。

## Do not

ページロード後に JS で:

```js
element.dataset.mpuDialogTheme = ...
```

と初期テーマを設定しない。

理由:

```text
page render
default theme visible
↓
JS load
↓
selected theme applied
```

という FOUC / color flash が発生する可能性がある。

今回の theme は WordPress option から分かっているため、PHP で最初から正しい attribute を出す。

---

# 3. Option key

新しい global option:

```php
dialog_theme
```

default:

```php
"dialog_theme" => "default",
```

`includes/core/core-functions.php` の `mpu_default_opt()` に追加。

Allowed values:

```php
array(
    'default',
    'sapphire',
    'crimson',
    'forest',
)
```

保存時は必ず allowlist で検証する。

例:

```php
$allowed_dialog_themes = array(
    'default',
    'sapphire',
    'crimson',
    'forest',
);

$posted_dialog_theme = isset( $_POST['dialog_theme'] )
    ? sanitize_key( wp_unslash( $_POST['dialog_theme'] ) )
    : 'default';

$mpu_opt['dialog_theme'] = in_array(
    $posted_dialog_theme,
    $allowed_dialog_themes,
    true
)
    ? $posted_dialog_theme
    : 'default';
```

任意文字列や file path を option として使わない。

---

# 4. Helper function を用意する

Theme 判定をテンプレート内に散らさない。

例:

```php
function mpu_get_dialog_theme( $mpu_opt = null ): string {
    if ( null === $mpu_opt ) {
        $mpu_opt = mpu_get_option();
    }

    $allowed = array(
        'default',
        'sapphire',
        'crimson',
        'forest',
    );

    $theme = isset( $mpu_opt['dialog_theme'] )
        ? sanitize_key( $mpu_opt['dialog_theme'] )
        : 'default';

    return in_array( $theme, $allowed, true )
        ? $theme
        : 'default';
}
```

`mpu_html()`:

```php
$dialog_theme = mpu_get_dialog_theme( $mpu_opt );
```

output:

```php
<div
    id="mp_ukagaka"
    data-mpu-dialog-theme="<?php echo esc_attr( $dialog_theme ); ?>"
>
```

将来的に character override を追加する場合も、この helper の内部だけ拡張できるようにする。

---

# 5. Future-compatible resolution order

初版は global theme のみ。

ただし将来以下へ拡張可能な設計にする:

```text
character-specific dialog_theme
        ↓
global dialog_theme
        ↓
default
```

将来例:

```php
mpu_get_dialog_theme( $ukagaka_num )
```

ただし **今回 character-specific theme は実装しない**。

理由:

- scope を増やさない
- generic / DIY character settings を再度触らない
- global UI feature としてまず安定させる

---

# 6. Admin UI

場所:

```text
設定
→ 通用設定
→ 🎨 樣式設定
```

現在ここには:

```text
使用自訂樣式
自訂樣式連結
```

が存在する。

その上に:

```text
對話框配色：
[ 旅の魔導書（Default） ▼ ]
```

を追加する。

推奨 UI:

```text
對話框配色
┌──────────────────────────────┐
│ 旅の魔導書（Default）       ▼│
└──────────────────────────────┘

・旅の魔導書 / Default
・蒼藍魔導書 / Sapphire
・深紅魔導書 / Crimson
・翠緑魔導書 / Forest
```

初版は `<select>` で十分。

Option values:

```text
default
sapphire
crimson
forest
```

Display labels は translation 対応。

---

# 7. Optional admin color preview

可能なら select の下に 4 色程度の small palette preview を追加してよい。

例:

```text
■ ■ ■ ■  Traveler's Grimoire
■ ■ ■ ■  Sapphire
■ ■ ■ ■  Crimson
■ ■ ■ ■  Forest
```

ただし:

- front-end dialog DOM を admin に複製しない
- 画像全体を preview しない
- JS-heavy live preview は不要
- admin feature のために production UI architecture を複雑化しない

Simple swatches で十分。

---

# 8. `no_style` との関係

現在:

```text
使用自訂樣式 = ON
```

の場合 plugin built-in CSS は読み込まれない。

したがって `dialog_theme` を保存していても、built-in CSS が無い場合は基本的に見た目へ影響しない。

Admin UI では:

```text
「使用自訂樣式が有効な場合、内蔵ダイアログテーマは適用されません」
```

の説明を追加する。

推奨:

- theme selection 自体は保存しておく
- `no_style` ON でも値を消さない
- UI を disabled にするかどうかは任意
- hidden にしない方が設定状態を理解しやすい

Do not:

```text
no_style ON
→ dialog_theme を default に強制リセット
```

ユーザーが custom style を OFF に戻した時、以前の選択を復元できる方がよい。

---

# 9. CSS token architecture

現在の default values をそのまま基準とする。

Default:

```css
#mp_ukagaka,
#mp_ukagaka[data-mpu-dialog-theme="default"] {
  --mpu-internal-dialog-frame-dark: #3d2d20;
  --mpu-internal-dialog-frame-mid: #8d6a44;
  --mpu-internal-dialog-accent: #c69d63;

  --mpu-internal-dialog-paper: #ecdabe;
  --mpu-internal-dialog-paper-translucent: rgb(236 218 190 / 82%);
  --mpu-internal-dialog-paper-light: #f5ead6;
  --mpu-internal-dialog-paper-dark: #dcc7a4;

  --mpu-internal-dialog-text: #403a34;
  --mpu-internal-dialog-muted: #5f574d;
  --mpu-internal-dialog-link: #335a72;
  --mpu-internal-dialog-link-hover: #6b4f2e;

  --mpu-internal-dialog-shadow: rgb(28 23 19 / 22%);
  --mpu-internal-dialog-glow: #f5ead6;
}
```

重要:

既存 `#mp_ukagaka` default を消して attribute selector だけにしない。

古い custom markup / integration で attribute が無い場合も、現在の default appearance を維持する。

---

# 10. Theme 1 — Default / Traveler's Grimoire

現在の production 配色を基準とする。

Concept:

```text
worn leather
warm parchment
muted brass
dark brown ink
```

Suggested palette:

```text
frame dark       #3d2d20
frame mid        #8d6a44
accent           #c69d63
paper            #ecdabe
paper light      #f5ead6
paper dark       #dcc7a4
text             #403a34
muted            #5f574d
link             #335a72
link hover       #6b4f2e
inner line       #c9aa7c
glow             #f5ead6
```

**Current production default must remain visually unchanged as much as possible.**

これは backward compatibility の基準テーマ。

---

# 11. Theme 2 — Sapphire / 蒼藍魔導書

Concept:

```text
ice
water
ancient magic
blue-gray leather
silver-blue metal
cold parchment
```

Avoid:

```text
neon blue
SAO blue
bright cyan
```

Suggested palette:

```text
frame dark       #263643
frame mid        #536f82
accent           #8faebe

paper            #dfe6e4
paper alpha      rgb(223 230 228 / 82%)
paper light      #edf2ef
paper dark       #becdce

text             #29363c
muted            #52636b
link             #365f7a
link hover       #27495f

shadow           rgb(20 29 35 / 22%)
inner line       #a9bcc4
glow             #edf5f5
```

SVG frame should read as blue-gray metal / leather, not fluorescent fantasy HUD.

---

# 12. Theme 3 — Crimson / 深紅魔導書

Concept:

```text
old red leather
fire magic
forbidden book
aged rose parchment
copper highlights
```

Avoid:

```text
pure #ff0000
alarm red
error UI appearance
```

Suggested palette:

```text
frame dark       #472b29
frame mid        #7a4b45
accent           #b37868

paper            #ead9cf
paper alpha      rgb(234 217 207 / 82%)
paper light      #f4e7de
paper dark       #ceb6a9

text             #40302d
muted            #69504a
link             #70444a
link hover       #8b3f38

shadow           rgb(35 20 19 / 22%)
inner line       #c99682
glow             #f3ddd3
```

注意:

`error` stream state と theme 自体が混同されないようにする。

Crimson theme の通常状態は「エラー表示」に見えない彩度にする。

---

# 13. Theme 4 — Forest / 翠緑魔導書

Concept:

```text
forest
elf
herbal magic
moss
old botanical book
```

Avoid:

```text
bright game-health-bar green
neon green
```

Suggested palette:

```text
frame dark       #29372f
frame mid        #566b58
accent           #91a478

paper            #e2e2ce
paper alpha      rgb(226 226 206 / 82%)
paper light      #eeeedc
paper dark       #c4c7aa

text             #303930
muted            #586257
link             #496a63
link hover       #3d584d

shadow           rgb(22 29 24 / 22%)
inner line       #aeb58a
glow             #eeefdc
```

---

# 14. Stream states must remain semantic

現在:

```text
thinking
streaming
status
tool
error
timeout
busy
```

は UI state を示す。

Theme palette と state palette を混同しない。

例えば Crimson theme でも:

```text
error
```

は通常 frame より明確に distinguish できる必要がある。

Theme selectors:

```css
#mp_ukagaka[data-mpu-dialog-theme="crimson"] {
  ...
}
```

State selectors:

```css
#ukagaka_msgbox[data-mpu-stream-state="error"] {
  ...
}
```

責務を分ける。

Theme の変更だけで state semantics を消さない。

---

# 15. SVG asset problem

以下 SVG は palette が hard-coded:

```text
msgbox-frame.svg
msgbox-nameplate.svg
msgbox-sparkle.svg
msgbox-hexagram.svg
```

外部 SVG を:

```css
border-image-source: url(...)
```

として使用しているため、parent document の CSS variable や `currentColor` を直接受け取れない。

そのため theme ごとに SVG variant が必要。

---

# 16. Recommended SVG directory

推奨:

```text
images/dialog-themes/
├── default/
│   ├── frame.svg
│   ├── nameplate.svg
│   ├── sparkle.svg
│   └── hexagram.svg
│
├── sapphire/
│   ├── frame.svg
│   ├── nameplate.svg
│   ├── sparkle.svg
│   └── hexagram.svg
│
├── crimson/
│   ├── frame.svg
│   ├── nameplate.svg
│   ├── sparkle.svg
│   └── hexagram.svg
│
└── forest/
    ├── frame.svg
    ├── nameplate.svg
    ├── sparkle.svg
    └── hexagram.svg
```

Backward compatibility のため、既存:

```text
images/msgbox-frame.svg
images/msgbox-nameplate.svg
images/msgbox-sparkle.svg
images/msgbox-hexagram.svg
```

を即座に消す必要はない。

選択肢:

### A. 既存ファイルを default として維持

```text
default -> existing msgbox-*.svg
other themes -> dialog-themes/*
```

最小差分。

### B. 全テーマを新 directory に統一

綺麗だが移行差分が増える。

今回の task では **A を推奨**。

---

# 17. Asset URL も CSS custom property 化

現在:

```css
border-image-source: url("../images/msgbox-frame.svg");
```

を:

```css
border-image-source: var(--mpu-internal-dialog-frame-image);
```

へ変更。

Default:

```css
#mp_ukagaka {
  --mpu-internal-dialog-frame-image:
    url("../images/msgbox-frame.svg");
  --mpu-internal-dialog-nameplate-image:
    url("../images/msgbox-nameplate.svg");
  --mpu-internal-dialog-sparkle-image:
    url("../images/msgbox-sparkle.svg");
  --mpu-internal-dialog-hexagram-image:
    url("../images/msgbox-hexagram.svg");
}
```

Sapphire:

```css
#mp_ukagaka[data-mpu-dialog-theme="sapphire"] {
  --mpu-internal-dialog-frame-image:
    url("../images/dialog-themes/sapphire/frame.svg");
  --mpu-internal-dialog-nameplate-image:
    url("../images/dialog-themes/sapphire/nameplate.svg");
  --mpu-internal-dialog-sparkle-image:
    url("../images/dialog-themes/sapphire/sparkle.svg");
  --mpu-internal-dialog-hexagram-image:
    url("../images/dialog-themes/sapphire/hexagram.svg");
}
```

同じ pattern を crimson / forest に適用。

---

# 18. Replace every hard-coded dialog asset reference

最低限検索する:

```text
msgbox-frame.svg
msgbox-nameplate.svg
msgbox-sparkle.svg
msgbox-hexagram.svg
```

対象例:

```css
border-image-source
border-image
background
#ukagaka_msgbox::before
cancel plate
nameplate
preload pseudo-element
```

特に:

```text
.mpu-dialog-name
.mpu-dialog-cancel
```

は同じ nameplate SVG を共有している。

Theme switch 後も両方が同じ theme asset を参照すること。

---

# 19. Preload is important

現在 `#mp_ukagaka::after` は dialogue asset を preload している。

理由:

`#ukagaka_msgbox` が初期状態で `display:none` の場合、画像の download が遅れて:

```text
text visible
↓
frame asset late
```

という flash が起こるのを防ぐため。

Theme system 追加後もこの目的を壊さない。

推奨:

asset URL custom property を preload にも使用。

概念:

```css
#mp_ukagaka::after {
  content:
    var(--mpu-internal-dialog-frame-image)
    var(--mpu-internal-dialog-nameplate-image)
    var(--mpu-internal-dialog-hexagram-image)
    var(--mpu-internal-dialog-sparkle-image)
    url("../images/think-bubble.svg")
    url("../images/think-tail.svg");
}
```

ブラウザで実際に preload が行われることを確認する。

もし `content: var(...)` の image list 挙動が不安定なら:

- theme selector ごとに preload `content:` を明示
- または selected theme asset だけ別 preload node で読み込む

**全 16 SVG を毎回 preload する実装は避けたい。**

現状 4 theme なら量は小さいが、theme 数が増えると不要 download が増える。

---

# 20. Do not use CSS hue-rotate

禁止推奨:

```css
filter: hue-rotate(...);
```

理由:

- parchment まで染まる
- shadow まで変わる
- contrast 管理が難しい
- brass / leather / paper の色関係が崩れる
- theme が「Photoshop filter」に見える

Theme は明示 palette substitution で作る。

---

# 21. Generate SVG variants; do not hand-maintain geometry

4 themes × 4 assets:

```text
16 SVG
```

を手作業で geometry editing しない。

Geometry は現在の approved SVG を source とする。

Theme variant は palette replace だけで生成する。

理想:

```text
base geometry
+
theme palette map
↓
generator
↓
theme SVG variants
```

例:

```json
{
  "sapphire": {
    "#2a1d12": "...",
    "#c69d63": "...",
    "#b58d58": "...",
    "#97734a": "...",
    "#4a3728": "...",
    "#3d2d20": "...",
    "#ecdabe": "...",
    "#c9aa7c": "..."
  }
}
```

注意:

単純に「brown -> blue」を機械的に 1 色ずつ置換するだけでなく、

```text
dark edge
mid band
highlight
paper
inner line
shadow
```

の役割を維持する。

---

# 22. Geometry must remain byte/shape equivalent where possible

Theme SVG 間で変えてよいもの:

```text
fill
stroke
fill-opacity
```

Theme SVG 間で変えないもの:

```text
viewBox
width
height
path d
border slice geometry
pixel corners
shape-rendering
```

特に:

```text
frame.svg
nameplate.svg
```

は `border-image-slice` に依存している。

1px の geometry 差でも frame line が壊れる可能性がある。

---

# 23. Keep native pixel dimensions

Current assets:

```text
msgbox-frame.svg      47×47
msgbox-nameplate.svg  16×16
msgbox-sparkle.svg     7×7
msgbox-hexagram.svg   31×31
```

Theme variant も同じ native size を維持する。

Do not:

- scale source SVG before saving
- add fractional coordinates
- add transforms unless necessary
- introduce anti-alias-heavy geometry

`shape-rendering="crispEdges"` を維持。

---

# 24. Theme should not alter layout

Theme selector で変更してよいもの:

```text
colors
asset URLs
shadow color/opacity
link color
glow color
```

Theme selector で変更しないもの:

```text
width
height
left
top
transform
border widths
border-image-slice
padding
nameplate size
cancel position
page cursor position
hexagram size
chat layout
gift picker layout
scrolling
```

つまり screenshot 比較時に geometry は完全一致するのが理想。

---

# 25. AVG controls must stay unchanged

現在:

```text
Cancel = top-right × plate
OK     = bottom-right ▼ RPG page cursor
```

この layout は全 theme 共通。

Theme によって:

```text
button position
hit area
Tab order
action
```

を変えない。

`#mpu_ok_btn` の semantic label:

```text
Next
Send
Give the gift
```

も現在の動的同期を維持。

---

# 26. State badge and long-name protection

現在 main には:

- long character name が × と badge に重ならない
- badge width を CSS variable へ反映
- badge clear 時に width variable も clear
- OK label の action-aware sync

が存在する。

Theme implementation でこれらを壊さない。

特に:

```text
--mpu-internal-dialog-badge-width
```

に関係する `max-width` 計算を theme selector で overwrite しない。

---

# 27. `data-mpu-stream-state` and `data-mpu-dialog-theme`

この 2 つは別責務:

```html
<div
  id="mp_ukagaka"
  data-mpu-dialog-theme="sapphire"
>
  <div
    id="ukagaka_msgbox"
    data-mpu-stream-state="thinking"
  >
```

Theme:

```text
visual palette
```

Stream state:

```text
runtime status
```

互いを混同しない。

---

# 28. Admin save compatibility warning

`mpu_save_general_settings()` は general form の値を明示的に保存する方式。

新しい `dialog_theme` を:

```text
mpu_default_opt()
options_general.php
mpu_save_general_settings()
```

の **3 箇所すべて** に通すこと。

また AI / LLM 等、別ページ保存 helper が general option を「preserve list」でコピーしている場合は:

```text
dialog_theme
```

が別ページ保存によって落ちないか必ず確認する。

特に `mpu_save_ai_settings()` 等の:

```php
foreach ( [ ...general keys... ] as $key )
```

の preserve list を確認する。

**これは重要。**

ある設定ページで theme を保存した後、AI 設定を保存したら theme option が消える、という regression を防ぐ。

---

# 29. Backward compatibility

既存 installation には:

```text
dialog_theme
```

option が存在しない。

`mpu_merge_option_defaults()` により default が補完されることを確認。

Expected:

```text
old install
dialog_theme missing
↓
default
↓
現在と同じ見た目
```

Migration script は不要なはず。

---

# 30. Security

`dialog_theme` は path や arbitrary CSS として直接使用しない。

禁止:

```php
$path = 'dialog-themes/' . $_POST['dialog_theme'] . '/frame.svg';
```

without allowlist.

必ず:

```text
default
sapphire
crimson
forest
```

から選ぶ。

HTML output は:

```php
esc_attr()
```

を使用。

---

# 31. Accessibility / contrast

4 themes 全てで最低限確認:

- body text vs paper
- link vs paper
- hover link vs paper
- badge text vs badge background
- focus-visible outline
- × icon
- ▼ cursor
- input placeholder
- gift picker text

目標:

```text
normal text 4.5:1 以上
```

現在 default の contrast 品質を落とさない。

Concept image の色をそのまま採用せず、実 CSS 値で contrast を測定する。

---

# 32. Dark host theme

Plugin 側 theme が:

```text
WordPress/site dark mode
```

の影響で filter / opacity / inherited color を受けないことを確認。

特に:

- SVG frame
- parchment
- nameplate
- link
- state badge
- gift picker
- input

を確認。

---

# 33. Admin option naming / labels

Option:

```text
dialog_theme
```

Admin label examples:

Japanese:

```text
ダイアログ配色
旅の魔導書（デフォルト）
蒼藍魔導書
深紅魔導書
翠緑魔導書
```

Traditional Chinese:

```text
對話框配色
旅之魔導書（預設）
蒼藍魔導書
深紅魔導書
翠綠魔導書
```

English:

```text
Dialogue color theme
Traveler's Grimoire (Default)
Sapphire Grimoire
Crimson Grimoire
Forest Grimoire
```

既存の i18n workflow に従って `.pot/.po` 等を更新。

---

# 34. Suggested implementation phases

## Phase 1 — Option plumbing

- `mpu_default_opt()` に `dialog_theme`
- sanitizer / allowlist
- general settings save
- other settings save preservation
- admin `<select>`
- i18n
- `data-mpu-dialog-theme` output

この段階では見た目をまだ変更しない。

Test:

```text
default save
sapphire save
invalid value -> default
AI settings save after sapphire -> sapphire remains
reset options -> default
```

---

## Phase 2 — CSS theme tokens

- current default variables unchanged
- sapphire variables
- crimson variables
- forest variables
- asset URL variables

まだ SVG variants が無ければ、default asset を暫定使用してもよい。

Goal:

text / paper / control palette switching の plumbing を先に確認。

---

## Phase 3 — SVG palette variants

Generate:

```text
sapphire/
crimson/
forest/
```

各 4 SVG。

Check:

```text
frame geometry identical
nameplate geometry identical
sparkle native 7×7
hexagram native 31×31
crispEdges retained
no raster embedded
no external URLs
```

---

## Phase 4 — Preload

selected theme assets が first dialogue 前に load されることを確認。

Cold-cache test を行う。

---

## Phase 5 — Admin preview

必要なら simple palette swatches を追加。

これは optional。

---

# 35. Required automated tests

可能なら PHP unit test を追加。

最低限:

### Theme sanitizer

```text
default   -> default
sapphire  -> sapphire
crimson   -> crimson
forest    -> forest
evil/path -> default
unknown   -> default
empty     -> default
```

### Default merge

Old options without `dialog_theme`:

```text
-> default
```

### Admin save

Posted:

```text
dialog_theme=sapphire
```

saved:

```text
sapphire
```

Invalid value:

```text
dialog_theme=../../x
```

saved:

```text
default
```

### Cross-page preservation

Theme saved as:

```text
forest
```

then save AI / LLM / other settings page.

Expected:

```text
forest remains
```

---

# 36. Required front-end / E2E checks

4 themes × basic states.

Minimum:

```text
default:
  normal
  chat
  thinking
  gift picker

sapphire:
  normal
  chat
  thinking
  gift picker

crimson:
  normal
  error
  chat

forest:
  normal
  streaming
  chat
```

Also:

- long character name
- English "Responding..."
- Japanese badge
- Chinese badge
- one-line message
- multi-line message
- scrollbar
- custom DIY character
- Frieren
- dark host page

---

# 37. Screenshot geometry regression

Theme switch should only change palette.

Recommended screenshot comparison:

```text
default
sapphire
crimson
forest
```

same:

- viewport
- character
- message
- name
- state
- chat mode

Compare geometry.

Expected:

```text
same width
same height
same nameplate location
same × location
same ▼ location
same hexagram location
same text wrapping
```

If text wrapping changes, inspect whether font/color CSS accidentally changed typography/layout.

---

# 38. Performance

Do not add runtime JS theme switching for initial implementation.

Server-render selected theme.

Only selected theme SVG should be loaded/preloaded.

Expected cost:

```text
4 selected theme SVG assets
```

not:

```text
all theme SVG assets
```

No timer, observer, or request should be added solely for theme selection.

---

# 39. Build / dist

If PHP / CSS only changes:

- verify CSS lint
- verify PHP tests
- run full project verify

If JS changes unexpectedly:

- rebuild bundles
- dist parity

Preferred:

```bash
npm --prefix tools/node run verify
```

and existing interaction E2E when practical:

```bash
npm --prefix tools/node run test:interaction
```

Theme feature should ideally require **zero production JS changes**.

---

# 40. Documentation

Update at least relevant user/customization docs.

Document:

```text
Dialogue color theme
default theme
available themes
custom CSS/no_style interaction
data-mpu-dialog-theme
```

If `CANVAS_CUSTOMIZATION.md` documents dialog CSS internals, add the attribute and explain that internal variables may change.

Do not accidentally declare every `--mpu-internal-*` variable a stable public API unless intentionally supported.

Current naming explicitly says `internal`.

---

# 41. Important compatibility notes

## Do not change the default appearance accidentally

`default` is current production appearance.

Theme feature should not mean:

```text
default v2 redesign
```

It means:

```text
current appearance, now named default
```

## Do not touch generic shell rendering

No changes to:

```text
mpu_list_shell_images()
isFrieren()
loadGeneration
generic image loader
Frieren SVG runtime
```

## Do not tie theme to display name

Never:

```text
if name contains Frieren -> default theme
```

Theme comes from option / explicit config only.

## Do not couple theme to personality yet

That is future work.

---

# 42. Important SVG notes

Do not use image generation to recreate the production SVG geometry.

Use the current approved SVG files as source.

Theme generation should recolor the existing vector geometry.

Verify:

```text
no embedded PNG
no base64
no <image>
no scripts
no foreignObject
no external href
```

SVGs should remain simple self-contained pixel-art vectors.

---

# 43. Concept images are references only

The generated concept images for:

```text
Default
Sapphire
Crimson
Forest
```

are **visual direction only**.

Do not attempt to reproduce:

- their exact ornate geometry
- texture
- extra decorations
- dimensions
- font
- border thickness

The production UI geometry must remain the current AVG-style implementation.

Only derive:

```text
palette mood
temperature
accent relationship
paper tint
frame color
```

from the concepts.

---

# 44. Recommended first version

The safest first release is:

```text
Global theme selection
4 palettes
same exact AVG layout
theme-specific SVG palette variants
server-rendered data attribute
no runtime theme JS
no per-character override
simple admin select
```

This has a small regression surface and creates a clean base for future expansion.

---

# 45. Future expansion — not part of this task

Possible later features:

```text
per-character theme override
custom theme registration
third-party personality theme
custom palette editor
live admin preview
automatic dark palette
seasonal themes
```

Do not implement these now.

---

# 46. Final acceptance checklist

## Option

- [ ] `dialog_theme` default exists
- [ ] allowlist validation exists
- [ ] invalid values fall back to `default`
- [ ] reset restores `default`
- [ ] other settings pages preserve the option

## Admin

- [ ] selector is under Style Settings
- [ ] 4 themes listed
- [ ] localized labels
- [ ] custom-style/no_style note shown

## Front-end

- [ ] `data-mpu-dialog-theme` rendered server-side
- [ ] no FOUC
- [ ] no new JS required
- [ ] default looks the same as current production

## SVG

- [ ] selected theme frame changes
- [ ] nameplate changes
- [ ] Cancel plate changes
- [ ] sparkle changes
- [ ] hexagram changes
- [ ] geometry unchanged
- [ ] crispEdges unchanged

## UI

- [ ] × stays top-right
- [ ] ▼ stays bottom-right
- [ ] long name protection works
- [ ] state badge protection works
- [ ] chat mode works
- [ ] gift picker works
- [ ] scrollbars work
- [ ] focus-visible works

## Compatibility

- [ ] Frieren works
- [ ] generic DIY character works
- [ ] old install without setting becomes default
- [ ] `no_style` remains supported
- [ ] custom CSS mode is not overwritten

## Tests

- [ ] PHPUnit green
- [ ] stylelint green
- [ ] project verify green
- [ ] interaction E2E green where practical
- [ ] screenshot check for all 4 palettes

---

# 47. Expected result

ユーザーは WordPress 後台から:

```text
Traveler's Grimoire
Sapphire
Crimson
Forest
```

の 4 配色を選択できる。

選択後:

```text
layout
animation
controls
dialog behavior
chat behavior
AVG interaction
```

は一切変わらず、

```text
frame palette
nameplate palette
paper palette
magic ornaments
text/link/control colors
```

だけが一貫して切り替わる。

Theme system の価値は「4 色追加」ではなく、

> 今後 palette を増やしても dialog implementation を複製しないこと

にある。
