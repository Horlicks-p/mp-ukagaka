# コードベース全体レビュー（架構・効率・セキュリティ）

> 📅 作成：2026-06-10（公司CLAUDE(Fable 5) が現行コード v2.25.4 を file:line 単位で実査）
> 🎯 想定読者：CODEX / Antigravity（クロスレビュー用）
> 🔖 対象：`includes/` 全 PHP・`js/` フロント・`mp-ukagaka.php` bootstrap
> 📌 性質：**観察と提案のみ（未施作）**。各項目は file:line で裏取り済み。優先度は S/A/B/C で示す。

---

## 0. 総評

全体として「動く・セキュリティ意識のある」コードベースで、bootstrap は明快、REST は OO 化済み、AI provider は抽象層あり、入力境界の sanitize / 出力の escape も後付けの寄せ集めではない。既存の `Code_Quality_Hardening_Plan.md` / `Engineering_Quality_Improvement_Plan.md` が方向性を示しており、本レビューはそれらと**重複しない範囲で、実コードに照らして今あらためて拾える具体的な穴**を列挙する。

優先度の分布：

- **S（着手推奨・低コストで効果大）**：3 件 — touch 系 AI 端点の session token 抜け、API cache key の取りこぼし、`mpu_get_option()` の shallow merge。
- **A（設計上の負債・計画的に）**：3 件 — 暗号化の認証性欠如、巨大ファイルの責務分割、debug log の常時 full-prompt 出力。
- **B（堅牢性・運用）**：4 件。
- **C（軽微・cleanup）**：3 件。

---

## S 級（着手推奨）

### S-1. `/touch/decoration`・`/touch/zone` が session token 検証を欠く（AI quota 露出）

**実証**：

- `class-mpu-rest-chat.php:130 / :357 / :512` — `chat_context` / `chat_greet` / `prepare_user_chat`（user_chat・user_chat_stream 共通）は全て `$this->check_session_token($request)` を**呼んでいる**。
- 一方 `class-mpu-rest-touch.php` には `check_session_token` / `runtime_session_token` の呼び出しが**一つも無い**（grep ヒット 0）。`decoration_chat()`（:45）・`touch_zone_chat()`（:130）は `rate_limit()` のみで AI（`mpu_call_ai_api`）を叩く。

**影響**：touch 系も chat 系と同じく LLM provider を消費する公開端点。chat 側は「ページ初期化で発行した IP-bound session token 必須」で素の API 直叩きを弾くのに、touch 側は rate limit（20/60s/IP）だけ。spoof 困難な strict IP に紐づくとはいえ、**chat で確立した防御モデルが touch で破れている**。`Code_Quality_Hardening_Plan.md` §Phase1-1 が掲げる「公開 AI 端点は session token を主防御に」という方針と、touch 系が不整合。

**提案**：`decoration_chat()` / `touch_zone_chat()` の先頭（rate limit の前後）に chat と同じ `check_session_token()` ガードを追加。`MPU_REST_Base` に `require_session_token(WP_REST_Request): ?WP_REST_Response` を一つ生やして 5 端点（chat3 + touch2）で共有すると、各メソッドが個別に `check_session_token` を持つ現状の重複も解消できる。

**Gift プランへの波及（重要）**：策定中の `/touch/give`（Gift_Feeding_System_Plan §3③）も `MPU_REST_Touch` に同居し、現設計では session token ガードが明記されていない。give は `session_id` を**checksum 用**に受け取るが、それは token 検証とは別物。S-1 を直すなら give も同じガードに最初から乗せるべき。**この 1 点は Gift プラン側にも追記を推奨**。

---

### S-2. API cache key が provider+prompt のみ — model / language / max_tokens を含まず誤ヒット

**実証**：`api-cache.php:47-52`

```php
function mpu_generate_cache_key($provider, $system_prompt, $user_prompt) {
    $content = $provider . '|' . $system_prompt . '|' . $user_prompt;
    return 'mpu_api_' . md5($content);
}
```

