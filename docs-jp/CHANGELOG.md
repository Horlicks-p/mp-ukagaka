# MP Ukagaka バージョン履歴

> 📋 全バージョンの更新記録

---

## [2.24.1] - 2026-05-29

### 🐛 バグ修正

#### Observation コンテキストでの装飾品名の解決

- 訪問者が装飾品に触れた後「何をしましたか？」と尋ねると、キャラクターは具体的な品名（例：杖、スーツケース）ではなく「装飾品に触れた」としか答えませんでした。Observation buffer には装飾品の `type` slug しか保存されておらず、prompt builder もそのまま raw slug を出力していました。
- `ghost/Frieren/decorations.json` の各エントリに `name` フィールドを追加しました（例：魔法杖、魔導書、スーツケース）。
- `MPU_Observation_Buffer` は drain 時に `mpu_load_personality_decorations()` 経由で装飾品 type → 表示名を解決するようになりました（name-first lookup）。drain 時に解決する理由は、push 側の normalize 正規表現が `[a-z0-9_]+` しか許容しないため、ローカライズされた名前を buffer に保存できないからです。fallback は ghost に依存しません（`str_replace _ → space`）。汎用クラスにキャラクター別の type マップを焼き込むことはしません。

### 🛠️ コード品質ツール整備

- PHPCS baseline（`tools/php/phpcs-baseline.json`）を追加し、分岐時点で検出されたすべての WordPress Coding Standards 違反をスナップショット化しました。新規コードは現行基準で検査され、既存の違反は照会可能な形で保持されます。
- `lint:phpcs` を `npm run verify` に組み込み、pre-commit チェックに含めました。
- PHPCS の `testVersion` を、宣言済みの PHP 7.4 最低要件に揃え、サポート対象外の言語機能由来の誤検出を防ぎました。

### 📐 リポジトリ整備

- `.gitattributes` の line-ending ポリシーを採用：すべてのソースファイルは LF、追跡対象の `.po` / `.pot` カタログは BOM を維持します。
- リポジトリ全体の line ending を正規化しました。
- EOL 正規化後に PHPCS baseline を再生成しました（1336 → 230 件。残りは EOL に起因する false positive ではなく、真の技術負債です）。

### 🌐 翻訳の調整

- 従来日本語 source に fallback していた console log と UI 文字列に、英語（en_US）翻訳を追加しました。
- アイドル / タイマー / ghost ライフサイクル周辺の日本語 console log の文言を調整しました。
- v2.22.1 changelog エントリーの日本語表記の typo を修正しました。
- Frieren の装飾品フォールバック台詞を、PHP 側の canonical source と整合させました。
- **`（…えっと…何を話せばいいかな…）` placeholder の「を」助詞の欠落を修正しました**：`zh_TW.po` の msgstr が `（…えっと…何話せばいいかな…）` という typo になっており、`js/ukagaka-base.js` の `systemMessages` ブラックリストが、その typo の繁体字中国語 locale でのレンダリング結果からコピーされていました。この不一致により、zh_TW 以外の locale ではキャラクターアニメーションのスキップロジックが沈黙的に壊れていました（placeholder 表示中もアニメーションが再生され続けていた）。
- すべての `.mo` ファイルを再コンパイルしました。

### 📚 ドキュメント

- en / jp / zh-TW の `DEVELOPER_GUIDE` ディレクトリツリーとモジュール読み込み順序を最新化しました。
- `API_REFERENCE` と `DEBUG_SLIMSTAT` ドキュメントの、展開可能な visitor-info debug log セクションを復元しました。
- reality-checked plan status snapshot（2026-05-27）を追加しました。

---

## [2.24.0] - 2026-05-26

### 🌐 フロントエンド console log i18n —— 完了

- フロントエンドのすべての console log 文字列を、繁体字中国語のハードコードから日本語 source 文字列へ移行し、WordPress locale を通じて翻訳表示するようにしました。`js/` と `ghost/` にハードコードされた CJK console log は残っていません。
- 移行はすべての console log を対象とします。production-visible 部分（`js/ukagaka-anime.js`、`ghost/Frieren/frieren.js`、core bundle 収尾の `js/ukagaka-features.js` / `js/ukagaka-base.js`）に加え、core bundle・Frieren・dialog・greeting・emoji の各モジュールに散在する 195 件の debug-gated log を含みます。
- log は `mpuLogger` ヘルパー（`logL` / `logF` / `warnL` / `warnF`、および常時出力の `errorL` / `errorF` / `warnAlways` / `warnAlwaysF`）を経由し、安定したセマンティック key で参照されるようになりました。文字列は 2 つの bucket で配信されます：`mpuL10n.logs`（常時注入）と `mpuL10n.logsDebug`（フロントエンド debug モード時のみ注入）。
- 出力タイミングと gating は変更ありません。error/warn の出力は従来どおりで、debug 専用 log は管理者が `WP_DEBUG` を有効にしている場合のみ表示されます。

### 🗂️ 翻訳カタログ

- `mp-ukagaka.pot` を再生成し、`ja`・`zh_TW`・`en_US` カタログをマージしました。日本語と繁体字中国語は、206 件の unique log msgid（212 件の登録、うち 6 件は同一の日本語 source を共有）すべての翻訳が完了しています。英語（`en_US`）はマージ・コンパイル済みですが未翻訳のため、英語 locale のサイトではこれらの log は日本語 source 文字列に fallback します。

## [2.23.2] - 2026-05-24

### 🐛 バグ修正

#### Observation Buffer — SPA ナビゲーション追跡

- 観察追跡（`page_view` / `stay_duration`）が、単一記事が初回読み込みだった場合だけでなく、クライアントサイド（SPA）ナビゲーションで記事に遷移した後にも開始されるようになりました。
- 投稿 ID は DOM（`postid-` / `page-id-` の body class、`data-post-id`、`article[id^="post-"]`）から再検出し、PHP が注入した `mpuPageContext.postId` が存在する場合はそれを優先します。
- 一覧・アーカイブ・トップページでの誤発火をガードし、SPA ナビゲーションのたびに再検出の前に古い投稿 ID をクリアします。

#### ページ感知の自動発話 — 自動会話の復帰

- ページ感知の自動発話の表示コールバックにあった脆弱な `!mpuAutoTalkTimer` ガードを削除しました。本番環境の API レイテンシにより古いタイマー参照が残ると、自動会話が停止したままになることがありました（ローカルでは再現せず）。
- `startAutoTalk()` は内部で既存タイマーを先に停止するため、自動会話が有効な場合は復帰が無条件になりました（タイマーの二重化なし、元の発話間隔を維持）。

---

## [2.23.1] - 2026-05-24

### 🌐 言語設定の統一とフォールバックロジックの修正

- ゴーストの言語設定の競合を修正しました。言語解決の優先順位は次のようになりました：バックエンドの明示的設定 > ゴースト専用 `manifest.json` > 最終フォールバック 日本語 (`ja`)。
- バックエンドの一般設定とAI設定の言語ドロップダウンに「デフォルト」オプションを追加し、管理者が言語の決定をゴーストの `manifest.json` に委ねることができるようにしました。
- 未使用の簡体字中国語と韓国語のマッピングを削除し、言語リストを簡素化しました。

---

## [2.23.0] - 2026-05-23

### 🧠 Observation Buffer (観察バッファ)

#### 🧩 セッション単位の観察ヒント

`MPU_Observation_Buffer` を追加し、訪問者の直近行動を session token 単位で transient に一時保存し、次のユーザー主導チャットへ状況情報として注入できるようにしました。

- Observation Buffer は WordPress transient を使用します。scope key は session token の hash から生成し、元の token を key にそのまま保存しません。
- 1 session あたり最大 5 件、1 件あたり 200 bytes まで保存します。TTL はデフォルト 1 時間で、`mpu_observation_buffer_ttl` filter により 5 分〜2 時間の範囲で調整できます。
- `/chat/user` が system prompt を組み立てるタイミングで buffer を `drain()` するため、同じ観察セットが重複して注入されることはありません。

#### 🔌 REST API とフロントエンド追跡

- `POST /mp-ukagaka/v1/observation/push` を追加しました。現時点では frontend からの `page_view` と `stay_duration` を受け付けます。
- endpoint は有効な session token を必須とし、`60 秒あたり 20 回` の rate limit を適用します。
- frontend に `mpuObservationPush()` と `mpuInitObservationTracking()` を追加し、記事ページの閲覧と 10 / 30 / 60 / 180 / 600 秒時点の滞在時間を記録します。
- session token が古く 403 になった場合、frontend は token を再取得して 1 回だけ再試行し、長時間滞在や SPA 遷移時の取りこぼしを減らします。

#### 👻 インタラクションイベント連携

- キャラクター本体や装飾物への touch は `touch` 観察として蓄積され、同じ部位への接触は重複排除しつつ回数として合算されます。
- 起こし、睡眠中からの起こし、ページ文脈トリガーなどの lifecycle event も buffer に入り、次の会話で「直前に何が起きたか」を自然に扱えるようになりました。
- `/chat/user` は観察情報を「最近の訪問者行動」ブロックとして整形し、指示ではなく状況情報として system prompt に追加します。

#### 🛡️ 安全性と境界

- 観察内容は許可 type のみ受け付け、type ごとの形式検証、HTML 除去、長さ制限を行います。非公開記事やパスワード保護記事はタイトルを出さず `[non-public]` として扱います。
- Buffer は短期 transient の文脈情報のみで、User Memory へは書き込まず、永続的な訪問者プロフィールも作成しません。
- `bot_signal` は将来の phase 2 用として予約されており、現時点では書き込みを拒否します。

---

## [2.22.1] - 2026-05-22

### 👻 ゴースト睡眠復帰時の愚痴/寝起きの不機嫌メカニズム（v2.22.1）

訪問者がキャラクターの「深眠（deep_sleep）」または「寝坊（oversleep）」中に起こすボタン（`/wake-ghost`）をクリックした際、バックエンドで LLM を使用してキャラクターらしい日本語の起床時の愚痴（寝起きの不機嫌）を動的に生成し、フロントエンドでタイプライター効果を用いて表示する機能を追加しました（従来のデフォルト歓迎メッセージを置き換えます）。

### ⚙️ バックエンド REST API と LLM 生成（`includes/rest/class-mpu-rest-dialog.php`）

- 新規関数 `get_wake_sleep_phase()` を追加し、状態書き込み前に現在のフェーズが `deep_sleep` か `oversleep` かを判定。
- 新規関数 `generate_wake_reaction()` を追加し、対応する日本語の AI プロンプトを動的に選択して `mpu_call_ai_api` を呼び出し（`max_tokens=120` 制限）、`<think>` タグや HTML タグを除去。エラー時は空文字列 `''` を返して安全にフォールバックさせ、デバッグログを出力。
- 起こし REST API レスポンスに `sleep_phase` と `wake_reaction` フィールドを追加。
- Ollama ローカルモデル用パスにおいて `busy-lock` ミューテックスを適用し、並行リクエストによる負荷を防ぐ。

### 📐 人格設定とプロンプト（`ghost/Frieren/sleep_mode.json` & `includes/personality/personality-prompts.php`）

- `sleep_mode.json` に `wake_reaction_prompts` 設定を追加し、`deep_sleep` と `oversleep` それぞれに 3 つのランダム AI プロンプトを定義。
- `personality-prompts.php` に `mpu_pick_wake_reaction_prompt()` を追加し、安全性のバリデーションを行いながらプロンプトをランダム抽出。

### 🔌 フロントエンド対話の統合（`js/ukagaka-chat.js`）

- `mpu_send_wake_up_request()` を Promise を返すように変更し、`window.mpuWakeRequestPromise` で並行の重複クリックを防止。LLM 生成時間を考慮してタイムアウトを 60 秒に延長。
- 新規関数 `mpu_display_wake_reaction()` を追加し、タイプライターで愚痴を表示させ、さらに `{ type: 'wake_reaction' }` を `window.mpuChatHistory` に追加して後続の対話文脈（寝起きの不機嫌コンテキスト）を維持。
- `mpu_toggleChatMode()` および OK ボタンハンドラを統合し、起こしアニメーション完了後にリクエストを await させ、愚痴が生成されなかった場合は安全に既存の歓迎メッセージへフォールバック。

### 📦 変更ファイルとビルド

- `mp-ukagaka.php` 主ファイルおよびバージョン定数を `2.22.1` に更新。
- フロントエンドアセット（`js/dist/ukagaka-bundle.js` および `js/dist/ukagaka-bundle.min.js`）を再ビルド。

---

## [2.22.0] - 2026-05-22

### 👻 Ghost Runtime State Helper（v2.22.0 #7 milestone）

新規の transient ベース helper で、フロントエンドセッションごとの「ゴースト runtime state」を短期記録します。今後の runtime UI / 観測機能のためのバックエンド基盤です。`plan/Engineering_Quality_Improvement_Plan.md` §v2.22.0 #7 の hard limits に対応 —— LLM prompt 注入なし、Observation Buffer 不実装、User / Visitor Memory への書込みなし。

### 📐 Public API（`includes/core/runtime-state-functions.php`）

State whitelist（9 個）：`idle` / `thinking` / `speaking` / `chatting` / `sleeping` / `waking` / `tool_running` / `suspended` / `error`。Payload shape：`['state' => string, 'ts' => int]`。

| Function | 用途 |
|---|---|
| `mpu_runtime_state_allowed_states(): array` | whitelist を返す |
| `mpu_runtime_state_scope_key(?$session_token): ?string` | transient key を解決；raw token は `sha256` で hash 化、key にそのまま入れない |
| `mpu_set_runtime_state(string $state, ?$session_token): bool` | 書き込み；invalid state または scope 解決失敗 → `false` |
| `mpu_get_runtime_state(?$session_token): ?array` | 読み出し；欠落・不正・whitelist 外 → `null` |
| `mpu_clear_runtime_state(?$session_token): void` | transient 削除 |
| `mpu_runtime_state_ttl(): int` | TTL（秒）、デフォルト 300、`mpu_runtime_state_ttl` filter で調整可、`[60, 900]` clamp |

### 🔌 REST Wiring（`MPU_REST_Base` + chat/dialog controllers）

- **`/chat/user`** —— LLM 呼び出し前 `thinking`、成功 return 前 `speaking`、`finally` で `idle` へ復帰。error 分岐 → `error` → `idle`。`register_shutdown_function` で異常中断時にも `idle` を書く二重保険。
- **`/chat/user-stream`** —— SSE start で `thinking`。stream callback が `status` イベント（`type=executing_tool`）を検知すると `tool_running`、`delta` イベントで `speaking` に切替。`exit_if_stream_aborted()` がクライアント切断時 `exit` 前に `idle` を書いて状態解放。`done` → `idle`、stream 途中エラー → `error` → `idle`。同様の shutdown fallback あり。
- **`wake_ghost`** —— token 取得直後に `waking`、endpoint 全体を `try/finally` で囲み、どの早 return `WP_Error` 分岐でも最終的に `idle` に戻る。

新規 `MPU_REST_Base::runtime_session_token(WP_REST_Request)` が `X-MPU-Session-Token` header / `session_token` パラメータを解析し `mpu_validate_session_token()` で検証、すべての state 書込み経路が同一の token resolver を共有。

### 🛡️ Plan §v2.22.0 #7 Hard Limits コンプライアンス

