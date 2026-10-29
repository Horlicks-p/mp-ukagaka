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
    public function test_openai_model_default_only_applies_to_new_installs(): void {
        if (!function_exists('plugins_url')) {
            function plugins_url($path = '', $plugin = '') {
                return 'https://example.test/wp-content/plugins/mp-ukagaka/' . ltrim((string) $path, '/');
            }
        }

        require_once MPU_TESTS_ROOT . '/includes/core/core-functions.php';

        $defaults = mpu_default_opt();
        $this->assertSame('gpt-6-luna', $defaults['llm_openai_model']);

        // 既有網站沒存過模型：維持改預設前生效的 gpt-4.1-mini
        $merged = mpu_merge_option_defaults(['cur_ukagaka' => 'default_1'], $defaults);
        $this->assertSame('gpt-4.1-mini-2025-04-14', $merged['llm_openai_model']);

        // 既有網站存過的模型：原樣保留
        $merged = mpu_merge_option_defaults(['llm_openai_model' => 'gpt-4o'], $defaults);
        $this->assertSame('gpt-4o', $merged['llm_openai_model']);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_gemini_model_defaults_only_apply_to_new_installs(): void {
        if (!function_exists('plugins_url')) {
            function plugins_url($path = '', $plugin = '') {
                return 'https://example.test/wp-content/plugins/mp-ukagaka/' . ltrim((string) $path, '/');
            }
        }

        require_once MPU_TESTS_ROOT . '/includes/core/core-functions.php';

        // 2.5 只開放給用過的帳號，新安裝改用 3.5 Flash-Lite
        $defaults = mpu_default_opt();
        $this->assertSame('gemini-3.5-flash-lite', $defaults['llm_gemini_model']);
        $this->assertSame('gemini-3.5-flash-lite', $defaults['diary_gemini_model']);

        // 既有網站沒存過模型：主 LLM 與日記都維持改預設前生效的 2.5 Flash
        $merged = mpu_merge_option_defaults(['cur_ukagaka' => 'default_1'], $defaults);
        $this->assertSame('gemini-2.5-flash', $merged['llm_gemini_model']);
        $this->assertSame('gemini-2.5-flash', $merged['diary_gemini_model']);

        // 存過的模型原樣保留
        $merged = mpu_merge_option_defaults(['llm_gemini_model' => 'gemini-3.8-flash', 'diary_gemini_model' => 'gemini-2.5-pro'], $defaults);
        $this->assertSame('gemini-3.8-flash', $merged['llm_gemini_model']);
        $this->assertSame('gemini-2.5-pro', $merged['diary_gemini_model']);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_new_install_gets_new_gemini_models(): void {
        if (!function_exists('plugins_url')) {
            function plugins_url($path = '', $plugin = '') {
                return 'https://example.test/wp-content/plugins/mp-ukagaka/' . ltrim((string) $path, '/');
            }
        }

        require_once MPU_TESTS_ROOT . '/includes/core/core-functions.php';

        // 新安裝：資料庫沒有選項，寫入完整預設，之後讀回時 key 都在，不會被當成既有網站
        $GLOBALS['_mpu_test_options'] = [];
        $opt = mpu_get_option();

        $this->assertSame('gemini-3.5-flash-lite', $opt['llm_gemini_model']);
        $this->assertSame('gemini-3.5-flash-lite', $GLOBALS['_mpu_test_options']['mp_ukagaka']['diary_gemini_model']);
        $merged = mpu_merge_option_defaults($GLOBALS['_mpu_test_options']['mp_ukagaka'], mpu_default_opt());
        $this->assertSame('gemini-3.5-flash-lite', $merged['diary_gemini_model']);
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
