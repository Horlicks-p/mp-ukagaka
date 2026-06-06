# ギフト／給食システム 設計プラン（送禮物・給食機能）

> 📅 初稿：2026-06-06
> 📅 改訂：2026-06-06（家 CODEX レビュー 6 点を本文に反映）
> 📋 訪客がキャラクターに「物を差し出す（ギフト／食事）」インタラクションの設計
> 🎯 想定読者：実装担当
> 🔖 対象バージョン基点：v2.25.1

---

## ⚠️ ステータス

- **状態**：**設計のみ（as-design）／未施作（Not implemented）**
- このドキュメントは方向性と実装手順を固めるための設計書。コードはまだ書いていない。
- 施作開始時は `feature/gift-system` ブランチを切ってから進める。

---

## 0. 決定事項（着手前に確定済み）

| 論点 | 決定 | 理由 |
|---|---|---|
| ギフトと給食の構造 | **統合：単一 `items.json` + `kind` フィールド** | gift/food は「物を差し出す→反応→記録」という同一メカニクス。1 catalog・1 endpoint・1 pipeline で重複を最小化し、差は `kind` と反応 prompt・observation 描写だけで出し分ける。 |
| 初回スコープ | **MVP（無状態の反応のみ）** | 既存 decoration 経路とほぼ同型。好感度・飽食度などの永続状態は持たず、まず「差し出す→反応→observation/chat 履歴に記録」までを出して挙動を確認する。 |
| Observation type 名 | **`item`（`gift` ではない）** | food は gift ではなく、将来 medicine / book / tool も来うる。総称 `item` 型に統一し、`kind` プレフィクスで `food:` / `gift:` を出し分ける（家 CODEX #1）。 |
| Catalog 供給 | **`wp_localize_script` で渡す** | 新 GET endpoint を生やさず、catalog を frieren.js に硬編みもしない。ghost-agnostic とキャッシュが綺麗（家 CODEX #2）。 |
| アイテム画像 | **MVP は text-only**（`image` 欄は保持） | 第一版で素材・サイズ・欠図 fallback を抱えないため画像は後回し。まず「差し出す→反応→記録」を安定させる（家 CODEX #3）。 |

> #### 家 CODEX レビュー反映（2026-06-06・第1巡）
> 方向性（統合 items + kind / MVP 無状態 / `MPU_REST_Touch` 拡張）は承認。実装前の収斂点 6 件を本文へ反映済み：
> (1) observation type を `gift`→`item` に総称化、(2) catalog は `wp_localize_script`、(3) MVP は text-only、
> (4) Step 0 helper は**真の重複のみ**抽出し prompt 組立は caller に残す、(5) normalizer context は `give`／inner monologue default は false、(6) item prompt/name にも可視 emotion tag 混入を禁じるテストを追加。
>
> #### レビュー反映（2026-06-06・第2巡）
> 実装直前の細部 5 件を反映（R2）：
> (R2-1) observation `dedupe_key()` に `item` 判定を追加し、同一道具の連投で 5 枠バッファを溢れさせない（**最重要**）、
> (R2-2) 前端 synthetic history anchor を `（{name}を差し出した）` と**動的組立**し追問時の文脈を保持、
> (R2-3) `run_reaction()` は失敗時 `WP_Error` を返し caller は `is_wp_error()` で即 return、
> (R2-4) rate limit key を `give_item` に**独立**（touch/decoration と共用しない）、
> (R2-5) MVP UI 入口は `#ukagaka_msgbox` 入力欄付近の小ボタン（Canvas 描画域に干渉しない）。

---

## 1. 背景・核心洞察

送禮物／給食は、構造的に既存の **「裝飾物クリック反応（`/touch/decoration`）」と同じ形**である：

```
訪客が何かを差し出す → キャラが LLM で反応 → observation buffer と chat history に記録
```

したがって新規モジュールを作るのではなく、`MPU_REST_Touch` コントローラと
`MPU_Observation_Buffer` を**拡張**する。これは CLAUDE.md の方針
「Extend existing functions before adding new ones」「Don't use procedural REST handlers」
にも合致する。

