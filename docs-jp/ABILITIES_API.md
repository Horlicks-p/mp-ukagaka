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

## 権限とセキュリティ（Permissions and Security）

ファイル操作や記事削除などの機密操作を含むコア機能は、非管理者ユーザーによってトリガーされた場合、システムが自動的にブロックし、権限不足のメッセージを返してセキュリティを確保します。

![権限ブロックの例](../screenshot6.PNG)

_権限制御：非管理者が MCP コマンドをトリガーした際のシステム反応_

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

`mp-ukagaka` プラグイン自体を開発している場合は、組み込みのモジュール化アーキテクチャの使用を推奨します。`class-wp-postviews-ability.php` および `class-wp-bot-blocker-ability.php` の開発経験に基づく、**ステップバイステップ開発ガイド (SOP) および落とし穴回避ガイド**を以下に示します。

#### 1. アーキテクチャの概要 (Architecture Overview)

```text
includes/mcp-tools/
├── manager.php                  # すべての能力クラスを自動検出し、登録します
└── abilities/
    ├── class-wp-postviews-ability.php     # 例: 読み取り専用、パラメータなし
    └── class-wp-bot-blocker-ability.php   # 例: 複数の能力、パラメータおよび Enum あり
```

**実行フロー:**

1. `manager.php` → `register_abilities()` → `wp_abilities_api_init` 時に、あなたの `YourClass::register()` を呼び出します。
2. `abilities-integration.php` → `mpu_get_mcp_tools_for_llm()` → 異なる LLM に応じてツールスキーマをフォーマットします。
3. LLM がツールの呼び出しをリクエストする → `mpu_execute_mcp_tool()` → `$ability->execute($args)` → 定義したコールバックを呼び出します。

**管理者権限の制限:** ツールの定義は非管理者訪問者には**送信されません**。`current_user_can('manage_options')` を持つユーザーのみがツールの呼び出しをトリガーできます。この制限は能力自体ではなく、統合レイヤーで強制されます。

#### 2. ステップバイステップ開発ガイド (Step-by-Step SOP)

**ステップ 1: 能力クラスファイルの作成**

ファイルパス: `includes/mcp-tools/abilities/class-{slug}-ability.php`

```php
<?php

namespace MP_Ukagaka\McpTools\Abilities;

class Wp_YourFeature_Ability
{
    public static function register()
    {
        // ガード: wp_register_ability が存在することを確認
        if (!function_exists('wp_register_ability')) {
            return;
        }

        // ガード: 外部プラグインの依存関係をチェック (ある場合)
        if (!function_exists('your_plugin_function')) {
            return;
        }

        wp_register_ability('mp-ukagaka/your-ability-name', array(
            'label'               => __('人間が読める能力名', 'mp-ukagaka'),
            'description'         => __('この能力が何をするか。意味は極めて正確である必要があります。', 'mp-ukagaka'),
            'category'            => 'mp-ukagaka',
            'input_schema'        => array(
                'type'       => 'object',
                'properties' => array(
                    'param_name' => array(
                        'type'        => 'string',
                        'description' => __('正確なパラメータの説明。Enum の場合は、各値に対して「...の時にこの値を使用する」を追加してください。', 'mp-ukagaka'),
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
        // 処理ロジック
        return '結果の文字列または配列';
    }
}
```

**ステップ 2: manager.php でのクラス登録**

`includes/mcp-tools/manager.php` 内の `$abilities` 配列に、クラスの完全な名前空間を追加します：

```php
protected static $abilities = [
    '\MP_Ukagaka\McpTools\Abilities\Wp_PostViews_Ability',
    '\MP_Ukagaka\McpTools\Abilities\Wp_Bot_Blocker_Ability',
    '\MP_Ukagaka\McpTools\Abilities\Wp_YourFeature_Ability',  // ← ここに追加
];
```

**ステップ 3: 登録の確認**

管理者として WordPress にログインし、フリーレンのチャットウィンドウに `/debug_mcp` と入力します。以下の点を確認してください：

- 能力名が "Tools found:" リストに表示されること。
- "Tool count" が追加した数だけ増加していること。

**ステップ 4: チャットテスト**

フリーレンに能力を使用するように依頼します。彼女が応答した後、実際のデータソース (データベース、Option など) で結果が正しいことを確認します。

#### 3. 必須フィールド (Required Fields)

以下の 5 つのフィールドは**絶対に必須**です (いずれかが欠けていると、サイレントエラーが発生します)：

| フィールド            | 必須    | 説明                                                                                                 |
| --------------------- | ------- | ---------------------------------------------------------------------------------------------------- |
| `label`               | **YES** | 欠けている場合、`InvalidArgumentException` がスローされ (内部で飲み込まれる)、能力は登録されません。 |
| `description`         | **YES** | LLM がこのツールをいつ呼び出すべきかを判断する唯一の根拠です。                                       |
| `category`            | **YES** | `'mp-ukagaka'` に設定する必要があります。                                                            |
| `execute_callback`    | **YES** | `[self::class, 'method_name']` の配列形式を使用してください。                                        |
| `permission_callback` | **YES** | `function () { return true; }` と記述してください (管理者チェックは外部呼び出し時に検証済み)。       |

