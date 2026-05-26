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
  "ghost/Frieren/frieren-emoji.js::mpuEmojiManager: 無法載入表情配置:": {
    key: "frierenEmojiConfigLoadFailed",
    jaSource: "mpuEmojiManager: 表情設定を読み込めませんでした：%s",
    translatorComment: "debug console warning. %s is the caught emoji config load error.",
  },
  "ghost/Frieren/frieren-emoji.js::mpuEmojiManager: 表情基礎路徑未設定": {
    key: "frierenEmojiBasePathMissing",
    jaSource: "mpuEmojiManager: 表情のベースパスが設定されていません",
    translatorComment: "debug console log. Emoji base path is not configured.",
  },
  "ghost/Frieren/frieren-emoji.js::mpuEmojiManager: 找不到 #ukagaka_img 容器": {
    key: "frierenEmojiContainerMissing",
    jaSource: "mpuEmojiManager: #ukagaka_img コンテナが見つかりません",
    translatorComment: "debug console log. The ukagaka image container is missing.",
  },
  "ghost/Frieren/frieren-emoji.js::mpuEmojiManager: 表情圖片載入失敗:": {
    key: "frierenEmojiImageLoadFailed",
    jaSource: "mpuEmojiManager: 表情画像の読み込みに失敗しました：%s",
    translatorComment: "debug console warning. %s is the failed emoji image URL.",
  },
  "ghost/Frieren/frieren-emoji.js::mpuEmojiManager: 顯示表情:": {
    key: "frierenEmojiShown",
    jaSource: "mpuEmojiManager: 表情を表示します：%s",
    translatorComment: "debug console log. %s is the emoji name being shown.",
  },
  "ghost/Frieren/frieren-emoji.js::mpuEmojiManager: 移除表情": {
    key: "frierenEmojiRemoved",
    jaSource: "mpuEmojiManager: 表情を削除します",
    translatorComment: "debug console log. Emoji display is removed.",
  },
  "ghost/Frieren/frieren.js::🌙 顯示睡眠圖片 frieren[s].png / ☀️ 顯示閒置圖片 frieren[0].png": {
    key: "frierenSleepIdleImageSelected",
    jaSource: "🌙 睡眠画像 frieren[s].png を表示します / ☀️ アイドル画像 frieren[0].png を表示します",
    translatorComment: "debug console log. Shows which Frieren base image was selected.",
  },
  "ghost/Frieren/frieren.js::裝飾物已載入，跳過重複載入": {
    key: "frierenDecorationsAlreadyLoaded",
    jaSource: "装飾品は読み込み済みのため、重複読み込みをスキップします",
    translatorComment: "debug console log. Decoration loading is skipped because it already completed.",
  },
  "ghost/Frieren/frieren.js::已從 JSON 配置載入 / 個裝飾物": {
    key: "frierenDecorationConfigLoaded",
    jaSource: "JSON 設定から %s 個の装飾品を読み込みました",
    translatorComment: "debug console log. %s is the number of decorations loaded from JSON config.",
  },
  "ghost/Frieren/frieren.js::點擊到透明區域，忽略:": {
    key: "frierenDecorationTransparentClickIgnored",
    jaSource: "透明領域がクリックされたため無視します：%s",
    translatorComment: "debug console log. %s is the decoration type whose transparent area was clicked.",
  },
  "ghost/Frieren/frieren.js::裝飾物被點擊（像素命中）:": {
    key: "frierenDecorationPixelHitClicked",
    jaSource: "装飾品がクリックされました（ピクセルヒット）：%s",
    translatorComment: "debug console log. %s is the decoration type hit by pixel detection.",
  },
  "ghost/Frieren/frieren.js::像素檢測 Canvas 已創建:": {
    key: "frierenPixelDetectionCanvasCreated",
    jaSource: "ピクセル検出 Canvas を作成しました：%1$s、%2$s",
    translatorComment: "debug console log. %1$s is the decoration type, %2$s is the canvas size.",
  },
  "ghost/Frieren/frieren.js::像素檢測:": {
    key: "frierenPixelDetectionSample",
    jaSource: "ピクセル検出：%1$s、x=%2$s、y=%3$s、alpha=%4$s、threshold=%5$s",
    translatorComment: "debug console log. Values are decoration type, pixel coordinates, alpha, and hit threshold.",
  },
  "ghost/Frieren/frieren.js::☀️ 芙莉蓮被喚醒了！": {
    key: "frierenAwakened",
    jaSource: "☀️ フリーレンが目を覚ましました！",
    translatorComment: "debug console log. Frieren has awakened; forceWakeUp may be appended as a separate suffix.",
  },
  "ghost/Frieren/frieren.js::👀 播放醒來動畫 frieren[w1-w5].png": {
    key: "frierenWakeAnimationPlaying",
    jaSource: "👀 目覚めアニメーション frieren[w1-w5].png を再生します",
    translatorComment: "debug console log. Wake animation frames are being played.",
  },
  "ghost/Frieren/frieren.js::🌅 開始喚醒動畫, skipBookFlip =": {
    key: "frierenWakeAnimationStarted",
    jaSource: "🌅 目覚めアニメーションを開始します。skipBookFlip = %s",
    translatorComment: "debug console log. %s is whether the book-flip animation should be skipped.",
  },
  "ghost/Frieren/frieren.js::📖 喚醒後播放翻書動畫": {
    key: "frierenPostWakeBookFlipPlaying",
    jaSource: "📖 目覚め後にページめくりアニメーションを再生します",
    translatorComment: "debug console log. Book-flip animation plays after wake.",
  },
  "ghost/Frieren/frieren.js::📖 喚醒後跳過翻書動畫": {
    key: "frierenPostWakeBookFlipSkipped",
    jaSource: "📖 目覚め後のページめくりアニメーションをスキップします",
    translatorComment: "debug console log. Book-flip animation is skipped after wake.",
  },
  "ghost/Frieren/frieren.js::🌙 睡眠模式：跳過翻書動畫": {
    key: "frierenSleepModeBookFlipSkipped",
    jaSource: "🌙 睡眠モード：ページめくりアニメーションをスキップします",
    translatorComment: "debug console log. Book-flip animation is skipped during sleep mode.",
  },
  "ghost/Frieren/frieren.js::📖 手動喚醒對話：跳過翻書動畫": {
    key: "frierenManualWakeDialogueBookFlipSkipped",
    jaSource: "📖 手動の目覚め会話：ページめくりアニメーションをスキップします",
    translatorComment: "debug console log. Book-flip animation is skipped for manual wake dialogue.",
  },
  "ghost/Frieren/frieren.js::handleDecorationClick 被調用，裝飾物類型:": {
    key: "frierenDecorationClickHandled",
    jaSource: "handleDecorationClick が呼び出されました。装飾品タイプ：%s",
    translatorComment: "debug console log. %s is the decoration type passed to the click handler.",
  },
  "ghost/Frieren/frieren.js::裝飾物對話正在進行中，忽略本次點擊": {
    key: "frierenDecorationClickIgnoredDialogActive",
    jaSource: "装飾品会話中のため、今回のクリックを無視します",
    translatorComment: "debug console log. Decoration click is ignored while a decoration dialog is active.",
  },
  "ghost/Frieren/frieren.js::AI 功能未啟用，跳過裝飾物對話": {
    key: "frierenDecorationDialogSkippedAiDisabled",
    jaSource: "AI 機能が有効ではないため、装飾品会話をスキップします",
    translatorComment: "debug console log. Decoration dialog is skipped because AI is disabled.",
  },
  "ghost/Frieren/frieren.js::裝飾物點擊：已取消請求": {
    key: "frierenDecorationClickRequestCancelled",
    jaSource: "装飾品クリック：リクエストをキャンセルしました",
    translatorComment: "debug console log. Additional argument is the cancelled decoration click request ID.",
  },
  "ghost/Frieren/frieren.js::裝飾物對話完成，狀態已恢復": {
    key: "frierenDecorationDialogCompletedStateRestored",
    jaSource: "装飾品会話が完了し、状態を復元しました",
    translatorComment: "debug console log. Decoration dialog completed and state was restored.",
  },
  "ghost/Frieren/frieren.js::觸摸區域檢測:": {
    key: "frierenTouchZoneDetected",
    jaSource: "タッチ領域検出：%1$s、relativeY=%2$s",
    translatorComment: "debug console log. %1$s is the zone name, %2$s is the relative Y coordinate.",
  },
  "ghost/Frieren/frieren.js::handleTouchZone 被調用，區域:": {
    key: "frierenTouchZoneHandled",
    jaSource: "handleTouchZone が呼び出されました。領域：%s",
    translatorComment: "debug console log. %s is the handled touch zone name.",
  },
  "ghost/Frieren/frieren.js::區域冷卻中，忽略點擊:": {
    key: "frierenTouchZoneClickIgnoredCooldown",
    jaSource: "領域がクールダウン中のため、クリックを無視します：%s",
    translatorComment: "debug console log. %s is the touch zone currently in cooldown.",
  },
  "ghost/Frieren/frieren.js::區域達到點擊上限，進入冷卻:": {
    key: "frierenTouchZoneClickLimitReached",
    jaSource: "領域のクリック上限に達したため、クールダウンに入ります：%s",
    translatorComment: "debug console log. %s is the touch zone entering cooldown after reaching its click limit.",
  },
  "ghost/Frieren/frieren.js::觸摸區域點擊：已取消請求": {
    key: "frierenTouchZoneRequestCancelled",
    jaSource: "タッチ領域クリック：リクエストをキャンセルしました",
    translatorComment: "debug console log. Additional argument is the cancelled touch zone request ID.",
  },
  "ghost/Frieren/frieren.js::角色觸摸事件已設置": {
    key: "frierenTouchEventsBound",
    jaSource: "キャラクターのタッチイベントを設定しました",
    translatorComment: "debug console log. Character touch event handlers have been bound.",
  },
  "ghost/Frieren/frieren.js::區域進入冷卻: / 冷卻時間: / 秒": {
    key: "frierenTouchZoneCooldownStarted",
    jaSource: "領域がクールダウンに入りました：%1$s、クールダウン時間：%2$s 秒",
    translatorComment: "debug console log. %1$s is the touch zone name, %2$s is the cooldown duration in seconds.",
  },
  "js/ukagaka-dialog.js::🌙 睡眠模式：檢測到睡眠訊息，跳過載入訊息顯示": {
    key: "dialogLoadingMessageSkippedSleepMessage",
    jaSource: "🌙 睡眠モード：睡眠メッセージを検出したため、読み込みメッセージの表示をスキップします",
    translatorComment: "debug console log. Loading message display is skipped because the sleep message is active.",
  },
  "js/ukagaka-dialog.js::loadExternalDialog: 對話文件為空": {
    key: "dialogExternalFileEmpty",
    jaSource: "loadExternalDialog: 会話ファイルが空です",
    translatorComment: "debug console warning. External dialogue file has no usable content.",
  },
  "js/ukagaka-dialog.js::loadExternalDialog: LLM 取代對話模式，對話文件為空，將依賴 LLM 生成": {
    key: "dialogExternalFileEmptyLlmFallback",
    jaSource: "loadExternalDialog: LLM 置換会話モードのため会話ファイルが空です。LLM 生成に依存します",
    translatorComment: "debug console log. Dialogue file is empty while LLM replacement mode is active.",
  },
  "js/ukagaka-dialog.js::loadExternalDialog: LLM 取代對話模式，已載入後備對話數據，但不顯示第一句": {
    key: "dialogFallbackLoadedFirstLineSuppressed",
    jaSource: "loadExternalDialog: LLM 置換会話モードで後備会話データを読み込みましたが、最初の一文は表示しません",
    translatorComment: "debug console log. Fallback dialogue data loaded but first line display is suppressed.",
  },
  "js/ukagaka-dialog.js::loadExternalDialog: 嘗試重複顯示第一句對話，已阻止": {
    key: "dialogFirstLineDuplicateBlocked",
    jaSource: "loadExternalDialog: 最初の会話文を重複表示しようとしたため阻止しました",
    translatorComment: "debug console log. Duplicate display of the first dialogue line was blocked.",
  },
  "js/ukagaka-dialog.js::🌙 睡眠模式且尚未被喚醒：跳過第一句內建對話，保持睡眠訊息": {
    key: "dialogFirstLineSkippedUnawokenSleepMode",
    jaSource: "🌙 睡眠モードでまだ目を覚ましていないため、最初の内蔵会話をスキップし、睡眠メッセージを維持します",
    translatorComment: "debug console log. First built-in dialogue is skipped while asleep and not awakened.",
  },
  "js/ukagaka-dialog.js::loadExternalDialog: 後端返回錯誤，設置空的 mpuMsgList 作為後備 -": {
    key: "dialogBackendErrorUseEmptyFallback",
    jaSource: "loadExternalDialog: バックエンドがエラーを返したため、空の mpuMsgList をフォールバックとして設定します - %s",
    translatorComment: "debug console warning. %s is the backend error message.",
  },
  "js/ukagaka-dialog.js::loadExternalDialog: 載入失敗，設置空的 mpuMsgList 作為後備": {
    key: "dialogLoadFailedUseEmptyFallback",
    jaSource: "loadExternalDialog: 読み込みに失敗したため、空の mpuMsgList をフォールバックとして設定します",
    translatorComment: "debug console warning. Dialogue loading failed and an empty message list is used as fallback.",
  },
  "js/ukagaka-emoji.js::mpuEmojiManager: 表情基礎路徑未設定": {
    key: "emojiBasePathMissing",
    jaSource: "mpuEmojiManager: 表情のベースパスが設定されていません",
    translatorComment: "debug console log. Emoji base path is not configured.",
  },
  "js/ukagaka-emoji.js::mpuEmojiManager: 找不到 #ukagaka_img 容器": {
    key: "emojiContainerMissing",
    jaSource: "mpuEmojiManager: #ukagaka_img コンテナが見つかりません",
    translatorComment: "debug console log. The ukagaka image container is missing.",
  },
  "js/ukagaka-emoji.js::mpuEmojiManager: 表情圖片載入失敗:": {
    key: "emojiImageLoadFailed",
    jaSource: "mpuEmojiManager: 表情画像の読み込みに失敗しました：%s",
    translatorComment: "debug console warning. %s is the failed emoji image URL.",
  },
  "js/ukagaka-emoji.js::mpuEmojiManager: 顯示表情:": {
    key: "emojiShown",
    jaSource: "mpuEmojiManager: 表情を表示します：%s",
    translatorComment: "debug console log. %s is the emoji name being shown.",
  },
  "js/ukagaka-emoji.js::mpuEmojiManager: 移除表情": {
    key: "emojiRemoved",
    jaSource: "mpuEmojiManager: 表情を削除します",
    translatorComment: "debug console log. Emoji display is removed.",
  },
  "js/ukagaka-greeting.js::🌙 睡眠模式：跳過初次訪客打招呼，讓角色好好休息": {
    key: "greetingSkippedSleepMode",
    jaSource: "🌙 睡眠モード：初回訪問者への挨拶をスキップし、キャラクターを休ませます",
    translatorComment: "debug console log. First-visitor greeting is skipped during sleep mode.",
  },
  "js/ukagaka-greeting.js::訪客資訊: / 無 / 無 / 無 / 無": {
    key: "greetingVisitorInfo",
    jaSource: "訪問者情報：%s",
    translatorComment: "debug console log. %s is the visitor information object used for first-visitor greeting.",
  },
  "js/ukagaka-greeting.js::mpu_greet_first_visitor: 問候已加入歷史並儲存": {
    key: "greetingSavedToHistory",
    jaSource: "mpu_greet_first_visitor: 挨拶を履歴に追加して保存しました",
    translatorComment: "debug console log. First-visitor greeting was added to history and saved.",
  },
  "js/ukagaka-greeting.js::mpu_greet_first_visitor: mpu_saveChatHistory 函數不存在，無法儲存對話歷史": {
    key: "greetingSaveHistoryFunctionMissing",
    jaSource: "mpu_greet_first_visitor: mpu_saveChatHistory 関数が存在しないため、会話履歴を保存できません",
    translatorComment: "debug console warning. Chat history cannot be saved because the save function is missing.",
  },
  "js/ukagaka-greeting.js::mpu_greet_first_visitor: window.mpuChatHistory 未初始化或不是陣列，無法加入對話歷史": {
    key: "greetingChatHistoryUnavailable",
    jaSource: "mpu_greet_first_visitor: window.mpuChatHistory が初期化されていないか配列ではないため、会話履歴に追加できません",
    translatorComment: "debug console warning. Greeting cannot be added because chat history is unavailable.",
  },
  "js/ukagaka-greeting.js::首次訪客打招呼失敗:": {
    key: "greetingFirstVisitorFailed",
    jaSource: "初回訪問者への挨拶に失敗しました：%s",
    translatorComment: "debug console warning. %s is the failed first-visitor greeting response object.",
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

function normalizeOverrideText(value) {
  return String(value).replace(/\s+/g, " ").trim();
}

function overrideKeyFor(relativePath, zhOriginal) {
  // Duplicate log text in the same file intentionally shares one override.
  return `${relativePath}::${normalizeOverrideText(zhOriginal)}`;
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
const usedOverrides = new Set();

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

    const overrideKey = overrideKeyFor(relativePath, zhOriginal);
    const override = overrides[overrideKey] || {};
    if (Object.prototype.hasOwnProperty.call(overrides, overrideKey)) {
      usedOverrides.add(overrideKey);
    }
    const row = {
      sourceFile: relativePath,
      line,
      channel: `${source}.${channel}`,
      bucket: override.bucket || bucketFor(source, channel),
      key: override.key || draftKey(relativePath, channel, line),
      overrideKey,
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

const unusedOverrides = Object.keys(overrides).filter((key) => !usedOverrides.has(key));
if (unusedOverrides.length > 0) {
  console.error("Unused console log inventory overrides:");
  for (const key of unusedOverrides) {
    console.error(`- ${key}`);
  }
  process.exit(1);
}

const rowsBySemanticKey = new Map();
for (const row of rows) {
  if (!rowsBySemanticKey.has(row.key)) {
    rowsBySemanticKey.set(row.key, []);
  }
  rowsBySemanticKey.get(row.key).push(row);
}

const duplicateKeyConflicts = [];
for (const [key, matchingRows] of rowsBySemanticKey) {
  const distinctOverrideKeys = new Set(matchingRows.map((row) => row.overrideKey));
  if (distinctOverrideKeys.size > 1) {
    duplicateKeyConflicts.push({ key, matchingRows });
  }
}

if (duplicateKeyConflicts.length > 0) {
  console.error("Conflicting console log i18n keys:");
  for (const conflict of duplicateKeyConflicts) {
    console.error(`- ${conflict.key}`);
    for (const row of conflict.matchingRows) {
      console.error(`  ${row.sourceFile}:${row.line} ${row.channel} :: ${row.zhOriginal}`);
    }
  }
  process.exit(1);
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

> Status: Phase 1.5 inventory / Phase 2 migration staging table.
>
> Generated by \`node tools/node/generate-console-log-inventory.js\`.
> Rows remain here until their call sites migrate to log i18n helpers.
> Overrides are verified on each run; stale path/line/channel entries fail fast.

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

fs.writeFileSync(outputPath, `${output.trimEnd()}\n`);
console.log(`Wrote ${rows.length} inventory rows to ${path.relative(repoRoot, outputPath)}`);
console.log(`Excluded ${excluded.length} rows for backlog review`);
