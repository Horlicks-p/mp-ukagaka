<?php

/**
 * Bot 防護管理頁面（Tab 9）
 *
 * 提供：啟用開關、黑名單設定、攔截統計、IP 黑名單管理、Log 瀏覽。
 *
 * @package MP_Ukagaka
 * @subpackage Admin
 */

if (!defined('ABSPATH')) {
    exit();
}

// 取得目前設定
$bb_config  = mpu_bb_get_config();
$banned_ips = get_option('moelog_bot_blocker_banned_ips', []);

// 處理維護動作（clear IPs / clear log / remove IP）—— 獨立 nonce，與設定表單分離
if (isset($_POST['mpu_bb_action']) && isset($_POST['mpu_bb_nonce']) && wp_verify_nonce($_POST['mpu_bb_nonce'], 'mpu_bb_admin_action')) {
    if (!current_user_can('manage_options')) {
        wp_die(__('您沒有足夠的權限執行此操作。', 'mp-ukagaka'));
    }
    $action = sanitize_text_field($_POST['mpu_bb_action']);

    if ($action === 'clear_ips') {
        delete_option('moelog_bot_blocker_banned_ips');
        $banned_ips = [];
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('IP 黑名單已清除。', 'mp-ukagaka') . '</p></div>';
    } elseif ($action === 'clear_log') {
        mpu_bb_clear_logs();
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Log 已清除。', 'mp-ukagaka') . '</p></div>';
    } elseif ($action === 'remove_ip' && !empty($_POST['remove_ip'])) {
        $remove_ip  = sanitize_text_field($_POST['remove_ip']);
        $banned_ips = get_option('moelog_bot_blocker_banned_ips', []);
        $banned_ips = array_values(array_diff($banned_ips, [$remove_ip]));
        update_option('moelog_bot_blocker_banned_ips', $banned_ips, false);
        echo '<div class="notice notice-success is-dismissible"><p>' . sprintf(esc_html__('已移除 IP：%s', 'mp-ukagaka'), esc_html($remove_ip)) . '</p></div>';
    }
}

// 取得最新統計與 Log
$parsed  = mpu_bb_parse_logs();
$stats   = $parsed['stats'];
$entries = $parsed['entries'];
?>

