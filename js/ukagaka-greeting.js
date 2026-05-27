
/**
 * 首次訪客打招呼：根據訪客資訊生成個性化問候語
 * @param {Object} settings - 設定物件，包含 auto_talk 等選項
 * @returns {Promise} 返回 Promise，完成時表示打招呼流程結束
 */
function mpu_greet_first_visitor(settings) {
  return new Promise((resolve, reject) => {
    // 🌙 睡眠模式檢查：讓芙莉蓮好好睡覺，不打擾訪客
    const isDeepSleep = mpu_isDeepSleepTime();
    if (isDeepSleep) {
      mpuLogger.logL("greetingSkippedSleepMode", "🌙 睡眠モード：初回訪問者への挨拶をスキップし、キャラクターを休ませます");
      resolve();
      return;
    }

    // 立即暫停自動對話，防止被打岔
    const wasAutoTalkRunning = mpuAutoTalkTimer !== null;
    if (wasAutoTalkRunning) {
      stopAutoTalk();
    }

    // 先獲取訪客資訊
    const visitorUrl = `${mpuRestUrl}visitor-info`;

    mpuFetch(visitorUrl, {
      timeout: 10000, // 10 秒超時
      retries: 1,
    })
      .then((visitorInfo) => {
        // 調試模式：記錄訪客資訊
        mpuLogger.logL("greetingVisitorInfo", "訪問者情報：", {
          referrer: visitorInfo.referrer || "無",
          referrer_host: visitorInfo.referrer_host || "無",
          search_engine: visitorInfo.search_engine || "無",
          country: visitorInfo.slimstat_country || "無",
        });

        // 顯示載入訊息
        if (jQuery("#ukagaka_msgbox").is(":hidden")) mpu_showmsg(200);
        const loadingMessage =
          typeof mpuL10n !== "undefined" && mpuL10n.unknownVisitor
            ? mpuL10n.unknownVisitor
            : "（…あ、知らない人間だ…）";
        mpu_typewriter(
          `<span style="color: ${mpuAiTextColor};">${loadingMessage}</span>`,
          "#ukagaka_msg",
        );

        const formData = new FormData();
        formData.append("referrer", visitorInfo.referrer || "");
        formData.append("referrer_host", visitorInfo.referrer_host || "");
        formData.append("search_engine", visitorInfo.search_engine || "");
        formData.append(
          "is_direct",
          visitorInfo.is_direct === true ? "true" : "false",
        );
        formData.append(
          "country",
          visitorInfo.slimstat_country || visitorInfo.country || "",
        );
        formData.append(
          "city",
          visitorInfo.slimstat_city || visitorInfo.city || "",
        );

        // [Fix] 傳送 session_id + history，讓後端記錄問候的 checksum
        const greetSessionId = typeof mpu_getOrCreateChatSessionId === "function"
          ? mpu_getOrCreateChatSessionId() : "";
        if (greetSessionId) {
          formData.append("session_id", greetSessionId);
        }
        if (typeof window.mpuChatHistory !== "undefined" && window.mpuChatHistory.length > 0) {
          formData.append("history", JSON.stringify(window.mpuChatHistory.slice(-10)));
        }

        return mpuFetch(mpuRestUrl + "chat/greet", {
          method: "POST",
          body: formData,
          cancelPrevious: true,
          requestId: "mpu_chat_greet",
          timeout: 60000,
          retries: 1,
        });
      })
      .then((res) => {
        if (res && res.msg && !res.error) {
          let greetingMessage = mpu_unescapeHTML(res.msg);
          greetingMessage = mpu_linkifyUrls(greetingMessage);

          mpu_typewriter(
            `<span style="color: ${mpuAiTextColor};">${greetingMessage}</span>`,
            "#ukagaka_msg",
          );

          // 觸發角色動畫（訪客問候是使用者觸發，強制播放動畫）
          if (
            typeof window.mpuCanvasManager !== "undefined" &&
            window.mpuCanvasManager.isCharacterMode
          ) {
            window.mpuCanvasManager.triggerCharacterAnimation(true);
          }

          // 顯示表情（如果有的話）
          if (res.emoji && typeof window.mpuEmojiManager !== "undefined") {
            window.mpuEmojiManager.showEmoji(res.emoji);
          }

          // 將自發對話加入對話歷史，讓用戶開對話模式時 AI 記得剛才說過什麼
          if (
            typeof window.mpuChatHistory !== "undefined" &&
            Array.isArray(window.mpuChatHistory)
          ) {
            // synthetic user 錨點：讓 LLM 能在後續對話中看到初次問候的完整脈絡
            window.mpuChatHistory.push({
              role: "user",
              content: "（新しい訪客が来た）",
              type: "synthetic",
              timestamp: Date.now(),
            });
            window.mpuChatHistory.push({
              role: "assistant",
              content: res.msg,
              type: "greet",
              timestamp: Date.now(),
            });

            if (typeof mpu_saveChatHistory === "function") {
              mpu_saveChatHistory();
              mpuLogger.logL("greetingSavedToHistory", "mpu_greet_first_visitor: 挨拶を履歴に追加して保存しました");
            } else {
              mpuLogger.warnL("greetingSaveHistoryFunctionMissing", "mpu_greet_first_visitor: mpu_saveChatHistory 関数が存在しないため、会話履歴を保存できません");
            }
          } else {
            mpuLogger.warnL("greetingChatHistoryUnavailable", "mpu_greet_first_visitor: window.mpuChatHistory が初期化されていないか配列ではないため、会話履歴に追加できません");
          }

          // 🔧 計時邏輯：打字完成 → displayDuration → autoTalkInterval
          if (mpuAiDisplayTimer !== null) {
            clearTimeout(mpuAiDisplayTimer);
            mpuSetAiDisplayTimer(null);
          }

          mpu_waitForTypewriterComplete(function () {
            // 打字完成後，開始 displayDuration 計時
            const displayDurationMs = mpuAiDisplayDuration * 1000;
            mpuSetAiDisplayTimer(setTimeout(function () {
              mpuSetAiDisplayTimer(null);
              if (
                wasAutoTalkRunning &&
                settings.auto_talk === true &&
                mpuAutoTalk
              ) {
                startAutoTalk();
              }
              resolve();
            }, displayDurationMs));
          });
        } else {
          mpuLogger.warnF("greetingFirstVisitorFailed", "初回訪問者への挨拶に失敗しました：%s", res);

          // 檢查是否是速率限制錯誤（請求過於頻繁）
          const isRateLimit =
            (res && res.error && (res.error.includes("請求過於頻繁") || res.error.includes("リクエストが多すぎます"))) ||
            (res && res.code === "rest_rate_limit_exceeded");

          if (isRateLimit) {
            const rateLimitMessage =
              typeof mpuL10n !== "undefined" && mpuL10n.apiMagicInsufficient
                ? mpuL10n.apiMagicInsufficient
                : "…ちょっと待って。API魔力が足りない";
            mpu_typewriter(
              `<span style="color: ${mpuAiTextColor};">${rateLimitMessage}</span>`,
              "#ukagaka_msg",
            );

            mpuSetMessageBlocking(true);
            const waitTime = (mpuAiDisplayDuration || 8) * 1000;

            setTimeout(function () {
              mpuSetMessageBlocking(false);

              const dialogStore = mpuGetDialogStore();
              if (
                dialogStore &&
                Array.isArray(dialogStore.msg) &&
                dialogStore.msg.length > 0
              ) {
                const msgArr = dialogStore.msg;
                const auto = dialogStore.auto_msg || "";
                const randomIdx = Math.floor(Math.random() * msgArr.length);
                mpu_typewriter(
                  mpu_unescapeHTML(msgArr[randomIdx] + auto),
                  "#ukagaka_msg",
                );
              }
              if (
                wasAutoTalkRunning &&
                settings.auto_talk === true &&
                mpuAutoTalk
              ) {
                startAutoTalk();
              }
              resolve();
            }, waitTime);
          } else {
            const dialogStore = mpuGetDialogStore();
            if (
              dialogStore &&
              Array.isArray(dialogStore.msg) &&
              dialogStore.msg.length > 0
            ) {
              const msgArr = dialogStore.msg;
              const auto = dialogStore.auto_msg || "";
              const randomIdx = Math.floor(Math.random() * msgArr.length);
              mpu_typewriter(
                mpu_unescapeHTML(msgArr[randomIdx] + auto),
                "#ukagaka_msg",
              );
            }
            if (
              wasAutoTalkRunning &&
              settings.auto_talk === true &&
              mpuAutoTalk
            ) {
              startAutoTalk();
            }
            resolve();
          }
        }
      })
      .catch((error) => {
        mpu_handle_error(error, "mpu_greet_first_visitor", {
          showToUser: false,
        });

        const dialogStore = mpuGetDialogStore();
        if (
          dialogStore &&
          Array.isArray(dialogStore.msg) &&
          dialogStore.msg.length > 0
        ) {
          const msgArr = dialogStore.msg;
          const auto = dialogStore.auto_msg || "";
          const randomIdx = Math.floor(Math.random() * msgArr.length);
          mpu_typewriter(
            mpu_unescapeHTML(msgArr[randomIdx] + auto),
            "#ukagaka_msg",
          );
        }

        if (wasAutoTalkRunning && settings.auto_talk === true && mpuAutoTalk) {
          startAutoTalk();
        }
        resolve();
      });
  });
}
