# Story Talk — Phase 3 結果

日期：2026-11-19；基底：`8ea858d`；規格：v0.7.1（`mp-ukagaka-story-talk-implementation-plan-v0.7.md`）。
本階段只擴充測試、隔離 fixture 與報告，沒有修改正式功能；素材仍為 `enabled:false`，版本維持 `2.39.2`。

## 環境與隔離

- 沿用 `tools/e2e/interaction-e2e.js`、Playwright／Edge 與假 Ollama；WordPress Playground CLI 3.1.40、WordPress 7.1.2、PHP 8.3，Node 24.22.1。PHPUnit 另使用本機 PHP 8.5.12。
- 複製 Frieren 與 dialogs 到臨時目錄後掛載，故事僅在副本啟用；不寫 repo 素材或正式站資料庫。每個 profile 使用獨立 SQLite 站與瀏覽器 context。
- 只有隔離站會安裝測試 MU plugin；其控制路由要求 `manage_options`，用於設定測試條件及建立／讀回三種安全事件 transient，不會進入產品。
- JSON／SSE 使用真實 WordPress REST、Provider factory 與 PHP HTTP client；假 Ollama 捕捉最終 `messages`，不呼叫付費模型。部分競態刻意延遲 browser route／動畫 callback，報告不把這些注入當成自然發生的動畫。
- Story profile 使用單一 PHP worker。最初多 worker 配合 Blueprint runtime constants 曾在 Playground 的 `/internal/shared/auto_prepend_file.php` 產生 `foreach(null)` 警告，污染 JSON。測試改以 `rewrite-wp-config` 設定 `SCRIPT_DEBUG`，沒有更改 vendor 或產品來隱藏警告。

## 驗證結果

- 完整 `npm --prefix tools/node run verify` 通過：PHPCS baseline、PHP／CSS lint、全部 smoke、build、PHPUnit **350 tests／2146 assertions**；baseline 未變更。
- 既有 interaction 回歸：**50／50 PASS**，包含睡眠／喚醒、聊天、送禮、頁面感知、安全 block owner、角色切換、對話表情、SSE 成功／失敗／watchdog，以及 checksum block 拒絕竄改。
- Story 最終整輪 **53／53 PASS**；另以原生有視窗 Edge 驗證背景分頁 **1／1 PASS**，合計 Story **54／54 PASS**。兩組加上既有回歸，共 **104 項 E2E 全數通過**。
- [版本化結果清單與實測數據](./mp-ukagaka-story-talk-phase3-evidence.json) 從最後通過的執行 JSON 擷取，包含每項名稱、驗證層級、結果、耗時及 Story 的 Provider／節奏證據；沒有把初期測試 fixture 的失敗算成通過。

## Story 證據範圍

