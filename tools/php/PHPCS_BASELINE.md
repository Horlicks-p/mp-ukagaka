# PHPCS Baseline

This tooling adds a PHPCS baseline without changing runtime plugin code.

## Install PHPCS tooling

Run from `tools/php` in an environment with Composer:

```powershell
composer require --dev squizlabs/php_codesniffer:^3.10 wp-coding-standards/wpcs:^3.1 phpcompatibility/phpcompatibility-wp:^2.1
```

The repo-level `phpcs.xml.dist` sets `installed_paths` for WordPressCS and PHPCompatibilityWP.

## Commands

Run from `tools/node`:

```powershell
npm run lint:phpcs:baseline
npm run lint:phpcs
npm run lint:phpcs:summary
```

Or run directly from the repo root:

```powershell
php tools/php/phpcs-baseline.php generate
php tools/php/phpcs-baseline.php check
php tools/php/phpcs-baseline.php summary
```

## Policy

The baseline is stored at `tools/php/phpcs-baseline.json`.

The check groups existing findings by `file + sniff source + message` and stores a count. Line numbers are intentionally not part of the key, so nearby edits do not invalidate the baseline. A PR fails only when a finding count exceeds the baseline.

Do not add `lint:phpcs` to `npm run verify` until the baseline has been generated and reviewed.
