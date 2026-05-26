// ====== 顯示/隱藏春菜與訊息 ======
/**
 * 顯示春菜人物
 * @param {number} speed - 淡入動畫速度（毫秒），預設 400
 */
function mpu_showrobot(speed = 400) {
  jQuery("#remove").html(mpuInfo.robot[1]); // "隱藏春菜 ▼"
  jQuery("#ukagaka").fadeIn(speed);
}

/**
 * 隱藏春菜人物
 * @param {number} speed - 淡出動畫速度（毫秒），預設 400
 */
function mpu_hiderobot(speed = 400) {
  jQuery("#remove").html(mpuInfo.robot[0]); // "顯示春菜 ▲"
  jQuery("#ukagaka").fadeOut(speed);
}

/**
 * 顯示訊息框
 * @param {number} speed - 淡入動畫速度（毫秒），預設 400
 */
function mpu_showmsg(speed = 400) {
  jQuery("#show_msg").html(mpuInfo.msg[1]);
  jQuery("#ukagaka_msgbox").fadeIn(speed);
}

/**
 * 隱藏訊息框
 * @param {number} speed - 淡出動畫速度（毫秒），預設 400
 */
function mpu_hidemsg(speed = 400) {
  jQuery("#show_msg").html(mpuInfo.msg[0]);
  if (speed === 0 || speed === "") {
    // 如果 speed 為 0 或空字串，直接隱藏（不使用動畫）
    jQuery("#ukagaka_msgbox").hide();
  } else {
    jQuery("#ukagaka_msgbox").fadeOut(speed);
  }
}

async function mpuObservationPush(type, content) {
  if (!window.mpuPageContext || !window.mpuPageContext.postId) return;
  if (typeof window.mpuRestUrl === "undefined") return;

  const send = async () => {
    const headers = { "Content-Type": "application/json" };
    if (typeof window.mpuRestNonce !== "undefined" && window.mpuRestNonce) {
      headers["X-WP-Nonce"] = window.mpuRestNonce;
    }
    if (typeof mpuEnsureSessionToken === "function") {
      const token = await mpuEnsureSessionToken();
      if (token) headers["X-MPU-Session-Token"] = token;
    }

    return fetch(window.mpuRestUrl + "observation/push", {
      method: "POST",
      credentials: "same-origin",
      cache: "no-store",
      headers,
      body: JSON.stringify({ type, content }),
    });
  };

  try {
    let response = await send();
    if (response.status === 403 && typeof mpuEnsureSessionToken === "function") {
      await mpuEnsureSessionToken(true);
      response = await send();
    }
    if (!response.ok && mpuIsDebugMode()) {
      mpuLogger.log("Observation push dropped:", response.status);
    }
  } catch (error) {
    if (mpuIsDebugMode()) {
      mpuLogger.log("Observation push failed:", error && error.message ? error.message : error);
    }
  }
}

function mpuGetObservationPostId() {
  const ctxId = parseInt(window.mpuPageContext && window.mpuPageContext.postId, 10);
  if (ctxId > 0) return ctxId;

  const candidates = [];
  const addCandidate = (value) => {
    const id = parseInt(value, 10);
    if (id > 0 && !candidates.includes(id)) candidates.push(id);
  };

  const path = window.location.pathname || "";
  if (
    path === "/" ||
    /^\/(category|tag|author|search|archive|feed)(\/|$)/.test(path) ||
    /\/page\/\d+\/?$/.test(path)
  ) {
    return 0;
  }

  const bodyClass = document.body ? document.body.className || "" : "";
  const hasSingularBodyClass = /\b(single|single-post|page)\b/.test(bodyClass);
  const articlePostElements = document.querySelectorAll("article[id^='post-'], .hentry[id^='post-']");
  if (!hasSingularBodyClass && articlePostElements.length > 1) {
    return 0;
  }

  const bodyMatch = bodyClass.match(/\b(?:postid|page-id)-(\d+)\b/);
  if (bodyMatch) addCandidate(bodyMatch[1]);

  const postElement = document.querySelector("[data-post-id], article[id^='post-'], .hentry[id^='post-']");
  if (postElement) {
    addCandidate(postElement.getAttribute("data-post-id"));
    const idMatch = (postElement.id || "").match(/\bpost-(\d+)\b/);
    if (idMatch) addCandidate(idMatch[1]);
  }

  return candidates[0] || 0;
}

function mpuClearObservationTracking() {
  const state = window.__mpuObservationState;
  if (state && Array.isArray(state.stayTimers)) {
    state.stayTimers.forEach((timer) => clearTimeout(timer));
  }
  window.__mpuObservationState = null;
  delete window.__mpuObservationStarted;
}

function mpuInitObservationTracking() {
  const postId = mpuGetObservationPostId();
  if (!postId || postId <= 0) {
    mpuClearObservationTracking();
    if (window.mpuPageContext) window.mpuPageContext.postId = 0;
    return;
  }

  if (!window.mpuPageContext) window.mpuPageContext = {};
  window.mpuPageContext.postId = postId;

  const state = window.__mpuObservationState;
  if (state && state.postId === postId && window.__mpuObservationStarted) return;

  mpuClearObservationTracking();
  window.__mpuObservationStarted = true;

  mpuObservationPush("page_view", `post:${postId}`);

  const stayTimers = [];
  [10, 30, 60, 180, 600].forEach((seconds) => {
    const timer = setTimeout(() => {
      mpuObservationPush("stay_duration", `post:${postId}:${seconds}s`);
    }, seconds * 1000);
    stayTimers.push(timer);
  });

  window.__mpuObservationState = { postId, stayTimers };
}

window.addEventListener("beforeunload", mpuClearObservationTracking);

function mpu_showMsgText() {
  const $msg = jQuery("#ukagaka_msg");
  if ($msg.length) $msg.css("visibility", "visible");
}

/**
 * 在顯示訊息前，確保春菜可見且訊息框隱藏
 * @param {number} speed - 動畫速度（毫秒），預設 400
 */
function mpu_beforemsg(speed = 400) {
  if (jQuery("#ukagaka").is(":hidden")) {
    mpu_showrobot(speed);
  } else if (!jQuery("#ukagaka_msgbox").is(":hidden")) {
    mpu_hidemsg(speed);
  }
}

