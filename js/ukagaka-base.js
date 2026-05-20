// ====== 全域設定 ======
const mpuClick = "next";
const mpuNextModeInitial = "sequential"; // 暫存變數，實際模式由 mpuMsgList.next_msg 決定
const mpuDefaultMsgInitial = 0;         // 暫存變數，0: 隨機第一條, 1: 第一條

window.MPU_STATE = window.MPU_STATE || {
    dialog: {
        nextMode: mpuNextModeInitial,
        defaultMsg: mpuDefaultMsgInitial,
        msgList: null,
    },
    autoTalk: {
        enabled: false,
        interval: 12000,
        baseInterval: 0,
        timer: null,
    },
    typewriter: {
        timer: null,
        speed: 40,
    },
    llm: {
        aiTextColor: "#000000",
        aiDisplayDuration: 8,
        aiDisplayTimer: null,
        ollamaReplaceDialogue: false,
        aiContextInProgress: false,
        messageBlocking: false,
        lastResponse: "",
        responseHistory: [],
        lastUserActionTime: Date.now(),
        ollamaRequesting: false,
        ollamaRequestQueue: [],
    },
    request: {
        sessionToken: "",
        sessionTokenPromise: null,
    },
    flags: {
        debugMode: (typeof window !== "undefined" && window.mpuDebugMode === true) || false,
        greetInProgress: false,
        contextPending: false,
        settingsProcessed: false,
        settingsLoaded: false,
        enableChatMode: false,
    },
    retry: {
        nextMessage: 0,
        fallbackMessage: 0,
    },
    storage: {},
};

const mpuState = window.MPU_STATE;

function mpuGetState() {
    return window.MPU_STATE;
}

function mpuIsDebugMode() {
    const state = mpuGetState();
    return !!(
        state.flags.debugMode ||
        (typeof window !== "undefined" && window.mpuDebugMode === true)
    );
}

let mpuNextMode = mpuState.dialog.nextMode;
let mpuDefaultMsg = mpuState.dialog.defaultMsg;
let mpuAutoTalk = mpuState.autoTalk.enabled;                // 自動對話開關，預設關閉
let mpuAutoTalkInterval = mpuState.autoTalk.interval;        // 自動對話間隔時間（毫秒），預設 12 秒
let mpuAutoTalkTimer = mpuState.autoTalk.timer;            // 自動對話計時器

// F5/Reload 偵測：重整時清空對話記憶與 Session ID（SPA 路由切換不觸發，只有真正的 reload 才清空）
(function () {
  var isReload = false;
  // 優先使用 PerformanceNavigationTiming（現代瀏覽器）
  if (window.performance && typeof performance.getEntriesByType === "function") {
    var navEntries = performance.getEntriesByType("navigation");
    if (navEntries.length > 0 && navEntries[0].type === "reload") {
      isReload = true;
    }
  }
  // Fallback：performance.navigation（舊瀏覽器相容，已 deprecated 但廣泛支援）
  if (!isReload && window.performance && performance.navigation) {
    if (performance.navigation.type === 1) {
      isReload = true;
    }
  }
  if (isReload) {
    try {
      localStorage.removeItem("mpu_chat_history");
      localStorage.removeItem("mpu_chat_session_id");
    } catch (e) {
      // localStorage 不可用時靜默略過
    }
    // mpuLogger 尚未定義，使用 console
    if (window.console) {
      console.log("[MPU] \uD83D\uDD04 偵測到頁面重整，清空對話記憶與 Session ID");
    }
  }
})();

// 對話歷史與 Session ID 全域共享（確保各個 JS 模組同步，避免 Checksum Mismatch）
window.mpuChatHistory = window.mpuChatHistory || [];
window.mpuChatSessionId = window.mpuChatSessionId || "";

let mpuAiTextColor = mpuState.llm.aiTextColor;         // AI 對話文字顏色
let mpuAiDisplayDuration = mpuState.llm.aiDisplayDuration;           // AI 對話顯示時間（秒）
let mpuAiDisplayTimer = mpuState.llm.aiDisplayTimer;           // AI 對話顯示計時器
let mpuGreetInProgress = mpuState.flags.greetInProgress;         // 首次訪客打招呼是否正在進行中
let mpuTypewriterTimer = mpuState.typewriter.timer;          // 打字效果計時器
let mpuTypewriterSpeed = mpuState.typewriter.speed;            // 打字速度（毫秒/字元），將從後台設定讀取
let mpuOllamaReplaceDialogue = mpuState.llm.ollamaReplaceDialogue;   // 是否使用 LLM 取代內建對話

