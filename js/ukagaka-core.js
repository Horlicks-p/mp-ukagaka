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
/**
 * 顯示下一句對話
 * @param {string} trigger - 觸發方式：'auto'（自動）、'startup'（啟動）、undefined（手動）
 */
function mpu_nextmsg(trigger) {
    const isAuto = (trigger === 'auto');
    const isStartup = (trigger === 'startup');
    mpuLogger.log('mpu_nextmsg 被調用, trigger =', trigger, ', isAuto =', isAuto, ', isStartup =', isStartup, ', mpuOllamaReplaceDialogue =', mpuOllamaReplaceDialogue);

    // 如果正在顯示重要訊息（如 API 錯誤或頁面感知 AI 載入中），則完全阻擋切換
    // 必須在最前面檢查，確保所有觸發方式都被阻止
    if (mpuMessageBlocking) {
        mpuLogger.log('mpu_nextmsg: 訊息顯示被阻擋 (mpuMessageBlocking=true)，跳過');
        return;
    }

    // 如果關閉了自動對話，且這是自動觸發，則不執行
    // 注意：isStartup 不受 mpuAutoTalk 影響，因為它是初始對話
    if (isAuto && !mpuAutoTalk) {
        mpuLogger.log('mpu_nextmsg: 自動對話已關閉，退出');
        return;
    }

    // 如果頁面感知 AI 正在進行中，且這是自動觸發或啟動觸發，則跳過
    if ((isAuto || isStartup) && mpuAiContextInProgress) {
        mpuLogger.log('mpu_nextmsg: 頁面感知 AI 正在進行中，跳過自動/啟動對話');
        return;
    }

    // 如果首次訪客打招呼正在進行中，且這是自動觸發或啟動觸發，則跳過
    if ((isAuto || isStartup) && mpuGreetInProgress) {
        mpuLogger.log('mpu_nextmsg: 首次訪客打招呼正在進行中，跳過自動/啟動對話');
        return;
    }

    if (!isAuto && mpuAutoTalk) {
        startAutoTalk();
    }

    mpu_hidemsg(400);

    // 檢查是否啟用了 LLM 取代內建對話
    if (mpuOllamaReplaceDialogue) {
        mpuLogger.log('mpu_nextmsg: 使用 LLM 生成對話');
        // 使用 LLM 生成對話
        const curNum = window.mpuInfo?.num || 'default_1';
        const curMsgnum = parseInt(document.getElementById("ukagaka_msgnum")?.innerHTML || '0', 10) || 0;

        // 使用 POST 方式傳遞資料，避免 URL 長度限制
        const formData = new FormData();
        formData.append('action', 'mpu_nextmsg');
        formData.append('cur_num', curNum);
        formData.append('cur_msgnum', curMsgnum);
        
        // 傳遞上一次 LLM 回應，用於避免重複對話
        if (mpuLastLLMResponse) {
            formData.append('last_response', mpuLastLLMResponse);
        }
        
        // ★★★ 傳遞回應歷史（最近3次），用於更嚴格的重複檢測 ★★★
        if (mpuLLMResponseHistory.length > 0) {
            // 只傳遞最近3次，使用 POST 方式避免 URL 長度限制
            const recentHistory = mpuLLMResponseHistory.slice(-3);
            formData.append('response_history', JSON.stringify(recentHistory));
        }
        
        mpuLogger.log('mpu_nextmsg: 發送 LLM POST 請求到', mpuurl);

        mpuFetch(mpuurl, {
            method: 'POST',
            body: formData,
            timeout: 60000,  // LLM 可能需要較長時間（60秒）
            retries: 1,
            requestId: 'mpu_nextmsg_llm',
            cancelPrevious: true  // 取消前一次還沒跑完的對話請求，防止連點導致多個並行請求
        })
            .then(res => {
                mpuLogger.log('mpu_nextmsg: LLM 回應 =', res);

                // 檢查是否被頁面感知 AI 或其他重要訊息阻擋
                if (mpuMessageBlocking || mpuAiContextInProgress) {
                    mpuLogger.log('mpu_nextmsg: LLM 回應被阻擋（頁面感知 AI 正在進行中），跳過顯示');
                    return;
                }

                if (res && res.msg) {
                    const auto = window.mpuMsgList?.auto_msg || "";
                    const out = res.msg + auto;
                    mpu_typewriter(mpu_unescapeHTML(out), "#ukagaka_msg");
                    
                    // 記錄這一次的 LLM 回應，用於下次避免重複對話
                    mpuLastLLMResponse = res.msg;
                    
                    // ★★★ 記錄到歷史中（用於更嚴格的重複檢測）★★★
                    if (mpuLLMResponseHistory.length >= mpuMaxResponseHistory) {
                        mpuLLMResponseHistory.shift(); // 移除最舊的記錄
                    }
                    mpuLLMResponseHistory.push(res.msg);
                    
                    if (res.msgnum !== undefined) {
                        jQuery("#ukagaka_msgnum").html(res.msgnum);
                    }
                    mpu_showmsg(400);
                } else {
                    mpuLogger.warn('mpu_nextmsg: LLM 回應沒有 msg，使用後備對話');
                    // LLM 生成失敗，清除記錄（因為使用的是後備對話）
                    mpuLastLLMResponse = '';
                    mpuLLMResponseHistory = []; // 清除歷史記錄
                    // LLM 生成失敗，使用後備對話
                    mpu_nextmsg_fallback();
                }
            })
            .catch(error => {
                // 檢查是否被頁面感知 AI 或其他重要訊息阻擋
                if (mpuMessageBlocking || mpuAiContextInProgress) {
                    mpuLogger.log('mpu_nextmsg: LLM 錯誤處理被阻擋（頁面感知 AI 正在進行中），跳過');
                    return;
                }
                mpuLogger.warn("LLM dialogue generation failed, using fallback:", error);
                
                // 在 debug 模式下顯示錯誤提示
                if (debugMode || window.mpuDebugMode) {
                    const errorMsg = error.message || 'LLM 連接失敗';
                    const debugMessage = `<span style="color: #ff4444;">[LLM 錯誤: ${errorMsg}]</span>`;
                    mpu_typewriter(debugMessage, "#ukagaka_msg");
                    mpu_showmsg(400);
                    // 2 秒後切換到後備對話
                    setTimeout(() => {
                        mpuLastLLMResponse = '';
                        mpu_nextmsg_fallback();
                    }, 2000);
                } else {
                    // 非 debug 模式，直接使用後備對話
                    mpuLastLLMResponse = '';
                    mpu_nextmsg_fallback();
                }
            });
        return;
    }

    // 正常模式：使用內建對話
    setTimeout(function () {
        const store = window.mpuMsgList;
        
        // 如果對話尚未載入，等待一段時間後重試（最多重試 3 次）
        if (!store) {
            mpuLogger.warn('mpu_nextmsg: 對話尚未載入，等待載入完成...');
            // 檢查是否正在載入外部對話（通過檢查是否有載入中的請求）
            const retryCount = window.__mpu_retry_count || 0;
            if (retryCount < 3) {
                window.__mpu_retry_count = retryCount + 1;
                setTimeout(() => {
                    // 不在此處重置計數器，讓重試邏輯繼續累積計數
                    mpu_nextmsg(trigger); // 重試
                }, 1000); // 等待 1 秒後重試
            } else {
                window.__mpu_retry_count = 0; // 最終失敗時才重置計數器
                mpu_typewriter("對話尚未載入，請稍候...", "#ukagaka_msg");
                mpu_showmsg(400);
                mpuLogger.warn('mpu_nextmsg: 對話載入超時，已重試 3 次');
            }
            return;
        }
        
        // 成功載入後，重置計數器
        window.__mpu_retry_count = 0;
        
        if (!Array.isArray(store.msg) || store.msg.length === 0) {
            // 提供更詳細的錯誤訊息
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

/**
 * 後備函數：當 LLM 生成失敗時使用內建對話
 */
function mpu_nextmsg_fallback() {
    setTimeout(function () {
        // 檢查是否被頁面感知 AI 或其他重要訊息阻擋
        if (mpuMessageBlocking || mpuAiContextInProgress) {
            mpuLogger.log('mpu_nextmsg_fallback: 被阻擋（頁面感知 AI 正在進行中），跳過顯示');
            return;
        }

        const store = window.mpuMsgList;
        
        // 如果對話尚未載入，等待一段時間後重試（最多重試 2 次）
        if (!store) {
            mpuLogger.warn('mpu_nextmsg_fallback: 對話尚未載入，等待載入完成...');
            const retryCount = window.__mpu_fallback_retry_count || 0;
            if (retryCount < 2) {
                window.__mpu_fallback_retry_count = retryCount + 1;
                setTimeout(() => {
                    // 不在此處重置計數器，讓重試邏輯繼續累積計數
                    mpu_nextmsg_fallback(); // 重試
                }, 1500); // 等待 1.5 秒後重試
            } else {
                window.__mpu_fallback_retry_count = 0; // 最終失敗時才重置計數器
                mpu_typewriter("對話尚未載入，請稍候...", "#ukagaka_msg");
                mpu_showmsg(400);
                mpuLogger.warn('mpu_nextmsg_fallback: 對話載入超時，已重試 2 次');
            }
            return;
        }
        
        // 成功載入後，重置計數器
        window.__mpu_fallback_retry_count = 0;
        
        if (!Array.isArray(store.msg) || store.msg.length === 0) {
            // 提供更詳細的錯誤訊息
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

// ====== 切換春菜 ======
/**
 * 切換春菜人物或顯示選單
 * @param {string|number|undefined} num - 人物編號，若未提供則顯示選單
 */
function mpuChange(num) {
    const hasNum = (typeof num !== 'undefined' && num !== null && num !== '');

    // 如果是要切換人物（提供編號），先檢查 Canvas 管理器是否存在
    if (hasNum && typeof window.mpuCanvasManager === 'undefined') {
        mpu_handle_error(
            'Canvas 管理器未載入',
            'mpuChange:canvas_manager_check',
            {
                showToUser: true,
                userMessage: "動畫模組載入失敗，無法切換角色。請刷新頁面後再試。"
            }
        );
        return; // 直接返回，不發送請求
    }

    const params = new URLSearchParams({ action: 'mpu_change' });
    if (hasNum) {
        params.append('mpu_num', num);
    }
    const url = `${mpuurl}?${params.toString()}`;

    // 顯示載入中游標
    document.body.style.cursor = "wait";
    if (!jQuery("#ukagaka_msgbox").is(":hidden")) mpu_hidemsg(200);

    mpuFetch(url, {
        cancelPrevious: true,  // 取消之前的切換請求
        requestId: `mpu_change_${hasNum ? num : 'menu'}`,
        timeout: 15000,  // 15 秒超時
        retries: 1
    })
        .then(res => {
            // 分支 A: 顯示選單 HTML（未提供人物編號）
            if (!hasNum) {
                if (typeof res !== 'string') throw new Error("Expected HTML, got JSON.");
                jQuery("#ukagaka_msg").html(res || "No content.");
                mpu_showmsg(300);
                jQuery("#ukagaka").stop(true, true).fadeIn(200);
                document.body.style.cursor = "auto";
                return;
            }

            // 分支 B: 切換人物（提供人物編號，回傳 JSON）
            if (typeof res !== 'object') throw new Error("Expected JSON, got HTML.");
            const payload = res || {};
            const $canvas = jQuery("#cur_ukagaka");
            const $wrap = jQuery("#ukagaka");

            // 使用 Canvas 管理器初始化圖片
            if (payload.shell_info && typeof window.mpuCanvasManager !== 'undefined') {
                const $imgWrapper = jQuery("#ukagaka_img");
                $imgWrapper.fadeOut(120, function () {
                    window.mpuCanvasManager.init(payload.shell_info, payload.name || '');
                    $imgWrapper.fadeIn(180);
                });
            } else if (payload.shell) {
                // 向後兼容：如果沒有 shell_info，嘗試使用 Canvas 管理器載入單張圖片
                // 注意：這裡的檢查是備用，主要檢查已在函數開始時完成
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
                    // 如果 Canvas 管理器不存在（理論上不應該到達這裡，因為已在開始時檢查）
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
            // 注意：如果使用外部對話文件，startAutoTalk 會在 loadExternalDialog 完成後由該函數內部調用
            // 這裡只在沒有使用外部對話文件時才調用 startAutoTalk，避免重複觸發對話
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
