# Object-Oriented Routing 實作計畫（修訂版 + Review 註記）

此計畫旨在將 `mp-ukagaka` 目前分散於 `includes/rest/` 中的程序性 (Procedural) REST API 處理函式，重構成物件導向 (Object-Oriented) 路由結構。

這將帶來以下好處：
1. **邏輯共用**：Rate Limiting、Permission Check、錯誤處理可集中於 Base Class，減少重複程式碼。
2. **易於維護**：每個 Domain（如 `Ghost`, `Chat`, `Test`）擁有自己的 Controller。
3. **擴充性高**：未來新增 Endpoint 只要在對應 Controller 註冊即可。

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
  - 定義 `$namespace`（`mp-ukagaka/v1`）
  - 封裝 `register_rest_route` 以簡化子類別的路由註冊
  - 提供共用 Rate Limiting 包裝（底層仍可呼叫 `mpu_rest_check_rate_limit()`）
  - 提供共用 Admin 權限驗證方法（供新 Controller 使用）
  - 統一常見回傳格式輔助方法 `ok()` / `fail()`

### 2. 領域控制器 (Domain Controllers)

根據功能劃分 Controller，繼承自 `MPU_REST_Base`：

| 控制器類別名 | 檔案位置 | 職責與對應的舊檔案 |
| :--- | :--- | :--- |
| `MPU_REST_Ghost` | `includes/rest/class-mpu-rest-ghost.php` | 角色外觀、切換、設定相關：取代 `rest-init.php` 與 `rest-core.php` 中的 `/init`, `/settings`, `/change`, `/extend`, `/shell-info`, `/decoration-config`, `/emoji-config` |
| `MPU_REST_Dialog` | `includes/rest/class-mpu-rest-dialog.php` | 對話文件與自言自語：取代 `rest-core.php` 中的 `/nextmsg`, `/dialog`, `/visitor-info`, `/decoration-prompts`, `/wake-ghost` |
| `MPU_REST_Touch` | `includes/rest/class-mpu-rest-touch.php` | 觸摸互動：取代 `rest-touch.php` |
| `MPU_REST_Chat` | `includes/rest/class-mpu-rest-chat.php` | 用戶互動對話：取代 `rest-chat.php` 與 `rest/chat/*.php` |
| `MPU_REST_Test` | `includes/rest/class-mpu-rest-test.php` | Admin 連線測試與快取清除：取代 `rest-test.php` |

### 3. 入口檔案重構（修正）

- **現狀**：`mp-ukagaka.php` 透過模組清單載入 `rest/*.php` 與 `ajax/chat-api-handlers.php`。
- **重構目標**：逐步以 Controller 類別取代對應的 procedural REST 檔案。
- **重要原則（Review 修正）**：
  - 在任何階段都不要讓「舊 procedural 與新 Controller」同時註冊相同 REST 路由。
  - 採用**單軌切換（cutover）**，而非雙軌並行註冊。
  - 舊檔可保留作備份，但不應同時掛到 `rest_api_init`。

### 4. Controller 註冊入口（新增，避免分散 `add_action`）

為避免新 Controller 各自在檔案內分散掛 `add_action('rest_api_init', ...)`，建議集中於單一入口：

- **建議檔案**：`includes/rest/bootstrap.php`（也可放在 `mp-ukagaka.php`，但集中檔案較乾淨）
- **職責**：
  - 載入 Base Class 與已啟用的 Controller 類別檔
  - 以陣列列出目前已啟用的 Controller 類別
  - `foreach` 實例化 Controller，並統一掛載 `add_action('rest_api_init', [$controller, 'register_routes'])`
- **Phase 管理方式（Review 補強）**：
  - Phase 1：Controller 陣列可為空（先建立機制）
  - 後續各 Phase：切換哪個 domain，就把對應 Controller 加入陣列，並同步停用舊 procedural 註冊

此作法可明確定義「啟用新 Controller」的唯一位置，降低與舊 procedural `add_action` 打架的機率。