### 既存パイプライン（`/touch/decoration`）の流れ

```
frieren.js（クリック描画）
  → POST /touch/decoration {decoration_type}
  → mpu_get_decoration_prompt()            … decorations.json から prompt 解決
  → 反応ルール付与 → mpu_call_ai_api()
  → mpu_normalize_ai_response_for_rest()   … emotion tag→APNG 抽出（v2.25.x の成果）
  → mpu_record_conversation() + mpu_observation_push_touch()
  → return {msg, emoji}
```

ギフト／給食はこの「prompt の出どころ」と「記録の意味」を差し替えるだけで実現できる。

---

## 2. 既存資産の再利用マッピング

| 必要な機能 | 既存資産 | 再利用方針 |
|---|---|---|
| アイテム catalog ローダ | `personality-decorations.php`（`mpu_load_personality_decorations` 他） | 同型の `personality-items.php` を新設（コピー＆改名レベル） |
| REST エンドポイント | `MPU_REST_Touch::decoration_chat()` | 同コントローラに `give_item()` メソッド＋route 追加 |
| AI 呼び出し定型 | `decoration_chat()` / `touch_zone_chat()` に**重複**している呼び出し〜normalize〜display limit | **先に private helper へ抽出**してから 3 か所で共有（Step 0） |
| emotion tag → APNG | `mpu_normalize_ai_response_for_rest()` | そのまま通すだけ。prompt 側で `[laugh]`/`[love]`/`[sigh]` を誘導すれば表情が自動で出る（**追加実装ゼロ**） |
| セッション記憶 | `MPU_Observation_Buffer` + `mpu_observation_push_touch()` | `gift` type を追加し `mpu_observation_push_gift()` を新設 |
| フロント描画・反応表示 | `ghost/Frieren/frieren.js` の decoration クリック処理 | ギフトメニュー UI として流用（反応表示・emoji・chat history push は既存コードが使える） |

---

## 3. アーキテクチャ（レイヤ別設計）

### ① Config（ghost ごと・ghost-agnostic）

`ghost/<Character>/items.json` を新設（`decorations.json` のミラー構造）。
**gift も food も 1 ファイルで扱う**。

```json
{
  "_comment": "<Character> Personality - Gift / Feeding Item Prompts",
  "_format_version": "1.0",
  "items_base_folder": "items",
  "items": [
    {
      "id": "mapo_tofu",
      "kind": "food",
      "name": "麻婆豆腐",
      "image": "mapo_tofu.png",
      "favorite": true,
      "prompt": "相手があなたに麻婆豆腐を差し出した。フリーレンとして、好物として喜びながら食べる反応を…"
    },
    {
      "id": "flower",
      "kind": "gift",
      "name": "花",
      "image": "flower.png",
      "favorite": false,
      "prompt": "相手が花をくれた。フリーレンとして、受け取った反応を…"
    }
  ]
}
```

- `kind`：`"food"` | `"gift"`（ホワイトリスト。将来 `medicine` / `book` / `tool` 等を足せる総称設計）
- `favorite`：true なら「特別に喜ぶ」反応ルールを追加付与
- `id`：`[a-z_][a-z0-9_]*`（observation の normalize 正規表現と一致させる）
- `image`：欄は持つが **MVP では未使用（UI は text-only）**。Phase 3 で素材・サイズ・欠図 fallback を設計してから使う。
- **ghost-agnostic 厳守**：アイテム種別・名前をコアにハードコードしない。catalog は各 ghost の json が持つ。

### ② Loader

`includes/personality/personality-items.php`（`personality-decorations.php` と同型）：

```php
mpu_load_personality_items($personality_id = null): array
mpu_get_personality_item($id, $personality_id = null): array|false  // {id,kind,name,image,favorite,prompt}
mpu_get_personality_item_ids($personality_id = null): array
```

