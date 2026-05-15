<?php

/**
 * Abilities API Integration
 * 
 * Bridges MP Ukagaka with the WordPress Abilities API (Core or Plugin) to enable tool use for AI characters.
 * 
 * @package MP_Ukagaka
 * @subpackage Integrations
 */

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Check if Abilities API is available
 * 
 * @return bool
 */
function mpu_is_mcp_active()
{
    // We now rely on the Core Abilities API
    return function_exists('wp_get_abilities');
}

/**
 * Get available Abilities formatted for LLM (OpenAI/Gemini/Claude format)
 * 
 * @param string $provider 'openai', 'gemini', 'claude', etc.
 * @param array  $context  Optional input role context.
 * @return array Tool definitions
 */
function mpu_get_mcp_tools_for_llm($provider = 'openai', array $context = [])
{
    if (!mpu_is_mcp_active()) {
        return [];
    }

    // Resolve input role before exposing tool definitions to the LLM.
    if (empty($context) && function_exists('mpu_get_request_input_context')) {
        $context = mpu_get_request_input_context();
    }

    $role = class_exists('MPU_Input_Role')
        ? MPU_Input_Role::resolve($context)
        : (current_user_can('manage_options') ? 'admin' : 'visitor');

    // Get all registered abilities from Core
    $abilities = wp_get_abilities();
    $formatted_tools = [];

    foreach ($abilities as $ability) {
        $original_name = $ability->get_name();
        if (class_exists('MPU_Input_Role') && !MPU_Input_Role::can_use_ability($original_name, $role)) {
            continue;
        }

        // Sanitize name: replace / with __ to satisfy OpenAI/Gemini regex ^[a-zA-Z0-9_-]+$
        $name = str_replace('/', '__', $original_name);
        
        $description = $ability->get_description();
        $input_schema = $ability->get_input_schema();

        // Normalize for LLM: convert union types like ['object','null'] → 'object'
        if (!empty($input_schema)) {
            $input_schema = mpu_normalize_schema_for_llm($input_schema);
        }

        // Ensure input_schema is an object for JSON encoding if empty
        if (empty($input_schema)) {
            $input_schema = new stdClass();
        }

        // Format for OpenAI / Ollama
        if ($provider === 'openai' || $provider === 'ollama') {
            $formatted_tools[] = [
                'type' => 'function',
                'function' => [
                    'name' => $name,
                    'description' => $description,
                    'parameters' => $input_schema,
                ]
            ];
        }
    // Format for Gemini
        elseif ($provider === 'gemini') {
            // Remove additionalProperties from schema for Gemini
            $clean_schema = mpu_remove_additional_properties($input_schema);
            
            // 修正：如果是空陣列，強制轉為空物件，否則 json_encode 會變成 []
            // 即使前面已經檢查過 empty($input_schema)，但 mpu_remove_additional_properties 可能會把
            // 原本有內容 (只含 additionalProperties) 的 schema 變成空陣列，因此需二次檢查。
            if (empty($clean_schema)) {
                $clean_schema = new stdClass();
            }

            // Gemini expects "name", "description", "parameters" at top level
            $formatted_tools[] = [
                'name' => $name,
                'description' => $description,
                'parameters' => $clean_schema,
            ];
        }
        // Format for Claude
        elseif ($provider === 'claude') {
            // Ensure input_schema is valid and has 'type'
            if (empty($input_schema)) {
                $input_schema = [
                    'type' => 'object',
                    'properties' => new stdClass(),
                ];
            } elseif (is_array($input_schema) && !isset($input_schema['type'])) {
                 $input_schema['type'] = 'object';
            } elseif (is_object($input_schema) && !isset($input_schema->type)) {
                 $input_schema->type = 'object';
            }

            $formatted_tools[] = [
                'name' => $name,
                'description' => $description,
                'input_schema' => $input_schema,
            ];
        }
    }

    return $formatted_tools;
}

