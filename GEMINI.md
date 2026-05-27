# GEMINI.md

Agent guidance for the **MP Ukagaka** WordPress plugin.

Canonical agent notes live in **[AGENTS.md](./AGENTS.md)**; the full project guide
is **[CLAUDE.md](./CLAUDE.md)**. Read those first — this file is a pointer plus the
one thing agents keep getting stuck on.

## PHPCS quick note

Full runbook: **[tools/php/PHPCS_BASELINE.md](./tools/php/PHPCS_BASELINE.md)**.
Two traps:

1. **Composer is not on PATH** — use `php composer.phar require --working-dir=tools/php ...`.
2. **`phpcs.xml.dist` `installed_paths`** must include `phpcsstandards/phpcsutils`
   **and** `phpcsstandards/phpcsextra` (WPCS 3.x deps; `allow-plugins: false`
   blocks auto-registration), else `Referenced sniff "PHPCSUtils" does not exist`.

Run from the repo root:

```powershell
php tools/php/phpcs-baseline.php generate   # rebuild tools/php/phpcs-baseline.json
php tools/php/phpcs-baseline.php check      # exit 1 on new findings beyond baseline
php tools/php/phpcs-baseline.php summary
```