key の構成要素は provider・system_prompt・user_prompt の 3 つだけ。

**影響**：同一 provider で **model を切り替えても**（例：`gemini-2.5-flash` → `gemini-2.5-pro`）、**language を変えても**、**max_tokens を変えても** cache key が変わらない。cache 有効時、設定変更後も TTL（最大 24h）の間は古いモデル/言語の回答が返り続ける。デバッグ困難な「設定を変えたのに反映されない」系の温床。

**提案**：key に `model`・`language`・`max_tokens`（および temperature を使うなら）を含める。後方互換は不要（cache は揮発・TTL 切れで自然消滅）。

```php
$content = implode('|', [$provider, $model, $language, (int)$max_tokens, $system_prompt, $user_prompt]);
```

呼び出し側（`ai-functions.php` の cache get/set 箇所）に model/language を渡す軽い signature 変更が要るが、影響範囲は cache 経路のみ。

---

### S-3. `mpu_get_option()` の `array_merge` が shallow — nested 設定の default 欠落

**実証**：`core-functions.php:112`

```php
$mpu_opt = array_merge($default_opt, $options);
```

`array_merge` はトップレベルだけ統合する。`default_opt` には nested 構造が複数ある：`ukagakas`（:41）、`extend`（:51）、`bot_blocker`（:82-91）。

**影響**：DB に保存済みの `bot_blocker` が**一部キーだけ**持つ状態（古いバージョンで保存 → 新バージョンでキー追加、等）だと、`array_merge` は `bot_blocker` 配列を**丸ごと保存値で上書き**し、新規 default サブキー（`hot_transient_ttl`・`rate_limit_threshold` 等）が**欠落**する。参照側が `?? default` で受けていれば顕在化しないが、直接 `$opt['bot_blocker']['rate_limit_threshold']` を触ると undefined index。`ukagakas['default_1']` だけは :115-117 で個別補填しているが、これは**この shallow merge 問題への場当たり的パッチ**であり、`bot_blocker` / `extend` には同等の保護が無い。

**提案**：nested の既知キーに限定した浅い再帰マージ（`wp_parse_args` を各 nested キーに適用、または専用の 2 段マージ helper）。:115-117 の個別パッチも統合できる。全面 deep-merge は personality 配列の意図的削除を壊す恐れがあるので、**対象キーを `bot_blocker` / `extend` に絞る**のが安全。

---

## A 級（設計負債）

### A-1. API key 暗号化に認証性（AEAD/HMAC）が無い + 脆弱な fallback

**実証**：`encryption-functions.php`

- `mpu_encrypt_api_key()`（:51-62）：`AES-256-CBC` + `OPENSSL_RAW_DATA`、IV は `openssl_random_pseudo_bytes`（良い）。だが **MAC を付けない**。CBC は改竄検知不能（ciphertext を弄っても復号は通る/失敗するが区別がつかない）。
- fallback（:64-66）：OpenSSL 不在時に `base64(strrev(key) . '|' . md5切片)`。コメント自身が「真の暗号化ではない」と認める難読化。
- key 導出（:24-29）：`AUTH_KEY` 由来。`AUTH_KEY` 未定義時は `get_site_url()` ベース（:27）— 公開情報からの導出になり実質無防備。

**影響**：これは「DB を読めた攻撃者から API key を守る」目的の at-rest 暗号化。脅威モデル上、DB read を得た攻撃者は通常 `wp-config.php`（AUTH_KEY）も狙えるため AEAD 化の限界利得は限定的だが、**fallback と AUTH_KEY 未定義パスは明確に弱い**。CLAUDE.md が「AES-256-CBC で暗号化」と明記している以上、整合も取りたい。

**提案**（段階的）：

1. `AUTH_KEY` 未定義時は暗号化を**拒否してエラー表示**（公開 URL 由来 key へ静かに fall back しない）。
2. fallback 難読化パスを削除し、OpenSSL 必須に（PHP 7.4+ で openssl は事実上常時利用可能）。
3. 余裕があれば `AES-256-GCM`（PHP 7.1+ で `openssl_encrypt` がタグ対応）へ移行し改竄検知を付与。`mpu_enc:` → `mpu_enc2:` の新 prefix で旧データと共存、復号は両対応。