/**
 * Normalize JSON Schema for LLM compatibility.
 * LLM providers require `type` to be a plain string; JSON Schema union types
 * like ['object', 'null'] are not accepted. This picks the first non-null type.
 *
 * @param array|object $schema
 * @return array|object
 */
function mpu_normalize_schema_for_llm($schema)
{
    $was_object = is_object($schema);
    if ($was_object) {
        $schema = (array) $schema;
    }

    if (!is_array($schema)) {
        return $schema;
    }

    if (isset($schema['type']) && is_array($schema['type'])) {
        $non_null = array_values(array_filter($schema['type'], function ($t) {
            return $t !== 'null';
        }));
        $schema['type'] = !empty($non_null) ? $non_null[0] : 'object';
    }

    foreach ($schema as $key => $value) {
        if (is_array($value) || is_object($value)) {
            $schema[$key] = mpu_normalize_schema_for_llm($value);
        }
    }

    return $was_object ? (object) $schema : $schema;
}

/**
 * Remove 'additionalProperties' from schema recursively
 *
 * @param array|object $schema
 * @return array|object
 */
function mpu_remove_additional_properties($schema)
{
    if (is_object($schema)) {
        $schema = (array) $schema;
    }

    if (!is_array($schema)) {
        return $schema;
    }

    if (isset($schema['additionalProperties'])) {
        unset($schema['additionalProperties']);
    }

    foreach ($schema as $key => $value) {
        if (is_array($value) || is_object($value)) {
            $schema[$key] = mpu_remove_additional_properties($value);
        }
    }

    return $schema;
}

/**
 * Execute an Ability
 * 
 * @param string $tool_name
 * @param array $arguments
 * @param array $context Optional input role context.
 * @return mixed Result of the tool execution
 */
function mpu_execute_mcp_tool($tool_name, $arguments, array $context = [])
{
    if (!mpu_is_mcp_active()) {
        return new WP_Error('mcp_inactive', 'Abilities API is not active.');
    }

    // Resolve input role before executing tool calls.
    if (empty($context) && function_exists('mpu_get_request_input_context')) {
        $context = mpu_get_request_input_context();
    }

    // Sanitize name: convert __ back to /
    $lookup_name = $tool_name;
    if (strpos($tool_name, '__') !== false) {
        $lookup_name = str_replace('__', '/', $tool_name);
    }

    if (class_exists('MPU_Input_Role')) {
        $role = MPU_Input_Role::resolve($context);
        if (!MPU_Input_Role::can_use_ability($lookup_name, $role)) {
            return new WP_Error('permission_denied', 'This role is not allowed to use the requested tool.');
        }
    } elseif (!current_user_can('manage_options')) {
        return new WP_Error('permission_denied', 'Only administrators are allowed to use tools.');
    }

    // Try to get ability directly
    $ability = wp_get_ability($lookup_name);
    
    // Fallback: try original if lookup failed (maybe it really has __ ?)
    if (!$ability && $lookup_name !== $tool_name) {
        $ability = wp_get_ability($tool_name);
    }
    
    if (!$ability) {
        return new WP_Error('tool_not_found', "Ability '{$tool_name}' not found.");
    }

    try {
        // Execute using the Core API's execute method which handles validation and perms
        $result = $ability->execute($arguments);
        return $result;
    } catch (Exception $e) {
        return new WP_Error('execution_failed', $e->getMessage());
    }
}

/**
 * Initialize MCP Tools Manager
 */
/**
 * Initialize MCP Tools Manager
 */
function mpu_init_mcp_tools()
{
    static $initialized = false;
    if ($initialized) {
        return;
    }

    require_once plugin_dir_path(__FILE__) . '../mcp-tools/manager.php';
    if (class_exists('\MP_Ukagaka\McpTools\Manager')) {
        \MP_Ukagaka\McpTools\Manager::init();
        $initialized = true;
    }
}

if (did_action('plugins_loaded')) {
    mpu_init_mcp_tools();
} else {
    add_action('plugins_loaded', 'mpu_init_mcp_tools');
}


