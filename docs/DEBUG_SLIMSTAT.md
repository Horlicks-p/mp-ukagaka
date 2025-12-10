# Slimstat API 整合調試指南

本文件說明如何確認 Slimstat API 是否正確整合，以及 AI 是否能夠獲取訪客來源資訊。

## 快速測試（最簡單的方法）

**在瀏覽器控制台（F12）直接輸入**：

```javascript
mpu_test_visitor_info()
```

這會立即顯示完整的訪客資訊，包括 Slimstat 的調試資訊，**無需清除 Cookie 或重新整理頁面**。

## 啟用調試模式

### 方法 1：瀏覽器控制台（推薦）

1. 打開您的網站
2. 按 `F12` 打開開發者工具
3. 切換到「Console」（控制台）標籤
4. **在首次訪客打招呼觸發之前**，輸入以下命令啟用調試模式：

```javascript
window.mpuDebugMode = true
```

5. **重要**：如果已經訪問過網站，需要先清除首次訪客 Cookie：

```javascript
// 清除首次訪客 Cookie
document.cookie.split(";").forEach(c => { 
    if(c.includes("mpu_first_visit")) 
        document.cookie = c.split("=")[0] + "=;expires=Thu, 01 Jan 1970 00:00:00 UTC;path=/"; 
});
```

6. 重新整理頁面，調試資訊會自動顯示在控制台

**注意**：調試模式現在支援動態啟用，即使腳本已經載入，設置 `window.mpuDebugMode = true` 後，下次觸發首次訪客打招呼時就會顯示調試資訊。

### 方法 2：WordPress 調試模式

在 `wp-config.php` 中啟用 WordPress 調試模式：

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

這樣會在 `wp-content/debug.log` 中記錄 AI 打招呼的詳細資訊。

## 檢查項目

### 1. 檢查 Slimstat 是否被檢測到

在瀏覽器控制台中，您應該會看到類似以下的調試資訊：

```
=== 訪客資訊調試 ===
Slimstat 啟用: true
Slimstat 調試資訊: {
  class_exists: true,
  init_method_exists: true,
  get_recent_method_exists: true,
  referrers_found: 1,
  referer_extracted: "https://example.com",
  searchterms_found: 0,
  searchterms_extracted: "no_records",
  countries_found: 1,
  country_extracted: "Taiwan",
  cities_found: 1,
  city_extracted: "Taipei"
}
```

**如果 `Slimstat 啟用: false`**：
- 確認 Slimstat 插件已安裝並啟用
- 確認 Slimstat 插件版本支援 API 調用

### 2. 檢查訪客資訊是否被正確抓取

調試資訊會顯示：
- **Referrer**: 訪客來源網址
- **Referrer Host**: 來源網域
- **Search Engine**: 搜尋引擎名稱（如果有）
- **Search Query**: 搜尋關鍵字（如果有）
- **Is Direct**: 是否為直接訪問
- **Country (Slimstat)**: 國家（來自 Slimstat）
- **City (Slimstat)**: 城市（來自 Slimstat）

### 3. 檢查 AI 是否收到訪客資訊

如果啟用了 `WP_DEBUG`，在 `wp-content/debug.log` 中會看到：

```
MP Ukagaka - AI 打招呼提示詞:
  - Referrer: https://www.google.com/search?q=example
  - Referrer Host: www.google.com
  - Search Engine: google
  - Search Query: example
  - Is Direct: 否
  - Country: Taiwan
  - City: Taipei
  - User Prompt: 有訪客第一次來到網站。訪客來自搜尋引擎「google」，搜尋關鍵字是「example」。訪客來自「Taiwan」的「Taipei」。請用親切友善的語氣打招呼。
```

## 常見問題

### Q: Slimstat 啟用顯示 false

**A:** 可能的原因：
1. Slimstat 插件未安裝或未啟用
2. Slimstat 版本過舊，不支援 API
3. 需要更新 Slimstat 到最新版本

### Q: 所有 Slimstat 資訊都是 "no_records"

**A:** 可能的原因：
1. 這是訪客的第一次訪問，Slimstat 還沒有記錄
2. Slimstat 的資料庫中沒有該 IP 的歷史記錄
3. Slimstat 的地理位置功能未啟用
4. **本地開發環境**：如果是本地環境（如 `localhost`、`.local` 網域），Slimstat 可能無法獲取地理位置資訊，因為本地 IP（如 127.0.0.1）無法解析地理位置

### Q: Country 和 City 顯示「無」，但 Referrer 有抓到

**A:** 這是正常現象，可能的原因：
1. **本地環境限制**：本地開發環境（如 `wordsworth.wp.local`）的 IP 地址無法解析地理位置
2. **Slimstat 設定**：檢查 Slimstat 設定中是否啟用了地理位置追蹤功能
3. **資料庫記錄**：Slimstat 可能還沒有記錄該訪客的地理位置資訊（需要等待 Slimstat 追蹤並記錄）

**解決方案**：
- 在生產環境測試：部署到實際伺服器後，真實的訪客 IP 應該可以獲取地理位置資訊
- 檢查 Slimstat 設定：確認地理位置追蹤功能已啟用
- 等待記錄：讓 Slimstat 追蹤幾次訪問後再測試

### Q: AI 打招呼沒有提到訪客來源

**A:** 檢查：
1. 確認調試資訊中 `Referrer` 或 `Search Engine` 有值
2. 檢查 AI 的 `ai_greet_prompt` 設定是否正確
3. 查看 `debug.log` 確認傳遞給 AI 的 `User Prompt` 是否包含來源資訊

## 測試步驟

1. **清除首次訪客 Cookie**：
   - 在瀏覽器控制台輸入：`document.cookie.split(";").forEach(c => { if(c.includes("mpu_first_visit")) document.cookie = c.split("=")[0] + "=;expires=Thu, 01 Jan 1970 00:00:00 UTC;path=/"; });`

2. **啟用調試模式**：
   - 輸入：`window.mpuDebugMode = true`

3. **模擬不同來源訪問**：
   - 直接訪問：直接輸入網址
   - 搜尋引擎：從 Google 搜尋結果點擊進入
   - 外部網站：從其他網站連結進入

4. **查看調試資訊**：
   - 檢查控制台輸出
   - 檢查 AI 打招呼內容是否包含來源資訊

## 相關檔案

- `includes/ajax-handlers.php`: `mpu_ajax_get_visitor_info()` 和 `mpu_ajax_chat_greet()` 函數
- `ukagaka.js`: `mpu_greet_first_visitor()` 函數

