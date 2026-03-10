<?php

/**
 * Bot Blocker 整合模組
 *
 * 多層防禦機制封鎖惡意爬蟲：指紋黑名單、IP 黑名單、Cookie 陷阱、UA 異常偵測。
 * 整合自 Moelog Bot Blocker v2.5.0，設定由 mp_ukagaka 選項（bot_blocker 子陣列）管理。
 *
 * 資料表：{prefix}moelog_bot_blocker_logs（與獨立外掛共用，方便遷移）
 * IP 黑名單 option：moelog_bot_blocker_banned_ips
 *
 * @package MP_Ukagaka
 * @subpackage Integrations
 */

if (!defined('ABSPATH')) {
    exit;
}

// =============================================================================
// 設定讀取
// =============================================================================

/**
 * 取得 Bot Blocker 設定（合併預設值）
 */
function mpu_bb_get_config() {
    $opt = mpu_get_option();
    $defaults = [
        'enabled'                => false,
        'banned_fingerprints'    => ['f70fd15177b6515b0abf82a68122b25f'],
        'suspicious_resolutions' => ['1280x1200'],
        'max_log_rows'           => 1000,
        'auto_ban_ip'            => true,
        'block_status'           => 403,
        'hot_transient_ttl'      => 600,
        'rate_limit_threshold'   => 40,
    ];
    $bb = isset($opt['bot_blocker']) && is_array($opt['bot_blocker']) ? $opt['bot_blocker'] : [];
    return array_merge($defaults, $bb);
}

// =============================================================================
// 資料庫初始化
// =============================================================================

function mpu_bb_create_table() {
    global $wpdb;
    $table           = $wpdb->prefix . 'moelog_bot_blocker_logs';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        event varchar(64) NOT NULL,
        ip varchar(45) NOT NULL,
        ua text NOT NULL,
        chrome_ver varchar(10) DEFAULT NULL,
        data longtext DEFAULT NULL,
        created_at datetime NOT NULL,
        PRIMARY KEY (id),
        KEY event (event),
        KEY ip (ip),
        KEY created_at (created_at),
        KEY chrome_ver (chrome_ver)
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
    update_option('moelog_bot_blocker_db_version', '1.0', false);
}

function mpu_bb_maybe_create_table() {
    if (get_option('moelog_bot_blocker_db_version') !== '1.0') {
        mpu_bb_create_table();
    }
}

// =============================================================================
// 初始化：由模組載入時直接呼叫（此時處於 plugins_loaded priority 1）
// =============================================================================

function mpu_bb_init() {
    // 確保資料表存在（無論是否啟用）
    mpu_bb_maybe_create_table();

    $config = mpu_bb_get_config();

    if (empty($config['enabled'])) {
        return;
    }

    // 1. 最早期攔截：直接執行（此時正處於 plugins_loaded priority 1）
    mpu_bb_early_check();

    // 2. Slimstat REST API 攔截
    add_filter('rest_pre_dispatch', 'mpu_bb_intercept_slimstat_rest', 0, 3);

    // 3. Slimstat Admin-Ajax 備援路徑
    add_action('wp_ajax_nopriv_slimtrack', 'mpu_bb_intercept_slimstat', 0);
    add_action('wp_ajax_slimtrack',        'mpu_bb_intercept_slimstat', 0);

    // 4. 前端 Honeypot
    add_action('wp_footer', 'mpu_bb_js_trap', 999);

    // 5. Honeypot 提交檢查
    add_action('init', 'mpu_bb_honeypot_check', 1);

    // 6. JS Beacon（頁面開頭偵測解析度 / headless 信號）
    add_action('wp_head', 'mpu_bb_js_beacon', -10);
    add_action('wp_ajax_nopriv_mbb_js_flag', 'mpu_bb_js_flag_handler');
    add_action('wp_ajax_mbb_js_flag',        'mpu_bb_js_flag_handler');
}

// =============================================================================
// 1. 最早期攔截：Cookie 陷阱 + IP 黑名單 + Hot-transient + Rate Limit + UA 異常
// =============================================================================

