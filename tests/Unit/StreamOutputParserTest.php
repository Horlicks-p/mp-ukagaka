<?php
/**
 * Unit tests for the SSE stream output parser.
 */

use PHPUnit\Framework\TestCase;

if (!function_exists('mpu_load_personality_emoji_config')) {
    function mpu_load_personality_emoji_config($personality_id = null) {
        return ['supported' => ['laugh', 'thinking', 'angry']];
    }
}

if (!function_exists('mpu_get_option')) {
    function mpu_get_option() {
        return $GLOBALS['_mpu_test_mpu_opt'] ?? [];
    }
}

if (!function_exists('mpu_load_personality_manifest')) {
    function mpu_load_personality_manifest($personality_id = null) {
        return $GLOBALS['_mpu_test_manifest'] ?? [];
    }
}

if (!function_exists('mpu_debug_log')) {
    function mpu_debug_log($message) {
        $GLOBALS['_mpu_test_stream_warns'][] = $message;
    }
}

require_once MPU_TESTS_ROOT . '/includes/llm/response-normalizer.php';
require_once MPU_TESTS_ROOT . '/includes/llm/class-mpu-stream-output-parser.php';

final class StreamOutputParserTest extends TestCase {
    protected function setUp(): void {
        $GLOBALS['_mpu_test_mpu_opt'] = [];
        $GLOBALS['_mpu_test_manifest'] = [];
        $GLOBALS['_mpu_test_stream_warns'] = [];
        $GLOBALS['_mpu_test_normalizer_warns'] = [];
    }

    private function collect(array $chunks, bool $strip_unknown_tags = false): array {
        $parser = new MPU_Stream_Output_Parser(null, 'chat', $strip_unknown_tags);
        $events = [];

        foreach ($chunks as $chunk) {
            $events = array_merge($events, $parser->feed($chunk));
        }

        return array_merge($events, $parser->flush());
    }

    private function warnings(): array {
        return array_merge(
            $GLOBALS['_mpu_test_stream_warns'] ?? [],
            $GLOBALS['_mpu_test_normalizer_warns'] ?? []
        );
    }

    public function test_emotion_tag_split_across_chunks_emits_event_without_delta_text(): void {
        $events = $this->collect(['[la', 'ugh] hello']);

        $this->assertSame('emotion', $events[0]['event']);
        $this->assertSame('laugh', $events[0]['data']['tag']);
        $this->assertSame('laugh.png', $events[0]['data']['file']);
        $this->assertSame('delta', $events[1]['event']);
        $this->assertSame(' hello', $events[1]['data']['text']);
    }

    public function test_think_open_and_close_split_across_chunks(): void {
        $events = $this->collect(['<thi', 'nk>inner</thi', 'nk>visible']);
        $event_names = array_column($events, 'event');

        $this->assertSame(['status', 'think_delta', 'think', 'status', 'delta'], $event_names);
        $this->assertSame('thinking_start', $events[0]['data']['type']);
        $this->assertSame('inner', $events[1]['data']['text']);
        $this->assertSame('inner', $events[2]['data']['text']);
        $this->assertSame('thinking_end', $events[3]['data']['type']);
        $this->assertSame('visible', $events[4]['data']['text']);
    }

    public function test_markdown_link_is_not_buffered_as_emotion_tag(): void {
        $events = $this->collect(['[link', '](https://example.test)']);
        $text = implode('', array_map(static function ($event) {
            return $event['event'] === 'delta' ? $event['data']['text'] : '';
        }, $events));

        $this->assertSame('[link](https://example.test)', $text);
        $this->assertNotContains('emotion', array_column($events, 'event'));
    }

    private function deltaText(array $events): string {
        return implode('', array_map(static function ($event) {
            return $event['event'] === 'delta' ? $event['data']['text'] : '';
        }, $events));
    }

