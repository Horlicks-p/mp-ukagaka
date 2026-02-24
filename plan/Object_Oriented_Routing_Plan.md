# Object-Oriented Routing 實作計畫

此計畫旨在將 `mp-ukagaka` 目前分散於 `includes/rest/` 中的程序性 (Procedural) REST API 處理函式，重構成如同 `ai-engine` 般的**物件導向 (Object-Oriented)** 路由結構。

這將帶來以下好處：
1. **邏輯共用**：權限檢查、錯誤處理、Nonce 驗證集中於 Base Class。
2. **易於維護**：每個 Domain (如 `Chat`, `Admin`, `System`) 擁有自己的 Controller，不再全部擠在一起。
3. **擴充性高**：未來新增 Endpoint 只要在對應的 Controller 註冊即可。

---

## 🏗️ 核心架構設計

### 1. 基礎類別 `MPU_REST_Base`
作為所有 REST Controller 的母類別，負責提供通用的輔助方法。
- **檔案位置**：`includes/rest/class-mpu-rest-base.php`
- **職責**：
  - 定義 `$namespace` (`mp-ukagaka/v1`)
  - 封裝 `register_rest_route` 以簡化子類別的路由註冊
  - 提供通用的權限驗證方法 (如 `check_admin_permission()`, `check_nonce()`)
  - 統一錯誤與成功的回傳格式 (`success_response()`, `error_response()`)

### 2. 領域控制器 (Domain Controllers)
根據功能劃分 Controller，繼承自 `MPU_REST_Base`：

| 控制器類別名 | 檔案位置 | 職責與對應的舊檔案 |
| :--- | :--- | :--- |
| `MPU_REST_Chat` | `includes/rest/class-mpu-rest-chat.php` | 負責前台對話 (`/chat`)，取代 [user-chat-handler.php](file:///d:/XAMPP/htdocs/wordpress/wp-content/plugins/mp-ukagaka/includes/rest/chat/user-chat-handler.php) |
| `MPU_REST_Admin` | `includes/rest/class-mpu-rest-admin.php` | 負責後台操作 (`/fetch-manifest`, `/save-dialog`), 取代 `admin-*-handler.php` |
| `MPU_REST_System` | `includes/rest/class-mpu-rest-system.php` | 負責日誌、速率限制清除等系統操作 |
| `MPU_REST_Test` | `includes/rest/class-mpu-rest-test.php` | 負責 AI API 連線測試 (`/test-*`)，取代 [rest-test.php](file:///d:/XAMPP/htdocs/wordpress/wp-content/plugins/mp-ukagaka/includes/rest/rest-test.php) |

### 3. 入口檔案重構 `rest-core.php`
- **現狀**：逐一 `require_once` 所有 handler，並使用獨立的 function 註冊路由。
- **未來**：
  1. 載入 `MPU_REST_Base` 與各 Controller。
  2. 在 `rest_api_init` hook 中，實例化所有 Controller，並呼叫它們的 [register_routes()](file:///d:/XAMPP/htdocs/wordpress/wp-content/plugins/ai-engine/classes/rest/ai.php#4-57) 方法。

---

## 🛠️ 實作步驟 (Migration Steps)

### 第一階段：建立基礎設施 (Infrastructure)
1. 建立 `includes/rest/class-mpu-rest-base.php`。
2. 實作 `MPU_REST_Base` 類別，包含必要的 protected 共用方法。
3. 修改 `rest-core.php`，匯入這個 Base Class。

### 第二階段：遷移 Admin 與 System 路由 (低風險)
這部分不影響前台使用者，適合優先重構。
1. 建立 `class-mpu-rest-admin.php` 與 `class-mpu-rest-system.php`。
2. 將 `admin-manifest-handler.php`, `admin-dialog-handler.php`, `admin-download-handler.php` 的邏輯搬移至 `MPU_REST_Admin` 分支方法。
3. 將 `clear-log-handler.php`, `rate-limit-handler.php` 搬移至 `MPU_REST_System`。
4. 在 `rest-core.php` 中實例化這兩個 Class 並執行 [register_routes()](file:///d:/XAMPP/htdocs/wordpress/wp-content/plugins/ai-engine/classes/rest/ai.php#4-57)。
5. 刪除被取代的舊 procedural 檔案。

### 第三階段：遷移 Test 路由
1. 建立 `class-mpu-rest-test.php`。
2. 將 [rest-test.php](file:///d:/XAMPP/htdocs/wordpress/wp-content/plugins/mp-ukagaka/includes/rest/rest-test.php) 內各個 `mpu_rest_test_*` endpoint 移入類別。
   *(註：若未來打算實作 Factory Pattern，這裡的方法會進一步被簡化)*
3. 刪除 [rest-test.php](file:///d:/XAMPP/htdocs/wordpress/wp-content/plugins/mp-ukagaka/includes/rest/rest-test.php)。

### 第四階段：遷移核心的 Chat 路由 (高風險)
1. 建立 `class-mpu-rest-chat.php`。
2. 將 [user-chat-handler.php](file:///d:/XAMPP/htdocs/wordpress/wp-content/plugins/mp-ukagaka/includes/rest/chat/user-chat-handler.php) 移入 `MPU_REST_Chat::handle_chat_request`。
3. 確保 Request-Scoped State、速率限制 (Rate Limit) hook 依然正常運作。
4. 刪除 [user-chat-handler.php](file:///d:/XAMPP/htdocs/wordpress/wp-content/plugins/mp-ukagaka/includes/rest/chat/user-chat-handler.php) 與 `rest-touch.php`（如果包含在 Chat 領域內）。

---

## 🧪 驗證計畫 (Verification Strategy)

這是一次**純粹的重構**，路由 URL (`/wp-json/mp-ukagaka/v1/...`) 與 Payload 格式**絕對不能改變**。

### 測試方式
1. **後台功能測試**：
   - 進入 MP-Ukagaka 設定頁面，嘗試「刷新設定清單 (Manifest)」。
   - 點擊「清除除錯日誌」。
   - 在 AI 設定區塊，針對不同模型點擊「Test Connection」。
   - 確認是否會跳出 200 OK，這會驗證 Admin, System, Test Controller 是否正確掛載。
2. **前台對話測試**：
   - 在前端網頁打開控制台 (Network tab)。
   - 送出對話給 AI。
   - 確認 Request URL 仍然是 `.../chat`，且正確回傳回應（非 404 或 500）。
   - 驗證 Nonce refresh 功能是否如常運作。

### 防呆機制
- 在轉換每個 Controller 時，舊的檔案先保留並改名為 `*.php.bak`，確保如果發生 `class not found` 或 500 錯誤可以隨時切換回去。

---

如果這份計畫書符合你的期待，我們後續再找個假日的空檔，從**第一階段：建立基礎設施**開始動工！
