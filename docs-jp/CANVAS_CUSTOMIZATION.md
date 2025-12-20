# Canvas アニメーション機能と CSS カスタマイズガイド

> 🎨 MP Ukagaka の Canvas アニメーション機能の実装方法と、CSS で伺かの位置を調整する方法を説明します

---

## 📑 目次

1. [Canvas アニメーション機能について](#canvas-アニメーション機能について)
2. [アニメーション設定方法](#アニメーション設定方法)
3. [技術的実装詳細](#技術的実装詳細)
4. [CSS 位置調整](#css-位置調整)
5. [よくある質問](#よくある質問)

---

## Canvas アニメーション機能について

MP Ukagaka はバージョン 2.1.6 から Canvas アニメーション機能をサポートし、従来の静的な `<img>` タグを置き換えました。この機能は以下を実現します：

- ✅ **単一静止画像サポート**：従来の単一画像設定との下位互換性
- ✅ **複数画像アニメーション**：フォルダを自動検出してフレームアニメーションを再生
- ✅ **話している時のみアニメーション再生**：リソースを節約し、パフォーマンスを向上
- ✅ **画像シーケンスの自動読み込み**：手動で画像順序を指定する必要なし

### アニメーション特性

- **フレーム間隔**：180 ミリ秒/フレーム（固定）
- **再生タイミング**：キャラクターが話している時のみ再生
- **サポート形式**：`.png`, `.jpg`, `.jpeg`, `.gif`, `.webp`
- **画像ソート**：自然順序（natural sort）で正しい順序を保証

---

## アニメーション設定方法

### 単一画像モード

管理画面で伺かを設定する際、`shell` フィールドに**画像ファイルパス**を入力：

```
images/shell/character.png
```

または WordPress アップロードディレクトリ相対パス：

```
2024/12/character.png
```

### 複数画像アニメーションモード

管理画面で伺かを設定する際、`shell` フィールドに**フォルダパス**を入力：

```
images/shell/Frieren/
```

システムは自動的に：

1. パスがフォルダかどうかを検出
2. フォルダ内のサポートされている画像ファイルをスキャン
3. ファイル名で自然順序ソート（例：`frame1.png`, `frame2.png`, ..., `frame12.png`）
4. すべての画像を読み込み、アニメーション再生を準備

**注意事項：**

- フォルダパスは `/` で終わる必要があります
- サポート形式：`.png`, `.jpg`, `.jpeg`, `.gif`, `.webp`
- 正しいソートのため、ファイル名には数字の連番を使用することを推奨

---

## 技術的実装詳細

### フロントエンドアーキテクチャ

#### 1. HTML 構造

```html
<div id="ukagaka_img">
    <canvas id="cur_ukagaka" 
            data-title="伺か名"
            data-alt="伺か名"
            data-shell="画像パスまたはフォルダパス">
    </canvas>
</div>
```

#### 2. JavaScript マネージャー

アニメーション機能は `ukagaka-anime.js` の `mpuCanvasManager` オブジェクトで管理：

```javascript
// Canvas を初期化
window.mpuCanvasManager.init(shellInfo, name);

// shellInfo 構造：
{
    type: 'single' | 'folder',  // 単一画像またはフォルダ
    url: '画像またはフォルダの URL',
    images: ['frame1.png', 'frame2.png', ...]  // folder モードの場合のみ
}
```

#### 3. アニメーション再生制御

- **再生開始**：`mpuCanvasManager.playAnimation()`
- **再生停止**：`mpuCanvasManager.stopAnimation()`
- **モード確認**：`mpuCanvasManager.isAnimationMode()`

アニメーションは以下の場合に自動再生：

- キャラクターが話し始める時（`mpu_typewriter` 関数がトリガー）
- システムメッセージを除外（例：「思考中…」、「（えっと…何話せばいいかな…）」）

#### 4. バックエンド関数

PHP 関数 `mpu_get_shell_info($num)` の役割：

- `shell` パスがファイルかフォルダかを検出
- フォルダ内の画像ファイルをスキャン
- `shell_info` 構造をフロントエンドに返す

---

## CSS 位置調整

### 主要な CSS セレクター

#### 1. 伺かシェル位置（全体ブロック）

```css
#ukagaka_shell {
    position: fixed;    /* ページに固定 */
    right: 0;          /* 右揃え */
    bottom: 20px;      /* 下から 20px */
    margin: 0 20px 0 0; /* 右マージン 20px */
    z-index: 10000;    /* レイヤー */
}
```

**調整方法：**

- `bottom`: 垂直位置を調整（上へ移動：値を増加、下へ移動：値を減少）
- `right`: 水平位置を調整（左へ移動：値を増加、右へ移動：値を減少）
- `margin`: 外マージンを調整

#### 2. 伺か画像コンテナ位置

```css
#ukagaka_img {
    margin-bottom: -10px; /* 垂直オフセット */
    /* margin-left: 30px; このプロパティを追加して画像を右に移動 */
}
```

**調整方法：**

- `margin-bottom`: 垂直位置を調整（負の値で上へ、正の値で下へ）
- `margin-left`: 画像を右に移動（このプロパティを追加して値を設定）
- `margin-right`: 画像を左に移動

#### 3. Canvas 要素スタイル

```css
#ukagaka_img canvas {
    opacity: 0.85; /* 透明度 85% */
}
```

**調整方法：**

- `opacity`: 透明度（0.0 完全透明 〜 1.0 完全不透明）

#### 4. 吹き出し位置

```css
#ukagaka_msgbox {
    position: absolute;
    top: 50%;          /* 垂直中央 */
    left: -200px;      /* 左に 200px オフセット（伺かの左側に表示）*/
    transform: translateY(-50%); /* 垂直中央調整 */
}
```

**調整方法：**

- `left`: 吹き出しと伺かの距離を調整（負の値で左へ、正の値で右へ）
- `top`: 垂直位置を調整

#### 5. メインコンテナのパディング

```css
#ukagaka {
    padding-right: 40px; /* 右パディング、吹き出し用のスペース */
}
```

**調整方法：**

- `padding-right`: 右パディングを調整（吹き出しと伺かの間隔に影響）

### 実際の調整例

#### 例 1：伺かを右に 20px 移動

```css
#ukagaka_img {
    margin-left: 20px; /* この行を追加 */
}
```

#### 例 2：透明度を調整

```css
#ukagaka_img canvas {
    opacity: 0.9; /* 90% に変更 */
}
```

#### 例 3：垂直位置を調整

```css
#ukagaka_img {
    margin-bottom: -20px; /* さらに上に移動 */
}
```

#### 例 4：吹き出しと伺かの距離を調整

```css
#ukagaka_msgbox {
    left: -180px; /* 距離を減らす（伺かに近づける）*/
}

/* 同時にメインコンテナのパディングを調整 */
#ukagaka {
    padding-right: 30px; /* パディングを減らす */
}
```

---

## よくある質問

### Q: アニメーションが再生されない？

**A:** 以下を確認してください：

1. すべての画像が読み込み完了しているか（ブラウザコンソールでエラーを確認）
2. キャラクターが話している時か（アニメーションは話している時のみ再生）
3. メッセージがシステムメッセージでないか（システムメッセージはアニメーションをトリガーしない）

### Q: 画像順序が正しくない？

**A:** ファイル名に数字の連番を使用してください：

- ✅ `frame1.png`, `frame2.png`, ..., `frame12.png`
- ✅ `001.png`, `002.png`, ..., `012.png`
- ❌ `frame_a.png`, `frame_b.png`（アルファベットソートで正しくない可能性）

システムは自然順序（natural sort）を使用し、数字は正しくソートされます。

### Q: `<img>` タグに戻すには？

**A:** Canvas 機能は `<img>` タグを完全に置き換えましたが、単一画像はサポートされています。単一画像を使用するには、`shell` フィールドに画像ファイルパス（フォルダではなく）を入力してください。

### Q: アニメーション再生速度を調整できる？

**A:** 現在、アニメーションフレーム間隔は 180 ミリ秒/フレームに固定されています。調整するには、`ukagaka-anime.js` の `frameInterval` プロパティを変更：

```javascript
frameInterval: 180, // 他の値に変更（単位：ミリ秒）
```

### Q: CSS 変更が反映されない？

**A:** 以下を確認してください：

1. `!important` で上書きされていないか（一部のテーマは `!important` を使用）
2. ブラウザキャッシュをクリアしたか
3. CSS セレクターが正しいか
4. 他の CSS ルールが設定を上書きしていないか

---

## 関連ファイル

- `ukagaka-anime.js` - Canvas アニメーションマネージャー
- `mpu_style.css` - メインスタイルファイル
- `includes/ukagaka-functions.php` - `mpu_get_shell_info()` 関数
- `includes/frontend-functions.php` - HTML 生成と Canvas 初期化
- `ukagaka-core.js` - アニメーショントリガーロジック

---

## 更新履歴

- **2.1.6** (2025-12-13) - Canvas アニメーション機能の初期実装

---

**Made with ❤ for WordPress**