// ====== 自動對話 ======

/**
 * 檢查是否為未被喚醒的睡眠模式
 * 這個函數不依賴 FrierenManager 的初始化狀態
 * @returns {boolean} 是否為睡眠模式且尚未被喚醒
 */
function mpu_isUnawokenSleepMode() {
  const isDeepSleep = mpu_isDeepSleepTime();

  if (!isDeepSleep) {
    return false;
  }

  // 如果角色管理器已初始化，使用它的方法（包含 sleepModeAwoken 檢查）
  // 目前支援 mpuFrierenManager，未來可擴展支援其他角色管理器
  if (
    typeof window.mpuFrierenManager !== "undefined" &&
    window.mpuFrierenManager.isFrierenMode &&
    typeof window.mpuFrierenManager.isSleepMessage === "function"
  ) {
    return window.mpuFrierenManager.isSleepMessage();
  }

  // 角色管理器尚未初始化，直接檢查初始訊息（後備方案）
  const msgElement = document.getElementById("ukagaka_msg");
  if (!msgElement) return false;

  const initialMsg = msgElement.getAttribute("data-initial-msg") || "";

  // 使用隱藏標記檢測睡眠模式（由 PHP 端統一添加）
  return initialMsg.includes("<!-- mpu-sleep -->");
}

/**
 * 啟動自動對話計時器
 */
function startAutoTalk() {
  stopAutoTalk();
  if (!mpuAutoTalk) {
    mpuLogger.logL("autoTalkDisabledExit", "startAutoTalk: mpuAutoTalk が false のため終了します");
    return;
  }

  // 對話模式中不啟動自動對話
  if (typeof mpuChatModeActive !== "undefined" && mpuChatModeActive) {
    mpuLogger.logL("autoTalkSkippedDuringChatMode", "startAutoTalk: 会話モード中のため自動会話を開始しません");
    return;
  }

  // 裝飾物/觸摸對話進行中不啟動自動對話
  if (
    typeof window.mpuFrierenManager !== "undefined" &&
    window.mpuFrierenManager.decorationChatInProgress
  ) {
    mpuLogger.logL("autoTalkSkippedDuringInteractionDialog", "startAutoTalk: 装飾品またはタッチ会話中のため自動会話を開始しません");
    return;
  }

  // 睡眠模式且尚未被喚醒時，不啟動自動對話（只接受 OK 鈕觸發）
  if (mpu_isUnawokenSleepMode()) {
    mpuLogger.logL("autoTalkSkippedUnawokenSleepMode", "🌙 睡眠モードでまだ目を覚ましていないため、自動会話を開始せず OK ボタンのみ受け付けます");
    return;
  }

  // 動態檢查睡眠模式（優先使用伺服器端時間）
  const checkSleepMode = function () {
    const isDeepSleep = mpu_isDeepSleepTime();

    // 獲取基礎間隔（從全域變數或當前設定）
    const baseInterval = mpuGetBaseAutoTalkInterval();

    if (isDeepSleep) {
      // 睡眠模式：使用 frequency_multiplier = 0.111（間隔延長 9 倍，約 3 分鐘）
      const sleepMultiplier = 0.111;
      const adjustedInterval = Math.round(baseInterval / sleepMultiplier);
      return { interval: adjustedInterval, isSleepMode: true };
    } else {
      // 正常模式：使用原始間隔
      return { interval: baseInterval, isSleepMode: false };
    }
  };

  // 計算當前應使用的間隔
  const sleepModeInfo = checkSleepMode();
  const currentInterval = sleepModeInfo.interval;
  const currentIsSleepMode = sleepModeInfo.isSleepMode;

  if (currentIsSleepMode) {
    mpuLogger.logF("autoTalkSleepModeIntervalAdjusted", "🌙 睡眠モードが有効です（00:00〜06:00）。間隔を %1$s ms に調整しました（元: %2$s ms）", currentInterval, mpuGetBaseAutoTalkInterval());
  }

  if (jQuery("#ukagaka_msgbox").is(":hidden")) mpu_showmsg(400);

  mpuLogger.logF("autoTalkTimerSet", "startAutoTalk: タイマーを設定しました。間隔=%1$s ms、mpuAutoTalk=%2$s", currentInterval, mpuAutoTalk);
  mpuSetAutoTalkTimer(setTimeout(function () {
    mpuSetAutoTalkTimer(null); // 清除計時器引用，表示已觸發
    mpuLogger.logF("autoTalkTimerTriggered", "自動会話タイマーが発火しました。mpuAutoTalk=%1$s、mpuOllamaReplaceDialogue=%2$s", mpuAutoTalk, mpuOllamaReplaceDialogue);

    // 閒置檢查：如果用戶閒置超過閾值，跳過本次自動對話
    const now = Date.now();
    const idleTime = now - mpuLastUserActionTime;
    if (idleTime > mpuIdleThreshold) {
      mpuLogger.logF("autoTalkSkippedUserIdle", "ユーザーがアイドル状態です（%s 秒）。今回の自動会話をスキップします", Math.floor(idleTime / 1000));
      // 雖然跳過，但仍需重新啟動計時器以檢測下一次
      if (mpuAutoTalk) startAutoTalk();
      return;
    }

    // 每次觸發前重新檢查睡眠模式，動態調整間隔
    const newSleepModeInfo = checkSleepMode();
    if (newSleepModeInfo.isSleepMode !== currentIsSleepMode) {
      mpuLogger.logF("autoTalkSleepModeStateChanged", "睡眠モード状態が変化しました（%1$s → %2$s）。自動会話を再起動します（新しい間隔: %3$s ms）", currentIsSleepMode ? "睡眠" : "通常", newSleepModeInfo.isSleepMode ? "睡眠" : "通常", newSleepModeInfo.interval);
      if (mpuAutoTalk) {
        startAutoTalk();
      }
      return;
    }

    // 檢查是否為睡眠模式且尚未被喚醒，如果是則跳過本次自動對話
    if (mpu_isUnawokenSleepMode()) {
      mpuLogger.logL("autoTalkTickSkippedUnawokenSleepMode", "🌙 睡眠モードでまだ目を覚ましていないため、今回の自動会話をスキップし OK ボタンのみ受け付けます");
      // 重新啟動計時器（雖然這次跳過）
      if (mpuAutoTalk) startAutoTalk();
      return;
    }

    // Akismet 垃圾留言連動：在自動對話前檢查是否有待處理的垃圾留言事件
    if (mpuAutoTalk) {
      mpu_checkSpamEvent(function (spamHandled) {
        if (!spamHandled) {
          // 沒有垃圾留言事件，執行正常的自動對話
          mpu_nextmsg("auto");
        }
        // 重新啟動計時器
        if (mpuAutoTalk) {
          startAutoTalk();
        } else {
          stopAutoTalk();
        }
      });
    } else {
      stopAutoTalk();
    }
  }, currentInterval));
}

