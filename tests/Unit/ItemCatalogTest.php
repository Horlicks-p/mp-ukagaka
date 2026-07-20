<?php

use PHPUnit\Framework\TestCase;

require_once MPU_TESTS_ROOT . '/includes/personality/personality-items.php';

final class ItemCatalogTest extends TestCase {
    public function test_frieren_item_catalog_contains_expected_valid_items(): void {
        $path = MPU_TESTS_ROOT . '/ghost/Frieren/items.json';
        $catalog = json_decode((string) file_get_contents($path), true);

        $this->assertIsArray($catalog);
        $this->assertSame('items', $catalog['items_base_folder']);
        $this->assertCount(2, $catalog['items']);

        $prompts = json_decode(
            (string) file_get_contents(MPU_TESTS_ROOT . '/ghost/Frieren/prompts.json'),
            true
        );
        $expected = [
            'merkur_pudding' => [
                'kind' => 'food',
                'name' => 'メルクーアプリン',
                'image' => 'merkur_pudding.png',
                'reactions' => ['give_food', 'give_favorite'],
                'size' => [112, 66],
                'has_variants' => false,
            ],
            'grimoire' => [
                'kind' => 'gift',
                'name' => '魔導書',
                'image' => 'grimoire.png',
                'reactions' => ['give_gift', 'give_favorite'],
                'size' => [68, 85],
                'has_variants' => true,
            ],
        ];

        foreach ($catalog['items'] as $item) {
            $this->assertArrayHasKey($item['id'], $expected);
            $this->assertSame($expected[$item['id']]['kind'], $item['kind']);
            $this->assertSame($expected[$item['id']]['name'], $item['name']);
            $this->assertSame($expected[$item['id']]['image'], $item['image']);
            $this->assertSame($expected[$item['id']]['reactions'], $item['reactions']);
            $imagePath = MPU_TESTS_ROOT . '/ghost/Frieren/items/' . $item['image'];
            $this->assertFileExists($imagePath);
            $this->assertSame($expected[$item['id']]['size'], array_slice(getimagesize($imagePath), 0, 2));
            $this->assertTrue($item['favorite']);
            $this->assertNotSame('', trim($item['prompt']));
            $this->assertMatchesRegularExpression('/\A[a-z_][a-z0-9_]*\z/', $item['id']);
            $this->assertMatchesRegularExpression('/\A[a-z0-9_-]+\.(png|webp)\z/', $item['image']);
            $this->assertDoesNotMatchRegularExpression('/\[(thinking|laugh|sigh|love)\]/i', $item['name']);
            $this->assertDoesNotMatchRegularExpression('/\[(thinking|laugh|sigh|love)\]/i', $item['prompt']);

            foreach ($item['reactions'] as $category) {
                $this->assertMatchesRegularExpression('/\A[a-z_][a-z0-9_]*\z/', $category);
                $this->assertArrayHasKey($category, $prompts);
                $this->assertNotEmpty($prompts[$category]);
            }

            $variants = $item['variants'] ?? [];
            $this->assertSame($expected[$item['id']]['has_variants'], !empty($variants));
            foreach ($variants as $variant) {
                $this->assertIsString($variant);
                $this->assertNotSame('', trim($variant));
                $this->assertDoesNotMatchRegularExpression('/\[(thinking|laugh|sigh|love)\]/i', $variant);
            }
            // variants を持つ item は prompt 側に {variant} 差し込み位置が必要（逆も同様）。
            $this->assertSame(
                !empty($variants),
                strpos($item['prompt'], '{variant}') !== false
            );
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
                    'variants' => ['<b>Fire Tome</b>', '', '   ', 123, ['nested'], 'Fire Tome', 'Ice Tome'],
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
        // variants は sanitize_text_field 適用・非文字列/空除去・重複除去される。
        $this->assertSame([
            'id' => 'valid_item',
            'kind' => 'food',
            'name' => 'Valid Item',
            'image' => 'valid_item.webp',
            'favorite' => true,
            'prompt' => 'React naturally.',
            'reactions' => ['give_food', 'badcat'],
            'variants' => ['Fire Tome', 'Ice Tome'],
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
