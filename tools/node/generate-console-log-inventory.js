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
  "js/ukagaka-core.js::startAutoTalk: mpuAutoTalk 為 false，退出": {
    key: "autoTalkDisabledExit",
    jaSource: "startAutoTalk: mpuAutoTalk が false のため終了します",
    translatorComment: "debug console log. Auto-talk startup exits because auto-talk is disabled.",
  },
  "js/ukagaka-core.js::startAutoTalk: 對話模式中，不啟動自動對話": {
    key: "autoTalkSkippedDuringChatMode",
    jaSource: "startAutoTalk: 会話モード中のため自動会話を開始しません",
    translatorComment: "debug console log. Auto-talk is not started while chat mode is active.",
  },
  "js/ukagaka-core.js::startAutoTalk: 裝飾物/觸摸對話進行中，不啟動自動對話": {
    key: "autoTalkSkippedDuringInteractionDialog",
    jaSource: "startAutoTalk: 装飾品またはタッチ会話中のため自動会話を開始しません",
    translatorComment: "debug console log. Auto-talk is skipped during decoration or touch dialog.",
  },
  "js/ukagaka-core.js::🌙 睡眠模式且尚未被喚醒：不啟動自動對話，只接受 OK 鈕觸發": {
    key: "autoTalkSkippedUnawokenSleepMode",
    jaSource: "🌙 睡眠モードでまだ起床していないため、自動会話を開始せず OK ボタンのみ受け付けます",
    translatorComment: "debug console log. Auto-talk is disabled while the character is asleep and not awakened.",
  },
  "js/ukagaka-core.js::🌙 睡眠模式啟用（00:00~06:00），間隔調整為 / ms（原始:": {
    key: "autoTalkSleepModeIntervalAdjusted",
    jaSource: "🌙 睡眠モードが有効です（00:00〜06:00）。間隔を %1$s ms に調整しました（元: %2$s ms）",
    translatorComment: "debug console log. %1$s is the adjusted interval in ms, %2$s is the original interval in ms.",
  },
  "js/ukagaka-core.js::startAutoTalk: 設置計時器，間隔 =": {
    key: "autoTalkTimerSet",
    jaSource: "startAutoTalk: タイマーを設定しました。間隔=%1$s ms、mpuAutoTalk=%2$s",
    translatorComment: "debug console log. %1$s is the timer interval in ms, %2$s is the auto-talk enabled flag.",
  },
  "js/ukagaka-core.js::自動對話計時器觸發, mpuAutoTalk =": {
    key: "autoTalkTimerTriggered",
    jaSource: "自動会話タイマーが発火しました。mpuAutoTalk=%1$s、mpuOllamaReplaceDialogue=%2$s",
    translatorComment: "debug console log. %1$s is the auto-talk flag, %2$s is the LLM replacement flag.",
  },
  "js/ukagaka-core.js::使用者閒置中（ / 秒），跳過本次自動對話": {
    key: "autoTalkSkippedUserIdle",
    jaSource: "ユーザーがアイドル状態です（%s 秒）。今回の自動会話をスキップします",
    translatorComment: "debug console log. %s is the idle duration in seconds.",
  },
  "js/ukagaka-core.js::睡眠模式狀態變化（ / 睡眠 / 正常 / 睡眠 / 正常 / ），重新啟動自動對話（新間隔:": {
    key: "autoTalkSleepModeStateChanged",
    jaSource: "睡眠モード状態が変化しました（%1$s → %2$s）。自動会話を再起動します（新しい間隔: %3$s ms）",
    translatorComment: "debug console log. %1$s is previous state, %2$s is new state, %3$s is the new interval in ms.",
  },
  "js/ukagaka-core.js::🌙 睡眠模式且尚未被喚醒：跳過本次自動對話，只接受 OK 鈕觸發": {
    key: "autoTalkTickSkippedUnawokenSleepMode",
    jaSource: "🌙 睡眠モードでまだ起床していないため、今回の自動会話をスキップし OK ボタンのみ受け付けます",
    translatorComment: "debug console log. A scheduled auto-talk tick is skipped while asleep and not awakened.",
  },
  "js/ukagaka-core.js::🛡️ Bot Alert：偵測到 Bot 入侵，Bot 名稱:": {
    key: "autoTalkBotAlertDetected",
    jaSource: "🛡️ Bot Alert: Bot の侵入を検出しました。Bot 名: %s",
    translatorComment: "debug console log. %s is the detected bot name.",
  },
  "js/ukagaka-core.js::🛡️ Turnstile 結界防禦：偵測到結界撞擊事件，攔截次數:": {
    key: "autoTalkTurnstileDefenseDetected",
    jaSource: "🛡️ Turnstile 結界防御: 結界衝突イベントを検出しました。ブロック回数: %s",
    translatorComment: "debug console log. %s is the Turnstile block count.",
  },
  "js/ukagaka-core.js::🛡️ Moelog Bot Blocker：偵測到防禦魔法攔截事件，攔截數量:": {
    key: "autoTalkBotBlockerDetected",
    jaSource: "🛡️ Moelog Bot Blocker: 防御魔法のブロックイベントを検出しました。ブロック数: %s",
    translatorComment: "debug console log. %s is the bot blocker block count.",
  },
  "js/ukagaka-core.js::🤖 AI Crawler：偵測到 AI 爬蟲訪問，crawler:": {
    key: "autoTalkAiCrawlerDetected",
    jaSource: "🤖 AI Crawler: AI クローラーの訪問を検出しました。crawler=%1$s、company=%2$s",
    translatorComment: "debug console log. %1$s is the crawler name, %2$s is the company name.",
  },
  "js/ukagaka-core.js::🌍 Visitor Pulse：訪客脈動訊號，pulse_type:": {
    key: "autoTalkVisitorPulseDetected",
    jaSource: "🌍 Visitor Pulse: 訪問者パルス信号を検出しました。pulse_type=%s",
    translatorComment: "debug console log. %s is the visitor pulse type.",
  },
  "js/ukagaka-core.js::🛡️ Akismet 垃圾留言連動：偵測到垃圾留言事件，攔截數量:": {
    key: "autoTalkAkismetSpamDetected",
    jaSource: "🛡️ Akismet スパム連携: スパムコメントイベントを検出しました。ブロック数: %s",
    translatorComment: "debug console log. %s is the spam block count.",
  },
  "js/ukagaka-core.js::🛡️ Auto-talk 事件（未分類 action）:": {
    key: "autoTalkUnclassifiedSecurityEvent",
    jaSource: "🛡️ Auto-talk イベント（未分類 action）: %s",
    translatorComment: "debug console log. %s is the unclassified action name.",
  },
  "js/ukagaka-core.js::🛡️ Turnstile/Akismet/BotBlocker/Bot Check: 無事件": {
    key: "securityCheckNoEvent",
    jaSource: "🛡️ Turnstile/Akismet/BotBlocker/Bot Check: イベントはありません",
    translatorComment: "debug console log. No security event was detected.",
  },
  "js/ukagaka-core.js::Security Check: 安全檢查失敗:": {
    key: "securityCheckFailed",
    jaSource: "Security Check: セキュリティチェックに失敗しました: %s",
    translatorComment: "debug console log. %s is the caught error value.",
  },
  "js/ukagaka-core.js::mpu_processOllamaQueue: 佇列為空": {
    key: "ollamaQueueEmpty",
    jaSource: "mpu_processOllamaQueue: キューは空です",
    translatorComment: "debug console log. The Ollama request queue has no pending item.",
  },
  "js/ukagaka-core.js::mpu_processOllamaQueue: 處理佇列中的請求, trigger = / , 剩餘佇列長度 =": {
    key: "ollamaQueueProcessingRequest",
    jaSource: "mpu_processOllamaQueue: キュー内のリクエストを処理します。trigger=%1$s、残りキュー長=%2$s",
    translatorComment: "debug console log. %1$s is the trigger, %2$s is the remaining queue length.",
  },
  "js/ukagaka-core.js::mpu_nextmsg 被調用, trigger =": {
    key: "nextMessageCalled",
    jaSource: "mpu_nextmsg が呼び出されました。trigger=%1$s、isAuto=%2$s、isStartup=%3$s、isManual=%4$s、mpuOllamaReplaceDialogue=%5$s",
    translatorComment: "debug console log. Values describe the next-message trigger and mode flags.",
  },
  "js/ukagaka-core.js::mpu_nextmsg: 訊息顯示被阻擋 (mpuMessageBlocking=true)，跳過": {
    key: "nextMessageSkippedMessageBlocking",
    jaSource: "mpu_nextmsg: メッセージ表示がブロックされています（mpuMessageBlocking=true）。スキップします",
    translatorComment: "debug console log. Next message is skipped because message blocking is active.",
  },
  "js/ukagaka-core.js::mpu_nextmsg: 對話模式中，跳過自動對話": {
    key: "nextMessageSkippedChatMode",
    jaSource: "mpu_nextmsg: 会話モード中のため自動会話をスキップします",
    translatorComment: "debug console log. Auto-talk next message is skipped during chat mode.",
  },
  "js/ukagaka-core.js::mpu_nextmsg: 裝飾物/觸摸對話進行中，跳過自動對話": {
    key: "nextMessageSkippedInteractionDialog",
    jaSource: "mpu_nextmsg: 装飾品またはタッチ会話中のため自動会話をスキップします",
    translatorComment: "debug console log. Auto-talk next message is skipped during decoration or touch dialog.",
  },
  "js/ukagaka-core.js::mpu_nextmsg: 自動對話已關閉，退出": {
    key: "nextMessageAutoTalkDisabledExit",
    jaSource: "mpu_nextmsg: 自動会話が無効のため終了します",
    translatorComment: "debug console log. Next message exits because auto-talk is disabled.",
  },
  "js/ukagaka-core.js::🌙 睡眠模式且尚未被喚醒：跳過 / 觸發的對話，只接受 OK 鈕觸發": {
    key: "nextMessageSkippedUnawokenSleepMode",
    jaSource: "🌙 睡眠モードでまだ起床していないため、%s トリガーの会話をスキップし OK ボタンのみ受け付けます",
    translatorComment: "debug console log. %s is the trigger that was skipped.",
  },
  "js/ukagaka-core.js::mpu_nextmsg: 頁面感知 AI 正在進行中，跳過自動/啟動對話": {
    key: "nextMessageSkippedPageAwareInProgress",
    jaSource: "mpu_nextmsg: ページ感知 AI が進行中のため、自動/起動会話をスキップします",
    translatorComment: "debug console log. Startup or auto next message is skipped while page-aware AI is active.",
  },
  "js/ukagaka-core.js::mpu_nextmsg: 頁面感知已排程，跳過 startup 以避免 BOT 對話覆蓋": {
    key: "nextMessageSkippedScheduledPageAwareStartup",
    jaSource: "mpu_nextmsg: ページ感知が予約済みのため、BOT 会話の上書きを避けるため startup をスキップします",
    translatorComment: "debug console log. Startup is skipped because page-aware AI is scheduled.",
  },
  "js/ukagaka-core.js::mpu_nextmsg: 首次訪客打招呼正在進行中，跳過自動/啟動對話": {
    key: "nextMessageSkippedGreetingInProgress",
    jaSource: "mpu_nextmsg: 初回訪問者への挨拶中のため、自動/起動会話をスキップします",
    translatorComment: "debug console log. Startup or auto next message is skipped while greeting is active.",
  },
  "js/ukagaka-core.js::mpu_nextmsg: Ollama 正在處理請求，自動觸發的請求被跳過": {
    key: "nextMessageAutoSkippedOllamaBusy",
    jaSource: "mpu_nextmsg: Ollama がリクエストを処理中のため、自動トリガーのリクエストをスキップします",
    translatorComment: "debug console log. Auto-triggered request is skipped because Ollama is busy.",
  },
  "js/ukagaka-core.js::mpu_nextmsg: Ollama 正在處理請求，此請求加入佇列": {
    key: "nextMessageQueuedOllamaBusy",
    jaSource: "mpu_nextmsg: Ollama がリクエストを処理中のため、このリクエストをキューに追加します",
    translatorComment: "debug console log. Request is queued because Ollama is busy.",
  },
  "js/ukagaka-core.js::mpu_nextmsg: 佇列已滿，跳過此請求": {
    key: "nextMessageSkippedQueueFull",
    jaSource: "mpu_nextmsg: キューが満杯のため、このリクエストをスキップします",
    translatorComment: "debug console log. Request is skipped because the queue is full.",
  },
  "js/ukagaka-core.js::mpu_nextmsg: 使用 LLM 生成對話": {
    key: "nextMessageUsingLlm",
    jaSource: "mpu_nextmsg: LLM を使用して会話を生成します",
    translatorComment: "debug console log. Next message will be generated by the LLM.",
  },
  "js/ukagaka-core.js::mpu_nextmsg: 發送 LLM POST 請求到": {
    key: "nextMessageSendingLlmPost",
    jaSource: "mpu_nextmsg: LLM POST リクエストを送信します: %s",
    translatorComment: "debug console log. %s is the REST endpoint URL.",
  },
  "js/ukagaka-core.js::mpu_nextmsg: LLM 回應 =": {
    key: "nextMessageLlmResponse",
    jaSource: "mpu_nextmsg: LLM 応答 = %s",
    translatorComment: "debug console log. %s is the raw LLM response object.",
  },
  "js/ukagaka-core.js::mpu_nextmsg: LLM 回應被阻擋（頁面感知 AI 正在進行中），跳過顯示": {
    key: "nextMessageLlmResponseSkippedPageAwareInProgress",
    jaSource: "mpu_nextmsg: ページ感知 AI が進行中のため、LLM 応答の表示をスキップします",
    translatorComment: "debug console log. LLM response display is skipped while page-aware AI is active.",
  },
  "js/ukagaka-core.js::mpu_nextmsg: 自發對話已加入對話歷史，當前歷史長度:": {
    key: "nextMessageSpontaneousAddedToHistory",
    jaSource: "mpu_nextmsg: 自発会話を会話履歴に追加しました。現在の履歴長: %s",
    translatorComment: "debug console log. %s is the current chat history length.",
  },
  "js/ukagaka-core.js::mpu_nextmsg: 對話歷史已儲存": {
    key: "nextMessageHistorySaved",
    jaSource: "mpu_nextmsg: 会話履歴を保存しました",
    translatorComment: "debug console log. Chat history was saved.",
  },
  "js/ukagaka-core.js::mpu_nextmsg: mpu_saveChatHistory 函數不存在，無法儲存對話歷史": {
    key: "nextMessageSaveHistoryMissing",
    jaSource: "mpu_nextmsg: mpu_saveChatHistory 関数が存在しないため、会話履歴を保存できません",
    translatorComment: "debug console log. Chat history cannot be saved because the save function is unavailable.",
  },
  "js/ukagaka-core.js::mpu_nextmsg: window.mpuChatHistory 未初始化或不是陣列，無法加入對話歷史": {
    key: "nextMessageHistoryUnavailable",
    jaSource: "mpu_nextmsg: window.mpuChatHistory が未初期化、または配列ではないため、会話履歴に追加できません",
    translatorComment: "debug console log. Chat history is unavailable or not an array.",
  },
  "js/ukagaka-core.js::mpu_nextmsg: LLM 回應完成，等待打字完成後啟動自動對話計時器": {
    key: "nextMessageLlmCompleteWaitingForTypewriter",
    jaSource: "mpu_nextmsg: LLM 応答が完了しました。タイピング完了後に自動会話タイマーを開始します",
    translatorComment: "debug console log. Auto-talk timer waits for typewriter completion after LLM response.",
  },
  "js/ukagaka-core.js::mpu_nextmsg: 打字完成，現在啟動自動對話計時器": {
    key: "nextMessageTypewriterCompleteStartingAutoTalk",
    jaSource: "mpu_nextmsg: タイピングが完了しました。自動会話タイマーを開始します",
    translatorComment: "debug console log. Typewriter completed and auto-talk timer starts.",
  },
  "js/ukagaka-core.js::mpu_nextmsg: LLM 回應沒有 msg": {
    key: "nextMessageLlmResponseMissingMessage",
    jaSource: "mpu_nextmsg: LLM 応答に msg がありません",
    translatorComment: "debug console log. LLM response object has no msg field.",
  },
  "js/ukagaka-core.js::mpu_nextmsg: LLM 回應沒有 msg，使用後備對話": {
    key: "nextMessageLlmMissingMessageUsingFallback",
    jaSource: "mpu_nextmsg: LLM 応答に msg がないため、フォールバック会話を使用します",
    translatorComment: "debug console log. Fallback dialog is used because LLM response has no msg.",
  },
  "js/ukagaka-core.js::mpu_nextmsg: fallback 完成，等待打字完成後啟動計時器": {
    key: "nextMessageFallbackCompleteWaitingForTypewriter",
    jaSource: "mpu_nextmsg: フォールバックが完了しました。タイピング完了後にタイマーを開始します",
    translatorComment: "debug console log. Timer waits for typewriter completion after fallback dialog.",
  },
  "js/ukagaka-core.js::mpu_nextmsg: 出錯，等待打字完成後啟動計時器": {
    key: "nextMessageErrorWaitingForTypewriter",
    jaSource: "mpu_nextmsg: エラーが発生しました。タイピング完了後にタイマーを開始します",
    translatorComment: "debug console log. Timer waits for typewriter completion after an error.",
  },
  "js/ukagaka-core.js::mpu_nextmsg: LLM 錯誤處理被阻擋（頁面感知 AI 正在進行中），跳過": {
    key: "nextMessageLlmErrorSkippedPageAwareInProgress",
    jaSource: "mpu_nextmsg: ページ感知 AI が進行中のため、LLM エラー処理をスキップします",
    translatorComment: "debug console log. LLM error handling is skipped while page-aware AI is active.",
  },
  "js/ukagaka-core.js::mpu_nextmsg: 對話尚未載入，等待載入完成...": {
    key: "nextMessageWaitingForDialogLoad",
    jaSource: "mpu_nextmsg: 会話がまだ読み込まれていません。読み込み完了を待機します...",
    translatorComment: "debug console log. Next message waits for dialog data to load.",
  },
  "js/ukagaka-core.js::mpu_nextmsg: 對話載入超時，已重試 3 次": {
    key: "nextMessageDialogLoadTimeout",
    jaSource: "mpu_nextmsg: 会話読み込みがタイムアウトしました。3 回再試行済みです",
    translatorComment: "debug console log. Dialog loading timed out after three retries.",
  },
  "js/ukagaka-core.js::mpu_nextmsg: 無法顯示對話 -": {
    key: "nextMessageCannotDisplayDialog",
    jaSource: "mpu_nextmsg: 会話を表示できません - store=%1$s、msgArray=%2$s",
    translatorComment: "debug console log. %1$s describes the dialog store, %2$s describes the message array.",
  },
  "js/ukagaka-core.js::mpu_nextmsg: 傳統對話，等待打字完成後啟動計時器": {
    key: "nextMessageTraditionalDialogWaitingForTypewriter",
    jaSource: "mpu_nextmsg: 通常会話です。タイピング完了後にタイマーを開始します",
    translatorComment: "debug console log. Traditional dialog waits for typewriter completion before timer restart.",
  },
  "js/ukagaka-core.js::mpu_nextmsg_fallback: 被阻擋（頁面感知 AI 正在進行中），跳過顯示": {
    key: "nextMessageFallbackSkippedPageAwareInProgress",
    jaSource: "mpu_nextmsg_fallback: ページ感知 AI が進行中のため、表示をスキップします",
    translatorComment: "debug console log. Fallback display is skipped while page-aware AI is active.",
  },
  "js/ukagaka-core.js::mpu_nextmsg_fallback: 對話尚未載入，等待載入完成...": {
    key: "nextMessageFallbackWaitingForDialogLoad",
    jaSource: "mpu_nextmsg_fallback: 会話がまだ読み込まれていません。読み込み完了を待機します...",
    translatorComment: "debug console log. Fallback waits for dialog data to load.",
  },
  "js/ukagaka-core.js::mpu_nextmsg_fallback: 對話載入超時，已重試 2 次": {
    key: "nextMessageFallbackDialogLoadTimeout",
    jaSource: "mpu_nextmsg_fallback: 会話読み込みがタイムアウトしました。2 回再試行済みです",
    translatorComment: "debug console log. Fallback dialog loading timed out after two retries.",
  },
  "js/ukagaka-core.js::mpu_nextmsg_fallback: 無法顯示後備對話 -": {
    key: "nextMessageFallbackCannotDisplayDialog",
    jaSource: "mpu_nextmsg_fallback: フォールバック会話を表示できません - store=%1$s、msgArray=%2$s",
    translatorComment: "debug console log. %1$s describes the dialog store, %2$s describes the message array.",
  },
  "js/ukagaka-core.js::mpuChange: Canvas 管理器在 Ajax 成功後才發現不存在，這不應該發生": {
    key: "changeCanvasManagerMissingAfterAjax",
    jaSource: "mpuChange: Ajax 成功後に Canvas マネージャーが存在しないことが判明しました。これは想定外です",
    translatorComment: "debug console log. Canvas manager is unexpectedly missing after Ajax success.",
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
