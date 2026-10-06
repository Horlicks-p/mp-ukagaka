# mp-ukagaka 「旅の魔導書」Pixel-Fantasy Dialogue UI 改造計画

Repository: `Horlicks-p/mp-ukagaka`  
Target: `main`

## 1. 目的

現在の SAO 風ラスターダイアログ枠を、Frieren の pixel-art SVG shell に馴染むオリジナルの CSS UI へ置き換える。

テーマ名:

`旅の魔導書 / Traveler's Grimoire`

狙う雰囲気:

- 古い旅の魔導書
- 魔法使いのフィールドノート
- 羊皮紙
- 擦れた革
- 控えめな真鍮 / 古金
- 少量の青灰色の魔法アクセント
- crisp pixel geometry
- hard shadow
- 低彩度
- 静かな fantasy UI

避けるもの:

- SAO / cyber UI
- neon blue
- glassmorphism
- large blur
- large rounded corners
- modern mobile app card
- 派手な魔法陣
- 過剰な金装飾

公式作品 UI の模倣はせず、あくまでオリジナルの pixel-fantasy UI とする。

---

## 2. CSS を使う理由

Main dialogue は可変サイズ UI のため SVG 一枚物にはしない。

Main dialogue は以下へ対応する必要がある:

- 短い台詞
- 長い台詞
- chat mode
- streaming
- scrolling
- input
- gift picker
- 日本語 / 中文 / English

本 plugin は mobile をサポート対象にしていないため、mobile viewport / responsive mobile UI は本改造の対象外とする。

推奨分担:

```text
Character        -> SVG
Decorations      -> SVG
Dock icons       -> SVG
Think bubble     -> 現在の SVG border-image を維持
Main dialogue    -> CSS
Chat input       -> CSS
Gift picker      -> CSS
Stream badge     -> CSS
Scrollbar        -> CSS
```

---

## 3. 現在置き換える対象

現在の dialog frame は以下の raster asset に依存:

```text
images/msgbox_top.png
images/msgbox_bg.png
images/msgbox_bottom.png
```

主な selector:

```text
.ukagaka-msgbox-top
#ukagaka_msgbox
#ukagaka_msg
.ukagaka-msgbox-border
#ukagaka_chat_input
#mpu_user_input
.mpu-gift-picker-button
.mpu-gift-picker
.mpu-state-badge
```

DOM 自体は可能な限り変更せず、CSS の差し替えを優先する。

---

## 4. Suggested Palette

```css
#mp_ukagaka {
  --mpu-dialog-frame-dark: #40362f;
  --mpu-dialog-frame-mid: #756554;
  --mpu-dialog-accent: #b29a6e;
  --mpu-dialog-accent-cool: #7f929c;

  --mpu-dialog-paper: #eee7d6;
  --mpu-dialog-paper-light: #f7f1e4;
  --mpu-dialog-paper-dark: #d7ceba;

  --mpu-dialog-text: #403a34;
  --mpu-dialog-muted: #766e63;

  --mpu-dialog-shadow: rgb(28 23 19 / 22%);
  --mpu-dialog-state-accent: var(--mpu-dialog-accent-cool);
}
```

Frieren sprite と browser screenshot を見ながら調整する。

---

## 5. Pixel Frame

基本:

```text
border-radius: 0
2px dark border
2px accent ring
2px mid frame
5px hard down-right shadow
no blur
```

例:

```css
#ukagaka_msgbox {
  color: var(--mpu-dialog-text);
  background: var(--mpu-dialog-paper);

  border: 2px solid var(--mpu-dialog-frame-dark);
  border-radius: 0;

  box-shadow:
    0 0 0 2px var(--mpu-dialog-accent),
    0 0 0 4px var(--mpu-dialog-frame-mid),
    0 0 0 6px var(--mpu-dialog-frame-dark),
    5px 5px 0 var(--mpu-dialog-shadow);
}
```

この 5px hard shadow は現在の pixel-art asset の視覚言語と揃える。

---

## 6. Pixel-cut Corners

丸角は使わない。

原則として pseudo-element で 4～6px の step corner を作る。

注意:

- `#ukagaka_msgbox` 本体への `clip-path` は避ける
- `#ukagaka_msgbox.chat-mode` は `overflow: hidden` を使用しているため、本体を切ると name plate、focus outline、state badge、gift picker、hard shadow まで切れる可能性がある
- `clip-path` を使う場合は content container ではなく、背景または decorative pseudo-element のみに適用する
- CSS trick より browser stability を優先
- corners は crisp に見えることを優先
- Phase 1 では square corner の多重 border を先に完成させ、step corner は layout 安定後に追加してもよい

