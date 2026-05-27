# Plan Status — 2026-05-27

> 對 `plan/` 全部文件做一次**對照實際程式碼**的狀態快照。
>
> **為什麼需要這份文件**：`plan/` 裡多數設計文件是**凍結規格**，裡面的
> `- [ ]` checkbox 是「驗收條目 / Hard Limit」，**不是待辦追蹤**。直接用
> 未勾選 checkbox 判斷「還沒做」會大量誤判（例：`Console_Log_i18n_Plan.md`
> 33 條、`Observation_Buffer_Design.md` 24 條都是**已上線功能**的 spec
> bullets）。本文件以「實際 code / 檔案是否存在」為準重新標記。
>
> 標記圖例：✅ 已做（已驗證）｜🟡 真待辦｜⏸ 刻意延後｜📄 參考/歷史文件

---

## A. 真待辦（可做但還沒做 — 已逐項對照程式碼）

| # | 項目 | 來源 plan | 效益 | 工數 | 程式碼現況（2026-05-27 核對） | 建議 |
|---|---|---|:--:|:--:|---|---|
| 1 | Observation Buffer **Phase 2 `bot_signal`** 接線 | Observation_Buffer_Design L440 | ⭐⭐ | 小 | `class-mpu-rest-observation.php:36` allowlist 仍只有 `page_view`/`stay_duration`，bot_signal 被 reject | ⏸ 刻意延後。bot 反應已有獨立路徑 `mpu_generate_mbb_reaction_llm()`，接進 observation 偏冗餘，不急 |
| 2 | Chat controller **進階拆分**（request normalizer + prompt builder） | Code_Quality Phase 2 #4 | ⭐⭐⭐ | 中（有回歸風險） | `class-mpu-rest-chat.php` 仍 **1298 行**；History Service 已抽出，normalizer/prompt-builder 未抽 | 綁**下次大改 chat 流程**時順手做，別當獨立工 |
| 3 | Chain of Thought 實驗（Gemini thinking A/B） | Future_Plan §3 | ⭐ | 中 | 未逐碼核對 | 低 ROI（token 3-5×、效果不確定），plan 自己也推較便宜的代替案。先不排 |
| 4 | Admin save handler **class 化**（router/saver class） | Code_Quality Phase 2 #5 | ⭐ | 小-中 | 具名 helper 已抽（`mpu_save_*_settings`）；class router 未做 | 只在 admin save 再長大時才值得 |
| 5 | **soak / 實機驗證**（賴床快取跨日等、Visitor Signals 4 訊號） | Engineering_Quality L193、Visitor_Signals | ⭐⭐ | 低（驗證非開發） | Visitor Signals 標「實作完成待實機驗證」 | 排進**發版前 checklist**，補上「實作完成、待驗證」缺口 |

**整體判斷**：沒有「高效益 + 立即可做 + 獨立」的項目剩下。剩的不是刻意延後（#1）、
就是該綁別的改動順手做（#2）、或低 ROI / 驗證類（#3–#5）。高 ROI backlog 已幾乎清空。

---

## B. 文件看似待辦、其實已做（別再追）

逐項已對照程式碼，附證據：

