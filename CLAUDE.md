# CLAUDE.md — MP Ukagaka Codebase Guide

This file provides AI assistants with the context needed to understand and contribute to the MP Ukagaka WordPress plugin codebase effectively.

---

## Project Overview

**MP Ukagaka** is a WordPress plugin (v2.29.0) that creates interactive "ukagaka" (伺か) desktop-companion–style characters on WordPress sites. Characters display dialogue, respond to page content using AI APIs, and support multi-turn chat with visitors.

- **Language stack**: PHP 7.4+ (backend), JavaScript ES6+ (frontend)
- **WordPress**: Requires 5.0+, tested up to 6.4
- **AI providers**: Gemini, OpenAI, Claude (Anthropic), Ollama (local)
- **Demo**: https://www.moelog.com

---

## Directory Structure

```
mp-ukagaka/
├── mp-ukagaka.php              # Plugin entry point — defines constants, registers hooks, loads modules
├── css/
│   ├── mpu_style.css           # Frontend styles
│   └── admin-style.css         # Admin panel styles
├── includes/                   # All PHP modules (~70 files)
│   ├── core/                   # Core utility modules
│   │   ├── debug-functions.php         # Logging system (must load first)
│   │   ├── core-functions.php          # Settings/option management
│   │   ├── utility-functions.php       # General helpers
│   │   ├── template-functions.php      # HTML/markup template helpers
│   │   ├── file-functions.php          # Filesystem helpers (WP Filesystem API)
│   │   ├── encryption-functions.php    # AES-256-CBC API key encryption
│   │   ├── wp-info-functions.php       # WordPress/site info helpers
│   │   ├── network-functions.php       # HTTP/network helpers
│   │   ├── runtime-state-functions.php # Per-request runtime state
│   │   ├── class-mpu-input-role.php    # LLM/tool input role resolver
│   │   ├── class-mpu-observation-buffer.php # Session-scoped visitor activity buffer
│   │   ├── class-mpu-log-i18n-builder.php   # Frontend console log i18n payload builder
│   │   ├── ukagaka-functions.php       # Character management
│   │   └── frontend-functions.php      # Frontend rendering (frontend-only)
│   ├── llm/                    # AI/LLM system
│   │   ├── ai-functions.php            # Main API call handler (cloud: Gemini, OpenAI, Claude)
│   │   ├── llm-functions.php           # Local LLM support (Ollama)
│   │   ├── llm-context-builder.php     # Builds AI context from page content
│   │   ├── api-cache.php               # Response caching
│   │   ├── chat-integrity.php          # Checksum validation (prevents frontend tampering)
│   │   ├── class-mpu-chat-lock.php     # Chat lifecycle lock (concurrent LLM request guard)
│   │   ├── request-state.php           # Per-request state management
│   │   ├── class-mpu-session-event.php # Transport-neutral session event envelope
│   │   ├── tool-loop-guard.php         # Prevents infinite LLM tool-call loops
│   │   ├── streaming-helpers.php       # SSE (Server-Sent Events) helpers
│   │   ├── provider-helpers.php        # Shared JSON encoding / tool result formatting
│   │   ├── provider-stream-http.php    # Low-level cURL streaming HTTP client
│   │   ├── response-normalizer.php     # Single contract for emotion-tag / think extraction
│   │   ├── class-mpu-stream-output-parser.php # SSE output state machine (emotion/think blocks)
│   │   ├── prompt-categories.php       # Prompt category/instruction management
│   │   ├── llm-slimstat.php            # Slimstat analytics integration
│   │   ├── weather-functions.php       # Open-Meteo weather API
│   │   ├── diary-functions.php         # Auto-diary feature (Frieren's journal)
│   │   └── providers/                  # OO AI provider implementations
│   │       ├── interface-mpu-ai-provider.php
│   │       ├── class-mpu-ai-provider-base.php
│   │       ├── class-mpu-ai-provider-factory.php
│   │       ├── class-mpu-ai-provider-gemini.php
│   │       ├── class-mpu-ai-provider-openai.php
│   │       ├── class-mpu-ai-provider-claude.php
│   │       └── class-mpu-ai-provider-ollama.php
│   ├── personality/            # Character personality system
│   │   ├── personality-loader.php      # JSON-based personality file loader
│   │   ├── personality-prompts.php     # Dynamic prompt building & variable substitution
│   │   ├── personality-decorations.php # Decoration/accessory system
│   │   ├── personality-items.php       # Gift & food item catalog
│   │   ├── personality-emoji.php       # Emoji configuration
│   │   └── emoji-mapper.php            # Emotion analysis & emoji selection
│   ├── rest/                   # REST API OO controllers (v2.9.2+)
│   │   ├── bootstrap.php               # Route registration entry point
│   │   ├── class-mpu-rest-base.php     # Base controller class
│   │   ├── class-mpu-rest-chat.php     # Chat endpoints
│   │   ├── class-mpu-rest-dialog.php   # Dialogue management endpoints
│   │   ├── class-mpu-rest-ghost.php    # Character/personality endpoints
│   │   ├── class-mpu-rest-touch.php    # Touch interaction endpoints
│   │   ├── class-mpu-rest-memory.php   # Chat memory endpoints
│   │   ├── class-mpu-rest-observation.php # Visitor observation endpoints
│   │   └── class-mpu-rest-test.php     # API test endpoints
│   ├── chat/
│   │   └── class-mpu-chat-history-service.php # Server-side chat history service
│   ├── ajax/
│   │   └── chat-api-handlers.php       # Multi-turn AJAX chat handlers
│   ├── stats/
│   │   ├── stats-collector.php         # Usage statistics collection
│   │   └── stats-analyzer.php          # Statistics analysis
│   ├── mcp-tools/              # Abilities/tool-call implementations
│   │   ├── manager.php                 # Ability registration/dispatch
│   │   └── abilities/                  # Individual ability classes
│   │       ├── class-ai-crawler-ability.php
│   │       ├── class-visitor-pulse-ability.php
│   │       ├── class-wp-bot-blocker-ability.php
│   │       └── class-wp-postviews-ability.php
│   ├── updater/
│   │   └── github-updater.php          # GitHub auto-update (Plugin Update Checker, admin-only)
│   ├── integrations/           # Third-party integrations
│   │   ├── akismet-integration.php
│   │   ├── turnstile-integration.php
│   │   ├── abilities-integration.php   # Abilities API (formerly MCP tools)
│   │   └── bot-blocker-integration.php # Bot protection (from Moelog Bot Blocker)
│   └── admin-functions.php     # Admin-only functions (loaded only in is_admin())
├── js/                         # Frontend JavaScript modules
│   ├── ukagaka-base.js         # Base config & utilities
│   ├── ukagaka-core.js         # Core character logic
│   ├── ukagaka-context.js      # Page awareness/context
│   ├── ukagaka-features.js     # Settings & feature flags
│   ├── ukagaka-anime.js        # Canvas animation
│   ├── ukagaka-dialog.js       # Dialogue file loading
│   ├── ukagaka-greeting.js     # First-visit greeting
│   ├── ukagaka-emoji.js        # Emoji rendering
│   ├── ukagaka-textarearesizer.js      # Chat input auto-resize
│   ├── ukagaka-chat-mode.js    # Chat mode lifecycle/UI
│   ├── ukagaka-chat-send.js    # Message send flow
│   ├── ukagaka-chat-sse.js     # SSE stream consumption
│   ├── ukagaka-chat-events.js  # Chat event wiring
│   ├── ukagaka-chat-history.js # Chat history management
│   ├── ukagaka-chat-format.js  # Message formatting/rendering
│   ├── ukagaka-chat-wake.js    # Wake/idle handling
│   └── dist/                   # Production bundles
│       ├── ukagaka-bundle.js
│       ├── ukagaka-bundle.min.js
│       └── ukagaka-textarearesizer.min.js
├── ghost/                      # Character personality assets
│   ├── Frieren/                # Default character (from anime "Frieren")
│   │   ├── manifest.json       # Character metadata
│   │   ├── personality.md      # Core personality description
│   │   ├── instructions.md     # Behavioral instructions
│   │   ├── shell/              # Character PNG images
│   │   ├── decorations/        # Decoration images
│   │   └── emojis/             # Character emoji images
│   ├── Asuna/                  # Additional bundled character
│   └── Sakura_Laurel/          # Additional bundled character
├── dialogs/                    # Runtime dialogue files (TXT/JSON)
├── options/                    # Admin settings page PHP files (11 files)
├── languages/                  # i18n files (.po, .pot, .mo)
│   └── compile_po.py           # Script to compile .po → .mo files
├── docs-en/                    # Canonical documentation (English, single source of truth)
├── plan/                       # Development planning documents
└── example/                    # Example configuration files
```

