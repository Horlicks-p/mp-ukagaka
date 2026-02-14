<?php

namespace MP_Ukagaka\McpTools;

/**
 * Manager for MCP Tools
 * 
 * Handles loading and registration of all MCP abilities.
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
