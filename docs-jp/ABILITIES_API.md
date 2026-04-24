# WordPress Abilities API 統合ガイド

**MP Ukagaka** は **Abilities API** を通じて、あなたの AI キャラクター（Ukagaka）に WordPress サイトと直接対話する能力を与えます。

この機能により、AI キャラクターは登録された「能力（Abilities）」を使用してサイト情報を照会したり、記事を管理したり、その他の WordPress 機能を実行したりすることができ、単なるチャットボットからサイト管理アシスタントへと進化します。

## Abilities API とは？

**Abilities API** は、WordPress の能力（Abilities）を登録、発見、実行するための標準化された方法を提供します。

MP Ukagaka にとって、これはキャラクターが WordPress 側で登録された能力を LLM が呼び出し可能なツール（tools）に変換できることを意味します。これにより、キャラクターは適切な状況でサイト情報を照会したり、制御されたアクションをトリガーしたりできます。

## Abilities API と MCP 命名の関係

現在、プラグインは**対外的な概念としては** Abilities API をメインとしていますが、**内部の実装における命名**には MCP の名称が一部残されています。これは、下位互換性を維持し、リファクタリングのリスクを減らすためです。

例えば：

- 統合ファイルは引き続き `includes/integrations/abilities-integration.php` です
- 内部関数名は引き続き `mpu_get_mcp_tools_for_llm()`、`mpu_execute_mcp_tool()` です
- 内部ディレクトリは引き続き `includes/mcp-tools/` を使用しています

これは、プラグインが依然として古い MCP アダプターに依存していることを意味するのではなく、以下を示しています：

- **内部**：実装層の移行期間として、既存の MCP 命名を踏襲する
- **外部**：能力の登録と発見のための公式インターフェースとして、WordPress Abilities API を使用する

MP Ukagaka にサイト内で直接これらの能力を使用させる場合は、**Abilities API** を基準とするべきです。
サイト内の能力を外部のエージェント（他の MCP をサポートするツールなど）に公開したい場合にのみ、追加の変換レイヤーが必要になる可能性があります。

## サポートされているモデル

現在、このプラグインは Tool Calling 機能を備えた複数のプロバイダーをサポートしています：

- **Google Gemini**
- **Anthropic Claude**
- **OpenAI**
- **Ollama**

実際に使用可能なモデルは、プラグインの現在の設定と対応するプロバイダーの実装に基づきます。

## 権限とセキュリティ

機密性の高い操作（ファイル操作、記事の削除など）を含むコア能力が管理者以外のユーザーによってトリガーされた場合、システムは自動的にブロックし、権限不足のプロンプトを返して安全性を確保します。

![権限ブロックの概念図](../screenshot6.PNG)

*権限制御：管理者権限のない役割がツール呼び出しをトリガーした際のシステムの反応*

## 能力の追加方法 (開発者ガイド)

MP Ukagaka は、WordPress コアに登録されたすべての Abilities を自動的に検出し、使用します。
能力は以下の2つの方法で追加できます：

### 方法 1：標準的な WordPress の方法 (すべてのプラグイン/テーマに適用可能)

これは WordPress 公式の標準的な方法です。どのプラグインでもこのように能力を登録でき、MP Ukagaka は自動的にそれを読み取って使用します。

```php
// カスタムプラグインまたはテーマの functions.php 内
add_action( 'wp_abilities_api_init', function() {
    if ( function_exists( 'wp_register_ability' ) ) {
        wp_register_ability(
            'my-plugin/say-hello', // 能力の一意の識別子
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

### 方法 2：MP Ukagaka モジュール方式 (本プラグインの開発 / AI 機能構築ガイドとして推奨)

`mp-ukagaka` プラグイン自体を直接開発する場合は、組み込みのモジュールアーキテクチャに合わせることをお勧めします。以下は `class-wp-postviews-ability.php` および `class-wp-bot-blocker-ability.php` の開発経験に基づいてまとめられた**ステップバイステップ開発ガイド (SOP) と落とし穴回避ガイド**です。

#### 1. アーキテクチャの概要 (Architecture Overview)

```text
includes/mcp-tools/
├── manager.php                  # すべての能力クラスを自動発見・登録
└── abilities/
    ├── class-wp-postviews-ability.php     # 例：読み取り専用、パラメータなし
    └── class-wp-bot-blocker-ability.php   # 例：複数の能力、パラメータおよび Enum あり
