# avatar-ui から学ぶ — MP-Ukagaka 改善提案

> 📅 作成日：2026-05-15
> 📋 avatar-ui（Electron 多通道 AI 伴侶框架）の設計と照らし合わせた改善分析
> 🔗 参照元：`C:\D\php\avatar-ui`

avatar-ui は MP-Ukagaka とは全く異なる環境（Electron / TypeScript / xAI）で動いているが、
「AI キャラと人間が長期的な関係を結ぶ」という核心目標は同じ。
以下はその設計から MP-Ukagaka が参照できる具体的な学びを優先度別に整理したもの。

---

## 優先度マップ

| 優先度 | 項目 | 工数 | リスク | 前置条件 |
|:------:|------|:----:|:------:|----------|
| 🔴 | chat-integrity.php の凍結モード移行 | 0.5日 | 低 | なし |
| 🔴 | 並行 LLM 呼び出しのロック機構 | 1日 | 中 | なし |
| 🟡 | UI リアルタイム状態フィードバック | 1-2日 | 低 | なし |
| 🟡 | 角色生命週期の状態機明示化 | 1日 | 低 | なし |
| 🟡 | 中央 Config クラス | 1日 | 低 | なし |
| 🟢 | テスト基盤（PHPUnit 導入） | 2-3日 | 低 | 工具鏈 lint gate 完成後 |
| 🟢 | CSS テーマ切り替え | 1日 | 低 | なし |
| 🟢 | i18n ホットスワップ | 1-2日 | 中 | なし |
| ⚪ | 観測バッファ / 自発行動 | 3-5日 | 高 | User Memory MVP 完成後 |

---

## 🔴 高優先 — すぐやる価値がある

### 1. chat-integrity.php を「監査モード」から「凍結モード」へ

**avatar-ui の実装：**
`integrity-manager.ts` は整合性違反を検知した瞬間に `fieldOrchestrator.freeze()` を呼び、
以後の AI 呼び出しを全停止する。「記録するだけ」では不十分という設計思想。

**MP-Ukagaka の現状：**
`chat-integrity.php` は checksum のミスマッチを `logs/checksum-mismatch.log` に記録するのみ。
CLAUDE.md にも「currently operates in **observational (audit) mode**」と明記されている。

**提案：**
```php
// chat-integrity.php 内
if ( ! $is_valid ) {
    mpu_log( '[INTEGRITY] Checksum mismatch — request blocked.', 'error' );
    // 現状：ログだけ
    // 改善：即時リターンでリクエストを止める
    return new WP_Error(
        'mpu_integrity_violation',
        __( 'Chat history integrity check failed.', 'mp-ukagaka' ),
        [ 'status' => 403 ]
    );
}
```

凍結の対象は「ユーザー発の /chat/user および /chat/user-stream エンドポイント」のみ。
greeting や context は管理者ロジックなので除外してよい。

> [!NOTE]
> これは機能追加ではなく「計画済みの昇格」。実装済みの checksum 検証ロジックに
> return 文を一行加えるだけなので、工数 0.5 日と見積もる。

---

### 2. 並行 LLM 呼び出しのロック機構

**avatar-ui の実装：**
`field-orchestrator.ts` の `enqueue()` は全ての入力（ユーザー発話 / cron pulse / 観測イベント）を
Promise chain で直列化する。前のタスクが終わるまで次は始まらない。

```typescript
private enqueue(task: () => Promise<void>): void {
    this.queue = this.queue.then(task).catch(err => this.warn(err));
}
```

**MP-Ukagaka の現状：**
`/chat/user-stream` はリクエストが来るたびに SSE セッションを開く。
ユーザーが送信ボタンを連打すると、複数の LLM 呼び出しが並走する可能性がある。
フロントエンド側の `mpuChatModeActive` フラグは UI のみのガードであり、
PHP 側には排他制御がない。

**提案（PHP 側）：**
```php
// class-mpu-rest-chat.php または chat-api-handlers.php
$lock_key = 'mpu_chat_lock_' . session_id();
if ( get_transient( $lock_key ) ) {
    return new WP_Error( 'mpu_busy', __( 'Previous request still processing.', 'mp-ukagaka' ), [ 'status' => 429 ] );
}
set_transient( $lock_key, true, 30 ); // 30秒タイムアウト
// ... LLM 呼び出し ...
delete_transient( $lock_key );
```

