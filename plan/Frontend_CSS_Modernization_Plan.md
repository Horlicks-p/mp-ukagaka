# 前端 CSS 現代化整理計畫（`css/mpu_style.css`）

> 📅 初稿：2026-09-01
> 📋 目標：把 703 行、81 個 `!important`、零自訂屬性的 `mpu_style.css`，整理成可維護、可被主題安全接管的現代 CSS
> 🧭 參照對象：`C:\D\php\moelog-20th\style.css`（v2.8.4，2693 行，同作者的 WordPress 主題）
> 🎯 想定讀者：實作擔當 + 交叉 review（家 CODEX / Gemini）
> 🔖 對象版本基點：v2.29.0
> 📂 影響範圍（**§11 已收緊**）：`css/mpu_style.css`（主體）、`tools/node/package.json` + `package-lock.json`、新增 stylelint config、Phase -1 截圖腳本。~~`includes/core/frontend-functions.php`（inline style 清理，Phase 3）~~ 與所有 JS／文件變更一律另案，理由見 §11.6
> 🧩 CODEX 檢討整合：2026-09-01（外掛 cascade 邊界、漸進相容、無障礙與驗收順序）
> 🧩 CLAUDE 第二輪：2026-09-01（核實 CODEX 事實主張、修正四處合併殘留、提出 C2-1～C2-4，見 §9）
> 🧩 CODEX 第二輪／作者裁決：2026-09-01（接受 C2-1～C2-3；作者拍板 Q-4 保留省略號呼吸效果）
> 🧩 作者確認：2026-09-01（**深色模式不在路線圖上**；token 化改以內部去重為目的，公開 API 契約暫緩發布）
> 🧩 **家庭端整合評審：2026-09-01**（家 CODEX + 家 CLAUDE 各自獨立評審後合併；共 14 項待處理（H-1～H-14），其中 **7 項會改動視覺／版面**——5 項立即可見、2 項為 RTL 潛伏。**結論：現稿不可直接開工**，見 §11）

---

## ⚠️ 狀態

**提案階段，尚未動工；且經 §11 家庭端整合評審後，現稿不可直接開工。** 新增的驗收約束是「**基本不改動 ghost layout，只改良 CSS、讓代碼更現代化更易於維護**」——原稿有數項在此約束下屬於實質視覺變更，須先依 §11 修正範圍才能進入 Phase -1。

**§11 待處理項目共 14 條**，必須先解決的有三類：token 公開性全文矛盾（§11.1）、會改動 layout 的項目（§11.2）、Phase -1 不可執行（§11.4）。

> 以下兩段為家庭端評審前的狀態描述，保留供追溯：
>
> ~~**方向未決項已清空。** C2-1～C2-3 均已收斂；Q-4 經作者裁決採用「單一省略號 + opacity 呼吸」方案。實作前只需完成 Phase -1 基準資料，不再等待設計方向確認。~~
>
> ~~**實作階段待拍板**：§4.1 的捲軸軌道歸屬與兩個灰色合併（P0-A）；§4 P2 的 `.mpu-thinking` 脈動幅度（P2）。B-4 的 glyph 變更已確認為預期差異，見 §5 註記。~~

**上述待拍板項的現況**：捲軸軌道歸屬與兩個灰色合併仍有效（於 P0-A 內決定）；`.mpu-thinking` 脈動幅度已由 §11.3 的 reduced-motion 更正取代；**B-4 的 glyph 變更已移出本輪範圍**（§11.2）。

---

## 0. TL;DR

三個結論，其餘都是細節：

1. **不要引入 `@layer`。** 主題該用，外掛不該用——理由見 §2，這是本計畫唯一「參照對象有、我方刻意不採用」的特性。
2. **最高價值的一步是自訂屬性化**，不是語法現代化——把散落 25 處的顏色字面值收斂成單一來源。token 設計照語意化與可對外的規格做，但**是否發布為公開 theming API 暫緩**：深色模式不在路線圖上，該契約目前沒有已知消費者（見 §4.1 註記）。（初稿曾宣稱「可讓 `moelog-20th` 刪掉檔尾那段深色補丁」，該段實際針對 `moelog-ai-qna-links`，與 MPU 無關——見 §4.1 的 CODEX 更正。）**§11.1 定案：本輪 token 僅作內部去重，不發布 theming API、不改文件，全部視為可自由重命名的內部實作。**
3. **81 個 `!important` 有 57 個集中在 `#ukagaka-dock`**，全是防禦主題的 `ul / li / a` 樣式。這是 cascade 邊界問題，不是語法問題；但不以大範圍 `all: revert-layer` 或重複 ID 當主要解法，改採元件級 reset、正常特異性與少量有理由的 `!important`。

---

## 1. 現況盤點

### 1.1 量化對照

| 指標 | `mpu_style.css` | `moelog-20th/style.css` |
|---|---|---|
| 行數 | 703 | 2693 |
| `!important` 數量 | **81** | 少量，且每處都有註解說明理由 |
| CSS 自訂屬性 | **0** | ~120 個 token（含深色模式整組覆寫） |
| `@layer` | 無 | `reset, base, layout, components, utilities, overrides` |
| 原生巢狀 | 無 | 無（採扁平 + `:is()`） |
| 邏輯屬性 | 無 | 大量 `margin-inline` / `padding-inline-start` / `inset-block-start` |
| `:is()` / `:has()` | 無 | 大量使用 |
| 深色模式 | **無** | `html.dark-mode` + `color-scheme` 全套 |
| `prefers-reduced-motion` | **無** | 有（view-transition 與 SPA 進度條） |
| `@supports` 漸進增強 | 無 | 8 組 |
| 建置流程 | 無（原檔直出） | 無（原檔直出） |
| Lint 守門 | **無** | 無 |

### 1.2 `!important` 分佈（實測）

```
16  #mp_ukagaka #ukagaka-dock ul li a / a:link / a:visited
10  #mp_ukagaka #ukagaka-dock
10  #mp_ukagaka #ukagaka-dock ul
 9  #mp_ukagaka #ukagaka-dock ul li
12  #mp_ukagaka #ukagaka-dock ul li.{gotop,hide,change} a:hover/:focus/:active
─────────────────────────────────────────────────────
57  小計：dock 區塊（:315-399 實測確認，佔全檔 70%）

 8  #mp_ukagaka .ukagaka-msgbox-border a（含 a img）
10  .mpu-gift-picker-{nav,item}（防主題的 button 樣式）
 6  其他零星（filter / APNG 尺寸 / suitcase opacity / chat-mode overflow）
```

**判讀**：這支 CSS 的複雜度幾乎全部來自「防禦主題」，而非它自身的設計。整理方向因此不是「寫得更現代」，而是「用更好的機制取代逐條 `!important` 防守」。

### 1.3 重複值統計（實測）

```
#1e3a8a                   10 次   主文字色
rgba(30, 58, 138, 0.x)    15 次   同一個藍的 8 種透明度（.08/.22/.25/.3/.5/.55/.7/.85）
rgba(200, 200, 200, 0.3)   4 次   捲軸軌道（一般態 + chat-mode 各兩組）
"Noto Sans TC", ...        5 次   字型堆疊
#1d8ac3                    3 次   連結／focus 色
```

`rgba(30, 58, 138, …)` 就是 `#1e3a8a` 的 rgb 分量——同一個顏色在檔案裡以兩種寫法散落 25 處。

### 1.4 `z-index` 現況

```
-1      #mp_ukagaka::after（圖片預載 hack）
 1      .mpu-gift-picker-item:hover
20      .frieren-emoji
99      canvas / #frieren_idle_apng   ← 註解說明必須低於 JS 硬寫的裝飾物 z-100
10000   #ukagaka_shell
10001   #ukagaka_msgbox
10002   .mpu-think-bubble / .mpu-state-badge
10030   .mpu-gift-picker
```

`99` 與 JS 端的 `z-100` 之間存在隱性契約，目前只寫在註解裡（`css/mpu_style.css:58`）。

---

## 2. 核心決策：`@layer` 不採用

`moelog-20th` 使用 `@layer` 是正確的——它是**主題**，需要讓自己容易被外掛與使用者覆寫。mp-ukagaka 是**外掛**，處境相反。

CSS 串接規則：**未分層（unlayered）的作者規則，永遠勝過任何 `@layer` 內的規則，與特異性無關。** 主題的 CSS 是未分層的普通規則，因此只要把 `mpu_style.css` 包進 `@layer`，在移除 `!important` 之後會被主題整片輾過去——結果比現況更糟。

這件事參照對象自己已經寫過了，`moelog-20th/style.css:2645`：

> Unlayered is the whole point: every rule in this theme's `@layer` blocks loses to *any* unlayered declaration regardless of specificity, and the plugin ships plain unlayered CSS. Putting these overrides in the `overrides` layer would lose to `background: #fff` even though the selector is stronger.

**決定：`mpu_style.css` 全檔維持未分層。** 檔頭加註解記錄此決策與理由，避免未來有人「順手現代化」時把它包進 layer。

> 補充：理論上可用「分層 + `!important` 反向排序」的技巧達成強防禦，但那會讓可讀性比現況的 81 個 `!important` 更差，不予採用。

---

## 3. 決定事項總表

