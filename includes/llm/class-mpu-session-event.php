<?php
/**
 * Session event envelope helpers.
 *
 * @package MP_Ukagaka
 * @subpackage LLM
 */

if (!defined('ABSPATH')) {
    exit();
}

class MPU_Session_Event {
    const STREAM_DELTA   = 'stream.delta';
    const STREAM_STATUS  = 'stream.status';
    const STREAM_DONE    = 'stream.done';
    const STREAM_ERROR   = 'stream.error';
    const TOOL_REQUEST   = 'tool.request';
    const TOOL_RESULT    = 'tool.result';
    const NONCE_REFRESH  = 'nonce.refresh';

    /**
     * Build a transport-neutral event envelope.
     *
     * @param string $kind    Event kind.
     * @param array  $payload Event payload.
     * @return array
     */
    public static function build(string $kind, array $payload = []): array {
        return [
            'eventId' => function_exists('wp_generate_uuid4') ? wp_generate_uuid4() : uniqid('mpu_evt_', true),
            'ts'      => gmdate('c'),
            'kind'    => (string) $kind,
            'payload' => $payload,
        ];
    }

    /**
     * Map legacy SSE event names to session event kinds.
     *
     * @param string $event Legacy event name.
     * @return string
     */
    public static function kind_for_legacy_event(string $event): string {
        switch ((string) $event) {
            case 'delta':
                return self::STREAM_DELTA;
            case 'status':
            case 'start':
                return self::STREAM_STATUS;
            case 'done':
                return self::STREAM_DONE;
            case 'error':
                return self::STREAM_ERROR;
            case 'tool_result':
                return self::TOOL_RESULT;
            case 'nonce':
                return self::NONCE_REFRESH;
            default:
                return (string) $event;
        }
    }
}

