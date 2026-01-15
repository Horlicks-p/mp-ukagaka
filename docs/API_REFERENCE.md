# MP Ukagaka API 參考

> 📚 完整的函數、Hooks、AJAX 端點參考

---

## 📑 目錄

1. [PHP 函數](#php-函數)
2. [WordPress Hooks](#wordpress-hooks)
3. [AJAX 端點](#ajax-端點)
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
function mpu_default_opt(): array
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
function mpu_get_option(): array
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
function mpu_count_total_msg(): int
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
function mpu_array2str(array $arr): string
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
function mpu_str2array(string $str): array
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
function mpu_output_filter(string $str): string
```

---

#### mpu_js_filter()

JavaScript 輸出過濾（跳脫引號和特殊字元）。

```php
/**
 * @param string $str 輸入字串
 * @return string 過濾後字串
 */
function mpu_js_filter(string $str): string
```

---

#### mpu_input_filter()

輸入過濾（儲存前處理）。

```php
/**
 * @param string $str 輸入字串
 * @return string 過濾後字串
 */
function mpu_input_filter(string $str): string
```

---

#### mpu_secure_file_read()

安全讀取檔案。

```php
/**
 * @param string $file_path 檔案路徑
 * @param int $max_size 最大檔案大小（預設 2MB）
 * @return string|WP_Error 檔案內容或錯誤
 */
function mpu_secure_file_read(string $file_path, int $max_size = 2097152)
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

| 錯誤代碼             | 說明               |
| -------------------- | ------------------ |
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
function mpu_secure_file_write(string $file_path, string $content)
```

**可能的錯誤：**

| 錯誤代碼             | 說明             |
| -------------------- | ---------------- |
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
function mpu_encrypt_api_key(string $api_key): string
```

---

#### mpu_decrypt_api_key()

解密 API Key。

```php
/**
 * @param string $encrypted 加密的字串
 * @return string 解密後的 API Key
 */
function mpu_decrypt_api_key(string $encrypted): string
```

---

#### mpu_get_client_ip()

獲取客戶端真實 IP 地址（支援反向代理）。

```php
/**
 * @return string 客戶端 IP 地址
 */
function mpu_get_client_ip(): string
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
function mpu_fetch_external_api(string $cache_key, string $url, int $cache_duration = 3600, array $options = [])
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
function mpu_render_prompt_template(string $template, array $variables = []): string
```

---

#### mpu_get_current_user_info()

獲取當前 WordPress 用戶資訊。

```php
/**
 * @return array 當前用戶資訊陣列
 */
function mpu_get_current_user_info(): array
```

---

#### mpu_get_wordpress_info()

獲取 WordPress 網站資訊（包含基本資訊和統計資訊）。

```php
/**
 * @return array WordPress 網站資訊陣列
 */
function mpu_get_wordpress_info(): array
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
function mpu_get_provider_api_key(string $provider, ?array $mpu_opt = null): string
```

---

#### mpu_get_current_provider()

獲取當前啟用的 AI 提供商名稱。

```php
/**
 * @param array|null $mpu_opt 選項陣列
 * @return string AI 提供商名稱
 */
function mpu_get_current_provider(?array $mpu_opt = null): string
```

---

### AI 函數 (ai-functions.php)

#### mpu_call_ai_api()

呼叫 AI API（自動選擇提供商）。支援 Gemini、OpenAI、Claude。

```php
/**
 * @param string $provider AI 提供商（'gemini'、'openai'、'claude'）
 * @param string $api_key API Key
 * @param string $system_prompt 系統提示（角色設定）
 * @param string $user_prompt 使用者提示
 * @param string $language 語言設定（'zh-TW'、'ja'、'en'）
 * @param array $mpu_opt 外掛設定（用於獲取模型名稱）
 * @return string|WP_Error AI 回應或錯誤
 */
function mpu_call_ai_api(
    string $provider,
    string $api_key,
    string $system_prompt,
    string $user_prompt,
    string $language = 'zh-TW',
    array $mpu_opt = []
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

#### mpu_should_trigger_ai()

檢查是否應觸發 AI。

```php
/**
 * @return bool 是否觸發
 */
function mpu_should_trigger_ai(): bool
```

檢查條件：

- AI 是否啟用
- API Key 是否設定
- 當前頁面是否符合觸發條件
- 機率檢查

---

#### mpu_get_language_instruction()

取得語言指令字串。

```php
/**
 * @param string $language 語言代碼 (zh-TW, ja, en)
 * @return string 語言指令
 */
function mpu_get_language_instruction(string $language): string
```

**返回值：**

| 語言代碼  | 返回值                         |
| --------- | ------------------------------ |
| `zh-TW` | `請用繁體中文回覆。`         |
| `ja`    | `日本語で返答してください。` |
| `en`    | `Please reply in English.`   |

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
 * @return string|WP_Error AI 回應或錯誤
 */
function mpu_call_gemini_api(
    string $api_key,
    string $model,
    string $system_prompt,
    string $user_prompt,
    string $language = 'zh-TW'
)
```

---

#### mpu_call_openai_api()

呼叫 OpenAI API。

```php
/**
 * @param string $api_key OpenAI API Key
 * @param string $model 模型名稱（如 'gpt-4.1-mini-2025-04-14'）
 * @param string $system_prompt 系統提示
 * @param string $user_prompt 使用者提示
 * @param string $language 語言設定
 * @return string|WP_Error AI 回應或錯誤
 */
function mpu_call_openai_api(
    string $api_key,
    string $model,
    string $system_prompt,
    string $user_prompt,
    string $language = 'zh-TW'
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
 * @return string|WP_Error AI 回應或錯誤
 */
function mpu_call_claude_api(
    string $api_key,
    string $model,
    string $system_prompt,
    string $user_prompt,
    string $language = 'zh-TW'
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
 * @return string|WP_Error AI 回應或錯誤
 */
function mpu_call_ollama_api(
    string $endpoint,
    string $model,
    string $system_prompt,
    string $user_prompt,
    string $language = 'zh-TW'
)
```

**功能特點：**

- 自動檢測本地/遠程連接
- 根據連接類型調整超時時間
- 支援關閉思考模式（Qwen3、DeepSeek 等模型）

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
function mpu_is_remote_endpoint(string $endpoint): bool
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
function mpu_get_ollama_timeout(string $endpoint, string $operation_type = 'api_call'): int
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
function mpu_validate_ollama_endpoint(string $endpoint)
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
function mpu_check_ollama_available(string $endpoint, string $model): bool
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
 * @return string|false 生成的對話內容，失敗時返回 false
 */
function mpu_generate_llm_dialogue(string $ukagaka_name = 'default_1', string $last_response = '', array $response_history = [])
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

---

#### mpu_is_llm_replace_dialogue_enabled()

檢查是否啟用了 LLM 取代內建對話。

```php
/**
 * @return bool
 */
function mpu_is_llm_replace_dialogue_enabled(): bool
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
function mpu_get_visitor_info_for_llm(): array
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
function mpu_get_visitor_status_text(array $visitor_info): string
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
function mpu_compress_context_info(array $wp_info, array $user_info, array $visitor_info): string
```

---

#### mpu_build_frieren_style_examples()

建構芙莉蓮風格的對話範例（70+ 個範例，涵蓋 12 個類別）。

```php
/**
 * @param array $wp_info WordPress 資訊
 * @param array $visitor_info 訪客資訊
 * @param string $time_context 時間情境（早上/下午/晚上/深夜）
 * @param string $theme_name 主題名稱
 * @param string $theme_version 主題版本
 * @param string $theme_author 主題作者
 * @return string 格式化的範例文字
 */
function mpu_build_frieren_style_examples(
    array $wp_info,
    array $visitor_info,
    string $time_context,
    string $theme_name,
    string $theme_version,
    string $theme_author
): string
```

**範例類別：**

- 問候類、閒聊類、時間感知類、觀察思考類
- 魔法研究類、技術觀察類、統計觀察類、回憶類
- 管理員評語類、意外反應類、BOT 檢測類、沉默類

**特殊功能：**

- **觀察思考類**會自動從當前春菜的內建對話文件中讀取最多 5 條台詞
  - 自動過濾空字串和超過 50 字元的訊息
  - 隨機選擇符合條件的台詞加入到範例中
  - 讓 AI 生成的對話更貼近角色的實際風格

---

#### mpu_build_prompt_categories()

建構 User Prompt 的類別指令（與範例類別對應）。

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
    array $wp_info,
    array $visitor_info,
    string $time_context,
    string $theme_name,
    string $theme_version,
    string $theme_author
): array
```

**返回值：**

```php
[
    'greeting' => ['問候類の会話例を参考に、軽く挨拶する', ...],
    'casual' => ['閒聊類の会話例を参考に、淡々とした日常の言葉を言う', ...],
    'time_aware' => ['時間感知類の会話例を参考に、{$time_context}の時間感覚を表現する', ...],
    // ... 更多類別
]
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
function mpu_weighted_random_select(array $categories, array $weights): string
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
    array $mpu_opt,
    array $wp_info,
    array $user_info,
    array $visitor_info,
    string $ukagaka_name,
    string $time_context,
    string $language
): string
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
 * @param string $text1 第一個文字
 * @param string $text2 第二個文字
 * @return float 相似度（0.0-1.0）
 */
function mpu_calculate_text_similarity(string $text1, string $text2): float
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
function mpu_debug_system_prompt(string $system_prompt): void
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
 * @return array 靜態類別指令陣列
 */
function mpu_get_static_prompt_categories(): array
```

---

#### mpu_add_statistics_prompts()

添加動態統計類別指令。根據 WordPress 統計資訊生成對話指令。

```php
/**
 * @param array &$categories 類別陣列（引用傳遞）
 * @param array $wp_info WordPress 資訊
 * @return void
 */
function mpu_add_statistics_prompts(array &$categories, array $wp_info): void
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
    array $wp_info,
    array $visitor_info,
    string $time_context,
    string $theme_name,
    string $theme_version,
    string $theme_author
): array
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
    string $time_context,
    array $visitor_info,
    array $context_vars = []
): array
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
 * @param string $decoration_type 裝飾物類型（suitcase, evil_horns, staff, books）
 * @return string|false 提示詞，若未找到則返回 false
 */
