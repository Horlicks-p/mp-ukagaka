<?php
/**
 * Interface for AI Providers.
 *
 * Defines the contract for all AI engine implementations in MP Ukagaka.
 *
 * @package MPU
 * @since 2.10.0
 */

if (!defined("ABSPATH")) {
    exit();
}

/**
 * Interface MPU_AI_Provider_Interface
 *
 * Each AI provider (Gemini, OpenAI, Claude, Ollama) must implement this interface.
 */
interface MPU_AI_Provider_Interface {
    /**
     * Get the unique slug for this provider (e.g., 'gemini', 'openai').
     *
     * @return string
     */
    public function get_slug();

    /**
     * Check if the provider supports a specific feature.
     *
     * @param string $feature Feature constant (e.g., 'tools', 'chat').
     * @return bool
     */
    public function supports($feature);

    /**
     * Single-turn text generation (corresponds to old mpu_call_ai_api).
     *
     * @param array{
     *   api_key:       string,
     *   model:         string,
     *   system_prompt: string,
     *   user_prompt:   string,
     *   language:      string,
     *   max_tokens:    int|null,
     *   temperature:   float|null,
     * } $args
     * @return string|WP_Error
     */
    public function generate_text(array $args);

    /**
     * Multi-turn chat generation with tool call loop (corresponds to old mpu_call_ai_api_with_messages).
     *
     * @param array{
     *   api_key:       string,
     *   model:         string,
     *   messages:      array,
     *   language:      string,
     *   max_tokens:    int|null,
     *   temperature:   float|null,
     * } $args
     * @return string|WP_Error
     */
    public function generate_chat(array $args);

    /**
     * Backend connection test (corresponds to old REST Test specific flow).
     *
     * @param array{
     *   api_key:       string,
     *   model:         string,
     *   endpoint:      string|null,
     * } $args
     * @return WP_REST_Response|WP_Error
     */
    public function test_connection(array $args);

    /**
     * SSE Streaming chat generation.
     *
     * @param array{
     *   api_key:       string,
     *   model:         string,
     *   system_prompt: string,
     *   messages:      array,
     *   language:      string,
     *   max_tokens:    int|null,
     *   temperature:   float|null,
     * } $args
     * @param callable $emit function(string $event, array|string $data): void
     * @param array $context Additional request context
     * @return void|WP_Error
     */
    public function generate_chat_stream(array $args, $emit, array $context = []);
}
