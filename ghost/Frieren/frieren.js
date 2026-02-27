/**
 * MP Ukagaka 芙莉蓮專用功能模組
 *
 * 從 ukagaka-anime.js 分離的芙莉蓮專用功能
 * 負責管理芙莉蓮角色的動畫、裝飾物和互動
 */

(function () {
  "use strict";

  /**
   * 芙莉蓮管理器
   * 擴展 mpuCanvasManager 的功能
   */
  const mpuFrierenManager = {
    // 芙莉蓮專用狀態
    isFrierenMode: false, // 是否為芙莉蓮模式
    frierenIdleImage: null, // 閒置狀態圖片（frieren[0].png）
    frierenSleepImage: null, // 睡眠狀態圖片（frieren[s].png）
    frierenWakeUpImages: [], // 醒來動畫圖片序列（frieren[w1-w5].png）
    frierenBookFlipImages: [], // 翻書動畫圖片序列（frieren[1-12].png）
    frierenImages: [], // 芙莉蓮所有圖片對象陣列
    frierenAnimationTimer: null, // 芙莉蓮動畫定時器
    frierenIsSpeaking: false, // 是否正在說話
    frierenIdleImgElement: null, // 用於顯示 APNG 的 <img> 元素
    frierenDecorations: [], // 裝飾元素陣列
    frierenIdleOpacity: 0.95, // 芙莉蓮閒置狀態透明度（0.0 - 1.0）
    decorationChatInProgress: false, // 裝飾物對話是否正在進行中
    decorationHitCanvases: new Map(), // 裝飾物像素檢測用的隱藏 Canvas
    pixelHitThreshold: 10, // 像素透明度閾值（0-255），大於此值才視為可點擊
    _decorationClickThroughHandler: null, // 點擊穿透事件處理器（綁定在容器上，避免 img 尚未建立時漏綁）
    sleepModeAwoken: false, // 睡眠模式是否已被用戶喚醒（刷新頁面重置）

    // 觸摸區域點擊計數和冷卻機制
    touchZoneClicks: {}, // { zoneName: [timestamp1, timestamp2, ...] }
    touchZoneCooldown: {}, // { zoneName: cooldownEndTime }
    touchZoneLimits: {
      // 各區域的點擊限制設定
      chest: { maxClicks: 3, windowMs: 30000, cooldownMs: 180000 }, // 30秒內點3次 → 冷卻180秒
    },

    /**
     * 初始化芙莉蓮模式
     * @param {Object} shellInfo - Shell 資訊對象
     * @param {string} name - 春菜名稱
     */
    initFrierenMode: function (shellInfo, name) {
      this.isFrierenMode = true;
      this.frierenIsSpeaking = false;

      if (!shellInfo || !shellInfo.url) {
        console.error("[MP Ukagaka] 芙莉蓮模式：shellInfo 無效");
        return;
      }

      const baseUrl = shellInfo.url;
      this.frierenIdleImage = baseUrl + "frieren[0].png";
      this.frierenSleepImage = baseUrl + "frieren[s].png";

      this.frierenWakeUpImages = [];
      for (let i = 1; i <= 5; i++) {
        this.frierenWakeUpImages.push(baseUrl + "frieren[w" + i + "].png");
      }

      this.frierenBookFlipImages = [];
      for (let i = 1; i <= 12; i++) {
        this.frierenBookFlipImages.push(baseUrl + "frieren[" + i + "].png");
      }

      this.loadFrierenImages();

      const imgContainer = document.getElementById("ukagaka_img");
      if (imgContainer) {
        imgContainer.style.position = "relative";
      }

      this.loadFrierenDecorations();
      this.setupCharacterTouchEvents();
    },

    /**
     * 載入芙莉蓮所有圖片
     */
    loadFrierenImages: function () {
      if (!window.mpuCanvasManager || !window.mpuCanvasManager.canvas) {
        console.error("[MP Ukagaka] Canvas 管理器未初始化");
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
          console.error("[MP Ukagaka] 芙莉蓮圖片載入失敗:", url);
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
            mpuLogger.log(
              shouldShowSleep
                ? "🌙 顯示睡眠圖片 frieren[s].png"
                : "☀️ 顯示閒置圖片 frieren[0].png"
            );
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
        this.frierenImages.length < 13
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
        console.error("[MP Ukagaka] Canvas 管理器未初始化");
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
          if (frameIndex > 12) {
            this.stopFrierenAnimation();
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
     * 載入裝飾物（透過 AJAX 動態載入配置）
     */
    loadFrierenDecorations: function () {
      const self = this;
      const imgContainer = document.getElementById("ukagaka_img");
      if (!imgContainer) return;

      // 防止重複載入（已載入過就跳過）
      if (this._decorationsLoaded) {
        if (typeof mpuLogger !== "undefined" && mpuLogger.log) {
          mpuLogger.log("[MP Ukagaka] 裝飾物已載入，跳過重複載入");
        }
        return;
      }

      if (window.mpuDecorationConfig !== undefined && window.mpuDecorationsBaseUrl !== undefined) {
        if (!window.mpuShowDecorations) {
          return;
        }
        this._decorationsLoaded = true; // 標記為已載入
        self._loadDecorationsFromConfig(
          window.mpuDecorationsBaseUrl,
          window.mpuDecorationConfig
        );
        return;
      }

      if (typeof jQuery !== "undefined") {
        jQuery(document).one("mpuInitComplete", function(event, response) {
          if (response && response.show_decorations && response.decoration_config) {
            self._decorationsLoaded = true; // 標記為已載入
            self._loadDecorationsFromConfig(
              response.decorations_base_url,
              response.decoration_config
            );
          }
        });
        return;
      }

      if (typeof jQuery !== "undefined" && typeof mpuurl !== "undefined") {
        jQuery.ajax({
          url: mpuurl,
          type: "GET",
          data: {
            action: "mpu_get_decoration_config",
            mpu_nonce: typeof mpuNonce !== "undefined" ? mpuNonce : ""
          },
          dataType: "json",
          success: function(response) {
            if (response.success) {
              window.mpuDecorationsBaseUrl = response.decorations_base_url;
              window.mpuDecorationConfig = response.decoration_config;
              window.mpuTouchZones = response.touchzones;
              window.mpuShowDecorations = response.show_decorations;

              if (!response.show_decorations) {
                return;
              }

              self._decorationsLoaded = true; // 標記為已載入
              self._loadDecorationsFromConfig(
                response.decorations_base_url,
                response.decoration_config
              );
            } else {
              console.warn("[MP Ukagaka] 無法載入裝飾配置:", response.error || "未知錯誤");
            }
          },
          error: function(xhr, status, error) {
            console.error("[MP Ukagaka] AJAX 載入裝飾配置失敗:", error);
          }
        });
      } else {
        console.warn("[MP Ukagaka] jQuery 或 mpuurl 不可用，無法載入裝飾配置");
      }
    },

    /**
     * 從配置載入裝飾物（內部方法）
     * @param {string} decorationsBaseUrl - 裝飾圖片基礎 URL
     * @param {Array} decorationConfig - 裝飾配置陣列
     */
    _loadDecorationsFromConfig: function(decorationsBaseUrl, decorationConfig) {
      if (!decorationsBaseUrl || !Array.isArray(decorationConfig)) {
        console.warn("[MP Ukagaka] 裝飾配置無效");
        return;
      }

      if (!decorationsBaseUrl.endsWith("/")) {
        decorationsBaseUrl += "/";
      }

      const sortedConfig = [...decorationConfig].sort(
        (a, b) => (a.z_index || 0) - (b.z_index || 0)
      );

      for (const item of sortedConfig) {
        if (!item.type || !item.image) continue;

        this.addFrierenDecoration({
          type: item.type,
          src: decorationsBaseUrl + item.image,
          top: item.position?.top || "auto",
          left: item.position?.left || "auto",
          right: item.position?.right || "auto",
          bottom: item.position?.bottom || "auto",
          width: item.size?.width || "auto",
          height: item.size?.height || "auto",
          transform: item.transform || "",
          zIndex: item.z_index || 0,
          opacity: item.opacity !== undefined ? item.opacity : 1.0,
        });
      }

      if (typeof mpuLogger !== "undefined" && mpuLogger.log) {
        mpuLogger.log(
          "[MP Ukagaka] 已從 JSON 配置載入 " + sortedConfig.length + " 個裝飾物"
        );
      }

      this.setupDecorationClickThrough();
    },

    /**
     * 設置點擊穿透：當點擊 canvas 或 img 時，檢查是否點擊到裝飾物區域
     * 使用事件委派綁定在容器上（capture），避免元素晚建立的問題
     */
    setupDecorationClickThrough: function () {
      const imgContainer = document.getElementById("ukagaka_img");
      if (!imgContainer) return;

      if (this._decorationClickThroughHandler) {
        imgContainer.removeEventListener(
          "click",
          this._decorationClickThroughHandler,
          true
        );
      }

      this._decorationClickThroughHandler = (e) => {
        const target = e.target;

        if (
          target &&
          target.classList &&
          target.classList.contains("frieren-decoration")
        ) {
          const m = target.className.match(/frieren-decoration\s+(\w+)/);
          const type = m && m[1] ? m[1] : null;
          if (type && this.isPixelHit(type, target, e)) {
            return;
          }
        }

        const rect = imgContainer.getBoundingClientRect();
        const x = e.clientX - rect.left;
        const y = e.clientY - rect.top;

        const ordered = this.frierenDecorations
          .map((d, idx) => {
            if (!d || !d.parentNode) return null;
            const z = parseInt(window.getComputedStyle(d).zIndex || "0", 10);
            return { d, idx, z: isNaN(z) ? 0 : z };
          })
          .filter(Boolean)
          .sort((a, b) => a.z - b.z || a.idx - b.idx);

        for (let i = ordered.length - 1; i >= 0; i--) {
          const decoration = ordered[i].d;
          const decRect = decoration.getBoundingClientRect();
          const decX = decRect.left - rect.left;
          const decY = decRect.top - rect.top;

          if (
            x >= decX &&
            x <= decX + decRect.width &&
            y >= decY &&
            y <= decY + decRect.height
          ) {
            const m = decoration.className.match(/frieren-decoration\s+(\w+)/);
            const type = m && m[1] ? m[1] : null;
            if (type && this.isPixelHit(type, decoration, e)) {
              e.stopPropagation();
              e.preventDefault();
              this.handleDecorationClick(type);
              return;
            }
          }
        }
      };

      imgContainer.addEventListener(
        "click",
        this._decorationClickThroughHandler,
        true
      );
    },

    /**
     * 添加芙莉蓮裝飾
     * @param {Object} config - 裝飾配置 { type, src, top, left, right, bottom, width, height, transform, zIndex, opacity }
     */
    addFrierenDecoration: function (config) {
      if (!this.isFrierenMode) return;

      const imgContainer = document.getElementById("ukagaka_img");
      if (!imgContainer) return;

      // 檢查是否已存在相同類型的裝飾
      const existing = imgContainer.querySelector(
        ".frieren-decoration." + config.type
      );
      if (existing) {
        existing.remove();
      }

      const decoration = document.createElement("img");
      decoration.className = "frieren-decoration " + config.type;
      decoration.src = config.src;
      decoration.alt = config.type || "decoration";

      let styleString = "position: absolute; pointer-events: auto;";
      if (config.top !== undefined) styleString += " top: " + config.top + ";";
      if (config.right !== undefined && config.right !== "auto")
        styleString += " right: " + config.right + ";";
      if (config.bottom !== undefined)
        styleString += " bottom: " + config.bottom + ";";
      if (
        config.left !== undefined &&
        (config.right === undefined || config.right === "auto")
      )
        styleString += " left: " + config.left + ";";
      if (config.width !== undefined)
        styleString += " width: " + config.width + ";";
      if (config.height !== undefined)
        styleString += " height: " + config.height + ";";
      if (config.transform !== undefined)
        styleString += " transform: " + config.transform + ";";
      styleString +=
        " z-index: " +
        (config.zIndex !== undefined ? config.zIndex : "10") +
        ";";
      if (config.opacity !== undefined) {
        styleString += " opacity: " + config.opacity + ";";
      }

      decoration.style.cssText = styleString;

      decoration.addEventListener("load", () => {
        this.createHitCanvas(config.type, decoration);
      });

      decoration.addEventListener("click", (e) => {
        if (!this.isPixelHit(config.type, decoration, e)) {
          if (typeof mpuLogger !== "undefined" && mpuLogger.log) {
            mpuLogger.log("點擊到透明區域，忽略:", config.type);
          }
          return;
        }

        e.stopPropagation();
        if (typeof mpuLogger !== "undefined" && mpuLogger.log) {
          mpuLogger.log("裝飾物被點擊（像素命中）:", config.type);
        }
        this.handleDecorationClick(config.type);
      });

      const canvas = imgContainer.querySelector("canvas");
      const frierenImg = imgContainer.querySelector("#frieren_idle_apng");
      const referenceElement = frierenImg || canvas;

      if (referenceElement && referenceElement.parentNode) {
        referenceElement.parentNode.insertBefore(decoration, referenceElement);
      } else {
        imgContainer.appendChild(decoration);
      }

      this.frierenDecorations.push(decoration);
    },

    /**
     * 為裝飾物創建像素檢測用的隱藏 Canvas
     * @param {string} type - 裝飾物類型
     * @param {HTMLImageElement} imgElement - 裝飾物圖片元素
     */
    createHitCanvas: function (type, imgElement) {
      if (
        !imgElement ||
        !imgElement.complete ||
        imgElement.naturalWidth === 0
      ) {
        return;
      }

      const hitCanvas = document.createElement("canvas");
      hitCanvas.width = imgElement.naturalWidth;
      hitCanvas.height = imgElement.naturalHeight;

      const ctx = hitCanvas.getContext("2d", { willReadFrequently: true });
      if (!ctx) {
        console.error("[MP Ukagaka] 無法創建像素檢測 Canvas:", type);
        return;
      }

      ctx.drawImage(imgElement, 0, 0);

      this.decorationHitCanvases.set(type, {
        canvas: hitCanvas,
        ctx: ctx,
        width: hitCanvas.width,
        height: hitCanvas.height,
      });

      if (typeof mpuLogger !== "undefined" && mpuLogger.log) {
        mpuLogger.log(
          "像素檢測 Canvas 已創建:",
          type,
          hitCanvas.width + "x" + hitCanvas.height
        );
      }
    },

    /**
     * 檢測點擊位置是否命中不透明像素
     * @param {string} type - 裝飾物類型
     * @param {HTMLImageElement} imgElement - 裝飾物圖片元素
     * @param {MouseEvent} event - 滑鼠事件
     * @returns {boolean} - 是否命中不透明像素
     */
    isPixelHit: function (type, imgElement, event) {
      const hitData = this.decorationHitCanvases.get(type);

      if (!hitData || !hitData.ctx) {
        return true;
      }

      const rect = imgElement.getBoundingClientRect();
      const clickX = event.clientX - rect.left;
      const clickY = event.clientY - rect.top;

      const scaleX = hitData.width / rect.width;
      const scaleY = hitData.height / rect.height;
      const pixelX = Math.floor(clickX * scaleX);
      const pixelY = Math.floor(clickY * scaleY);

      if (
        pixelX < 0 ||
        pixelX >= hitData.width ||
        pixelY < 0 ||
        pixelY >= hitData.height
      ) {
        return false;
      }

      try {
        const imageData = hitData.ctx.getImageData(pixelX, pixelY, 1, 1);
        const alpha = imageData.data[3];

        if (typeof mpuLogger !== "undefined" && mpuLogger.log) {
          mpuLogger.log(
            "像素檢測:",
            type,
            "x=" + pixelX,
            "y=" + pixelY,
            "alpha=" + alpha,
            "threshold=" + this.pixelHitThreshold
          );
        }

        return alpha > this.pixelHitThreshold;
      } catch (e) {
        console.warn(
          "[MP Ukagaka] 無法獲取像素數據（可能是跨域問題）:",
          type,
          e.message
        );
        return true;
      }
    },

    /**
     * 移除芙莉蓮裝飾
     * @param {string} type - 裝飾類型
     */
    removeFrierenDecoration: function (type) {
      const imgContainer = document.getElementById("ukagaka_img");
      if (!imgContainer) return;

      const decoration = imgContainer.querySelector(
        ".frieren-decoration." + type
      );
      if (decoration) {
        decoration.remove();
        this.frierenDecorations = this.frierenDecorations.filter(
          (d) => d !== decoration
        );
        this.decorationHitCanvases.delete(type);
      }
    },

    /**
     * 清除所有裝飾
     */
    clearFrierenDecorations: function () {
      this.frierenDecorations.forEach((decoration) => {
        if (decoration.parentNode) {
          decoration.parentNode.removeChild(decoration);
        }
      });
      this.frierenDecorations = [];
      this.decorationHitCanvases.clear();
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
      if (this.isSleepMessage() && !this.sleepModeAwoken) {
        this.sleepModeAwoken = true;
        if (typeof mpuLogger !== "undefined" && mpuLogger.log) {
          mpuLogger.log("☀️ 芙莉蓮被喚醒了！");
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
        mpuLogger.log("👀 播放醒來動畫 frieren[w1-w5].png");
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
            mpuLogger.log("🌅 開始喚醒動畫, skipBookFlip =", skipBookFlip);
          }
          this.playWakeUpAnimation(function () {
            if (!skipBookFlip) {
              if (typeof mpuLogger !== "undefined" && mpuLogger.log) {
                mpuLogger.log("📖 喚醒後播放翻書動畫");
              }
              self.playFrierenBookFlipAnimation();
            } else {
              if (typeof mpuLogger !== "undefined" && mpuLogger.log) {
                mpuLogger.log("📖 喚醒後跳過翻書動畫");
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
          mpuLogger.log("🌙 睡眠模式：跳過翻書動畫");
        }
        return false;
      }

      if (
        !window.mpuCanvasManager ||
        !window.mpuCanvasManager.imagesLoaded ||
        !this.frierenImages ||
        this.frierenImages.length < 13
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

    /**
     * 處理裝飾物點擊事件
     * @param {string} decorationType - 裝飾物類型
     */
    handleDecorationClick: function (decorationType) {
      if (typeof mpuLogger !== "undefined" && mpuLogger.log) {
        mpuLogger.log(
          "handleDecorationClick 被調用，裝飾物類型:",
          decorationType
        );
        mpuLogger.log(
          "mpuAiEnabled:",
          typeof mpuAiEnabled !== "undefined" ? mpuAiEnabled : "undefined"
        );
      }

      if (this.decorationChatInProgress) {
        if (typeof mpuLogger !== "undefined" && mpuLogger.log) {
          mpuLogger.log("裝飾物對話正在進行中，忽略本次點擊");
        }
        return;
      }

      if (typeof mpuAiEnabled === "undefined" || !mpuAiEnabled) {
        if (typeof mpuLogger !== "undefined" && mpuLogger.log) {
          mpuLogger.log("AI 功能未啟用，跳過裝飾物對話");
        }
        return;
      }

      this.decorationChatInProgress = true;

      if (typeof window !== "undefined") {
        window.mpuMessageBlocking = true;
      }

      if (typeof stopAutoTalk !== "undefined") {
        stopAutoTalk();
      }

      if (typeof mpuCancelRequest !== "undefined") {
        mpuCancelRequest("", { requestId: "mpu_nextmsg_llm" });
      }
      if (typeof mpuRequestManager !== "undefined") {
        mpuRequestManager.activeRequests.forEach((controller, requestId) => {
          if (requestId.includes("mpu_nextmsg")) {
            controller.abort();
            mpuRequestManager.activeRequests.delete(requestId);
            if (typeof mpuLogger !== "undefined" && mpuLogger.log) {
              mpuLogger.log("裝飾物點擊：已取消請求", requestId);
            }
          }
        });
      }

      if (typeof mpu_cancelTypewriter !== "undefined") {
        mpu_cancelTypewriter();
      }

      if (typeof mpuOllamaRequesting !== "undefined") {
        window.mpuOllamaRequesting = false;
      }
      if (typeof mpuOllamaRequestQueue !== "undefined") {
        window.mpuOllamaRequestQueue = [];
      }

      this.setDecorationsClickable(false);

      const executeAjaxReq = () => {
        const formData = new FormData();
        formData.append("decoration_type", decorationType);

        if (typeof mpuFetch !== "undefined" && typeof mpuRestUrl !== "undefined") {
          mpuFetch(mpuRestUrl + "touch/decoration", {
            method: "POST",
            body: formData,
            timeout: 30000,
            retries: 1,
            requestId: "mpu_decoration_chat_" + decorationType,
          })
            .then((res) => {
              if (jQuery("#ukagaka_msgbox").is(":hidden") && typeof mpu_showmsg !== "undefined") {
                mpu_showmsg(400);
              }

              if (res && res.msg && !res.error) {
                if (typeof mpu_typewriter !== "undefined") {
                  mpu_typewriter(res.msg, "#ukagaka_msg");
                }

                if (this.isFrierenMode) {
                  this.triggerFrierenSpeaking(true);
                }

                if (res.emoji && typeof window.mpuEmojiManager !== "undefined") {
                  window.mpuEmojiManager.showEmoji(res.emoji);
                }

                this.waitForTypewriterAndRestore();
              } else {
                const errorMsg = res?.error || "發生錯誤，請稍後再試";
                if (typeof mpu_typewriter !== "undefined") {
                  mpu_typewriter(errorMsg, "#ukagaka_msg");
                }

                this.waitForTypewriterAndRestore();
              }
            })
            .catch((error) => {
              if (jQuery("#ukagaka_msgbox").is(":hidden") && typeof mpu_showmsg !== "undefined") {
                mpu_showmsg(400);
              }

              if (typeof mpuLogger !== "undefined" && mpuLogger.error) {
                mpuLogger.error("裝飾物對話請求失敗:", error);
              }
              if (typeof mpu_typewriter !== "undefined") {
                mpu_typewriter("（…連線好像有點問題…）", "#ukagaka_msg");
              }

              this.waitForTypewriterAndRestore();
            });
        }
      };

      const $msgbox = jQuery("#ukagaka_msgbox");
      const isAsleep = this.isSleepMessage();

      // 如果處於睡眠模式，觸發喚醒動畫
      if (isAsleep) {
        if ($msgbox.is(":visible")) {
          $msgbox.fadeOut(1000, () => {
            if (typeof mpu_send_wake_up_request === "function") {
              mpu_send_wake_up_request();
            }
            this.triggerFrierenSpeaking(true, null);
            
            // 清空對話框準備顯示新訊息
            jQuery("#ukagaka_msg").html("");
            executeAjaxReq();
          });
        } else {
          if (typeof mpu_send_wake_up_request === "function") {
            mpu_send_wake_up_request();
          }
          this.triggerFrierenSpeaking(true, null);
          executeAjaxReq();
        }
      } else {
        // 一般模式：顯示思考中
        if ($msgbox.is(":visible")) {
          $msgbox.fadeOut(400, () => {
            jQuery("#ukagaka_msg").html(
              '（…えっと<span class="mpu-thinking"></span>）'
            );
            $msgbox.fadeIn(400);
            executeAjaxReq();
          });
        } else {
          jQuery("#ukagaka_msg").html(
            '（…えっと<span class="mpu-thinking"></span>）'
          );
          if (typeof mpu_showmsg !== "undefined") {
            mpu_showmsg(400);
          }
          executeAjaxReq();
        }
      }
    },

    /**
     * 等待打字效果完成後恢復狀態
     */
    waitForTypewriterAndRestore: function () {
      const self = this;

      if (typeof mpu_waitForTypewriterComplete !== "undefined") {
        mpu_waitForTypewriterComplete(function () {
          setTimeout(function () {
            self.restoreAfterDecorationChat();
          }, 2000);
        });
      } else {
        const msgElement = document.querySelector("#ukagaka_msg");
        let estimatedTime = 3000;

        if (msgElement) {
          const msgText = msgElement.textContent || msgElement.innerText || "";
          const typewriterSpeed =
            typeof mpuTypewriterSpeed !== "undefined" && mpuTypewriterSpeed
              ? mpuTypewriterSpeed
              : 40;
          estimatedTime = Math.max(
            2000,
            msgText.length * typewriterSpeed + 500
          );
        }

        setTimeout(function () {
          self.restoreAfterDecorationChat();
        }, estimatedTime);
      }
    },

    /**
     * 恢復裝飾物對話後的狀態
     */
    restoreAfterDecorationChat: function () {
      this.decorationChatInProgress = false;

      if (typeof window !== "undefined") {
        window.mpuMessageBlocking = false;
      }

      this.setDecorationsClickable(true);

      if (
        typeof mpuAutoTalk !== "undefined" &&
        mpuAutoTalk &&
        typeof startAutoTalk !== "undefined"
      ) {
        startAutoTalk();
      }

      if (typeof mpuLogger !== "undefined" && mpuLogger.log) {
        mpuLogger.log("裝飾物對話完成，狀態已恢復");
      }
    },

    /**
     * 設置裝飾物是否可點擊
     * @param {boolean} clickable - 是否可點擊
     */
    setDecorationsClickable: function (clickable) {
      this.frierenDecorations.forEach((decoration) => {
        if (decoration && decoration.style) {
          if (clickable) {
            decoration.style.pointerEvents = "auto";
            decoration.style.cursor = "pointer";
            decoration.style.opacity = "1.0";
          } else {
            decoration.style.pointerEvents = "none";
            decoration.style.cursor = "not-allowed";
            decoration.style.opacity = "1.0";
          }
        }
      });
    },

    /**
     * 檢測觸摸區域
     * @param {MouseEvent} event - 滑鼠事件
     * @param {HTMLElement} element - 被點擊的元素
     * @returns {string|null} - 區域名稱或 null
     */
    detectTouchZone: function (event, element) {
      if (
        !element ||
        typeof mpuTouchZones === "undefined" ||
        !mpuTouchZones.zones
      ) {
        return null;
      }

      const rect = element.getBoundingClientRect();
      const clickY = event.clientY - rect.top;
      const relativeY = clickY / rect.height;

      for (const [zoneName, zone] of Object.entries(mpuTouchZones.zones)) {
        if (zoneName.startsWith("_")) continue;
        if (relativeY >= zone.yStart && relativeY < zone.yEnd) {
          if (typeof mpuLogger !== "undefined" && mpuLogger.log) {
            mpuLogger.log(
              "觸摸區域檢測:",
              zoneName,
              "relativeY=",
              relativeY.toFixed(2)
            );
          }
          return zoneName;
        }
      }

      return mpuTouchZones.default_reaction ? "body" : null;
    },

    /**
     * 處理角色觸摸事件
     * @param {string} zoneName - 區域名稱
     */
    handleTouchZone: function (zoneName) {
      if (typeof mpuLogger !== "undefined" && mpuLogger.log) {
        mpuLogger.log("handleTouchZone 被調用，區域:", zoneName);
      }

      if (this.decorationChatInProgress) {
        return;
      }

      if (this.isZoneInCooldown(zoneName)) {
        if (typeof mpuLogger !== "undefined" && mpuLogger.log) {
          mpuLogger.log("區域冷卻中，忽略點擊:", zoneName);
        }
        return;
      }

      if (this.recordZoneClick(zoneName)) {
        if (typeof mpuLogger !== "undefined" && mpuLogger.log) {
          mpuLogger.log("區域達到點擊上限，進入冷卻:", zoneName);
        }
        return;
      }

      if (typeof mpuAiEnabled === "undefined" || !mpuAiEnabled) {
        return;
      }

      this.decorationChatInProgress = true;

      if (typeof window !== "undefined") {
        window.mpuMessageBlocking = true;
      }

      if (typeof stopAutoTalk !== "undefined") {
        stopAutoTalk();
      }

      if (typeof mpuCancelRequest !== "undefined") {
        mpuCancelRequest("", { requestId: "mpu_nextmsg_llm" });
      }
      if (typeof mpuRequestManager !== "undefined") {
        mpuRequestManager.activeRequests.forEach((controller, requestId) => {
          if (requestId.includes("mpu_nextmsg")) {
            controller.abort();
            mpuRequestManager.activeRequests.delete(requestId);
            if (typeof mpuLogger !== "undefined" && mpuLogger.log) {
              mpuLogger.log("觸摸區域點擊：已取消請求", requestId);
            }
          }
        });
      }

      if (typeof mpu_cancelTypewriter !== "undefined") {
        mpu_cancelTypewriter();
      }

      if (typeof mpuOllamaRequesting !== "undefined") {
        window.mpuOllamaRequesting = false;
      }
      if (typeof mpuOllamaRequestQueue !== "undefined") {
        window.mpuOllamaRequestQueue = [];
      }

      const self = this;

      const executeAjaxReq = () => {
        const formData = new FormData();
        formData.append("touch_zone", zoneName);

        if (typeof mpuFetch !== "undefined" && typeof mpuRestUrl !== "undefined") {
          mpuFetch(mpuRestUrl + "touch/zone", {
            method: "POST",
            body: formData,
            timeout: 30000,
            retries: 1,
            requestId: "mpu_touch_zone_" + zoneName,
          })
            .then((res) => {
              if (jQuery("#ukagaka_msgbox").is(":hidden") && typeof mpu_showmsg !== "undefined") {
                mpu_showmsg(400);
              }

              if (res && res.msg && !res.error) {
                if (typeof mpu_typewriter !== "undefined") {
                  mpu_typewriter(res.msg, "#ukagaka_msg");
                }

                if (self.isFrierenMode) {
                  self.triggerFrierenSpeaking(true);
                }

                if (res.emoji && typeof mpuEmojiManager !== "undefined") {
                  mpuEmojiManager.showEmoji(res.emoji);
                }
              }

              self.scheduleRestoreAfterChat();
            })
            .catch((err) => {
              if (jQuery("#ukagaka_msgbox").is(":hidden") && typeof mpu_showmsg !== "undefined") {
                mpu_showmsg(400);
              }

              console.error("觸摸區域對話請求失敗:", err);
              self.restoreAfterDecorationChat();
            });
        }
      };

      const $msgbox = jQuery("#ukagaka_msgbox");
      const isAsleep = this.isSleepMessage();

      // 如果處於睡眠模式，觸發喚醒動畫
      if (isAsleep) {
        if ($msgbox.is(":visible")) {
          $msgbox.fadeOut(1000, () => {
            if (typeof mpu_send_wake_up_request === "function") {
              mpu_send_wake_up_request();
            }
            self.triggerFrierenSpeaking(true, null);
            
            // 清空對話框準備顯示新訊息
            jQuery("#ukagaka_msg").html("");
            executeAjaxReq();
          });
        } else {
          if (typeof mpu_send_wake_up_request === "function") {
            mpu_send_wake_up_request();
          }
          self.triggerFrierenSpeaking(true, null);
          executeAjaxReq();
        }
      } else {
        // 一般模式：顯示思考中
        if ($msgbox.is(":visible")) {
          $msgbox.fadeOut(400, () => {
            jQuery("#ukagaka_msg").html(
              '（…えっと<span class="mpu-thinking"></span>）'
            );
            $msgbox.fadeIn(400);
            executeAjaxReq();
          });
        } else {
          jQuery("#ukagaka_msg").html(
            '（…えっと<span class="mpu-thinking"></span>）'
          );
          if (typeof mpu_showmsg !== "undefined") {
            mpu_showmsg(400);
          }
          executeAjaxReq();
        }
      }
    },

    /**
     * 設置角色觸摸事件監聽
     */
    setupCharacterTouchEvents: function () {
      const imgContainer = document.getElementById("ukagaka_img");
      if (!imgContainer) return;

      const self = this;

      imgContainer.addEventListener("mousemove", function (e) {
        const target = e.target;

        if (target.id !== "frieren_idle_apng" && target.id !== "cur_ukagaka") {
          return;
        }

        const zone = self.detectTouchZone(e, target);
        if (zone) {
          const cursorMap = {
            head: "grab",
            face: "pointer",
            chest: "not-allowed",
            book: "help",
            legs: "pointer",
          };
          target.style.cursor = cursorMap[zone] || "pointer";
        } else {
          target.style.cursor = "default";
        }
      });

      imgContainer.addEventListener(
        "click",
        function (e) {
          const target = e.target;

          if (
            target.id !== "frieren_idle_apng" &&
            target.id !== "cur_ukagaka"
          ) {
            return;
          }

          const zone = self.detectTouchZone(e, target);
          if (zone) {
            e.stopPropagation();
            e.preventDefault();
            self.handleTouchZone(zone);
          }
        },
        true
      );

      if (typeof mpuLogger !== "undefined" && mpuLogger.log) {
        mpuLogger.log("角色觸摸事件已設置");
      }
    },

    /**
     * 延遲恢復對話完成後的狀態
     */
    scheduleRestoreAfterChat: function () {
      const self = this;

      if (typeof mpu_waitForTypewriterComplete !== "undefined") {
        mpu_waitForTypewriterComplete(function () {
          setTimeout(function () {
            self.restoreAfterDecorationChat();
          }, 2000);
        });
      } else {
        setTimeout(function () {
          self.restoreAfterDecorationChat();
        }, 3000);
      }
    },

    /**
     * 檢查區域是否在冷卻中
     * @param {string} zoneName - 區域名稱
     * @returns {boolean} - 是否在冷卻中
     */
    isZoneInCooldown: function (zoneName) {
      const cooldownEnd = this.touchZoneCooldown[zoneName];
      if (!cooldownEnd) return false;

      const now = Date.now();
      if (now < cooldownEnd) {
        return true;
      }

      delete this.touchZoneCooldown[zoneName];
      delete this.touchZoneClicks[zoneName];
      return false;
    },

    /**
     * 記錄區域點擊，並檢查是否需要進入冷卻
     * @param {string} zoneName - 區域名稱
     * @returns {boolean} - 是否剛進入冷卻（true = 應該忽略這次點擊）
     */
    recordZoneClick: function (zoneName) {
      const limit = this.touchZoneLimits[zoneName];
      if (!limit) return false;

      const now = Date.now();

      if (!this.touchZoneClicks[zoneName]) {
        this.touchZoneClicks[zoneName] = [];
      }

      this.touchZoneClicks[zoneName] = this.touchZoneClicks[zoneName].filter(
        (timestamp) => now - timestamp < limit.windowMs
      );

      this.touchZoneClicks[zoneName].push(now);

      if (this.touchZoneClicks[zoneName].length >= limit.maxClicks) {
        this.touchZoneCooldown[zoneName] = now + limit.cooldownMs;

        if (typeof mpuLogger !== "undefined" && mpuLogger.log) {
          mpuLogger.log(
            "區域進入冷卻:",
            zoneName,
            "冷卻時間:",
            limit.cooldownMs / 1000,
            "秒"
          );
        }

        return true;
      }

      return false;
    },
  };

  window.mpuFrierenManager = mpuFrierenManager;
})();
