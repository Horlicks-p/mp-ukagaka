// ====== AI 上下文對話 ======
/**
 * 檢查頁面是否應該觸發 AI 對話
 * @param {string} triggerPages - 觸發條件字串，以逗號分隔（例如："is_single,is_page"）
 * @returns {boolean} 是否符合觸發條件
 */
function mpu_check_page_trigger(triggerPages) {
  if (!triggerPages) return false;

  const conditions = triggerPages.split(",").map((s) => s.trim().toLowerCase());
  const path = window.location.pathname;
  const url = window.location.href;

  // 檢查各種 WordPress 條件
  for (let condition of conditions) {
    condition = condition.trim();
    if (!condition) continue;

    // is_single: 單篇文章頁面
    if (condition === "is_single") {
      // 檢查是否為單篇文章：通常有日期格式 /YYYY/MM/DD/ 或直接是文章 slug
      // 排除分類、標籤、作者、頁面等特殊頁面
      if (
        path.match(/\/\d{4}\/\d{2}\/\d{2}\//) ||
        (path.length > 1 &&
          !path.match(/^\/(category|tag|author|page|search|archive|feed)/) &&
          !path.match(/\/page\/\d+/))
      ) {
        return true;
      }
    }
    // is_page: 頁面
    else if (condition === "is_page") {
      // 簡單檢查：頁面通常沒有日期格式，且不是分類/標籤等
      if (
        path.length > 1 &&
        !path.match(/^\/(category|tag|author|search|archive|feed)/) &&
        !path.match(/\/\d{4}\/\d{2}\/\d{2}\//)
      ) {
        return true;
      }
    }
    // is_home: 首頁
    else if (condition === "is_home" || condition === "is_front_page") {
      if (path === "/" || path.match(/^\/page\/\d+$/)) {
        return true;
      }
    }
    // is_archive: 歸檔頁面
    else if (condition === "is_archive") {
      if (path.match(/^\/(category|tag|author|date)/)) {
        return true;
      }
    }
  }

  return false;
}

/**
 * 獲取頁面上下文資訊（標題和內容）
 * @returns {{title: string, content: string}} 包含頁面標題和內容的物件
 */
function mpu_get_page_context() {
  const title = document.title;

  // 從 article, main, 或 .entry-content 提取內容
  // 注意：不包含 document.body 以避免抓到導航列和頁尾雜訊
  let content = "";

  const article =
    document.querySelector("article") ||
    document.querySelector("main") ||
    document.querySelector(".entry-content") ||
    document.querySelector("#content");

  if (article) {
    // 抓取文字，將多個空白合併為一個，並限制長度
    content = article.innerText.replace(/\s+/g, " ").substring(0, 3000);
  }

  return { title, content };
}

/**
 * AI 上下文對話：根據當前頁面內容生成 AI 回應
 */
function mpu_chat_context() {
  const context = mpu_get_page_context();
  const contentLength = context.content ? context.content.length : 0;

  if (!context.title && !context.content) {
    return;
  }

  // 如果首次訪客打招呼正在進行中，跳過頁面感知 AI
  if (mpuGreetInProgress) {
    return;
  }

  if (contentLength < 500) {
    return;
  }

  // 立即停止自動對話並設置標誌，防止自發對話在載入訊息顯示時打斷
  const wasAutoTalkRunning = mpuAutoTalkTimer !== null;
  if (wasAutoTalkRunning) {
    stopAutoTalk();
  }

  mpuAiContextInProgress = true;

  // 設置阻擋標誌，完全阻止自發對話
  mpuMessageBlocking = true;

  if (jQuery("#ukagaka_msgbox").is(":hidden")) mpu_showmsg(200);
  const loadingMessage = "（…ああ、記事か。どれどれ…）";
  mpu_typewriter(
    `<span style="color: ${mpuAiTextColor};">${loadingMessage}</span>`,
    "#ukagaka_msg"
  );

  const formData = new FormData();
  formData.append("action", "mpu_chat_context");
  if (typeof mpuNonce !== "undefined" && mpuNonce) {
    formData.append("mpu_nonce", mpuNonce);
  }
  formData.append("page_title", context.title);
  formData.append("page_content", context.content);

  mpuFetch(mpuurl, {
    method: "POST",
    body: formData,
    cancelPrevious: true,
    requestId: "mpu_chat_context",
    timeout: 60000,
    retries: 1,
  })
    .then((res) => {
      if (res && res.msg && !res.error) {
        let aiResponse = mpu_unescapeHTML(res.msg);
        aiResponse = mpu_linkifyUrls(aiResponse);
        mpu_typewriter(
          `<span style="color: ${mpuAiTextColor};">${aiResponse}</span>`,
          "#ukagaka_msg"
        );

        if (mpuAiDisplayTimer !== null) {
          clearTimeout(mpuAiDisplayTimer);
          mpuAiDisplayTimer = null;
        }

        const displayDurationMs = mpuAiDisplayDuration * 1000;
        mpuAiDisplayTimer = setTimeout(function () {
          mpuAiDisplayTimer = null;
          mpuMessageBlocking = false;
          mpuAiContextInProgress = false;
          if (wasAutoTalkRunning && mpuAutoTalk) {
            startAutoTalk();
          }
        }, displayDurationMs);
      } else {
        mpuLogger.warn("AI 對話失敗，使用預設對話系統:", res);

        // 檢查是否是速率限制錯誤
        const isRateLimit =
          res && res.error && res.error.includes("請求過於頻繁");

        if (isRateLimit) {
          const rateLimitMessage = "…ちょっと待って。API魔力が足りない";
          mpu_typewriter(
            `<span style="color: ${mpuAiTextColor};">${rateLimitMessage}</span>`,
            "#ukagaka_msg"
          );

          mpuMessageBlocking = true;
          const waitTime = (mpuAiDisplayDuration || 8) * 1000;

          setTimeout(function () {
            mpuMessageBlocking = false;
            mpuAiContextInProgress = false;
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
                "#ukagaka_msg"
              );
            }
            if (wasAutoTalkRunning && mpuAutoTalk) {
              startAutoTalk();
            }
          }, waitTime);
        } else {
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
              "#ukagaka_msg"
            );
          }
          mpuAiContextInProgress = false;
          if (wasAutoTalkRunning && mpuAutoTalk) {
            startAutoTalk();
          }
        }
      }
    })
    .catch((error) => {
      mpu_handle_error(error, "mpu_chat_context", {
        showToUser: false, // 已經有 fallback 處理，不需要顯示錯誤
      });

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
          "#ukagaka_msg"
        );
      }
      mpuAiContextInProgress = false;
      if (wasAutoTalkRunning && mpuAutoTalk) {
        startAutoTalk();
      }
    });
}