| 論點 | 決定 | 理由 |
|---|---|---|
| `@layer` | **不採用** | 外掛需要贏過主題，分層會反向削弱。見 §2 |
| 自訂屬性 | **採用，定義在 `#mp_ukagaka` 上；~~視為公開 API~~ → §11.1 定案：僅內部去重，不發布契約** | 原理由「讓主題一行接管配色」在深色模式不列入路線圖後已無消費者。見 §4.1 與 §11.1 |
| `!important` 整理 | **逐元件盤點；元件級 reset + 正常特異性，第三方防禦例外保留** | `all` 會重置可存取性與 UA 行為；重複 ID 則把權重債換成選擇器債。見 §4.2 |
| 原生巢狀 | **第一輪不採用；先用扁平規則 + `:is()` 收攏** | 完整 selector 可直接被 `rg`／CodeGraph 命中，較適合三方交叉 review；壓行數不是主要目標。見 §4.3 |
| 邏輯屬性 | ~~僅限文本流屬性，定位屬性維持物理值~~ → **§11.2 定案：全檔維持物理屬性，本輪不做任何邏輯屬性轉換** | 原「文本流」欄的兩項（dock `padding-left`、`text-align: right`）實為視覺錨點；扣掉後只剩一條規則。見 §4.4 與 §11.2 |
| 現代顏色語法 | **採用 `rgb(r g b / x%)`，透明度階梯用 `color-mix()`** | 與主題一致；`rgb(from …)` 相對顏色語法暫緩（相容性）。見 §4.5 |
| 深色模式 | **不在路線圖上**（2026-09-01 作者確認），非僅本次不做 | 對話框底是點陣圖（`msgbox_bg.png` 等），純換色做不完整；且無此需求。連帶影響公開 API 是否發布，見 §4.1 註記與 §7 |
| 檔案拆分 | **不拆，維持單檔** | 無建置流程，拆檔會多出 HTTP 請求或逼引入 bundler |
| 相容性底線 | **核心樣式採成熟特性；2023+ 特性只能漸進增強或提供 fallback** | 公開外掛不能讓日期標籤取代失敗模式分析。見 §6 |
| Lint | **固定版本安裝 stylelint 並掛進 `npm run verify`** | 不用會臨時下載最新版的裸 `npx`；規則由寬到嚴逐步導入 |

---

## 4. 分階段實作

### Phase 0（P0）— 自訂屬性（**§11.1：僅內部去重，不對外**）

**這是投報率最高的一項。** 價值是內部去重（25 處顏色字面值 → 單一來源）。~~對外 theming 是設計上保留的可能性~~ → **§11.1 定案：不發布 theming API、不寫文件，全部 token 一律 `--mpu-internal-*` 前綴，視為可自由重命名的內部實作。**

在 `#mp_ukagaka` 上定義 token：

```css
#mp_ukagaka {
  /* 全部為內部實作，可自由重命名；不構成 theming API（§11.1）。
     命名刻意全用 --mpu-internal-*，避免任何一半看起來像對外契約。 */

  /* ---- 基礎色與字型 ---- */
  --mpu-internal-ink: #1e3a8a;              /* 主文字色 */
  --mpu-internal-link: #1d8ac3;             /* 連結／focus ring */
  --mpu-internal-link-hover: #6d6d6d;
  --mpu-internal-surface: rgb(255 255 255 / 92%);
  --mpu-internal-muted: #6b7280;
  --mpu-internal-font-family: "Noto Sans TC", "Noto Sans JP", serif;
  --mpu-internal-radius-message: 12px;
  --mpu-internal-duration-fast: 0.18s;

  /* ---- 由基礎色推導的衍生色（靜態 fallback，下方 @supports 覆寫）---- */
  --mpu-internal-control-border: rgb(30 58 138 / 30%);
  --mpu-internal-control-border-strong: rgb(30 58 138 / 55%);
  --mpu-internal-focus-ring: rgb(29 138 195 / 20%);
  --mpu-internal-scrollbar-thumb: rgb(30 58 138 / 50%);
  --mpu-internal-scrollbar-thumb-hover: rgb(30 58 138 / 70%);

  /* ---- 層級 ---- */
  --mpu-internal-z-preload: -1;
  --mpu-internal-z-canvas: 99;   /* 必須 < ghost decorations.json 的 z_index 100，見 B-9 */
  --mpu-internal-z-emoji: 20;
  --mpu-internal-z-shell: 10000;
  --mpu-internal-z-msgbox: 10001;
  --mpu-internal-z-bubble: 10002;
  --mpu-internal-z-picker: 10030;

  /* ---- 捲軸：兩組值不同，不可合併（§11.3 H-7）---- */
  --mpu-internal-scrollbar-size: 6px;
  --mpu-internal-scrollbar-radius: 3px;
  --mpu-internal-scrollbar-size-chat: 8px;
  --mpu-internal-scrollbar-radius-chat: 4px;
  --mpu-internal-scrollbar-track: rgb(200 200 200 / 30%);
}

@supports (color: color-mix(in srgb, red, transparent)) {
  #mp_ukagaka {
    --mpu-internal-control-border: color-mix(in srgb, var(--mpu-internal-ink) 30%, transparent);
    --mpu-internal-control-border-strong: color-mix(in srgb, var(--mpu-internal-ink) 55%, transparent);
    --mpu-internal-focus-ring: color-mix(in srgb, var(--mpu-internal-link) 20%, transparent);
    --mpu-internal-scrollbar-thumb: color-mix(in srgb, var(--mpu-internal-ink) 50%, transparent);
    --mpu-internal-scrollbar-thumb-hover: color-mix(in srgb, var(--mpu-internal-ink) 70%, transparent);
  }
}
```

**`color-mix()` 是精確等價，不是近似**：依 CSS Color 5 的 premultiplied 插值，`color-mix(in srgb, #1e3a8a 30%, transparent)` 的結果精確等於 `rgba(30, 58, 138, 0.3)`；八個 alpha 階（.08/.22/.25/.3/.5/.55/.7/.85）換算百分比全為整數、無誤差。**這是 P0-A 能宣稱零視覺差異的支點。**

> **⚠️ 維護陷阱（既然不對外，衍生機制的唯一價值就是內部單一來源）**：`@supports` 區塊讓「改一次 `--mpu-internal-ink`、衍生色自動跟上」成立，但**只在支援 `color-mix()` 的瀏覽器上**。若日後有人改動 ink 卻忘了同步上方的靜態 fallback，舊瀏覽器會拿到過期的衍生色。請在靜態區塊就地留註解說明兩者必須同時改；或者，若團隊認為這個耦合比去重價值更麻煩，**可以直接放棄 `@supports` 區塊、只保留靜態值**——不對外之後，這是一個可自由取捨的實作細節，不再是契約問題。

~~關鍵在**定義位置**。定義在 `#mp_ukagaka` 上，主題就能針對仍由 CSS 繪製的部分接管：~~

```css
/* ~~示意：只接管仍由 CSS 繪製的文字／連結~~ → §11.1：本輪不提供此用法，範例僅存查 */
html.dark-mode #mp_ukagaka {
  --mpu-color-ink: var(--color-text-dark);
  --mpu-color-link: var(--color-link-sidebar);
}
```

> **CODEX 更正：** `moelog-20th/style.css:2645` 後的第三方深色補丁實際針對的是 `moelog-ai-qna-links` 的 `.moe-aiqna-block`，不是 MP Ukagaka。該段文字可用來說明「主題與外掛應透過契約協作」的原則，但不能當成 MPU 已存在的需求或宣稱 MPU token 上線後即可刪除那段補丁。

~~此外，`--mpu-color-surface` 也不能讓 PNG 對話框真正變成深色。公開 token 第一版只承諾……避免形成假的深色模式 API。~~

**§11.1 取代**：既然不發布契約，就不存在「承諾了什麼」的問題。但底層事實仍成立且值得記錄——`--mpu-internal-surface` 改不動 PNG 對話框（`msgbox_*.png`、`think-bubble.png` 皆為淺色點陣圖），**這正是深色模式不能只靠 token 完成的原因**（見 §7.1）。

`moelog-20th/style.css:2653` 的註解表達了相同的跨專案維護原則：

> The plugin is a separate codebase on its own release cycle, so styling it from here keeps theme updates from carrying plugin edits. **If the plugin ever adopts CSS custom properties or layers, delete this block.**

~~**驗收**：完成後在 `docs-en/DEVELOPER_GUIDE.md` 新增一節「Theming the frontend widget」，把 `--mpu-*` 公開清單寫成契約。~~

**§11.1 取代**：本輪**不新增任何文件**。依本節自己的規則「沒有文件的變數不算 API」，不寫進 `DEVELOPER_GUIDE.md` 即不形成相容性承諾。驗收改為：所有 token 一律使用 `--mpu-internal-*` 前綴（含原本規劃公開的 8 個），明示全部可自由重命名。

> **公開契約是否發布，視深色模式是否列入路線圖而定（2026-09-01 作者確認：目前不列入）。**
>
> C2-3 把公開 token 從 8 個擴到 13 個，理由完全來自「宿主主題完整覆寫 palette、但瀏覽器不支援 `color-mix()` 會半套失效」——那是深色模式場景。深色模式既不在路線圖上，這 13 個 token 的公開契約就沒有已知消費者。
>
> **因此 P0-A 的預設做法是：只做內部 token 化，暫不發布 API 契約。** 依上一行自己的規則，不寫進 `DEVELOPER_GUIDE.md` 就不形成相容性承諾，日後改名不算破壞性變更——保留彈性，也省掉本項的文件工作。
>
> 這**不改變 §4.1 的 token 設計本身**：語意化命名、`--mpu-internal-*` 前綴區分、靜態 fallback + `@supports color-mix` 推導全部照做。差別只在「是否對外承諾」。若日後深色模式進入路線圖，把既有的 13 個 token 寫進文件即可升格為 API，不需重做。

~~**公開契約決定（C2-3 已收斂）：** 第一版公開 13 個 token……需要完整換色的宿主主題必須明確覆寫全部 13 個公開 token。~~

**§11.1 推翻此決定，理由是 13 個並不足以完整接管 palette。** 家 CODEX 盤點出未被涵蓋的活躍顏色：gift picker hover surface `rgb(219 234 254 / 98%)`、picker surface `rgb(255 255 255 / 98%)`、picker shadow、placeholder `#9ca3af`、stream badge 的 success／warning／error／timeout 狀態色。家 CLAUDE 另查出三個仍活著的 alpha 值未被 token 覆蓋：

| 值 | 位置 |
|---|---|
| `rgba(30,58,138,0.25)` | `.mpu-gift-picker` border `css/mpu_style.css:476` |
| `rgba(30,58,138,0.22)` | `.mpu-gift-picker` box-shadow `css/mpu_style.css:479` |
| `rgba(30,58,138,0.85)` | state badge thinking `css/mpu_style.css:692` |

（CODEX 原列的 user／assistant message 背景屬 B-1 死碼，會被整組刪除，不列入證據。）

因此「完整換色只需覆寫 13 個」的敘述不成立，公開它反而會給出一個做不到的承諾。**定案：不發布，全部內部化**；`box-shadow` 與 badge 狀態色明確保留字面值，不強行 token 化。

~~**命名與 fallback 原則已反映在上例：** 公開 token 使用語意完整名稱……API 文件必須同時示範兩種用法。~~

