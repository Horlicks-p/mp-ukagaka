# avatar-ui から学ぶ — MP-Ukagaka 改善提案（v3 統合版）

> 📅 初稿：2026-05-15
> 📅 v2 改訂：2026-05-15（Claude reviewer note 統合・優先度再編・誤りの訂正）
> 📅 v3 統合：2026-05-15（**CODEX / Gemini 両 reviewer の回答を本文に反映**）
> 📋 avatar-ui（Electron 多通道 AI 伴侶框架）の設計と照らし合わせた改善分析
> 🔗 参照元：`avatar-ui`（同梱の reference implementation）
> 👥 想定読者：実装担当（Claude）+ クロスレビュー結果反映済み

---

## このドキュメントの読み方

avatar-ui は MP-Ukagaka とは全く異なる環境（Electron 常駐 / TypeScript / xAI / 単一ユーザー）で動いている。
「AI キャラと人間が長期的な関係を結ぶ」という核心目標は同じだが、
**「思考」を借りるのは正しく、「実装」をそのまま移植すると壊れる**項目が混在している。

v3 統合の要点：

1. CODEX / Gemini 両 reviewer の **6 つの問いへの回答が一致** → そのまま本文の決定事項として反映
2. Codex 追加論点 3 件（approval-hub 予約 / integrity warn-report 分離 / User Memory namespace 境界）を該当節に統合
3. Gemini 追加論点（**Client-side タイムアウト & idle fallback** — zombie state 対策）を P1-2 の必須要素として昇格
4. 実装 PR の分割順序を Codex 推奨の 5 段に確定（後述「推奨実行順序」参照）

v2 改訂で行ったこと（参考）：

1. 優先度を **P0 / P1 / P2 / 降級・却下** に再編（MP-Ukagaka における ROI で並べ直し）
2. WordPress on-demand アーキテクチャに合わない実装案を訂正（`session_id()`, `mpu_opt` への runtime state 保存など）
3. **input-role-resolver パターン** を新規 P0 として追加
4. 中央 Config クラス・i18n hot-swap を明示的に却下

---

## 優先度マップ（改訂版）

| 優先度 | 項目                                       | 工数  | リスク | 前置条件 | PR |
| :----: | ------------------------------------------ | :---: | :----: | -------- | :-: |
|   🔴 P0 | **input-role-resolver の導入**             |  1日  |   低   | なし     | PR1 |
|   🔴 P0 | **Session Event Schema 統一（PHP/JS 共通定数）** |  1日  |   低   | なし     | PR2 |
|   🔴 P0 | **Server-side Tool Gate（role 拡張版）**   | 0.5日 |   低   | PR1 + PR2 | PR3 |
|   🟡 P1 | UI 状態フィードバック + **client-side watchdog** | 1.5日 |   低   | PR2      | PR4 |
|   🟡 P1 | chat-integrity 三段モード（audit/warn/block） |  1日  |   中   | PR4 + 観察期2週間 | PR5 |
|   🟢 P2 | Ghost Runtime State helper（transient ベース、唯讀表示） | 0.5日 |   低   | なし     | PR6 |
|   🟢 P2 | PHPUnit 純函式テスト（4ファイル限定）       | 1-2日 |   低   | なし     | PR7 |
|   🟢 P2 | Observation Buffer（passive-only、自発発話なし） | 2-3日 |   中   | **User Memory v2**（§M 参照） | PR8 |
|   ⚪ 降級 | 並行 LLM ロック機構                        |  -    |   -    | UX 問題優先 | -  |
|   ❌ 却下 | 中央 Config クラス                         |  -    |   -    | 投下労力に見合わない | - |
|   ❌ 却下 | i18n ホットスワップ                        |  -    |   -    | WordPress と相性が悪い | - |
|   ⚪ 保留 | CSS テーマ切り替え                         |  1日  |   低   | 必要時のみ | -  |
|   ⚪ 保留 | 観測バッファ（autonomous 版）              | 3-5日 |   高   | passive 版が安定後 | - |

**v3 での順序変更**：
- P0-3（event schema）を P0-2（tool gate）より **先に** 実装（tool gate の SSE 通知が event schema に依存）
- P1-2（UI feedback + watchdog）を P1-1（integrity mode）より **先に** 実装（zombie state 対策が integrity block より先に必要）

### 実装進捗（2026-05-15）

| PR | 項目 | 状態 | 実装メモ |
|----|------|------|----------|
| PR1 | P0-1 input-role-resolver | ✅ 完了 | `includes/core/class-mpu-input-role.php` を追加。`admin / system / subscriber / visitor` と hardcoded whitelist を導入 |
| PR2 | P0-3 Session Event Schema | ✅ 完了 | `includes/llm/class-mpu-session-event.php` と `window.MPU_EVENTS` を追加。SSE は `kind + payload + eventId + ts` envelope を受け取れる |
| PR3 | P0-2 Server-side Tool Gate | ✅ 完了 | tool exposure と tool execution の両方で role gate。各 ability の `permission_callback` も `MPU_Input_Role` 経由へ整理 |
| PR4 | P1-2 UI feedback + client-side watchdog | ✅ 完了 | 45 秒 watchdog、`AbortController.abort()`、user history rollback、`data-mpu-stream-state`、`tool.request` handler を実装 |
| PR5 | P1-1 chat-integrity 三段モード | ✅ 完了（後端のみ） | `audit / warn / block` を `chat-integrity.php` の verify decision 層に追加。デフォルトは `audit`。admin UI / グラフは後続 |
| PR6+ | P2 系列 | ⏸ 保留 | P0 + P1 の観察期を優先。P2 は mismatch log / watchdog / tool gate の安定確認後に再開 |

**現在の運用方針**：
- `chat_integrity_mode` は後台 UI なし。常数 `MPU_CHAT_INTEGRITY_MODE`、option `mp_ukagaka['chat_integrity_mode']`、filter `mpu_chat_integrity_mode` で制御する。
- 当面は `audit` のまま `logs/checksum-mismatch.log` を観察し、必要なら `warn` に上げる。`block` は admin opt-in / staging 検証後。
- P2（Runtime State / PHPUnit / Observation Buffer）は急がない。まず SSE watchdog の誤殺、checksum mismatch の残存頻度、tool gate の誤判定を観察する。

---

## 🔴 P0 — 最優先（地基整備）

### P0-1. `mpu_resolve_input_role()` ヘルパー導入

**avatar-ui の実装：**
`src/services/input-role-resolver.ts` が `Source × Channel × User` から `InputRole` を解決する純関数。
`InputGate`（権限制御）と prompt builder の両方が同じ関数を見ることで、
「prompt に書いた制限」と「実際の権限チェック」が必ず一致する。

**MP-Ukagaka の現状（要訂正）：**
初稿は「abilities に `permission_callback` がない」と書いたが、これは誤り。
実際は：

| Ability | permission_callback |
|---|---|
| `Visitor_Pulse_Ability` | `current_user_can('manage_options')` |
| `AI_Crawler_Ability` | `current_user_can('manage_options')` |
| `Wp_Bot_Blocker_Ability`（×3 register） | `current_user_can('manage_options')` |
| `Wp_PostViews_Ability` | `return true`（公開読取） |