<!-- ===== 設定表單 ===== -->
<form method="post" action="<?php echo esc_url(admin_url('options-general.php?page=' . $base_name . '&cur_page=9')); ?>">
    <?php wp_nonce_field('mp_ukagaka_settings', '_wpnonce'); ?>

    <h3>🛡️ <?php esc_html_e('Bot 防護設定', 'mp-ukagaka'); ?></h3>
    <p class="description"><?php esc_html_e('多層防禦機制封鎖惡意爬蟲：指紋黑名單、IP 黑名單、Cookie 陷阱、UA 異常偵測、JS Beacon。', 'mp-ukagaka'); ?></p>

    <div class="mpu-settings-card">
    <table class="form-table" role="presentation">

        <tr>
            <th scope="row"><?php esc_html_e('啟用 Bot 防護', 'mp-ukagaka'); ?></th>
            <td>
                <label>
                    <input type="checkbox" name="bot_blocker_enabled" value="1" <?php checked(!empty($bb_config['enabled'])); ?> />
                    <strong><?php esc_html_e('啟用多層 Bot 封鎖防護', 'mp-ukagaka'); ?></strong>
                </label>
                <p class="description"><?php esc_html_e('啟用後立即生效。關閉後所有防護停止運作，但資料庫記錄與 IP 黑名單保留。', 'mp-ukagaka'); ?></p>
            </td>
        </tr>

        <tr>
            <th scope="row"><?php esc_html_e('指紋黑名單', 'mp-ukagaka'); ?></th>
            <td>
                <textarea name="bot_blocker_banned_fingerprints" rows="5" cols="50" class="regular-text code"><?php
                    echo esc_textarea(implode("\n", (array) $bb_config['banned_fingerprints']));
                ?></textarea>
                <p class="description"><?php esc_html_e('Slimstat fingerprint hash，每行一個（例：f70fd15177b6515b0abf82a68122b25f）。', 'mp-ukagaka'); ?></p>
            </td>
        </tr>

        <tr>
            <th scope="row"><?php esc_html_e('可疑解析度黑名單', 'mp-ukagaka'); ?></th>
            <td>
                <textarea name="bot_blocker_suspicious_resolutions" rows="5" cols="50" class="regular-text code"><?php
                    echo esc_textarea(implode("\n", (array) $bb_config['suspicious_resolutions']));
                ?></textarea>
                <p class="description"><?php esc_html_e('格式：寬x高（例：1280x1200），每行一個。出現即封鎖，不需搭配其他條件。', 'mp-ukagaka'); ?></p>
            </td>
        </tr>

        <tr>
            <th scope="row"><?php esc_html_e('IP 自動封鎖', 'mp-ukagaka'); ?></th>
            <td>
                <label>
                    <input type="checkbox" name="bot_blocker_auto_ban_ip" value="1" <?php checked(!empty($bb_config['auto_ban_ip'])); ?> />
                    <?php esc_html_e('被指紋或解析度命中後，自動將該 IP 加入永久黑名單', 'mp-ukagaka'); ?>
                </label>
            </td>
        </tr>

        <tr>
            <th scope="row"><?php esc_html_e('封鎖狀態碼', 'mp-ukagaka'); ?></th>
            <td>
                <input type="number" name="bot_blocker_block_status" value="<?php echo esc_attr($bb_config['block_status']); ?>" min="400" max="599" style="width:100px;" />
                <p class="description"><?php esc_html_e('HTTP 狀態碼（通常為 403 Forbidden）', 'mp-ukagaka'); ?></p>
            </td>
        </tr>

        <tr>
            <th scope="row"><?php esc_html_e('Hot-Transient 存活時間（秒）', 'mp-ukagaka'); ?></th>
            <td>
                <input type="number" name="bot_blocker_hot_transient_ttl" value="<?php echo esc_attr($bb_config['hot_transient_ttl']); ?>" min="60" max="86400" style="width:100px;" />
                <p class="description"><?php esc_html_e('命中後同 IP 在此秒數內直接封鎖（預設 600 秒 = 10 分鐘）', 'mp-ukagaka'); ?></p>
            </td>
        </tr>

        <tr>
            <th scope="row"><?php esc_html_e('Rate Limit 閾值（次 / 5 分鐘）', 'mp-ukagaka'); ?></th>
            <td>
                <input type="number" name="bot_blocker_rate_limit_threshold" value="<?php echo esc_attr($bb_config['rate_limit_threshold']); ?>" min="0" max="1000" style="width:100px;" />
                <p class="description"><?php esc_html_e('同 IP 在 5 分鐘內超過此請求次數即封鎖（0 = 停用 Rate Limit）', 'mp-ukagaka'); ?></p>
            </td>
        </tr>

        <tr>
            <th scope="row"><?php esc_html_e('Log 最大列數', 'mp-ukagaka'); ?></th>
            <td>
                <input type="number" name="bot_blocker_max_log_rows" value="<?php echo esc_attr($bb_config['max_log_rows']); ?>" min="100" max="10000" style="width:100px;" />
                <p class="description"><?php esc_html_e('超過時自動刪除最舊的紀錄（預設 1000）', 'mp-ukagaka'); ?></p>
            </td>
        </tr>

    </table>
    </div>

    <p class="submit">
        <input type="submit" name="submit_bot_blocker" value="<?php esc_attr_e('儲存 Bot 防護設定', 'mp-ukagaka'); ?>" class="button button-primary" />
    </p>
</form>

