# MP Ukagaka

WordPress サイトにインタラクティブな伺か（デスクトップマスコット）キャラクターを作成するプラグイン。AI コンテキスト認識機能搭載。

[![Plugin Version](https://img.shields.io/badge/version-2.25.1-blue.svg)](https://github.com)
[![WordPress](https://img.shields.io/badge/WordPress-5.0%2B-blue.svg)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple.svg)](https://www.php.net/)

🌍 **他の言語**: [English](README.md) | [繁體中文](README_zh-TW.md)

## 📢 はじめに（必読）

本プラグインは、10 年以上前に Ariagle 氏が公開した WordPress プラグイン「MP Ukagaka」をベースに、大幅に拡張・派生させたバージョンです。

> ⚠️ **重要なお知らせ**：本プラグインのコードの約 90% は AI 支援開発（バイブコーディング）で作成されています。数え切れないほどのデバッグと改良を経ていますが、未知のバグや不完全なコード構造が存在する可能性があります。ご使用前にこのリスクをご理解ください。

📺 **デモサイト**：[https://www.moelog.com](https://www.moelog.com/)

### キャラクター人格作成について

本プラグインには **新しい人格を作成する** 機能があります（[GHOST_CREATE_GUIDE.md](docs-jp/GHOST_CREATE_GUIDE.md) を参照）。ただし、開発の重点はデフォルトキャラクター「フリーレン」の制作に注がれているため、この機能は十分にテストされていません。ご了承ください。

デフォルトキャラクター「フリーレン」をそのまま使用する場合、基本的なセリフはプラグインに内蔵されており、すぐに使用できます。より豊かでインタラクティブな対話体験をお求めの場合は、AI モデルの API Key を設定することをおすすめします。また、キャラクターの記憶設定ファイル（読み込み順序：personality.md、instructions.md、そして system_prompt.md、アニメ第1期の記憶を含む）も内蔵されています。管理人のニックネーム・短縮名・誕生日は、**設定 → MP Ukagaka → 一般設定** から **Admin full nickname**、**Admin short name**、**Admin birthday**（MM-DD 形式、例：`10-18`）を直接設定できます。personality ファイル内の `{{admin_nickname}}`、`{{admin_name}}`、`{{admin_birthday}}` はプレースホルダーとして機能し、管理画面の設定値が実行時に自動で置換されます。そのため、personality ファイルや `calendar.json` を手動で編集する必要はありません。キャラクターはこの設定に基づいて、誕生日も自動的にお祝いします。

### AI モデルの推奨

本プラグインは Gemini、OpenAI、Claude、Ollama など複数の AI プロバイダーをサポートしています。テスト結果に基づくと、**GPT-4o Mini** は対話生成品質と API コストのバランスが非常に優れており、強くおすすめできる選択肢です。

## 📸 スクリーンショット

![MP Ukagaka デモ](screenshot.PNG)

_フリーレンが記事内容に基づいて AI 生成のダイアログを表示_

> 💡 **その他のスクリーンショット**：
>
> - `screenshot2.PNG` - 一般設定と LLM 設定ページ
> - `screenshot3.PNG` - インタラクティブ対話モードのデモ（v2.3.0 新機能）

## ✨ コア機能

- **複数キャラクター対応**：複数のゴーストキャラクターを作成・管理
- **AI コンテキスト認識**：Gemini、OpenAI、Claude、Ollama を使用したインテリジェントな応答
- **インタラクティブ対話モード**：訪問者とのリアルタイム対話、SSE streaming 応答をサポート
- **外部ダイアログファイル**：TXT および JSON 形式のダイアログをサポート
- **Canvas アニメーション**：単一の画像または複数フレームアニメーションをサポート
- **多言語対応**：英語、繁体中国語、日本語
- **セキュリティ優先**：API Key 暗号化、CSRF 保護、XSS 防止

## 🚀 クイックスタート

### インストール

1. `wp-content/plugins/` にダウンロードまたはクローン
2. WordPress 管理画面 → プラグインで有効化
3. **設定 → MP Ukagaka** に移動

### 基本設定

1. **一般設定**：デフォルトキャラクターを選択し、表示設定を構成
2. **キャラクター作成**：画像 URL とダイアログでキャラクターを追加
3. **ダイアログファイル**：ダイアログは自動的に `dialogs/` フォルダに保存

### AI 機能を有効化（オプション）

**LLM 設定**：

- プロバイダーを選択：Ollama（無料）、Gemini、OpenAI、または Claude
- API Key を入力（自動暗号化）または Ollama エンドポイントを設定
- 「組み込みダイアログを置き換える」を有効化

**AI 設定**：

- 「ページ認識機能」を有効化
- トリガー確率を設定（コスト管理には 10-30% 推奨）
- System Prompt でキャラクターの個性をカスタマイズ

**対話モード**：

- 一般設定で「インタラクティブ対話機能を有効にする」を有効化
- 「伺か切り替え」ボタンが対話インターフェースに変わります

## 🤖 AI プロバイダー

| プロバイダー | コスト   | セットアップ                                                                     |
| ------------ | -------- | -------------------------------------------------------------------------------- |
| **Ollama**   | 無料     | ローカルにインストールまたはリモートサーバーに接続                               |
| **Gemini**   | 有料 API | [Google AI Studio](https://makersuite.google.com/app/apikey) から API Key を取得 |
| **OpenAI**   | 有料 API | [OpenAI Platform](https://platform.openai.com/api-keys) から API Key を取得      |
| **Claude**   | 有料 API | [Anthropic Console](https://console.anthropic.com/) から API Key を取得          |

## 📚 ドキュメント

詳細情報については以下を参照してください：

- **[ユーザーガイド](docs-jp/USER_GUIDE.md)** - 完全なセットアップと設定ガイド
- **[開発者ガイド](docs-jp/DEVELOPER_GUIDE.md)** - アーキテクチャと開発情報
- **[API リファレンス](docs-jp/API_REFERENCE.md)** - 関数とフック参照
- **[変更履歴](docs-jp/CHANGELOG.md)** - バージョン履歴

## 🎉 v2.25.3 の新機能

**Emotion tag 表示の修正（イベント応答）**：`check-spam-event` エンドポイント（Turnstile・Akismet スパム・bot blocker・bot アラート・AI クローラー・訪問者パルスの反応）がバックエンドの response normalizer を通るようになり、サポート済み `[tag]`（例：`[smirk]`）が会話ボックスに漏れなくなり、保存される checksum もクリーン済みテキストと一致します。これらのイベント応答も他の REST パスと同様に構造化された `emoji` / `emotion_tags` を返します。（v2.25.2 はページ感知／挨拶／会話パスを修正、本リリースで残るイベントパスを塞ぎました。）

### v2.25.0 のハイライト

**Emotion tag パイプライン**：AI 応答で inline `[tag]` 表情マーカーを使えるようになりました。新しい response normalizer により、display/history/checksum/TTS は同じクリーン済みテキストを共有し、emotion tag は REST / SSE で利用できる構造化データとして抽出されます。

**Frieren 表情プロンプトの切り替え**：Frieren は `manifest.json` で対応 emoji tag を宣言し、従来の末尾 `[表情:xxx]` 指示ではなく、新しい inline tag 形式を使います。旧形式の互換解析は維持しています。

**ストリーミング対応**：SSE 出力は、chunk をまたいで分割された emotion tag や think block を解析できるようになりました。Markdown link の誤検出を避け、ストリーム中に明示された表情を完了時のキーワード推測で上書きしません。

**Think bubble placeholder**：`えっと` や初期表示の `何を話せばいいかな` などの system placeholder は、メイン吹き出しではなくキャラクター側の think bubble に表示されます。Touch、decoration、初期読み込みの流れも調整し、stale placeholder 状態や空のメイン吹き出しのちらつきを避けます。

**注意**：Ollama `message.thinking` の接続は一度実装後に revert しました。Ollama は reasoning と final content が `num_predict` 予算を共有するため、空 / 途中で切れた返答、history の user 連続、checksum 不一致が発生しました。Think bubble 本体は読み込み / placeholder UI（初期の `何を話せばいいかな`、touch / decoration の思考状態）として正式に稼働しています。一方「LLM `<think>` 内心独白を吹き出しへ流す」経路は検証の結果お蔵入りとなりました——コードはツリーに残していますが、供給する provider も要求する prompt もなく、ユーザーに見える挙動はありません。本プロジェクトでは今後メンテナンスしませんが、パイプライン全体（normalizer・SSE parser・吹き出し）はそのまま同梱しています。`DEVELOPER_GUIDE.md` の「Inner Monologue (`<think>`) Channel」節に、開発者向け opt-in の接続点と落とし穴を記載しています。

### 前バージョンの主な変更

**Observation の装飾品名解決**（v2.24.1）：直近の訪問者行動に記録された装飾品 type slug を、プロンプト注入前に読みやすい表示名へ解決します。

**コード品質フロー**（v2.24.1）：PHPCS baseline workflow を追加し、`lint:phpcs` を検証フローへ組み込みました。

[完全な変更履歴を表示](docs-jp/CHANGELOG.md)

## ❓ よくある質問

**AI がトリガーされないのはなぜ？**

- API Key が有効か確認
- ページがトリガー条件に一致しているか確認（例：`is_single`）
- 確率が設定されているか確認（テスト時は 100% を試す）
- コンテンツ長を確認（\>300 文字必要）

**API コストを抑えるには？**

- 確率を 10-20% に設定
- より安価なモデルを使用（gemini-2.5-flash、gpt-4o-mini）
- トリガーページを `is_single` に制限

**LLM 接続が失敗する？**

- Ollama の場合：サービスがポート 11434 で実行中か確認
- リモートの場合：Cloudflare Tunnel またはネットワーク接続を確認
- 設定のテストボタンで接続をテスト

[ユーザーガイドでさらに FAQ を確認](docs-jp/USER_GUIDE.md#よくある質問)

## 🔒 セキュリティ機能

- **API Key 暗号化**：すべての API Key を AES-256-CBC で暗号化
- **CSRF 保護**：すべてのフォームで WordPress nonce 検証
- **XSS 防止**：WordPress コア関数を使用した入力/出力サニタイズ
- **安全なファイル操作**：パス検証と WordPress Filesystem API

## 💬 サポート

- [萌えログ.COM](https://www.moelog.com/) を訪問
- [ユーザーガイド](docs-jp/USER_GUIDE.md) と [よくある質問](docs-jp/USER_GUIDE.md#よくある質問) を確認
- GitHub で Issue を開く

## 👥 クレジット

- **オリジナル作者**：Ariagle
- **メンテナー**：Horlicks ([萌えログ.COM](https://www.moelog.com/))
- **インスピレーション**：クラシック MP Ukagaka プラグイン / 伺か (Ukagaka)

## 📄 ライセンス

オリジナルの MP Ukagaka プラグインをベースにしています。オリジナルプラグインのライセンス条項を参照してください。

---

**Made with ❤ for WordPress コミュニティ**
