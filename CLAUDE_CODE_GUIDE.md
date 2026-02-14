# Claude Code 使用指南 (Claude Code Guide)

這是一份關於如何使用 Claude Code CLI 工具來檢查與分析程式碼的簡易指南。

## 1. 啟動 Claude Code

在您的專案目錄中打開終端機 (Terminal/PowerShell/CMD)，輸入以下指令啟動：

```bash
claude
```

或是如果沒有全域安裝：

```bash
npx @anthropic-ai/claude-code
```

## 2. 檢查程式碼 (Code Review)

Claude Code 的介面是**對話式 (Conversational)** 的。您就像在跟一位工程師聊天一樣直接下指令。

### 常用的指令範例：

- **檢查特定檔案：**

  > "Review `includes/core-functions.php` specifically for security vulnerabilities."
  > (請檢查 `includes/core-functions.php` 是否有安全漏洞。)

- **解釋程式碼：**

  > "Explain how the `update_love_level` function works in `dynamics.json`."
  > (請解釋 `dynamics.json` 中的 `update_love_level` 函式是如何運作的。)
  > _(注意：JSON 是資料檔，若要問邏輯通常是問讀取它的 PHP/JS 檔)_

- **全專案掃描 (慎用，可能耗費 Token)：**

  > "Scan the project for any hardcoded API keys."
  > (掃描專案中是否有硬編碼的 API Key。)

- **除錯：**
  > "I'm getting an 'undefined index' error in `mp-ukagaka.php` line 45. Can you help fix it?"
  > (我在 `mp-ukagaka.php` 第 45 行遇到 'undefined index' 錯誤，能幫我修嗎？)

## 3. 實用技巧

- **使用 `/` 指令：**
  在對話框輸入 `/` 可以看到可用的指令列表 (例如 `/help`, `/clear`, `/compact` 等)。

- **提及檔案：**
  Claude Code 通常會自動偵測檔案，但您也可以明確指出檔案路徑，它會自動讀取內容。

- **執行命令：**
  Claude Code 可以執行終端機指令。例如您可以叫它："Run the tests" (執行測試) 或 "List files in directory" (列出目錄檔案)。

- **多行輸入：**
  如果您的問題很長，可以直接貼上，Claude Code 能夠處理。

## 4. 常見問題

- **亂碼問題：** 如果在 Windows 上看到亂碼，請嘗試在終端機輸入 `chcp 65001` 切換編碼。
- **Token 限制：** 如果檔案太大，Claude Code 可能無法一次讀取全部，建議分批詢問或只詢問關鍵部分。