| 制限 | 状態 |
|---|---|
| `session_start()` / `session_id()` 使用禁止 | ✅ word-boundary regrep で 0 hit |
| IP / referrer / fingerprint を scope に使用禁止 | ✅ token-only（option として logged-in `user_id` fallback あり、下記参照） |
| `mpu_opt` / `update_option(...runtime...)` 書込み禁止 | ✅ 0 hit |
| REST response shape 変更禁止 | ✅ wiring は write-only、payload に新規 field なし |
| TTL clamp、デフォルト 5 分 | ✅ `[60, 900]` clamp |
| State whitelist 強制 | ✅ 未知値は書込み `false`、読出し `null` |
| error/abort/done 後にクリア | ✅ `finally` + SSE abort + shutdown fallback の三重保険 |

### 🟡 Plan からの逸脱（明示記録）

`mpu_runtime_state_scope_key()` は **logged-in user fallback** を追加：caller が session token を渡さない（あるいは検証失敗）かつ `is_user_logged_in()` が true の場合、`mpu_runtime_state_user_{user_id}` を transient key として使用。Plan は token-based scope のみを定義しているが、この user-id 分岐は：

- IP / referrer / fingerprint レッドラインに抵触しない（§scope rule 4 を満たす）
- token-hashed key と異なる prefix で衝突なし
- admin が wp-admin から直接アクセスするような、フロント session-token bootstrap を経由しない経路でも state 書込み可能

実用上この fallback が不要と判断されれば、public signature を壊さずに削除可能。

### 🧪 テスト（`tests/Unit/RuntimeStateTest.php`）

8 ケース：valid 書込み/読出し round-trip、invalid state 拒否、invalid token は scope key を作らない、匿名で token なしは scope なし、scope key が token を hash 化し raw 値を漏らさない、`clear` で transient 削除、TTL filter 上下界 clamp（`999999 → 900`、`1 → 60`）、logged-in user は token なしでも書込み可能。`tests/bootstrap.php` に `wp_salt()` と `get_current_user_id()` mock を追加して新 fixture を支援。

### ✅ 検証

- `npm --prefix tools/node run lint:php`：全 PHP ファイル clean
- `npm --prefix tools/node run test:php`：**35 tests / 76 assertions 全グリーン**（v2.22.0 起点 27/59、本 milestone で +8 tests / +17 assertions）
- Red-line greps（`session_start(` / `session_id(` word-boundary、`update_option(...runtime` / `mpu_opt['runtime_state']`、`mpu_get_session_key`）：source 内 0 hit

### 📦 Commit 構成

feature commit `feat(v2.22.0): ghost runtime state helper (#7)` + 本 CHANGELOG commit の 2 commit。本 milestone は PHP-only で build artifact 変更なし。

### 📋 Milestone ノート

v2.22.0 freeze 表をクローズ。次の段階：**#10 Observation Buffer MVP** は引き続き設計フェーズ（`plan/Observation_Buffer_Design.md`）、実装は User Memory v2 待ち。

---

## [2.21.1] - 2026-05-22

### 🐛 寝坊中フリーレンの起こし動作修正

「対話ウィンドウを開く」ボタンで寝坊中のフリーレンを起こす際、設計上は「目を開く + 対話ウィンドウを開く」のはずが、実際は「目を開く + 本めくりアニメ」が起こり、対話ウィンドウが開かない不具合。

### 🔍 根本原因

並存する 2 つの問題チェーン：

**起こし競合（race condition）**

- `mpu_toggleChatMode` は起こし分岐で先に `mpu_send_wake_up_request()`（バックエンド AJAX）を呼び、続いて `$msgbox.fadeOut(1000, ...)`、最後に callback 内で `triggerCharacterAnimation` を呼ぶ流れ。
- 1 秒の fadeOut 遅延中にバックエンドの起こしレスポンスが返ると `isSleepMessage()` の判定が変化（`data-initial-msg` クリアや `mpuInfo.isTemporaryWakeUp` 反転）。
- 結果：`wakeUp()` が false を返す → 起こしアニメ分岐をスキップ → `triggerFrierenSpeaking` 後段の本めくり経路に落ちる。さらに `onWakeUpComplete` が呼ばれないため対話ウィンドウは永遠に開かない。

**OK ボタン経路でのフラグ残留**

- OK ボタン起こし後に `mpuSkipNextManualBookFlip = true` を設定し、後続の手動操作で `mpu_nextmsg` に消費させる想定。
- しかし chat モードでは `handleOkAction` が `mpu_sendUserMessage()`（SSE ストリーム）を呼ぶため `mpu_nextmsg` に届かず、フラグが消費されない。
- 次回の手動操作で本めくりアニメを誤ってスキップしてしまう。

### 🛠️ 修正

| Commit | 内容 |
|---|---|
| `2433729` | (1) `window.mpuForceWakeUpNextTime` フラグを追加、`fadeOut` の前にセットして「今回は必ず起こす」をロック、バックエンドレスポンスに上書きされないようにする；(2) `triggerFrierenSpeaking` に `skipBookFlip` early-return 経路を追加、`sleepModeAwoken` が既に true でも本めくりが誤発火しないように、かつ `onWakeUpComplete` は呼んで対話ウィンドウを開く；(3) OK ボタン経路を、起こしアニメ完了後に `handleOkAction` を呼ぶよう変更、LLM 対話即時トリガーによるアニメ衝突を回避 |
| `6ceb36f` | (1) `wakeUp()` 冒頭で無条件に `mpuForceWakeUpNextTime` をクリア、`sleepModeAwoken` が既に true の場合のフラグ残留を防止；(2) `mpuSkipNextManualBookFlip` に `Date.now()` token + 8 秒 timeout フォールバックを追加；(3) consumer 側で正常消費時に token を null クリア、timeout 発火時の自然 no-op 化、「古い timeout が新しいフラグを誤クリア」リグレッション窓を閉じる |

### 📦 変更ファイル

- `ghost/Frieren/frieren.js` — `wakeUp()` / `triggerFrierenSpeaking()`
- `js/ukagaka-chat.js` — `mpu_toggleChatMode` / OK ボタン handler
- `js/ukagaka-core.js` — `mpu_nextmsg` 内の manual animation トリガー 2 箇所
- `js/dist/ukagaka-bundle.js` / `ukagaka-bundle.min.js` — `tools/node/build.js` でリビルド

### ✅ 検証ガイド

実機で 3 つのシナリオを確認、console log は以下のとおり（ログ文字列は繁体中文でハードコードされており、WordPress ロケールに追従しません — 既知の UX ギャップ、今後の改善課題として記録）：

- 寝坊中に「対話ウィンドウを開く」ボタンをクリック：`☀️ 芙莉蓮被喚醒了！(forceWakeUp)` + `📖 喚醒後跳過翻書動畫` + 対話ウィンドウが開く
- 寝坊中に OK ボタンをクリック：起こしアニメ完了後に LLM 返答が生成され、本めくりは出ない
- すでに起きている状態で通常操作：本めくりアニメが正常に再生され、誤ってスキップされない

---

## [2.21.0] - 2026-05-20

### 🏗️ JS グローバル状態の封装（v2.21.0 #8 milestone）

フロントエンドの runtime state はこれまで 19 個の file-level `let` と 9 個の `window.*` グローバルに分散していましたが、構造化された `window.MPU_STATE` namespace に集約し、31 個の setter/getter helper function 経由でアクセスするように再編しました（`mpuState` const alias を含めると合計 32 entry）。**アルゴリズム変更なし、REST payload 変更なし、UI 動作変更なし** — 純粋な構造リファクタです。

**移行レイヤー**：

| レイヤー | 変数 | 戦略 |
|---|---|---|
| 完全移行（`window.*` 残存なし）| `__mpu_retry_count` / `__mpu_fallback_retry_count` / `mpuContextPending` / `mpuSettingsProcessed` / `mpuSettingsLoaded` / `mpuEnableChatMode` / `debugMode` | 読み書き共に MPU_STATE のみ |
| 互換ブリッジ保持 | `mpuMsgList` / `mpuBaseAutoTalkInterval` | Helper が `window.*` と MPU_STATE 両方に書く；getter は `window.*` を canonical 扱い |
| Dual-write helper | 17 個の primitive（autoTalk / typewriter / AI / LLM / dialog / greet state）| Helper が旧 `let` と MPU_STATE 両方を更新；reader はどちらでも可 |
| 同一オブジェクト参照 | `mpuLLMResponseHistory` / `mpuOllamaRequestQueue` / `__mpuStorage` | Array/object 共有 — mutation 自動同期 |

**Plan §2.3「移行しない一覧」保持**（意図的に未変更）：`window.mpuChatHistory`, `window.mpuChatModeActive`, `window.mpuChatSessionId`, `window.mpuChatRequesting`, `mpuChatAbortController`（chat shared state）；`mpuInfo`, `mpuSettings`, `mpuPreSettings`, `mpuRestUrl`, `mpuRestNonce`, `mpuL10n`, `mpuInitData`, `mpuInitParams`, `mpuPersonalityId`（PHP localized data 契約）；`mpuCanvasManager`, `mpuEmojiManager`, `mpuFrierenManager`, `mpuEmojiConfig`（manager objects）；`MPU_EVENTS`, `mpuSpaEvents`（event surface）。

### ✨ 動作変更（意図的）

- **`window.mpuDebugMode = true` がブラウザコンソールで即座に有効に**。従来は `let debugMode` がスクリプト読込時に一度だけ window flag をキャプチャしていたため、console 切替が反映されませんでした。新しい `mpuIsDebugMode()` helper は呼び出し毎に `MPU_STATE.flags.debugMode` と `window.mpuDebugMode` の両方を即時チェックするため、runtime 切替が即座にログに反映されます。

### 📐 Helper 一覧（`js/ukagaka-base.js`）

合計 31 個 function（+1 個 `mpuState` const alias = 32 entry）：

- **State access**：`mpuGetState`, `mpuState`（const alias）
- **Debug**：`mpuIsDebugMode`
- **AutoTalk**：`mpuSetAutoTalkTimer`, `mpuSetAutoTalkEnabled`, `mpuSetAutoTalkInterval`, `mpuSetBaseAutoTalkInterval`, `mpuGetBaseAutoTalkInterval`
- **Typewriter**：`mpuSetTypewriterTimer`
- **LLM/AI**：`mpuSetAiTextColor`, `mpuSetAiDisplayDuration`, `mpuSetAiDisplayTimer`, `mpuSetAiContextInProgress`, `mpuSetMessageBlocking`, `mpuSetOllamaReplaceDialogue`, `mpuSetLastLLMResponse`, `mpuResetLLMResponseHistory`, `mpuSetOllamaRequesting`, `mpuSetLastUserActionTime`
- **Dialog**：`mpuSetDialogStore`, `mpuGetDialogStore`, `mpuSetDialogNextMode`, `mpuSetDialogDefaultMsg`
- **Flags**：`mpuSetGreetInProgress`, `mpuSetContextPending`, `mpuIsContextPending`, `mpuSetSettingsProcessed`, `mpuIsSettingsProcessed`, `mpuSetSettingsLoaded`, `mpuIsSettingsLoaded`, `mpuSetEnableChatMode`, `mpuIsChatModeEnabled`

### 🔁 Plan §2.3 #6 からの逸脱（実装が計画より優れている）

Plan は当初「ローカルショートエイリアス + 段階的置換」で refactor 中の scope エラーを避けることを提案しましたが、実装は **setter helper function** に変更 — dual-write ロジックを単一箇所に集約し、grep フレンドリーで scope 問題に免疫があります。Intent は同じ（search-replace バグ回避）で実行はよりクリーン。

### 📦 Commit 構成

本 milestone は 2 commit でランディング：

1. `refactor(js): encapsulate runtime state into window.MPU_STATE` — 7 ソースファイルの変更
2. `chore(build): rebuild dist bundle for v2.21.0 #8 MPU_STATE migration` — `tools/node/build.js` の純出力

Plan §2.3 は当初 7 step commit を計画（Inventory / Namespace / Base+core / Dialog+context+greeting / Features / Chat boundary / Bundle）。6 個の source step は途中 commit なしで実装され、最後に単一 source commit へ統合。Trade-off：bisect は「v2.21.0 vs pre-v2.21.0」を分離可能だが milestone 内の単一 step は特定不可。緩和策：source 変更がファイル境界（base/core/dialog/context/greeting/features/chat それぞれ独自の scope）に従っているため、本番リグレッション時は file-level cherry-pick が可能。

### ✅ 検証

- `npm run verify`：lint + bundle + PHPUnit（27 tests / 59 assertions）両 commit 共に独立して pass。
- 完全移行された変数の source grep が 0 hit を確認。
- `window.mpuMsgList` / `window.mpuBaseAutoTalkInterval` の source 参照は base.js helper 内部の compat bridge 書込みと defensive fallback のみ。
- `git diff --check` クリーン。

### ⚠️ 既知の制限

- **Release 前に manual smoke test 必須**。PHPUnit はバックエンドのみカバー、JS runtime リグレッションは検出不可。Plan §2.3 #6 検証 path：auto-talk / chat / context / SSE / typewriter / wake_ghost / first-visit greeting / SPA navigation / sleep-mode interval。
- **`mpuGetDialogStore()` defensive fallback は通常運用下では dead code**：`typeof window.mpuMsgList !== "undefined"` は base.js init 後常に truthy（`typeof null === "object"`）、`MPU_STATE.dialog.msgList` fallback は実行されません。**意図的に保持**して外部スクリプトが `window.mpuMsgList = undefined` に設定するケースの安全網としており、inline comment で意図を説明。

### 📋 Milestone メモ

凍結表の v2.21.0 を完了。次の予定：**v2.22.0 = #7 runtime_state helper**（Avatar §4）または **#9 CSS theme / i18n hot swap**（Avatar §7 + §8）— 実装複雑度次第で決定。

---

## [2.20.0] - 2026-05-20

### 🔧 utility-functions.php のドメイン分割（v2.20.0 #6 milestone）

純粋なファイル移動のみで、ロジック変更はありません。約 1,143 行の catch-all ファイルを 5 つのドメインファイルに分割しました。`utility-functions.php` には定数のみ残存（36 行）。

| 新ファイル | 行数 | 関数 |
|---|---|---|
| `core/template-functions.php` | 142 | `array2str` / `str2array` / `output_filter` / `js_filter` / `render_prompt_template` / `build_user_info_prompt` |
| `core/file-functions.php` | 174 | `is_path_within_allowed_dir` / `secure_file_read` / `secure_file_write` / `get_dialogs_dir` / `ensure_dialogs_dir` |
| `core/encryption-functions.php` | 190 | `get_encryption_key` / `encrypt_api_key` / `decrypt_api_key` / `is_api_key_encrypted` / `get_provider_api_key` / `get_current_provider` |
| `core/wp-info-functions.php` | 284 | `get_wordpress_info` / `get_current_user_info` / `country_code_to_name` / `resolve_personality_id` |
| `core/network-functions.php` | 389 | `get_client_ip` / `get_client_ip_strict` / `fetch_external_api` / `clear_api_cache` / `check_rate_limit` / `rest_check_rate_limit` / `generate_session_token` / `validate_session_token` |

- **`mp-ukagaka.php` のロード順**：`debug → core → utility (定数) → template → file → encryption → wp-info → network → ...` — encryption は wp-info より前（provider key resolver が encryption に依存）、network はその他 utility 関数の最大消費者なので最後にロード。
- **tests/bootstrap.php**：PHPUnit でも同じ順序でロード。
- **雑関数の配置決定**（plan #5 の事前判断）：`array2str` / `str2array` / `output_filter` / `js_filter` → template；`fetch_external_api` / `clear_api_cache` → network；`resolve_personality_id` → wp-info。

### 🧹 冗長な防御コードの整理

`mp-ukagaka.php` のロード順により、以下のヘルパーは caller より前に必ず存在することが保証されているため、`function_exists` ガードはノイズでした：

