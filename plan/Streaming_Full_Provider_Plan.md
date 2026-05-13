# Streaming 完整 Provider 支援計畫

> 本文件已整合 CODEX Review 所有修正意見，可直接照此實作。

---

## 背景與現況

SSE 串流架構已在 v2.12.x 完成基礎建設：

- `/chat/user-stream` 端點存在（`class-mpu-rest-chat.php`）
- `generate_chat_stream()` 介面已定義（`interface-mpu-ai-provider.php`）
- `mpu_stream_api_request()` 低階 cURL 串流 client 存在（`provider-stream-http.php`）
- OpenAI / Ollama 已有完整 streaming 實作（含 tool call）
- **Claude / Gemini** 的 `generate_chat_stream()` 目前只回傳 `unsupported` 錯誤
- **Claude / Gemini** 的 `supports()` 未宣告 `FEATURE_STREAMING`

### 前端現況問題

`onDelta` 目前只累積文字，`onDone` 才呼叫 `mpu_typewriter()`，等同於完全沒有利用 streaming 的視覺優勢。OpenAI / Ollama 的串流已無法讓使用者看到漸進顯示。

---

## 確立方向

### 實作順序

1. **前端 streaming typewriter queue**（立即改善 OpenAI / Ollama）
2. **SSE event parser helper**（`mpu_stream_sse_events()`，加在 `provider-stream-http.php`）
3. **Gemini 純文字 streaming**（第一版不傳 tools）
4. **Claude 純文字 streaming**
5. **Claude tool call streaming**（完整保存 assistant content blocks）
6. **Gemini tool call**：暫緩，僅做明確同步 fallback

### 關鍵決策

| 議題 | 決定 |
|---|---|
| 前端 timer | streaming queue 使用**區域** `streamTypewriterTimer`，絕不碰全域 `mpuTypewriterTimer` |
| Queue chunkSize | `Math.min(4, Math.max(1, Math.floor(streamPendingText.length / 80)))` |
| Gemini 第一版 | request body **不帶 tools**；無需任何 fallback 邏輯 |
| Gemini tool call | 日後若要支援，在 `generate_chat_stream()` 內部呼叫 `$this->generate_chat()` 再 emit 單一 delta |
| SSE parser | 所有 provider 統一用 `mpu_stream_sse_events()` helper，不各自手寫 line parser |
| Claude pre-tool text | 帶 tools 後，若 pre-tool text 不在最終回應內，就**不要 emit 成 delta** |
| Checksum（純文字） | 不需修改 |
| Checksum（tool call） | 必須確保：delta 累積值 = `done.msg` = 寫入 checksum 的 `$result`，三者一致 |

---

## 步驟一：前端 Streaming Typewriter Queue

**檔案**：`js/ukagaka-chat.js`

### 說明

引入區域 queue，`onDelta` 把文字推進去，timer 依照 `mpuTypewriterSpeed` 慢慢 drain 到 DOM。`onDone` 等 queue 清空後才儲存 history、顯示 emoji、解鎖輸入框。

**所有 timer 變數使用區域 `streamTypewriterTimer`，絕對不碰全域 `mpuTypewriterTimer`。**

### 實作

```js
// if (useStreaming) { 區塊內初始化
let fullResponse = "";
let streamTypewriterTimer = null;  // 區域，不共用 mpuTypewriterTimer
let streamDisplayedText = "";      // 已顯示的純文字（累積）
let streamPendingText = "";        // 待顯示佇列
let streamDone = false;
let streamDoneData = null;

function streamStartDrain() {
  if (streamTypewriterTimer !== null) return;
  streamTickDrain();
}

function streamTickDrain() {
  if (streamPendingText.length === 0) {
    streamTypewriterTimer = null;
    if (streamDone) streamFinalize(streamDoneData);
    return;
  }
  const chunkSize = Math.min(4, Math.max(1, Math.floor(streamPendingText.length / 80)));
  streamDisplayedText += streamPendingText.slice(0, chunkSize);
  streamPendingText = streamPendingText.slice(chunkSize);
  $msg.html(mpu_parseMarkdown(streamDisplayedText));
  streamTypewriterTimer = setTimeout(streamTickDrain, mpuTypewriterSpeed);
}

function streamFinalize(data) {
  const finalMsg = data.msg || fullResponse;
  window.mpuChatHistory.push({
    role: "assistant",
    content: finalMsg,
    type: "chat",
    timestamp: Date.now(),
  });
  mpu_saveChatHistory();
  mpuChatRequesting = false;
  $input.prop("disabled", false);
  if (window.mpuChatModeActive) $input.focus();
  if (data.emoji && typeof window.mpuEmojiManager !== "undefined") {
    window.mpuEmojiManager.showEmoji(data.emoji);
  }
  if (typeof window.mpuCanvasManager !== "undefined" && window.mpuCanvasManager.isCharacterMode) {
    window.mpuCanvasManager.triggerCharacterAnimation(true);
  }
}
```

