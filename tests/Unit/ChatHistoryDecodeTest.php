<?php

use PHPUnit\Framework\TestCase;

/**
 * History params arrive already unslashed by WP_REST_Server, exactly as the
 * frontend's JSON.stringify() produced them. Decoding must not unslash again:
 * that broke the JSON on `"` or `\` (gift: 会話履歴の形式が正しくありません;
 * chat/dialog: history silently dropped) and rewrote newlines and backslashes.
 */
final class ChatHistoryDecodeTest extends TestCase {

    /**
     * @return array<string, array{0: string}>
     */
    public function contentProvider(): array {
        return [
            'double quotes'      => ['"魔法"って言ったよね'],
            'windows path'       => ['C:\\path\\to\\file'],
            'real newline'       => ["一行目\n二行目"],
            'literal backslash-n' => ['改行は \\n と書く'],
            'json-looking text'  => ['{"role":"user"} \\" \\\\'],
        ];
    }

    /**
     * @dataProvider contentProvider
     */
    public function test_json_string_round_trips_content_unchanged(string $content): void {
        $history = [
            ['role' => 'user', 'content' => $content, 'type' => 'chat'],
            ['role' => 'assistant', 'content' => $content, 'type' => 'give'],
        ];
        $raw = json_encode($history, JSON_UNESCAPED_UNICODE);

        $decoded = MPU_Chat_History_Service::decode_param($raw);

        $this->assertSame($history, $decoded);
        $this->assertSame($history, MPU_Chat_History_Service::normalize_history($decoded));
    }

    /**
     * @dataProvider contentProvider
     */
    public function test_already_decoded_array_is_passed_through_unchanged(string $content): void {
        $history = [['role' => 'assistant', 'content' => $content, 'type' => 'chat']];

        $decoded = MPU_Chat_History_Service::decode_param($history);

        $this->assertSame($history, $decoded);
        $this->assertSame($history, MPU_Chat_History_Service::normalize_history($decoded));
    }

    public function test_response_history_list_of_strings_decodes(): void {
        $lines = ['"ふーん"', 'C:\\path'];

        $this->assertSame($lines, MPU_Chat_History_Service::decode_param(json_encode($lines, JSON_UNESCAPED_UNICODE)));
    }

    /**
     * @return array<string, array{0: mixed}>
     */
    public function invalidProvider(): array {
        return [
            'broken json'   => ['[{"role":"user","content":"a"'],
            'json scalar'   => ['5'],
            'json string'   => ['"text"'],
            'json null'     => ['null'],
            'empty string'  => [''],
            'integer'       => [5],
            'null'          => [null],
        ];
    }

    /**
     * @dataProvider invalidProvider
     * @param mixed $raw
     */
    public function test_non_array_values_return_null($raw): void {
        $this->assertNull(MPU_Chat_History_Service::decode_param($raw));
    }
}