- `mp-ukagaka.php` の load order に追加：personality-decorations の直後（人格系ブロック内）。

### ③ REST（既存コントローラを拡張・新規コントローラは作らない）

`includes/rest/class-mpu-rest-touch.php` に追加：

- route：`POST /mp-ukagaka/v1/touch/give`
  - rate limit 20 / 60s。**key は独立して `'give_item'`**（R2-4）。decoration（`'decoration_chat'`）/
    touch zone（`'touch_zone_chat'`）と共用しない。給食が上限に達しても通常の触摸・装飾クリックを 429 で巻き込まない。
- メソッド：`give_item(WP_REST_Request $request)`
  1. `ai_enabled` チェック → `rate_limit('give_item', 20, 60)`
  2. `item_id` 受領・sanitize → `mpu_get_personality_item()` で解決（不明なら 400）
  3. `kind` 別に反応ルールを組み立て：
     - `food`：「食べる／味の感想を述べる」
     - `gift`：「受け取る／お礼を述べる」
     - `favorite=true`：上記に「特別に喜ぶ」を追加
     - 末尾に既存と同じ `【回応ルール】淡々とした常体で、30-150文字で…直接反応…第三者視点の描写は禁止。`
  4. `$normalized = $this->run_reaction($user_prompt, $personality_id, 'give')` でAI呼び出し〜normalize〜display limit。
     **prompt 組立（手順 3）は caller 側に残し、helper には渡さない**（家 CODEX #4）。
     normalize 時の `context` は **`'give'`**（`'decoration'` を流用しない／家 CODEX #5）。
     inner monologue context default は **false**（decoration と同様）。gift/food 反応で LLM think bubble を出さない。
     **`run_reaction()` は失敗時 `WP_Error` を返す契約（R2-3）**。caller は直後に
     `if (is_wp_error($normalized)) { return $normalized; }` だけ。WP REST 框架が自動でエラー応答に包む。
  5. `mpu_record_conversation('give')`
  6. `mpu_observation_push_item($request, $kind, $id)`
  7. `return $this->ok( mpu_normalize_ai_response_rest_fields($normalized) + ['item_id'=>$id, 'kind'=>$kind] )`

### ④ Observation buffer（小さな拡張）

`includes/core/class-mpu-observation-buffer.php`：

- `VALID_TYPES` に `'item'` を追加（`gift` ではなく総称 `item`／家 CODEX #1）
- `normalize_content('item', …)`：`food:<id>` / `gift:<id>` 形式を許可
  （`/\A(food|gift):([a-z_][a-z0-9_]*)\z/u`。将来 `medicine|book|tool` を足せるよう kind を拡張可能に）
- `format_observation_description('item', …)`：**content 先頭の kind で分岐**
  - `food:<id>` → 「〇〇を貰って食べた」
  - `gift:<id>` → 「〇〇を貰った」
  - item 名は items.json から **name-first lookup**（decoration の `get_decoration_display_name()` と同じ作法で `get_item_display_name()` を追加）
- **`dedupe_key('item', …)` を必ず追加（R2-1・最重要）**：buffer の唯一の溢れ防止機構は dedupe。
  同一 session で同じ道具を連続で差し出すと、dedupe が無いと `MAX_ENTRIES = 5` を埋め尽くし、
  page_view / touch 等の重要イベントが押し出される。同一道具は最新 1 件だけ残すよう keying する：
  ```php
  // MPU_Observation_Buffer::dedupe_key() に追加
  if ($type === 'item' && preg_match('/\A(food|gift):([a-z_][a-z0-9_]*)\z/u', $content, $matches)) {
      return 'item:' . $matches[1] . ':' . $matches[2]; // 例: item:food:mapo_tofu
  }
  ```
  （`touch` が `touch:<part>`、`page_view` が `page_view:<post_id>` で dedupe しているのと同じ作法。
  kind+id で鍵を作るので「麻婆豆腐×3＝最新1件」「麻婆豆腐＋花＝2件」になる。）
  ⚠️ **鍵は必ず `kind + id` 粒度（`item:food:mapo_tofu`）で、`id` 単独にしない**。将来 food と gift が
  たまたま同じ `id` を持った場合に、別物の記録が互いに上書きされる事故を防ぐ。
