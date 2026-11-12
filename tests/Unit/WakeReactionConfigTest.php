<?php

use PHPUnit\Framework\TestCase;

/**
 * ghost/Frieren/sleep_mode.json の起床反応設定を守る。
 *
 * wake_reaction_emoji は mpu_get_wake_reaction_emoji() と同じ形式でなければ
 * 黙って無視されるので、実ファイルの存在まで確認する。
 */
final class WakeReactionConfigTest extends TestCase {
    private function sleep_config(): array {
        $config = json_decode(
            (string) file_get_contents(MPU_TESTS_ROOT . '/ghost/Frieren/sleep_mode.json'),
            true
        );
        $this->assertIsArray($config);
        return $config;
    }

    public function test_wake_reaction_emoji_is_a_valid_existing_file(): void {
        $config = $this->sleep_config();

        $this->assertArrayHasKey('wake_reaction_emoji', $config);
        $emoji = $config['wake_reaction_emoji'];

        // mpu_get_wake_reaction_emoji() の検証と同じ形式。
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]+\.(png|gif|webp)$/', $emoji);
        $this->assertFileExists(MPU_TESTS_ROOT . '/ghost/Frieren/emojis/' . $emoji);
    }

    public function test_wake_reaction_prompts_are_directions_not_quoted_lines(): void {
        $config = $this->sleep_config();

        foreach (array('deep_sleep', 'oversleep', 'nap') as $phase) {
            $this->assertNotEmpty($config['wake_reaction_prompts'][$phase] ?? array(), $phase);

            foreach ($config['wake_reaction_prompts'][$phase] as $prompt) {
                // 『…』で台詞例を書くと LLM がそのまま言うので、演出指示として書く。
                $this->assertStringNotContainsString('『', $prompt, $phase . ': ' . $prompt);
            }
        }
    }
}