/**
 * 停止自動對話計時器
 */
function stopAutoTalk() {
  if (mpuAutoTalkTimer !== null) {
    clearInterval(mpuAutoTalkTimer);
    mpuSetAutoTalkTimer(null);
  }
}

/**
 * 更新自動對話按鈕的 UI 狀態
 */
function setAutoTalkUI() {
  const $btn = jQuery("#toggleAutoTalk");
  if ($btn.length) $btn.text(mpuAutoTalk ? "停止自動對話" : "開始自動對話");
}

/**
 * 檢查是否有 Akismet 攔截的垃圾留言事件
 *
 * @param {Function} callback - 回調函數，參數為 boolean（是否已處理垃圾留言事件）
 */
function mpu_checkSpamEvent(callback) {
  const formData = new FormData();
  // [Fix] 傳送 session_id + history，讓後端在事件觸發後寫入 checksum
  const spamSessionId = typeof mpu_getOrCreateChatSessionId === "function"
    ? mpu_getOrCreateChatSessionId() : "";
  if (spamSessionId) {
    formData.append("session_id", spamSessionId);
  }
  if (typeof mpuChatHistory !== "undefined" && mpuChatHistory.length > 0) {
    formData.append("history", JSON.stringify(mpuChatHistory.slice(-10)));
  }

  mpuFetch(mpuRestUrl + "check-spam-event", {
    method: "POST",
    body: formData,
    timeout: 15000,
    retries: 0,
    requestId: "mpu_check_spam_event",
    cancelPrevious: true,
  })
    .then(function (res) {
      if (res && res.has_event && res.msg) {
        if (res.action === "bot_alert") {
          mpuLogger.logF("autoTalkBotAlertDetected", "🛡️ Bot Alert: Bot の侵入を検出しました。Bot 名: %s", res.bot_name);
        } else if (res.action === "turnstile_block") {
          mpuLogger.logF("autoTalkTurnstileDefenseDetected", "🛡️ Turnstile 結界防御: 結界衝突イベントを検出しました。ブロック回数: %s", res.block_count);
        } else if (res.action === "bot_blocker_alert") {
          mpuLogger.logF("autoTalkBotBlockerDetected", "🛡️ Moelog Bot Blocker: 防御魔法のブロックイベントを検出しました。ブロック数: %s", res.block_count);
        } else if (res.action === "ai_crawler_alert") {
          mpuLogger.logF("autoTalkAiCrawlerDetected", "🤖 AI Crawler: AI クローラーの訪問を検出しました。crawler=%1$s、company=%2$s", res.crawler, res.company);
        } else if (res.action === "visitor_pulse_alert") {
          mpuLogger.logF("autoTalkVisitorPulseDetected", "🌍 Visitor Pulse: 訪問者パルス信号を検出しました。pulse_type=%s", res.pulse_type);
        } else if (res.action === "spam_alert") {
          mpuLogger.logF("autoTalkAkismetSpamDetected", "🛡️ Akismet スパム連携: スパムコメントイベントを検出しました。ブロック数: %s", res.spam_count);
        } else {
          mpuLogger.logF("autoTalkUnclassifiedSecurityEvent", "🛡️ Auto-talk イベント（未分類 action）: %s", res.action);
        }

        // 停止當前的自動對話計時器
        stopAutoTalk();

        // 隱藏當前訊息
        mpu_hidemsg(600);

        setTimeout(function () {
          // 顯示垃圾留言反應台詞
          const aiColor =
            typeof mpuAiTextColor !== "undefined" ? mpuAiTextColor : "#4a6fa5";
          const msg =
            '<span style="color: ' + aiColor + ';">' + res.msg + "</span>";

          mpu_showMsgText();
          mpu_typewriter(msg, "#ukagaka_msg");
          mpu_showmsg(400);

          // 顯示表情（smirk 或 alert）
          if (res.emoji && typeof window.mpuEmojiManager !== "undefined") {
            if (
              typeof window.mpuEmojiConfig === "undefined" ||
              !window.mpuEmojiConfig.baseUrl
            ) {
              if (typeof window.loadEmojiConfig === "function") {
                window
                  .loadEmojiConfig()
                  .then(function () {
                    window.mpuEmojiManager.showEmoji(res.emoji);
                  })
                  .catch(function (error) {
                    mpuLogger.warn(
                      "Akismet: Failed to load emoji config:",
                      error,
                    );
                  });
              }
            } else {
              window.mpuEmojiManager.showEmoji(res.emoji);
            }
          }

          // 觸發角色動畫
          if (
            typeof window.mpuCanvasManager !== "undefined" &&
            window.mpuCanvasManager.isCharacterMode
          ) {
            window.mpuCanvasManager.triggerCharacterAnimation();
          }

          // [Fix] 將安全事件 AI 回應加入對話歷史，讓用戶開對話視窗時 chat/user verify 不會失敗
          if (typeof window.mpuChatHistory !== "undefined" && Array.isArray(window.mpuChatHistory)) {
            // synthetic user 錨點：讓 LLM 能在後續對話中看到此事件的完整脈絡
            window.mpuChatHistory.push({
              role: "user",
              content: "（システムイベントを感知した）",
              type: "synthetic",
              timestamp: Date.now(),
            });
            window.mpuChatHistory.push({
              role: "assistant",
              content: res.msg,
              type: "event",
              timestamp: Date.now(),
            });
            if (typeof mpu_saveChatHistory === "function") {
              mpu_saveChatHistory();
            }
          }

          // 等待打字完成後，通過回調告知已處理
          mpu_waitForTypewriterComplete(function () {
            callback(true);
          });
        }, 700);
      } else {
        // 沒有垃圾留言事件
        mpuLogger.logL("securityCheckNoEvent", "🛡️ Turnstile/Akismet/BotBlocker/Bot Check: イベントはありません");
        callback(false);
      }
    })
    .catch(function (error) {
      mpuLogger.warnF("securityCheckFailed", "Security Check: セキュリティチェックに失敗しました: %s", error);
      // 出錯時不阻擋正常的自動對話
      callback(false);
    });
}

