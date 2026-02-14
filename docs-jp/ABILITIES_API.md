# WordPress Abilities API 統合ガイド (Japanese)

**MP Ukagaka** は、WordPress 6.9+ コアに組み込まれた **Abilities API** を利用して、AI キャラクター (伺か) が WordPress サイトと直接対話できるようにします。

この機能により、AI キャラクターは登録された「能力 (Abilities)」(サイト情報の取得、投稿の管理、その他の WordPress 機能の実行など) を使用できるようになり、単純なチャットボットからサイト管理アシスタントへと進化します。

## Abilities API とは？

**Abilities API** は、WordPress 6.9 (2025年12月2日リリース) で導入されたコア機能です。WordPress の機能 (Abilities) を登録および検出するための標準化された方法を提供します。

- **過去 (WP < 6.9)**: レジストリ機能を提供するために **MCP Adapter** プラグインに依存していました。
- **現在 (WP >= 6.9)**: レジストリは WordPress コアの一部になりました。

したがって、WordPress バージョンが 6.9 以降であれば、MP Ukagaka がこれらの機能を利用するために**追加のプラグインは必要ありません**。

## なぜ MCP Adapter が不要なのですか？

`mp-ukagaka` がコア関数 `wp_register_ability()` を呼び出し、能力 (例:「人気の投稿を取得」) を WordPress コアレジストリに直接註冊するためです。

AI ロジックはこのコアレジストリから能力を直接読み取り、Gemini、Claude、OpenAI、Ollama などのモデルに「ツール (Tools)」として渡します。登録と検出のメカニズムが組み込まれているため、中間者としての MCP Adapter は不要になりました。

- **內部利用 (Internal Agent)**: 伺かでの利用 → **MCP Adapter は不要** (Core API への直接アクセス)。
- **外部利用 (External Agent)**: Cursor や Claude Desktop での利用 → **MCP Adapter が必要な場合があります** (コアの Abilities を標準 MCP プロトコルで外部に公開するため)。

## サポートされているモデル

現在、このプラグインでツール呼び出しをサポートしている AI モデルは以下の通りです：

- **Google Gemini**: Gemini 2.0 Flash (推奨), Gemini 1.5 Pro など。
- **Anthropic Claude**: Claude 3.5 Sonnet など。
- **OpenAI**: GPT-4o, GPT-4o-mini など。
- **Ollama**: Qwen 2.5, Llama 3.1 など (ツール呼び出しをサポートするモデル)。

## 能力の追加方法 (開發者ガイド)

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

### 方法2：MP Ukagaka モジュール化方式 (プラグイン內部開發に推奨)

`mp-ukagaka` プラグイン自体を開発している場合は、組み込みのモジュール化アーキテクチャを使用することをお勧めします：

1.  `includes/mcp-tools/abilities/` ディレクトリに新しいクラスファイルを作成します (例: `class-my-ability.php`)。
2.  `register` メソッドと `execute` メソッドを実装します。
3.  `includes/mcp-tools/manager.php` のレジストリにクラスを追加します。
