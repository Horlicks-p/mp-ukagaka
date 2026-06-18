<?php

use PHPUnit\Framework\TestCase;

require_once MPU_TESTS_ROOT . '/includes/personality/personality-items.php';

final class ItemCatalogTest extends TestCase {
    public function test_frieren_item_catalog_contains_only_valid_test_item(): void {
        $path = MPU_TESTS_ROOT . '/ghost/Frieren/items.json';
        $catalog = json_decode((string) file_get_contents($path), true);

        $this->assertIsArray($catalog);
        $this->assertSame('items', $catalog['items_base_folder']);
        $this->assertCount(1, $catalog['items']);

        $item = $catalog['items'][0];
        $this->assertSame('merkur_pudding', $item['id']);
        $this->assertSame('food', $item['kind']);
        $this->assertSame('メルクーアプリン', $item['name']);
        $this->assertSame('merkur_pudding.png', $item['image']);
        $this->assertTrue($item['favorite']);
        $this->assertNotSame('', trim($item['prompt']));
        $this->assertMatchesRegularExpression('/\A[a-z_][a-z0-9_]*\z/', $item['id']);
        $this->assertMatchesRegularExpression('/\A[a-z0-9_-]+\.(png|webp)\z/', $item['image']);
        $this->assertDoesNotMatchRegularExpression('/\[(thinking|laugh|sigh|love)\]/i', $item['name']);
        $this->assertDoesNotMatchRegularExpression('/\[(thinking|laugh|sigh|love)\]/i', $item['prompt']);

        // reactions は prompts.json のカテゴリ名（任意・配列）。
        $this->assertArrayHasKey('reactions', $item);
        $this->assertIsArray($item['reactions']);
        foreach ($item['reactions'] as $category) {
            $this->assertMatchesRegularExpression('/\A[a-z_][a-z0-9_]*\z/', $category);
        }

        // items.json の reactions が指すカテゴリが prompts.json に実在すること。
        $prompts = json_decode(
            (string) file_get_contents(MPU_TESTS_ROOT . '/ghost/Frieren/prompts.json'),
            true
        );
        foreach ($item['reactions'] as $category) {
            $this->assertArrayHasKey($category, $prompts);
            $this->assertNotEmpty($prompts[$category]);
        }
    }

    public function test_catalog_normalizer_rejects_invalid_items_and_sanitizes_valid_item(): void {
        $catalog = mpu_normalize_personality_items_catalog([
            'version' => '<b>1.0</b>',
            'items_base_folder' => '../unsafe',
            'items' => [
                [
                    'id' => 'valid_item',
                    'kind' => 'food',
                    'name' => '<b>Valid Item</b>',
                    'image' => 'valid_item.webp',
                    'favorite' => 1,
                    'prompt' => '<i>React naturally.</i>',
                    'reactions' => ['Give_Food', 'give_food', 'bad cat!', ''],
                ],
                [
                    'id' => 'bad/id',
                    'kind' => 'food',
                    'name' => 'Bad ID',
                    'image' => 'bad.png',
                    'prompt' => 'Bad.',
                ],
                [
                    'id' => 'bad_kind',
                    'kind' => 'medicine',
                    'name' => 'Bad Kind',
                    'image' => 'bad_kind.png',
                    'prompt' => 'Bad.',
                ],
                [
                    'id' => 'bad_image',
                    'kind' => 'gift',
                    'name' => 'Bad Image',
                    'image' => '../secret.svg',
                    'prompt' => 'Bad.',
                ],
            ],
        ]);

        $this->assertSame('1.0', $catalog['version']);
        $this->assertSame('items', $catalog['items_base_folder']);
        $this->assertCount(1, $catalog['items']);
        // reactions は sanitize_key 適用・空除去・重複除去される
        // （'Give_Food'→'give_food'、'give_food' は重複、'bad cat!'→'badcat'、'' は除去）。
        $this->assertSame([
            'id' => 'valid_item',
            'kind' => 'food',
            'name' => 'Valid Item',
            'image' => 'valid_item.webp',
            'favorite' => true,
            'prompt' => 'React naturally.',
            'reactions' => ['give_food', 'badcat'],
        ], $catalog['items'][0]);
    }

    public function test_reaction_pool_merges_categories_and_drops_malformed_entries(): void {
        $prompts_data = [
            'give_food' => [
                'first line',
                '',            // 空文字 → 除外
                '   ',         // 空白のみ → 除外
                123,           // 非文字列 → 除外
                ['nested'],    // 非文字列 → 除外
                null,          // 非文字列 → 除外
                'second line',
            ],
            'give_favorite' => [
                'favorite line',
            ],
            'give_empty' => [],          // 空配列 → スキップ
            'give_scalar' => 'not-array', // 非配列 → スキップ
        ];

        // 複数カテゴリを順序通りにマージし、有効な文字列のみ残す。
        $pool = mpu_collect_item_reaction_pool(
            ['give_food', 'give_favorite', 'give_empty', 'give_scalar', 'give_missing'],
            $prompts_data
        );
        $this->assertSame(
            ['first line', 'second line', 'favorite line'],
            $pool
        );

        // reactions が空なら pool も空。
        $this->assertSame([], mpu_collect_item_reaction_pool([], $prompts_data));
    }
}
