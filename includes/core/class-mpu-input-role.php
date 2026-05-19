<?php
/**
 * Input role resolver for LLM/tool boundaries.
 *
 * @package MP_Ukagaka
 * @subpackage Core
 */

if (!defined('ABSPATH')) {
    exit();
}

class MPU_Input_Role {
    const ADMIN      = 'admin';
    const SYSTEM     = 'system';
    const SUBSCRIBER = 'subscriber';
    const VISITOR    = 'visitor';

    /**
     * Resolve the caller role from explicit context plus current WP state.
     *
     * @param array $context Optional context such as source=cron/system.
     * @return string
     */
    public static function resolve(array $context = []): string {
        if (!empty($context['role']) && self::is_known_role((string) $context['role'])) {
            return (string) $context['role'];
        }

        $source = isset($context['source']) ? (string) $context['source'] : '';
        if (in_array($source, ['cron', 'system', 'diary', 'auto_talk'], true)) {
            return self::SYSTEM;
        }

        if (current_user_can('manage_options')) {
            return self::ADMIN;
        }

        if (is_user_logged_in()) {
            return self::SUBSCRIBER;
        }

        return self::VISITOR;
    }

    /**
     * Check whether a role can expose or execute an ability.
     *
     * @param string $ability_name Full ability name, e.g. mp-ukagaka/get-popular-posts.
     * @param string $role         One of this class' role constants.
     * @return bool
     */
    public static function can_use_ability(string $ability_name, string $role): bool {
        $ability_name = self::normalize_ability_name((string) $ability_name);
        $role = self::is_known_role((string) $role) ? (string) $role : self::VISITOR;

        $whitelist = [
            self::ADMIN      => '*',
            self::SYSTEM     => [
                'mp-ukagaka/get-visitor-pulse',
                'mp-ukagaka/get-popular-posts',
                'mp-ukagaka/get-recent-ai-crawlers',
            ],
            self::SUBSCRIBER => [
                'mp-ukagaka/get-popular-posts',
            ],
            self::VISITOR    => [
                'mp-ukagaka/get-popular-posts',
            ],
        ];

        $allowed = isset($whitelist[$role]) ? $whitelist[$role] : [];
        return $allowed === '*' || in_array($ability_name, $allowed, true);
    }

    /**
     * Resolve and check in one call.
     *
     * @param string $ability_name Ability name.
     * @param array  $context      Optional resolver context.
     * @return bool
     */
    public static function current_can_use_ability(string $ability_name, array $context = []): bool {
        return self::can_use_ability($ability_name, self::resolve($context));
    }

    /**
     * Convert LLM-safe tool names back to ability names when needed.
     *
     * @param string $ability_name Ability or tool name.
     * @return string
     */
    public static function normalize_ability_name(string $ability_name): string {
        return str_replace('__', '/', (string) $ability_name);
    }

    /**
     * @param string $role Role to validate.
     * @return bool
     */
    private static function is_known_role(string $role): bool {
        return in_array($role, [self::ADMIN, self::SYSTEM, self::SUBSCRIBER, self::VISITOR], true);
    }
}

