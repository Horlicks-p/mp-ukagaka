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
          case "emotion":
            if (handlers.onEmotion) handlers.onEmotion(data);
            break;
          case "think":
            if (handlers.onThink) handlers.onThink(data);
            break;
          case "think_delta":
            if (handlers.onThinkDelta) handlers.onThinkDelta(data);
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
