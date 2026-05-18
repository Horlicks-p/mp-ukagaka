<?php

use PHPUnit\Framework\TestCase;

final class InputRoleTest extends TestCase {
    protected function tearDown(): void {
        $GLOBALS['_mpu_test_current_user_can'] = false;
        $GLOBALS['_mpu_test_is_user_logged_in'] = false;
    }

    public function test_resolve_prefers_explicit_known_role(): void {
        $this->assertSame(MPU_Input_Role::SYSTEM, MPU_Input_Role::resolve(['role' => 'system']));
    }

    public function test_resolve_maps_system_sources_to_system_role(): void {
        $this->assertSame(MPU_Input_Role::SYSTEM, MPU_Input_Role::resolve(['source' => 'cron']));
    }

    public function test_resolve_uses_wordpress_user_state(): void {
        $GLOBALS['_mpu_test_current_user_can'] = true;
        $this->assertSame(MPU_Input_Role::ADMIN, MPU_Input_Role::resolve());

        $GLOBALS['_mpu_test_current_user_can'] = false;
        $GLOBALS['_mpu_test_is_user_logged_in'] = true;
        $this->assertSame(MPU_Input_Role::SUBSCRIBER, MPU_Input_Role::resolve());
    }

    public function test_ability_gate_allows_only_role_whitelist(): void {
        $this->assertTrue(MPU_Input_Role::can_use_ability('mp-ukagaka/get-popular-posts', MPU_Input_Role::VISITOR));
        $this->assertFalse(MPU_Input_Role::can_use_ability('mp-ukagaka/ban-ip', MPU_Input_Role::VISITOR));
        $this->assertTrue(MPU_Input_Role::can_use_ability('mp-ukagaka/ban-ip', MPU_Input_Role::ADMIN));
        $this->assertTrue(MPU_Input_Role::can_use_ability('mp-ukagaka__get-popular-posts', MPU_Input_Role::VISITOR));
    }
}
