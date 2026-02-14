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

```text
mp-ukagaka/
├── mp-ukagaka.php          # 主程式進入點
├── css/                    # 樣式表
│   ├── mpu_style.css           # 前端樣式表
│   └── admin-style.css         # 後台樣式表
├── includes/               # PHP 模組
│   ├── core/                   # 核心功能模組
│   │   ├── core-functions.php      # 核心功能（設定管理）
│   │   ├── utility-functions.php   # 工具函數
│   │   ├── ukagaka-functions.php   # 偽春菜管理
│   │   └── frontend-functions.php  # 前端功能
│   ├── ajax/                   # AJAX 處理模組
│   │   ├── chat/                   # 對話處理器子目錄（v2.5.6）
│   │   │   ├── context-handler.php     # 頁面感知對話
│   │   │   ├── greet-handler.php       # 首訪問候
│   │   │   └── user-chat-handler.php   # 互動對話
│   │   ├── ajax-handlers.php       # AJAX 處理（核心功能）
│   │   ├── ajax-chat-handlers-llm.php  # LLM 對話載入器（v2.5.6，載入 chat/ 子目錄）
│   │   ├── ajax-touch-handlers-llm.php # LLM 觸摸相關 AJAX 處理
│   │   ├── ajax-handlers-test.php  # API 連線測試處理器
│   │   └── chat-api-handlers.php   # 多輪對話 API 處理
│   ├── personality/            # 人格系統模組
│   │   ├── personality-loader.php  # 人格系統（JSON 載入器，v2.4.0）
│   │   ├── personality-prompts.php # 人格提示詞模組
│   │   ├── personality-decorations.php # 裝飾物系統
│   │   ├── personality-emoji.php   # 表情系統
│   │   └── emoji-mapper.php        # 表情映射與情緒分析（v2.4.0）
│   ├── llm/                    # LLM/AI 功能模組
│   │   ├── api-cache.php           # API 快取系統（v2.5.6）
│   │   ├── ai-functions.php        # AI 功能（雲端 API + Ollama）
│   │   ├── llm-functions.php       # LLM 功能（Ollama 專用）- BETA
│   │   ├── llm-context-builder.php # LLM 上下文建構
│   │   ├── llm-slimstat.php        # LLM Slimstat 整合
│   │   ├── prompt-categories.php   # Prompt 類別指令管理
│   │   ├── weather-functions.php   # 天氣功能（Open-Meteo API）
│   │   └── diary-functions.php     # AI 日記功能（v2.5.0）
    │   ├── integrations/           # 整合功能模組（v2.7.0）
    │   │   ├── akismet-integration.php # Akismet 垃圾留言攔截整合
    │   │   └── turnstile-integration.php # Turnstile 驗證整合
│   └── admin-functions.php     # 後台功能
├── ghost/                  # 角色人格配置（v2.4.0，類似偽春菜的 ghost 資料夾）
│   ├── Frieren/
│   │   ├── shell/              # Frieren 的角色圖片
│   │   ├── decorations/        # Frieren 的裝飾物圖片
│   │   ├── manifest.json       # 元數據與設定
│   │   ├── system_prompt.md    # 系統提示詞（Markdown）
│   │   ├── prompts.json        # 靜態對話類別
│   │   ├── dynamics.json       # 動態模板（含變數）
│   │   ├── weights.json        # 類別權重配置
│   │   ├── sleep_mode.json     # 睡眠模式配置
│   │   ├── calendar.json       # 節日配置
│   │   ├── touchzones.json     # 觸摸區域配置
│   │   ├── decorations.json    # 裝飾物點擊提示詞
│   │   ├── frieren.js          # 角色專屬 JavaScript
│   │   ├── frieren-emoji.js    # Frieren 專屬表情系統（RO 風格，v2.4.0）
│   │   ├── emoji-keywords.json # 表情關鍵字自定義配置（v2.4.0）
│   │   └── emojis/             # Frieren 專屬表情圖片（RO 風格）
│   └── [其他角色...]/
│       ├── shell/              # 角色圖片
│       └── decorations/        # 裝飾物圖片（可選）
├── dialogs/                # 對話檔案
├── images/                 # 通用圖片資源
│   └── msgbox_*.png            # 對話視窗圖片
├── languages/              # 語言檔案
├── docs/                   # 文檔
├── options/                # 後台設定頁面
│   ├── options.php             # 後台頁面框架
│   ├── options_general.php     # 通用設定頁面
│   ├── options_ukagakas.php    # 偽春菜管理頁面
│   ├── options_create.php      # 創建新偽春菜頁面
│   ├── options_extend.php      # 擴展設定頁面
│   ├── options_dialog.php      # 會話設定頁面
│   ├── options_page_ai.php     # AI 功能設定頁面
│   ├── options_page_llm.php    # LLM 功能設定頁面（BETA）
│   └── options_page_diary.php  # 日記功能設定頁面
├── js/                     # 前端 JavaScript 模組
│   ├── dist/                   # 打包輸出目錄（生產版）
│   │   ├── ukagaka-bundle.min.js   # 合併壓縮後的核心 bundle
│   │   └── ukagaka-textarearesizer.min.js  # 後台工具（壓縮版）
│   ├── ukagaka-base.js         # 基礎層（配置 + 工具 + AJAX）
│   ├── ukagaka-core.js         # 前端核心 JS（訊息顯示、偽春菜切換等）
│   ├── ukagaka-features.js     # 前端功能 JS（AI 頁面感知、首次訪客打招呼等）
│   ├── ukagaka-anime.js        # Canvas 動畫管理器（圖片序列播放）
│   ├── ukagaka-chat.js         # 聊天功能前端（v2.3.0）
│   ├── ukagaka-emoji.js        # 表情配置載入器
│   └── ukagaka-textarearesizer.js  # 後台文字區域調整器
└── readme.txt              # WordPress 外掛目錄說明檔
```

### 模組載入順序

外掛採用條件載入機制，根據執行環境（前端/後台）載入對應模組：

