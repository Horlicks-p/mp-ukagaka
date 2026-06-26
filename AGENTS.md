# AGENTS.md

Agent guidance for the **MP Ukagaka** WordPress plugin.

**The full project guide is [CLAUDE.md](./CLAUDE.md)** - architecture, module load
order, naming conventions, security rules, i18n, and common pitfalls. Read it
first. This file only adds operational notes that aren't in CLAUDE.md.

## Toolchain quick reference (Windows)

- **PHP**: `php` on PATH (`C:\D\php\php\php.exe`). Plugin targets PHP 7.4+.
- **Composer**: **not on PATH** - use `php composer.phar` from the repo root
  (gitignored phar). Bare `composer` fails.
- **Node tooling** lives in `tools/node`: `npm run build`, `test:logger`,
  `inventory:logs`, `verify`.
- **PHPUnit**: `php tools/php/vendor/bin/phpunit --configuration tests/phpunit.xml.dist`.

## Current architecture reminders

- Treat `CLAUDE.md` and `mp-ukagaka.php` as the architecture/module loading
  source of truth. If notes disagree, verify `mpu_load_modules()` in code.
- `docs-en/` is the canonical documentation tree. Do not re-expand deleted
  `docs/` or `docs-jp/`; keep root `README_zh-TW.md` / `README_ja.md` as
  lightweight landing docs when touched.
- Frontend chat is split across `js/ukagaka-chat-mode.js`,
  `js/ukagaka-chat-history.js`, `js/ukagaka-chat-send.js`,
  `js/ukagaka-chat-sse.js`, `js/ukagaka-chat-format.js`,
  `js/ukagaka-chat-events.js`, and `js/ukagaka-chat-wake.js`; do not treat the
  old monolithic chat file as current source.
- Gift / Feeding is live in v2.27.x: REST route `/touch/give`, catalog
  `ghost/<Character>/items.json`, loader `includes/personality/personality-items.php`,
  and Frieren UI wiring in `ghost/Frieren/frieren-interactions.js`.
- Plan files are often frozen specs. Before treating unchecked boxes as pending
  work, check `plan/Plan_Status_2026-05.md`, the changelog, and actual code.

## PHPCS (coding-standard baseline)

Full runbook: **[tools/php/PHPCS_BASELINE.md](./tools/php/PHPCS_BASELINE.md)**.
The two traps that cost the most time:

1. **Composer not on PATH** -> install with
   `php composer.phar require --working-dir=tools/php ...`.
2. **`phpcs.xml.dist` `installed_paths`** must include
   `phpcsstandards/phpcsutils` **and** `phpcsstandards/phpcsextra` (WPCS 3.x
   deps), because `allow-plugins: false` blocks auto-registration. Missing them
   -> `Referenced sniff "PHPCSUtils" does not exist` and `PHPCS did not return valid JSON`.

Run from the repo root:

```powershell
php tools/php/phpcs-baseline.php generate   # rebuild tools/php/phpcs-baseline.json
php tools/php/phpcs-baseline.php check      # exit 1 on new findings beyond baseline
php tools/php/phpcs-baseline.php summary
```

`lint:phpcs` is wired into `npm run verify` (right after `lint:php`), so new
PHPCS findings beyond the baseline fail verification. This means `npm run verify`
requires PHPCS installed - on a fresh checkout run `composer install` in
`tools/php` first (`tools/php/vendor` is gitignored), or `lint:phpcs` aborts
`verify` with an install hint.

Operational rules:

- Do not hand-edit `tools/php/phpcs-baseline.json`; change code or rules, then run
  `php tools/php/phpcs-baseline.php generate`.
- `check` is count-based by `file + sniff source + message`. It catches new
  findings, but it does not auto-tighten after cleanup; regenerate the baseline
  only when intentionally lowering existing counts.
- Keep PHPCS/baseline commits scoped to tooling files unless the task explicitly
  includes code cleanup.