**§11.1 取代的命名原則**：**不再區分公開／內部——全部 `--mpu-internal-*`。** 沒有 API 文件，因此也沒有「兩種用法」要示範。靜態值與 `@supports` 推導的取捨降級為實作細節，見上方維護陷阱註記。

**實作時待拍板的 token 邊界：**

1. ~~**捲軸軌道的歸屬。** ……二擇一並寫進契約：(a) 升為第 14 個公開 token；(b) 明文寫「軌道刻意維持中性」。~~ → **§11.1 已消解**：不發布契約，就沒有「第 14 個公開 token」這個問題。軌道與滑塊都是 `--mpu-internal-*`，無需拍板。
2. **兩個灰色的合併（仍需拍板）。** `.mpu-gift-picker-counter` 用 `#64748b`（slate-500），`--mpu-internal-muted` 定為 `#6b7280`（gray-500）。token 化等於要挑一個，視覺差異幾乎不可辨，但屬需明確拍板的合併，不應在實作時默默決定。（`.mpu-msg-role` 的 `#6b7280` 隨 B-1 消失，不列入考量。）

---

### Phase 0（P0）— 消除 `!important`

依衝突來源逐元件處理。

#### CODEX 檢討結論：不採全域 `all` reset，也不以重複 ID 為常態

以下原提案保留作為被否決方案的紀錄：

```css
#mp_ukagaka#mp_ukagaka :where(ul, ol, li, a, button, input) {
  all: revert-layer;
}
```

不採用理由：

1. 本檔未分層時，`revert-layer` 的效果接近回退整個作者 origin；套在 `a`、`button`、`input` 會一起重置 `display`、字型、line-height、outline、appearance 等大量行為。
2. `all` 不會重置 custom properties，卻會重置由它們消費的普通屬性，形成「token 還在、元件行為卻回 UA」的難查狀態。
3. 後續必須完整重建每個控制項狀態，漏掉 `disabled`、`:focus-visible`、`[hidden]` 或 forced-colors 行為的風險，比保留幾個有註解的 `!important` 更高。

刻意重複 ID 同樣不作為主要方案：

```css
/* 現況：(1,1,3) + 16 個 !important */
#mp_ukagaka #ukagaka-dock ul li a { display: block !important; /* … */ }

/* 不採用：(3,1,3)，可贏過主題但會製造新的權重債 */
#mp_ukagaka#mp_ukagaka #ukagaka-dock#ukagaka-dock ul li a { display: block; }
```

**採用方案：**

1. 先以實際衝突分類宣告，而不是以「81 → 某個數字」為目標。
2. 使用元件邊界的明確 reset，例如 `#mp_ukagaka #ukagaka-dock :where(ul, li)`，只重置該元件確定需要接管的 `margin`、`padding`、`list-style`、`background`、`border` 等屬性。
3. 自家規則用正常的 root + component 特異性；若一般 WordPress 主題規則仍能勝出，再針對該宣告保留 `!important` 並留下理由。
4. `all: revert` / `revert-layer` 只允許用在範圍極小、所有狀態都已重建的 leaf component，且必須經鍵盤、disabled 與 forced-colors 驗收。

**保留的 `!important` 證據表（C2-2 已收斂）：** 每個實作後仍保留的宣告都必須在本表獨佔一列，並在原地留下相同理由。填不出「宿主規則類型」或「交叉測試」任一欄，就應移除。

| 位置／宣告 | 防禦的宿主規則類型 | 交叉測試覆蓋 | 處置 |
|---|---|---|---|
| `css/mpu_style.css:31` — `filter: none !important` | 關燈／深色模式對 `img`、`canvas` 套用 `brightness()` 或其他 filter | `moelog-20th` dark mode：角色、Canvas 與切換瞬間無變暗／閃爍 | 保留 |
| `css/mpu_style.css:61-65` — APNG `width/height/max-width` | 常見的響應式 `img { max-width: 100%; height: auto; }` | `moelog-20th` 與未客製的核心主題：APNG 原始尺寸及裝飾物對位不變 | 保留 |

`suitcase` 的 `opacity: 1 !important` 不列入保留表：它只覆寫同檔 `.frieren-decoration { opacity: .9 }`，改用正常選擇器順序即可移除。其餘目前的 `!important` 均先視為待證明，不預先核准。

**驗收規則**：不設數字 KPI。最終 CSS 中每個 `!important` 必須與上表逐項對得上，且指定的主題交叉測試通過；表格本身就是可稽核的自然上限。

---

### Phase 1（P1）— 扁平元件區塊 + `:is()`

第一輪維持參照主題採用的扁平寫法，三大群組以相鄰區塊與 `:is()` 收攏：

```css
#mp_ukagaka #ukagaka-dock {
  width: 250px;
  height: 45px;
  float: right; /* §11.3：必留。移除後 dock 會靠左，見 §5「已排除的誤判」 */
  background: url(../images/menu.png) no-repeat center;
}

#mp_ukagaka #ukagaka-dock ul {
  display: flex;
  justify-content: center;
  align-items: center;
  height: 45px;
  gap: 19px;
  padding-left: 64px; /* §11.2：視覺錨點，維持物理值 */
}

#mp_ukagaka #ukagaka-dock li {
  font-size: 0;
}

#mp_ukagaka #ukagaka-dock li > a {
  display: block;
  width: 45px;
  height: 45px;
  cursor: pointer;
  overflow: hidden;
}

#mp_ukagaka #ukagaka-dock li.gotop > a:is(:hover, :focus-visible, :active) {
  background-image: url(../images/top.png);
}
```

**不採巢狀的主因是可搜尋性（C2-1 已收斂）。** 這份無建置、需由 CLAUDE／CODEX／Gemini 交叉 review 的原始 CSS，讓 `rg "#ukagaka-dock ul li a"` 能直接命中完整 selector，比少寫約 200 行更有維護價值。無轉譯的相容性風險只是次要理由：它確實有界，也能由瀏覽器矩陣驗證，不單獨拿來支撐決策。

原本 `:376-399` 三組各 4 行的 `background / background-image / background-repeat / background-position` 重複宣告（那是為了對抗主題而堆的），在 `!important` 消除後只需 `background-image` 一行。

同樣處理：
- `.mpu-gift-picker-*` 群組（`:437-604`，14 條規則 → 以 `:is()` 收攏狀態選擇器，整併為 button / slider / item 三個相鄰區塊）
- `#ukagaka_msgbox.chat-mode` 群組（`:261-303`，8 條規則 → 一個相鄰區塊，`::-webkit-scrollbar-*` 併入下一條）
- `#ukagaka_msg` 的 4 條 `::-webkit-scrollbar-*`（`:243-259`）與 chat-mode 的另外 4 條（`:283-299`）→ ~~變數化 `--mpu-internal-scrollbar-size` 後合併為 1 組~~
  > **⚠️ §11.3 更正：兩組值不同，直接合併會造成視覺回歸。** 一般態 `width: 6px` / `radius: 3px`（`:245`）；chat-mode `width: 8px` / `radius: 4px`（`:284`）。Firefox 的 `scrollbar-color` 兩邊相同可共用，webkit 那組不行。**修法**：`--mpu-internal-scrollbar-size: 6px` 之外另加 `--mpu-internal-scrollbar-size-chat: 8px`（或在 `.chat-mode` 內覆寫同一個 token），radius 同理。

**預估**：仍可透過去重與 `:is()` 明顯縮短；行數不是驗收標準，清楚的元件邊界與可搜尋性優先。

---

### ~~Phase 1（P1）— 邏輯屬性（**有條件採用**）~~ → **§11.2 刪除本項**

> **本節整段作廢。** 家 CODEX 與家 CLAUDE 獨立得出相同結論：下表 ✅ 欄的兩個項目其實都是**視覺錨點**，不是文本流——
>
> - dock `ul` 的 `padding-left: 64px` 是把三顆按鈕對齊固定方向的 `menu.png`（`background: no-repeat center`），RTL 下 padding 會翻、點陣圖不會翻。
> - `text-align: right` 實際不在 `#ukagaka_msg`（全檔只有 `css/mpu_style.css:107` 的 `.mpu-think-bubble` 與 `:618` 的 B-1 死碼）。而氣泡尾巴 `::after` 釘死在物理 `right: 40px`（`:130`），換成 `end` 正是本節自己警告的「CSS 翻了、對位沒翻」。
>
> 兩項移入 ❌ 欄後，✅ 欄只剩 `#ukagaka_msg { padding: 10px 15px }` **一條規則**，不值得開一個 Phase。**改為：全檔一律維持物理屬性**，只在檔頭寫一行註解說明理由。P1-B 的邏輯屬性半段從工時表移除。

這裡與參照主題的做法要刻意分開。主題內文是文本流，`margin-inline` 全面採用完全正確；但 mpu 的定位有兩種性質：

| 性質 | 例子 | 處置 |
|---|---|---|
| ~~**文本流相關**~~ | ~~`#ukagaka_msg` 的 `padding: 10px 15px`、`text-align: right`、dock `ul` 的 `padding-left: 64px`~~ | ~~✅ 換成 `padding-inline` / `text-align: end`~~ → **§11.2：後兩者實為視覺錨點，整列作廢** |
| **視覺錨點** | `#ukagaka_shell { right: 0 }`、`.mpu-think-bubble { right: 38px }`、尾巴 `::after { right: 40px }`、`#ukagaka { padding-right: 65px }` | ❌ **維持物理屬性** |

**理由**：角色是「釘在畫面右下角」的物件。氣泡尾巴的對位、表情位置、以及 `js/ukagaka-emoji.js:138-140` / `:172-174` 硬寫的 `style.left` / `style.top`，全都是視覺座標。若 CSS 單方面換成 `inset-inline-end`，站台上了 RTL 語系後角色會跑到左側，但 JS 的 `style.left = '100%'` 不會跟著翻 → **表情與角色分家**。

~~**決定**：只換文本流部分。~~

**§11.2 修訂後的決定**：**全檔維持物理屬性，不做任何邏輯屬性轉換。** 僅在檔頭註解寫明「定位與對位屬性刻意保留物理值，因與 `js/ukagaka-emoji.js:138-139`／`:172-173` 硬寫的 `style.left`／`style.top` 視覺座標耦合，且 dock 與氣泡尾巴依賴固定方向的點陣圖；要做 RTL 必須 CSS + JS + 圖片三者同時處理」，避免未來被當成疏漏「補齊」。