function mpu_bb_early_check() {
    // 跳過管理後台，避免誤封自己
    if (is_admin()) {
        return;
    }

    // 1a. Cookie 陷阱檢查
    if (isset($_COOKIE['_mbb_t']) && $_COOKIE['_mbb_t'] === '1') {
        mpu_bb_block('cookie_trap');
        return;
    }

    $config = mpu_bb_get_config();
    $ip     = mpu_bb_get_ip();

    // 1b. IP 黑名單
    $banned_ips = get_option('moelog_bot_blocker_banned_ips', []);
    if (in_array($ip, $banned_ips, true)) {
        mpu_bb_block('ip_blacklist');
        return;
    }

    // 1b-2. Hot-transient：Slimstat 或 JS beacon 最近命中過此 IP
    if (get_transient('mbb_hot_' . md5($ip))) {
        mpu_bb_block('hot_transient');
        return;
    }

    // 1b-3. Rate limit：同 IP 5 分鐘內請求過多
    $rate_threshold = $config['rate_limit_threshold'] ?? 0;
    if ($rate_threshold > 0) {
        $bucket     = (int) floor(time() / 300); // 固定 5 分鐘桶
        $rate_key   = 'mbb_rate_' . md5($ip) . '_' . $bucket;
        $rate_count = (int) get_transient($rate_key);
        set_transient($rate_key, $rate_count + 1, 360); // 6 分鐘讓桶自動過期
        if ($rate_count >= $rate_threshold) {
            mpu_bb_set_hot_transient($ip);
            mpu_bb_ban_ip($ip);
            mpu_bb_log('rate_limit', ['count' => $rate_count + 1]);
            mpu_bb_block('rate_limit');
            return;
        }
    }

    // 1c. UA 異常偵測：過舊的 Chrome 版本
    $ua = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
    if (preg_match('/Chrome\/(\d+)\./', $ua, $matches)) {
        $chrome_ver = (int) $matches[1];
        if ($chrome_ver < 100) {
            mpu_bb_set_trap_cookie();
            mpu_bb_log('ua_ancient_chrome', [
                'chrome_version' => $chrome_ver,
                'ua'             => $ua,
            ]);
            mpu_bb_block('ancient_chrome');
            return;
        }
    }
}

// =============================================================================
// 2. Slimstat REST API 攔截（主路徑：/wp-json/slimstat/v1/hit）
// =============================================================================

function mpu_bb_intercept_slimstat_rest($result, $server, $request) {
    if ($request->get_route() !== '/slimstat/v1/hit') {
        return $result;
    }

    $config      = mpu_bb_get_config();
    $body        = $request->get_body();
    $body_params = [];
    if (!empty($body)) {
        parse_str($body, $body_params);
    }

    $fh = sanitize_text_field(wp_unslash($body_params['fh'] ?? $request->get_param('fh') ?? ''));
    $sw = sanitize_text_field(wp_unslash($body_params['sw'] ?? $request->get_param('sw') ?? ''));
    $sh = sanitize_text_field(wp_unslash($body_params['sh'] ?? $request->get_param('sh') ?? ''));

    if (empty($fh) && (empty($sw) || empty($sh))) {
        return $result;
    }

    $should_block = false;
    $reason       = '';

    if (!empty($fh) && in_array($fh, $config['banned_fingerprints'], true)) {
        $should_block = true;
        $reason       = 'fingerprint_match:' . $fh;
    }

    if (!$should_block && !empty($sw) && !empty($sh)) {
        $res = (int) $sw . 'x' . (int) $sh;
        if (in_array($res, $config['suspicious_resolutions'], true)) {
            $should_block = true;
            $reason       = 'suspicious_resolution:' . $res;
        }
    }

    if ($should_block) {
        $ip = mpu_bb_get_ip();
        mpu_bb_set_trap_cookie();
        mpu_bb_set_hot_transient($ip);
        if ($config['auto_ban_ip']) {
            mpu_bb_ban_ip($ip);
        }
        mpu_bb_log('slimstat_rest_intercept', [
            'reason'  => $reason,
            'fh'      => $fh,
            'res'     => ($sw && $sh) ? ((int) $sw . 'x' . (int) $sh) : '',
            'headers' => mpu_bb_get_headers(),
        ]);
        return new WP_Error('mbb_blocked', 'Blocked', ['status' => $config['block_status']]);
    }

    return $result;
}

