# MP Ukagaka 開發者指南

> 🛠️ 架構說明、擴展開發與 API 參考

---

## 📑 目錄

1. [架構概覽](#架構概覽)
2. [模組說明](#模組說明)
3. [資料結構](#資料結構)
4. [Hooks 與 Filters](#hooks-與-filters)
5. [AJAX 端點](#ajax-端點)
6. [JavaScript API](#javascript-api)
7. [擴展開發](#擴展開發)
8. [安全性考量](#安全性考量)
9. [開發規範](#開發規範)

---

## 架構概覽

### 目錄結構

```
mp-ukagaka/
├── mp-ukagaka.php          # 主程式進入點
├── includes/               # PHP 模組
│   ├── core-functions.php      # 核心功能
│   ├── utility-functions.php   # 工具函數
│   ├── ai-functions.php        # AI 功能
│   ├── ukagaka-functions.php   # 春菜管理
│   ├── ajax-handlers.php       # AJAX 處理
│   ├── frontend-functions.php  # 前端功能
│   └── admin-functions.php     # 後台功能
├── dialogs/                # 對話檔案
├── images/                 # 圖片資源
│   └── shell/                  # 角色圖片
├── languages/              # 語言檔案
├── docs/                   # 文檔
├── options.php             # 後台頁面框架
├── options_page*.php       # 各設定頁面
├── ukagaka-core.js         # 前端核心 JS
├── ukagaka-features.js     # 前端功能 JS
├── ukagaka_cookie.js       # Cookie 工具
└── mpu_style.css           # 樣式表
```

### 模組載入順序

```php
// mp-ukagaka.php 中的載入順序
$modules = [
    'core-functions.php',      // 1. 核心功能（設定管理）
    'utility-functions.php',   // 2. 工具函數
    'ai-functions.php',        // 3. AI 功能
    'ukagaka-functions.php',   // 4. 春菜管理
    'ajax-handlers.php',       // 5. AJAX 處理器
    'frontend-functions.php',  // 6. 前端功能
    'admin-functions.php',     // 7. 後台功能
];
```

### 常數定義

| 常數 | 說明 | 值 |
|-----|------|-----|
| `MPU_VERSION` | 外掛版本 | `"2.1.0"` |
| `MPU_MAIN_FILE` | 主檔案路徑 | `__FILE__` |

---

## 模組說明

### core-functions.php

核心功能模組，負責設定管理。

#### 主要函數

```php
/**
 * 取得預設設定值
 * @return array 預設設定陣列
 */
function mpu_get_default_options(): array

/**
 * 取得外掛設定（帶快取）
 * @return array 設定陣列
 */
function mpu_get_option(): array

/**
 * 計算所有春菜的總對話數
 * @return int 總對話數
 */
function mpu_count_total_msg(): int
```

### utility-functions.php

工具函數模組，提供各種輔助功能。

#### 字串/陣列轉換

```php
/**
 * 陣列轉字串（用換行分隔）
 * @param array $arr 輸入陣列
 * @return string 輸出字串
 */
function mpu_array2str(array $arr): string

/**
 * 字串轉陣列（以空行分隔）
 * @param string $str 輸入字串
 * @return array 輸出陣列
 */
function mpu_str2array(string $str): array
```

#### 輸出過濾

```php
/**
 * HTML 輸出過濾
 * @param string $str 輸入字串
 * @return string 過濾後字串
 */
function mpu_output_filter(string $str): string

/**
 * JavaScript 輸出過濾
 * @param string $str 輸入字串
 * @return string 過濾後字串
 */
function mpu_js_filter(string $str): string

/**
 * 輸入過濾（儲存前）
 * @param string $str 輸入字串
 * @return string 過濾後字串
 */
function mpu_input_filter(string $str): string
```

#### 安全檔案操作

```php
/**
 * 安全讀取檔案
 * @param string $file_path 檔案路徑
 * @param int $max_size 最大檔案大小（預設 2MB）
 * @return string|WP_Error 檔案內容或錯誤
 */
function mpu_secure_file_read(string $file_path, int $max_size = 2097152)

/**
 * 安全寫入檔案
 * @param string $file_path 檔案路徑
 * @param string $content 檔案內容
 * @return bool|WP_Error 成功或錯誤
 */
function mpu_secure_file_write(string $file_path, string $content)
```

#### API Key 加密

```php
/**
 * 加密 API Key
 * @param string $api_key 原始 API Key
 * @return string 加密後的字串
 */
function mpu_encrypt_api_key(string $api_key): string

/**
 * 解密 API Key
 * @param string $encrypted 加密的字串
 * @return string 解密後的 API Key
 */
function mpu_decrypt_api_key(string $encrypted): string
```

### ai-functions.php

AI 功能模組，處理 AI API 呼叫。

#### 主要函數

```php
/**
 * 呼叫 AI API
 * @param string $prompt 使用者提示
 * @param string $system_prompt 系統提示（角色設定）
 * @return string|null AI 回應或 null
 */
function mpu_call_ai_api(string $prompt, string $system_prompt): ?string

/**
 * 檢查是否應觸發 AI
 * @return bool 是否觸發
 */
function mpu_should_trigger_ai(): bool

/**
 * 取得語言指令
 * @param string $language 語言代碼
 * @return string 語言指令
 */
function mpu_get_language_instruction(string $language): string
```

#### 支援的 AI 提供商

| 提供商 | 函數 | API 端點 |
|-------|------|---------|
| Gemini | `mpu_call_gemini_api()` | `generativelanguage.googleapis.com` |
| OpenAI | `mpu_call_openai_api()` | `api.openai.com` |
| Claude | `mpu_call_claude_api()` | `api.anthropic.com` |

### ukagaka-functions.php

春菜管理模組，處理角色相關操作。

#### 主要函數

```php
/**
 * 取得春菜列表 HTML
 * @return string HTML 字串
 */
function mpu_get_ukagakas(): string

/**
 * 取得春菜圖片 URL
 * @param string $key 春菜鍵值
 * @param bool $for_js 是否用於 JavaScript
 * @return string 圖片 URL
 */
function mpu_get_shell(string $key, bool $for_js = true): string

/**
 * 取得訊息陣列
 * @param array $ukagaka 春菜資料
 * @return array 訊息陣列
 */
function mpu_get_msg_array(array $ukagaka): array

/**
 * 處理訊息中的特殊代碼
 * @param string $msg 原始訊息
 * @return string 處理後的訊息
 */
function mpu_process_msg_codes(string $msg): string

/**
 * 載入對話檔案
 * @param string $filename 檔案名稱
 * @param string $format 檔案格式（txt/json）
 * @return array 對話陣列
 */
function mpu_load_dialog_file(string $filename, string $format): array
```

### ajax-handlers.php

AJAX 處理模組，處理所有 AJAX 請求。

> 詳見 [AJAX 端點](#ajax-端點) 章節

### frontend-functions.php

前端功能模組，負責頁面顯示。

#### 主要函數

```php
/**
 * 檢查是否顯示春菜
 * @return bool 是否顯示
 */
function mpu_is_hide(): bool

/**
 * 生成春菜 HTML
 * @return void
 */
function mpu_generate_html(): void

/**
 * 載入前端資源
 * @return void
 */
function mpu_enqueue_scripts(): void
```

### admin-functions.php

後台功能模組，處理設定儲存。

#### 主要函數

```php
/**
 * 處理設定儲存
 * @return void
 */
function mpu_handle_settings_save(): void

/**
 * 生成對話檔案
 * @param string $key 春菜鍵值
 * @param array $ukagaka 春菜資料
 * @return bool 是否成功
 */
function mpu_generate_dialog_file(string $key, array $ukagaka): bool

/**
 * 註冊後台選單
 * @return void
 */
function mpu_add_admin_menu(): void
```

---

## 資料結構

### 設定結構 ($mpu_opt)

```php
$mpu_opt = [
    // 基本設定
    'cur_ukagaka' => 'default_1',      // 目前春菜
    'show_ukagaka' => true,             // 是否顯示春菜
    'show_msg' => true,                 // 是否顯示對話框
    'default_msg' => 0,                 // 0=隨機, 1=第一條
    'next_msg' => 0,                    // 0=順序, 1=隨機
    'click_ukagaka' => 0,               // 0=下一條, 1=無操作
    'insert_html' => 0,                 // HTML 插入位置
    'no_style' => false,                // 是否使用自訂樣式
    'no_page' => '',                    // 排除頁面列表
    
    // 自動對話
    'auto_talk' => true,                // 是否啟用自動對話
    'auto_talk_interval' => 8,          // 自動對話間隔（秒）
    'typewriter_speed' => 40,           // 打字速度（毫秒/字）
    
    // 外部對話檔案
    'use_external_file' => false,       // 是否使用外部檔案
    'external_file_format' => 'txt',    // 檔案格式
    
    // 會話設定
    'auto_msg' => '',                   // 固定訊息
    'common_msg' => '',                 // 通用會話
    
    // AI 設定
    'ai_enabled' => false,              // 是否啟用 AI
    'ai_provider' => 'gemini',          // AI 提供商
    'ai_api_key' => '',                 // Gemini API Key（加密）
    'openai_api_key' => '',             // OpenAI API Key（加密）
    'openai_model' => 'gpt-4o-mini',    // OpenAI 模型
    'claude_api_key' => '',             // Claude API Key（加密）
    'claude_model' => 'claude-sonnet-4-5-20250929',
    'ai_language' => 'zh-TW',           // AI 回應語言
    'ai_system_prompt' => '',           // AI 人格設定
    'ai_probability' => 10,             // AI 觸發機率
    'ai_trigger_pages' => 'is_single',  // 觸發頁面
    'ai_text_color' => '#ff6b6b',       // AI 文字顏色
    'ai_display_duration' => 8,         // AI 顯示時間（秒）
    'ai_greet_enabled' => false,        // 首次訪客打招呼
    'ai_greet_prompt' => '',            // 打招呼提示詞
    
    // 擴展
    'extend' => [
        'js_area' => '',                // 自訂 JavaScript
    ],
    
    // 春菜列表
    'ukagakas' => [
        'default_1' => [
            'name' => '初音',
            'shell' => 'shell_1.png',
            'msg' => ['歡迎光臨～'],
            'show' => true,
            'dialog_filename' => 'default_1',
        ],
        // ... 更多春菜
    ],
];
```

### 春菜結構

```php
$ukagaka = [
    'name' => '芙莉蓮',               // 名稱
    'shell' => 'https://...png',      // 圖片 URL
    'msg' => [                        // 對話陣列
        '對話 1',
        '對話 2',
    ],
    'show' => true,                   // 是否可顯示
    'dialog_filename' => 'frieren',   // 對話檔案名稱
];
```

---

## Hooks 與 Filters

### Actions

```php
// 外掛載入後
do_action('mpu_loaded');

// 春菜 HTML 生成前
do_action('mpu_before_html');

// 春菜 HTML 生成後
do_action('mpu_after_html');

// 設定儲存後
do_action('mpu_settings_saved', $mpu_opt);
```

### Filters

```php
// 過濾設定值
$mpu_opt = apply_filters('mpu_options', $mpu_opt);

// 過濾訊息陣列
$messages = apply_filters('mpu_messages', $messages, $ukagaka_key);

// 過濾 AI 回應
$response = apply_filters('mpu_ai_response', $response, $prompt);

// 過濾春菜 HTML
$html = apply_filters('mpu_ukagaka_html', $html);
```

---

## AJAX 端點

所有 AJAX 請求使用 `admin-ajax.php`。

### mpu_nextmsg

取得下一條訊息。

**請求：**
```javascript
{
    action: 'mpu_nextmsg',
    ukagaka: 'default_1',    // 春菜鍵值
    current: 0,               // 目前訊息索引
    mode: 'next'              // next 或 random
}
```

**回應：**
```javascript
{
    success: true,
    data: {
        msg: '對話內容',
        index: 1
    }
}
```

### mpu_change

切換春菜。

**請求：**
```javascript
{
    action: 'mpu_change',
    ukagaka: 'frieren'
}
```

**回應：**
```javascript
{
    success: true,
    data: {
        name: '芙莉蓮',
        shell: 'https://.../frieren.png',
        messages: ['對話1', '對話2']
    }
}
```

### mpu_load_dialog

載入外部對話檔案。

**請求：**
```javascript
{
    action: 'mpu_load_dialog',
    filename: 'frieren',
    format: 'json'
}
```

**回應：**
```javascript
{
    success: true,
    data: {
        messages: ['對話1', '對話2', '對話3']
    }
}
```

### mpu_ai_context_chat

AI 頁面感知對話。

**請求：**
```javascript
{
    action: 'mpu_ai_context_chat',
    title: '文章標題',
    content: '文章內容摘要...',
    nonce: 'xxx'
}
```

**回應：**
```javascript
{
    success: true,
    data: {
        message: 'AI 生成的評論'
    }
}
```

### mpu_get_visitor_info

取得訪客資訊（需要 Slimstat）。

**請求：**
```javascript
{
    action: 'mpu_get_visitor_info',
    nonce: 'xxx'
}
```

**回應：**
```javascript
{
    success: true,
    data: {
        country: 'TW',
        referer: 'https://google.com',
        searchterms: '搜尋關鍵字'
    }
}
```

### mpu_ai_greet

AI 首次訪客打招呼。

**請求：**
```javascript
{
    action: 'mpu_ai_greet',
    visitor_info: { country: 'TW', ... },
    nonce: 'xxx'
}
```

**回應：**
```javascript
{
    success: true,
    data: {
        message: '歡迎來自台灣的朋友！'
    }
}
```

---

## JavaScript API

### 全域物件

```javascript
// 設定物件
window.mpuSettings = {
    ajaxUrl: '/wp-admin/admin-ajax.php',
    nonce: 'xxx',
    autoTalk: true,
    autoTalkInterval: 8000,
    typewriterSpeed: 40,
    aiEnabled: true,
    aiTextColor: '#ff6b6b',
    aiDisplayDuration: 8000,
    // ...
};
```

### 核心函數 (ukagaka-core.js)

```javascript
/**
 * 顯示下一條訊息
 * @param {string} mode - 'next' 或 'random'
 */
function mpu_nextmsg(mode)

/**
 * 隱藏對話框
 */
function mpu_hidemsg()

/**
 * 顯示對話框
 */
function mpu_showmsg()

/**
 * 隱藏春菜
 */
function mpu_hideukagaka()

/**
 * 顯示春菜
 */
function mpu_showukagaka()

/**
 * 切換春菜
 */
function mpuChange()

/**
 * 顯示指定訊息（帶打字效果）
 * @param {string} message - 訊息內容
 * @param {object} options - 選項
 */
function mpu_showMessage(message, options)
```

### AI 功能函數 (ukagaka-features.js)

```javascript
/**
 * 觸發 AI 頁面感知
 */
function mpu_triggerAIContext()

/**
 * 觸發 AI 首次訪客打招呼
 */
function mpu_triggerAIGreeting()

/**
 * 暫停自動對話
 * @param {number} duration - 暫停時間（毫秒）
 */
function mpu_pauseAutoTalk(duration)
```

---

## 擴展開發

### 添加新的 AI 提供商

1. 在 `ai-functions.php` 中添加新函數：

```php
function mpu_call_newprovider_api($prompt, $system_prompt) {
    $mpu_opt = mpu_get_option();
    $api_key = mpu_decrypt_api_key($mpu_opt['newprovider_api_key']);
    
    // API 呼叫邏輯...
    
    return $response;
}
```

2. 在 `mpu_call_ai_api()` 中添加 case：

```php
case 'newprovider':
    return mpu_call_newprovider_api($prompt, $system_prompt);
```

3. 在後台設定頁面添加對應選項。

### 添加新的訊息代碼

在 `ukagaka-functions.php` 的 `mpu_process_msg_codes()` 中添加：

```php
// 處理 :newcode[param]: 格式
if (preg_match('/:newcode\[(\d+)\]:/', $msg, $matches)) {
    $param = intval($matches[1]);
    $replacement = my_custom_function($param);
    $msg = str_replace($matches[0], $replacement, $msg);
}
```

### 添加新的 AJAX 端點

在 `ajax-handlers.php` 中：

```php
add_action('wp_ajax_mpu_custom_action', 'mpu_handle_custom_action');
add_action('wp_ajax_nopriv_mpu_custom_action', 'mpu_handle_custom_action');

function mpu_handle_custom_action() {
    // 驗證 nonce
    check_ajax_referer('mpu_nonce', 'nonce');
    
    // 處理邏輯...
    
    wp_send_json_success(['data' => $result]);
}
```

---

## 安全性考量

### API Key 安全

- 所有 API Key 使用 AES-256-CBC 加密存儲
- 使用 WordPress `AUTH_KEY` 作為加密金鑰
- 後台顯示時使用 `type="password"` 隱藏

### 輸入驗證

```php
// 始終使用 WordPress 函數進行過濾
$input = sanitize_text_field($_POST['input']);
$html = wp_kses_post($_POST['html']);
$url = esc_url($_POST['url']);
```

### 輸出跳脫

```php
// HTML 輸出
echo esc_html($text);

// 屬性輸出
echo esc_attr($value);

// URL 輸出
echo esc_url($url);

// JavaScript 輸出
echo wp_json_encode($data);
```

### Nonce 驗證

```php
// 表單中添加 nonce
wp_nonce_field('mp_ukagaka_settings');

// 驗證 nonce
if (!wp_verify_nonce($_POST['_wpnonce'], 'mp_ukagaka_settings')) {
    wp_die('安全性檢查失敗');
}
```

### 檔案操作

- 使用 `mpu_secure_file_read()` 和 `mpu_secure_file_write()`
- 驗證檔案路徑在允許的目錄內
- 檢查檔案大小限制

---

## 開發規範

### 程式碼風格

- 遵循 [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/)
- 使用 4 空格縮排
- 函數命名使用 `mpu_` 前綴

### 註解規範

```php
/**
 * 函數簡短說明
 *
 * 詳細說明（可選）
 *
 * @since 2.1.0
 * @param string $param1 參數說明
 * @param int    $param2 參數說明
 * @return string 返回值說明
 */
function mpu_example_function($param1, $param2 = 0) {
    // ...
}
```

### 國際化

```php
// 可翻譯字串
__('字串', 'mp-ukagaka')

// 直接輸出的可翻譯字串
_e('字串', 'mp-ukagaka')

// 帶佔位符的字串
sprintf(__('歡迎 %s', 'mp-ukagaka'), $name)
```

### 測試

1. 在開發環境測試所有功能
2. 使用 `WP_DEBUG` 檢查錯誤
3. 測試多種 AI 提供商
4. 測試多語言環境
5. 檢查瀏覽器控制台無錯誤

---

## 相關資源

- [WordPress Plugin Handbook](https://developer.wordpress.org/plugins/)
- [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/)
- [Gemini API 文檔](https://ai.google.dev/docs)
- [OpenAI API 文檔](https://platform.openai.com/docs)
- [Claude API 文檔](https://docs.anthropic.com/)

---

**Happy Coding! 🎉**

