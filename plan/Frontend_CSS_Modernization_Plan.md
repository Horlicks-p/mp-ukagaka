# 前端 CSS 現代化整理計畫（`css/mpu_style.css`）

> 📅 初稿：2026-09-01
> 📋 目標：把 703 行、81 個 `!important`、零自訂屬性的 `mpu_style.css`，整理成可維護、可被主題安全接管的現代 CSS
> 🧭 參照對象：`C:\D\php\moelog-20th\style.css`（v2.8.4，2693 行，同作者的 WordPress 主題）
> 🎯 想定讀者：實作擔當 + 交叉 review（家 CODEX / Gemini）
> 🔖 對象版本基點：v2.29.0
> 📂 影響範圍：`css/mpu_style.css`（主體）、`includes/core/frontend-functions.php`（inline style 清理，Phase 3）、`tools/node/package.json`（新增 lint）
> 🧩 CODEX 檢討整合：2026-09-01（外掛 cascade 邊界、漸進相容、無障礙與驗收順序）
> 🧩 CLAUDE 第二輪：2026-09-01（核實 CODEX 事實主張、修正四處合併殘留、提出 C2-1～C2-4，見 §9）
> 🧩 CODEX 第二輪／作者裁決：2026-09-01（接受 C2-1～C2-3；作者拍板 Q-4 保留省略號呼吸效果）
> 🧩 作者確認：2026-09-01（**深色模式不在路線圖上**；token 化改以內部去重為目的，公開 API 契約暫緩發布）

---

## ⚠️ 狀態

**提案階段，尚未動工。** CODEX 兩輪檢討、CLAUDE 第二輪回應與作者裁決皆已整合；方向已確認，下一步依計畫先做 Phase -1 基準資料，再進入實作。

**方向未決項已清空。** C2-1～C2-3 均已收斂；Q-4 經作者裁決採用「單一省略號 + opacity 呼吸」方案。實作前只需完成 Phase -1 基準資料，不再等待設計方向確認。

**實作階段待拍板（非方向問題，可在對應 Phase 內決定）**：§4.1 的捲軸軌道歸屬與兩個灰色合併（P0-A）；§4 P2 的 `.mpu-thinking` 脈動幅度（P2）。B-4 的 glyph 變更已確認為預期差異，見 §5 註記。

---

## 0. TL;DR

三個結論，其餘都是細節：

1. **不要引入 `@layer`。** 主題該用，外掛不該用——理由見 §2，這是本計畫唯一「參照對象有、我方刻意不採用」的特性。
2. **最高價值的一步是自訂屬性化**，不是語法現代化——把散落 25 處的顏色字面值收斂成單一來源。token 設計照語意化與可對外的規格做，但**是否發布為公開 theming API 暫緩**：深色模式不在路線圖上，該契約目前沒有已知消費者（見 §4.1 註記）。（初稿曾宣稱「可讓 `moelog-20th` 刪掉檔尾那段深色補丁」，該段實際針對 `moelog-ai-qna-links`，與 MPU 無關——見 §4.1 的 CODEX 更正。）
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
57  小計：dock 區塊（佔全檔 70%）

 8  #mp_ukagaka .ukagaka-msgbox-border a（含 a img）
 8  .mpu-gift-picker-{nav,item}（防主題的 button 樣式）
 8  其他零星（filter / APNG 尺寸 / suitcase opacity / chat-mode overflow）
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
| 自訂屬性 | **採用，且定義在 `#mp_ukagaka` 上並視為公開 API** | 讓主題用一行覆寫接管配色，取代猜選擇器的 hack。見 §4.1 |
| `!important` 整理 | **逐元件盤點；元件級 reset + 正常特異性，第三方防禦例外保留** | `all` 會重置可存取性與 UA 行為；重複 ID 則把權重債換成選擇器債。見 §4.2 |
| 原生巢狀 | **第一輪不採用；先用扁平規則 + `:is()` 收攏** | 完整 selector 可直接被 `rg`／CodeGraph 命中，較適合三方交叉 review；壓行數不是主要目標。見 §4.3 |
| 邏輯屬性 | **僅限文本流屬性，定位屬性維持物理值** | 角色是右下角視覺錨點，JS 硬寫 `style.left`；半套 RTL 會讓表情與角色分家。見 §4.4 |
| 現代顏色語法 | **採用 `rgb(r g b / x%)`，透明度階梯用 `color-mix()`** | 與主題一致；`rgb(from …)` 相對顏色語法暫緩（相容性）。見 §4.5 |
| 深色模式 | **不在路線圖上**（2026-09-01 作者確認），非僅本次不做 | 對話框底是點陣圖（`msgbox_bg.png` 等），純換色做不完整；且無此需求。連帶影響公開 API 是否發布，見 §4.1 註記與 §7 |
| 檔案拆分 | **不拆，維持單檔** | 無建置流程，拆檔會多出 HTTP 請求或逼引入 bundler |
| 相容性底線 | **核心樣式採成熟特性；2023+ 特性只能漸進增強或提供 fallback** | 公開外掛不能讓日期標籤取代失敗模式分析。見 §6 |
| Lint | **固定版本安裝 stylelint 並掛進 `npm run verify`** | 不用會臨時下載最新版的裸 `npx`；規則由寬到嚴逐步導入 |