---

## Module Loading Order

Module load order in `mp-ukagaka.php` is **critical** and intentional. It is driven by the `$core_modules` / `$frontend_modules` / `$admin_modules` arrays in `mpu_load_modules()`. Dependencies are strict — the array order is the source of truth; when in doubt, read it directly. The current sequence:

1. `core/debug-functions.php` — must be first (logging used by all others)
2. `core/core-functions.php` → `core/utility-functions.php` → `core/template-functions.php` → `core/file-functions.php` → `core/encryption-functions.php` → `core/wp-info-functions.php` → `core/network-functions.php` → `core/runtime-state-functions.php`
3. `core/class-mpu-input-role.php` → `core/class-mpu-observation-buffer.php` → `core/class-mpu-log-i18n-builder.php`
4. `personality/personality-loader.php` → `personality-prompts.php` → `personality-decorations.php` → `personality-items.php` → `personality-emoji.php`
5. `stats/stats-collector.php` → `stats/stats-analyzer.php` (before `ai-functions.php`)
6. `llm/api-cache.php` → `provider-helpers.php` → `chat-integrity.php` → `class-mpu-chat-lock.php` → `request-state.php` → `class-mpu-session-event.php` → `tool-loop-guard.php` → `streaming-helpers.php` → `provider-stream-http.php`
7. `llm/providers/bootstrap.php` (before `ai-functions.php`)
8. `llm/ai-functions.php` → `response-normalizer.php` → `class-mpu-stream-output-parser.php` → `prompt-categories.php` → `llm-slimstat.php` → `llm-context-builder.php`
9. `llm/weather-functions.php` → `diary-functions.php` → `llm-functions.php`
10. `personality/emoji-mapper.php` → `core/ukagaka-functions.php` → `rest/bootstrap.php` → `ajax/chat-api-handlers.php`
11. Integration modules last (`akismet` → `turnstile` → `abilities` → `bot-blocker`)

