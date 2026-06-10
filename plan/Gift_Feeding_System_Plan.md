# ギフト／給食システム 設計プラン（送禮物・給食機能）

> 📅 初稿：2026-06-06
> 📅 改訂：2026-06-06（家 CODEX レビュー 6 点を本文に反映）
> 📅 改訂：2026-06-09（御三家＋Gemini レビュー反映後、家 CLAUDE/CODEX が実コード突合せ → 文書 3 点修正 F-1〜F-3）
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
> (R2-2) synthetic history anchor で追問時の文脈を保持（**G-2 で改訂**：前端動的組立ではなく backend が localized anchor を生成・返却し、前端は `res.user_anchor` をそのまま push）、
> (R2-3) `run_reaction()` は失敗時 `WP_Error` を返し caller は `is_wp_error()` で即 return、
> (R2-4) rate limit key を `give_item` に**独立**（touch/decoration と共用しない）、
> (R2-5) MVP UI 入口は `#ukagaka_msgbox` 入力欄付近の小ボタン（Canvas 描画域に干渉しない）。
>
> #### 会社 CLAUDE レビュー（2026-06-09・必修2件＋要確認2件）
> 方向性（統合 items+kind / MVP 無状態 / `MPU_REST_Touch`・`MPU_Observation_Buffer` 拡張）は承認。家 CODEX が縦の3層（REST / observation / frontend）を固めた一方、**3層を横断する「chat history 整合性」**が抜けている。これは spam-event 第5経路・chat_context の checksum 補填と**同じクラス**の漏れ。着手前に C-A / C-B を本文へ反映必須：
> **(C-A) give 反応の history `type` が未定義＆ checksum allowlist 未追加（最重要）**：前端は反応を `mpuChatHistory` に push するが、後端 `class-mpu-rest-chat.php:606-607` の `$allowed_types` がこの history を **checksum と LLM context の両方**で濾過する（同 :619）。give の assistant `type` を `'give'` と定め allowlist に追加しないと、**本プランの成功基準「さっきの〇〇」記憶連結（§7）が壊れる**（LLM が送禮の会話を見られない）。
> **(C-B) give_item に backend checksum 書き込みが無い（§13.2）**：§3③ 手順1–7 は checksum を書かない。現状 `decoration_chat()`/`touch_zone_chat()` も書いておらず（`store_after_auto` は `chat_context:335`/`chat_greet:489` のみ）、checksum が observational だから 400 にならないだけ。§13.2 を掲げる以上、give は `chat_context` 同様 `store_after_auto` を呼んで初日から揃える（推奨）／または decoration 現状踏襲を明示しリスク章に記載する。decoration/touch 側の checksum 補填も backlog 化を提案。
> **(C-1) inner monologue default**：`'give'` を `MPU_INNER_MONOLOGUE_CONTEXT_DEFAULTS` に **明示**で `=> false` 追加（fallback 依存にしない。`decoration` は明示済み）。
> **(C-2) tag ⊆ supported テスト**：2.25.2 以降、supported 外 tag は display に残る（`strip_unknown=false`）。items.json prompt が誘導する `[tag]` は全て当該 ghost の `emoji.supported` 内であることを assert（§7 #6 の補強）。
>
> #### 会社 CODEX レビュー（2026-06-09・実装前の補強3件）
> CLAUDE の C-A / C-B は正しいが、現行コードに照らすと実装時にさらに 3 点を明文化する必要がある：
> **(D-1) `give` history type allowlist は2箇所に追加**：`class-mpu-rest-chat.php` のローカル `$allowed_types` だけでなく、`includes/chat/class-mpu-chat-history-service.php` の `MPU_Chat_History_Service::ALLOWED_MSG_TYPES` にも `'give'` を追加する。後者を忘れると `parse_history_from_request()` 経由の自動 checksum 書き込みで `give` が `chat` に降格し、type 契約が揺れる。
> **(D-2) backend checksum には synthetic user anchor も含める**：前端が `（{name}を差し出した）` を表示・保存するだけでは、`store_after_auto()` に渡す history に同じ user anchor が入る保証がない。信頼境界上は backend が items catalog の `name` から同一 anchor を組み立て、`store_after_auto()` 前に append してから assistant reply を追加するのが望ましい。
> **(D-3) `mpu_record_conversation('give')` を有効化**：`includes/stats/stats-collector.php` の `$valid_types` と初期 stats 構造に `'give'` を追加する。追加しないなら MVP は `touch` として記録するが、送禮／給食は touch と語意が異なるため `give` として独立記録する方針を推奨。
>
> #### 会社 Antigravity レビュー（2026-06-09・要調整1件＋確認事項2件）
> 方向性（CLAUDE の C-A / C-B および CODEX の D-1 / D-2 / D-3 を含む）について全面的に賛同・承認。その上で、backend の checksum 書き込み（`store_after_auto`）を正しく動作させ、かつ 409 checksum mismatch を防ぐために、実装に不可欠な設計のギャップをさらに 1 件補強（A-A）し、運用・テスト上の留意点を 2 件（A-1 / A-2）提案する：
> **(A-A) フロントエンドから `/touch/give` 送信時、`session_id` と `history` を明示的に渡す必要性（最重要）**：§3⑤（前端）の設計案では `body: {item_id}` のみを送信しているが、バックエンドで `store_after_auto` を正常に実行するには、`session_id` と現在の履歴 `history` が必須である。`mpuFetch` は `X-MPU-Session-Token` をヘッダーに自動注入するのみで、`session_id` や `history` は自動付与しない。そのため、フロントエンドは `mpu_getOrCreateChatSessionId()` から `session_id` を取得し、`window.mpuChatHistory.slice(-20)` と共に body パラメータとして POST 送信しなければならない。これを怠ると、バックエンドで `$session_id` が空となり checksum が保存されず、次回の `/chat/user` リクエスト時に 409 mismatch エラーが発生する。
> **(A-1) エンドポイント名および名前空間の確定**：⏳残1 の論点に対し、`/touch/give` の採用を確定とする。コントローラが `MPU_REST_Touch` であるため、`/touch/` 名前空間に配置することがルート登録の局所化および一貫性の観点から最も自然である。
> **(A-2) ObservationBufferTest へのテスト追加規定**：§7 のテスト方針を補強するため、`tests/Unit/ObservationBufferTest.php` に `item` タイプの `dedupe_key` 検証テスト（同一アイテムの連投で最新1件のみ残るか、別アイテムの混在で正しく共存できるか）を追加することを明文化する。
>
> #### 会社 CLAUDE 確認補足（2026-06-09・A-A の重大度校正）
> CODEX の D-1 / D-2 / D-3、Antigravity の A-A / A-1 / A-2 はコードに照らして全て妥当・採用。ただし A-A の表現を 1 点だけ校正する：
> **A-A の「次回 `/chat/user` で 409 mismatch エラーが発生する」は現状では不正確**。checksum 既定は `audit` モード（`chat-integrity.php:28`）で、mismatch は**ログ記録のみ・チャットは中断しない**（409 にもならない）。`session_id`/`history` 未送信時の**現状の実害は「checksum 未保存＝§13.2 漂移＋ mismatch ログ noise」**であり、ハードな WP_Error は `block` モードに切り替えた場合に限る。したがって A-A の対処（前端が `session_id`＋`history` を body 送信）は**必須**だが、根拠は「409 防止」ではなく「§13.2 整合と block モード移行時の前方互換」と理解すること。
>
> #### 会社 CLAUDE 裁決（2026-06-09・Gemini 提案への判定）
> Gemini から4点。G-2 を採用し D-2 の方向を改訂、G-3 採用、G-1 は Phase 2 へ降格、G-4 は A-1 への同調のみ：
> **(G-2 採用・D-2 を改訂) synthetic anchor は「backend 単一所有」にする（最重要）**：D-2 は前端と backend が各々 anchor を組む案だったが、checksum は type 含め全列照合のため両者が**言語まで含め逐字一致**しないと mismatch する（前端 hardcode 日本語＋backend が利用者言語生成＝必ず破綻）。よって anchor 文字列は **backend が解決済み言語で生成 → checksum に使用 → REST レスポンスにも同梱して返す**。前端はそれを**そのまま**表示・履歴 push する（前端で組まない）。これで i18n と前後端 checksum 対称を同時に満たす。§3⑤／§4／D-2 の「前端で動的組立」記述はこの方針で上書き。
> **(G-3 採用) 前端ロック解除は `finally` 必須**：`giveItemInProgress` は `try...finally`（または `.finally()`）で解除する。現行 decoration の `decorationChatInProgress` は成功路で setTimeout 解除のみ（`frieren.js:1281-1291`）で error/timeout 時にロック残留の懸念があるため、give では踏襲せず finally 化する（decoration 側の同様修正も backlog）。
> **(G-1 → Phase 2 降格) 異種アイテム flooding は MVP では非対応**：dedupe は同一アイテムの連投を防ぐが、異種5件で 5 枠が item で埋まる懸念は妥当。ただし (a) touch/page_view も同じ輪転特性を持ち item 固有ではない、(b) 送禮は意図的操作で「直近＝送禮5件」は正しい近況とも言える。彼の「彙総レコードへ merge」は MVP には過剰。対応するなら push() に **per-type 上限（item 全体 2–3 件）** が筋だが、好感度/飽食度が入る Phase 2 に回す。
> **(G-4) /touch/give**：A-1 への同調のみ。確定済み、追加対応なし。
>
> #### 家 CLAUDE / CODEX 文書整合（2026-06-09・実コード突合せ後の文書修正3点）
> 御三家＋Gemini のレビュー内容を全 file:line で現行コードと突合せ、全て正確と確認（特に G-2 採用は checksum 全列照合の性質上正しい）。
> その上で実装者が照表施工で踏みやすい文書上の穴を 3 点補修：
> **(F-1) §6 に `includes/llm/response-normalizer.php` を追加**：C-1 の `'give' => false` を入れる `MPU_INNER_MONOLOGUE_CONTEXT_DEFAULTS` は
> この実ファイル（冒頭 define、`'decoration' => false` の隣）に在る。§6 に無いと編集漏れする。所在注記も併記（姉妹 Emotion plan の `utility-functions.php` 記述は旧構成）。
> **(F-2) §3③ 手順2 の `history` 空判定を精緻化**：`session_id` 空は 400 で良いが、`history` は「送信され array に解析できる」ことのみ要求し、
> **解析後が空配列 `[]` でも 400 にしない**（初回インタラクションが送禮＝`history=[]` は正当。backend は anchor→reply を append すれば checksum を書ける）。
> **(F-3) dedupe wording 修正（§3④／§9）**：hard cap は `push()` 末尾の `array_slice($buf, -MAX_ENTRIES)`。dedupe は「同一道具の連投で 5 枠を食い潰すのを防ぐ」機構と表現を正す。
> 併せて D-3 に「機能必須は `$valid_types` 追加のみ、初期 stats 構造への追加は表示・一貫性整理」と注記（`:139-140` が未知キーを自動初期化するため）。

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
| セッション記憶 | `MPU_Observation_Buffer` + `mpu_observation_push_touch()` | 総称 `item` type を追加し `mpu_observation_push_item()` を新設（§3④・家 CODEX #1） |
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
  2. `item_id`、`session_id`、および `history` 受領・sanitize。`item_id` は `mpu_get_personality_item()` で解決（不明なら 400）。`session_id` と `history` は後続の checksum 保存に利用するため、`MPU_Chat_History_Service::get_session_id($request)` および `MPU_Chat_History_Service::parse_history_from_request($request)` にて解決。
     - **`session_id` が空なら 400**（checksum 保存に必須）。
     - **`history` は「送られていて array として解析できる」ことだけを要求し、解析後が空配列 `[]` でも 400 にしない**。初回インタラクションが送禮というケースは正当で、その時 `history = []` は合法。backend は空配列に対し synthetic user anchor → assistant reply を append すれば checksum を書ける（§3③ 手順 6.5）。`history` パラメータ自体が未送信／array に解析できない場合のみ 400。
     - ⚠️ **実装注意**：`MPU_Chat_History_Service::parse_history_from_request()` は未送信・空文字・JSON 不正・解析後空配列をすべて `[]` に畳むため、この判定には使えない。`$request->get_param('history')` の raw 値を先に検査し、「param が存在する」「JSON decode 後が array」の 2 点を確認してから、正規化済み history として `parse_history_from_request()` の戻り値を使う。
       - 「param が存在する」の判定は **`$request->has_param('history')`** を使う（`null !== $request->get_param('history')` ではない）。route args で `history` に default を設定すると未送信時も default 値が入り `get_param()` の null 判定では「真の未送信」と区別できなくなるため、`has_param()` で判定し、かつ **route 登録時に `history` へ default を与えない**こと。
  3. `kind` 別に反応ルールを組み立て：
     - `food`：「食べる／味の感想を述べる」
     - `gift`：「受け取る／お礼を述べる」
     - `favorite=true`：上記に「特別に喜ぶ」を追加
     - 末尾に既存と同じ `【回応ルール】淡々とした常体で、30-150文字で…直接反応…第三者視点の描写は禁止。`
  4. `$normalized = $this->run_reaction($user_prompt, $personality_id, 'give')` でAI呼び出し〜normalize〜display limit。
     **prompt 組立（手順 3）は caller 側に残し、helper には渡さない**（家 CODEX #4）。
     normalize 時の `context` は **`'give'`**（`'decoration'` を流用しない／家 CODEX #5）。
     inner monologue context default は **false**（decoration と同様）。gift/food 反応で LLM think bubble を出さない。
     **`run_reaction()` は失敗時 `WP_Error` を返す契約（R2-3）**。ただし既存 `decoration_chat()` / `touch_zone_chat()` の
     REST status と error code を変えないため、helper が返す `WP_Error` には status 400 を持たせる（または caller 側で
     既存どおり `$this->fail('rest_error', $error->get_error_message(), 400)` に包む）。caller は `is_wp_error()` 分岐だけにして、
     3 メソッドのエラーハンドリングを統一・最小化する。
  5. `mpu_record_conversation('give')`
     - **会社 CODEX D-3**：`includes/stats/stats-collector.php` の `$valid_types`（`:132`）に `'give'` を追加する。
       **機能上の必要条件はこの `$valid_types` 追加のみ**——未追加だと早期 return で計上されない。初期 stats 構造（`conversations` 配列・`:46-52`）への
       `'give' => 0` 追加は、`:139-140` が未知キーを自動で `0` 初期化するため**機能必須ではなく、表示・一覧の一貫性整理**として併せて行うのが望ましい。
       MVP で `touch` に寄せる案もあるが、送禮／給食は touch と語意が違うため `give` 独立を推奨。
  6. `mpu_observation_push_item($request, $kind, $id)`
  6.5. **backend checksum 書き込み（会社 CLAUDE C-B / 会社 CODEX D-2）**：`chat_context`/`chat_greet` と同様に
     `MPU_Chat_History_Service::store_after_auto($session_id, $history_with_anchor, $normalized['checksum_text'], 'give')` を呼び、
     前端 history と後端 checksum を揃える（§13.2）。省くと decoration と同じ audit 漂移を継承する。
     ここで渡す `$history_with_anchor` は、既存 request history に **backend 側で** synthetic user anchor を
     append したものにする（G-2）。anchor 文字列は **backend が解決済み言語で生成**（item name は catalog 由来の
     信頼済み値）し、**同一文字列を REST レスポンス `user_anchor` でも返す**。前端はその `res.user_anchor` を
     そのまま push するため、前後端 checksum が逐字一致し、前端が同じ文言を送ってくる前提にも依存しない。
  7. `return $this->ok( mpu_normalize_ai_response_rest_fields($normalized) + ['item_id'=>$id, 'kind'=>$kind] )`

  > **会社 CLAUDE C-A（history type / 最重要）**：give assistant の前端 history `type` を `'give'` に統一し、
  > **`class-mpu-rest-chat.php:606-607` の `$allowed_types` に `'give'` を追加**する。これを忘れると give 履歴が
  > checksum と LLM context の両方から脱落し、§7 の「記憶連結（さっきの〇〇）」が壊れる。normalize の `context` も
  > `'give'`（C-1：`MPU_INNER_MONOLOGUE_CONTEXT_DEFAULTS` に `'give' => false` を明示追加）。
  > **会社 CODEX D-1**：同時に **`includes/chat/class-mpu-chat-history-service.php` の
  > `MPU_Chat_History_Service::ALLOWED_MSG_TYPES` にも `'give'` を追加**する。`store_after_auto()` 用の
  > `parse_history_from_request()` はこの allowlist を見るため、片方だけの追加では type 契約が不完全になる。

