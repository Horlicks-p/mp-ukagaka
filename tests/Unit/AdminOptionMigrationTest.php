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
}
