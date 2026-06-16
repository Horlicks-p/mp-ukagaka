/**
 * MP Ukagaka Core Bundle
 * Generated: 2026-06-16T04:23:14.144Z
 * 
 * 包含: ukagaka-base.js, ukagaka-core.js, ukagaka-anime.js, ukagaka-emoji.js, ukagaka-context.js, ukagaka-greeting.js, ukagaka-dialog.js, ukagaka-chat-history.js, ukagaka-chat-mode.js, ukagaka-chat-format.js, ukagaka-chat-sse.js, ukagaka-chat-send.js, ukagaka-chat-events.js, ukagaka-chat-wake.js, ukagaka-features.js
 */

// ========== ukagaka-base.js ==========
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
function mpuClearReloadChatSession() {
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
    mpuLogger.logL(
      'pageReloadClearedChatSession',
      '🔄 ページの再読み込みを検出したため、会話履歴とセッション ID をクリアしました'
    );
  }
}

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
async function mpuEnsureSessionToken(forceRefresh = false) {
    if (forceRefresh) {
        window.mpuSessionToken = "";
        mpuState.request.sessionToken = "";
        mpuState.request.sessionTokenPromise = null;
        _mpuSessionTokenPromise = null;
    }
    if (typeof window.mpuSessionToken === 'string' && window.mpuSessionToken) return window.mpuSessionToken;
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
    _missingI18nKeys: new Set(),
    _isDebug: function () {
        return mpuIsDebugMode();
    },
    _getLogBuckets: function () {
        const l10n = (typeof window !== "undefined" && window.mpuL10n) || {};
        return {
            logs: l10n.logs && typeof l10n.logs === "object" ? l10n.logs : {},
            logsDebug: l10n.logsDebug && typeof l10n.logsDebug === "object" ? l10n.logsDebug : {},
        };
    },
    t: function (key, fallback) {
        const buckets = this._getLogBuckets();
        if (Object.prototype.hasOwnProperty.call(buckets.logs, key)) {
            return buckets.logs[key];
        }
        if (Object.prototype.hasOwnProperty.call(buckets.logsDebug, key)) {
            return buckets.logsDebug[key];
        }
        if (this._isDebug() && !this._missingI18nKeys.has(key)) {
            this._missingI18nKeys.add(key);
            console.debug('[MP Ukagaka i18n missing]', key);
        }
        return typeof fallback === "undefined" ? String(key) : String(fallback);
    },
    tFormat: function (key, fallback, ...values) {
        const tpl = this.t(key, fallback);
        if (/%\d+\$[sd]/.test(tpl)) {
            return tpl.replace(/%(\d+)\$[sd]/g, function (match, index) {
                const valueIndex = parseInt(index, 10) - 1;
                return valueIndex >= 0 && valueIndex < values.length ? String(values[valueIndex]) : "";
            });
        }
        let valueIndex = 0;
        return tpl.replace(/%[sd]/g, function () {
            const value = valueIndex < values.length ? values[valueIndex] : "";
            valueIndex += 1;
            return String(value);
        });
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
    },
    logL: function (key, fallback, ...args) {
        if (this._isDebug()) { console.log('[MP Ukagaka]', this.t(key, fallback), ...args); }
    },
    warnL: function (key, fallback, ...args) {
        if (this._isDebug()) { console.warn('[MP Ukagaka]', this.t(key, fallback), ...args); }
    },
    errorL: function (key, fallback, ...args) {
        console.error('[MP Ukagaka ERROR]', this.t(key, fallback), ...args);
    },
    infoL: function (key, fallback, ...args) {
        if (this._isDebug()) { console.info('[MP Ukagaka]', this.t(key, fallback), ...args); }
    },
    logF: function (key, fallback, ...values) {
        if (this._isDebug()) { console.log('[MP Ukagaka]', this.tFormat(key, fallback, ...values)); }
    },
    warnF: function (key, fallback, ...values) {
        if (this._isDebug()) { console.warn('[MP Ukagaka]', this.tFormat(key, fallback, ...values)); }
    },
    errorF: function (key, fallback, ...values) {
        console.error('[MP Ukagaka ERROR]', this.tFormat(key, fallback, ...values));
    },
    infoF: function (key, fallback, ...values) {
        if (this._isDebug()) { console.info('[MP Ukagaka]', this.tFormat(key, fallback, ...values)); }
    },
    warnAlways: function (key, fallback, ...args) {
        console.warn('[MP Ukagaka]', this.t(key, fallback), ...args);
    },
    warnAlwaysF: function (key, fallback, ...values) {
        console.warn('[MP Ukagaka]', this.tFormat(key, fallback, ...values));
    }
};
mpuClearReloadChatSession();
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
 * §16.3-A DOM 標記層 helper：標記 / 清除 system placeholder 屬性。
 *
 * 供「不經 mpu_typewriter、直接 .html() 寫入 placeholder」的路徑使用
 * （streaming chat 初始、SSE status/tool、飾品/touch「思考中」）。
 * 經 mpu_typewriter() 的路徑已自動處理 data-mpu-placeholder，毋需再呼叫。
 *
 * M3 §16.11 Gap I 的 lifecycle 版（含 request token / AbortController / bubble
 * restore）將以此為底層原語擴充；屆時 clear 端若需 token 語義，比照
 * mpu_typewriter 第 4 參數做 polymorphic 收斂，避免改名。
 *
 * @param {string|Element|jQuery} [target="#ukagaka_msg"] 目標容器
 */
function mpuMarkSystemPlaceholder(target) {
    jQuery(target || '#ukagaka_msg').attr('data-mpu-placeholder', 'system');
}

function mpuGetThinkingPlaceholder(context) {
    const placeholders = (window.mpuInitData && window.mpuInitData.thinking_placeholder) || {};
    const language = (window.mpuInitData && window.mpuInitData.language)
        || (window.mpuSettings && window.mpuSettings.language)
        || 'ja';
    if (typeof placeholders === 'string' && placeholders) {
        return placeholders;
    }
    if (placeholders && typeof placeholders === 'object') {
        return placeholders[context] || placeholders[language] || placeholders.default || placeholders.ja || placeholders.en || '';
    }
    return '';
}

function mpuGetDefaultThinkingPlaceholder(context) {
    return mpuGetThinkingPlaceholder(context || 'chat') || 'えっと';
}

function mpuRenderThinkBubble($bubble, text, showSpinner) {
    $bubble.empty().text(text || '');
    if (showSpinner) {
        $bubble.append('<span class="mpu-thinking"></span>');
    }
}

function mpuShowThinkBubble(text, options) {
    const opts = (options && typeof options === 'object') ? options : {};
    const source = opts.source || 'llm';
    const context = opts.context || 'chat';
    const $bubble = jQuery('#ukagaka_think');
    if (!$bubble.length || !text) {
        return;
    }
    mpuRenderThinkBubble($bubble, text, !!opts.showSpinner);
    $bubble
        .attr('data-mpu-think-source', source)
        .attr('data-mpu-think-context', context)
        .prop('hidden', false)
        .addClass('is-visible');
    $bubble.off('click.mpuThinkBubble').on('click.mpuThinkBubble', function () {
        if (jQuery(this).attr('data-mpu-think-source') === 'llm') {
            mpuHideThinkBubble({ source: 'llm' });
        }
    });
    if (source === 'system' && context !== 'initial') {
        jQuery('#ukagaka_msgbox').addClass('mpu-main-bubble-dimmed');
    }
}

function mpuHideThinkBubble(options) {
    const opts = (options && typeof options === 'object') ? options : {};
    const $bubble = jQuery('#ukagaka_think');
    if (!$bubble.length) {
        return;
    }
    if (opts.source && $bubble.attr('data-mpu-think-source') !== opts.source) {
        return;
    }
    $bubble
        .removeClass('is-visible')
        .off('click.mpuThinkBubble')
        .removeAttr('data-mpu-think-source data-mpu-think-context')
        .prop('hidden', true)
        .empty();
    jQuery('#ukagaka_msgbox')
        .removeClass('mpu-main-bubble-dimmed')
        .stop(true, true);
}

function mpuShowSystemPlaceholder(options) {
    const opts = (options && typeof options === 'object') ? options : {};
    const context = opts.context || 'chat';
    const text = opts.text || mpuGetDefaultThinkingPlaceholder(context);
    const shouldHideMain = opts.hideMainDialog === true
        || context === 'context'
        || context === 'greet';
    mpuShowThinkBubble(text, {
        source: 'system',
        context: context,
        showSpinner: opts.showSpinner !== false
    });
    if (shouldHideMain && !jQuery('#ukagaka_msgbox').is(':hidden')) {
        // 立即隱藏（非 fadeOut）：context/greet 的主框要「直接消失」交給思考氣泡。
        // 若用 fadeOut，當頁面感知 API 在動畫期間（<120ms）快速返回時，showMainDialog()
        // 會因主框尚未 :hidden 而不 fadeIn，隨後 mpuClearSystemPlaceholder() 的
        // .stop(true,true) 又把淡出跳到終點，導致正式回應被寫進隱藏的主框（race）。
        jQuery('#ukagaka_msgbox').stop(true, true);
        if (typeof mpu_hidemsg === 'function') {
            mpu_hidemsg(0);
        } else {
            jQuery('#ukagaka_msgbox').hide();
        }
    }
}

function mpuClearSystemPlaceholder(targetOrOptions) {
    const isOptions = targetOrOptions
        && typeof targetOrOptions === 'object'
        && !targetOrOptions.jquery
        && !targetOrOptions.nodeType
        && (Object.prototype.hasOwnProperty.call(targetOrOptions, 'target')
            || Object.prototype.hasOwnProperty.call(targetOrOptions, 'context')
            || Object.prototype.hasOwnProperty.call(targetOrOptions, 'source'));
    const target = isOptions ? targetOrOptions.target : targetOrOptions;
    jQuery(target || '#ukagaka_msg').removeAttr('data-mpu-placeholder');
    mpuHideThinkBubble({ source: 'system' });
}

/**
 * 打字效果函數（性能優化版）
 * @param {string} text - 要顯示的文字（可包含 HTML）
 * @param {string|jQuery} target - 目標元素選擇器或 jQuery 對象
 * @param {number} speed - 打字速度（毫秒/字元），預設使用 mpuTypewriterSpeed
 * @param {boolean|Object} options - 第 4 參數。向下相容：傳 boolean 等同舊的
 *     skipCharacterAnimation。亦可傳 options 物件：
 *       - {boolean} skipCharacterAnimation: 是否跳過角色動畫
 *       - {boolean} systemPlaceholder: 標記此為 system placeholder（如「（思考中…）」/
 *         「えっと…何を話せばいいかな…」）。一律跳過角色動畫，並在目標容器標記
 *         data-mpu-placeholder="system"。§16.3-A 標記式判定，取代舊的 systemMessages
 *         字串黑名單（字串內容比對會隨翻譯漂移而失效，見 §16.2）。
 */
