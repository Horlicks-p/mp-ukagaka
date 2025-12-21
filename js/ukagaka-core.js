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
    jQuery("#ukagaka_msgbox").fadeOut(speed);
}

/**
 * 在顯示訊息前，確保春菜可見且訊息框隱藏
 * @param {number} speed - 動畫速度（毫秒），預設 400
 */
function mpu_beforemsg(speed = 400) {
    if (jQuery("#ukagaka").is(":hidden")) {
        mpu_showrobot(speed);
    }
    else if (!jQuery("#ukagaka_msgbox").is(":hidden")) {
        mpu_hidemsg(speed);
    }
}

// ====== 自動對話 ======
/**
 * 啟動自動對話計時器
 */
function startAutoTalk() {
    mpuLogger.log('startAutoTalk 被調用, mpuAutoTalk =', mpuAutoTalk, ', mpuAutoTalkInterval =', mpuAutoTalkInterval);
    stopAutoTalk();
    if (!mpuAutoTalk) {
        mpuLogger.log('startAutoTalk: mpuAutoTalk 為 false，退出');
        return;
    }

    if (jQuery('#ukagaka_msgbox').is(':hidden')) mpu_showmsg(400);

    mpuLogger.log('startAutoTalk: 設置計時器，間隔 =', mpuAutoTalkInterval, 'ms');
    mpuAutoTalkTimer = setInterval(function () {
        mpuLogger.log('自動對話計時器觸發, mpuAutoTalk =', mpuAutoTalk, ', mpuOllamaReplaceDialogue =', mpuOllamaReplaceDialogue);
        
        // 閒置檢查：如果用戶閒置超過閾值，跳過本次自動對話
        const now = Date.now();
        const idleTime = now - mpuLastUserActionTime;
        if (idleTime > mpuIdleThreshold) {
            mpuLogger.log('使用者閒置中（', Math.floor(idleTime / 1000), '秒），跳過本次自動對話');
            return; // 直接跳過，不發送請求
        }
        
        if (mpuAutoTalk) mpu_nextmsg('auto');
        else stopAutoTalk();
    }, mpuAutoTalkInterval);
}

/**
 * 停止自動對話計時器
 */
function stopAutoTalk() {
    if (mpuAutoTalkTimer !== null) {
        clearInterval(mpuAutoTalkTimer);
        mpuAutoTalkTimer = null;
    }
}

/**
 * 更新自動對話按鈕的 UI 狀態
 */
function setAutoTalkUI() {
    const $btn = jQuery('#toggleAutoTalk');
    if ($btn.length) $btn.text(mpuAutoTalk ? '停止自動對話' : '開始自動對話');
}

// ====== 指令處理 ======
/**
 * 處理春菜指令
 * @param {string} command - 指令字串，例如 "(:next)"、"(:showmsg)" 等
 * @returns {boolean} 是否成功處理指令
 */
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

    // (:msg[n])
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

    // 直接發話（會附 auto_msg）
    if (window.mpuMsgList) {
        const auto = window.mpuMsgList.auto_msg || "";
        mpu_beforemsg(400);
        mpu_showmsg(400);
        setTimeout(() => {
            mpu_typewriter(mpu_unescapeHTML(command + auto), "#ukagaka_msg");
        }, 510);
    }
}

// ====== 下一句對話 ======

function mpu_processOllamaQueue() {
    if (mpuOllamaRequestQueue.length === 0) {
        mpuLogger.log('mpu_processOllamaQueue: 佇列為空');
        return;
    }
    
    setTimeout(function() {
        const nextTrigger = mpuOllamaRequestQueue.shift();
        mpuLogger.log('mpu_processOllamaQueue: 處理佇列中的請求, trigger =', nextTrigger, ', 剩餘佇列長度 =', mpuOllamaRequestQueue.length);
        mpu_nextmsg(nextTrigger);
    }, mpuOllamaQueueDelay);
}

/**
 * 顯示下一句對話
 * @param {string} trigger - 觸發方式：'auto'（自動）、'startup'（啟動）、undefined（手動）
 */
