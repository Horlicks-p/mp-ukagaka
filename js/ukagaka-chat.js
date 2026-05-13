// ====== 互動對話模式 ======
// 對話模式狀態
window.mpuChatModeActive = false;
window.mpuChatRequesting = false;
let mpuEnableChatMode = false; // 後台設定：是否啟用互動對話功能
const MPU_CHAT_HISTORY_KEY = "mpu_chat_history";
const MPU_CHAT_SESSION_KEY = "mpu_chat_session_id";
const MPU_MAX_CHAT_HISTORY = 40; // synthetic+assistant 各佔一則，20 個互動事件 = 40 entries

function mpu_generateChatSessionId() {
  if (
    typeof window !== "undefined" &&
    window.crypto &&
    typeof window.crypto.randomUUID === "function"
  ) {
    return window.crypto.randomUUID().replace(/-/g, "");
  }

  return (
    "mpu" +
    Date.now().toString(36) +
    Math.random().toString(36).slice(2, 12)
  ).toLowerCase();
}

function mpu_getOrCreateChatSessionId(forceNew = false) {
  if (!forceNew && window.mpuChatSessionId) {
    return window.mpuChatSessionId;
  }

  const stored = !forceNew ? mpu_getLocal(MPU_CHAT_SESSION_KEY) : null;
  if (!forceNew && typeof stored === "string" && stored) {
    window.mpuChatSessionId = stored;
    return window.mpuChatSessionId;
  }

  window.mpuChatSessionId = mpu_generateChatSessionId();
  mpu_setLocal(MPU_CHAT_SESSION_KEY, window.mpuChatSessionId);
  return window.mpuChatSessionId;
}

/**
 * 從 localStorage 載入對話歷史
 */
function mpu_loadChatHistory() {
  // 使用通用存儲函數，支援多層後備機制
  const stored = mpu_getLocal(MPU_CHAT_HISTORY_KEY);
  if (stored && Array.isArray(stored)) {
    window.mpuChatHistory = stored.slice(-MPU_MAX_CHAT_HISTORY);
    return true;
  }
  window.mpuChatHistory = [];
  return false;
}

/**
 * 儲存對話歷史到 localStorage
 */
function mpu_saveChatHistory() {
  // 使用通用存儲函數，支援多層後備機制
  const toSave = (window.mpuChatHistory || []).slice(-MPU_MAX_CHAT_HISTORY);
  mpu_setLocal(MPU_CHAT_HISTORY_KEY, toSave);
}

/**
 * 清除對話歷史
 */
function mpu_clearChatHistory() {
  window.mpuChatHistory = [];
  // 使用通用存儲函數刪除
  mpu_delLocal(MPU_CHAT_HISTORY_KEY);
  // 清空歷史時同步輪替 session，避免 checksum 將合法 reset 視為篡改
  mpu_getOrCreateChatSessionId(true);
}

/**
 * 切換對話模式
 * @param {boolean} enable - 是否啟用對話模式
 */