<!-- ===== 統計卡片 ===== -->
<h3>📊 <?php esc_html_e('攔截統計', 'mp-ukagaka'); ?></h3>
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;margin-bottom:24px;">

    <div style="background:#fff;border:1px solid #CCC;border-radius:8px;padding:16px 20px;box-shadow:0 2px 8px rgba(75,61,107,0.08);">
        <div style="font-size:11px;font-weight:600;color:#9B8EC4;text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px;"><?php esc_html_e('攔截總計', 'mp-ukagaka'); ?></div>
        <div style="font-size:28px;font-weight:800;color:#c0392b;"><?php echo number_format($stats['total']); ?></div>
    </div>

    <div style="background:#fff;border:1px solid #CCC;border-radius:8px;padding:16px 20px;box-shadow:0 2px 8px rgba(75,61,107,0.08);">
        <div style="font-size:11px;font-weight:600;color:#9B8EC4;text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px;"><?php esc_html_e('今日攔截', 'mp-ukagaka'); ?></div>
        <div style="font-size:28px;font-weight:800;color:#b7791f;"><?php echo number_format($stats['today']); ?></div>
    </div>

    <div style="background:#fff;border:1px solid #CCC;border-radius:8px;padding:16px 20px;box-shadow:0 2px 8px rgba(75,61,107,0.08);">
        <div style="font-size:11px;font-weight:600;color:#9B8EC4;text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px;"><?php esc_html_e('IP 黑名單', 'mp-ukagaka'); ?></div>
        <div style="font-size:28px;font-weight:800;color:#7B68AE;"><?php echo count($banned_ips); ?></div>
    </div>

    <div style="background:#fff;border:1px solid #CCC;border-radius:8px;padding:16px 20px;box-shadow:0 2px 8px rgba(75,61,107,0.08);">
        <div style="font-size:11px;font-weight:600;color:#9B8EC4;text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px;"><?php esc_html_e('監控指紋', 'mp-ukagaka'); ?></div>
        <div style="font-size:28px;font-weight:800;color:#276749;"><?php echo count((array) $bb_config['banned_fingerprints']); ?></div>
    </div>

</div>

<!-- ===== 事件分佈 ===== -->
<?php if (!empty($stats['by_event'])): ?>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:24px;">

    <div style="background:#fff;border:1px solid #CCC;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(75,61,107,0.08);">
        <div style="background:#EDE8F5;padding:12px 16px;font-size:13px;font-weight:700;color:#4A3D6B;border-bottom:1px solid #DED6EE;">
            📋 <?php esc_html_e('攔截事件分佈', 'mp-ukagaka'); ?>
        </div>
        <div style="padding:12px 16px;max-height:240px;overflow-y:auto;">
            <table style="width:100%;border-collapse:collapse;font-size:13px;">
                <?php foreach ($stats['by_event'] as $ev => $cnt): ?>
                <tr>
                    <td style="padding:5px 0;border-bottom:1px solid #EDE8F5;"><code style="font-size:11px;"><?php echo esc_html($ev); ?></code></td>
                    <td style="padding:5px 0;border-bottom:1px solid #EDE8F5;text-align:right;font-weight:700;color:#4A3D6B;"><?php echo number_format($cnt); ?></td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
    </div>

    <?php if (!empty($stats['by_chrome'])): ?>
    <div style="background:#fff;border:1px solid #CCC;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(75,61,107,0.08);">
        <div style="background:#EDE8F5;padding:12px 16px;font-size:13px;font-weight:700;color:#4A3D6B;border-bottom:1px solid #DED6EE;">
            🌐 <?php esc_html_e('偽造的 Chrome 版本', 'mp-ukagaka'); ?>
        </div>
        <div style="padding:12px 16px;max-height:240px;overflow-y:auto;">
            <table style="width:100%;border-collapse:collapse;font-size:13px;">
                <?php foreach ($stats['by_chrome'] as $ver => $cnt): ?>
                <tr>
                    <td style="padding:5px 0;border-bottom:1px solid #EDE8F5;color:#9B8EC4;font-family:monospace;font-size:12px;">Chrome/<?php echo esc_html($ver); ?></td>
                    <td style="padding:5px 0;border-bottom:1px solid #EDE8F5;text-align:right;font-weight:700;color:#4A3D6B;"><?php echo number_format($cnt); ?></td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
    </div>
    <?php endif; ?>

</div>
<?php endif; ?>

<!-- ===== Log 表格 ===== -->
<h3>📜 <?php esc_html_e('攔截紀錄', 'mp-ukagaka'); ?> <span style="font-weight:400;font-size:13px;color:#9B8EC4;"><?php printf(esc_html__('最新 %d 筆', 'mp-ukagaka'), min(count($entries), 200)); ?></span></h3>

<?php if (empty($entries)): ?>
    <p style="color:#9B8EC4;">👍 <?php esc_html_e('還沒有攔截紀錄，一切安好！', 'mp-ukagaka'); ?></p>