---

## 🛠️ 實作步驟（Review 修正版）

### 第一階段：建立基礎設施（低風險，不接管既有路由）

1. 建立 `includes/rest/class-mpu-rest-base.php`，實作：
   - `protected function rate_limit($key, $max, $window)` — 包裝現有的 `mpu_rest_check_rate_limit()`
   - `protected function check_admin()` — 提供新 Controller 使用的 admin 權限檢查
   - `protected function ok($data, $status = 200)` — 包裝 `WP_REST_Response`
   - `protected function fail($code, $message, $status)` — 包裝 `WP_Error`
   - `abstract public function register_routes()`
2. **暫不移除或改名任何既有全域函式**（例如 `mpu_rest_admin_permission_check()`），以避免舊 procedural 檔案失效。
3. `mp-ukagaka.php` 可先加入 Base Class 載入，但**不得**因此停止載入現有 `rest/*.php`。
4. 建立 Controller 註冊入口（建議 `includes/rest/bootstrap.php`），先把集中註冊機制就位；此時 Controller 陣列可為空。
5. 此階段不切換任何 endpoint，目標是先建立可用基礎類別與註冊入口。

### 第二階段：遷移 Test Controller（低風險）

`rest-test.php` 相對獨立：2 個 endpoint、Admin-only、行為集中。

1. 建立 `class-mpu-rest-test.php`，繼承 `MPU_REST_Base`。
2. 將 `mpu_rest_test_connection()` 與 `mpu_rest_clear_api_cache()` 邏輯移入類別方法。
3. `register_routes()` 中註冊相同路由（URL / method / args / permission / status / error code 行為保持一致）。
4. **切換方式（Review 修正）**：
   - 啟用新 Controller 註冊前，先停用舊 `rest-test.php` 的路由註冊（例如從模組清單移除 `rest/rest-test.php`，或讓舊檔不再 `add_action('rest_api_init', ...)`）。
   - 在 Controller 註冊入口（`includes/rest/bootstrap.php` 或等效位置）將 `MPU_REST_Test` 加入啟用陣列。
   - 不採用新舊同時註冊相同路由的「雙軌並行」。
5. 驗證後台 Test Connection 正常，再決定是否刪除 `rest-test.php`（可先保留檔案但不載入）。

### 第三階段：遷移 Touch Controller（低風險）

`rest-touch.php` 只有 2 個 endpoint，邏輯集中。

1. 建立 `class-mpu-rest-touch.php`，繼承 `MPU_REST_Base`。
2. 將 `/touch/decoration` 與 `/touch/zone` callback 移入。
3. 在 Controller 註冊入口加入 `MPU_REST_Touch`。
4. 採用與 Test Controller 相同的**單軌切換**方式（先停用舊註冊，再啟用新註冊）。
5. 驗證前端觸摸互動正常後，再移除/停用 `rest-touch.php`。

### 第四階段：遷移 Chat Controller（中風險）

`rest-chat.php` 本身是路由入口，邏輯在 `rest/chat/*.php`。

1. 建立 `class-mpu-rest-chat.php`，繼承 `MPU_REST_Base`。
2. 將三個 handler（`context-handler.php`, `greet-handler.php`, `user-chat-handler.php`）移入類別方法。
3. 注意：`user-chat-handler.php` 含 Rate Limit 與 Abilities/MCP 整合，需完整回歸測試。
4. 在 Controller 註冊入口加入 `MPU_REST_Chat`。
5. 切換時避免與 `rest-chat.php` / `rest/chat/*.php` 重複註冊同路徑。
6. 驗證完成後，再停用或刪除 `rest-chat.php` 與 `rest/chat/`。

### 第五階段：拆解 `rest-core.php`（中高風險）

`rest-core.php` 目前有 11 個 endpoint，是主要重構目標。建議拆成兩個 Controller：

