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
