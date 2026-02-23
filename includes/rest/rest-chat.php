<?php

/**
 * REST API Endpoints: Chat Handlers
 * 
 * @package MP_Ukagaka
 * @subpackage REST
 */

if (!defined('ABSPATH')) {
    exit();
}

// 載入對話相關處理器
require_once __DIR__ . '/chat/context-handler.php';
require_once __DIR__ . '/chat/greet-handler.php';
require_once __DIR__ . '/chat/user-chat-handler.php';

/**
 * Register Chat REST API routes.
 */
function mpu_register_rest_chat_routes() {
    $namespace = 'mp-ukagaka/v1';

    register_rest_route($namespace, '/chat/context', array(
        'methods'             => WP_REST_Server::CREATABLE,
        'callback'            => 'mpu_rest_chat_context',
        'permission_callback' => '__return_true',
    ));

    register_rest_route($namespace, '/chat/greet', array(
        'methods'             => WP_REST_Server::CREATABLE,
        'callback'            => 'mpu_rest_chat_greet',
        'permission_callback' => '__return_true',
    ));

    register_rest_route($namespace, '/chat/user', array(
        'methods'             => WP_REST_Server::CREATABLE,
        'callback'            => 'mpu_rest_user_chat',
        'permission_callback' => '__return_true',
    ));
}
add_action('rest_api_init', 'mpu_register_rest_chat_routes');
