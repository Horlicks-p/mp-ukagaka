<?php

use PHPUnit\Framework\TestCase;

if (!class_exists('WP_REST_Request')) {
    class WP_REST_Request {
        private $params;

        public function __construct(array $params = []) {
            $this->params = $params;
        }

        public function get_param($key) {
            return $this->params[$key] ?? null;
        }
    }
}

if (!function_exists('sanitize_textarea_field')) {
    function sanitize_textarea_field($value) {
        return trim(strip_tags((string) $value));
    }
}

if (!function_exists('mpu_load_personality_emoji_config')) {
    function mpu_load_personality_emoji_config($personality_id = null) {
        return ['supported' => $GLOBALS['_mpu_test_emoji_supported'] ?? ['laugh', 'angry', 'happy', 'sad', 'surprised']];
    }
}

if (!function_exists('mpu_load_personality_manifest')) {
    function mpu_load_personality_manifest($personality_id = null) {
        return [];
    }
}

if (!function_exists('mpu_analyze_emoji_from_text')) {
    function mpu_analyze_emoji_from_text($text, $personality_id = null) {
        return null;
    }
}

if (!function_exists('mpu_filter_thinking_content')) {
    function mpu_filter_thinking_content($response) {
        if (empty($response) || !is_string($response)) {
            return $response;
        }
        $response = preg_replace('/<think>.*?<\/think>/is', '', $response);
        $response = preg_replace('/<think>.*$/is', '', $response);
        return is_string($response) ? trim($response) : '';
    }
}

require_once MPU_TESTS_ROOT . '/includes/llm/response-normalizer.php';
require_once MPU_TESTS_ROOT . '/includes/integrations/akismet-integration.php';

final class SpamEventResponseTest extends TestCase {
    protected function setUp(): void {
        $GLOBALS['_mpu_test_transients'] = [];
        $GLOBALS['_mpu_test_transient_expirations'] = [];
        $GLOBALS['_mpu_test_emoji_supported'] = ['smirk', 'laugh', 'thinking'];
    }

    public function test_event_response_uses_normalized_text_for_payload_and_checksum(): void {
        $history = [
            [
                'role'    => 'user',
                'content' => 'security event detected',
                'type'    => 'synthetic',
            ],
        ];
        $request = new WP_REST_Request([
            'session_id' => 'event-session',
            'history'    => wp_json_encode($history),
        ]);

        $payload = mpu_finalize_spam_event_response(
            $request,
            'Google Bot is quietly walking around. [smirk]',
            [
                'action'  => 'ai_crawler_alert',
                'crawler' => 'Google Bot',
                'company' => 'Google',
            ]
        );

        $this->assertSame('Google Bot is quietly walking around.', $payload['msg']);
        $this->assertSame('smirk.png', $payload['emoji']);
        $this->assertSame(['smirk'], $payload['emotion_tags']);
        $this->assertSame('ai_crawler_alert', $payload['action']);

        $clean_frontend_history = [
            [
                'role'    => 'user',
                'content' => 'security event detected',
                'type'    => 'synthetic',
            ],
            [
                'role'    => 'assistant',
                'content' => 'Google Bot is quietly walking around.',
                'type'    => 'event',
            ],
        ];
        $expected_checksum = mpu_chat_integrity_compute_checksum($clean_frontend_history);

        $this->assertSame(
            $expected_checksum,
            get_transient(mpu_chat_integrity_transient_key('event-session'))
        );
    }
}