// =============================================================================
// 3. Slimstat Admin-Ajax 備援路徑（admin-ajax.php?action=slimtrack）
// =============================================================================

function mpu_bb_intercept_slimstat() {
    $config = mpu_bb_get_config();

    $fh = isset($_POST['fh']) ? sanitize_text_field(wp_unslash($_POST['fh'])) : (isset($_GET['fh']) ? sanitize_text_field(wp_unslash($_GET['fh'])) : '');
    $sw = isset($_POST['sw']) ? sanitize_text_field(wp_unslash($_POST['sw'])) : (isset($_GET['sw']) ? sanitize_text_field(wp_unslash($_GET['sw'])) : '');
    $sh = isset($_POST['sh']) ? sanitize_text_field(wp_unslash($_POST['sh'])) : (isset($_GET['sh']) ? sanitize_text_field(wp_unslash($_GET['sh'])) : '');

    if (empty($fh) && (empty($sw) || empty($sh))) {
        return;
    }

    $should_block = false;
    $reason       = '';

    if (!empty($fh) && in_array($fh, $config['banned_fingerprints'], true)) {
        $should_block = true;
        $reason       = 'fingerprint_match:' . $fh;
    }

    if (!$should_block && !empty($sw) && !empty($sh)) {
        $res = (int) $sw . 'x' . (int) $sh;
        if (in_array($res, $config['suspicious_resolutions'], true)) {
            $should_block = true;
            $reason       = 'suspicious_resolution:' . $res;
        }
    }

    if ($should_block) {
        $ip = mpu_bb_get_ip();
        mpu_bb_set_trap_cookie();
        mpu_bb_set_hot_transient($ip);
        if ($config['auto_ban_ip']) {
            mpu_bb_ban_ip($ip);
        }
        mpu_bb_log('slimstat_intercept', [
            'reason'  => $reason,
            'fh'      => $fh,
            'res'     => ($sw && $sh) ? ((int) $sw . 'x' . (int) $sh) : '',
            'headers' => mpu_bb_get_headers(),
        ]);
        wp_die('Blocked', '', ['response' => $config['block_status']]);
    }
}

// =============================================================================
// 4. 前端 Honeypot（隱藏表單，真人不會觸發）
// =============================================================================

function mpu_bb_js_trap() {
    ?>
    <div style="position:absolute;left:-9999px;top:-9999px;opacity:0;pointer-events:none;" aria-hidden="true">
        <form id="_mbb_hp" method="post">
            <input type="text" name="_mbb_email" value="" autocomplete="off" tabindex="-1" />
            <input type="hidden" name="_mbb_hp_check" value="1" />
        </form>
    </div>
    <script>
    (function(){
        var f = document.getElementById('_mbb_hp');
        if (f) {
            f.addEventListener('submit', function(e) {
                e.preventDefault();
                document.cookie = '_mbb_t=1;path=/;max-age=315360000;SameSite=Lax';
            });
        }
    })();
    </script>
    <?php
}

// =============================================================================
// 5. Honeypot 提交檢查
// =============================================================================

function mpu_bb_honeypot_check() {
    if (
        isset($_POST['_mbb_hp_check']) &&
        $_POST['_mbb_hp_check'] === '1' &&
        !empty($_POST['_mbb_email'])
    ) {
        $ip = mpu_bb_get_ip();
        mpu_bb_set_trap_cookie();
        mpu_bb_ban_ip($ip);
        mpu_bb_log('honeypot_triggered', [
            'filled_value' => sanitize_text_field(wp_unslash($_POST['_mbb_email'])),
        ]);
        mpu_bb_block('honeypot');
    }
}

// =============================================================================
// 6. JS Beacon：頁面開頭偵測可疑信號，命中後通報 server
// =============================================================================

