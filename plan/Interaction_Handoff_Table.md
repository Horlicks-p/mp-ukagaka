# 互動交接表（第一階段盤點）

日期：2026-09-24
核對基準：`62e6197`
對應計畫：`plan/Interaction_Reliability_And_Response_Quality_Plan.md` 第一階段

第一至五節最初記錄第一階段（`62e6197`）的實際行為。第三節中疑點 A、B、C 的列已依第二階段修正更新（2026-09-29），修正前的行為與實測見第六節，修正後見第七節。

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
| 自動對話 LLM 請求進行中（`mpu_nextmsg_llm`） | 進入聊天 | 只清計時器。回應到達時（`.then`、畫面就緒後、fallback）檢查聊天模式：**不顯示，照常以 `auto_talk` 記入歷史**，並釋放請求旗標（已修正疑點 A） | 否（伺服器可能已記錄；不取消） | 離開聊天 5 秒後 |
| 自動對話 LLM 請求進行中 | 觸摸／裝飾 | 設 `messageBlocking`，並 `mpuCancelRequest("mpu_nextmsg_llm")` | 是 | 互動結束後 |
| 睡眠未喚醒 | 自動計時器觸發 | 略過並重設計時器 | — | — |
| 睡眠未喚醒 | OK 鈕 | 喚醒請求 → 喚醒台詞 → `startAutoTalk` | — | 是 |
| 喚醒請求進行中 | 再按 OK／進入聊天 | 共用 `mpuWakeRequestPromise`，不重複送出 | — | 依各自呼叫端 |
| 聊天 JSON 請求進行中 | 再次送出 | `mpuChatRequesting` 拒絕 | — | — |
| 聊天請求進行中（JSON、SSE） | 關閉聊天，或關閉後在回應前重新開啟 | 以聊天世代（`mpuChatGeneration`，每次開關聊天遞增）判定過期：**不顯示（SSE 不逐字、不動狀態標記），照常記入歷史，插在它回答的那一輪之後**；失敗時按參考撤回那一輪 user（已修正疑點 B、C）。**產品行為變更**：JSON 關閉原本丟棄回應，現在保留在歷史 | 否（請求繼續跑到完成） | 離開 5 秒後 |
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

## 七、第二階段修正與實測（2026-09-29）

規則（使用者決定）：聊天關閉、重開，或被自動對話搶先時，晚到的回應**不顯示，但照常寫入歷史**。伺服器在回覆當下已把該輪記入 checksum（只計算 `type` 為 `chat` 的 assistant）；前端丟棄會讓下一輪對不上，`block` 模式下下一輪會被拒絕。

| 問題 | 修正 | 位置 |
| --- | --- | --- |
| A | 自動台詞回應到達時檢查聊天模式（外層 `.then`、畫面就緒後、fallback）；聊天中只以 `auto_talk` 記入歷史，並釋放 `ollamaRequesting` | `js/ukagaka-core.js`：`mpu_recordLlmAutoTalk()`、`mpu_recordAutoTalkIfChatTookOver()` |
| B、C | 聊天世代 `mpuChatGeneration`（`mpu_toggleChatMode` 每次遞增）。送出時記下世代與那一輪 user；過期回應不碰畫面（SSE 的 delta、狀態、思考氣泡、表情、動畫都跳過），只插入歷史 | `js/ukagaka-chat-mode.js`、`js/ukagaka-chat-send.js` |
| 歷史順序 | 回應插在它回答的 user 之後，不再直接 push；失敗時按參考撤回那一輪 user，不再假設它是最後一筆。重新進入聊天會換成從 storage 讀回的陣列，所以找不到參考時以 role、timestamp、content 比對 | `js/ukagaka-chat-history.js`：`mpu_insertChatReply()`、`mpu_removeChatHistoryEntry()` |

新增除錯 log 鍵：`nextMessageLlmResponseRecordedDuringChat`、`chatStaleReplyRecorded`（含 ja／en_US／zh_TW 翻譯）。`chatModeClosedDiscardAiResponse` 已無呼叫端，登錄與翻譯暫留。

### 實測

`npm --prefix tools/node run test:interaction`：22 個情境，22 PASS。新增 `block` 模式站台執行晚到回應的情境：

- `checksum-block-rejects-tampered-history`：竄改歷史後下一輪被拒絕，確認 `block` 模式確實生效，否則以下情境的「下一輪成功」沒有意義。
- `chat-close-keeps-late-reply-{sse,json}`：回應延遲 7 秒，晚於離開 5 秒後寫入的自語（以時間戳確認），所以會實際走到「插在它回答的那一輪之後」。回應不顯示（含 `$msg.html()` 直寫，由 MutationObserver 記錄）、在歷史中恰好一次且緊接它的 user，下一輪在 `block` 模式下成功。
- `tools/node/test-chat-history-order-smoke.js`（已加入 `verify`）：`vm` 單元測試歷史 helper——參考命中時插入位置、重新載入後以內容比對、找不到時接在最後、按參考撤回且重複撤回不誤刪。
- `chat-reopen-keeps-late-reply-off-screen-{sse,json}`：重開後舊回應不出現在新畫面，下一輪成功，且把舊回應當作上下文送給 provider。

