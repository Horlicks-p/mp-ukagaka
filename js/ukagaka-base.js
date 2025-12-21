// ====== 全域設定 ======
const mpuClick = "next";
const mpuNextModeInitial = "sequential"; // 暫存變數，實際模式由 mpuMsgList.next_msg 決定
const mpuDefaultMsgInitial = 0;         // 暫存變數，0: 隨機第一條, 1: 第一條
let mpuNextMode = mpuNextModeInitial;
let mpuDefaultMsg = mpuDefaultMsgInitial;
let mpuAutoTalk = false;                // 自動對話開關，預設關閉
let mpuAutoTalkInterval = 12000;        // 自動對話間隔時間（毫秒），預設 12 秒
let mpuAutoTalkTimer = null;            // 自動對話計時器
let debugMode = (typeof window !== 'undefined' && window.mpuDebugMode === true) || false; // 調試模式，可在瀏覽器控制台輸入 window.mpuDebugMode = true 啟用
let mpuAiTextColor = "#000000";         // AI 對話文字顏色
let mpuAiDisplayDuration = 8;           // AI 對話顯示時間（秒）
let mpuAiDisplayTimer = null;           // AI 對話顯示計時器
let mpuGreetInProgress = false;         // 首次訪客打招呼是否正在進行中
let mpuTypewriterTimer = null;          // 打字效果計時器
let mpuTypewriterSpeed = 40;            // 打字速度（毫秒/字元）
let mpuOllamaReplaceDialogue = false;   // 是否使用 LLM 取代內建對話
let mpuAiContextInProgress = false;     // 頁面感知 AI 是否正在進行中（防止自動對話打斷）
let mpuMessageBlocking = false;         // 強制阻擋訊息切換（用於顯示錯誤或重要訊息時防止被打斷）
let mpuLastLLMResponse = '';            // 上一次 LLM 生成的回應（用於避免重複對話）
let mpuLLMResponseHistory = [];         // LLM 回應歷史（最近5次，用於更嚴格的重複檢測）
const mpuMaxResponseHistory = 5;        // 最大歷史記錄數量
let mpuLastUserActionTime = Date.now(); // 記錄最後動作時間（用於閒置偵測）
const mpuIdleThreshold = 60000;         // 閒置閾值：60 秒（1 分鐘），超過此時間則暫停自動對話（可根據需求調整：30秒=30000, 90秒=90000, 180秒=180000）

// ★★★ 方案 C：前端請求節流機制 ★★★
// 用於防止連續快速點擊導致多個 Ollama 請求堆積
let mpuOllamaRequesting = false;        // Ollama 是否正在處理請求
let mpuOllamaRequestQueue = [];         // 等待處理的請求佇列
const mpuOllamaQueueDelay = 1500;       // 佇列處理延遲（毫秒）

// 以記憶體保存已解析的對話資料
window.mpuMsgList = null;

// ====== 工具 ======

/**
 * 統一的日誌管理系統
 * 在生產環境中自動過濾調試訊息，只保留錯誤訊息
 */
const mpuLogger = {
    /**
     * 記錄調試訊息（只在調試模式下顯示）
     * @param {...any} args - 要記錄的參數
     */
    log: function (...args) {
        if (debugMode || (typeof window !== 'undefined' && window.mpuDebugMode === true)) {
            console.log('[MP Ukagaka]', ...args);
        }
    },

    /**
     * 記錄警告訊息（只在調試模式下顯示）
     * @param {...any} args - 要記錄的參數
     */
    warn: function (...args) {
        if (debugMode || (typeof window !== 'undefined' && window.mpuDebugMode === true)) {
            console.warn('[MP Ukagaka]', ...args);
        }
    },

    /**
     * 記錄錯誤訊息（始終記錄，但格式統一）
     * @param {...any} args - 要記錄的參數
     */
    error: function (...args) {
        // 錯誤始終記錄，但使用統一格式
        console.error('[MP Ukagaka ERROR]', ...args);
    },

    /**
     * 記錄資訊訊息（只在調試模式下顯示）
     * @param {...any} args - 要記錄的參數
     */
    info: function (...args) {
        if (debugMode || (typeof window !== 'undefined' && window.mpuDebugMode === true)) {
            console.info('[MP Ukagaka]', ...args);
        }
    }
};