---

## 4. 分階段實作

### Phase 0（P0）— 自訂屬性 + 對外 theming API

**這是投報率最高的一項。** 主要價值是內部去重（25 處顏色字面值 → 單一來源）；對外 theming 是設計上保留的可能性，但暫不發布為契約，理由見本節末註記。

在 `#mp_ukagaka` 上定義 token：

```css
#mp_ukagaka {
  /* ---- 對外公開：主題可覆寫 ---- */
  --mpu-color-ink: #1e3a8a;              /* 主文字色 */
  --mpu-color-link: #1d8ac3;             /* 連結／focus ring */
  --mpu-color-link-hover: #6d6d6d;
  --mpu-color-surface: rgb(255 255 255 / 92%);
  --mpu-color-muted: #6b7280;
  --mpu-font-family: "Noto Sans TC", "Noto Sans JP", serif;
  --mpu-radius-message: 12px;
  --mpu-duration-fast: 0.18s;

  /* ---- 對外公開：需完整接管 palette 的主題一併覆寫 ---- */
  --mpu-color-control-border: rgb(30 58 138 / 30%);
  --mpu-color-control-border-strong: rgb(30 58 138 / 55%);
  --mpu-color-focus-ring: rgb(29 138 195 / 20%);
  --mpu-color-scrollbar-thumb: rgb(30 58 138 / 50%);
  --mpu-color-scrollbar-thumb-hover: rgb(30 58 138 / 70%);

  /* ---- 層級 ---- */
  --mpu-internal-z-preload: -1;
  --mpu-internal-z-canvas: 99;      /* 必須 < JS 端裝飾物的 100 */
  --mpu-internal-z-emoji: 20;
  --mpu-internal-z-shell: 10000;
  --mpu-internal-z-msgbox: 10001;
  --mpu-internal-z-bubble: 10002;
  --mpu-internal-z-picker: 10030;

  /* ---- 捲軸 ---- */
  --mpu-internal-scrollbar-size: 6px;
  --mpu-internal-scrollbar-track: rgb(200 200 200 / 30%);
}

@supports (color: color-mix(in srgb, red, transparent)) {
  #mp_ukagaka {
    --mpu-color-control-border: color-mix(in srgb, var(--mpu-color-ink) 30%, transparent);
    --mpu-color-control-border-strong: color-mix(in srgb, var(--mpu-color-ink) 55%, transparent);
    --mpu-color-focus-ring: color-mix(in srgb, var(--mpu-color-link) 20%, transparent);
    --mpu-color-scrollbar-thumb: color-mix(in srgb, var(--mpu-color-ink) 50%, transparent);
    --mpu-color-scrollbar-thumb-hover: color-mix(in srgb, var(--mpu-color-ink) 70%, transparent);
  }
}
```

