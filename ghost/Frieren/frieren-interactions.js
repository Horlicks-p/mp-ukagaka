/**
 * MP Ukagaka 芙莉蓮互動反應模組
 *
 * 擴展 frieren.js 建立的 window.mpuFrierenManager，負責裝飾點擊、
 * 身體 touch zone、互動後狀態恢復與冷卻計數。
 */

(function () {
  "use strict";

  function warnFrieren(key, fallback, ...args) {
    if (typeof mpuLogger === "undefined") {
      return;
    }
    if (args.length > 0 && typeof mpuLogger.warnAlwaysF === "function") {
      mpuLogger.warnAlwaysF(key, fallback, ...args);
    } else if (typeof mpuLogger.warnAlways === "function") {
      mpuLogger.warnAlways(key, fallback);
    }
  }

  const manager = window.mpuFrierenManager;
  if (!manager) {
    warnFrieren("frierenInteractionsManagerMissing", "Frieren manager が見つからないため、インタラクションモジュールを初期化できません");
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

      if (this.decorationChatInProgress || this.giveItemInProgress) {
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

      if (typeof mpuSetMessageBlocking === "function") {
        mpuSetMessageBlocking(true);
      } else if (typeof window !== "undefined") {
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

      if (typeof mpuSetMessageBlocking === "function") {
        mpuSetMessageBlocking(false);
      } else if (typeof window !== "undefined") {
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

      if (this.decorationChatInProgress || this.giveItemInProgress) {
        if (typeof mpuLogger !== "undefined" && mpuLogger.log) {
          mpuLogger.logL("frierenTouchZoneIgnoredDialogActive", "装飾品またはタッチ会話中のため、タッチ領域クリックを無視します");
        }
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
        if (typeof mpuLogger !== "undefined" && mpuLogger.log) {
          mpuLogger.logL("frierenTouchZoneDialogSkippedAiDisabled", "AI 機能が有効ではないため、タッチ領域会話をスキップします");
        }
        return;
      }

      this.decorationChatInProgress = true;

      if (typeof mpuSetMessageBlocking === "function") {
        mpuSetMessageBlocking(true);
      } else if (typeof window !== "undefined") {
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
        } else {
          warnFrieren("frierenTouchZoneRuntimeUnavailable", "mpuFetch または mpuRestUrl が利用できないため、タッチ領域会話を送信できません");
          self.restoreAfterDecorationChat();
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
      if (!imgContainer) {
        warnFrieren("frierenTouchContainerMissing", "#ukagaka_img が見つからないため、タッチイベントを設定できません");
        return;
      }

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

      this.setupGiftPicker();
    },

    /**
     * チャット入力欄の末尾にギフトピッカーを設置する。
     */
    setupGiftPicker: function () {
      const config = window.mpuPersonalityItems;
      const input = document.getElementById("mpu_user_input");
      const container = document.getElementById("ukagaka_chat_input");
      if (
        !config ||
        !Array.isArray(config.items) ||
        config.items.length === 0 ||
        !input ||
        !container ||
        document.getElementById("mpu_gift_picker_button")
      ) {
        return;
      }

      const button = document.createElement("button");
      button.type = "button";
      button.id = "mpu_gift_picker_button";
      button.className = "mpu-gift-picker-button";
      button.textContent = "🎁";
      button.setAttribute("aria-label", config.pickerLabel || "ギフトを選ぶ");
      button.setAttribute("aria-expanded", "false");
      button.setAttribute("aria-controls", "mpu_gift_picker");

      const picker = document.createElement("div");
      picker.id = "mpu_gift_picker";
      picker.className = "mpu-gift-picker";
      picker.hidden = true;
      picker.setAttribute("role", "dialog");
      picker.setAttribute("aria-label", config.pickerLabel || "ギフトを選ぶ");

      const slider = document.createElement("div");
      slider.className = "mpu-gift-picker-slider";

      const previousButton = document.createElement("button");
      previousButton.type = "button";
      previousButton.className = "mpu-gift-picker-nav mpu-gift-picker-nav-previous";
      previousButton.textContent = "‹";
      previousButton.setAttribute("aria-label", config.previousLabel || "前のアイテム");

      const slide = document.createElement("div");
      slide.className = "mpu-gift-picker-slide";

      const nextButton = document.createElement("button");
      nextButton.type = "button";
      nextButton.className = "mpu-gift-picker-nav mpu-gift-picker-nav-next";
      nextButton.textContent = "›";
      nextButton.setAttribute("aria-label", config.nextLabel || "次のアイテム");

      const counter = document.createElement("div");
      counter.className = "mpu-gift-picker-counter";
      counter.setAttribute("aria-live", "polite");

      let currentIndex = 0;
      let touchStartX = null;
      const hasNavigation = config.items.length > 1;

      const renderSlide = () => {
        const item = config.items[currentIndex];
        const itemButton = document.createElement("button");
        itemButton.type = "button";
        itemButton.className = "mpu-gift-picker-item";
        itemButton.setAttribute("aria-label", item.name);
        itemButton.title = item.name;

        const image = document.createElement("img");
        image.src = String(config.itemsBaseUrl || "") + item.image;
        image.alt = "";
        image.loading = "lazy";
        image.addEventListener("error", () => {
          const fallback = document.createElement("span");
          fallback.className = "mpu-gift-picker-fallback";
          fallback.textContent = item.name;
          image.replaceWith(fallback);
        }, { once: true });

        itemButton.appendChild(image);
        itemButton.addEventListener("click", () => {
          this.closeGiftPicker();
          this.giveItem(item.id);
        });
        slide.replaceChildren(itemButton);
        counter.textContent = `${currentIndex + 1} / ${config.items.length}`;
      };

      const moveSlide = (offset) => {
        currentIndex = (currentIndex + offset + config.items.length) % config.items.length;
        renderSlide();
        const itemButton = slide.querySelector(".mpu-gift-picker-item");
        if (!picker.hidden && itemButton) {
          itemButton.focus();
        }
      };

      previousButton.addEventListener("click", () => moveSlide(-1));
      nextButton.addEventListener("click", () => moveSlide(1));

      if (!hasNavigation) {
        slider.classList.add("is-single");
        previousButton.hidden = true;
        nextButton.hidden = true;
        counter.hidden = true;
      }

      slider.addEventListener("touchstart", (event) => {
        touchStartX = event.changedTouches[0]?.clientX ?? null;
      }, { passive: true });
      slider.addEventListener("touchend", (event) => {
        if (!hasNavigation || touchStartX === null) {
          touchStartX = null;
          return;
        }
        const touchEndX = event.changedTouches[0]?.clientX ?? touchStartX;
        const deltaX = touchEndX - touchStartX;
        touchStartX = null;
        if (Math.abs(deltaX) < 30) {
          return;
        }
        moveSlide(deltaX > 0 ? -1 : 1);
      }, { passive: true });

      slider.append(previousButton, slide, nextButton);
      picker.append(slider, counter);
      renderSlide();

      button.addEventListener("click", () => {
        const willOpen = picker.hidden;
        picker.hidden = !willOpen;
        button.setAttribute("aria-expanded", willOpen ? "true" : "false");
        if (willOpen) {
          slide.querySelector(".mpu-gift-picker-item")?.focus();
        }
      });

      container.append(button, picker);

      // ピッカーは「🎁 を開く → 入力欄に台詞を書く → アイテムを押して贈る」順で使う。
      // button / picker / 入力欄はいずれも #ukagaka_chat_input の子なので、
      // コンテナ内のクリックでは閉じない（台詞を書くために入力欄を押しても開いたまま）.
      document.addEventListener("click", (event) => {
        const target = event.target;
        if (target && target.closest && target.closest("#ukagaka_chat_input")) {
          return;
        }
        this.closeGiftPicker();
      });

      // ピッカーが開いている間の入力欄は「贈り物への添え書き」なので、Enter は
      // 一般チャット送信ではなく現在のアイテムの贈与に割り当てる。
      // ukagaka-chat-events.js の keypress ハンドラより先に走らせるため、
      // 祖先要素の capture フェーズで捕まえて keydown を canceled にする
      // （keydown を preventDefault すると keypress は発火しない）.
      container.addEventListener(
        "keydown",
        (event) => {
          if (
            picker.hidden ||
            event.target !== input ||
            event.key !== "Enter" ||
            event.shiftKey
          ) {
            return;
          }
          event.preventDefault();
          event.stopPropagation();
          const item = config.items[currentIndex];
          this.closeGiftPicker();
          this.giveItem(item.id);
        },
        true
      );

      // ✅ ボタンは Enter と同じ「送信」操作なので、ピッカーが開いている間は
      // 同じ意味を持たせる。片方だけ贈与に割り当てると、添え書きを書いてから
      // ボタンを押した回だけ品物が消え、台詞だけが普通のチャットとして飛ぶ。
      // #mpu_ok_btn は #ukagaka_chat_input の外にあるので container では捕まらず、
      // document の capture フェーズで ukagaka-chat-events.js の click ハンドラより
      // 先に止める必要がある.
      document.addEventListener(
        "click",
        (event) => {
          if (picker.hidden || !event.target || !event.target.closest) {
            return;
          }
          if (!event.target.closest("#mpu_ok_btn")) {
            return;
          }
          event.preventDefault();
          event.stopPropagation();
          const item = config.items[currentIndex];
          this.closeGiftPicker();
          this.giveItem(item.id);
        },
        true
      );

      document.addEventListener("keydown", (event) => {
        if (event.key === "Escape" && !picker.hidden) {
          this.closeGiftPicker();
          button.focus();
          return;
        }
        // 入力欄で台詞を書いている間の矢印キーはキャレット移動。スライダーが奪わない.
        if (picker.hidden || !hasNavigation || event.target === input) {
          return;
        }
        if (event.key === "ArrowLeft") {
          event.preventDefault();
          moveSlide(-1);
        } else if (event.key === "ArrowRight") {
          event.preventDefault();
          moveSlide(1);
        }
      });
    },

    /**
     * チャット入力欄の値を差し替え、input 依存の UI（リサイズ等）へ通知する。
     * @param {HTMLInputElement} input
     * @param {string} value
     */
    setChatInputValue: function (input, value) {
      if (!input) {
        return;
      }
      input.value = value;
      input.dispatchEvent(new Event("input", { bubbles: true }));
    },

    closeGiftPicker: function () {
      const button = document.getElementById("mpu_gift_picker_button");
      const picker = document.getElementById("mpu_gift_picker");
      if (picker) {
        picker.hidden = true;
      }
      if (button) {
        button.setAttribute("aria-expanded", "false");
      }
    },

    /**
     * 選択されたアイテムを /touch/give に送信する。
     * チャット入力欄に文字があれば、同じ give イベントの附言として一緒に送る。
     * @param {string} itemId
     */
    giveItem: async function (itemId) {
      const messageBlocking = typeof mpuMessageBlocking !== "undefined"
        ? mpuMessageBlocking
        : Boolean(window.mpuMessageBlocking);
      if (
        this.giveItemInProgress ||
        this.decorationChatInProgress ||
        messageBlocking ||
        typeof mpuAiEnabled === "undefined" ||
        !mpuAiEnabled
      ) {
        return;
      }

      // 附言 snapshot。空白のみの入力は「附言なし」扱いで、入力欄も触らない。
      const chatInput = document.getElementById("mpu_user_input");
      const giveMessage = chatInput ? String(chatInput.value || "").trim() : "";

      const button = document.getElementById("mpu_gift_picker_button");
      const pickerButtons = document.querySelectorAll("#mpu_gift_picker button");
      this.giveItemInProgress = true;
      if (typeof mpuSetMessageBlocking === "function") {
        mpuSetMessageBlocking(true);
      } else {
        window.mpuMessageBlocking = true;
      }
      if (button) {
        button.disabled = true;
      }
      pickerButtons.forEach((pickerButton) => {
        pickerButton.disabled = true;
      });
      // 送信と同時に入力欄を空にする（通常チャット送信と同じ節奏）。
      if (giveMessage) {
        this.setChatInputValue(chatInput, "");
      }

      try {
        if (
          typeof mpuFetch !== "function" ||
          typeof mpuRestUrl === "undefined" ||
          typeof mpu_getOrCreateChatSessionId !== "function"
        ) {
          throw new Error("Gift picker runtime is unavailable");
        }

        if (typeof stopAutoTalk === "function") {
          stopAutoTalk();
        }
        if (typeof mpu_cancelTypewriter === "function") {
          mpu_cancelTypewriter();
        }
        if (typeof mpuShowSystemPlaceholder === "function") {
          mpuShowSystemPlaceholder({ context: "give" });
        }
        if (typeof mpuMarkSystemPlaceholder === "function") {
          mpuMarkSystemPlaceholder("#ukagaka_msg");
        }
        if (jQuery("#ukagaka_msgbox").is(":hidden") && typeof mpu_showmsg !== "undefined") {
          mpu_showmsg(400);
        }

        const history = typeof mpu_getChatHistoryForRequest === "function"
          ? mpu_getChatHistoryForRequest()
          : (Array.isArray(window.mpuChatHistory) ? window.mpuChatHistory.slice(-40) : []);
        const formData = new FormData();
        formData.append("item_id", itemId);
        formData.append("session_id", mpu_getOrCreateChatSessionId());
        formData.append("history", JSON.stringify(history));
        if (giveMessage) {
          formData.append("message", giveMessage);
        }

        // timeout は後端 provider の 30 秒より大きくし、ネットワーク + PHP 前処理の
        // 余白を確保する。これがないと「後端は成功したのに前端だけ abort」で
        // 偽の「通信状況が良くない」が出る（chat の watchdog 45 秒と揃える）。
        // give は history/統計を書き込む非冪等 POST なので retries は 0（二重送信防止）。
        const res = await mpuFetch(mpuRestUrl + "touch/give", {
          method: "POST",
          body: formData,
          timeout: 45000,
          retries: 0,
          requestId: "mpu_give_item_" + itemId,
        });

        if (!res || !res.msg || res.error) {
          throw new Error(res?.error || "Gift reaction failed");
        }

        if (typeof mpu_typewriter === "function") {
          mpu_typewriter(res.msg, "#ukagaka_msg");
        }
        if (this.isFrierenMode) {
          this.triggerFrierenSpeaking(true);
        }
        if (res.emoji && window.mpuEmojiManager) {
          window.mpuEmojiManager.showEmoji(res.emoji);
        }
        if (Array.isArray(window.mpuChatHistory) && res.user_anchor) {
          window.mpuChatHistory.push({
            role: "user",
            content: res.user_anchor,
            type: "synthetic",
            timestamp: Date.now(),
          });
          window.mpuChatHistory.push({
            role: "assistant",
            content: res.msg,
            type: "give",
            timestamp: Date.now(),
          });
          if (typeof mpu_saveChatHistory === "function") {
            mpu_saveChatHistory();
          }
        }
      } catch (error) {
        // 失敗時は附言を取り戻す。ただし待機中に新しく入力していたら上書きしない。
        if (
          giveMessage &&
          chatInput &&
          String(chatInput.value || "").trim() === ""
        ) {
          this.setChatInputValue(chatInput, giveMessage);
        }
        if (typeof mpuLogger !== "undefined" && mpuLogger.errorL) {
          mpuLogger.errorL("frierenGiveItemRequestFailed", "ギフト反応リクエストに失敗しました", error);
        }
        if (typeof mpu_typewriter === "function") {
          // 真の通信/タイムアウト障害だけ「通信状況…」を表示し、後端が返した
          // 構造化エラー（rate limit・不明なアイテム等、いずれもユーザー向け日本語）は
          // そのまま見せる。これで checksum / rate limit / provider error を区別できる。
          const rawMsg = error && error.message ? String(error.message) : "";
          const fallback =
            (window.mpuL10n && window.mpuL10n.connectionError) ||
            "（…通信状況が良くないみたいだ…）";
          const isConnectionIssue =
            rawMsg === "" ||
            rawMsg.indexOf("請求已被取消") !== -1 ||
            rawMsg.indexOf("Failed to fetch") !== -1 ||
            rawMsg.indexOf("NetworkError") !== -1 ||
            rawMsg.toLowerCase().indexOf("timeout") !== -1 ||
            rawMsg.indexOf("Gift picker runtime is unavailable") !== -1;
          mpu_typewriter(isConnectionIssue ? fallback : rawMsg, "#ukagaka_msg");
        }
      } finally {
        const self = this;
        const restoreAfterGive = function () {
          self.giveItemInProgress = false;
          if (typeof mpuSetMessageBlocking === "function") {
            mpuSetMessageBlocking(false);
          } else {
            window.mpuMessageBlocking = false;
          }
          if (button) {
            button.disabled = false;
          }
          pickerButtons.forEach((pickerButton) => {
            pickerButton.disabled = false;
          });
          if (typeof mpuClearSystemPlaceholder === "function") {
            mpuClearSystemPlaceholder("#ukagaka_msg");
          }
          if (typeof mpuAutoTalk !== "undefined" && mpuAutoTalk && typeof startAutoTalk === "function") {
            startAutoTalk();
          }
        };

        // 打字機仍在輸出時不可解鎖，否則使用者能立刻再次送禮、或讓自動對話打斷尚未完成的演出。
        // decoration/touch 流程同型（scheduleRestoreAfterChat → mpu_waitForTypewriterComplete）。
        if (typeof mpu_waitForTypewriterComplete !== "undefined") {
          mpu_waitForTypewriterComplete(restoreAfterGive);
        } else {
          setTimeout(restoreAfterGive, 3000);
        }
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
