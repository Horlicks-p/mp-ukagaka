<?php

use PHPUnit\Framework\TestCase;

final class SessionEventTest extends TestCase {
    public function test_kind_for_legacy_event_maps_known_events(): void {
        $this->assertSame(MPU_Session_Event::STREAM_DELTA, MPU_Session_Event::kind_for_legacy_event('delta'));
        $this->assertSame(MPU_Session_Event::STREAM_STATUS, MPU_Session_Event::kind_for_legacy_event('start'));
        $this->assertSame(MPU_Session_Event::STREAM_DONE, MPU_Session_Event::kind_for_legacy_event('done'));
        $this->assertSame(MPU_Session_Event::STREAM_ERROR, MPU_Session_Event::kind_for_legacy_event('error'));
        $this->assertSame(MPU_Session_Event::TOOL_RESULT, MPU_Session_Event::kind_for_legacy_event('tool_result'));
        $this->assertSame(MPU_Session_Event::NONCE_REFRESH, MPU_Session_Event::kind_for_legacy_event('nonce'));
    }

    public function test_kind_for_legacy_event_preserves_unknown_events(): void {
        $this->assertSame('custom.event', MPU_Session_Event::kind_for_legacy_event('custom.event'));
    }

    public function test_build_creates_event_envelope(): void {
        $event = MPU_Session_Event::build(MPU_Session_Event::STREAM_STATUS, ['message' => 'ready']);

        $this->assertSame('00000000-0000-4000-8000-000000000000', $event['eventId']);
        $this->assertSame(MPU_Session_Event::STREAM_STATUS, $event['kind']);
        $this->assertSame(['message' => 'ready'], $event['payload']);
        $this->assertArrayHasKey('ts', $event);
    }
}