---

### Phase 1（P1）— 現代顏色語法

`rgba(30, 58, 138, 0.5)` → `rgb(30 58 138 / 50%)`（與主題一致），再讓支援的瀏覽器由 `--mpu-internal-ink` 經 `color-mix()` 推導。

**暫緩**：`rgb(from var(--mpu-internal-ink) r g b / 30%)` 相對顏色語法。它更精簡，但本案不需要用較新的語法取代已有靜態 fallback 的 `color-mix()` 漸進增強。

---

### Phase 2（P2）— 漸進增強（挑有用的抄）

參照主題 `@supports` 那 8 組，對 MPU 值得採用或補強的項目如下：

| 項目 | 套用位置 | 價值 |
|---|---|---|
| `overscroll-behavior: contain` | `#ukagaka_msg` | 聊天記錄滾到底時不會連動整頁捲動。**實打實的體驗修復**，優先度最高 |
| `prefers-reduced-motion: reduce` | think bubble 的 `translateY` 淡入、gift picker 的 `scale(1.12)` hover | 目前**完全無保護**，無障礙審查會抓。**注意順序**：`mpu-msg-fade-in` 不列入，它隨 B-1 整組刪除，須先做 B-1 再做本項，否則會替死碼補保護 |
| ~~`text-wrap: pretty`~~ | ~~`#ukagaka_msg`、`.mpu-think-bubble`~~ | **§11.2 移出本輪**：會改變換行結果與對話框高度，屬視覺變更。另列「可選視覺改善」 |
| `:focus-visible` | 對話框 OK／Cancel 與 dock 三個操作 | 現況把 `outline` 全面移除；必須補回不影響滑鼠操作的鍵盤焦點指示 |
| ~~`dvh` + safe-area fallback~~ | ~~shell 與 message box~~ | **§11.2 移出本輪**：會改變 shell／message box 的尺寸與位置，屬視覺變更。另列「可選視覺改善」 |

> ~~**`.mpu-thinking` 的 opacity 脈動刻意不列入 reduced-motion 停用清單。**……關掉之後思考中與閒置態會長得完全一樣，等於用無障礙改善換來功能退化。~~
>
> **⚠️ §11.3 更正：此論證的事實前提是錯的。** 查 `js/ukagaka-base.js:612-616`：
>
> ```js
> function mpuRenderThinkBubble($bubble, text, showSpinner) {
>     $bubble.empty().text(text || '');
>     if (showSpinner) {
>         $bubble.append('<span class="mpu-thinking"></span>');   // 僅思考態才建立
>     }
> }
> ```
>
> `.mpu-thinking` span **只在 `showSpinner` 為真時建立**，且 `::after` 的 `content` 在基礎規則上（`css/mpu_style.css:648-651`）。停掉動畫得到的是**靜態省略號**，與閒置態（氣泡整個 hidden）差異明顯，不存在「兩態長得一樣」的問題。**改採**：
>
> ```css
> @media (prefers-reduced-motion: reduce) {
>   .mpu-thinking::after { animation: none; opacity: 1; }
> }
> ```
>
> 連帶：§⚠️ 狀態段列的「`.mpu-thinking` 脈動幅度待拍板」已無需拍板。

**不抄**：`view-transition`（外掛不控制導航）、`content-visibility`（元素太小無收益）、`-webkit-line-clamp`（無此需求）、`backdrop-filter`（對話框底是點陣圖，糊化沒意義）。

---

## 5. 順手該修的實際問題

以下與現代化無關，但在同一次整理中處理成本最低。

| # | 位置 | 問題 | 處置 |
|---|---|---|---|
| B-1 | `css/mpu_style.css:603-629` | `.mpu-chat-message`、`.user`、`.assistant`、`.mpu-msg-role` **全 repo 零引用**（PHP / JS / `js/dist/` 皆無）；其專用 `mpu-msg-fade-in` 也成為孤兒 | 整組刪除；刪前再以 production bundle 與執行時 DOM inventory 確認一次 |
| B-2 | `css/mpu_style.css:593` + `:601` | `.mpu-gift-picker-fallback` 連續宣告兩次 | 合併為一條 |
| B-3 | `css/mpu_style.css:12` + `:15` | `#ukagaka_shell` 同時有 `position: fixed` 與 `float: right`；fixed 之下 float 被忽略 | 刪 `float` |
| B-4 | `css/mpu_style.css:650`、`:654-663` | `@keyframes mpu-thinking-dots` 的 0% / 50% / 100% 三處 `content` 值**完全相同**（都是 `"…"`），實際只有 `opacity` 在閃 | **作者已裁決：保留單一省略號呼吸效果。** 固定 `content: "…"` 放在 `.mpu-thinking::after`，keyframes 只控制 opacity，動畫改名 `mpu-thinking-pulse`。**⚠️ §11.2：glyph 更換是明確視覺差異，本輪移出範圍另案處理**；若日後執行且要求零差異，固定 glyph 應選 `"..."` 而非 `"…"`（見下方註記） |
| ~~B-5~~ | `css/mpu_style.css:680` | `.mpu-state-badge` 字型堆疊結尾是 `sans-serif`，其他五處都是 `serif` | ~~badge 統一到 `var(--mpu-font-family)`~~ → **§11.2 移出本輪**：改成 `serif` 收尾在缺 Noto Sans TC/JP 的機器上是實質渲染變更，而 `.mpu-state-badge` 只是 v2.18 的 runtime debug 工具（見該規則自身註解），不一致無害 |
| B-6 | `css/mpu_style.css:23` | `margin-bottom: 0px`（單位冗餘） | → `0` |
| B-7 | `css/mpu_style.css:228` | `border: 0 solid`（`solid` 無意義） | → `border: 0` |
| B-8 | `css/mpu_style.css:341-353` | dock `li` 同時有 `list-style-type: none` 與 `list-style: none` | 保留後者 |
| B-9 | `css/mpu_style.css:58` / `:63` | `z-index: 99` 的隱性契約只寫在註解。~~與 JS 端裝飾物 `z-100`~~ → **§11.3 更正：全 repo 的 JS/PHP 都沒有 z-index 100**，實際來源是 ghost **資料**：`ghost/Frieren/decorations.json:22`（`z_index: 100`）與 `:56`（`101`），另有 `:39/74/91/108` 的 `5/8/6/7` 在 canvas 之下；由 `ghost/Frieren/dist/frieren-bundle.js:2044` 渲染成 inline style。99 是刻意劃在 ghost 作者資料中間的分界線，任何第三方 ghost 都能自填 | 本輪**只做** CSS 端變數化 `--mpu-internal-z-canvas`。~~在 JS 端註解回指~~ → 契約應寫進 `docs-en/GHOST_CREATE_GUIDE.md`；~~`decorations.json` 註解~~ → **標準 JSON 不支援 `//` 或 `/* */`**，改用既有的 `_comment` 資料欄位（`ghost/Frieren/decorations.json:2` 已在用；`personality-decorations.php:35-38` 回傳完整 JSON，後續消費者只讀 `items`）。**屬 ghost 作者文件，另案**（§11.6） |
| B-10 | `css/mpu_style.css:219-224` | **`.nextmsg` 是死碼。** 全 repo grep `nextmsg` 命中的全部是 `mpu_nextmsg` 函式名與 `mpu_nextmsg_llm` requestId，**沒有任何 `class="nextmsg"`**；markup 用的是 `<a id="mpu_ok_btn"><img …></a>`（`includes/core/frontend-functions.php:277-281`） | 整條刪除。順帶化解 §11.5 點名的「`:219` 無 `#mp_ukagaka` root scope」——刪掉即可，不需煩惱要不要加 root |

> **B-4 實作註記（Phase -1 對比時勿誤判）：** 位元組層級確認，基礎規則與 keyframes 用的是**不同字元**——
>
> ```
> css/mpu_style.css:650   content: "..."   → 2E 2E 2E（三個 ASCII 句點）
> css/mpu_style.css:657   content: "…"     → E2 80 A6（U+2026）
> css/mpu_style.css:661   content: "…"     → E2 80 A6
> ```
>
> 因此現況在會套用離散 `content` 動畫的瀏覽器上，glyph 實際會從 `...` 跳成 `…`；不套用的瀏覽器則一路停在 `...`。裁決採用的 `content: "…"` 固定於基礎規則，等於**在後者那群瀏覽器上把三點改成單一省略號字元**。字元選擇正確（`…` 才是原意），但這會在 Phase -1 基準圖對比中呈現為真實差異，**屬預期變更，不是回歸**。

**已排除的誤判**：初評時曾懷疑 `#ukagaka-dock` 的 `float: right !important`（`:319`）無效。實查 `includes/core/frontend-functions.php:257-300`，dock 的父層 `#ukagaka_shell` 是 block，兄弟 `#ukagaka` 為 `float: right` 並由 `.mpu-clear` 清除——**這個 float 是有效且必要的**，不可刪。

---

## 6. 相容性底線

這是**公開發布的 WordPress 外掛**，使用者的瀏覽器分佈不可控，底線不能照個人網站的標準訂。

**能力分級：** 核心佈局與操作不可依賴失敗後會整組消失的新語法；2023+ 特性若無 fallback，只能用於「不支援也不影響操作」的漸進增強。Baseline 2023 可作測試矩陣的參考，不作為替代 WordPress 專案相容政策的單一承諾。

| 特性 | 全綠時間 | 判定 |
|---|---|---|
| CSS 自訂屬性 | 2017 | ✅ 採用 |
| `:is()` / `:where()` | 2021 | ✅ 採用 |
| `:focus-visible` | 2022 | ✅ 採用（現況已部分使用） |
| `color-mix()` | 2023-05 | ⚠️ 採用，但關鍵顏色先寫靜態 fallback，再以支援的新宣告覆蓋 |
| `:has()` | 2023-12（Firefox 121） | ⚠️ 採用但需 `@supports selector(:has(*))` 包覆 |
| **原生巢狀** | 2023-08（Firefox 117） | ❌ 第一輪不採用；完整 selector 的 grep-ability 與三方 review 效率優先 |
| `overscroll-behavior` | 2022 | ✅ 採用 |
| `text-wrap: pretty` | 2024-2025 陸續 | ⚠️ 技術上純漸進增強、不支援時無損，**但 §11.2 已移出本輪**：它會改變換行結果與對話框高度，屬視覺變更 |
| `rgb(from …)` 相對顏色 | 2023-12 起 | ❌ 暫緩，改用 `color-mix()` |
| `light-dark()` | 2024-05 | ❌ 出局（且深色模式本次不做） |
| `@scope` | 2024-07（Firefox 128） | ❌ 出局 |
| `@layer` | 2022 | ❌ **刻意不用**，理由見 §2 |

