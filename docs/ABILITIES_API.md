# WordPress Abilities API 整合說明

**MP Ukagaka** 利用 WordPress 6.9+ 核心內建的 **Abilities API**，賦予您的 AI 角色 (Ukagaka) 直接與 WordPress 網站互動的能力。

這項功能允許 AI 角色使用已註冊的「能力 (Abilities)」，例如查詢網站資訊、管理文章、或執行其他 WordPress 功能，從而從單純的聊天機器人進化為網站管理助理。

## 什麼是 Abilities API？

**Abilities API** 是 WordPress 6.9（發布於 2025 年 12 月 2 日）引入的核心功能。它提供了一個標準化的方式來註冊和發現 WordPress 的功能（Abilities）。

- **過去 (WP < 6.9)**: 需要依賴 **MCP Adapter** 外掛來提供註冊表功能。
- **現在 (WP >= 6.9)**: 註冊表已成為 WordPress 核心的一部分。

因此，只要您的 WordPress 版本為 6.9 以上，**不需要安裝任何額外外掛**，MP Ukagaka 就能直接使用這些功能。

## 為什麼不需要 MCP Adapter？

因為 `mp-ukagaka` 呼叫了核心函式 `wp_register_ability()`，直接將能力（如「讀取熱門文章」）註冊到了 WordPress 核心。

AI 的邏輯會直接從這個核心註冊表讀取能力，並將其作為 Tools 傳給 Gemini/Claude/OpenAI/Ollama。「註冊」和「發現」的機制已經內建了，因此不再需要 MCP Adapter 作為中間人。

- **內部使用 (Internal Agent)**: 給偽春菜使用 → **不需要 MCP Adapter** (直接存取核心 API)。
- **外部使用 (External Agent)**: 給 Cursor 或 Claude Desktop 使用 → **可能需要 MCP Adapter** (將核心能力轉為 MCP 協議暴露給外部)。

## 支援的模型

目前本插件支援以下 AI 模型的工具調用 (Tool Calling)：

- **Google Gemini**: Gemini 2.0 Flash (推薦), Gemini 1.5 Pro 等。
- **Anthropic Claude**: Claude 3.5 Sonnet 等。
- **OpenAI**: GPT-4o, GPT-4o-mini 等。
- **Ollama**: Qwen 2.5, Llama 3.1 等支援 Tool Calling 的模型。

## 權限與安全

包含敏感操作的核心能力（如文件操作、刪除文章等）如果被非管理員用戶觸發，系統會自動攔截並返回權限不足的提示，確保安全性。

![權限攔截示意圖](../screenshot6.PNG)

_權限控制：非管理員角色觸發 MCP 指令時的系統反應_

## 如何新增能力 (開發者指南)

MP Ukagaka 會自動偵測並使用所有註冊到 WordPress 核心的 Abilities。
您可以使用兩種方式新增能力：

### 方法一：標準 WordPress 方式 (適用於任何插件/主題)

這是 WordPress 官方標準做法，任何插件都可以這樣註冊能力，MP Ukagaka 都會自動讀取並使用。

```php
// 在您的自定義插件或主題的 functions.php 中
add_action( 'wp_abilities_api_init', function() {
    if ( function_exists( 'wp_register_ability' ) ) {
        wp_register_ability(
            'my-plugin/say-hello', // 能力的唯一識別碼
            array(
                'label'       => 'Say Hello',
                'description' => 'A simple ability that says hello.',
                'input_schema' => array(
                    'type' => 'object',
                    'properties' => array(
                        'name' => array( 'type' => 'string' ),
                    ),
                ),
                'execute_callback' => function( $args ) {
                    $name = isset( $args['name'] ) ? $args['name'] : 'World';
                    return "Hello, " . $name . "!";
                },
            )
        );
    }
} );
```

### 方法二：MP Ukagaka 模組化方式 (推薦用於本插件開發 / AI 能力建置指南)

如果您是直接開發 `mp-ukagaka` 插件本身，建議與我們內建的模組化架構對齊。以下是基於 `class-wp-postviews-ability.php` 與 `class-wp-bot-blocker-ability.php` 的開發經驗所整理的**逐步開發指南 (SOP) 與避坑指南**。

#### 1. 架構總覽 (Architecture Overview)

```text
includes/mcp-tools/
├── manager.php                  # 自動發現與註冊所有能力類別
└── abilities/
    ├── class-wp-postviews-ability.php     # 範例：純讀取、無參數
    └── class-wp-bot-blocker-ability.php   # 範例：多種能力、有參數與 Enum
```