// Session token 懶取得 — 避免 server-render token 被 full-page cache 污染
window.mpuSessionToken = window.mpuSessionToken || mpuState.request.sessionToken;
window.__mpuStorage = window.__mpuStorage || mpuState.storage;
mpuState.storage = window.__mpuStorage;
let _mpuSessionTokenPromise = mpuState.request.sessionTokenPromise;
async function mpuEnsureSessionToken() {
    if (typeof mpuSessionToken === 'string' && mpuSessionToken) return mpuSessionToken;
    if (!_mpuSessionTokenPromise) {
        _mpuSessionTokenPromise = (async () => {
            if (typeof mpuRestUrl === 'undefined') return '';
            try {
                const resp = await fetch(mpuRestUrl + 'session-token', {
                    credentials: 'same-origin',
                    cache: 'no-store',
                });
                const data = await resp.json();
                window.mpuSessionToken = data.token || '';
                mpuState.request.sessionToken = window.mpuSessionToken;
                return window.mpuSessionToken;
            } catch (_) {
                return '';
            }
        })();
        mpuState.request.sessionTokenPromise = _mpuSessionTokenPromise;
    }
    return _mpuSessionTokenPromise;
}

// 從後台設定初始化變數
if (typeof mpuPreSettings !== 'undefined') {
    if (typeof mpuPreSettings.typewriter_speed !== 'undefined') {
        mpuTypewriterSpeed = parseInt(mpuPreSettings.typewriter_speed, 10) || 40;
        mpuState.typewriter.speed = mpuTypewriterSpeed;
    }
    if (typeof mpuPreSettings.ollama_replace !== 'undefined') {
        mpuOllamaReplaceDialogue = mpuPreSettings.ollama_replace === true;
        mpuState.llm.ollamaReplaceDialogue = mpuOllamaReplaceDialogue;
    }
}
let mpuAiContextInProgress = mpuState.llm.aiContextInProgress;     // 頁面感知 AI 是否正在進行中（防止自動對話打斷）
let mpuMessageBlocking = mpuState.llm.messageBlocking;         // 強制阻擋訊息切換（用於顯示錯誤或重要訊息時防止被打斷）
let mpuLastLLMResponse = mpuState.llm.lastResponse;            // 上一次 LLM 生成的回應（用於避免重複對話）
let mpuLLMResponseHistory = mpuState.llm.responseHistory;         // LLM 回應歷史（最近10次，用於更嚴格的重複檢測）
const mpuMaxResponseHistory = 10;       // 最大歷史記錄數量
let mpuLastUserActionTime = mpuState.llm.lastUserActionTime; // 記錄最後動作時間（用於閒置偵測）
const mpuIdleThreshold = 60000;         // 閒置閾值：60 秒（1 分鐘），超過此時間則暫停自動對話（可根據需求調整：30秒=30000, 90秒=90000, 180秒=180000）

let mpuOllamaRequesting = mpuState.llm.ollamaRequesting;
let mpuOllamaRequestQueue = mpuState.llm.ollamaRequestQueue;
const mpuOllamaQueueDelay = 1500;

// 以記憶體保存已解析的對話資料
window.mpuMsgList = mpuState.dialog.msgList;

function mpuSetAutoTalkTimer(timer) {
    mpuAutoTalkTimer = timer;
    mpuGetState().autoTalk.timer = timer;
}

function mpuSetAutoTalkEnabled(isEnabled) {
    mpuAutoTalk = isEnabled;
    mpuGetState().autoTalk.enabled = isEnabled;
}

function mpuSetAutoTalkInterval(interval) {
    mpuAutoTalkInterval = interval;
    mpuGetState().autoTalk.interval = interval;
}

function mpuSetBaseAutoTalkInterval(interval) {
    window.mpuBaseAutoTalkInterval = interval;
    mpuGetState().autoTalk.baseInterval = interval;
}

function mpuGetBaseAutoTalkInterval() {
    const interval = mpuGetState().autoTalk.baseInterval;
    return interval > 0 ? interval : mpuAutoTalkInterval;
}

function mpuSetTypewriterTimer(timer) {
    mpuTypewriterTimer = timer;
    mpuGetState().typewriter.timer = timer;
}

function mpuSetAiTextColor(color) {
    mpuAiTextColor = color;
    mpuGetState().llm.aiTextColor = color;
}

function mpuSetAiDisplayDuration(duration) {
    mpuAiDisplayDuration = duration;
    mpuGetState().llm.aiDisplayDuration = duration;
}

function mpuSetAiDisplayTimer(timer) {
    mpuAiDisplayTimer = timer;
    mpuGetState().llm.aiDisplayTimer = timer;
}

function mpuSetAiContextInProgress(isInProgress) {
    mpuAiContextInProgress = isInProgress;
    mpuGetState().llm.aiContextInProgress = isInProgress;
}

