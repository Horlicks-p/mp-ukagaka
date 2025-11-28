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

#### mpu_get_default_options()

取得預設設定值。

```php
/**
 * @return array 預設設定陣列
 */
function mpu_get_default_options(): array
```

**範例：**
```php
$defaults = mpu_get_default_options();
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
| 錯誤代碼 | 說明 |
|---------|------|
| `file_not_found` | 找不到指定的文件 |
| `path_not_allowed` | 不允許讀取該路徑 |
| `file_too_large` | 文件過大，無法讀取 |
| `read_failed` | 無法讀取文件 |

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
| 錯誤代碼 | 說明 |
|---------|------|
| `mkdir_failed` | 無法創建目錄 |
| `path_not_allowed` | 不允許寫入該路徑 |
| `invalid_filename` | 不合法的文件名 |
| `write_failed` | 無法寫入文件 |

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

### AI 函數 (ai-functions.php)

#### mpu_call_ai_api()

呼叫 AI API（自動選擇提供商）。

```php
/**
 * @param string $prompt 使用者提示
 * @param string $system_prompt 系統提示（角色設定）
 * @return string|null AI 回應或 null
 */
function mpu_call_ai_api(string $prompt, string $system_prompt): ?string
```

**範例：**
```php
$response = mpu_call_ai_api(
    '這篇文章講了什麼？',
    '你是一個友善的助手，回應請保持簡短。'
);
if ($response) {
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
| 語言代碼 | 返回值 |
|---------|--------|
| `zh-TW` | `請用繁體中文回覆。` |
| `ja` | `日本語で返答してください。` |
| `en` | `Please reply in English.` |

---

#### mpu_call_gemini_api()

呼叫 Google Gemini API。

```php
/**
 * @param string $prompt 使用者提示
 * @param string $system_prompt 系統提示
 * @return string|null AI 回應或 null
 */
function mpu_call_gemini_api(string $prompt, string $system_prompt): ?string
```

---

#### mpu_call_openai_api()

呼叫 OpenAI API。

```php
/**
 * @param string $prompt 使用者提示
 * @param string $system_prompt 系統提示
 * @return string|null AI 回應或 null
 */
function mpu_call_openai_api(string $prompt, string $system_prompt): ?string
```

---

#### mpu_call_claude_api()

呼叫 Anthropic Claude API。

```php
/**
 * @param string $prompt 使用者提示
 * @param string $system_prompt 系統提示
 * @return string|null AI 回應或 null
 */
function mpu_call_claude_api(string $prompt, string $system_prompt): ?string
```

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
| 參數 | 類型 | 說明 |
|-----|------|------|
| `ukagaka` | string | 春菜鍵值 |
| `current` | int | 目前訊息索引 |
| `mode` | string | `next` 或 `random` |

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
| 參數 | 類型 | 說明 |
|-----|------|------|
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

### mpu_load_dialog

載入外部對話檔案。

**Action:** `mpu_load_dialog`

**請求參數：**
| 參數 | 類型 | 說明 |
|-----|------|------|
| `filename` | string | 檔案名稱 |
| `format` | string | `txt` 或 `json` |

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
| 參數 | 類型 | 說明 |
|-----|------|------|
| `title` | string | 文章標題 |
| `content` | string | 文章內容 |
| `nonce` | string | 安全驗證碼 |

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
| 參數 | 類型 | 說明 |
|-----|------|------|
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
| 參數 | 類型 | 說明 |
|-----|------|------|
| `visitor_info` | object | 訪客資訊 |
| `nonce` | string | 安全驗證碼 |

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

### :date:

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

**文檔版本：2.1.0**

