// ====== 互動對話模式 ======
// 對話模式狀態
window.mpuChatModeActive = false;
window.mpuChatRequesting = false;
// 每次開關聊天就遞增。請求記下送出時的世代，回應到達時世代不同就是過期回應：
// 照常記入歷史，但不寫進目前畫面。
window.mpuChatGeneration = 0;
// 離開聊天時設下的 5 秒訊息阻擋是否仍由離開流程持有
window.mpuChatExitBlocking = false;
const MPU_CHAT_HISTORY_KEY = "mpu_chat_history";
const MPU_CHAT_SESSION_KEY = "mpu_chat_tab_session_id";
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

  // Generate once per page instance. Browsers copy sessionStorage when a tab is
  // duplicated, so restoring an ID from it can make two live tabs share one
  // checksum session. Full navigation safely starts a new server checksum.
  window.mpuChatSessionId = mpu_generateChatSessionId();
  try {
    window.sessionStorage.setItem(MPU_CHAT_SESSION_KEY, window.mpuChatSessionId);
  } catch (e) {
    window.__mpuChatTabSessionId = window.mpuChatSessionId;
  }
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
 * Return the raw history window shared by request transport and persistence.
 * The backend applies the same 40-entry boundary before checksum filtering.
 *
 * @returns {Array}
 */
function mpu_getChatHistoryForRequest() {
  return (window.mpuChatHistory || []).slice(-MPU_MAX_CHAT_HISTORY);
}

/**
 * 找出某筆歷史在目前陣列中的位置。重新進入聊天時 mpu_loadChatHistory() 會換成
 * 從 storage 讀回的新陣列，原本的物件參考就找不到了，所以再以內容比對。
 *
 * @param {Object} entry
 * @returns {number} 找不到時為 -1
 */
function mpu_findChatHistoryEntry(entry) {
  const history = window.mpuChatHistory || [];
  const direct = history.indexOf(entry);
  if (direct !== -1) return direct;
  for (let i = history.length - 1; i >= 0; i--) {
    const m = history[i];
    if (m && m.role === entry.role && m.timestamp === entry.timestamp && m.content === entry.content) {
      return i;
    }
  }
  return -1;
}

/**
 * 把回應放在它所回答的那一輪之後。請求進行中，離開聊天的自語或送禮可能已先
 * 寫入歷史；直接 push 會讓回應和它的提問分開。
 *
 * @param {Object} userEntry - 送出時寫入的 user 訊息
 * @param {Object} assistantEntry
 */
function mpu_insertChatReply(userEntry, assistantEntry) {
  const index = mpu_findChatHistoryEntry(userEntry);
  if (index === -1) {
    window.mpuChatHistory.push(assistantEntry);
  } else {
    window.mpuChatHistory.splice(index + 1, 0, assistantEntry);
  }
  mpu_saveChatHistory();
}

/**
 * 撤回送出失敗的 user 訊息。最後一筆未必是它（離開聊天後會追加自語）。
 *
 * @param {Object} userEntry
 */
function mpu_removeChatHistoryEntry(userEntry) {
  const index = mpu_findChatHistoryEntry(userEntry);
  if (index !== -1) {
    window.mpuChatHistory.splice(index, 1);
    mpu_saveChatHistory();
  }
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
