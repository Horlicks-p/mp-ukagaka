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
        $this->assertFalse(mpu_is_spontaneous_prompt_category('give_gift'));
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
        $this->assertArrayHasKey('give_gift', $prompts);
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
            'give_gift'     => ['贈り物の感想'],
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
            $this->assertArrayNotHasKey('give_gift', $result);
            $this->assertArrayNotHasKey('give_favorite', $result);
            $this->assertArrayNotHasKey('_comment', $result);
        } finally {
            unset($GLOBALS['_mpu_test_personality_prompts']);
        }
    }

    private function build_with_admin_categories(bool $logged_in, bool $can_manage): array {
        $GLOBALS['_mpu_test_personality_prompts'] = [
            'casual'                     => ['日常の一言'],
            'admin_comment'              => ['管理人を軽くからかう'],
            'anniversary_admin_birthday' => ['管理人の誕生日を祝う'],
        ];
        $GLOBALS['_mpu_test_is_user_logged_in'] = $logged_in;
        $GLOBALS['_mpu_test_current_user_can']  = $can_manage;

        try {
            return mpu_build_prompt_categories(
                [], [], 'time', 'Theme', '1.0', 'Author',
                'test_audience_' . uniqid()
            );
        } finally {
            unset($GLOBALS['_mpu_test_personality_prompts']);
            $GLOBALS['_mpu_test_is_user_logged_in'] = false;
            $GLOBALS['_mpu_test_current_user_can']  = false;
        }
    }

    public function test_admin_only_categories_are_excluded_when_the_listener_is_not_the_admin(): void {
        // 未ログインの訪客も、管理権限のないログインユーザーも管理人ではない。
        foreach ([[false, false], [true, false]] as [$logged_in, $can_manage]) {
            $result = $this->build_with_admin_categories($logged_in, $can_manage);

            $this->assertArrayHasKey('casual', $result);
            $this->assertArrayNotHasKey('admin_comment', $result);
            $this->assertArrayNotHasKey('anniversary_admin_birthday', $result);
        }
    }

    public function test_admin_only_categories_added_by_a_filter_are_still_excluded_for_guests(): void {
        $saved = $GLOBALS['_mpu_test_filters']['mpu_prompt_categories'] ?? null;
        add_filter('mpu_prompt_categories', static function ($categories) {
            $categories['admin_comment'] = ['第三者が加えた管理人向け指示'];
            return $categories;
        });

        try {
            $guest = $this->build_with_admin_categories(false, false);
            $this->assertArrayHasKey('casual', $guest);
            $this->assertArrayNotHasKey('admin_comment', $guest);

            $admin = $this->build_with_admin_categories(true, true);
            $this->assertArrayHasKey('admin_comment', $admin);
        } finally {
            if (null === $saved) {
                unset($GLOBALS['_mpu_test_filters']['mpu_prompt_categories']);
            } else {
                $GLOBALS['_mpu_test_filters']['mpu_prompt_categories'] = $saved;
            }
        }
    }

    public function test_admin_only_categories_remain_for_the_admin(): void {
        $result = $this->build_with_admin_categories(true, true);

        $this->assertArrayHasKey('casual', $result);
        $this->assertArrayHasKey('admin_comment', $result);
        $this->assertArrayHasKey('anniversary_admin_birthday', $result);
    }

    /**
     * 再訪判定はブラウザ単位なので、管理人向けの admin_comment を上げてはいけない。
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_revisit_flags_do_not_boost_admin_comment(): void {
        function mpu_get_personality_base_weights($personality_id = null) {
            return ['casual' => 5, 'admin_comment' => 6];
        }

        $weights = mpu_get_dynamic_category_weights(
            '',
            [],
            ['is_recent_visit' => true, 'is_frequent_visitor' => true]
        );

        // 再訪による調整そのものは効いている。
        $this->assertSame(18, $weights['casual']);
        // admin_comment は基礎権重のまま。
        $this->assertSame(6, $weights['admin_comment']);
    }

    public function test_frieren_frequent_visitor_weights_do_not_boost_admin_comment(): void {
        $weights = json_decode(
            (string) file_get_contents(MPU_TESTS_ROOT . '/ghost/Frieren/weights.json'),
            true
        );
        $this->assertIsArray($weights);

        $found = false;
        $stack = [$weights];
        while ($stack) {
            $node = array_pop($stack);
            foreach ($node as $key => $value) {
                if ($key === 'is_frequent_visitor' && is_array($value)) {
                    $found = true;
                    $this->assertArrayNotHasKey('admin_comment', $value);
                } elseif (is_array($value)) {
                    $stack[] = $value;
                }
            }
        }
        $this->assertTrue($found, 'is_frequent_visitor adjustment not found in weights.json');
    }
}