關鍵在**定義位置**。定義在 `#mp_ukagaka` 上，主題就能針對仍由 CSS 繪製的部分接管：

```css
/* 示意：只接管仍由 CSS 繪製的文字／連結，不宣稱完成深色模式 */
html.dark-mode #mp_ukagaka {
  --mpu-color-ink: var(--color-text-dark);
  --mpu-color-link: var(--color-link-sidebar);
}
```

> **CODEX 更正：** `moelog-20th/style.css:2645` 後的第三方深色補丁實際針對的是 `moelog-ai-qna-links` 的 `.moe-aiqna-block`，不是 MP Ukagaka。該段文字可用來說明「主題與外掛應透過契約協作」的原則，但不能當成 MPU 已存在的需求或宣稱 MPU token 上線後即可刪除那段補丁。

此外，`--mpu-color-surface` 也不能讓 PNG 對話框真正變成深色。公開 token 第一版只承諾可安全覆寫的文字、連結、控制項與非點陣表面；凡是會與 `msgbox_*.png` 共同構圖的 token，必須在文件中註明限制，避免形成假的深色模式 API。

`moelog-20th/style.css:2653` 的註解表達了相同的跨專案維護原則：

> The plugin is a separate codebase on its own release cycle, so styling it from here keeps theme updates from carrying plugin edits. **If the plugin ever adopts CSS custom properties or layers, delete this block.**

**驗收**：完成後在 `docs-en/DEVELOPER_GUIDE.md` 新增一節「Theming the frontend widget」，把 `--mpu-*` 公開清單寫成契約（哪些保證穩定、哪些是內部實作）。**沒有文件的變數不算 API。**

> **公開契約是否發布，視深色模式是否列入路線圖而定（2026-09-01 作者確認：目前不列入）。**
>
> C2-3 把公開 token 從 8 個擴到 13 個，理由完全來自「宿主主題完整覆寫 palette、但瀏覽器不支援 `color-mix()` 會半套失效」——那是深色模式場景。深色模式既不在路線圖上，這 13 個 token 的公開契約就沒有已知消費者。
>
> **因此 P0-A 的預設做法是：只做內部 token 化，暫不發布 API 契約。** 依上一行自己的規則，不寫進 `DEVELOPER_GUIDE.md` 就不形成相容性承諾，日後改名不算破壞性變更——保留彈性，也省掉本項的文件工作。
>
> 這**不改變 §4.1 的 token 設計本身**：語意化命名、`--mpu-internal-*` 前綴區分、靜態 fallback + `@supports color-mix` 推導全部照做。差別只在「是否對外承諾」。若日後深色模式進入路線圖，把既有的 13 個 token 寫進文件即可升格為 API，不需重做。

**公開契約決定（C2-3 已收斂）：** 第一版公開 13 個 token。新增的五個不是通用的 `ink-30`／`ink-55` 色階，而是有用途語意的 control border、focus ring 與 scrollbar token。現代瀏覽器會由 ink/link 自動推導；不支援 `color-mix()` 的舊瀏覽器仍有靜態預設值，而需要完整換色的宿主主題必須明確覆寫全部 13 個公開 token。這比默許「只換文字、衍生色不連動」的部分失效更可測試，也避免把無語意的 alpha 階梯永久鎖成 API。

**命名與 fallback 原則已反映在上例：** 公開 token 使用語意完整名稱；內部 token 使用 `--mpu-internal-*` 明示非契約。靜態值先保證基本可讀性，再由 `@supports` 提供自動連動；API 文件必須同時示範「只覆寫基礎色」與「跨舊瀏覽器完整覆寫 palette」兩種用法。

