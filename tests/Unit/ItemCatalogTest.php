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
        $this->assertFalse($item['favorite']);
        $this->assertNotSame('', trim($item['prompt']));
        $this->assertMatchesRegularExpression('/\A[a-z_][a-z0-9_]*\z/', $item['id']);
        $this->assertMatchesRegularExpression('/\A[a-z0-9_-]+\.(png|webp)\z/', $item['image']);
        $this->assertDoesNotMatchRegularExpression('/\[(thinking|laugh|sigh|love)\]/i', $item['name']);
        $this->assertDoesNotMatchRegularExpression('/\[(thinking|laugh|sigh|love)\]/i', $item['prompt']);
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
        $this->assertSame([
            'id' => 'valid_item',
            'kind' => 'food',
            'name' => 'Valid Item',
            'image' => 'valid_item.webp',
            'favorite' => true,
            'prompt' => 'React naturally.',
        ], $catalog['items'][0]);
    }
}
