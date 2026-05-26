// ====== 互動對話模式 ======
// 對話模式狀態
window.mpuChatModeActive = false;
window.mpuChatRequesting = false;
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
    mpuLogger.logL("chatModeEntered", "インタラクティブ会話モードに入りました");

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
        // 檢查是否有喚醒動畫文件（通用方法，支援各種角色管理器）
        const hasWakeUpAnimation =
          typeof window.mpuCanvasManager !== "undefined" &&
          typeof window.mpuCanvasManager.hasWakeUpAnimation === "function" &&
          window.mpuCanvasManager.hasWakeUpAnimation();
        const showChatAfterWake = function (reactionDisplayed) {
          $msgbox.addClass("chat-mode");
          $chatInput.slideDown(400, function () {
            if (!reactionDisplayed) {
              showWelcome();
            } else if ($msgbox.is(":hidden")) {
              mpu_showmsg(400);
            }
            setTimeout(() => $input.focus(), 250);
          });
        };
        const requestWakeThenShowChat = function () {
          if (typeof mpu_send_wake_up_request !== "function") {
            showChatAfterWake(false);
            return;
          }
          mpu_send_wake_up_request()
            .then(showChatAfterWake)
            .catch(function () {
              showChatAfterWake(false);
            });
        };

        if (hasWakeUpAnimation) {
          window.mpuForceWakeUpNextTime = true;
          // 有喚醒動畫：先淡出對話框（隱藏 ZZZ），等待喚醒動畫完成後再顯示
          $msgbox.fadeOut(1000, function () {
            // 在對話框隱藏後，開始喚醒動畫（skipBookFlip = true：不翻書）
            window.mpuCanvasManager.triggerCharacterAnimation(
              true,
              function () {
                // 喚醒動畫完成後，再取得被喚醒的第一句反應
                requestWakeThenShowChat();
              },
              true,
            ); // skipBookFlip = true：開啟對話時不翻書
          });
        } else {
          // 沒有喚醒動畫：直接取得被喚醒的第一句反應
          requestWakeThenShowChat();
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
    mpuLogger.logL("chatModeExited", "インタラクティブ会話モードを終了しました");

    // 隱藏輸入框
    $chatInput.slideUp(400);
    $msgbox.removeClass("chat-mode");

    // 設置訊息阻擋，防止退出後立即說話
    mpuSetMessageBlocking(true);

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
        mpuSetMessageBlocking(false);

        // 顯示一條隨機對話
        const store = mpuGetDialogStore();
        if (
          store &&
          Array.isArray(store.msg) &&
          store.msg.length > 0
        ) {
          const msgArr = store.msg;
          const auto = store.auto_msg || "";
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
 * @param {object} handlers - 事件處理器 { onEvent, onStart, onDelta, onStatus, onNonce, onDone, onError, onToolRequest, onToolResult }
 */
window.MPU_EVENTS = window.MPU_EVENTS || {
  STREAM_DELTA: "stream.delta",
  STREAM_STATUS: "stream.status",
  STREAM_DONE: "stream.done",
  STREAM_ERROR: "stream.error",
  TOOL_REQUEST: "tool.request",
  TOOL_RESULT: "tool.result",
  NONCE_REFRESH: "nonce.refresh",
};

function mpuNormalizeSseEvent(eventName, data) {
  const legacyMap = {
    start: window.MPU_EVENTS.STREAM_STATUS,
    delta: window.MPU_EVENTS.STREAM_DELTA,
    status: window.MPU_EVENTS.STREAM_STATUS,
    done: window.MPU_EVENTS.STREAM_DONE,
    error: window.MPU_EVENTS.STREAM_ERROR,
    tool_result: window.MPU_EVENTS.TOOL_RESULT,
    nonce: window.MPU_EVENTS.NONCE_REFRESH,
  };

  if (data && typeof data === "object" && data.kind && data.payload !== undefined) {
    return { name: data.kind, data: data.payload, envelope: data };
  }

  return { name: legacyMap[eventName] || eventName, data, envelope: null };
}

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

        const normalized = mpuNormalizeSseEvent(eventName, data);
        eventName = normalized.name;
        data = normalized.data;
        if (handlers.onEvent) handlers.onEvent(eventName, data, normalized.envelope);

        switch (eventName) {
          case window.MPU_EVENTS.STREAM_STATUS:
            if (data && (data.type === "executing_tool" || data.message)) {
              if (handlers.onStatus) handlers.onStatus(data);
            } else if (handlers.onStart) {
              handlers.onStart(data);
            }
            break;
          case "start":
            if (handlers.onStart) handlers.onStart(data);
            break;
          case window.MPU_EVENTS.STREAM_DELTA:
          case "delta":
            if (handlers.onDelta) handlers.onDelta(data);
            break;
          case "status":
            if (handlers.onStatus) handlers.onStatus(data);
            break;
          case window.MPU_EVENTS.NONCE_REFRESH:
          case "nonce":
            if (data.new_token && typeof mpuRestNonce !== "undefined") {
              window.mpuRestNonce = data.new_token;
              mpuLogger.log("REST Nonce refreshed via SSE");
            }
            if (handlers.onNonce) handlers.onNonce(data);
            break;
          case window.MPU_EVENTS.STREAM_DONE:
          case "done":
            if (handlers.onDone) handlers.onDone(data);
            return;
          case window.MPU_EVENTS.STREAM_ERROR:
          case "error":
            if (handlers.onError) handlers.onError(data);
            return; // [Fix] 直接結束，避免 throw 導致 catch 再次觸發 onError
          case window.MPU_EVENTS.TOOL_REQUEST:
            if (handlers.onToolRequest) handlers.onToolRequest(data);
            break;
          case window.MPU_EVENTS.TOOL_RESULT:
            if (handlers.onToolResult) handlers.onToolResult(data);
            break;
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
      mpuLogger.logL("chatWaitingForResponse", "応答待ちです。しばらくお待ちください");
    }
    return;
  }

  // 指令攔截：/reset 或 /clear 清除對話歷史（僅管理員）
  if (message === "/reset" || message === "/clear") {
    if (mpuPreSettings && mpuPreSettings.is_admin) {
      mpu_clearChatHistory();
      $input.val("");
      mpu_typewriter("（記憶を消去しました...）", "#ukagaka_msg");
      mpuLogger.logL("chatHistoryCleared", "会話履歴をクリアしました");
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

  mpuLogger.logF("chatSendingUserMessage", "ユーザーメッセージを送信します：%s", message);
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
    const streamWatchdogMs = 45000;
    let streamWatchdogTimer = null;
    let streamTimedOut = false;
    const $msgbox = jQuery("#ukagaka_msgbox");

    function streamTimeoutMessage() {
      if (typeof mpuL10n !== "undefined" && mpuL10n.streamTimeout) {
        return mpuL10n.streamTimeout;
      }
      if (typeof mpuL10n !== "undefined" && mpuL10n.connectionError) {
        return mpuL10n.connectionError;
      }
      return "Connection timed out. Please try again.";
    }

    function streamErrorMessage(error) {
      if (error && error.message) return error.message;
      if (error && error.error) return error.error;
      if (typeof mpuL10n !== "undefined" && mpuL10n.connectionError) {
        return mpuL10n.connectionError;
      }
      return "Connection error. Please try again.";
    }

    function setStreamState(state) {
      $msgbox.attr("data-mpu-stream-state", state);
      const labels =
        (typeof mpuL10n !== "undefined" && mpuL10n.streamStates) || {};
      const label = labels[state] || "";
      let badge = $msgbox.children(".mpu-state-badge");
      if (badge.length === 0) {
        badge = jQuery('<span class="mpu-state-badge" aria-live="polite"></span>');
        $msgbox.append(badge);
      }
      badge.text(label);
    }

    function clearStreamState() {
      $msgbox.removeAttr("data-mpu-stream-state");
      $msgbox.children(".mpu-state-badge").remove();
    }

    function clearStreamWatchdog() {
      if (streamWatchdogTimer !== null) {
        clearTimeout(streamWatchdogTimer);
        streamWatchdogTimer = null;
      }
    }

    function armStreamWatchdog() {
      clearStreamWatchdog();
      if (streamFinalized) return;
      streamWatchdogTimer = setTimeout(() => {
        if (streamFinalized || !mpuChatRequesting) return;
        streamTimedOut = true;
        setStreamState("timeout");
        if (mpuChatAbortController) {
          mpuChatAbortController.abort();
        }
      }, streamWatchdogMs);
    }

    function stopStreamTypewriter() {
      clearTimeout(streamTypewriterTimer);
      streamTypewriterTimer = null;
      streamPendingText = "";
      streamDone = false;
    }

    function rollbackLastUserMessage() {
      if (
        window.mpuChatHistory.length > 0 &&
        window.mpuChatHistory[window.mpuChatHistory.length - 1].role === "user"
      ) {
        window.mpuChatHistory.pop();
        mpu_saveChatHistory();
      }
    }

    function releaseStreamInput() {
      mpuChatRequesting = false;
      mpuChatAbortController = null;
      $input.prop("disabled", false);
      if (window.mpuChatModeActive) $input.focus();
    }

    function handleStreamFailure(error, timedOut) {
      if (streamFinalized) return;
      streamFinalized = true;
      clearStreamWatchdog();

      const isBusy =
        !timedOut &&
        error &&
        (error.code === "mpu_chat_lock_busy" ||
          (error.data && error.data.status === 429));

      if (isBusy) {
        setStreamState("busy");
      } else if (timedOut) {
        setStreamState("timeout");
      } else if (error) {
        setStreamState("error");
      } else {
        clearStreamState();
      }
      stopStreamTypewriter();
      rollbackLastUserMessage();
      releaseStreamInput();
      if (isBusy) {
        const busyMsg =
          (error && error.message) ||
          (typeof mpuL10n !== "undefined" &&
            mpuL10n.streamStates &&
            mpuL10n.streamStates.busy) ||
          "混雑中…";
        mpu_typewriter(busyMsg, "#ukagaka_msg");
        mpuLogger.warn("Chat lock busy:", error);
      } else if (timedOut) {
        mpu_typewriter(streamTimeoutMessage(), "#ukagaka_msg");
        mpuLogger.warn("SSE watchdog timeout");
      } else if (error) {
        const errorMsg = streamErrorMessage(error);
        mpu_typewriter(errorMsg, "#ukagaka_msg");
        mpuLogger.error("SSE Error:", error);
      }
    }

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
      clearStreamWatchdog();
      clearStreamState();
      mpuChatAbortController = null;
      const finalMsg = data.msg || fullResponse;
      // [Fix] SSE 端點偶爾會回 JSON（例如 /debug_mcp redirect、非 streaming
      // provider 的同步 fallback）。這條路徑沒有 delta，streamTickDrain
      // 從沒跑過，$msg 還停在「（…えっと…）」placeholder。在這裡補渲染。
      if (streamDisplayedText === "" && streamPendingText === "" && finalMsg) {
        $msg.html(mpu_parseMarkdown(finalMsg));
      }
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

    armStreamWatchdog();
    mpuFetchSSE(
      mpuRestUrl + "chat/user-stream",
      {
        method: "POST",
        body: formData,
        controller: mpuChatAbortController,
      },
      {
        onEvent: (eventName) => {
          if (
            eventName === window.MPU_EVENTS.STREAM_DONE ||
            eventName === window.MPU_EVENTS.STREAM_ERROR
          ) {
            clearStreamWatchdog();
            return;
          }
          armStreamWatchdog();
        },
        onStart: (data) => {
          setStreamState("thinking");
          mpuLogger.log("SSE Started:", data);
        },
        onDelta: (data) => {
          setStreamState("streaming");
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
            setStreamState(data.type === "executing_tool" ? "tool" : "status");
            $msg.html(`（…${statusMsg}<span class="mpu-thinking"></span>）`);
          }
        },
        onToolRequest: (data) => {
          const toolName = data.tool || data.name || "";
          let statusMsg = data.message || "";

          if (!statusMsg && toolName) {
            const template =
              typeof mpuL10n !== "undefined" && mpuL10n.executingTool
                ? mpuL10n.executingTool
                : "正在執行工具：%s...";
            statusMsg = template.replace("%s", toolName);
          }

          if (statusMsg) {
            setStreamState("tool");
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
          handleStreamFailure(error, false);
        },
        onAbort: () => {
          handleStreamFailure(streamTimedOut ? new Error(streamTimeoutMessage()) : null, streamTimedOut);
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
          mpuLogger.logL("chatModeClosedDiscardAiResponse", "会話モードが閉じているため、今回の AI 応答を破棄します");
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
          mpuLogger.logL("chatModeClosedDiscardError", "会話モードが閉じているため、エラーメッセージを破棄します");
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
    mpuLogger.logF("chatHistoryLoadedOnPageLoad", "ページ読み込み：会話履歴を読み込みました。現在の記録数：%s", window.mpuChatHistory.length);
  }

  // 第三個按鈕點擊事件：根據設定決定是對話還是切換春菜
  jQuery("#mpu_chat_toggle").on("click", function (e) {
    e.preventDefault();
    if (mpuIsChatModeEnabled()) {
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
      mpuLogger.logL("chatButtonIgnoredDecorationDialogActive", "装飾品会話中のため、ボタンクリックを無視します");
      return;
    }

    // 檢查訊息是否被阻擋
    if (mpuMessageBlocking) {
      mpuLogger.logL("chatButtonIgnoredMessageBlocking", "メッセージがブロックされているため、ボタンクリックを無視します");
      return;
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
    var handleWakeThenOkAction = function () {
      if (typeof mpu_send_wake_up_request !== "function") {
        handleOkAction();
        return;
      }
      mpu_send_wake_up_request()
        .then(function (reactionDisplayed) {
          if (!reactionDisplayed) {
            handleOkAction();
          }
        })
        .catch(function () {
          handleOkAction();
        });
    };

    if (needsWakeUp) {
      if (!hasWakeUpAnimation) {
        handleWakeThenOkAction();
        return;
      }

      const $msgbox = jQuery("#ukagaka_msgbox");
      window.mpuForceWakeUpNextTime = true;
      // 有喚醒動畫：先淡出對話框（隱藏 ZZZ），等待喚醒動畫完成後再顯示
      $msgbox.fadeOut(1000, function () {
        // 在對話框隱藏後，開始喚醒動畫（skipBookFlip = true：不翻書）
        window.mpuCanvasManager.triggerCharacterAnimation(
          true,
          function () {
            window.mpuSkipNextManualBookFlip = true;
            // chat 模式下 handleOkAction 會走 mpu_sendUserMessage，不會消費此旗標；
            // 設一個 8 秒上限的後備清理，避免旗標殘留影響後續手動互動。
            const expireToken = Date.now();
            window.mpuSkipBookFlipExpireToken = expireToken;
            setTimeout(function () {
              if (window.mpuSkipBookFlipExpireToken === expireToken) {
                window.mpuSkipNextManualBookFlip = false;
                window.mpuSkipBookFlipExpireToken = null;
              }
            }, 8000);
            handleWakeThenOkAction();
          },
          true
        );
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
      mpuLogger.logL("chatModeExitRequested", "会話モードを終了します");
      return;
    }

    // 非對話模式：檢查是否正在處理裝飾物對話
    if (
      typeof window.mpuCanvasManager !== "undefined" &&
      window.mpuCanvasManager.decorationChatInProgress
    ) {
      mpuLogger.logL("chatButtonIgnoredDecorationDialogActive", "装飾品会話中のため、ボタンクリックを無視します");
      return;
    }

    // 非對話模式：檢查訊息是否被阻擋
    if (mpuMessageBlocking) {
      mpuLogger.logL("chatButtonIgnoredMessageBlocking", "メッセージがブロックされているため、ボタンクリックを無視します");
      return;
    }

    mpu_hidemsg("");
  });

  mpuLogger.logL("chatModeInitialized", "インタラクティブ会話モードを初期化しました");
});

/**
 * 發送喚醒角色請求給後端
 */
function mpu_display_wake_reaction(res) {
  const fallbackPool =
    res && res.sleep_phase === "oversleep"
      ? [
          "……もう少し寝ていたかったんだけど。",
          "……起こすの、少し早くない？",
          "……あと少しだけ寝るつもりだったのに。",
          "……二度寝の途中だったんだけど。",
          "……もう朝？ まだ眠い。",
        ]
      : res && res.sleep_phase === "deep_sleep"
        ? [
            "……今、起こす必要あった？",
            "……うるさい。まだ寝てたんだけど。",
            "……なに。急ぎの用？",
            "……眠い。あとでいい？",
            "……せっかく寝てたのに。",
          ]
        : [];
  const fallbackReaction =
    fallbackPool.length > 0
      ? fallbackPool[Math.floor(Math.random() * fallbackPool.length)]
      : "";
  const reaction = String((res && res.wake_reaction) || fallbackReaction).trim();
  if (!reaction) {
    return false;
  }

  const output =
    typeof mpu_parseMarkdown === "function" ? mpu_parseMarkdown(reaction) : reaction;
  mpu_typewriter(output, "#ukagaka_msg", null, true);
  if (jQuery("#ukagaka_msgbox").is(":hidden")) {
    mpu_showmsg(400);
  }

  if (Array.isArray(window.mpuChatHistory)) {
    window.mpuChatHistory.push({
      role: "assistant",
      content: reaction,
      type: "wake_reaction",
      timestamp: Date.now(),
    });
    if (typeof mpu_saveChatHistory === "function") {
      mpu_saveChatHistory();
    }
  }

  return true;
}

function mpu_send_wake_up_request() {
  if (window.mpuWakeRequestPromise) {
    return window.mpuWakeRequestPromise;
  }

  if (typeof mpuLogger !== "undefined") {
    mpuLogger.logL("wakeRequestPreparing", "🌅 キャラクターを起こします。リクエスト送信を準備しています...");
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
      mpuLogger.warnL("wakeRequestCancelledMissingIdentity", "目覚めリクエストをキャンセルしました：personality_id/ukagaka_num が不足しているため、人格状態の混乱を避けます");
    }
    return Promise.resolve(false);
  }

  // 發送喚醒請求
  var wakeFormData = new FormData();
  if (personalityId) {
    wakeFormData.append("personality_id", personalityId);
  }
  if (ukagakaNum) {
    wakeFormData.append("ukagaka_num", ukagakaNum);
  }

  window.mpuWakeRequestPromise = mpuFetch(mpuRestUrl + "wake-ghost", {
    method: "POST",
    body: wakeFormData,
    timeout: 60000,
  })
    .then(function (res) {
      if (res && res.success) {
        if (typeof mpuLogger !== "undefined") {
          mpuLogger.logF("wakeRequestSucceeded", "目覚めに成功しました：%s", res);
        }
        // 更新本地狀態，避免重複喚醒請求
        if (typeof window.mpuInfo !== "undefined") {
          window.mpuInfo.isDeepSleepTime = false;

          // 如果是深度睡眠期間的暫時喚醒，額外記錄狀態供後續參考
          if (res.is_temporary) {
            if (typeof mpuLogger !== "undefined") {
              mpuLogger.logL("wakeRequestTemporaryDuringDeepSleep", "これは深い睡眠中の一時的な目覚めです");
            }
            window.mpuInfo.isTemporaryWakeUp = true;
          }
        }
        return mpu_display_wake_reaction(res);
      } else {
        if (typeof mpuLogger !== "undefined") {
          mpuLogger.warnF("wakeRequestResponseFailed", "目覚めリクエストの応答が失敗しました：%s", res);
        }
      }
      return false;
    })
    .catch(function (err) {
      if (typeof mpuLogger !== "undefined") {
        mpuLogger.warnF("wakeRequestFailedNonBlocking", "目覚めリクエストに失敗しましたが、通常動作には影響しません：%s", err);
      }
      return false;
    })
    .finally(function () {
      window.mpuWakeRequestPromise = null;
    });

  return window.mpuWakeRequestPromise;
}