---

## 7. Existing DOM を活かす

現在:

```text
#ukagaka_msgbox
├── .ukagaka-msgbox-top
├── #ukagaka_msg
├── #ukagaka_chat_input
└── .ukagaka-msgbox-border
```

推奨:

- `#ukagaka_msgbox` が main frame / background を持つ
- `.ukagaka-msgbox-top` は decorative strip にする
- `.ukagaka-msgbox-border` は bottom ornament / separator にする
- `#ukagaka_msg` は scrollable content のまま
- `#ukagaka_chat_input` は panel 下部と一体化
- JS / DOM をできるだけ触らない

### Existing action controls

`.ukagaka-msgbox-border` は単なる bottom ornament ではない。現在この中に以下が存在する:

```text
#mpu_ok_btn
#mpu_cancel_btn
```

さらに gift picker が開いている間、`#mpu_ok_btn` は「gift + optional message」の送信操作として capture phase で処理される。

したがって:

- `.ukagaka-msgbox-border` を非表示にしない
- `#mpu_ok_btn` / `#mpu_cancel_btn` の ID と既存 event semantics を維持する
- normal dialogue / chat mode / gift picker open の三状態を確認する
- mockup のように send button を input 右側へ見せる場合も、DOM 上の既存 control とイベントを壊さない
- 現在の button image に付いている inline margin は、可能なら PHP markup 側から外して CSS に移す。CSS の `!important` で恒久的に上書きしない

### Character name plate

concept mockup の name plate は現在の dialogue DOM には存在しない。character name は canvas の `data-title` にのみ存在し、CSS は sibling element の attribute を参照できない。

name plate を実装する場合:

- PHP から escaped character display name を持つ `.mpu-dialog-name` を出力する
- character switch (`mpuChange`) 時にも内容を同期する
- `Frieren` / `フリーレン` を CSS の `content` に hard-code しない
- display name による theme 判定は行わない

完全 CSS-only を優先する場合は、Phase 1 では name plate を省略してよい。

---

## 8. Main Surface

構造:

```text
outer dark pixel edge
middle leather frame
thin muted-gold line
parchment center
hard 5px shadow
```

photo texture は使わない。

必要なら極めて弱い CSS gradient は許容するが、smooth web-card に見えるなら flat color に戻す。

---

## 9. Minimal Magic Ornament

魔法要素は控えめにする。

OK:

- small diamond
- small star / rune
- 1px～2px divider
- low-saturation blue-gray accent

NG:

- large rune circle
- glow
- particles
- neon
- large filigree

可能なら ornament も CSS の数 pixel の box-shadow などで表現する。

---

## 10. Typography

本文フォントは読みやすさ優先。

現在の:

```css
"Noto Sans TC", "Noto Sans JP", serif
```

を基本維持。

pixel font は本文に使わない。

```css
#ukagaka_msg {
  color: var(--mpu-dialog-text);
  line-height: 1.65;
  letter-spacing: 0.01em;
}
```

link は muted magic blue を検討。

---

## 11. Chat Input

現在の `border-radius: 16px` の pill-style を廃止。

例:

```css
#mpu_user_input {
  border: 2px solid var(--mpu-dialog-frame-mid);
  border-radius: 0;

  background: var(--mpu-dialog-paper-light);
  color: var(--mpu-dialog-text);

  box-shadow:
    inset 2px 2px 0 var(--mpu-dialog-paper-dark),
    inset -2px -2px 0 #fff9ed;
}
```

focus:

```css
#mpu_user_input:focus {
  border-color: var(--mpu-dialog-accent);

  box-shadow:
    0 0 0 2px var(--mpu-dialog-frame-dark),
    inset 2px 2px 0 var(--mpu-dialog-paper-dark);
}
```

blue glow は使わない。

---

## 12. Gift Picker

`.mpu-gift-picker` も同じ frame language に統一。

```css
.mpu-gift-picker {
  border: 2px solid var(--mpu-dialog-frame-dark);
  border-radius: 0;
  background: var(--mpu-dialog-paper);

  box-shadow:
    0 0 0 2px var(--mpu-dialog-accent),
    4px 4px 0 var(--mpu-dialog-shadow);
}
```

現代的な large rounded card / soft blur shadow は廃止。

---

## 13. Stream State

既存の:

