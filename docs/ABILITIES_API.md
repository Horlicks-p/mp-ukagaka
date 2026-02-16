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

### 方法二：MP Ukagaka 模組化方式 (推薦用於本插件開發)

如果您是直接開發 `mp-ukagaka` 插件本身，建議與我們內建的模組化架構對齊：

1.  在 `includes/mcp-tools/abilities/` 目錄下建立新的類別檔案 (例如 `class-my-ability.php`)。
2.  實作 `register` 和 `execute` 方法。
3.  在 `includes/mcp-tools/manager.php` 中註冊該類別。

範例：

```php
namespace MP_Ukagaka\McpTools\Abilities;

class My_Ability {
    public static function register() {
        if ( ! function_exists( 'wp_register_ability' ) ) { return; }

        wp_register_ability(
            'mp-ukagaka/my-ability',
            [
                'label' => 'My Ability',
                'execute_callback' => [self::class, 'execute'],
                // ... 其他參數
            ]
        );
    }

    public static function execute($args) {
        // ... 實作邏輯
    }
}
```
