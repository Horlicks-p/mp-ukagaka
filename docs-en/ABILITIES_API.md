# WordPress Abilities API Integration Guide (English)

**MP Ukagaka** leverages the **Abilities API** built into the WordPress 6.9+ core, granting your AI characters (Ukagaka) the ability to interact directly with your WordPress site.

This feature allows AI characters to use registered "Abilities," such as querying site information, managing posts, or executing other WordPress functions, evolving them from simple chatbots into site management assistants.

## What is the Abilities API?

The **Abilities API** is a core feature introduced in WordPress 6.9 (released December 2, 2025). It provides a standardized way to register and discover WordPress functionalities (Abilities).

- **Past (WP < 6.9)**: Depended on the **MCP Adapter** plugin to provide registry functionality.
- **Present (WP >= 6.9)**: The registry is now part of the WordPress core.

Therefore, as long as your WordPress version is 6.9 or higher, **no additional plugins are required** for MP Ukagaka to utilize these functions.

## Why is the MCP Adapter not needed?

Because `mp-ukagaka` calls the core function `wp_register_ability()`, directly registering abilities (e.g., "Get Popular Posts") into the WordPress core registry.

The AI logic reads abilities directly from this core registry and passes them as "Tools" to models like Gemini, Claude, OpenAI, and Ollama. Since the registration and discovery mechanisms are built-in, the MCP Adapter is no longer required as an intermediary.

- **Internal Use (Internal Agent)**: For Ukagaka use → **No MCP Adapter needed** (direct access to Core API).
- **External Use (External Agent)**: For Cursor or Claude Desktop → **May need MCP Adapter** (exposing core abilities via standard MCP protocol).

## Supported Models

The following AI models currently support tool calling in this plugin:

- **Google Gemini**: Gemini 2.0 Flash (Recommended), Gemini 1.5 Pro, etc.
- **Anthropic Claude**: Claude 3.5 Sonnet, etc.
- **OpenAI**: GPT-4o, GPT-4o-mini, etc.
- **Ollama**: Qwen 2.5, Llama 3.1, etc. (Models supporting Tool Calling).

## How to Add Abilities (Developer Guide)

MP Ukagaka automatically detects and uses all Abilities registered to the WordPress core. You can add abilities using two methods:

### Method 1: Standard WordPress Method (For any Plugin/Theme)

This is the official WordPress standard. Any plugin can register abilities this way, and MP Ukagaka will automatically read and use them.

```php
// In your custom plugin or theme's functions.php
add_action( 'wp_abilities_api_init', function() {
    if ( function_exists( 'wp_register_ability' ) ) {
        wp_register_ability(
            'my-plugin/say-hello', // Unique identifier
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

### Method 2: MP Ukagaka Modular Method (Recommended for internal development)

If you are developing the `mp-ukagaka` plugin itself, we recommend using our built-in modular architecture:

1.  Create a new class file in `includes/mcp-tools/abilities/` (e.g., `class-my-ability.php`).
2.  Implement `register` and `execute` methods.
3.  Add the class to the registry in `includes/mcp-tools/manager.php`.