### 其他觀察

- 聊天接手路徑（疑點 A 的修正）在記錄後直接返回，不會更新 `#ukagaka_msgnum`。LLM 取代模式下這個編號幾乎不被使用，影響極小。
- `logs/checksum-mismatch.log` 在 Playground 掛載目錄上寫不進去：`file_put_contents(..., LOCK_EX)` 的檔案鎖在 php-wasm＋Windows 掛載上失敗，錯誤被 `@` 吞掉。測試因此改用 `block` 模式驗證 checksum，不讀這個檔案。實站不受影響。

## 八、頁面感知與伺服器串流錯誤（2026-09-29）

### 頁面感知後自動對話停擺（已重現、已修正）

使用者實際體驗：頁面感知觸發時會截斷當前的自發台詞，之後的自動對話要等很久才恢復。

- 原因：頁面感知開始時不取消進行中的自動台詞請求（`mpu_nextmsg_llm`）。該回應到達時因 `messageBlocking`／`aiContextInProgress` 提前返回，沒有釋放 `ollamaRequesting`。之後每次自動 tick 都被當成「LLM 忙碌」而略過；計時器照跳，但不會再送出請求。
- 實測：`page-aware-during-autotalk-llm`（單篇長文、自動台詞請求在 provider 等待中時呼叫 `mpu_chat_context()`，與 SPA 換頁相同的入口）。修正前連續重現，頁面感知結束後 12 秒內 0 次 provider 呼叫、旗標保持 `true`；觀察 90 秒共 21 次 tick 全部落空，不會自行恢復。釋放旗標的地方只在 `mpu_nextmsg` 自己的請求流程，以及頁面載入／SPA 換頁的 startup 分支。所以實站上的「好一陣子才恢復」應該是換頁或重新載入時才恢復。
- 修正：該提前返回也釋放 `ollamaRequesting` 並處理佇列（`js/ukagaka-core.js`）。修正後頁面感知結束、經過一個間隔後，自動對話即送出請求。
- 未改變的設計行為：被截斷的那句自發台詞不顯示、也不記入歷史；頁面感知顯示完後再等 `ai_display_duration` 秒才恢復自動對話。這兩點是否要調整另行決定。

### 串流錯誤顯示原始 cURL 字串（已修正）

- 原因：`/chat/user-stream` 把 provider／傳輸層的 `WP_Error` 訊息原樣以 SSE `error` 事件送到前端，前端直接顯示。同步路徑（`/chat/user`）早已只回通用訊息。
- 修正：`MPU_REST_Chat::public_stream_error_message()`，串流錯誤與 provider 實例錯誤都改回與同步路徑相同的通用訊息（「不明なエラーが発生しました。ログを確認してください」），原始內容以 `mpu_log_error()` 寫入伺服器 log。
- 實測：`sse-server-provider-cut` 斷言畫面不含 cURL 字串、狀態為 `error`、該輪不留在歷史。
- 備註：這句通用訊息的「請確認 log」是寫給站長的，訪客也會看到；同步路徑原本就是這樣。若要改成角色口吻（如前端既有的「（…通信状況が良くないみたいだ…）」），兩條路徑應一起改。

### D：自動台詞送出瞬間進入聊天，聊天框看不見（測試中發現，已修正）

- 現象：自動 tick 送出請求時，會先把訊息框淡出（`mpu_hidemsg(600)`）。若在這 600ms 內開啟聊天，進入聊天時的「框隱藏就顯示」判斷，會因為淡出途中框仍算可見而落空；淡出結束後聊天框隱藏，連關閉鈕都點不到。
- 發現經過：`awakePage()` 改為等頁面載入的 startup 台詞結束後才交給情境，`chat-entered-while-autotalk-llm-in-flight` 因此固定抓到自動 tick（startup 不會淡出訊息框），3 輪都重現。
- 修正：`mpu_toggleChatMode(true)` 開頭先 `$msgbox.stop(true, true)`，讓進行中的動畫直接跑完，再依實際狀態顯示（`js/ukagaka-chat-mode.js`）。
- 測試：`enterChat()` 共用斷言「進入聊天後訊息框可見且不在動畫中」，所有進入聊天的情境都會檢查。

### 測試工具的穩定性修正（同日）

- `awakePage()` 等頁面載入的 startup 台詞完成後才交給情境。startup 不受自動對話開關控制，原本可能落在情境的計數期間（`gift-rest-unknown-item` 曾因此誤判）。
- fake Ollama 的延遲在「請求到達 fake」時讀取，而 PHP 處理完才呼叫 provider，所以頁面感知情境改為依請求內容決定回應、依到達時間計數。
- 失敗證據檔保存完整錯誤與 Playwright 呼叫紀錄。
