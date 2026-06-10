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
    frierenIdleOpacity: 1.0, // 芙莉蓮閒置狀態透明度（0.0 - 1.0）；黑底下 0.95 會壓暗 5%，故設 1.0
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
        mpuLogger.errorL('frierenShellInfoInvalid', 'フリーレンモード：shellInfo が無効です');
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
      for (let i = 1; i <= 11; i++) {
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
  };

  window.mpuFrierenManager = mpuFrierenManager;
})();