WordPress transients は既に使用しているため、追加の依存関係なしで実装可能。

> [!WARNING]
> セッション ID はログインユーザーと匿名訪問者で挙動が異なる。
> 匿名ユーザーの場合は `session_start()` を呼んでいるか確認すること。
> 代替として Cloudflare Turnstile の IP ベースレート制限を活用する方法もある。

---

## 🟡 中優先 — 体験向上に直結

### 3. UI リアルタイム状態フィードバック

**avatar-ui の実装：**
7 面板全てに状態インジケーターがある。LLM 呼び出し中は Pulse アニメーション、
凍結時は赤い Alert bar、完了時はフェードアウト。ユーザーはシステム状態を常に把握できる。

**MP-Ukagaka の現状：**
SSE 送信中に typewriter 効果でテキストが流れる表示はあるが、
「コンテキスト構築中」「AI 呼び出し中」「ツール実行中」の区別がない。
エラー時の表示も最小限。

**提案（JavaScript 側）：**
```javascript
// ukagaka-chat.js または ukagaka-core.js に追加
const mpuStateIndicator = {
    show(state) {
        // state: 'thinking' | 'streaming' | 'tool' | 'error' | 'frozen'
        const el = document.getElementById('mpu-state-badge');
        if (!el) return;
        el.dataset.state = state;
        el.textContent = mpuStateLabels[state] || state;
        el.style.display = 'block';
    },
    hide() {
        const el = document.getElementById('mpu-state-badge');
        if (el) el.style.display = 'none';
    }
};
```

SSE イベントタイプ `status` は既に実装済み（`streaming-helpers.php`）。
`delta` / `status` / `done` / `error` のイベントを受け取るたびに `mpuStateIndicator.show()` を呼べば最小工数で実現できる。

具体的な状態ラベル案（三言語対応）：
| state | 日本語 | 繁体中文 | English |
|-------|--------|----------|---------|
| thinking | 考え中... | 思考中... | Thinking... |
| streaming | 話している... | 回應中... | Responding... |
| tool | 調べてる... | 查詢中... | Looking up... |
| error | エラーが発生しました | 發生錯誤 | An error occurred |

---

### 4. 角色生命週期の状態機明示化

**avatar-ui の実装：**
```
generated → active → paused ⇄ resumed → terminated
```
状態は `state.json` に永続化され、`fieldOrchestrator` が全ての遷移を管理する。
「ログに残る」ではなく「状態として存在する」。

**MP-Ukagaka の現状：**
角色の有効/無効は WordPress のオプション（`mpu_opt['enable']`）で管理しているが、
「一時停止」「ツール実行中」「AI 呼び出し中」などの動的状態は追跡されていない。

**提案：**
まずシンプルに `mpu_opt` に `runtime_state` フィールドを追加するだけでよい：

```php
// core-functions.php 内に追加
function mpu_set_runtime_state( string $state ): void {
    // $state: 'idle' | 'processing' | 'error' | 'disabled'
    $opt = mpu_get_option();
    $opt['runtime_state'] = $state;
    $opt['runtime_state_updated'] = time();
    mpu_save_option( $opt );
}
```

これにより後台 UI でも「フリーレンは今何をしているか」を表示できるようになる。
将来の自発行動（§ 観測バッファ）の前置条件でもある。

---

### 5. 中央 Config クラス

**avatar-ui の実装：**
`src/config.ts` が全環境変数の唯一の真実。型バリデーション（Zod）付き。
設定値が散在しない。

**MP-Ukagaka の現状：**
設定値は `$mpu_opt` を通じて各モジュールが個別に取得している。
定数（`MPU_VERSION` 等）は `mp-ukagaka.php` に定義されているが、
デフォルト値の定義が各モジュールに分散していることがある。

