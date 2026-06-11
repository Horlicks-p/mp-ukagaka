// 綁定對話模式事件
jQuery(document).ready(function () {
  // 初始化：載入對話歷史
  if (typeof mpu_loadChatHistory === "function") {
    mpu_loadChatHistory();
    mpu_getOrCreateChatSessionId();
    mpuLogger.logF("chatHistoryLoadedOnPageLoad", "ページ読み込み：会話履歴を読み込みました。現在の記録数：%s", window.mpuChatHistory.length);
  }

  // 第三個按鈕點擊事件：根據設定決定是對話還是切換春菜
  jQuery("#mpu_chat_toggle").on("click", function (e) {
    e.preventDefault();
    if (mpuIsChatModeEnabled()) {
      // 啟用互動對話模式
      mpu_toggleChatMode();
    } else {
      // 執行原本的角色切換功能
      if (typeof mpuChange === "function") {
        mpuChange("");
      }
    }
  });

  // 輸入框 Enter 鍵發送
  jQuery("#mpu_user_input").on("keypress", function (e) {
    if (e.which === 13 && !e.shiftKey) {
      e.preventDefault();
      mpu_sendUserMessage();
    }
  });

  // OK 按鈕（✅）：對話模式中送出訊息，一般模式下一句
  jQuery("#mpu_ok_btn").on("click", function (e) {
    e.preventDefault();

    // 檢查是否正在處理裝飾物對話
    if (
      typeof window.mpuCanvasManager !== "undefined" &&
      window.mpuCanvasManager.decorationChatInProgress
    ) {
      mpuLogger.logL("chatButtonIgnoredDecorationDialogActive", "装飾品会話中のため、ボタンクリックを無視します");
      return;
    }

    // 檢查訊息是否被阻擋
    if (mpuMessageBlocking) {
      mpuLogger.logL("chatButtonIgnoredMessageBlocking", "メッセージがブロックされているため、ボタンクリックを無視します");
      return;
    }

    var handleOkAction = function () {
      if (mpuChatModeActive) {
        mpu_sendUserMessage();
      } else {
        mpu_nextmsg("");
      }
    };

    // 若處於睡眠且尚未喚醒，先播放喚醒動畫再送出（無論是否在對話模式）
    var needsWakeUp =
      typeof mpu_isUnawokenSleepMode === "function" &&
      mpu_isUnawokenSleepMode();
    var hasWakeUpAnimation =
      typeof window.mpuCanvasManager !== "undefined" &&
      window.mpuCanvasManager.isCharacterMode &&
      typeof window.mpuCanvasManager.hasWakeUpAnimation === "function" &&
      window.mpuCanvasManager.hasWakeUpAnimation();
    var handleWakeThenOkAction = function () {
      if (typeof mpu_send_wake_up_request !== "function") {
        handleOkAction();
        return;
      }
      mpu_send_wake_up_request()
        .then(function (reactionDisplayed) {
          if (!reactionDisplayed) {
            handleOkAction();
          }
        })
        .catch(function () {
          handleOkAction();
        });
    };

    if (needsWakeUp) {
      if (!hasWakeUpAnimation) {
        handleWakeThenOkAction();
        return;
      }

      const $msgbox = jQuery("#ukagaka_msgbox");
      window.mpuForceWakeUpNextTime = true;
      // 有喚醒動畫：先淡出對話框（隱藏 ZZZ），等待喚醒動畫完成後再顯示
      $msgbox.fadeOut(1000, function () {
        // 在對話框隱藏後，開始喚醒動畫（skipBookFlip = true：不翻書）
        window.mpuCanvasManager.triggerCharacterAnimation(
          true,
          function () {
            window.mpuSkipNextManualBookFlip = true;
            // chat 模式下 handleOkAction 會走 mpu_sendUserMessage，不會消費此旗標；
            // 設一個 8 秒上限的後備清理，避免旗標殘留影響後續手動互動。
            const expireToken = Date.now();
            window.mpuSkipBookFlipExpireToken = expireToken;
            setTimeout(function () {
              if (window.mpuSkipBookFlipExpireToken === expireToken) {
                window.mpuSkipNextManualBookFlip = false;
                window.mpuSkipBookFlipExpireToken = null;
              }
            }, 8000);
            handleWakeThenOkAction();
          },
          true
        );
      });
      return;
    }

    handleOkAction();
  });

  // Cancel 按鈕（❌）：對話模式中退出對話，一般模式隱藏對話框
  jQuery("#mpu_cancel_btn").on("click", function (e) {
    e.preventDefault();

    // 對話模式中：cancel 直接退出，不受 mpuMessageBlocking 阻擋
    // （mpuMessageBlocking 是阻擋自動對話切換用，不應阻擋使用者主動退出）
    if (mpuChatModeActive) {
      mpu_toggleChatMode(false);
      mpuLogger.logL("chatModeExitRequested", "会話モードを終了します");
      return;
    }

    // 非對話模式：檢查是否正在處理裝飾物對話
    if (
      typeof window.mpuCanvasManager !== "undefined" &&
      window.mpuCanvasManager.decorationChatInProgress
    ) {
      mpuLogger.logL("chatButtonIgnoredDecorationDialogActive", "装飾品会話中のため、ボタンクリックを無視します");
      return;
    }

    // 非對話模式：檢查訊息是否被阻擋
    if (mpuMessageBlocking) {
      mpuLogger.logL("chatButtonIgnoredMessageBlocking", "メッセージがブロックされているため、ボタンクリックを無視します");
      return;
    }

    mpu_hidemsg("");
  });

  mpuLogger.logL("chatModeInitialized", "インタラクティブ会話モードを初期化しました");
});