```php
// mp-ukagaka.php 中的載入邏輯

// 核心模組：前端和後台都需要
$core_modules = [
    'core/debug-functions.php',     // 0. 日誌系統（必須最先載入）
    'core/core-functions.php',      // 1. 核心功能（設定管理）
    'core/utility-functions.php',   // 2. 工具函數
    'personality/personality-loader.php',  // 3. 人格系統（JSON 載入器，需在其他 personality 模組之前載入）
    'personality/personality-prompts.php', // 4. 人格提示詞模組（動態提示詞、變數替換）
    'personality/personality-decorations.php', // 5. 裝飾物系統
    'personality/personality-emoji.php',   // 6. 表情系統
    'stats/stats-collector.php',   // 7. 統計收集器（需在 ai-functions.php 之前載入）
    'stats/stats-analyzer.php',    // 8. 統計分析器
    'llm/api-cache.php',           // 9. API 快取系統（v2.5.6，需在 ai-functions.php 之前載入）
    'llm/ai-functions.php',        // 10. AI 功能（雲端 API：Gemini, OpenAI, Claude）
    'llm/prompt-categories.php',   // 11. Prompt 類別指令管理（需在 llm-functions.php 之前載入）
    'llm/llm-slimstat.php',        // 12. LLM Slimstat 整合（需在 llm-context-builder.php 之前載入）
    'llm/llm-context-builder.php', // 13. LLM 上下文建構（需在 llm-functions.php 之前載入）
    'llm/weather-functions.php',   // 14. 天氣功能（Open-Meteo API）
    'llm/diary-functions.php',     // 15. AI 日記功能（v2.5.0）
    'llm/llm-functions.php',       // 16. LLM 功能（本機 LLM：Ollama）
    'personality/emoji-mapper.php',        // 17. 表情映射與情緒分析（需在 AJAX 處理器之前載入）
    'core/ukagaka-functions.php',   // 18. 偽春菜管理
    'ajax/ajax-handlers.php',       // 19. AJAX 處理器（核心功能）
    'ajax/ajax-chat-handlers-llm.php',      // 20. LLM 相關 AJAX 處理器（對話相關）
    'ajax/ajax-touch-handlers-llm.php',     // 21. LLM 相關 AJAX 處理器（觸摸相關）
    'ajax/ajax-handlers-test.php',  // 22. API 連線測試處理器
    'ajax/chat-api-handlers.php',   // 23. 對話模式 API 處理器（多輪對話）
    'integrations/akismet-integration.php', // 24. Akismet 垃圾留言連動
    'integrations/turnstile-integration.php', // 25. Turnstile 垃圾留言連動
];

// 前端專用模組（僅在非後台環境載入）
$frontend_modules = [
    'core/frontend-functions.php',  // 前端功能
];

// 後台專用模組（僅在後台環境載入）
$admin_modules = [
    'admin-functions.php',     // 後台功能
];
```

**載入時機：**

- 所有核心模組在 `plugins_loaded` action（優先級 1）載入
- 前端模組僅在 `!is_admin()` 時載入
- 後台模組僅在 `is_admin()` 時載入

### 常數定義

| 常數              | 說明       | 值           |
| ----------------- | ---------- | ------------ |
| `MPU_VERSION`   | 外掛版本   | `"2.5.6"`  |
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
function mpu_default_opt(): array

/**
 * 取得外掛設定（帶快取）
 * @return array 設定陣列
 */
function mpu_get_option(): array
```

**注意：** `mpu_count_total_msg()` 位於 `ukagaka-functions.php` 模組中。

### utility-functions.php

工具函數模組，提供各種輔助功能（字串處理、過濾、檔案操作、加密等）。

#### 字串/陣列轉換

```php
/**
 * 陣列轉字串（用雙換行分隔）
 * @param array $arr 輸入陣列
 * @return string 輸出字串
 */
function mpu_array2str($arr = []): string

/**
 * 字串轉陣列（以換行分隔，過濾空行）
 * @param string $str 輸入字串
 * @return array 輸出陣列
 */
function mpu_str2array($str = ""): array
```

#### 輸出過濾

```php
/**
 * HTML 輸出過濾（使用 esc_html）
 * @param string $str 輸入字串
 * @return string 過濾後字串
 */
function mpu_output_filter($str): string

/**
 * JavaScript 輸出過濾（使用 esc_js）
 * @param string $str 輸入字串
 * @return string 過濾後字串
 */
function mpu_js_filter($str): string

/**
 * 輸入過濾（stripslashes）
 * @param string $str 輸入字串
 * @return string 過濾後字串
 */
function mpu_input_filter($str): string

/**
 * HTML 解碼
 * @param string $str 輸入字串
 * @return string 解碼後字串
 */
function mpu_html_decode($str): string
```

#### 瀏覽器檢測

```php
/**
 * 檢測瀏覽器類型
 * @param string $target 目標瀏覽器（如 'ie', 'chrome'）
 * @return bool 是否為目標瀏覽器
 */
function mpu_is_browser($target = ""): bool
```

#### 安全檔案操作

```php
/**
 * 安全讀取檔案（使用 WordPress Filesystem API）
 * @param string $file_path 檔案路徑
 * @return string|WP_Error 檔案內容或錯誤
 */
function mpu_secure_file_read($file_path)

/**
 * 安全寫入檔案（使用 WordPress Filesystem API）
 * @param string $file_path 檔案路徑
 * @param string $content 檔案內容
 * @return bool|WP_Error 成功或錯誤
 */
function mpu_secure_file_write($file_path, $content)

/**
 * 取得對話檔案目錄路徑
 * @return string 目錄路徑
 */
function mpu_get_dialogs_dir(): string

/**
 * 確保對話檔案目錄存在
 * @return bool 是否成功
 */
function mpu_ensure_dialogs_dir(): bool
```

#### API Key 加密

```php
/**
 * 取得加密金鑰（基於 WordPress AUTH_KEY）
 * @return string 加密金鑰
 */
function mpu_get_encryption_key(): string

/**
 * 加密 API Key（AES-256-CBC）
 * @param string $api_key 原始 API Key
 * @return string 加密後的字串
 */
function mpu_encrypt_api_key($api_key): string

