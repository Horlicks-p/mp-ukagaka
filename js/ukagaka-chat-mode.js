/**
 * 切換對話模式
 * @param {boolean} enable - 是否啟用對話模式
 */
function mpu_toggleChatMode(enable) {
  const $msgbox = jQuery("#ukagaka_msgbox");
  const $chatInput = jQuery("#ukagaka_chat_input");
  const $input = jQuery("#mpu_user_input");

  if (typeof enable === "undefined") {
    enable = !window.mpuChatModeActive;
  }

  window.mpuChatModeActive = enable;
  window.mpuChatGeneration += 1;

  if (enable) {
    // 進入對話模式
    mpuLogger.logL("chatModeEntered", "インタラクティブ会話モードに入りました");

    // 自動台詞送出時會把訊息框淡出（600ms）。淡出途中框仍算可見，下面的
    // 「隱藏就顯示」判斷會落空，淡出結束後聊天框就看不見了。先讓進行中的
    // 動畫直接跑完，再依實際狀態顯示。
    $msgbox.stop(true, true);

    // 離開聊天時設下的阻擋屬於已關閉的那次聊天。5 秒內重新進入時，離開的計時器
    // 因世代不同而不再動作，所以由這裡釋放；否則阻擋會留到下次離開為止，OK 鈕與
    // 送禮都會被擋住。只在沒有其他互動持有阻擋時才解除。
    if (window.mpuChatExitBlocking) {
      window.mpuChatExitBlocking = false;
      if (!mpuAiContextInProgress && !mpuGreetInProgress && !mpuIsInteractionDialogActive()) {
        mpuSetMessageBlocking(false);
      }
    }

    // 暫停自動對話
    if (mpuAutoTalkTimer !== null) {
      stopAutoTalk();
    }

    // 載入對話歷史（用於上下文，但不顯示）
    mpu_loadChatHistory();

    // 觸發角色喚醒動畫（睡眠模式時會先喚醒）
    if (
      typeof window.mpuCanvasManager !== "undefined" &&
      window.mpuCanvasManager.isCharacterMode
    ) {
      // 檢查是否為睡眠模式（需要喚醒動畫）- 使用通用函數
      const needsWakeUp =
        typeof mpu_isUnawokenSleepMode === "function" &&
        mpu_isUnawokenSleepMode();

      if (needsWakeUp) {
        // 檢查是否有喚醒動畫文件（通用方法，支援各種角色管理器）
        const hasWakeUpAnimation =
          typeof window.mpuCanvasManager !== "undefined" &&
          typeof window.mpuCanvasManager.hasWakeUpAnimation === "function" &&
          window.mpuCanvasManager.hasWakeUpAnimation();
        const showChatAfterWake = function (reactionDisplayed) {
          $msgbox.addClass("chat-mode");
          $chatInput.slideDown(400, function () {
            if (!reactionDisplayed) {
              showWelcome();
            } else if ($msgbox.is(":hidden")) {
              mpu_showmsg(400);
            }
            setTimeout(() => $input.focus(), 250);
          });
        };
        const requestWakeThenShowChat = function () {
          if (typeof mpu_send_wake_up_request !== "function") {
            showChatAfterWake(false);
            return;
          }
          mpu_send_wake_up_request()
            .then(showChatAfterWake)
            .catch(function () {
              showChatAfterWake(false);
            });
        };

        if (hasWakeUpAnimation) {
          window.mpuForceWakeUpNextTime = true;
          // 有喚醒動畫：先淡出對話框（隱藏 ZZZ），等待喚醒動畫完成後再顯示
          $msgbox.fadeOut(1000, function () {
            // 在對話框隱藏後，開始喚醒動畫（skipBookFlip = true：不翻書）
            window.mpuCanvasManager.triggerCharacterAnimation(
              true,
              function () {
                // 喚醒動畫完成後，再取得被喚醒的第一句反應
                requestWakeThenShowChat();
              },
              true,
            ); // skipBookFlip = true：開啟對話時不翻書
          });
        } else {
          // 沒有喚醒動畫：直接取得被喚醒的第一句反應
          requestWakeThenShowChat();
        }
      } else {
        // 非睡眠模式：正常流程（不觸發動畫，只在回答問題時播放）
        $chatInput.slideDown(400);
        $msgbox.addClass("chat-mode");

        // 直接顯示歡迎訊息，不播放動畫
        showWelcome();
      }
    } else {
      // 非角色動畫模式：顯示輸入框並直接顯示歡迎訊息
      $chatInput.slideDown(400);
      $msgbox.addClass("chat-mode");
      showWelcome();
    }

    function showWelcome() {
      // 顯示歡迎訊息（不觸發動畫，只在回答問題時播放）
      const welcomeMsg =
        typeof mpuL10n !== "undefined" && mpuL10n.chatWelcome
          ? mpuL10n.chatWelcome
          : "有什麼想聊的嗎？";
      mpu_typewriter(welcomeMsg, "#ukagaka_msg", null, true); // true = skipCharacterAnimation

      // 確保對話框可見
      if ($msgbox.is(":hidden")) {
        mpu_showmsg(400);
      }

      // 聚焦輸入框
      setTimeout(() => $input.focus(), 250);
    }
  } else {
    // 退出對話模式
    mpuLogger.logL("chatModeExited", "インタラクティブ会話モードを終了しました");

    // 隱藏輸入框
    $chatInput.slideUp(400);
    $msgbox.removeClass("chat-mode");

    // 設置訊息阻擋，防止退出後立即說話
    mpuSetMessageBlocking(true);
    window.mpuChatExitBlocking = true;

    // 顯示「結束對話」的訊息（不觸發動畫，只在回答問題時播放）
    const exitMsg =
      typeof mpuL10n !== "undefined" && mpuL10n.chatExit
        ? mpuL10n.chatExit
        : "……";
    // 使用 skipAnimation 參數來跳過動畫
    mpu_typewriter(exitMsg, "#ukagaka_msg", null, true); // true = skipCharacterAnimation

    // 延遲 5 秒後恢復正常狀態。5 秒內又開關過聊天時，只有最後一次離開的計時器有效：
    // 否則前一次的計時器會提早解除阻擋，並多說一句離開台詞。
    const exitGeneration = window.mpuChatGeneration;
    setTimeout(() => {
      if (exitGeneration === window.mpuChatGeneration) {
        window.mpuChatExitBlocking = false;
        mpuSetMessageBlocking(false);

        // 顯示一條隨機對話
        const store = mpuGetDialogStore();
        if (
          store &&
          Array.isArray(store.msg) &&
          store.msg.length > 0
        ) {
          const msgArr = store.msg;
          const auto = store.auto_msg || "";
          const randomIdx = Math.floor(Math.random() * msgArr.length);
          const exitContent = mpu_unescapeHTML(msgArr[randomIdx] + auto);
          mpu_typewriter(exitContent, "#ukagaka_msg");
          // 將隨機對話加入歷史，確保下次開啟互動對話模式有完整脈絡
          if (exitContent && Array.isArray(window.mpuChatHistory)) {
            window.mpuChatHistory.push({ role: "user", content: "（独り言）", type: "synthetic", timestamp: Date.now() });
            window.mpuChatHistory.push({ role: "assistant", content: exitContent, type: "auto_talk", timestamp: Date.now() });
            if (typeof mpu_saveChatHistory === "function") mpu_saveChatHistory();
          }
        }

        // 恢復自動對話
        if (mpuAutoTalk) {
          startAutoTalk();
        }
      }
    }, 5000);
  }
}
