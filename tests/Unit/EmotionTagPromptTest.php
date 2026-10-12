<?php
/**
 * Prompt switch tests for expression tag instructions.
 */

use PHPUnit\Framework\TestCase;

final class EmotionTagPromptTest extends TestCase {
    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_expression_tag_instruction_uses_inline_tag_policy(): void {
        require_once MPU_TESTS_ROOT . '/includes/personality/personality-loader.php';

        $prompt = mpu_build_emotion_tag_instruction(['laugh', 'thinking', 'sigh']);

        $this->assertStringContainsString('## 表情タグ（Expression Tags）', $prompt);
        $this->assertStringContainsString('[laugh]', $prompt);
        $this->assertStringContainsString('[thinking]', $prompt);
        $this->assertStringContainsString('[sigh]', $prompt);
        $this->assertStringContainsString('一回の返信につき表情タグは一つだけ', $prompt);
        $this->assertStringContainsString('タグは読み上げられず', $prompt);
        $this->assertStringNotContainsString('回覆末尾添加 [表情:', $prompt);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_expression_tag_instruction_lists_descriptions(): void {
        require_once MPU_TESTS_ROOT . '/includes/personality/personality-loader.php';

        $prompt = mpu_build_emotion_tag_instruction(
            ['laugh', 'stun', 'vomit'],
            ['laugh' => '口を大きく開けて笑う', 'stun' => '魂が抜けて呆然とする', 'ignored' => 'x'],
            0
        );

        $this->assertStringContainsString("- [laugh]：口を大きく開けて笑う", $prompt);
        $this->assertStringContainsString("- [stun]：魂が抜けて呆然とする", $prompt);
        $this->assertStringContainsString("\n- [vomit]\n", $prompt);
        $this->assertStringNotContainsString('ignored', $prompt);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_expression_tag_examples_rotate_and_are_placement_only(): void {
        require_once MPU_TESTS_ROOT . '/includes/personality/personality-loader.php';

        $tags = ['a', 'b', 'c', 'd', 'e', 'f'];
        $examples = function (string $prompt): array {
            preg_match_all('/^- 「[^」]*\[([a-z]+)\][^」]*」$/mu', $prompt, $m);
            return $m[1];
        };

        $day0 = $examples(mpu_build_emotion_tag_instruction($tags, [], 0));
        $day1 = $examples(mpu_build_emotion_tag_instruction($tags, [], 1));

        $this->assertSame(['a', 'c', 'e'], $day0);
        $this->assertSame(['b', 'd', 'f'], $day1);
        $this->assertSame($day0, $examples(mpu_build_emotion_tag_instruction($tags, [], 6)));
        $this->assertSame(['a', 'a', 'a'], $examples(mpu_build_emotion_tag_instruction(['a'], [], 5)));
        $this->assertStringNotContainsString('見つけた', mpu_build_emotion_tag_instruction($tags, [], 0));
    }

    public function test_frieren_keyword_file_describes_every_supported_tag(): void {
        $manifest = json_decode(file_get_contents(MPU_TESTS_ROOT . '/ghost/Frieren/manifest.json'), true);
        $keywords = json_decode(file_get_contents(MPU_TESTS_ROOT . '/ghost/Frieren/emoji-keywords.json'), true);

        foreach ($manifest['emoji']['supported'] as $tag) {
            $this->assertArrayHasKey($tag, $keywords['mappings'], "missing mapping for {$tag}");
            $this->assertNotEmpty($keywords['mappings'][$tag]['description'] ?? '', "missing description for {$tag}");
        }
    }

    /**
     * Each Frieren keyword on its own must score for its own expression, so a
     * short or shared keyword in another mapping cannot take it over.
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_frieren_keywords_are_not_taken_by_other_expressions(): void {
        $mappings = json_decode(file_get_contents(MPU_TESTS_ROOT . '/ghost/Frieren/emoji-keywords.json'), true)['mappings'];
        $GLOBALS['mpu_test_emoji_mappings'] = $mappings;
        if (!function_exists('mpu_load_personality_emoji_keywords')) {
            function mpu_load_personality_emoji_keywords($personality_id = null) {
                return $GLOBALS['mpu_test_emoji_mappings'];
            }
        }
        require_once MPU_TESTS_ROOT . '/includes/personality/emoji-mapper.php';

        $taken = [];
        foreach ($mappings as $tag => $mapping) {
            foreach ($mapping['keywords'] as $keyword) {
                $winner = mpu_analyze_emoji_from_text($keyword, 'Frieren');
                if ($winner !== $mapping['file']) {
                    $taken[] = "{$tag}: {$keyword} -> {$winner}";
                }
            }
        }

        $this->assertSame([], $taken);
        $this->assertNotSame('stun.png', mpu_analyze_emoji_from_text('請放心，我沒事。', 'Frieren'));
        $this->assertNotSame('vomit.png', mpu_analyze_emoji_from_text('系統廢了，等一下再試。', 'Frieren'));
    }

    /**
     * The generic fallback for characters without emoji-keywords.json follows the same rule.
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_default_keywords_are_not_taken_by_other_expressions(): void {
        if (!function_exists('mpu_load_personality_emoji_keywords')) {
            function mpu_load_personality_emoji_keywords($personality_id = null) {
                return [];
            }
        }
        require_once MPU_TESTS_ROOT . '/includes/personality/emoji-mapper.php';

        $taken = [];
        foreach (mpu_get_default_emoji_mappings() as $tag => $mapping) {
            foreach ($mapping['keywords'] as $keyword) {
                $winner = mpu_analyze_emoji_from_text($keyword, 'NoKeywordFile');
                if ($winner !== $mapping['file']) {
                    $taken[] = "{$tag}: {$keyword} -> {$winner}";
                }
            }
        }

        $this->assertSame([], $taken);
        $this->assertNotSame('stun.png', mpu_analyze_emoji_from_text('請放心，我沒事。', 'NoKeywordFile'));
        $this->assertNotSame('vomit.png', mpu_analyze_emoji_from_text('系統廢了，等一下再試。', 'NoKeywordFile'));
    }

    public function test_expression_tag_instruction_source_removed_legacy_tail_prompt(): void {
        $source = file_get_contents(MPU_TESTS_ROOT . '/includes/personality/personality-loader.php');

        $this->assertStringNotContainsString('回覆末尾添加 [表情:', $source);
    }

    public function test_frieren_manifest_declares_supported_expression_tags(): void {
        $path = MPU_TESTS_ROOT . '/ghost/Frieren/manifest.json';
        $data = json_decode(file_get_contents($path), true);

        $this->assertIsArray($data);
        $this->assertSame(JSON_ERROR_NONE, json_last_error());
        $this->assertContains('laugh', $data['emoji']['supported']);
        $this->assertContains('thinking', $data['emoji']['supported']);
        $this->assertContains('sigh', $data['emoji']['supported']);
    }

    public function test_frieren_keyword_file_contains_prompt_examples(): void {
        $path = MPU_TESTS_ROOT . '/ghost/Frieren/emoji-keywords.json';
        $data = json_decode(file_get_contents($path), true);

        $this->assertIsArray($data);
        $this->assertSame(JSON_ERROR_NONE, json_last_error());
        $this->assertNotEmpty($data['_meta']['expression_tag_examples']);
        $this->assertStringNotContainsString('[laugh]', implode("\n", $data['_meta']['expression_tag_examples']));
        $this->assertStringNotContainsString('[thinking]', implode("\n", $data['_meta']['expression_tag_examples']));
        $this->assertStringNotContainsString('[sigh]', implode("\n", $data['_meta']['expression_tag_examples']));
        $this->assertStringContainsString('at most one supported [tag]', $data['_meta']['expression_tag_policy']);
    }
}
