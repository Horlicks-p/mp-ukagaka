<?php

use PHPUnit\Framework\TestCase;

final class ObservationBufferTest extends TestCase {
    protected function setUp(): void {
        $GLOBALS['_mpu_test_transients'] = [];
        $GLOBALS['_mpu_test_transient_expirations'] = [];
        $GLOBALS['_mpu_test_filters'] = [];
        $GLOBALS['_mpu_test_posts'] = [];
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
    }

    public function test_push_requires_valid_session_token(): void {
        $this->assertFalse(MPU_Observation_Buffer::push('page_view', 'post:1', null));
        $this->assertSame([], MPU_Observation_Buffer::drain(null));
    }

    public function test_page_view_is_normalized_from_server_post_title(): void {
        $token = mpu_generate_session_token();
        $GLOBALS['_mpu_test_posts'][123] = (object) [
            'post_status' => 'publish',
            'post_password' => '',
            'post_title' => '平方根的計算-入門指南',
        ];

        $this->assertTrue(MPU_Observation_Buffer::push('page_view', 'post:123:<script>x</script>', $token));
        $entries = MPU_Observation_Buffer::peek($token);

        $this->assertCount(1, $entries);
        $this->assertSame('page_view', $entries[0]['type']);
        $this->assertSame('post:123:平方根的計算-入門指南', $entries[0]['content']);
    }

    public function test_invalid_page_view_pattern_is_dropped(): void {
        $token = mpu_generate_session_token();

        $this->assertFalse(MPU_Observation_Buffer::push('page_view', '[click](https://evil.test)', $token));
        $this->assertSame([], MPU_Observation_Buffer::peek($token));
    }

    public function test_bot_signal_is_rejected_in_mvp(): void {
        $token = mpu_generate_session_token();

        $this->assertFalse(MPU_Observation_Buffer::push('bot_signal', 'soft_suspicious', $token));
        $this->assertSame([], MPU_Observation_Buffer::peek($token));
    }

    public function test_cross_token_isolation(): void {
        $token_a = mpu_generate_session_token();
        $token_b = mpu_generate_session_token();

        MPU_Observation_Buffer::push('lifecycle_event', 'wake', $token_a);

        $this->assertSame([], MPU_Observation_Buffer::drain($token_b));
        $this->assertCount(1, MPU_Observation_Buffer::drain($token_a));
    }

    public function test_drain_is_at_most_once(): void {
        $token = mpu_generate_session_token();
        $GLOBALS['_mpu_test_posts'][1] = (object) [
            'post_status' => 'publish',
            'post_password' => '',
            'post_title' => 'Article',
        ];

        $this->assertTrue(MPU_Observation_Buffer::push('page_view', 'post:1', $token));
        $this->assertCount(1, MPU_Observation_Buffer::drain($token));
        $this->assertSame([], MPU_Observation_Buffer::drain($token));
    }

    public function test_dedupe_moves_same_target_to_tail(): void {
        $token = mpu_generate_session_token();
        $GLOBALS['_mpu_test_posts'][1] = (object) [
            'post_status' => 'publish',
            'post_password' => '',
            'post_title' => 'One',
        ];
        $GLOBALS['_mpu_test_posts'][2] = (object) [
            'post_status' => 'publish',
            'post_password' => '',
            'post_title' => 'Two',
        ];

        MPU_Observation_Buffer::push('page_view', 'post:1', $token);
        MPU_Observation_Buffer::push('page_view', 'post:2', $token);
        MPU_Observation_Buffer::push('page_view', 'post:1', $token);

        $entries = MPU_Observation_Buffer::peek($token);
        $this->assertCount(2, $entries);
        $this->assertSame('post:2:Two', $entries[0]['content']);
        $this->assertSame('post:1:One', $entries[1]['content']);
    }

    public function test_touch_dedupe_replaces_same_part(): void {
        $token = mpu_generate_session_token();

        MPU_Observation_Buffer::push('touch', 'head:1', $token);
        MPU_Observation_Buffer::push('touch', 'hand:1', $token);
        MPU_Observation_Buffer::push('touch', 'head:3', $token);

        $entries = MPU_Observation_Buffer::peek($token);
        $this->assertCount(2, $entries);
        $this->assertSame('hand:1', $entries[0]['content']);
        $this->assertSame('head:3', $entries[1]['content']);
    }

    public function test_ring_buffer_keeps_latest_five(): void {
        $token = mpu_generate_session_token();
        for ($i = 1; $i <= 6; $i++) {
            MPU_Observation_Buffer::push('lifecycle_event', 'wake:' . $i, $token);
        }

        $entries = MPU_Observation_Buffer::peek($token);
        $this->assertCount(5, $entries);
        $this->assertSame('wake:2', $entries[0]['content']);
        $this->assertSame('wake:6', $entries[4]['content']);
    }

    public function test_stay_duration_clamps_seconds(): void {
        $token = mpu_generate_session_token();
        $this->assertTrue(MPU_Observation_Buffer::push('stay_duration', 'post:1:8000s', $token));

        $entries = MPU_Observation_Buffer::peek($token);
        $this->assertSame('post:1:7200s', $entries[0]['content']);
    }

    public function test_private_post_is_not_exposed(): void {
        $token = mpu_generate_session_token();
        $GLOBALS['_mpu_test_posts'][5] = (object) [
            'post_status' => 'private',
            'post_password' => '',
            'post_title' => 'Secret',
        ];

        MPU_Observation_Buffer::push('page_view', 'post:5', $token);
        $entries = MPU_Observation_Buffer::peek($token);

        $this->assertSame('post:5:[non-public]', $entries[0]['content']);
    }

    public function test_observation_ttl_filter_clamps_lower_and_upper_bounds(): void {
        add_filter('mpu_observation_buffer_ttl', static function () {
            return 1;
        });
        $token = mpu_generate_session_token();
        MPU_Observation_Buffer::push('lifecycle_event', 'wake', $token);
        $key = 'mpu_obs_' . hash('sha256', $token);

        $this->assertTrue(array_key_exists($key, $GLOBALS['_mpu_test_transient_expirations']));
        $this->assertSame(300, $GLOBALS['_mpu_test_transient_expirations'][$key]);

        unset($GLOBALS['_mpu_test_transients'][$key]);
        $GLOBALS['_mpu_test_transient_expirations'] = [];
        $GLOBALS['_mpu_test_filters'] = [];

        add_filter('mpu_observation_buffer_ttl', static function () {
            return 99999;
        });
        $this->assertTrue(MPU_Observation_Buffer::push('lifecycle_event', 'wake', $token));

        $this->assertSame(7200, $GLOBALS['_mpu_test_transient_expirations'][$key]);
    }

    public function test_control_characters_are_stripped(): void {
        $token = mpu_generate_session_token();

        MPU_Observation_Buffer::push('lifecycle_event', "wake\x00", $token);
        $entries = MPU_Observation_Buffer::peek($token);

        $this->assertSame('wake', $entries[0]['content']);
    }
}