/**
 * 解密 API Key
 * @param string $encrypted_key 加密的字串
 * @return string|false 解密後的 API Key 或 false
 */
function mpu_decrypt_api_key($encrypted_key)

/**
 * 檢查 API Key 是否已加密
 * @param string $api_key API Key 字串
 * @return bool 是否已加密
 */
function mpu_is_api_key_encrypted($api_key): bool
```

### personality-loader.php (v2.4.0)

Personality 系統載入器模組，提供基於 JSON 的角色配置系統。允許不同角色通過 JSON 檔案定義人格，無需修改 PHP 程式碼。

#### personality-loader.php 主要函數

```php
/**
 * 獲取 ghost 目錄路徑（personalities 目錄）
 * @return string 絕對路徑
 */
function mpu_get_personalities_dir(): string

/**
 * 獲取當前 personality ID
 * @return string Personality ID（資料夾名稱）
 */
function mpu_get_current_personality_id(): string

/**
 * 檢查 personality 是否存在
 * @param string $personality_id Personality 資料夾名稱
 * @return bool 是否存在
 */
function mpu_personality_exists($personality_id): bool

/**
 * 獲取所有可用的 personalities
 * @param bool $include_placeholders 是否包含佔位角色
 * @return array Personality ID => manifest 的關聯陣列
 */
function mpu_get_available_personalities($include_placeholders = false): array

/**
 * 載入 personality manifest
 * @param string|null $personality_id Personality ID，null 為當前
 * @return array Manifest 資料
 */
function mpu_load_personality_manifest($personality_id = null): array

/**
 * 載入 personality prompts
 * @param string|null $personality_id Personality ID，null 為當前
 * @return array 提示詞類別陣列
 */
function mpu_load_personality_prompts($personality_id = null): array

/**
 * 載入 personality weights
 * @param string|null $personality_id Personality ID，null 為當前
 * @return array 權重配置陣列
 */
function mpu_load_personality_weights($personality_id = null): array

/**
 * 載入 personality decorations 配置
 * @param string|null $personality_id Personality ID，null 為當前
 * @return array 裝飾物配置陣列
 */
function mpu_load_personality_decorations($personality_id = null): array

/**
 * 載入 personality dynamic prompts
 * @param string|null $personality_id Personality ID，null 為當前
 * @return array 動態提示詞配置陣列
 */
function mpu_load_personality_dynamic_prompts($personality_id = null): array

/**
 * 載入 personality emoji keywords
 * @param string|null $personality_id Personality ID，null 為當前
 * @return array 表情關鍵字配置陣列
 */
function mpu_load_personality_emoji_keywords($personality_id = null): array
```

#### Personality 檔案結構

每個 personality 資料夾應包含：

- **manifest.json**（必需）：元數據和設定

  - `id`：Personality ID
  - `name`、`name_en`、`name_zh`：多語言名稱
  - `version`：版本號
  - `settings`：角色設定（如 `max_response_length`、`speech_style`、`tone`）
  - `character_traits`：角色特質（如 `age`、`race`、`occupation`、`personality`）
- **prompts.json**（可選）：靜態對話類別

  - 鍵值為類別名稱，值為提示詞陣列
- **dynamics.json**（可選）：動態模板（含變數替換）

  - 支援 `{variable_name}` 變數替換
  - 包含 `time_aware_dynamic`、`tech_observation`、`bot_detection` 等類別
- **weights.json**（可選）：類別權重配置

  - `base_weights`：基礎權重
  - `time_adjustments`：時間段調整
- **decorations.json**（可選）：裝飾物點擊提示詞

  - `items`：裝飾物配置陣列，每項包含：
    - `id`：裝飾物 ID
    - `image`：圖片路徑（相對於 `decorations/` 資料夾）
    - `position`：位置設定（如 `{"bottom": "0px", "right": "0px"}`）
    - `size`：尺寸設定（如 `{"width": "100px", "height": "auto"}`）
    - `z_index`：層級（數字）
    - `prompt`：點擊時的提示詞
    - `transform`：CSS 變形（可選，如 `scale(1)`）
- **emoji-keywords.json**（可選，v2.4.0）：表情觸發關鍵字

  - `mappings`：表情類型與關鍵字的映射
  - 格式範例：
    ```json
    {
      "mappings": {
        "happy": {
          "keywords": ["開心", "happy"],
          "file": "happy.png",
          "weight": 10
        }
      }
    }
    ```
- **script**（可選）：角色專屬 JavaScript 檔案

  - 如 `frieren.js`，由前端自動載入

#### 使用範例

```php
// 獲取當前 personality 的提示詞
$prompts = mpu_load_personality_prompts();

// 獲取特定 personality 的 manifest
$manifest = mpu_load_personality_manifest('Frieren');

// 檢查 personality 是否存在
if (mpu_personality_exists('Frieren')) {
    // Frieren personality 存在
}

// 獲取所有可用的 personalities
$personalities = mpu_get_available_personalities();
foreach ($personalities as $id => $manifest) {
    echo $manifest['name'];
}
```

### ai-functions.php

AI 功能模組，處理雲端 AI API 呼叫（Gemini、OpenAI、Claude）和 Ollama 整合。

#### ai-functions.php 主要函數

```php
/**
 * 呼叫 AI API（統一入口）
 * @param string $provider 提供商（gemini/openai/claude/ollama）
 * @param string $api_key API 金鑰（Ollama 不需要）
 * @param string $system_prompt 系統提示（角色設定）
 * @param string $user_prompt 使用者提示
 * @param string $language 語言代碼
 * @param array|null $mpu_opt 設定陣列（可選）
 * @return string|WP_Error AI 回應或錯誤
 */
function mpu_call_ai_api($provider, $api_key, $system_prompt, $user_prompt, $language, $mpu_opt = null)

/**
 * 呼叫 Gemini API
 * @param string $api_key API 金鑰
 * @param string $model 模型名稱（如 gemini-2.5-flash）
 * @param string $system_prompt 系統提示
 * @param string $user_prompt 使用者提示
 * @param string $language 語言代碼
 * @return string|WP_Error 生成的文本或錯誤
 */
