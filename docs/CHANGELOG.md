# MP Ukagaka 版本歷史

> 📋 所有版本的更新記錄

---

## [2.2.0] - 2025-12-19

### 🚀 重大更新：通用 LLM 接口

- **多 AI 提供商支援**：統一接口支援四大 AI 服務
  - **Ollama**：本機/遠程免費 LLM（無需 API Key）
  - **Google Gemini**：支援 Gemini 2.5 Flash（推薦）、Gemini 1.5 Pro 等
  - **OpenAI**：支援 GPT-4.1 Mini（推薦）、GPT-4o 等
  - **Claude (Anthropic)**：支援 Claude Sonnet 4.5、Claude Haiku 4.5、Claude Opus 4.5
  - 所有提供商使用統一的設定介面，可隨時切換

- **API Key 加密存儲**：所有 API Key 自動加密儲存，確保安全性
- **連接測試功能**：為所有 AI 提供商新增連接測試按鈕

### 🧠 System Prompt 優化系統

- **XML 結構化設計**：採用 XML 標籤組織 System Prompt，提升 LLM 理解效率
  - `<character>`：角色名稱和核心設定
  - `<knowledge_base>`：壓縮後的 WordPress 資訊
  - `<behavior_rules>`：行為規則（must_do、should_do、must_not_do）
  - `<response_style_examples>`：70+ 個對話範例
  - `<current_context>`：當前情境資訊

- **上下文壓縮機制**：自動壓縮 WordPress、用戶、訪客資訊，減少 token 使用
- **芙莉蓮風格範例系統**：內建 70+ 個實際對話範例，涵蓋 12 個類別
  - 問候類、閒聊類、時間感知類、觀察思考類
  - 魔法研究類、技術觀察類、統計觀察類、回憶類
  - 管理員評語類、意外反應類、BOT 檢測類、沉默類

- **雙層架構設計**：
  - **System Prompt**：定義角色風格、行為規則和對話範例
  - **User Prompt**：每次對話的具體任務指令（與範例類別對應）

### 🎨 UI/UX 全面升級

- **統一卡片式設計**：所有設定頁面採用一致的卡片式佈局
- **動漫風格配色**：參考芙莉蓮網站設計，採用柔和漸層背景
  - 卡片背景：`#E8F4F8`（淡藍綠色）
  - 邊框顏色：`#B8E6E6`（淺青色）
  - 標題顏色：`#4A9EBD`（藍綠色）
  - 文字顏色：`#2C3E50`（深藍灰色）

- **兩欄式佈局**：主設定頁面採用主內容 + 側邊欄設計
  - 主內容寬度：55%
  - 側邊欄寬度：300px（固定）
  - 側邊欄包含：AI Provider 連結、文檔連結、一般連結

- **自訂滾動條樣式**：為長文字區域（System Prompt 等）添加美觀的滾動條

### 🔧 功能改進

- **頁面認識機能整合**：將「頁面認識機能」設定移至 LLM 設定頁面
  - 統一管理所有 LLM 相關設定
  - 與「使用 LLM 取代內建對話」功能整合

- **AI 設定頁面簡化**：專注於「頁面感知」功能
  - 保留：言語設定、キャラクター設定、頁面感知確率、トリガーページ、AI会話の表示時間、初回訪問者への挨拶
  - 移除：AI 提供商選擇、API Key 設定、模型選擇（移至 LLM 設定頁面）

- **統計比喻優化**：恢復並優化遊戲化統計比喻
  - 魔族遭遇回数 = 文章數 (`post_count`)
  - 最大ダメージ = 留言數量 (`comment_count`)
  - 習得スキル総数 = 分類數量 (`category_count`)
  - アイテム使用回数 = TAG數量 (`tag_count`)
  - 冒険経過日数 = 運營日數 (`days_operating`)

### 📝 代碼優化

