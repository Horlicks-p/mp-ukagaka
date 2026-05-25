const fs = require("fs");
const path = require("path");

const repoRoot = path.resolve(__dirname, "..", "..");
const outputPath = path.join(
  repoRoot,
  "plan",
  "translation-tables",
  "console-logs-zh-to-ja.md"
);

const roots = [path.join(repoRoot, "js"), path.join(repoRoot, "ghost", "Frieren")];
const sourceFiles = [];

function walk(dir) {
  for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
    const fullPath = path.join(dir, entry.name);
    if (entry.isDirectory()) {
      if (fullPath.includes(`${path.sep}js${path.sep}dist`)) {
        continue;
      }
      walk(fullPath);
      continue;
    }
    if (entry.isFile() && entry.name.endsWith(".js")) {
      sourceFiles.push(fullPath);
    }
  }
}

for (const root of roots) {
  walk(root);
}

sourceFiles.sort();

const callPattern = /\b(?:(console)\.(log|warn|error|info)|(mpuLogger)\.(log|warn|error|info))\s*\(/g;
const cjkPattern = /[一-鿿]/;
const kanaPattern = /[ぁ-んァ-ン]/;
const stringPattern = /(['"`])((?:\\.|(?!\1)[\s\S])*?)\1/g;
const prefixPattern = /^\s*(?:\[MPU?\]|\[MP Ukagaka(?: ERROR)?\])\s*/;

const overrides = {
  "ghost/Frieren/frieren.js:52:console.error": {
    key: "frierenShellInfoInvalid",
    jaSource: "フリーレンモード：shellInfo が無効です",
    translatorComment: "console log. shellInfo はキャラクター画像と表示設定を含む初期化データ。",
  },
  "ghost/Frieren/frieren.js:86:console.error": {
    key: "frierenImageCanvasManagerMissing",
    jaSource: "Canvas マネージャーが初期化されていません",
    translatorComment: "console log. フリーレン画像の読み込み前に Canvas 管理オブジェクトが見つからない状態。",
  },
  "ghost/Frieren/frieren.js:114:console.error": {
    key: "frierenImageLoadFailed",
    jaSource: "フリーレン画像の読み込みに失敗しました：%s",
    translatorComment: "console log. %s は読み込みに失敗した画像 URL。",
  },
  "ghost/Frieren/frieren.js:298:console.error": {
    key: "frierenDrawCanvasManagerMissing",
    jaSource: "Canvas マネージャーが初期化されていません",
    translatorComment: "console log. フリーレン描画処理の前提となる Canvas 管理オブジェクトが見つからない状態。",
  },
  "ghost/Frieren/frieren.js:493:console.warn": {
    key: "frierenDecorationConfigLoadFailed",
    jaSource: "装飾設定を読み込めませんでした：%s",
    translatorComment: "console log. %s はサーバーから返されたエラー、または「未知のエラー」に相当する値。",
  },
  "ghost/Frieren/frieren.js:497:console.error": {
    key: "frierenDecorationConfigAjaxFailed",
    jaSource: "AJAX による装飾設定の読み込みに失敗しました：%s",
    translatorComment: "console log. %s は jQuery AJAX の error 値。",
  },
  "ghost/Frieren/frieren.js:501:console.warn": {
    key: "frierenDecorationConfigRuntimeUnavailable",
    jaSource: "jQuery または mpuurl が利用できないため、装飾設定を読み込めません",
    translatorComment: "console log. 装飾設定を取得するための front-end runtime 依存が不足している状態。",
  },
  "ghost/Frieren/frieren.js:512:console.warn": {
    key: "frierenDecorationConfigInvalid",
    jaSource: "装飾設定が無効です",
    translatorComment: "console log. decoration_config の構造が期待形式ではない状態。",
  },
  "ghost/Frieren/frieren.js:728:console.error": {
    key: "frierenPixelCanvasCreateFailed",
    jaSource: "ピクセル判定用 Canvas を作成できません：%s",
    translatorComment: "console log. %s は装飾物タイプ。",
  },
  "ghost/Frieren/frieren.js:799:console.warn": {
    key: "frierenPixelDataUnavailable",
    jaSource: "ピクセルデータを取得できません（クロスオリジンの可能性があります）：%1$s / %2$s",
    translatorComment: "console log. %1$s は装飾物タイプ、%2$s は例外メッセージ。",
  },
  "ghost/Frieren/frieren.js:1505:console.error": {
    key: "frierenTouchZoneDialogRequestFailed",
    jaSource: "タッチ領域の会話リクエストに失敗しました：%s",
    translatorComment: "console log. %s は request failure の error object/message。",
  },
  "js/ukagaka-anime.js:65:console.error": {
    key: "animeCanvasElementMissing",
    jaSource: "Canvas 要素が存在しません",
    translatorComment: "console log. キャラクター描画用 Canvas DOM 要素が見つからない状態。",
  },
  "js/ukagaka-anime.js:72:console.error": {
    key: "animeCanvasContextUnavailable",
    jaSource: "Canvas コンテキストを取得できません",
    translatorComment: "console log. CanvasRenderingContext2D の取得に失敗した状態。",
  },
  "js/ukagaka-anime.js:89:console.warn": {
    key: "animeFrierenManagerMissing",
    jaSource: "フリーレンマネージャーが読み込まれていないため、汎用モードを使用します",
    translatorComment: "console log. Frieren 専用 manager がないため generic mode にフォールバックする状態。",
  },
  "js/ukagaka-anime.js:160:console.error": {
    key: "animeImageLoadFailed",
    jaSource: "画像の読み込みに失敗しました：%s",
    translatorComment: "console log. %s は読み込みに失敗した画像 URL。",
  },
  "js/ukagaka-anime.js:221:console.error": {
    key: "animeFrameImageLoadFailed",
    jaSource: "フレーム画像の読み込みに失敗しました：%s",
    translatorComment: "console log. %s は読み込みに失敗したフレーム画像 URL。",
  },
  "js/ukagaka-base.js:99:console.log": {
    key: "pageReloadClearedChatSession",
    jaSource: "ページの再読み込みを検出したため、会話履歴とセッション ID をクリアしました",
    translatorComment: "console log. direct console.log のため migration 前に debug-only かどうかを判定すること。",
  },
  "js/ukagaka-features.js:7:mpuLogger.error": {
    key: "jqueryCookieInitFailed",
    jaSource: "jQuery.cookie を初期化できません。一部の機能が正常に動作しない可能性があります",
    translatorComment: "console log. jQuery.cookie 初期化失敗により cookie 依存機能が使えない可能性がある状態。",
  },
  "ghost/Frieren/frieren.js:1196:mpuLogger.error": {
    key: "frierenDecorationDialogRequestFailed",
  },
};

function lineForIndex(text, index) {
  return text.slice(0, index).split(/\r?\n/).length;
}

function findCallEnd(text, openParenIndex) {
  let depth = 0;
  let quote = null;
  let escaped = false;
  let templateExpressionDepth = 0;

  for (let i = openParenIndex; i < text.length; i += 1) {
    const ch = text[i];
    const next = text[i + 1];

    if (quote) {
      if (escaped) {
        escaped = false;
        continue;
      }
      if (ch === "\\") {
        escaped = true;
        continue;
      }
      if (quote === "`" && ch === "$" && next === "{") {
        templateExpressionDepth += 1;
        i += 1;
        continue;
      }
      if (quote === "`" && templateExpressionDepth > 0) {
        if (ch === "{") {
          templateExpressionDepth += 1;
        } else if (ch === "}") {
          templateExpressionDepth -= 1;
        }
        continue;
      }
      if (ch === quote) {
        quote = null;
      }
      continue;
    }

    if (ch === "'" || ch === '"' || ch === "`") {
      quote = ch;
      continue;
    }
    if (ch === "(") {
      depth += 1;
      continue;
    }
    if (ch === ")") {
      depth -= 1;
      if (depth === 0) {
        return i + 1;
      }
    }
  }

  return -1;
}

function extractStrings(callText) {
  const strings = [];
  let match;
  while ((match = stringPattern.exec(callText)) !== null) {
    const raw = match[2]
      .replace(/\\u\{([0-9a-fA-F]+)\}/g, (_, hex) => String.fromCodePoint(parseInt(hex, 16)))
      .replace(/\\u([0-9a-fA-F]{4})/g, (_, hex) => String.fromCharCode(parseInt(hex, 16)))
      .replace(/\\n/g, " ")
      .replace(/\s+/g, " ")
      .trim();
    if (cjkPattern.test(raw)) {
      strings.push(raw.replace(prefixPattern, ""));
    }
  }
  return strings;
}

function rel(file) {
  return path.relative(repoRoot, file).replace(/\\/g, "/");
}

function filePrefix(relativePath) {
  const normalizedPath = relativePath.startsWith("ghost/Frieren/")
    ? relativePath.replace(/^ghost\/Frieren\//, "")
    : relativePath.replace(/^js\//, "");

  const base = normalizedPath
    .replace(/\.js$/, "")
    .split("/")
    .map((part) =>
      part
        .split(/[-_]/)
        .map((piece) => piece.charAt(0).toUpperCase() + piece.slice(1))
        .join("")
    )
    .join("");

  const normalized = base.charAt(0).toLowerCase() + base.slice(1);
  const prefixed = relativePath.startsWith("ghost/Frieren/") && !normalized.startsWith("frieren")
    ? `frieren${normalized.charAt(0).toUpperCase()}${normalized.slice(1)}`
    : normalized;
  return relativePath.startsWith("js/") && !prefixed.startsWith("ukagaka")
    ? `ukagaka${prefixed.charAt(0).toUpperCase()}${prefixed.slice(1)}`
    : prefixed;
}

function draftKey(relativePath, channel, line) {
  const prefix = filePrefix(relativePath);
  return `${prefix}${channel.charAt(0).toUpperCase()}${channel.slice(1)}${line}`;
}

function bucketFor(source, channel) {
  if (source === "console") {
    if (channel === "error" || channel === "warn") {
      return "logs";
    }
    return "TODO";
  }
  if (channel === "error") {
    return "logs";
  }
  return "logsDebug";
}

function placeholderNotes(callText, strings) {
  const hasTemplate = /`[\s\S]*\$\{[\s\S]*`/.test(callText);
  const hasPercent = /%(\d+\$)?[sd]/.test(strings.join(" "));
  const hasExtraArgs = /,\s*[^,\s)]/.test(callText.replace(stringPattern, '""'));
  const notes = [];
  if (hasPercent) {
    notes.push("contains % placeholder");
  }
  if (hasTemplate) {
    notes.push("template literal; convert to *F");
  }
  if (hasExtraArgs) {
    notes.push("dynamic args present");
  }
  return notes.length > 0 ? notes.join("; ") : "none";
}

function callPreview(callText) {
  return callText.replace(/\s+/g, " ").trim().slice(0, 140);
}

const rows = [];
const excluded = [];

for (const file of sourceFiles) {
  const text = fs.readFileSync(file, "utf8");
  let match;
  while ((match = callPattern.exec(text)) !== null) {
    const source = match[1] ? "console" : "mpuLogger";
    const channel = match[2] || match[4];
    const openParen = text.indexOf("(", match.index);
    const end = findCallEnd(text, openParen);
    if (end === -1) {
      continue;
    }

    const callText = text.slice(match.index, end);
    if (!cjkPattern.test(callText)) {
      continue;
    }

    const strings = extractStrings(callText);
    if (strings.length === 0) {
      continue;
    }

    const relativePath = rel(file);
    const line = lineForIndex(text, match.index);
    const zhOriginal = strings.join(" / ");
    const isJapaneseSource = kanaPattern.test(zhOriginal) && !/[一-鿿].*[：，、。！]/.test(zhOriginal);

    const override = overrides[`${relativePath}:${line}:${source}.${channel}`] || {};
    const row = {
      sourceFile: relativePath,
      line,
      channel: `${source}.${channel}`,
      bucket: override.bucket || bucketFor(source, channel),
      key: override.key || draftKey(relativePath, channel, line),
      zhOriginal,
      jaSource: override.jaSource || "TODO",
      placeholderNotes: placeholderNotes(callText, strings),
      translatorComment: override.translatorComment || "TODO",
      callPreview: callPreview(callText),
    };

    if (isJapaneseSource) {
      excluded.push({ ...row, reason: "Japanese source/backlog" });
    } else {
      rows.push(row);
    }
  }
}

function escapeCell(value) {
  return String(value)
    .replace(/\r?\n/g, " ")
    .replace(/\|/g, "\\|")
    .trim();
}

function table(rowsToWrite, includeReason = false) {
  const header = includeReason
    ? "| Source file | Line | Channel | Bucket | Key | Original | Call preview | Reason |\n| --- | ---: | --- | --- | --- | --- | --- | --- |"
    : "| Source file | Line | Channel | Bucket | Key | zh-TW original | ja source / fallback | Placeholder notes | Call preview | Translator comment draft |\n| --- | ---: | --- | --- | --- | --- | --- | --- | --- | --- |";

  const body = rowsToWrite.map((row) => {
    const cells = includeReason
      ? [
          row.sourceFile,
          row.line,
          row.channel,
          row.bucket,
          row.key,
          row.zhOriginal,
          row.callPreview,
          row.reason,
        ]
      : [
          row.sourceFile,
          row.line,
          row.channel,
          row.bucket,
          row.key,
          row.zhOriginal,
          row.jaSource,
          row.placeholderNotes,
          row.callPreview,
          row.translatorComment,
        ];
    return `| ${cells.map(escapeCell).join(" | ")} |`;
  });

  return `${header}\n${body.join("\n")}`;
}

const counts = rows.reduce((acc, row) => {
  const key = `${row.bucket}:${row.channel}`;
  acc[key] = (acc[key] || 0) + 1;
  return acc;
}, {});

const summaryLines = Object.keys(counts)
  .sort()
  .map((key) => `- ${key}: ${counts[key]}`)
  .join("\n");

const output = `# Console Logs zh-to-ja Translation Table

> Status: Phase 1.5 inventory skeleton.
>
> Generated by \`node tools/node/generate-console-log-inventory.js\`.
> This commit intentionally fills raw inventory, draft bucket, and draft key only.
> Japanese source/fallback and translator comments are left as TODO for the
> translation-review commit.

## Inventory Bootstrap

Run these from the repo root to cross-check the raw call-site list:

\`\`\`powershell
rg -n --glob "!js/dist/**" --multiline 'console\\.(log|warn|error|info)\\s*\\([^)]*[一-鿿]' js/ ghost/Frieren/
rg -n --glob "!js/dist/**" --multiline 'mpuLogger\\.(log|warn|error|info)\\s*\\([^)]*[一-鿿]' js/ ghost/Frieren/
\`\`\`

Normalize each row manually before migration:

- Exclude \`js/dist/**\`; dist files are generated by build.
- Mark production-visible direct \`console.error\` / \`console.warn\` rows as
  \`logs\`.
- Mark existing debug-gated \`mpuLogger.log\` / \`warn\` / \`info\` rows as
  \`logsDebug\`.
- Use \`logs\` for \`mpuLogger.error\` because \`error()\` is always output.
- For \`ghost/Frieren/**\`, use a \`frieren\` key prefix.

## Inventory Summary

- Included zh-TW/CJK rows: ${rows.length}
- Excluded source/backlog rows: ${excluded.length}

${summaryLines}

## Translation Rows

${table(rows)}

## Excluded / Backlog Rows

${table(excluded, true)}
`;

fs.writeFileSync(outputPath, output);
console.log(`Wrote ${rows.length} inventory rows to ${path.relative(repoRoot, outputPath)}`);
console.log(`Excluded ${excluded.length} rows for backlog review`);
