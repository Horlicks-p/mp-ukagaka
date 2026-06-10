<?php

use PHPUnit\Framework\TestCase;

require_once MPU_TESTS_ROOT . '/includes/llm/api-cache.php';

final class ApiCacheTest extends TestCase {
    public function test_cache_key_changes_when_generation_metadata_changes(): void {
        $base = mpu_generate_cache_key(
            'gemini',
            'gemini-2.5-flash',
            'ja',
            500,
            'system prompt',
            'user prompt'
        );

        $this->assertNotSame(
            $base,
            mpu_generate_cache_key('gemini', 'gemini-2.5-pro', 'ja', 500, 'system prompt', 'user prompt')
        );
        $this->assertNotSame(
            $base,
            mpu_generate_cache_key('gemini', 'gemini-2.5-flash', 'zh-TW', 500, 'system prompt', 'user prompt')
        );
        $this->assertNotSame(
            $base,
            mpu_generate_cache_key('gemini', 'gemini-2.5-flash', 'ja', 1000, 'system prompt', 'user prompt')
        );
        $this->assertNotSame(
            $base,
            mpu_generate_cache_key('gemini', 'gemini-2.5-flash', 'ja', 500, 'system prompt', 'different prompt')
        );
    }

    public function test_cache_key_changes_when_local_endpoint_changes(): void {
        $base = mpu_generate_cache_key(
            'ollama',
            'qwen3:8b',
            'ja',
            500,
            'system prompt',
            'user prompt',
            'http://localhost:11434'
        );

        $this->assertNotSame(
            $base,
            mpu_generate_cache_key(
                'ollama',
                'qwen3:8b',
                'ja',
                500,
                'system prompt',
                'user prompt',
                'http://ollama.example.test:11434'
            )
        );
    }
}
