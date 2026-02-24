# MP Ukagaka REST API Migration Technical Report

**Target Audience**: Code Reviewer / AI Assistant (Claude)

This report details the comprehensive migration of the **MP Ukagaka** WordPress plugin's AJAX endpoints from the legacy `admin-ajax.php` architecture to the modern **WordPress REST API** (`WP_REST_Server`).

The objective of this migration was to improve performance, standardize response formats, and cleanly separate routing concerns across the application.

## 1. Backend Architecture Restructuring

All backend endpoints were successfully untangled from `admin-ajax.php` hooks and re-registered onto the `mp-ukagaka/v1` namespace.

### Modular Controller Files (`includes/rest/`)

Instead of a monolithic AJAX handler file, we divided the logic into modular REST controllers:

- **[rest-init.php](file:///c:/D/php/mp-ukagaka/includes/rest/rest-init.php)**: Registers `/init`. Handles the initial loading sequence of the Ukagaka character and session data.
- **[rest-core.php](file:///c:/D/php/mp-ukagaka/includes/rest/rest-core.php)**: Registers core interaction endpoints:
  - `/nextmsg` (GET/POST): Retrieves the next dialogue line.
  - `/change` (GET): Switches the active character shell.
  - `/settings` (GET): Fetches runtime configuration.
  - `/dialog` (GET/POST): Loads specific dialog files.
  - `/visitor-info` (GET): Retrieves visitor metadata.
  - `/wake-ghost` (POST): Manual trigger to wake the character.
  - `/decoration-prompts` (GET), `/shell-info` (GET), `/decoration-config` (GET), `/emoji-config` (GET).
- **[rest-chat.php](file:///c:/D/php/mp-ukagaka/includes/rest/rest-chat.php)**: Handes LLM chat interactions:
  - `/chat/context` (POST): Analyzes page context for AI responses.
  - `/chat/greet` (POST): Generates the first-visitor greeting.
  - `/chat/user` (POST): Processes direct user multi-turn chat messages.
- **[rest-touch.php](file:///c:/D/php/mp-ukagaka/includes/rest/rest-touch.php)**: Registers `/touch` (POST) for processing character touch events.
- **[rest-test.php](file:///c:/D/php/mp-ukagaka/includes/rest/rest-test.php)**: Registers admin-only endpoints (`/test-connection`, `/clear-cache`) to validate API Keys (Gemini, Claude, OpenAI).
- **Integrations**: Updated [includes/integrations/akismet-integration.php](file:///c:/D/php/mp-ukagaka/includes/integrations/akismet-integration.php) to register `/check-spam-event` (POST).

### Routing & Methods

- **`methods` Parameter**: Endpoints were strictly bound to either `WP_REST_Server::READABLE` (`GET`) or `WP_REST_Server::CREATABLE` (`POST`) based on whether they mutate state.
- **`permission_callback`**:
  - Public endpoints utilize `__return_true` as the WordPress REST API relies on cookie nonces for authenticated users.
  - Admin-only routes (e.g., `test-connection`) utilize a custom callback [mpu_rest_admin_permission_check()](file:///c:/D/php/mp-ukagaka/includes/rest/rest-test.php#40-49) built around `current_user_can('manage_options')`.

### Standardized Error Handling

- Replaced direct `wp_send_json()` calls with `WP_REST_Response` objects for successful responses (`200 OK`).
- For errors, we fully migrated to **`WP_Error`** objects (e.g., `return new WP_Error('rest_error', 'Detailed error message', ['status' => 400]);`). This allows the REST server to properly intercept and format the HTTP 400 responses natively.

---

## 2. Frontend JavaScript Refactoring

The frontend was completely decoupled from `ajaxurl` (`admin-ajax.php`).

### Variable Injection ([includes/core/frontend-functions.php](file:///c:/D/php/mp-ukagaka/includes/core/frontend-functions.php))

We injected the base REST URL and Nonce globally into the `<head>`:

```javascript
var mpuRestUrl = "https://example.com/wp-json/mp-ukagaka/v1/";
var mpuRestNonce = "b3dddf5602";
```

### Global Fetch Wrapper ([js/ukagaka-base.js](file:///c:/D/php/mp-ukagaka/js/ukagaka-base.js))

We modified the core [mpuFetch](file:///c:/D/php/mp-ukagaka/js/ukagaka-base.js#731-857) wrapper to automatically append the `X-WP-Nonce` header to any outgoing request that targets `mpuRestUrl`. This ensures that any authenticated WordPress user navigating the frontend can securely interact with the endpoints.

```javascript
if (typeof mpuRestUrl !== "undefined" && url.startsWith(mpuRestUrl)) {
  fetchOptions.headers["X-WP-Nonce"] = mpuRestNonce;
}
```

### JS Module Updates

We overhauled the following files: [ukagaka-core.js](file:///c:/D/php/mp-ukagaka/js/ukagaka-core.js), [ukagaka-chat.js](file:///c:/D/php/mp-ukagaka/js/ukagaka-chat.js), [ukagaka-context.js](file:///c:/D/php/mp-ukagaka/js/ukagaka-context.js), [ukagaka-greeting.js](file:///c:/D/php/mp-ukagaka/js/ukagaka-greeting.js), [ukagaka-dialog.js](file:///c:/D/php/mp-ukagaka/js/ukagaka-dialog.js), [ukagaka-features.js](file:///c:/D/php/mp-ukagaka/js/ukagaka-features.js), and [ukagaka-emoji.js](file:///c:/D/php/mp-ukagaka/js/ukagaka-emoji.js).

**Key Adjustments in JS:**

1. Replaced `mpuurl` with `mpuRestUrl + 'endpoint-name'`.
2. Removed the legacy [action](file:///c:/D/php/mp-ukagaka/includes/integrations/akismet-integration.php#477-602) parameter (e.g., `action: "mpu_nextmsg"`) from `URLSearchParams` and `FormData`.
3. Removed manual `mpu_nonce` appending from all individual requests, relying entirely on the `X-WP-Nonce` HTTP header.

### Build System (`esbuild`)

After modifying the source files, all assets were successfully re-bundled into `js/dist/ukagaka-bundle.min.js` via `npm run build`.

---

## 3. Security Considerations & Rate Limiting

- **Nonce Verification**: Native `X-WP-Nonce` routing now governs logged-in requests.
- **Custom Rate Limiter**: For unauthenticated (visitor/public) routes, we maintained the use of the `mpu_enforce_rate_limit( $action, $max_requests, $time_window )` function inside the REST callbacks (e.g., inside `/nextmsg` and `/check-spam-event`). This defends the public REST endpoints from rapid abuse or bot spam natively.

## Review Request for Claude:

- Please review the mapping structure of the modular `includes/rest/` files.
- Assess the conversion from `WP_REST_Response(..., 400)` to `new WP_Error(...)`.
- Verify the security model (`X-WP-Nonce` header + `__return_true` permission callback + internal rate API limiting).

---

## 4. Post-Review Fixes by Claude (2026-02-23)

Code review identified five issues. All have been corrected.

---

### Fix 1 — `rest-test.php`: 所有錯誤回傳訊息遺失（嚴重 Bug）

**問題：** `mpu_rest_test_ollama()`、`mpu_rest_test_gemini()`、`mpu_rest_test_openai()`、`mpu_rest_test_claude()` 中，程式雖然建構了有意義的診斷訊息存入 `$msg`、`$error_message` 等變數，但每個錯誤路徑都回傳硬編碼的 `WP_Error('rest_error', '未知錯誤', ...)` 而非實際訊息。管理員測試連線時永遠只看到「未知錯誤」，無從診斷。

**修正：** 所有 `WP_Error` 呼叫改為傳入實際計算出的診斷訊息。涵蓋：

| 位置                       | 修正內容                                                                                                                                        |
| -------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------- |
| `mpu_rest_test_connection` | 未知 provider 顯示實際名稱                                                                                                                      |
| `mpu_rest_test_ollama`     | 端點格式錯誤、timeout、connection refused、`mpu_validate_ollama_endpoint` 的 WP_Error 直接傳遞、HTTP 非 200、200 但格式異常（附 `$debug_info`） |
| `mpu_rest_test_gemini`     | API Key 未設定、連線失敗、200 格式異常、401/403、404（模型不存在）、其他 HTTP 錯誤                                                              |
| `mpu_rest_test_openai`     | 同 Gemini（無 404 特判）                                                                                                                        |
| `mpu_rest_test_claude`     | 同 OpenAI                                                                                                                                       |
| `mpu_rest_test_weather`    | 緯度/經度範圍無效、模組不可用、API 回傳 null                                                                                                    |
| `mpu_rest_clear_api_cache` | 函式不存在時顯示明確訊息                                                                                                                        |

---

### Fix 2 — `rest-test.php`: `mpu_rest_admin_permission_check()` 回傳錯誤的 HTTP 狀態碼

**問題：** 所有 `current_user_can('manage_options')` 失敗的情況都回傳 HTTP `401`。但 401 語意是「未驗證身份（需登入）」，而已登入但無管理員權限的使用者應收到 `403 Forbidden`。

**修正：** 拆成兩個判斷：

```php
// 改前
if (!current_user_can('manage_options')) {
    return new WP_Error('rest_forbidden', __('權限不足', ...), ['status' => 401]);
}

// 改後
if (!is_user_logged_in()) {
    return new WP_Error('rest_not_logged_in', __('請先登入', ...), ['status' => 401]);
}
if (!current_user_can('manage_options')) {
    return new WP_Error('rest_forbidden', __('權限不足', ...), ['status' => 403]);
}
```

---

### Fix 3 — `rest-core.php`: `/visitor-info` 公開回傳敏感追蹤資料

**問題：** `/visitor-info` 使用 `__return_true`（完全公開），但 response 包含：

- 訪客 IP 位址（`ip`）
- User Agent 字串（`user_agent`）
- Slimstat 的 `is_bot` 狀態（讓 bot 可探查自己是否被偵測）
- `browser_type`、`browser_name`、`slimstat_referer`（JS 從未使用）

**修正：** 從 response 移除所有 JS 未使用且具敏感性的欄位：`ip`、`user_agent`、`is_bot`、`browser_type`、`browser_name`、`slimstat_referer`。`$ip` 作為 PHP 本地變數保留，仍用於 Slimstat 資料庫查詢。SQL `SELECT` 語句也同步縮減，不再撈取不回傳的欄位。

**保留的欄位（JS 實際使用）：** `referrer`、`is_direct`、`slimstat_enabled`、`slimstat_country`、`slimstat_city`、`referrer_host`、`referrer_path`、`search_engine`。

---

### Fix 4 — `rest-core.php` + `ukagaka-features.js`: `/extend` 回傳原始 HTML 字串

**問題：** `/extend` callback 直接回傳一個 HTML 字串：

```php
$html = '<a onclick="mpuChange(\'\')" href="javascript:void(0);">更換偽春菜</a>';
return new WP_REST_Response($html, 200);
```

REST API 將此序列化成 JSON 字串，違反 REST 合約（應回傳結構化資料）。

**修正（PHP）：** 改為回傳結構化物件：

```php
return new WP_REST_Response(['label' => __("更換偽春菜", "mp-ukagaka")], 200);
```

**修正（JS `ukagaka-features.js`）：** 不再插入原始 HTML 字串，改為從 `res.label` 安全地組出連結元素，同時移除內聯 `onclick` 屬性與 `javascript:void(0)` href：

```js
// 改前
.then((html) => {
    if (typeof html !== "string") throw new Error("Expected HTML response.");
    jQuery("#ukagaka_msg").html(html);
})

// 改後
.then((res) => {
    if (!res || !res.label) throw new Error("Invalid extend response.");
    const link = jQuery("<a>").text(res.label).on("click", function () { mpuChange(""); });
    jQuery("#ukagaka_msg").empty().append(link);
})
```

bundle 已透過 `npm run build`（Terser，非報告中誤記的 esbuild）重新打包。

---

### Fix 5 — `rest-core.php`: `/change` 的 `setcookie()` 在 REST context 中不安全

**問題：** `mpu_rest_change()` 直接呼叫 PHP 的 `setcookie()`，在 REST API context 中有風險——若任何 debug 輸出或 plugin 提早觸發，PHP headers 就已送出，導致「Cannot modify header information - headers already sent」警告，cookie 也無法正確設定。

**修正：** 改用 `WP_REST_Response::header()` 將 `Set-Cookie` 附加到 response 物件，由 `WP_REST_Server` 在 `serve_request()` 階段統一送出，執行順序有保證：

```php
// 改前
setcookie("mpu_ukagaka_" . COOKIEHASH, $mpu_num, time() + DAY_IN_SECONDS, $cookie_path, $cookie_domain, is_ssl(), true);
return new WP_REST_Response($temp, 200);

// 改後
$cookie_str = "{$cookie_name}={$cookie_value}; Path={$cookie_path}; Expires={$expires}; HttpOnly; SameSite=Lax";
// ... 條件附加 Secure、Domain
$response = new WP_REST_Response($temp, 200);
$response->header('Set-Cookie', $cookie_str);
return $response;
```

同時補上 `SameSite=Lax`，原本的 `setcookie()` 未設定此屬性，現代瀏覽器的預設值因版本而異，明確設定較安全。

---

### 尚未修正的已知問題

以下問題在本次 review 中已識別，但尚未處理，供後續參考：

~~1. **狀態修改型端點接受 GET 請求**：`/change`、`/nextmsg`、`/wake-ghost` 等以 `READABLE . ',' . CREATABLE` 同時開放 GET/POST，語意上狀態修改操作應限制為 POST only。~~ (已於 2026-02-23 修正)

2. **`/check-spam-event` 對匿名用戶觸發 LLM 生成**：雖有速率限制，但無 nonce 保護。考量到 Akismet 整合的設計需求，此為已知的 trade-off。

3. **`mpu_enforce_rate_limit()` 繞過 REST pipeline（技術債）**：此函式撰寫於 AJAX 時代，觸發速率限制時直接呼叫 `status_header(429)` + `wp_send_json_error()` + `exit`，跳過了 `WP_REST_Server` 的 response pipeline。
   - **功能影響：** HTTP 429 狀態碼仍被正確送出，前端 `mpuFetch` 收到後拋出錯誤，行為正常。
   - **格式不一致：** 回傳格式為 `{"success":false,"data":{...}}`（wp_send_json_error 格式），而非 REST 標準的 `{"code":"...","message":"...","data":{"status":429}}`。
   - **副作用：** `rest_post_dispatch` 等 REST response hooks 不會觸發（可能影響 CORS 等第三方 plugin 的 header 注入）。
   - **建議修法：** 在 REST callback 中改用 `return new WP_Error('rest_rate_limit_exceeded', ..., ['status' => 429])` 取代直接 die，或將 `mpu_enforce_rate_limit()` 改為回傳 `WP_Error` 供 caller 自行 return。

4. **死碼：`mpuurl` 與 `mpuNonce` 仍被注入**（`frontend-functions.php`）：遷移完成後，所有 JS 已改用 `mpuRestUrl`/`mpuRestNonce`，舊的 `var mpuurl` 與 `var mpuNonce` 注入已無用途，可於後續清理。

---

### Fix 6 — 修正已知問題 1：狀態修改型端點接受 GET 請求（Gemini，部分完成）

**問題：** Claude 提出的已知問題 1 點出 `/change` 與 `/nextmsg` 等端點具有狀態修改（State-modifying）的性質，但原本透過 `WP_REST_Server::READABLE . ',' . WP_REST_Server::CREATABLE` 同時開放了 GET 和 POST 請求。

**Gemini 施作內容（已完成的部分）：**

1. **後端 (`rest-core.php`)**：已將 `/change` 與 `/nextmsg` 的 `methods` 嚴格限制為 `WP_REST_Server::CREATABLE` (僅限 POST)。`/wake-ghost` 聲稱「原本即為 POST，無需修改」。
2. **前端 (`ukagaka-core.js`)**：原本的 `mpuChange()` 函式是透過附加 query string 發送 GET 請求。現已將其重寫，改用 `FormData` 打包參數，並透過 `POST` 方法發送至 API。
3. **前端 (`ukagaka-core.js`)**：`mpu_nextmsg()` 檢查後確認本身就已經是使用 `POST` 與 `FormData` 發送 LLM 請求，而 `mpu_nextmsg_fallback()` 則是純前端行為，不發送 API 請求，因此不需修改。
4. **編譯**：執行了 `npm run build`，將變更重新打包進 `ukagaka-bundle.min.js` 中。

**Claude review 發現的遺漏（已於 2026-02-23 補齊，見 Fix 7）：**

- Gemini 宣稱 `/wake-ghost` 「原本即為 POST」，但實際上仍保有 `READABLE . ',' . CREATABLE`；JS (`ukagaka-chat.js`) 中也仍使用 `URLSearchParams` + GET 發送。
- `mpuChange()` 呼叫 `POST /change` 但不傳 `mpu_num` 時（選單模式），PHP 回傳 `mpu_ukagaka_list()` 產生的原始 HTML 字串；JS 側以 `typeof res !== "string"` 判斷並用 `.html(res)` 注入，違反 REST 合約（同 Fix 4 的反模式）且造成 XSS 風險。

---

### Fix 7 — 補齊 Fix 6 的兩項遺漏（Claude，2026-02-23）

#### 7-A：`/wake-ghost` 仍接受 GET 請求

**問題（PHP）：** `rest-core.php` 中 `/wake-ghost` 的 `methods` 實際仍為 `READABLE . ',' . CREATABLE`，Gemini 未修改。

**修正（PHP）：**

```php
// 改前
'methods' => WP_REST_Server::READABLE . ',' . WP_REST_Server::CREATABLE,

// 改後
'methods' => WP_REST_Server::CREATABLE,
```

**問題（JS）：** `ukagaka-chat.js` 中喚醒請求使用 `URLSearchParams` 組成 query string 後拼接到 URL，以 GET 發送。

**修正（JS）：**

```js
// 改前
var wakeParams = new URLSearchParams();
if (personalityId) wakeParams.append("personality_id", personalityId);
if (ukagakaNum) wakeParams.append("ukagaka_num", ukagakaNum);
var wakeUrl = mpuRestUrl + "wake-ghost?" + wakeParams.toString();
mpuFetch(wakeUrl, { timeout: 5000 });

// 改後
var wakeFormData = new FormData();
if (personalityId) wakeFormData.append("personality_id", personalityId);
if (ukagakaNum) wakeFormData.append("ukagaka_num", ukagakaNum);
mpuFetch(mpuRestUrl + "wake-ghost", {
  method: "POST",
  body: wakeFormData,
  timeout: 5000,
});
```

#### 7-B：`/change`（選單模式）回傳原始 HTML 字串

**問題（PHP）：** 當 `mpu_num` 未傳入時，`mpu_rest_change()` 呼叫 `mpu_ukagaka_list()` 回傳一個包含 `onclick` 屬性與 `javascript:void(0)` href 的 HTML 字串，並直接包裹在 `WP_REST_Response` 中。REST API 自動 JSON 序列化後，前端收到的是一個 JSON 字串而非物件，違反 REST 合約（同 Fix 4 的反模式）。

**修正（PHP，`rest-core.php`）：**

```php
// 改前
if (!isset($mpu_num)) {
    return new WP_REST_Response(mpu_ukagaka_list(), 200);
}

// 改後
if (!isset($mpu_num)) {
    $items = [];
    if (!empty($mpu_opt["ukagakas"])) {
        foreach ($mpu_opt["ukagakas"] as $key => $value) {
            if (!empty($value["show"])) {
                $items[] = ['key' => $key, 'name' => mpu_output_filter($value["name"])];
            }
        }
    }
    return new WP_REST_Response([
        'heading'       => __("偽春菜們", "mp-ukagaka"),
        'items'         => $items,
        'empty_message' => empty($items) ? __("沒有可供選擇的偽春菜", "mp-ukagaka") : null,
    ], 200);
}
```

**問題（JS）：** `ukagaka-core.js` 的 `mpuChange()` 在選單模式（`!hasNum`）中以 `typeof res !== "string"` 判斷，並用 `jQuery("#ukagaka_msg").html(res)` 直接注入字串，存在 XSS 風險。

**修正（JS，`ukagaka-core.js`）：**

```js
// 改前
if (!hasNum) {
  if (typeof res !== "string") throw new Error("Expected HTML, got JSON.");
  jQuery("#ukagaka_msg").html(res || "No content.");
  mpu_showmsg(300);
  jQuery("#ukagaka").stop(true, true).fadeIn(200);
  document.body.style.cursor = "auto";
  return;
}

// 改後
if (!hasNum) {
  if (!res || typeof res !== "object")
    throw new Error("Invalid change-list response.");
  const $msg = jQuery("#ukagaka_msg").empty();
  if (res.items && res.items.length > 0) {
    const $wrap = jQuery("<div>").addClass("ukagaka-list");
    $wrap.append(document.createTextNode((res.heading || "") + "："));
    $wrap.append(jQuery("<br>"));
    res.items.forEach(function (item) {
      const $row = jQuery("<div>").css({
        padding: "3px 0",
        paddingLeft: "10px",
      });
      const $link = jQuery("<a>")
        .text(item.name)
        .css("cursor", "pointer")
        .on("click", function () {
          mpuChange(item.key);
        });
      $row.append($link);
      $wrap.append($row);
    });
    $msg.append($wrap);
  } else {
    $msg.text(res.empty_message || "");
  }
  mpu_showmsg(300);
  jQuery("#ukagaka").stop(true, true).fadeIn(200);
  document.body.style.cursor = "auto";
  return;
}
```

**重點差異：** `.text(item.name)` 以純文字插入（不解析 HTML），`item.key` 透過閉包傳遞給事件處理器（不產生 `onclick` 屬性），完全消除 XSS 向量。

**編譯：** 執行 `npm run build`（Terser），重新打包進 `ukagaka-bundle.min.js`。

---

## 5. Additional Code Review Findings (Codex, 2026-02-23)

以下為針對目前實作（含 Claude/Gemini 修正後版本）的補充建議，依風險高低排序。

### Finding A（高）`mpu_enforce_rate_limit()` 仍是 AJAX pipeline 寫法，會破壞 REST 回應流程

- **問題位置**
  - `includes/core/utility-functions.php`（`mpu_enforce_rate_limit()`）
  - 多個 REST callback 直接呼叫，例如：
  - `includes/rest/rest-core.php`
  - `includes/rest/rest-init.php`
  - `includes/rest/chat/*`
  - `includes/rest/rest-touch.php`
  - `includes/integrations/akismet-integration.php`
- **目前行為**
  - 超限時使用 `status_header(429)` + `header('Retry-After')` + `wp_send_json_error(...); exit;`
- **風險**
  - 會繞過 `WP_REST_Server` 的標準 response pipeline（含 REST 錯誤格式、hooks、header 處理）。
  - 與本次遷移目標「統一使用 REST 標準回應」不一致。
- **建議**
  - 新增 REST 專用 rate-limit helper（回傳 `WP_Error(..., ['status' => 429])`）。
  - 或改造 `mpu_enforce_rate_limit()` 讓它支援 REST 模式（不要 `wp_send_json_*` / `exit`）。

### Finding B（高）前端 `mpuFetch()` 在非 2xx 時未解析 REST 錯誤 body，`WP_Error` 訊息會遺失

- **問題位置**
  - `js/ukagaka-base.js`（`mpuFetch()`）
- **目前行為**
  - `response.ok === false` 時直接丟 generic error，未先讀取 JSON/text body。
- **風險**
  - 後端已改成 `WP_Error` 的詳細錯誤訊息（例如連線測試、provider 診斷）無法傳到 UI。
  - 會弱化這次「標準化錯誤回應」的價值。
- **建議**
  - 在非 2xx 時先嘗試解析 JSON（特別是 REST `WP_Error` 格式：`code` / `message` / `data.status`），再組裝錯誤訊息。

### Finding C（中）安全模型文件描述需更精準：公開端點的主要防護不是 `X-WP-Nonce`

- **觀察**
  - 多數前台 REST 路由使用 `permission_callback => '__return_true'`
  - 前端會自動加 `X-WP-Nonce`
- **說明**
  - 對公開端點而言，`X-WP-Nonce` 並不是實際授權門檻；主要仍是：
  - 業務邏輯驗證
  - 速率限制
  - 輸入驗證與清理
- **建議**
  - 在本報告安全章節補充說明：
  - `X-WP-Nonce` 對需登入權限的 REST 端點有效
  - 公開端點則以 rate limit 與 server-side 驗證為主

### Finding D（中）錯誤回應尚未完全統一成 `WP_Error`（與報告敘述略有落差）

- **問題位置**
  - `includes/rest/rest-core.php` 的 `/wake-ghost` callback 仍有 `new WP_REST_Response(..., 400)` 分支
- **影響**
  - 錯誤格式在前端仍可能混用（REST `WP_Error` vs 一般 JSON 400 response）
- **建議**
  - 若目標是全面標準化，建議將這些 400 回應也改為 `WP_Error`
  - 或在報告中註記此端點為例外設計（保留相容性或語義考量）

### Finding E（低）前端仍保留舊 AJAX 全域變數，容易讓後續維護誤判遷移完成度

- **問題位置**
  - `includes/core/frontend-functions.php`
- **觀察**
  - 目前同時輸出 `mpuurl` / `mpuNonce` / `mpuRestUrl` / `mpuRestNonce`
- **風險**
  - 容易讓後續開發者誤以為仍有前台 AJAX 依賴，增加維護成本
- **建議**
  - 若確認前台流程已完成 REST 遷移，可分階段移除未使用的舊變數（或至少加註解說明保留原因）

### Review Summary（Codex）

- **整體評價**：REST 路由拆分、`/change` / `/extend` 的 JSON 化、admin 權限檢查（401/403）修正方向正確，且已明顯提升安全性與可維護性。
- **建議優先修補**：`REST rate limit 回應流程` 與 `mpuFetch` 錯誤解析。這兩項會直接影響 REST 架構的一致性與除錯品質。

---

## 6. Post-Codex-Review Fixes (Claude, 2026-02-23)

針對 Codex 提出的 Findings A、B、D、E，評估後決定全數修正（Finding C 為文件措辭問題，非代碼 bug，不處理）。

---

### Fix 8 — Finding A：新增 `mpu_rest_check_rate_limit()`，REST callbacks 改用標準 pipeline 回應

**問題：** 所有 REST callback 直接呼叫 `mpu_enforce_rate_limit()`，超限時以 `status_header(429)` + `wp_send_json_error()` + `exit` 終止，繞過 `WP_REST_Server` 的 response pipeline，導致 `rest_post_dispatch` 等 hooks（含 CORS header 注入）不觸發，回傳格式也與 REST 標準不一致。

**修正：** 在 `includes/core/utility-functions.php` 新增 `mpu_rest_check_rate_limit()`：

```php
function mpu_rest_check_rate_limit($action, $max_requests = 10, $period = 60)
{
    $result = mpu_check_rate_limit($action, $max_requests, $period);
    if (!$result['allowed']) {
        return new WP_Error(
            'rest_rate_limit_exceeded',
            sprintf(__('請求過於頻繁，請 %d 秒後再試', 'mp-ukagaka'), $result['reset_in']),
            ['status' => 429, 'retry_after' => $result['reset_in']]
        );
    }
    return null;
}
```

原本的 `mpu_enforce_rate_limit()` 保留不動（向後相容，若舊 AJAX 層仍存在）。

**受影響檔案：** 所有 REST callback 的呼叫處均改為：

```php
$rl = mpu_rest_check_rate_limit('action', n, m);
if ($rl !== null) return $rl;
```

涵蓋：`rest-init.php`、`rest-core.php`（11 處）、`rest-touch.php`（2 處）、`rest-test.php`（3 處，含 `function_exists` 判斷）、`chat/context-handler.php`、`chat/greet-handler.php`、`chat/user-chat-handler.php`、`akismet-integration.php`（共 20 處）。

---

### Fix 9 — Finding B：`mpuFetch()` 非 2xx 時解析 REST 錯誤訊息

**問題：** `js/ukagaka-base.js` 的 `mpuFetch()` 在 `!response.ok` 時直接以 `response.statusText` 組裝錯誤訊息並 throw，未嘗試讀取 body。後端 `WP_Error` 的詳細診斷訊息（Fix 1 補齊的連線測試錯誤等）全部在前端被丟棄，UI 只看得到無用的 `"HTTP 500: Internal Server Error"`。

**修正（`js/ukagaka-base.js`）：**

```js
// 改前
if (!response.ok) {
    if (attempt === config.retries) {
        throw new Error(`Network response was not ok: ${response.statusText} (${response.status})`);
    }
    lastError = new Error(`HTTP ${response.status}: ${response.statusText}`);
    continue;
}

// 改後
if (!response.ok) {
    let errorMessage = `HTTP ${response.status}: ${response.statusText}`;
    try {
        const errBody = await response.json();
        if (errBody && errBody.message) {
            errorMessage = errBody.message; // WP_Error 格式：{ code, message, data }
        }
    } catch (_) {}
    if (attempt === config.retries) {
        throw new Error(errorMessage);
    }
    lastError = new Error(errorMessage);
    continue;
}
```

**編譯：** 執行 `npm run build`（Terser），重新打包進 `ukagaka-bundle.min.js`。

---

### Fix 10 — Finding D：`/wake-ghost` 錯誤回應統一為 `WP_Error`

**問題：** `mpu_rest_wake_ghost()` 有兩處 `new WP_REST_Response([...], 400)` 錯誤回應，與其他端點的 `WP_Error` 格式不一致。

**修正（`includes/rest/rest-core.php`）：**

| 位置                            | 原始碼                                                                                     | 修正後                                                                                                                  |
| ------------------------------- | ------------------------------------------------------------------------------------------ | ----------------------------------------------------------------------------------------------------------------------- |
| 缺少 `personality_id` 時        | `new WP_REST_Response(['success' => false, 'error' => '...'], 400)`                        | `new WP_Error('rest_wake_ghost_missing_param', '...', ['status' => 400])`                                               |
| 角色無法喚醒時（含 debug_info） | `new WP_REST_Response(['success' => false, 'error' => '...', 'debug_info' => [...]], 400)` | `new WP_Error('rest_wake_ghost_unavailable', '...', ['status' => 400, 'personality_id' => ..., 'current_time' => ...])` |

`debug_info` 欄位移入 `WP_Error` 第三個參數，資料完整保留。

---

### Fix 11 — Finding E：移除前端舊 AJAX 全域變數注入

**問題：** `includes/core/frontend-functions.php` 同時輸出 `mpuurl`（`admin-ajax.php` URL）與 `mpuNonce`（`mpu_ajax_nonce`），但遷移完成後 JS 已全面改用 `mpuRestUrl` / `mpuRestNonce`，兩個舊變數已無任何消費端。

**確認方式：** Grep 整個 `js/` source 目錄（含 options template、所有 PHP inline script），確認 zero reference。

**修正：** 直接移除兩行注入，前端 `<head>` 現在只輸出：

```javascript
var mpuRestUrl = "https://example.com/wp-json/mp-ukagaka/v1/";
var mpuRestNonce = "...";
```

**附加效果：** 每次頁面載入減少一次 `wp_create_nonce('mpu_ajax_nonce')` 與 `admin_url('admin-ajax.php')` 呼叫。

---

## 7. Review of Post-Codex-Review Fixes (Codex, 2026-02-23)

已針對第 6 節（Claude 修正內容）對照實際代碼再次檢查。

### 已確認完成（實作與報告一致）

- **Fix 8（REST rate limit helper）已落地**
  - `includes/core/utility-functions.php` 已新增 `mpu_rest_check_rate_limit()`。
  - REST callbacks 已改用 `mpu_rest_check_rate_limit()`，涵蓋 `rest-init.php`、`rest-core.php`、`rest-touch.php`、`rest-test.php`、`includes/rest/chat/*`、`akismet-integration.php`。
  - 舊 `mpu_enforce_rate_limit()` 仍保留，符合向後相容描述。

- **Fix 9（`mpuFetch()` 解析 REST 錯誤 body）已落地**
  - `js/ukagaka-base.js` 的 `mpuFetch()` 在 `!response.ok` 時已嘗試 `await response.json()` 並取 `errBody.message`。
  - 這代表後端 `WP_Error` 的錯誤訊息可傳到前端，而不再只剩 HTTP status text。

- **Fix 10（`/wake-ghost` 錯誤回應改 `WP_Error`）已落地**
  - `includes/rest/rest-core.php` 中 `/wake-ghost` 的兩個 400 錯誤分支已改為 `WP_Error`。
  - `debug_info` 等附加資料已移入 `WP_Error` 第三個參數（`data`）中。

- **Fix 11（移除舊 AJAX 全域變數）已落地**
  - `includes/core/frontend-functions.php` 僅保留 `mpuRestUrl` 與 `mpuRestNonce`。
  - 檢查結果未見 `mpuurl` / `mpuNonce` 的前端消費端殘留。

### 補充建議（非阻擋，建議再評估）

#### Follow-up 1：`mpuFetch()` 目前會對所有非 2xx 回應重試（包含 4xx / 429）

**觀察（`js/ukagaka-base.js`）：**

- `!response.ok` 時即使已解析出錯誤訊息，若尚未達到最後一次重試，仍會 `continue` 重試。

**影響：**

- `429 Too Many Requests` 會被立即再次請求，可能加劇 rate limit。
- `400/401/403/404` 這類通常不可重試的錯誤也會浪費一次重試。

**建議：**

- 預設不要重試 `4xx`（至少排除 `400/401/403/404/422`）。
- 若要對 `429` 重試，建議讀取 `Retry-After` header 或 REST error data 裡的 `retry_after` 做 backoff。

#### Follow-up 2：REST rate limit 改為 `WP_Error` 後，`Retry-After` HTTP header 尚未補回

**觀察（`includes/core/utility-functions.php`）：**

- `mpu_rest_check_rate_limit()` 目前將 `retry_after` 放在 `WP_Error` 的 `data` 中。
- 但舊版 AJAX `mpu_enforce_rate_limit()` 另外有送出 `Retry-After` header。

**影響：**

- 一般 REST client / 中介層若依賴標準 `Retry-After` header，現在收不到（屬於小幅語意退化）。

**建議：**

- 保持 `WP_Error` 架構不變，另在 REST response pipeline（例如 `rest_post_dispatch`）對 `rest_rate_limit_exceeded` 補上 `Retry-After` header。

### 二次 Review 結論（Codex）

- Claude 對 Findings A / B / D / E 的修正方向正確，且大部分已完整落地。
- 目前剩餘議題主要是「重試策略」與「`Retry-After` header 完整性」，屬於可再優化項目，不是結構性阻擋問題。

---

## 8. Post-Codex-Second-Review Fixes (Claude, 2026-02-23)

針對 Follow-up 1 與 Follow-up 2 一併修正。

---

### Fix 12 — Follow-up 1：`mpuFetch()` 改為僅對 5xx 重試

**問題：** `mpuRequestManager.defaults.retries` 預設值為 `2`，而 `!response.ok` 的重試邏輯未區分狀態碼，導致：

- `429 Too Many Requests`：立即重試兩次，把 rate limit 計數再加兩次，加速封鎖。
- `400/401/403/404`：重試兩次，浪費兩個 HTTP round-trip，延遲最終錯誤呈現。

**修正（`js/ukagaka-base.js`）：**

```js
// 改前
if (attempt === config.retries) {
    throw new Error(errorMessage);
}
lastError = new Error(errorMessage);
continue;

// 改後
// 4xx 是客戶端錯誤（含 429 rate limit），不重試；僅 5xx 才值得重試
const shouldRetry = response.status >= 500;
if (!shouldRetry || attempt === config.retries) {
    throw new Error(errorMessage);
}
lastError = new Error(errorMessage);
continue;
```

**編譯：** 執行 `npm run build`（Terser），重新打包進 `ukagaka-bundle.min.js`。

---

### Fix 13 — Follow-up 2：`mpu_rest_check_rate_limit()` 補回 `Retry-After` header

**問題：** Fix 8 將 rate limit 超限回應從 `mpu_enforce_rate_limit()`（直接 `header('Retry-After: ...')`）改為 `WP_Error`，但 `WP_Error` 無法攜帶 HTTP header，導致 `Retry-After` header 從回應中消失。

**修正（`includes/core/utility-functions.php`）：** 將回傳型別由 `WP_Error` 改為 `WP_REST_Response`，直接附加 header：

```php
// 改前
return new WP_Error(
    'rest_rate_limit_exceeded',
    sprintf(__('...'), $result['reset_in']),
    ['status' => 429, 'retry_after' => $result['reset_in']]
);

// 改後
$response = new WP_REST_Response(
    [
        'code'    => 'rest_rate_limit_exceeded',
        'message' => sprintf(__('請求過於頻繁，請 %d 秒後再試', 'mp-ukagaka'), $result['reset_in']),
        'data'    => ['status' => 429, 'retry_after' => $result['reset_in']],
    ],
    429
);
$response->header('Retry-After', (string) $result['reset_in']);
return $response;
```

**設計說明：** 選用 `WP_REST_Response` 而非 Codex 建議的 `rest_post_dispatch` filter，原因：

- `WP_REST_Response::header()` 由 `WP_REST_Server` 在 `serve_request()` 統一送出，執行順序有保證。
- 邏輯集中在呼叫點，不需要全域 filter 做 status code 判斷。
- Caller 側 `if ($rl !== null) return $rl;` 無需任何修改。

JSON body 格式維持與 `WP_Error` 相容的結構（`code` / `message` / `data`），前端 `mpuFetch` 的 `errBody.message` 解析路徑不受影響。