/**
 * 調試用：手動測試訪客資訊獲取
 * 在瀏覽器控制台輸入：mpu_test_visitor_info() 即可測試
 */
function mpu_test_visitor_info() {
  const visitorParams = new URLSearchParams({ action: "mpu_get_visitor_info" });
  const visitorUrl = `${mpuurl}?${visitorParams.toString()}`;

  mpuFetch(visitorUrl, {
    timeout: 10000, // 10 秒超時
    retries: 1,
  })
    .then((visitorInfo) => {
      mpuLogger.log("訪客資訊:", {
        referrer: visitorInfo.referrer || "無",
        referrer_host: visitorInfo.referrer_host || "無",
        search_engine: visitorInfo.search_engine || "無",
        country: visitorInfo.slimstat_country || "無",
        city: visitorInfo.slimstat_city || "無",
      });
    })
    .catch((error) => {
      mpu_handle_error(error, "mpu_test_visitor_info");
    });
}

/**
 * 首次訪客打招呼：根據訪客資訊生成個性化問候語
 * @param {Object} settings - 設定物件，包含 auto_talk 等選項
 * @returns {Promise} 返回 Promise，完成時表示打招呼流程結束
 */
function mpu_greet_first_visitor(settings) {
  return new Promise((resolve, reject) => {
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
        const loadingMessage = "（…あ、知らない人間だ…）";
        mpu_typewriter(
          `<span style="color: ${mpuAiTextColor};">${loadingMessage}</span>`,
          "#ukagaka_msg"
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
          visitorInfo.is_direct === true ? "true" : "false"
        );
        formData.append(
          "country",
          visitorInfo.slimstat_country || visitorInfo.country || ""
        );
        formData.append(
          "city",
          visitorInfo.slimstat_city || visitorInfo.city || ""
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
            "#ukagaka_msg"
          );

          if (mpuAiDisplayTimer !== null) {
            clearTimeout(mpuAiDisplayTimer);
            mpuAiDisplayTimer = null;
          }

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
        } else {
          mpuLogger.warn("首次訪客打招呼失敗:", res);

          // 檢查是否是速率限制錯誤
          const isRateLimit =
            res && res.error && res.error.includes("請求過於頻繁");

          if (isRateLimit) {
            const rateLimitMessage = "…ちょっと待って。API魔力が足りない";
            mpu_typewriter(
              `<span style="color: ${mpuAiTextColor};">${rateLimitMessage}</span>`,
              "#ukagaka_msg"
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
                  "#ukagaka_msg"
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
                "#ukagaka_msg"
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
            "#ukagaka_msg"
          );
        }

        if (wasAutoTalkRunning && settings.auto_talk === true && mpuAutoTalk) {
          startAutoTalk();
        }
        resolve();
      });
  });
}

