# Slimstat 整合調試指南

本文件說明如何確認 Slimstat 資料是否正確整合到 MP Ukagaka，以及頁面感知 / 首訪打招呼是否能獲取訪客來源資訊。

## 啟用調試模式

### 方法 1：瀏覽器控制台（推薦）

1. 打開您的網站
2. 按 `F12` 打開開發者工具
3. 切換到「Console」（控制台）標籤
4. 輸入以下命令啟用調試模式：

```javascript
window.mpuDebugMode = true
```

5. 重新整理頁面（或清除首次訪客 Cookie 後重新訪問）

### 方法 2：WordPress 調試模式

在 `wp-config.php` 中啟用 WordPress 調試模式：

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

這樣會讓 PHP 端錯誤寫入 `wp-content/debug.log`；前端細節則仍以瀏覽器 Console 為主。

## 檢查項目

### 1. 檢查 Slimstat 是否被檢測到

在瀏覽器控制台中，現行前端通常會輸出類似以下資訊：

```
[MP Ukagaka] 訪問者情報： {
  referrer: "https://example.com",
  referrer_host: "example.com",
  search_engine: "google",
  country: "TW",
  city: "Taipei"
}
```

實際欄位來源為 `/wp-json/mp-ukagaka/v1/visitor-info`，後端實作位於 `includes/rest/class-mpu-rest-dialog.php`。

**如果 `slimstat_enabled` 為 false**：
- 確認 Slimstat 插件已安裝並啟用
- 確認資料表存在且外掛可讀取 Slimstat 紀錄

### 2. 檢查訪客資訊是否被正確抓取

目前前端會使用或顯示以下資訊：
- **Referrer**: 訪客來源網址
- **Referrer Host**: 來源網域
- **Search Engine**: 搜尋引擎名稱（如果有）
- **Is Direct**: 是否為直接訪問
- **Country (Slimstat)**: 國家（來自 Slimstat）
- **City (Slimstat)**: 城市（來自 Slimstat）

### 3. 檢查 AI 是否收到訪客資訊

目前首訪打招呼流程為：

1. 前端先呼叫 `GET /visitor-info`
2. 再將整理後的資料送到 `POST /chat/greet`

相關程式位置：

- `js/ukagaka-greeting.js`
- `includes/rest/class-mpu-rest-dialog.php`
- `includes/rest/class-mpu-rest-chat.php`

如果啟用 `window.mpuDebugMode = true`，可在 Console 中確認是否有成功記錄訪客資訊與後續流程。實際頁面會由後端依 `WP_DEBUG && current_user_can('manage_options')` 注入 `window.mpuDebugMode`；手動在 Console 設為 `true` 仍可開啟前端 debug 輸出，但非管理員頁面不會注入 `mpuL10n.logsDebug`，debug log 會使用日文 fallback。

如果啟用了 `WP_DEBUG` / `WP_DEBUG_LOG`，則可在 `wp-content/debug.log` 觀察是否有 PHP 端錯誤；但文件早期版本那種固定格式的完整 greet prompt 輸出，不再保證一定存在。

可自行在瀏覽器 Network 面板檢查：

```
GET  /wp-json/mp-ukagaka/v1/visitor-info
POST /wp-json/mp-ukagaka/v1/chat/greet
```

## 常見問題

### Q: `slimstat_enabled` 顯示 false

**A:** 可能的原因：
1. Slimstat 插件未安裝或未啟用
2. Slimstat 資料表不存在或目前站點尚未產生可讀取紀錄
3. 環境限制導致無法取得對應訪客記錄

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
1. 確認 `/visitor-info` 回應中 `referrer` 或 `search_engine` 有值
2. 檢查 AI 的 `ai_greet_prompt` 設定是否正確
3. 用瀏覽器 Network 面板檢查 `/chat/greet` 的 request payload 是否包含 `referrer`、`referrer_host`、`search_engine`、`country`、`city`

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
   - 檢查 Console 的 `訪問者情報` log
   - 檢查 Network 面板中的 `/visitor-info` 與 `/chat/greet`
   - 檢查 AI 打招呼內容是否包含來源資訊

## 相關檔案

- `includes/rest/class-mpu-rest-dialog.php`: `/visitor-info`
- `includes/rest/class-mpu-rest-chat.php`: `/chat/greet`
- `js/ukagaka-greeting.js`: `mpu_greet_first_visitor()` 函數
- `js/ukagaka-context.js`: 頁面感知 AI 也會讀取 `visitor-info`