**提案：**
```php
// includes/core/class-mpu-config.php（新規）
class MPU_Config {
    private static array $defaults = [
        'enable'            => true,
        'ai_provider'       => 'gemini',
        'trigger_prob'      => 20,
        'chat_history_max'  => 40,
        'tool_turns_max'    => MPU_MAX_TOOL_TURNS,
        'cache_ttl'         => 3600,
        // ...
    ];

    public static function get( string $key, mixed $fallback = null ): mixed {
        $opt = mpu_get_option();
        return $opt[ $key ] ?? self::$defaults[ $key ] ?? $fallback;
    }
}
```

既存の `mpu_get_option()` を壊さずに段階的に移行できる。
まず新機能から `MPU_Config::get()` を使い始める方針でよい。

---

## 🟢 低優先 — Nice-to-have

### 6. PHPUnit テスト基盤の導入

**avatar-ui の実装：**
403 件の Vitest テスト（39 ファイル）＋ 5 つの受け入れシナリオ（S1-S5）。
AI プロバイダー、整合性チェック、ツール呼び出しループ検知など
全ての重要パスが自動テストで保護されている。

**MP-Ukagaka の現状：**
自動テストなし。`class-mpu-rest-test.php` による手動接続テストのみ。

**提案（最小 MVP）：**
```bash
composer require --dev phpunit/phpunit
```

まず以下の 3 ファイルだけテストを書く：
1. `MPU_AI_Provider_Factory::create()` — 未知プロバイダーで例外が出るか
2. `chat-integrity.php` — checksum 検証ロジック（改ざんを検知できるか）
3. `tool-loop-guard.php` — ループ検知（同じ引数 N 回で停止するか）

> [!IMPORTANT]
> `Code_Quality_Hardening_Plan` が「次に PHP を触る前に工具鏈安全網を整備すべき」と結論付けている。
> PHPUnit 導入はその延長線上にある。lint gate 完成後に着手するのが自然な順序。

---

### 7. CSS テーマ切り替え

**avatar-ui の実装：**
`data-theme="modern"` / `data-theme="classic"` の切り替えで
Dark UI と TUI グリーン端末スタイルを瞬時に切り替える。`localStorage` に記憶。

**MP-Ukagaka の提案：**
```css
/* mpu_style.css */
:root {
    --mpu-bg: #fff;
    --mpu-text: #333;
    --mpu-border: #e0e0e0;
    --mpu-bubble-bg: rgba(255,255,255,0.92);
}

[data-mpu-theme="dark"] {
    --mpu-bg: #1a1a2e;
    --mpu-text: #e0e0e0;
    --mpu-border: #444;
    --mpu-bubble-bg: rgba(30,30,50,0.92);
}
```

```javascript
// ukagaka-features.js
function mpuSetTheme(theme) {
    document.documentElement.dataset.mpuTheme = theme;
    localStorage.setItem('mpu_theme', theme);
}
```

設定パネルにトグルボタンを追加するだけで実現できる。
CSS 変数は既に一部使用しているため、段階的に移行可能。

---

### 8. i18n ホットスワップ

**avatar-ui の実装：**
`src/shared/i18n.ts` が `setLocale()` を提供し、`localStorage` に記憶。
ページリロードなしで日本語 ↔ 英語を切り替え。

**MP-Ukagaka の現状：**
WordPress の `get_locale()` に依存しており、言語切り替えはサーバーサイドで処理される。
フロントエンドの UI 文字列（JS 内）は `wp_localize_script()` で注入済み。

**提案：**
現状の WordPress i18n 機構はそのまま維持しつつ、
JS 側の動的文字列（状態ラベル、チャット UI テキスト等）を
`window.mpuI18n` オブジェクトに集中させる：

```php
// frontend-functions.php
wp_localize_script( 'ukagaka-chat', 'mpuI18n', [
    'thinking'   => __( '考え中...', 'mp-ukagaka' ),
    'streaming'  => __( '話している...', 'mp-ukagaka' ),
    'chatClose'  => __( '閉じる', 'mp-ukagaka' ),
    // ...
]);
```

完全なホットスワップは WordPress のアーキテクチャ上難しいが、
JS 文字列を `mpuI18n` に統一するだけでも保守性が大きく改善される。

---

## ⚪ 将来候補 — User Memory 完成後に検討

### 9. 観測バッファ / 自発行動（Resonance）