問題は「権限チェックがない」ことではなく、**役割モデルが admin/非 admin の二元で、中間階層がない** こと。

- cron からの自動 trigger（diary, auto-talk）は user context がないため `current_user_can()` が常に false
- 訪客が ability を直接叩くシナリオの想定がない
- `Wp_PostViews_Ability` だけ `return true` という不統一

**提案：**

```php
// includes/core/class-mpu-input-role.php（新規）
class MPU_Input_Role {
    const ADMIN   = 'admin';   // current_user_can('manage_options')
    const SYSTEM  = 'system';  // cron / auto-talk / diary
    const VISITOR = 'visitor'; // 通常の匿名訪問者
    const SUBSCRIBER = 'subscriber'; // 登録ユーザー

    public static function resolve( array $context = [] ): string {
        if ( isset( $context['source'] ) && $context['source'] === 'cron' ) {
            return self::SYSTEM;
        }
        if ( current_user_can('manage_options') ) {
            return self::ADMIN;
        }
        if ( is_user_logged_in() ) {
            return self::SUBSCRIBER;
        }
        return self::VISITOR;
    }

    public static function can_use_ability( string $ability_name, string $role ): bool {
        // ホワイトリスト：プロンプトインジェクション耐性のためハードコード
        $whitelist = [
            self::ADMIN      => '*',
            self::SYSTEM     => [ 'mp-ukagaka/get-visitor-pulse', 'mp-ukagaka/get-postviews' ],
            self::SUBSCRIBER => [ 'mp-ukagaka/get-postviews' ],
            self::VISITOR    => [ 'mp-ukagaka/get-postviews' ],
        ];
        $allowed = $whitelist[ $role ] ?? [];
        return $allowed === '*' || in_array( $ability_name, $allowed, true );
    }
}
```

**なぜこれが P0 か：**
- 後続の Tool Gate（P0-2）、Observation Buffer（P2-3）、auto-talk 改善が全てこの role 概念に依存する
- 今の二元モデルのまま機能を増やすと、各所に `if ( current_user_can() )` が散らばって収拾がつかなくなる
- 純関数なのでテストしやすく、将来の変更コストも低い

> [!NOTE] **Reviewer 統合（v3）**
> - **CODEX**：「最優先で正しい。`current_user_can()` は WordPress 権限を見る関数で、LLM 入力の由来・責任主体・許可される tool surface を表す語彙ではない」
> - **Gemini**：「絶対に P0。Capability と Identity/Role を分離することで宣言的なセキュリティ管理が可能に」
> - **共通注意点（Codex）**：実装は framework 化しない。`MPU_Input_Role::resolve()` と `can_use_ability()` の **2 つの純関数寄り helper のみ** に留め、既存の `permission_callback` と `mpu_get_mcp_tools_for_llm()` に差し込む。クラス階層・DI コンテナ・event observer などは追加しない。
> - **PR 分割（Codex）**：P0-1 単独で 1 つの PR を切る。P0-2（tool gate 変更）と混ぜない理由は「role 定義の追加」と「tool exposure の変更」が同 PR に混ざると review しづらいため。

---

### P0-2. Server-side Tool Gate（role 拡張版）

**avatar-ui の実装：**
`InputGate` は二段防御：
1. `buildTools()` で role に基づきツールリストをフィルタ（LLM に渡す段階）
2. `handleToolCall()` で実行直前にも再チェック（万が一 LLM が別経路で呼んだ場合）

**MP-Ukagaka の現状：**
- `mpu_get_mcp_tools_for_llm()` が `current_user_can('manage_options')` で**全ツールを一括 hide**（`abilities-integration.php:39-42`）
- 各 ability の `permission_callback` も同じく `manage_options` を見る → 二段防御は実質できているが、両者とも admin/非 admin の二元のみ

**提案：**

```php
// abilities-integration.php
function mpu_get_mcp_tools_for_llm( $provider = 'openai', array $context = [] ) {
    if ( ! mpu_is_mcp_active() ) return [];

    $role = MPU_Input_Role::resolve( $context );
    $abilities = wp_get_abilities();
    $formatted_tools = [];

    foreach ( $abilities as $ability ) {
        $ability_name = $ability->get_name();
        if ( ! MPU_Input_Role::can_use_ability( $ability_name, $role ) ) {
            continue;
        }
        // 既存の formatting ロジック
    }
    return $formatted_tools;
}

// 各 ability の permission_callback も role 経由に統一
'permission_callback' => function() {
    $role = MPU_Input_Role::resolve();
    return MPU_Input_Role::can_use_ability( 'mp-ukagaka/get-visitor-pulse', $role );
},
```

> [!NOTE]
> 既存の `manage_options` チェックは保たれる（admin role の定義そのもの）。後方互換性あり。

> [!NOTE] **Reviewer 統合（v3）**
> - 両者一致：role gate は role resolver ベースに統一すべき。
> - **PR 順序（Codex）**：P0-1 → P0-3 → **P0-2** の順で出す。理由は「role 定義」と「tool exposure 変更」を別 PR に分けると、後で問題が出た時に bisect しやすいため。
> - whitelist は **ハードコード必須**（option として可変にしない）。プロンプトインジェクションで visitor が admin tool を叩く設定を作られないようにするため。

---

### P0-3. Session Event Schema 統一

**avatar-ui の実装：**
`src/shared/session-event-schema.ts` が `stream.item / approval.* / monitor.item / session.state` の
discriminated union を zod で定義し、**WS / IPC / テストが同じ型を見る**。

**MP-Ukagaka の現状：**
- SSE は `delta / status / nonce / done / error` の 5 種だが、payload 構造は `streaming-helpers.php` 内で個別に組まれている
- 前端 SSE handler（`ukagaka-chat.js`）は型なしの switch
- chat history、touch event、greeting、weather event などは別経路で個別実装

→ 新しい event を増やすたびに「PHP 側で送ったが JS 側で受けない」「フィールド名が片方だけ camelCase」が発生しやすい。

**提案：**

```php
// includes/llm/class-mpu-session-event.php（新規）
class MPU_Session_Event {
    const STREAM_DELTA   = 'stream.delta';
    const STREAM_STATUS  = 'stream.status';
    const STREAM_DONE    = 'stream.done';
    const STREAM_ERROR   = 'stream.error';
    const TOOL_REQUEST   = 'tool.request';
    const TOOL_RESULT    = 'tool.result';
    const NONCE_REFRESH  = 'nonce.refresh';

    public static function build( string $kind, array $payload ): array {
        return [
            'eventId' => wp_generate_uuid4(),
            'ts'      => gmdate('c'),
            'kind'    => $kind,
            'payload' => $payload,
        ];
    }
}
```

```javascript
// js/ukagaka-events.js（新規 / 単一の受信ハブ）
window.MPU_EVENTS = {
  STREAM_DELTA:  'stream.delta',
  STREAM_STATUS: 'stream.status',
  STREAM_DONE:   'stream.done',
  STREAM_ERROR:  'stream.error',
  TOOL_REQUEST:  'tool.request',
  TOOL_RESULT:   'tool.result',
  NONCE_REFRESH: 'nonce.refresh',
};
```

