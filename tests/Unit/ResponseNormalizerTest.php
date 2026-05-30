<?php
/**
 * M1a 契約測試 — mpu_normalize_ai_response()（§13.2 / §13.6）.
 *
 * 以最小 stub 取代 WP / personality 依賴，驗證 Response Normalizer 的非串流契約。
 * tests/ 不在 PHPCS 掃描範圍，故沿用既有 Unit 測試的寬鬆風格。
 */

use PHPUnit\Framework\TestCase;

// --- 依賴 stub（bootstrap 載入的檔案皆未定義這些，無衝突）---------------------
if (!function_exists('mpu_filter_thinking_content')) {
    /**
     * 忠實複製 includes/llm/ai-functions.php 的同名函式，確保 display 剝離行為
     * 與 production 一致（normalizer 委派此函式）。
     */
    function mpu_filter_thinking_content($response) {
        if (empty($response) || !is_string($response)) {
            return $response;
        }
        $complete_tags = ['think', 'thinking', 'reflection', 'chain_of_thought', 'reasoning', 'inner_monologue', 'redacted_reasoning'];
        foreach ($complete_tags as $tag) {
            $response = preg_replace('/<' . $tag . '>.*?<\/' . $tag . '>/is', '', $response);
        }
        $incomplete_tags = ['think', 'thinking', 'reflection', 'chain_of_thought', 'reasoning', 'inner_monologue'];
        foreach ($incomplete_tags as $tag) {
            $response = preg_replace('/<' . $tag . '>.*$/is', '', $response);
        }
        $response = preg_replace('/\<\|[a-z_]+\|\>/i', '', $response);
        $response = preg_replace('/\n\s*\n\s*\n/s', "\n\n", $response);
        return trim($response);
    }
}

