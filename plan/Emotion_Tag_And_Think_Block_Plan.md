# 情緒標籤系統與內心獨白渲染計畫

> 📅 規劃日期：2026-05-29
> 🧭 來源：Open-LLM-VTuber 設計研究（`live2d_model.py` / `prompts/utils/live2d_expression_prompt.txt` / `prompts/utils/think_tag_prompt.txt`）+ Claude 與 Gemini 雙方分析建議的交集
> 🎯 目標版本：**v2.25.0 ~ v2.28.0**（接續當前 v2.24.0；分多個 minor 版發佈，詳見 §13.7）
> 📂 影響範圍：`includes/personality/`、`includes/llm/`、`includes/rest/`、`js/ukagaka-chat.js`、`js/ukagaka-core.js`、`css/mpu_style.css`
>
> **版號政策備註**：本計畫所有變更皆**向下相容**（`[表情: xxx]` 中文語法、`mpu_filter_thinking_content()`、emoji-mapper keyword fallback、既有 REST schema、personality 檔案格式皆不破壞），故走 minor bump 而非 major bump。**v3.0.0 保留給 Live2D 實裝**（真正的破壞性架構變更：新增 Cubism SDK 依賴、shell 渲染從 `<img>` 改為 `<canvas>` + WebGL、model3.json 配置取代靜態 PNG 機制）。

---

## 1. 背景與動機

### 1.1 現況問題

mp-ukagaka 目前的情緒/思考處理是「事後過濾」式：

