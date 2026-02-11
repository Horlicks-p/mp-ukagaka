var __defProp = Object.defineProperty;
var __defProps = Object.defineProperties;
var __getOwnPropDescs = Object.getOwnPropertyDescriptors;
var __getOwnPropSymbols = Object.getOwnPropertySymbols;
var __hasOwnProp = Object.prototype.hasOwnProperty;
var __propIsEnum = Object.prototype.propertyIsEnumerable;
var __defNormalProp = (obj, key, value) => key in obj ? __defProp(obj, key, { enumerable: true, configurable: true, writable: true, value }) : obj[key] = value;
var __spreadValues = (a, b) => {
  for (var prop in b || (b = {}))
    if (__hasOwnProp.call(b, prop))
      __defNormalProp(a, prop, b[prop]);
  if (__getOwnPropSymbols)
    for (var prop of __getOwnPropSymbols(b)) {
      if (__propIsEnum.call(b, prop))
        __defNormalProp(a, prop, b[prop]);
    }
  return a;
};
var __spreadProps = (a, b) => __defProps(a, __getOwnPropDescs(b));
const mpuClick = "next";
const mpuNextModeInitial = "sequential";
const mpuDefaultMsgInitial = 0;
let mpuNextMode = mpuNextModeInitial;
let mpuDefaultMsg = mpuDefaultMsgInitial;
let mpuAutoTalk = false;
let mpuAutoTalkInterval = 12e3;
let mpuAutoTalkTimer = null;
let debugMode = typeof window !== "undefined" && window.mpuDebugMode === true || false;
let mpuAiTextColor = "#000000";
let mpuAiDisplayDuration = 8;
let mpuAiDisplayTimer = null;
let mpuGreetInProgress = false;
let mpuTypewriterTimer = null;
let mpuTypewriterSpeed = 40;
let mpuOllamaReplaceDialogue = false;
if (typeof mpuPreSettings !== "undefined") {
  if (typeof mpuPreSettings.typewriter_speed !== "undefined") {
    mpuTypewriterSpeed = parseInt(mpuPreSettings.typewriter_speed, 10) || 40;
  }
  if (typeof mpuPreSettings.ollama_replace !== "undefined") {
    mpuOllamaReplaceDialogue = mpuPreSettings.ollama_replace === true;
  }
}
let mpuAiContextInProgress = false;
let mpuMessageBlocking = false;
let mpuLastLLMResponse = "";
let mpuLLMResponseHistory = [];
const mpuMaxResponseHistory = 10;
let mpuLastUserActionTime = Date.now();
const mpuIdleThreshold = 6e4;
let mpuOllamaRequesting = false;
let mpuOllamaRequestQueue = [];
const mpuOllamaQueueDelay = 1500;
window.mpuMsgList = null;
const mpuLogger = {
  /**
   * 記錄調試訊息（只在調試模式下顯示）
   * @param {...any} args - 要記錄的參數
   */
  log: function(...args) {
    if (debugMode || typeof window !== "undefined" && window.mpuDebugMode === true) {
      console.log("[MP Ukagaka]", ...args);
    }
  },
  /**
   * 記錄警告訊息（只在調試模式下顯示）
   * @param {...any} args - 要記錄的參數
   */
  warn: function(...args) {
    if (debugMode || typeof window !== "undefined" && window.mpuDebugMode === true) {
      console.warn("[MP Ukagaka]", ...args);
    }
  },
  /**
   * 記錄錯誤訊息（始終記錄，但格式統一）
   * @param {...any} args - 要記錄的參數
   */
  error: function(...args) {
    console.error("[MP Ukagaka ERROR]", ...args);
  },
  /**
   * 記錄資訊訊息（只在調試模式下顯示）
   * @param {...any} args - 要記錄的參數
   */
  info: function(...args) {
    if (debugMode || typeof window !== "undefined" && window.mpuDebugMode === true) {
      console.info("[MP Ukagaka]", ...args);
    }
  }
};
function debugLog() {
  mpuLogger.log.apply(mpuLogger, arguments);
}
function mpu_handle_error(error, context, options = {}) {
  const {
    showToUser = false,
    userMessage = null,
    silent = false
  } = options;
  const errorMessage = error instanceof Error ? error.message : String(error);
  const errorStack = error instanceof Error ? error.stack : null;
  if (!silent) {
    mpuLogger.error(`[${context}]`, errorMessage);
    if (errorStack && (debugMode || window.mpuDebugMode)) {
      mpuLogger.error("Stack trace:", errorStack);
    }
  }
  if (showToUser) {
    const displayMessage = userMessage || (debugMode || window.mpuDebugMode ? errorMessage : "\u767C\u751F\u932F\u8AA4\uFF0C\u8ACB\u7A0D\u5F8C\u518D\u8A66");
    const $msgBox = jQuery("#ukagaka_msg");
    if ($msgBox.length) {
      mpu_typewriter(displayMessage, $msgBox);
      if (jQuery("#ukagaka_msgbox").is(":hidden")) {
        mpu_showmsg(200);
      }
    }
  }
  return {
    message: errorMessage,
    context,
    originalError: error
  };
}
function mpu_typewriter(text, target, speed, skipCharacterAnimation) {
  if (mpuTypewriterTimer !== null) {
    clearTimeout(mpuTypewriterTimer);
    mpuTypewriterTimer = null;
  }
  if (!text) {
    const $target2 = typeof target === "string" ? jQuery(target) : target;
    $target2.html("");
    return;
  }
  const $target = typeof target === "string" ? jQuery(target) : target;
  const typeSpeed = speed || mpuTypewriterSpeed;
  const targetElement = $target[0] || $target;
  targetElement.innerHTML = "";
  const parts = [];
  let currentIndex = 0;
  let textBuffer = "";
  const textLength = text.length;
  while (currentIndex < textLength) {
    const char = text[currentIndex];
    if (char === "<") {
      if (textBuffer) {
        parts.push({ type: "text", content: textBuffer });
        textBuffer = "";
      }
      const tagEnd = text.indexOf(">", currentIndex);
      if (tagEnd !== -1) {
        const tagContent = text.substring(currentIndex, tagEnd + 1);
        parts.push({ type: "tag", content: tagContent });
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
    parts.push({ type: "text", content: textBuffer });
  }
  let totalTextLength = 0;
  for (const part of parts) {
    if (part.type === "text") {
      totalTextLength += part.content.length;
    }
  }
  const useBatchUpdate = totalTextLength > 50;
  const batchSize = useBatchUpdate ? Math.max(2, Math.min(5, Math.floor(totalTextLength / 20))) : 1;
  let partIndex = 0;
  let charIndex = 0;
  let currentHTML = "";
  let pendingUpdate = false;
  let rafId = null;
  let animationTriggered = false;
  const systemMessages = [
    "\uFF08\u3048\u3063\u3068\u2026\u4F55\u8A71\u305B\u3070\u3044\u3044\u304B\u306A\u2026\uFF09",
    "\u2026\u3042\u3042\u3001\u8A18\u4E8B\u304B\u3002\u3069\u308C\u3069\u308C\u2026",
    "\uFF08\u601D\u8003\u4E2D\u2026\uFF09"
  ];
  let plainText = text.replace(/<[^>]*>/g, "").trim();
  const isSystemMessage = systemMessages.some(function(msg) {
    return plainText.indexOf(msg) !== -1;
  });
  if (typeof window.mpuCanvasManager !== "undefined" && !animationTriggered && !isSystemMessage && !skipCharacterAnimation) {
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
    if (part.type === "tag") {
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
function mpu_cancelTypewriter() {
  if (mpuTypewriterTimer !== null) {
    clearTimeout(mpuTypewriterTimer);
    mpuTypewriterTimer = null;
    mpuLogger.log("\u6253\u5B57\u6548\u679C\u5DF2\u88AB\u4E2D\u65B7");
    return true;
  }
  return false;
}
function mpu_waitForTypewriterComplete(callback, maxWaitTime, checkInterval) {
  if (typeof callback !== "function") {
    mpuLogger.warn("mpu_waitForTypewriterComplete: callback \u4E0D\u662F\u51FD\u6578");
    return;
  }
  maxWaitTime = maxWaitTime || 3e4;
  checkInterval = checkInterval || 50;
  const startTime = Date.now();
  function checkTypewriter() {
    if (mpuTypewriterTimer === null) {
      callback();
      return;
    }
    if (Date.now() - startTime > maxWaitTime) {
      mpuLogger.warn("mpu_waitForTypewriterComplete: \u7B49\u5F85\u8D85\u6642\uFF0C\u5F37\u5236\u57F7\u884C\u56DE\u8ABF");
      callback();
      return;
    }
    setTimeout(checkTypewriter, checkInterval);
  }
  checkTypewriter();
}
function mpu_setLocal(name, value) {
  const data = {
    value,
    expiry: Date.now() + 864e5
    // 1 天過期
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
  var ca = document.cookie.split(";");
  for (var i = 0; i < ca.length; i++) {
    var c = ca[i];
    while (c.charAt(0) === " ") c = c.substring(1, c.length);
    if (c.indexOf(nameEQ) === 0) return decodeURIComponent(c.substring(nameEQ.length, c.length));
  }
  return null;
}
function mpu_setCookie(name, value, days, path) {
  path = path || "/";
  var expires = "";
  if (days) {
    var date = /* @__PURE__ */ new Date();
    date.setTime(date.getTime() + days * 24 * 60 * 60 * 1e3);
    expires = "; expires=" + date.toUTCString();
  }
  document.cookie = name + "=" + encodeURIComponent(value) + expires + "; path=" + path;
}
function mpu_init_jquery_cookie() {
  if (typeof jQuery === "undefined") {
    mpuLogger.warn("jQuery \u5C1A\u672A\u8F09\u5165\uFF0C\u7121\u6CD5\u521D\u59CB\u5316 jQuery.cookie");
    return false;
  }
  if (typeof jQuery.cookie !== "undefined") {
    return true;
  }
  jQuery.cookie = function(name, value, options) {
    if (arguments.length > 1 && value !== null && value !== void 0) {
      var opts = options || {};
      var days = opts.expires;
      if (typeof days === "number") {
        mpu_setCookie(name, value, days, opts.path || "/");
      } else {
        mpu_setCookie(name, value, 0, opts.path || "/");
      }
      return value;
    } else {
      return mpu_getCookie(name);
    }
  };
  return true;
}
function mpu_init_idle_detection() {
  if (typeof jQuery === "undefined") {
    mpuLogger.warn("jQuery \u5C1A\u672A\u8F09\u5165\uFF0C\u7121\u6CD5\u521D\u59CB\u5316\u9592\u7F6E\u5075\u6E2C");
    return false;
  }
  mpuLastUserActionTime = Date.now();
  jQuery(document).on("mousemove keydown scroll click", function() {
    mpuLastUserActionTime = Date.now();
  });
  mpuLogger.log("\u9592\u7F6E\u5075\u6E2C\u5DF2\u521D\u59CB\u5316\uFF0C\u95BE\u503C\uFF1A", mpuIdleThreshold / 1e3, "\u79D2");
  return true;
}
function mpu_updateLastVisitTime() {
  try {
    localStorage.setItem("mpu_last_visit_time", Date.now().toString());
    mpuLogger.log("\u5DF2\u66F4\u65B0\u6700\u5F8C\u8A2A\u554F\u6642\u9593");
  } catch (e) {
    mpuLogger.warn("\u7121\u6CD5\u66F4\u65B0\u6700\u5F8C\u8A2A\u554F\u6642\u9593:", e);
  }
}
function mpu_getLastVisitTime() {
  try {
    const lastVisit = localStorage.getItem("mpu_last_visit_time");
    return lastVisit ? parseInt(lastVisit, 10) : null;
  } catch (e) {
    mpuLogger.warn("\u7121\u6CD5\u7372\u53D6\u6700\u5F8C\u8A2A\u554F\u6642\u9593:", e);
    return null;
  }
}
function mpu_getHoursSinceLastVisit() {
  const lastVisit = mpu_getLastVisitTime();
  if (lastVisit === null) {
    return -1;
  }
  const hours = (Date.now() - lastVisit) / (1e3 * 60 * 60);
  return Math.floor(hours);
}
function mpu_init_visit_tracking() {
  const hoursSince = mpu_getHoursSinceLastVisit();
  if (hoursSince === -1) {
    mpuLogger.log("\u9996\u6B21\u8A2A\u554F\uFF0C\u7121\u4E0A\u6B21\u8A2A\u554F\u8A18\u9304");
  } else {
    mpuLogger.log("\u8DDD\u96E2\u4E0A\u6B21\u8A2A\u554F:", hoursSince, "\u5C0F\u6642");
  }
  mpu_updateLastVisitTime();
  return hoursSince;
}
if (typeof jQuery !== "undefined") {
  mpu_init_jquery_cookie();
  mpu_init_idle_detection();
}
function mpu_unescapeHTML(str) {
  if (!str) return "";
  return String(str).replace(/&amp;/g, "&").replace(/&lt;/g, "<").replace(/&gt;/g, ">").replace(/&nbsp;/g, " ").replace(/&#39;/g, "'").replace(/&quot;/g, '"');
}
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
function mpu_createLinkFromUrl(url) {
  let cleanUrl = url.trim();
  const trimmedUrl = cleanUrl.replace(/[.,;:!?]+$/, "");
  const escapedUrl = trimmedUrl.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#39;");
  const displayText = trimmedUrl.length > 60 ? trimmedUrl.substring(0, 57) + "..." : trimmedUrl;
  const escapedDisplayText = displayText.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#39;");
  return '<a href="' + escapedUrl + '" target="_blank" rel="noopener noreferrer">' + escapedDisplayText + "</a>";
}
const mpuRequestManager = {
  activeRequests: /* @__PURE__ */ new Map(),
  defaults: {
    timeout: 3e4,
    retries: 2,
    retryDelay: 1e3,
    dedupe: false,
    cancelPrevious: false
  },
  generateRequestId: function(url, options = {}) {
    const method = (options.method || "GET").toUpperCase();
    const body = options.body ? options.body instanceof FormData ? "form" : JSON.stringify(options.body) : "";
    return `${method}:${url}:${body}`;
  },
  cancel: function(requestId) {
    if (this.activeRequests.has(requestId)) {
      const controller = this.activeRequests.get(requestId);
      controller.abort();
      this.activeRequests.delete(requestId);
      mpuLogger.log(`\u8ACB\u6C42\u5DF2\u53D6\u6D88: ${requestId}`);
    }
  },
  cancelAll: function() {
    this.activeRequests.forEach((controller, requestId) => {
      controller.abort();
      mpuLogger.log(`\u8ACB\u6C42\u5DF2\u53D6\u6D88: ${requestId}`);
    });
    this.activeRequests.clear();
  },
  cleanup: function(requestId) {
    this.activeRequests.delete(requestId);
  }
};
async function mpuFetch(url, options = {}) {
  const config = __spreadValues(__spreadValues({}, mpuRequestManager.defaults), options);
  const requestId = config.requestId || mpuRequestManager.generateRequestId(url, options);
  if (config.cancelPrevious) {
    mpuRequestManager.cancel(requestId);
  }
  if (config.dedupe && mpuRequestManager.activeRequests.has(requestId)) {
    mpuLogger.log(`\u8ACB\u6C42\u53BB\u91CD\uFF0C\u8DF3\u904E: ${requestId}`);
    return Promise.reject(new Error("\u91CD\u8907\u8ACB\u6C42\u5DF2\u5B58\u5728\uFF0C\u8ACB\u7A0D\u5F8C\u518D\u8A66"));
  }
  const controller = new AbortController();
  mpuRequestManager.activeRequests.set(requestId, controller);
  let timeoutId = null;
  if (config.timeout > 0) {
    timeoutId = setTimeout(() => {
      controller.abort();
      mpuRequestManager.cleanup(requestId);
      mpuLogger.warn(`\u8ACB\u6C42\u8D85\u6642: ${requestId}`);
    }, config.timeout);
  }
  const fetchOptions = __spreadProps(__spreadValues({}, options), {
    signal: controller.signal
  });
  let lastError = null;
  for (let attempt = 0; attempt <= config.retries; attempt++) {
    try {
      if (attempt > 0) {
        mpuLogger.log(`\u91CD\u8A66\u8ACB\u6C42 (${attempt}/${config.retries}): ${requestId}`);
        await new Promise((resolve) => setTimeout(resolve, config.retryDelay * attempt));
      }
      const response = await fetch(url, fetchOptions);
      if (timeoutId) {
        clearTimeout(timeoutId);
      }
      if (controller.signal.aborted) {
        throw new Error("\u8ACB\u6C42\u5DF2\u88AB\u53D6\u6D88");
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
      if (error.name === "AbortError" || controller.signal.aborted) {
        mpuRequestManager.cleanup(requestId);
        if (timeoutId) {
          clearTimeout(timeoutId);
        }
        throw new Error("\u8ACB\u6C42\u5DF2\u88AB\u53D6\u6D88");
      }
      if (attempt < config.retries && (error.message.includes("Failed to fetch") || error.message.includes("NetworkError") || error.message.includes("network"))) {
        mpuLogger.warn(`\u7DB2\u7D61\u932F\u8AA4\uFF0C\u5C07\u91CD\u8A66: ${error.message}`);
        continue;
      }
      if (timeoutId) {
        clearTimeout(timeoutId);
      }
      mpuRequestManager.cleanup(requestId);
      mpu_handle_error(error, "mpuFetch", {
        silent: true
      });
      throw error;
    }
  }
  if (timeoutId) {
    clearTimeout(timeoutId);
  }
  mpuRequestManager.cleanup(requestId);
  throw lastError || new Error("\u8ACB\u6C42\u5931\u6557");
}
function mpuCancelRequest(url, options = {}) {
  const requestId = mpuRequestManager.generateRequestId(url, options);
  mpuRequestManager.cancel(requestId);
}
function mpuCancelAllRequests() {
  mpuRequestManager.cancelAll();
}
function mpu_init_context_menu() {
  if (typeof jQuery === "undefined") {
    mpuLogger.warn("jQuery \u5C1A\u672A\u8F09\u5165\uFF0C\u7121\u6CD5\u521D\u59CB\u5316\u53F3\u9375\u83DC\u55AE");
    return false;
  }
  jQuery(document).ready(function() {
    jQuery(document).on("contextmenu", "#ukagaka_img, #cur_ukagaka", function(e) {
      e.preventDefault();
      mpuLogger.log("\u53F3\u9375\u83DC\u55AE\u89F8\u767C\uFF1A\u986F\u793A\u89D2\u8272\u5207\u63DB\u9078\u55AE");
      if (typeof mpuChange === "function") {
        mpuChange();
      } else {
        mpuLogger.warn("mpuChange \u51FD\u6578\u672A\u5B9A\u7FA9");
      }
      return false;
    });
    mpuLogger.log("\u53F3\u9375\u83DC\u55AE\u5DF2\u521D\u59CB\u5316");
  });
  return true;
}
if (typeof jQuery !== "undefined") {
  mpu_init_context_menu();
}
function mpu_showrobot(speed = 400) {
  jQuery("#remove").html(mpuInfo.robot[1]);
  jQuery("#ukagaka").fadeIn(speed);
}
function mpu_hiderobot(speed = 400) {
  jQuery("#remove").html(mpuInfo.robot[0]);
  jQuery("#ukagaka").fadeOut(speed);
}
function mpu_showmsg(speed = 400) {
  jQuery("#show_msg").html(mpuInfo.msg[1]);
  jQuery("#ukagaka_msgbox").fadeIn(speed);
}
function mpu_hidemsg(speed = 400) {
  jQuery("#show_msg").html(mpuInfo.msg[0]);
  if (speed === 0 || speed === "") {
    jQuery("#ukagaka_msgbox").hide();
  } else {
    jQuery("#ukagaka_msgbox").fadeOut(speed);
  }
}
function mpu_hideMsgText() {
  const $msg = jQuery("#ukagaka_msg");
  if ($msg.length) $msg.css("visibility", "hidden");
}
function mpu_showMsgText() {
  const $msg = jQuery("#ukagaka_msg");
  if ($msg.length) $msg.css("visibility", "visible");
}
function mpu_beforemsg(speed = 400) {
  if (jQuery("#ukagaka").is(":hidden")) {
    mpu_showrobot(speed);
  } else if (!jQuery("#ukagaka_msgbox").is(":hidden")) {
    mpu_hidemsg(speed);
  }
}
function mpu_isUnawokenSleepMode() {
  let isDeepSleep = false;
  if (typeof window.mpuInfo !== "undefined" && typeof window.mpuInfo.isDeepSleepTime !== "undefined") {
    isDeepSleep = window.mpuInfo.isDeepSleepTime;
  } else {
    const now = /* @__PURE__ */ new Date();
    const hour = now.getHours();
    isDeepSleep = hour >= 0 && hour < 6;
  }
  if (!isDeepSleep) {
    return false;
  }
  if (typeof window.mpuFrierenManager !== "undefined" && window.mpuFrierenManager.isFrierenMode && typeof window.mpuFrierenManager.isSleepMessage === "function") {
    return window.mpuFrierenManager.isSleepMessage();
  }
  const msgElement = document.getElementById("ukagaka_msg");
  if (!msgElement) return false;
  const initialMsg = msgElement.getAttribute("data-initial-msg") || "";
  return initialMsg.includes("<!-- mpu-sleep -->");
}
function startAutoTalk() {
  mpuLogger.log(
    "startAutoTalk \u88AB\u8ABF\u7528, mpuAutoTalk =",
    mpuAutoTalk,
    ", mpuAutoTalkInterval =",
    mpuAutoTalkInterval
  );
  stopAutoTalk();
  if (!mpuAutoTalk) {
    mpuLogger.log("startAutoTalk: mpuAutoTalk \u70BA false\uFF0C\u9000\u51FA");
    return;
  }
  if (typeof mpuChatModeActive !== "undefined" && mpuChatModeActive) {
    mpuLogger.log("startAutoTalk: \u5C0D\u8A71\u6A21\u5F0F\u4E2D\uFF0C\u4E0D\u555F\u52D5\u81EA\u52D5\u5C0D\u8A71");
    return;
  }
  if (typeof window.mpuFrierenManager !== "undefined" && window.mpuFrierenManager.decorationChatInProgress) {
    mpuLogger.log("startAutoTalk: \u88DD\u98FE\u7269/\u89F8\u6478\u5C0D\u8A71\u9032\u884C\u4E2D\uFF0C\u4E0D\u555F\u52D5\u81EA\u52D5\u5C0D\u8A71");
    return;
  }
  if (mpu_isUnawokenSleepMode()) {
    mpuLogger.log("\u{1F319} \u7761\u7720\u6A21\u5F0F\u4E14\u5C1A\u672A\u88AB\u559A\u9192\uFF1A\u4E0D\u555F\u52D5\u81EA\u52D5\u5C0D\u8A71\uFF0C\u53EA\u63A5\u53D7 OK \u9215\u89F8\u767C");
    return;
  }
  const checkSleepMode = function() {
    let isDeepSleep = false;
    if (typeof window.mpuInfo !== "undefined" && typeof window.mpuInfo.isDeepSleepTime !== "undefined") {
      isDeepSleep = window.mpuInfo.isDeepSleepTime;
    } else {
      const now = /* @__PURE__ */ new Date();
      const hour = now.getHours();
      isDeepSleep = hour >= 0 && hour < 6;
    }
    const baseInterval = typeof window.mpuBaseAutoTalkInterval !== "undefined" && window.mpuBaseAutoTalkInterval > 0 ? window.mpuBaseAutoTalkInterval : mpuAutoTalkInterval;
    if (isDeepSleep) {
      const sleepMultiplier = 0.111;
      const adjustedInterval = Math.round(baseInterval / sleepMultiplier);
      return { interval: adjustedInterval, isSleepMode: true };
    } else {
      return { interval: baseInterval, isSleepMode: false };
    }
  };
  const sleepModeInfo = checkSleepMode();
  const currentInterval = sleepModeInfo.interval;
  const currentIsSleepMode = sleepModeInfo.isSleepMode;
  if (currentIsSleepMode) {
    mpuLogger.log(
      "\u{1F319} \u7761\u7720\u6A21\u5F0F\u555F\u7528\uFF0800:00~06:00\uFF09\uFF0C\u9593\u9694\u8ABF\u6574\u70BA",
      currentInterval,
      "ms\uFF08\u539F\u59CB:",
      window.mpuBaseAutoTalkInterval || mpuAutoTalkInterval,
      "ms\uFF09"
    );
  }
  if (jQuery("#ukagaka_msgbox").is(":hidden")) mpu_showmsg(400);
  mpuLogger.log("startAutoTalk: \u8A2D\u7F6E\u8A08\u6642\u5668\uFF0C\u9593\u9694 =", currentInterval, "ms");
  mpuAutoTalkTimer = setTimeout(function() {
    mpuAutoTalkTimer = null;
    mpuLogger.log(
      "\u81EA\u52D5\u5C0D\u8A71\u8A08\u6642\u5668\u89F8\u767C, mpuAutoTalk =",
      mpuAutoTalk,
      ", mpuOllamaReplaceDialogue =",
      mpuOllamaReplaceDialogue
    );
    const now = Date.now();
    const idleTime = now - mpuLastUserActionTime;
    if (idleTime > mpuIdleThreshold) {
      mpuLogger.log(
        "\u4F7F\u7528\u8005\u9592\u7F6E\u4E2D\uFF08",
        Math.floor(idleTime / 1e3),
        "\u79D2\uFF09\uFF0C\u8DF3\u904E\u672C\u6B21\u81EA\u52D5\u5C0D\u8A71"
      );
      if (mpuAutoTalk) startAutoTalk();
      return;
    }
    const newSleepModeInfo = checkSleepMode();
    if (newSleepModeInfo.isSleepMode !== currentIsSleepMode) {
      mpuLogger.log(
        "\u7761\u7720\u6A21\u5F0F\u72C0\u614B\u8B8A\u5316\uFF08",
        currentIsSleepMode ? "\u7761\u7720" : "\u6B63\u5E38",
        " \u2192 ",
        newSleepModeInfo.isSleepMode ? "\u7761\u7720" : "\u6B63\u5E38",
        "\uFF09\uFF0C\u91CD\u65B0\u555F\u52D5\u81EA\u52D5\u5C0D\u8A71\uFF08\u65B0\u9593\u9694:",
        newSleepModeInfo.interval,
        "ms\uFF09"
      );
      if (mpuAutoTalk) {
        startAutoTalk();
      }
      return;
    }
    if (mpu_isUnawokenSleepMode()) {
      mpuLogger.log(
        "\u{1F319} \u7761\u7720\u6A21\u5F0F\u4E14\u5C1A\u672A\u88AB\u559A\u9192\uFF1A\u8DF3\u904E\u672C\u6B21\u81EA\u52D5\u5C0D\u8A71\uFF0C\u53EA\u63A5\u53D7 OK \u9215\u89F8\u767C"
      );
      if (mpuAutoTalk) startAutoTalk();
      return;
    }
    if (mpuAutoTalk) {
      mpu_checkSpamEvent(function(spamHandled) {
        if (!spamHandled) {
          mpu_nextmsg("auto");
        }
        if (mpuAutoTalk) {
          startAutoTalk();
        } else {
          stopAutoTalk();
        }
      });
    } else {
      stopAutoTalk();
    }
  }, currentInterval);
}
function stopAutoTalk() {
  if (mpuAutoTalkTimer !== null) {
    clearInterval(mpuAutoTalkTimer);
    mpuAutoTalkTimer = null;
  }
}
function setAutoTalkUI() {
  const $btn = jQuery("#toggleAutoTalk");
  if ($btn.length) $btn.text(mpuAutoTalk ? "\u505C\u6B62\u81EA\u52D5\u5C0D\u8A71" : "\u958B\u59CB\u81EA\u52D5\u5C0D\u8A71");
}
function mpuMoe(command) {
  if (!command) return false;
  const commands = {
    "(:next)": () => mpu_nextmsg(),
    "(:showmsg)": () => mpu_showmsg(400),
    "(:hidemsg)": () => mpu_hidemsg(400),
    "(:showrobot)": () => mpu_showrobot(400),
    "(:hiderobot)": () => mpu_hiderobot(400),
    "(:previous)": () => debugLog("(:previous) is not implemented.")
  };
  if (commands[command]) {
    commands[command]();
    return;
  }
  const m = command.match(/^\(:msg\[(\d+)\]\)$/);
  if (m) {
    const idx = parseInt(m[1], 10) - 1;
    if (window.mpuMsgList && Array.isArray(window.mpuMsgList.msg)) {
      const msgArr = window.mpuMsgList.msg;
      const auto = window.mpuMsgList.auto_msg || "";
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
  if (window.mpuMsgList) {
    const auto = window.mpuMsgList.auto_msg || "";
    mpu_beforemsg(400);
    mpu_showmsg(400);
    setTimeout(() => {
      mpu_typewriter(mpu_unescapeHTML(command + auto), "#ukagaka_msg");
    }, 510);
  }
}
function mpu_checkSpamEvent(callback) {
  const formData = new FormData();
  formData.append("action", "mpu_check_spam_event");
  mpuFetch(mpuurl, {
    method: "POST",
    body: formData,
    timeout: 15e3,
    retries: 0,
    requestId: "mpu_check_spam_event",
    cancelPrevious: true
  }).then(function(res) {
    if (res && res.has_event && res.msg) {
      mpuLogger.log(
        "\u{1F6E1}\uFE0F Akismet \u5783\u573E\u7559\u8A00\u9023\u52D5\uFF1A\u5075\u6E2C\u5230\u5783\u573E\u7559\u8A00\u4E8B\u4EF6\uFF0C\u6514\u622A\u6578\u91CF:",
        res.spam_count
      );
      stopAutoTalk();
      mpu_hidemsg(600);
      setTimeout(function() {
        const aiColor = typeof mpuAiTextColor !== "undefined" ? mpuAiTextColor : "#4a6fa5";
        const msg = '<span style="color: ' + aiColor + ';">' + res.msg + "</span>";
        mpu_showMsgText();
        mpu_typewriter(msg, "#ukagaka_msg");
        mpu_showmsg(400);
        if (res.emoji && typeof window.mpuEmojiManager !== "undefined") {
          if (typeof window.mpuEmojiConfig === "undefined" || !window.mpuEmojiConfig.baseUrl) {
            if (typeof window.loadEmojiConfig === "function") {
              window.loadEmojiConfig().then(function() {
                window.mpuEmojiManager.showEmoji(res.emoji);
              }).catch(function(error) {
                mpuLogger.warn("Akismet: Failed to load emoji config:", error);
              });
            }
          } else {
            window.mpuEmojiManager.showEmoji(res.emoji);
          }
        }
        if (typeof window.mpuCanvasManager !== "undefined" && window.mpuCanvasManager.isCharacterMode) {
          window.mpuCanvasManager.triggerCharacterAnimation();
        }
        mpu_waitForTypewriterComplete(function() {
          callback(true);
        });
      }, 700);
    } else {
      callback(false);
    }
  }).catch(function(error) {
    mpuLogger.warn("Akismet: \u5783\u573E\u7559\u8A00\u4E8B\u4EF6\u6AA2\u67E5\u5931\u6557:", error);
    callback(false);
  });
}
function mpu_processOllamaQueue() {
  if (mpuOllamaRequestQueue.length === 0) {
    mpuLogger.log("mpu_processOllamaQueue: \u4F47\u5217\u70BA\u7A7A");
    return;
  }
  setTimeout(function() {
    const nextTrigger = mpuOllamaRequestQueue.shift();
    mpuLogger.log(
      "mpu_processOllamaQueue: \u8655\u7406\u4F47\u5217\u4E2D\u7684\u8ACB\u6C42, trigger =",
      nextTrigger,
      ", \u5269\u9918\u4F47\u5217\u9577\u5EA6 =",
      mpuOllamaRequestQueue.length
    );
    mpu_nextmsg(nextTrigger);
  }, mpuOllamaQueueDelay);
}
function mpu_nextmsg(trigger) {
  var _a, _b;
  const isAuto = trigger === "auto";
  const isStartup = trigger === "startup";
  const isManual = !isAuto && !isStartup;
  mpuLogger.log(
    "mpu_nextmsg \u88AB\u8ABF\u7528, trigger =",
    trigger,
    ", isAuto =",
    isAuto,
    ", isStartup =",
    isStartup,
    ", isManual =",
    isManual,
    ", mpuOllamaReplaceDialogue =",
    mpuOllamaReplaceDialogue
  );
  if (mpuMessageBlocking) {
    mpuLogger.log(
      "mpu_nextmsg: \u8A0A\u606F\u986F\u793A\u88AB\u963B\u64CB (mpuMessageBlocking=true)\uFF0C\u8DF3\u904E"
    );
    return;
  }
  if (typeof mpuChatModeActive !== "undefined" && mpuChatModeActive) {
    mpuLogger.log("mpu_nextmsg: \u5C0D\u8A71\u6A21\u5F0F\u4E2D\uFF0C\u8DF3\u904E\u81EA\u52D5\u5C0D\u8A71");
    return;
  }
  if (typeof window.mpuFrierenManager !== "undefined" && window.mpuFrierenManager.decorationChatInProgress) {
    mpuLogger.log("mpu_nextmsg: \u88DD\u98FE\u7269/\u89F8\u6478\u5C0D\u8A71\u9032\u884C\u4E2D\uFF0C\u8DF3\u904E\u81EA\u52D5\u5C0D\u8A71");
    return;
  }
  if (isAuto && !mpuAutoTalk) {
    mpuLogger.log("mpu_nextmsg: \u81EA\u52D5\u5C0D\u8A71\u5DF2\u95DC\u9589\uFF0C\u9000\u51FA");
    return;
  }
  if ((isAuto || isStartup) && mpu_isUnawokenSleepMode()) {
    mpuLogger.log(
      "\u{1F319} \u7761\u7720\u6A21\u5F0F\u4E14\u5C1A\u672A\u88AB\u559A\u9192\uFF1A\u8DF3\u904E",
      trigger,
      "\u89F8\u767C\u7684\u5C0D\u8A71\uFF0C\u53EA\u63A5\u53D7 OK \u9215\u89F8\u767C"
    );
    return;
  }
  stopAutoTalk();
  if ((isAuto || isStartup) && mpuAiContextInProgress) {
    mpuLogger.log("mpu_nextmsg: \u9801\u9762\u611F\u77E5 AI \u6B63\u5728\u9032\u884C\u4E2D\uFF0C\u8DF3\u904E\u81EA\u52D5/\u555F\u52D5\u5C0D\u8A71");
    return;
  }
  if ((isAuto || isStartup) && mpuGreetInProgress) {
    mpuLogger.log("mpu_nextmsg: \u9996\u6B21\u8A2A\u5BA2\u6253\u62DB\u547C\u6B63\u5728\u9032\u884C\u4E2D\uFF0C\u8DF3\u904E\u81EA\u52D5/\u555F\u52D5\u5C0D\u8A71");
    return;
  }
  if (mpuOllamaReplaceDialogue && mpuOllamaRequesting) {
    if (isAuto) {
      mpuLogger.log("mpu_nextmsg: Ollama \u6B63\u5728\u8655\u7406\u8ACB\u6C42\uFF0C\u81EA\u52D5\u89F8\u767C\u7684\u8ACB\u6C42\u88AB\u8DF3\u904E");
      return;
    }
    if (mpuOllamaRequestQueue.length < 2) {
      mpuLogger.log("mpu_nextmsg: Ollama \u6B63\u5728\u8655\u7406\u8ACB\u6C42\uFF0C\u6B64\u8ACB\u6C42\u52A0\u5165\u4F47\u5217");
      mpuOllamaRequestQueue.push(trigger);
    } else {
      mpuLogger.log("mpu_nextmsg: \u4F47\u5217\u5DF2\u6EFF\uFF0C\u8DF3\u904E\u6B64\u8ACB\u6C42");
    }
    return;
  }
  if (!isStartup) {
    mpu_hidemsg(600);
  }
  if (mpuOllamaReplaceDialogue) {
    mpuLogger.log("mpu_nextmsg: \u4F7F\u7528 LLM \u751F\u6210\u5C0D\u8A71");
    mpuOllamaRequesting = true;
    const curNum = ((_a = window.mpuInfo) == null ? void 0 : _a.num) || "default_1";
    const curMsgnum = parseInt(
      ((_b = document.getElementById("ukagaka_msgnum")) == null ? void 0 : _b.innerHTML) || "0",
      10
    ) || 0;
    const formData = new FormData();
    formData.append("action", "mpu_nextmsg");
    formData.append("cur_num", curNum);
    formData.append("cur_msgnum", curMsgnum);
    const lastVisitHours = typeof mpu_getHoursSinceLastVisit === "function" ? mpu_getHoursSinceLastVisit() : -1;
    formData.append("last_visit_hours", lastVisitHours);
    if (mpuLastLLMResponse) {
      formData.append("last_response", mpuLastLLMResponse);
    }
    if (mpuLLMResponseHistory.length > 0) {
      const recentHistory = mpuLLMResponseHistory.slice(-8);
      formData.append("response_history", JSON.stringify(recentHistory));
    }
    mpuLogger.log("mpu_nextmsg: \u767C\u9001 LLM POST \u8ACB\u6C42\u5230", mpuurl);
    mpuFetch(mpuurl, {
      method: "POST",
      body: formData,
      timeout: 6e4,
      retries: 1,
      requestId: "mpu_nextmsg_llm",
      cancelPrevious: true
    }).then((res) => {
      var _a2;
      mpuLogger.log("mpu_nextmsg: LLM \u56DE\u61C9 =", res);
      if (mpuMessageBlocking || mpuAiContextInProgress) {
        mpuLogger.log(
          "mpu_nextmsg: LLM \u56DE\u61C9\u88AB\u963B\u64CB\uFF08\u9801\u9762\u611F\u77E5 AI \u6B63\u5728\u9032\u884C\u4E2D\uFF09\uFF0C\u8DF3\u904E\u986F\u793A"
        );
        return;
      }
      if (res && res.msg) {
        const auto = ((_a2 = window.mpuMsgList) == null ? void 0 : _a2.auto_msg) || "";
        const out = res.msg + auto;
        if (typeof window.mpuCanvasManager !== "undefined" && window.mpuCanvasManager.isCharacterMode) {
          const forceAnimation = !isAuto && !isStartup;
          const isWakingUp = window.mpuCanvasManager.triggerCharacterAnimation(
            forceAnimation,
            function() {
              mpu_cancelTypewriter();
              jQuery("#ukagaka_msg").html("");
              mpu_showMsgText();
              mpu_typewriter(mpu_unescapeHTML(out), "#ukagaka_msg");
              mpu_showmsg(400);
            }
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
        if (res.emoji && typeof window.mpuEmojiManager !== "undefined") {
          if (typeof window.mpuEmojiConfig === "undefined" || !window.mpuEmojiConfig.baseUrl) {
            if (typeof window.loadEmojiConfig === "function") {
              window.loadEmojiConfig().then(() => {
                window.mpuEmojiManager.showEmoji(res.emoji);
              }).catch((error) => {
                if (typeof mpuLogger !== "undefined" && mpuLogger.warn) {
                  mpuLogger.warn("Failed to load emoji config:", error);
                }
              });
              return;
            }
          }
          window.mpuEmojiManager.showEmoji(res.emoji);
        }
        mpuLastLLMResponse = res.msg;
        if (mpuLLMResponseHistory.length >= mpuMaxResponseHistory) {
          mpuLLMResponseHistory.shift();
        }
        mpuLLMResponseHistory.push(res.msg);
        if (typeof mpuChatHistory !== "undefined" && Array.isArray(mpuChatHistory)) {
          mpuChatHistory.push({
            role: "assistant",
            content: res.msg,
            timestamp: Date.now()
          });
          mpuLogger.log(
            "mpu_nextmsg: \u81EA\u767C\u5C0D\u8A71\u5DF2\u52A0\u5165\u5C0D\u8A71\u6B77\u53F2\uFF0C\u7576\u524D\u6B77\u53F2\u9577\u5EA6:",
            mpuChatHistory.length
          );
          const maxAutoTalkHistory = 3;
          const assistantMessages = mpuChatHistory.filter(
            (msg) => msg.role === "assistant"
          );
          if (assistantMessages.length > maxAutoTalkHistory) {
            let removed = 0;
            const toRemove = assistantMessages.length - maxAutoTalkHistory;
            for (let i = 0; i < mpuChatHistory.length && removed < toRemove; i++) {
              if (mpuChatHistory[i].role === "assistant") {
                mpuChatHistory.splice(i, 1);
                removed++;
                i--;
              }
            }
            mpuLogger.log(
              "mpu_nextmsg: \u522A\u9664\u4E86",
              removed,
              "\u689D\u820A\u7684\u81EA\u767C\u5C0D\u8A71\uFF0C\u4FDD\u7559",
              maxAutoTalkHistory,
              "\u689D"
            );
          }
          if (typeof mpu_saveChatHistory === "function") {
            mpu_saveChatHistory();
            mpuLogger.log("mpu_nextmsg: \u5C0D\u8A71\u6B77\u53F2\u5DF2\u5132\u5B58");
          } else {
            mpuLogger.warn(
              "mpu_nextmsg: mpu_saveChatHistory \u51FD\u6578\u4E0D\u5B58\u5728\uFF0C\u7121\u6CD5\u5132\u5B58\u5C0D\u8A71\u6B77\u53F2"
            );
          }
        } else {
          mpuLogger.warn(
            "mpu_nextmsg: mpuChatHistory \u672A\u521D\u59CB\u5316\u6216\u4E0D\u662F\u9663\u5217\uFF0C\u7121\u6CD5\u52A0\u5165\u5C0D\u8A71\u6B77\u53F2"
          );
        }
        if (res.msgnum !== void 0) {
          jQuery("#ukagaka_msgnum").html(res.msgnum);
        }
        if (mpuAutoTalk && !mpuAutoTalkTimer) {
          mpuLogger.log(
            "mpu_nextmsg: LLM \u56DE\u61C9\u5B8C\u6210\uFF0C\u7B49\u5F85\u6253\u5B57\u5B8C\u6210\u5F8C\u555F\u52D5\u81EA\u52D5\u5C0D\u8A71\u8A08\u6642\u5668"
          );
          mpu_waitForTypewriterComplete(function() {
            if (mpuAutoTalk && !mpuAutoTalkTimer) {
              mpuLogger.log("mpu_nextmsg: \u6253\u5B57\u5B8C\u6210\uFF0C\u73FE\u5728\u555F\u52D5\u81EA\u52D5\u5C0D\u8A71\u8A08\u6642\u5668");
              startAutoTalk();
            }
          });
        }
      } else {
        mpuLogger.warn("mpu_nextmsg: LLM \u56DE\u61C9\u6C92\u6709 msg", res);
        const isRateLimit = res && res.error && res.error.includes("\u8ACB\u6C42\u904E\u65BC\u983B\u7E41");
        if (isRateLimit) {
          const rateLimitMessage = typeof mpuL10n !== "undefined" && mpuL10n.apiMagicInsufficient ? mpuL10n.apiMagicInsufficient : "\u2026\u3061\u3087\u3063\u3068\u5F85\u3063\u3066\u3002API\u9B54\u529B\u304C\u8DB3\u308A\u306A\u3044";
          mpuLastLLMResponse = "";
          mpuLLMResponseHistory = [];
          mpu_showMsgText();
          mpu_typewriter(
            `<span style="color: ${mpuAiTextColor};">${rateLimitMessage}</span>`,
            "#ukagaka_msg"
          );
          mpu_showmsg(400);
          mpuMessageBlocking = true;
          const waitTime = (mpuAiDisplayDuration || 8) * 1e3;
          setTimeout(function() {
            mpuMessageBlocking = false;
            mpu_nextmsg_fallback();
            if (mpuAutoTalk && !mpuAutoTalkTimer) {
              startAutoTalk();
            }
          }, waitTime);
        } else {
          mpuLogger.warn("mpu_nextmsg: LLM \u56DE\u61C9\u6C92\u6709 msg\uFF0C\u4F7F\u7528\u5F8C\u5099\u5C0D\u8A71");
          mpuLastLLMResponse = "";
          mpuLLMResponseHistory = [];
          mpu_nextmsg_fallback();
          if (mpuAutoTalk && !mpuAutoTalkTimer) {
            mpuLogger.log(
              "mpu_nextmsg: fallback \u5B8C\u6210\uFF0C\u7B49\u5F85\u6253\u5B57\u5B8C\u6210\u5F8C\u555F\u52D5\u8A08\u6642\u5668"
            );
            mpu_waitForTypewriterComplete(function() {
              if (mpuAutoTalk && !mpuAutoTalkTimer) {
                startAutoTalk();
              }
            });
          }
        }
      }
      mpuOllamaRequesting = false;
      mpu_processOllamaQueue();
    }).catch((error) => {
      mpuOllamaRequesting = false;
      mpu_processOllamaQueue();
      if (mpuAutoTalk && !mpuAutoTalkTimer) {
        mpuLogger.log("mpu_nextmsg: \u51FA\u932F\uFF0C\u7B49\u5F85\u6253\u5B57\u5B8C\u6210\u5F8C\u555F\u52D5\u8A08\u6642\u5668");
        mpu_waitForTypewriterComplete(function() {
          if (mpuAutoTalk && !mpuAutoTalkTimer) {
            startAutoTalk();
          }
        });
      }
      if (mpuMessageBlocking || mpuAiContextInProgress) {
        mpuLogger.log(
          "mpu_nextmsg: LLM \u932F\u8AA4\u8655\u7406\u88AB\u963B\u64CB\uFF08\u9801\u9762\u611F\u77E5 AI \u6B63\u5728\u9032\u884C\u4E2D\uFF09\uFF0C\u8DF3\u904E"
        );
        return;
      }
      mpuLogger.warn(
        "LLM dialogue generation failed, using fallback:",
        error
      );
      if (debugMode || window.mpuDebugMode) {
        const errorMsg = error.message || "LLM \u9023\u63A5\u5931\u6557";
        const debugMessage = `<span style="color: #ff4444;">[LLM \u932F\u8AA4: ${errorMsg}]</span>`;
        mpu_showMsgText();
        mpu_typewriter(debugMessage, "#ukagaka_msg");
        mpu_showmsg(400);
        setTimeout(() => {
          mpuLastLLMResponse = "";
          mpu_nextmsg_fallback();
        }, 2e3);
      } else {
        mpuLastLLMResponse = "";
        mpu_nextmsg_fallback();
      }
    });
    return;
  }
  setTimeout(function() {
    const store = window.mpuMsgList;
    if (!store) {
      mpuLogger.warn("mpu_nextmsg: \u5C0D\u8A71\u5C1A\u672A\u8F09\u5165\uFF0C\u7B49\u5F85\u8F09\u5165\u5B8C\u6210...");
      const retryCount = window.__mpu_retry_count || 0;
      if (retryCount < 3) {
        window.__mpu_retry_count = retryCount + 1;
        setTimeout(() => {
          mpu_nextmsg(trigger);
        }, 1e3);
      } else {
        window.__mpu_retry_count = 0;
        mpu_showMsgText();
        mpu_typewriter("\u5C0D\u8A71\u5C1A\u672A\u8F09\u5165\uFF0C\u8ACB\u7A0D\u5019...", "#ukagaka_msg");
        mpu_showmsg(400);
        mpuLogger.warn("mpu_nextmsg: \u5C0D\u8A71\u8F09\u5165\u8D85\u6642\uFF0C\u5DF2\u91CD\u8A66 3 \u6B21");
      }
      return;
    }
    window.__mpu_retry_count = 0;
    if (!Array.isArray(store.msg) || store.msg.length === 0) {
      const errorMsg = store.msg && store.msg.length === 0 ? "\u5C0D\u8A71\u6587\u4EF6\u70BA\u7A7A\uFF0C\u8ACB\u6AA2\u67E5\u5C0D\u8A71\u6587\u4EF6\u5167\u5BB9" : "\u8A0A\u606F\u5217\u8868\u683C\u5F0F\u932F\u8AA4";
      mpu_typewriter(errorMsg, "#ukagaka_msg");
      mpu_showmsg(400);
      mpuLogger.warn("mpu_nextmsg: \u7121\u6CD5\u986F\u793A\u5C0D\u8A71 -", {
        store: store ? "exists" : "null",
        msgArray: store && Array.isArray(store.msg) ? `length=${store.msg.length}` : "not array"
      });
      return;
    }
    let msgNum = parseInt(document.getElementById("ukagaka_msgnum").innerHTML, 10) || 0;
    const msgCount = store.msg.length;
    if (mpuNextMode === "random") {
      let newIdx;
      do {
        newIdx = Math.floor(Math.random() * msgCount);
      } while (newIdx === msgNum && msgCount > 1);
      msgNum = newIdx;
    } else {
      msgNum = msgNum + 1 >= msgCount ? 0 : msgNum + 1;
    }
    const auto = store.auto_msg || "";
    const out = store.msg[msgNum] ? store.msg[msgNum] + auto : "";
    if (typeof window.mpuCanvasManager !== "undefined" && window.mpuCanvasManager.isFrierenMode) {
      const isWakingUp = window.mpuCanvasManager.triggerCharacterAnimation(
        isManual,
        function() {
          mpu_cancelTypewriter();
          jQuery("#ukagaka_msg").html("");
          mpu_showMsgText();
          mpu_typewriter(mpu_unescapeHTML(out), "#ukagaka_msg");
          jQuery("#ukagaka_msgnum").html(msgNum);
          mpu_showmsg(400);
        }
      );
      if (!isWakingUp) {
        mpu_showMsgText();
        mpu_typewriter(mpu_unescapeHTML(out), "#ukagaka_msg");
        jQuery("#ukagaka_msgnum").html(msgNum);
        mpu_showmsg(400);
      }
    } else {
      mpu_showMsgText();
      mpu_typewriter(mpu_unescapeHTML(out), "#ukagaka_msg");
      jQuery("#ukagaka_msgnum").html(msgNum);
      mpu_showmsg(400);
    }
    if (mpuAutoTalk && !mpuAutoTalkTimer) {
      mpuLogger.log("mpu_nextmsg: \u50B3\u7D71\u5C0D\u8A71\uFF0C\u7B49\u5F85\u6253\u5B57\u5B8C\u6210\u5F8C\u555F\u52D5\u8A08\u6642\u5668");
      mpu_waitForTypewriterComplete(function() {
        if (mpuAutoTalk && !mpuAutoTalkTimer) {
          startAutoTalk();
        }
      });
    }
  }, 400);
}
function mpu_nextmsg_fallback() {
  setTimeout(function() {
    mpu_showMsgText();
    if (mpuMessageBlocking || mpuAiContextInProgress) {
      mpuLogger.log(
        "mpu_nextmsg_fallback: \u88AB\u963B\u64CB\uFF08\u9801\u9762\u611F\u77E5 AI \u6B63\u5728\u9032\u884C\u4E2D\uFF09\uFF0C\u8DF3\u904E\u986F\u793A"
      );
      return;
    }
    const store = window.mpuMsgList;
    if (!store) {
      mpuLogger.warn("mpu_nextmsg_fallback: \u5C0D\u8A71\u5C1A\u672A\u8F09\u5165\uFF0C\u7B49\u5F85\u8F09\u5165\u5B8C\u6210...");
      const retryCount = window.__mpu_fallback_retry_count || 0;
      if (retryCount < 2) {
        window.__mpu_fallback_retry_count = retryCount + 1;
        setTimeout(() => {
          mpu_nextmsg_fallback();
        }, 1500);
      } else {
        window.__mpu_fallback_retry_count = 0;
        mpu_showMsgText();
        mpu_typewriter("\u5C0D\u8A71\u5C1A\u672A\u8F09\u5165\uFF0C\u8ACB\u7A0D\u5019...", "#ukagaka_msg");
        mpu_showmsg(400);
        mpuLogger.warn("mpu_nextmsg_fallback: \u5C0D\u8A71\u8F09\u5165\u8D85\u6642\uFF0C\u5DF2\u91CD\u8A66 2 \u6B21");
      }
      return;
    }
    window.__mpu_fallback_retry_count = 0;
    if (!Array.isArray(store.msg) || store.msg.length === 0) {
      const errorMsg = store.msg && store.msg.length === 0 ? "\u5C0D\u8A71\u6587\u4EF6\u70BA\u7A7A\uFF0C\u8ACB\u6AA2\u67E5\u5C0D\u8A71\u6587\u4EF6\u5167\u5BB9" : "\u8A0A\u606F\u5217\u8868\u683C\u5F0F\u932F\u8AA4";
      mpu_typewriter(errorMsg, "#ukagaka_msg");
      mpu_showmsg(400);
      mpuLogger.warn("mpu_nextmsg_fallback: \u7121\u6CD5\u986F\u793A\u5F8C\u5099\u5C0D\u8A71 -", {
        store: store ? "exists" : "null",
        msgArray: store && Array.isArray(store.msg) ? `length=${store.msg.length}` : "not array"
      });
      return;
    }
    let msgNum = parseInt(document.getElementById("ukagaka_msgnum").innerHTML, 10) || 0;
    const msgCount = store.msg.length;
    if (mpuNextMode === "random") {
      let newIdx;
      do {
        newIdx = Math.floor(Math.random() * msgCount);
      } while (newIdx === msgNum && msgCount > 1);
      msgNum = newIdx;
    } else {
      msgNum = msgNum + 1 >= msgCount ? 0 : msgNum + 1;
    }
    const auto = store.auto_msg || "";
    const out = store.msg[msgNum] ? store.msg[msgNum] + auto : "";
    mpu_showMsgText();
    mpu_typewriter(mpu_unescapeHTML(out), "#ukagaka_msg");
    if (typeof window.mpuCanvasManager !== "undefined" && window.mpuCanvasManager.isFrierenMode) {
      window.mpuCanvasManager.triggerCharacterAnimation();
    }
    jQuery("#ukagaka_msgnum").html(msgNum);
    mpu_showmsg(400);
  }, 400);
}
function mpuChange(num) {
  const hasNum = typeof num !== "undefined" && num !== null && num !== "";
  if (hasNum && typeof window.mpuCanvasManager === "undefined") {
    mpu_handle_error("Canvas \u7BA1\u7406\u5668\u672A\u8F09\u5165", "mpuChange:canvas_manager_check", {
      showToUser: true,
      userMessage: "\u52D5\u756B\u6A21\u7D44\u8F09\u5165\u5931\u6557\uFF0C\u7121\u6CD5\u5207\u63DB\u89D2\u8272\u3002\u8ACB\u5237\u65B0\u9801\u9762\u5F8C\u518D\u8A66\u3002"
    });
    return;
  }
  const params = new URLSearchParams({ action: "mpu_change" });
  if (hasNum) {
    params.append("mpu_num", num);
  }
  const url = `${mpuurl}?${params.toString()}`;
  document.body.style.cursor = "wait";
  const wasAutoTalkRunning = mpuAutoTalkTimer !== null;
  if (!hasNum) {
    stopAutoTalk();
  }
  if (!jQuery("#ukagaka_msgbox").is(":hidden")) mpu_hidemsg(200);
  mpuFetch(url, {
    cancelPrevious: true,
    requestId: `mpu_change_${hasNum ? num : "menu"}`,
    timeout: 15e3,
    retries: 1
  }).then((res) => {
    if (!hasNum) {
      if (typeof res !== "string")
        throw new Error("Expected HTML, got JSON.");
      jQuery("#ukagaka_msg").html(res || "No content.");
      mpu_showmsg(300);
      jQuery("#ukagaka").stop(true, true).fadeIn(200);
      document.body.style.cursor = "auto";
      return;
    }
    if (typeof res !== "object") throw new Error("Expected JSON, got HTML.");
    const payload = res || {};
    const $canvas = jQuery("#cur_ukagaka");
    const $wrap = jQuery("#ukagaka");
    if (payload.shell_info && typeof window.mpuCanvasManager !== "undefined") {
      const $imgWrapper = jQuery("#ukagaka_img");
      $imgWrapper.fadeOut(120, function() {
        window.mpuCanvasManager.init(
          payload.shell_info,
          payload.name || "",
          payload.num || null
        );
        $imgWrapper.fadeIn(180);
      });
    } else if (payload.shell) {
      if (typeof window.mpuCanvasManager !== "undefined") {
        const $imgWrapper = jQuery("#ukagaka_img");
        $imgWrapper.fadeOut(120, function() {
          window.mpuCanvasManager.init(
            {
              type: "single",
              url: payload.shell,
              images: []
            },
            payload.name || "",
            payload.num || null
          );
          $imgWrapper.fadeIn(180);
        });
      } else {
        mpuLogger.warn(
          "mpuChange: Canvas \u7BA1\u7406\u5668\u5728 Ajax \u6210\u529F\u5F8C\u624D\u767C\u73FE\u4E0D\u5B58\u5728\uFF0C\u9019\u4E0D\u61C9\u8A72\u767C\u751F"
        );
        mpu_handle_error(
          "Canvas \u7BA1\u7406\u5668\u672A\u8F09\u5165",
          "mpuChange:canvas_manager_fallback",
          {
            showToUser: true,
            userMessage: "\u52D5\u756B\u6A21\u7D44\u8F09\u5165\u5931\u6557\uFF0C\u8ACB\u5237\u65B0\u9801\u9762\u3002"
          }
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
    const useExternalDialog = payload.dialog_filename && msgListElem && msgListElem.getAttribute("data-load-external") === "true";
    if (useExternalDialog) {
      const currentFile = msgListElem.getAttribute("data-file") || "";
      const ext = currentFile.split(".").pop() || "json";
      const pure = `${payload.dialog_filename}.${ext}`;
      msgListElem.setAttribute("data-file", `dialogs/${pure}`);
      loadExternalDialog(pure);
    } else if (payload.msglist) {
      try {
        window.mpuMsgList = typeof payload.msglist === "string" ? JSON.parse(payload.msglist) : payload.msglist;
      } catch (e) {
        mpu_handle_error(e, "mpuChange:parse_msglist");
        window.mpuMsgList = null;
      }
    }
    $wrap.stop(true, true).fadeIn(200);
    mpu_showmsg(300);
    if (hasNum) {
      mpu_hidemsg(0);
    }
    if (wasAutoTalkRunning && mpuAutoTalk && !useExternalDialog) {
      startAutoTalk();
    }
    document.body.style.cursor = "auto";
  }).catch((error) => {
    mpu_handle_error(error, "mpuChange", {
      showToUser: true,
      userMessage: debugMode || window.mpuDebugMode ? `\u8F09\u5165\u5931\u6557: ${error.message}` : "\u8F09\u5165\u5931\u6557\uFF0C\u8ACB\u7A0D\u5F8C\u518D\u8A66\u3002"
    });
    jQuery("#ukagaka").stop(true, true).fadeIn(200);
    mpu_showmsg(200);
    document.body.style.cursor = "auto";
  });
}
(function() {
  "use strict";
  const mpuEmojiManager = {
    // 當前顯示的表情元素
    currentEmoji: null,
    // 表情顯示持續時間（毫秒），APNG 動畫完成後自動移除
    displayDuration: 3e3,
    // 3 秒
    /**
     * 顯示表情
     * @param {string} emojiName - 表情文件名（如 'happy.png'）
     */
    showEmoji: function(emojiName) {
      if (!emojiName || typeof emojiName !== "string") {
        return;
      }
      if (this.currentEmoji) {
        this.hideEmoji(this.currentEmoji);
      }
      const baseUrl = typeof mpuEmojiConfig !== "undefined" && mpuEmojiConfig.baseUrl ? mpuEmojiConfig.baseUrl : "";
      if (!baseUrl) {
        if (typeof mpuLogger !== "undefined" && mpuLogger.log) {
          mpuLogger.log("mpuEmojiManager: \u8868\u60C5\u57FA\u790E\u8DEF\u5F91\u672A\u8A2D\u5B9A");
        }
        return;
      }
      const emojiUrl = baseUrl + emojiName;
      const imgContainer = document.getElementById("ukagaka_img");
      if (!imgContainer) {
        if (typeof mpuLogger !== "undefined" && mpuLogger.log) {
          mpuLogger.log("mpuEmojiManager: \u627E\u4E0D\u5230 #ukagaka_img \u5BB9\u5668");
        }
        return;
      }
      const emojiImg = document.createElement("img");
      emojiImg.className = "frieren-emoji";
      emojiImg.src = emojiUrl;
      emojiImg.alt = "emoji";
      emojiImg.style.display = "block";
      emojiImg.dataset.emojiKey = emojiName.replace(/\.[^.]+$/, "");
      this.applyEmojiScale(emojiImg);
      this.updateEmojiPosition(emojiImg);
      imgContainer.appendChild(emojiImg);
      this.currentEmoji = emojiImg;
      emojiImg.onload = () => {
        this.updateEmojiPosition(emojiImg);
      };
      emojiImg.onerror = () => {
        if (typeof mpuLogger !== "undefined" && mpuLogger.warn) {
          mpuLogger.warn("mpuEmojiManager: \u8868\u60C5\u5716\u7247\u8F09\u5165\u5931\u6557:", emojiUrl);
        }
        this.hideEmoji(emojiImg);
      };
      const self = this;
      setTimeout(() => {
        if (self.currentEmoji === emojiImg) {
          self.hideEmoji(emojiImg);
        }
      }, this.displayDuration);
      if (typeof mpuLogger !== "undefined" && mpuLogger.log) {
        mpuLogger.log("mpuEmojiManager: \u986F\u793A\u8868\u60C5:", emojiName);
      }
    },
    /**
     * 更新表情位置（計算芙莉蓮頭部右側位置）
     * @param {HTMLElement} emojiElement - 表情元素
     */
    updateEmojiPosition: function(emojiElement) {
      var _a, _b, _c;
      const imgContainer = document.getElementById("ukagaka_img");
      if (!imgContainer || !emojiElement) {
        return;
      }
      let frierenImg = null;
      const canvas = imgContainer.querySelector("canvas");
      if (canvas && canvas.style.display !== "none") {
        frierenImg = canvas;
      } else {
        const apngImg = document.getElementById("frieren_idle_apng");
        if (apngImg && apngImg.style.display !== "none") {
          frierenImg = apngImg;
        } else {
          const curUkagaka = document.querySelector("#cur_ukagaka");
          if (curUkagaka && !curUkagaka.classList.contains("frieren-decoration")) {
            frierenImg = curUkagaka;
          }
        }
      }
      if (!frierenImg) {
        const scale2 = emojiElement.dataset.emojiScale || 1;
        emojiElement.style.left = "100%";
        emojiElement.style.top = "20%";
        emojiElement.style.transform = `translateY(-50%) scale(${scale2})`;
        return;
      }
      const containerRect = imgContainer.getBoundingClientRect();
      const imgRect = frierenImg.getBoundingClientRect();
      const emojiKey = emojiElement.dataset.emojiKey;
      let position = { offsetX: -105, offsetY: -88, headRatio: 0.25 };
      if (typeof mpuEmojiConfig !== "undefined" && mpuEmojiConfig.mappings && mpuEmojiConfig.mappings[emojiKey] && mpuEmojiConfig.mappings[emojiKey].position) {
        const cfg = mpuEmojiConfig.mappings[emojiKey].position;
        position = {
          offsetX: (_a = cfg.offsetX) != null ? _a : position.offsetX,
          offsetY: (_b = cfg.offsetY) != null ? _b : position.offsetY,
          headRatio: (_c = cfg.headRatio) != null ? _c : position.headRatio
        };
      }
      const headY = imgRect.top - containerRect.top + imgRect.height * position.headRatio + position.offsetY;
      const offsetX = imgRect.left - containerRect.left + imgRect.width + position.offsetX;
      const scale = emojiElement.dataset.emojiScale || 1;
      emojiElement.style.left = offsetX + "px";
      emojiElement.style.top = headY + "px";
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
      const emojiKey = emojiElement.dataset.emojiKey;
      let scale = 1;
      if (typeof mpuEmojiConfig !== "undefined" && mpuEmojiConfig.mappings && mpuEmojiConfig.mappings[emojiKey] && typeof mpuEmojiConfig.mappings[emojiKey].scale === "number") {
        scale = mpuEmojiConfig.mappings[emojiKey].scale;
      }
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
        if (typeof mpuLogger !== "undefined" && mpuLogger.log) {
          mpuLogger.log("mpuEmojiManager: \u79FB\u9664\u8868\u60C5");
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
  let resizeTimer = null;
  window.addEventListener("resize", () => {
    if (resizeTimer) {
      clearTimeout(resizeTimer);
    }
    resizeTimer = setTimeout(() => {
      mpuEmojiManager.updatePosition();
    }, 100);
  });
  window.mpuEmojiManager = mpuEmojiManager;
  function loadEmojiConfig() {
    if (typeof window.mpuEmojiConfig !== "undefined" && window.mpuEmojiConfig.baseUrl) {
      return Promise.resolve(window.mpuEmojiConfig);
    }
    return new Promise((resolve, reject) => {
      if (typeof mpuurl === "undefined") {
        reject(new Error("mpuurl is not defined"));
        return;
      }
      const params = new URLSearchParams({ action: "mpu_get_emoji_config" });
      if (typeof mpuNonce !== "undefined") {
        params.append("mpu_nonce", mpuNonce);
      }
      const url = `${mpuurl}?${params.toString()}`;
      fetch(url, {
        method: "GET",
        headers: {
          "Content-Type": "application/json"
        }
      }).then((response) => {
        if (!response.ok) {
          throw new Error(`HTTP ${response.status}`);
        }
        return response.json();
      }).then((data) => {
        if (data.success) {
          window.mpuEmojiConfig = {
            baseUrl: data.baseUrl || "",
            supportedEmojis: data.supportedEmojis || [],
            mappings: data.mappings || {}
          };
          resolve(window.mpuEmojiConfig);
        } else {
          reject(new Error(data.error || "Failed to load emoji config"));
        }
      }).catch((error) => {
        reject(error);
      });
    });
  }
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", () => {
      loadEmojiConfig().catch((error) => {
        if (typeof mpuLogger !== "undefined" && mpuLogger.warn) {
          mpuLogger.warn("Failed to load emoji config:", error);
        }
      });
    });
  } else {
    loadEmojiConfig().catch((error) => {
      if (typeof mpuLogger !== "undefined" && mpuLogger.warn) {
        mpuLogger.warn("Failed to load emoji config:", error);
      }
    });
  }
  window.loadEmojiConfig = loadEmojiConfig;
})();
(function() {
  "use strict";
  const mpuCanvasManager = {
    // 內部狀態
    canvas: null,
    ctx: null,
    isAnimated: false,
    images: [],
    // Image 對象陣列
    imageUrls: [],
    // 圖片 URL 陣列
    currentFrame: 0,
    animationTimer: null,
    frameInterval: 150,
    // 動畫幀間隔（毫秒）
    imagesLoaded: false,
    // 圖片是否已全部載入
    pendingAnimation: false,
    // 是否有待執行的動畫
    currentCharacterNum: null,
    // 當前角色 num
    currentCharacterName: null,
    // 當前角色 name
    /**
     * 初始化 Canvas
     * @param {Object} shellInfo - Shell 資訊對象 {type: 'single'|'folder', url: string, images: string[]}
     * @param {string} name - 春菜名稱
     * @param {string} num - 春菜編號（可選，用於角色識別）
     */
    init: function(shellInfo, name, num) {
      if (this.isFrieren(num, name) && window.mpuFrierenManager && window.mpuFrierenManager.isFrierenMode) {
        if (typeof mpuLogger !== "undefined" && mpuLogger.log) {
          mpuLogger.log("\u23ED\uFE0F \u5DF2\u5728\u8299\u8389\u84EE\u6A21\u5F0F\uFF0C\u8DF3\u904E\u91CD\u65B0\u521D\u59CB\u5316");
        }
        return;
      }
      this.stopAnimation();
      if (window.mpuFrierenManager) {
        window.mpuFrierenManager.stopFrierenAnimation();
      }
      this.imagesLoaded = false;
      this.pendingAnimation = false;
      this.currentCharacterName = name || null;
      this.currentCharacterNum = num || null;
      this.canvas = document.getElementById("cur_ukagaka");
      if (!this.canvas) {
        console.error("[MP Ukagaka] Canvas \u5143\u7D20\u4E0D\u5B58\u5728");
        return;
      }
      this.ctx = this.canvas.getContext("2d");
      if (!this.ctx) {
        console.error("[MP Ukagaka] \u7121\u6CD5\u7372\u53D6 Canvas \u4E0A\u4E0B\u6587");
        return;
      }
      if (name) {
        this.canvas.setAttribute("title", name);
        this.canvas.setAttribute("data-alt", name);
      }
      if (this.isFrieren(num, name)) {
        if (window.mpuFrierenManager) {
          window.mpuFrierenManager.initFrierenMode(shellInfo, name);
        } else {
          console.warn("[MP Ukagaka] \u8299\u8389\u84EE\u7BA1\u7406\u5668\u672A\u8F09\u5165\uFF0C\u4F7F\u7528\u901A\u7528\u6A21\u5F0F");
          this.initGenericMode(shellInfo);
        }
      } else {
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
      if (shellInfo && shellInfo.type === "folder" && shellInfo.images && shellInfo.images.length > 0) {
        this.isAnimated = true;
        this.imageUrls = shellInfo.images.map(function(filename) {
          return shellInfo.url + filename;
        });
        this.loadImages();
      } else {
        this.isAnimated = false;
        this.imagesLoaded = true;
        const imageUrl = shellInfo && shellInfo.url ? shellInfo.url : "";
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
      img.crossOrigin = "anonymous";
      img.onload = (function() {
        this.canvas.width = img.width;
        this.canvas.height = img.height;
        this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
        this.ctx.drawImage(img, 0, 0);
        const imgContainer = document.getElementById("ukagaka_img");
        if (imgContainer) {
          imgContainer.style.visibility = "visible";
        }
        const msgbox = document.getElementById("ukagaka_msgbox");
        if (msgbox) {
          msgbox.style.visibility = "visible";
        }
      }).bind(this);
      img.onerror = function() {
        console.error("[MP Ukagaka] \u5716\u7247\u8F09\u5165\u5931\u6557:", imageUrl);
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
      this.imagesLoaded = false;
      for (let i = 0; i < this.imageUrls.length; i++) {
        const img = new Image();
        img.crossOrigin = "anonymous";
        img.onload = (function(index) {
          loadedCount++;
          if (loadedCount === 1) {
            this.canvas.width = img.width;
            this.canvas.height = img.height;
          }
          if (loadedCount === totalImages) {
            this.imagesLoaded = true;
            const imgContainer = document.getElementById("ukagaka_img");
            if (imgContainer) {
              imgContainer.style.visibility = "visible";
            }
            const msgbox = document.getElementById("ukagaka_msgbox");
            if (msgbox) {
              msgbox.style.visibility = "visible";
            }
            this.currentFrame = 0;
            this.drawFrame(0);
            if (this.pendingAnimation) {
              setTimeout((function() {
                this.playAnimation();
              }).bind(this), 50);
            }
          }
        }).bind(this);
        img.onerror = (function(url) {
          console.error("[MP Ukagaka] \u5716\u7247\u8F09\u5165\u5931\u6557:", url);
          loadedCount++;
          if (loadedCount === totalImages) {
            this.imagesLoaded = true;
            if (this.images.length > 0) {
              this.currentFrame = 0;
              this.drawFrame(0);
              if (this.pendingAnimation) {
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
      if (frameIndex < 0 || frameIndex >= this.images.length) {
        frameIndex = 0;
      }
      const img = this.images[frameIndex];
      if (!img || !img.complete || img.naturalWidth === 0) {
        return;
      }
      this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
      this.ctx.drawImage(img, 0, 0);
      this.currentFrame = frameIndex;
    },
    /**
     * 播放動畫
     */
    playAnimation: function() {
      if (this.isCharacterMode) {
        this.triggerCharacterAnimation();
        return;
      }
      if (!this.isAnimated) {
        return;
      }
      if (!this.imagesLoaded || !this.images || this.images.length === 0) {
        this.pendingAnimation = true;
        return;
      }
      this.pendingAnimation = false;
      this.stopAnimation();
      this.currentFrame = 0;
      this.drawFrame(0);
      if (this.images.length <= 1) {
        return;
      }
      let frameIndex = 1;
      this.animationTimer = setInterval((function() {
        if (frameIndex >= this.images.length) {
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
      const checkNum = num !== void 0 ? num : this.currentCharacterNum;
      const checkName = name !== void 0 ? name : this.currentCharacterName;
      if (checkNum === "default_1") {
        return true;
      }
      if (checkName && (checkName.indexOf("\u30D5\u30EA\u30FC\u30EC\u30F3") !== -1 || checkName.indexOf("Frieren") !== -1 || checkName.indexOf("frieren") !== -1)) {
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
      if (window.mpuFrierenManager && window.mpuFrierenManager.isFrierenMode) {
        if (window.mpuFrierenManager.frierenWakeUpImages && window.mpuFrierenManager.frierenWakeUpImages.length > 0) {
          return true;
        }
        if (typeof window.mpuFrierenManager.hasWakeUpAnimation === "function") {
          return window.mpuFrierenManager.hasWakeUpAnimation();
        }
      }
      return false;
    }
  };
  window.mpuCanvasManager = mpuCanvasManager;
})();
jQuery(document).ready(function() {
  mpuLogger.log("jQuery ready \u5DF2\u57F7\u884C");
  if (!mpu_init_jquery_cookie()) {
    mpuLogger.error("\u7121\u6CD5\u521D\u59CB\u5316 jQuery.cookie\uFF0C\u67D0\u4E9B\u529F\u80FD\u53EF\u80FD\u7121\u6CD5\u6B63\u5E38\u5DE5\u4F5C");
  } else {
    mpuLogger.log("jQuery.cookie \u5DF2\u6210\u529F\u521D\u59CB\u5316");
  }
  const msgElement = jQuery("#ukagaka_msg");
  if (msgElement.length) {
    const initialMsg = msgElement.attr("data-initial-msg");
    if (initialMsg) {
      msgElement.html("");
      mpu_typewriter(initialMsg, "#ukagaka_msg");
    }
  }
  function initExternalDialog() {
    const msgListElem = document.getElementById("ukagaka_msglist");
    const isLLMReplaceEnabled = typeof mpuPreSettings !== "undefined" && mpuPreSettings.ollama_replace === true;
    if (isLLMReplaceEnabled) {
      mpuLogger.log("LLM \u53D6\u4EE3\u5C0D\u8A71\u5DF2\u555F\u7528\uFF0C\u4F46\u4ECD\u8F09\u5165\u5167\u5EFA\u5C0D\u8A71\u4F5C\u70BA\u5F8C\u5099");
    }
    if (msgListElem && msgListElem.getAttribute("data-load-external") === "true") {
      const dialogFile = msgListElem.getAttribute("data-file");
      if (dialogFile) {
        loadExternalDialog(dialogFile, isLLMReplaceEnabled);
      }
    } else {
      try {
        const jsonText = msgListElem ? msgListElem.innerHTML.trim() : "";
        if (jsonText) {
          window.mpuMsgList = JSON.parse(jsonText);
          if (window.mpuMsgList.next_msg !== void 0) {
            mpuNextMode = window.mpuMsgList.next_msg == 1 ? "random" : "sequential";
          }
          if (window.mpuMsgList.default_msg !== void 0) {
            mpuDefaultMsg = window.mpuMsgList.default_msg == 1 ? 1 : 0;
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
  function processSettings(res) {
    if (window.mpuSettingsProcessed) {
      mpuLogger.log("processSettings: \u5DF2\u8655\u7406\u904E\u8A2D\u5B9A\uFF0C\u8DF3\u904E\u91CD\u8907\u8ABF\u7528");
      return;
    }
    window.mpuSettingsProcessed = true;
    if (!res || typeof res !== "object") {
      mpuLogger.warn("mpu_get_settings: \u7121\u6548\u7684\u56DE\u61C9", res);
      return;
    }
    mpuLogger.log("mpu_get_settings: \u6536\u5230\u8A2D\u5B9A =", JSON.stringify(res));
    mpuLogger.log("mpu_get_settings: auto_talk =", res.auto_talk, ", ollama_replace_dialogue =", res.ollama_replace_dialogue);
    mpuAutoTalk = res.auto_talk === true;
    mpuLogger.log("mpu_get_settings: \u8A2D\u7F6E mpuAutoTalk =", mpuAutoTalk);
    if (res.auto_talk_interval) {
      const iv = parseInt(res.auto_talk_interval, 10);
      if (!isNaN(iv) && iv > 0) {
        const baseInterval = iv * 1e3;
        window.mpuBaseAutoTalkInterval = baseInterval;
        let interval = baseInterval;
        let isDeepSleep2 = false;
        if (typeof window.mpuInfo !== "undefined" && typeof window.mpuInfo.isDeepSleepTime !== "undefined") {
          isDeepSleep2 = window.mpuInfo.isDeepSleepTime;
        } else {
          const now = /* @__PURE__ */ new Date();
          const hour = now.getHours();
          isDeepSleep2 = hour >= 0 && hour < 6;
        }
        if (isDeepSleep2) {
          const multiplier = res.sleep_mode && res.sleep_mode.frequency_multiplier ? res.sleep_mode.frequency_multiplier : 0.0667;
          interval = Math.round(baseInterval / multiplier);
          mpuLogger.log("\u{1F319} \u7761\u7720\u6A21\u5F0F\u555F\u7528\uFF0800:00~06:00\uFF09\uFF0C\u9593\u9694\u8ABF\u6574\u70BA", interval, "ms\uFF08\u539F\u59CB:", baseInterval, "ms\uFF09");
        }
        mpuAutoTalkInterval = interval;
      }
      mpuLogger.log("mpu_get_settings: \u8A2D\u7F6E mpuAutoTalkInterval =", mpuAutoTalkInterval, "ms");
    }
    if (res.ai_text_color) {
      mpuAiTextColor = res.ai_text_color;
    }
    if (res.ai_display_duration) {
      mpuAiDisplayDuration = parseInt(res.ai_display_duration, 10) || 8;
    }
    mpuOllamaReplaceDialogue = !!res.ollama_replace_dialogue;
    mpuEnableChatMode = !!res.enable_chat_mode;
    mpuLogger.log(
      "LLM \u53D6\u4EE3\u5C0D\u8A71\u8A2D\u5B9A: " + (mpuOllamaReplaceDialogue ? "\u555F\u7528" : "\u505C\u7528") + "\uFF0C\u4E92\u52D5\u5C0D\u8A71\u6A21\u5F0F: " + (mpuEnableChatMode ? "\u555F\u7528" : "\u505C\u7528")
    );
    let isDeepSleep = false;
    if (typeof window.mpuInfo !== "undefined" && typeof window.mpuInfo.isDeepSleepTime !== "undefined") {
      isDeepSleep = window.mpuInfo.isDeepSleepTime;
    } else {
      const now = /* @__PURE__ */ new Date();
      const hour = now.getHours();
      isDeepSleep = hour >= 0 && hour < 6;
    }
    const msgElement2 = jQuery("#ukagaka_msg");
    const initialMsg = msgElement2.length ? msgElement2.attr("data-initial-msg") : "";
    const isSleepMessage = initialMsg && initialMsg.includes("<!-- mpu-sleep -->");
    if (mpuOllamaReplaceDialogue) {
      if (isDeepSleep && isSleepMessage) {
        mpuLogger.log("\u{1F319} \u7761\u7720\u6A21\u5F0F\uFF1A\u8DF3\u904E\u521D\u59CB LLM \u5C0D\u8A71\u89F8\u767C\uFF0C\u4FDD\u6301\u7761\u7720\u8A0A\u606F\u986F\u793A");
        mpuLogger.log("\u{1F319} \u7761\u7720\u8A0A\u606F\u5C07\u4FDD\u6301\u986F\u793A\uFF0C\u76F4\u5230\u81EA\u52D5\u5C0D\u8A71\u8A08\u6642\u5668\u89F8\u767C\uFF08\u7D04 " + Math.round((window.mpuBaseAutoTalkInterval || mpuAutoTalkInterval) / 0.0667 / 1e3) + " \u79D2\u5F8C\uFF09");
      } else {
        mpuLogger.log("LLM \u53D6\u4EE3\u5C0D\u8A71\u5DF2\u555F\u7528\uFF0C\u7B49\u5F85\u521D\u59CB\u8A0A\u606F\u5B8C\u6210\u5F8C\u89F8\u767C LLM \u5C0D\u8A71");
        mpu_waitForTypewriterComplete(function() {
          setTimeout(function() {
            mpu_nextmsg("startup");
          }, 1500);
        });
      }
    }
    const shouldDelayAutoTalk = mpuOllamaReplaceDialogue && !(isDeepSleep && isSleepMessage);
    mpuLogger.log("mpu_get_settings: \u6E96\u5099\u8ABF\u7528 startAutoTalk/stopAutoTalk, mpuAutoTalk =", mpuAutoTalk, ", shouldDelayAutoTalk =", shouldDelayAutoTalk);
    if (mpuAutoTalk && !shouldDelayAutoTalk) {
      startAutoTalk();
    } else if (!mpuAutoTalk) {
      stopAutoTalk();
    }
    setAutoTalkUI();
    if (res.ai_enabled === true && res.ai_greet_first_visit === true) {
      if (mpuGreetInProgress) {
        return;
      }
      const firstVisitCookie = "mpu_first_visit_" + (document.domain || "default");
      if (typeof jQuery.cookie === "undefined") {
        mpu_init_jquery_cookie();
      }
      if (typeof jQuery.cookie === "undefined") {
        const isFirstVisit2 = !mpu_getCookie(firstVisitCookie);
        if (isFirstVisit2) {
          mpuGreetInProgress = true;
          mpu_greet_first_visitor(res).then(() => {
            mpu_setCookie(firstVisitCookie, "1", 365, "/");
            mpuGreetInProgress = false;
          }).catch((error) => {
            mpu_handle_error(error, "\u9996\u6B21\u8A2A\u5BA2\u6253\u62DB\u547C:catch", {
              showToUser: false
            });
            mpuGreetInProgress = false;
          });
        }
        return;
      }
      const isFirstVisit = !jQuery.cookie(firstVisitCookie);
      if (isFirstVisit) {
        mpuGreetInProgress = true;
        mpu_greet_first_visitor(res).then(() => {
          if (typeof jQuery.cookie !== "undefined") {
            jQuery.cookie(firstVisitCookie, "1", {
              expires: 365,
              path: "/"
            });
          } else {
            mpu_setCookie(firstVisitCookie, "1", 365, "/");
          }
          mpuGreetInProgress = false;
        }).catch((error) => {
          mpu_handle_error(error, "\u9996\u6B21\u8A2A\u5BA2\u6253\u62DB\u547C:catch2", {
            showToUser: false
          });
          mpuGreetInProgress = false;
        });
        return;
      }
    }
    if (res.ai_enabled === true) {
      mpuLogger.log("\u9801\u9762\u611F\u77E5 AI \u5DF2\u555F\u7528\uFF0C\u89F8\u767C\u9801\u9762\u689D\u4EF6 =", res.ai_trigger_pages);
      const shouldTrigger = mpu_check_page_trigger(res.ai_trigger_pages);
      mpuLogger.log("\u9801\u9762\u611F\u77E5\u6AA2\u67E5\u7D50\u679C: shouldTrigger =", shouldTrigger);
      if (shouldTrigger) {
        const probability = parseInt(res.ai_probability || 10, 10);
        const roll = Math.floor(Math.random() * 100) + 1;
        mpuLogger.log(
          "\u9801\u9762\u611F\u77E5\u6A5F\u7387\u6AA2\u67E5: \u8A2D\u5B9A\u6A5F\u7387 =",
          probability,
          "%, \u9AB0\u5B50 =",
          roll,
          ", \u89F8\u767C =",
          roll <= probability
        );
        if (roll <= probability) {
          mpuLogger.log("\u9801\u9762\u611F\u77E5 AI \u5C07\u5728 3 \u79D2\u5F8C\u89F8\u767C");
          setTimeout(function() {
            mpu_chat_context();
          }, 3e3);
          return;
        } else {
          mpuLogger.log("\u9801\u9762\u611F\u77E5 AI \u672A\u901A\u904E\u6A5F\u7387\u6AA2\u67E5\uFF0C\u4E0D\u89F8\u767C");
        }
      } else {
        mpuLogger.log("\u9801\u9762\u611F\u77E5 AI \u672A\u901A\u904E\u9801\u9762\u985E\u578B\u6AA2\u67E5\uFF0C\u4E0D\u89F8\u767C");
      }
    } else {
      mpuLogger.log("\u9801\u9762\u611F\u77E5 AI \u672A\u555F\u7528\uFF08ai_enabled =", res.ai_enabled, "\uFF09");
    }
  }
  if (window.mpuSettings) {
    mpuLogger.log("\u4F7F\u7528\u9810\u8F09\u8A2D\u5B9A\u8CC7\u6599");
    processSettings(window.mpuSettings);
  } else {
    jQuery(document).one("mpuInitComplete", function(event, response) {
      if (response && response.settings) {
        mpuLogger.log("\u5F9E mpuInitComplete \u4E8B\u4EF6\u7372\u53D6\u8A2D\u5B9A");
        processSettings(response.settings);
      }
    });
    setTimeout(function() {
      if (!window.mpuSettings && !window.mpuSettingsLoaded) {
        mpuLogger.log("Fallback: \u767C\u9001\u7368\u7ACB mpu_get_settings AJAX");
        const settingsParams = new URLSearchParams({ action: "mpu_get_settings" });
        const settingsUrl = `${mpuurl}?${settingsParams.toString()}`;
        mpuFetch(settingsUrl, {
          dedupe: true,
          requestId: "mpu_get_settings",
          timeout: 1e4,
          retries: 2
        }).then((res) => {
          window.mpuSettingsLoaded = true;
          processSettings(res);
        }).catch((error) => {
          mpuLogger.warn("Failed to get mpu_get_settings:", error);
        });
      }
    }, 500);
  }
  if (jQuery("#toggleAutoTalk").length === 0) {
    const btn = '<li class="auto-talk"><a id="toggleAutoTalk" href="javascript:void(0);" title="\u81EA\u52D5\u5C0D\u8A71"></a></li>';
    jQuery("#ukagaka-dock ul").append(btn);
    setAutoTalkUI();
    jQuery("#toggleAutoTalk").on("click", function() {
      mpuAutoTalk = !mpuAutoTalk;
      if (mpuAutoTalk) startAutoTalk();
      else stopAutoTalk();
      setAutoTalkUI();
    });
  }
  jQuery("#show_msg").on("click", function() {
    if (jQuery("#ukagaka_msgbox").is(":hidden")) {
      mpu_showmsg(400);
      mpu_setLocal("mpuMsg", "show");
    } else {
      mpu_hidemsg(400);
      mpu_setLocal("mpuMsg", "hidden");
    }
  });
  jQuery("#ukagaka_img").on("click", function() {
    if (jQuery("#ukagaka_msgbox").is(":hidden")) {
      mpu_showmsg(400);
    } else {
      mpu_hidemsg(400);
    }
  });
  jQuery("#mpu_extend").on("click", function() {
    const extendParams = new URLSearchParams({ action: "mpu_extend" });
    const extendUrl = `${mpuurl}?${extendParams.toString()}`;
    document.body.style.cursor = "wait";
    if (jQuery("#ukagaka").is(":hidden")) mpu_showrobot(400);
    else if (!jQuery("#ukagaka_msgbox").is(":hidden")) mpu_hidemsg(200);
    mpuFetch(extendUrl, {
      timeout: 1e4,
      // 10 秒超時
      retries: 1
    }).then((html) => {
      if (typeof html !== "string")
        throw new Error("Expected HTML response.");
      mpu_showmsg(400);
      jQuery("#ukagaka_msg").html(html);
      document.body.style.cursor = "auto";
    }).catch((error) => {
      mpu_handle_error(error, "mpu_extend", {
        showToUser: true,
        userMessage: debugMode || window.mpuDebugMode ? `\u8F09\u5165\u5931\u6557: ${error.message}` : "\u8F09\u5165\u5931\u6557\uFF0C\u8ACB\u7A0D\u5F8C\u518D\u8A66\u3002"
      });
      mpu_showmsg(400);
      document.body.style.cursor = "auto";
    });
  });
  jQuery("#toTop").on("click", function() {
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
  jQuery("#remove").on("click", function() {
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
jQuery(window).on("blur", function() {
  if (mpuAutoTalk) stopAutoTalk();
});
jQuery(window).on("focus", function() {
  if (mpuAutoTalk) startAutoTalk();
});
document.addEventListener("visibilitychange", function() {
  if (document.hidden) {
    stopAutoTalk();
  } else if (mpuAutoTalk) {
    startAutoTalk();
  }
});
window.mpuSpaEvents = window.mpuSpaEvents || [
  "moelogContentSwap",
  // Moelog 
  "swup:contentReplaced",
  // Swup.js
  "barba:after",
  // Barba.js
  "turbo:load",
  // Turbo (Hotwire)
  "astro:page-load"
  // Astro View Transitions
];
function mpu_handleSpaNavigation(e) {
  var _a, _b, _c;
  const url = ((_a = e.detail) == null ? void 0 : _a.url) || ((_c = (_b = e.detail) == null ? void 0 : _b.to) == null ? void 0 : _c.url) || window.location.href;
  mpuLogger.log("\u{1F504} SPA \u5C0E\u822A\uFF1A\u9801\u9762\u5167\u5BB9\u5DF2\u66F4\u63DB", url);
  setTimeout(function() {
    if (typeof window.mpuSettings !== "undefined" && window.mpuSettings.ai_enabled === true) {
      const shouldTrigger = mpu_check_page_trigger(
        window.mpuSettings.ai_trigger_pages
      );
      if (shouldTrigger) {
        const probability = parseInt(window.mpuSettings.ai_probability || 10, 10);
        const roll = Math.floor(Math.random() * 100) + 1;
        mpuLogger.log(
          "\u{1F3B2} SPA \u9801\u9762\u611F\u77E5\u6AA2\u67E5\uFF1A\u6A5F\u7387 =",
          probability,
          ", \u9AB0\u5B50 =",
          roll,
          ", \u89F8\u767C =",
          roll <= probability
        );
        if (roll <= probability) {
          if (typeof mpu_chat_context === "function") {
            mpu_chat_context();
          }
        }
      }
    }
  }, 1e3);
}
window.mpuSpaEvents.forEach(function(eventName) {
  document.addEventListener(eventName, mpu_handleSpaNavigation);
});
mpuLogger.log("\u8173\u672C\u8F09\u5165\u5B8C\u6210");
function mpu_check_page_trigger(triggerPages) {
  if (!triggerPages) {
    mpuLogger.log("mpu_check_page_trigger: triggerPages \u70BA\u7A7A\uFF0C\u8FD4\u56DE false");
    return false;
  }
  const conditions = triggerPages.split(",").map((s) => s.trim().toLowerCase());
  const path = window.location.pathname;
  const url = window.location.href;
  mpuLogger.log("mpu_check_page_trigger: \u6AA2\u67E5\u689D\u4EF6 =", conditions, ", path =", path);
  for (let condition of conditions) {
    condition = condition.trim();
    if (!condition) continue;
    if (condition === "is_single") {
      const isRootPath = path === "/" || path === "";
      const normalizedPath = path.endsWith("/") ? path.slice(0, -1) : path;
      const pathParts = normalizedPath.split("/").filter((p) => p.length > 0);
      const firstPathPart = pathParts[0] || "";
      const isUrlEncoded = firstPathPart.includes("%");
      const hasHyphen = firstPathPart.includes("-");
      const isSubdirectoryHome = pathParts.length === 1 && !isUrlEncoded && !hasHyphen && !path.match(/\/\d{4}\/\d{2}\/\d{2}\//) && !path.match(/^\/(category|tag|author|search|archive|feed)/);
      const isHomePage = isRootPath || isSubdirectoryHome;
      const isSpecialPage = path.match(/^\/(category|tag|author|page|search|archive|feed|$)/);
      const isPagination = path.match(/\/page\/\d+/);
      if (isHomePage || isSpecialPage || isPagination) {
        mpuLogger.log("mpu_check_page_trigger: is_single \u6AA2\u67E5 - \u6392\u9664\u9996\u9801/\u6B78\u6A94\u9801\u9762", {
          path,
          isHomePage,
          isSubdirectoryHome,
          isSpecialPage,
          isPagination,
          shouldTrigger: false
        });
        continue;
      }
      const hasDatePath = path.match(/\/\d{4}\/\d{2}\/\d{2}\//);
      const hasArticleContent = document.querySelector("article .entry-title, article .entry-content") || document.querySelector(".hentry .entry-title") || document.querySelector(".single .entry-title");
      const hasContentContainer = document.querySelector("article") || document.querySelector("main") || document.querySelector(".entry-content");
      const shouldTrigger = hasDatePath || hasArticleContent || hasContentContainer;
      mpuLogger.log("mpu_check_page_trigger: is_single \u6AA2\u67E5", {
        path,
        hasDatePath,
        hasArticleContent,
        hasContentContainer,
        shouldTrigger
      });
      if (shouldTrigger) {
        return true;
      }
    } else if (condition === "is_page") {
      const isNotSpecial = path.length > 1 && !path.match(/^\/(category|tag|author|search|archive|feed)/) && !path.match(/\/\d{4}\/\d{2}\/\d{2}\//);
      mpuLogger.log("mpu_check_page_trigger: is_page \u6AA2\u67E5", {
        path,
        pathLength: path.length,
        isNotSpecial,
        shouldTrigger: isNotSpecial
      });
      if (isNotSpecial) {
        return true;
      }
    } else if (condition === "is_home" || condition === "is_front_page") {
      const isRootPath = path === "/" || path === "";
      const normalizedPath = path.endsWith("/") ? path.slice(0, -1) : path;
      const pathParts = normalizedPath.split("/").filter((p) => p.length > 0);
      const isSubdirectoryHome = pathParts.length === 1 && !path.match(/\/\d{4}\/\d{2}\/\d{2}\//) && !path.match(/^\/(category|tag|author|search|archive|feed)/);
      const isHomePagination = path.match(/^(\/[^\/]+)?\/page\/\d+$/);
      const hasMultipleArticles = document.querySelectorAll("article").length > 1;
      const hasSinglePostFeatures = document.querySelector("article.single") || document.querySelector(".single article") || path.match(/\/\d{4}\/\d{2}\/\d{2}\//);
      const isHome = isRootPath || isSubdirectoryHome || isHomePagination || hasMultipleArticles && !hasSinglePostFeatures;
      mpuLogger.log("mpu_check_page_trigger: is_home/is_front_page \u6AA2\u67E5", {
        path,
        normalizedPath,
        pathParts,
        isRootPath,
        isSubdirectoryHome,
        isHomePagination,
        hasMultipleArticles,
        hasSinglePostFeatures,
        isHome
      });
      if (isHome) {
        return true;
      }
    } else if (condition === "is_archive") {
      const isArchive = path.match(/^\/(category|tag|author|date)/);
      mpuLogger.log("mpu_check_page_trigger: is_archive \u6AA2\u67E5", {
        path,
        isArchive
      });
      if (isArchive) {
        return true;
      }
    } else if (condition === "is_category") {
      const isCategory = path.match(/^\/category\//);
      mpuLogger.log("mpu_check_page_trigger: is_category \u6AA2\u67E5", {
        path,
        isCategory
      });
      if (isCategory) {
        return true;
      }
    } else if (condition === "is_tag") {
      const isTag = path.match(/^\/tag\//);
      mpuLogger.log("mpu_check_page_trigger: is_tag \u6AA2\u67E5", {
        path,
        isTag
      });
      if (isTag) {
        return true;
      }
    }
  }
  return false;
}
function mpu_get_page_context() {
  let title = "";
  const titleElement = document.querySelector("article h1.entry-title") || document.querySelector("article h2.entry-title") || document.querySelector("article .entry-title") || document.querySelector(".entry-title") || document.querySelector("article h1:not(.site-title)") || document.querySelector("article h2") || document.querySelector("main h1:not(.site-title)") || document.querySelector("main h2") || document.querySelector("#content h1:not(.site-title)") || document.querySelector("#content h2") || document.querySelector("h1.post-title") || document.querySelector("h2.post-title") || document.querySelector("h1:not(.site-title)");
  if (titleElement && titleElement.textContent) {
    title = titleElement.textContent.trim();
  }
  if (!title) {
    title = document.title;
  }
  let content = "";
  let publishDate = "";
  const article = document.querySelector("article") || document.querySelector("main") || document.querySelector(".entry-content") || document.querySelector("#content");
  if (article) {
    content = article.innerText.replace(/\s+/g, " ").substring(0, 3e3);
  }
  const timeElement = document.querySelector("article time[datetime]") || document.querySelector("time.entry-date[datetime]") || document.querySelector("time.published[datetime]") || document.querySelector("time[datetime]");
  if (timeElement && timeElement.getAttribute("datetime")) {
    publishDate = timeElement.getAttribute("datetime");
  }
  if (!publishDate && timeElement && timeElement.textContent) {
    publishDate = timeElement.textContent.trim();
  }
  if (!publishDate) {
    const metaDate = document.querySelector("meta[property='article:published_time']") || document.querySelector("meta[name='pubdate']") || document.querySelector("meta[name='date']");
    if (metaDate && metaDate.content) {
      publishDate = metaDate.content;
    }
  }
  if (!publishDate) {
    const dateElement = document.querySelector(".entry-date") || document.querySelector(".post-date") || document.querySelector(".date") || document.querySelector(".posted-on");
    if (dateElement && dateElement.textContent) {
      publishDate = dateElement.textContent.trim();
    }
  }
  return { title, content, publishDate };
}
function mpu_chat_context() {
  if (typeof mpuChatModeActive !== "undefined" && mpuChatModeActive) {
    mpuLogger.log("mpu_chat_context: \u5C0D\u8A71\u6A21\u5F0F\u4E2D\uFF0C\u8DF3\u904E\u9801\u9762\u611F\u77E5 AI");
    return;
  }
  if (mpuAiContextInProgress) {
    mpuLogger.log("mpu_chat_context: \u9801\u9762\u611F\u77E5\u9032\u884C\u4E2D\uFF0C\u8DF3\u904E\u65B0\u89F8\u767C");
    return;
  }
  let isDeepSleep = false;
  if (typeof window.mpuInfo !== "undefined" && typeof window.mpuInfo.isDeepSleepTime !== "undefined") {
    isDeepSleep = window.mpuInfo.isDeepSleepTime;
  } else {
    const now = /* @__PURE__ */ new Date();
    const hour = now.getHours();
    isDeepSleep = hour >= 0 && hour < 6;
  }
  if (isDeepSleep) {
    mpuLogger.log("\u{1F319} \u7761\u7720\u6A21\u5F0F\uFF0800:00-06:00\uFF09\uFF1A\u8DF3\u904E\u9801\u9762\u611F\u77E5 AI\uFF0C\u8B93\u89D2\u8272\u597D\u597D\u4F11\u606F");
    return;
  }
  const context = mpu_get_page_context();
  const contentLength = context.content ? context.content.length : 0;
  mpuLogger.log("mpu_chat_context: \u9801\u9762\u4E0A\u4E0B\u6587\u6AA2\u67E5", {
    hasTitle: !!context.title,
    title: context.title,
    contentLength,
    hasContent: !!context.content
  });
  if (!context.title && !context.content) {
    mpuLogger.log("mpu_chat_context: \u6C92\u6709\u6A19\u984C\u548C\u5167\u5BB9\uFF0C\u8DF3\u904E");
    return;
  }
  if (mpuGreetInProgress) {
    mpuLogger.log("mpu_chat_context: \u9996\u6B21\u8A2A\u5BA2\u6253\u62DB\u547C\u9032\u884C\u4E2D\uFF0C\u8DF3\u904E");
    return;
  }
  if (contentLength < 300) {
    mpuLogger.log("mpu_chat_context: \u5167\u5BB9\u9577\u5EA6\u4E0D\u8DB3 300 \u5B57\uFF08\u7576\u524D:", contentLength, "\uFF09\uFF0C\u8DF3\u904E");
    return;
  }
  const wasAutoTalkRunning = mpuAutoTalkTimer !== null;
  if (wasAutoTalkRunning) {
    stopAutoTalk();
  }
  mpuAiContextInProgress = true;
  mpuMessageBlocking = true;
  if (jQuery("#ukagaka_msgbox").is(":hidden")) mpu_showmsg(200);
  let loadingMessage;
  const diaryPrefix = typeof mpuL10n !== "undefined" && mpuL10n.diaryTitlePrefix ? mpuL10n.diaryTitlePrefix : "";
  const isOwnDiary = diaryPrefix && context.title && context.title.includes(diaryPrefix);
  if (isOwnDiary && typeof mpuL10n !== "undefined" && mpuL10n.loadingOwnDiary) {
    loadingMessage = mpuL10n.loadingOwnDiary;
  } else {
    loadingMessage = typeof mpuL10n !== "undefined" && mpuL10n.loadingArticle ? mpuL10n.loadingArticle : "\uFF08\u2026\u3042\u3042\u3001\u8A18\u4E8B\u304B\u3002\u3069\u308C\u3069\u308C\u2026\uFF09";
  }
  mpu_typewriter(
    `<span style="color: ${mpuAiTextColor};">${loadingMessage}</span>`,
    "#ukagaka_msg"
  );
  const formData = new FormData();
  formData.append("action", "mpu_chat_context");
  if (typeof mpuNonce !== "undefined" && mpuNonce) {
    formData.append("mpu_nonce", mpuNonce);
  }
  formData.append("page_title", context.title);
  formData.append("page_content", context.content);
  formData.append("publish_date", context.publishDate || "");
  mpuFetch(mpuurl, {
    method: "POST",
    body: formData,
    cancelPrevious: true,
    requestId: "mpu_chat_context",
    timeout: 6e4,
    retries: 1
  }).then((res) => {
    if (res && res.msg && !res.error) {
      let aiResponse = mpu_unescapeHTML(res.msg);
      aiResponse = mpu_linkifyUrls(aiResponse);
      mpu_typewriter(
        `<span style="color: ${mpuAiTextColor};">${aiResponse}</span>`,
        "#ukagaka_msg"
      );
      if (typeof window.mpuCanvasManager !== "undefined" && window.mpuCanvasManager.isCharacterMode) {
        window.mpuCanvasManager.triggerCharacterAnimation(true);
      }
      if (res.emoji && typeof window.mpuEmojiManager !== "undefined") {
        window.mpuEmojiManager.showEmoji(res.emoji);
      }
      if (typeof mpuChatHistory !== "undefined" && Array.isArray(mpuChatHistory)) {
        mpuChatHistory.push({
          role: "assistant",
          content: res.msg,
          timestamp: Date.now()
        });
        const maxAutoTalkHistory = 3;
        const assistantMessages = mpuChatHistory.filter(
          (msg) => msg.role === "assistant"
        );
        if (assistantMessages.length > maxAutoTalkHistory) {
          let removed = 0;
          const toRemove = assistantMessages.length - maxAutoTalkHistory;
          for (let i = 0; i < mpuChatHistory.length && removed < toRemove; i++) {
            if (mpuChatHistory[i].role === "assistant") {
              mpuChatHistory.splice(i, 1);
              removed++;
              i--;
            }
          }
        }
        if (typeof mpu_saveChatHistory === "function") {
          mpu_saveChatHistory();
          mpuLogger.log("mpu_chat_context: \u5C0D\u8A71\u5DF2\u52A0\u5165\u6B77\u53F2\u4E26\u5132\u5B58");
        }
      }
      if (mpuAiDisplayTimer !== null) {
        clearTimeout(mpuAiDisplayTimer);
        mpuAiDisplayTimer = null;
      }
      mpu_waitForTypewriterComplete(function() {
        const displayDurationMs = mpuAiDisplayDuration * 1e3;
        mpuAiDisplayTimer = setTimeout(function() {
          mpuAiDisplayTimer = null;
          mpuMessageBlocking = false;
          mpuAiContextInProgress = false;
          if (wasAutoTalkRunning && mpuAutoTalk) {
            startAutoTalk();
          }
        }, displayDurationMs);
      });
    } else {
      mpuLogger.warn("AI \u5C0D\u8A71\u5931\u6557\uFF0C\u4F7F\u7528\u9810\u8A2D\u5C0D\u8A71\u7CFB\u7D71:", res);
      const isRateLimit = res && res.error && res.error.includes("\u8ACB\u6C42\u904E\u65BC\u983B\u7E41");
      if (isRateLimit) {
        const rateLimitMessage = typeof mpuL10n !== "undefined" && mpuL10n.apiMagicInsufficient ? mpuL10n.apiMagicInsufficient : "\u2026\u3061\u3087\u3063\u3068\u5F85\u3063\u3066\u3002API\u9B54\u529B\u304C\u8DB3\u308A\u306A\u3044";
        mpu_typewriter(
          `<span style="color: ${mpuAiTextColor};">${rateLimitMessage}</span>`,
          "#ukagaka_msg"
        );
        mpuMessageBlocking = true;
        const waitTime = (mpuAiDisplayDuration || 8) * 1e3;
        setTimeout(function() {
          mpuMessageBlocking = false;
          mpuAiContextInProgress = false;
          if (window.mpuMsgList && Array.isArray(window.mpuMsgList.msg) && window.mpuMsgList.msg.length > 0) {
            const msgArr = window.mpuMsgList.msg;
            const auto = window.mpuMsgList.auto_msg || "";
            const randomIdx = Math.floor(Math.random() * msgArr.length);
            mpu_typewriter(
              mpu_unescapeHTML(msgArr[randomIdx] + auto),
              "#ukagaka_msg"
            );
          }
          if (wasAutoTalkRunning && mpuAutoTalk) {
            startAutoTalk();
          }
        }, waitTime);
      } else {
        mpuMessageBlocking = false;
        if (window.mpuMsgList && Array.isArray(window.mpuMsgList.msg) && window.mpuMsgList.msg.length > 0) {
          const msgArr = window.mpuMsgList.msg;
          const auto = window.mpuMsgList.auto_msg || "";
          const randomIdx = Math.floor(Math.random() * msgArr.length);
          mpu_typewriter(
            mpu_unescapeHTML(msgArr[randomIdx] + auto),
            "#ukagaka_msg"
          );
        }
        mpuAiContextInProgress = false;
        if (wasAutoTalkRunning && mpuAutoTalk) {
          startAutoTalk();
        }
      }
    }
  }).catch((error) => {
    mpu_handle_error(error, "mpu_chat_context", {
      showToUser: false
      // 已經有 fallback 處理，不需要顯示錯誤
    });
    mpuMessageBlocking = false;
    if (window.mpuMsgList && Array.isArray(window.mpuMsgList.msg) && window.mpuMsgList.msg.length > 0) {
      const msgArr = window.mpuMsgList.msg;
      const auto = window.mpuMsgList.auto_msg || "";
      const randomIdx = Math.floor(Math.random() * msgArr.length);
      mpu_typewriter(
        mpu_unescapeHTML(msgArr[randomIdx] + auto),
        "#ukagaka_msg"
      );
    }
    mpuAiContextInProgress = false;
    if (wasAutoTalkRunning && mpuAutoTalk) {
      startAutoTalk();
    }
  });
}
function mpu_test_visitor_info() {
  const visitorParams = new URLSearchParams({ action: "mpu_get_visitor_info" });
  const visitorUrl = `${mpuurl}?${visitorParams.toString()}`;
  mpuFetch(visitorUrl, {
    timeout: 1e4,
    // 10 秒超時
    retries: 1
  }).then((visitorInfo) => {
    mpuLogger.log("\u8A2A\u5BA2\u8CC7\u8A0A:", {
      referrer: visitorInfo.referrer || "\u7121",
      referrer_host: visitorInfo.referrer_host || "\u7121",
      search_engine: visitorInfo.search_engine || "\u7121",
      country: visitorInfo.slimstat_country || "\u7121",
      city: visitorInfo.slimstat_city || "\u7121"
    });
  }).catch((error) => {
    mpu_handle_error(error, "mpu_test_visitor_info");
  });
}
function mpu_greet_first_visitor(settings) {
  return new Promise((resolve, reject) => {
    let isDeepSleep = false;
    if (typeof window.mpuInfo !== "undefined" && typeof window.mpuInfo.isDeepSleepTime !== "undefined") {
      isDeepSleep = window.mpuInfo.isDeepSleepTime;
    } else {
      const now = /* @__PURE__ */ new Date();
      const hour = now.getHours();
      isDeepSleep = hour >= 0 && hour < 6;
    }
    if (isDeepSleep) {
      mpuLogger.log("\u{1F319} \u7761\u7720\u6A21\u5F0F\uFF1A\u8DF3\u904E\u521D\u6B21\u8A2A\u5BA2\u6253\u62DB\u547C\uFF0C\u8B93\u89D2\u8272\u597D\u597D\u4F11\u606F");
      resolve();
      return;
    }
    const wasAutoTalkRunning = mpuAutoTalkTimer !== null;
    if (wasAutoTalkRunning) {
      stopAutoTalk();
    }
    const visitorParams = new URLSearchParams({
      action: "mpu_get_visitor_info"
    });
    const visitorUrl = `${mpuurl}?${visitorParams.toString()}`;
    mpuFetch(visitorUrl, {
      timeout: 1e4,
      // 10 秒超時
      retries: 1
    }).then((visitorInfo) => {
      mpuLogger.log("\u8A2A\u5BA2\u8CC7\u8A0A:", {
        referrer: visitorInfo.referrer || "\u7121",
        referrer_host: visitorInfo.referrer_host || "\u7121",
        search_engine: visitorInfo.search_engine || "\u7121",
        country: visitorInfo.slimstat_country || "\u7121"
      });
      if (jQuery("#ukagaka_msgbox").is(":hidden")) mpu_showmsg(200);
      const loadingMessage = typeof mpuL10n !== "undefined" && mpuL10n.unknownVisitor ? mpuL10n.unknownVisitor : "\uFF08\u2026\u3042\u3001\u77E5\u3089\u306A\u3044\u4EBA\u9593\u3060\u2026\uFF09";
      mpu_typewriter(
        `<span style="color: ${mpuAiTextColor};">${loadingMessage}</span>`,
        "#ukagaka_msg"
      );
      const formData = new FormData();
      formData.append("action", "mpu_chat_greet");
      if (typeof mpuNonce !== "undefined" && mpuNonce) {
        formData.append("mpu_nonce", mpuNonce);
      }
      formData.append("referrer", visitorInfo.referrer || "");
      formData.append("referrer_host", visitorInfo.referrer_host || "");
      formData.append("search_engine", visitorInfo.search_engine || "");
      formData.append(
        "is_direct",
        visitorInfo.is_direct === true ? "true" : "false"
      );
      formData.append(
        "country",
        visitorInfo.slimstat_country || visitorInfo.country || ""
      );
      formData.append(
        "city",
        visitorInfo.slimstat_city || visitorInfo.city || ""
      );
      return mpuFetch(mpuurl, {
        method: "POST",
        body: formData,
        cancelPrevious: true,
        requestId: "mpu_chat_greet",
        timeout: 6e4,
        retries: 1
      });
    }).then((res) => {
      if (res && res.msg && !res.error) {
        let greetingMessage = mpu_unescapeHTML(res.msg);
        greetingMessage = mpu_linkifyUrls(greetingMessage);
        mpu_typewriter(
          `<span style="color: ${mpuAiTextColor};">${greetingMessage}</span>`,
          "#ukagaka_msg"
        );
        if (typeof window.mpuCanvasManager !== "undefined" && window.mpuCanvasManager.isCharacterMode) {
          window.mpuCanvasManager.triggerCharacterAnimation(true);
        }
        if (res.emoji && typeof window.mpuEmojiManager !== "undefined") {
          window.mpuEmojiManager.showEmoji(res.emoji);
        }
        if (typeof mpuChatHistory !== "undefined" && Array.isArray(mpuChatHistory)) {
          mpuChatHistory.push({
            role: "assistant",
            content: res.msg,
            timestamp: Date.now()
          });
          const maxAutoTalkHistory = 3;
          const assistantMessages = mpuChatHistory.filter(
            (msg) => msg.role === "assistant"
          );
          if (assistantMessages.length > maxAutoTalkHistory) {
            let removed = 0;
            const toRemove = assistantMessages.length - maxAutoTalkHistory;
            for (let i = 0; i < mpuChatHistory.length && removed < toRemove; i++) {
              if (mpuChatHistory[i].role === "assistant") {
                mpuChatHistory.splice(i, 1);
                removed++;
                i--;
              }
            }
          }
          if (typeof mpu_saveChatHistory === "function") {
            mpu_saveChatHistory();
            mpuLogger.log("mpu_greet_first_visitor: \u554F\u5019\u5DF2\u52A0\u5165\u6B77\u53F2\u4E26\u5132\u5B58");
          } else {
            mpuLogger.warn(
              "mpu_greet_first_visitor: mpu_saveChatHistory \u51FD\u6578\u4E0D\u5B58\u5728\uFF0C\u7121\u6CD5\u5132\u5B58\u5C0D\u8A71\u6B77\u53F2"
            );
          }
        } else {
          mpuLogger.warn(
            "mpu_greet_first_visitor: mpuChatHistory \u672A\u521D\u59CB\u5316\u6216\u4E0D\u662F\u9663\u5217\uFF0C\u7121\u6CD5\u52A0\u5165\u5C0D\u8A71\u6B77\u53F2"
          );
        }
        if (mpuAiDisplayTimer !== null) {
          clearTimeout(mpuAiDisplayTimer);
          mpuAiDisplayTimer = null;
        }
        mpu_waitForTypewriterComplete(function() {
          const displayDurationMs = mpuAiDisplayDuration * 1e3;
          mpuAiDisplayTimer = setTimeout(function() {
            mpuAiDisplayTimer = null;
            if (wasAutoTalkRunning && settings.auto_talk === true && mpuAutoTalk) {
              startAutoTalk();
            }
            resolve();
          }, displayDurationMs);
        });
      } else {
        mpuLogger.warn("\u9996\u6B21\u8A2A\u5BA2\u6253\u62DB\u547C\u5931\u6557:", res);
        const isRateLimit = res && res.error && res.error.includes("\u8ACB\u6C42\u904E\u65BC\u983B\u7E41");
        if (isRateLimit) {
          const rateLimitMessage = typeof mpuL10n !== "undefined" && mpuL10n.apiMagicInsufficient ? mpuL10n.apiMagicInsufficient : "\u2026\u3061\u3087\u3063\u3068\u5F85\u3063\u3066\u3002API\u9B54\u529B\u304C\u8DB3\u308A\u306A\u3044";
          mpu_typewriter(
            `<span style="color: ${mpuAiTextColor};">${rateLimitMessage}</span>`,
            "#ukagaka_msg"
          );
          mpuMessageBlocking = true;
          const waitTime = (mpuAiDisplayDuration || 8) * 1e3;
          setTimeout(function() {
            mpuMessageBlocking = false;
            if (window.mpuMsgList && Array.isArray(window.mpuMsgList.msg) && window.mpuMsgList.msg.length > 0) {
              const msgArr = window.mpuMsgList.msg;
              const auto = window.mpuMsgList.auto_msg || "";
              const randomIdx = Math.floor(Math.random() * msgArr.length);
              mpu_typewriter(
                mpu_unescapeHTML(msgArr[randomIdx] + auto),
                "#ukagaka_msg"
              );
            }
            if (wasAutoTalkRunning && settings.auto_talk === true && mpuAutoTalk) {
              startAutoTalk();
            }
            resolve();
          }, waitTime);
        } else {
          if (window.mpuMsgList && Array.isArray(window.mpuMsgList.msg) && window.mpuMsgList.msg.length > 0) {
            const msgArr = window.mpuMsgList.msg;
            const auto = window.mpuMsgList.auto_msg || "";
            const randomIdx = Math.floor(Math.random() * msgArr.length);
            mpu_typewriter(
              mpu_unescapeHTML(msgArr[randomIdx] + auto),
              "#ukagaka_msg"
            );
          }
          if (wasAutoTalkRunning && settings.auto_talk === true && mpuAutoTalk) {
            startAutoTalk();
          }
          resolve();
        }
      }
    }).catch((error) => {
      mpu_handle_error(error, "mpu_greet_first_visitor", {
        showToUser: false
      });
      if (window.mpuMsgList && Array.isArray(window.mpuMsgList.msg) && window.mpuMsgList.msg.length > 0) {
        const msgArr = window.mpuMsgList.msg;
        const auto = window.mpuMsgList.auto_msg || "";
        const randomIdx = Math.floor(Math.random() * msgArr.length);
        mpu_typewriter(
          mpu_unescapeHTML(msgArr[randomIdx] + auto),
          "#ukagaka_msg"
        );
      }
      if (wasAutoTalkRunning && settings.auto_talk === true && mpuAutoTalk) {
        startAutoTalk();
      }
      resolve();
    });
  });
}
function loadExternalDialog(file, skipFirstMessage = false) {
  const pure = (file || "").replace(/^.*[\\/]/, "");
  const params = new URLSearchParams({
    action: "mpu_load_dialog",
    file: pure
  });
  const url = `${mpuurl}?${params.toString()}`;
  document.body.style.cursor = "wait";
  if (jQuery("#ukagaka_msgbox").is(":hidden")) mpu_showmsg(200);
  const msgElement = jQuery("#ukagaka_msg");
  const currentMsg = msgElement.text().trim();
  const initialMsg = msgElement.attr("data-initial-msg");
  const hasShownInitialMsg = initialMsg && currentMsg.indexOf(initialMsg) !== -1;
  let isDeepSleep = false;
  if (typeof window.mpuInfo !== "undefined" && typeof window.mpuInfo.isDeepSleepTime !== "undefined") {
    isDeepSleep = window.mpuInfo.isDeepSleepTime;
  } else {
    const now = /* @__PURE__ */ new Date();
    const hour = now.getHours();
    isDeepSleep = hour >= 0 && hour < 6;
  }
  const isSleepMessage = initialMsg && initialMsg.includes("<!-- mpu-sleep -->");
  if (!hasShownInitialMsg) {
    if (isDeepSleep && isSleepMessage) {
      mpuLogger.log("\u{1F319} \u7761\u7720\u6A21\u5F0F\uFF1A\u6AA2\u6E2C\u5230\u7761\u7720\u8A0A\u606F\uFF0C\u8DF3\u904E\u8F09\u5165\u8A0A\u606F\u986F\u793A");
    } else {
      const loadingMessage = typeof mpuL10n !== "undefined" && mpuL10n.thinkingMessage ? mpuL10n.thinkingMessage : "\uFF08\u3048\u3063\u3068\u2026\u4F55\u8A71\u305B\u3070\u3044\u3044\u304B\u306A\u2026\uFF09";
      mpu_typewriter(loadingMessage, "#ukagaka_msg");
    }
  }
  mpuFetch(url, {
    cancelPrevious: true,
    requestId: `loadExternalDialog_${pure}`,
    timeout: 15e3,
    retries: 1
  }).then((resp) => {
    if (typeof resp !== "object") {
      throw new Error(resp.error || "Expected JSON response from server.");
    }
    if (resp && !resp.error && Array.isArray(resp.msg)) {
      if (resp.msg.length === 0) {
        mpuLogger.warn("loadExternalDialog: \u5C0D\u8A71\u6587\u4EF6\u70BA\u7A7A");
        window.mpuMsgList = {
          msg: [],
          auto_msg: resp.auto_msg || "",
          next_msg: resp.next_msg || 0,
          default_msg: resp.default_msg || 0
        };
        if (skipFirstMessage) {
          mpuLogger.log("loadExternalDialog: LLM \u53D6\u4EE3\u5C0D\u8A71\u6A21\u5F0F\uFF0C\u5C0D\u8A71\u6587\u4EF6\u70BA\u7A7A\uFF0C\u5C07\u4F9D\u8CF4 LLM \u751F\u6210");
          jQuery("#ukagaka").stop(true, true).fadeIn(200);
          document.body.style.cursor = "auto";
          return;
        }
        mpu_typewriter("\u5C0D\u8A71\u6587\u4EF6\u70BA\u7A7A\uFF0C\u8ACB\u6AA2\u67E5\u5C0D\u8A71\u6587\u4EF6\u5167\u5BB9", "#ukagaka_msg");
        mpu_showmsg(400);
        jQuery("#ukagaka").stop(true, true).fadeIn(200);
        document.body.style.cursor = "auto";
        return;
      }
      try {
        window.mpuMsgList = resp;
        mpuNextMode = resp.next_msg == 1 ? "random" : "sequential";
        mpuDefaultMsg = resp.default_msg == 1 ? 1 : 0;
        if (skipFirstMessage) {
          mpuLogger.log("loadExternalDialog: LLM \u53D6\u4EE3\u5C0D\u8A71\u6A21\u5F0F\uFF0C\u5DF2\u8F09\u5165\u5F8C\u5099\u5C0D\u8A71\u6578\u64DA\uFF0C\u4F46\u4E0D\u986F\u793A\u7B2C\u4E00\u53E5");
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
        const showFirstMessage = function() {
          if (firstMessageTimer !== null) {
            clearTimeout(firstMessageTimer);
            firstMessageTimer = null;
          }
          if (firstMessageShown) {
            mpuLogger.log("loadExternalDialog: \u5617\u8A66\u91CD\u8907\u986F\u793A\u7B2C\u4E00\u53E5\u5C0D\u8A71\uFF0C\u5DF2\u963B\u6B62");
            return;
          }
          if (typeof mpu_isUnawokenSleepMode === "function" && mpu_isUnawokenSleepMode()) {
            mpuLogger.log("\u{1F319} \u7761\u7720\u6A21\u5F0F\u4E14\u5C1A\u672A\u88AB\u559A\u9192\uFF1A\u8DF3\u904E\u7B2C\u4E00\u53E5\u5167\u5EFA\u5C0D\u8A71\uFF0C\u4FDD\u6301\u7761\u7720\u8A0A\u606F");
            firstMessageShown = true;
            return;
          }
          firstMessageShown = true;
          let first = 0;
          if (mpuDefaultMsg === 0 && resp.msg.length) {
            first = Math.floor(Math.random() * resp.msg.length);
          }
          mpu_typewriter(
            mpu_unescapeHTML(resp.msg[first] + (resp.auto_msg || "")),
            "#ukagaka_msg"
          );
          jQuery("#ukagaka_msgnum").html(first);
          if (mpuAutoTalk) {
            mpu_waitForTypewriterComplete(function() {
              startAutoTalk();
            });
          }
        };
        mpu_waitForTypewriterComplete(function() {
          if (!firstMessageShown) {
            firstMessageTimer = setTimeout(showFirstMessage, 1e3);
          }
        });
      } catch (e) {
        mpu_handle_error(e, "loadExternalDialog:process_data", {
          showToUser: true,
          userMessage: debugMode || window.mpuDebugMode ? `\u8655\u7406\u5C0D\u8A71\u6578\u64DA\u6642\u51FA\u932F\uFF1A${e.message}` : "\u8655\u7406\u5C0D\u8A71\u6578\u64DA\u6642\u51FA\u932F\uFF0C\u8ACB\u7A0D\u5F8C\u518D\u8A66\u3002"
        });
      }
    } else {
      const errorMsg = resp && resp.error ? resp.error : "\u7121\u6CD5\u53D6\u5F97\u5C0D\u8A71\u8CC7\u6599";
      jQuery("#ukagaka_msg").html(errorMsg);
      if (!window.mpuMsgList) {
        window.mpuMsgList = {
          msg: [],
          auto_msg: "",
          next_msg: 0,
          default_msg: 0
        };
        mpuLogger.warn("loadExternalDialog: \u5F8C\u7AEF\u8FD4\u56DE\u932F\u8AA4\uFF0C\u8A2D\u7F6E\u7A7A\u7684 mpuMsgList \u4F5C\u70BA\u5F8C\u5099 -", errorMsg);
      }
    }
    jQuery("#ukagaka").stop(true, true).fadeIn(200);
    document.body.style.cursor = "auto";
  }).catch((error) => {
    mpu_handle_error(error, "loadExternalDialog", {
      showToUser: true,
      userMessage: debugMode || window.mpuDebugMode ? `\u8F09\u5165\u5C0D\u8A71\u6587\u4EF6\u5931\u6557\uFF1A${error.message}` : "\u8F09\u5165\u5C0D\u8A71\u6587\u4EF6\u5931\u6557\uFF0C\u8ACB\u7A0D\u5F8C\u518D\u8A66\u3002"
    });
    if (!window.mpuMsgList) {
      window.mpuMsgList = {
        msg: [],
        auto_msg: "",
        next_msg: 0,
        default_msg: 0
      };
      mpuLogger.warn("loadExternalDialog: \u8F09\u5165\u5931\u6557\uFF0C\u8A2D\u7F6E\u7A7A\u7684 mpuMsgList \u4F5C\u70BA\u5F8C\u5099");
    }
    jQuery("#ukagaka").stop(true, true).fadeIn(200);
    document.body.style.cursor = "auto";
  });
}
let mpuChatModeActive = false;
let mpuChatHistory = [];
let mpuChatRequesting = false;
let mpuEnableChatMode = false;
const MPU_CHAT_HISTORY_KEY = "mpu_chat_history";
const MPU_MAX_CHAT_HISTORY = 20;
function mpu_loadChatHistory() {
  const stored = mpu_getLocal(MPU_CHAT_HISTORY_KEY);
  if (stored && Array.isArray(stored)) {
    mpuChatHistory = stored.slice(-MPU_MAX_CHAT_HISTORY);
    return true;
  }
  mpuChatHistory = [];
  return false;
}
function mpu_saveChatHistory() {
  const toSave = mpuChatHistory.slice(-MPU_MAX_CHAT_HISTORY);
  mpu_setLocal(MPU_CHAT_HISTORY_KEY, toSave);
}
function mpu_clearChatHistory() {
  mpuChatHistory = [];
  mpu_delLocal(MPU_CHAT_HISTORY_KEY);
}
function mpu_toggleChatMode(enable) {
  const $msgbox = jQuery("#ukagaka_msgbox");
  const $chatInput = jQuery("#ukagaka_chat_input");
  const $input = jQuery("#mpu_user_input");
  if (typeof enable === "undefined") {
    enable = !mpuChatModeActive;
  }
  mpuChatModeActive = enable;
  if (enable) {
    let showWelcome2 = function() {
      const welcomeMsg = typeof mpuL10n !== "undefined" && mpuL10n.chatWelcome ? mpuL10n.chatWelcome : "\u6709\u4EC0\u9EBC\u60F3\u804A\u7684\u55CE\uFF1F";
      mpu_typewriter(welcomeMsg, "#ukagaka_msg", null, true);
      if ($msgbox.is(":hidden")) {
        mpu_showmsg(400);
      }
      setTimeout(() => $input.focus(), 250);
    };
    var showWelcome = showWelcome2;
    mpuLogger.log("\u9032\u5165\u4E92\u52D5\u5C0D\u8A71\u6A21\u5F0F");
    if (mpuAutoTalkTimer !== null) {
      stopAutoTalk();
    }
    mpu_loadChatHistory();
    if (typeof window.mpuCanvasManager !== "undefined" && window.mpuCanvasManager.isCharacterMode) {
      const needsWakeUp = typeof mpu_isUnawokenSleepMode === "function" && mpu_isUnawokenSleepMode();
      if (needsWakeUp) {
        const hasWakeUpAnimation = typeof window.mpuCanvasManager !== "undefined" && typeof window.mpuCanvasManager.hasWakeUpAnimation === "function" && window.mpuCanvasManager.hasWakeUpAnimation();
        if (hasWakeUpAnimation) {
          $msgbox.fadeOut(1e3, function() {
            window.mpuCanvasManager.triggerCharacterAnimation(true, function() {
              $msgbox.addClass("chat-mode");
              $chatInput.slideDown(400, function() {
                showWelcome2();
              });
            }, true);
          });
        } else {
          $msgbox.addClass("chat-mode");
          $chatInput.slideDown(400, function() {
            showWelcome2();
          });
        }
      } else {
        $chatInput.slideDown(400);
        $msgbox.addClass("chat-mode");
        showWelcome2();
      }
    } else {
      $chatInput.slideDown(400);
      $msgbox.addClass("chat-mode");
      showWelcome2();
    }
  } else {
    mpuLogger.log("\u9000\u51FA\u4E92\u52D5\u5C0D\u8A71\u6A21\u5F0F");
    $chatInput.slideUp(400);
    $msgbox.removeClass("chat-mode");
    mpuMessageBlocking = true;
    const exitMsg = typeof mpuL10n !== "undefined" && mpuL10n.chatExit ? mpuL10n.chatExit : "\u2026\u2026";
    mpu_typewriter(exitMsg, "#ukagaka_msg", null, true);
    setTimeout(() => {
      if (!mpuChatModeActive) {
        mpuMessageBlocking = false;
        if (window.mpuMsgList && Array.isArray(window.mpuMsgList.msg) && window.mpuMsgList.msg.length > 0) {
          const msgArr = window.mpuMsgList.msg;
          const auto = window.mpuMsgList.auto_msg || "";
          const randomIdx = Math.floor(Math.random() * msgArr.length);
          mpu_typewriter(mpu_unescapeHTML(msgArr[randomIdx] + auto), "#ukagaka_msg");
        }
        if (mpuAutoTalk) {
          startAutoTalk();
        }
      }
    }, 5e3);
  }
}
function mpu_parseMarkdown(text) {
  if (!text || typeof text !== "string") return text;
  return text.replace(/\*\*(.+?)\*\*/g, "<strong>$1</strong>").replace(/__(.+?)__/g, "<strong>$1</strong>").replace(/\*([^*]+)\*/g, "<em>$1</em>").replace(/\b_([^_]+)_\b/g, "<em>$1</em>").replace(/`([^`]+)`/g, '<code style="background:#f0f0f0;padding:1px 4px;border-radius:3px;font-family:monospace;font-size:0.9em;">$1</code>');
}
function mpu_sendUserMessage() {
  const $input = jQuery("#mpu_user_input");
  const message = $input.val().trim();
  if (!message || mpuChatRequesting) {
    if (mpuChatRequesting) {
      mpuLogger.log("\u6B63\u5728\u7B49\u5F85\u56DE\u61C9\uFF0C\u8ACB\u7A0D\u5019");
    }
    return;
  }
  if (message === "/reset" || message === "/clear") {
    mpu_clearChatHistory();
    $input.val("");
    mpu_typewriter("\uFF08\u8A18\u61B6\u3092\u6D88\u53BB\u3057\u307E\u3057\u305F...\uFF09\u5C0D\u8A71\u6B77\u53F2\u5DF2\u6E05\u9664\u3002", "#ukagaka_msg");
    mpuLogger.log("\u5C0D\u8A71\u6B77\u53F2\u5DF2\u6E05\u9664");
    return;
  }
  if (message === "/help") {
    $input.val("");
    const helpText = "\u3010\u53EF\u7528\u6307\u4EE4\u3011\n/reset - \u6E05\u9664\u5C0D\u8A71\u6B77\u53F2\n/clear - \u540C\u4E0A\n/help - \u986F\u793A\u6B64\u8AAA\u660E";
    mpu_typewriter(helpText, "#ukagaka_msg");
    return;
  }
  mpuLogger.log("\u767C\u9001\u7528\u6236\u8A0A\u606F:", message);
  $input.val("").prop("disabled", true);
  mpuChatRequesting = true;
  mpuChatHistory.push({
    role: "user",
    content: message,
    timestamp: Date.now()
  });
  jQuery("#ukagaka_msg").html('\uFF08\u2026\u3048\u3063\u3068<span class="mpu-thinking"></span>\uFF09');
  const pageContext = mpu_get_page_context();
  const formData = new FormData();
  formData.append("action", "mpu_user_chat");
  if (typeof mpuNonce !== "undefined" && mpuNonce) {
    formData.append("mpu_nonce", mpuNonce);
  }
  formData.append("message", message);
  formData.append("history", JSON.stringify(mpuChatHistory.slice(-10)));
  formData.append("page_title", pageContext.title || "");
  formData.append("page_content", (pageContext.content || "").substring(0, 2e3));
  mpuFetch(mpuurl, {
    method: "POST",
    body: formData,
    timeout: 6e4,
    retries: 1,
    requestId: "mpu_user_chat",
    cancelPrevious: true
  }).then((res) => {
    if (!mpuChatModeActive) {
      mpuLogger.log("\u5C0D\u8A71\u6A21\u5F0F\u5DF2\u95DC\u9589\uFF0C\u6368\u68C4\u672C\u6B21 AI \u56DE\u61C9");
      return;
    }
    if (res && res.msg && !res.error) {
      const aiResponse = res.msg;
      mpuChatHistory.push({
        role: "assistant",
        content: aiResponse,
        timestamp: Date.now()
      });
      mpu_saveChatHistory();
      mpu_typewriter(mpu_parseMarkdown(aiResponse), "#ukagaka_msg");
      if (typeof window.mpuCanvasManager !== "undefined" && window.mpuCanvasManager.isCharacterMode) {
        window.mpuCanvasManager.triggerCharacterAnimation(true);
      }
      if (res.emoji && typeof window.mpuEmojiManager !== "undefined") {
        window.mpuEmojiManager.showEmoji(res.emoji);
      }
    } else {
      const errorMsg = res && res.error ? res.error : "\u62B1\u6B49\uFF0C\u7121\u6CD5\u53D6\u5F97\u56DE\u61C9";
      mpu_typewriter(errorMsg, "#ukagaka_msg");
    }
  }).catch((error) => {
    if (!mpuChatModeActive) {
      mpuLogger.log("\u5C0D\u8A71\u6A21\u5F0F\u5DF2\u95DC\u9589\uFF0C\u6368\u68C4\u932F\u8AA4\u8A0A\u606F");
      return;
    }
    mpu_handle_error(error, "mpu_sendUserMessage", {
      showToUser: false
    });
    mpu_typewriter("\uFF08\u2026\u9023\u7DDA\u597D\u50CF\u6709\u9EDE\u554F\u984C\u2026\uFF09", "#ukagaka_msg");
  }).finally(() => {
    mpuChatRequesting = false;
    $input.prop("disabled", false);
    if (mpuChatModeActive) {
      $input.focus();
    }
  });
}
function mpu_escapeHTML(str) {
  if (!str) return "";
  return str.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
}
jQuery(document).ready(function() {
  if (typeof mpu_loadChatHistory === "function") {
    mpu_loadChatHistory();
    mpuLogger.log("\u9801\u9762\u8F09\u5165\uFF1A\u5C0D\u8A71\u6B77\u53F2\u5DF2\u8F09\u5165\uFF0C\u7576\u524D\u8A18\u9304\u6578:", mpuChatHistory.length);
  }
  jQuery("#mpu_chat_toggle").on("click", function(e) {
    e.preventDefault();
    if (mpuEnableChatMode) {
      mpu_toggleChatMode();
    } else {
      if (typeof mpuChange === "function") {
        mpuChange("");
      }
    }
  });
  jQuery("#mpu_user_input").on("keypress", function(e) {
    if (e.which === 13 && !e.shiftKey) {
      e.preventDefault();
      mpu_sendUserMessage();
    }
  });
  jQuery("#mpu_ok_btn").on("click", function(e) {
    e.preventDefault();
    if (typeof window.mpuCanvasManager !== "undefined" && window.mpuCanvasManager.decorationChatInProgress) {
      mpuLogger.log("\u88DD\u98FE\u7269\u5C0D\u8A71\u9032\u884C\u4E2D\uFF0C\u5FFD\u7565\u6309\u9215\u9EDE\u64CA");
      return;
    }
    if (mpuMessageBlocking) {
      mpuLogger.log("\u8A0A\u606F\u88AB\u963B\u64CB\uFF0C\u5FFD\u7565\u6309\u9215\u9EDE\u64CA");
      return;
    }
    if (typeof window.mpuInfo !== "undefined" && window.mpuInfo.isDeepSleepTime) {
      mpuLogger.log("\u{1F305} \u559A\u9192\u89D2\u8272\uFF01\u6B63\u5728\u8A18\u9304 IP...");
      var wakeParams = new URLSearchParams({ action: "mpu_wake_ghost" });
      if (typeof mpuNonce !== "undefined") {
        wakeParams.append("mpu_nonce", mpuNonce);
      }
      var wakeUrl = mpuurl + "?" + wakeParams.toString();
      mpuFetch(wakeUrl, { timeout: 5e3 }).then(function(res) {
        mpuLogger.log("\u559A\u9192\u6210\u529F:", res);
        window.mpuInfo.isDeepSleepTime = false;
      }).catch(function(err) {
        mpuLogger.warn("\u559A\u9192\u8ACB\u6C42\u5931\u6557\uFF0C\u4F46\u4E0D\u5F71\u97FF\u6B63\u5E38\u64CD\u4F5C:", err);
      });
    }
    if (mpuChatModeActive) {
      mpu_sendUserMessage();
    } else {
      mpu_nextmsg("");
    }
  });
  jQuery("#mpu_cancel_btn").on("click", function(e) {
    e.preventDefault();
    if (typeof window.mpuCanvasManager !== "undefined" && window.mpuCanvasManager.decorationChatInProgress) {
      mpuLogger.log("\u88DD\u98FE\u7269\u5C0D\u8A71\u9032\u884C\u4E2D\uFF0C\u5FFD\u7565\u6309\u9215\u9EDE\u64CA");
      return;
    }
    if (mpuMessageBlocking) {
      mpuLogger.log("\u8A0A\u606F\u88AB\u963B\u64CB\uFF0C\u5FFD\u7565\u6309\u9215\u9EDE\u64CA");
      return;
    }
    if (mpuChatModeActive) {
      mpu_toggleChatMode(false);
      mpuLogger.log("\u9000\u51FA\u5C0D\u8A71\u6A21\u5F0F");
    } else {
      mpu_hidemsg("");
    }
  });
  mpuLogger.log("\u4E92\u52D5\u5C0D\u8A71\u6A21\u5F0F\u5DF2\u521D\u59CB\u5316");
});
(function($) {
  var textarea, staticOffset;
  var iLastMousePos = 0;
  var iMin = 32;
  var grip;
  $.fn.TextAreaResizer = function() {
    return this.each(function() {
      textarea = $(this).addClass(
        "processed"
      );
      staticOffset = null;
      $(this).wrap(
        '<div class="resizable-textarea"><span></span></div>'
      ).parent().append(
        $('<div class="grippie"></div>').bind(
          "mousedown",
          {
            el: this
          },
          startDrag
        )
        // 添加拖曳柄並綁定 mousedown 事件
      );
      var grippie = $("div.grippie", $(this).parent())[0];
      grippie.style.marginRight = grippie.offsetWidth - $(this)[0].offsetWidth + "px";
    });
  };
  function startDrag(e) {
    textarea = $(e.data.el);
    textarea.blur();
    iLastMousePos = mousePosition(e).y;
    staticOffset = textarea.height() - iLastMousePos;
    textarea.css("opacity", 0.25);
    $(document).mousemove(performDrag).mouseup(endDrag);
    return false;
  }
  function performDrag(e) {
    var iThisMousePos = mousePosition(e).y;
    var iMousePos = staticOffset + iThisMousePos;
    if (iLastMousePos >= iThisMousePos) {
      iMousePos -= 5;
    }
    iLastMousePos = iThisMousePos;
    iMousePos = Math.max(iMin, iMousePos);
    textarea.height(iMousePos + "px");
    if (iMousePos < iMin) {
      endDrag(e);
    }
    return false;
  }
  function endDrag(e) {
    $(document).unbind("mousemove", performDrag).unbind("mouseup", endDrag);
    textarea.css("opacity", 1);
    textarea.focus();
    textarea = null;
    staticOffset = null;
    iLastMousePos = 0;
  }
  function mousePosition(e) {
    return {
      x: e.clientX + document.documentElement.scrollLeft,
      // X 座標（考慮滾動偏移）
      y: e.clientY + document.documentElement.scrollTop
      // Y 座標（考慮滾動偏移）
    };
  }
})(jQuery);
