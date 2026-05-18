<?php

use PHPUnit\Framework\TestCase;

final class EncryptionTest extends TestCase {
    public function test_encrypt_and_decrypt_api_key_round_trips(): void {
        $plain = 'sk-test-secret';

        $encrypted = mpu_encrypt_api_key($plain);

        $this->assertNotSame($plain, $encrypted);
        $this->assertTrue(mpu_is_api_key_encrypted($encrypted));
        $this->assertSame($plain, mpu_decrypt_api_key($encrypted));
    }

    public function test_encrypt_api_key_is_idempotent_for_existing_encrypted_value(): void {
        $encrypted = mpu_encrypt_api_key('sk-test-secret');

        $this->assertSame($encrypted, mpu_encrypt_api_key($encrypted));
    }
}
