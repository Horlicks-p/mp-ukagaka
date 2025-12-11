// ====== AI 上下文對話 ======
/**
 * 檢查頁面是否應該觸發 AI
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
      // 排除分類、標籤、作者、頁面等
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
 * 獲取頁面上下文
 */
function mpu_get_page_context() {
  const title = document.title;

  // 從 article, main, 或 .entry-content 提取內容
  let content = "";

  // ★★★ 修改：移除了 || document.body 以避免抓到導航列和頁尾雜訊 ★★★
  const article =
    document.querySelector("article") ||
    document.querySelector("main") ||
    document.querySelector(".entry-content") ||
    document.querySelector("#content"); // 多加一個常見的 ID

  if (article) {
    // 抓取文字，將多個空白合併為一個，並限制長度
    content = article.innerText.replace(/\s+/g, " ").substring(0, 3000);
  }

  return { title, content };
}

/**
 * AI 上下文對話
 */
function mpu_chat_context() {
  const context = mpu_get_page_context();
  const contentLength = context.content ? context.content.length : 0;

  if (!context.title && !context.content) {
    return;
  }

  // 檢查文章內容長度：小於 500 字時不觸發 AI
  if (contentLength < 500) {
    // 使用正常對話系統
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
      if (jQuery("#ukagaka_msgbox").is(":hidden")) mpu_showmsg(200);
    }
    return;
  }

  // ★★★ 停止自動對話，避免被覆蓋 ★★★
  const wasAutoTalkRunning = mpuAutoTalkTimer !== null;
  if (wasAutoTalkRunning) {
    stopAutoTalk();
  }

  // ★★★ 設置頁面感知 AI 正在進行中的標誌，防止 LLM 自發性對話打岔 ★★★
  mpuAiContextInProgress = true;

  // 顯示載入訊息，應用設定的文字顏色
  if (jQuery("#ukagaka_msgbox").is(":hidden")) mpu_showmsg(200);
  const loadingMessage = "（…ああ、記事か。どれどれ…）";
  mpu_typewriter(
    `<span style="color: ${mpuAiTextColor};">${loadingMessage}</span>`,
    "#ukagaka_msg"
  );

  // 發送 AJAX 請求
  const formData = new FormData();
  formData.append("action", "mpu_chat_context");
  // 如果 nonce 存在，則添加（非強制要求）
  if (typeof mpuNonce !== "undefined" && mpuNonce) {
    formData.append("mpu_nonce", mpuNonce);
  }
  formData.append("page_title", context.title);
  formData.append("page_content", context.content);

  mpuFetch(mpuurl, {
    method: "POST",
    body: formData,
    cancelPrevious: true, // 取消之前的 AI 對話請求
    requestId: "mpu_chat_context", // 使用固定 ID 以便取消
    timeout: 60000, // AI 請求可能需要更長時間，設置 60 秒超時
    retries: 1, // AI 請求只重試 1 次
  })
    .then((res) => {
      if (res && res.msg && !res.error) {
        const aiResponse = mpu_unescapeHTML(res.msg);
        mpu_typewriter(
          `<span style="color: ${mpuAiTextColor};">${aiResponse}</span>`,
          "#ukagaka_msg"
        );

        // ★★★ 清除之前的計時器（如果有）★★★
        if (mpuAiDisplayTimer !== null) {
          clearTimeout(mpuAiDisplayTimer);
          mpuAiDisplayTimer = null;
        }

        // ★★★ 設置計時器，在指定時間後恢復自動對話 ★★★
        const displayDurationMs = mpuAiDisplayDuration * 1000;
        mpuAiDisplayTimer = setTimeout(function () {
          mpuAiDisplayTimer = null;
          // ★★★ 清除頁面感知 AI 正在進行中的標誌 ★★★
          mpuAiContextInProgress = false;
          if (wasAutoTalkRunning && mpuAutoTalk) {
            startAutoTalk();
          }
        }, displayDurationMs);
      } else {
        mpuLogger.warn("AI 對話失敗，使用預設對話系統:", res);

        // ★★★ 檢查是否是速率限制錯誤 ★★★
        const isRateLimit =
          res && res.error && res.error.includes("請求過於頻繁");

        if (isRateLimit) {
          // 顯示速率限制訊息
          const rateLimitMessage = "…ちょっと待って。API魔力が足りない";
          mpu_typewriter(
            `<span style="color: ${mpuAiTextColor};">${rateLimitMessage}</span>`,
            "#ukagaka_msg"
          );

          // 顯示 8 秒後恢復正常對話
          setTimeout(function () {
            // ★★★ 清除頁面感知 AI 正在進行中的標誌 ★★★
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
            // 恢復自動對話
            if (wasAutoTalkRunning && mpuAutoTalk) {
              startAutoTalk();
            }
          }, 8000);
        } else {
          // 其他錯誤，直接恢復正常對話
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
          // ★★★ 清除頁面感知 AI 正在進行中的標誌 ★★★
          mpuAiContextInProgress = false;
          // ★★★ AI 對話失敗時也要恢復自動對話 ★★★
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

      // 顯示錯誤訊息並恢復正常對話
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
      // ★★★ 清除頁面感知 AI 正在進行中的標誌 ★★★
      mpuAiContextInProgress = false;
      // ★★★ 錯誤時也要恢復自動對話 ★★★
      if (wasAutoTalkRunning && mpuAutoTalk) {
        startAutoTalk();
      }
    });
}

/**
 * 調試用：手動測試訪客資訊獲取
 * 在瀏覽器控制台輸入：mpu_test_visitor_info()
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
 * 首次訪客打招呼
 */
function mpu_greet_first_visitor(settings) {
  return new Promise((resolve, reject) => {
    // ★★★ 立即暫停自動對話，防止被打岔 ★★★
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
        // 調試模式：顯示基本訪客資訊
        mpuLogger.log("訪客資訊:", {
          referrer: visitorInfo.referrer || "無",
          referrer_host: visitorInfo.referrer_host || "無",
          search_engine: visitorInfo.search_engine || "無",
          country: visitorInfo.slimstat_country || "無",
        });

        // 顯示載入訊息
        if (jQuery("#ukagaka_msgbox").is(":hidden")) mpu_showmsg(200);
        const loadingMessage = "（…あ、知らない人間だ）";
        mpu_typewriter(
          `<span style="color: ${mpuAiTextColor};">${loadingMessage}</span>`,
          "#ukagaka_msg"
        );

        // 準備打招呼的資料
        const formData = new FormData();
        formData.append("action", "mpu_chat_greet");
        // 如果 nonce 存在，則添加（非強制要求）
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
        // 添加 Slimstat 提供的地理位置資訊
        formData.append(
          "country",
          visitorInfo.slimstat_country || visitorInfo.country || ""
        );
        formData.append(
          "city",
          visitorInfo.slimstat_city || visitorInfo.city || ""
        );

        // 發送打招呼請求
        return mpuFetch(mpuurl, {
          method: "POST",
          body: formData,
          cancelPrevious: true, // 取消之前的打招呼請求
          requestId: "mpu_chat_greet", // 使用固定 ID 以便取消
          timeout: 60000, // AI 請求可能需要更長時間，設置 60 秒超時
          retries: 1, // AI 請求只重試 1 次
        });
      })
      .then((res) => {
        if (res && res.msg && !res.error) {
          const greetingMessage = mpu_unescapeHTML(res.msg);

          mpu_typewriter(
            `<span style="color: ${mpuAiTextColor};">${greetingMessage}</span>`,
            "#ukagaka_msg"
          );

          // ★★★ 清除之前的計時器（如果有）★★★
          if (mpuAiDisplayTimer !== null) {
            clearTimeout(mpuAiDisplayTimer);
            mpuAiDisplayTimer = null;
          }

          // ★★★ 設置計時器，在指定時間後恢復自動對話 ★★★
          const displayDurationMs = mpuAiDisplayDuration * 1000;
          mpuAiDisplayTimer = setTimeout(function () {
            mpuAiDisplayTimer = null;
            // ★★★ 恢復自動對話（如果之前在運行）★★★
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

          // ★★★ 檢查是否是速率限制錯誤 ★★★
          const isRateLimit =
            res && res.error && res.error.includes("請求過於頻繁");

          if (isRateLimit) {
            // 顯示速率限制訊息
            const rateLimitMessage = "…ちょっと待って。API魔力が足りない";
            mpu_typewriter(
              `<span style="color: ${mpuAiTextColor};">${rateLimitMessage}</span>`,
              "#ukagaka_msg"
            );

            // 顯示 8 秒後恢復正常對話
            setTimeout(function () {
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
              // 恢復自動對話
              if (
                wasAutoTalkRunning &&
                settings.auto_talk === true &&
                mpuAutoTalk
              ) {
                startAutoTalk();
              }
              // 標記已訪問，避免重複嘗試
              resolve();
            }, 8000);
          } else {
            // 其他錯誤，直接恢復正常對話
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
            // ★★★ 失敗時也要恢復自動對話（如果之前在運行）★★★
            if (
              wasAutoTalkRunning &&
              settings.auto_talk === true &&
              mpuAutoTalk
            ) {
              startAutoTalk();
            }
            // 失敗時也標記已訪問，避免重複嘗試
            resolve();
          }
        }
      })
      .catch((error) => {
        mpu_handle_error(error, "mpu_greet_first_visitor", {
          showToUser: false, // 已經有 fallback 處理
        });

        // 顯示錯誤訊息並恢復正常對話
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

        // ★★★ 錯誤時也要恢復自動對話（如果之前在運行）★★★
        if (wasAutoTalkRunning && settings.auto_talk === true && mpuAutoTalk) {
          startAutoTalk();
        }
        // 錯誤時也標記已訪問，避免重複嘗試
        resolve();
      });
  });
}

// ====== 讀取外部對話 ======
function loadExternalDialog(file) {
  const pure = (file || "").replace(/^.*[\\/]/, "");

  const params = new URLSearchParams({
    action: "mpu_load_dialog",
    file: pure,
  });
  const url = `${mpuurl}?${params.toString()}`;

  // beforeSend:
  document.body.style.cursor = "wait";
  if (jQuery("#ukagaka_msgbox").is(":hidden")) mpu_showmsg(200);
  mpu_typewriter("（えっと…何話せばいいかな…）", "#ukagaka_msg");

  mpuFetch(url, {
    cancelPrevious: true, // 取消之前的載入請求
    requestId: `loadExternalDialog_${pure}`,
    timeout: 15000, // 15 秒超時
    retries: 1,
  })
    .then((resp) => {
      // success:
      if (typeof resp !== "object") {
        throw new Error(resp.error || "Expected JSON response from server.");
      }

      if (resp && !resp.error && Array.isArray(resp.msg)) {
        try {
          window.mpuMsgList = resp;
          mpuNextMode = resp.next_msg == 1 ? "random" : "sequential";
          mpuDefaultMsg = resp.default_msg == 1 ? 1 : 0;

          let first = 0;
          if (mpuDefaultMsg === 0 && resp.msg.length) {
            first = Math.floor(Math.random() * resp.msg.length);
          }
          mpu_typewriter(
            mpu_unescapeHTML(resp.msg[first] + (resp.auto_msg || "")),
            "#ukagaka_msg"
          );
          jQuery("#ukagaka_msgnum").html(first);

          if (mpuAutoTalk) startAutoTalk();
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
        jQuery("#ukagaka_msg").html(
          resp && resp.error ? resp.error : "無法取得對話資料"
        );
      }
      jQuery("#ukagaka").stop(true, true).fadeIn(200);
      document.body.style.cursor = "auto";
    })
    .catch((error) => {
      // error:
      mpu_handle_error(error, "loadExternalDialog", {
        showToUser: true,
        userMessage:
          debugMode || window.mpuDebugMode
            ? `載入對話文件失敗：${error.message}`
            : "載入對話文件失敗，請稍後再試。",
      });
      jQuery("#ukagaka").stop(true, true).fadeIn(200);
      document.body.style.cursor = "auto";
    });
}

// ====== 事件 ======
jQuery(document).ready(function () {
  // 【調試】確認 jQuery ready 已執行
  mpuLogger.log("jQuery ready 已執行");

  // 0. 【★ 修正】確保 jQuery.cookie 已初始化
  if (!mpu_init_jquery_cookie()) {
    mpuLogger.error("無法初始化 jQuery.cookie，某些功能可能無法正常工作");
  } else {
    mpuLogger.log("jQuery.cookie 已成功初始化");
  }

  // 1. 【★ 修正】 刪除 #show_ukagaka 的 handler

  // 2. 載入外部對話
  function initExternalDialog() {
    // 如果 LLM 取代對話已啟用，跳過內建對話載入
    if (typeof mpuPreSettings !== 'undefined' && mpuPreSettings.ollama_replace === true) {
      mpuLogger.log("LLM 取代對話已啟用，跳過內建對話載入");
      mpu_typewriter("（えっと…何話せばいいかな…）", "#ukagaka_msg");
      return;
    }

    const msgListElem = document.getElementById("ukagaka_msglist");
    if (
      msgListElem &&
      msgListElem.getAttribute("data-load-external") === "true"
    ) {
      const dialogFile = msgListElem.getAttribute("data-file");
      if (dialogFile) loadExternalDialog(dialogFile);
    } else {
      // 非外部檔案模式：初始化 mpuMsgList
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
      if (mpuAutoTalk && !mpuAutoTalkTimer) startAutoTalk();
    }
  }

  initExternalDialog();

  // 3. 從伺服器獲取最新設定
  const settingsParams = new URLSearchParams({ action: "mpu_get_settings" });
  const settingsUrl = `${mpuurl}?${settingsParams.toString()}`;

  mpuFetch(settingsUrl, {
    dedupe: true, // 設定請求可以去重
    requestId: "mpu_get_settings",
    timeout: 10000, // 10 秒超時
    retries: 2,
  })
    .then((res) => {
      // success:
      if (!res || typeof res !== "object") {
        mpuLogger.warn("mpu_get_settings: 無效的回應", res);
        return;
      }

      // ★★★ 詳細的設定調試輸出 ★★★
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
      // ★★★ 保存 LLM 取代對話設定 ★★★
      mpuOllamaReplaceDialogue = !!res.ollama_replace_dialogue;
      mpuLogger.log(
        "LLM 取代對話設定: " + (mpuOllamaReplaceDialogue ? "啟用" : "停用")
      );

      // 如果啟用了 LLM 取代對話，延遲後觸發 LLM 對話
      if (mpuOllamaReplaceDialogue) {
        mpuLogger.log("LLM 取代對話已啟用，延遲後觸發 LLM 對話");
        setTimeout(function() {
          mpu_nextmsg();
        }, 1500);
      }

      mpuLogger.log("mpu_get_settings: 準備調用 startAutoTalk/stopAutoTalk, mpuAutoTalk =", mpuAutoTalk);
      if (mpuAutoTalk) startAutoTalk();
      else stopAutoTalk();
      setAutoTalkUI();

      // ★★★ 首次訪客打招呼檢查 ★★★
      if (res.ai_enabled === true && res.ai_greet_first_visit === true) {
        // ★★★ 防止重複觸發 ★★★
        if (mpuGreetInProgress) {
          return;
        }

        const firstVisitCookie =
          "mpu_first_visit_" + (document.domain || "default");

        // 【★ 修正】確保 jQuery.cookie 已初始化
        if (typeof jQuery.cookie === "undefined") {
          mpu_init_jquery_cookie();
        }

        // 如果仍然無法使用，使用備用方案
        if (typeof jQuery.cookie === "undefined") {
          // 使用 mpu_getCookie 作為備用
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
          // 標記為正在進行中
          mpuGreetInProgress = true;
          // 獲取訪客資訊並打招呼
          mpu_greet_first_visitor(res)
            .then(() => {
              // 設置 cookie，標記已訪問（保存 1 年）
              if (typeof jQuery.cookie !== "undefined") {
                jQuery.cookie(firstVisitCookie, "1", {
                  expires: 365,
                  path: "/",
                });
              } else {
                // 備用方案：使用 mpu_setCookie
                mpu_setCookie(firstVisitCookie, "1", 365, "/");
              }
              // 重置標記
              mpuGreetInProgress = false;
            })
            .catch((error) => {
              mpu_handle_error(error, "首次訪客打招呼:catch2", {
                showToUser: false,
              });
              // 重置標記
              mpuGreetInProgress = false;
            });
          // 首次訪客打招呼時，跳過正常 AI 對話和正常對話系統
          return;
        }
      }

      // AI 自動觸發邏輯
      if (res.ai_enabled === true) {
        // 前端檢查頁面條件
        const shouldTrigger = mpu_check_page_trigger(res.ai_trigger_pages);

        if (shouldTrigger) {
          // 檢查機率
          const probability = parseInt(res.ai_probability || 10, 10);
          const roll = Math.floor(Math.random() * 100) + 1;

          if (roll <= probability) {
            // 等待 3 秒後觸發 AI
            setTimeout(function () {
              mpu_chat_context();
            }, 3000);
            return; // 如果觸發了 AI，就不使用正常對話系統
          }
        }
      }
    })
    .catch((error) => {
      mpuLogger.warn("Failed to get mpu_get_settings:", error);
    });

  // 4. 加入自動對話開關
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

  // 如果獲取設定失敗，仍然嘗試載入外部對話（向後兼容）
  // 這個調用會在獲取設定成功後被覆蓋（如果未啟用 LLM）

  // 5. 顯示/隱藏訊息
  jQuery("#show_msg").on("click", function () {
    if (jQuery("#ukagaka_msgbox").is(":hidden")) {
      mpu_showmsg(400);
      mpu_setLocal("mpuMsg", "show");
    } else {
      mpu_hidemsg(400);
      mpu_setLocal("mpuMsg", "hidden");
    }
  });

  // 6. 點擊春菜圖片
  jQuery("#ukagaka_img").on("click", function () {
    if (jQuery("#ukagaka_msgbox").is(":hidden")) mpu_showmsg(400);
    else mpu_nextmsg();
  });

  // 7. 擴展功能
  jQuery("#mpu_extend").on("click", function () {
    const extendParams = new URLSearchParams({ action: "mpu_extend" });
    const extendUrl = `${mpuurl}?${extendParams.toString()}`;

    // beforeSend:
    document.body.style.cursor = "wait";
    if (jQuery("#ukagaka").is(":hidden")) mpu_showrobot(400);
    else if (!jQuery("#ukagaka_msgbox").is(":hidden")) mpu_hidemsg(200);

    mpuFetch(extendUrl, {
      timeout: 10000, // 10 秒超時
      retries: 1,
    })
      .then((html) => {
        // success:
        if (typeof html !== "string")
          throw new Error("Expected HTML response.");
        mpu_showmsg(400);
        jQuery("#ukagaka_msg").html(html);
        document.body.style.cursor = "auto";
      })
      .catch((error) => {
        // error:
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

  // 8. 捲動 / 回頂 / 登出淡出
  jQuery(window).on("scroll", function () {
    const soffset = jQuery("#ukagaka_shell").attr("rel") || 0;
    if (jQuery(this).scrollTop() > soffset) jQuery("#ukagaka_shell").fadeIn();
    else jQuery("#ukagaka_shell").fadeOut();
  });
  jQuery("#toTop").on("click", function () {
    jQuery("body,html").animate({ scrollTop: 0 }, 800);
  });

  // 9. 【★ 修正】 顯示/隱藏春菜 (取代原 #remove 邏輯)
  jQuery("#mp_ukagaka").css("display", "block"); // 確保主容器可見
  jQuery("#remove").on("click", function () {
    const $ukagaka = jQuery("#ukagaka"); // 這是人物+對話的容器
    if ($ukagaka.is(":hidden")) {
      mpu_showrobot(400); // 淡入 #ukagaka
      mpu_setLocal("mpuRobot", "show"); //
    } else {
      mpu_hiderobot(400); // 淡出 #ukagaka
      mpu_setLocal("mpuRobot", "hidden"); //
    }
    return false;
  });

  // 10. 【★ 修正】 檢查春菜隱藏狀態
  const robotState = mpu_getLocal("mpuRobot"); //
  if (robotState === "hidden") {
    jQuery("#ukagaka").css("display", "none"); // 只隱藏人物+對話
    jQuery("#remove").html(mpuInfo.robot[0]); // 設為 "顯示春菜 ▲"
  } else {
    // 預設是顯示，按鈕文字應為 "隱藏春菜 ▼"
    jQuery("#remove").html(mpuInfo.robot[1]); // 設為 "隱藏春菜 ▼"
  }
});

// 視窗焦點/可見性（避免多計時器）
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

// 腳本載入完成日誌（在 mpuLogger 定義之後）
mpuLogger.log("腳本載入完成");