// ====== 讀取外部對話 ======
/**
 * 載入外部對話檔案
 * @param {string} file - 對話檔案名稱（路徑會被自動處理）
 * @param {boolean} skipFirstMessage - 是否跳過顯示第一句對話（用於 LLM 取代對話模式）
 */
function loadExternalDialog(file, skipFirstMessage = false) {
  const pure = (file || "").replace(/^.*[\\/]/, "");

  const params = new URLSearchParams({
    action: "mpu_load_dialog",
    file: pure,
  });
  const url = `${mpuurl}?${params.toString()}`;

  document.body.style.cursor = "wait";
  if (jQuery("#ukagaka_msgbox").is(":hidden")) mpu_showmsg(200);

  const msgElement = jQuery("#ukagaka_msg");
  const currentMsg = msgElement.text().trim();
  const initialMsg = msgElement.attr("data-initial-msg");
  const hasShownInitialMsg = initialMsg && currentMsg.indexOf(initialMsg) !== -1;

  if (!hasShownInitialMsg) {
    const loadingMessage = "（えっと…何話せばいいかな…）";
    mpu_typewriter(loadingMessage, "#ukagaka_msg");
  }

  mpuFetch(url, {
    cancelPrevious: true,
    requestId: `loadExternalDialog_${pure}`,
    timeout: 15000,
    retries: 1,
  })
    .then((resp) => {
      if (typeof resp !== "object") {
        throw new Error(resp.error || "Expected JSON response from server.");
      }

        if (resp && !resp.error && Array.isArray(resp.msg)) {
          if (resp.msg.length === 0) {
          mpuLogger.warn('loadExternalDialog: 對話文件為空');
            window.mpuMsgList = {
              msg: [],
              auto_msg: resp.auto_msg || "",
              next_msg: resp.next_msg || 0,
              default_msg: resp.default_msg || 0
            };
            if (skipFirstMessage) {
              mpuLogger.log('loadExternalDialog: LLM 取代對話模式，對話文件為空，將依賴 LLM 生成');
              jQuery("#ukagaka").stop(true, true).fadeIn(200);
              document.body.style.cursor = "auto";
              return;
            }
            mpu_typewriter("對話文件為空，請檢查對話文件內容", "#ukagaka_msg");
          mpu_showmsg(400);
          jQuery("#ukagaka").stop(true, true).fadeIn(200);
          document.body.style.cursor = "auto";
          return;
        }

        try {
          window.mpuMsgList = resp;
          mpuNextMode = resp.next_msg == 1 ? "random" : "sequential";
          mpuDefaultMsg = resp.default_msg == 1 ? 1 : 0;

          if (skipFirstMessage) {
            mpuLogger.log('loadExternalDialog: LLM 取代對話模式，已載入後備對話數據，但不顯示第一句');
            let first = 0;
            if (mpuDefaultMsg === 0 && resp.msg.length) {
              first = Math.floor(Math.random() * resp.msg.length);
            }
            jQuery("#ukagaka_msgnum").html(first);
            jQuery("#ukagaka").stop(true, true).fadeIn(200);
            document.body.style.cursor = "auto";
            return;
          }

          let firstMessageShown = false;
          let firstMessageTimer = null;
          let waitForTypewriterActive = false;

          const showFirstMessage = function () {
            if (firstMessageTimer !== null) {
              clearTimeout(firstMessageTimer);
              firstMessageTimer = null;
            }

            if (firstMessageShown) {
              mpuLogger.log('loadExternalDialog: 嘗試重複顯示第一句對話，已阻止');
              return;
            }
            firstMessageShown = true;
            waitForTypewriterActive = false;

            let first = 0;
            if (mpuDefaultMsg === 0 && resp.msg.length) {
              first = Math.floor(Math.random() * resp.msg.length);
            }
            mpu_typewriter(
              mpu_unescapeHTML(resp.msg[first] + (resp.auto_msg || "")),
              "#ukagaka_msg"
            );
            jQuery("#ukagaka_msgnum").html(first);

            const waitForFirstMessageTypewriter = () => {
              if (mpuTypewriterTimer !== null) {
                setTimeout(waitForFirstMessageTypewriter, 50);
              } else {
                if (mpuAutoTalk) startAutoTalk();
              }
            };

            if (mpuAutoTalk) {
              waitForFirstMessageTypewriter();
            }
          };

          const waitForTypewriter = () => {
            if (firstMessageShown) {
              return;
            }

            if (mpuTypewriterTimer !== null) {
              setTimeout(waitForTypewriter, 50);
            } else {
              if (firstMessageTimer !== null) {
                clearTimeout(firstMessageTimer);
              }
              firstMessageTimer = setTimeout(showFirstMessage, 1000);
            }
          };

          if (mpuTypewriterTimer !== null) {
            if (!waitForTypewriterActive) {
              waitForTypewriterActive = true;
              waitForTypewriter();
            }
          } else {
            if (firstMessageTimer !== null) {
              clearTimeout(firstMessageTimer);
            }
            firstMessageTimer = setTimeout(showFirstMessage, 1000);
          }
        } catch (e) {
          mpu_handle_error(e, "loadExternalDialog:process_data", {
            showToUser: true,
            userMessage:
              debugMode || window.mpuDebugMode
                ? `處理對話數據時出錯：${e.message}`
                : "處理對話數據時出錯，請稍後再試。",
          });
        }
        } else {
          const errorMsg = resp && resp.error ? resp.error : "無法取得對話資料";
          jQuery("#ukagaka_msg").html(errorMsg);

          if (!window.mpuMsgList) {
          window.mpuMsgList = {
            msg: [],
            auto_msg: "",
            next_msg: 0,
            default_msg: 0
          };
            mpuLogger.warn('loadExternalDialog: 後端返回錯誤，設置空的 mpuMsgList 作為後備 -', errorMsg);
          }
        }
      jQuery("#ukagaka").stop(true, true).fadeIn(200);
      document.body.style.cursor = "auto";
    })
    .catch((error) => {
      mpu_handle_error(error, "loadExternalDialog", {
        showToUser: true,
        userMessage:
          debugMode || window.mpuDebugMode
            ? `載入對話文件失敗：${error.message}`
            : "載入對話文件失敗，請稍後再試。",
      });

      if (!window.mpuMsgList) {
        window.mpuMsgList = {
          msg: [],
          auto_msg: "",
          next_msg: 0,
          default_msg: 0
        };
        mpuLogger.warn('loadExternalDialog: 載入失敗，設置空的 mpuMsgList 作為後備');
      }

      jQuery("#ukagaka").stop(true, true).fadeIn(200);
      document.body.style.cursor = "auto";
    });
}

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

  const settingsParams = new URLSearchParams({ action: "mpu_get_settings" });
  const settingsUrl = `${mpuurl}?${settingsParams.toString()}`;

  mpuFetch(settingsUrl, {
    dedupe: true,
    requestId: "mpu_get_settings",
    timeout: 10000,
    retries: 2,
  })
    .then((res) => {
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
        if (!isNaN(iv) && iv > 0) mpuAutoTalkInterval = iv * 1000;
        mpuLogger.log("mpu_get_settings: 設置 mpuAutoTalkInterval =", mpuAutoTalkInterval, "ms");
      }
      if (res.ai_text_color) {
        mpuAiTextColor = res.ai_text_color;
      }
      if (res.ai_display_duration) {
        mpuAiDisplayDuration = parseInt(res.ai_display_duration, 10) || 8;
      }
      mpuOllamaReplaceDialogue = !!res.ollama_replace_dialogue;
      mpuLogger.log(
        "LLM 取代對話設定: " + (mpuOllamaReplaceDialogue ? "啟用" : "停用")
      );

      if (mpuOllamaReplaceDialogue) {
        mpuLogger.log("LLM 取代對話已啟用，等待初始訊息完成後觸發 LLM 對話");
        const waitForInitialMessageTypewriter = () => {
          if (mpuTypewriterTimer !== null) {
            setTimeout(waitForInitialMessageTypewriter, 60);
          } else {
            setTimeout(function () {
              mpu_nextmsg('startup');
            }, 1500);
          }
        };
        waitForInitialMessageTypewriter();
      }

      mpuLogger.log("mpu_get_settings: 準備調用 startAutoTalk/stopAutoTalk, mpuAutoTalk =", mpuAutoTalk);
      if (mpuAutoTalk) startAutoTalk();
      else stopAutoTalk();
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
        const shouldTrigger = mpu_check_page_trigger(res.ai_trigger_pages);

        if (shouldTrigger) {
          const probability = parseInt(res.ai_probability || 10, 10);
          const roll = Math.floor(Math.random() * 100) + 1;

          if (roll <= probability) {
            setTimeout(function () {
              mpu_chat_context();
            }, 3000);
            return;
          }
        }
      }
    })
    .catch((error) => {
      mpuLogger.warn("Failed to get mpu_get_settings:", error);
    });

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

  jQuery(window).on("scroll", function () {
    const soffset = jQuery("#ukagaka_shell").attr("rel") || 0;
    if (jQuery(this).scrollTop() > soffset) jQuery("#ukagaka_shell").fadeIn();
    else jQuery("#ukagaka_shell").fadeOut();
  });
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

mpuLogger.log("腳本載入完成");
