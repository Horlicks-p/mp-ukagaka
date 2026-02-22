<?php

namespace MP_Ukagaka\McpTools\Abilities;

/**
 * Ability to interact with the Moelog Bot Blocker plugin.
 *
 * Allows the AI to view blocking statistics, manually ban IPs, and clear logs.
 */
class Wp_Bot_Blocker_Ability
{
    /**
     * Register the abilities.
     */
    public static function register()
    {
        if (!function_exists('wp_register_ability')) {
            return;
        }

        if (!function_exists('moelog_bot_blocker_ban_ip')) {
            return;
        }

        // Ability 1: Get Bot Blocker Stats
        wp_register_ability('mp-ukagaka/get-bot-blocker-stats', array(
            'label'              => __('Get Bot Blocker Stats', 'mp-ukagaka'),
            'description'        => __('Retrieve statistics from the Moelog Bot Blocker, including the number of banned IPs and intercept statistics by type.', 'mp-ukagaka'),
            'category'           => 'mp-ukagaka',
            'input_schema'       => array(
                'type'       => 'object',
                'properties' => new \stdClass(),
            ),
            'execute_callback'   => [self::class, 'get_stats_callback'],
            'permission_callback' => function () { return current_user_can('manage_options'); },
        ));

        // Ability 2: Ban an IP manually
        wp_register_ability('mp-ukagaka/ban-ip', array(
            'label'              => __('Ban IP Address', 'mp-ukagaka'),
            'description'        => __('Manually add an IP address to the Moelog Bot Blocker ban list.', 'mp-ukagaka'),
            'category'           => 'mp-ukagaka',
            'input_schema'       => array(
                'type'       => 'object',
                'properties' => array(
                    'ip_address' => array(
                        'type'        => 'string',
                        'description' => __('The IPv4 or IPv6 address to ban.', 'mp-ukagaka'),
                    ),
                ),
                'required' => array('ip_address'),
            ),
            'execute_callback'   => [self::class, 'ban_ip_callback'],
            'permission_callback' => function () { return current_user_can('manage_options'); },
        ));

        // Ability 3: Clear Bot Blocker Logs
        wp_register_ability('mp-ukagaka/clear-bot-blocker-data', array(
            'label'              => __('Clear Bot Blocker Data', 'mp-ukagaka'),
            'description'        => __('Clear the Moelog Bot Blocker intercept records or reset the IP ban list.', 'mp-ukagaka'),
            'category'           => 'mp-ukagaka',
            'input_schema'       => array(
                'type'       => 'object',
                'properties' => array(
                    'target' => array(
                        'type'        => 'string',
                        'enum'        => array('logs', 'ips', 'both'),
                        'description' => __(
                            '"logs" — deletes all intercept log records from the database (攔截紀錄/ログ). Use this when the user asks to clear records, history, logs, or intercept data. ' .
                            '"ips" — clears the banned IP list only (IP黑名單). Use this when the user asks to unblock IPs or reset the ban list. ' .
                            '"both" — clears both the intercept records and the banned IP list.',
                            'mp-ukagaka'
                        ),
                    ),
                ),
                'required' => array('target'),
            ),
            'execute_callback'   => [self::class, 'clear_data_callback'],
            'permission_callback' => function () { return current_user_can('manage_options'); },
        ));
    }

    /**
     * Callback for getting bot blocker stats
     */
    public static function get_stats_callback($args)
    {
        $banned_ips = get_option('moelog_bot_blocker_banned_ips', []);

        // 使用 plugin 自己的解析函式，與後台顯示的數字一致
        $total_intercepts = 0;
        $today_intercepts = 0;
        $by_event = [];
        if (function_exists('moelog_bot_blocker_parse_logs')) {
            $parsed = moelog_bot_blocker_parse_logs();
            $total_intercepts = $parsed['stats']['total'] ?? 0;
            $today_intercepts = $parsed['stats']['today'] ?? 0;
            $by_event         = $parsed['stats']['by_event'] ?? [];
        }

        // Map event keys to human-readable labels
        $event_label_map = [
            'ua_ancient_chrome'  => 'outdated browser (old Chrome)',
            'slimstat_intercept' => 'suspicious access (Slimstat)',
            'honeypot_triggered' => 'honeypot triggered',
            'MANUAL_BAN'         => 'manually banned',
        ];
        $labeled_by_type = [];
        foreach ($by_event as $event => $count) {
            $label = $event_label_map[$event] ?? $event;
            $labeled_by_type[$label] = $count;
        }

        return array(
            'total_banned_ips'   => count($banned_ips),
            'banned_ips_list'    => array_slice($banned_ips, -20),
            'total_intercepts'   => $total_intercepts,
            'today_intercepts'   => $today_intercepts,
            'intercepts_by_type' => $labeled_by_type,
            'status'             => 'Active',
        );
    }

    /**
     * Callback for manually banning an IP
     */
    public static function ban_ip_callback($args)
    {
        if (empty($args['ip_address'])) {
            return new \WP_Error('invalid_param', __('IP address is required.', 'mp-ukagaka'));
        }

        $ip = sanitize_text_field($args['ip_address']);

        // Check if the IP is valid
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return new \WP_Error('invalid_ip', __('The provided IP address is invalid.', 'mp-ukagaka'));
        }

        // We use the same storage mechanism as the plugin does
        $banned = get_option('moelog_bot_blocker_banned_ips', []);

        if (in_array($ip, $banned, true)) {
            return "IP {$ip} is already in the ban list.";
        }

        // Delegate to the plugin's own function to ensure consistency
        // (handles deduplication, 500-limit, and option update)
        moelog_bot_blocker_ban_ip($ip);

        // Use the plugin's own log function so log rotation and the
        // moelog_bot_blocker_event transient are also updated correctly.
        moelog_bot_blocker_log('MANUAL_BAN', ['source' => 'Frieren API', 'ip' => $ip]);

        return "Successfully added IP {$ip} to the ban list.";
    }

    /**
     * Callback for clearing bot blocker data
     */
    public static function clear_data_callback($args)
    {
        if (empty($args['target'])) {
            return new \WP_Error('invalid_param', __('Target to clear is required.', 'mp-ukagaka'));
        }

        $target = $args['target'];
        $messages = [];

        if ($target === 'ips' || $target === 'both') {
            delete_option('moelog_bot_blocker_banned_ips');
            $messages[] = 'Cleared the IP ban list.';
        }

        if ($target === 'logs' || $target === 'both') {
            if (function_exists('moelog_bot_blocker_clear_logs')) {
                moelog_bot_blocker_clear_logs();
                $messages[] = 'Cleared the log table.';
            } else {
                $messages[] = 'Log clear function not available.';
            }
        }

        return implode(' ', $messages);
    }
}