```js
onDelta: (data) => {
  if (data.text) {
    fullResponse += data.text;
    if (streamDisplayedText === "" && streamPendingText === "") $msg.empty();
    streamPendingText += data.text;
    streamStartDrain();
  }
},

onDone: (data) => {
  streamDone = true;
  streamDoneData = data;
  if (streamTypewriterTimer === null && streamPendingText.length === 0) {
    streamFinalize(data);
  }
  // 否則 streamTickDrain 跑完後自動呼叫 streamFinalize
},

onError: (error) => {
  // 清理區域 timer
  clearTimeout(streamTypewriterTimer);
  streamTypewriterTimer = null;
  streamPendingText = "";
  streamDone = false;
  mpuChatRequesting = false;
  $input.prop("disabled", false);
  if (window.mpuChatModeActive) $input.focus();
  // 撤回已 push 的 user 訊息（原有邏輯保留）
  if (
    window.mpuChatHistory.length > 0 &&
    window.mpuChatHistory[window.mpuChatHistory.length - 1].role === "user"
  ) {
    window.mpuChatHistory.pop();
    mpu_saveChatHistory();
  }
  const errorMsg = (error && error.message) ? error.message : "（…連線好像有點問題…）";
  mpu_typewriter(errorMsg, "#ukagaka_msg");
},

onAbort: () => {
  clearTimeout(streamTypewriterTimer);
  streamTypewriterTimer = null;
  streamPendingText = "";
  streamDone = false;
  if (
    window.mpuChatHistory.length > 0 &&
    window.mpuChatHistory[window.mpuChatHistory.length - 1].role === "user"
  ) {
    window.mpuChatHistory.pop();
    mpu_saveChatHistory();
  }
  mpuChatRequesting = false;
  $input.prop("disabled", false);
},
```

完成後執行 `npm run build`。

---

## 步驟二：SSE Event Parser Helper

**檔案**：`includes/llm/provider-stream-http.php`

### 說明

Gemini 和 Claude 的 SSE 格式細節不同於 OpenAI，且 chunk 邊界可能切在任意位置（包含 `data:` 中間）。抽一個共用 helper 處理：

- `event:` 行解析
- 多行 `data:` 合併
- 空行代表 event 結束
- `data: [DONE]` sentinel 轉成 stop 訊號

### 介面設計

```php
/**
 * 解析 SSE 串流，每收到一個完整 event 就呼叫 $callback。
 *
 * @param string   $url      API endpoint
 * @param array    $http_args wp_remote_post 格式的 args（含 stream callback）
 * @param callable $callback function(string $event_type, string $data_str): void
 *                           $event_type: event 名稱（無則為空字串）
 *                           $data_str:   合併後的 data payload（尚未 json_decode）
 * @return null|WP_Error
 */
function mpu_stream_sse_events(string $url, array $http_args, callable $callback): ?WP_Error {
    $chunk_buffer = "";
    $current_event = "";
    $data_lines = [];

    $result = mpu_stream_api_request($url, $http_args,
        function($chunk) use (&$chunk_buffer, &$current_event, &$data_lines, $callback) {
            $chunk_buffer .= $chunk;

            while (($pos = strpos($chunk_buffer, "\n")) !== false) {
                $line = substr($chunk_buffer, 0, $pos);
                $chunk_buffer = substr($chunk_buffer, $pos + 1);
                $line = rtrim($line, "\r");

                if ($line === '') {
                    // 空行：dispatch event
                    if (!empty($data_lines)) {
                        $data_str = implode("\n", $data_lines);
                        if ($data_str !== '[DONE]') {
                            call_user_func($callback, $current_event, $data_str);
                        }
                    }
                    $current_event = "";
                    $data_lines = [];
                } elseif (strpos($line, 'event:') === 0) {
                    $current_event = trim(substr($line, 6));
                } elseif (strpos($line, 'data:') === 0) {
                    $data_lines[] = trim(substr($line, 5));
                }
                // 其他欄位（id:, retry:）忽略
            }
        }
    );

    return is_wp_error($result) ? $result : null;
}
```

