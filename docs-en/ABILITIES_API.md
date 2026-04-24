# WordPress Abilities API Integration Guide

**MP Ukagaka** uses the **Abilities API** to give your AI character (Ukagaka) the ability to interact directly with your WordPress site.

This feature allows the AI character to use registered "Abilities", such as querying site information, managing posts, or executing other WordPress functions, transforming it from a simple chatbot into a site management assistant.

## What is the Abilities API?

The **Abilities API** provides a standardized way to register, discover, and execute WordPress abilities.

For MP Ukagaka, this means the character can convert abilities registered on the WordPress side into tools callable by the LLM, allowing the character to query site information or trigger controlled actions in appropriate contexts.

## Relationship between Abilities API and MCP Naming

Currently, the plugin **externally** relies primarily on the Abilities API, but the **internal implementation naming** still retains some MCP terminology. This is to maintain backward compatibility and reduce refactoring risks.

For example:

- The integration file is still `includes/integrations/abilities-integration.php`
- Internal function names are still `mpu_get_mcp_tools_for_llm()`, `mpu_execute_mcp_tool()`
- The internal directory still uses `includes/mcp-tools/`

This does not mean the plugin still relies on the legacy MCP Adapter, but rather indicates:

- **Internally**: Continuing to use existing MCP naming as a transition layer for the implementation.
- **Externally**: Using the WordPress Abilities API as the official interface for registering and discovering abilities.

If you are letting MP Ukagaka use these abilities directly within the site, you should adhere to the **Abilities API**.
If you want to expose in-site abilities to an external agent (like other tools supporting MCP), then you might need an additional adapter layer.

## Supported Models

Currently, this plugin supports multiple providers with Tool Calling capabilities, including:

- **Google Gemini**
- **Anthropic Claude**
- **OpenAI**
- **Ollama**

The actually available models depend on the plugin's current settings and the corresponding provider implementation.

## Permissions and Security

Core abilities involving sensitive operations (such as file operations, deleting posts, etc.) will be automatically intercepted if triggered by non-admin users. The system will return an insufficient permissions prompt to ensure security.

![Permission Interception Diagram](../screenshot6.PNG)

*Permission Control: System reaction when a non-admin role triggers a tool call*

## How to Add Abilities (Developer Guide)

MP Ukagaka will automatically detect and use all Abilities registered to the WordPress core.
You can add abilities in two ways:

### Method 1: Standard WordPress Way (Suitable for any plugin/theme)

This is the official WordPress standard approach. Any plugin can register abilities this way, and MP Ukagaka will automatically read and use them.

```php
// In the functions.php of your custom plugin or theme
add_action( 'wp_abilities_api_init', function() {
    if ( function_exists( 'wp_register_ability' ) ) {
        wp_register_ability(
            'my-plugin/say-hello', // Unique identifier for the ability
            array(
                'label'       => 'Say Hello',
                'description' => 'A simple ability that says hello.',
                'input_schema' => array(
                    'type' => 'object',
                    'properties' => array(
                        'name' => array( 'type' => 'string' ),
                    ),
                ),
                'execute_callback' => function( $args ) {
                    $name = isset( $args['name'] ) ? $args['name'] : 'World';
                    return "Hello, " . $name . "!";
                },
            )
        );
    }
} );
```

### Method 2: MP Ukagaka Modular Way (Recommended for developing this plugin / AI capability building guide)

If you are developing the `mp-ukagaka` plugin itself, it is recommended to align with our built-in modular architecture. The following is a **step-by-step development guide (SOP) and pitfall avoidance guide** compiled based on the development experience of `class-wp-postviews-ability.php` and `class-wp-bot-blocker-ability.php`.

#### 1. Architecture Overview

```text
includes/mcp-tools/
├── manager.php                  # Automatically discovers and registers all ability classes
└── abilities/
    ├── class-wp-postviews-ability.php     # Example: Read-only, no parameters
    └── class-wp-bot-blocker-ability.php   # Example: Multiple abilities, with parameters and Enums
```

**Execution Flow:**