**avatar-ui の実装：**
Roblox の接近イベント、X のメンション、cron pulse などが全て
「観測バッファ」に蓄積され、次の LLM ターンの前置コンテキストとして注入される。
これにより AI は「呼ばれた時だけ反応する」から「状況を観察して自ら話しかける」に進化する。

**MP-Ukagaka への応用案：**
```
新着コメント投稿 → 観測バッファ → 次のオートトーク時に言及
新記事公開     → 観測バッファ → 角色が「新しい話があるよ」と言う
天気急変       → 観測バッファ → 気候に触れた発話
```

実装アーキテクチャ（案）：
```php
// includes/core/class-mpu-observation-buffer.php（新規）
class MPU_Observation_Buffer {
    public static function push( string $type, string $content ): void {
        $buf = get_transient( 'mpu_obs_buffer' ) ?: [];
        $buf[] = compact( 'type', 'content' ) + [ 'ts' => time() ];
        // 最大 5 件 / 6 時間で TTL 切れ
        set_transient( 'mpu_obs_buffer', array_slice( $buf, -5 ), 6 * HOUR_IN_SECONDS );
    }

    public static function flush(): array {
        $buf = get_transient( 'mpu_obs_buffer' ) ?: [];
        delete_transient( 'mpu_obs_buffer' );
        return $buf;
    }
}
```

`llm-context-builder.php` がプロンプト構築時に `MPU_Observation_Buffer::flush()` を呼び、
バッファ内容を system prompt の末尾に注入する。

> [!CAUTION]
> この機能は User Memory MVP（`Next_Plan` §1）が完成してから着手すべき。
> 状態機の明示化（§4 本文）も前置条件として推奨。
> 観測イベントの発火頻度・コスト・プライバシーの設計が複雑なため、
> 「最窄 MVP」の定義を先に固めること。

---

## avatar-ui との根本的な違い（設計上の前提）

MP-Ukagaka は avatar-ui と環境が異なるため、**そのまま移植できない部分もある**。
以下は参照する際に念頭に置くべき差異：

| 観点 | avatar-ui | MP-Ukagaka |
|------|-----------|------------|
| **実行環境** | Electron（常駐プロセス） | PHP on demand（リクエスト単位） |
| **状態管理** | メモリ内 FSM + `state.json` | WordPress options（永続化のみ） |
| **並行制御** | Promise chain（単一プロセス） | WordPress transients / PHP ロック |
| **WebSocket** | ネイティブサポート | 要追加インフラ（難易度高） |
| **テスト** | Vitest 403 件 | 手動のみ（PHPUnit は導入可） |
| **ユーザー** | 単一ユーザー設計 | 多数の匿名訪問者 + admin |

特に「常駐プロセス vs リクエスト単位」の違いは大きい。
avatar-ui の観測バッファが機能するのは Electron が常時起動しているためであり、
PHP 版では wp_cron や外部 webhook で代替する必要がある。

---

## 推奨実行順序

```
1. chat-integrity.php 凍結モード移行   ← 0.5日、即効性高
2. 並行 LLM ロック機構                ← 1日、安定性向上
3. UI 状態フィードバック              ← 1-2日、UX 直結
   （User Memory MVP と並行可）
4. 角色状態機明示化                   ← 1日、将来の基盤
5. PHPUnit 導入（lint gate 後）        ← 2-3日、品質保証
6. CSS テーマ / i18n 改善             ← 必要に応じて
7. 観測バッファ / 自発行動            ← User Memory 完成後
```

**最初の一手として最も ROI が高いのは §1（chat-integrity 凍結モード移行）**。
既存コードへの変更が最小で、セキュリティ品質が明確に向上する。
---

## 補充觀點：可借鏡的是 runtime 思維，不是 Electron 外殼

avatar-ui 對 MP-Ukagaka 最有價值的啟發，不是它使用 Electron、WebSocket、Roblox、X 或 Discord，而是它把「AI 角色互動」視為一個 runtime：有生命週期、有事件格式、有輸入來源、有權限邊界、有狀態恢復策略。MP-Ukagaka 已經具備人格、睡眠模式、訪客訊號、REST/SSE、MCP abilities、User Memory 等材料；下一階段的重點應該是收斂這些能力，而不是再增加零散功能。

### A. Ghost Runtime State 應該比 `runtime_state` 更語義化