Frontend-only: `core/frontend-functions.php` (skipped when `is_admin()`)
Admin-only: `admin-functions.php` → `updater/github-updater.php` (only loaded when `is_admin()`)

---

## Naming Conventions

### PHP

| Pattern | Example | Usage |
|---|---|---|
| `mpu_*` prefix | `mpu_get_option()` | All public functions |
| `MPU_*` prefix | `MPU_VERSION` | All constants |
| `MPU_*` class names | `MPU_AI_Provider_Factory` | All classes |
| `MPU_REST_*` | `MPU_REST_Chat` | REST controller classes |
| `MPU_AI_Provider_*` | `MPU_AI_Provider_Gemini` | AI provider classes |

### JavaScript

| Pattern | Example | Usage |
|---|---|---|
| `mpu*` camelCase | `mpuChatHistory`, `mpuFetchSSE` | Global state and functions |
| `window.mpu*` | `window.mpuChatModeActive` | Cross-module shared state |

### Options / Settings

All plugin settings are stored in a single WordPress option key as a serialized array. Access via:
```php
$mpu_opt = mpu_get_option();      // Read (cached)
mpu_save_option($mpu_opt);        // Write
```
The global `$mpu_opt` is also available for legacy compatibility.

---

## Key Architectural Patterns

### AI Provider Factory Pattern (v2.10.0+)