---

## 7. 明確排除的範圍（Non-goals）

0. **手機與平板版。** MPU 在 `mpu_is_show_page()` 內以 `wp_is_mobile()` 於伺服器端停止輸出，`css/mpu_style.css` 本身沒有 mobile media query。`moelog-20th/style.css` 的 `max-width: 768px` 隱藏規則只是該主題的第二層防護。本計畫不設計觸控版或手機版；只防止桌機使用者在縮放、窄視窗與分割畫面時發生關鍵控制項溢出或不可操作。

1. **深色模式實作。** 對話框底是點陣圖（`msgbox_bg.png` / `msgbox_top.png` / `msgbox_bottom.png` / `think-bubble.png` / `think-tail.png`，皆為淺色），純換 CSS 顏色只會做出「深色文字配淺色圖片框」的半套結果。要真正支援得同時處理圖片（`image-set()` / `filter` / 改為純 CSS 繪製），那是獨立的視覺工程。

   **作者確認（2026-09-01）：深色模式目前不在路線圖上**，不僅是本次不做。本計畫的 token 化因此以**內部去重**為主要目的——把散落 25 處的顏色字面值收斂成單一來源；能否支撐未來的深色模式是附帶效果，不是動機，也不構成排程理由。

2. **`#mp_ukagaka::after` 圖片預載 hack 的改造**（`css/mpu_style.css:148-157`）。這招有效但隱晦，現代做法是 PHP 端 `wp_head` 輸出 `<link rel="preload" as="image">`。但它牽涉 `includes/core/frontend-functions.php:260` 直接輸出的 inline `style="display:none;"`，以及 `:1218` / `:1224` 的 jQuery `fadeOut(400)`（會寫 inline style，與 CSS transition 互踩）。**跨 CSS / PHP / jQuery 三層，應獨立成 issue，不混入本計畫。**

3. **markup 內的 inline style 清理**（`frontend-functions.php:275`、`:281` 的 `style="margin-top:14px;margin-left:65px"` 等）。同上，屬 PHP 端變更，建議 Phase 3 另案處理。

4. **`css/admin-style.css`。** 後台樣式不受主題干擾，問題性質完全不同，本計畫不涵蓋。

---

## 8. 驗收方式

這支 CSS 目前沒有任何測試，改完只能靠眼睛。建議：

1. **Phase -1：視覺基準線先行。** 在任何 token 化或 selector 改寫之前，對現況拍五組基準圖——
   > **⚠️ §11.4 事實更正：`.playwright-mcp/` 目前不存在**（`ls -d .playwright-mcp` → No such file or directory），repo 內也沒有任何 CSS 視覺測試腳本。本項在補齊 §11.4 的六項前置條件之前**不是可交付的 Phase**，只是一個方向。

   - 一般態（角色 + 主對話框）
   - `chat-mode` 展開（含輸入框、捲軸）
   - gift picker 展開（單項 / 多項 slider 兩種）
   - think bubble 顯示（`system` 短語態與 `chat` 長文態）
   - dock hover 三顆按鈕
2. ~~每個 Phase 完成後逐張對照，差異須能逐條解釋。~~ → **§11.7 收緊**：「能解釋」允許有解釋的視覺變更通過，與「不改動 ghost layout」的約束不符。改為：**除白名單外，基準圖須 pixel-identical**，比對指令設 diff 閾值。**本輪白名單應為空**（B-4 已移出範圍）。
3. **主題交叉測試**：至少在 `moelog-20th`（亮/暗兩態）與一個未針對 MPU 客製的 WordPress 核心主題各跑一次，確認 `!important` 移除後沒有防守破口。
4. **桌機視窗矩陣**：一般寬度、窄桌機視窗、200% zoom 與分割畫面；手機／平板不列入顯示驗收，另確認 PHP 端確實不輸出 widget。
5. **操作與輔助模式**：Tab 鍵依序操作 OK、Cancel、dock、輸入框與 gift picker；`prefers-reduced-motion` 下無非必要位移；forced-colors 下不以 `forced-color-adjust: none` 整體封鎖使用者配色。
6. **相容契約**：第一輪不改既有 public ID/class。外掛的 `no_style`／`custom_style_link` 使用者可能依賴這些 selector；疑似死碼只有在 source、production bundle 與執行時 DOM 三方皆無引用時才刪除。

**新增 lint**（`tools/node/package.json`）：

先把固定版本的 `stylelint` 與必要 config 寫入 `tools/node/devDependencies` 和 lockfile，再使用本地 binary：

```json
"lint:css": "stylelint ../../css/mpu_style.css"
```

裸 `npx stylelint` 可能在乾淨環境臨時下載不同最新版，不適合作為可重現的 `verify` 守門。

掛進 `verify` 鏈。規則只開三條，目的是防回歸不是追求風格統一：

- `declaration-no-important`（保留項以就地 `stylelint-disable-next-line` 加理由，不建立看不出用途的全域 allowlist）
- `custom-property-pattern: "^mpu-internal-[a-z0-9]+(-[a-z0-9]+)*$"`（**2026-09-02 收緊**：原訂 `"^mpu-"`，但 §11.1 定案「所有 token 一律 `--mpu-internal-*`」，`^mpu-` 會放行 `--mpu-color-ink` 這種讀起來像公開契約的名字。v2.30.0 交付時此規則漏設，config 只繼承 `stylelint-config-standard` 的 kebab-case 預設，等於未生效——已補）
- 重複 selector／無效值等低爭議 correctness 規則

不建議第一天就開全域 `color-no-hex`：token 定義本身必須容許顏色字面值，否則會逼出無意義的 disable。元件宣告禁止硬寫顏色可在 token 化穩定後，再用精準規則或 review 守門。

---

## 9. CODEX 檢討爭點與收斂結果

> ⚠️ **本節全部內容由 §11 覆蓋，僅供追溯決策脈絡，不得作為實作指示。** 凡本節與 §11 衝突之處，一律以 §11 為準——特別是 token 公開性（§11.1）、邏輯屬性（§11.2）與 Q-4／B-4 的 glyph 裁決（§11.2 已移出本輪）。

本節保留初稿（CLAUDE）提出的四個爭點原文，後接 CODEX 第一輪答覆、CLAUDE 第二輪回應與最終裁決，讓後續 reviewer 看見完整決策脈絡。**四個爭點在當時均已收斂**（其後由 §11 重新裁定）。

> 以下 Q-1～Q-4 為初稿當時的提問原文，僅供追溯，不代表現行立場；現行結論見其後兩節。

**Q-1 `@layer` 到底用不用。**（初稿提問）
我方立場明確：不用（§2）。若 CODEX 主張採用，請具體說明如何處理「未分層主題規則勝過分層外掛規則」這個串接事實——特別是在 `!important` 已被移除的前提下。

**Q-2 `!important` 的替代機制。**（初稿提問）
容器級 `all: revert-layer` 與刻意重複 ID 是初稿提出的兩條路：前者一次到位但作用範圍難以預期（`all` 很暴力，可能吃掉 UA 的 `input` 預設行為），後者精準但選擇器醜。初稿提議兩者混用。

**Q-3 相容性底線訂在哪。**（初稿提問）
提議 Baseline 2023（§6）。這是公開發布的外掛，底線訂太前面會有真實使用者受影響；但訂太保守又等於放棄本計畫一半的價值。若 CODEX 有 WP 生態的瀏覽器分佈數據，請提出。

**Q-4（次要）B-4 的 `mpu-thinking-dots` 意圖為何。**（初稿提問）
現況是三個 keyframe 的 `content` 值相同，只有 opacity 在動。這是刻意（呼吸感）還是遺留 bug（原本想做跳點）？決定後才知道該修還是該刪。原始意圖可能只有作者知道。

### CODEX 第一輪答覆與收斂結果

- **Q-1：同意不用 `@layer`。** 外掛無法要求所有宿主主題先宣告 layer 順序；核心樣式維持 unlayered 是較可靠的預設。
- **Q-2：不同意把大範圍 `all: revert-layer` 或重複 ID 當主要替代。** 兩者都可能把目前可見的 `!important` 債，轉成更難診斷的 UA 行為或 selector 權重債。採用 §4.2 的元件級、逐屬性策略；有證據的第三方防禦 `!important` 可以保留。
- **Q-3：不把 Baseline 2023 當單一開關。** 核心 UI 使用成熟語法；`color-mix()`、`text-wrap: pretty` 等依失敗影響提供 fallback 或作漸進增強。這比假設所有 WordPress 前台訪客都符合一個年份標籤更穩健。
- **Q-4：依目前規則判斷，實際視覺就是單一省略號的 opacity 呼吸。** 建議保留這個低干擾意圖，把固定 glyph 放在 `.mpu-thinking::after`，keyframes 只控制 opacity，並把動畫改名為 `mpu-thinking-pulse`；不要用離散 `content` 動畫製作跳點，除非作者明確希望改變視覺。

### CLAUDE 第二輪回應（2026-09-01）

**已核實的 CODEX 事實主張**（全數成立，逐項實查）：

| 主張 | 查證結果 |
|---|---|
| `wp_is_mobile()` 於伺服器端停止輸出 | ✅ `includes/core/frontend-functions.php:169`，位於 `mpu_is_show_page()` 的早期 return 條件 |
| 主題端 768px 隱藏只是第二層防護 | ✅ `moelog-20th/style.css:2368-2371`，`#mp_ukagaka { display: none !important }` |
| 深色補丁針對 `moe-aiqna-block` 而非 MPU | ✅ `moelog-20th/style.css:2656` 起，選擇器全為 `.moe-aiqna-block`。**初稿此處為過度延伸，TL;DR 與 §4.1 均已更正** |
| B-1 死碼範圍應擴大 | ✅ `mpu-chat-message` 在 source / `js/dist/` / PHP 全數零引用，`.user`、`.assistant`、`.mpu-msg-role`、`mpu-msg-fade-in` 為同一組孤兒 |
| `no_style` / `custom_style_link` 構成相容性約束 | ✅ `includes/core/core-functions.php:28-29`、`frontend-functions.php:334-350`、`options/options_general.php:165-170` |

