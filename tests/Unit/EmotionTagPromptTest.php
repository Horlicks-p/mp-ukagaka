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

        $this->assertStringContainsString('## 表情標籤（Expression Tags）', $prompt);
        $this->assertStringContainsString('[laugh]', $prompt);
        $this->assertStringContainsString('[thinking]', $prompt);
        $this->assertStringContainsString('[sigh]', $prompt);
        $this->assertStringContainsString('每次回覆只使用一個表情標籤', $prompt);
        $this->assertStringContainsString('標籤不會被朗讀', $prompt);
        $this->assertStringNotContainsString('回覆末尾添加 [表情:', $prompt);
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
        $this->assertStringContainsString('[laugh]', implode("\n", $data['_meta']['expression_tag_examples']));
        $this->assertStringContainsString('at most one supported [tag]', $data['_meta']['expression_tag_policy']);
    }
}