function mpu_get_decoration_prompt(string $decoration_type)
```

**支援的裝飾物類型：**

| 類型           | 說明                 |
| -------------- | -------------------- |
| `suitcase`   | 行李箱（魔法收藏箱） |
| `evil_horns` | 惡魔角飾             |
| `staff`      | 魔法杖               |
| `books`      | 魔導書               |

---

### 春菜函數 (ukagaka-functions.php)

#### mpu_get_ukagakas()

取得春菜列表 HTML。

```php
/**
 * @return string HTML 字串
 */
function mpu_get_ukagakas(): string
```

---

#### mpu_get_shell()

取得春菜圖片 URL。

```php
/**
 * @param string $key 春菜鍵值
 * @param bool $for_js 是否用於 JavaScript（預設 true）
 * @return string 圖片 URL
 */
function mpu_get_shell(string $key, bool $for_js = true): string
```

---

#### mpu_get_msg_array()

取得訊息陣列。

```php
/**
 * @param array $ukagaka 春菜資料
 * @return array 訊息陣列
 */
function mpu_get_msg_array(array $ukagaka): array
```

---

#### mpu_process_msg_codes()

處理訊息中的特殊代碼。

```php
/**
 * @param string $msg 原始訊息
 * @return string 處理後的訊息
 */