// 向後兼容：保留 debugLog 函數
function debugLog() {
    mpuLogger.log.apply(mpuLogger, arguments);
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
        if (errorStack && (debugMode || window.mpuDebugMode)) {
            mpuLogger.error('Stack trace:', errorStack);
        }
    }

    // 如果需要向用戶顯示錯誤
    if (showToUser) {
        const displayMessage = userMessage ||
            (debugMode || window.mpuDebugMode ? errorMessage : '發生錯誤，請稍後再試');
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
 */
function mpu_typewriter(text, target, speed) {
    // 清除之前的打字效果
    if (mpuTypewriterTimer !== null) {
        clearTimeout(mpuTypewriterTimer);
        mpuTypewriterTimer = null;
    }

    if (!text) {
        const $target = typeof target === 'string' ? jQuery(target) : target;
        $target.html('');
        return;
    }

    const $target = typeof target === 'string' ? jQuery(target) : target;
    const typeSpeed = speed || mpuTypewriterSpeed;

    // 獲取原生 DOM 元素（性能更好，避免 jQuery 開銷）
    const targetElement = $target[0] || $target;

    // 先清空內容
    targetElement.innerHTML = '';

    // 解析 HTML，提取標籤和文字
    const parts = [];
    let currentIndex = 0;
    let textBuffer = '';
    const textLength = text.length;

    // 優化：使用單次遍歷解析
    while (currentIndex < textLength) {
        const char = text[currentIndex];

        if (char === '<') {
            // 遇到標籤開始，先保存之前的文字
            if (textBuffer) {
                parts.push({ type: 'text', content: textBuffer });
                textBuffer = '';
            }
            // 找到標籤結束
            const tagEnd = text.indexOf('>', currentIndex);
            if (tagEnd !== -1) {
                const tagContent = text.substring(currentIndex, tagEnd + 1);
                parts.push({ type: 'tag', content: tagContent });
                currentIndex = tagEnd + 1;
            } else {
                // 沒有找到結束標籤，當作普通文字處理
                textBuffer += char;
                currentIndex++;
            }
        } else {
            textBuffer += char;
            currentIndex++;
        }
    }
    // 保存最後的文字
    if (textBuffer) {
        parts.push({ type: 'text', content: textBuffer });
    }

    // 計算總文字長度，決定是否使用批量更新
    let totalTextLength = 0;
    for (const part of parts) {
        if (part.type === 'text') {
            totalTextLength += part.content.length;
        }
    }

    // 對於長文字（超過 50 字元），使用批量更新以提高性能
    // 批量大小根據文字長度動態調整
    const useBatchUpdate = totalTextLength > 50;
    const batchSize = useBatchUpdate ? Math.max(2, Math.min(5, Math.floor(totalTextLength / 20))) : 1;

    // 開始打字效果
    let partIndex = 0;
    let charIndex = 0;
    let currentHTML = '';
    let pendingUpdate = false;
    let rafId = null;
    let animationTriggered = false; // 標記動畫是否已觸發

    // 檢查是否為系統訊息（不觸發動畫的訊息）
    const systemMessages = [
        '（えっと…何話せばいいかな…）',
        '…ああ、記事か。どれどれ…',
        '（思考中…）'
    ];
    
    // 提取純文字內容（去除 HTML 標籤）用於檢查
    let plainText = text.replace(/<[^>]*>/g, '').trim();
    const isSystemMessage = systemMessages.some(function(msg) {
        return plainText.indexOf(msg) !== -1;
    });

    // 在打字效果開始時觸發 Canvas 動畫（排除系統訊息）
    if (typeof window.mpuCanvasManager !== 'undefined' && !animationTriggered && !isSystemMessage) {
        animationTriggered = true;
        window.mpuCanvasManager.playAnimation();
    }

    /**
     * 批量更新 DOM（使用 requestAnimationFrame 優化渲染時機）
     * 減少 DOM 操作頻率，提高性能
     */
    function flushUpdate() {
        if (pendingUpdate && targetElement) {
            // 直接更新 innerHTML（性能已通過 requestAnimationFrame 優化）
            targetElement.innerHTML = currentHTML;
            pendingUpdate = false;
        }
    }

    /**
     * 處理下一個字元或批次
     */
    function processNextChar() {
        if (partIndex >= parts.length) {
            // 完成，執行最後一次更新
            if (pendingUpdate) {
                flushUpdate();
            }
            if (rafId) {
                cancelAnimationFrame(rafId);
                rafId = null;
            }
            mpuTypewriterTimer = null;
            // 重置動畫觸發標記
            animationTriggered = false;
            return;
        }

        const part = parts[partIndex];

        if (part.type === 'tag') {
            // 標籤立即顯示（不需要打字效果）
            currentHTML += part.content;
            pendingUpdate = true;

            // 使用 requestAnimationFrame 來批量更新，減少重繪次數
            if (!rafId) {
                rafId = requestAnimationFrame(() => {
                    flushUpdate();
                    rafId = null;
                });
            }

            partIndex++;
            // 立即處理下一個部分（不延遲）
            processNextChar();
        } else {
            // 文字顯示
            if (charIndex < part.content.length) {
                if (useBatchUpdate && batchSize > 1) {
                    // 批量更新：一次更新多個字元（性能優化）
                    const endIndex = Math.min(charIndex + batchSize, part.content.length);
                    const batch = part.content.substring(charIndex, endIndex);
                    currentHTML += batch;
                    pendingUpdate = true;
                    charIndex = endIndex;

                    // 使用 requestAnimationFrame 來批量更新
                    if (!rafId) {
                        rafId = requestAnimationFrame(() => {
                            flushUpdate();
                            rafId = null;
                        });
                    }

                    // 批量更新時，延遲時間也相應調整（但保持流暢感）
                    const batchDelay = Math.max(typeSpeed, typeSpeed * batchSize * 0.7);
                    mpuTypewriterTimer = setTimeout(processNextChar, batchDelay);
                } else {
                    // 逐字更新（短文字使用，保持打字效果）
                    currentHTML += part.content[charIndex];
                    pendingUpdate = true;
                    charIndex++;

                    // 使用 requestAnimationFrame 來批量更新
                    if (!rafId) {
                        rafId = requestAnimationFrame(() => {
                            flushUpdate();
                            rafId = null;
                        });
                    }

                    mpuTypewriterTimer = setTimeout(processNextChar, typeSpeed);
                }
            } else {
                // 當前部分完成，處理下一個部分
                partIndex++;
                charIndex = 0;
                processNextChar();
            }
        }
    }

    // 開始處理
    processNextChar();
}

// ========================================
// 本地儲存機制（含 Fallback）
// ========================================

/**
 * 設置本地存儲，支援多種儲存方式
 * 依序嘗試：localStorage -> sessionStorage -> window 變數
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

    // 1. 嘗試 localStorage
    try {
        localStorage.setItem(name, dataStr);
        return; // 成功
    } catch (e) {
        debugLog("localStorage set failed:", name, e);
    }

    // 2. 嘗試 sessionStorage
    try {
        sessionStorage.setItem(name, dataStr);
        return; // 成功
    } catch (e) {
        debugLog("sessionStorage set failed:", name, e);
    }

    // 3. Fallback 到 window 記憶體
    try {
        window.__mpuStorage = window.__mpuStorage || {};
        window.__mpuStorage[name] = data; // 存物件本身
    } catch (e) {
        debugLog("window storage set failed:", name, e);
    }
}

/**
 * 讀取本地存儲，支援多種儲存方式
 * 依序嘗試：localStorage -> sessionStorage -> window 變數
 * @param {string} name - 儲存鍵名
 * @returns {*} 儲存的值，若不存在或已過期則返回 null
 */
function mpu_getLocal(name) {
    let itemStr = null;

    // 1. 嘗試 localStorage
    try {
        itemStr = localStorage.getItem(name);
    } catch (e) {
        debugLog("localStorage get failed:", name, e);
    }

    // 2. 嘗試 sessionStorage
    if (!itemStr) {
        try {
            itemStr = sessionStorage.getItem(name);
        } catch (e) {
            debugLog("sessionStorage get failed:", name, e);
        }
    }

    // 3. Fallback 到 window 記憶體
    if (!itemStr) {
        try {
            if (window.__mpuStorage && window.__mpuStorage[name]) {
                // 從 window 讀取時，它還是物件，需要轉回字串
                itemStr = JSON.stringify(window.__mpuStorage[name]);
            }
        } catch (e) {
            debugLog("window storage get failed:", name, e);
        }
    }

    // 如果所有地方都找不到
    if (!itemStr) {
        return null;
    }

    // --- 統一處理找到的 itemStr ---
    try {
        const data = JSON.parse(itemStr);
        // 檢查是否過期
        if (data.expiry && Date.now() < data.expiry) {
            return data.value;
        }
        // 已過期，刪除它
        mpu_delLocal(name);
        return null;
    } catch (e) {
        debugLog("JSON parse failed for storage:", name, e);
        return null;
    }
}

/**
 * 刪除本地存儲，支援多種儲存方式
 * 依序嘗試：localStorage -> sessionStorage -> window 變數
 * @param {string} name - 儲存鍵名
 */
function mpu_delLocal(name) {
    // 1. 嘗試 localStorage
    try {
        localStorage.removeItem(name);
    } catch (e) {
        debugLog("localStorage delete failed:", name, e);
    }

    // 2. 嘗試 sessionStorage
    try {
        sessionStorage.removeItem(name);
    } catch (e) {
        debugLog("sessionStorage delete failed:", name, e);
    }

    // 3. Fallback 到 window 記憶體
    try {
        if (window.__mpuStorage && window.__mpuStorage[name]) {
            delete window.__mpuStorage[name];
        }
    } catch (e) {
        debugLog("window storage delete failed:", name, e);
    }
}

// ========================================
// Cookie 操作函數（向後兼容 jQuery.cookie）
// ========================================
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

// 為了向後兼容，初始化 jQuery.cookie 包裝器
// 這個函數會在 jQuery 完全載入後被調用
function mpu_init_jquery_cookie() {
    if (typeof jQuery === 'undefined') {
        mpuLogger.warn('jQuery 尚未載入，無法初始化 jQuery.cookie');
        return false;
    }

    if (typeof jQuery.cookie !== 'undefined') {
        // 如果已經存在，就不需要初始化
        return true;
    }

    // 創建 jQuery.cookie 包裝器
    jQuery.cookie = function (name, value, options) {
        if (arguments.length > 1 && value !== null && value !== undefined) {
            // 設置 cookie
            var opts = options || {};
            var days = opts.expires;
            if (typeof days === 'number') {
                mpu_setCookie(name, value, days, opts.path || '/');
            } else {
                mpu_setCookie(name, value, 0, opts.path || '/');
            }
            return value;
        } else {
            // 獲取 cookie
            return mpu_getCookie(name);
        }
    };

    return true;
}

/**
 * 初始化閒置偵測：追蹤用戶活動
 * 當用戶進行 mousemove、keydown、scroll、click 操作時更新最後活動時間
 */
function mpu_init_idle_detection() {
    if (typeof jQuery === 'undefined') {
        mpuLogger.warn('jQuery 尚未載入，無法初始化閒置偵測');
        return false;
    }

    // 初始化最後活動時間
    mpuLastUserActionTime = Date.now();

    // 監聽用戶活動事件
    jQuery(document).on('mousemove keydown scroll click', function() {
        mpuLastUserActionTime = Date.now();
        // 注意：不在此處記錄日誌，因為 mousemove 事件觸發頻率極高（每秒數十次）
        // 在 debug 模式下會導致控制台被洗版，影響調試其他問題
        // 如需調試，可臨時取消以下註釋，但建議只在 click 事件時記錄
        // mpuLogger.log('用戶活動偵測：更新最後活動時間');
    });

    mpuLogger.log('閒置偵測已初始化，閾值：', mpuIdleThreshold / 1000, '秒');
    return true;
}

// 立即嘗試初始化（如果 jQuery 已經載入）
if (typeof jQuery !== 'undefined') {
    mpu_init_jquery_cookie();
    // 初始化閒置偵測：追蹤用戶活動
    mpu_init_idle_detection();
}
function mpu_delCookie(name) {
    return mpu_delLocal(name);
}

// ========================================
// (以下程式碼與 V1 版本完全相同)
// ========================================

// HTML 解碼
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

// ========================================
// AJAX 請求管理系統
// ========================================

/**
 * 請求管理器：追蹤和管理所有活躍的 AJAX 請求
 */
const mpuRequestManager = {
    // 存儲活躍請求：key 為請求 ID，value 為 AbortController
    activeRequests: new Map(),

    // 請求配置預設值
    defaults: {
        timeout: 30000,        // 30 秒超時
        retries: 2,            // 重試次數
        retryDelay: 1000,      // 重試延遲（毫秒）
        dedupe: false,         // 是否去重（相同請求只發送一次）
        cancelPrevious: false  // 是否取消之前的相同請求
    },

    /**
     * 生成請求 ID（用於去重和取消）
     * @param {string} url - 請求 URL
     * @param {Object} options - 請求選項
     * @returns {string} 請求 ID
     */
    generateRequestId: function (url, options = {}) {
        const method = (options.method || 'GET').toUpperCase();
        const body = options.body ? (options.body instanceof FormData ? 'form' : JSON.stringify(options.body)) : '';
        return `${method}:${url}:${body}`;
    },

    /**
     * 取消請求
     * @param {string} requestId - 請求 ID
     */
    cancel: function (requestId) {
        if (this.activeRequests.has(requestId)) {
            const controller = this.activeRequests.get(requestId);
            controller.abort();
            this.activeRequests.delete(requestId);
            mpuLogger.log(`請求已取消: ${requestId}`);
        }
    },

    /**
     * 取消所有請求
     */
    cancelAll: function () {
        this.activeRequests.forEach((controller, requestId) => {
            controller.abort();
            mpuLogger.log(`請求已取消: ${requestId}`);
        });
        this.activeRequests.clear();
    },

    /**
     * 清理已完成的請求
     * @param {string} requestId - 請求 ID
     */
    cleanup: function (requestId) {
        this.activeRequests.delete(requestId);
    }
};

/**
 * 統一的 AJAX 請求函數（增強版）
 * @param {string} url - 請求 URL
 * @param {Object} options - 請求選項
 * @param {number} options.timeout - 超時時間（毫秒，預設 30 秒）
 * @param {number} options.retries - 重試次數（預設 2 次）
 * @param {number} options.retryDelay - 重試延遲（毫秒，預設 1000）
 * @param {boolean} options.dedupe - 是否去重（預設 false）
 * @param {boolean} options.cancelPrevious - 是否取消之前的相同請求（預設 false）
 * @param {string} options.requestId - 自定義請求 ID（用於去重和取消）
 * @returns {Promise} 請求 Promise
 */
async function mpuFetch(url, options = {}) {
    const config = {
        ...mpuRequestManager.defaults,
        ...options
    };

    // 生成請求 ID
    const requestId = config.requestId || mpuRequestManager.generateRequestId(url, options);

    // 檢查是否需要取消之前的請求
    if (config.cancelPrevious) {
        mpuRequestManager.cancel(requestId);
    }

    // 檢查是否需要去重
    if (config.dedupe && mpuRequestManager.activeRequests.has(requestId)) {
        mpuLogger.log(`請求去重，跳過: ${requestId}`);
        // 返回一個被拒絕的 Promise，調用者應該處理這個情況
        return Promise.reject(new Error('重複請求已存在，請稍後再試'));
    }

    // 創建 AbortController
    const controller = new AbortController();
    mpuRequestManager.activeRequests.set(requestId, controller);

    // 設置超時
    let timeoutId = null;
    if (config.timeout > 0) {
        timeoutId = setTimeout(() => {
            controller.abort();
            mpuRequestManager.cleanup(requestId);
            mpuLogger.warn(`請求超時: ${requestId}`);
        }, config.timeout);
    }

    // 合併 AbortSignal
    const fetchOptions = {
        ...options,
        signal: controller.signal
    };

    // 重試邏輯
    let lastError = null;
    for (let attempt = 0; attempt <= config.retries; attempt++) {
        try {
            if (attempt > 0) {
                mpuLogger.log(`重試請求 (${attempt}/${config.retries}): ${requestId}`);
                // 等待重試延遲
                await new Promise(resolve => setTimeout(resolve, config.retryDelay * attempt));
            }

            const response = await fetch(url, fetchOptions);

            // 清除超時
            if (timeoutId) {
                clearTimeout(timeoutId);
            }

            // 檢查是否被取消
            if (controller.signal.aborted) {
                throw new Error('請求已被取消');
            }

            if (!response.ok) {
                // 對於 HTTP 錯誤，只在最後一次嘗試時拋出
                if (attempt === config.retries) {
                    throw new Error(`Network response was not ok: ${response.statusText} (${response.status})`);
                }
                // 否則繼續重試
                lastError = new Error(`HTTP ${response.status}: ${response.statusText}`);
                continue;
            }

            // 解析響應
            const contentType = response.headers.get("content-type");
            let result;
            if (contentType && contentType.includes("application/json")) {
                result = await response.json();
            } else {
                result = await response.text();
            }

            // 清理請求
            mpuRequestManager.cleanup(requestId);

            return result;

        } catch (error) {
            lastError = error;

            // 如果是取消錯誤，直接拋出
            if (error.name === 'AbortError' || controller.signal.aborted) {
                mpuRequestManager.cleanup(requestId);
                if (timeoutId) {
                    clearTimeout(timeoutId);
                }
                throw new Error('請求已被取消');
            }

            // 如果是網絡錯誤且還有重試機會，繼續重試
            if (attempt < config.retries && (
                error.message.includes('Failed to fetch') ||
                error.message.includes('NetworkError') ||
                error.message.includes('network')
            )) {
                mpuLogger.warn(`網絡錯誤，將重試: ${error.message}`);
                continue;
            }

            // 最後一次嘗試或非網絡錯誤，拋出錯誤
            if (timeoutId) {
                clearTimeout(timeoutId);
            }
            mpuRequestManager.cleanup(requestId);

            // 記錄錯誤（但不顯示給用戶，由調用者決定）
            mpu_handle_error(error, 'mpuFetch', {
                silent: true // 靜默記錄，由調用者決定是否顯示
            });

            throw error;
        }
    }

    // 如果所有重試都失敗
    if (timeoutId) {
        clearTimeout(timeoutId);
    }
    mpuRequestManager.cleanup(requestId);
    throw lastError || new Error('請求失敗');
}

/**
 * 取消指定的 AJAX 請求
 * @param {string} url - 請求 URL
 * @param {Object} options - 請求選項（用於生成請求 ID）
 */
function mpuCancelRequest(url, options = {}) {
    const requestId = mpuRequestManager.generateRequestId(url, options);
    mpuRequestManager.cancel(requestId);
}

/**
 * 取消所有 AJAX 請求
 */
function mpuCancelAllRequests() {
    mpuRequestManager.cancelAll();
}
