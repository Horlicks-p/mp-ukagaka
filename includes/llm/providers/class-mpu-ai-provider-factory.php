<?php
/**
 * Factory class for AI Providers.
 *
 * Provides a single entry point for creating AI engine instances.
 *
 * @package MPU
 * @since 2.10.0
 */

if (!defined("ABSPATH")) {
    exit();
}

/**
 * Class MPU_AI_Provider_Factory
 */
class MPU_AI_Provider_Factory {
    /**
     * Get a provider instance by slug.
     *
     * @param string $provider_slug Slug like 'gemini', 'openai', 'claude', 'ollama'.
     * @return MPU_AI_Provider_Interface|WP_Error
     */
    public static function get_provider($provider_slug) {
        $slug = strtolower(trim($provider_slug));

        switch ($slug) {
            case 'gemini':
                return new MPU_AI_Provider_Gemini();
            case 'openai':
                return new MPU_AI_Provider_OpenAI();
            case 'claude':
                return new MPU_AI_Provider_Claude();
            case 'ollama':
                return new MPU_AI_Provider_Ollama();
            default:
                return new WP_Error(
                    'unsupported_provider',
                    sprintf(__('Unsupported AI provider: %s', 'mp-ukagaka'), $provider_slug)
                );
        }
    }
}