本文第 4 點提到 `runtime_state`，這方向正確，但建議不要只停在 `idle | processing | error | disabled`。MP-Ukagaka 的角色狀態比一般聊天機器人更豐富，至少可以先定義一組內部狀態：

```text
idle
thinking
speaking
chatting
sleeping
waking
tool_running
suspended
error
```

這樣的好處是前端動畫、睡眠模式、SSE 狀態、工具執行、管理後台診斷可以共享同一套語意。短期可以只做 helper 與只讀顯示，不急著讓所有功能都依賴它；但命名先定下來，後續重構會容易很多。

### B. 將「角色事件」作為前後端共通格式

avatar-ui 的 `stream.item`、`monitor.item`、`approval.requested`、`session.state` 概念值得借鏡。MP-Ukagaka 不一定需要 WebSocket，也不需要完整 event bus，但可以先定義 PHP/JS 共用的事件型別：

```text
ghost.speech
ghost.state
ghost.touch
ghost.observation
ghost.memory
ghost.tool.requested
ghost.tool.resolved
ghost.error
```

這能改善目前 REST、SSE、前端全域變數、chat history、觸碰反應各自成形的問題。最小做法是在 `streaming-helpers.php` 與前端 SSE handler 之間統一 event payload，例如 `type`, `source`, `state`, `message`, `meta`。

### C. Tool Gate 應該成為 server-side policy

目前 MP-Ukagaka 已經在 prompt 裡要求非管理員不能執行管理工具，但 prompt 不是安全邊界。avatar-ui 的 InputGate 值得轉成 WordPress 版本：

```text
visitor     -> 只能聊天、不能執行站台工具
subscriber  -> 可使用低風險查詢工具
admin       -> 可使用診斷、統計、設定類工具
system      -> cron / auto talk / diary 可用白名單工具
```

這應該放在 MCP abilities 呼叫前，而不是只放在 prompt builder。尤其 `ban-ip`、清除資料、修改設定、檔案操作這類能力，必須由 PHP 權限檢查阻擋。

### D. Observation Buffer 應該先走「低侵入」路線

第 9 點的 observation buffer 很重要，但不建議一開始就做 autonomous action。更穩的 MVP 是「收集但不主動發話」：

1. 訪客來源、頁面、觸碰、bot/crawler、Slimstat、文章上下文只進 buffer。
2. 下一次 `/chat/user`、auto talk 或 page-aware context 時才 flush。
3. flush 後只作為 context，不直接觸發 LLM。

這樣可以先降低成本、隱私與觸發頻率風險，也能避免角色突然主動說話造成體驗噪音。等 User Memory MVP 穩定後，再評估哪些 observation 可以升級成主動觸發。

### E. 測試優先補 policy 與純函式，不急著做完整 E2E

avatar-ui 的測試密度很高，但 MP-Ukagaka 是 WordPress 外掛，直接補完整 E2E 成本偏高。建議先補最容易出價值的測試：

```text
chat-integrity checksum
tool-loop-guard
provider factory
tool permission gate
sleep/runtime state transition
prompt category weight selection
observation buffer push/flush
```

這些多半可以做成低 WordPress 依賴或輕量 WP bootstrap 測試。優先保護「會造成安全、成本、狀態錯亂」的邏輯，比先測 UI 更有 ROI。

### F. 文件中的優先級建議微調

原本最高 ROI 放在 chat-integrity freeze mode 是合理的。但我會把「server-side tool gate」也拉到高優先級，甚至排在 observation buffer 之前。原因是 MP-Ukagaka 已經有 MCP/Abilities，能力越強，越需要明確權限邊界。

建議排序可以調整為：

```text
1. chat-integrity freeze mode
2. LLM request lock / busy protection
3. server-side tool gate
4. UI runtime/state feedback
5. ghost runtime state helper
6. PHPUnit / focused policy tests
7. observation buffer
8. CSS theme / i18n polish
```

總結：avatar-ui 的真正價值是提醒 MP-Ukagaka，角色系統不該只是「前端動畫 + prompt + REST endpoint」，而應該有一個清楚的 runtime core。先把狀態、事件、權限、測試邊界定好，後續 User Memory、自主反應、訪客觀察與工具能力才不會互相纏繞。
