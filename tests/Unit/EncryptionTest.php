<?php

use PHPUnit\Framework\TestCase;

final class EncryptionTest extends TestCase {
    public function test_encrypt_and_decrypt_api_key_round_trips(): void {
        $plain = 'sk-test-secret';

        $encrypted = mpu_encrypt_api_key($plain);

        $this->assertNotSame($plain, $encrypted);
        $this->assertStringStartsWith('mpu_enc2:', $encrypted);
        $this->assertTrue(mpu_is_api_key_encrypted($encrypted));
        $this->assertSame($plain, mpu_decrypt_api_key($encrypted));
    }

    public function test_encrypt_api_key_is_idempotent_for_existing_encrypted_value(): void {
        $encrypted = mpu_encrypt_api_key('sk-test-secret');

        $this->assertSame($encrypted, mpu_encrypt_api_key($encrypted));
    }

    public function test_decrypt_api_key_rejects_tampered_aead_ciphertext(): void {
        $encrypted = mpu_encrypt_api_key('sk-test-secret');
        $payload = base64_decode(substr($encrypted, 9), true);
        $payload[strlen($payload) - 1] = chr(ord($payload[strlen($payload) - 1]) ^ 1);
        $tampered = 'mpu_enc2:' . base64_encode($payload);

        $this->assertSame('', mpu_decrypt_api_key($tampered));
    }

    public function test_decrypt_api_key_keeps_legacy_cbc_compatibility(): void {
        $plain = 'sk-legacy-secret';
        $method = 'AES-256-CBC';
        $key = mpu_get_encryption_key();
        $iv = str_repeat('a', openssl_cipher_iv_length($method));
        $encrypted = openssl_encrypt($plain, $method, $key, OPENSSL_RAW_DATA, $iv);
        $legacy = 'mpu_enc:' . base64_encode($iv . $encrypted);

        $this->assertSame($plain, mpu_decrypt_api_key($legacy));
    }

    public function test_decrypt_api_key_keeps_legacy_obfuscation_compatibility(): void {
        $plain = 'sk-obfuscated-secret';
        $legacy = 'mpu_obf:' . base64_encode(strrev($plain) . '|' . substr(md5($plain), 0, 8));

        $this->assertTrue(mpu_is_api_key_encrypted($legacy));
        $this->assertSame($plain, mpu_decrypt_api_key($legacy));
    }
}