- **新增函數**：
  - `mpu_build_optimized_system_prompt()`：建構 System Prompt（支援變數替換）
  - `mpu_build_prompt_categories()`：生成 User Prompt 指令類別
  - `mpu_compress_context_info()`：壓縮上下文資訊
  - `mpu_get_visitor_status_text()`：獲取訪客狀態文字

- **函數重構**：
  - `mpu_generate_llm_dialogue()`：使用新的優化 System Prompt 系統
  - 移除舊的冗長 System Prompt 建構邏輯

- **向後兼容**：保持對舊設定的支援，自動遷移設定鍵值

### 🐛 錯誤修復

- 修復統計比喻對應關係
- 優化文字區域寬度設定（統一為 850px）
- 修復主選單底部線條對齊問題
- 修復滾動條樣式問題

### 📚 文檔更新

- 更新 `USER_GUIDE.md`：完整說明通用 LLM 接口和 System Prompt 優化系統
- 更新 `CHANGELOG.md`：記錄 2.2.0 版本所有更新

### 🎉 特別更新（2025-12-19）

- 為慶祝『葬送のフリーレン』第2期於2026年1月16日開始放送，預設角色已從初音變更為芙莉蓮（フリーレン）
- 新安裝的用戶會看到芙莉蓮作為預設角色
- 已安裝的用戶如果預設角色名稱仍為「初音」，系統會自動更新為芙莉蓮

---

## [2.1.7] - 2025-12-15

### 🚀 效能優化

- **JavaScript 檔案結構重構**：將 10 個 JS 檔案合併為 4 個，減少 HTTP 請求
  - `ukagaka-base.js`：合併 config + utils + ajax（基礎層）
  - `ukagaka-core.js`：合併 ui + dialogue + core（核心功能）
  - `ukagaka-features.js`：合併 ai + external + events（功能模組）
  - `ukagaka-anime.js`：保持獨立（動畫模組）
  - 所有檔案統一使用 `ukagaka-` 前綴命名

- **優化 mousemove 日誌**：移除頻繁觸發的日誌記錄，避免控制台被洗版
  - 註解掉 `mousemove` 事件中的日誌輸出
  - 提升 debug 模式下的調試體驗

### 🔧 功能改進

- **LLM 請求優化**：改用 POST 方式傳遞資料，避免 URL 長度限制
  - 使用 `FormData` 傳遞所有參數（`cur_num`、`cur_msgnum`、`last_response`、`response_history`）
  - 後端支援 POST 和 GET 兩種方式（向後兼容）
  - 使用 `wp_unslash()` 正確處理 WordPress 的 JSON 資料

- **防止 LLM 請求連點**：加入 `cancelPrevious: true` 選項
  - 當使用者快速連續點擊「下一句」時，自動取消前一個未完成的請求
  - 避免多個並行請求互相覆蓋打字機效果

### 🐛 錯誤處理優化

- **Canvas 動畫錯誤處理**：在 `mpuChange` 函數開始時檢查 Canvas Manager
  - 提前檢查 `window.mpuCanvasManager` 是否存在
  - 避免在 Ajax 成功後才發現錯誤，提供更一致的體驗

- **LLM 錯誤視覺提示**：在 debug 模式下顯示錯誤訊息
  - 顯示格式：`[LLM 錯誤: 錯誤訊息]`
  - 2 秒後自動切換到後備對話
  - 非 debug 模式下直接使用後備對話，不影響一般使用者

### 📝 其他改進

- 統一檔名命名規範：所有 JavaScript 檔案使用 `ukagaka-` 前綴
  - `jquery.textarearesizer.compressed.js` → `ukagaka-textarearesizer.js`

---

## [2.1.6] - 2025-12-13

### ✨ 新功能

- **WordPress 資訊整合**：LLM 自發對話現在可以獲取並評論網站資訊
  - 整合 WordPress 版本、主題資訊（名稱、版本、作者）、PHP 版本、網站名稱
  - 統計資訊：文章數、留言數、分類數、標籤數、運營日數
  - 使用 transient 快取機制（5 分鐘），提升效能
  - 新增 `wordpress_info` 和 `statistics` 兩類提示詞分類

