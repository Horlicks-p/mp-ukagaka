# MP Ukagaka 開発者ガイド

> 🛠️ アーキテクチャ説明、拡張開発、API リファレンス

---

## 📑 目次

1. [アーキテクチャ概要](#アーキテクチャ概要)
2. [モジュール説明](#モジュール説明)
3. [データ構造](#データ構造)
4. [Hooks と Filters](#hooks-と-filters)
5. [AJAX エンドポイント](#ajax-エンドポイント)
6. [JavaScript API](#javascript-api)
7. [拡張開発](#拡張開発)
8. [セキュリティ考慮事項](#セキュリティ考慮事項)
9. [開発規約](#開発規約)

---

## アーキテクチャ概要

### ディレクトリ構造

```text
mp-ukagaka/
├── mp-ukagaka.php          # メインエントリーポイント
├── css/                    # スタイルシート
│   ├── mpu_style.css           # フロントエンドスタイルシート
│   └── admin-style.css         # 管理画面スタイルシート
├── includes/               # PHP モジュール
│   ├── core/                   # コア機能モジュール
│   │   ├── core-functions.php      # コア機能（設定管理）
│   │   ├── utility-functions.php   # ユーティリティ関数
│   │   ├── ukagaka-functions.php   # 伺か管理
│   │   └── frontend-functions.php  # フロントエンド機能
│   ├── ajax/                   # AJAX ハンドラーモジュール
│   │   ├── chat/                   # チャットハンドラーサブディレクトリ（v2.5.6）
│   │   │   ├── context-handler.php     # ページ感知チャット
│   │   │   ├── greet-handler.php       # 初回訪問挨拶
│   │   │   └── user-chat-handler.php   # インタラクティブチャット
│   │   ├── ajax-handlers.php       # AJAX 処理（コア）
│   │   ├── ajax-chat-handlers-llm.php  # LLM チャットローダー（v2.5.6，chat/ サブディレクトリを読み込み）
│   │   ├── ajax-touch-handlers-llm.php # LLM タッチ AJAX 処理
│   │   ├── ajax-handlers-test.php  # API 接続テストハンドラー
│   │   └── chat-api-handlers.php   # マルチターンダイアログ API ハンドラー
│   ├── personality/            # パーソナリティシステムモジュール
│   │   ├── personality-loader.php  # パーソナリティシステム（JSON ローダー，v2.4.0）
│   │   ├── personality-prompts.php # パーソナリティプロンプトモジュール
│   │   ├── personality-decorations.php # 装飾品システム
│   │   ├── personality-emoji.php   # 表情システム
│   │   └── emoji-mapper.php        # 表情マッピングと感情分析（v2.4.0）
│   ├── llm/                    # LLM/AI 機能モジュール
│   │   ├── api-cache.php           # API キャッシュシステム（v2.5.6）
│   │   ├── ai-functions.php        # AI 機能（クラウド API + Ollama）
│   │   ├── llm-functions.php       # LLM 機能（Ollama 専用）- BETA
│   │   ├── llm-context-builder.php # LLM コンテキスト構築
│   │   ├── llm-slimstat.php        # LLM Slimstat 統合
│   │   ├── prompt-categories.php   # Prompt カテゴリ指示管理
│   │   ├── weather-functions.php   # 天気機能（Open-Meteo API）
│   │   └── diary-functions.php     # AI 日記機能（v2.5.0）
    │   ├── integrations/           # 統合機能モジュール（v2.7.0）
    │   │   ├── akismet-integration.php # Akismet スパムブロック統合
    │   │   └── turnstile-integration.php # Turnstile 統合
    │   ├── integrations/           # 統合機能モジュール（v2.7.0）
    │   │   └── akismet-integration.php # Akismet スパムブロック統合
│   └── admin-functions.php     # 管理画面機能
├── ghost/                  # キャラクターパーソナリティ設定（v2.4.0）
│   ├── Frieren/
│   │   ├── shell/              # キャラクター画像
│   │   ├── decorations/        # 装飾品画像
│   │   ├── manifest.json       # メタデータと設定
│   │   ├── frieren.js          # キャラクター専用 JavaScript
│   │   ├── frieren-emoji.js    # Frieren 専用表情システム（RO スタイル，v2.4.0）
│   │   ├── emoji-keywords.json # 表情キーワードカスタム設定（v2.4.0）
│   │   └── emojis/             # Frieren 専用表情画像（RO スタイル）
│   └── [その他のキャラクター...]/
├── dialogs/                # ダイアログファイル
├── images/                 # 画像リソース
├── languages/              # 言語ファイル
├── docs/                   # ドキュメント
├── options/                # 管理画面設定ページ
│   ├── options.php             # 管理画面ページフレームワーク
│   ├── options_general.php     # 一般設定ページ
│   ├── options_ukagakas.php    # 伺か管理ページ
│   ├── options_create.php      # 新規伺か作成ページ
│   ├── options_extend.php      # 拡張設定ページ
│   ├── options_dialog.php      # 会話設定ページ
│   ├── options_page_ai.php     # AI 機能設定ページ
│   ├── options_page_llm.php    # LLM 機能設定ページ（BETA）
│   └── options_page_diary.php  # 日記機能設定ページ
├── js/                     # フロントエンド JavaScript モジュール
│   ├── dist/                   # ビルド出力ディレクトリ（本番用）
│   │   ├── ukagaka-bundle.min.js   # 結合・圧縮されたコアバンドル
│   │   └── ukagaka-textarearesizer.min.js  # 管理画面ツール（圧縮版）
│   ├── ukagaka-base.js         # 基盤層（設定 + ユーティリティ + AJAX）
│   ├── ukagaka-core.js         # フロントエンドコア JS（メッセージ表示、伺か切り替えなど）
│   ├── ukagaka-features.js     # フロントエンド機能 JS（AI ページ感知、初回訪問者挨拶など）
│   ├── ukagaka-anime.js        # Canvas アニメーションマネージャー（画像シーケンス再生）
│   ├── ukagaka-chat.js         # チャット機能フロントエンド（v2.3.0）
│   ├── ukagaka-emoji.js        # 表情設定ローダー
│   └── ukagaka-textarearesizer.js  # 管理画面テキストエリアリサイザー
└── readme.txt              # WordPress プラグインディレクトリ説明ファイル
```

### モジュール読み込み順序

プラグインは条件付き読み込み機構を採用し、実行環境（フロントエンド/管理画面）に応じて対応するモジュールを読み込みます：

```php
// mp-ukagaka.php の読み込みロジック

// コアモジュール：フロントエンドと管理画面の両方で必要
$core_modules = [
    'core/debug-functions.php',     // 0. ログシステム（最初に読み込む必要あり）
    'core/core-functions.php',      // 1. コア機能（設定管理）
    'core/utility-functions.php',   // 2. ユーティリティ関数
    'personality/personality-loader.php',  // 3. パーソナリティシステム（JSON ローダー）
    'personality/personality-prompts.php', // 4. パーソナリティプロンプトモジュール
    'personality/personality-decorations.php', // 5. 装飾品システム
    'personality/personality-emoji.php',   // 6. 表情システム
    'stats/stats-collector.php',   // 7. 統計コレクター（ai-functions.php より前に読み込み）
    'stats/stats-analyzer.php',    // 8. 統計アナライザー
    'llm/api-cache.php',           // 9. API キャッシュシステム（v2.5.6，ai-functions.php より前に読み込み）
    'llm/ai-functions.php',        // 10. AI 機能（クラウド API：Gemini, OpenAI, Claude）
    'llm/prompt-categories.php',   // 11. Prompt カテゴリ指示管理（llm-functions.php より前に読み込み）
    'llm/llm-slimstat.php',        // 12. LLM Slimstat 統合（llm-context-builder.php より前に読み込み）
    'llm/llm-context-builder.php', // 13. LLM コンテキスト構築（llm-functions.php より前に読み込み）
    'llm/weather-functions.php',   // 14. 天気機能（Open-Meteo API）
    'llm/diary-functions.php',     // 15. AI 日記機能（v2.5.0）
    'llm/llm-functions.php',       // 16. LLM 機能（ローカル LLM：Ollama）
    'personality/emoji-mapper.php',        // 17. 表情マッピング（AJAX 処理より前に読み込み）
    'core/ukagaka-functions.php',   // 18. 伺か管理
    'ajax/ajax-handlers.php',       // 19. AJAX 処理（コア）
    'ajax/ajax-chat-handlers-llm.php',      // 20. LLM 対話 AJAX 処理
    'ajax/ajax-touch-handlers-llm.php',     // 21. LLM タッチ AJAX 処理
    'ajax/ajax-handlers-test.php',  // 22. API 接続テストハンドラー
    'ajax/chat-api-handlers.php',   // 23. マルチターンダイアログ API ハンドラー
    'integrations/akismet-integration.php', // 24. Akismet スパムブロック統合
    'integrations/turnstile-integration.php', // 25. Turnstile 統合
];

// フロントエンド専用モジュール（非管理画面環境でのみ読み込み）
$frontend_modules = [
    'core/frontend-functions.php',  // フロントエンド機能
];

// 管理画面専用モジュール（管理画面環境でのみ読み込み）
$admin_modules = [
    'admin-functions.php',     // 管理画面機能
];
```

**読み込みタイミング：**

- すべてのコアモジュールは `plugins_loaded` action（優先度 1）で読み込み
- フロントエンドモジュールは `!is_admin()` の場合のみ読み込み
- 管理画面モジュールは `is_admin()` の場合のみ読み込み

### 定数定義

| 定数            | 説明                 | 値         |
| --------------- | -------------------- | ---------- |
| `MPU_VERSION`   | プラグインバージョン   | `"2.5.6"`  |
| `MPU_MAIN_FILE` | メインファイルパス   | `__FILE__` |

---

## モジュール説明

### core-functions.php

コア機能モジュール、設定管理を担当。

#### 主要関数

```php
/**
 * デフォルト設定値を取得
 * @return array デフォルト設定配列
 */
function mpu_default_opt(): array

/**
 * プラグイン設定を取得（キャッシュ付き）
 * @return array 設定配列
 */
function mpu_get_option(): array
```

**注意：** `mpu_count_total_msg()` は `ukagaka-functions.php` モジュールにあります。

### personality-loader.php (v2.4.0)

Personality システムローダーモジュール、JSON ベースのキャラクター設定システムを提供します。異なるキャラクターが PHP コードを変更することなく、JSON ファイルを通じて人格を定義できるようにします。

#### personality-loader.php 主要関数

```php
/**
 * ghost ディレクトリパスを取得（personalities ディレクトリ）
 * @return string 絶対パス
 */
function mpu_get_personalities_dir(): string

/**
 * 現在の personality ID を取得
 * @return string Personality ID（フォルダ名）
 */
function mpu_get_current_personality_id(): string

/**
 * personality が存在するか確認
 * @param string $personality_id Personality フォルダ名
 * @return bool 存在するか
 */
function mpu_personality_exists($personality_id): bool

/**
 * 利用可能なすべての personalities を取得
 * @param bool $include_placeholders プレースホルダーを含むか
 * @return array Personality ID => manifest の連想配列
 */
function mpu_get_available_personalities($include_placeholders = false): array

/**
 * personality manifest を読み込み
 * @param string|null $personality_id Personality ID，null は現在
 * @return array Manifest データ
 */
function mpu_load_personality_manifest($personality_id = null): array

/**
 * personality prompts を読み込み
 * @param string|null $personality_id Personality ID，null は現在
 * @return array プロンプトカテゴリアレイ
 */
function mpu_load_personality_prompts($personality_id = null): array

/**
 * personality weights を読み込み
 * @param string|null $personality_id Personality ID，null は現在
 * @return array 重み設定アレイ
 */
function mpu_load_personality_weights($personality_id = null): array

/**
 * personality decorations 設定を読み込み
 * @param string|null $personality_id Personality ID，null は現在
 * @return array 装飾品設定アレイ
 */
function mpu_load_personality_decorations($personality_id = null): array

/**
 * personality dynamic prompts を読み込み
 * @param string|null $personality_id Personality ID，null は現在
 * @return array 動的プロンプト設定アレイ
 */
function mpu_load_personality_dynamic_prompts($personality_id = null): array

/**
 * personality emoji keywords を読み込み
 * @param string|null $personality_id Personality ID，null は現在
 * @return array 表情キーワード設定アレイ
 */
function mpu_load_personality_emoji_keywords($personality_id = null): array
```

#### Personality ファイル構造

各 personality フォルダには以下を含める必要があります：

- **manifest.json**（必須）：メタデータと設定
  - `id`：Personality ID
  - `name`、`name_en`、`name_zh`：多言語名
  - `version`：バージョン番号
  - `settings`：キャラクター設定（例：`max_response_length`、`speech_style`、`tone`）
  - `character_traits`：キャラクター特性（例：`age`、`race`、`occupation`、`personality`）

- **prompts.json**（オプション）：静的ダイアログカテゴリ
  - キーはカテゴリ名、値はプロンプト配列

- **dynamics.json**（オプション）：動的テンプレート（変数置換付き）
  - `{variable_name}` 変数置換をサポート
  - `time_aware_dynamic`、`tech_observation`、`bot_detection` などのカテゴリを含む

- **weights.json**（オプション）：カテゴリ重み設定
  - `base_weights`：基本重み
  - `time_adjustments`：時間帯ごとの調整

- **decorations.json**（オプション）：装飾品クリックプロンプト
  - `items`：装飾品設定配列、各項目には以下が含まれます：
    - `id`：装飾品 ID
    - `image`：画像パス（`decorations/` フォルダからの相対パス）
    - `position`：位置設定（例：`{"bottom": "0px", "right": "0px"}`）
    - `size`：サイズ設定（例：`{"width": "100px", "height": "auto"}`）
    - `z_index`：Z-index（数値）
    - `prompt`：クリック時のプロンプト
    - `transform`：CSS 変形（オプション、例：`scale(1)`）

- **emoji-keywords.json**（オプション，v2.4.0）：表情トリガーキーワード
  - `mappings`：表情タイプとキーワードのマッピング
  - フォーマット例：
    ```json
    {
      "mappings": {
        "happy": {
          "keywords": ["嬉しい", "happy"],
          "file": "happy.png",
          "weight": 10
        }
      }
    }
    ```

    - **diary.json**（オプション、v2.5.0）：AI 日記設定
      - `categories`：日記カテゴリ設定
      - フォーマット例：
        ```json
        {
          "categories": {
            "daily": {
              "weight": 10,
              "title_themes": ["日常"],
              "prompts": ["日常に関する日記を書いてください"]
            }
          }
        }
        ```

- **script**（オプション）：キャラクター専用 JavaScript ファイル
  - 例：`frieren.js`、フロントエンドによって自動的に読み込まれます

### utility-functions.php

ユーティリティ関数モジュール、各種ヘルパー機能を提供（文字列処理、フィルター、ファイル操作、暗号化など）。

#### 文字列/配列変換

```php
/**
 * 配列を文字列に変換（二重改行で区切り）
 * @param array $arr 入力配列
 * @return string 出力文字列
 */
function mpu_array2str($arr = []): string

/**
 * 文字列を配列に変換（改行で区切り、空行をフィルター）
 * @param string $str 入力文字列
 * @return array 出力配列
 */
function mpu_str2array($str = ""): array
```

#### 出力フィルター

```php
/**
 * HTML 出力フィルター（esc_html を使用）
 */
function mpu_output_filter($str): string

/**
 * JavaScript 出力フィルター（esc_js を使用）
 */
function mpu_js_filter($str): string

/**
 * 入力フィルター（stripslashes）
 */
function mpu_input_filter($str): string

/**
 * HTML デコード
 */
function mpu_html_decode($str): string
```

#### 安全なファイル操作

```php
/**
 * 安全なファイル読み込み（WordPress Filesystem API を使用）
 * @param string $file_path ファイルパス
 * @return string|WP_Error ファイル内容またはエラー
 */
function mpu_secure_file_read($file_path)

/**
 * 安全なファイル書き込み（WordPress Filesystem API を使用）
 * @param string $file_path ファイルパス
 * @param string $content ファイル内容
 * @return bool|WP_Error 成功またはエラー
 */
function mpu_secure_file_write($file_path, $content)
```

#### API Key 暗号化

```php
/**
 * API Key を暗号化（AES-256-CBC）
 */
function mpu_encrypt_api_key($api_key): string

/**
 * API Key を復号
 */
function mpu_decrypt_api_key($encrypted_key)

/**
 * API Key が暗号化されているか確認
 */
function mpu_is_api_key_encrypted($api_key): bool
```

### ai-functions.php

AI 機能モジュール、クラウド AI API 呼び出し（Gemini、OpenAI、Claude）と Ollama 統合を処理。

#### サポートされている AI プロバイダー

| プロバイダー | 関数                    | API エンドポイント                  | モデル選択 |
| ------------ | ----------------------- | ----------------------------------- | ---------- |
| Gemini       | `mpu_call_gemini_api()` | `generativelanguage.googleapis.com` | サポート   |
| OpenAI       | `mpu_call_openai_api()` | `api.openai.com`                    | サポート   |
| Claude       | `mpu_call_claude_api()` | `api.anthropic.com`                 | サポート   |
| Ollama       | `mpu_call_ollama_api()` | ローカルまたはリモート Ollama サービス | サポート   |

### llm-functions.php (BETA)

> ⚠️ **注意**：このモジュールは**テスト段階（BETA）**で、API が変更される可能性があります。

LLM 機能モジュール、Ollama ローカル LLM 統合を専門に処理。

#### タイムアウト設定

| 操作タイプ                    | ローカル接続 | リモート接続 |
| --------------------------- | ------------ | ------------ |
| サービスチェック (`check`)   | 3 秒         | 10 秒        |
| API 呼び出し (`api_call`)   | 60 秒        | 90 秒        |
| 接続テスト (`test`)         | 30 秒        | 45 秒        |

### diary-functions.php (v2.5.0)

AI 日記機能モジュール、キャラクター日記の自動生成と投稿を担当。

#### diary-functions.php 主要関数

```php
/**
 * 日記タイトルプレフィックスを取得
 * @param string|null $personality_id パーソナリティ ID
 * @return string プレフィックス（例："[フリーレン手記] "）
 */
function mpu_get_diary_title_prefix($personality_id = null): string

/**
 * 日記をトリガーすべきか判定（確率と1日1回制限に基づく）
 * @return bool トリガーすべきか
 */
function mpu_should_trigger_diary(): bool

/**
 * 日記コンテンツを生成
 * @return array|WP_Error 日記データまたはエラー
 */
function mpu_generate_diary_content()

/**
 * 日記投稿を公開
 * @param array $diary_data 日記データ
 * @return int|WP_Error 投稿 ID またはエラー
 */
function mpu_publish_diary_post($diary_data)
```

### ukagaka-functions.php

伺か管理モジュール、キャラクター関連操作とダイアログ管理を処理。

#### ukagaka-functions.php 主要関数

```php
/**
 * 伺かリストの HTML を取得
 * @return string HTML 文字列
 */
function mpu_ukagaka_list(): string

/**
 * 伺かデータを取得
 * @param string|false $num 伺かキー（false は現在の伺か）
 * @return array|false 伺かデータまたは false
 */
function mpu_get_ukagaka($num = false)

/**
 * 伺かのシェル画像 URL を取得
 * @param string|false $num 伺かキー（false は現在の伺か）
 * @param bool $echo 直接出力するかどうか
 * @return string 画像 URL
 */
function mpu_get_shell($num = false, $echo = false): string

/**
 * 指定したメッセージを取得
 * @param int $msgnum メッセージインデックス
 * @param string|false $num 伺かキー
 * @param bool $echo 直接出力するかどうか
 * @return string メッセージ内容
 */
function mpu_get_msg($msgnum = 0, $num = false, $echo = false): string

/**
 * ランダムなメッセージを取得
 * @param string|false $num 伺かキー
 * @param bool $echo 直接出力するかどうか
 * @return string メッセージ内容
 */
function mpu_get_random_msg($num = false, $echo = false): string

/**
 * デフォルトメッセージを取得
 * @param string|false $num 伺かキー
 * @param bool $echo 直接出力するかどうか
 * @return string メッセージ内容
 */
function mpu_get_default_msg($num = false, $echo = false): string

/**
 * 共通メッセージを取得
 * @return string 共通メッセージ内容
 */
function mpu_common_msg(): string

/**
 * メッセージ配列を取得
 * @param string|false $num 伺かキー
 * @return array メッセージ配列
 */
function mpu_get_msg_arr($num = false): array

/**
 * 次のメッセージを取得
 * @param string|false $num 伺かキー
 * @param int $msgnum 現在のメッセージインデックス
 * @return array メッセージとインデックスを含む配列
 */
function mpu_get_next_msg($num = false, $msgnum = 0): array

/**
 * メッセージ内の特殊コードを処理
 * @param array $msglist メッセージ配列
 * @return array 処理後のメッセージ配列
 */
function mpu_msg_code($msglist = []): array
```

### ajax-handlers.php

AJAX 処理モジュール、すべての AJAX リクエストを処理。

#### ajax-handlers.php 主要関数

```php
/**
 * 次のメッセージリクエストを処理
 */
function mpu_ajax_nextmsg()

/**
 * 拡張機能リクエストを処理
 */
function mpu_ajax_extend()

/**
 * 伺か切り替えリクエストを処理
 */
function mpu_ajax_change()

/**
 * 設定取得リクエストを処理
 */
function mpu_ajax_get_settings()

/**
 * ダイアログ読み込みリクエストを処理
 */
function mpu_ajax_load_dialog()

/**
 * 訪問者情報取得リクエストを処理（Slimstat が必要）
 */
function mpu_ajax_get_visitor_info()
```

### frontend-functions.php

フロントエンド機能モジュール、ページ表示とリソース読み込みを担当。

#### frontend-functions.php 主要関数

```php
/**
 * 現在のページに表示すべきか確認
 * @return bool 表示すべきか
 */
function mpu_is_show_page(): bool

/**
 * 出力バッファコールバック（HTML 挿入用）
 * @param string $buffer ページ内容
 * @return string 処理後の内容
 */
function mpu_ob_callback($buffer): string

/**
 * シャットダウン時のコールバック（HTML 挿入を保証）
 */
function mpu_shutdown_callback(): void

/**
 * 伺か HTML を生成
 * @param string|false $num 伺かキー
 * @return string HTML 文字列
 */
function mpu_html($num = false): string

/**
 * 伺か HTML を出力
 */
function mpu_echo_html(): void

/**
 * フロントエンドリソース（CSS/JS）を読み込み
 */
function mpu_enqueue_frontend_assets(): void

/**
 * head 内に設定（JavaScript 変数）を出力
 */
function mpu_head(): void
```

### admin-functions.php

管理画面機能モジュール、設定保存と管理画面インターフェースを処理。

#### admin-functions.php 主要関数

```php
/**
 * 管理画面リソース（CSS/JS）を読み込み
 * @param string $hook_suffix 現在のページフック
 */
function mpu_admin_enqueue_scripts($hook_suffix): void

/**
 * 設定保存を処理
 */
function mpu_handle_options_save(): void

/**
 * ダイアログファイルを生成（TXT または JSON）
 * @param string $filename ファイル名（拡張子なし）
 * @param array $msg_array メッセージ配列
 * @param string $ext 拡張子（txt または json）
 * @return bool 成功したか
 */
function mpu_generate_dialog_file($filename, $msg_array, $ext): bool

/**
 * 設定ページ HTML を表示
 */
function mpu_options_page_html(): void

/**
 * 管理メニューを登録
 */
function mpu_options(): void
```

---

## データ構造

### 設定構造 ($mpu_opt)

```php
$mpu_opt = [
    // 基本設定
    'cur_ukagaka' => 'default_1',      // 現在の伺か
    'show_ukagaka' => true,             // 伺かを表示するか
    'show_msg' => true,                 // 吹き出しを表示するか
    'default_msg' => 0,                 // 0=ランダム, 1=最初の一つ
    'next_msg' => 0,                    // 0=順序, 1=ランダム
    'click_ukagaka' => 0,               // 0=次へ, 1=何もしない
    
    // 自動ダイアログ
    'auto_talk' => true,                // 自動ダイアログを有効化
    'auto_talk_interval' => 8,          // 自動ダイアログ間隔（秒）
    'typewriter_speed' => 40,           // タイプ速度（ミリ秒/文字）
    
    // 外部ダイアログファイル
    'use_external_file' => true,        // 外部ファイルを使用
    'external_file_format' => 'txt',     // ファイル形式（txt/json）
    
    // AI 設定（ページ感知機能）
    'ai_enabled' => false,              // AI を有効化
    'ai_provider' => 'gemini',          // AI プロバイダー
    'ai_api_key' => '',                 // Gemini API Key（暗号化）
    'gemini_model' => 'gemini-2.5-flash', // Gemini モデル
    'openai_api_key' => '',             // OpenAI API Key（暗号化）
    'openai_model' => 'gpt-4.1-mini-2025-04-14',    // OpenAI モデル
    'claude_api_key' => '',             // Claude API Key（暗号化）
    'claude_model' => 'claude-sonnet-4-5-20250929', // Claude モデル
    'ai_language' => 'zh-TW',           // AI 応答言語
    'ai_system_prompt' => '',           // AI 人格設定
    'ai_probability' => 10,             // AI トリガー確率（0-100）
    'ai_trigger_pages' => 'is_single',  // トリガーページ条件
    'ai_display_duration' => 8,         // AI 表示時間（秒）
    
    // LLM 設定 (BETA)
    'ollama_endpoint' => 'http://localhost:11434',  // Ollama エンドポイント
    'ollama_model' => 'qwen3:8b',                   // Ollama モデル
    'ollama_replace_dialogue' => false,              // LLM で内蔵ダイアログを置換
    'ollama_disable_thinking' => true,               // 思考モードを無効化
    
    // 伺かリスト
    'ukagakas' => [
        'default_1' => [
            'name' => 'フリーレン',
            'shell' => 'images/shell/Frieren/',
            'msg' => ['フリレーンだ。千年以上生きた魔法使いだ。'],
            'show' => true,
            'dialog_filename' => 'Frieren',
        ],
        // ... 他の伺か
    ],
];
```

### 伺か構造

```php
$ukagaka = [
    'name' => 'フリーレン',               // 名前
    'shell' => 'https://...png',      // 画像 URL
    'msg' => [                        // ダイアログ配列
        'ダイアログ 1',
        'ダイアログ 2',
    ],
    'show' => true,                   // 表示するか
    'dialog_filename' => 'frieren',   // ダイアログファイル名
];
```

---

## Hooks と Filters

### Actions

```php
// プラグイン読み込み後
do_action('mpu_loaded');

// 伺か HTML 生成前
do_action('mpu_before_html');

// 伺か HTML 生成後
do_action('mpu_after_html');

// 設定保存後
do_action('mpu_settings_saved', $mpu_opt);
```

### Filters

```php
// 設定配列をフィルター
$mpu_opt = apply_filters('mpu_options', $mpu_opt);

// 伺か HTML をフィルター
$html = apply_filters('mpu_html', $html, $ukagaka);

// メッセージをフィルター
$message = apply_filters('mpu_message', $message, $ukagaka_key);

// AI 応答をフィルター
$response = apply_filters('mpu_ai_response', $response, $provider);
```

---

## AJAX エンドポイント

### 公開エンドポイント（wp_ajax_nopriv_*）

| アクション               | 説明                 | パラメータ                   |
| ---------------------- | -------------------- | -------------------------- |
| `mpu_nextmsg`          | 次のメッセージを取得 | `cur_num`, `cur_msgnum`    |
| `mpu_extend`           | 拡張機能を実行       | 状況による                 |
| `mpu_change`           | 伺かを切り替え       | `new_num`                  |
| `mpu_get_settings`     | 設定を取得           | なし                       |
| `mpu_load_dialog`      | ダイアログファイルを読み込み | `filename`, `format`       |
| `mpu_chat_context`     | AI ページ感知       | `post_content`, `post_title` |
| `mpu_get_visitor_info` | 訪問者情報を取得     | なし                       |
| `mpu_chat_greet`       | 初回訪問者挨拶       | `visitor_info`             |

### 管理画面エンドポイント（wp_ajax_*）

| アクション            | 説明               |
| ------------------- | ------------------ |
| `mpu_test_ollama`   | Ollama 接続テスト   |
| `mpu_test_gemini`   | Gemini 接続テスト   |
| `mpu_test_openai`   | OpenAI 接続テスト   |
| `mpu_test_claude`   | Claude 接続テスト   |

---

## JavaScript API

### グローバルオブジェクト

```javascript
// 設定オブジェクト
window.mpuConfig = {
    ajaxUrl: '/wp-admin/admin-ajax.php',
    nonce: 'xxx',
    currentUkagaka: 'default_1',
    autoTalkInterval: 8000,
    typewriterSpeed: 40,
    // ...
};

// Canvas マネージャー
window.mpuCanvasManager = {
    init(shellInfo, name) {},
    playAnimation() {},
    stopAnimation() {},
    isAnimationMode() {},
    // ...
};
```

### 主要関数

```javascript
// メッセージを表示（タイプライター効果）
mpu_typewriter(message, element, speed);

// 伺かを切り替え
mpuChange(newUkagakaKey);

// 次のメッセージを取得
mpuNextMsg();

// AI ページ感知を実行
mpuChatContext(postContent, postTitle);

// 初回訪問者に挨拶
mpuGreetFirstVisitor();
```

---

## 拡張開発

### 新しい AI プロバイダーを追加

1. `ai-functions.php` に API 呼び出し関数を追加：

```php
function mpu_call_newprovider_api($api_key, $model, $system_prompt, $user_prompt, $language) {
    // API 呼び出しロジックを実装
}
```

2\. `mpu_call_ai_api()` にプロバイダー分岐を追加

3\. 管理画面に設定フィールドを追加

### 新しい AJAX エンドポイントを追加

```php
// includes/ajax-handlers.php に追加
function mpu_ajax_custom_action() {
    check_ajax_referer('mpu_nonce', 'nonce');
    
    // ロジックを実装
    
    wp_send_json_success(['data' => $result]);
}
add_action('wp_ajax_mpu_custom_action', 'mpu_ajax_custom_action');
add_action('wp_ajax_nopriv_mpu_custom_action', 'mpu_ajax_custom_action');
```

---

## セキュリティ考慮事項

### 入力検証

- すべてのユーザー入力を `sanitize_*` 関数でサニタイズ
- ファイルパスを検証してディレクトリトラバーサルを防止
- nonce を使用して CSRF を防止

### 出力エスケープ

- HTML 出力に `esc_html()` を使用
- 属性に `esc_attr()` を使用
- URL に `esc_url()` を使用
- JavaScript に `esc_js()` を使用

### API Key 保護

- すべての API Key を AES-256-CBC で暗号化
- 平文で API Key を保存しない
- API Key をログに記録しない

---

## 開発規約

### 命名規約

- 関数：`mpu_` プレフィックス + snake_case
- フック：`mpu_` プレフィックス + snake_case
- JavaScript：camelCase
- CSS クラス：`mpu-` プレフィックス + kebab-case

### コードスタイル

- PHP：WordPress Coding Standards に従う
- JavaScript：ESLint 推奨ルールに従う
- インデント：タブ（スペース 4 つ相当）

### ドキュメント

- すべての公開関数に PHPDoc を追加
- 複雑なロジックにコメントを追加
- README とドキュメントを更新

---

## SPA（シングルページアプリケーション）統合

MP Ukagaka は SPA ナビゲーションをサポートしています。テーマが完全なページリフレッシュではなく AJAX でページコンテンツを読み込む場合、プラグインに再初期化を通知する必要があります。

### イベントトリガー

テーマは SPA ナビゲーション完了後に `mpu:spaReady` イベントをディスパッチする必要があります：

```javascript
// SPA ナビゲーション完了後にディスパッチ
document.dispatchEvent(new CustomEvent('mpu:spaReady', {
    detail: {
        url: window.location.href,    // オプション：現在の URL
        title: document.title         // オプション：ページタイトル
    }
}));
```

### プラグインの応答

プラグインはこのイベントをリッスンし、以下を実行します：

1. 自動対話タイマーを停止して再起動
2. ページ感知 AI を再トリガー（有効な場合）
3. ページコンテキスト情報を更新

### テーマ統合例

```javascript
// History API を使用した SPA ナビゲーション例
document.addEventListener('click', function(e) {
    const link = e.target.closest('a');
    if (!link || link.target === '_blank') return;
    
    e.preventDefault();
    
    // AJAX 読み込みを実行...
    fetch(link.href)
        .then(response => response.text())
        .then(html => {
            // ページコンテンツを更新
            document.getElementById('content').innerHTML = html;
            history.pushState({}, '', link.href);
            
            // MP Ukagaka に通知
            document.dispatchEvent(new CustomEvent('mpu:spaReady'));
        });
});

// ブラウザの戻る/進むを処理
window.addEventListener('popstate', function() {
    // ページコンテンツ読み込み後...
    document.dispatchEvent(new CustomEvent('mpu:spaReady'));
});
```

### 注意事項

- DOM 更新が完了した後にイベントをディスパッチしてください
- プラグインは自動的に対話状態を維持します
- チャット履歴は同じセッション内で保持されます

---

### Made with ❤ for WordPress
