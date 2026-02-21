
// ====== 互動對話模式 ======
// 對話模式狀態
let mpuChatModeActive = false;
let mpuChatHistory = [];
let mpuChatRequesting = false;
let mpuEnableChatMode = false; // 後台設定：是否啟用互動對話功能
const MPU_CHAT_HISTORY_KEY = "mpu_chat_history";
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
  const $msgbox = jQuery("#ukagaka_msgbox");
  const $chatInput = jQuery("#ukagaka_chat_input");
  const $input = jQuery("#mpu_user_input");

  if (typeof enable === "undefined") {
    enable = !mpuChatModeActive;
  }

  mpuChatModeActive = enable;

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
          mpu_typewriter(
            mpu_unescapeHTML(msgArr[randomIdx] + auto),
            "#ukagaka_msg",
          );
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
 * 發送用戶訊息（簡化版：只顯示春菜回覆）
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

  // 指令攔截：/reset 或 /clear 清除對話歷史
  if (message === "/reset" || message === "/clear") {
    mpu_clearChatHistory();
    $input.val("");
    mpu_typewriter("（記憶を消去しました...）對話歷史已清除。", "#ukagaka_msg");
    mpuLogger.log("對話歷史已清除");
    return;
  }

  // 指令攔截：/help 顯示可用指令
  if (message === "/help") {
    $input.val("");
    const helpText =
      "【可用指令】\n/reset - 清除對話歷史\n/clear - 同上\n/help - 顯示此說明";
    mpu_typewriter(helpText, "#ukagaka_msg");
    return;
  }

  mpuLogger.log("發送用戶訊息:", message);

  // 1. UI 防呆：清空並鎖定輸入框
  $input.val("").prop("disabled", true);
  mpuChatRequesting = true;

  // 添加用戶訊息到歷史（用於上下文，但不顯示）
  mpuChatHistory.push({
    role: "user",
    content: message,
    timestamp: Date.now(),
  });

  // 顯示思考中
  jQuery("#ukagaka_msg").html('（…えっと<span class="mpu-thinking"></span>）');

  // 獲取頁面上下文（複用現有函數）
  const pageContext = mpu_get_page_context();

  // 發送 AJAX 請求
  const formData = new FormData();
  formData.append("action", "mpu_user_chat");
  if (typeof mpuNonce !== "undefined" && mpuNonce) {
    formData.append("mpu_nonce", mpuNonce);
  }
  formData.append("message", message);
  formData.append("history", JSON.stringify(mpuChatHistory.slice(-10)));
  // 新增：傳送頁面資訊
  formData.append("page_title", pageContext.title || "");
  formData.append(
    "page_content",
    (pageContext.content || "").substring(0, 2000),
  ); // 裁切節省 Token

  mpuFetch(mpuurl, {
    method: "POST",
    body: formData,
    timeout: 60000,
    retries: 1,
    requestId: "mpu_user_chat",
    cancelPrevious: true,
  })
    .then((res) => {
      // 2. 幽靈說話檢查：如果對話模式已關閉，捨棄回應
      if (!mpuChatModeActive) {
        mpuLogger.log("對話模式已關閉，捨棄本次 AI 回應");
        return;
      }

      if (res && res.msg && !res.error) {
        const aiResponse = res.msg;

        // 添加 AI 回應到歷史
        mpuChatHistory.push({
          role: "assistant",
          content: aiResponse,
          timestamp: Date.now(),
        });

        // 3. 記憶功能：儲存對話歷史到 localStorage
        mpu_saveChatHistory();

        // 使用打字效果顯示春菜回覆（先解析 Markdown）
        mpu_typewriter(mpu_parseMarkdown(aiResponse), "#ukagaka_msg");

        // 觸發角色動畫（使用者發送訊息，強制播放）
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
      } else {
        const errorMsg = res && res.error ? res.error : "抱歉，無法取得回應";
        mpu_typewriter(errorMsg, "#ukagaka_msg");
      }
    })
    .catch((error) => {
      // 幽靈說話檢查
      if (!mpuChatModeActive) {
        mpuLogger.log("對話模式已關閉，捨棄錯誤訊息");
        return;
      }

      mpu_handle_error(error, "mpu_sendUserMessage", {
        showToUser: false,
      });
      mpu_typewriter("（…連線好像有點問題…）", "#ukagaka_msg");
    })
    .finally(() => {
      mpuChatRequesting = false;
      // 1. UI 防呆：解鎖輸入框
      $input.prop("disabled", false);
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
  if (!str) return "";
  return str
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#039;");
}

// 綁定對話模式事件
jQuery(document).ready(function () {
  // 初始化：載入對話歷史
  if (typeof mpu_loadChatHistory === "function") {
    mpu_loadChatHistory();
    mpuLogger.log(
      "頁面載入：對話歷史已載入，當前記錄數:",
      mpuChatHistory.length,
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

    if (mpuChatModeActive) {
      // 退出對話模式，回到自言自語模式
      mpu_toggleChatMode(false);
      mpuLogger.log("退出對話模式");
    } else {
      mpu_hidemsg("");
    }
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
    typeof window.mpuPersonalityId !== "undefined" &&
    window.mpuPersonalityId
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
  var wakeParams = new URLSearchParams({ action: "mpu_wake_ghost" });
  if (personalityId) {
    wakeParams.append("personality_id", personalityId);
  }
  if (ukagakaNum) {
    wakeParams.append("ukagaka_num", ukagakaNum);
  }
  if (typeof mpuNonce !== "undefined") {
    wakeParams.append("mpu_nonce", mpuNonce);
  }
  var wakeUrl = mpuurl + "?" + wakeParams.toString();

  mpuFetch(wakeUrl, { timeout: 5000 })
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
