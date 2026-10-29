<?php

use PHPUnit\Framework\TestCase;

/**
 * Claude request shape: temperature only for models known to accept it, effort low and
 * room for thinking on Claude 5, thinking blocks kept across a streamed tool round.
 */
final class ClaudeGenerationParamsTest extends TestCase {
    public function test_models_known_to_accept_temperature_keep_it(): void {
        foreach (['claude-sonnet-4-6', 'claude-opus-4-6', 'claude-haiku-4-5-20251001', 'claude-haiku-4-5', 'claude-sonnet-4-5-20250929'] as $model) {
            $this->assertSame(['max_tokens' => 800, 'temperature' => 0.8], mpu_build_claude_generation_params($model, 800, 0.8), $model);
        }
    }

    public function test_newer_and_unknown_models_never_get_temperature(): void {
        // anthropic-version 2023-06-01：4.7 以後與 5.x 收到 temperature 會 400
        foreach (['claude-opus-4-7', 'claude-opus-4-8', 'claude-sonnet-5', 'claude-sonnet-5-5', 'claude-haiku-5-5', 'claude-opus-5-5', 'claude-fable-5-1', 'claude-next-model'] as $model) {
            $this->assertArrayNotHasKey('temperature', mpu_build_claude_generation_params($model, 800, 0.8), $model);
        }
    }

    public function test_claude_5_gets_low_effort_and_room_for_thinking(): void {
        foreach (['claude-sonnet-5-5', 'claude-haiku-5-5', 'claude-opus-5-5', 'claude-fable-5-1', 'claude-opus-5', ' Claude-Sonnet-5-5 '] as $model) {
            // 短回覆路徑（自發發言、Akismet 反應等）的 100 token 會被墊高
            $this->assertSame(
                ['max_tokens' => 1024, 'output_config' => ['effort' => 'low']],
                mpu_build_claude_generation_params($model, 100, 0.8),
                $model
            );
        }

        // 墊高只往上，不會壓低管理員設的大額度
        $this->assertSame(4000, mpu_build_claude_generation_params('claude-sonnet-5-5', 4000)['max_tokens']);
    }

