# SSE Streaming 蟇ｦ菴懆ｨ育吻・・6・・
## 逶ｮ逧・
轤ｺ `mp-ukagaka` 逧・ｺ貞虚蟆崎ｩｱ讓｡蠑丞刈蜈･ **莨ｺ譛榊勣逋ｼ騾∽ｺ倶ｻｶ荳ｲ豬・ｼ・SE・・*・悟惠蠕檎ｫｯ隗｣譫先ｨ｡蝙・Streaming chunk 蠕鯉ｼ悟叉譎よ耳騾∝芦蜑咲ｫｯ・梧隼蝟・聞蝗櫁ｦ・・諤晁・梛讓｡蝙狗噪遲牙ｾ・ｫ秘ｩ暦ｼ域遠蟄玲ｩ滓譜譫・/ 貍ｸ騾ｲ鬘ｯ遉ｺ・峨・
譛ｬ險育吻莉･縲・*菴朱｢ｨ髫ｪ蛻・嚴谿ｵ蟆主・**縲咲ぜ蜴溷援・悟━蜈井ｿ晉蕗迴ｾ譛牙酔豁･霍ｯ蠕醍嶌螳ｹ諤ｧ縲・
---

## 迴ｾ豕∵遭隕・ｼ亥ｷｲ遒ｺ隱搾ｼ・
- REST 閨雁､ｩ荳ｻ霍ｯ蠕醍ぜ `POST /mp-ukagaka/v1/chat/user`
  - 險ｻ蜀贋ｽ咲ｽｮ・啻includes/rest/class-mpu-rest-chat.php:42`
  - 陌慕炊蜈･蜿｣・啻includes/rest/class-mpu-rest-chat.php:466`
- 蜑咲ｫｯ莠貞虚閨雁､ｩ逶ｮ蜑咲ぜ荳谺｡諤ｧ `fetch` + JSON 蝗樊㊨・磯撼荳ｲ豬・ｼ・  - `js/ukagaka-chat.js:329`
- 騾夂畑 `mpuFetch()` 逶ｮ蜑肴弍 JSON/譁・ｭ怜屓諛画ｨ｡蝙具ｼ御ｸｦ譛・ｾ・JSON 逧・`new_token` 譖ｴ譁ｰ REST nonce
  - `js/ukagaka-base.js:737`
  - `js/ukagaka-base.js:772`
  - `js/ukagaka-base.js:826`
- 螟夊ｼｪ閨雁､ｩ wrapper 逶ｮ蜑咲ぜ蜷梧ｭ･ `generate_chat()`
  - `includes/ajax/chat-api-handlers.php:26`
- Provider base 蟾ｲ鬆千蕗 `FEATURE_STREAMING` 蟶ｸ謨ｸ・御ｽ・ｰ壽悴蟇ｦ菴・  - `includes/llm/providers/class-mpu-ai-provider-base.php:24`
- Request-scoped state 蟾ｲ鬆千蕗 `streaming` 迢諷区ｬ・ｽ搾ｼ育岼蜑榊ュ buffer・・  - `includes/llm/request-state.php:42`
- `chat integrity checksum` 豬∫ｨ狗岼蜑肴弍・・  - 隲区ｱょ燕鬩苓ｭ・history
  - AI 蝗樊㊨螳梧・蠕悟ｯｫ蜈･荳倶ｸ霈ｪ checksum
  - SSE 蠢・井ｿ晉蕗逶ｸ蜷悟ｮ牙・鬆・ｺ・
---

## 譬ｸ蠢・ｨｭ險域ｱｺ遲厄ｼ亥ｻｺ隴ｰ・・
1. 譁ｰ蠅樒昏遶狗ｫｯ鮟・`POST /mp-ukagaka/v1/chat/user-stream`
2. 蜑咲ｫｯ菴ｿ逕ｨ `fetch + ReadableStream` 隶蜿・SSE・井ｸ堺ｽｿ逕ｨ `EventSource`・・3. 隨ｬ荳迚亥ュ蟆主・莠貞虚閨雁､ｩ・・chat/user`・会ｼ御ｸ肴洞謨｣蛻ｰ `chat/context` / `chat/greet`
4. 菫晉蕗蜷梧ｭ･ fallback・・rovider 荳肴髪謠ｴ streaming 譎り・蜍暮蝗櫁・豬∫ｨ具ｼ・5. 隨ｬ荳髫取ｮｵ蜆ｪ蜈域髪謠ｴ `OpenAI` + `Ollama`・形Claude` / `Gemini` 蟒ｶ蠕・6. REST SSE 謗幃ｻ樊治 **Option A・壼惠 callback 蜈ｧ逶ｴ謗･ `echo + flush + exit;`**・井ｸ堺ｽｿ逕ｨ `rest_pre_serve_request`・・
### 轤ｺ菴穂ｸ咲畑 EventSource

- 迴ｾ譛芽ｫ区ｱよ弍 `POST + FormData + X-WP-Nonce`
- `EventSource` 荳埼←蜷域ｭ､隲区ｱよｨ｡蝙具ｼ亥￥蜷・GET・御ｸ・headers 螳｢陬ｽ蝗ｰ髮｣・・- `fetch + ReadableStream` 譖ｴ隨ｦ蜷育樟譛牙燕遶ｯ譫ｶ讒玖・ nonce 豕ｨ蜈･髴豎・
---

## 蛻・嚴谿ｵ蟇ｦ譁ｽ險育吻

## Phase 0・亥・豎ｺ譴昜ｻｶ・会ｼ壽歓蜃ｺ user_chat 蜈ｱ逕ｨ蜑咲ｽｮ貅門ｙ豬∫ｨ具ｼ磯∩蜈埼ｏ霈ｯ隍・｣ｽ・・
`REST user_chat()` 逶ｮ蜑埼ｏ霈ｯ蠕磯聞・郡SE 闍･逶ｴ謗･隍・｣ｽ譛・謌千ｶｭ隴ｷ鬚ｨ髫ｪ縲・
### 蟒ｺ隴ｰ謚ｽ蜃ｺ蜃ｽ蠑擾ｼ域・遒ｺ蜻ｽ蜷搾ｼ・
```php
function mpu_prepare_user_chat_args(WP_REST_Request $request): array|WP_Error
```

### 蟒ｺ隴ｰ蝗槫さ蜈ｧ螳ｹ・郁・蟆托ｼ・
- `provider`
- `api_key`
- `system_prompt`
- `messages`
- `max_tokens`
- `language`
- `personality_id`
- `ukagaka_name`
- `session_id`・亥ｷｲ螳梧・ checksum 鬩苓ｭ会ｼ・- `chat_history`
- `user_message`
- `mpu_opt`

### 逶ｮ讓・
- 蜷梧ｭ･ `user_chat()` 闊・眠 `user_chat_stream()` 蜈ｱ逕ｨ蜷御ｸ莉ｽ蜑咲ｽｮ豬∫ｨ・- 髯堺ｽ取悴萓・ｮ牙・菫ｮ陬懶ｼ・anitize/checksum/rate-limit・画ｼ乗隼鬚ｨ髫ｪ

---

## Phase 1・壼ｮ夂ｾｩ謠剃ｻｶ蜈ｧ驛ｨ SSE 蜊碑ｭｰ・域ｨ呎ｺ紋ｺ倶ｻｶ・・
蠕檎ｫｯ邨ｱ荳霈ｸ蜃ｺ `text/event-stream`・御ｺ倶ｻｶ譬ｼ蠑丞崋螳夲ｼ継ayload 轤ｺ JSON・・
- `event: start`
  - 隲区ｱょｷｲ謗･蜿暦ｼ・rovider / model / request_id・・- `event: nonce`
  - 蛯ｳ驕・`new_token`・亥庄蜉荳・`new_nonce` 蜷悟ｼ谺・ｽ搾ｼ御ｿ晉蕗譛ｪ萓・洞蜈・多蜷咲ｩｺ髢難ｼ・  - SSE 霍ｯ蠕台ｸ肴怎閾ｪ蜍戊ｵｰ `rest_post_dispatch`
