# REST Smoke Test Checklist

Runnable before every release. Each section includes copy-pasteable `curl.exe` commands
(Windows PowerShell / cmd) and a pass/fail criterion. No AI API key is required for
Groups 1–2 and 5.

---

## Setup

```powershell
$BASE = "https://your-site.com/wp-json/mp-ukagaka/v1"
```

Replace `your-site.com` with the target site. All commands below reference `$BASE`.

---

## Group 1 — Baseline (no auth, no AI)

These endpoints must work on every page load regardless of AI configuration.

### 1-A  GET /init

```powershell
curl.exe -s "$BASE/init" | python -m json.tool
```

**Pass:** HTTP 200, JSON contains `ghost_name` and `personality_id`.

---

### 1-B  GET /settings

```powershell
curl.exe -s "$BASE/settings" | python -m json.tool
```

**Pass:** HTTP 200, JSON contains feature-flag fields (e.g. `ai_enabled`, `chat_mode`).

---

### 1-C  GET /visitor-info

```powershell
curl.exe -s "$BASE/visitor-info" | python -m json.tool
```

**Pass:** HTTP 200, JSON contains `ip`, `country`, or similar visitor fields.

---

## Group 2 — Session Token

### 2-A  Normal token fetch

```powershell
curl.exe -s -D - "$BASE/session-token"
```

**Pass:**
- HTTP 200
- `Cache-Control` header contains `no-store`
- Response body JSON contains `"token"` key with a non-empty string value

---

### 2-B  Rate limit enforcement (10 req / 60 s)

Run the following 11 times rapidly (modify the loop count as needed):

```powershell
1..11 | ForEach-Object { curl.exe -s -o NUL -w "%{http_code}`n" "$BASE/session-token" }
```

**Pass:** At least one response returns HTTP **429**.

---

## Group 3 — Token Enforcement (security regression)

Confirm that AI endpoints reject anonymous requests without a session token.
A regression here means the v2.15 session-token guard was broken.

### 3-A  POST /chat/user — no token → 403

```powershell
curl.exe -s -o NUL -w "%{http_code}" -X POST "$BASE/chat/user" `
  -H "Content-Type: application/json" `
  -d '{"message":"hello"}'
```

**Pass:** HTTP **403**

---

### 3-B  POST /chat/greet — no token → 403

```powershell
curl.exe -s -o NUL -w "%{http_code}" -X POST "$BASE/chat/greet" `
  -H "Content-Type: application/json" `
  -d '{}'
```

**Pass:** HTTP **403**

---

### 3-C  POST /chat/context — no token → 403

```powershell
curl.exe -s -o NUL -w "%{http_code}" -X POST "$BASE/chat/context" `
  -H "Content-Type: application/json" `
  -d '{}'
```

**Pass:** HTTP **403**

---

## Group 4 — Normal Chat Flow (requires AI configured)

Fetch a real token first, then send a chat request. This tests the full round-trip.
Skip if the test environment has no AI API key.

```powershell
# Step 1: fetch token
$TOKEN = (curl.exe -s "$BASE/session-token" | python -m json.tool | Select-String '"token"').ToString() -replace '.*"token":\s*"([^"]+)".*', '$1'

# Step 2: send chat
curl.exe -s -X POST "$BASE/chat/user" `
  -H "Content-Type: application/json" `
  -H "X-MPU-Session-Token: $TOKEN" `
  -d '{"message":"hello","history":[]}'
```

**Pass (AI configured):** HTTP 200, JSON response has `"reply"` field.
**Pass (AI not configured):** HTTP 400/503 with a WP_Error body (not HTTP 403 — that would be a token regression).

---

## Group 5 — Admin-only Guard

These endpoints must refuse unauthenticated requests. If they return 200 without auth, admin privileges are exposed.

### 5-A  POST /test-connection/gemini — no auth → 401

```powershell
curl.exe -s -o NUL -w "%{http_code}" -X POST "$BASE/test-connection/gemini"
```

**Pass:** HTTP **401**

---

### 5-B  POST /clear-cache — no auth → 401

```powershell
curl.exe -s -o NUL -w "%{http_code}" -X POST "$BASE/clear-cache"
```

**Pass:** HTTP **401**

---

## Group 6 — SSE Endpoint Headers

Confirm the streaming endpoint returns the correct content-type. AI does not need to be
configured — only the response headers matter here.

### 6-A  POST /chat/user-stream — headers check

```powershell
# Fetch a token first (reuse $TOKEN from Group 4, or re-fetch)
$TOKEN = (curl.exe -s "$BASE/session-token" | python -m json.tool | Select-String '"token"').ToString() -replace '.*"token":\s*"([^"]+)".*', '$1'

curl.exe -s -D - --max-time 3 -X POST "$BASE/chat/user-stream" `
  -H "Content-Type: application/json" `
  -H "X-MPU-Session-Token: $TOKEN" `
  -d '{"message":"hello","history":[]}' `
  2>&1 | Select-String "content-type|HTTP/"
```

**Pass:** Response headers contain `Content-Type: text/event-stream`.
If AI is not configured, an `event: error` SSE frame is acceptable — a plain HTTP 4xx/5xx is a regression.

---

## Pre-release Checklist

Copy this table into your release notes and check off each item:

| # | Endpoint | Check | Pass? |
|---|----------|-------|-------|
| 1-A | GET /init | HTTP 200 + ghost_name present | ☐ |
| 1-B | GET /settings | HTTP 200 + feature flags present | ☐ |
| 1-C | GET /visitor-info | HTTP 200 + visitor fields present | ☐ |
| 2-A | GET /session-token | HTTP 200 + token field + no-store header | ☐ |
| 2-B | GET /session-token ×11 | At least one 429 | ☐ |
| 3-A | POST /chat/user (no token) | HTTP 403 | ☐ |
| 3-B | POST /chat/greet (no token) | HTTP 403 | ☐ |
| 3-C | POST /chat/context (no token) | HTTP 403 | ☐ |
| 4 | POST /chat/user (with token) | 200 or non-403 error | ☐ |
| 5-A | POST /test-connection/gemini (no auth) | HTTP 401 | ☐ |
| 5-B | POST /clear-cache (no auth) | HTTP 401 | ☐ |
| 6-A | POST /chat/user-stream (with token) | Content-Type: text/event-stream | ☐ |

All 12 items must pass before tagging a release.
