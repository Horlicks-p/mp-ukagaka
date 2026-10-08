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
        displayGeneration: 0,

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

            const generation = ++this.displayGeneration;
            const curNum = typeof window.mpuGetCurrentUkagakaNum === 'function'
                ? window.mpuGetCurrentUkagakaNum()
                : '';

            // 如果已經有表情在顯示，先移除
            if (this.currentEmoji) {
                this.hideEmoji(this.currentEmoji);
            }

            const render = (config) => {
                if (generation !== this.displayGeneration) return;
                if (!config || config.curNum !== curNum || !config.baseUrl) return;
                if (typeof window.mpuGetCurrentUkagakaNum === 'function' && window.mpuGetCurrentUkagakaNum() !== curNum) return;

                const emojiUrl = config.baseUrl + emojiName;
                const imgContainer = document.getElementById('ukagaka_img');
                if (!imgContainer) return;

                const emojiImg = document.createElement('img');
                emojiImg.className = 'frieren-emoji';
                emojiImg.alt = 'emoji';
                emojiImg.style.display = 'none';
                emojiImg.dataset.emojiKey = emojiName.replace(/\.[^.]+$/, '');
                this.applyEmojiScale(emojiImg);
                imgContainer.appendChild(emojiImg);
                this.currentEmoji = emojiImg;

                emojiImg.onload = () => {
                    if (generation !== this.displayGeneration || this.currentEmoji !== emojiImg) {
                        this.hideEmoji(emojiImg);
                        return;
                    }
                    if (typeof window.mpuGetCurrentUkagakaNum === 'function' && window.mpuGetCurrentUkagakaNum() !== curNum) {
                        this.hideEmoji(emojiImg);
                        return;
                    }
                    emojiImg.style.display = 'block';
                    this.updateEmojiPosition(emojiImg);
                };
                emojiImg.onerror = () => {
                    if (typeof mpuLogger !== 'undefined' && mpuLogger.warn) {
                        mpuLogger.warnF("frierenEmojiImageLoadFailed", "mpuEmojiManager: 表情画像の読み込みに失敗しました：%s", emojiUrl);
                    }
                    this.hideEmoji(emojiImg);
                };

                const self = this;
                setTimeout(() => {
                    if (generation === self.displayGeneration && self.currentEmoji === emojiImg) {
                        self.hideEmoji(emojiImg);
                    }
                }, this.displayDuration);
                emojiImg.src = emojiUrl;

                if (typeof mpuLogger !== 'undefined' && mpuLogger.log) {
                    mpuLogger.logF("frierenEmojiShown", "mpuEmojiManager: 表情を表示します：%s", emojiName);
                }
            };

            const config = window.mpuEmojiConfig;
            if (config && config.curNum === curNum) {
                render(config);
                return;
            }
            if (typeof window.loadEmojiConfig !== 'function') return;
            window.loadEmojiConfig(curNum).then(render).catch(error => {
                if (typeof mpuLogger !== 'undefined' && mpuLogger.warn) {
                    mpuLogger.warnF("frierenEmojiConfigLoadFailed", "mpuEmojiManager: 表情設定を読み込めませんでした：%s", error);
                }
            });
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
                // 如果 Canvas 不可見，檢查閒置 <img>
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
            const imgRect = typeof window.mpuGetCharacterRect === 'function'
                ? window.mpuGetCharacterRect(frierenImg)
                : frierenImg.getBoundingClientRect();

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
                    mpuLogger.logL("frierenEmojiRemoved", "mpuEmojiManager: 表情を削除します");
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
            this.displayGeneration++;
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

})();
