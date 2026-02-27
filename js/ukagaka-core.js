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

/**
 * 隱藏/顯示訊息文字（保留高度，避免視窗縮動）
 * - 用在睡眠模式按 OK 喚醒時，避免 ZZZ 閃現 & 清空文字導致訊息框「縮一下」
 */
function mpu_hideMsgText() {
  const $msg = jQuery("#ukagaka_msg");
  if ($msg.length) $msg.css("visibility", "hidden");
}
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
    mpuLogger.log("startAutoTalk: mpuAutoTalk 為 false，退出");
    return;
  }

  // 對話模式中不啟動自動對話
  if (typeof mpuChatModeActive !== "undefined" && mpuChatModeActive) {
    mpuLogger.log("startAutoTalk: 對話模式中，不啟動自動對話");
    return;
  }

  // 裝飾物/觸摸對話進行中不啟動自動對話
  if (
    typeof window.mpuFrierenManager !== "undefined" &&
    window.mpuFrierenManager.decorationChatInProgress
  ) {
    mpuLogger.log("startAutoTalk: 裝飾物/觸摸對話進行中，不啟動自動對話");
    return;
  }

  // 睡眠模式且尚未被喚醒時，不啟動自動對話（只接受 OK 鈕觸發）
  if (mpu_isUnawokenSleepMode()) {
    mpuLogger.log("🌙 睡眠模式且尚未被喚醒：不啟動自動對話，只接受 OK 鈕觸發");
    return;
  }

  // 動態檢查睡眠模式（優先使用伺服器端時間）
  const checkSleepMode = function () {
    const isDeepSleep = mpu_isDeepSleepTime();

    // 獲取基礎間隔（從全域變數或當前設定）
    const baseInterval =
      typeof window.mpuBaseAutoTalkInterval !== "undefined" &&
      window.mpuBaseAutoTalkInterval > 0
        ? window.mpuBaseAutoTalkInterval
        : mpuAutoTalkInterval;

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
    mpuLogger.log(
      "🌙 睡眠模式啟用（00:00~06:00），間隔調整為",
      currentInterval,
      "ms（原始:",
      window.mpuBaseAutoTalkInterval || mpuAutoTalkInterval,
      "ms）",
    );
  }

  if (jQuery("#ukagaka_msgbox").is(":hidden")) mpu_showmsg(400);

  mpuLogger.log("startAutoTalk: 設置計時器，間隔 =", currentInterval, "ms, mpuAutoTalk =", mpuAutoTalk);
  mpuAutoTalkTimer = setTimeout(function () {
    mpuAutoTalkTimer = null; // 清除計時器引用，表示已觸發
    mpuLogger.log(
      "自動對話計時器觸發, mpuAutoTalk =",
      mpuAutoTalk,
      ", mpuOllamaReplaceDialogue =",
      mpuOllamaReplaceDialogue,
    );

    // 閒置檢查：如果用戶閒置超過閾值，跳過本次自動對話
    const now = Date.now();
    const idleTime = now - mpuLastUserActionTime;
    if (idleTime > mpuIdleThreshold) {
      mpuLogger.log(
        "使用者閒置中（",
        Math.floor(idleTime / 1000),
        "秒），跳過本次自動對話",
      );
      // 雖然跳過，但仍需重新啟動計時器以檢測下一次
      if (mpuAutoTalk) startAutoTalk();
      return;
    }

    // 每次觸發前重新檢查睡眠模式，動態調整間隔
    const newSleepModeInfo = checkSleepMode();
    if (newSleepModeInfo.isSleepMode !== currentIsSleepMode) {
      mpuLogger.log(
        "睡眠模式狀態變化（",
        currentIsSleepMode ? "睡眠" : "正常",
        " → ",
        newSleepModeInfo.isSleepMode ? "睡眠" : "正常",
        "），重新啟動自動對話（新間隔:",
        newSleepModeInfo.interval,
        "ms）",
      );
      if (mpuAutoTalk) {
        startAutoTalk();
      }
      return;
    }

    // 檢查是否為睡眠模式且尚未被喚醒，如果是則跳過本次自動對話
    if (mpu_isUnawokenSleepMode()) {
      mpuLogger.log(
        "🌙 睡眠模式且尚未被喚醒：跳過本次自動對話，只接受 OK 鈕觸發",
      );
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
  }, currentInterval);
}

