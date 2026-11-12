# mp-ukagaka v2.38.2 — 保守型維護審查與分階段修正計畫

> **用途**：交由 Claude Code / Codex 審查；請先找證據、再提出是否值得實作的 GO／HOLD／NO-GO。這不是立即修改程式的授權。
>
> **專案**：https://github.com/Horlicks-p/mp-ukagaka
> **審查基準**：`main` HEAD `bd32950d655edb2bde331baf37333c9667aa7cdf`；外掛版本 `v2.38.2`（2026-10-27 審查時）。開始前先 `git status`、`git rev-parse HEAD`，若 HEAD 更新，以最新程式為準並記錄差異。
> **工作原則**：只維護既有功能；不新增使用者功能、不擴大重構、不順手改 UI、Ghost 行為或 LLM 人格。每個主題分開評估、分開 PR；先取得站長批准。

---

## 0. 給 Claude Code / Codex 的執行指示（先讀）

請**先只做 Phase A（唯讀評估）**，勿修改 production code、測試、設定、版本號、翻譯、編譯輸出，亦勿建立 PR。必要時可提出具體的測試設計與最/小差異草案，但請先回報觀察與建議。若結論是 NO-GO，無須為了完成計畫硬做。

請區分：

- **已確認的實作事實**：附檔案及行號、入口與實際路徑。
- **可重現的缺陷**：寫出最小重現、預期／實際結果，或給出失敗測試。
- **風險推論或設計取捨**：明言尚未重現，勿稱為確定 bug。
- **原先的審查假說**：允許你駁斥，並附上理由。

若某項已在新版 `main` 修掉，直接標記 RESOLVED；不要重複施工。

### 原則與禁止事項

1. **不得混合主題**：Logging／Privacy 與 Stream Parser／Normalizer 分別是不同 PR；CI 是選配第三項；退休模型相容是維護備忘。
2. **不碰無關路徑**：不更動角色切換、Chat Lock、checksum／history 邊界、Provider Tool Loop、MCP Function Calling、Touch／Gift transaction、伺か／芙莉蓮角色設定，除非有直接、可重現的必要，且另行取得同意。
3. **不得新增複雜診斷系統**：本次不建立新後台頁面、新開關、新資料庫欄位或統一 Logger 框架；優先以現有能力實現更安全的記錄方式。
4. **不以刪減行數為目的**：不大拆 `ukagaka-core.js`、`ukagaka-base.js`、REST Controller；避免全檔格式化、重排 include 或更新無關套件。
5. **不更動對外契約**：除明確記錄並批准的日誌內容縮減及表情解析修正外，維持 HTTP status、`WP_Error`／REST 回傳、SSE 事件、既有 JSON 欄位、模型呼叫與重試順序。
6. **先看既有測試**：新增小型、行為導向測試，盡量沿用現有 PHPUnit／Node／Playground harness，不造第二套 WordPress 模擬環境。
7. **PR 完成後有停止點**：PR A、PR B、CI 不互為必然前提；每階段需站長再次確認。

---

## 1. 本次審查範圍與已知狀態

近期相關 PR：