- **`MPU_REST_Ghost`**（外觀與設定，盡量不含 LLM 流程）：
  `/init`, `/settings`, `/change`, `/extend`, `/shell-info`, `/decoration-config`, `/emoji-config`
- **`MPU_REST_Dialog`**（對話文件 / 對話流程相關）：
  `/nextmsg`, `/dialog`, `/visitor-info`, `/decoration-prompts`, `/wake-ghost`

建議逐一 Controller 切換與驗證，不要一次替換整個 `rest-core.php`。
切換每個 Controller 時，皆透過 Controller 註冊入口加入/移除啟用項目，避免註冊邏輯再次分散。

---

## ⚠️ 相容性要求（Review 補強）

這是一次**純粹重構**，除了路由分層方式改變外，對外行為應維持一致。

每個被遷移的 endpoint 都應確認以下項目**不變**：

1. 路由 URL（path）
2. HTTP method（`GET` / `POST` 等）
3. `permission_callback` 行為（登入、權限、錯誤碼）
4. `args` 驗證規則（含 route param regex / required / type）
5. Payload 結構（request / response key）
6. HTTP status code
7. `WP_Error` code 與 message（至少對前端依賴的部分保持一致）
8. Rate limit key / 限制值 / 視窗時間

---

## 🧪 驗證策略（Review 修正）

### 原則

- **單軌切換，不重複註冊**：同一路由只允許一個註冊來源（舊或新）。
- **可回滾**：保留舊檔案作備份可以，但切換時不載入它。
- **小步驗證**：每遷移一個 Controller 就測一次，不要累積到最後一起測。

### 每階段驗證方式

1. **Test Controller**：後台 → AI 設定 → Test Connection（各 provider）確認回應與錯誤處理正常。
2. **Touch Controller**：前端觸摸裝飾/區域，確認對話與互動行為正常。
3. **Chat Controller**：前端 Network tab 送出對話，確認 `/chat/user`、`/chat/context`、`/chat/greet` 回傳格式正確。
4. **Ghost/Dialog Controllers**：頁面載入 `/init`、角色切換 `/change`、自言自語 `/nextmsg`、對話資料 `/dialog`。
5. **錯誤情境驗證**：未登入/權限不足/無效 provider/Rate limit 觸發時，確認 status 與錯誤碼未改變。

---

## 附帶清理（改為獨立後續工作，避免混入重構風險）

以下檔案是否可刪除，應在 REST OO 遷移完成後另開一個清理 task 處理，不建議綁在本次重構內：

- `includes/ajax/ajax-handlers.php`
- `includes/ajax/ajax-handlers-test.php`
- `includes/ajax/ajax-touch-handlers-llm.php`
- `includes/ajax/ajax-chat-handlers-llm.php`
- `includes/ajax/chat/context-handler.php`
- `includes/ajax/chat/greet-handler.php`
- `includes/ajax/chat/user-chat-handler.php`

### 清理前必要確認（Review 新增）

1. 全專案搜尋是否仍有 `require/include/function_exists/call_user_func` 等引用。
2. 前端 JS 是否仍呼叫舊 AJAX action（`admin-ajax.php`）。
3. `mp-ukagaka.php` 模組清單是否仍載入任何相關檔案。
4. 實測主要功能後再刪檔，並獨立 commit 方便回滾。

> 註：`includes/ajax/chat-api-handlers.php` 目前仍被 `mp-ukagaka.php` 載入，需獨立評估，不在本次 REST 路由 OO 重構範圍內。

---

## 建議施作順序（更新）

1. 第一階段：Base Class（不切路由）
2. 第二階段：Test Controller（單軌切換 + 驗證）
3. 第三階段：Touch Controller（單軌切換 + 驗證）
4. 第四階段：Chat Controller（獨立 commit）
5. 第五階段：Ghost / Dialog（可拆成 2 個 commit）
6. 清理 AJAX 舊檔（另案、另 commit）

這樣可先快速建立基礎與低風險遷移，同時避免「雙重註冊同路由」造成的偽成功驗證。
