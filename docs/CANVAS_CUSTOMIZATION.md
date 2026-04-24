# Canvas 動畫功能與 CSS 自訂指南

> 🎨 說明 MP Ukagaka 的 Canvas 動畫功能實裝方式，以及如何透過 CSS 調整春菜位置

---

## 📑 目錄

1. [Canvas 動畫功能簡介](#canvas-動畫功能簡介)
2. [動畫設定方式](#動畫設定方式)
3. [芙莉蓮專屬配件系統](#芙莉蓮專屬配件系統)
4. [技術實裝細節](#技術實裝細節)
5. [CSS 位置調整](#css-位置調整)
6. [常見問題](#常見問題)

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

## 芙莉蓮專屬配件系統

> 🎨 芙莉蓮（`default_1`）的裝飾物已改為由 `ghost/Frieren/decorations.json` 驅動，不再是文件早期版本中的固定硬編碼三件組。

### 配件概覽

芙莉蓮角色會依 `ghost/Frieren/decorations.json` 自動載入裝飾配件：

| 配件 | 檔案名稱 | 位置 | 層級 | 用途 |
|------|---------|------|-----|------|
| 皮箱 | `suitcase.png` | 右前方 | `z-index: 10` | 芙莉蓮的旅行皮箱 |
| 巨大頭蓋骨 | `evil_horns.png` | 左後方 | `z-index: -1` | 不知道能用來幹嘛 |
| 暗黑龍的角 | `dark_dragon_horn.png` | 左前方 | 依 JSON 配置 | 芙莉蓮收藏的素材 |
| 魔導書 | `books.png` | 右後方 | 依 JSON 配置 | 芙莉蓮的魔導書堆 |
| 魔法杖 | `staff.png` | 右後方 | 依 JSON 配置 | 芙莉蓮常用法杖 |
| 藥水 | `potion.png` | 右側 | 依 JSON 配置 | 特殊互動道具 |

> 🖌️ **互動功能**：點擊任何配件，芙莉蓮會介紹該物品！

### 配件位置與尺寸

以下數值僅為目前 `ghost/Frieren/decorations.json` 的範例，實際應以 JSON 配置為準。

#### 1. 手提箱（Suitcase）

```javascript
{
    type: 'suitcase',
    src: decorationsBaseUrl + 'suitcase.png',
    top: '82%',        // 垂直位置：82% 從上方
    right: '-62px',    // 水平位置：向右外側 62px
    width: '90px',     // 寬度
    height: 'auto',    // 高度自動
    transform: 'translateY(-50%)', // 垂直置中
    zIndex: 10         // 在角色圖片前方
}
```

#### 2. 巨大頭蓋骨（Evil Horns）

```javascript
{
    type: 'evil_horns',
    src: decorationsBaseUrl + 'evil_horns.png',
    top: '42%',        // 垂直位置：42% 從上方
    left: '-65px',     // 水平位置：向左外側 65px
    width: '135px',    // 寬度
    height: 'auto',    // 高度自動
    transform: 'translateY(-50%)', // 垂直置中
    zIndex: -1         // 在角色圖片後方
}
```

#### 3. 魔法杖（Staff）

```javascript
{
    type: 'staff',
    src: decorationsBaseUrl + 'staff.png',
    top: '25%',        // 垂直位置：25% 從上方
    right: '-40px',    // 水平位置：向右外側 40px
    width: '110px',    // 寬度
    height: 'auto',    // 高度自動
    transform: 'translateY(-50%)', // 垂直置中
    zIndex: 8
}
```

#### 4. 魔導書（Books）

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

### 配件檔案路徑

配件圖片預設存放於：

```
ghost/Frieren/decorations/
├── suitcase.png
├── evil_horns.png
├── dark_dragon_horn.png
├── staff.png
├── books.png
└── potion.png
```

系統會自動從以下來源推導配件路徑：

1. **初始化資料**（優先）：`/init` 回傳的 `decorations_base_url`
2. **前端全域變數**：`window.mpuDecorationsBaseUrl`
3. **芙莉蓮管理器備援流程**：等待 `mpuInitComplete` 事件，必要時再補抓設定

### 啟用/停用配件

**方法 1：使用後台設定（推薦）**

1. 前往 **設定** → **MP Ukagaka** → **伺か管理**
2. 找到芙莉蓮角色（`default_1`）
3. 勾選或取消勾選該角色的 `show_decorations`
4. 保存設定

**方法 2：使用 CSS 隱藏特定配件（進階）**

```css
/* 隱藏所有配件 */
.frieren-decoration {
    display: none !important;
}

/* 或隱藏特定配件 */
.frieren-decoration.suitcase {
    display: none !important;
}
```

> 💡 **提示**：CSS 方法適用於需要隱藏部分配件但保留其他配件的情況。

### 配件位置調整

#### 調整皮箱位置

```css
.frieren-decoration.suitcase {
    top: 85% !important;    /* 向下移動 */
    right: -55px !important; /* 向左移動（減少負值）*/
    width: 100px !important; /* 放大 */
}
```

#### 調整惡魔角位置

```css
.frieren-decoration.evil_horns {
    top: 40% !important;    /* 向上移動 */
    left: -70px !important; /* 向左移動（增加負值）*/
    width: 120px !important; /* 縮小 */
}
```

#### 調整魔導書位置

```css
.frieren-decoration.books {
    top: 40% !important;
    right: -50px !important;
    opacity: 0.8;           /* 調整透明度 */
}
```

### 技術實作細節

#### 配件載入流程

1. **檢查角色**：`mpuCanvasManager.isFrieren(num, name)`
   - 優先檢查 `num === 'default_1'`
   - 也會檢查名稱是否包含 `フリーレン` 或 `Frieren`

2. **初始化芙莉蓮模式**：`initFrierenMode()`
   - 設定容器為相對定位
   - 調用 `loadFrierenDecorations()`

3. **載入配件**：`loadFrierenDecorations()`
   - 優先使用 `/init` 回傳後寫入的 `window.mpuDecorationConfig`、`window.mpuDecorationsBaseUrl`
   - 檢查 `window.mpuShowDecorations` 是否啟用
   - 依 `decorations.json` 的 `items` 逐一建立元素

4. **添加配件**：`addFrierenDecoration(config)`
   - 創建 `<img>` 元素
   - 設置 `frieren-decoration` CSS 類別
   - 應用絕對定位樣式
   - 附加到 `#ukagaka_img` 容器

#### 配件 HTML 結構

```html
<div id="ukagaka_img" style="position: relative;">
    <!-- 春菜 Canvas 或 img -->
    <canvas id="cur_ukagaka">...</canvas>
    
    <!-- 配件元素（依 decorations.json 自動添加）-->
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
// 檢查是否為芙莉蓮
mpuCanvasManager.isFrieren(num, name);

// 手動添加配件（進階用法）
mpuCanvasManager.addFrierenDecoration({
    type: 'custom_accessory',
    src: 'path/to/accessory.png',
    top: '50%',
    right: '-40px',
    width: '80px',
    zIndex: 5
});

// 移除特定配件
mpuCanvasManager.removeFrierenDecoration('suitcase');

// 清除所有配件
mpuCanvasManager.clearFrierenDecorations();
```

### 自訂配件範例

#### 添加新配件

```javascript
// 建議新增到 ghost/Frieren/decorations.json，而不是直接硬改 JS
mpuCanvasManager.addFrierenDecoration({
    type: 'custom_hat',
    src: decorationsBaseUrl + 'custom_hat.png',
    top: '10%',
    left: '50%',
    width: '60px',
    transform: 'translateX(-50%)', // 水平置中
    zIndex: 20
});
```

#### 動態控制配件

```javascript
// 在特定事件時添加配件
document.addEventListener('某個事件', function() {
    if (mpuCanvasManager.isFrierenMode) {
        mpuCanvasManager.addFrierenDecoration({
            type: 'special_effect',
            src: '/path/to/effect.png',
            top: '30%',
            left: '30%',
            width: '50px',
            zIndex: 15
        });
        
        // 5秒後移除
        setTimeout(function() {
            mpuCanvasManager.removeFrierenDecoration('special_effect');
        }, 5000);
    }
});
```

### 配件相容性

- ✅ **支援角色切換**：切換到其他角色時，配件會自動清除
- ✅ **支援響應式**：配件使用百分比定位，自動適應不同螢幕尺寸
- ✅ **支援透明度**：可透過 CSS `opacity` 調整配件透明度
- ✅ **支援 z-index**：可自由調整配件層級（前景/背景）

### 常見自訂需求

#### 1. 隱藏皮箱，保留其他配件

```css
.frieren-decoration.suitcase {
    display: none !important;
}
```

#### 2. 讓所有配件都在背景

```css
.frieren-decoration {
    z-index: -1 !important;
}
```

#### 3. 調整所有配件的透明度

```css
.frieren-decoration {
    opacity: 0.7 !important;
}
```

#### 4. 在小螢幕隱藏配件

```css
@media (max-width: 768px) {
    .frieren-decoration {
        display: none !important;
    }
}
```

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

PHP 函數 `mpu_get_shell_info($num)` 目前位於 `includes/core/ukagaka-functions.php`，負責：
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

**A:** 目前動畫幀間隔固定為 180 毫秒/幀。如需調整，可修改 `ukagaka-anime.js` 中的 `frameInterval` 屬性：

```javascript
frameInterval: 180, // 改為其他數值（單位：毫秒）
```

### Q: CSS 修改後沒有生效？

**A:** 請檢查：
1. 是否有使用 `!important` 覆蓋（某些主題可能使用 `!important`）
2. 瀏覽器快取是否已清除
3. CSS 選擇器是否正確
4. 是否有其他 CSS 規則覆蓋了你的設定

---

## 相關檔案

- `js/ukagaka-anime.js` - Canvas 動畫管理器
- `mpu_style.css` - 主要樣式檔案
- `ghost/Frieren/frieren.js` - 芙莉蓮專屬裝飾物與互動邏輯
- `ghost/Frieren/decorations.json` - 裝飾物配置
- `includes/core/ukagaka-functions.php` - `mpu_get_shell_info()` 函數
- `includes/core/frontend-functions.php` - HTML 生成與初始化資料注入
- `js/ukagaka-core.js` - 動畫觸發邏輯

---

## 更新記錄

- **2.1.6** - 初始實裝 Canvas 動畫功能
- **2.12.x+** - 芙莉蓮裝飾物改為由 `ghost/Frieren/decorations.json` 驅動

---

**Made with ❤ for WordPress**