- `event: delta`
  - 譁・ｭ怜｢樣㍼・亥燕遶ｯ蜊ｳ譎よ蕎謗･・・- `event: status`
  - 迢諷矩夂衍・域晁・ｸｭ縲∝ｷ･蜈ｷ蝓ｷ陦御ｸｭ遲会ｼ・- `event: tool_call`
  - 蟾･蜈ｷ蜷咲ｨｱ・亥庄驕ｸ・悟￥ debug/UI・・- `event: tool_result`
  - 蟾･蜈ｷ蝓ｷ陦悟ｮ梧・・亥庄驕ｸ・・- `event: done`
  - 螳梧紛邨先棡縲‘moji縲∵噺蟆ｾ雉・ｨ奇ｼ・hecksum / 邨ｱ險亥ｮ梧・・・- `event: error`
  - 骭ｯ隱､遒ｼ闊・ｨ頑・
- `event: ping`
  - heartbeat・磯∩蜈埼聞譎る俣辟｡霈ｸ蜃ｺ驕ｭ proxy 荳ｭ譁ｷ・・
### 險ｭ險亥次蜑・
- 蟆榊燕遶ｯ證ｴ髴ｲ逧・弍縲梧薯莉ｶ讓呎ｺ紋ｺ倶ｻｶ縲搾ｼ御ｸ肴弍蜷・provider 蜴溽函譬ｼ蠑・- provider chunk 蟾ｮ逡ｰ・・penAI SSE / Ollama JSON Lines・臥罰蠕檎ｫｯ蜷ｸ謾ｶ

---

## Phase 2・壽眠蠅・SSE 蛯ｳ霈ｸ蜈ｱ逕ｨ Helper

蟒ｺ隴ｰ譁ｰ蠅樊ｪ疲｡茨ｼ域島荳・会ｼ・
- `includes/rest/sse-helpers.php`
- 謌・`includes/llm/streaming-helpers.php`

### 閨ｷ雋ｬ

- 險ｭ螳・SSE 讓咎ｭ
  - `Content-Type: text/event-stream`
  - `Cache-Control: no-cache, no-transform`
  - `X-Accel-Buffering: no`
  - `X-Content-Type-Options: nosniff`
- 螳牙・蝨ｰ髣憺哩/貂・炊 output buffering
- SSE handler 騾ｲ蜈･鮟櫁ｨｭ鄂ｮ・・  - `ignore_user_abort(true);`
  - `set_time_limit(0);`
- 謠蝉ｾ幃夂畑蜃ｽ蠑擾ｼ・  - `mpu_sse_send_event($event, $data)`
  - `mpu_sse_flush()`
  - `mpu_sse_heartbeat_if_needed()`
  - `mpu_sse_client_disconnected()`

### 豕ｨ諢・
- 騾吩ｸ螻､蜿ｪ雋雋ｬ縲悟さ霈ｸ縲搾ｼ御ｸ崎剳逅・♀螟ｩ讌ｭ蜍咎ｏ霈ｯ
- Output buffering 蠢・域ｸ・､壼ｱ､・井ｸ崎・蜿ｪ貂・ｸ螻､・会ｼ・
```php
while (ob_get_level() > 0) {
    ob_end_clean();
}
```

- 闍･譛ｪ貂・ｹｾ豺ｨ・傾AMPP / Apache / PHP 螟壼ｱ､ buffering 譛・ｰ手・縲檎恚莨ｼ荳ｲ豬√∝ｯｦ髫帶紛蛹・ｼｸ蜃ｺ縲・
---

## Phase 3・壽眠蠅・REST 霍ｯ逕ｱ `POST /chat/user-stream`

讙疲｡茨ｼ啻includes/rest/class-mpu-rest-chat.php`

### 隶頑峩

- 蝨ｨ `register_routes()` 蠅槫刈 `/chat/user-stream`
- 譁ｰ蠅・callback・啻user_chat_stream(WP_REST_Request $request)`

### 陦檎ぜ隕∵ｱゑｼ磯怙闊・`/chat/user` 荳閾ｴ・・
- Rate limit 荳閾ｴ・・0 谺｡ / 60 遘抵ｼ・- 菴ｿ逕ｨ迴ｾ譛・request-state reset 讖溷宛・・rest_pre_dispatch` 蟾ｲ譛会ｼ・- `chat integrity verify` 蜑咲ｽｮ讙｢譟･荳閾ｴ
- 謌仙粥譎りｵｰ SSE 霈ｸ蜃ｺ・磯撼 `WP_REST_Response`・・
### 驥崎ｦ∝ｷｮ逡ｰ

- 逕ｱ譁ｼ SSE callback 蜿ｯ閭ｽ逶ｴ謗･霈ｸ蜃ｺ荳ｦ謠仙燕邨先據・形rest_post_dispatch` 逧・・蜍・nonce refresh 荳堺ｸ螳壽怎螂礼畑
- 蠢・亥惠 SSE 豬∫ｨ倶ｸｭ閾ｪ陦檎匸騾・`event: nonce`

### 謗幃ｻ樊ｱｺ遲厄ｼ域悽險育吻謗｡逕ｨ・・
- 謗｡逕ｨ **callback 蜈ｧ逶ｴ謗･霈ｸ蜃ｺ SSE 荳ｦ `exit;`**
- 荳堺ｽｿ逕ｨ `rest_pre_serve_request`
- 逅・罰・・  - `rest_pre_dispatch`・・equest-state reset・牙ｷｲ蝨ｨ callback 蜑榊濤陦・  - `rest_post_dispatch` nonce refresh 蜿ｯ逕ｱ `event: nonce` 陬憺ｽ・  - 驕ｿ蜈・sentinel 蝗槫さ蛟ｼ闊・｡榊､・filter 蜊碑ｪｿ謌先悽

---

## Phase 4・啀rovider Streaming 莉矩擇闊・・蜉帛ｮ｣蜻・
### 蟒ｺ隴ｰ謫ｴ蜈・Provider contract

蝨ｨ Provider 莉矩擇蠅槫刈荳ｲ豬∵婿豕包ｼ亥多蜷榊庄險手ｫ厄ｼ会ｼ・
- `generate_chat_stream(array $args, callable $emit, array $context = [])`

蜈ｶ荳ｭ・・
- `$emit`・嗔rovider 蟆・ｧ｣譫仙ｾ御ｺ倶ｻｶ蝗槫さ邨ｦ荳雁ｱ､・・EST SSE handler・・- `$context`・壼さ驕・request-level metadata・井ｾ句ｦ・`provider`縲～request_id`・会ｼ碁∩蜈・provider 萓晁ｳｴ REST 螻､邏ｰ遽

### `$emit` callable 邁ｽ蜷搾ｼ亥ｻｺ隴ｰ蜈亥ｮ夂ｾｩ・・
```php
// $emit(string $event_name, array $data): void
```

遽・ｾ具ｼ・
```php
$emit('delta', ['text' => '菴螂ｽ']);
$emit('done', ['full_text' => '...', 'emoji' => null]);
$emit('error', ['code' => 'stream_failed', 'message' => '...']);
```

### `supports()` 閭ｽ蜉帛ｮ｣蜻・
- `supports(FEATURE_STREAMING)` 譏守｢ｺ蝗槫ｱ provider 譏ｯ蜷ｦ謾ｯ謠ｴ
- 隨ｬ荳髫取ｮｵ・・  - `OpenAI`・啻true`
  - `Ollama`・啻true`
  - `Claude`・啻false`・域・ stub・・  - `Gemini`・啻false`・域・ stub・・
### 逶ｸ螳ｹ諤ｧ隕∵ｱ・
- 謇譛・provider 蜈郁｣・stub・碁∩蜈・interface 謾ｹ蜍暮謌・fatal error
- 蟇ｦ菴憺怙邯ｭ謖・PHP 7.4 逶ｸ螳ｹ・井ｸ榊庄菴ｿ逕ｨ PHP 8.0 union types・・
### PHP 7.4 逶ｸ螳ｹ諤ｧ謠宣・・亥ｯｦ菴懈凾・・
譛ｬ險育吻荳ｭ逧・・蠑冗ｰｽ蜷咲､ｺ諢丞ｱｬ譁ｼ pseudocode・悟ｯｦ菴懈凾隲句響逶ｴ謗･菴ｿ逕ｨ PHP 8+ union type・御ｾ句ｦゑｼ・
- `array|WP_Error`
- `void|WP_Error`

隲区隼逕ｨ PHPDoc + PHP 7.4 逶ｸ螳ｹ邁ｽ蜷搾ｼ御ｾ句ｦゑｼ・
```php
/** @return array|WP_Error */
function mpu_prepare_user_chat_args(WP_REST_Request $request) {
    // ...
}
```

---

## Phase 5・壽眠蠅樔ｽ朱嚴荳ｲ豬・HTTP Client・磯梨骰ｵ・・
迴ｾ譛・provider 逧・ｻ･ `wp_remote_post()` 謾ｶ謨ｴ蛹・屓諛会ｼ御ｸ埼←蜷育悄豁｣ chunk relay縲・
### 蟒ｺ隴ｰ譁ｰ蠅・helper

- `includes/llm/provider-stream-http.php`

### 蟒ｺ隴ｰ蟇ｦ菴懈婿蠑・
- 菴ｿ逕ｨ PHP cURL low-level streaming・・CURLOPT_WRITEFUNCTION`・・- 蝨ｨ callback 荳ｭ蜊ｳ譎よ磁謾ｶ chunk・御ｺ､邨ｦ provider parser
- 謾ｯ謠ｴ・・  - headers / body / timeout
  - client disconnect 譎ゆｸｭ豁｢荳頑ｸｸ隲区ｱ・  - 骭ｯ隱､讓呎ｺ門喧蝗槫さ