優先度 A（即時の実害は低いが、CLAUDE.md の謳い文句と乖離があり、security review で必ず指摘される類）。

---

### A-2. 巨大ファイルの責務集中（保守コスト逓増）

**実証**（実測）：

- `frontend-functions.php` **85.7 KB**（enqueue・`mpu_head` のインライン JS 生成・localize・session 関連が混在。:1047-1149 だけで巨大なインライン `<script>` heredoc）。
- `class-mpu-rest-chat.php` **59.9 KB**（context/greet/user/stream の prepare〜dispatch〜checksum〜SSE が 1 クラス）。
- `frieren.js` **56.2 KB**、`admin-functions.php` **51.2 KB**。

**影響**：`Engineering_Quality_Improvement_Plan.md` が既に分割方針を持つが、現状 chat controller と frontend が肥大化を続けている。特に `frontend-functions.php` の `mpu_head()` 内インライン JS（`echo "var mpuInfo = …"` 等）は、PHP と JS が混ざり escape 境界が読みにくく、`wp_localize_script` への移行余地が大きい（:1029 で一部は既に localize 済みなのでパターンは存在する）。

**提案**：新規大改は避け、`Code_Quality_Hardening_Plan.md` の漸進方針通り「① chat の history/checksum は既に `MPU_Chat_History_Service` へ分離済み（良い前例）→ ② SSE 経路を別 trait/class へ → ③ `mpu_head` のインライン JS を localize 化」の順。本レビューは**新たな分割計画を作るのではなく、既存計画の優先度を frontend インライン JS に寄せること**を提案する。

---

### A-3. debug log が WP_DEBUG 時に full system/user prompt を吐く（単輪・多輪の両方）

> ⚠️ **2026-06-10 校正（家 CODEX 指摘・実コード再確認済み）**：初稿は「`WP_DEBUG` **または** `WP_DEBUG_LOG` で出力」と書いたが**過大**。`chat-api-handlers.php:41` の `if` は `WP_DEBUG || WP_DEBUG_LOG` だが、内側は全て `mpu_debug_log()` を通り、同関数は `debug-functions.php:30-32` で **`WP_DEBUG !== true` なら early-return** する。生 `error_log` は `else`（`mpu_debug_log` 不在時）分岐のみで、同関数は最初に load されるため実質発火しない。**結論：`WP_DEBUG_LOG` 単独（`WP_DEBUG=false`）の本番では洩れない。実際の洩れは `WP_DEBUG=true` のとき**。リスク自体は成立するが、トリガ条件は `WP_DEBUG` に訂正する。

**実証（2 経路）**：

- 多輪：`ajax/chat-api-handlers.php:41-70` — system prompt 全文・messages 全件（各 200 字截断はあるが件数無制限）。
- **単輪：`ai-functions.php:55-68`**（`mpu_call_ai_api()`）— こちらも system_prompt / user_prompt を**全文**ダンプ。**touch/decoration・chat context・greet はこの単輪経路を通る**ので、多輪だけ直しても prompt 洩れは残る。

**影響**：`WP_DEBUG=true` で運用している環境（小規模サイトでは珍しくない）では、**全 AI 呼び出しの system prompt と会話内容が `debug.log` に蓄積**する。system prompt には admin profile（`admin_name` 等、core-functions.php 由来）が載りうる。API key はログされない（良い）が、会話 PII とプロンプト資産が平文ログ化する。

**提案**：(1) この詳細ダンプを `mpu_is_frontend_debug_mode()` 相当の**専用フラグ**（独自 `MPU_DEBUG_LLM` 定数 or filter）にゲートし、`WP_DEBUG` 単独では prompt 全文を出さない（メタ情報のみに留める）。(2) **単輪・多輪を同時に修正**（`ai-functions.php` と `chat-api-handlers.php` の両方）。(3) `chat-api-handlers.php:56-68` の到達不能な生 `error_log` fallback を削り `mpu_debug_log` 一本化。(4) 多輪は出力件数に上限（直近 N 件）。

