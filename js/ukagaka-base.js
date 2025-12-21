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

let mpuOllamaRequesting = false;
let mpuOllamaRequestQueue = [];
const mpuOllamaQueueDelay = 1500;

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

    if (typeof window.mpuCanvasManager !== 'undefined' && !animationTriggered && !isSystemMessage) {
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
            mpuTypewriterTimer = null;
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
                    mpuTypewriterTimer = setTimeout(processNextChar, batchDelay);
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

                    mpuTypewriterTimer = setTimeout(processNextChar, typeSpeed);
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

    mpuLastUserActionTime = Date.now();

    jQuery(document).on('mousemove keydown scroll click', function() {
        mpuLastUserActionTime = Date.now();
    });

    mpuLogger.log('閒置偵測已初始化，閾值：', mpuIdleThreshold / 1000, '秒');
    return true;
}

if (typeof jQuery !== 'undefined') {
    mpu_init_jquery_cookie();
    mpu_init_idle_detection();
}
function mpu_delCookie(name) {
    return mpu_delLocal(name);
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
        return Promise.reject(new Error('重複請求已存在，請稍後再試'));
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
                if (attempt === config.retries) {
                    throw new Error(`Network response was not ok: ${response.statusText} (${response.status})`);
                }
                lastError = new Error(`HTTP ${response.status}: ${response.statusText}`);
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
    throw lastError || new Error('請求失敗');
}

function mpuCancelRequest(url, options = {}) {
    const requestId = mpuRequestManager.generateRequestId(url, options);
    mpuRequestManager.cancel(requestId);
}

function mpuCancelAllRequests() {
    mpuRequestManager.cancelAll();
}
