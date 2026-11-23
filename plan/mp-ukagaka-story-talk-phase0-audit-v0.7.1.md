# Story Talk — Phase 0 審計摘要

日期：2026-11-19；規格：[v0.7.1](mp-ukagaka-story-talk-implementation-plan-v0.7.md)。
基準：`4a2f410ac2f0aed9cb85b21a67778d7a2ef22125`，外掛 `2.39.2`，分支 `docs/story-talk-plan`；起始工作區乾淨。與規格基準 `f5e4971` 相比，`js/`、`ghost/`、`includes/`、入口 PHP 與 build 腳本沒有差異。

**結論：Phase 0 完成，未發現必須重構核心或改變已決策產品語意的 STOP 條件。可以依規格實作；本輪只新增本報告，未開始 Phase 1。**

| 核對項目 | 結論與實作落點 |
|---|---|
| 唯一入口／計時器 | [core](../js/ukagaka-core.js) `mpu_nextmsg()`：全部 Guard 後、`mpu_hidemsg()` 前插入。外層事件回呼立即重排 timer，Story 不管理它；略過 tick 的判斷在 `mpuSetAutoTalkTimer(null)` 後、idle 前。 |
| 完成／取消 | [base](../js/ukagaka-base.js) `processNextChar()` 結尾 flush 後通知自然完成；換 run／取消讓舊 timeout、rAF 失效。wait helper 不作成功證據。[Frieren](../ghost/Frieren/frieren-animation.js) 的一般翻書不呼叫喚醒 callback；第 1 段用 Typewriter 預設動畫、第 2 段起跳過動畫。 |
| 角色／人格 | `window.mpuPersonalityId` 僅初始化設定，切換未同步；`default_1` 也不等於人格 ID。**落實建議**：送出 `/dialog` 時捕捉 `cur_num`／generation，通過既有 load-token／generation 檢查後才建立「角色／世代 → `stories.personality`」關聯；`tryStart` 核對此關聯，切換成功清掉 stories 及關聯。不要依賴 bootstrap 人格或舊 emoji cache；不需新增 REST 欄位／請求。 |
| 資料／載入 | [dialog](../includes/rest/class-mpu-rest-dialog.php) 空檔仍回 400；PHP loader 登記於 REST bootstrap 前。新 JS 排 core 後、anime 前，[build](../tools/node/build.js) 與 [debug enqueue](../includes/core/frontend-functions.php) 同步登記並接入依賴鏈；頂層不取 history。SPA listener 在 features 初始化事件陣列後於 DOM ready 綁定，保留 `typeof` 防護。 |
| UI／DOM 寫入 | 選單／切換均走 `mpuChange()`；extend 在此前自行 fetch、直接寫 DOM，成功／失敗皆需落地檢查。另盤點串流聊天、core 動畫清框、dialog 錯誤／首句、context／greet placeholder：按 §6.3–6.5 實作取消、pending owner、舊 callback 防護，驗證錯誤不覆蓋故事。觸摸 capture／裝飾已先阻止傳播，Story 判斷留在 handle 內。[features](../js/ukagaka-features.js) 的隱藏、OFF 與角色點擊接點符合規格，其餘隱藏／顯示路徑納入 R6+ 回歸。 |
| 安全事件 | [整合](../includes/integrations/akismet-integration.php) 的 Akismet／Turnstile／Bot Blocker transient 保留 10／10／30 分鐘、符合條件取用時刪除；Bot／AI crawler 為 60 秒、Visitor Pulse 為 60 分鐘時窗。沿用已核准的漏播報政策；驗收排除自然過期／其他訪客消費。 |
| History／Provider | [history service](../includes/chat/class-mpu-chat-history-service.php) 保留 synthetic／auto_talk；[chat controller](../includes/rest/class-mpu-rest-chat.php) 正規化、過濾孤立 assistant 後取 20 筆組成模型 role/content，JSON／SSE 共用，Story 文字會進模型。前端／integrity raw window 為 40 筆，checksum 排除 auto_talk；每段完成即存檔、開聊天可重讀，F5 只清聊天、不清故事袋。 |

實作時的兩個小接點：`mpuDisplayBuiltInMessage()` 回傳**文字**，重用時不得當作 run id，另用 getter 取得；其空字串與正常分支的 callback／速度錯位都要修。Story session 固定持有所屬站點／人格的袋子 key 與 config，換角色取消時用原 session 的 key 收尾，避免改到新角色冷卻。這些是現有契約的落實，不需要新架構。

**驗證證據與後續範圍**

- 已執行：`php tools/php/vendor/bin/phpunit --configuration tests/phpunit.xml.dist --do-not-cache-result --filter 'ChatIntegrityTest|RestChatPrepareArgsTest'`：**21 tests、243 assertions 通過**（PHP 8.5.12／PHPUnit 9.6.34）。
- 已執行：`test-chat-integrity-window-smoke.js`、`test-chat-history-order-smoke.js`、`test-dialog-emotion-smoke.js`、`test-shuffle-bag-smoke.js`：**4 項通過**。
- 後續依 §11–13 新增 Story loader／袋子／Typewriter／session／DOM 測試；沿用既有 Playground＋假 Ollama E2E，重點包含兩種載入模式、短間隔、全部中斷、舊 callback、換角色載入失敗、JSON／SSE block checksum 與 40-entry 邊界。
- 本輪未跑完整 verify、瀏覽器／WordPress E2E 或實站；現有基線測試通過不代表尚未實作的 Story 已通過。核心修改仍依規格等待維護者核准。
