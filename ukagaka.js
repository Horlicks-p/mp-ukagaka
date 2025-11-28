// ====== 全域設定 (使用 const 和 let) ======
// 【調試】確認腳本已載入
// 注意：此處暫時保留，因為 mpuLogger 尚未定義
// 將在定義後統一替換
const mpuClick = "next";
const mpuNextModeInitial = "sequential"; // 暫存變數，實際模式由 mpuMsgList.next_msg 決定
const mpuDefaultMsgInitial = 0;         // 暫存變數，0: 隨機第一條, 1: 第一條
let mpuNextMode = mpuNextModeInitial;
let mpuDefaultMsg = mpuDefaultMsgInitial;
let mpuAutoTalk = false;                // 預設關閉
let mpuAutoTalkInterval = 12000;        // 12秒 (12000ms)
let mpuAutoTalkTimer = null;
// ★★★ 調試模式：可在瀏覽器控制台輸入 window.mpuDebugMode = true 來啟用 ★★★
let debugMode = (typeof window !== 'undefined' && window.mpuDebugMode === true) || false;
let mpuAiTextColor = "#000000";         // AI 對話文字顏色
let mpuAiDisplayDuration = 8;           // AI 對話顯示時間（秒）
let mpuAiDisplayTimer = null;           // AI 對話顯示計時器
let mpuGreetInProgress = false;         // 首次訪客打招呼是否正在進行中
let mpuTypewriterTimer = null;          // 打字效果計時器
let mpuTypewriterSpeed = 40;            // 打字速度（毫秒/字元）

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
    log: function(...args) {
        if (debugMode || (typeof window !== 'undefined' && window.mpuDebugMode === true)) {
            console.log('[MP Ukagaka]', ...args);
        }
    },
    
    /**
     * 記錄警告訊息（只在調試模式下顯示）
     * @param {...any} args - 要記錄的參數
     */
    warn: function(...args) {
        if (debugMode || (typeof window !== 'undefined' && window.mpuDebugMode === true)) {
            console.warn('[MP Ukagaka]', ...args);
        }
    },
    
    /**
     * 記錄錯誤訊息（始終記錄，但格式統一）
     * @param {...any} args - 要記錄的參數
     */
    error: function(...args) {
        // 錯誤始終記錄，但使用統一格式
        console.error('[MP Ukagaka ERROR]', ...args);
    },
    
    /**
     * 記錄資訊訊息（只在調試模式下顯示）
     * @param {...any} args - 要記錄的參數
     */
    info: function(...args) {
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
// 【★ 8.1 升級】儲存機制 (含 Fallback)
// ========================================

/**
 * 【升級】設置本地存儲，增加 Fallback 機制
 * 嘗試 localStorage -> sessionStorage -> window 變數
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
 * 【升級】讀取本地存儲，增加 Fallback 機制
 * 嘗試 localStorage -> sessionStorage -> window 變數
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
 * 【升級】刪除本地存儲，增加 Fallback 機制
 * 嘗試 localStorage -> sessionStorage -> window 變數
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
// 回溯相容性補丁 (不變)
// ========================================

// ★★★ Cookie 操作函數（替代 jQuery.cookie 插件）★★★
function mpu_getCookie(name) {
    var nameEQ = name + "=";
    var ca = document.cookie.split(';');
    for(var i = 0; i < ca.length; i++) {
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
    jQuery.cookie = function(name, value, options) {
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

// 立即嘗試初始化（如果 jQuery 已經載入）
if (typeof jQuery !== 'undefined') {
    mpu_init_jquery_cookie();
}
function mpu_delCookie(name){
    return mpu_delLocal(name);
}

// ========================================
// (以下程式碼與 V1 版本完全相同)
// ========================================

// HTML 解碼
function mpu_unescapeHTML(str){
  if(!str) return "";
  return String(str)
    .replace(/&amp;/g,"&")
    .replace(/&lt;/g,"<")
    .replace(/&gt;/g,">")
    .replace(/&nbsp;/g," ")
    .replace(/&#39;/g,"'")
    .replace(/&quot;/g,'"');
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
    generateRequestId: function(url, options = {}) {
        const method = (options.method || 'GET').toUpperCase();
        const body = options.body ? (options.body instanceof FormData ? 'form' : JSON.stringify(options.body)) : '';
        return `${method}:${url}:${body}`;
    },
    
    /**
     * 取消請求
     * @param {string} requestId - 請求 ID
     */
    cancel: function(requestId) {
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
    cancelAll: function() {
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
    cleanup: function(requestId) {
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

// 顯示/隱藏春菜 & 訊息
// 【★ 修正】更新按鈕文字從 #show_ukagaka 改為 #remove
function mpu_showrobot(speed = 400){ 
    jQuery("#remove").html(mpuInfo.robot[1]); // "隱藏春菜 ▼"
    jQuery("#ukagaka").fadeIn(speed);
}
// 【★ 修正】更新按鈕文字從 #show_ukagaka 改為 #remove
function mpu_hiderobot(speed = 400){ 
    jQuery("#remove").html(mpuInfo.robot[0]); // "顯示春菜 ▲"
    jQuery("#ukagaka").fadeOut(speed);
}
function mpu_showmsg(speed = 400){ 
    jQuery("#show_msg").html(mpuInfo.msg[1]); 
    jQuery("#ukagaka_msgbox").fadeIn(speed);
}
function mpu_hidemsg(speed = 400){ 
    jQuery("#show_msg").html(mpuInfo.msg[0]); 
    jQuery("#ukagaka_msgbox").fadeOut(speed);
}

function mpu_beforemsg(speed = 400){
    if (jQuery("#ukagaka").is(":hidden")) {
        mpu_showrobot(speed);
    } 
    else if (!jQuery("#ukagaka_msgbox").is(":hidden")) {
        mpu_hidemsg(speed);
    }
}

// ====== 自動對話 ======
function startAutoTalk(){
    stopAutoTalk();
    if (!mpuAutoTalk) return;

    if (jQuery('#ukagaka_msgbox').is(':hidden')) mpu_showmsg(400);

    mpuAutoTalkTimer = setInterval(function(){
        if (mpuAutoTalk) mpu_nextmsg('auto');
        else stopAutoTalk();
    }, mpuAutoTalkInterval);
}

function stopAutoTalk(){
    if (mpuAutoTalkTimer !== null){
        clearInterval(mpuAutoTalkTimer);
        mpuAutoTalkTimer = null;
    }
}

function setAutoTalkUI(){
    const $btn = jQuery('#toggleAutoTalk');
    if ($btn.length) $btn.text(mpuAutoTalk ? '停止自動對話' : '開始自動對話');
}

// ====== 指令處理 ======
function mpuMoe(command){
    if(!command) return false;

    const commands = {
        "(:next)":      () => mpu_nextmsg(),
        "(:showmsg)":   () => mpu_showmsg(400),
        "(:hidemsg)":   () => mpu_hidemsg(400),
        "(:showrobot)": () => mpu_showrobot(400),
        "(:hiderobot)": () => mpu_hiderobot(400),
        "(:previous)":  () => debugLog("(:previous) is not implemented.")
    };
    
    if (commands[command]) {
        commands[command]();
        return;
    }

    // (:msg[n])
    const m = command.match(/^\(:msg\[(\d+)\]\)$/);
    if (m){
        const idx = parseInt(m[1], 10) - 1;
        if (window.mpuMsgList && Array.isArray(window.mpuMsgList.msg)){
            const msgArr = window.mpuMsgList.msg;
            const auto   = window.mpuMsgList.auto_msg || "";
            const safeIdx = idx >= 0 && idx < msgArr.length ? idx : 0;
            const safeMsg = msgArr[safeIdx] + auto;
            
            mpu_beforemsg(400);
            mpu_showmsg(400);
            setTimeout(() => { 
                mpu_typewriter(mpu_unescapeHTML(safeMsg), "#ukagaka_msg");
            }, 510);
        }
        return;
    }

    // 直接發話（會附 auto_msg）
    if (window.mpuMsgList){
        const auto = window.mpuMsgList.auto_msg || "";
        mpu_beforemsg(400);
        mpu_showmsg(400);
        setTimeout(() => { 
            mpu_typewriter(mpu_unescapeHTML(command + auto), "#ukagaka_msg");
        }, 510);
    }
}

// ====== 切換春菜 ======
function mpuChange(num){
    const hasNum = (typeof num !== 'undefined' && num !== null && num !== '');
    
    const params = new URLSearchParams({ action: 'mpu_change' });
    if (hasNum) {
        params.append('mpu_num', num);
    }
    const url = `${mpuurl}?${params.toString()}`;

    // beforeSend:
    document.body.style.cursor = "wait";
    if (!jQuery("#ukagaka_msgbox").is(":hidden")) mpu_hidemsg(200);

    mpuFetch(url, {
        cancelPrevious: true,  // 取消之前的切換請求
        requestId: `mpu_change_${hasNum ? num : 'menu'}`,
        timeout: 15000,  // 15 秒超時
        retries: 1
    })
        .then(res => { // success:
            // === 分支 A: 顯示「選單 HTML」（沒帶 mpu_num） ===
            if (!hasNum){
                if (typeof res !== 'string') throw new Error("Expected HTML, got JSON.");
                jQuery("#ukagaka_msg").html(res || "No content.");
                mpu_showmsg(300);
                jQuery("#ukagaka").stop(true,true).fadeIn(200);
                document.body.style.cursor = "auto";
                return;
            }

            // === 分支 B: 切換人物（有帶 mpu_num，回 JSON） ===
            if (typeof res !== 'object') throw new Error("Expected JSON, got HTML.");
            const payload = res || {};
            const $img = jQuery("#cur_ukagaka");
            const $wrap = jQuery("#ukagaka");

            if (payload.shell) {
                const pre = new Image();
                pre.onload = function(){
                    $img.fadeOut(120, function(){
                        $img.attr({
                            "src": payload.shell,
                            "alt": payload.name || $img.attr("alt") || "",
                            "title": payload.name || $img.attr("title") || ""
                        }).fadeIn(180);
                    });
                };
                pre.onerror = function(){
                    mpu_handle_error(
                        `圖片載入失敗: ${payload.shell}`,
                        'mpuChange:image_load',
                        {
                            showToUser: true,
                            userMessage: "載入圖片失敗，稍後再試。"
                        }
                    );
                };
                pre.src = payload.shell;
            }

            if (payload.num)        jQuery("#ukagaka_num").html(payload.num);
            if (payload.msg)        mpu_typewriter(mpu_unescapeHTML(payload.msg), "#ukagaka_msg");
            if (payload.name)       $img.attr({"alt": payload.name, "title": payload.name});

            const msgListElem = document.getElementById("ukagaka_msglist");
            if (payload.dialog_filename && msgListElem && msgListElem.getAttribute("data-load-external") === "true"){
                const currentFile = msgListElem.getAttribute("data-file") || "";
                const ext = currentFile.split('.').pop() || "json";
                const pure = `${payload.dialog_filename}.${ext}`;
                
                msgListElem.setAttribute("data-file", `dialogs/${pure}`);
                loadExternalDialog(pure);
            } else if (payload.msglist){
                try{
                    window.mpuMsgList = (typeof payload.msglist === 'string') ? JSON.parse(payload.msglist) : payload.msglist;
                }catch(e){ 
                    mpu_handle_error(e, 'mpuChange:parse_msglist');
                    window.mpuMsgList = null; 
                }
            }

            $wrap.stop(true,true).fadeIn(200);
            mpu_showmsg(300);
            if (mpuAutoTalk) startAutoTalk();
            document.body.style.cursor = "auto";
        })
        .catch(error => { // error:
            mpu_handle_error(error, 'mpuChange', {
                showToUser: true,
                userMessage: debugMode || window.mpuDebugMode 
                    ? `載入失敗: ${error.message}` 
                    : "載入失敗，請稍後再試。"
            });
            jQuery("#ukagaka").stop(true,true).fadeIn(200);
            mpu_showmsg(200);
            document.body.style.cursor = "auto";
        });
}


// ====== 下一句 ======
function mpu_nextmsg(trigger){
    const isAuto = (trigger === 'auto');

    if (!isAuto && mpuAutoTalk) {
        startAutoTalk();
    }

    mpu_hidemsg(400);
    setTimeout(function(){
        const store = window.mpuMsgList;
        if (!store || !Array.isArray(store.msg) || store.msg.length === 0){
            mpu_typewriter("訊息列表為空", "#ukagaka_msg");
            mpu_showmsg(400);
            return;
        }

        let msgNum = parseInt(document.getElementById("ukagaka_msgnum").innerHTML, 10) || 0;
        const msgCount = store.msg.length;

        if (mpuNextMode === "random"){
            let newIdx;
            do { newIdx = Math.floor(Math.random() * msgCount); } while (newIdx === msgNum && msgCount > 1);
            msgNum = newIdx;
        } else { // sequential
            msgNum = (msgNum + 1 >= msgCount) ? 0 : (msgNum + 1);
        }

        const auto = store.auto_msg || "";
        const out = store.msg[msgNum] ? (store.msg[msgNum] + auto) : "";
        mpu_typewriter(mpu_unescapeHTML(out), "#ukagaka_msg");
        jQuery("#ukagaka_msgnum").html(msgNum);
        mpu_showmsg(400);
    }, 400);
}

// ====== AI 上下文對話 ======
/**
 * 檢查頁面是否應該觸發 AI
 */
function mpu_check_page_trigger(triggerPages) {
    if (!triggerPages) return false;
    
    const conditions = triggerPages.split(',').map(s => s.trim().toLowerCase());
    const path = window.location.pathname;
    const url = window.location.href;
    
    // 檢查各種 WordPress 條件
    for (let condition of conditions) {
        condition = condition.trim();
        if (!condition) continue;
        
        // is_single: 單篇文章頁面
        if (condition === 'is_single') {
            // 檢查是否為單篇文章：通常有日期格式 /YYYY/MM/DD/ 或直接是文章 slug
            // 排除分類、標籤、作者、頁面等
            if (path.match(/\/\d{4}\/\d{2}\/\d{2}\//) || 
                (path.length > 1 && 
                 !path.match(/^\/(category|tag|author|page|search|archive|feed)/) &&
                 !path.match(/\/page\/\d+/))) {
                return true;
            }
        }
        // is_page: 頁面
        else if (condition === 'is_page') {
            // 簡單檢查：頁面通常沒有日期格式，且不是分類/標籤等
            if (path.length > 1 && 
                !path.match(/^\/(category|tag|author|search|archive|feed)/) &&
                !path.match(/\/\d{4}\/\d{2}\/\d{2}\//)) {
                return true;
            }
        }
        // is_home: 首頁
        else if (condition === 'is_home' || condition === 'is_front_page') {
            if (path === '/' || path.match(/^\/page\/\d+$/)) {
                return true;
            }
        }
        // is_archive: 歸檔頁面
        else if (condition === 'is_archive') {
            if (path.match(/^\/(category|tag|author|date)/)) {
                return true;
            }
        }
    }
    
    return false;
}

/**
 * 獲取頁面上下文
 */
function mpu_get_page_context() {
    const title = document.title;
    
    // 從 article, main, 或 .entry-content 提取內容
    let content = "";
    
    // ★★★ 修改：移除了 || document.body 以避免抓到導航列和頁尾雜訊 ★★★
    const article = document.querySelector('article') || 
                    document.querySelector('main') || 
                    document.querySelector('.entry-content') ||
                    document.querySelector('#content'); // 多加一個常見的 ID
    
    if (article) {
        // 抓取文字，將多個空白合併為一個，並限制長度
        content = article.innerText.replace(/\s+/g, ' ').substring(0, 3000);
    }
    
    return { title, content };
}

/**
 * AI 上下文對話
 */
/**
 * AI 上下文對話
 */
function mpu_chat_context() {
    const context = mpu_get_page_context();
    const contentLength = context.content ? context.content.length : 0;
    
    if (!context.title && !context.content) {
        return;
    }
    
    // 檢查文章內容長度：小於 500 字時不觸發 AI
    if (contentLength < 500) {
        // 使用正常對話系統
        if (window.mpuMsgList && Array.isArray(window.mpuMsgList.msg) && window.mpuMsgList.msg.length > 0) {
            const msgArr = window.mpuMsgList.msg;
            const auto = window.mpuMsgList.auto_msg || "";
            const randomIdx = Math.floor(Math.random() * msgArr.length);
            mpu_typewriter(mpu_unescapeHTML(msgArr[randomIdx] + auto), "#ukagaka_msg");
            if (jQuery("#ukagaka_msgbox").is(":hidden")) mpu_showmsg(200);
        }
        return;
    }
    
    // ★★★ 停止自動對話，避免被覆蓋 ★★★
    const wasAutoTalkRunning = (mpuAutoTalkTimer !== null);
    if (wasAutoTalkRunning) {
        stopAutoTalk();
    }
    
    // 顯示載入訊息，應用設定的文字顏色
    if (jQuery("#ukagaka_msgbox").is(":hidden")) mpu_showmsg(200);
    const loadingMessage = "（…ああ、記事か。どれどれ…）";
    mpu_typewriter(`<span style="color: ${mpuAiTextColor};">${loadingMessage}</span>`, "#ukagaka_msg");
    
    // 發送 AJAX 請求
    const formData = new FormData();
    formData.append('action', 'mpu_chat_context');
    // 如果 nonce 存在，則添加（非強制要求）
    if (typeof mpuNonce !== 'undefined' && mpuNonce) {
        formData.append('mpu_nonce', mpuNonce);
    }
    formData.append('page_title', context.title);
    formData.append('page_content', context.content);
    
    mpuFetch(mpuurl, {
        method: 'POST',
        body: formData,
        cancelPrevious: true,  // 取消之前的 AI 對話請求
        requestId: 'mpu_chat_context',  // 使用固定 ID 以便取消
        timeout: 60000,  // AI 請求可能需要更長時間，設置 60 秒超時
        retries: 1  // AI 請求只重試 1 次
    })
    .then(res => {
        if (res && res.msg && !res.error) {
            const aiResponse = mpu_unescapeHTML(res.msg);
            mpu_typewriter(`<span style="color: ${mpuAiTextColor};">${aiResponse}</span>`, "#ukagaka_msg");
            
            // ★★★ 清除之前的計時器（如果有）★★★
            if (mpuAiDisplayTimer !== null) {
                clearTimeout(mpuAiDisplayTimer);
                mpuAiDisplayTimer = null;
            }
            
            // ★★★ 設置計時器，在指定時間後恢復自動對話 ★★★
            const displayDurationMs = mpuAiDisplayDuration * 1000;
            mpuAiDisplayTimer = setTimeout(function() {
                mpuAiDisplayTimer = null;
                if (wasAutoTalkRunning && mpuAutoTalk) {
                    startAutoTalk();
                }
            }, displayDurationMs);
        } else {
            mpuLogger.warn("AI 對話失敗，使用預設對話系統:", res);
            
            // ★★★ 檢查是否是速率限制錯誤 ★★★
            const isRateLimit = res && res.error && res.error.includes('請求過於頻繁');
            
            if (isRateLimit) {
                // 顯示速率限制訊息
                const rateLimitMessage = "…ちょっと待って。API魔力が足りない";
                mpu_typewriter(`<span style="color: ${mpuAiTextColor};">${rateLimitMessage}</span>`, "#ukagaka_msg");
                
                // 顯示 8 秒後恢復正常對話
                setTimeout(function() {
                    if (window.mpuMsgList && Array.isArray(window.mpuMsgList.msg) && window.mpuMsgList.msg.length > 0) {
                        const msgArr = window.mpuMsgList.msg;
                        const auto = window.mpuMsgList.auto_msg || "";
                        const randomIdx = Math.floor(Math.random() * msgArr.length);
                        mpu_typewriter(mpu_unescapeHTML(msgArr[randomIdx] + auto), "#ukagaka_msg");
                    }
                    // 恢復自動對話
                    if (wasAutoTalkRunning && mpuAutoTalk) {
                        startAutoTalk();
                    }
                }, 8000);
            } else {
                // 其他錯誤，直接恢復正常對話
                if (window.mpuMsgList && Array.isArray(window.mpuMsgList.msg) && window.mpuMsgList.msg.length > 0) {
                    const msgArr = window.mpuMsgList.msg;
                    const auto = window.mpuMsgList.auto_msg || "";
                    const randomIdx = Math.floor(Math.random() * msgArr.length);
                    mpu_typewriter(mpu_unescapeHTML(msgArr[randomIdx] + auto), "#ukagaka_msg");
                }
                // ★★★ AI 對話失敗時也要恢復自動對話 ★★★
                if (wasAutoTalkRunning && mpuAutoTalk) {
                    startAutoTalk();
                }
            }
        }
    })
    .catch(error => {
        mpu_handle_error(error, 'mpu_chat_context', {
            showToUser: false // 已經有 fallback 處理，不需要顯示錯誤
        });
        
        // 顯示錯誤訊息並恢復正常對話
        if (window.mpuMsgList && Array.isArray(window.mpuMsgList.msg) && window.mpuMsgList.msg.length > 0) {
            const msgArr = window.mpuMsgList.msg;
            const auto = window.mpuMsgList.auto_msg || "";
            const randomIdx = Math.floor(Math.random() * msgArr.length);
            mpu_typewriter(mpu_unescapeHTML(msgArr[randomIdx] + auto), "#ukagaka_msg");
        }
        // ★★★ 錯誤時也要恢復自動對話 ★★★
        if (wasAutoTalkRunning && mpuAutoTalk) {
            startAutoTalk();
        }
    });
}

/**
 * 調試用：手動測試訪客資訊獲取
 * 在瀏覽器控制台輸入：mpu_test_visitor_info()
 */
function mpu_test_visitor_info() {
    const visitorParams = new URLSearchParams({ action: 'mpu_get_visitor_info' });
    const visitorUrl = `${mpuurl}?${visitorParams.toString()}`;
    
    mpuFetch(visitorUrl, {
        timeout: 10000,  // 10 秒超時
        retries: 1
    })
        .then(visitorInfo => {
            mpuLogger.log("訪客資訊:", {
                referrer: visitorInfo.referrer || "無",
                referrer_host: visitorInfo.referrer_host || "無",
                search_engine: visitorInfo.search_engine || "無",
                country: visitorInfo.slimstat_country || "無",
                city: visitorInfo.slimstat_city || "無"
            });
        })
        .catch(error => {
            mpu_handle_error(error, 'mpu_test_visitor_info');
        });
}

/**
 * 首次訪客打招呼
 */
function mpu_greet_first_visitor(settings) {
    return new Promise((resolve, reject) => {
        // ★★★ 立即暫停自動對話，防止被打岔 ★★★
        const wasAutoTalkRunning = (mpuAutoTalkTimer !== null);
        if (wasAutoTalkRunning) {
            stopAutoTalk();
        }
        
        // 先獲取訪客資訊
        const visitorParams = new URLSearchParams({ action: 'mpu_get_visitor_info' });
        const visitorUrl = `${mpuurl}?${visitorParams.toString()}`;
        
        mpuFetch(visitorUrl, {
            timeout: 10000,  // 10 秒超時
            retries: 1
        })
            .then(visitorInfo => {
                // 調試模式：顯示基本訪客資訊
                mpuLogger.log("訪客資訊:", {
                    referrer: visitorInfo.referrer || "無",
                    referrer_host: visitorInfo.referrer_host || "無",
                    search_engine: visitorInfo.search_engine || "無",
                    country: visitorInfo.slimstat_country || "無"
                });
                
                // 顯示載入訊息
                if (jQuery("#ukagaka_msgbox").is(":hidden")) mpu_showmsg(200);
                const loadingMessage = "（…あ、知らない人間だ）";
                mpu_typewriter(`<span style="color: ${mpuAiTextColor};">${loadingMessage}</span>`, "#ukagaka_msg");
                
                // 準備打招呼的資料
                const formData = new FormData();
                formData.append('action', 'mpu_chat_greet');
                // 如果 nonce 存在，則添加（非強制要求）
                if (typeof mpuNonce !== 'undefined' && mpuNonce) {
                    formData.append('mpu_nonce', mpuNonce);
                }
                formData.append('referrer', visitorInfo.referrer || '');
                formData.append('referrer_host', visitorInfo.referrer_host || '');
                formData.append('search_engine', visitorInfo.search_engine || '');
                formData.append('is_direct', visitorInfo.is_direct === true ? 'true' : 'false');
                // 添加 Slimstat 提供的地理位置資訊
                formData.append('country', visitorInfo.slimstat_country || visitorInfo.country || '');
                formData.append('city', visitorInfo.slimstat_city || visitorInfo.city || '');
                
                // 發送打招呼請求
                return mpuFetch(mpuurl, {
                    method: 'POST',
                    body: formData,
                    cancelPrevious: true,  // 取消之前的打招呼請求
                    requestId: 'mpu_chat_greet',  // 使用固定 ID 以便取消
                    timeout: 60000,  // AI 請求可能需要更長時間，設置 60 秒超時
                    retries: 1  // AI 請求只重試 1 次
                });
            })
            .then(res => {
                if (res && res.msg && !res.error) {
                    const greetingMessage = mpu_unescapeHTML(res.msg);
                    
                    mpu_typewriter(`<span style="color: ${mpuAiTextColor};">${greetingMessage}</span>`, "#ukagaka_msg");
                    
                    // ★★★ 清除之前的計時器（如果有）★★★
                    if (mpuAiDisplayTimer !== null) {
                        clearTimeout(mpuAiDisplayTimer);
                        mpuAiDisplayTimer = null;
                    }
                    
                    // ★★★ 設置計時器，在指定時間後恢復自動對話 ★★★
                    const displayDurationMs = mpuAiDisplayDuration * 1000;
                    mpuAiDisplayTimer = setTimeout(function() {
                        mpuAiDisplayTimer = null;
                        // ★★★ 恢復自動對話（如果之前在運行）★★★
                        if (wasAutoTalkRunning && settings.auto_talk === true && mpuAutoTalk) {
                            startAutoTalk();
                        }
                        resolve();
                    }, displayDurationMs);
                } else {
                    mpuLogger.warn("首次訪客打招呼失敗:", res);
                    
                    // ★★★ 檢查是否是速率限制錯誤 ★★★
                    const isRateLimit = res && res.error && res.error.includes('請求過於頻繁');
                    
                    if (isRateLimit) {
                        // 顯示速率限制訊息
                        const rateLimitMessage = "…ちょっと待って。API魔力が足りない";
                        mpu_typewriter(`<span style="color: ${mpuAiTextColor};">${rateLimitMessage}</span>`, "#ukagaka_msg");
                        
                        // 顯示 8 秒後恢復正常對話
                        setTimeout(function() {
                            if (window.mpuMsgList && Array.isArray(window.mpuMsgList.msg) && window.mpuMsgList.msg.length > 0) {
                                const msgArr = window.mpuMsgList.msg;
                                const auto = window.mpuMsgList.auto_msg || "";
                                const randomIdx = Math.floor(Math.random() * msgArr.length);
                                mpu_typewriter(mpu_unescapeHTML(msgArr[randomIdx] + auto), "#ukagaka_msg");
                            }
                            // 恢復自動對話
                            if (wasAutoTalkRunning && settings.auto_talk === true && mpuAutoTalk) {
                                startAutoTalk();
                            }
                            // 標記已訪問，避免重複嘗試
                            resolve();
                        }, 8000);
                    } else {
                        // 其他錯誤，直接恢復正常對話
                        if (window.mpuMsgList && Array.isArray(window.mpuMsgList.msg) && window.mpuMsgList.msg.length > 0) {
                            const msgArr = window.mpuMsgList.msg;
                            const auto = window.mpuMsgList.auto_msg || "";
                            const randomIdx = Math.floor(Math.random() * msgArr.length);
                            mpu_typewriter(mpu_unescapeHTML(msgArr[randomIdx] + auto), "#ukagaka_msg");
                        }
                        // ★★★ 失敗時也要恢復自動對話（如果之前在運行）★★★
                        if (wasAutoTalkRunning && settings.auto_talk === true && mpuAutoTalk) {
                            startAutoTalk();
                        }
                        // 失敗時也標記已訪問，避免重複嘗試
                        resolve();
                    }
                }
            })
            .catch(error => {
                mpu_handle_error(error, 'mpu_greet_first_visitor', {
                    showToUser: false // 已經有 fallback 處理
                });
                
                // 顯示錯誤訊息並恢復正常對話
                if (window.mpuMsgList && Array.isArray(window.mpuMsgList.msg) && window.mpuMsgList.msg.length > 0) {
                    const msgArr = window.mpuMsgList.msg;
                    const auto = window.mpuMsgList.auto_msg || "";
                    const randomIdx = Math.floor(Math.random() * msgArr.length);
                    mpu_typewriter(mpu_unescapeHTML(msgArr[randomIdx] + auto), "#ukagaka_msg");
                }
                
                // ★★★ 錯誤時也要恢復自動對話（如果之前在運行）★★★
                if (wasAutoTalkRunning && settings.auto_talk === true && mpuAutoTalk) {
                    startAutoTalk();
                }
                // 錯誤時也標記已訪問，避免重複嘗試
                resolve();
            });
    });
}

// ====== 讀取外部對話 ======
function loadExternalDialog(file){
    const pure = (file || "").replace(/^.*[\\/]/, '');

    const params = new URLSearchParams({
        action: 'mpu_load_dialog',
        file: pure
    });
    const url = `${mpuurl}?${params.toString()}`;

    // beforeSend:
    document.body.style.cursor = "wait";
    if (jQuery("#ukagaka_msgbox").is(":hidden")) mpu_showmsg(200);
    mpu_typewriter("（えっと…何話せばいいかな…）", "#ukagaka_msg");

    mpuFetch(url, {
        cancelPrevious: true,  // 取消之前的載入請求
        requestId: `loadExternalDialog_${pure}`,
        timeout: 15000,  // 15 秒超時
        retries: 1
    })
        .then(resp => { // success:
            if (typeof resp !== 'object') {
                 throw new Error(resp.error || "Expected JSON response from server.");
            }

            if (resp && !resp.error && Array.isArray(resp.msg)) {
                try{
                    window.mpuMsgList = resp;
                    mpuNextMode = (resp.next_msg == 1) ? "random" : "sequential";
                    mpuDefaultMsg = (resp.default_msg == 1) ? 1 : 0;

                    let first = 0;
                    if (mpuDefaultMsg === 0 && resp.msg.length){
                        first = Math.floor(Math.random() * resp.msg.length);
                    }
                    mpu_typewriter(mpu_unescapeHTML(resp.msg[first] + (resp.auto_msg || "")), "#ukagaka_msg");
                    jQuery("#ukagaka_msgnum").html(first);

                    if (mpuAutoTalk) startAutoTalk();
                }catch(e){
                    mpu_handle_error(e, 'loadExternalDialog:process_data', {
                        showToUser: true,
                        userMessage: debugMode || window.mpuDebugMode 
                            ? `處理對話數據時出錯：${e.message}` 
                            : "處理對話數據時出錯，請稍後再試。"
                    });
                }
            } else {
                jQuery("#ukagaka_msg").html((resp && resp.error) ? resp.error : "無法取得對話資料");
            }
            jQuery("#ukagaka").stop(true,true).fadeIn(200);
            document.body.style.cursor = "auto";
        })
        .catch(error => { // error:
            mpu_handle_error(error, 'loadExternalDialog', {
                showToUser: true,
                userMessage: debugMode || window.mpuDebugMode 
                    ? `載入對話文件失敗：${error.message}` 
                    : "載入對話文件失敗，請稍後再試。"
            });
            jQuery("#ukagaka").stop(true,true).fadeIn(200);
            document.body.style.cursor = "auto";
        });
}


// ====== 事件 ======
jQuery(document).ready(function(){
    // 【調試】確認 jQuery ready 已執行
    mpuLogger.log('jQuery ready 已執行');
    
    // 0. 【★ 修正】確保 jQuery.cookie 已初始化
    if (!mpu_init_jquery_cookie()) {
        mpuLogger.error('無法初始化 jQuery.cookie，某些功能可能無法正常工作');
    } else {
        mpuLogger.log('jQuery.cookie 已成功初始化');
    }
    
    // 1. 【★ 修正】 刪除 #show_ukagaka 的 handler

    // 2. 從伺服器獲取最新設定
    const settingsParams = new URLSearchParams({ action: 'mpu_get_settings' });
    const settingsUrl = `${mpuurl}?${settingsParams.toString()}`;

    mpuFetch(settingsUrl, {
        dedupe: true,  // 設定請求可以去重
        requestId: 'mpu_get_settings',
        timeout: 10000,  // 10 秒超時
        retries: 2
    })
        .then(res => { // success:
            if (!res || typeof res !== 'object') return; 

            mpuAutoTalk = (res.auto_talk === true);
            if (res.auto_talk_interval){
                const iv = parseInt(res.auto_talk_interval, 10);
                if (!isNaN(iv) && iv > 0) mpuAutoTalkInterval = iv * 1000;
            }
            if (res.ai_text_color) {
                mpuAiTextColor = res.ai_text_color;
            }
            if (res.ai_display_duration) {
                mpuAiDisplayDuration = parseInt(res.ai_display_duration, 10) || 8;
            }
            if (res.typewriter_speed) {
                mpuTypewriterSpeed = parseInt(res.typewriter_speed, 10) || 40;
                mpuLogger.log('打字速度已設置為:', mpuTypewriterSpeed, 'ms');
            }
            if (mpuAutoTalk) startAutoTalk(); else stopAutoTalk();
            setAutoTalkUI();
            
            // ★★★ 首次訪客打招呼檢查 ★★★
            if (res.ai_enabled === true && res.ai_greet_first_visit === true) {
                // ★★★ 防止重複觸發 ★★★
                if (mpuGreetInProgress) {
                    return;
                }
                
                const firstVisitCookie = 'mpu_first_visit_' + (document.domain || 'default');
                
                // 【★ 修正】確保 jQuery.cookie 已初始化
                if (typeof jQuery.cookie === 'undefined') {
                    mpu_init_jquery_cookie();
                }
                
                // 如果仍然無法使用，使用備用方案
                if (typeof jQuery.cookie === 'undefined') {
                    // 使用 mpu_getCookie 作為備用
                    const isFirstVisit = !mpu_getCookie(firstVisitCookie);
                    if (isFirstVisit) {
                        mpuGreetInProgress = true;
                        mpu_greet_first_visitor(res).then(() => {
                            mpu_setCookie(firstVisitCookie, '1', 365, '/');
                            mpuGreetInProgress = false;
                        }).catch(error => {
                            mpu_handle_error(error, '首次訪客打招呼:catch', {
                                showToUser: false
                            });
                            mpuGreetInProgress = false;
                        });
                    }
                    return;
                }
                
                const isFirstVisit = !jQuery.cookie(firstVisitCookie);
                
                if (isFirstVisit) {
                    // 標記為正在進行中
                    mpuGreetInProgress = true;
                    // 獲取訪客資訊並打招呼
                    mpu_greet_first_visitor(res).then(() => {
                        // 設置 cookie，標記已訪問（保存 1 年）
                        if (typeof jQuery.cookie !== 'undefined') {
                            jQuery.cookie(firstVisitCookie, '1', { expires: 365, path: '/' });
                        } else {
                            // 備用方案：使用 mpu_setCookie
                            mpu_setCookie(firstVisitCookie, '1', 365, '/');
                        }
                        // 重置標記
                        mpuGreetInProgress = false;
                    }).catch(error => {
                        mpu_handle_error(error, '首次訪客打招呼:catch2', {
                            showToUser: false
                        });
                        // 重置標記
                        mpuGreetInProgress = false;
                    });
                    // 首次訪客打招呼時，跳過正常 AI 對話和正常對話系統
                    return;
                }
            }
            
            // AI 自動觸發邏輯
            if (res.ai_enabled === true) {
                // 前端檢查頁面條件
                const shouldTrigger = mpu_check_page_trigger(res.ai_trigger_pages);
                
                if (shouldTrigger) {
                    // 檢查機率
                    const probability = parseInt(res.ai_probability || 10, 10);
                    const roll = Math.floor(Math.random() * 100) + 1;
                    
                    if (roll <= probability) {
                        // 等待 3 秒後觸發 AI
                        setTimeout(function() {
                            mpu_chat_context();
                        }, 3000);
                        return; // 如果觸發了 AI，就不使用正常對話系統
                    }
                }
            }
        })
        .catch(error => {
            mpuLogger.warn("Failed to get mpu_get_settings:", error);
        });


    // 3. 加入自動對話開關
    if (jQuery('#toggleAutoTalk').length === 0){
        const btn = '<li class="auto-talk"><a id="toggleAutoTalk" href="javascript:void(0);" title="自動對話"></a></li>';
        jQuery('#ukagaka-dock ul').append(btn);
        setAutoTalkUI();

        jQuery('#toggleAutoTalk').on('click', function(){
            mpuAutoTalk = !mpuAutoTalk;
            if (mpuAutoTalk) startAutoTalk(); else stopAutoTalk();
            setAutoTalkUI();
        });
    }

    // 4. 外部檔案對話初始化
    const msgListElem = document.getElementById("ukagaka_msglist");
    if (msgListElem && msgListElem.getAttribute("data-load-external") === "true"){
        const dialogFile = msgListElem.getAttribute("data-file");
        if (dialogFile) loadExternalDialog(dialogFile);
    } else {
        // 非外部檔案模式：初始化 mpuMsgList
        try {
            const jsonText = msgListElem.innerHTML.trim();
            if(jsonText) {
                window.mpuMsgList = JSON.parse(jsonText);
                
                if (window.mpuMsgList.next_msg !== undefined) {
                    mpuNextMode = (window.mpuMsgList.next_msg == 1) ? "random" : "sequential";
                }
                if (window.mpuMsgList.default_msg !== undefined) {
                    mpuDefaultMsg = (window.mpuMsgList.default_msg == 1) ? 1 : 0;
                }
            }
        } catch(e) {
            mpu_handle_error(e, 'jQuery.ready:init_dialog_data');
        }
        if (mpuAutoTalk && !mpuAutoTalkTimer) startAutoTalk();
    }


    // 5. 顯示/隱藏訊息
    jQuery("#show_msg").on('click', function(){
        if (jQuery("#ukagaka_msgbox").is(":hidden")){
            mpu_showmsg(400);
            mpu_setLocal("mpuMsg", "show");
        } else {
            mpu_hidemsg(400);
            mpu_setLocal("mpuMsg", "hidden");
        }
    });

    // 6. 點擊春菜圖片
    jQuery("#ukagaka_img").on('click', function(){
        if (jQuery("#ukagaka_msgbox").is(":hidden")) mpu_showmsg(400);
        else mpu_nextmsg();
    });

    // 7. 擴展功能
    jQuery("#mpu_extend").on('click', function(){
        
        const extendParams = new URLSearchParams({ action: 'mpu_extend' });
        const extendUrl = `${mpuurl}?${extendParams.toString()}`;

        // beforeSend:
        document.body.style.cursor = "wait";
        if (jQuery("#ukagaka").is(":hidden")) mpu_showrobot(400);
        else if (!jQuery("#ukagaka_msgbox").is(":hidden")) mpu_hidemsg(200);

        mpuFetch(extendUrl, {
            timeout: 10000,  // 10 秒超時
            retries: 1
        })
            .then(html => { // success:
                if (typeof html !== 'string') throw new Error("Expected HTML response.");
                mpu_showmsg(400);
                jQuery("#ukagaka_msg").html(html);
                document.body.style.cursor = "auto";
            })
            .catch(error => { // error:
                mpu_handle_error(error, 'mpu_extend', {
                    showToUser: true,
                    userMessage: debugMode || window.mpuDebugMode 
                        ? `載入失敗: ${error.message}` 
                        : "載入失敗，請稍後再試。"
                });
                mpu_showmsg(400);
                document.body.style.cursor = "auto";
            });
    });

    // 8. 捲動 / 回頂 / 登出淡出
    jQuery(window).on('scroll', function(){
        const soffset = jQuery('#ukagaka_shell').attr('rel') || 0;
        if (jQuery(this).scrollTop() > soffset) jQuery('#ukagaka_shell').fadeIn();
        else jQuery('#ukagaka_shell').fadeOut();
    });
    jQuery('#toTop').on('click', function(){
        jQuery('body,html').animate({scrollTop:0}, 800);
    });

    // 9. 【★ 修正】 顯示/隱藏春菜 (取代原 #remove 邏輯)
    jQuery('#mp_ukagaka').css("display", "block"); // 確保主容器可見
    jQuery('#remove').on('click', function(){
        const $ukagaka = jQuery("#ukagaka"); // 這是人物+對話的容器
        if ($ukagaka.is(":hidden")){
            mpu_showrobot(400); // 淡入 #ukagaka
            mpu_setLocal("mpuRobot", "show"); //
        } else {
            mpu_hiderobot(400); // 淡出 #ukagaka
            mpu_setLocal("mpuRobot", "hidden"); //
        }
        return false;
    });

    // 10. 【★ 修正】 檢查春菜隱藏狀態
    const robotState = mpu_getLocal('mpuRobot'); //
    if (robotState === 'hidden') {
        jQuery('#ukagaka').css("display", "none"); // 只隱藏人物+對話
        jQuery('#remove').html(mpuInfo.robot[0]); // 設為 "顯示春菜 ▲"
    } else {
        // 預設是顯示，按鈕文字應為 "隱藏春菜 ▼"
        jQuery('#remove').html(mpuInfo.robot[1]); // 設為 "隱藏春菜 ▼"
    }
});

// 視窗焦點/可見性（避免多計時器）
jQuery(window).on('blur', function(){ if (mpuAutoTalk) stopAutoTalk(); });
jQuery(window).on('focus', function(){ if (mpuAutoTalk) startAutoTalk(); });
document.addEventListener('visibilitychange', function(){
    if (document.hidden){ stopAutoTalk(); }
    else if (mpuAutoTalk){ startAutoTalk(); }
});

// 腳本載入完成日誌（在 mpuLogger 定義之後）
mpuLogger.log('腳本載入完成');