All AI provider routing goes through `MPU_AI_Provider_Factory`:
```php
$provider = MPU_AI_Provider_Factory::create($provider_slug);
$response = $provider->generate($prompt, $options);
```
Provider implementations are in `includes/llm/providers/`. To add a new provider, implement `MPU_AI_Provider_Interface` and register in the factory.

### REST API OO Controllers (v2.9.2+)

All REST routes use an OO architecture:
- Base class: `MPU_REST_Base` (handles auth, rate limiting, nonce refresh)
- Route registration: `includes/rest/bootstrap.php`
- Route namespace: `mp-ukagaka/v1`

Do **not** add new REST endpoints as procedural functions — extend `MPU_REST_Base` instead.

### SSE Streaming

The `/chat/user-stream` endpoint uses Server-Sent Events. Key files:
- `streaming-helpers.php` — SSE event sending helpers
- `provider-stream-http.php` — cURL-based streaming HTTP client

SSE event types: `delta`, `status`, `nonce`, `done`, `error`

### Chat Integrity (Checksum System)

`chat-integrity.php` validates chat history between frontend/backend using checksums. Currently operates in **observational (audit) mode** — mismatches are logged to `logs/checksum-mismatch.log` but do not block requests.

### Tool Call Loop Guard

`tool-loop-guard.php` detects and halts infinite LLM tool-call cycles by comparing argument JSON hashes. Constant `MPU_MAX_TOOL_TURNS` limits maximum consecutive tool calls.

---

## Security Conventions

Always follow these patterns:

1. **API Key Storage**: Encrypted with AES-256-CBC. Never store or log plaintext API keys.
2. **Nonce Verification**: All form submissions and AJAX calls verify WordPress nonces.
3. **Sanitization**: Use WordPress core functions (`sanitize_text_field`, `wp_kses`, etc.) at input boundaries.
4. **Escaping**: Use `esc_html`, `esc_attr`, `esc_url` at output boundaries.
5. **File Operations**: Use WordPress Filesystem API, not direct `file_put_contents`. Always validate paths.
6. **No ABSPATH Check Skip**: Every PHP file must start with `if (!defined('ABSPATH')) { exit(); }`.

---

## Personality / Character System

Characters (called "ghosts") are defined by files in `ghost/<CharacterName>/`:

| File | Purpose |
|---|---|
| `manifest.json` | Character metadata (name, version, author) |
| `personality.md` | Core personality traits (loaded into system prompt) |
| `instructions.md` | Behavioral rules and response guidelines |
| `shell/*.png` | Character images |
| `emojis/*.png` | Character-specific emoji images |
| `*.json` | Additional configs (dynamics, weights, prompts) |

Variable substitution in personality files: `{{admin_nickname}}`, `{{admin_name}}`, `{{site_name}}`, etc. are replaced at runtime by `personality-prompts.php`.

---

## Internationalization (i18n)

- Text domain: `mp-ukagaka`
- Translation files in: `languages/`
- To add a translation string in PHP: `__('string', 'mp-ukagaka')` or `_e('string', 'mp-ukagaka')`
- Compile `.po` → `.mo`: `python3 languages/compile_po.py`
- Supported languages: English, Traditional Chinese (zh-TW), Japanese (ja)

---

## JavaScript Frontend Architecture

Frontend JavaScript is split into focused modules that coordinate via the global `window` object:

- **ukagaka-base.js**: Configuration, constants, utility functions
- **ukagaka-core.js**: Character rendering, dialogue display, auto-talk scheduling
- **ukagaka-context.js**: Page content extraction for AI context building
- **ukagaka-features.js**: Feature flags and settings integration
- **ukagaka-anime.js**: Canvas-based animation

Chat mode is split across several `ukagaka-chat-*.js` modules (formerly a single `ukagaka-chat.js`):

- **ukagaka-chat-mode.js**: Chat mode lifecycle and UI
- **ukagaka-chat-send.js**: Message send flow
- **ukagaka-chat-sse.js**: SSE stream consumption
- **ukagaka-chat-events.js**: Event wiring
- **ukagaka-chat-history.js**: Conversation history management
- **ukagaka-chat-format.js**: Message formatting/rendering
- **ukagaka-chat-wake.js**: Wake/idle handling

