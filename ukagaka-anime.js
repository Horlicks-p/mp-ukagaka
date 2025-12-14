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
        frameInterval: 200, // 動畫幀間隔（毫秒）
        imagesLoaded: false, // 圖片是否已全部載入
        pendingAnimation: false, // 是否有待執行的動畫

        /**
         * 初始化 Canvas
         * @param {Object} shellInfo - Shell 資訊對象 {type: 'single'|'folder', url: string, images: string[]}
         * @param {string} name - 春菜名稱
         */
        init: function(shellInfo, name) {
            // 清除之前的動畫
            this.stopAnimation();
            
            // 重置狀態
            this.imagesLoaded = false;
            this.pendingAnimation = false;

            // 獲取 Canvas 元素
            this.canvas = document.getElementById('cur_ukagaka');
            if (!this.canvas) {
                console.error('[MP Ukagaka] Canvas 元素不存在');
                return;
            }

            // 獲取 Canvas 上下文
            this.ctx = this.canvas.getContext('2d');
            if (!this.ctx) {
                console.error('[MP Ukagaka] 無法獲取 Canvas 上下文');
                return;
            }

            // 設置 title 和 alt
            if (name) {
                this.canvas.setAttribute('title', name);
                this.canvas.setAttribute('data-alt', name);
            }

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
            }).bind(this);

            img.onerror = function() {
                console.error('[MP Ukagaka] 圖片載入失敗:', imageUrl);
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
                    console.error('[MP Ukagaka] 圖片載入失敗:', url);
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
        },

        /**
         * 檢查是否為動畫模式
         * @returns {boolean}
         */
        isAnimationMode: function() {
            return this.isAnimated;
        }
    };

    // 將管理器暴露到全域
    window.mpuCanvasManager = mpuCanvasManager;

})();

