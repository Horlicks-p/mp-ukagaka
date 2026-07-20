/**
 * Version marker synchronizer.
 *
 * One command to keep every human-maintained version reference in sync with
 * the plugin version. The canonical version lives in mp-ukagaka.php's
 * MPU_VERSION define; every other marker is derived from it.
 *
 * Usage:
 *   node tools/node/bump-version.js <version> [YYYY-MM-DD]
 *       Set all markers to <version> (semver). The optional date sets the
 *       "Last Updated" field in docs-en/README.md; defaults to today.
 *
 *   node tools/node/bump-version.js --check
 *       Verify every marker already equals MPU_VERSION. Exits non-zero on any
 *       drift (suitable for the verify pipeline). Does not modify files.
 *
 * Note: this deliberately does NOT touch the CHANGELOG prose in
 * docs-en/CHANGELOG.md or the readme.txt changelog section — those entries
 * need human-written release notes.
 */

const fs = require("fs");
const path = require("path");

const repoRoot = path.resolve(__dirname, "..", "..");

const SEMVER = /^\d+\.\d+\.\d+$/;
const VER = "\\d+\\.\\d+\\.\\d+";

// Each target names one marker: the file it lives in, a regex that captures the
// version with the surrounding context (so we only ever touch the intended
// spot), and a replacement that re-inserts the new version. `date: true` marks
// the docs-en/README.md "Last Updated" line, which carries a date, not a
// version, and is excluded from --check.
const targets = [
  {
    file: "mp-ukagaka.php",
    label: "plugin header Version",
    find: new RegExp(`(^\\s*Version:\\s*)${VER}`, "m"),
    replace: (v) => `$1${v}`,
  },
  {
    file: "mp-ukagaka.php",
    label: "MPU_VERSION define (canonical)",
    find: new RegExp(`(define\\("MPU_VERSION",\\s*')${VER}('\\))`),
    replace: (v) => `$1${v}$2`,
    canonical: true,
  },
  {
    file: "readme.txt",
    label: "readme Stable tag",
    find: new RegExp(`(^Stable tag:\\s*)${VER}`, "m"),
    replace: (v) => `$1${v}`,
  },
  {
    file: "README.md",
    label: "README version badge",
    find: new RegExp(`(version-)${VER}(-blue\\.svg)`),
    replace: (v) => `$1${v}$2`,
  },
  {
    file: "docs-en/README.md",
    label: "docs README Current Version",
    find: new RegExp(`(\\*\\*Current Version\\*\\*:\\s*)${VER}`),
    replace: (v) => `$1${v}`,
  },
  {
    file: "docs-en/README.md",
    label: "docs README Last Updated",
    find: /(\*\*Last Updated\*\*:\s*)\d{4}-\d{2}-\d{2}/,
    replace: (d) => `$1${d}`,
    date: true,
  },
  {
    file: "CLAUDE.md",
    label: "CLAUDE.md plugin version",
    find: new RegExp(`(WordPress plugin \\(v)${VER}(\\))`),
    replace: (v) => `$1${v}$2`,
  },
  {
    file: "docs-en/API_REFERENCE.md",
    label: "API reference version",
    find: new RegExp(`(Reference \\(v)${VER}(\\))`),
    replace: (v) => `$1${v}$2`,
  },
];

function readCanonicalVersion() {
  const target = targets.find((t) => t.canonical);
  const content = fs.readFileSync(path.join(repoRoot, target.file), "utf8");
  const match = content.match(new RegExp(`define\\("MPU_VERSION",\\s*'(${VER})'\\)`));
  if (!match) {
    fail(`Could not read MPU_VERSION from ${target.file}`);
  }
  return match[1];
}

function fail(message) {
  console.error(`✗ ${message}`);
  process.exit(1);
}

function today() {
  return new Date().toISOString().slice(0, 10);
}

function runSet(version, date) {
  if (!SEMVER.test(version)) {
    fail(`Invalid version "${version}" — expected semver like 2.28.0`);
  }
  if (!/^\d{4}-\d{2}-\d{2}$/.test(date)) {
    fail(`Invalid date "${date}" — expected YYYY-MM-DD`);
  }

  const cache = new Map();
  const summary = [];

  for (const target of targets) {
    const abs = path.join(repoRoot, target.file);
    const before = cache.has(abs) ? cache.get(abs) : fs.readFileSync(abs, "utf8");
    if (!target.find.test(before)) {
      fail(`Marker not found: ${target.label} in ${target.file} (pattern drifted?)`);
    }
    const value = target.date ? date : version;
    const after = before.replace(target.find, target.replace(value));
    cache.set(abs, after);
    summary.push({
      file: target.file,
      label: target.label,
      changed: after !== before,
      value,
    });
  }

  for (const [abs, content] of cache) {
    fs.writeFileSync(abs, content);
  }

  console.log(`Set version to ${version} (date ${date}):`);
  for (const row of summary) {
    const mark = row.changed ? "updated" : "already current";
    console.log(`  ${row.changed ? "✓" : "·"} ${row.file} — ${row.label} → ${row.value} (${mark})`);
  }
}

function runCheck() {
  const version = readCanonicalVersion();
  const problems = [];

  for (const target of targets) {
    if (target.date) {
      continue; // date is not a version; nothing to reconcile
    }
    const content = fs.readFileSync(path.join(repoRoot, target.file), "utf8");
    const match = content.match(target.find);
    if (!match) {
      problems.push(`${target.file}: marker missing (${target.label})`);
      continue;
    }
    const found = match[0].match(new RegExp(VER))[0];
    if (found !== version) {
      problems.push(`${target.file}: ${target.label} is ${found}, expected ${version}`);
    }
  }

  if (problems.length > 0) {
    console.error(`✗ Version markers out of sync with MPU_VERSION (${version}):`);
    for (const p of problems) {
      console.error(`  - ${p}`);
    }
    process.exit(1);
  }

  console.log(`✓ All version markers match MPU_VERSION (${version}).`);
}

function main() {
  const args = process.argv.slice(2);

  if (args.length === 0 || args[0] === "--check") {
    runCheck();
    return;
  }

  if (args[0] === "--help" || args[0] === "-h") {
    console.log("Usage: node tools/node/bump-version.js <version> [YYYY-MM-DD]");
    console.log("       node tools/node/bump-version.js --check");
    return;
  }

  runSet(args[0], args[1] || today());
}

main();