### 譁ｷ邱夊剳逅・梨骰ｵ・亥ｿ・茨ｼ・
- SSE handler 騾ｲ蜈･鮟樒ｬｬ荳陦悟ｰｱ蜻ｼ蜿ｫ・・
```php
ignore_user_abort(true);
```

- 蜷ｦ蜑・client 譁ｷ邱壽凾 PHP 蜿ｯ閭ｽ逶ｴ謗･荳ｭ豁｢ script・悟濤陦御ｸ榊芦 `connection_aborted()` 讙｢譟･鮟・- 辟ｶ蠕悟惠 streaming loop / callback 荳ｭ螳壽悄讙｢譟･ `connection_aborted()`・悟ｿ・ｦ∵凾蛛懈ｭ｢荳頑ｸｸ cURL 荳ｲ豬・
### 髯咲ｴ夂ｭ也払

- 闍･迺ｰ蠅・┌ cURL 謌紋ｸｲ豬∝ｻｺ遶句､ｱ謨暦ｼ・  - 蝗・`error` event・域・遒ｺ險頑・・・  - 謌・fallback 蛻ｰ蜷梧ｭ･ `chat/user`・井ｾ晏ｯｦ菴憺∈謫・ｼ・
> 騾呎弍 #6 謌先風髣憺嵯・瑚ｫ・Claude / Gemini 迚ｹ蛻･蟇ｩ隕厄ｼ亥ｰ､蜈ｶ `ignore_user_abort(true)` 闊・cURL callback 逧・ｸｭ豁｢遲也払・峨・
---

## Phase 6・啀rovider 荳ｲ豬∬ｧ｣譫仙勣・育ｬｬ荳髫取ｮｵ・唹penAI + Ollama・・
## 7.1 OpenAI Streaming

### 荳頑ｸｸ譬ｼ蠑・
- OpenAI 轤ｺ SSE・・data: ...`・梧怙邨・`[DONE]`・・
### 蠕檎ｫｯ隗｣譫宣㍾鮟・
- 隗｣譫・`choices[].delta.content` 菴懃ぜ譁・ｭ怜｢樣㍼
- 闍･蜃ｺ迴ｾ `tool_calls` delta・碁怙莉･ `index` 邨・｣晁ｷｨ螟・chunk 逧・tool call・井ｸ崎・逶ｴ謗･逡ｶ譁・ｭ苓ｼｸ蜃ｺ・・- 蟆・provider chunk 霓臥ぜ謠剃ｻｶ讓呎ｺ紋ｺ倶ｻｶ・・delta/status/tool_call/...`・・
### OpenAI `tool_calls` 荳ｲ豬∫ｵ・｣晢ｼ磯ｫ倬｢ｨ髫ｪ蜊・碁怙迯ｨ遶区ｸｬ隧ｦ・・
OpenAI 逧・`tool_calls` 蟶ｸ霍ｨ螟壼・delta・御ｻ･ `index` 諡ｼ謗･・御ｸ肴怎荳谺｡邨ｦ螳梧紛 arguments・・
```text
delta: {"tool_calls":[{"index":0,"id":"call_abc","function":{"name":"get-pop"}}]}
delta: {"tool_calls":[{"index":0,"function":{"arguments":"{\"lim"}}]}
delta: {"tool_calls":[{"index":0,"function":{"arguments":"it\":5}"}}]}
```

### 蟒ｺ隴ｰ邨・｣晉ｭ也払

- 邯ｭ隴ｷ `$tool_calls_buffer[$index]`
- 謖∫ｺ梧蕎謗･・・  - `id`
  - `function.name`
  - `function.arguments`・亥ｭ嶺ｸｲ蠅樣㍼・・- 蜒・惠 `finish_reason === 'tool_calls'` 譎ゑｼ梧燕蟆・buffer 隕也ぜ蜿ｯ蝓ｷ陦悟ｷ･蜈ｷ蜻ｼ蜿ｫ
- 荵句ｾ悟・騾ｲ蜈･譌｢譛・Loop Guard / MCP execution 豬∫ｨ・
> 豁､谿ｵ蟒ｺ隴ｰ蛻礼ぜ Phase 6 逧・昏遶区ｸｬ隧ｦ鬆・岼・域怙螳ｹ譏灘・骭ｯ・峨・
## 7.2 Ollama Streaming

### 荳頑ｸｸ譬ｼ蠑・
- 蟶ｸ隕狗ぜ JSON Lines・域ｯ剰｡御ｸ蛟・JSON・御ｸ肴弍 SSE・・
### 蠕檎ｫｯ隗｣譫宣㍾鮟・
- 騾占｡瑚ｧ｣譫・JSON
- 謚ｽ蜿・`message.content` / `message.thinking` / `done`
- 蜀崎ｽ画・謠剃ｻｶ讓呎ｺ・SSE 莠倶ｻｶ
- `thinking` 蜈ｧ螳ｹ・亥ｦ・`<think>...</think>`・蛾怙蝨ｨ隨ｬ荳迚域・遒ｺ遲也払・・  - 蟒ｺ隴ｰ鬆占ｨｭ驕取ｿｾ・御ｸ埼｡ｯ遉ｺ邨ｦ菴ｿ逕ｨ閠・  - 螯る怙 debug・悟庄謾ｹ襍ｰ `status` 莠倶ｻｶ鞫倩ｦ∬碁撼蜴滓枚霈ｸ蜃ｺ

