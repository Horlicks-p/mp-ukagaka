<?php

use PHPUnit\Framework\TestCase;

require_once MPU_TESTS_ROOT . '/includes/llm/prompt-categories.php';

// mpu_build_prompt_categories() を実際に通すための seam。
// 本物の personality-prompts.php はテスト bootstrap に読み込まれないため、
// グローバルから固定カタログを返す stub を定義する。
if (!function_exists('mpu_load_personality_prompts')) {
    function mpu_load_personality_prompts($personality_id = null) {
        return $GLOBALS['_mpu_test_personality_prompts'] ?? [];
    }
}

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

    public function test_builder_excludes_reaction_and_metadata_categories(): void {
        // helper を直接呼ぶのではなく、mpu_build_prompt_categories() 全体を通す。
        // builder が将来フィルタ適用を外したら、この回帰テストが落ちる。
        $GLOBALS['_mpu_test_personality_prompts'] = [
            'casual'        => ['日常の一言'],
            'greeting'      => ['挨拶'],
            'touch_head'    => ['頭を撫でられた反応'],
            'give_food'     => ['食べ物の感想'],
            'give_favorite' => ['大好物の反応'],
            '_comment'      => 'metadata',
        ];

        try {
            $result = mpu_build_prompt_categories(
                [],            // wp_info
                [],            // visitor_info
                'time',        // time_context
                'Theme',       // theme_name
                '1.0',         // theme_version
                'Author',      // theme_author
                'test_builder_' . uniqid() // 静的キャッシュ回避のため毎回ユニーク
            );

            // 自発対話カテゴリは残る。
            $this->assertArrayHasKey('casual', $result);
            $this->assertArrayHasKey('greeting', $result);

            // 反応専用 / metadata カテゴリは builder を通すと除外される。
            $this->assertArrayNotHasKey('touch_head', $result);
            $this->assertArrayNotHasKey('give_food', $result);
            $this->assertArrayNotHasKey('give_favorite', $result);
            $this->assertArrayNotHasKey('_comment', $result);
        } finally {
            unset($GLOBALS['_mpu_test_personality_prompts']);
        }
    }
}
