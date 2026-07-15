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
                ['role' => 'assistant', 'content' => 'four'],
            ],
            mpu_chat_integrity_slice_for_store($history, 3)
        );
    }

    public function test_slice_for_store_uses_checksum_messages_for_window(): void {
        $before_auto_talk = [
            ['role' => 'user', 'content' => 'one'],
            ['role' => 'assistant', 'content' => 'reply one', 'type' => 'chat'],
            ['role' => 'user', 'content' => 'two'],
            ['role' => 'assistant', 'content' => 'reply two', 'type' => 'chat'],
            ['role' => 'user', 'content' => 'gift anchor', 'type' => 'synthetic'],
            ['role' => 'assistant', 'content' => 'gift reply', 'type' => 'give'],
            ['role' => 'user', 'content' => 'three'],
            ['role' => 'assistant', 'content' => 'reply three', 'type' => 'chat'],
        ];

        $after_frontend_auto_talk = array_merge(
            $before_auto_talk,
            [
                ['role' => 'user', 'content' => 'monologue anchor', 'type' => 'synthetic'],
                ['role' => 'assistant', 'content' => 'auto reply', 'type' => 'auto_talk'],
            ]
        );

        $expected = [
            ['role' => 'assistant', 'content' => 'reply two'],
            ['role' => 'assistant', 'content' => 'reply three'],
        ];

        $this->assertSame($expected, mpu_chat_integrity_slice_for_store($before_auto_talk, 2));
        $this->assertSame($expected, mpu_chat_integrity_slice_for_store($after_frontend_auto_talk, 2));
    }

    public function test_raw_transport_window_matches_store_after_non_chat_append(): void {
        $history = [];
        for ($turn = 1; $turn <= 20; $turn++) {
            $history[] = ['role' => 'user', 'content' => 'user ' . $turn, 'type' => 'chat'];
            $history[] = ['role' => 'assistant', 'content' => 'reply ' . $turn, 'type' => 'chat'];
        }

        $after_gift = array_merge(
            $history,
            [
                ['role' => 'user', 'content' => 'gift anchor', 'type' => 'synthetic'],
                ['role' => 'assistant', 'content' => 'gift reply', 'type' => 'give'],
            ]
        );
        $stored = mpu_chat_integrity_slice_for_store($after_gift, 10);

        // The REST controller keeps a separate 20-entry LLM context. Using that
        // truncated context for checksum storage loses older chat replies.
        $truncated_store = mpu_chat_integrity_slice_for_store(
            array_slice($after_gift, -20),
            10
        );

        $next_request = $after_gift;
        $next_request[] = ['role' => 'user', 'content' => 'next user', 'type' => 'chat'];
        $transport = array_slice($next_request, -40);
        $verified = mpu_chat_integrity_slice_for_store($transport, 10);

        $this->assertCount(10, $stored);
        $this->assertNotSame($truncated_store, $verified);
        $this->assertSame($stored, $verified);
        $this->assertSame(
            mpu_chat_integrity_compute_checksum($stored),
            mpu_chat_integrity_compute_checksum($verified)
        );
    }
}
