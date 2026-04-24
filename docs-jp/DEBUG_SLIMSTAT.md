# Slimstat 統合デバッグガイド

このドキュメントでは、Slimstat のデータが MP Ukagaka に正しく統合されているか、ページ認識 AI / 初回訪問者への挨拶が訪問元の情報を正しく取得できているかを確認する方法を説明します。

## デバッグモードの有効化

### 方法 1：ブラウザコンソール（推奨）

1. ウェブサイトを開きます。
2. `F12` キーを押して開発者ツールを開きます。
3. 「Console」（コンソール）タブに切り替えます。
4. 以下のコマンドを入力してデバッグモードを有効にします：

```javascript
window.mpuDebugMode = true
```

5. ページを再読み込みします（または、初回訪問の Cookie をクリアしてから再訪問します）。

### 方法 2：WordPress デバッグモード

`wp-config.php` で WordPress のデバッグモードを有効にします：

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

これにより、PHP 側のエラーが `wp-content/debug.log` に書き込まれます。フロントエンドの詳細は引き続きブラウザの Console を主に使用します。

## 確認項目

### 1. Slimstat が検出されているか確認する

ブラウザのコンソールで、現在のフロントエンドは通常、以下のような情報を出力します：

```
[MP Ukagaka] 訪客資訊 (Visitor Info): {
  referrer: "https://example.com",
  referrer_host: "example.com",
  search_engine: "google",
  country: "TW",
  city: "Taipei"
}
```

実際のフィールドデータは `/wp-json/mp-ukagaka/v1/visitor-info` から取得され、バックエンドの実装は `includes/rest/class-mpu-rest-dialog.php` にあります。

**`slimstat_enabled` が false の場合**：
- Slimstat プラグインがインストールされ、有効になっていることを確認します。
- データベーステーブルが存在し、プラグインが Slimstat の記録を読み取れることを確認します。

### 2. 訪問者情報が正しく取得されているか確認する

現在、フロントエンドでは以下の情報を使用または表示します：
- **Referrer**: 訪問元の URL
- **Referrer Host**: 訪問元のドメイン
- **Search Engine**: 検索エンジン名（ある場合）
- **Is Direct**: 直接アクセスかどうか
- **Country (Slimstat)**: 国（Slimstat から取得）
- **City (Slimstat)**: 都市（Slimstat から取得）

### 3. AI が訪問者情報を受け取っているか確認する

現在の初回訪問者への挨拶のフローは以下の通りです：

1. フロントエンドがまず `GET /visitor-info` を呼び出します。
2. 次に、整理されたデータを `POST /chat/greet` に送信します。

関連するプログラムの場所：

- `js/ukagaka-greeting.js`
- `includes/rest/class-mpu-rest-dialog.php`
- `includes/rest/class-mpu-rest-chat.php`

`window.mpuDebugMode = true` が有効になっている場合、Console で訪問者情報や後続のフローが正常に記録されているか確認できます。

`WP_DEBUG` / `WP_DEBUG_LOG` を有効にしている場合は、`wp-content/debug.log` で PHP 側のエラーを確認できます。ただし、以前のドキュメントにあったような固定フォーマットでの完全な greet prompt の出力は、必ず存在するとは限りません。

ブラウザの Network（ネットワーク）パネルで手動で確認することもできます：

```
GET  /wp-json/mp-ukagaka/v1/visitor-info
POST /wp-json/mp-ukagaka/v1/chat/greet
```

## よくある質問

### Q: `slimstat_enabled` が false と表示されます

**A:** 考えられる原因：
1. Slimstat プラグインがインストールされていない、または有効になっていない。
2. Slimstat のデータテーブルが存在しないか、サイトで読み取り可能な記録がまだ生成されていない。
3. 環境の制限により、対応する訪問者記録が取得できない。

### Q: すべての Slimstat 情報が "no_records" です

**A:** 考えられる原因：
1. これが訪問者の最初のアクセスであり、Slimstat がまだ記録していない。
2. Slimstat のデータベースにその IP の履歴記録がない。
3. Slimstat の位置情報（ジオロケーション）機能が有効になっていない。
4. **ローカル開発環境**：ローカル環境（`localhost`、`.local` ドメインなど）の場合、ローカル IP（127.0.0.1 など）は位置情報を解決できないため、Slimstat は位置情報を取得できない可能性があります。

### Q: Country と City が「無（なし）」と表示されますが、Referrer は取得できています

**A:** これは正常な動作です。考えられる原因：
1. **ローカル環境の制限**：ローカル開発環境（例：`wordsworth.wp.local`）の IP アドレスは位置情報を解決できません。
2. **Slimstat の設定**：Slimstat の設定で位置情報トラッキング機能が有効になっているか確認してください。
3. **データベースの記録**：Slimstat が訪問者の位置情報をまだ記録していない可能性があります（Slimstat がトラッキングして記録するのを待つ必要があります）。

**解決策**：
- 本番環境でテストする：実際のサーバーにデプロイした後であれば、実際の訪問者の IP から位置情報を取得できるはずです。
- Slimstat の設定を確認する：位置情報トラッキング機能が有効になっていることを確認します。
- 記録を待つ：Slimstat が数回のアクセスをトラッキングするまで待ってから、再度テストします。

### Q: AI の挨拶で訪問元について言及されません

**A:** 以下を確認してください：
1. `/visitor-info` のレスポンス内で `referrer` または `search_engine` に値があるか確認します。
2. AI の `ai_greet_prompt` 設定が正しく設定されているか確認します。
3. ブラウザの Network パネルを使用して、`/chat/greet` のリクエストペイロードに `referrer`、`referrer_host`、`search_engine`、`country`、`city` が含まれているか確認します。

## テスト手順

1. **初回訪問 Cookie のクリア**：
   - ブラウザのコンソールに以下を入力します：`document.cookie.split(";").forEach(c => { if(c.includes("mpu_first_visit")) document.cookie = c.split("=")[0] + "=;expires=Thu, 01 Jan 1970 00:00:00 UTC;path=/"; });`

2. **デバッグモードの有効化**：
   - コンソールに入力します：`window.mpuDebugMode = true`

3. **異なるアクセス元からのシミュレーション**：
   - 直接アクセス：URL を直接入力します。
   - 検索エンジン：Google の検索結果からクリックしてアクセスします。
   - 外部サイト：他のサイトのリンクからクリックしてアクセスします。

4. **デバッグ情報の確認**：
   - Console で `訪客資訊 (Visitor Info)` ログを確認します。
   - Network パネルで `/visitor-info` と `/chat/greet` を確認します。
   - AI の挨拶内容にアクセス元の情報が含まれているか確認します。

## 関連ファイル

- `includes/rest/class-mpu-rest-dialog.php`: `/visitor-info`
- `includes/rest/class-mpu-rest-chat.php`: `/chat/greet`
- `js/ukagaka-greeting.js`: `mpu_greet_first_visitor()` 関数
- `js/ukagaka-context.js`: ページ認識 AI も `visitor-info` を読み込みます
