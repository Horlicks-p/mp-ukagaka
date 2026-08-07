<?php

namespace MP_Ukagaka\McpTools;

/**
 * Manager for MCP Tools
 *
 * Handles loading and registration of all MCP abilities.
 *
 * External exposure policy — the absent meta keys are deliberate, not oversights:
 *
 * - No ability declares `meta.mcp.public`. The bundled WordPress MCP adapter only
 *   surfaces abilities where that is strictly true, so none of these are reachable
 *   from an external MCP client. Frieren does not need it: the in-plugin tool path
 *   reads wp_get_abilities() directly (see integrations/abilities-integration.php).
 * - The three Bot Blocker abilities deliberately omit `meta.show_in_rest`, so they
 *   stay off the wp-abilities/v1 REST namespace. The read-only query abilities set
 *   it; the two that write (ban-ip, clear-bot-blocker-data) and the stats reader
 *   that sits beside them do not. permission_callback already confines them to
 *   administrators — keeping them off the public namespace is defence in depth,
 *   not the only control.
 *
 * Opening either surface is a product decision, not a missing-field fix.
 * AbilityAnnotationsTest pins the current state so a change has to be intentional.
 */
class Manager
{
    /**
     * List of Ability classes to register
     * 
     * @var array
     */
    protected static $abilities = [
        '\MP_Ukagaka\McpTools\Abilities\Wp_PostViews_Ability',
        '\MP_Ukagaka\McpTools\Abilities\Wp_Bot_Blocker_Ability',
        '\MP_Ukagaka\McpTools\Abilities\Visitor_Pulse_Ability',
        '\MP_Ukagaka\McpTools\Abilities\AI_Crawler_Ability',
    ];

    /**
     * Initialize the Manager
     */
    public static function init()
    {
        // Hook into Abilities API initialization
        add_action('wp_abilities_api_init', [self::class, 'register_abilities']);
        
        // Also ensure category is registered
        add_action('wp_abilities_api_categories_init', [self::class, 'register_category']);
    }

    /**
     * Register the ability category
     */
    public static function register_category()
    {
        if (function_exists('wp_register_ability_category') && !wp_has_ability_category('mp-ukagaka')) {
            wp_register_ability_category(
                'mp-ukagaka',
                array(
                    'label'       => __('MP Ukagaka', 'mp-ukagaka'),
                    'description' => __('Abilities provided by MP Ukagaka plugin.', 'mp-ukagaka'),
                )
            );
        }
    }

    /**
     * Load and register all abilities
     */
    public static function register_abilities()
    {
        if (!function_exists('wp_register_ability')) {
            return;
        }

        self::load_ability_files();

        foreach (self::$abilities as $class) {
            if (class_exists($class) && method_exists($class, 'register')) {
                $class::register();
            }
        }
    }

    /**
     * Load all ability class files
     */
    protected static function load_ability_files()
    {
        $abilities_dir = plugin_dir_path(__FILE__) . 'abilities/';
        
        // Load known ability files
        // We can either glob or require specifically.
        // For now, let's glob to make it easy to add new ones.
        foreach (glob($abilities_dir . '*.php') as $file) {
            require_once $file;
        }
    }
}
