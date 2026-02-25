# Object-Oriented Routing 實作計畫（修訂版）

此計畫旨在將 `mp-ukagaka` 目前分散於 `includes/rest/` 中的程序性 (Procedural) REST API 處理函式，重構成物件導向 (Object-Oriented) 路由結構。

這將帶來以下好處：
1. **邏輯共用**：Rate Limiting、Permission Check、錯誤處理集中於 Base Class，消除現有的重複程式碼。
2. **易於維護**：每個 Domain (如 `Ghost`, `Chat`, `Test`) 擁有自己的 Controller。
3. **擴充性高**：未來新增 Endpoint 只要在對應的 Controller 註冊即可。

---

## 現有 REST 端點清單（實際狀態）

| 檔案 | 端點數 | 功能 |
| :--- | :---: | :--- |
| `rest-init.php` | 1 | `/init` 統一初始化 |
| `rest-core.php` | 11 | `/nextmsg`, `/extend`, `/change`, `/settings`, `/dialog`, `/visitor-info`, `/decoration-prompts`, `/wake-ghost`, `/shell-info`, `/decoration-config`, `/emoji-config` |
| `rest-touch.php` | 2 | `/touch/decoration`, `/touch/zone` |
| `rest-chat.php` | — | 路由入口，include chat/*.php |
| `rest/chat/context-handler.php` | 1 | `/chat/context` |
| `rest/chat/greet-handler.php` | 1 | `/chat/greet` |
| `rest/chat/user-chat-handler.php` | 1 | `/chat/user` |
| `rest-test.php` | 2 | `/test-connection/{provider}`, `/clear-cache` |

**共 19 個端點。**

---

## 🏗️ 核心架構設計

### 1. 基礎類別 `MPU_REST_Base`

作為所有 REST Controller 的母類別，負責提供通用的輔助方法。

- **檔案位置**：`includes/rest/class-mpu-rest-base.php`
- **職責**：
  - 定義 `$namespace` (`mp-ukagaka/v1`)
  - 封裝 `register_rest_route` 以簡化子類別的路由註冊
  - 提供共用的 Rate Limiting（現在每個 handler 都在各自呼叫 `mpu_rest_check_rate_limit()`）
  - 提供 Admin 權限驗證（現在只有 `rest-test.php` 有，邏輯應集中到這裡）
  - 統一錯誤與成功的回傳格式 `success_response()` / `error_response()`

### 2. 領域控制器 (Domain Controllers)

根據功能劃分 Controller，繼承自 `MPU_REST_Base`：

| 控制器類別名 | 檔案位置 | 職責與對應的舊檔案 |
| :--- | :--- | :--- |
| `MPU_REST_Ghost` | `includes/rest/class-mpu-rest-ghost.php` | 角色外觀、切換、設定相關：取代 `rest-init.php` 與 `rest-core.php` 中的 `/init`, `/settings`, `/change`, `/extend`, `/shell-info`, `/decoration-config`, `/emoji-config` |
| `MPU_REST_Dialog` | `includes/rest/class-mpu-rest-dialog.php` | 對話文件與自言自語：取代 `rest-core.php` 中的 `/nextmsg`, `/dialog`, `/visitor-info`, `/decoration-prompts`, `/wake-ghost` |
| `MPU_REST_Touch` | `includes/rest/class-mpu-rest-touch.php` | 觸摸互動：取代 `rest-touch.php` |
| `MPU_REST_Chat` | `includes/rest/class-mpu-rest-chat.php` | 用戶互動對話：取代 `rest-chat.php` 與 `rest/chat/*.php` |
| `MPU_REST_Test` | `includes/rest/class-mpu-rest-test.php` | Admin 連線測試與快取清除：取代 `rest-test.php` |

### 3. 入口檔案重構

- **現狀**：`mp-ukagaka.php` 逐一 `require_once` 所有 rest/*.php，各自用獨立 function 掛 `rest_api_init`。
- **未來**：載入 Base Class 與各 Controller，在 `rest_api_init` hook 中實例化所有 Controller 並呼叫 `register_routes()`。

---

## 🛠️ 實作步驟

### 第一階段：建立基礎設施（零風險）

1. 建立 `includes/rest/class-mpu-rest-base.php`，實作：
   - `protected function rate_limit($key, $max, $window)` — 包裝現有的 `mpu_rest_check_rate_limit()`
   - `protected function check_admin()` — 將 `mpu_rest_admin_permission_check()` 邏輯移入
   - `protected function ok($data, $status = 200)` — 包裝 `new WP_REST_Response()`
   - `protected function fail($code, $message, $status)` — 包裝 `new WP_Error()`
   - `abstract public function register_routes()`
2. 修改 `mp-ukagaka.php`，改為只載入 Base Class，準備接收 Controller 實例化。
3. **不刪除任何舊檔案**，舊的 procedural 檔案繼續工作。

### 第二階段：遷移 Test Controller（低風險）

`rest-test.php` 是最獨立的：只有 2 個 endpoint，只有 admin 才能呼叫，邏輯簡單。

1. 建立 `class-mpu-rest-test.php`，繼承 `MPU_REST_Base`。
2. 將 `mpu_rest_test_connection()` 與 `mpu_rest_clear_api_cache()` 移入類別方法。
3. `register_routes()` 中呼叫 `register_rest_route`，路由 URL 不變。
4. 在 `mp-ukagaka.php` 實例化並呼叫 `register_routes()`，同時**保留** `rest-test.php` 的 `add_action`（雙軌並行驗證）。
5. 驗證後台 Test Connection 正常，再刪除 `rest-test.php`。

### 第三階段：遷移 Touch Controller（低風險）

`rest-touch.php` 只有 2 個 endpoint，邏輯集中在一個檔案裡。

1. 建立 `class-mpu-rest-touch.php`，繼承 `MPU_REST_Base`。
2. 將 `/touch/decoration` 與 `/touch/zone` 的 callback 移入。
3. 同樣雙軌並行驗證後刪除 `rest-touch.php`。

### 第四階段：遷移 Chat Controller（中風險）

`rest-chat.php` 本身只是路由入口，真正的邏輯在 `rest/chat/*.php` 裡。

1. 建立 `class-mpu-rest-chat.php`，繼承 `MPU_REST_Base`。
2. 將三個 handler（`context-handler.php`, `greet-handler.php`, `user-chat-handler.php`）的函式移入類別方法。
3. 注意：`user-chat-handler.php` 包含 Rate Limit 與 MCP/Abilities 整合，是這個 Controller 中最複雜的部分，需完整測試。
4. 雙軌並行驗證後，刪除 `rest-chat.php` 與 `rest/chat/` 目錄。

### 第五階段：拆解 rest-core.php（中高風險）

`rest-core.php` 目前有 11 個 endpoint，是最大的重構目標。Domain 切割如下：

- **`MPU_REST_Ghost`**（外觀與設定，不呼叫 LLM）：
  `/init`, `/settings`, `/change`, `/extend`, `/shell-info`, `/decoration-config`, `/emoji-config`
- **`MPU_REST_Dialog`**（需要 LLM 或對話文件的端點）：
  `/nextmsg`, `/dialog`, `/visitor-info`, `/decoration-prompts`, `/wake-ghost`

建議按 Controller 逐一遷移，每次遷移後立即驗證對應功能。

---

## 附帶清理

OO 遷移完成後可一併刪除以下死碼：

- `includes/ajax/ajax-handlers.php` — 所有 handler 已遷移至 REST，且 `mp-ukagaka.php` 已不再 require 此檔
- `includes/ajax/ajax-handlers-test.php` — 同上
- `includes/ajax/ajax-touch-handlers-llm.php` — 同上
- `includes/ajax/ajax-chat-handlers-llm.php` — 同上
- `includes/ajax/chat/context-handler.php` — 同上
- `includes/ajax/chat/greet-handler.php` — 同上
- `includes/ajax/chat/user-chat-handler.php` — 同上

（`includes/ajax/chat-api-handlers.php` 仍被 `mp-ukagaka.php` require，需另行確認再處理。）

---

## 🧪 驗證策略

這是一次**純粹的重構**，路由 URL 與 Payload 格式**絕對不能改變**。

每個階段的驗證方式：

1. **雙軌並行**：新 Controller 上線時，舊的 procedural 檔案改名為 `*.bak` 備用而非直接刪除。
2. **Test Controller**：後台 → AI 設定 → Test Connection 各 provider，確認 200 OK。
3. **Chat Controller**：前端 Network tab 送出對話，確認 `/chat/user` 回傳正確。
4. **Ghost/Dialog Controllers**：頁面載入 `/init`，角色切換 `/change`，自言自語 `/nextmsg`。

---

## 建議施作順序

第一、二、三階段可以在一個下午完成，效益立竿見影（`rest-test.php` 的 `mpu_rest_admin_permission_check` 函式消失，邏輯歸入 Base Class）。

第四、五階段風險較高，建議各自獨立為一次 commit，方便 rollback。