Key shared state lives on `window`:
- `window.mpuChatHistory` — multi-turn conversation history array (max 40 entries)
- `window.mpuChatModeActive` — boolean chat mode flag

Production bundles in `js/dist/` are pre-compiled. If a `build.js` script exists (not tracked in git), use it to rebuild bundles after JS changes.

---

## Development Workflow

### Making Changes

1. **PHP modules**: Edit files in `includes/`. Respect module load order — if a new file depends on another, ensure it loads after in `mpu_load_modules()` in `mp-ukagaka.php`.
2. **New AI provider**: Implement `MPU_AI_Provider_Interface`, extend `MPU_AI_Provider_Base`, register in `MPU_AI_Provider_Factory`.
3. **New REST endpoint**: Create or extend a controller in `includes/rest/`, register in `includes/rest/bootstrap.php`.
4. **Admin settings**: Add settings pages in `options/`, register via `admin-functions.php`.

### Testing

There is no automated test suite. Testing is manual:
- Use the built-in test endpoint (`class-mpu-rest-test.php`) for API connectivity tests
- Enable WordPress debug logging (`WP_DEBUG_LOG`) — plugin errors log via `mpu_log()`
- Check `logs/checksum-mismatch.log` for chat integrity issues
- Use the Settings → MP Ukagaka → Test Connection buttons

### Translation Updates

After adding new translatable strings:
```bash
# Regenerate .pot file (use WP-CLI or equivalent tool)
# Then compile .po files
python3 languages/compile_po.py
```

---

## Version History Highlights

| Version | Key Change |
|---|---|
| 2.27.x | Gift / Feeding System (`personality-items.php`); boot-time visual-ready latch; gift interaction hardening |
| 2.26.0 | Daytime Nap (after-lunch sleep) behavior |
| 2.25.x | Frontend chat split into `ukagaka-chat-*.js`; emotion-tag response pipeline & backend normalizer; S/A-tier security hardening |
| 2.24.0 | Frontend console log i18n (`class-mpu-log-i18n-builder.php`) |
| 2.13.0 | Dead code cleanup; removed deprecated functions and files |
| 2.12.5 | Unified History Memory; SSE stability hardening; frontend global state migration |
| 2.12.x | SSE streaming & typewriter effect; `/chat/user-stream` endpoint |
| 2.10.0 | AI Provider Factory Pattern; tool call loop detection |
| 2.9.2  | REST OO routing architecture; `MPU_REST_Base` class |

---

## Common Pitfalls

- **Extend existing functions before adding new ones** — Before adding any new features based on instructions, always check if it's possible to extend existing functions instead of creating new ones. Maintain a unified code format. This is especially critical for the **abilities** section to avoid future maintenance difficulties.
- **Don't use procedural REST handlers** — all REST routes must go through the OO controller system (`MPU_REST_Base`).
- **Don't bypass the factory** — always use `MPU_AI_Provider_Factory::create()` for AI calls, not direct provider instantiation.
- **Mind the load order** — adding a `require_once` out of order in `mpu_load_modules()` will cause fatal errors.
- **Don't log API keys** — even in debug mode, never log raw API keys or tokens.
- **Sanitize at boundaries** — sanitize all user input when saving; escape all output when rendering.
- **Chat history limit** — the frontend stores up to 40 entries in `window.mpuChatHistory`. Backend context building truncates independently.

---

## Key Documentation References

- [User Guide](docs-en/USER_GUIDE.md) — Installation, configuration, FAQ
- [Developer Guide](docs-en/DEVELOPER_GUIDE.md) — Architecture deep-dive, hooks, extension patterns
- [API Reference](docs-en/API_REFERENCE.md) — PHP functions, WordPress hooks, REST endpoints, JS API
- [Changelog](docs-en/CHANGELOG.md) — Full version history
- [Ghost Create Guide](docs-en/GHOST_CREATE_GUIDE.md) — Creating custom characters
- [Abilities API](docs-en/ABILITIES_API.md) — Tool-calling/abilities extension system
- [Canvas Customization](docs-en/CANVAS_CUSTOMIZATION.md) — Animation configuration
