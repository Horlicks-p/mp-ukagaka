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
 *       Verify every marker already equals MPU_VERSION, and that a
 *       human-written release note exists for that version in each of the
 *       three prose locations. Exits non-zero on any drift (suitable for the
 *       verify pipeline). Does not modify files.
 *
 * Two kinds of rule:
 *
 *   targets      — version strings. Rewritten by <version> mode, compared by
 *                  --check.
 *   releaseNotes — human-written prose. NEVER rewritten; --check only asserts
 *                  that an entry for the current version exists.
 *
 * Release notes are deliberately check-only. Auto-inserting the version into a
 * "What's New" heading would leave the heading claiming a release the prose
 * below it does not describe, and --check would still pass — worse than the
 * omission it was meant to catch. Failing until a human writes the entry is
 * the point.
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

// Release-note rules. Each asserts that the human-written entry for the current
// version exists. `present` builds the regex from the version; `hint` tells the
// author what to write when it is missing.
const releaseNotes = [
  {
    file: "README.md",
    label: "What's New heading",
    present: (v) => new RegExp(`^##\\s.*What.s New in v${esc(v)}\\s*$`, "m"),
    hint: 'retitle the "What\'s New" section to the new version',
  },
  {
    file: "README.md",
    label: "What's New entry",
    present: (v) => new RegExp(`\\(v${esc(v)}\\)`),
    hint: 'add a "**Title** (vX.Y.Z): …" paragraph at the top of that section',
  },
  {
    file: "readme.txt",
    label: "changelog entry",
    present: (v) => new RegExp(`^\\* v${esc(v)}\\s*$`, "m"),
    hint: 'add a "= YYYY-MM-DD =" block with "* vX.Y.Z" under == Changelog ==',
  },
  {
    file: "docs-en/CHANGELOG.md",
    label: "changelog entry",
    present: (v) => new RegExp(`^##\\s*\\[${esc(v)}\\]`, "m"),
    hint: 'add a "## [X.Y.Z] - YYYY-MM-DD" section at the top',
  },
];

function esc(version) {
  return version.replace(/\./g, "\\.");
}

// Returns the release notes that have no entry for `version`.
function missingReleaseNotes(version) {
  return releaseNotes.filter((note) => {
    const content = fs.readFileSync(path.join(repoRoot, note.file), "utf8");
    return !note.present(version).test(content);
  });
}

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

  // Bumping the numbers is half a release. Name what is still owed, in the
  // order it is usually written, so `verify` does not have to be the reminder.
  const missing = missingReleaseNotes(version);
  if (missing.length > 0) {
    console.log(`\nStill to write by hand for ${version} (\`verify\` fails until then):`);
    for (const note of missing) {
      console.log(`  ✗ ${note.file} — ${note.label}: ${note.hint}`);
    }
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

  const missing = missingReleaseNotes(version);
  if (missing.length > 0) {
    console.error(`✗ No release note written for MPU_VERSION (${version}):`);
    for (const note of missing) {
      console.error(`  - ${note.file}: ${note.label} missing — ${note.hint}`);
    }
    process.exit(1);
  }

  console.log(`✓ All version markers match MPU_VERSION (${version}), release notes present.`);
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