## 7.3 蜈ｱ騾夊ｦ∵ｱ・
- 邏ｯ遨肴怙邨よ枚蟄暦ｼ井ｾ・checksum / emoji / store_history 菴ｿ逕ｨ・・- 蟾･蜈ｷ蜻ｼ蜿ｫ蝗槫粋邯ｭ謖∵里譛・Loop Guard 闊・request-state 讓呵ｨ・- 蝨ｨ驕ｩ逡ｶ譎よｩ滄・heartbeat・・ping`・・
---

## Phase 7・壼ｷ･蜈ｷ蜻ｼ蜿ｫ・・CP・芽・荳ｲ豬∫ｭ也払・育ｬｬ荳迚亥ｻｺ隴ｰ・・
### 隨ｬ荳迚育ｭ也払

- 蜒・ｸｲ豬√梧怙邨・assistant 譁・ｭ怜屓蜷医・- 蟾･蜈ｷ蝗槫粋荳崎ｼｸ蜃ｺ蟾･蜈ｷ邨先棡蜈ｨ譁・ｼ悟ュ逋ｼ騾∫朽諷倶ｺ倶ｻｶ
  - `status`
  - `tool_call`
  - `tool_result`

### 迢諷倶ｺ倶ｻｶ蟒ｺ隴ｰ payload

- `status` 蜿ｯ蟶ｶ・・  - `thinking: true`
  - `executing_tool: "tool_name"`
  - `message: "豁｣蝨ｨ謳懷ｰ狗ｶｲ鬆・.."`・亥庄驕ｸ・・
### 蜆ｪ鮟・
- 菫晉蕗逶ｮ蜑・MCP 螳牙・驍顔阜
- 荳榊ｿ・ｮ灘燕遶ｯ陌慕炊隍・屆 tool schema / partial JSON
- 闊・樟譛・`Loop Guard` / checksum 豬∫ｨ狗嶌螳ｹ諤ｧ鬮・
### 蜿ｯ驕ｸ・井ｹ句ｾ鯉ｼ・
- debug mode 荳矩｡榊､冶ｼｸ蜃ｺ蟾･蜈ｷ邨先棡鞫倩ｦ・ｼ磯撼鬆占ｨｭ・・
---

## Phase 8・壼燕遶ｯ SSE Reader・域眠蜃ｽ蠑擾ｼ御ｸ肴隼 `mpuFetch()`・・
讙疲｡茨ｼ啻js/ukagaka-chat.js`

### 譁ｰ蠅槫・蠑擾ｼ亥多蜷榊庄隱ｿ謨ｴ・・
- `mpuFetchSSE(...)`
- 謌・`mpu_chat_stream_request(...)`

### 蟇ｦ菴懈婿蠑・
- 菴ｿ逕ｨ `fetch(..., { method: "POST", body: FormData, headers: { "X-WP-Nonce": mpuRestNonce } })`
- 隶蜿・`response.body.getReader()`
- 菴ｿ逕ｨ `TextDecoder` 隗｣譫・chunk
- 閾ｪ陦瑚剳逅・SSE frame 驍顔阜・・hunk 蜿ｯ閭ｽ蛻・鵡 event・・
### SSE frame parser・磯怙譏守｢ｺ蟇ｦ菴懶ｼ・
蜑咲ｫｯ髴邯ｭ謖∵戟荵・`lineBuffer`・瑚剳逅・chunk 陲ｫ蛻・鵡蝨ｨ event 荳ｭ髢鍋噪諠・ｳ・ｼ・
```js
let lineBuffer = "";

// 豈乗ｬ｡ chunk 蛻ｰ驕疲凾・・lineBuffer += decoder.decode(chunk, { stream: true });
const frames = lineBuffer.split("\n\n");
lineBuffer = frames.pop() || ""; // 譛蠕御ｸ谿ｵ蜿ｯ閭ｽ荳榊ｮ梧紛・御ｿ晉蕗蛻ｰ荳倶ｸ霈ｪ

for (const frame of frames) {
  // parse event:/data: lines
}
```

### 陬懷・

- 荳榊庄蛛・ｨｭ `ReadableStream` chunk 闊・SSE event frame 荳荳蟆埼ｽ・- `data:` JSON 荵溷庄閭ｽ蜑帛･ｽ蝨ｨ蟄嶺ｸｲ荳ｭ髢楢｢ｫ蛻・幕

### 莠倶ｻｶ陌慕炊驍剰ｼｯ

- `delta`
  - 邏ｯ遨肴枚蟄嶺ｸｦ蜊ｳ譎よ峩譁ｰ `#ukagaka_msg`
  - 蜿紋ｻ｣縲檎ｭ牙ｾ・紛蛹・ｾ悟・ `mpu_typewriter()`縲咲噪讓｡蠑・- `nonce`
  - 譖ｴ譁ｰ `window.mpuRestNonce`
- `status`
  - 蜈亥ｯｫ log・井ｹ句ｾ悟庄蛛・UI・・- `done`
  - 蟇ｫ蜈･ `mpuChatHistory`
  - `mpu_saveChatHistory()`
  - 鬘ｯ遉ｺ emoji
  - 隗｣骼冶ｼｸ蜈･譯・/ 閨夂┬
- `error`
  - 鬘ｯ遉ｺ骭ｯ隱､險頑・
  - 隗｣骼・UI

### 闊・樟譛・`mpu_sendUserMessage()` 謨ｴ蜷・
- 蜉蜈･蛻・髪・・  - 闍･ SSE 蜿ｯ逕ｨ荳・provider 謾ｯ謠ｴ streaming -> 襍ｰ SSE
  - 蜷ｦ蜑・ｶｭ謖∵里譛・`mpuFetch(mpuRestUrl + "chat/user", ...)`

### 豕ｨ諢・
- 荳榊ｻｺ隴ｰ逶ｴ謗･謾ｹ騾 `mpuFetch()` 謌・SSE 蜈ｩ逕ｨ・碁｢ｨ髫ｪ霈・ｫ假ｼ育樟譛牙､ｧ驥・JSON 蜻ｼ蜿ｫ萓晁ｳｴ・・
---

## Phase 9・壼叙豸郁ｫ区ｱり・譁ｷ邱夊剳逅・
## 蜑咲ｫｯ

- 菴ｿ逕ｨ `AbortController` 荳ｭ豁｢ SSE 隲区ｱ・- 隗ｸ逋ｼ諠・｢・ｼ・  - 菴ｿ逕ｨ閠・・谺｡騾∝・
  - 髮｢髢玖♀螟ｩ讓｡蠑・  - 荳ｻ蜍募叙豸茨ｼ郁凶蠕檎ｺ悟刈謖蛾・・・
## 蠕檎ｫｯ

- 莉･ `connection_aborted()` 蛛ｵ貂ｬ client disconnect
- 荳ｭ豁｢荳頑ｸｸ provider 荳ｲ豬∬ｫ区ｱゑｼ磯∩蜈崎ｳ・ｺ先ｵｪ雋ｻ・・- 蛛懈ｭ｢蠕檎ｺ・SSE 霈ｸ蜃ｺ闊・噺蟆ｾ蟇ｫ蜈･

### 驕ｿ蜈阪悟ｹｽ髱郁ｪｪ隧ｱ縲・
- 蜑咲ｫｯ蝨ｨ閨雁､ｩ讓｡蠑城梨髢画凾遶句叉 abort
- 荳榊ｰ・ｸｭ譁ｷ荳ｭ逧・partial 蝗櫁ｦ・ｯｫ蜈･豁ｷ蜿ｲ

---

## Phase 10・壼ｮ梧紛謾ｶ蟆ｾ・郁・蜷梧ｭ･霍ｯ蠕台ｸ閾ｴ・・
蝨ｨ荳ｲ豬∝ｮ梧・蠕鯉ｼ・inal text 蟾ｲ遒ｺ螳夲ｼ牙・蝓ｷ陦鯉ｼ・
- 蝗樊㊨髟ｷ蠎ｦ髯仙宛・井ｿ晉蕗迴ｾ譛・`mpu_did_request_execute_mcp_tool()` 驍剰ｼｯ・・- `emoji` 蛻・梵
- `mpu_record_conversation('interactive')`
- `mpu_chat_integrity_store_history(...)`
- 譛蠕碁・`event: done`

### 荳ｭ騾秘険隱､ / 荳ｭ豁｢譎・
- 荳榊ｯｫ checksum・磯∩蜈肴ｱ｡譟謎ｸ倶ｸ霈ｪ integrity・・- 隕匁ュ蠅・・`error` event 謌門ｮ蛾撩邨先據・・lient 蟾ｲ譁ｷ邱夲ｼ・- 蜒・惠 `event: done` 蜑阪∽ｸ秘｣邱壻ｻ肴怏謨域凾蟇ｫ蜈･ checksum

---

## Phase 11・壽ｸｬ隧ｦ闊・ｩ苓ｭ芽ｨ育吻・亥ｿ・★・・
## 蜉溯・貂ｬ隧ｦ

