/**
 * MP Ukagaka 芙莉蓮裝飾物模組
 *
 * 擴展 frieren.js 建立的 window.mpuFrierenManager，負責裝飾物載入、
 * 像素命中判定與裝飾 DOM 管理。
 */

(function () {
  "use strict";

  function warnFrieren(key, fallback, ...args) {
    if (typeof mpuLogger === "undefined") {
      return;
    }
    if (args.length > 0 && typeof mpuLogger.warnAlwaysF === "function") {
      mpuLogger.warnAlwaysF(key, fallback, ...args);
    } else if (typeof mpuLogger.warnAlways === "function") {
      mpuLogger.warnAlways(key, fallback);
    }
  }

  const manager = window.mpuFrierenManager;
  if (!manager) {
    warnFrieren("frierenDecorationsManagerMissing", "Frieren manager が見つからないため、装飾モジュールを初期化できません");
    return;
  }

  Object.assign(manager, {
    /**
     * 載入裝飾物（透過 AJAX 動態載入配置）
     */
    loadFrierenDecorations: function () {
      const self = this;
      const imgContainer = document.getElementById("ukagaka_img");
      if (!imgContainer) {
        warnFrieren("frierenDecorationsContainerMissing", "#ukagaka_img が見つからないため、装飾を読み込めません");
        return;
      }

      // 防止重複載入（已載入過就跳過）
      if (this._decorationsLoaded) {
        if (typeof mpuLogger !== "undefined" && mpuLogger.log) {
          mpuLogger.logL("frierenDecorationsAlreadyLoaded", "装飾品は読み込み済みのため、重複読み込みをスキップします");
        }
        return;
      }

      if (window.mpuDecorationConfig !== undefined && window.mpuDecorationsBaseUrl !== undefined) {
        if (!window.mpuShowDecorations) {
          if (typeof mpuLogger !== "undefined" && mpuLogger.log) {
            mpuLogger.logL("frierenDecorationsDisabled", "装飾表示が無効のため、装飾を読み込みません");
          }
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
              if (response.error) {
                mpuLogger.warnAlwaysF('frierenDecorationConfigLoadFailed', '装飾設定を読み込めませんでした：%s', response.error);
              } else {
                mpuLogger.warnAlways('frierenDecorationConfigLoadFailedUnknown', '装飾設定を読み込めませんでした：不明なエラー');
              }
            }
          },
          error: function(xhr, status, error) {
            mpuLogger.errorF('frierenDecorationConfigAjaxFailed', 'AJAX による装飾設定の読み込みに失敗しました：%s', error);
          }
        });
      } else {
        mpuLogger.warnAlways('frierenDecorationConfigRuntimeUnavailable', 'jQuery または mpuurl が利用できないため、装飾設定を読み込めません');
      }
    },

    /**
     * 從配置載入裝飾物（內部方法）
     * @param {string} decorationsBaseUrl - 裝飾圖片基礎 URL
     * @param {Array} decorationConfig - 裝飾配置陣列
     */
    _loadDecorationsFromConfig: function(decorationsBaseUrl, decorationConfig) {
      if (!decorationsBaseUrl || !Array.isArray(decorationConfig)) {
        mpuLogger.warnAlways('frierenDecorationConfigInvalid', '装飾設定が無効です');
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
        mpuLogger.logF("frierenDecorationConfigLoaded", "JSON 設定から %s 個の装飾品を読み込みました", sortedConfig.length);
      }

      this.setupDecorationClickThrough();
    },

    /**
     * 設置點擊穿透：當點擊 canvas 或 img 時，檢查是否點擊到裝飾物區域
     * 使用事件委派綁定在容器上（capture），避免元素晚建立的問題
     */
    setupDecorationClickThrough: function () {
      const imgContainer = document.getElementById("ukagaka_img");
      if (!imgContainer) {
        warnFrieren("frierenDecorationClickThroughContainerMissing", "#ukagaka_img が見つからないため、装飾クリック判定を設定できません");
        return;
      }

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
      if (!this.isFrierenMode) {
        warnFrieren("frierenDecorationAddSkippedInactiveMode", "Frieren mode が有効ではないため、装飾を追加できません");
        return;
      }

      const imgContainer = document.getElementById("ukagaka_img");
      if (!imgContainer) {
        warnFrieren("frierenDecorationAddContainerMissing", "#ukagaka_img が見つからないため、装飾を追加できません");
        return;
      }

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
            mpuLogger.logF("frierenDecorationTransparentClickIgnored", "透明領域がクリックされたため無視します：%s", config.type);
          }
          return;
        }

        e.stopPropagation();
        if (typeof mpuLogger !== "undefined" && mpuLogger.log) {
          mpuLogger.logF("frierenDecorationPixelHitClicked", "装飾品がクリックされました（ピクセルヒット）：%s", config.type);
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
        mpuLogger.errorF('frierenPixelCanvasCreateFailed', 'ピクセル判定用 Canvas を作成できません：%s', type);
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
        mpuLogger.logF("frierenPixelDetectionCanvasCreated", "ピクセル検出 Canvas を作成しました：%1$s、%2$s", type, hitCanvas.width, hitCanvas.height);
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
          mpuLogger.logF("frierenPixelDetectionSample", "ピクセル検出：%1$s、x=%2$s、y=%3$s、alpha=%4$s、threshold=%5$s", type, pixelX, pixelY, alpha, this.pixelHitThreshold);
        }

        return alpha > this.pixelHitThreshold;
      } catch (e) {
        mpuLogger.warnAlwaysF(
          'frierenPixelDataUnavailable',
          'ピクセルデータを取得できません（クロスオリジンの可能性があります）：タイプ=%1$s、メッセージ=%2$s',
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
      if (!imgContainer) {
        warnFrieren("frierenDecorationRemoveContainerMissing", "#ukagaka_img が見つからないため、装飾を削除できません：%s", type);
        return;
      }

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
  });
})();
