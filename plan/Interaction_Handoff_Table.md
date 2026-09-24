# 互動交接表（第一階段盤點）

日期：2026-09-24
核對基準：`62e6197`
對應計畫：`plan/Interaction_Reliability_And_Response_Quality_Plan.md` 第一階段

本表記錄的是**目前程式碼的實際行為**，不是期望行為。標示「疑點」的列已由 `tools/e2e/interaction-e2e.js` 實際執行確認，結果見第六節。

## 一、共享旗標與寫入者

| 旗標／狀態 | 定義 | 寫入者 | 讀取（守門）者 |
| --- | --- | --- | --- |
| `mpuAutoTalk`（`MPU_STATE.autoTalk.enabled`） | `ukagaka-base.js` `mpuSetAutoTalkEnabled` | 設定載入 `ukagaka-features.js:148`、切換鈕 `:371` | `startAutoTalk`、`mpu_nextmsg`、各收尾點的「是否恢復」 |
| `mpuAutoTalkTimer` | `mpuSetAutoTalkTimer` | 只有 `startAutoTalk`（設）與 `stopAutoTalk`／觸發回呼（清） | 聊天進入 `ukagaka-chat-mode.js:21`、LLM 完成 `ukagaka-core.js:774` 的 `!mpuAutoTalkTimer` |
| `window.mpuChatModeActive` | `ukagaka-chat-history.js:3` | 只有 `mpu_toggleChatMode` | `startAutoTalk`、`mpu_nextmsg` 入口、聊天 JSON 回應 `ukagaka-chat-send.js:463` |
| `mpuChatRequesting` | `ukagaka-chat-history.js:4` | `mpu_sendUserMessage`（設）；串流收尾 `releaseStreamInput`／`streamFinalize`；JSON `finally` | `mpu_sendUserMessage` 入口（拒絕重送）、串流 watchdog |
| `mpuMessageBlocking` | `mpuSetMessageBlocking` | 聊天離開（5 秒）、頁面感知、LLM 速率限制冷卻、觸摸／裝飾、送禮 | `mpu_nextmsg` 入口、LLM 回應 `.then`、`giveItem` 入口 |
| `mpuOllamaRequesting` ＋ 佇列 | `mpuSetOllamaRequesting` | `mpu_nextmsg` LLM 分支 | `mpu_nextmsg`：auto 直接略過，手動入佇列（上限 2） |
| `mpuAiContextInProgress` | `mpuSetAiContextInProgress` | 頁面感知 `ukagaka-context.js` | `mpu_nextmsg`、LLM 回應 `.then` |
| `mpuGreetInProgress` | `mpuSetGreetInProgress` | 首訪問候 `ukagaka-features.js` | `mpu_nextmsg`、LLM 回應 `.then` |
| `mpuFrierenManager.decorationChatInProgress` | Frieren 專用 | 裝飾／觸摸開始與 `restoreAfterDecorationChat` | `startAutoTalk`、`mpu_nextmsg`、`giveItem` |
| `mpuFrierenManager.giveItemInProgress` | Frieren 專用 | `giveItem` 開始與 `restoreAfterGive` | 裝飾點擊、`giveItem` 入口 |
| `window.mpuWakeRequestPromise` | `ukagaka-chat-wake.js` | `mpu_send_wake_up_request`（`finally` 清空） | 同函式：進行中重複呼叫回傳同一個 Promise |
| 睡眠 | 伺服器 `mpu_is_deep_sleep_time()` → `mpuInfo.isDeepSleepTime`；Frieren 另有 `isSleepMessage()` | 喚醒成功時前端設 `isDeepSleepTime = false` | `mpu_isUnawokenSleepMode()` → `startAutoTalk`、`mpu_nextmsg`（auto／startup） |

## 二、進入、結束、取消點