function mpuSetMessageBlocking(isBlocking) {
    mpuMessageBlocking = isBlocking;
    mpuGetState().llm.messageBlocking = isBlocking;
}

function mpuSetOllamaReplaceDialogue(isEnabled) {
    mpuOllamaReplaceDialogue = isEnabled;
    mpuGetState().llm.ollamaReplaceDialogue = isEnabled;
}

function mpuSetLastLLMResponse(response) {
    mpuLastLLMResponse = response;
    mpuGetState().llm.lastResponse = response;
}

function mpuResetLLMResponseHistory() {
    mpuLLMResponseHistory = [];
    mpuGetState().llm.responseHistory = mpuLLMResponseHistory;
}

function mpuSetOllamaRequesting(isRequesting) {
    mpuOllamaRequesting = isRequesting;
    mpuGetState().llm.ollamaRequesting = isRequesting;
}

function mpuSetLastUserActionTime(timestamp) {
    mpuLastUserActionTime = timestamp;
    mpuGetState().llm.lastUserActionTime = timestamp;
}

function mpuSetGreetInProgress(isInProgress) {
    mpuGreetInProgress = isInProgress;
    mpuGetState().flags.greetInProgress = isInProgress;
}

function mpuSetContextPending(isPending) {
    mpuGetState().flags.contextPending = isPending;
}

function mpuIsContextPending() {
    return !!mpuGetState().flags.contextPending;
}

function mpuSetSettingsProcessed(isProcessed) {
    mpuGetState().flags.settingsProcessed = isProcessed;
}

function mpuIsSettingsProcessed() {
    return !!mpuGetState().flags.settingsProcessed;
}

function mpuSetSettingsLoaded(isLoaded) {
    mpuGetState().flags.settingsLoaded = isLoaded;
}

function mpuIsSettingsLoaded() {
    return !!mpuGetState().flags.settingsLoaded;
}

function mpuSetEnableChatMode(isEnabled) {
    mpuGetState().flags.enableChatMode = isEnabled;
}

function mpuIsChatModeEnabled() {
    return !!mpuGetState().flags.enableChatMode;
}

function mpuSetDialogStore(store) {
    window.mpuMsgList = store;
    mpuGetState().dialog.msgList = store;
}

function mpuGetDialogStore() {
    // Defensive: base.js initializes window.mpuMsgList, but external scripts can unset it.
    if (typeof window.mpuMsgList !== "undefined") {
        return window.mpuMsgList;
    }
    return mpuGetState().dialog.msgList;
}

function mpuSetDialogNextMode(nextMode) {
    mpuNextMode = nextMode;
    mpuGetState().dialog.nextMode = nextMode;
}

function mpuSetDialogDefaultMsg(defaultMsg) {
    mpuDefaultMsg = defaultMsg;
    mpuGetState().dialog.defaultMsg = defaultMsg;
}

// ====== 工具 ======

/**
 * 統一的日誌管理系統
 * 在生產環境中自動過濾調試訊息，只保留錯誤訊息
 */
const mpuLogger = {
    _isDebug: function () {
        return mpuIsDebugMode();
    },
    log: function (...args) {
        if (this._isDebug()) { console.log('[MP Ukagaka]', ...args); }
    },
    warn: function (...args) {
        if (this._isDebug()) { console.warn('[MP Ukagaka]', ...args); }
    },
    error: function (...args) {
        console.error('[MP Ukagaka ERROR]', ...args);
    },
    info: function (...args) {
        if (this._isDebug()) { console.info('[MP Ukagaka]', ...args); }
    }
};

// 向後兼容：保留 debugLog 函數
function debugLog() {
    mpuLogger.log.apply(mpuLogger, arguments);
}

/**
 * 判斷目前是否為深度睡眠時段
 * 優先使用伺服器端時間（避免客戶端/伺服器時區差異）
 * @returns {boolean}
 */
function mpu_isDeepSleepTime() {
    if (typeof window.mpuInfo !== 'undefined' && typeof window.mpuInfo.isDeepSleepTime !== 'undefined') {
        return window.mpuInfo.isDeepSleepTime;
    }
    const hour = new Date().getHours();
    return hour >= 0 && hour < 6;
}

/**
 * 選取下一條對話訊息（隨機或循序）
 * @param {Object} store - 訊息列表物件（含 msg 陣列）
 * @param {number} currentNum - 目前顯示的訊息索引
 * @returns {number} 下一條訊息的索引
 */
function mpu_selectNextMessage(store, currentNum) {
    const msgCount = store.msg.length;
    if (mpuNextMode === "random") {
        let newIdx;
        do {
            newIdx = Math.floor(Math.random() * msgCount);
        } while (newIdx === currentNum && msgCount > 1);
        return newIdx;
    } else {
        return currentNum + 1 >= msgCount ? 0 : currentNum + 1;
    }
}