**實作時待拍板的兩個 token 邊界（不影響 13 個公開表面的決定）：**

1. **捲軸軌道的歸屬。** 滑塊已升為公開（`--mpu-color-scrollbar-thumb`），軌道仍為 `--mpu-internal-scrollbar-track: rgb(200 200 200 / 30%)`。兩者永遠同時被看見；深色宿主覆寫完 13 個 token 後，軌道仍是淺灰。二擇一並寫進契約：**(a)** 升為第 14 個公開 token；**(b)** 比照 `moelog-20th` 對 `--color-img-mat` 的處理（「照片襯底在兩種模式都維持紙白，調暗它等於替它要分隔的圖片上色」），明文寫「軌道刻意在兩種模式維持中性」。重點是它必須是決定，不是遺漏。
2. **兩個灰色的合併。** `.mpu-gift-picker-counter` 用 `#64748b`（slate-500），`--mpu-color-muted` 定為 `#6b7280`（gray-500）。token 化等於要挑一個，視覺差異幾乎不可辨，但屬需明確拍板的合併，不應在實作時默默決定。（`.mpu-msg-role` 的 `#6b7280` 隨 B-1 消失，不列入考量。）

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
  background: url(../images/menu.png) no-repeat center;
}

#mp_ukagaka #ukagaka-dock ul {
  display: flex;
  justify-content: center;
  align-items: center;
  height: 45px;
  gap: 19px;
  padding-inline-start: 64px;
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
- `#ukagaka_msg` 的 4 條 `::-webkit-scrollbar-*`（`:243-259`）與 chat-mode 的另外 4 條（`:283-299`）→ 變數化 `--mpu-internal-scrollbar-size` 後合併為 1 組

**預估**：仍可透過去重與 `:is()` 明顯縮短；行數不是驗收標準，清楚的元件邊界與可搜尋性優先。

---

### Phase 1（P1）— 邏輯屬性（**有條件採用**）

這裡與參照主題的做法要刻意分開。主題內文是文本流，`margin-inline` 全面採用完全正確；但 mpu 的定位有兩種性質：

| 性質 | 例子 | 處置 |
|---|---|---|
| **文本流相關** | `#ukagaka_msg` 的 `padding: 10px 15px`、`text-align: right`、dock `ul` 的 `padding-left: 64px` | ✅ 換成 `padding-inline` / `text-align: end` |
| **視覺錨點** | `#ukagaka_shell { right: 0 }`、`.mpu-think-bubble { right: 38px }`、尾巴 `::after { right: 40px }`、`#ukagaka { padding-right: 65px }` | ❌ **維持物理屬性** |

**理由**：角色是「釘在畫面右下角」的物件。氣泡尾巴的對位、表情位置、以及 `js/ukagaka-emoji.js:138-140` / `:172-174` 硬寫的 `style.left` / `style.top`，全都是視覺座標。若 CSS 單方面換成 `inset-inline-end`，站台上了 RTL 語系後角色會跑到左側，但 JS 的 `style.left = '100%'` 不會跟著翻 → **表情與角色分家**。

**決定**：只換文本流部分。並在檔頭註解寫明「定位屬性刻意保留物理值，因與 `ukagaka-emoji.js` 的視覺座標耦合；要做 RTL 必須 CSS + JS 同時處理」，避免未來被當成疏漏「補齊」。

---

### Phase 1（P1）— 現代顏色語法

`rgba(30, 58, 138, 0.5)` → `rgb(30 58 138 / 50%)`（與主題一致），再讓支援的瀏覽器由 `--mpu-color-ink` 經 `color-mix()` 推導。

**暫緩**：`rgb(from var(--mpu-color-ink) r g b / 30%)` 相對顏色語法。它更精簡，但本案不需要用較新的語法取代已有靜態 fallback 的 `color-mix()` 漸進增強。

---

