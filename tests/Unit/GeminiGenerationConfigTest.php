<?php

use PHPUnit\Framework\TestCase;

/**
 * Gemini 3.x request shape: no sampling fields, thinkingLevel low on 3.8, and the
 * functionCall id echoed back in the functionResponse.
 */
final class GeminiGenerationConfigTest extends TestCase {
    public function test_gemini_3_and_later_drop_sampling_and_think_low(): void {
        foreach (['gemini-3.8-flash', 'gemini-3.6-flash', 'gemini-3-flash-preview', 'gemini-3.1-pro-preview', 'gemini-4-argon', ' Gemini-3.8-Flash '] as $model) {
            $config = mpu_build_gemini_generation_config($model, 800, ['temperature' => 0.8, 'topK' => 40, 'topP' => 0.95]);

            $this->assertSame(['maxOutputTokens' => 800, 'thinkingConfig' => ['thinkingLevel' => 'low']], $config, $model);
        }
    }

    public function test_flash_lite_keeps_its_minimal_default(): void {
        // Flash-Lite 預設就是 minimal；不指定 thinkingLevel，也不送採樣參數
        foreach (['gemini-3.5-flash-lite', 'gemini-3.1-flash-lite'] as $model) {
            $config = mpu_build_gemini_generation_config($model, 500, ['temperature' => 0.7]);

            $this->assertSame(['maxOutputTokens' => 500], $config, $model);
        }
    }

    public function test_gemini_2_5_and_unknown_ids_keep_the_old_request(): void {
        $sampling = ['temperature' => 0.7, 'topK' => 40, 'topP' => 0.95];

        // 2.5 不接受 thinkingLevel；無法辨識版本的自訂模型照舊處理
        foreach (['gemini-2.5-flash', 'gemini-2.5-pro', 'gemini-exp-1206', 'learnlm-2.0-flash', 'gemini-30x'] as $model) {
            $config = mpu_build_gemini_generation_config($model, 500, $sampling);

            $this->assertSame($sampling + ['maxOutputTokens' => 500], $config, $model);
            $this->assertArrayNotHasKey('thinkingConfig', $config, $model);
        }

        $this->assertSame(['maxOutputTokens' => 1024], mpu_build_gemini_generation_config('gemini-2.5-flash', 1024));
    }

    public function test_minimal_is_never_sent(): void {
        // 3.8 Flash 不接受 minimal，送出會報錯
        foreach (['gemini-3.8-flash', 'gemini-3.5-flash-lite', 'gemini-4-argon', 'gemini-2.5-flash'] as $model) {
            $config = mpu_build_gemini_generation_config($model, 100);

            $this->assertNotSame('minimal', $config['thinkingConfig']['thinkingLevel'] ?? null, $model);
        }
    }

    public function test_function_response_echoes_the_call_id_only_when_present(): void {
        $this->assertSame(
            ['functionResponse' => ['name' => 'get_post', 'response' => ['title' => 'x'], 'id' => 'call-8f2b']],
            mpu_build_gemini_function_response_part('get_post', ['title' => 'x'], 'call-8f2b')
        );

        // 2.5 不回傳 id：不送空的 id 欄位
        foreach ([null, ''] as $call_id) {
            $this->assertSame(
                ['functionResponse' => ['name' => 'get_post', 'response' => ['result' => 'ok']]],
                mpu_build_gemini_function_response_part('get_post', 'ok', $call_id)
            );
        }
        $this->assertSame(
            ['functionResponse' => ['name' => 'get_post', 'response' => ['result' => 'ok']]],
            mpu_build_gemini_function_response_part('get_post', 'ok')
        );
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_chat_tool_round_trip_sends_new_request_shape(): void {
        $requests = $this->run_tool_round_trip('gemini-3.8-flash');

        $this->assertCount(2, $requests);
        foreach ($requests as $body) {
            $this->assertSame(['maxOutputTokens' => 1000, 'thinkingConfig' => ['thinkingLevel' => 'low']], $body['generationConfig']);
        }

        // 第二輪：模型回應原樣（含 thoughtSignature）加回歷史，functionResponse 帶回同一個 id
        $contents = $requests[1]['contents'];
        $this->assertSame('sig-abc', $contents[1]['parts'][0]['thoughtSignature']);
        $this->assertSame('call-8f2b', $contents[2]['parts'][0]['functionResponse']['id']);
        $this->assertSame('get_post', $contents[2]['parts'][0]['functionResponse']['name']);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_chat_tool_round_trip_on_2_5_is_unchanged(): void {
        $requests = $this->run_tool_round_trip('gemini-2.5-flash', false);

        foreach ($requests as $body) {
            $this->assertSame(['temperature' => 0.8, 'maxOutputTokens' => 1000], $body['generationConfig']);
        }
        $this->assertArrayNotHasKey('id', $requests[1]['contents'][2]['parts'][0]['functionResponse']);
    }

    /**
     * Runs MPU_AI_Provider_Gemini::generate_chat() against canned responses: a
     * functionCall first, then text. Returns the decoded request bodies.
     */
    private function run_tool_round_trip(string $model, bool $with_id = true): array {
        $GLOBALS['_gemini_test_requests']  = [];
        $call = ['name' => 'get_post', 'args' => ['id' => 1]];
        if ($with_id) {
            $call['id'] = 'call-8f2b';
        }
        $GLOBALS['_gemini_test_responses'] = [
            ['candidates' => [['content' => ['role' => 'model', 'parts' => [['functionCall' => $call, 'thoughtSignature' => 'sig-abc']]]]]],
            ['candidates' => [['content' => ['role' => 'model', 'parts' => [['text' => 'done']]]]]],
        ];

        if (!function_exists('is_wp_error')) {
            function is_wp_error($value) {
                return $value instanceof WP_Error;
            }
        }
        if (!function_exists('wp_remote_post')) {
            function wp_remote_post($url, $args) {
                $GLOBALS['_gemini_test_requests'][] = json_decode($args['body'], true);
                return ['code' => 200, 'body' => json_encode(array_shift($GLOBALS['_gemini_test_responses']))];
            }
            function wp_remote_retrieve_response_code($response) {
                return $response['code'];
            }
            function wp_remote_retrieve_body($response) {
                return $response['body'];
            }
            function mpu_get_mcp_tools_for_llm($provider) {
                return [['name' => 'get_post', 'description' => 'Get a post', 'parameters' => ['type' => 'object']]];
            }
            function mpu_execute_mcp_tool($name, $args) {
                return ['title' => 'Hello'];
            }
        }

        require_once MPU_TESTS_ROOT . '/includes/core/utility-functions.php';
        require_once MPU_TESTS_ROOT . '/includes/llm/tool-loop-guard.php';
        require_once MPU_TESTS_ROOT . '/includes/llm/providers/interface-mpu-ai-provider.php';
        require_once MPU_TESTS_ROOT . '/includes/llm/providers/class-mpu-ai-provider-base.php';
        require_once MPU_TESTS_ROOT . '/includes/llm/providers/class-mpu-ai-provider-gemini.php';

        $provider = new MPU_AI_Provider_Gemini();
        $result   = $provider->generate_chat([
            'api_key'       => 'k',
            'model'         => $model,
            'messages'      => [['role' => 'user', 'content' => 'hi']],
            'system_prompt' => 'sys',
        ]);

        $this->assertSame('done', $result);

        return $GLOBALS['_gemini_test_requests'];
    }
}