/**
 * 統一的錯誤處理函數
 * @param {Error|string} error - 錯誤對象或錯誤訊息
 * @param {string} context - 錯誤發生的上下文（函數名或操作描述）
 * @param {Object} options - 可選配置
 * @param {boolean} options.showToUser - 是否向用戶顯示錯誤訊息（預設：false）
 * @param {string} options.userMessage - 自定義用戶友好的錯誤訊息
 * @param {boolean} options.silent - 是否靜默處理（不記錄日誌，預設：false）
 */
function mpu_handle_error(error, context, options = {}) {
    const {
        showToUser = false,
        userMessage = null,
        silent = false
    } = options;

    // 提取錯誤訊息
    const errorMessage = error instanceof Error ? error.message : String(error);
    const errorStack = error instanceof Error ? error.stack : null;

    // 記錄錯誤（除非靜默模式）
    if (!silent) {
        mpuLogger.error(`[${context}]`, errorMessage);
        if (errorStack && mpuIsDebugMode()) {
            mpuLogger.error('Stack trace:', errorStack);
        }
    }

    // 如果需要向用戶顯示錯誤
    if (showToUser) {
        const displayMessage = userMessage ||
            (mpuIsDebugMode() ? errorMessage : ((window.mpuL10n && window.mpuL10n.errorOccurred) || 'エラーが発生しました。後でもう一度お試しください。'));
        const $msgBox = jQuery("#ukagaka_msg");
        if ($msgBox.length) {
            mpu_typewriter(displayMessage, $msgBox);
            if (jQuery("#ukagaka_msgbox").is(":hidden")) {
                mpu_showmsg(200);
            }
        }
    }

    // 返回錯誤對象以便進一步處理
    return {
        message: errorMessage,
        context: context,
        originalError: error
    };
}

/**
 * 打字效果函數（性能優化版）
 * @param {string} text - 要顯示的文字（可包含 HTML）
 * @param {string|jQuery} target - 目標元素選擇器或 jQuery 對象
 * @param {number} speed - 打字速度（毫秒/字元），預設使用 mpuTypewriterSpeed
 * @param {boolean} skipCharacterAnimation - 是否跳過角色動畫，預設 false
 */
function mpu_typewriter(text, target, speed, skipCharacterAnimation) {
    // 清除之前的打字效果
    if (mpuTypewriterTimer !== null) {
        clearTimeout(mpuTypewriterTimer);
        mpuSetTypewriterTimer(null);
    }

    if (!text) {
        const $target = typeof target === 'string' ? jQuery(target) : target;
        $target.html('');
        return;
    }

    const $target = typeof target === 'string' ? jQuery(target) : target;
    const typeSpeed = speed || mpuTypewriterSpeed;

    const targetElement = $target[0] || $target;
    targetElement.innerHTML = '';

    const parts = [];
    let currentIndex = 0;
    let textBuffer = '';
    const textLength = text.length;

    while (currentIndex < textLength) {
        const char = text[currentIndex];

        if (char === '<') {
            if (textBuffer) {
                parts.push({ type: 'text', content: textBuffer });
                textBuffer = '';
            }
            const tagEnd = text.indexOf('>', currentIndex);
            if (tagEnd !== -1) {
                const tagContent = text.substring(currentIndex, tagEnd + 1);
                parts.push({ type: 'tag', content: tagContent });
                currentIndex = tagEnd + 1;
            } else {
                textBuffer += char;
                currentIndex++;
            }
        } else {
            textBuffer += char;
            currentIndex++;
        }
    }
    if (textBuffer) {
        parts.push({ type: 'text', content: textBuffer });
    }

    let totalTextLength = 0;
    for (const part of parts) {
        if (part.type === 'text') {
            totalTextLength += part.content.length;
        }
    }

    const useBatchUpdate = totalTextLength > 50;
    const batchSize = useBatchUpdate ? Math.max(2, Math.min(5, Math.floor(totalTextLength / 20))) : 1;

    let partIndex = 0;
    let charIndex = 0;
    let currentHTML = '';
    let pendingUpdate = false;
    let rafId = null;
    let animationTriggered = false;

    const systemMessages = [
        '（えっと…何話せばいいかな…）',
        '…ああ、記事か。どれどれ…',
        '（思考中…）'
    ];
    
    let plainText = text.replace(/<[^>]*>/g, '').trim();
    const isSystemMessage = systemMessages.some(function(msg) {
        return plainText.indexOf(msg) !== -1;
    });

    // 只有在非系統訊息且未要求跳過動畫時才播放動畫
    if (typeof window.mpuCanvasManager !== 'undefined' && !animationTriggered && !isSystemMessage && !skipCharacterAnimation) {
        animationTriggered = true;
        window.mpuCanvasManager.playAnimation();
    }

    function flushUpdate() {
        if (pendingUpdate && targetElement) {
            targetElement.innerHTML = currentHTML;
            pendingUpdate = false;
        }
    }

    function processNextChar() {
        if (partIndex >= parts.length) {
            if (pendingUpdate) {
                flushUpdate();
            }
            if (rafId) {
                cancelAnimationFrame(rafId);
                rafId = null;
            }
            mpuSetTypewriterTimer(null);
            animationTriggered = false;
            return;
        }

        const part = parts[partIndex];

        if (part.type === 'tag') {
            currentHTML += part.content;
            pendingUpdate = true;

            if (!rafId) {
                rafId = requestAnimationFrame(() => {
                    flushUpdate();
                    rafId = null;
                });
            }

            partIndex++;
            processNextChar();
        } else {
            if (charIndex < part.content.length) {
                if (useBatchUpdate && batchSize > 1) {
                    const endIndex = Math.min(charIndex + batchSize, part.content.length);
                    const batch = part.content.substring(charIndex, endIndex);
                    currentHTML += batch;
                    pendingUpdate = true;
                    charIndex = endIndex;

                    if (!rafId) {
                        rafId = requestAnimationFrame(() => {
                            flushUpdate();
                            rafId = null;
                        });
                    }

                    const batchDelay = Math.max(typeSpeed, typeSpeed * batchSize * 0.7);
                    mpuSetTypewriterTimer(setTimeout(processNextChar, batchDelay));
                } else {
                    currentHTML += part.content[charIndex];
                    pendingUpdate = true;
                    charIndex++;

                    if (!rafId) {
                        rafId = requestAnimationFrame(() => {
                            flushUpdate();
                            rafId = null;
                        });
                    }

                    mpuSetTypewriterTimer(setTimeout(processNextChar, typeSpeed));
                }
            } else {
                partIndex++;
                charIndex = 0;
                processNextChar();
            }
        }
    }

    processNextChar();
}