- `chat/class-mpu-chat-history-service.php`：`mpu_chat_integrity_normalize_session_id` / `verify_history` / `store_history` の 3 ガード削除。
- `integrations/akismet-integration.php`：`mpu_chat_integrity_*` および `mpu_rest_check_rate_limit` の 4 ガード削除。
- `llm/class-mpu-chat-lock.php`：`normalize_session_id()` のインライン fallback 削除 — chat-integrity 版と byte-equivalent（`is_scalar` → `sanitize_key` → `substr 64`）。
- `rest/class-mpu-rest-base.php`：`rate_limit()` ガード削除。
- `rest/class-mpu-rest-dialog.php`：`mpu_chat_integrity_*` の 2 ガード削除。

### 🪦 旧 Sleep ヘルパーの dead code 削除（v2.19.2 follow-up）

v2.19.2 で正時版 sleep ヘルパーは dead code として明示されました（`wake_ghost` の fallback 参照のみ）。本リリースで正式削除：

- `llm/llm-context-builder.php`：`mpu_get_daily_deep_sleep_start()` と `mpu_get_daily_oversleep_end()` を削除。`_mod` 版は継続使用。
- `rest/class-mpu-rest-dialog.php`：`wake_ghost()` の 3 層 `function_exists` fallback を `_mod` への直接呼び出しに集約。

### ✅ 検証

- `npm run verify`：lint + bundle + PHPUnit（27 tests / 59 assertions）全て pass。
- `grep -h "^function mpu_" includes/core/*.php | sort | uniq -d` 空 — 重複定義なし。
- 旧正時ヘルパーの code-path grep は doc / changelog / plan の履歴記録のみ。

### 📋 Milestone メモ

本リリースは凍結表の v2.20.0 を完了します（v2.19.2 patch により 1 枠前倒しになった後の slot）。次の予定：**v2.21.0 = #8 JS グローバル状態の封装** — surface が大きく manual smoke test が必須のため、バックエンド作業とはバンドルせず独立リリース。

---

## [2.19.2] - 2026-05-20

### ✨ 睡眠システムの分単位精度

v2.19.1 の「毎日の正時抽選」を分単位精度まで拡張。manifest schema は変更なし（引き続き正時で記述）、純粋なバックエンド側の cache key 世代交代です。

- **`_mod` サフィックス関数の新設**：`mpu_get_daily_deep_sleep_start_mod()` / `mpu_get_daily_oversleep_end_mod()` は minutes-of-day（0–1439）を返します。`[22, 23]` の deep_sleep_start は `random_int(22*60, 23*60+59) = random_int(1320, 1439)` で抽選され、「22:00 ～ 23:59 のいずれかの分」inclusive に拡張されます（従来は「22 時または 23 時の正時のみ」）。
- **比較処理を全て minutes-of-day に**：`mpu_is_deep_sleep_time()` の日跨ぎ判定を分単位で書き直し（`$start_mod > $end_mod` で OR 分岐）、`mpu_is_ip_woken_today()` / `mpu_mark_ip_as_woken()` の境界判定も分単位に揃えました。
- **cache key に `_mod` サフィックス**：v2.19.1 の正時 key に残った旧値を強制的に miss させます。旧 key の backward-compat 読みは**行いません**。
- **フロントエンド側 caller も同時更新**：`includes/core/frontend-functions.php` は従来正時版を呼んでいましたが、`_mod` に切り替え、フロントの chat block 描画境界とバックエンドの判定を揃えます。
- **`wake_ghost()` 更新**：`_mod` ヘルパーを読むように変更。`function_exists` fallback は load 順序の保険として継続。
- **`oversleep_max_hour` の意味が微調整**：値 `9` は従来「09:00 ぴったりまでに起床」でしたが、分単位精度に合わせて「09:59 までに起床」に変わります。IP 記録ウィンドウもこれに合わせて 59 分延長されています。

### ⚠️ 既知の制限

- **旧正時ヘルパーは dead code に**：`mpu_get_daily_deep_sleep_start()` / `mpu_get_daily_oversleep_end()` は `wake_ghost` の fallback としてのみ残存し、ロジック上は到達しません。整理は **v2.20.0**（`#6` utility-functions 分割）で実施 — ファイル移動と dead code 削除は同じ前提条件のため、同 PR で扱います。
- **Sleep settings の入力 clamping は先送り**：`_mod` 関数は正の日跨ぎオーバーフローを `% 1440` で処理するのみで、負数の clamp は**行いません**。manifest に負値を入れると minutes-of-day 比較が破綻します（PHP `%` は dividend の符号を保持）。manifest は角色作者が手書きで管理しており admin UI が無いため、実害は出ません。完全な入力 validation は将来の admin UI PR と同じ milestone で実装すべきで、単独 clamp は無意味です。

### ✅ 検証

- `npm run verify`：lint + bundle + PHPUnit（27 tests / 59 assertions）全て pass。
- **PHPUnit は新 `_mod` 関数をカバーしていません**：v2.19.1 と同じく `set_transient` / `random_int` / `DateTimeImmutable` が `tests/bootstrap.php` で mock されていません。Manual smoke test の経路：日跨ぎ切替、同日 cache hit、二度寝+IP 記録（分単位精度に上昇）、設定不正、旧 cache 過渡期（`_mod` サフィックスで強制 miss）。

### 📦 バージョン表記について

本リリースの 2 commit（`b54d96c`, `239a5d1`）の commit message は `feat(v2.20.0):` / `docs(plan): v2.20.0` と書かれています — これは凍結表でこの機能を v2.20.0 として扱っていた時に commit された名残です。Release では **v2.19.2** patch として確定 — v2.19.1 の延長線上（manifest schema 不変・IP 機構不変・内部時間単位のみ精度向上）であるため。後続の凍結表 milestone は一つ前倒し（utility-functions 分割 → v2.20.0、JS グローバル状態の封装 → v2.21.0、以下同様）。

---

## [2.19.1] - 2026-05-19

### ✨ フリーレンの動的な睡眠時間（毎日抽選）

- **`deep_sleep_start` が `[start, end]` 時間レンジ配列に対応**：従来は固定整数（例：`23` で毎日 23:00 入眠）でしたが、`[22, 23]` のように書くと毎日 1 回抽選されます。同じ日の page load では transient で同じ値が返されるため（次の真夜中まで cache）、「リロードでフリーレンが急に寝る／急に起きる」というおかしな挙動を防ぎます。
- **`oversleep_probability` を 1.0 に**：フリーレンの manifest を 50% から 100% 二度寝に変更。`oversleep_max_hour: 9` と組み合わせて、毎日 07:00～09:00 の間でランダムに起床します。手動 `/wake-ghost` で IP を 3 時間記録し、リロード後も起きたままになります。
- **新関数 `mpu_get_daily_deep_sleep_start()`**：既存の `mpu_get_daily_oversleep_end()` と同じ構造。cache key は `mpu_deep_sleep_start_{date}_{pid}`、expire は翌日午前 0 時に揃えています（`DateTimeImmutable('tomorrow', wp_timezone())` + `max(60, ...)` clamp）。
- **配列のフェイルセーフ**：`is_array` 分岐は `random_int(min, max)` を使って範囲反転を自動補正、`count !== 2` のときは `mpu_log_warning` を出して先頭要素にフォールバック、非配列設定は従来通り整数読み。
- **`24 → 0` の明示処理**：manifest に `24` が設定されている場合、`if ($v === 24) $v = 0` で翌日 00:00 に変換します（フリーレンは曖昧さ回避のため `[22, 23]` を使用していますが、関数側に防御層を保持）。
- **呼び出し側の置き換え**：`mpu_is_deep_sleep_time()`（`includes/llm/llm-context-builder.php`）と `wake_ghost()`（`includes/rest/class-mpu-rest-dialog.php`）は従来 `$sleep_settings['deep_sleep_start']` を直接読んでいましたが、新関数を呼ぶように変更。`wake_ghost` 側は load 順序の保険として `function_exists` ガードを残しています。

### ⚠️ 既知の制限

- **入眠時刻は引き続き「正時」に揃います**：本リリースでは「何時に」の部分だけランダム化しました。分単位ランダム（22:43 入眠 / 07:35 起床）は起床側の分単位化と cache key 世代交代と合わせて **v2.19.2** で導入予定です。

### ✅ 検証

- `npm run verify`：lint + bundle + PHPUnit（27 tests / 59 assertions）すべて pass。
- **PHPUnit は新関数をカバーしていません**：`mpu_get_daily_deep_sleep_start()` は `set_transient` / `get_transient` / `wp_date` / `random_int` / `DateTimeImmutable` に依存しており、現在の `tests/bootstrap.php` ではモックされていません。「テスト pass」は lint OK、syntax OK、既存テスト path に regression が無いことを示すだけです。新関数の動作は manual smoke test（日付跨ぎ、同日 cache hit、二度寝 + IP、設定不正）で確認してください。

---

## [2.19.0] - 2026-05-19

### 🏷️ コア Class への型宣言（v2.19 #5 milestone）

- **`MPU_Session_Event`**：`build(string $kind, array $payload = []): array` と `kind_for_legacy_event(string $event): string` に PHP 7.4 互換の type hint を追加。
- **`MPU_Input_Role`**：5 つの static メソッド（`resolve` / `can_use_ability` / `current_can_use_ability` / `normalize_ability_name` / `is_known_role`）に `string` / `bool` 型を宣言。内部の `(string)` キャストは防御層として保持しています。
- **`MPU_REST_Base`**：`rate_limit` は `?WP_REST_Response`、`ok` は `WP_REST_Response`、`fail` は `WP_Error` を返します。`$data` は意図的に mixed のまま。**`check_admin()` は型宣言なし**：実際の contract は `true|WP_Error` で、PHP 7.4 には union types がないため。
- **`chat-integrity.php`**：13 個の関数に `string` / `array` / `bool` / `void` / `WP_Error` の型を追加。**`verify_history()` は型宣言なし**：実際の contract は `true|null|WP_Error`。`_store_history(): bool` の 2 つの return path（`return false` と `set_transient()` の bool）は整合済み。

### 📋 #6 utility-functions 分割の事前調査

- `chat-integrity.php` / `provider-helpers.php` / `utility-functions.php` は `rest/bootstrap.php` の前にロードされることを確認。REST と chat history 内の `function_exists('mpu_chat_integrity_*')` / `function_exists('mpu_rest_check_rate_limit')` は load 順序の保証下では冗長防御の候補です。
- **本リリースでは防御を削除しません**。冗長削除は #6 utility-functions 分割 PR でファイル移動と同時に実施します（ファイル移動が load 順序を変えうるため、`function_exists` 削除と同じ前提の表裏一体で、分割 review すると焦点がぼやけるため）。

### ✅ 検証

- `npm run verify`：PHP lint + bundle build + PHPUnit（27 tests / 59 assertions）すべて pass。
- **動作変更なし**：純粋な type hint 追加で、ロジック変更・ファイル移動・インターフェース変更は含まれません。

---

## [2.18.0] - 2026-05-18

### 🧪 テスト基盤（PHPUnit + verify pipeline）

- **PHPUnit ユニットテストを追加**：`tests/` ディレクトリを新設し、WordPress を起動せずに pure function を検証できる最小限の WordPress モック（`tests/bootstrap.php`）を用意しました。初期スイートは `chat-integrity`（filter / checksum / slice）、encryption の round-trip、input role 解決、session event envelope、template rendering、新規 chat lock の 6 件で、合計 22 tests / 51 assertions です。
- **Composer ベースのツール環境**：dev 依存（`phpunit/phpunit ^9.6`、`brain/monkey ^2.6`）は `tools/php/composer.json` で管理し、vendor は `tools/php/vendor/` に隔離されます。プラグイン本体は runtime の composer 依存を持ちません。
- **`npm run verify` パイプライン**：`tools/node/package.json` で `lint:php` → `build` → `test:php` を順に実行できるようにしました。build スクリプトは minify 失敗時に exit code を非 0 に変更したため、CI が誤って pass することはありません。
- **PHPUnit `cacheResult="false"`**：制限的な sandbox で `.phpunit.result.cache` 書き込みが permission warning を引き起こすケースを防ぎます。
- **filter / action モックが実際にコールバックを実行**：`tests/bootstrap.php` で priority ソートと `accepted_args` を尊重するようになりました。今後 `mpu_chat_integrity_mode` 等のフィルターを安心してテストできます。

### 🔒 Chat Lifecycle Lock（並行 LLM 防護）

- **新しい `MPU_Chat_Lock` クラス**（`includes/llm/class-mpu-chat-lock.php`）：`add_option($key, $payload, '', 'no')` による atomic check-and-set を採用しました。transient は `get_transient()` + `set_transient()` が並行リクエスト下で atomic でないため、まさに lock が防ごうとしている race を lock 自身が持ってしまうので採用していません。
- **期限切れ lock の retry**：`add_option()` が失敗し、かつ既存 lock が期限切れであれば `delete_option()` してから一度だけ再 acquire を試みます。クラッシュした PHP worker が残した stale lock は次のリクエストで自動回復します。
- **token 検証付き release**：`release($session_id, $token)` は `hash_equals()` で token を照合するため、別リクエストの lock を誤って解放することはありません。二重 finally バグへの防御層です。
- **`mpu_chat_lock_ttl` フィルターで 60 秒**（`[10, 300]` 秒にクランプ）。
- **3 つの action hook**：`mpu_chat_lock_acquired` / `mpu_chat_lock_released` / `mpu_chat_lock_conflict`。metrics 収集、audit log、将来の approval-hub 統合に利用可能。
- **`/chat/user` と `/chat/user-stream` のみ対象**：`/chat/greet`、`/chat/context`、`/debug_mcp` は意図的に lock しません（既存のルート別 rate limit でカバー）。
- **conflict は HTTP 429 を返却**：既存の `$this->fail()` envelope を使うため、フロントエンドのエラー処理を改修する必要はありません。
- **SSE 安全な release**：lock 取得直後に `register_shutdown_function()` を登録し、stream loop は chunk 間で `connection_aborted()` を `exit_if_stream_aborted()` 経由で確認します。クライアントが途中で切断しても lock は確実に解放され、token 検証により後続リクエストの lock を上書きしません。
- **lock context** に `route`、`input_role`、`ip_hash`（`sha256(client_ip)` の先頭 12 文字）を記録。raw IP を保存せずに triage が可能。

### 🧹 REST Chat ハンドラの重複削減

- **新しい `prepare_auto_chat_context()` ヘルパー**（`MPU_REST_Chat`）：`chat_context()` と `chat_greet()` で重複していた前処理（`ai_enabled` / `ai_greet_first_visit` チェック、provider + API key、`wp_info`、ukagaka 識別、language、personality、time context、13 個の variable map、解決済み system prompt）を集約しました。
- **`require_first_visit_greeting` オプション flag** によって `chat_greet` の追加チェックを差分化。2 つのヘルパーに分けずに済みます。
- **response shape は変更なし**。checksum 保存（`store_after_auto` の `'context'` / `'greet'` kind）は意図的にそのまま。`prepare_user_chat_args()`、chat lock、SSE 周りは触っていません。

### 🏷️ SSE Stream State Badge（runtime 検証 UI）