/**
 * 停止自動對話計時器
 */
function stopAutoTalk() {
  if (mpuAutoTalkTimer !== null) {
    clearInterval(mpuAutoTalkTimer);
    mpuAutoTalkTimer = null;
  }
}

/**
 * 更新自動對話按鈕的 UI 狀態
 */
function setAutoTalkUI() {
  const $btn = jQuery("#toggleAutoTalk");
  if ($btn.length) $btn.text(mpuAutoTalk ? "停止自動對話" : "開始自動對話");
}

// ====== 指令處理 ======
/**
 * 處理春菜指令
 * @param {string} command - 指令字串，例如 "(:next)"、"(:showmsg)" 等
 * @returns {boolean} 是否成功處理指令
 */
function mpuMoe(command) {
  if (!command) return false;

  const commands = {
    "(:next)": () => mpu_nextmsg(),
    "(:showmsg)": () => mpu_showmsg(400),
    "(:hidemsg)": () => mpu_hidemsg(400),
    "(:showrobot)": () => mpu_showrobot(400),
    "(:hiderobot)": () => mpu_hiderobot(400),
    "(:previous)": () => debugLog("(:previous) is not implemented."),
  };

  if (commands[command]) {
    commands[command]();
    return;
  }

  // (:msg[n])
  const m = command.match(/^\(:msg\[(\d+)\]\)$/);
  if (m) {
    const idx = parseInt(m[1], 10) - 1;
    if (window.mpuMsgList && Array.isArray(window.mpuMsgList.msg)) {
      const msgArr = window.mpuMsgList.msg;
      const auto = window.mpuMsgList.auto_msg || "";
      const safeIdx = idx >= 0 && idx < msgArr.length ? idx : 0;
      const safeMsg = msgArr[safeIdx] + auto;

      mpu_beforemsg(400);
      mpu_showmsg(400);
      setTimeout(() => {
        mpu_typewriter(mpu_unescapeHTML(safeMsg), "#ukagaka_msg");
      }, 510);
    }
    return;
  }

  // 直接發話（會附 auto_msg）
  if (window.mpuMsgList) {
    const auto = window.mpuMsgList.auto_msg || "";
    mpu_beforemsg(400);
    mpu_showmsg(400);
    setTimeout(() => {
      mpu_typewriter(mpu_unescapeHTML(command + auto), "#ukagaka_msg");
    }, 510);
  }
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
          mpuLogger.log(
            "🛡️ Bot Alert：偵測到 Bot 入侵，Bot 名稱:",
            res.bot_name,
          );
        } else if (res.action === "turnstile_block") {
          mpuLogger.log(
            "🛡️ Turnstile 結界防禦：偵測到結界撞擊事件，攔截次數:",
            res.block_count,
          );
        } else if (res.action === "bot_blocker_alert") {
          mpuLogger.log(
            "🛡️ Moelog Bot Blocker：偵測到防禦魔法攔截事件，攔截數量:",
            res.block_count,
          );
        } else {
          mpuLogger.log(
            "🛡️ Akismet 垃圾留言連動：偵測到垃圾留言事件，攔截數量:",
            res.spam_count,
          );
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
          if (typeof mpuChatHistory !== "undefined" && Array.isArray(mpuChatHistory)) {
            mpuChatHistory.push({
              role: "assistant",
              content: res.msg,
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
        mpuLogger.log("🛡️ Turnstile/Akismet/BotBlocker/Bot Check: 無事件");
        callback(false);
      }
    })
    .catch(function (error) {
      mpuLogger.warn("Security Check: 安全檢查失敗:", error);
      // 出錯時不阻擋正常的自動對話
      callback(false);
    });
}

