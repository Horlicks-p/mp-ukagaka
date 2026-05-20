<?php

/**
 * API key encryption and provider helper functions.
 *
 * @package MP_Ukagaka
 * @subpackage Utility
 */

if (!defined('ABSPATH')) {
    exit();
}

// ========================================
// API Key 加密/解密函數
// ========================================

/**
 * 獲取加密密鑰
 * 使用 WordPress 的 AUTH_KEY 作為基礎，確保每個站點都有唯一的密鑰
 * 
 * @return string 加密密鑰
 */
function mpu_get_encryption_key()
{
    // 使用 WordPress 的 AUTH_KEY 和一個固定的鹽值
    $base_key = defined('AUTH_KEY') ? AUTH_KEY : 'mpu-default-key-' . get_site_url();
    return hash('sha256', $base_key . 'mpu_api_key_encryption', true);
}

/**
 * 加密 API Key
 * 
 * @param string $api_key 明文 API Key
 * @return string 加密後的 API Key（base64 編碼）
 */
function mpu_encrypt_api_key($api_key)
{
    if (empty($api_key)) {
        return '';
    }

    // 如果已經加密過（以 mpu_enc: 開頭），直接返回
    if (strpos($api_key, 'mpu_enc:') === 0) {
        return $api_key;
    }

    $key = mpu_get_encryption_key();

    // 檢查 OpenSSL 是否可用
    if (function_exists('openssl_encrypt')) {
        $method = 'AES-256-CBC';
        $iv_length = openssl_cipher_iv_length($method);
        $iv = openssl_random_pseudo_bytes($iv_length);

        $encrypted = openssl_encrypt($api_key, $method, $key, OPENSSL_RAW_DATA, $iv);

        if ($encrypted !== false) {
            // 將 IV 和加密數據一起編碼
            return 'mpu_enc:' . base64_encode($iv . $encrypted);
        }
    }

    // OpenSSL 不可用時，使用簡單的混淆（不是真正的加密，但比明文好）
    $obfuscated = base64_encode(strrev($api_key) . '|' . substr(md5($api_key), 0, 8));
    return 'mpu_obf:' . $obfuscated;
}

/**
 * 解密 API Key
 * 
 * @param string $encrypted_key 加密的 API Key
 * @return string 明文 API Key
 */
function mpu_decrypt_api_key($encrypted_key)
{
    if (empty($encrypted_key)) {
        return '';
    }

    // 如果是 OpenSSL 加密的
    if (strpos($encrypted_key, 'mpu_enc:') === 0) {
        $key = mpu_get_encryption_key();
        $data = base64_decode(substr($encrypted_key, 8));

        if ($data !== false && function_exists('openssl_decrypt')) {
            $method = 'AES-256-CBC';
            $iv_length = openssl_cipher_iv_length($method);
            $iv = substr($data, 0, $iv_length);
            $encrypted = substr($data, $iv_length);

            $decrypted = openssl_decrypt($encrypted, $method, $key, OPENSSL_RAW_DATA, $iv);

            if ($decrypted !== false) {
                return $decrypted;
            }
        }

        // 解密失敗，返回空
        mpu_log_error('API Key 解密失敗');
        return '';
    }

    // 如果是混淆的
    if (strpos($encrypted_key, 'mpu_obf:') === 0) {
        $data = base64_decode(substr($encrypted_key, 8));
        if ($data !== false) {
            $parts = explode('|', $data);
            if (count($parts) >= 1) {
                return strrev($parts[0]);
            }
        }
        return '';
    }

    // 如果既沒有加密前綴也沒有混淆前綴，則是明文（向後兼容）
    return $encrypted_key;
}

/**
 * 檢查 API Key 是否已加密
 * 
 * @param string $api_key API Key
 * @return bool 是否已加密
 */
function mpu_is_api_key_encrypted($api_key)
{
    return strpos($api_key, 'mpu_enc:') === 0 || strpos($api_key, 'mpu_obf:') === 0;
}

/**
 * 獲取指定 AI 提供商的解密後 API Key
 * 統一處理各提供商的 API Key 獲取邏輯，減少代碼重複
 * 
 * @param string $provider AI 提供商名稱（gemini, openai, claude, ollama）
 * @param array|null $mpu_opt 選項陣列，如果為 null 則自動獲取
 * @return string 解密後的 API Key，或空字串（Ollama 不需要 API Key）
 */
function mpu_get_provider_api_key($provider, $mpu_opt = null)
{
    if ($mpu_opt === null) {
        $mpu_opt = mpu_get_option();
    }

    // Ollama 不需要 API Key
    if ($provider === 'ollama') {
        return '';
    }

    $api_key_encrypted = '';

    switch ($provider) {
        case 'openai':
            // 向後兼容：優先使用新設定鍵，否則使用舊設定鍵
            $api_key_encrypted = !empty($mpu_opt['llm_openai_api_key'])
                ? $mpu_opt['llm_openai_api_key']
                : (!empty($mpu_opt['openai_api_key']) ? $mpu_opt['openai_api_key'] : '');
            break;
        case 'claude':
            $api_key_encrypted = !empty($mpu_opt['llm_claude_api_key'])
                ? $mpu_opt['llm_claude_api_key']
                : (!empty($mpu_opt['claude_api_key']) ? $mpu_opt['claude_api_key'] : '');
            break;
        case 'gemini':
        default:
            $api_key_encrypted = !empty($mpu_opt['llm_gemini_api_key'])
                ? $mpu_opt['llm_gemini_api_key']
                : (!empty($mpu_opt['ai_api_key']) ? $mpu_opt['ai_api_key'] : '');
            break;
    }

    // 解密並返回 API Key
    return mpu_decrypt_api_key($api_key_encrypted);
}

/**
 * 獲取當前啟用的 AI 提供商名稱
 * 
 * @param array|null $mpu_opt 選項陣列，如果為 null 則自動獲取
 * @return string AI 提供商名稱（gemini, openai, claude, ollama）
 */
function mpu_get_current_provider($mpu_opt = null)
{
    if ($mpu_opt === null) {
        $mpu_opt = mpu_get_option();
    }

    // 向後兼容：優先使用 llm_provider，否則使用 ai_provider
    return $mpu_opt['llm_provider'] ?? $mpu_opt['ai_provider'] ?? 'gemini';
}