function mpu_bb_js_beacon() {
    // 放行常見搜尋引擎，避免誤殺
    $ua = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
    if (preg_match('/(Googlebot|Bingbot|Slurp|DuckDuckBot|Baiduspider|YandexBot)/i', $ua)) {
        return;
    }

    $config   = mpu_bb_get_config();
    $res_json = wp_json_encode($config['suspicious_resolutions']);
    $ajax_url = esc_url(admin_url('admin-ajax.php'));
    ?>
<script>
(function(){
    var signals = [];
    var w = screen.width, h = screen.height;
    var sus = <?php echo $res_json; ?>;
    if (sus.indexOf(w + 'x' + h) !== -1) signals.push('res:' + w + 'x' + h);
    if (navigator.webdriver) signals.push('webdriver');
    if (navigator.plugins && navigator.plugins.length === 0) signals.push('no_plugins');
    if (typeof window.chrome === 'undefined') signals.push('no_chrome_obj');
    if (signals.length > 0) {
        var fd = new FormData();
        fd.append('action', 'mbb_js_flag');
        fd.append('r', w + 'x' + h);
        fd.append('s', signals.join(','));
        if (navigator.sendBeacon) {
            navigator.sendBeacon('<?php echo $ajax_url; ?>', fd);
        }
    }
})();
</script>
    <?php
}

function mpu_bb_js_flag_handler() {
    $ip      = mpu_bb_get_ip();
    $res     = isset($_POST['r']) ? sanitize_text_field(wp_unslash($_POST['r'])) : '';
    $signals = isset($_POST['s']) ? sanitize_text_field(wp_unslash($_POST['s'])) : '';
    $config  = mpu_bb_get_config();

    // 解析度命中 → 直接封鎖（誤判率極低）
    $block_by_res = in_array($res, $config['suspicious_resolutions'], true);

    // 其他 headless 信號：需同時命中 2 個以上才封鎖
    $signal_list      = array_filter(explode(',', $signals));
    $non_res_hits     = array_filter($signal_list, function($s) { return strpos($s, 'res:') !== 0; });
    $block_by_signals = count($non_res_hits) >= 2;

    if ($block_by_res || $block_by_signals) {
        mpu_bb_set_hot_transient($ip);
        if ($config['auto_ban_ip']) {
            mpu_bb_ban_ip($ip);
        }
        mpu_bb_log('js_beacon_flag', [
            'res'     => $res,
            'signals' => $signals,
            'reason'  => $block_by_res ? 'resolution' : 'headless_signals',
        ]);
    }

    status_header(204);
    exit;
}

// =============================================================================
// 輔助函式
// =============================================================================

function mpu_bb_block($reason = 'unknown') {
    $config = mpu_bb_get_config();
    if (!headers_sent()) {
        http_response_code($config['block_status']);
        header('Content-Type: text/plain; charset=utf-8');
        header('Connection: close');
        header('Cache-Control: no-store');
    }
    die("403 Forbidden");
}

function mpu_bb_set_trap_cookie() {
    if (!headers_sent()) {
        setcookie('_mbb_t', '1', [
            'expires'  => time() + (10 * 365 * 24 * 60 * 60),
            'path'     => '/',
            'secure'   => is_ssl(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}

function mpu_bb_set_hot_transient($ip) {
    $config = mpu_bb_get_config();
    $ttl    = $config['hot_transient_ttl'] ?? 600;
    set_transient('mbb_hot_' . md5($ip), 1, $ttl);
}

function mpu_bb_ban_ip($ip) {
    $banned = get_option('moelog_bot_blocker_banned_ips', []);
    if (!in_array($ip, $banned, true)) {
        $banned[] = $ip;
        if (count($banned) > 500) {
            $banned = array_slice($banned, -500);
        }
        update_option('moelog_bot_blocker_banned_ips', $banned, false);
    }
}

function mpu_bb_get_ip() {
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        return sanitize_text_field($_SERVER['HTTP_CF_CONNECTING_IP']);
    }
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(',', sanitize_text_field($_SERVER['HTTP_X_FORWARDED_FOR']));
        return trim($ips[0]);
    }
    return isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field($_SERVER['REMOTE_ADDR']) : '0.0.0.0';
}

function mpu_bb_get_headers() {
    $headers = [];
    foreach ($_SERVER as $key => $value) {
        if (strpos($key, 'HTTP_') === 0) {
            $name          = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($key, 5)))));
            $headers[$name] = sanitize_text_field($value);
        }
    }
    return $headers;
}

