# MP Ukagaka 開発者ガイド

> 🛠️ アーキテクチャの概要、拡張開発、および API リファレンス

---

## 📑 目次

1. [アーキテクチャの概要](#アーキテクチャの概要)
2. [モジュールの説明](#モジュールの説明)
3. [データ構造](#データ構造)
4. [フックとフィルター](#フックとフィルター)
5. [REST エンドポイント](#rest-エンドポイント)
6. [JavaScript API](#javascript-api)
7. [拡張開発](#拡張開発)
8. [セキュリティの考慮事項](#セキュリティの考慮事項)
9. [開発標準](#開発標準)

---

## アーキテクチャの概要

### ディレクトリ構造

```text
mp-ukagaka/
├── mp-ukagaka.php          # メインプラグインエントリポイント
├── css/                    # スタイルシート
│   ├── mpu_style.css           # フロントエンドスタイルシート
│   └── admin-style.css         # 管理画面スタイルシート
├── includes/               # PHP モジュール
│   ├── core/                   # コア機能モジュール
│   │   ├── debug-functions.php     # ログシステム（最初に読み込む必要があります）
│   │   ├── core-functions.php      # コア機能（設定管理）
│   │   ├── utility-functions.php   # ユーティリティ関数 / 定数定義
│   │   ├── template-functions.php  # テンプレート読み込みと文字列処理
│   │   ├── file-functions.php      # セキュアなファイル操作とディレクトリヘルパー
│   │   ├── encryption-functions.php# API キー暗号化 / 復号ヘルパー
│   │   ├── wp-info-functions.php   # WordPress 情報抽出
│   │   ├── network-functions.php   # ネットワークリクエストヘルパー
│   │   ├── runtime-state-functions.php # ランタイムスコープの状態ヘルパー
│   │   ├── class-mpu-input-role.php # LLM / ツール入力ロール解決
│   │   ├── class-mpu-observation-buffer.php # セッションスコープの訪問者活動バッファ
│   │   ├── class-mpu-log-i18n-builder.php # フロントエンドコンソールログ i18n ペイロードビルダー
│   │   ├── ukagaka-functions.php   # 伺か管理
│   │   └── frontend-functions.php  # フロントエンド機能
│   ├── chat/                   # 会話フローサービス
│   │   └── class-mpu-chat-history-service.php # 会話履歴サービス（verify/store/slice）
│   ├── rest/                   # REST API 処理モジュール（オブジェクト指向アーキテクチャ）
│   │   ├── bootstrap.php           # REST Controller 登録エントリ
│   │   ├── class-mpu-rest-base.php # 基底クラス
│   │   ├── class-mpu-rest-chat.php # LLM 会話エンドポイント
│   │   ├── class-mpu-rest-ghost.php# コア / 人格エンドポイント
│   │   ├── class-mpu-rest-dialog.php# 会話管理エンドポイント
│   │   ├── class-mpu-rest-touch.php# タッチインタラクションエンドポイント
│   │   ├── class-mpu-rest-test.php # API テストエンドポイント
│   │   ├── class-mpu-rest-observation.php # 観測データ送信エンドポイント
│   │   └── class-mpu-rest-memory.php # ユーザーメモリエンドポイント
│   ├── ajax/                   # AJAX ハンドラモジュール
│   │   └── chat-api-handlers.php   # 会話モード API ハンドラ（複数ターン会話のカプセル化）
│   ├── personality/            # 人格（Personality）システムモジュール
│   │   ├── personality-loader.php  # 人格システム（JSON ローダー）
│   │   ├── personality-prompts.php # 人格プロンプトモジュール
│   │   ├── personality-decorations.php # 装飾品システム
│   │   ├── personality-emoji.php   # 絵文字システム
│   │   └── emoji-mapper.php        # 絵文字マッピングと感情分析
│   ├── llm/                    # LLM/AI 機能モジュール
│   │   ├── api-cache.php           # API キャッシュシステム
│   │   ├── ai-functions.php        # AI 機能（クラウド API：Gemini、OpenAI、Claude）
│   │   ├── llm-functions.php       # LLM 機能（Ollama 専用）
│   │   ├── llm-context-builder.php # LLM コンテキストビルダー
│   │   ├── llm-slimstat.php        # LLM Slimstat 統合
│   │   ├── prompt-categories.php   # プロンプトカテゴリ指示管理
│   │   ├── chat-integrity.php      # 会話履歴チェックサム検証
│   │   ├── class-mpu-chat-lock.php # 会話ライフサイクルロック（並列 LLM リクエスト防護）
│   │   ├── request-state.php       # リクエストレベルのステータス管理
│   │   ├── class-mpu-session-event.php # トランスポート非依存の会話イベント
│   │   ├── provider-helpers.php    # AI プロバイダー補助関数
│   │   ├── streaming-helpers.php   # SSE ストリーミング補助関数
│   │   ├── provider-stream-http.php# cURL ストリーミング HTTP クライアント
│   │   ├── tool-loop-guard.php     # ツール呼び出しループ防止メカニズム
│   │   ├── weather-functions.php   # 天気機能（Open-Meteo API）
│   │   ├── diary-functions.php     # AI 日記機能
│   │   └── providers/              # AI プロバイダーファクトリ モジュール
│   │       ├── bootstrap.php       # ローダー
│   │       ├── interface-mpu-ai-provider.php # インターフェース
│   │       ├── class-mpu-ai-provider-base.php # 基底クラス
│   │       ├── class-mpu-ai-provider-factory.php # ファクトリクラス
│   │       ├── class-mpu-ai-provider-gemini.php # Gemini プロバイダー
│   │       ├── class-mpu-ai-provider-openai.php # OpenAI プロバイダー
│   │       ├── class-mpu-ai-provider-claude.php # Claude プロバイダー
│   │       └── class-mpu-ai-provider-ollama.php # Ollama プロバイダー
│   ├── stats/                  # 統計モジュール
│   │   ├── stats-collector.php     # 利用統計の収集
│   │   └── stats-analyzer.php      # 統計分析
│   ├── mcp-tools/              # アビリティ/ツール呼び出しの実装
│   │   ├── manager.php             # アビリティ マネージャー
│   │   └── abilities/
│   │       ├── class-wp-bot-blocker-ability.php # Bot ブロッカーアビリティ
│   │       ├── class-wp-postviews-ability.php   # 記事閲覧数アビリティ
│   │       ├── class-ai-crawler-ability.php     # AI クローラーシグナルアビリティ
│   │       └── class-visitor-pulse-ability.php  # 訪問者活動統計アビリティ
│   ├── integrations/           # 統合機能モジュール
│   │   ├── abilities-integration.php   # アビリティ API 統合
│   │   ├── akismet-integration.php     # Akismet スパム対策統合
│   │   ├── bot-blocker-integration.php # Bot ブロッカー統合
│   │   └── turnstile-integration.php   # Turnstile 検証統合
│   ├── updater/                # 自動更新モジュール
│   │   └── github-updater.php      # GitHub ベースのプラグイン更新チェッカー
│   └── admin-functions.php     # 管理画面機能
├── ghost/                  # キャラクター人格設定
│   ├── Frieren/
│   │   ├── shell/              # キャラクター画像
│   │   ├── decorations/        # 装飾品画像
│   │   ├── emojis/             # キャラクター絵文字画像
│   │   ├── manifest.json       # メタデータと設定
│   │   ├── personality.md      # コア人格の説明
│   │   ├── instructions.md     # 動作ルールと指示
│   │   ├── prompts.json        # 静的会話カテゴリ
│   │   ├── dynamics.json       # 動的テンプレート（変数を含む）
│   │   ├── weights.json        # カテゴリの重み設定
│   │   ├── sleep_mode.json     # 睡眠モード設定
│   │   ├── calendar.json       # カレンダー/休日イベント
│   │   ├── touchzones.json     # タッチゾーン設定
│   │   ├── decorations.json    # 装飾品クリック時のプロンプト
│   │   ├── diary.json          # AI 日記設定
│   │   ├── emoji-keywords.json # 絵文字キーワード設定
│   │   ├── frieren.js          # キャラクター専用 JavaScript
│   │   └── frieren-emoji.js    # Frieren 専用絵文字システム
│   └── [他のキャラクター...]/
│       ├── shell/              # キャラクター画像
│       └── decorations/        # 装飾品画像（オプション）
├── dialogs/                # 会話ファイル
├── images/                 # 共通画像リソース
├── languages/              # 翻訳ファイル
├── docs/                   # ドキュメント
├── options/                # 管理画面設定ページ
│   ├── options.php             # 管理画面ページフレームワーク
│   ├── options_general.php     # 一般設定ページ
│   ├── options_ukagakas.php    # 伺か管理ページ
│   ├── options_create.php      # 新規伺か作成ページ
│   ├── options_extend.php      # 拡張設定ページ
│   ├── options_dialog.php      # 会話設定ページ
│   ├── options_page_ai.php     # AI 機能設定ページ
│   ├── options_page_llm.php    # LLM 機能設定ページ
│   ├── options_page_diary.php  # 日記機能設定ページ
│   ├── options_page_bot_blocker.php # Bot ブロッカー設定ページ
│   └── options_page_stats.php  # 統計設定ページ
├── js/                     # フロントエンド JavaScript モジュール
│   ├── dist/                   # バンドル出力ディレクトリ（本番環境用）
│   │   ├── ukagaka-bundle.js       # 圧縮前バンドル
│   │   ├── ukagaka-bundle.min.js   # 圧縮済みコアバンドル
│   │   └── ukagaka-textarearesizer.min.js  # 管理ツール（圧縮済み）
│   ├── ukagaka-base.js         # ベース層（設定 + ユーティリティ + AJAX）
│   ├── ukagaka-core.js         # フロントエンドコア JS（メッセージ表示、伺かの切り替えなど）
│   ├── ukagaka-features.js     # フロントエンド機能 JS（設定、イベントリスナー）
│   ├── ukagaka-context.js      # ページ認識 AI 会話機能
│   ├── ukagaka-greeting.js     # 初回訪問者への挨拶機能
│   ├── ukagaka-chat.js         # チャット機能のフロントエンド（インタラクティブな会話）
│   ├── ukagaka-dialog.js       # 外部会話ファイルの読み込みとフォールバック
│   ├── ukagaka-anime.js        # Canvas アニメーションマネージャー（画像シーケンス再生）
│   ├── ukagaka-emoji.js        # 絵文字設定ローダー
│   └── ukagaka-textarearesizer.js  # 管理画面のテキストエリアリサイズツール
└── readme.txt              # WordPress プラグインディレクトリの readme
```

### モジュールの読み込み順序

プラグインは実行環境（フロントエンド/管理画面）に基づいて、対応するモジュールを条件付きで読み込むメカニズムを採用しています。

```php
// mp-ukagaka.php 内の読み込みロジック

// コアモジュール：フロントエンドと管理画面の両方で必要
$core_modules = [
    'core/debug-functions.php',     // 0. ログシステム（最初に読み込む必要があります）
    'core/core-functions.php',      // 1. コア機能（設定管理）
    'core/utility-functions.php',   // 2. ユーティリティ関数 / 定数定義
    'core/template-functions.php',  // 3. テンプレート読み込みと文字列処理
    'core/file-functions.php',      // 4. セキュアなファイル操作とディレクトリヘルパー
    'core/encryption-functions.php',// 5. API キー暗号化 / 復号ヘルパー
    'core/wp-info-functions.php',   // 6. WordPress 情報抽出
    'core/network-functions.php',   // 7. ネットワークリクエストヘルパー
    'core/runtime-state-functions.php', // 8. ランタイムスコープの状態ヘルパー
    'core/class-mpu-input-role.php', // 9. LLM / ツール入力ロール解決
    'core/class-mpu-observation-buffer.php', // 10. セッションスコープの訪問者活動バッファ
    'core/class-mpu-log-i18n-builder.php', // 11. フロントエンドコンソールログ i18n ペイロードビルダー
    'personality/personality-loader.php',  // 12. 人格システム（JSON ローダー、他の personality モジュールより先に読み込む）
    'personality/personality-prompts.php', // 13. 人格プロンプトモジュール（動的プロンプト、変数の置換）
    'personality/personality-decorations.php', // 14. 装飾品システム
    'personality/personality-emoji.php',   // 15. 絵文字システム
    'stats/stats-collector.php',   // 16. 統計収集器（ai-functions.php より先に読み込む）
    'stats/stats-analyzer.php',    // 17. 統計分析器
    'llm/api-cache.php',           // 18. API キャッシュシステム
    'llm/provider-helpers.php',    // 19. Provider 共有ヘルパー（JSON エンコード / ツール結果フォーマット）
    'llm/chat-integrity.php',      // 20. 会話履歴の整合性チェックサム（フロントエンド改竄防止）
    'llm/class-mpu-chat-lock.php', // 21. 会話ライフサイクルロック（並列 LLM リクエスト防護）
    'llm/request-state.php',       // 22. リクエストレベルのステータス管理
    'llm/class-mpu-session-event.php', // 23. トランスポート非依存の会話イベント
    'llm/tool-loop-guard.php',     // 24. ツール呼び出しループ防止メカニズム
    'llm/streaming-helpers.php',   // 25. SSE ストリーミング補助関数
    'llm/provider-stream-http.php', // 26. cURL ストリーミング HTTP クライアント
    'llm/providers/bootstrap.php', // 27. AI Providers ファクトリとクラス
    'llm/ai-functions.php',        // 28. AI 機能（クラウド API：Gemini, OpenAI, Claude）
    'llm/prompt-categories.php',   // 29. プロンプトカテゴリ指示管理
    'llm/llm-slimstat.php',        // 30. LLM Slimstat 統合
    'llm/llm-context-builder.php', // 31. LLM コンテキストビルダー
    'llm/weather-functions.php',   // 32. 天気機能（Open-Meteo API）
    'llm/diary-functions.php',     // 33. AI 日記機能（フリーレン手記）
    'llm/llm-functions.php',       // 34. LLM 機能（ローカル LLM：Ollama）
    'personality/emoji-mapper.php', // 35. 絵文字マッピングと感情分析
    'core/ukagaka-functions.php',   // 36. 伺か管理
    'rest/bootstrap.php',           // 37. REST OO Controller 登録エントリ
    'ajax/chat-api-handlers.php',   // 38. 会話モード AJAX ハンドラ（複数ターン）
    'integrations/akismet-integration.php', // 39. Akismet スパム対策統合
    'integrations/turnstile-integration.php', // 40. Turnstile 検証統合
    'integrations/abilities-integration.php', // 41. アビリティ API 統合
    'integrations/bot-blocker-integration.php', // 42. Bot Blocker 統合
];

// フロントエンド専用モジュール（管理画面以外の環境でのみ読み込む）
$frontend_modules = [
    'core/frontend-functions.php',  // フロントエンド機能
];

// 管理画面専用モジュール（管理画面環境でのみ読み込む）
$admin_modules = [
    'admin-functions.php',          // 管理画面機能
    'updater/github-updater.php',   // GitHub 自動更新（Plugin Update Checker）
];
```

**読み込みのタイミング：**

- すべてのコアモジュールは `plugins_loaded` アクション（優先度 1）で読み込まれます。
- フロントエンドモジュールは `!is_admin()` の場合のみ読み込まれます。
- 管理画面モジュールは `is_admin()` の場合のみ読み込まれます。

### 定義された定数

| 定数            | 説明            | 値                  |
| --------------- | --------------- | ------------------- |
| `MPU_VERSION`   | プラグインバージョン | `"2.24.0"` |
| `MPU_MAIN_FILE` | メインファイルパス | `__FILE__`          |

---

## モジュールの説明

### core-functions.php

コア機能モジュール。設定管理を担当します。

#### 主な関数

```php
/**
 * デフォルト設定値を取得します。
 * @return array デフォルト設定の配列
 */
function mpu_default_opt()

/**
 * プラグイン設定を取得します（キャッシュ対応）。
 * @return array 設定の配列
 */
function mpu_get_option()
```

**注意：** `mpu_count_total_msg()` は `ukagaka-functions.php` モジュールに配置されています。

### utility-functions.php

ユーティリティ関数モジュール。さまざまな補助機能（文字列処理、フィルタリング、ファイル操作、暗号化など）を提供します。

#### 文字列/配列の変換

```php
/**
 * 配列を文字列に変換します（2つの改行で区切る）。
 * @param array $arr 入力配列
 * @return string 出力文字列
 */
function mpu_array2str($arr = [])

/**
 * 文字列を配列に変換します（改行で区切り、空行を除外）。
 * @param string $str 入力文字列
 * @return array 出力配列
 */
function mpu_str2array($str = "")
```

#### 出力フィルタリング

```php
/**
 * HTML 出力フィルター（esc_html を使用）
 * @param string $str 入力文字列
 * @return string フィルタリングされた文字列
 */
function mpu_output_filter($str)

/**
 * JavaScript 出力フィルター（esc_js を使用）
 * @param string $str 入力文字列
 * @return string フィルタリングされた文字列
 */
function mpu_js_filter($str)
```

#### 安全なファイル操作

```php
/**
 * ファイルを安全に読み込みます（WordPress Filesystem API を使用）
 * @param string $file_path ファイルパス
 * @return string|WP_Error ファイル内容またはエラー
 */
function mpu_secure_file_read($file_path)

/**
 * ファイルを安全に書き込みます（WordPress Filesystem API を使用）
 * @param string $file_path ファイルパス
 * @param string $content ファイル内容
 * @return bool|WP_Error 成功またはエラー
 */
function mpu_secure_file_write($file_path, $content)

/**
 * 会話ファイルのディレクトリパスを取得します
 * @return string ディレクトリパス
 */
function mpu_get_dialogs_dir()

/**
 * 会話ファイルディレクトリの存在を確保します
 * @return bool 成功したかどうか
 */
function mpu_ensure_dialogs_dir()
```

#### API Key 暗号化

```php
/**
 * 暗号化キーを取得します（WordPress の AUTH_KEY に基づく）
 * @return string 暗号化キー
 */
function mpu_get_encryption_key()

/**
 * API Key を暗号化します（AES-256-GCM / mpu_enc2）
 * @param string $api_key 元の API Key
 * @return string 暗号化された文字列
 */
function mpu_encrypt_api_key($api_key)

/**
 * API Key を復号化します
 * @param string $encrypted_key 暗号化された文字列
 * @return string|false 復号化された API Key、または false
 */
function mpu_decrypt_api_key($encrypted_key)

/**
 * API Key が暗号化されているか確認します
 * @param string $api_key API Key の文字列
 * @return bool 暗号化されているかどうか
 */
function mpu_is_api_key_encrypted($api_key)
```

### personality-loader.php (v2.4.0)

人格（Personality）システムローダーモジュール。JSON ベースのキャラクター設定システムを提供します。PHP コードを変更することなく、JSON ファイルを通じて異なるキャラクターの人格を定義できるようにします。

#### personality-loader.php の主な関数

```php
/**
 * ghost ディレクトリパスを取得します（personalities ディレクトリ）
 * @return string 絶対パス
 */
function mpu_get_personalities_dir()

/**
 * 現在の人格 ID を取得します
 * @return string 人格 ID（フォルダー名）
 */
function mpu_get_current_personality_id()

/**
 * 人格が存在するかどうかを確認します
 * @param string $personality_id 人格のフォルダー名
 * @return bool 存在するかどうか
 */
function mpu_personality_exists($personality_id)

/**
 * 利用可能なすべての人格を取得します
 * @param bool $include_placeholders プレースホルダーキャラクターを含めるかどうか
 * @return array 人格 ID => manifest の連想配列
 */
function mpu_get_available_personalities($include_placeholders = false)

/**
 * 人格の manifest を読み込みます
 * @param string|null $personality_id 人格 ID、null の場合は現在の人格
 * @return array Manifest データ
 */
function mpu_load_personality_manifest($personality_id = null)

/**
 * 人格のプロンプトを読み込みます
 * @param string|null $personality_id 人格 ID、null の場合は現在の人格
 * @return array プロンプトカテゴリの配列
 */
function mpu_load_personality_prompts($personality_id = null)

/**
 * 人格の重み設定を読み込みます
 * @param string|null $personality_id 人格 ID、null の場合は現在の人格
 * @return array 重み設定の配列
 */
function mpu_load_personality_weights($personality_id = null)

/**
 * 人格の装飾品設定を読み込みます
 * @param string|null $personality_id 人格 ID、null の場合は現在の人格
 * @return array 装飾品設定の配列
 */
function mpu_load_personality_decorations($personality_id = null)

/**
 * 人格の動的プロンプトを読み込みます
 * @param string|null $personality_id 人格 ID、null の場合は現在の人格
 * @return array 動的プロンプト設定の配列
 */
function mpu_load_personality_dynamic_prompts($personality_id = null)

/**
 * 人格の絵文字キーワードを読み込みます
 * @param string|null $personality_id 人格 ID、null の場合は現在の人格
 * @return array 絵文字キーワード設定の配列
 */
function mpu_load_personality_emoji_keywords($personality_id = null)
```

#### 人格ファイルの構造

各人格フォルダーには以下を含める必要があります：

- **manifest.json**（必須）：メタデータと設定
  - `id`：人格 ID
  - `name`、`name_en`、`name_zh`：多言語対応の名前
  - `version`：バージョン番号
  - `settings`：キャラクター設定（例：`max_response_length`、`speech_style`、`tone`）
  - `character_traits`：キャラクターの特性（例：`age`、`race`、`occupation`、`personality`）

- **prompts.json**（オプション）：静的な会話カテゴリ
  - キーはカテゴリ名、値はプロンプトの配列

- **dynamics.json**（オプション）：動的テンプレート（変数の置換を含む）
  - `{variable_name}` による変数の置換をサポート
  - `time_aware_dynamic`、`tech_observation`、`bot_detection` などのカテゴリを含む

- **weights.json**（オプション）：カテゴリの重み設定
  - `base_weights`：基本の重み
  - `time_adjustments`：時間帯による調整

- **decorations.json**（オプション）：装飾品クリック時のプロンプト
  - `items`：装飾品設定の配列。各要素には以下が含まれます：
    - `id`：装飾品 ID
    - `image`：画像パス（`decorations/` フォルダーからの相対パス）
    - `position`：位置設定（例：`{"bottom": "0px", "right": "0px"}`）
    - `size`：サイズ設定（例：`{"width": "100px", "height": "auto"}`）
    - `z_index`：Z インデックス（数値）
    - `prompt`：クリック時のプロンプト
    - `transform`：CSS 変換（オプション、例：`scale(1)`）

- **emoji-keywords.json**（オプション、v2.4.0）：絵文字トリガーキーワード
  - `mappings`：絵文字タイプとキーワードのマッピング
  - 形式の例：
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

- **script**（オプション）：キャラクター専用の JavaScript ファイル
  - 例：`frieren.js`。フロントエンドによって自動的に読み込まれます。

#### 使用例

```php
// 現在の人格のプロンプトを取得する
$prompts = mpu_load_personality_prompts();

// 特定の人格の manifest を取得する
$manifest = mpu_load_personality_manifest('Frieren');

// 人格が存在するかどうかを確認する
if (mpu_personality_exists('Frieren')) {
    // Frieren の人格が存在する
}

// 利用可能なすべての人格を取得する
$personalities = mpu_get_available_personalities();
foreach ($personalities as $id => $manifest) {
    echo $manifest['name'];
}
```

### ai-functions.php

AI 機能モジュール。クラウド AI API の呼び出し（Gemini、OpenAI、Claude）および Ollama の統合を処理します。

#### ai-functions.php の主な関数

```php
/**
 * AI API を呼び出します（統合エントリポイント）
 * @param string $provider プロバイダー（gemini/openai/claude/ollama）
 * @param string $api_key API キー（Ollama の場合は不要）
 * @param string $system_prompt システムプロンプト（キャラクター設定）
 * @param string $user_prompt ユーザープロンプト
 * @param string $language 言語コード
 * @param array|null $mpu_opt 設定配列（オプション）
 * @return string|WP_Error AI の応答またはエラー
 */
function mpu_call_ai_api($provider, $api_key, $system_prompt, $user_prompt, $language, $mpu_opt = null)

/**
 * 言語の指示を取得します
 * @param string $language 言語コード
 * @return string 言語の指示
 */
function mpu_get_language_instruction($language)
```

#### サポートされている AI プロバイダー

すべてのプロバイダーは `MPU_AI_Provider_Factory::create($provider_slug)->generate_text($args)` を通じてルーティングされます。統一エントリーポイントとして `mpu_call_ai_api()` を使用してください。

| プロバイダー | スラッグ | API エンドポイント | モデル選択 |
| -------- | ---- | ------------ | --------------- |
| Gemini | `gemini` | `generativelanguage.googleapis.com` | サポートあり（gemini-2.5-flash, gemini-2.5-pro など） |
| OpenAI | `openai` | `api.openai.com` | サポートあり（gpt-4o-mini, gpt-4o など） |
| Claude | `claude` | `api.anthropic.com` | サポートあり（claude-sonnet-4-6 など） |
| Ollama | `ollama` | ローカルまたはリモートの Ollama サービス | サポートあり（任意の Ollama モデル） |

#### AI の安定性とセキュリティ

LLM がツール呼び出しの無限ループに陥るのを防ぐため、システムには以下の保護メカニズムが組み込まれています：

1. **ターン制限 (Turn Limit)**：`MPU_MAX_TOOL_TURNS` によって定義され、1回のリクエストで最大 5 回のツール呼び出しを許可します。
2. **ループ防止 (Loop Guard)**：
   - 検出対象：同じパラメータで同じツールが連続して繰り返し呼び出されるシナリオ。
   - しきい値：`MPU_MAX_TOOL_REPEAT_SAME_CALL` によって定義（デフォルトは 2）。
   - 動作：ループが検出されると、システムは直ちに `tool_call_loop_detected` エラーを返し、ループを中断します。
   - 実装：`includes/llm/tool-loop-guard.php`。

### llm-functions.php (BETA)

> ⚠️ **注意**：このモジュールは **BETA（テスト段階）** です。API は変更される可能性があります。

LLM 機能モジュール。Ollama のローカル LLM 統合を専門に処理します。

#### llm-functions.php の主な関数

```php
/**
 * エンドポイントがリモート接続であるかどうかを確認します
 * @param string $endpoint Ollama エンドポイント URL
 * @return bool リモート接続かどうか（true = リモート、false = ローカル）
 */
function mpu_is_remote_endpoint($endpoint)

/**
 * エンドポイントのタイプと操作のタイプに基づいて適切なタイムアウトを取得します
 * @param string $endpoint Ollama エンドポイント URL
 * @param string $operation_type 操作のタイプ：'check'、'api_call'、'test'
 * @return int タイムアウト時間（秒）
 */
function mpu_get_ollama_timeout($endpoint, $operation_type = 'api_call')

/**
 * Ollama エンドポイント URL を検証および正規化します
 * @param string $endpoint 元のエンドポイント URL
 * @return string|WP_Error 正規化された URL またはエラー
 */
function mpu_validate_ollama_endpoint($endpoint)

/**
 * Ollama サービスが利用可能かどうかを確認します（クイックチェック、キャッシュを使用）
 * @param string $endpoint Ollama エンドポイント
 * @param string $model モデル名
 * @return bool サービスが利用可能かどうか
 */
function mpu_check_ollama_available($endpoint, $model)

/**
 * LLM を使用してランダムな会話を生成します（組み込みの会話を置き換えます）
 * @param string $ukagaka_name 伺かの名前
 * @return string|false 生成された会話内容、失敗時は false
 */
function mpu_generate_llm_dialogue($ukagaka_name = 'default_1')

/**
 * LLM による組み込み会話の置き換えが有効になっているかどうかを確認します
 * @return bool
 */
function mpu_is_llm_replace_dialogue_enabled()
```

#### タイムアウト設定

| 操作のタイプ | ローカル接続 | リモート接続 |
| -------------- | ---------------- | ----------------- |
| サービスチェック (`check`) | 3秒 | 10秒 |
| API 呼び出し (`api_call`) | 60秒 | 90秒 |
| 接続テスト (`test`) | 30秒 | 45秒 |

#### 使用例

```php
// サービスが利用可能かどうかを確認する
$endpoint = 'https://your-domain.com';
$model = 'qwen3:8b';
if (mpu_check_ollama_available($endpoint, $model)) {
    // サービスは利用可能、会話を生成できる
    $dialogue = mpu_generate_llm_dialogue('default_1');
    if ($dialogue !== false) {
        echo $dialogue;
    }
}

// 接続のタイプを検出する
$is_remote = mpu_is_remote_endpoint($endpoint);
$timeout = mpu_get_ollama_timeout($endpoint, 'api_call');
```

### chat-api-handlers.php (互換性レイヤー)

会話モード API ハンドラ。新しい REST Controller のために、複数ターンの会話の AI 呼び出しをカプセル化し、複雑な Provider のオプション処理を分離します。

#### 主な関数

```php
/**
 * AI API を呼び出します（複数ターン会話の統合エントリ、ファクトリクラスへの自動ディスパッチ）
 * @param string $provider プロバイダー
 * @param string $api_key API キー
 * @param string $system_prompt システムプロンプト
 * @param array $messages 会話の履歴
 * @param string $language 言語
 * @param array $options オプション（max_tokens、temperature など）
 * @return string|WP_Error AI の応答
 */
function mpu_call_ai_api_with_messages($provider, $api_key, $system_prompt, $messages, $language, $options = [])
```

#### 会話メッセージの形式

```php
// 会話履歴の配列形式
$messages = [
    [
        'role' => 'user',      // 'user' または 'assistant'
        'content' => 'こんにちは'   // メッセージ内容
    ],
    [
        'role' => 'assistant',
        'content' => 'こんにちは！何かお手伝いできることはありますか？'
    ],
    // ... さらにメッセージが続く
];
```

#### 動的コンテキストの注入

システムはユーザーのメッセージ内容に基づいて、WordPress の統計情報を注入するかどうかを決定します：

```php
// キーワードリスト（繁体字中国語/日本語/英語）
$stats_keywords = [
    '文章', '記事', 'article', 'post',
    '留言', 'コメント', 'comment',
    '網站', 'サイト', 'site', 'website',
    'php', 'wordpress', '外掛', 'plugins', 'プラグイン',
    '主題', 'テーマ', 'theme'
];

// ユーザーのメッセージにこれらのキーワードが含まれている場合のみ、統計情報を追加する
```

**メリット**：

- トークン消費を 70%+ 削減
- API コストの削減
- 応答速度の向上

#### 思考モードのサポート (Ollama)

Ollama プロバイダーは思考モデル（Qwen3、DeepSeek など）を自動検出し、`think: true` / `num_ctx: 8192` を設定します。`ollama_disable_thinking` オプションで無効化できます。

#### 応答の長さ制限

すべての AI プロバイダーは **300 トークン** に統一して制限されています：

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

フロントエンド機能モジュール。ページの表示とリソースの読み込みを担当します。

#### frontend-functions.php の主な関数

```php
/**
 * 現在のページに表示すべきかどうかを確認します
 * @return bool 表示するかどうか
 */
function mpu_is_show_page()

/**
 * 出力バッファコールバック（伺かの HTML を挿入するために使用）
 * @param string $buffer ページ内容
 * @return string 処理された内容
 */
function mpu_ob_callback($buffer)

/**
 * シャットダウンコールバック（HTML が挿入されることを保証します）
 */
function mpu_shutdown_callback()

/**
 * 伺かの HTML を生成します
 * @param string|false $num 伺かのキー
 * @return string HTML 文字列
 */
function mpu_html($num = false)

/**
 * 伺かの HTML を出力します
 */
function mpu_echo_html()

/**
 * フロントエンドリソース（CSS/JS）をキューに入れます
 */
function mpu_enqueue_frontend_assets()

/**
 * head 内に設定を出力します（JavaScript 変数）
 */
function mpu_head()
```

### admin-functions.php

管理画面機能モジュール。設定の保存と管理画面のインターフェースを処理します。

#### admin-functions.php の主な関数

```php
/**
 * 管理画面リソース（CSS/JS）をキューに入れます
 * @param string $hook_suffix 現在のページのフック
 */
function mpu_admin_enqueue_scripts($hook_suffix)

/**
 * 設定の保存を処理します
 */
function mpu_handle_options_save()

/**
 * 会話ファイルを生成します（TXT または JSON 形式）
 * @param string $filename ファイル名（拡張子なし）
 * @param array $msg_array メッセージ配列
 * @param string $ext 拡張子（txt または json）
 * @return bool 成功したかどうか
 */
function mpu_generate_dialog_file($filename, $msg_array, $ext)

/**
 * 管理メニューページの HTML
 */
function mpu_options_page_html()

/**
 * 管理メニューを登録します
 */
function mpu_options()
```

---

## データ構造

### 設定構造 ($mpu_opt)

```php
$mpu_opt = [
    // 一般設定
    'cur_ukagaka' => 'default_1',      // 現在の伺か
    'show_ukagaka' => true,             // 伺かを表示する
    'show_msg' => true,                 // ダイアログボックスを表示する
    'default_msg' => 0,                 // 0=ランダム, 1=最初のメッセージ
    'next_msg' => 0,                    // 0=順番, 1=ランダム
    'click_ukagaka' => 0,               // 0=次のメッセージ, 1=何もしない
    'insert_html' => 0,                 // HTML の挿入位置
    'no_style' => false,                // カスタムスタイルを使用する
    'no_page' => '',                    // 除外するページのリスト

    // 自動会話
    'auto_talk' => true,                // 自動会話を有効にする
    'auto_talk_interval' => 8,          // 自動会話の間隔（秒）
    'typewriter_speed' => 40,           // タイピング速度（ミリ秒/文字）

    // 外部会話ファイル
    'use_external_file' => true,        // 外部ファイルを使用する（システムで強制的に true）
    'external_file_format' => 'txt',     // ファイル形式（txt/json）

    // 会話設定
    'auto_msg' => '',                   // 固定メッセージ
    'common_msg' => '',                 // 共通の会話

    // AI 設定（ページ認識機能）
    'ai_enabled' => false,              // AI を有効にする
    'ai_provider' => 'gemini',          // AI プロバイダー（gemini/openai/claude/ollama）
    'ai_api_key' => '',                 // Gemini API キー（暗号化）
    'gemini_model' => 'gemini-2.5-flash', // Gemini モデル
    'openai_api_key' => '',             // OpenAI API キー（暗号化）
    'openai_model' => 'gpt-4o-mini',    // OpenAI モデル
    'claude_api_key' => '',             // Claude API キー（暗号化）
    'claude_model' => 'claude-sonnet-4-5-20250929', // Claude モデル
    'ai_language' => 'zh-TW',           // AI の応答言語
    'ai_system_prompt' => '',           // AI の人格設定
    'ai_probability' => 10,             // AI のトリガー確率（0-100）
    'ai_trigger_pages' => 'is_single',  // トリガーするページの条件
    'ai_text_color' => '#ff6b6b',       // AI テキストの色
    'ai_display_duration' => 8,         // AI 表示時間（秒）
    'ai_greet_enabled' => false,        // 初回訪問者への挨拶
    'ai_greet_prompt' => '',            // 挨拶のプロンプト

    // LLM 設定 (BETA)
    'ollama_endpoint' => 'http://localhost:11434',  // Ollama エンドポイント
    'ollama_model' => 'qwen3:8b',                   // Ollama モデル
    'ollama_replace_dialogue' => false,              // 組み込みの会話を LLM で置き換える
    'ollama_disable_thinking' => true,               // 思考モードを無効にする

    // 拡張機能
    'extend' => [
        'js_area' => '',                // カスタム JavaScript
    ],

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
    'name' => 'フリーレン',            // 名前
    'shell' => 'https://...png',      // 画像 URL
    'msg' => [                        // 会話配列
        '会話 1',
        '会話 2',
    ],
    'show' => true,                   // 表示可能か
    'dialog_filename' => 'frieren',   // 会話ファイル名
];
```

---

## フックとフィルター

`v2.9.2` の REST リファクタリング以降、プラグインレベルの `do_action()` フック（`mpu_loaded`、`mpu_before_html`、`mpu_after_html`、`mpu_settings_saved`）および `apply_filters()` フック（`mpu_options`、`mpu_messages`、`mpu_ai_response`、`mpu_ukagaka_html`）はすべて削除されました。

現在残っている機能的なフィルターは、LLM のプロンプト構築に関連する以下の 4 つです：

### mpu_llm_system_prompt

LLM に送信されるシステムプロンプトを変更するために使用されます。完全な構造の中で、人格カード、WordPress のコンテキスト、および行動ルールが含まれています。

```php
add_filter('mpu_llm_system_prompt', function($prompt, $ukagaka_name, $personality_id, $context) {
    return $prompt;
}, 10, 4);
```

### mpu_llm_user_prompt

ユーザーのプロンプトの前に、セキュリティ警告、イベント情報、外部システムメッセージなどの追加コンテキストを付与するために使用されます。

```php
add_filter('mpu_llm_user_prompt', function($prompt, $ukagaka_name, $personality_id) {
    $attack_info = get_transient('mpu_llar_attack_info');
    if ($attack_info) {
        return $prompt . "\n【セキュリティ警告】\n" . $attack_info;
    }
    return $prompt;
}, 10, 3);
```

### mpu_prompt_categories

LLM の自動会話のカテゴリ定義（挨拶、雑談、時間認識、統計観察など）を調整するために使用されます。

```php
add_filter('mpu_prompt_categories', function($categories, $wp_info, $visitor_info, $time_context) {
    return $categories;
}, 10, 4);
```

### mpu_category_weights

会話カテゴリの重み付きランダムの重みを調整するために使用されます。

```php
add_filter('mpu_category_weights', function($weights, $time_context, $visitor_info, $context_vars) {
    if ($time_context === '深夜') {
        $weights['philosophical'] = 15;
    }
    return $weights;
}, 10, 4);
```

---

## REST エンドポイント

現在、フロントエンドとほとんどの管理画面でのテストプロセスは主に REST API に依存しています。ベースの名前空間は `mp-ukagaka/v1` で、完全なプレフィックスは `/wp-json/mp-ukagaka/v1/` になります。フロントエンドのリクエストには、`mpuRestNonce` から提供される `X-WP-Nonce` が必要です。

### キャラクター / 設定系

| エンドポイント | メソッド | 説明 |
| --- | --- | --- |
| `/init` | GET | シェル、装飾品、絵文字、タッチゾーン、設定などの初期化データを取得します |
| `/settings` | GET | フロントエンドの設定オブジェクトを取得します |
| `/change` | POST | キャラクターを切り替えるか、パラメータが渡されていない場合は利用可能なキャラクターのリストを返します |
| `/shell-info` | GET / POST | 特定のキャラクターの外観情報を取得します |
| `/decoration-config` | GET / POST | 装飾品の設定を取得します |
| `/emoji-config` | GET / POST | 絵文字の設定を取得します |
| `/extend` | GET / POST | 拡張エントリポイント。フロントエンドでクリック可能なタグを返します |

### 会話系

| エンドポイント | メソッド | 説明 |
| --- | --- | --- |
| `/nextmsg` | POST | 次のメッセージを取得します。LLM 置換モードが有効な場合は AI 生成を使用します |
| `/dialog` | GET / POST | `dialogs/` 配下の会話ファイルを読み込みます |
| `/visitor-info` | GET | 訪問元の情報や Slimstat 関連の情報を取得します |
| `/decoration-prompts` | GET / POST | 装飾品をクリックしたときのプロンプトを取得します |
| `/wake-ghost` | POST | 睡眠中のキャラクターを起こします |

### AI 会話系

| エンドポイント | メソッド | 説明 |
| --- | --- | --- |
| `/chat/context` | POST | ページを認識する AI 会話 |
| `/chat/greet` | POST | 初回訪問者への挨拶 |
| `/chat/user` | POST | 複数ターンのインタラクティブチャット（非ストリーミング） |
| `/chat/user-stream` | POST | SSE ストリーミングインタラクティブチャット |

### タッチインタラクション系

| エンドポイント | メソッド | 説明 |
| --- | --- | --- |
| `/touch/decoration` | POST | 装飾品をクリックしたときの AI の反応 |
| `/touch/zone` | POST | キャラクターの領域をクリックしたときのインタラクション反応 |

### 管理画面テスト系

| エンドポイント | メソッド | 説明 |
| --- | --- | --- |
| `/test-connection/{provider}` | POST | 統合されたプロバイダーの接続テスト |
| `/clear-cache` | POST | LLM API のキャッシュをクリアします |

### 保持されている AJAX エンドポイント

メインアーキテクチャは REST に移行しましたが、いくつかの内部統合は引き続き `admin-ajax.php` を使用します：

| アクション | ハンドラ | 説明 |
| --- | --- | --- |
| `wp_ajax_mpu_test_diary_generate` | `mpu_ajax_test_diary_generate` | 管理画面から日記生成をテストします |
| `wp_ajax_nopriv_slimtrack` / `wp_ajax_slimtrack` | `mpu_bb_intercept_slimstat` | Bot Blocker が Slimstat を傍受します |
| `wp_ajax_nopriv_mbb_js_flag` / `wp_ajax_mbb_js_flag` | `mpu_bb_js_flag_handler` | Bot Blocker の JS フラグ検出 |

---

## JavaScript API

### グローバル変数

フロントエンドの初期化後、以下のグローバル変数が公開されます：

```javascript
window.mpuRestUrl;         // REST ベース URL（例：/wp-json/mp-ukagaka/v1/）
window.mpuRestNonce;       // REST 用 Nonce
window.mpuL10n;            // フロントエンドの翻訳文字列
window.mpuSettings;        // /init から返された settings
window.mpuInitData;        // /init からの完全な応答
window.mpuPersonalityId;   // 現在の人格 ID
window.mpuMsgList;         // 会話データ
window.mpuChatHistory;     // 複数ターンの会話履歴
window.mpuChatModeActive;  // インタラクティブ会話モードがアクティブかどうか
window.mpuCanvasManager;   // Canvas アニメーションマネージャー
window.mpuDecorationConfig;
window.mpuTouchZones;
window.mpuEmojiBaseUrl;
window.mpuSupportedEmojis;
window.mpuEmojiMappings;
```

`window.mpuSettings` のデータは `/init` から返された `settings` ブロックから取得され、以下のような形式になります：

```javascript
window.mpuSettings = {
  auto_talk: true,
  auto_talk_interval: 8,
  typewriter_speed: 40,
  ai_enabled: true,
  ai_probability: 10,
  ai_trigger_pages: "is_single",
  ai_text_color: "#000000",
  ai_display_duration: 8,
  ai_greet_first_visit: true,
  ollama_replace_dialogue: false,
  enable_chat_mode: false,
  sleep_mode: {
    enabled: false,
    frequency_multiplier: 1.0
  }
};
```

### コア関数

```javascript
function mpu_nextmsg(trigger)
function mpu_hidemsg()
function mpu_showmsg()
function mpu_hiderobot()
function mpu_showrobot()
function mpuChange(num)
```

### AI / インタラクション関数

```javascript
function mpu_chat_context()
function mpu_greet_first_visitor(settings)
function mpu_sendUserMessage()
function mpu_toggleChatMode(enable)
```

### Canvas マネージャー

```javascript
window.mpuCanvasManager = {
  init: function(shellInfo, name),
  playAnimation: function(),
  stopAnimation: function(),
  isAnimationMode: function()
};
```

---

## 拡張開発

### 新しい AI プロバイダーの追加

現在のプロバイダーアーキテクチャは `includes/llm/providers/` の下にあります。`ai-functions.php` に手続き型のケース（case 文）を直接追加するのではなく、プロバイダーファクトリと統合することをお勧めします。

基本的な手順：

1. `class-mpu-ai-provider-*.php` を追加する
2. 既存の provider インターフェースを実装する
3. `class-mpu-ai-provider-factory.php` に登録する
4. 必要に応じて `provider-helpers.php` と管理画面の設定ページに対応するフィールドを追加する
5. `/test-connection/{provider}` が新しいプロバイダーをテストできるようにする

### 新しいメッセージコードの追加

メッセージの特殊コードは引き続き `mpu_msg_code()` によって処理されます。新しい `:newcode[n]:` タイプを追加するには、その処理フローに対応する置換ルールを挿入します。

```php
if (preg_match('/:newcode\[(\d+)\]:/', $msg, $matches)) {
    $param = intval($matches[1]);
    $replacement = my_custom_function($param);
    $msg = str_replace($matches[0], $replacement, $msg);
}
```

### 新しい REST エンドポイントの追加

古い AJAX アクションを追加する代わりに、`includes/rest/` 配下のコントローラーアーキテクチャを優先して使用してください。

```php
class MPU_REST_Custom extends MPU_REST_Base {
    public function register_routes() {
        register_rest_route($this->namespace, '/custom', [
            [
                'methods' => 'POST',
                'callback' => [$this, 'handle_custom'],
                'permission_callback' => '__return_true',
            ],
        ]);
    }

    public function handle_custom(WP_REST_Request $request) {
        return rest_ensure_response([
            'success' => true,
            'data' => ['message' => 'ok'],
        ]);
    }
}
```

次に、新しいコントローラーを `includes/rest/bootstrap.php` に登録します。

### 会話カテゴリの重みのカスタマイズ

会話カテゴリと重みは現在、`includes/llm/prompt-categories.php` に集約されています。これらは `mpu_prompt_categories` と `mpu_category_weights` フィルターを通じて調整できます。これは `llm-functions.php` を直接変更するよりも安定しています。

### 観察系会話サンプルのカスタマイズ

組み込みの会話ファイルからサンプルを抽出する戦略を変更するには、`includes/llm/llm-functions.php` でサンプルセリフを構築する関数を確認し、`dialog_filename` と personality / ukagaka のマッピングロジックに注意してください。

### 今後の展望：汎用キャラクターマネージャーのサポート

**現在のステータス：**

現在のシステムでは、キャラクター固有のアニメーションやインタラクションのロジック（フリーレンの起床アニメーション、ページめくりアニメーション、睡眠モードなど）は、ハードコーディングされた `window.mpuFrierenManager` を通じて実装されています。これは以下のことを意味します：

- フリーレン（Frieren）の人格だけが専用のキャラクターマネージャーを持っています。
- 他のキャラクターは、同様の専用アニメーションやインタラクション機能を使用できません。
- キャラクターマネージャーへのすべての参照は、直接 `mpuFrierenManager` を指しています。

**改善の方向性：**

将来的には、複数のキャラクターをサポートし、それぞれが専用のアニメーションやインタラクションロジックを持つことができる、汎用のキャラクターマネージャーシステムを実装することができます：

1. **動的マネージャー検索メカニズム**
   - `ukagaka-anime.js` に `getCurrentCharacterManager()` メソッドを実装する。
   - 現在のキャラクターの `dialog_filename` または personality ID に基づいて、対応するマネージャーを動的に検索する。
   - 命名規則を使用する：`window.mpu{PersonalityId}Manager`（例：`mpuFrierenManager`、`mpuSakuraManager`）。

2. **統一インターフェース標準**
   - 標準的なキャラクターマネージャーのインターフェース（メソッド名とプロパティ）を定義する。
   - すべてのキャラクターマネージャーは `initMode()`, `triggerSpeaking()`, `isCharacterMode` などを実装しなければならない。
   - 下位互換性を確保する（`mpuFrierenManager` へのサポートを維持する）。

3. **実装場所**
   - 主な変更点：`js/ukagaka-anime.js`（約20箇所の参照を変更する必要がある）。
   - 副次的な変更点：`js/ukagaka-chat.js` と `js/ukagaka-core.js`（少数の参照）。
   - 見積もり作業量：約 2-3 時間（テストを含む）。

4. **トリガーのタイミング**
   - 2人目のキャラクターが専用のアニメーションやインタラクションを必要とするときに、一緒に実装することができる。
   - または、フリーレンの専用機能を抽象化する必要があるときにリファクタリングを行う。

**技術的な要点：**

- 現在のキャラクター情報は `dialog_filename` または personality ID から取得する必要がある。
- 既存のフリーレンの機能が正常に動作するように、下位互換性を維持する必要がある。
- 実装例として `ghost/Frieren/frieren.js` 内の `mpuFrierenManager` を参考にすることができる。

### 内心独白（`<think>`）チャンネル — 既定では廃案、開発者向け opt-in

> **状態：** キャラクターの「内心の思考」を吹き出しに描画する LLM `<think>` 内心独白チャンネルは、v2.25.0 時点で**プロジェクトとして廃案・非メンテナンス**です。元のメンテナはローカル Ollama 環境で実用的な結果を得られませんでしたが、*パイプライン全体はそのまま同梱*されており、何も供給していないために惰性状態であるだけです。お使いの provider／モデルが良質で短い内心独白を生成できるなら、`<think>` テキストを既存パイプラインへ供給する形で opt-in できます。これはサポート対象の正式機能ではなく、開発者向け拡張として記載しています。

**すでに動作する部分（変更不要）：**

1. `mpu_normalize_ai_response()`（`includes/llm/response-normalizer.php`）は AI 応答から**先頭**の `<think>...</think>` ブロックを `think` フィールドに抽出し、`display_text` / `history_text` / `checksum_text` / `tts_text` には含めません。（中間の `<think>` は warning log に剥がすだけで、描画されません。）
2. SSE ステートマシン `MPU_Stream_Output_Parser`（`includes/llm/class-mpu-stream-output-parser.php`）は chunk 境界をまたいで `<think>` を検出し、正規化イベントを emit します：`status {type:"thinking_start"|"thinking_end"}`、`think_delta {text}`（漸進）、`think {text}`（最終）。
3. フロントエンド（`js/ukagaka-chat.js`）は上記すべてのイベント — および非ストリーミングの `res.think` フィールド — をすでに消費し、`mpuShowThinkBubble(text, { source: "llm", context })` で `#ukagaka_think` に描画します。ストリーミング・漸進・非ストリーミングの 3 経路すべて接続済みです。

**唯一不足している接続：** provider が実際に*テキスト出力の中で* `<think>...</think>` を吐く必要があります。クラウド推論モデル（Claude/OpenAI/Gemini）は reasoning を**本パイプラインを通らない別の API フィールド**に入れるため、`<think>` の出力を明示的に要求する必要があります。2 通り：

**方法 A — Prompt 指示（クロス provider、最小コード）。** 有効なキャラクター prompt（`instructions.md`、管理画面の System Prompt、または `manifest.json` の prompt source）に、短い内心独白を任意で `<think>` に包むよう指示を追加します。例：

```markdown
## Inner Monologue
Optionally begin your reply with one short <think>...</think> block
(a private thought or stage direction, max ~30 chars). It is shown in a
separate bubble, never spoken, and must appear before any other text.
Open and close the tag as a pair, or omit it entirely.
```

ゲートが ON の context では、これだけで normalizer、SSE parser、吹き出しが残りを処理します。ただしこれは prompt レベルの opt-in であり、runtime の per-context hook ではありません。同じ prompt が無効 context でも再利用される場合、モデルが `<think>` を生成して token を消費し、その後 strip される可能性があります。

`mpu_llm_system_prompt` を現在の REST 主経路の接続点として使わないでください。現行 core では legacy `mpu_generate_llm_dialogue()` 経路にのみ適用され、`mpu_resolve_system_prompt()` を呼ぶ REST chat / touch / page-aware 経路には適用されません。また第 4 引数は request context 文字列ではなく、情報 array（`wp_info`、`user_info`、`visitor_info`、`time_context`、`language`）です。runtime の context-aware 注入が必要な場合は、`mpu_resolve_system_prompt()` 呼び出し経路に専用 filter を追加するか、provider integration 内で mapping してください。

**方法 B — provider のネイティブ reasoning フィールドを `<think>` にマッピング。** 独立した `thinking` フィールドを持つ Ollama／推論モデルでは、provider class 内でそれを `<think>{thinking}</think>` で包み content の前に付加します（これは revert された `a0e257f` が行ったことです — 再利用前に落とし穴を確認してください）。

**通過させる必要があるゲート（すべて存在済み）：**

| ゲート | 場所 | 既定 |
|---|---|---|
| `enable_inner_monologue` option | `mpu_is_inner_monologue_enabled()` が読み取る | `true`（グローバル ON） |
| Per-context ポリシー | `mpu_is_inner_monologue_enabled_for_context($context, $personality_id)` | `chat` / `touch` / `page_aware` = ON；`decoration` / `initial` / `diary` = OFF；未知 = OFF |
| Per-personality 上書き | `manifest.json` → `features.inner_monologue_contexts[$context]` | その context の既定を上書き |

ある context のゲートが OFF の場合、SSE parser は `<think>` を剥がし think イベントを emit **せず**、normalizer も空の `think` を返します — したがって prompt 注入だけでは不十分で、その context も有効である必要があります。

**落とし穴（廃案の理由 — 依存する前に解決すること）：**

- **共有トークン予算。** Ollama の `num_predict`（および単一出力予算のモデル）は reasoning と最終応答で**共有**されます。長い `<think>` が予算を食い、実際の回答を切り詰めたり空にしたりし、chat history を非同期化させ checksum mismatch（`logs/checksum-mismatch.log`）を引き起こします。こうしたモデルで有効化する前に、reasoning と応答の予算を**別々に**確保してください。
- **吹き出しに overflow ガードがない。** `.mpu-think-bubble` には `max-height` / `overflow` がなく、長い reasoning が UI を溢れさせます。`max-height` + `overflow: auto` を追加してください（以前の overflow 修正は revert されたチャンネルと共に破棄されました）。
- **品質。** 推論モデルの「思考」は長く、機械的で、キャラクターから外れがちです。指示を厳しく（長さ上限・単一ブロック）保ち、モデルごとに実測してください。

---

## セキュリティの考慮事項

### API Key のセキュリティ

- すべての API Key は AES-256-GCM（`mpu_enc2:`）で暗号化して保存されます。
- WordPress の `AUTH_KEY` を暗号化キーとして必須にします。利用できない場合は、公開情報由来の key へ fallback せず暗号化を拒否します。
- 既存設定との互換性のため、旧 `mpu_enc:`（AES-256-CBC）と `mpu_obf:` 値は引き続き読み取れます。
- 管理画面での表示時は `type="password"` を使用して非表示にします。

### 入力の検証

```php
// フィルタリングには常に WordPress の関数を使用する
$input = sanitize_text_field($_POST['input']);
$html = wp_kses_post($_POST['html']);
$url = esc_url($_POST['url']);
```

### 出力のエスケープ

```php
// HTML の出力
echo esc_html($text);

// 属性の出力
echo esc_attr($value);

// URL の出力
echo esc_url($url);

// JavaScript の出力
echo wp_json_encode($data);
```

### Nonce の検証

```php
// フォームに Nonce を追加
wp_nonce_field('mp_ukagaka_settings');

// Nonce を検証
if (!wp_verify_nonce($_POST['_wpnonce'], 'mp_ukagaka_settings')) {
    wp_die('セキュリティチェックに失敗しました');
}
```

### ファイル操作

- `mpu_secure_file_read()` と `mpu_secure_file_write()` を使用します。
- ファイルパスが許可されたディレクトリ内にあることを検証します。
- ファイルサイズの制限をチェックします。

---

## 開発標準

### コードスタイル

- [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/) に従います。
- インデントには 4 つのスペースを使用します。
- 関数名には `mpu_` のプレフィックスを使用します。

### コメント標準

```php
/**
 * 関数の簡単な説明
 *
 * 詳細な説明（オプション）
 *
 * @since 2.1.0
 * @param string $param1 パラメータの説明
 * @param int    $param2 パラメータの説明
 * @return string 戻り値の説明
 */
function mpu_example_function($param1, $param2 = 0) {
    // ...
}
```

### 国際化

```php
// 翻訳可能な文字列
__('文字列', 'mp-ukagaka')

// 直接出力される翻訳可能な文字列
_e('文字列', 'mp-ukagaka')

// プレースホルダーを含む文字列
sprintf(__('ようこそ %s', 'mp-ukagaka'), $name)
```

フロントエンドの console log も i18n 対象です。Production-visible な log は PHP 側で `mpu_console_log_i18n_builder()->always()` を使って `mpuL10n.logs` に登録し、JS call site では `mpuLogger.errorL/errorF` または `mpuLogger.warnAlways/warnAlwaysF` を使用します。always-output の `console.warn` を debug-gated な `warnL` に変更してはいけません。移行期間中、console fallback は段階的に日本語 source へ切り替わり、実際の表示は WordPress locale に従って翻訳されます。

### テスト

1. 開発環境ですべての機能をテストする。
2. エラーを確認するために `WP_DEBUG` を使用する。
3. 複数の AI プロバイダーをテストする。
4. 多言語環境をテストする。
5. ブラウザのコンソールにエラーがないことを確認する。

---

## SPA (シングルページアプリケーション) の統合

MP Ukagaka は SPA ナビゲーションをサポートしています。テーマがページ全体を更新する代わりに AJAX を使用してページコンテンツを読み込む場合、再初期化を行うようにプラグインに通知する必要があります。

### イベントのトリガー

テーマは SPA ナビゲーションの完了後に `mpu:spaReady` イベントをトリガーする必要があります：

```javascript
// SPA ナビゲーションの完了後にトリガー
document.dispatchEvent(
  new CustomEvent("mpu:spaReady", {
    detail: {
      url: window.location.href, // オプション: 現在の URL
      title: document.title, // オプション: ページのタイトル
    },
  }),
);
```

### プラグインの応答

プラグインはこのイベントをリッスンし、以下を実行します：

1. 自動会話のタイマーを停止して再起動します。
2. ページ認識 AI を再トリガーします（有効な場合）。
3. ページのコンテキスト情報を更新します。

### 統合例（テーマ）

```javascript
// History API を使用した SPA ナビゲーションの例
document.addEventListener("click", function (e) {
  const link = e.target.closest("a");
  if (!link || link.target === "_blank") return;

  e.preventDefault();

  // AJAX 読み込みを実行...
  fetch(link.href)
    .then((response) => response.text())
    .then((html) => {
      // ページ内容を更新
      document.getElementById("content").innerHTML = html;
      history.pushState({}, "", link.href);

      // MP Ukagaka に通知
      document.dispatchEvent(new CustomEvent("mpu:spaReady"));
    });
});

// ブラウザの戻る/進むを処理
window.addEventListener("popstate", function () {
  // 対応するページコンテンツを読み込んだ後...
  document.dispatchEvent(new CustomEvent("mpu:spaReady"));
});
```

### 注意事項

- イベントは DOM の更新が完了した後にトリガーする必要があります。
- プラグインは会話のステータスの保持を自動的に処理します。
- 会話履歴は同じセッション内に保持されます。

---

## 関連リソース

- [WordPress Plugin Handbook](https://developer.wordpress.org/plugins/)
- [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/)
- [Gemini API ドキュメント](https://ai.google.dev/docs)
- [OpenAI API ドキュメント](https://platform.openai.com/docs)
- [Claude API ドキュメント](https://docs.anthropic.com/)

---

### Happy Coding! 🎉