- `mpu_observation_push_item(WP_REST_Request $request, string $kind, string $id): void`
  （session token 取得 → `item` type で `"{$kind}:{$id}"` を push。push 側は `MPU_Observation_Buffer::push()` が
  内部で `dedupe_entries()` を呼ぶので、上記 `dedupe_key` さえ足せば自動で効く。）

> ⚠️ `touch` type を `gift_` プレフィクスで流用する案もあるが、描写が「N回触れた」になり
> 意味がズレるため**専用 `item` type を採用**する。`gift` ではなく `item` にするのは、food が gift ではなく、
> 将来 medicine / book / tool も同じ型で扱えるため（家 CODEX #1）。Phase 2 で好感度・飽食度に使うときも
> kind プレフィクスで集計しやすい。

### ⑤ Frontend

- **Catalog 供給は `wp_localize_script`**（家 CODEX #2）：サーバ側で `mpu_load_personality_items()` を読み、
  表示用の最小フィールド（`id` / `kind` / `name` / `favorite`、画像は MVP 不要）だけを localize で渡す。
  新 GET endpoint を生やさず、catalog を frieren.js に硬編みもしない。
- **UI 入口（R2-5）**：MVP はテキストメニュー。入口は **`#ukagaka_msgbox` の入力欄付近に小ボタン**を置き、
  クリックで道具テキスト一覧をドロップ表示。「キャラと会話して物を渡す」という動線に最も直感的で、
  Frieren の **Canvas 描画域に干渉しない**。
- `ghost/Frieren/frieren.js`：decoration 描画ロジックを流用して「ギフトメニュー」UI を追加
  - ボタン → item リスト表示（localize 済み catalog を描画、**MVP は text ラベルのみ・画像なし**）→ クリックで
    `mpuFetch(mpuRestUrl + "touch/give", { body: {item_id} })`
  - 反応表示・`mpuEmojiManager.showEmoji()`・`window.mpuChatHistory.push()`（synthetic user anchor + assistant）
    は decoration 経路の既存コードを再利用
  - **synthetic user anchor は動的組立（R2-2）**：decoration は固定文字列 `（装飾品に触れた）` を push しているが、
    give では **localize の `name` で動的に**組み立てる：
    ```js
    window.mpuChatHistory.push({
      role: "user",
      content: `（${item.name}を差し出した）`, // 例：（麻婆豆腐を差し出した）
      type: "synthetic",
      timestamp: Date.now(),
    });
    ```
    これで後続チャットの「さっきの、美味しかった？」に対し LLM が**何を渡したか**の語意文脈を保持できる。
    固定文字列だと「何を渡したか」が履歴から消え、記憶連結が切れる。
  - 多重実行ガードは decoration の `decorationChatInProgress` と同型（`giveItemInProgress`）

### ⑥ 演出

- emotion tag パイプラインが既にあるため、prompt 側で `[laugh]`/`[love]`（好物）・`[sigh]`（苦手）を
  誘導すれば APNG 表情が自動表示される。**追加実装は不要**。

---

## 4. データ契約まとめ

| 層 | キー / 形式 | 制約 |
|---|---|---|
| items.json `id` | `[a-z_][a-z0-9_]*` | observation normalize と一致 |
| items.json `kind` | `food` \| `gift` | ホワイトリスト、不一致は読み飛ばし（将来 medicine/book/tool 拡張可） |
| catalog → 前端 | `wp_localize_script`（`id`/`kind`/`name`/`favorite`） | 画像は MVP 渡さない |
| REST 入力 | `item_id`（POST body） | sanitize_text_field、空 or 未知は 400 |
| REST 出力 | `{msg, emoji, display_text…, item_id, kind}` | 既存 normalize fields ＋ 2 フィールド |
| observation type | `item` | `gift` ではなく総称 |
| observation content | `food:<id>` / `gift:<id>` | kind プレフィクス付き、MAX_CONTENT_BYTES=200 以内 |
| normalizer context | `give` | inner monologue default = false |

