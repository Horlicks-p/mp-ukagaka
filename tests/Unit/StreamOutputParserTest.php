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

    private function collect(array $chunks): array {
        $parser = new MPU_Stream_Output_Parser(null, 'chat');
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

        $this->assertSame(['status', 'think', 'status', 'delta'], $event_names);
        $this->assertSame('thinking_start', $events[0]['data']['type']);
        $this->assertSame('inner', $events[1]['data']['text']);
        $this->assertSame('thinking_end', $events[2]['data']['type']);
        $this->assertSame('visible', $events[3]['data']['text']);
    }

    public function test_markdown_link_is_not_buffered_as_emotion_tag(): void {
        $events = $this->collect(['[link', '](https://example.test)']);
        $text = implode('', array_map(static function ($event) {
            return $event['event'] === 'delta' ? $event['data']['text'] : '';
        }, $events));

        $this->assertSame('[link](https://example.test)', $text);
        $this->assertNotContains('emotion', array_column($events, 'event'));
    }

    public function test_unclosed_think_flushes_final_think(): void {
        $events = $this->collect(['<think>unfinished']);

        $this->assertSame(['status', 'think', 'status'], array_column($events, 'event'));
        $this->assertSame('unfinished', $events[1]['data']['text']);
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
