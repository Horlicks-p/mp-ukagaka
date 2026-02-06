# 人格作成ガイド

> 🎭 MP Ukagaka 用の新しいキャラクター人格を作成する方法

---

## 📑 目次

1. [概要](#概要)
2. [必須ファイル](#必須ファイル)
3. [フォルダ構成](#フォルダ構成)
4. [manifest.json フォーマット説明](#manifestjson-フォーマット説明)
5. [system_prompt.md 使用方法](#system_promptmd-使用方法)
6. [prompts.json フォーマット説明（LLM モード）](#promptsjson-フォーマット説明llm-モード)
7. [weights.json フォーマット説明（LLM モード）](#weightsjson-フォーマット説明llm-モード)
8. [decorations.json フォーマット説明（オプション）](#decorationsjson-フォーマット説明オプション)
9. [シェル画像ファイル](#シェル画像ファイル)
10. [JavaScript スクリプト（オプション）](#javascript-スクリプトオプション)
11. [アップロードと使用](#アップロードと使用)
12. [完全な例](#完全な例)

---

## 概要

MP Ukagaka では、各キャラクター人格は `ghost/` フォルダ内の人格 ID を名前にした独立したフォルダに格納されます。完全な人格には以下が含まれます：

- **必須ファイル**：`manifest.json`、`shell/` フォルダ（キャラクター画像を含む）
- **LLM モードファイル**（AI 使用時）：`prompts.json`、`weights.json`、`system_prompt.md`（または `manifest.json` 内の `system_prompt` フィールド）
- **オプションファイル**：`decorations.json`、`decorations/` フォルダ、JavaScript スクリプト、`dynamics.json`

---

## 必須ファイル

新しい人格を作成するには、**少なくとも以下のファイルが必要です**：

1. **`manifest.json`** - 人格のメタデータと設定
2. **`shell/{人格ID}/{人格ID}.png`** - キャラクターのメイン画像（少なくとも1枚）

### 最小構成例

```
ghost/
└── MyCharacter/
    ├── manifest.json
    └── shell/
        └── MyCharacter/
            └── MyCharacter.png
```

---

## フォルダ構成

完全な人格フォルダ構成は以下の通りです：

```
ghost/
└── {人格ID}/              # 人格フォルダ（例：Frieren、Sakura_Laurel）
    ├── manifest.json       # 必須：メタデータと設定
    ├── system_prompt.md    # 推奨：System Prompt（Markdown 形式）
    │
    ├── shell/              # 必須：キャラクター画像フォルダ
    │   └── {人格ID}/       # 画像サブフォルダ（通常は人格 ID と同じ）
    │       ├── {人格ID}.png          # メイン画像（必須）
    │       ├── {人格ID}[0].png       # アニメーションフレーム（オプション）
    │       ├── {人格ID}[1].png       # アニメーションフレーム（オプション）
    │       └── ...
    │
    ├── decorations/        # オプション：デコレーション画像フォルダ
    │   ├── item1.png
    │   └── item2.png
    │
    ├── prompts.json        # LLM モード：対話カテゴリプロンプト
    ├── weights.json        # LLM モード：カテゴリ重み設定
    ├── dynamics.json       # LLM モード：動的テンプレート（オプション）
    ├── decorations.json    # オプション：デコレーション設定
    └── {人格ID}.js         # オプション：JavaScript アニメーションスクリプト
```

---

## manifest.json フォーマット説明

`manifest.json` は人格のコア設定ファイルであり、基本情報と構成を定義します。

### 必須フィールド

- `id`：人格のユニーク識別子（英数字、アンダースコア、ハイフン。大文字で始まるキャメルケース推奨）
- `name`：キャラクター表示名（デフォルト言語）
- `shell_folder`：シェル画像フォルダの名前（通常は `id` と同じ）

### 完全なフィールド説明

```json
{
  "id": "MyCharacter",                    // 必須：人格 ID（ユニーク識別子）
  "name": "キャラクター名",               // 必須：キャラクター表示名
  "name_en": "Character Name",            // オプション：英語名
  "name_zh": "角色名稱",                  // オプション：中国語名
  "version": "1.0.0",                     // オプション：バージョン番号
  "author": "作者名",                     // オプション：作者情報
  "description": "キャラクター説明",      // オプション：キャラクター紹介
  "description_en": "Character description",  // オプション：英語説明
  "language": "ja",                       // オプション：主要言語（ja/zh-TW/en）
  "shell_folder": "MyCharacter",          // 必須：シェル画像フォルダ名
  "decorations_folder": "decorations",    // オプション：デコレーションフォルダ名（デフォルト "decorations"）
  "script": "mycharacter.js",             // オプション：JavaScript スクリプトファイル名
  
  "settings": {                           // オプション：動作設定
    "max_response_length": 500,           // 応答長制限（文字数、デフォルト 500）
    "max_tokens": 800,                     // API 呼び出し時のトークン制限（デフォルト 800）
    "speech_style": "常体",                // 話し方（メタデータ、現在は未使用）
    "tone": "淡々とした",                  // 口調（メタデータ、現在は未使用）
    "emoji_style": "minimal"              // 絵文字スタイル（メタデータ、現在は未使用）
  },
  
  "character_traits": {                   // オプション：キャラクター属性（メタデータ、現在は未使用）
    "age": "18",
    "race": "Human",
    "occupation": "Student",
    "personality": ["Cheerful", "Lively"],
    "aliases": ["Nickname1", "Nickname2"]
  },
  
  "system_prompt": "あなたは...",         // オプション：System Prompt（文字列または配列）
                                           // 注意：system_prompt.md ファイルの使用を推奨
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

### settings フィールド説明

`settings` オブジェクトにはキャラクターの動作設定が含まれ、応答長制限メカニズムは三層保護システムに統一されています：

#### 応答長設定

- **`max_response_length`**（デフォルト：500）

  - バックエンドの切り詰め制限（文字数）
  - AI の応答がこの長さを超える場合、システムは自動的に切り詰めて `...` を追加します
  - すべての対話タイプ（ページ認識、初回訪問者挨拶、インタラクティブチャット、タッチゾーン、デコレーションクリック、自発的対話）に適用されます
- **`max_tokens`**（デフォルト：800）

  - API 呼び出し時のトークン制限
  - AI モデルが生成できる最大トークン数を制御します
  - 約 600-800 文字に相当します（言語と内容によって異なります）
  - すべての AI 対話タイプで使用されます

#### 三層保護メカニズム

システムは統一された三層の応答長制限メカニズムを実装しています：

1. **プロンプト提案**：30-250 文字（ソフトガイダンス）

   - System Prompt と User Prompt で AI に 30-250 文字の範囲内に収めるよう提案します
2. **API max_tokens**：800（`max_tokens` で設定可能）

   - AI モデルが生成できる最大トークン数を制限します
   - `manifest.json` の `settings.max_tokens` から読み取られます。デフォルト 800
3. **バックエンド切り詰め**：500 文字（`max_response_length` で設定可能）

   - 最終的な安全層
   - `manifest.json` の `settings.max_response_length` から読み取られます。デフォルト 500

### JSON フォーマットルール

1. **エンコーディング**：必ず UTF-8 エンコーディングを使用してください。
2. **構文**：
   - 文字列はダブルクォート `"` で囲む必要があります。
   - 最後のプロパティの後にカンマを**入れてはいけません**。
   - 配列やオブジェクトの最後の要素の後にカンマを**入れてはいけません**。
3. **コメント**：標準 JSON はコメントをサポートしていませんが、`_comment` フィールドを注釈として使用できます。
4. **検証**：オンライン JSON 検証ツールを使用して構文をチェックできます。

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

**間違った例：**

```json
{
  "id": "MyCharacter",
  "name": "キャラクター名",  // ❌ JSON はコメントをサポートしていません
  "settings": {
    "max_response_length": 129,  // ❌ 最後のプロパティの後にカンマを入れてはいけません
  }
}
```

---

## system_prompt.md 使用方法

`system_prompt.md` はキャラクターの System Prompt を定義するための Markdown ファイルで、最高の優先順位を持ちます。

### 優先順位

1. **`system_prompt.md`**（最高優先順位）⭐
2. `manifest.json` 内の `system_prompt` フィールド
3. バックエンドグローバル設定（フォールバック）

### ファイル場所

```
ghost/{人格ID}/system_prompt.md
```

### フォーマット要件

- **エンコーディング**：UTF-8
- **フォーマット**：プレーン Markdown テキストファイル
- **内容**：キャラクターの完全な System Prompt。Markdown フォーマットを使用して可読性を高めることができます。

### Markdown フォーマット推奨

Markdown を使用すると、System Prompt をより構造化して読みやすくできます：

```markdown
# キャラクター定義

あなたは「キャラクター名」です。以下のルールを遵守してください。

## 対話プロトコル

1. **応答長**：必ず40文字以内で収まること。
2. **一人称**：必ず「私」を使用すること。
3. **口調**：常体のみ使用。

## 背景設定

- キャラクター背景説明
- 性格特徴
- 話し方

## 行動ルール

- ルール1
- ルール2
```

### 変数サポート

System Prompt は以下の変数置換をサポートしています：

- `{{ukagaka_display_name}}`：キャラクター名
- `{{language}}`：応答言語（zh-TW、ja、en）
- `{{time_context}}`：時間コンテキスト（例：「1月2日（木曜日）・冬の朝」）
- `{{wp_version}}`：WordPress バージョン
- `{{php_version}}`：PHP バージョン
- `{{theme_name}}`：テーマ名
- `{{theme_version}}`：テーマバージョン
- `{{theme_author}}`：テーマ作者
- `{{post_count}}`：投稿数
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

完全な Markdown フォーマットの例については、`example/system-prompt-markdown-example.md` を参照してください。

---

## prompts.json フォーマット説明（LLM モード）

`prompts.json` は、LLM が対話を生成する際に使用するプロンプトカテゴリを定義します。各カテゴリには複数のプロンプトテンプレートが含まれており、システムは重みに基づいてランダムに選択します。

### ファイル構造

```json
{
  "_comment": "Character Name - Prompt Categories",
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

### カテゴリ命名推奨

- `greeting`：挨拶系
- `casual`：雑談系
- `observation`：観察系
- `memory`：思い出系
- `time_aware`：時間認識系
- `magic_collection`：魔法収集系（またはキャラクター固有の趣味）
- `self_awareness`：自己認識系
- `emotional_density`：感情密度系
- など...

### 変数プレースホルダー

プロンプト内で変数プレースホルダーを使用でき、システムによって自動的に置換されます：

- `{time_context}`：時間コンテキスト
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

`weights.json` は、各対話カテゴリの重みを定義します。重みが高いほど、そのカテゴリが選択される確率が高くなります。

### ファイル構造

```json
{
  "_comment": "Character Name - Category Weights Configuration",
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

すべての時間帯で使用される基本重み。推奨範囲：1-20。

- 値が高い = 選択される確率が高い
- よく使うカテゴリは 10-15 推奨
- あまり使わないカテゴリは 1-5 推奨

### time_adjustments

時間帯に基づいて重みを調整し、`base_weights` とマージされます。

**サポートされている時間帯（キーは内部ロジックに対応）：**

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

`decorations.json` は、キャラクターのデコレーション（クリック可能なインタラクティブ要素）を定義します。

### ファイル構造

```json
{
  "_comment": "Character Name - Decoration Click Prompts",
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
      "prompt": "ユーザーがこのデコレーションをクリックしたときのプロンプト（50文字以内）"
    }
  ]
}
```

### フィールド説明

- `type`：デコレーションタイプ（ユニーク識別子）
- `image`：画像ファイル名（`decorations/` フォルダに保存）
- `position`：CSS 配置（`top`、`left`、`right`）
- `size`：画像サイズ（`width`、`height`）
- `transform`：CSS transform（オプション）
- `z_index`：レイヤー順序
- `prompt`：クリック時の LLM プロンプト

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

## シェル画像ファイル

シェル画像はキャラクターの視覚表現であり、`shell/{人格ID}/` フォルダに格納されます。

### 必須ファイル

- **`{人格ID}.png`**：メイン画像（必須）

### オプションファイル（アニメーション）

- `{人格ID}[0].png`、`{人格ID}[1].png`、...：アニメーションフレーム
- `{人格ID}[s].png`：特殊状態画像
- `{人格ID}[w1].png`、`{人格ID}[w2].png`、...：ウェイクアップアニメーションフレーム

### 命名規則

1. **メイン画像**：`{人格ID}.png`（例：`Frieren.png`）
2. **アニメーションフレーム**：`{人格ID}[数字].png`（例：`Frieren[0].png`、`Frieren[1].png`）
3. **特殊状態**：`{人格ID}[文字].png`（例：`Frieren[s].png`）

### 画像フォーマット

- **フォーマット**：PNG（推奨）または JPG
- **サイズ**：幅 200-400px 推奨、高さは自由
- **背景**：透明背景推奨（PNG）

### ファイル構造例

```
shell/
└── Frieren/
    ├── Frieren.png        # メイン画像（必須）
    ├── Frieren[0].png     # アニメーションフレーム 0
    ├── Frieren[1].png     # アニメーションフレーム 1
    ├── Frieren[2].png     # アニメーションフレーム 2
    ├── Frieren[s].png     # 特殊状態
    ├── Frieren[w1].png    # ウェイクアップアニメーション 1
    ├── Frieren[w2].png    # ウェイクアップアニメーション 2
    └── ...
```

---

## JavaScript スクリプト（オプション）

カスタムアニメーションやインタラクション動作が必要な場合は、JavaScript スクリプトを作成できます。

### ファイル場所

```
ghost/{人格ID}/{人格ID}.js
```

### manifest.json での指定

```json
{
  "id": "MyCharacter",
  "script": "mycharacter.js"
}
```

### 基本構造

JavaScript スクリプトは `window.mpuFrierenManager`（または同様のキャラクターマネージャー）に登録する必要があります。完全な例については `ghost/Frieren/frieren.js` を参照してください。

---

## アップロードと使用

### 方法1：ZIP アップロード（推奨）

1. すべての人格ファイルを ZIP ファイルに圧縮します。
2. WordPress 管理画面にログイン → **設定** → **MP Ukagaka** → **新規偽春菜作成**
3. ZIP ファイルを選択してアップロードします。
4. システムが自動的に解凍して検証します。
5. プレビュー情報が正しいことを確認後、「確認して作成」をクリックします。

### 方法2：手動アップロード

1. FTP またはファイルマネージャーを使用して、人格フォルダを `wp-content/plugins/mp-ukagaka/ghost/` にアップロードします。
2. WordPress 管理画面にログイン → **設定** → **MP Ukagaka** → **偽春菜たち**
3. 新しいキャラクター設定を手動で追加します。

### ZIP ファイル構造要件

ZIP ファイルを解凍すると、直下に `manifest.json` と `shell/` フォルダが含まれている必要があります：

```
MyCharacter.zip
└── (解凍後)
    ├── manifest.json
    ├── system_prompt.md
    ├── shell/
    │   └── MyCharacter/
    │       └── MyCharacter.png
    ├── prompts.json
    └── weights.json
```

**注意**：ZIP ファイルにはトップレベルフォルダ名（例：`MyCharacter/manifest.json`）を含めず、ファイル自体を含める必要があります。

---

## 完全な例

以下は最小限のキャラクター例です：

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
  "name": "シンプルキャラクター",
  "shell_folder": "SimpleCharacter"
}
```

### 3. system_prompt.md（オプション、推奨）

```markdown
# キャラクター定義

あなたは「シンプルキャラクター」です。訪問者とは簡潔でフレンドリーな口調で対話してください。

## 対話ルール

- 応答は50文字以内に収めること
- 常体を使用すること（敬語なし）
- 一人称は「私」を使用すること
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

新しい人格を作成する基本ステップ：

1. ✅ `manifest.json` を作成する（必須）
2. ✅ `shell/{人格ID}/{人格ID}.png` を準備する（必須）
3. ⭐ `system_prompt.md` を作成する（推奨、キャラクター行動定義用）
4. 📝 `prompts.json` と `weights.json` を作成する（LLM モード用）
5. 🎨 `decorations.json` とデコレーション画像を追加する（オプション）
6. 📦 ZIP に圧縮してアップロードする

**参考例**：`ghost/Frieren/` フォルダを確認して、完全な人格構造を理解してください。

---

**最終更新**：2026-01-15