Gemini / Claude 的 `generate_chat_stream()` 都改用此 helper，不再各自維護 line parser。

---

## 步驟三：Gemini 純文字 Streaming

**檔案**：`includes/llm/providers/class-mpu-ai-provider-gemini.php`

### API 差異

| | 同步 | 串流 |
|---|---|---|
| Endpoint | `v1beta/models/{model}:generateContent` | `v1beta/models/{model}:streamGenerateContent?alt=sse` |
| 回應格式 | 單一 JSON | SSE，每個 event 是完整候選物件 |
| Key 位置 | URL query `?key=` | 同左 |

每個 SSE event 的 data：
```json
{"candidates":[{"content":{"parts":[{"text":"文字片段"}],"role":"model"},"finishReason":null}]}
```
- `finishReason` 為 `null`：生成中
- `finishReason` 為 `"STOP"`：結束
- 每個 event 的 `text` 就是**新增文字**（不是累積），直接 emit delta

### supports() 修改

```php
public function supports($feature) {
    return in_array($feature, [
        self::FEATURE_TOOLS,
        self::FEATURE_CHAT,
        self::FEATURE_STREAMING,
    ]);
}
```

### generate_chat_stream() 實作

```php
public function generate_chat_stream(array $args, $emit, array $context = []) {
    $api_key   = $args['api_key'] ?? '';
    $model     = $args['model'] ?? 'gemini-2.5-flash';
    $system_prompt = $args['system_prompt'] ?? '';
    $max_tokens    = $args['max_tokens'] ?? 1000;
    $temperature   = $args['temperature'] ?? 0.8;
    $messages      = $args['messages'] ?? [];

    $api_url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}"
             . ":streamGenerateContent?alt=sse&key=" . urlencode($api_key);

    // 轉換 messages（同 generate_chat() 邏輯）
    $contents = [];
    foreach ($messages as $msg) {
        $role = $msg['role'] === 'user' ? 'user' : 'model';
        $contents[] = ['role' => $role, 'parts' => [['text' => $msg['content']]]];
    }

    $request_body = [
        'systemInstruction' => ['parts' => [['text' => $system_prompt]]],
        'contents'          => $contents,
        'generationConfig'  => [
            'maxOutputTokens' => intval($max_tokens),
            'temperature'     => $temperature,
        ],
        // 第一版不帶 tools
    ];

    $error = mpu_stream_sse_events(
        $api_url,
        mpu_build_http_args(mpu_get_provider_headers('gemini'), $request_body),
        function($event_type, $data_str) use ($emit) {
            $data = json_decode($data_str, true);
            if (empty($data)) return;

            $parts = $data['candidates'][0]['content']['parts'] ?? [];
            foreach ($parts as $part) {
                if (isset($part['text']) && empty($part['thought'])) {
                    call_user_func($emit, 'delta', ['text' => $part['text']]);
                }
            }
        }
    );

    if (is_wp_error($error)) {
        call_user_func($emit, 'error', ['message' => $error->get_error_message()]);
        return $error;
    }

    return null;
}
```

**注意**：`mpu_get_provider_headers('gemini')` 應只回傳 `Content-Type: application/json`（Gemini API key 在 URL query，不在 header）。實作前確認這點。

---

## 步驟四：Claude 純文字 Streaming

**檔案**：`includes/llm/providers/class-mpu-ai-provider-claude.php`

### Anthropic SSE Event 類型（純文字用到的）

| event | 用途 |
|---|---|
| `content_block_delta` | `delta.type === 'text_delta'` → emit delta text |
| `message_stop` | 串流結束 |
| 其他 | 純文字階段忽略 |

### supports() 修改

```php
public function supports($feature) {
    return in_array($feature, [
        self::FEATURE_TOOLS,
        self::FEATURE_CHAT,
        self::FEATURE_STREAMING,
    ]);
}
```

### generate_chat_stream() 實作