**接受的否決**：`all: revert-layer` 的否決理由第 2 點（`all` 不重置 custom properties、卻重置消費它們的普通屬性，形成「token 還在、元件行為回 UA」的難查狀態）是初稿未考慮到的失敗模式，否決成立。

**C2-1（理由替換，決定不變）— 不採原生巢狀的理由應改為 grep-ability。**
現行理由「無轉譯時故障範圍大」偏弱：不支援巢狀的是 2023 年前的瀏覽器，風險有界且拍一張基準圖即可證偽。真正撐得住的理由是**可搜尋性**——本檔無建置、無測試，且是 CLAUDE / CODEX / Gemini 三方交叉 review 的對象；扁平寫法讓 `grep "#ukagaka-dock ul li a"` 能命中完整選擇器，巢狀則不能。建議 §3 與 §6 的理由欄改寫為此，決定維持不變。

**C2-2（部分回推）— 「顯著降低」不可驗收，建議改為可驗收的規則。**
同意撤除「81 → 10 以下」這類會誘導錯誤重構的數字 KPI。但本計畫本就沒有自動化測試，再配一個不可證偽的目標等於沒有驗收條件。**折衷提案：不設數字，改設規則**——每個保留下來的 `!important` 必須在 §4.2 的保留表佔一列，寫明「防禦哪一類宿主規則」與「由哪個主題交叉測試覆蓋」。表格長度即自然形成的 KPI，且每一列都帶理由。無法填滿該列的 `!important` 即應移除。

**C2-3（新發現的設計缺陷）— 內部衍生色在 `color-mix()` 不支援時不隨公開 token 連動。**
§4.1 現行寫法為靜態 fallback + `@supports` 漸進增強：

```css
--mpu-internal-ink-30: rgb(30 58 138 / 30%);                    /* 硬寫，不連動 */
@supports (color: color-mix(in srgb, red, transparent)) {
  #mp_ukagaka { --mpu-internal-ink-30: color-mix(in srgb, var(--mpu-color-ink) 30%, transparent); }
}
```

宿主主題覆寫 `--mpu-color-ink` 後，**不支援 `color-mix()` 的瀏覽器上所有衍生色不會跟隨**——文字換成淺色但邊框與背景仍是深藍 `#1e3a8a`，對比度反而比未覆寫時更差。這正是 CODEX 自己主張的「不能用日期標籤取代失敗模式分析」所應攔截的情況。

實作上不必然要改（`color-mix()` 2023-05 已全綠，受影響族群小），但**必須寫進 §4.1 的 API 契約與 `docs-en/DEVELOPER_GUIDE.md`**：衍生色與公開 token 的連動性依賴 `color-mix()` 支援度，主題覆寫在極舊瀏覽器上只會部分生效。若不接受此限制，替代方案是把需要連動的衍生色升為公開 token（由主題各自覆寫），代價是公開表面從 8 個擴大到約 13 個。

**C2-4（程序）— Q-4 應標回「待作者確認」。**
CODEX 的技術結論（保留單一省略號、keyframes 只控 opacity、改名 `mpu-thinking-pulse`）我同意，但「原始意圖不明」不應由 reviewer 以推斷結案。這是全文唯一需要作者本人拍板的項目，因此第二輪當時維持未收斂。

### CODEX 第二輪回應與作者最終裁決（2026-09-01）

- **C2-1：接受。** 不採巢狀的主要理由已改為完整 selector 的 grep-ability；相容性只保留為次要考量。
- **C2-2：接受。** `!important` 不設數字 KPI，改由 §4.2 的「宿主規則類型 + 交叉測試」證據表逐項核准；無法列入者一律移除。
- **C2-3：選擇擴大公開 palette，而非接受部分失效。** 五個對比度相關的衍生色提升為語意 token，公開表面由 8 個增為 13 個。現代瀏覽器可自動推導；需照顧不支援 `color-mix()` 的宿主主題，必須完整覆寫 13 個 token。
- **C2-4／Q-4 作者裁決：同意 CODEX 方案。** 正式保留單一省略號的 opacity 呼吸；固定 glyph 移至基礎規則，keyframes 不再改 `content`，並更名為 `mpu-thinking-pulse`。

**未決項彙整：無。** 下一步是 Phase -1 建立基準資料，而不是直接重寫 CSS。

---

## 10. 工時估算

| Phase | 內容 | 估算 |
|---|---|---|
| P-1 | 視覺基準（含等待 `mpuVisualReady`）、DOM inventory、主題矩陣、canvas-only 路徑 | 半天～一天（待 §11.4 前置補齊後重估） |
| B-* | §5 的**十**項實際問題（新增 B-10；B-4／B-5 已移出） | 1 小時 |
| P0-A | 內部 custom properties（**不含任何文件化**，見 §4.1 與 §11.1） | 2～3 小時 |
| P0-B | `!important` 消除 + 主題交叉測試（**分批，每組跑一次**） | 一天（測試佔一半） |
| P1-A | 扁平元件區塊整理 + `:is()` 收攏 | 半天 |
| P1-B | ~~邏輯屬性（限文本流）+~~ 現代顏色語法 | 1 小時（邏輯屬性半段依 §11.2 刪除） |
| P2 | `overscroll-behavior` + `:focus-visible` + `reduced-motion`（`text-wrap`／`dvh` 已移出） | 1 小時 |
| — | stylelint 導入 | 半天 |

> **§11.6 順序更正**：原表把 `B-*` 排在 `P2` 之後，但 §4 P2 自己寫明「須先做 B-1 再做本項，否則會替死碼補保護」。上表已將 `B-*` 提前到 P0-A 之前——先刪死碼再 token 化，也避免替死碼建立 token。

**合計約 3 個工作天**（API 文件化暫緩後由 3～3.5 天下修），其中視覺驗證佔比最高。建議 P-1 基準資料獨立保存，P0-A 與 P0-B 各自獨立 commit，方便回退。

---

## 11. 家庭端整合評審（2026-09-01）

> **評審者**：家 CODEX + 家 CLAUDE，各自獨立評審後合併（原計畫由公司端 CLAUDE 與 CODEX 撰寫）。
> **新增約束**：作者指定「**基本不改動原外掛 ghost 的 layout，只改良 CSS，讓代碼更現代化更易於維護**」。本節所有判定以此為準。
> **方法**：所有事實主張均對照 v2.29.0 工作樹原始碼實查，證據行號列於各項。
> **結論**：方向正確，但**不建議依現稿直接開工**。§1 現況盤點（703 行、81 個 `!important`、0 個 custom property）實測全部正確；問題在於計畫內有互相矛盾的決策，以及部分項目會實際改動 ghost layout。

### 11.0 待處理清單（H-1～H-14）

| ID | 問題 | 類型 | 提出 | 處置 |
|---|---|---|---|---|
| H-1 | Token 公開性全文五處互相矛盾 | 決策矛盾 | 雙方一致 | §11.1 定案：僅內部去重 |
| H-2 | dock `padding-left` → `padding-inline-start` | **Layout（RTL 潛伏）** | 雙方一致 | §11.2 移出 |
| H-3 | `text-align: right` → `end` | **Layout（RTL 潛伏）** | 雙方一致 | §11.2 移出 |
| H-4 | B-4 glyph `...` → `…` | **Layout（立即）** | 雙方一致 | §11.2 移出 |
| H-5 | `dvh`／safe-area／`text-wrap: pretty` | **Layout（立即）** | CODEX | §11.2 移出 |
| H-6 | B-5 badge 字型 `sans-serif` → `serif` | **Layout（立即）** | CLAUDE | §11.2 移出 |
| H-7 | 捲軸兩組值不同卻要合併 | **Layout（立即）** | CLAUDE | §11.3 修法 |
| H-8 | P1 dock 範例漏掉 `float: right` | **Layout（立即）** | CLAUDE | §11.3 已補回 |
| H-9 | reduced-motion 論證的事實前提錯誤 | 事實錯誤 | CODEX | §11.3 已更正 |
| H-10 | B-9 z-index 契約找錯位置 | 事實錯誤 | CLAUDE | §11.3 已更正 |
| H-11 | `.playwright-mcp/` 不存在，Phase -1 不可執行 | 事實錯誤 | CODEX | §11.4 補前置 |
| H-12 | 13 個 token 不足以完整接管 palette | 設計缺陷 | CODEX + CLAUDE | §11.1 併入定案 |
| H-13 | `.nextmsg` 是死碼 | 新發現 | CLAUDE | §11.5 新增 B-10 |
| H-14 | 跨 ghost 驗收不足 + 缺 selector scope 策略 | 驗收缺口 | CODEX | §11.5 修訂 |

> **§11.9 追加（CODEX 第二輪，2026-09-01）**：另有 5 項殘留指示與驗收定義未收乾淨，含一項家 CLAUDE 的實測錯誤。見 §11.9。

---

### 11.1 Token 公開性：全文矛盾，定案為「僅內部去重」（H-1、H-12）

同一份計畫在五處給出互斥立場：

| 位置 | 原文立場 |
|---|---|
| 檔頭 | 公開 API 契約**暫緩發布** |
| §3 決定事項總表 | **視為公開 API** |
| §4.1 驗收 | 要**寫進 `DEVELOPER_GUIDE.md`** |
| §4.1 公開契約決定 | **已決定公開 13 個** |
| §10 工時 | 又說**不含 API 文件化** |

**定案（雙方一致）**：

> **Token 僅作內部去重，不發布 theming API、不改文件；全部視為可自由重命名的內部實作。**

三個理由：

