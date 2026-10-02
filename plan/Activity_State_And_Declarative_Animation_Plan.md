# Frieren Agent Monitor 借鏡評估：審查結論與聊天標籤修正

日期：2026-10-02

狀態：v3，整合使用者轉交的 Claude 審查及 Codex 原始碼複核。架構提案取消；§4 小修正已實作（2026-10-02）。新增 E2E `sse-badge-chat-toggle`、`sse-badge-new-input`、`sse-badge-closed-watchdog` 在修正前三項皆失敗、修正後通過；全套 34 個情境與 `verify` 通過。

本文取代 v1/v2，保留原檔名供既有連結使用。本文不代表使用者已要求實作或發版。

## 1. 決策

取消 Activity Store、operation map、revision、新模組、事件訂閱介面及 A+B PR 計畫。現有問題在原函式內處理即可，不新增共用 helper。

宣告式動畫、Ghost adapter 與全互動狀態遷移取消本次排程。未來出現第二個實際動畫 Ghost，或具體無程式碼資產匯入需求時，再另案評估；不是本案待完成階段。

近期暫停更新前，沒有必要展開這項架構工程。若要處理目前標籤問題，一個小型修正加上針對性 E2E 即可。

## 2. 為何不做 Store

核對 `js/ukagaka-chat-send.js` 與 `js/ukagaka-chat-mode.js`：

- `mpuChatRequesting` 在請求未收尾前阻止下一次送出；關閉／重開聊天不直接解除它。
- `streamFinalized` 讓 finalize/failure 收尾只執行一次。
- `isStaleReply()` 以 `mpuChatGeneration` 隔離舊畫面的 delta、status、emotion 等顯示。
- SSE done 之後仍由現有 drain 流程等文字顯示完，再執行 finalize。

目前一般聊天路徑沒有「兩個有效請求並行競爭 badge」的可重現證據。v2 為此新增 token/view/store 的成本不合理。此結論限於現行流程，不宣稱所有非同步 callback 都已全面驗證。

message-block owners、history/checksum、watchdog 與動畫流程維持各自責任。若未來找到具體 race，先重現，再決定最小修法。

## 3. 原始碼可確認的兩個缺口

### 聊天切換後殘留標籤

`mpu_toggleChatMode()` 沒有移除 `.mpu-state-badge` 或 `data-mpu-stream-state`；`css/mpu_style.css` 的 badge 規則也未限定 `.chat-mode`。

錯誤／逾時／忙碌提示，或請求仍在執行時的狀態，會保留到其他程式清除它。切換到自動對話後仍可能可見。

### 下一次送出仍顯示舊錯誤

`mpu_sendUserMessage()` 建立 placeholder 前沒有清除舊 badge，第一個 SSE 狀態到達前可能同時看見舊錯誤及新的等待提示。

以上為原始碼推導；實作時應先以 E2E 重現，不能將本文件當成已實測報告。

## 4. 最小修正範圍

1. 在 `mpu_toggleChatMode()` 的共用切換路徑，同時移除 badge 與 state attribute。
2. 在 `mpu_sendUserMessage()` 通過空訊息／忙碌 guard 後、處理被接受的輸入前，同樣清除兩者。此位置也涵蓋 `/help` 等本地指令，避免新內容留著舊錯誤；被拒絕的空送出／忙碌送出不清狀態。
3. 保留現有 error/busy/timeout 當下的提示；不新增 TTL、不改 history、輸入鎖、SSE drain、動畫或 CSS 隱藏規則。

兩處重複的 DOM 清理很短，不值得因此建立新抽象。實際修改行數以程式可讀性為準，不以「5–10 行」作驗收要求。

### Watchdog 補充邊界

`armStreamWatchdog()` 在 timer callback 中直接呼叫 `setStreamState("timeout")`，未先檢查 `isStaleReply()`。即使切換時清過 badge，舊請求逾時仍可能在 abort cleanup 前短暫重建它。

實作時將「畫面已過期時不寫 timeout badge」一併列入小修正：使用現有 `isStaleReply()` 保護該次顯示即可。`streamTimedOut` 記錄與 `AbortController.abort()` 仍必須執行，不能因 stale 而跳過請求清理。

## 5. 測試與驗收

沿用 `tools/e2e/interaction-e2e.js`，兩類情境即可，必要時以參數涵蓋不同狀態：

1. **切換清除：** 正在 thinking，以及 error/timeout/busy 結束後，關閉並重開聊天；assert badge 與 attribute 都不存在。舊請求繼續送 status 或觸發 watchdog 時，不得重新插入 badge。用 MutationObserver 記錄插入，不能只在 abort 收尾後拍一次快照而漏掉短暫重建。
2. **新輸入清除：** 真實觸發一次錯誤後，再送下一則訊息，攔住第一個 SSE 事件；assert placeholder 已出現且舊 badge/attribute 已清除。放行後確認新狀態正常顯示。補確認空輸入或 busy guard 拒絕的送出不清掉當前有效狀態。

測試應先在修正前失敗，再確認修正後通過。沿用既有晚到回覆、history/checksum 與 stream cleanup 情境做回歸，不另建 activity reducer 測試套件。

實作後執行：

```powershell
npm --prefix tools/node run test:interaction
npm --prefix tools/node run verify
```

`verify` 已包含 build、PHPUnit、PHPCS 與 smoke tests；`test:interaction` 需另外執行。JS 修正需重建並檢查 core bundle。無 Ghost 原始碼變更時不應附帶 Ghost 功能或素材變動。

完成定義：兩個標籤問題及 stale watchdog 顯示邊界有測試證據、既有互動回歸通過；不新增模組、狀態 API 或宣告式動畫 schema。

## 6. 設計啟發與版本摘要

參考：[Frieren Agent Monitor，commit 13d0c76](https://github.com/Eskeeet/frieren-agent-monitor-pet/tree/13d0c76ee32dbbe4189e1397e9e535b6ab78fa8a)。它將監控狀態映射成角色表現的設計可供未來參考，但不足以構成 MP Ukagaka 現在重構的需求。

- v1：提出全互動 Activity Store 與宣告式動畫，範圍過大。
- v2：修正排序、transport/presentation 與 ownership 問題，縮成聊天 MVP，但仍欠缺值得新抽象解決的實際症狀。
- v3：採納 Claude「不建立 Store/helper」的建議，取消架構工作；只保留已核對的小修正及測試範圍。Codex 補充 watchdog 的 stale 顯示邊界。

原稿在本次修訂前仍為未追蹤檔案，因此不宣稱 Git 已保存 v1/v2 全文。本文件保留決策摘要，完整提案可追溯本次對話。