// ====== 下一句對話 ======

function mpu_processOllamaQueue() {
  if (mpuOllamaRequestQueue.length === 0) {
    mpuLogger.log("mpu_processOllamaQueue: 佇列為空");
    return;
  }

  setTimeout(function () {
    const nextTrigger = mpuOllamaRequestQueue.shift();
    mpuLogger.log(
      "mpu_processOllamaQueue: 處理佇列中的請求, trigger =",
      nextTrigger,
      ", 剩餘佇列長度 =",
      mpuOllamaRequestQueue.length,
    );
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
  mpuLogger.log(
    "mpu_nextmsg 被調用, trigger =",
    trigger,
    ", isAuto =",
    isAuto,
    ", isStartup =",
    isStartup,
    ", isManual =",
    isManual,
    ", mpuOllamaReplaceDialogue =",
    mpuOllamaReplaceDialogue,
  );

  if (mpuMessageBlocking) {
    mpuLogger.log(
      "mpu_nextmsg: 訊息顯示被阻擋 (mpuMessageBlocking=true)，跳過",
    );
    return;
  }

  // 對話模式中不執行自動對話
  if (typeof mpuChatModeActive !== "undefined" && mpuChatModeActive) {
    mpuLogger.log("mpu_nextmsg: 對話模式中，跳過自動對話");
    return;
  }

  // 裝飾物/觸摸對話進行中不執行自動對話
  if (
    typeof window.mpuFrierenManager !== "undefined" &&
    window.mpuFrierenManager.decorationChatInProgress
  ) {
    mpuLogger.log("mpu_nextmsg: 裝飾物/觸摸對話進行中，跳過自動對話");
    return;
  }

  if (isAuto && !mpuAutoTalk) {
    mpuLogger.log("mpu_nextmsg: 自動對話已關閉，退出");
    return;
  }

  // 睡眠模式且尚未被喚醒時，跳過自動和啟動觸發（只接受手動觸發）
  if ((isAuto || isStartup) && mpu_isUnawokenSleepMode()) {
    mpuLogger.log(
      "🌙 睡眠模式且尚未被喚醒：跳過",
      trigger,
      "觸發的對話，只接受 OK 鈕觸發",
    );
    return;
  }

  // 停止當前正在運行的自動對話計時器（如果有的話）
  // 因為我們即將開始一段新對話，需要重新計時
  stopAutoTalk();

  if ((isAuto || isStartup) && mpuAiContextInProgress) {
    mpuLogger.log("mpu_nextmsg: 頁面感知 AI 正在進行中，跳過自動/啟動對話");
    return;
  }

  if ((isAuto || isStartup) && mpuGreetInProgress) {
    mpuLogger.log("mpu_nextmsg: 首次訪客打招呼正在進行中，跳過自動/啟動對話");
    return;
  }

  if (mpuOllamaReplaceDialogue && mpuOllamaRequesting) {
    if (isAuto) {
      mpuLogger.log("mpu_nextmsg: Ollama 正在處理請求，自動觸發的請求被跳過");
      return;
    }
    if (mpuOllamaRequestQueue.length < 2) {
      mpuLogger.log("mpu_nextmsg: Ollama 正在處理請求，此請求加入佇列");
      mpuOllamaRequestQueue.push(trigger);
    } else {
      mpuLogger.log("mpu_nextmsg: 佇列已滿，跳過此請求");
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
    mpuLogger.log("mpu_nextmsg: 使用 LLM 生成對話");

    mpuOllamaRequesting = true;
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

    mpuLogger.log("mpu_nextmsg: 發送 LLM POST 請求到", mpuRestUrl + "nextmsg");

    mpuFetch(mpuRestUrl + "nextmsg", {
      method: "POST",
      body: formData,
      timeout: 60000,
      retries: 1,
      requestId: "mpu_nextmsg_llm",
      cancelPrevious: true,
    })
      .then((res) => {
        mpuLogger.log("mpu_nextmsg: LLM 回應 =", res);

        if (mpuMessageBlocking || mpuAiContextInProgress) {
          mpuLogger.log(
            "mpu_nextmsg: LLM 回應被阻擋（頁面感知 AI 正在進行中），跳過顯示",
          );
          return;
        }

        if (res && res.msg) {
          const auto = window.mpuMsgList?.auto_msg || "";
          const out = res.msg + auto;

          // 觸發角色動畫（手動觸發時強制播放）
          if (
            typeof window.mpuCanvasManager !== "undefined" &&
            window.mpuCanvasManager.isCharacterMode
          ) {
            const forceAnimation = !isAuto && !isStartup;

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

          mpuLastLLMResponse = res.msg;

          if (mpuLLMResponseHistory.length >= mpuMaxResponseHistory) {
            mpuLLMResponseHistory.shift();
          }
          mpuLLMResponseHistory.push(res.msg);

          // 將自發對話加入對話歷史，讓用戶開對話模式時 AI 記得剛才說過什麼
          if (
            typeof mpuChatHistory !== "undefined" &&
            Array.isArray(mpuChatHistory)
          ) {
            mpuChatHistory.push({
              role: "assistant",
              content: res.msg,
              timestamp: Date.now(),
            });
            mpuLogger.log(
              "mpu_nextmsg: 自發對話已加入對話歷史，當前歷史長度:",
              mpuChatHistory.length,
            );
            // 限制最多保留 3 條自發對話（避免佔用太多上下文）
            // 只刪除最舊的 assistant 記錄，保留所有 user 記錄
            const maxAutoTalkHistory = 3;
            const assistantMessages = mpuChatHistory.filter(
              (msg) => msg.role === "assistant",
            );
            if (assistantMessages.length > maxAutoTalkHistory) {
              // 找到需要刪除的最舊的 assistant 記錄
              let removed = 0;
              const toRemove = assistantMessages.length - maxAutoTalkHistory;
              for (
                let i = 0;
                i < mpuChatHistory.length && removed < toRemove;
                i++
              ) {
                if (mpuChatHistory[i].role === "assistant") {
                  mpuChatHistory.splice(i, 1);
                  removed++;
                  i--; // 因為刪除了元素，索引需要減 1
                }
              }
              mpuLogger.log(
                "mpu_nextmsg: 刪除了",
                removed,
                "條舊的自發對話，保留",
                maxAutoTalkHistory,
                "條",
              );
            }
            if (typeof mpu_saveChatHistory === "function") {
              mpu_saveChatHistory();
              mpuLogger.log("mpu_nextmsg: 對話歷史已儲存");
            } else {
              mpuLogger.warn(
                "mpu_nextmsg: mpu_saveChatHistory 函數不存在，無法儲存對話歷史",
              );
            }
          } else {
            mpuLogger.warn(
              "mpu_nextmsg: mpuChatHistory 未初始化或不是陣列，無法加入對話歷史",
            );
          }

          if (res.msgnum !== undefined) {
            jQuery("#ukagaka_msgnum").html(res.msgnum);
          }

          // ⚠️ LLM 回應成功後，等待打字效果完成再啟動自動對話計時器
          if (mpuAutoTalk && !mpuAutoTalkTimer) {
            mpuLogger.log(
              "mpu_nextmsg: LLM 回應完成，等待打字完成後啟動自動對話計時器",
            );
            mpu_waitForTypewriterComplete(function () {
              if (mpuAutoTalk && !mpuAutoTalkTimer) {
                mpuLogger.log("mpu_nextmsg: 打字完成，現在啟動自動對話計時器");
                startAutoTalk();
              }
            });
          }
        } else {
          mpuLogger.warn("mpu_nextmsg: LLM 回應沒有 msg", res);

          // 檢查是否為速率限制錯誤（請求過於頻繁）
          const isRateLimit =
            res && res.error && res.error.includes("請求過於頻繁");

          if (isRateLimit) {
            const rateLimitMessage =
              typeof mpuL10n !== "undefined" && mpuL10n.apiMagicInsufficient
                ? mpuL10n.apiMagicInsufficient
                : "…ちょっと待って。API魔力が足りない";

            mpuLastLLMResponse = "";
            mpuLLMResponseHistory = [];

            // 顯示 API 魔力不足提示，暫時阻擋自發對話
            mpu_showMsgText();
            mpu_typewriter(
              `<span style="color: ${mpuAiTextColor};">${rateLimitMessage}</span>`,
              "#ukagaka_msg",
            );
            mpu_showmsg(400);

            mpuMessageBlocking = true;
            const waitTime = (mpuAiDisplayDuration || 8) * 1000;

            setTimeout(function () {
              mpuMessageBlocking = false;

              // 顯示一條內建對話作為後備，避免角色一直沉默
              mpu_nextmsg_fallback();

              // 若原本有自動對話，則在冷卻後恢復
              if (mpuAutoTalk && !mpuAutoTalkTimer) {
                startAutoTalk();
              }
            }, waitTime);
          } else {
            mpuLogger.warn("mpu_nextmsg: LLM 回應沒有 msg，使用後備對話");
            mpuLastLLMResponse = "";
            mpuLLMResponseHistory = [];
            mpu_nextmsg_fallback();

            // ⚠️ 即使 fallback，也等待打字完成再啟動自動對話
            if (mpuAutoTalk && !mpuAutoTalkTimer) {
              mpuLogger.log(
                "mpu_nextmsg: fallback 完成，等待打字完成後啟動計時器",
              );
              mpu_waitForTypewriterComplete(function () {
                if (mpuAutoTalk && !mpuAutoTalkTimer) {
                  startAutoTalk();
                }
              });
            }
          }
        }

        mpuOllamaRequesting = false;
        mpu_processOllamaQueue();
      })
      .catch((error) => {
        mpuOllamaRequesting = false;
        mpu_processOllamaQueue();

        // ⚠️ 即使出錯，也等待打字完成再啟動自動對話
        if (mpuAutoTalk && !mpuAutoTalkTimer) {
          mpuLogger.log("mpu_nextmsg: 出錯，等待打字完成後啟動計時器");
          mpu_waitForTypewriterComplete(function () {
            if (mpuAutoTalk && !mpuAutoTalkTimer) {
              startAutoTalk();
            }
          });
        }

        if (mpuMessageBlocking || mpuAiContextInProgress) {
          mpuLogger.log(
            "mpu_nextmsg: LLM 錯誤處理被阻擋（頁面感知 AI 正在進行中），跳過",
          );
          return;
        }
        mpuLogger.warn(
          "LLM dialogue generation failed, using fallback:",
          error,
        );

        if (debugMode || window.mpuDebugMode) {
          const errorMsg = error.message || "LLM 連接失敗";
          const debugMessage = `<span style="color: #ff4444;">[LLM 錯誤: ${errorMsg}]</span>`;
          mpu_showMsgText();
          mpu_typewriter(debugMessage, "#ukagaka_msg");
          mpu_showmsg(400);
          setTimeout(() => {
            mpuLastLLMResponse = "";
            mpu_nextmsg_fallback();
          }, 2000);
        } else {
          mpuLastLLMResponse = "";
          mpu_nextmsg_fallback();
        }
      });
    return;
  }

  setTimeout(function () {
    const store = window.mpuMsgList;

    if (!store) {
      mpuLogger.warn("mpu_nextmsg: 對話尚未載入，等待載入完成...");
      const retryCount = window.__mpu_retry_count || 0;
      if (retryCount < 3) {
        window.__mpu_retry_count = retryCount + 1;
        setTimeout(() => {
          mpu_nextmsg(trigger);
        }, 1000);
      } else {
        window.__mpu_retry_count = 0;
        mpu_showMsgText();
        mpu_typewriter("對話尚未載入，請稍候...", "#ukagaka_msg");
        mpu_showmsg(400);
        mpuLogger.warn("mpu_nextmsg: 對話載入超時，已重試 3 次");
      }
      return;
    }

    window.__mpu_retry_count = 0;

    if (!Array.isArray(store.msg) || store.msg.length === 0) {
      const errorMsg =
        store.msg && store.msg.length === 0
          ? "對話文件為空，請檢查對話文件內容"
          : "訊息列表格式錯誤";
      mpu_typewriter(errorMsg, "#ukagaka_msg");
      mpu_showmsg(400);
      mpuLogger.warn("mpu_nextmsg: 無法顯示對話 -", {
        store: store ? "exists" : "null",
        msgArray:
          store && Array.isArray(store.msg)
            ? `length=${store.msg.length}`
            : "not array",
      });
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

    // ⚠️ 傳統對話流程：等待打字完成後重啟自動對話計時器
    if (mpuAutoTalk && !mpuAutoTalkTimer) {
      mpuLogger.log("mpu_nextmsg: 傳統對話，等待打字完成後啟動計時器");
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
      mpuLogger.log(
        "mpu_nextmsg_fallback: 被阻擋（頁面感知 AI 正在進行中），跳過顯示",
      );
      return;
    }

    const store = window.mpuMsgList;

    if (!store) {
      mpuLogger.warn("mpu_nextmsg_fallback: 對話尚未載入，等待載入完成...");
      const retryCount = window.__mpu_fallback_retry_count || 0;
      if (retryCount < 2) {
        window.__mpu_fallback_retry_count = retryCount + 1;
        setTimeout(() => {
          mpu_nextmsg_fallback();
        }, 1500);
      } else {
        window.__mpu_fallback_retry_count = 0;
        mpu_showMsgText();
        mpu_typewriter("對話尚未載入，請稍候...", "#ukagaka_msg");
        mpu_showmsg(400);
        mpuLogger.warn("mpu_nextmsg_fallback: 對話載入超時，已重試 2 次");
      }
      return;
    }

    window.__mpu_fallback_retry_count = 0;

    if (!Array.isArray(store.msg) || store.msg.length === 0) {
      const errorMsg =
        store.msg && store.msg.length === 0
          ? "對話文件為空，請檢查對話文件內容"
          : "訊息列表格式錯誤";
      mpu_typewriter(errorMsg, "#ukagaka_msg");
      mpu_showmsg(400);
      mpuLogger.warn("mpu_nextmsg_fallback: 無法顯示後備對話 -", {
        store: store ? "exists" : "null",
        msgArray:
          store && Array.isArray(store.msg)
            ? `length=${store.msg.length}`
            : "not array",
      });
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
  }, 400);
}

function mpuChange(num) {
  const hasNum = typeof num !== "undefined" && num !== null && num !== "";

  if (hasNum && typeof window.mpuCanvasManager === "undefined") {
    mpu_handle_error("Canvas 管理器未載入", "mpuChange:canvas_manager_check", {
      showToUser: true,
      userMessage: "動畫模組載入失敗，無法切換角色。請刷新頁面後再試。",
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
          mpuLogger.warn(
            "mpuChange: Canvas 管理器在 Ajax 成功後才發現不存在，這不應該發生",
          );
          mpu_handle_error(
            "Canvas 管理器未載入",
            "mpuChange:canvas_manager_fallback",
            {
              showToUser: true,
              userMessage: "動畫模組載入失敗，請刷新頁面。",
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
          window.mpuMsgList =
            typeof payload.msglist === "string"
              ? JSON.parse(payload.msglist)
              : payload.msglist;
        } catch (e) {
          mpu_handle_error(e, "mpuChange:parse_msglist");
          window.mpuMsgList = null;
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
          debugMode || window.mpuDebugMode
            ? `載入失敗: ${error.message}`
            : "載入失敗，請稍後再試。",
      });
      jQuery("#ukagaka").stop(true, true).fadeIn(200);
      mpu_showmsg(200);
      document.body.style.cursor = "auto";
    });
}