```text
data-mpu-stream-state="thinking"
data-mpu-stream-state="streaming"
data-mpu-stream-state="status"
data-mpu-stream-state="tool"
data-mpu-stream-state="error"
data-mpu-stream-state="timeout"
data-mpu-stream-state="busy"
```

をそのまま使う。

新しい JS は不要。

状態表現は:

- tiny rune
- small divider
- small badge

程度に留める。

concept mockup では thinking / streaming / error 時に frame accent も変化している。これを採用する場合、新しい JS は追加せず、既存 attribute から CSS variable だけを差し替える:

```css
#ukagaka_msgbox[data-mpu-stream-state="thinking"] {
  --mpu-dialog-state-accent: /* sufficiently dark blue-gray */;
}
```

frame 全体を強い色にせず、thin accent ring と badge 程度に留める。

---

## 14. Stream Badge

現在の rounded pill を pixel label 化。

```css
.mpu-state-badge {
  border-radius: 0;
  border: 1px solid var(--mpu-dialog-frame-dark);
  padding: 2px 6px;

  color: var(--mpu-dialog-paper-light);
  background: var(
    --mpu-dialog-state-accent,
    var(--mpu-dialog-frame-mid)
  );

  box-shadow: 2px 2px 0 rgb(0 0 0 / 18%);
}
```

アクセシビリティ注意:

- `#f7f1e4` text on `#7f929c` background は約 `2.87:1` で、11px badge text のコントラストとして不足する
- blue-gray background を暗くするか、badge text を十分に暗い色へ変更し、通常文字として最低 `4.5:1` を確保する
- 本文の `#403a34` on `#eee7d6` は約 `9.10:1` で問題ない

---

## 15. Scrollbar

round scrollbar を square pixel style に。

```css
#ukagaka_msg::-webkit-scrollbar {
  width: 8px;
}

#ukagaka_msg::-webkit-scrollbar-track {
  background: var(--mpu-dialog-paper-dark);
  border-radius: 0;
}

#ukagaka_msg::-webkit-scrollbar-thumb {
  background: var(--mpu-dialog-frame-mid);
  border: 2px solid var(--mpu-dialog-paper-dark);
  border-radius: 0;
}
```

Firefox `scrollbar-color` も維持。

---

## 16. Think Bubble

`.mpu-think-bubble` は無理に CSS 化しない。

現状の:

```text
think-bubble.svg
think-tail.svg
border-image
```

は固定 decorative asset として適切。

必要なら palette / shadow だけ合わせる。

---

## 17. Old Raster Cleanup

CSS UI が安定した後に参照検索:

```text
msgbox_top.png
msgbox_bg.png
msgbox_bottom.png
```

参照 0 を確認してから削除。

同時に `#mp_ukagaka::after` の preload からも削除する。

先に file を消さない。

---

## 18. Generic / DIY Compatibility

- Frieren 専用 asset を generic character に要求しない
- display name で theme を分岐しない
- generic renderer を触らない
- `loadGeneration` を触らない
- shell scan を触らない

この UI を全 personality 共通の新しい default theme にするのか、明示的に選択する theme にするのかを実装前に決定する。Frieren 専用にする場合も display name では判定しない。

推奨 selector:

```text
#mp_ukagaka[data-mpu-ui-theme="grimoire"]
```

theme attribute を採用する場合:

- PHP の初期描画時に出力する
- character switch 後も正しい値へ同期する
- personality ID または明示設定を source of truth にする
- attribute がない既存/custom style 利用者の挙動を意図せず変えない

---

## 19. Responsive Requirements

本 plugin は mobile をサポート対象にしていない。本改造でも mobile layout は実装・検証しない。

desktop で最低限確認:

- normal dialogue
- chat mode
- long Japanese
- Traditional Chinese
- English
- viewport height < 600px
- browser zoom
- light theme
- dark host theme

既存の max-height / scrolling / chat flex / input anchoring は維持する。

`left: -220px` や `width: 250px` は visual comparison 前に不用意に変更しない。

---

## 20. Accessibility

維持必須:

- `:focus-visible`
- sufficient contrast
- keyboard navigation
- readable placeholder
- `prefers-reduced-motion`
- link clickability
- input usability

pixel style のために可読性を落とさない。

---

## 21. Implementation Phases

### Phase 1 — Main frame

対象:

```text
#ukagaka_msgbox
.ukagaka-msgbox-top
#ukagaka_msg
.ukagaka-msgbox-border
```

目標:

