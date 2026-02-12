# MP Ukagaka

WordPress サイトにインタラクティブな伺か（デスクトップマスコット）キャラクターを作成するプラグイン。AI コンテキスト認識機能搭載。

[![Plugin Version](https://img.shields.io/badge/version-2.6.0-blue.svg)](https://github.com)
[![WordPress](https://img.shields.io/badge/WordPress-5.0%2B-blue.svg)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple.svg)](https://www.php.net/)

🌍 **他の言語**: [English](README.md) | [繁體中文](README_zh-TW.md)

## 📢 はじめに（必読）

本プラグインは、10 年以上前に Ariagle 氏が公開した WordPress プラグイン「MP Ukagaka」をベースに、大幅に拡張・派生させたバージョンです。

> ⚠️ **重要なお知らせ**：本プラグインのコードの約 90% は AI 支援開発（バイブコーディング）で作成されています。数え切れないほどのデバッグと改良を経ていますが、未知のバグや不完全なコード構造が存在する可能性があります。ご使用前にこのリスクをご理解ください。

📺 **デモサイト**：[https://www.moelog.com](https://www.moelog.com/)

### キャラクター人格作成について

本プラグインには **新しい人格を作成する** 機能があります（[GHOST_CREATE_GUIDE.md](docs-jp/GHOST_CREATE_GUIDE.md) を参照）。ただし、開発の重点はデフォルトキャラクター「フリーレン」の制作に注がれているため、この機能は十分にテストされていません。ご了承ください。

デフォルトキャラクター「フリーレン」をそのまま使用する場合、基本的なセリフはプラグインに内蔵されており、すぐに使用できます。より豊かでインタラクティブな対話体験をお求めの場合は、AI モデルの API Key を設定することをおすすめします。また、キャラクターの記憶設定ファイル [system_prompt.md](ghost/Frieren/system_prompt.md)（アニメ第1期の記憶を含む）も内蔵されていますが、その中の `{{admin_nickname}}` と `{{admin_name}}` をご自身のニックネームに置き換え、誕生日もご自身の設定に合わせて変更してください。

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
- **インタラクティブ対話モード**：訪問者とのリアルタイム対話
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

## 🎉 v2.7.0 の新機能

**外部プラグイン連携**：Akismet または Turnstile プラグインがインストールされている場合、それらがブロック動作を実行した際に対応する反応対話をトリガーします。

- **クールダウン機能**：独立した反応クールダウン（30 分）を実装し、対話が頻繁になりすぎるのを防止。

**BOT 検知機能**：検索エンジンクローラーや悪意のあるボットの訪問をリアルタイムで検知。

- 人格システム（`bot_detection`）と深く統合し、専用の警告ダイアログをトリガーします。
- Slimstat データに基づいた、より正確な検知を提供。

**自動対話の最適化**：スパムと BOT 検知イベントの優先順位処理ロジックを最適化。

[完全な変更履歴を表示](docs-jp/CHANGELOG.md)

## ❓ よくある質問

**AI がトリガーされないのはなぜ？**

- API Key が有効か確認
- ページがトリガー条件に一致しているか確認（例：`is_single`）
- 確率が設定されているか確認（テスト時は 100% を試す）
- コンテンツ長を確認（\>500 文字必要）

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
- [ユーザーガイド](docs-jp/USER_GUIDE.md) と [トラブルシューティング](docs-jp/USER_GUIDE.md#トラブルシューティング) を確認
- GitHub で Issue を開く

## 👥 クレジット

- **オリジナル作者**：Ariagle
- **メンテナー**：Horlicks ([萌えログ.COM](https://www.moelog.com/))
- **インスピレーション**：クラシック MP Ukagaka プラグイン / 伺か (Ukagaka)

## 📄 ライセンス

オリジナルの MP Ukagaka プラグインをベースにしています。オリジナルプラグインのライセンス条項を参照してください。

---

**Made with ❤ for WordPress コミュニティ**
