# MP Ukagaka API リファレンス

> 📚 関数、Hooks、AJAX エンドポイントの完全リファレンス

---

## 📑 目次

1. [PHP 関数](#php-関数)
2. [WordPress Hooks](#wordpress-hooks)
3. [AJAX エンドポイント](#ajax-エンドポイント)
4. [JavaScript 関数](#javascript-関数)
5. [特殊コード](#特殊コード)

---

## PHP 関数

### コア関数 (core-functions.php)

#### mpu_default_opt()

デフォルト設定値を取得。

```php
/**
 * @return array デフォルト設定配列
 */
function mpu_default_opt(): array
```

**例：**

```php
$defaults = mpu_default_opt();
echo $defaults['auto_talk_interval']; // 8
```

---

#### mpu_get_option()

プラグイン設定を取得（キャッシュ付き）。

```php
/**
 * @return array 設定配列
 */
function mpu_get_option(): array
```

**例：**

```php
$mpu_opt = mpu_get_option();
if ($mpu_opt['ai_enabled']) {
    // AI が有効
}
```

---

#### mpu_count_total_msg()

すべての伺かの総ダイアログ数を計算。

```php
/**
 * @return int 総ダイアログ数
 */
function mpu_count_total_msg(): int
```

---

### ユーティリティ関数 (utility-functions.php)

#### mpu_array2str()

配列を文字列に変換（改行で区切り）。

```php
/**
 * @param array $arr 入力配列
 * @return string 出力文字列
 */
function mpu_array2str(array $arr): string
```

**例：**

```php
$messages = ['ダイアログ1', 'ダイアログ2', 'ダイアログ3'];
$str = mpu_array2str($messages);
// 結果：
// ダイアログ1
//
// ダイアログ2
//
// ダイアログ3
```

---

#### mpu_str2array()

文字列を配列に変換（空行で区切り）。

```php
/**
 * @param string $str 入力文字列
 * @return array 出力配列
 */
function mpu_str2array(string $str): array
```

**例：**

```php
$str = "ダイアログ1\n\nダイアログ2\n\nダイアログ3";
$messages = mpu_str2array($str);
// 結果：['ダイアログ1', 'ダイアログ2', 'ダイアログ3']
```

---

#### mpu_secure_file_read()

安全なファイル読み込み。

```php
/**
 * @param string $file_path ファイルパス
 * @param int $max_size 最大ファイルサイズ（デフォルト 2MB）
 * @return string|WP_Error ファイル内容またはエラー
 */
function mpu_secure_file_read(string $file_path, int $max_size = 2097152)
```

**可能なエラー：**

| エラーコード       | 説明                                 |
| ------------------ | ------------------------------------ |
| `file_not_found`   | 指定されたファイルが見つからない     |
| `path_not_allowed` | そのパスの読み取りは許可されていない |
| `file_too_large`   | ファイルが大きすぎて読み取れない     |
| `read_failed`      | ファイルの読み取りに失敗             |

---

#### mpu_secure_file_write()

安全なファイル書き込み。

```php
/**
 * @param string $file_path ファイルパス
 * @param string $content ファイル内容
 * @return bool|WP_Error 成功またはエラー
 */
function mpu_secure_file_write(string $file_path, string $content)
```

---

#### mpu_encrypt_api_key()

AES-256-CBC で API Key を暗号化。

```php
/**
 * @param string $api_key 元の API Key
 * @return string 暗号化された文字列
 */
function mpu_encrypt_api_key(string $api_key): string
```

---

#### mpu_decrypt_api_key()

API Key を復号。

```php
/**
 * @param string $encrypted 暗号化された文字列
 * @return string 復号された API Key
 */
function mpu_decrypt_api_key(string $encrypted): string
```

---

### AI 関数 (ai-functions.php)

#### mpu_call_ai_api()

AI API を呼び出す（自動プロバイダー選択）。Gemini、OpenAI、Claude をサポート。

```php
/**
 * @param string $provider AI プロバイダー（'gemini'、'openai'、'claude'、'ollama'）
 * @param string $api_key API Key
 * @param string $system_prompt システムプロンプト（キャラクター設定）
 * @param string $user_prompt ユーザープロンプト
 * @param string $language 言語設定（'zh-TW'、'ja'、'en'）
 * @param array|null $mpu_opt プラグイン設定（モデル名取得用）
 * @param int|null $max_tokens 最大トークン数（オプション）
 * @return string|WP_Error AI 応答またはエラー
 */
function mpu_call_ai_api(
    string $provider,
    string $api_key,
    string $system_prompt,
    string $user_prompt,
    string $language = 'zh-TW',
    ?array $mpu_opt = null,
    ?int $max_tokens = null
)
```

**例：**

```php
$response = mpu_call_ai_api(
    'gemini',
    $api_key,
    'あなたはフレンドリーなアシスタント。回答は簡潔に。',
    'この記事は何について書いていますか？',
    'ja',
    $mpu_opt
);
if (!is_wp_error($response)) {
    echo $response;
}
```

---

#### mpu_get_language_instruction()

言語指示文字列を取得。

```php
/**
 * @param string $language 言語コード (zh-TW, ja, en)
 * @return string 言語指示
 */
function mpu_get_language_instruction(string $language): string
```

**戻り値：**

| 言語コード | 戻り値                       |
| ---------- | ---------------------------- |
| `zh-TW`    | `請用繁體中文回覆。`         |
| `ja`       | `日本語で返答してください。` |
| `en`       | `Please reply in English.`   |

---

#### mpu_call_gemini_api()

Google Gemini API を呼び出す。

```php
/**
 * @param string $api_key Gemini API Key
 * @param string $model モデル名（例：'gemini-2.5-flash'）
 * @param string $system_prompt システムプロンプト
 * @param string $user_prompt ユーザープロンプト
 * @param string $language 言語設定
 * @param int|null $max_tokens 最大トークン数（オプション）
 * @return string|WP_Error AI 応答またはエラー
 */
function mpu_call_gemini_api(
    string $api_key,
    string $model,
    string $system_prompt,
    string $user_prompt,
    string $language = 'zh-TW',
    ?int $max_tokens = null
)
```

---

#### mpu_call_openai_api()

OpenAI API を呼び出す。

```php
/**
 * @param string $api_key OpenAI API Key
 * @param string $model モデル名（例：'gpt-4o-mini'）
 * @param string $system_prompt システムプロンプト
 * @param string $user_prompt ユーザープロンプト
 * @param string $language 言語設定
 * @param int|null $max_tokens 最大トークン数（オプション）
 * @return string|WP_Error AI 応答またはエラー
 */
function mpu_call_openai_api(
    string $api_key,
    string $model,
    string $system_prompt,
    string $user_prompt,
    string $language = 'zh-TW',
    ?int $max_tokens = null
)
```

---

#### mpu_call_claude_api()

Anthropic Claude API を呼び出す。

```php
/**
 * @param string $api_key Claude API Key
 * @param string $model モデル名（例：'claude-sonnet-4-5-20250929'）
 * @param string $system_prompt システムプロンプト
 * @param string $user_prompt ユーザープロンプト
 * @param string $language 言語設定
 * @param int|null $max_tokens 最大トークン数（オプション）
 * @return string|WP_Error AI 応答またはエラー
 */
function mpu_call_claude_api(
    string $api_key,
    string $model,
    string $system_prompt,
    string $user_prompt,
    string $language = 'zh-TW',
    ?int $max_tokens = null
)
```

---

#### mpu_call_ollama_api()

Ollama API を呼び出す（ローカルまたはリモート）。

```php
/**
 * @param string $endpoint Ollama エンドポイント URL
 * @param string $model モデル名（例：'qwen3:8b'）
 * @param string $system_prompt システムプロンプト
 * @param string $user_prompt ユーザープロンプト
 * @param string $language 言語設定
 * @param int|null $max_tokens 最大トークン数（オプション）
 * @return string|WP_Error AI 応答またはエラー
 */
function mpu_call_ollama_api(
    string $endpoint,
    string $model,
    string $system_prompt,
    string $user_prompt,
    string $language = 'zh-TW',
    ?int $max_tokens = null
)
```

**機能特徴：**

- ローカル/リモート接続を自動検出
- 接続タイプに応じてタイムアウトを調整
- 思考モードを無効化サポート（Qwen3、DeepSeek などのモデル）

---

### API キャッシュ関数 (api-cache.php)

> 💡 **v2.5.6 新規**：API キャッシュシステム。WordPress Transient API を使用して AI API 応答をキャッシュし、重複リクエストとコストを削減。

#### mpu_is_api_cache_enabled()

API キャッシュが有効か確認。

```php
/**
 * @return bool
 */
function mpu_is_api_cache_enabled(): bool
```

---

#### mpu_get_api_cache_ttl()

キャッシュ TTL（秒）を取得。

```php
/**
 * @return int デフォルト 3600 秒（1 時間）、範囲 300-86400 秒
 */
function mpu_get_api_cache_ttl(): int
```

---

#### mpu_generate_cache_key()

キャッシュキーを生成。

```php
/**
 * @param string $provider プロバイダー
 * @param string $system_prompt システムプロンプト
 * @param string $user_prompt ユーザープロンプト
 * @return string キャッシュキー
 */
function mpu_generate_cache_key(string $provider, string $system_prompt, string $user_prompt): string
```

---

#### mpu_get_cached_api_response()

キャッシュから API 応答を取得。

```php
/**
 * @param string $cache_key キャッシュキー
 * @return string|false キャッシュされた応答または false
 */
function mpu_get_cached_api_response(string $cache_key)
```

---

#### mpu_set_cached_api_response()

API 応答をキャッシュに保存。

```php
/**
 * @param string $cache_key キャッシュキー
 * @param string $response API 応答
 * @return bool
 */
function mpu_set_cached_api_response(string $cache_key, string $response): bool
```

---

#### mpu_clear_all_api_cache()

すべての LLM API キャッシュをクリア。

```php
/**
 * @return int クリアされたキャッシュ数
 */
function mpu_clear_all_api_cache(): int
```

---

#### mpu_get_api_cache_stats()

API キャッシュ統計を取得。

```php
/**
 * @return array ['count' => int, 'ttl' => int, 'enabled' => bool]
 */
function mpu_get_api_cache_stats(): array
```

---

### LLM 機能関数 (llm-functions.php)

> 💡 **2.2.0 更新**：LLM 機能が**汎用 LLM インターフェース**にアップグレードされ、Ollama、Gemini、OpenAI、Claude の 4 大 AI サービスをサポート。

#### mpu_is_remote_endpoint()

エンドポイントがリモート接続かどうかを検出。

```php
/**
 * @param string $endpoint Ollama エンドポイント URL
 * @return bool リモート接続かどうか（true = リモート、false = ローカル）
 */
function mpu_is_remote_endpoint(string $endpoint): bool
```

**例：**

```php
$is_remote = mpu_is_remote_endpoint('https://your-domain.com'); // true
$is_local = mpu_is_remote_endpoint('http://localhost:11434');  // false
```

---

#### mpu_get_ollama_timeout()

エンドポイントタイプと操作タイプに基づいて適切なタイムアウトを取得。

```php
/**
 * @param string $endpoint Ollama エンドポイント URL
 * @param string $operation_type 操作タイプ：'check'、'api_call'、'test'
 * @return int タイムアウト（秒）
 */
function mpu_get_ollama_timeout(string $endpoint, string $operation_type = 'api_call'): int
```

---

#### mpu_check_ollama_available()

Ollama サービスが利用可能かどうかを確認（クイックチェック、キャッシュ使用）。

```php
/**
 * @param string $endpoint Ollama エンドポイント
 * @param string $model モデル名
 * @return bool サービスが利用可能か
 */
function mpu_check_ollama_available(string $endpoint, string $model): bool
```

---

#### mpu_generate_llm_dialogue()

LLM を使用してランダムダイアログを生成（内蔵ダイアログを置換）。すべての AI プロバイダーをサポート。

```php
/**
 * @param string $ukagaka_name 伺か名
 * @param string $last_response 前回の AI 応答（重複ダイアログ回避用）
 * @param array $response_history 応答履歴配列
 * @param int $last_visit_hours 最後の訪問からの時間（デフォルト -1 はデータなし、v2.5.6 新規）
 * @return string|false 生成されたダイアログ内容、失敗時は false
 */
function mpu_generate_llm_dialogue(
    string $ukagaka_name = 'default_1',
    string $last_response = '',
    array $response_history = [],
    int $last_visit_hours = -1
)
```

**機能特徴：**

- 最適化された XML 構造化 System Prompt を自動使用
- 重複ダイアログ防止機構をサポート（類似度検出）
- WordPress 情報、ユーザー情報、訪問者情報を自動統合
- 70+ のフリーレン風ダイアログ例をサポート

**利用可能な Filter Hooks（v2.5.7）：**

| Filter                  | 説明                                 | パラメータ                                                |
| ----------------------- | ------------------------------------ | --------------------------------------------------------- |
| `mpu_llm_system_prompt` | System Prompt を変更                 | `$prompt`, `$ukagaka_name`, `$personality_id`, `$context` |
| `mpu_llm_user_prompt`   | 会話指示の前に追加コンテキストを注入 | `$prompt`, `$ukagaka_name`, `$personality_id`             |

**使用例（セキュリティアラート統合）：**

```php
add_filter('mpu_llm_user_prompt', function($prompt, $ukagaka_name, $personality_id) {
    $attack_info = get_transient('mpu_llar_attack_info');
    if ($attack_info) {
        return $prompt . "\n【セキュリティアラート】\n" . $attack_info;
    }
    return $prompt;
}, 10, 3);
```

---

#### mpu_is_llm_replace_dialogue_enabled()

LLM で内蔵ダイアログを置換するかどうかを確認。

```php
/**
 * @return bool
 */
function mpu_is_llm_replace_dialogue_enabled(): bool
```

---

#### mpu_get_visitor_info_for_llm()

訪問者情報を取得（LLM ダイアログ生成用）。Slimstat データを統合、BOT 検出と地理情報を含む。

```php
/**
 * @return array 訪問者情報配列
 */
function mpu_get_visitor_info_for_llm(): array
```

**戻り値：**

```php
[
    'is_bot' => false,                    // BOT かどうか
    'browser_type' => 0,                  // ブラウザタイプ（0=一般, 1=BOT, 2=モバイル）
    'browser_name' => 'Chrome',            // ブラウザ名（BOT 名）
    'slimstat_enabled' => true,            // Slimstat が有効か
    'slimstat_country' => 'TW',            // 国コード
    'slimstat_city' => 'Taipei',           // 都市名
]
```

---

#### mpu_build_optimized_system_prompt()

最適化された System Prompt を構築（XML 構造化バージョン）。

```php
/**
 * @param array $mpu_opt プラグイン設定
 * @param array $wp_info WordPress 情報
 * @param array $user_info ユーザー情報
 * @param array $visitor_info 訪問者情報
 * @param string $ukagaka_name 伺か名
 * @param string $time_context 時間コンテキスト
 * @param string $language 言語設定
 * @return string 最適化された system prompt
 */
function mpu_build_optimized_system_prompt(
    array $mpu_opt,
    array $wp_info,
    array $user_info,
    array $visitor_info,
    string $ukagaka_name,
    string $time_context,
    string $language
): string
```

---

### 伺か関数 (ukagaka-functions.php)

#### mpu_get_ukagaka()

伺かデータを取得。

```php
/**
 * @param string|false $num 伺かキー（false で現在の伺か）
 * @return array|false 伺かデータまたは false
 */
function mpu_get_ukagaka($num = false)
```

---

#### mpu_get_shell()

伺か画像 URL を取得。

```php
/**
 * @param string|false $num 伺かキー
 * @param bool $echo 直接出力するか
 * @return string 画像 URL
 */
function mpu_get_shell($num = false, $echo = false): string
```

---

#### mpu_get_msg()

指定メッセージを取得。

```php
/**
 * @param int $msgnum メッセージインデックス
 * @param string|false $num 伺かキー
 * @param bool $echo 直接出力するか
 * @return string メッセージ内容
 */
function mpu_get_msg($msgnum = 0, $num = false, $echo = false): string
```

---

#### mpu_get_random_msg()

ランダムメッセージを取得。

```php
/**
 * @param string|false $num 伺かキー
 * @param bool $echo 直接出力するか
 * @return string メッセージ内容
 */
function mpu_get_random_msg($num = false, $echo = false): string
```

---

## WordPress Hooks

### Actions

| Hook                 | 説明                 | パラメータ |
| -------------------- | -------------------- | ---------- |
| `mpu_loaded`         | プラグイン読み込み後 | なし       |
| `mpu_before_html`    | 伺か HTML 生成前     | なし       |
| `mpu_after_html`     | 伺か HTML 生成後     | なし       |
| `mpu_settings_saved` | 設定保存後           | `$mpu_opt` |

### Filters

| Filter                  | 説明                                         | パラメータ                                                    |
| ----------------------- | -------------------------------------------- | ------------------------------------------------------------- |
| `mpu_options`           | 設定配列をフィルター                         | `$mpu_opt`                                                    |
| `mpu_html`              | 伺か HTML をフィルター                       | `$html`, `$ukagaka`                                           |
| `mpu_message`           | メッセージをフィルター                       | `$message`, `$ukagaka_key`                                    |
| `mpu_ai_response`       | AI 応答をフィルター                          | `$response`, `$provider`                                      |
| `mpu_llm_system_prompt` | LLMシステムプロンプトをフィルター            | `$prompt`, `$ukagaka_name`, `$personality_id`, `$context`     |
| `mpu_llm_user_prompt`   | ユーザーチャットプロンプトをフィルター       | `$prompt`, `$ukagaka_name`, `$personality_id`                 |
| `mpu_prompt_categories` | ダイアログカテゴリ定義をフィルター           | `$categories`, `$wp_info`, `$visitor_info`, `$time_context`   |
| `mpu_category_weights`  | ダイアログカテゴリのランダム重みをフィルター | `$weights`, `$time_context`, `$visitor_info`, `$context_vars` |

---

## AJAX エンドポイント

### 公開エンドポイント

#### mpu_nextmsg

次のメッセージを取得。

**パラメータ：**

| パラメータ      | タイプ | 説明                         |
| --------------- | ------ | ---------------------------- |
| `cur_num`       | string | 現在の伺かキー               |
| `cur_msgnum`    | int    | 現在のメッセージインデックス |
| `last_response` | string | (オプション) 前回の LLM 応答 |

**戻り値：**

```json
{
  "success": true,
  "data": {
    "msg": "メッセージ内容",
    "msgnum": 1,
    "is_llm": false
  }
}
```

---

#### mpu_change

伺かを切り替え。

**パラメータ：**

| パラメータ | タイプ | 説明           |
| ---------- | ------ | -------------- |
| `new_num`  | string | 新しい伺かキー |

**戻り値：**

```json
{
    "success": true,
    "data": {
        "name": "フリーレン",
        "shell_info": { ... },
        "msg": "最初のメッセージ",
        "msgnum": 0
    }
}
```

---

#### mpu_chat_context

AI ページ感知ダイアログを取得。

**パラメータ：**

| パラメータ     | タイプ | 説明         |
| -------------- | ------ | ------------ |
| `post_content` | string | 記事内容     |
| `post_title`   | string | 記事タイトル |

**戻り値：**

```json
{
  "success": true,
  "data": {
    "response": "AI が生成したコメント"
  }
}
```

---

#### mpu_chat_greet

初回訪問者挨拶を取得。

**パラメータ：**

| パラメータ     | タイプ | 説明       |
| -------------- | ------ | ---------- |
| `visitor_info` | object | 訪問者情報 |

**戻り値：**

```json
{
  "success": true,
  "data": {
    "response": "AI が生成した挨拶"
  }
}
```

---

### mpu_get_settings

フロントエンド設定を取得。

**Action:** `mpu_get_settings`

**成功応答：**

```json
{
  "success": true,
  "data": {
    "autoTalk": true,
    "autoTalkInterval": 8000,
    "typewriterSpeed": 40,
    "clickBehavior": 0
  }
}
```

---

### mpu_test_ollama_connection

Ollama 接続をテスト。

**Action:** `mpu_test_ollama_connection`

**リクエストパラメータ：**

| パラメータ | タイプ | 説明                      |
| ---------- | ------ | ------------------------- |
| `endpoint` | string | Ollama エンドポイント URL |
| `model`    | string | モデル名                  |
| `nonce`    | string | WordPress nonce           |

**リクエスト例：**

```javascript
{
    action: 'mpu_test_ollama_connection',
    endpoint: 'https://your-domain.com',
    model: 'qwen3:8b',
    nonce: '...'
}
```

**成功応答：**

```json
{
  "success": true,
  "data": "接続成功（リモート接続）、モデル応答正常（プレビュー：Hello...）"
}
```

**失敗応答：**

```json
{
  "success": false,
  "data": "接続失敗：リモート Ollama サービスに接続できません..."
}
```

---

### mpu_test_gemini_connection

Google Gemini API 接続をテスト。

**Action:** `mpu_test_gemini_connection`

**リクエストパラメータ：**

| パラメータ | タイプ | 説明                                                         |
| ---------- | ------ | ------------------------------------------------------------ |
| `api_key`  | string | Gemini API Key（オプション、未提供の場合は設定から読み込み） |
| `model`    | string | モデル名（オプション、未提供の場合は設定から読み込み）       |
| `nonce`    | string | WordPress nonce                                              |

**成功応答：**

```json
{
  "success": true,
  "data": "接続成功、API Key 有効"
}
```

**失敗応答：**

```json
{
  "success": false,
  "data": "接続失敗：API Key 無効またはネットワークエラー"
}
```

---

### mpu_test_openai_connection

OpenAI API 接続をテスト。

**Action:** `mpu_test_openai_connection`

**リクエストパラメータ：**

| パラメータ | タイプ | 説明                                                         |
| ---------- | ------ | ------------------------------------------------------------ |
| `api_key`  | string | OpenAI API Key（オプション、未提供の場合は設定から読み込み） |
| `model`    | string | モデル名（オプション、未提供の場合は設定から読み込み）       |
| `nonce`    | string | WordPress nonce                                              |

**成功応答：**

```json
{
  "success": true,
  "data": "接続成功、API Key 有効"
}
```

**失敗応答：**

```json
{
  "success": false,
  "data": "接続失敗：API Key 無効またはネットワークエラー"
}
```

---

### mpu_test_claude_connection

Claude (Anthropic) API 接続をテスト。

**Action:** `mpu_test_claude_connection`

**リクエストパラメータ：**

| パラメータ | タイプ | 説明                                                         |
| ---------- | ------ | ------------------------------------------------------------ |
| `api_key`  | string | Claude API Key（オプション、未提供の場合は設定から読み込み） |
| `model`    | string | モデル名（オプション、未提供の場合は設定から読み込み）       |
| `nonce`    | string | WordPress nonce                                              |

**成功応答：**

```json
{
  "success": true,
  "data": "接続成功、API Key 有効"
}
```

**失敗応答：**

```json
{
  "success": false,
  "data": "接続失敗：API Key 無効またはネットワークエラー"
}
```

---

### mpu_load_dialog

外部ダイアログファイルを読み込み。

**Action:** `mpu_load_dialog`

**リクエストパラメータ：**

| パラメータ | タイプ | 説明                |
| ---------- | ------ | ------------------- |
| `filename` | string | ファイル名          |
| `format`   | string | `txt` または `json` |

**成功応答：**

```json
{
  "success": true,
  "data": {
    "messages": ["ダイアログ1", "ダイアログ2", "ダイアログ3"]
  }
}
```

---

### mpu_ai_context_chat

AI ページ感知ダイアログ。

**Action:** `mpu_ai_context_chat`

**リクエストパラメータ：**

| パラメータ | タイプ | 説明                   |
| ---------- | ------ | ---------------------- |
| `title`    | string | 記事タイトル           |
| `content`  | string | 記事内容               |
| `nonce`    | string | セキュリティ認証コード |

**成功応答：**

```json
{
  "success": true,
  "data": {
    "message": "AI が生成したコメント"
  }
}
```

---

### mpu_get_visitor_info

訪問者情報を取得（Slimstat が必要）。

**Action:** `mpu_get_visitor_info`

**リクエストパラメータ：**

| パラメータ | タイプ | 説明                   |
| ---------- | ------ | ---------------------- |
| `nonce`    | string | セキュリティ認証コード |

**成功応答：**

```json
{
  "success": true,
  "data": {
    "country": "JP",
    "city": "Tokyo",
    "referer": "https://google.com",
    "searchterms": "検索キーワード",
    "browser": "Chrome",
    "platform": "Windows"
  }
}
```

---

### mpu_ai_greet

AI 初回訪問者挨拶。

**Action:** `mpu_ai_greet`

**リクエストパラメータ：**

| パラメータ     | タイプ | 説明                   |
| -------------- | ------ | ---------------------- |
| `visitor_info` | object | 訪問者情報             |
| `nonce`        | string | セキュリティ認証コード |

**成功応答：**

```json
{
  "success": true,
  "data": {
    "message": "日本からの友達、ようこそ！"
  }
}
```

---

### mpu_user_chat (v2.3.0)

ユーザーインタラクティブチャットリクエスト。インタラクティブチャットモードでのユーザー入力を処理。

**Action:** `mpu_user_chat`

**リクエストパラメータ：**

| パラメータ | タイプ | 説明                         |
| ---------- | ------ | ---------------------------- |
| `message`  | string | ユーザーが入力したメッセージ |
| `history`  | array  | 会話履歴配列                 |
| `nonce`    | string | セキュリティ認証コード       |

**会話履歴形式：**

```json
[
  { "role": "user", "content": "こんにちは" },
  { "role": "assistant", "content": "こんにちは！何か話したいことある？" }
]
```

**成功応答：**

```json
{
  "success": true,
  "data": {
    "message": "AI が生成した応答"
  }
}
```

---

### mpu_decoration_chat (v2.3.0)

装飾物クリックダイアログリクエスト。ユーザーがキャラクターの装飾物をクリックした際に関連するダイアログを生成。

**Action:** `mpu_decoration_chat`

**リクエストパラメータ：**

| パラメータ        | タイプ | 説明                                               |
| ----------------- | ------ | -------------------------------------------------- |
| `decoration_type` | string | 装飾物タイプ（suitcase, evil_horns, staff, books） |
| `nonce`           | string | セキュリティ認証コード                             |

**成功応答：**

```json
{
  "success": true,
  "data": {
    "message": "このスーツケースについて...中には集めた魔法が入っている。"
  }
}
```

---

### mpu_touch_zone_chat (v2.3.0)

キャラクタータッチ領域クリックリクエスト。タッチ反応ダイアログを生成。

**Action:** `mpu_touch_zone_chat`

---

### mpu_check_spam_event

Akismet スパムコメントブロックイベント通知。

**Action:** `mpu_check_spam_event`

---

### mpu_wake_ghost

手動で伺かを起こす。

**Action:** `mpu_wake_ghost`

---

### mpu_init

フロントエンド伺か初期化に必要なリソース。

**Action:** `mpu_init`

---

### mpu_clear_api_cache (Admin)

LLM API キャッシュをすべてクリア。

**Action:** `mpu_clear_api_cache`

---

### mpu_test_weather_api (Admin)

天気 API 接続をテスト。

**Action:** `mpu_test_weather_api`

---

## JavaScript 関数

### グローバルオブジェクト

```javascript
// 設定オブジェクト
window.mpuConfig = {
  ajaxUrl: "/wp-admin/admin-ajax.php",
  nonce: "xxx",
  currentUkagaka: "default_1",
  autoTalkInterval: 8000,
  typewriterSpeed: 40,
};

// Canvas マネージャー
window.mpuCanvasManager = {
  init(shellInfo, name) {},
  playAnimation() {},
  stopAnimation() {},
  isAnimationMode() {},
};
```

### コア関数

#### mpu_nextmsg(mode)

次のメッセージを表示。

```javascript
/**
 * @param {string} mode - 'next' 順序 / 'random' ランダム / '' 設定値を使用
 */
mpu_nextmsg("next");
```

---

#### mpu_hidemsg()

ダイアログボックスを非表示。

```javascript
mpu_hidemsg();
```

---

#### mpu_showmsg()

ダイアログボックスを表示。

```javascript
mpu_showmsg();
```

---

#### mpu_hideukagaka()

伺かを非表示。

```javascript
mpu_hideukagaka();
```

---

#### mpu_showukagaka()

伺かを表示。

```javascript
mpu_showukagaka();
```

---

#### mpuChange()

伺か切り替えメニューを開く。

```javascript
mpuChange();
```

---

#### mpu_showMessage(message, options)

指定したメッセージを表示（タイプライター効果付き）。

```javascript
/**
 * @param {string} message - メッセージ内容
 * @param {object} options - オプション
 * @param {string} options.color - テキスト色
 * @param {boolean} options.typewriter - タイプライター効果を使用するか
 */
mpu_showMessage("ようこそ！", {
  color: "#ff6b6b",
  typewriter: true,
});
```

---

### AI 機能関数

#### mpu_triggerAIContext()

AI ページ感知をトリガー。

```javascript
mpu_triggerAIContext();
```

---

#### mpu_triggerAIGreeting()

AI 初回訪問者挨拶をトリガー。

```javascript
mpu_triggerAIGreeting();
```

---

#### mpu_pauseAutoTalk(duration)

自動対話を一時停止。

```javascript
/**
 * @param {number} duration - 一時停止時間（ミリ秒）
 */
mpu_pauseAutoTalk(10000); // 10 秒間一時停止
```

---

### グローバル設定オブジェクト

```javascript
window.mpuSettings = {
  ajaxUrl: "/wp-admin/admin-ajax.php",
  nonce: "xxx",
  autoTalk: true,
  autoTalkInterval: 8000, // ミリ秒
  typewriterSpeed: 40, // ミリ秒/文字
  clickBehavior: 0, // 0=次へ, 1=操作なし
  nextMode: 0, // 0=順序, 1=ランダム
  aiEnabled: true,
  aiTextColor: "#ff6b6b",
  aiDisplayDuration: 8000, // ミリ秒
  aiGreetEnabled: true,
  useExternalFile: false,
  externalFileFormat: "txt",
};
```

---

## 特殊コード

ダイアログ内容で使用できる特殊コード：

### :recentpost[n]:

最近の n 記事一覧を表示。

```
最近の記事：:recentpost[5]:
```

---

### :randompost[n]:

ランダムな n 記事一覧を表示。

```
おすすめ：:randompost[3]:
```

---

### :commenters[n]:

最近の n 人のコメント者を表示。

```
コメントありがとう：:commenters[5]:
```

---

### 📅

今日の日付を表示。

```
今日は :date:
```

---

### :time:

現在の時刻を表示。

```
現在の時刻は :time:
```

---

### :sitename:

サイト名を表示。

```
:sitename: へようこそ！
```

---

**📌 注意：** 特殊コードはサーバー側で処理され、実際の内容に変換されてからフロントエンドに送信されます。

---

**ドキュメントバージョン：2.5.6**