**執行流程：**

1. `manager.php` → `register_abilities()` → 在 `wp_abilities_api_init` 時呼叫您的 `YourClass::register()`。
2. `abilities-integration.php` → `mpu_get_mcp_tools_for_llm()` → 根據不同 LLM 格式化工具 Schema。
3. LLM 請求調用工具 → `mpu_execute_mcp_tool()` → `$ability->execute($args)` → 呼叫您定義的 Callback。

**管理員權限限制：** 工具的定義**不會**發送給非管理員訪客。只有 `current_user_can('manage_options')` 的使用者能觸發工具呼叫。此限制在整合層強制執行，而非能力本身。

#### 2. 逐步開發指南 (Step-by-Step SOP)

**步驟一：建立能力類別檔案**

檔案路徑：`includes/mcp-tools/abilities/class-{slug}-ability.php`

```php
<?php

namespace MP_Ukagaka\McpTools\Abilities;

class Wp_YourFeature_Ability
{
    public static function register()
    {
        // 防護：確保 wp_register_ability 存在
        if (!function_exists('wp_register_ability')) {
            return;
        }

        // 防護：檢查外部外掛依賴 (如果有的話)
        if (!function_exists('your_plugin_function')) {
            return;
        }

        wp_register_ability('mp-ukagaka/your-ability-name', array(
            'label'               => __('人類可讀的能力名稱', 'mp-ukagaka'),
            'description'         => __('這個能力的作用。語意必須極度精準。', 'mp-ukagaka'),
            'category'            => 'mp-ukagaka',
            'input_schema'        => array(
                'type'       => 'object',
                'properties' => array(
                    'param_name' => array(
                        'type'        => 'string',
                        'description' => __('精準的參數說明。若是 Enum，請為每個值加上 "當發生...時使用此值"。', 'mp-ukagaka'),
                    ),
                ),
                'required' => array('param_name'),
            ),
            'execute_callback'    => [self::class, 'your_callback'],
            'permission_callback' => function () { return true; },
        ));
    }

    public static function your_callback($args)
    {
        // 您的處理邏輯
        return '結果字串或陣列';
    }
}
```

**步驟二：在 manager.php 中註冊類別**

將完整的類別命名空間加入 `includes/mcp-tools/manager.php` 中的 `$abilities` 陣列：

```php
protected static $abilities = [
    '\MP_Ukagaka\McpTools\Abilities\Wp_PostViews_Ability',
    '\MP_Ukagaka\McpTools\Abilities\Wp_Bot_Blocker_Ability',
    '\MP_Ukagaka\McpTools\Abilities\Wp_YourFeature_Ability',  // ← 加在這裡
];
```

**步驟三：驗證註冊**

以管理員身分登入 WordPress，在 Frieren 聊天視窗中輸入 `/debug_mcp`。請確認：

- 您的能力名稱出現在 "Tools found:" 列表中。
- "Tool count" 增加了您新增的數量。

**步驟四：對話測試**

要求 Frieren 使用該能力。在她回應後，去實際的資料來源 (資料庫、Option 等) 驗證結果是否正確。

#### 3. 必填欄位 (Required Fields)

以下 5 個欄位為**絕對必填**（缺少任何一個都會導致無聲錯誤）：

| 欄位                  | 必填    | 說明                                                                    |
| --------------------- | ------- | ----------------------------------------------------------------------- |
| `label`               | **YES** | 若缺少會拋出 `InvalidArgumentException`（被底層吃掉），能力將無法註冊。 |
| `description`         | **YES** | LLM 判斷何時該呼叫此工具的唯一依據。                                    |
| `category`            | **YES** | 必須設定為 `'mp-ukagaka'`。                                             |
| `execute_callback`    | **YES** | 請使用 `[self::class, 'method_name']` 陣列格式。                        |
| `permission_callback` | **YES** | 請寫 `function () { return true; }` (管理員檢驗已於外層呼叫時驗證)。    |

#### 4. input_schema 規則

**無參數的能力「必須」定義 input_schema：**

```php
// ✅ 正確寫法 — LLM 送出 {}，validate_input 看到定義好的 schema → 通過
'input_schema' => array(
    'type'       => 'object',
    'properties' => new \stdClass(),  // ← 必須使用 stdClass 不能用 [] ([] 會被轉成陣列而非物件)
),

// ❌ 錯誤寫法 — LLM 送出 {} (並非 null)，validate_input 收到後回傳 WP_Error → Frieren 會說「情報を取得できなかったよ」
// （完全省略 input_schema 是錯的）
```