**WebSocket は不要**。SSE と既存の REST レスポンスに同じ envelope を使うだけで十分。
これは後続の UI 状態フィードバック（P1-2）、observation buffer（P2-3）の前提となる。

> [!NOTE] **Reviewer 統合（v3）**
> - **CODEX**：「WebSocket は入れない方がよい。サーバー構成、キャッシュ、プロキシ、WordPress hosting 互換性のリスクを増やす」
> - **Gemini**：「PHP/WordPress のステートレスなリクエスト駆動環境において、WebSocket 維持はインフラコストが高すぎる。SSE + REST envelope で 100% 享受できる」
> - **段階移行（Codex）**：最初の PR では **chat stream（`stream.delta/status/done/error`）と tool 関連（`tool.request/result`）の 6 種だけ** envelope に乗せる。touch / greeting / weather / nonce.refresh のような既存 event は **後続 PR で順次移行**。一度に全 event を envelope 化しようとするとレビューも regression テストも肥大化する。
> - **将来予約（Codex）**：approval-hub（first-response-wins）の概念を将来導入する可能性に備え、event schema に `approval.request / approval.resolve` を **コメントレベルで予約**しておく（実装は不要、定数のみ）。これで承認 request id・timeout・deny default の語彙が後付けで衝突しない。

---

## 🟡 P1 — UX 直結

### P1-1. chat-integrity 凍結モード（opt-in 設定付き）

**avatar-ui の実装：**
`integrity-manager.ts` は `report()` を呼ばれた瞬間に `frozen = true` ラッチし、
以後の `handleStreamPost()` で `if (isFrozen()) return` で全停止。

**MP-Ukagaka の現状：**
`chat-integrity.php` は checksum mismatch を `logs/checksum-mismatch.log` に記録するのみ（観測モード）。
CLAUDE.md にも「currently operates in observational (audit) mode」と明記。

**提案（v3 改訂：audit / warn / block 三段モード）：**

初稿は「return 文一行で凍結に昇格」と書いたが、現実には**いきなり hard block にすると false positive で正常リクエストが死ぬ**。
特に bot-blocker 関連 commit が直近で入っており、bot 流量との相互作用が未検証。

Codex の助言に従い、**`audit` / `warn` / `block` の三段** を導入：

| mode | 挙動 | 用途 |
|------|------|------|
| `audit`（default） | log のみ、リクエストはそのまま処理 | 既存動作。観察期 2 週間の標準 |
| `warn` | log + SSE で `stream.warning` event を送信、リクエストは継続 | UI に注意を表示しつつ機能停止しない中間段階 |
| `block` | log + 403 で停止 | 観察期で false positive ゼロを確認後の本格運用 |

```php
// admin 設定: $mpu_opt['integrity_mode'] = 'audit' | 'warn' | 'block'
// デフォルトは 'audit'（既存動作）

if ( ! $is_valid ) {
    $mode = mpu_get_option()['integrity_mode'] ?? 'audit';
    mpu_log( "[INTEGRITY] Checksum mismatch (mode={$mode})", 'error' );
    mpu_chat_integrity_dump_mismatch( $session_id, $expected, $actual, $verify_history );

    if ( $mode === 'warn' ) {
        // SSE 経由で UI に通知（処理は継続）
        mpu_sse_send_event( MPU_Session_Event::build(
            MPU_Session_Event::STREAM_WARNING,
            [ 'reason' => 'integrity_mismatch', 'severity' => 'low' ]
        ));
    } elseif ( $mode === 'block' ) {
        return new WP_Error(
            'mpu_integrity_violation',
            __( 'Chat history integrity check failed.', 'mp-ukagaka' ),
            [ 'status' => 403 ]
        );
    }
}
```

加えて管理画面に「過去 7 日の mismatch 件数」グラフを表示し、admin が `warn` → `block` に進める判断材料を提供する。

> [!NOTE] **Reviewer 統合（v3）**
> - **Codex の追加論点**：avatar-ui の `integrity-manager` は `warn()` と `report()` を分離している。これを参考に MP-Ukagaka でも：
>   - `warn` 相当 = API timeout / 一時的な network error → 自動回復可能、UI に軽い通知
>   - `report` 相当 = checksum mismatch / 契約違反 → 永続記録、admin 通知、必要なら block
>
>   この分類表を作っておくと、UI が "考え中" のまま固まる zombie state を減らせる（→ P1-2 の client-side timeout と組み合わせて完成）。
> - **元 v2 の警告**：avatar-ui は単一ユーザー前提なので「凍結 = 自分が困る」だけだが、MP-Ukagaka は多数の訪客が同時に使うため、誤凍結のコストは桁違いに大きい。三段モードで段階移行する判断はこの非対称性に基づく。

---

### P1-2. UI リアルタイム状態フィードバック

P0-3 の Session Event Schema が完成していれば、これは追加コストが小さい。

**提案（初稿のものをほぼ踏襲）：**

```javascript
// ukagaka-chat.js
const mpuStateIndicator = {
  show(state) {
    const el = document.getElementById('mpu-state-badge');
    if (!el) return;
    el.dataset.state = state;
    el.textContent = window.mpuI18n?.[state] ?? state;
    el.style.display = 'block';
  },
  hide() {
    const el = document.getElementById('mpu-state-badge');
    if (el) el.style.display = 'none';
  },
};

// SSE handler（既存に統合）
function onSseEvent(event) {
  switch (event.kind) {
    case window.MPU_EVENTS.STREAM_STATUS:
      mpuStateIndicator.show(event.payload.state);
      break;
    case window.MPU_EVENTS.STREAM_DONE:
    case window.MPU_EVENTS.STREAM_ERROR:
      mpuStateIndicator.hide();
      break;
  }
}
```

ラベル（三言語）：

| state     | 日本語               | 繁體中文  | English           |
| --------- | -------------------- | --------- | ----------------- |
| thinking  | 考え中...            | 思考中... | Thinking...       |
| streaming | 話している...        | 回應中... | Responding...     |
| tool      | 調べてる...          | 查詢中... | Looking up...     |
| error     | エラーが発生しました | 發生錯誤  | An error occurred |

#### P1-2 追加要件（v3）：Client-side タイムアウト & idle fallback ⚠️ 必須

> [!IMPORTANT] **Gemini の指摘を昇格**
> 状態を表示するだけでは不十分。**応答が一定時間途絶えたら自動で `idle` に復帰し、エラー吹き出しを出す**復帰機構を JS 側に持たせる。これがないと、API timeout や network error の際にゴーストが「考え中...」のまま固まる **zombie state** が発生する。
> P1-1 の `audit/warn/block` 三段モードと役割分担：
> - **サーバー側（P1-1）**：契約違反の検知（checksum mismatch）
> - **クライアント側（P1-2）**：応答途絶の検知（API timeout, network drop）
> 両方が揃って初めて UI が固まらない。

