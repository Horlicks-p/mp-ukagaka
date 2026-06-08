# MP Ukagaka

一個用於在 WordPress 網站上創建互動式偽春菜（伺か）角色的外掛，具備 AI 頁面感知功能。

[![Plugin Version](https://img.shields.io/badge/version-2.25.1-blue.svg)](https://github.com)
[![WordPress](https://img.shields.io/badge/WordPress-5.0%2B-blue.svg)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple.svg)](https://www.php.net/)

🌍 **其他語言**: [English](README.md) | [日本語](README_ja.md)

## 📢 前言（必讀）

本外掛以 10 多年前 Ariagle 氏所發布之 WordPress 外掛「MP Ukagaka」為基礎，進行大幅擴展與衍生的版本。

> ⚠️ **重要提示**：本外掛約 90% 的程式碼是透過 AI 輔助開發（Vibe Coding）完成的，雖已經過不計其數的調試與改良，但仍可能存在未知的 BUG 或不夠完善的程式碼結構，使用前請務必理解此風險。

📺 **演示網站**：[https://www.moelog.com](https://www.moelog.com/)

### 關於角色人格創建

本外掛雖提供 **創建新角色人格** 的功能（詳見 [GHOST_CREATE_GUIDE.md](docs/GHOST_CREATE_GUIDE.md)），但由於開發精力主要投注在預設角色「芙莉蓮」的製作上，因此該功能尚未經過完整測試，敬請見諒。

如果您只是想使用預設角色「芙莉蓮」，基本台詞已內建於外掛中，開箱即用。若想獲得更豐富、更具互動性的對話體驗，建議設定 AI 模型的 API Key。此外，角色記憶設定檔（載入順序：personality.md、instructions.md、再到 system_prompt.md，包含動畫第一期的劇情記憶）也已內建。管理人的暱稱、簡稱與生日，現在可以直接在 **設定 → MP Ukagaka → 通用設定** 中填寫 **Admin full nickname**、**Admin short name** 和 **Admin birthday**（MM-DD 格式，例如 `10-18`）。人格檔案中的 `{{admin_nickname}}`、`{{admin_name}}`、`{{admin_birthday}}` 是佔位符，會在執行時自動替換為後台設定的值，因此您不再需要手動修改人格檔案或 `calendar.json`。角色也會根據此設定自動在您的生日當天為您慶祝。

### AI 模型推薦

本外掛內建支援 Gemini、OpenAI、Claude 及 Ollama 等多種 AI 提供商。根據實際測試，**GPT-4o Mini** 在對話生成品質與 API 成本之間取得了極佳的平衡，是非常推薦的選擇，供您參考。

## 📸 預覽截圖

![MP Ukagaka 展示](screenshot.PNG)

_芙莉蓮角色根據文章內容顯示 AI 生成的對話_

> 💡 **更多截圖**：
>
> - `screenshot2.PNG` - 通用設定與 LLM 設定頁面
> - `screenshot3.PNG` - 互動對話模式展示（v2.3.0 新功能）

## ✨ 核心功能

- **多角色支援**：創建和管理多個春菜角色
- **AI 頁面感知**：使用 Gemini、OpenAI、Claude 或 Ollama 生成智慧回應
- **互動對話模式**：訪客可即時與角色對話，支援 SSE 串流回應
- **外部對話檔案**：支援 TXT 和 JSON 格式對話
- **Canvas 動畫**：支援單張圖片或多幀動畫
- **多語言支援**：繁體中文、日文、英文
- **安全優先**：API Key 加密、CSRF 保護、XSS 防護

## 🚀 快速開始

### 安裝

1. 下載或克隆本儲存庫至 `wp-content/plugins/`
2. 在 WordPress 後台 → 外掛中啟用
3. 前往 **設定 → MP Ukagaka**

### 基本設定

1. **通用設定**：選擇預設角色並配置顯示設定
2. **創建角色**：添加角色圖片 URL 和對話
3. **對話檔案**：對話自動儲存至 `dialogs/` 資料夾

### 啟用 AI 功能（可選）

**LLM 設定**：

- 選擇提供商：Ollama（免費）、Gemini、OpenAI 或 Claude
- 輸入 API Key（自動加密）或配置 Ollama 端點
- 啟用「使用 LLM 取代內建對話」

**AI 設定**：

- 啟用「頁面感知功能」
- 設定觸發機率（建議 10-30% 以控制成本）
- 在 System Prompt 中自訂角色人格

**對話模式**：

- 在通用設定中啟用「互動對話功能」
- 「更換春菜」按鈕將變為對話介面

## 🤖 AI 提供商

| 提供商     | 費用     | 設定                                                                         |
| ---------- | -------- | ---------------------------------------------------------------------------- |
| **Ollama** | 免費     | 本地安裝或連接遠程伺服器                                                     |
| **Gemini** | 付費 API | 從 [Google AI Studio](https://makersuite.google.com/app/apikey) 取得 API Key |
| **OpenAI** | 付費 API | 從 [OpenAI Platform](https://platform.openai.com/api-keys) 取得 API Key      |
| **Claude** | 付費 API | 從 [Anthropic Console](https://console.anthropic.com/) 取得 API Key          |

## 📚 完整文件

詳細資訊請參閱：

- **[使用者指南](docs/USER_GUIDE.md)** - 完整設定與配置指南
- **[開發者指南](docs/DEVELOPER_GUIDE.md)** - 架構與開發資訊
- **[API 參考](docs/API_REFERENCE.md)** - 函數與 Hook 參考
- **[更新日誌](docs/CHANGELOG.md)** - 版本歷史

## 🎉 v2.25.2 新功能

**Emotion tag 顯示修正**：後端 response normalizer 會在 REST 回應送到前端前移除支援的 `[tag]` 標記與常見變體（例如 `[ thinking ]`、`【thinking】`、`[thinking…]`），讓頁面感知、初次訪問問候、BOT／事件回應與對話維持「顯示文字 = 歷史文字 = checksum 文字」。未知 tag 預設仍保留，Markdown 連結也會被保護。Frieren 的 `emoji-keywords.json` metadata 也不再附帶範例標記字串，避免誘導模型照抄。

### v2.25.0 重點

**Emotion tag 表情管線**：AI 回覆現在可以使用 inline `[tag]` 表情標記。新的 response normalizer 會讓 display/history/checksum/TTS 共用同一份乾淨文字，同時把 emotion tag 解析成 REST 與 SSE 都能使用的結構化資料。

**Frieren 表情提示切換**：Frieren 於 `manifest.json` 宣告支援的 emoji tag，prompt 改用新的 inline tag 風格，不再依賴舊的句尾 `[表情:xxx]` 指示；舊格式仍保留向下相容解析。

**串流支援**：SSE 輸出現在能跨 chunk 解析被切開的 emotion tag 與 think block，避免 Markdown link 偽陽性，並保留串流中明確指定的表情，不再於完成時被關鍵字猜測覆蓋。

**Think bubble placeholder**：`えっと` 與初始 `何を話せばいいかな` 等 system placeholder 現在顯示於角色旁的 think bubble，而不是主對話框。Touch、decoration 與初始載入流程也已修正，避免 stale placeholder 狀態與空白主對話框閃現。

**注意**：Ollama `message.thinking` 曾經接通後又 revert。實測確認 Ollama 會讓 reasoning 與 final content 共用 `num_predict` 預算，導致空回覆 / 截斷、history 連續 user、checksum 對不齊等問題。Think bubble 本身已正式啟用，負責載入 / placeholder UI（進場的 `何を話せばいいかな`、touch / decoration 思考狀態）。另一條「LLM `<think>` 內心獨白餵入氣泡」的通道實測後已廢案——代碼保留於樹中，但無任何 provider 餵入、亦無 prompt 要求，對使用者無任何可見行為。本專案不再維護它，但整條管線（normalizer、SSE parser、氣泡）完整保留：`DEVELOPER_GUIDE.md` 的「Inner Monologue (`<think>`) Channel」一節說明開發者 opt-in 接線點與踩雷，供能產出良好簡短內心獨白的 provider 實驗。

### 前版重點

**Observation 飾品名稱解析**（v2.24.1）：近期訪客行為中的飾品 type slug 會在注入 prompt 前解析成可讀名稱。

**程式碼品質流程**（v2.24.1）：新增 PHPCS baseline workflow，並將 `lint:phpcs` 納入驗證流程。

[查看完整更新日誌](docs/CHANGELOG.md)

## ❓ 常見問題

**為什麼 AI 沒有觸發？**

- 檢查 API Key 是否有效
- 確認頁面符合觸發條件（例如 `is_single`）
- 確保機率有設定（測試時可設為 100%）
- 檢查內容長度（需 \>300 字元）

**如何控制 API 成本？**

- 將機率設為 10-20%
- 使用較便宜的模型（gemini-2.5-flash、gpt-4o-mini）
- 限制觸發頁面為 `is_single`

**LLM 連接失敗？**

- Ollama：確保服務在 11434 端口運行
- 遠程連接：檢查 Cloudflare Tunnel 或網絡連接
- 使用設定中的測試按鈕驗證連接

[更多 FAQ 請見使用者指南](docs/USER_GUIDE.md#常見問題)

## 🔒 安全功能

- **API Key 加密**：所有 API Key 使用 AES-256-CBC 加密
- **CSRF 保護**：所有表單使用 WordPress nonce 驗證
- **XSS 防護**：使用 WordPress 核心函數進行輸入/輸出清理
- **安全檔案操作**：路徑驗證和 WordPress Filesystem API

## 💬 支援

- 訪問 [萌えログ.COM](https://www.moelog.com/)
- 查看 [使用者指南](docs/USER_GUIDE.md) 和 [常見問題](docs/USER_GUIDE.md#常見問題)
- 在 GitHub 開立 Issue

## 👥 致謝

- **原作者**：Ariagle
- **維護者**：Horlicks ([萌えログ.COM](https://www.moelog.com/))
- **靈感來源**：經典 MP Ukagaka 外掛 / 伺か (Ukagaka)

## 📄 授權

本外掛基於原始 MP Ukagaka 外掛。請參閱原始外掛的授權條款。

---

**Made with ❤ for WordPress 社群**
