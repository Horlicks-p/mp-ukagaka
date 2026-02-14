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

---

# WordPress Abilities API Integration Guide (English)

**MP Ukagaka** leverages the **Abilities API** built into the WordPress 6.9+ core, granting your AI characters (Ukagaka) the ability to interact directly with your WordPress site.

This feature allows AI characters to use registered "Abilities," such as querying site information, managing posts, or executing other WordPress functions, evolving them from simple chatbots into site management assistants.

## What is the Abilities API?

The **Abilities API** is a core feature introduced in WordPress 6.9 (released December 2, 2025). It provides a standardized way to register and discover WordPress functionalities (Abilities).

- **Past (WP < 6.9)**: Depended on the **MCP Adapter** plugin to provide registry functionality.
- **Present (WP >= 6.9)**: The registry is now part of the WordPress core.

Therefore, as long as your WordPress version is 6.9 or higher, **no additional plugins are required** for MP Ukagaka to utilize these functions.

## Why is the MCP Adapter not needed?

Because `mp-ukagaka` calls the core function `wp_register_ability()`, directly registering abilities (e.g., "Get Popular Posts") into the WordPress core registry.

The AI logic reads abilities directly from this core registry and passes them as "Tools" to models like Gemini, Claude, OpenAI, and Ollama. Since the registration and discovery mechanisms are built-in, the MCP Adapter is no longer required as an intermediary.

- **Internal Use (Internal Agent)**: For Ukagaka use → **No MCP Adapter needed** (direct access to Core API).
- **External Use (External Agent)**: For Cursor or Claude Desktop → **May need MCP Adapter** (exposing core abilities via standard MCP protocol).

## Supported Models

The following AI models currently support tool calling in this plugin:

- **Google Gemini**: Gemini 2.0 Flash (Recommended), Gemini 1.5 Pro, etc.
- **Anthropic Claude**: Claude 3.5 Sonnet, etc.
- **OpenAI**: GPT-4o, GPT-4o-mini, etc.
- **Ollama**: Qwen 2.5, Llama 3.1, etc. (Models supporting Tool Calling).

## How to Add Abilities (Developer Guide)

MP Ukagaka automatically detects and uses all Abilities registered to the WordPress core. You can add abilities using two methods:

### Method 1: Standard WordPress Method (For any Plugin/Theme)

This is the official WordPress standard. Any plugin can register abilities this way, and MP Ukagaka will automatically read and use them.

```php
// In your custom plugin or theme's functions.php
add_action( 'wp_abilities_api_init', function() {
    if ( function_exists( 'wp_register_ability' ) ) {
        wp_register_ability(
            'my-plugin/say-hello', // Unique identifier
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

### Method 2: MP Ukagaka Modular Method (Recommended for internal development)

If you are developing the `mp-ukagaka` plugin itself, we recommend using our built-in modular architecture:

1.  Create a new class file in `includes/mcp-tools/abilities/` (e.g., `class-my-ability.php`).
2.  Implement `register` and `execute` methods.
3.  Add the class to the registry in `includes/mcp-tools/manager.php`.

---

# WordPress Abilities API 統合ガイド (Japanese)

**MP Ukagaka** は、WordPress 6.9+ コアに組み込まれた **Abilities API** を利用して、AI キャラクター (伺か) が WordPress サイトと直接対話できるようにします。

この機能により、AI キャラクターは登録された「能力 (Abilities)」(サイト情報の取得、投稿の管理、その他の WordPress 機能の実行など) を使用できるようになり、単純なチャットボットからサイト管理アシスタントへと進化します。

## Abilities API とは？

**Abilities API** は、WordPress 6.9 (2025年12月2日リリース) で導入されたコア機能です。WordPress の機能 (Abilities) を登録および検出するための標準化された方法を提供します。

- **過去 (WP < 6.9)**: レジストリ機能を提供するために **MCP Adapter** プラグインに依存していました。
- **現在 (WP >= 6.9)**: レジストリは WordPress コアの一部になりました。

したがって、WordPress バージョンが 6.9 以降であれば、MP Ukagaka がこれらの機能を利用するために**追加のプラグインは必要ありません**。

## なぜ MCP Adapter が不要なのですか？

`mp-ukagaka` がコア関数 `wp_register_ability()` を呼び出し、能力 (例:「人気の投稿を取得」) を WordPress コアレジストリに直接登録するためです。

AI ロジックはこのコアレジストリから能力を直接読み取り、Gemini、Claude、OpenAI、Ollama などのモデルに「ツール (Tools)」として渡します。登録と検出のメカニズムが組み込まれているため、中間者としての MCP Adapter は不要になりました。

- **内部利用 (Internal Agent)**: 伺かでの利用 → **MCP Adapter は不要** (Core API への直接アクセス)。
- **外部利用 (External Agent)**: Cursor や Claude Desktop での利用 → **MCP Adapter が必要な場合があります** (コアの Abilities を標準 MCP プロトコルで外部に公開するため)。

## サポートされているモデル

現在、このプラグインでツール呼び出しをサポートしている AI モデルは以下の通りです：

- **Google Gemini**: Gemini 2.0 Flash (推奨), Gemini 1.5 Pro など。
- **Anthropic Claude**: Claude 3.5 Sonnet など。
- **OpenAI**: GPT-4o, GPT-4o-mini など。
- **Ollama**: Qwen 2.5, Llama 3.1 など (ツール呼び出しをサポートするモデル)。

## 能力の追加方法 (開発者ガイド)

MP Ukagaka は、WordPress コアに登録されているすべての能力を自動的に検出して使用します。能力を追加するには2つの方法があります。

### 方法1：標準的な WordPress の方法 (任意のプラグイン/テーマ用)

これは WordPress 公式の標準的な方法です。どのプラグインでもこの方法で能力を登録でき、MP Ukagaka はそれらを自動的に読み取って使用します。

```php
// カスタムプラグインまたはテーマの functions.php 内
add_action( 'wp_abilities_api_init', function() {
    if ( function_exists( 'wp_register_ability' ) ) {
        wp_register_ability(
            'my-plugin/say-hello', // ユニークな識別子
            array(
                'label'       => 'Say Hello',
                'description' => '挨拶を返すシンプルな能力です。',
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

### 方法2：MP Ukagaka モジュール化方式 (プラグイン内部開発に推奨)

`mp-ukagaka` プラグイン自体を開発している場合は、組み込みのモジュール化アーキテクチャを使用することをお勧めします：

1.  `includes/mcp-tools/abilities/` ディレクトリに新しいクラスファイルを作成します (例: `class-my-ability.php`)。
2.  `register` メソッドと `execute` メソッドを実装します。
3.  `includes/mcp-tools/manager.php` のレジストリにクラスを追加します。