- OpenAI・・  - 遏ｭ蝗櫁ｦ・/ 髟ｷ蝗櫁ｦ・/ 蟾･蜈ｷ蜻ｼ蜿ｫ蝗槫粋
- Ollama・・  - 荳闊ｬ讓｡蝙・/ thinking 讓｡蝙具ｼ磯聞遲牙ｾ・ｼ・- 閨雁､ｩ讓｡蠑城梨髢我ｸｭ騾・abort
- 蠢ｫ騾滄｣鮟樣∝・・・ancelPrevious 陦檎ぜ・・- `/reset` / `/clear` 蠕・session 霈ｪ譖ｿ豁｣蟶ｸ

## 螳牙・闊・ｩｩ螳壽ｧ貂ｬ隧ｦ

- Rate limit 莉咲函謨・- nonce 譖ｴ譁ｰ・・SE `nonce` event・牙庄豁｣遒ｺ蛻ｷ譁ｰ蜑咲ｫｯ `mpuRestNonce`
- chat integrity checksum 蝨ｨ豁｣蟶ｸ螳梧・譎ょｯｫ蜈･縲∝惠荳ｭ豁｢譎ゆｸ肴ｱ｡譟・- 髟ｷ譎る俣辟｡霈ｸ蜃ｺ譎・heartbeat 蜿ｯ邯ｭ謖・｣邱・- client 譁ｷ邱壽凾 `ignore_user_abort(true)` + `connection_aborted()` 霍ｯ蠕大庄豁｣蟶ｸ荳ｭ豁｢荳頑ｸｸ荳ｲ豬・
## 逶ｸ螳ｹ諤ｧ貂ｬ隧ｦ

- 荳肴髪謠ｴ streaming 逧・provider 閾ｪ蜍・fallback 蜷梧ｭ･豬∫ｨ・- 蜴滓怏 `chat/user` JSON API 螳悟・荳榊女蠖ｱ髻ｿ
- 蜑咲ｫｯ謇灘桁蠕梧ｭ｣蟶ｸ・・npm run build`・・- Proxy 迺ｰ蠅・buffering 貂ｬ隧ｦ・井ｾ句ｦ・Nginx + FastCGI・会ｼ檎｢ｺ隱埼撼蜒・悽蝨ｰ XAMPP 譛画譜

---

## 蟒ｺ隴ｰ蟇ｦ菴憺・ｺ擾ｼ亥ｯｦ蜍呻ｼ・
1. **蜈亥ｮ梧・ Phase 0・域歓髮｢蜈ｱ逕ｨ蜑咲ｽｮ驍剰ｼｯ・・*・御ｽ懃ぜ SSE 蟆主・蜈域ｱｺ譴昜ｻｶ
2. 螳梧・ SSE helper + `/chat/user-stream` 遨ｺ谿ｼ・亥屓 `start` / `done`・・3. 蜑咲ｫｯ SSE reader 荳ｲ襍ｷ萓・ｼ亥・逕ｨ蛛・ｳ・侭鬩苓ｭ・parser/UI・・4. 謗･ `Ollama` streaming・域悽蝨ｰ譏灘渚隕・ｸｬ隧ｦ・・5. 謗･ `OpenAI` streaming・亥性 `tool_calls` index 邨・｣晢ｼ・6. 陬・nonce event / checksum store / fallback 邏ｰ遽
7. 蜀崎ｩ穂ｼｰ `Claude` / `Gemini`

---

## 鬚ｨ髫ｪ闊・ｳｨ諢丈ｺ矩・
- WordPress REST SSE 謗幃ｻ樊悽險育吻蟾ｲ豎ｺ遲匁治逕ｨ縲慶allback 蜈ｧ逶ｴ謗･霈ｸ蜃ｺ `echo + flush + exit;`縲搾ｼ郁ｩｳ隕・Phase 3・・  - 髴豕ｨ諢剰・ REST lifecycle 逧・ｺ､逡瑚｡檎ぜ・井ｾ句ｦ・`rest_post_dispatch` 荳肴怎謗･謇・nonce refresh・悟屏豁､莉･ `event: nonce` 陬憺ｽ奇ｼ・- `wp_remote_post()` 荳埼←蜷育悄荳ｲ豬・relay・碁怙 low-level cURL 謾ｯ謠ｴ
- 荳榊酔荳ｻ讖溽腸蠅・噪 buffering・・HP output buffering / proxy buffering・牙庄閭ｽ蟆手・縲檎恚莨ｼ SSE 蟇ｦ髫帛ｻｶ驕ｲ謨ｴ蛹・・- 蟾･蜈ｷ蜻ｼ蜿ｫ + 荳ｲ豬∬凶陌慕炊荳咲文・悟ｮｹ譏馴謌・partial JSON / state 豎呎沒

---

## 隲・Claude / Gemini 蜊泌勧蟇ｩ隕也噪驥埼ｻ・
1. 蟾ｲ豎ｺ遲匁治逕ｨ callback 逶ｴ謗･霈ｸ蜃ｺ SSE + `exit;`・瑚ｫ句ｯｩ隕門・邏ｰ遽鬚ｨ髫ｪ・・uffer 貂・炊縲］once event縲∽ｸｭ豁｢陦檎ぜ・・2. WP 迺ｰ蠅・ｸｭ譛遨ｩ螳夂噪 provider streaming transport・・URL callback 險ｭ險茨ｼ・3. 蟾･蜈ｷ蜻ｼ蜿ｫ・・CP・芽・荳ｲ豬∽ｺ倶ｻｶ讓｡蝙区弍蜷ｦ髴隕∵峩邏ｰ邊貞ｺｦ迢諷倶ｺ倶ｻｶ
4. chat integrity checksum 蝨ｨ荳ｲ豬∽ｸｭ譁ｷ譎ら噪譛菴ｳ遲也払・亥ｮ悟・荳榊ｯｫ vs partial 讓呵ｨ假ｼ・5. 蟾ｲ蟆・歓髮｢ `REST/AJAX user_chat` 蜈ｱ逕ｨ蜑咲ｽｮ驍剰ｼｯ謠仙合轤ｺ Phase 0 蜈域ｱｺ譴昜ｻｶ・瑚ｫ句ｯｩ隕門・蛻・ｊ逡梧弍蜷ｦ蜷育炊

---

## 陬懷・・壽悽險育吻逧・嶌螳ｹ諤ｧ逶ｮ讓・
- 荳咲ｴ螢樒樟譛・`POST /mp-ukagaka/v1/chat/user`
- 荳肴隼螢樊里譛牙燕遶ｯ `mpuFetch()` JSON 霍ｯ蠕・- 蜿ｯ騾・provider 貍ｸ騾ｲ髢句福 streaming 閭ｽ蜉・- 闍･荳ｲ豬∽ｸ榊庄逕ｨ・御ｽｿ逕ｨ閠・ｫ秘ｩ鈴蝗樒樟譛牙酔豁･讓｡蠑擾ｼ亥庄謗･蜿暦ｼ・
---

## 蟾ｲ蟇ｦ菴懃ｵ先棡闊・撫鬘御ｿｮ豁｣邏骭・ｼ・026-02-26・・
譛ｬ遽險倬隙 SSE Streaming 蟇ｦ菴懷ｾ檎噪蟇ｦ髫幄誠蝨ｰ邨先棡・御ｻ･蜿顔岼蜑榊ｷｲ雕ｩ驕惹ｸｦ菫ｮ蠕ｩ逧・撫鬘後よ悴萓・凶蜀榊・迴ｾ蝗樊ｭｸ・悟庄蜆ｪ蜈亥ｰ咲・譛ｬ遽縲・
### 蟾ｲ螳梧・・亥ｯｦ菴懆誠蝨ｰ・・
- 譁ｰ蠅・REST SSE 遶ｯ鮟橸ｼ啻POST /mp-ukagaka/v1/chat/user-stream`
- 蠕檎ｫｯ SSE helper 蟾ｲ關ｽ蝨ｰ・亥､壼ｱ､ output buffer 貂・炊縲～ignore_user_abort(true)`縲～set_time_limit(0)`縲～X-Accel-Buffering: no`縲～X-Content-Type-Options: nosniff`・・- Provider streaming 蟾ｲ關ｽ蝨ｰ・育ｬｬ荳髫取ｮｵ・・- OpenAI・售SE chunk relay + `tool_calls` index 邨・｣・- Ollama・哽SON Lines relay + 蟾･蜈ｷ蜻ｼ蜿ｫ霑ｴ蝨域紛蜷・- 蜑咲ｫｯ `fetch + ReadableStream` SSE reader 蟾ｲ關ｽ蝨ｰ・亥性 `lineBuffer` 邏ｯ遨搾ｼ・- SSE 霍ｯ蠕・nonce refresh 蟾ｲ關ｽ蝨ｰ・・event: nonce` + 蜑咲ｫｯ譖ｴ譁ｰ `mpuRestNonce`・・- 蜑咲ｫｯ蟾ｲ謨ｴ蜷・`AbortController` 驕ｿ蜈榊ｹｽ髱郁ｪｪ隧ｱ
- 髱樔ｸｲ豬・Provider・亥ｦ・Claude/Gemini・牙ｷｲ蜿ｯ閾ｪ蜍戊ｵｰ蜷梧ｭ･ fallback・亥燕遶ｯ隶 `mpuPreSettings.streaming_enabled`・・
### 蟾ｲ菫ｮ蠕ｩ蝠城｡鯉ｼ磯㍾鮟樒ｴ骭・ｼ・
#### 1. Streaming 蜿・丙驕ｺ貍擾ｼ・llama endpoint / max_tokens / model・・
- `prepare_user_chat_args()` 蛻晉沿譛ｪ蝗槫さ鬆ょｱ､ `endpoint` / `max_tokens` / `model`
- 蠖ｱ髻ｿ・・- Ollama streaming 豌ｸ驕謇・`localhost`
- `max_tokens` 謗牙屓鬆占ｨｭ `1000`
- SSE `start` event 讓｡蝙句錐蜿ｯ閭ｽ鬘ｯ遉ｺ `unknown`
- 菫ｮ豁｣・壼惠 `prepare_user_chat_args()` 蝗槫さ鬆ょｱ､蜿・丙・御ｾ・streaming provider 闊・SSE `start` 蜈ｱ逕ｨ

#### 2. `/debug_mcp` 謇灘芦 `user-stream` 騾謌・500 Fatal

- 逞・朽・・- `Undefined array key "provider"`
- `get_provider(null)` -> `WP_Error`
- 蜻ｼ蜿ｫ `WP_Error::supports()` fatal
- 譬ｹ蝗・啻user_chat_stream()` 蝨ｨ `/debug_mcp` 迚ｹ谿雁屓蛯ｳ・育┌ `provider`・我ｸ具ｼ御ｻ榊・蛛・provider 讙｢譟･
- 菫ｮ豁｣・・- `user_chat_stream()` 謠仙燕謾疲穐 `is_debug_mcp`・瑚ｽ牙屓蜷梧ｭ･ `user_chat($request)`
- 蠅槫刈 `is_wp_error($provider_instance)` guard

