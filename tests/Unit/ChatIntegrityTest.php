<?php

use PHPUnit\Framework\TestCase;

final class ChatIntegrityTest extends TestCase {
    /** @var MPU_Test_Integrity_Log_Guard|null */
    private $log_guard;

    protected function setUp(): void {
        require_once MPU_TESTS_ROOT . '/tests/support/IntegrityLogGuard.php';
        $GLOBALS['_mpu_test_filters']    = [];
        $GLOBALS['_mpu_test_transients'] = [];
        $GLOBALS['_mpu_test_action_log'] = [];
        $this->log_guard = new MPU_Test_Integrity_Log_Guard();
    }

    protected function tearDown(): void {
        $GLOBALS['_mpu_test_filters']    = [];
        $GLOBALS['_mpu_test_transients'] = [];
        $problems = $this->log_guard->restore();
        $this->log_guard = null;
        $this->assertSame([], $problems, 'checksum-mismatch log was not restored cleanly');
    }

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

    /**
     * @dataProvider non_blocking_modes
     */
    public function test_mismatch_in_non_blocking_mode_lets_request_continue(string $mode): void {
        $this->use_mode($mode);

        $this->assertNull($this->verify_mismatch());
        $this->assertSame([$mode], $this->mismatch_decisions());
        $this->assertNotSame('', $this->log_guard->appended_log());
    }

    public function non_blocking_modes(): array {
        return [
            'audit' => ['audit'],
            'warn'  => ['warn'],
        ];
    }

    public function test_mismatch_in_block_mode_returns_409_error(): void {
        $this->use_mode('block');

        $result = $this->verify_mismatch();

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame(409, $result->get_error_data()['status']);
        $this->assertSame(['block'], $this->mismatch_decisions());
    }

    public function test_should_block_filter_overrides_mode_decision(): void {
        $this->use_mode('block');
        add_filter('mpu_chat_integrity_should_block', static function () {
            return false;
        });
        $this->assertNull($this->verify_mismatch());

        $GLOBALS['_mpu_test_filters'] = [];
        $this->use_mode('audit');
        add_filter('mpu_chat_integrity_should_block', static function () {
            return true;
        });
        $result = $this->verify_mismatch();
        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame(409, $result->get_error_data()['status']);
    }

    public function test_invalid_mode_filter_value_falls_back_to_unfiltered_mode(): void {
        $base = mpu_chat_integrity_mode();
        $this->assertContains($base, ['audit', 'warn', 'block']);

        $this->use_mode('bogus');

        $this->assertSame($base, mpu_chat_integrity_mode());
    }

    private function use_mode(string $mode): void {
        add_filter('mpu_chat_integrity_mode', static function () use ($mode) {
            return $mode;
        });
    }

    private function verify_mismatch() {
        $stored = [['role' => 'assistant', 'content' => 'original reply']];
        $sent   = [['role' => 'assistant', 'content' => 'edited reply']];
        $this->assertTrue(mpu_chat_integrity_store_history('integritymode01', $stored));

        return mpu_chat_integrity_verify_history('integritymode01', $sent);
    }

    private function mismatch_decisions(): array {
        $decisions = [];
        foreach ($GLOBALS['_mpu_test_action_log'] as $entry) {
            if ($entry[0] === 'mpu_chat_integrity_mismatch') {
                $decisions[] = $entry[1][0]['decision'];
            }
        }
        return $decisions;
    }
}