```javascript
// ukagaka-chat.js — SSE 受信時の watchdog
let mpuStreamWatchdog = null;
const MPU_STREAM_TIMEOUT_MS = 45000; // 最終 event から 45 秒で fallback

function mpuArmWatchdog() {
  if (mpuStreamWatchdog) clearTimeout(mpuStreamWatchdog);
  mpuStreamWatchdog = setTimeout(() => {
    mpuStateIndicator.show('error');
    mpuShowBalloon(window.mpuI18n?.streamTimeout ?? 'No response. Please try again.');
    mpuStateIndicator.hide();
    mpuChatModeReset(); // 送信ボタン再有効化、stream 接続クローズ

    // [v3.2 Gemini 補足] タイムアウト時は進行中の fetch/SSE 接続を確実に切断する
    if (window.mpuStreamAbortController) {
        window.mpuStreamAbortController.abort();
    }
  }, MPU_STREAM_TIMEOUT_MS);
}

function onSseEvent(event) {
  mpuArmWatchdog(); // 任意の event 受信で watchdog をリセット
  switch (event.kind) {
    case window.MPU_EVENTS.STREAM_STATUS:
      mpuStateIndicator.show(event.payload.state);
      break;
    case window.MPU_EVENTS.STREAM_DONE:
    case window.MPU_EVENTS.STREAM_ERROR:
      clearTimeout(mpuStreamWatchdog);
      mpuStateIndicator.hide();
      break;
  }
}
```

タイムアウト値の根拠：
- LLM streaming は通常 1 分以内に最初の delta が来る
- 45 秒の根拠：tool call → LLM 二度目の think で時間がかかる場合の 95 percentile を想定
- 短すぎる（10 秒など）と正常な long-running tool で誤発火する

> [!NOTE]
> watchdog は SSE event を受信するたびにリセットされるため、stream 中に delta が流れている限り発火しない。発火するのは「最後の event から 45 秒間何も来ない」場合のみ。

---

## 🟢 P2 — Nice-to-have

### P2-1. Ghost Runtime State helper（transient ベース）

**初稿からの重要な訂正：**
初稿は `mpu_opt['runtime_state']` への保存を提案したが、これは **WordPress では強い反パターン**：

- `mpu_opt` 全体が serialized array → 1 フィールド更新で全体を deserialize/reserialize
- options API は object cache に乗るため、頻繁な `update_option()` は他のリクエストにも波及
- 並行 SSE で race condition が発生しうる

**修正提案：transient ベース、per-session（または per-user）：**

```php
// includes/core/runtime-state.php（新規）
function mpu_set_runtime_state( string $state, ?string $session_key = null ): void {
    $session_key = $session_key ?? mpu_get_session_key();
    set_transient( "mpu_state_{$session_key}", [
        'state' => $state,
        'ts'    => time(),
    ], 5 * MINUTE_IN_SECONDS );
}

function mpu_get_runtime_state( ?string $session_key = null ): ?array {
    $session_key = $session_key ?? mpu_get_session_key();
    $val = get_transient( "mpu_state_{$session_key}" );
    return is_array( $val ) ? $val : null;
}
```

補論 A の語意セット（`idle / thinking / speaking / chatting / sleeping / waking / tool_running / suspended / error`）はそのまま採用する価値がある。
**短期は helper と表示のみ**、各機能が依存し始めるのは P0-3 の event schema が安定してから。

---

### P2-2. PHPUnit 純函式テスト（4 ファイル限定）

**初稿からの方針調整：**
avatar-ui の 403 件規模は WordPress プラグインでは過剰。最小 ROI のものに絞る：

```bash
composer require --dev phpunit/phpunit
```

WordPress を bootstrap せずに動く純関数のみ対象：

| ファイル | テスト内容 |
|---|---|
| `provider-helpers.php` | JSON encode、tool message format、provider headers |
| `tool-loop-guard.php` | 同一引数 N 回でループ検知 |
| `chat-integrity.php`（checksum 関数） | filter_messages、compute_checksum の冪等性 |
| `class-mpu-input-role.php`（P0-1） | role 解決とホワイトリスト |

> [!IMPORTANT]
> Code_Quality_Hardening_Plan が「PHP を触る前に lint gate」と結論付けている。
> PHPUnit 導入はその延長線。lint gate 完成後に着手。

---

### P2-3. Observation Buffer（passive-only）

**avatar-ui の実装：**
`observation-buffer.ts` は in-memory 配列に観測を蓄積し、次の LLM ターン直前で `drainObservationContext()` で flush。
**自発的に LLM を起動はしない**。

**MP-Ukagaka 版（補論 D 路線を採用）：**

```php
// includes/core/class-mpu-observation-buffer.php（新規）
class MPU_Observation_Buffer {
    private static function key( ?string $scope = null ): string {
        // visitor context を global key に混ぜない。scope は session/visitor hash 由来に限定する。
        $scope = $scope ?: mpu_get_session_key();
        return 'mpu_obs_buffer_' . hash( 'sha256', $scope );
    }

    public static function push( string $type, string $content, ?string $scope = null ): void {
        $key = self::key( $scope );
        $buf = get_transient( $key ) ?: [];
        $buf[] = [ 'type' => $type, 'content' => $content, 'ts' => time() ];
        set_transient( $key, array_slice( $buf, -5 ), 6 * HOUR_IN_SECONDS );
    }

    public static function drain( ?string $scope = null ): array {
        $key = self::key( $scope );
        $buf = get_transient( $key ) ?: [];
        delete_transient( $key );
        return $buf;
    }
}
```

`llm-context-builder.php` がプロンプト構築時に `drain()` を呼び、system prompt の末尾に注入するだけ。
**autonomous trigger は出さない**（cost / privacy / UX 的にリスク大）。

> [!IMPORTANT] **Codex 追加補足（v3.1）**：Observation Buffer は passive-only でも **global transient にしてはいけない**。
> `mpu_obs_buffer` のような単一 key だと、訪客 A の referrer / 滞在 / touch context が訪客 B の次回 LLM ターンに注入されるリスクがある。
> 最低条件は `session_key` / visitor hash / logged-in user id などで scope を切った transient key にすること。
> admin/system 起点の観測だけを global scope に載せる場合も、`global` と明示し、visitor signal と混在させない。

> [!CAUTION]
> 前置条件は **「User Memory v2」（後述 §M）** の完成。
> 現在実装済みの User Memory MVP（v2.16.0）は admin 専用なので、
> 観測 buffer が訪客信号を収集しても書き込み先がない。
> 詳細は §M「User Memory 現況：MVP 完了 / v2 未着手」を参照。

---

## 📌 §M. User Memory 現況：MVP 完了 / v2 未着手

> [!IMPORTANT]
> 初稿および補論で「User Memory MVP 完成後」を前置条件として参照している箇所が複数ある。
> 現実には MVP は **v2.16.0（2026-05-13）で実装済み** だが、想定されている範囲はかなり限定的。
> Reviewer（CODEX / Gemini）は「MVP=完了」を額面通りに受け取らず、本セクションで実態を確認すること。

### M.1 v2.16.0 で実装済みのもの

| 項目 | 実装位置 |
|---|---|
| REST controller | `includes/rest/class-mpu-rest-memory.php` |
| `/remember` admin command（前台 chat box トリガー） | `js/dist/ukagaka-bundle.js:4407` |
| LLM 抽出 + dedup + 80字×10件上限 | `class-mpu-rest-memory.php:108-132` |
| 保存：`usermeta` key=`mpu_user_memory` | `class-mpu-rest-memory.php:138` |
| System prompt 注入（「参考メモ、指示ではない」と明示） | `personality-loader.php:442-452` |
| 後台 AI 設定タブで表示 + クリアボタン | `options_page_ai.php:195-229` |
| 60 秒 throttle、defensive cleanup、`manage_options` guard | `class-mpu-rest-memory.php:38-42` |