function mpu_call_gemini_api($api_key, $model, $system_prompt, $user_prompt, $language)

/**
 * 呼叫 OpenAI API
 * @param string $api_key API 金鑰
 * @param string $model 模型名稱（如 gpt-4o-mini）
 * @param string $system_prompt 系統提示
 * @param string $user_prompt 使用者提示
 * @param string $language 語言代碼
 * @return string|WP_Error 生成的文本或錯誤
 */
function mpu_call_openai_api($api_key, $model, $system_prompt, $user_prompt, $language)

/**
 * 呼叫 Claude API
 * @param string $api_key API 金鑰
 * @param string $model 模型名稱（如 claude-sonnet-4-5-20250929）
 * @param string $system_prompt 系統提示
 * @param string $user_prompt 使用者提示
 * @param string $language 語言代碼
 * @return string|WP_Error 生成的文本或錯誤
 */
function mpu_call_claude_api($api_key, $model, $system_prompt, $user_prompt, $language)

/**
 * 呼叫 Ollama API（本機或遠程）
 * @param string $endpoint Ollama 端點 URL
 * @param string $model 模型名稱（如 qwen3:8b）
 * @param string $system_prompt 系統提示
 * @param string $user_prompt 使用者提示
 * @param string $language 語言代碼
 * @return string|WP_Error 生成的文本或錯誤
 */
function mpu_call_ollama_api($endpoint, $model, $system_prompt, $user_prompt, $language)

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

/**
 * 取得允許的條件標籤列表
 * @return array 條件標籤陣列
 */
function mpu_get_allowed_conditional_tags(): array
```

#### 支援的 AI 提供商

| 提供商 | 函數                      | API 端點                              | 模型選擇                                    |
| ------ | ------------------------- | ------------------------------------- | ------------------------------------------- |
| Gemini | `mpu_call_gemini_api()` | `generativelanguage.googleapis.com` | 支援（gemini-2.5-flash, gemini-2.5-pro 等） |
| OpenAI | `mpu_call_openai_api()` | `api.openai.com`                    | 支援（gpt-4o-mini, gpt-4o 等）              |
| Claude | `mpu_call_claude_api()` | `api.anthropic.com`                 | 支援（claude-sonnet-4-5-20250929 等）       |
| Ollama | `mpu_call_ollama_api()` | 本地或遠程 Ollama 服務                | 支援（任何 Ollama 模型）                    |

### llm-functions.php (BETA)

> ⚠️ **注意**：此模組處於**測試階段（BETA）**，API 可能會變更。

LLM 功能模組，專門處理 Ollama 本地 LLM 整合。

#### llm-functions.php 主要函數

```php
/**
 * 檢測端點是否為遠程連接
 * @param string $endpoint Ollama 端點 URL
 * @return bool 是否為遠程連接（true = 遠程，false = 本地）
 */
function mpu_is_remote_endpoint(string $endpoint): bool

/**
 * 根據端點類型和操作類型獲取適當的超時時間
 * @param string $endpoint Ollama 端點 URL
 * @param string $operation_type 操作類型：'check'（服務檢查）、'api_call'（API 調用）、'test'（測試連接）
 * @return int 超時時間（秒）
 */
function mpu_get_ollama_timeout(string $endpoint, string $operation_type = 'api_call'): int

/**
 * 驗證和標準化 Ollama 端點 URL
 * @param string $endpoint 原始端點 URL
 * @return string|WP_Error 標準化後的 URL 或錯誤
 */
function mpu_validate_ollama_endpoint(string $endpoint)

/**
 * 檢查 Ollama 服務是否可用（快速檢查，使用緩存）
 * @param string $endpoint Ollama 端點
 * @param string $model 模型名稱
 * @return bool 服務是否可用
 */
function mpu_check_ollama_available(string $endpoint, string $model): bool

/**
 * 使用 LLM 生成隨機對話（取代內建對話）
 * @param string $ukagaka_name 偽春菜名稱
 * @return string|false 生成的對話內容，失敗時返回 false
 */
function mpu_generate_llm_dialogue(string $ukagaka_name = 'default_1')

/**
 * 檢查是否啟用了 LLM 取代內建對話
 * @return bool
 */
function mpu_is_llm_replace_dialogue_enabled(): bool

/**
 * 獲取 Ollama 設定
 * @return array|false 設定陣列，未啟用時返回 false
 */
function mpu_get_ollama_settings()
```

#### 超時時間設定

| 操作類型                | 本地連接 | 遠程連接 |
| ----------------------- | -------- | -------- |
| 服務檢查 (`check`)    | 3 秒     | 10 秒    |
| API 調用 (`api_call`) | 60 秒    | 90 秒    |
| 測試連接 (`test`)     | 30 秒    | 45 秒    |

#### 使用範例

```php
// 檢查服務是否可用
$endpoint = 'https://your-domain.com';
$model = 'qwen3:8b';
if (mpu_check_ollama_available($endpoint, $model)) {
    // 服務可用，可以生成對話
    $dialogue = mpu_generate_llm_dialogue('default_1');
    if ($dialogue !== false) {
        echo $dialogue;
    }
}

// 檢測連接類型
$is_remote = mpu_is_remote_endpoint($endpoint);
$timeout = mpu_get_ollama_timeout($endpoint, 'api_call');
```

### diary-functions.php (v2.5.0)

AI 日記功能模組，負責自動生成和發佈角色日記。

#### diary-functions.php 主要函數

```php
/**
 * 獲取日記標題前綴
 * @param string|null $personality_id 人格 ID
 * @return string 前綴（如 "[フリーレン手記] "）
 */
function mpu_get_diary_title_prefix($personality_id = null): string

/**
 * 判斷是否應觸發日記（基於機率和每日一次限制）
 * @return bool 是否觸發
 */
function mpu_should_trigger_diary(): bool

/**
 * 生成日記內容
 * @return array|WP_Error 日記資料或錯誤
 */
function mpu_generate_diary_content()

/**
 * 發表日記文章
 * @param array $diary_data 日記資料
 * @return int|WP_Error 文章 ID 或錯誤
 */