- **`#ukagaka_msgbox` の右上に表示される `.mpu-state-badge`**：2.17.0 で追加した `data-mpu-stream-state` 属性をベースにした可視レイヤーです。新しい state machine ではなく、既存データを可視化しただけ。
- **6 つの可視状態**：`thinking`、`streaming`、`tool`、`error`、`timeout`、`busy`。`status` は空ラベル回避のため `streaming` と同じ表示にマップされます。
- **chat lock 衝突時の `busy` 状態**：`handleStreamFailure()` が SSE の JSON fallback path から `error.code === "mpu_chat_lock_busy"` または `error.data.status === 429` を検出し、汎用エラーではなくローカライズされた「混雑中…」メッセージを表示します。
- **最初の delta で `streaming` 状態に**：`onDelta` は属性を消すのではなく `setStreamState("streaming")` を呼ぶようになり、テキスト streaming 中も badge が表示されます。
- **既存の `mpuL10n` 機構で i18n**：新しい `mpuL10n.streamStates` map（`考え中…` / `応答中…` / `調べてる…` / `エラー` / `タイムアウト` / `混雑中…`）は既存の `.po` / `.mo` ワークフローで翻訳可能です。

### 🐛 バグ修正

- **Ollama の空の Tool Calls 配列解析の修正**: Ollama が引数なしの tool call（例: `get-bot-blocker-stats`）を返す際、PHP の `json_decode` と `json_encode` が空のオブジェクト `{}` を空の配列 `[]` に変換してしまい、Ollama エンジンが `"Value looks like object, but can't find closing '}' symbol"` というエラーを投げる問題を修正しました。現在は `mpu_normalize_ollama_assistant_message()` を介して空の配列を強制的に `stdClass` (`{}`) に変換するようになり、全てのエッジケースを網羅する 5 つの PHPUnit テストケースも追加しました。
- **Provider HTTP エラーログの強化**: HTTP >= 400 エラーが発生した際、Provider JSON の `error` フィールドを抽出してよりクリーンなエラーメッセージを提供するようになりました。また、`error_log` を通じて tool_calls 構造や 4KB の response tail を含む完全なデバッグ情報を出力します。
- **UI 状態 Badge の位置微調整**: `.mpu-state-badge` の CSS を修正し、メッセージボックスの右上隅の内側によりフィットするように調整し、切り取られるのを防ぎました。

### ✅ 検証

- `npm run verify`：PHP lint、bundle build、PHPUnit（22 tests / 51 assertions）すべて pass。
- `js/dist/ukagaka-bundle.js` と `.min.js` を再ビルド（169.7 KB → 79.0 KB）。
- `git diff --check`：whitespace error なし。

---

## [2.17.0] - 2026-05-15

### 🛡️ Input Role Resolver と Server-side Tool Gate

- **新しい `MPU_Input_Role` クラス**（`includes/core/class-mpu-input-role.php`）：LLM 入力の identity（`admin` / `system` / `subscriber` / `visitor`）を WordPress capability から切り離します。`resolve()` がリクエスト context と現在の WP 状態から役割を導出し、`can_use_ability()` がハードコードされた whitelist を参照することで prompt injection 耐性を確保します。
- **リクエストスコープ input context**（`request-state.php`）：`mpu_set_request_input_context()` / `mpu_get_request_input_context()` を追加し、chat endpoint・tool 露出・tool 実行が同じ context を参照するようにしました。
- **二段階 tool gate**（`abilities-integration.php`）：`mpu_get_mcp_tools_for_llm()` が LLM に渡す前に役割で tool list をフィルタし、`mpu_execute_mcp_tool()` が実行時に再チェックすることで out-of-band な tool 呼び出しも捉えます。組み込み ability（`visitor-pulse` / `ai-crawler` / `wp-postviews` / `wp-bot-blocker` ×3）はすべて `MPU_Input_Role::current_can_use_ability()` を使い、`current_user_can('manage_options')` への安全な fallback を残しています。

### 📡 Session Event Envelope（SSE）

- **新しい `MPU_Session_Event` クラス**（`includes/llm/class-mpu-session-event.php`）：イベント種別（`stream.delta / status / done / error`、`tool.request / result`、`nonce.refresh`）を定義し、payload を `eventId + ts + kind + payload` で包みます。
- **後方互換性のある SSE**：`streaming-helpers.php` が `kind_for_legacy_event()` を経由して legacy 名を変換し、新 envelope に包みます。既存 endpoint は変更なしで動作します。
- **フロントエンド dispatcher**（`js/ukagaka-chat.js`）：`window.MPU_EVENTS` 定数と `mpuNormalizeSseEvent()` が新 envelope と legacy event を判別し、switch 文で双方を並行処理します。

### ⏱️ クライアント側 SSE Watchdog（zombie state 対策）

- **45 秒タイムアウト**＋ `AbortController.abort()`：45 秒間どの SSE event も届かなければ stream を abort し、未確定の user message を `mpuChatHistory` から rollback、入力欄を解放、ローカライズされたタイムアウトメッセージを `mpuShowBalloon()` で表示します。
- **統一された `onEvent` フック**：legacy 名と新 envelope の両方が watchdog をリセットするため、handler ごとに reset を書く必要がなく、漏れによるバグを排除します。
- **Typewriter 安全**：`stream.done` / `stream.error` が `onEvent` レベルで watchdog をクリアするため、バックエンドが完了しても typewriter が text を吐き終わるまで watchdog が誤発火しません。
- **`data-mpu-stream-state` 属性**を `#ukagaka_msgbox` に付与：`thinking / tool / status / timeout / error` を CSS や debug の hook として利用できます。
- **`tool.request` ハンドラ**：将来 backend が `tool.request` を送るようになると、フロントエンドが「ツール実行中…」状態を表示します。

### 🔐 Chat Integrity 三段モード

- **新しい `chat_integrity_mode` オプション**（既定値：`audit`）：`audit` は mismatch を log するのみで中断しません（既存挙動）。`warn` は WARN 判定を記録し、`mpu_chat_integrity_mismatch` action hook を発火します。`block` は expected checksum が存在し、actual が一致しない場合のみ `WP_Error`（HTTP 409）を返します。
- **3 つの制御点**：定数 `MPU_CHAT_INTEGRITY_MODE` > オプション `chat_integrity_mode` > フィルター `mpu_chat_integrity_mode`。expected checksum が無いケースは常にスルーするため、`block` でも初回リクエストや session reset で誤殺しません。
- **`mpu_chat_integrity_should_block` フィルター**：per-session bypass や `block` モードのグレースケール展開が可能です。
- **`mpu_chat_integrity_mismatch` action hook** は audit / warn / block の三モードすべてで発火し、`{ session_id, expected, actual, mode, decision, source, history_count }` の payload を持ちます。`audit` モードでも metrics 収集や webhook 連携に使えます。
- **REST chat が WP_Error の status を尊重**：`class-mpu-rest-chat.php` が `error_data['status']`（409）を読み取り、400 のハードコードを置き換えました。
- **最小切り口**：store / normalize / slice の流れは触らず、verify mismatch の決定層のみを変更しました。

### 🛠️ Hardening パッチ（2.16.1–2.16.4 から取り込み）

- **管理画面の output escaping 監査**を全体に適用。
- **Bot-blocker のスキャナー除外**：full scanner 除外とログイン済みリクエストへの rate-limit スキップで誤評価を防ぎ、共有用 helper `mpu_bb_is_logged_in_request()` を抽出しました。
- **`MPU_PLUGIN_DIR` 修正**：未定義の定数参照を `plugin_dir_path(MPU_MAIN_FILE)` に置換しました。

### ✅ 検証

- 変更した PHP ファイルすべてで `php -l` 通過（`chat-integrity.php` / `chat-history-service.php` / REST chat / `core-functions.php` / ability 群）。
- JS 構文チェック通過（`ukagaka-chat.js` / `dist/ukagaka-bundle.js`）。
- Bundle 再ビルド（`js/dist/ukagaka-bundle.js` + `.min.js`）。
- `git diff --check` で whitespace error 無し。

---

## [2.16.0] - 2026-05-13

### 🧠 User Memory MVP

- **管理者向け `/remember` コマンドを追加**：管理者がフロントエンドのチャット欄で `/remember` と入力すると、直近 20 件の会話履歴から安定した管理人情報を抽出し、`mpu_user_memory` usermeta に保存します。
- **Memory REST Controller を追加**：`MPU_REST_Memory` と `POST /memory/extract` を追加。`manage_options`、WordPress REST nonce、60 秒 transient throttle、defensive cleanup で保護します。
- **System prompt への記憶注入**：`mpu_resolve_system_prompt()` が保存済み記憶を「管理人についての記憶（参考メモ）」として注入し、指示ではなく参考情報として扱うよう明記して prompt injection リスクを抑えます。
- **AI 設定ページで記憶管理**：AI tab 下部に記憶カードを追加し、保存済み facts、最終更新時刻、現在の管理者ユーザーの記憶を nonce-protected form で削除できるようにしました。

### ✅ リリース品質と Runtime Info 改善

- **リリース検証ツールを追加**：`npm run lint:php` がメインプラグインファイルも検査するようになり、`npm run verify` で PHP lint と JS build を連続実行できます。
- **REST smoke test checklist を追加**：`docs/REST_SMOKE_TEST.md`、`docs-en/REST_SMOKE_TEST.md`、`docs-jp/REST_SMOKE_TEST.md` を追加し、baseline endpoint、session token、token enforcement、chat round-trip、admin guard、SSE headers を確認できるようにしました。
- **GitHub 自動更新サポートを追加**：Plugin Update Checker v5.6 を同梱（`vendor/plugin-update-checker/`）し、`includes/updater/github-updater.php` を追加。GitHub Release が発行されると WordPress 管理画面に更新通知が表示されます。各タグに `mp-ukagaka.zip` を release asset としてアップロードするとワンクリック更新が有効になります。
- **Runtime Info を微調整**：天気の温度閾値を調整し、Frieren の `instructions.md` に感情トリガールールを追加。過剰反応を避けながらキャラクターの細部を強化しました。
- **i18n debt cleanup**：`mp-ukagaka-zh_TW.po/.mo` と `mp-ukagaka-ja.po/.mo` を更新し、繁体字中国語・日本語翻訳を拡充しました。

---

## [2.15.0] - 2026-05-08

### 🔒 セキュリティと濫用対策の強化

- **公開 AI REST エンドポイントに Session Token 防護を追加**：匿名訪問者向けの IP-bound session token を追加しました。`/chat/context`、`/chat/greet`、`/chat/user`、`/chat/user-stream` は有効な token を要求し、外部から直接 API を叩かれて AI quota を消費されるリスクを低減します。
- **Session Token を lazy-fetch 化**：フロントエンドは token を HTML に直接埋め込まず、初回の保護対象リクエスト前に `/session-token` から取得します。`no-store` cache header も付与し、full-page cache 経由で最初の訪問者の token が漏れることを避けます。
- **フロントエンド request の token 注入を統一**：`mpuFetch` と `mpuFetchSSE` が `X-MPU-Session-Token` を自動付与するようになり、helper の存在確認も追加して通常 REST と SSE の挙動を揃えました。
- **Raw JavaScript 拡張の権限を厳格化**：`extend[js_area]` の保存と表示には `unfiltered_html` 権限を要求するようにしました。`manage_options` のみでは raw frontend JavaScript を出力すべきでない環境での誤用を防ぎます。
- **Personality ZIP 上書きフローを強化**：ZIP は一度一時ディレクトリへ展開し、上書き前の確認、backup→rename→rollback、予約 ID の大小文字非依存チェック、`realpath()` + `DIRECTORY_SEPARATOR` 境界チェックを追加しました。Zip Slip、symlink escape、誤上書きのリスクを下げます。

### 🧱 構造整理と保守性改善

- **Chat history/checksum ロジックを抽出**：`MPU_Chat_History_Service` を追加し、session id、history parsing、checksum verify/store、通常 chat / streaming 応答後の integrity 更新を集中管理するようにしました。
- **Admin save handler を分割**：`mpu_handle_options_save()` から通用設定、キャラクター設定、AI、LLM、日記、Bot Blocker の保存 helper を分離し、大きな分岐を持つ単一 handler の保守コストを下げました。
- **既存 REST 挙動を維持**：REST route、error code、SSE event 名、response shape、checksum behavior は後方互換を維持しました。今回のリリースは大規模リファクタではなく、小さな段階的 hardening を目的としています。

### ✅ 検証とドキュメント

- **JS bundle を再ビルド**：`js/dist/ukagaka-bundle.js` と `js/dist/ukagaka-bundle.min.js` を更新しました。
- **構文と build を検証**：PHP lint と Node build の検証を通過しました。
- **Hardening plan を追加**：`plan/Code_Quality_Hardening_Plan.md` を追加し、セキュリティ評価、段階的な改善順、実装概要、post-review fixes を記録しました。

---

## [2.14.1] - 2026-04-30

### ✨ 管理人プロフィールのバックエンド管理化

- **管理画面に入力欄を追加**：**通用設定** ページに「Admin full nickname」「Admin short name」「Admin birthday」の 3 つの入力欄を追加（`options_general.php`）。管理人のニックネーム・短縮名・誕生日を WordPress 管理画面から直接設定できるようになり、`personality.md` や `calendar.json` を手動で編集する必要がなくなりました。
- **System Prompt への自動注入**：`core-functions.php` に `mpu_get_admin_profile_prompt_block()` を追加。System Prompt レンダリング時に `{{admin_nickname}}`・`{{admin_name}}`・`{{admin_birthday}}` を管理画面の設定値で自動置換し、オーバーライド指示ブロックを付加します。
- **calendar.json の動的オーバーライド**：`personality-prompts.php` にロジックを追加。管理画面で誕生日が設定されている場合、`calendar.json` の `"MM-DD"` プレースホルダーを自動的に削除し、実際の誕生日で置き換えます。ファイルの手動編集は不要です。
- **誕生日フォーマット検証**：`admin-functions.php` に `mpu_normalize_admin_birthday()` を追加。保存時に `MM-DD` フォーマットを検証・正規化します。

### 🎌 祝日期間（Holiday Periods）サポート

- **holiday_periods メカニズムを追加**：`calendar.json` で特定期間の祝日定義をサポート。システムは現在の日付が指定期間内かどうかを自動判定し、対応する祝日反応をトリガーします。
- **内蔵祝日期間**：
  - **ゴールデンウィーク**（4/29–5/5）：GW 期間中にキャラクターが専用の祝日反応を表示
  - **お正月**（1/1–1/7）：正月期間中にキャラクターが専用の祝日反応を表示
- **Prompt と Weight の対応**：`prompts.json` と `weights.json` に祝日期間用の prompt カテゴリとウェイト設定を追加。

### 📖 ドキュメント更新

- **USER_GUIDE に Abilities 説明を追加**：3 言語（繁体字中国語・英語・日本語）の USER_GUIDE のインタラクティブチャットモードセクションに「アビリティ」サブセクションを追加。内蔵 6 アビリティ（人気記事照会・IP BAN・Bot Blocker 統計/クリア・AI クローラー検出・訪問者パルス）の使用例と必要プラグイン一覧を記載。
- **README 更新**：3 言語版の README で管理人設定の説明を更新、バックエンド設定方式に変更。
- **スクリーンショット追加**：`screenshot7.PNG` — アビリティ機能のデモ（キャラクターが Bot Blocker 統計をキャラクターらしく報告）。

---

## [2.14.0] - 2026-04-29

### LLM ストリーミング対応拡張：Gemini / Claude