function mpu_process_msg_codes(string $msg): string
```

---

#### mpu_load_dialog_file()

載入對話檔案。

```php
/**
 * @param string $filename 檔案名稱（不含副檔名）
 * @param string $format 檔案格式（txt/json）
 * @return array 對話陣列
 */
function mpu_load_dialog_file(string $filename, string $format): array
```

**範例：**

```php
$messages = mpu_load_dialog_file('frieren', 'json');
```

---

### 前端函數 (frontend-functions.php)

#### mpu_is_hide()

檢查是否應隱藏春菜。

```php
/**
 * @return bool 是否隱藏
 */
function mpu_is_hide(): bool
```

---

#### mpu_generate_html()

生成春菜 HTML 並輸出。

```php
/**
 * @return void
 */
function mpu_generate_html(): void
```

---

### 後台函數 (admin-functions.php)

#### mpu_generate_dialog_file()

生成對話檔案。

```php
/**
 * @param string $key 春菜鍵值
 * @param array $ukagaka 春菜資料
 * @return bool 是否成功
 */
function mpu_generate_dialog_file(string $key, array $ukagaka): bool
```

---

## WordPress Hooks

### Actions

#### mpu_loaded

外掛模組載入完成後觸發。

```php
add_action('mpu_loaded', function() {
    // 外掛已載入
});
```

---

#### mpu_before_html

春菜 HTML 生成前觸發。

```php
add_action('mpu_before_html', function() {
    // 在春菜 HTML 之前輸出內容
});
```

---

#### mpu_after_html

春菜 HTML 生成後觸發。

```php
add_action('mpu_after_html', function() {
    // 在春菜 HTML 之後輸出內容
});
```

---

#### mpu_settings_saved

設定儲存後觸發。

```php
add_action('mpu_settings_saved', function($mpu_opt) {
    // 設定已儲存，$mpu_opt 是新的設定值
}, 10, 1);
```

---

### Filters

#### mpu_options

過濾設定值。

```php
add_filter('mpu_options', function($mpu_opt) {
    // 修改設定值
    $mpu_opt['auto_talk_interval'] = 10;
    return $mpu_opt;
});
```

---

#### mpu_messages

過濾訊息陣列。

```php
add_filter('mpu_messages', function($messages, $ukagaka_key) {
    // 為特定春菜添加額外訊息
    if ($ukagaka_key === 'frieren') {
        $messages[] = '魔法是需要時間研究的。';
    }
    return $messages;
}, 10, 2);
```

---

#### mpu_ai_response

過濾 AI 回應。

```php
add_filter('mpu_ai_response', function($response, $prompt) {
    // 修改 AI 回應
    return $response . ' ✨';
}, 10, 2);
```

---

#### mpu_ukagaka_html

過濾春菜 HTML。

```php
add_filter('mpu_ukagaka_html', function($html) {
    // 修改 HTML
    return $html;
});
```

---

## AJAX 端點

### mpu_nextmsg

取得下一條訊息。

**Action:** `mpu_nextmsg`

**請求參數：**

| 參數        | 類型   | 說明                   |
| ----------- | ------ | ---------------------- |
| `ukagaka` | string | 春菜鍵值               |
| `current` | int    | 目前訊息索引           |
| `mode`    | string | `next` 或 `random` |

**成功回應：**

```json
{
    "success": true,
    "data": {
        "msg": "對話內容",
        "index": 1
    }
}
```

---

### mpu_change

切換春菜。

**Action:** `mpu_change`

**請求參數：**

| 參數        | 類型   | 說明         |
| ----------- | ------ | ------------ |
| `ukagaka` | string | 目標春菜鍵值 |

**成功回應：**

```json
{
    "success": true,
    "data": {
        "name": "芙莉蓮",
        "shell": "https://.../frieren.png",
        "messages": ["對話1", "對話2"]
    }
}
```

---

### mpu_get_settings

取得前端設定。

**Action:** `mpu_get_settings`

**成功回應：**

```json
{
    "success": true,
    "data": {
        "autoTalk": true,
        "autoTalkInterval": 8000,
        "typewriterSpeed": 40,
        "clickBehavior": 0
    }
}
```

---

### mpu_test_ollama_connection

測試 Ollama 連接。

**Action:** `mpu_test_ollama_connection`

**請求參數：**

| 參數         | 類型   | 說明            |
| ------------ | ------ | --------------- |
| `endpoint` | string | Ollama 端點 URL |
| `model`    | string | 模型名稱        |
| `nonce`    | string | WordPress nonce |

**請求範例：**

```javascript
{
    action: 'mpu_test_ollama_connection',
    endpoint: 'https://your-domain.com',
    model: 'qwen3:8b',
    nonce: '...'
}
```

**成功回應：**

```json
{
    "success": true,
    "data": "連接成功（遠程連接），模型響應正常（預覽：Hello...）"
}
```

**失敗回應：**

```json
{
    "success": false,
    "data": "連接失敗：無法連接到遠程 Ollama 服務..."
}
```

---

### mpu_test_gemini_connection

測試 Google Gemini API 連接。

**Action:** `mpu_test_gemini_connection`

**請求參數：**

| 參數        | 類型   | 說明                                           |
| ----------- | ------ | ---------------------------------------------- |
| `api_key` | string | Gemini API Key（可選，如未提供則從設定中讀取） |
| `model`   | string | 模型名稱（可選，如未提供則從設定中讀取）       |
| `nonce`   | string | WordPress nonce                                |

**成功回應：**

```json
{
    "success": true,
    "data": "連接成功，API Key 有效"
}
```

**失敗回應：**

```json
{
    "success": false,
    "data": "連接失敗：API Key 無效或網路錯誤"
}
```

---

### mpu_test_openai_connection

測試 OpenAI API 連接。

**Action:** `mpu_test_openai_connection`

**請求參數：**

| 參數        | 類型   | 說明                                           |
| ----------- | ------ | ---------------------------------------------- |
| `api_key` | string | OpenAI API Key（可選，如未提供則從設定中讀取） |
| `model`   | string | 模型名稱（可選，如未提供則從設定中讀取）       |
| `nonce`   | string | WordPress nonce                                |

**成功回應：**

```json
{
    "success": true,
    "data": "連接成功，API Key 有效"
}
```

**失敗回應：**

```json
{
    "success": false,
    "data": "連接失敗：API Key 無效或網路錯誤"
}
```

---

### mpu_test_claude_connection

測試 Claude (Anthropic) API 連接。

**Action:** `mpu_test_claude_connection`

**請求參數：**

| 參數        | 類型   | 說明                                           |
| ----------- | ------ | ---------------------------------------------- |
| `api_key` | string | Claude API Key（可選，如未提供則從設定中讀取） |
| `model`   | string | 模型名稱（可選，如未提供則從設定中讀取）       |
| `nonce`   | string | WordPress nonce                                |

**成功回應：**

```json
{
    "success": true,
    "data": "連接成功，API Key 有效"
}
```

**失敗回應：**

```json
{
    "success": false,
    "data": "連接失敗：API Key 無效或網路錯誤"
}
```

---

### mpu_load_dialog

載入外部對話檔案。

**Action:** `mpu_load_dialog`

**請求參數：**

| 參數         | 類型   | 說明                |
| ------------ | ------ | ------------------- |
| `filename` | string | 檔案名稱            |
| `format`   | string | `txt` 或 `json` |

**成功回應：**

```json
{
    "success": true,
    "data": {
        "messages": ["對話1", "對話2", "對話3"]
    }
}
```

---

### mpu_ai_context_chat

AI 頁面感知對話。

**Action:** `mpu_ai_context_chat`

**請求參數：**

| 參數        | 類型   | 說明       |
| ----------- | ------ | ---------- |
| `title`   | string | 文章標題   |
| `content` | string | 文章內容   |
| `nonce`   | string | 安全驗證碼 |

**成功回應：**

```json
{
    "success": true,
    "data": {
        "message": "AI 生成的評論"
    }
}
```

---

### mpu_get_visitor_info

取得訪客資訊（需要 Slimstat）。

**Action:** `mpu_get_visitor_info`

**請求參數：**

| 參數      | 類型   | 說明       |
| --------- | ------ | ---------- |
| `nonce` | string | 安全驗證碼 |

**成功回應：**

```json
{
    "success": true,
    "data": {
        "country": "TW",
        "city": "Taipei",
        "referer": "https://google.com",
        "searchterms": "搜尋關鍵字",
        "browser": "Chrome",
        "platform": "Windows"
    }
}
```

---

### mpu_ai_greet

AI 首次訪客打招呼。

**Action:** `mpu_ai_greet`

**請求參數：**

| 參數             | 類型   | 說明       |
| ---------------- | ------ | ---------- |
| `visitor_info` | object | 訪客資訊   |
| `nonce`        | string | 安全驗證碼 |

**成功回應：**

```json
{
    "success": true,
    "data": {
        "message": "歡迎來自台灣的朋友！"
    }
}
```

---

### mpu_user_chat (v2.3.0)

用戶互動對話請求。處理互動對話模式中的用戶輸入。

**Action:** `mpu_user_chat`

**請求參數：**

| 參數        | 類型   | 說明           |
| ----------- | ------ | -------------- |
| `message` | string | 用戶輸入的訊息 |
| `history` | array  | 對話歷史陣列   |
| `nonce`   | string | 安全驗證碼     |

**對話歷史格式：**

```json
[
    {"role": "user", "content": "你好"},
    {"role": "assistant", "content": "你好！有什麼想聊的嗎？"}
]
```

**成功回應：**

```json
{
    "success": true,
    "data": {
        "message": "AI 生成的回應"
    }
}
```

---

### mpu_decoration_chat (v2.3.0)

裝飾物點擊對話請求。當用戶點擊角色的裝飾物時生成相關對話。

**Action:** `mpu_decoration_chat`

**請求參數：**

| 參數                | 類型   | 說明                                             |
| ------------------- | ------ | ------------------------------------------------ |
| `decoration_type` | string | 裝飾物類型（suitcase, evil_horns, staff, books） |
| `nonce`           | string | 安全驗證碼                                       |

**成功回應：**

```json
{
    "success": true,
    "data": {
        "message": "關於這個行李箱...裡面裝的都是收集的魔法。"
    }
}
```

---

## JavaScript 函數

### 核心函數

#### mpu_nextmsg(mode)

顯示下一條訊息。

```javascript
/**
 * @param {string} mode - 'next' 順序 / 'random' 隨機 / '' 使用設定值
 */
