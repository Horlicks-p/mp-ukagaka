<?php

namespace MP_Ukagaka\McpTools\Abilities;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Ability: Get Recent AI Crawlers
 *
 * 比對 Slimstat 的 bot 紀錄與 AI 爬蟲 UA 對照表，
 * 回傳最近一段時間命中的 AI 爬蟲清單與訪問次數。
 *
 * UA 對照表借鑑自 atlant-security AICrawlers 模組。
 */
class AI_Crawler_Ability
{
    public static function register()
    {
        if (!function_exists('wp_register_ability')) {
            return;
        }

        wp_register_ability(
            'mp-ukagaka/get-recent-ai-crawlers',
            [
                'label'       => __('Get Recent AI Crawlers', 'mp-ukagaka'),
                'description' => __('Detect AI/LLM training crawlers (GPTBot, ClaudeBot, Bytespider, Google-Extended, etc.) seen recently by matching Slimstat user agents.', 'mp-ukagaka'),
                'category'    => 'mp-ukagaka',
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'seconds' => [
                            'type'        => 'integer',
                            'description' => __('Time window in seconds (60–86400, default 600).', 'mp-ukagaka'),
                            'default'     => 600,
                        ],
                    ],
                ],
                'output_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'seconds_window'    => ['type' => 'integer'],
                        'total_hits'        => ['type' => 'integer'],
                        'distinct_crawlers' => ['type' => 'integer'],
                        'crawlers'          => [
                            'type'  => 'array',
                            'items' => [
                                'type'       => 'object',
                                'properties' => [
                                    'name'    => ['type' => 'string'],
                                    'company' => ['type' => 'string'],
                                    'purpose' => ['type' => 'string'],
                                    'count'   => ['type' => 'integer'],
                                ],
                            ],
                        ],
                        'summary' => ['type' => 'string'],
                    ],
                ],
                'execute_callback'    => [self::class, 'execute'],
                'permission_callback' => function () { return current_user_can('manage_options'); },
                'meta' => [
                    'show_in_rest' => true,
                ],
            ]
        );
    }

    public static function execute($args = [])
    {
        if (!function_exists('mpu_get_recent_ai_crawlers')) {
            return new \WP_Error('dependency_missing', 'AI crawler detection functions are not loaded.');
        }

        $seconds  = isset($args['seconds']) ? max(60, min(86400, intval($args['seconds']))) : 600;
        $crawlers = mpu_get_recent_ai_crawlers($seconds);

        $total_hits = 0;
        foreach ($crawlers as $c) {
            $total_hits += intval($c['count'] ?? 0);
        }

        if (empty($crawlers)) {
            $summary = sprintf(
                __('No AI crawlers detected in the past %d seconds.', 'mp-ukagaka'),
                $seconds
            );
        } else {
            $names = array_map(function ($c) {
                return $c['name'] . ' x' . intval($c['count']);
            }, array_slice($crawlers, 0, 5));
            $summary = sprintf(
                __('Past %1$d seconds: %2$d AI crawler hits across %3$d distinct crawlers (top: %4$s).', 'mp-ukagaka'),
                $seconds,
                $total_hits,
                count($crawlers),
                implode(', ', $names)
            );
        }

        // 對外輸出去掉 internal id
        $public = array_map(function ($c) {
            return [
                'name'    => $c['name'] ?? '',
                'company' => $c['company'] ?? '',
                'purpose' => $c['purpose'] ?? '',
                'count'   => intval($c['count'] ?? 0),
            ];
        }, $crawlers);

        return [
            'seconds_window'    => $seconds,
            'total_hits'        => $total_hits,
            'distinct_crawlers' => count($crawlers),
            'crawlers'          => $public,
            'summary'           => $summary,
        ];
    }
}
