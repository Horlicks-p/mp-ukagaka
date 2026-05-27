# PHPCS Baseline — Runbook

Adds a PHPCS coding-standard baseline for the plugin PHP (`mp-ukagaka.php`,
`includes/`, `options/`) **without changing runtime code**. The baseline freezes
existing findings so a PR fails only when it introduces *new* findings.

> This is the battle-tested procedure. Two traps below cost real time the first
> time around — read them before running anything.

## Environment (this Windows machine)

- **PHP** is on PATH: `php` → `C:\D\php\php\php.exe` (currently 8.5). The plugin
  targets **PHP 7.4+** (`readme.txt: Requires PHP 7.4`).
- **Composer is NOT on PATH.** A bundled phar lives at the repo root:
  `composer.phar` (gitignored, ~3 MB, Composer 2.x). Run it as `php composer.phar`.
  Running bare `composer` fails — that is the first trap.

## One-time install (PHPCS + WPCS + PHPCompatibility)

Run from the **repo root**. `composer.json` lives in `tools/php`, so target it
with `--working-dir`:

```powershell
php composer.phar require --working-dir=tools/php --no-interaction --dev squizlabs/php_codesniffer:^3.10 wp-coding-standards/wpcs:^3.1 phpcompatibility/phpcompatibility-wp:^2.1
```

Installs into `tools/php/vendor/` (gitignored) and updates
`tools/php/composer.json` + `composer.lock`.

### TRAP: `installed_paths` must be complete

`tools/php/composer.json` sets `allow-plugins: false`, which **blocks**
`dealerdirect/phpcodesniffer-composer-installer` from auto-registering standards.
So `phpcs.xml.dist` registers them **manually** via
`<config name="installed_paths">`, and that list MUST include WPCS 3.x's split-out
dependencies, not just WPCS itself:

- `tools/php/vendor/phpcsstandards/phpcsutils`   ← required by WPCS 3.x
- `tools/php/vendor/phpcsstandards/phpcsextra`   ← required by WPCS 3.x
- `tools/php/vendor/wp-coding-standards/wpcs`
- `tools/php/vendor/phpcompatibility/php-compatibility`
- `tools/php/vendor/phpcompatibility/phpcompatibility-paragonie`
- `tools/php/vendor/phpcompatibility/phpcompatibility-wp`

If `phpcsutils` / `phpcsextra` are missing you get
`ERROR: Referenced sniff "PHPCSUtils" does not exist` plus a flood of
`Universal.*` / `NormalizedArrays.*` errors, and the baseline runner reports
`PHPCS did not return valid JSON`. (The ruleset already lists all six — keep it
that way.)

## Commands

From the **repo root**:

```powershell
php tools/php/phpcs-baseline.php generate   # (re)create tools/php/phpcs-baseline.json
php tools/php/phpcs-baseline.php check      # exit 1 if findings exceed baseline
php tools/php/phpcs-baseline.php summary    # print totals from the baseline
```

Or via npm, from `tools/node`:

```powershell
npm run lint:phpcs:baseline
npm run lint:phpcs
npm run lint:phpcs:summary
```

Debug a single file directly:

```powershell
php tools/php/vendor/bin/phpcs --standard=phpcs.xml.dist includes/core/frontend-functions.php
php tools/php/vendor/bin/phpcbf --standard=phpcs.xml.dist <path>   # auto-fix; large reformat, do not run casually
```

## Baseline policy

- Stored at `tools/php/phpcs-baseline.json` — **committed** so other machines / CI
  share the same baseline.
- Each finding is grouped by `file + sniff source + message` with a count.
  **Line numbers are NOT part of the key**, so nearby edits don't invalidate it.
- `check` fails only when a group's count exceeds the baseline (a new violation).
  It never auto-tightens — regenerate after cleanup to lower the numbers.
- Current baseline: ~48,000 findings / ~3,500 groups, dominated by space-indent
  (WP wants tabs) and function-call paren spacing — mostly cosmetic and
  phpcbf-autofixable, not bugs.

## Wired into `npm run verify`

`lint:phpcs` runs as part of `npm run verify` (right after `lint:php`), so any
commit that introduces new PHPCS findings beyond the baseline fails the standard
verification flow — fail-fast on style regressions.

This means **`npm run verify` now requires PHPCS to be installed.** On a fresh
checkout, run `composer install` (or the `require` command above) in `tools/php`
first; otherwise `lint:phpcs` exits with the install hint and aborts `verify`.
`tools/php/vendor` is gitignored, so this install step is per-machine.
