# Story Talk — Phase 2 結果

日期：2026-11-19；規格：`mp-ukagaka-story-talk-implementation-plan-v0.7.md`（v0.7.1）。
分支：`feature/story-talk-phase1`；起點：`f4d53d1`；版本維持 `2.39.2`。

## 已完成

- Auto Talk 的既有 guard 通過後、普通台詞／LLM 流程之前嘗試抽故事；只有 `auto` 觸發會抽，手動 OK 與 startup 沿用原路徑。故事選中後不送 `/nextmsg`；播放中的 auto tick 跳過安全事件檢查與普通台詞，只由既有排程重排。
- `/dialog` 的回應先通過 load token／角色世代檢查，再綁定送出時的 `storiesGeneration`；空 `msg` 防禦分支也保留故事欄位。換角色成功時清掉舊 stories，晚到的舊回應不能恢復它。
- 新模組加入 `coreFiles` 與 `SCRIPT_DEBUG` enqueue；透過 anime 的依賴保證 Story 在 features／角色腳本前載入。features 在 DOM ready 初始化 player，等 `mpuSpaEvents` 已建立才註冊 listener；重複初始化不重複綁定。
- 聊天、角色選單／切換、extend、隱藏氣泡／角色、Auto Talk OFF、隱藏分頁與 SPA 都接上取消。完整台詞保留於既有 chat history，取消中的段落不寫入，也不續播。修補 history adapter 的陣列檢查。
- 觸摸、裝飾與角色本體點擊在故事播放時略過；OK 等操作沿用 message block。普通台詞的延遲顯示／動畫、外部對話首句、LLM 喚醒動畫，以及 extend 成功／失敗的晚到結果，都避免覆蓋較新的故事。
- SPA 立即取得 `"spa"` pending，只有最後一次導航的 1 秒 callback 能釋放；與 startup owner 互不清除。隱藏角色時同步記錄 hide requested，涵蓋 fadeOut 還沒變成 `:hidden` 的空窗。
- 新增略過 auto tick 的日文 log key，註冊 PHP i18n、補齊 POT／四份 PO，使用 `languages/compile_po.py` 重編 MO。重建核心與 Frieren bundle，更新作者指南。

## 驗證

- `npm --prefix tools/node run verify` 通過：PHP／CSS lint、PHPCS baseline gate、所有 smoke、build、PHPUnit **350 tests／2146 assertions**。未修改 PHPCS baseline。
- `test:story-talk` 同時執行引擎 smoke 與新增的整合 smoke。整合測試使用實際 JS 入口、故事引擎、Typewriter、故事／內建台詞袋與 message-block owner；DOM、時間及 transport 使用模擬環境。
- 整合案例涵蓋內建／LLM 模式零故事請求、整篇連播與 tick 恢復、手動／startup 不抽故事、九種中斷、已完成 history 保留、其他 owner 不被釋放、SPA burst 與 startup 重疊、角色淡出空窗、晚到的普通台詞／首句／LLM 動畫／extend，以及角色切換後的舊 catalog／舊回應。
- 額外驗證整套原始 JS 依 build 順序載入，以及正式壓縮 bundle 載入：兩者都能在 features ready 初始化 player，SPA／visibility listener 均有綁定。此項只驗證 JS 載入與初始化，不代表已在 WordPress 實測 enqueue。
- 完整 verify 後擴充雙載入模式的測試並單獨重跑整合 smoke，通過；PHP 縮排調整後重跑 PHPCS gate。`git diff --check` 通過。

## 保留給 Phase 3

`ghost/Frieren/stories.json` 仍為 `enabled:false`，維持 5% 與 360 分鐘冷卻。未在正式站啟用，未執行 WordPress／Playwright E2E；JSON／SSE 的實際 Provider 上下文與 integrity、動畫視覺時序、實際 DOM 點擊傳播和 WordPress 雙載入模式，仍須依 Phase 3 驗證。

Story 引擎未呼叫 `startAutoTalk()`／`stopAutoTalk()`，只釋放自己的 `"story-talk"` block；未更動 SSE／checksum、安全事件後端、LLM response history 或既有送禮流程。未合併、發版或部署。