1. 深色模式不在路線圖（作者 2026-09-01 確認），該契約**沒有已知消費者**，現在公開只會過早形成相容性負擔。
2. **13 個 token 根本不足以完整接管 palette**（H-12）。家 CODEX 盤點出未涵蓋的活躍顏色：gift picker hover surface、picker surface、picker shadow、placeholder、stream badge 的 success／warning／error／timeout 狀態色。家 CLAUDE 另查出三個仍活著的 alpha 值（`:476` 的 `.25`、`:479` 的 `.22`、`:692` 的 `.85`）也沒歸屬。所以「完整換色只需覆寫 13 個」不成立——公開它等於給出做不到的承諾。
3. 依 §4.1 自己的規則「沒有文件的變數不算 API」，不寫文件即不形成承諾，日後改名不算破壞性變更。

**實作規則**：所有 token 一律使用 `--mpu-internal-*` 前綴（含原本規劃公開的 8 個）；`box-shadow` 與 badge 狀態色明確保留字面值，不強行 token 化。

> **CODEX 原列的證據需剔除一項**：user／assistant message 背景（`:614`、`:621`）屬 B-1 死碼，會被整組刪除，不能當作「13 個不足」的證據。其餘成立。

---

### 11.2 會改動 layout 的項目，一律移出本輪（H-2～H-6）

以下**不是等價重構**，在「不改動 ghost layout」的約束下必須移出：

| 項目 | 為何是視覺變更 | 證據 |
|---|---|---|
| dock `ul` 的 `padding-left: 64px` → `padding-inline-start` | 那 64px 是把三顆按鈕對齊**固定方向的點陣圖** `menu.png`（`background: no-repeat center`）。RTL 下 padding 會翻、圖不會翻 | `css/mpu_style.css:330`、`:322` |
| `text-align: right` → `end` | 全檔只有 `:107`（`.mpu-think-bubble`）與 `:618`（B-1 死碼）兩處，**不在 `#ukagaka_msg`**（原表誤植）。而氣泡尾巴 `::after` 釘死在物理 `right: 40px`，換成 `end` 正是 §4.4 自己警告的「CSS 翻了、對位沒翻」 | `css/mpu_style.css:107`、`:130` |
| `dvh` + safe-area | 會改變 shell／message box 的尺寸與位置 | §4 P2 表 |
| `text-wrap: pretty` | 會改變換行結果與對話框高度 | §4 P2 表 |
| B-4 glyph `...` → `…` | 不套用離散 `content` 動畫的瀏覽器**現在顯示的是 `...`**；改後變 `…`。這是主動的字元選擇變更，不是 no-op | `:650` = `2E 2E 2E`；`:657/661` = `E2 80 A6` |
| B-5 badge 字型 `sans-serif` → `serif` | 在缺 Noto Sans TC/JP 的機器上是實質渲染變更。而 `.mpu-state-badge` 只是 v2.18 的 runtime debug 工具，不一致無害 | `css/mpu_style.css:680` |

**連帶結果：§4.4「邏輯屬性」整節作廢。** 扣掉 H-2、H-3 後，✅ 欄只剩 `#ukagaka_msg { padding: 10px 15px }` **一條規則**，不值得開一個 Phase。改為**全檔一律維持物理屬性**，只在檔頭寫一行註解說明與 `js/ukagaka-emoji.js:138-139`／`:172-173` 的座標耦合。這同時消掉一整類 RTL 風險，對「不改 layout」是淨賺。

**保留在本輪的 P2 項目**（不改靜態 layout）：`overscroll-behavior: contain`、`:focus-visible`、`prefers-reduced-motion`。

**另列「可選視覺改善」另案**：`dvh`／safe-area、`text-wrap: pretty`、glyph 更換、badge 字型統一。

---

### 11.3 事實錯誤與自相矛盾的更正（H-7～H-10）

#### H-7 捲軸合併會造成視覺回歸

§4.1 只定義單一 `--mpu-internal-scrollbar-size: 6px`，§4 P1 又說「兩組合併為 1 組」。實測兩組值不同：

| | 一般態 | chat-mode |
|---|---|---|
| `width` | 6px（`:245`） | **8px**（`:284`） |
| `border-radius` | 3px | **4px** |

Firefox 的 `scrollbar-color` 兩邊相同可共用；webkit 那組不行。**修法**：另加 `--mpu-internal-scrollbar-size-chat: 8px`，或在 `.chat-mode` 內覆寫同一 token；radius 同理。

#### H-8 §4 P1 的 dock 範例砍掉了 §5 說「不可刪」的 float

§5「已排除的誤判」明寫這個 `float: right` 有效且必要，但 §4 P1 示範碼裡沒有它。查 markup（`includes/core/frontend-functions.php:296`）：dock 在 `#ukagaka_shell` 內、`.mpu-clear` 之後，而 shell 是 shrink-to-fit 的 fixed 元素——移除 float 後 dock 會靠左。**已於 §4 P1 範例補回。**

> 同段砍掉的 `padding: 0 0 30px 0` + `box-sizing: border-box` 經核算**幾何等價**（`padding-top` 為 0，背景定位區在兩種寫法下都是 250×45），可以砍。

#### H-9 reduced-motion 的論證前提錯誤

計畫稱停掉 `.mpu-thinking` 動畫後「思考中與閒置態會長得完全一樣」。查 `js/ukagaka-base.js:612-616`，該 span **只在 `showSpinner` 為真時建立**，且 `::after` 的 `content` 在基礎規則上。停掉動畫得到的是**靜態省略號**，與閒置態（氣泡整個 hidden）差異明顯。改採 `animation: none; opacity: 1`。**已於 §4 P2 就地更正。**

#### H-10 B-9 找錯了契約的另一端

計畫說 `z-index: 99` 對應「JS 端硬寫的 z-100」。全 repo grep：**JS 與 PHP 裡沒有任何 z-index 100**。實際來源是 **ghost 資料** `ghost/Frieren/decorations.json`（`:22` = 100、`:56` = 101、`:39/74/91/108` = 5/8/6/7），由 `frieren-bundle.js:2044` 渲染成 inline style。99 是刻意劃在 ghost 作者資料中間的分界線，任何第三方 ghost 都能自填。契約該寫進 `docs-en/GHOST_CREATE_GUIDE.md`，**不是 JS 註解**——這正好與 §11.6「JS／文件全部另案」相容。

> **JSON 註解的寫法（CODEX 補正）**：標準 JSON 不支援 `//` 或 `/* */`，不可直接加。本專案已有既成慣例——`ghost/Frieren/decorations.json:2` 的頂層 `"_comment"` 欄位；loader `includes/personality/personality-decorations.php:35-38` 會載入並回傳完整 JSON，後續消費者只讀 `$decorations['items']`（`:52-60`、`:75-84`），因此容忍未知頂層鍵。若要在資料檔就地記錄 z-index 契約，**用 `_comment`／`_meta` 欄位，不要加註解語法**。

#### 附帶更正

- **`!important` 計數：原稿的 57 是對的，家 CLAUDE 第一輪的「55」為誤測**（範圍誤截在 `:396`，漏掉 `.change` 區塊的 `:397` `background-repeat` 與 `:398` `background-position`）。dock 正確範圍是 `:315-399`，實測分區為 container 10 + ul 10 + li 9 + a 16 + gotop/hide/change 各 4 = **57**。非 dock：msgbox link/img 8（`:205-218`）+ gift picker 10（`:437-610`）+ 其他 6 = 24。57 + 24 = 81 ✓。**§1.2 與 TL;DR 的數字已恢復原值。**
- **`color-mix()` 是精確等價，不是近似**：`color-mix(in srgb, #1e3a8a 30%, transparent)` 依 CSS Color 5 的 premultiplied 插值，結果精確等於 `rgba(30,58,138,0.3)`；八個 alpha 階（.08/.22/.25/.3/.5/.55/.7/.85）換算百分比全為整數無誤差。**這是 P0-A 能宣稱零視覺差異的支點**，原文沒明講，應補進 §4.1。

---

### 11.4 Phase -1 目前不可執行（H-11）

§8 宣稱「專案已有 `.playwright-mcp/`」——實查不存在（`ls -d .playwright-mcp` 回 No such file or directory），repo 內也沒有任何 CSS 視覺測試腳本。**在補齊以下六項之前，Phase -1 只是一個方向，不是可交付的 Phase**：

1. 測試站 URL 與使用哪個 WordPress 安裝
2. 如何切換 ghost、主題、chat mode、think bubble、gift picker
3. 固定 viewport、DPR、zoom、字型與動畫狀態
4. 截圖保存位置，以及是否納入 Git
5. 哪些差異允許、哪些必須像素一致
6. **（家 CLAUDE 補充）等待 `mpuVisualReady`**——`#ukagaka_img`（`:25`）與 `#ukagaka_msgbox`（`:186`）初始都是 `visibility: hidden`，要等 JS 定位完成才顯示。截圖腳本若不等該事件，基準圖會拍到空白或位移中的畫面

---

### 11.5 跨 ghost 驗收與 selector scope（H-13、H-14）

#### 驗收不能只用 Frieren——但原建議的矩陣不可執行

家 CODEX 指出 `mpu_style.css` 為所有 ghost 共用，要求加測 Asuna、Sakura_Laurel。**該矩陣目前無法執行**：`.gitignore:74-75` 已將 `Asuna/`、`Sakura_Laurel/` 排除，`git ls-files ghost/` 只有 Frieren，家用機上也不存在。（`CLAUDE.md` 稱它們是 additional bundled characters 屬過時敘述。）

**但底層擔憂成立，且有更強的證據**：`frieren-decoration`／`frieren-emoji`／`frieren_idle_apng` 這三個 class **由核心 `js/ukagaka-emoji.js` 產生**，不是 Frieren 自己 bundle 的專有物——它們是掛著 Frieren 品牌前綴的**跨 ghost 通用機制名**。因此：

- `mpu_style.css` 確實跨 ghost，驗收不能只覆蓋 Frieren
- **這三個名稱不可改名**（會炸掉所有第三方 ghost），須列入 selector inventory 的「禁止變更」欄
- **可執行的替代驗收**：關掉裝飾與 APNG、跑一次 **canvas-only 路徑**（模擬無裝飾 ghost），而非要求切換不存在的 ghost

#### selector scope：本輪只刪死碼，不做 root scope 化

家 CODEX 主張建立 selector inventory、把無 `#mp_ukagaka` 邊界的 selector 收進來。方向對，但實際盤點後結論不同：