#### 3. Windows/Apache 迺ｰ蠅・ｸ・SSE frame 辟｡豕戊｢ｫ蜑咲ｫｯ豁｣遒ｺ隗｣譫撰ｼ・TTP 200 菴・UI 蜊｡蝨ｨ縲後∴縺｣縺ｨ窶ｦ縲搾ｼ・
- 逞・朽・咼evTools 蜿ｯ逵句芦 `event: delta`/`done`・御ｽ・燕遶ｯ荳崎ｧｸ逋ｼ `onDelta` / `onDone`
- 譬ｹ蝗・壼燕遶ｯ parser 蜿ｪ逕ｨ `split("\\n\\n")`・梧悴陌慕炊 `CRLF`・・\\r\\n\\r\\n`・・- 菫ｮ豁｣・・- frame 蛻・牡謾ｹ轤ｺ `split(/\\r?\\n\\r?\\n/)`
- frame 隗｣譫仙燕蜉 `line = line.replace(/\\r/g, "")`

#### 4. 蜷梧ｭ･ fallback 蛻・髪荳蠎ｦ隶頑・遨ｺ谿ｼ / UX 蝠城｡・
- 髱樔ｸｲ豬・fallback 譖ｾ蜿ｪ蜑ｩ險ｻ隗｣ `.then(...)`・悟ｰ手・荳肴髪謠ｴ streaming 逧・provider 辟｡豕墓ｭ｣蟶ｸ鬘ｯ遉ｺ蝗櫁ｦ・- 菫ｮ豁｣・夊｣懷屓螳梧紛 `.then/.catch/.finally`・・istory 蟇ｫ蜈･縲∝虚逡ｫ縲‘moji縲・険隱､陌慕炊縲ゞI 隗｣骼厄ｼ・- SSE `onDone` 逕ｨ謌ｪ譁ｷ蠕・`data.msg` 驥咲ｹｪ逡ｫ髱｢譛・謌宣聞蝗櫁ｦ・麻霍ｳ
- 菫ｮ豁｣・夊凶荳ｲ豬・℃遞句ｷｲ譛・`fullResponse`・形onDone` 荳榊・驥咲ｹｪ・悟宵蟇ｫ豁ｷ蜿ｲ

#### 5. Chat Integrity Checksum 400・域ｭｷ蜿ｲ鬩苓ｭ牙､ｱ謨暦ｼ臥ｳｻ蛻怜撫鬘・
- 蝠城｡・A・壼┫蟄・checksum 逧・ｭｷ蜿ｲ髟ｷ蠎ｦ闊・燕遶ｯ荳倶ｸ霈ｪ騾∝・逧・ｭｷ蜿ｲ髟ｷ蠎ｦ荳堺ｸ閾ｴ
- 菫ｮ豁｣・壼酔豁･ / 荳ｲ豬∬ｷｯ蠕大惠 `store_history()` 蜑咲ｵｱ荳 `array_slice($next_history, -10)`

- 蝠城｡・B・啻/debug_mcp` 蝗樊㊨譛・｢ｫ蜑咲ｫｯ蟇ｫ蜈･閨雁､ｩ豁ｷ蜿ｲ・御ｽ・debug 蛻・髪譛ｪ蟇ｫ checksum
- 逞・朽・壼濤陦・`/debug_mcp` 蠕鯉ｼ御ｸ倶ｸ蜿･襍ｷ莠貞虚閨雁､ｩ闊・ｸ闊ｬ蟆崎ｩｱ逧・庄閭ｽ陲ｫ豎｡譟難ｼ形user-stream`/`chat/user` 蝗・400
- 菫ｮ豁｣・啻/debug_mcp` 蛻・髪蝗槫さ蜑搾ｼ梧焔蜍募ｰ・debug 蟆崎ｩｱ・・ser + assistant report・牙ｯｫ蜈･ checksum

- 蝠城｡・C・唹llama thinking 蜈ｧ螳ｹ・亥ｦ・`<think>...</think>`・蛾謌仙燕蠕檎ｫｯ蜈ｧ螳ｹ荳堺ｸ閾ｴ
- 逞・朽・夂ｬｬ荳蜿･豁｣蟶ｸ縲∫ｬｬ莠悟唱 checksum mismatch・亥ｰ､蜈ｶ Ollama / MCP / thinking 讓｡蝙具ｼ・- 譬ｹ蝗・壼ｾ檎ｫｯ蟇ｫ蜈･ checksum 逧・・螳ｹ闊・燕遶ｯ鬘ｯ遉ｺ / 荳倶ｸ霈ｪ騾∝屓蜈ｧ螳ｹ蝨ｨ thinking 讓呵ｨ伜ｱ､邏壻ｸ堺ｸ閾ｴ
- 菫ｮ豁｣・壼惠 `user_chat` / `user_chat_stream` 謾ｶ蟆ｾ縲∝ｯｫ蜈･ checksum 蜑搾ｼ瑚凶 provider 轤ｺ Ollama・悟・螂・`mpu_filter_thinking_content()` 蜀榊ｭ・
- 蝠城｡・D・夊ｷｨ Provider 蛻・鋤・亥ｰ､蜈ｶ蛻・芦/蛻・屬 Ollama・牙ｾ悟・迴ｾ checksum 400
- 譬ｹ蝗・壻ｸ榊酔 provider・育音蛻･譏ｯ Ollama・牙ｰ・assistant content 逧・怙邨ょ梛諷倶ｸ榊酔・悟ｰ手・蜷御ｸ `session_id` 荳・checksum 荳堺ｸ閾ｴ
- 菫ｮ豁｣譁ｹ蜷托ｼ亥ｷｲ螳梧・・会ｼ夂ｵｱ荳 checksum 蟇ｫ蜈･蜑咲噪蜈ｧ螳ｹ豁｣隕丞喧豬∫ｨ具ｼ檎｢ｺ菫晏・謠・provider 蠕御ｻ榊庄蟆埼ｽ・
##### 逕溽箸迺ｰ蠅・怙邨ら｢ｺ隱搾ｼ・026-02-27・・
- 蝠城｡・E・壽ｻ大虚遯怜哨騾謌・store / verify 荳榊ｰ咲ｨｱ・磯聞蟆崎ｩｱ隨ｬ 6 霈ｪ蠕碁ｫ俶ｩ溽紫 400・・- 譬ｹ蝗・壼ュ蛛・`array_slice(..., -10)` 譎ゑｼ檎ｪ怜哨蟾ｦ蛛ｴ蛻・脂 `user` 蠕鯉ｼ碁ｦ紋ｽ榊庄閭ｽ隶頑・蟄､遶・`assistant`・幄凶蜆ｲ蟄倡ｫｯ闊・ｩ苓ｭ臥ｫｯ陌慕炊鬆・ｺ丈ｸ榊酔・慶hecksum 蠢・┯荳堺ｸ閾ｴ
- 菫ｮ豁｣・・- 蝨ｨ `includes/llm/chat-integrity.php` 譁ｰ蠅樔ｸｦ邨ｱ荳菴ｿ逕ｨ `mpu_chat_integrity_slice_for_store($history, 10)`・亥・ slice・悟・豁｣隕丞喧遘ｻ髯､蟄､遶・assistant・・- 荳牙句ｯｫ蜈･鮟橸ｼ・user_chat` / `user_chat_stream` / `/debug_mcp`・臥嚀謾ｹ轤ｺ蜈育ｵ・`$raw_history`・悟・蜻ｼ蜿ｫ `mpu_chat_integrity_slice_for_store(..., 10)` 蠕悟ｯｫ蜈･

