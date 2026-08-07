<?php

namespace MP_Ukagaka\McpTools\Abilities;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Ability: Get Visitor Pulse
 *
 * 回傳最近一段時間的訪客脈動摘要（總數、不重複 IP、國家清單、bot 比例），
 * 資料來源為 Slimstat 既有 wp_slim_stats，無額外寫入。
 */
class Visitor_Pulse_Ability
{
    public static function register()
    {
        if (!function_exists('wp_register_ability')) {
            return;
        }

        wp_register_ability(
            'mp-ukagaka/get-visitor-pulse',
            [
                'label'       => __('Get Visitor Pulse', 'mp-ukagaka'),
                'description' => __('Retrieve recent visitor pulse: total requests, unique IPs, country list, and bot/human split. Source: Slimstat (read-only).', 'mp-ukagaka'),
                'category'    => 'mp-ukagaka',
                'input_schema' => [
                    'type'       => 'object',
                    // 零引数呼び出しを許すための頂層 default（間接パスの null 対策）.
                    'default'    => (object) array(),
                    'properties' => [
                        'minutes' => [
                            'type'        => 'integer',
                            'description' => __('Time window in minutes (1–1440, default 60).', 'mp-ukagaka'),
                            'default'     => 60,
                        ],
                    ],
                ],
                'output_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'minutes_window'   => ['type' => 'integer'],
                        'total_requests'   => ['type' => 'integer'],
                        'unique_visitors'  => ['type' => 'integer'],
                        'country_count'    => ['type' => 'integer'],
                        'countries'        => ['type' => 'array', 'items' => ['type' => 'string']],
                        'bot_requests'     => ['type' => 'integer'],
                        'human_requests'   => ['type' => 'integer'],
                        'summary'          => ['type' => 'string'],
                    ],
                ],
                'execute_callback'    => [self::class, 'execute'],
                'permission_callback' => function () {
                    return class_exists('\MPU_Input_Role')
                        ? \MPU_Input_Role::current_can_use_ability('mp-ukagaka/get-visitor-pulse')
                        : current_user_can('manage_options');
                },
                'meta' => [
                    'show_in_rest' => true,
                    'annotations'  => [
                        'readonly'    => true,
                        'destructive' => false,
                        'idempotent'  => true,
                    ],
                ],
            ]
        );
    }

    public static function execute($args = [])
    {
        if (!function_exists('mpu_get_visitor_pulse')) {
            return new \WP_Error('dependency_missing', 'Visitor pulse functions are not loaded.');
        }

        $minutes = isset($args['minutes']) ? max(1, min(1440, intval($args['minutes']))) : 60;
        $pulse   = mpu_get_visitor_pulse($minutes);

        $countries = array_values(array_filter((array) ($pulse['countries'] ?? [])));

        $summary = sprintf(
            __('Past %1$d minutes: %2$d unique visitors from %3$d countries (%4$d bots, %5$d humans).', 'mp-ukagaka'),
            $minutes,
            intval($pulse['unique_ips'] ?? 0),
            intval($pulse['unique_countries'] ?? 0),
            intval($pulse['bot_count'] ?? 0),
            intval($pulse['human_count'] ?? 0)
        );

        return [
            'minutes_window'  => $minutes,
            'total_requests'  => intval($pulse['total'] ?? 0),
            'unique_visitors' => intval($pulse['unique_ips'] ?? 0),
            'country_count'   => intval($pulse['unique_countries'] ?? 0),
            'countries'       => $countries,
            'bot_requests'    => intval($pulse['bot_count'] ?? 0),
            'human_requests'  => intval($pulse['human_count'] ?? 0),
            'summary'         => $summary,
        ];
    }
}