| 規格情境 | 實際驗證方式 |
|---|---|
| 1–4、11e | bundle 與 SCRIPT_DEBUG 各跑內建／LLM 整篇；逐段截圖、表情順序、完成 callback 與下一段開始的時間差；3 秒與 30 秒排程比較 |
| 5、13e | 真實 OK／Cancel 點擊、角色非觸摸像素、命中不透明 head zone 的滑鼠事件與裝飾點擊；確認 typewriter run、點擊計數、冷卻與 history 不被改動。送禮控制在聊天關閉時不可見，另 dispatch 其既有 handler 做防禦性檢查 |
| 5a–5c、13g | 真實 DOM／Typewriter 中注入晚到的普通台詞、外部首句與 LLM 動畫 callback；extend 成功回應先由真實 REST 取得再延後落地，另注入失敗／無效資料 |
| 6–8、11、11a、13b、13d、13f、13h、13j | 初段打字前、第二段打字中及初段淡入期間中斷；涵蓋聊天、選單／右鍵、切換角色、氣泡／角色隱藏、Auto Talk OFF、extend、SPA；檢查完整前段保留、殘字不再出現、Story owner 釋放、不續播 |
| 9 | 隔離原生 Edge profile 透過 CDP 的 `noDefaults:true` 連線，使用原生 default context 實際切換頁籤；排除 Playwright 的 focus emulation，觀察瀏覽器真正的 `document.hidden`。一般 headless 執行明確列 SKIP，另用專用 headed 指令驗證通過，不手動 dispatch 可見性事件 |
| 10、11d、13a、13c | SPA 後 1 秒的 pending 與真實頁面感知 Provider；原生首次載入的 3 秒 pending 與 SPA 的 1 秒 pending 以兩種順序重疊，記錄每個 owner 的取得／釋放 |
| 11b、11c、17 | UI 選單切到 Asuna 並注入 dialog 失敗；隔離 dialogs 的空／壞 JSON、空 TXT 走真實 REST；disabled／壞故事 JSON／Story storage 存取失敗安全降級 |
| 11f、16 | 200 ms／字、五段各 120 字實際播完，約 105 秒；另移除 onComplete，等待動態看門狗釋放鎖並確認 OK 恢復 |
| 12、14、15 | F5 後讀回袋子與冷卻；三篇三輪且每次重建 player，包含中斷即消耗與跨輪避開上一篇；冷卻期間不再抽故事 |
| 13 | Turnstile、Akismet、Moelog Bot Blocker transient 在播放中仍存在，故事結束後由正常 tick 消費並送達 Provider；不將 Bot／AI crawler 的 60 秒時窗列為必過項目 |
| 18、19 | 全篇 Story → JSON → SSE、第二段打字中接聊天、講完兩段接聊天；直接比對 Provider 的已說／未說內容。另先加入超過 40 筆歷史，再驗證儲存／請求視窗及 checksum block |

## 實測釐清與限制

1. **F5 的冷卻起算有瀏覽器行為差異。** Chromium 在離頁時會先送出 `visibilitychange(hidden)`，既有隱藏分頁取消路徑因此刷新「結束時間＋冷卻」。袋子與六小時冷卻不會遺失，但時間戳可能比「開始時間＋冷卻」晚數秒。這與 §5.3 將 F5 列為純開始時間例外的敘述有落差；本階段沒有新增 pagehide 存檔或生命週期仲裁，保留實測紀錄供維護者判斷。
2. **40 筆是儲存／請求視窗，不是即時陣列上限。** `mpu_saveChatHistory()` 與 `mpu_getChatHistoryForRequest()` 各取最後 40 筆，進聊天時再從儲存讀回；後端模型上下文另截最後 20 筆。測試依真實契約判斷，沒有為測試改變歷史或 checksum。
3. **目前預設 dock 沒有 `#show_msg`／`#mpu_extend`。** 測試在 DOM ready 前加入 legacy／自訂主題控制 fixture，讓既有 handler 綁定後以真實點擊驗證；這不表示預設 UI 已新增這兩個按鈕。
4. 畫面截圖與完整執行 JSON 位於 gitignored 的 `tools/e2e/output/`。這是隔離 WordPress 實驗，不是正式站驗證、發布或部署；也未宣稱已在 PHP 7.4 跑過。
5. Playwright 1.62 的一般頁面會啟用 focus emulation，單純加 `--headed` 仍不足以驗證背景可見性。`lib/visibility-browser.js` 只為這個案例啟動獨立臨時 Edge profile，連線不套用預設覆寫，結束後關閉並檢查路徑再清理；不使用訪客／維護者原有的瀏覽器 profile。

## 重跑

```powershell
npm --prefix tools/node run test:story-talk:e2e
npm --prefix tools/node run test:interaction -- --exclude=story-
npm --prefix tools/node run test:interaction -- --only=story-interrupt-background --headed
npm --prefix tools/node run verify
```

關閉／回退仍為維持 `enabled:false` 或移除 `stories.json`；本階段不更改發布政策。