if (!function_exists('mpu_load_personality_emoji_config')) {
    function mpu_load_personality_emoji_config($personality_id = null) {
        return ['supported' => $GLOBALS['_mpu_test_emoji_supported'] ?? ['laugh', 'angry', 'happy', 'sad', 'surprised']];
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

if (!function_exists('mpu_analyze_emoji_from_text')) {
    function mpu_analyze_emoji_from_text($text, $personality_id = null) {
        return strpos($text, 'angry keyword') !== false ? 'angry.png' : null;
    }
}

if (!function_exists('mpu_debug_log')) {
    function mpu_debug_log($message) {
        $GLOBALS['_mpu_test_normalizer_warns'][] = $message;
    }
}

require_once MPU_TESTS_ROOT . '/includes/llm/response-normalizer.php';

final class ResponseNormalizerTest extends TestCase {
    protected function setUp(): void {
        $GLOBALS['_mpu_test_emoji_supported'] = ['laugh', 'angry', 'happy', 'sad', 'surprised'];
        $GLOBALS['_mpu_test_mpu_opt'] = [];
        $GLOBALS['_mpu_test_manifest'] = [];
        $GLOBALS['_mpu_test_normalizer_warns'] = [];
    }

    protected function tearDown(): void {
        $GLOBALS['_mpu_test_mpu_opt'] = [];
        $GLOBALS['_mpu_test_manifest'] = [];
        $GLOBALS['_mpu_test_normalizer_warns'] = [];
    }

    /** 契約鎖死：display === history === checksum === tts. */
    private function assertLocked(array $r): void {
        $this->assertSame($r['display_text'], $r['history_text']);
        $this->assertSame($r['history_text'], $r['checksum_text']);
        $this->assertSame($r['display_text'], $r['tts_text']);
    }

    /** primary_emotion_tag / file 同步存在或同步 null. */
    private function assertPrimarySync(array $r): void {
        $both_null = null === $r['primary_emotion_tag'] && null === $r['primary_emotion_file'];
        $both_set  = null !== $r['primary_emotion_tag'] && null !== $r['primary_emotion_file'];
        $this->assertTrue($both_null || $both_set, 'primary_emotion_tag/file 必須同步');
    }

    private function assertWarnContains(string $needle): void {
        $hit = false;
        foreach ($GLOBALS['_mpu_test_normalizer_warns'] as $w) {
            if (strpos($w, $needle) !== false) {
                $hit = true;
            }
        }
        $this->assertTrue($hit, "預期 warning 含「{$needle}」");
    }

    public function test_plain_text_no_tags(): void {
        $r = mpu_normalize_ai_response('こんにちは、いい天気だね。');
        $this->assertSame('こんにちは、いい天気だね。', $r['display_text']);
        $this->assertSame([], $r['emotion_tags']);
        $this->assertSame([], $r['emotion_files']);
        $this->assertSame('', $r['think']);
        $this->assertNull($r['primary_emotion_tag']);
        $this->assertNull($r['primary_emotion_file']);
        $this->assertPrimarySync($r);
        $this->assertLocked($r);
    }

    public function test_single_legal_tag(): void {
        $r = mpu_normalize_ai_response('うれしいな [laugh]');
        $this->assertStringNotContainsString('[laugh]', $r['display_text']);
        $this->assertSame('うれしいな', $r['display_text']);
        $this->assertSame(['laugh'], $r['emotion_tags']);
        $this->assertSame(['laugh.png'], $r['emotion_files']);
        $this->assertSame('laugh', $r['primary_emotion_tag']);
        $this->assertSame('laugh.png', $r['primary_emotion_file']);
        $this->assertLocked($r);
    }

    public function test_multiple_tags_primary_is_first(): void {
        $r = mpu_normalize_ai_response('[laugh] あはは [angry] むっ');
        $this->assertSame(['laugh', 'angry'], $r['emotion_tags']);
        $this->assertSame(['laugh.png', 'angry.png'], $r['emotion_files']);
        $this->assertSame('laugh', $r['primary_emotion_tag']);
        $this->assertSame('laugh.png', $r['primary_emotion_file']);
        $this->assertStringNotContainsString('[laugh]', $r['display_text']);
        $this->assertStringNotContainsString('[angry]', $r['display_text']);
        $this->assertLocked($r);
    }

    public function test_embedded_tag_keeps_surrounding_text(): void {
        $r = mpu_normalize_ai_response('そう [laugh] だね');
        $this->assertStringNotContainsString('[laugh]', $r['display_text']);
        $this->assertStringContainsString('そう', $r['display_text']);
        $this->assertStringContainsString('だね', $r['display_text']);
        $this->assertSame('laugh.png', $r['primary_emotion_file']);
        $this->assertLocked($r);
    }

    /** @dataProvider unknownTagProvider */
    public function test_unknown_tags_are_kept(string $unknown): void {
        $r = mpu_normalize_ai_response("これは {$unknown} だよ");
        $this->assertStringContainsString($unknown, $r['display_text']);
        $this->assertSame([], $r['emotion_tags']);
        $this->assertPrimarySync($r);
    }

    public function unknownTagProvider(): array {
        return [
            'evil'      => ['[evil]'],
            'WordPress' => ['[WordPress]'],
            'TODO'      => ['[TODO]'],
            'single'    => ['[A]'],
        ];
    }

    public function test_legacy_explicit_emotion_compat(): void {
        $r = mpu_normalize_ai_response('わかった [表情: happy]');
        $this->assertStringNotContainsString('表情', $r['display_text']);
        $this->assertSame(['happy'], $r['emotion_tags']);
        $this->assertSame('happy.png', $r['primary_emotion_file']);
        $this->assertLocked($r);

        $paren = mpu_normalize_ai_response('test (emotion: sad)');
        $this->assertSame('sad', $paren['primary_emotion_tag']);

        $unknown = mpu_normalize_ai_response('foo [表情: nonexist]');
        $this->assertSame([], $unknown['emotion_tags']);
        $this->assertStringContainsString('nonexist', $unknown['display_text']);
    }

    public function test_markdown_link_preserved(): void {
        $r = mpu_normalize_ai_response('詳細は [docs](https://example.com) を参照');
        $this->assertStringContainsString('[docs](https://example.com)', $r['display_text']);
        $this->assertSame([], $r['emotion_tags']);

        // link text 即使是 supported tag 名，negative lookahead 仍保護.
        $r2 = mpu_normalize_ai_response('[laugh](https://x.com)');
        $this->assertStringContainsString('[laugh](https://x.com)', $r2['display_text']);
        $this->assertSame([], $r2['emotion_tags']);
    }

    public function test_unclosed_think_captured(): void {
        $r = mpu_normalize_ai_response('<think>途中で切れた思考');
        $this->assertSame('', $r['display_text']);
        $this->assertSame('途中で切れた思考', $r['think']);
        $this->assertLocked($r);
    }

    public function test_multiple_think_keeps_first(): void {
        $r = mpu_normalize_ai_response('<think>最初の思考</think>表面の返事<think>二番目</think>');
        $this->assertSame('最初の思考', $r['think']);
        $this->assertSame('表面の返事', $r['display_text']);
        $this->assertWarnContains('multiple think');
        $this->assertLocked($r);
    }

    public function test_mid_response_think_not_rendered(): void {
        $r = mpu_normalize_ai_response('こんにちは<think>これは内心</think>');
        $this->assertSame('', $r['think']);
        $this->assertSame('こんにちは', $r['display_text']);
        $this->assertWarnContains('mid-response');
        $this->assertLocked($r);
    }

    public function test_think_and_tag_mixed(): void {
        $r = mpu_normalize_ai_response('<think>笑っておこう</think>あはは [laugh]');
        $this->assertSame('笑っておこう', $r['think']);
        $this->assertSame('laugh.png', $r['primary_emotion_file']);
        $this->assertStringNotContainsString('[laugh]', $r['display_text']);
        $this->assertStringNotContainsString('think', $r['display_text']);
        $this->assertStringContainsString('あはは', $r['display_text']);
        $this->assertLocked($r);
    }

    public function test_inner_monologue_disabled_empties_think(): void {
        $GLOBALS['_mpu_test_mpu_opt'] = ['enable_inner_monologue' => false];
        $r = mpu_normalize_ai_response('<think>内心</think>表面');
        $this->assertSame('', $r['think']);
        $this->assertSame('表面', $r['display_text']);
    }

    public function test_strip_unknown_tags_option(): void {
        $r = mpu_normalize_ai_response('これは [evil] だ', null, ['strip_unknown_tags' => true]);
        $this->assertStringNotContainsString('[evil]', $r['display_text']);
        $this->assertSame([], $r['emotion_tags']);
    }

    public function test_rest_helper_uses_primary_emotion_before_keyword_scorer(): void {
        $r = mpu_normalize_ai_response_for_rest('[laugh] angry keyword', null, ['context' => 'chat']);
        $this->assertSame('laugh.png', $r['emoji']);
        $this->assertSame('laugh.png', $r['primary_emotion_file']);
        $this->assertSame('angry keyword', $r['display_text']);
    }

    public function test_rest_helper_falls_back_to_keyword_without_tag(): void {
        $r = mpu_normalize_ai_response_for_rest('angry keyword', null, ['context' => 'chat']);
        $this->assertSame('angry.png', $r['emoji']);
        $this->assertNull($r['primary_emotion_file']);
    }

    public function test_context_disabled_suppresses_think_but_keeps_display_clean(): void {
        $r = mpu_normalize_ai_response('<think>inner</think>visible', null, ['context' => 'decoration']);
        $this->assertSame('', $r['think']);
        $this->assertSame('visible', $r['display_text']);
        $this->assertWarnContains('inner monologue suppressed');
    }

    public function test_manifest_context_override_is_partial(): void {
        $GLOBALS['_mpu_test_manifest'] = [
            'features' => [
                'inner_monologue_contexts' => [
                    'touch' => false,
                ],
            ],
        ];

        $this->assertFalse(mpu_is_inner_monologue_enabled_for_context('touch'));
        $this->assertTrue(mpu_is_inner_monologue_enabled_for_context('chat'));
        $this->assertFalse(mpu_is_inner_monologue_enabled_for_context('unknown_context'));
    }

    public function test_rest_display_limit_preserves_locked_text_fields(): void {
        $r = mpu_normalize_ai_response_for_rest('<think>long thought</think>1234567890[laugh]', null, ['context' => 'chat']);
        $r = mpu_normalize_ai_response_apply_display_limit($r, 5);
        $this->assertSame('12345...', $r['display_text']);
        $this->assertSame($r['display_text'], $r['history_text']);
        $this->assertSame($r['history_text'], $r['checksum_text']);
        $this->assertSame('laugh.png', $r['emoji']);
    }
}