function mpu_nextmsg(trigger) {
    const isAuto = (trigger === 'auto');
    const isStartup = (trigger === 'startup');
    mpuLogger.log('mpu_nextmsg 被調用, trigger =', trigger, ', isAuto =', isAuto, ', isStartup =', isStartup, ', mpuOllamaReplaceDialogue =', mpuOllamaReplaceDialogue);

    if (mpuMessageBlocking) {
        mpuLogger.log('mpu_nextmsg: 訊息顯示被阻擋 (mpuMessageBlocking=true)，跳過');
        return;
    }

    if (isAuto && !mpuAutoTalk) {
        mpuLogger.log('mpu_nextmsg: 自動對話已關閉，退出');
        return;
    }

    if ((isAuto || isStartup) && mpuAiContextInProgress) {
        mpuLogger.log('mpu_nextmsg: 頁面感知 AI 正在進行中，跳過自動/啟動對話');
        return;
    }

    if ((isAuto || isStartup) && mpuGreetInProgress) {
        mpuLogger.log('mpu_nextmsg: 首次訪客打招呼正在進行中，跳過自動/啟動對話');
        return;
    }

    if (mpuOllamaReplaceDialogue && mpuOllamaRequesting) {
        if (isAuto) {
            mpuLogger.log('mpu_nextmsg: Ollama 正在處理請求，自動觸發的請求被跳過');
            return;
        }
        if (mpuOllamaRequestQueue.length < 2) {
            mpuLogger.log('mpu_nextmsg: Ollama 正在處理請求，此請求加入佇列');
            mpuOllamaRequestQueue.push(trigger);
        } else {
            mpuLogger.log('mpu_nextmsg: 佇列已滿，跳過此請求');
        }
        return;
    }

    if (!isAuto && mpuAutoTalk) {
        startAutoTalk();
    }

    mpu_hidemsg(400);

    if (mpuOllamaReplaceDialogue) {
        mpuLogger.log('mpu_nextmsg: 使用 LLM 生成對話');
        
        mpuOllamaRequesting = true;
        const curNum = window.mpuInfo?.num || 'default_1';
        const curMsgnum = parseInt(document.getElementById("ukagaka_msgnum")?.innerHTML || '0', 10) || 0;

        const formData = new FormData();
        formData.append('action', 'mpu_nextmsg');
        formData.append('cur_num', curNum);
        formData.append('cur_msgnum', curMsgnum);
        
        if (mpuLastLLMResponse) {
            formData.append('last_response', mpuLastLLMResponse);
        }
        
        if (mpuLLMResponseHistory.length > 0) {
            const recentHistory = mpuLLMResponseHistory.slice(-3);
            formData.append('response_history', JSON.stringify(recentHistory));
        }
        
        mpuLogger.log('mpu_nextmsg: 發送 LLM POST 請求到', mpuurl);

        mpuFetch(mpuurl, {
            method: 'POST',
            body: formData,
            timeout: 60000,
            retries: 1,
            requestId: 'mpu_nextmsg_llm',
            cancelPrevious: true
        })
            .then(res => {
                mpuLogger.log('mpu_nextmsg: LLM 回應 =', res);

                if (mpuMessageBlocking || mpuAiContextInProgress) {
                    mpuLogger.log('mpu_nextmsg: LLM 回應被阻擋（頁面感知 AI 正在進行中），跳過顯示');
                    return;
                }

                if (res && res.msg) {
                    const auto = window.mpuMsgList?.auto_msg || "";
                    const out = res.msg + auto;
                    mpu_typewriter(mpu_unescapeHTML(out), "#ukagaka_msg");
                    
                    mpuLastLLMResponse = res.msg;
                    
                    if (mpuLLMResponseHistory.length >= mpuMaxResponseHistory) {
                        mpuLLMResponseHistory.shift();
                    }
                    mpuLLMResponseHistory.push(res.msg);
                    
                    if (res.msgnum !== undefined) {
                        jQuery("#ukagaka_msgnum").html(res.msgnum);
                    }
                    mpu_showmsg(400);
                } else {
                    mpuLogger.warn('mpu_nextmsg: LLM 回應沒有 msg，使用後備對話');
                    mpuLastLLMResponse = '';
                    mpuLLMResponseHistory = [];
                    mpu_nextmsg_fallback();
                }
                
                mpuOllamaRequesting = false;
                mpu_processOllamaQueue();
            })
            .catch(error => {
                mpuOllamaRequesting = false;
                mpu_processOllamaQueue();
                
                if (mpuMessageBlocking || mpuAiContextInProgress) {
                    mpuLogger.log('mpu_nextmsg: LLM 錯誤處理被阻擋（頁面感知 AI 正在進行中），跳過');
                    return;
                }
                mpuLogger.warn("LLM dialogue generation failed, using fallback:", error);
                
                if (debugMode || window.mpuDebugMode) {
                    const errorMsg = error.message || 'LLM 連接失敗';
                    const debugMessage = `<span style="color: #ff4444;">[LLM 錯誤: ${errorMsg}]</span>`;
                    mpu_typewriter(debugMessage, "#ukagaka_msg");
                    mpu_showmsg(400);
                    setTimeout(() => {
                        mpuLastLLMResponse = '';
                        mpu_nextmsg_fallback();
                    }, 2000);
                } else {
                    mpuLastLLMResponse = '';
                    mpu_nextmsg_fallback();
                }
            });
        return;
    }

    setTimeout(function () {
        const store = window.mpuMsgList;
        
        if (!store) {
            mpuLogger.warn('mpu_nextmsg: 對話尚未載入，等待載入完成...');
            const retryCount = window.__mpu_retry_count || 0;
            if (retryCount < 3) {
                window.__mpu_retry_count = retryCount + 1;
                setTimeout(() => {
                    mpu_nextmsg(trigger);
                }, 1000);
            } else {
                window.__mpu_retry_count = 0;
                mpu_typewriter("對話尚未載入，請稍候...", "#ukagaka_msg");
                mpu_showmsg(400);
                mpuLogger.warn('mpu_nextmsg: 對話載入超時，已重試 3 次');
            }
            return;
        }
        
        window.__mpu_retry_count = 0;
        
        if (!Array.isArray(store.msg) || store.msg.length === 0) {
            const errorMsg = store.msg && store.msg.length === 0 
                ? "對話文件為空，請檢查對話文件內容" 
                : "訊息列表格式錯誤";
            mpu_typewriter(errorMsg, "#ukagaka_msg");
            mpu_showmsg(400);
            mpuLogger.warn('mpu_nextmsg: 無法顯示對話 -', {
                store: store ? 'exists' : 'null',
                msgArray: store && Array.isArray(store.msg) ? `length=${store.msg.length}` : 'not array'
            });
            return;
        }

        let msgNum = parseInt(document.getElementById("ukagaka_msgnum").innerHTML, 10) || 0;
        const msgCount = store.msg.length;

        if (mpuNextMode === "random") {
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

function mpu_nextmsg_fallback() {
    setTimeout(function () {
        if (mpuMessageBlocking || mpuAiContextInProgress) {
            mpuLogger.log('mpu_nextmsg_fallback: 被阻擋（頁面感知 AI 正在進行中），跳過顯示');
            return;
        }

        const store = window.mpuMsgList;
        
        if (!store) {
            mpuLogger.warn('mpu_nextmsg_fallback: 對話尚未載入，等待載入完成...');
            const retryCount = window.__mpu_fallback_retry_count || 0;
            if (retryCount < 2) {
                window.__mpu_fallback_retry_count = retryCount + 1;
                setTimeout(() => {
                    mpu_nextmsg_fallback();
                }, 1500);
            } else {
                window.__mpu_fallback_retry_count = 0;
                mpu_typewriter("對話尚未載入，請稍候...", "#ukagaka_msg");
                mpu_showmsg(400);
                mpuLogger.warn('mpu_nextmsg_fallback: 對話載入超時，已重試 2 次');
            }
            return;
        }
        
        window.__mpu_fallback_retry_count = 0;
        
        if (!Array.isArray(store.msg) || store.msg.length === 0) {
            const errorMsg = store.msg && store.msg.length === 0 
                ? "對話文件為空，請檢查對話文件內容" 
                : "訊息列表格式錯誤";
            mpu_typewriter(errorMsg, "#ukagaka_msg");
            mpu_showmsg(400);
            mpuLogger.warn('mpu_nextmsg_fallback: 無法顯示後備對話 -', {
                store: store ? 'exists' : 'null',
                msgArray: store && Array.isArray(store.msg) ? `length=${store.msg.length}` : 'not array'
            });
            return;
        }

        let msgNum = parseInt(document.getElementById("ukagaka_msgnum").innerHTML, 10) || 0;
        const msgCount = store.msg.length;

        if (mpuNextMode === "random") {
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

function mpuChange(num) {
    const hasNum = (typeof num !== 'undefined' && num !== null && num !== '');

    if (hasNum && typeof window.mpuCanvasManager === 'undefined') {
        mpu_handle_error(
            'Canvas 管理器未載入',
            'mpuChange:canvas_manager_check',
            {
                showToUser: true,
                userMessage: "動畫模組載入失敗，無法切換角色。請刷新頁面後再試。"
            }
        );
        return;
    }

    const params = new URLSearchParams({ action: 'mpu_change' });
    if (hasNum) {
        params.append('mpu_num', num);
    }
    const url = `${mpuurl}?${params.toString()}`;

    document.body.style.cursor = "wait";
    if (!jQuery("#ukagaka_msgbox").is(":hidden")) mpu_hidemsg(200);

    mpuFetch(url, {
        cancelPrevious: true,
        requestId: `mpu_change_${hasNum ? num : 'menu'}`,
        timeout: 15000,
        retries: 1
    })
        .then(res => {
            if (!hasNum) {
                if (typeof res !== 'string') throw new Error("Expected HTML, got JSON.");
                jQuery("#ukagaka_msg").html(res || "No content.");
                mpu_showmsg(300);
                jQuery("#ukagaka").stop(true, true).fadeIn(200);
                document.body.style.cursor = "auto";
                return;
            }

            if (typeof res !== 'object') throw new Error("Expected JSON, got HTML.");
            const payload = res || {};
            const $canvas = jQuery("#cur_ukagaka");
            const $wrap = jQuery("#ukagaka");

            if (payload.shell_info && typeof window.mpuCanvasManager !== 'undefined') {
                const $imgWrapper = jQuery("#ukagaka_img");
                $imgWrapper.fadeOut(120, function () {
                    window.mpuCanvasManager.init(payload.shell_info, payload.name || '');
                    $imgWrapper.fadeIn(180);
                });
            } else if (payload.shell) {
                if (typeof window.mpuCanvasManager !== 'undefined') {
                    const $imgWrapper = jQuery("#ukagaka_img");
                    $imgWrapper.fadeOut(120, function () {
                        window.mpuCanvasManager.init({
                            type: 'single',
                            url: payload.shell,
                            images: []
                        }, payload.name || '');
                        $imgWrapper.fadeIn(180);
                    });
                } else {
                    mpuLogger.warn('mpuChange: Canvas 管理器在 Ajax 成功後才發現不存在，這不應該發生');
                    mpu_handle_error(
                        'Canvas 管理器未載入',
                        'mpuChange:canvas_manager_fallback',
                        {
                            showToUser: true,
                            userMessage: "動畫模組載入失敗，請刷新頁面。"
                        }
                    );
                }
            }

            if (payload.num) jQuery("#ukagaka_num").html(payload.num);
            if (payload.msg) mpu_typewriter(mpu_unescapeHTML(payload.msg), "#ukagaka_msg");
            if (payload.name && $canvas.length) {
                $canvas.attr({ "data-alt": payload.name, "title": payload.name });
            }

            const msgListElem = document.getElementById("ukagaka_msglist");
            const useExternalDialog = payload.dialog_filename && msgListElem && msgListElem.getAttribute("data-load-external") === "true";
            
            if (useExternalDialog) {
                const currentFile = msgListElem.getAttribute("data-file") || "";
                const ext = currentFile.split('.').pop() || "json";
                const pure = `${payload.dialog_filename}.${ext}`;

                msgListElem.setAttribute("data-file", `dialogs/${pure}`);
                loadExternalDialog(pure);
            } else if (payload.msglist) {
                try {
                    window.mpuMsgList = (typeof payload.msglist === 'string') ? JSON.parse(payload.msglist) : payload.msglist;
                } catch (e) {
                    mpu_handle_error(e, 'mpuChange:parse_msglist');
                    window.mpuMsgList = null;
                }
            }

            $wrap.stop(true, true).fadeIn(200);
            mpu_showmsg(300);
            if (mpuAutoTalk && !useExternalDialog) {
                startAutoTalk();
            }
            document.body.style.cursor = "auto";
        })
        .catch(error => {
            mpu_handle_error(error, 'mpuChange', {
                showToUser: true,
                userMessage: debugMode || window.mpuDebugMode
                    ? `載入失敗: ${error.message}`
                    : "載入失敗，請稍後再試。"
            });
            jQuery("#ukagaka").stop(true, true).fadeIn(200);
            mpu_showmsg(200);
            document.body.style.cursor = "auto";
        });
}