| 項目 | 文件曾標示 | 實際狀態（證據） |
|---|---|---|
| **PHPCS baseline + `lint:phpcs` 接入 verify** | Code_Quality / Next_Plan「❌ 未開始」 | ✅ 本 session 完成（commits `56a2620`/`d8d20ce`/`36a7ab9`/`881294b`）；runbook 見 `tools/php/PHPCS_BASELINE.md` |
| `lint:php` / `npm run verify` / REST smoke checklist | Code_Quality Phase 3「未開始」 | ✅ `package.json` 已有；`docs-en/REST_SMOKE_TEST.md` |
| **User Memory 使用者記憶** | Next_Plan「❌ 最高價值未做」 | ✅ `includes/rest/class-mpu-rest-memory.php` 存在；注入點在 `personality-loader.php` |
| **utility-functions.php 領域拆分** | Code_Quality Phase 2「未開始/最高風險」 | ✅ 主檔已縮到 **36 行**；`file-functions.php`/`network-functions.php`/`wp-info-functions.php` 已抽出 |
| Chat History Service | Code_Quality Phase 2 #4 第一步 | ✅ `class-mpu-chat-history-service.php` 已抽出 |
| **Runtime Info 微調**（感情トリガー + 溫度閾值） | Future_Plan §2 L173/L175 未勾 | ✅ `instructions.md:20`「感情が表れやすい場面（変化のトリガー）」；`personality-prompts.php:477/484` 已是 `>=28`/`<=15`（且天氣擴到 6 類 + 預報） |
| Console Log i18n（全 Phase） | Console_Log_i18n_Progress 多段「未做」 | ✅ migration 全完成，**v2.24.0** 已 release；對照表已歸零 |
| Observation Buffer MVP | Observation_Buffer 系列 | ✅ core/REST/hooks/frontend 全到位，PHPUnit 48 tests 通過 |
| Visitor Signals（4 訊號） | Visitor_Signals_Plan | ✅ 實作完成（待實機驗證，見 A#5） |
| i18n debt（~94/172 字串） | Next_Plan | ✅ v2.16 已補 |
| 人格模組化（personality.md + instructions.md, §4） | Future_Plan §4 | ✅ v2.8.1 |

---

## C. 全文件狀態地圖（22 份）

| 文件 | 狀態 | 備註 |
|---|:--:|---|
| AI_Provider_Factory_Plan.md | ✅ | v2.10 Provider Factory |
| Object_Oriented_Routing_Plan.md | ✅ | v2.9.2 REST OO |
| Loop_Detection_Plan.md | ✅ | v2.10 tool-loop guard |
| SSE_Streaming_Plan.md | ✅ | v2.12.x SSE |
| Streaming_Full_Provider_Plan.md | ✅ | v2.14 全 provider；checkbox 為 spec |
| UnifiedHistory_MemoryPlan.md | ✅ | v2.12.5 |
| REST API Migration Technical Report.md | 📄 | 歷史技術報告 |
| RELEASE_NOTES_2.13.6.md | 📄 | 歷史 release notes |
| Dead_Code_Cleanup_DocsTodo.md | ✅ | v2.13.9 |
| Console_Log_i18n_Plan.md | ✅ | v2.24.0；33 條 checkbox 為 Hard Limit/spec |
| Console_Log_i18n_Progress.md | ✅ | migration 完成；nice-to-have (d) 因對照表歸零已無意義 |
| Observation_Buffer_Design.md | ✅/⏸ | MVP ✅；Phase 2 `bot_signal` ⏸（A#1） |
| Observation_Buffer_Implementation_Plan.md | ✅ | MVP 落地 |
| Visitor_Signals_Plan.md | ✅ | 實作完成、待實機驗證（A#5） |
| Future_Plan.md | ✅/🟡 | §1 User Memory MVP ✅、§2 Runtime ✅、§4 模組化 ✅；§3 CoT 🟡（A#3） |
| Code_Quality_Hardening_Plan.md | ✅/🟡 | Phase 1+2 核心 ✅、Phase 3 工具鏈 ✅；chat 進階拆分 🟡（A#2）、admin class 化 🟡（A#4） |
| Engineering_Quality_Improvement_Plan.md | ✅/🟡 | 12/13 ✅；剩 post-launch soak（A#5） |
| Next_Plan.md | 📄 | 2026-05-13 快照，**多數待辦已被後續完成**；保留作歷史，勿據此排期 |
| Ai_Engine_Patterns_Memo.md | 📄 | 參考 memo |
| Avatar_UI_Learnings.md | 📄 | 參考 learnings |
| SSE_400_audit.md | 📄 | SSE 上線時的稽核；隨 SSE shipping 多已處理，本輪未逐項重驗 |
| translation-tables/console-logs-zh-to-ja.md | ✅ | migration 後已歸零，屬 staging 產物，可封存 |

---

## 維護方式

- 本文件是**狀態快照**，不是凍結設計。每完成一個 plan 項目後在此標 ✅ 並補證據檔路徑。
- 判斷「是否真待辦」一律**對照實際 code/檔案**，不要只看設計文件的 checkbox。
- 信心標註：B 區與 A#1/#2 為本輪逐碼核對；版本史推斷者（C 區多數 ✅）以 CLAUDE.md 版本史與 Next_Plan 為據，未逐碼重驗。
