<?php

use PHPUnit\Framework\TestCase;

require_once MPU_TESTS_ROOT . '/includes/llm/prompt-categories.php';

final class PromptCategoriesTest extends TestCase {
    public function test_reaction_and_metadata_categories_are_not_spontaneous(): void {
        // 通常の自発対話カテゴリは候選に残る。
        $this->assertTrue(mpu_is_spontaneous_prompt_category('casual'));
        $this->assertTrue(mpu_is_spontaneous_prompt_category('greeting'));

        // 反応専用カテゴリ（touch_* / give_*）は除外される。
        $this->assertFalse(mpu_is_spontaneous_prompt_category('touch_head'));
        $this->assertFalse(mpu_is_spontaneous_prompt_category('give_food'));
        $this->assertFalse(mpu_is_spontaneous_prompt_category('give_favorite'));

        // metadata key（_*）と空文字も除外される。
        $this->assertFalse(mpu_is_spontaneous_prompt_category('_comment'));
        $this->assertFalse(mpu_is_spontaneous_prompt_category('_comment_give'));
        $this->assertFalse(mpu_is_spontaneous_prompt_category(''));
    }

    public function test_real_prompts_json_excludes_give_categories_from_candidates(): void {
        $prompts = json_decode(
            (string) file_get_contents(MPU_TESTS_ROOT . '/ghost/Frieren/prompts.json'),
            true
        );
        $this->assertIsArray($prompts);

        // give_* が実際に存在することを先に確認（テストが空振りしないように）。
        $this->assertArrayHasKey('give_food', $prompts);
        $this->assertArrayHasKey('give_favorite', $prompts);

        $candidates = array_filter(
            array_keys($prompts),
            'mpu_is_spontaneous_prompt_category'
        );

        foreach ($candidates as $key) {
            $this->assertStringStartsNotWith('give_', $key);
            $this->assertStringStartsNotWith('touch_', $key);
            $this->assertStringStartsNotWith('_', $key);
        }
    }
}