- **Gemini streaming を追加**：`gemini` provider に `generate_chat_stream()` を追加し、`streamGenerateContent?alt=sse` エンドポイントを使用するようにしました。通常のテキスト応答は SSE の `delta` としてフロントエンドへ順次送信されます。
- **Claude streaming を追加**：`claude` provider に `generate_chat_stream()` を追加し、Anthropic Messages API の `content_block_start`、`content_block_delta`、`content_block_stop` を処理できるようにしました。tool call の `input_json_delta` もバッファして復元します。
- **tool call の安全性を維持**：Claude で MCP tools が利用可能な場合、本ターンが tool call なしと確定するまでテキストをバッファします。tool call が発生した場合は pre-tool text を出力せず、フロント表示・バックエンド checksum・最終 tool 結果の不整合を防ぎます。
- **Gemini tools fallback**：Gemini streaming はまず純テキスト応答を対象とします。MCP tools が利用可能な場合は同期版 `generate_chat()` に fallback し、結果を単一の `delta` として返すことで、streaming mode で tool 機能が静かに無効化されることを防ぎます。

### Streaming の安定性とフロントエンド表示

- **共通 SSE parser を追加**：`mpu_stream_sse_events()` を追加し、chunk 境界をまたぐ行結合、複数行 `data:` の結合、空行による dispatch、末尾に空行がない stream の最後の event flush を共通処理化しました。
- **Claude tool loop 保護**：Claude streaming で `MPU_MAX_TOOL_TURNS` を使い切った場合、空の成功応答ではなく `max_turns_exceeded` を返すようにしました。
- **Claude tool 引数の保護**：streaming された Claude tool input の JSON decode に失敗した場合は debug log に記録し、空引数として tool 側に渡してエラー結果を返せるようにしました。不完全な引数で実行されることを避けます。
- **フロントエンドの streaming typewriter queue**：会話モードの streaming 表示を局所 timer と pending queue に変更し、管理画面の `typewriter_speed` 設定に従うようにしました。重複 finalize を防ぐ guard も追加しています。
- **Bundle を再ビルド**：`js/dist/ukagaka-bundle.js` と `js/dist/ukagaka-bundle.min.js` を更新しました。

---

## [2.13.9] - 2026-04-29

### 🧹 デッドコード削除（フェーズ 1 & 2）

PHP と JS ソース全体でランタイム呼び出し元がゼロと確認された孤立関数をすべて削除。動作変更なし。

**PHP 削除（8 ファイルにわたる計 21 関数）：**

- `utility-functions.php`：`mpu_enforce_rate_limit`、`mpu_verify_ajax_nonce`、`mpu_input_filter`
- `ai-functions.php`：`mpu_call_gemini_api`、`mpu_call_openai_api`、`mpu_call_claude_api`、`mpu_call_ollama_api`、`mpu_get_allowed_conditional_tags`
- `chat-api-handlers.php`：`mpu_call_ollama_with_messages`、`mpu_call_openai_with_messages`、`mpu_call_claude_with_messages`、`mpu_call_gemini_with_messages`（統合エントリ `mpu_call_ai_api_with_messages` は保持）
- `llm-context-builder.php`：`mpu_compress_context_info`、`mpu_get_context_label`（存在しない `mpu_load_personality_dynamics` に依存）
- `llm-functions.php`：`mpu_get_ollama_settings`、`mpu_debug_system_prompt`
- `personality-prompts.php`：`mpu_get_dynamic_prompt_templates`
- `personality-decorations.php`：`mpu_get_personality_all_decorations`
- `personality-loader.php`：`mpu_is_frieren_personality`、`mpu_get_personality_trait`
- `weather-functions.php`：`mpu_get_weather_info`

**JS 削除（3 ソースファイルにわたる計 4 関数）：**

- `ukagaka-base.js`：`mpu_init_visit_tracking`
- `ukagaka-core.js`：`mpu_hideMsgText`、`mpuMoe`
- `ukagaka-chat.js`：`mpu_escapeHTML`

**ドキュメント更新：** 3 言語セット（EN / TW / JP）の API_REFERENCE と DEVELOPER_GUIDE を同期更新済み。

---

## [2.13.8] - 2026-04-27

### ✨ 新機能：訪問者脈動と AI クローラーシグナル

- **AI クローラー検出を追加**：`llm-slimstat.php` に AI crawler のシグネチャ表と検索関数を追加し、Slimstat の bot 記録から GPTBot、ClaudeBot、Google-Extended、PerplexityBot などの AI / LLM crawler を識別できるようになりました。
- **Visitor Pulse シグナルを追加**：Slimstat をデータ源とする 3 種類のサイトシグナルを追加しました。
  - `foreign_visitor`：直近 60 分で初めて現れた国
  - `traffic_spike`：前の 1 時間と比べて人間の訪問者が大きく増加
  - `late_night_visitor`：深夜帯にも人間の訪問者がいる状態
- **auto-talk 用イベント分岐を追加**：`/check-spam-event` REST handler に `ai_crawler_alert` と `visitor_pulse_alert` を追加し、Frieren がこれらのサイトシグナルへ自発的に反応できるようにしました。
- **Abilities tool を追加**：`mp-ukagaka/get-recent-ai-crawlers` と `mp-ukagaka/get-visitor-pulse` の 2 つの read-only tool を追加し、LLM が最近の crawler 活動と visitor pulse 要約を自発的に参照できるようにしました。

### 💤 人格整合性の修正：Sleep mode とイベント push の統合

- **睡眠時の寝言フォールバック**：共通 helper `mpu_pick_sleep_dream_line()` を追加しました。キャラクターが deep sleep window（デフォルト `00:00–06:00`、oversleep により延長可）にいる場合、新イベント反応は LLM を呼ばず、`sleep_mode.json` の寝言を返します。
- **`visitor_dreams` を追加**：`ghost/Frieren/sleep_mode.json` に visitor pulse 専用の寝言プールを追加しました。AI crawler は sleep mode 中の低優先度イベントとして、反応をスキップします。
- **sleep mode との衝突を解消**：深夜訪問者イベントが、設定上は寝ている Frieren に覚醒した分析台詞を言わせてしまう問題を修正しました。

### 🔒 セキュリティと安定性の修正

- **foreign visitor の重複記録タイミングを修正**：`mpu_detect_visitor_pulse_event()` を純粋な問い合わせ関数に変更し、検出段階では seen countries を書き込まないようにしました。`mpu_visitor_pulse_commit_seen_countries()` により、メッセージ生成成功後のみ commit されます。これにより cooldown 中に新しい国が消費される問題を防ぎます。
- **Abilities の権限を強化**：2 つの新 abilities の `permission_callback` を `current_user_can('manage_options')` に変更し、Core Abilities API の REST endpoint 経由で訪問者情報や crawler 情報が未認可で読まれるのを防ぎました。
- **IP spoofing 防御を強化**：`mpu_get_client_ip_strict()` を追加し、rate limit と `/visitor-info` の Slimstat 逆引きを厳格な IP 判定へ切り替えました。これにより、偽造された `X-Forwarded-For` / `CF-Connecting-IP` header による LLM endpoint の quota 回避や、任意 IP の訪問者情報探索を防ぎます。
- **無効な weight 設定を削除**：`weights.json` では有効な `ai_crawler_reactions` / `visitor_pulse_reactions` のカテゴリ重みと `is_bot` 調整のみを残し、機能していなかった `is_foreign_visitor` / `is_late_night` context 設定を削除しました。
- **フロントエンドのイベント分流を補完**：`ukagaka-core.js` に `ai_crawler_alert`、`visitor_pulse_alert`、`spam_alert` の明示的な log 分岐を追加し、新イベントが Akismet spam と誤表示されないようにしました。

---

## [2.13.7] - 2026-04-25

### 🐛 バグ修正：Akismet 5.7 互換性 — AI ダイアログが生成されない問題

- **根本原因**：Akismet 5.7 が WordPress Abilities API に `akismet/get-stats` ability を追加しました。この ability の input schema には JSON Schema のユニオン型 `type: ['object', 'null']` が使われています。mp-ukagaka は `wp_get_abilities()` で登録済みの ability をすべて収集し、ツール定義として LLM プロバイダーに送信しますが、このユニオン型配列により API 呼び出し全体が拒否されていました。Gemini・OpenAI・Claude はいずれも `type` に配列ではなく文字列（例：`"object"`）を要求します。
- **症状**：Akismet を有効化すると AI ダイアログが一切生成されなくなり、キャラクターが内蔵の静的ダイアログにフォールバックしていました。Akismet を無効化すると即座に AI ダイアログが復元されました。
- **修正**：`abilities-integration.php` に `mpu_normalize_schema_for_llm()` を追加しました。この関数は ability の input schema を再帰的に走査し、ユニオン型配列（例：`['object', 'null']`）を単一の文字列（最初の非 null 型）に変換してから、LLM プロバイダーに送信します。

### 🐛 バグ修正：SPA モードで gotop ボタンが動作しない問題

- **症状**：WordPress テーマで SPA（Single Page Application）モードを有効にしている場合、伺か dock 上の「トップへ戻る」（gotop）ボタンのクリックイベントが SPA ルーターに横取りされ、ボタンが正常に動作しませんでした。
- **修正**：`frontend-functions.php` の `#toTop` アンカーに `data-spa-ignore` 属性を追加し、`ukagaka-features.js` のクリックハンドラに `e.stopPropagation()` を追加して、SPA フレームワークによるアンカークリックの横取りを防ぎます。

---

## [2.13.6] - 2026-04-24

### 📖 ドキュメント更新：開発者向け文書の補完と多言語同期

- **昨日反映しきれていなかった開発者向け文書を補完**：`API_REFERENCE.md`、`DEVELOPER_GUIDE.md`、`CANVAS_CUSTOMIZATION.md`、`DEBUG_SLIMSTAT.md`、`ABILITIES_API.md`、`GHOST_CREATE_GUIDE.md` を全面的に見直し、現在のコード構造と一致するよう更新しました。
- **REST / Abilities / Personality アーキテクチャに整合**：
  - 古い AJAX、旧 hooks、旧グローバル変数、廃止済みモジュール説明を、現在の REST controller ベース構成に更新しました。
  - 対外的な Abilities API の概念と、実装内部に一部残っている MCP 由来の命名との違いを明確化しました。
  - ゴースト作成ガイドを `instructions.md + personality.md` を主構成とする内容に改め、`system_prompt.md` / `manifest.json.system_prompt` は legacy fallback として整理しました。
- **現在サポートされている personality / frontend 構成を追記**：
  - `touchzones.json`、`sleep_mode.json`、`calendar.json`、`diary.json`、`emoji-keywords.json`、`scripts` など、現行構成の説明を追加しました。
  - canvas 装飾システム、Slimstat / visitor-info デバッグ手順、初期化データ、フロントエンド script 読み込み説明を更新しました。
- **3 言語同期完了**：繁体字中国語・英語・日本語の開発者向け文書と changelog が同期され、内容が一致しました。

---

## [2.13.5] - 2026-04-23

### 📖 ドキュメント：USER_GUIDE 全面再構成

- **三部構成への再編**：設定タブではなく使用用途を軸に、ユーザーガイドを以下の 3 つのセクションに再整理しました：
  1. **基本設定**（AI の有無に関わらず適用）：インストール、伺か管理、自動ダイアログ、カスタム表情システム など
  2. **AI 機能設定**（AI プロバイダーが必要）：LLM 設定、ページ感知、インタラクティブチャット、思考モード、天気感知、自動日記
  3. **静的ダイアログ機能**（AI を使用しない場合）：外部ダイアログファイル、会話設定、特殊コード、拡張機能
- **表情システムの説明を修正**：静的ダイアログと AI 生成ダイアログの両方で表情機能がサポートされることを明記し、第一部に移動しました（以前の配置が誤っていました）
- **各言語バージョンの内容を同期**：
  - 英語版（`docs-en/`）に天気感知機能と ZIP アップロードの説明を追加（以前は未掲載）
  - 日本語版（`docs-jp/`）に ZIP アップロードの説明を追加（以前は未掲載）
- **開発者向け技術詳細を削除**：35 の会話カテゴリ一覧、PHP 動的重みコード、思考モードの PHP スニペットなど、開発者向けの技術的な詳細をユーザーガイドから削除しました（これらは DEVELOPER_GUIDE に属します）

---

## [2.13.4] - 2026-04-21

### 🐛 バグ修正 (Bug Fix)