// ====== 下一句對話 ======

function mpu_processOllamaQueue() {
  if (mpuOllamaRequestQueue.length === 0) {
    mpuLogger.logL("ollamaQueueEmpty", "mpu_processOllamaQueue: キューは空です");
    return;
  }

  setTimeout(function () {
    const nextTrigger = mpuOllamaRequestQueue.shift();
    mpuLogger.logF("ollamaQueueProcessingRequest", "mpu_processOllamaQueue: キュー内のリクエストを処理します。trigger=%1$s、残りキュー長=%2$s", nextTrigger, mpuOllamaRequestQueue.length);
    mpu_nextmsg(nextTrigger);
  }, mpuOllamaQueueDelay);
}

/**
 * 顯示下一句對話
 * @param {string} trigger - 觸發方式：'auto'（自動）、'startup'（啟動）、undefined（手動）
 */
function mpu_nextmsg(trigger) {
  const isAuto = trigger === "auto";
  const isStartup = trigger === "startup";
  const isManual = !isAuto && !isStartup; // 手動觸發（使用者點擊按鈕）
  mpuLogger.logF("nextMessageCalled", "mpu_nextmsg が呼び出されました。trigger=%1$s、isAuto=%2$s、isStartup=%3$s、isManual=%4$s、mpuOllamaReplaceDialogue=%5$s", trigger, isAuto, isStartup, isManual, mpuOllamaReplaceDialogue);

  if (mpuMessageBlocking) {
    mpuLogger.logL("nextMessageSkippedMessageBlocking", "mpu_nextmsg: メッセージ表示がブロックされています（mpuMessageBlocking=true）。スキップします");
    return;
  }

  // 對話模式中不執行自動對話
  if (typeof mpuChatModeActive !== "undefined" && mpuChatModeActive) {
    mpuLogger.logL("nextMessageSkippedChatMode", "mpu_nextmsg: 会話モード中のため自動会話をスキップします");
    return;
  }

  // 裝飾物/觸摸對話進行中不執行自動對話
  if (
    typeof window.mpuFrierenManager !== "undefined" &&
    window.mpuFrierenManager.decorationChatInProgress
  ) {
    mpuLogger.logL("nextMessageSkippedInteractionDialog", "mpu_nextmsg: 装飾品またはタッチ会話中のため自動会話をスキップします");
    return;
  }

  if (isAuto && !mpuAutoTalk) {
    mpuLogger.logL("nextMessageAutoTalkDisabledExit", "mpu_nextmsg: 自動会話が無効のため終了します");
    return;
  }

  // 睡眠模式且尚未被喚醒時，跳過自動和啟動觸發（只接受手動觸發）
  if ((isAuto || isStartup) && mpu_isUnawokenSleepMode()) {
    mpuLogger.logF("nextMessageSkippedUnawokenSleepMode", "🌙 睡眠モードでまだ目を覚ましていないため、%s トリガーの会話をスキップし OK ボタンのみ受け付けます", trigger);
    return;
  }

  // 停止當前正在運行的自動對話計時器（如果有的話）
  // 因為我們即將開始一段新對話，需要重新計時
  stopAutoTalk();

  if ((isAuto || isStartup) && mpuAiContextInProgress) {
    mpuLogger.logL("nextMessageSkippedPageAwareInProgress", "mpu_nextmsg: ページ感知 AI が進行中のため、自動/起動会話をスキップします");
    return;
  }

  // 頁面感知即將觸發（3 秒內），避免 startup 的 BOT 對話搶先覆蓋頁面感知
  if (isStartup && mpuIsContextPending()) {
    mpuLogger.logL("nextMessageSkippedScheduledPageAwareStartup", "mpu_nextmsg: ページ感知が予約済みのため、BOT 会話の上書きを避けるため startup をスキップします");
    mpuSetOllamaRequesting(false);
    return;
  }

  if ((isAuto || isStartup) && mpuGreetInProgress) {
    mpuLogger.logL("nextMessageSkippedGreetingInProgress", "mpu_nextmsg: 初回訪問者への挨拶中のため、自動/起動会話をスキップします");
    return;
  }

  if (mpuOllamaReplaceDialogue && mpuOllamaRequesting) {
    if (isAuto) {
      mpuLogger.logL("nextMessageAutoSkippedOllamaBusy", "mpu_nextmsg: Ollama がリクエストを処理中のため、自動トリガーのリクエストをスキップします");
      return;
    }
    if (mpuOllamaRequestQueue.length < 2) {
      mpuLogger.logL("nextMessageQueuedOllamaBusy", "mpu_nextmsg: Ollama がリクエストを処理中のため、このリクエストをキューに追加します");
      mpuOllamaRequestQueue.push(trigger);
    } else {
      mpuLogger.logL("nextMessageSkippedQueueFull", "mpu_nextmsg: キューが満杯のため、このリクエストをスキップします");
    }
    // 注意：這裡不再啟動計時器
    // startup 觸發已被加入佇列，會在佇列處理時自然完成
    // 計時器會在最終的對話完成後由打字完成回調啟動
    return;
  }

  // 手動點擊時不再立即重置計時器，改由打字完成後統一啟動

  // 🌙 睡眠模式喚醒時：讓整個對話框（包括 ZZZ 夢話文字）一起淡出
  if (!isStartup) {
    mpu_hidemsg(600);
  }

  if (mpuOllamaReplaceDialogue) {
    mpuLogger.logL("nextMessageUsingLlm", "mpu_nextmsg: LLM を使用して会話を生成します");

    mpuSetOllamaRequesting(true);
    const curNum = window.mpuInfo?.num || "default_1";
    const curMsgnum =
      parseInt(
        document.getElementById("ukagaka_msgnum")?.innerHTML || "0",
        10,
      ) || 0;

    const formData = new FormData();
    formData.append("cur_num", curNum);
    formData.append("cur_msgnum", curMsgnum);

    // 傳送上次訪問時間（用於問候語選擇）
    const lastVisitHours =
      typeof mpu_getHoursSinceLastVisit === "function"
        ? mpu_getHoursSinceLastVisit()
        : -1;
    formData.append("last_visit_hours", lastVisitHours);

    if (mpuLastLLMResponse) {
      formData.append("last_response", mpuLastLLMResponse);
    }

    if (mpuLLMResponseHistory.length > 0) {
      const recentHistory = mpuLLMResponseHistory.slice(-8);
      formData.append("response_history", JSON.stringify(recentHistory));
    }

    // [Fix] 傳送 session_id + history，讓後端記錄 LLM 自發對話的 checksum
    const nextmsgSessionId = typeof mpu_getOrCreateChatSessionId === "function"
      ? mpu_getOrCreateChatSessionId() : "";
    if (nextmsgSessionId) {
      formData.append("session_id", nextmsgSessionId);
    }
    if (typeof mpuChatHistory !== "undefined" && mpuChatHistory.length > 0) {
      formData.append("history", JSON.stringify(mpuChatHistory.slice(-10)));
    }

    mpuLogger.logF("nextMessageSendingLlmPost", "mpu_nextmsg: LLM POST リクエストを送信します: %s", mpuRestUrl + "nextmsg");

    mpuFetch(mpuRestUrl + "nextmsg", {
      method: "POST",
      body: formData,
      timeout: 60000,
      retries: 1,
      requestId: "mpu_nextmsg_llm",
      cancelPrevious: true,
    })
      .then((res) => {
        mpuLogger.logF("nextMessageLlmResponse", "mpu_nextmsg: LLM 応答 = %s", res);

        if (mpuMessageBlocking || mpuAiContextInProgress) {
          mpuLogger.logL("nextMessageLlmResponseSkippedPageAwareInProgress", "mpu_nextmsg: ページ感知 AI が進行中のため、LLM 応答の表示をスキップします");
          return;
        }

        if (res && res.msg) {
          const auto = mpuGetDialogStore()?.auto_msg || "";
          const out = res.msg + auto;

          // 觸發角色動畫（手動觸發時強制播放）
          if (
            typeof window.mpuCanvasManager !== "undefined" &&
            window.mpuCanvasManager.isCharacterMode
          ) {
            const forceAnimation = !isAuto && !isStartup;
            const skipBookFlip =
              forceAnimation && window.mpuSkipNextManualBookFlip === true;
            if (skipBookFlip) {
              window.mpuSkipNextManualBookFlip = false;
              window.mpuSkipBookFlipExpireToken = null;
            }

            // 喚醒動畫完成後顯示對話
            const isWakingUp =
              window.mpuCanvasManager.triggerCharacterAnimation(
                forceAnimation,
                function () {
                  mpu_cancelTypewriter();
                  jQuery("#ukagaka_msg").html("");
                  mpu_showMsgText();
                  mpu_typewriter(mpu_unescapeHTML(out), "#ukagaka_msg");
                  mpu_showmsg(400);
                },
                skipBookFlip
              );

            if (!isWakingUp) {
              mpu_showMsgText();
              mpu_typewriter(mpu_unescapeHTML(out), "#ukagaka_msg");
              mpu_showmsg(400);
            }
          } else {
            mpu_showMsgText();
            mpu_typewriter(mpu_unescapeHTML(out), "#ukagaka_msg");
            mpu_showmsg(400);
          }

          // 顯示表情（如果有的話）
          if (res.emoji && typeof window.mpuEmojiManager !== "undefined") {
            // 確保配置已載入
            if (
              typeof window.mpuEmojiConfig === "undefined" ||
              !window.mpuEmojiConfig.baseUrl
            ) {
              if (typeof window.loadEmojiConfig === "function") {
                window
                  .loadEmojiConfig()
                  .then(() => {
                    window.mpuEmojiManager.showEmoji(res.emoji);
                  })
                  .catch((error) => {
                    if (typeof mpuLogger !== "undefined" && mpuLogger.warn) {
                      mpuLogger.warn("Failed to load emoji config:", error);
                    }
                  });
                return;
              }
            }
            window.mpuEmojiManager.showEmoji(res.emoji);
          }

          mpuSetLastLLMResponse(res.msg);

          if (mpuLLMResponseHistory.length >= mpuMaxResponseHistory) {
            mpuLLMResponseHistory.shift();
          }
          mpuLLMResponseHistory.push(res.msg);

          // 將自發對話加入對話歷史，讓用戶開對話模式時 AI 記得剛才說過什麼
          if (
            typeof window.mpuChatHistory !== "undefined" &&
            Array.isArray(window.mpuChatHistory)
          ) {
            // synthetic user 錨點：讓 LLM 能在後續對話中看到自語的完整脈絡
            window.mpuChatHistory.push({
              role: "user",
              content: "（独り言）",
              type: "synthetic",
              timestamp: Date.now(),
            });
            window.mpuChatHistory.push({
              role: "assistant",
              content: out,
              type: "auto_talk",
              timestamp: Date.now(),
            });
            mpuLogger.logF("nextMessageSpontaneousAddedToHistory", "mpu_nextmsg: 自発会話を会話履歴に追加しました。現在の履歴長: %s", window.mpuChatHistory.length);
            if (typeof mpu_saveChatHistory === "function") {
              mpu_saveChatHistory();
              mpuLogger.logL("nextMessageHistorySaved", "mpu_nextmsg: 会話履歴を保存しました");
            } else {
              mpuLogger.warnL("nextMessageSaveHistoryMissing", "mpu_nextmsg: mpu_saveChatHistory 関数が存在しないため、会話履歴を保存できません");
            }
          } else {
            mpuLogger.warnL("nextMessageHistoryUnavailable", "mpu_nextmsg: window.mpuChatHistory が未初期化、または配列ではないため、会話履歴に追加できません");
          }

          if (res.msgnum !== undefined) {
            jQuery("#ukagaka_msgnum").html(res.msgnum);
          }

          // ⚠️ LLM 回應成功後，等待打字效果完成再啟動自動對話計時器
          if (mpuAutoTalk && !mpuAutoTalkTimer) {
            mpuLogger.logL("nextMessageLlmCompleteWaitingForTypewriter", "mpu_nextmsg: LLM 応答が完了しました。タイピング完了後に自動会話タイマーを開始します");
            mpu_waitForTypewriterComplete(function () {
              if (mpuAutoTalk && !mpuAutoTalkTimer) {
                mpuLogger.logL("nextMessageTypewriterCompleteStartingAutoTalk", "mpu_nextmsg: タイピングが完了しました。自動会話タイマーを開始します");
                startAutoTalk();
              }
            });
          }
        } else {
          mpuLogger.warnL("nextMessageLlmResponseMissingMessage", "mpu_nextmsg: LLM 応答に msg がありません", res);

          // 檢查是否為速率限制錯誤（請求過於頻繁）
          const isRateLimit =
            (res && res.error && (res.error.includes("請求過於頻繁") || res.error.includes("リクエストが多すぎます"))) ||
            (res && res.code === "rest_rate_limit_exceeded");

          if (isRateLimit) {
            const rateLimitMessage =
              typeof mpuL10n !== "undefined" && mpuL10n.apiMagicInsufficient
                ? mpuL10n.apiMagicInsufficient
                : "…ちょっと待って。API魔力が足りない";

            mpuSetLastLLMResponse("");
            mpuResetLLMResponseHistory();

            // 顯示 API 魔力不足提示，暫時阻擋自發對話
            mpu_showMsgText();
            mpu_typewriter(
              `<span style="color: ${mpuAiTextColor};">${rateLimitMessage}</span>`,
              "#ukagaka_msg",
            );
            mpu_showmsg(400);

            mpuSetMessageBlocking(true);
            const waitTime = (mpuAiDisplayDuration || 8) * 1000;

            setTimeout(function () {
              mpuSetMessageBlocking(false);

              // 顯示一條內建對話作為後備，避免角色一直沉默
              mpu_nextmsg_fallback();

              // 若原本有自動對話，則在冷卻後恢復
              if (mpuAutoTalk && !mpuAutoTalkTimer) {
                startAutoTalk();
              }
            }, waitTime);
          } else {
            mpuLogger.warnL("nextMessageLlmMissingMessageUsingFallback", "mpu_nextmsg: LLM 応答に msg がないため、フォールバック会話を使用します");
            mpuSetLastLLMResponse("");
            mpuResetLLMResponseHistory();
            mpu_nextmsg_fallback();

            // ⚠️ 即使 fallback，也等待打字完成再啟動自動對話
            if (mpuAutoTalk && !mpuAutoTalkTimer) {
              mpuLogger.logL("nextMessageFallbackCompleteWaitingForTypewriter", "mpu_nextmsg: フォールバックが完了しました。タイピング完了後にタイマーを開始します");
              mpu_waitForTypewriterComplete(function () {
                if (mpuAutoTalk && !mpuAutoTalkTimer) {
                  startAutoTalk();
                }
              });
            }
          }
        }

        mpuSetOllamaRequesting(false);
        mpu_processOllamaQueue();
      })
      .catch((error) => {
        mpuSetOllamaRequesting(false);
        mpu_processOllamaQueue();

        // ⚠️ 即使出錯，也等待打字完成再啟動自動對話
        if (mpuAutoTalk && !mpuAutoTalkTimer) {
          mpuLogger.logL("nextMessageErrorWaitingForTypewriter", "mpu_nextmsg: エラーが発生しました。タイピング完了後にタイマーを開始します");
          mpu_waitForTypewriterComplete(function () {
            if (mpuAutoTalk && !mpuAutoTalkTimer) {
              startAutoTalk();
            }
          });
        }

        if (mpuMessageBlocking || mpuAiContextInProgress) {
          mpuLogger.logL("nextMessageLlmErrorSkippedPageAwareInProgress", "mpu_nextmsg: ページ感知 AI が進行中のため、LLM エラー処理をスキップします");
          return;
        }
        mpuLogger.warn(
          "LLM dialogue generation failed, using fallback:",
          error,
        );

        if (mpuIsDebugMode()) {
          const errorMsg = error.message || "LLM 連接失敗";
          const debugMessage = `<span style="color: #ff4444;">[LLM 錯誤: ${errorMsg}]</span>`;
          mpu_showMsgText();
          mpu_typewriter(debugMessage, "#ukagaka_msg");
          mpu_showmsg(400);
          setTimeout(() => {
            mpuSetLastLLMResponse("");
            mpu_nextmsg_fallback();
          }, 2000);
        } else {
          mpuSetLastLLMResponse("");
          mpu_nextmsg_fallback();
        }
      });
    return;
  }

  setTimeout(function () {
    const store = mpuGetDialogStore();

    if (!store) {
      mpuLogger.warnL("nextMessageWaitingForDialogLoad", "mpu_nextmsg: 会話がまだ読み込まれていません。読み込み完了を待機します...");
      const retryCount = mpuGetState().retry.nextMessage || 0;
      if (retryCount < 3) {
        mpuGetState().retry.nextMessage = retryCount + 1;
        setTimeout(() => {
          mpu_nextmsg(trigger);
        }, 1000);
      } else {
        mpuGetState().retry.nextMessage = 0;
        mpu_showMsgText();
        mpu_typewriter((window.mpuL10n && window.mpuL10n.dialogNotLoaded) || "ダイアログがまだ読み込まれていません。お待ちください...", "#ukagaka_msg");
        mpu_showmsg(400);
        mpuLogger.warnL("nextMessageDialogLoadTimeout", "mpu_nextmsg: 会話読み込みがタイムアウトしました。3 回再試行済みです");
      }
      return;
    }

    mpuGetState().retry.nextMessage = 0;

    if (!Array.isArray(store.msg) || store.msg.length === 0) {
      const errorMsg =
        store.msg && store.msg.length === 0
          ? "對話文件為空，請檢查對話文件內容"
          : "訊息列表格式錯誤";
      mpu_typewriter(errorMsg, "#ukagaka_msg");
      mpu_showmsg(400);
      mpuLogger.warnF("nextMessageCannotDisplayDialog", "mpu_nextmsg: 会話を表示できません - store=%1$s、msgArray=%2$s", store ? "exists" : "null", store && Array.isArray(store.msg) ? `length=${store.msg.length}` : "not array");
      return;
    }

    const $msgnum = jQuery("#ukagaka_msgnum");
    let msgNum = parseInt($msgnum.html(), 10) || 0;
    msgNum = mpu_selectNextMessage(store, msgNum);

    const auto = store.auto_msg || "";
    const out = store.msg[msgNum] ? store.msg[msgNum] + auto : "";

    // 觸發角色動畫（手動觸發時強制播放）
    if (
      typeof window.mpuCanvasManager !== "undefined" &&
      window.mpuCanvasManager.isFrierenMode
    ) {
      // 喚醒動畫完成後顯示對話
      const skipBookFlip =
        isManual && window.mpuSkipNextManualBookFlip === true;
      if (skipBookFlip) {
        window.mpuSkipNextManualBookFlip = false;
        window.mpuSkipBookFlipExpireToken = null;
      }

      const isWakingUp = window.mpuCanvasManager.triggerCharacterAnimation(
        isManual,
        function () {
          mpu_cancelTypewriter();
          jQuery("#ukagaka_msg").html("");
          mpu_showMsgText();
          mpu_typewriter(mpu_unescapeHTML(out), "#ukagaka_msg");
          $msgnum.html(msgNum);
          mpu_showmsg(400);
        },
        skipBookFlip
      );

      if (!isWakingUp) {
        mpu_showMsgText();
        mpu_typewriter(mpu_unescapeHTML(out), "#ukagaka_msg");
        $msgnum.html(msgNum);
        mpu_showmsg(400);
      }
    } else {
      mpu_showMsgText();
      mpu_typewriter(mpu_unescapeHTML(out), "#ukagaka_msg");
      $msgnum.html(msgNum);
      mpu_showmsg(400);
    }

    // 將傳統對話加入歷史，確保互動對話模式有完整脈絡
    if (out && typeof window.mpuChatHistory !== "undefined" && Array.isArray(window.mpuChatHistory)) {
      window.mpuChatHistory.push({ role: "user", content: "（独り言）", type: "synthetic", timestamp: Date.now() });
      window.mpuChatHistory.push({ role: "assistant", content: mpu_unescapeHTML(out), type: "auto_talk", timestamp: Date.now() });
      if (typeof mpu_saveChatHistory === "function") mpu_saveChatHistory();
    }

    // ⚠️ 傳統對話流程：等待打字完成後重啟自動對話計時器
    if (mpuAutoTalk && !mpuAutoTalkTimer) {
      mpuLogger.logL("nextMessageTraditionalDialogWaitingForTypewriter", "mpu_nextmsg: 通常会話です。タイピング完了後にタイマーを開始します");
      mpu_waitForTypewriterComplete(function () {
        if (mpuAutoTalk && !mpuAutoTalkTimer) {
          startAutoTalk();
        }
      });
    }
  }, 400);
}

