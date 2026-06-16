/**
 * 發送喚醒角色請求給後端
 */
function mpu_display_wake_reaction(res) {
  const fallbackPool =
    res && res.sleep_phase === "nap"
      ? [
          "……食べたばかりなんだけど。",
          "……少しだけ昼寝してたのに。",
          "……お腹いっぱいで、まだ眠い。",
          "……午後は眠いね。",
          "……もう少しだけ、目を閉じてたかった。",
        ]
      : res && res.sleep_phase === "oversleep"
      ? [
          "……もう少し寝ていたかったんだけど。",
          "……起こすの、少し早くない？",
          "……あと少しだけ寝るつもりだったのに。",
          "……二度寝の途中だったんだけど。",
          "……もう朝？ まだ眠い。",
          "……あと五分。人間の五分でいいから。",
          "……夢の続き、ちょうどいいところだったのに。",
          "……布団がまだ私を放してくれない。",
          "……起きる。起きるけど、今じゃなくてもいい。",
          "……まだ少し、夢の中にいたかった。",
          "……もう少し静かに起こしてほしかった。",
        ]
      : res && res.sleep_phase === "deep_sleep"
        ? [
            "……今、起こす必要あった？",
            "……うるさい。まだ寝てたんだけど。",
            "……なに。急ぎの用？",
            "……眠い。あとでいい？",
            "……せっかく寝てたのに。",
            "……まだ頭が起きてないんだけど。",
            "……もう少しだけ寝かせて。",
            "……まだ朝じゃないでしょ。",
            "……夢を見てたところ。",
            "……今の用件、明日でもよくない？",
            "……起きた。たぶん。まだ半分だけ。",
            "……急ぎじゃないなら、あとにして。",
          ]
        : [];
  const fallbackReaction =
    fallbackPool.length > 0
      ? fallbackPool[Math.floor(Math.random() * fallbackPool.length)]
      : "";
  const reaction = String((res && res.wake_reaction) || fallbackReaction).trim();
  if (!reaction) {
    return false;
  }

  const output =
    typeof mpu_parseMarkdown === "function" ? mpu_parseMarkdown(reaction) : reaction;
  mpu_typewriter(output, "#ukagaka_msg", null, true);
  if (jQuery("#ukagaka_msgbox").is(":hidden")) {
    mpu_showmsg(400);
  }

  if (Array.isArray(window.mpuChatHistory)) {
    window.mpuChatHistory.push({
      role: "assistant",
      content: reaction,
      type: "wake_reaction",
      timestamp: Date.now(),
    });
    if (typeof mpu_saveChatHistory === "function") {
      mpu_saveChatHistory();
    }
  }

  return true;
}

function mpu_send_wake_up_request() {
  if (window.mpuWakeRequestPromise) {
    return window.mpuWakeRequestPromise;
  }

  if (typeof mpuLogger !== "undefined") {
    mpuLogger.logL("wakeRequestPreparing", "🌅 キャラクターを起こします。リクエスト送信を準備しています...");
  }

  // 取得當前角色 ID（後端 mpu_wake_ghost 必填 personality_id）
  // 注意：這裡必須嚴格綁定目前人格，不做 default_1 fallback，避免跨人格寫錯喚醒狀態
  var personalityId =
    typeof window.mpuPersonalityId !== "undefined" && window.mpuPersonalityId
      ? window.mpuPersonalityId
      : typeof window.mpuInitData !== "undefined" &&
          window.mpuInitData &&
          window.mpuInitData.personality_id
        ? window.mpuInitData.personality_id
        : "";
  var ukagakaNum =
    typeof window.mpuInitData !== "undefined" &&
    window.mpuInitData &&
    window.mpuInitData.ukagaka_num
      ? window.mpuInitData.ukagaka_num
      : typeof window.mpuInitParams !== "undefined" &&
          window.mpuInitParams &&
          window.mpuInitParams.ukagaka_num
        ? window.mpuInitParams.ukagaka_num
        : "";

  if (!personalityId && !ukagakaNum) {
    if (typeof mpuLogger !== "undefined") {
      mpuLogger.warnL("wakeRequestCancelledMissingIdentity", "目覚めリクエストをキャンセルしました：personality_id/ukagaka_num が不足しているため、人格状態の混乱を避けます");
    }
    return Promise.resolve(false);
  }

  // 發送喚醒請求
  var wakeFormData = new FormData();
  if (personalityId) {
    wakeFormData.append("personality_id", personalityId);
  }
  if (ukagakaNum) {
    wakeFormData.append("ukagaka_num", ukagakaNum);
  }

  window.mpuWakeRequestPromise = mpuFetch(mpuRestUrl + "wake-ghost", {
    method: "POST",
    body: wakeFormData,
    timeout: 60000,
  })
    .then(function (res) {
      if (res && res.success) {
        if (typeof mpuLogger !== "undefined") {
          mpuLogger.logF("wakeRequestSucceeded", "目覚めに成功しました：%s", res);
        }
        // 更新本地狀態，避免重複喚醒請求
        if (typeof window.mpuInfo !== "undefined") {
          window.mpuInfo.isDeepSleepTime = false;

          // 如果是深度睡眠期間的暫時喚醒，額外記錄狀態供後續參考
          if (res.is_temporary) {
            if (typeof mpuLogger !== "undefined") {
              mpuLogger.logL("wakeRequestTemporaryDuringDeepSleep", "これは深い睡眠中の一時的な目覚めです");
            }
            window.mpuInfo.isTemporaryWakeUp = true;
          }
        }
        return mpu_display_wake_reaction(res);
      } else {
        if (typeof mpuLogger !== "undefined") {
          mpuLogger.warnF("wakeRequestResponseFailed", "目覚めリクエストの応答が失敗しました：%s", res);
        }
      }
      return false;
    })
    .catch(function (err) {
      if (typeof mpuLogger !== "undefined") {
        mpuLogger.warnF("wakeRequestFailedNonBlocking", "目覚めリクエストに失敗しましたが、通常動作には影響しません：%s", err);
      }
      return false;
    })
    .finally(function () {
      window.mpuWakeRequestPromise = null;
    });

  return window.mpuWakeRequestPromise;
}
