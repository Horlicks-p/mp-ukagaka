<?php

use PHPUnit\Framework\TestCase;

final class ChatIntegrityTest extends TestCase {
    public function test_filter_messages_keeps_only_chat_assistant_messages(): void {
        $messages = [
            ['role' => 'user', 'content' => 'ignored'],
            ['role' => 'assistant', 'content' => 'kept'],
            ['role' => 'assistant', 'type' => 'status', 'content' => 'ignored status'],
            ['role' => 'assistant', 'content' => ['nested' => true]],
            ['content' => 'missing role'],
        ];

        $this->assertSame(
            [
                ['role' => 'assistant', 'content' => 'kept'],
                ['role' => 'assistant', 'content' => wp_json_encode(['nested' => true])],
            ],
            mpu_chat_integrity_filter_messages($messages)
        );
    }

    public function test_compute_checksum_is_based_on_filtered_messages(): void {
        $messages = [
            ['role' => 'user', 'content' => 'ignored'],
            ['role' => 'assistant', 'content' => 'reply'],
        ];

        $expected = md5(wp_json_encode([
            ['role' => 'assistant', 'content' => 'reply'],
        ]));

        $this->assertSame($expected, mpu_chat_integrity_compute_checksum($messages));
    }

    public function test_slice_for_store_removes_orphan_assistant_messages_before_slicing(): void {
        $history = [
            ['role' => 'assistant', 'content' => 'orphan'],
            ['role' => 'user', 'content' => 'one'],
            ['role' => 'assistant', 'content' => 'two'],
            ['role' => 'user', 'content' => 'three'],
            ['role' => 'assistant', 'content' => 'four'],
        ];

        $this->assertSame(
            [
                ['role' => 'assistant', 'content' => 'two'],
                ['role' => 'user', 'content' => 'three'],
                ['role' => 'assistant', 'content' => 'four'],
            ],
            mpu_chat_integrity_slice_for_store($history, 3)
        );
    }
}