    public function test_claude_4_requests_are_unchanged(): void {
        // 4.x 不接受 effort（Haiku 4.5 / Sonnet 4.5 會報錯），也不墊高 max_tokens
        foreach (['claude-sonnet-4-6', 'claude-haiku-4-5-20251001', 'claude-opus-4-7', 'claude-3-5-haiku-20241022'] as $model) {
            $params = mpu_build_claude_generation_params($model, 100);

            $this->assertSame(['max_tokens' => 100], $params, $model);
        }
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_connection_reads_text_after_a_thinking_block(): void {
        $this->load_provider();
        $GLOBALS['_claude_test_responses'] = [[
            'content' => [
                ['type' => 'thinking', 'thinking' => '', 'signature' => 'sig'],
                ['type' => 'text', 'text' => 'Hello there'],
            ],
            'stop_reason' => 'end_turn',
        ]];

        $result = (new MPU_AI_Provider_Claude())->test_connection(['api_key' => 'k', 'model' => 'claude-sonnet-5-5']);

        $this->assertInstanceOf(WP_REST_Response::class, $result);
        $this->assertStringContainsString('Hello there', $result->data['msg']);

        $body = $GLOBALS['_claude_test_requests'][0];
        $this->assertSame(1024, $body['max_tokens']);
        $this->assertSame(['effort' => 'low'], $body['output_config']);
        $this->assertArrayNotHasKey('temperature', $body);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_connection_reports_a_reply_with_only_thinking(): void {
        $this->load_provider();
        $GLOBALS['_claude_test_responses'] = [[
            'content'     => [['type' => 'thinking', 'thinking' => '', 'signature' => 'sig']],
            'stop_reason' => 'max_tokens',
        ]];

        $result = (new MPU_AI_Provider_Claude())->test_connection(['api_key' => 'k', 'model' => 'claude-sonnet-5-5']);

        $this->assertInstanceOf(WP_Error::class, $result);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_stream_tool_round_sends_thinking_block_back_unchanged(): void {
        $requests = $this->run_stream_tool_round('claude-sonnet-5-5');

        $this->assertCount(2, $requests);
        foreach ($requests as $body) {
            $this->assertArrayNotHasKey('temperature', $body);
            $this->assertSame(['effort' => 'low'], $body['output_config']);
        }

        // 第二輪：assistant 回合含原樣的 thinking（signature 由 signature_delta 組成）、redacted_thinking 與 tool_use
        $assistant = $requests[1]['messages'][1];
        $this->assertSame('assistant', $assistant['role']);
        $this->assertSame(['type' => 'thinking', 'thinking' => 'plan', 'signature' => 'sig-part1sig-part2'], $assistant['content'][0]);
        $this->assertSame(['type' => 'redacted_thinking', 'data' => 'opaque'], $assistant['content'][1]);
        $this->assertSame('tool_use', $assistant['content'][2]['type']);
        $this->assertSame(['id' => 1], $assistant['content'][2]['input']);
        $this->assertSame('toolu_1', $requests[1]['messages'][2]['content'][0]['tool_use_id']);

        $this->assertSame(['done'], $GLOBALS['_claude_test_deltas']);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_stream_on_sonnet_4_6_keeps_temperature(): void {
        $requests = $this->run_stream_tool_round('claude-sonnet-4-6');

        foreach ($requests as $body) {
            $this->assertSame(0.8, $body['temperature']);
            $this->assertSame(1000, $body['max_tokens']);
            $this->assertArrayNotHasKey('output_config', $body);
        }
    }

    /**
     * Streams a thinking + redacted_thinking + tool_use turn, then a text turn, through
     * generate_chat_stream(). Returns the decoded request bodies.
     */
    private function run_stream_tool_round(string $model): array {
        $this->load_provider();

        $GLOBALS['_claude_test_streams'] = [
            [
                ['content_block_start', ['index' => 0, 'content_block' => ['type' => 'thinking', 'thinking' => '', 'signature' => '']]],
                ['content_block_delta', ['index' => 0, 'delta' => ['type' => 'thinking_delta', 'thinking' => 'plan']]],
                ['content_block_delta', ['index' => 0, 'delta' => ['type' => 'signature_delta', 'signature' => 'sig-part1']]],
                ['content_block_delta', ['index' => 0, 'delta' => ['type' => 'signature_delta', 'signature' => 'sig-part2']]],
                ['content_block_stop', ['index' => 0]],
                ['content_block_start', ['index' => 1, 'content_block' => ['type' => 'redacted_thinking', 'data' => 'opaque']]],
                ['content_block_stop', ['index' => 1]],
                ['content_block_start', ['index' => 2, 'content_block' => ['type' => 'tool_use', 'id' => 'toolu_1', 'name' => 'get_post', 'input' => []]]],
                ['content_block_delta', ['index' => 2, 'delta' => ['type' => 'input_json_delta', 'partial_json' => '{"id":1}']]],
                ['content_block_stop', ['index' => 2]],
            ],
            [
                ['content_block_start', ['index' => 0, 'content_block' => ['type' => 'text', 'text' => '']]],
                ['content_block_delta', ['index' => 0, 'delta' => ['type' => 'text_delta', 'text' => 'done']]],
                ['content_block_stop', ['index' => 0]],
            ],
        ];
        $GLOBALS['_claude_test_deltas'] = [];

        $error = (new MPU_AI_Provider_Claude())->generate_chat_stream(
            [
                'api_key'       => 'k',
                'model'         => $model,
                'messages'      => [['role' => 'user', 'content' => 'hi']],
                'system_prompt' => 'sys',
            ],
            function ($event, $data) {
                if ('delta' === $event) {
                    $GLOBALS['_claude_test_deltas'][] = $data['text'];
                }
            }
        );

        $this->assertNull($error);

        return $GLOBALS['_claude_test_requests'];
    }

    private function load_provider(): void {
        $GLOBALS['_claude_test_requests'] = [];

        if (!function_exists('is_wp_error')) {
            function is_wp_error($value) {
                return $value instanceof WP_Error;
            }
        }
        require_once MPU_TESTS_ROOT . '/tests/support/wp-rest-response.php';
        if (!function_exists('wp_remote_post')) {
            function wp_remote_post($url, $args) {
                $GLOBALS['_claude_test_requests'][] = json_decode($args['body'], true);
                return ['code' => 200, 'body' => json_encode(array_shift($GLOBALS['_claude_test_responses']))];
            }
            function wp_remote_retrieve_response_code($response) {
                return $response['code'];
            }
            function wp_remote_retrieve_body($response) {
                return $response['body'];
            }
            function mpu_stream_sse_events($url, $http_args, $on_event) {
                $GLOBALS['_claude_test_requests'][] = json_decode($http_args['body'], true);
                foreach (array_shift($GLOBALS['_claude_test_streams']) as [$type, $data]) {
                    $on_event($type, json_encode($data));
                }
                return null;
            }
            function mpu_get_mcp_tools_for_llm($provider) {
                return [['name' => 'get_post', 'description' => 'Get a post', 'input_schema' => ['type' => 'object']]];
            }
            function mpu_execute_mcp_tool($name, $args) {
                return ['title' => 'Hello'];
            }
            function mpu_debug_log($message, $level = 'debug') {
            }
        }

        require_once MPU_TESTS_ROOT . '/includes/llm/tool-loop-guard.php';
        require_once MPU_TESTS_ROOT . '/includes/llm/providers/interface-mpu-ai-provider.php';
        require_once MPU_TESTS_ROOT . '/includes/llm/providers/class-mpu-ai-provider-base.php';
        require_once MPU_TESTS_ROOT . '/includes/llm/providers/class-mpu-ai-provider-claude.php';
    }
}