- 蝠城｡・F・夐聞譁・悽闊・鋤陦悟惠蟄伜叙豬∫ｨ倶ｸｭ逧・ｾｮ蟾ｮ蟆手・ checksum mismatch
- 譬ｹ蝗・啻sanitize_text_field` 譛・｣灘ｹｳ謠幄｡鯉ｼ娥CP/髟ｷ蝗櫁ｦ・ュ蠅・ｸ具ｼ悟燕遶ｯ蝗槫さ蜈ｧ螳ｹ闊・ｾ檎ｫｯ蜆ｲ蟄伜・螳ｹ螳ｹ譏灘・迴ｾ蟄嶺ｸｲ螻､邏壼ｷｮ逡ｰ
- 菫ｮ豁｣・・- 鬩苓ｭ臥ｫｯ豁ｷ蜿ｲ貂・炊邨ｱ荳謾ｹ轤ｺ `sanitize_textarea_field(wp_unslash(...))`
- 蜆ｲ蟄倡ｫｯ assistant 蜈ｧ螳ｹ邨ｱ荳謾ｹ轤ｺ `sanitize_textarea_field(...)`

- 蝠城｡・H・壽崟蝌苓ｩｦ蝨ｨ store 遶ｯ蜉蜈･ `wp_unslash()`・・sanitize_textarea_field(wp_unslash($result))`・・- 鬚ｨ髫ｪ・啻$result` 萓・・ AI API・碁撼 WP magic quotes 霈ｸ蜈･・幃｡榊､・`wp_unslash()` 蜿ｯ閭ｽ蜷・脂蜷域ｳ募渚譁懃ｷ夲ｼ育ｨ句ｼ冗｢ｼ/豁｣蜑・霍ｯ蠕托ｼ会ｼ御ｸｦ闊・`done` event 鬘ｯ遉ｺ蜈ｧ螳ｹ逕｢逕滓眠荳堺ｸ閾ｴ
- 譛邨よｱｺ遲厄ｼ・026-02-27・会ｼ壽彫蝗・store 遶ｯ `wp_unslash()`・帛ュ verify 遶ｯ菫晉蕗 `wp_unslash()`・亥屏轤ｺ verify 隶逧・弍 request POST payload・・
- 蝠城｡・I・售SE/蜷梧ｭ･骭ｯ隱､謌紋ｸｭ豁｢譎ゑｼ悟燕遶ｯ蟾ｲ push 逧・user 險頑・譛ｪ蝗樊ｻｾ
- 鬚ｨ髫ｪ・壼燕遶ｯ豁ｷ蜿ｲ谿倡蕗縲檎┌蟆肴㊨ assistant縲咲噪 user 險頑・・御ｸ倶ｸ霈ｪ蜿ｯ閭ｽ隗ｸ逋ｼ checksum mismatch
- 譛邨ゆｿｮ豁｣・井ｿ晉蕗・会ｼ壼燕遶ｯ `onError` / `onAbort` / 蜷梧ｭ･ `.catch()` 驛ｽ譛・屓貊ｾ譛ｫ蟆ｾ user 荳ｦ `saveChatHistory()`

- 蝠城｡・G・夐｣邱壻ｸｭ豁｢蠕御ｻ榊ｯｫ蜈･ checksum・梧ｱ｡譟謎ｸ倶ｸ霈ｪ鬩苓ｭ・- 菫ｮ豁｣・壻ｸ牙・checksum 蟇ｫ蜈･鮟樒嚀蜉蜈･ `!connection_aborted()` 髦ｲ隴ｷ

- 鬩玲噺邨先棡・・- `php -l includes/llm/chat-integrity.php` 騾夐℃
- `php -l includes/rest/class-mpu-rest-chat.php` 騾夐℃
- `user-stream` 200・・text/event-stream`・芽・ 400・・application/json`・臥ぜ蝗樊㊨蝙区・蟾ｮ逡ｰ・悟ｷｲ遒ｺ隱肴ｸ蠢・撫鬘悟惠 checksum 蟆埼ｽ企ｏ霈ｯ・碁撼 Cloudflare header 陦檎ぜ譛ｬ霄ｫ

#### 6. MCP 蟾･蜈ｷ蝓ｷ陦檎朽諷玖ｨ頑・隱櫁ｨ荳榊酔豁･・域ｨ｡蝙句屓譌･譁・∫朽諷区署遉ｺ鬘ｯ遉ｺ荳ｭ譁・ｼ・
- 譬ｹ蝗・壼ｾ檎ｫｯ `$emit('status', ...)` 菴ｿ逕ｨ遑ｬ邱ｨ遒ｼ荳ｭ譁・ｨ頑・
- 菫ｮ豁｣・・- 蠕檎ｫｯ・・penAI/Ollama・画隼轤ｺ machine-readable status payload・・type: "executing_tool"`, `tool: "..."`・・- 蜑咲ｫｯ `onStatus` 萓・`type` 菴ｿ逕ｨ `mpuL10n.executingTool` 讓｡譚ｿ譬ｼ蠑丞喧
- `frontend-functions.php` 譁ｰ蠅・`mpuL10n.executingTool`・郁ｵｰ WP i18n・・- 菫晉蕗 `data.message` / `executing_tool` 菴懃嶌螳ｹ fallback

#### 7. `/debug_mcp` 邯・`user-stream` 蝗槫さ JSON 200・御ｽ・燕遶ｯ莉堺ｻ･ SSE parser 陌慕炊・育ｴ・ｭ・骭ｯ隱､ UI・・
- 逞・朽・啻/debug_mcp` 蠕檎ｫｯ蟾ｲ豁｣遒ｺ蝗・`application/json` 闊・ｨｺ譁ｷ蜈ｧ螳ｹ・御ｽ・燕遶ｯ莉埼｡ｯ遉ｺ骭ｯ隱､讓｣蠑乗・譛ｪ豁｣遒ｺ螳梧・豬∫ｨ・- 譬ｹ蝗・啻mpuFetchSSE()` 鬆先悄 `text/event-stream`・梧悴陌慕炊 `application/json` fallback
- 菫ｮ豁｣・壼惠 `mpuFetchSSE()` 蜈域ｪ｢譟･ `content-type`
- 闍･轤ｺ `application/json` 荳・`response.ok`・檎峩謗･ `onDone(json)`
- 闍･轤ｺ `application/json` 荳秘撼 200・檎峩謗･ `onError(json)`