/**
 * 取消正在進行的打字效果
 * 用於需要立即搶佔訊息框的場景（如裝飾物點擊）
 * @returns {boolean} 是否成功取消
 */
function mpu_cancelTypewriter() {
    if (mpuTypewriterTimer !== null) {
        clearTimeout(mpuTypewriterTimer);
        mpuSetTypewriterTimer(null);
        mpuLogger.log('打字效果已被中斷');
        return true;
    }
    return false;
}

/**
 * 等待打字效果完成後執行回調
 * @param {Function} callback - 打字效果完成後要執行的函數
 * @param {number} maxWaitTime - 最大等待時間（毫秒），預設 30000（30秒）
 * @param {number} checkInterval - 檢查間隔（毫秒），預設 50
 */
function mpu_waitForTypewriterComplete(callback, maxWaitTime, checkInterval) {
    if (typeof callback !== 'function') {
        mpuLogger.warn('mpu_waitForTypewriterComplete: callback 不是函數');
        return;
    }
    
    maxWaitTime = maxWaitTime || 30000;
    checkInterval = checkInterval || 50;
    
    const startTime = Date.now();
    
    function checkTypewriter() {
        // 如果打字效果已完成（計時器為 null）
        if (mpuTypewriterTimer === null) {
            callback();
            return;
        }
        
        // 檢查是否超時
        if (Date.now() - startTime > maxWaitTime) {
            mpuLogger.warn('mpu_waitForTypewriterComplete: 等待超時，強制執行回調');
            callback();
            return;
        }
        
        // 繼續等待
        setTimeout(checkTypewriter, checkInterval);
    }
    
    checkTypewriter();
}

/**
 * 設置本地存儲，支援多種儲存方式
 * @param {string} name - 儲存鍵名
 * @param {*} value - 要儲存的值
 */
function mpu_setLocal(name, value) {
    const data = {
        value,
        expiry: Date.now() + 86400000 // 1 天過期
    };
    let dataStr;
    try {
        dataStr = JSON.stringify(data);
    } catch (e) {
        debugLog("JSON stringify failed:", name, e);
        return;
    }

    try {
        localStorage.setItem(name, dataStr);
        return;
    } catch (e) {
        debugLog("localStorage set failed:", name, e);
    }

    try {
        sessionStorage.setItem(name, dataStr);
        return;
    } catch (e) {
        debugLog("sessionStorage set failed:", name, e);
    }

    try {
        window.__mpuStorage = window.__mpuStorage || {};
        window.__mpuStorage[name] = data;
    } catch (e) {
        debugLog("window storage set failed:", name, e);
    }
}

/**
 * 讀取本地存儲，支援多種儲存方式
 * @param {string} name - 儲存鍵名
 * @returns {*} 儲存的值，若不存在或已過期則返回 null
 */
