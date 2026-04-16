<?php
/**
 * Base class for AI Providers.
 *
 * Implements shared utility methods for all providers.
 *
 * @package MPU
 * @since 2.10.0
 */

if (!defined("ABSPATH")) {
    exit();
}

/**
 * Class MPU_AI_Provider_Base
 */
abstract class MPU_AI_Provider_Base implements MPU_AI_Provider_Interface {
    /**
     * Feature constants for supports() check.
     */
    const FEATURE_TOOLS          = 'tools';           // Function calling / tool use
    const FEATURE_CHAT           = 'chat';            // Multi-turn chat
    const FEATURE_STREAMING      = 'streaming';       // SSE Streaming (not yet implemented)
    const FEATURE_LOCAL_ENDPOINT = 'local_endpoint';  // Customizable endpoint (Ollama)

    /**
     * Default implementation of supports().
     *
     * @param string $feature
     * @return bool
     */
    public function supports($feature) {
        // Most providers should override this for specific features they support.
        return false;
    }

    /**
     * Create a standardized WP_Error with raw response data.
     *
     * @param string $code
     * @param string $message
     * @param int|null $http_status
     * @param string|null $raw_body
     * @return WP_Error
     */
    protected function error($code, $message, $http_status = null, $raw_body = null) {
        return new WP_Error($code, $message, [
            'http_status' => $http_status,
            'raw_body'    => $raw_body,
        ]);
    }

    /**
     * Handle API error from wp_remote_post or response.
     *
     * @param array|WP_Error $response Result from wp_remote_post.
     * @return WP_Error
     */
    protected function handle_api_error($response) {
        if (is_wp_error($response)) {
            return $response;
        }

        $status = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $error_msg = mpu_parse_api_error_message($body, '');
        $provider_code = $this->get_slug() . '_error';

        if ($error_msg === '') {
            $error_msg = sprintf(__('HTTP %s エラー', 'mp-ukagaka'), $status);
        }

        return $this->error($provider_code, $error_msg, $status, $body);
    }
}