- **API キー欄のブラウザ自動入力問題**：LLM 設定ページの Gemini・OpenAI・Claude 3 つの API キー入力欄（`type="password"`）の `autocomplete="off"` を `autocomplete="new-password"` に変更しました。一部のブラウザが `autocomplete="off"` を無視して保存済みパスワードを注入し、プレースホルダーの代わりに `.......' が表示される問題を修正しました。
- **カスタムモデル入力欄へのユーザー名自動入力問題**：3 プロバイダーすべてのカスタムモデルテキスト入力欄に `autocomplete="off"` を追加しました。パスワードフィールドに隣接するテキスト入力をブラウザがユーザー名欄と誤認し、`admin` を自動入力していた問題を修正しました。
- **Gemini 2.5 Pro の thinking パート未処理**：Gemini 2.5 Pro は実際の返答テキストの前に、内部思考ブロック（`"thought": true`）を `parts` 配列に含めて返します。従来のコードは `parts[0].text` のみを確認していたため、先頭が thinking パートの場合に解析失敗していました。`generate_text`・`generate_chat`・`test_connection` の 3 箇所で、`thought: true` のパートをスキップして最初の通常テキストを取り出すよう修正しました。
- **Gemini 2.5 Pro のテスト接続で MAX_TOKENS 発生**：テスト接続リクエストの `maxOutputTokens` が `50` だったため、Gemini 2.5 Pro の内部 thinking（47 トークン）だけで使い切られ、実際の返答が出力されず `content.parts` が空になっていました。`200` に増やし解決しました。
- **Gemini 2.5 Pro プリセットの削除**：プリセットモデル一覧から `gemini-2.5-pro` を削除しました。thinking の必須オーバーヘッドにより現在のトークンバジェットでは安定動作しないためです。自訂モデル入力欄から引き続き利用可能です。

---

## [2.13.3] - 2026-04-20

### ✨ 機能強化：カスタムモデル選択 & Claude バージョン更新

- **カスタムモデル入力**：**LLM 設定**ページおよび**日記 AI 設定**ページの Gemini・OpenAI・Claude プロバイダーのモデル選択欄に「カスタムモデル…」オプションを追加しました。選択するとテキスト入力欄が表示され、プリセット一覧に縛られることなく任意のモデル ID を直接入力できます。保存方法はプリセット選択と同一であり、バックエンドの変更は不要です。
- **Claude モデルバージョンの更新**：Claude のプリセットモデル一覧を最新バージョンに更新しました：
  - Sonnet 4.5 → **Sonnet 4.6** (`claude-sonnet-4-6`)
  - Opus 4.5 → **Opus 4.7** (`claude-opus-4-7`)
  - Haiku 4.5 は変更なし（`claude-haiku-4-5-20251001`、4.6 版は現時点で未提供）

---

## [2.13.2] - 2026-04-16

### 🌐 ローカライズ：システムメッセージを日本語に統一

- **i18n 全面見直し**：18 個の PHP ファイルにわたり、ハードコードされた中国語（zh-TW）のシステムメッセージ（約 200 件以上）をすべて日本語に統一し、ユーザー向け出力の言語一貫性を確保しました。
  - `includes/core/`：ファイル操作・セキュリティ検証・レート制限エラー（`utility-functions.php`）；フロントエンド UI ラベルと `mpuL10n` ローカライズ文字列（`frontend-functions.php`）；ダイアログファイルエラー（`ukagaka-functions.php`）。
  - `includes/rest/`：すべての REST API エラー・ステータスメッセージ（`class-mpu-rest-base.php`, `class-mpu-rest-chat.php`, `class-mpu-rest-dialog.php`, `class-mpu-rest-ghost.php`, `class-mpu-rest-test.php`, `class-mpu-rest-touch.php`）。
  - `includes/llm/`：全 LLM プロバイダーのエラーメッセージ（`class-mpu-ai-provider-gemini.php`, `class-mpu-ai-provider-openai.php`, `class-mpu-ai-provider-claude.php`, `class-mpu-ai-provider-ollama.php`, `class-mpu-ai-provider-base.php`）；日記・Ollama バリデーション・ツールループメッセージ（`diary-functions.php`, `llm-functions.php`, `tool-loop-guard.php`）。
  - `includes/admin-functions.php`：全管理画面通知と ZIP アップロード/検証メッセージ。
- **`mpuL10n` の拡充**：フロントエンド JavaScript オブジェクトに新しいローカライズキーを追加（`loadingFailed`, `errorOccurred`, `duplicateRequest`, `requestFailed`, `securityVerificationFailed`, `animationLoadFailed`, `connectionError`, `chatExit`）。JS モジュールがハードコードされたフォールバック文字列に依存しなくなりました。
- すべての文字列は引き続き `__()` でラップされており、将来的な `.po`/`.mo` 翻訳パックに対応しています。

### 🐛 バグ修正 (Bug Fix)

- **`mpuL10n` キー名の不一致**：`js/ukagaka-dialog.js` が未定義の `mpuL10n.loadFailed` を参照していた問題（正しくは `mpuL10n.loadingFailed`）を修正。暗黙のフォールバックが発生していました。
- **`chatExit` キーの未定義**：`js/ukagaka-chat.js` が参照する `mpuL10n.chatExit` が `wp_localize_script` に登録されていなかった問題を修正。`frontend-functions.php` にキーを追加しました。

---

## [2.13.1] - 2026-03-18

### 🔒 セキュリティ強化 (Security)

- **コマンドアクセス制御**：`/debug_mcp`・`/reset`・`/clear` などの管理者専用コマンドが、非ログイン状態ではシステムメッセージを返さなくなりました。代わりに `dynamics.json` の `visitor_rejection` プロンプトに従い、キャラクターがそのキャラクターらしい口調で拒否します。MCP ツール拒否と一貫した体験を提供します。
  - バックエンド：非管理者の `/debug_mcp` リクエストは通常の AI パイプラインにフォールスルーするよう変更。非管理者向け system prompt にスラッシュコマンド拒否の指示を追記。
  - フロントエンド：`mpuPreSettings` に `is_admin` フラグを追加。`/reset`・`/clear` に管理者チェックを追加し、非管理者の場合は AI パスへフォールスルー。
- **IP スプーフィング対策**：`mpu_get_client_ip()`（レート制限）と `mpu_bb_get_ip()`（Bot Blocker 自動 BAN）にて、proxy ヘッダー（CF-Connecting-IP / X-Forwarded-For）の値に対してパブリック IP 検証（`FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE`）を追加。プライベート/予約済み IP を偽装してレート制限を回避したり、正規 IP に誤 BAN を引き起こす攻撃を防止します。

### 🐛 バグ修正 (Bug Fix)

- **`fail()` エラー情報の消失**：`class-mpu-rest-base.php` の `fail()` メソッドに省略可能な第 4 引数 `$data` を追加。Provider が返す `http_status`・`raw_body` などのデバッグ情報がサイレントに破棄されなくなり、REST レスポンスボディから直接確認できるようになりました。

---

## [2.13.0] - 2026-03-06

### 🧹 デッドコードのクリーンアップ (Dead Code Cleanup)

- **未使用の関数とファイルの削除**：長期間使用されておらず、フロントエンド/バックエンドからの呼び出しもない不要なコードを削除し、プラグインの軽量化と将来のメンテナンス性を確保しました。
  - `includes/rest/chat/` および `includes/ajax/chat/` ディレクトリ内の、最新の OO アーキテクチャに置き換えられた複数の古いファイルを削除しました。
  - `mpu_html_decode`、`mpu_is_browser`、`mpu_should_trigger_ai`、`mpu_ukagaka_list`、`mpu_get_default_msg`、`mpu_get_next_msg`、`mpu_get_msg_key`、`mpu_count_msg` などの時代遅れの関数を削除し、同時に開発者向けガイドからそれらの参照を削除しました。

---

## [2.12.5] - 2026-02-28

### 🚀 メジャーアップデート：統一歴史記憶と SSE 安定性の強化

- **統一歴史記憶プラン (Unified History)**：フリーレンがすべてのインタラクション（自言自語、ページ感知、タッチ反応など）を記憶できるようになりました。「Synthetic User アンカー」技術の実装により、非対話型の応答も完全に対話コンテキストに保持され、LLM のロールプレイの中斷を解決しました。
- **SSE ストリーミングの安定性修正**：SSE と Checksum の間で発生していた多くの 400 エラーを修正しました。
  - **観測モード**：Checksum を硬性ブロックから監査観測モードに変更し、長時間の会話や不安定なネットワーク下での体験を向上。
  - **確認用ログ**：`logs/checksum-mismatch.log` を新設し、前後端のデータ差異を自動記録、精確なトラブルシューティングを可能にしました。
  - **データ同期の最適化**：store と verify 両端の slice 順序が不一致だった問題を修正し、整合性を確保。
- **フロントエンドアーキテクチャの転換**：
  - **グローバル変数への移行**：`mpuChatHistory` や `mpuChatModeActive` などのコアステートを `window` オブジェクトに移行し、モジュール間アクセスの安定性を向上。
  - **メモリ容量の倍増**：対話履歴の上限を 20 から 40 エントリに引き上げ、すべてのインタラクションイベントを網羅。
  - **ライフサイクル管理**：F5 リロードを検知してメモリとセッションをリセットしつつ、SPA 内部遷移時はメモリを維持する機能を実装。
- **UX 個別最適化**：
  - `/reset` コマンドの応答を調整し、キャラクターの口調の一貫性を維持。
  - `mpuFetchSSE` の例外処理を改善し、JSON エラー発生時の自動フォールバック (Fallback) メカニズムを追加。

---

### 🚀 メジャーアップデート：SSE ストリーミングとタイプライター効果

- **サーバー送信イベント (SSE) ストリーミング**: `text/event-stream` プロトコルを全面的に導入し、リアルタイム出力を実現。AI の応答が「タイプライター効果」で即座に表示されるようになり、長文回答や思考型モデル（Ollama など）の待機体験が大幅に向上しました。
- **専用ストリーミングエンドポイント**: 新しい REST ルート `/chat/user-stream` を追加。従来の同期パスと同様のセキュリティ、レート制限、対話整合性チェックを維持しつつ、ストリーミングに対応しました。
- **標準化された SSE プロトコル**: プラグイン独自のイベントモデル（`delta`, `status`, `nonce`, `done`, `error`）を設計。フロントエンドでトークンの増分、ツールの実行、セキュリティリフレッシュを正確に制御可能になりました。
- **プロバイダーのストリーミング対応**:
  - **OpenAI**: 複雑な `tool_calls` の断片的な組み立てを含む、フルストリーミングをサポート。
  - **Ollama**: JSON Lines ストリーミングの転送をサポートし、思考タグのフィルタリングも統合しました。
- **接続の安定性と保護**: `ignore_user_abort` と cURL ストリーミングコールバックを統合。ユーザーがチャットを閉じた際や切断時に、上流の API リクエストを能動的に中断し、リソースの浪費と「ゴーストレスポンス」を防止します。

### 🏗️ アーキテクチャの高度な最適化 (Architecture Upgrades)

- **AI プロバイダーファクトリーパターン (Factory Pattern)**: 第 2 段階のリファクタリングが完了。すべてのプロバイダーロジックを `MPU_AI_Provider_Factory` に集約し、拡張性を向上させました。
- **ツール呼び出しループ検出 (Loop Detection)**: ループ署名検出システムを正式実装。同一ツールとパラメータの無限ループを自動的に遮斷し、API コストを保護します。

---

## [2.10.0] - 2026-02-26

### 🚀 メジャーアップデート：AI プロバイダーファクトリーと安定性の強化

- **AI プロバイダーファクトリーパターン (Factory Pattern)**: `ai-functions.php` と `chat-api-handlers.php` のプロバイダールーティングロジックを包括的にオブジェクト指向のファクトリーパターンにリファクタリング。`includes/llm/providers/` アーキテクチャと `MPU_AI_Provider_Factory` を導入し、DeepSeek のような新しいモデルの統合を容易にしました。
- **ツール呼び出しループ検出 (Tool Call Loop Detection)**: マルチターンチャット向けに堅牢なツール呼び出しループ保護メカニズム (`tool-loop-guard.php`) を実装。引数の JSON ハッシュ署名を利用し、LLM がまったく同じツールと同じパラメータを 2 回連続して要求した場合、システムは即座に実行を停止して `tool_call_loop_detected` を返します。これにより、無駄な API トークン消費を大幅に削減します。
- **API 互換性ラッパー (Thin Wrappers)**: 下位互換性を維持するため、既存の 8 つの API コールエントリ (例: `mpu_call_ai_api`) を、背後で新しいファクトリークラスを呼び出す「薄いラッパー」に変換しました。
- **安定性の最適化**: `handle_api_error` のパラメータバグを修正。プロバイダー呼び出しの防御用スラッグ (Slug) 正規化メカニズムを追加し、Gemini デフォルトモデルの処理を最新シリーズに更新しました。

---

## [2.9.2] - 2026-02-25

### 🚀 メジャーアップデート：REST OO ルーティングとアーキテクチャ再構築

- **オブジェクト指向ルーティング (Object-Oriented Routing)**: 19 の REST API ルートを全面的にオブジェクト指向アーキテクチャにリファクタリング。`MPU_REST_Base` 基底クラスを導入し、ドメインごとに 5 つの専用コントローラー（`Chat`, `Dialog`, `Ghost`, `Test`, `Touch`）に分割。`bootstrap.php` で一元的に登録することで、ルーティングの保守性を大幅に向上させました。
- **Provider 共通 Helper の抽出**: LLM プロバイダーの共通ロジック（安全な JSON エンコーディング、ツール結果のフォーマット変換など）を `provider-helpers.php` に抽出し、コードの重複を排除。今後の実装予定であるファクトリーパターン (Factory Pattern) のための強固な基盤を構築しました。
- **ツール呼び出しループ保護の強化**: `includes/llm/ai-functions.php` およびマルチターンチャットにおけるツールの実行保護を強化。ツールの最大呼び出し回数を定数化 (`MPU_MAX_TOOL_TURNS`) し、モデルが無限ツール呼び出しループに陥るのを防止しました。
- **デッドコードのクリーンアップ**: 非推奨となった手続き型の REST ファイル (`rest-init.php`, `rest-core.php` など) や旧式のレガシー AJAX ハンドラーを完全に削除し、エレガントでモダンなルーティング構造を全面的に採用しました。

---

## [2.9.1] - 2026-02-24

### 🛡️ セキュリティと安定性の強化

- **REST API Nonce の自動更新**: `rest_post_dispatch` 経由で古くなった Nonce (12〜24時間) を自動更新し、長時間のブラウザセッションで発生する 403 エラーを完全に解決しました。
- **フィールドタイプを認識するサニタイズ**: バックエンド設定のサニタイズロジックを最適化。プレーンテキスト、HTML、ブール値を区別し、システムプロンプトなど HTML が許可されたフィールドの過剰なサニタイズを防止しました。
- **UTF-8 セーフな JSON エンコーディング**: API リクエストに `JSON_INVALID_UTF8_SUBSTITUTE` を追加し、不正な UTF-8 文字によって引き起こされるサイレントエラーを防止しました。
- **未マッチプレースホルダーの安全なクリーンアップ**: LLM に送信する前にプロンプト内の未マッチの `{{variable}}` タグを自動的に削除し、元のテンプレート構文による LLM の混乱を防止しました。

### ✨ 新機能と改善

- **Cron ヘルスステータス追跡**: トランジェントを介して Cron スケジュールの実行時間とエラーを記録し、管理画面の日記設定ページに詳細なステータスパネルを追加しました。自動日記機能の動作状況や失敗の理由を明確に把握できるようになります。

---

## [2.9.0] - 2026-02-23

### 🚀 メジャーアップデート：REST API 完全移行 (REST API Migration)

- **バックエンドアーキテクチャの再構築**: すべての AJAX エンドポイントを従来の `admin-ajax.php` からモダンな **WordPress REST API** (`wp-json/mp-ukagaka/v1/`) に全面的に移行しました。
- **モジュール化ルーティング**: 単一の AJAX ハンドラを複数のモジュール化された REST コントローラ（`rest-init.php`, `rest-core.php`, `rest-chat.php`, `rest-touch.php` など）に分割し、コードの保守性と可読性を大幅に向上させました。
- **セキュリティのアップグレード**:
  - 認証リクエストにはネイティブな `X-WP-Nonce` ヘッダーと `permission_callback` を組み合わせて使用するように変更しました。
  - 不正な状態変更を防ぐため、`GET` (読み取り専用) と `POST` (状態変更) メソッドを厳格に割り当てました。
- **エラー処理の標準化**: フロントエンドでのエラー解析をより正確にするため、`WP_Error` とネイティブの HTTP ステータスコード (400, 401, 403, 429) によるレスポンスを全面的に採用しました。
- **レート制限 (Rate Limit) の最適化**: REST 専用のレート制限メカニズム (`mpu_rest_check_rate_limit`) を追加し、上限超過時には正しい HTTP 429 および `Retry-After` ヘッダーを返すようにしました。
- **Cookie 処理の修正**: REST コンテキスト内で直接 `setcookie()` を呼び出す際の問題を修正し、`WP_REST_Response::header` を介して安全に Cookie を発行するように改善しました。
- **フロントエンドのリファクタリング**: すべての JavaScript リクエスト（`mpuFetch`）を `admin-ajax.php` から切り離し、REST API との通信に統一しました。また、5xx などのエラーに対するネットワークの再試行ロジックを最適化しました。

## [2.8.3] - 2026-02-21

### 🚀 パフォーマンス最適化とリファクタリング

- **PHP バックエンドのパフォーマンスと構造の最適化**:
  - **O(1) テーブルルックアップ最適化**: `personality-loader.php` に静的な逆引きマップ (`static $name_map`) を実装し、配列走査の O(n) の複雑さを O(1) の `isset()` 検索に改善しました。
  - **リクエストレベルのキャッシュ**: 同一 URL に対する高コストな `url_to_postid()` クエリの繰り返しを防ぐため、`llm-slimstat.php` に `$post_id_cache` 配列を実装しました。
  - **静的リソースキャッシュの統合**: `prompt-categories.php` のキャッシュを最適化し、`$personality_id ?? '__default__'` を使用することで、すべての伺か（Ukagaka）人格がリクエスト間で静的キャッシュの恩恵を受けられるようにしました。
  - **文字列処理の改善**: `mpu_normalize_for_similarity()` を抽出し、ループ前に正規化を実行することで `preg_replace` の重複を回避しました。また、`personality-prompts.php` で同一文字列に対する複数回の天候 Regex (`preg_match`) 操作を 1 回の効率的なマッチングに統合しました。
  - **ループの統合**: `user-chat-handler.php` の 4 つの独立した `foreach` 反復を、可変変数 (`$$flag`) を使って 1 つのループにまとめ、重複コードを大幅に削減しました。

- **JS フロントエンドのパフォーマンス最適化**:
  - **O(n²) → O(n)**: `ukagaka-context.js` および `ukagaka-greeting.js` 内の、配列を絶えずシフトさせる `splice` ループを廃止し、カウンターを用いた `filter()` 処理に書き換えました。これにより、長いチャット履歴配列を処理する際の計算ボトルネックが大幅に解消されました。
  - **jQuery セレクターのキャッシュ**: DOM のクエリ回数を減らすため、`ukagaka-core.js` の `mpu_nextmsg` と fallback 処理内に `const $msgnum` のキャッシュを追加しました。

### 🛡️ セキュリティと安定性の強化

- **ZIP 爆弾対策 (ZIP Bomb Mitigation)**: 極端に多くの小ファイルを含む ZIP ファイルのアップロードによるサーバーのメモリ枯渇 (DoS) を防ぐため、`admin-functions.php` の処理フローにファイル上限 (`1000` 件) の早期拒否メカニズムを導入しました。
- **安全な乱数生成器**: セキュリティ向上のため、古い `mt_rand()` をすべて WordPress 標準のより安全な `wp_rand()` に置き換えました。
- **致命的エラー (Fatal Error) の防止**: `mpu_recursive_rmdir` 関数をグローバルスコープに抽出し、ネストによるインクルード競合で発生する可能性のある致命的エラーを排除しました。

### 🔧 コードのクリーンアップと共通化

- **重複コードの大幅な削減**: 分散したロジックを抽出し、以下の通り独立したユーティリティ関数として統一しました（コード数百行を削減）:
  - PHP: `mpu_verify_ajax_nonce()`, `mpu_get_current_provider()`, `mpu_get_provider_api_key()`, `mpu_build_user_info_prompt()`。
  - JS: `mpu_isDeepSleepTime()`, `mpu_selectNextMessage()`, 一元化された `_isDebug()` 条件。
- **Ollama 思考モデル (Thinking Model) 検出**: 長くてメンテナンスしづらい `strpos(strtolower())` チェーンから、柔軟な `array_filter` + `stripos` メカニズムにアップグレードしました。
- **ディレクトリスキャンの簡素化**: `personality-loader.php` で冗長な `scandir()` ループを `glob()` に置き換え、単調な `.` / `..` のフィルタリングや `file_exists` チェック処理を省略し、ロジックをスッキリさせました。

## [2.8.2] - 2026-02-16

- 追加：ISO 3166-1 国コードを完全な国名に変換する `mpu_country_code_to_name` ユーティリティ関数を追加（PHP intl 拡張を優先使用）。
- 改善：LLM コンテキスト（挨拶、チャットコンテキスト、プロンプト変数）において、訪問者の国コードを完全な名前（例："JP" -> "日本"）に変換し、AI の応答の自然さを向上させました。

## [2.8.1] - 2026-02-16

### 📝 System Prompt 読み込みメカニズムのリファクタリング

- **読み込みロジックの統一**：すべての AJAX ハンドラー（`mpu_ajax_chat_context`, `mpu_ajax_user_chat`, `mpu_ajax_touch_zone_chat`, `mpu_ajax_decoration_chat`）における System Prompt 読み込み方法を統一し、一貫性を確保しました。
- **モジュラー Personality ファイルのサポート**：
  - `system_prompt.md` を `personality.md`（キャラクター背景）と `instructions.md`（行動指針）に分割する機能をサポートしました。
  - より柔軟なキャラクター設定管理が可能になります。
- **UI ソースインジケーター**：
  - 管理画面の AI 設定ページの System Prompt エリアにソースインジケーターを追加しました。
  - 現在使用されているのがモジュラーファイル、レガシーファイル、Manifest 設定、またはバックエンド Textarea のいずれであるかを明確に表示します。

### 🐛 バグ修正

- **タッチ反応 System Prompt の修正**：`ajax-touch-handlers-llm.php` 内の関数名の誤りを修正し、タッチや装飾品インタラクション時にキャラクター固有の System Prompt が正しく読み込まれない問題を解決しました。

## [2.8.0] - 2026-02-15

### 🚀 メジャーアップデート：Abilities API (ツール呼び出し)

- **コア統合**：WordPress Core Abilities API を統合し、AI キャラクターにバックエンド操作を実行する能力を付与しました。
  - 現在 `get_popular_posts` ツールが実装されており、AI はサイトの人気記事を照会できます。
  - **権限管理**：ツール実行は管理者権限 (`manage_options`) を持つユーザーのみに厳格に制限されます。
  - **訪問者最適化**：非管理者の訪問者に対しては、システムが自動的にツール定義をフィルタリングし、トークンを節約してセキュリティを向上させます。

- **キャラクターによる拒絶応答**：
  - 訪問者が特権操作（データ照会など）を要求した場合、AI はエラーを出さずにキャラクターの口調で拒否します。
  - `dynamics.json` に `visitor_rejection` 行動指針を追加し、多様でキャラクター設定に合った拒絶を保証します（例：フリーレンなら「私と管理人との秘密だよ」と言うかもしれません）。

### 🔒 セキュリティ強化

- **グローバル Nonce 検証**：すべてのフロントエンド AJAX リクエストのセキュリティを強化しました。
  - `mpu_nextmsg`、`mpu_change`、`mpu_get_settings`、`mpu_extend`、`mpu_load_dialog` リクエストの `mpu_nonce` 欠落問題を修正しました。
  - バックエンドとのすべての対話で厳格な Nonce 検証が行われ、CSRF 攻撃を防止します。

- **トークン節約と最適化**：
  - ツールを使用できない訪問者に対して、システムは大量のツール説明を LLM に送信しないようにし、トークン消費を大幅に削減します。
  - LLM がツールを呼び出そうとしてバックエンドにブロックされる無効な対話ループを防止します。

## [2.7.0] - 2026-02-12

### 🛡️ 外部プラグイン連携 (Plugin Integrations)

- **Akismet + Turnstile 統合**：Akismet または Turnstile プラグインがインストールされている場合、それらがブロック動作を実行した際に対応する反応対話をトリガーします。
  - **クールダウン機能**：独立した反応クールダウン（30 分）を実装し、対話が頻繁になりすぎるのを防止。

### 🤖 BOT 検知機能

- **リアルタイムロボット検知**：検索エンジンクローラーや悪意のあるボットの訪問を検知。
  - 人格システム（`bot_detection`）と深く統合し、専用の警告ダイアログをトリガー。
  - Slimstat データに基づき、より精度の高い検知ロジックを提供。

### 🔧 最適化と調整

- **自動対話優先順位の最適化**：スパムおよび BOT 検知イベントの優先順位処理を最適化。
- **LLM プロンプトの拡張**：`dynamics.json` および `prompts.json` にスパムと BOT 検知専用のプロンプトを追加。

---

### 🚀 パフォーマンス最適化

- **フロントエンド JS バンドル・圧縮**: 7 つの JS ファイルを単一バンドルに統合
  - HTTP リクエストを 87.5% 削減（8→1）
  - ファイルサイズを 64.5% 削減（160KB→60KB）
  - Terser を使用してミニファイ
  - `SCRIPT_DEBUG` で開発モード切り替えに対応
  - `npm run build` コマンドを追加

### ✨ 新機能

- **API キャッシュシステム**：重複 API リクエストとコストを削減
  - WordPress Transient API を使用して実装
  - 設定可能な TTL（30分〜24時間）
  - 管理画面設定 UI（LLM 設定ページ）
  - キャッシュ統計表示とワンクリッククリア機能
- **自動日記機能**：AI が自動的に日記スタイルの記事を生成
  - 最近の閲覧データに基づいてコンテンツを生成
  - カスタムタイトル接頭辞と公開設定をサポート
  - パーソナリティシステムの日記プロンプトと統合

### 🔧 コードリファクタリング

- **AJAX Chat Handler のモジュール化**：`ajax-chat-handlers-llm.php` を分割
  - `context-handler.php`：ページ感知対話
  - `greet-handler.php`：初訪問者への挨拶
  - `user-chat-handler.php`：インタラクティブチャット
  - 元ファイルは 1036 行から 18 行のローダーに簡素化
- **フォームハンドラーアーキテクチャ統一**：分散したハンドラーを `admin-functions.php` に統合
  - LLM 設定と日記設定ロジックを `mpu_handle_options_save()` に統合
  - シングルエントリーポイント、WordPress ベストプラクティスに準拠

### 📝 ドキュメント更新

- `DEVELOPER_GUIDE.md` のディレクトリ構造を更新
- API キャッシュシステムの説明を追加

---

## [2.5.2] - 2026-01-11

### ✨ 新機能と改善

- **天気感知機能**：キャラクターが Open-Meteo API を使用して天気状況を感知できるようになりました
  - 無料の Open-Meteo API を使用、API Key 不要
  - 天気設定は管理画面で設定可能
  - 天気状況に応じた会話内容の調整に対応

- **睡眠機能**：睡眠モードを追加
  - 指定された時間帯にキャラクターが睡眠状態に入ります
  - 睡眠時間は `manifest.json` の `sleep_settings` で設定可能
  - 深い睡眠時間と起きられない（oversleep）設定に対応

- **フリーレン人格強化**：
  - デフォルトキャラクターのフリーレンの `system_prompt.md` を強化
  - `prompts.json` を拡張し、より多様な台詞を話せるようにしました
  - 会話の多様性とロールプレイ品質を向上

- **タッチインタラクション機能**：
  - タッチゾーン機能を追加
  - キャラクターは体の異なる部位（頭、顔、胸、脚など）へのタッチに反応できます
  - `touchzones.json` で設定
  - 各ゾーンで独立した反応会話を定義可能

- **絵文字の種類追加**：
  - より多くの絵文字種類を追加
  - キャラクター表現をより豊かにしました

- **新しい装飾品**：
  - フリーレンに2つの新しい装飾品を追加：「暗黒竜の角」と「服だけ溶かす薬」
  - 装飾品をクリックすると関連する会話がトリガーされます

- **コードリファクタリング**：
  - コード構造と保守性を改善
  - モジュール組織を最適化

---

## [2.4.0] - 2026-01-03

### 🚀 メジャーアップデート：JSON 人格システム (v2.4.0)

- **Personality システム**：JSON ベースのキャラクター設定システム
  - 新しい `ghost/` フォルダ（伺かの ghost フォルダと同様）、各キャラクターが独立した設定ファイルを持てるように
  - `manifest.json`（メタデータ）、`prompts.json`（静的会話カテゴリ）、`dynamics.json`（動態テンプレート）、`weights.json`（カテゴリ重み）、`decorations.json`（デコレーション設定）、`emoji-keywords.json`（表情キーワード）をサポート
  - 各キャラクターは専用の JavaScript ファイル（例：`frieren.js`）を含めることが可能
  - 従来の伺か SHIORI アーキテクチャと同様に、PHP コードを変更せずにキャラクター人格を定義可能

- **新モジュール**：
  - `personality-loader.php`：Personality システムローダー、JSON ファイル読み込みとキャッシュ機構を提供
  - `emoji-mapper.php`：表情マッピングと感情分析モジュール、会話内容に基づいて表情を自動選択

- **フロントエンド拡張**：
  - `ukagaka-chat.js`：チャット機能フロントエンド実装
  - `ghost/Frieren/frieren-emoji.js`：フリーレン専用表情システムフロントエンド（RO 風、フリーレン人格時のみ読み込み）

- **アーキテクチャ改善**：
  - `prompt-categories.php` が Personality システムと完全に統合
  - 動的プロンプト、重み設定、統計マッピングはすべて JSON ファイルから読み込み可能
  - 下位互換性：Personality システムが利用できない場合、自動的に旧動作にフォールバック

- **モジュール読み込み順序の最適化**：
  - `personality-loader.php` を `prompt-categories.php` の前に読み込み（必須）
  - `emoji-mapper.php` を AJAX ハンドラの前に読み込み（必須）

---

## [2.3.1] - 2025-12-30

### 🔧 改善とバグ修正

- **用語統一**：すべての「春菜」を「偽春菜」に変更
  - すべての PHP ファイルの UI テキスト、コメント、メッセージを更新
  - 中国語ドキュメント（USER_GUIDE.md、DEVELOPER_GUIDE.md、README.md）を更新
  - 日本語「伺か」用語との一貫性を確保

- **インタラクティブチャットモード改善**：
  - チャットモード終了後、自動対話は 5 秒の遅延後に再開
  - 会話終了直後にキャラクターが即座に話し始める違和感を回避

- **デコレーションクリックアニメーション**：
  - デコレーションクリック時に fade-out/fade-in アニメーションを追加
  - OK ボタンで次のダイアログを取得する際の視覚効果と一致
  - 全体的な UX の流暢さを向上

- **深夜モード**：
  - 深夜時間帯（02:00〜06:00）専用のダイアログコンテキストを追加
  - AI が深夜時間帯にダイアログスタイルと内容を調整

---

## [2.3.0] - 2025-12-27

### 🚀 メジャーアップデート：インタラクティブチャットモード

- **インタラクティブチャットモード**：「伺か変更」ボタンをリアルタイムチャットインターフェースに変換
  - 訪問者はキャラクターと直接チャット可能
  - 会話履歴を保持してコンテキストに基づいた応答を提供
  - スクロール可能な会話エリアで、長い会話は自動スクロール
  - 入力ボックスは下部に固定、メッセージは上部でスクロール

- **動的コンテキストインジェクション**：スマートトークン最適化
  - WordPress 統計情報（記事数、コメント数、PHP バージョン、プラグイン数など）は、ユーザークエリで関連キーワードが検出された場合にのみ System Prompt に追加
  - ほとんどの会話でトークン使用量を大幅に削減
  - 繁体字中国語、日本語、英語のキーワードをサポート
  - キーワード例：article、コメント、comment、php、wordpress、plugin、plugins、theme など

- **思考モード（デフォルトで有効）**：AI 応答品質の向上
  - **デフォルト動作変更**：対応モデル（Qwen3、DeepSeek）はデフォルトで思考モードが有効に
  - **独白モード**：`ai-functions.php` で `think = true` を設定、AI は考えてから応答
  - **会話モード**：`chat-api-handlers.php` でも思考を有効化、コンテキストウィンドウを 8192 トークンに拡大
  - **分離メカニズム**：思考プロセスと応答を完全に分離、ユーザーには応答のみ表示
  - **品質向上**：より正確な回答、特に会話モードで顕著
  - **動作の違い**：
    - **以前**：think = false、直接回答、高速だが精度が低い可能性、思考内容が応答に混入する可能性
    - **現在**：think = true、考えてから回答、より正確、思考と応答が分離、応答のみ表示
  - **対応モデル**：Qwen3（qwen3:8b など）、DeepSeek（deepseek など）などのカスタムモデル
  - **設定可能**：バックエンド LLM 設定で「思考モードを無効にする」オプションで無効化可能

- **キャラクター性格の一貫性**：ロールプレイの改善
  - System Prompt 変数レンダリングを修正（`{{ukagaka_display_name}}`）
  - デフォルト System Prompt でロールプレイを明示的に強調：「あなたはこのキャラクターとして完全に話し、行動する必要があります。AI や言語モデルとして応答しないでください」
  - チャットモードは独白モードと同じバックエンド System Prompt を使用し、一貫性を確保

- **コードリファクタリング**：より良い組織構造
  - `ajax-handlers.php` を `ajax-handlers.php` と `chat-api-handlers.php` に分割
  - マルチターン会話 API 関数（`mpu_call_ai_api_with_messages`、`mpu_call_ollama_with_messages` など）を専用モジュールに移動
  - コードの保守性と組織構造を改善
  - 過剰なコードコメントを削除し、コードベースをクリーンに保つ

- **応答長制御**：AI 応答の最適化
  - AI 応答トークン制限を 200 から 300 トークンに増加
  - すべての AI プロバイダー（Ollama、Gemini、OpenAI、Claude）に適用

- **UI 改善**：
  - チャット入力ボックスプレースホルダーを再設計：「ここをクリックして {{name}} とチャット...」
  - チャットバブルは異なる色を使用してユーザーとアシスタントを区別
  - ローディングアニメーションを追加（3 つの跳ねるドット）
  - スクロールバーの外観を改善

### 📁 ファイル構造更新

- **新規ファイル**：`includes/chat-api-handlers.php`
  - マルチターン会話（インタラクティブチャットモード）を処理する専用モジュール
  - `mpu_ajax_chat()` とすべての `*_with_messages()` 関数を含む

- **変更されたファイル**：
  - `includes/ajax-handlers.php`：簡素化、チャット関連機能を削除
  - `options/options_page_llm.php`：「インタラクティブチャットモードを有効にする」チェックボックスオプションを追加
  - `js/ukagaka-base.js`：ユーザーチャットインタラクションを処理する `mpuChat()` 関数を追加
  - `mpu_style.css`：チャットモードスタイルを追加（メッセージボックススクロール、入力ボックス、チャットバブル、ローディングアニメーション）

### 🧠 技術詳細

#### 動的コンテキストインジェクション

ユーザー入力に基づいて自動的に検出されるキーワード：

- **統計キーワード**：article、post、comment、コメント、category、tag、days、運営、stats など
- **システムキーワード**：php、wordpress、wp version など
- **プラグインキーワード**：plugin、プラグイン、外掛など
- **テーマキーワード**：theme、テーマ、主題、author など

**利点**：

- 通常の会話で 70% 以上のトークン消費を節約
- API コストを削減
- 応答時間の高速化

#### 思考モード

対応モデル（Qwen3、DeepSeek）の場合、システムは自動的に：

1. API リクエストで `think = true` を設定
2. コンテキストウィンドウを 8192 トークンに拡大（通常モード：4096）
3. 思考プロセスと応答内容を分離
4. ユーザーには応答部分のみを表示

**検出メカニズム**：

- モデル名に `qwen3`、`deepseek`、または `frieren` が含まれているかチェック
- バックエンド `ollama_disable_thinking` オプションで無効化可能
- 無効化時、ユーザープロンプトに `/no_think` 指示を追加

### 🐛 バグ修正

- System Prompt 変数レンダリング問題を修正
- マルチターン会話でチャット履歴が欠落する問題を修正
- 思考内容フィルタリングロジックを改善（複数の検出方法をサポート）
- 独白とチャットモード間のタイムコンテキストフォーマットを統一

### 📚 ドキュメント更新

- `docs/USER_GUIDE.md` を更新：インタラクティブチャットモードと思考モードの章を追加
- `docs/DEVELOPER_GUIDE.md` を更新：`chat-api-handlers.php` モジュールドキュメントを追加
- `docs/CANVAS_CUSTOMIZATION.md` を更新：フリーレン専用デコレーションシステムを追加
- すべての 3 つの README ファイルを更新：スクリーンショット参照を追加

---

## [2.2.0] - 2025-12-19

### 🚀 メジャーアップデート：汎用 LLM インターフェース

- **複数 AI プロバイダーサポート**：統一インターフェースで 4 つの AI サービスをサポート
  - **Ollama**：ローカル/リモート無料 LLM（API Key 不要）
  - **Google Gemini**：Gemini 2.5 Flash（推奨）、Gemini 1.5 Pro などをサポート
  - **OpenAI**：GPT-4.1 Mini（推奨）、GPT-4o などをサポート
  - **Claude (Anthropic)**：Claude Sonnet 4.5、Claude Haiku 4.5、Claude Opus 4.5 をサポート
  - すべてのプロバイダーで統一された設定インターフェースを使用、いつでも切り替え可能

- **API Key 暗号化保存**：すべての API Key を自動暗号化して保存、セキュリティを確保
- **接続テスト機能**：すべての AI プロバイダーに接続テストボタンを追加

### 🧠 System Prompt 最適化システム

- **XML 構造化設計**：XML タグで System Prompt を整理、LLM の理解効率を向上
  - `<character>`：キャラクター名とコア設定
  - `<knowledge_base>`：圧縮された WordPress 情報
  - `<behavior_rules>`：行動ルール（must_do、should_do、must_not_do）
  - `<response_style_examples>`：70+ の会話例
  - `<current_context>`：現在のコンテキスト情報

- **コンテキスト圧縮機構**：WordPress、ユーザー、訪問者情報を自動圧縮、トークン使用量を削減
- **フリーレン風範例システム**：70+ の実際の会話例を内蔵、12 カテゴリをカバー
  - 挨拶類、雑談類、時間感知類、観察思考類
  - 魔法研究類、技術観察類、統計観察類、回想類
  - 管理者評語類、意外反応類、BOT 検出類、沈黙類

- **二層アーキテクチャ設計**：
  - **System Prompt**：キャラクターの風格、行動ルール、会話例を定義
  - **User Prompt**：各会話の具体的なタスク指示（範例カテゴリに対応）

### 🎨 UI/UX 全面アップグレード

- **統一カードデザイン**：すべての設定ページで一貫したカードレイアウトを採用
- **アニメ風配色**：フリーレン公式サイトデザインを参考に、柔らかいグラデーション背景
  - カード背景：`#E8F4F8`（淡いブルーグリーン）
  - ボーダー色：`#B8E6E6`（薄いシアン）
  - タイトル色：`#4A9EBD`（ブルーグリーン）
  - テキスト色：`#2C3E50`（ダークブルーグレー）

