<?php

use PHPUnit\Framework\TestCase;

final class OpenAIGenerationParamsTest extends TestCase {
    public function test_luna_models_use_completion_tokens_and_none_effort(): void {
        // gpt-5.6-luna 只能從自訂模型填入，規則與 gpt-6-luna 相同
        foreach (['gpt-6-luna', 'gpt-5.6-luna'] as $model) {
            $params = mpu_build_openai_generation_params($model, 800, 0.8);

            $this->assertSame(800, $params['max_completion_tokens'], $model);
            $this->assertSame('none', $params['reasoning_effort'], $model);
            $this->assertSame(0.8, $params['temperature'], $model);
            $this->assertArrayNotHasKey('max_tokens', $params, $model);
        }
    }

    public function test_legacy_models_keep_old_format(): void {
        foreach (['gpt-4.1-mini-2025-04-14', 'gpt-4o-mini', 'gpt-4o'] as $model) {
            $params = mpu_build_openai_generation_params($model, 800, 0.8);

            // 舊模型不接受 reasoning_effort，送出會 400
            $this->assertSame(['max_tokens' => 800, 'temperature' => 0.8], $params, $model);
        }
    }

    public function test_null_temperature_is_omitted(): void {
        $params = mpu_build_openai_generation_params('gpt-6-luna', 50);

        $this->assertArrayNotHasKey('temperature', $params);
        $this->assertSame(50, $params['max_completion_tokens']);
    }
}
