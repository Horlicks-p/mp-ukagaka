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
    mpuShowSystemPlaceholder({ context: "chat" });
    mpuMarkSystemPlaceholder($msg); // §16.3-A：直接 .html() 的 placeholder 需手動標記

    // Streaming typewriter queue — 區域 timer，絕不碰全域 mpuTypewriterTimer
    let streamTypewriterTimer = null;
    let streamDisplayedText = "";
    let streamPendingText = "";
    let streamDone = false;
    let streamDoneData = null;
    let streamFinalized = false;
    let streamEmotionApplied = false;
    let streamThinkText = "";
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
      // §16.3-A：失敗 / 中斷 / 逾時 / abort 一律清除 placeholder 標記，避免殘影
      mpuClearSystemPlaceholder($msg);

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
      // §16.3-A：正式回應落定，清除 placeholder 標記（涵蓋無 delta 的 JSON fallback）
      mpuClearSystemPlaceholder($msg);
      mpuChatAbortController = null;
      const finalMsg = data.msg || fullResponse;
      if (data.think) {
        mpuShowThinkBubble(data.think, { source: "llm", context: "chat" });
      }
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
      if (data.emoji && !streamEmotionApplied && typeof window.mpuEmojiManager !== "undefined") {
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
            if (streamDisplayedText === "" && streamPendingText === "") {
              // §16.3-A：首個正式 delta 抵達，placeholder 退場，清除標記
              mpuClearSystemPlaceholder($msg);
              $msg.empty();
            }
            streamPendingText += data.text;
            streamStartDrain();
          }
        },
        onStatus: (data) => {
          let statusMsg = "";

          if (data.type === "thinking_start") {
            setStreamState("thinking");
            return;
          }
          if (data.type === "thinking_end") {
            setStreamState(streamPendingText || streamDisplayedText ? "streaming" : "thinking");
            return;
          }

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
            mpuShowSystemPlaceholder({ context: "chat", text: statusMsg });
            mpuMarkSystemPlaceholder($msg); // §16.3-A：status placeholder
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
            mpuShowSystemPlaceholder({ context: "chat", text: statusMsg });
            mpuMarkSystemPlaceholder($msg); // §16.3-A：tool placeholder
          }
        },
        onEmotion: (data) => {
          if (!data || streamEmotionApplied) {
            if (window.console && console.debug) {
              console.debug("MPU stream ignored extra emotion event", data);
            }
            return;
          }
          const emoji = data.file || (data.tag ? `${data.tag}.png` : "");
          if (emoji && typeof window.mpuEmojiManager !== "undefined") {
            window.mpuEmojiManager.showEmoji(emoji);
            streamEmotionApplied = true;
          }
        },
        onThink: (data) => {
          if (data && data.text) {
            streamThinkText = data.text;
            mpuClearSystemPlaceholder($msg);
            mpuShowThinkBubble(streamThinkText, { source: "llm", context: "chat" });
            mpuLogger.log("SSE think:", streamThinkText);
          }
        },
        onThinkDelta: (data) => {
          if (data && data.text) {
            streamThinkText += data.text;
            mpuClearSystemPlaceholder($msg);
            mpuShowThinkBubble(streamThinkText, { source: "llm", context: "chat" });
            mpuLogger.log("SSE think delta:", data.text);
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
    mpuShowSystemPlaceholder({ context: "chat" });
    mpuMarkSystemPlaceholder("#ukagaka_msg"); // §16.3-A：同步 chat placeholder（真實回應經 typewriter 自動清除）

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
          if (res.think) {
            mpuShowThinkBubble(res.think, { source: "llm", context: "chat" });
          }
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
