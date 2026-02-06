# 人格製作指南

> 🎭 如何為 MP Ukagaka 創建新的角色人格

---

## 📑 目錄

1. [概述](#概述)
2. [必要檔案](#必要檔案)
3. [資料夾結構](#資料夾結構)
4. [manifest.json 格式說明](#manifestjson-格式說明)
5. [system_prompt.md 使用方法](#system_promptmd-使用方法)
6. [prompts.json 格式說明（LLM 模式）](#promptsjson-格式說明llm-模式)
7. [weights.json 格式說明（LLM 模式）](#weightsjson-格式說明llm-模式)
8. [decorations.json 格式說明（可選）](#decorationsjson-格式說明可選)
9. [Shell 圖片檔案](#shell-圖片檔案)
10. [JavaScript 腳本（可選）](#javascript-腳本可選)
11. [上傳與使用](#上傳與使用)
12. [完整範例](#完整範例)

---

## 概述

在 MP Ukagaka 中，每個角色人格都存放在 `ghost/` 資料夾下，以人格 ID 為名稱的獨立資料夾中。一個完整的人格包含以下內容：

- **必要檔案**：`manifest.json`、`shell/` 資料夾（包含角色圖片）
- **LLM 模式檔案**（使用 AI 時）：`prompts.json`、`weights.json`、`system_prompt.md`（或 `manifest.json` 中的 `system_prompt` 欄位）
- **可選檔案**：`decorations.json`、`decorations/` 資料夾、JavaScript 腳本、`dynamics.json`

---

## 必要檔案

創建一個新的人格，**最少需要以下檔案**：

1. **`manifest.json`** - 人格的元數據和設定
2. **`shell/{人格ID}/{人格ID}.png`** - 角色的主圖片（至少一張）

### 最小範例

```
ghost/
└── MyCharacter/
    ├── manifest.json
    └── shell/
        └── MyCharacter/
            └── MyCharacter.png
```

---

## 資料夾結構

完整的人格資料夾結構如下：

```
ghost/
└── {人格ID}/              # 人格資料夾（例如：Frieren、Sakura_Laurel）
    ├── manifest.json       # 必要：元數據與設定
    ├── system_prompt.md    # 推薦：System Prompt（Markdown 格式）
    │
    ├── shell/              # 必要：角色圖片資料夾
    │   └── {人格ID}/       # 圖片子資料夾（名稱通常與人格 ID 相同）
    │       ├── {人格ID}.png          # 主圖片（必要）
    │       ├── {人格ID}[0].png       # 動畫幀（可選）
    │       ├── {人格ID}[1].png       # 動畫幀（可選）
    │       └── ...
    │
    ├── decorations/        # 可選：裝飾物圖片資料夾
    │   ├── item1.png
    │   └── item2.png
    │
    ├── prompts.json        # LLM 模式：對話類別提示詞
    ├── weights.json        # LLM 模式：類別權重配置
    ├── dynamics.json       # LLM 模式：動態模板（可選）
    ├── decorations.json    # 可選：裝飾物配置
    └── {人格ID}.js         # 可選：JavaScript 動畫腳本
```

---

## manifest.json 格式說明

`manifest.json` 是人格的核心設定檔案，定義了人格的基本資訊和配置。

### 必要欄位

- `id`：人格的唯一識別碼（英數字、底線、連字號，建議使用大寫開頭的駝峰式命名）
- `name`：角色顯示名稱（預設語言）
- `shell_folder`：shell 圖片資料夾的名稱（通常與 `id` 相同）

### 完整欄位說明

```json
{
  "id": "MyCharacter",                    // 必要：人格 ID（唯一識別碼）
  "name": "角色名稱",                     // 必要：角色顯示名稱
  "name_en": "Character Name",            // 可選：英文名稱
  "name_zh": "角色名稱",                  // 可選：中文名稱
  "version": "1.0.0",                     // 可選：版本號
  "author": "作者名稱",                   // 可選：作者資訊
  "description": "角色描述",              // 可選：角色簡介
  "description_en": "Character description",  // 可選：英文描述
  "language": "ja",                       // 可選：主要語言（ja/zh-TW/en）
  "shell_folder": "MyCharacter",          // 必要：shell 圖片資料夾名稱
  "decorations_folder": "decorations",    // 可選：裝飾物資料夾名稱（預設 "decorations"）
  "script": "mycharacter.js",             // 可選：JavaScript 腳本檔名
  
  "settings": {                           // 可選：行為設定
    "max_response_length": 500,           // 回應長度限制（字符數，預設 500）
    "max_tokens": 800,                     // API 調用時的 token 限制（預設 800）
    "speech_style": "常体",                // 說話風格（元數據，目前未實際使用）
    "tone": "淡々とした",                  // 語調（元數據，目前未實際使用）
    "emoji_style": "minimal"              // 表情風格（元數據，目前未實際使用）
  },
  
  "character_traits": {                   // 可選：角色屬性（元數據，目前未實際使用）
    "age": "18",
    "race": "人類",
    "occupation": "學生",
    "personality": ["開朗", "活潑"],
    "aliases": ["暱稱1", "暱稱2"]
  },
  
  "system_prompt": "你是...",             // 可選：System Prompt（字串或陣列）
                                           // 注意：建議使用 system_prompt.md 檔案
}
```

### 範例

```json
{
  "id": "Frieren",
  "name": "フリーレン",
  "name_en": "Frieren",
  "name_zh": "芙莉蓮",
  "version": "1.0.0",
  "author": "和製ホーリックス",
  "description": "千年以上生きるエルフの魔法使い。淡々とした口調で語り、魔法収集が趣味。",
  "language": "ja",
  "shell_folder": "Frieren",
  "decorations_folder": "decorations",
  "script": "frieren.js",
  "settings": {
    "max_response_length": 500,
    "max_tokens": 800,
    "speech_style": "常体",
    "tone": "淡々とした",
    "emoji_style": "minimal"
  }
}
```

### settings 欄位說明

`settings` 物件包含角色的行為設定，其中字數限制機制已統一化為三層防護：

#### 字數限制設定

- **`max_response_length`**（預設：500）

  - 後端截斷限制（字符數）
  - 當 AI 回應超過此長度時，系統會自動截斷並加上 `...`
  - 所有對話類型（頁面感知、首次訪客、互動對話、觸摸區域、裝飾物點擊、自發性對話）都會套用此限制
- **`max_tokens`**（預設：800）

  - API 調用時的 token 限制
  - 控制 AI 模型生成回應的最大 token 數
  - 約等於 600-800 個字符（取決於語言和內容）
  - 所有 AI 對話類型都會使用此設定

#### 三層防護機制

系統實現了統一的三層字數限制機制：

1. **Prompt 建議**：30-150字（軟性引導）

   - 在 System Prompt 和 User Prompt 中建議 AI 保持在 30-250 字範圍內
2. **API max_tokens**：600（可配置 `max_tokens`）

   - 限制 AI 模型生成的最大 token 數
   - 從 `manifest.json` 的 `settings.max_tokens` 讀取，預設 600
3. **後端截斷**：150 字（可配置 `max_response_length`）

   - 最終的安全防護層
   - 從 `manifest.json` 的 `settings.max_response_length` 讀取，預設 500

### JSON 格式規範

1. **檔案編碼**：必須使用 UTF-8 編碼
2. **語法**：
   - 使用雙引號 `"` 包裹字串
   - 最後一個屬性後**不能**有逗號
   - 陣列和物件的最後一個元素後**不能**有逗號
3. **註解**：JSON 標準不支援註解，但可以使用 `_comment` 欄位作為說明
4. **驗證**：可以使用線上 JSON 驗證工具檢查語法

**正確範例：**

```json
{
  "id": "MyCharacter",
  "name": "角色名稱",
  "settings": {
    "max_response_length": 500,
    "max_tokens": 800
  }
}
```

**錯誤範例：**

```json
{
  "id": "MyCharacter",
  "name": "角色名稱",  // ❌ JSON 不支援註解
  "settings": {
    "max_response_length": 129,  // ❌ 最後一個屬性後不能有逗號
  }
}
```

---

## system_prompt.md 使用方法

`system_prompt.md` 是用於定義角色 System Prompt 的 Markdown 檔案，具有最高優先級。

### 優先級順序

1. **`system_prompt.md`**（最高優先級）⭐
2. `manifest.json` 中的 `system_prompt` 欄位
3. 後台全域設定（Fallback）

### 檔案位置

```
ghost/{人格ID}/system_prompt.md
```

### 格式要求

- **編碼**：UTF-8
- **格式**：純 Markdown 文字檔案
- **內容**：角色的完整 System Prompt，可以使用 Markdown 格式增強可讀性

### Markdown 格式建議

使用 Markdown 可以讓 System Prompt 更結構化、易讀：

```markdown
# 角色定義

你是「角色名稱」。以下規則必須遵守。

## 對話協議

1. **回應長度**：必ず40文字以内で収まること。
2. **第一人称**：必ず「私」を使用すること。
3. **口調**：常体のみ使用。

## 背景設定

- 角色背景說明
- 性格特徵
- 說話風格

## 行為規則

- 規則1
- 規則2
```

### 變數支援

System Prompt 支援以下變數替換：

- `{{ukagaka_display_name}}`：角色名稱
- `{{language}}`：回應語言（zh-TW、ja、en）
- `{{time_context}}`：時間情境（如「1月2日（木曜日）・冬の朝」）
- `{{wp_version}}`：WordPress 版本
- `{{php_version}}`：PHP 版本
- `{{theme_name}}`：主題名稱
- `{{theme_version}}`：主題版本
- `{{theme_author}}`：主題作者
- `{{post_count}}`：文章數量
- `{{comment_count}}`：留言數量
- `{{category_count}}`：分類數量
- `{{tag_count}}`：標籤數量
- `{{days_operating}}`：網站運營天數

**範例：**

```markdown
あなたは「{{ukagaka_display_name}}」というキャラクターです。

現在の時間は {{time_context}} です。
```

### 完整範例

參考 `example/system-prompt-markdown-example.md` 查看完整的 Markdown 格式範例。

---

## prompts.json 格式說明（LLM 模式）

`prompts.json` 定義了 LLM 生成對話時使用的提示詞類別。每個類別包含多個提示詞模板，系統會根據權重隨機選擇。

### 檔案結構

```json
{
  "_comment": "角色名稱 - Prompt Categories",
  "_format_version": "1.0",
  "_variable_placeholders": [
    "{time_context}", "{visitor_country}", "{bot_name}"
  ],
  
  "category_name": [
    "提示詞模板1",
    "提示詞模板2",
    "提示詞模板3"
  ]
}
```

### 類別命名建議

- `greeting`：問候類
- `casual`：閒聊類
- `observation`：觀察類
- `memory`：回憶類
- `time_aware`：時間感知類
- `magic_collection`：魔法收集類（或對應角色的興趣）
- `self_awareness`：自我認知類
- `emotional_density`：情感密度類
- 等等...

### 變數佔位符

可以在提示詞中使用變數佔位符，系統會自動替換：

- `{time_context}`：時間情境
- `{wp_version}`：WordPress 版本
- `{theme_name}`：主題名稱
- `{visitor_country}`：訪客國家
- `{bot_name}`：BOT 名稱（如果檢測到）
- 等等...

### 範例

```json
{
  "_comment": "MyCharacter - Prompt Categories",
  "_format_version": "1.0",
  
  "greeting": [
    "淡々とした態度で、再訪を軽く認識する",
    "久しぶりの訪問に対して、「えっ」と少しびっくりした様子を見せる"
  ],
  
  "casual": [
    "目についたものについて、淡々とした感想を述べる",
    "ふと思い出した、特に意味のないことを呟く"
  ],
  
  "time_aware": [
    "人間にとっての時間について、短すぎるという実感を述べる",
    "「たった10年」という期間を、ほんの短い間として扱う"
  ]
}
```

---

## weights.json 格式說明（LLM 模式）

`weights.json` 定義了各個對話類別的權重，權重越高，該類別被選中的機率越大。

### 檔案結構

```json
{
  "_comment": "角色名稱 - Category Weights Configuration",
  "_format_version": "1.0",
  
  "base_weights": {
    "category_name": 10,
    "another_category": 15
  },
  
  "time_adjustments": {
    "朝": {
      "category_name": 20
    },
    "夜": {
      "category_name": 5
    }
  }
}
```

### base_weights

基礎權重，所有時間段都會使用。數值範圍建議：**1-20**。

- 數值越高 = 被選中的機率越大
- 建議常用類別設為 10-15
- 少用類別設為 1-5

### time_adjustments

根據時間段調整權重，會與 `base_weights` 合併。

**支援的時間段：**

- `深夜`：23:00-04:59
- `睡眠時間帯`：00:00-05:59
- `朝`：05:00-11:59
- `昼`：12:00-17:59
- `夜`：18:00-22:59

### 範例

```json
{
  "_comment": "MyCharacter - Category Weights",
  "_format_version": "1.0",
  
  "base_weights": {
    "casual": 15,
    "observation": 15,
    "greeting": 6,
    "memory": 8,
    "time_aware": 10
  },
  
  "time_adjustments": {
    "深夜": {
      "memory": 15,
      "time_aware": 15,
      "casual": 5
    },
    "朝": {
      "greeting": 20,
      "casual": 15
    }
  }
}
```

---

## decorations.json 格式說明（可選）

`decorations.json` 定義了角色的裝飾物（可點擊的互動元素）。

### 檔案結構

```json
{
  "_comment": "角色名稱 - Decoration Click Prompts",
  "_format_version": "1.0",
  
  "decorations_base_folder": "decorations",
  
  "items": [
    {
      "type": "item_type",
      "image": "item.png",
      "position": {
        "top": "82%",
        "right": "-62px",
        "left": "auto"
      },
      "size": {
        "width": "90px",
        "height": "auto"
      },
      "transform": "translateY(-50%)",
      "z_index": 10,
      "prompt": "用戶點擊此裝飾物時的提示詞（50文字以内）"
    }
  ]
}
```

### 欄位說明

- `type`：裝飾物類型（唯一識別碼）
- `image`：圖片檔名（存放在 `decorations/` 資料夾）
- `position`：CSS 定位（`top`、`left`、`right`）
- `size`：圖片尺寸（`width`、`height`）
- `transform`：CSS transform（可選）
- `z_index`：圖層順序
- `prompt`：點擊時的 LLM 提示詞

### 範例

```json
{
  "_comment": "MyCharacter - Decorations",
  "_format_version": "1.0",
  
  "decorations_base_folder": "decorations",
  
  "items": [
    {
      "type": "suitcase",
      "image": "suitcase.png",
      "position": {
        "top": "82%",
        "right": "-62px",
        "left": "auto"
      },
      "size": {
        "width": "90px",
        "height": "auto"
      },
      "transform": "translateY(-50%)",
      "z_index": 10,
      "prompt": "ユーザーがスーツケースをクリックしました。このスーツケースについて語ってください（50文字以内）。"
    }
  ]
}
```

---

## Shell 圖片檔案

Shell 圖片是角色的視覺表現，存放在 `shell/{人格ID}/` 資料夾中。

### 必要檔案

- **`{人格ID}.png`**：主圖片（必要）

### 可選檔案（動畫）

- `{人格ID}[0].png`、`{人格ID}[1].png`、...：動畫幀
- `{人格ID}[s].png`：特殊狀態圖片
- `{人格ID}[w1].png`、`{人格ID}[w2].png`、...：喚醒動畫幀

### 命名規則

1. **主圖片**：`{人格ID}.png`（例如：`Frieren.png`）
2. **動畫幀**：`{人格ID}[數字].png`（例如：`Frieren[0].png`、`Frieren[1].png`）
3. **特殊狀態**：`{人格ID}[字母].png`（例如：`Frieren[s].png`）

### 圖片格式

- **格式**：PNG（推薦）或 JPG
- **尺寸**：建議 200-400px 寬度，高度自訂
- **背景**：建議使用透明背景（PNG）

### 檔案結構範例

```
shell/
└── Frieren/
    ├── Frieren.png        # 主圖片（必要）
    ├── Frieren[0].png     # 動畫幀 0
    ├── Frieren[1].png     # 動畫幀 1
    ├── Frieren[2].png     # 動畫幀 2
    ├── Frieren[s].png     # 特殊狀態
    ├── Frieren[w1].png    # 喚醒動畫 1
    ├── Frieren[w2].png    # 喚醒動畫 2
    └── ...
```

---

## JavaScript 腳本（可選）

如果需要自訂動畫或互動行為，可以創建 JavaScript 腳本。

### 檔案位置

```
ghost/{人格ID}/{人格ID}.js
```

### 在 manifest.json 中指定

```json
{
  "id": "MyCharacter",
  "script": "mycharacter.js"
}
```

### 基本結構

JavaScript 腳本需要註冊到 `window.mpuFrierenManager`（或類似的角色管理器）中。參考 `ghost/Frieren/frieren.js` 查看完整範例。

---

## 上傳與使用

### 方法一：ZIP 上傳（推薦）

1. 將所有人格檔案打包成 ZIP 檔案
2. 登入 WordPress 後台 → **設定** → **MP Ukagaka** → **創建新偽春菜**
3. 選擇 ZIP 檔案並上傳
4. 系統會自動解壓並驗證
5. 確認預覽資訊無誤後，點擊「確認並創建」

### 方法二：手動上傳

1. 透過 FTP 或檔案管理器，將人格資料夾上傳到 `wp-content/plugins/mp-ukagaka/ghost/`
2. 登入 WordPress 後台 → **設定** → **MP Ukagaka** → **偽春菜們**
3. 手動添加新角色設定

### ZIP 檔案結構要求

ZIP 檔案解壓後應該直接包含 `manifest.json` 和 `shell/` 資料夾：

```
MyCharacter.zip
└── (解壓後)
    ├── manifest.json
    ├── system_prompt.md
    ├── shell/
    │   └── MyCharacter/
    │       └── MyCharacter.png
    ├── prompts.json
    └── weights.json
```

**注意**：ZIP 檔案中**不能**包含頂層資料夾名稱（例如 `MyCharacter/manifest.json`），應該直接是檔案本身。

---

## 完整範例

以下是一個最簡化的人格範例：

### 1. 資料夾結構

```
ghost/
└── SimpleCharacter/
    ├── manifest.json
    └── shell/
        └── SimpleCharacter/
            └── SimpleCharacter.png
```

### 2. manifest.json

```json
{
  "id": "SimpleCharacter",
  "name": "簡單角色",
  "shell_folder": "SimpleCharacter"
}
```

### 3. system_prompt.md（可選，推薦）

```markdown
# 角色定義

你是「簡單角色」。請以簡潔、友善的語氣與訪客互動。

## 對話規則

- 回應保持在 50 字以內
- 使用常體（不使用敬語）
- 第一人称使用「私」
```

### 4. prompts.json（LLM 模式，可選）

```json
{
  "_comment": "SimpleCharacter - Prompt Categories",
  "_format_version": "1.0",
  
  "greeting": [
    "淡々とした態度で挨拶する",
    "訪問者に軽く声をかける"
  ],
  
  "casual": [
    "目についたものについて感想を述べる",
    "ふと思い出したことを呟く"
  ]
}
```

### 5. weights.json（LLM 模式，可選）

```json
{
  "_comment": "SimpleCharacter - Category Weights",
  "_format_version": "1.0",
  
  "base_weights": {
    "greeting": 10,
    "casual": 15
  }
}
```

---

## 總結

創建新人格的基本步驟：

1. ✅ 創建 `manifest.json`（必要）
2. ✅ 準備 `shell/{人格ID}/{人格ID}.png`（必要）
3. ⭐ 創建 `system_prompt.md`（推薦，用於定義角色行為）
4. 📝 創建 `prompts.json` 和 `weights.json`（LLM 模式時使用）
5. 🎨 添加 `decorations.json` 和裝飾物圖片（可選）
6. 📦 打包成 ZIP 並上傳

**參考範例**：查看 `ghost/Frieren/` 資料夾了解完整的人格結構。

---

**最後更新**：2026-01-15