### ④ Observation buffer（小さな拡張）

`includes/core/class-mpu-observation-buffer.php`：

- `VALID_TYPES` に `'item'` を追加（`gift` ではなく総称 `item`／家 CODEX #1）
- `normalize_content('item', …)`：`food:<id>` / `gift:<id>` 形式を許可
  （`/\A(food|gift):([a-z_][a-z0-9_]*)\z/u`。将来 `medicine|book|tool` を足せるよう kind を拡張可能に）
- `format_observation_description('item', …)`：**content 先頭の kind で分岐**
  - `food:<id>` → 「〇〇を貰って食べた」
  - `gift:<id>` → 「〇〇を貰った」
  - item 名は items.json から **name-first lookup**（decoration の `get_decoration_display_name()` と同じ作法で `get_item_display_name()` を追加）
- **`dedupe_key('item', …)` を必ず追加（R2-1・最重要）**：hard cap は `push()` 末尾の `array_slice($buf, -MAX_ENTRIES)`（5 件）。
  dedupe はその 5 枠を同一道具の連投で食い潰さないための機構。同一 session で同じ道具を連続で差し出すと、
  dedupe が無いと 5 枠を埋め尽くし、page_view / touch 等の重要イベントが押し出される。同一道具は最新 1 件だけ残すよう keying する：
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
  ⚠️ **`push_touch` を copy して作らない**：既存 `mpu_observation_push_touch()`（`class-mpu-observation-buffer.php:352`）は
  content を `part:N` の **回数カウント累加モード**で組み（描写も「N回触れた」）、`push_item` の狙う
  「同一道具は最新1件のみ」語意とは別物。`push_item` は count 累加を**持ち込まず**、`"{$kind}:{$id}"`（カウント無し）を
  素直に push し、収斂は `dedupe_key('item', …)` に任せる。loader（§2）と違い observation push は「コピー＆改名」してはいけない。

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
    `session_id` と `history` を含めて POST リクエスト（FormData 形式）：
    ```javascript
    const formData = new FormData();
    formData.append("item_id", item.id);
    const chatSessionId = typeof mpu_getOrCreateChatSessionId === "function" ? mpu_getOrCreateChatSessionId() : "";
    if (chatSessionId) {
        formData.append("session_id", chatSessionId);
    }
    formData.append("history", JSON.stringify(window.mpuChatHistory.slice(-20)));
    mpuFetch(mpuRestUrl + "touch/give", {
        method: "POST",
        body: formData
    })
    ```
  - 反応表示・`mpuEmojiManager.showEmoji()`・`window.mpuChatHistory.push()`（synthetic user anchor + assistant）
    は decoration 経路の既存コードを再利用
  - **synthetic user anchor は backend 所有（R2-2 を G-2 で改訂）**：anchor 文字列は backend が REST レスポンスで返す
    （例 `res.user_anchor`）。前端は**前端で組まず**それを**そのまま** push する（前端 hardcode と backend 生成の
    言語差で checksum が割れるのを防ぐ）：
    ```js
    window.mpuChatHistory.push({
      role: "user",
      content: res.user_anchor, // backend が解決済み言語で生成（checksum と同一文字列）
      type: "synthetic",
      timestamp: Date.now(),
    });
    window.mpuChatHistory.push({
      role: "assistant",
      content: res.msg,
      type: "give",
      timestamp: Date.now(),
    });
    ```
    これで「さっきの、美味しかった？」に対し LLM が**何を渡したか**の語意文脈を保持しつつ、前後端 checksum も逐字一致する。
    `res.msg` は現行 normalizer 契約上 `display_text` / `history_text` / `checksum_text` と同一なので、frontend history へ保存してよい。
  - 多重実行ガードは decoration の `decorationChatInProgress` と同型（`giveItemInProgress`）。
    **解除は `try...finally` / `.finally()` で必須（G-3）**：error/timeout 時の UI ロック残留を防ぐ。
  - ⚠️ **script dependency**：`frieren.js` は現行 enqueue で `$anime_handle` のみに依存しており、`mpu_getOrCreateChatSessionId()` を定義する
    `ukagaka-chat.js` / bundle への依存が明示されていない。give UI が `mpu_getOrCreateChatSessionId()` と `window.mpuChatHistory` を必須にするため、
    personality script の依存配列に `$chat_handle`（bundle 時は `mpu-bundle`）を含める、または give UI 初期化を chat runtime 準備後に遅延する。

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
| REST 入力 | `item_id`, `session_id`, `history`（POST body） | `item_id` は sanitize_text_field、空 or 未知は 400。`session_id` 空は 400。`history` は raw param が存在し JSON decode 後 array であること（空配列 `[]` は合法） |
| REST 出力 | `{msg, emoji, display_text…, item_id, kind, user_anchor}` | 既存 normalize fields ＋ item_id/kind ＋ backend 生成 localized anchor（G-2） |
| observation type | `item` | `gift` ではなく総称 |
| observation content | `food:<id>` / `gift:<id>` | kind プレフィクス付き、MAX_CONTENT_BYTES=200 以内 |
| normalizer context | `give` | inner monologue default = false（`MPU_INNER_MONOLOGUE_CONTEXT_DEFAULTS` に明示追加・C-1） |
| chat history type（give assistant） | `give` | **`class-mpu-rest-chat.php:606-607` の `$allowed_types` と `MPU_Chat_History_Service::ALLOWED_MSG_TYPES` の両方に追加必須**（C-A / D-1）。checksum＋LLM context 両方の濾過対象 |
| synthetic user anchor | backend 生成（`res.user_anchor`） | backend が解決済み言語で生成し checksum に使用＋レスポンスで返す。前端はそのまま表示・push（G-2 で D-2 の「前端で組立」を改訂） |
| backend checksum | `store_after_auto(..., 'give')` | 前端 history と揃える（§13.2・C-B）。`chat_context`/`chat_greet` と同型。事前に synthetic anchor を含める |
| conversation stats type | `give` | `stats-collector.php` の `$valid_types` と初期 stats 構造に追加（D-3）。追加しない場合は記録されない |

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
  `run_reaction()` は `WP_Error` を返す。既存 REST 挙動を維持するため、`WP_Error` に status 400 を入れるか、caller が
  `$this->fail('rest_error', $error->get_error_message(), 400)` に包む。caller 側の分岐は `is_wp_error()` のみに揃え、
  3 メソッドのエラーハンドリングを統一・最小化する。