#### 4. input_schema のルール

**パラメータのない能力は `input_schema` を定義「しなければならない」：**

```php
// ✅ 正しい書き方 — LLM は {} を送信し、validate_input は定義されたスキーマを見る → パス
'input_schema' => array(
    'type'       => 'object',
    'properties' => new \stdClass(),  // ← [] ではなく stdClass を使用する必要があります ([] はオブジェクトではなく配列になります)
),

// ❌ 誤った書き方 — LLM は {} (nullではない) を送信し、validate_input がそれを受け取って WP_Error を返す → フリーレンが「情報を取得できなかったよ」と言う
// (input_schema を完全に省略するのは誤りです)
```

**パラメータのある能力：**

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

#### 5. LLM を正確に導くための説明の記述 (Descriptions)

これが最も重要なステップです。曖昧な説明は、LLM が誤ったツールやパラメータを選択する原因となります。

**能力レベルの説明：**
**それが何をするか**と**いつそれを使用するか**を明確に説明します：

```php
// ❌ 曖昧すぎる
'description' => 'Clear the log file or reset the IP ban list.'

// ✅ 意図が明確
'description' => 'Clear the Moelog Bot Blocker intercept records or reset the IP ban list.'
```

**Enum パラメータの説明：**
常に各 Enum 値に対して **"Use this when..." (...の時に使用する)** のガイドを追加します：

```php
// ❌ LLM が推測することになる
'description' => 'What to clear: "logs", "ips", or "both".'

// ✅ ユーザーの意図に基づいて LLM を正確に導く
'description' =>
    '"logs" — データベース内のすべての中止記録を削除します。Use this when the user asks to clear records, history, logs, or intercept data. ' .
    '"ips" — IP ブラックリストのみをクリアします。Use this when the user asks to unblock IPs or reset the ban list. ' .
    '"both" — 両方をクリアします。'
```

**アクションの語彙 (Action vs. Query):**
データベース操作を説明するためにファイルシステムのメタファーを使用しないでください：

- ❌ "log file" 👉 ✅ "log records in the database"
- ❌ "config file" 👉 ✅ "settings stored in WordPress options"
- ❌ "reset file" 👉 ✅ "delete records from the table"

**操作後の検証の戻り値：**
能力がデータを変更する場合は、LLM が正確に報告できるように検証結果を返してください：

```php
public static function clear_callback($args)
{
    moelog_bot_blocker_clear_logs();
    return 'Cleared the intercept log table. All records deleted.'; // 成功したことを LLM に知らせる
}
```

#### 6. 命名規則 (Naming Conventions)

- **Ability name 形式:** `mp-ukagaka/{slug}` — 小文字、数字、およびハイフン (`-`) のみが使用可能です。
  - 正規表現に厳密に従う：`/^[a-z0-9-]+\/[a-z0-9-]+$/`
  - **アンダースコア (`_`) の使用は禁止されています**。
  - ✅ `mp-ukagaka/get-bot-blocker-stats`
  - ❌ `mp-ukagaka/get_bot_blocker_stats`
- **クラス名:** `Wp_{Feature}_Ability` (アンダースコア付きの PascalCase)。
- **ファイル名:** `class-{slug}-ability.php` (kebab-case)。
- **注意:** `abilities-integration.php` は、LLM に送信する際、OpenAI の正規表現の制限に準拠するため、自動的に `/` を `__` に変換します。

#### 7. 外部プラグイン統合パターン (External Plugin Integration Pattern)

**プラグイン独自の公開関数を優先して呼び出し**、DB や Option の直接操作を避けます：

```php
// ✅ プラグイン独自の関数の呼び出し — プラグイン内部のログローテーション、Transient、および Action フックが確実にトリガーされます
moelog_bot_blocker_ban_ip($ip);
moelog_bot_blocker_log('MANUAL_BAN', ['source' => 'Frieren API', 'ip' => $ip]);

// ❌ オプションの直接変更 — プラグインのコアロジックをバイパスします
$banned = get_option('moelog_bot_blocker_banned_ips', []);
$banned[] = $ip;
update_option('moelog_bot_blocker_banned_ips', $banned);
```

#### 8. リリース前のチェックリスト (Checklist Before Shipping)

- [ ] `includes/mcp-tools/abilities/` にクラスファイルを作成した。
- [ ] `manager.php` の `$abilities` 配列にクラスを追加した。
- [ ] 5つの必須フィールドがすべて設定されている (`label`, `description`, `category`, `execute_callback`, `permission_callback`)。
- [ ] `input_schema` が定義されている (パラメータのない能力でも `new \stdClass()` を使用している)。
- [ ] Ability の名前が `mp-ukagaka/{kebab-case}` であり、アンダースコアがない。
- [ ] プラグインの依存関係ガード (`function_exists()`) が追加されている。
- [ ] 説明の意味が正確である — Enum 値には "Use this when..." のガイドが含まれている。
- [ ] `/debug_mcp` でツールがリストに表示されることを確認した。
- [ ] エンドツーエンドのチャットテストに合格し、データが正しく変更される。