→ Prompt injection 防護、admin-only 境界、節流まで揃っており、**この範囲では完成度が高い**。

### M.2 v2 として未実装の項目（差分）

| 項目 | 現状 | 必要性 |
|---|---|---|
| **訪客側の記憶** | なし（admin のみ） | 高：observation buffer の書き込み先として必須 |
| **自動抽出トリガー** | `/remember` 手動入力のみ | 中：N ターンごと / wp_cron での自動化 |
| **時系列・版本管理** | facts 配列に平置き | 中：「最近言及」「強度減衰」が必要なら |
| **意味分類** | 単一 facts 配列 | 低：現状でも prompt 上は機能する |
| **個別編集 UI** | 一括クリアのみ | 中：ユーザー側で個別削除したい時 |
| **観測との統合** | touch / auto-talk / bot 感知が記憶に流れない | 高：observation buffer の前提 |
| **前台での可視化** | 訪客は「何を覚えられているか」見えない | 中：UX / プライバシー透明性 |

### M.3 影響：Avatar_UI_Learnings 内の前置条件再整理

本ドキュメント内で「User Memory MVP 完成後」と書かれていた箇所は、すべて **「User Memory v2 完成後」** に読み替えること。
具体的には：

- **P2-3 Observation Buffer**：MVP では訪客側の保存先がないため、現状 MVP では着手不可
- **将来候補 §9 観測バッファ / 自発行動（v1 初稿）**：同上、加えて autonomous trigger なら **訪客 memory + 観測統合**の双方が必要

### M.4 User Memory v2 の概念設計（粗案）

正式な設計は別ドキュメントに切り出すべきだが、avatar-ui との対比で粗案だけ残す：

```
┌─ Canonical Profile（read-mostly, plugin が管理）
│   └─ ghost manifest / personality.md / instructions.md  ← AI は書けない
│       └─ avatar-ui の refs/ ディレクトリ相当（v3 追加）
│
├─ User Memory（admin / per-user, 既存）
│   └─ usermeta: mpu_user_memory  ← v2.16.0 で完成
│       └─ namespace: scratch（AI が抽出して書く）
│
├─ Visitor Memory（per-session / per-IP-hash, 新規）
│   └─ transient: mpu_visitor_mem_{hash}  ← v2 の核心
│       ├─ 滞在時間、訪問頁、touch 履歴、bot/human 判定
│       └─ TTL: 24h-7d、明示同意なき長期保存はしない
│
└─ Observation Buffer（scoped, 短期）
    ├─ transient: mpu_obs_buffer_{session_hash}  ← visitor / session scoped
    ├─ transient: mpu_obs_buffer_user_{user_id}  ← logged-in user scoped
    └─ transient: mpu_obs_buffer_global          ← admin/system only
        └─ 次の LLM ターン直前で drain → context に注入
            ├─ 書き込み先（admin facts / visitor mem）はここで判定
            └─ visitor は admin 級の精度で書き込まない（プライバシー境界）
```

> [!NOTE] **Codex の追加論点（v3）**：namespace 境界
> avatar-ui の `Avatar Space` には **`refs/` という read-only ディレクトリ** があり、AI が自由に書ける scratch/memory と、人間（plugin）が管理する canonical profile / docs / policy を厳格に分離している。
>
> MP-Ukagaka では filesystem を開ける話ではないが、**memory namespace の read/write 境界として取り込む** べき：
> - **Canonical**（read-only for AI）：`personality.md`, `instructions.md`, `manifest.json`, ghost decorations
> - **Scratch**（read/write for AI）：User Memory（admin facts）、Visitor Memory、Observation Buffer
> - prompt 構築時に両方注入するが、AI への指示は「**canonical を変更しようとせず、scratch にだけ書け**」と明示
>
> これがないと、AI が「instructions.md を更新しました」のような幻覚 tool call を出した際に boundary が曖昧になる。

**判断軸：v2 を作るべきか？**

- 観測 buffer / 自発発話を真にやりたいなら v2 は必須
- 「admin が自分の記憶を持てる」だけで満足するなら MVP で十分、v2 不要
- 中間案：**まず Observation Buffer だけ「揮発性 only」で作り、admin 側にだけ流し込む**（訪客 memory なし、buffer 内容は次ターンで消える）

> [!NOTE]
> v2 設計は **訪客プライバシー / GDPR / 同意取得**の論点を含むため、
> 技術判断のみで決められない。User Memory v2 着手前に運用方針の整理が必要。
> Reviewer はここを「未解決の戦略課題」と認識して評価してほしい。

---

## 🆕 §F. Reviewer 追加論点（v3 で本文統合）

CODEX / Gemini が初稿 6 問への回答で **追加で指摘した論点** を、本文の該当節に分散して反映した。一覧として再掲：

| # | 出典 | 論点 | 反映先 |
|---|------|------|--------|
| F-1 | Codex Q1 | role helper は framework 化しない（純関数 2 つに留める） | §P0-1 reviewer note |
| F-2 | Codex Q2 | event envelope は最初 chat + tool の 6 種だけ | §P0-3 reviewer note |
| F-3 | Codex Q2 | approval-hub 用に `approval.request / resolve` を将来予約 | §P0-3 reviewer note |
| F-4 | Codex Q6 | integrity を `warn` / `report` で分離（timeout vs 契約違反） | §P1-1 三段モード |
| F-5 | Gemini Q6 | **Client-side timeout & idle fallback（zombie state 対策）** | §P1-2 必須要件 |
| F-6 | Codex Q6 | Avatar Space `refs/` の namespace 境界思想 | §M.4 canonical/scratch 分離 |
| F-7 | Codex 実行順序 | PR 5 段分割（後述「推奨実行順序」に反映） | §推奨実行順序 |

これらは v3 時点で別セクションを新設するのではなく、**各機能セクションに直接埋め込んだ** ことで、実装時にひとつの節を読めば reviewer 合意も含めて完結するようにした。

---

## ⚪ 降級 — 「やるべき」を取り下げる項目

### D-1. 並行 LLM ロック機構（初稿 §2）

**初稿の問題：**
`session_id()` を使う案だったが、**WordPress は標準で PHP session を使わない**。
`session_start()` を呼ぶと page cache（W3 Total Cache, WP Super Cache, Cloudflare 等）が破壊される。

**現実的な代替：**

1. **フロントエンド側で十分**：`mpuChatModeActive` フラグ + 送信ボタン disable
2. **既存の Cloudflare Turnstile が IP 級 rate limit を提供済み**
3. PHP 側で本当に並行を防ぎたい場合：`get_current_user_id()` ベース（匿名は cookie hash）
4. `wp_loaded` から `shutdown` までが 30 秒以下のリクエストにロックを 30 秒かけるのは過剰

→ 「30 秒 lock」は本物の問題が観測されてから対応。先に設計するのは過剰防衛。

---

## ❌ 却下 — 投下労力に見合わない項目

### X-1. 中央 Config クラス（初稿 §5）