function mpu_getLocal(name) {
    let itemStr = null;

    try {
        itemStr = localStorage.getItem(name);
    } catch (e) {
        debugLog("localStorage get failed:", name, e);
    }

    if (!itemStr) {
        try {
            itemStr = sessionStorage.getItem(name);
        } catch (e) {
            debugLog("sessionStorage get failed:", name, e);
        }
    }

    if (!itemStr) {
        try {
            if (window.__mpuStorage && window.__mpuStorage[name]) {
                itemStr = JSON.stringify(window.__mpuStorage[name]);
            }
        } catch (e) {
            debugLog("window storage get failed:", name, e);
        }
    }

    if (!itemStr) {
        return null;
    }

    try {
        const data = JSON.parse(itemStr);
        if (data.expiry && Date.now() < data.expiry) {
            return data.value;
        }
        mpu_delLocal(name);
        return null;
    } catch (e) {
        debugLog("JSON parse failed for storage:", name, e);
        return null;
    }
}

/**
 * 刪除本地存儲，支援多種儲存方式
 * @param {string} name - 儲存鍵名
 */
function mpu_delLocal(name) {
    try {
        localStorage.removeItem(name);
    } catch (e) {
        debugLog("localStorage delete failed:", name, e);
    }

    try {
        sessionStorage.removeItem(name);
    } catch (e) {
        debugLog("sessionStorage delete failed:", name, e);
    }

    try {
        if (window.__mpuStorage && window.__mpuStorage[name]) {
            delete window.__mpuStorage[name];
        }
    } catch (e) {
        debugLog("window storage delete failed:", name, e);
    }
}

function mpu_getCookie(name) {
    var nameEQ = name + "=";
    var ca = document.cookie.split(';');
    for (var i = 0; i < ca.length; i++) {
        var c = ca[i];
        while (c.charAt(0) === ' ') c = c.substring(1, c.length);
        if (c.indexOf(nameEQ) === 0) return decodeURIComponent(c.substring(nameEQ.length, c.length));
    }
    return null;
}

function mpu_setCookie(name, value, days, path) {
    path = path || '/';
    var expires = "";
    if (days) {
        var date = new Date();
        date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
        expires = "; expires=" + date.toUTCString();
    }
    document.cookie = name + "=" + encodeURIComponent(value) + expires + "; path=" + path;
}

function mpu_init_jquery_cookie() {
    if (typeof jQuery === 'undefined') {
        mpuLogger.warn('jQuery 尚未載入，無法初始化 jQuery.cookie');
        return false;
    }

    if (typeof jQuery.cookie !== 'undefined') {
        return true;
    }

    jQuery.cookie = function (name, value, options) {
        if (arguments.length > 1 && value !== null && value !== undefined) {
            var opts = options || {};
            var days = opts.expires;
            if (typeof days === 'number') {
                mpu_setCookie(name, value, days, opts.path || '/');
            } else {
                mpu_setCookie(name, value, 0, opts.path || '/');
            }
            return value;
        } else {
            return mpu_getCookie(name);
        }
    };

    return true;
}

/**
 * 初始化閒置偵測：追蹤用戶活動
 */
function mpu_init_idle_detection() {
    if (typeof jQuery === 'undefined') {
        mpuLogger.warn('jQuery 尚未載入，無法初始化閒置偵測');
        return false;
    }

    mpuSetLastUserActionTime(Date.now());

    jQuery(document).on('mousemove keydown scroll click', function() {
        mpuSetLastUserActionTime(Date.now());
    });

    mpuLogger.log('閒置偵測已初始化，閾值：', mpuIdleThreshold / 1000, '秒');
    return true;
}

/**
 * 更新最後訪問時間
 * 將當前時間戳存入 localStorage
 */
function mpu_updateLastVisitTime() {
    try {
        localStorage.setItem('mpu_last_visit_time', Date.now().toString());
        mpuLogger.log('已更新最後訪問時間');
    } catch (e) {
        mpuLogger.warn('無法更新最後訪問時間:', e);
    }
}

/**
 * 獲取上次訪問時間戳
 * @returns {number|null} 上次訪問的時間戳，若為首次訪問則返回 null
 */
function mpu_getLastVisitTime() {
    try {
        const lastVisit = localStorage.getItem('mpu_last_visit_time');
        return lastVisit ? parseInt(lastVisit, 10) : null;
    } catch (e) {
        mpuLogger.warn('無法獲取最後訪問時間:', e);
        return null;
    }
}

/**
 * 計算距離上次訪問的小時數
 * @returns {number} 距離上次訪問的小時數，-1 表示首次訪問
 */