- [#36 — GPT-6 Luna 與 LLM 自創表情標籤處理](https://github.com/Horlicks-p/mp-ukagaka/pull/36)
- [#37 — SSE 串流支援全形表情標籤](https://github.com/Horlicks-p/mp-ukagaka/pull/37)
- [#39 — 非管理員訪客不應取得站長姓名、生日與管理員專屬內容](https://github.com/Horlicks-p/mp-ukagaka/pull/39)

現有優點應保留：OpenAI 新模型參數已集中處理；表情正規化和串流解析已建立多種等價性測試；管理員／訪客 Prompt 已有權限隔離；`Dist Parity` 檢查 bundle 與來源一致。**本計畫不是否定這些 PR 的成果**。

| 候選                                 | 初步優先度     | 判定／工作上限                             |
| ------------------------------------ | -------------- | ------------------------------------------ |
| A. Provider 串流 HTTP 錯誤日誌最小化 | P1             | 優先唯讀審查；有真實隱私風險時，小 PR 修正 |
| B. SSE 與 Normalizer 表情標籤一致性  | P2             | 先以測試重現；只修有證據的不一致           |
| C. GitHub CI 的 PHP 驗證覆蓋         | P2（獨立選配） | 評估成本後另案，不要和 A/B 合併            |
| D. OpenAI 舊模型預設與退休前檢查     | P3（記錄）     | 不強制遷移既有用戶；另行決策               |
| 大型檔案拆分                         | NO-GO（目前）  | 沒有新的證據就不做                         |

---

## 2. Phase A — 唯讀評估（第一步唯一任務）

### A. 錯誤日誌與敏感資料流

主要程式：

- [`includes/llm/provider-stream-http.php`](https://github.com/Horlicks-p/mp-ukagaka/blob/bd32950d655edb2bde331baf37333c9667aa7cdf/includes/llm/provider-stream-http.php#L72-L149)
- 串流呼叫者：`includes/llm/providers/class-mpu-ai-provider-openai.php`、其他 Provider 的串流實作
- 上層 `WP_Error` 對外清理：`includes/rest/class-mpu-rest-chat.php` 與現有錯誤回傳輔助函數

已確認：在 Provider 回傳 HTTP 4xx／5xx 時，底層會呼叫 `error_log()`，列出截斷但仍可能敏感的 `messages[*].content`、**完整** `tool_calls`，與 Provider 回覆 `error_tail`（最多約 4 KB）。這項輸出沒有受 `WP_DEBUG` 開關限制。**並沒有證據顯示外部訪客可直接存取伺服器 error log；實際風險取決於伺服器的日誌存取與保存設定。**

Phase A 請回答：

1. 哪些 Provider、哪些 HTTP 錯誤情境會走進這段日誌？是否有其他路徑記錄 request body、Prompt、工具參數、回應 body？
2. `error_tail`、`WP_Error` 的 `message`／`data.body` 如何被上層使用？目前對訪客呈現的是什麼？**本 PR 不可假設清除日誌就等於所有對外資料安全。**
3. 日誌需要留下哪些可操作的欄位（HTTP status、可辨識的 Provider／error code、請求體大小等）？哪些需要預設消除（聊天正文、System Prompt、API Key、MCP args、可能包含私人資料的回應尾端）？
4. 既有 `mpu_debug_log()` 及其隱私門檻是否適合此情境？若不適合，可否直接做安全的 `error_log()` 摘要，不引入新開關？
5. 能否用 fake cURL server／受控假 HTTP 回應測試 400、401、429、500 與 JSON／非 JSON body？需要多少額外 harness？若成本過高，建議最小可行測試。
6. 安全摘要是否仍足以排查錯誤？如需詳細診斷，應提出**另行批准**的選項，不在本 PR 新增診斷設定。

**決策門檻**：只要可在不變更回傳契約的前提下顯著減少敏感內容的落盤，原則上 GO；若已被其他層覆蓋或確定有必需的診斷契約，先報告取捨。

### B. 串流與 Normalizer 的標籤契約

主要程式：

- [`includes/llm/class-mpu-stream-output-parser.php`](https://github.com/Horlicks-p/mp-ukagaka/blob/bd32950d655edb2bde331baf37333c9667aa7cdf/includes/llm/class-mpu-stream-output-parser.php#L259-L370)
- [`includes/llm/response-normalizer.php`](https://github.com/Horlicks-p/mp-ukagaka/blob/bd32950d655edb2bde331baf37333c9667aa7cdf/includes/llm/response-normalizer.php#L243-L349)
- `tests/Unit/StreamOutputParserTest.php`、`tests/Unit/ResponseNormalizerTest.php`
- SSE Caller：`includes/rest/class-mpu-rest-chat.php`

已確認的**原始碼差異**：Normalizer 使用 `mpu_normalize_inline_tag_body_regex()`，承認 `[ laugh ]`、`[laugh.]` 等半形寬鬆寫法；SSE `drain_tag_candidate()` 對半形只接受嚴格 ASCII `/^\[([a-z][a-z0-9_-]{0,31})\]$/i`。PR #37 自己也將其記為 known remaining gap。

Phase A 請回答：

1. 利用現有測試、在 `strip_unknown_tags=true` 與 false（必要時）重現：串流 `delta` 彙整後文字／`emotion` 事件 vs 最終 Normalizer `display_text`／emotion tags。
2. 釐清本次的**主要目標是 emotion tag 的等價性**，不要把本來就刻意不同的其他行為（例如最後套用的 500 字顯示上限、歷史截斷與空白整理）混成缺陷。
3. 評估舊式顯式格式如 `[表情: laugh]`、`[emoji: laugh]`、`(表情: laugh)` 是否也存在同類不一致；優先列為測試發現，不預設必須一次支援所有格式。
4. 覆核 PR #36／#37 的契約：未知嚴格半形情緒字 `[smile]` 有選擇性剝除；非情緒文字如 `[note]`、`[gallery]`、`[TODO]`、`[happy_path]` 要保留；Markdown `[laugh](url)` 是一般文字；全形未知標籤不應誤刪；巢狀括號需維持既有規則。
5. 檢查 Unicode UTF-8 被拆在不同網路 chunk 時的處理，尤其全形 `【】`／`［］` 的 3-byte 邊界；提出測試即可，**勿沒有重現就重寫 parser**。
6. 判斷最小修法：共用 tag grammar／解析判定，或在現有分支做小型擴充；不得為了去重而讓 Streaming 全部等待到完成才顯示。

**決策門檻**：有具體不一致且小修即可恢復 parity → GO；若需大型狀態機重寫，HOLD，先測試與報告。PR #37 的真人 SSE 驗證應列為手動驗收，而非假定已完成。

### C. CI 與模型退休（只評估，不執行）

- `.github/workflows/dist-parity.yml` 目前只確認 Frieren shell、bundle build、dist parity；不跑完整 `npm --prefix tools/node run verify` 或 PHPUnit。
- `tools/node/package.json` 已有 `test:php`、`lint:php`、`lint:phpcs`、`verify` 指令。
- 請評估**最小 PHP CI**：PHP 版本、必要 PHP extensions、`tools/php` 相依安裝、執行時間、是否與既有 `Dist Parity` 重複；若維護成本不合理可 HOLD。
- `includes/core/core-functions.php` 的 `mpu_merge_option_defaults()` 保留既有站點（沒有保存 `llm_openai_model` 欄位時）使用 GPT-4.1 mini 的行為。這是**刻意向後相容**，不可擅自改成新模型。
- 2027-01-14 舊模型服務退休的事項只記成後續維護決策：先測 UI 錯誤可理解性與人工遷移，再考慮是否調整預設。不能默默替現有網站切換模型。

---

## 3. PR A — Privacy & Error Logging Hardening（需站長批准）

**單一目標**：HTTP 錯誤仍可診斷，但正式環境的日誌預設不落地對話、人格／系統指令、站長資訊或 MCP 工具參數。

**允許的變更**：優先只修改 `includes/llm/provider-stream-http.php` 與**直接相關**的測試。如需動到其他 Provider 或上層錯誤輔助函式，先提交證據與獨立理由。

最低契約：

- 400、401、429、5xx 原本的錯誤判定、`WP_Error` code/status 與上層使用方式不回歸；**不要悄悄變更 API／SSE 對外事件結構**。
- 在 logging 端，不寫入 `messages` 原文、`tool_calls.function.arguments`、API Key、`error_tail` 原文、供應商回應 body 原文；**截短不等於去識別化**。
- 適度保留診斷用途的非敏感 metadata；不要把整個 request URL（可能有 query secrets）視為天然安全。
- 正常 2xx 串流、`on_chunk` 回呼時序、`connection_aborted()`、cURL 中止及原有 HTTP timeout 行為維持不變。
- 目前 `WP_Error` 仍可能攜帶 Provider 原始 body；**先描繪此資料流**，不要在未查明 consumer 前直接移除。若發現可對外洩漏敏感內容，先報告為**額外安全問題**並提出分離的小修，不和 logging PR 悄悄合併。
- 不新增 DB schema、後台開關、外部 logger、資料保留系統，也不更新與本修正無關的 log key。

建議測試（不拘實作形式）：

| 情境                                                   | 斷言                                                                  |
| ------------------------------------------------------ | --------------------------------------------------------------------- |
| Provider 401/429/500 + 含敏感字串的 request / response | server log 不含唯一哨兵字串（姓名、Prompt、工具參數、假 Key）         |
| 錯誤碼與狀態                                           | caller 取得原本約定的 `WP_Error`／status；不是因遮蔽而變 200          |
| 合法 200 串流                                          | delta、tool call、回呼次數與先後不變                                  |
| JSON／非 JSON 的錯誤                                   | 沒有額外 notice、fatal 或無限 retry                                   |
| logging 寫入路徑                                       | 真正捕捉會寫進 PHP log 的內容，而非只用字串 grep 確認沒有 `error_log` |

測試 harness 若成本過高，Claude 應先展示方案與替代方案，再決定是否 GO。

---

## 4. PR B — Stream / Normalizer Emotion Parity（需單獨批准）

**單一目標**：針對現有支援的表情語法，串流時使用者看見的文字與 emotion 事件，不得和最終 Normalizer 的處理結果無故矛盾。

**優先級**：先加入能失敗的 characterization test，再做最小修補。PR A 尚未完成也不應成為技術前提；只是不合併在同一 PR。

推薦測試矩陣（採用實際已安裝、可解析的 `laugh` 等標籤及 fixture；表格是**待驗證輸入**，不是全部均已證實失敗）：

| 輸入／情境                                         | 驗證點                                                         |
| -------------------------------------------------- | -------------------------------------------------------------- |
| `[laugh]`                                          | 原有成功案例、emotion 事件一次                                 |
| `[ laugh ]`、`[laugh.]`、`[laugh 。]`              | 半形寬鬆語法的 stream / normalize parity                       |
| `A[ lau` + `gh ]B`                                 | delta 分割時的最終文字及 emotion 一致                          |
| `【laugh】`、`［ laugh 。］`                       | PR #37 全形相容回歸                                            |
| `[smile]`、`[soft_smile]`、`【smile】`             | 未知情緒標籤：嚴格半形 vs 全形既有規則不混淆                   |
| `[note]`、`[gallery]`、`[happy_path]`、`[TODO]`    | 一般文字不得被當作情緒標籤剝離                                 |
| `[laugh](https://example.test)`                    | Markdown link 文字及 emotion 不被破壞                          |
| `[[laugh]]`、`【[laugh]】`                         | 巢狀括號和事件順序與既有契約一致                               |
| `[表情: laugh]`、`[emoji: laugh]`、`(表情: laugh)` | 舊式語法是否有實際不一致；獨立分類 GO／HOLD                    |
| 每個字元／每個位元組 feed 一次                     | 跨 chunk／UTF-8 邊界觀察（需考慮 parser 是否接受不完整 UTF-8） |
| 不完整 tag 到 `flush()`                            | 不丟失未結束的一般文字                                         |

要注意：

1. 用資料提供者（data provider）或共用測試工具，對照 `MPU_Stream_Output_Parser::feed()/flush()` 的串接結果與 `mpu_normalize_ai_response()`；只比較**表情解析應一致的維度**。
2. 驗證 `emotion` 事件順序及對應檔名，非只比較 `delta` 文字。
3. 做小範圍 mutation：將嚴格半形 regex 暫時改回舊寫法，新測試應失敗；恢復後全部通過。不要寫只檢查 helper 名稱的測試。
4. 保留 Streaming 的即時輸出特性與 think block 的既有處理，不要為了完全相同而把所有文字延遲到 `flush()`。
5. 修改後至少在真實 PHP/cURL + WordPress SSE 環境人工檢查一次（可用假 Provider 串流；不必真的付費呼叫 OpenAI）：畫面無殘留 tag、表情能切換、`done.msg`／checksum 不矛盾。沒有環境就註明 **NOT VERIFIED**，不能把 PHPUnit 通過當成真實 SSE 已驗證。
6. 若發現 500 字截斷、空白正規化等獨立契約使「逐字完全相同」不成立，應明確限定測試對比範圍，不藉題重寫其他子系統。

**停止條件**：一旦需要牽動 SSE 事件設計、Chat History、前端 renderer 或重寫狀態機，先 STOP 並請求新的審查，不在這個 PR 硬做。

---

## 5. Optional PR C — 最小 PHP CI（非本輪必做）

本項允許單獨評估，但**不應作為 A 或 B 的順帶變更**。若站長同意，原則上只新增／調整 `.github/workflows/*.yml`，不要改生產功能。

- 優先涵蓋 PHP 語法與既有 PHPUnit；PHPCS baseline 視 runner setup 成本加入。
- 調查 PHP 安裝版本、extensions、`tools/php` Composer vendor 安裝方式；使用快取時避免造成 stale dependency。
- 保留既有 `Dist Parity`、`Attach Release ZIP`，不得讓 release workflow 靜默改包檔方式。
- 對 PHP 測試的 PHPUnit stub／程序隔離、系統環境和外部服務依賴提出必要條件；禁止假造成功。
- 可先在 branch／workflow_dispatch 跑通，確認耗時、可靠性後再決定是否要求每個 PR 必跑。

若 runner 成本或偶發失敗過高，HOLD 即是合理結論。

---

## 6. 驗證與測試分層

執行前先確認目前腳本仍適用，並依風險選擇：

### Mandatory（直接相關測試）

- PR A：錯誤日誌捕捉與 HTTP error contract；cURL 成功／失敗處理。
- PR B：`StreamOutputParserTest`、`ResponseNormalizerTest`、已存在的 Chat checksum／transport smoke。
- 每個 PHP 原始碼變更：`php -l`（相關檔）、`git diff --check`。

### Regression smoke（每個 production PR）

```bash
npm --prefix tools/node run verify
```

此命令會跑版本檢查、PHP lint、PHPCS baseline、CSS、Node smoke、bundle build、PHPUnit；**目前的 GitHub `Dist Parity` 不是完整 `verify` 的替代品**。驗證前後檢查 working tree，勿把不相關重建產物混入 PR。

### Risk-based E2E（有實際環境才執行）

```bash
npm --prefix tools/node run test:interaction
```

- PR B 優先執行 Chat Stream + emotion + checksum 相關情境；如 E2E 不支援 targeted run，請註明實際執行的命令與覆蓋範圍。
- PR A 建議跑 provider 4xx／5xx 的可控整合情境。
- 不要求無關的 Gift／Touch／角色切換人工全測，除非 diff 擴散到相關路徑。
- 不可從「腳本存在」推定「本次已成功執行」。

---

## 7. Phase A 回報格式（請照此交付，不要先實作）

請以以下單一報告取代冗長重複的計畫文件；可以直接在聊天內回報，除非站長另外要求 MD。

```markdown
# mp-ukagaka v2.38.2 Maintenance — Phase A Report

- HEAD / working tree:
- 檢查範圍與證據（檔案:行號／呼叫鏈）：

## A. Error Logging

- Current observable behavior:
- Sensitive data paths (server log / WP_Error / user-facing):
- Actual risk vs assumptions:
- Existing tests and missing tests:
- Minimum safe change, estimated diff/harness cost:
- Decision: GO / HOLD / NO-GO / RESOLVED

## B. Stream / Normalizer Parity

- Reproduction and minimal failing cases:
- Cases already covered; cases still different:
- Smallest safe patch; danger of delayed deltas:
- Test plan and validation environment:
- Decision: GO / HOLD / NO-GO / RESOLVED

## C. CI (optional)

- Dependencies, expected maintenance cost:
- Decision: GO / HOLD / NO-GO

## D. Retiring model compatibility (record only)

- Remaining fallback semantics:
- No action now / later action trigger:

## Recommended sequence and STOP points

- Proposed PR boundaries:
- What should NOT be changed:
- What needs owner approval:
```

**最終請回答：若只能選一個小 PR，哪個最值得先做？有哪些原始審查判斷並不成立？**

---

## 8. 完工標準

只有在站長批准後，才依小 PR 執行。每個 PR 必須說明：

- 根據哪個可重現案例修正（不是「原始碼看起來很長」）。
- 修改檔案、理由、對外行為差異、未變動的安全契約。
- 測試命令、結果與**未執行項目**；必要時標註 real-server check 未完成。
- 是否需要 Release Note（尤其涉及日誌內容／LLM 表情語法的變更）。
- `git diff --check`、版本／dist 一致性；不在沒有明確指示時升版本。

本維護計畫允許最後結論是：**PR A 完成、PR B 延後、CI 暫不做，且不再進行任何拆檔**。能可靠地停止，也是成功的維護決策。

---

### 參考資料

- [Repository](https://github.com/Horlicks-p/mp-ukagaka)
- [PR #36 — OpenAI &amp; emotion tag compatibility](https://github.com/Horlicks-p/mp-ukagaka/pull/36)
- [PR #37 — Fullwidth streaming emotion tags](https://github.com/Horlicks-p/mp-ukagaka/pull/37)
- [PR #39 — Admin profile audience privacy](https://github.com/Horlicks-p/mp-ukagaka/pull/39)
- [Dist Parity Workflow](https://github.com/Horlicks-p/mp-ukagaka/blob/bd32950d655edb2bde331baf37333c9667aa7cdf/.github/workflows/dist-parity.yml)

_本文件為截至 2026-10-27 對 GitHub 原始碼的靜態審查與工作建議，非程式修改或真實伺服器測試結果。_