### Phase 2（P2）— 漸進增強（挑有用的抄）

參照主題 `@supports` 那 8 組，對 MPU 值得採用或補強的項目如下：

| 項目 | 套用位置 | 價值 |
|---|---|---|
| `overscroll-behavior: contain` | `#ukagaka_msg` | 聊天記錄滾到底時不會連動整頁捲動。**實打實的體驗修復**，優先度最高 |
| `prefers-reduced-motion: reduce` | think bubble 的 `translateY` 淡入、gift picker 的 `scale(1.12)` hover | 目前**完全無保護**，無障礙審查會抓。**注意順序**：`mpu-msg-fade-in` 不列入，它隨 B-1 整組刪除，須先做 B-1 再做本項，否則會替死碼補保護 |

> **`.mpu-thinking` 的 opacity 脈動刻意不列入 reduced-motion 停用清單。** `prefers-reduced-motion` 針對的是位移／縮放／視差這類前庭觸發，純 opacity 淡入淡出一般視為安全；而這個脈動是「AI 正在思考」的**唯一視覺訊號**，關掉之後思考中與閒置態會長得完全一樣，等於用無障礙改善換來功能退化。若實測仍覺過度，改為收窄幅度（`0.3 → 1` 調整為 `0.6 → 1`）而非停止動畫。
| `text-wrap: pretty` | `#ukagaka_msg`、`.mpu-think-bubble` | 對話泡泡避免落單字。用 `pretty` 而非主題的 `balance`——後者適合標題，前者適合流式內容 |
| `:focus-visible` | 對話框 OK／Cancel 與 dock 三個操作 | 現況把 `outline` 全面移除；必須補回不影響滑鼠操作的鍵盤焦點指示 |
| `dvh` + safe-area fallback | shell 與 message box | 僅處理桌機縮放、窄視窗、分割畫面與瀏覽器 UI 遮擋，不延伸為手機版設計 |

**不抄**：`view-transition`（外掛不控制導航）、`content-visibility`（元素太小無收益）、`-webkit-line-clamp`（無此需求）、`backdrop-filter`（對話框底是點陣圖，糊化沒意義）。

---

## 5. 順手該修的實際問題

以下與現代化無關，但在同一次整理中處理成本最低。

| # | 位置 | 問題 | 處置 |
|---|---|---|---|
| B-1 | `css/mpu_style.css:603-629` | `.mpu-chat-message`、`.user`、`.assistant`、`.mpu-msg-role` **全 repo 零引用**（PHP / JS / `js/dist/` 皆無）；其專用 `mpu-msg-fade-in` 也成為孤兒 | 整組刪除；刪前再以 production bundle 與執行時 DOM inventory 確認一次 |
| B-2 | `css/mpu_style.css:593` + `:601` | `.mpu-gift-picker-fallback` 連續宣告兩次 | 合併為一條 |
| B-3 | `css/mpu_style.css:12` + `:15` | `#ukagaka_shell` 同時有 `position: fixed` 與 `float: right`；fixed 之下 float 被忽略 | 刪 `float` |
| B-4 | `css/mpu_style.css:650`、`:654-663` | `@keyframes mpu-thinking-dots` 的 0% / 50% / 100% 三處 `content` 值**完全相同**（都是 `"…"`），實際只有 `opacity` 在閃 | **作者已裁決：保留單一省略號呼吸效果。** 固定 `content: "…"` 放在 `.mpu-thinking::after`，keyframes 只控制 opacity，動畫改名 `mpu-thinking-pulse`。**⚠️ 非 no-op，見下方註記** |

