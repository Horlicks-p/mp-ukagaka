<?php

use PHPUnit\Framework\TestCase;

final class AdminOptionMigrationTest extends TestCase {
    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_legacy_ai_keys_are_migrated_then_removed_before_save(): void {
        require_once MPU_TESTS_ROOT . '/includes/admin-functions.php';

        $options = [
            'ai_provider'             => 'openai',
            'ai_api_key'              => 'legacy-gemini-key',
            'gemini_model'            => 'legacy-gemini-model',
            'openai_api_key'          => 'legacy-openai-key',
            'openai_model'            => 'legacy-openai-model',
            'claude_api_key'          => 'legacy-claude-key',
            'claude_model'            => 'legacy-claude-model',
            'ollama_replace_dialogue' => true,
        ];

        mpu_normalize_llm_option_keys($options);

        $this->assertSame('openai', $options['llm_provider']);
        $this->assertSame('legacy-gemini-key', $options['llm_gemini_api_key']);
        $this->assertSame('legacy-gemini-model', $options['llm_gemini_model']);
        $this->assertSame('legacy-openai-key', $options['llm_openai_api_key']);
        $this->assertSame('legacy-openai-model', $options['llm_openai_model']);
        $this->assertSame('legacy-claude-key', $options['llm_claude_api_key']);
        $this->assertSame('legacy-claude-model', $options['llm_claude_model']);
        $this->assertTrue($options['llm_replace_dialogue']);

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
            $this->assertArrayNotHasKey($legacy_key, $options);
        }
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_legacy_values_win_over_defaults_added_during_option_merge(): void {
        if (!function_exists('plugins_url')) {
            function plugins_url($path = '', $plugin = '') {
                return 'https://example.test/wp-content/plugins/mp-ukagaka/' . ltrim((string) $path, '/');
            }
        }

        require_once MPU_TESTS_ROOT . '/includes/core/core-functions.php';
        require_once MPU_TESTS_ROOT . '/includes/admin-functions.php';

        $stored = [
            'ai_provider'             => 'claude',
            'gemini_model'            => 'legacy-gemini-model',
            'openai_model'            => 'legacy-openai-model',
            'claude_model'            => 'legacy-claude-model',
            'ollama_replace_dialogue' => true,
        ];
        $merged = mpu_merge_option_defaults($stored, mpu_default_opt());

        mpu_normalize_llm_option_keys($merged, $stored);

        $this->assertSame('claude', $merged['llm_provider']);
        $this->assertSame('legacy-gemini-model', $merged['llm_gemini_model']);
        $this->assertSame('legacy-openai-model', $merged['llm_openai_model']);
        $this->assertSame('legacy-claude-model', $merged['llm_claude_model']);
        $this->assertTrue($merged['llm_replace_dialogue']);
        $this->assertArrayNotHasKey('ai_provider', $merged);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_reset_replaces_options_with_defaults_without_unsetting_array(): void {
        if (!function_exists('plugins_url')) {
            function plugins_url($path = '', $plugin = '') {
                return 'https://example.test/wp-content/plugins/mp-ukagaka/' . ltrim((string) $path, '/');
            }
        }

        require_once MPU_TESTS_ROOT . '/includes/core/core-functions.php';
        require_once MPU_TESTS_ROOT . '/includes/admin-functions.php';

        $options = ['llm_provider' => 'openai', 'custom' => 'value'];
        $notice = mpu_reset_options($options);

        $this->assertSame(mpu_default_opt(), $options);
        $this->assertStringContainsString('設定をリセットしました', $notice);
    }
}
