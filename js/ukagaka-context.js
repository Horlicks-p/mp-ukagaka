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

  mpuLogger.log(
    "mpu_check_page_trigger: 檢查條件 =",
    conditions,
    ", path =",
    path,
  );

  // 檢查各種 WordPress 條件
  for (let condition of conditions) {
    condition = condition.trim();
    if (!condition) continue;

    // is_single: 單篇文章頁面
    if (condition === "is_single") {
      // 排除首頁和歸檔頁面（優先檢查）
      // 只排除明確的首頁路徑，不排除可能是單篇文章的路徑
      const isRootPath = path === "/" || path === "";
      const normalizedPath = path.endsWith("/") ? path.slice(0, -1) : path;
      const pathParts = normalizedPath.split("/").filter((p) => p.length > 0);
      const firstPathPart = pathParts[0] || "";
      const isUrlEncoded = firstPathPart.includes("%");
      const hasHyphen = firstPathPart.includes("-");

      // 只有當路徑段只有一個，且不包含 URL 編碼，且不包含連字符，且不是日期格式或特殊頁面時，才視為首頁
      const isSubdirectoryHome =
        pathParts.length === 1 &&
        !isUrlEncoded &&
        !hasHyphen &&
        !path.match(/\/\d{4}\/\d{2}\/\d{2}\//) &&
        !path.match(/^\/(category|tag|author|search|archive|feed)/);

      const isHomePage = isRootPath || isSubdirectoryHome;
      const isSpecialPage = path.match(
        /^\/(category|tag|author|page|search|archive|feed|$)/,
      );
      const isPagination = path.match(/\/page\/\d+/);

      if (isHomePage || isSpecialPage || isPagination) {
        mpuLogger.log(
          "mpu_check_page_trigger: is_single 檢查 - 排除首頁/歸檔頁面",
          {
            path,
            isHomePage,
            isSubdirectoryHome,
            isSpecialPage,
            isPagination,
            shouldTrigger: false,
          },
        );
        continue; // 跳過此條件，檢查下一個
      }

      // 檢查是否為單篇文章：
      // 1. URL 有日期格式 /YYYY/MM/DD/
      // 2. 或者 DOM 中有實際的文章內容（entry-title + entry-content）
      // 3. 或者 DOM 中有 article、main、.entry-content 等容器元素（適用於 single page）
      const hasDatePath = path.match(/\/\d{4}\/\d{2}\/\d{2}\//);
      const hasArticleContent =
        document.querySelector(
          "article .entry-title, article .entry-content",
        ) ||
        document.querySelector(".hentry .entry-title") ||
        document.querySelector(".single .entry-title");
      // 放寬條件：只要有 article、main 或 .entry-content 容器
      const hasContentContainer =
        document.querySelector("article") ||
        document.querySelector("main") ||
        document.querySelector(".entry-content");

      const shouldTrigger =
        hasDatePath || hasArticleContent || hasContentContainer;

      mpuLogger.log("mpu_check_page_trigger: is_single 檢查", {
        path,
        hasDatePath,
        hasArticleContent,
        hasContentContainer,
        shouldTrigger,
      });

      if (shouldTrigger) {
        return true;
      }
    }
    // is_page: 頁面
    else if (condition === "is_page") {
      // 簡單檢查：頁面通常沒有日期格式，且不是分類/標籤等
      const isNotSpecial =
        path.length > 1 &&
        !path.match(/^\/(category|tag|author|search|archive|feed)/) &&
        !path.match(/\/\d{4}\/\d{2}\/\d{2}\//);

      mpuLogger.log("mpu_check_page_trigger: is_page 檢查", {
        path,
        pathLength: path.length,
        isNotSpecial,
        shouldTrigger: isNotSpecial,
      });

      if (isNotSpecial) {
        return true;
      }
    }
    // is_home: 首頁
    else if (condition === "is_home" || condition === "is_front_page") {
      const isRootPath = path === "/" || path === "";
      const normalizedPath = path.endsWith("/") ? path.slice(0, -1) : path;
      const pathParts = normalizedPath.split("/").filter((p) => p.length > 0);
      const isSubdirectoryHome =
        pathParts.length === 1 &&
        !path.match(/\/\d{4}\/\d{2}\/\d{2}\//) &&
        !path.match(/^\/(category|tag|author|search|archive|feed)/);

      const isHomePagination = path.match(/^(\/[^\/]+)?\/page\/\d+$/);
      const hasMultipleArticles =
        document.querySelectorAll("article").length > 1;
      const hasSinglePostFeatures =
        document.querySelector("article.single") ||
        document.querySelector(".single article") ||
        path.match(/\/\d{4}\/\d{2}\/\d{2}\//);

      const isHome =
        isRootPath ||
        isSubdirectoryHome ||
        isHomePagination ||
        (hasMultipleArticles && !hasSinglePostFeatures);

      mpuLogger.log("mpu_check_page_trigger: is_home/is_front_page 檢查", {
        path,
        normalizedPath,
        pathParts,
        isRootPath,
        isSubdirectoryHome,
        isHomePagination,
        hasMultipleArticles,
        hasSinglePostFeatures,
        isHome,
      });

      if (isHome) {
        return true;
      }
    }
    // is_archive: 歸檔頁面（包含 category、tag、author、date 等所有歸檔）
    else if (condition === "is_archive") {
      const isArchive = path.match(/^\/(category|tag|author|date)/);

      mpuLogger.log("mpu_check_page_trigger: is_archive 檢查", {
        path,
        isArchive,
      });

      if (isArchive) {
        return true;
      }
    }
    // is_category: 分類頁面
    else if (condition === "is_category") {
      const isCategory = path.match(/^\/category\//);

      mpuLogger.log("mpu_check_page_trigger: is_category 檢查", {
        path,
        isCategory,
      });

      if (isCategory) {
        return true;
      }
    }
    // is_tag: 標籤頁面
    else if (condition === "is_tag") {
      const isTag = path.match(/^\/tag\//);

      mpuLogger.log("mpu_check_page_trigger: is_tag 檢查", {
        path,
        isTag,
      });

      if (isTag) {
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
  let title = "";
  const titleElement =
    document.querySelector("article h1.entry-title") ||
    document.querySelector("article h2.entry-title") ||
    document.querySelector("article .entry-title") ||
    document.querySelector(".entry-title") ||
    document.querySelector("article h1:not(.site-title)") ||
    document.querySelector("article h2") ||
    document.querySelector("main h1:not(.site-title)") ||
    document.querySelector("main h2") ||
    document.querySelector("#content h1:not(.site-title)") ||
    document.querySelector("#content h2") ||
    document.querySelector("h1.post-title") ||
    document.querySelector("h2.post-title") ||
    document.querySelector("h1:not(.site-title)");

  if (titleElement && titleElement.textContent) {
    title = titleElement.textContent.trim();
  }

  // Fallback: 如果找不到文章標題元素，使用 document.title
  if (!title) {
    title = document.title;
  }

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
  const timeElement =
    document.querySelector("article time[datetime]") ||
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
    const metaDate =
      document.querySelector("meta[property='article:published_time']") ||
      document.querySelector("meta[name='pubdate']") ||
      document.querySelector("meta[name='date']");
    if (metaDate && metaDate.content) {
      publishDate = metaDate.content;
    }
  }

  // 4. 嘗試從常見的日期選擇器獲取
  if (!publishDate) {
    const dateElement =
      document.querySelector(".entry-date") ||
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
  if (typeof mpuChatModeActive !== "undefined" && mpuChatModeActive) {
    mpuLogger.log("mpu_chat_context: 對話模式中，跳過頁面感知 AI");
    return;
  }

  // 🔧 如果頁面感知 AI 正在進行中（包含打字），跳過新的觸發
  if (mpuAiContextInProgress) {
    mpuLogger.log("mpu_chat_context: 頁面感知進行中，跳過新觸發");
    return;
  }

  // 睡眠模式檢查：優先使用伺服器端時間（避免客戶端/伺服器時區差異）
  const isDeepSleep = mpu_isDeepSleepTime();
  if (isDeepSleep) {
    mpuLogger.log(
      "🌙 睡眠模式（00:00-06:00）：跳過頁面感知 AI，讓角色好好休息",
    );
    return;
  }

  const context = mpu_get_page_context();
  const contentLength = context.content ? context.content.length : 0;

  mpuLogger.log("mpu_chat_context: 頁面上下文檢查", {
    hasTitle: !!context.title,
    title: context.title,
    contentLength,
    hasContent: !!context.content,
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
    mpuLogger.log(
      "mpu_chat_context: 內容長度不足 300 字（當前:",
      contentLength,
      "），跳過",
    );
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
  const diaryPrefix =
    typeof mpuL10n !== "undefined" && mpuL10n.diaryTitlePrefix
      ? mpuL10n.diaryTitlePrefix
      : "";
  const isOwnDiary =
    diaryPrefix && context.title && context.title.includes(diaryPrefix);

  if (isOwnDiary && typeof mpuL10n !== "undefined" && mpuL10n.loadingOwnDiary) {
    loadingMessage = mpuL10n.loadingOwnDiary;
  } else {
    loadingMessage =
      typeof mpuL10n !== "undefined" && mpuL10n.loadingArticle
        ? mpuL10n.loadingArticle
        : "（…ああ、記事か。どれどれ…）";
  }

  mpu_typewriter(
    `<span style="color: ${mpuAiTextColor};">${loadingMessage}</span>`,
    "#ukagaka_msg",
  );

  const formData = new FormData();
  formData.append("page_title", context.title);
  formData.append("page_content", context.content);
  formData.append("publish_date", context.publishDate || "");

  // [Fix] 傳送 session_id + history，讓後端在頁面感知 AI 成功後也能更新 checksum，
  // 防止下一輪 chat/user 驗證 400（裝飾品/身體點擊場景）。
  const contextSessionId = mpu_getOrCreateChatSessionId();
  if (contextSessionId) {
    formData.append("session_id", contextSessionId);
  }
  if (typeof window.mpuChatHistory !== "undefined" && window.mpuChatHistory.length > 0) {
    formData.append("history", JSON.stringify(window.mpuChatHistory.slice(-10)));
  }

  mpuFetch(mpuRestUrl + "chat/context", {
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
          "#ukagaka_msg",
        );

        // 觸發角色動畫（頁面感知是使用者觸發，強制播放動畫）
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

        // 記憶功能：將頁面感知對話存入對話歷史
        // 這樣當用戶切換到對話模式時，AI 會記得剛剛說過的話
        if (
          typeof window.mpuChatHistory !== "undefined" &&
          Array.isArray(window.mpuChatHistory)
        ) {
          // synthetic user 錨點：讓 LLM 能在後續對話中看到頁面感知的完整脈絡
          window.mpuChatHistory.push({
            role: "user",
            content: "（ページの内容を感知した）",
            type: "synthetic",
            timestamp: Date.now(),
          });
          window.mpuChatHistory.push({
            role: "assistant",
            content: res.msg,
            type: "context",
            timestamp: Date.now(),
          });

          if (typeof mpu_saveChatHistory === "function") {
            mpu_saveChatHistory();
            mpuLogger.log("mpu_chat_context: 對話已加入歷史並儲存");
          }
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
            // wasAutoTalkRunning 只記錄頁面感知觸發當下的狀態；
            // startup 被跳過時 auto-talk 從未啟動，wasAutoTalkRunning = false，
            // 但 mpuAutoTalk 仍為 true，因此改用 mpuAutoTalk 作為判斷依據。
            if (mpuAutoTalk && !mpuAutoTalkTimer) {
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
                "#ukagaka_msg",
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
              "#ukagaka_msg",
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
          "#ukagaka_msg",
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
  const visitorUrl = `${mpuRestUrl}visitor-info`;

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
