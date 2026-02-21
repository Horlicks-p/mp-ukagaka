
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
      mpuLogger.log("🌙 睡眠模式：跳過初次訪客打招呼，讓角色好好休息");
      resolve();
      return;
    }

    // 立即暫停自動對話，防止被打岔
    const wasAutoTalkRunning = mpuAutoTalkTimer !== null;
    if (wasAutoTalkRunning) {
      stopAutoTalk();
    }

    // 先獲取訪客資訊
    const visitorParams = new URLSearchParams({
      action: "mpu_get_visitor_info",
    });
    const visitorUrl = `${mpuurl}?${visitorParams.toString()}`;

    mpuFetch(visitorUrl, {
      timeout: 10000, // 10 秒超時
      retries: 1,
    })
      .then((visitorInfo) => {
        // 調試模式：記錄訪客資訊
        mpuLogger.log("訪客資訊:", {
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
        formData.append("action", "mpu_chat_greet");
        if (typeof mpuNonce !== "undefined" && mpuNonce) {
          formData.append("mpu_nonce", mpuNonce);
        }
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

        return mpuFetch(mpuurl, {
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
            typeof mpuChatHistory !== "undefined" &&
            Array.isArray(mpuChatHistory)
          ) {
            mpuChatHistory.push({
              role: "assistant",
              content: res.msg,
              timestamp: Date.now(),
            });

            // 限制最多保留 3 條自發對話
            const maxAutoTalkHistory = 3;
            const assistantMessages = mpuChatHistory.filter(
              (msg) => msg.role === "assistant",
            );
            if (assistantMessages.length > maxAutoTalkHistory) {
              const toRemove = assistantMessages.length - maxAutoTalkHistory;
              let removed = 0;
              mpuChatHistory = mpuChatHistory.filter((msg) => {
                if (msg.role === "assistant" && removed < toRemove) {
                  removed++;
                  return false;
                }
                return true;
              });
            }

            if (typeof mpu_saveChatHistory === "function") {
              mpu_saveChatHistory();
              mpuLogger.log("mpu_greet_first_visitor: 問候已加入歷史並儲存");
            } else {
              mpuLogger.warn(
                "mpu_greet_first_visitor: mpu_saveChatHistory 函數不存在，無法儲存對話歷史",
              );
            }
          } else {
            mpuLogger.warn(
              "mpu_greet_first_visitor: mpuChatHistory 未初始化或不是陣列，無法加入對話歷史",
            );
          }

          // 🔧 計時邏輯：打字完成 → displayDuration → autoTalkInterval
          if (mpuAiDisplayTimer !== null) {
            clearTimeout(mpuAiDisplayTimer);
            mpuAiDisplayTimer = null;
          }

          mpu_waitForTypewriterComplete(function () {
            // 打字完成後，開始 displayDuration 計時
            const displayDurationMs = mpuAiDisplayDuration * 1000;
            mpuAiDisplayTimer = setTimeout(function () {
              mpuAiDisplayTimer = null;
              if (
                wasAutoTalkRunning &&
                settings.auto_talk === true &&
                mpuAutoTalk
              ) {
                startAutoTalk();
              }
              resolve();
            }, displayDurationMs);
          });
        } else {
          mpuLogger.warn("首次訪客打招呼失敗:", res);

          // 檢查是否是速率限制錯誤
          const isRateLimit =
            res && res.error && res.error.includes("請求過於頻繁");

          if (isRateLimit) {
            const rateLimitMessage =
              typeof mpuL10n !== "undefined" && mpuL10n.apiMagicInsufficient
                ? mpuL10n.apiMagicInsufficient
                : "…ちょっと待って。API魔力が足りない";
            mpu_typewriter(
              `<span style="color: ${mpuAiTextColor};">${rateLimitMessage}</span>`,
              "#ukagaka_msg",
            );

            mpuMessageBlocking = true;
            const waitTime = (mpuAiDisplayDuration || 8) * 1000;

            setTimeout(function () {
              mpuMessageBlocking = false;

              if (
                window.mpuMsgList &&
                Array.isArray(window.mpuMsgList.msg) &&
                window.mpuMsgList.msg.length > 0
              ) {
                const msgArr = window.mpuMsgList.msg;
                const auto = window.mpuMsgList.auto_msg || "";
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
            if (
              window.mpuMsgList &&
              Array.isArray(window.mpuMsgList.msg) &&
              window.mpuMsgList.msg.length > 0
            ) {
              const msgArr = window.mpuMsgList.msg;
              const auto = window.mpuMsgList.auto_msg || "";
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

        if (
          window.mpuMsgList &&
          Array.isArray(window.mpuMsgList.msg) &&
          window.mpuMsgList.msg.length > 0
        ) {
          const msgArr = window.mpuMsgList.msg;
          const auto = window.mpuMsgList.auto_msg || "";
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
