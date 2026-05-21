<?php

use PHPUnit\Framework\TestCase;

final class RuntimeStateTest extends TestCase {
    private string $token = '0123456789abcdef0123456789abcdef';

    protected function setUp(): void {
        $GLOBALS['_mpu_test_transients'] = [];
        $GLOBALS['_mpu_test_filters'] = [];
        $GLOBALS['_mpu_test_is_user_logged_in'] = false;
        $GLOBALS['_mpu_test_current_user_id'] = 0;
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

        set_transient('mpu_sess_' . $this->token, [
            'ip_hash' => md5('127.0.0.1' . wp_salt('auth')),
            'created' => time(),
        ], 2 * HOUR_IN_SECONDS);
    }

    public function test_valid_state_can_be_written_and_read(): void {
        $this->assertTrue(mpu_set_runtime_state('thinking', $this->token));

        $state = mpu_get_runtime_state($this->token);

        $this->assertSame('thinking', $state['state']);
        $this->assertIsInt($state['ts']);
    }

    public function test_invalid_state_is_rejected(): void {
        $this->assertFalse(mpu_set_runtime_state('dancing', $this->token));
        $this->assertNull(mpu_get_runtime_state($this->token));
    }

    public function test_invalid_token_does_not_create_scope_key(): void {
        $this->assertNull(mpu_runtime_state_scope_key('not-a-token'));
        $this->assertFalse(mpu_set_runtime_state('thinking', 'not-a-token'));
    }

    public function test_anonymous_request_without_token_has_no_scope(): void {
        $this->assertNull(mpu_runtime_state_scope_key());
        $this->assertFalse(mpu_set_runtime_state('thinking'));
    }

    public function test_scope_key_hashes_token_without_leaking_raw_value(): void {
        $key = mpu_runtime_state_scope_key($this->token);

        $this->assertIsString($key);
        $this->assertStringNotContainsString($this->token, $key);
        $this->assertStringContainsString(hash('sha256', $this->token), $key);
    }

    public function test_clear_removes_runtime_state(): void {
        mpu_set_runtime_state('speaking', $this->token);

        mpu_clear_runtime_state($this->token);

        $this->assertNull(mpu_get_runtime_state($this->token));
    }

    public function test_ttl_filter_is_clamped(): void {
        add_filter('mpu_runtime_state_ttl', static function () {
            return 999999;
        });

        $this->assertSame(900, mpu_runtime_state_ttl());

        $GLOBALS['_mpu_test_filters'] = [];
        add_filter('mpu_runtime_state_ttl', static function () {
            return 1;
        });

        $this->assertSame(60, mpu_runtime_state_ttl());
    }

    public function test_logged_in_user_scope_does_not_need_session_token(): void {
        $GLOBALS['_mpu_test_is_user_logged_in'] = true;
        $GLOBALS['_mpu_test_current_user_id'] = 42;

        $this->assertTrue(mpu_set_runtime_state('chatting'));

        $state = mpu_get_runtime_state();

        $this->assertSame('chatting', $state['state']);
    }
}