```php
public function generate_chat_stream(array $args, $emit, array $context = []) {
    $api_key       = $args['api_key'] ?? '';
    $model         = $args['model'] ?? 'claude-sonnet-4-6';
    $system_prompt = $args['system_prompt'] ?? '';
    $max_tokens    = $args['max_tokens'] ?? 1000;
    $temperature   = $args['temperature'] ?? 0.8;
    $messages      = $args['messages'] ?? [];

    $api_url = 'https://api.anthropic.com/v1/messages';

    $claude_messages = [];
    foreach ($messages as $msg) {
        $claude_messages[] = ['role' => $msg['role'], 'content' => $msg['content']];
    }

    $request_body = [
        'model'       => $model,
        'max_tokens'  => intval($max_tokens),
        'temperature' => $temperature,
        'system'      => $system_prompt,
        'messages'    => $claude_messages,
        'stream'      => true,
        // 第一版不帶 tools
    ];

    $error = mpu_stream_sse_events(
        $api_url,
        mpu_build_http_args(mpu_get_provider_headers('claude', $api_key), $request_body),
        function($event_type, $data_str) use ($emit) {
            if ($event_type !== 'content_block_delta') return;
            $data = json_decode($data_str, true);
            $delta = $data['delta'] ?? [];
            if (($delta['type'] ?? '') === 'text_delta' && isset($delta['text'])) {
                call_user_func($emit, 'delta', ['text' => $delta['text']]);
            }
        }
    );

    if (is_wp_error($error)) {
        call_user_func($emit, 'error', ['message' => $error->get_error_message()]);
        return $error;
    }

    return null;
}
```

---

## 步驟五：Claude Tool Call Streaming

在步驟四基礎上加入 tool call 支援。改為多輪迴圈，並在串流過程中建立完整的 `$current_turn_content`。

### 關鍵：必須保存完整 assistant content blocks

Claude 下一輪的 `messages` 必須 append **完整本輪 assistant content**（text block + tool_use block），否則 Claude 缺失 tool_use context：

```php
// 正確格式
$claude_messages[] = [
    'role'    => 'assistant',
    'content' => $current_turn_content,  // [{type:'text',text:...}, {type:'tool_use',id,name,input:{...}}]
];
$claude_messages[] = [
    'role'    => 'user',
    'content' => $tool_results_for_next, // [{type:'tool_result',tool_use_id,...}]
];
```

### 串流 callback 內的 content block 狀態機

```php
$current_turn_content = [];  // 本輪 assistant content blocks
$current_block_type = null;
$current_block_idx = null;

// content_block_start
if ($event_type === 'content_block_start') {
    $block = $data['content_block'] ?? [];
    if ($block['type'] === 'text') {
        $current_block_type = 'text';
        $current_turn_content[] = ['type' => 'text', 'text' => ''];
        $current_block_idx = count($current_turn_content) - 1;
    } elseif ($block['type'] === 'tool_use') {
        $current_block_type = 'tool_use';
        $current_turn_content[] = [
            'type'  => 'tool_use',
            'id'    => $block['id'],
            'name'  => $block['name'],
            'input' => '',  // 累積 partial_json，content_block_stop 後 json_decode
        ];
        $current_block_idx = count($current_turn_content) - 1;
    }
}

// content_block_delta
if ($event_type === 'content_block_delta') {
    $delta = $data['delta'] ?? [];
    if ($delta['type'] === 'text_delta' && $current_block_type === 'text') {
        $text = $delta['text'];
        $current_turn_content[$current_block_idx]['text'] .= $text;
        // 帶 tools 時，pre-tool text 是否 emit 需根據決策
        // 若 pre-tool text 不會出現在最終回應，就不 emit（避免 checksum 不一致）
        call_user_func($emit, 'delta', ['text' => $text]);
    } elseif ($delta['type'] === 'input_json_delta' && $current_block_type === 'tool_use') {
        $current_turn_content[$current_block_idx]['input'] .= $delta['partial_json'];
    }
}

// content_block_stop
if ($event_type === 'content_block_stop' && $current_block_type === 'tool_use') {
    $raw = $current_turn_content[$current_block_idx]['input'];
    $current_turn_content[$current_block_idx]['input'] = json_decode($raw, true) ?? [];
}
```

### 多輪迴圈結構