1. `manager.php` → `register_abilities()` → calls your `YourClass::register()` during `wp_abilities_api_init`.
2. `abilities-integration.php` → `mpu_get_mcp_tools_for_llm()` → Formats the tool schema according to different LLM providers.
3. LLM request invokes the tool → `mpu_execute_mcp_tool()` → `$ability->execute($args)` → Calls your defined callback.

**Admin Permission Restriction:** The tool definition is **not** sent to non-admin visitors. Only users with `current_user_can('manage_options')` can trigger a tool call. This restriction is enforced at the integration layer, not within the ability itself.

#### 2. Step-by-Step Development Guide (SOP)

**Step 1: Create the Ability Class File**

File path: `includes/mcp-tools/abilities/class-{slug}-ability.php`

```php
<?php

namespace MP_Ukagaka\McpTools\Abilities;

class Wp_YourFeature_Ability
{
    public static function register()
    {
        // Guard: Ensure wp_register_ability exists
        if (!function_exists('wp_register_ability')) {
            return;
        }

        // Guard: Check external plugin dependencies (if any)
        if (!function_exists('your_plugin_function')) {
            return;
        }

        wp_register_ability('mp-ukagaka/your-ability-name', array(
            'label'               => __('Human-readable ability name', 'mp-ukagaka'),
            'description'         => __('What this ability does. The semantics must be extremely precise.', 'mp-ukagaka'),
            'category'            => 'mp-ukagaka',
            'input_schema'        => array(
                'type'       => 'object',
                'properties' => array(
                    'param_name' => array(
                        'type'        => 'string',
                        'description' => __('Precise parameter description. If it is an Enum, add "Use this value when..." for each value.', 'mp-ukagaka'),
                    ),
                ),
                'required' => array('param_name'),
            ),
            'execute_callback'    => [self::class, 'your_callback'],
            'permission_callback' => function () { return true; },
        ));
    }

    public static function your_callback($args)
    {
        // Your processing logic
        return 'Result string or array';
    }
}
```

**Step 2: Register the class in manager.php**

Add the full class namespace to the `$abilities` array in `includes/mcp-tools/manager.php`:

```php
protected static $abilities = [
    '\MP_Ukagaka\McpTools\Abilities\Wp_PostViews_Ability',
    '\MP_Ukagaka\McpTools\Abilities\Wp_Bot_Blocker_Ability',
    '\MP_Ukagaka\McpTools\Abilities\Wp_YourFeature_Ability',  // ← Add here
];
```

**Step 3: Verify Registration**

Log in to WordPress as an administrator and enter `/debug_mcp` in the Frieren chat window. Confirm that:

- Your ability name appears in the "Tools found:" list.
- "Tool count" has increased by the number you added.

**Step 4: Dialogue Testing**

Ask Frieren to use the ability. After her response, go to the actual data source (Database, Option, etc.) to verify if the result is correct.

#### 3. Required Fields

The following 5 fields are **absolutely required** (missing any of them will result in a silent error):

| Field                 | Required | Description                                                                                          |
| --------------------- | -------- | ---------------------------------------------------------------------------------------------------- |
| `label`               | **YES**  | If missing, an `InvalidArgumentException` will be thrown (swallowed by the underlying layer), and the ability won't be registered. |
| `description`         | **YES**  | The only basis for the LLM to determine when to call this tool.                                      |
| `category`            | **YES**  | Must be set to `'mp-ukagaka'`.                                                                       |
| `execute_callback`    | **YES**  | Please use the `[self::class, 'method_name']` array format.                                          |
| `permission_callback` | **YES**  | Please write `function () { return true; }` (Admin check is verified in the outer call).             |

#### 4. input_schema Rules

**Abilities without parameters "must" define input_schema:**

```php
// ✅ Correct writing — LLM sends {}, validate_input sees defined schema → passes
'input_schema' => array(
    'type'       => 'object',
    'properties' => new \stdClass(),  // ← Must use stdClass, cannot use [] ([] will be converted to an array, not an object)
),

// ❌ Incorrect writing — LLM sends {} (not null), validate_input receives it and returns WP_Error → Frieren will say "I couldn't get the information"
// (Completely omitting input_schema is wrong)
```

