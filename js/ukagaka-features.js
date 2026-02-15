// ====== 事件處理 ======
jQuery(document).ready(function () {
  mpuLogger.log("jQuery ready 已執行");

  // 確保 jQuery.cookie 已初始化
  if (!mpu_init_jquery_cookie()) {
    mpuLogger.error("無法初始化 jQuery.cookie，某些功能可能無法正常工作");
  } else {
    mpuLogger.log("jQuery.cookie 已成功初始化");
  }

  // 顯示初始訊息的打字效果
  const msgElement = jQuery("#ukagaka_msg");
  if (msgElement.length) {
    const initialMsg = msgElement.attr("data-initial-msg");
    if (initialMsg) {
      // 清空內容，然後用打字效果顯示
      msgElement.html("");
      mpu_typewriter(initialMsg, "#ukagaka_msg");
    }
  }

  // 載入外部對話
  function initExternalDialog() {
    const msgListElem = document.getElementById("ukagaka_msglist");
    const isLLMReplaceEnabled = typeof mpuPreSettings !== 'undefined' && mpuPreSettings.ollama_replace === true;

    if (isLLMReplaceEnabled) {
      mpuLogger.log("LLM 取代對話已啟用，但仍載入內建對話作為後備");
    }

    if (
      msgListElem &&
      msgListElem.getAttribute("data-load-external") === "true"
    ) {
      const dialogFile = msgListElem.getAttribute("data-file");
      if (dialogFile) {
        loadExternalDialog(dialogFile, isLLMReplaceEnabled);
      }
    } else {
      try {
        const jsonText = msgListElem ? msgListElem.innerHTML.trim() : "";
        if (jsonText) {
          window.mpuMsgList = JSON.parse(jsonText);

          if (window.mpuMsgList.next_msg !== undefined) {
            mpuNextMode =
              window.mpuMsgList.next_msg == 1 ? "random" : "sequential";
          }
          if (window.mpuMsgList.default_msg !== undefined) {
            mpuDefaultMsg = window.mpuMsgList.default_msg == 1 ? 1 : 0;
          }
        }
      } catch (e) {
        mpu_handle_error(e, "jQuery.ready:init_dialog_data");
      }
      if (!isLLMReplaceEnabled && mpuAutoTalk && !mpuAutoTalkTimer) {
        startAutoTalk();
      }
    }
  }

  initExternalDialog();

  /**
   * 處理設定資料並初始化功能
   * @param {Object} res - 設定資料
   */
  function processSettings(res) {
    // 防止重複調用（可能由 mpuInitComplete 和預載資料同時觸發）
    if (window.mpuSettingsProcessed) {
      mpuLogger.log("processSettings: 已處理過設定，跳過重複調用");
      return;
    }
    window.mpuSettingsProcessed = true;
    
    if (!res || typeof res !== "object") {
      mpuLogger.warn("mpu_get_settings: 無效的回應", res);
      return;
    }

    mpuLogger.log("mpu_get_settings: 收到設定 =", JSON.stringify(res));
    mpuLogger.log("mpu_get_settings: auto_talk =", res.auto_talk, ", ollama_replace_dialogue =", res.ollama_replace_dialogue);

    mpuAutoTalk = res.auto_talk === true;
    mpuLogger.log("mpu_get_settings: 設置 mpuAutoTalk =", mpuAutoTalk);

    if (res.auto_talk_interval) {
      const iv = parseInt(res.auto_talk_interval, 10);
      if (!isNaN(iv) && iv > 0) {
        const baseInterval = iv * 1000;
        
        // 保存基礎間隔（用於動態調整睡眠模式）
        window.mpuBaseAutoTalkInterval = baseInterval;
        
        // 初始設定：檢查當前是否為睡眠模式
        // 優先使用伺服器端時間判定（避免客戶端/伺服器時區差異）
        let interval = baseInterval;
        let isDeepSleep = false;
        if (typeof window.mpuInfo !== 'undefined' && typeof window.mpuInfo.isDeepSleepTime !== 'undefined') {
          isDeepSleep = window.mpuInfo.isDeepSleepTime;
        } else {
          // 備用：使用客戶端時間（向後兼容）
          const now = new Date();
          const hour = now.getHours();
          isDeepSleep = hour >= 0 && hour < 6;
        }
        
        if (isDeepSleep) {
          // 睡眠模式：使用 frequency_multiplier = 0.0667（間隔延長 15 倍）
          const multiplier = res.sleep_mode && res.sleep_mode.frequency_multiplier 
              ? res.sleep_mode.frequency_multiplier 
              : 0.0667;
          interval = Math.round(baseInterval / multiplier);
          mpuLogger.log("🌙 睡眠模式啟用（00:00~06:00），間隔調整為", interval, "ms（原始:", baseInterval, "ms）");
        }
        
        mpuAutoTalkInterval = interval;
      }
      mpuLogger.log("mpu_get_settings: 設置 mpuAutoTalkInterval =", mpuAutoTalkInterval, "ms");
    }
    if (res.ai_text_color) {
      mpuAiTextColor = res.ai_text_color;
    }
    if (res.ai_display_duration) {
      mpuAiDisplayDuration = parseInt(res.ai_display_duration, 10) || 8;
    }
    mpuOllamaReplaceDialogue = !!res.ollama_replace_dialogue;
    mpuEnableChatMode = !!res.enable_chat_mode;
    mpuLogger.log(
      "LLM 取代對話設定: " + (mpuOllamaReplaceDialogue ? "啟用" : "停用") +
      "，互動對話模式: " + (mpuEnableChatMode ? "啟用" : "停用")
    );

    // 睡眠模式檢測（移到外面以便後續判斷使用）
    let isDeepSleep = false;
    if (typeof window.mpuInfo !== 'undefined' && typeof window.mpuInfo.isDeepSleepTime !== 'undefined') {
      isDeepSleep = window.mpuInfo.isDeepSleepTime;
    } else {
      // 備用：使用客戶端時間（向後兼容）
      const now = new Date();
      const hour = now.getHours();
      isDeepSleep = hour >= 0 && hour < 6;
    }
    
    // 檢查初始訊息是否為睡眠相關
    const msgElement = jQuery("#ukagaka_msg");
    const initialMsg = msgElement.length ? msgElement.attr("data-initial-msg") : "";
    // 使用隱藏標記檢測睡眠模式（由 PHP 端統一添加）
    const isSleepMessage = initialMsg && initialMsg.includes('<!-- mpu-sleep -->');

    if (mpuOllamaReplaceDialogue) {
      if (isDeepSleep && isSleepMessage) {
        // 睡眠模式下，完全跳過初始的 LLM 對話觸發
        // 讓睡眠訊息保持顯示，直到自動對話計時器自然觸發（約 300 秒後）
        mpuLogger.log("🌙 睡眠模式：跳過初始 LLM 對話觸發，保持睡眠訊息顯示");
        mpuLogger.log("🌙 睡眠訊息將保持顯示，直到自動對話計時器觸發（約 " + Math.round((window.mpuBaseAutoTalkInterval || mpuAutoTalkInterval) / 0.0667 / 1000) + " 秒後）");
      } else {
        // 正常模式下，立即觸發 LLM 對話
      mpuLogger.log("LLM 取代對話已啟用，等待初始訊息完成後觸發 LLM 對話");
      mpu_waitForTypewriterComplete(function() {
        setTimeout(function () {
          mpu_nextmsg('startup');
        }, 1500);
      });
      }
    }

    // ⚠️ 當 LLM 取代對話啟用時，應在 startup 完成後再啟動自動對話
    // 否則計時器會在 LLM 回應返回前就觸發，導致對話互相覆蓋
    const shouldDelayAutoTalk = mpuOllamaReplaceDialogue && !(isDeepSleep && isSleepMessage);

    mpuLogger.log("mpu_get_settings: 準備調用 startAutoTalk/stopAutoTalk, mpuAutoTalk =", mpuAutoTalk, ", shouldDelayAutoTalk =", shouldDelayAutoTalk);
    if (mpuAutoTalk && !shouldDelayAutoTalk) {
      startAutoTalk();
    } else if (!mpuAutoTalk) {
      stopAutoTalk();
    }
    // 如果 shouldDelayAutoTalk 為 true，startAutoTalk 會在 mpu_nextmsg callback 中被觸發
    setAutoTalkUI();

    if (res.ai_enabled === true && res.ai_greet_first_visit === true) {
      if (mpuGreetInProgress) {
        return;
      }

      const firstVisitCookie =
        "mpu_first_visit_" + (document.domain || "default");

      if (typeof jQuery.cookie === "undefined") {
        mpu_init_jquery_cookie();
      }

      if (typeof jQuery.cookie === "undefined") {
        const isFirstVisit = !mpu_getCookie(firstVisitCookie);
        if (isFirstVisit) {
          mpuGreetInProgress = true;
          mpu_greet_first_visitor(res)
            .then(() => {
              mpu_setCookie(firstVisitCookie, "1", 365, "/");
              mpuGreetInProgress = false;
            })
            .catch((error) => {
              mpu_handle_error(error, "首次訪客打招呼:catch", {
                showToUser: false,
              });
              mpuGreetInProgress = false;
            });
        }
        return;
      }

      const isFirstVisit = !jQuery.cookie(firstVisitCookie);

      if (isFirstVisit) {
        mpuGreetInProgress = true;
        mpu_greet_first_visitor(res)
          .then(() => {
            if (typeof jQuery.cookie !== "undefined") {
              jQuery.cookie(firstVisitCookie, "1", {
                expires: 365,
                path: "/",
              });
            } else {
              mpu_setCookie(firstVisitCookie, "1", 365, "/");
            }
            mpuGreetInProgress = false;
          })
          .catch((error) => {
            mpu_handle_error(error, "首次訪客打招呼:catch2", {
              showToUser: false,
            });
            mpuGreetInProgress = false;
          });
        return;
      }
    }

    if (res.ai_enabled === true) {
      mpuLogger.log("頁面感知 AI 已啟用，觸發頁面條件 =", res.ai_trigger_pages);
      const shouldTrigger = mpu_check_page_trigger(res.ai_trigger_pages);

      mpuLogger.log("頁面感知檢查結果: shouldTrigger =", shouldTrigger);

      if (shouldTrigger) {
        const probability = parseInt(res.ai_probability || 10, 10);
        const roll = Math.floor(Math.random() * 100) + 1;

        mpuLogger.log(
          "頁面感知機率檢查: 設定機率 =",
          probability,
          "%, 骰子 =",
          roll,
          ", 觸發 =",
          roll <= probability
        );

        if (roll <= probability) {
          mpuLogger.log("頁面感知 AI 將在 3 秒後觸發");
          setTimeout(function () {
            mpu_chat_context();
          }, 3000);
          return;
        } else {
          mpuLogger.log("頁面感知 AI 未通過機率檢查，不觸發");
        }
      } else {
        mpuLogger.log("頁面感知 AI 未通過頁面類型檢查，不觸發");
      }
    } else {
      mpuLogger.log("頁面感知 AI 未啟用（ai_enabled =", res.ai_enabled, "）");
    }
  }

  // 優先使用 mpu_init 預載的設定資料（性能優化）
  if (window.mpuSettings) {
    mpuLogger.log("使用預載設定資料");
    processSettings(window.mpuSettings);
  } else {
    // 監聽 mpuInitComplete 事件
    jQuery(document).one("mpuInitComplete", function(event, response) {
      if (response && response.settings) {
        mpuLogger.log("從 mpuInitComplete 事件獲取設定");
        processSettings(response.settings);
      }
    });
    
    // Fallback：如果 500ms 內沒有收到資料，發送獨立 AJAX
    setTimeout(function() {
      if (!window.mpuSettings && !window.mpuSettingsLoaded) {
        mpuLogger.log("Fallback: 發送獨立 mpu_get_settings AJAX");
        const settingsParams = new URLSearchParams({ action: "mpu_get_settings" });
        if (typeof mpuNonce !== "undefined") {
          settingsParams.append("mpu_nonce", mpuNonce);
        }
        const settingsUrl = `${mpuurl}?${settingsParams.toString()}`;
        
        mpuFetch(settingsUrl, {
          dedupe: true,
          requestId: "mpu_get_settings",
          timeout: 10000,
          retries: 2,
        })
          .then((res) => {
            window.mpuSettingsLoaded = true;
            processSettings(res);
          })
          .catch((error) => {
            mpuLogger.warn("Failed to get mpu_get_settings:", error);
          });
      }
    }, 500);
  }

  if (jQuery("#toggleAutoTalk").length === 0) {
    const btn =
      '<li class="auto-talk"><a id="toggleAutoTalk" href="javascript:void(0);" title="自動對話"></a></li>';
    jQuery("#ukagaka-dock ul").append(btn);
    setAutoTalkUI();

    jQuery("#toggleAutoTalk").on("click", function () {
      mpuAutoTalk = !mpuAutoTalk;
      if (mpuAutoTalk) startAutoTalk();
      else stopAutoTalk();
      setAutoTalkUI();
    });
  }

  jQuery("#show_msg").on("click", function () {
    if (jQuery("#ukagaka_msgbox").is(":hidden")) {
      mpu_showmsg(400);
      mpu_setLocal("mpuMsg", "show");
    } else {
      mpu_hidemsg(400);
      mpu_setLocal("mpuMsg", "hidden");
    }
  });

  jQuery("#ukagaka_img").on("click", function () {
    if (jQuery("#ukagaka_msgbox").is(":hidden")) {
      mpu_showmsg(400);
    } else {
      mpu_hidemsg(400);
    }
  });

  jQuery("#mpu_extend").on("click", function () {
    const extendParams = new URLSearchParams({ action: "mpu_extend" });
    if (typeof mpuNonce !== "undefined") {
      extendParams.append("mpu_nonce", mpuNonce);
    }
    const extendUrl = `${mpuurl}?${extendParams.toString()}`;

    document.body.style.cursor = "wait";
    if (jQuery("#ukagaka").is(":hidden")) mpu_showrobot(400);
    else if (!jQuery("#ukagaka_msgbox").is(":hidden")) mpu_hidemsg(200);

    mpuFetch(extendUrl, {
      timeout: 10000, // 10 秒超時
      retries: 1,
    })
      .then((html) => {
        if (typeof html !== "string")
          throw new Error("Expected HTML response.");
        mpu_showmsg(400);
        jQuery("#ukagaka_msg").html(html);
        document.body.style.cursor = "auto";
      })
      .catch((error) => {
        mpu_handle_error(error, "mpu_extend", {
          showToUser: true,
          userMessage:
            debugMode || window.mpuDebugMode
              ? `載入失敗: ${error.message}`
              : "載入失敗，請稍後再試。",
        });
        mpu_showmsg(400);
        document.body.style.cursor = "auto";
      });
  });

  // [!] 移除 scroll fadeIn/fadeOut 邏輯
  jQuery("#toTop").on("click", function () {
    const startY = window.pageYOffset;
    const duration = 600;
    const startTime = performance.now();
    
    function easeOutCubic(t) {
      return 1 - Math.pow(1 - t, 3);
    }
    
    function step(currentTime) {
      const elapsed = currentTime - startTime;
      const progress = Math.min(elapsed / duration, 1);
      const ease = easeOutCubic(progress);
      
      window.scrollTo(0, startY * (1 - ease));
      
      if (progress < 1) {
        requestAnimationFrame(step);
      }
    }
    
    requestAnimationFrame(step);
  });

  jQuery("#mp_ukagaka").css("display", "block");
  jQuery("#remove").on("click", function () {
    const $ukagaka = jQuery("#ukagaka");
    if ($ukagaka.is(":hidden")) {
      mpu_showrobot(400);
      mpu_setLocal("mpuRobot", "show");
    } else {
      mpu_hiderobot(400);
      mpu_setLocal("mpuRobot", "hidden");
    }
    return false;
  });

  const robotState = mpu_getLocal("mpuRobot");
  if (robotState === "hidden") {
    jQuery("#ukagaka").css("display", "none");
    jQuery("#remove").html(mpuInfo.robot[0]);
  } else {
    jQuery("#remove").html(mpuInfo.robot[1]);
  }
});

