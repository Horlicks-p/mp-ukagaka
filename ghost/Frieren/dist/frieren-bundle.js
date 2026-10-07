/**
 * MP Ukagaka Frieren Bundle
 *
 * 包含: frieren.js, frieren-animation.js, frieren-interactions.js, frieren-decorations.js
 */

// ========== frieren.js ==========
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
    frierenAssets: null, // shell/Frieren/assets.json（SVG 幀序列、時序、版面）
    frierenLayout: null, // assets.json 的 layout：134x249 人物框與 SVG 幀的顯示位置
    frierenSequences: {}, // { idle|sleep|book_flip|wake: [{ src, duration, img, load }] }
    frierenSequenceLoads: {}, // 各序列的預載 Promise
    frierenSequenceState: {}, // 各序列狀態：loading | ready | failed
    frierenLoadGeneration: -1, // 本次芙莉蓮載入對應的 mpuCanvasManager.loadGeneration；離開芙莉蓮後為 -1
    _frierenAssetAbort: null, // assets.json 讀取的 AbortController
    frierenIdleImage: null, // 閒置序列第一幀 URL
    frierenSleepImage: null, // 睡眠序列第一幀 URL
    frierenWakeUpImages: [], // 醒來動畫各幀 URL
    frierenBookFlipImages: [], // 翻書動畫各幀 URL
    frierenAnimationTimer: null, // 翻書／醒來動畫定時器
    frierenLoopTimer: null, // 閒置／睡眠序列的換幀定時器
    frierenIsSpeaking: false, // 是否正在說話
    frierenIdleImgElement: null, // 顯示閒置／睡眠序列的 <img> 元素
    frierenDecorations: [], // 裝飾元素陣列
    frierenIdleOpacity: 1.0, // 芙莉蓮閒置狀態透明度（0.0 - 1.0）；黑底下 0.95 會壓暗 5%，故設 1.0
    decorationChatInProgress: false, // 裝飾物對話是否正在進行中
    decorationHitCanvases: new Map(), // 裝飾物像素檢測用的隱藏 Canvas
    pixelHitThreshold: 10, // 像素透明度閾值（0-255），大於此值才視為可點擊
    _decorationClickThroughHandler: null, // 點擊穿透事件處理器（綁定在容器上，避免 img 尚未建立時漏綁）
    _touchMoveHandler: null, // 角色觸摸的游標處理器（綁定在容器上）
    _touchClickHandler: null, // 角色觸摸的點擊處理器（綁定在容器上）
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
      this.frierenLoadGeneration = window.mpuCanvasManager ? window.mpuCanvasManager.loadGeneration : 0;

      if (!shellInfo || !shellInfo.url) {
        mpuLogger.errorL('frierenShellInfoInvalid', 'フリーレンモード：shellInfo が無効です');
        return;
      }

      this.loadFrierenAssets(shellInfo.url);

      const imgContainer = document.getElementById("ukagaka_img");
      if (imgContainer) {
        imgContainer.style.position = "relative";
        // 人物元素以負 margin 讓 SVG 幀溢出人物框；flow-root 防止 margin 與容器合併
        imgContainer.style.display = "flow-root";
      }

      this.loadFrierenDecorations();
      this.setupCharacterTouchEvents();
    },
  };

  window.mpuFrierenManager = mpuFrierenManager;
})();