function mpu_publish_diary_post($diary_data)
```

### emoji-mapper.php (v2.4.0)

表情映射與情緒分析模組，根據對話內容的情緒自動選擇對應的表情圖案。

#### emoji-mapper.php 主要函數

```php
/**
 * 分析對話內容的情緒，返回對應的表情文件名
 * 優先從角色專屬配置 `emoji-keywords.json` 載入，
 * 如果不存在則回退到內建的通用預設值。
 * 
 * @param string $text 對話內容
 * @param string|null $personality_id Personality ID (可選)
 * @return string|null 表情文件名（如 'happy.png'），無法匹配時返回 null
 */
function mpu_analyze_emoji_from_text($text, $personality_id = null)
```

#### 支援的表情類型

系統支援多種表情類型，包括：

- `happy`：開心、高興
- `waku_waku`：興奮、期待
- `laugh`：大笑
- `angry`：生氣
- `get_angry`：暴怒
- `surprised` / `startled`：驚訝
- `stunned`：震驚
- `discovery`：發現
- `scared_to_death`：嚇死
- `heart`：愛心
- `kiss`：親吻
- `sleepy`：想睡
- `awkward`：尷尬
- `proud`：驕傲
- `suspect`：懷疑
- 等等...

#### 關鍵字匹配機制

- 支援繁體中文、日文、英文關鍵字
- 使用加權機制，優先匹配高權重表情
- 關鍵字匹配不區分大小寫

#### 使用範例

```php
// 分析對話內容並獲取表情
$text = "今天真是開心的一天！";
$emoji = mpu_analyze_emoji_from_text($text);
// 可能返回：'happy.png'

// 在 AJAX 回應中使用
wp_send_json([
    'msg' => $text,
    'emoji' => $emoji
]);
```

### ukagaka-functions.php

偽春菜管理模組，處理角色相關操作和對話管理。

#### ukagaka-functions.php 主要函數

```php
/**
 * 取得偽春菜列表 HTML
 * @return string HTML 字串
 */
function mpu_ukagaka_list(): string

/**
 * 取得偽春菜資料
 * @param string|false $num 偽春菜鍵值（false 為目前偽春菜）
 * @return array|false 偽春菜資料或 false
 */
function mpu_get_ukagaka($num = false)

/**
 * 取得偽春菜圖片 URL
 * @param string|false $num 偽春菜鍵值（false 為目前偽春菜）
 * @param bool $echo 是否直接輸出
 * @return string 圖片 URL
 */
function mpu_get_shell($num = false, $echo = false): string

/**
 * 取得指定訊息
 * @param int $msgnum 訊息索引
 * @param string|false $num 偽春菜鍵值
 * @param bool $echo 是否直接輸出
 * @return string 訊息內容
 */
function mpu_get_msg($msgnum = 0, $num = false, $echo = false): string

/**
 * 取得隨機訊息
 * @param string|false $num 偽春菜鍵值
 * @param bool $echo 是否直接輸出
 * @return string 訊息內容
 */
function mpu_get_random_msg($num = false, $echo = false): string

/**
 * 取得預設訊息
 * @param string|false $num 偽春菜鍵值
 * @param bool $echo 是否直接輸出
 * @return string 訊息內容
 */
function mpu_get_default_msg($num = false, $echo = false): string

/**
 * 取得通用訊息
 * @return string 通用訊息內容
 */
function mpu_common_msg(): string

/**
 * 取得訊息陣列
 * @param string|false $num 偽春菜鍵值
 * @return array 訊息陣列
 */
function mpu_get_msg_arr($num = false): array

/**
 * 取得下一條訊息
 * @param string|false $num 偽春菜鍵值
 * @param int $msgnum 目前訊息索引
 * @return array 包含訊息和索引的陣列
 */
function mpu_get_next_msg($num = false, $msgnum = 0): array

/**
 * 處理訊息中的特殊代碼
 * @param array $msglist 訊息陣列
 * @return array 處理後的訊息陣列
 */
function mpu_msg_code($msglist = []): array

/**
 * 取得訊息鍵值
 * @param string|false $num 偽春菜鍵值
 * @param string $msg 訊息內容
 * @return int|false 訊息索引或 false
 */
function mpu_get_msg_key($num = false, $msg = "")

/**
 * 計算偽春菜訊息數
 * @param string|false $num 偽春菜鍵值
 * @return int 訊息數量
 */
function mpu_count_msg($num = false): int

/**
 * 計算所有偽春菜的總對話數
 * @return int 總對話數
 */
function mpu_count_total_msg(): int

/**
 * 從外部檔案載入對話
 * @param string $filename_base 檔案名稱（不含副檔名）
 * @return array 對話陣列
 */
function mpu_get_msg_from_file($filename_base): array
```

### ajax-chat-handlers-llm.php (v2.5.0)

LLM 對話相關 AJAX 處理器，負責處理各種對話情境。

#### ajax-chat-handlers-llm.php 主要函數

```php
/**
 * 處理 AI 上下文對話（頁面感知）
 */
function mpu_ajax_chat_context()

/**
 * 處理 AI 打招呼對話
 */
function mpu_ajax_chat_greet()

/**
 * 處理用戶互動對話
 */
function mpu_ajax_user_chat()
```

### ajax-touch-handlers-llm.php (v2.5.0)

LLM 觸摸相關 AJAX 處理器，負責處理點擊裝飾物或角色的互動。

#### ajax-touch-handlers-llm.php 主要函數

```php
/**
 * 處理裝飾物點擊對話
 */
function mpu_ajax_decoration_chat()

/**
 * 處理角色觸摸區域對話
 */
function mpu_ajax_touch_zone_chat()
```

### ajax-handlers.php

AJAX 處理模組，處理核心 AJAX 請求。

#### ajax-handlers.php 主要函數

```php
/**
 * 處理下一條訊息請求
 */
function mpu_ajax_nextmsg()

/**
 * 處理擴展功能請求
 */
function mpu_ajax_extend()

/**
 * 處理切換偽春菜請求
 */
function mpu_ajax_change()

/**
 * 處理取得設定請求
 */