| 互動 | 進入 | 正常結束 | 失敗／取消 | 結束後恢復自動對話 |
| --- | --- | --- | --- | --- |
| 自動對話 | `startAutoTalk` 設計時器 → 觸發 → `mpu_checkSpamEvent` → `mpu_nextmsg("auto")` | 內建台詞：回呼後立即 `startAutoTalk`。LLM：`mpu_waitForTypewriterComplete` 後 `startAutoTalk` | 閒置、睡眠：略過本次並重設計時器。LLM 失敗：打字結束後恢復；速率限制：冷卻 `ai_display_duration` 秒後恢復 | 自身循環 |
| 打字機 | `mpu_typewriter` | `mpu_waitForTypewriterComplete` 的回呼 | `mpu_cancelTypewriter`（送禮開始時） | — |
| 聊天模式 | `mpu_toggleChatMode(true)`：`stopAutoTalk`，睡眠時先喚醒 | `mpu_toggleChatMode(false)`：`messageBlocking` 5 秒 → 隨機台詞入歷史 → `startAutoTalk` | 5 秒內重新進入聊天時不恢復 | 是，5 秒後 |
| 聊天請求（JSON） | `mpu_sendUserMessage`，`requestId: mpu_user_chat`、`cancelPrevious` | 回應時 `mpuChatModeActive` 為真才顯示並入歷史 | 失敗撤回最後一筆 user；`finally` 解鎖輸入 | 不處理（聊天中） |
| 聊天請求（SSE） | 同上，`mpuPreSettings.streaming_enabled` 為真時 | `streamFinalize`（`streamFinalized` 防重入） | `handleStreamFailure`：逾時（watchdog 45 秒）、中斷、abort、忙碌 429；撤回 user、解鎖輸入 | 不處理 |
| 喚醒（OK 鈕） | `ukagaka-chat-events.js`：睡眠中 → 有喚醒動畫則淡出並播放，否則直接 `mpu_send_wake_up_request` | 顯示喚醒台詞則只 `startAutoTalk`（台詞留在畫面）；沒有台詞則走 `handleOkAction` | 請求失敗 → `handleOkAction` | 是（v2.33.1 修正） |
| 喚醒（進入聊天） | `mpu_toggleChatMode(true)` 的 `needsWakeUp` 分支 | `showChatAfterWake`：有台詞就不顯示歡迎語 | 失敗 → 顯示歡迎語 | 不處理（聊天中） |
| 觸摸／裝飾（Frieren） | 設 `decorationChatInProgress`、`messageBlocking`，`stopAutoTalk`，取消 `mpu_nextmsg_llm` | `restoreAfterDecorationChat` | 同一收尾 | 是 |
| 送禮（Frieren） | `giveItem`：設 `giveItemInProgress`、`messageBlocking`，`stopAutoTalk`，`mpu_cancelTypewriter` | 顯示回應 → 以 `res.user_anchor` 推入 synthetic user ＋ `give` assistant | 失敗還原附言、顯示錯誤 | 是，打字結束後（`restoreAfterGive`） |

## 三、交接規則（目前實作）

| 目前互動 | 收到事件 | 目前行為 | 取消進行中的請求？ | 恢復自動對話？ |
| --- | --- | --- | --- | --- |
| 自動對話計時器等待中 | 進入聊天 | `stopAutoTalk` 清除計時器 | 無請求 | 離開聊天 5 秒後 |
| 自動對話 LLM 請求進行中（`mpu_nextmsg_llm`） | 進入聊天 | 只清計時器 | **否**。**疑點 A（已重現）**：回應 `.then` 只檢查 `messageBlocking`／`aiContextInProgress`／`greetInProgress`，沒有檢查 `mpuChatModeActive` | 回應後 `startAutoTalk` 會因聊天模式直接返回 |
| 自動對話 LLM 請求進行中 | 觸摸／裝飾 | 設 `messageBlocking`，並 `mpuCancelRequest("mpu_nextmsg_llm")` | 是 | 互動結束後 |
| 睡眠未喚醒 | 自動計時器觸發 | 略過並重設計時器 | — | — |
| 睡眠未喚醒 | OK 鈕 | 喚醒請求 → 喚醒台詞 → `startAutoTalk` | — | 是 |
| 喚醒請求進行中 | 再按 OK／進入聊天 | 共用 `mpuWakeRequestPromise`，不重複送出 | — | 依各自呼叫端 |
| 聊天 JSON 請求進行中 | 再次送出 | `mpuChatRequesting` 拒絕 | — | — |
| 聊天 JSON 請求進行中 | 關閉聊天 | 回應到達時丟棄 | **否**（請求繼續跑到完成） | 離開 5 秒後 |
| 聊天 JSON 請求進行中 | 關閉後在回應前重新開啟 | **疑點 B（已重現，JSON 與 SSE 皆是）**：丟棄條件只看 `mpuChatModeActive`，重開後舊回應會顯示在新畫面並寫入歷史 | 否 | — |
| 聊天 SSE 進行中 | 關閉聊天 | 串流繼續，`streamFinalize` 無聊天模式檢查。**疑點 C（已重現）**：關閉後的回應仍寫入歷史，並與離開台詞／5 秒後的隨機台詞競爭 | 否 | 離開 5 秒後 |
| 送禮進行中 | 送禮、裝飾點擊 | `giveItemInProgress`／`messageBlocking` 拒絕 | — | — |
| 送禮進行中 | 自動計時器 | 已 `stopAutoTalk`；即使另有呼叫，`mpu_nextmsg` 也會被 `messageBlocking` 擋下 | — | 打字結束後 |
| 聊天模式中 | 送禮 | 允許（`giveItem` 不檢查聊天模式）；回應入歷史供下一輪聊天使用 | — | `startAutoTalk` 因聊天模式直接返回 |

