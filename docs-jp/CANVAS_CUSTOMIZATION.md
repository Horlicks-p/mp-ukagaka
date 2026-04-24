# Canvas アニメーション機能と CSS カスタマイズガイド

> 🎨 MP Ukagaka の Canvas アニメーション機能の実装方法、および CSS を使用して伺かの位置を調整する方法について説明します。

---

## 📑 目次

1. [Canvas アニメーション機能の概要](#canvas-アニメーション機能の概要)
2. [アニメーション設定方法](#アニメーション設定方法)
3. [フリーレン専用装飾品システム](#フリーレン専用装飾品システム)
4. [技術的な実装の詳細](#技術的な実装の詳細)
5. [CSS による位置調整](#css-による位置調整)
6. [よくある質問](#よくある質問)

---

## Canvas アニメーション機能の概要

MP Ukagaka はバージョン 2.1.6 から、従来の静的な `<img>` タグに代わり、Canvas アニメーション機能をサポートしました。この機能により以下が可能になります：

- ✅ **単一の静止画像をサポート**：従来の単一画像設定との下位互換性
- ✅ **複数画像によるアニメーションをサポート**：フォルダを自動検出し、フレームアニメーションを再生
- ✅ **発話時のみアニメーションを再生**：リソースを節約し、パフォーマンスを向上
- ✅ **画像シーケンスの自動読み込み**：画像の順序を手動で指定する必要なし

### アニメーションの特性

- **フレーム間隔**：180 ミリ秒/フレーム（固定）
- **再生タイミング**：キャラクターが話している時のみ再生
- **サポートされている形式**：`.png`, `.jpg`, `.jpeg`, `.gif`, `.webp`
- **画像のソート**：正しい順序を確保するため、自然順（natural sort）を使用

---

## アニメーション設定方法

### 単一画像モード

管理画面で伺かを設定する際、`shell` フィールドに **画像ファイルのパス** を入力します：

```
images/shell/character.png
```

または、WordPress のアップロードディレクトリからの相対パス：

```
2024/12/character.png
```

### 複数画像アニメーションモード

管理画面で伺かを設定する際、`shell` フィールドに **フォルダのパス** を入力します：

```
images/shell/Frieren/
```

システムは自動的に以下を行います：
1. パスがフォルダであるかどうかを検出します。
2. フォルダ内のサポートされているすべての画像ファイルをスキャンします。
3. ファイル名を自然順にソートします（例：`frame1.png`, `frame2.png`, ..., `frame12.png`）。
4. すべての画像を読み込み、アニメーション再生の準備をします。

**注意事項：**
- フォルダパスは必ず `/` で終わる必要があります。
- サポートされている画像形式：`.png`, `.jpg`, `.jpeg`, `.gif`, `.webp`。
- 正しい順序でソートされるように、画像のファイル名には連番の数字を使用することをお勧めします。

---

## フリーレン専用装飾品システム

> 🎨 フリーレン（`default_1`）の装飾品は、以前のバージョンのように固定でハードコーディングされたものではなく、`ghost/Frieren/decorations.json` によって駆動されるようになりました。

### 装飾品の概要

フリーレンは、`ghost/Frieren/decorations.json` に基づいて装飾品を自動的に読み込みます：

| 装飾品 | ファイル名 | 位置 | Z-Index | 用途 |
|------|---------|------|-----|------|
| スーツケース | `suitcase.png` | 右手前 | `z-index: 10` | フリーレンの旅行用スーツケース |
| 巨大な頭蓋骨 | `evil_horns.png` | 左奥 | `z-index: -1` | 何に使うのかわからない |
| 暗黒竜の角 | `dark_dragon_horn.png` | 左手前 | JSON に準拠 | フリーレンが収集した素材 |
| 魔導書 | `books.png` | 右奥 | JSON に準拠 | フリーレンの魔導書の山 |
| 杖 | `staff.png` | 右奥 | JSON に準拠 | フリーレンの愛用の杖 |
| ポーション | `potion.png` | 右側 | JSON に準拠 | 特別なインタラクションアイテム |

> 🖌️ **インタラクティブ機能**：装飾品をクリックすると、フリーレンがそのアイテムについて説明してくれます！

### 装飾品の位置とサイズ

以下の数値は、現在の `ghost/Frieren/decorations.json` の一例にすぎません。実際の設定は JSON ファイルを参照してください。

#### 1. スーツケース (Suitcase)

```javascript
{
    type: 'suitcase',
    src: decorationsBaseUrl + 'suitcase.png',
    top: '82%',        // 垂直位置：上から 82%
    right: '-62px',    // 水平位置：右外側へ 62px
    width: '90px',     // 幅
    height: 'auto',    // 高さ（自動）
    transform: 'translateY(-50%)', // 垂直方向の中央揃え
    zIndex: 10         // キャラクター画像の手前
}
```

#### 2. 巨大な頭蓋骨 (Evil Horns)

```javascript
{
    type: 'evil_horns',
    src: decorationsBaseUrl + 'evil_horns.png',
    top: '42%',        // 垂直位置：上から 42%
    left: '-65px',     // 水平位置：左外側へ 65px
    width: '135px',    // 幅
    height: 'auto',    // 高さ（自動）
    transform: 'translateY(-50%)', // 垂直方向の中央揃え
    zIndex: -1         // キャラクター画像の奥
}
```

#### 3. 杖 (Staff)

```javascript
{
    type: 'staff',
    src: decorationsBaseUrl + 'staff.png',
    top: '25%',        // 垂直位置：上から 25%
    right: '-40px',    // 水平位置：右外側へ 40px
    width: '110px',    // 幅
    height: 'auto',    // 高さ（自動）
    transform: 'translateY(-50%)', // 垂直方向の中央揃え
    zIndex: 8
}
```

#### 4. 魔導書 (Books)

```javascript
{
    type: 'books',
    src: decorationsBaseUrl + 'books.png',
    top: '38%',
    right: '-58px',
    width: '115px',
    height: 'auto',
    transform: 'translateY(-50%)',
    zIndex: 6
}
```

### 装飾品のファイルパス

装飾品の画像はデフォルトで以下の場所に保存されます：

```
ghost/Frieren/decorations/
├── suitcase.png
├── evil_horns.png
├── dark_dragon_horn.png
├── staff.png
├── books.png
└── potion.png
```

システムは以下のソースから装飾品のパスを自動的に導き出します：

1. **初期化データ**（優先）：`/init` から返される `decorations_base_url`
2. **フロントエンドグローバル変数**：`window.mpuDecorationsBaseUrl`
3. **フリーレンマネージャーのフォールバック**：`mpuInitComplete` イベントを待機し、必要に応じて設定を再取得します

### 装飾品の有効化/無効化

**方法 1：管理画面設定を使用する（推奨）**

1. **設定** → **MP Ukagaka** → **伺か管理** に移動します。
2. フリーレン（`default_1`）のキャラクターを見つけます。
3. そのキャラクターの `show_decorations` にチェックを入れる、またはチェックを外します。
4. 設定を保存します。

**方法 2：CSS を使用して特定の装飾品を非表示にする（高度）**

```css
/* すべての装飾品を非表示にする */
.frieren-decoration {
    display: none !important;
}

/* または特定の装飾品を非表示にする */
.frieren-decoration.suitcase {
    display: none !important;
}
```

> 💡 **ヒント**：一部の装飾品を非表示にして、他の装飾品を残したい場合に CSS の方法が適しています。

### 装飾品の位置調整

#### スーツケースの位置調整

```css
.frieren-decoration.suitcase {
    top: 85% !important;    /* 下へ移動 */
    right: -55px !important; /* 左へ移動（負の値を減らす）*/
    width: 100px !important; /* 拡大 */
}
```

#### 巨大な頭蓋骨の位置調整

```css
.frieren-decoration.evil_horns {
    top: 40% !important;    /* 上へ移動 */
    left: -70px !important; /* 左へ移動（負の値を増やす）*/
    width: 120px !important; /* 縮小 */
}
```

#### 魔導書の位置調整

```css
.frieren-decoration.books {
    top: 40% !important;
    right: -50px !important;
    opacity: 0.8;           /* 透明度を調整 */
}
```

### 技術的な実装の詳細

#### 装飾品の読み込みフロー

1. **キャラクターのチェック**：`mpuCanvasManager.isFrieren(num, name)`
   - `num === 'default_1'` であるかを優先して確認します。
   - 名前の中に `フリーレン` または `Frieren` が含まれているかも確認します。

2. **フリーレンモードの初期化**：`initFrierenMode()`
   - コンテナを相対位置（relative positioning）に設定します。
   - `loadFrierenDecorations()` を呼び出します。

3. **装飾品の読み込み**：`loadFrierenDecorations()`
   - `/init` によって返され、書き込まれた `window.mpuDecorationConfig` および `window.mpuDecorationsBaseUrl` を優先して使用します。
   - `window.mpuShowDecorations` が有効になっているかを確認します。
   - `decorations.json` の `items` に基づいて、要素を1つずつ作成します。

4. **装飾品の追加**：`addFrierenDecoration(config)`
   - `<img>` 要素を作成します。
   - `frieren-decoration` の CSS クラスを設定します。
   - 絶対位置（absolute positioning）のスタイルを適用します。
   - `#ukagaka_img` コンテナに追加します。

#### 装飾品の HTML 構造

```html
<div id="ukagaka_img" style="position: relative;">
    <!-- キャラクターの Canvas または img -->
    <canvas id="cur_ukagaka">...</canvas>
    
    <!-- 装飾品要素（decorations.json に基づいて自動的に追加されます）-->
    <img class="frieren-decoration suitcase" 
         src=".../ghost/Frieren/decorations/suitcase.png"
         style="position: absolute; top: 82%; right: -62px; ...">
    
    <img class="frieren-decoration evil_horns" 
         src=".../ghost/Frieren/decorations/evil_horns.png"
         style="position: absolute; top: 42%; left: -65px; ...">
    
    <img class="frieren-decoration books" 
         src=".../ghost/Frieren/decorations/books.png"
         style="position: absolute; top: 38%; right: -58px; ...">
</div>
```

#### JavaScript API

```javascript
// フリーレンであるかを確認する
mpuCanvasManager.isFrieren(num, name);

// 装飾品を手動で追加する（高度な使用法）
mpuCanvasManager.addFrierenDecoration({
    type: 'custom_accessory',
    src: 'path/to/accessory.png',
    top: '50%',
    right: '-40px',
    width: '80px',
    zIndex: 5
});

// 特定の装飾品を削除する
mpuCanvasManager.removeFrierenDecoration('suitcase');

// すべての装飾品をクリアする
mpuCanvasManager.clearFrierenDecorations();
```

### カスタム装飾品の例

#### 新しい装飾品の追加

```javascript
// JS に直接ハードコーディングするのではなく、ghost/Frieren/decorations.json に追加することをお勧めします
mpuCanvasManager.addFrierenDecoration({
    type: 'custom_hat',
    src: decorationsBaseUrl + 'custom_hat.png',
    top: '10%',
    left: '50%',
    width: '60px',
    transform: 'translateX(-50%)', // 水平方向の中央揃え
    zIndex: 20
});
```

#### 装飾品の動的な制御

```javascript
// 特定のイベント発生時に装飾品を追加する
document.addEventListener('some_event', function() {
    if (mpuCanvasManager.isFrierenMode) {
        mpuCanvasManager.addFrierenDecoration({
            type: 'special_effect',
            src: '/path/to/effect.png',
            top: '30%',
            left: '30%',
            width: '50px',
            zIndex: 15
        });
        
        // 5秒後に削除
        setTimeout(function() {
            mpuCanvasManager.removeFrierenDecoration('special_effect');
        }, 5000);
    }
});
```

### 装飾品の互換性

- ✅ **キャラクターの切り替えをサポート**：他のキャラクターに切り替えると、装飾品は自動的にクリアされます。
- ✅ **レスポンシブデザインをサポート**：装飾品はパーセンテージによる配置を使用するため、異なる画面サイズに自動的に適応します。
- ✅ **透明度をサポート**：CSS の `opacity` を通じて装飾品の透明度を調整できます。
- ✅ **Z-Index をサポート**：装飾品のレイヤー（前面/背面）を自由に調整できます。

### よくあるカスタマイズの要望

#### 1. スーツケースを隠して、他は残す

```css
.frieren-decoration.suitcase {
    display: none !important;
}
```

#### 2. すべての装飾品を背景に置く

```css
.frieren-decoration {
    z-index: -1 !important;
}
```

#### 3. すべての装飾品の透明度を調整する

```css
.frieren-decoration {
    opacity: 0.7 !important;
}
```

#### 4. 小さな画面で装飾品を隠す

```css
@media (max-width: 768px) {
    .frieren-decoration {
        display: none !important;
    }
}
```

---

## 技術的な実装の詳細

### フロントエンドアーキテクチャ

#### 1. HTML 構造

```html
<div id="ukagaka_img">
    <canvas id="cur_ukagaka" 
            data-title="伺かの名前"
            data-alt="伺かの名前"
            data-shell="画像パスまたはフォルダパス">
    </canvas>
</div>
```

#### 2. JavaScript マネージャー

アニメーション機能は、`ukagaka-anime.js` 内の `mpuCanvasManager` オブジェクトによって管理されます：

```javascript
// Canvas の初期化
window.mpuCanvasManager.init(shellInfo, name);

// shellInfo 構造：
{
    type: 'single' | 'folder',  // 単一の画像またはフォルダ
    url: '画像またはフォルダの URL',
    images: ['frame1.png', 'frame2.png', ...]  // folder モードの場合のみ値が存在
}
```

#### 3. アニメーションの再生制御

- **再生開始**：`mpuCanvasManager.playAnimation()`
- **再生停止**：`mpuCanvasManager.stopAnimation()`
- **モードの確認**：`mpuCanvasManager.isAnimationMode()`

アニメーションは以下の場合に自動的に再生されます：
- キャラクターが話し始めた時（`mpu_typewriter` 関数によってトリガー）。
- システムメッセージ（「思考中…」、「（えっと…何話せばいいかな…）」など）を除外した場合。

#### 4. バックエンド関数

PHP 関数 `mpu_get_shell_info($num)` は現在 `includes/core/ukagaka-functions.php` に配置されており、以下の役割を担います：
- `shell` パスがファイルかフォルダかを検出します。
- フォルダ内の画像ファイルをスキャンします。
- `shell_info` 構造をフロントエンドに返します。

---

## CSS による位置調整

### 主な CSS セレクタ

#### 1. 伺かの外観位置（全体ブロック）

```css
#ukagaka_shell {
    position: fixed;    /* 画面に固定 */
    right: 0;          /* 右揃え */
    bottom: 20px;      /* 下から 20px */
    margin: 0 20px 0 0; /* 右マージン 20px */
    z-index: 10000;    /* レイヤー順位 */
}
```

**調整方法：**
- `bottom`: 垂直位置を調整します（増やすと上に移動、減らすと下に移動）。
- `right`: 水平位置を調整します（増やすと左に移動、減らすと右に移動）。
- `margin`: 外側の余白（マージン）を調整します。

#### 2. 伺か画像コンテナの位置

```css
#ukagaka_img {
    margin-bottom: -10px; /* 垂直オフセット */
    /* margin-left: 30px; この属性を追加して画像を右に移動できます */
}
```

**調整方法：**
- `margin-bottom`: 垂直位置を調整します（負の値で上へ、正の値で下へ移動）。
- `margin-left`: 画像を右へ移動します（この属性を追加して数値を設定します）。
- `margin-right`: 画像を左へ移動します。

#### 3. Canvas 要素のスタイル

```css
#ukagaka_img canvas {
    opacity: 0.85; /* 透明度 85% */
}
```

**調整方法：**
- `opacity`: 透明度（0.0 完全な透明 〜 1.0 完全な不透明）。

#### 4. ダイアログボックスの位置

```css
#ukagaka_msgbox {
    position: absolute;
    top: 50%;          /* 垂直方向の中央揃え */
    left: -200px;      /* 左へ 200px オフセット（伺かの左側に表示）*/
    transform: translateY(-50%); /* 垂直方向の中央揃えの調整 */
}
```

**調整方法：**
- `left`: ダイアログボックスと伺かの距離を調整します（負の値で左へ、正の値で右へ移動）。
- `top`: 垂直位置を調整します。

#### 5. メインコンテナの余白（パディング）

```css
#ukagaka {
    padding-right: 40px; /* 右側の内側の余白、ダイアログボックスのスペースを確保 */
}
```

**調整方法：**
- `padding-right`: 右側のパディングを調整します（ダイアログボックスと伺かの間隔に影響します）。

### 実際の調整例

#### 例 1：伺かを右へ 20px 移動する

```css
#ukagaka_img {
    margin-left: 20px; /* この行を追加 */
}
```

#### 例 2：透明度を調整する

```css
#ukagaka_img canvas {
    opacity: 0.9; /* 90% に変更 */
}
```

#### 例 3：垂直位置を調整する

```css
#ukagaka_img {
    margin-bottom: -20px; /* さらに上へ移動 */
}
```

#### 例 4：ダイアログボックスと伺かの距離を調整する

```css
#ukagaka_msgbox {
    left: -180px; /* 距離を減らす（伺かに近づける）*/
}

/* メインコンテナのパディングも同時に調整 */
#ukagaka {
    padding-right: 30px; /* パディングを減らす */
}
```

---

## よくある質問

### Q: アニメーションが再生されませんか？

**A:** 以下を確認してください：
1. 画像がすべて読み込まれているか（ブラウザのコンソールにエラーがないか確認します）。
2. キャラクターが話している時かどうか（アニメーションは話している時のみ再生されます）。
3. メッセージがシステムメッセージではないか（システムメッセージではアニメーションはトリガーされません）。

### Q: 画像の順序が間違っていませんか？

**A:** 画像のファイル名に連番の数字が使用されていることを確認してください。例：
- ✅ `frame1.png`, `frame2.png`, ..., `frame12.png`
- ✅ `001.png`, `002.png`, ..., `012.png`
- ❌ `frame_a.png`, `frame_b.png`（アルファベット順のソートでは正しくならない場合があります）

システムは自然順（natural sort）を使用するため、数字は正しくソートされます。

### Q: `<img>` タグの使用に戻すにはどうすればよいですか？

**A:** Canvas 機能は `<img>` タグを完全に置き換えましたが、単一画像も引き続きサポートしています。単一画像を使用する必要がある場合は、`shell` フィールドに画像ファイルのパス（フォルダではない）を入力するだけです。

### Q: アニメーションの再生速度は調整できますか？

**A:** 現在、アニメーションのフレーム間隔は 180 ミリ秒/フレームで固定されています。調整する必要がある場合は、`ukagaka-anime.js` 内の `frameInterval` 属性を変更できます：

```javascript
frameInterval: 180, // 他の数値に変更します（単位：ミリ秒）
```

### Q: CSS の変更が反映されませんか？

**A:** 以下を確認してください：
1. `!important` で上書きされていないか（一部のテーマでは `!important` が使用されている場合があります）。
2. ブラウザのキャッシュがクリアされているか。
3. CSS セレクタが正しいか。
4. 他の CSS ルールがあなたの設定を上書きしていないか。

---

## 関連ファイル

- `js/ukagaka-anime.js` - Canvas アニメーションマネージャー
- `mpu_style.css` - メインのスタイルシートファイル
- `ghost/Frieren/frieren.js` - フリーレン専用の装飾品とインタラクションロジック
- `ghost/Frieren/decorations.json` - 装飾品の設定
- `includes/core/ukagaka-functions.php` - `mpu_get_shell_info()` 関数
- `includes/core/frontend-functions.php` - HTML の生成と初期化データの注入
- `js/ukagaka-core.js` - アニメーションのトリガーロジック

---

## 更新履歴

- **2.1.6** - Canvas アニメーション機能を初期実装
- **2.12.x+** - フリーレンの装飾品が `ghost/Frieren/decorations.json` によって駆動されるように変更

---

**Made with ❤ for WordPress**