- **Step 1 — Config**：`ghost/Frieren/items.json` 新設＋ Frieren 初期カタログ（麻婆豆腐 food/favorite、
  甘い物 food、花 gift、本 gift など）。
- **Step 2 — Loader**：`personality-items.php` 新設＋ load order 追加。
- **Step 3 — REST**：`give_item()` ＋ route `/touch/give`。
  ＋ **backend 側で synthetic anchor を含めた history を組み立てて `store_after_auto(..., 'give')` で checksum 書き込み（C-B / D-2）**
  ＋ **`class-mpu-rest-chat.php:606-607` の `$allowed_types` と `MPU_Chat_History_Service::ALLOWED_MSG_TYPES` の両方に `'give'` 追加（C-A / D-1）**
  ＋ `MPU_INNER_MONOLOGUE_CONTEXT_DEFAULTS` に `'give' => false`（C-1）
  ＋ `stats-collector.php` の conversation stats に `'give'` 追加（D-3）。
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
| 拡張 | `includes/rest/class-mpu-rest-chat.php`（history `$allowed_types` に `give`） |
| 拡張 | `includes/chat/class-mpu-chat-history-service.php`（`ALLOWED_MSG_TYPES` に `give`） |
| 拡張 | `includes/stats/stats-collector.php`（conversation stats type に `give`） |
| 拡張 | `includes/llm/response-normalizer.php`（`MPU_INNER_MONOLOGUE_CONTEXT_DEFAULTS` に `'give' => false`・C-1） |
| 拡張 | `includes/core/class-mpu-observation-buffer.php`（`item` type） |
| 拡張 | `ghost/Frieren/frieren.js`（ギフトメニュー・text-only） |
| 拡張 | catalog の `wp_localize_script` 供給（enqueue 箇所。`frontend-functions.php` 付近） |
| 拡張 | `includes/core/frontend-functions.php`（personality script dependency に chat runtime を含める） |
| 1 行 | `mp-ukagaka.php`（personality-items の load order） |
| 再ビルド | `js/dist/ukagaka-bundle.js` / `.min.js`（core 変更時） |
| 後回し | `ghost/Frieren/items/*.png`（Phase 3 で画像 UI 化する時） |