**有參數的能力：**

```php
'input_schema' => array(
    'type'       => 'object',
    'properties' => array(
        'my_param' => array(
            'type'        => 'string',
            'description' => '...',
        ),
    ),
    'required' => array('my_param'),
),
```

#### 5. 撰寫精準引導 LLM 的說明 (Descriptions)

這是最關鍵的一步。模糊的說明會導致 LLM 選擇錯誤的工具或參數。

**能力層級的說明：**
清楚說明**它的作用**與**何時該用**：

```php
// ❌ 太模糊
'description' => 'Clear the log file or reset the IP ban list.'

// ✅ 意圖清晰
'description' => 'Clear the Moelog Bot Blocker intercept records or reset the IP ban list.'
```

**Enum 參數的說明：**
永遠為每個 Enum 值加上 **"Use this when..." (當...時使用)** 的指引：

```php
// ❌ 會導致 LLM 亂猜
'description' => 'What to clear: "logs", "ips", or "both".'

// ✅ 根據使用者意圖精準引導
'description' =>
    '"logs" — 刪除資料庫中的所有攔截紀錄。Use this when the user asks to clear records, history, logs, or intercept data. ' .
    '"ips" — 僅清除 IP 黑名單。Use this when the user asks to unblock IPs or reset the ban list. ' .
    '"both" — 兩者皆清除。'
```

**動作詞彙 (Action vs. Query)：**
避免使用檔案系統的隱喻來描述資料庫操作：

- ❌ "log file" 👉 ✅ "log records in the database"
- ❌ "config file" 👉 ✅ "settings stored in WordPress options"
- ❌ "reset file" 👉 ✅ "delete records from the table"

**操作後驗證回傳值：**
如果能力會修改資料，請回傳驗證結果，讓 LLM 能夠準確回報：

```php
public static function clear_callback($args)
{
    moelog_bot_blocker_clear_logs();
    return 'Cleared the intercept log table. All records deleted.'; // 讓 LLM 知道成功了
}
```

#### 6. 命名規範 (Naming Conventions)

- **Ability name 格式:** `mp-ukagaka/{slug}` — 只能使用小寫字母、數字與連字號 (`-`)。
  - 嚴格遵守正則表達式：`/^[a-z0-9-]+\/[a-z0-9-]+$/`
  - **禁止使用底線 (`_`)**。
  - ✅ `mp-ukagaka/get-bot-blocker-stats`
  - ❌ `mp-ukagaka/get_bot_blocker_stats`
- **類別名稱:** `Wp_{Feature}_Ability` (PascalCase 加上底線)。
- **檔案名稱:** `class-{slug}-ability.php` (kebab-case)。
- **注意:** `abilities-integration.php` 在傳送給 LLM 時，會自動將 `/` 轉換為 `__` (以符合 OpenAI 的正則限制)。

#### 7. 外部外掛整合模式 (External Plugin Integration Pattern)

**優先呼叫該外掛公開的函式**，避免直接操作 DB 或 Option：

```php
// ✅ 呼叫外掛自有函式 — 能確保觸發外掛內部的 Log 輪替、Transient 和 Action hooks
moelog_bot_blocker_ban_ip($ip);
moelog_bot_blocker_log('MANUAL_BAN', ['source' => 'Frieren API', 'ip' => $ip]);

// ❌ 直接修改 option — 會略過外掛核心邏輯
$banned = get_option('moelog_bot_blocker_banned_ips', []);
$banned[] = $ip;
update_option('moelog_bot_blocker_banned_ips', $banned);
```

#### 8. 發布前檢查表 (Checklist Before Shipping)

- [ ] 已在 `includes/mcp-tools/abilities/` 建立類別檔案。
- [ ] 已將類別加入 `manager.php` 的 `$abilities` 陣列。
- [ ] 5 個必填欄位皆已設定 (`label`, `description`, `category`, `execute_callback`, `permission_callback`)。
- [ ] 已定義 `input_schema` (即使是無參數能力也使用了 `new \stdClass()`)。
- [ ] Ability 命名為 `mp-ukagaka/{kebab-case}` 且無底線。
- [ ] 已加入外掛依賴防護 (`function_exists()`)。
- [ ] 描述語義精確 — Enum 值具備 "Use this when..." 的引導。
- [ ] `/debug_mcp` 確認工具已出現在列表中。
- [ ] 端到端對話測試通過，且資料正確變更。
