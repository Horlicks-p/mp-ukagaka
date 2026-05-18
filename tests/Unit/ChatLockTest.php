<?php

use PHPUnit\Framework\TestCase;

final class ChatLockTest extends TestCase {
    protected function setUp(): void {
        $GLOBALS['_mpu_test_options'] = [];
        $GLOBALS['_mpu_test_filters'] = [];
        $GLOBALS['_mpu_test_action_log'] = [];
    }

    public function test_acquire_writes_lock_option(): void {
        $lock = MPU_Chat_Lock::acquire('session-1', ['route' => 'user']);

        $this->assertIsArray($lock);
        $this->assertArrayHasKey('token', $lock);
        $this->assertTrue(MPU_Chat_Lock::is_locked('session-1'));
        $this->assertSame('user', $lock['context']['route']);
    }

    public function test_acquire_conflict_returns_wp_error(): void {
        $first = MPU_Chat_Lock::acquire('session-1');
        $second = MPU_Chat_Lock::acquire('session-1');

        $this->assertIsArray($first);
        $this->assertInstanceOf(WP_Error::class, $second);
        $this->assertSame('mpu_chat_lock_busy', $second->get_error_code());
        $this->assertSame(['status' => 429], $second->get_error_data());
    }

    public function test_release_allows_next_acquire(): void {
        $first = MPU_Chat_Lock::acquire('session-1');

        $this->assertTrue(MPU_Chat_Lock::release('session-1', $first['token']));
        $this->assertFalse(MPU_Chat_Lock::is_locked('session-1'));

        $second = MPU_Chat_Lock::acquire('session-1');
        $this->assertIsArray($second);
    }

    public function test_expired_lock_can_be_replaced(): void {
        $key = MPU_Chat_Lock::option_key('session-1');
        add_option($key, [
            'token' => 'old-token',
            'acquired_at' => time() - 120,
            'expires_at' => time() - 60,
            'context' => [],
        ], '', 'no');

        $lock = MPU_Chat_Lock::acquire('session-1');

        $this->assertIsArray($lock);
        $this->assertNotSame('old-token', $lock['token']);
    }

    public function test_token_mismatch_does_not_release_existing_lock(): void {
        $lock = MPU_Chat_Lock::acquire('session-1');

        $this->assertFalse(MPU_Chat_Lock::release('session-1', 'wrong-token'));
        $this->assertTrue(MPU_Chat_Lock::is_locked('session-1'));
        $this->assertTrue(MPU_Chat_Lock::release('session-1', $lock['token']));
    }

    public function test_existing_option_simulates_race_winner(): void {
        $key = MPU_Chat_Lock::option_key('session-1');
        add_option($key, [
            'token' => 'race-winner',
            'acquired_at' => time(),
            'expires_at' => time() + 60,
            'context' => [],
        ], '', 'no');

        $lock = MPU_Chat_Lock::acquire('session-1');

        $this->assertInstanceOf(WP_Error::class, $lock);
        $this->assertSame('race-winner', get_option($key)['token']);
    }

    public function test_ttl_filter_controls_expiration_window(): void {
        add_filter('mpu_chat_lock_ttl', static function () {
            return 30;
        });

        $lock = MPU_Chat_Lock::acquire('session-1');

        $this->assertGreaterThanOrEqual(29, $lock['expires_at'] - $lock['acquired_at']);
        $this->assertLessThanOrEqual(30, $lock['expires_at'] - $lock['acquired_at']);
    }
}