function mpu_bb_log($event, $data = []) {
    global $wpdb;
    $config = mpu_bb_get_config();
    $table  = $wpdb->prefix . 'moelog_bot_blocker_logs';

    $ip = mpu_bb_get_ip();
    $ua = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field($_SERVER['HTTP_USER_AGENT']) : 'N/A';

    $chrome_ver = null;
    if (preg_match('/Chrome\/(\d+)/', $ua, $cv)) {
        $chrome_ver = $cv[1];
    }

    $wpdb->insert(
        $table,
        [
            'event'      => $event,
            'ip'         => $ip,
            'ua'         => $ua,
            'chrome_ver' => $chrome_ver,
            'data'       => !empty($data) ? wp_json_encode($data, JSON_UNESCAPED_UNICODE) : null,
            'created_at' => current_time('mysql'),
        ],
        ['%s', '%s', '%s', '%s', '%s', '%s']
    );

    // 列數上限：超過時刪除最舊的記錄
    $max_rows = $config['max_log_rows'];
    $count    = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table");
    if ($count > $max_rows) {
        $cutoff = $wpdb->get_var(
            $wpdb->prepare("SELECT id FROM $table ORDER BY id ASC LIMIT 1 OFFSET %d", $count - $max_rows)
        );
        if ($cutoff) {
            $wpdb->query($wpdb->prepare("DELETE FROM $table WHERE id < %d", $cutoff));
        }
    }

    // 通知 mp-ukagaka 觸發對話
    $mbb_event = get_transient('moelog_bot_blocker_event');
    if ($mbb_event === false) {
        $mbb_event = ['count' => 0];
    }
    $mbb_event['count']++;
    $mbb_event['last_event'] = $event;
    set_transient('moelog_bot_blocker_event', $mbb_event, 30 * MINUTE_IN_SECONDS);
}

// =============================================================================
// 管理用函式（供後台 options 頁面呼叫）
// =============================================================================

function mpu_bb_parse_logs() {
    global $wpdb;
    $table = $wpdb->prefix . 'moelog_bot_blocker_logs';

    $entries = [];
    $stats   = [
        'total'     => 0,
        'today'     => 0,
        'by_event'  => [],
        'by_ip'     => [],
        'by_chrome' => [],
    ];

    if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
        return ['entries' => $entries, 'stats' => $stats];
    }

    $stats['total'] = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table");
    $today          = current_time('Y-m-d');
    $stats['today'] = (int) $wpdb->get_var(
        $wpdb->prepare("SELECT COUNT(*) FROM $table WHERE DATE(created_at) = %s", $today)
    );

    $rows = $wpdb->get_results("SELECT event, COUNT(*) as cnt FROM $table GROUP BY event ORDER BY cnt DESC");
    foreach ($rows as $row) {
        $stats['by_event'][$row->event] = (int) $row->cnt;
    }

    $rows = $wpdb->get_results("SELECT ip, COUNT(*) as cnt FROM $table GROUP BY ip ORDER BY cnt DESC LIMIT 50");
    foreach ($rows as $row) {
        $stats['by_ip'][$row->ip] = (int) $row->cnt;
    }

    $rows = $wpdb->get_results(
        "SELECT chrome_ver, COUNT(*) as cnt FROM $table WHERE chrome_ver IS NOT NULL GROUP BY chrome_ver ORDER BY cnt DESC"
    );
    foreach ($rows as $row) {
        $stats['by_chrome'][$row->chrome_ver] = (int) $row->cnt;
    }

    $rows = $wpdb->get_results("SELECT * FROM $table ORDER BY id DESC LIMIT 200");
    foreach ($rows as $row) {
        $entries[] = [
            'time'   => $row->created_at,
            'event'  => $row->event,
            'ip'     => $row->ip,
            'ua'     => $row->ua,
            'data'   => $row->data ?? '',
            'chrome' => $row->chrome_ver ?? 'N/A',
        ];
    }

    return ['entries' => $entries, 'stats' => $stats];
}

function mpu_bb_clear_logs() {
    global $wpdb;
    $table = $wpdb->prefix . 'moelog_bot_blocker_logs';
    $wpdb->query("TRUNCATE TABLE $table");
}

// =============================================================================
// 啟動（模組載入時直接呼叫）
// =============================================================================
mpu_bb_init();