mpu_nextmsg('next');
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

#### mpu_hideukagaka()

隱藏春菜。

```javascript
mpu_hideukagaka();
```

---

#### mpu_showukagaka()

顯示春菜。

```javascript
mpu_showukagaka();
```

---

#### mpuChange()

開啟春菜切換選單。

```javascript
mpuChange();
```

---

#### mpu_showMessage(message, options)

顯示指定訊息（帶打字效果）。

```javascript
/**
 * @param {string} message - 訊息內容
 * @param {object} options - 選項
 * @param {string} options.color - 文字顏色
 * @param {boolean} options.typewriter - 是否使用打字效果
 */
mpu_showMessage('歡迎光臨！', {
    color: '#ff6b6b',
    typewriter: true
});
```

---

### AI 功能函數

#### mpu_triggerAIContext()

觸發 AI 頁面感知。

```javascript
mpu_triggerAIContext();
```

---

#### mpu_triggerAIGreeting()

觸發 AI 首次訪客打招呼。

```javascript
mpu_triggerAIGreeting();
```

---

#### mpu_pauseAutoTalk(duration)

暫停自動對話。

```javascript
/**
 * @param {number} duration - 暫停時間（毫秒）
 */
mpu_pauseAutoTalk(10000); // 暫停 10 秒
```

