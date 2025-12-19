# MP Ukagaka

一個用於在 WordPress 網站上創建和顯示互動式偽春菜（伺か）角色的外掛，具備 AI 頁面感知功能。

[![外掛版本](https://img.shields.io/badge/version-2.2.0-blue.svg)](https://github.com)
[![WordPress](https://img.shields.io/badge/WordPress-5.0%2B-blue.svg)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple.svg)](https://www.php.net/)

🌍 **其他語言**: [English](README.md) | [日本語](README_ja.md)

## 🎉 特別公告

為慶祝 **『葬送のフリーレン』第2期** 於 **2026年1月16日** 開始放送，預設角色已從 **初音** 變更為 **芙莉蓮（フリーレン）**。

## 📸 預覽截圖

![MP Ukagaka 展示](screenshot.PNG)

_芙莉蓮角色根據文章內容顯示 AI 生成的對話_

## 📖 簡介

MP Ukagaka 讓你能夠為 WordPress 網站創建自訂的互動式偽春菜。這個版本基於經典的 MP Ukagaka 外掛進行了全面現代化，具備增強的安全性、效能改進、模組化架構和先進的 AI 功能。

### 經典功能

- **多角色支援**：創建和管理多個春菜角色
- **自訂對話**：為每個角色設計專屬對話訊息
- **外部對話檔案**：支援從 TXT 或 JSON 檔案載入對話
- **自動對話**：可設定間隔的自動訊息輪播
- **通用訊息**：可套用到所有角色的共用訊息
- **頁面排除**：控制春菜在哪些頁面顯示
- **多語言**：支援繁體中文、日文、英文等多種語言
- **Canvas 動畫**：支援單張靜態圖片和多張圖片動畫
  - 自動資料夾偵測，載入動畫序列
  - 僅在角色說話時播放動畫（節省資源）
  - 幀率：100 毫秒/幀
  - 支援格式：PNG、JPG、JPEG、GIF、WebP

### 🚀 AI 頁面感知（v2.0.0 新功能）

本外掛現在包含智慧 AI 功能，可分析頁面內容並生成個性化回應：

- **多 AI 提供商支援**：
  - **Google Gemini**：快速高效（gemini-2.5-flash、gemini-2.5-pro）
  - **OpenAI GPT**：強大的語言模型（GPT-4o、GPT-4o-mini、GPT-3.5-turbo）
  - **Anthropic Claude**：進階推理能力（Claude Sonnet 4.5）
- **智慧內容分析**：自動擷取並分析頁面標題和內容
- **可設定觸發條件**：設定哪些頁面觸發 AI 對話
- **機率控制**：調整 AI 回應頻率（1-100%）以管理 API 成本
- **可自訂人格**：透過系統提示詞設計角色個性
- **視覺自訂**：自訂 AI 對話文字顏色
- **顯示時間控制**：防止 AI 對話被自動對話覆蓋
- **多語言 AI**：生成繁體中文、日文或英文回應
- **首次訪客打招呼**：用個性化 AI 問候歡迎新訪客（需要 Slimstat 外掛）

### 🤖 通用 LLM 接口（v2.2.0）

> 💡 **重大更新**：LLM 功能已升級為**通用 LLM 接口**，支援多個 AI 服務！

外掛現在支援統一接口的多種 AI 服務，讓你可以使用以下任一服務生成對話：

- **Ollama**（本機/遠程）：完全免費，無需 API Key
  - 連接本地或遠程 Ollama 實例
  - 支援 Cloudflare Tunnel、ngrok 或其他隧道服務
  - 智慧連接檢測，自動調整超時設定
  - 模型支援：Qwen3、Llama、Mistral 等
  - 思考模式控制（Qwen3、DeepSeek 等模型）

- **Google Gemini**：需要 API Key
  - 支援模型：Gemini 2.5 Flash（推薦）、Gemini 1.5 Pro 等

- **OpenAI**：需要 API Key
  - 支援模型：GPT-4.1 Mini（推薦）、GPT-4o 等

- **Claude (Anthropic)**：需要 API Key
  - 支援模型：Claude Sonnet 4.5、Claude Haiku 4.5、Claude Opus 4.5

**主要功能：**

- **統一設定介面**：所有提供商使用相同的設定頁面
- **API Key 加密**：所有 API Key 自動加密儲存，確保安全性
- **連接測試**：為所有 AI 提供商提供測試按鈕
- **取代內建對話**：可選擇使用 AI 生成的內容取代靜態對話（支援所有提供商）
- **優化 System Prompt**：XML 結構化 System Prompt，包含 70+ 個芙莉蓮風格對話範例
- **WordPress 資訊整合**：LLM 對話可以包含網站資訊（WordPress 版本、主題資訊、統計資料）
- **防止重複對話機制**：追蹤之前的回應，避免重複的閒聊
- **閒置偵測功能**：使用者閒置時（60 秒）自動暫停自動對話，節省 GPU 和網路資源

**設定需求：**

- 安裝並運行 [Ollama](https://ollama.ai/)（本地或遠程伺服器）
- 下載所需模型（例如：`ollama pull qwen3:8b`）
- 在外掛設定中配置端點 URL（本地：`http://localhost:11434` 或遠程：`https://your-domain.com`）
- 詳細的設定說明請參見 [USER_GUIDE.md](/docs/USER_GUIDE.md)

**目前限制：**

- 功能處於測試階段
- 遠程設定可能會有連接問題
- 回應時間可能因模型和連接類型而異

## 📦 安裝

1. **下載外掛**

   - 從本儲存庫下載
   - 或將本儲存庫克隆到 WordPress 外掛目錄

2. **安裝外掛**

   ```bash
   # 前往 WordPress 外掛目錄
   cd /path/to/wordpress/wp-content/plugins/

   # 解壓縮或克隆外掛
   unzip mp-ukagaka.zip
   ```

3. **啟用外掛**

   - 前往 WordPress 後台 → 外掛
   - 找到「MP Ukagaka」並點擊「啟用」

4. **設定**
   - 前往 **設定 → MP Ukagaka**
   - 配置通用設定並創建你的第一個春菜角色
   - （可選）在「AI 設定」區塊啟用 AI 功能

## ⚙️ 設定

### 基本設定

1. **通用設定**

   - 選擇預設春菜角色
   - 啟用/停用顯示
   - 設定自動對話間隔
   - 設定頁面排除規則

2. **創建角色**

   - 前往「春菜們」分頁
   - 添加新角色並設定圖片和對話
   - 配置角色專屬設定

3. **對話設定**
   - **重要**：所有對話必須以外部檔案形式存放（TXT 或 JSON 格式）
   - 將對話檔案放在 `dialogs/` 資料夾中
   - 儲存角色設定時會自動生成對話檔案
   - 你也可以手動在 `dialogs/` 資料夾中創建/編輯對話檔案

### AI 頁面感知設定

1. **啟用 AI 功能**

   - 前往 設定 → MP Ukagaka → AI 設定
   - 勾選「啟用頁面感知功能」

   - **言語設定**：選擇回應語言（繁體中文、日文、英文）
   - **キャラクター設定（System Prompt）**：定義角色個性
     - 此設定會與優化的 System Prompt 系統整合
     - 雲端 AI 服務：System Prompt 會自動優化以減少 token 使用
     - 本機 LLM：可以使用更長的提示詞以獲得更好的角色一致性
   - **頁面感知確率（%）**：設定 AI 觸發機率（1-100%，建議 10-30% 以控制成本）
   - **トリガーページ**：指定觸發 AI 的頁面（例如：「is_single」只在單篇文章觸發）
   - **AI会話の表示時間（秒）**：設定 AI 訊息顯示多久（建議 5-10 秒）
   - **初回訪問者への挨拶**：啟用並設定打招呼提示詞（可選）

4. **儲存設定**
   - 點擊「儲存」按鈕
   - 在單篇文章頁面測試 AI 回應

### 通用 LLM 設定

1. **選擇 AI 提供商**

   前往 **設定 → MP Ukagaka → LLM 設定**，選擇以下任一 AI 提供商：

   **Ollama**（免費，無需 API Key）：
   - 安裝並運行 [Ollama](https://ollama.ai/)（本地或遠程伺服器）
   - 下載模型：`ollama pull qwen3:8b`（或你偏好的模型）
   - 輸入端點：`http://localhost:11434`（本地）或 `https://your-domain.com`（遠程）
   - 輸入模型名稱（例如：`qwen3:8b`、`llama3.2`、`mistral`）
   - 使用「測試 Ollama 連接」按鈕驗證設定

   **Google Gemini**（推薦新手使用）：
   - 從 [Google AI Studio](https://makersuite.google.com/app/apikey) 取得 API Key
   - 輸入 API Key（自動加密）
   - 選擇模型：Gemini 2.5 Flash（推薦）、Gemini 1.5 Pro 等
   - 使用「連接測試」按鈕驗證設定

   **OpenAI**：
   - 從 [OpenAI Platform](https://platform.openai.com/api-keys) 取得 API Key
   - 輸入 API Key（自動加密）
   - 選擇模型：GPT-4.1 Mini（推薦）、GPT-4o 等
   - 使用「連接測試」按鈕驗證設定

   **Claude (Anthropic)**：
   - 從 [Anthropic Console](https://console.anthropic.com/) 取得 API Key
   - 輸入 API Key（自動加密）
   - 選擇模型：Claude Sonnet 4.5（推薦）、Claude Haiku 4.5、Claude Opus 4.5
   - 使用「連接測試」按鈕驗證設定

2. **配置 LLM 設定**

   - **使用 LLM 取代內建對話**：啟用以使用 LLM 生成的對話取代靜態對話（支援所有提供商）
   - **頁面認識機能**：控制頁面感知功能是否啟用
   - **關閉思考模式**：建議用於 Qwen3、DeepSeek 模型（僅 Ollama）

3. **配置 AI 設定（頁面感知）**

   前往 **設定 → MP Ukagaka → AI 設定** 配置頁面感知功能：

## 🔧 進階功能

### 外部對話檔案

> ⚠️ **重要**：自版本 2.1.3 起，系統**固定使用外部對話檔案**。所有對話必須以外部檔案形式存放在 `dialogs/` 資料夾中。內部對話儲存功能已移除。

你可以從外部檔案載入對話（TXT 或 JSON 格式）：

**TXT 格式**（`dialogs/角色名.txt`）：

```
對話1

對話2

對話3
```

**JSON 格式**（`dialogs/角色名.json`）：

```json
{
  "messages": ["對話1", "對話2", "對話3"]
}
```

### 特殊代碼

您可以在對話檔案中使用特殊代碼來顯示動態內容：

| 代碼              | 說明                                      |
| ----------------- | ----------------------------------------- |
| `:recentpost[n]:` | 顯示最近 n 篇文章列表（可點擊的連結）     |
| `:randompost[n]:` | 顯示 n 篇隨機文章列表（可點擊的連結）     |
| `:commenters[n]:` | 顯示最近 n 位留言者（有網站會顯示為連結） |

**使用範例：**

```
最近的文章：:recentpost[3]:

推薦閱讀：:randompost[5]:

感謝最近留言的朋友：:commenters[5]:
```

> 📌 **注意**：特殊代碼會在伺服器端處理，轉換為 HTML 連結後再傳送到前端。這些代碼在 TXT 和 JSON 格式的對話檔案中都可以使用。舊格式 `(:recentpost[5]:)`（帶括號）也支援，以保持向後相容性。

### 頁面觸發條件

使用 WordPress 條件標籤來設定 AI 觸發條件：

- `is_single` - 單篇文章頁面
- `is_page` - 靜態頁面
- `is_home` - 首頁
- `is_front_page` - 網站首頁
- 多個條件：`is_single,is_page`

### 人格設定範例

**友善角色**：

```
你是一個友善的桌面助手。你會用親切的語氣簡單評論文章內容，回應請保持在 30 字以內。
```

**專業角色**：

```
你是專業的部落格助手。請提供簡短、有見地的文章評論。回應保持在 50 字以內。
```

**俏皮角色**：

```
你是一個俏皮的桌面寵物。用有趣的方式簡短（40字以內）評論文章內容。
```

## 🔒 安全功能

- **CSRF 保護**：所有表單提交使用 WordPress nonce 驗證
- **XSS 防護**：使用 WordPress 核心函數進行輸入過濾
- **API Key 加密**：API Key 在儲存前使用 AES-256-CBC 加密
- **安全檔案操作**：所有檔案 I/O 使用 WordPress Filesystem API 並進行路徑驗證
- **目錄遍歷防護**：驗證檔案路徑以防止未授權存取
- **輸入驗證**：所有使用者輸入都經過清理和驗證

## ❓ 常見問題

### 如何控制 API 成本？

- 將**機率**設為較低值（10-20%）
- 使用較快/較便宜的模型（例如 gemini-2.5-flash、gpt-4o-mini）
- 限制觸發頁面（例如只在 `is_single` 觸發）

### AI 為什麼沒有觸發？

1. 確認已在設定中啟用 AI
2. 驗證 API Key 是否正確
3. 確保頁面符合觸發條件（例如 `is_single`）
4. 檢查內容長度是否達到最低要求（500 字元）
5. 確認機率設定（測試時可嘗試 100%）
6. 檢查瀏覽器控制台是否有 JavaScript 錯誤

### AI 對話被自動對話覆蓋？

增加**AI 對話顯示時間**設定（以秒為單位）。這控制 AI 訊息顯示多久後恢復自動對話。建議：5-10 秒。

### 如何自訂 AI 回應風格？

編輯**人格設定（System Prompt）**欄位。這定義了角色的個性和回應風格。

**雲端 AI 服務**（Gemini、OpenAI、Claude）：建議保持在 200-300 字以內，以控制 API 成本和回應速度。

**本機 LLM**（Ollama）：可以使用更長、更詳細的提示詞（甚至 1000 字以上），因為沒有 API 成本限制。較長的提示詞通常能帶來更好的角色一致性和個性定義。

### LLM 連接失敗

1. **本地連接**

   - 確認 Ollama 服務正在運行
   - 檢查端口是否為 11434
   - 嘗試在瀏覽器訪問 `http://localhost:11434`

2. **遠程連接**
   - 確認 Cloudflare Tunnel 服務正在運行
   - 檢查隧道 URL 是否正確
   - 確認網絡連接正常

### LLM 回應速度慢

1. 使用更快的模型（如 `qwen3:8b`）
2. 啟用「關閉思考模式」選項
3. 遠程連接會有額外延遲（正常現象）

## 🐛 疑難排解

### AI 沒有觸發

- 檢查瀏覽器控制台錯誤
- 驗證 API Key 有效性
- 將機率設為 100% 測試
- 確保頁面內容足夠（>500 字元）

### 角色沒有顯示

- 確認「預設顯示春菜」設定已啟用
- 驗證角色圖片路徑正確
- 清除瀏覽器快取
- 檢查頁面排除規則

### 對話沒有載入

- 驗證對話檔案格式（TXT 或 JSON）
- 確認檔案命名與角色設定一致
- 確保檔案在 `dialogs/` 資料夾中
- 檢查檔案權限

## 📝 檔案結構

```
mp-ukagaka/
├── includes/                      # PHP 模組化元件
│   ├── core-functions.php        # 核心功能（設定、選項）
│   ├── utility-functions.php     # 工具函數（字串/陣列、過濾、安全性）
│   ├── ai-functions.php          # AI 功能（Gemini、OpenAI、Claude API 呼叫）
│   ├── llm-functions.php         # LLM 功能（Ollama 整合）
│   ├── ukagaka-functions.php     # 春菜管理（CRUD、訊息處理）
│   ├── ajax-handlers.php         # AJAX 處理器（所有 AJAX 端點）
│   ├── frontend-functions.php    # 前端功能（HTML、資源、顯示邏輯）
│   └── admin-functions.php        # 後台功能（設定儲存、後台頁面）
├── options/                       # 後台選項頁面
│   ├── options.php               # 後台頁面框架
│   ├── options_page0.php         # 一般設定
│   ├── options_page1.php         # 角色管理
│   ├── options_page2.php         # 建立新角色
│   ├── options_page3.php         # 擴展功能
│   ├── options_page4.php         # 對話管理
│   ├── options_page_ai.php      # AI 設定（頁面感知）
│   └── options_page_llm.php      # LLM 設定（Ollama）- 測試版
├── dialogs/                      # 對話檔案（TXT/JSON）
├── images/                       # 角色圖片
│   └── shell/                    # 角色外殼圖片
├── js/                           # JavaScript 檔案（v2.1.7+）
│   ├── ukagaka-base.js          # 基礎層（config + utils + ajax）
│   ├── ukagaka-core.js          # 核心功能（ui + dialogue + 角色切換）
│   ├── ukagaka-features.js      # 功能模組（ai + external + events）
│   ├── ukagaka-anime.js         # Canvas 動畫管理器
│   ├── ukagaka-cookie.js        # Cookie 處理工具
│   └── ukagaka-textarearesizer.js # 後台文字區域調整器
├── languages/                    # 翻譯檔案
├── mp-ukagaka.php               # 主外掛檔案（模組載入器）
├── mpu_style.css                # 樣式表
├── readme.txt                   # WordPress.org readme
└── README_zh-TW.md              # 本檔案
```

## 📜 版本歷史

### 版本 2.2.0（2025-12-19）

**🚀 重大更新：通用 LLM 接口**

- **多 AI 提供商支援**：統一接口支援四大 AI 服務
  - **Ollama**：本機/遠程免費 LLM（無需 API Key）
  - **Google Gemini**：支援 Gemini 2.5 Flash（推薦）、Gemini 1.5 Pro 等
  - **OpenAI**：支援 GPT-4.1 Mini（推薦）、GPT-4o 等
  - **Claude (Anthropic)**：支援 Claude Sonnet 4.5、Claude Haiku 4.5、Claude Opus 4.5
  - 所有提供商使用統一的設定介面，可隨時切換

- **API Key 加密存儲**：所有 API Key 自動加密儲存，確保安全性
- **連接測試功能**：為所有 AI 提供商新增連接測試按鈕

**🧠 System Prompt 優化系統**

- **XML 結構化設計**：採用 XML 標籤組織 System Prompt，提升 LLM 理解效率
  - `<character>`：角色名稱和核心設定
  - `<knowledge_base>`：壓縮後的 WordPress 資訊
  - `<behavior_rules>`：行為規則（must_do、should_do、must_not_do）
  - `<response_style_examples>`：70+ 個對話範例
  - `<current_context>`：當前情境資訊

- **上下文壓縮機制**：自動壓縮 WordPress、用戶、訪客資訊，減少 token 使用
- **芙莉蓮風格範例系統**：內建 70+ 個實際對話範例，涵蓋 12 個類別
- **雙層架構設計**：System Prompt 定義風格，User Prompt 提供任務指令

**🎨 UI/UX 全面升級**

- **統一卡片式設計**：所有設定頁面採用一致的卡片式佈局
- **兩欄式佈局**：主內容 + 側邊欄設計（主內容 55%，側邊欄 300px）
- **自訂滾動條樣式**：為長文字區域添加美觀的滾動條

**🔧 功能改進**

- **頁面認識機能整合**：將「頁面認識機能」設定移至 LLM 設定頁面
- **AI 設定頁面簡化**：專注於「頁面感知」功能
- **統計比喻優化**：恢復並優化遊戲化統計比喻

**📝 代碼優化**

- **新增函數**：mpu_build_optimized_system_prompt、mpu_build_frieren_style_examples、mpu_build_prompt_categories、mpu_compress_context_info、mpu_get_visitor_status_text、mpu_calculate_text_similarity、mpu_debug_system_prompt
- **函數重構**：mpu_generate_llm_dialogue 使用新的優化 System Prompt 系統
- **向後兼容**：保持對舊設定的支援，自動遷移設定鍵值

**🐛 錯誤修復**

- 修復統計比喻對應關係
- 優化文字區域寬度設定（統一為 850px）
- 修復主選單底部線條對齊問題
- 修復滾動條樣式問題

**🎉 特別更新（2025-12-19）**

- 為慶祝『葬送のフリーレン』第2期於2026年1月16日開始放送，預設角色已從初音變更為芙莉蓮（フリーレン）

---

### 版本 2.1.7（2025-12-15）

**效能優化：**

- 🚀 **JavaScript 檔案結構重構**：將 10 個 JS 檔案合併為 4 個，減少 HTTP 請求
  - `ukagaka-base.js`：合併 config + utils + ajax（基礎層）
  - `ukagaka-core.js`：合併 ui + dialogue + core（核心功能）
  - `ukagaka-features.js`：合併 ai + external + events（功能模組）
  - `ukagaka-anime.js`：保持獨立（動畫模組）
  - 所有檔案統一使用 `ukagaka-` 前綴命名
- 🚀 **優化 mousemove 日誌**：移除頻繁觸發的日誌記錄，避免控制台被洗版

**功能改進：**

- 🔧 **LLM 請求優化**：改用 POST 方式傳遞資料，避免 URL 長度限制
  - 使用 `FormData` 傳遞所有參數
  - 後端支援 POST 和 GET 兩種方式（向後兼容）
  - 使用 `wp_unslash()` 正確處理 WordPress 的 JSON 資料
- 🔧 **防止 LLM 請求連點**：加入 `cancelPrevious: true` 選項
  - 當使用者快速連續點擊「下一句」時，自動取消前一個未完成的請求
  - 避免多個並行請求互相覆蓋打字機效果

**錯誤處理優化：**

- 🐛 **Canvas 動畫錯誤處理**：在 `mpuChange` 函數開始時檢查 Canvas Manager
  - 提前檢查 `window.mpuCanvasManager` 是否存在
  - 避免在 Ajax 成功後才發現錯誤
- 🐛 **LLM 錯誤視覺提示**：在 debug 模式下顯示錯誤訊息
  - 顯示格式：`[LLM 錯誤: 錯誤訊息]`
  - 2 秒後自動切換到後備對話

**其他改進：**

- 📝 統一檔名命名規範：所有 JavaScript 檔案使用 `ukagaka-` 前綴
  - `jquery.textarearesizer.compressed.js` → `ukagaka-textarearesizer.js`

### 版本 2.1.6（2025-12-14）

**新功能：**

- ✨ **Canvas 動畫**：支援多幀角色動畫
  - 自動資料夾偵測，載入動畫序列
  - 僅在角色說話時播放動畫（節省資源）
  - 向後兼容單張靜態圖片
  - 幀率：180 毫秒/幀
  - 支援格式：PNG、JPG、JPEG、GIF、WebP
  - 詳見 [Canvas 自訂指南](docs/CANVAS_CUSTOMIZATION.md)
  - 可在作者的網站 [www.moelog.com](https://www.moelog.com/) 查看實際運作效果
- ✨ **LLM**: WordPress 資訊整合 - LLM 現在可以獲取並評論網站資訊
  - WordPress 版本、主題資訊（名稱、版本、作者）、PHP 版本、網站名稱
  - 統計資訊：文章數、留言數、分類數、標籤數、運營日數
  - 使用快取機制（5 分鐘）提升效能
  - 可自訂統計提示詞，詳見 [USER_GUIDE.md](docs/USER_GUIDE.md)）
- ✨ **LLM**: 防止重複對話機制 - 透過追蹤之前回應，避免「廢話迴圈」問題
- ✨ **效能**: 閒置偵測功能 - 使用者閒置時（60 秒）自動暫停自動對話
  - 節省 GPU 資源，避免在背景分頁或使用者離開時浪費資源
  - 追蹤使用者活動（滑鼠、鍵盤、滾動、點擊）
  - 使用者返回時自動恢復

**改進：**

- 🔧 **LLM**: 新增提示詞分類：`wordpress_info` 和 `statistics`，用於 WordPress 相關對話
- 🔧 **LLM**: 增強系統提示詞，加入 WordPress 背景資訊
- 🔧 **效能**: 減少使用者閒置時不必要的 LLM 請求

### 版本 2.1.2（2025-12-08）

**新功能：**

- ✨ **新增（測試階段）**：透過 Ollama 整合支援本機 LLM
- ✨ **新增**：Cloudflare Tunnel 和遠程連接支援
- ✨ **新增**：本地與遠程連接的動態超時管理
- ✨ **新增**：自動服務可用性檢查

**改進：**

- 🔧 **改善**：遠程連接的錯誤訊息更詳細
- 🔧 **改善**：Ollama 端點的 URL 驗證
- 🔧 **改善**：連接類型檢測（本地/遠程）

**錯誤修正：**

- 🐛 **修正**：LLM 啟用勾選框狀態持久化問題

**架構改進：**

- ✨ 完全模組化架構（7 個模組）
- ✨ 主程式檔案精簡至約 85 行

**新功能：**

- ✨ AI 頁面感知，支援多提供商（Gemini、OpenAI、Claude）
- ✨ 首次訪客打招呼（Slimstat 整合）
- ✨ 可配置 AI 文字顏色和顯示時間

**改進：**

- 🔧 JSON 對話檔案支援
- 🔧 改善錯誤處理和日誌

## 👥 致謝

- **原作者**：Ariagle _（原站點已停止運營）_
- **維護者**：Horlicks (<https://www.moelog.com/>)
- **靈感來源**：經典 MP Ukagaka 外掛 / 伺か (Ukagaka)

## 📄 授權

本外掛基於原始 MP Ukagaka 外掛。請參閱原始外掛的授權條款。

## 🔗 連結

- [萌えログ.COM](https://www.moelog.com/)
- [維基百科 - 伺か](http://en.wikipedia.org/wiki/Ukagaka)
- [Google AI Studio](https://makersuite.google.com/app/apikey)（Gemini API Key）
- [OpenAI Platform](https://platform.openai.com/api-keys)（OpenAI API Key）
- [Anthropic Console](https://console.anthropic.com/)（Claude API Key）

## 💬 支援

如有問題或建議：

- 訪問[萌えログ.COM](https://www.moelog.com/)
- 查看 WordPress 後台的常見問題
- 參閱上方疑難排解章節
- 在 GitHub 開立 Issue

---

**Made with ❤ for WordPress 社群**