> 差分規模感：新規 2 ファイル＋既存 6 ファイル拡張＋ load order 1 行。Step 0 のおかげで REST 追加は薄い。
>
> ⚠️ **`MPU_INNER_MONOLOGUE_CONTEXT_DEFAULTS` の所在は `includes/llm/response-normalizer.php`（define は同ファイル冒頭）**。
> 姉妹プラン `Emotion_Tag_And_Think_Block_Plan.md` は旧構成のまま `utility-functions.php` と記すが、現行では response-normalizer.php に移動済み。C-1 の `'give' => false` 追加はこちらを編集する。

---

## 7. テスト方針

- `tests/Unit/` に items.json のスキーマ的検証（`EmotionTagPromptTest` の流儀）：
  - `kind ∈ {food, gift}`、`prompt` 非空、`id` が正規表現に合致、metadata に可視 emotion tag が混ざっていない
  - **可視 tag 防線（家 CODEX #6）**：各 item の `prompt` と `name`（および example 類）に `[thinking]`/`[laugh]`/`[sigh]`
    のような**可視 emotion tag が含まれていない**ことを assert。v2.25.1 で直したばかりの回帰をここで防ぐ。
  - **tag ⊆ supported（会社 CLAUDE C-2）**：item `prompt` が誘導する `[tag]` は全て当該 ghost の `emoji.supported` 内である
    ことを assert。2.25.2 以降 supported 外 tag は `strip_unknown=false` で display に残るため、未サポート tag を誘導すると漏れる。