- **RPG 風格統計資訊**：統計資訊使用遊戲化術語
  - 魔族遭遇回数（文章數）
  - 最大ダメージ（留言數）
  - 習得スキル総数（分類數）
  - アイテム使用回数（TAG數）
  - 冒険日数（運營日數）

- **防止重複對話機制**：避免「廢話迴圈」問題
  - 追蹤上一次 LLM 生成的回應
  - 提示詞中加入避免重複的指令
  - 自動生成不同的閒聊內容或保持沉默

- **閒置偵測功能**：自動暫停自動對話以節省資源
  - 偵測使用者活動（滑鼠、鍵盤、滾動、點擊）
  - 60 秒閒置閾值（可調整）
  - 使用者返回時自動恢復
  - 有效節省 GPU 和網路資源

### 🔧 改進

- **LLM 系統提示詞增強**：加入 WordPress 網站資訊作為背景知識
- **提示詞多樣性提升**：新增 WordPress 相關和統計資訊相關的提示詞
- **效能優化**：減少不必要的 LLM 請求
- **資源管理**：更好的 GPU 和網路資源使用控制

### 📝 技術細節

- 新增 `mpu_get_wordpress_info()` 函數（位於 `includes/utility-functions.php`）
- 修改 `mpu_generate_llm_dialogue()` 函數，整合 WordPress 資訊
- 前端 JavaScript 加入閒置偵測邏輯（`ukagaka-core.js`）
- AJAX 處理器支援 `last_response` 參數

---

## [2.1.0] - 2025-11-26

### ✨ 新功能

- **可配置打字速度**：新增打字效果速度設定（10-200 毫秒/字）
- **API Key 加密存儲**：使用 AES-256-CBC 加密所有 API Key
- **安全文件操作**：使用 WordPress Filesystem API 進行所有文件讀寫
- **目錄遍歷防護**：驗證所有文件路徑，防止未授權存取

### 🔧 改進

- 已設定的 API Key 會顯示綠色勾勾指示器
- 改善文件操作的錯誤訊息
- 向下相容：支援現有的明文 API Key 自動加密

### 🔒 安全性

- 所有 API Key 使用 AES-256-CBC 加密
- 文件操作使用 WordPress Filesystem API
- 新增路徑驗證防止目錄遍歷攻擊

---

## [2.0.0] - 2025-11-22

### 🏗️ 架構改進

- **完全模組化重構**：將單一檔案拆分為 7 個獨立模組
- **主程式精簡**：`mp-ukagaka.php` 精簡至約 85 行
- **依賴順序載入**：模組按依賴關係順序載入

### ✨ 新功能

- **AI 頁面感知**：根據文章內容自動生成 AI 評論
- **多 AI 提供商支援**：
  - Google Gemini（gemini-2.5-flash、gemini-2.5-pro）
  - OpenAI GPT（GPT-4o、GPT-4o-mini、GPT-3.5-turbo）
  - Anthropic Claude（Claude Sonnet 4.5）
- **首次訪客打招呼**：對新訪客顯示個性化歡迎訊息
- **Slimstat 整合**：獲取訪客來源、地區等資訊
- **AI 文字顏色**：可自訂 AI 回應的文字顏色
- **AI 顯示時間控制**：設定 AI 訊息顯示時長

### 🔧 改進

- **JSON 對話檔案支援**：除 TXT 外，新增 JSON 格式支援
- **改善錯誤處理**：更詳細的錯誤日誌
- **效能優化**：設定讀取使用快取機制

### 📁 模組結構

```
includes/
├── core-functions.php      # 核心功能
├── utility-functions.php   # 工具函數
├── ai-functions.php        # AI 功能
├── ukagaka-functions.php   # 春菜管理
├── ajax-handlers.php       # AJAX 處理
├── frontend-functions.php  # 前端功能
└── admin-functions.php     # 後台功能
```

