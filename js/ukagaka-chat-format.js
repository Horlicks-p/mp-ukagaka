/**
 * 輕量級 Markdown 解析（處理粗體、斜體和行內代碼）
 *
 * 用於增強 AI 對話的閱讀體驗，將常見的 Markdown 語法轉換為 HTML。
 *
 * @param {string} text - 原始文字
 * @returns {string} 轉換後的 HTML
 */
function mpu_parseMarkdown(text) {
  if (!text || typeof text !== "string") return text;

  return (
    text
      // 處理粗體 **text** 或 __text__
      .replace(/\*\*(.+?)\*\*/g, "<strong>$1</strong>")
      .replace(/__(.+?)__/g, "<strong>$1</strong>")
      // 處理斜體 *text* 或 _text_（排除已處理的粗體）
      .replace(/\*([^*]+)\*/g, "<em>$1</em>")
      .replace(/\b_([^_]+)_\b/g, "<em>$1</em>")
      // 處理連結 [text](url)
      .replace(
        /\[([^\]]+)\]\(([^)]+)\)/g,
        '<a href="$2" target="_blank" rel="noopener noreferrer">$1</a>',
      )
      // 處理行內代碼 `code`
      .replace(
        /`([^`]+)`/g,
        '<code style="background:#f0f0f0;padding:1px 4px;border-radius:3px;font-family:monospace;font-size:0.9em;">$1</code>',
      )
  );
}