function mpu_nextmsg_fallback() {
  setTimeout(function () {
    mpu_showMsgText();
    if (mpuMessageBlocking || mpuAiContextInProgress) {
      mpuLogger.logL("nextMessageFallbackSkippedPageAwareInProgress", "mpu_nextmsg_fallback: ページ感知 AI が進行中のため、表示をスキップします");
      return;
    }

    const store = mpuGetDialogStore();

    if (!store) {
      mpuLogger.warnL("nextMessageFallbackWaitingForDialogLoad", "mpu_nextmsg_fallback: 会話がまだ読み込まれていません。読み込み完了を待機します...");
      const retryCount = mpuGetState().retry.fallbackMessage || 0;
      if (retryCount < 2) {
        mpuGetState().retry.fallbackMessage = retryCount + 1;
        setTimeout(() => {
          mpu_nextmsg_fallback();
        }, 1500);
      } else {
        mpuGetState().retry.fallbackMessage = 0;
        mpu_showMsgText();
        mpu_typewriter((window.mpuL10n && window.mpuL10n.dialogNotLoaded) || "ダイアログがまだ読み込まれていません。お待ちください...", "#ukagaka_msg");
        mpu_showmsg(400);
        mpuLogger.warnL("nextMessageFallbackDialogLoadTimeout", "mpu_nextmsg_fallback: 会話読み込みがタイムアウトしました。2 回再試行済みです");
      }
      return;
    }

    mpuGetState().retry.fallbackMessage = 0;

    if (!Array.isArray(store.msg) || store.msg.length === 0) {
      const errorMsg =
        store.msg && store.msg.length === 0
          ? "對話文件為空，請檢查對話文件內容"
          : "訊息列表格式錯誤";
      mpu_typewriter(errorMsg, "#ukagaka_msg");
      mpu_showmsg(400);
      mpuLogger.warnF("nextMessageFallbackCannotDisplayDialog", "mpu_nextmsg_fallback: フォールバック会話を表示できません - store=%1$s、msgArray=%2$s", store ? "exists" : "null", store && Array.isArray(store.msg) ? `length=${store.msg.length}` : "not array");
      return;
    }

    const $msgnum = jQuery("#ukagaka_msgnum");
    let msgNum = parseInt($msgnum.html(), 10) || 0;
    msgNum = mpu_selectNextMessage(store, msgNum);

    const auto = store.auto_msg || "";
    const out = store.msg[msgNum] ? store.msg[msgNum] + auto : "";
    mpu_showMsgText();
    mpu_typewriter(mpu_unescapeHTML(out), "#ukagaka_msg");

    // 觸發角色動畫
    if (
      typeof window.mpuCanvasManager !== "undefined" &&
      window.mpuCanvasManager.isFrierenMode
    ) {
      window.mpuCanvasManager.triggerCharacterAnimation();
    }

    $msgnum.html(msgNum);
    mpu_showmsg(400);

    // 將 fallback 對話加入歷史，確保互動對話模式有完整脈絡
    if (out && typeof window.mpuChatHistory !== "undefined" && Array.isArray(window.mpuChatHistory)) {
      window.mpuChatHistory.push({ role: "user", content: "（独り言）", type: "synthetic", timestamp: Date.now() });
      window.mpuChatHistory.push({ role: "assistant", content: mpu_unescapeHTML(out), type: "auto_talk", timestamp: Date.now() });
      if (typeof mpu_saveChatHistory === "function") mpu_saveChatHistory();
    }
  }, 400);
}