jQuery(window).on("blur", function () {
  if (mpuAutoTalk) stopAutoTalk();
});
jQuery(window).on("focus", function () {
  if (mpuAutoTalk) startAutoTalk();
});
document.addEventListener("visibilitychange", function () {
  if (document.hidden) {
    stopAutoTalk();
  } else if (mpuAutoTalk) {
    startAutoTalk();
  }
});

// ====== SPA Navigation 支援 ======
// 預設支援的 SPA 事件列表（使用者可在此陣列添加自己主題的事件）
// 使用方式：在主題的 JS 中添加 window.mpuSpaEvents.push('your-custom-event');
window.mpuSpaEvents = window.mpuSpaEvents || [
  'moelogContentSwap',     // Moelog 
  'swup:contentReplaced',  // Swup.js
  'barba:after',           // Barba.js
  'turbo:load',            // Turbo (Hotwire)
  'astro:page-load'        // Astro View Transitions
];

/**
 * SPA 導航後的頁面感知處理函數
 * @param {Event} e - 事件物件
 */
function mpu_handleSpaNavigation(e) {
  const url = e.detail?.url || e.detail?.to?.url || window.location.href;
  mpuLogger.log("🔄 SPA 導航：頁面內容已更換", url);

  // 延遲一下讓新內容載入完成，然後檢查是否要觸發頁面感知對話
  setTimeout(function () {
    // 檢查 AI 和頁面感知功能是否啟用
    if (
      typeof window.mpuSettings !== "undefined" &&
      window.mpuSettings.ai_enabled === true
    ) {
      const shouldTrigger = mpu_check_page_trigger(
        window.mpuSettings.ai_trigger_pages
      );

      if (shouldTrigger) {
        const probability = parseInt(window.mpuSettings.ai_probability || 10, 10);
        const roll = Math.floor(Math.random() * 100) + 1;

        mpuLogger.log(
          "🎲 SPA 頁面感知檢查：機率 =",
          probability,
          ", 骰子 =",
          roll,
          ", 觸發 =",
          roll <= probability
        );

        if (roll <= probability) {
          // 觸發頁面感知 AI 對話
          if (typeof mpu_chat_context === "function") {
            mpu_chat_context();
          }
        }
      }
    }
  }, 1000); // 等待 1 秒讓新頁面內容穩定
}

// 為所有已註冊的 SPA 事件添加監聽器
window.mpuSpaEvents.forEach(function(eventName) {
  document.addEventListener(eventName, mpu_handleSpaNavigation);
});

mpuLogger.log("腳本載入完成");

// ====== 互動對話模式 ======
// 已移至 ukagaka-chat.js
