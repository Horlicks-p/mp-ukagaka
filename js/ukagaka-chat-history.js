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
 * Return the raw history window shared by request transport and persistence.
 * The backend applies the same 40-entry boundary before checksum filtering.
 *
 * @returns {Array}
 */
function mpu_getChatHistoryForRequest() {
  return (window.mpuChatHistory || []).slice(-MPU_MAX_CHAT_HISTORY);
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
