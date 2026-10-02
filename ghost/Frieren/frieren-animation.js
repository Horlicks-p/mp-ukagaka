/**
 * MP Ukagaka 芙莉蓮動畫模組
 *
 * 擴展 frieren.js 建立的 window.mpuFrierenManager，負責 SVG 幀序列
 * （shell/Frieren/assets.json）的載入、閒置／睡眠循環、翻書動畫、
 * 睡眠判定與喚醒動畫。所有序列都在同一個 <img> 上換幀顯示。
 */

(function () {
  "use strict";

  const manager = window.mpuFrierenManager;
  if (!manager) {
    return;
  }

  Object.assign(manager, {
    /**
     * 讀取 shell/Frieren/assets.json，建立 SVG 幀序列後開始預載。
     * 讀不到或格式不對時記錄錯誤並照常顯示容器，不讓頁面停在隱藏狀態。
     * @param {string} baseUrl - shell 資料夾 URL（以 / 結尾）
     */
    loadFrierenAssets: function (baseUrl) {
      if (!window.mpuCanvasManager || !window.mpuCanvasManager.canvas) {
        mpuLogger.errorL('frierenImageCanvasManagerMissing', '画像読み込み前に Canvas マネージャーが初期化されていません');
        return;
      }

      fetch(baseUrl + "assets.json", { credentials: "same-origin" })
        .then(function (response) {
          if (!response.ok) {
            throw new Error("HTTP " + response.status);
          }
          return response.json();
        })
        .then(function (assets) {
          this.applyFrierenAssets(assets, baseUrl);
          this.loadFrierenImages();
        }.bind(this))
        .catch(function (error) {
          mpuLogger.errorF('frierenAssetManifestLoadFailed', 'フリーレンの表示資産マニフェストを読み込めません：%s', error && error.message ? error.message : String(error));
          this.revealFrierenContainer();
        }.bind(this));
    },

    /**
     * 驗證 assets.json 並展開為各序列的幀清單與 URL。
     * @param {Object} assets - assets.json 內容
     * @param {string} baseUrl - shell 資料夾 URL
     */
    applyFrierenAssets: function (assets, baseUrl) {
      if (
        !assets ||
        assets.format_version !== 1 ||
        !assets.layout ||
        !Array.isArray(assets.layout.box) ||
        !Array.isArray(assets.layout.frame) ||
        !assets.sequences ||
        !assets.sequences.idle ||
        !Array.isArray(assets.sequences.idle.frames) ||
        assets.sequences.idle.frames.length === 0
      ) {
        throw new Error("invalid assets.json");
      }

      this.frierenAssets = assets;
      this.frierenLayout = assets.layout;
      this.frierenSequences = {};
      this.frierenSequenceLoads = {};
      this.frierenSequenceState = {};

      Object.keys(assets.sequences).forEach(function (name) {
        const seq = assets.sequences[name];
        this.frierenSequences[name] = (seq.frames || []).map(function (frame) {
          return {
            src: baseUrl + frame.src,
            duration: Math.max(16, Number(frame.duration_ms) || 100),
            img: null,
          };
        });
      }, this);

      const urls = function (name) {
        return (this.frierenSequences[name] || []).map(function (frame) {
          return frame.src;
        });
      }.bind(this);
      this.frierenIdleImage = urls("idle")[0] || null;
      this.frierenSleepImage = urls("sleep")[0] || null;
      this.frierenWakeUpImages = urls("wake");
      this.frierenBookFlipImages = urls("book_flip");
    },

    /**
     * 預載一個序列的全部幀（含 decode）。任一幀失敗即整個序列視為失敗，
     * 不以缺幀播放錯誤的動作。
     * @param {string} name - 序列名稱
     * @returns {Promise} 成功 resolve、失敗 reject
     */
    loadFrierenSequence: function (name) {
      if (this.frierenSequenceLoads[name]) {
        return this.frierenSequenceLoads[name];
      }
      const frames = this.frierenSequences[name];
      if (!frames || frames.length === 0) {
        this.frierenSequenceState[name] = "failed";
        return Promise.reject(new Error("no frames: " + name));
      }

      this.frierenSequenceState[name] = "loading";
      const self = this;
      const load = Promise.all(
        frames.map(function (frame) {
          return new Promise(function (resolve, reject) {
            const img = new Image();
            img.onload = function () {
              const decoded = typeof img.decode === "function" ? img.decode().catch(function () {}) : Promise.resolve();
              decoded.then(resolve);
            };
            img.onerror = function () {
              mpuLogger.errorF('frierenImageLoadFailed', 'フリーレン画像の読み込みに失敗しました：%s', frame.src);
              reject(new Error(frame.src));
            };
            img.src = frame.src;
            frame.img = img;
          });
        })
      ).then(
        function () {
          self.frierenSequenceState[name] = "ready";
        },
        function (error) {
          self.frierenSequenceState[name] = "failed";
          mpuLogger.errorF('frierenSequenceLoadFailed', 'フリーレンのアニメーション序列を読み込めません：%s', name);
          throw error;
        }
      );
      this.frierenSequenceLoads[name] = load;
      return load;
    },

    /**
     * @param {string} name - 序列名稱
     * @returns {boolean} 序列是否已可播放
     */
    isFrierenSequenceReady: function (name) {
      return this.frierenSequenceState[name] === "ready";
    },

    /**
     * 依初始狀態分段預載：先載入第一個要顯示的序列（閒置或睡眠）並顯示，
     * 之後在背景依序載入其餘序列（睡眠時優先醒來動畫）。
     */
    loadFrierenImages: function () {
      const sleeping = this.isSleepMessage() && !this.sleepModeAwoken;
      const first = sleeping && this.frierenSequences.sleep ? "sleep" : "idle";
      const rest = sleeping ? ["wake", "idle", "book_flip"] : ["book_flip"];

      this.applyFrierenBodyLayout(window.mpuCanvasManager.canvas);

      const self = this;
      const showThenLoadRest = function () {
        window.mpuCanvasManager.imagesLoaded = true;
        self.showFrierenIdle();
        rest.reduce(function (chain, name) {
          return chain.then(function () {
            return self.loadFrierenSequence(name).catch(function () {});
          });
        }, Promise.resolve());
      };

      this.loadFrierenSequence(first).then(showThenLoadRest, showThenLoadRest);
    },

    /**
     * 把人物元素（閒置 <img> 或動畫 Canvas）放到版面上。
     * 版面只佔 layout.box（原 134x249 人物框；裝飾位置、觸摸區、表情都以它為準），
     * SVG 幀依 layout.frame 縮放並向外溢出（四周留白與陰影），以負 margin 抵銷。
     * @param {HTMLElement} element - <img> 或 <canvas>
     */
    applyFrierenBodyLayout: function (element) {
      if (!element || !this.frierenLayout) {
        return;
      }
      const box = this.frierenLayout.box;
      const frame = this.frierenLayout.frame;
      const left = frame[0];
      const top = frame[1];
      const width = frame[2];
      const height = frame[3];
      const set = function (prop, value) {
        element.style.setProperty(prop, value, "important");
      };
      set("width", width + "px");
      set("height", height + "px");
      set("max-width", "none");
      set("margin", top + "px " + (box[0] - left - width) + "px " + (box[1] - top - height) + "px " + left + "px");
      // 人物框在元素內的位置與大小（mpuGetCharacterRect 用）
      element.dataset.mpuBodyBox = [-left, -top, box[0], box[1]].join(",");

      if (element.tagName === "CANVAS") {
        const dpr = Math.max(1, window.devicePixelRatio || 1);
        const backingWidth = Math.round(width * dpr);
        const backingHeight = Math.round(height * dpr);
        if (element.width !== backingWidth || element.height !== backingHeight) {
          element.width = backingWidth;
          element.height = backingHeight;
        }
      }
    },

    /**
     * 清除 applyFrierenBodyLayout 加在元素上的版面樣式（切換到其他角色時）。
     * @param {HTMLElement} element
     */
    clearFrierenBodyLayout: function (element) {
      if (!element) {
        return;
      }
      ["width", "height", "max-width", "margin"].forEach(function (prop) {
        element.style.removeProperty(prop);
      });
      delete element.dataset.mpuBodyBox;
    },

    /**
     * 取得（必要時建立）顯示芙莉蓮本體的 <img>。閒置、睡眠、翻書、醒來都以它
     * 換幀顯示：<img> 會依 SVG 的 crispEdges 在顯示尺寸上點陣化，而 Canvas
     * drawImage 會忽略它，非整數縮放時每個色塊邊緣都變半透明（淡化與殘像）。
     * @returns {HTMLImageElement|null}
     */
    ensureFrierenBodyImg: function () {
      if (this.frierenIdleImgElement) {
        return this.frierenIdleImgElement;
      }
      const imgContainer = document.getElementById("ukagaka_img");
      if (!imgContainer) {
        return null;
      }
      // 先嘗試從 DOM 中獲取，避免 SPA 重載時建立重複元素
      const existingImg = document.getElementById("frieren_idle_apng");
      if (existingImg) {
        this.frierenIdleImgElement = existingImg;
      } else {
        const img = document.createElement("img");
        img.id = "frieren_idle_apng";
        img.style.display = "none";
        img.style.opacity = String(this.frierenIdleOpacity);
        img.style.cursor = "pointer";
        if (window.mpuCanvasManager && window.mpuCanvasManager.currentCharacterName) {
          img.setAttribute("title", window.mpuCanvasManager.currentCharacterName);
          img.setAttribute("alt", window.mpuCanvasManager.currentCharacterName);
        }
        imgContainer.appendChild(img);
        this.frierenIdleImgElement = img;
      }
      this.applyFrierenBodyLayout(this.frierenIdleImgElement);
      return this.frierenIdleImgElement;
    },

    /**
     * 顯示一幀（換 <img> 的 src；幀都已預載並 decode）。
     * @param {Object} frame - frierenSequences 的幀
     */
    setFrierenFrame: function (frame) {
      const imgElement = this.frierenIdleImgElement;
      if (imgElement && imgElement.getAttribute("src") !== frame.src) {
        imgElement.setAttribute("src", frame.src);
      }
    },

    /**
     * 讓 <img> 成為可見的本體（Canvas 隱藏；它只保留給通用 Canvas 管理器）。
     */
    showFrierenBodyImg: function () {
      const imgElement = this.frierenIdleImgElement;
      if (!imgElement) {
        return;
      }
      imgElement.style.display = "block";
      imgElement.style.opacity = String(this.frierenIdleOpacity);
      if (window.mpuCanvasManager && window.mpuCanvasManager.canvas) {
        window.mpuCanvasManager.canvas.style.display = "none";
      }
    },

    /**
     * 以 <img> 循環播放閒置或睡眠序列（原 APNG 的時序寫在 assets.json）。
     * @param {string} name - idle | sleep
     */
    playFrierenLoop: function (name) {
      const frames = this.frierenSequences[name];
      const imgElement = this.frierenIdleImgElement;
      if (!frames || frames.length === 0 || !imgElement) {
        return;
      }
      const self = this;
      let index = 0;
      const step = function () {
        const frame = frames[index];
        self.setFrierenFrame(frame);
        if (frames.length < 2) {
          return;
        }
        self.frierenLoopTimer = setTimeout(function () {
          index = (index + 1) % frames.length;
          step();
        }, frame.duration);
      };
      step();
    },

    /**
     * 顯示芙莉蓮容器（序列無法播放時的安全收尾，避免頁面停在隱藏狀態）。
     */
    revealFrierenContainer: function () {
      const imgContainer = document.getElementById("ukagaka_img");
      if (imgContainer) imgContainer.style.visibility = "visible";
      const msgbox = document.getElementById("ukagaka_msgbox");
      if (msgbox) msgbox.style.visibility = "visible";
      if (typeof window.mpuMarkVisualReady === "function") {
        window.mpuMarkVisualReady("frieren");
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
     * 睡眠模式且未喚醒時循環播放睡眠序列，否則循環播放閒置序列
     */
    showFrierenIdle: function () {
      if (!this.isFrierenMode || !this.frierenIdleImage) {
        return;
      }

      this.stopFrierenAnimation();

      if (!this.ensureFrierenBodyImg()) {
        return;
      }

      const wantSleep = this.isSleepMessage() && !this.sleepModeAwoken && !!this.frierenSequences.sleep;
      const sequence = wantSleep ? "sleep" : "idle";

      if (!this.isFrierenSequenceReady(sequence)) {
        if (this.frierenSequenceState[sequence] === "failed") {
          // 序列無法播放：停在目前畫面並確保容器可見，不無限等待
          this.revealFrierenContainer();
          this.frierenIsSpeaking = false;
          return;
        }
        this.loadFrierenSequence(sequence).then(
          this.showFrierenIdle.bind(this),
          this.showFrierenIdle.bind(this)
        );
        return;
      }

      this.playFrierenLoop(sequence);
      this.showFrierenBodyImg();

      this.revealFrierenContainer();
      this.setupDecorationClickThrough();
      this.frierenIsSpeaking = false;

      if (typeof mpuLogger !== "undefined" && mpuLogger.log) {
        mpuLogger.logF("frierenSleepIdleImageSelected", "🌙 / ☀️ 待機アニメーションを再生します：%s", sequence);
      }
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
     * 依序播放一個非循環序列（翻書／醒來），結束後呼叫 onDone。
     * @param {string} name - book_flip | wake
     * @param {Function} onDone
     */
    playFrierenOnce: function (name, onDone) {
      const frames = this.frierenSequences[name];
      if (!frames || frames.length === 0 || !this.ensureFrierenBodyImg()) {
        if (onDone) onDone();
        return;
      }

      this.setFrierenFrame(frames[0]);
      this.showFrierenBodyImg();

      const self = this;
      let index = 0;
      const next = function () {
        index++;
        if (index >= frames.length) {
          self.frierenAnimationTimer = null;
          if (onDone) onDone();
          return;
        }
        self.setFrierenFrame(frames[index]);
        self.frierenAnimationTimer = setTimeout(next, frames[index].duration);
      };
      this.frierenAnimationTimer = setTimeout(next, frames[0].duration);
    },

    /**
     * 播放芙莉蓮翻書動畫（assets.json 的 book_flip 序列）
     */
    playFrierenBookFlipAnimation: function () {
      if (!this.isFrierenMode || !this.isFrierenSequenceReady("book_flip")) {
        return;
      }

      // 如果正在播放動畫，等待完成
      if (this.frierenAnimationTimer) {
        return;
      }

      this.stopFrierenAnimation();
      this.frierenIsSpeaking = true;
      this.playFrierenOnce("book_flip", this.showFrierenIdle.bind(this));
    },

    /**
     * 停止芙莉蓮動畫（翻書／醒來與閒置／睡眠循環）
     */
    stopFrierenAnimation: function () {
      if (this.frierenAnimationTimer) {
        clearTimeout(this.frierenAnimationTimer);
        this.frierenAnimationTimer = null;
      }
      if (this.frierenLoopTimer) {
        clearTimeout(this.frierenLoopTimer);
        this.frierenLoopTimer = null;
      }
    },

    /**
     * 清理芙莉蓮相關元素（用於角色切換）
     */
    cleanupFrierenElements: function () {
      this.isFrierenMode = false;
      this.stopFrierenAnimation();

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

      const imgContainer = document.getElementById("ukagaka_img");
      if (imgContainer) {
        imgContainer.style.removeProperty("display");
      }
      this._decorationsLoaded = false; // 重置標誌，允許重新載入

      if (window.mpuCanvasManager && window.mpuCanvasManager.canvas) {
        this.clearFrierenBodyLayout(window.mpuCanvasManager.canvas);
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
     * @returns {boolean} 此 shell 是否有醒來動畫（manifest 讀取前視為有）
     */
    hasWakeUpAnimation: function () {
      return !this.frierenAssets || (this.frierenSequences.wake || []).length > 0;
    },

    /**
     * 播放醒來動畫（assets.json 的 wake 序列）
     * @param {Function} callback - 動畫完成後的回調函數
     */
    playWakeUpAnimation: function (callback) {
      if (!this.isFrierenMode || !this.frierenSequences.wake || this.frierenSequences.wake.length === 0) {
        if (callback) callback();
        return;
      }

      if (typeof mpuLogger !== "undefined" && mpuLogger.log) {
        mpuLogger.logL("frierenWakeAnimationPlaying", "👀 目覚めアニメーションを再生します");
      }

      this.stopFrierenAnimation();

      const self = this;
      this.loadFrierenSequence("wake").then(
        function () {
          self.playFrierenOnce("wake", callback);
        },
        function () {
          if (callback) callback();
        }
      );
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

      if (!window.mpuCanvasManager || !window.mpuCanvasManager.imagesLoaded) {
        setTimeout(
          function () {
            this.triggerFrierenSpeaking(forceAnimation, onWakeUpComplete);
          }.bind(this),
          100
        );
        return false;
      }

      if (!this.isFrierenSequenceReady("book_flip")) {
        // 尚未載入完成時，載入後再翻書；載入失敗則不播放（不以缺幀播放）
        if (this.frierenSequences.book_flip && this.frierenSequenceState.book_flip !== "failed") {
          this.loadFrierenSequence("book_flip").then(
            this.playFrierenBookFlipAnimation.bind(this),
            function () {}
          );
        }
        return false;
      }

      this.playFrierenBookFlipAnimation();
      return false;
    },
  });
})();