- **2 カラムレイアウト**：メイン設定ページでメインコンテンツ + サイドバー設計
  - メインコンテンツ幅：55%
  - サイドバー幅：300px（固定）
  - サイドバー内容：AI Provider リンク、ドキュメントリンク、一般リンク

- **カスタムスクロールバースタイル**：長いテキストエリア（System Prompt など）に美しいスクロールバーを追加

### 🔧 機能改善

- **ページ認識機能統合**：「ページ認識機能」設定を LLM 設定ページに移動
  - すべての LLM 関連設定を統一管理
  - 「LLM で内蔵ダイアログを置換」機能と統合

- **AI 設定ページ簡素化**：「ページ感知」機能に集中
  - 保持：言語設定、キャラクター設定、ページ感知確率、トリガーページ、AI 会話の表示時間、初回訪問者への挨拶
  - 移動：AI プロバイダー選択、API Key 設定、モデル選択（LLM 設定ページへ）

- **統計比喩の最適化**：ゲーミフィケーション統計比喩を復元・最適化
  - 魔族遭遇回数 = 記事数 (`post_count`)
  - 最大ダメージ = コメント数 (`comment_count`)
  - 習得スキル総数 = カテゴリ数 (`category_count`)
  - アイテム使用回数 = タグ数 (`tag_count`)
  - 冒険経過日数 = 運営日数 (`days_operating`)

