# 人格作成ガイド

> 🎭 MP Ukagaka の新しいキャラクター人格を作成する方法

---

## 📑 目次

1. [概要](#概要)
2. [必須ファイル](#必須ファイル)
3. [フォルダ構造](#フォルダ構造)
4. [manifest.json フォーマット説明](#manifestjson-フォーマット説明)
5. [人格プロンプト構造](#人格プロンプト構造)
6. [prompts.json フォーマット説明（LLM モード）](#promptsjson-フォーマット説明llm-モード)
7. [weights.json フォーマット説明（LLM モード）](#weightsjson-フォーマット説明llm-モード)
8. [decorations.json フォーマット説明（オプション）](#decorationsjson-フォーマット説明オプション)
9. [Shell 画像ファイル](#shell-画像ファイル)
10. [JavaScript スクリプト（オプション）](#javascript-スクリプトオプション)
11. [アップロードと使用](#アップロードと使用)
12. [完全な例](#完全な例)

---

## 概要

MP Ukagaka では、各キャラクターの人格は `ghost/` フォルダ内の、人格 ID を名前とした独立したフォルダに保存されます。完全な人格には通常、以下の内容が含まれます：

- **必須ファイル**：`manifest.json`、`shell/` フォルダ（キャラクターの画像を含む）
- **LLM モードのコアファイル**（AI 使用時）：`prompts.json`、`weights.json`、および人格プロンプトファイル
- **現在推奨されている人格プロンプト構造**：`instructions.md` + `personality.md`
- **旧バージョンの互換性**：`system_prompt.md` または `manifest.json` 内の `system_prompt`
- **オプションファイル**：`dynamics.json`、`decorations.json`、`decorations/`、`touchzones.json`、`sleep_mode.json`、`calendar.json`、`emoji-keywords.json`、`diary.json`、JavaScript スクリプト

---

## 必須ファイル

新しい人格を作成するには、**少なくとも以下のファイルが必要です**：

1. **`manifest.json`** - 人格のメタデータと設定
2. **`shell/{人格ID}/{人格ID}.png`** - キャラクターのメイン画像（少なくとも1枚）

### 最小構成の例

```
ghost/
└── MyCharacter/
    ├── manifest.json
    └── shell/
        └── MyCharacter/
            └── MyCharacter.png
```

---

## フォルダ構造

完全な人格のフォルダ構造は以下の通りです：

```
ghost/
└── {人格ID}/              # 人格フォルダ（例：Frieren、Sakura_Laurel）
    ├── manifest.json       # 必須：メタデータと設定
    ├── instructions.md     # 推奨：行動ルール / 対話プロトコル
    ├── personality.md      # 推奨：人格の背景 / キャラクター説明
    ├── system_prompt.md    # 旧互換：レガシープロンプトのフォールバック
    │
    ├── shell/              # 必須：キャラクター画像フォルダ
    │   └── {人格ID}/       # 画像サブフォルダ（通常は人格 ID と同じ名前）
    │       ├── {人格ID}.png          # メイン画像（必須）
    │       ├── {人格ID}[0].png       # アニメーションフレーム（オプション）
    │       ├── {人格ID}[1].png       # アニメーションフレーム（オプション）
    │       └── ...
    │
    ├── decorations/        # オプション：装飾品画像フォルダ
    │   ├── item1.png
    │   └── item2.png
    │
    ├── prompts.json        # LLM モード：対話カテゴリごとのプロンプト
    ├── weights.json        # LLM モード：カテゴリの重み設定
    ├── dynamics.json       # LLM モード：動的テンプレート（オプション）
    ├── decorations.json    # オプション：装飾品の設定
    ├── touchzones.json     # オプション：タッチ領域の設定
    ├── sleep_mode.json     # オプション：睡眠モードの設定
    ├── calendar.json       # オプション：休日 / 記念日の設定
    ├── diary.json          # オプション：AI 日記の設定
    ├── emoji-keywords.json # オプション：絵文字キーワードの設定
    └── {人格ID}.js         # オプション：JavaScript アニメーションスクリプト
```

---

## manifest.json フォーマット説明

`manifest.json` は人格のコア設定ファイルであり、人格の基本情報と設定を定義します。

### 必須フィールド

- `id`：人格の一意の識別子（英数字、アンダースコア、ハイフン。大文字で始まるキャメルケースを推奨）
- `name`：キャラクターの表示名（デフォルト言語）
- `shell_folder`：shell 画像フォルダの名前（通常は `id` と同じ）

### 完全なフィールド説明

```json
{
  "id": "MyCharacter",                    // 必須：人格 ID（一意の識別子）
  "name": "キャラクター名",               // 必須：キャラクターの表示名
  "name_en": "Character Name",            // オプション：英語名
  "name_zh": "キャラクター名",            // オプション：中国語名
  "version": "1.0.0",                     // オプション：バージョン番号
  "author": "作者名",                     // オプション：作者情報
  "description": "キャラクターの説明",    // オプション：キャラクターの紹介
  "description_en": "Character description",  // オプション：英語の説明
  "language": "ja",                       // オプション：主要言語（ja/zh-TW/en）
  "shell_folder": "MyCharacter",          // 必須：shell 画像フォルダ名
  "decorations_folder": "decorations",    // オプション：装飾品フォルダ名（デフォルトは "decorations"）
  "script": "mycharacter.js",             // オプション：古いフォーマット、単一の JavaScript スクリプト
  "scripts": ["mycharacter.js"],          // オプション：新しいフォーマット、複数のスクリプトをサポート
  
  "settings": {                           // オプション：行動設定
    "max_response_length": 500,           // 応答の長さ制限（文字数、デフォルトは 500）
    "max_tokens": 800,                     // API 呼び出し時のトークン制限（デフォルトは 800）
    "speech_style": "常体",                // 話し方（メタデータ、現在は実際には使用されていません）
    "tone": "淡々とした",                  // 語調（メタデータ、現在は実際には使用されていません）
    "emoji_style": "minimal"              // 絵文字のスタイル（メタデータ、現在は実際には使用されていません）
  },
  
  "character_traits": {                   // オプション：キャラクターの属性（メタデータ、現在は実際には使用されていません）
    "age": "18",
    "race": "人間",
    "occupation": "学生",
    "personality": ["明るい", "活発"],
    "aliases": ["ニックネーム1", "ニックネーム2"]
  },
  
  "system_prompt": "あなたは...",         // オプション：古いフォーマットのプロンプトのフォールバック（文字列または配列）
                                           // 代わりに instructions.md + personality.md を使用することをお勧めします
}
```

### 例

```json
{
  "id": "Frieren",
  "name": "フリーレン",
  "name_en": "Frieren",
  "name_zh": "芙莉蓮",
  "version": "1.0.0",
  "author": "和製ホーリックス",
  "description": "千年以上生きるエルフの魔法使い。淡々とした口調で語り、魔法収集が趣味。",
  "language": "ja",
  "shell_folder": "Frieren",
  "decorations_folder": "decorations",
  "script": "frieren.js",
  "settings": {
    "max_response_length": 500,
    "max_tokens": 800,
    "speech_style": "常体",
    "tone": "淡々とした",
    "emoji_style": "minimal"
  }
}
```

### settings フィールドの説明

`settings` オブジェクトにはキャラクターの行動設定が含まれており、そのうち文字数制限のメカニズムは3層の防御に統合されています：

#### 文字数制限の設定

- **`max_response_length`**（デフォルト：500）

  - バックエンドの切り捨て制限（文字数）
  - AI の応答がこの長さを超えると、システムは自動的に切り捨てて `...` を追加します
  - すべての対話タイプ（ページ認識、初回訪問者、インタラクティブ対話、タッチ領域、装飾品クリック、自発的な対話）にこの制限が適用されます
- **`max_tokens`**（デフォルト：800）

  - API 呼び出し時のトークン制限
  - AI モデルが生成する応答の最大トークン数を制御します
  - 約 600-800 文字に相当します（言語や内容によって異なります）
  - すべての AI 対話タイプでこの設定が使用されます

#### 3層の防御メカニズム

システムは統一された3層の文字数制限メカニズムを実装しています：

1. **プロンプトでの提案**：30-150文字（ソフトな誘導）

   - System Prompt および User Prompt で、AI に 30-250 文字の範囲内に収めるよう提案します
2. **API max_tokens**：800（`max_tokens` で設定可能）

   - AI モデルが生成する最大トークン数を制限します
   - `manifest.json` の `settings.max_tokens` から読み取られ、デフォルトは 800 です
3. **バックエンドでの切り捨て**：150文字（`max_response_length` で設定可能）

   - 最終的な安全防御層
   - `manifest.json` の `settings.max_response_length` から読み取られ、デフォルトは 500 です

### JSON フォーマットの規則

1. **ファイルエンコーディング**：必ず UTF-8 エンコーディングを使用してください。
2. **構文**：
   - 文字列はダブルクォーテーション `"` で囲んでください。
   - 最後のプロパティの後にカンマを置かないでください。
   - 配列やオブジェクトの最後の要素の後にカンマを置かないでください。
3. **コメント**：JSON 標準はコメントをサポートしていませんが、説明として `_comment` フィールドを使用できます。
4. **検証**：オンラインの JSON 検証ツールを使用して構文を確認できます。

**正しい例：**

```json
{
  "id": "MyCharacter",
  "name": "キャラクター名",
  "settings": {
    "max_response_length": 500,
    "max_tokens": 800
  }
}
```

**誤った例：**

```json
{
  "id": "MyCharacter",
  "name": "キャラクター名",  // ❌ JSON はコメントをサポートしていません
  "settings": {
    "max_response_length": 129,  // ❌ 最後のプロパティの後にカンマは置けません
  }
}
```

---

## 人格プロンプト構造

キャラクターを定義するには、**モジュラープロンプト (modular prompt)** 構造を使用することが現在推奨されています：

- `instructions.md`：行動ルール、語調、フォーマット制限、対話プロトコル
- `personality.md`：背景設定、世界観、好み、性格の補足

システムはまず `instructions.md` を読み込み、その後に `personality.md` を追加します。これが現在の**最優先事項**です。

### 優先順位

1. **`instructions.md` + `personality.md`**（現在推奨）⭐
2. `system_prompt.md`（レガシーなフォールバック）
3. `manifest.json` 内の `system_prompt` フィールド（レガシーなフォールバック）
4. 管理画面のグローバル設定（フォールバック）

### ファイルの場所

```text
ghost/{人格ID}/instructions.md
ghost/{人格ID}/personality.md
```

### フォーマット要件

- **エンコーディング**：UTF-8
- **フォーマット**：プレーンな Markdown テキストファイル
- **内容の推奨事項**：
  - `instructions.md` はルールと出力制限に焦点を当てます
  - `personality.md` は人格と背景に焦点を当てます

### 推奨される書き方

`instructions.md`

```markdown
# 対話プロトコル

- 応答は簡潔に保つこと
- 常体を使用すること
- 一人称は「私」を使用すること
- キャラクターから外れないこと
```

`personality.md`

```markdown
# キャラクター設定

あなたは「キャラクター名」です。

- 静かな性格
- 特定の話題に対して明らかな好みがある
- 話すペースは遅め
```

### レガシー互換性

古い人格フォーマットを維持したい場合は、引き続き以下のいずれかの方法を使用できます：

- `ghost/{人格ID}/system_prompt.md`
- `manifest.json` 内の `system_prompt`

ただし、新しい人格を作成する場合は、直接 `instructions.md + personality.md` を使用することをお勧めします。

### 変数のサポート

人格プロンプトは以下の変数の置換をサポートしています：

- `{{ukagaka_display_name}}`：キャラクターの表示名
- `{{language}}`：応答言語（zh-TW、ja、en）
- `{{time_context}}`：時間的文脈（例：「1月2日（木曜日）・冬の朝」）
- `{{wp_version}}`：WordPress バージョン
- `{{php_version}}`：PHP バージョン
- `{{theme_name}}`：テーマ名
- `{{theme_version}}`：テーマのバージョン
- `{{theme_author}}`：テーマの作者
- `{{post_count}}`：記事数
- `{{comment_count}}`：コメント数
- `{{category_count}}`：カテゴリ数
- `{{tag_count}}`：タグ数
- `{{days_operating}}`：サイト運営日数

**例：**

```markdown
あなたは「{{ukagaka_display_name}}」というキャラクターです。

現在の時間は {{time_context}} です。
```

### 完全な例

Markdown プロンプトの書き方を理解するには `example/system-prompt-markdown-example.md` を参照してください。現在のアーキテクチャに合わせるには、内容を `instructions.md` と `personality.md` に分割することをお勧めします。

---

## prompts.json フォーマット説明（LLM モード）

`prompts.json` は、LLM が自発的な対話を生成する際に使用するプロンプトのカテゴリを定義します。各カテゴリには複数のプロンプトテンプレートが含まれており、システムは重みに基づいてランダムに選択します。

### ファイル構造

```json
{
  "_comment": "キャラクター名 - Prompt Categories",
  "_format_version": "1.0",
  "_variable_placeholders": [
    "{time_context}", "{visitor_country}", "{bot_name}"
  ],
  
  "category_name": [
    "プロンプトテンプレート1",
    "プロンプトテンプレート2",
    "プロンプトテンプレート3"
  ]
}
```

### 推奨されるカテゴリ名

- `greeting`：挨拶
- `casual`：雑談
- `observation`：観察
- `memory`：思い出
- `time_aware`：時間認識
- `magic_collection`：魔法収集（またはキャラクターの趣味）
- `self_awareness`：自己認識
- `emotional_density`：感情密度
- など...

### 変数のプレースホルダー

プロンプト内で変数のプレースホルダーを使用でき、システムが自動的に置換します：

- `{time_context}`：時間的文脈
- `{wp_version}`：WordPress バージョン
- `{theme_name}`：テーマ名
- `{visitor_country}`：訪問者の国
- `{bot_name}`：BOT 名（検出された場合）
- など...

### 例

```json
{
  "_comment": "MyCharacter - Prompt Categories",
  "_format_version": "1.0",
  
  "greeting": [
    "淡々とした態度で、再訪を軽く認識する",
    "久しぶりの訪問に対して、「えっ」と少しびっくりした様子を見せる"
  ],
  
  "casual": [
    "目についたものについて、淡々とした感想を述べる",
    "ふと思い出した、特に意味のないことを呟く"
  ],
  
  "time_aware": [
    "人間にとっての時間について、短すぎるという実感を述べる",
    "「たった10年」という期間を、ほんの短い間として扱う"
  ]
}
```

---

## weights.json フォーマット説明（LLM モード）

`weights.json` は、各対話カテゴリの重みを定義します。重みが大きいほど、そのカテゴリが選択される確率が高くなります。

### ファイル構造

```json
{
  "_comment": "キャラクター名 - Category Weights Configuration",
  "_format_version": "1.0",
  
  "base_weights": {
    "category_name": 10,
    "another_category": 15
  },
  
  "time_adjustments": {
    "朝": {
      "category_name": 20
    },
    "夜": {
      "category_name": 5
    }
  }
}
```

### base_weights

基本の重みであり、すべての時間帯で使用されます。推奨される数値の範囲：**1-20**。

- 数値が大きい = 選択される確率が高い
- よく使用するカテゴリは 10-15 に設定することをお勧めします
- あまり使用しないカテゴリは 1-5 に設定します

### time_adjustments

時間帯に基づいて重みを調整します。`base_weights` と統合されます。

**サポートされている時間帯：**

- `深夜`：23:00-04:59
- `睡眠時間帯`：00:00-05:59
- `朝`：05:00-11:59
- `昼`：12:00-17:59
- `夜`：18:00-22:59

### 例

```json
{
  "_comment": "MyCharacter - Category Weights",
  "_format_version": "1.0",
  
  "base_weights": {
    "casual": 15,
    "observation": 15,
    "greeting": 6,
    "memory": 8,
    "time_aware": 10
  },
  
  "time_adjustments": {
    "深夜": {
      "memory": 15,
      "time_aware": 15,
      "casual": 5
    },
    "朝": {
      "greeting": 20,
      "casual": 15
    }
  }
}
```

---

## decorations.json フォーマット説明（オプション）

`decorations.json` は、キャラクターの装飾品（クリック可能なインタラクティブ要素）を定義します。

### ファイル構造

```json
{
  "_comment": "キャラクター名 - Decoration Click Prompts",
  "_format_version": "1.0",
  
  "decorations_base_folder": "decorations",
  
  "items": [
    {
      "type": "item_type",
      "image": "item.png",
      "position": {
        "top": "82%",
        "right": "-62px",
        "left": "auto"
      },
      "size": {
        "width": "90px",
        "height": "auto"
      },
      "transform": "translateY(-50%)",
      "z_index": 10,
      "prompt": "ユーザーがこの装飾品をクリックした時のプロンプト（50文字以内）"
    }
  ]
}
```

### フィールド説明

- `type`：装飾品の種類（一意の識別子）
- `image`：画像ファイル名（`decorations/` フォルダに保存）
- `position`：CSS の位置指定（`top`、`left`、`right`）
- `size`：画像のサイズ（`width`、`height`）
- `transform`：CSS transform（オプション）
- `z_index`：レイヤーの順序
- `prompt`：クリック時の LLM へのプロンプト

### 例

```json
{
  "_comment": "MyCharacter - Decorations",
  "_format_version": "1.0",
  
  "decorations_base_folder": "decorations",
  
  "items": [
    {
      "type": "suitcase",
      "image": "suitcase.png",
      "position": {
        "top": "82%",
        "right": "-62px",
        "left": "auto"
      },
      "size": {
        "width": "90px",
        "height": "auto"
      },
      "transform": "translateY(-50%)",
      "z_index": 10,
      "prompt": "ユーザーがスーツケースをクリックしました。このスーツケースについて語ってください（50文字以内）。"
    }
  ]
}
```

---

## Shell 画像ファイル

Shell 画像はキャラクターの視覚的な表現であり、`shell/{人格ID}/` フォルダに保存されます。

### 必須ファイル

- **`{人格ID}.png`**：メイン画像（必須）

### オプションファイル（アニメーション）

- `{人格ID}[0].png`、`{人格ID}[1].png`、...：アニメーションフレーム
- `{人格ID}[s].png`：特殊状態の画像
- `{人格ID}[w1].png`、`{人格ID}[w2].png`、...：起床アニメーションフレーム

### 命名規則

1. **メイン画像**：`{人格ID}.png`（例：`Frieren.png`）
2. **アニメーションフレーム**：`{人格ID}[数字].png`（例：`Frieren[0].png`、`Frieren[1].png`）
3. **特殊状態**：`{人格ID}[文字].png`（例：`Frieren[s].png`）

### 画像フォーマット

- **フォーマット**：PNG（推奨）または JPG
- **サイズ**：推奨幅 200-400px、高さはカスタマイズ可能
- **背景**：透明背景を推奨（PNG）

### ファイル構造の例

```
shell/
└── Frieren/
    ├── Frieren.png        # メイン画像（必須）
    ├── Frieren[0].png     # アニメーションフレーム 0
    ├── Frieren[1].png     # アニメーションフレーム 1
    ├── Frieren[2].png     # アニメーションフレーム 2
    ├── Frieren[s].png     # 特殊状態
    ├── Frieren[w1].png    # 起床アニメーション 1
    ├── Frieren[w2].png    # 起床アニメーション 2
    └── ...
```

---

## JavaScript スクリプト（オプション）

カスタムのアニメーションやインタラクティブな動作が必要な場合は、JavaScript スクリプトを作成できます。

### ファイルの場所

```text
ghost/{人格ID}/*.js
```

### manifest.json での指定

```json
{
  "id": "MyCharacter",
  "script": "mycharacter.js"
}
```

または、新しいマルチスクリプトフォーマットを使用します：

```json
{
  "id": "MyCharacter",
  "scripts": ["mycharacter.js", "mycharacter-extra.js"]
}
```

### 基本構造

人格には 1 つ以上のフロントエンドスクリプトを含めることができます。一般的なインタラクションスクリプトは `script` または `scripts` を通じて読み込むことができます。`*-emoji.js` の命名に一致する絵文字スクリプトは、絵文字システムによって独立して検出および読み込まれます。完全な例については `ghost/Frieren/frieren.js` および `ghost/Frieren/frieren-emoji.js` を参照してください。

---

## アップロードと使用

### 方法 1：ZIP アップロード（推奨）

1. すべての人格ファイルを ZIP ファイルにパッケージ化します。
2. WordPress 管理画面にログインし、**設定** → **MP Ukagaka** → **新規伺か作成** に移動します。
3. ZIP ファイルを選択してアップロードします。
4. システムが自動的に展開して検証します。
5. プレビュー情報が正しいことを確認した後、「確認して作成」をクリックします。

### 方法 2：手動アップロード

1. FTP またはファイルマネージャーを使用して、人格フォルダを `wp-content/plugins/mp-ukagaka/ghost/` にアップロードします。
2. WordPress 管理画面にログインし、**設定** → **MP Ukagaka** → **伺か管理** に移動します。
3. 新しいキャラクター設定を手動で追加します。

### ZIP ファイルの構造要件

ZIP ファイルを展開すると、直接 `manifest.json` と `shell/` フォルダが含まれている必要があります：

```
MyCharacter.zip
└── (展開後)
    ├── manifest.json
    ├── instructions.md
    ├── personality.md
    ├── shell/
    │   └── MyCharacter/
    │       └── MyCharacter.png
    ├── prompts.json
    └── weights.json
```

**注意**：ZIP ファイルには**トップレベルのフォルダ名を含めてはいけません**（例：`MyCharacter/manifest.json`）。ファイル自体が直接含まれている必要があります。

---

## 完全な例

以下は最もシンプルな人格の例です：

### 1. フォルダ構造

```
ghost/
└── SimpleCharacter/
    ├── manifest.json
    └── shell/
        └── SimpleCharacter/
            └── SimpleCharacter.png
```

### 2. manifest.json

```json
{
  "id": "SimpleCharacter",
  "name": "シンプルなキャラクター",
  "shell_folder": "SimpleCharacter"
}
```

### 3. instructions.md / personality.md（オプション、推奨）

```markdown
# 対話プロトコル

- 応答は50文字以内に保つこと
- 常体を使用すること（敬語は使用しない）
- 一人称は「私」を使用すること
```

```markdown
# キャラクターの定義

あなたは「シンプルなキャラクター」です。簡潔でフレンドリーな口調で訪問者と対話してください。

- 静かな性格
- 周囲を観察するのが好き
```

### 4. prompts.json（LLM モード、オプション）

```json
{
  "_comment": "SimpleCharacter - Prompt Categories",
  "_format_version": "1.0",
  
  "greeting": [
    "淡々とした態度で挨拶する",
    "訪問者に軽く声をかける"
  ],
  
  "casual": [
    "目についたものについて感想を述べる",
    "ふと思い出したことを呟く"
  ]
}
```

### 5. weights.json（LLM モード、オプション）

```json
{
  "_comment": "SimpleCharacter - Category Weights",
  "_format_version": "1.0",
  
  "base_weights": {
    "greeting": 10,
    "casual": 15
  }
}
```

---

## まとめ

新しい人格を作成するための基本的な手順：

1. ✅ `manifest.json` を作成する（必須）
2. ✅ `shell/{人格ID}/{人格ID}.png` を準備する（必須）
3. ⭐ `instructions.md` と `personality.md` を作成する（キャラクターの動作を定義するために推奨）
4. 📝 `prompts.json` と `weights.json` を作成する（LLM モード時に使用）
5. 🎨 `decorations.json` と装飾品画像を追加する（オプション）
6. 📦 ZIP にパッケージ化してアップロードする

**参考例**：完全な人格構造を理解するには `ghost/Frieren/` フォルダを確認してください。新しい人格を作成する際は、モジュラープロンプト構造を優先して使用してください。

---

**最終更新**：2026-01-15