function mpu_ajax_get_settings()

/**
 * 處理載入對話檔案請求
 */
function mpu_ajax_load_dialog()

/**
 * 處理取得訪客資訊請求（需要 Slimstat）
 */
function mpu_ajax_get_visitor_info()
```

> 詳見 [AJAX 端點](#ajax-端點) 章節

### ajax-handlers-test.php (v2.3.0)

API 連線測試 AJAX 處理模組，提供各 AI 提供商的連線測試功能。

#### ajax-handlers-test.php 主要函數

```php
/**
 * 測試 Ollama 連接
 */
function mpu_ajax_test_ollama_connection()

/**
 * 測試 Gemini 連接
 */
function mpu_ajax_test_gemini_connection()

/**
 * 測試 OpenAI 連接
 */
function mpu_ajax_test_openai_connection()

/**
 * 測試 Claude 連接
 */
function mpu_ajax_test_claude_connection()
```

### chat-api-handlers.php (v2.3.0)

多輪對話 API 處理模組，專門處理互動對話模式（Interactive Chat Mode）的多輪對話請求。

#### chat-api-handlers.php 主要函數

```php
/**
 * 處理多輪對話請求（AJAX 處理器）
 */
function mpu_ajax_chat()

/**
 * 呼叫 AI API（多輪對話統一入口）
 * @param string $system_prompt 系統提示（角色設定）
 * @param array $messages 對話歷史陣列
 * @param array $options 選項陣列（包含 provider, api_key, model 等）
 * @return string|WP_Error AI 回應或錯誤
 */
function mpu_call_ai_api_with_messages($system_prompt, $messages, $options = [])

/**
 * Ollama 多輪對話 API
 * @param string $system_prompt 系統提示
 * @param array $messages 對話歷史陣列
 * @param array $options 選項陣列
 * @return string|WP_Error AI 回應或錯誤
 */
function mpu_call_ollama_with_messages($system_prompt, $messages, $options = [])

/**
 * Gemini 多輪對話 API
 * @param string $api_key API 金鑰
 * @param string $model 模型名稱
 * @param string $system_prompt 系統提示
 * @param array $messages 對話歷史陣列
 * @param string $language 語言代碼
 * @return string|WP_Error AI 回應或錯誤
 */
function mpu_call_gemini_with_messages($api_key, $model, $system_prompt, $messages, $language)

/**
 * OpenAI 多輪對話 API
 * @param string $api_key API 金鑰
 * @param string $model 模型名稱
 * @param string $system_prompt 系統提示
 * @param array $messages 對話歷史陣列
 * @param string $language 語言代碼
 * @return string|WP_Error AI 回應或錯誤
 */
function mpu_call_openai_with_messages($api_key, $model, $system_prompt, $messages, $language)

/**
 * Claude 多輪對話 API
 * @param string $api_key API 金鑰
 * @param string $model 模型名稱
 * @param string $system_prompt 系統提示
 * @param array $messages 對話歷史陣列
 * @param string $language 語言代碼
 * @return string|WP_Error AI 回應或錯誤
 */
function mpu_call_claude_with_messages($api_key, $model, $system_prompt, $messages, $language)
```

#### 對話訊息格式

```php
// 對話歷史陣列格式
$messages = [
    [
        'role' => 'user',      // 'user' 或 'assistant'
        'content' => '你好'    // 訊息內容
    ],
    [
        'role' => 'assistant',
        'content' => '你好！有什麼我可以幫助的嗎？'
    ],
    // ... 更多訊息
];
```

#### 動態上下文注入

系統會根據用戶訊息內容決定是否注入 WordPress 統計資訊：

```php
// 關鍵字列表（繁中/日文/英文）
$stats_keywords = [
    '文章', '記事', 'article', 'post',
    '留言', 'コメント', 'comment',
    '網站', 'サイト', 'site', 'website',
    'php', 'wordpress', '外掛', 'plugins', プラグイン',
    '主題', 'テーマ', 'theme'
];

// 只有在用戶訊息包含這些關鍵字時才加入統計資訊
```

**優點**：

- 節省 70%+ token 消耗
- 減少 API 成本
- 加快回應速度

#### 思考模式支援（Ollama）

```php
// 在 mpu_call_ollama_with_messages() 中
$is_thinking_model = (strpos(strtolower($model), 'qwen3') !== false)
    || (strpos(strtolower($model), 'frieren') !== false)
    || (strpos(strtolower($model), 'deepseek') !== false);

// 預設啟用思考模式
$enable_thinking = $is_thinking_model && !(isset($options['ollama_disable_thinking']) && $options['ollama_disable_thinking']);

if ($enable_thinking) {
    $request_body['think'] = true;
    $request_body['options']['num_ctx'] = 8192;  // 擴大 context window
} else {
    $request_body['think'] = false;
    $request_body['options']['num_ctx'] = 4096;
}
```

#### 回應長度限制

所有 AI 提供商統一限制為 **300 tokens**：

```php
// Ollama
$request_body['options']['num_predict'] = 300;

// OpenAI
'max_tokens' => 300,

// Gemini
'generationConfig' => ['maxOutputTokens' => 300],

// Claude
'max_tokens' => 300,
```

### frontend-functions.php

前端功能模組，負責頁面顯示和資源載入。

#### frontend-functions.php 主要函數

```php
/**
 * 檢查是否應顯示在當前頁面
 * @return bool 是否顯示
 */
function mpu_is_show_page(): bool

/**
 * 輸出緩衝回調（用於插入偽春菜 HTML）
 * @param string $buffer 頁面內容
 * @return string 處理後的內容
 */
function mpu_ob_callback($buffer): string

/**
 * 關閉時回調（確保 HTML 插入）
 */
function mpu_shutdown_callback(): void

/**
 * 生成偽春菜 HTML
 * @param string|false $num 偽春菜鍵值
 * @return string HTML 字串
 */
function mpu_html($num = false): string

/**
 * 輸出偽春菜 HTML
 */
function mpu_echo_html(): void

/**
 * 載入前端資源（CSS/JS）
 */
function mpu_enqueue_frontend_assets(): void