## 四、角色差異

- `mpu_isUnawokenSleepMode()`：Frieren 以 `isSleepMessage()` 判斷；其他角色退回 `#ukagaka_msg[data-initial-msg]` 中的 `<!-- mpu-sleep -->` 標記。
- 喚醒動畫：`mpuCanvasManager.hasWakeUpAnimation()` 只轉交 `mpuFrierenManager`；其他角色一律走「無喚醒動畫」分支。
- 觸摸、裝飾、送禮與 `decorationChatInProgress` 都只存在於 Frieren。
- 睡眠時段：Frieren `manifest.json` 為每日 22 或 23 點入睡、7 點起床、賴床到 9 點（機率 1）、午睡 12:30–13:30（機率 0.4）；其他角色使用預設 0–6 點。每日隨機值以日期為 transient 鍵快取。深睡時段內的喚醒不寫入 IP 記錄，重新整理後恢復睡眠。

## 五、測試分層

| 層 | 位置 | 能證明的範圍 |
| --- | --- | --- |
| JS 模擬 | `tools/node/test-*-smoke.js` | 單一模組在 `vm` 中的邏輯，沒有 DOM 與伺服器 |
| 瀏覽器 | `tools/e2e/`，拋棄式 Playground ＋ 假 Ollama ＋ Playwright（Edge） | 真實前端 bundle 與 DOM |
| 瀏覽器（SSE 回放） | 同上，以 `page.route` 回放 `chat/user-stream` | 只驗證前端收尾；用於假 Ollama 無法產生的情況（error 事件、JSON fallback、watchdog、連線失敗） |
| WordPress 整合 | 同上，經真實 REST 路由，並檢查假 Ollama 收到的請求 | 真實 REST 路由 → 歷史轉換 → provider 請求內容；聊天走真實伺服器端 SSE（CLI 的 PHP 8.5 有 cURL，PHP cURL 串流讀取假 Ollama 的 NDJSON）；provider 為假回應 |
| 實站 | 家裡 `http://127.0.0.1/wordpress/` | 真實模型、真實站台資料與設定、非 Playground 的 PHP／網頁伺服器 |

注意：IDE 內建的 Playground（`localhost:8881`）是另一個 php-wasm 版本，Site Health 顯示沒有 cURL，`streaming_enabled` 為 `false`。以它手動測試時聊天走 JSON，不能代表 SSE。

## 六、第一階段實測結果（2026-09-24）

執行：`npm --prefix tools/node run test:interaction`（拋棄式 Playground，WordPress 7.1.2，CLI 回報 PHP 8.5.6；假 Ollama；Edge headless）。
最終一輪：21 個情境，17 PASS、4 XFAIL、0 FAIL；未變動的情境在 3 輪中結果一致。

### 已重現的問題（以 XFAIL 固定，修正後會轉為 XPASS 並使測試失敗，提醒移除標記）

| 編號 | 情境 | 實際結果 |
| --- | --- | --- |
| A | 自動對話的 LLM 請求進行中時進入聊天 | 延遲到達的自動台詞覆蓋聊天歡迎語，並以 `auto_talk` 寫入聊天歷史 |
| B | 聊天請求進行中關閉聊天、在回應前重新開啟（JSON 與 SSE） | 上一輪的回應出現在重新開啟的聊天畫面 |
| C | SSE 請求進行中關閉聊天 | 關閉後到達的回應仍寫入聊天歷史（JSON 路徑正確丟棄）。串流逐字顯示用 `$msg.html()` 直寫、不經 `mpu_typewriter`，且檢查時畫面已被離開後的隨機台詞覆蓋，所以「畫面是否短暫顯示」未驗證；第二階段修正時應補斷言 |

### 其他觀察（未斷言，供第二階段決定）

- JSON 路徑關閉聊天後丟棄回應，但使用者那一輪留在歷史（`orphanUserTurnsKept: 1`）。是否要撤回，交由第二階段依歷史規則決定。
- 伺服器端 SSE 被 provider 中途切斷時，收尾正確（輸入恢復、user 那一輪撤回、狀態為 `error`），但畫面直接顯示 `cURL Error (18): transfer closed with outstanding read data remaining`，是內部錯誤字串。
- Asuna（佔位角色）的喚醒請求成功，但伺服器沒有回傳喚醒台詞，前端改用內建的 `deep_sleep` 備用台詞。行為符合程式碼，是否需要角色專屬台詞另行決定。
