<?php
/**
 * SSE (Server-Sent Events) Streaming Helpers
 *
 * @package MP_Ukagaka
 * @subpackage LLM
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 初始化 SSE 連線環境
 * 設定必要的 Headers 並清理 Output Buffer
 */
function mpu_sse_init() {
    // 設置不超時，並確保 PHP 不會因為用戶斷開而立即中止（除非我們檢查 connection_aborted）
    if (function_exists('set_time_limit')) {
        @set_time_limit(0);
    }
    ignore_user_abort(true);

    // 清理所有層級的 Output Buffer，確保串流即時發送
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    // 發送 SSE 必要的 Headers
    header('Content-Type: text/event-stream; charset=utf-8');
    header('Cache-Control: no-cache, no-transform');
    header('Connection: keep-alive');
    header('X-Accel-Buffering: no'); // 針對 Nginx
    header('X-Content-Type-Options: nosniff');

    // 某些主機環境需要額外刷新
    if (function_exists('flush')) {
        flush();
    }
}

/**
 * 發送 SSE 事件到客戶端
 *
 * @param string $event 事件名稱
 * @param array|string $data 資料內容
 */
function mpu_sse_send_event($event, $data) {
    $event_name = (string) $event;
    $payload = is_array($data) ? $data : ['message' => (string) $data];

    if (class_exists('MPU_Session_Event')) {
        $event_name = MPU_Session_Event::kind_for_legacy_event($event_name);
        $payload = MPU_Session_Event::build($event_name, $payload);
    }

    echo "event: " . $event_name . "
";
    
    $json_payload = json_encode($payload, JSON_UNESCAPED_UNICODE);
    echo "data: " . $json_payload . "

";

    if (function_exists('flush')) {
        flush();
    }
}

