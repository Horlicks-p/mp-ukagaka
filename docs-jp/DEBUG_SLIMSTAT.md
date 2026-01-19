# Slimstat API 連携デバッグガイド

本ドキュメントでは、Slimstat API が正しく連携されているか、AI が訪問者のソース情報を取得できているかを確認する方法を説明します。

## デバッグモードの有効化

### 方法 1：ブラウザコンソール（推奨）

1. サイトを開く
2. `F12` を押して開発者ツールを開く
3. 「Console」（コンソール）タブに切り替え
4. 以下のコマンドを入力してデバッグモードを有効化：

```javascript
window.mpuDebugMode = true
```

5. ページをリロード（または初回訪問者 Cookie をクリアしてから再訪問）

### 方法 2：WordPress デバッグモード

`wp-config.php` で WordPress デバッグモードを有効化：

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

これにより、`wp-content/debug.log` に AI 挨拶の詳細情報が記録されます。

## 確認項目

### 1. Slimstat が検出されているか確認

ブラウザコンソールで、以下のようなデバッグ情報が表示されるはずです：

```
=== 訪問者情報デバッグ ===
Slimstat 有効: true
Slimstat デバッグ情報: {
  class_exists: true,
  init_method_exists: true,
  get_recent_method_exists: true,
  referrers_found: 1,
  referer_extracted: "https://example.com",
  searchterms_found: 0,
  searchterms_extracted: "no_records",
  countries_found: 1,
  country_extracted: "Taiwan",
  cities_found: 1,
  city_extracted: "Taipei"
}
```

**`Slimstat 有効: false` の場合**：

- Slimstat プラグインがインストールされ、有効化されているか確認
- Slimstat プラグインのバージョンが API 呼び出しをサポートしているか確認

### 2. 訪問者情報が正しく取得されているか確認

デバッグ情報には以下が表示されます：

- **Referrer**: 訪問者のソース URL
- **Referrer Host**: ソースドメイン
- **Search Engine**: 検索エンジン名（ある場合）
- **Search Query**: 検索クエリ（ある場合）
- **Is Direct**: 直接アクセスかどうか
- **Country (Slimstat)**: 国（Slimstat から）
- **City (Slimstat)**: 都市（Slimstat から）

### 3. AI が訪問者情報を受け取っているか確認

`WP_DEBUG` を有効にしている場合、`wp-content/debug.log` に以下が表示されます：

```
MP Ukagaka - AI 挨拶プロンプト:
  - Referrer: https://www.google.com/search?q=example
  - Referrer Host: www.google.com
  - Search Engine: google
  - Search Query: example
  - Is Direct: いいえ
  - Country: Taiwan
  - City: Taipei
  - User Prompt: 訪問者が初めてサイトに来ました。訪問者は検索エンジン「google」から、検索クエリ「example」で来ました。訪問者は「Taiwan」の「Taipei」から来ました。親しみやすい口調で挨拶してください。
```

## よくある問題

### Q: Slimstat 有効が false と表示される

**A:** 以下の可能性があります：

1. Slimstat プラグインがインストールされていないか、有効化されていない
2. Slimstat のバージョンが古く、API をサポートしていない
3. Slimstat を最新バージョンに更新する必要がある

### Q: すべての Slimstat 情報が "no_records"

**A:** 以下の可能性があります：

1. 訪問者の初回訪問で、Slimstat にまだ記録がない
2. Slimstat のデータベースにその IP の履歴記録がない
3. Slimstat の地理情報機能が有効になっていない
4. **ローカル開発環境**：ローカル環境（`localhost`、`.local` ドメインなど）では、Slimstat は地理情報を取得できない場合があります。ローカル IP（127.0.0.1 など）は地理位置を解決できないためです

### Q: Country と City が「なし」と表示されるが、Referrer は取得できている

**A:** これは正常な現象で、以下の可能性があります：

1. **ローカル環境の制限**：ローカル開発環境（`wordsworth.wp.local` など）の IP アドレスは地理情報を解決できない
2. **Slimstat 設定**：Slimstat 設定で地理情報追跡機能が有効になっているか確認
3. **データベース記録**：Slimstat がその訪問者の地理情報を記録するにはトラッキングと記録が必要

**解決策**：

- 本番環境でテスト：実際のサーバーにデプロイ後、実際の訪問者 IP で地理情報を取得できるはず
- Slimstat 設定を確認：地理情報追跡機能が有効になっているか確認
- 記録を待つ：Slimstat が数回訪問をトラッキングした後でテスト

### Q: AI 挨拶で訪問者ソースが言及されない

**A:** 以下を確認：

1. デバッグ情報で `Referrer` または `Search Engine` に値があるか確認
2. AI の `ai_greet_prompt` 設定が正しいか確認
3. `debug.log` で AI に渡される `User Prompt` にソース情報が含まれているか確認

## テスト手順

1. **初回訪問者 Cookie をクリア**：
   - ブラウザコンソールで入力：`document.cookie.split(";").forEach(c => { if(c.includes("mpu_first_visit")) document.cookie = c.split("=")[0] + "=;expires=Thu, 01 Jan 1970 00:00:00 UTC;path=/"; });`

2. **デバッグモードを有効化**：
   - 入力：`window.mpuDebugMode = true`

3. **異なるソースからのアクセスをシミュレート**：
   - 直接アクセス：URL を直接入力
   - 検索エンジン：Google 検索結果からクリック
   - 外部サイト：他のサイトのリンクからアクセス

4. **デバッグ情報を確認**：
   - コンソール出力を確認
   - AI 挨拶内容にソース情報が含まれているか確認

## 関連ファイル

- `includes/ajax-handlers.php`: `mpu_ajax_get_visitor_info()` と `mpu_ajax_chat_greet()` 関数
- `ukagaka.js`: `mpu_greet_first_visitor()` 関数
