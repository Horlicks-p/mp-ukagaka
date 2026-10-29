<?php

use PHPUnit\Framework\TestCase;

/**
 * A tool called with no arguments comes back as `{}`. json_decode($json, true) turns it
 * into `[]`, and sending the turn back as `[]` makes Claude and Gemini reject the next
 * request. These tests check the raw JSON the providers send, where the two differ.
 */
final class EmptyToolArgsTest extends TestCase {
    public function test_claude_empty_tool_input_becomes_an_object(): void {
        $content = [
            ['type' => 'thinking', 'thinking' => '', 'signature' => 'sig'],
            ['type' => 'tool_use', 'id' => 'toolu_1', 'name' => 'get_visitor_pulse', 'input' => []],
            ['type' => 'tool_use', 'id' => 'toolu_2', 'name' => 'get_post', 'input' => ['id' => 1]],
        ];

        $json = json_encode(mpu_normalize_claude_assistant_content($content));

        $this->assertStringContainsString('"name":"get_visitor_pulse","input":{}', $json);
        $this->assertStringContainsString('"input":{"id":1}', $json);
        $this->assertStringContainsString('{"type":"thinking","thinking":"","signature":"sig"}', $json);
    }

    public function test_gemini_empty_function_args_become_an_object(): void {
        $content = [
            'role'  => 'model',
            'parts' => [
                ['functionCall' => ['name' => 'get_visitor_pulse', 'args' => [], 'id' => 'c1'], 'thoughtSignature' => 'ts'],
                ['functionCall' => ['name' => 'get_post', 'args' => ['id' => 1]]],
                ['text' => 'hi'],
            ],
        ];

        $json = json_encode(mpu_normalize_gemini_model_content($content));

        $this->assertStringContainsString('"name":"get_visitor_pulse","args":{}', $json);
        $this->assertStringContainsString('"args":{"id":1}', $json);
        $this->assertStringContainsString('"thoughtSignature":"ts"', $json);
        $this->assertSame(['role' => 'model'], mpu_normalize_gemini_model_content(['role' => 'model']));
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_claude_stream_sends_no_argument_tool_call_back_as_object(): void {
        $this->load_stubs();
        $GLOBALS['_mpu_test_streams'] = [
            [
                // 無參數呼叫：沒有 input_json_delta
                ['content_block_start', ['index' => 0, 'content_block' => ['type' => 'tool_use', 'id' => 'toolu_1', 'name' => 'get_visitor_pulse', 'input' => new stdClass()]]],
                ['content_block_stop', ['index' => 0]],
            ],
            [
                ['content_block_start', ['index' => 0, 'content_block' => ['type' => 'text', 'text' => '']]],
                ['content_block_delta', ['index' => 0, 'delta' => ['type' => 'text_delta', 'text' => 'none']]],
                ['content_block_stop', ['index' => 0]],
            ],
        ];

        require_once MPU_TESTS_ROOT . '/includes/llm/providers/class-mpu-ai-provider-claude.php';
        $error = (new MPU_AI_Provider_Claude())->generate_chat_stream(
            ['api_key' => 'k', 'model' => 'claude-sonnet-5-5', 'messages' => [['role' => 'user', 'content' => '/visitor-info']], 'system_prompt' => 'sys'],
            function ($event, $data) {
            }
        );

        $this->assertNull($error);
        $this->assertSame([[]], $GLOBALS['_mpu_test_tool_args'], 'the tool still runs with array arguments');
        $this->assertStringContainsString('"name":"get_visitor_pulse","input":{}', $GLOBALS['_mpu_test_raw_bodies'][1]);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_claude_chat_sends_no_argument_tool_call_back_as_object(): void {
        $this->load_stubs();
        $GLOBALS['_mpu_test_responses'] = [
            '{"stop_reason":"tool_use","content":[{"type":"tool_use","id":"toolu_1","name":"get_visitor_pulse","input":{}}]}',
            '{"stop_reason":"end_turn","content":[{"type":"text","text":"none"}]}',
        ];

        require_once MPU_TESTS_ROOT . '/includes/llm/providers/class-mpu-ai-provider-claude.php';
        $result = (new MPU_AI_Provider_Claude())->generate_chat(
            ['api_key' => 'k', 'model' => 'claude-opus-4-7', 'messages' => [['role' => 'user', 'content' => '/visitor-info']], 'system_prompt' => 'sys']
        );

        $this->assertSame('none', $result);
        $this->assertStringContainsString('"name":"get_visitor_pulse","input":{}', $GLOBALS['_mpu_test_raw_bodies'][1]);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_gemini_chat_sends_no_argument_function_call_back_as_object(): void {
        $this->load_stubs();
        $GLOBALS['_mpu_test_responses'] = [
            '{"candidates":[{"content":{"role":"model","parts":[{"functionCall":{"name":"get_visitor_pulse","args":{}}}]}}]}',
            '{"candidates":[{"content":{"role":"model","parts":[{"text":"none"}]}}]}',
        ];

        require_once MPU_TESTS_ROOT . '/includes/llm/providers/class-mpu-ai-provider-gemini.php';
        $result = (new MPU_AI_Provider_Gemini())->generate_chat(
            ['api_key' => 'k', 'model' => 'gemini-2.5-flash', 'messages' => [['role' => 'user', 'content' => '/visitor-info']], 'system_prompt' => 'sys']
        );

        $this->assertSame('none', $result);
        $this->assertStringContainsString('"name":"get_visitor_pulse","args":{}', $GLOBALS['_mpu_test_raw_bodies'][1]);
    }

    private function load_stubs(): void {
        $GLOBALS['_mpu_test_raw_bodies'] = [];
        $GLOBALS['_mpu_test_tool_args']  = [];

        if (!function_exists('is_wp_error')) {
            function is_wp_error($value) {
                return $value instanceof WP_Error;
            }
        }
        if (!function_exists('wp_remote_post')) {
            function wp_remote_post($url, $args) {
                $GLOBALS['_mpu_test_raw_bodies'][] = $args['body'];
                return ['code' => 200, 'body' => array_shift($GLOBALS['_mpu_test_responses'])];
            }
            function wp_remote_retrieve_response_code($response) {
                return $response['code'];
            }
            function wp_remote_retrieve_body($response) {
                return $response['body'];
            }
            function mpu_stream_sse_events($url, $http_args, $on_event) {
                $GLOBALS['_mpu_test_raw_bodies'][] = $http_args['body'];
                foreach (array_shift($GLOBALS['_mpu_test_streams']) as [$type, $data]) {
                    $on_event($type, json_encode($data));
                }
                return null;
            }
            function mpu_get_mcp_tools_for_llm($provider) {
                return [['name' => 'get_visitor_pulse', 'description' => 'Visitor pulse', 'input_schema' => ['type' => 'object']]];
            }
            function mpu_execute_mcp_tool($name, $args) {
                $GLOBALS['_mpu_test_tool_args'][] = $args;
                return ['unique_visitors' => 0];
            }
            function mpu_debug_log($message, $level = 'debug') {
            }
        }

        require_once MPU_TESTS_ROOT . '/includes/llm/tool-loop-guard.php';
        require_once MPU_TESTS_ROOT . '/includes/llm/providers/interface-mpu-ai-provider.php';
        require_once MPU_TESTS_ROOT . '/includes/llm/providers/class-mpu-ai-provider-base.php';
    }
}