### 📝 コード最適化

- **新関数**：
  - `mpu_build_optimized_system_prompt()`：System Prompt を構築（変数置換サポート）
  - `mpu_build_prompt_categories()`：User Prompt 指示カテゴリを生成
  - `mpu_compress_context_info()`：コンテキスト情報を圧縮
  - `mpu_get_visitor_status_text()`：訪問者ステータステキストを取得

- **関数リファクタリング**：
  - `mpu_generate_llm_dialogue()`：新しい最適化 System Prompt システムを使用
  - 古い冗長な System Prompt 構築ロジックを削除

- **下位互換性**：古い設定のサポートを維持、設定キーを自動移行

### 🐛 バグ修正

- 統計比喩の対応関係を修正
- テキストエリア幅設定を最適化（統一 850px）
- メインメニュー下部線の配置問題を修正
- スクロールバースタイル問題を修正

### 📚 ドキュメント更新

- `USER_GUIDE.md` を更新：汎用 LLM インターフェースと System Prompt 最適化システムを完全説明
- `CHANGELOG.md` を更新：2.2.0 バージョンのすべての更新を記録

### 🎉 特別更新（2025-12-19）

- 『葬送のフリーレン』第 2 期が 2026 年 1 月 16 日に放送開始することを記念して、デフォルトキャラクターを初音からフリーレンに変更
- 新規インストールユーザーはフリーレンがデフォルトキャラクターとして表示
- 既存ユーザーでデフォルトキャラクター名が「初音」のままの場合、システムが自動的にフリーレンに更新

---

## [2.1.7] - 2025-12-15

### 🚀 パフォーマンス最適化

- **JavaScript ファイル構造リファクタリング**：10 個の JS ファイルを 4 つに統合、HTTP リクエストを削減
  - `ukagaka-base.js`：config + utils + ajax を統合（基盤層）
  - `ukagaka-core.js`：ui + dialogue + core を統合（コア機能）
  - `ukagaka-features.js`：ai + external + events を統合（機能モジュール）
  - `ukagaka-anime.js`：独立維持（アニメーションモジュール）
  - すべてのファイルで `ukagaka-` プレフィックス命名を統一

- **mousemove ログ最適化**：頻繁にトリガーされるログ記録を削除、コンソールの洪水を回避
  - `mousemove` イベントのログ出力をコメントアウト
  - デバッグモードでのデバッグ体験を向上

### 🔧 機能改善

- **LLM リクエスト最適化**：POST 方式でデータを送信、URL 長さ制限を回避
  - `FormData` ですべてのパラメータを送信（`cur_num`、`cur_msgnum`、`last_response`、`response_history`）
  - バックエンドで POST と GET 両方をサポート（下位互換性）
  - `wp_unslash()` で WordPress の JSON データを正しく処理

- **LLM リクエスト連打防止**：`cancelPrevious: true` オプションを追加
  - ユーザーが「次へ」を素早く連続クリックした時、前の未完了リクエストを自動キャンセル
  - 複数の並行リクエストがタイプライター効果を上書きするのを回避

### 🐛 エラー処理最適化

- **Canvas アニメーションエラー処理**：`mpuChange` 関数の開始時に Canvas Manager をチェック
  - `window.mpuCanvasManager` が存在するかを事前チェック
  - Ajax 成功後にエラーを発見することを避け、より一貫した体験を提供

- **LLM エラービジュアル提示**：デバッグモードでエラーメッセージを表示
  - 表示形式：`[LLM エラー: エラーメッセージ]`
  - 2 秒後に自動的にフォールバックダイアログに切り替え
  - 非デバッグモードでは直接フォールバックダイアログを使用、一般ユーザーに影響なし

### 📝 その他の改善

- ファイル命名規則を統一：すべての JavaScript ファイルで `ukagaka-` プレフィックスを使用
  - `jquery.textarearesizer.compressed.js` → `ukagaka-textarearesizer.js`

---

## [2.1.6] - 2025-12-13

### ✨ 新機能

- **WordPress 情報統合**：LLM 自発ダイアログがサイト情報を取得してコメント可能に
  - WordPress バージョン、テーマ情報（名前、バージョン、作者）、PHP バージョン、サイト名を統合
  - 統計情報：記事数、コメント数、カテゴリ数、タグ数、運営日数
  - transient キャッシュ機構を使用（5 分間）、パフォーマンス向上
  - `wordpress_info` と `statistics` の 2 つのプロンプトカテゴリを追加

- **RPG 風統計情報**：統計情報にゲーミフィケーション用語を使用
  - 魔族遭遇回数（記事数）
  - 最大ダメージ（コメント数）
  - 習得スキル総数（カテゴリ数）
  - アイテム使用回数（タグ数）
  - 冒険日数（運営日数）

- **重複ダイアログ防止機構**：「無駄話ループ」問題を回避
  - 前回の LLM 生成応答を追跡
  - プロンプトに重複回避指示を追加
  - 自動的に異なる雑談内容を生成するか、沈黙を維持

- **アイドル検出機能**：リソース節約のため自動ダイアログを自動一時停止
  - ユーザーアクティビティを検出（マウス、キーボード、スクロール、クリック）
  - 60 秒のアイドル閾値（調整可能）
  - ユーザーが戻った時に自動復帰
  - GPU とネットワークリソースを効果的に節約

### 🔧 改善

- **LLM システムプロンプト強化**：WordPress サイト情報を背景知識として追加
- **プロンプトの多様性向上**：WordPress 関連と統計情報関連のプロンプトを追加
- **パフォーマンス最適化**：不要な LLM リクエストを削減
- **リソース管理**：GPU とネットワークリソースの使用制御を改善

### 📝 技術詳細

- `mpu_get_wordpress_info()` 関数を追加（`includes/utility-functions.php` 内）
- `mpu_generate_llm_dialogue()` 関数を変更、WordPress 情報を統合
- フロントエンド JavaScript にアイドル検出ロジックを追加（`ukagaka-core.js`）
- AJAX ハンドラで `last_response` パラメータをサポート

---

## [2.1.0] - 2025-11-26

### ✨ 新機能

- **設定可能なタイプ速度**：タイプライター効果速度設定を追加（10-200 ミリ秒/文字）
- **API Key 暗号化保存**：AES-256-CBC ですべての API Key を暗号化
- **安全なファイル操作**：WordPress Filesystem API ですべてのファイル読み書きを実行
- **ディレクトリトラバーサル保護**：すべてのファイルパスを検証、不正アクセスを防止

### 🔧 改善

- 設定済み API Key に緑のチェックマークインジケーターを表示
- ファイル操作のエラーメッセージを改善
- 下位互換性：既存の平文 API Key を自動暗号化

### 🔒 セキュリティ

- すべての API Key で AES-256-CBC 暗号化を使用
- ファイル操作で WordPress Filesystem API を使用
- ディレクトリトラバーサル攻撃を防ぐパス検証を追加

---

## [2.0.0] - 2025-11-22

### 🏗️ アーキテクチャ改善

- **完全モジュール化リファクタリング**：単一ファイルを 7 つの独立モジュールに分割
- **メインプログラム簡素化**：`mp-ukagaka.php` を約 85 行に簡素化
- **依存順序読み込み**：モジュールを依存関係順に読み込み

### ✨ 新機能

- **AI ページ感知**：記事内容に基づいて AI コメントを自動生成
- **複数 AI プロバイダーサポート**：
  - Google Gemini（gemini-2.5-flash、gemini-2.5-pro）
  - OpenAI GPT（GPT-4o、GPT-4o-mini、GPT-3.5-turbo）
  - Anthropic Claude（Claude Sonnet 4.5）
- **初回訪問者への挨拶**：新規訪問者にパーソナライズされた歓迎メッセージを表示
- **Slimstat 統合**：訪問者のソース、地域などの情報を取得
- **AI テキスト色**：AI 応答のテキスト色をカスタマイズ可能
- **AI 表示時間制御**：AI メッセージの表示時間を設定

### 🔧 改善

- **JSON ダイアログファイルサポート**：TXT に加えて JSON 形式をサポート
- **エラー処理の改善**：より詳細なエラーログ
- **パフォーマンス最適化**：設定読み取りにキャッシュ機構を使用

### 📁 モジュール構造

```
includes/
├── core-functions.php      # コア機能
├── utility-functions.php   # ユーティリティ関数
├── ai-functions.php        # AI 機能
├── ukagaka-functions.php   # 伺か管理
├── ajax-handlers.php       # AJAX 処理
├── frontend-functions.php  # フロントエンド機能
└── admin-functions.php     # 管理画面機能
```

---

## 問題報告

問題を発見した場合、以下の情報を提供してください：

1. WordPress バージョン
2. PHP バージョン
3. プラグインバージョン
4. エラーメッセージ（ある場合）
5. ブラウザコンソールエラー（F12 で確認）

---

## 貢献者

- **原作者**：Ariagle _(元サイトは運営終了)_
- **メンテナー**：Horlicks ([MoeLog](https://www.moelog.com/))

---

**すべてのユーザーのサポートとフィードバックに感謝！** ❤️
