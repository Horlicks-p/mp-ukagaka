
// ====== 讀取外部對話 ======
/**
 * 載入外部對話檔案
 * @param {string} file - 對話檔案名稱（路徑會被自動處理）
 * @param {boolean} skipFirstMessage - 是否跳過顯示第一句對話（用於 LLM 取代對話模式）
 */
function loadExternalDialog(file, skipFirstMessage = false) {
  const pure = (file || "").replace(/^.*[\\/]/, "");

  const params = new URLSearchParams({
    file: pure,
  });

  const url = `${mpuRestUrl}dialog?${params.toString()}`;

  document.body.style.cursor = "wait";
  if (jQuery("#ukagaka_msgbox").is(":hidden")) mpu_showmsg(200);

  const msgElement = jQuery("#ukagaka_msg");
  const currentMsg = msgElement.text().trim();
  const initialMsg = msgElement.attr("data-initial-msg");
  const hasShownInitialMsg =
    initialMsg && currentMsg.indexOf(initialMsg) !== -1;

  // 檢查是否為睡眠模式且初始訊息為睡眠相關（優先使用伺服器端時間）
  let isDeepSleep = false;
  if (
    typeof window.mpuInfo !== "undefined" &&
    typeof window.mpuInfo.isDeepSleepTime !== "undefined"
  ) {
    isDeepSleep = window.mpuInfo.isDeepSleepTime;
  } else {
    // 備用：使用客戶端時間（向後兼容）
    const now = new Date();
    const hour = now.getHours();
    isDeepSleep = hour >= 0 && hour < 6;
  }
  // 使用隱藏標記檢測睡眠模式（由 PHP 端統一添加）
  const isSleepMessage =
    initialMsg && initialMsg.includes("<!-- mpu-sleep -->");

  if (!hasShownInitialMsg) {
    // 睡眠模式下，如果初始訊息是睡眠相關的，不要覆蓋它
    if (isDeepSleep && isSleepMessage) {
      mpuLogger.logL("dialogLoadingMessageSkippedSleepMessage", "🌙 睡眠モード：睡眠メッセージを検出したため、読み込みメッセージの表示をスキップします");
      // 不顯示載入訊息，保持睡眠訊息
    } else {
      const loadingMessage =
        typeof mpuL10n !== "undefined" && mpuL10n.thinkingMessage
          ? mpuL10n.thinkingMessage
          : "（えっと…何話せばいいかな…）";
      mpu_typewriter(loadingMessage, "#ukagaka_msg");
    }
  }

  mpuFetch(url, {
    cancelPrevious: true,
    requestId: `loadExternalDialog_${pure}`,
    timeout: 15000,
    retries: 1,
  })
    .then((resp) => {
      if (typeof resp !== "object") {
        throw new Error(resp.error || "Expected JSON response from server.");
      }

      if (resp && !resp.error && Array.isArray(resp.msg)) {
        if (resp.msg.length === 0) {
          mpuLogger.warnL("dialogExternalFileEmpty", "loadExternalDialog: 会話ファイルが空です");
          mpuSetDialogStore({
            msg: [],
            auto_msg: resp.auto_msg || "",
            next_msg: resp.next_msg || 0,
            default_msg: resp.default_msg || 0,
          });
          if (skipFirstMessage) {
            mpuLogger.logL("dialogExternalFileEmptyLlmFallback", "loadExternalDialog: LLM 置換会話モードのため会話ファイルが空です。LLM 生成に依存します");
            jQuery("#ukagaka").stop(true, true).fadeIn(200);
            document.body.style.cursor = "auto";
            return;
          }
          mpu_typewriter((window.mpuL10n && window.mpuL10n.dialogEmpty) || "ダイアログファイルが空です。内容を確認してください", "#ukagaka_msg");
          mpu_showmsg(400);
          jQuery("#ukagaka").stop(true, true).fadeIn(200);
          document.body.style.cursor = "auto";
          return;
        }

        try {
          mpuSetDialogStore(resp);
          mpuSetDialogNextMode(resp.next_msg == 1 ? "random" : "sequential");
          mpuSetDialogDefaultMsg(resp.default_msg == 1 ? 1 : 0);

          if (skipFirstMessage) {
            mpuLogger.logL("dialogFallbackLoadedFirstLineSuppressed", "loadExternalDialog: LLM 置換会話モードでフォールバック会話データを読み込みましたが、最初の一文は表示しません");
            let first = 0;
            if (mpuDefaultMsg === 0 && resp.msg.length) {
              first = Math.floor(Math.random() * resp.msg.length);
            }
            jQuery("#ukagaka_msgnum").html(first);
            jQuery("#ukagaka").stop(true, true).fadeIn(200);
            document.body.style.cursor = "auto";
            return;
          }

          let firstMessageShown = false;
          let firstMessageTimer = null;

          const showFirstMessage = function () {
            if (firstMessageTimer !== null) {
              clearTimeout(firstMessageTimer);
              firstMessageTimer = null;
            }

            if (firstMessageShown) {
              mpuLogger.logL("dialogFirstLineDuplicateBlocked", "loadExternalDialog: 最初の会話文を重複表示しようとしたため阻止しました");
              return;
            }

            // 睡眠模式檢查：如果在未喚醒的睡眠模式下，跳過第一句對話
            if (
              typeof mpu_isUnawokenSleepMode === "function" &&
              mpu_isUnawokenSleepMode()
            ) {
              mpuLogger.logL("dialogFirstLineSkippedUnawokenSleepMode", "🌙 睡眠モードでまだ目を覚ましていないため、最初の内蔵会話をスキップし、睡眠メッセージを維持します");
              firstMessageShown = true; // 標記為已處理，避免重複嘗試
              // 睡眠模式下不啟動自動對話
              return;
            }

            firstMessageShown = true;

            let first = 0;
            if (mpuDefaultMsg === 0 && resp.msg.length) {
              first = Math.floor(Math.random() * resp.msg.length);
            }
            mpu_typewriter(
              mpu_unescapeHTML(resp.msg[first] + (resp.auto_msg || "")),
              "#ukagaka_msg",
            );
            jQuery("#ukagaka_msgnum").html(first);

            // 等待第一句對話打字完成後啟動自動對話
            if (mpuAutoTalk) {
              mpu_waitForTypewriterComplete(function () {
                startAutoTalk();
              });
            }
          };

          // 使用通用函數等待當前打字效果完成
          mpu_waitForTypewriterComplete(function () {
            if (!firstMessageShown) {
              firstMessageTimer = setTimeout(showFirstMessage, 1000);
            }
          });
        } catch (e) {
          mpu_handle_error(e, "loadExternalDialog:process_data", {
            showToUser: true,
            userMessage:
              mpuIsDebugMode()
                ? `処理データの読み込みに失敗しました：${e.message}`
                : ((window.mpuL10n && window.mpuL10n.processingError) || "ダイアログデータの処理中にエラーが発生しました。後でもう一度お試しください。"),
          });
        }
      } else {
        const errorMsg = resp && resp.error ? resp.error : ((window.mpuL10n && window.mpuL10n.loadingFailed) || "読み込みに失敗しました。後でもう一度お試しください。");
        jQuery("#ukagaka_msg").html(errorMsg);

        if (!mpuGetDialogStore()) {
          mpuSetDialogStore({
            msg: [],
            auto_msg: "",
            next_msg: 0,
            default_msg: 0,
          });
          mpuLogger.warnF("dialogBackendErrorUseEmptyFallback", "loadExternalDialog: バックエンドがエラーを返したため、空の mpuMsgList をフォールバックとして設定します - %s", errorMsg);
        }
      }
      jQuery("#ukagaka").stop(true, true).fadeIn(200);
      document.body.style.cursor = "auto";
    })
    .catch((error) => {
      mpu_handle_error(error, "loadExternalDialog", {
        showToUser: true,
        userMessage:
          mpuIsDebugMode()
            ? `ダイアログの読み込みに失敗しました：${error.message}`
            : ((window.mpuL10n && window.mpuL10n.dialogLoadFailed) || "ダイアログファイルの読み込みに失敗しました。後でもう一度お試しください。"),
      });

      if (!mpuGetDialogStore()) {
        mpuSetDialogStore({
          msg: [],
          auto_msg: "",
          next_msg: 0,
          default_msg: 0,
        });
        mpuLogger.warnL("dialogLoadFailedUseEmptyFallback", "loadExternalDialog: 読み込みに失敗したため、空の mpuMsgList をフォールバックとして設定します");
      }

      jQuery("#ukagaka").stop(true, true).fadeIn(200);
      document.body.style.cursor = "auto";
    });
}