**Abilities with parameters:**

```php
'input_schema' => array(
    'type'       => 'object',
    'properties' => array(
        'my_param' => array(
            'type'        => 'string',
            'description' => '...',
        ),
    ),
    'required' => array('my_param'),
),
```

#### 5. Writing Precise Descriptions to Guide the LLM

This is the most critical step. Vague descriptions will cause the LLM to choose the wrong tool or parameters.

**Ability-level description:**
Clearly explain **what it does** and **when to use it**:

```php
// ❌ Too vague
'description' => 'Clear the log file or reset the IP ban list.'

// ✅ Intent is clear
'description' => 'Clear the Moelog Bot Blocker intercept records or reset the IP ban list.'
```

**Enum parameter description:**
Always add **"Use this when..."** guidance for each Enum value:

```php
// ❌ Will cause the LLM to guess wildly
'description' => 'What to clear: "logs", "ips", or "both".'

// ✅ Precisely guides based on user intent
'description' =>
    '"logs" — Deletes all intercept records in the database. Use this when the user asks to clear records, history, logs, or intercept data. ' .
    '"ips" — Clears only the IP blacklist. Use this when the user asks to unblock IPs or reset the ban list. ' .
    '"both" — Clears both.'
```

**Action vocabulary (Action vs. Query):**
Avoid using file system metaphors to describe database operations:

- ❌ "log file" 👉 ✅ "log records in the database"
- ❌ "config file" 👉 ✅ "settings stored in WordPress options"
- ❌ "reset file" 👉 ✅ "delete records from the table"

**Post-operation validation return value:**
If the ability modifies data, please return a validation result so the LLM can report accurately:

```php
public static function clear_callback($args)
{
    moelog_bot_blocker_clear_logs();
    return 'Cleared the intercept log table. All records deleted.'; // Let the LLM know it succeeded
}
```

#### 6. Naming Conventions

- **Ability name format:** `mp-ukagaka/{slug}` — Only use lowercase letters, numbers, and hyphens (`-`).
  - Strictly adhere to the regular expression: `/^[a-z0-9-]+\/[a-z0-9-]+$/`
  - **Underscores (`_`) are prohibited**.
  - ✅ `mp-ukagaka/get-bot-blocker-stats`
  - ❌ `mp-ukagaka/get_bot_blocker_stats`
- **Class name:** `Wp_{Feature}_Ability` (PascalCase with underscores).
- **File name:** `class-{slug}-ability.php` (kebab-case).
- **Note:** When `abilities-integration.php` sends the schema to the LLM, it automatically converts `/` to `__` (to comply with OpenAI's regex limitations).

#### 7. External Plugin Integration Pattern

**Prioritize calling the plugin's public functions**, avoiding direct manipulation of DB or Options:

```php
// ✅ Calling the plugin's own function — Ensures triggering of internal log rotation, Transients, and Action hooks
moelog_bot_blocker_ban_ip($ip);
moelog_bot_blocker_log('MANUAL_BAN', ['source' => 'Frieren API', 'ip' => $ip]);

// ❌ Directly modifying option — Bypasses core plugin logic
$banned = get_option('moelog_bot_blocker_banned_ips', []);
$banned[] = $ip;
update_option('moelog_bot_blocker_banned_ips', $banned);
```

#### 8. Checklist Before Shipping

- [ ] Created the class file in `includes/mcp-tools/abilities/`.
- [ ] Added the class to the `$abilities` array in `manager.php`.
- [ ] All 5 required fields are set (`label`, `description`, `category`, `execute_callback`, `permission_callback`).
- [ ] `input_schema` is defined (even parameterless abilities use `new \stdClass()`).
- [ ] Ability is named `mp-ukagaka/{kebab-case}` and has no underscores.
- [ ] Plugin dependency guard (`function_exists()`) is added.
- [ ] Description semantics are precise — Enum values have "Use this when..." guidance.
- [ ] `/debug_mcp` confirms the tool appears in the list.
- [ ] End-to-end dialogue test passed, and data changed correctly.
