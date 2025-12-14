# Canvas 動畫功能與 CSS 自訂指南

> 🎨 說明 MP Ukagaka 的 Canvas 動畫功能實裝方式，以及如何透過 CSS 調整春菜位置

---

## 📑 目錄

1. [Canvas 動畫功能簡介](#canvas-動畫功能簡介)
2. [動畫設定方式](#動畫設定方式)
3. [技術實裝細節](#技術實裝細節)
4. [CSS 位置調整](#css-位置調整)
5. [常見問題](#常見問題)

---

## Canvas 動畫功能簡介

MP Ukagaka 從 2.1.6 版本開始支援 Canvas 動畫功能，取代原本的靜態 `<img>` 標籤。此功能可以：

- ✅ **支援單張靜態圖片**：向後兼容原有的單張圖片設定
- ✅ **支援多張圖片動畫**：自動檢測資料夾並播放幀動畫
- ✅ **僅在說話時播放動畫**：節省資源，提升效能
- ✅ **自動載入圖片序列**：無需手動指定圖片順序

### 動畫特性

- **幀間隔**：180 毫秒/幀（固定）
- **播放時機**：僅在角色說話時播放
- **支援格式**：`.png`, `.jpg`, `.jpeg`, `.gif`, `.webp`
- **圖片排序**：使用自然排序（natural sort）確保順序正確

---

## 動畫設定方式

### 單張圖片模式

在後台設定春菜時，`shell` 欄位填入**圖片檔案路徑**：

```
images/shell/character.png
```

或相對 WordPress 上傳目錄：

```
2024/12/character.png
```

### 多張圖片動畫模式

在後台設定春菜時，`shell` 欄位填入**資料夾路徑**：

```
images/shell/Frieren/
```

系統會自動：
1. 檢測該路徑是否為資料夾
2. 掃描資料夾內所有支援的圖片檔案
3. 按照檔名自然排序（例如：`frame1.png`, `frame2.png`, ..., `frame12.png`）
4. 載入所有圖片並準備播放動畫

**注意事項：**
- 資料夾路徑必須以 `/` 結尾
- 支援的圖片格式：`.png`, `.jpg`, `.jpeg`, `.gif`, `.webp`
- 圖片檔名建議使用數字序號以便正確排序

---

## 技術實裝細節

### 前端架構

#### 1. HTML 結構

```html
<div id="ukagaka_img">
    <canvas id="cur_ukagaka" 
            data-title="春菜名稱"
            data-alt="春菜名稱"
            data-shell="圖片路徑或資料夾路徑">
    </canvas>
</div>
```

#### 2. JavaScript 管理器

動畫功能由 `ukagaka-anime.js` 中的 `mpuCanvasManager` 對象管理：

```javascript
// 初始化 Canvas
window.mpuCanvasManager.init(shellInfo, name);

// shellInfo 結構：
{
    type: 'single' | 'folder',  // 單張圖片或資料夾
    url: '圖片或資料夾的 URL',
    images: ['frame1.png', 'frame2.png', ...]  // 僅在 folder 模式下有值
}
```

#### 3. 動畫播放控制

- **開始播放**：`mpuCanvasManager.playAnimation()`
- **停止播放**：`mpuCanvasManager.stopAnimation()`
- **檢查模式**：`mpuCanvasManager.isAnimationMode()`

動畫會在以下情況自動播放：
- 角色開始說話（`mpu_typewriter` 函數觸發）
- 排除系統訊息（如「思考中…」、「（えっと…何話せばいいかな…）」）

#### 4. 後端函數

PHP 函數 `mpu_get_shell_info($num)` 負責：
- 檢測 `shell` 路徑是檔案還是資料夾
- 掃描資料夾內的圖片檔案
- 返回 `shell_info` 結構給前端

---

## CSS 位置調整

### 主要 CSS 選擇器

#### 1. 春菜外殼位置（整個區塊）

```css
#ukagaka_shell {
    position: fixed;    /* 固定在頁面 */
    right: 0;          /* 靠右對齊 */
    bottom: 20px;      /* 距離底部 20px */
    margin: 0 20px 0 0; /* 右邊距 20px */
    z-index: 10000;    /* 層級 */
}
```

**調整方式：**
- `bottom`: 調整垂直位置（向上移：增大數值，向下移：減小數值）
- `right`: 調整水平位置（向左移：增大數值，向右移：減小數值）
- `margin`: 調整外邊距

#### 2. 春菜圖片容器位置

```css
#ukagaka_img {
    margin-bottom: -10px; /* 垂直偏移 */
    /* margin-left: 30px; 可以添加此屬性向右移動圖片 */
}
```

**調整方式：**
- `margin-bottom`: 調整垂直位置（負值向上，正值向下）
- `margin-left`: 向右移動圖片（新增此屬性並設定數值）
- `margin-right`: 向左移動圖片

#### 3. Canvas 元素樣式

```css
#ukagaka_img canvas {
    opacity: 0.85; /* 透明度 85% */
}
```

**調整方式：**
- `opacity`: 透明度（0.0 完全透明 ~ 1.0 完全不透明）

#### 4. 對話框位置

```css
#ukagaka_msgbox {
    position: absolute;
    top: 50%;          /* 垂直置中 */
    left: -200px;      /* 向左偏移 200px（顯示在春菜左側）*/
    transform: translateY(-50%); /* 垂直置中調整 */
}
```

**調整方式：**
- `left`: 調整對話框與春菜的距離（負值向左，正值向右）
- `top`: 調整垂直位置

#### 5. 主容器內邊距

```css
#ukagaka {
    padding-right: 40px; /* 右內邊距，為對話框留空間 */
}
```

**調整方式：**
- `padding-right`: 調整右側內邊距（影響對話框與春菜的間距）

### 實際調整範例

#### 範例 1：將春菜向右移動 20px

```css
#ukagaka_img {
    margin-left: 20px; /* 新增此行 */
}
```

#### 範例 2：調整透明度

```css
#ukagaka_img canvas {
    opacity: 0.9; /* 改為 90% */
}
```

#### 範例 3：調整垂直位置

```css
#ukagaka_img {
    margin-bottom: -20px; /* 向上移動更多 */
}
```

#### 範例 4：調整對話框與春菜的距離

```css
#ukagaka_msgbox {
    left: -180px; /* 減少距離（更靠近春菜）*/
}

/* 同時調整主容器內邊距 */
#ukagaka {
    padding-right: 30px; /* 減少內邊距 */
}
```

---

## 常見問題

### Q: 動畫沒有播放？

**A:** 請檢查：
1. 圖片是否都已載入完成（檢查瀏覽器控制台是否有錯誤）
2. 是否在角色說話時（動畫只在說話時播放）
3. 訊息是否為系統訊息（系統訊息不會觸發動畫）

### Q: 圖片順序不對？

**A:** 請確保圖片檔名使用數字序號，例如：
- ✅ `frame1.png`, `frame2.png`, ..., `frame12.png`
- ✅ `001.png`, `002.png`, ..., `012.png`
- ❌ `frame_a.png`, `frame_b.png`（字母排序可能不正確）

系統會使用自然排序（natural sort），數字會正確排序。

### Q: 如何回到使用 `<img>` 標籤？

**A:** Canvas 功能已完全取代 `<img>` 標籤，但仍支援單張圖片。如需使用單張圖片，只需在 `shell` 欄位填入圖片檔案路徑（非資料夾）。

### Q: 可以調整動畫播放速度嗎？

**A:** 目前動畫幀間隔固定為 100 毫秒/幀。如需調整，可修改 `ukagaka-anime.js` 中的 `frameInterval` 屬性：

```javascript
frameInterval: 100, // 改為其他數值（單位：毫秒）
```

### Q: CSS 修改後沒有生效？

**A:** 請檢查：
1. 是否有使用 `!important` 覆蓋（某些主題可能使用 `!important`）
2. 瀏覽器快取是否已清除
3. CSS 選擇器是否正確
4. 是否有其他 CSS 規則覆蓋了你的設定

---

## 相關檔案

- `ukagaka-anime.js` - Canvas 動畫管理器
- `mpu_style.css` - 主要樣式檔案
- `includes/ukagaka-functions.php` - `mpu_get_shell_info()` 函數
- `includes/frontend-functions.php` - HTML 生成與 Canvas 初始化
- `ukagaka-core.js` - 動畫觸發邏輯

---

## 更新記錄

- **2.1.6** (2025-12-13) - 初始實裝 Canvas 動畫功能

---

**Made with ❤ for WordPress**

