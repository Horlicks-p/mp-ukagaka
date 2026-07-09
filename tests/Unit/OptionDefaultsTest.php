<?php

use PHPUnit\Framework\TestCase;

final class OptionDefaultsTest extends TestCase {
    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_get_option_fills_known_nested_defaults_without_overwriting_saved_values(): void {
        if (!function_exists('plugins_url')) {
            function plugins_url($path = '', $plugin = '') {
                return 'https://example.test/wp-content/plugins/mp-ukagaka/' . ltrim((string) $path, '/');
            }
        }

        require_once MPU_TESTS_ROOT . '/includes/core/core-functions.php';

        $GLOBALS['_mpu_test_options']['mp_ukagaka'] = [
            'cur_ukagaka' => 'default_1',
            'extend' => [],
            'bot_blocker' => [
                'enabled' => true,
            ],
            'ukagakas' => [
                'default_1' => [
                    'name' => 'Custom Frieren',
                ],
            ],
        ];

        $opt = mpu_get_option();

        $this->assertSame('', $opt['extend']['js_area']);
        $this->assertTrue($opt['bot_blocker']['enabled']);
        $this->assertSame(600, $opt['bot_blocker']['hot_transient_ttl']);
        $this->assertSame(40, $opt['bot_blocker']['rate_limit_threshold']);
        $this->assertSame('Custom Frieren', $opt['ukagakas']['default_1']['name']);
        $this->assertTrue($opt['ukagakas']['default_1']['show_decorations']);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_default_options_do_not_include_legacy_ai_storage_keys(): void {
        if (!function_exists('plugins_url')) {
            function plugins_url($path = '', $plugin = '') {
                return 'https://example.test/wp-content/plugins/mp-ukagaka/' . ltrim((string) $path, '/');
            }
        }

        require_once MPU_TESTS_ROOT . '/includes/core/core-functions.php';

        $defaults = mpu_default_opt();

        foreach ([
            'ai_provider',
            'ai_api_key',
            'gemini_model',
            'openai_api_key',
            'openai_model',
            'claude_api_key',
            'claude_model',
            'ollama_replace_dialogue',
        ] as $legacy_key) {
            $this->assertArrayNotHasKey($legacy_key, $defaults);
        }
    }
}