```

**実行フロー：**

1. `manager.php` → `register_abilities()` → `wp_abilities_api_init` 時にあなたの `YourClass::register()` を呼び出します。
2. `abilities-integration.php` → `mpu_get_mcp_tools_for_llm()` → 異なる LLM プロバイダー形式に従ってツールスキーマをフォーマットします。
3. LLM リクエストがツールを呼び出します → `mpu_execute_mcp_tool()` → `$ability->execute($args)` → 定義したコールバックを呼び出します。

**管理者権限の制限：** ツールの定義は管理者以外の訪問者には**送信されません**。`current_user_can('manage_options')` を持つユーザーのみがツール呼び出しをトリガーできます。この制限は能力自体ではなく、統合層で強制されます。

#### 2. ステップバイステップ開発ガイド (Step-by-Step SOP)

**ステップ 1：能力クラスファイルの作成**

ファイルパス：`includes/mcp-tools/abilities/class-{slug}-ability.php`

```php
<?php

namespace MP_Ukagaka\McpTools\Abilities;

class Wp_YourFeature_Ability
{
    public static function register()
    {
        // ガード：wp_register_ability が存在することを確認
        if (!function_exists('wp_register_ability')) {
            return;
        }

        // ガード：外部プラグインの依存関係をチェック (ある場合)
        if (!function_exists('your_plugin_function')) {
            return;
        }

        wp_register_ability('mp-ukagaka/your-ability-name', array(
            'label'               => __('人間が読める能力名', 'mp-ukagaka'),
            'description'         => __('この能力の役割。意味付けは非常に正確でなければなりません。', 'mp-ukagaka'),
            'category'            => 'mp-ukagaka',
            'input_schema'        => array(
                'type'       => 'object',
                'properties' => array(
                    'param_name' => array(
                        'type'        => 'string',
                        'description' => __('正確なパラメータの説明。Enum の場合は、各値に「〜の時にこの値を使用する」と追加してください。', 'mp-ukagaka'),
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

**ステップ 2：manager.php へのクラスの登録**

完全なクラス名前空間を `includes/mcp-tools/manager.php` 内の `$abilities` 配列に追加します：

```php
protected static $abilities = [
    '\MP_Ukagaka\McpTools\Abilities\Wp_PostViews_Ability',
    '\MP_Ukagaka\McpTools\Abilities\Wp_Bot_Blocker_Ability',
    '\MP_Ukagaka\McpTools\Abilities\Wp_YourFeature_Ability',  // ← ここに追加
];
```

**ステップ 3：登録の確認**

管理者として WordPress にログインし、フリーレンのチャットウィンドウに `/debug_mcp` と入力します。以下を確認してください：

- 能力名が "Tools found:" のリストに表示されていること。
- "Tool count" が追加した数だけ増えていること。

**ステップ 4：対話テスト**

フリーレンにその能力を使うように頼みます。彼女の応答後、実際のデータソース（データベース、Option など）にアクセスして、結果が正しいか検証します。

#### 3. 必須フィールド (Required Fields)

以下の 5 つのフィールドは**絶対に必須**です（いずれか一つでも欠けていると、エラーを出さずに失敗します）：

| フィールド              | 必須    | 説明                                                                    |
| --------------------- | ------- | ----------------------------------------------------------------------- |
| `label`               | **YES** | 欠落していると `InvalidArgumentException` がスローされ（下位層に吸収される）、能力は登録されません。 |
| `description`         | **YES** | LLM がいつこのツールを呼び出すべきかを判断する唯一の根拠です。                                    |
| `category`            | **YES** | `'mp-ukagaka'` に設定する必要があります。                                             |
| `execute_callback`    | **YES** | `[self::class, 'method_name']` の配列形式を使用してください。                        |
| `permission_callback` | **YES** | `function () { return true; }` と記述してください（管理者チェックは外層での呼び出し時に検証されます）。    |

#### 4. input_schema ルール

**パラメータのない能力は「必ず」input_schema を定義する必要があります：**

```php
// ✅ 正しい書き方 — LLM は {} を送信し、validate_input は定義されたスキーマを見て → 通過します
'input_schema' => array(
    'type'       => 'object',
    'properties' => new \stdClass(),  // ← 必須：stdClass を使用してください。[] は配列に変換され、オブジェクトになりません。
),

// ❌ 誤った書き方 — LLM は {} を送信し（null ではない）、validate_input がそれを受け取って WP_Error を返します → フリーレンは「情報を取得できなかったよ」と言います。
// （input_schema を完全に省略するのは誤りです）
```

**パラメータを持つ能力：**

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

#### 5. LLM を正確に導くための説明 (Descriptions) の記述

これが最も重要なステップです。曖昧な説明は、LLM が間違ったツールやパラメータを選択する原因になります。

**能力レベルの説明：**
**その役割**と**いつ使用すべきか**を明確に説明します：

```php
// ❌ 曖昧すぎる
'description' => 'Clear the log file or reset the IP ban list.'

// ✅ 意図が明確
'description' => 'Clear the Moelog Bot Blocker intercept records or reset the IP ban list.'
```

**Enum パラメータの説明：**
必ず、各 Enum 値に対して **"Use this when..." (〜の時にこれを使用する)** という指針を追加します：

```php
// ❌ LLM が適当に推測してしまう
'description' => 'What to clear: "logs", "ips", or "both".'

// ✅ ユーザーの意図に基づいて正確に導く
'description' =>
    '"logs" — データベース内のすべての傍受記録を削除します。Use this when the user asks to clear records, history, logs, or intercept data. ' .
    '"ips" — IP ブラックリストのみをクリアします。Use this when the user asks to unblock IPs or reset the ban list. ' .
    '"both" — 両方をクリアします。'
```

**アクションの語彙 (Action vs. Query)：**
データベース操作を説明するためにファイルシステムのメタファーを使用しないでください：

- ❌ "log file" 👉 ✅ "log records in the database"
- ❌ "config file" 👉 ✅ "settings stored in WordPress options"
- ❌ "reset file" 👉 ✅ "delete records from the table"

**操作後の検証戻り値：**
能力がデータを変更する場合、LLM が正確に報告できるように検証結果を返してください：

```php
public static function clear_callback($args)
{
    moelog_bot_blocker_clear_logs();
    return 'Cleared the intercept log table. All records deleted.'; // 成功したことを LLM に伝える
}
```

#### 6. 命名規則 (Naming Conventions)

- **Ability name のフォーマット:** `mp-ukagaka/{slug}` — 小文字のアルファベット、数字、ハイフン (`-`) のみが使用可能です。
  - 正規表現に厳密に準拠：`/^[a-z0-9-]+\/[a-z0-9-]+$/`
  - **アンダースコア (`_`) は使用禁止です**。
  - ✅ `mp-ukagaka/get-bot-blocker-stats`
  - ❌ `mp-ukagaka/get_bot_blocker_stats`
- **クラス名:** `Wp_{Feature}_Ability` (PascalCase にアンダースコアを使用)。
- **ファイル名:** `class-{slug}-ability.php` (kebab-case)。
- **注意:** `abilities-integration.php` が LLM に送信される際、`/` は自動的に `__` に変換されます（OpenAI の正規表現制限に準拠するため）。

#### 7. 外部プラグイン統合パターン (External Plugin Integration Pattern)

**そのプラグインの公開関数を優先して呼び出し**、DB や Option を直接操作することは避けてください：

```php
// ✅ プラグイン独自の関数を呼び出す — プラグイン内部のログローテーション、Transient、Action フックが確実にトリガーされます
moelog_bot_blocker_ban_ip($ip);
moelog_bot_blocker_log('MANUAL_BAN', ['source' => 'Frieren API', 'ip' => $ip]);

// ❌ option を直接変更する — プラグインのコアロジックをバイパスしてしまいます
$banned = get_option('moelog_bot_blocker_banned_ips', []);
$banned[] = $ip;
update_option('moelog_bot_blocker_banned_ips', $banned);
```

#### 8. リリース前のチェックリスト (Checklist Before Shipping)

- [ ] `includes/mcp-tools/abilities/` にクラスファイルを作成した。
- [ ] クラスを `manager.php` の `$abilities` 配列に追加した。
- [ ] 5 つの必須フィールドをすべて設定した (`label`, `description`, `category`, `execute_callback`, `permission_callback`)。
- [ ] `input_schema` を定義した（パラメータのない能力でも `new \stdClass()` を使用している）。
- [ ] Ability の名前は `mp-ukagaka/{kebab-case}` であり、アンダースコアは含まれていない。
- [ ] プラグインの依存関係ガード (`function_exists()`) を追加した。
- [ ] 意味付けの記述が正確である — Enum 値には "Use this when..." の導きがある。
- [ ] `/debug_mcp` でツールがリストに表示されることを確認した。
- [ ] エンドツーエンドの対話テストに合格し、データが正しく変更された。