<?php else: ?>
    <div style="max-height:440px;overflow-y:auto;border:1px solid #CCC;border-radius:8px;box-shadow:0 2px 8px rgba(75,61,107,0.08);">
        <table class="widefat" style="border:none;border-radius:0;">
            <thead>
                <tr>
                    <th style="position:sticky;top:0;background:#EDE8F5;"><?php esc_html_e('時間', 'mp-ukagaka'); ?></th>
                    <th style="position:sticky;top:0;background:#EDE8F5;"><?php esc_html_e('事件', 'mp-ukagaka'); ?></th>
                    <th style="position:sticky;top:0;background:#EDE8F5;"><?php esc_html_e('IP', 'mp-ukagaka'); ?></th>
                    <th style="position:sticky;top:0;background:#EDE8F5;"><?php esc_html_e('Chrome', 'mp-ukagaka'); ?></th>
                    <th style="position:sticky;top:0;background:#EDE8F5;"><?php esc_html_e('User-Agent', 'mp-ukagaka'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($entries as $entry): ?>
                <tr>
                    <td style="white-space:nowrap;font-family:monospace;font-size:12px;color:#9B8EC4;"><?php echo esc_html($entry['time']); ?></td>
                    <td><code style="font-size:11px;"><?php echo esc_html($entry['event']); ?></code></td>
                    <td style="font-family:monospace;font-size:12px;"><?php echo esc_html($entry['ip']); ?></td>
                    <td style="font-size:12px;"><?php echo esc_html($entry['chrome']); ?></td>
                    <td style="max-width:280px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:11px;color:#9B8EC4;" title="<?php echo esc_attr($entry['ua']); ?>"><?php echo esc_html($entry['ua']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<!-- ===== IP 黑名單管理 ===== -->
<h3 style="margin-top:28px;">🚫 <?php esc_html_e('IP 黑名單', 'mp-ukagaka'); ?> <span style="font-weight:400;font-size:14px;color:#9B8EC4;">(<?php echo count($banned_ips); ?>)</span></h3>

<?php if (empty($banned_ips)): ?>
    <p style="color:#9B8EC4;"><?php esc_html_e('目前沒有被封鎖的 IP。', 'mp-ukagaka'); ?></p>
<?php else: ?>
    <div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:14px;max-height:200px;overflow-y:auto;padding:2px;">
        <?php foreach ($banned_ips as $ip_addr): ?>
            <span style="display:inline-flex;align-items:center;gap:4px;background:#EDE8F5;color:#5E4D8B;font-family:monospace;font-size:11px;font-weight:500;padding:4px 8px;border-radius:6px;border:1px solid #CCC;">
                <?php echo esc_html($ip_addr); ?>
                <form method="post" style="display:inline;margin:0;padding:0;">
                    <?php wp_nonce_field('mpu_bb_admin_action', 'mpu_bb_nonce'); ?>
                    <input type="hidden" name="mpu_bb_action" value="remove_ip" />
                    <input type="hidden" name="remove_ip" value="<?php echo esc_attr($ip_addr); ?>" />
                    <button type="submit" style="background:none;border:none;color:#7B68AE;cursor:pointer;font-size:14px;line-height:1;padding:0 0 0 2px;opacity:0.55;" title="<?php esc_attr_e('移除此 IP', 'mp-ukagaka'); ?>">&times;</button>
                </form>
            </span>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- 維護操作按鈕 -->
<div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:28px;">
    <form method="post" style="display:inline;">
        <?php wp_nonce_field('mpu_bb_admin_action', 'mpu_bb_nonce'); ?>
        <input type="hidden" name="mpu_bb_action" value="clear_ips" />
        <button type="submit" class="button button-secondary"
                onclick="return confirm('<?php esc_attr_e('確定要清除所有被封鎖的 IP？這不會影響指紋黑名單。', 'mp-ukagaka'); ?>');">
            🗑️ <?php esc_html_e('清除 IP 黑名單', 'mp-ukagaka'); ?>
        </button>
    </form>
    <form method="post" style="display:inline;">
        <?php wp_nonce_field('mpu_bb_admin_action', 'mpu_bb_nonce'); ?>
        <input type="hidden" name="mpu_bb_action" value="clear_log" />
        <button type="submit" class="button button-secondary"
                onclick="return confirm('<?php esc_attr_e('確定要清除所有 Log 紀錄？此操作無法復原。', 'mp-ukagaka'); ?>');">
            📝 <?php esc_html_e('清除 Log', 'mp-ukagaka'); ?>
        </button>
    </form>
</div>