- raster frame removal
- parchment surface
- pixel frame
- current layout preserved
- existing OK / Cancel controls preserved and re-skinned

まず screenshot 比較。

### Phase 2 — Chat controls

対象:

```text
#ukagaka_chat_input
#mpu_user_input
.mpu-gift-picker-button
#mpu_ok_btn
#mpu_cancel_btn
```

name plate を採用する場合は、この phase までに PHP output と character switch 同期も実装する。

### Phase 3 — Picker / Badge / Scrollbar

対象:

```text
.mpu-gift-picker
.mpu-gift-picker-nav
.mpu-gift-picker-item
.mpu-state-badge
scrollbar
```

### Phase 4 — Cleanup

- old `msgbox_*.png` reference scan
- preload cleanup
- remove orphan raster assets if safe
- docs update
- build
- dist parity

---

## 22. Test Checklist

Visual:

- [ ] short dialogue
- [ ] long dialogue
- [ ] auto talk
- [ ] LLM streaming
- [ ] thinking
- [ ] error
- [ ] chat mode
- [ ] gift picker
- [ ] scrollbar
- [ ] Japanese
- [ ] Traditional Chinese
- [ ] English
- [ ] light host site
- [ ] dark host site

Functional:

- [ ] scrolling still works
- [ ] links clickable
- [ ] dock unaffected
- [ ] input usable
- [ ] gift picker usable
- [ ] OK button still advances / sends correctly
- [ ] Cancel button still hides / exits chat correctly
- [ ] gift picker open 時の OK button が gift + message を送る
- [ ] state badge visible
- [ ] think bubble unaffected
- [ ] Frieren animation unaffected
- [ ] generic DIY characters unaffected
- [ ] visual-ready flow unaffected
- [ ] no unnecessary JS

Accessibility:

- [ ] keyboard focus visible
- [ ] text contrast acceptable
- [ ] placeholder readable
- [ ] reduced-motion respected

---

## 23. Validation

```bash
npm --prefix tools/node run verify
```

既存の visual screenshot / comparison infrastructure を使用する:

```bash
npm --prefix tools/node run visual:baseline
npm --prefix tools/node run visual:compare
```

既存 harness は desktop `1440 x 900` で normal / chat / gift / think-system / canvas-only を扱う。本 PR では少なくとも以下を追加または手動確認する:

- thinking
- streaming
- error
- long Japanese / Traditional Chinese / English
- dark host background

mobile viewport の追加は不要。

Chrome で manual visual check も行う。

---

## 24. Visual Acceptance Criteria

以下を満たすこと:

- pixel Frieren sprite と同じ世界の UI に見える
- sci-fi より fantasy に見える
- flashy ではなく静か
- corners が crisp
- hard shadow が意図的に見える
- 日本語本文が読みやすい
- dark host page でも読める
- chat input が同じ UI object の一部に見える
- gift picker も同じ UI family に見える

---

## 25. Target Mood

```text
quiet fantasy
travel diary
old grimoire
worn parchment
muted leather
small brass details
one cool magical accent
crisp pixel geometry
hard 5px shadow
```

Not:

```text
SAO
cyber HUD
modern app
glass card
neon JRPG UI
ornate medieval manuscript
```

添付する concept mockup は visual direction 用であり、pixel-perfect 実装仕様ではない。

---

## 26. Implementation Guardrails Added After Code Review

この計画は実装可能であり、dialogue / streaming / gift の業務ロジックを作り直す必要はない。変更の中心は `css/mpu_style.css` とする。

ただし「CSS-only」を絶対条件にはしない。以下の二点は、採用する visual requirement に応じて小さな PHP / JS 変更を許容する:

1. character name plate の server-side output と character switch 同期
2. theme scope 用 `data-mpu-ui-theme` の初期出力と切り替え同期

変更してはいけない contract:

- `#ukagaka_msgbox`, `#ukagaka_msg`, `#ukagaka_chat_input`, `#mpu_user_input` の既存 ID
- `#mpu_ok_btn`, `#mpu_cancel_btn` の既存 ID と click semantics
- `data-mpu-stream-state` の既存 state names
- gift picker の keyboard / capture-phase send behavior
- chat mode の flex / scrolling behavior
- custom / DIY personality を display name で識別しないこと

old raster cleanup は必ず最後に行う。CSS、preload、その他の参照が 0 になり、visual / functional validation が完了するまで `msgbox_top.png`, `msgbox_bg.png`, `msgbox_bottom.png` を削除しない。
