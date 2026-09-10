# CLAUDE.md — MP Ukagaka Codebase Guide

This file provides AI assistants with the context needed to understand and contribute to the MP Ukagaka WordPress plugin codebase effectively.

---

## Project Overview

**MP Ukagaka** is a WordPress plugin (v2.33.1) that creates interactive "ukagaka" (伺か) desktop-companion–style characters on WordPress sites. Characters display dialogue, respond to page content using AI APIs, and support multi-turn chat with visitors.

- **Language stack**: PHP 7.4+ (backend), JavaScript ES6+ (frontend)
- **WordPress**: Requires 5.0+, tested up to 6.4
- **AI providers**: Gemini, OpenAI, Claude (Anthropic), Ollama (local)
- **Demo**: https://www.moelog.com

---

## Module Loading Order

Module load order in `mp-ukagaka.php` is **critical** and intentional. It is driven by the `$core_modules` / `$frontend_modules` / `$admin_modules` arrays in `mpu_load_modules()`. Dependencies are strict — the array order is the source of truth; read it directly rather than trusting a copy. Frontend-only modules are skipped when `is_admin()`; admin-only modules load only when it is true.

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

Frontend JavaScript is split into focused `ukagaka-*.js` modules that coordinate via the global `window` object; chat mode spans the `ukagaka-chat-*.js` files.

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

The PHPUnit suite runs via `tools/php/vendor/bin/phpunit -c tests/phpunit.xml.dist`.
Beyond it, testing is manual:
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

### Releasing

See [Releasing](docs-en/RELEASING.md). The version markers and the release
notes are both owned by `tools/node/bump-version.js`; `npm --prefix tools/node run verify`
fails until the notes exist.

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

Canonical docs live in `docs-en/` (user guide, developer guide, API reference,
changelog, ghost creation, abilities API, canvas customization).