#### 8. SSE 骭ｯ隱､霍ｯ蠕鷹㍾隍・ｧｸ逋ｼ `onError`・亥燕遶ｯ・・
- 逞・朽・壻ｸｲ豬・比ｸｭ逋ｼ逕・`event: error` 譎ゑｼ形onError` 陲ｫ蜻ｼ蜿ｫ蜈ｩ谺｡・碁険隱､蜍慕吻/Log 逍雁刈
- 譬ｹ蝗・啻case "error"` 蜈亥他蜿ｫ `onError` 蠕悟処 `throw`・瑚｢ｫ螟門ｱ､ `catch` 蜀肴ｬ｡蜻ｼ蜿ｫ `onError`
- 菫ｮ豁｣・啻case "error"` 謾ｹ轤ｺ蜻ｼ蜿ｫ `onError` 蠕檎峩謗･ `return`・御ｸ榊・ `throw`

#### 9. Ollama provider 闊・REST handler 驥崎､・∝・ SSE `error` event

- 逞・朽・唹llama 荳ｲ豬・険隱､譎ゑｼ継rovider 闊・`user_chat_stream()` 螟門ｱ､驛ｽ騾・`event: error`・悟燕遶ｯ骭ｯ隱､陌慕炊蜿ｯ閭ｽ蛟榊｢・- 譬ｹ蝗・嗔rovider 螻､ `emit('error', ...)` 蠕御ｻ榊屓蛯ｳ `WP_Error`・悟､門ｱ､ handler 蜀埼∽ｸ谺｡ `error` event
- 菫ｮ豁｣・夂ｵｱ荳骭ｯ隱､莠倶ｻｶ逋ｼ騾∬ｲｬ莉ｻ蛻ｰ `user_chat_stream()` 螟門ｱ､
- Ollama provider 骭ｯ隱､譎ょュ `return WP_Error`・御ｸ崎・陦・`emit('error', ...)`

#### 10. `prepare_user_chat_args()` 闊・`rate_limit()` 蝗槫さ蝙句挨莠､逡鯉ｼ・WP_REST_Response`・・
- 逞・朽・夊凶 `rate_limit()` 蜻ｽ荳ｭ蝗槫さ `WP_REST_Response`・悟他蜿ｫ遶ｯ闍･隱､逡ｶ髯｣蛻嶺ｽｿ逕ｨ・悟庄閭ｽ蟆手・蠕檎ｺ碁険隱､
- 菫ｮ豁｣・・- `prepare_user_chat_args()` 蜈ｧ莉･ `if ($rl !== null) return $rl;` 譏守｢ｺ霓牙さ
- `user_chat()` / `user_chat_stream()` 蜈･蜿｣蜷梧凾讙｢譟･ `WP_REST_Response` 闊・`WP_Error`

### 蟇ｦ蜍咎ｩ苓ｭ臥ｶ馴ｩ暦ｼ亥庄萓帛ｾ檎ｺ梧賜譟･・・
- 闍･ `user-stream` 譏ｯ HTTP 200 荳・body 荳ｭ蜿ｯ逵句芦 `event: delta`/`event: done`・御ｽ・UI 荳肴峩譁ｰ・壼━蜈域ｪ｢譟･蜑咲ｫｯ SSE parser・亥・髫皮ｬｦ / CRLF・・- 闍･ `/debug_mcp` 蠕御ｸ倶ｸ蜿･髢句ｧ区園譛芽♀螟ｩ驛ｽ 400・壼━蜈域ｪ｢譟･ debug 蛻・髪譏ｯ蜷ｦ譛牙酔豁･蟇ｫ蜈･ checksum
- 闍･蜒・Ollama 謌門・謠帛芦/髮｢髢・Ollama 蠕悟ｮｹ譏・400・壼━蜈域ｪ｢譟･ thinking content 豁｣隕丞喧闊・checksum 蟇ｫ蜈･蜈ｧ螳ｹ譏ｯ蜷ｦ蟆埼ｽ・- 闍･蜃ｺ迴ｾ `Undefined array key "provider"` 謌・`WP_Error::supports()`・壼━蜈域ｪ｢譟･ `user_chat_stream()` 譏ｯ蜷ｦ蜈域粕謌ｪ debug 蛻・髪闊・`is_wp_error($provider_instance)` guard

### 蟆夐怙謖∫ｺ瑚ｧ蟇滂ｼ域悴萓・庄閭ｽ蜀崎ｸｩ蛻ｰ・・
- Ollama 荳榊酔讓｡蝙狗噪蟾･蜈ｷ蜻ｼ蜿ｫ遨ｩ螳壽ｧ・域怏莠帶ｨ｡蝙句庄閭ｽ霈ｸ蜃ｺ譁・ｭ怜ｼ丞ｷ･蜈ｷ隱樊ｳ包ｼ御ｾ句ｦ・`::invoke ...`・瑚碁撼邨先ｧ句喧 `tool_calls`・・- 莉｣逅・腸蠅・ｼ・ginx + FastCGI・叡uffering 蟆・SSE 逵滉ｸｲ豬・ｫ秘ｩ礼噪蠖ｱ髻ｿ
- 髟ｷ譎る俣蟾･菴憺嚴谿ｵ荳・nonce refresh 闊・ｸｭ譁ｷ驥埼｣陦檎ぜ
- 螟夊ｼｪ蟆崎ｩｱ + MCP + Provider 蛻・鋤豺ｷ蜷域ュ蠅・噪 checksum 荳閾ｴ諤ｧ

### 邯ｭ隴ｷ閠・ｙ蠢假ｼ磯∩蜈埼㍾雹郁ｦ・ｽ搾ｼ・
- 豸牙所 `class-mpu-rest-chat.php` / `ukagaka-chat.js` 逧・ｿｮ陬懆ｫ句━蜈井ｽｿ逕ｨ邊ｾ貅・patch・碁∩蜈肴紛讙秘㍾蟇ｫ騾謌千ｷｨ遒ｼ謌紋ｺら｢ｼ鬚ｨ髫ｪ
- 豈乗ｬ｡隱ｿ謨ｴ蠕瑚・蟆大濤陦鯉ｼ・- `php -l includes/rest/class-mpu-rest-chat.php`
- `npm run build`・域峩譁ｰ `js/dist/ukagaka-bundle*.js`・・- 闍･蜀榊・迴ｾ `蟆崎ｩｱ豁ｷ蜿ｲ鬩苓ｭ牙､ｱ謨輿・・00・会ｼ悟━蜈域衍逵・`MPU Chat Integrity` debug log 逧・`expected/actual/history_count`

---

## 2026-02-27 決策更新：Checksum 改為觀測模式（治本）

- 背景：
  - SSE Streaming 上線後，`chat history checksum` 在多種邊界情境（分段、fallback、中斷、工具呼叫、thinking）容易出現「理論上不一致，但不代表攻擊」的誤判。
  - 既有硬性策略為：mismatch -> `WP_Error(status=400)` -> REST fail，造成使用體驗受損。

- 新策略（已實作）：
  - mismatch -> 記錄 `[WARN]` log -> `return null`（與「沒有 transient」同級處理）-> 請求繼續。
  - 對齊 -> `return true`，請求繼續。
  - 沒有 transient -> `return null`，請求繼續。

- 影響：
  - checksum 從「阻斷機制」調整為「稽核/觀測機制」。
  - 目標是優先穩定 SSE 體驗，降低誤判造成的 400。

- 回滾方式（保留）：
  - 若未來要恢復硬性阻斷，只要把 mismatch 分支改回 `return new WP_Error(...)` 即可，REST 入口邏輯無需調整。