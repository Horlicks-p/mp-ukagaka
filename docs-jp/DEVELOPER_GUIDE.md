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

```
mp-ukagaka/
├── mp-ukagaka.php          # メインエントリーポイント
├── includes/               # PHP モジュール
│   ├── core-functions.php      # コア機能
│   ├── utility-functions.php   # ユーティリティ関数
│   ├── ai-functions.php        # AI 機能（クラウド API + Ollama）
│   ├── prompt-categories.php   # Prompt カテゴリ指示管理
│   ├── llm-functions.php       # LLM 機能（Ollama 専用）- BETA
│   ├── ukagaka-functions.php   # 伺か管理
│   ├── ajax-handlers.php       # AJAX 処理
│   ├── frontend-functions.php  # フロントエンド機能
│   └── admin-functions.php     # 管理画面機能
├── dialogs/                # ダイアログファイル
├── images/                 # 画像リソース
│   └── shell/                  # キャラクター画像
├── languages/              # 言語ファイル
├── docs/                   # ドキュメント
├── options/                # 管理画面設定ページ
│   ├── options.php             # 管理画面ページフレームワーク
│   ├── options_page0.php       # 基本設定ページ
│   ├── options_page1.php       # 伺か管理ページ
│   ├── options_page2.php       # ダイアログ設定ページ
│   ├── options_page3.php       # 表示設定ページ
│   ├── options_page4.php       # 詳細設定ページ
│   ├── options_page_ai.php     # AI 機能設定ページ
│   └── options_page_llm.php    # LLM 機能設定ページ（BETA）
├── js/                     # フロントエンド JavaScript モジュール
│   ├── ukagaka-base.js         # 基盤層（設定 + ユーティリティ + AJAX）
│   ├── ukagaka-core.js         # フロントエンドコア JS（メッセージ表示、伺か切り替えなど）
│   ├── ukagaka-features.js     # フロントエンド機能 JS（AI ページ感知、初回訪問者挨拶など）
│   ├── ukagaka-anime.js        # Canvas アニメーションマネージャー（画像シーケンス再生）
│   ├── ukagaka-cookie.js       # Cookie ユーティリティ（訪問者追跡）
│   └── ukagaka-textarearesizer.js  # 管理画面テキストエリアリサイザー
├── mpu_style.css           # フロントエンドスタイルシート
├── admin-style.css         # 管理画面スタイルシート
└── readme.txt              # WordPress プラグインディレクトリ説明ファイル
```

### モジュール読み込み順序

プラグインは条件付き読み込み機構を採用し、実行環境（フロントエンド/管理画面）に応じて対応するモジュールを読み込みます：

```php
// mp-ukagaka.php の読み込みロジック

// コアモジュール：フロントエンドと管理画面の両方で必要
$core_modules = [
    'core-functions.php',      // 1. コア機能（設定管理）
    'utility-functions.php',   // 2. ユーティリティ関数
    'ai-functions.php',        // 3. AI 機能（クラウド API：Gemini, OpenAI, Claude）
    'prompt-categories.php',   // 4. Prompt カテゴリ指示管理（llm-functions.php より前に読み込み）
    'llm-functions.php',       // 5. LLM 機能（ローカル LLM：Ollama）
    'ukagaka-functions.php',   // 6. 伺か管理
    'ajax-handlers.php',       // 7. AJAX 処理（フロントエンドと管理画面両方で使用可能）
];

// フロントエンド専用モジュール（非管理画面環境でのみ読み込み）
$frontend_modules = [
    'frontend-functions.php',  // フロントエンド機能
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

| 定数 | 説明 | 値 |
|-----|------|-----|
| `MPU_VERSION` | プラグインバージョン | `"2.2.0"` |
| `MPU_MAIN_FILE` | メインファイルパス | `__FILE__` |

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

| プロバイダー | 関数 | API エンドポイント | モデル選択 |
|-------|------|---------|---------
| Gemini | `mpu_call_gemini_api()` | `generativelanguage.googleapis.com` | サポート |
| OpenAI | `mpu_call_openai_api()` | `api.openai.com` | サポート |
| Claude | `mpu_call_claude_api()` | `api.anthropic.com` | サポート |
| Ollama | `mpu_call_ollama_api()` | ローカルまたはリモート Ollama サービス | サポート |

### llm-functions.php (BETA)

> ⚠️ **注意**：このモジュールは**テスト段階（BETA）**で、API が変更される可能性があります。

LLM 機能モジュール、Ollama ローカル LLM 統合を専門に処理。

#### タイムアウト設定

| 操作タイプ | ローカル接続 | リモート接続 |
|---------|---------|---------
| サービスチェック (`check`) | 3 秒 | 10 秒 |
| API 呼び出し (`api_call`) | 60 秒 | 90 秒 |
| 接続テスト (`test`) | 30 秒 | 45 秒 |

### ukagaka-functions.php

伺か管理モジュール、キャラクター関連操作とダイアログ管理を処理。

### ajax-handlers.php

AJAX 処理モジュール、すべての AJAX リクエストを処理。

### frontend-functions.php

フロントエンド機能モジュール、ページ表示とリソース読み込みを担当。

### admin-functions.php

管理画面機能モジュール、設定保存と管理画面インターフェースを処理。

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
    'openai_model' => 'gpt-4o-mini',    // OpenAI モデル
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

| アクション | 説明 | パラメータ |
|--------|------|----------|
| `mpu_nextmsg` | 次のメッセージを取得 | `cur_num`, `cur_msgnum` |
| `mpu_extend` | 拡張機能を実行 | 状況による |
| `mpu_change` | 伺かを切り替え | `new_num` |
| `mpu_get_settings` | 設定を取得 | なし |
| `mpu_load_dialog` | ダイアログファイルを読み込み | `filename`, `format` |
| `mpu_chat_context` | AI ページ感知 | `post_content`, `post_title` |
| `mpu_get_visitor_info` | 訪問者情報を取得 | なし |
| `mpu_chat_greet` | 初回訪問者挨拶 | `visitor_info` |

### 管理画面エンドポイント（wp_ajax_*）

| アクション | 説明 |
|--------|------|
| `mpu_test_ollama` | Ollama 接続テスト |
| `mpu_test_gemini` | Gemini 接続テスト |
| `mpu_test_openai` | OpenAI 接続テスト |
| `mpu_test_claude` | Claude 接続テスト |

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

2. `mpu_call_ai_api()` にプロバイダー分岐を追加

3. 管理画面に設定フィールドを追加

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

**Made with ❤ for WordPress**