| Selector | 處置 |
|---|---|
| `.nextmsg` `:219` | **刪除**（H-13，死碼，新增為 B-10） |
| `.ukagaka-msgbox-top` `:164`／`.ukagaka-msgbox-border` `:189` | 名稱夠獨特，**維持原狀** |
| `.mpu-think-bubble` 系列 | 元素是 `#ukagaka_think`，**維持原狀** |
| `.mpu-gift-picker-*`、`.mpu-thinking`、`.mpu-state-badge` | `mpu-` 前綴，**維持原狀** |
| `#ukagaka_*`／`#mpu_user_input` | ID 已足夠 |

**H-13：`.nextmsg` 是死碼。** 全 repo grep `nextmsg` 命中的全部是 `mpu_nextmsg` 函式名與 `mpu_nextmsg_llm` requestId，**沒有任何 class 引用**；markup 用的是 `<a id="mpu_ok_btn"><img …></a>`（`frontend-functions.php:277-281`）。刪掉即可，順帶化解 CODEX 點名的 `:219` scope 問題。

**為何不做 root scope 化**：加 `#mp_ukagaka ` 前綴會把特異性從 (0,1,0) 抬到 (1,1,0)，與 `custom_style_link`（`core-functions.php:29`、`frontend-functions.php:345-350`）使用者現有的覆寫相衝。**本輪 inventory 只當文件成果產出，不當改動成果。**

---

### 11.6 正式範圍與執行順序

原稿檔頭的影響範圍列入 PHP inline-style 清理，但 §7 Non-goals 又明確排除；B-9 還要求改 JS 註解。**正式範圍收緊為**：

```
[ 本輪範圍 ]
  css/mpu_style.css
  tools/node/package.json + package-lock.json
  新增 stylelint config
  Phase -1 截圖腳本（建議置於 tools/visual/）

[ 一律另案 ]
  includes/core/frontend-functions.php（inline style 清理）
  JS 註解（B-9）→ 改寫進 GHOST_CREATE_GUIDE
  docs-en/*（因為不發布 API）
  glyph 更換（B-4）、badge 字型（B-5）、preload hack
```

**順序矛盾已修正**：原 §10 工時表把 `B-*` 排在 `P2` 之後，但 §4 P2 自己寫明「須先做 B-1 再做本項，否則會替死碼補保護」。已將 `B-*` 提前至 P0-A 之前——先刪死碼，再 token 化，避免替死碼建立 token。

---

### 11.7 合併後的執行順序與驗收

| 步 | 內容 | 允許的視覺差異 |
|---|---|---|
| 1 | 建立**可重跑**的視覺基準（含等待 `mpuVisualReady`）與 DOM inventory；含 canvas-only 路徑 | — |
| 2 | 刪死碼（B-1 + **B-10 `.nextmsg`**）、合併重複（B-2）、修等價無效宣告（B-3/6/7/8） | 零 |
| 3 | 內部 token 化——**只替換相同值，不調整任何顏色**；捲軸拆兩個 size；`box-shadow` 與 badge 保留字面值 | 零 |
| 4 | 按 dock → message box → chat → gift picker **分批**整理 selector（dock 記得保留 `float: right`） | 零 |
| 5 | **每移除一組 `!important` 就跑一次主題交叉測試，不一次全刪** | 零 |
| 6 | `overscroll-behavior` + `:focus-visible` + `reduced-motion`（用 `animation: none; opacity: 1`） | 非 layout |
| 7 | stylelint 導入 | — |

**驗收標準收緊**：§8 原寫「差異須能逐條解釋」，這允許「解釋得出來的視覺變更」通過，與本輪約束不符。但單一的「pixel-identical + 白名單為空」與第 6 步互相矛盾——`:focus-visible` 必然讓 Tab 狀態多出 outline，`reduced-motion` 會改變思考省略號／氣泡／gift picker 在特定時刻的像素，dock 的 `:focus` → `:focus-visible` 也會改變滑鼠點擊後的狀態。**因此分成兩道門：**

| 門 | 適用步驟 | 標準 |
|---|---|---|
| **門 A：等價重構** | 步驟 2～5 | 同一瀏覽器、同一狀態下 **pixel-identical，白名單為空** |
| **門 B：無障礙增強** | 步驟 6 | 允許**已列明的**無障礙狀態差異（`:focus-visible` outline、reduced-motion 下的靜止態）；但**一般靜態 layout 的 bounding box 必須不變**——以 `getBoundingClientRect()` 對關鍵元素逐一斷言，而非只看截圖 |

**「pixel-identical」與「設 diff 閾值」語意不同，必須各自寫死參數**（否則 canvas／APNG 根本無法重複產生相同截圖）：

| 參數 | 需明定 |
|---|---|
| 像素色差容許值 | 每通道最大差值（建議 0，門 B 可放寬） |
| 最大差異像素數／比例 | 絕對值與百分比皆須設定 |
| 遮罩區域 | canvas（`#cur_ukagaka`）、APNG（`#frieren_idle_apng`）、游標、`.frieren-emoji` 與所有動畫區域一律遮罩 |
| 動畫凍結 | 角色動畫時間如何凍結（停在固定 frame，或以 `animation-play-state: paused` + 固定 seed 注入），須寫成腳本的一部分 |

門 A 的目標是「遮罩後的靜態區域 0 差異」；未遮罩區域不進入比對，改由 bounding box 斷言覆蓋。

**主題交叉測試矩陣（家 CLAUDE 補充）**：移除 `#mp_ukagaka .ukagaka-msgbox-border a` 那 8 個 `!important`（`:206-216`）是全案風險最高的動作——它防的是宿主主題對 OK／Cancel 連結的 `outline`／`border`／`text-decoration`。原計畫的樣本（`moelog-20th` + 一個核心主題）偏薄，建議加測 Twenty Twenty-One（對 `a` 有重樣式）與 Twenty Twenty-Four；或直接把這 8 條連同證據列一起保留——它們本來就填得出 §4.2 證據表要求的「宿主規則類型」欄。

---

### 11.8 兩位評審的分歧與收斂

雙方唯一實質分歧在 **B-9 的後續處置**：家 CLAUDE 原建議把 z-index 契約寫進 `docs-en/GHOST_CREATE_GUIDE.md`；家 CODEX 主張「JS 與文件變更全部另案」。

**採 CODEX 的界線。** 理由是家 CLAUDE 自己查出的結論正好支持它——原計畫指向 JS 是找錯地方，而正確的位置在 **ghost 資料層**，那本來就該獨立成一個「ghost 作者文件」的 issue，不該綁在 CSS 重構裡。本輪 B-9 只做 CSS 端的 `--mpu-internal-z-canvas` 變數化。

其餘全部收斂：H-1～H-5、H-12 為雙方獨立得出的相同結論（信心最高）；H-9、H-11、H-14 由 CODEX 提出並經 CLAUDE 實查確認；H-6～H-8、H-10、H-13 由 CLAUDE 提出。

---

### 11.9 CODEX 第二輪收尾（2026-09-01）

家 CODEX 對 §11 修訂稿再審，指出 5 項殘留。**全部已修，計畫可進入 Phase -1。**

| # | 殘留 | 修正 |
|---|---|---|
| R-1 | **dock `!important` 應是 57，不是 55**——家 CLAUDE 的實測錯誤 | 已撤回，§1.2 與 TL;DR 恢復 57 |
| R-2 | Token 舊指示仍有**操作性**殘留（標題、範例註解、`--mpu-color-*` 命名、第 14 個 token 討論） | §4.1 範例整段改寫為 `--mpu-internal-*`，舊方案刪節保留 |
| R-3 | §3 決定表仍寫「文本流採邏輯屬性」；§6 仍判 `text-wrap: pretty` 為「可直接寫」；§9 缺覆蓋聲明 | 三處均已標註 |
| R-4 | Pixel-identical 與第 6 步互相矛盾；且「pixel-identical」與「設 diff 閾值」語意不同 | §11.7 改為**門 A／門 B 兩道驗收**，並列出四項必須寫死的比對參數 |
| R-5 | **標準 JSON 不支援註解**，B-9 不能寫「加 `decorations.json` 註解」 | 改用既有 `_comment` 欄位慣例 |

#### R-1 的錯誤原因（記錄以免重犯）

家 CLAUDE 用 `awk 'NR>=316 && NR<=396'` 統計，**範圍兩端都錯**：dock 區塊實際是 `:315-399`，尾端漏掉 `.change` 群組的 `:397` `background-repeat` 與 `:398` `background-position`。重測分區：

```
container (315-326)   10
ul        (328-339)   10
li        (341-351)    9
a         (353-371)   16
gotop     (374-381)    4
hide      (383-390)    4
change    (392-399)    4
─────────────────────────
dock 小計             57      ← 原稿數字正確

msgbox link/img (205-218)   8
gift picker     (437-610)  10
其他                        6
─────────────────────────
總計                       81  ✓
```

原稿 §1.2 的 `16 + 10 + 10 + 9 + 12` 本身也等於 57，內部一致。**§1.2 的小計數字予以保留**（原建議「乾脆移除」不採，因為它是正確的，且 §4.2 的證據表需要一個起始基準）。

#### R-5 的既有慣例

不要在 JSON 加 `//` 或 `/* */`。本專案已有現成做法：

```json
{
  "_comment": "Frieren Personality - Decoration Click Prompts",
  "_format_version": "1.2",
  ...
}
```

`ghost/Frieren/decorations.json:2` 已在用。Loader `includes/personality/personality-decorations.php:35-38` 會載入並回傳完整 JSON；後續消費者只讀 `$decorations['items']`（`:52-60`、`:75-84`），因此容忍未知頂層鍵。z-index 契約若要就地記錄，用 `_comment`／`_meta` 欄位即可——但主要位置仍是 `docs-en/GHOST_CREATE_GUIDE.md`，且屬另案。

#### 家 CODEX 覆核通過的項目

`.nextmsg` 死碼（B-10）、reduced-motion 更正、捲軸 6px/3px vs 8px/4px 差異、z-index 另一端在 ghost decoration 資料而非 JS 硬編碼、Asuna／Sakura_Laurel 不在工作樹因而改用 canvas-only 路徑——**五項均經雙方獨立確認成立。**

**狀態：計畫已可進入 Phase -1**，前提是先補齊 §11.4 的六項截圖前置條件。剩餘問題已非架構方向，而是 Phase -1 腳本本身的工程細節。