// ========== frieren-animation.js ==========
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

      const generation = this.frierenLoadGeneration;
      if (this._frierenAssetAbort) {
        this._frierenAssetAbort.abort();
      }
      const abort = typeof AbortController === "function" ? new AbortController() : null;
      this._frierenAssetAbort = abort;

      fetch(baseUrl + "assets.json", { credentials: "same-origin", signal: abort ? abort.signal : undefined })
        .then(function (response) {
          if (!response.ok) {
            throw new Error("HTTP " + response.status);
          }
          return response.json();
        })
        .then(function (assets) {
          if (!this.isFrierenLoadCurrent(generation)) {
            return;
          }
          this._frierenAssetAbort = null;
          this.applyFrierenAssets(assets, baseUrl);
          this.loadFrierenImages();
        }.bind(this))
        .catch(function (error) {
          if (!this.isFrierenLoadCurrent(generation)) {
            return;
          }
          mpuLogger.errorF('frierenAssetManifestLoadFailed', 'フリーレンの表示資産マニフェストを読み込めません：%s', error && error.message ? error.message : String(error));
          // 載入流程已終止（非成功）：不設的話 triggerFrierenSpeaking 會每 100ms 無限重試；
          // 沒有序列可播，翻書自然不會發生
          window.mpuCanvasManager.imagesLoaded = true;
          this.revealFrierenContainer();
        }.bind(this));
    },

    /**
     * 角色切換後，之前開始的非同步載入完成時不可再動到頁面。
     * @param {number} generation - 開始載入時的 frierenLoadGeneration
     * @returns {boolean} 該次載入是否仍屬目前的芙莉蓮
     */
    isFrierenLoadCurrent: function (generation) {
      if (!this.isFrierenMode || generation !== this.frierenLoadGeneration) {
        return false;
      }
      const canvasManager = window.mpuCanvasManager;
      return !canvasManager || typeof canvasManager.loadGeneration !== "number" || canvasManager.loadGeneration === generation;
    },

    /**
     * assets.json 的必要序列與其是否循環。
     */
    frierenRequiredSequences: { idle: true, sleep: true, book_flip: false, wake: false },

    /**
     * 驗證 assets.json。
     * @param {Object} assets - assets.json 內容
     * @returns {string|null} 不合格的理由；合格時為 null
     */
    validateFrierenAssets: function (assets) {
      const isNum = function (v) {
        return typeof v === "number" && isFinite(v);
      };
      const isNumArray = function (v, length) {
        return Array.isArray(v) && v.length === length && v.every(isNum);
      };
      if (!assets || typeof assets !== "object") {
        return "not an object";
      }
      if (assets.format_version !== 1) {
        return "format_version must be 1";
      }
      if (!isNumArray(assets.view_box, 4) || !(assets.view_box[2] > 0 && assets.view_box[3] > 0)) {
        return "view_box must be [x, y, width, height]";
      }
      const layout = assets.layout;
      if (!layout || !isNumArray(layout.box, 2) || !(layout.box[0] > 0 && layout.box[1] > 0)) {
        return "layout.box must be [width, height]";
      }
      if (!isNumArray(layout.frame, 4) || !(layout.frame[2] > 0 && layout.frame[3] > 0)) {
        return "layout.frame must be [left, top, width, height]";
      }
      if (!assets.sequences || typeof assets.sequences !== "object") {
        return "sequences missing";
      }
      const required = this.frierenRequiredSequences;
      const names = Object.keys(required);
      for (let i = 0; i < names.length; i++) {
        const name = names[i];
        const seq = assets.sequences[name];
        if (!seq || typeof seq !== "object") {
          return "sequence " + name + " missing";
        }
        if (seq.loop !== required[name]) {
          return "sequence " + name + " loop must be " + required[name];
        }
        if (!Array.isArray(seq.frames) || seq.frames.length === 0) {
          return "sequence " + name + " has no frames";
        }
        for (let j = 0; j < seq.frames.length; j++) {
          const frame = seq.frames[j];
          if (!frame || typeof frame.src !== "string" || !/^[a-z_]+\/[A-Za-z0-9_-]+\.svg$/.test(frame.src)) {
            return "sequence " + name + " frame " + j + " src must be a relative .svg path";
          }
          if (!isNum(frame.duration_ms) || frame.duration_ms <= 0) {
            return "sequence " + name + " frame " + j + " duration_ms must be > 0";
          }
        }
      }
      return null;
    },

    /**
     * 驗證 assets.json 並展開為各序列的幀清單與 URL。
     * @param {Object} assets - assets.json 內容
     * @param {string} baseUrl - shell 資料夾 URL
     */
    applyFrierenAssets: function (assets, baseUrl) {
      const problem = this.validateFrierenAssets(assets);
      if (problem) {
        throw new Error("invalid assets.json: " + problem);
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
     * 預載一幀（含 decode）。同一幀只載入一次。
     * @param {Object} frame - frierenSequences 的幀
     * @returns {Promise} 成功 resolve、失敗 reject
     */
    loadFrierenFrame: function (frame) {
      if (frame.load) {
        return frame.load;
      }
      frame.load = new Promise(function (resolve, reject) {
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
      return frame.load;
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
      // 角色切換會換掉這個物件；過期的載入只寫回它自己那一代的狀態
      const state = this.frierenSequenceState;
      const load = Promise.all(frames.map(this.loadFrierenFrame, this)).then(
        function () {
          state[name] = "ready";
        },
        function (error) {
          state[name] = "failed";
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
     * 依初始狀態分段預載：第一個要顯示的序列（閒置或睡眠）的第 0 幀一到就先顯示，
     * 整個序列載完才開始循環；之後在背景依序載入其餘序列（睡眠時優先醒來動畫）。
     */
    loadFrierenImages: function () {
      const sleeping = this.isSleepMessage() && !this.sleepModeAwoken;
      const first = sleeping && this.frierenSequences.sleep ? "sleep" : "idle";
      const rest = sleeping ? ["wake", "idle", "book_flip"] : ["book_flip"];
      const generation = this.frierenLoadGeneration;

      this.applyFrierenBodyLayout(window.mpuCanvasManager.canvas);

      const self = this;
      const showThenLoadRest = function () {
        if (!self.isFrierenLoadCurrent(generation)) {
          return;
        }
        window.mpuCanvasManager.imagesLoaded = true;
        self.showFrierenIdle();
        rest.reduce(function (chain, name) {
          return chain.then(function () {
            if (!self.isFrierenLoadCurrent(generation)) {
              return null;
            }
            return self.loadFrierenSequence(name).catch(function () {});
          });
        }, Promise.resolve());
      };

      this.loadFrierenFrame(this.frierenSequences[first][0]).then(function () {
        if (self.isFrierenLoadCurrent(generation)) {
          self.showFrierenFirstFrame(first);
        }
      }, function () {});
      this.loadFrierenSequence(first).then(showThenLoadRest, showThenLoadRest);
    },

    /**
     * 序列其餘幀還在載入時，先以第 0 幀靜止顯示並讓容器可見。
     * @param {string} name - idle | sleep
     */
    showFrierenFirstFrame: function (name) {
      if (this.isFrierenSequenceReady(name) || !this.ensureFrierenBodyImg()) {
        return;
      }
      this.setFrierenFrame(this.frierenSequences[name][0]);
      this.showFrierenBodyImg();
      this.revealFrierenContainer();
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
        const generation = this.frierenLoadGeneration;
        const retry = function () {
          if (this.isFrierenLoadCurrent(generation)) {
            this.showFrierenIdle();
          }
        }.bind(this);
        this.loadFrierenSequence(sequence).then(retry, retry);
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

      this.unbindFrierenContainerEvents();

      // 尚未完成的載入作廢，並放掉已解碼的幀與命中判定用的 Canvas
      this.frierenLoadGeneration = -1;
      if (this._frierenAssetAbort) {
        this._frierenAssetAbort.abort();
        this._frierenAssetAbort = null;
      }
      this.frierenAssets = null;
      this.frierenLayout = null;
      this.frierenSequences = {};
      this.frierenSequenceLoads = {};
      this.frierenSequenceState = {};
      this.frierenIdleImage = null;
      this.frierenSleepImage = null;
      this.frierenWakeUpImages = [];
      this.frierenBookFlipImages = [];
      this._bodyHitCanvas = null;
      this._bodyHitCtx = null;
      this._bodyHitSrc = "";
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

      // 角色已切換時連 callback 一起放棄：它接續的是舊芙莉蓮的對話流程
      const self = this;
      const generation = this.frierenLoadGeneration;
      this.loadFrierenSequence("wake").then(
        function () {
          if (self.isFrierenLoadCurrent(generation)) {
            self.playFrierenOnce("wake", callback);
          }
        },
        function () {
          if (self.isFrierenLoadCurrent(generation) && callback) {
            callback();
          }
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
        const generation = this.frierenLoadGeneration;
        setTimeout(
          function () {
            if (this.isFrierenLoadCurrent(generation)) {
              this.triggerFrierenSpeaking(forceAnimation, onWakeUpComplete, skipBookFlip);
            }
          }.bind(this),
          100
        );
        return false;
      }

      if (!this.isFrierenSequenceReady("book_flip")) {
        // 尚未載入完成時，載入後再翻書；載入失敗則不播放（不以缺幀播放）
        if (this.frierenSequences.book_flip && this.frierenSequenceState.book_flip !== "failed") {
          const generation = this.frierenLoadGeneration;
          this.loadFrierenSequence("book_flip").then(
            function () {
              if (this.isFrierenLoadCurrent(generation)) {
                this.playFrierenBookFlipAnimation();
              }
            }.bind(this),
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

// ========== frieren-interactions.js ==========
/**
 * MP Ukagaka 芙莉蓮互動反應模組
 *
 * 擴展 frieren.js 建立的 window.mpuFrierenManager，負責裝飾點擊、
 * 身體 touch zone、互動後狀態恢復與冷卻計數。
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
    warnFrieren("frierenInteractionsManagerMissing", "Frieren manager が見つからないため、インタラクションモジュールを初期化できません");
    return;
  }

  Object.assign(manager, {
    /**
     * 處理裝飾物點擊事件
     * @param {string} decorationType - 裝飾物類型
     */
    handleDecorationClick: function (decorationType) {
      if (typeof mpuLogger !== "undefined" && mpuLogger.log) {
        mpuLogger.logF("frierenDecorationClickHandled", "handleDecorationClick が呼び出されました。装飾品タイプ：%s", decorationType);
        mpuLogger.log(
          "mpuAiEnabled:",
          typeof mpuAiEnabled !== "undefined" ? mpuAiEnabled : "undefined"
        );
      }

      if (this.decorationChatInProgress || this.giveItemInProgress) {
        if (typeof mpuLogger !== "undefined" && mpuLogger.log) {
          mpuLogger.logL("frierenDecorationClickIgnoredDialogActive", "装飾品会話中のため、今回のクリックを無視します");
        }
        return;
      }

      if (typeof mpuAiEnabled === "undefined" || !mpuAiEnabled) {
        if (typeof mpuLogger !== "undefined" && mpuLogger.log) {
          mpuLogger.logL("frierenDecorationDialogSkippedAiDisabled", "AI 機能が有効ではないため、装飾品会話をスキップします");
        }
        return;
      }

      this.decorationChatInProgress = true;

      if (typeof mpuAcquireMessageBlock === "function") {
        mpuAcquireMessageBlock("frieren-interaction");
      } else if (typeof mpuSetMessageBlocking === "function") {
        mpuSetMessageBlocking(true);
      } else if (typeof window !== "undefined") {
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
              mpuLogger.logL("frierenDecorationClickRequestCancelled", "装飾品クリック：リクエストをキャンセルしました");
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

                // 記錄裝飾物對話到歷史，讓互動對話模式能記得此次觸摸反應
                if (typeof window.mpuChatHistory !== "undefined" && Array.isArray(window.mpuChatHistory)) {
                  // synthetic user 錨點：讓 LLM 能在後續對話中看到裝飾物觸摸的完整脈絡
                  window.mpuChatHistory.push({
                    role: "user",
                    content: "（装飾品に触れた）",
                    type: "synthetic",
                    timestamp: Date.now(),
                  });
                  window.mpuChatHistory.push({
                    role: "assistant",
                    content: res.msg,
                    type: "touch_decoration",
                    timestamp: Date.now(),
                  });
                  if (typeof mpu_saveChatHistory === "function") {
                    mpu_saveChatHistory();
                  }
                }

                this.waitForTypewriterAndRestore();
              } else {
                const errorMsg = res?.error || ((window.mpuL10n && window.mpuL10n.errorOccurred) || "エラーが発生しました。後でもう一度お試しください。");
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

              if (typeof mpuLogger !== "undefined" && mpuLogger.errorL) {
                mpuLogger.errorL('frierenDecorationDialogRequestFailed', '装飾品会話リクエストに失敗しました', error);
              }
              if (typeof mpu_typewriter !== "undefined") {
                mpu_typewriter((window.mpuL10n && window.mpuL10n.connectionError) || "（…通信状況が良くないみたいだ…）", "#ukagaka_msg");
              }

              this.waitForTypewriterAndRestore();
            });
        }
      };

      const $msgbox = jQuery("#ukagaka_msgbox");
      const isAsleep = this.isSleepMessage();

      // 如果處於睡眠模式，觸發喚醒動畫
      if (isAsleep) {
        const wakeThenContinue = () => {
          if (typeof mpu_send_wake_up_request !== "function") {
            executeAjaxReq();
            return;
          }

          mpu_send_wake_up_request()
            .then((reactionDisplayed) => {
              if (!reactionDisplayed) {
                executeAjaxReq();
              } else {
                this.waitForTypewriterAndRestore();
              }
            })
            .catch(() => {
              executeAjaxReq();
            });
        };

        if ($msgbox.is(":visible")) {
          $msgbox.fadeOut(1000, () => {
            this.triggerFrierenSpeaking(true, null, true);
            wakeThenContinue();
          });
        } else {
          this.triggerFrierenSpeaking(true, null, true);
          wakeThenContinue();
        }
      } else {
        // 一般模式：顯示思考中
        mpuShowSystemPlaceholder({ context: "decoration" });
        mpuMarkSystemPlaceholder("#ukagaka_msg"); // §16.3-A：飾品/touch 思考中 placeholder
        if ($msgbox.is(":visible")) {
          $msgbox.fadeOut(400, () => {
            executeAjaxReq();
          });
        } else {
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
      if (typeof mpuClearSystemPlaceholder === "function") {
        mpuClearSystemPlaceholder("#ukagaka_msg");
      }

      if (typeof mpuReleaseMessageBlock === "function") {
        mpuReleaseMessageBlock("frieren-interaction");
      } else if (typeof mpuSetMessageBlocking === "function") {
        mpuSetMessageBlocking(false);
      } else if (typeof window !== "undefined") {
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
        mpuLogger.logL("frierenDecorationDialogCompletedStateRestored", "装飾品会話が完了し、状態を復元しました");
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
     * 點擊位置是否落在角色本體的不透明像素上（陰影等半透明處不算）。
     * 以目前顯示的幀繪到原尺寸的隱藏 Canvas 取 alpha；無法判定時視為命中。
     * @param {MouseEvent} event - 滑鼠事件
     * @param {HTMLElement} element - 角色元素（<img> 或 Canvas）
     * @returns {boolean}
     */
    isCharacterPixelHit: function (event, element) {
      if (!element || element.tagName !== "IMG" || !element.naturalWidth) {
        return true;
      }
      const src = element.currentSrc || element.src;
      if (!this._bodyHitCanvas) {
        this._bodyHitCanvas = document.createElement("canvas");
        this._bodyHitCtx = this._bodyHitCanvas.getContext("2d", { willReadFrequently: true });
        this._bodyHitSrc = "";
      }
      const canvas = this._bodyHitCanvas;
      const ctx = this._bodyHitCtx;
      if (!ctx) {
        return true;
      }
      if (this._bodyHitSrc !== src) {
        canvas.width = element.naturalWidth;
        canvas.height = element.naturalHeight;
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        ctx.drawImage(element, 0, 0, canvas.width, canvas.height);
        this._bodyHitSrc = src;
      }
      const rect = element.getBoundingClientRect();
      const x = Math.floor((event.clientX - rect.left) / rect.width * canvas.width);
      const y = Math.floor((event.clientY - rect.top) / rect.height * canvas.height);
      if (x < 0 || y < 0 || x >= canvas.width || y >= canvas.height) {
        return false;
      }
      try {
        return ctx.getImageData(x, y, 1, 1).data[3] >= 128;
      } catch (e) {
        return true;
      }
    },

    /**
     * 檢測觸摸區域
     * @param {MouseEvent} event - 滑鼠事件
     * @param {HTMLElement} element - 被點擊的元素
     * @returns {string|null} - 區域名稱或 null
     */
    detectTouchZone: function (event, element) {
      if (
        !this.isFrierenMode ||
        !element ||
        typeof mpuTouchZones === "undefined" ||
        !mpuTouchZones.zones
      ) {
        return null;
      }

      const rect = typeof window.mpuGetCharacterRect === "function"
        ? window.mpuGetCharacterRect(element)
        : element.getBoundingClientRect();

      // 角色元素比角色框大（SVG 幀的四周留白與陰影），會蓋住後方的裝飾。
      // 只有點在角色框內、且點到角色本身的不透明像素才算觸摸；
      // 其餘交給後方的裝飾判定。
      if (
        event.clientX < rect.left ||
        event.clientX >= rect.right ||
        event.clientY < rect.top ||
        event.clientY >= rect.bottom ||
        !this.isCharacterPixelHit(event, element)
      ) {
        return null;
      }

      const clickY = event.clientY - rect.top;
      const relativeY = clickY / rect.height;

      for (const [zoneName, zone] of Object.entries(mpuTouchZones.zones)) {
        if (zoneName.startsWith("_")) continue;
        if (relativeY >= zone.yStart && relativeY < zone.yEnd) {
          if (typeof mpuLogger !== "undefined" && mpuLogger.log) {
            mpuLogger.logF("frierenTouchZoneDetected", "タッチ領域検出：%1$s、relativeY=%2$s", zoneName, relativeY.toFixed(2));
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
        mpuLogger.logF("frierenTouchZoneHandled", "handleTouchZone が呼び出されました。領域：%s", zoneName);
      }

      if (this.decorationChatInProgress || this.giveItemInProgress) {
        if (typeof mpuLogger !== "undefined" && mpuLogger.log) {
          mpuLogger.logL("frierenTouchZoneIgnoredDialogActive", "装飾品またはタッチ会話中のため、タッチ領域クリックを無視します");
        }
        return;
      }

      if (this.isZoneInCooldown(zoneName)) {
        if (typeof mpuLogger !== "undefined" && mpuLogger.log) {
          mpuLogger.logF("frierenTouchZoneClickIgnoredCooldown", "領域がクールダウン中のため、クリックを無視します：%s", zoneName);
        }
        return;
      }

      if (this.recordZoneClick(zoneName)) {
        if (typeof mpuLogger !== "undefined" && mpuLogger.log) {
          mpuLogger.logF("frierenTouchZoneClickLimitReached", "領域のクリック上限に達したため、クールダウンに入ります：%s", zoneName);
        }
        return;
      }

      if (typeof mpuAiEnabled === "undefined" || !mpuAiEnabled) {
        if (typeof mpuLogger !== "undefined" && mpuLogger.log) {
          mpuLogger.logL("frierenTouchZoneDialogSkippedAiDisabled", "AI 機能が有効ではないため、タッチ領域会話をスキップします");
        }
        return;
      }

      this.decorationChatInProgress = true;

      if (typeof mpuAcquireMessageBlock === "function") {
        mpuAcquireMessageBlock("frieren-interaction");
      } else if (typeof mpuSetMessageBlocking === "function") {
        mpuSetMessageBlocking(true);
      } else if (typeof window !== "undefined") {
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
              mpuLogger.logL("frierenTouchZoneRequestCancelled", "タッチ領域クリック：リクエストをキャンセルしました");
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

                // 記錄身體觸摸對話到歷史，讓互動對話模式能記得此次觸摸反應
                if (typeof window.mpuChatHistory !== "undefined" && Array.isArray(window.mpuChatHistory)) {
                  // synthetic user 錨點：讓 LLM 能在後續對話中看到身體觸摸的完整脈絡
                  window.mpuChatHistory.push({
                    role: "user",
                    content: "（芙莉蓮の体に触れた）",
                    type: "synthetic",
                    timestamp: Date.now(),
                  });
                  window.mpuChatHistory.push({
                    role: "assistant",
                    content: res.msg,
                    type: "touch_zone",
                    timestamp: Date.now(),
                  });
                  if (typeof mpu_saveChatHistory === "function") {
                    mpu_saveChatHistory();
                  }
                }
              }

              self.scheduleRestoreAfterChat();
            })
            .catch((err) => {
              if (jQuery("#ukagaka_msgbox").is(":hidden") && typeof mpu_showmsg !== "undefined") {
                mpu_showmsg(400);
              }

              mpuLogger.errorL('frierenTouchZoneDialogRequestFailed', 'タッチ領域の会話リクエストに失敗しました', err);
              self.restoreAfterDecorationChat();
            });
        } else {
          warnFrieren("frierenTouchZoneRuntimeUnavailable", "mpuFetch または mpuRestUrl が利用できないため、タッチ領域会話を送信できません");
          self.restoreAfterDecorationChat();
        }
      };

      const $msgbox = jQuery("#ukagaka_msgbox");
      const isAsleep = this.isSleepMessage();

      // 如果處於睡眠模式，觸發喚醒動畫
      if (isAsleep) {
        const wakeThenContinue = () => {
          if (typeof mpu_send_wake_up_request !== "function") {
            executeAjaxReq();
            return;
          }

          mpu_send_wake_up_request()
            .then((reactionDisplayed) => {
              if (!reactionDisplayed) {
                executeAjaxReq();
              } else {
                self.waitForTypewriterAndRestore();
              }
            })
            .catch(() => {
              executeAjaxReq();
            });
        };

        if ($msgbox.is(":visible")) {
          $msgbox.fadeOut(1000, () => {
            self.triggerFrierenSpeaking(true, null, true);
            wakeThenContinue();
          });
        } else {
          self.triggerFrierenSpeaking(true, null, true);
          wakeThenContinue();
        }
      } else {
        // 一般模式：顯示思考中
        mpuShowSystemPlaceholder({ context: "touch" });
        mpuMarkSystemPlaceholder("#ukagaka_msg"); // §16.3-A：飾品/touch 思考中 placeholder
        if ($msgbox.is(":visible")) {
          $msgbox.fadeOut(400, () => {
            executeAjaxReq();
          });
        } else {
          executeAjaxReq();
        }
      }
    },

    /**
     * 設置角色觸摸事件監聽
     */
    setupCharacterTouchEvents: function () {
      const imgContainer = document.getElementById("ukagaka_img");
      if (!imgContainer) {
        warnFrieren("frierenTouchContainerMissing", "#ukagaka_img が見つからないため、タッチイベントを設定できません");
        return;
      }

      const self = this;

      // 重新進入芙莉蓮模式時先拆掉上一次的處理器，避免重複綁定
      if (this._touchMoveHandler) {
        imgContainer.removeEventListener("mousemove", this._touchMoveHandler);
      }
      if (this._touchClickHandler) {
        imgContainer.removeEventListener("click", this._touchClickHandler, true);
      }

      this._touchMoveHandler = function (e) {
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
          // 角色透明處底下若有裝飾，點擊會交給它，游標也跟著顯示可點
          target.style.cursor = self.findDecorationAt && self.findDecorationAt(e, true) ? "pointer" : "default";
        }
      };

      this._touchClickHandler = function (e) {
        const target = e.target;

        if (
          target.id !== "frieren_idle_apng" &&
          target.id !== "cur_ukagaka"
        ) {
          return;
        }

        const zone = self.detectTouchZone(e, target);
        if (zone) {
          // 同一容器上還有裝飾的點擊穿透判定，觸摸成立時不再交給它
          e.stopImmediatePropagation();
          e.preventDefault();
          self.handleTouchZone(zone);
        }
      };

      imgContainer.addEventListener("mousemove", this._touchMoveHandler);
      imgContainer.addEventListener("click", this._touchClickHandler, true);

      if (typeof mpuLogger !== "undefined" && mpuLogger.log) {
        mpuLogger.logL("frierenTouchEventsBound", "キャラクターのタッチイベントを設定しました");
      }

      this.setupGiftPicker();
    },

    /**
     * 拆掉綁在 #ukagaka_img 上的觸摸與裝飾點擊處理器（角色切換時）。
     * 容器在切換後仍會留給下一個角色，處理器不拆會繼續作用在它身上。
     */
    unbindFrierenContainerEvents: function () {
      const imgContainer = document.getElementById("ukagaka_img");
      if (imgContainer) {
        if (this._touchMoveHandler) {
          imgContainer.removeEventListener("mousemove", this._touchMoveHandler);
        }
        if (this._touchClickHandler) {
          imgContainer.removeEventListener("click", this._touchClickHandler, true);
        }
        if (this._decorationClickThroughHandler) {
          imgContainer.removeEventListener("click", this._decorationClickThroughHandler, true);
        }
      }
      this._touchMoveHandler = null;
      this._touchClickHandler = null;
      this._decorationClickThroughHandler = null;
    },

    /**
     * チャット入力欄の末尾にギフトピッカーを設置する。
     */
    setupGiftPicker: function () {
      const config = window.mpuPersonalityItems;
      const input = document.getElementById("mpu_user_input");
      const container = document.getElementById("ukagaka_chat_input");
      if (
        !config ||
        !Array.isArray(config.items) ||
        config.items.length === 0 ||
        !input ||
        !container ||
        document.getElementById("mpu_gift_picker_button")
      ) {
        return;
      }

      const button = document.createElement("button");
      button.type = "button";
      button.id = "mpu_gift_picker_button";
      button.className = "mpu-gift-picker-button";
      button.textContent = "🎁";
      button.setAttribute("aria-label", config.pickerLabel || "ギフトを選ぶ");
      button.setAttribute("aria-expanded", "false");
      button.setAttribute("aria-controls", "mpu_gift_picker");

      const picker = document.createElement("div");
      picker.id = "mpu_gift_picker";
      picker.className = "mpu-gift-picker";
      picker.hidden = true;
      picker.setAttribute("role", "dialog");
      picker.setAttribute("aria-label", config.pickerLabel || "ギフトを選ぶ");

      const slider = document.createElement("div");
      slider.className = "mpu-gift-picker-slider";

      const previousButton = document.createElement("button");
      previousButton.type = "button";
      previousButton.className = "mpu-gift-picker-nav mpu-gift-picker-nav-previous";
      previousButton.textContent = "‹";
      previousButton.setAttribute("aria-label", config.previousLabel || "前のアイテム");

      const slide = document.createElement("div");
      slide.className = "mpu-gift-picker-slide";

      const nextButton = document.createElement("button");
      nextButton.type = "button";
      nextButton.className = "mpu-gift-picker-nav mpu-gift-picker-nav-next";
      nextButton.textContent = "›";
      nextButton.setAttribute("aria-label", config.nextLabel || "次のアイテム");

      const counter = document.createElement("div");
      counter.className = "mpu-gift-picker-counter";
      counter.setAttribute("aria-live", "polite");

      let currentIndex = 0;
      let touchStartX = null;
      const hasNavigation = config.items.length > 1;

      const renderSlide = () => {
        const item = config.items[currentIndex];
        const itemButton = document.createElement("button");
        itemButton.type = "button";
        itemButton.className = "mpu-gift-picker-item";
        itemButton.setAttribute("aria-label", item.name);
        itemButton.title = item.name;

        const image = document.createElement("img");
        image.src = String(config.itemsBaseUrl || "") + item.image;
        image.alt = "";
        image.loading = "lazy";
        image.addEventListener("error", () => {
          const fallback = document.createElement("span");
          fallback.className = "mpu-gift-picker-fallback";
          fallback.textContent = item.name;
          image.replaceWith(fallback);
        }, { once: true });

        itemButton.appendChild(image);
        itemButton.addEventListener("click", () => {
          this.closeGiftPicker();
          this.giveItem(item.id);
        });
        slide.replaceChildren(itemButton);
        counter.textContent = `${currentIndex + 1} / ${config.items.length}`;
      };

      const moveSlide = (offset) => {
        currentIndex = (currentIndex + offset + config.items.length) % config.items.length;
        renderSlide();
        const itemButton = slide.querySelector(".mpu-gift-picker-item");
        if (!picker.hidden && itemButton) {
          itemButton.focus();
        }
      };

      previousButton.addEventListener("click", () => moveSlide(-1));
      nextButton.addEventListener("click", () => moveSlide(1));

      if (!hasNavigation) {
        slider.classList.add("is-single");
        previousButton.hidden = true;
        nextButton.hidden = true;
        counter.hidden = true;
      }

      slider.addEventListener("touchstart", (event) => {
        touchStartX = event.changedTouches[0]?.clientX ?? null;
      }, { passive: true });
      slider.addEventListener("touchend", (event) => {
        if (!hasNavigation || touchStartX === null) {
          touchStartX = null;
          return;
        }
        const touchEndX = event.changedTouches[0]?.clientX ?? touchStartX;
        const deltaX = touchEndX - touchStartX;
        touchStartX = null;
        if (Math.abs(deltaX) < 30) {
          return;
        }
        moveSlide(deltaX > 0 ? -1 : 1);
      }, { passive: true });

      slider.append(previousButton, slide, nextButton);
      picker.append(slider, counter);
      renderSlide();

      button.addEventListener("click", () => {
        const willOpen = picker.hidden;
        picker.hidden = !willOpen;
        button.setAttribute("aria-expanded", willOpen ? "true" : "false");
        if (willOpen) {
          slide.querySelector(".mpu-gift-picker-item")?.focus();
        }
      });

      container.append(button, picker);

      // 選單在好幾處開關（按鈕、Esc、送出、點外面）；統一由屬性變化同步 OK 鈕的標籤
      if (typeof window.mpuSyncOkButtonLabel === "function" && typeof MutationObserver === "function") {
        new MutationObserver(window.mpuSyncOkButtonLabel)
          .observe(picker, { attributes: true, attributeFilter: ["hidden"] });
      }

      // ピッカーは「🎁 を開く → 入力欄に台詞を書く → アイテムを押して贈る」順で使う。
      // button / picker / 入力欄はいずれも #ukagaka_chat_input の子なので、
      // コンテナ内のクリックでは閉じない（台詞を書くために入力欄を押しても開いたまま）.
      document.addEventListener("click", (event) => {
        const target = event.target;
        if (target && target.closest && target.closest("#ukagaka_chat_input")) {
          return;
        }
        this.closeGiftPicker();
      });

      // ピッカーが開いている間の入力欄は「贈り物への添え書き」なので、Enter は
      // 一般チャット送信ではなく現在のアイテムの贈与に割り当てる。
      // ukagaka-chat-events.js の keypress ハンドラより先に走らせるため、
      // 祖先要素の capture フェーズで捕まえて keydown を canceled にする
      // （keydown を preventDefault すると keypress は発火しない）.
      container.addEventListener(
        "keydown",
        (event) => {
          if (
            picker.hidden ||
            event.target !== input ||
            event.key !== "Enter" ||
            event.shiftKey
          ) {
            return;
          }
          event.preventDefault();
          event.stopPropagation();
          const item = config.items[currentIndex];
          this.closeGiftPicker();
          this.giveItem(item.id);
        },
        true
      );

      // ✅ ボタンは Enter と同じ「送信」操作なので、ピッカーが開いている間は
      // 同じ意味を持たせる。片方だけ贈与に割り当てると、添え書きを書いてから
      // ボタンを押した回だけ品物が消え、台詞だけが普通のチャットとして飛ぶ。
      // #mpu_ok_btn は #ukagaka_chat_input の外にあるので container では捕まらず、
      // document の capture フェーズで ukagaka-chat-events.js の click ハンドラより
      // 先に止める必要がある.
      document.addEventListener(
        "click",
        (event) => {
          if (picker.hidden || !event.target || !event.target.closest) {
            return;
          }
          if (!event.target.closest("#mpu_ok_btn")) {
            return;
          }
          event.preventDefault();
          event.stopPropagation();
          const item = config.items[currentIndex];
          this.closeGiftPicker();
          this.giveItem(item.id);
        },
        true
      );

      document.addEventListener("keydown", (event) => {
        if (event.key === "Escape" && !picker.hidden) {
          this.closeGiftPicker();
          button.focus();
          return;
        }
        // 入力欄で台詞を書いている間の矢印キーはキャレット移動。スライダーが奪わない.
        if (picker.hidden || !hasNavigation || event.target === input) {
          return;
        }
        if (event.key === "ArrowLeft") {
          event.preventDefault();
          moveSlide(-1);
        } else if (event.key === "ArrowRight") {
          event.preventDefault();
          moveSlide(1);
        }
      });
    },

    /**
     * チャット入力欄の値を差し替え、input 依存の UI（リサイズ等）へ通知する。
     * @param {HTMLInputElement} input
     * @param {string} value
     */
    setChatInputValue: function (input, value) {
      if (!input) {
        return;
      }
      input.value = value;
      input.dispatchEvent(new Event("input", { bubbles: true }));
    },

    closeGiftPicker: function () {
      const button = document.getElementById("mpu_gift_picker_button");
      const picker = document.getElementById("mpu_gift_picker");
      if (picker) {
        picker.hidden = true;
      }
      if (button) {
        button.setAttribute("aria-expanded", "false");
      }
    },

    /**
     * 選択されたアイテムを /touch/give に送信する。
     * チャット入力欄に文字があれば、同じ give イベントの附言として一緒に送る。
     * @param {string} itemId
     */
    giveItem: async function (itemId) {
      const messageBlocking = typeof mpuMessageBlocking !== "undefined"
        ? mpuMessageBlocking
        : Boolean(window.mpuMessageBlocking);
      if (
        this.giveItemInProgress ||
        this.decorationChatInProgress ||
        messageBlocking ||
        typeof mpuAiEnabled === "undefined" ||
        !mpuAiEnabled
      ) {
        return;
      }

      // 附言 snapshot。空白のみの入力は「附言なし」扱いで、入力欄も触らない。
      const chatInput = document.getElementById("mpu_user_input");
      const giveMessage = chatInput ? String(chatInput.value || "").trim() : "";

      const button = document.getElementById("mpu_gift_picker_button");
      const pickerButtons = document.querySelectorAll("#mpu_gift_picker button");
      this.giveItemInProgress = true;
      if (typeof mpuAcquireMessageBlock === "function") {
        mpuAcquireMessageBlock("frieren-gift");
      } else if (typeof mpuSetMessageBlocking === "function") {
        mpuSetMessageBlocking(true);
      } else {
        window.mpuMessageBlocking = true;
      }
      if (button) {
        button.disabled = true;
      }
      pickerButtons.forEach((pickerButton) => {
        pickerButton.disabled = true;
      });
      // 送信と同時に入力欄を空にする（通常チャット送信と同じ節奏）。
      if (giveMessage) {
        this.setChatInputValue(chatInput, "");
      }

      try {
        if (
          typeof mpuFetch !== "function" ||
          typeof mpuRestUrl === "undefined" ||
          typeof mpu_getOrCreateChatSessionId !== "function"
        ) {
          throw new Error("Gift picker runtime is unavailable");
        }

        if (typeof stopAutoTalk === "function") {
          stopAutoTalk();
        }
        if (typeof mpu_cancelTypewriter === "function") {
          mpu_cancelTypewriter();
        }
        if (typeof mpuShowSystemPlaceholder === "function") {
          mpuShowSystemPlaceholder({ context: "give" });
        }
        if (typeof mpuMarkSystemPlaceholder === "function") {
          mpuMarkSystemPlaceholder("#ukagaka_msg");
        }
        if (jQuery("#ukagaka_msgbox").is(":hidden") && typeof mpu_showmsg !== "undefined") {
          mpu_showmsg(400);
        }

        const history = typeof mpu_getChatHistoryForRequest === "function"
          ? mpu_getChatHistoryForRequest()
          : (Array.isArray(window.mpuChatHistory) ? window.mpuChatHistory.slice(-40) : []);
        const formData = new FormData();
        formData.append("item_id", itemId);
        formData.append("session_id", mpu_getOrCreateChatSessionId());
        formData.append("history", JSON.stringify(history));
        if (giveMessage) {
          formData.append("message", giveMessage);
        }

        // timeout は後端 provider の 30 秒より大きくし、ネットワーク + PHP 前処理の
        // 余白を確保する。これがないと「後端は成功したのに前端だけ abort」で
        // 偽の「通信状況が良くない」が出る（chat の watchdog 45 秒と揃える）。
        // give は history/統計を書き込む非冪等 POST なので retries は 0（二重送信防止）。
        const res = await mpuFetch(mpuRestUrl + "touch/give", {
          method: "POST",
          body: formData,
          timeout: 45000,
          retries: 0,
          requestId: "mpu_give_item_" + itemId,
        });

        if (!res || !res.msg || res.error) {
          throw new Error(res?.error || "Gift reaction failed");
        }

        if (typeof mpu_typewriter === "function") {
          mpu_typewriter(res.msg, "#ukagaka_msg");
        }
        if (this.isFrierenMode) {
          this.triggerFrierenSpeaking(true);
        }
        if (res.emoji && window.mpuEmojiManager) {
          window.mpuEmojiManager.showEmoji(res.emoji);
        }
        if (Array.isArray(window.mpuChatHistory) && res.user_anchor) {
          window.mpuChatHistory.push({
            role: "user",
            content: res.user_anchor,
            type: "synthetic",
            timestamp: Date.now(),
          });
          window.mpuChatHistory.push({
            role: "assistant",
            content: res.msg,
            type: "give",
            timestamp: Date.now(),
          });
          if (typeof mpu_saveChatHistory === "function") {
            mpu_saveChatHistory();
          }
        }
      } catch (error) {
        // 失敗時は附言を取り戻す。ただし待機中に新しく入力していたら上書きしない。
        if (
          giveMessage &&
          chatInput &&
          String(chatInput.value || "").trim() === ""
        ) {
          this.setChatInputValue(chatInput, giveMessage);
        }
        if (typeof mpuLogger !== "undefined" && mpuLogger.errorL) {
          mpuLogger.errorL("frierenGiveItemRequestFailed", "ギフト反応リクエストに失敗しました", error);
        }
        if (typeof mpu_typewriter === "function") {
          // 真の通信/タイムアウト障害だけ「通信状況…」を表示し、後端が返した
          // 構造化エラー（rate limit・不明なアイテム等、いずれもユーザー向け日本語）は
          // そのまま見せる。これで checksum / rate limit / provider error を区別できる。
          const rawMsg = error && error.message ? String(error.message) : "";
          const fallback =
            (window.mpuL10n && window.mpuL10n.connectionError) ||
            "（…通信状況が良くないみたいだ…）";
          const isConnectionIssue =
            rawMsg === "" ||
            rawMsg.indexOf("請求已被取消") !== -1 ||
            rawMsg.indexOf("Failed to fetch") !== -1 ||
            rawMsg.indexOf("NetworkError") !== -1 ||
            rawMsg.toLowerCase().indexOf("timeout") !== -1 ||
            rawMsg.indexOf("Gift picker runtime is unavailable") !== -1;
          mpu_typewriter(isConnectionIssue ? fallback : rawMsg, "#ukagaka_msg");
        }
      } finally {
        const self = this;
        const restoreAfterGive = function () {
          self.giveItemInProgress = false;
          if (typeof mpuReleaseMessageBlock === "function") {
            mpuReleaseMessageBlock("frieren-gift");
          } else if (typeof mpuSetMessageBlocking === "function") {
            mpuSetMessageBlocking(false);
          } else {
            window.mpuMessageBlocking = false;
          }
          if (button) {
            button.disabled = false;
          }
          pickerButtons.forEach((pickerButton) => {
            pickerButton.disabled = false;
          });
          if (typeof mpuClearSystemPlaceholder === "function") {
            mpuClearSystemPlaceholder("#ukagaka_msg");
          }
          if (typeof mpuAutoTalk !== "undefined" && mpuAutoTalk && typeof startAutoTalk === "function") {
            startAutoTalk();
          }
        };

        // 打字機仍在輸出時不可解鎖，否則使用者能立刻再次送禮、或讓自動對話打斷尚未完成的演出。
        // decoration/touch 流程同型（scheduleRestoreAfterChat → mpu_waitForTypewriterComplete）。
        if (typeof mpu_waitForTypewriterComplete !== "undefined") {
          mpu_waitForTypewriterComplete(restoreAfterGive);
        } else {
          setTimeout(restoreAfterGive, 3000);
        }
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
          mpuLogger.logF("frierenTouchZoneCooldownStarted", "領域がクールダウンに入りました：%1$s、クールダウン時間：%2$s 秒", zoneName, limit.cooldownMs / 1000);
        }

        return true;
      }

      return false;
    },
  });
})();

// ========== frieren-decorations.js ==========
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

      const generation = this.frierenLoadGeneration;

      if (typeof jQuery !== "undefined") {
        jQuery(document).one("mpuInitComplete", function(event, response) {
          if (!self.isFrierenLoadCurrent(generation)) {
            return;
          }
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

              if (!response.show_decorations || !self.isFrierenLoadCurrent(generation)) {
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
     * 滑鼠位置底下可點擊的裝飾（由上層往下找，以像素判定透明處）。
     * 裝飾可能被角色元素的透明留白蓋住，點擊與游標都用它判定。
     * @param {MouseEvent} e
     * @param {boolean} quiet - 不寫除錯 log（滑鼠移動時使用）
     * @returns {string|null} 裝飾類型
     */
    findDecorationAt: function (e, quiet) {
      const bodyZ = this.getOpaqueBodyZAt(e);
      const ordered = this.frierenDecorations
        .map((d, idx) => {
          if (!d || !d.parentNode) return null;
          const z = parseInt(window.getComputedStyle(d).zIndex || "0", 10);
          return { d, idx, z: isNaN(z) ? 0 : z };
        })
        .filter(Boolean)
        .sort((a, b) => a.z - b.z || a.idx - b.idx);

      for (let i = ordered.length - 1; i >= 0; i--) {
        if (bodyZ !== null && ordered[i].z < bodyZ) {
          // 其餘裝飾都在本體後方，而這一點本體是不透明的：點擊屬於本體
          break;
        }
        const decoration = ordered[i].d;
        const decRect = decoration.getBoundingClientRect();
        if (
          e.clientX >= decRect.left &&
          e.clientX <= decRect.right &&
          e.clientY >= decRect.top &&
          e.clientY <= decRect.bottom
        ) {
          const m = decoration.className.match(/frieren-decoration\s+(\w+)/);
          const type = m && m[1] ? m[1] : null;
          if (type && this.isPixelHit(type, decoration, e, quiet)) {
            return type;
          }
        }
      }
      return null;
    },

    /**
     * 滑鼠位置上本體是否為不透明像素；是的話回傳本體的 z-index。
     * 無法判定（幀尚未載入等）時視為透明，不擋住後方裝飾。
     * @param {MouseEvent} e
     * @returns {number|null}
     */
    getOpaqueBodyZAt: function (e) {
      const body = this.frierenIdleImgElement;
      if (
        !body ||
        !body.parentNode ||
        body.style.display === "none" ||
        body.tagName !== "IMG" ||
        !body.naturalWidth ||
        typeof this.isCharacterPixelHit !== "function"
      ) {
        return null;
      }
      const rect = body.getBoundingClientRect();
      if (
        e.clientX < rect.left ||
        e.clientX >= rect.right ||
        e.clientY < rect.top ||
        e.clientY >= rect.bottom ||
        !this.isCharacterPixelHit(e, body)
      ) {
        return null;
      }
      const z = parseInt(window.getComputedStyle(body).zIndex || "0", 10);
      return isNaN(z) ? 0 : z;
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

        const type = this.findDecorationAt(e);
        if (type) {
          e.stopPropagation();
          e.preventDefault();
          this.handleDecorationClick(type);
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
        // 載入完成前已被 cleanup 移除的裝飾不再建立命中判定
        if (this.frierenDecorations.indexOf(decoration) !== -1) {
          this.createHitCanvas(config.type, decoration);
        }
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
     * @param {boolean} quiet - 不寫除錯 log
     * @returns {boolean} - 是否命中不透明像素
     */
    isPixelHit: function (type, imgElement, event, quiet) {
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

        if (!quiet && typeof mpuLogger !== "undefined" && mpuLogger.log) {
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

