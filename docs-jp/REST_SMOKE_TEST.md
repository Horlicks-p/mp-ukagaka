# REST スモークテスト チェックリスト

リリース前に毎回実行します。各セクションにはコピー可能な `curl.exe` コマンド
（Windows PowerShell / cmd）と合否判定基準を記載しています。グループ 1〜2 および 5 は
AI API キー不要です。

---

## セットアップ

```powershell
$BASE = "https://your-site.com/wp-json/mp-ukagaka/v1"
```

`your-site.com` を対象サイトのドメインに置き換えてください。以下のコマンドはすべて `$BASE` を参照します。

---

## グループ 1 — ベースライン（認証なし・AI なし）

AI 設定の有無にかかわらず、すべてのページ読み込みで動作しなければならないエンドポイントです。

### 1-A  GET /init

```powershell
curl.exe -s "$BASE/init" | python -m json.tool
```

**合格:** HTTP 200、JSON に `ghost_name` と `personality_id` が含まれる。

---

### 1-B  GET /settings

```powershell
curl.exe -s "$BASE/settings" | python -m json.tool
```

**合格:** HTTP 200、JSON に機能フラグフィールド（例: `ai_enabled`、`chat_mode`）が含まれる。

---

### 1-C  GET /visitor-info

```powershell
curl.exe -s "$BASE/visitor-info" | python -m json.tool
```

**合格:** HTTP 200、JSON に `ip`、`country` などの訪問者情報フィールドが含まれる。

---

## グループ 2 — セッショントークン

### 2-A  通常のトークン取得

```powershell
curl.exe -s -D - "$BASE/session-token"
```

**合格:**
- HTTP 200
- `Cache-Control` ヘッダーに `no-store` が含まれる
- レスポンス本文 JSON に空でない文字列値の `"token"` キーが含まれる

---

### 2-B  レート制限の確認（10 回 / 60 秒）

以下を 11 回連続で実行します（必要に応じてループ回数を変更してください）:

```powershell
1..11 | ForEach-Object { curl.exe -s -o NUL -w "%{http_code}`n" "$BASE/session-token" }
```

**合格:** 少なくとも 1 回のレスポンスが HTTP **429** を返す。

---

## グループ 3 — トークン強制（セキュリティ回帰テスト）

セッショントークンなしの匿名リクエストを AI エンドポイントが拒否することを確認します。
ここで回帰が発生した場合、v2.15 のセッショントークンガードが壊れています。

### 3-A  POST /chat/user — トークンなし → 403

```powershell
curl.exe -s -o NUL -w "%{http_code}" -X POST "$BASE/chat/user" `
  -H "Content-Type: application/json" `
  -d '{"message":"hello"}'
```

**合格:** HTTP **403**

---

### 3-B  POST /chat/greet — トークンなし → 403

```powershell
curl.exe -s -o NUL -w "%{http_code}" -X POST "$BASE/chat/greet" `
  -H "Content-Type: application/json" `
  -d '{}'
```

**合格:** HTTP **403**

---

### 3-C  POST /chat/context — トークンなし → 403

```powershell
curl.exe -s -o NUL -w "%{http_code}" -X POST "$BASE/chat/context" `
  -H "Content-Type: application/json" `
  -d '{}'
```

**合格:** HTTP **403**

---

## グループ 4 — 通常のチャットフロー（AI 設定が必要）

まず実際のトークンを取得してからチャットリクエストを送信します。フルラウンドトリップをテストします。
テスト環境に AI API キーがない場合はスキップしてください。

```powershell
# ステップ 1: トークン取得
$TOKEN = (curl.exe -s "$BASE/session-token" | python -m json.tool | Select-String '"token"').ToString() -replace '.*"token":\s*"([^"]+)".*', '$1'

# ステップ 2: チャット送信
curl.exe -s -X POST "$BASE/chat/user" `
  -H "Content-Type: application/json" `
  -H "X-MPU-Session-Token: $TOKEN" `
  -d '{"message":"hello","history":[]}'
```

**合格（AI 設定済み）:** HTTP 200、JSON レスポンスに `"reply"` フィールドがある。
**合格（AI 未設定）:** HTTP 400/503 と WP_Error 本文（HTTP 403 は不可 — それはトークン回帰）。

---

## グループ 5 — 管理者専用ガード

これらのエンドポイントは未認証リクエストを拒否しなければなりません。認証なしで 200 が返る場合、管理者権限が公開されています。

### 5-A  POST /test-connection/gemini — 認証なし → 401

```powershell
curl.exe -s -o NUL -w "%{http_code}" -X POST "$BASE/test-connection/gemini"
```

**合格:** HTTP **401**

---

### 5-B  POST /clear-cache — 認証なし → 401

```powershell
curl.exe -s -o NUL -w "%{http_code}" -X POST "$BASE/clear-cache"
```

**合格:** HTTP **401**

---

## グループ 6 — SSE エンドポイントヘッダー

ストリーミングエンドポイントが正しい Content-Type を返すことを確認します。AI の設定は不要で、レスポンスヘッダーのみを確認します。

### 6-A  POST /chat/user-stream — ヘッダー確認

```powershell
# まずトークンを取得（グループ 4 の $TOKEN を再利用するか再取得）
$TOKEN = (curl.exe -s "$BASE/session-token" | python -m json.tool | Select-String '"token"').ToString() -replace '.*"token":\s*"([^"]+)".*', '$1'

curl.exe -s -D - --max-time 3 -X POST "$BASE/chat/user-stream" `
  -H "Content-Type: application/json" `
  -H "X-MPU-Session-Token: $TOKEN" `
  -d '{"message":"hello","history":[]}' `
  2>&1 | Select-String "content-type|HTTP/"
```

**合格:** レスポンスヘッダーに `Content-Type: text/event-stream` が含まれる。
AI が未設定の場合、`event: error` SSE フレームは許容されます。通常の HTTP 4xx/5xx は回帰です。

---

## リリース前チェックリスト

このテーブルをリリースノートにコピーして各項目を確認してください:

| # | エンドポイント | 確認内容 | 合否 |
|---|--------------|---------|------|
| 1-A | GET /init | HTTP 200 + ghost_name あり | ☐ |
| 1-B | GET /settings | HTTP 200 + 機能フラグあり | ☐ |
| 1-C | GET /visitor-info | HTTP 200 + 訪問者フィールドあり | ☐ |
| 2-A | GET /session-token | HTTP 200 + token フィールド + no-store ヘッダー | ☐ |
| 2-B | GET /session-token ×11 | 少なくとも 1 回の 429 | ☐ |
| 3-A | POST /chat/user（トークンなし） | HTTP 403 | ☐ |
| 3-B | POST /chat/greet（トークンなし） | HTTP 403 | ☐ |
| 3-C | POST /chat/context（トークンなし） | HTTP 403 | ☐ |
| 4 | POST /chat/user（トークンあり） | 200 または 403 以外のエラー | ☐ |
| 5-A | POST /test-connection/gemini（認証なし） | HTTP 401 | ☐ |
| 5-B | POST /clear-cache（認証なし） | HTTP 401 | ☐ |
| 6-A | POST /chat/user-stream（トークンあり） | Content-Type: text/event-stream | ☐ |

リリースタグを打つ前に 12 項目すべてが合格していなければなりません。