- observation の `item` normalize / format をユニットで（`food:mapo_tofu` → 「麻婆豆腐を貰って食べた」、
  `gift:flower` → 「花を貰った」。kind 分岐の両方を確認）
- **dedup ユニット（R2-1）**：`tests/Unit/ObservationBufferTest.php` にテストを追加。同じ `food:mapo_tofu` を 3 回 push → buffer に 1 件のみ（最新）。
  `food:mapo_tofu` ＋ `gift:flower` → 2 件。さらに touch / page_view と混在させ、連投で他イベントが
  押し出されないこと（5 枠を道具で埋めない）を確認。
- **history allowlist ユニット（D-1）**：`give` type が `class-mpu-rest-chat.php` 側の history sanitize と
  `MPU_Chat_History_Service::parse_history_from_request()` の両方で保持されることを確認。片方だけ追加してもテストが落ちる形にする。
- **checksum 整合ユニット（D-2 / G-2）**：`give_item()` 成功時、backend が catalog name から生成した localized
  synthetic user anchor（= レスポンス `user_anchor` と同一文字列）を含む history を構築し、その後ろに cleaned assistant
  reply（`checksum_text`）を `type=give` で追加して checksum 保存することを確認。レスポンス `user_anchor` と checksum
  に使った anchor が一致することも assert。
