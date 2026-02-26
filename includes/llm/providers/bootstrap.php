<?php
/**
 * AI Providers Bootstrap.
 *
 * Loads all AI provider related classes and interfaces.
 *
 * @package MPU
 * @since 2.10.0
 */

if (!defined("ABSPATH")) {
    exit();
}

/**
 * MPU AI Providers Bootstrap
 */
function mpu_ai_providers_init() {
    $dir = plugin_dir_path(__FILE__);

    // Load interface and base class first
    require_once $dir . 'interface-mpu-ai-provider.php';
    require_once $dir . 'class-mpu-ai-provider-base.php';

    // Load factory
    require_once $dir . 'class-mpu-ai-provider-factory.php';

    // Load implementations
    require_once $dir . 'class-mpu-ai-provider-gemini.php';
    require_once $dir . 'class-mpu-ai-provider-openai.php';
    require_once $dir . 'class-mpu-ai-provider-claude.php';
    require_once $dir . 'class-mpu-ai-provider-ollama.php';
}

// Initialize
mpu_ai_providers_init();