WordPress エコシステムは array option が標準。`MPU_Config::get('foo')` に置き換えても、
中身は結局 `mpu_get_option()['foo'] ?? $defaults['foo']`。
**OO 潔癖以外のメリットがない**。むしろ後続貢献者が WordPress 慣習から外れて混乱する。

→ デフォルト値が散在している問題は、defaults を `mpu-ukagaka.php` の定数 or `core-functions.php` 末尾に集約するだけで解決。

### X-2. i18n ホットスワップ（初稿 §8）

WordPress の locale は per-request、per-user。`switch_to_locale()` で切り替え可能だが、
**フロント側で locale を切っても、SSE で流れてくる文字列のサーバー側 locale とズレる**。
管理画面で言語を切り替えるユースケース以外、需要が薄い。

→ JS 文字列を `wp_localize_script()` の `mpuI18n` オブジェクトに集中させる「整理」だけ採用（これは普通の refactor）。

---

## 🆕 初稿が見落としていた論点

### N-1. WordPress on-demand と avatar-ui 常駐の根本的な非対称

| 観点          | avatar-ui                     | MP-Ukagaka                        |
| ------------- | ----------------------------- | --------------------------------- |
| **実行環境**  | Electron（常駐プロセス）      | PHP on demand（リクエスト単位）   |
| **状態管理**  | メモリ内 FSM + `state.json`    | WordPress options/transients     |
| **並行制御**  | Promise chain（単一プロセス） | リクエスト毎独立、共有メモリなし |
| **WebSocket** | ネイティブサポート            | 要追加インフラ（実用上不要）      |
| **テスト**    | Vitest 403 件                 | 手動 + PHPUnit 純関数のみで十分    |
| **ユーザー**  | 単一ユーザー設計              | 多数の匿名訪問者 + admin          |
| **Cron**      | プロセス内 setInterval        | wp_cron（HTTP 駆動、不正確）      |

これは初稿にも書かれていたが、**ほとんどの「移植」提案がこの非対称を踏まえていない**ことに注意。
特に `field-orchestrator` の `enqueue()` パターンは PHP には移植不可能。

### N-2. 既存資産との整合性

MP-Ukagaka には avatar-ui にはない資産が既にある：

- `tool-loop-guard.php` — 既に `MPU_MAX_TOOL_TURNS = 5` で実装済み
- `provider-helpers.php` — 4 provider 共通の helper 群が既に抽出済み
- REST OO 化（`MPU_REST_Base`）— v2.9.2 で完了
- Personality system（`personality-loader.php`）— avatar-ui の `BEING.md` 相当が既にある

→ avatar-ui から借りるのは「**まだ無いもの**」に絞る。既存と重複する設計の置き換えは ROI 低。

### N-3. avatar-ui の真の貢献は「ランタイム思考」（補論まとめ）

avatar-ui の最大の価値は WebSocket でも Roblox でもなく、**「AI キャラとの相互作用を runtime として捉える」** という設計姿勢にある：

- 生命週期がある（FSM）
- イベント形式が定義されている（session-event-schema）
- 入力源と権限境界が明示されている（input-role-resolver + InputGate）
- 状態回復策略がある（integrity-manager の RECOVERY_POLICY）

MP-Ukagaka は人格・睡眠モード・訪客信号・REST/SSE・MCP abilities・User Memory など材料は揃っている。
**次のステージは「機能を増やす」ではなく「これらを runtime として収斂させる」**。
P0 の 3 項目はこの収斂の最低限の下地である。

---

## 推奨実行順序（v3：Codex の PR 5 段分割を採用）

v2 までは Phase 単位の概算だったが、v3 では **Codex の PR 分割推奨** に従って 1 PR = 1 焦点にする。
理由：「role 定義の追加」と「tool exposure の変更」を同 PR に混ぜると review が難しく、regression が出た時の bisect も困難になる。

```
PR1（約 1 日）— P0-1  input-role-resolver
  ├─ MPU_Input_Role::resolve() / can_use_ability() の 2 関数のみ
  ├─ 既存呼び出し箇所への差し込みはこの PR では行わない
  └─ PHPUnit テストを同 PR に同梱（純関数なので可能）

PR2（約 1 日）— P0-3  Session Event Schema 統一
  ├─ MPU_Session_Event 定数クラス + JS 側 MPU_EVENTS
  ├─ 最初は chat stream + tool の 6 種のみ（touch/greeting/weather は後続）
  └─ approval.request/resolve は予約（コメントのみ、実装なし）

PR3（約 0.5 日）— P0-2  Server-side Tool Gate（role 拡張）
  ├─ mpu_get_mcp_tools_for_llm() を role resolver ベースに
  ├─ 各 ability の permission_callback を MPU_Input_Role 経由に統一
  └─ PR1 と PR2 が両方 main に入っていることが前提

PR4（約 1.5 日）— P1-2  UI 状態フィードバック + Client-side watchdog
  ├─ PR2 の event envelope を消費する receiver 実装
  ├─ ⚠️ Client-side timeout（45 秒で自動 idle 復帰）も同 PR に必須
  └─ なぜ P1-1 より先か：先に「UI が固まらない」基礎を入れてから
     サーバー側 integrity を強化すべき（順序逆だと block 時に zombie 状態になる）

PR5（約 1 日）— P1-1  chat-integrity 三段モード（audit/warn/block）
  ├─ ✅ 後端三段モードは実装済み（デフォルト audit）
  ├─ `mpu_chat_integrity_mismatch` action hook で metrics 接続可能
  └─ 管理画面 UI / 過去 7 日 mismatch グラフは後続 PR に分離

────────────────────────────────────────
以下は別フェーズ（P0 + P1 観察期後、必要に応じて順次）

PR6（約 0.5 日）— P2-1  Ghost Runtime State helper（transient）
PR7（約 1-2 日）— P2-2  PHPUnit 純函式テスト 4 ファイル
PR8（要 §M.3 解決後）— P2-3  Observation Buffer（passive-only）
```

**最初の一手は依然として PR1（P0-1 input-role-resolver）。**
理由：後続の Tool Gate / Observation Buffer / auto-talk 改善が全てこの role 概念に依存し、
今ここを定義しないと各機能が個別に `current_user_can()` をばら撒いて回収不能になる。

**v2 からの順序変更点**：
- v2 では P1-1 → P1-2 の順だったが、v3 では **P1-2 を先**にする（Codex 推奨）。理由は zombie state 対策が integrity block より先に必要なため。
- PR2（P0-3 event schema）を PR3（P0-2 tool gate）より先に出す理由は、tool gate の SSE 通知（block された時の `stream.warning`）が event schema に依存するため。

---

## レビュアーへの問い（CODEX / Gemini 向け）

このドキュメントを評価する際に、以下を特に検討してほしい：

1. **P0-1（input-role-resolver）を本当に最優先にすべきか？**
   - 代替案：先に P1-1（integrity 凍結）で「セキュリティ網」を張ってから role を整理する順序もあり得る
   - 判断軸：今の二元 role モデルで実害が出ているか / 出る可能性があるか

2. **Session Event Schema（P0-3）は WebSocket なしで十分か？**
   - SSE + REST envelope だけで avatar-ui の event bus 思想を再現できるか
   - touch event / greeting / weather のような非 LLM event も同じ envelope に乗せるべきか

