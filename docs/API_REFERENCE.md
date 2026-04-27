# MP Ukagaka API 參考

> 📚 完整的函數、Hooks、REST 端點參考（v2.13.7）

---

## 📑 目錄

1. [PHP 函數](#php-函數)
2. [WordPress Hooks](#wordpress-hooks)
3. [REST 端點](#rest-端點)
4. [JavaScript 函數](#javascript-函數)
5. [特殊代碼](#特殊代碼)

---

## PHP 函數

### 核心函數 (core-functions.php)

#### mpu_default_opt()

取得預設設定值。

```php
/**
 * @return array 預設設定陣列
 */
function mpu_default_opt()
```

**範例：**

```php
$defaults = mpu_default_opt();
echo $defaults['auto_talk_interval']; // 8
```

---

#### mpu_get_option()

取得外掛設定（帶快取）。

```php
/**
 * @return array 設定陣列
 */
function mpu_get_option()
```

**範例：**

```php
$mpu_opt = mpu_get_option();
if ($mpu_opt['ai_enabled']) {
    // AI 已啟用
}
```

---

#### mpu_count_total_msg()

計算所有春菜的總對話數。

```php
/**
 * @return int 總對話數
 */
function mpu_count_total_msg()
```

---

### 工具函數 (utility-functions.php)

#### mpu_array2str()

將陣列轉換為字串（用換行分隔）。

```php
/**
 * @param array $arr 輸入陣列
 * @return string 輸出字串
 */
function mpu_array2str($arr = [])
```

**範例：**

```php
$messages = ['對話1', '對話2', '對話3'];
$str = mpu_array2str($messages);
// 結果：
// 對話1
//
// 對話2
//
// 對話3
```

---

#### mpu_str2array()

將字串轉換為陣列（以空行分隔）。

```php
/**
 * @param string $str 輸入字串
 * @return array 輸出陣列
 */
function mpu_str2array($str = "")
```

**範例：**

```php
$str = "對話1\n\n對話2\n\n對話3";
$messages = mpu_str2array($str);
// 結果：['對話1', '對話2', '對話3']
```

---

#### mpu_output_filter()

HTML 輸出過濾。

```php
/**
 * @param string $str 輸入字串
 * @return string 過濾後字串
 */
function mpu_output_filter($str)
```

---

#### mpu_js_filter()

JavaScript 輸出過濾（跳脫引號和特殊字元）。

```php
/**
 * @param string $str 輸入字串
 * @return string 過濾後字串
 */
function mpu_js_filter($str)
```

---

#### mpu_input_filter()

輸入過濾（儲存前處理）。

```php
/**
 * @param string $str 輸入字串
 * @return string 過濾後字串
 */
function mpu_input_filter($str)
```

---

#### mpu_secure_file_read()

安全讀取檔案。

```php
/**
 * @param string $file_path 檔案路徑（必須位於 dialogs/ 目錄內）
 * @return string|WP_Error 檔案內容或錯誤
 */
function mpu_secure_file_read($file_path)
```

**範例：**

```php
$content = mpu_secure_file_read('/path/to/file.txt');
if (is_wp_error($content)) {
    echo $content->get_error_message();
} else {
    echo $content;
}
```

**可能的錯誤：**

| 錯誤代碼           | 說明               |
| ------------------ | ------------------ |
| `file_not_found`   | 找不到指定的文件   |
| `path_not_allowed` | 不允許讀取該路徑   |
| `file_too_large`   | 文件過大，無法讀取 |
| `read_failed`      | 無法讀取文件       |

---

#### mpu_secure_file_write()

安全寫入檔案。

```php
/**
 * @param string $file_path 檔案路徑
 * @param string $content 檔案內容
 * @return bool|WP_Error 成功或錯誤
 */
function mpu_secure_file_write($file_path, $content)
```

**可能的錯誤：**

| 錯誤代碼           | 說明             |
| ------------------ | ---------------- |
| `mkdir_failed`     | 無法創建目錄     |
| `path_not_allowed` | 不允許寫入該路徑 |
| `invalid_filename` | 不合法的文件名   |
| `write_failed`     | 無法寫入文件     |

---

#### mpu_encrypt_api_key()

使用 AES-256-CBC 加密 API Key。

```php
/**
 * @param string $api_key 原始 API Key
 * @return string 加密後的字串
 */
function mpu_encrypt_api_key($api_key)
```

---

#### mpu_decrypt_api_key()

解密 API Key。

```php
/**
 * @param string $encrypted 加密的字串
 * @return string 解密後的 API Key
 */
function mpu_decrypt_api_key($encrypted_key)
```

---

#### mpu_get_client_ip()

獲取客戶端真實 IP 地址（支援反向代理）。

```php
/**
 * @return string 客戶端 IP 地址
 */
function mpu_get_client_ip()
```

---

#### mpu_fetch_external_api()

通用外部 API 請求函數（含快取）。

```php
/**
 * @param string $cache_key 快取鍵
 * @param string $url API 端點 URL
 * @param int $cache_duration 快取時間（秒）
 * @param array $options 額外選項
 * @return array|string|null API 回應資料
 */
function mpu_fetch_external_api($cache_key, $url, $cache_duration = MPU_CACHE_DEFAULT, $options = [])
```

---

#### mpu_render_prompt_template()

渲染提示詞模板，替換 `{{變數名}}` 為實際值。

```php
/**
 * @param string $template 模板字串
 * @param array $variables 變數陣列
 * @return string 替換後的字串
 */
function mpu_render_prompt_template($template, $variables = [])
```

---

#### mpu_get_current_user_info()

獲取當前 WordPress 用戶資訊。

```php
/**
 * @return array 當前用戶資訊陣列
 */
function mpu_get_current_user_info()
```

---

#### mpu_get_wordpress_info()

獲取 WordPress 網站資訊（包含基本資訊和統計資訊）。

```php
/**
 * @return array WordPress 網站資訊陣列
 */
function mpu_get_wordpress_info()
```

---

#### mpu_get_provider_api_key()

獲取指定 AI 提供商的解密後 API Key。

```php
/**
 * @param string $provider AI 提供商名稱（gemini, openai, claude, ollama）
 * @param array|null $mpu_opt 選項陣列
 * @return string 解密後的 API Key
 */
function mpu_get_provider_api_key($provider, $mpu_opt = null)
```

---

#### mpu_get_current_provider()

獲取當前啟用的 AI 提供商名稱。

```php
/**
 * @param array|null $mpu_opt 選項陣列
 * @return string AI 提供商名稱
 */
function mpu_get_current_provider($mpu_opt = null)
```

---

### AI 函數 (ai-functions.php)

#### mpu_call_ai_api()

呼叫 AI API（自動選擇提供商）。支援 Gemini、OpenAI、Claude。

```php
/**
 * @param string $provider AI 提供商（'gemini'、'openai'、'claude'、'ollama'）
 * @param string $api_key API Key
 * @param string $system_prompt 系統提示（角色設定）
 * @param string $user_prompt 使用者提示
 * @param string $language 語言設定（'zh-TW'、'ja'、'en'）
 * @param array|null $mpu_opt 外掛設定（用於獲取模型名稱）
 * @param int|null $max_tokens 最大 token 數（可選）
 * @return string|WP_Error AI 回應或錯誤
 */
function mpu_call_ai_api(
    $provider,
    $api_key,
    $system_prompt,
    $user_prompt,
    $language = 'zh-TW',
    $mpu_opt = null,
    $max_tokens = null
)
```

**範例：**

```php
$response = mpu_call_ai_api(
    'gemini',
    $api_key,
    '你是一個友善的助手，回應請保持簡短。',
    '這篇文章講了什麼？',
    'zh-TW',
    $mpu_opt
);
if (!is_wp_error($response)) {
    echo $response;
}
```

---

#### mpu_get_language_instruction()

取得語言指令字串。

```php
/**
 * @param string $language 語言代碼 (zh-TW, ja, en)
 * @return string 語言指令
 */
function mpu_get_language_instruction($language)
```

**返回值：**

| 語言代碼 | 返回值                       |
| -------- | ---------------------------- |
| `zh-TW`  | `請用繁體中文回覆。`         |
| `ja`     | `日本語で返答してください。` |
| `en`     | `Please reply in English.`   |

---

#### mpu_call_gemini_api()

呼叫 Google Gemini API。

```php
/**
 * @param string $api_key Gemini API Key
 * @param string $model 模型名稱（如 'gemini-2.5-flash'）
 * @param string $system_prompt 系統提示
 * @param string $user_prompt 使用者提示
 * @param string $language 語言設定
 * @param int|null $max_tokens 最大 token 數（可選）
 * @return string|WP_Error AI 回應或錯誤
 */
function mpu_call_gemini_api(
    $api_key,
    $model,
    $system_prompt,
    $user_prompt,
    $language = 'zh-TW',
    $max_tokens = null
)
```

---

#### mpu_call_openai_api()

呼叫 OpenAI API。

```php
/**
 * @param string $api_key OpenAI API Key
 * @param string $model 模型名稱（如 'gpt-4o-mini'）
 * @param string $system_prompt 系統提示
 * @param string $user_prompt 使用者提示
 * @param string $language 語言設定
 * @param int|null $max_tokens 最大 token 數（可選）
 * @return string|WP_Error AI 回應或錯誤
 */
function mpu_call_openai_api(
    $api_key,
    $model,
    $system_prompt,
    $user_prompt,
    $language = 'zh-TW',
    $max_tokens = null
)
```

---

#### mpu_call_claude_api()

呼叫 Anthropic Claude API。

```php
/**
 * @param string $api_key Claude API Key
 * @param string $model 模型名稱（如 'claude-sonnet-4-5-20250929'）
 * @param string $system_prompt 系統提示
 * @param string $user_prompt 使用者提示
 * @param string $language 語言設定
 * @param int|null $max_tokens 最大 token 數（可選）
 * @return string|WP_Error AI 回應或錯誤
 */
function mpu_call_claude_api(
    $api_key,
    $model,
    $system_prompt,
    $user_prompt,
    $language = 'zh-TW',
    $max_tokens = null
)
```

---

#### mpu_call_ollama_api()

呼叫 Ollama API（本機或遠程）。

```php
/**
 * @param string $endpoint Ollama 端點 URL
 * @param string $model 模型名稱（如 'qwen3:8b'）
 * @param string $system_prompt 系統提示
 * @param string $user_prompt 使用者提示
 * @param string $language 語言設定
 * @param int|null $max_tokens 最大 token 數（可選）
 * @return string|WP_Error AI 回應或錯誤
 */
function mpu_call_ollama_api(
    $endpoint,
    $model,
    $system_prompt,
    $user_prompt,
    $language = 'zh-TW',
    $max_tokens = null
)
```

**功能特點：**

- 自動檢測本地/遠程連接
- 根據連接類型調整超時時間
- 支援關閉思考模式（Qwen3、DeepSeek 等模型）

---

### API 快取函數 (api-cache.php)

> 💡 **v2.5.6 新增**：API 快取系統，使用 WordPress Transient API 快取 AI API 回應，減少重複請求和費用。

#### mpu_is_api_cache_enabled()

檢查 API 快取是否啟用。

```php
/**
 * @return bool
 */
function mpu_is_api_cache_enabled()
```

---

#### mpu_get_api_cache_ttl()

取得快取 TTL（秒）。

```php
/**
 * @return int 預設 3600 秒（1 小時），範圍 300-86400 秒
 */
function mpu_get_api_cache_ttl()
```

---

#### mpu_generate_cache_key()

生成快取鍵。

```php
/**
 * @param string $provider 提供商
 * @param string $system_prompt 系統提示詞
 * @param string $user_prompt 用戶提示詞
 * @return string 快取鍵
 */
function mpu_generate_cache_key($provider, $system_prompt, $user_prompt)
```

---

#### mpu_get_cached_api_response()

從快取取得 API 回應。

```php
/**
 * @param string $cache_key 快取鍵
 * @return string|false 快取的回應或 false
 */
function mpu_get_cached_api_response($cache_key)
```

---

#### mpu_set_cached_api_response()

將 API 回應存入快取。

```php
/**
 * @param string $cache_key 快取鍵
 * @param string $response API 回應
 * @return bool
 */
function mpu_set_cached_api_response($cache_key, $response)
```

---

#### mpu_clear_all_api_cache()

清除所有 LLM API 快取。

```php
/**
 * @return int 清除的快取數量
 */
function mpu_clear_all_api_cache()
```

---

#### mpu_get_api_cache_stats()

取得 API 快取統計。

```php
/**
 * @return array ['count' => int, 'ttl' => int, 'enabled' => bool]
 */
function mpu_get_api_cache_stats()
```

---

### LLM 功能函數 (llm-functions.php)

> 💡 **2.2.0 更新**：LLM 功能已升級為**通用 LLM 接口**，支援 Ollama、Gemini、OpenAI、Claude 四大 AI 服務。

#### mpu_is_remote_endpoint()

檢測端點是否為遠程連接。

```php
/**
 * @param string $endpoint Ollama 端點 URL
 * @return bool 是否為遠程連接（true = 遠程，false = 本地）
 */
function mpu_is_remote_endpoint($endpoint)
```

**範例：**

```php
$is_remote = mpu_is_remote_endpoint('https://your-domain.com'); // true
$is_local = mpu_is_remote_endpoint('http://localhost:11434');  // false
```

---

#### mpu_get_ollama_timeout()

根據端點類型和操作類型獲取適當的超時時間。

```php
/**
 * @param string $endpoint Ollama 端點 URL
 * @param string $operation_type 操作類型：'check'（服務檢查）、'api_call'（API 調用）、'test'（測試連接）
 * @return int 超時時間（秒）
 */
function mpu_get_ollama_timeout($endpoint, $operation_type = 'api_call')
```

**範例：**

```php
$timeout = mpu_get_ollama_timeout('https://your-domain.com', 'api_call'); // 90
$timeout = mpu_get_ollama_timeout('http://localhost:11434', 'check');      // 3
```

---

#### mpu_validate_ollama_endpoint()

驗證和標準化 Ollama 端點 URL。

```php
/**
 * @param string $endpoint 原始端點 URL
 * @return string|WP_Error 標準化後的 URL 或錯誤
 */
function mpu_validate_ollama_endpoint($endpoint)
```

**範例：**

```php
$validated = mpu_validate_ollama_endpoint('https://your-domain.com');
if (is_wp_error($validated)) {
    echo $validated->get_error_message();
} else {
    echo $validated; // 'https://your-domain.com'
}
```

---

#### mpu_check_ollama_available()

檢查 Ollama 服務是否可用（快速檢查，使用緩存）。

```php
/**
 * @param string $endpoint Ollama 端點
 * @param string $model 模型名稱
 * @return bool 服務是否可用
 */
function mpu_check_ollama_available($endpoint, $model)
```

**範例：**

```php
if (mpu_check_ollama_available('https://your-domain.com', 'qwen3:8b')) {
    // 服務可用
}
```

---

#### mpu_generate_llm_dialogue()

使用 LLM 生成隨機對話（取代內建對話）。支援所有 AI 提供商（Ollama、Gemini、OpenAI、Claude）。

```php
/**
 * @param string $ukagaka_name 春菜名稱
 * @param string $last_response 上一次 AI 的回應（用於避免重複對話）
 * @param array $response_history 回應歷史陣列（最近幾次回應，用於更嚴格的重複檢測）
 * @param int $last_visit_hours 最後訪問距今的小時數（預設 -1 表示無資料，v2.5.6 新增）
 * @return string|false 生成的對話內容，失敗時返回 false
 */
function mpu_generate_llm_dialogue($ukagaka_name = 'default_1', $last_response = '', $response_history = [], $last_visit_hours = -1)
```

**範例：**

```php
$dialogue = mpu_generate_llm_dialogue('frieren');
if ($dialogue !== false) {
    echo $dialogue;
}

// 帶重複檢測
$dialogue = mpu_generate_llm_dialogue('frieren', '上次的回應', ['回應1', '回應2']);
```

**功能特點：**

- 自動使用優化的 XML 結構化 System Prompt
- 支援防止重複對話機制（相似度檢測）
- 自動整合 WordPress 資訊、用戶資訊、訪客資訊
- 支援 70+ 個芙莉蓮風格對話範例

**可用的 Filter Hooks（v2.5.7）：**

| Filter                  | 說明                       | 參數                                                      |
| ----------------------- | -------------------------- | --------------------------------------------------------- |
| `mpu_llm_system_prompt` | 修改 System Prompt         | `$prompt`, `$ukagaka_name`, `$personality_id`, `$context` |
| `mpu_llm_user_prompt`   | 在會話指示前注入額外上下文 | `$prompt`, `$ukagaka_name`, `$personality_id`             |

**使用範例（安全警報整合）：**

```php
add_filter('mpu_llm_user_prompt', function($prompt, $ukagaka_name, $personality_id) {
    $attack_info = get_transient('mpu_llar_attack_info');
    if ($attack_info) {
        return $prompt . "\n【安全警報】\n" . $attack_info;
    }
    return $prompt;
}, 10, 3);
```

---

#### mpu_is_llm_replace_dialogue_enabled()

檢查是否啟用了 LLM 取代內建對話。

```php
/**
 * @return bool
 */
function mpu_is_llm_replace_dialogue_enabled()
```

---

#### mpu_get_ollama_settings()

獲取 Ollama 設定。

```php
/**
 * @return array|false 設定陣列，未啟用時返回 false
 */
function mpu_get_ollama_settings()
```

**返回值：**

```php
[
    'endpoint' => 'http://localhost:11434',
    'model' => 'qwen3:8b',
    'replace_dialogue' => true,
]
```

---

#### mpu_get_visitor_info_for_llm()

獲取訪客資訊（用於 LLM 對話生成）。整合 Slimstat 資料，包含 BOT 檢測和地理位置資訊。

```php
/**
 * @return array 訪客資訊陣列
 */
function mpu_get_visitor_info_for_llm()
```

**返回值：**

```php
[
    'is_bot' => false,                    // 是否為 BOT
    'browser_type' => 0,                  // 瀏覽器類型（0=一般, 1=BOT, 2=行動裝置）
    'browser_name' => 'Chrome',            // 瀏覽器名稱（BOT 名稱）
    'slimstat_enabled' => true,            // 是否啟用 Slimstat
    'slimstat_country' => 'TW',            // 國家代碼
    'slimstat_city' => 'Taipei',           // 城市名稱
]
```

---

#### mpu_get_visitor_status_text()

獲取訪客狀態文字（BOT 或地理位置）。

```php
/**
 * @param array $visitor_info 訪客資訊
 * @return string 訪客狀態描述
 */
function mpu_get_visitor_status_text($visitor_info)
```

**範例：**

```php
$visitor_info = mpu_get_visitor_info_for_llm();
$status = mpu_get_visitor_status_text($visitor_info);
// 可能返回：'🤖 BOT: Googlebot' 或 '來自 TW / Taipei'
```

---

#### mpu_compress_context_info()

壓縮 WordPress、用戶、訪客資訊為緊湊的 XML 格式（用於 System Prompt）。

```php
/**
 * @param array $wp_info WordPress 資訊
 * @param array $user_info 用戶資訊
 * @param array $visitor_info 訪客資訊
 * @return string 壓縮後的 XML 格式字串
 */
function mpu_compress_context_info($wp_info, $user_info, $visitor_info)
```

---

#### mpu_weighted_random_select()

根據權重陣列，從類別陣列中隨機選擇一個類別（加權隨機選擇）。

```php
/**
 * @param array $categories 類別陣列（key => value）
 * @param array $weights 權重陣列（key => weight），數值越高被選中的機率越大
 * @return string 選中的類別 key
 */
function mpu_weighted_random_select($categories, $weights)
```

**使用範例：**

```php
$categories = [
    'greeting' => ['問候1', '問候2'],
    'casual' => ['閒聊1', '閒聊2'],
    'tech_observation' => ['技術1', '技術2'],
];

$weights = [
    'greeting' => 10,
    'casual' => 10,
    'tech_observation' => 3,  // 降低技術觀察類的權重
];

$selected = mpu_weighted_random_select($categories, $weights);
// 可能返回：'greeting'、'casual' 或 'tech_observation'
// tech_observation 被選中的機率約為其他類別的 30%
```

**注意事項：**

- 如果類別沒有在權重陣列中設定，預設權重為 5
- 如果總權重為 0，會使用均勻隨機選擇（`array_rand()`）
- 權重數值越高，被選中的機率越大

---

#### mpu_build_optimized_system_prompt()

建構優化後的 System Prompt（XML 結構化版本）。

```php
/**
 * @param array $mpu_opt 外掛設定
 * @param array $wp_info WordPress 資訊
 * @param array $user_info 用戶資訊
 * @param array $visitor_info 訪客資訊
 * @param string $ukagaka_name 春菜名稱
 * @param string $time_context 時間情境（早上/下午/晚上/深夜）
 * @param string $language 語言設定
 * @return string 優化後的 system prompt
 */
function mpu_build_optimized_system_prompt(
    $mpu_opt,
    $wp_info,
    $user_info,
    $visitor_info,
    $ukagaka_name,
    $time_context,
    $language
)
```

**返回的 XML 結構：**

```xml
<character>
名稱：{角色名稱}
核心設定：{來自後台的 System Prompt}
風格特徵：...
</character>
<knowledge_base>
{壓縮後的上下文資訊}
</knowledge_base>
<behavior_rules>
  <must_do>...</must_do>
  <should_do>...</should_do>
  <must_not_do>...</must_not_do>
</behavior_rules>
<response_style_examples>
{70+ 個對話範例}
</response_style_examples>
<current_context>
時間：{時間情境}
語言：{語言設定}
</current_context>
```

---

#### mpu_calculate_text_similarity()

計算兩個文字的相似度（用於防止重複對話）。

```php
/**
 * @param string $text1              第一個文字
 * @param string $text2              第二個文字
 * @param bool   $text1_normalized   $text1 是否已經過正規化（減少重複工作）
 * @return float 相似度（0.0-1.0）
 */
function mpu_calculate_text_similarity($text1, $text2, $text1_normalized = false)
```

**範例：**

```php
$similarity = mpu_calculate_text_similarity('また来たのね。', 'また来たのね。');
// 返回：1.0（完全相同）

$similarity = mpu_calculate_text_similarity('また来たのね。', '久しぶり。');
// 返回：0.0（完全不同）
```

---

#### mpu_debug_system_prompt()

Debug 模式：輸出 System Prompt 到 WordPress debug log。

```php
/**
 * @param string $system_prompt System Prompt 內容
 * @return void
 */
function mpu_debug_system_prompt($system_prompt)
```

**使用條件：**

- 僅在 `WP_DEBUG` 為 `true` 時輸出
- 輸出到 `wp-content/debug.log`
- 包含 System Prompt 內容、估計 token 數、字元長度

---

### Prompt 類別管理 (prompt-categories.php)

> 💡 **v2.2.0 新增**：Prompt 類別指令管理模組，用於 LLM 對話生成時的類別指令和動態權重配置。

#### mpu_get_static_prompt_categories()

獲取靜態類別指令（使用快取避免重複建構）。

```php
/**
 * @param string|null $personality_id 人格 ID（可選，預設為當前人格）
 * @return array 靜態類別指令陣列
 */
function mpu_get_static_prompt_categories($personality_id = null)
```

---

#### mpu_add_statistics_prompts()

添加動態統計類別指令。根據 WordPress 統計資訊生成對話指令。

```php
/**
 * @param array       &$categories     類別陣列（引用傳遞）
 * @param array        $wp_info        WordPress 資訊
 * @param string|null  $personality_id 人格 ID（可選，預設為當前人格）
 * @return void
 */
function mpu_add_statistics_prompts(&$categories, $wp_info, $personality_id = null)
```

---

#### mpu_build_prompt_categories()

建構 User Prompt 的類別指令。此函數生成不同類別的對話指令，用於「使用 LLM 取代內建對話」功能。

```php
/**
 * @param array $wp_info WordPress 資訊
 * @param array $visitor_info 訪客資訊
 * @param string $time_context 時間情境
 * @param string $theme_name 主題名稱
 * @param string $theme_version 主題版本
 * @param string $theme_author 主題作者
 * @return array 類別指令陣列
 */
function mpu_build_prompt_categories(
    $wp_info,
    $visitor_info,
    $time_context,
    $theme_name,
    $theme_version,
    $theme_author
)
```

**返回值結構：**

```php
[
    'greeting' => ['問候類の会話例を参考に、軽く挨拶する', ...],
    'casual' => ['閒聊類の会話例を参考に、淡々とした日常の言葉を言う', ...],
    'time_aware' => ['時間感知類の会話例を参考に、時間感覚を表現する', ...],
    // ... 35 個類別
]
```

---

#### mpu_get_dynamic_category_weights()

獲取動態類別權重配置。根據時間情境、訪客資訊調整各類別的權重。

```php
/**
 * @param string $time_context 時間情境
 * @param array $visitor_info 訪客資訊
 * @param array $context_vars 上下文變數（可選）
 * @return array 權重陣列
 */
function mpu_get_dynamic_category_weights(
    $time_context,
    $visitor_info,
    $context_vars = [],
    $personality_id = null
)
```

**特殊調整邏輯：**

- 深夜：`silence`、`philosophical`、`party_memories` 權重提升
- 早上：`daily_life` 權重提升（因為角色朝弱い）
- BOT 訪客：`bot_detection` 類別權重大幅提升

---

#### mpu_get_decoration_prompt()

獲取裝飾品點擊對話的提示詞。當用戶點擊裝飾物時，返回對應的 User Prompt 指令。

```php
/**
 * @param string      $decoration_type 裝飾物類型（suitcase, evil_horns, staff, books）
 * @param string|null $personality_id  人格 ID（可選，預設為當前人格）
 * @return string|false 提示詞，若未找到則返回 false
 */
function mpu_get_decoration_prompt($decoration_type, $personality_id = null)
```

**支援的裝飾物類型：**

| 類型         | 說明                 |
| ------------ | -------------------- |
| `suitcase`   | 行李箱（魔法收藏箱） |
| `evil_horns` | 惡魔角飾             |
| `staff`      | 魔法杖               |
| `books`      | 魔導書               |

---

### 春菜函數 (ukagaka-functions.php)

#### mpu_get_shell()

取得指定角色的 shell 圖片 URL。

```php
/**
 * @param string|false $num   角色鍵值；false 表示使用當前角色
 * @param bool         $echo  是否直接輸出（預設 false，回傳字串）
 * @return string 圖片 URL
 */
function mpu_get_shell($num = false, $echo = false)
```

---

#### mpu_get_msg_arr()

取得指定角色的訊息陣列結構（含 `msgall`、`auto_msg`、`msg` 等鍵）。

```php
/**
 * @param string $num 角色鍵值（如 'default_1'、'frieren'）
 * @return array 訊息陣列
 */
function mpu_get_msg_arr($num)
```

---

#### mpu_msg_code()

處理訊息陣列中的特殊代碼（`:recentpost[n]:`、`:commenters[n]:` 等），替換為實際 HTML。

```php
/**
 * @param array $msglist 訊息陣列
 * @return array 處理後的訊息陣列
 */
function mpu_msg_code($msglist = [])
```

---

#### mpu_get_msg_from_file()

載入 `dialogs/` 目錄下的對話檔案（自動判別 `.txt` / `.json` 格式）。

```php
/**
 * @param string $filename_base 檔案名稱（不含副檔名）
 * @return array 對話陣列
 */
function mpu_get_msg_from_file($filename_base)
```

**範例：**

```php
$messages = mpu_get_msg_from_file('frieren');
```

---

### 前端函數 (frontend-functions.php)

#### mpu_html()

生成並輸出春菜 HTML。

```php
/**
 * @param string|false $num 角色鍵值；false 表示使用當前角色
 * @return void
 */
function mpu_html($num = false)
```

---

### 後台函數 (admin-functions.php)

#### mpu_generate_dialog_file()

將訊息陣列寫出為對話檔案（`.txt` 或 `.json`）。

```php
/**
 * @param string $filename  檔案名稱（不含副檔名）
 * @param array  $msg_array 訊息陣列
 * @param string $ext       副檔名（'txt' 或 'json'）
 * @return bool 是否成功
 */
function mpu_generate_dialog_file($filename, $msg_array, $ext)
```

---

## WordPress Hooks

> 📌 自 v2.9.2 REST 重構起，已移除所有外掛層級的 `do_action()` hook（`mpu_loaded`、`mpu_before_html`、`mpu_after_html`、`mpu_settings_saved`）以及 `apply_filters()` hook（`mpu_options`、`mpu_messages`、`mpu_ai_response`、`mpu_ukagaka_html`）。目前僅保留與 LLM 提示詞建構相關的 4 個 filter。

### Filters

#### mpu_llm_system_prompt

過濾 LLM 系統提示詞（含人格卡、WordPress 上下文、行為規則等完整 XML 結構化內容）。

```php
add_filter('mpu_llm_system_prompt', function($prompt, $ukagaka_name, $personality_id, $context) {
    return $prompt;
}, 10, 4);
```

---

#### mpu_llm_user_prompt

過濾用戶對話提示詞（在會話指示前注入額外上下文，例如安全警報、活動訊息）。

```php
add_filter('mpu_llm_user_prompt', function($prompt, $ukagaka_name, $personality_id) {
    $attack_info = get_transient('mpu_llar_attack_info');
    if ($attack_info) {
        return $prompt . "\n【安全警報】\n" . $attack_info;
    }
    return $prompt;
}, 10, 3);
```

---

#### mpu_prompt_categories

過濾 LLM 自動對話的類別定義（問候、閒聊、時間感知、統計觀察等 35+ 類別）。

```php
add_filter('mpu_prompt_categories', function($categories, $wp_info, $visitor_info, $time_context) {
    return $categories;
}, 10, 4);
```

---

#### mpu_category_weights

過濾對話類別的加權隨機權重配置。權重數值越高越容易被選中；預設為 5。

```php
add_filter('mpu_category_weights', function($weights, $time_context, $visitor_info, $context_vars) {
    // 深夜時提升沉思類對話機率
    if ($time_context === '深夜') {
        $weights['philosophical'] = 15;
    }
    return $weights;
}, 10, 4);
```

---

## REST 端點

> 💡 **v2.9.2 起**：外掛的端點架構全面從 AJAX 遷移到 REST API，並統一套用速率限制與錯誤處理。前端呼叫時需帶 `X-WP-Nonce`（由 `wp_localize_script` 透過 `mpuRestNonce` 提供）。

### 基礎資訊

- **Namespace**：`/wp-json/mp-ukagaka/v1`
- **權限**：多數端點公開（`__return_true`），僅測試/快取管理端點限管理員。
- **速率限制**：每端點獨立計數，超限時回傳 HTTP 429。
- **回應格式**：除 `/chat/user-stream`（SSE）外，一律為 JSON；結構為 `{ success, data, ... }` 或 `WP_Error`。

### 角色 / 設定類

| 端點 | 方法 | 權限 | 參數（全部為可選） | Rate Limit | 說明 |
| --- | --- | --- | --- | --- | --- |
| `/init` | GET | 公開 | `ukagaka_num` | 30/60s | 一次取得 shell、裝飾、表情、touchzone 與 settings 的統一初始化端點 |
| `/settings` | GET | 公開 | — | 30/60s | 取得前端使用的設定物件（`auto_talk`、`typewriter_speed`、`ai_*` 等） |
| `/change` | POST | 公開 | `mpu_num` | 10/60s | 無參數時回傳可切換的春菜列表；有參數時切換角色（同時 Set-Cookie） |
| `/shell-info` | GET / POST | 公開 | `ukagaka_num` | 30/60s | 取得指定角色的外觀資訊 |
| `/decoration-config` | GET / POST | 公開 | — | 30/60s | 取得裝飾物基礎 URL、設定、touchzone、可見旗標 |
| `/emoji-config` | GET / POST | 公開 | — | 30/60s | 取得表情符號基礎 URL、支援清單與關鍵字對應 |
| `/extend` | GET / POST | 公開 | — | 10/60s | 角色擴充標籤位置（保留端點） |

### 對話類

| 端點 | 方法 | 權限 | 參數 | Rate Limit | 說明 |
| --- | --- | --- | --- | --- | --- |
| `/nextmsg` | POST | 公開 | `cur_num`, `cur_msgnum`, `last_response`, `response_history`, `last_visit_hours`, `session_id`, `history` | 20/60s | 自動對話輪播：LLM 取代模式會呼叫 AI 生成，否則從內建對話抽取 |
| `/dialog` | GET / POST | 公開 | `file`（必填） | 30/60s | 讀取 `dialogs/` 下的對話檔；回傳 `{msgall, auto_msg, msg, next_msg, default_msg}` |
| `/visitor-info` | GET | 公開 | — | 30/60s | 回傳 referrer、search engine、Slimstat 國家／城市等訪客資訊 |
| `/decoration-prompts` | GET / POST | 公開 | `decoration_type` | 20/60s | 取得裝飾物點擊對話的提示詞 |
| `/wake-ghost` | POST | 公開 | `personality_id` 或 `ukagaka_num`（至少一個） | 10/60s | 暫時喚醒處於睡眠模式的角色；WP_Error codes：`rest_wake_ghost_missing_param`、`rest_wake_ghost_unavailable` |

### AI 對話類（Chat）

| 端點 | 方法 | 權限 | 參數 | Rate Limit | 說明 |
| --- | --- | --- | --- | --- | --- |
| `/chat/context` | POST | 公開 | `page_title`, `page_content`, `publish_date`, `session_id`, `history` | 5/60s | 頁面感知對話，依當前文章內容觸發 AI 評論；最大 500 字 |
| `/chat/greet` | POST | 公開 | `referrer`, `referrer_host`, `search_engine`, `is_direct`, `country`, `city`, `session_id`, `history` | 10/60s | 首訪訪客打招呼，根據來源國家／搜尋引擎客製 |
| `/chat/user` | POST | 公開 | `message`（必填）、`history`, `page_title`, `page_content`, `session_id` | 30/60s | 多輪互動對話（非串流），支援 MCP Tool/Abilities 呼叫；回傳 `{msg, emoji}` |
| `/chat/user-stream` | POST | 公開 | 同 `/chat/user` | 30/60s | SSE 串流版本，Provider 支援時逐字輸出 |

### 觸摸互動類

| 端點 | 方法 | 權限 | 參數 | Rate Limit | 說明 |
| --- | --- | --- | --- | --- | --- |
| `/touch/decoration` | POST | 公開 | `decoration_type`（必填） | 20/60s | 點擊裝飾物時觸發的 AI 反應；回傳 `{msg, emoji}` |
| `/touch/zone` | POST | 公開 | `touch_zone`（必填） | 20/60s | 點擊角色身體區塊的撫摸反應；回傳 `{msg, emoji, zone}` |

### 後台測試與管理類（限管理員）

| 端點 | 方法 | 權限 | 參數 | Rate Limit | 說明 |
| --- | --- | --- | --- | --- | --- |
| `/test-connection/{provider}` | POST | 管理員 | `provider`（路徑參數：gemini／openai／claude／ollama／weather）、`api_key`, `model`, `endpoint`；weather 另接 `latitude`, `longitude` | 10/60s | 統一的 Provider 連線測試端點 |
| `/clear-cache` | POST | 管理員 | — | 10/60s | 清除 LLM API 回應快取 |

---

### `/chat/user-stream` SSE 事件格式

v2.12.x 起，互動對話支援 Server-Sent Events。串流以固定順序發送事件，使用 `text/event-stream` 格式：

| 事件 | 發送時機 | data 內容 |
| --- | --- | --- |
| `start` | 串流啟動 | `{"provider": "gemini", "model": "gemini-2.5-flash"}` |
| `nonce` | `start` 之後立即發送 | `{"new_token": "<nonce>", "new_nonce": "<nonce>"}` — 提供下一次請求用的新 nonce |
| `delta` | AI 逐字產生時（多次觸發） | `{"text": "是"}` — 單個 token／片段 |
| `done` | 串流結束 | `{"msg": "完整訊息", "emoji": "smile"}` — 經長度截斷與 emoji 分析後的最終結果 |
| `error` | Provider 不支援串流、或中途發生錯誤 | `{"message": "<error_message>"}` |

**原始串流範例**：

```
event: start
data: {"provider":"gemini","model":"gemini-2.5-flash"}

event: nonce
data: {"new_token":"a1b2c3","new_nonce":"a1b2c3"}

event: delta
data: {"text":"今日"}

event: delta
data: {"text":"はいい天気"}

event: delta
data: {"text":"ですね。"}

event: done
data: {"msg":"今日はいい天気ですね。","emoji":"happy"}
```

---

### 仍保留的 AJAX 端點

少數非對話類動作仍以 `admin-ajax.php` 執行，主要屬於內部整合：

| Action | Handler | 說明 |
| --- | --- | --- |
| `wp_ajax_mpu_test_diary_generate` | `mpu_ajax_test_diary_generate` | 後台手動觸發日記生成測試（限管理員） |
| `wp_ajax_nopriv_slimtrack` / `wp_ajax_slimtrack` | `mpu_bb_intercept_slimstat` | Bot Blocker 攔截 Slimstat 追蹤（priority 0） |
| `wp_ajax_nopriv_mbb_js_flag` / `wp_ajax_mbb_js_flag` | `mpu_bb_js_flag_handler` | Bot Blocker 的 JS 執行旗標偵測 |

> 📌 舊版（v2.9.x 以前）的 `mpu_nextmsg`、`mpu_change`、`mpu_chat_*`、`mpu_test_*_connection`、`mpu_load_dialog`、`mpu_get_visitor_info`、`mpu_wake_ghost`、`mpu_init`、`mpu_get_settings`、`mpu_clear_api_cache`、`mpu_check_spam_event` 等 AJAX action 皆已移除，請改用上表對應的 REST 端點。

## JavaScript 函數

### 核心函數

#### mpu_nextmsg(trigger)

顯示下一條訊息。

```javascript
/**
 * @param {string} trigger - 'next' 順序 / 'random' 隨機 / '' 使用設定值
 */
mpu_nextmsg("next");
```

---

#### mpu_hidemsg()

隱藏對話框。

```javascript
mpu_hidemsg();
```

---

#### mpu_showmsg()

顯示對話框。

```javascript
mpu_showmsg();
```

---

#### mpu_hiderobot()

隱藏春菜。

```javascript
mpu_hiderobot();
```

---

#### mpu_showrobot()

顯示春菜。

```javascript
mpu_showrobot();
```

---

#### mpuChange(num)

開啟春菜切換選單，或在帶參數時直接切換到指定角色。

```javascript
/**
 * @param {string} [num] - 目標角色鍵值；省略則開啟選單
 */
mpuChange();            // 開啟選單
mpuChange("default_2"); // 直接切換
```

---

### 全域變數

前端載入時，透過 `wp_localize_script` 與 `/init` 端點回傳的資料，會寫入以下 `window` 全域：

| 變數 | 來源 | 說明 |
| --- | --- | --- |
| `window.mpuRestUrl` | `wp_localize_script` | REST 基礎 URL（例：`/wp-json/mp-ukagaka/v1/`） |
| `window.mpuRestNonce` | `wp_localize_script` | REST 請求用的 `X-WP-Nonce` |
| `window.mpuL10n` | `wp_localize_script` | 前端顯示用的翻譯字串集 |
| `window.mpuSettings` | `/init` 回傳 | 角色行為設定物件（見下方） |
| `window.mpuInitData` | `/init` 回傳 | 完整 init 回應原物件 |
| `window.mpuPersonalityId` | `/init` 回傳 | 當前人格 ID |
| `window.mpuCanvasManager` | `ukagaka-anime.js` | Canvas 動畫管理器 |
| `window.mpuChatHistory` | `ukagaka-chat.js` | 多輪對話歷史陣列（最多 40 筆） |
| `window.mpuChatModeActive` | `ukagaka-chat.js` | 互動對話模式旗標 |
| `window.mpuDecorationsBaseUrl` / `mpuDecorationConfig` / `mpuTouchZones` / `mpuShowDecorations` | `/init` 回傳 | 裝飾物與 touchzone 相關資訊 |
| `window.mpuEmojiBaseUrl` / `mpuSupportedEmojis` / `mpuEmojiMappings` | `/init` 回傳 | 表情符號系統相關資料 |

#### window.mpuSettings

由 `/wp-json/mp-ukagaka/v1/init` 回傳的 `settings` 區塊填入：

```javascript
window.mpuSettings = {
  auto_talk: true,
  auto_talk_interval: 8,            // 秒
  typewriter_speed: 40,             // 毫秒／字
  ai_enabled: true,
  ai_probability: 10,               // 0-100，AI 觸發機率
  ai_trigger_pages: "is_single",    // 頁面類型條件
  ai_text_color: "#000000",
  ai_display_duration: 8,           // 秒
  ai_greet_first_visit: true,
  ollama_replace_dialogue: false,   // 是否以 LLM 取代內建對話
  enable_chat_mode: false,          // 是否啟用互動對話模式
  sleep_mode: {
    enabled: false,
    frequency_multiplier: 1.0
  }
};
```

---

## 特殊代碼

在對話內容中可使用以下特殊代碼，由 `mpu_msg_code()` 於伺服器端處理後再送到前端。支援兩種格式：`:code[n]:` 或 `(:code[n]:)`（括號內含）。

### :recentpost[n]: / :recentposts[n]:

顯示最近 n 篇文章列表。單數形為逐行列出，複數形（`recentposts`）為以 `<br>` 串接。

```
最近的文章：:recentpost[5]:
```

---

### :randompost[n]: / :randomposts[n]:

顯示隨機 n 篇文章列表。單數形為逐行列出，複數形為以 `<br>` 串接。

```
推薦閱讀：:randompost[3]:
```

---

### :commenters[n]:

顯示最近 n 位不重複留言者（以頓號分隔）。

```
感謝留言：:commenters[5]:
```

---

**📌 注意：** 以上為 `mpu_msg_code()` 實際支援的全部代碼。舊版文件中提到的 `:date:`、`:time:`、`:sitename:` **目前並未實作**；若需要這類變數替換，請改用 `mpu_render_prompt_template()` 處理 `{{variable}}` 佔位符。

---

**文檔版本：2.13.7**