- **conversation stats ユニット（D-3）**：`mpu_record_conversation('give')` が有効な type として計上されることを確認。
- 手動：前台で給食 → 反応・APNG 表情 → その後チャットで「さっきの〇〇」に言及できるか（記憶連結の確認）

---

## 8. Phase 2 / 3 の展望（今回スコープ外）

- **Phase 2（状態あり）**：session-token スコープの transient で「好感度／飽食度」を蓄積
  （observation buffer と同じ仕組み）。好感度で反応が変化、満腹時は「もう食べられない」等。
  匿名訪客が主なのでサーバ永続化は session 単位が現実的。状態管理・上限・リセット・チート対策の設計が増える。
- **Phase 2（observation 整理・G-1）**：異種アイテム flooding 対策。同一アイテムの連投は dedupe で防げるが、
  異なる道具を5件以上差し出すと 5 枠が item で埋まり page_view 等が押し出される。`push()` に item の per-type 上限
  （全体 2–3 件）を入れる、もしくは閾値超で「色々な物を貰った」集約レコードへ merge する。MVP では非対応。
- **Phase 3（演出強化）**：ドラッグ&ドロップで口元に運ぶ、食べるアニメ、ギフト箱を開ける演出。

---

## 9. 注意点・リスク

- **ghost-agnostic 厳守**：item 種別・名前をコアにハードコードしない（observation buffer の既存コメントが明示）。
- **rate limit / nonce**：既存 touch と同じ 20/60s と `MPU_REST_Base` の作法を踏襲。
- **abilities ではない**：ギフトは訪客起点の UI 操作なので REST 層。LLM ツール（abilities）には載せない。
- **emotion tag 表示**：tag stripping は**後端 normalizer**で行う（v2.25.2 で前端 typewriter の strip は撤去・§13.2）。
  反応文に tag を入れても可視テキストには漏れない（表情選択は別フィールド経由）。ただし当該 ghost の `supported` 外 tag は
  display に残る（`strip_unknown=false`）ため、item prompt の tag は supported 内に限る（C-2 のテストで担保）。
