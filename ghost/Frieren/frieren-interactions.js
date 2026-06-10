/**
 * MP Ukagaka 芙莉蓮互動反應模組
 *
 * 擴展 frieren.js 建立的 window.mpuFrierenManager，負責裝飾點擊、
 * 身體 touch zone、互動後狀態恢復與冷卻計數。
 */

(function () {
  "use strict";

  const manager = window.mpuFrierenManager;
  if (!manager) {
    return;
  }

  Object.assign(manager, {
    /**
     * 處理裝飾物點擊事件
     * @param {string} decorationType - 裝飾物類型
     */
    handleDecorationClick: function (decorationType) {
      if (typeof mpuLogger !== "undefined" && mpuLogger.log) {
        mpuLogger.logF("frierenDecorationClickHandled", "handleDecorationClick が呼び出されました。装飾品タイプ：%s", decorationType);
        mpuLogger.log(
          "mpuAiEnabled:",
          typeof mpuAiEnabled !== "undefined" ? mpuAiEnabled : "undefined"
        );
      }

      if (this.decorationChatInProgress) {
        if (typeof mpuLogger !== "undefined" && mpuLogger.log) {
          mpuLogger.logL("frierenDecorationClickIgnoredDialogActive", "装飾品会話中のため、今回のクリックを無視します");
        }
        return;
      }

      if (typeof mpuAiEnabled === "undefined" || !mpuAiEnabled) {
        if (typeof mpuLogger !== "undefined" && mpuLogger.log) {
          mpuLogger.logL("frierenDecorationDialogSkippedAiDisabled", "AI 機能が有効ではないため、装飾品会話をスキップします");
        }
        return;
      }

      this.decorationChatInProgress = true;

      if (typeof window !== "undefined") {
        window.mpuMessageBlocking = true;
      }

      if (typeof stopAutoTalk !== "undefined") {
        stopAutoTalk();
      }

      if (typeof mpuCancelRequest !== "undefined") {
        mpuCancelRequest("", { requestId: "mpu_nextmsg_llm" });
      }
      if (typeof mpuRequestManager !== "undefined") {
        mpuRequestManager.activeRequests.forEach((controller, requestId) => {
          if (requestId.includes("mpu_nextmsg")) {
            controller.abort();
            mpuRequestManager.activeRequests.delete(requestId);
            if (typeof mpuLogger !== "undefined" && mpuLogger.log) {
              mpuLogger.logL("frierenDecorationClickRequestCancelled", "装飾品クリック：リクエストをキャンセルしました");
            }
          }
        });
      }

      if (typeof mpu_cancelTypewriter !== "undefined") {
        mpu_cancelTypewriter();
      }

      if (typeof mpuOllamaRequesting !== "undefined") {
        window.mpuOllamaRequesting = false;
      }
      if (typeof mpuOllamaRequestQueue !== "undefined") {
        window.mpuOllamaRequestQueue = [];
      }

      this.setDecorationsClickable(false);

      const executeAjaxReq = () => {
        const formData = new FormData();
        formData.append("decoration_type", decorationType);

        if (typeof mpuFetch !== "undefined" && typeof mpuRestUrl !== "undefined") {
          mpuFetch(mpuRestUrl + "touch/decoration", {
            method: "POST",
            body: formData,
            timeout: 30000,
            retries: 1,
            requestId: "mpu_decoration_chat_" + decorationType,
          })
            .then((res) => {
              if (jQuery("#ukagaka_msgbox").is(":hidden") && typeof mpu_showmsg !== "undefined") {
                mpu_showmsg(400);
              }

              if (res && res.msg && !res.error) {
                if (typeof mpu_typewriter !== "undefined") {
                  mpu_typewriter(res.msg, "#ukagaka_msg");
                }

                if (this.isFrierenMode) {
                  this.triggerFrierenSpeaking(true);
                }

                if (res.emoji && typeof window.mpuEmojiManager !== "undefined") {
                  window.mpuEmojiManager.showEmoji(res.emoji);
                }

                // 記錄裝飾物對話到歷史，讓互動對話模式能記得此次觸摸反應
                if (typeof window.mpuChatHistory !== "undefined" && Array.isArray(window.mpuChatHistory)) {
                  // synthetic user 錨點：讓 LLM 能在後續對話中看到裝飾物觸摸的完整脈絡
                  window.mpuChatHistory.push({
                    role: "user",
                    content: "（装飾品に触れた）",
                    type: "synthetic",
                    timestamp: Date.now(),
                  });
                  window.mpuChatHistory.push({
                    role: "assistant",
                    content: res.msg,
                    type: "touch_decoration",
                    timestamp: Date.now(),
                  });
                  if (typeof mpu_saveChatHistory === "function") {
                    mpu_saveChatHistory();
                  }
                }

                this.waitForTypewriterAndRestore();
              } else {
                const errorMsg = res?.error || ((window.mpuL10n && window.mpuL10n.errorOccurred) || "エラーが発生しました。後でもう一度お試しください。");
                if (typeof mpu_typewriter !== "undefined") {
                  mpu_typewriter(errorMsg, "#ukagaka_msg");
                }

                this.waitForTypewriterAndRestore();
              }
            })
            .catch((error) => {
              if (jQuery("#ukagaka_msgbox").is(":hidden") && typeof mpu_showmsg !== "undefined") {
                mpu_showmsg(400);
              }

              if (typeof mpuLogger !== "undefined" && mpuLogger.errorL) {
                mpuLogger.errorL('frierenDecorationDialogRequestFailed', '装飾品会話リクエストに失敗しました', error);
              }
              if (typeof mpu_typewriter !== "undefined") {
                mpu_typewriter((window.mpuL10n && window.mpuL10n.connectionError) || "（…通信状況が良くないみたいだ…）", "#ukagaka_msg");
              }

              this.waitForTypewriterAndRestore();
            });
        }
      };

      const $msgbox = jQuery("#ukagaka_msgbox");
      const isAsleep = this.isSleepMessage();

      // 如果處於睡眠模式，觸發喚醒動畫
      if (isAsleep) {
        const wakeThenContinue = () => {
          if (typeof mpu_send_wake_up_request !== "function") {
            executeAjaxReq();
            return;
          }

          mpu_send_wake_up_request()
            .then((reactionDisplayed) => {
              if (!reactionDisplayed) {
                executeAjaxReq();
              } else {
                this.waitForTypewriterAndRestore();
              }
            })
            .catch(() => {
              executeAjaxReq();
            });
        };

        if ($msgbox.is(":visible")) {
          $msgbox.fadeOut(1000, () => {
            this.triggerFrierenSpeaking(true, null, true);
            wakeThenContinue();
          });
        } else {
          this.triggerFrierenSpeaking(true, null, true);
          wakeThenContinue();
        }
      } else {
        // 一般模式：顯示思考中
        mpuShowSystemPlaceholder({ context: "decoration" });
        mpuMarkSystemPlaceholder("#ukagaka_msg"); // §16.3-A：飾品/touch 思考中 placeholder
        if ($msgbox.is(":visible")) {
          $msgbox.fadeOut(400, () => {
            executeAjaxReq();
          });
        } else {
          executeAjaxReq();
        }
      }
    },

    /**
     * 等待打字效果完成後恢復狀態
     */
    waitForTypewriterAndRestore: function () {
      const self = this;

      if (typeof mpu_waitForTypewriterComplete !== "undefined") {
        mpu_waitForTypewriterComplete(function () {
          setTimeout(function () {
            self.restoreAfterDecorationChat();
          }, 2000);
        });
      } else {
        const msgElement = document.querySelector("#ukagaka_msg");
        let estimatedTime = 3000;

        if (msgElement) {
          const msgText = msgElement.textContent || msgElement.innerText || "";
          const typewriterSpeed =
            typeof mpuTypewriterSpeed !== "undefined" && mpuTypewriterSpeed
              ? mpuTypewriterSpeed
              : 40;
          estimatedTime = Math.max(
            2000,
            msgText.length * typewriterSpeed + 500
          );
        }

        setTimeout(function () {
          self.restoreAfterDecorationChat();
        }, estimatedTime);
      }
    },

    /**
     * 恢復裝飾物對話後的狀態
     */
    restoreAfterDecorationChat: function () {
      this.decorationChatInProgress = false;
      if (typeof mpuClearSystemPlaceholder === "function") {
        mpuClearSystemPlaceholder("#ukagaka_msg");
      }

      if (typeof window !== "undefined") {
        window.mpuMessageBlocking = false;
      }

      this.setDecorationsClickable(true);

      if (
        typeof mpuAutoTalk !== "undefined" &&
        mpuAutoTalk &&
        typeof startAutoTalk !== "undefined"
      ) {
        startAutoTalk();
      }

      if (typeof mpuLogger !== "undefined" && mpuLogger.log) {
        mpuLogger.logL("frierenDecorationDialogCompletedStateRestored", "装飾品会話が完了し、状態を復元しました");
      }
    },

    /**
     * 設置裝飾物是否可點擊
     * @param {boolean} clickable - 是否可點擊
     */
    setDecorationsClickable: function (clickable) {
      this.frierenDecorations.forEach((decoration) => {
        if (decoration && decoration.style) {
          if (clickable) {
            decoration.style.pointerEvents = "auto";
            decoration.style.cursor = "pointer";
            decoration.style.opacity = "1.0";
          } else {
            decoration.style.pointerEvents = "none";
            decoration.style.cursor = "not-allowed";
            decoration.style.opacity = "1.0";
          }
        }
      });
    },

    /**
     * 檢測觸摸區域
     * @param {MouseEvent} event - 滑鼠事件
     * @param {HTMLElement} element - 被點擊的元素
     * @returns {string|null} - 區域名稱或 null
     */
    detectTouchZone: function (event, element) {
      if (
        !element ||
        typeof mpuTouchZones === "undefined" ||
        !mpuTouchZones.zones
      ) {
        return null;
      }

      const rect = element.getBoundingClientRect();
      const clickY = event.clientY - rect.top;
      const relativeY = clickY / rect.height;

      for (const [zoneName, zone] of Object.entries(mpuTouchZones.zones)) {
        if (zoneName.startsWith("_")) continue;
        if (relativeY >= zone.yStart && relativeY < zone.yEnd) {
          if (typeof mpuLogger !== "undefined" && mpuLogger.log) {
            mpuLogger.logF("frierenTouchZoneDetected", "タッチ領域検出：%1$s、relativeY=%2$s", zoneName, relativeY.toFixed(2));
          }
          return zoneName;
        }
      }

      return mpuTouchZones.default_reaction ? "body" : null;
    },

    /**
     * 處理角色觸摸事件
     * @param {string} zoneName - 區域名稱
     */
    handleTouchZone: function (zoneName) {
      if (typeof mpuLogger !== "undefined" && mpuLogger.log) {
        mpuLogger.logF("frierenTouchZoneHandled", "handleTouchZone が呼び出されました。領域：%s", zoneName);
      }

      if (this.decorationChatInProgress) {
        return;
      }

      if (this.isZoneInCooldown(zoneName)) {
        if (typeof mpuLogger !== "undefined" && mpuLogger.log) {
          mpuLogger.logF("frierenTouchZoneClickIgnoredCooldown", "領域がクールダウン中のため、クリックを無視します：%s", zoneName);
        }
        return;
      }

      if (this.recordZoneClick(zoneName)) {
        if (typeof mpuLogger !== "undefined" && mpuLogger.log) {
          mpuLogger.logF("frierenTouchZoneClickLimitReached", "領域のクリック上限に達したため、クールダウンに入ります：%s", zoneName);
        }
        return;
      }

      if (typeof mpuAiEnabled === "undefined" || !mpuAiEnabled) {
        return;
      }

      this.decorationChatInProgress = true;

      if (typeof window !== "undefined") {
        window.mpuMessageBlocking = true;
      }

      if (typeof stopAutoTalk !== "undefined") {
        stopAutoTalk();
      }

      if (typeof mpuCancelRequest !== "undefined") {
        mpuCancelRequest("", { requestId: "mpu_nextmsg_llm" });
      }
      if (typeof mpuRequestManager !== "undefined") {
        mpuRequestManager.activeRequests.forEach((controller, requestId) => {
          if (requestId.includes("mpu_nextmsg")) {
            controller.abort();
            mpuRequestManager.activeRequests.delete(requestId);
            if (typeof mpuLogger !== "undefined" && mpuLogger.log) {
              mpuLogger.logL("frierenTouchZoneRequestCancelled", "タッチ領域クリック：リクエストをキャンセルしました");
            }
          }
        });
      }

      if (typeof mpu_cancelTypewriter !== "undefined") {
        mpu_cancelTypewriter();
      }

      if (typeof mpuOllamaRequesting !== "undefined") {
        window.mpuOllamaRequesting = false;
      }
      if (typeof mpuOllamaRequestQueue !== "undefined") {
        window.mpuOllamaRequestQueue = [];
      }

      const self = this;

      const executeAjaxReq = () => {
        const formData = new FormData();
        formData.append("touch_zone", zoneName);

        if (typeof mpuFetch !== "undefined" && typeof mpuRestUrl !== "undefined") {
          mpuFetch(mpuRestUrl + "touch/zone", {
            method: "POST",
            body: formData,
            timeout: 30000,
            retries: 1,
            requestId: "mpu_touch_zone_" + zoneName,
          })
            .then((res) => {
              if (jQuery("#ukagaka_msgbox").is(":hidden") && typeof mpu_showmsg !== "undefined") {
                mpu_showmsg(400);
              }

              if (res && res.msg && !res.error) {
                if (typeof mpu_typewriter !== "undefined") {
                  mpu_typewriter(res.msg, "#ukagaka_msg");
                }

                if (self.isFrierenMode) {
                  self.triggerFrierenSpeaking(true);
                }

                if (res.emoji && typeof mpuEmojiManager !== "undefined") {
                  mpuEmojiManager.showEmoji(res.emoji);
                }

                // 記錄身體觸摸對話到歷史，讓互動對話模式能記得此次觸摸反應
                if (typeof window.mpuChatHistory !== "undefined" && Array.isArray(window.mpuChatHistory)) {
                  // synthetic user 錨點：讓 LLM 能在後續對話中看到身體觸摸的完整脈絡
                  window.mpuChatHistory.push({
                    role: "user",
                    content: "（芙莉蓮の体に触れた）",
                    type: "synthetic",
                    timestamp: Date.now(),
                  });
                  window.mpuChatHistory.push({
                    role: "assistant",
                    content: res.msg,
                    type: "touch_zone",
                    timestamp: Date.now(),
                  });
                  if (typeof mpu_saveChatHistory === "function") {
                    mpu_saveChatHistory();
                  }
                }
              }

              self.scheduleRestoreAfterChat();
            })
            .catch((err) => {
              if (jQuery("#ukagaka_msgbox").is(":hidden") && typeof mpu_showmsg !== "undefined") {
                mpu_showmsg(400);
              }

              mpuLogger.errorL('frierenTouchZoneDialogRequestFailed', 'タッチ領域の会話リクエストに失敗しました', err);
              self.restoreAfterDecorationChat();
            });
        }
      };

      const $msgbox = jQuery("#ukagaka_msgbox");
      const isAsleep = this.isSleepMessage();

      // 如果處於睡眠模式，觸發喚醒動畫
      if (isAsleep) {
        const wakeThenContinue = () => {
          if (typeof mpu_send_wake_up_request !== "function") {
            executeAjaxReq();
            return;
          }

          mpu_send_wake_up_request()
            .then((reactionDisplayed) => {
              if (!reactionDisplayed) {
                executeAjaxReq();
              } else {
                self.waitForTypewriterAndRestore();
              }
            })
            .catch(() => {
              executeAjaxReq();
            });
        };

        if ($msgbox.is(":visible")) {
          $msgbox.fadeOut(1000, () => {
            self.triggerFrierenSpeaking(true, null, true);
            wakeThenContinue();
          });
        } else {
          self.triggerFrierenSpeaking(true, null, true);
          wakeThenContinue();
        }
      } else {
        // 一般模式：顯示思考中
        mpuShowSystemPlaceholder({ context: "touch" });
        mpuMarkSystemPlaceholder("#ukagaka_msg"); // §16.3-A：飾品/touch 思考中 placeholder
        if ($msgbox.is(":visible")) {
          $msgbox.fadeOut(400, () => {
            executeAjaxReq();
          });
        } else {
          executeAjaxReq();
        }
      }
    },

    /**
     * 設置角色觸摸事件監聽
     */
    setupCharacterTouchEvents: function () {
      const imgContainer = document.getElementById("ukagaka_img");
      if (!imgContainer) return;

      const self = this;

      imgContainer.addEventListener("mousemove", function (e) {
        const target = e.target;

        if (target.id !== "frieren_idle_apng" && target.id !== "cur_ukagaka") {
          return;
        }

        const zone = self.detectTouchZone(e, target);
        if (zone) {
          const cursorMap = {
            head: "grab",
            face: "pointer",
            chest: "not-allowed",
            book: "help",
            legs: "pointer",
          };
          target.style.cursor = cursorMap[zone] || "pointer";
        } else {
          target.style.cursor = "default";
        }
      });

      imgContainer.addEventListener(
        "click",
        function (e) {
          const target = e.target;

          if (
            target.id !== "frieren_idle_apng" &&
            target.id !== "cur_ukagaka"
          ) {
            return;
          }

          const zone = self.detectTouchZone(e, target);
          if (zone) {
            e.stopPropagation();
            e.preventDefault();
            self.handleTouchZone(zone);
          }
        },
        true
      );

      if (typeof mpuLogger !== "undefined" && mpuLogger.log) {
        mpuLogger.logL("frierenTouchEventsBound", "キャラクターのタッチイベントを設定しました");
      }
    },

    /**
     * 延遲恢復對話完成後的狀態
     */
    scheduleRestoreAfterChat: function () {
      const self = this;

      if (typeof mpu_waitForTypewriterComplete !== "undefined") {
        mpu_waitForTypewriterComplete(function () {
          setTimeout(function () {
            self.restoreAfterDecorationChat();
          }, 2000);
        });
      } else {
        setTimeout(function () {
          self.restoreAfterDecorationChat();
        }, 3000);
      }
    },

    /**
     * 檢查區域是否在冷卻中
     * @param {string} zoneName - 區域名稱
     * @returns {boolean} - 是否在冷卻中
     */
    isZoneInCooldown: function (zoneName) {
      const cooldownEnd = this.touchZoneCooldown[zoneName];
      if (!cooldownEnd) return false;

      const now = Date.now();
      if (now < cooldownEnd) {
        return true;
      }

      delete this.touchZoneCooldown[zoneName];
      delete this.touchZoneClicks[zoneName];
      return false;
    },

    /**
     * 記錄區域點擊，並檢查是否需要進入冷卻
     * @param {string} zoneName - 區域名稱
     * @returns {boolean} - 是否剛進入冷卻（true = 應該忽略這次點擊）
     */
    recordZoneClick: function (zoneName) {
      const limit = this.touchZoneLimits[zoneName];
      if (!limit) return false;

      const now = Date.now();

      if (!this.touchZoneClicks[zoneName]) {
        this.touchZoneClicks[zoneName] = [];
      }

      this.touchZoneClicks[zoneName] = this.touchZoneClicks[zoneName].filter(
        (timestamp) => now - timestamp < limit.windowMs
      );

      this.touchZoneClicks[zoneName].push(now);

      if (this.touchZoneClicks[zoneName].length >= limit.maxClicks) {
        this.touchZoneCooldown[zoneName] = now + limit.cooldownMs;

        if (typeof mpuLogger !== "undefined" && mpuLogger.log) {
          mpuLogger.logF("frierenTouchZoneCooldownStarted", "領域がクールダウンに入りました：%1$s、クールダウン時間：%2$s 秒", zoneName, limit.cooldownMs / 1000);
        }

        return true;
      }

      return false;
    },
  });
})();