---

## 5. 実装ステップ（順序）

- **Step 0 — リファクタ（前提）**：`MPU_REST_Touch` の `decoration_chat()` と `touch_zone_chat()` に
  重複する**真の共通部分のみ**を private helper `run_reaction($user_prompt, $personality_id, $context)` に抽出：
  - provider / api_key 準備
  - `mpu_call_ai_api`
  - `mpu_normalize_ai_response_for_rest`
  - display limit
  既存 2 メソッドも置換（**挙動・レスポンス構造は不変**）。→ give 追加が薄くなる。
  ⚠️ **prompt 組立（カテゴリ抽選・反応ルール文）は helper に入れず各 caller に残す**。
  入れると decoration/touch/give の分岐が helper に集まり巨大化する（家 CODEX #4）。
  ⚠️ **エラー契約（R2-3）**：API Key 未設定・timeout・provider が `WP_Error` を返した等の失敗時、
  `run_reaction()` は `WP_Error` をそのまま返す。caller は `is_wp_error()` で 1 行 return するだけにして、
  3 メソッドのエラーハンドリングを統一・最小化する。
- **Step 1 — Config**：`ghost/Frieren/items.json` 新設＋ Frieren 初期カタログ（麻婆豆腐 food/favorite、
  甘い物 food、花 gift、本 gift など）。
- **Step 2 — Loader**：`personality-items.php` 新設＋ load order 追加。
- **Step 3 — REST**：`give_item()` ＋ route `/touch/give`。
- **Step 4 — Observation**：`item` type ＋ normalize ＋ format（kind 分岐）＋ **`dedupe_key` の `item` 判定（R2-1・必須）**
  ＋ `mpu_observation_push_item()` ＋ `get_item_display_name()`。
- **Step 5 — Frontend**：catalog を `wp_localize_script` で供給 ＋ frieren.js にギフトメニュー UI（text-only）＋
  `/touch/give` 呼び出し。
- **Step 6 — 仕上げ**：dist 再ビルド（`node tools/node/build.js`）、PHPUnit（items.json の kind 妥当性・
  prompt 非空・可視タグ無し）、CHANGELOG / README（実装完了時）。

---

## 6. ファイル変更一覧（予定）

| 種別 | パス |
|---|---|
| 新規 | `ghost/Frieren/items.json` |
| 新規 | `includes/personality/personality-items.php` |
| 拡張 | `includes/rest/class-mpu-rest-touch.php`（Step 0 リファクタ＋ `give_item`） |
| 拡張 | `includes/core/class-mpu-observation-buffer.php`（`item` type） |
| 拡張 | `ghost/Frieren/frieren.js`（ギフトメニュー・text-only） |
| 拡張 | catalog の `wp_localize_script` 供給（enqueue 箇所。`frontend-functions.php` 付近） |
| 1 行 | `mp-ukagaka.php`（personality-items の load order） |
| 再ビルド | `js/dist/ukagaka-bundle.js` / `.min.js`（core 変更時） |
| 後回し | `ghost/Frieren/items/*.png`（Phase 3 で画像 UI 化する時） |

> 差分規模感：新規 2 ファイル＋既存 3 ファイル拡張＋ load order 1 行。Step 0 のおかげで REST 追加は薄い。

---

## 7. テスト方針

- `tests/Unit/` に items.json のスキーマ的検証（`EmotionTagPromptTest` の流儀）：
  - `kind ∈ {food, gift}`、`prompt` 非空、`id` が正規表現に合致、metadata に可視 emotion tag が混ざっていない
  - **可視 tag 防線（家 CODEX #6）**：各 item の `prompt` と `name`（および example 類）に `[thinking]`/`[laugh]`/`[sigh]`
    のような**可視 emotion tag が含まれていない**ことを assert。v2.25.1 で直したばかりの回帰をここで防ぐ。