function mpu_toggleChatMode(enable) {
  const $msgbox = jQuery("#ukagaka_msgbox");
  const $chatInput = jQuery("#ukagaka_chat_input");
  const $input = jQuery("#mpu_user_input");

  if (typeof enable === "undefined") {
    enable = !window.mpuChatModeActive;
  }

  window.mpuChatModeActive = enable;

  if (enable) {
    // 進入對話模式
    mpuLogger.log("進入互動對話模式");

    // 暫停自動對話
    if (mpuAutoTalkTimer !== null) {
      stopAutoTalk();
    }

    // 載入對話歷史（用於上下文，但不顯示）
    mpu_loadChatHistory();

    // 觸發角色喚醒動畫（睡眠模式時會先喚醒）
    if (
      typeof window.mpuCanvasManager !== "undefined" &&
      window.mpuCanvasManager.isCharacterMode
    ) {
      // 檢查是否為睡眠模式（需要喚醒動畫）- 使用通用函數
      const needsWakeUp =
        typeof mpu_isUnawokenSleepMode === "function" &&
        mpu_isUnawokenSleepMode();

      if (needsWakeUp) {
        // 發送喚醒請求給後端
        if (typeof mpu_send_wake_up_request === "function") {
          mpu_send_wake_up_request();
        }

        // 檢查是否有喚醒動畫文件（通用方法，支援各種角色管理器）
        const hasWakeUpAnimation =
          typeof window.mpuCanvasManager !== "undefined" &&
          typeof window.mpuCanvasManager.hasWakeUpAnimation === "function" &&
          window.mpuCanvasManager.hasWakeUpAnimation();

        if (hasWakeUpAnimation) {
          // 有喚醒動畫：先淡出對話框（隱藏 ZZZ），等待喚醒動畫完成後再顯示
          $msgbox.fadeOut(1000, function () {
            // 在對話框隱藏後，開始喚醒動畫（skipBookFlip = true：不翻書）
            window.mpuCanvasManager.triggerCharacterAnimation(
              true,
              function () {
                // 喚醒動畫完成後，顯示輸入框和歡迎訊息
                $msgbox.addClass("chat-mode");
                $chatInput.slideDown(400, function () {
                  showWelcome();
                });
              },
              true,
            ); // skipBookFlip = true：開啟對話時不翻書
          });
        } else {
          // 沒有喚醒動畫：直接顯示輸入框（跳過淡出步驟）
          $msgbox.addClass("chat-mode");
          $chatInput.slideDown(400, function () {
            showWelcome();
          });
        }
      } else {
        // 非睡眠模式：正常流程（不觸發動畫，只在回答問題時播放）
        $chatInput.slideDown(400);
        $msgbox.addClass("chat-mode");

        // 直接顯示歡迎訊息，不播放動畫
        showWelcome();
      }
    } else {
      // 非角色動畫模式：顯示輸入框並直接顯示歡迎訊息
      $chatInput.slideDown(400);
      $msgbox.addClass("chat-mode");
      showWelcome();
    }

    function showWelcome() {
      // 顯示歡迎訊息（不觸發動畫，只在回答問題時播放）
      const welcomeMsg =
        typeof mpuL10n !== "undefined" && mpuL10n.chatWelcome
          ? mpuL10n.chatWelcome
          : "有什麼想聊的嗎？";
      mpu_typewriter(welcomeMsg, "#ukagaka_msg", null, true); // true = skipCharacterAnimation

      // 確保對話框可見
      if ($msgbox.is(":hidden")) {
        mpu_showmsg(400);
      }

      // 聚焦輸入框
      setTimeout(() => $input.focus(), 250);
    }
  } else {
    // 退出對話模式
    mpuLogger.log("退出互動對話模式");

    // 隱藏輸入框
    $chatInput.slideUp(400);
    $msgbox.removeClass("chat-mode");

    // 設置訊息阻擋，防止退出後立即說話
    mpuMessageBlocking = true;

    // 顯示「結束對話」的訊息（不觸發動畫，只在回答問題時播放）
    const exitMsg =
      typeof mpuL10n !== "undefined" && mpuL10n.chatExit
        ? mpuL10n.chatExit
        : "……";
    // 使用 skipAnimation 參數來跳過動畫
    mpu_typewriter(exitMsg, "#ukagaka_msg", null, true); // true = skipCharacterAnimation

    // 延遲 5 秒後恢復正常狀態
    setTimeout(() => {
      // 再次確認還沒重新進入對話模式
      if (!mpuChatModeActive) {
        mpuMessageBlocking = false;

        // 顯示一條隨機對話
        if (
          window.mpuMsgList &&
          Array.isArray(window.mpuMsgList.msg) &&
          window.mpuMsgList.msg.length > 0
        ) {
          const msgArr = window.mpuMsgList.msg;
          const auto = window.mpuMsgList.auto_msg || "";
          const randomIdx = Math.floor(Math.random() * msgArr.length);
          const exitContent = mpu_unescapeHTML(msgArr[randomIdx] + auto);
          mpu_typewriter(exitContent, "#ukagaka_msg");
          // 將隨機對話加入歷史，確保下次開啟互動對話模式有完整脈絡
          if (exitContent && Array.isArray(window.mpuChatHistory)) {
            window.mpuChatHistory.push({ role: "user", content: "（独り言）", type: "synthetic", timestamp: Date.now() });
            window.mpuChatHistory.push({ role: "assistant", content: exitContent, type: "auto_talk", timestamp: Date.now() });
            if (typeof mpu_saveChatHistory === "function") mpu_saveChatHistory();
          }
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
  if (!text || typeof text !== "string") return text;

  return (
    text
      // 處理粗體 **text** 或 __text__
      .replace(/\*\*(.+?)\*\*/g, "<strong>$1</strong>")
      .replace(/__(.+?)__/g, "<strong>$1</strong>")
      // 處理斜體 *text* 或 _text_（排除已處理的粗體）
      .replace(/\*([^*]+)\*/g, "<em>$1</em>")
      .replace(/\b_([^_]+)_\b/g, "<em>$1</em>")
      // 處理連結 [text](url)
      .replace(
        /\[([^\]]+)\]\(([^)]+)\)/g,
        '<a href="$2" target="_blank" rel="noopener noreferrer">$1</a>',
      )
      // 處理行內代碼 `code`
      .replace(
        /`([^`]+)`/g,
        '<code style="background:#f0f0f0;padding:1px 4px;border-radius:3px;font-family:monospace;font-size:0.9em;">$1</code>',
      )
  );
}

/**
 * 使用 SSE (Server-Sent Events) 獲取 AI 回應
 *
 * @param {string} url - 請求 URL
 * @param {object} options - Fetch 選項
 * @param {object} handlers - 事件處理器 { onStart, onDelta, onStatus, onNonce, onDone, onError }
 */
async function mpuFetchSSE(url, options, handlers) {
  const controller = options.controller || new AbortController();
  const signal = controller.signal;

  try {
    const sessionTok = typeof mpuEnsureSessionToken === 'function' ? await mpuEnsureSessionToken() : (window.mpuSessionToken || '');
    const response = await fetch(url, {
      ...options,
      signal,
      headers: {
        ...options.headers,
        "X-WP-Nonce": typeof mpuRestNonce !== "undefined" ? mpuRestNonce : "",
        "X-MPU-Session-Token": sessionTok,
      },
    });

    // [Fix Issue 2] JSON Fallback：若後端回傳 application/json (如 /debug_mcp 或標準錯誤)
    const contentType = response.headers.get("content-type");
    if (contentType && contentType.includes("application/json")) {
      const json = await response.json();
      if (response.ok) {
        if (handlers.onDone) handlers.onDone(json);
      } else {
        if (handlers.onError) handlers.onError(json);
      }
      return;
    }

    if (!response.ok) {
      const errorData = await response.json().catch(() => ({}));
      throw new Error(
        errorData.message || `HTTP error! status: ${response.status}`,
      );
    }

    const reader = response.body.getReader();
    const decoder = new TextDecoder();
    let lineBuffer = "";

    while (true) {
      const { done, value } = await reader.read();
      if (done) break;

      lineBuffer += decoder.decode(value, { stream: true });

      // [Fix Issue 1] 支援 Windows (\r\n\r\n) 與 Unix (\n\n) 換行符切分 Frame
      const frames = lineBuffer.split(/\r?\n\r?\n/);
      lineBuffer = frames.pop() || "";

      for (let frame of frames) {
        if (!frame.trim()) continue;

        // 拆分 Frame 內的每一行 (支援 \r\n 或 \n)
        const frameLines = frame.split(/\r?\n/);
        let eventName = "message";
        let dataStr = "";

        for (let line of frameLines) {
          if (line.startsWith("event: ")) {
            eventName = line.substring(7);
          } else if (line.startsWith("data: ")) {
            dataStr = line.substring(6);
          }
        }

        if (!dataStr) continue;

        let data;
        try {
          data = JSON.parse(dataStr);
        } catch (e) {
          data = dataStr;
        }

        switch (eventName) {
          case "start":
            if (handlers.onStart) handlers.onStart(data);
            break;
          case "delta":
            if (handlers.onDelta) handlers.onDelta(data);
            break;
          case "status":
            if (handlers.onStatus) handlers.onStatus(data);
            break;
          case "nonce":
            if (data.new_token && typeof mpuRestNonce !== "undefined") {
              window.mpuRestNonce = data.new_token;
              mpuLogger.log("REST Nonce refreshed via SSE");
            }
            if (handlers.onNonce) handlers.onNonce(data);
            break;
          case "done":
            if (handlers.onDone) handlers.onDone(data);
            return;
          case "error":
            if (handlers.onError) handlers.onError(data);
            return; // [Fix] 直接結束，避免 throw 導致 catch 再次觸發 onError
          case "ping":
            break;
        }
      }
    }
  } catch (error) {
    if (error.name === "AbortError") {
      mpuLogger.log("SSE Request aborted");
      // [Fix 漏洞 8] 通知呼叫端清理歷史狀態
      if (handlers.onAbort) handlers.onAbort();
    } else {
      if (handlers.onError) handlers.onError(error);
      throw error;
    }
  }
}

let mpuChatAbortController = null;

/**
 * 發送用戶訊息
 */
function mpu_sendUserMessage() {
  const $input = jQuery("#mpu_user_input");
  const message = $input.val().trim();

  if (!message || mpuChatRequesting) {
    if (mpuChatRequesting) {
      mpuLogger.log("正在等待回應，請稍候");
    }
    return;
  }

  // 指令攔截：/reset 或 /clear 清除對話歷史（僅管理員）
  if (message === "/reset" || message === "/clear") {
    if (mpuPreSettings && mpuPreSettings.is_admin) {
      mpu_clearChatHistory();
      $input.val("");
      mpu_typewriter("（記憶を消去しました...）", "#ukagaka_msg");
      mpuLogger.log("對話歷史已清除");
      return;
    }
    // 非管理員：不攔截，讓訊息流入下方 AI 路徑，由 visitor_rejection 引導角色拒絕
  }

  // 指令攔截：/remember 萃取並保存管理人記憶（僅管理員）
  // 非管理員不攔截，讓訊息流入下方 AI 路徑，由 visitor_rejection 引導角色拒絕
  if (message === "/remember" && mpuPreSettings && mpuPreSettings.is_admin) {
    $input.val("");
    const filteredHistory = (window.mpuChatHistory || [])
      .filter(function(m) {
        return (m.role === "user" || m.role === "assistant") && m.type !== "synthetic";
      })
      .slice(-20)
      .map(function(m) {
        return { role: m.role, content: (m.content || "").substring(0, 200) };
      });
    if (filteredHistory.length === 0) {
      mpu_typewriter("（会話履歴がないよ）", "#ukagaka_msg");
      return;
    }
    mpu_typewriter("（記憶を整理してる…）", "#ukagaka_msg");
    const nonce = (mpuPreSettings && mpuPreSettings.rest_nonce) ? mpuPreSettings.rest_nonce : "";
    fetch(mpuRestUrl + "memory/extract", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-WP-Nonce": nonce,
      },
      body: JSON.stringify({ history: filteredHistory }),
    })
      .then(function(r) { return r.json(); })
      .then(function(data) {
        if (data.status === "updated") {
          mpu_typewriter("（記憶を更新したよ）", "#ukagaka_msg");
        } else if (data.status === "no_memory") {
          mpu_typewriter("（保存できる記憶はなかったね）", "#ukagaka_msg");
        } else {
          mpu_typewriter("（記憶の更新に失敗したよ）", "#ukagaka_msg");
        }
      })
      .catch(function() {
        mpu_typewriter("（記憶の更新に失敗したよ）", "#ukagaka_msg");
      });
    return;
  }

  // /visitor-info と /check-spam-event は管理員専用
  // 非管理員：不攔截，讓訊息流入下方 AI 路徑，由 visitor_rejection 引導角色拒絕

  // 指令攔截：/help 顯示可用指令
  if (message === "/help") {
    $input.val("");
    let helpText =
      "【コマンド一覧】<br>/reset - 会話履歴を消去<br>/clear - 同上<br>/help - このヘルプを表示";
    if (mpuPreSettings && mpuPreSettings.is_admin) {
      helpText +=
        "<br>【管理者専用】<br>/visitor-info - 訪客情報を照会<br>/check-spam-event - スパム状況を確認<br>/debug_mcp - MCPツール診断<br>/remember - フリーレンに記憶を保存させる";
    }
    mpu_typewriter(helpText, "#ukagaka_msg");
    return;
  }

  mpuLogger.log("發送用戶訊息:", message);
  const chatSessionId = mpu_getOrCreateChatSessionId();

  // 1. UI 防呆：清空並鎖定輸入框
  $input.val("").prop("disabled", true);
  mpuChatRequesting = true;

  // 如果有舊請求，中止它
  if (mpuChatAbortController) {
    mpuChatAbortController.abort();
  }
  mpuChatAbortController = new AbortController();

  // 添加用戶訊息到歷史（用於上下文，但不顯示）
  window.mpuChatHistory.push({
    role: "user",
    content: message,
    type: "chat",
    timestamp: Date.now(),
  });
  // [Fix] 立即存檔，防止 F5 導致歷史遺失造成 Checksum Mismatch
  mpu_saveChatHistory();

  // 獲取頁面上下文（複用現有函數）
  const pageContext = mpu_get_page_context();

  // 準備 FormData
  const formData = new FormData();
  formData.append("message", message);
  formData.append("history", JSON.stringify(window.mpuChatHistory.slice(-20)));
  if (chatSessionId) {
    formData.append("session_id", chatSessionId);
  }
  formData.append("page_title", pageContext.title || "");
  formData.append(
    "page_content",
    (pageContext.content || "").substring(0, 2000),
  );

  // 判斷是否使用 Streaming (暫時預設啟用，若 provider 支援)
  const useStreaming =
    typeof mpuPreSettings !== "undefined" &&
    mpuPreSettings &&
    mpuPreSettings.streaming_enabled === true;

  if (useStreaming) {
    let fullResponse = "";
    const $msg = jQuery("#ukagaka_msg");
    $msg.html('（…えっと<span class="mpu-thinking"></span>）');

    // Streaming typewriter queue — 區域 timer，絕不碰全域 mpuTypewriterTimer
    let streamTypewriterTimer = null;
    let streamDisplayedText = "";
    let streamPendingText = "";
    let streamDone = false;
    let streamDoneData = null;
    let streamFinalized = false;

    function streamStartDrain() {
      if (streamTypewriterTimer !== null) return;
      streamTickDrain();
    }

    function streamTickDrain() {
      if (streamPendingText.length === 0) {
        streamTypewriterTimer = null;
        if (streamDone) streamFinalize(streamDoneData);
        return;
      }
      const chunkSize = Math.min(4, Math.max(1, Math.floor(streamPendingText.length / 80)));
      streamDisplayedText += streamPendingText.slice(0, chunkSize);
      streamPendingText = streamPendingText.slice(chunkSize);
      $msg.html(mpu_parseMarkdown(streamDisplayedText));
      streamTypewriterTimer = setTimeout(streamTickDrain, mpuTypewriterSpeed);
    }

    function streamFinalize(data) {
      if (streamFinalized) return;
      streamFinalized = true;
      const finalMsg = data.msg || fullResponse;
      window.mpuChatHistory.push({
        role: "assistant",
        content: finalMsg,
        type: "chat",
        timestamp: Date.now(),
      });
      mpu_saveChatHistory();
      mpuChatRequesting = false;
      $input.prop("disabled", false);
      if (window.mpuChatModeActive) $input.focus();
      if (data.emoji && typeof window.mpuEmojiManager !== "undefined") {
        window.mpuEmojiManager.showEmoji(data.emoji);
      }
      if (
        typeof window.mpuCanvasManager !== "undefined" &&
        window.mpuCanvasManager.isCharacterMode
      ) {
        window.mpuCanvasManager.triggerCharacterAnimation(true);
      }
    }

    mpuFetchSSE(
      mpuRestUrl + "chat/user-stream",
      {
        method: "POST",
        body: formData,
        controller: mpuChatAbortController,
      },
      {
        onStart: (data) => {
          mpuLogger.log("SSE Started:", data);
        },
        onDelta: (data) => {
          if (data.text) {
            fullResponse += data.text;
            if (streamDisplayedText === "" && streamPendingText === "") $msg.empty();
            streamPendingText += data.text;
            streamStartDrain();
          }
        },
        onStatus: (data) => {
          let statusMsg = "";

          if (data.type === "executing_tool" && data.tool) {
            // [Fix] 使用 mpuL10n 本地化模板
            const template =
              typeof mpuL10n !== "undefined" && mpuL10n.executingTool
                ? mpuL10n.executingTool
                : "正在執行工具：%s...";
            statusMsg = template.replace("%s", data.tool);
          } else if (data.message) {
            // Fallback
            statusMsg = data.message;
          }

          if (statusMsg) {
            $msg.html(`（…${statusMsg}<span class="mpu-thinking"></span>）`);
          }
        },
        onDone: (data) => {
          streamDone = true;
          streamDoneData = data;
          if (streamTypewriterTimer === null && streamPendingText.length === 0) {
            streamFinalize(data);
          }
          // 否則 streamTickDrain 跑完後自動呼叫 streamFinalize
        },
        onError: (error) => {
          clearTimeout(streamTypewriterTimer);
          streamTypewriterTimer = null;
          streamPendingText = "";
          streamDone = false;
          mpuChatRequesting = false;
          $input.prop("disabled", false);
          if (window.mpuChatModeActive) $input.focus();
          // [Fix 漏洞 4] 錯誤時撤回已 push 的 user 訊息，防止下一輪 checksum mismatch
          if (
            window.mpuChatHistory.length > 0 &&
            window.mpuChatHistory[window.mpuChatHistory.length - 1].role === "user"
          ) {
            window.mpuChatHistory.pop();
            mpu_saveChatHistory();
          }
          // 優先顯示後端回傳的錯誤訊息（如權限不足），否則才用通用字串
          const errorMsg = (error && error.message) ? error.message : "（…連線好像有點問題…）";
          mpu_typewriter(errorMsg, "#ukagaka_msg");
          mpuLogger.error("SSE Error:", error);
        },
        onAbort: () => {
          clearTimeout(streamTypewriterTimer);
          streamTypewriterTimer = null;
          streamPendingText = "";
          streamDone = false;
          // [Fix 漏洞 8] abort 時撤回已 push 的 user 訊息，防止後端已寫 checksum 但前端缺少 assistant
          if (
            window.mpuChatHistory.length > 0 &&
            window.mpuChatHistory[window.mpuChatHistory.length - 1].role === "user"
          ) {
            window.mpuChatHistory.pop();
            mpu_saveChatHistory();
          }
          mpuChatRequesting = false;
          $input.prop("disabled", false);
        },
      },
    );
  } else {
    // 傳統同步模式
    $input.val("").prop("disabled", true);
    jQuery("#ukagaka_msg").html(
      '（…えっと<span class="mpu-thinking"></span>）',
    );

    mpuFetch(mpuRestUrl + "chat/user", {
      method: "POST",
      body: formData,
      timeout: 60000,
      retries: 1,
      requestId: "mpu_user_chat",
      cancelPrevious: true,
    })
      .then((res) => {
        if (!window.mpuChatModeActive) {
          mpuLogger.log("對話模式已關閉，捨棄本次 AI 回應");
          return;
        }

        if (res && res.msg && !res.error) {
          const aiResponse = res.msg;
          window.mpuChatHistory.push({
            role: "assistant",
            content: aiResponse,
            timestamp: Date.now(),
          });
          mpu_saveChatHistory();
          mpu_typewriter(mpu_parseMarkdown(aiResponse), "#ukagaka_msg", null, true);

          if (
            typeof window.mpuCanvasManager !== "undefined" &&
            window.mpuCanvasManager.isCharacterMode
          ) {
            window.mpuCanvasManager.triggerCharacterAnimation(true);
          }

          if (res.emoji && typeof window.mpuEmojiManager !== "undefined") {
            window.mpuEmojiManager.showEmoji(res.emoji);
          }
        } else {
          const errorMsg = res && res.error ? res.error : "抱歉，無法取得回應";
          mpu_typewriter(errorMsg, "#ukagaka_msg");
        }
      })
      .catch((error) => {
        // [Fix 漏洞 4] 錯誤時撤回已 push 的 user 訊息，防止下一輪 checksum mismatch
        if (
          window.mpuChatHistory.length > 0 &&
          window.mpuChatHistory[window.mpuChatHistory.length - 1].role === "user"
        ) {
          window.mpuChatHistory.pop();
          mpu_saveChatHistory();
        }

        if (!window.mpuChatModeActive) {
          mpuLogger.log("對話模式已關閉，捨棄錯誤訊息");
          return;
        }

        mpu_handle_error(error, "mpu_sendUserMessage", { showToUser: false });
        // 優先顯示後端回傳的錯誤訊息（如權限不足），否則才用通用字串
        const syncErrorMsg = (error && error.message) ? error.message : "（…連線好像有點問題…）";
        mpu_typewriter(syncErrorMsg, "#ukagaka_msg");
      })
      .finally(() => {
        mpuChatRequesting = false;
        $input.prop("disabled", false);
        if (window.mpuChatModeActive) {
          $input.focus();
        }
      });
  }
}

// 綁定對話模式事件
jQuery(document).ready(function () {
  // 初始化：載入對話歷史
  if (typeof mpu_loadChatHistory === "function") {
    mpu_loadChatHistory();
    mpu_getOrCreateChatSessionId();
    mpuLogger.log(
      "頁面載入：對話歷史已載入，當前記錄數:",
      window.mpuChatHistory.length,
    );
  }

  // 第三個按鈕點擊事件：根據設定決定是對話還是切換春菜
  jQuery("#mpu_chat_toggle").on("click", function (e) {
    e.preventDefault();
    if (mpuEnableChatMode) {
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
      mpuLogger.log("裝飾物對話進行中，忽略按鈕點擊");
      return;
    }

    // 檢查訊息是否被阻擋
    if (mpuMessageBlocking) {
      mpuLogger.log("訊息被阻擋，忽略按鈕點擊");
      return;
    }

    // 賴床功能：如果是睡眠模式被喚醒，記錄 IP
    if (
      typeof window.mpuInfo !== "undefined" &&
      window.mpuInfo.isDeepSleepTime
    ) {
      if (typeof mpu_send_wake_up_request === "function") {
        mpu_send_wake_up_request();
      }
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

    if (needsWakeUp && hasWakeUpAnimation) {
      const $msgbox = jQuery("#ukagaka_msgbox");
      // 有喚醒動畫：先淡出對話框（隱藏 ZZZ），等待喚醒動畫完成後再顯示
      $msgbox.fadeOut(1000, function () {
        // 在對話框隱藏後，開始喚醒動畫（skipBookFlip = true：不翻書）
        window.mpuCanvasManager.triggerCharacterAnimation(true, null, true);

        // 同時呼叫後續動作（如生成 LLM 對話）
        handleOkAction();
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
      mpuLogger.log("退出對話模式");
      return;
    }

    // 非對話模式：檢查是否正在處理裝飾物對話
    if (
      typeof window.mpuCanvasManager !== "undefined" &&
      window.mpuCanvasManager.decorationChatInProgress
    ) {
      mpuLogger.log("裝飾物對話進行中，忽略按鈕點擊");
      return;
    }

    // 非對話模式：檢查訊息是否被阻擋
    if (mpuMessageBlocking) {
      mpuLogger.log("訊息被阻擋，忽略按鈕點擊");
      return;
    }

    mpu_hidemsg("");
  });

  mpuLogger.log("互動對話模式已初始化");
});

/**
 * 發送喚醒角色請求給後端
 */
function mpu_send_wake_up_request() {
  if (typeof mpuLogger !== "undefined") {
    mpuLogger.log("🌅 喚醒角色！正在準備發送請求...");
  }

  // 取得當前角色 ID（後端 mpu_wake_ghost 必填 personality_id）
  // 注意：這裡必須嚴格綁定目前人格，不做 default_1 fallback，避免跨人格寫錯喚醒狀態
  var personalityId =
    typeof window.mpuPersonalityId !== "undefined" && window.mpuPersonalityId
      ? window.mpuPersonalityId
      : typeof window.mpuInitData !== "undefined" &&
          window.mpuInitData &&
          window.mpuInitData.personality_id
        ? window.mpuInitData.personality_id
        : "";
  var ukagakaNum =
    typeof window.mpuInitData !== "undefined" &&
    window.mpuInitData &&
    window.mpuInitData.ukagaka_num
      ? window.mpuInitData.ukagaka_num
      : typeof window.mpuInitParams !== "undefined" &&
          window.mpuInitParams &&
          window.mpuInitParams.ukagaka_num
        ? window.mpuInitParams.ukagaka_num
        : "";

  if (!personalityId && !ukagakaNum) {
    if (typeof mpuLogger !== "undefined") {
      mpuLogger.warn(
        "喚醒請求已取消：缺少 personality_id/ukagaka_num，避免人格狀態錯亂",
      );
    }
    return;
  }

  // 發送喚醒請求
  var wakeFormData = new FormData();
  if (personalityId) {
    wakeFormData.append("personality_id", personalityId);
  }
  if (ukagakaNum) {
    wakeFormData.append("ukagaka_num", ukagakaNum);
  }

  mpuFetch(mpuRestUrl + "wake-ghost", {
    method: "POST",
    body: wakeFormData,
    timeout: 5000,
  })
    .then(function (res) {
      if (res && res.success) {
        if (typeof mpuLogger !== "undefined") {
          mpuLogger.log("喚醒成功:", res);
        }
        // 更新本地狀態，避免重複喚醒請求
        if (typeof window.mpuInfo !== "undefined") {
          window.mpuInfo.isDeepSleepTime = false;

          // 如果是深度睡眠期間的暫時喚醒，額外記錄狀態供後續參考
          if (res.is_temporary) {
            if (typeof mpuLogger !== "undefined") {
              mpuLogger.log("這是一次深度睡眠期間的暫時喚醒");
            }
            window.mpuInfo.isTemporaryWakeUp = true;
          }
        }
      } else {
        if (typeof mpuLogger !== "undefined") {
          mpuLogger.warn("喚醒請求回應失敗:", res);
        }
      }
    })
    .catch(function (err) {
      if (typeof mpuLogger !== "undefined") {
        mpuLogger.warn("喚醒請求失敗，但不影響正常操作:", err);
      }
    });
}