> **B-4 實作註記（Phase -1 對比時勿誤判）：** 位元組層級確認，基礎規則與 keyframes 用的是**不同字元**——
>
> ```
> css/mpu_style.css:650   content: "..."   → 2E 2E 2E（三個 ASCII 句點）
> css/mpu_style.css:657   content: "…"     → E2 80 A6（U+2026）
> css/mpu_style.css:661   content: "…"     → E2 80 A6
> ```
>
> 因此現況在會套用離散 `content` 動畫的瀏覽器上，glyph 實際會從 `...` 跳成 `…`；不套用的瀏覽器則一路停在 `...`。裁決採用的 `content: "…"` 固定於基礎規則，等於**在後者那群瀏覽器上把三點改成單一省略號字元**。字元選擇正確（`…` 才是原意），但這會在 Phase -1 基準圖對比中呈現為真實差異，**屬預期變更，不是回歸**。
| B-5 | `css/mpu_style.css:680` | `.mpu-state-badge` 字型堆疊結尾是 `sans-serif`，其他五處都是 `serif`。**`serif` 收尾與主題 `--font-body` 一致，是刻意的**；不一致的是 badge | badge 統一到 `var(--mpu-font-family)` |
| B-6 | `css/mpu_style.css:23` | `margin-bottom: 0px`（單位冗餘） | → `0` |
| B-7 | `css/mpu_style.css:228` | `border: 0 solid`（`solid` 無意義） | → `border: 0` |
| B-8 | `css/mpu_style.css:341-353` | dock `li` 同時有 `list-style-type: none` 與 `list-style: none` | 保留後者 |
| B-9 | `css/mpu_style.css:58` / `:63` | `z-index: 99` 與 JS 端裝飾物 `z-100` 的隱性契約只寫在註解 | 變數化為 `--mpu-internal-z-canvas`，並在 JS 端註解回指 |

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
| `text-wrap: pretty` | 2024-2025 陸續 | ⚠️ 純漸進增強，不支援時無損，可直接寫 |
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

1. **Phase -1：視覺基準線先行。** 在任何 token 化或 selector 改寫之前，用 Playwright MCP（專案已有 `.playwright-mcp/`）對現況拍五組基準圖——
   - 一般態（角色 + 主對話框）
   - `chat-mode` 展開（含輸入框、捲軸）
   - gift picker 展開（單項 / 多項 slider 兩種）
   - think bubble 顯示（`system` 短語態與 `chat` 長文態）
   - dock hover 三顆按鈕
2. 每個 Phase 完成後逐張對照，差異須能逐條解釋。
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
- `custom-property-pattern: "^mpu-"`
- 重複 selector／無效值等低爭議 correctness 規則

不建議第一天就開全域 `color-no-hex`：token 定義本身必須容許顏色字面值，否則會逼出無意義的 disable。元件宣告禁止硬寫顏色可在 token 化穩定後，再用精準規則或 review 守門。

---

## 9. CODEX 檢討爭點與收斂結果

本節保留初稿（CLAUDE）提出的四個爭點原文，後接 CODEX 第一輪答覆、CLAUDE 第二輪回應與最終裁決，讓後續 reviewer 看見完整決策脈絡。**四個爭點均已收斂。**

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
| P-1 | 視覺基準、DOM inventory、主題與桌機視窗矩陣準備 | 半天 |
| P0-A | 自訂屬性 + token 化（**不含 API 文件化**，見 §4.1 註記） | 2～3 小時 |
| P0-B | `!important` 消除 + 主題交叉測試 | 一天（測試佔一半） |
| P1-A | 扁平元件區塊整理 + `:is()` 收攏 | 半天 |
| P1-B | 邏輯屬性（限文本流）+ 現代顏色語法 | 2 小時 |
| P2 | 漸進增強、鍵盤焦點與桌機窄視窗防溢位 | 1～2 小時 |
| B-* | §5 的九項實際問題 | 1 小時 |
| — | stylelint 導入 | 半天 |

**合計約 3 個工作天**（API 文件化暫緩後由 3～3.5 天下修），其中視覺驗證佔比最高。建議 P-1 基準資料獨立保存，P0-A 與 P0-B 各自獨立 commit，方便回退。
