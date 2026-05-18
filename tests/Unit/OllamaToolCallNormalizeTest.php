<?php

use PHPUnit\Framework\TestCase;

final class OllamaToolCallNormalizeTest extends TestCase {
    public function test_empty_arguments_array_becomes_stdclass(): void {
        $message = [
            'role' => 'assistant',
            'tool_calls' => [
                [
                    'function' => [
                        'name' => 'mp-ukagaka__get-bot-blocker-stats',
                        'arguments' => [], // PHP empty array from json_decode of {}
                    ],
                ],
            ],
        ];

        $normalized = mpu_normalize_ollama_assistant_message($message);

        $this->assertInstanceOf(stdClass::class, $normalized['tool_calls'][0]['function']['arguments']);
        // 確認 json_encode 後是 `{}` 而非 `[]`
        $this->assertSame('{}', json_encode($normalized['tool_calls'][0]['function']['arguments']));
    }

    public function test_non_empty_arguments_array_left_untouched(): void {
        $message = [
            'role' => 'assistant',
            'tool_calls' => [
                [
                    'function' => [
                        'name' => 'mp-ukagaka__ban-ip',
                        'arguments' => ['ip_address' => '1.2.3.4'],
                    ],
                ],
            ],
        ];

        $normalized = mpu_normalize_ollama_assistant_message($message);

        $this->assertSame(['ip_address' => '1.2.3.4'], $normalized['tool_calls'][0]['function']['arguments']);
    }

    public function test_string_arguments_left_untouched(): void {
        // 部分 provider / Ollama 版本送 string 形式的 arguments，不該動
        $message = [
            'role' => 'assistant',
            'tool_calls' => [
                [
                    'function' => [
                        'name' => 'mp-ukagaka__ban-ip',
                        'arguments' => '{"ip_address":"1.2.3.4"}',
                    ],
                ],
            ],
        ];

        $normalized = mpu_normalize_ollama_assistant_message($message);

        $this->assertSame('{"ip_address":"1.2.3.4"}', $normalized['tool_calls'][0]['function']['arguments']);
    }

    public function test_message_without_tool_calls_passes_through(): void {
        $message = ['role' => 'assistant', 'content' => 'hello'];

        $normalized = mpu_normalize_ollama_assistant_message($message);

        $this->assertSame($message, $normalized);
    }

    public function test_multiple_tool_calls_each_normalized(): void {
        $message = [
            'role' => 'assistant',
            'tool_calls' => [
                ['function' => ['name' => 'a', 'arguments' => []]],
                ['function' => ['name' => 'b', 'arguments' => ['x' => 1]]],
                ['function' => ['name' => 'c', 'arguments' => []]],
            ],
        ];

        $normalized = mpu_normalize_ollama_assistant_message($message);

        $this->assertInstanceOf(stdClass::class, $normalized['tool_calls'][0]['function']['arguments']);
        $this->assertSame(['x' => 1], $normalized['tool_calls'][1]['function']['arguments']);
        $this->assertInstanceOf(stdClass::class, $normalized['tool_calls'][2]['function']['arguments']);
    }
}