---

### 全域設定物件

```javascript
window.mpuSettings = {
    ajaxUrl: '/wp-admin/admin-ajax.php',
    nonce: 'xxx',
    autoTalk: true,
    autoTalkInterval: 8000,      // 毫秒
    typewriterSpeed: 40,          // 毫秒/字
    clickBehavior: 0,             // 0=下一條, 1=無操作
    nextMode: 0,                  // 0=順序, 1=隨機
    aiEnabled: true,
    aiTextColor: '#ff6b6b',
    aiDisplayDuration: 8000,      // 毫秒
    aiGreetEnabled: true,
    useExternalFile: false,
    externalFileFormat: 'txt'
};
```

---

## 特殊代碼

在對話內容中可使用以下特殊代碼：

### :recentpost[n]:

顯示最近 n 篇文章列表。

```
最近的文章：:recentpost[5]:
```

---

### :randompost[n]:

顯示隨機 n 篇文章列表。

```
推薦閱讀：:randompost[3]:
```

---

### :commenters[n]:

顯示最近 n 位留言者。

```
感謝留言：:commenters[5]:
```

---

### 📅

顯示今天日期。

```
今天是 :date:
```

---

### :time:

顯示目前時間。

```
現在時間是 :time:
```

---

### :sitename:

顯示網站名稱。

```
歡迎來到 :sitename:！
```

---

**📌 注意：** 特殊代碼會在伺服器端處理，轉換為實際內容後再傳送到前端。

---

**文檔版本：2.5.6**