```php
$max_turns = MPU_MAX_TOOL_TURNS;
$current_turn = 0;
$loop_state = [];

while ($current_turn < $max_turns) {
    $current_turn_content = [];
    $current_block_type = null;
    $current_block_idx = null;
    $stop_reason = null;

    $error = mpu_stream_sse_events($api_url, $http_args,
        function($event_type, $data_str) use (
            &$current_turn_content, &$current_block_type, &$current_block_idx,
            &$stop_reason, $emit
        ) {
            $data = json_decode($data_str, true);
            // ... 上方狀態機邏輯 ...

            if ($event_type === 'message_delta') {
                $stop_reason = $data['delta']['stop_reason'] ?? null;
            }
        }
    );

    if (is_wp_error($error)) {
        call_user_func($emit, 'error', ['message' => $error->get_error_message()]);
        return $error;
    }

    // 收集 tool_use blocks
    $tool_blocks = array_filter($current_turn_content, fn($b) => $b['type'] === 'tool_use');

    if (empty($tool_blocks)) break; // 無工具呼叫，結束

    if (function_exists('mpu_mark_request_mcp_tool_executed')) {
        mpu_mark_request_mcp_tool_executed();
    }

    $tool_results_for_next = [];
    foreach ($tool_blocks as $block) {
        $function_name = $block['name'];
        $tool_args = $block['input'];

        $guard = mpu_tool_loop_guard_check($loop_state, $this->get_slug(), $current_turn, $function_name, $tool_args);
        if (is_wp_error($guard)) return $guard;

        call_user_func($emit, 'status', ['type' => 'executing_tool', 'tool' => $function_name]);

        $tool_result = function_exists('mpu_execute_mcp_tool')
            ? mpu_execute_mcp_tool($function_name, $tool_args)
            : ['error' => 'Tool execution function missing'];

        $tool_results_for_next[] = mpu_build_claude_tool_result_block($block['id'], $tool_result);
    }

    $claude_messages[] = ['role' => 'assistant', 'content' => $current_turn_content];
    $claude_messages[] = ['role' => 'user',      'content' => $tool_results_for_next];

    $current_turn++;
}
```

---

## 步驟六：Gemini Tool Call — 永久 Fallback

當 Gemini streaming 需要工具呼叫時（日後擴充），在 `generate_chat_stream()` 內部呼叫 `$this->generate_chat()` 並 emit 單一 delta：

```php
// Gemini generate_chat_stream() 內，若需要 tools：
$sync_result = $this->generate_chat($args);
if (is_wp_error($sync_result)) {
    call_user_func($emit, 'error', ['message' => $sync_result->get_error_message()]);
    return $sync_result;
}
call_user_func($emit, 'delta', ['text' => $sync_result]);
return null;
```

第一版（步驟三）不帶 tools，不需要此邏輯。留待日後擴充時加入。

---

## Checksum 注意事項

### 純文字 streaming（步驟三、四）

`user_chat_stream()` 在 `generate_chat_stream()` 回傳後，從所有 `delta` event 累積的 `$full_response_content` 寫入 checksum，再 emit `done`。純文字無 tool call，累積值 = 最終文字，與同步路徑一致。**不需修改 checksum 邏輯。**

### Tool call streaming（步驟五）

必須確保三者完全一致：

```
delta 累積值（$full_response_content）
    = done.msg
    = 寫入 checksum 的 $result
```

若 pre-tool text（工具呼叫前的過渡台詞）**不會**出現在最終回應，就**不要 emit 成 delta**，否則累積值會包含它，但 done.msg（最後一輪 LLM 回應）不包含它，造成 mismatch。

決策：實作 Claude tool streaming 時先統一「pre-tool text 不 emit」，僅 emit 最後一輪的文字，最簡單且最安全。

---

## 測試清單

每個步驟完成後驗證：

- [ ] 純文字：文字依照後台 `typewriter_speed` 逐字顯示（不瞬間顯示）
- [ ] `streamTypewriterTimer` 不影響一般 `mpu_typewriter()` 行為
- [ ] `onError` / `onAbort` 時 user message 正確從 history 撤回，timer 清除
- [ ] `mpuChatHistory` push 在 queue 清空後才執行
- [ ] Emoji 在 finalize 時正確顯示
- [ ] Tool call：顯示 `status: executing_tool`
- [ ] Tool call 完成：LLM 第二輪文字正常串流
- [ ] Checksum 無 mismatch（連續對話 5 輪以上，含 tool call）
- [ ] `php -l` 全部通過

---

## 檔案清單

| 步驟 | 修改檔案 |
|---|---|
| 1 | `js/ukagaka-chat.js`、`js/dist/ukagaka-bundle.js`、`js/dist/ukagaka-bundle.min.js` |
| 2 | `includes/llm/provider-stream-http.php` |
| 3 | `includes/llm/providers/class-mpu-ai-provider-gemini.php` |
| 4 | `includes/llm/providers/class-mpu-ai-provider-claude.php` |
| 5 | `includes/llm/providers/class-mpu-ai-provider-claude.php`（續） |
| 6 | `includes/llm/providers/class-mpu-ai-provider-gemini.php`（日後擴充） |

步驟一完成後需執行 `npm run build`。所有 PHP 修改後執行 `php -l`。
