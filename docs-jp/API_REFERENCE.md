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

| エラーコード | 説明 |
|---------|------|
| `file_not_found` | 指定されたファイルが見つからない |
| `path_not_allowed` | そのパスの読み取りは許可されていない |
| `file_too_large` | ファイルが大きすぎて読み取れない |
| `read_failed` | ファイルの読み取りに失敗 |

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
 * @param string $provider AI プロバイダー（'gemini'、'openai'、'claude'）
 * @param string $api_key API Key
 * @param string $system_prompt システムプロンプト（キャラクター設定）
 * @param string $user_prompt ユーザープロンプト
 * @param string $language 言語設定（'zh-TW'、'ja'、'en'）
 * @param array $mpu_opt プラグイン設定（モデル名取得用）
 * @return string|WP_Error AI 応答またはエラー
 */
function mpu_call_ai_api(
    string $provider,
    string $api_key,
    string $system_prompt,
    string $user_prompt,
    string $language = 'zh-TW',
    array $mpu_opt = []
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

#### mpu_should_trigger_ai()

AI をトリガーするかどうかを確認。

```php
/**
 * @return bool トリガーするか
 */
function mpu_should_trigger_ai(): bool
```

確認条件：

- AI が有効か
- API Key が設定されているか
- 現在のページがトリガー条件に合っているか
- 確率チェック

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

| 言語コード | 戻り値 |
|---------|--------|
| `zh-TW` | `請用繁體中文回覆。` |
| `ja` | `日本語で返答してください。` |
| `en` | `Please reply in English.` |

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
 * @return string|WP_Error AI 応答またはエラー
 */
function mpu_call_gemini_api(
    string $api_key,
    string $model,
    string $system_prompt,
    string $user_prompt,
    string $language = 'zh-TW'
)
```

---

#### mpu_call_openai_api()

OpenAI API を呼び出す。

```php
/**
 * @param string $api_key OpenAI API Key
 * @param string $model モデル名（例：'gpt-4.1-mini-2025-04-14'）
 * @param string $system_prompt システムプロンプト
 * @param string $user_prompt ユーザープロンプト
 * @param string $language 言語設定
 * @return string|WP_Error AI 応答またはエラー
 */
function mpu_call_openai_api(
    string $api_key,
    string $model,
    string $system_prompt,
    string $user_prompt,
    string $language = 'zh-TW'
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
 * @return string|WP_Error AI 応答またはエラー
 */
function mpu_call_claude_api(
    string $api_key,
    string $model,
    string $system_prompt,
    string $user_prompt,
    string $language = 'zh-TW'
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
 * @return string|WP_Error AI 応答またはエラー
 */
function mpu_call_ollama_api(
    string $endpoint,
    string $model,
    string $system_prompt,
    string $user_prompt,
    string $language = 'zh-TW'
)
```

**機能特徴：**

- ローカル/リモート接続を自動検出
- 接続タイプに応じてタイムアウトを調整
- 思考モードを無効化サポート（Qwen3、DeepSeek などのモデル）

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
 * @return string|false 生成されたダイアログ内容、失敗時は false
 */
function mpu_generate_llm_dialogue(
    string $ukagaka_name = 'default_1',
    string $last_response = '',
    array $response_history = []
)
```

**機能特徴：**

- 最適化された XML 構造化 System Prompt を自動使用
- 重複ダイアログ防止機構をサポート（類似度検出）
- WordPress 情報、ユーザー情報、訪問者情報を自動統合
- 70+ のフリーレン風ダイアログ例をサポート

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

#### mpu_ukagaka_list()

伺かリスト HTML を取得。

```php
/**
 * @return string HTML 文字列
 */
function mpu_ukagaka_list(): string
```

---

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

| Hook | 説明 | パラメータ |
|------|------|----------|
| `mpu_loaded` | プラグイン読み込み後 | なし |
| `mpu_before_html` | 伺か HTML 生成前 | なし |
| `mpu_after_html` | 伺か HTML 生成後 | なし |
| `mpu_settings_saved` | 設定保存後 | `$mpu_opt` |

### Filters

| Filter | 説明 | パラメータ |
|--------|------|----------|
| `mpu_options` | 設定配列をフィルター | `$mpu_opt` |
| `mpu_html` | 伺か HTML をフィルター | `$html`, `$ukagaka` |
| `mpu_message` | メッセージをフィルター | `$message`, `$ukagaka_key` |
| `mpu_ai_response` | AI 応答をフィルター | `$response`, `$provider` |

---

## AJAX エンドポイント

### 公開エンドポイント

#### mpu_nextmsg

次のメッセージを取得。

**パラメータ：**

| パラメータ | タイプ | 説明 |
|-----------|--------|------|
| `cur_num` | string | 現在の伺かキー |
| `cur_msgnum` | int | 現在のメッセージインデックス |
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

| パラメータ | タイプ | 説明 |
|-----------|--------|------|
| `new_num` | string | 新しい伺かキー |

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

| パラメータ | タイプ | 説明 |
|-----------|--------|------|
| `post_content` | string | 記事内容 |
| `post_title` | string | 記事タイトル |

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

| パラメータ | タイプ | 説明 |
|-----------|--------|------|
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

## JavaScript 関数

### グローバルオブジェクト

```javascript
// 設定オブジェクト
window.mpuConfig = {
    ajaxUrl: '/wp-admin/admin-ajax.php',
    nonce: 'xxx',
    currentUkagaka: 'default_1',
    autoTalkInterval: 8000,
    typewriterSpeed: 40
};

// Canvas マネージャー
window.mpuCanvasManager = {
    init(shellInfo, name) {},
    playAnimation() {},
    stopAnimation() {},
    isAnimationMode() {}
};
```

### 主要関数

#### mpu_typewriter()

タイプライター効果でメッセージを表示。

```javascript
/**
 * @param {string} message - 表示するメッセージ
 * @param {HTMLElement} element - ターゲット要素
 * @param {number} speed - タイプ速度（ミリ秒/文字）
 */
function mpu_typewriter(message, element, speed)
```

---

#### mpuChange()

伺かを切り替え。

```javascript
/**
 * @param {string} newUkagakaKey - 新しい伺かキー
 */
function mpuChange(newUkagakaKey)
```

---

#### mpuNextMsg()

次のメッセージを取得して表示。

```javascript
/**
 * @returns {Promise}
 */
function mpuNextMsg()
```

---

## 特殊コード

ダイアログ内で使用できる特殊コード：

| コード | 説明 | 例 |
|--------|------|-----|
| `:recentpost[N]:` | 最近の N 記事を表示 | `:recentpost[5]:` |
| `:randompost[N]:` | ランダムな N 記事を表示 | `:randompost[3]:` |
| `:commenters[N]:` | 最近の N 人のコメント者を表示 | `:commenters[5]:` |

**使用例：**

```
最近の記事を見てみる？
:recentpost[3]:
```

---

**Made with ❤ for WordPress**