/**
 * 在 head 中輸出設定（JavaScript 變數）
 */
function mpu_head(): void
```

### admin-functions.php

後台功能模組，處理設定儲存和後台介面。

#### admin-functions.php 主要函數

```php
/**
 * 載入後台資源（CSS/JS）
 * @param string $hook_suffix 當前頁面 hook
 */
function mpu_admin_enqueue_scripts($hook_suffix): void

/**
 * 處理設定儲存
 */
function mpu_handle_options_save(): void

/**
 * 生成對話檔案（TXT 或 JSON 格式）
 * @param string $filename 檔案名稱（不含副檔名）
 * @param array $msg_array 訊息陣列
 * @param string $ext 副檔名（txt 或 json）
 * @return bool 是否成功
 */
function mpu_generate_dialog_file($filename, $msg_array, $ext): bool

/**
 * 後台選單頁面 HTML
 */
function mpu_options_page_html(): void

/**
 * 註冊後台選單
 */
function mpu_options(): void
```

---

## 資料結構

### 設定結構 ($mpu_opt)

```php
$mpu_opt = [
    // 基本設定
    'cur_ukagaka' => 'default_1',      // 目前偽春菜
    'show_ukagaka' => true,             // 是否顯示偽春菜
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
    'use_external_file' => true,        // 是否使用外部檔案（系統已固定啟用）
    'external_file_format' => 'txt',     // 檔案格式（txt/json）

    // 會話設定
    'auto_msg' => '',                   // 固定訊息
    'common_msg' => '',                 // 通用會話

    // AI 設定（頁面感知功能）
    'ai_enabled' => false,              // 是否啟用 AI
    'ai_provider' => 'gemini',          // AI 提供商（gemini/openai/claude/ollama）
    'ai_api_key' => '',                 // Gemini API Key（加密）
    'gemini_model' => 'gemini-2.5-flash', // Gemini 模型
    'openai_api_key' => '',             // OpenAI API Key（加密）
    'openai_model' => 'gpt-4.1-mini-2025-04-14',    // OpenAI 模型
    'claude_api_key' => '',             // Claude API Key（加密）
    'claude_model' => 'claude-sonnet-4-5-20250929', // Claude 模型
    'ai_language' => 'zh-TW',           // AI 回應語言
    'ai_system_prompt' => '',           // AI 人格設定
    'ai_probability' => 10,             // AI 觸發機率（0-100）
    'ai_trigger_pages' => 'is_single',  // 觸發頁面條件
    'ai_text_color' => '#ff6b6b',       // AI 文字顏色
    'ai_display_duration' => 8,         // AI 顯示時間（秒）
    'ai_greet_enabled' => false,        // 首次訪客打招呼
    'ai_greet_prompt' => '',            // 打招呼提示詞

    // LLM 設定 (BETA)
    'ollama_endpoint' => 'http://localhost:11434',  // Ollama 端點
    'ollama_model' => 'qwen3:8b',                   // Ollama 模型
    'ollama_replace_dialogue' => false,              // 使用 LLM 取代內建對話
    'ollama_disable_thinking' => true,               // 關閉思考模式

    // 擴展
    'extend' => [
        'js_area' => '',                // 自訂 JavaScript
    ],

    // 偽春菜列表
    'ukagakas' => [
        'default_1' => [
            'name' => 'フリーレン',
            'shell' => 'images/shell/Frieren/',
            'msg' => ['フリレーンだ。千年以上生きた魔法使いだ。'],
            'show' => true,
            'dialog_filename' => 'Frieren',
        ],
        // ... 更多偽春菜
    ],
];
```

### 偽春菜結構

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

// 偽春菜 HTML 生成前
do_action('mpu_before_html');

// 偽春菜 HTML 生成後
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

// 過濾偽春菜 HTML
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
    ukagaka: 'default_1',    // 偽春菜鍵值
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

切換偽春菜。

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

### mpu_test_ollama_connection (BETA)

> ⚠️ **注意**：此端點處於**測試階段（BETA）**。

測試 Ollama 連接。

**請求：**

```javascript
{
    action: 'mpu_test_ollama_connection',
    endpoint: 'https://your-domain.com',  // Ollama 端點
    model: 'qwen3:8b',                     // 模型名稱
    nonce: '...'                           // WordPress nonce
}
```

**回應（成功）：**

```javascript
{
    success: true,
    data: '連接成功（遠程連接），模型響應正常（預覽：Hello...）'
}
```

**回應（失敗）：**

```javascript
{
    success: false,
    data: '連接失敗：無法連接到遠程 Ollama 服務...'
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
  ajaxUrl: "/wp-admin/admin-ajax.php",
  nonce: "xxx",
  autoTalk: true,
  autoTalkInterval: 8000,
  typewriterSpeed: 40,
  aiEnabled: true,
  aiTextColor: "#ff6b6b",
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

````javascript
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

### Canvas 動畫函數 (ukagaka-anime.js)

```javascript
/**
 * 全域 Canvas 管理器物件
 */
window.mpuCanvasManager = {
    /**
     * 初始化 Canvas
     * @param {object} shellInfo - 圖片或資料夾資訊
     * @param {string} name - 春菜名稱
     */
    init: function(shellInfo, name),

    /**
     * 開始播放動畫
     */
    playAnimation: function(),

    /**
     * 停止播放動畫
     */
    stopAnimation: function(),

    /**
     * 檢查是否為動畫模式
     * @return {boolean}
     */
    isAnimationMode: function()
};
````

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

2\. 在 `mpu_call_ai_api()` 中添加 case：

```php
case 'newprovider':
    return mpu_call_newprovider_api($prompt, $system_prompt);
```

3\. 在後台設定頁面添加對應選項。

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

### 自訂對話類別權重

系統使用加權隨機選擇來決定生成哪種類型的對話。你可以在 `includes/llm/llm-functions.php` 的 `mpu_generate_llm_dialogue()` 函數中修改權重：

```php
// 類別權重設定（數值越高，被選中的機率越大）
// 總權重：100
$category_weights = [
    'greeting' => 8,           // 問候類
    'casual' => 10,             // 閒聊類
    'time_aware' => 8,          // 時間感知類
    'observation' => 10,        // 觀察思考類
    'magic_research' => 8,      // 魔法研究類
    'tech_observation' => 6,    // 技術觀察類（降低權重）
    'statistics' => 8,          // 統計觀察類
    'memory' => 10,             // 回憶類
    'admin_comment' => 8,      // 管理員評語類
    'unexpected' => 10,         // 意外反應類
    'silence' => 8,             // 沉默類
    'bot_detection' => 6,       // BOT 檢測類
];
```

**權重調整建議：**

- 總權重建議保持為 100，方便計算機率
- 降低某類別的權重可以減少其出現頻率
- 提高某類別的權重可以增加其出現頻率

### 自訂觀察思考類的內建台詞讀取

觀察思考類會自動從當前春菜的內建對話文件中讀取台詞。你可以在 `includes/llm-functions.php` 的 `mpu_build_frieren_style_examples()` 函數中修改此功能：

```php
// 從內建對話文件中讀取台詞（最多 5 條）
$mpu_opt = mpu_get_option();
$current_ukagaka = $mpu_opt['cur_ukagaka'] ?? 'default_1';
if (isset($mpu_opt['ukagakas'][$current_ukagaka])) {
    $ukagaka = $mpu_opt['ukagakas'][$current_ukagaka];
    $dialog_filename = $ukagaka['dialog_filename'] ?? $current_ukagaka;

    // 讀取對話文件
    if (function_exists('mpu_get_msg_from_file')) {
        $dialog_messages = mpu_get_msg_from_file($dialog_filename);
        // ... 處理邏輯
    }
}
```

**可調整的參數：**

- 最大讀取數量：目前為 5 條，可修改 `min(5, $count)` 中的數字
- 字元長度限制：目前為 50 字元，可修改 `mb_strlen($msg) <= 50` 中的數字
- 過濾條件：可以添加更多過濾條件來篩選合適的台詞

### 未來展望：通用角色管理器支援

**目前狀態：**

目前系統中，角色專屬的動畫和互動邏輯（如芙莉蓮的喚醒動畫、翻書動畫、睡眠模式等）是通過硬編碼的 `window.mpuFrierenManager` 來實現的。這意味著：

- 只有芙莉蓮（Frieren）人格擁有專屬的角色管理器
- 其他角色無法使用類似的專屬動畫和互動功能
- 所有角色管理器的引用都直接指向 `mpuFrierenManager`

**改進方向：**

未來可以實現一個通用的角色管理器系統，支援多個角色各自擁有專屬的動畫和互動邏輯：

1. **動態管理器查找機制**

   - 在 `ukagaka-anime.js` 中實現 `getCurrentCharacterManager()` 方法
   - 根據當前角色的 `dialog_filename` 或 personality ID 動態查找對應的管理器
   - 使用命名約定：`window.mpu{PersonalityId}Manager`（例如：`mpuFrierenManager`、`mpuSakuraManager`）
2. **統一介面標準**

   - 定義標準的角色管理器介面（方法名和屬性）
   - 所有角色管理器必須實現：`initMode()`, `triggerSpeaking()`, `isCharacterMode` 等
   - 確保向後兼容（保持對 `mpuFrierenManager` 的支援）
3. **實現位置**

   - 主要修改：`js/ukagaka-anime.js`（約 20 處引用需要修改）
   - 次要修改：`js/ukagaka-chat.js` 和 `js/ukagaka-core.js`（少量引用）
   - 預估工作量：約 2-3 小時（包含測試）
4. **觸發時機**

   - 當有第二個角色需要專屬動畫或互動功能時，可以一併實現
   - 或者當需要將芙莉蓮專屬功能抽象化時進行重構

**技術要點：**

- 需要從 `dialog_filename` 或 personality ID 獲取當前角色資訊
- 需要保持向後兼容，確保現有的芙莉蓮功能正常運作
- 可以參考 `ghost/Frieren/frieren.js` 中的 `mpuFrierenManager` 作為實現範例

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

## SPA（單頁應用程式）整合

MP Ukagaka 支援 SPA 導航。當佈景主題使用 AJAX 載入頁面內容而非完整頁面刷新時，需要通知插件重新初始化。

### 事件觸發

佈景主題應在 SPA 導航完成後觸發 `mpu:spaReady` 事件：

```javascript
// 在 SPA 導航完成後觸發
document.dispatchEvent(new CustomEvent('mpu:spaReady', {
    detail: {
        url: window.location.href,    // 可選：當前 URL
        title: document.title         // 可選：頁面標題
    }
}));
```

### 插件回應

插件會監聽此事件並執行：

1. 停止並重新啟動自動對話計時器
2. 重新觸發頁面感知 AI（如果啟用）
3. 更新頁面上下文資訊

### 整合範例（佈景主題）

```javascript
// 使用 History API 的 SPA 導航範例
document.addEventListener('click', function(e) {
    const link = e.target.closest('a');
    if (!link || link.target === '_blank') return;
    
    e.preventDefault();
    
    // 執行 AJAX 載入...
    fetch(link.href)
        .then(response => response.text())
        .then(html => {
            // 更新頁面內容
            document.getElementById('content').innerHTML = html;
            history.pushState({}, '', link.href);
            
            // 通知 MP Ukagaka
            document.dispatchEvent(new CustomEvent('mpu:spaReady'));
        });
});

// 處理瀏覽器返回/前進
window.addEventListener('popstate', function() {
    // 載入對應頁面內容後...
    document.dispatchEvent(new CustomEvent('mpu:spaReady'));
});
```

### 注意事項

- 事件應在 DOM 更新完成後觸發
- 插件會自動處理對話狀態的保持
- 對話歷史記錄會在同一 session 中保留

---

## 相關資源

- [WordPress Plugin Handbook](https://developer.wordpress.org/plugins/)
- [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/)
- [Gemini API 文檔](https://ai.google.dev/docs)
- [OpenAI API 文檔](https://platform.openai.com/docs)
- [Claude API 文檔](https://docs.anthropic.com/)

---

### Happy Coding! 🎉
