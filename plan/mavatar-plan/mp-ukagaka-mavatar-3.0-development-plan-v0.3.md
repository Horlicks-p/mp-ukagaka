# mp-ukagaka 3.0 — Mesh Avatar 實驗開發計畫書

> ⚠️ **已被取代**：本檔僅供追溯。現行基準是 `plan/mavatar-plan/mp-ukagaka-mavatar-3.0-development-plan-v0.3.2.md`（內容版本 v0.3.3）。

> **給 Claude Code 與 Codex CLI 的工作指示／Project Charter**  
> 版本：Draft v0.3.1／2026-11-11（v0.3.1：文件精簡收尾、WP 6.5 以下站點退場、2.x 安全 ZIP 發放、用詞校正；v0.3：D0 定案為後繼版；2.x EOL 與更新通道；覆蓋式升級；全身／半身決策；差分素材；ES module 與 WP 6.5；載入世代與 readiness；文件精簡）  
> 專案：[`Horlicks-p/mp-ukagaka`](https://github.com/Horlicks-p/mp-ukagaka)  
> 技術參考：[`shinshin86/mesh-avatar-studio`](https://github.com/shinshin86/mesh-avatar-studio)  
> 專案代號：**mp-ukagaka-mavatar**（`mavatar` 對應 `.mavatar` 格式；不要拼成 `mavater`）  
> **狀態：規劃階段。D0 產品身分已由擁有者決議（見 D0）。本文仍不是已批准的實作規格，也不是修改 `main` 的授權。**

### v0.3 變更摘要

1. D0 定案：**方案 A（正式後繼）**；方案 B 改為棄案摘要。
2. 新增更新通道政策：**3.0 GA 接管正式更新通道，2.x 同時 EOL**；之後的 2.x 安全修補以手動 ZIP 提供，不發 GitHub Release。
3. 明定**只支援覆蓋式升級**，不支援「刪除 2.x 再安裝 3.0」（`uninstall.php` 會刪除 `mp_ukagaka`）。
4. **全身／半身構圖**列為 Phase 0 第一個產品決策（擁有者目前傾向全身）。
5. 補齊**差分素材清單**；正式站 `.mavatar` 匯出不得包含原始立繪。
6. 3.0 **最低 WordPress 提升至 6.5**，以 `wp_register_script_module()` 載入 ES module；Phase 1 加入**以載入世代為單位的 readiness** 與重新載入鍵。
7. Phase 0 文件精簡為 **ADR-000 + PoC Results** 兩份。
8. 其他校正：WebGL2（非泛稱 WebGL）、`.mavatar` 公開即可下載、Miko 條款與本專案政策分開陳述、PUC 預設排除 prerelease 的已核實事實。
9. （v0.3.1）Architecture Assessment 併入 ADR-000；Fix Ledger 延到第一次實際移植；補 WP 6.5 以下站點的退場路徑（§D0.3、Phase 3）；2.x 安全修補 ZIP 的發放規格；「同頁比較」改為分別建置測量。

---

## 0. 請兩位 Agent 先讀這段：決策摘要

本專案將 `mp-ukagaka` 分成兩條**開發政策不同**的產品線：

1. **2.x Stable（現行 `main`）**：進入保守維護期。只處理明確 Bug、安全修補、WordPress／PHP／瀏覽器相容性，以及必要的 LLM API 供應商變動；**不再開發新功能或大規模重構**。**3.0 GA 時 2.x 進入 EOL**（見 D0.3）。
2. **3.0 Experimental（新實驗分支）**：從既有 `mp-ukagaka` 程式碼建立分支，以 **Mesh Avatar Studio／`.mavatar`／WebGL2 網格角色**作為前台角色系統。允許破壞既有視覺架構，**不以相容 2.x 的 PNG／Canvas／SVG／APNG Shell 為前提**。3.0 最終以**正式後繼**身分原地取代 2.x。

**最重要的產品目標：** 讓網站上的芙莉蓮從「播放固定畫格的偽春菜」進化為「具有自然待機、眨眼、細微頭部動作、情緒表情與說話嘴型的互動角色」。保留 mp-ukagaka 的對話人格與互動精神，**不是**把整套系統變成直播／攝影機面捕應用，也不是重新寫一個 LLM 聊天外掛。

**第一道技術 Go/No-Go 門檻：** 必須先在**完全獨立於 WordPress** 的 PoC 中，使用自行準備的芙莉蓮立繪生成 `.mavatar`，確認角色外觀、動作與實際顯示尺寸下的效果達標；在此之前，**不要先大改外掛核心**。

**授權邊界：** Claude Code／Codex CLI 負責調查現有架構、提出方案、實作小型可驗證實驗、建立測試與回報結果。資料遷移細節、發行時機、是否分拆獨立 Repo 由專案擁有者決定。**不得自行合併至 `main`、發布 Release、更新正式網站或覆蓋安定版。**

---

## D0. 產品身分：已決議為方案 A（正式後繼）

> **擁有者決議（2026-11-11）：3.0 是 `mp-ukagaka` 的正式後繼版本。** Agent 不需再提出 A／B 比較，直接以本節為契約。

### D0.1 身分契約

- 3.0 最終**原地取代**同一站點上的 2.x；**不支援 2.x 與 3.0 同站同時啟用**。
- 沿用：外掛目錄與主檔 `mp-ukagaka/mp-ukagaka.php`、slug `mp-ukagaka`、設定樹 `mp_ukagaka`、`mpu_*`／`MPU_*` 前綴、REST namespace `mp-ukagaka/v1`、text domain、更新器來源 `Horlicks-p/mp-ukagaka`。
- **不做全域重新命名。** 「Git Repo 是否獨立」仍是另一個問題（見 §2.2.1）；即使將來分拆 Repo，WordPress 身分與更新來源也必須維持後繼關係，或設計刻意、經同意的轉換路徑。
- 「不維持舊 Shell 相容」**不等於**可以默默覆蓋或刪除使用者資料；必須明確記錄哪些設定保留、轉換、停用或捨棄。

### D0.2 升級方式：只支援覆蓋式升級

`uninstall.php` 會 `delete_option( 'mp_ukagaka' )`，連同加密 API Key 一併刪除。uninstall 時無法可靠判斷使用者之後是否要安裝 3.0，**不要讓 uninstall 猜測意圖**。

- **支援**：WordPress 後台「上傳 ZIP 並取代現有版本」、外掛自動更新、檔案層覆蓋。
- **不支援**：先刪除 2.x 再安裝 3.0（會遺失設定與 API Key）；升級文件必須明確警告。
- 升級前提供設定匯出／備份確認；遷移測試必須包含「升級失敗後恢復 2.x」。
- **只有真正修改 option schema 時才引入 migration version**；純 Renderer 改造不做不必要的資料轉換。
- **加密金鑰現況（已核對）**：`includes/core/encryption-functions.php` 以 `AUTH_KEY` + 固定字串 `mpu_api_key_encryption` 衍生金鑰，支援 `mpu_enc2:`（AES-256-GCM）及舊 `mpu_enc:`、`mpu_obf:`。同站 `AUTH_KEY` 不變且保留解密邏輯時，原則上可沿用舊 API Key；仍須以測試樣本驗證，**不得寫出或記錄明文 API Key**。

### D0.3 更新通道與 2.x EOL

**已核實**：`includes/updater/github-updater.php` 使用 Plugin Update Checker（PUC），對 `Horlicks-p/mp-ukagaka` 查 `releases/latest` 並啟用 Release Asset；PUC 預設**排除 GitHub prerelease**（`vendor/plugin-update-checker/Puc/v5p6/Vcs/GitHubApi.php` 的 `getLatestRelease()`）。沒有依主版本分流的 release filter。

因此同一 Repo 只有一條「最新正式 Release」通道，以下三者**最多只能同時成立兩個**：3.0 原地後繼／2.x 長期發一般維護版／兩者共用同一更新來源。擁有者決議的政策是：

- **3.0 GA 之前**：2.x 是 Stable，正常發 Release；3.0 只能是 prerelease 或本機 ZIP。
- **3.0 GA 時**：3.0 接管正式更新通道，所有 2.x 站點會在後台看到升級到 3.0。**2.x 同時 EOL**。
- **3.0 GA 之後**：2.x 不再發 GitHub Release（避免搶走 `releases/latest`，讓 3.0 站點看不到 3.0.x 更新）。如有必要的 2.x 安全修補，只在 2.x 維護分支打 tag，**以手動 ZIP 提供**，不設計額外的更新通道。
- GA 前需公告 2.x EOL 與 3.0 的不相容事項（舊 Shell、其他 Ghost 支援範圍、最低 WordPress 6.5 等），並明確寫出「**先升級 WordPress 至 6.5 以上，再升級外掛**」。
- **最後一個 2.x Release** 必須內含清楚的 EOL／升級通知（後台提示或 readme），並作為無法升級 WordPress 之站點的最終版本。
- **WordPress 6.5 以下的站點**：GA 後既無法升級到 3.0，也收不到 2.x 一般更新。須在 WP 6.4 測試站實測更新器正確標示「需要 WordPress 6.5」且不會誤裝；這類站點以最後一版 2.x（及之後的手動安全 ZIP）為終點。
- **2.x 安全修補 ZIP 的發放規格**（寫入 ADR-000）：放置位置、通知管道、檔名（須含 `2.x` 版號，例如 `mp-ukagaka-2.40.1-security.zip`）、SHA-256 checksum，以及確認其主檔版本為 2.x、不會被誤認為 3.0 更新包的檢查方式。

### D0.4 方案 B（同站可共存的獨立外掛）：棄案摘要

需要全面隔離所有 PHP 全域函式／類別／常數、option、REST namespace、AJAX、cookie、cron、uninstall、JS 全域、DOM ID、text domain 與更新來源，成本顯著較高，且與「讓既有芙莉蓮進化」的目標無關。**不採用。** 若將來需求改變，須由擁有者重新開案。

### D0.5 ADR-000

建立 `MAvatar-ADR-000-Product-Identity.md`，記錄 D0.1–D0.3 的決議、覆蓋式升級的測試步驟、2.x EOL 的公告與安全修補發放方式，以及 §2.5 的發布隔離方案。這是 Phase 0 僅有的兩份必要文件之一。

---

## 1. 背景與目前已知事實

### 1.1 現行 mp-ukagaka

- 規劃時所見 `main`：v2.39.0，Commit `a1ddc292e6ee73315f39707f186a48e6fad76f40`。開始施工前先重新確認最新狀態，**不要把此 SHA 永久寫死**。
- 外掛已包含非 LLM 對話、LLM Provider、REST/SSE、人格、情緒標籤、互動觸摸、裝飾物及 Ghost 管理等功能。應優先保留有價值的非渲染能力，除非有實證顯示必須改動。
- 角色畫面主要由 `js/ukagaka-anime.js` 的 `window.mpuCanvasManager` 管理；該物件同時負責角色識別、初始化、動畫、載入世代與初始視覺就緒，不是單純的畫布畫圖工具。
- `js/ukagaka-core.js` 的角色切換路徑會呼叫 `mpuCanvasManager.init(...)`；前台輸出由 `includes/core/frontend-functions.php` 產生，含 `#ukagaka_img` 與 `#cur_ukagaka` Canvas。
- 芙莉蓮自己的程式位於 `ghost/Frieren/`，例如 `frieren-animation.js`、`frieren-interactions.js`；現有 `shell/Frieren/assets.json` 定義 `idle`、`sleep`、`book_flip`、`wake` 動畫序列。
- 現有顯示盒尺寸約 **134 × 249 CSS px**（依 `assets.json` 的 `layout.box`），但 SVG 畫格的實際範圍與顯示盒不相同；不要以為所有座標都能直接轉換。
- 現有「翻書」、睡眠、醒來、觸摸區域、裝飾品及角色切換包含相當多專用行為，不能把它們簡化為單一 `setExpression()` 就算完成。
- **已核實**：`js/ukagaka-base.js` 的 `mpuVisualReadyPromise` 是**整個頁面生命週期只觸發一次的 latch**；第一次 Ready 後，後續角色切換不會重新進入 pending。
- **已核實**：`js/ukagaka-anime.js` 的 `mpuCanvasManager.init()` 在已處於芙莉蓮模式時直接 early-return，不比較 Shell、asset 或 Renderer；換 `.mavatar` 或服裝會被這段吃掉。
- 外掛目前標示 WordPress 5.0+；**3.0 將最低版本提升至 6.5**（見 §4.6）。

### 1.2 Mesh Avatar Studio

- 參考 Repo：[`shinshin86/mesh-avatar-studio`](https://github.com/shinshin86/mesh-avatar-studio)。規劃時見 `main` Commit `3619620266740236327debfcf327f898a99dd7b3`；施工時確認實際版本與 API。
- 它有製作工具（Studio）與執行環境（Runtime），角色可匯出為 `.mavatar`。
- [`mesh-avatar`](https://github.com/shinshin86/mesh-avatar-studio/tree/main/packages/runtime) 為獨立 WebGL 執行環境；[`mesh-avatar-element`](https://github.com/shinshin86/mesh-avatar-studio/tree/main/packages/element) 提供不依賴 React 的 `<mesh-avatar>` Web Component，以 ES module 載入。
- Element README 明載 **Requires WebGL2**；元素離開畫面、分頁隱藏或 `paused` 時自動暫停動畫；`prefers-reduced-motion: reduce` 時減少髮絲擺動。這些上游行為**不需重做**，但 Adapter 不得破壞。
- Studio 匯出 `.mavatar` 時 **Include source image 預設勾選**，會把原始立繪一併打包。
- 規劃時所見套件版本：`mesh-avatar` **0.7.2**、`mesh-avatar-element` **0.4.4**。兩者仍可能快速更新；**實作需鎖定並記錄已驗證版本**，不可盲目追最新。
- 可使用 `setEmotion()`、`setExpression()`、`setSpeaking()`、`setVoiceLevel()`、`setParameters()`、`speak()` 等 API。`speak()` 偏向日文 Kana 嘴型動畫，**不會自行生成語音**，也不能直接假定對任意日文漢字、中文或逐字 SSE 都能準確對嘴。
- 可以把編輯器與 Agent、Python／Node.js 等工具**留在開發機**；WordPress 正式站只需經驗證的瀏覽器 Runtime、必要素材及前端整合程式。
- Runtime 原始碼使用 MIT 授權，但**範例角色 Miko 的圖像不是 MIT**。上游 `MIKO_ASSET_TERMS.md` 允許將 Miko 作為軟體／網站的一部分散布，禁止單獨散布與素材集；本專案另有更保守的政策（§5.3）。

### 1.3 已知資訊與尚待驗證事項的區別

- **已知**：存在 Web Component／Runtime、基本表情與待機 API、`.mavatar` 檔案、目前外掛的 Legacy Canvas 路徑；2.x 發行工作流程受 `release: published` 及人工派送觸發；API Key 與站點 `AUTH_KEY` 存在密鑰衍生關係。
- **合理推論，尚未證明**：Mesh 可以在 mp-ukagaka 的常駐區域流暢運作；在 134 × 249 顯示大小仍可感受表情變化；可以自然保留翻書與身體觸摸體驗。
- **尚未定案**：角色構圖（全身或半身；擁有者傾向全身）、是否最終使用 Web Component 或 Runtime 直接操控、具體 Shell Schema、是否有必要做混合 Renderer、3.0 是否仍支援任何 Legacy Ghost、模型素材的儲存與載入方式。

> **Agent 必須先驗證「推論」，再把它寫成實作契約；禁止把本計畫中的概念示例直接視為最終 API。**

---

## 2. 長期產品線與 Git 管理策略

### 2.1 Stable / Experimental 分工

| 項目 | 2.x Stable：`main` | 3.0 Experimental：新分支 |
|---|---|---|
| 目的 | 維持現有功能穩定、安全與可使用 | 探索新一代角色顯示與互動方式 |
| 功能開發 | 原則上凍結 | 允許重大改造與原型試驗 |
| PNG／SVG／APNG 相容 | 維持現有既定行為 | **不列為必要條件** |
| 既有 Ghost 相容 | 必須保護 | 可先只支援 Frieren；其他 Ghost 待決 |
| 既有 LLM／REST／SSE | Bug／服務商必要相容維護 | 盡量沿用，不因改 Renderer 而重寫 |
| WordPress、安全性 | 持續維護 | **仍必須維持基本安全與正確性** |
| 發行／正式站 | 照正常維護流程；**3.0 GA 時 EOL**（D0.3） | 未經擁有者批准不得發布或部署；GA 後接管正式更新通道 |
| 最低 WordPress | 5.0+（維持） | **6.5+** |
| 測試 | 持續執行既有 CI／E2E | 自建針對新模型的單元／整合／E2E 驗證 |

**重要澄清：「不顧舊 Shell 相容」≠「不顧正確性、安全、WordPress 或現有使用者資料」。** 實驗線可以重新定義角色畫面與 Shell 協定，但不能引入任意檔案讀取、跨站請求漏洞、未授權上傳、錯亂的使用者狀態或無法清理的非同步工作。

### 2.2 分支策略——先隔離實驗，Repo 與產品身分各自決策

**建議起步方式**：從最新 `main` 建立長期實驗分支 `experimental/mavatar`（或 `feature/mavatar-lab`，擇一），供 Phase 0/1 工作。不要以「分支位於同一 Repo」推論將來一定要 merge 回 `main`，也不要以「Repo 獨立」推論 WordPress 外掛身分一定要重命名。

```text
mp-ukagaka/ (source repository)
├─ main                         # v2.x Stable；僅必要維護
└─ experimental/mavatar        # 3.0 實驗；不得自動合併回 main

# 達到明確門檻後才由擁有者評估建立：
mp-ukagaka-mavatar/               # 可為獨立 Git Repo，不代表可同站共存
```

操作原則：

1. 分支前確認 `main` 最新、工作目錄乾淨、既有測試可執行，記錄基線 SHA。
2. Claude Code／Codex CLI 使用各自的 worktree 或短分支，不要在同一工作目錄互相覆寫。
3. `main` 和實驗線**不做自動雙向 merge 或定期全部同步**，而是依下節的修補移植規則逐項判斷。
4. PoC 不得修改正式站；WordPress 驗證一律在**獨立的 WordPress 測試站**進行。
5. **不支援在同一站點同時啟用 2.x／3.0**；3.0 以覆蓋式升級取代 2.x（D0.2）。
6. 先不要把實驗分支的版本顯示為正式 `3.0.0`，避免外掛自動更新、資料遷移及下載者誤判。

### 2.2.1 何時值得真正獨立成 Repo？（客觀門檻）

**最早在 Phase 2 的 WordPress 整合 PoC 完成後評估**，而非在 Phase 0 僅完成單張 Avatar 時就強制切割；門檻是：

- 已通過 Phase 0：自有 `.mavatar` 在獨立頁能正確載入，角色外觀、表情、待機與嘴型經擁有者接受。
- 已通過 Phase 1：最小事件 Bridge／生命週期測試可重播，載入／銷毀／快速切換不會留下舊狀態。
- **已通過 Phase 2 的最小 WordPress 垂直切片**：在隔離 WP 環境啟用 3.0 PoC，載入一隻自有 `.mavatar`，**完成一輪對話 → LLM 情緒切換 → Speaking 嘴型動作 → 結束回 Idle**；必須包含至少一條 SSE 路徑與一次取消或重新切換測試。中文／日文嘴型的品質限制要如實記錄，不要求有 TTS。
- 已有 D0 身分 ADR、安裝／資料策略、候選 Repo 的版本來源、發布方案與基本回歸測試。
- 若實際協作管理已受長壽分支困擾，或與 2.x 逐步分歧已造成大量無關 PR，提供具體例子／成本再提議分拆。

**通過門檻 ≠ 自動建立 Repo**：兩位 Agent 提供 `SPLIT / KEEP_BRANCH / HOLD` 方案與證據，由擁有者決議。如果 PoC 可以做、但沒有充分管理收益，就繼續用分支。另 Repo 的 Repo 名稱、WordPress slug 與發行標籤仍要分開設計。

### 2.3 Stable 維護準則

`main` 只接受以下類型（需有理由與測試）：

- 可重現的 Bug、安全修補、重要穩定性改善；
- WordPress／PHP／主流瀏覽器版本更新造成的相容問題；
- OpenAI／Claude／Gemini／Ollama 等既有 Provider 因 API 變動而必須修正的相容性問題；
- 維持現行功能所必需的 CI、測試或發行流程修補。

`main` 不應接受 Mesh、全新 Ghost 表現系統、無必要的格式統一、大型前台框架替換或純粹為了「3.0 預先鋪路」的破壞性重構。

### 2.4 2.x 修補如何進入 3.0？（禁止無條件 cherry-pick）

| 2.x 修正類型 | 實驗線處置 | 時限／依據 |
|---|---|---|
| **安全漏洞、秘密洩漏、權限繞過、認證／資料遺失** | **MUST PORT**：必須評估並移植等效修正；若架構不同，另做 3.0 對應修補 | 在下一次 3.0 可分享測試版前完成，影響暴露中的開發環境時立即處理 |
| WordPress／PHP 相容性造成啟用失敗或網站嚴重故障 | **PORT IF APPLICABLE**：若 3.0 仍使用該路徑，就移植 | 下一個 3.0 驗收門檻前 |
| LLM Provider、REST／SSE 既有共同底層的**重大 Bug**（錯角色／錯誤權限／資料錯亂） | **REVIEW REQUIRED**：有共用程式且可重現，就移植或做等效修正 | 依影響度安排，非安全問題不強制當天完成 |
| LLM API 供應商更新、舊模型淘汰等必要相容修正 | **PORT IF STILL USED**：確認 3.0 是否使用同一 Provider／模型，再決定 | Provider 實測前完成 |
| 2.x 的 CSS、舊 Canvas、舊 Shell、翻書動畫專用修正 | **DEFAULT NO PORT**：除非同樣影響 3.0 | 不因「保持同步」而移植 |
| 純整理、格式化、非必要功能／可選優化 | **NO PORT BY DEFAULT** | 除非擁有者另行同意 |

每項擬移植修正都必須在 `MAvatar-Upstream-Fix-Ledger.md` 記錄：`2.x Commit／PR`、問題類型與安全影響、3.0 是否適用、決策（`PORTED / EQUIVALENT / NOT APPLICABLE / HOLD`）、3.0 對應 Commit／測試、審核人與日期。**不要直接把原本 PR 當成套用補丁的許可**；尤其不能把 2.x 的視覺修補覆蓋正在重寫的 Mesh 檔案。

發現重大安全問題時，若實驗線尚未修好：**不得發布或對外分享有風險的 3.0 測試 ZIP**；對應 2.x Stable 應按其安全維護政策獨立發布（3.0 GA 後依 D0.3 以手動 ZIP 提供，不發 GitHub Release）。

### 2.5 發布命名與 GitHub Actions 隔離（先檢查，再發 Release）

**已核對現況（`main` v2.39.0）**：`.github/workflows/release.yml` 監聽的是 `release: published`，以及人工 `workflow_dispatch` 的 `tag_name`，**不是 `push: tags`**。現行工作流程依傳入 tag `git archive`，輸出固定根目錄 `mp-ukagaka/`、固定 ZIP `mp-ukagaka.zip`，再附加至該 GitHub Release。`includes/updater/github-updater.php` 透過 Plugin Update Checker 指向 `Horlicks-p/mp-ukagaka` 且啟用 Release Asset。這一套不能直接不加修改地拿來發 3.0 Alpha。

**建議命名（提案，需由擁有者確認）**：

- 2.x Stable：`v2.39.1`、`v2.40.0` 等維護線標籤；**3.0 GA 前**只有 2.x Release 可以是正式（非 prerelease）Release。GA 後 2.x 不再發 Release（D0.3）。
- 3.0 Preview：`v3.0.0-alpha.1`、`v3.0.0-beta.1`、`v3.0.0-rc.1` 等 **SemVer prerelease**；在發行隔離完成前，只能作為本機／分支標示，**不建立 GitHub Release**。
- 同 Repo 的 Git Tag 在 Repository 層級唯一，不能以「Tag 指向 3.0 分支」來保證 2.x Workflow 不會執行；`release: published` 也不會自動按 Tag 所屬分支篩選。人工 dispatch 同樣要管控。

**發行前必做的發布安全檢查**：

1. Agent 盤點 `release.yml`、`bump-version.js`、`readme.txt`、主檔版號、ZIP 根目錄／名稱、更新器、CI／Dist Parity 對 Alpha SemVer 的支援情形。特別注意 `bump-version.js` 的現有 SemVer 正規式／版本標記可能不接受 prerelease；**不得假設只換 Tag 名即可運作**。
2. 若使用同 Repo 的實驗線，提出**明確的工作流程隔離**（如標籤白名單＋Ref／Commit 核查＋獨立 Alpha Workflow／手動批准），並建立防止 Alpha 誤用 Stable ZIP 資產名的斷言；不要只靠 `if: startsWith(github.ref, 'refs/heads/main')`，因 release 事件的 ref 並不是一般 push branch ref。
3. 已核實 PUC 預設排除 GitHub prerelease（D0.3）；仍須在測試站實測一次，並確認沒有自訂 release filter 改變此行為。
4. 3.0 ZIP 必須維持根目錄 `mp-ukagaka/` 與主檔 `mp-ukagaka.php` 以支援原地升級；若改 Git Repo，舊版更新器仍指向原 Repo，須設計**刻意、經同意的轉換路徑**，不能假設 WordPress 會自己追蹤到新 Repo。
5. **正式網站禁止自動收到 Alpha／Beta**；人工分發測試 ZIP 前，需檢查 manifest、版本顯示、安裝位置與升級／回退風險。任何 `release: published`、`workflow_dispatch`、tag 建立、正式部署都需擁有者明確批准。

**最低驗收**：一次不會發布的乾跑（dry-run）驗證 `v3.0.0-alpha.1` 不會產生 2.x 用 `mp-ukagaka.zip`／不會推向 Stable 更新來源；一次 2.x 正常 Release 打包的回歸檢查。不需真的建立對外 Release 來測試。

---

## 3. 3.0 的產品願景與範圍

### 3.1 優先目標（Must-have）

- 一隻**具有芙莉蓮視覺辨識度**的 `.mavatar` 角色，風格不因分層與網格變形嚴重崩壞。
- **待機動畫**：自然眨眼、呼吸、細微頭部與頭髮動作；符合角色安靜、淡然的性格，避免過度誇張。
- **情緒與表情**：能接上 mp-ukagaka 已有的情緒輸出，不使 LLM 知道 Canvas／WebGL 的細節。
- **對話時的視覺反應**：至少能區分 Idle / Thinking / Speaking / Sleep；有可接受的嘴型或說話感，**沒有 TTS 也能運作**。
- **網站常駐展示**：能在既有角色附近的尺寸與位置顯示、收起、切換／清理，不干擾對話框及頁面導航。
- **基本可靠性**：模型載入失敗、WebGL2 不可用、重複初始化、角色切換中途取消、背景分頁停止等情境，行為可預測且不讓頁面陷入錯誤循環。

### 3.2 次要目標（Should-have，須先有 PoC 佐證）

- 情緒與表情相互轉換的可調整映射；
- 眼神或輕微跟隨滑鼠的動作（避免過度活潑）；
- 姿勢或自訂 Motion，在有素材與 Rig 能力後重現部分既有特色動畫；
- 觸摸區域與既有互動回覆整合；
- 多套 Mesh Shell／不同服裝或配件的切換；
- 必要時與傳統畫格動畫的**特定效果**共存，但前提是視覺自然且維護成本合理。

### 3.3 非目標（Non-goals）

- **不要求 3.0 完整支援 2.x 所有 PNG／Canvas／SVG Shell**；這些功能在 2.x Stable 繼續存在。
- 不強制 3.0 最初版本支援所有 Ghost，不把 DIY Ghost 完整相容當作第一道門檻。
- 不要求在 WordPress 內安裝 Studio、Python、uv、Playwright 或 Node.js 開發伺服器。
- 不以攝影機面捕、麥克風常駐或 OBS 串流為產品首要用途。
- 不要求 Blender／3D 建模，也不在起步階段開發自行替代的網格動畫引擎。
- 不把 Stable Diffusion 生圖流程綁進 WordPress 外掛。
- 不因 3.0 啟動而重寫既有 LLM Provider、MCP Tool Calling、SSE Server 或對話人格系統。
- 不急著設計通用 Marketplace、遠端 Ghost 上傳或複雜的多租戶權限系統。

---

## 4. 架構原則——目標與約束，不是強迫的實作細節

### 4.1 Renderer 的定位

建議研究下列**概念性**架構：

```text
WordPress / mp-ukagaka 核心
  ├─ 對話、LLM、SSE、情緒、人格、角色狀態
  │              │
  │           高階事件
  │              ▼
  └─ Shell / Visual Orchestrator（責任與事件協定待研究）
                 │
                 ▼
         Mesh Avatar Renderer
                 │
                 ▼
          .mavatar + WebGL2
```

**不需要在第一個 PR 就建造大型抽象框架。** 若最簡單可行的方案是薄薄的 `ukagaka-mesh.js` Bridge，就先用它驗證。經過證據確認需要時，再決定是否擴充為正式 Shell Manager。

研究並提出方案的重點：

- `mpuCanvasManager` 目前混合動畫、載入、角色切換與就緒通知，哪些責任需要移出、哪些可直接捨棄？
- 是否選擇 `<mesh-avatar>`，或直接用 `mesh-avatar` Runtime？請比較**整合複雜度、效能、可控性與第三方更新成本**。
- 如何讓現有對話模組只發出 `emotion`、`speaking_start`、`speaking_end` 等**高階語意**，不直接依賴 WebGL API？
- 如何避免不同世代的非同步載入互相覆蓋？現有 `loadGeneration`／`mpuCharacterGeneration` 應作為調查起點，而非必然照抄。
- **readiness 必須改為以載入世代為單位**（現行 `mpuVisualReadyPromise` 是頁面級一次性 latch，見 §1.1）：每次載入是否有自己的 ready／error／timeout、舊載入如何 abort、新角色何時允許顯示對話、fallback 是否也結束該世代。
- **重新載入鍵**：`(characterId, renderer, shellId, assetVersion)` 任一改變都必須開新的載入世代；不得沿用現行「已是芙莉蓮模式就 early-return」的判斷。
- 如何在 3.0 中移除／替換現有專用芙莉蓮動畫程式，而不讓聊天與事件流程斷裂？

### 4.2 Shell 設定與資料結構

已有 `ghost/Frieren/manifest.json`、`shell_folder` 與傳統 `shell_info`。請先盤點 PHP 端如何選取 Shell、產生 REST 回應與建構 URL，再提案是否引入新版 Schema。

以下只是**討論用示例**，不是已批准的規格：

```json
{
  "shell": {
    "renderer": "mesh",
    "asset": "shell/Frieren/frieren.mavatar"
  }
}
```

方案應說明：版本化方式、資產路徑驗證、錯誤處理、是否需要舊欄位、Ghost 與 Shell 的關係、前台載入格式、是否有必要允許多套 Shell。**不必為了 2.x 相容而永久背負兩套 Schema**，但不要在沒有分析 PHP 路徑時直接寫入此欄位。

### 4.3 生命週期與資源管理

最少要處理：`create / load / ready / hide / show / switch / destroy / error`，以及 `Idle / Thinking / Speaking / Sleeping / Waking` 的互動狀態。

強制驗證以下邊界：

- 角色快速切換、載入途中取消、連續多次點擊，不會留下過期角色或錯亂對話。
- WebGL Context、RAF／timer、監聽器和事件回呼能正確清理；同一頁不應無故建立多個 Avatar。
- 背景分頁／元素離開畫面時停止動畫、回到前台能恢復（上游已實作，驗證 Adapter 未破壞即可）。
- `prefers-reduced-motion`、Canvas 的無障礙 label、鍵盤操作不受影響。
- WebGL2 不可用時，至少能**優雅顯示靜態預覽／錯誤提示或停止角色渲染**。**不要求在 3.0 中重建整套舊 Canvas Renderer 當 Fallback。**
- SPA／前台常駐元件不應在換文章時反覆初始化；必要時與 `moelog-20th` 實測，但不可把修改 WordPress Theme 當成隱藏前提。

### 4.4 情緒、說話與 SSE

- 現有 LLM 的情緒標籤先經統一映射層，不要直接把模型的任意字串塞給 Runtime。
- 優先確認簡單狀態切換：Idle → Speaking → Idle、Idle → Thinking → Speaking；不能只測一段固定文字。
- `speak(text)` 為日文 Kana 導向且不產生聲音：必須驗證真實日文（漢字與假名混用）、逐字／分段 SSE、中文、標點與長文。
- 若文字導向嘴型效果不自然，可先用 `setSpeaking(true)` + 受控的嘴型強度／節奏作為替代，**不要假稱它是真正的音素級同步**。
- 停止、取消、失敗、切換角色時嘴型必須回到正常狀態；避免對話結束後嘴巴一直動。
- 不引入自動 TTS、聲音克隆或麥克風權限需求；這些屬未來另案。

### 4.5 展示尺寸、點擊與佈局

- 現有芙莉蓮約 `134 × 249` 的視覺區塊，而 `<mesh-avatar>` 預設為 `aspect-ratio: 1 / 1`，兩者不能直接等同。
- 應用實際輸出尺寸、DPR、裁切、透明背景、陰影與視覺錨點來檢驗，不要靠固定 CSS `transform: scale(...)` 硬湊到「看起來大致一樣」。
- 既有身體觸摸區域與透明像素命中判定可能依賴 2D Canvas／SVG 的座標。3.0 可重新定義命中區域，但必須避免點不到角色或點到透明背景。
- 裝飾物點擊、角色點擊、對話框、Dock 按鈕等事件的優先順序需要重新檢驗。

### 4.6 3.0 特有的介面／資料隔離選項

- 先定義 Renderer 對 LLM／SSE 的**事件契約**，才選擇具體的 Web Component 或 Runtime；外部套件 API 透過薄 Adapter 隔離，不將套件 API 散布到對話與 WordPress PHP 入口。
- **前端載入方式（擁有者決議）**：3.0 最低 WordPress 提升至 **6.5**，以 `wp_register_script_module()`／`wp_enqueue_script_module()` 載入上游 ES module；不另外打包 classic／IIFE bundle。Runtime 檔案放在外掛內（vendor 或建置產物），不依賴 CDN。
- 對 `mesh-avatar`（現讀到 0.7.2）與 `mesh-avatar-element`（0.4.4），採用**固定版本＋可重建 Lockfile＋來源及授權記錄＋相依更新回歸測試**；`0.x` 更新可能改變行為或格式，不能依賴 floating `@0` CDN。
- 調查 `.mavatar` 的格式驗證、大小、資產路徑與可疑封存檔處理；**不把使用者上傳的不可信角色檔當作已驗證素材**。
- 對中文、日文漢字、假名、英文、符號與 SSE Delta 做**對嘴策略比較**：`speak()` 的 Kana 導向能力，不等於能準確處理中／日混寫。可先採受控的「正在說話」嘴型節奏，必要時附加文字分段規則；不要把假音量或依字長的嘴型稱為音素級同步。
- 觸摸區須研究 Canvas/SVG 座標轉換為 Mesh WebGL 畫面後的真正命中區、縮放／裁切／透明背景／配件事件優先權。**與其直接搬動 2.x Y 比例，不如先制定新的可測觸摸契約**；這不代表一定要實作逐像素 GPU picking。
- **無 WebGL2 的降級是產品決策，而不是既定答案**：選項至少比較 `static PNG poster`、`靜止角色＋正常對話`、`停用角色渲染並保留聊天`、`完整 Legacy Renderer`，列出體積／複雜度／UX；**預設不重建完整 Legacy Engine**，但 Phase 2 前必須由擁有者決定最低可接受降級行為。
- **Miko（本專案政策）**：即使上游條款允許將 Miko 作為應用／網站的一部分散布，本專案仍決定**不將 Miko 插畫、模型或其 `.mavatar` 納入任何外掛 ZIP、測試 ZIP 或發行資產**；PoC 可參考官方頁面，對外發行只用自備素材或只含程式碼的測試 Fixture。
- **`.mavatar` 放上網站即等同公開可下載**：瀏覽器能載入的 URL，訪客就能取得。授權與分發應分別判斷 Git、Release ZIP、正式網站三種範圍；不要把隱藏 URL、nonce 或 CORS 當成 DRM。正式站用的 `.mavatar` **必須取消 Include source image 後匯出**。

---

## 5. Stable Diffusion 素材與 Avatar 製作流程

素材由專案擁有者使用 Stable Diffusion（可搭配 Forge Neo、LoRA、ControlNet）製作。**Agent 不必自行尋找或下載版權不明的角色圖片**，也不必把生圖系統整合進外掛。

### 5.1 PoC 素材建議

- 正面或接近正面、上半身／頭肩為主、透明或容易去背的背景。
- 較高解析度的原圖，便於切出眉眼、前髮、側髮、耳朵、肩膀與嘴型。
- 人物五官、服裝、手臂、書本等輪廓清楚；初期**不要求手持書本**，降低分層與遮擋難度。
- 嘴巴自然閉合、眼睛完整，避免髮絲跨越臉部太多。
- 優先保留角色辨識度與靜止畫品質，而不是追求極端動作幅度。
- 素材可以逐輪調整，**不能因為程式開發已投入時間就硬用效果不好的 Rig**。
- **構圖**：Phase 0 用同一角色各做「全身（約現行 134×249 顯示）」與「半身放大」兩版比較（擁有者目前傾向全身）。全身版需特別檢查臉部在實際尺寸下表情是否仍可辨識。

**差分素材包（以 Stable Diffusion inpaint 製作，與原圖同構圖、同光影、同臉型）：**

| 差分 | 用途 | 缺少時的影響 |
|---|---|---|
| 閉眼 | 眨眼、睡眠 | 只能靠網格壓扁眼睛，眨眼明顯不自然 |
| 嘴巴閉合＋主要母音嘴型（あ・い・う・え・お） | 說話 | 只能靠網格開合，說話感弱 |
| 微笑、悲傷、驚訝的眼睛／嘴 | 情緒表情 | 情緒變化幅度小 |
| 頭髮與臉部遮擋關係清楚的版本 | 分層 | 髮絲切割破圖 |

差分**不是開始 Phase 0 的硬性門檻**：可先用最小 Rig 判斷原圖是否適合；但**正式評估眨眼與嘴型之前，差分必須到位**。上游以 Codex 生圖產生差分，Claude Code 無法生圖，本專案改由擁有者以 SD 製作。

### 5.2 Agent 製作與品質檢查

1. 依官方 [`docs/agent-guide.md`](https://github.com/shinshin86/mesh-avatar-studio/blob/main/docs/agent-guide.md) 的流程校準座標、建立 Rig、切圖層與檢查固定姿勢。
2. 使用本機素材；確認有無被任何工具傳送至外部服務，**需要時先取得擁有者授權**。
3. 優先修正髮絲、眼睛、嘴巴、耳朵、臉部邊緣等可見破圖。
4. 必須同時檢查**大尺寸預覽**與 **mp-ukagaka 實際縮小顯示尺寸**。
5. 記錄所用素材版本、Studio／Runtime 版本、重要 Rig 設定與已知缺陷，避免重做時失去基準。
6. 達標才匯出 `.mavatar`，檔案應可重現、可檢查來源及版號；**要放上網站的版本取消 Include source image**，可編輯的完整版只留在本機。

### 5.3 授權與可發布性

- `mesh-avatar` 原始碼授權為 MIT；任何第三方圖片、Miko 範例與芙莉蓮角色素材的權利仍需個別確認。
- **請勿把 Miko 的範例圖直接改名後加入外掛發行包，也不要假定所有 Stable Diffusion 生成的特定既有角色都可自由散布。**
- 正式網站提供的 `.mavatar` 等同公開下載（§4.6）；是否公開提供完整模型屬分發決策。
- 初期將大體積原圖、工作檔及 `.mavatar` 視為**實驗素材**；是否跟著 GitHub 發行或正式外掛 ZIP 分發，需要擁有者明確決定。
- 若程式要公開、素材不適合分發，應考慮用獨立下載／使用者自備素材／不含角色素材的示範方式。此為發行決策，不需在 Phase 0 一次完成。

---

## 6. 分階段里程碑、交付物與 Go / No-Go

> 每個 Phase 應是一個可獨立審查的里程碑。除非擁有者明確同意，**不要跨階段一次完成所有工作**。

### Decision Gate D0 — 產品身分（已決議）

擁有者已於 2026-11-11 選定方案 A（見 D0）。剩餘工作是把決議寫成 `MAvatar-ADR-000-Product-Identity.md`（含覆蓋式升級測試步驟與 2.x EOL 方式），可與 Phase 0 平行進行。**ADR-000 完成前，Phase 2 的可安裝 ZIP 仍 BLOCKED。**

### Phase 0 — 環境調查與「角色本身」可行性（最高技術優先）

**目標：** 證明至少一個芙莉蓮 `.mavatar` 在獨立 HTML／官方 Player 中足以支撐專案方向。

**任務：**

- **第一個產品決策：構圖**。用同一素材做出「全身」與「半身放大」兩版固定畫面／短動畫，在實際網站尺寸比較，交擁有者決定（目前傾向全身）。此決定連動對話框與 Dock 佈局、翻書、身體觸摸區、手機遮擋範圍。
- 盤點 Studio 的安裝依賴、Windows 開發環境、Runtime 版本及必要工具。
- 用擁有者提供的 Stable Diffusion 圖片製作 Rig／分層／基本表情，可使用 Codex、Claude Code 互相審查，但不可直接改主外掛。
- 製作**獨立於 WordPress**的可重播 Demo；驗證眨眼、Idle、頭髮／頭部、表情切換、日文說話嘴型與錯誤行為。
- 在目標網站尺寸觀察品質、透明度、裁切及角色位置。
- 保留截圖／短錄影或比較表，記錄原圖與動態變形問題。

**交付：**

- `MAvatar-PoC-Results.md`：技術與視覺評估（含構圖比較、明確限制及版本）。
- `MAvatar-ADR-000-Product-Identity.md`（見 D0.5）。Phase 0 **只需要這兩份文件**。
- 可重播的 Demo、可載入的 `.mavatar`、素材／Rig 來源資訊（可放本機非公開區）。
- 兩位 Agent 對是否繼續的**獨立判斷**，並列出不確定性。

**Go 條件：** 構圖已決定；擁有者認為角色「看起來仍是那個芙莉蓮」，在實際尺寸有可感受且自然的待機、表情和嘴型，並且瀏覽器運作可接受。

**No-Go／HOLD：** 角色變形明顯破壞畫風、臉／耳／頭髮無法合理分層、縮小後看不出改善、成本明顯超過效果。**此時優先換素材／修 Rig，不要靠重寫外掛掩飾素材問題。**

### Phase 1 — 獨立事件 Bridge 與生命週期概念驗證

**前提：Phase 0 Go。**

**目標：** 證明既有 mp-ukagaka 的語意事件可以驅動 Mesh，且有正確的載入、銷毀與狀態恢復。

**任務：**

- 比較 Web Component 與直接 Runtime 的整合方式，提交 `MAvatar-ADR-001-Renderer.md`（此時才建立）。前提是 WP 6.5 script modules。
- 設計以載入世代為單位的 readiness，以及 `(characterId, renderer, shellId, assetVersion)` 重新載入鍵（§4.1）。
- 定義**最小**事件／操作契約（如 `load / ready / setEmotion / speakingStart / speakingEnd / sleep / destroy`；名稱由 Agent 提案）。
- 以簡單 Mock 訊息、情緒、取消和角色切換測試；先不要全面連接 REST、SSE、Ghost 設定。
- 研究資源卸載、顯示／隱藏、WebGL2 不支援、快速切換、對話結束恢復 Idle。

**交付：**

- Bridge 原型及設計 ADR。
- 事件時序圖／狀態表、失敗處理表、最小自動測試。
- 是否需要正式 Shell Manager、是否應保留 Canvas 介面包裝層的分析。

**驗收：** 同一頁無重複建立 Avatar；每次載入世代各自 ready／error／timeout，舊世代的結果被丟棄；同角色換 asset 會重新載入；快速切換不殘留舊模型；缺少 WebGL2 時不影響其他頁面操作；模型初始化失敗不會造成無限重試。

### Phase 2 — 3.0 實驗分支的 WordPress 整合

**前提：Phase 1 通過、ADR-000 完成。**

**目標：** 在**獨立 WordPress 測試環境**中以 Mesh 取代／重建實驗版的角色顯示流程，驗證必要對話功能。

**任務：**

- 依 ADR 改寫實驗分支必要的前台顯示入口、Shell 載入與角色選取。
- 讓現有對話、LLM／非 LLM、情緒標籤、Thinking、SSE 結束／取消事件能驅動 Mesh。
- 驗證角色變更、顯示／隱藏、Dock、睡眠與醒來、觸摸與裝飾物互動；無法保留的 2.x 視覺效果需**明確列為 3.0 已知差異**，不假裝已支援。
- **落實 D0**：在測試站以覆蓋式升級從 2.x 升到 3.0，驗證舊設定與 API Key 可沿用，並演練恢復 2.x。不得以「不管舊 Shell 相容」為藉口跳過資料契約。
- `readme.txt`／主檔標頭的 `Requires at least` 改為 6.5。
- 原則上不因 Renderer 改造而重寫 LLM Server／MCP Tool；API Key 與 option 結構依 D0 遷移或隔離策略處理，任何轉換都要有安全備份／回復測試。
- 補一條**可回放的 WordPress 垂直切片測試**：一隻 `.mavatar` → 非 LLM／LLM 對話 → 情緒切換 → SSE Speaking 嘴型 → 結束回 Idle → 取消或角色替換，不允許殘留舊角色或錯話。
- 建立 WordPress 環境下的測試與前端建置流程；避免依賴測試機的全域 npm 套件或未鎖定 CDN 版本。

**交付：**

- 測試分支可安裝的實驗外掛（不發布正式 Release）。
- 完整測試結果、已知未支援功能、設定與清理文件。
- 與 2.x Stable 比較的功能矩陣，以及正式 3.0 要保留／捨棄的提案。

**驗收：** 在本機 WordPress 可正常啟用、切換角色或明確限制為 Frieren，且已通過**單隻 `.mavatar` 的情緒切換＋SSE 對嘴垂直切片**；重新整理／導航／連續互動不產生明顯狀態錯亂；測試環境停止後沒有不應存在的背景任務。此時才評估是否獨立 Repo（見 §2.2.1）。

### Phase 3 — 產品方向審查與 3.0 候選版本

**前提：Phase 2 達標，且擁有者批准進入產品化。**

**目標：** 決定實驗成果是否真的值得升級成 3.0，而不是為了版本號而發布。

**任務：**

- 使用者體驗比較：2.x SVG／Canvas 與 3.0 Mesh 的畫風、流暢度、回應性、手機／低階裝置影響。
- 評估網格角色、Bundle 和 `.mavatar` 的載入成本、記憶體／WebGL 資源，提出可接受門檻（以實測為基準，不預填虛構效能數字）。
- 釐清正式版要採「Mesh-only」「Mesh-first」或有條件兼容 Legacy；**不必為維持 2.x 的功能承諾而自動選擇雙 Renderer**。
- 依 D0 完成錯誤監測、資料與素材授權、發行檔案、覆蓋式升級／回退、文件更新，以及 **2.x EOL 公告**（不相容事項、最低 WP 6.5、2.x 安全修補的取得方式）。
- WP 6.5 以下站點的退場驗證（D0.3）：WP 6.4 測試站實測更新器標示「需要 WordPress 6.5」且不誤裝；最後一版 2.x 已含 EOL／升級通知。
- 完成 `release.yml` 隔離及正式站更新器對 Alpha／Beta 的排除驗證；不得直接拿 2.x 發行 workflow 打包 3.0。
- 在不同瀏覽器與真實網頁情境進行最終 E2E。

**交付：**

- `3.0 Release Readiness` 報告與 Go/No-Go 清單。
- 版本策略、使用者遷移／不相容說明、回退指引。
- 擁有者確認是否發布 3.0、持續實驗、或停止此方向。

**明確規則：** 即使 Phase 3 通過，**沒有擁有者的明確授權，也不得自行發布、部署或將實驗線合併到 Stable `main`。**

---

## 7. 優先驗證的功能矩陣

以下是規劃上的驗收清單；`P0/P1` 表示**實驗線的優先次序**，不是代表現在已支援。

| 行為 | 優先度 | 最低驗收／備註 |
|---|---:|---|
| 角色正確顯示及透明背景 | P0 | 大小與定位可控，不遮蔽對話框 |
| Idle／眨眼／呼吸／頭髮 | P0 | 自然、穩定，符合角色氣質 |
| 情緒映射（Happy／Sad／Angry 等） | P0 | 有可理解的視覺變化，不直接暴露 Runtime 給 LLM |
| Speaking 開始／結束 | P0 | 可與 SSE 或打字機狀態協調；取消後停止 |
| 角色載入錯誤／快速切換 | P0 | 無 stale response、重複 context 或無限重試 |
| WebGL2 不支援或失敗 | P0 | 擁有者選擇最低降級方案後測試；預設不重建舊 Canvas Engine |
| WordPress 基本前台與對話功能 | P0（Phase 2） | 單一獨立測試站能完成 `.mavatar`、情緒、SSE Speaking、結束回 Idle |
| Sleep／Wake | P1 | 可以重新設計表現，不一定逐幀重現舊動畫 |
| 觸摸區域／裝飾品 | P1 | 點擊語意正確，透明背景不誤判 |
| 翻書動作 | P1／探索 | 重要的角色特色，需另做素材／Motion 技術判斷 |
| 其他 Ghost 相容 | Deferred | 初期允許 Frieren-only；不自動承諾 |
| 錄音／攝影機／OBS | Out | 非產品目標 |
| TTS、音素級唇形 | Out | 不列為第一版必要條件 |
| 構圖（全身／半身） | P0（Phase 0） | 兩版比較後由擁有者決定 |
| 載入世代 readiness／重新載入鍵 | P0（Phase 1） | 換角色、換 asset 都產生新世代；舊結果丟棄 |
| 覆蓋式升級與資料保留 | P0（Phase 2） | 覆蓋升級後設定與 API Key 可用；可回退 2.x |
| 3.0 Alpha 防誤發 | P0（發布前） | Release 乾跑不產生穩定版 ZIP、不污染更新器 |

**重要：** 即使翻書動畫暫時做不到，也必須**在報告中列出並由擁有者決策**，不能悄悄刪掉已存在且具有角色辨識度的互動。

---

## 8. 工程驗證與品質要求

### 8.1 開工前先做的技術盤點

- 列出 mp-ukagaka 當前實際使用的 Renderer、Ghost、Shell、事件、資料載入與通知介面。
- 掃描 `mpuCanvasManager` 的所有呼叫點，區分「視覺相關」與「對話／流程控制相關」依賴。
- 盤點 `mpuCharacterGeneration`、`loadGeneration`、`mpuVisualReady`、`frierenLoadGeneration` 等世代／就緒機制，整理跨檔案事件流程。
- 確認 `ghost/Frieren/shell/Frieren/assets.json` 的比例與動畫契約，以及 `frieren-interactions.js` 的點擊區域座標。
- 檢視前台 JS Bundle 與 WordPress `wp_enqueue_script()` 路徑；建議 Runtime 以**本機鎖版的建置產物**交付，不以 floating CDN `@0` 作為正式版依賴。
- 核對現有 CI、PHPUnit、JS smoke、Playwright E2E 是否有可重用的腳手架；實驗分支應建立自己的回歸保護。

### 8.2 測試層級

1. **Avatar 資產與 Rig**：格式驗證、能載入、固定姿勢截圖與視覺檢查；不可只憑 Schema 成功判定畫質成功。
2. **Bridge 單元測試**：狀態機、情緒映射、重複 init、錯誤／取消、快速切換、destroy、fallback。
3. **瀏覽器整合測試**：WebGL2、有／無模型、背景頁籤、容器隱藏、尺寸變化、角色切換、資源清理。
4. **WordPress E2E**：開站、顯示／收起、非 LLM 對話、LLM 對話、串流取消、睡眠／情緒／觸摸流程。
5. **視覺與效能實測**：在相同網址、版面與裝置條件下，**分別**使用 2.x／3.0 建置測量（不是同站同時啟用）；量測載入、Bundle 大小、記憶體、GPU／CPU 和小尺寸畫質，不預設合格數字。

對於前端無法完全自動化的視覺品質，允許人工驗收，但應提供相同視角與尺寸的 Before／After 記錄。

### 8.3 回歸與停止條件

- **禁止**因測試難寫就刪除穩定版既有測試，或將 Fail 改為 Skip 當作修復。
- 若某項功能 3.0 決定不支援，應**明確移入 3.0 的功能差異表**，而不是默默忽略。
- 若某項改動會同時觸及 REST、SSE、LLM Provider、PHP 設定和 Renderer，必須先拆分並解釋；不能一個 PR 一次動遍全部系統。
- PoC 沒通過時，先停止大範圍改造，更新實驗紀錄，不得擅自把它包裝成完成的 3.0。

---

## 9. 風險登錄（由 Agent 更新，不可直接宣布已解決）

| 風險 | 可能後果 | 驗證／緩解方向 |
|---|---|---|
| 單張立繪無法自然分層與網格變形 | 模型品質不及現有 SVG | 先做高品質單角色 PoC、調整 SD 素材與 Rig |
| 134 × 249px 低尺寸失去動態優勢 | 投入成本無實質回報 | 固定尺寸比較、選擇足夠明顯但自然的動作 |
| 翻書、醒來、睡眠無等價 Mesh Motion | 3.0 失去角色特色 | 列差異、驗證替代方案、由擁有者決定 |
| 現有 Canvas／Ghost 互動深度耦合 | 改 Renderer 牽動聊天功能 | 呼叫點調查、薄 Adapter、分階段測試 |
| SSE／中文與日文混合造成嘴型效果不自然 | 人物說話不可信 | 比較文字導向與狀態驅動的嘴型方案 |
| 快速角色切換留下過期載入 | 顯示錯角色、記憶體洩漏 | 世代檢查、取消／destroy 測試 |
| WebGL2 不支援、GPU 資源與多 Context | 手機／低階裝置卡頓 | 單一模型、暫停不可見動畫、優雅退化 |
| 上游套件快速改版或外部 CDN 不穩 | 破壞正式站或重新建置失敗 | 鎖版、Vendor／Bundle、保留版本紀錄 |
| `.mavatar` 檔案體積與不可信來源 | 載入慢、ZIP 格式風險、資產路徑問題 | 本地可信檔、格式驗證、限制大小與路徑 |
| 範例角色／粉絲創作素材授權 | 無法公開發行素材 | 程式 MIT 與角色資產分離、發行前審核 |
| 使用者以「刪除 2.x 再裝 3.0」升級 | `uninstall.php` 刪除 `mp_ukagaka`，設定與 API Key 遺失 | 只支援覆蓋式升級、文件警告、升級前備份 |
| 3.0 GA 後再發 2.x Release | 搶走 `releases/latest`，3.0 站點收不到 3.0.x | D0.3：GA 後 2.x 只以手動 ZIP 提供 |
| 正式站 `.mavatar` 含原始立繪 | 高解析原圖被下載 | 匯出時取消 Include source image |
| 頁面級 visual ready latch | 換角色時對話在新模型就緒前顯示 | 以載入世代為單位的 readiness |
| 長期分支與 Stable 漸行漸遠 | Bug fix 雙重維護 | 手動選擇移植、定期評估是否獨立 Repo |
| 後繼版遷移既有加密 API Key 失敗 | LLM 無法使用、Key 遺失或洩漏 | 保留同站 AUTH_KEY 條件、離線 Fixture 測試、備份與回退演練 |
| 3.0 Alpha Release 誤觸 2.x Release workflow／updater | 正式網站收到實驗版，覆蓋穩定版 | `release: published` 與 dispatch 隔離、tag／ZIP／Slug 契約與 dry-run |
| 0.x Mesh 套件升級破壞 runtime 行為／API | 畫面或嘴型變化、載入失敗 | 固定 `mesh-avatar`／`mesh-avatar-element` 版本及薄 Adapter |
| 中文與日文混寫在 Kana-only speak 流程下失真 | 說話感不自然或無動作 | 中文／日文／SSE Delta 對比測試；可用狀態型嘴型退化 |
| 觸摸區按舊 Canvas 比例映射 | 點錯部位或透明區誤觸 | 新命中測試與座標映射，不盲拷舊 2.x 門檻 |
| Miko 範例被誤打包進外掛 ZIP | 角色圖資授權問題 | 發行黑名單、ZIP 掃描、只用權利確認素材 |

---

## 10. Claude Code 與 Codex CLI 的協作方式

為避免兩個 Agent 互相覆蓋工作、或在未確認的假設上同時開發，建議分工如下。

### 10.1 Claude Code：現有系統盤點／架構與產品契約

優先任務：

- 讀取 `mp-ukagaka` 的 Ghost、前台生命週期、對話、情緒、Shell 資料流。
- 現有系統盤點（元件責任、依賴、可能 Breaking Changes）**併入 ADR-000 的附錄**，不另建文件。
- 比較 Web Component 與 Runtime，提出**2～3 個可行架構選項**及利弊；不得只給單一選項不附理由。
- 規劃優先級、驗收條件、錯誤與回退政策。
- Code Review Codex 的 PoC 結果及風險；如不同意提出可驗證的反例。

### 10.2 Codex CLI：Mesh Studio PoC／技術實驗／測試

優先任務：

- 依官方 Agent Guide、官方 Runtime／Element API 實測 Mesh Studio。
- 使用擁有者提供的 Stable Diffusion 圖檔建立 `mavatar` PoC；**未取得素材時只準備環境與測試方法，不得以隨機角色冒充已完成的 Frieren 驗證**。
- 以獨立 HTML 測試動畫、API、載入與銷毀、網頁實際縮小尺寸。
- 提供可重現指令、版本、效能觀察、截圖／影片與已知失敗點。
- 與 Claude 的設計結論交叉驗證，但不要在不同的 worktree 同時修改同一個共享檔案。

### 10.3 協作與衝突處理

- 分工僅是建議，不限制 Agent 的擅長領域；最終由擁有者決定。
- 可以平行做**讀取／研究**；寫程式時分別使用 worktree／獨立短分支，**不得共用會互相覆寫的工作目錄**。
- 每項重大方案需列出：「已驗證事實」「推論」「尚待確認」。
- 有歧見時先設計最小可驗證實驗，**不要只靠模型互相說服**。
- 不要在未獲批准前改動 Stable `main`、調整線上設定或公開包含角色素材的 Release。

---

## 11. 建議在實驗分支保存的文件與工作產物

以下為建議名稱，請依現有 Repo 文件習慣調整，**不是要求一次建立全部空文件**。

```text
plan/
  # Phase 0：只需要這兩份
  MAvatar-ADR-000-Product-Identity.md   # D0 決議、覆蓋式升級、2.x EOL、發布隔離
  MAvatar-PoC-Results.md                # 構圖比較、獨立 HTML 驗證與視覺評估

  # 之後真的產生相關工作時才建立
  MAvatar-ADR-001-Renderer.md           # Phase 1
  MAvatar-Upstream-Fix-Ledger.md         # 第一次需要移植 2.x 修補時
  MAvatar-Compatibility-Matrix.md       # Phase 2
  MAvatar-Release-Readiness.md          # Phase 3

# 本機非公開實驗材料（位置由 Agent 與擁有者確認）
experiments/mavatar/
  demo.html
  ...

# Stable Diffusion 原圖、高解析度源檔、Rig 工作目錄及大體積模型
# 不預設全部加入版控；先檢查 .gitignore、授權及分發風險。
```

每個 PR／里程碑報告至少包含：**目標、修改範圍、排除範圍、驗證方式與結果、失敗記錄、風險、下一步建議**。

---

## 12. 立即執行的「第一個委託」——給 Claude Code／Codex CLI

> **注意：以下是讓 Agent 開始工作時使用的指令；開工前先確認是實驗工作目錄。第一輪預設只調查與提出方案，不要求一口氣完成 3.0。**

### 可直接貼給 Claude Code 的指示

```text
你是 mp-ukagaka 3.0 / mavatar 實驗線的架構審查與規劃 Agent。

請先完整閱讀附上的《mp-ukagaka 3.0 — Mesh Avatar 實驗開發計畫書》，並以當前 Repo main 為基準重新核對既有前台、Shell、Ghost、情緒、SSE、載入世代與 WordPress 整合路徑。再核對 Mesh Avatar Studio 的實際 API 與版本。

第一階段只進行閱讀、盤點、風險分析與技術提案。請不要直接重寫 mp-ukagaka，也不要改 main、合併 PR、發布 Release 或部署正式站。

請交付：
1. **ADR-000**：D0 已決議為 A（後繼）。把 D0.1–D0.3 寫成 ADR，含覆蓋式升級與回退 2.x 的測試步驟、2.x EOL 公告內容。
2. 既有 API Key 的 AUTH_KEY 衍生／舊格式相容、option／uninstall 的覆蓋升級驗證方式。
3. `release.yml` 實際觸發是 `release: published`／`workflow_dispatch`，`bump-version.js` 不接受 prerelease 版號；請提出 Alpha 防誤發 dry-run。
4. 2.x→3.0 安全／LLM Provider／REST 修補移植規則（§2.4 已定；Fix Ledger 等第一次實際移植時再建立）。
5. 已知事實 vs 尚待驗證假設；現有 Canvas/Frieren/Shell 模組依賴圖與重要呼叫點。
6. Web Component vs Runtime 方案（以 WP 6.5 script modules 為前提）、載入世代 readiness、中文／日文 SSE 嘴型、觸摸區、WebGL2 靜態降級與 0.x 鎖版方案。
7. Phase 0/1 的可執行驗收測試、停止條件、Repo 分拆的 WordPress 垂直切片門檻與分工。
8. 第一個真正需要寫程式的最小 PR 建議（先說明，不要自行施工）。

你的任務是讓擁有者能決策，不是一次做完 3.0。
```

### 可直接貼給 Codex CLI 的指示

```text
你是 mp-ukagaka 3.0 / mavatar 實驗線的技術驗證與 PoC Agent。

請先閱讀附上的《mp-ukagaka 3.0 — Mesh Avatar 實驗開發計畫書》尤其 D0 產品身分決策與 2.4/2.5 發布隔離，再確認 Mesh Avatar Studio、mesh-avatar runtime、mesh-avatar-element 的當前版本、依賴與官方 Agent Guide。

Phase 0 目標：在不修改 mp-ukagaka main 與正式 WordPress 網站的前提下，用擁有者提供的 Stable Diffusion 立繪做出可重播的 .mavatar 與獨立 HTML Demo，確認眨眼、Idle、情緒、頭部/頭髮動作、日文文字說話嘴型。先做「全身（約 134x249px）」與「半身放大」兩版比較，交擁有者決定構圖（目前傾向全身）。

若尚未有立繪：只做環境盤點、預先列出素材要求、測試腳本規劃；不得把 Miko 範例或自動隨機圖視為已完成芙莉蓮 PoC，也不得擅自下載版權不明素材。

技術實驗時請：
- 鎖定 mesh-avatar（規劃時 0.7.2）與 mesh-avatar-element（規劃時 0.4.4），透過薄 Adapter 驗證實際 API；不要假設 0.x 版本相容。
- 為所有自動生成／重新建置的素材保存來源資訊、命令與測試結果。
- 驗證模型重載、destroy、WebGL2 不可用、prefers-reduced-motion、縮小後視覺效果；比較無 WebGL2 時的靜態 PNG／純聊天降級方案。
- 比較日文 Kana、漢字混寫與中文的嘴型效果，不要將 `speak()` 稱為通用音素級同步。
- 不把 Miko 範例及未確認權利的素材放入任何外掛 ZIP 或發行資產（本專案政策）；要放網站的 .mavatar 取消 Include source image。
- 不引入正式站 CDN 或 Node.js 開發伺服器依賴。
- 不碰現有 LLM Provider、REST/SSE 後端及 Stable main。

交付 PoC 與真實結果（成功、失敗、仍未驗證）；不要因為 Phase 0 無法通過，就自動跳到外掛整合階段。
```

---

## 13. 最終決策與成功定義

**本次啟動 3.0 的成功，不是「Git Diff 很大」，也不是「外掛原本的 Canvas 全部刪掉」。**

成功應該是：

1. 2.x Stable 繼續可靠地在網站上運作，不因 3.0 實驗而被迫升級或停用。
2. 3.0 實驗線能自由探索 Mesh Avatar，不再為了既有 PNG/SVG/APNG Shell 的相容負擔而妥協新架構。
3. 第一個芙莉蓮 `.mavatar` 在實際網站角色尺寸仍然好看、具有自然動態，並可被對話／情緒事件驅動。
4. 覆蓋式升級與資料保留已驗證、2.x EOL 已公告；GA 前實驗 ZIP 不會被當成 2.x Stable 更新，GA 後 3.0 順利接管更新通道。
5. Agent 每個決策有測試或資料支撐；當技術路線不划算時能夠停止，而不是無限制重構。
6. 只有在技術與視覺雙重驗收完成、擁有者批准後，才考慮正式的 mp-ukagaka 3.0。

**一句話的開發原則：**

> **守住 2.x，解放 3.0；先證明角色值得動起來，再決定外掛應該怎麼改。**

### 13.1 擁有者需要做的最少決策（Agent 只能提案，不能代決）

- [x] **D0**：A 後繼；不支援 2.x／3.0 同站啟用。（2026-11-11）
- [x] **2.x 退場**：3.0 GA 時 EOL；之後安全修補以手動 ZIP 提供，不發 Release。（2026-11-11）
- [x] **最低 WordPress**：6.5，使用 script modules。（2026-11-11）
- [ ] **構圖**：全身或半身（傾向全身；Phase 0 比較後定案）。
- [ ] **資料**：舊站設定、API Key、角色／對話資料要保留哪些？
- [ ] **Repo**：在 Phase 2 垂直切片後選 `KEEP_BRANCH / SPLIT_REPO / HOLD`，不是先寫死獨立日期。
- [ ] **發布**：Alpha 是否僅限本機 ZIP？
- [ ] **無 WebGL2**：選靜態 PNG、保留聊天但不顯示角色，或其他經實測的最小降級；不把整套舊 Renderer 當預設義務。

---

## 14. 參考資料

- mp-ukagaka：[Repository](https://github.com/Horlicks-p/mp-ukagaka)；[`js/ukagaka-anime.js`](https://github.com/Horlicks-p/mp-ukagaka/blob/main/js/ukagaka-anime.js)；[`js/ukagaka-core.js`](https://github.com/Horlicks-p/mp-ukagaka/blob/main/js/ukagaka-core.js)；[`ghost/Frieren/manifest.json`](https://github.com/Horlicks-p/mp-ukagaka/blob/main/ghost/Frieren/manifest.json)；[`ghost/Frieren/shell/Frieren/assets.json`](https://github.com/Horlicks-p/mp-ukagaka/blob/main/ghost/Frieren/shell/Frieren/assets.json)；[`includes/core/frontend-functions.php`](https://github.com/Horlicks-p/mp-ukagaka/blob/main/includes/core/frontend-functions.php)；[`.github/workflows/release.yml`](https://github.com/Horlicks-p/mp-ukagaka/blob/main/.github/workflows/release.yml)；[`includes/updater/github-updater.php`](https://github.com/Horlicks-p/mp-ukagaka/blob/main/includes/updater/github-updater.php)；[`includes/core/encryption-functions.php`](https://github.com/Horlicks-p/mp-ukagaka/blob/main/includes/core/encryption-functions.php)；[`tools/node/bump-version.js`](https://github.com/Horlicks-p/mp-ukagaka/blob/main/tools/node/bump-version.js)
- Mesh Avatar Studio：[Repository](https://github.com/shinshin86/mesh-avatar-studio)；[官方 Agent Guide](https://github.com/shinshin86/mesh-avatar-studio/blob/main/docs/agent-guide.md)；[Avatar Format](https://github.com/shinshin86/mesh-avatar-studio/blob/main/docs/format.md)；[Element README](https://github.com/shinshin86/mesh-avatar-studio/blob/main/packages/element/README.md)；[Runtime README](https://github.com/shinshin86/mesh-avatar-studio/blob/main/packages/runtime/README.md)

*註：以上路徑、版本與 API 是規劃時核對的快照，正式實作前請由 Agent 再次確認；本文的架構示例是設計候選，不是上游已承諾的 API 或 mp-ukagaka 既有實作。*