- **dist 再ビルド忘れ**：frieren.js はバンドル対象外だが、core を触ったらビルドが要る。
- **observation バッファ溢れ（R2-1）**：hard cap 自体は `push()` 末尾の `array_slice($buf, -MAX_ENTRIES)`（=5 件）。
  `dedupe_key` は**同一道具の連投で 5 枠を食い潰すのを防ぐ機構**。`item` の dedupe を入れ忘れると、同一道具の連投で
  page_view / touch 等が押し出され「最近の活動」記憶が壊れる。Step 4 の必須項目。
- **synthetic anchor の文脈欠落（R2-2 / G-2）**：固定文字列で push すると追問時に「何を渡したか」が履歴から消える。
  anchor は backend が生成した localized `res.user_anchor` をそのまま push する（前端で組まない＝言語差で checksum が割れない）。
- **history type allowlist 漏れ（会社 CLAUDE C-A・最重要）**：give assistant の `type` を `class-mpu-rest-chat.php:606-607`
  の `$allowed_types` に追加し忘れると、give 履歴が checksum と LLM context の両方から濾過脱落し、§7 の記憶連結が壊れる。
- **history type allowlist が片方だけ（会社 CODEX D-1）**：`class-mpu-rest-chat.php` だけに `give` を追加しても、
  `MPU_Chat_History_Service::parse_history_from_request()` 側で `give` が降格する。必ず `MPU_Chat_History_Service::ALLOWED_MSG_TYPES`
  にも追加する。
