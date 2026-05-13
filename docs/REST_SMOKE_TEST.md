# REST 煙霧測試檢查清單

每次發版前執行。各組包含可直接複製的 `curl.exe` 指令
（Windows PowerShell / cmd）與合格判定標準。第 1〜2 組與第 5 組不需要 AI API 金鑰。

---

## 環境設定

```powershell
$BASE = "https://your-site.com/wp-json/mp-ukagaka/v1"
```

將 `your-site.com` 替換為目標站台網域。以下所有指令均參照 `$BASE`。

---

## 第 1 組 — 基本功能（無需認證、無需 AI）

無論 AI 是否設定，這些端點在每次頁面載入時都必須正常運作。

### 1-A  GET /init

```powershell
curl.exe -s "$BASE/init" | python -m json.tool
```

**合格：** HTTP 200，JSON 包含 `ghost_name` 與 `personality_id`。

---

### 1-B  GET /settings

```powershell
curl.exe -s "$BASE/settings" | python -m json.tool
```

**合格：** HTTP 200，JSON 包含功能旗標欄位（例如 `ai_enabled`、`chat_mode`）。

---

### 1-C  GET /visitor-info

```powershell
curl.exe -s "$BASE/visitor-info" | python -m json.tool
```

**合格：** HTTP 200，JSON 包含 `ip`、`country` 等訪客資訊欄位。

---

## 第 2 組 — Session Token

### 2-A  正常取得 Token

```powershell
curl.exe -s -D - "$BASE/session-token"
```

**合格：**
- HTTP 200
- `Cache-Control` 標頭包含 `no-store`
- 回應本文 JSON 包含非空字串值的 `"token"` 鍵

---

### 2-B  速率限制確認（每 60 秒最多 10 次）

連續執行以下指令 11 次（可依需求調整迴圈次數）：

```powershell
1..11 | ForEach-Object { curl.exe -s -o NUL -w "%{http_code}`n" "$BASE/session-token" }
```

**合格：** 至少有一次回應返回 HTTP **429**。

---

## 第 3 組 — Token 強制驗證（安全回歸測試）

確認 AI 端點拒絕未帶 Session Token 的匿名請求。
若此處發生回歸，表示 v2.15 的 Session Token 防護已被破壞。

### 3-A  POST /chat/user — 無 Token → 403

```powershell
curl.exe -s -o NUL -w "%{http_code}" -X POST "$BASE/chat/user" `
  -H "Content-Type: application/json" `
  -d '{"message":"hello"}'
```

**合格：** HTTP **403**

---

### 3-B  POST /chat/greet — 無 Token → 403

```powershell
curl.exe -s -o NUL -w "%{http_code}" -X POST "$BASE/chat/greet" `
  -H "Content-Type: application/json" `
  -d '{}'
```

**合格：** HTTP **403**

---

### 3-C  POST /chat/context — 無 Token → 403

```powershell
curl.exe -s -o NUL -w "%{http_code}" -X POST "$BASE/chat/context" `
  -H "Content-Type: application/json" `
  -d '{}'
```

**合格：** HTTP **403**

---

## 第 4 組 — 正常聊天流程（需要 AI 設定）

先取得真實 Token，再送出聊天請求，測試完整的來回流程。
若測試環境沒有 AI API 金鑰則略過此組。

```powershell
# 步驟 1：取得 Token
$TOKEN = (curl.exe -s "$BASE/session-token" | python -m json.tool | Select-String '"token"').ToString() -replace '.*"token":\s*"([^"]+)".*', '$1'

# 步驟 2：送出聊天訊息
curl.exe -s -X POST "$BASE/chat/user" `
  -H "Content-Type: application/json" `
  -H "X-MPU-Session-Token: $TOKEN" `
  -d '{"message":"hello","history":[]}'
```

**合格（AI 已設定）：** HTTP 200，JSON 回應包含 `"reply"` 欄位。
**合格（AI 未設定）：** HTTP 400/503 含 WP_Error 本文（HTTP 403 不可接受 — 那代表 Token 回歸）。

---

## 第 5 組 — 管理員專用防護

這些端點必須拒絕未認證的請求。若未認證即返回 200，表示管理員權限已暴露。

### 5-A  POST /test-connection/gemini — 無認證 → 401

```powershell
curl.exe -s -o NUL -w "%{http_code}" -X POST "$BASE/test-connection/gemini"
```

**合格：** HTTP **401**

---

### 5-B  POST /clear-cache — 無認證 → 401

```powershell
curl.exe -s -o NUL -w "%{http_code}" -X POST "$BASE/clear-cache"
```

**合格：** HTTP **401**

---

## 第 6 組 — SSE 端點標頭

確認串流端點返回正確的 Content-Type。不需要設定 AI，僅確認回應標頭。

### 6-A  POST /chat/user-stream — 標頭確認

```powershell
# 先取得 Token（重用第 4 組的 $TOKEN 或重新取得）
$TOKEN = (curl.exe -s "$BASE/session-token" | python -m json.tool | Select-String '"token"').ToString() -replace '.*"token":\s*"([^"]+)".*', '$1'

curl.exe -s -D - --max-time 3 -X POST "$BASE/chat/user-stream" `
  -H "Content-Type: application/json" `
  -H "X-MPU-Session-Token: $TOKEN" `
  -d '{"message":"hello","history":[]}' `
  2>&1 | Select-String "content-type|HTTP/"
```

**合格：** 回應標頭包含 `Content-Type: text/event-stream`。
若 AI 未設定，收到 `event: error` SSE 訊框可接受；收到純 HTTP 4xx/5xx 則為回歸。

---

## 發版前檢查清單

將此表格複製至發版說明並逐項確認：

| # | 端點 | 確認內容 | 合格？ |
|---|------|---------|--------|
| 1-A | GET /init | HTTP 200 + ghost_name 存在 | ☐ |
| 1-B | GET /settings | HTTP 200 + 功能旗標存在 | ☐ |
| 1-C | GET /visitor-info | HTTP 200 + 訪客欄位存在 | ☐ |
| 2-A | GET /session-token | HTTP 200 + token 欄位 + no-store 標頭 | ☐ |
| 2-B | GET /session-token ×11 | 至少一次 429 | ☐ |
| 3-A | POST /chat/user（無 Token） | HTTP 403 | ☐ |
| 3-B | POST /chat/greet（無 Token） | HTTP 403 | ☐ |
| 3-C | POST /chat/context（無 Token） | HTTP 403 | ☐ |
| 4 | POST /chat/user（有 Token） | 200 或非 403 錯誤 | ☐ |
| 5-A | POST /test-connection/gemini（無認證） | HTTP 401 | ☐ |
| 5-B | POST /clear-cache（無認證） | HTTP 401 | ☐ |
| 6-A | POST /chat/user-stream（有 Token） | Content-Type: text/event-stream | ☐ |

打發版標籤前，12 項全數合格才可繼續。
