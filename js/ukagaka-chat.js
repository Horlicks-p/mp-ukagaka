// ====== AI 上下文對話 ======
/**
 * 檢查頁面是否應該觸發 AI 對話
 * @param {string} triggerPages - 觸發條件字串，以逗號分隔（例如："is_single,is_page"）
 * @returns {boolean} 是否符合觸發條件
 */
function mpu_check_page_trigger(triggerPages) {
  if (!triggerPages) {
    mpuLogger.log("mpu_check_page_trigger: triggerPages 為空，返回 false");
    return false;
  }

  const conditions = triggerPages.split(",").map((s) => s.trim().toLowerCase());
  const path = window.location.pathname;
  const url = window.location.href;

  mpuLogger.log("mpu_check_page_trigger: 檢查條件 =", conditions, ", path =", path);

  // 檢查各種 WordPress 條件
  for (let condition of conditions) {
    condition = condition.trim();
    if (!condition) continue;

    // is_single: 單篇文章頁面
    if (condition === "is_single") {
      // 排除首頁和歸檔頁面（優先檢查）
      const isHomePage = path === '/' || path === '' || path === '/wordpress' || path === '/wordpress/';
      const isSpecialPage = path.match(/^\/(category|tag|author|page|search|archive|feed|$)/);
      const isPagination = path.match(/\/page\/\d+/);
      
      if (isHomePage || isSpecialPage || isPagination) {
        mpuLogger.log("mpu_check_page_trigger: is_single 檢查 - 排除首頁/歸檔頁面", {
          path,
          isHomePage,
          isSpecialPage,
          isPagination,
          shouldTrigger: false
        });
        continue; // 跳過此條件，檢查下一個
      }
      
      // 檢查是否為單篇文章：
      // 1. URL 有日期格式 /YYYY/MM/DD/
      // 2. 或者 DOM 中有實際的文章內容（entry-title + entry-content）
      // 3. 或者 DOM 中有 article、main、.entry-content 等容器元素（適用於 single page）
      const hasDatePath = path.match(/\/\d{4}\/\d{2}\/\d{2}\//);
      const hasArticleContent = 
        document.querySelector('article .entry-title, article .entry-content') ||
        document.querySelector('.hentry .entry-title') ||
        document.querySelector('.single .entry-title');
      // 放寬條件：只要有 article、main 或 .entry-content 容器
      const hasContentContainer = 
        document.querySelector('article') ||
        document.querySelector('main') ||
        document.querySelector('.entry-content');
      
      const shouldTrigger = hasDatePath || hasArticleContent || hasContentContainer;
      
      mpuLogger.log("mpu_check_page_trigger: is_single 檢查", {
        path,
        hasDatePath,
        hasArticleContent,
        hasContentContainer,
        shouldTrigger
      });
      
      if (shouldTrigger) {
        return true;
      }
    }
    // is_page: 頁面
    else if (condition === "is_page") {
      // 簡單檢查：頁面通常沒有日期格式，且不是分類/標籤等
      const isNotSpecial = path.length > 1 &&
                          !path.match(/^\/(category|tag|author|search|archive|feed)/) &&
                          !path.match(/\/\d{4}\/\d{2}\/\d{2}\//);
      
      mpuLogger.log("mpu_check_page_trigger: is_page 檢查", {
        path,
        pathLength: path.length,
        isNotSpecial,
        shouldTrigger: isNotSpecial
      });
      
      if (isNotSpecial) {
        return true;
      }
    }
    // is_home: 首頁
    else if (condition === "is_home" || condition === "is_front_page") {
      const isHome = path === "/" || path.match(/^\/page\/\d+$/);
      
      mpuLogger.log("mpu_check_page_trigger: is_home/is_front_page 檢查", {
        path,
        isHome
      });
      
      if (isHome) {
        return true;
      }
    }
    // is_archive: 歸檔頁面
    else if (condition === "is_archive") {
      const isArchive = path.match(/^\/(category|tag|author|date)/);
      
      mpuLogger.log("mpu_check_page_trigger: is_archive 檢查", {
        path,
        isArchive
      });
      
      if (isArchive) {
        return true;
      }
    }
  }

  return false;
}

/**
 * 獲取頁面上下文資訊（標題、內容和發布日期）
 * @returns {{title: string, content: string, publishDate: string}} 包含頁面標題、內容和發布日期的物件
 */
function mpu_get_page_context() {
  // 優先從文章標題元素取得標題（避免 document.title 只顯示網站名稱的問題）
  // 注意：某些主題使用 h2 作為文章標題，h1 用於網站名稱
  let title = '';
  const titleElement = document.querySelector('article h1.entry-title') ||
                       document.querySelector('article h2.entry-title') ||
                       document.querySelector('article .entry-title') ||
                       document.querySelector('.entry-title') ||
                       document.querySelector('article h1:not(.site-title)') ||
                       document.querySelector('article h2') ||
                       document.querySelector('main h1:not(.site-title)') ||
                       document.querySelector('main h2') ||
                       document.querySelector('#content h1:not(.site-title)') ||
                       document.querySelector('#content h2') ||
                       document.querySelector('h1.post-title') ||
                       document.querySelector('h2.post-title') ||
                       document.querySelector('h1:not(.site-title)');
  
  if (titleElement && titleElement.textContent) {
    title = titleElement.textContent.trim();
  }
  
  // Fallback: 如果找不到文章標題元素，使用 document.title
  if (!title) {
    title = document.title;
  }

  // 從 article, main, 或 .entry-content 提取內容
  // 注意：不包含 document.body 以避免抓到導航列和頁尾雜訊
  let content = "";
  let publishDate = "";

  const article =
    document.querySelector("article") ||
    document.querySelector("main") ||
    document.querySelector(".entry-content") ||
    document.querySelector("#content");

  if (article) {
    // 抓取文字，將多個空白合併為一個，並限制長度
    content = article.innerText.replace(/\s+/g, " ").substring(0, 3000);
  }

  // 嘗試獲取文章發布日期（多種來源）
  // 1. WordPress 標準 <time> 元素（帶 datetime 屬性）
  const timeElement = document.querySelector("article time[datetime]") ||
                      document.querySelector("time.entry-date[datetime]") ||
                      document.querySelector("time.published[datetime]") ||
                      document.querySelector("time[datetime]");
  if (timeElement && timeElement.getAttribute("datetime")) {
    publishDate = timeElement.getAttribute("datetime");
  }

  // 2. 如果沒有 datetime，嘗試從 time 元素的文字內容獲取
  if (!publishDate && timeElement && timeElement.textContent) {
    publishDate = timeElement.textContent.trim();
  }

  // 3. 嘗試從 meta 標籤獲取
  if (!publishDate) {
    const metaDate = document.querySelector("meta[property='article:published_time']") ||
                     document.querySelector("meta[name='pubdate']") ||
                     document.querySelector("meta[name='date']");
    if (metaDate && metaDate.content) {
      publishDate = metaDate.content;
    }
  }

  // 4. 嘗試從常見的日期選擇器獲取
  if (!publishDate) {
    const dateElement = document.querySelector(".entry-date") ||
                        document.querySelector(".post-date") ||
                        document.querySelector(".date") ||
                        document.querySelector(".posted-on");
    if (dateElement && dateElement.textContent) {
      publishDate = dateElement.textContent.trim();
    }
  }

  return { title, content, publishDate };
}

/**
 * AI 上下文對話：根據當前頁面內容生成 AI 回應
 */
function mpu_chat_context() {
  // 新增：如果正在對話模式中，直接取消頁面感知 AI
  if (typeof mpuChatModeActive !== 'undefined' && mpuChatModeActive) {
    mpuLogger.log('mpu_chat_context: 對話模式中，跳過頁面感知 AI');
    return;
  }
  
  // 🔧 如果頁面感知 AI 正在進行中（包含打字），跳過新的觸發
  if (mpuAiContextInProgress) {
    mpuLogger.log('mpu_chat_context: 頁面感知進行中，跳過新觸發');
    return;
  }
  
  // 睡眠模式檢查：優先使用伺服器端時間（避免客戶端/伺服器時區差異）
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
    mpuLogger.log('🌙 睡眠模式（00:00-06:00）：跳過頁面感知 AI，讓角色好好休息');
    return;
  }
  
  const context = mpu_get_page_context();
  const contentLength = context.content ? context.content.length : 0;

  mpuLogger.log("mpu_chat_context: 頁面上下文檢查", {
    hasTitle: !!context.title,
    title: context.title,
    contentLength,
    hasContent: !!context.content
  });

  if (!context.title && !context.content) {
    mpuLogger.log("mpu_chat_context: 沒有標題和內容，跳過");
    return;
  }

  // 如果首次訪客打招呼正在進行中，跳過頁面感知 AI
  if (mpuGreetInProgress) {
    mpuLogger.log("mpu_chat_context: 首次訪客打招呼進行中，跳過");
    return;
  }

  if (contentLength < 300) {
    mpuLogger.log("mpu_chat_context: 內容長度不足 300 字（當前:", contentLength, "），跳過");
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
  
  // 檢測是否為自己的日記（根據標題前綴）
  let loadingMessage;
  const diaryPrefix = (typeof mpuL10n !== 'undefined' && mpuL10n.diaryTitlePrefix) ? mpuL10n.diaryTitlePrefix : '';
  const isOwnDiary = diaryPrefix && context.title && context.title.includes(diaryPrefix);
  
  if (isOwnDiary && typeof mpuL10n !== 'undefined' && mpuL10n.loadingOwnDiary) {
    loadingMessage = mpuL10n.loadingOwnDiary;
  } else {
    loadingMessage = (typeof mpuL10n !== 'undefined' && mpuL10n.loadingArticle) ? mpuL10n.loadingArticle : "（…ああ、記事か。どれどれ…）";
  }
  
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
  formData.append("publish_date", context.publishDate || "");

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

        // 觸發角色動畫（頁面感知是使用者觸發，強制播放動畫）
        if (typeof window.mpuCanvasManager !== 'undefined' && window.mpuCanvasManager.isCharacterMode) {
          window.mpuCanvasManager.triggerCharacterAnimation(true);
        }

        // 顯示表情（如果有的話）
        if (res.emoji && typeof window.mpuEmojiManager !== 'undefined') {
          window.mpuEmojiManager.showEmoji(res.emoji);
        }

        // 🔧 計時邏輯：打字完成 → displayDuration → autoTalkInterval
        // 這樣用戶可以自由設定 AI 回應的顯示時間
        if (mpuAiDisplayTimer !== null) {
          clearTimeout(mpuAiDisplayTimer);
          mpuAiDisplayTimer = null;
        }

        mpu_waitForTypewriterComplete(function () {
          // 打字完成後，開始 displayDuration 計時
          const displayDurationMs = mpuAiDisplayDuration * 1000;
          mpuAiDisplayTimer = setTimeout(function () {
            mpuAiDisplayTimer = null;
            mpuMessageBlocking = false;
            mpuAiContextInProgress = false;
            if (wasAutoTalkRunning && mpuAutoTalk) {
              startAutoTalk();
            }
          }, displayDurationMs);
        });
      } else {
        mpuLogger.warn("AI 對話失敗，使用預設對話系統:", res);

        // 檢查是否是速率限制錯誤
        const isRateLimit =
          res && res.error && res.error.includes("請求過於頻繁");

        if (isRateLimit) {
          const rateLimitMessage = (typeof mpuL10n !== 'undefined' && mpuL10n.apiMagicInsufficient) ? mpuL10n.apiMagicInsufficient : "…ちょっと待って。API魔力が足りない";
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
    // 🌙 睡眠模式檢查：讓芙莉蓮好好睡覺，不打擾訪客
    let isDeepSleep = false;
    if (typeof window.mpuInfo !== 'undefined' && typeof window.mpuInfo.isDeepSleepTime !== 'undefined') {
      isDeepSleep = window.mpuInfo.isDeepSleepTime;
    } else {
      const now = new Date();
      const hour = now.getHours();
      isDeepSleep = hour >= 0 && hour < 6;
    }
    if (isDeepSleep) {
      mpuLogger.log('🌙 睡眠模式：跳過初次訪客打招呼，讓角色好好休息');
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
        const loadingMessage = (typeof mpuL10n !== 'undefined' && mpuL10n.unknownVisitor) ? mpuL10n.unknownVisitor : "（…あ、知らない人間だ…）";
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

          // 觸發角色動畫（訪客問候是使用者觸發，強制播放動畫）
          if (typeof window.mpuCanvasManager !== 'undefined' && window.mpuCanvasManager.isCharacterMode) {
            window.mpuCanvasManager.triggerCharacterAnimation(true);
          }

          // 顯示表情（如果有的話）
          if (res.emoji && typeof window.mpuEmojiManager !== 'undefined') {
            window.mpuEmojiManager.showEmoji(res.emoji);
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
            const rateLimitMessage = (typeof mpuL10n !== 'undefined' && mpuL10n.apiMagicInsufficient) ? mpuL10n.apiMagicInsufficient : "…ちょっと待って。API魔力が足りない";
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

  // 檢查是否為睡眠模式且初始訊息為睡眠相關（優先使用伺服器端時間）
  let isDeepSleep = false;
  if (typeof window.mpuInfo !== 'undefined' && typeof window.mpuInfo.isDeepSleepTime !== 'undefined') {
    isDeepSleep = window.mpuInfo.isDeepSleepTime;
  } else {
    // 備用：使用客戶端時間（向後兼容）
    const now = new Date();
    const hour = now.getHours();
    isDeepSleep = hour >= 0 && hour < 6;
  }
  // 使用隱藏標記檢測睡眠模式（由 PHP 端統一添加）
  const isSleepMessage = initialMsg && initialMsg.includes('<!-- mpu-sleep -->');

  if (!hasShownInitialMsg) {
    // 睡眠模式下，如果初始訊息是睡眠相關的，不要覆蓋它
    if (isDeepSleep && isSleepMessage) {
      mpuLogger.log("🌙 睡眠模式：檢測到睡眠訊息，跳過載入訊息顯示");
      // 不顯示載入訊息，保持睡眠訊息
    } else {
      const loadingMessage = (typeof mpuL10n !== 'undefined' && mpuL10n.thinkingMessage) ? mpuL10n.thinkingMessage : "（えっと…何話せばいいかな…）";
      mpu_typewriter(loadingMessage, "#ukagaka_msg");
    }
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

          const showFirstMessage = function () {
            if (firstMessageTimer !== null) {
              clearTimeout(firstMessageTimer);
              firstMessageTimer = null;
            }

            if (firstMessageShown) {
              mpuLogger.log('loadExternalDialog: 嘗試重複顯示第一句對話，已阻止');
              return;
            }
            
            // 睡眠模式檢查：如果在未喚醒的睡眠模式下，跳過第一句對話
            if (typeof mpu_isUnawokenSleepMode === 'function' && mpu_isUnawokenSleepMode()) {
              mpuLogger.log('🌙 睡眠模式且尚未被喚醒：跳過第一句內建對話，保持睡眠訊息');
              firstMessageShown = true; // 標記為已處理，避免重複嘗試
              // 睡眠模式下不啟動自動對話
              return;
            }
            
            firstMessageShown = true;

            let first = 0;
            if (mpuDefaultMsg === 0 && resp.msg.length) {
              first = Math.floor(Math.random() * resp.msg.length);
            }
            mpu_typewriter(
              mpu_unescapeHTML(resp.msg[first] + (resp.auto_msg || "")),
              "#ukagaka_msg"
            );
            jQuery("#ukagaka_msgnum").html(first);

            // 等待第一句對話打字完成後啟動自動對話
            if (mpuAutoTalk) {
              mpu_waitForTypewriterComplete(function() {
                startAutoTalk();
              });
            }
          };

          // 使用通用函數等待當前打字效果完成
          mpu_waitForTypewriterComplete(function() {
            if (!firstMessageShown) {
              firstMessageTimer = setTimeout(showFirstMessage, 1000);
            }
          });
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

// ====== 互動對話模式 ======
// 對話模式狀態
let mpuChatModeActive = false;
let mpuChatHistory = [];
let mpuChatRequesting = false;
let mpuEnableChatMode = false; // 後台設定：是否啟用互動對話功能
const MPU_CHAT_HISTORY_KEY = 'mpu_chat_history';
const MPU_MAX_CHAT_HISTORY = 20;

/**
 * 從 localStorage 載入對話歷史
 */
function mpu_loadChatHistory() {
    // 使用通用存儲函數，支援多層後備機制
    const stored = mpu_getLocal(MPU_CHAT_HISTORY_KEY);
    if (stored && Array.isArray(stored)) {
        mpuChatHistory = stored.slice(-MPU_MAX_CHAT_HISTORY);
        return true;
    }
    mpuChatHistory = [];
    return false;
}

/**
 * 儲存對話歷史到 localStorage
 */
function mpu_saveChatHistory() {
    // 使用通用存儲函數，支援多層後備機制
    const toSave = mpuChatHistory.slice(-MPU_MAX_CHAT_HISTORY);
    mpu_setLocal(MPU_CHAT_HISTORY_KEY, toSave);
}

/**
 * 清除對話歷史
 */
function mpu_clearChatHistory() {
    mpuChatHistory = [];
    // 使用通用存儲函數刪除
    mpu_delLocal(MPU_CHAT_HISTORY_KEY);
}

/**
 * 切換對話模式
 * @param {boolean} enable - 是否啟用對話模式
 */
function mpu_toggleChatMode(enable) {
    const $msgbox = jQuery('#ukagaka_msgbox');
    const $chatInput = jQuery('#ukagaka_chat_input');
    const $input = jQuery('#mpu_user_input');
    
    if (typeof enable === 'undefined') {
        enable = !mpuChatModeActive;
    }
    
    mpuChatModeActive = enable;
    
    if (enable) {
        // 進入對話模式
        mpuLogger.log('進入互動對話模式');
        
        // 暫停自動對話
        if (mpuAutoTalkTimer !== null) {
            stopAutoTalk();
        }
        
        // 載入對話歷史（用於上下文，但不顯示）
        mpu_loadChatHistory();
        
        // 觸發角色喚醒動畫（睡眠模式時會先喚醒）
        if (typeof window.mpuCanvasManager !== 'undefined' && window.mpuCanvasManager.isCharacterMode) {
            // 檢查是否為睡眠模式（需要喚醒動畫）- 使用通用函數
            const needsWakeUp = typeof mpu_isUnawokenSleepMode === 'function' && mpu_isUnawokenSleepMode();
            
            if (needsWakeUp) {
                // 檢查是否有喚醒動畫文件（通用方法，支援各種角色管理器）
                const hasWakeUpAnimation = typeof window.mpuCanvasManager !== 'undefined' &&
                                          typeof window.mpuCanvasManager.hasWakeUpAnimation === 'function' &&
                                          window.mpuCanvasManager.hasWakeUpAnimation();
                
                if (hasWakeUpAnimation) {
                    // 有喚醒動畫：先淡出對話框（隱藏 ZZZ），等待喚醒動畫完成後再顯示
                    $msgbox.fadeOut(1000, function() {
                        // 在對話框隱藏後，開始喚醒動畫（skipBookFlip = true：不翻書）
                        window.mpuCanvasManager.triggerCharacterAnimation(true, function() {
                            // 喚醒動畫完成後，顯示輸入框和歡迎訊息
                            $msgbox.addClass('chat-mode');
                            $chatInput.slideDown(400, function() {
                                showWelcome();
                            });
                        }, true); // skipBookFlip = true：開啟對話時不翻書
                    });
                } else {
                    // 沒有喚醒動畫：直接顯示輸入框（跳過淡出步驟）
                    $msgbox.addClass('chat-mode');
                    $chatInput.slideDown(400, function() {
                        showWelcome();
                    });
                }
            } else {
                // 非睡眠模式：正常流程（不觸發動畫，只在回答問題時播放）
                $chatInput.slideDown(400);
                $msgbox.addClass('chat-mode');
                
                // 直接顯示歡迎訊息，不播放動畫
                showWelcome();
            }
        } else {
            // 非角色動畫模式：顯示輸入框並直接顯示歡迎訊息
            $chatInput.slideDown(400);
            $msgbox.addClass('chat-mode');
            showWelcome();
        }
        
        function showWelcome() {
            // 顯示歡迎訊息（不觸發動畫，只在回答問題時播放）
            const welcomeMsg = (typeof mpuL10n !== 'undefined' && mpuL10n.chatWelcome) 
                ? mpuL10n.chatWelcome 
                : '有什麼想聊的嗎？';
            mpu_typewriter(welcomeMsg, '#ukagaka_msg', null, true); // true = skipCharacterAnimation
            
            // 確保對話框可見
            if ($msgbox.is(':hidden')) {
                mpu_showmsg(400);
            }
            
            // 聚焦輸入框
            setTimeout(() => $input.focus(), 250);
        }
        
    } else {
        // 退出對話模式
        mpuLogger.log('退出互動對話模式');
        
        // 隱藏輸入框
        $chatInput.slideUp(400);
        $msgbox.removeClass('chat-mode');
        
        // 設置訊息阻擋，防止退出後立即說話
        mpuMessageBlocking = true;
        
        // 顯示「結束對話」的訊息（不觸發動畫，只在回答問題時播放）
        const exitMsg = (typeof mpuL10n !== 'undefined' && mpuL10n.chatExit) 
            ? mpuL10n.chatExit 
            : '……';
        // 使用 skipAnimation 參數來跳過動畫
        mpu_typewriter(exitMsg, '#ukagaka_msg', null, true); // true = skipCharacterAnimation
        
        // 延遲 5 秒後恢復正常狀態
        setTimeout(() => {
            // 再次確認還沒重新進入對話模式
            if (!mpuChatModeActive) {
                mpuMessageBlocking = false;
                
                // 顯示一條隨機對話
                if (window.mpuMsgList && Array.isArray(window.mpuMsgList.msg) && window.mpuMsgList.msg.length > 0) {
                    const msgArr = window.mpuMsgList.msg;
                    const auto = window.mpuMsgList.auto_msg || '';
                    const randomIdx = Math.floor(Math.random() * msgArr.length);
                    mpu_typewriter(mpu_unescapeHTML(msgArr[randomIdx] + auto), '#ukagaka_msg');
                }
                
                // 恢復自動對話
                if (mpuAutoTalk) {
                    startAutoTalk();
                }
            }
        }, 5000);
    }
}

/**
 * 輕量級 Markdown 解析（處理粗體、斜體和行內代碼）
 * 
 * 用於增強 AI 對話的閱讀體驗，將常見的 Markdown 語法轉換為 HTML。
 * 
 * @param {string} text - 原始文字
 * @returns {string} 轉換後的 HTML
 */
function mpu_parseMarkdown(text) {
    if (!text || typeof text !== 'string') return text;
    
    return text
        // 處理粗體 **text** 或 __text__
        .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
        .replace(/__(.+?)__/g, '<strong>$1</strong>')
        // 處理斜體 *text* 或 _text_（排除已處理的粗體）
        .replace(/\*([^*]+)\*/g, '<em>$1</em>')
        .replace(/\b_([^_]+)_\b/g, '<em>$1</em>')
        // 處理行內代碼 `code`
        .replace(/`([^`]+)`/g, '<code style="background:#f0f0f0;padding:1px 4px;border-radius:3px;font-family:monospace;font-size:0.9em;">$1</code>');
}

/**
 * 發送用戶訊息（簡化版：只顯示春菜回覆）
 */
function mpu_sendUserMessage() {
    const $input = jQuery('#mpu_user_input');
    const message = $input.val().trim();
    
    if (!message || mpuChatRequesting) {
        if (mpuChatRequesting) {
            mpuLogger.log('正在等待回應，請稍候');
        }
        return;
    }
    
    // 指令攔截：/reset 或 /clear 清除對話歷史
    if (message === '/reset' || message === '/clear') {
        mpu_clearChatHistory();
        $input.val('');
        mpu_typewriter('（記憶を消去しました...）對話歷史已清除。', '#ukagaka_msg');
        mpuLogger.log('對話歷史已清除');
        return;
    }
    
    // 指令攔截：/help 顯示可用指令
    if (message === '/help') {
        $input.val('');
        const helpText = '【可用指令】\n/reset - 清除對話歷史\n/clear - 同上\n/help - 顯示此說明';
        mpu_typewriter(helpText, '#ukagaka_msg');
        return;
    }
    
    mpuLogger.log('發送用戶訊息:', message);
    
    // 1. UI 防呆：清空並鎖定輸入框
    $input.val('').prop('disabled', true);
    mpuChatRequesting = true;
    
    // 添加用戶訊息到歷史（用於上下文，但不顯示）
    mpuChatHistory.push({
        role: 'user',
        content: message,
        timestamp: Date.now()
    });
    
    // 顯示思考中
    jQuery('#ukagaka_msg').html('（…えっと<span class="mpu-thinking"></span>）');
    
    // 獲取頁面上下文（複用現有函數）
    const pageContext = mpu_get_page_context();
    
    // 發送 AJAX 請求
    const formData = new FormData();
    formData.append('action', 'mpu_user_chat');
    if (typeof mpuNonce !== 'undefined' && mpuNonce) {
        formData.append('mpu_nonce', mpuNonce);
    }
    formData.append('message', message);
    formData.append('history', JSON.stringify(mpuChatHistory.slice(-10)));
    // 新增：傳送頁面資訊
    formData.append('page_title', pageContext.title || '');
    formData.append('page_content', (pageContext.content || '').substring(0, 2000)); // 裁切節省 Token
    
    mpuFetch(mpuurl, {
        method: 'POST',
        body: formData,
        timeout: 60000,
        retries: 1,
        requestId: 'mpu_user_chat',
        cancelPrevious: true
    })
    .then(res => {
        // 2. 幽靈說話檢查：如果對話模式已關閉，捨棄回應
        if (!mpuChatModeActive) {
            mpuLogger.log('對話模式已關閉，捨棄本次 AI 回應');
            return;
        }
        
        if (res && res.msg && !res.error) {
            const aiResponse = res.msg;
            
            // 添加 AI 回應到歷史
            mpuChatHistory.push({
                role: 'assistant',
                content: aiResponse,
                timestamp: Date.now()
            });
            
            // 3. 記憶功能：儲存對話歷史到 localStorage
            mpu_saveChatHistory();
            
            // 使用打字效果顯示春菜回覆（先解析 Markdown）
            mpu_typewriter(mpu_parseMarkdown(aiResponse), '#ukagaka_msg');
            
            // 觸發角色動畫（使用者發送訊息，強制播放）
            if (typeof window.mpuCanvasManager !== 'undefined' && window.mpuCanvasManager.isCharacterMode) {
                window.mpuCanvasManager.triggerCharacterAnimation(true);
            }

            // 顯示表情（如果有的話）
            if (res.emoji && typeof window.mpuEmojiManager !== 'undefined') {
                window.mpuEmojiManager.showEmoji(res.emoji);
            }
        } else {
            const errorMsg = res && res.error ? res.error : '抱歉，無法取得回應';
            mpu_typewriter(errorMsg, '#ukagaka_msg');
        }
    })
    .catch(error => {
        // 幽靈說話檢查
        if (!mpuChatModeActive) {
            mpuLogger.log('對話模式已關閉，捨棄錯誤訊息');
            return;
        }
        
        mpu_handle_error(error, 'mpu_sendUserMessage', {
            showToUser: false
        });
        mpu_typewriter('（…連線好像有點問題…）', '#ukagaka_msg');
    })
    .finally(() => {
        mpuChatRequesting = false;
        // 1. UI 防呆：解鎖輸入框
        $input.prop('disabled', false);
        // 只有在對話模式中才聚焦
        if (mpuChatModeActive) {
            $input.focus();
        }
    });
}

/**
 * HTML 轉義
 */
function mpu_escapeHTML(str) {
    if (!str) return '';
    return str
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

// 綁定對話模式事件
jQuery(document).ready(function() {
    // 第三個按鈕點擊事件：根據設定決定是對話還是切換春菜
    jQuery('#mpu_chat_toggle').on('click', function(e) {
        e.preventDefault();
        if (mpuEnableChatMode) {
            // 啟用互動對話模式
            mpu_toggleChatMode();
        } else {
            // 執行原本的角色切換功能
            if (typeof mpuChange === 'function') {
                mpuChange('');
            }
        }
    });
    
    // 輸入框 Enter 鍵發送
    jQuery('#mpu_user_input').on('keypress', function(e) {
        if (e.which === 13 && !e.shiftKey) {
            e.preventDefault();
            mpu_sendUserMessage();
        }
    });
    
    // OK 按鈕（✅）：對話模式中送出訊息，一般模式下一句
    jQuery('#mpu_ok_btn').on('click', function(e) {
        e.preventDefault();
        
        // 檢查是否正在處理裝飾物對話
        if (typeof window.mpuCanvasManager !== 'undefined' && window.mpuCanvasManager.decorationChatInProgress) {
            mpuLogger.log('裝飾物對話進行中，忽略按鈕點擊');
            return;
        }
        
        // 檢查訊息是否被阻擋
        if (mpuMessageBlocking) {
            mpuLogger.log('訊息被阻擋，忽略按鈕點擊');
            return;
        }
        
        // 賴床功能：如果是睡眠模式被喚醒，記錄 IP
        if (typeof window.mpuInfo !== 'undefined' && window.mpuInfo.isDeepSleepTime) {
            mpuLogger.log('🌅 喚醒角色！正在記錄 IP...');
            
            // 發送喚醒請求
            var wakeParams = new URLSearchParams({ action: 'mpu_wake_ghost' });
            if (typeof mpuNonce !== 'undefined') {
                wakeParams.append('mpu_nonce', mpuNonce);
            }
            var wakeUrl = mpuurl + '?' + wakeParams.toString();
            
            mpuFetch(wakeUrl, { timeout: 5000 })
                .then(function(res) {
                    mpuLogger.log('喚醒成功:', res);
                    // 更新本地狀態，避免重複喚醒請求
                    window.mpuInfo.isDeepSleepTime = false;
                })
                .catch(function(err) {
                    mpuLogger.warn('喚醒請求失敗，但不影響正常操作:', err);
                });
        }
        
        if (mpuChatModeActive) {
            mpu_sendUserMessage();
        } else {
            mpu_nextmsg('');
        }
    });
    
    // Cancel 按鈕（❌）：對話模式中退出對話，一般模式隱藏對話框
    jQuery('#mpu_cancel_btn').on('click', function(e) {
        e.preventDefault();
        
        // 檢查是否正在處理裝飾物對話
        if (typeof window.mpuCanvasManager !== 'undefined' && window.mpuCanvasManager.decorationChatInProgress) {
            mpuLogger.log('裝飾物對話進行中，忽略按鈕點擊');
            return;
        }
        
        // 檢查訊息是否被阻擋
        if (mpuMessageBlocking) {
            mpuLogger.log('訊息被阻擋，忽略按鈕點擊');
            return;
        }
        
        if (mpuChatModeActive) {
            // 退出對話模式，回到自言自語模式
            mpu_toggleChatMode(false);
            mpuLogger.log('退出對話模式');
        } else {
            mpu_hidemsg('');
        }
    });
    
    mpuLogger.log('互動對話模式已初始化');
});

