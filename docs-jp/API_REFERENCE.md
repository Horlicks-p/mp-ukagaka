# MP Ukagaka API リファレンス

> 📚 完全な関数、フック、REST エンドポイントのリファレンス（v2.13.6）

---

## 📑 目次

1. [PHP 関数](#php-関数)
2. [WordPress フック](#wordpress-フック)
3. [REST エンドポイント](#rest-エンドポイント)
4. [JavaScript 関数](#javascript-関数)
5. [特殊コード](#特殊コード)

---

## PHP 関数

### コア関数 (core-functions.php)

#### mpu_default_opt()

デフォルト設定値を取得します。

```php
/**
 * @return array デフォルト設定の配列
 */
function mpu_default_opt()
```

**例：**

```php
$defaults = mpu_default_opt();
echo $defaults['auto_talk_interval']; // 8
```

---

#### mpu_get_option()

プラグイン設定を取得します（キャッシュ対応）。

```php
/**
 * @return array 設定の配列
 */
function mpu_get_option()
```

**例：**

```php
$mpu_opt = mpu_get_option();
if ($mpu_opt['ai_enabled']) {
    // AIが有効な場合
}
```

---

#### mpu_count_total_msg()

すべての伺かの総ダイアログ（会話）数を計算します。

```php
/**
 * @return int 総ダイアログ数
 */
function mpu_count_total_msg()
```

---

### ユーティリティ関数 (utility-functions.php)

#### mpu_array2str()

配列を文字列に変換します（改行区切り）。

```php
/**
 * @param array $arr 入力配列
 * @return string 出力文字列
 */
function mpu_array2str($arr = [])
```

**例：**

```php
$messages = ['会話1', '会話2', '会話3'];
$str = mpu_array2str($messages);
// 結果：
// 会話1
//
// 会話2
//
// 会話3
```

---

#### mpu_str2array()

文字列を配列に変換します（空行区切り）。

```php
/**
 * @param string $str 入力文字列
 * @return array 出力配列
 */
function mpu_str2array($str = "")
```

**例：**

```php
$str = "会話1\n\n会話2\n\n会話3";
$messages = mpu_str2array($str);
// 結果：['会話1', '会話2', '会話3']
```

---

#### mpu_output_filter()

HTML出力フィルター。

```php
/**
 * @param string $str 入力文字列
 * @return string フィルタリングされた文字列
 */
function mpu_output_filter($str)
```

---

#### mpu_js_filter()

JavaScript出力フィルター（引用符と特殊文字のエスケープ）。

```php
/**
 * @param string $str 入力文字列
 * @return string フィルタリングされた文字列
 */
function mpu_js_filter($str)
```

---

#### mpu_input_filter()

入力フィルター（保存前の処理）。

```php
/**
 * @param string $str 入力文字列
 * @return string フィルタリングされた文字列
 */
function mpu_input_filter($str)
```

---

#### mpu_secure_file_read()

ファイルを安全に読み込みます。

```php
/**
 * @param string $file_path ファイルパス（dialogs/ディレクトリ内であること）
 * @return string|WP_Error ファイル内容またはエラー
 */
function mpu_secure_file_read($file_path)
```

**例：**

```php
$content = mpu_secure_file_read('/path/to/file.txt');
if (is_wp_error($content)) {
    echo $content->get_error_message();
} else {
    echo $content;
}
```

**考えられるエラー：**

| エラーコード | 説明 |
| ------------------ | -------------------------------- |
| `file_not_found` | 指定されたファイルが見つかりません |
| `path_not_allowed` | このパスの読み込みは許可されていません |
| `file_too_large` | ファイルが大きすぎて読み込めません |
| `read_failed` | ファイルの読み込みに失敗しました |

---

#### mpu_secure_file_write()

ファイルを安全に書き込みます。

```php
/**
 * @param string $file_path ファイルパス
 * @param string $content ファイル内容
 * @return bool|WP_Error 成功またはエラー
 */
function mpu_secure_file_write($file_path, $content)
```

**考えられるエラー：**

| エラーコード | 説明 |
| ------------------ | ------------------------------ |
| `mkdir_failed` | ディレクトリの作成に失敗しました |
| `path_not_allowed` | このパスへの書き込みは許可されていません |
| `invalid_filename` | 無効なファイル名です |
| `write_failed` | ファイルの書き込みに失敗しました |

---

#### mpu_encrypt_api_key()

AES-256-CBCを使用してAPIキーを暗号化します。

```php
/**
 * @param string $api_key 生のAPIキー
 * @return string 暗号化された文字列
 */
function mpu_encrypt_api_key($api_key)
```

---

#### mpu_decrypt_api_key()

APIキーを復号化します。

```php
/**
 * @param string $encrypted 暗号化された文字列
 * @return string 復号化されたAPIキー
 */
function mpu_decrypt_api_key($encrypted_key)
```

---

#### mpu_get_client_ip()

クライアントの実際のIPアドレスを取得します（リバースプロキシ対応）。

```php
/**
 * @return string クライアントIPアドレス
 */
function mpu_get_client_ip()
```

---

#### mpu_fetch_external_api()

汎用的な外部APIリクエスト関数（キャッシュ機能付き）。

```php
/**
 * @param string $cache_key キャッシュキー
 * @param string $url APIエンドポイントURL
 * @param int $cache_duration キャッシュ期間（秒）
 * @param array $options 追加オプション
 * @return array|string|null APIレスポンスデータ
 */
function mpu_fetch_external_api($cache_key, $url, $cache_duration = MPU_CACHE_DEFAULT, $options = [])
```

---

#### mpu_render_prompt_template()

プロンプトテンプレートをレンダリングし、`{{変数名}}`を実際の値に置き換えます。

```php
/**
 * @param string $template テンプレート文字列
 * @param array $variables 変数配列
 * @return string 置き換えられた文字列
 */
function mpu_render_prompt_template($template, $variables = [])
```

---

#### mpu_get_current_user_info()

現在のWordPressユーザー情報を取得します。

```php
/**
 * @return array 現在のユーザー情報の配列
 */
function mpu_get_current_user_info()
```

---

#### mpu_get_wordpress_info()

WordPressサイト情報（基本情報および統計情報を含む）を取得します。

```php
/**
 * @return array WordPressサイト情報の配列
 */
function mpu_get_wordpress_info()
```

---

#### mpu_get_provider_api_key()

指定されたAIプロバイダーの復号化されたAPIキーを取得します。

```php
/**
 * @param string $provider AIプロバイダー名（gemini, openai, claude, ollama）
 * @param array|null $mpu_opt オプション配列
 * @return string 復号化されたAPIキー
 */
function mpu_get_provider_api_key($provider, $mpu_opt = null)
```

---

#### mpu_get_current_provider()

現在有効なAIプロバイダー名を取得します。

```php
/**
 * @param array|null $mpu_opt オプション配列
 * @return string AIプロバイダー名
 */
function mpu_get_current_provider($mpu_opt = null)
```

---

### AI 関数 (ai-functions.php)

#### mpu_call_ai_api()

AI APIを呼び出します（プロバイダーを自動選択）。Gemini、OpenAI、Claudeをサポート。

```php
/**
 * @param string $provider AIプロバイダー（'gemini', 'openai', 'claude', 'ollama'）
 * @param string $api_key APIキー
 * @param string $system_prompt システムプロンプト（キャラクター設定）
 * @param string $user_prompt ユーザープロンプト
 * @param string $language 言語設定（'zh-TW', 'ja', 'en'）
 * @param array|null $mpu_opt プラグイン設定（モデル名取得用）
 * @param int|null $max_tokens 最大トークン数（オプション）
 * @return string|WP_Error AIの応答またはエラー
 */
function mpu_call_ai_api(
    $provider,
    $api_key,
    $system_prompt,
    $user_prompt,
    $language = 'zh-TW',
    $mpu_opt = null,
    $max_tokens = null
)
```

**例：**

```php
$response = mpu_call_ai_api(
    'gemini',
    $api_key,
    'あなたはフレンドリーなアシスタントです。短く返答してください。',
    'この記事は何について書かれていますか？',
    'ja',
    $mpu_opt
);
if (!is_wp_error($response)) {
    echo $response;
}
```

---

#### mpu_get_language_instruction()

言語指示の文字列を取得します。

```php
/**
 * @param string $language 言語コード (zh-TW, ja, en)
 * @return string 言語指示
 */
function mpu_get_language_instruction($language)
```

**戻り値：**

| 言語コード | 戻り値 |
| -------- | ---------------------------- |
| `zh-TW`  | `請用繁體中文回覆。` |
| `ja`     | `日本語で返答してください。` |
| `en`     | `Please reply in English.` |

---

#### mpu_call_gemini_api()

Google Gemini APIを呼び出します。

```php
/**
 * @param string $api_key Gemini APIキー
 * @param string $model モデル名（例: 'gemini-2.5-flash'）
 * @param string $system_prompt システムプロンプト
 * @param string $user_prompt ユーザープロンプト
 * @param string $language 言語設定
 * @param int|null $max_tokens 最大トークン数（オプション）
 * @return string|WP_Error AIの応答またはエラー
 */
function mpu_call_gemini_api(
    $api_key,
    $model,
    $system_prompt,
    $user_prompt,
    $language = 'zh-TW',
    $max_tokens = null
)
```

---

#### mpu_call_openai_api()

OpenAI APIを呼び出します。

```php
/**
 * @param string $api_key OpenAI APIキー
 * @param string $model モデル名（例: 'gpt-4o-mini'）
 * @param string $system_prompt システムプロンプト
 * @param string $user_prompt ユーザープロンプト
 * @param string $language 言語設定
 * @param int|null $max_tokens 最大トークン数（オプション）
 * @return string|WP_Error AIの応答またはエラー
 */
function mpu_call_openai_api(
    $api_key,
    $model,
    $system_prompt,
    $user_prompt,
    $language = 'zh-TW',
    $max_tokens = null
)
```

---

#### mpu_call_claude_api()

Anthropic Claude APIを呼び出します。

```php
/**
 * @param string $api_key Claude APIキー
 * @param string $model モデル名（例: 'claude-sonnet-4-5-20250929'）
 * @param string $system_prompt システムプロンプト
 * @param string $user_prompt ユーザープロンプト
 * @param string $language 言語設定
 * @param int|null $max_tokens 最大トークン数（オプション）
 * @return string|WP_Error AIの応答またはエラー
 */
function mpu_call_claude_api(
    $api_key,
    $model,
    $system_prompt,
    $user_prompt,
    $language = 'zh-TW',
    $max_tokens = null
)
```

---

#### mpu_call_ollama_api()

Ollama API（ローカルまたはリモート）を呼び出します。

```php
/**
 * @param string $endpoint Ollama エンドポイント URL
 * @param string $model モデル名（例: 'qwen3:8b'）
 * @param string $system_prompt システムプロンプト
 * @param string $user_prompt ユーザープロンプト
 * @param string $language 言語設定
 * @param int|null $max_tokens 最大トークン数（オプション）
 * @return string|WP_Error AIの応答またはエラー
 */
function mpu_call_ollama_api(
    $endpoint,
    $model,
    $system_prompt,
    $user_prompt,
    $language = 'zh-TW',
    $max_tokens = null
)
```

**機能の特徴：**

- ローカル/リモート接続の自動検出
- 接続タイプに応じたタイムアウトの調整
- 思考モードの無効化対応（Qwen3、DeepSeek等のモデル）

---

### API キャッシュ関数 (api-cache.php)

> 💡 **v2.5.6 新機能**：APIキャッシュシステム。WordPress Transient APIを使用してAI APIの応答をキャッシュし、重複リクエストとコストを削減します。

#### mpu_is_api_cache_enabled()

APIキャッシュが有効かどうかを確認します。

```php
/**
 * @return bool
 */
function mpu_is_api_cache_enabled()
```

---

#### mpu_get_api_cache_ttl()

キャッシュのTTL（秒）を取得します。

```php
/**
 * @return int デフォルト 3600 秒（1時間）、範囲 300〜86400 秒
 */
function mpu_get_api_cache_ttl()
```

---

#### mpu_generate_cache_key()

キャッシュキーを生成します。

```php
/**
 * @param string $provider プロバイダー
 * @param string $system_prompt システムプロンプト
 * @param string $user_prompt ユーザープロンプト
 * @return string キャッシュキー
 */
function mpu_generate_cache_key($provider, $system_prompt, $user_prompt)
```

---

#### mpu_get_cached_api_response()

キャッシュからAPIレスポンスを取得します。

```php
/**
 * @param string $cache_key キャッシュキー
 * @return string|false キャッシュされた応答、または false
 */
function mpu_get_cached_api_response($cache_key)
```

---

#### mpu_set_cached_api_response()

APIレスポンスをキャッシュに保存します。

```php
/**
 * @param string $cache_key キャッシュキー
 * @param string $response APIレスポンス
 * @return bool
 */
function mpu_set_cached_api_response($cache_key, $response)
```

---

#### mpu_clear_all_api_cache()

すべてのLLM APIキャッシュをクリアします。

```php
/**
 * @return int クリアされたキャッシュの数
 */
function mpu_clear_all_api_cache()
```

---

#### mpu_get_api_cache_stats()

APIキャッシュの統計情報を取得します。

```php
/**
 * @return array ['count' => int, 'ttl' => int, 'enabled' => bool]
 */
function mpu_get_api_cache_stats()
```

---

### LLM 関数 (llm-functions.php)

> 💡 **2.2.0 更新**：LLM機能が**汎用LLMインターフェース**にアップグレードされ、Ollama、Gemini、OpenAI、Claudeの4つのAIサービスをサポートするようになりました。

#### mpu_is_remote_endpoint()

エンドポイントがリモート接続かどうかを検出します。

```php
/**
 * @param string $endpoint Ollama エンドポイント URL
 * @return bool リモート接続かどうか（true = リモート, false = ローカル）
 */
function mpu_is_remote_endpoint($endpoint)
```

**例：**

```php
$is_remote = mpu_is_remote_endpoint('https://your-domain.com'); // true
$is_local = mpu_is_remote_endpoint('http://localhost:11434');  // false
```

---

#### mpu_get_ollama_timeout()

エンドポイントタイプと操作タイプに基づいて適切なタイムアウトを取得します。

```php
/**
 * @param string $endpoint Ollama エンドポイント URL
 * @param string $operation_type 操作タイプ: 'check'（サービス確認）, 'api_call'（API呼び出し）, 'test'（接続テスト）
 * @return int タイムアウト時間（秒）
 */
function mpu_get_ollama_timeout($endpoint, $operation_type = 'api_call')
```

**例：**

```php
$timeout = mpu_get_ollama_timeout('https://your-domain.com', 'api_call'); // 90
$timeout = mpu_get_ollama_timeout('http://localhost:11434', 'check');      // 3
```

---

#### mpu_validate_ollama_endpoint()

OllamaエンドポイントURLを検証し、正規化します。

```php
/**
 * @param string $endpoint 元のエンドポイントURL
 * @return string|WP_Error 正規化されたURLまたはエラー
 */
function mpu_validate_ollama_endpoint($endpoint)
```

**例：**

```php
$validated = mpu_validate_ollama_endpoint('https://your-domain.com');
if (is_wp_error($validated)) {
    echo $validated->get_error_message();
} else {
    echo $validated; // 'https://your-domain.com'
}
```

---

#### mpu_check_ollama_available()

Ollamaサービスが利用可能かどうかを確認します（キャッシュを使用するクイックチェック）。

```php
/**
 * @param string $endpoint Ollama エンドポイント
 * @param string $model モデル名
 * @return bool サービスが利用可能かどうか
 */
function mpu_check_ollama_available($endpoint, $model)
```

**例：**

```php
if (mpu_check_ollama_available('https://your-domain.com', 'qwen3:8b')) {
    // サービス利用可能
}
```

---

#### mpu_generate_llm_dialogue()

LLMを使用してランダムな会話を生成します（組み込み会話の代替）。すべてのAIプロバイダー（Ollama、Gemini、OpenAI、Claude）をサポートします。

```php
/**
 * @param string $ukagaka_name キャラクター名
 * @param string $last_response 前回のAIの応答（重複防止用）
 * @param array $response_history 応答履歴の配列（より厳密な重複検出用）
 * @param int $last_visit_hours 最終訪問からの経過時間（デフォルト -1 はデータなし）
 * @return string|false 生成された会話内容、失敗時は false
 */
function mpu_generate_llm_dialogue($ukagaka_name = 'default_1', $last_response = '', $response_history = [], $last_visit_hours = -1)
```

**例：**

```php
$dialogue = mpu_generate_llm_dialogue('frieren');
if ($dialogue !== false) {
    echo $dialogue;
}

// 重複検出付き
$dialogue = mpu_generate_llm_dialogue('frieren', '前回の応答', ['応答1', '応答2']);
```

**機能の特徴：**

- 最適化されたXML構造化システムプロンプトの自動使用
- 重複会話防止メカニズムのサポート（類似度検出）
- WordPress情報、ユーザー情報、訪問者情報の自動統合
- 70以上のフリーレン風の会話サンプルをサポート

**利用可能なフィルターフック (v2.5.7)：**

| フィルター | 説明 | パラメータ |
| ----------------------- | -------------------------- | --------------------------------------------------------- |
| `mpu_llm_system_prompt` | システムプロンプトを変更 | `$prompt`, `$ukagaka_name`, `$personality_id`, `$context` |
| `mpu_llm_user_prompt` | 会話指示前に追加コンテキストを注入 | `$prompt`, `$ukagaka_name`, `$personality_id` |

**使用例（セキュリティ警告の統合）：**

```php
add_filter('mpu_llm_user_prompt', function($prompt, $ukagaka_name, $personality_id) {
    $attack_info = get_transient('mpu_llar_attack_info');
    if ($attack_info) {
        return $prompt . "\n【セキュリティ警告】\n" . $attack_info;
    }
    return $prompt;
}, 10, 3);
```

---

#### mpu_is_llm_replace_dialogue_enabled()

LLMによる組み込み会話の置換が有効かどうかを確認します。

```php
/**
 * @return bool
 */
function mpu_is_llm_replace_dialogue_enabled()
```

---

#### mpu_get_ollama_settings()

Ollama設定を取得します。

```php
/**
 * @return array|false 設定配列、無効時は false
 */
function mpu_get_ollama_settings()
```

**戻り値：**

```php
[
    'endpoint' => 'http://localhost:11434',
    'model' => 'qwen3:8b',
    'replace_dialogue' => true,
]
```

---

#### mpu_get_visitor_info_for_llm()

訪問者情報を取得します（LLM会話生成用）。Slimstatデータと統合し、BOT検出や位置情報を含みます。

```php
/**
 * @return array 訪問者情報の配列
 */
function mpu_get_visitor_info_for_llm()
```

**戻り値：**

```php
[
    'is_bot' => false,                    // BOTかどうか
    'browser_type' => 0,                  // ブラウザタイプ（0=一般, 1=BOT, 2=モバイル）
    'browser_name' => 'Chrome',           // ブラウザ名（BOT名）
    'slimstat_enabled' => true,           // Slimstatが有効か
    'slimstat_country' => 'JP',           // 国コード
    'slimstat_city' => 'Tokyo',           // 都市名
]
```

---

#### mpu_get_visitor_status_text()

訪問者ステータステキスト（BOTまたは位置情報）を取得します。

```php
/**
 * @param array $visitor_info 訪問者情報
 * @return string 訪問者ステータスの説明
 */
function mpu_get_visitor_status_text($visitor_info)
```

**例：**

```php
$visitor_info = mpu_get_visitor_info_for_llm();
$status = mpu_get_visitor_status_text($visitor_info);
// 戻り値の例：'🤖 BOT: Googlebot' または 'JP / Tokyo から'
```

---

#### mpu_compress_context_info()

WordPress、ユーザー、および訪問者情報をシステムプロンプト用のコンパクトなXML形式に圧縮します。

```php
/**
 * @param array $wp_info WordPress情報
 * @param array $user_info ユーザー情報
 * @param array $visitor_info 訪問者情報
 * @return string 圧縮されたXML形式の文字列
 */
function mpu_compress_context_info($wp_info, $user_info, $visitor_info)
```

---

#### mpu_weighted_random_select()

重み配列に基づいてカテゴリ配列からランダムに1つのカテゴリを選択します（重み付きランダム選択）。

```php
/**
 * @param array $categories カテゴリ配列（key => value）
 * @param array $weights 重み配列（key => weight）、数値が高いほど選択確率が高い
 * @return string 選択されたカテゴリのキー
 */
function mpu_weighted_random_select($categories, $weights)
```

**使用例：**

```php
$categories = [
    'greeting' => ['挨拶1', '挨拶2'],
    'casual' => ['雑談1', '雑談2'],
    'tech_observation' => ['技術1', '技術2'],
];

$weights = [
    'greeting' => 10,
    'casual' => 10,
    'tech_observation' => 3,  // 技術観察カテゴリの重みを下げる
];

$selected = mpu_weighted_random_select($categories, $weights);
// 戻り値の例：'greeting'、'casual' または 'tech_observation'
// tech_observation が選ばれる確率は他と比べて約30%になります
```

**注意事項：**

- カテゴリが重み配列に設定されていない場合、デフォルトの重みは 5 になります。
- 総重みが 0 の場合、均一なランダム選択（`array_rand()`）が使用されます。
- 重みの数値が高いほど、選択される確率が高くなります。

---

#### mpu_build_optimized_system_prompt()

最適化されたシステムプロンプト（XML構造化バージョン）を構築します。

```php
/**
 * @param array $mpu_opt プラグイン設定
 * @param array $wp_info WordPress情報
 * @param array $user_info ユーザー情報
 * @param array $visitor_info 訪問者情報
 * @param string $ukagaka_name キャラクター名
 * @param string $time_context 時間帯のコンテキスト（朝/午後/夜/深夜）
 * @param string $language 言語設定
 * @return string 最適化されたシステムプロンプト
 */
function mpu_build_optimized_system_prompt(
    $mpu_opt,
    $wp_info,
    $user_info,
    $visitor_info,
    $ukagaka_name,
    $time_context,
    $language
)
```

**返されるXML構造：**

```xml
<character>
名称：{キャラクター名}
コア設定：{管理画面からのシステムプロンプト}
スタイルの特徴：...
</character>
<knowledge_base>
{圧縮されたコンテキスト情報}
</knowledge_base>
<behavior_rules>
  <must_do>...</must_do>
  <should_do>...</should_do>
  <must_not_do>...</must_not_do>
</behavior_rules>
<response_style_examples>
{70以上の会話サンプル}
</response_style_examples>
<current_context>
時間：{時間帯のコンテキスト}
言語：{言語設定}
</current_context>
```

---

#### mpu_calculate_text_similarity()

2つのテキスト間の類似度を計算します（重複会話の防止用）。

```php
/**
 * @param string $text1 1つ目のテキスト
 * @param string $text2 2つ目のテキスト
 * @param bool $text1_normalized $text1が正規化済みかどうか
 * @return float 類似度（0.0-1.0）
 */
function mpu_calculate_text_similarity($text1, $text2, $text1_normalized = false)
```

**例：**

```php
$similarity = mpu_calculate_text_similarity('また来たのね。', 'また来たのね。');
// 戻り値：1.0（完全一致）

$similarity = mpu_calculate_text_similarity('また来たのね。', '久しぶり。');
// 戻り値：0.0（全く異なる）
```

---

#### mpu_debug_system_prompt()

デバッグモード：システムプロンプトをWordPressのデバッグログに出力します。

```php
/**
 * @param string $system_prompt システムプロンプトの内容
 * @return void
 */
function mpu_debug_system_prompt($system_prompt)
```

**使用条件：**

- `WP_DEBUG` が `true` の場合のみ出力します。
- `wp-content/debug.log` に出力されます。
- システムプロンプトの内容、推定トークン数、文字数が含まれます。

---

### プロンプトカテゴリ管理 (prompt-categories.php)

> 💡 **v2.2.0 新機能**：プロンプトのカテゴリ指示管理モジュール。LLM会話生成時のカテゴリ指示や動的な重み設定に使用します。

#### mpu_get_static_prompt_categories()

静的なカテゴリ指示を取得します（再構築を避けるためキャッシュを使用）。

```php
/**
 * @param string|null $personality_id 人格ID（オプション、デフォルトは現在の人格）
 * @return array 静的カテゴリ指示の配列
 */
function mpu_get_static_prompt_categories($personality_id = null)
```

---

#### mpu_add_statistics_prompts()

動的な統計カテゴリ指示を追加します。WordPressの統計情報に基づいて会話の指示を生成します。

```php
/**
 * @param array &$categories カテゴリ配列（参照渡し）
 * @param array $wp_info WordPress情報
 * @param string|null $personality_id 人格ID（オプション、デフォルトは現在の人格）
 * @return void
 */
function mpu_add_statistics_prompts(&$categories, $wp_info, $personality_id = null)
```

---

#### mpu_build_prompt_categories()

ユーザープロンプトのカテゴリ指示を構築します。この関数は異なるカテゴリの会話指示を生成し、「組み込み会話をLLMで置換する」機能で使用されます。

```php
/**
 * @param array $wp_info WordPress情報
 * @param array $visitor_info 訪問者情報
 * @param string $time_context 時間帯のコンテキスト
 * @param string $theme_name テーマ名
 * @param string $theme_version テーマバージョン
 * @param string $theme_author テーマ作者
 * @return array カテゴリ指示の配列
 */
function mpu_build_prompt_categories(
    $wp_info,
    $visitor_info,
    $time_context,
    $theme_name,
    $theme_version,
    $theme_author
)
```

**戻り値の構造：**

```php
[
    'greeting' => ['挨拶の会話例を参考に、軽く挨拶する', ...],
    'casual' => ['雑談の会話例を参考に、淡々とした日常の言葉を言う', ...],
    'time_aware' => ['時間認識の会話例を参考に、時間感覚を表現する', ...],
    // ... 計35カテゴリ
]
```

---

#### mpu_get_dynamic_category_weights()

動的なカテゴリの重み設定を取得します。時間帯のコンテキストや訪問者情報に基づいて各カテゴリの重みを調整します。

```php
/**
 * @param string $time_context 時間帯のコンテキスト
 * @param array $visitor_info 訪問者情報
 * @param array $context_vars コンテキスト変数（オプション）
 * @return array 重みの配列
 */
function mpu_get_dynamic_category_weights(
    $time_context,
    $visitor_info,
    $context_vars = [],
    $personality_id = null
)
```

**特別な調整ロジック：**

- 深夜：`silence`、`philosophical`、`party_memories` の重みが増加
- 朝：`daily_life` の重みが増加（キャラクターが朝に弱いため）
- BOT訪問者：`bot_detection` カテゴリの重みが大幅に増加

---

#### mpu_get_decoration_prompt()

装飾品クリック会話のプロンプトを取得します。ユーザーが装飾品をクリックした際に、対応するユーザープロンプトの指示を返します。

```php
/**
 * @param string $decoration_type 装飾品タイプ（suitcase, evil_horns, staff, books）
 * @param string|null $personality_id 人格ID（オプション、デフォルトは現在の人格）
 * @return string|false プロンプト、見つからない場合は false
 */
function mpu_get_decoration_prompt($decoration_type, $personality_id = null)
```

**サポートされている装飾品タイプ：**

| タイプ | 説明 |
| ------------ | -------------------- |
| `suitcase` | トランク（魔法収集箱） |
| `evil_horns` | 悪魔の角飾り |
| `staff` | 魔法の杖 |
| `books` | 魔導書 |

---

### 伺か関数 (ukagaka-functions.php)

#### mpu_get_shell()

指定されたキャラクターのシェル画像URLを取得します。

```php
/**
 * @param string|false $num キャラクターのキー値；false の場合は現在のキャラクターを使用
 * @param bool $echo 直接出力するかどうか（デフォルト false、文字列を返す）
 * @return string 画像URL
 */
function mpu_get_shell($num = false, $echo = false)
```

---

#### mpu_get_msg_arr()

指定されたキャラクターのメッセージ配列構造（`msgall`、`auto_msg`、`msg` などのキーを含む）を取得します。

```php
/**
 * @param string $num キャラクターのキー値（例: 'default_1', 'frieren'）
 * @return array メッセージの配列
 */
function mpu_get_msg_arr($num)
```

---

#### mpu_msg_code()

メッセージ配列内の特殊コード（`:recentpost[n]:`、`:commenters[n]:` など）を処理し、実際のHTMLに置き換えます。

```php
/**
 * @param array $msglist メッセージの配列
 * @return array 処理後のメッセージ配列
 */
function mpu_msg_code($msglist = [])
```

---

#### mpu_get_msg_from_file()

`dialogs/` ディレクトリ配下の会話ファイル（`.txt` / `.json` 形式を自動判別）を読み込みます。

```php
/**
 * @param string $filename_base ファイル名（拡張子なし）
 * @return array 会話の配列
 */
function mpu_get_msg_from_file($filename_base)
```

**例：**

```php
$messages = mpu_get_msg_from_file('frieren');
```

---

### フロントエンド関数 (frontend-functions.php)

#### mpu_html()

伺かのHTMLを生成して出力します。

```php
/**
 * @param string|false $num キャラクターのキー値；false の場合は現在のキャラクターを使用
 * @return void
 */
function mpu_html($num = false)
```

---

### 管理画面関数 (admin-functions.php)

#### mpu_generate_dialog_file()

メッセージ配列を会話ファイル（`.txt` または `.json`）として書き出します。

```php
/**
 * @param string $filename ファイル名（拡張子なし）
 * @param array $msg_array メッセージの配列
 * @param string $ext 拡張子（'txt' または 'json'）
 * @return bool 成功したかどうか
 */
function mpu_generate_dialog_file($filename, $msg_array, $ext)
```

---

## WordPress フック

> 📌 v2.9.2 の REST リファクタリング以降、プラグインレベルの `do_action()` フック（`mpu_loaded`, `mpu_before_html`, `mpu_after_html`, `mpu_settings_saved`）および `apply_filters()` フック（`mpu_options`, `mpu_messages`, `mpu_ai_response`, `mpu_ukagaka_html`）はすべて削除されました。現在は、LLMプロンプトの構築に関連する4つのフィルターのみが保持されています。

### フィルター

#### mpu_llm_system_prompt

LLMのシステムプロンプトをフィルタリングします（人格カード、WordPressのコンテキスト、行動ルールなどを含む完全なXML構造化内容）。

```php
add_filter('mpu_llm_system_prompt', function($prompt, $ukagaka_name, $personality_id, $context) {
    return $prompt;
}, 10, 4);
```

---

#### mpu_llm_user_prompt

ユーザー会話プロンプトをフィルタリングします（会話指示の前に、セキュリティ警告やイベントメッセージなどの追加コンテキストを注入します）。

```php
add_filter('mpu_llm_user_prompt', function($prompt, $ukagaka_name, $personality_id) {
    $attack_info = get_transient('mpu_llar_attack_info');
    if ($attack_info) {
        return $prompt . "\n【セキュリティ警告】\n" . $attack_info;
    }
    return $prompt;
}, 10, 3);
```

---

#### mpu_prompt_categories

LLMの自動会話のカテゴリ定義をフィルタリングします（挨拶、雑談、時間認識、統計観察など、35以上のカテゴリ）。

```php
add_filter('mpu_prompt_categories', function($categories, $wp_info, $visitor_info, $time_context) {
    return $categories;
}, 10, 4);
```

---

#### mpu_category_weights

会話カテゴリの重み付きランダムの重み設定をフィルタリングします。数値が高いほど選ばれやすくなります。デフォルトは 5 です。

```php
add_filter('mpu_category_weights', function($weights, $time_context, $visitor_info, $context_vars) {
    // 深夜帯に思索的な会話の確率を上げる
    if ($time_context === '深夜') {
        $weights['philosophical'] = 15;
    }
    return $weights;
}, 10, 4);
```

---

## REST エンドポイント

> 💡 **v2.9.2 以降**：プラグインのエンドポイントアーキテクチャは AJAX から REST API に完全移行し、レート制限とエラー処理が統一されました。フロントエンドから呼び出す際は `X-WP-Nonce` が必要です（`wp_localize_script` 経由で `mpuRestNonce` として提供されます）。

### 基本情報

- **ネームスペース**：`/wp-json/mp-ukagaka/v1`
- **権限**：ほとんどのエンドポイントは公開（`__return_true`）ですが、テストやキャッシュ管理エンドポイントは管理者限定です。
- **レート制限**：エンドポイントごとに独立してカウントされ、超過した場合は HTTP 429 を返します。
- **レスポンス形式**：`/chat/user-stream` (SSE) を除き、すべて JSON です。構造は `{ success, data, ... }` または `WP_Error` となります。

### キャラクター / 設定系

| エンドポイント | メソッド | 権限 | パラメータ（すべてオプション） | レート制限 | 説明 |
| --- | --- | --- | --- | --- | --- |
| `/init` | GET | 公開 | `ukagaka_num` | 30/60秒 | シェル、装飾、絵文字、タッチゾーン、設定を一度に取得する統合初期化エンドポイント |
| `/settings` | GET | 公開 | — | 30/60秒 | フロントエンドで使用される設定オブジェクト（`auto_talk`, `typewriter_speed`, `ai_*` など）を取得 |
| `/change` | POST | 公開 | `mpu_num` | 10/60秒 | パラメータなしの場合は切り替え可能なキャラクター一覧を返し、ある場合はキャラクターを切り替える（Set-Cookie 含む） |
| `/shell-info` | GET / POST | 公開 | `ukagaka_num` | 30/60秒 | 指定されたキャラクターの外観情報を取得 |
| `/decoration-config` | GET / POST | 公開 | — | 30/60秒 | 装飾のベースURL、設定、タッチゾーン、表示フラグを取得 |
| `/emoji-config` | GET / POST | 公開 | — | 30/60秒 | 絵文字のベースURL、サポートリスト、キーワードマッピングを取得 |
| `/extend` | GET / POST | 公開 | — | 10/60秒 | キャラクター拡張タグの位置（予約エンドポイント） |

### 会話系

| エンドポイント | メソッド | 権限 | パラメータ | レート制限 | 説明 |
| --- | --- | --- | --- | --- | --- |
| `/nextmsg` | POST | 公開 | `cur_num`, `cur_msgnum`, `last_response`, `response_history`, `last_visit_hours`, `session_id`, `history` | 20/60秒 | 自動会話のローテーション：LLM置換モードがオンの場合はAI生成を呼び出し、それ以外は組み込み会話から抽出 |
| `/dialog` | GET / POST | 公開 | `file`（必須） | 30/60秒 | `dialogs/` 下の会話ファイルを読み込む；`{msgall, auto_msg, msg, next_msg, default_msg}` を返す |
| `/visitor-info` | GET | 公開 | — | 30/60秒 | リファラー、検索エンジン、Slimstatの国/都市などの訪問者情報を返す |
| `/decoration-prompts` | GET / POST | 公開 | `decoration_type` | 20/60秒 | 装飾品クリック会話のプロンプトを取得 |
| `/wake-ghost` | POST | 公開 | `personality_id` または `ukagaka_num`（少なくとも1つ） | 10/60秒 | スリープモードのキャラクターを一時的に起こす；WP_Error コード：`rest_wake_ghost_missing_param`, `rest_wake_ghost_unavailable` |

### AI 会話系（Chat）

| エンドポイント | メソッド | 権限 | パラメータ | レート制限 | 説明 |
| --- | --- | --- | --- | --- | --- |
| `/chat/context` | POST | 公開 | `page_title`, `page_content`, `publish_date`, `session_id`, `history` | 5/60秒 | ページ認識会話。現在の記事内容に基づいてAIコメントをトリガー；最大500文字 |
| `/chat/greet` | POST | 公開 | `referrer`, `referrer_host`, `search_engine`, `is_direct`, `country`, `city`, `session_id`, `history` | 10/60秒 | 初回訪問者の挨拶。送信元の国や検索エンジンに基づいてカスタマイズ |
| `/chat/user` | POST | 公開 | `message`（必須）、`history`, `page_title`, `page_content`, `session_id` | 30/60秒 | 複数ターンのインタラクティブ会話（非ストリーミング）。MCP Tool/Abilitiesの呼び出しをサポート；`{msg, emoji}` を返す |
| `/chat/user-stream` | POST | 公開 | `/chat/user` と同じ | 30/60秒 | SSEストリーミングバージョン。プロバイダーがサポートしている場合、トークンごとに逐次出力 |

### タッチインタラクション系

| エンドポイント | メソッド | 権限 | パラメータ | レート制限 | 説明 |
| --- | --- | --- | --- | --- | --- |
| `/touch/decoration` | POST | 公開 | `decoration_type`（必須） | 20/60秒 | 装飾品をクリックした際のAIの反応；`{msg, emoji}` を返す |
| `/touch/zone` | POST | 公開 | `touch_zone`（必須） | 20/60秒 | キャラクターの身体領域をクリックした際の撫でる反応；`{msg, emoji, zone}` を返す |

### 管理画面テスト・管理系（管理者専用）

| エンドポイント | メソッド | 権限 | パラメータ | レート制限 | 説明 |
| --- | --- | --- | --- | --- | --- |
| `/test-connection/{provider}` | POST | 管理者 | `provider`（パスパラメータ：gemini / openai / claude / ollama / weather）、`api_key`, `model`, `endpoint`；weatherの場合はさらに `latitude`, `longitude` | 10/60秒 | 統合されたプロバイダー接続テストエンドポイント |
| `/clear-cache` | POST | 管理者 | — | 10/60秒 | LLM APIのレスポンスキャッシュをクリアする |

---

### `/chat/user-stream` SSE イベント形式

v2.12.x から、インタラクティブ会話は Server-Sent Events に対応しました。ストリームは `text/event-stream` 形式を使用して、固定順序でイベントを送信します。

| イベント | 発信タイミング | data 内容 |
| --- | --- | --- |
| `start` | ストリーム開始時 | `{"provider": "gemini", "model": "gemini-2.5-flash"}` |
| `nonce` | `start` の直後 | `{"new_token": "<nonce>", "new_nonce": "<nonce>"}` — 次回リクエスト用の新しい nonce を提供 |
| `delta` | AI がトークンを生成した時（複数回） | `{"text": "はい"}` — 単一のトークン／チャンク |
| `done` | ストリーム終了時 | `{"msg": "完全なメッセージ", "emoji": "smile"}` — 長さの切り詰めや絵文字解析を行った最終結果 |
| `error` | プロバイダーがストリーミングに非対応、または途中でエラーが発生 | `{"message": "<error_message>"}` |

**生ストリームの例**：

```
event: start
data: {"provider":"gemini","model":"gemini-2.5-flash"}

event: nonce
data: {"new_token":"a1b2c3","new_nonce":"a1b2c3"}

event: delta
data: {"text":"今日"}

event: delta
data: {"text":"はいい天気"}

event: delta
data: {"text":"ですね。"}

event: done
data: {"msg":"今日はいい天気ですね。","emoji":"happy"}
```

---

### 保持されている AJAX エンドポイント

少数の非会話系アクションは引き続き `admin-ajax.php` 経由で実行されており、主に内部統合に関連しています：

| アクション | ハンドラ | 説明 |
| --- | --- | --- |
| `wp_ajax_mpu_test_diary_generate` | `mpu_ajax_test_diary_generate` | 管理画面から日記生成テストを手動トリガー（管理者専用） |
| `wp_ajax_nopriv_slimtrack` / `wp_ajax_slimtrack` | `mpu_bb_intercept_slimstat` | Bot Blocker が Slimstat のトラッキングを傍受（priority 0） |
| `wp_ajax_nopriv_mbb_js_flag` / `wp_ajax_mbb_js_flag` | `mpu_bb_js_flag_handler` | Bot Blocker の JS 実行フラグの検出 |

> 📌 旧版（v2.9.x 以前）の `mpu_nextmsg`、`mpu_change`、`mpu_chat_*`、`mpu_test_*_connection`、`mpu_load_dialog`、`mpu_get_visitor_info`、`mpu_wake_ghost`、`mpu_init`、`mpu_get_settings`、`mpu_clear_api_cache`、`mpu_check_spam_event` などの AJAX action はすべて削除されました。上記の表に対応する REST エンドポイントをご利用ください。

## JavaScript 関数

### コア関数

#### mpu_nextmsg(trigger)

次のメッセージを表示します。

```javascript
/**
 * @param {string} trigger - 'next' 順番 / 'random' ランダム / '' 設定値を使用
 */
mpu_nextmsg("next");
```

---

#### mpu_hidemsg()

ダイアログボックスを非表示にします。

```javascript
mpu_hidemsg();
```

---

#### mpu_showmsg()

ダイアログボックスを表示します。

```javascript
mpu_showmsg();
```

---

#### mpu_hiderobot()

伺かを非表示にします。

```javascript
mpu_hiderobot();
```

---

#### mpu_showrobot()

伺かを表示します。

```javascript
mpu_showrobot();
```

---

#### mpuChange(num)

キャラクター切り替えメニューを開くか、パラメータがある場合は指定したキャラクターに直接切り替えます。

```javascript
/**
 * @param {string} [num] - ターゲットキャラクターのキー値；省略時はメニューを開く
 */
mpuChange();            // メニューを開く
mpuChange("default_2"); // 直接切り替える
```

---

### グローバル変数

フロントエンドの読み込み時に、`wp_localize_script` や `/init` エンドポイントが返すデータは、以下の `window` グローバルオブジェクトに書き込まれます。

| 変数 | ソース | 説明 |
| --- | --- | --- |
| `window.mpuRestUrl` | `wp_localize_script` | REST ベース URL（例：`/wp-json/mp-ukagaka/v1/`） |
| `window.mpuRestNonce` | `wp_localize_script` | REST リクエスト用 `X-WP-Nonce` |
| `window.mpuL10n` | `wp_localize_script` | フロントエンド表示用の翻訳文字列セット |
| `window.mpuSettings` | `/init` 戻り値 | キャラクターの動作設定オブジェクト（下記参照） |
| `window.mpuInitData` | `/init` 戻り値 | 完全な初期化応答の元オブジェクト |
| `window.mpuPersonalityId` | `/init` 戻り値 | 現在の人格ID |
| `window.mpuCanvasManager` | `ukagaka-anime.js` | Canvas アニメーションマネージャー |
| `window.mpuChatHistory` | `ukagaka-chat.js` | 複数ターン会話履歴の配列（最大 40 件） |
| `window.mpuChatModeActive` | `ukagaka-chat.js` | インタラクティブ会話モードのフラグ |
| `window.mpuDecorationsBaseUrl` / `mpuDecorationConfig` / `mpuTouchZones` / `mpuShowDecorations` | `/init` 戻り値 | 装飾およびタッチゾーン関連情報 |
| `window.mpuEmojiBaseUrl` / `mpuSupportedEmojis` / `mpuEmojiMappings` | `/init` 戻り値 | 絵文字システム関連データ |

#### window.mpuSettings

`/wp-json/mp-ukagaka/v1/init` が返す `settings` ブロックによって値が入ります。

```javascript
window.mpuSettings = {
  auto_talk: true,
  auto_talk_interval: 8,            // 秒
  typewriter_speed: 40,             // ミリ秒／文字
  ai_enabled: true,
  ai_probability: 10,               // 0-100、AIのトリガー確率
  ai_trigger_pages: "is_single",    // ページタイプの条件
  ai_text_color: "#000000",
  ai_display_duration: 8,           // 秒
  ai_greet_first_visit: true,
  ollama_replace_dialogue: false,   // 組み込み会話をLLMで置き換えるか
  enable_chat_mode: false,          // インタラクティブ会話モードを有効にするか
  sleep_mode: {
    enabled: false,
    frequency_multiplier: 1.0
  }
};
```

---

## 特殊コード

会話内容では以下の特殊コードを使用できます。これらは `mpu_msg_code()` によってサーバーサイドで処理された後、フロントエンドに送信されます。2つの形式をサポートしています：`:code[n]:` または `(:code[n]:)`（括弧内に含む）。

### :recentpost[n]: / :recentposts[n]:

最近の記事を n 件表示します。単数形は1行ずつリストアップし、複数形（`recentposts`）は `<br>` で連結します。

```
最近の記事：:recentpost[5]:
```

---

### :randompost[n]: / :randomposts[n]:

ランダムな記事を n 件表示します。単数形は1行ずつリストアップし、複数形は `<br>` で連結します。

```
おすすめの記事：:randompost[3]:
```

---

### :commenters[n]:

最近のユニークなコメント投稿者を n 名表示します（カンマや読点で区切り）。

```
コメントありがとうございます：:commenters[5]:
```

---

**📌 注意：** 上記は `mpu_msg_code()` が実際にサポートしているすべてのコードです。旧版のドキュメントで言及されていた `:date:`、`:time:`、`:sitename:` は**現在実装されていません**。このような変数の置換が必要な場合は、`mpu_render_prompt_template()` を使用して `{{variable}}` プレースホルダーを処理してください。

---

**ドキュメントバージョン：2.13.6**
