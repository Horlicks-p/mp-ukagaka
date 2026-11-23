# Story Talk — Phase 1 結果

日期：2026-11-19；規格：`mp-ukagaka-story-talk-implementation-plan-v0.7.md`（v0.7.1，含 `23f592c` 的 Phase 0 修訂）。
分支：`feature/story-talk-phase1`；起點：`23f592c08b5c9c64c6f6ae99e38e5ff472968aa2`；版本維持 `2.39.2`。

## 已完成

- `ghost/Frieren/stories.json`：三段寶箱故事，`enabled:false`、觸發率 0.05、冷卻 360 分鐘。
- PHP Story loader、格式／機率／段數／純文字驗證、既有 emotion tag 映射；註冊載入模組，既有 `/dialog` 增加 `stories` 欄位。空對話檔仍回 400；不新增路由。
- `js/ukagaka-story-talk.js`：可注入時間、亂數、storage 與 UI adapter 的引擎。故事袋同步換輪／抽出／存檔後才播放，開始與收尾均設冷卻；storage 失敗時本頁固定以記憶體為準。
- 連續分段播放、逐段 history、段間停頓、動態看門狗、session 隔離、中斷後停止自己的 run。session 固定持有袋子 key／config；世代不符不讀寫袋子。
- Typewriter run id、自然完成 `onComplete`、舊 timeout／rAF 寫入失效；修正顯示 helper 兩處 callback／速度錯位，維持其文字回傳契約。Context pending 改成向下相容的 owner 集合，SPA 接線尚未加入。
- 新增 PHP／JS 測試、將 Story smoke 納入 verify、重建現有 bundle；新增 `GHOST_CREATE_GUIDE` 的作者格式說明。

## 與計畫的一項必要落實差異

`mpu_secure_file_read()` 只允許 `dialogs/`，不能拿來讀 `ghost/`。Story loader 改用 items／emoji 已使用的 `mpu_load_json_file()`，並先檢查人格 ID 與 realpath 位於 ghost 根目錄內。未放寬安全讀檔函式。PHPUnit 同時驗證「Story loader 可讀取」及「dialog reader 仍拒絕該路徑」。

## 驗證

- `npm --prefix tools/node run verify` 通過：PHP／CSS lint、PHPCS baseline gate、全部既有與新增 smoke、build、PHPUnit **350 tests／2146 assertions**（其中 Story loader 新增 20 個測試）。未重產 PHPCS baseline。
- Story smoke 使用真實 Typewriter 搭配模擬 DOM／時間，測試三篇三輪且每次重讀袋子、中斷消耗／冷卻、storage 降級、取消與舊 callback、逐段 history、pause 邊界、200 ms 慢速五段、看門狗、例外與 pending owner 重疊。
- 語法與 `git diff --check` 通過。PHP 執行環境為 8.5.12；沒有宣稱已在 PHP 7.4 或正式站實測。

## Phase 2／3 邊界

這是資料與引擎階段：新 Story JS 尚未加入前台 enqueue／coreFiles，也沒有建立正式 player instance。現有 bundle 只同步本階段已生效的 Typewriter／helper／pending 修改。
下一階段才做 Auto Talk 入口、`storiesGeneration` 的實際載入綁定、雙載入模式、UI 取消／鎖定、舊顯示 callback 防護、SPA pending 與 listener。規格中對應這些接點的 smoke／E2E 尚未驗證；不能把引擎的模擬環境測試當成已完成整合。
未執行瀏覽器／WordPress E2E、未啟用故事、未合併、未發版或部署。

## Phase 1 審查修正：損壞的故事袋

Claude 指出的 `readBag()` 問題已重現並修正：只有 storage 存取拋出例外才降級為本頁記憶體；JSON 解析失敗僅重設空袋，後續抽取仍會把有效袋子與冷卻寫回 storage。
新增回歸測試先在舊程式重現失敗，再確認壞 JSON／無效 schema 能被覆寫，開始後未收尾的換頁、中斷後換頁都保留 360 分鐘冷卻；既有 getItem／setItem 例外降級案例仍通過。
本次執行 Story smoke、兩個 JS 語法檢查與 diff 空白檢查，全部通過；未重跑完整 verify，未變更 PHP 或 bundle（Story 模組尚未加入 bundle）。