| 環節 | 現有實作 | 痛點 |
|------|----------|------|
| **情緒辨識** | `mpu_analyze_emoji_from_text()`（[emoji-mapper.php:378](../includes/personality/emoji-mapper.php#L378)）對 LLM 回應做關鍵字掃描 | 誤判嚴重 — 「我**沒有生氣**」、「我才**不會難過**」都會觸發負面表情 |
| **顯式標籤** | `mpu_extract_explicit_emotion()`（[emoji-mapper.php:328](../includes/personality/emoji-mapper.php#L328)）解析 `[表情: xxx]` | 已存在但 prompt 引導弱、未過濾顯示文字（LLM 寫的 `[表情: 笑]` 會直接被使用者看到） |
| **思考標籤** | `mpu_filter_thinking_content()`（[ai-functions.php:136](../includes/llm/ai-functions.php#L136)）整段丟棄 `<think>...</think>` | 浪費了角色內心戲的呈現機會 — 對伺か這種強調人格的形態尤其可惜 |

### 1.2 Open-LLM-VTuber 給的啟發

兩個對應的設計：

**A. 表情標籤閉環**（`live2d_model.py:extract_emotion()` / `remove_emotion_keywords()`）
1. 把可用表情清單動態注入 system prompt：`[<insert_emomap_keys>]`
2. 引導 LLM 在句中**主動**埋入 `[joy]`、`[surprise]` 等標籤
3. 後端抽取標籤 → 對應到表情索引
4. **從文字中移除標籤** → 乾淨文字才送給前端 / TTS

**B. `<think>` 內心獨白**（`think_tag_prompt.txt`）
- LLM 在 `<think>*臉微微泛紅*</think>` 中表達動作或心理活動
- 不被 TTS 朗讀，但**可視化**呈現給觀眾

### 1.3 兩個 AI 的共識點

Claude 與 Gemini 獨立分析後，都指向同一組改進方向：

| 項目 | Claude（建議 1） | Gemini（建議一） |
|------|------------------|----------------|
| 情緒標籤注入+過濾 | ✅ 列為最該抄的第 1 件事 | ✅ 列為建議 1 |
| `<think>` 視覺化 | ✅ 列為最該抄的第 2 件事 | ✅ 列為建議四 |

→ 雙重背書，方向確定。

---

## 2. 設計總則

### 2.1 不破壞既有相容性

- 現有 `[表情: xxx]` 語法**保留**並作為「中文 fallback 語法」
- 新增的 `[joy]` 等短標籤**並存**，由同一個解析器處理
- `mpu_filter_thinking_content()` 不刪除，改為**可選行為**（保留給不支援 `<think>` 渲染的舊路徑使用）

### 2.2 標籤詞彙表的設計

採用「**簡短英文標籤 + 角色 emoji 配置驅動**」策略，避免硬編碼：

- 每個角色的 `ghost/<id>/emoji-keywords.json` 已經宣告了 `supported` 清單（如 Frieren 有 `notice`、`thinking`、`upset`、`laugh`、`shy`、`angry` 等）
- system prompt 中**直接列出該角色支援的標籤**，不另設詞彙表
- LLM 寫 `[laugh]` → 對應 `laugh.png`；寫了不存在的 `[evil]` → 過濾掉但不換表情

### 2.3 思考標籤的渲染原則

- `<think>` 內容**不送 TTS、不計入 checksum、不存進對話歷史**（避免污染下一輪 context）
- 視覺上獨立呈現於主對話框上方/旁邊（user 提到「應該要弄新的對話框」→ 確認此方向）
- 可由 admin 全域關閉（給不想看內心戲的用戶）

---

## 3. Phase 1 — 情緒標籤系統

### 3.1 Prompt 注入改進

**檔案**：`includes/personality/personality-loader.php`（現有 emotion instruction 在 L459-470）

目前的指令：
```
【表情代碼指定】
你可以在回覆末尾添加 [表情: 表情名] 來顯式指定你的表情。這比自動識別更準確。
當前可用的表情名有：notice、thinking、upset、laugh、shy、angry、talking、amazed、sigh
```

**改進為**（仿 Open-LLM-VTuber `live2d_expression_prompt.txt`，但因 APNG 動畫限制改為**單 tag 政策**）：
```
## 表情標籤（Expression Tags）
你可以在回覆中嵌入一個表情標籤，用方括號包住，例如 [laugh]。
標籤會被系統解析來切換你的表情圖案，並從顯示文字中移除（讀者看不到方括號）。

當前可用標籤：[notice], [thinking], [upset], [laugh], [shy], [angry], [talking], [amazed], [sigh]

### 範例
- 「找到了！[laugh] 這個魔法我研究很久了。」
- 「[thinking] 嗯…等等，這個圖案我見過。」
- 「[sigh] 又是這種無聊的請求…」

### 規則
- **每次回覆只使用一個表情標籤**，放在最能代表整段話情緒的位置
- 表情圖是動畫（APNG），連續切換會打斷動畫週期，因此單一鮮明的情緒比多個切換更有表現力
- 只能使用上方列出的標籤，其他標籤會被忽略
- 標籤不會被朗讀，僅控制表情
- 仍可使用 [表情: xxx] 中文格式（向下相容）
```

關鍵差異：
1. 標籤可**內嵌句中**（不再限制「末尾」）
2. **單 tag 政策**：與 Open-LLM-VTuber 的多 tag 設計不同，因 mp-ukagaka 表情為 APNG 預烘焙動畫（laugh.png 23 frames infinite、thinking.png 15 frames infinite 等），中途切換會強制終止當前播放幀，視覺破碎感明顯。Live2D 因有 motion blending 才能多 tag 平滑連續，APNG 學不來。
3. 明確告知「方括號會被移除」→ LLM 更願意使用

> 此單 tag 限制屬於 **policy 層**（prompt + 前端 honor 第一個 emotion event），**mechanism 層**（normalizer 仍輸出完整 `emotion_tags` 陣列、parser 仍 emit 多個 emotion event）保留多 tag 能力，待 v3.0.0 Live2D 實裝後可解除（見 §10）。

### 3.2 抽取與過濾函式

**新增檔案**：`includes/personality/emotion-tag-parser.php`

提供三個核心函式（注意是新模組，不污染 `emoji-mapper.php` 的關鍵字匹配邏輯）：

```php
/**
 * 從文字中抽取所有情緒標籤（依出現順序）
 * @return array  例：['laugh', 'thinking', 'notice']
 */
function mpu_extract_emotion_tags($text, $personality_id = null): array;

/**
 * 移除文字中所有合法的情緒標籤（清乾淨給使用者看的版本）
 * 只移除「在 supported 清單內」的標籤，避免誤刪正常方括號內容
 */
function mpu_strip_emotion_tags($text, $personality_id = null): string;

/**
 * 抽取 + 清理 + 對應檔名，一次完成
 * @return array{
 *   tags: string[],      // ['laugh', 'thinking']
 *   files: string[],     // ['laugh.png', 'thinking.png']
 *   primary: ?string,    // 'laugh.png'（取第一個合法標籤）
 *   clean_text: string,  // 移除標籤後的文字
 * }
 */
function mpu_parse_emotion_payload($text, $personality_id = null): array;
```

**為什麼不直接擴充 `emoji-mapper.php`？**
- `emoji-mapper.php` 的主流程是「關鍵字評分匹配」，是後備策略
- 新模組職責是「LLM 主動標註的結構化解析」，邏輯獨立
- 兩者透過 `mpu_analyze_emoji_from_text()` 串接：先試 `mpu_parse_emotion_payload()`，無結果才退回關鍵字匹配

### 3.3 整合點清單

| 檔案 | 行號附近 | 改動 |
|------|---------|------|
| `personality-loader.php` | L459-470 | 替換 emotion instruction 為新版本（§3.1） |
| `emotion-tag-parser.php` | 新檔 | 實作三個解析函式 |
| `emoji-mapper.php` | L385（`mpu_analyze_emoji_from_text` 開頭） | 優先呼叫 `mpu_parse_emotion_payload()`，相容 `[表情: xx]` 仍交給 `mpu_extract_explicit_emotion()` |
| `class-mpu-rest-chat.php` | 非流式回應點（L1080 附近） | 在送出 response 前呼叫 `mpu_parse_emotion_payload()`，回傳 `{ text, emoji, emotion_tags }` |
| `class-mpu-rest-chat.php` | 流式回應點（L1206 附近） | 串流結束後對 `$full_response_content` 解析；串流中也需即時把標籤從 delta 過濾掉（見 §3.4） |
| `class-mpu-rest-dialog.php` | L520 附近 | 同步處理 |
| `mp-ukagaka.php` | `mpu_load_modules()` | 在 `emoji-mapper.php` 之前 require `emotion-tag-parser.php` |

### 3.4 SSE 串流時的標籤過濾（關鍵難點）

> ⚠️ **本節已被 §13.3 取代**（CODEX §14.2.1 + Antigravity §15.1 共識）。
> 原方案「20 chars / 20ms timeout 緩衝」**廢棄**。實作請直接參考 §13.3 的「前綴白名單匹配 + `MPU_Stream_Output_Parser` 狀態機」。
> 此段保留作為歷史紀錄，不要照此實作。

~~問題：LLM 串流回應一次一個 token，可能 `[laugh]` 被切成 `[la` + `ugh]` 兩個 delta。~~

~~策略：在 `class-mpu-rest-chat.php` 串流回呼中維護**緩衝區**：~~
- ~~收到 `[` 開始緩衝，直到遇到 `]` 或緩衝超過 N 個 chars（例如 20）才 flush~~
- ~~flush 時呼叫 `mpu_strip_emotion_tags()`，把合法標籤抽出 → 額外發 SSE 事件 `event: emotion` `data: {tag: "laugh"}`~~
- ~~非標籤的方括號內容（如 markdown 連結）原樣 flush 給前端~~

→ checksum 計算用 `clean_text`（標籤剝離後），與既有 chat-integrity 系統相容。**此原則仍有效**，但 normalizer 契約（§13.2）的 `checksum_text` 才是正式來源。

### 3.5 Phase 1 工數估計

| 任務 | 工數 |
|------|------|
| `emotion-tag-parser.php` 實作 + 單元測試思路 | 0.5d |
| Prompt 模板改寫 + 多語版本（日中英） | 0.5d |
| 非流式整合（REST chat/dialog 各點） | 0.5d |
| 串流緩衝過濾邏輯 + 新 SSE 事件型別 | 1d |
| 前端 `ukagaka-chat.js` 處理 `emotion` 事件 → 切換表情 | 0.5d |
| Frieren 角色 prompt fine-tune + 實測 | 0.5d |
| **小計** | **3.5d** |

---

## 4. Phase 2 — 內心獨白渲染（新對話框）

### 4.1 UI 設計（user 已確認需要新對話框）

**位置決議（2026-05-29 確認）**：think 框放在**芙莉蓮頭部右上方**，與既有左方主對話框形成「對話在左 / 內心在右上」的視覺對比。

```
                                 ╭──────────────────╮
                                 │ 💭 嗯…這個問題  │  ← think bubble
                                 │   還真不好回答  │     (角色頭部右上、傾斜浮起)
                                 ╰─────╯ ╮ ──────────╯
                                          ╲
┌─────────────────────────────────┐    ┌─────┐
│ 我覺得啊，這個應該是…           │    │     │
└─────────────────────────────────┘    │ 芙莉│
                                       │ 蓮  │  ← #ukagaka_shell (右側)
              ↑                        │     │
       既有 #ukagaka_msg              └─────┘
       (左方主對話框)
```

設計重點：
- **位置**：絕對定位於 `#ukagaka_shell` 右上方（`top: -16px; right: -32px;` 之類的負偏移，讓 bubble 像氣球一樣從頭部浮起）
- **小尾巴指向**：用 `::after` 偽元素畫一個小三角或氣泡引線指向角色頭部，視覺上強化「這是這個角色的想法」
- **與主對話框的視覺對比**：主對話框是「對外發言」、實心 border、典型對話框造型；think 框是「對內獨白」、虛線 border、雲朵/氣球風格 → 不會被誤認為同一條訊息
- **視覺樣式**：半透明白底 (`rgba(255,255,255,0.85)`)、字級小 1 級、字色淡灰 (`#666`)、斜體、預設加上 💭 icon
- **動畫**：先於主對話框 0.3-0.5s 出現（fade-in + 微微的 scale 0.9 → 1.0），給人「想法先冒出來，然後才開口說」的時序感
- **生命週期**：主對話框內容結束後 think 框延遲 1s 淡出消失
- **可關閉**：admin 設定可全域隱藏（給不喜歡內心戲的人）；單次點擊可隱藏該次（採納 Antigravity §11.2 Q1）
  - 全域開關使用**新增**的 `enable_inner_monologue` option（預設 `true`），**不可沿用**既有的 `ollama_disable_thinking`（那是 Ollama 原生 reasoning 引擎層開關，職責不同）→ 完整語意定義見 **§13.2 規則 8** 與 **§13.4 Q6**

**為什麼選右上而不是上方居中**：
1. 主對話框已佔左半畫面，think 框若再放上方居中容易跟主對話框視覺重疊／競爭注意力
2. 角色在右、think 在頭部右上 → 視線動線是「氣球從頭部冒出 → 角色 → 左方對話框」，這個 Z 字流動跟漫畫的閱讀順序很搭

> **手機環境不在本計畫範圍**。`mpu_is_show_page()`（[frontend-functions.php:155](../includes/core/frontend-functions.php#L155)）在 `wp_is_mobile()` 為真時直接 return false，整個外掛在手機上不會渲染任何 DOM，所以 think bubble 也不需要 mobile 排版方案。

### 4.2 DOM 結構（新增）

於 `frontend-functions.php` 角色容器產生處新增。`#ukagaka_think` 必須是 `#ukagaka_shell` 的兄弟節點（或子節點），以便用絕對定位錨在角色頭部右上：

```html
<div id="ukagaka_container" style="position: relative">
  <div id="ukagaka_msg" class="mpu-main-bubble">...</div>      <!-- 主對話框 (左) -->
  <div id="ukagaka_shell_wrap" style="position: relative">     <!-- 角色容器 (右) -->
    <img id="ukagaka_shell" />
    <div id="ukagaka_think" class="mpu-think-bubble" hidden></div>
  </div>
</div>
```

CSS（新增於 `mpu_style.css`）：
```css
/* think bubble 浮在 shell 右上方 */
.mpu-think-bubble {
  position: absolute;
  top: -8px;
  right: -24px;             /* 從角色頭部右上「冒出」 */
  transform: translateX(40%) scale(0.92);  /* 初始位置稍偏右 */
  max-width: 220px;
  min-width: 100px;
  padding: 8px 14px;

  background: rgba(255, 255, 255, 0.88);
  backdrop-filter: blur(2px);
  border: 1.5px dashed rgba(120, 120, 120, 0.5);
  border-radius: 18px 18px 18px 6px;     /* 左下角小一點 → 朝向角色頭部 */
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);

  font-size: 0.85em;
  font-style: italic;
  color: #666;
  line-height: 1.4;

  opacity: 0;
  transition: opacity 0.4s ease, transform 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
  pointer-events: auto;
  cursor: pointer;          /* 提示可點擊隱藏 */
  z-index: 10;
}
.mpu-think-bubble.is-visible {
  opacity: 1;
  transform: translateX(40%) scale(1);
}
.mpu-think-bubble::before { content: "💭 "; }

/* 小尾巴指向角色頭部（左下方向） */
.mpu-think-bubble::after {
  content: "";
  position: absolute;
  left: 8px;
  bottom: -8px;
  width: 10px;
  height: 10px;
  background: rgba(255, 255, 255, 0.88);
  border-right: 1.5px dashed rgba(120, 120, 120, 0.5);
  border-bottom: 1.5px dashed rgba(120, 120, 120, 0.5);
  transform: rotate(45deg);
}

```

DOM 重點：
- `#ukagaka_shell_wrap` 必須 `position: relative`，才能讓 think bubble 用 `position: absolute` 錨定在角色容器內
- `hidden` 屬性取代 `style="display:none"`，符合語意化 HTML（JS 切換 `is-visible` class 控制顯示）
- 不需要 mobile media query：`mpu_is_show_page()` 已在 PHP 端 `wp_is_mobile()` 直接 return false，手機根本不會渲染任何 DOM

### 4.3 後端處理

**新增 helper**：`includes/llm/think-block-parser.php`

```php
/**
 * 抽取 <think>...</think> 內容並返回 {think, main}
 * 支援 §4.1 mpu_filter_thinking_content 已涵蓋的所有別名（thinking, reflection,
 * chain_of_thought, reasoning, inner_monologue, redacted_reasoning）
 *
 * @return array{think: string, main: string}
 */
function mpu_split_think_block($text): array;
```

**`mpu_filter_thinking_content()` 重構**：
- 現有 L136-188 改為兩階段：
  1. `mpu_split_think_block($response)` → 取得 `think` 與 `main`
  2. 若呼叫端不需要 think → 只返回 `main`（保持既有行為）
  3. 新增 `mpu_filter_thinking_content_with_split()` 返回 `array` 供新流程使用

**REST 回應 schema 擴充**：
```json
{
  "response": "我覺得啊…",
  "emoji": "thinking.png",
  "emotion_tags": ["thinking"],
  "think": "（嗯…這個問題還真不好回答）",   ← 新欄位，可能為空字串
  "checksum": "..."
}
```

### 4.4 SSE 串流的 think 處理

策略：think 區塊**整段緩衝**直到 `</think>` 才發送。

理由：
- think 框是「一次性顯示心理活動」，逐字打字反而違和
- 主對話文字在 think 結束後才開始（LLM 通常先寫 think 再寫主回應）

SSE 事件：
```
events:
  - status:   {type: "thinking"}                      ← 進入 <think>
  - think:    {text: "嗯…這個問題還真不好回答"}        ← </think> 後一次發送
  - delta:    {text: "我覺得"}                         ← 主回應開始
  - delta:    {text: "啊…"}
  - emotion:  {tag: "thinking"}
  - done
```

前端 `ukagaka-chat.js` 處理：
- 收到 `status: thinking` → 顯示「思考中…」placeholder（可選）
- 收到 `think` 事件 → 渲染 think 框 + 加 `.is-visible`
- 收到 `delta` 開始 → 啟動 main 對話框 typewriter

### 4.5 Prompt 引導

於 `personality-loader.php` 加入新 instruction 區段。**此段 prompt 受 `enable_inner_monologue` 控制**：當 admin 關閉時完全不注入，避免徒增 token 消耗（見 **§13.2 規則 8** 的三件事約束 + **§13.4 Q6** 為何不沿用 `ollama_disable_thinking`）。

```
## 內心獨白（Inner Monologue）
你可以選擇性地在回覆前以 <think>...</think> 包裹一段內心活動、肢體動作或情緒
描寫。這段內容會被特別呈現給讀者看，但不會被朗讀。

### 適用情境
- 表達角色的真實想法（與口頭回答不同的內心 OS）
- 描述動作（例：*抬起頭*、*嘆了口氣*）
- 短暫的猶豫或感慨

### 範例
<think>*微微抬頭，目光飄向遠方* 又被問到這個問題了…</think>
這個啊，我以前也想過很多次，但答案其實很簡單。

### 規則
- think 區塊每次回覆**最多一段**
- 內容簡短（建議 30 字內，最長 80 字）
- 不要在 think 中嵌入表情標籤（[laugh] 那些）
- `<think>` 與 `</think>` 標籤必須成對出現，或者完全不使用。絕不能輸出孤立的 `</think>`。
```

### 4.6 Phase 2 工數估計

| 任務 | 工數 |
|------|------|
| `think-block-parser.php` + 重構 `mpu_filter_thinking_content` | 0.5d |
| DOM + CSS + 動畫 | 0.5d |
| 前端 SSE handler + typewriter 隔離 | 1d |
| REST schema 擴充 + 非流式路徑整合 | 0.5d |
| Prompt 模板（多語） + admin 開關 | 0.5d |
| Frieren 內心獨白風格 fine-tune + 實測 | 0.5d |
| **小計** | **3.5d** |

---

## 5. 整體里程碑

> ⚠️ **本節已被 §13.7 取代**（CODEX §14.2.2 + Antigravity §15.1 共識）。
> 正式實作里程碑請參考 §13.7（M1a/M1b/Prompt 切換/M2/M3/M4，7.5d 總計），含 normalizer 契約優先的順序。
> 此段保留作為歷史原案紀錄。

~~| 階段 | 內容 | 工數 | 可獨立發版 |~~
~~|------|------|------|----------|~~
~~| **M1** | Phase 1 §3.1-3.3（非串流） | 1.5d | ✅ 可單獨上 v2.16.0 |~~
~~| **M2** | Phase 1 §3.4（串流支援） | 1.5d | ✅ v2.16.1 |~~
~~| **M3** | Phase 2 §4.1-4.3（基礎內心獨白） | 1.5d | ✅ v2.17.0 |~~
~~| **M4** | Phase 2 §4.4-4.5（串流 + Prompt） | 2d | ✅ v2.17.1 |~~
~~| **總計** | | **6.5d** |~~

---

## 6. 風險與注意事項

| 風險 | 影響 | 緩解 |
|------|------|------|
| LLM 不肯用新標籤（仍寫 `[表情: 笑]`） | 新功能無感 | 相容舊語法 + 在 Frieren prompt 多放範例 |
| Claude/Gemini/OpenAI 對標籤的服從度差異大 | 部分 provider 體驗弱 | 在 admin 加「標籤強制度」滑桿，弱模型套用更強的指令 |
| `[`、`]` 在 markdown link `[text](url)` 中誤觸發 | 連結被破壞 | `mpu_strip_emotion_tags` 嚴格白名單比對（只匹配 supported 內的） |
| SSE 緩衝增加感知延遲 | 「打字慢了」 | **前綴白名單匹配**：一旦緩衝內容不再是任何合法 tag 前綴即刻 flush；串流結束時呼叫 `MPU_Stream_Output_Parser::flush()` 收尾。徹底不依賴固定 timeout（§13.3 / §15.1） |
| `<think>` 與工具呼叫 JSON 的 `{` 衝突 | tool-loop-guard 誤判 | think 必須在 tool JSON **之前**結束；於 prompt 明示規則 |
| Checksum 系統因新欄位 mismatch | observational mode 噪音 | checksum 計算明確指定只算 `clean_text`，think 不參與 |
| 前端 typewriter 同時跑兩個（think + main） | 視覺混亂 / timer 衝突 | think 用 fade-in 一次顯示（非 typewriter），main 才走打字機 |
| `ollama_disable_thinking` 與 `<think>` bubble 開關混用 | 設定語意污染；使用者無法同時「保留 Ollama reasoning」但「隱藏角色內心戲」 | 新增獨立 `enable_inner_monologue` option。`ollama_disable_thinking` 只控制 Ollama/Qwen/DeepSeek 原生 reasoning；`enable_inner_monologue` 只控制跨 provider 的 UI 內心獨白演出層（§13.2 規則 8 / §13.4 Q6） |

---

## 7. 與既有 plan 的關聯

- **`Loop_Detection_Plan.md`**：think 區塊不送入 tool-loop-guard 的歷史比對。其運作本質為：既有 `tool-loop-guard.php` 比對的是 tool name + args hash，不看 raw text。所以此處隔離的真正意思是，Response Normalizer 必須在 tool call JSON 解析之前就將 `<think>` 內容與標籤剝離，避免 think 區塊中可能包含的 `{}` 括號干擾 JSON 語法偵測。
- **`SSE_Streaming_Plan.md` / `Streaming_Full_Provider_Plan.md`**：本計畫 §3.4 與 §4.4 新增的 `emotion`、`think`、`status` 事件型別需在 SSE 規範文件中補登
- **`UnifiedHistory_MemoryPlan.md`**：存進歷史的訊息使用**清乾淨的版本**（無標籤、無 think），避免下一輪 context 被污染
- **`Future_Plan.md` User Memory**：think 區塊不送進記憶萃取 — 它是「給觀眾看的演出」，不是事實

---

## 8. 開放問題（待設計階段確認）

1. **think 框是否點擊可隱藏？** — 想看角色內心 vs 不想被劇透內心戲，兩種使用者都會有
2. **多 personality 時是否允許各自關閉 think？** — manifest.json 加 `features.inner_monologue: bool`？
3. **TTS（未來如接 VOICEVOX）的 think 處理** — 確定不朗讀，但是否要播放短「思考音效」？
4. **Mobile 版的 think 框位置** — 螢幕窄時上方擺放可能擋住對話，需另設 layout
5. **`<think>` 出現於回覆中段而非開頭時的處理** — 目前假設只在開頭，若 LLM 寫在中段是切成兩段 main 還是合併？

---

## 9. 驗收標準

> 📌 **最終驗收 gate 以 §13.6 + §15.1（CODEX §14.2.4 共識）為準**。本節列出的是功能面驗收（使用者可感知的行為），§13.6 列出的是契約面驗收（Normalizer / SSE chunk boundary 等內部不變量）。兩者皆須通過。

### Phase 1（功能面）
- [ ] Frieren 在 10 次連續對話中至少使用 5 次內嵌標籤（如 `[thinking]`、`[laugh]`）
- [ ] 標籤 100% 不出現在使用者可見的對話框中
- [ ] 串流模式下，標籤對應表情切換的延遲 ≤ 300ms
- [ ] 現有「自動關鍵字偵測」對「我沒有生氣」這類負面句的誤判率下降 ≥ 80%（透過 LLM 主動標註覆蓋）
- [ ] Markdown 連結 `[text](url)` 不被破壞
- [ ] 非角色 supported 清單內的中括號文字（如 `[WordPress]`、`[TODO]`）**原樣保留**於 display_text 中（§15.2.1）

### Phase 2（功能面）
- [ ] `<think>` 內容於專屬對話框渲染，與主對話框視覺明顯區隔
- [ ] think 區塊不進入 chat history、不參與 checksum、不被 TTS 朗讀
- [ ] admin 可一鍵全域關閉內心獨白功能
- [ ] 三家主流 provider（Gemini / OpenAI / Claude）均能正確產生 `<think>` 區塊（透過 prompt 引導）
- [ ] **僅渲染回覆開頭的 `<think>`**；中段 `<think>` 剝離後僅寫入 warning log，不渲染 bubble（§15.2.2）

### 契約面 gate
→ 完整契約測試清單見 §13.6。實作時 §13.6 為**先決條件**，§9 功能面為**完成條件**。

---

## 10. 後續延伸（不在本計畫範圍）

- **動作標籤** `[bow]`、`[wave]` 對應 Canvas 動畫（已有 `ukagaka-anime.js`）
- **語氣標籤** `[whisper]`、`[shout]` 控制對話框視覺強度（字體大小、抖動）
- 真正的 **Live2D 接入**（model3.json + pixi-live2d-display）— 體積與授權成本高，留到 v3.0.0 討論
- **解除單 tag 政策**（v3.0.0 Live2D 後）：當 shell 從 APNG 換成 Live2D，因 Cubism 引擎支援 motion blending（多 motion 可平滑連續切換、無動畫斷裂），可解除 §3.1 / §13.2 規則 4 的「單 tag 渲染」限制，重啟 Open-LLM-VTuber 風格的多 tag 內嵌（如 `[thinking] 嗯…[notice] 等等`）。屆時需同步調整：
  - §3.1 prompt 改回鼓勵多 tag、句中切換
  - 前端 emotion event handler 移除「只 honor 第一個」邏輯
  - §13.2 規則 4 的單 tag 註記移除
  - **mechanism 層 (normalizer / parser / SSE) 無需任何改動** — 此即 §3.1「policy / mechanism 分離」設計的回收價值

---

## 11. 公司 Antigravity 評審意見與反饋（2026-05-29 中午）

### 11.1 技術架構建議
1. **SSE 串流表情標籤過濾優化（前綴白名單匹配）**：
   - 針對 §3.4，不建議使用固定的 20ms timeout（網路波動或慢速生成下容易碎裂）。
   - 建議前端/後端串流解析器改用**前綴白名單匹配策略**：當緩衝區遇到 `[` 時，檢查內容是否為 supported 列表中任意標籤的前綴（如 `[la` 是 `[laugh]` 的前綴）。
   - 若是，則繼續等待與緩衝；一旦緩衝內容「不再是任何合法標籤的前綴」（如 `[WordPress`），代表是正常文字或 markdown 連結，立即將緩衝內容 flush 輸出，如此可將非標籤內容的顯示延遲減到最低。

2. **`<think>` 區塊漸進式渲染**：
   - 針對 §4.4，若是採用 Reasoning 模型（如 DeepSeek-R1），思考過程有時長度不可控，完全緩衝至 `</think>` 結束可能導致 UI 長時間靜止（看似當機）。
   - 建議改用**漸進式渲染**：前端一邊接收 think block 的 delta 流，一邊實時追加顯示在 `#ukagaka_think` 框內（可加上打字游標或微弱呼吸動畫）。

3. **Markdown 連結排除優化**：
   - 在 `emotion-tag-parser.php` 中進行標籤過濾時，正則表達式應使用預查（Lookahead）以排除緊鄰 `(` 的中括號（例如 `\[(?!.*\]\()(.*?)\]`），確保 `[text](url)` 不會因為串流延遲而遭到表情解析器損毀。

### 11.2 開放問題決策建議（對應 §8）
- **Q1（think 框點擊隱藏）**：建議支援**點擊隱藏（單次）**與設定中全域關閉。
- **Q2（多 personality 分別開關）**：強烈建議在 `manifest.json` 中配置 `features.inner_monologue: bool` 以適應不同性格的角色特徵。
- **Q3（TTS 對於 think 處理）**：保持完全靜音（TTS 不朗讀），不額外添加思考音效，以符合「內心獨白」的靜默屬性。
- **Q4（Mobile 版排版）**：在窄螢幕下，建議將 think 框合併至主對話框內部最上方（以斜體、淡灰色及虛線分隔），避免遮擋角色本體。

---

## 12. 公司 CODEX 評審意見與反饋（2026-05-29 下午）

### 12.1 整體判斷

方向正確，值得做。Open-LLM-VTuber 的重點不是單純「讓 LLM 寫標籤」，而是把輸出分成三層：

1. **display text**：給使用者看的乾淨文字。
2. **TTS text**：可朗讀文字，必須排除 emotion tag 與 think block。
3. **actions / metadata**：表情、動作、think 顯示等非語音訊號。

mp-ukagaka 目前已有 checksum、history、SSE、provider factory 與 loop guard，這次改動應優先建立同一個「回應正規化層」，避免 REST 非串流、SSE 串流、dialog、history store 各自清洗一次，最後產生 checksum 或畫面不一致。

### 12.2 架構建議

1. **新增 Response Normalizer，而不是只新增 parser**
   - `emotion-tag-parser.php` 與 `think-block-parser.php` 可以存在，但建議再包一層統一入口，例如 `includes/llm/response-normalizer.php`。
   - 建議公開 API：
     ```php
     function mpu_normalize_ai_response($text, $personality_id = null, array $options = []): array;
     ```
   - **歷史註記**：下方早期範例中的 `primary_emotion` 已由最終決議 §13.2 拆分為 `primary_emotion_tag` / `primary_emotion_file`，實作以 §13.2 為準。
   - 回傳至少包含：
     ```php
     [
       'display_text' => '',
       'tts_text' => '',
       'history_text' => '',
       'checksum_text' => '',
       'think' => '',
       'emotion_tags' => [],
       'emotion_files' => [],
       'primary_emotion' => null,
     ]
     ```
   - 原則：`display_text`、`history_text`、`checksum_text` 預設相同且都已剝離 tag / think；未來若 display 想保留某些舞台效果，也要明確分欄，不要靠呼叫端自己猜。

2. **SSE 串流需要獨立狀態機**
   - Antigravity 的前綴白名單策略是對的，但建議不要散寫在 `class-mpu-rest-chat.php`。
   - 建議新增 `includes/llm/stream-output-parser.php`，用 request-scoped object / array state 處理：
     - `text`
     - `emotion_candidate`
     - `think_opening_candidate`
     - `inside_think`
     - `think_buffer`
     - `pending_plain_text`
   - 這樣 OpenAI / Gemini / Claude / Ollama 的 provider streaming callback 只管 emit 原始 delta，REST controller 統一轉成 `delta`、`emotion`、`think`、`status`。

3. **`<think>` 建議支援兩種渲染模式**
   - 基礎模式：等 `</think>` 後一次 emit，容易實作、畫面穩。
   - 漸進模式：進入 think 後即時 emit `think_delta`，適合 reasoning 模型或長思考。
   - 設定上可先預設「一次 emit」，但 parser 設計不要封死；事件型別可預留：
     ```text
     status: {type: "thinking_start"}
     think_delta: {text: "..."}
     think: {text: "...", final: true}
     status: {type: "thinking_end"}
     ```

4. **不要只用正則處理 markdown 與標籤**
   - 非串流完整文字可以用白名單 regex 快速處理。
   - 串流場景應用小型 tokenizer / state machine。Markdown link、短碼、陣列文字、教學內容都可能包含 `[]`，只靠 lookahead 容易漏掉跨 chunk 情境。
   - 表情 tag 的合法格式建議限制為 `/^\[([a-z][a-z0-9_-]{0,31})\]$/i`，且必須在角色 supported 清單內才移除。

### 12.3 與現有系統的整合注意事項

1. **checksum 必須只看 normalized history text**
   - 計畫 §2.3 的方向正確。建議驗收時加一個明確案例：同一段 AI 原文包含 `[laugh]` 與 `<think>`，前端回傳歷史時只帶乾淨文字，下一輪 checksum 不應 mismatch。

2. **loop guard / memory extraction 不應看到 think**
   - think 是演出層，不是事實層。若未來要做角色長期記憶，`think` 不應進入 memory candidate。
   - 但 debug log 可以保留 think 摘要，方便排查 prompt 是否過度產生內心戲。

3. **顯式標籤應優先於關鍵字偵測，但不要完全關閉 fallback**
   - 建議優先序：
     1. 合法 `[tag]`
     2. 合法 `[表情: xxx]`
     3. 現有 keyword scorer
     4. default / keep current expression
   - 若文字中出現合法 tag，keyword scorer 不應再覆蓋 primary emotion，否則會回到「沒有生氣」誤判問題。

4. **多標籤的語意要先定義**
   - Open-LLM-VTuber 可送出 expression list，但 mp-ukagaka 目前多半是一張圖。建議先定義：
     - `primary_emotion`：第一個合法 tag，用於目前表情圖。
     - `emotion_tags`：完整保留，供未來動作或調試使用。
   - 不建議第一版做「一句話中途多次換圖」；SSE 可以先支援即時 emotion event，但非串流仍以 primary 為主，降低前端複雜度。

### 12.4 UI / UX 建議

1. **think bubble 不應搶主訊息版面**
   - 桌面版放在主對話框上方可以。
   - Mobile 版建議採 Antigravity 的「合併到主框上緣」方案，但高度要有上限，例如 `max-height: 4.5em; overflow: auto;`，避免長 think 擠掉主對話。

2. **think 應有使用者可控性**
   - 全域關閉：必要。
   - personality feature flag：必要。
   - 單次點擊隱藏：建議做，但不必進 M3；可以放 M4 或後續小版。

3. **不要加思考音效作為預設**
   - 內心獨白的價值是「可看不可聽」。音效會讓靜默訊號變成打擾，也可能和未來 TTS / 角色語音衝突。

### 12.5 建議調整里程碑

原本 M1/M2/M3/M4 可行，但我建議把「統一 normalizer + 測試」提前：

| 階段 | 建議內容 | 理由 |
|------|----------|------|
| **M1a** | `mpu_normalize_ai_response()` + emotion/think 非串流測試 | 先鎖定資料契約，避免後續 SSE 與 UI 反覆改 response shape |
| **M1b** | 非串流 REST chat/dialog 整合 | 快速讓功能可見，風險低 |
| **M2** | SSE state machine + `emotion` event | 串流是難點，應獨立驗收 |
| **M3** | think bubble DOM/CSS + 非串流 think | UI 先穩定，不急著同時做漸進串流 |
| **M4** | `think_delta` / progressive think + per-personality 開關 | 留給較複雜模型與角色差異 |

### 12.6 補充驗收標準

- [ ] `mpu_normalize_ai_response()` 覆蓋以下案例：合法 tag、非法 tag、中文 `[表情: xxx]`、markdown link、巢狀或未閉合 `<think>`、多個 `<think>`。
- [ ] 串流 chunk 切在 `[la` / `ugh]`、`<thi` / `nk>`、`</thi` / `nk>` 時仍能正確輸出。
- [ ] 非法 tag（如 `[evil]`）不切表情，也不應被移除，除非設計明確決定所有未知 tag 都要清掉。
- [ ] `think` 與 emotion tag 不進入 history/checksum/TTS。
- [ ] 有 tag 時 keyword scorer 不覆蓋 primary emotion。
- [ ] 前端收到未知 SSE event 時忽略，不中斷既有 typewriter。

### 12.7 CODEX 結論

這份計畫可以進入實作，但第一個 commit 不建議直接改 UI 或 prompt。應先做「輸出正規化契約」與測試，把 display / TTS / history / checksum / metadata 的邊界固定下來。Open-LLM-VTuber 的真正可取之處是 pipeline 分層；mp-ukagaka 若先把這層做好，後面加表情、內心獨白、動作標籤與語氣標籤都會順。

---

## 13. 整合決議（2026-05-29 收斂）

兩家公司意見高度互補：**Antigravity 偏重「具體策略」**（前綴白名單、漸進渲染、Mobile fallback），**CODEX 偏重「架構契約」**（先建 normalizer、SSE state machine、checksum 邊界）。以下逐條決議。

### 13.1 採納的核心調整

| 來源 | 建議 | 決議 | 落地於本計畫的章節 |
|------|------|------|-----------------|
| CODEX §12.2.1 | 先建 `mpu_normalize_ai_response()` 統一回應契約 | ✅ **完全採納**，提升為 M1a 第一里程碑 | §13.2 / §13.6 |
| Antigravity §11.1.1 + CODEX §12.2.2 | SSE 用「前綴白名單匹配」取代固定 timeout | ✅ **完全採納**，§3.4 改寫 | §13.3 |
| CODEX §12.2.2 | SSE state machine 抽成獨立檔 `stream-output-parser.php` | ✅ **完全採納** | §13.3 |
| Antigravity §11.1.2 + CODEX §12.2.3 | think 支援漸進渲染 | ✅ **採納為「可選模式」**，預設仍為一次 emit | §13.5 |
| CODEX §12.2.4 | 表情 tag 用小型 tokenizer + 嚴格 regex (`/^\[([a-z][a-z0-9_-]{0,31})\]$/i`) + supported 白名單 | ✅ **完全採納** | §13.3 / §3.2 |
| CODEX §12.3.3 | 顯式 tag 出現時，keyword scorer 不再覆蓋 primary emotion | ✅ **完全採納**（這正是要解決的核心問題） | §13.6 驗收 |
| CODEX §12.3.4 / §14.3.4 | 區分 `primary_emotion_tag` / `primary_emotion_file` vs `emotion_tags`（完整列表）；第一版不做「一句話多次換圖」 | ✅ **完全採納** | §13.2 / §13.6 |
| Antigravity §11.2 Q1 + CODEX §12.4.2 | think 框支援「點擊單次隱藏 + 全域關閉」 | ✅ **採納**，點擊隱藏放 M4 | §4.1 已更新 |
| Antigravity §11.2 Q2 + CODEX §12.4.2 | `manifest.json` 加 `features.inner_monologue` | ✅ **採納** | §13.6 |
| Antigravity §11.2 Q3 + CODEX §12.4.3 | think 不加思考音效 | ✅ **採納** | §8 Q3 解決 |
| Antigravity §11.2 Q4 + CODEX §12.4.1 | Mobile 版 think 合併至主框上緣，加 `max-height` | ❌ **不採納** — 外掛在 `wp_is_mobile()` 為真時不渲染（[frontend-functions.php:155](../includes/core/frontend-functions.php#L155)），手機環境不在支援範圍 | — |
| CODEX §12.5 | 把 normalizer 抽出為 M1a，里程碑微調 | ✅ **採納** | §13.7 |

### 13.2 新增：Response Normalizer 契約（M1a）

**新增檔案**：`includes/llm/response-normalizer.php`

這層是後續所有改動的**單一資料契約來源**。emotion-tag-parser 與 think-block-parser 都是它的內部依賴，呼叫端永遠走這個入口：

```php
/**
 * 將 AI 原始回應轉換為各通路所需的乾淨版本與 metadata。
 *
 * @param string $raw_text       AI 原始輸出
 * @param string|null $personality_id
 * @param array $options {
 *   @type bool $progressive_think  漸進 think 模式（預設 false）
 *   @type bool $strip_unknown_tags 未知標籤是否剝離（預設 false，**保留**）
 *                                  — 改動歷史：原預設 true，依 §14.3.1 / §15.2.1 改為 false
 *                                  以避免誤刪 [WordPress]、[TODO] 等正常文字
 * }
 * @return array{
 *   display_text: string,           // 給使用者看的（已剝離合法 tag 與 think；未知 tag 原樣保留）
 *   tts_text: string,               // TTS 用（同 display，但保留可選的口語潤飾空間）
 *   history_text: string,           // 寫入 chat history（同 display）
 *   checksum_text: string,          // 計算 checksum（同 display，鎖死防止漂移）
 *   think: string,                  // <think> 內容，空字串表示無；僅取「開頭」<think>
 *   emotion_tags: string[],         // 全部合法標籤，依出現順序
 *   emotion_files: string[],        // 對應檔名
 *   primary_emotion_tag: ?string,   // 第一個合法 tag 名（如 'laugh'）— 給 debug log / metadata
 *   primary_emotion_file: ?string,  // 第一個合法 tag 的檔名（如 'laugh.png'）— 給前端換圖
 * }
 */
function mpu_normalize_ai_response(
  string $raw_text,
  ?string $personality_id = null,
  array $options = []
): array;
```

**契約規則**（鎖死，後續任何改動必須相容）：
1. `display_text` / `history_text` / `checksum_text` **永遠相等**。若未來 display 想加舞台效果（例如保留某些動作描寫），須**新增欄位**而非分歧三者。
2. `tts_text` 預設等於 `display_text`，但語意上保留將來「TTS 專屬預處理」（如 Open-LLM-VTuber 的 tts_preprocessor_config）的擴充空間。
3. `think` 為空字串時，前端應完全不渲染 bubble（不顯空白氣球）。
4. `primary_emotion_file` 為 `null` 時，前端維持當前表情（不換圖、不退回 default）。`primary_emotion_tag` 與 `primary_emotion_file` 必須同步存在或同步為 null。
   - **單 tag 渲染政策**（因應 APNG 動畫特性）：第一版前端**只使用 `primary_emotion_file`** 切換表情，後續 `emotion_tags` 中的其他 tag 不觸發視覺變更（避免打斷 laugh.png 23-frame infinite loop 這類長動畫）。`emotion_tags` / `emotion_files` 陣列完整保留供 debug log、metadata 與未來 Live2D 多 motion 擴充使用 — schema 不為單 tag 政策卡死，未來 v3.0.0 可僅改前端 honor 邏輯即可放寬（§3.1 / §10）。
5. **未知標籤預設保留**（§15.2.1）：只有匹配 `/^\[([a-z][a-z0-9_-]{0,31})\]$/i` **且** 在角色 `supported` 清單內的 tag 才會被剝離；其餘中括號文字原樣保留。這避免 `[WordPress]`、`[TODO]` 這類正常用字被誤刪。
6. **中段 `<think>` 不渲染**（§15.2.2）：僅抽取回覆**最開頭**的 `<think>...</think>`；若 LLM 在中段插入 `<think>`，parser 將其剝離並寫入 warning log，但**不**回傳至 `think` 欄位、也**不**渲染 bubble。
7. **checksum sanitize 時點鎖死**（§15.2.3）：`checksum_text` 僅可透過既有 `chat-integrity` 的正規化邏輯（如 `sanitize_textarea_field()`）做進一步處理，REST controller **不得**另行二次清洗。確保前端回傳的歷史 checksum 永遠對得上。
8. **`enable_inner_monologue` 全域開關語意**：新增跨 provider 的 UI 演出層 option `enable_inner_monologue`，預設 `true`，且**不得沿用**既有 `ollama_disable_thinking`。當 `enable_inner_monologue === false` 時：
   - system prompt **不注入** §4.5 的 `<think>` 內心獨白引導。
   - normalizer 仍會剝離 `<think>`，但 `think` 欄位固定回傳空字串。
   - SSE parser 不 emit `think` / `think_delta` / `thinking_start` / `thinking_end` 給前端。
   - `ollama_disable_thinking` 維持原職責：只控制 Ollama / Qwen3 / DeepSeek 等原生 reasoning 模型的 `/no_think` 與 `think` request 參數。

### 13.3 §3.4 改寫：前綴白名單 + 獨立狀態機

**原方案**（固定 20 chars / 20ms timeout）→ **廢棄**。

**新方案**：新增 `includes/llm/stream-output-parser.php`，提供 request-scoped 的串流解析狀態機：

```php
class MPU_Stream_Output_Parser {
    private array $supported_tags;          // 角色 supported 清單，預先載入
    private string $pending_buffer = '';    // 不確定是否為 tag 的緩衝
    private string $think_buffer = '';
    private string $state = 'normal';       // normal | maybe_tag | inside_think | maybe_think_open | maybe_think_close

    public function feed(string $delta): array;   // 回傳要 emit 的事件陣列
    
    /**
     * 串流結束時清空緩衝（Defense A）。
     * 若串流結束時仍處於 `inside_think` 狀態且未收到 `</think>`（因 max_tokens 截斷），
     * `flush()` 必須將 `think_buffer` 的緩衝內容作為最後一個 `think` 事件 emit 出去，
     * 並正常觸發 `thinking_end` 狀態，避免遺失已生成的思考內容。
     */
    public function flush(): array;
}
```

**Parser 不做什麼（明文拒絕清單 - Defense B 決議）**：
- **拒絕孤立 `</think>` 反向追溯**：當狀態機處於 `normal` 狀態而意外收到 `</think>` 時，**絕不**反向追溯前方已透過 SSE 發送的文字將其歸為 think。這會違反 SSE 的 append-only 原則並導致前端複雜度爆炸。
- **處理方式**：遇到孤立的 `</think>` 直接將其作為普通文字 delta 輸出，並記錄 warning log。R1 等模型的格式問題由 Prompt 規則（§4.5 規則）收斂，parser 絕不猜測模型意圖。


**前綴白名單匹配邏輯**（採納 Antigravity §11.1.1）：

> **單 tag 政策註記**：Parser / SSE mechanism 層仍會依序 emit 所有合法 `emotion` event；v2.x 前端依 §13.2 規則 4 只 honor 第一個 `emotion` event，後續 event 僅 debug log，不觸發 `<img>` 切換。這讓 v3.0.0 Live2D 後可只放寬前端政策，不改 parser / SSE 契約。

```
收到 delta「[la」
  → state = maybe_tag, pending_buffer = "[la"
  → 檢查：是否為 supported 任一 tag 的前綴？
    - "[laugh" 開頭 ✓  → 繼續緩衝，不 emit
  → 等下一個 delta

收到 delta「ugh] 這個」
  → pending_buffer = "[laugh] 這個"
  → 偵測到完整 "[laugh]" → emit:
    - emotion: {tag: "laugh", file: "laugh.png"}
  → 剩餘「 這個」回到 normal state，emit:
    - delta: {text: " 這個"}

收到 delta「[Word」
  → state = maybe_tag, pending_buffer = "[Word"
  → 檢查：是否為任一 supported tag 前綴？❌
  → 立即 flush："[Word" emit 為 delta，回到 normal
```

**Markdown link 保護**（採納 CODEX §12.2.4）：
- tag 合法格式硬性限制 `/^\[([a-z][a-z0-9_-]{0,31})\]$/i`
- `[Hello World](https://...)` 中的 `[Hello World]` → 「[H」開頭立即不符合任何 supported tag 前綴 → 立即 flush，不會誤判
- 同時補上 Antigravity §11.1.3 的 lookahead `\[(?!.*\]\()` 作為非串流時的二重保險

**Provider 端職責簡化**（CODEX §12.2.2）：
- OpenAI / Gemini / Claude / Ollama 的 stream callback **只負責 emit 原始 delta**
- 統一由 `MPU_REST_Chat` 接 parser，emit `delta` / `emotion` / `think` / `status` 標準事件
- → 新增 provider 時不必各自寫過濾邏輯

### 13.4 開放問題 §8 完整收斂

| Q | 原問題 | 決議 |
|---|--------|------|
| Q1 | think 框是否點擊可隱藏？ | ✅ **支援單次點擊隱藏 + admin 全域開關**。點擊隱藏放 M4，全域開關放 M3 |
| Q2 | 多 personality 各自關閉？ | ✅ **在 `manifest.json` 新增 `features.inner_monologue: bool`**，預設 `true` |
| Q3 | TTS think 處理 | ✅ **完全靜音，不加思考音效**（兩家公司一致） |
| Q4 | Mobile 版位置 | ❌ **不適用** — `mpu_is_show_page()` 在 `wp_is_mobile()` 為真時 return false（[frontend-functions.php:155](../includes/core/frontend-functions.php#L155)），整個外掛在手機環境不渲染，無需任何 mobile 排版 |
| Q5 | `<think>` 出現於中段 | ✅ **第一版只渲染回覆開頭的 `<think>`**（§15.2.2 修正）。中段 `<think>` 由 parser **剝離並寫入 warning log，不渲染** bubble — 避免「主回答顯示到一半 think 才補上」的時序錯位。中段出現屬模型行為偏差，靠 prompt 規則（§4.5「最多一段」）收斂 |
| Q6 | 是否沿用既有 `ollama_disable_thinking` 控制 `<think>` bubble？ | ❌ **不沿用**。`ollama_disable_thinking` 是 Ollama 原生 reasoning 引擎層開關；新的 `<think>` bubble 是跨 provider UI 演出層，新增 `enable_inner_monologue` option 控制。兩者可以同時存在並表達不同組合：例如保留 Qwen3 reasoning，但關閉 Frieren 內心戲 bubble |

### 13.5 think 渲染雙模式（採納 CODEX §12.2.3）

預設「一次 emit」，但 parser 與 SSE 事件型別預留漸進模式擴充。

**SSE 事件規格**（鎖定）：
```text
# 基礎模式（預設）
status:      {type: "thinking_start"}
think:       {text: "...", final: true}
status:      {type: "thinking_end"}

# 漸進模式（admin 可開啟）
status:      {type: "thinking_start"}
think_delta: {text: "嗯…"}
think_delta: {text: "這個問題"}
think:       {text: "嗯…這個問題還真不好回答", final: true}
status:      {type: "thinking_end"}
```

**前端處理原則**：
- 未知 event type → 忽略不中斷（CODEX §12.6）
- `think_delta` 未實作時，前端只看 `think` final event，行為等同基礎模式
- → 後端先行漸進，前端後續才實作，不會造成相容性問題
- **漸進式 `think_delta` 無定時器設計**（Antigravity §15.3.1）：前端在接收到 `think_delta` 時，不啟動打字機效果，而是直接採用純文字拼接方式更新 DOM（`thinkElement.textContent += data.text`）。只有在收到 `status: thinking_end`（或第一個主對話 `delta`）後，才啟動主對話框的打字機定時器（Typewriter Timer），完美規避雙定時器同時運行的衝突。


### 13.6 驗收標準擴充（合併 CODEX §12.6）

於 §9 既有驗收外，新增：

**Normalizer 契約測試（M1a 必過）**：
- [ ] `mpu_normalize_ai_response()` 覆蓋以下案例並輸出符合契約：
  - [ ] 純文字、無任何標籤
  - [ ] 合法 tag（單個 / 多個 / 句中內嵌）→ 剝離且填入 `emotion_tags` / `primary_emotion_tag` / `primary_emotion_file`
  - [ ] **未知 tag（如 `[evil]`、`[WordPress]`、`[TODO]`、`[A]`）→ 預設保留於 `display_text`，不切表情**（§15.2.1）
  - [ ] 中文 `[表情: xxx]` 向下相容
  - [ ] Markdown link `[text](url)` 不被破壞
  - [ ] 未閉合 `<think>`（模型截斷）→ 整段視為 think 或丟棄（依 `mpu_filter_thinking_content` 既有行為）
  - [ ] 多個 `<think>` → 只取第一個，其餘剝離並寫 warning log
  - [ ] **中段 `<think>` → 剝離 + warning log，`think` 欄位回傳空字串**（§15.2.2）
  - [ ] 同時包含 `[laugh]` 與 `<think>` 的混合情境
  - [ ] `primary_emotion_tag` 與 `primary_emotion_file` 必須同步：兩者同時為 null，或同時為對應的 tag 名與檔名（§13.2 規則 4）

**契約一致性**：
- [ ] `display_text === history_text === checksum_text`（鎖死）
- [ ] `checksum_text` 不經 REST controller 二次清洗（§15.2.3）
- [ ] 有合法 tag 時，keyword scorer 不再覆蓋 `primary_emotion_file`
- [ ] `think` 不出現於 chat history、checksum、TTS、memory extraction

**APNG 動畫保護（單 tag 政策驗收）**：
- [ ] **多 tag 容錯**：LLM 違規輸出 `[laugh] xxx [angry] yyy` 時，normalizer 仍正確抽出 `emotion_tags: ['laugh', 'angry']`，但 `primary_emotion_*` 只指向第一個 (`laugh`)
- [ ] **前端只 honor 第一個 emotion event**：SSE 串流中前端收到第二個 `emotion` 事件時，僅寫入 `console.debug` 提示 LLM 違規，**不**觸發 `<img src>` 變更，避免打斷當前 APNG 播放
- [ ] **Prompt 服從度**：Frieren 在 100 次連續回應的抽樣中，產生 ≤ 1 個表情標籤的比例 ≥ 90%（若 < 90% 須回頭加強 §3.1 prompt）
- [ ] APNG 動畫不被中途打斷：肉眼測試 10 次連續對話，無視覺破碎感（如 laugh 動畫播到一半被切走）

**SSE 切 chunk 邊界測試（M2 必過）**：
- [ ] chunk 切在 `[la` / `ugh]` 仍正確 emit `emotion` 事件
- [ ] chunk 切在 `<thi` / `nk>` 仍正確進入 think state
- [ ] chunk 切在 `</thi` / `nk>` 仍正確結束 think state
- [ ] chunk 切在 markdown `[link](url)` 的 `[link` 處不會卡住緩衝

**前端容錯**：
- [ ] 收到未知 SSE event type 時忽略，不中斷既有 typewriter

### 13.7 里程碑調整（採納 CODEX §12.5）

當前基線版本：**v2.24.0**。

| 階段 | 內容 | 工數 | 對應版本 | 使用者可見性 |
|------|------|------|---------|------------|
| **M1a** | `mpu_normalize_ai_response()` 契約 + 非串流測試套件 | 1d | **v2.25.0** | 內部架構（無感） |
| **M1b** | 非串流 REST chat/dialog 整合（用 normalizer 統一輸出） | 1d | v2.25.1 *(patch)* | 內部接線（無感） |
| **Prompt 切換** | 改 §3.1 prompt + Frieren `emoji-keywords.json` 範例 | 0.5d | v2.25.2 *(patch)* | **使用者開始看到內嵌 `[tag]` 生效** |
| **M2** | SSE state machine (`stream-output-parser.php`) + `emotion` event | 1.5d | **v2.26.0** | 串流模式表情切換即時化 |
| **M3** | think bubble DOM/CSS（右上方位置）+ 非串流 think + 新增 `enable_inner_monologue` option（不可沿用 `ollama_disable_thinking`）+ manifest `features.inner_monologue` | 1.5d | **v2.27.0** | **內心獨白 bubble 出現** |
| **M4** | `think_delta` 漸進模式 + 單次點擊隱藏 + per-personality 開關前端 UI | 2d | **v2.28.0** | 進階 UX |
| **總計** | | **7.5d** | | |

工數從原 6.5d → 7.5d（含新增的 Prompt 切換 0.5d 步驟）。

**版本切分原則**：
- M1a/M1b 對使用者**完全無感**（normalizer 是新增層、prompt 未動 → LLM 不會主動產生新 tag），故走 patch
- Prompt 切換是「使用者開始看到效果」的分水嶺，但仍歸入 M1 系列的 patch
- M2/M3/M4 各自帶有使用者可感知的新功能，每個都配一個 minor bump
- 此切法跟既有 2.23.x（Observation Buffer 系列 patch fix）、2.22.x 模式一致

**為什麼不一次 v2.25.0 全發**：每個 minor 之間留時間實際使用、收集 prompt 服從度資料、調整角色 emoji-keywords.json 範例。漸進式發佈讓「Frieren 究竟會不會用新標籤」這種模型行為問題能在早期版本捕捉到。

### 13.8 給實作者的順序提醒（CODEX §12.7 精神）

**第一個 commit 不要碰 UI 或 prompt**。順序：

1. 先寫 `response-normalizer.php` + 完整測試（M1a 驗收標準全綠）
2. 用 normalizer 改造非串流路徑（M1b），這時功能對使用者**仍不可見**（沒改 prompt，LLM 不會主動產生新 tag）
3. 改 prompt 模板（§3.1）並更新 Frieren `emoji-keywords.json` 的範例 → 使用者開始看到效果
4. 才進 SSE state machine（M2）
5. 才進 think bubble UI（M3）
6. 最後補 `think_delta` 漸進模式、單次點擊隱藏與 per-personality 前端開關（M4）

這個順序的關鍵是：**契約測試先綠**，後續每個 PR 不會反覆改 response shape，也不會把 checksum 系統打壞。

---

## 14. 公司 CODEX 追評（2026-05-29 晚間）

### 14.1 整體評價

第 13 章已經把前一輪 CODEX 與 Antigravity 的建議收斂成可實作方案，方向比原稿穩很多。特別是：

- `mpu_normalize_ai_response()` 被提升為 M1a，這是正確的第一步。
- SSE parser 從 REST controller 內部邏輯提升為 `stream-output-parser.php`，可避免四個 provider 各自長出一套清洗邏輯。
- think bubble 的位置改成角色頭部右上方，比原本「主框上方」更像角色內心戲，也比較不會和主對話框競爭。
- Mobile 不支援的事實有被納入決議，避免多做一套不會被渲染的 layout。

結論：**可以進入實作規劃**，但實作前建議再清掉以下文件內部矛盾，避免工程師照舊章節做錯。

### 14.2 需要修正的文件矛盾

1. **§3.4 還保留舊的 20 chars / 20ms timeout 方案**
   - 第 13 章已決議「原方案廢棄」，改用前綴白名單 + `MPU_Stream_Output_Parser`。
   - 建議直接把 §3.4 改成引用 §13.3，或在 §3.4 開頭加「本節已由 §13.3 取代」。
   - 否則實作者可能照 §3.4 做固定 timeout，和收斂決議衝突。

2. **§5 舊里程碑仍與 §13.7 新里程碑並存**
   - §5 仍寫 M1/M2/M3/M4、總計 6.5d。
   - §13.7 已改為 M1a/M1b/M2/M3/M4、總計 7d。
   - 建議 §5 改成「歷史原案」或直接替換為 §13.7 的版本，避免版本規劃混亂。

3. **§6 風險表仍提到固定 timeout 緩解**
   - `SSE 緩衝增加感知延遲` 的緩解方式仍是「20 chars、20ms timeout」。
   - 建議改成「前綴白名單匹配 + 非前綴立即 flush + flush() 收尾」。

4. **§9 驗收標準應合併 §13.6**
   - §13.6 的 normalizer / chunk boundary 測試才是目前真正的 gate。
   - 建議把 §13.6 直接併回 §9，或在 §9 開頭寫「最終驗收以 §13.6 擴充項為準」。

### 14.3 需要再定義清楚的技術細節

1. **未知 tag 的策略仍有風險**
   - §13.2 寫 `keep_unknown_tags` 預設 false，§13.6 也寫非法 tag 預設剝離。
   - 這會有誤刪風險：`[WordPress]`、`[TODO]`、`[A]` 這類正常文字可能被當成未知 tag 清掉。
   - CODEX 建議預設改為：**未知 tag 保留在 display_text，但不切表情**。
   - 若真的想清掉 LLM 亂寫的 `[evil]`，建議只清掉「已知 emotion vocabulary 但該角色不支援」的標籤，而不是清掉所有 bracket word。

2. **中段 `<think>` 的處理要更保守**
   - §13.4 Q5 寫「中段出現仍抽出，但前端在主對話一開始顯示」。
   - 這可能造成時序錯位：主回答已經顯示到一半，think 卻被移到開頭。
   - 建議第一版改成：只抽取「開頭 `<think>`」，中段 `<think>` 只做剝離與 log，不渲染 bubble。這更符合 prompt 規則，也降低 UI 行為歧義。

3. **`display_text === history_text === checksum_text` 可以先鎖死，但要明確 sanitize 時點**
   - WordPress 目前 checksum 可能會經過 `sanitize_text_field()` 或其他 normalization。
   - 建議在 normalizer 契約中補一句：checksum 使用 `checksum_text` 經既有 chat-integrity normalization 後的值，不能由 REST controller 再自行清洗一份不同文字。

4. **`primary_emotion` 欄位型別建議分清楚**
   - §13.2 寫 `primary_emotion` 是檔名，但名稱像 tag。
   - 建議改成兩個欄位：
     ```php
     'primary_emotion_tag' => null,
     'primary_emotion_file' => null,
     ```
   - 這樣前端、debug log、REST response 都不會混淆「tag」與「圖片檔」。

### 14.4 建議的最後收斂動作

實作前建議先做一個「文件清理 commit」：

1. 用 §13.3 覆蓋 §3.4。
2. 用 §13.7 覆蓋 §5。
3. 更新 §6 timeout 風險緩解。
4. 把 §13.6 合併回 §9。
5. 決定未知 tag 預設保留或剝離。CODEX 建議預設保留。
6. 決定中段 `<think>` 是否渲染。CODEX 建議第一版不渲染，只剝離與 log。

完成這些後，計畫就足夠清楚，可以直接從 M1a 開始實作。

---

## 15. 整合與收斂決議（公司 Antigravity 最終回饋，2026-05-29 中午）

Antigravity 認同並完全採納 CODEX 在 §14 中提出的六大收斂動作與細節設計。以下為最終落地的技術決議，作為未來 M1a 至 M4 階段實作的最高指引：

### 15.1 文件矛盾清理決議
1. **關於 §3.4、§5、§6、§9 的內容覆蓋**：
   - 實作時，**§3.4 的串流過濾方案**由 **§13.3（前綴白名單匹配）** 替代。
   - **§5 的專案里程碑**由 **§13.7（含 M1a 輸出正規化契約的 5 階段里程碑）** 替代。
   - **§6 中「SSE 緩衝延遲」的緩解方案**，調整為：「前綴白名單匹配，一旦判定非合法標籤前綴即刻 flush 輸出；並在流結束時呼叫 `flush()` 收尾」，徹底封殺固定的時間超時。
   - **§9 的驗收標準**，將 **§13.6 的 Normalizer 契約測試與 SSE 切 chunk 測試** 作為首要的驗收 Gate。

### 15.2 技術細節收斂決議
1. **未知標籤（Unknown Tags）處理策略**：
   - **採納 CODEX 建議**：為了避免誤刪非表情的中括號文字（如 `[WordPress]` 或 `[TODO]`），**未知標籤預設保留在 `display_text` 中，但不換表情**。
   - **過濾白名單邏輯**：表情標籤正則限制為 `/^\[([a-z][a-z0-9_-]{0,31})\]$/i`，且**只有**在當前角色 manifest 宣告的 `supported` 清單內的標籤，才會被判定為「合法標籤」並予以剝離；其餘未知中括號內容原樣保留輸出。

2. **中段 `<think>` 區塊的防護策略**：
   - **採納 CODEX 建議**：第一版（Phase 2）**僅抽取並渲染位於回覆最開頭的 `<think>...</think>`**。
   - 若 LLM 異常地在回覆中段輸出 `<think>` 標籤，解析器將對其進行**剝離並記錄警告 Log**，但**不渲染**為獨立的 think bubble，以防止前端發生時間軸與語意錯亂。

3. **Checksum 與 Sanitize 時點的鎖定**：
   - 在 `mpu_normalize_ai_response()` 輸出中，`checksum_text` 必須與 `display_text` / `history_text` 保持嚴格一致。
   - 該 `checksum_text` 僅會送交既有的 `chat-integrity` 機制進行與資料庫寫入時一致的文本正規化（如 `sanitize_textarea_field()`），REST controller 不得再在此基礎上額外進行可能導致 checksum 不一致的二次清洗。

4. **欄位命名規範**：
   - 將原 §13.2 契約中的 `primary_emotion` 拆分為：
     - `primary_emotion_tag`: 合法標籤名（如 `'laugh'`）
     - `primary_emotion_file`: 對應圖片檔名（如 `'laugh.png'`）
   - 前端與 REST JSON 回應統一依此命名，避免語意混淆。

### 15.3 實作加強與優化細節（Antigravity 建議與最終收斂）
1. **漸進式 Think 渲染的「無 Timer 衝突」設計**：
   - 為了落實 §13.5 的 `think_delta` 漸進渲染，且不與主對話框的打字機效果（Typewriter Timer）產生衝突，前端 `ukagaka-chat.js` 在接收 `think_delta` 時，應**直接以字串拼接方式**更新 DOM 內容（即 `thinkElement.textContent += data.text`），而不啟用任何定時器。
   - 只有當收到 `status: thinking_end`（或第一個 `delta` 主文字）後，才啟動主對話框的打字機定時器（Typewriter Timer）。這能完美解決多 timer 衝突、複雜度高的痛點（此條已寫入 §13.5 前端處理原則）。

2. **Ollama / DeepSeek-R1 的思考格式相容性（開頭無 `<think>` 標籤的防禦與收斂）**：
   - 部分 local Reasoning 模型在串流輸出時，有時會發生截斷（未閉合 `</think>`）或遺漏 `<think>` 的狀況。
     - **防禦 A（未閉合 - 接受）**：若串流在 `inside_think` 狀態下結束而沒有收到 `</think>`（因 max_tokens 截斷），在呼叫 `flush()` 時，必須將 `think_buffer` 中的所有內容作為最後一個 `think` 事件 emit 出去，並正常觸發 `thinking_end` 狀態（此條已寫入 §13.3 MPU_Stream_Output_Parser::flush() 契約）。
     - **防禦 B（無開頭 - 明文拒絕）**：若在 `normal` 狀態下意外收到孤立的 `</think>`，**禁止**進行反向追溯（即不把前方已輸出的文字重新歸為 think 框）。這會破壞 SSE append-only 原則並導致前端複雜度爆炸。處理方式是一律當作普通文字 delta 輸出並寫入 warning log。R1 等模型的格式問題由 Prompt 規則（§4.5 規則）限制，parser 絕不猜測模型意圖（此條已寫入 §13.3 拒絕清單）。

3. **Loop Guard 運作與 Think 區塊的精準隔離**：
   - 經檢查，既有的 [tool-loop-guard.php](file:///c:/D/php/mp-ukagaka/includes/llm/tool-loop-guard.php#L76-L80) 是基於「工具名稱與參數雜湊值」比對，並不直接校驗 LLM 的原始文本。
   - 因此，此處隔離的真正意思是：**確保 Response Normalizer 在分離出 `<think>` 內容與 Tool Call JSON 後，只將結構化的 Tool Call 資訊送入 Loop Guard 判定，避免 think 區塊中可能包含的 `{}` 符號干擾 JSON 語法偵測**（此條已更新至 §7 與既有 plan 的關聯）。

---

## 16. 系統 Placeholder（「えっと…」等待提示）與 LLM `<think>` 的邊界收斂（2026-05-29 H + CODEX + Antigravity 第三輪 + Claude 校正）

### 16.1 背景與校正

第三輪評審中 CODEX 與 Antigravity 一致指出：`（えっと…何を話せばいいかな…）`、`（思考中…）`、飾品點擊時的 `（…えっと<span class="mpu-thinking"></span>）` **不是 LLM 動態回應、也不是角色內心戲**，而是「等 AJAX/SSE 回應期間的前端 system placeholder」。

Claude 在前一輪曾誤判此字串為「LLM 自然產出的圓括號嘀咕」並提出「CSS murmur span 淡化」方案 — **此方案無效並作廢**，因為：
- `dialogs/Frieren.txt` / `ghost/Frieren/touchzones.json` 全文檢查後，**無**圓括號嘀咕的固定範例
- 實際 grep 確認字串硬編碼在 9 處：`frontend-functions.php:120,123,972,977` / `ukagaka-base.js:568-570` / `ukagaka-chat.js:581,819,836,859` / `ghost/Frieren/frieren.js:1230,1237,1529,1536`
- LLM 完全沒機會「自然產出」這些字串 — 它們在 AJAX request 發出時就已經顯示了

此章節為兩家公司觀點的合併收斂，以及 placeholder 與 `<think>` bubble 的邊界鎖定。

### 16.2 已修的歷史遺毒（「を」字缺失）

#### 16.2.1 漏字路徑

| 檔案位置 | msgid / source | 實際內容 | 狀態 |
|---|---|---|---|
| `frontend-functions.php:120` | source | `何を話せば`（有「を」） | ✅ 正確 |
| `mp-ukagaka.po` | msgid | `何を話せば` | ✅ 正確 |
| `mp-ukagaka-en_US.po` | msgstr | `(Um... what should I talk about...)` | ✅ 正確 |
| `mp-ukagaka-ja.po` | msgstr | 空（直接用 msgid） | ✅ 正確 |
| `mp-ukagaka-zh_TW.po:1623` | msgstr | `何話せば`（**漏「を」**） | ❌ 翻譯 typo |
| `ukagaka-base.js:568` | hardcoded | `何話せば`（**漏「を」**） | ❌ 源頭推測：從中文環境複製貼來 |

#### 16.2.2 失效機制

`ukagaka-base.js:567-579` 用 `systemMessages.some(msg => plainText.indexOf(msg) !== -1)` 判斷是否為系統訊息以決定是否跳過角色動畫。因為 JS 字串對齊到 zh_TW 的 typo msgstr 而非 msgid：
- 中文 WordPress 環境：渲染後是 typo msgstr「無を」版本 → JS 比對中 → 動畫**確實跳過** ✅
- 日文 / 英文 / 其他語系：渲染後是 msgid「有を」版本（en_US 是英文翻譯，但不在 JS 黑名單內） → JS 比對失敗 → 動畫**錯誤地播放** ❌

此 bug 在中文環境**沉默生效**、其他語系**沉默失效**，因此長期沒被發現。

#### 16.2.3 已執行的修正

- `js/ukagaka-base.js:568`：補「を」 → `何を話せば`
- `languages/mp-ukagaka-zh_TW.po:1623`：msgstr 補「を」 → `何を話せば`
- 重新編譯全部 4 個 `.mo` 檔（en_US / ja / zh_TW / 模板）

但這只是**治標**。治本見 §16.3 縫隙 A — 字串內容比對是脆弱設計，應改為標記式判定。

### 16.3 五個縫隙（Claude 校正補完）

#### A. `ukagaka-base.js:567-579` 字串黑名單應改為標記式判定（pre-M1a 獨立 tech debt）

當前用 `systemMessages.some(msg => indexOf(msg))` 字串內容比對來決定是否跳過角色動畫，問題：
1. 字串改動需要同步 9 處 + 4 個 .po 檔，極易再次漂移
2. 翻譯後字串會跟 JS 黑名單脫節（§16.2 即此案例）
3. 將來 §16.3-C 個性化 placeholder 後，每個角色都有自己的字串，黑名單機制完全無法擴展

改法：
```js
// 不再比對字串內容，改看 DOM attribute 或函式參數 flag
mpu_typewriter(text, "#ukagaka_msg", null, { systemPlaceholder: true });
// 或：
$msg.attr('data-mpu-placeholder', 'system').html(text);
// typewriter 內部讀 attribute / option 來決定動畫行為
```

**這條跟 think bubble 架構無關，應該 pre-M1a 獨立處理**。否則 M3 上線時要同時對付「字串黑名單失效 + 個性化 + 視覺重構」三件事疊在一起，風險爆炸。

#### B. placeholder → LLM `<think>` 採「替換」而非「接續」（CODEX 對，Antigravity 錯）

兩家公司在 placeholder 與 LLM think_delta 流動方式上有隱藏衝突：
- **CODEX**：「主對話框保留空白或舊內容，等正式回覆回來再更新」→ placeholder 用完**清掉**，llm `<think>` 從零開始顯示
- **Antigravity**：「Placeholder 會流暢地轉化為：💭 （…えっと…**這個飾品是…**…）」→ placeholder 字串**被 think_delta 接續**

**決議：採 CODEX 替換方案**。理由：
1. 「えっと」字串混入 LLM 內心戲的 debug log，違反 §13.2 normalizer 契約的「`source` 邊界明確」原則
2. 多數回應 LLM **不會寫** `<think>`（只在表達內心活動時主動寫），placeholder 卡在 bubble 沒有 think_delta 來接續 → 變成 UI 殘留
3. 視覺整合目標仍可達成：**bubble DOM 容器共用，但生命週期切開**

#### C. `manifest.json` i18n 字串只給純文字，HTML wrapper 由前端 template 加（第一版鎖定單字串）

Antigravity 的 manifest i18n 結構（§16.4）會被誤用為：
```json
"thinking_placeholder": "（…えっと<span class=\"mpu-thinking\"></span>）"
```
這把三點動畫 HTML 混進 i18n 字串。問題：ghost 作者要學會什麼 class 能用、什麼會被清洗，門檻高且容易意外。

改法：拆兩層
```json
// manifest.json — 只有純文字
"i18n": {
  "ja": { "thinking_placeholder": "（…えっと…）" },
  "zh-TW": { "thinking_placeholder": "（嗯…讓我想想…）" },
  "en": { "thinking_placeholder": "(...let me think...)" }
}
```
前端 wrapper template 負責加 `<span class="mpu-thinking"></span>` 三點動畫。

**第一版範圍鎖定**：只支援**單一字串**，**不支援**像 `sleeping_messages` 那種隨機池結構。否則「思考口頭禪該不該隨機」會擴張成下一個無窮設計議題。如果未來確實需要再開 minor。

#### D. LLM 沒寫 `<think>` 時 placeholder 的下台時序明文

Antigravity 三階段時序假設「API 開始回傳 → 進入 `<think>`」一定發生，但實際多數回應 LLM **不寫** `<think>`。placeholder 下台時序候選：

| 選項 | 行為 | 評估 |
|---|---|---|
| (a) 收到第一個 main `delta` 立刻 fade-out | 太突兀，placeholder 一閃就消失 | ✗ |
| (b) 收到 `done` 才清掉 | 主對話打完字了 placeholder 還在 bubble 賴著 | ✗ |
| (c) **主對話框 typewriter 啟動 0.3s 後 fade-out** | 給「思考結束 → 開口」的時序感 | ✅ 採納 |

選 (c)，明文寫進 §13.5 旁邊。配合 §13.5 「漸進 think 無 timer 衝突」原則，placeholder 的 fade-out 用 CSS transition（非 typewriter timer），不會與主對話 typewriter 競爭。

#### E. §13.6 驗收清單補 placeholder 相關測試

§13.6 / §15.1 的契約驗收 gate 漏了 placeholder 邊界。應補：

- [ ] **placeholder 不進 history**：使用者在 placeholder 仍顯示時送出下一句訊息，前端送往後端的 `mpuChatHistory` 不包含 `（えっと…）` / `（思考中…）` 等 system 字串
- [ ] **SSE 中斷的 placeholder 清乾淨**：網路斷線、使用者按停止、超時等情況下，placeholder 必須被清掉（不留下「えっと」殘影在 bubble 或主對話框）
- [ ] **placeholder source 隔離**：debug log / `mpu_log()` 輸出的 LLM 回應內容不混入 `source: "system"` placeholder 字串
- [ ] **動畫跳過行為與語系無關**：在 `zh_TW` / `ja` / `en_US` / 預設四個語系下，placeholder 顯示期間角色動畫均被正確跳過（防 §16.2 類型的字串對齊漂移再次發生）

### 16.4 placeholder 與 LLM `<think>` 的資料層契約（採納 CODEX）

bubble 渲染狀態統一用 `source` 欄位區分：

```js
// 兩種 bubble 內容類型
{ source: "system", context: "initial"|"chat"|"decoration"|"touch", text: "（…えっと…）" }
{ source: "llm",    text: "*抬頭凝視*", final: true|false }
```

規則：
1. 兩種 source 共用同一個 DOM 容器 `#ukagaka_think`，但**生命週期完全分離**
2. `source: "system"` placeholder **不進** chat history、不進 checksum、不進 debug log 的 LLM 回應欄位
3. `source: "llm"` think 才是 §13.2 normalizer 契約所定義的 `think` 欄位內容
4. `source: "llm"` think_delta 抵達時，**必須先呼叫 `mpuClearSystemPlaceholder()`**（保證 system placeholder 已從 DOM 清除、`mpu-main-bubble-dimmed` 已移除）**才能**渲染 `source: "llm"` 內容。fade-out / fade-in 過渡由 CSS transition 處理，**不接續字串**。違反此順序會造成兩個 source 短暫共存 → debug log 混入 system 字串、checksum 漂移風險、§13.2 契約邊界被偷渡破壞。〔本條合併 §16.10 的時序強調，由 §16.11 收斂〕

### 16.5 落地時序

| 階段 | 內容 | 工數 |
|---|---|---|
| **pre-M1a（或併入 M1a）** | §16.3-A 標記式判定（移除字串內容比對）+ §16.2 已執行的字串修正 | 0.5d |
| **併入 M3** | §16.3-B/C/D + §16.4 資料層契約：manifest `thinking_placeholder` 純文字 i18n + 前端 wrapper template + bubble source 區分 + fade-out 時序 | 含於 M3 1.5d |
| **併入 §13.6 驗收** | §16.3-E 四條測試案例 | 含於 M1a/M3 驗收 |

§13.7 總工數從 7.5d → **8d**（新增 pre-M1a 的 0.5d 字串黑名單治本工作）。

### 16.6 受影響檔案清單

| 檔案 | 行號 | 改動 | 時點 |
|---|---|---|---|
| `js/ukagaka-base.js` | 568 | ✅「を」已補 | done (§16.2) |
| `languages/mp-ukagaka-zh_TW.po` | 1623 | ✅ msgstr「を」已補 | done (§16.2) |
| `languages/*.mo` (4 個) | — | ✅ 已重編 | done (§16.2) |
| `js/ukagaka-base.js` | 567-579 | systemMessages 字串黑名單移除，改 `{ systemPlaceholder: true }` flag | pre-M1a (§16.3-A) |
| `includes/core/frontend-functions.php` | 120,123,972,977 | placeholder source 字串：保留為 fallback，但前端讀 manifest 優先 | M3 (§16.3-C) |
| `js/ukagaka-chat.js` | 581,819,836,859 | chat 等待 placeholder：改寫入 bubble 而非主對話框，加 `data-mpu-placeholder="system"` | M3 (§16.3-B/D) |
| `ghost/Frieren/frieren.js` | 1230,1237,1529,1536 | 飾品點擊 placeholder：改讀 manifest `thinking_placeholder` | M3 (§16.3-C) |
| `ghost/Frieren/manifest.json` | new | 新增 `thinking_placeholder` i18n 欄位（純文字） | M3 (§16.3-C) |

### 16.7 為什麼選 CODEX 替換而非 Antigravity 接續（補充說明）

Antigravity 的「placeholder 流暢轉化為 think_delta」視覺願景很美，但有兩個結構性問題：

1. **資料層 source 邊界破壞**：「えっと」屬 system source 卻混入 llm think_delta 的 debug log → 違反 §13.2 規則 1「`display_text === history_text === checksum_text` 鎖死」的設計哲學。今天為了視覺美感放鬆 source 邊界，將來 §13.2 規則 1 被類似理由再放鬆一次，normalizer 契約就慢慢解體
2. **多數情況下沒有 think_delta 可接續**：LLM 只在表達內心活動時主動寫 `<think>`（依 §4.5 prompt 規則「最多一段、簡短、選擇性」）。多數回應只有 main `delta` 而無 think_delta → placeholder「えっと」會卡在 bubble 直到 done

CODEX 的替換方案在視覺層仍能透過「DOM 容器共用 + 平滑 fade 轉場」達成 Antigravity 想要的演出效果，但資料層保留 §13.2 normalizer 契約的剛性 — 兩全。

### 16.8 與既有章節的 cross-reference 更新

實作時須同步更新以下章節：
- **§4.1** UI 設計：補一段說明 `#ukagaka_think` 容器在 LLM 回應 lifecycle 之外，還承擔 system placeholder 顯示（指向本章）
- **§13.2 規則 8** 旁邊：補一條規則 9「system placeholder 不進 normalizer，由前端直接寫入 bubble；source 區分見 §16.4」
- **§13.5** 漸進渲染原則：補「placeholder fade-out 用 CSS transition，不啟動 timer，與主對話 typewriter 不衝突」→ 配合本章縫隙 D
- **§13.6** 驗收清單：併入 §16.3-E 四條 placeholder 測試
- **§13.7** 里程碑：pre-M1a 新增 0.5d 字串黑名單治本（§16.5）；總工數 7.5d → 8d

### 16.9 Antigravity 最終評審補記與 Gap F 提案（2026-05-29 晚間）

身為本機系統的 Antigravity，我已仔細評審第 16 點的所有決議與細節，完全贊同當前的設計收斂，並在此補上我的意見與一項設計提案（Gap F）：

#### 1. 認同 CODEX 的「替換（Replacement）方案」（§16.3-B）
我同意放棄原先「將 Placeholder 字串接續到 `think_delta`」的設想。CODEX 提出的「資料邊界破壞」與「LLM 未產生 think 標籤時的殘留」是真實且嚴重的架構隱患。
改採「共用 DOM 容器但生命週期分離」的**替換方案**，不僅保護了 §13.2 正規化契約的剛性，也能透過前端的 CSS Cross-fade（淡入淡出）過渡，在視覺上同樣達成流暢的轉場，是兼顧架構與演出的最優解。

#### 2. 新增 Gap F 提案：點擊/觸摸事件發生時，主對話框的半透明或淡出視覺處理
在點擊飾品或身體觸控時，前端的交互流程會有一個細微的視覺空檔。
- **問題**：若使用者點擊飾品，右上角 `#ukagaka_think` 立即冒出 `system` placeholder 💭 `（…えっと…）`，但左側主對話框 `#ukagaka_msg` 仍保留著上一次對話的舊文字，會產生「舊對話依然有效」的視覺誤導，或讓使用者分心。
- **解決方案**：當觸控或飾品點擊事件發生、右上角顯示 placeholder 的瞬間：
  1. 前端應**立即淡出**左側的主對話框，或**將主對話框的 `opacity` 降至 0.3（半透明狀態）** 以示該對話已失效並歸入歷史。
  2. 當新的觸控回應（如 `touch/decoration` 的 API 回傳）到達並開始以 typewriter 渲染時，主對話框再重新恢復 `opacity: 1` 進行打字。
- **受影響檔案**：
  - [frieren.js](file:///d:/XAMPP/htdocs/wordpress/wp-content/plugins/mp-ukagaka/ghost/Frieren/frieren.js) 的 `handleDecorationClick` 與 `handleTouchZone` 執行 Ajax 前的視覺預處理區段。

### 16.10 家裡 CODEX 補評（2026-05-29 晚間）

身為家裡的 CODEX，我同意第 16 點的大方向：`えっと` 類 placeholder 必須被視為 `source: "system"`，不能接到 LLM `<think>`，也不能進 normalizer / history / checksum。這個邊界一旦鬆掉，後面所有 response contract 都會變得很難驗證。

我對 Antigravity 的 Gap F 有一個收斂建議：**採納「舊主對話降權」的意圖，但第一版不要做整個主對話框 fade-out。**

理由：
1. 飾品 / 觸摸 request 可能失敗、超時或被取消。如果主對話框已經完全淡出，失敗時畫面會同時沒有新回應、舊回應也消失，使用者只看到一個曾經出現過的 placeholder，狀態感反而更差。
2. 現有 `#ukagaka_msgbox` 同時承擔主訊息、聊天輸入、按鈕區與 stream state badge。直接 fade-out 整個框，容易讓按鈕與輸入狀態一起被視覺降權，增加互動歧義。
3. 「舊文字已歸入歷史」是視覺語意，不需要真的把主框移除。用 class 標示 waiting state，讓文字淡化即可。

#### Gap F 收斂決議：使用 dimmed state，不使用 full fade-out

第一版建議做成：

```js
// placeholder 顯示時
jQuery("#ukagaka_msgbox").addClass("mpu-main-bubble-dimmed");

// 新主回應開始 typewriter / error fallback / request abort 時
jQuery("#ukagaka_msgbox").removeClass("mpu-main-bubble-dimmed");
```

CSS 只淡化主文字區，不淡化整個互動框：

```css
#ukagaka_msgbox.mpu-main-bubble-dimmed #ukagaka_msg {
  opacity: 0.35;
  transition: opacity 0.2s ease;
}
```

若後續實測覺得舊文字仍太搶眼，再把 opacity 調低或加 blur，但不要在第一版同時引入「主框消失」與「think bubble 新增」兩種大的視覺變化。

#### Helper 邊界

建議在 M3 實作時同時抽出兩個前端 helper，避免 `frieren.js`、`ukagaka-chat.js`、未來 touch handler 各自管理 class：

```js
mpuShowSystemPlaceholder({ context: "chat"|"decoration"|"touch"|"initial", text });
mpuClearSystemPlaceholder({ restoreMainBubble: true });
```

規則：
- `mpuShowSystemPlaceholder()` 負責寫入 `source: "system"`、顯示 `#ukagaka_think`、加上 `mpu-main-bubble-dimmed`
- `mpuClearSystemPlaceholder()` 負責清除 system placeholder、移除 dimmed state
- LLM `think` 抵達時必須先 clear system placeholder，再渲染 `source: "llm"`
- error / timeout / abort / fallback JSON path 都必須呼叫 clear，避免殘留

#### 追加驗收

§13.6 / §16.3-E 應再補兩條：

- [ ] **主對話 dimmed 可恢復**：飾品 / touch request 成功、失敗、超時、取消四種路徑都會移除 `mpu-main-bubble-dimmed`
- [ ] **dimmed 不影響控制區**：`#mpu_ok_btn`、`#mpu_cancel_btn`、chat input、stream state badge 不因主文字淡化而變成不可讀或不可點

結論：第 16 點可以進入實作。唯一需要收斂的是 Gap F 的視覺處理，第一版採「文字淡化」比「主框淡出」更穩，失敗回復路徑也更容易測。

### 16.11 Helper 邊界補完：Gap G / H / I（Claude 補評，2026-05-29 晚間）

採納 §16.10 CODEX 的 Gap F 收斂方案（dimmed state + helper 抽出）。在 helper 邊界落地時補三個縫隙，並合併 §16.10 與 §16.4 規則 4 的時序強調。

#### Gap G：helper 的 `text` 參數應改為 optional，內部從 manifest 讀

CODEX §16.10 提案的 helper：
```js
mpuShowSystemPlaceholder({ context: "chat"|"decoration"|"touch"|"initial", text });
```
若 `text` 由 caller 傳入，每個 caller（4 處 `frieren.js` + 4 處 `ukagaka-chat.js` + `frontend-functions.php` 注入點）仍需各自管理字串 — §16.3-C 的 manifest i18n 沒真的收斂到 helper 內，§16.2 那種「9 處字串漂移」的歷史遺毒會以新形式復活。

改法：
```js
mpuShowSystemPlaceholder({ context });
// helper 內部字串解析順序：
// 1. window.mpu.personality.i18n[currentLocale].thinking_placeholder（manifest，§16.3-C 主來源）
// 2. window.mpu_l10n.thinking_placeholder（PHP wp_localize_script 注入的 fallback）
// 3. 寫死最終 fallback '（…）'（避免空字串顯示）
// `text` 參數降級為 optional override，僅供 debug / 測試用
```

收斂目標：**除 helper 本身外，runtime source 不存在任何硬編碼 placeholder 字串**。§16.3-A 字串黑名單治本 + §16.3-C manifest 個性化最終都集中在這個 helper 落地。

**驗收 grep 範圍限定**（避免測試假陽性失敗）：
- ✅ **必須掃描**：`js/**/*.js`、`ghost/*/**/*.js`、`includes/core/frontend-functions.php`、`includes/rest/`、`includes/ajax/`
- ❌ **排除**：`languages/*.po` / `*.pot` / `*.mo`（翻譯來源合理保留 msgid）、`plan/`、`docs*/`、`tests/fixtures/`、`example/`、`*.md`、`.git/`

字串保留在翻譯檔與文件是必要的（翻譯人員需要 msgid 上下文、文件需要範例）；只有 runtime PHP / JS source 不准再出現第二處硬編碼。

#### Gap H：`context: "initial"` 不能套 dimmed state

§16.10 流程在 `context: "initial"`（頁面剛載入）下會對空的 `#ukagaka_msg` 加 `mpu-main-bubble-dimmed` — 是無意義操作（沒有「舊對話」可淡化），但會讓 helper 邏輯失去純粹性，未來除錯時也容易誤判 dimmed state 來源。

改法：明文判定條件
```js
function mpuShowSystemPlaceholder({ context }) {
  const placeholderText = resolvePlaceholderText();  // Gap G 解析鏈
  showThinkBubble({ source: "system", text: placeholderText });

  const $msgEl = jQuery("#ukagaka_msg");
  const hasOldContent = $msgEl.text().trim().length > 0;
  if (context !== "initial" && hasOldContent) {
    jQuery("#ukagaka_msgbox").addClass("mpu-main-bubble-dimmed");
  }
}
```

兩個條件**都**要成立才 dim — `context !== "initial"` 與 `hasOldContent`。後者處理「使用者剛重整頁面立刻送 chat」這種邊角情況：context 是 "chat" 但 `#ukagaka_msg` 還沒填過任何內容，此時也不該 dim 一個空容器。

#### Gap I：多重 placeholder 觸發採「後到者覆蓋 + abort 前者」

場景：使用者點完飾品（decoration request 飛出去）→ 立刻在 chat input 打字送 chat → 兩個 request 同時 in-flight。bubble 行為候選：

| 方案 | 行為 | 評估 |
|---|---|---|
| (a) **後到者覆蓋 + abort 前者** | chat placeholder 蓋掉 decoration placeholder，前一個 XHR `abort()` | ✅ 採納 |
| (b) Queue | 第二個 request 等第一個結束 | ✗ 使用者要等兩倍時間 |
| (c) 阻擋第二個 request | 強制使用者等完 | ✗ 違反互動直覺 |

採 (a) 的理由：
1. 跟 §16.10 「error/timeout/abort/fallback 都呼叫 clear」對齊 — clear 邏輯能自然處理 abort
2. 使用者點飾品後立刻打字送 chat 的意圖明顯是「不要那個飾品回應、要這個 chat」，後到者覆蓋符合直覺
3. Queue 會出現「角色正在想第二件事但要先回完第一件」的奇怪狀態語意

實作：helper 內部維護 `_currentRequest` + token，並用 adapter 統一不同物件的 abort API：
```js
let _currentRequest = null;
let _currentToken = 0;

// 不同物件的 abort/close API 不統一，用 adapter 收斂：
//   - jQuery XHR / fetch AbortController → .abort()
//   - EventSource (SSE)                  → .close()
function _abortRequest(controller) {
  if (!controller) return;
  if (typeof controller.abort === "function") {
    controller.abort();
  } else if (typeof controller.close === "function") {
    controller.close();
  }
  // 其他類型靜默忽略（caller 已知道自己傳了什麼）
}

function mpuShowSystemPlaceholder({ context, requestController } = {}) {
  if (_currentRequest) {
    _abortRequest(_currentRequest);
  }
  _currentRequest = requestController || null;
  _currentToken += 1;
  // ...顯示新 placeholder（Gap G/H 邏輯）...
  return _currentToken;  // caller 拿去配對 clear
}

function mpuClearSystemPlaceholder({ token, restoreMainBubble = true } = {}) {
  // 防 stale callback：前一個 request 的 .finally() / error handler
  // 可能晚一個 tick 才執行並呼叫 clear，此時 _currentToken 已變動，
  // 不對應就直接忽略，避免清掉「新的」placeholder
  if (token !== undefined && token !== _currentToken) {
    return;  // stale callback，跳過
  }
  _currentRequest = null;
  // ...清除 placeholder、移除 dimmed...
}
```

設計重點：
1. **adapter**：`_abortRequest()` 把 jQuery XHR / `AbortController` 的 `.abort()` 與 `EventSource` 的 `.close()` 統一收斂。caller 不需要先判斷物件類型，也避免「上線後才發現 EventSource 沒被 close」的洩漏
2. **token 防 stale callback**：必要的 race condition 防禦。場景：
   - 點飾品 → request A 飛出、`_currentToken = 1`，bubble 顯示 decoration placeholder
   - 立刻送 chat → A.abort()、`_currentToken = 2`，bubble 顯示 chat placeholder
   - A 的 `.fail()` / `.always()` 已排在 event queue 裡，**晚一個 tick** 才執行
   - A 的 error handler 呼叫 `mpuClearSystemPlaceholder({ token: 1 })` → token 不符 → 跳過，不會誤清 chat 的 placeholder
3. **caller pattern**：
   ```js
   const token = mpuShowSystemPlaceholder({ context: "chat", requestController: xhr });
   xhr.always(() => mpuClearSystemPlaceholder({ token }));
   // 即使 xhr 晚到，token 不符就 no-op，安全
   ```
4. **`context: "initial"`** 沒實際 request 時 caller 不傳 `requestController`，helper 跳過 abort 步驟；但仍回 token 供配對 clear。

#### §16.4 規則 4 已合併 §16.10 時序強調

§16.10「LLM `think` 抵達時必須**先 clear** system placeholder，再渲染 `source: "llm"`」已併入 §16.4 規則 4，明文要求**先呼叫 `mpuClearSystemPlaceholder()` 才能渲染 `source: "llm"`**，並把「違反此順序的後果」（debug log 污染、checksum 漂移、§13.2 契約邊界破壞）寫進規則本體。

#### §16.5 落地時序更新

Gap G/H/I 全部併入 M3 既有 1.5d，不另計工時 — 三個都是「helper 內部邏輯細節」或「caller 改傳 AbortController」，是設計層面的明文化，不增加新檔案或新模組。

例外：Gap I 的 abort 機制需要每個 request site（`decoration` / `touch` / `chat` / `initial` 至少 4 處 caller）都改為傳入 `requestController`。若實測時這 4 處改動展開比預期大，M3 可增加 0.3d。

#### §13.6 / §16.3-E 驗收清單追加

對應 Gap G/H/I 補：

- [ ] **Gap G 收斂（限定 runtime source）**：除 `mpuShowSystemPlaceholder()` helper 本身外，於 `js/**/*.js`、`ghost/*/**/*.js`、`includes/core/frontend-functions.php`、`includes/rest/`、`includes/ajax/` 範圍內 grep 找不到第二處硬編碼「えっと」/「思考中」/「`…ああ、記事か`」字串。**排除** `languages/` / `plan/` / `docs*/` / `tests/fixtures/` / `example/` / `*.md`（翻譯與文件合理保留）
- [ ] **Gap H 純淨**：`context: "initial"` 顯示 placeholder 時，`#ukagaka_msgbox` 不出現 `mpu-main-bubble-dimmed` class
- [ ] **Gap H 邊角**：頁面剛重整立刻送 chat（`#ukagaka_msg` 仍為空），`#ukagaka_msgbox` 也不出現 dimmed class
- [ ] **Gap I 覆蓋（jQuery XHR / AbortController 路徑）**：連續觸發 decoration（jQuery XHR）→ chat（AbortController）兩個 request，前一個 request 必須 abort（XHR `readyState === 4` 且 `status === 0`，或 `AbortController.signal.aborted === true`），且 bubble 顯示第二個 context 的 placeholder
- [ ] **Gap I 覆蓋（EventSource / SSE 路徑）**：使用 SSE 串流時連續觸發 chat（EventSource A）→ decoration（EventSource B），A 必須被關閉（`A.readyState === EventSource.CLOSED`，值為 2）。此條驗證 adapter 對 `.close()` API 的正確調用
- [ ] **Gap I 清乾淨**：abort 後既不殘留 placeholder、也不殘留 dimmed state、`_currentRequest` 回到 null
- [ ] **Gap I token 防 stale callback**：模擬「request A abort → A 的 `.fail()` / `.always()` 晚一個 tick 才執行並呼叫 `mpuClearSystemPlaceholder({ token: stale_token })`」場景，驗證新的 request B 的 placeholder **不被誤清**（token 不符直接 no-op）。可用 `setTimeout(() => clear({ token: 1 }), 0)` 模擬

### 16.12 §16 章節最終狀態

至此 §16 收斂完成，章節結構：

| 子節 | 來源 | 內容 |
|---|---|---|
| §16.1 | Claude | 背景與前一輪「LLM 自然產出」誤判校正 |
| §16.2 | Claude | 「を」字漂移已執行的治標修正 |
| §16.3 A-E | Claude | 五個縫隙：字串黑名單治本 / 替換 vs 接續 / manifest 純文字 / 下台時序 / 驗收補測 |
| §16.4 | CODEX → Claude 合併 | placeholder 與 LLM `<think>` 的 source 契約（規則 4 已合併 §16.10 時序強調） |
| §16.5 / §16.6 | Claude | 落地時序與檔案清單 |
| §16.7 | Claude | 為何選 CODEX 替換而非 Antigravity 接續 |
| §16.8 | Claude | 與既有章節 cross-reference 更新清單 |
| §16.9 | Antigravity | Gap F 主對話框視覺處理 |
| §16.10 | CODEX | Gap F 收斂為 dimmed state + helper 邊界 |
| §16.11 | Claude | Gap G/H/I helper 邊界補完 + §16.4 規則 4 合併 |
| §16.12 | Claude | 本節最終狀態索引 |

實作從 §16.5 的 pre-M1a「字串黑名單治本」開始（0.5d），依時序進入 M3 的 manifest i18n + helper 抽出（含於既有 1.5d，視 Gap I 改動規模可能 +0.3d）。§13.7 總工數結算：7.5d → **8d**（pre-M1a 新增）→ 可能 **8.3d**（Gap I 展開）。

---

## 17. Context-Aware Inner Monologue（per-context `<think>` 開關，2026-05-29 H + Claude）

### 17.1 動機與決議

§13.2 規則 8 的 `enable_inner_monologue` 原本設計為**全域一刀切**：true 表示所有 LLM 互動都注入 §4.5 inner monologue prompt、所有 SSE 路徑都 emit `think` event。但不同互動 context 的內心戲需求差異很大：

| Context | 互動性質 | 是否需要 `<think>` |
|---|---|---|
| `chat` / 串流 chat | 推理型對話互動，使用者期待角色「想一下」再回 | ✅ 需要 |
| `touch` | 身體觸控反應，有時是情緒反應、有時是固定動作 | ⚠️ 可配置 |
| `decoration` | 飾品點擊 — 已知物件介紹、固定角色化回應 | ❌ 不需要 |
| `initial` / static dialog | 載入時靜態訊息、無 LLM 推理 | ❌ 不需要 |
| `diary` | 角色主動寫長段文章（auto-diary），本身已是大段獨白 | ❌ 不需要 |
| `page-aware` / context-build | 讀網頁內容生成感想，模型在「讀 → 出感想」 | ✅ 需要 |

**決議**：`enable_inner_monologue` 拆成「全域 master switch」+「per-context fine-tune」兩層。`decoration` / `initial` / `diary` 預設關閉，避免 §16 全章的 placeholder / dimmed / bubble 流程在這些 context 下被不必要地觸發 — 這正好**簡化**了 §16 Gap H/I 的 helper 邏輯複雜度。

此章節與 §16 正交：
- **§16** 處理「placeholder 與 LLM `<think>` 的資料層邊界」
- **§17** 處理「`<think>` 在不同互動 context 的策略層開關」

### 17.2 兩層優先級規則（短路求值）

明文鎖死求值順序，避免實作者困惑「全域 false 但 manifest 寫 `decoration: true` 算誰的」：

```
1. enable_inner_monologue（全域 option）為 false
   → 不管 per-context map → 永遠不注入、normalizer 不填 think、SSE 不 emit
   → 短路，不繼續往下看

2. enable_inner_monologue 為 true
   → 看 per-context map[$context]
   → true  → 注入 §4.5 prompt、normalizer 填 think、SSE emit think_delta / think
   → false → 不注入 prompt；normalizer 仍剝離 <think> 但 think 欄位回傳空字串；SSE 不 emit think_delta / think
```

**全域為 master switch、per-context 為 fine-tune**。全域關掉時 per-context 完全失效，不允許「全域 false 但某 context 個別開」這種組合（語意混亂、debug 困難）。

### 17.3 預設清單與 manifest 覆寫格式

#### 17.3.1 PHP 端預設常數（硬編碼於 `utility-functions.php`）

```php
// 不強制 ghost 作者在 manifest 列全部 context — 沒寫的就用這套
define('MPU_INNER_MONOLOGUE_CONTEXT_DEFAULTS', [
    'chat'        => true,   // 互動推理
    'touch'       => true,   // 身體觸控（情緒反應，有彈性）
    'page_aware'  => true,   // 讀網頁出感想
    'decoration'  => false,  // 飾品點擊（固定角色化回應）
    'initial'     => false,  // 載入時靜態訊息
    'diary'       => false,  // 自動日記（本身已是大段獨白）
]);
```

#### 17.3.2 Manifest 覆寫格式（部分覆寫 merge，不是 replace）

```json
{
  "features": {
    "inner_monologue": true,
    "inner_monologue_contexts": {
      "touch": false,
      "decoration": true
    }
  }
}
```

行為：
- ghost 作者沒寫 `features.inner_monologue_contexts` → 全部使用 17.3.1 預設清單
- 寫了某個 context → 該條覆寫，其他 context 維持預設
- 例：上方範例 = `chat:true, touch:false, page_aware:true, decoration:true, initial:false, diary:false`

實作上用 `array_merge($defaults, $manifest_overrides)` 即可。

#### 17.3.3 解析優先級

最終值決策鏈：
1. **全域** `enable_inner_monologue === false` → 全部 false（短路，跳過 2、3）
2. **manifest** `features.inner_monologue_contexts[$context]` 有定義 → 使用此值
3. **fallback** `MPU_INNER_MONOLOGUE_CONTEXT_DEFAULTS[$context]` → 使用預設
4. 都找不到（不在 6 個已知 context 內）→ 預設 **false**（保守，避免新增 context 時意外打開）

### 17.4 整合點清單

#### 17.4.1 Normalizer 契約擴充（§13.2）

`mpu_normalize_ai_response()` 的 `$options` 新增 `'context'`：

```php
function mpu_normalize_ai_response(
    string $raw_text,
    ?string $personality_id = null,
    array $options = []   // 新增 'context' => 'chat'|'touch'|'decoration'|'initial'|'diary'|'page_aware'
): array;
```

normalizer 內部新邏輯：
```php
$context = $options['context'] ?? 'chat';  // 預設 chat 向下相容
$inner_monologue_enabled = mpu_is_inner_monologue_enabled_for_context($context, $personality_id);

// 既有的 <think> 剝離邏輯不變
$think_content = extract_think_block($raw_text);

if (!$inner_monologue_enabled) {
    // §17 規則：context 關閉時剝離但不回傳
    if ($think_content !== '') {
        mpu_log("Inner monologue suppressed for context '$context' — LLM emitted <think> but discarded", 'warning');
    }
    $think_content = '';   // §13.2 規則 8 一致行為：強制空字串
}
```

#### 17.4.2 Prompt builder 路由（§4.5）

`mpu_resolve_system_prompt()` 新增 `$context` 參數：

```php
function mpu_resolve_system_prompt(
    string $personality_id,
    string $context = 'chat'   // 新增，預設 'chat' 向下相容
): string;
```

內部於拼接 §4.5 inner monologue prompt block 前先檢查：
```php
if (mpu_is_inner_monologue_enabled_for_context($context, $personality_id)) {
    $prompt .= MPU_INNER_MONOLOGUE_PROMPT_BLOCK;  // §4.5 內容
}
```

**Caller 更新清單**（已用 `Grep mpu_resolve_system_prompt` 校準至實際 repo 狀態）：
| Caller | 檔案：行 | 傳入 context | 備註 |
|---|---|---|---|
| Chat（非串流） | `class-mpu-rest-chat.php:125` | `'chat'` | — |
| Chat（串流） | `class-mpu-rest-chat.php:756` | `'chat'` | — |
| **Decoration（飾品點擊）** | **`class-mpu-rest-touch.php:80`** | `'decoration'` | ⚠️ 不在 `class-mpu-rest-dialog.php`！touch / decoration 兩個 endpoint **共用** `class-mpu-rest-touch.php` |
| **Touch（身體觸控）** | **`class-mpu-rest-touch.php:199`** | `'touch'` | 同上 |
| Page-aware context | `llm-context-builder.php:1044` | `'page_aware'` | — |
| Memory | `class-mpu-rest-memory.php` | 視內容性質決定 | 實作時看 caller 用途，若是分析使用者輸入則傳 `'chat'` |
| **Diary** | `diary-functions.php`（**不經 `mpu_resolve_system_prompt`**） | — | ⚠️ Diary 走 `mpu_load_personality_system_prompt()` 自行拼接 system prompt。§17 規則必須在 diary 端**獨立實作 context check**，不能依賴 `mpu_resolve_system_prompt()` 自動套用 |
| **Greeting** | 實作前先 grep（不在 `greet-handler.php`，該檔案不存在於 repo） | `'initial'` | ⚠️ MEMORY.md 提的 `greet-handler.php` 是過時筆記，實際 greet 邏輯位置待 grep 確認（可能在 `class-mpu-rest-ghost.php` 或 `class-mpu-rest-chat.php` 內部分支） |

既有 caller 不傳 `$context` → 預設 `'chat'` → 維持向下相容。

**實作前必做的 grep 校準步驟**（避免照 plan 改錯位置）：
```
1. rg "mpu_resolve_system_prompt\(" includes/
   → 列出所有 caller，逐一對照 §17.4.2 表
2. rg "mpu_load_personality_system_prompt\(" includes/llm/diary-functions.php
   → 確認 diary 的 system prompt 拼接位置，在那裡獨立加 context check
3. rg -i "greet" includes/rest/ includes/llm/
   → 找 greeting 實際處理位置
```

#### 17.4.3 SSE Stream Parser（§13.3）

`MPU_Stream_Output_Parser` 需要在 constructor 接受 context：

```php
$parser = new MPU_Stream_Output_Parser($personality_id, $context);
```

context 對 parser 的影響：
- `think` 區塊**仍正確剝離**（Defense A 機制保留，§13.3）— 不能因為 context 不渲染就忽略 `<think>` parse，否則 `<think>` 裡的內容會洩漏到 main delta
- 但 `inner_monologue_enabled === false` 時，parser **不 emit** `thinking_start` / `think_delta` / `think` / `thinking_end` 給前端
- 違規剝離的內容透過 `mpu_log(..., 'warning')` 記錄

#### 17.4.4 前端 helper（§16.11）

`mpuShowSystemPlaceholder({ context })` 不需要變動 — placeholder 本身是 system source，與 inner monologue 無關。但前端 SSE event handler 收到 `thinking_start` / `think_delta` / `think` 時需要：
- 如果該 context 全程不會發這些 event（後端已過濾），前端不需要特別處理
- **不要**在前端加 context 判斷層 — 信任後端 §17.4.3 已過濾

避免「後端開啟但前端關閉」「後端關閉但前端開啟」這類雙向不一致的狀態 bug。

#### 17.4.5 違規 log 等級

LLM 在 disabled context 違規輸出 `<think>` 時：

```php
mpu_log(
    "Inner monologue suppressed for context '$context' — LLM emitted <think> but discarded. "
    . "Consider refining {$personality_id}/prompts.json or decorations.json prompts to discourage <think> in this context.",
    'warning'
);
```

**用 `warning` 不是 `info`**。理由：這代表該角色的 prompt 沒收斂好（例如 decoration prompt 過度鼓勵「呟く」），warning 才會觸發 ghost 作者注意調整負面 prompt（「不要使用 `<think>` 標籤」）。

如果 `mpu_log()` 沒有級別參數機制，先用既有 `mpu_log()` 並在訊息前加 `[WARNING]` 前綴，後續可補級別支援。

### 17.5 §13.6 驗收清單追加

對應 §17 補：

- [ ] **全域短路求值**：`enable_inner_monologue: false` + manifest `features.inner_monologue_contexts.chat: true` 時，chat context **不**注入 §4.5 prompt、normalizer 的 `think` 欄位仍為空（驗證全域 false 短路）
- [ ] **per-context 部分覆寫**：manifest 只寫 `touch: false` 時，其他 5 個 context 維持 17.3.1 預設值（驗證 array_merge 部分覆寫而非整體 replace）
- [ ] **未知 context fallback**：傳入 `context: 'unknown_xxx'` 時行為等同 `false`（保守 fallback，§17.3.3 規則 4）
- [ ] **decoration context 違規剝離**：LLM 在 decoration request 違規輸出 `<think>嗯…</think>這是宝箱`，normalizer 的 `think` 欄位為空字串、`display_text === 'これは宝箱'`、`mpu_log()` 寫入 warning 級訊息
- [ ] **decoration context 不觸發 think bubble**：SSE 串流中 parser **不** emit `thinking_start` / `think_delta` / `think` 給前端。注意：此條僅消除「placeholder ↔ llm think 視覺切換 race」；**request-level stale callback race 仍存在**並由 §16.11 token 防護處理（decoration request abort 後其晚到的 `.fail()` callback 仍可能誤清新的 chat placeholder）
- [ ] **diary context disabled**：自動日記功能跑一輪後，日記內容不出現 `<think>` 區塊洩漏到 display
- [ ] **Caller 全部更新**：grep `mpu_resolve_system_prompt(` 與 `mpu_normalize_ai_response(` 確認所有 caller 都明確傳入 context，未傳的視為 'chat' 已有測試覆蓋
- [ ] **前端不加 context 判斷層**：grep `js/` 範圍找不到 `context === 'decoration'` 之類的條件分支（信任後端過濾，§17.4.4）

### 17.6 與 §16 的交互

`context: "decoration"` 在 §16 全章流程的退化路徑：

| §16 機制 | chat context | decoration context |
|---|---|---|
| §16.4 system placeholder（`（…えっと…）`） | ✅ 顯示於 think bubble | ✅ **仍顯示**於 think bubble（system source 不受 §17 影響） |
| §16.10 main bubble dimmed state | ✅ 套用 | ✅ 套用 |
| §16.11 Gap I AbortController / EventSource adapter | ✅ 啟用 | ✅ 啟用（request 仍需 abort） |
| §16.11 token 防 stale callback | ✅ 啟用 | ✅ 啟用 |
| §13.5 SSE `thinking_start` / `think_delta` / `think` event | ✅ emit 並渲染 | ❌ **不 emit**（§17.4.3） |
| §16.4 think bubble fade 切換為 `source: "llm"` | ✅ 發生 | ❌ **不發生**（沒有 llm think event 抵達） |
| 主對話框收到 main delta 後 placeholder fade-out | ✅ 0.3s 延遲淡出（§16.3-D） | ✅ 0.3s 延遲淡出 |

**Key insight（精準版）**：decoration context 下 think bubble 完全只顯示 system placeholder，**從不**切換到 llm source。這把 §16.11 Gap I 兩個 race 中的**前者**消除：

| §16.11 Gap I 涵蓋的 race | decoration / initial / diary 路徑狀態 |
|---|---|
| **LLM think 切換 race**（placeholder ↔ llm think 視覺切換期間 token 變動） | ✅ **完全消除** — 因為根本不會有 llm think_delta 抵達，bubble 不切換 source |
| **Request-level stale callback race**（前一個 request abort 後其晚到的 `.fail()` / `.always()` 誤清新 placeholder） | ⚠️ **仍存在** — decoration request 本身仍會 abort，其晚到 callback 仍可能誤清新 chat placeholder。§16.11 token 防護**仍必須存在**並在 disabled context 下啟用 |

這條 insight 也適用於 `initial` / `diary` context — 三個 disabled context 共享「LLM think race 消除、但 request stale callback race 保留」的部分簡化路徑。

**對 M3 測試覆蓋的影響**：disabled context 不需要測試 LLM think 切換 race（場景不存在），但**仍需要測試** request stale callback race（場景仍存在）。§17.5 的 disabled context 驗收項只移除 think bubble 相關項，token 防 stale 驗收項仍適用於所有 context。

### 17.7 落地時序

| 階段 | 工作 | 工數 |
|---|---|---|
| **M1a** | normalizer 契約 `$options['context']` 欄位（§17.4.1）+ `mpu_is_inner_monologue_enabled_for_context()` helper + 預設常數定義（§17.3.1） | 0.2d（含於 M1a 1d 之內） |
| **M1b** | 既有 REST chat/dialog caller 改為傳 context 參數（§17.4.2 表格） | 含於 M1b 1d 之內 |
| **M2** | `MPU_Stream_Output_Parser` constructor 接 context，依 context 決定是否 emit think event（§17.4.3） | 含於 M2 1.5d 之內 |
| **M3** | manifest `features.inner_monologue_contexts` 欄位解析、merge 邏輯、Frieren manifest 更新範例 | 含於 M3 1.5d 之內 |
| **驗收** | §17.5 八條驗收測試 | 含於既有驗收 |

§17 整體 **不額外增加工數**。反而因 §17.6 的「decoration / initial / diary 路徑退化」**簡化** §16.11 Gap I 的測試覆蓋面（不需要在 disabled context 下測試 token race condition）。

§13.7 總工數結算維持 **8d**（pre-M1a）→ 可能 **8.3d**（Gap I 展開）。

### 17.8 為什麼 `touch` 預設 true、`decoration` 預設 false

兩者表面相似（都是點擊角色互動），但語意不同：

- **touch**：身體觸碰 — 反應**因人而異、因情境而異**（同樣摸頭，剛起床和睡飽是不同反應；剛吵架和心情好是不同反應）。LLM 推理「現在這個情境下角色會怎麼想」有價值，內心戲合理
- **decoration**：飾品點擊 — 答案**已知且固定**（這是芙莉蓮的宝箱、這是裝魔導書的包包、這是ヒンメル送的指環）。LLM 不是在推理，是在「依照 decorations.json 提示做角色化朗讀」，加 `<think>` 反而拖、也讓 §16 流程複雜化

如果某角色的飾品設計真的需要內心戲（例如某飾品是創傷物件、每次點擊角色都會有不同情緒反應），用 manifest 覆寫 `decoration: true` 即可。預設保守、特例放權給 ghost 作者。

### 17.9 章節索引

| 子節 | 內容 |
|---|---|
| §17.1 | 動機與決議（§17 vs §16 的正交分層） |
| §17.2 | 兩層優先級規則（短路求值） |
| §17.3 | 預設清單（PHP 常數）+ manifest 覆寫格式 + 解析優先級 |
| §17.4 | 整合點：normalizer / prompt builder / SSE parser / 前端 helper / 違規 log |
| §17.5 | 驗收清單追加（8 條） |
| §17.6 | 與 §16 的交互（decoration / initial / diary 退化路徑表） |
| §17.7 | 落地時序（不增工數） |
| §17.8 | touch 與 decoration 預設值差異的設計理由 |
| §17.9 | 本節索引 |

---

## 計畫整體狀態（截至 2026-05-29 晚間）

§1-§10：原始計畫（§3.4 / §5 / §6 部分歷史段落已被 §13 / §15 取代）
§11-§12：Antigravity + CODEX 第一輪評審
§13-§15：第一輪收斂決議
§16：placeholder 邊界補完（含 §16.2 已執行的「を」字治標修正）
§17：context-aware inner monologue（per-context `<think>` 開關）
§18：**最終施作統整（As-Built，2026-05-31）— 給御三家確認**（計畫→commit 對照、偏差、驗證基線、待確認事項）

下刀順序（依 §13.7 / §16.5 / §17.7）：
1. **pre-M1a**（0.5d）：§16.2 已完成 + §16.3-A 字串黑名單治本
2. **M1a**（1d）：§13.2 normalizer 契約 + §17.4.1 context 欄位 + §17.3.1 預設常數
3. **M1b**（1d）：非串流 REST 整合 + §17.4.2 caller 改傳 context
4. **Prompt 切換**（0.5d）：§3.1 emotion tag prompt + Frieren `emoji-keywords.json`
5. **M2**（1.5d）：§13.3 SSE parser + §17.4.3 context 路由
6. **M3**（1.5d）：§16 think bubble UI + §16.11 helper + §17.3.2 manifest 解析 + §17.4.4 前端整合
7. **M4**（2d）：§13.5 think_delta 漸進模式 + 點擊隱藏 + per-personality 開關 UI

**總計 8d（可能 8.3d 含 §16.11 Gap I 展開）**。

---

## 18. 最終施作統整（As-Built，2026-05-31 — 給御三家確認）

> 本章是**實裝對照表**：把 §13.7 的計畫順序對到實際落地的 commit，記錄與計畫的偏差，供 Antigravity / CODEX / Claude 三方確認。分支 `feature/emotion-tag-system`（自 `main`），尚未合併、尚未 bump 版號。**emotion `[tag]` 主線全部落地並可運作；think bubble UI 已完成但目前休眠（見 §18.3 偏差 D1）**。
>
> ⚠️ **2026-06-01 更新**：本章為 2026-05-31 的歷史快照，其中「think bubble 休眠待重啟」之表述**已被 §19.1.1 取代**——LLM `<think>` 通道**定為廢案（abandoned），代碼保留但無重啟規劃**；`system placeholder` 氣泡為正式啟用 UI。閱讀 §18 各「休眠／重啟前提」字樣時請以 §19 為準。

### 18.1 計畫 → commit 對照（依落地順序）

| # | 計畫項（§ref） | commit | 落地內容摘要 |
|---|---|---|---|
| 1 | pre-M1a（§16.3-A） | `acb1de0` | 字串黑名單治本：`mpu_typewriter` 第 4 參收 `{ systemPlaceholder }`；新增 `mpuMark/mpuClearSystemPlaceholder` DOM 標記層；PHP 端 `data-initial-msg-system` 標記。 |
| 2 | M1a（§13.2 / §13.6） | `ca75176` + `267a6f8` | 新增 `includes/llm/response-normalizer.php`（`mpu_normalize_ai_response()` 單一契約）；display/history/checksum/tts 鎖死相等；只抽開頭 `<think>`；雙格式 `[tag]`＋`[表情:xxx]`。測試移入 PHPUnit。 |
| — | baseline refresh | `d2a0b6d` | 吸收 release drift，重產 PHPCS baseline。 |
| 3 | M1b（§13.7 / §17.4.2） | `dfc7528` | 非串流 REST 接 normalizer（chat/dialog/touch）；新增 `mpu_is_inner_monologue_enabled_for_context()`（§17 per-context 開關，manifest `features.inner_monologue_contexts` 可覆寫）。 |
| 4 | Prompt 切換（§3.1） | `1ae7239` | `personality-loader.php` 末尾 `[表情:xxx]` 指示 → `mpu_build_emotion_tag_instruction()` 內嵌 `[tag]`；Frieren `manifest.json` 補 `emoji.supported`（31 tag）**＝ 內嵌 tag 對 Frieren 真正生效的分水嶺**。 |
| — | wake-ghost 補洞 | `0f61f86` | `generate_wake_reaction` 也接 normalizer，剝離 emotion tag。 |
| 5 | M2 SSE parser（§13.3 / §17.4.3） | `1cb1837` | 新增 `class-mpu-stream-output-parser.php`：串流狀態機把 delta 轉 normalized SSE event（`emotion`/`think`/`status`），跨 chunk 邊界保尾；接線 `class-mpu-rest-chat.php` `user_chat_stream`。 |
| 6 | M3 think bubble UI（§16 / §16.11） | `fa574ff` | `#ukagaka_think` DOM ＋ `.mpu-think-bubble` CSS ＋ `mpuShowThinkBubble/HideThinkBubble/ShowSystemPlaceholder` helper；chat/touch/decoration placeholder 全改走 bubble；manifest 補 `thinking_placeholder`。 |
| 7 | M4 漸進模式（§13.5） | `8473787` | `think_delta` 漸進 emit ＋ bubble 點擊隱藏（限 `source==='llm'`）。 |
| — | review 修正 | `ff9230d` | ① `done` 的 `data.emoji` 不再覆蓋串流已套用 emotion；② `thinking_placeholder`/`language` 補進 `/init` response。 |
| — | 尾巴角度微調 | `b8a1772` | think bubble 尾巴朝向角色。 |

### 18.2 計畫外但已落地（過程中追加）

| 主題 | commit | 內容 |
|---|---|---|
| Touch mode 思考時隱藏主框 | `4948ec2` | touch 思考狀態先隱藏主對話框、結束再還原。 |
| **初始 placeholder 進場修正**（4 連 commit + 收尾） | `af7d427` → `7774dd8` → `7857402` → `0b1f31e` → `811bd3a` | 進入網頁/F5 的「（えっと…）」從主對話框搬進思考氣泡；修掉 `loadExternalDialog` 在 placeholder 期間先 fadeIn **空白主對話框**的 race（根因見 §18.3 D2）。最終：進場只剩思考氣泡，待 `showFirstMessage` 顯示真正自發台詞時才 `mpuClearSystemPlaceholder()` → 淡入主框 → 打字。 |
| think bubble 視覺微調 | `68ba4f3` | 邊框 `solid`→`dashed`（內心話泡泡感）；透明度 `0.94`→`0.69` 比照主對話框（保留白底材質）。 |
| Frieren 圖資（非本計畫） | `3c06803` / `0d05b41` / `689edf3` | 角色透明度 1.0、翻書動畫暗場修正、idle APNG 更新——順道搭車，與情緒標籤無關。 |

### 18.3 與計畫的偏差（⚠️ 重點，請御三家確認）

- **D1 — Ollama think 通道：打通後 revert（`a0e257f` → `c64b7ff` → `8236fcf`）。**
  曾讓 Ollama `message.thinking` 包成 `<think>…</think>` 餵進 think pipeline（唯一真正餵 think bubble 的 provider 路徑；雲端 reasoning 走獨立欄位不經本 pipeline）。**2026-05-31 實測後關閉。根因**：`ollama.php` `num_predict`（預設 1000）是「思考＋回覆」**共用**輸出上限 → qwen3 reasoning 吃光 → content 空/截斷 → 掉訊 → 歷史連續 user/缺 assistant → checksum 視窗對不齊（`logs/checksum-mismatch.log` 當天 11 筆皆在 `a0e257f` 後）。加上 reasoning 又長又機械、非預期吐槽風、UI 爆版。
  **保留**：`c64b7ff` 的 diary/memory `<think>` 剝除（無 think 時 no-op，無害防護）。
  **現況**：**think bubble UI（M3/M4）完整但休眠——目前無任何 provider 餵 think**；emotion `[tag]` 不受影響、正常運作。
  **重啟前提**：先解決「思考與回覆**分開計算 token 預算**」，並補 think bubble 的 max-height/overflow（長推理會爆版，本次 overflow 修正已隨通道一起丟棄）。

- **D2 — §16 未涵蓋的進場 race。** §16 設計了 placeholder → think bubble 的資料契約，但沒處理 `loadExternalDialog` 在 jQuery ready 時無條件 `mpu_showmsg()` 會把剛被 `mpu_hidemsg(0)` 藏起的主對話框再淡入成空殼。已於 `811bd3a` 補守衛（`!isInitialSystemMessage` 時不提前顯示，改由 `showFirstMessage` 交棒）。

- **D3 — initial placeholder 文案決議。** 一度改成 `（何を話せばいいかな…）`（全形括號標記內心話），實測發現 ① `…` 與 spinner 點點重複；② 12 全形字 ＋ `）` 撐破 150px 段行。**決議回到 `何を話せばいいかな`（不加括號）**——理由：思考氣泡的造型本身已表意內心話，括號重複且 `）` 卡在文字與 spinner 之間最尷尬。manifest `thinking_placeholder.initial` 為此值。

- **D4 — legacy `[表情:xxx]` 串流仍不剝（刻意）。** 串流 parser regex 只吃 ASCII `[tag]`；中文 `[表情:xxx]` 串流時不剝（非串流 normalizer 會剝）。需明確 mapping 策略才動，否則變另一個猜測來源。

### 18.4 最終驗證基線

- **PHPUnit**：79 tests / 252 assertions 綠（`tests/Unit/` 含 `ResponseNormalizerTest`、`EmotionTagPromptTest`、`StreamOutputParserTest`）。
- **PHPCS**：within baseline（47925 findings，gate 綠；M1b 後實際 < baseline，未 regenerate 保留 slack）。
- **JS bundle**：`tools/node/build.js` 重建 `js/dist/ukagaka-bundle.js`＋`.min.js`。

### 18.5 待御三家確認 / 未做事項

1. **think bubble 休眠處置**：M3/M4 完整但無 provider 觸發（雲端 reasoning 走獨立欄位、Ollama 通道已關）。保留休眠待重啟，或在文件標記「實驗性／預設停用」？
2. **per-personality inner_monologue 後台 UI**（§17 提及）未實作；目前僅靠 manifest `features.inner_monologue_contexts` 落地。
3. **合 main 時機與版號**：分支累積中，合併時才 bump（計畫目標 v2.25.0~）。請確認是否含 D1 休眠狀態一起合，或先抽掉 think bubble UI。
4. **emotion `[tag]` 上線可獨立**：主線（normalizer＋prompt＋串流 parser）與 think 解耦，可單獨先發。

---

## 19. 後續對應與定案決議（2026-06-01 家裡討論定案）

經過本次討論，針對 §18.5 的待確認事項進行以下決議定案，並制定後續與 `main` 同步的對應計畫：

### 19.1 §18.5 待確認事項之決議定案

1. **think bubble 的 LLM `<think>` 通道處置（定案：廢案，但代碼保留）**
   - **決策**：`source='llm'` 的內心獨白通道**定為廢案（abandoned），不在 roadmap 上**；但**代碼完整保留、不抽離**。文件與 `CHANGELOG.md` 一律以「廢案／已擱置」表述，**不可**再用「休眠待重啟」這類暗示有後續排程的措辭。`system placeholder` 氣泡（進場 `何を話せばいいかな`、touch / decoration 思考狀態）則為**正式啟用**的 UI，與本廢案無關。
   - **廢案理由**：① 實作成本相對效益太高；② 2026-05-31 唯一真正打通的 Ollama 通道實測效果極差（reasoning 又長又機械、非預期吐槽風、UI 爆版），加上 `num_predict` 思考／回覆共用預算導致截斷與 checksum 漂移（§18.3 D1）。雲端 reasoning 走 provider 獨立欄位、不經本管線，故本通道實際無可用 provider。
   - **為何代碼仍保留（而非抽離）**：① 目前為**完全惰性**——無任何 prompt 注入 `<think>` 指示、無 provider 餵入，render 路徑永不觸發，**零 runtime／token 成本**；normalizer 仍會剝除偶發 `<think>`（無 think 時 no-op，純防護）。② think 相關的共用基礎設施（`#ukagaka_think` DOM/CSS、`class-mpu-stream-output-parser.php` 狀態機、normalizer 的 think strip）**同時被 system placeholder 與 emotion `[tag]` 依賴**；單獨抽離 LLM-think 路徑要動到這些共用點，徒增工時與回歸風險，對一個已惰性的死路徑不划算。抽離的成本本身就違背「成本太高才廢案」的初衷。

2. **per-personality inner_monologue 後台 UI（定案：暫不實作）**
   - **決策**：**暫不實作後台 UI**。完全依賴 `manifest.json` 的 `features.inner_monologue_contexts` 機制進行配置。
   - **考量**：全域開關（`enable_inner_monologue`）對一般站長已足夠。細粒度的 Context 級別開關主要面向角色包創作者（Ghost Author），直接寫在角色 manifest 中進行版本控制與宣告最為乾淨，後台無需為此新增複雜的 UI。

3. **合 main 時機與版號（定案：含廢案代碼一同併入，版號定為 v2.25.0）**
   - **決策**：**不拆分分支，含 LLM think source 廢案代碼（保留、惰性）一同合併至 `main`，版號定為 v2.25.0**。
   - **考量**：分支已領先 `main` 35 個 commit，且 `main` 為本分支直系祖先（零分歧），同步為乾淨 fast-forward。由於 LLM think source 完全惰性（無 provider、無 prompt），對一般使用者零新增輸出風險；system placeholder 氣泡則已作為 UI 改善正式啟用。一同合併可立刻解決 Branch Drift，避免後續 release / main 同步成本繼續上升。

4. **emotion `[tag]` 上線可獨立（定案：同上，不拆分分支）**
   - **決策**：不單獨拆分，與上述合併決議一致，全數併入 v2.25.0 發布。但發布日誌與功能宣傳將重點著重在「表情標籤 `[tag]` 系統」的正式上線與語意治本最佳化。

### 19.2 `CHANGELOG` / README 待補項目

既有 v2.25.0 release note 已有初稿，但後續 UI / Frieren / bubble 圖資又追加多個 commit。正式同步前至少補以下 delta：

1. **正式實裝：情緒標籤系統**
   - 支援句中內嵌 `[tag]` 與傳統 `[表情:xxx]`。
   - 非串流 REST 與 SSE 串流皆走 normalizer / stream parser，明確 tag 優先於 keyword scorer。
   - Frieren `manifest.json` 宣告 `emoji.supported`，prompt 改為 inline tag 指示。

2. **底層重構：Response Normalizer**
   - `mpu_normalize_ai_response()` 統一 think / emotion / display text。
   - 鎖定 display / history / checksum / TTS 使用同一份乾淨文本。
   - PHPUnit 已納入 normalizer / stream parser 契約測試。

3. **system placeholder 與 think bubble UI**
   - 移除依賴字串比對的 system placeholder 黑名單，改為 DOM attribute 標記。
   - 初始、chat 等待、頁面感知、初訪客、touch / decoration placeholder 改走 `#ukagaka_think`。
   - 氣泡改為 PNG 9-slice (`think-bubble.png`) + 獨立尾巴 (`think-tail.png`)。
   - 頁面感知 / 初訪客 placeholder 期間主對話框立即隱藏，正式回應才交棒回主框。

4. **LLM think source 狀態（廢案）**
   - `stream-output-parser.php` 與前端 progressive think bubble 代碼保留（惰性，零成本）。
   - Ollama thinking feed 曾打通但因 `num_predict` 共用預算問題 revert（§18.3 D1）。
   - 對外說明：LLM 內心獨白通道**已廢案、無重啟規劃**（無 provider 餵入、無 prompt 注入，故無可見行為）；`system placeholder` 氣泡為正式 UI。措辭不得用「休眠待重啟」。

5. **Frieren 專屬修正**
   - 睡眠中點 OK / 對話 / 飾品 / touch 統一為「睜眼 → 抱怨」，不翻書。
   - 擴充 `sleep_mode.json` 的 `deep_sleep` / `oversleep` wake prompt；共通 `ukagaka-chat.js` fallback 保持角色中性。
   - 修正翻書動畫到 idle APNG 的亮度 / 姿勢接縫。
   - 更新 Frieren idle APNG 圖資。

### 19.3 分支同步與合併順序

1. **先補文件**
   - 更新 `docs/CHANGELOG.md`、`docs-en/CHANGELOG.md`、`docs-jp/CHANGELOG.md`。
   - 同步更新 `README.md`、`README_zh-TW.md`、`README_ja.md`、`readme.txt` 的 v2.25.0 摘要。
   - 確認 `mp-ukagaka.php` 版本仍為 `2.25.0`。

2. **本機 merge rehearsal**
   - `git fetch origin main --tags`。
   - 在臨時分支或本分支上測試 `origin/main` 合併，檢查 `git diff origin/main...HEAD` 是否只含 v2.25.0 預期差異。
   - 跑 `npm run verify`，必要時另外跑前端 bundle build / PHP lint。

3. **合併 main**
   - 若 main 無新衝突：直接 merge feature branch into `main`。
   - 若 main 有 release metadata 或 changelog 差異：只手動解 changelog / readme / version 衝突，不回退功能 commit。
   - 合併後再跑一次 `npm run verify`。

4. **發版**
   - 確認 working tree clean。
   - 打 `v2.25.0` tag。
   - 推 `main`、推 tag、建立 GitHub Release。
