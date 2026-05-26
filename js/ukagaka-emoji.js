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