- observation の `item` normalize / format をユニットで（`food:mapo_tofu` → 「麻婆豆腐を貰って食べた」、
  `gift:flower` → 「花を貰った」。kind 分岐の両方を確認）
- **dedup ユニット（R2-1）**：同じ `food:mapo_tofu` を 3 回 push → buffer に 1 件のみ（最新）。
  `food:mapo_tofu` ＋ `gift:flower` → 2 件。さらに touch / page_view と混在させ、連投で他イベントが
  押し出されないこと（5 枠を道具で埋めない）を確認。
- 手動：前台で給食 → 反応・APNG 表情 → その後チャットで「さっきの〇〇」に言及できるか（記憶連結の確認）

---

## 8. Phase 2 / 3 の展望（今回スコープ外）

- **Phase 2（状態あり）**：session-token スコープの transient で「好感度／飽食度」を蓄積
  （observation buffer と同じ仕組み）。好感度で反応が変化、満腹時は「もう食べられない」等。
  匿名訪客が主なのでサーバ永続化は session 単位が現実的。状態管理・上限・リセット・チート対策の設計が増える。
- **Phase 3（演出強化）**：ドラッグ&ドロップで口元に運ぶ、食べるアニメ、ギフト箱を開ける演出。

---

## 9. 注意点・リスク

- **ghost-agnostic 厳守**：item 種別・名前をコアにハードコードしない（observation buffer の既存コメントが明示）。
- **rate limit / nonce**：既存 touch と同じ 20/60s と `MPU_REST_Base` の作法を踏襲。
- **abilities ではない**：ギフトは訪客起点の UI 操作なので REST 層。LLM ツール（abilities）には載せない。
- **emotion tag 表示**：v2.25.1 で typewriter 表示前に tag を strip する処理が入っているため、
  反応文に tag を入れても可視テキストには漏れない（表情選択は別フィールド経由）。
- **dist 再ビルド忘れ**：frieren.js はバンドル対象外だが、core を触ったらビルドが要る。
- **observation バッファ溢れ（R2-1）**：`MAX_ENTRIES = 5` の唯一の溢れ防止は `dedupe_key`。`item` の dedupe を
  入れ忘れると、同一道具の連投で page_view / touch 等が押し出され「最近の活動」記憶が壊れる。Step 4 の必須項目。
- **synthetic anchor の文脈欠落（R2-2）**：固定文字列で push すると追問時に「何を渡したか」が履歴から消える。
  必ず `name` で動的組立する。

---

### ✅ 確定済み（レビューで決着）

- **catalog 供給** → `wp_localize_script`（#2）。
- **アイテム画像** → MVP は text-only、`image` 欄は保持（#3）。
- **observation type 名** → `item`／`dedupe_key` 必須（#1・R2-1）。
- **normalizer context** → `give`、inner monologue default = false（#5）。
- **エラー契約** → `run_reaction()` は `WP_Error` を返し caller は `is_wp_error()` 即 return（R2-3）。
- **rate limit key** → `give_item` で独立（R2-4）。
- **synthetic anchor** → `（{name}を差し出した）` で動的組立（R2-2）。
- **UI 入口** → `#ukagaka_msgbox` 入力欄付近の小ボタン → テキスト一覧。Canvas 域に干渉しない（R2-5）。

### ⏳ 実装時に確定（残）

1. **エンドポイント名**：`/touch/give` か `/interact/give` か。touch 名前空間に同居させる方が既存と一貫。
   （observation type は総称 `item` だが、endpoint は MVP の体験名「give」で据える想定。）
2. **localize ハンドル**：catalog をどの enqueue 済みスクリプトにぶら下げるか（既存 frontend の localize 箇所に相乗り）。
3. **メニュー展開の見た目**：小ボタン押下後のドロップ表示スタイル（位置・最大件数・スクロール）。Phase 3 の D&D／画像化と整合する形が望ましい。