    public function test_unknown_lowercase_tag_split_across_chunks_is_stripped(): void {
        // gpt-6-luna 實際輸出：清單外的 [smile] 曾直接顯示在對話框
        $events = $this->collect(['いいね。私も行きたいな。[smi', 'le]'], true);

        $this->assertSame('いいね。私も行きたいな。', $this->deltaText($events));
        $this->assertNotContains('emotion', array_column($events, 'event'));
        $this->assertNotEmpty($this->warnings());
    }

    public function test_unknown_lowercase_tag_mid_text_is_stripped(): void {
        $events = $this->collect(['A[smi', 'le]B'], true);

        $this->assertSame('AB', $this->deltaText($events));
    }

    public function test_strip_keeps_capitalized_brackets_and_markdown_links(): void {
        $events = $this->collect(['[WordPress] と [TODO]、[smi', 'le', '](https://example.test)'], true);

        $this->assertSame('[WordPress] と [TODO]、[smile](https://example.test)', $this->deltaText($events));
    }

    public function test_strip_keeps_identifiers_and_loose_spelling(): void {
        $events = $this->collect(['[happy_', 'path] [joy_division] [soft_smi', 'le] [smile.] [ smile ]'], true);

        $this->assertSame('[happy_path] [joy_division]  [smile.] [ smile ]', $this->deltaText($events));
    }

    public function test_strip_keeps_ordinary_bracket_text(): void {
        $events = $this->collect(['Use [gal', 'lery] here; range [a-z]; - [x', '] done'], true);

        $this->assertSame('Use [gallery] here; range [a-z]; - [x] done', $this->deltaText($events));
    }

    /** @dataProvider nestedBracketProvider */
    public function test_nested_brackets_match_normalizer(array $chunks): void {
        // 雙層 / 巢狀括號：畫面必須與 normalizer 的 display_text（history）一致
        $text   = implode('', $chunks);
        $events = $this->collect($chunks, true);
        $r      = mpu_normalize_ai_response($text, null, ['strip_unknown_tags' => true]);

        $this->assertSame($r['display_text'], trim($this->deltaText($events)), $text);
        $this->assertSame($r['emotion_tags'], array_column(array_column(array_filter($events, static function ($event) {
            return $event['event'] === 'emotion';
        }), 'data'), 'tag'), $text);
    }

    public function nestedBracketProvider(): array {
        return [
            'double unknown'      => [['A[[smile]]B']],
            'double split'        => [['A[[smi', 'le]]B']],
            'double supported'    => [['A[[laugh]]B']],
            'prefixed inner'      => [['A[x[smile]]B']],
            'unbalanced open'     => [['A[[smile]B']],
            'double before link'  => [['[[smile]](https://example.test)']],
        ];
    }

    public function test_unknown_tag_kept_when_strip_disabled(): void {
        $events = $this->collect(['ok[smile]']);

        $this->assertSame('ok[smile]', $this->deltaText($events));
    }

    public function test_unclosed_think_flushes_final_think(): void {
        $events = $this->collect(['<think>unfinished']);

        $this->assertSame(['status', 'think_delta', 'think', 'status'], array_column($events, 'event'));
        $this->assertSame('unfinished', $events[1]['data']['text']);
        $this->assertSame('unfinished', $events[2]['data']['text']);
    }

    public function test_mid_response_think_is_stripped_and_warned(): void {
        $events = $this->collect(['hello <think>hidden</think> world']);
        $text = implode('', array_map(static function ($event) {
            return $event['event'] === 'delta' ? $event['data']['text'] : '';
        }, $events));

        $this->assertSame('hello  world', $text);
        $this->assertFalse(in_array('think', array_column($events, 'event'), true));
        $this->assertNotEmpty($this->warnings());
    }

    public function test_context_disabled_suppresses_think_events_but_keeps_display_clean(): void {
        $parser = new MPU_Stream_Output_Parser(null, 'decoration');
        $events = array_merge(
            $parser->feed('<think>inner</think>visible'),
            $parser->flush()
        );

        $this->assertSame(['delta'], array_column($events, 'event'));
        $this->assertSame('visible', $events[0]['data']['text']);
        $this->assertNotEmpty($this->warnings());
    }
}