function mpu_typewriter(text, target, speed, options) {
    // 第 4 參數正規化：boolean（舊 skipCharacterAnimation）或 options 物件
    const _opts = (options && typeof options === 'object')
        ? options
        : { skipCharacterAnimation: !!options };
    const isSystemPlaceholder = !!_opts.systemPlaceholder;
    // system placeholder 一律跳過角色動畫（取代舊字串黑名單）
    const skipCharacterAnimation = !!_opts.skipCharacterAnimation || isSystemPlaceholder;
    // 清除之前的打字效果
    if (mpuTypewriterTimer !== null) {
        clearTimeout(mpuTypewriterTimer);
        mpuSetTypewriterTimer(null);
    }

    if (!text) {
        const $target = typeof target === 'string' ? jQuery(target) : target;
        // 清空時一併移除 placeholder 標記，避免 stale attribute 殘留誤判 source
        mpuClearSystemPlaceholder($target);
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

    // §16.3-A：以 systemPlaceholder 標記（而非字串內容比對）決定動畫行為，並在目標容器
    // 標記 data-mpu-placeholder，供 M3 bubble routing 與 §16.3-E 驗收測試使用。
    if (targetElement && typeof targetElement.setAttribute === 'function') {
        if (isSystemPlaceholder) {
            targetElement.setAttribute('data-mpu-placeholder', 'system');
        } else {
            mpuClearSystemPlaceholder($target);
        }
    }

    // 只有在未要求跳過動畫時才播放動畫（system placeholder 已併入 skipCharacterAnimation）
    if (typeof window.mpuCanvasManager !== 'undefined' && !animationTriggered && !skipCharacterAnimation) {
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
        mpuLogger.logL("typewriterInterrupted", "タイピング効果を中断しました");
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
        mpuLogger.warnL("typewriterWaitCallbackInvalid", "mpu_waitForTypewriterComplete: callback が関数ではありません");
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
            mpuLogger.warnL("typewriterWaitTimeoutForcingCallback", "mpu_waitForTypewriterComplete: 待機がタイムアウトしたため、callback を強制実行します");
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
        mpuLogger.warnL("jqueryMissingForCookieInit", "jQuery がまだ読み込まれていないため、jQuery.cookie を初期化できません");
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
        mpuLogger.warnL("jqueryMissingForIdleDetection", "jQuery がまだ読み込まれていないため、無操作検出を初期化できません");
        return false;
    }

    mpuSetLastUserActionTime(Date.now());

    jQuery(document).on('mousemove keydown scroll click', function() {
        mpuSetLastUserActionTime(Date.now());
    });

    mpuLogger.logF("idleDetectionInitialized", "無操作検出を初期化しました。しきい値：%s 秒", mpuIdleThreshold / 1000);
    return true;
}

/**
 * 更新最後訪問時間
 * 將當前時間戳存入 localStorage
 */
function mpu_updateLastVisitTime() {
    try {
        localStorage.setItem('mpu_last_visit_time', Date.now().toString());
        mpuLogger.logL("lastVisitTimeUpdated", "最終訪問時刻を更新しました");
    } catch (e) {
        mpuLogger.warnF("lastVisitTimeUpdateFailed", "最終訪問時刻を更新できませんでした：%s", e);
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
        mpuLogger.warnF("lastVisitTimeReadFailed", "最終訪問時刻を取得できませんでした：%s", e);
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
            mpuLogger.logF("requestCancelled", "リクエストをキャンセルしました：%s", requestId);
        }
    },

    cancelAll: function () {
        this.activeRequests.forEach((controller, requestId) => {
            controller.abort();
            mpuLogger.logF("requestCancelled", "リクエストをキャンセルしました：%s", requestId);
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
        mpuLogger.logF("requestDeduped", "リクエストを重複排除してスキップします：%s", requestId);
        return Promise.reject(new Error((window.mpuL10n && window.mpuL10n.duplicateRequest) || '重複したリクエストが存在します。後でもう一度お試しください。'));
    }

    const controller = new AbortController();
    mpuRequestManager.activeRequests.set(requestId, controller);

    let timeoutId = null;
    if (config.timeout > 0) {
        timeoutId = setTimeout(() => {
            controller.abort();
            mpuRequestManager.cleanup(requestId);
            mpuLogger.warnF("requestTimedOut", "リクエストがタイムアウトしました：%s", requestId);
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
                mpuLogger.logF("requestRetrying", "リクエストを再試行します（%1$s/%2$s）：%3$s", attempt, config.retries, requestId);
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
                mpuLogger.logL("restNonceAutoUpdated", "REST Nonce を自動更新しました");
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
                mpuLogger.warnF("networkErrorWillRetry", "ネットワークエラーのため再試行します：%s", error.message);
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
        mpuLogger.warnL("jqueryMissingForContextMenu", "jQuery がまだ読み込まれていないため、右クリックメニューを初期化できません");
        return false;
    }

    // 等待 DOM 完全加載
    jQuery(document).ready(function() {
        // 監聽角色圖片的右鍵點擊
        jQuery(document).on('contextmenu', '#ukagaka_img, #cur_ukagaka', function(e) {
            e.preventDefault(); // 阻止默認的右鍵菜單
            
            mpuLogger.logL("contextMenuTriggered", "右クリックメニューが作動しました：キャラクター切り替えメニューを表示します");
            
            // 調用現有的 mpuChange() 函數來顯示角色選擇菜單
            if (typeof mpuChange === 'function') {
                mpuChange(); // 不帶參數調用會顯示選擇菜單
            } else {
                mpuLogger.warnL("changeFunctionMissing", "mpuChange 関数が定義されていません");
            }
            
            return false;
        });
        
        mpuLogger.logL("contextMenuInitialized", "右クリックメニューを初期化しました");
    });
    
    return true;
}

// 自動初始化右鍵菜單
if (typeof jQuery !== 'undefined') {
    mpu_init_context_menu();
}

// ========== ukagaka-core.js ==========
// ====== 顯示/隱藏春菜與訊息 ======
/**
 * 顯示春菜人物
 * @param {number} speed - 淡入動畫速度（毫秒），預設 400
 */
function mpu_showrobot(speed = 400) {
  jQuery("#remove").html(mpuInfo.robot[1]); // "隱藏春菜 ▼"
  jQuery("#ukagaka").fadeIn(speed);
}

/**
 * 隱藏春菜人物
 * @param {number} speed - 淡出動畫速度（毫秒），預設 400
 */
function mpu_hiderobot(speed = 400) {
  jQuery("#remove").html(mpuInfo.robot[0]); // "顯示春菜 ▲"
  jQuery("#ukagaka").fadeOut(speed);
}

/**
 * 顯示訊息框
 * @param {number} speed - 淡入動畫速度（毫秒），預設 400
 */
function mpu_showmsg(speed = 400) {
  jQuery("#show_msg").html(mpuInfo.msg[1]);
  jQuery("#ukagaka_msgbox").fadeIn(speed);
}

/**
 * 隱藏訊息框
 * @param {number} speed - 淡出動畫速度（毫秒），預設 400
 */
function mpu_hidemsg(speed = 400) {
  jQuery("#show_msg").html(mpuInfo.msg[0]);
  if (speed === 0 || speed === "") {
    // 如果 speed 為 0 或空字串，直接隱藏（不使用動畫）
    jQuery("#ukagaka_msgbox").hide();
  } else {
    jQuery("#ukagaka_msgbox").fadeOut(speed);
  }
}

async function mpuObservationPush(type, content) {
  if (!window.mpuPageContext || !window.mpuPageContext.postId) return;
  if (typeof window.mpuRestUrl === "undefined") return;

  const send = async () => {
    const headers = { "Content-Type": "application/json" };
    if (typeof window.mpuRestNonce !== "undefined" && window.mpuRestNonce) {
      headers["X-WP-Nonce"] = window.mpuRestNonce;
    }
    if (typeof mpuEnsureSessionToken === "function") {
      const token = await mpuEnsureSessionToken();
      if (token) headers["X-MPU-Session-Token"] = token;
    }

    return fetch(window.mpuRestUrl + "observation/push", {
      method: "POST",
      credentials: "same-origin",
      cache: "no-store",
      headers,
      body: JSON.stringify({ type, content }),
    });
  };

  try {
    let response = await send();
    if (response.status === 403 && typeof mpuEnsureSessionToken === "function") {
      await mpuEnsureSessionToken(true);
      response = await send();
    }
    if (!response.ok && mpuIsDebugMode()) {
      mpuLogger.log("Observation push dropped:", response.status);
    }
  } catch (error) {
    if (mpuIsDebugMode()) {
      mpuLogger.log("Observation push failed:", error && error.message ? error.message : error);
    }
  }
}

function mpuGetObservationPostId() {
  const ctxId = parseInt(window.mpuPageContext && window.mpuPageContext.postId, 10);
  if (ctxId > 0) return ctxId;

  const candidates = [];
  const addCandidate = (value) => {
    const id = parseInt(value, 10);
    if (id > 0 && !candidates.includes(id)) candidates.push(id);
  };

  const path = window.location.pathname || "";
  if (
    path === "/" ||
    /^\/(category|tag|author|search|archive|feed)(\/|$)/.test(path) ||
    /\/page\/\d+\/?$/.test(path)
  ) {
    return 0;
  }

  const bodyClass = document.body ? document.body.className || "" : "";
  const hasSingularBodyClass = /\b(single|single-post|page)\b/.test(bodyClass);
  const articlePostElements = document.querySelectorAll("article[id^='post-'], .hentry[id^='post-']");
  if (!hasSingularBodyClass && articlePostElements.length > 1) {
    return 0;
  }

  const bodyMatch = bodyClass.match(/\b(?:postid|page-id)-(\d+)\b/);
  if (bodyMatch) addCandidate(bodyMatch[1]);

  const postElement = document.querySelector("[data-post-id], article[id^='post-'], .hentry[id^='post-']");
  if (postElement) {
    addCandidate(postElement.getAttribute("data-post-id"));
    const idMatch = (postElement.id || "").match(/\bpost-(\d+)\b/);
    if (idMatch) addCandidate(idMatch[1]);
  }

  return candidates[0] || 0;
}

function mpuClearObservationTracking() {
  const state = window.__mpuObservationState;
  if (state && Array.isArray(state.stayTimers)) {
    state.stayTimers.forEach((timer) => clearTimeout(timer));
  }
  window.__mpuObservationState = null;
  delete window.__mpuObservationStarted;
}

function mpuInitObservationTracking() {
  const postId = mpuGetObservationPostId();
  if (!postId || postId <= 0) {
    mpuClearObservationTracking();
    if (window.mpuPageContext) window.mpuPageContext.postId = 0;
    return;
  }

  if (!window.mpuPageContext) window.mpuPageContext = {};
  window.mpuPageContext.postId = postId;

  const state = window.__mpuObservationState;
  if (state && state.postId === postId && window.__mpuObservationStarted) return;

  mpuClearObservationTracking();
  window.__mpuObservationStarted = true;

  mpuObservationPush("page_view", `post:${postId}`);

  const stayTimers = [];
  [10, 30, 60, 180, 600].forEach((seconds) => {
    const timer = setTimeout(() => {
      mpuObservationPush("stay_duration", `post:${postId}:${seconds}s`);
    }, seconds * 1000);
    stayTimers.push(timer);
  });

  window.__mpuObservationState = { postId, stayTimers };
}

window.addEventListener("beforeunload", mpuClearObservationTracking);

function mpu_showMsgText() {
  const $msg = jQuery("#ukagaka_msg");
  if ($msg.length) $msg.css("visibility", "visible");
}

/**
 * 在顯示訊息前，確保春菜可見且訊息框隱藏
 * @param {number} speed - 動畫速度（毫秒），預設 400
 */
function mpu_beforemsg(speed = 400) {
  if (jQuery("#ukagaka").is(":hidden")) {
    mpu_showrobot(speed);
  } else if (!jQuery("#ukagaka_msgbox").is(":hidden")) {
    mpu_hidemsg(speed);
  }
}

// ====== 自動對話 ======

/**
 * 檢查是否為未被喚醒的睡眠模式
 * 這個函數不依賴 FrierenManager 的初始化狀態
 * @returns {boolean} 是否為睡眠模式且尚未被喚醒
 */
function mpu_isUnawokenSleepMode() {
  const isDeepSleep = mpu_isDeepSleepTime();

  if (!isDeepSleep) {
    return false;
  }

  // 如果角色管理器已初始化，使用它的方法（包含 sleepModeAwoken 檢查）
  // 目前支援 mpuFrierenManager，未來可擴展支援其他角色管理器
  if (
    typeof window.mpuFrierenManager !== "undefined" &&
    window.mpuFrierenManager.isFrierenMode &&
    typeof window.mpuFrierenManager.isSleepMessage === "function"
  ) {
    return window.mpuFrierenManager.isSleepMessage();
  }

  // 角色管理器尚未初始化，直接檢查初始訊息（後備方案）
  const msgElement = document.getElementById("ukagaka_msg");
  if (!msgElement) return false;

  const initialMsg = msgElement.getAttribute("data-initial-msg") || "";

  // 使用隱藏標記檢測睡眠模式（由 PHP 端統一添加）
  return initialMsg.includes("<!-- mpu-sleep -->");
}

/**
 * 啟動自動對話計時器
 */
function startAutoTalk() {
  stopAutoTalk();
  if (!mpuAutoTalk) {
    mpuLogger.logL("autoTalkDisabledExit", "startAutoTalk: mpuAutoTalk が false のため終了します");
    return;
  }

  // 對話模式中不啟動自動對話
  if (typeof mpuChatModeActive !== "undefined" && mpuChatModeActive) {
    mpuLogger.logL("autoTalkSkippedDuringChatMode", "startAutoTalk: 会話モード中のため自動会話を開始しません");
    return;
  }

  // 裝飾物/觸摸對話進行中不啟動自動對話
  if (
    typeof window.mpuFrierenManager !== "undefined" &&
    window.mpuFrierenManager.decorationChatInProgress
  ) {
    mpuLogger.logL("autoTalkSkippedDuringInteractionDialog", "startAutoTalk: 装飾品またはタッチ会話中のため自動会話を開始しません");
    return;
  }

  // 睡眠模式且尚未被喚醒時，不啟動自動對話（只接受 OK 鈕觸發）
  if (mpu_isUnawokenSleepMode()) {
    mpuLogger.logL("autoTalkSkippedUnawokenSleepMode", "🌙 睡眠モードでまだ目を覚ましていないため、自動会話を開始せず OK ボタンのみ受け付けます");
    return;
  }

  // 動態檢查睡眠模式（優先使用伺服器端時間）
  const checkSleepMode = function () {
    const isDeepSleep = mpu_isDeepSleepTime();

    // 獲取基礎間隔（從全域變數或當前設定）
    const baseInterval = mpuGetBaseAutoTalkInterval();

    if (isDeepSleep) {
      // 睡眠模式：使用 frequency_multiplier = 0.111（間隔延長 9 倍，約 3 分鐘）
      const sleepMultiplier = 0.111;
      const adjustedInterval = Math.round(baseInterval / sleepMultiplier);
      return { interval: adjustedInterval, isSleepMode: true };
    } else {
      // 正常模式：使用原始間隔
      return { interval: baseInterval, isSleepMode: false };
    }
  };

  // 計算當前應使用的間隔
  const sleepModeInfo = checkSleepMode();
  const currentInterval = sleepModeInfo.interval;
  const currentIsSleepMode = sleepModeInfo.isSleepMode;

  if (currentIsSleepMode) {
    mpuLogger.logF("autoTalkSleepModeIntervalAdjusted", "🌙 睡眠モードが有効です（00:00〜06:00）。間隔を %1$s ms に調整しました（元: %2$s ms）", currentInterval, mpuGetBaseAutoTalkInterval());
  }

  if (jQuery("#ukagaka_msgbox").is(":hidden")) mpu_showmsg(400);

  mpuLogger.logF("autoTalkTimerSet", "startAutoTalk: タイマーを設定しました。間隔=%1$s ms、mpuAutoTalk=%2$s", currentInterval, mpuAutoTalk);
  mpuSetAutoTalkTimer(setTimeout(function () {
    mpuSetAutoTalkTimer(null); // 清除計時器引用，表示已觸發
    mpuLogger.logF("autoTalkTimerTriggered", "自動会話タイマーが作動しました。mpuAutoTalk=%1$s、mpuOllamaReplaceDialogue=%2$s", mpuAutoTalk, mpuOllamaReplaceDialogue);

    // 閒置檢查：如果用戶閒置超過閾值，跳過本次自動對話
    const now = Date.now();
    const idleTime = now - mpuLastUserActionTime;
    if (idleTime > mpuIdleThreshold) {
      mpuLogger.logF("autoTalkSkippedUserIdle", "ユーザーが無操作状態です（%s 秒）。今回の自動会話をスキップします", Math.floor(idleTime / 1000));
      // 雖然跳過，但仍需重新啟動計時器以檢測下一次
      if (mpuAutoTalk) startAutoTalk();
      return;
    }

    // 每次觸發前重新檢查睡眠模式，動態調整間隔
    const newSleepModeInfo = checkSleepMode();
    if (newSleepModeInfo.isSleepMode !== currentIsSleepMode) {
      mpuLogger.logF("autoTalkSleepModeStateChanged", "睡眠モード状態が変化しました（%1$s → %2$s）。自動会話を再起動します（新しい間隔: %3$s ms）", currentIsSleepMode ? "睡眠" : "通常", newSleepModeInfo.isSleepMode ? "睡眠" : "通常", newSleepModeInfo.interval);
      if (mpuAutoTalk) {
        startAutoTalk();
      }
      return;
    }

    // 檢查是否為睡眠模式且尚未被喚醒，如果是則跳過本次自動對話
    if (mpu_isUnawokenSleepMode()) {
      mpuLogger.logL("autoTalkTickSkippedUnawokenSleepMode", "🌙 睡眠モードでまだ目を覚ましていないため、今回の自動会話をスキップし OK ボタンのみ受け付けます");
      // 重新啟動計時器（雖然這次跳過）
      if (mpuAutoTalk) startAutoTalk();
      return;
    }

    // Akismet 垃圾留言連動：在自動對話前檢查是否有待處理的垃圾留言事件
    if (mpuAutoTalk) {
      mpu_checkSpamEvent(function (spamHandled) {
        if (!spamHandled) {
          // 沒有垃圾留言事件，執行正常的自動對話
          mpu_nextmsg("auto");
        }
        // 重新啟動計時器
        if (mpuAutoTalk) {
          startAutoTalk();
        } else {
          stopAutoTalk();
        }
      });
    } else {
      stopAutoTalk();
    }
  }, currentInterval));
}

/**
 * 停止自動對話計時器
 */
function stopAutoTalk() {
  if (mpuAutoTalkTimer !== null) {
    clearInterval(mpuAutoTalkTimer);
    mpuSetAutoTalkTimer(null);
  }
}

/**
 * 更新自動對話按鈕的 UI 狀態
 */
function setAutoTalkUI() {
  const $btn = jQuery("#toggleAutoTalk");
  if ($btn.length) $btn.text(mpuAutoTalk ? "停止自動對話" : "開始自動對話");
}

/**
 * 檢查是否有 Akismet 攔截的垃圾留言事件
 *
 * @param {Function} callback - 回調函數，參數為 boolean（是否已處理垃圾留言事件）
 */
function mpu_checkSpamEvent(callback) {
  const formData = new FormData();
  // [Fix] 傳送 session_id + history，讓後端在事件觸發後寫入 checksum
  const spamSessionId = typeof mpu_getOrCreateChatSessionId === "function"
    ? mpu_getOrCreateChatSessionId() : "";
  if (spamSessionId) {
    formData.append("session_id", spamSessionId);
  }
  if (typeof mpuChatHistory !== "undefined" && mpuChatHistory.length > 0) {
    formData.append("history", JSON.stringify(mpuChatHistory.slice(-10)));
  }

  mpuFetch(mpuRestUrl + "check-spam-event", {
    method: "POST",
    body: formData,
    timeout: 15000,
    retries: 0,
    requestId: "mpu_check_spam_event",
    cancelPrevious: true,
  })
    .then(function (res) {
      if (res && res.has_event && res.msg) {
        if (res.action === "bot_alert") {
          mpuLogger.logF("autoTalkBotAlertDetected", "🛡️ Bot Alert: Bot の侵入を検出しました。Bot 名: %s", res.bot_name);
        } else if (res.action === "turnstile_block") {
          mpuLogger.logF("autoTalkTurnstileDefenseDetected", "🛡️ Turnstile 結界防御: 結界衝突イベントを検出しました。ブロック回数: %s", res.block_count);
        } else if (res.action === "bot_blocker_alert") {
          mpuLogger.logF("autoTalkBotBlockerDetected", "🛡️ Moelog Bot Blocker: 防御魔法のブロックイベントを検出しました。ブロック数: %s", res.block_count);
        } else if (res.action === "ai_crawler_alert") {
          mpuLogger.logF("autoTalkAiCrawlerDetected", "🤖 AI Crawler: AI クローラーの訪問を検出しました。crawler=%1$s、company=%2$s", res.crawler, res.company);
        } else if (res.action === "visitor_pulse_alert") {
          mpuLogger.logF("autoTalkVisitorPulseDetected", "🌍 Visitor Pulse: 訪問者パルス信号を検出しました。pulse_type=%s", res.pulse_type);
        } else if (res.action === "spam_alert") {
          mpuLogger.logF("autoTalkAkismetSpamDetected", "🛡️ Akismet スパム連携: スパムコメントイベントを検出しました。ブロック数: %s", res.spam_count);
        } else {
          mpuLogger.logF("autoTalkUnclassifiedSecurityEvent", "🛡️ Auto-talk イベント（未分類 action）: %s", res.action);
        }

        // 停止當前的自動對話計時器
        stopAutoTalk();

        // 隱藏當前訊息
        mpu_hidemsg(600);

        setTimeout(function () {
          // 顯示垃圾留言反應台詞
          const aiColor =
            typeof mpuAiTextColor !== "undefined" ? mpuAiTextColor : "#4a6fa5";
          const msg =
            '<span style="color: ' + aiColor + ';">' + res.msg + "</span>";

          mpu_showMsgText();
          mpu_typewriter(msg, "#ukagaka_msg");
          mpu_showmsg(400);

          // 顯示表情（smirk 或 alert）
          if (res.emoji && typeof window.mpuEmojiManager !== "undefined") {
            if (
              typeof window.mpuEmojiConfig === "undefined" ||
              !window.mpuEmojiConfig.baseUrl
            ) {
              if (typeof window.loadEmojiConfig === "function") {
                window
                  .loadEmojiConfig()
                  .then(function () {
                    window.mpuEmojiManager.showEmoji(res.emoji);
                  })
                  .catch(function (error) {
                    mpuLogger.warn(
                      "Akismet: Failed to load emoji config:",
                      error,
                    );
                  });
              }
            } else {
              window.mpuEmojiManager.showEmoji(res.emoji);
            }
          }

          // 觸發角色動畫
          if (
            typeof window.mpuCanvasManager !== "undefined" &&
            window.mpuCanvasManager.isCharacterMode
          ) {
            window.mpuCanvasManager.triggerCharacterAnimation();
          }

          // [Fix] 將安全事件 AI 回應加入對話歷史，讓用戶開對話視窗時 chat/user verify 不會失敗
          if (typeof window.mpuChatHistory !== "undefined" && Array.isArray(window.mpuChatHistory)) {
            // synthetic user 錨點：讓 LLM 能在後續對話中看到此事件的完整脈絡
            window.mpuChatHistory.push({
              role: "user",
              content: "（システムイベントを感知した）",
              type: "synthetic",
              timestamp: Date.now(),
            });
            window.mpuChatHistory.push({
              role: "assistant",
              content: res.msg,
              type: "event",
              timestamp: Date.now(),
            });
            if (typeof mpu_saveChatHistory === "function") {
              mpu_saveChatHistory();
            }
          }

          // 等待打字完成後，通過回調告知已處理
          mpu_waitForTypewriterComplete(function () {
            callback(true);
          });
        }, 700);
      } else {
        // 沒有垃圾留言事件
        mpuLogger.logL("securityCheckNoEvent", "🛡️ Turnstile/Akismet/BotBlocker/Bot Check: イベントはありません");
        callback(false);
      }
    })
    .catch(function (error) {
      mpuLogger.warnF("securityCheckFailed", "Security Check: セキュリティチェックに失敗しました: %s", error);
      // 出錯時不阻擋正常的自動對話
      callback(false);
    });
}

// ====== 下一句對話 ======

function mpu_processOllamaQueue() {
  if (mpuOllamaRequestQueue.length === 0) {
    mpuLogger.logL("ollamaQueueEmpty", "mpu_processOllamaQueue: キューは空です");
    return;
  }

  setTimeout(function () {
    const nextTrigger = mpuOllamaRequestQueue.shift();
    mpuLogger.logF("ollamaQueueProcessingRequest", "mpu_processOllamaQueue: キュー内のリクエストを処理します。trigger=%1$s、残りキュー長=%2$s", nextTrigger, mpuOllamaRequestQueue.length);
    mpu_nextmsg(nextTrigger);
  }, mpuOllamaQueueDelay);
}

/**
 * 顯示下一句對話
 * @param {string} trigger - 觸發方式：'auto'（自動）、'startup'（啟動）、undefined（手動）
 */
function mpu_nextmsg(trigger) {
  const isAuto = trigger === "auto";
  const isStartup = trigger === "startup";
  const isManual = !isAuto && !isStartup; // 手動觸發（使用者點擊按鈕）
  mpuLogger.logF("nextMessageCalled", "mpu_nextmsg が呼び出されました。trigger=%1$s、isAuto=%2$s、isStartup=%3$s、isManual=%4$s、mpuOllamaReplaceDialogue=%5$s", trigger, isAuto, isStartup, isManual, mpuOllamaReplaceDialogue);

  if (mpuMessageBlocking) {
    mpuLogger.logL("nextMessageSkippedMessageBlocking", "mpu_nextmsg: メッセージ表示がブロックされています（mpuMessageBlocking=true）。スキップします");
    return;
  }

  // 對話模式中不執行自動對話
  if (typeof mpuChatModeActive !== "undefined" && mpuChatModeActive) {
    mpuLogger.logL("nextMessageSkippedChatMode", "mpu_nextmsg: 会話モード中のため自動会話をスキップします");
    return;
  }

  // 裝飾物/觸摸對話進行中不執行自動對話
  if (
    typeof window.mpuFrierenManager !== "undefined" &&
    window.mpuFrierenManager.decorationChatInProgress
  ) {
    mpuLogger.logL("nextMessageSkippedInteractionDialog", "mpu_nextmsg: 装飾品またはタッチ会話中のため自動会話をスキップします");
    return;
  }

  if (isAuto && !mpuAutoTalk) {
    mpuLogger.logL("nextMessageAutoTalkDisabledExit", "mpu_nextmsg: 自動会話が無効のため終了します");
    return;
  }

  // 睡眠模式且尚未被喚醒時，跳過自動和啟動觸發（只接受手動觸發）
  if ((isAuto || isStartup) && mpu_isUnawokenSleepMode()) {
    mpuLogger.logF("nextMessageSkippedUnawokenSleepMode", "🌙 睡眠モードでまだ目を覚ましていないため、%s トリガーの会話をスキップし OK ボタンのみ受け付けます", trigger);
    return;
  }

  // 停止當前正在運行的自動對話計時器（如果有的話）
  // 因為我們即將開始一段新對話，需要重新計時
  stopAutoTalk();

  if ((isAuto || isStartup) && mpuAiContextInProgress) {
    mpuLogger.logL("nextMessageSkippedPageAwareInProgress", "mpu_nextmsg: ページ感知 AI が進行中のため、自動/起動会話をスキップします");
    return;
  }

  // 頁面感知即將觸發（3 秒內），避免 startup 的 BOT 對話搶先覆蓋頁面感知
  if (isStartup && mpuIsContextPending()) {
    mpuLogger.logL("nextMessageSkippedScheduledPageAwareStartup", "mpu_nextmsg: ページ感知が予約済みのため、BOT 会話の上書きを避けるため startup をスキップします");
    mpuSetOllamaRequesting(false);
    return;
  }

  if ((isAuto || isStartup) && mpuGreetInProgress) {
    mpuLogger.logL("nextMessageSkippedGreetingInProgress", "mpu_nextmsg: 初回訪問者への挨拶中のため、自動/起動会話をスキップします");
    return;
  }

  if (mpuOllamaReplaceDialogue && mpuOllamaRequesting) {
    if (isAuto) {
      mpuLogger.logL("nextMessageAutoSkippedOllamaBusy", "mpu_nextmsg: Ollama がリクエストを処理中のため、自動トリガーのリクエストをスキップします");
      return;
    }
    if (mpuOllamaRequestQueue.length < 2) {
      mpuLogger.logL("nextMessageQueuedOllamaBusy", "mpu_nextmsg: Ollama がリクエストを処理中のため、このリクエストをキューに追加します");
      mpuOllamaRequestQueue.push(trigger);
    } else {
      mpuLogger.logL("nextMessageSkippedQueueFull", "mpu_nextmsg: キューが満杯のため、このリクエストをスキップします");
    }
    // 注意：這裡不再啟動計時器
    // startup 觸發已被加入佇列，會在佇列處理時自然完成
    // 計時器會在最終的對話完成後由打字完成回調啟動
    return;
  }

  // 手動點擊時不再立即重置計時器，改由打字完成後統一啟動

  // 🌙 睡眠模式喚醒時：讓整個對話框（包括 ZZZ 夢話文字）一起淡出
  if (!isStartup) {
    mpu_hidemsg(600);
  }

  if (mpuOllamaReplaceDialogue) {
    mpuLogger.logL("nextMessageUsingLlm", "mpu_nextmsg: LLM を使用して会話を生成します");

    mpuSetOllamaRequesting(true);
    const curNum = window.mpuInfo?.num || "default_1";
    const curMsgnum =
      parseInt(
        document.getElementById("ukagaka_msgnum")?.innerHTML || "0",
        10,
      ) || 0;

    const formData = new FormData();
    formData.append("cur_num", curNum);
    formData.append("cur_msgnum", curMsgnum);

    // 傳送上次訪問時間（用於問候語選擇）
    const lastVisitHours =
      typeof mpu_getHoursSinceLastVisit === "function"
        ? mpu_getHoursSinceLastVisit()
        : -1;
    formData.append("last_visit_hours", lastVisitHours);

    if (mpuLastLLMResponse) {
      formData.append("last_response", mpuLastLLMResponse);
    }

    if (mpuLLMResponseHistory.length > 0) {
      const recentHistory = mpuLLMResponseHistory.slice(-8);
      formData.append("response_history", JSON.stringify(recentHistory));
    }

    // [Fix] 傳送 session_id + history，讓後端記錄 LLM 自發對話的 checksum
    const nextmsgSessionId = typeof mpu_getOrCreateChatSessionId === "function"
      ? mpu_getOrCreateChatSessionId() : "";
    if (nextmsgSessionId) {
      formData.append("session_id", nextmsgSessionId);
    }
    if (typeof mpuChatHistory !== "undefined" && mpuChatHistory.length > 0) {
      formData.append("history", JSON.stringify(mpuChatHistory.slice(-10)));
    }

    mpuLogger.logF("nextMessageSendingLlmPost", "mpu_nextmsg: LLM POST リクエストを送信します: %s", mpuRestUrl + "nextmsg");

    mpuFetch(mpuRestUrl + "nextmsg", {
      method: "POST",
      body: formData,
      timeout: 60000,
      retries: 1,
      requestId: "mpu_nextmsg_llm",
      cancelPrevious: true,
    })
      .then((res) => {
        mpuLogger.logF("nextMessageLlmResponse", "mpu_nextmsg: LLM 応答 = %s", res);

        if (mpuMessageBlocking || mpuAiContextInProgress) {
          mpuLogger.logL("nextMessageLlmResponseSkippedPageAwareInProgress", "mpu_nextmsg: ページ感知 AI が進行中のため、LLM 応答の表示をスキップします");
          return;
        }

        if (res && res.msg) {
          const auto = mpuGetDialogStore()?.auto_msg || "";
          const out = res.msg + auto;

          // 觸發角色動畫（手動觸發時強制播放）
          if (
            typeof window.mpuCanvasManager !== "undefined" &&
            window.mpuCanvasManager.isCharacterMode
          ) {
            const forceAnimation = !isAuto && !isStartup;
            const skipBookFlip =
              forceAnimation && window.mpuSkipNextManualBookFlip === true;
            if (skipBookFlip) {
              window.mpuSkipNextManualBookFlip = false;
              window.mpuSkipBookFlipExpireToken = null;
            }

            // 喚醒動畫完成後顯示對話
            const isWakingUp =
              window.mpuCanvasManager.triggerCharacterAnimation(
                forceAnimation,
                function () {
                  mpu_cancelTypewriter();
                  jQuery("#ukagaka_msg").html("");
                  mpu_showMsgText();
                  mpu_typewriter(mpu_unescapeHTML(out), "#ukagaka_msg");
                  mpu_showmsg(400);
                },
                skipBookFlip
              );

            if (!isWakingUp) {
              mpu_showMsgText();
              mpu_typewriter(mpu_unescapeHTML(out), "#ukagaka_msg");
              mpu_showmsg(400);
            }
          } else {
            mpu_showMsgText();
            mpu_typewriter(mpu_unescapeHTML(out), "#ukagaka_msg");
            mpu_showmsg(400);
          }

          // 顯示表情（如果有的話）
          if (res.emoji && typeof window.mpuEmojiManager !== "undefined") {
            // 確保配置已載入
            if (
              typeof window.mpuEmojiConfig === "undefined" ||
              !window.mpuEmojiConfig.baseUrl
            ) {
              if (typeof window.loadEmojiConfig === "function") {
                window
                  .loadEmojiConfig()
                  .then(() => {
                    window.mpuEmojiManager.showEmoji(res.emoji);
                  })
                  .catch((error) => {
                    if (typeof mpuLogger !== "undefined" && mpuLogger.warn) {
                      mpuLogger.warn("Failed to load emoji config:", error);
                    }
                  });
                return;
              }
            }
            window.mpuEmojiManager.showEmoji(res.emoji);
          }

          mpuSetLastLLMResponse(res.msg);

          if (mpuLLMResponseHistory.length >= mpuMaxResponseHistory) {
            mpuLLMResponseHistory.shift();
          }
          mpuLLMResponseHistory.push(res.msg);

          // 將自發對話加入對話歷史，讓用戶開對話模式時 AI 記得剛才說過什麼
          if (
            typeof window.mpuChatHistory !== "undefined" &&
            Array.isArray(window.mpuChatHistory)
          ) {
            // synthetic user 錨點：讓 LLM 能在後續對話中看到自語的完整脈絡
            window.mpuChatHistory.push({
              role: "user",
              content: "（独り言）",
              type: "synthetic",
              timestamp: Date.now(),
            });
            window.mpuChatHistory.push({
              role: "assistant",
              content: out,
              type: "auto_talk",
              timestamp: Date.now(),
            });
            mpuLogger.logF("nextMessageSpontaneousAddedToHistory", "mpu_nextmsg: 自発会話を会話履歴に追加しました。現在の履歴長: %s", window.mpuChatHistory.length);
            if (typeof mpu_saveChatHistory === "function") {
              mpu_saveChatHistory();
              mpuLogger.logL("nextMessageHistorySaved", "mpu_nextmsg: 会話履歴を保存しました");
            } else {
              mpuLogger.warnL("nextMessageSaveHistoryMissing", "mpu_nextmsg: mpu_saveChatHistory 関数が存在しないため、会話履歴を保存できません");
            }
          } else {
            mpuLogger.warnL("nextMessageHistoryUnavailable", "mpu_nextmsg: window.mpuChatHistory が未初期化、または配列ではないため、会話履歴に追加できません");
          }

          if (res.msgnum !== undefined) {
            jQuery("#ukagaka_msgnum").html(res.msgnum);
          }

          // ⚠️ LLM 回應成功後，等待打字效果完成再啟動自動對話計時器
          if (mpuAutoTalk && !mpuAutoTalkTimer) {
            mpuLogger.logL("nextMessageLlmCompleteWaitingForTypewriter", "mpu_nextmsg: LLM 応答が完了しました。タイピング完了後に自動会話タイマーを開始します");
            mpu_waitForTypewriterComplete(function () {
              if (mpuAutoTalk && !mpuAutoTalkTimer) {
                mpuLogger.logL("nextMessageTypewriterCompleteStartingAutoTalk", "mpu_nextmsg: タイピングが完了しました。自動会話タイマーを開始します");
                startAutoTalk();
              }
            });
          }
        } else {
          mpuLogger.warnL("nextMessageLlmResponseMissingMessage", "mpu_nextmsg: LLM 応答に msg がありません", res);

          // 檢查是否為速率限制錯誤（請求過於頻繁）
          const isRateLimit =
            (res && res.error && (res.error.includes("請求過於頻繁") || res.error.includes("リクエストが多すぎます"))) ||
            (res && res.code === "rest_rate_limit_exceeded");

          if (isRateLimit) {
            const rateLimitMessage =
              typeof mpuL10n !== "undefined" && mpuL10n.apiMagicInsufficient
                ? mpuL10n.apiMagicInsufficient
                : "…ちょっと待って。API魔力が足りない";

            mpuSetLastLLMResponse("");
            mpuResetLLMResponseHistory();

            // 顯示 API 魔力不足提示，暫時阻擋自發對話
            mpu_showMsgText();
            mpu_typewriter(
              `<span style="color: ${mpuAiTextColor};">${rateLimitMessage}</span>`,
              "#ukagaka_msg",
            );
            mpu_showmsg(400);

            mpuSetMessageBlocking(true);
            const waitTime = (mpuAiDisplayDuration || 8) * 1000;

            setTimeout(function () {
              mpuSetMessageBlocking(false);

              // 顯示一條內建對話作為後備，避免角色一直沉默
              mpu_nextmsg_fallback();

              // 若原本有自動對話，則在冷卻後恢復
              if (mpuAutoTalk && !mpuAutoTalkTimer) {
                startAutoTalk();
              }
            }, waitTime);
          } else {
            mpuLogger.warnL("nextMessageLlmMissingMessageUsingFallback", "mpu_nextmsg: LLM 応答に msg がないため、フォールバック会話を使用します");
            mpuSetLastLLMResponse("");
            mpuResetLLMResponseHistory();
            mpu_nextmsg_fallback();

            // ⚠️ 即使 fallback，也等待打字完成再啟動自動對話
            if (mpuAutoTalk && !mpuAutoTalkTimer) {
              mpuLogger.logL("nextMessageFallbackCompleteWaitingForTypewriter", "mpu_nextmsg: フォールバックが完了しました。タイピング完了後にタイマーを開始します");
              mpu_waitForTypewriterComplete(function () {
                if (mpuAutoTalk && !mpuAutoTalkTimer) {
                  startAutoTalk();
                }
              });
            }
          }
        }

        mpuSetOllamaRequesting(false);
        mpu_processOllamaQueue();
      })
      .catch((error) => {
        mpuSetOllamaRequesting(false);
        mpu_processOllamaQueue();

        // ⚠️ 即使出錯，也等待打字完成再啟動自動對話
        if (mpuAutoTalk && !mpuAutoTalkTimer) {
          mpuLogger.logL("nextMessageErrorWaitingForTypewriter", "mpu_nextmsg: エラーが発生しました。タイピング完了後にタイマーを開始します");
          mpu_waitForTypewriterComplete(function () {
            if (mpuAutoTalk && !mpuAutoTalkTimer) {
              startAutoTalk();
            }
          });
        }

        if (mpuMessageBlocking || mpuAiContextInProgress) {
          mpuLogger.logL("nextMessageLlmErrorSkippedPageAwareInProgress", "mpu_nextmsg: ページ感知 AI が進行中のため、LLM エラー処理をスキップします");
          return;
        }
        mpuLogger.warn(
          "LLM dialogue generation failed, using fallback:",
          error,
        );

        if (mpuIsDebugMode()) {
          const errorMsg = error.message || "LLM 連接失敗";
          const debugMessage = `<span style="color: #ff4444;">[LLM 錯誤: ${errorMsg}]</span>`;
          mpu_showMsgText();
          mpu_typewriter(debugMessage, "#ukagaka_msg");
          mpu_showmsg(400);
          setTimeout(() => {
            mpuSetLastLLMResponse("");
            mpu_nextmsg_fallback();
          }, 2000);
        } else {
          mpuSetLastLLMResponse("");
          mpu_nextmsg_fallback();
        }
      });
    return;
  }

  setTimeout(function () {
    const store = mpuGetDialogStore();

    if (!store) {
      mpuLogger.warnL("nextMessageWaitingForDialogLoad", "mpu_nextmsg: 会話がまだ読み込まれていません。読み込み完了を待機します...");
      const retryCount = mpuGetState().retry.nextMessage || 0;
      if (retryCount < 3) {
        mpuGetState().retry.nextMessage = retryCount + 1;
        setTimeout(() => {
          mpu_nextmsg(trigger);
        }, 1000);
      } else {
        mpuGetState().retry.nextMessage = 0;
        mpu_showMsgText();
        mpu_typewriter((window.mpuL10n && window.mpuL10n.dialogNotLoaded) || "ダイアログがまだ読み込まれていません。お待ちください...", "#ukagaka_msg");
        mpu_showmsg(400);
        mpuLogger.warnL("nextMessageDialogLoadTimeout", "mpu_nextmsg: 会話読み込みがタイムアウトしました。3 回再試行済みです");
      }
      return;
    }

    mpuGetState().retry.nextMessage = 0;

    if (!Array.isArray(store.msg) || store.msg.length === 0) {
      const errorMsg =
        store.msg && store.msg.length === 0
          ? "對話文件為空，請檢查對話文件內容"
          : "訊息列表格式錯誤";
      mpu_typewriter(errorMsg, "#ukagaka_msg");
      mpu_showmsg(400);
      mpuLogger.warnF("nextMessageCannotDisplayDialog", "mpu_nextmsg: 会話を表示できません - store=%1$s、msgArray=%2$s", store ? "exists" : "null", store && Array.isArray(store.msg) ? `length=${store.msg.length}` : "not array");
      return;
    }

    const $msgnum = jQuery("#ukagaka_msgnum");
    let msgNum = parseInt($msgnum.html(), 10) || 0;
    msgNum = mpu_selectNextMessage(store, msgNum);

    const auto = store.auto_msg || "";
    const out = store.msg[msgNum] ? store.msg[msgNum] + auto : "";

    // 觸發角色動畫（手動觸發時強制播放）
    if (
      typeof window.mpuCanvasManager !== "undefined" &&
      window.mpuCanvasManager.isFrierenMode
    ) {
      // 喚醒動畫完成後顯示對話
      const skipBookFlip =
        isManual && window.mpuSkipNextManualBookFlip === true;
      if (skipBookFlip) {
        window.mpuSkipNextManualBookFlip = false;
        window.mpuSkipBookFlipExpireToken = null;
      }

      const isWakingUp = window.mpuCanvasManager.triggerCharacterAnimation(
        isManual,
        function () {
          mpu_cancelTypewriter();
          jQuery("#ukagaka_msg").html("");
          mpu_showMsgText();
          mpu_typewriter(mpu_unescapeHTML(out), "#ukagaka_msg");
          $msgnum.html(msgNum);
          mpu_showmsg(400);
        },
        skipBookFlip
      );

      if (!isWakingUp) {
        mpu_showMsgText();
        mpu_typewriter(mpu_unescapeHTML(out), "#ukagaka_msg");
        $msgnum.html(msgNum);
        mpu_showmsg(400);
      }
    } else {
      mpu_showMsgText();
      mpu_typewriter(mpu_unescapeHTML(out), "#ukagaka_msg");
      $msgnum.html(msgNum);
      mpu_showmsg(400);
    }

    // 將傳統對話加入歷史，確保互動對話模式有完整脈絡
    if (out && typeof window.mpuChatHistory !== "undefined" && Array.isArray(window.mpuChatHistory)) {
      window.mpuChatHistory.push({ role: "user", content: "（独り言）", type: "synthetic", timestamp: Date.now() });
      window.mpuChatHistory.push({ role: "assistant", content: mpu_unescapeHTML(out), type: "auto_talk", timestamp: Date.now() });
      if (typeof mpu_saveChatHistory === "function") mpu_saveChatHistory();
    }

    // ⚠️ 傳統對話流程：等待打字完成後重啟自動對話計時器
    if (mpuAutoTalk && !mpuAutoTalkTimer) {
      mpuLogger.logL("nextMessageTraditionalDialogWaitingForTypewriter", "mpu_nextmsg: 通常会話です。タイピング完了後にタイマーを開始します");
      mpu_waitForTypewriterComplete(function () {
        if (mpuAutoTalk && !mpuAutoTalkTimer) {
          startAutoTalk();
        }
      });
    }
  }, 400);
}

function mpu_nextmsg_fallback() {
  setTimeout(function () {
    mpu_showMsgText();
    if (mpuMessageBlocking || mpuAiContextInProgress) {
      mpuLogger.logL("nextMessageFallbackSkippedPageAwareInProgress", "mpu_nextmsg_fallback: ページ感知 AI が進行中のため、表示をスキップします");
      return;
    }

    const store = mpuGetDialogStore();

    if (!store) {
      mpuLogger.warnL("nextMessageFallbackWaitingForDialogLoad", "mpu_nextmsg_fallback: 会話がまだ読み込まれていません。読み込み完了を待機します...");
      const retryCount = mpuGetState().retry.fallbackMessage || 0;
      if (retryCount < 2) {
        mpuGetState().retry.fallbackMessage = retryCount + 1;
        setTimeout(() => {
          mpu_nextmsg_fallback();
        }, 1500);
      } else {
        mpuGetState().retry.fallbackMessage = 0;
        mpu_showMsgText();
        mpu_typewriter((window.mpuL10n && window.mpuL10n.dialogNotLoaded) || "ダイアログがまだ読み込まれていません。お待ちください...", "#ukagaka_msg");
        mpu_showmsg(400);
        mpuLogger.warnL("nextMessageFallbackDialogLoadTimeout", "mpu_nextmsg_fallback: 会話読み込みがタイムアウトしました。2 回再試行済みです");
      }
      return;
    }

    mpuGetState().retry.fallbackMessage = 0;

    if (!Array.isArray(store.msg) || store.msg.length === 0) {
      const errorMsg =
        store.msg && store.msg.length === 0
          ? "對話文件為空，請檢查對話文件內容"
          : "訊息列表格式錯誤";
      mpu_typewriter(errorMsg, "#ukagaka_msg");
      mpu_showmsg(400);
      mpuLogger.warnF("nextMessageFallbackCannotDisplayDialog", "mpu_nextmsg_fallback: フォールバック会話を表示できません - store=%1$s、msgArray=%2$s", store ? "exists" : "null", store && Array.isArray(store.msg) ? `length=${store.msg.length}` : "not array");
      return;
    }

    const $msgnum = jQuery("#ukagaka_msgnum");
    let msgNum = parseInt($msgnum.html(), 10) || 0;
    msgNum = mpu_selectNextMessage(store, msgNum);

    const auto = store.auto_msg || "";
    const out = store.msg[msgNum] ? store.msg[msgNum] + auto : "";
    mpu_showMsgText();
    mpu_typewriter(mpu_unescapeHTML(out), "#ukagaka_msg");

    // 觸發角色動畫
    if (
      typeof window.mpuCanvasManager !== "undefined" &&
      window.mpuCanvasManager.isFrierenMode
    ) {
      window.mpuCanvasManager.triggerCharacterAnimation();
    }

    $msgnum.html(msgNum);
    mpu_showmsg(400);

    // 將 fallback 對話加入歷史，確保互動對話模式有完整脈絡
    if (out && typeof window.mpuChatHistory !== "undefined" && Array.isArray(window.mpuChatHistory)) {
      window.mpuChatHistory.push({ role: "user", content: "（独り言）", type: "synthetic", timestamp: Date.now() });
      window.mpuChatHistory.push({ role: "assistant", content: mpu_unescapeHTML(out), type: "auto_talk", timestamp: Date.now() });
      if (typeof mpu_saveChatHistory === "function") mpu_saveChatHistory();
    }
  }, 400);
}

function mpuChange(num) {
  const hasNum = typeof num !== "undefined" && num !== null && num !== "";

  if (hasNum && typeof window.mpuCanvasManager === "undefined") {
    mpu_handle_error("Canvas 管理器未載入", "mpuChange:canvas_manager_check", {
      showToUser: true,
      userMessage: (window.mpuL10n && window.mpuL10n.animationLoadFailed) || "アニメーションモジュールの読み込みに失敗しました。ページを更新してください。",
    });
    return;
  }

  const formData = new FormData();
  if (hasNum) {
    formData.append("mpu_num", num);
  }
  const url = `${mpuRestUrl}change`;

  document.body.style.cursor = "wait";

  // 記錄自動對話狀態，以便在窗口關閉後恢復
  const wasAutoTalkRunning = mpuAutoTalkTimer !== null;

  if (!hasNum) {
    // 顯示切換窗口時，停止自動對話計時器
    stopAutoTalk();
  }

  if (!jQuery("#ukagaka_msgbox").is(":hidden")) mpu_hidemsg(200);

  mpuFetch(url, {
    method: "POST",
    body: formData,
    cancelPrevious: true,
    requestId: `mpu_change_${hasNum ? num : "menu"}`,
    timeout: 15000,
    retries: 1,
  })
    .then((res) => {
      if (!hasNum) {
        if (!res || typeof res !== "object")
          throw new Error("Invalid change-list response.");
        const $msg = jQuery("#ukagaka_msg").empty();
        if (res.items && res.items.length > 0) {
          const $wrap = jQuery("<div>").addClass("ukagaka-list");
          $wrap.append(document.createTextNode((res.heading || "") + "："));
          $wrap.append(jQuery("<br>"));
          res.items.forEach(function (item) {
            const $row = jQuery("<div>").css({ padding: "3px 0", paddingLeft: "10px" });
            const $link = jQuery("<a>")
              .text(item.name)
              .css("cursor", "pointer")
              .on("click", function () { mpuChange(item.key); });
            $row.append($link);
            $wrap.append($row);
          });
          $msg.append($wrap);
        } else {
          $msg.text(res.empty_message || "");
        }
        mpu_showmsg(300);
        jQuery("#ukagaka").stop(true, true).fadeIn(200);
        document.body.style.cursor = "auto";
        return;
      }

      if (typeof res !== "object") throw new Error("Expected JSON, got HTML.");
      const payload = res || {};
      const $canvas = jQuery("#cur_ukagaka");
      const $wrap = jQuery("#ukagaka");

      if (
        payload.shell_info &&
        typeof window.mpuCanvasManager !== "undefined"
      ) {
        const $imgWrapper = jQuery("#ukagaka_img");
        $imgWrapper.fadeOut(120, function () {
          window.mpuCanvasManager.init(
            payload.shell_info,
            payload.name || "",
            payload.num || null,
          );
          $imgWrapper.fadeIn(180);
        });
      } else if (payload.shell) {
        if (typeof window.mpuCanvasManager !== "undefined") {
          const $imgWrapper = jQuery("#ukagaka_img");
          $imgWrapper.fadeOut(120, function () {
            window.mpuCanvasManager.init(
              {
                type: "single",
                url: payload.shell,
                images: [],
              },
              payload.name || "",
              payload.num || null,
            );
            $imgWrapper.fadeIn(180);
          });
        } else {
          mpuLogger.warnL("changeCanvasManagerMissingAfterAjax", "mpuChange: Ajax 成功後に Canvas マネージャーが存在しないことが判明しました。これは想定外です");
          mpu_handle_error(
            "Canvas 管理器未載入",
            "mpuChange:canvas_manager_fallback",
            {
              showToUser: true,
              userMessage: (window.mpuL10n && window.mpuL10n.animationLoadFailed) || "アニメーションモジュールの読み込みに失敗しました。ページを更新してください。",
            },
          );
        }
      }

      if (payload.num) jQuery("#ukagaka_num").html(payload.num);
      if (payload.msg)
        mpu_typewriter(mpu_unescapeHTML(payload.msg), "#ukagaka_msg");
      if (payload.name && $canvas.length) {
        $canvas.attr({ "data-alt": payload.name, title: payload.name });
      }

      const msgListElem = document.getElementById("ukagaka_msglist");
      const useExternalDialog =
        payload.dialog_filename &&
        msgListElem &&
        msgListElem.getAttribute("data-load-external") === "true";

      if (useExternalDialog) {
        const currentFile = msgListElem.getAttribute("data-file") || "";
        const ext = currentFile.split(".").pop() || "json";
        const pure = `${payload.dialog_filename}.${ext}`;

        msgListElem.setAttribute("data-file", `dialogs/${pure}`);
        loadExternalDialog(pure);
      } else if (payload.msglist) {
        try {
          mpuSetDialogStore(
            typeof payload.msglist === "string"
              ? JSON.parse(payload.msglist)
              : payload.msglist
          );
        } catch (e) {
          mpu_handle_error(e, "mpuChange:parse_msglist");
          mpuSetDialogStore(null);
        }
      }

      $wrap.stop(true, true).fadeIn(200);
      mpu_showmsg(300);

      // 如果是從選單切換（有帶參數），則立即隱藏訊息框（不使用動畫）
      if (hasNum) {
        mpu_hidemsg(0);
      }

      // 恢復自動對話計時器（如果原本是開啟的）
      if (wasAutoTalkRunning && mpuAutoTalk && !useExternalDialog) {
        startAutoTalk();
      }
      document.body.style.cursor = "auto";
    })
    .catch((error) => {
      mpu_handle_error(error, "mpuChange", {
        showToUser: true,
        userMessage:
          mpuIsDebugMode()
            ? `読み込みに失敗しました: ${error.message}`
            : ((window.mpuL10n && window.mpuL10n.loadingFailed) || "読み込みに失敗しました。後でもう一度お試しください。"),
      });
      jQuery("#ukagaka").stop(true, true).fadeIn(200);
      mpu_showmsg(200);
      document.body.style.cursor = "auto";
    });
}

jQuery(function () {
  mpuInitObservationTracking();
});

// ========== ukagaka-anime.js ==========
/**
 * MP Ukagaka Canvas 動畫管理器
 * 
 * 負責管理春菜圖片的 Canvas 繪製和動畫播放
 * 支援單張圖片和多張圖片動畫
 */

(function() {
    'use strict';

    /**
     * Canvas 動畫管理器
     */
    const mpuCanvasManager = {
        // 內部狀態
        canvas: null,
        ctx: null,
        isAnimated: false,
        images: [], // Image 對象陣列
        imageUrls: [], // 圖片 URL 陣列
        currentFrame: 0,
        animationTimer: null,
        frameInterval: 150, // 動畫幀間隔（毫秒）
        imagesLoaded: false, // 圖片是否已全部載入
        pendingAnimation: false, // 是否有待執行的動畫
        currentCharacterNum: null, // 當前角色 num
        currentCharacterName: null, // 當前角色 name

        /**
         * 初始化 Canvas
         * @param {Object} shellInfo - Shell 資訊對象 {type: 'single'|'folder', url: string, images: string[]}
         * @param {string} name - 春菜名稱
         * @param {string} num - 春菜編號（可選，用於角色識別）
         */
        init: function(shellInfo, name, num) {
            // 🔧 早期檢查：如果已經是芙莉蓮模式且角色沒變，直接跳過（必須在狀態重置之前）
            if (this.isFrieren(num, name) && 
                window.mpuFrierenManager && 
                window.mpuFrierenManager.isFrierenMode) {
                if (typeof mpuLogger !== 'undefined' && mpuLogger.log) {
                    mpuLogger.logL("animeSkipFrierenReinit", "⏭️ すでにフリーレンモードのため、再初期化をスキップします");
                }
                return;
            }
            
            // 清除之前的動畫
            this.stopAnimation();
            
            // 停止芙莉蓮動畫（如果存在）
            if (window.mpuFrierenManager) {
                window.mpuFrierenManager.stopFrierenAnimation();
            }
            
            // 重置狀態
            this.imagesLoaded = false;
            this.pendingAnimation = false;
            
            // 保存當前角色資訊
            this.currentCharacterName = name || null;
            this.currentCharacterNum = num || null;

            // 獲取 Canvas 元素
            this.canvas = document.getElementById('cur_ukagaka');
            if (!this.canvas) {
                mpuLogger.errorL('animeCanvasElementMissing', 'Canvas 要素が存在しません');
                return;
            }

            // 獲取 Canvas 上下文
            this.ctx = this.canvas.getContext('2d');
            if (!this.ctx) {
                mpuLogger.errorL('animeCanvasContextUnavailable', 'Canvas コンテキストを取得できません');
                return;
            }

            // 設置 title 和 alt
            if (name) {
                this.canvas.setAttribute('title', name);
                this.canvas.setAttribute('data-alt', name);
            }

            // 檢查是否為芙莉蓮
            if (this.isFrieren(num, name)) {
                // 使用芙莉蓮管理器處理
                if (window.mpuFrierenManager) {
                    // 芙莉蓮模式 - 首次初始化（重複初始化已在函數開頭被阻擋）
                    window.mpuFrierenManager.initFrierenMode(shellInfo, name);
                } else {
                    mpuLogger.warnAlways('animeFrierenManagerMissing', 'フリーレンマネージャーが読み込まれていないため、汎用モードを使用します');
                    // 降級為通用模式
                    this.initGenericMode(shellInfo);
                }
            } else {
                // 通用模式 - 清理芙莉蓮元素
                if (window.mpuFrierenManager && window.mpuFrierenManager.isFrierenMode) {
                    window.mpuFrierenManager.cleanupFrierenElements();
                }
                
                this.initGenericMode(shellInfo);
            }
        },
        
        /**
         * 初始化通用模式（非芙莉蓮角色）
         * @param {Object} shellInfo - Shell 資訊對象
         */
        initGenericMode: function(shellInfo) {
                // 根據 shellInfo 類型處理
                if (shellInfo && shellInfo.type === 'folder' && shellInfo.images && shellInfo.images.length > 0) {
                    // 多張圖片模式
                    this.isAnimated = true;
                    this.imageUrls = shellInfo.images.map(function(filename) {
                        return shellInfo.url + filename;
                    });
                    this.loadImages();
                } else {
                    // 單張圖片模式
                    this.isAnimated = false;
                    this.imagesLoaded = true; // 單張圖片視為已載入
                    const imageUrl = (shellInfo && shellInfo.url) ? shellInfo.url : '';
                    this.loadSingleImage(imageUrl);
            }
        },

        /**
         * 載入單張圖片
         * @param {string} imageUrl - 圖片 URL
         */
        loadSingleImage: function(imageUrl) {
            if (!imageUrl) {
                return;
            }

            const img = new Image();
            img.crossOrigin = 'anonymous';
            
            img.onload = (function() {
                // 設置 Canvas 尺寸
                this.canvas.width = img.width;
                this.canvas.height = img.height;
                
                // 繪製圖片
                this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
                this.ctx.drawImage(img, 0, 0);
                
                // 設置 #ukagaka_img 的 visibility 為 visible（CSS 中初始為 hidden）
                const imgContainer = document.getElementById('ukagaka_img');
                if (imgContainer) {
                    imgContainer.style.visibility = 'visible';
                }
                
                // 顯示對話視窗（初始為 hidden，避免定位錯誤）
                const msgbox = document.getElementById('ukagaka_msgbox');
                if (msgbox) {
                    msgbox.style.visibility = 'visible';
                }
            }).bind(this);

            img.onerror = function() {
                mpuLogger.errorF('animeImageLoadFailed', '画像の読み込みに失敗しました：%s', imageUrl);
            };

            img.src = imageUrl;
        },

        /**
         * 載入多張圖片
         */
        loadImages: function() {
            if (!this.imageUrls || this.imageUrls.length === 0) {
                return;
            }

            this.images = [];
            let loadedCount = 0;
            const totalImages = this.imageUrls.length;
            this.imagesLoaded = false; // 標記圖片是否已全部載入

            // 載入所有圖片
            for (let i = 0; i < this.imageUrls.length; i++) {
                const img = new Image();
                img.crossOrigin = 'anonymous';
                
                img.onload = (function(index) {
                    loadedCount++;
                    
                    // 第一張圖片載入完成時，設置 Canvas 尺寸
                    if (loadedCount === 1) {
                        this.canvas.width = img.width;
                        this.canvas.height = img.height;
                    }
                    
                    // 所有圖片載入完成時，繪製第一幀
                    if (loadedCount === totalImages) {
                        this.imagesLoaded = true;
                        // 設置 #ukagaka_img 的 visibility 為 visible（CSS 中初始為 hidden）
                        const imgContainer = document.getElementById('ukagaka_img');
                        if (imgContainer) {
                            imgContainer.style.visibility = 'visible';
                        }
                        
                        // 顯示對話視窗（初始為 hidden，避免定位錯誤）
                        const msgbox = document.getElementById('ukagaka_msgbox');
                        if (msgbox) {
                            msgbox.style.visibility = 'visible';
                        }
                        this.currentFrame = 0;
                        this.drawFrame(0);
                        
                        // 如果有待執行的動畫，現在執行
                        if (this.pendingAnimation) {
                            // 延遲一小段時間確保繪製完成
                            setTimeout((function() {
                                this.playAnimation();
                            }).bind(this), 50);
                        }
                    }
                }).bind(this);

                img.onerror = (function(url) {
                    mpuLogger.errorF('animeFrameImageLoadFailed', 'フレーム画像の読み込みに失敗しました：%s', url);
                    loadedCount++;
                    
                    // 即使有圖片載入失敗，也要檢查是否所有圖片都已處理
                    if (loadedCount === totalImages) {
                        this.imagesLoaded = true;
                        if (this.images.length > 0) {
                            this.currentFrame = 0;
                            this.drawFrame(0);
                            
                            // 如果有待執行的動畫，現在執行
                            if (this.pendingAnimation) {
                                // 延遲一小段時間確保繪製完成
                                setTimeout((function() {
                                    this.playAnimation();
                                }).bind(this), 50);
                            }
                        }
                    }
                }).bind(this);

                img.src = this.imageUrls[i];
                this.images.push(img);
            }
        },

        /**
         * 繪製指定幀
         * @param {number} frameIndex - 幀索引
         */
        drawFrame: function(frameIndex) {
            if (!this.ctx || !this.images || this.images.length === 0) {
                return;
            }

            // 確保索引在有效範圍內
            if (frameIndex < 0 || frameIndex >= this.images.length) {
                frameIndex = 0;
            }

            const img = this.images[frameIndex];
            if (!img || !img.complete || img.naturalWidth === 0) {
                // 圖片尚未載入完成，跳過
                return;
            }

            // 清除畫布
            this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
            
            // 繪製當前幀
            this.ctx.drawImage(img, 0, 0);
            this.currentFrame = frameIndex;
        },

        /**
         * 播放動畫
         */
        playAnimation: function() {
            // 如果是角色動畫模式，委派給角色管理器處理
            if (this.isCharacterMode) {
                this.triggerCharacterAnimation();
                return;
            }
            
            // 如果不是動畫模式，不執行
            if (!this.isAnimated) {
                return;
            }

            // 如果圖片尚未載入完成，標記為待執行
            if (!this.imagesLoaded || !this.images || this.images.length === 0) {
                this.pendingAnimation = true;
                return;
            }

            // 標記動畫已執行
            this.pendingAnimation = false;

            // 停止之前的動畫
            this.stopAnimation();

            // 重置到第一幀
            this.currentFrame = 0;
            this.drawFrame(0);

            // 如果只有一張圖片，不需要動畫
            if (this.images.length <= 1) {
                return;
            }

            // 播放動畫
            let frameIndex = 1; // 從第二幀開始（第一幀已經繪製）

            this.animationTimer = setInterval((function() {
                if (frameIndex >= this.images.length) {
                    // 動畫完成，停留在最後一幀
                    this.stopAnimation();
                    return;
                }

                this.drawFrame(frameIndex);
                frameIndex++;
            }).bind(this), this.frameInterval);
        },

        /**
         * 停止動畫
         */
        stopAnimation: function() {
            if (this.animationTimer) {
                clearInterval(this.animationTimer);
                this.animationTimer = null;
            }
            // 同時停止芙莉蓮動畫（如果存在）
            if (window.mpuFrierenManager) {
                window.mpuFrierenManager.stopFrierenAnimation();
            }
        },

        /**
         * 檢查是否為動畫模式
         * @returns {boolean}
         */
        isAnimationMode: function() {
            return this.isAnimated;
        },
        
        /**
         * 檢查當前角色是否為芙莉蓮
         * @param {string} num - 角色編號（可選）
         * @param {string} name - 角色名稱（可選）
         * @returns {boolean}
         */
        isFrieren: function(num, name) {
            // 使用傳入的參數，如果沒有則使用保存的值
            const checkNum = num !== undefined ? num : this.currentCharacterNum;
            const checkName = name !== undefined ? name : this.currentCharacterName;
            
            // 檢查 num 是否為 'default_1'（預設芙莉蓮）
            if (checkNum === 'default_1') {
                return true;
            }
            
            // 檢查 name 是否包含 'フリーレン' 或 'Frieren'
            if (checkName && (
                checkName.indexOf('フリーレン') !== -1 || 
                checkName.indexOf('Frieren') !== -1 ||
                checkName.indexOf('frieren') !== -1
            )) {
                return true;
            }
            
            return false;
        },
        
        /**
         * 觸發角色動畫（委派給角色管理器處理）
         * @param {boolean} forceAnimation - 可選，使用者主動觸發時設為 true，會忽略睡眠模式
         * @param {Function} onWakeUpComplete - 可選，當喚醒動畫完成後的回調函數
         * @param {boolean} skipBookFlip - 可選，是否跳過翻書動畫
         * @returns {boolean} 是否正在播放喚醒動畫
         */
        triggerCharacterAnimation: function(forceAnimation, onWakeUpComplete, skipBookFlip) {
            // 如果是角色動畫模式，委派給角色管理器處理
            if (window.mpuFrierenManager && window.mpuFrierenManager.isFrierenMode) {
                return window.mpuFrierenManager.triggerFrierenSpeaking(forceAnimation, onWakeUpComplete, skipBookFlip);
            }
            return false;
        },
        
        /**
         * 向後兼容：保留舊的函數名稱
         * @deprecated 請使用 triggerCharacterAnimation
         */
        triggerFrierenSpeaking: function(forceAnimation, onWakeUpComplete, skipBookFlip) {
            return this.triggerCharacterAnimation(forceAnimation, onWakeUpComplete, skipBookFlip);
        },
        
        /**
         * 檢查是否為角色動畫模式
         * @returns {boolean}
         */
        get isCharacterMode() {
            return window.mpuFrierenManager && window.mpuFrierenManager.isFrierenMode;
        },
        
        /**
         * 向後兼容：保留舊的屬性名稱
         * @deprecated 請使用 isCharacterMode
         */
        get isFrierenMode() {
            return this.isCharacterMode;
        },

        /**
         * 檢查當前角色是否有喚醒動畫
         * 這是一個通用方法，支援各種角色管理器
         * @returns {boolean} 是否有喚醒動畫
         */
        hasWakeUpAnimation: function() {
            // 如果是角色動畫模式，檢查角色管理器是否有喚醒動畫
            if (window.mpuFrierenManager && window.mpuFrierenManager.isFrierenMode) {
                // 芙莉蓮管理器：檢查 frierenWakeUpImages
                if (window.mpuFrierenManager.frierenWakeUpImages && 
                    window.mpuFrierenManager.frierenWakeUpImages.length > 0) {
                    return true;
                }
                // 如果角色管理器有 hasWakeUpAnimation 方法，使用它（其他人格可以實現）
                if (typeof window.mpuFrierenManager.hasWakeUpAnimation === 'function') {
                    return window.mpuFrierenManager.hasWakeUpAnimation();
                }
            }
            return false;
        }
    };

    // 將管理器暴露到全域
    window.mpuCanvasManager = mpuCanvasManager;

})();

// ========== ukagaka-emoji.js ==========
(function() {
    'use strict';

    /**
     * 表情管理器：固定在芙莉蓮頭部右側顯示表情圖案
     * 
     * 根據 AI 對話內容的情緒自動選擇對應的表情（APNG），
     * 固定在芙莉蓮頭部右側顯示。APNG 動畫播放完成後自動消失。
     */
    const mpuEmojiManager = {
        // 當前顯示的表情元素
        currentEmoji: null,

        // 表情顯示持續時間（毫秒），APNG 動畫完成後自動移除
        displayDuration: 3000, // 3 秒

        /**
         * 顯示表情
         * @param {string} emojiName - 表情文件名（如 'happy.png'）
         */
        showEmoji: function(emojiName) {
            if (!emojiName || typeof emojiName !== 'string') {
                return;
            }

            // 如果已經有表情在顯示，先移除
            if (this.currentEmoji) {
                this.hideEmoji(this.currentEmoji);
            }

            // 獲取表情基礎路徑
            const baseUrl = (typeof mpuEmojiConfig !== 'undefined' && mpuEmojiConfig.baseUrl)
                ? mpuEmojiConfig.baseUrl
                : '';

            if (!baseUrl) {
                if (typeof mpuLogger !== 'undefined' && mpuLogger.log) {
                    mpuLogger.logL("emojiBasePathMissing", "mpuEmojiManager: 表情のベースパスが設定されていません");
                }
                return;
            }

            // 構建完整路徑
            const emojiUrl = baseUrl + emojiName;

            // 獲取容器
            const imgContainer = document.getElementById('ukagaka_img');
            if (!imgContainer) {
                if (typeof mpuLogger !== 'undefined' && mpuLogger.log) {
                    mpuLogger.logL("emojiContainerMissing", "mpuEmojiManager: #ukagaka_img コンテナが見つかりません");
                }
                return;
            }

            // 創建表情元素
            const emojiImg = document.createElement('img');
            emojiImg.className = 'frieren-emoji';
            emojiImg.src = emojiUrl;
            emojiImg.alt = 'emoji';
            emojiImg.style.display = 'block';

            // 儲存表情 key（用於讀取位置/縮放配置）
            emojiImg.dataset.emojiKey = emojiName.replace(/\.[^.]+$/, '');

            // 應用縮放配置
            this.applyEmojiScale(emojiImg);

            // 計算位置
            this.updateEmojiPosition(emojiImg);

            // 添加到容器
            imgContainer.appendChild(emojiImg);
            this.currentEmoji = emojiImg;

            // 監聽圖片載入完成
            emojiImg.onload = () => {
                // 重新計算位置（確保圖片尺寸正確）
                this.updateEmojiPosition(emojiImg);
            };

            // 監聽錯誤
            emojiImg.onerror = () => {
                if (typeof mpuLogger !== 'undefined' && mpuLogger.warn) {
                    mpuLogger.warnF("emojiImageLoadFailed", "mpuEmojiManager: 表情画像の読み込みに失敗しました：%s", emojiUrl);
                }
                this.hideEmoji(emojiImg);
            };

            // 設定自動移除（APNG 動畫完成後）
            // 注意：APNG 動畫結束事件可能不可靠，使用 setTimeout 作為後備
            const self = this;
            setTimeout(() => {
                if (self.currentEmoji === emojiImg) {
                    self.hideEmoji(emojiImg);
                }
            }, this.displayDuration);

            if (typeof mpuLogger !== 'undefined' && mpuLogger.log) {
                mpuLogger.logF("emojiShown", "mpuEmojiManager: 表情を表示します：%s", emojiName);
            }
        },

        /**
         * 更新表情位置（計算芙莉蓮頭部右側位置）
         * @param {HTMLElement} emojiElement - 表情元素
         */
        updateEmojiPosition: function(emojiElement) {
            const imgContainer = document.getElementById('ukagaka_img');
            if (!imgContainer || !emojiElement) {
                return;
            }

            // 獲取芙莉蓮圖片元素（優先使用可見的元素）
            // 確保只獲取芙莉蓮圖片，不要獲取到裝飾品
            let frierenImg = null;
            
            // 優先檢查 Canvas（翻書動畫時會顯示）
            const canvas = imgContainer.querySelector('canvas');
            if (canvas && canvas.style.display !== 'none') {
                frierenImg = canvas;
            } else {
                // 如果 Canvas 不可見，檢查 APNG
                const apngImg = document.getElementById('frieren_idle_apng');
                if (apngImg && apngImg.style.display !== 'none') {
                    frierenImg = apngImg;
                } else {
                    // 最後嘗試找 #cur_ukagaka，但要確保不是裝飾品
                    const curUkagaka = document.querySelector('#cur_ukagaka');
                    if (curUkagaka && !curUkagaka.classList.contains('frieren-decoration')) {
                        frierenImg = curUkagaka;
                    }
                }
            }
            
            if (!frierenImg) {
                // 如果找不到圖片，使用容器的默認位置
                const scale = emojiElement.dataset.emojiScale || 1;
                emojiElement.style.left = '100%';
                emojiElement.style.top = '20%';
                emojiElement.style.transform = `translateY(-50%) scale(${scale})`;
                return;
            }

            // 獲取容器和圖片的邊界矩形
            const containerRect = imgContainer.getBoundingClientRect();
            const imgRect = frierenImg.getBoundingClientRect();

            // 獲取當前表情的位置配置（從 JSON 讀取，若無則使用預設值）
            const emojiKey = emojiElement.dataset.emojiKey;
            let position = { offsetX: -105, offsetY: -88, headRatio: 0.25 };
            
            if (typeof mpuEmojiConfig !== 'undefined' && 
                mpuEmojiConfig.mappings && 
                mpuEmojiConfig.mappings[emojiKey] && 
                mpuEmojiConfig.mappings[emojiKey].position) {
                const cfg = mpuEmojiConfig.mappings[emojiKey].position;
                position = {
                    offsetX: cfg.offsetX ?? position.offsetX,
                    offsetY: cfg.offsetY ?? position.offsetY,
                    headRatio: cfg.headRatio ?? position.headRatio
                };
            }

            // 計算表情符號位置（相對於容器）
            // 頭部位置：圖片頂部 + headRatio% 高度 + offsetY
            const headY = (imgRect.top - containerRect.top) + (imgRect.height * position.headRatio) + position.offsetY;
            // 右側位置：圖片右邊緣 + offsetX
            const offsetX = (imgRect.left - containerRect.left) + imgRect.width + position.offsetX;

            // 設置位置（相對於容器），保留縮放設定
            const scale = emojiElement.dataset.emojiScale || 1;
            emojiElement.style.left = offsetX + 'px';
            emojiElement.style.top = headY + 'px';
            emojiElement.style.transform = `translateY(-50%) scale(${scale})`;
        },

        /**
         * 應用表情縮放配置
         * @param {HTMLElement} emojiElement - 表情元素
         */
        applyEmojiScale: function(emojiElement) {
            if (!emojiElement) {
                return;
            }

            // 獲取當前表情的縮放配置（從 JSON 讀取，若無則使用預設值 1.0）
            const emojiKey = emojiElement.dataset.emojiKey;
            let scale = 1.0;
            
            if (typeof mpuEmojiConfig !== 'undefined' && 
                mpuEmojiConfig.mappings && 
                mpuEmojiConfig.mappings[emojiKey] && 
                typeof mpuEmojiConfig.mappings[emojiKey].scale === 'number') {
                scale = mpuEmojiConfig.mappings[emojiKey].scale;
            }

            // 儲存縮放值到 dataset，供 updateEmojiPosition 使用
            emojiElement.dataset.emojiScale = scale;
        },

        /**
         * 隱藏表情
         * @param {HTMLElement} emojiElement - 表情元素（可選，如果不提供則移除當前表情）
         */
        hideEmoji: function(emojiElement) {
            const elementToRemove = emojiElement || this.currentEmoji;
            
            if (elementToRemove && elementToRemove.parentNode) {
                elementToRemove.parentNode.removeChild(elementToRemove);
                
                if (this.currentEmoji === elementToRemove) {
                    this.currentEmoji = null;
                }

                if (typeof mpuLogger !== 'undefined' && mpuLogger.log) {
                    mpuLogger.logL("emojiRemoved", "mpuEmojiManager: 表情を削除します");
                }
            }
        },

        /**
         * 更新所有表情的位置（響應視窗大小變化）
         */
        updatePosition: function() {
            if (this.currentEmoji) {
                this.updateEmojiPosition(this.currentEmoji);
            }
        },

        /**
         * 清理所有表情元素
         */
        cleanup: function() {
            if (this.currentEmoji) {
                this.hideEmoji(this.currentEmoji);
            }
        }
    };

    // 監聽視窗大小變化，更新表情位置
    let resizeTimer = null;
    window.addEventListener('resize', () => {
        if (resizeTimer) {
            clearTimeout(resizeTimer);
        }
        resizeTimer = setTimeout(() => {
            mpuEmojiManager.updatePosition();
        }, 100);
    });

    // 將管理器暴露到全域
    window.mpuEmojiManager = mpuEmojiManager;

    /**
     * 載入表情配置（延遲載入，避免在網頁原始碼中暴露路徑）
     */
    function loadEmojiConfig() {
        // 如果已經載入過，直接返回
        if (typeof window.mpuEmojiConfig !== 'undefined' && window.mpuEmojiConfig.baseUrl) {
            return Promise.resolve(window.mpuEmojiConfig);
        }

        return new Promise((resolve, reject) => {
            if (typeof mpuRestUrl === 'undefined') {
                reject(new Error('mpuRestUrl is not defined'));
                return;
            }

            const url = `${mpuRestUrl}emoji-config`;

            const headers = {
                'Content-Type': 'application/json',
            };
            if (typeof mpuRestNonce !== 'undefined') {
                headers['X-WP-Nonce'] = mpuRestNonce;
            }

            fetch(url, {
                method: 'GET',
                headers: headers,
            })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        window.mpuEmojiConfig = {
                            baseUrl: data.baseUrl || '',
                            supportedEmojis: data.supportedEmojis || [],
                            mappings: data.mappings || {},
                        };
                        resolve(window.mpuEmojiConfig);
                    } else {
                        reject(new Error(data.error || 'Failed to load emoji config'));
                    }
                })
                .catch(error => {
                    reject(error);
                });
        });
    }

    // 在頁面載入完成後自動載入配置
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            loadEmojiConfig().catch(error => {
                if (typeof mpuLogger !== 'undefined' && mpuLogger.warn) {
                    mpuLogger.warn('Failed to load emoji config:', error);
                }
            });
        });
    } else {
        // DOM 已經載入完成，立即載入
        loadEmojiConfig().catch(error => {
            if (typeof mpuLogger !== 'undefined' && mpuLogger.warn) {
                mpuLogger.warn('Failed to load emoji config:', error);
            }
        });
    }

    // 暴露載入函數供外部調用
    window.loadEmojiConfig = loadEmojiConfig;

})();