- **backend checksum 未書き込み（会社 CLAUDE C-B）**：decoration/touch を踏襲すると give も前端 history と後端 checksum が
  ズレる（現状は checksum が observational のため 400 にならないだけ）。§13.2 を保つため `store_after_auto(..., 'give')` を呼ぶ。
- **backend checksum に synthetic anchor が無い（会社 CODEX D-2 / G-2）**：assistant reply だけを保存すると、前端 history の
  anchor と backend checksum が一致しない。anchor は前端任せにせず、backend が catalog name から localized 文字列を生成して
  history に追加してから checksum を保存し、同一文字列を `res.user_anchor` で返して前端に使わせる。
- **conversation stats が無効 type（会社 CODEX D-3）**：`mpu_record_conversation('give')` を呼んでも `stats-collector.php`
  の valid types に無ければ記録されない。送禮／給食を分析したいなら stats type も同時に追加する。

---

### ✅ 確定済み（レビューで決着）

- **catalog 供給** → `wp_localize_script`（#2）。
- **アイテム画像** → MVP は text-only、`image` 欄は保持（#3）。
- **observation type 名** → `item`／`dedupe_key` 必須（#1・R2-1）。
- **normalizer context** → `give`、inner monologue default = false（#5）。
- **エラー契約** → `run_reaction()` は `WP_Error` を返し caller は `is_wp_error()` 即 return（R2-3）。
- **rate limit key** → `give_item` で独立（R2-4）。
- **synthetic anchor** → backend 生成の localized anchor（`res.user_anchor`）。checksum・REST 返却・前端 push は同一文字列（G-2 が R2-2/D-2 の前端組立を改訂）。
- **エンドポイント名** → `/touch/give`（A-1・G-4）。`MPU_REST_Touch` 名前空間に同居。
- **UI 入口** → `#ukagaka_msgbox` 入力欄付近の小ボタン → テキスト一覧。Canvas 域に干渉しない（R2-5）。

### ⏳ 実装時に確定（残）

1. **localize ハンドル**：catalog をどの enqueue 済みスクリプトにぶら下げるか（既存 frontend の localize 箇所に相乗り）。
2. **メニュー展開の見た目**：小ボタン押下後のドロップ表示スタイル（位置・最大件数・スクロール）。Phase 3 の D&D／画像化と整合する形が望ましい。