---

## [1.9.x] - 歷史版本

### 1.9.5
- 修復部分主題相容性問題
- 改善對話顯示效果

### 1.9.4
- 新增自動對話功能
- 新增對話間隔時間設定

### 1.9.3
- 新增外部對話檔案支援（TXT 格式）
- 新增多春菜切換功能

### 1.9.2
- 新增頁面排除功能
- 改善行動裝置顯示

### 1.9.1
- 新增固定訊息功能
- 新增通用會話功能

### 1.9.0
- 新增點擊行為設定
- 新增會話順序設定

---

## [1.8.x] - 歷史版本

### 1.8.5
- 新增 jQuery 相容性修復
- 改善 WordPress 5.x 相容性

### 1.8.0
- 新增多語言支援
- 新增繁體中文、日文翻譯

---

## [1.7.x] - 歷史版本

### 1.7.0
- 新增春菜管理介面
- 新增創建新春菜功能

---

## [1.6.x] - 歷史版本

### 1.6.0
- 新增擴展頁面
- 新增自訂 JavaScript 功能

---

## [1.5.x] - 歷史版本

### 1.5.0
- 初始公開版本
- 基本春菜顯示功能
- 基本對話功能

---

## 升級指南

### 從 1.x 升級到 2.x

1. **備份設定**
   - 建議先備份 `wp_options` 中的 `mpu_opt` 選項

2. **升級外掛**
   - 上傳新版本覆蓋舊版本
   - 或透過 WordPress 後台更新

3. **檢查設定**
   - 升級後設定會自動保留
   - 建議檢查所有設定頁面確認無誤

4. **清除快取**
   - 清除瀏覽器快取
   - 清除 WordPress 快取外掛的快取

### 從 2.0.x 升級到 2.1.x

1. **API Key 自動加密**
   - 現有的明文 API Key 會在第一次儲存設定時自動加密
   - 無需手動操作

2. **檢查文件權限**
   - 確保 `dialogs/` 資料夾可寫入
   - WordPress Filesystem API 需要適當權限

### 從 2.1.x 升級到 2.2.0

1. **設定自動遷移**
   - 所有現有設定會自動保留並遷移
   - AI 提供商設定會自動遷移到 LLM 設定頁面
   - 無需手動操作

2. **檢查 LLM 設定**
   - 前往 **設定** → **MP Ukagaka** → **LLM 設定**
   - 確認 AI 提供商選擇正確
   - 確認 API Key 已正確設定（會自動加密）
   - 測試連接確認正常

3. **檢查 AI 設定**
   - 前往 **設定** → **MP Ukagaka** → **AI 設定**
   - 確認「頁面感知確率」和「トリガーページ」設定正確
   - 確認「キャラクター設定（System Prompt）」內容正確

4. **清除快取**
   - 清除瀏覽器快取
   - 清除 WordPress 快取外掛的快取（如有使用）

5. **體驗新 UI**
   - 所有設定頁面已更新為新的卡片式設計
   - 主設定頁面新增側邊欄快速連結

---

## 已知問題

### 2.1.0
- 部分舊版 PHP（< 7.4）可能不支援加密功能
- 建議升級至 PHP 7.4 或以上

### 2.0.0
- AI 功能需要穩定的網路連線
- 部分防火牆可能阻擋 AI API 請求

---

## 回報問題

如發現問題，請提供以下資訊：

1. WordPress 版本
2. PHP 版本
3. 外掛版本
4. 錯誤訊息（如有）
5. 瀏覽器控制台錯誤（按 F12 查看）

---

## 貢獻者

- **原作者**：Ariagle *(原站點已停止運營)*
- **維護者**：Horlicks ([MoeLog](https://www.moelog.com/))

---

**感謝所有使用者的支持與回饋！** ❤️