function mpu_getHoursSinceLastVisit() {
    const lastVisit = mpu_getLastVisitTime();
    if (lastVisit === null) {
        return -1; // 首次訪問
    }
    const hours = (Date.now() - lastVisit) / (1000 * 60 * 60);
    return Math.floor(hours);
}

if (typeof jQuery !== 'undefined') {
    mpu_init_jquery_cookie();
    mpu_init_idle_detection();
}
function mpu_unescapeHTML(str) {
    if (!str) return "";
    return String(str)
        .replace(/&amp;/g, "&")
        .replace(/&lt;/g, "<")
        .replace(/&gt;/g, ">")
        .replace(/&nbsp;/g, " ")
        .replace(/&#39;/g, "'")
        .replace(/&quot;/g, '"');
}

/**
 * 將文字中的 URL 轉換為可點擊的 HTML 連結
 * @param {string} text - 要處理的文字
 * @return {string} 處理後的文字
 */
function mpu_linkifyUrls(text) {
    if (!text) return "";
    
    const urlRegex = /(https?:\/\/[^\s<>"']+)/gi;
    
    return text.replace(urlRegex, function(match, p1, offset, string) {
        const url = p1 || match;
        const before = string.substring(Math.max(0, offset - 100), offset);
        const openTags = (before.match(/<a\s[^>]*>/gi) || []).length;
        const closeTags = (before.match(/<\/a>/gi) || []).length;
        
        if (openTags > closeTags) {
            return match;
        }
        
        if (before.match(/href\s*=\s*["'][^"']*$/i)) {
            return match;
        }
        
        return mpu_createLinkFromUrl(url);
    });
}

/**
 * 從 URL 創建 HTML 連結
 * @param {string} url - URL 地址
 * @return {string} HTML 連結標籤
 */
function mpu_createLinkFromUrl(url) {
    let cleanUrl = url.trim();
    const trimmedUrl = cleanUrl.replace(/[.,;:!?]+$/, '');
    
    const escapedUrl = trimmedUrl
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
    
    const displayText = trimmedUrl.length > 60 
        ? trimmedUrl.substring(0, 57) + '...' 
        : trimmedUrl;
    
    const escapedDisplayText = displayText
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
    
    return '<a href="' + escapedUrl + '" target="_blank" rel="noopener noreferrer">' + escapedDisplayText + '</a>';
}

const mpuRequestManager = {
    activeRequests: new Map(),

    defaults: {
        timeout: 30000,
        retries: 2,
        retryDelay: 1000,
        dedupe: false,
        cancelPrevious: false
    },

    generateRequestId: function (url, options = {}) {
        const method = (options.method || 'GET').toUpperCase();
        const body = options.body ? (options.body instanceof FormData ? 'form' : JSON.stringify(options.body)) : '';
        return `${method}:${url}:${body}`;
    },

    cancel: function (requestId) {
        if (this.activeRequests.has(requestId)) {
            const controller = this.activeRequests.get(requestId);
            controller.abort();
            this.activeRequests.delete(requestId);
            mpuLogger.log(`請求已取消: ${requestId}`);
        }
    },

    cancelAll: function () {
        this.activeRequests.forEach((controller, requestId) => {
            controller.abort();
            mpuLogger.log(`請求已取消: ${requestId}`);
        });
        this.activeRequests.clear();
    },

    cleanup: function (requestId) {
        this.activeRequests.delete(requestId);
    }
};

/**
 * 統一的 AJAX 請求函數
 * @param {string} url - 請求 URL
 * @param {Object} options - 請求選項
 * @returns {Promise} 請求 Promise
 */
async function mpuFetch(url, options = {}) {
    const config = {
        ...mpuRequestManager.defaults,
        ...options
    };

    const requestId = config.requestId || mpuRequestManager.generateRequestId(url, options);

    if (config.cancelPrevious) {
        mpuRequestManager.cancel(requestId);
    }

    if (config.dedupe && mpuRequestManager.activeRequests.has(requestId)) {
        mpuLogger.log(`請求去重，跳過: ${requestId}`);
        return Promise.reject(new Error((window.mpuL10n && window.mpuL10n.duplicateRequest) || '重複したリクエストが存在します。後でもう一度お試しください。'));
    }

    const controller = new AbortController();
    mpuRequestManager.activeRequests.set(requestId, controller);

    let timeoutId = null;
    if (config.timeout > 0) {
        timeoutId = setTimeout(() => {
            controller.abort();
            mpuRequestManager.cleanup(requestId);
            mpuLogger.warn(`請求超時: ${requestId}`);
        }, config.timeout);
    }

    const fetchOptions = {
        ...options,
        signal: controller.signal
    };

    // 自動注入 REST API Nonce 與 Session Token
    if (typeof mpuRestUrl !== 'undefined' && url.startsWith(mpuRestUrl)) {
        fetchOptions.headers = fetchOptions.headers || {};
        if (typeof mpuRestNonce !== 'undefined' && !fetchOptions.headers['X-WP-Nonce']) {
            fetchOptions.headers['X-WP-Nonce'] = mpuRestNonce;
        }
        if (!fetchOptions.headers['X-MPU-Session-Token']) {
            const tok = typeof mpuEnsureSessionToken === 'function'
                ? await mpuEnsureSessionToken()
                : null;
            if (tok) fetchOptions.headers['X-MPU-Session-Token'] = tok;
        }
    }

    let lastError = null;
    for (let attempt = 0; attempt <= config.retries; attempt++) {
        try {
            if (attempt > 0) {
                mpuLogger.log(`重試請求 (${attempt}/${config.retries}): ${requestId}`);
                await new Promise(resolve => setTimeout(resolve, config.retryDelay * attempt));
            }

            const response = await fetch(url, fetchOptions);

            if (timeoutId) {
                clearTimeout(timeoutId);
            }

            if (controller.signal.aborted) {
                throw new Error('請求已被取消');
            }

            if (!response.ok) {
                // 嘗試解析 WP REST API 的錯誤訊息（WP_Error 格式：{ code, message, data }）
                let errorMessage = `HTTP ${response.status}: ${response.statusText}`;
                try {
                    const errBody = await response.json();
                    if (errBody && errBody.message) {
                        errorMessage = errBody.message;
                    }
                } catch (_) {}
                // 4xx 是客戶端錯誤（含 429 rate limit），不重試；僅 5xx 才值得重試
                const shouldRetry = response.status >= 500;
                if (!shouldRetry || attempt === config.retries) {
                    throw new Error(errorMessage);
                }
                lastError = new Error(errorMessage);
                continue;
            }

            const contentType = response.headers.get("content-type");
            let result;
            if (contentType && contentType.includes("application/json")) {
                result = await response.json();
            } else {
                result = await response.text();
            }

            mpuRequestManager.cleanup(requestId);

            if (result && typeof result === "object" && result.new_token) {
                window.mpuRestNonce = result.new_token;
                mpuLogger.log("REST Nonce 已自動更新");
            }

            return result;

        } catch (error) {
            lastError = error;

            if (error.name === 'AbortError' || controller.signal.aborted) {
                mpuRequestManager.cleanup(requestId);
                if (timeoutId) {
                    clearTimeout(timeoutId);
                }
                throw new Error('請求已被取消');
            }

            if (attempt < config.retries && (
                error.message.includes('Failed to fetch') ||
                error.message.includes('NetworkError') ||
                error.message.includes('network')
            )) {
                mpuLogger.warn(`網絡錯誤，將重試: ${error.message}`);
                continue;
            }

            if (timeoutId) {
                clearTimeout(timeoutId);
            }
            mpuRequestManager.cleanup(requestId);

            mpu_handle_error(error, 'mpuFetch', {
                silent: true
            });

            throw error;
        }
    }

    if (timeoutId) {
        clearTimeout(timeoutId);
    }
    mpuRequestManager.cleanup(requestId);
    throw lastError || new Error((window.mpuL10n && window.mpuL10n.requestFailed) || 'リクエストに失敗しました');
}

function mpuCancelRequest(url, options = {}) {
    const requestId = mpuRequestManager.generateRequestId(url, options);
    mpuRequestManager.cancel(requestId);
}

function mpuCancelAllRequests() {
    mpuRequestManager.cancelAll();
}

/**
 * 初始化右鍵菜單：在角色上右鍵點擊時顯示切換選單
 */
function mpu_init_context_menu() {
    if (typeof jQuery === 'undefined') {
        mpuLogger.warn('jQuery 尚未載入，無法初始化右鍵菜單');
        return false;
    }

    // 等待 DOM 完全加載
    jQuery(document).ready(function() {
        // 監聽角色圖片的右鍵點擊
        jQuery(document).on('contextmenu', '#ukagaka_img, #cur_ukagaka', function(e) {
            e.preventDefault(); // 阻止默認的右鍵菜單
            
            mpuLogger.log('右鍵菜單觸發：顯示角色切換選單');
            
            // 調用現有的 mpuChange() 函數來顯示角色選擇菜單
            if (typeof mpuChange === 'function') {
                mpuChange(); // 不帶參數調用會顯示選擇菜單
            } else {
                mpuLogger.warn('mpuChange 函數未定義');
            }
            
            return false;
        });
        
        mpuLogger.log('右鍵菜單已初始化');
    });
    
    return true;
}

// 自動初始化右鍵菜單
if (typeof jQuery !== 'undefined') {
    mpu_init_context_menu();
}