---

## B 級（堅牢性・運用）

### B-1. `wp_remote_get(..., 'sslverify' => false)` が Slimstat 連携で 2 箇所

**実証**：`llm-slimstat.php:86 / :105`。自サイトの `rest_url('slimstat/v1/get')`（:75）へ `sslverify => false` で叩く。

**影響**：宛先が自ホストの REST とはいえ、`sslverify=false` は習慣化すると危険な既定。token をクエリ（:78-82）に載せて投げるため、ループバックでない構成（リバプロ経由で外向き解決される等）では MITM 面が生じうる。`provider-stream-http.php:43` は逆に `apply_filters('https_ssl_verify', true)` と正しく既定 true。**プラグイン内で態度が割れている**。

**提案**：自ホスト宛は HTTP（`home_url` の scheme）で組むか、`sslverify` を既定 true にして `apply_filters('https_local_ssl_verify', …)` で運用者に委ねる。少なくともコメントで「なぜ false か」を残す。

### B-2. `mpu_clear_all_api_cache()` の直 SQL は OK だが options autoload 肥大に注意

**実証**：`api-cache.php:110-118` は `$wpdb->prepare` でエスケープ済み（SQL injection なし、良い）。だが transient を `mpu_api_*` で大量に作る設計（:97 `set_transient`）。外部オブジェクトキャッシュが無い環境では `wp_options` に積もり、`_transient_*` は autoload=no だが件数増で `DELETE … LIKE` が重くなる。

**提案**：実害は小。cache 件数の上限 or LRU 的破棄は過剰なので、`mpu_get_api_cache_stats()`（:126）を admin に出して運用者が把握できれば十分（既にある）。**記載のみ**。

### B-3. `uninstall.php` 不在 — アンインストールで option/transient が残置

**実証**：プラグインルートに `uninstall.php` **無し**（Test-Path = False）。`register_deactivation_hook`（mp-ukagaka.php:50）は cron 解除のみ。`mp_ukagaka` option・`mpu_sess_*`・`mpu_rl_*`・`mpu_api_*` transient・統計 option（`mpu_stats_*`）は削除されない。

**影響**：規約違反ではない（多くのプラグインが残す）が、暗号化 API key を含む `mp_ukagaka` option が**アンインストール後も DB に残る**のは、A-1 の at-rest 暗号化の意義を考えると一貫しない。

**提案**：`uninstall.php` を追加し、option + 既知 transient prefix + 統計 option を掃除。削除可否を admin 設定でオプトインにすると親切（誤削除防止）。

### B-4. observation `/observation/push` は token ガードあり（良い対比）

**実証**：`class-mpu-rest-observation.php:25` は `require_valid_session_token()` を**最初に**呼ぶ。S-1 の touch 系と対照的に、こちらは正しく実装されている。**これが touch 系にあるべき姿のリファレンス**。指摘ではなく、S-1 の修正テンプレートとして参照価値あり。

---

## C 級（軽微・cleanup）

### C-1. `core-functions.php` 末尾（:252-256）にコメントだけ残り本体が無い

`/** 啟用時建立目錄 … */` の docblock 直後にファイルが終わっている。実体（activation hook）は mp-ukagaka.php:36 に移動済みなので、この**宙ぶらりんコメントは削除**でよい。

### C-2. options 既定に新旧キーの二重持ち（`llm_*` と `ai_*` / `gemini_model` 等）

`core-functions.php:55-69` で `llm_gemini_api_key` と `ai_api_key`、`llm_gemini_model` と `gemini_model` 等が**両方** default に存在。`mpu_get_provider_api_key()`（encryption-functions.php:152-170）が新旧両対応で読むため動くが、新規インストールでも両系統の空キーが DB に入る。**移行が一段落しているなら旧キーを default から外し、読み取り側の後方互換のみ残す**のが綺麗（破壊的なので慎重に、別 PR で）。