function mpuChange(num) {
  const hasNum = typeof num !== "undefined" && num !== null && num !== "";

  if (hasNum && typeof window.mpuCanvasManager === "undefined") {
    mpu_handle_error("Canvas 管理器未載入", "mpuChange:canvas_manager_check", {
      showToUser: true,
      userMessage: (window.mpuL10n && window.mpuL10n.animationLoadFailed) || "アニメーションモジュールの読み込みに失敗しました。ページを更新してください。",
    });
    return;
  }

  const formData = new FormData();
  if (hasNum) {
    formData.append("mpu_num", num);
  }
  const url = `${mpuRestUrl}change`;

  document.body.style.cursor = "wait";

  // 記錄自動對話狀態，以便在窗口關閉後恢復
  const wasAutoTalkRunning = mpuAutoTalkTimer !== null;

  if (!hasNum) {
    // 顯示切換窗口時，停止自動對話計時器
    stopAutoTalk();
  }

  if (!jQuery("#ukagaka_msgbox").is(":hidden")) mpu_hidemsg(200);

  mpuFetch(url, {
    method: "POST",
    body: formData,
    cancelPrevious: true,
    requestId: `mpu_change_${hasNum ? num : "menu"}`,
    timeout: 15000,
    retries: 1,
  })
    .then((res) => {
      if (!hasNum) {
        if (!res || typeof res !== "object")
          throw new Error("Invalid change-list response.");
        const $msg = jQuery("#ukagaka_msg").empty();
        if (res.items && res.items.length > 0) {
          const $wrap = jQuery("<div>").addClass("ukagaka-list");
          $wrap.append(document.createTextNode((res.heading || "") + "："));
          $wrap.append(jQuery("<br>"));
          res.items.forEach(function (item) {
            const $row = jQuery("<div>").css({ padding: "3px 0", paddingLeft: "10px" });
            const $link = jQuery("<a>")
              .text(item.name)
              .css("cursor", "pointer")
              .on("click", function () { mpuChange(item.key); });
            $row.append($link);
            $wrap.append($row);
          });
          $msg.append($wrap);
        } else {
          $msg.text(res.empty_message || "");
        }
        mpu_showmsg(300);
        jQuery("#ukagaka").stop(true, true).fadeIn(200);
        document.body.style.cursor = "auto";
        return;
      }

      if (typeof res !== "object") throw new Error("Expected JSON, got HTML.");
      const payload = res || {};
      const $canvas = jQuery("#cur_ukagaka");
      const $wrap = jQuery("#ukagaka");

      if (
        payload.shell_info &&
        typeof window.mpuCanvasManager !== "undefined"
      ) {
        const $imgWrapper = jQuery("#ukagaka_img");
        $imgWrapper.fadeOut(120, function () {
          window.mpuCanvasManager.init(
            payload.shell_info,
            payload.name || "",
            payload.num || null,
          );
          $imgWrapper.fadeIn(180);
        });
      } else if (payload.shell) {
        if (typeof window.mpuCanvasManager !== "undefined") {
          const $imgWrapper = jQuery("#ukagaka_img");
          $imgWrapper.fadeOut(120, function () {
            window.mpuCanvasManager.init(
              {
                type: "single",
                url: payload.shell,
                images: [],
              },
              payload.name || "",
              payload.num || null,
            );
            $imgWrapper.fadeIn(180);
          });
        } else {
          mpuLogger.warnL("changeCanvasManagerMissingAfterAjax", "mpuChange: Ajax 成功後に Canvas マネージャーが存在しないことが判明しました。これは想定外です");
          mpu_handle_error(
            "Canvas 管理器未載入",
            "mpuChange:canvas_manager_fallback",
            {
              showToUser: true,
              userMessage: (window.mpuL10n && window.mpuL10n.animationLoadFailed) || "アニメーションモジュールの読み込みに失敗しました。ページを更新してください。",
            },
          );
        }
      }

      if (payload.num) jQuery("#ukagaka_num").html(payload.num);
      if (payload.msg)
        mpu_typewriter(mpu_unescapeHTML(payload.msg), "#ukagaka_msg");
      if (payload.name && $canvas.length) {
        $canvas.attr({ "data-alt": payload.name, title: payload.name });
      }

      const msgListElem = document.getElementById("ukagaka_msglist");
      const useExternalDialog =
        payload.dialog_filename &&
        msgListElem &&
        msgListElem.getAttribute("data-load-external") === "true";

      if (useExternalDialog) {
        const currentFile = msgListElem.getAttribute("data-file") || "";
        const ext = currentFile.split(".").pop() || "json";
        const pure = `${payload.dialog_filename}.${ext}`;

        msgListElem.setAttribute("data-file", `dialogs/${pure}`);
        loadExternalDialog(pure);
      } else if (payload.msglist) {
        try {
          mpuSetDialogStore(
            typeof payload.msglist === "string"
              ? JSON.parse(payload.msglist)
              : payload.msglist
          );
        } catch (e) {
          mpu_handle_error(e, "mpuChange:parse_msglist");
          mpuSetDialogStore(null);
        }
      }

      $wrap.stop(true, true).fadeIn(200);
      mpu_showmsg(300);

      // 如果是從選單切換（有帶參數），則立即隱藏訊息框（不使用動畫）
      if (hasNum) {
        mpu_hidemsg(0);
      }

      // 恢復自動對話計時器（如果原本是開啟的）
      if (wasAutoTalkRunning && mpuAutoTalk && !useExternalDialog) {
        startAutoTalk();
      }
      document.body.style.cursor = "auto";
    })
    .catch((error) => {
      mpu_handle_error(error, "mpuChange", {
        showToUser: true,
        userMessage:
          mpuIsDebugMode()
            ? `読み込みに失敗しました: ${error.message}`
            : ((window.mpuL10n && window.mpuL10n.loadingFailed) || "読み込みに失敗しました。後でもう一度お試しください。"),
      });
      jQuery("#ukagaka").stop(true, true).fadeIn(200);
      mpu_showmsg(200);
      document.body.style.cursor = "auto";
    });
}

jQuery(function () {
  mpuInitObservationTracking();
});