3. **Observation Buffer の passive-only 縛りは妥当か？**
   - autonomous 化（自発発話）への移行条件をどう設計するか
   - 訪客プライバシー（IP / referrer / 滞在時間）を buffer に入れる際の boundary
   - **§M で示した「揮発性 only 中間案」と「User Memory v2 完全実装」のどちらを先に進めるか**

4. **却下した中央 Config / i18n hot-swap は本当に却下でよいか？**
   - 中央 Config は将来 settings 画面の自動生成にも使えるが、それを優先する状況か
   - i18n hot-swap は管理画面プレビュー機能と組み合わせると価値が出る可能性

5. **PHPUnit を 4 ファイルに絞るのは慎重すぎないか？**
   - REST controller 層もテスト対象に含めるべきか（WP bootstrap が必要になる）
   - 実害（regression）が出ていない現状で、テスト基盤投資の優先度

6. **このドキュメントが見落としている avatar-ui の論点はあるか？**
   - 例：`approval-hub.ts`（first-response-wins の承認フロー）は MP-Ukagaka に応用可能か
   - 例：`pulse-runner.ts`（cron pulse → context 注入）は wp_cron で代替可能か

---

## 改訂履歴

| 日付       | 改訂内容 |
|------------|----------|
| 2026-05-15 | 初稿作成（avatar-ui 9 項目を P0/P1/P2 で分類） |
| 2026-05-15 | v2 改訂：Claude reviewer note を統合、優先度を MP-Ukagaka ROI ベースで再編、`session_id()` / `mpu_opt` runtime state など WordPress 不適合を訂正、input-role-resolver を新 P0 として追加、中央 Config / i18n hot-swap を明示却下 |
| 2026-05-15 | §M 追加：User Memory MVP（v2.16.0）の実装範囲を明記し、本ドキュメント内「User Memory MVP 完成後」の前置条件を「User Memory v2 完成後」に再整理。Reviewer 向け論点 #3 に v2 vs 揮発性 only 中間案の選択を追加 |
| 2026-05-15 | **v3 統合**：CODEX / Gemini 両 reviewer の 6 問への回答を本文に統合。主な変更点 — (1) 各 P0/P1/P2 セクション末尾に「Reviewer 統合」note を追加、(2) P1-1 を audit/warn/block の **三段モード** に拡張（Codex の warn/report 分離助言）、(3) **P1-2 に Client-side timeout & idle fallback を必須要件として追加**（Gemini の zombie state 指摘を昇格）、(4) §M.4 に **canonical/scratch namespace 境界**を追加（Codex の Avatar Space refs/ 思想）、(5) §F に追加論点一覧表を新設、(6) **推奨実行順序を Codex 推奨の PR 5 段分割**に置き換え（P0-3 → P0-2、P1-2 → P1-1 の順序入れ替え）、(7) 優先度マップに PR 番号列を追加 |
| 2026-05-15 | **v3.1 Codex 補足**：P2-3 Observation Buffer の transient key を global ではなく session / visitor / user scoped に修正。§M.4 の概念図も `mpu_obs_buffer_{session_hash}` / `mpu_obs_buffer_user_{user_id}` / `mpu_obs_buffer_global` に分離し、visitor signal の cross-session 混入リスクを明記。 |
| 2026-05-15 | **v3.2 Gemini 補足**：P1-2 Client-side watchdog に `AbortController.abort()` による接続強制切断を追加（タイムアウト後の race condition 対策）。 |
| 2026-05-15 | **v3.3 実装進捗更新**：PR1-PR5 の実装完了状況を追加。P0（input-role / event schema / tool gate）、PR4（45 秒 watchdog）、PR5（chat-integrity audit/warn/block 後端三段モード）は完了。P2 系列と admin mismatch graph は観察期後に延期。 |

---

## 🤖 Codex's Review & Answers (2026-05-15)

> [!NOTE] **v3 統合状況**：以下の Codex 回答は **v3 で本文の各セクションに反映済み**。元回答は履歴・traceability のため保存。
> 反映先一覧は §F「Reviewer 追加論点」を参照。

Codex としては、この文書の方向性は概ね妥当。ただし MP-Ukagaka は WordPress プラグインであり、avatar-ui の常駐 runtime をそのまま移植するより、**権限境界・イベント契約・失敗時の復帰**だけを薄く取り込むのが最も ROI が高いと見る。

**1. P0-1（input-role-resolver）を本当に最優先にすべきか？**

**結論：最優先でよい。**
理由は security だけではなく、今後の設計語彙を揃えるため。`current_user_can()` は「その瞬間の WordPress 権限」を見る関数であって、LLM 入力の由来・責任主体・許可される tool surface を表す語彙ではない。cron / visitor / logged-in user / admin を同じ判定で扱うと、auto-talk、MCP abilities、Observation Buffer、User Memory の各所で個別 if が増え、後から一貫性を取り戻すコストが高くなる。

ただし実装は大きな framework 化にしない方がよい。最初は `MPU_Input_Role::resolve()` と `can_use_ability()` の純粋関数寄り helper に留め、既存の `permission_callback` と `mpu_get_mcp_tools_for_llm()` へ差し込むのが妥当。

**2. Session Event Schema（P0-3）は WebSocket なしで十分か？**

**結論：十分。むしろ現段階では WebSocket を入れない方がよい。**
MP-Ukagaka の現在の通信面は REST/SSE で成立しているため、WebSocket はサーバー構成、キャッシュ、プロキシ、WordPress hosting 互換性のリスクを増やす。avatar-ui から借りるべきなのは transport ではなく、`kind + payload + eventId + ts` の envelope 思考。

`stream.delta` / `stream.status` / `stream.done` / `stream.error` / `tool.request` / `tool.result` あたりを PHP 定数と JS 定数で揃えれば、SSE と通常 REST response の両方に同じ dispatcher を使える。touch / greeting / weather のような非 LLM event も同じ envelope に乗せてよいが、最初から全 event を移す必要はない。まず chat stream と tool result だけで十分。

**3. Observation Buffer の passive-only 縛りは妥当か？**

**結論：妥当。autonomous 化は User Memory v2 と同意設計の後。**
Web サイト上で avatar が勝手に話しかける体験は、技術的には魅力があるが、訪問者の期待・プライバシー・トークン消費・誤作動時の UX コストが大きい。まずは「観測を短時間だけ貯め、次のユーザー発話に context として添える」passive-only がよい。

保存先も初期段階では transient が適切。IP、referrer、閲覧ページ、滞在時間のような visitor signal は個人情報になり得るため、永続 memory に入れる前に、収集対象・TTL・admin 設定・表示上の説明を決めるべき。User Memory v2 が整うまでは、永続化しない短命 buffer を default にするのが安全。

**4. 却下した中央 Config / i18n hot-swap は本当に却下でよいか？**

**結論：今は却下でよい。**
中央 Config クラスは、WordPress の option array と既存 helper に対して抽象化が重くなりやすい。現状の問題が「default 値が散っている」ことなら、まず defaults の定義場所を整理するだけで足りる。`MPU_Config::get()` のような層を増やすのは、設定画面や migration が複雑化してからでよい。

