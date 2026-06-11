/**
 * MP Ukagaka 芙莉蓮動畫模組
 *
 * 擴展 frieren.js 建立的 window.mpuFrierenManager，負責圖片載入、
 * idle/APNG 顯示、翻書動畫、睡眠判定與喚醒動畫。
 */

(function () {
  "use strict";

  const manager = window.mpuFrierenManager;
  if (!manager) {
    return;
  }

  Object.assign(manager, {
    /**
     * 載入芙莉蓮所有圖片
     */
    loadFrierenImages: function () {
      if (!window.mpuCanvasManager || !window.mpuCanvasManager.canvas) {
        mpuLogger.errorL('frierenImageCanvasManagerMissing', '画像読み込み前に Canvas マネージャーが初期化されていません');
        return;
      }

      this.frierenImages = [];
      const allImageUrls = [this.frierenIdleImage].concat(
        this.frierenBookFlipImages
      );
      let loadedCount = 0;
      const totalImages = allImageUrls.length;

      for (let i = 0; i < allImageUrls.length; i++) {
        const img = new Image();
        img.crossOrigin = "anonymous";

        img.onload = function (index) {
          loadedCount++;
          if (loadedCount === 1) {
            window.mpuCanvasManager.canvas.width = img.width;
            window.mpuCanvasManager.canvas.height = img.height;
          }
          if (loadedCount === totalImages) {
            window.mpuCanvasManager.imagesLoaded = true;
            this.showFrierenIdle();
          }
        }.bind(this);

        img.onerror = function (url) {
          mpuLogger.errorF('frierenImageLoadFailed', 'フリーレン画像の読み込みに失敗しました：%s', url);
          loadedCount++;
          if (loadedCount === totalImages) {
            window.mpuCanvasManager.imagesLoaded = true;
            if (this.frierenImages.length > 0) {
              this.showFrierenIdle();
            }
          }
        }.bind(this);

        img.src = allImageUrls[i];
        this.frierenImages.push(img);
      }
    },

    /**
     * 檢查是否為深夜睡眠時間（00:00-05:59）
     * 優先使用伺服器端判定，確保時區一致性
     * @returns {boolean}
     */
    isDeepSleepTime: function () {
      if (
        typeof window.mpuInfo !== "undefined" &&
        typeof window.mpuInfo.isDeepSleepTime !== "undefined"
      ) {
        return window.mpuInfo.isDeepSleepTime;
      }
      const now = new Date();
      return now.getHours() >= 0 && now.getHours() < 6;
    },

    /**
     * 顯示芙莉蓮閒置狀態
     * 睡眠模式且未喚醒時顯示 frieren[s].png，否則顯示 frieren[0].png
     */
    showFrierenIdle: function () {
      if (!this.isFrierenMode || !this.frierenIdleImage) {
        return;
      }

      this.stopFrierenAnimation();

      const imgContainer = document.getElementById("ukagaka_img");
      if (!imgContainer) {
        return;
      }

      const self = this;

      if (!this.frierenIdleImgElement) {
        // 先嘗試從 DOM 中獲取，避免 SPA 重載時建立重複元素
        const existingImg = document.getElementById("frieren_idle_apng");

        if (existingImg) {
          this.frierenIdleImgElement = existingImg;
        } else {
          this.frierenIdleImgElement = document.createElement("img");
          this.frierenIdleImgElement.id = "frieren_idle_apng";
          this.frierenIdleImgElement.style.display = "none";
          this.frierenIdleImgElement.style.opacity = String(
            this.frierenIdleOpacity
          );
          this.frierenIdleImgElement.style.cursor = "pointer";
          this.frierenIdleImgElement.style.maxWidth = "none";
          this.frierenIdleImgElement.style.width = "auto";
          this.frierenIdleImgElement.style.height = "auto";

          if (!this.frierenIdleImgElement.dataset.mpuSizeLocked) {
            this.frierenIdleImgElement.addEventListener("load", () => {
              const w = this.frierenIdleImgElement.naturalWidth;
              const h = this.frierenIdleImgElement.naturalHeight;
              if (w && h) {
                this.frierenIdleImgElement.style.width = w + "px";
                this.frierenIdleImgElement.style.height = h + "px";
                this.frierenIdleImgElement.style.maxWidth = "none";
              }
            });
            this.frierenIdleImgElement.dataset.mpuSizeLocked = "1";
          }

          // 設置 title 和 alt
          if (
            window.mpuCanvasManager &&
            window.mpuCanvasManager.currentCharacterName
          ) {
            this.frierenIdleImgElement.setAttribute(
              "title",
              window.mpuCanvasManager.currentCharacterName
            );
            this.frierenIdleImgElement.setAttribute(
              "alt",
              window.mpuCanvasManager.currentCharacterName
            );
          }

          // 如果舊元素不存在，才掛載新元素
          imgContainer.appendChild(this.frierenIdleImgElement);
        }
      }

      const shouldShowSleep = this.isSleepMessage() && !this.sleepModeAwoken;
      const imageToShow =
        shouldShowSleep && this.frierenSleepImage
          ? this.frierenSleepImage
          : this.frierenIdleImage;

      const preloadImg = new Image();
      const finalizeIdle = function() {
          // [Fix] 使用 endsWith 比對，避免絕對路徑造成的誤判，減少重複賦值 src。
          const currentSrc = self.frierenIdleImgElement.src || "";
          if (!currentSrc.endsWith(imageToShow)) {
              self.frierenIdleImgElement.src = imageToShow;
          }

          // 先顯示閒置圖片
          self.frierenIdleImgElement.style.display = "block";
          self.frierenIdleImgElement.style.opacity = String(self.frierenIdleOpacity);

          // 後隱藏畫布，確保視覺無縫過接
          if (window.mpuCanvasManager && window.mpuCanvasManager.canvas) {
              window.mpuCanvasManager.canvas.style.display = "none";
          }

          const imgContainer = document.getElementById("ukagaka_img");
          if (imgContainer) imgContainer.style.visibility = "visible";

          const msgbox = document.getElementById("ukagaka_msgbox");
          if (msgbox) msgbox.style.visibility = "visible";

          self.setupDecorationClickThrough();
          self.frierenIsSpeaking = false;

          // [Fix] 加回 Debug Log，方便監測切換時機
          if (typeof mpuLogger !== "undefined" && mpuLogger.log) {
            mpuLogger.logL("frierenSleepIdleImageSelected", "🌙 睡眠画像 frieren[s].png を表示します / ☀️ ゴースト画像 frieren[0].png を表示します");
          }
      };

      preloadImg.onload = finalizeIdle;
      preloadImg.onerror = finalizeIdle;
      preloadImg.src = imageToShow;
    },

    /**
     * 設置芙莉蓮閒置狀態透明度
     * @param {number} opacity - 透明度值（0.0 - 1.0）
     */
    setFrierenIdleOpacity: function (opacity) {
      opacity = Math.max(0.0, Math.min(1.0, parseFloat(opacity)));
      this.frierenIdleOpacity = opacity;
      if (
        this.frierenIdleImgElement &&
        this.frierenIdleImgElement.style.display !== "none"
      ) {
        this.frierenIdleImgElement.style.opacity = String(opacity);
      }
    },

    /**
     * 播放芙莉蓮翻書動畫（frieren[1].png ~ frieren[12].png）
     */
    playFrierenBookFlipAnimation: function () {
      if (
        !this.isFrierenMode ||
        !this.frierenImages ||
        this.frierenImages.length < 12
      ) {
        return;
      }

      // 如果正在播放動畫，等待完成
      if (this.frierenAnimationTimer) {
        return;
      }

      if (
        !window.mpuCanvasManager ||
        !window.mpuCanvasManager.canvas ||
        !window.mpuCanvasManager.ctx
      ) {
        mpuLogger.errorL('frierenDrawCanvasManagerMissing', '描画前に Canvas マネージャーが初期化されていません');
        return;
      }

      this.frierenIsSpeaking = true;

      const firstFrameImg = this.frierenImages[1];
      const canvas = window.mpuCanvasManager.canvas;
      const ctx = window.mpuCanvasManager.ctx;
      const frameInterval = window.mpuCanvasManager.frameInterval || 150;

      if (
        firstFrameImg &&
        firstFrameImg.complete &&
        firstFrameImg.naturalWidth > 0
      ) {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        ctx.drawImage(firstFrameImg, 0, 0);

        if (
          canvas.width !== firstFrameImg.width ||
          canvas.height !== firstFrameImg.height
        ) {
          canvas.width = firstFrameImg.width;
          canvas.height = firstFrameImg.height;
          ctx.drawImage(firstFrameImg, 0, 0);
        }

        if (this.frierenIdleImgElement) {
          this.frierenIdleImgElement.style.display = "none";
        }
        if (canvas) {
          canvas.style.display = "block";
        }
      } else {
        const checkFirstFrame = function () {
          if (
            firstFrameImg &&
            firstFrameImg.complete &&
            firstFrameImg.naturalWidth > 0
          ) {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            ctx.drawImage(firstFrameImg, 0, 0);

            if (
              canvas.width !== firstFrameImg.width ||
              canvas.height !== firstFrameImg.height
            ) {
              canvas.width = firstFrameImg.width;
              canvas.height = firstFrameImg.height;
              ctx.drawImage(firstFrameImg, 0, 0);
            }

            if (this.frierenIdleImgElement) {
              this.frierenIdleImgElement.style.display = "none";
            }
            if (canvas) {
              canvas.style.display = "block";
            }
          } else {
            setTimeout(checkFirstFrame, 50);
          }
        }.bind(this);

        checkFirstFrame();
      }

      let frameIndex = 2;

      this.frierenAnimationTimer = setInterval(
        function () {
          // [Fix] frieren[1..11] 是翻書幀，最後要交棒給原生 <img> 渲染的 idle（frieren[0].png，APNG）。
          // 直接從翻書末幀 frieren[11]（翻書中段姿勢）跳到 idle <img> 會「閃一下」：
          //   ① 姿勢落差：11 是翻書中段、0 是 idle 定格；
          //   ② 亮度落差：半透明 APNG 在 canvas 上以 source-over 繪製會雙重混合偏暗，<img> 原生渲染較亮。
          // 收尾時先用 'copy' 合成把 idle 姿勢（frieren[0]）畫到 canvas（copy 直接覆蓋像素、不疊 alpha，
          // 避免偏暗），使最後一張 canvas 幀＝idle 定格且亮度與 <img> 一致，再由 showFrierenIdle 無縫換成 <img>。
          if (frameIndex >= 12) {
            this.stopFrierenAnimation();
            const idleImg = this.frierenImages[0];
            if (idleImg && idleImg.complete && idleImg.naturalWidth > 0 && ctx) {
              const prevOp = ctx.globalCompositeOperation;
              ctx.globalCompositeOperation = "copy";
              ctx.clearRect(0, 0, canvas.width, canvas.height);
              ctx.drawImage(idleImg, 0, 0);
              ctx.globalCompositeOperation = prevOp;
            }
            this.showFrierenIdle();
            return;
          }

          const img = this.frierenImages[frameIndex];
          if (img && img.complete && img.naturalWidth > 0) {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            ctx.drawImage(img, 0, 0);
          }

          frameIndex++;
        }.bind(this),
        frameInterval
      );
    },

    /**
     * 停止芙莉蓮動畫
     */
    stopFrierenAnimation: function () {
      if (this.frierenAnimationTimer) {
        clearInterval(this.frierenAnimationTimer);
        this.frierenAnimationTimer = null;
      }
    },

    /**
     * 清理芙莉蓮相關元素（用於角色切換）
     */
    cleanupFrierenElements: function () {
      this.isFrierenMode = false;

      if (this.frierenIdleImgElement && this.frierenIdleImgElement.parentNode) {
        this.frierenIdleImgElement.parentNode.removeChild(
          this.frierenIdleImgElement
        );
        this.frierenIdleImgElement = null;
      }

      // 防止 DOM 裡面有殘留未被綁定的重複元素
      const strayImgs = document.querySelectorAll("#frieren_idle_apng");
      strayImgs.forEach(img => {
        if (img.parentNode) {
          img.parentNode.removeChild(img);
        }
      });

      this.clearFrierenDecorations();
      this._decorationsLoaded = false; // 重置標誌，允許重新載入

      if (window.mpuCanvasManager && window.mpuCanvasManager.canvas) {
        window.mpuCanvasManager.canvas.style.display = "block";
      }
    },

    /**
     * 檢查是否為睡眠模式（深夜 + 初始訊息是睡眠相關 + 尚未被喚醒）
     * @returns {boolean} 是否為睡眠模式
     */
    isSleepMessage: function () {
      if (this.sleepModeAwoken) {
        return false;
      }

      // 如果是暫時喚醒，我們仍然視為正在處理睡眠訊息（以便播放喚醒動畫）
      const isTemporaryWakeUp = typeof window.mpuInfo !== "undefined" && window.mpuInfo.isTemporaryWakeUp === true;

      if (!this.isDeepSleepTime() && !isTemporaryWakeUp) {
        return false;
      }

      const msgElement = document.getElementById("ukagaka_msg");
      if (!msgElement) return false;

      const initialMsg = msgElement.getAttribute("data-initial-msg") || "";
      return initialMsg.includes("<!-- mpu-sleep -->");
    },

    /**
     * 喚醒芙莉蓮（用戶點擊 OK 按鈕時調用）
     * @returns {boolean} 是否需要播放醒來動畫
     */
    wakeUp: function () {
      const isForced = window.mpuForceWakeUpNextTime === true;
      window.mpuForceWakeUpNextTime = false;
      if ((this.isSleepMessage() || isForced) && !this.sleepModeAwoken) {
        this.sleepModeAwoken = true;
        if (typeof mpuLogger !== "undefined" && mpuLogger.log) {
          mpuLogger.logL("frierenAwakened", "☀️ フリーレンが目を覚ましました！");
        }
        return true;
      }
      return false;
    },

    /**
     * 播放醒來動畫（frieren[w1-w4].png）
     * @param {Function} callback - 動畫完成後的回調函數
     */
    playWakeUpAnimation: function (callback) {
      if (
        !this.isFrierenMode ||
        !this.frierenWakeUpImages ||
        this.frierenWakeUpImages.length === 0
      ) {
        if (callback) callback();
        return;
      }

      if (typeof mpuLogger !== "undefined" && mpuLogger.log) {
        mpuLogger.logL("frierenWakeAnimationPlaying", "👀 目覚めアニメーション frieren[w1-w5].png を再生します");
      }

      this.stopFrierenAnimation();

      const self = this;
      let frameIndex = 0;
      const frameInterval = 80;

      const wakeUpImgs = [];
      let loadedCount = 0;

      for (let i = 0; i < this.frierenWakeUpImages.length; i++) {
        const img = new Image();
        img.crossOrigin = "anonymous";
        img.onload = function () {
          loadedCount++;
          if (loadedCount === self.frierenWakeUpImages.length) {
            startAnimation();
          }
        };
        img.onerror = function () {
          loadedCount++;
          if (loadedCount === self.frierenWakeUpImages.length) {
            startAnimation();
          }
        };
        img.src = this.frierenWakeUpImages[i];
        wakeUpImgs.push(img);
      }

      function startAnimation() {
        if (
          !window.mpuCanvasManager ||
          !window.mpuCanvasManager.canvas ||
          !window.mpuCanvasManager.ctx
        ) {
          if (callback) callback();
          return;
        }

        const canvas = window.mpuCanvasManager.canvas;
        const ctx = window.mpuCanvasManager.ctx;

        const firstImg = wakeUpImgs[0];
        if (firstImg && firstImg.complete && firstImg.naturalWidth > 0) {
          canvas.width = firstImg.width;
          canvas.height = firstImg.height;
          ctx.clearRect(0, 0, canvas.width, canvas.height);
          ctx.drawImage(firstImg, 0, 0);
        }

        if (self.frierenIdleImgElement) {
          self.frierenIdleImgElement.style.display = "none";
        }
        canvas.style.display = "block";

        frameIndex = 1;
        playFrames();
      }

      function playFrames() {
        if (frameIndex >= wakeUpImgs.length) {
          self.frierenAnimationTimer = null;
          if (callback) callback();
          return;
        }

        const img = wakeUpImgs[frameIndex];
        if (
          img.complete &&
          img.naturalWidth > 0 &&
          window.mpuCanvasManager &&
          window.mpuCanvasManager.ctx
        ) {
          const canvas = window.mpuCanvasManager.canvas;
          const ctx = window.mpuCanvasManager.ctx;
          canvas.width = img.width;
          canvas.height = img.height;
          ctx.clearRect(0, 0, canvas.width, canvas.height);
          ctx.drawImage(img, 0, 0);
        }

        frameIndex++;
        self.frierenAnimationTimer = setTimeout(playFrames, frameInterval);
      }
    },

    /**
     * 觸發芙莉蓮說話動畫
     * @param {boolean} forceAnimation - 使用者主動觸發時設為 true，會喚醒芙莉蓮
     * @param {Function} onWakeUpComplete - 喚醒動畫完成後的回調函數
     * @param {boolean} skipBookFlip - 是否跳過翻書動畫（對話模式開啟時只需喚醒不翻書）
     * @returns {boolean} 是否正在播放喚醒動畫（用於判斷是否需要延遲對話）
     */
    triggerFrierenSpeaking: function (
      forceAnimation,
      onWakeUpComplete,
      skipBookFlip
    ) {
      if (!this.isFrierenMode) {
        return false;
      }

      if (forceAnimation) {
        const needWakeUpAnimation = this.wakeUp();
        if (needWakeUpAnimation) {
          const self = this;
          if (typeof mpuLogger !== "undefined" && mpuLogger.log) {
            mpuLogger.logF("frierenWakeAnimationStarted", "🌅 目覚めアニメーションを開始します。skipBookFlip = %s", skipBookFlip);
          }
          this.playWakeUpAnimation(function () {
            if (!skipBookFlip) {
              if (typeof mpuLogger !== "undefined" && mpuLogger.log) {
                mpuLogger.logL("frierenPostWakeBookFlipPlaying", "📖 目覚め後にページめくりアニメーションを再生します");
              }
              self.playFrierenBookFlipAnimation();
            } else {
              if (typeof mpuLogger !== "undefined" && mpuLogger.log) {
                mpuLogger.logL("frierenPostWakeBookFlipSkipped", "📖 目覚め後のページめくりアニメーションをスキップします");
              }
            }
            if (onWakeUpComplete) {
              onWakeUpComplete();
            }
          });
          return true;
        }
      }

      if (this.isSleepMessage()) {
        if (typeof mpuLogger !== "undefined" && mpuLogger.log) {
          mpuLogger.logL("frierenSleepModeBookFlipSkipped", "🌙 睡眠モード：ページめくりアニメーションをスキップします");
        }
        return false;
      }

      if (skipBookFlip) {
        if (typeof mpuLogger !== "undefined" && mpuLogger.log) {
          mpuLogger.logL("frierenManualWakeDialogueBookFlipSkipped", "📖 手動の目覚め会話：ページめくりアニメーションをスキップします");
        }
        if (onWakeUpComplete) {
          onWakeUpComplete();
        }
        return false;
      }

      if (
        !window.mpuCanvasManager ||
        !window.mpuCanvasManager.imagesLoaded ||
        !this.frierenImages ||
        this.frierenImages.length < 12
      ) {
        setTimeout(
          function () {
            this.triggerFrierenSpeaking(forceAnimation, onWakeUpComplete);
          }.bind(this),
          100
        );
        return false;
      }

      this.playFrierenBookFlipAnimation();
      return false;
    },
  });
})();
