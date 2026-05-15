<?php

namespace MP_Ukagaka\McpTools\Abilities;

/**
 * Ability: Get Popular Posts (via WP-PostViews)
 *
 * Checks if WP-PostViews is active and retrieves the most viewed posts.
 */
class Wp_PostViews_Ability
{
    /**
     * Register the ability with WordPress Core
     */
    public static function register()
    {
        if (!function_exists('wp_register_ability')) {
            return;
        }

        wp_register_ability(
            'mp-ukagaka/get-popular-posts',
            [
                'label' => 'Get Popular Posts',
                'description' => __('Get a list of most viewed posts (requires WP-PostViews plugin).', 'mp-ukagaka'),
                'category' => 'mp-ukagaka',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                         'limit' => [
                             'type' => 'integer',
                             'description' => __('Number of posts to retrieve (default: 5, max: 10)', 'mp-ukagaka'),
                             'default' => 5,
                         ],
                         'days' => [
                             'type' => 'integer',
                             'description' => __('Number of days to look back for statistics (0 = all time, default: 30)', 'mp-ukagaka'),
                             'default' => 30,
                         ],
                    ],
                ],
                'output_schema' => [
                     'type' => 'object',
                     'properties' => [
                         'count' => ['type' => 'integer'],
                         'posts' => [
                             'type' => 'array',
                             'items' => [
                                 'type' => 'object',
                                 'properties' => [
                                     'id' => ['type' => 'integer'],
                                     'title' => ['type' => 'string'],
                                     'url' => ['type' => 'string'],
                                     'views' => ['type' => 'integer'],
                                     'date' => ['type' => 'string']
                                 ]
                             ]
                         ],
                         'note' => ['type' => 'string']
                     ]
                ],
                'execute_callback' => [self::class, 'execute'],
                'permission_callback' => function() {
                    return class_exists('\MPU_Input_Role')
                        ? \MPU_Input_Role::current_can_use_ability('mp-ukagaka/get-popular-posts')
                        : true;
                },
                'meta' => [
                    'show_in_rest' => true, 
                ]
            ]
        );
    }

    /**
     * Execute the ability
     * 
     * @param array $args
     * @return array|\WP_Error
     */
    public static function execute($args = [])
    {
        // Check if WP-PostViews is active (or similar plugin providing 'views' meta)
        // Note: Some themes implement 'views' meta manually without the plugin class.
        // We check if we can query by meta_key 'views'.
        
        $limit = isset($args['limit']) ? min(10, max(1, intval($args['limit']))) : 5;
        $query_args = [
            'post_type' => 'post',
            'posts_per_page' => $limit,
            'meta_key' => 'views',
            'orderby' => 'meta_value_num',
            'order' => 'DESC',
            'post_status' => 'publish',
            'ignore_sticky_posts' => true,
        ];

        if (isset($args['days']) && intval($args['days']) > 0) {
            $days = intval($args['days']);
            $query_args['date_query'] = [
                [
                    'after' => $days . ' days ago',
                    'inclusive' => true,
                ],
            ];
            $note = sprintf(__('Showing posts published in the last %d days, ordered by total views.', 'mp-ukagaka'), $days);
        } else {
            $note = __('Showing all-time most viewed posts.', 'mp-ukagaka');
        }
        
        $query = new \WP_Query($query_args);
        $posts_data = [];

        if ($query->have_posts()) {
            foreach ($query->posts as $post) {
                $views = get_post_meta($post->ID, 'views', true);
                if ($views === '') $views = 0; // Ensure 0 if empty
                
                $posts_data[] = [
                    'id' => $post->ID,
                    'title' => $post->post_title,
                    'url' => urldecode(get_permalink($post->ID)),
                    'views' => intval($views),
                    'date' => $post->post_date,
                ];
            }
        }
        
        return [
            'count' => count($posts_data),
            'posts' => $posts_data,
            'note' => $note,
        ];
    }
}