// ========== ukagaka-context.js ==========
// ====== AI 上下文對話 ======
/**
 * 檢查頁面是否應該觸發 AI 對話
 * @param {string} triggerPages - 觸發條件字串，以逗號分隔（例如："is_single,is_page"）
 * @returns {boolean} 是否符合觸發條件
 */
function mpu_check_page_trigger(triggerPages) {
  if (!triggerPages) {
    mpuLogger.logL("contextTriggerPagesEmpty", "mpu_check_page_trigger: triggerPages が空のため false を返します");
    return false;
  }

  const conditions = triggerPages.split(",").map((s) => s.trim().toLowerCase());
  const path = window.location.pathname;
  const url = window.location.href;

  mpuLogger.logF("contextTriggerConditionsCheck", "mpu_check_page_trigger: チェック条件 = %s、path = %s", conditions, path);

  // 檢查各種 WordPress 條件
  for (let condition of conditions) {
    condition = condition.trim();
    if (!condition) continue;

    // is_single: 單篇文章頁面
    if (condition === "is_single") {
      // 排除首頁和歸檔頁面（優先檢查）
      // 只排除明確的首頁路徑，不排除可能是單篇文章的路徑
      const isRootPath = path === "/" || path === "";
      const normalizedPath = path.endsWith("/") ? path.slice(0, -1) : path;
      const pathParts = normalizedPath.split("/").filter((p) => p.length > 0);
      const firstPathPart = pathParts[0] || "";
      const isUrlEncoded = firstPathPart.includes("%");
      const hasHyphen = firstPathPart.includes("-");

      // 只有當路徑段只有一個，且不包含 URL 編碼，且不包含連字符，且不是日期格式或特殊頁面時，才視為首頁
      const isSubdirectoryHome =
        pathParts.length === 1 &&
        !isUrlEncoded &&
        !hasHyphen &&
        !path.match(/\/\d{4}\/\d{2}\/\d{2}\//) &&
        !path.match(/^\/(category|tag|author|search|archive|feed)/);

      const isHomePage = isRootPath || isSubdirectoryHome;
      const isSpecialPage = path.match(
        /^\/(category|tag|author|page|search|archive|feed|$)/,
      );
      const isPagination = path.match(/\/page\/\d+/);

      if (isHomePage || isSpecialPage || isPagination) {
        mpuLogger.logL("contextTriggerIsSingleExcludingHomeArchive", "mpu_check_page_trigger: is_single チェック - ホームページ/アーカイブページを除外します", {
            path,
            isHomePage,
            isSubdirectoryHome,
            isSpecialPage,
            isPagination,
            shouldTrigger: false,
          });
        continue; // 跳過此條件，檢查下一個
      }

      // 檢查是否為單篇文章：
      // 1. URL 有日期格式 /YYYY/MM/DD/
      // 2. 或者 DOM 中有實際的文章內容（entry-title + entry-content）
      // 3. 或者 DOM 中有 article、main、.entry-content 等容器元素（適用於 single page）
      const hasDatePath = path.match(/\/\d{4}\/\d{2}\/\d{2}\//);
      const hasArticleContent =
        document.querySelector(
          "article .entry-title, article .entry-content",
        ) ||
        document.querySelector(".hentry .entry-title") ||
        document.querySelector(".single .entry-title");
      // 放寬條件：只要有 article、main 或 .entry-content 容器
      const hasContentContainer =
        document.querySelector("article") ||
        document.querySelector("main") ||
        document.querySelector(".entry-content");

      const shouldTrigger =
        hasDatePath || hasArticleContent || hasContentContainer;

      mpuLogger.logL("contextTriggerIsSingleCheck", "mpu_check_page_trigger: is_single チェック", {
        path,
        hasDatePath,
        hasArticleContent,
        hasContentContainer,
        shouldTrigger,
      });

      if (shouldTrigger) {
        return true;
      }
    }
    // is_page: 頁面
    else if (condition === "is_page") {
      // 簡單檢查：頁面通常沒有日期格式，且不是分類/標籤等
      const isNotSpecial =
        path.length > 1 &&
        !path.match(/^\/(category|tag|author|search|archive|feed)/) &&
        !path.match(/\/\d{4}\/\d{2}\/\d{2}\//);

      mpuLogger.logL("contextTriggerIsPageCheck", "mpu_check_page_trigger: is_page チェック", {
        path,
        pathLength: path.length,
        isNotSpecial,
        shouldTrigger: isNotSpecial,
      });

      if (isNotSpecial) {
        return true;
      }
    }
    // is_home: 首頁
    else if (condition === "is_home" || condition === "is_front_page") {
      const isRootPath = path === "/" || path === "";
      const normalizedPath = path.endsWith("/") ? path.slice(0, -1) : path;
      const pathParts = normalizedPath.split("/").filter((p) => p.length > 0);
      const isSubdirectoryHome =
        pathParts.length === 1 &&
        !path.match(/\/\d{4}\/\d{2}\/\d{2}\//) &&
        !path.match(/^\/(category|tag|author|search|archive|feed)/);

      const isHomePagination = path.match(/^(\/[^\/]+)?\/page\/\d+$/);
      const hasMultipleArticles =
        document.querySelectorAll("article").length > 1;
      const hasSinglePostFeatures =
        document.querySelector("article.single") ||
        document.querySelector(".single article") ||
        path.match(/\/\d{4}\/\d{2}\/\d{2}\//);

      const isHome =
        isRootPath ||
        isSubdirectoryHome ||
        isHomePagination ||
        (hasMultipleArticles && !hasSinglePostFeatures);

      mpuLogger.logL("contextTriggerIsHomeFrontPageCheck", "mpu_check_page_trigger: is_home/is_front_page チェック", {
        path,
        normalizedPath,
        pathParts,
        isRootPath,
        isSubdirectoryHome,
        isHomePagination,
        hasMultipleArticles,
        hasSinglePostFeatures,
        isHome,
      });

      if (isHome) {
        return true;
      }
    }
    // is_archive: 歸檔頁面（包含 category、tag、author、date 等所有歸檔）
    else if (condition === "is_archive") {
      const isArchive = path.match(/^\/(category|tag|author|date)/);

      mpuLogger.logL("contextTriggerIsArchiveCheck", "mpu_check_page_trigger: is_archive チェック", {
        path,
        isArchive,
      });

      if (isArchive) {
        return true;
      }
    }
    // is_category: 分類頁面
    else if (condition === "is_category") {
      const isCategory = path.match(/^\/category\//);

      mpuLogger.logL("contextTriggerIsCategoryCheck", "mpu_check_page_trigger: is_category チェック", {
        path,
        isCategory,
      });

      if (isCategory) {
        return true;
      }
    }
    // is_tag: 標籤頁面
    else if (condition === "is_tag") {
      const isTag = path.match(/^\/tag\//);

      mpuLogger.logL("contextTriggerIsTagCheck", "mpu_check_page_trigger: is_tag チェック", {
        path,
        isTag,
      });

      if (isTag) {
        return true;
      }
    }
  }

  return false;
}

/**
 * 獲取頁面上下文資訊（標題、內容和發布日期）
 * @returns {{title: string, content: string, publishDate: string}} 包含頁面標題、內容和發布日期的物件
 */
function mpu_get_page_context() {
  let title = "";
  const titleElement =
    document.querySelector("article h1.entry-title") ||
    document.querySelector("article h2.entry-title") ||
    document.querySelector("article .entry-title") ||
    document.querySelector(".entry-title") ||
    document.querySelector("article h1:not(.site-title)") ||
    document.querySelector("article h2") ||
    document.querySelector("main h1:not(.site-title)") ||
    document.querySelector("main h2") ||
    document.querySelector("#content h1:not(.site-title)") ||
    document.querySelector("#content h2") ||
    document.querySelector("h1.post-title") ||
    document.querySelector("h2.post-title") ||
    document.querySelector("h1:not(.site-title)");

  if (titleElement && titleElement.textContent) {
    title = titleElement.textContent.trim();
  }

  // Fallback: 如果找不到文章標題元素，使用 document.title
  if (!title) {
    title = document.title;
  }

  let content = "";
  let publishDate = "";

  const article =
    document.querySelector("article") ||
    document.querySelector("main") ||
    document.querySelector(".entry-content") ||
    document.querySelector("#content");

  if (article) {
    // 抓取文字，將多個空白合併為一個，並限制長度
    content = article.innerText.replace(/\s+/g, " ").substring(0, 3000);
  }

  // 嘗試獲取文章發布日期（多種來源）
  // 1. WordPress 標準 <time> 元素（帶 datetime 屬性）
  const timeElement =
    document.querySelector("article time[datetime]") ||
    document.querySelector("time.entry-date[datetime]") ||
    document.querySelector("time.published[datetime]") ||
    document.querySelector("time[datetime]");
  if (timeElement && timeElement.getAttribute("datetime")) {
    publishDate = timeElement.getAttribute("datetime");
  }

  // 2. 如果沒有 datetime，嘗試從 time 元素的文字內容獲取
  if (!publishDate && timeElement && timeElement.textContent) {
    publishDate = timeElement.textContent.trim();
  }

  // 3. 嘗試從 meta 標籤獲取
  if (!publishDate) {
    const metaDate =
      document.querySelector("meta[property='article:published_time']") ||
      document.querySelector("meta[name='pubdate']") ||
      document.querySelector("meta[name='date']");
    if (metaDate && metaDate.content) {
      publishDate = metaDate.content;
    }
  }

  // 4. 嘗試從常見的日期選擇器獲取
  if (!publishDate) {
    const dateElement =
      document.querySelector(".entry-date") ||
      document.querySelector(".post-date") ||
      document.querySelector(".date") ||
      document.querySelector(".posted-on");
    if (dateElement && dateElement.textContent) {
      publishDate = dateElement.textContent.trim();
    }
  }

  return { title, content, publishDate };
}

/**
 * AI 上下文對話：根據當前頁面內容生成 AI 回應
 */
function mpu_chat_context() {
  // 新增：如果正在對話模式中，直接取消頁面感知 AI
  if (typeof mpuChatModeActive !== "undefined" && mpuChatModeActive) {
    mpuLogger.logL("contextChatSkippedChatMode", "mpu_chat_context: 会話モード中のためページ感知 AI をスキップします");
    return;
  }

  // 🔧 如果頁面感知 AI 正在進行中（包含打字），跳過新的觸發
  if (mpuAiContextInProgress) {
    mpuLogger.logL("contextChatSkippedPageAwareInProgress", "mpu_chat_context: ページ感知処理中のため新しいトリガーをスキップします");
    return;
  }

  // 60 秒冷卻：芙莉蓮對某篇文章發表過感想後，60 秒內不再觸發頁面感知（跨 SPA 換頁與
  // 整頁重載皆有效，用 sessionStorage 存時間戳）。避免高機率設定（如本機 40%）下每換
  // 一頁就重新發表感想。顯示時長 mpuAiDisplayDuration 不受影響，此處僅作再觸發冷卻窗。
  const MPU_CONTEXT_COOLDOWN_MS = 60000;
  let mpuContextLastShown = 0;
  try {
    mpuContextLastShown = parseInt(sessionStorage.getItem("mpu_context_last_shown") || "0", 10) || 0;
  } catch (e) {
    mpuContextLastShown = 0;
  }
  if (mpuContextLastShown && Date.now() - mpuContextLastShown < MPU_CONTEXT_COOLDOWN_MS) {
    const remainSec = Math.ceil((MPU_CONTEXT_COOLDOWN_MS - (Date.now() - mpuContextLastShown)) / 1000);
    mpuLogger.log("mpu_chat_context: クールダウン中（残り" + remainSec + "秒）のためスキップします");
    return;
  }

  // 睡眠模式檢查：優先使用伺服器端時間（避免客戶端/伺服器時區差異）
  const isDeepSleep = mpu_isDeepSleepTime();
  if (isDeepSleep) {
    mpuLogger.logL("contextChatSkippedSleepMode", "🌙 睡眠モード（00:00-06:00）：ページ感知 AI をスキップし、キャラクターを休ませます");
    return;
  }

  const context = mpu_get_page_context();
  const contentLength = context.content ? context.content.length : 0;

  mpuLogger.logL("contextChatPageContextCheck", "mpu_chat_context: ページコンテキストチェック", {
    hasTitle: !!context.title,
    title: context.title,
    contentLength,
    hasContent: !!context.content,
  });

  if (!context.title && !context.content) {
    mpuLogger.logL("contextChatSkippedEmptyTitleContent", "mpu_chat_context: タイトルと本文がないためスキップします");
    return;
  }

  // 如果首次訪客打招呼正在進行中，跳過頁面感知 AI
  if (mpuGreetInProgress) {
    mpuLogger.logL("contextChatSkippedFirstVisitorGreeting", "mpu_chat_context: 初回訪問者への挨拶中のためスキップします");
    return;
  }

  if (contentLength < 300) {
    mpuLogger.logF("contextChatSkippedShortContent", "mpu_chat_context: 内容が 300 文字未満です（現在：%s）。スキップします", contentLength);
    return;
  }

  // 立即停止自動對話並設置標誌，防止自發對話在載入訊息顯示時打斷
  const wasAutoTalkRunning = mpuAutoTalkTimer !== null;
  if (wasAutoTalkRunning) {
    stopAutoTalk();
  }

  mpuSetAiContextInProgress(true);

  // 設置阻擋標誌，完全阻止自發對話
  mpuSetMessageBlocking(true);

  const showMainDialog = function () {
    if (jQuery("#ukagaka_msgbox").is(":hidden")) mpu_showmsg(200);
  };

  // 檢測是否為自己的日記（根據標題前綴）
  let loadingMessage;
  const diaryPrefix =
    typeof mpuL10n !== "undefined" && mpuL10n.diaryTitlePrefix
      ? mpuL10n.diaryTitlePrefix
      : "";
  const isOwnDiary =
    diaryPrefix && context.title && context.title.includes(diaryPrefix);

  if (isOwnDiary && typeof mpuL10n !== "undefined" && mpuL10n.loadingOwnDiary) {
    loadingMessage = mpuL10n.loadingOwnDiary;
  } else {
    loadingMessage =
      typeof mpuL10n !== "undefined" && mpuL10n.loadingArticle
        ? mpuL10n.loadingArticle
        : "…ああ、記事か。どれどれ…";
  }

  if (typeof mpuShowSystemPlaceholder === "function") {
    mpuShowSystemPlaceholder({
      context: "context",
      text: loadingMessage,
      showSpinner: false,
    });
    if (typeof mpuMarkSystemPlaceholder === "function") {
      mpuMarkSystemPlaceholder("#ukagaka_msg");
    }
  } else {
    showMainDialog();
    mpu_typewriter(
      `<span style="color: ${mpuAiTextColor};">${loadingMessage}</span>`,
      "#ukagaka_msg",
      null,
    { systemPlaceholder: true }, // §16.3-A：頁面感知 loading placeholder，跳過角色動畫
    );
  }

  const formData = new FormData();
  formData.append("page_title", context.title);
  formData.append("page_content", context.content);
  formData.append("publish_date", context.publishDate || "");

  // [Fix] 傳送 session_id + history，讓後端在頁面感知 AI 成功後也能更新 checksum，
  // 防止下一輪 chat/user 驗證 400（裝飾品/身體點擊場景）。
  const contextSessionId = mpu_getOrCreateChatSessionId();
  if (contextSessionId) {
    formData.append("session_id", contextSessionId);
  }
  if (typeof window.mpuChatHistory !== "undefined" && window.mpuChatHistory.length > 0) {
    formData.append("history", JSON.stringify(window.mpuChatHistory.slice(-10)));
  }

  mpuFetch(mpuRestUrl + "chat/context", {
    method: "POST",
    body: formData,
    cancelPrevious: true,
    requestId: "mpu_chat_context",
    timeout: 60000,
    retries: 1,
  })
    .then((res) => {
      if (res && res.msg && !res.error) {
        let aiResponse = mpu_unescapeHTML(res.msg);
        aiResponse = mpu_linkifyUrls(aiResponse);
        showMainDialog();
        // 記錄感想顯示時間，啟動 60 秒再觸發冷卻（見 mpu_chat_context 開頭的 cooldown 守衛）。
        try {
          sessionStorage.setItem("mpu_context_last_shown", String(Date.now()));
        } catch (e) {}
        mpu_typewriter(
          `<span style="color: ${mpuAiTextColor};">${aiResponse}</span>`,
          "#ukagaka_msg",
        );

        // 觸發角色動畫（頁面感知是使用者觸發，強制播放動畫）
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

        // 記憶功能：將頁面感知對話存入對話歷史
        // 這樣當用戶切換到對話模式時，AI 會記得剛剛說過的話
        if (
          typeof window.mpuChatHistory !== "undefined" &&
          Array.isArray(window.mpuChatHistory)
        ) {
          // synthetic user 錨點：讓 LLM 能在後續對話中看到頁面感知的完整脈絡
          window.mpuChatHistory.push({
            role: "user",
            content: "（ページの内容を感知した）",
            type: "synthetic",
            timestamp: Date.now(),
          });
          window.mpuChatHistory.push({
            role: "assistant",
            content: res.msg,
            type: "context",
            timestamp: Date.now(),
          });

          if (typeof mpu_saveChatHistory === "function") {
            mpu_saveChatHistory();
            mpuLogger.logL("contextChatSavedToHistory", "mpu_chat_context: 会話を履歴に追加して保存しました");
          }
        }

        // 🔧 計時邏輯：打字完成 → displayDuration → autoTalkInterval
        // 這樣用戶可以自由設定 AI 回應的顯示時間
        if (mpuAiDisplayTimer !== null) {
          clearTimeout(mpuAiDisplayTimer);
          mpuSetAiDisplayTimer(null);
        }

        mpu_waitForTypewriterComplete(function () {
          // 打字完成後，開始 displayDuration 計時
          const displayDurationMs = mpuAiDisplayDuration * 1000;
          mpuSetAiDisplayTimer(setTimeout(function () {
            mpuSetAiDisplayTimer(null);
            mpuSetMessageBlocking(false);
            mpuSetAiContextInProgress(false);
            // wasAutoTalkRunning 只記錄頁面感知觸發當下的狀態；startup 被跳過時
            // auto-talk 從未啟動（wasAutoTalkRunning=false），但 mpuAutoTalk 仍為 true，
            // 因此改用 mpuAutoTalk 判斷。不再加 !mpuAutoTalkTimer guard：生產環境 API
            // 延遲會讓此刻殘留 stale timer 參照，guard 會誤判而永不重啟（本機 API 快、
            // 重現不出）。startAutoTalk() 內部已先 stopAutoTalk()，重複呼叫不會疊計時器。
            if (mpuAutoTalk) {
              startAutoTalk();
            }
          }, displayDurationMs));
        });
      } else {
        mpuLogger.warnF("contextChatAiFailedUseDefault", "AI 会話に失敗したため、既定の会話システムを使用します：%s", res);

        // 檢查是否是速率限制錯誤
        const isRateLimit =
          (res && res.error && (res.error.includes("請求過於頻繁") || res.error.includes("リクエストが多すぎます"))) ||
          (res && res.code === "rest_rate_limit_exceeded");

        if (isRateLimit) {
          const rateLimitMessage =
            typeof mpuL10n !== "undefined" && mpuL10n.apiMagicInsufficient
              ? mpuL10n.apiMagicInsufficient
              : "…ちょっと待って。API魔力が足りない";
          showMainDialog();
          mpu_typewriter(
            `<span style="color: ${mpuAiTextColor};">${rateLimitMessage}</span>`,
            "#ukagaka_msg",
          );

          mpuSetMessageBlocking(true);
          const waitTime = (mpuAiDisplayDuration || 8) * 1000;

          setTimeout(function () {
            mpuSetMessageBlocking(false);
            mpuSetAiContextInProgress(false);
            const dialogStore = mpuGetDialogStore();
            if (
              dialogStore &&
              Array.isArray(dialogStore.msg) &&
              dialogStore.msg.length > 0
            ) {
              const msgArr = dialogStore.msg;
              const auto = dialogStore.auto_msg || "";
              const randomIdx = Math.floor(Math.random() * msgArr.length);
              showMainDialog();
              mpu_typewriter(
                mpu_unescapeHTML(msgArr[randomIdx] + auto),
                "#ukagaka_msg",
              );
            }
            if (wasAutoTalkRunning && mpuAutoTalk) {
              startAutoTalk();
            }
          }, waitTime);
        } else {
          mpuSetMessageBlocking(false);
          const dialogStore = mpuGetDialogStore();
          if (
            dialogStore &&
            Array.isArray(dialogStore.msg) &&
            dialogStore.msg.length > 0
          ) {
            const msgArr = dialogStore.msg;
            const auto = dialogStore.auto_msg || "";
            const randomIdx = Math.floor(Math.random() * msgArr.length);
            showMainDialog();
            mpu_typewriter(
              mpu_unescapeHTML(msgArr[randomIdx] + auto),
              "#ukagaka_msg",
            );
          } else if (typeof mpuClearSystemPlaceholder === "function") {
            // 無內建對話可 fallback：清掉思考氣泡，避免 placeholder 懸空（角色保持沉默）
            mpuClearSystemPlaceholder("#ukagaka_msg");
          }
          mpuSetAiContextInProgress(false);
          if (wasAutoTalkRunning && mpuAutoTalk) {
            startAutoTalk();
          }
        }
      }
    })
    .catch((error) => {
      mpu_handle_error(error, "mpu_chat_context", {
        showToUser: false, // 已經有 fallback 處理，不需要顯示錯誤
      });

      mpuSetMessageBlocking(false);
      const dialogStore = mpuGetDialogStore();
      if (
        dialogStore &&
        Array.isArray(dialogStore.msg) &&
        dialogStore.msg.length > 0
      ) {
        const msgArr = dialogStore.msg;
        const auto = dialogStore.auto_msg || "";
        const randomIdx = Math.floor(Math.random() * msgArr.length);
        showMainDialog();
        mpu_typewriter(
          mpu_unescapeHTML(msgArr[randomIdx] + auto),
          "#ukagaka_msg",
        );
      } else if (typeof mpuClearSystemPlaceholder === "function") {
        // 無內建對話可 fallback：清掉思考氣泡，避免 placeholder 懸空（角色保持沉默）
        mpuClearSystemPlaceholder("#ukagaka_msg");
      }
      mpuSetAiContextInProgress(false);
      if (wasAutoTalkRunning && mpuAutoTalk) {
        startAutoTalk();
      }
    });
}

/**
 * 調試用：手動測試訪客資訊獲取
 * 在瀏覽器控制台輸入：mpu_test_visitor_info() 即可測試
 */
function mpu_test_visitor_info() {
  const visitorUrl = `${mpuRestUrl}visitor-info`;

  mpuFetch(visitorUrl, {
    timeout: 10000, // 10 秒超時
    retries: 1,
  })
    .then((visitorInfo) => {
      mpuLogger.logL("contextVisitorInfo", "訪問者情報：", {
        referrer: visitorInfo.referrer || "無",
        referrer_host: visitorInfo.referrer_host || "無",
        search_engine: visitorInfo.search_engine || "無",
        country: visitorInfo.slimstat_country || "無",
        city: visitorInfo.slimstat_city || "無",
      });
    })
    .catch((error) => {
      mpu_handle_error(error, "mpu_test_visitor_info");
    });
}

// ========== ukagaka-greeting.js ==========

/**
 * 首次訪客打招呼：根據訪客資訊生成個性化問候語
 * @param {Object} settings - 設定物件，包含 auto_talk 等選項
 * @returns {Promise} 返回 Promise，完成時表示打招呼流程結束
 */
function mpu_greet_first_visitor(settings) {
  return new Promise((resolve, reject) => {
    // 🌙 睡眠模式檢查：讓芙莉蓮好好睡覺，不打擾訪客
    const isDeepSleep = mpu_isDeepSleepTime();
    if (isDeepSleep) {
      mpuLogger.logL("greetingSkippedSleepMode", "🌙 睡眠モード：初回訪問者への挨拶をスキップし、キャラクターを休ませます");
      resolve();
      return;
    }

    // 立即暫停自動對話，防止被打岔
    const wasAutoTalkRunning = mpuAutoTalkTimer !== null;
    if (wasAutoTalkRunning) {
      stopAutoTalk();
    }

    const showMainDialog = function () {
      if (jQuery("#ukagaka_msgbox").is(":hidden")) mpu_showmsg(200);
    };

    // 先獲取訪客資訊
    const visitorUrl = `${mpuRestUrl}visitor-info`;

    mpuFetch(visitorUrl, {
      timeout: 10000, // 10 秒超時
      retries: 1,
    })
      .then((visitorInfo) => {
        // 調試模式：記錄訪客資訊
        mpuLogger.logL("greetingVisitorInfo", "訪問者情報：", {
          referrer: visitorInfo.referrer || "無",
          referrer_host: visitorInfo.referrer_host || "無",
          search_engine: visitorInfo.search_engine || "無",
          country: visitorInfo.slimstat_country || "無",
        });

        // 顯示載入訊息
        const loadingMessage =
          typeof mpuL10n !== "undefined" && mpuL10n.unknownVisitor
            ? mpuL10n.unknownVisitor
            : "…あ、知らない人間だ…";
        if (typeof mpuShowSystemPlaceholder === "function") {
          mpuShowSystemPlaceholder({
            context: "greet",
            text: loadingMessage,
            showSpinner: false,
          });
          if (typeof mpuMarkSystemPlaceholder === "function") {
            mpuMarkSystemPlaceholder("#ukagaka_msg");
          }
        } else {
          showMainDialog();
          mpu_typewriter(
            `<span style="color: ${mpuAiTextColor};">${loadingMessage}</span>`,
            "#ukagaka_msg",
            null,
            { systemPlaceholder: true },
          );
        }

        const formData = new FormData();
        formData.append("referrer", visitorInfo.referrer || "");
        formData.append("referrer_host", visitorInfo.referrer_host || "");
        formData.append("search_engine", visitorInfo.search_engine || "");
        formData.append(
          "is_direct",
          visitorInfo.is_direct === true ? "true" : "false",
        );
        formData.append(
          "country",
          visitorInfo.slimstat_country || visitorInfo.country || "",
        );
        formData.append(
          "city",
          visitorInfo.slimstat_city || visitorInfo.city || "",
        );

        // [Fix] 傳送 session_id + history，讓後端記錄問候的 checksum
        const greetSessionId = typeof mpu_getOrCreateChatSessionId === "function"
          ? mpu_getOrCreateChatSessionId() : "";
        if (greetSessionId) {
          formData.append("session_id", greetSessionId);
        }
        if (typeof window.mpuChatHistory !== "undefined" && window.mpuChatHistory.length > 0) {
          formData.append("history", JSON.stringify(window.mpuChatHistory.slice(-10)));
        }

        return mpuFetch(mpuRestUrl + "chat/greet", {
          method: "POST",
          body: formData,
          cancelPrevious: true,
          requestId: "mpu_chat_greet",
          timeout: 60000,
          retries: 1,
        });
      })
      .then((res) => {
        if (res && res.msg && !res.error) {
          let greetingMessage = mpu_unescapeHTML(res.msg);
          greetingMessage = mpu_linkifyUrls(greetingMessage);

          showMainDialog();
          mpu_typewriter(
            `<span style="color: ${mpuAiTextColor};">${greetingMessage}</span>`,
            "#ukagaka_msg",
          );

          // 觸發角色動畫（訪客問候是使用者觸發，強制播放動畫）
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

          // 將自發對話加入對話歷史，讓用戶開對話模式時 AI 記得剛才說過什麼
          if (
            typeof window.mpuChatHistory !== "undefined" &&
            Array.isArray(window.mpuChatHistory)
          ) {
            // synthetic user 錨點：讓 LLM 能在後續對話中看到初次問候的完整脈絡
            window.mpuChatHistory.push({
              role: "user",
              content: "（新しい訪客が来た）",
              type: "synthetic",
              timestamp: Date.now(),
            });
            window.mpuChatHistory.push({
              role: "assistant",
              content: res.msg,
              type: "greet",
              timestamp: Date.now(),
            });

            if (typeof mpu_saveChatHistory === "function") {
              mpu_saveChatHistory();
              mpuLogger.logL("greetingSavedToHistory", "mpu_greet_first_visitor: 挨拶を履歴に追加して保存しました");
            } else {
              mpuLogger.warnL("greetingSaveHistoryFunctionMissing", "mpu_greet_first_visitor: mpu_saveChatHistory 関数が存在しないため、会話履歴を保存できません");
            }
          } else {
            mpuLogger.warnL("greetingChatHistoryUnavailable", "mpu_greet_first_visitor: window.mpuChatHistory が初期化されていないか配列ではないため、会話履歴に追加できません");
          }

          // 🔧 計時邏輯：打字完成 → displayDuration → autoTalkInterval
          if (mpuAiDisplayTimer !== null) {
            clearTimeout(mpuAiDisplayTimer);
            mpuSetAiDisplayTimer(null);
          }

          mpu_waitForTypewriterComplete(function () {
            // 打字完成後，開始 displayDuration 計時
            const displayDurationMs = mpuAiDisplayDuration * 1000;
            mpuSetAiDisplayTimer(setTimeout(function () {
              mpuSetAiDisplayTimer(null);
              if (
                wasAutoTalkRunning &&
                settings.auto_talk === true &&
                mpuAutoTalk
              ) {
                startAutoTalk();
              }
              resolve();
            }, displayDurationMs));
          });
        } else {
          mpuLogger.warnF("greetingFirstVisitorFailed", "初回訪問者への挨拶に失敗しました：%s", res);

          // 檢查是否是速率限制錯誤（請求過於頻繁）
          const isRateLimit =
            (res && res.error && (res.error.includes("請求過於頻繁") || res.error.includes("リクエストが多すぎます"))) ||
            (res && res.code === "rest_rate_limit_exceeded");

          if (isRateLimit) {
            const rateLimitMessage =
              typeof mpuL10n !== "undefined" && mpuL10n.apiMagicInsufficient
                ? mpuL10n.apiMagicInsufficient
                : "…ちょっと待って。API魔力が足りない";
            showMainDialog();
            mpu_typewriter(
              `<span style="color: ${mpuAiTextColor};">${rateLimitMessage}</span>`,
              "#ukagaka_msg",
            );

            mpuSetMessageBlocking(true);
            const waitTime = (mpuAiDisplayDuration || 8) * 1000;

            setTimeout(function () {
              mpuSetMessageBlocking(false);

              const dialogStore = mpuGetDialogStore();
              if (
                dialogStore &&
                Array.isArray(dialogStore.msg) &&
                dialogStore.msg.length > 0
              ) {
                const msgArr = dialogStore.msg;
                const auto = dialogStore.auto_msg || "";
                const randomIdx = Math.floor(Math.random() * msgArr.length);
                showMainDialog();
                mpu_typewriter(
                  mpu_unescapeHTML(msgArr[randomIdx] + auto),
                  "#ukagaka_msg",
                );
              }
              if (
                wasAutoTalkRunning &&
                settings.auto_talk === true &&
                mpuAutoTalk
              ) {
                startAutoTalk();
              }
              resolve();
            }, waitTime);
          } else {
            const dialogStore = mpuGetDialogStore();
            if (
              dialogStore &&
              Array.isArray(dialogStore.msg) &&
              dialogStore.msg.length > 0
            ) {
              const msgArr = dialogStore.msg;
              const auto = dialogStore.auto_msg || "";
              const randomIdx = Math.floor(Math.random() * msgArr.length);
              showMainDialog();
              mpu_typewriter(
                mpu_unescapeHTML(msgArr[randomIdx] + auto),
                "#ukagaka_msg",
              );
            } else if (typeof mpuClearSystemPlaceholder === "function") {
              // 無內建對話可 fallback：清掉思考氣泡，避免 placeholder 懸空（角色保持沉默）
              mpuClearSystemPlaceholder("#ukagaka_msg");
            }
            if (
              wasAutoTalkRunning &&
              settings.auto_talk === true &&
              mpuAutoTalk
            ) {
              startAutoTalk();
            }
            resolve();
          }
        }
      })
      .catch((error) => {
        mpu_handle_error(error, "mpu_greet_first_visitor", {
          showToUser: false,
        });

        const dialogStore = mpuGetDialogStore();
        if (
          dialogStore &&
          Array.isArray(dialogStore.msg) &&
          dialogStore.msg.length > 0
        ) {
          const msgArr = dialogStore.msg;
          const auto = dialogStore.auto_msg || "";
          const randomIdx = Math.floor(Math.random() * msgArr.length);
          showMainDialog();
          mpu_typewriter(
            mpu_unescapeHTML(msgArr[randomIdx] + auto),
            "#ukagaka_msg",
          );
        } else if (typeof mpuClearSystemPlaceholder === "function") {
          // 無內建對話可 fallback：清掉思考氣泡，避免 placeholder 懸空（角色保持沉默）
          mpuClearSystemPlaceholder("#ukagaka_msg");
        }

        if (wasAutoTalkRunning && settings.auto_talk === true && mpuAutoTalk) {
          startAutoTalk();
        }
        resolve();
      });
  });
}

// ========== ukagaka-dialog.js ==========

// ====== 讀取外部對話 ======
/**
 * 載入外部對話檔案
 * @param {string} file - 對話檔案名稱（路徑會被自動處理）
 * @param {boolean} skipFirstMessage - 是否跳過顯示第一句對話（用於 LLM 取代對話模式）
 */
function loadExternalDialog(file, skipFirstMessage = false) {
  const pure = (file || "").replace(/^.*[\\/]/, "");

  const params = new URLSearchParams({
    file: pure,
  });

  const url = `${mpuRestUrl}dialog?${params.toString()}`;

  document.body.style.cursor = "wait";

  const msgElement = jQuery("#ukagaka_msg");
  const currentMsg = msgElement.text().trim();
  const initialMsg = msgElement.attr("data-initial-msg");
  const isInitialSystemMessage =
    msgElement.attr("data-initial-msg-system") === "1";
  const hasShownInitialMsg =
    initialMsg && currentMsg.indexOf(initialMsg) !== -1;

  // 初始訊息為 system placeholder（思考氣泡）時，主對話框維持隱藏，避免在自發
  // 對話載入前先閃出一個空白對話框；待 showFirstMessage 顯示真正的台詞時再淡入。
  if (jQuery("#ukagaka_msgbox").is(":hidden") && !isInitialSystemMessage) {
    mpu_showmsg(200);
  }

  // 檢查是否為睡眠模式且初始訊息為睡眠相關（優先使用伺服器端時間）
  let isDeepSleep = false;
  if (
    typeof window.mpuInfo !== "undefined" &&
    typeof window.mpuInfo.isDeepSleepTime !== "undefined"
  ) {
    isDeepSleep = window.mpuInfo.isDeepSleepTime;
  } else {
    // 備用：使用客戶端時間（向後兼容）
    const now = new Date();
    const hour = now.getHours();
    isDeepSleep = hour >= 0 && hour < 6;
  }
  // 使用隱藏標記檢測睡眠模式（由 PHP 端統一添加）
  const isSleepMessage =
    initialMsg && initialMsg.includes("<!-- mpu-sleep -->");

  if (!hasShownInitialMsg && !isInitialSystemMessage) {
    // 睡眠模式下，如果初始訊息是睡眠相關的，不要覆蓋它
    if (isDeepSleep && isSleepMessage) {
      mpuLogger.logL("dialogLoadingMessageSkippedSleepMessage", "🌙 睡眠モード：睡眠メッセージを検出したため、読み込みメッセージの表示をスキップします");
      // 不顯示載入訊息，保持睡眠訊息
    } else {
      const loadingMessage =
        typeof mpuL10n !== "undefined" && mpuL10n.thinkingMessage
          ? mpuL10n.thinkingMessage
          : "えっと…何を話せばいいかな…";
      // §16.3-A：thinking placeholder，跳過角色動畫
      mpu_typewriter(loadingMessage, "#ukagaka_msg", null, { systemPlaceholder: true });
    }
  }

  mpuFetch(url, {
    cancelPrevious: true,
    requestId: `loadExternalDialog_${pure}`,
    timeout: 15000,
    retries: 1,
  })
    .then((resp) => {
      if (typeof resp !== "object") {
        throw new Error(resp.error || "Expected JSON response from server.");
      }

      if (resp && !resp.error && Array.isArray(resp.msg)) {
        if (resp.msg.length === 0) {
          mpuLogger.warnL("dialogExternalFileEmpty", "loadExternalDialog: 会話ファイルが空です");
          mpuSetDialogStore({
            msg: [],
            auto_msg: resp.auto_msg || "",
            next_msg: resp.next_msg || 0,
            default_msg: resp.default_msg || 0,
          });
          if (skipFirstMessage) {
            mpuLogger.logL("dialogExternalFileEmptyLlmFallback", "loadExternalDialog: LLM 置換会話モードのため会話ファイルが空です。LLM 生成に依存します");
            jQuery("#ukagaka").stop(true, true).fadeIn(200);
            document.body.style.cursor = "auto";
            return;
          }
          mpu_typewriter((window.mpuL10n && window.mpuL10n.dialogEmpty) || "ダイアログファイルが空です。内容を確認してください", "#ukagaka_msg");
          mpu_showmsg(400);
          jQuery("#ukagaka").stop(true, true).fadeIn(200);
          document.body.style.cursor = "auto";
          return;
        }

        try {
          mpuSetDialogStore(resp);
          mpuSetDialogNextMode(resp.next_msg == 1 ? "random" : "sequential");
          mpuSetDialogDefaultMsg(resp.default_msg == 1 ? 1 : 0);

          if (skipFirstMessage) {
            mpuLogger.logL("dialogFallbackLoadedFirstLineSuppressed", "loadExternalDialog: LLM 置換会話モードでフォールバック会話データを読み込みましたが、最初の一文は表示しません");
            let first = 0;
            if (mpuDefaultMsg === 0 && resp.msg.length) {
              first = Math.floor(Math.random() * resp.msg.length);
            }
            jQuery("#ukagaka_msgnum").html(first);
            jQuery("#ukagaka").stop(true, true).fadeIn(200);
            document.body.style.cursor = "auto";
            return;
          }

          let firstMessageShown = false;
          let firstMessageTimer = null;

          const showFirstMessage = function () {
            if (firstMessageTimer !== null) {
              clearTimeout(firstMessageTimer);
              firstMessageTimer = null;
            }

            if (firstMessageShown) {
              mpuLogger.logL("dialogFirstLineDuplicateBlocked", "loadExternalDialog: 最初の会話文を重複表示しようとしたため阻止しました");
              return;
            }

            // 睡眠模式檢查：如果在未喚醒的睡眠模式下，跳過第一句對話
            if (
              typeof mpu_isUnawokenSleepMode === "function" &&
              mpu_isUnawokenSleepMode()
            ) {
              mpuLogger.logL("dialogFirstLineSkippedUnawokenSleepMode", "🌙 睡眠モードでまだ目を覚ましていないため、最初の内蔵会話をスキップし、睡眠メッセージを維持します");
              firstMessageShown = true; // 標記為已處理，避免重複嘗試
              // 睡眠模式下不啟動自動對話
              return;
            }

            firstMessageShown = true;

            let first = 0;
            if (mpuDefaultMsg === 0 && resp.msg.length) {
              first = Math.floor(Math.random() * resp.msg.length);
            }
            // 從初始思考氣泡 placeholder 切換到真正的自發對話：清除氣泡並淡入主對話框。
            if (isInitialSystemMessage) {
              if (typeof mpuClearSystemPlaceholder === "function") {
                mpuClearSystemPlaceholder({ context: "initial" });
              }
              msgElement.removeAttr("data-initial-msg-system");
              if (jQuery("#ukagaka_msgbox").is(":hidden")) mpu_showmsg(200);
            }
            mpu_typewriter(
              mpu_unescapeHTML(resp.msg[first] + (resp.auto_msg || "")),
              "#ukagaka_msg",
            );
            jQuery("#ukagaka_msgnum").html(first);

            // 等待第一句對話打字完成後啟動自動對話
            if (mpuAutoTalk) {
              mpu_waitForTypewriterComplete(function () {
                startAutoTalk();
              });
            }
          };

          // 使用通用函數等待當前打字效果完成
          mpu_waitForTypewriterComplete(function () {
            if (!firstMessageShown) {
              firstMessageTimer = setTimeout(showFirstMessage, 1000);
            }
          });
        } catch (e) {
          mpu_handle_error(e, "loadExternalDialog:process_data", {
            showToUser: true,
            userMessage:
              mpuIsDebugMode()
                ? `処理データの読み込みに失敗しました：${e.message}`
                : ((window.mpuL10n && window.mpuL10n.processingError) || "ダイアログデータの処理中にエラーが発生しました。後でもう一度お試しください。"),
          });
        }
      } else {
        const errorMsg = resp && resp.error ? resp.error : ((window.mpuL10n && window.mpuL10n.loadingFailed) || "読み込みに失敗しました。後でもう一度お試しください。");
        jQuery("#ukagaka_msg").html(errorMsg);

        if (!mpuGetDialogStore()) {
          mpuSetDialogStore({
            msg: [],
            auto_msg: "",
            next_msg: 0,
            default_msg: 0,
          });
          mpuLogger.warnF("dialogBackendErrorUseEmptyFallback", "loadExternalDialog: バックエンドがエラーを返したため、空の mpuMsgList をフォールバックとして設定します - %s", errorMsg);
        }
      }
      jQuery("#ukagaka").stop(true, true).fadeIn(200);
      document.body.style.cursor = "auto";
    })
    .catch((error) => {
      mpu_handle_error(error, "loadExternalDialog", {
        showToUser: true,
        userMessage:
          mpuIsDebugMode()
            ? `ダイアログの読み込みに失敗しました：${error.message}`
            : ((window.mpuL10n && window.mpuL10n.dialogLoadFailed) || "ダイアログファイルの読み込みに失敗しました。後でもう一度お試しください。"),
      });

      if (!mpuGetDialogStore()) {
        mpuSetDialogStore({
          msg: [],
          auto_msg: "",
          next_msg: 0,
          default_msg: 0,
        });
        mpuLogger.warnL("dialogLoadFailedUseEmptyFallback", "loadExternalDialog: 読み込みに失敗したため、空の mpuMsgList をフォールバックとして設定します");
      }

      jQuery("#ukagaka").stop(true, true).fadeIn(200);
      document.body.style.cursor = "auto";
    });
}

// ========== ukagaka-chat-history.js ==========
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

// ========== ukagaka-chat-mode.js ==========
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

// ========== ukagaka-chat-format.js ==========
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

// ========== ukagaka-chat-sse.js ==========
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

// ========== ukagaka-chat-send.js ==========
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

// ========== ukagaka-chat-events.js ==========
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

// ========== ukagaka-chat-wake.js ==========
/**
 * 發送喚醒角色請求給後端
 */
function mpu_display_wake_reaction(res) {
  const fallbackPool =
    res && res.sleep_phase === "nap"
      ? [
          "……食べたばかりなんだけど。",
          "……少しだけ昼寝してたのに。",
          "……お腹いっぱいで、まだ眠い。",
          "……午後は眠いね。",
          "……もう少しだけ、目を閉じてたかった。",
        ]
      : res && res.sleep_phase === "oversleep"
      ? [
          "……もう少し寝ていたかったんだけど。",
          "……起こすの、少し早くない？",
          "……あと少しだけ寝るつもりだったのに。",
          "……二度寝の途中だったんだけど。",
          "……もう朝？ まだ眠い。",
          "……あと五分。人間の五分でいいから。",
          "……夢の続き、ちょうどいいところだったのに。",
          "……布団がまだ私を放してくれない。",
          "……起きる。起きるけど、今じゃなくてもいい。",
          "……まだ少し、夢の中にいたかった。",
          "……もう少し静かに起こしてほしかった。",
        ]
      : res && res.sleep_phase === "deep_sleep"
        ? [
            "……今、起こす必要あった？",
            "……うるさい。まだ寝てたんだけど。",
            "……なに。急ぎの用？",
            "……眠い。あとでいい？",
            "……せっかく寝てたのに。",
            "……まだ頭が起きてないんだけど。",
            "……もう少しだけ寝かせて。",
            "……まだ朝じゃないでしょ。",
            "……夢を見てたところ。",
            "……今の用件、明日でもよくない？",
            "……起きた。たぶん。まだ半分だけ。",
            "……急ぎじゃないなら、あとにして。",
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

// ========== ukagaka-features.js ==========
// ====== 事件處理 ======
function mpuNormalizeInitialSystemPlaceholder(text) {
  let value = String(text || "").trim();
  value = value.replace(/^<!--.*?-->/g, "").trim();
  value = value.replace(/^[（(]+/, "").replace(/[）)]+$/, "").trim();
  value = value.replace(/^[….\s]*えっと[….\s]*/u, "").trim();
  return value || "えっと";
}

function mpuIsCharacterVisible() {
  const img = document.getElementById("ukagaka_img");
  const canvas = document.getElementById("cur_ukagaka");
  if (!img || !canvas) {
    return false;
  }
  const style = window.getComputedStyle ? window.getComputedStyle(img) : img.style;
  return (
    style.visibility !== "hidden" &&
    style.display !== "none" &&
    canvas.width > 0 &&
    canvas.height > 0
  );
}

function mpuShowInitialSystemPlaceholderWhenReady(msgElement, initialMsg) {
  const startedAt = Date.now();
  // First-time visitors may need several seconds for shell/APNG/decoration assets.
  // Do not show the initial system bubble before the character itself is visible.
  const timeout = 12000;

  function show() {
    const manifestText = typeof mpuGetThinkingPlaceholder === "function"
      ? mpuGetThinkingPlaceholder("initial")
      : "";
    mpuShowSystemPlaceholder({
      context: "initial",
      text: manifestText || mpuNormalizeInitialSystemPlaceholder(initialMsg),
    });
    mpuMarkSystemPlaceholder(msgElement);
  }

  function wait() {
    if (mpuIsCharacterVisible() || Date.now() - startedAt > timeout) {
      show();
      return;
    }
    window.requestAnimationFrame(wait);
  }

  wait();
}

jQuery(document).ready(function () {
  mpuLogger.logL("featuresJqueryReady", "jQuery ready を実行しました");

  // 確保 jQuery.cookie 已初始化
  if (!mpu_init_jquery_cookie()) {
    mpuLogger.errorL('jqueryCookieInitFailed', 'jQuery.cookie を初期化できません。一部の機能が正常に動作しない可能性があります');
  } else {
    mpuLogger.logL("featuresJqueryCookieInitialized", "jQuery.cookie を正常に初期化しました");
  }

  // 顯示初始訊息的打字效果
  const msgElement = jQuery("#ukagaka_msg");
  if (msgElement.length) {
    const initialMsg = msgElement.attr("data-initial-msg");
    if (initialMsg) {
      // 清空內容，然後用打字效果顯示
      msgElement.html("");
      // §16.3-A：是否為 system placeholder 由 PHP 端標記決定（睡眠台詞 → 播動畫；
      // 思考中／placeholder → 跳動畫），不再依賴 JS 字串黑名單
      const isSystemMsg = msgElement.attr("data-initial-msg-system") === "1";
      if (isSystemMsg) {
        mpu_hidemsg(0);
        mpuShowInitialSystemPlaceholderWhenReady(msgElement, initialMsg);
      } else {
        mpu_typewriter(initialMsg, "#ukagaka_msg", null, {
          systemPlaceholder: false,
        });
      }
    }
  }

  // 載入外部對話
  function initExternalDialog() {
    const msgListElem = document.getElementById("ukagaka_msglist");
    const isLLMReplaceEnabled = typeof mpuPreSettings !== 'undefined' && mpuPreSettings.ollama_replace === true;

    if (isLLMReplaceEnabled) {
      mpuLogger.logL("featuresLlmReplaceDialogueFallbackLoaded", "LLM 置換会話が有効ですが、フォールバックとして内蔵会話も読み込みます");
    }

    if (
      msgListElem &&
      msgListElem.getAttribute("data-load-external") === "true"
    ) {
      const dialogFile = msgListElem.getAttribute("data-file");
      if (dialogFile) {
        loadExternalDialog(dialogFile, isLLMReplaceEnabled);
      }
    } else {
      try {
        const jsonText = msgListElem ? msgListElem.innerHTML.trim() : "";
        if (jsonText) {
          const dialogStore = JSON.parse(jsonText);
          mpuSetDialogStore(dialogStore);

          if (dialogStore.next_msg !== undefined) {
            mpuSetDialogNextMode(
              dialogStore.next_msg == 1 ? "random" : "sequential"
            );
          }
          if (dialogStore.default_msg !== undefined) {
            mpuSetDialogDefaultMsg(dialogStore.default_msg == 1 ? 1 : 0);
          }
        }
      } catch (e) {
        mpu_handle_error(e, "jQuery.ready:init_dialog_data");
      }
      if (!isLLMReplaceEnabled && mpuAutoTalk && !mpuAutoTalkTimer) {
        startAutoTalk();
      }
    }
  }

  initExternalDialog();

  /**
   * 處理設定資料並初始化功能
   * @param {Object} res - 設定資料
   */
  function processSettings(res) {
    // 防止重複調用（可能由 mpuInitComplete 和預載資料同時觸發）
    if (mpuIsSettingsProcessed()) {
      mpuLogger.logL("featuresProcessSettingsAlreadyHandled", "processSettings: 設定は処理済みのため、重複呼び出しをスキップします");
      return;
    }
    mpuSetSettingsProcessed(true);
    
    if (!res || typeof res !== "object") {
      mpuLogger.warnF("featuresGetSettingsInvalidResponse", "mpu_get_settings: 無効な応答です：%s", res);
      return;
    }

    mpuLogger.logF("featuresGetSettingsReceived", "mpu_get_settings: 設定を受信しました = %s", JSON.stringify(res));
    mpuLogger.log("mpu_get_settings: auto_talk =", res.auto_talk, ", ollama_replace_dialogue =", res.ollama_replace_dialogue);

    mpuSetAutoTalkEnabled(res.auto_talk === true);
    mpuLogger.logF("featuresGetSettingsAutoTalkSet", "mpu_get_settings: mpuAutoTalk を設定しました = %s", mpuAutoTalk);

    if (res.auto_talk_interval) {
      const iv = parseInt(res.auto_talk_interval, 10);
      if (!isNaN(iv) && iv > 0) {
        const baseInterval = iv * 1000;
        
        // 保存基礎間隔（用於動態調整睡眠模式）
        mpuSetBaseAutoTalkInterval(baseInterval);
        
        // 初始設定：檢查當前是否為睡眠模式
        // 優先使用伺服器端時間判定（避免客戶端/伺服器時區差異）
        let interval = baseInterval;
        let isDeepSleep = false;
        if (typeof window.mpuInfo !== 'undefined' && typeof window.mpuInfo.isDeepSleepTime !== 'undefined') {
          isDeepSleep = window.mpuInfo.isDeepSleepTime;
        } else {
          // 備用：使用客戶端時間（向後兼容）
          const now = new Date();
          const hour = now.getHours();
          isDeepSleep = hour >= 0 && hour < 6;
        }
        
        if (isDeepSleep) {
          // 睡眠模式：使用 frequency_multiplier = 0.0667（間隔延長 15 倍）
          const multiplier = res.sleep_mode && res.sleep_mode.frequency_multiplier 
              ? res.sleep_mode.frequency_multiplier 
              : 0.0667;
          interval = Math.round(baseInterval / multiplier);
          mpuLogger.logF("featuresSleepModeIntervalAdjusted", "🌙 睡眠モードが有効です（00:00~06:00）。間隔を %1$s ms に調整しました（元：%2$s ms）", interval, baseInterval);
        }
        
        mpuSetAutoTalkInterval(interval);
      }
      mpuLogger.logF("featuresGetSettingsAutoTalkIntervalSet", "mpu_get_settings: mpuAutoTalkInterval を設定しました = %s ms", mpuAutoTalkInterval);
    }
    if (res.ai_text_color) {
      mpuSetAiTextColor(res.ai_text_color);
    }
    if (res.ai_display_duration) {
      mpuSetAiDisplayDuration(parseInt(res.ai_display_duration, 10) || 8);
    }
    mpuSetOllamaReplaceDialogue(!!res.ollama_replace_dialogue);
    mpuSetEnableChatMode(!!res.enable_chat_mode);
    mpuLogger.logF("featuresLlmReplaceDialogueSettings", "LLM 置換会話設定：%1$s、インタラクティブ会話モード：%2$s", mpuOllamaReplaceDialogue ? "啟用" : "停用", mpuIsChatModeEnabled() ? "啟用" : "停用");

    // 睡眠模式檢測（移到外面以便後續判斷使用）
    const isDeepSleep = mpu_isDeepSleepTime();
    
    // 檢查初始訊息是否為睡眠相關
    const msgElement = jQuery("#ukagaka_msg");
    const initialMsg = msgElement.length ? msgElement.attr("data-initial-msg") : "";
    // 使用隱藏標記檢測睡眠模式（由 PHP 端統一添加）
    const isSleepMessage = initialMsg && initialMsg.includes('<!-- mpu-sleep -->');

    if (mpuOllamaReplaceDialogue) {
      if (isDeepSleep && isSleepMessage) {
        // 睡眠模式下，完全跳過初始的 LLM 對話觸發
        // 讓睡眠訊息保持顯示，直到自動對話計時器自然觸發（約 300 秒後）
        mpuLogger.logL("featuresSleepModeSkipInitialLlmTrigger", "🌙 睡眠モード：初回 LLM 会話トリガーをスキップし、睡眠メッセージの表示を維持します");
        mpuLogger.logF("featuresSleepMessageRetainedUntilAutoTalk", "🌙 睡眠メッセージは自動会話タイマーが作動するまで表示を維持します（約 %s 秒後）", Math.round(mpuGetBaseAutoTalkInterval() / 0.0667 / 1000));
      } else {
        // 正常模式下，立即觸發 LLM 對話
      mpuLogger.logL("featuresLlmReplaceDialogueDelayInitialTrigger", "LLM 置換会話が有効です。初期メッセージの完了後に LLM 会話をトリガーします");
      mpu_waitForTypewriterComplete(function() {
        setTimeout(function () {
          mpu_nextmsg('startup');
        }, 1500);
      });
      }
    }

    // ⚠️ 當 LLM 取代對話啟用時，應在 startup 完成後再啟動自動對話
    // 否則計時器會在 LLM 回應返回前就觸發，導致對話互相覆蓋
    const shouldDelayAutoTalk = mpuOllamaReplaceDialogue && !(isDeepSleep && isSleepMessage);

    mpuLogger.logF("featuresAutoTalkTogglePreparing", "mpu_get_settings: startAutoTalk/stopAutoTalk の呼び出しを準備します。mpuAutoTalk=%1$s、shouldDelayAutoTalk=%2$s", mpuAutoTalk, shouldDelayAutoTalk);
    if (mpuAutoTalk && !shouldDelayAutoTalk) {
      startAutoTalk();
    } else if (!mpuAutoTalk) {
      stopAutoTalk();
    }
    // 如果 shouldDelayAutoTalk 為 true，startAutoTalk 會在 mpu_nextmsg callback 中被觸發
    setAutoTalkUI();

    if (res.ai_enabled === true && res.ai_greet_first_visit === true) {
      if (mpuGreetInProgress) {
        return;
      }

      const firstVisitCookie =
        "mpu_first_visit_" + (document.domain || "default");

      if (typeof jQuery.cookie === "undefined") {
        mpu_init_jquery_cookie();
      }

      if (typeof jQuery.cookie === "undefined") {
        const isFirstVisit = !mpu_getCookie(firstVisitCookie);
        if (isFirstVisit) {
          mpuSetGreetInProgress(true);
          mpu_greet_first_visitor(res)
            .then(() => {
              mpu_setCookie(firstVisitCookie, "1", 365, "/");
              mpuSetGreetInProgress(false);
            })
            .catch((error) => {
              mpu_handle_error(error, "首次訪客打招呼:catch", {
                showToUser: false,
              });
              mpuSetGreetInProgress(false);
            });
        }
        return;
      }

      const isFirstVisit = !jQuery.cookie(firstVisitCookie);

      if (isFirstVisit) {
        mpuSetGreetInProgress(true);
        mpu_greet_first_visitor(res)
          .then(() => {
            if (typeof jQuery.cookie !== "undefined") {
              jQuery.cookie(firstVisitCookie, "1", {
                expires: 365,
                path: "/",
              });
            } else {
              mpu_setCookie(firstVisitCookie, "1", 365, "/");
            }
            mpuSetGreetInProgress(false);
          })
          .catch((error) => {
            mpu_handle_error(error, "首次訪客打招呼:catch2", {
              showToUser: false,
            });
            mpuSetGreetInProgress(false);
          });
        return;
      }
    }

    if (res.ai_enabled === true) {
      mpuLogger.logF("featuresPageAwareAiEnabled", "ページ感知 AI が有効です。トリガーページ条件 = %s", res.ai_trigger_pages);
      const shouldTrigger = mpu_check_page_trigger(res.ai_trigger_pages);

      mpuLogger.logF("featuresPageAwareTriggerCheckResult", "ページ感知チェック結果：shouldTrigger = %s", shouldTrigger);

      if (shouldTrigger) {
        const probability = parseInt(res.ai_probability || 10, 10);
        const roll = Math.floor(Math.random() * 100) + 1;

        mpuLogger.logF("featuresPageAwareProbabilityCheck", "ページ感知の確率チェック：設定確率=%1$s%%、ロール=%2$s、トリガー=%3$s", probability, roll, roll <= probability);

        if (roll <= probability) {
          mpuLogger.logL("featuresPageAwareAiTriggerScheduled", "ページ感知 AI は 3 秒後にトリガーされます");
          // 設置旗標，讓 startup/auto-talk 在頁面感知觸發前不搶先顯示 BOT 對話
          mpuSetContextPending(true);
          setTimeout(function () {
            mpuSetContextPending(false);
            mpu_chat_context();
          }, 3000);
          return;
        } else {
          mpuLogger.logL("featuresPageAwareAiProbabilitySkipped", "ページ感知 AI は確率チェックを通過しなかったため、トリガーしません");
        }
      } else {
        mpuLogger.logL("featuresPageAwareAiPageTypeSkipped", "ページ感知 AI はページタイプチェックを通過しなかったため、トリガーしません");
      }
    } else {
      mpuLogger.logF("featuresPageAwareAiDisabled", "ページ感知 AI は有効ではありません（ai_enabled = %s）", res.ai_enabled);
    }
  }

  // 優先使用 mpu_init 預載的設定資料（性能優化）
  if (window.mpuSettings) {
    mpuLogger.logL("featuresUsingPreloadedSettings", "プリロード済み設定データを使用します");
    processSettings(window.mpuSettings);
  } else {
    // 監聽 mpuInitComplete 事件
    jQuery(document).one("mpuInitComplete", function(event, response) {
      if (response && response.settings) {
        mpuLogger.logL("featuresSettingsFromInitComplete", "mpuInitComplete イベントから設定を取得します");
        processSettings(response.settings);
      }
    });
    
    // Fallback：如果 500ms 內沒有收到資料，發送獨立 AJAX
    setTimeout(function() {
      if (!window.mpuSettings && !mpuIsSettingsLoaded()) {
        mpuLogger.logL("featuresFallbackGetSettingsAjax", "Fallback: 独立した mpu_get_settings AJAX を送信します");
        const settingsUrl = `${mpuRestUrl}settings`;
        
        mpuFetch(settingsUrl, {
          dedupe: true,
          requestId: "mpu_get_settings",
          timeout: 10000,
          retries: 2,
        })
          .then((res) => {
            mpuSetSettingsLoaded(true);
            processSettings(res);
          })
          .catch((error) => {
            mpuLogger.warn("Failed to get mpu_get_settings:", error);
          });
      }
    }, 500);
  }

  if (jQuery("#toggleAutoTalk").length === 0) {
    const btn =
      '<li class="auto-talk"><a id="toggleAutoTalk" href="#" title="自動對話"></a></li>';
    jQuery("#ukagaka-dock ul").append(btn);
    setAutoTalkUI();

    jQuery("#toggleAutoTalk").on("click", function (e) {
      e.preventDefault();
      mpuSetAutoTalkEnabled(!mpuAutoTalk);
      if (mpuAutoTalk) startAutoTalk();
      else stopAutoTalk();
      setAutoTalkUI();
    });
  }

  jQuery("#show_msg").on("click", function (e) {
    e.preventDefault();
    if (jQuery("#ukagaka_msgbox").is(":hidden")) {
      mpu_showmsg(400);
      mpu_setLocal("mpuMsg", "show");
    } else {
      mpu_hidemsg(400);
      mpu_setLocal("mpuMsg", "hidden");
    }
  });

  jQuery("#ukagaka_img").on("click", function () {
    if (jQuery("#ukagaka_msgbox").is(":hidden")) {
      mpu_showmsg(400);
    } else {
      mpu_hidemsg(400);
    }
  });

  jQuery("#mpu_extend").on("click", function () {
    const extendUrl = `${mpuRestUrl}extend`;

    document.body.style.cursor = "wait";
    if (jQuery("#ukagaka").is(":hidden")) mpu_showrobot(400);
    else if (!jQuery("#ukagaka_msgbox").is(":hidden")) mpu_hidemsg(200);

    mpuFetch(extendUrl, {
      timeout: 10000, // 10 秒超時
      retries: 1,
    })
      .then((res) => {
        if (!res || !res.label)
          throw new Error("Invalid extend response.");
        mpu_showmsg(400);
        const link = jQuery("<a>")
          .text(res.label)
          .on("click", function () { mpuChange(""); });
        jQuery("#ukagaka_msg").empty().append(link);
        document.body.style.cursor = "auto";
      })
      .catch((error) => {
        mpu_handle_error(error, "mpu_extend", {
          showToUser: true,
          userMessage:
            mpuIsDebugMode()
              ? `読み込みに失敗しました: ${error.message}`
              : ((window.mpuL10n && window.mpuL10n.loadingFailed) || "読み込みに失敗しました。後でもう一度お試しください。"),
        });
        mpu_showmsg(400);
        document.body.style.cursor = "auto";
      });
  });

  // [!] 移除 scroll fadeIn/fadeOut 邏輯
  jQuery("#toTop").on("click", function (e) {
    e.preventDefault();
    e.stopPropagation(); // 防止 SPA 攔截此錨點點擊
    const startY = window.pageYOffset;
    const duration = 600;
    const startTime = performance.now();
    
    function easeOutCubic(t) {
      return 1 - Math.pow(1 - t, 3);
    }
    
    function step(currentTime) {
      const elapsed = currentTime - startTime;
      const progress = Math.min(elapsed / duration, 1);
      const ease = easeOutCubic(progress);
      
      window.scrollTo(0, startY * (1 - ease));
      
      if (progress < 1) {
        requestAnimationFrame(step);
      }
    }
    
    requestAnimationFrame(step);
  });

  jQuery("#mp_ukagaka").css("display", "block");
  jQuery("#remove").on("click", function (e) {
    e.preventDefault();
    const $ukagaka = jQuery("#ukagaka");
    if ($ukagaka.is(":hidden")) {
      mpu_showrobot(400);
      mpu_setLocal("mpuRobot", "show");
    } else {
      mpu_hiderobot(400);
      mpu_setLocal("mpuRobot", "hidden");
    }
    return false;
  });

  const robotState = mpu_getLocal("mpuRobot");
  if (robotState === "hidden") {
    jQuery("#ukagaka").css("display", "none");
    jQuery("#remove").html(mpuInfo.robot[0]);
  } else {
    jQuery("#remove").html(mpuInfo.robot[1]);
  }
});

jQuery(window).on("blur", function () {
  if (mpuAutoTalk) stopAutoTalk();
});
jQuery(window).on("focus", function () {
  if (mpuAutoTalk) startAutoTalk();
});
document.addEventListener("visibilitychange", function () {
  if (document.hidden) {
    stopAutoTalk();
  } else if (mpuAutoTalk) {
    startAutoTalk();
  }
});

// ====== SPA Navigation 支援 ======
// 預設支援的 SPA 事件列表（使用者可在此陣列添加自己主題的事件）
// 使用方式：在主題的 JS 中添加 window.mpuSpaEvents.push('your-custom-event');
window.mpuSpaEvents = window.mpuSpaEvents || [
  'moelogContentSwap',     // Moelog 
  'swup:contentReplaced',  // Swup.js
  'barba:after',           // Barba.js
  'turbo:load',            // Turbo (Hotwire)
  'astro:page-load'        // Astro View Transitions
];

/**
 * SPA 導航後的頁面感知處理函數
 * @param {Event} e - 事件物件
 */
function mpu_handleSpaNavigation(e) {
  const url = e.detail?.url || e.detail?.to?.url || window.location.href;
  mpuLogger.logL("featuresSpaNavigationContentChanged", "🔄 SPA ナビゲーション：ページ内容が変更されました", url);

  // 延遲一下讓新內容載入完成，然後檢查是否要觸發頁面感知對話
  setTimeout(function () {
    if (typeof mpuInitObservationTracking === "function") {
      if (window.mpuPageContext) window.mpuPageContext.postId = 0;
      mpuInitObservationTracking();
    }

    // 檢查 AI 和頁面感知功能是否啟用
    if (
      typeof window.mpuSettings !== "undefined" &&
      window.mpuSettings.ai_enabled === true
    ) {
      const shouldTrigger = mpu_check_page_trigger(
        window.mpuSettings.ai_trigger_pages
      );

      if (shouldTrigger) {
        const probability = parseInt(window.mpuSettings.ai_probability || 10, 10);
        const roll = Math.floor(Math.random() * 100) + 1;

        mpuLogger.logF("featuresSpaPageAwareProbabilityCheck", "🎲 SPA ページ感知チェック：確率=%1$s、ロール=%2$s、トリガー=%3$s", probability, roll, roll <= probability);

        if (roll <= probability) {
          // 觸發頁面感知 AI 對話
          if (typeof mpu_chat_context === "function") {
            mpu_chat_context();
          }
        }
      }
    }
  }, 1000); // 等待 1 秒讓新頁面內容穩定
}

// 為所有已註冊的 SPA 事件添加監聽器
window.mpuSpaEvents.forEach(function(eventName) {
  document.addEventListener(eventName, mpu_handleSpaNavigation);
});

mpuLogger.logL("featuresScriptLoaded", "スクリプトの読み込みが完了しました");

// ====== 互動對話模式 ======
// 已移至 ukagaka-chat-send.js
