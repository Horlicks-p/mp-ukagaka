<?php

/**
 * Chat History Service
 *
 * 集中管理 chat history 的 session ID 正規化、歷史解析、checksum 寫入與驗證。
 * 消除 class-mpu-rest-chat.php 中四處重複的 integrity 邏輯。
 *
 * @package MP_Ukagaka
 * @subpackage Chat
 */

if (!defined('ABSPATH')) {
    exit();
}

class MPU_Chat_History_Service {

    const ALLOWED_MSG_TYPES = [
        'chat', 'synthetic', 'auto_talk', 'greet',
        'context', 'event', 'touch_decoration', 'touch_zone',
    ];

    /**
     * 從 REST request 取得並正規化 session ID。
     */
    public static function get_session_id(WP_REST_Request $request): string {
        $param = $request->get_param('session_id') ?: $request->get_param('chat_session_id');
        return function_exists('mpu_chat_integrity_normalize_session_id')
            ? mpu_chat_integrity_normalize_session_id($param)
            : '';
    }

    /**
     * 解析並正規化 request 中的 history 參數。
     * 用於 chat/context 與 chat/greet 端點（auto-response 路徑）。
     *
     * @return array<int, array{role: string, content: string, type: string}>
     */
    public static function parse_history_from_request(WP_REST_Request $request): array {
        $history = [];
        $raw     = $request->get_param('history');
        if (empty($raw)) {
            return $history;
        }

        $decoded = is_string($raw)
            ? json_decode(wp_unslash($raw), true)
            : (array) $raw;

        if (!is_array($decoded)) {
            return $history;
        }

        foreach ($decoded as $msg) {
            if (!is_array($msg) || empty($msg['role']) || !isset($msg['content'])) {
                continue;
            }
            $role = in_array($msg['role'], ['user', 'assistant'], true) ? $msg['role'] : null;
            if ($role === null) {
                continue;
            }
            $content = sanitize_textarea_field(wp_unslash((string) ($msg['content'] ?? '')));
            if ($content === '') {
                continue;
            }
            $type = in_array($msg['type'] ?? '', self::ALLOWED_MSG_TYPES, true)
                ? (string) $msg['type'] : 'chat';
            $history[] = ['role' => $role, 'content' => $content, 'type' => $type];
        }

        return $history;
    }

    /**
     * 驗證 checksum（chat/user 路徑）。
     * 若驗證失敗回傳 WP_Error，通過或功能不可用回傳 null。
     */
    public static function verify(string $session_id, array $history): ?WP_Error {
        if (empty($session_id) || !function_exists('mpu_chat_integrity_verify_history')) {
            return null;
        }
        $result = mpu_chat_integrity_verify_history(
            $session_id,
            mpu_chat_integrity_slice_for_store($history, 10)
        );
        return is_wp_error($result) ? $result : null;
    }

    /**
     * 寫入 auto-response checksum（chat/context 與 chat/greet）。
     * prior_history 為前端送來的歷史；$msg_type 決定 assistant 訊息的 type 標籤。
     */
    public static function store_after_auto(
        string $session_id,
        array $prior_history,
        string $assistant_reply,
        string $msg_type
    ): void {
        if (empty($session_id)
            || !function_exists('mpu_chat_integrity_store_history')
            || connection_aborted()
        ) {
            return;
        }
        $prior_history[] = [
            'role'    => 'assistant',
            'content' => sanitize_textarea_field($assistant_reply),
            'type'    => $msg_type,
        ];
        mpu_chat_integrity_store_history(
            $session_id,
            mpu_chat_integrity_slice_for_store($prior_history, 10)
        );
    }

    /**
     * 寫入 user-chat checksum（chat/user 與 chat/user-stream）。
     *
     * @param string $session_id      前端送來的 session id；空字串時直接 return。
     * @param array  $prior_history   verify 階段使用過的 history 陣列；本函式會視 dedup 旗標附加 user 訊息。
     * @param string $user_message    使用者本回合送出的訊息（已 sanitize）。
     * @param string $assistant_reply 模型最終回覆內容（已 sanitize）。
     * @param bool   $dedup_user      是否啟用 Double-Append 防護。預設 true：若 prior_history 末尾已是同樣的 user 訊息就不再附加；
     *                                呼叫端已自行把 user 訊息塞進 history 時應傳 false 避免重複。
     */
    public static function store_after_user_chat(
        string $session_id,
        array $prior_history,
        string $user_message,
        string $assistant_reply,
        bool $dedup_user = true
    ): void {
        if (empty($session_id)
            || !function_exists('mpu_chat_integrity_store_history')
            || connection_aborted()
        ) {
            return;
        }
        if ($dedup_user) {
            $last = end($prior_history);
            if (!($last && $last['role'] === 'user' && trim($last['content'] ?? '') === trim($user_message))) {
                $prior_history[] = ['role' => 'user', 'content' => $user_message];
            }
        } else {
            $prior_history[] = ['role' => 'user', 'content' => $user_message];
        }
        $prior_history[] = [
            'role'    => 'assistant',
            'content' => sanitize_textarea_field($assistant_reply),
        ];
        mpu_chat_integrity_store_history(
            $session_id,
            mpu_chat_integrity_slice_for_store($prior_history, 10)
        );
    }
}