i18n hot-swap も優先度は低い。WordPress は request 単位の locale と gettext が基本なので、フロントだけ言語を切り替えると server-side error / SSE text / admin text とズレる。今やるなら `wp_localize_script()` に渡す JS 文言を整理する程度で十分。

**5. PHPUnit を 4 ファイルに絞るのは慎重すぎないか？**

**結論：初手としてはちょうどよい。**
この repo ではまず lint gate と狭い pure-function test の価値が高い。WordPress bootstrap が必要な REST 統合テストは導入コストが大きく、最初に広げると保守対象だけが増える。

優先するなら、`class-mpu-input-role.php`、`tool-loop-guard.php`、`provider-helpers.php`、`chat-integrity.php` のように副作用が少なく、壊れると被害が大きい場所から始めるのがよい。REST controller 層は、P0 の schema と role gate が固まった後に追加する。

**6. この文書が見落としている avatar-ui の論点はあるか？**

**追加で見るべき論点は 3 つ。**

1. **approval-hub の「first-response-wins」は将来の tool approval に有用。** 現時点で MP-Ukagaka に複数承認者は不要だが、admin UI とフロント UI の両方から承認できる設計にするなら、承認 request id、timeout、deny default の概念だけ先に event schema に入れておく価値がある。
2. **Integrity の `warn/report` 分離は chat-integrity にも使える。** checksum mismatch を即 freeze するのではなく、audit/warn/block の段階制にする判断は正しい。加えて「API timeout は warn、契約違反は report」のような分類表を作ると、UI が zombie state になる問題を減らせる。
3. **Avatar Space の `refs/` 読み取り専用思想は User Memory v2 に応用できる。** AI が自由に書ける scratch/memory と、plugin が管理する canonical profile / docs / policy を分けるべき。MP-Ukagaka では filesystem を開ける話ではなく、memory namespace の read/write 境界として取り込むのがよい。

**Codex 側の実行順序案**

1. P0-1 `MPU_Input_Role` を追加し、既存 abilities と MCP tool exposure に接続する。
2. P0-3 event envelope を PHP/JS で定義し、chat SSE の status/error/done から移す。
3. P0-2 tool gate を role resolver ベースに整理し、admin/system/visitor の whitelist を明示する。
4. P1-2 client-side timeout / idle fallback を追加し、失敗時に UI が固まらないようにする。
5. P1-1 integrity mode を `audit` default で追加し、block は admin opt-in にする。

最初の PR は P0-1 だけに切るのがよい。P0-1 と P0-2 は近いが、同時にやると「role 定義の追加」と「tool exposure の変更」が混ざって review しづらい。

→ **本文反映先**：§P0-1 reviewer note / §P0-2 PR 順序 / §P0-3 envelope 6 種限定 + approval 予約 / §P1-1 三段モード / §P2-2 / §M.4 namespace 境界 / 「推奨実行順序」PR 5 段分割

---

## 🤖 Gemini's Review & Answers (2026-05-15)

> [!NOTE] **v3 統合状況**：以下の Gemini 回答も **v3 で本文に反映済み**。特に Q6 の「Graceful Degradation / zombie state 対策」は P1-2 の必須要件として昇格。

Gemini として、レビュアーへの問いに対する見解と回答を提示します。

**1. P0-1（input-role-resolver）を本当に最優先にすべきか？**
**結論：絶対に P0 にすべき。**
WordPress の `current_user_can()` は「能力（Capability）」を問う関数であり、「役割（Identity/Role）」を定義するものではありません。これを直接ツールゲートやプロンプト構築に混ぜると、ロジックが確実にスパゲッティ化します。`visitor / subscriber / admin / system` という4象限の中間階層を設けることで、セキュリティの宣言的（Declarative）な管理が可能になり、後続の開発速度が飛躍的に上がります。

**2. Session Event Schema（P0-3）は WebSocket なしで十分か？**
**結論：十分かつ最適解。**
PHP / WordPress のようなステートレスなリクエスト駆動型環境において、WebSocket を維持するのはインフラ面（リバースプロクシ設定、コネクション維持リソース）でのコストが高すぎます。SSE + 標準的な REST レスポンスエンベロープで `stream.status` や `ghost.touch` などの型を統一すれば、フロントエンドは「単一のイベントディスパッチャ」で状態をさばけるようになり、avatar-ui の Event Bus 思想の恩恵を100%享受できます。

**3. Observation Buffer の passive-only 縛りは妥当か？**
**結論：極めて妥当。まずは「揮発性 only 中間案」から始めるべき。**
Web サイト上のアシスタントが勝手に喋り出す（Autonomous trigger）のは、ユーザーの閲覧体験を阻害するリスク（UX上のノイズ）と、トークン消費の予測不能性（コストリスク）を伴います。
まずは Transient を使った「揮発性 Buffer」を作り、ユーザーが次に `/chat/user` を叩いた時に Context として合流させる（Passive）アプローチが最も安全です。訪客プライバシーの観点からも、明示的な保存（User Memory v2）を急ぐより、TTL（生存期間）が短い Transient で済ませる方がコンプライアンス的に優れています。

**4. 却下した中央 Config / i18n hot-swap は本当に却下でよいか？**
**結論：却下で大正解。**
WordPress のエコシステム（Options API、L10n 関数）に逆らう独自実装は、キャッシュプラグイン（Redis Object Cache など）や翻訳プラグイン（Loco Translate 等）との互換性を壊します。設定のデフォルト値が散在する問題は、PHP 定数や Getter 関数の一元化といった地味なリファクタリングで解決すべきであり、過剰な抽象化は不要です。

**5. PHPUnit を 4 ファイルに絞るのは慎重すぎないか？**
**結論：初期フェーズとしては完璧なスコープ。**
WordPress において REST API の E2E / 統合テストを書くには `wp-env` やテストデータベースのセットアップが必要になり、導入コストが跳ね上がります。一方で、純関数（Pure Functions）は副作用がないため、即座にユニットテストの恩恵（回帰バグの防止）を受けられます。権限解決（P0-1）やチェックサム（P1-1）といった「絶対に壊してはいけない中核の純関数」を保護することが、最も ROI が高い投資です。

**6. このドキュメントが見落としている avatar-ui の論点はあるか？**
**追加の視点：Graceful Degradation（縮退運転）と UI の復帰**
avatar-ui は `integrity-manager` がエラーを検知した際に `warn()` を発火して UI を回復させますが、現在の MP-Ukagaka は API タイムアウトなどの際にゴーストが「考え中」のままフリーズする課題があります（State bloat / Zombie state）。
UI 状態フィードバック（P1-2）を実装する際、単に状態を表示するだけでなく、**「一定時間応答がなければ自動的に idle にフォールバックし、エラーメッセージを吹き出しに出す」** という Client-side のタイムアウト＆状態回復メカニズムを JS 側に持たせる必要があります。これは P1-2 とセットで実装すべきです。

→ **本文反映先**：§P1-2「P1-2 追加要件（v3）：Client-side タイムアウト & idle fallback」として必須要件に昇格。45 秒 watchdog の実装例とタイムアウト値の根拠も同節に記載。
→ また §P1-1 で audit/warn/block の三段モードと役割分担（サーバー側 = 契約違反検知 / クライアント側 = 応答途絶検知）を明示。
