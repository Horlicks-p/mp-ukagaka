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
    jaSource: "🌙 睡眠モードでまだ目を覚ましていないため、自動会話を開始せず OK ボタンのみ受け付けます",
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
    jaSource: "🌙 睡眠モードでまだ目を覚ましていないため、今回の自動会話をスキップし OK ボタンのみ受け付けます",
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
    jaSource: "🌙 睡眠モードでまだ目を覚ましていないため、%s トリガーの会話をスキップし OK ボタンのみ受け付けます",
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
  "js/ukagaka-anime.js::⏭️ 已在芙莉蓮模式，跳過重新初始化": {
    key: "animeSkipFrierenReinit",
    jaSource: "⏭️ すでにフリーレンモードのため、再初期化をスキップします",
    translatorComment: "debug console log. Anime initialization is skipped because Frieren mode is already active.",
  },
  "js/ukagaka-base.js::打字效果已被中斷": {
    key: "typewriterInterrupted",
    jaSource: "タイピング効果を中断しました",
    translatorComment: "debug console log. Typewriter animation was interrupted.",
  },
  "js/ukagaka-base.js::mpu_waitForTypewriterComplete: callback 不是函數": {
    key: "typewriterWaitCallbackInvalid",
    jaSource: "mpu_waitForTypewriterComplete: callback が関数ではありません",
    translatorComment: "debug console log. The typewriter completion callback is not a function.",
  },
  "js/ukagaka-base.js::mpu_waitForTypewriterComplete: 等待超時，強制執行回調": {
    key: "typewriterWaitTimeoutForcingCallback",
    jaSource: "mpu_waitForTypewriterComplete: 待機がタイムアウトしたため、callback を強制実行します",
    translatorComment: "debug console log. Typewriter wait timed out and callback will be forced.",
  },
  "js/ukagaka-base.js::jQuery 尚未載入，無法初始化 jQuery.cookie": {
    key: "jqueryMissingForCookieInit",
    jaSource: "jQuery がまだ読み込まれていないため、jQuery.cookie を初期化できません",
    translatorComment: "debug console log. jQuery is missing before jQuery.cookie initialization.",
  },
  "js/ukagaka-base.js::jQuery 尚未載入，無法初始化閒置偵測": {
    key: "jqueryMissingForIdleDetection",
    jaSource: "jQuery がまだ読み込まれていないため、アイドル検出を初期化できません",
    translatorComment: "debug console log. jQuery is missing before idle detection initialization.",
  },
  "js/ukagaka-base.js::閒置偵測已初始化，閾值： / 秒": {
    key: "idleDetectionInitialized",
    jaSource: "アイドル検出を初期化しました。しきい値：%s 秒",
    translatorComment: "debug console log. %s is the idle threshold in seconds.",
  },
  "js/ukagaka-base.js::已更新最後訪問時間": {
    key: "lastVisitTimeUpdated",
    jaSource: "最終訪問時刻を更新しました",
    translatorComment: "debug console log. Last visit timestamp was updated.",
  },
  "js/ukagaka-base.js::無法更新最後訪問時間:": {
    key: "lastVisitTimeUpdateFailed",
    jaSource: "最終訪問時刻を更新できませんでした：%s",
    translatorComment: "debug console log. %s is the caught error value.",
  },
  "js/ukagaka-base.js::無法獲取最後訪問時間:": {
    key: "lastVisitTimeReadFailed",
    jaSource: "最終訪問時刻を取得できませんでした：%s",
    translatorComment: "debug console log. %s is the caught error value.",
  },
  "js/ukagaka-base.js::請求已取消: ${requestId}": {
    key: "requestCancelled",
    jaSource: "リクエストをキャンセルしました：%s",
    translatorComment: "debug console log. %s is the request ID. This text appears in two cancel paths.",
  },
  "js/ukagaka-base.js::請求去重，跳過: ${requestId}": {
    key: "requestDeduped",
    jaSource: "リクエストを重複排除してスキップします：%s",
    translatorComment: "debug console log. %s is the request ID skipped by dedupe.",
  },
  "js/ukagaka-base.js::請求超時: ${requestId}": {
    key: "requestTimedOut",
    jaSource: "リクエストがタイムアウトしました：%s",
    translatorComment: "debug console log. %s is the timed-out request ID.",
  },
  "js/ukagaka-base.js::重試請求 (${attempt}/${config.retries}): ${requestId}": {
    key: "requestRetrying",
    jaSource: "リクエストを再試行します（%1$s/%2$s）：%3$s",
    translatorComment: "debug console log. %1$s is current attempt, %2$s is max retries, %3$s is request ID.",
  },
  "js/ukagaka-base.js::REST Nonce 已自動更新": {
    key: "restNonceAutoUpdated",
    jaSource: "REST Nonce を自動更新しました",
    translatorComment: "debug console log. REST nonce was refreshed from a response.",
  },
  "js/ukagaka-base.js::網絡錯誤，將重試: ${error.message}": {
    key: "networkErrorWillRetry",
    jaSource: "ネットワークエラーのため再試行します：%s",
    translatorComment: "debug console log. %s is the network error message.",
  },
  "js/ukagaka-base.js::jQuery 尚未載入，無法初始化右鍵菜單": {
    key: "jqueryMissingForContextMenu",
    jaSource: "jQuery がまだ読み込まれていないため、右クリックメニューを初期化できません",
    translatorComment: "debug console log. jQuery is missing before context-menu initialization.",
  },
  "js/ukagaka-base.js::右鍵菜單觸發：顯示角色切換選單": {
    key: "contextMenuTriggered",
    jaSource: "右クリックメニューが発火しました：キャラクター切り替えメニューを表示します",
    translatorComment: "debug console log. Context menu opens the character switch menu.",
  },
  "js/ukagaka-base.js::mpuChange 函數未定義": {
    key: "changeFunctionMissing",
    jaSource: "mpuChange 関数が定義されていません",
    translatorComment: "debug console log. Character change function is unavailable.",
  },
  "js/ukagaka-base.js::右鍵菜單已初始化": {
    key: "contextMenuInitialized",
    jaSource: "右クリックメニューを初期化しました",
    translatorComment: "debug console log. Context menu initialization completed.",
  },
  "js/ukagaka-chat.js::進入互動對話模式": {
    key: "chatModeEntered",
    jaSource: "インタラクティブ会話モードに入りました",
    translatorComment: "debug console log. Interactive chat mode was entered.",
  },
  "js/ukagaka-chat.js::退出互動對話模式": {
    key: "chatModeExited",
    jaSource: "インタラクティブ会話モードを終了しました",
    translatorComment: "debug console log. Interactive chat mode was exited.",
  },
  "js/ukagaka-chat.js::正在等待回應，請稍候": {
    key: "chatWaitingForResponse",
    jaSource: "応答待ちです。しばらくお待ちください",
    translatorComment: "debug console log. User attempted to send while a response is pending.",
  },
  "js/ukagaka-chat.js::對話歷史已清除": {
    key: "chatHistoryCleared",
    jaSource: "会話履歴をクリアしました",
    translatorComment: "debug console log. Chat history was cleared.",
  },
  "js/ukagaka-chat.js::發送用戶訊息:": {
    key: "chatSendingUserMessage",
    jaSource: "ユーザーメッセージを送信します：%s",
    translatorComment: "debug console log. %s is the user message text.",
  },
  "js/ukagaka-chat.js::對話模式已關閉，捨棄本次 AI 回應": {
    key: "chatModeClosedDiscardAiResponse",
    jaSource: "会話モードが閉じているため、今回の AI 応答を破棄します",
    translatorComment: "debug console log. AI response is discarded because chat mode closed.",
  },
  "js/ukagaka-chat.js::對話模式已關閉，捨棄錯誤訊息": {
    key: "chatModeClosedDiscardError",
    jaSource: "会話モードが閉じているため、エラーメッセージを破棄します",
    translatorComment: "debug console log. Error message is discarded because chat mode closed.",
  },
  "js/ukagaka-chat.js::頁面載入：對話歷史已載入，當前記錄數:": {
    key: "chatHistoryLoadedOnPageLoad",
    jaSource: "ページ読み込み：会話履歴を読み込みました。現在の記録数：%s",
    translatorComment: "debug console log. %s is the current chat history length.",
  },
  "js/ukagaka-chat.js::裝飾物對話進行中，忽略按鈕點擊": {
    key: "chatButtonIgnoredDecorationDialogActive",
    jaSource: "装飾品会話中のため、ボタンクリックを無視します",
    translatorComment: "debug console log. Button click is ignored while a decoration dialog is active. This text appears in two button handlers.",
  },
  "js/ukagaka-chat.js::訊息被阻擋，忽略按鈕點擊": {
    key: "chatButtonIgnoredMessageBlocking",
    jaSource: "メッセージがブロックされているため、ボタンクリックを無視します",
    translatorComment: "debug console log. Button click is ignored while message blocking is active. This text appears in two button handlers.",
  },
  "js/ukagaka-chat.js::退出對話模式": {
    key: "chatModeExitRequested",
    jaSource: "会話モードを終了します",
    translatorComment: "debug console log. Chat mode exit was requested from a control.",
  },
  "js/ukagaka-chat.js::互動對話模式已初始化": {
    key: "chatModeInitialized",
    jaSource: "インタラクティブ会話モードを初期化しました",
    translatorComment: "debug console log. Interactive chat mode initialization completed.",
  },
  "js/ukagaka-chat.js::🌅 喚醒角色！正在準備發送請求...": {
    key: "wakeRequestPreparing",
    jaSource: "🌅 キャラクターを起こします。リクエスト送信を準備しています...",
    translatorComment: "debug console log. Wake-up request is being prepared.",
  },
  "js/ukagaka-chat.js::喚醒請求已取消：缺少 personality_id/ukagaka_num，避免人格狀態錯亂": {
    key: "wakeRequestCancelledMissingIdentity",
    jaSource: "目覚めリクエストをキャンセルしました：personality_id/ukagaka_num が不足しているため、人格状態の混乱を避けます",
    translatorComment: "debug console log. Wake request is cancelled because identity data is missing.",
  },
  "js/ukagaka-chat.js::喚醒成功:": {
    key: "wakeRequestSucceeded",
    jaSource: "目覚めに成功しました：%s",
    translatorComment: "debug console log. %s is the wake request response object.",
  },
  "js/ukagaka-chat.js::這是一次深度睡眠期間的暫時喚醒": {
    key: "wakeRequestTemporaryDuringDeepSleep",
    jaSource: "これは深い睡眠中の一時的な目覚めです",
    translatorComment: "debug console log. Wake-up was temporary during deep sleep.",
  },
  "js/ukagaka-chat.js::喚醒請求回應失敗:": {
    key: "wakeRequestResponseFailed",
    jaSource: "目覚めリクエストの応答が失敗しました：%s",
    translatorComment: "debug console log. %s is the failed wake response object.",
  },
  "js/ukagaka-chat.js::喚醒請求失敗，但不影響正常操作:": {
    key: "wakeRequestFailedNonBlocking",
    jaSource: "目覚めリクエストに失敗しましたが、通常動作には影響しません：%s",
    translatorComment: "debug console log. %s is the caught wake request error.",
  },
  "js/ukagaka-context.js::mpu_check_page_trigger: triggerPages 為空，返回 false": {
    key: "contextTriggerPagesEmpty",
    jaSource: "mpu_check_page_trigger: triggerPages が空のため false を返します",
    translatorComment: "debug console log. Page trigger configuration is empty.",
  },
  "js/ukagaka-context.js::mpu_check_page_trigger: 檢查條件 =": {
    key: "contextTriggerConditionsCheck",
    jaSource: "mpu_check_page_trigger: チェック条件 = %s、path = %s",
    translatorComment: "debug console log. %s values are the trigger conditions and current path.",
  },
  "js/ukagaka-context.js::mpu_check_page_trigger: is_single 檢查 - 排除首頁/歸檔頁面": {
    key: "contextTriggerIsSingleExcludingHomeArchive",
    jaSource: "mpu_check_page_trigger: is_single チェック - ホームページ/アーカイブページを除外します",
    translatorComment: "debug console log. Additional object data describes the is_single exclusion check.",
  },
  "js/ukagaka-context.js::mpu_check_page_trigger: is_single 檢查": {
    key: "contextTriggerIsSingleCheck",
    jaSource: "mpu_check_page_trigger: is_single チェック",
    translatorComment: "debug console log. Additional object data describes the is_single trigger check.",
  },
  "js/ukagaka-context.js::mpu_check_page_trigger: is_page 檢查": {
    key: "contextTriggerIsPageCheck",
    jaSource: "mpu_check_page_trigger: is_page チェック",
    translatorComment: "debug console log. Additional object data describes the is_page trigger check.",
  },
  "js/ukagaka-context.js::mpu_check_page_trigger: is_home/is_front_page 檢查": {
    key: "contextTriggerIsHomeFrontPageCheck",
    jaSource: "mpu_check_page_trigger: is_home/is_front_page チェック",
    translatorComment: "debug console log. Additional object data describes the home/front-page trigger check.",
  },
  "js/ukagaka-context.js::mpu_check_page_trigger: is_archive 檢查": {
    key: "contextTriggerIsArchiveCheck",
    jaSource: "mpu_check_page_trigger: is_archive チェック",
    translatorComment: "debug console log. Additional object data describes the archive trigger check.",
  },
  "js/ukagaka-context.js::mpu_check_page_trigger: is_category 檢查": {
    key: "contextTriggerIsCategoryCheck",
    jaSource: "mpu_check_page_trigger: is_category チェック",
    translatorComment: "debug console log. Additional object data describes the category trigger check.",
  },
  "js/ukagaka-context.js::mpu_check_page_trigger: is_tag 檢查": {
    key: "contextTriggerIsTagCheck",
    jaSource: "mpu_check_page_trigger: is_tag チェック",
    translatorComment: "debug console log. Additional object data describes the tag trigger check.",
  },
  "js/ukagaka-context.js::mpu_chat_context: 對話模式中，跳過頁面感知 AI": {
    key: "contextChatSkippedChatMode",
    jaSource: "mpu_chat_context: 会話モード中のためページ感知 AI をスキップします",
    translatorComment: "debug console log. Page-aware AI is skipped while chat mode is active.",
  },
  "js/ukagaka-context.js::mpu_chat_context: 頁面感知進行中，跳過新觸發": {
    key: "contextChatSkippedPageAwareInProgress",
    jaSource: "mpu_chat_context: ページ感知処理中のため新しいトリガーをスキップします",
    translatorComment: "debug console log. A new page-aware trigger is skipped while one is already running.",
  },
  "js/ukagaka-context.js::🌙 睡眠模式（00:00-06:00）：跳過頁面感知 AI，讓角色好好休息": {
    key: "contextChatSkippedSleepMode",
    jaSource: "🌙 睡眠モード（00:00-06:00）：ページ感知 AI をスキップし、キャラクターを休ませます",
    translatorComment: "debug console log. Page-aware AI is skipped during sleep mode.",
  },
  "js/ukagaka-context.js::mpu_chat_context: 頁面上下文檢查": {
    key: "contextChatPageContextCheck",
    jaSource: "mpu_chat_context: ページコンテキストチェック",
    translatorComment: "debug console log. Additional object data describes the page context check.",
  },
  "js/ukagaka-context.js::mpu_chat_context: 沒有標題和內容，跳過": {
    key: "contextChatSkippedEmptyTitleContent",
    jaSource: "mpu_chat_context: タイトルと本文がないためスキップします",
    translatorComment: "debug console log. Page-aware chat is skipped because both title and content are missing.",
  },
  "js/ukagaka-context.js::mpu_chat_context: 首次訪客打招呼進行中，跳過": {
    key: "contextChatSkippedFirstVisitorGreeting",
    jaSource: "mpu_chat_context: 初回訪問者への挨拶中のためスキップします",
    translatorComment: "debug console log. Page-aware chat is skipped during the first-visitor greeting.",
  },
  "js/ukagaka-context.js::mpu_chat_context: 內容長度不足 300 字（當前: / ），跳過": {
    key: "contextChatSkippedShortContent",
    jaSource: "mpu_chat_context: 内容が 300 文字未満です（現在：%s）。スキップします",
    translatorComment: "debug console log. %s is the current content length.",
  },
  "js/ukagaka-context.js::mpu_chat_context: 對話已加入歷史並儲存": {
    key: "contextChatSavedToHistory",
    jaSource: "mpu_chat_context: 会話を履歴に追加して保存しました",
    translatorComment: "debug console log. Page-aware chat was added to history and saved.",
  },
  "js/ukagaka-context.js::AI 對話失敗，使用預設對話系統:": {
    key: "contextChatAiFailedUseDefault",
    jaSource: "AI 会話に失敗したため、既定の会話システムを使用します：%s",
    translatorComment: "debug console warning. %s is the failed AI response object.",
  },
  "js/ukagaka-context.js::訪客資訊: / 無 / 無 / 無 / 無 / 無": {
    key: "contextVisitorInfo",
    jaSource: "訪問者情報：%s",
    translatorComment: "debug console log. %s is the visitor information object.",
  },
  "js/ukagaka-features.js::jQuery ready 已執行": {
    key: "featuresJqueryReady",
    jaSource: "jQuery ready を実行しました",
    translatorComment: "debug console log. jQuery ready handler has run.",
  },
  "js/ukagaka-features.js::jQuery.cookie 已成功初始化": {
    key: "featuresJqueryCookieInitialized",
    jaSource: "jQuery.cookie を正常に初期化しました",
    translatorComment: "debug console log. jQuery.cookie initialization succeeded.",
  },
  "js/ukagaka-features.js::LLM 取代對話已啟用，但仍載入內建對話作為後備": {
    key: "featuresLlmReplaceDialogueFallbackLoaded",
    jaSource: "LLM 置換会話が有効ですが、フォールバックとして内蔵会話も読み込みます",
    translatorComment: "debug console log. Built-in dialogue remains loaded as a fallback when LLM replacement is enabled.",
  },
  "js/ukagaka-features.js::processSettings: 已處理過設定，跳過重複調用": {
    key: "featuresProcessSettingsAlreadyHandled",
    jaSource: "processSettings: 設定は処理済みのため、重複呼び出しをスキップします",
    translatorComment: "debug console log. Duplicate settings processing is skipped.",
  },
  "js/ukagaka-features.js::mpu_get_settings: 無效的回應": {
    key: "featuresGetSettingsInvalidResponse",
    jaSource: "mpu_get_settings: 無効な応答です：%s",
    translatorComment: "debug console warning. %s is the invalid settings response object.",
  },
  "js/ukagaka-features.js::mpu_get_settings: 收到設定 =": {
    key: "featuresGetSettingsReceived",
    jaSource: "mpu_get_settings: 設定を受信しました = %s",
    translatorComment: "debug console log. %s is the serialized settings response.",
  },
  "js/ukagaka-features.js::mpu_get_settings: 設置 mpuAutoTalk =": {
    key: "featuresGetSettingsAutoTalkSet",
    jaSource: "mpu_get_settings: mpuAutoTalk を設定しました = %s",
    translatorComment: "debug console log. %s is the auto-talk enabled flag.",
  },
  "js/ukagaka-features.js::🌙 睡眠模式啟用（00:00~06:00），間隔調整為 / ms（原始:": {
    key: "featuresSleepModeIntervalAdjusted",
    jaSource: "🌙 睡眠モードが有効です（00:00~06:00）。間隔を %1$s ms に調整しました（元：%2$s ms）",
    translatorComment: "debug console log. %1$s is the adjusted interval in ms, %2$s is the original interval in ms.",
  },
  "js/ukagaka-features.js::mpu_get_settings: 設置 mpuAutoTalkInterval =": {
    key: "featuresGetSettingsAutoTalkIntervalSet",
    jaSource: "mpu_get_settings: mpuAutoTalkInterval を設定しました = %s ms",
    translatorComment: "debug console log. %s is the auto-talk interval in ms.",
  },
  "js/ukagaka-features.js::LLM 取代對話設定: / 啟用 / 停用 / ，互動對話模式: / 啟用 / 停用": {
    key: "featuresLlmReplaceDialogueSettings",
    jaSource: "LLM 置換会話設定：%1$s、インタラクティブ会話モード：%2$s",
    translatorComment: "debug console log. %1$s and %2$s are enabled/disabled labels.",
  },
  "js/ukagaka-features.js::🌙 睡眠模式：跳過初始 LLM 對話觸發，保持睡眠訊息顯示": {
    key: "featuresSleepModeSkipInitialLlmTrigger",
    jaSource: "🌙 睡眠モード：初回 LLM 会話トリガーをスキップし、睡眠メッセージの表示を維持します",
    translatorComment: "debug console log. Initial LLM dialogue trigger is skipped during sleep mode.",
  },
  "js/ukagaka-features.js::🌙 睡眠訊息將保持顯示，直到自動對話計時器觸發（約 / 秒後）": {
    key: "featuresSleepMessageRetainedUntilAutoTalk",
    jaSource: "🌙 睡眠メッセージは自動会話タイマーが発火するまで表示を維持します（約 %s 秒後）",
    translatorComment: "debug console log. %s is the approximate number of seconds until auto-talk triggers.",
  },
  "js/ukagaka-features.js::LLM 取代對話已啟用，等待初始訊息完成後觸發 LLM 對話": {
    key: "featuresLlmReplaceDialogueDelayInitialTrigger",
    jaSource: "LLM 置換会話が有効です。初期メッセージの完了後に LLM 会話をトリガーします",
    translatorComment: "debug console log. LLM dialogue trigger waits for the initial message to finish.",
  },
  "js/ukagaka-features.js::mpu_get_settings: 準備調用 startAutoTalk/stopAutoTalk, mpuAutoTalk =": {
    key: "featuresAutoTalkTogglePreparing",
    jaSource: "mpu_get_settings: startAutoTalk/stopAutoTalk の呼び出しを準備します。mpuAutoTalk=%1$s、shouldDelayAutoTalk=%2$s",
    translatorComment: "debug console log. %1$s is the auto-talk flag, %2$s is whether auto-talk should be delayed.",
  },
  "js/ukagaka-features.js::頁面感知 AI 已啟用，觸發頁面條件 =": {
    key: "featuresPageAwareAiEnabled",
    jaSource: "ページ感知 AI が有効です。トリガーページ条件 = %s",
    translatorComment: "debug console log. %s is the configured page-aware trigger condition.",
  },
  "js/ukagaka-features.js::頁面感知檢查結果: shouldTrigger =": {
    key: "featuresPageAwareTriggerCheckResult",
    jaSource: "ページ感知チェック結果：shouldTrigger = %s",
    translatorComment: "debug console log. %s is the page-aware trigger result.",
  },
  "js/ukagaka-features.js::頁面感知機率檢查: 設定機率 = / %, 骰子 = / , 觸發 =": {
    key: "featuresPageAwareProbabilityCheck",
    jaSource: "ページ感知の確率チェック：設定確率=%1$s%%、ロール=%2$s、トリガー=%3$s",
    translatorComment: "debug console log. %1$s is probability percent, %2$s is the random roll, %3$s is the trigger result.",
  },
  "js/ukagaka-features.js::頁面感知 AI 將在 3 秒後觸發": {
    key: "featuresPageAwareAiTriggerScheduled",
    jaSource: "ページ感知 AI は 3 秒後にトリガーされます",
    translatorComment: "debug console log. Page-aware AI trigger has been scheduled.",
  },
  "js/ukagaka-features.js::頁面感知 AI 未通過機率檢查，不觸發": {
    key: "featuresPageAwareAiProbabilitySkipped",
    jaSource: "ページ感知 AI は確率チェックを通過しなかったため、トリガーしません",
    translatorComment: "debug console log. Page-aware AI is skipped after failing probability check.",
  },
  "js/ukagaka-features.js::頁面感知 AI 未通過頁面類型檢查，不觸發": {
    key: "featuresPageAwareAiPageTypeSkipped",
    jaSource: "ページ感知 AI はページタイプチェックを通過しなかったため、トリガーしません",
    translatorComment: "debug console log. Page-aware AI is skipped after failing page type check.",
  },
  "js/ukagaka-features.js::頁面感知 AI 未啟用（ai_enabled =": {
    key: "featuresPageAwareAiDisabled",
    jaSource: "ページ感知 AI は有効ではありません（ai_enabled = %s）",
    translatorComment: "debug console log. %s is the ai_enabled flag.",
  },
  "js/ukagaka-features.js::使用預載設定資料": {
    key: "featuresUsingPreloadedSettings",
    jaSource: "プリロード済み設定データを使用します",
    translatorComment: "debug console log. Preloaded settings data is being used.",
  },
  "js/ukagaka-features.js::從 mpuInitComplete 事件獲取設定": {
    key: "featuresSettingsFromInitComplete",
    jaSource: "mpuInitComplete イベントから設定を取得します",
    translatorComment: "debug console log. Settings are obtained from the mpuInitComplete event.",
  },
  "js/ukagaka-features.js::Fallback: 發送獨立 mpu_get_settings AJAX": {
    key: "featuresFallbackGetSettingsAjax",
    jaSource: "Fallback: 独立した mpu_get_settings AJAX を送信します",
    translatorComment: "debug console log. Fallback settings request is sent separately.",
  },
  "js/ukagaka-features.js::🔄 SPA 導航：頁面內容已更換": {
    key: "featuresSpaNavigationContentChanged",
    jaSource: "🔄 SPA ナビゲーション：ページ内容が変更されました",
    translatorComment: "debug console log. Additional argument is the SPA navigation URL.",
  },
  "js/ukagaka-features.js::🎲 SPA 頁面感知檢查：機率 = / , 骰子 = / , 觸發 =": {
    key: "featuresSpaPageAwareProbabilityCheck",
    jaSource: "🎲 SPA ページ感知チェック：確率=%1$s、ロール=%2$s、トリガー=%3$s",
    translatorComment: "debug console log. %1$s is probability, %2$s is the random roll, %3$s is the trigger result.",
  },
  "js/ukagaka-features.js::腳本載入完成": {
    key: "featuresScriptLoaded",
    jaSource: "スクリプトの読み込みが完了しました",
    translatorComment: "debug console log. Feature script loading has completed.",
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