### C-3. `mpu_get_client_ip()`（network-functions.php:30）と `_strict`（:86）の二系統

用途別に分けてあるのは正しい設計（docblock も丁寧）。ただ非 strict 版の現在の利用箇所が「表示・enrichment のみ」に限定され続けているかは grep 監査の価値あり。**rate limit / 黒名單 / token に非 strict が混入していないことを CI 的にチェック**できると、将来の事故を防げる（現状は混入なしを確認済み：`mpu_check_rate_limit:261`・`mpu_generate_session_token:360`・`mpu_validate_session_token:387` は全て strict）。

---

## 付録：確認したが「問題なし」の項目（誤指摘防止）

- **SSE streaming の SSL**：`provider-stream-http.php:43` は `https_ssl_verify` filter 既定 true。正しい。
- **session token**：`random_bytes(16)`（128bit）+ IP hash 紐付け + TTL 2h（network-functions.php:358-371）。token を HTML に埋めず `/session-token` で遅延取得（frontend-functions.php:1064-1066）— full-page cache 汚染を正しく回避。
- **nonce 自動更新**：`rest_post_dispatch`（network-functions.php:334-347）で aging nonce（戻り値 2）時のみ `new_token` 同梱。妥当。
- **admin 保存の nonce**：`admin-functions.php:336 / :348 / :375` で各操作に個別 nonce。診断 AJAX（diary-functions.php:777）も `check_ajax_referer` + `manage_options`。OK。
- **file 操作**：`file-functions.php` は realpath + allowed-dir 包含チェック + サイズ上限 + ファイル名正規表現（:122）。directory traversal 対策は妥当。
- **SQL**：`api-cache.php` の直 SQL は `$wpdb->prepare` 使用。injection なし。
- **フロント XSS**：`mpu_parseMarkdown`（ukagaka-chat.js:252-274）は AI 出力を `.html()` に渡す（:746/:766）。**ただし**入力は自サイトの LLM 応答であり、tag は normalizer 側で処理済み。リスクは「LLM が生 HTML を吐く」場合に限られ、現状 markdown サブセットのみ HTML 化。**潜在的に DOMPurify 等の sanitize を挟む価値はある**が、脅威は限定的なので C 級未満として記載のみ。

---

## 推奨着手順（コスト対効果）

1. **S-1**（touch session token）— ガードを `MPU_REST_Base` に抽出し **chat / observation / touch の 3 系統で共有**。各メソッド個別の `check_session_token` 重複も解消。Gift プランの `/touch/give` も同時にこの共有ガードへ乗せる。正常前端は `mpuFetch()` が `X-MPU-Session-Token` を自動付与するため UI は壊れない（公司 CODEX 確認済み）。
2. **S-2**（cache key）— 1 関数 + 呼び出し側微修正。⚠️ **key は provider の model 解決より前で生成される**ため、model/language を含めるには cache key 生成位置を model 解決後へ移すか、cache metadata を先に解決する（公司 CODEX 指摘）。
3. **S-3**（shallow merge）— `bot_blocker`/`extend` 限定の nested merge。`:115-117` のパッチも吸収。bot_blocker 設定ページは `$bb_config['rate_limit_threshold']` を直読みするため純理論問題ではない（公司 CODEX 確認済み）。
4. **A-3**（debug prompt log ゲート）— トリガは **`WP_DEBUG`**（`WP_DEBUG_LOG` 単独では洩れない）。**単輪 `ai-functions.php` と多輪 `chat-api-handlers.php` を両方**ゲートしないと touch/context の prompt 洩れが残る。低コスト。
5. **A-1**（暗号化）/ **B-3**（uninstall）— security review 対策として計画的に。
6. **A-2**（分割）— 既存 Engineering Quality 計画に統合、frontend インライン JS を優先。

各項目は独立して回退可能。S 級 3 件は 1 PR にまとめても相互依存なし。
