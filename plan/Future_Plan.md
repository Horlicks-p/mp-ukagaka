# MP-Ukagaka Future Implementation Plan: OpenClaw SOUL.md Integration

このドキュメントは、AIエージェントに「一貫した人格（Soul）」と「文脈認識（Context Awareness）」を持たせる OpenClaw の `SOUL.md` 設計思想に基づいた、MP-Ukagaka の将来的な実装計画です。

目標：フリーレンを「設定されたセリフを返すBOT」から、「あなたと同じ時間を生き、あなたを記憶するパートナー」へと進化させる。

---

## 実装優先度（推奨順序）

| 順序 | 項目 | 工数 | リスク | 効果 |
|------|------|------|--------|------|
| 1 | §4 モジュール化（personality.md + instructions.md） | 小 | 低 | 高 |
| 2 | §2 ランタイム情報のナラティブ化 | — | — | **実装済み 90%** |
| 3 | §1 ユーザー記憶（admin-only MVP） | 中〜大 | 中 | 高 |
| 4 | §3 Chain of Thought | 中 | 中 | 不確定 |

---

## 1. USER.md（ユーザーの魂）の概念導入

### 現状の課題

現在の `system_prompt.md` には「管理人の扱い」として固定の情報（ニックネーム「和製ホーリックス」、誕生日 10/18）のみが含まれており、会話を通じて増える動的な情報が欠如している。

### 現状のコード分析

| 項目 | 現状 |
|------|------|
| 管理人情報 | `system_prompt.md:59-66` にハードコード |
| 対話履歴 | クライアント側のみ（JS）、最大10件、サーバー非永続化 (`user-chat-handler.php:218-249`) |
| 訪問者情報 | IP・国・都市・BOT判定を注入済み (`llm-context-builder.php:635-703`) |
| 跨セッション記憶 | **未実装** |

### 実装計画

- **動的なユーザープロファイル (Dynamic User Profile)** の作成。
- `user_memories.json` または `user_profile.md` を実装し、会話から得られたユーザーの情報を永続化する。
- `llm-context-builder.php` を更新し、プロンプト生成時にこの記憶ファイルを注入する。

**プロンプト例:**

> 「そういえば、先週言ってた仕事のトラブル、もう片付いたの？」（ユーザーファイル参照）

### 設計上の課題と提案

#### A) ストレージ方式の選定

| 方式 | 対象 | メリット | デメリット |
|------|------|----------|------------|
| WordPress `usermeta` | ログインユーザーのみ | WP標準、プライバシー安全 | 訪問者は対象外 |
| `ghost/<id>/user_memories.json` | 全訪問者共有 | 実装が簡単 | 複数ユーザーの区別不可 |
| カスタムDBテーブル | 全ユーザー | 柔軟性最高 | IP/Cookie依存、プライバシー問題 |
| `wp_options` + ユーザーID | ログインユーザーのみ | シンプル | スケールしない |

**推奨**: 第一版は **admin-only + `usermeta`** で実装。プライバシーリスクなし、対象が明確。

#### B) 記憶の抽出方法

| 方式 | コスト | 精度 | 複雑度 |
|------|--------|------|--------|
| LLM による自動抽出（毎回追加 API 呼出） | Token 2倍 | 高 | 中 |
| ルールベース抽出（キーワードマッチ） | 0 | 低 | 低 |
| 対話終了時にまとめて LLM 抽出 | Token 1.2倍 | 高 | 中 |

**推奨**: 対話終了時（チャットウィンドウを閉じた時）に、軽量プロンプトで LLM に記憶更新を依頼する方式。Claude Code の auto memory 機制に似たアプローチ。

#### C) Token 予算管理

- 現在の `system_prompt.md` は既に **303行**（約 3000-4000 tokens）
- 記憶を追加するとさらに膨張する
- **対策**: 記憶上限を **500文字 / 最大10件の事実** に制限し、古い記憶は要約圧縮する

#### D) 実装の前提条件

§4（モジュール化）を先に完了することで、記憶の注入ポイントが明確になる：

```
instructions.md  → システム制約（変更なし）
personality.md   → キャラ設定（変更なし）
user_memory      → ← ここに動的記憶を注入
```

### 工数見積

| フェーズ | 内容 | 工数 |
|----------|------|------|
| MVP | admin-only + usermeta + 手動記憶保存 | 2-3日 |
| v2 | LLM 自動抽出 + 記憶圧縮 | 3-5日 |
| v3 | 一般ユーザー対応（オプトイン） | 検討中 |

---

## 2. ランタイム情報（Runtime Info）のナラティブ化

### ⚠️ ステータス: 実装済み（90%以上）

このセクションで計画されていた内容は、現在のコードベースで**既に大部分が実装されている**。

### 実装済みの対応表

| 計画の記述 | 実装済みの対応 |
|------------|----------------|
| 時間帯「深夜」→ ユーザーに「早く寝なよ」 | `prompts.json:time_night` + `dynamics.json:time_night` + `sleep_mode.json` 完全な睡眠システム（賴床・IP 記録付き） |
| 季節「冬」→ 防寒・暖かい食べ物 | `weights.json:seasonal_adjustments.冬` + `weather_adjustments` |
| 訪問者「BOT」→ 冷ややかな目 | `dynamics.json:bot_detection` + `prompts.json:bot_detection` + 重み圧制（他カテゴリを 10% に圧縮、BOT 80%） |
| 天気 → 感情トリガー | `dynamics.json`: sunny/cloudy/rainy/snowy/hot/cold/stormy/foggy の **8種類の天気テンプレート** |
| 時間帯 → 感情トリガー | `prompts.json`: time_morning/afternoon/evening/night + `dynamics.json` 動的版 |

### 実装済みコードの所在

- **季節判定**: `mpu_get_season()` — `llm-context-builder.php:26-42`
- **天気 API**: Open-Meteo 統合 — `weather-functions.php`
- **祝日システム**: `calendar.json` + 動的祝日 + 期間祝日 — `llm-context-builder.php:66-261`
- **動的重み調整**: `mpu_get_dynamic_category_weights()` — `prompt-categories.php:176-374`（200行以上の調整ロジック）
- **睡眠モード**: `mpu_is_deep_sleep_time()` — `llm-context-builder.php:363-414`（賴床・IP 記録付き）

### 残りのタスク（微調整）

- [ ] `system_prompt.md` に明示的な感情トリガー規則を追記（例：「深夜は相手に早く寝なよと促す」）
  - → これはプログラム変更ではなく、プロンプトエンジニアリングのみ
- [ ] 温度閾値の感情テンプレートの拡充（現在: ≥30°C と ≤10°C のみ）
  - → `dynamics.json` にテンプレートを追加するだけ

**結論**: このセクションは「完了」とマークし、微調整のみ残す。

---

## 3. 「思考」と「発話」の分離（Inner Voice / Chain of Thought）

### 現状の課題

フリーレンは淡々とした短文で返す制約があるため、複雑な文脈理解が表に出にくい。

### 現状のコード分析

- `system_prompt.md:6` に既にソフト CoT 指示あり:
  > 「回答する前に、以下の設定から関連する設定を検索し、それを踏まえて発言すること」
- `system_prompt.md:74-100` に 25 件の会話例（Few-shot）が既に存在
- 回答の 50 文字制限は `system_prompt.md:5` で強制

### 実装計画

- **思考ブロック (Chain of Thought)** の導入。
- ユーザーには見せない（または HTML コメントとして隠す）ブロックで、まず「キャラとしての思考」を行わせる。
- その思考に基づいて、最終的な短文のセリフを出力させる。

**プロンプト指示:**

> 回答の前に必ず `(thought: ...)` という形式で、相手の意図の分析と、過去の記憶（ヒンメルならどうするか）の検索を行ってください。

### ROI 分析と懸念事項

#### Token コストの大幅増加

| 項目 | 現在 | CoT 導入後 |
|------|------|------------|
| 入力 tokens (system prompt) | ~3000-4000 | 同じ |
| 出力 tokens | ~50-100 (短文回答) | ~300-500 (思考 + 回答) |
| **出力コスト増** | — | **3〜5倍** |

Gemini Flash なら影響は小さいが、Claude/GPT-4o では顕著。

#### プロバイダー間の差異

| プロバイダー | CoT サポート | 実装方式 |
|-------------|-------------|----------|
| Claude | Extended thinking（有料） | API パラメータ `thinking` |
| Gemini | Thinking mode | `thinkingConfig` |
| OpenAI | ネイティブ CoT なし | プロンプトで模擬 |
| Ollama | モデル依存 | プロンプトで模擬 |

`mpu_call_ai_api()` の各プロバイダー分岐で個別対応が必要 → 保守コスト増。

#### 効果の不確実性

- CoT は**複雑な推理タスク**で効果が高いが、**短文生成**での効果は限定的
- 現在の会話品質は `system_prompt.md`（303行の詳細設定）と `dynamics.json` の品質に大きく依存
- CoT より、Few-shot 例の拡充やプロンプト改善の方が費用対効果が高い可能性あり

### 代替案（推奨）

| 方式 | コスト | 効果 | 推奨度 |
|------|--------|------|--------|
| CoT (thought ブロック) | Token 3-5倍 | 不確定 | △ |
| Few-shot 例の拡充 | 0 (プロンプト修正のみ) | 中〜高 | ◎ |
| `system_prompt.md` の反応規則強化 | 0 (プロンプト修正のみ) | 中 | ◎ |
| Gemini thinking mode のみ実験 | 低 | 検証可能 | ○ |

**推奨**: 低優先度。まず Gemini thinking mode のみで A/B テストを実施し、会話品質の向上が確認できた場合のみ他プロバイダーに展開する。

---

## 4. AGENTS.md（行動指針）の分離 ← **最優先で実装推奨**

### 現状の課題

#### 課題 A: system_prompt.md の構造問題

`system_prompt.md` に人格設定とシステム的な制約（会話長度、プロトコル）が混在している。

`system_prompt.md`（303行）の構成:

```
L1-13    : 対話プロトコル（50字制限、口調規則、第一人称）    → instructions
L14-33   : 背景設定（身世、エルフ、旅の動機）               → personality
L34-50   : 感情表現・時間感覚                               → personality
L51-66   : 社交パターン・管理人の扱い                       → personality
L68-72   : 話し方ルール                                     → instructions
L74-100  : 会話例                                           → instructions
L102-265 : 魔法・戦闘・仲間記憶（全体の半分以上）           → personality
L266-288 : 物語の背景・現在の旅                             → personality
L289-303 : 返答の原則・最終注意                             → instructions
```

**問題**: 新キャラクター追加時、プロトコルと人格が分離されていないため、コピペ＆修正が必要。

#### 課題 B: 4つの Handler 間で System Prompt ロード方式が不統一（🐛 バグ含む）

現在、4つの AJAX handler がそれぞれ**異なる方法**で system prompt を取得しており、一貫性がない。

| Handler | ファイル:行 | personality ファイル読込 | 後台 fallback |
|---|---|---|---|
| `mpu_build_optimized_system_prompt` | `llm-context-builder.php:912-928` | `mpu_load_personality_system_prompt()` ✅ | `$mpu_opt['ai_system_prompt']` |
| `mpu_ajax_user_chat` | `user-chat-handler.php:342-352` | `mpu_load_personality_system_prompt()` ✅ | `$mpu_opt['ai_system_prompt']` |
| `mpu_ajax_chat_context` | `context-handler.php:115` | **なし** ❌ 直接後台を使用 | `$mpu_opt['ai_system_prompt']` |
| `mpu_ajax_on_touch_llm` (x2) | `ajax-touch-handlers-llm.php:72,262` | **🐛 関数名 typo!** `mpu_get_personality_system_prompt` を呼び出すが、正しくは `mpu_load_personality_system_prompt`。`function_exists()` が常に `false` → 常に後台 fallback | `$mpu_opt['ai_system_prompt']` |

**影響**: Frieren のように `system_prompt.md` を持つキャラクターでも、**頁面感知** (`context-handler`) と**タッチ反応** (`touch-handlers`) では `system_prompt.md` が完全に無視され、後台の簡易テキストが使われてしまう。

#### 課題 C: 後台の `ai_system_prompt` textarea がグローバル（全キャラ共有）

- 後台 `options_page_ai.php:97-102` の textarea はキャラクター単位ではなく**全キャラ共有**
- `system_prompt.md` を持つキャラクターでは textarea の内容は無視されるが、**ユーザーにはその事実が見えない**
- 新規キャラクター（ghost フォルダなし）は textarea の内容を使うが、textarea がどの場面でどう使われているか不透明

---

### 実装計画

本セクションでは、3つの課題を一括して解決する。

#### 4.1 ファイル構成（モジュール化）

```
ghost/Frieren/
├── system_prompt.md        → 後方互換のため残すが、非推奨（レガシー）
├── personality.md          → 新規: 魂（背景、記憶、感情、仲間、魔法知識）
├── instructions.md         → 新規: 身体（50字制限、口調規則、返答原則、会話例）
└── ... (JSON ファイルは変更なし)
```

#### personality.md に含める内容

```md
# フリーレンの人格

## 背景
（L14-33 の内容）

## 感情表現
（L34-43 の内容）

## 時間感覚
（L44-49 の内容）

## 社交パターン・管理人の扱い
（L51-66 の内容）

## 仲間への記憶
（L133-241 の内容 — 全体の最大セクション）

## 魔法収集・戦闘スタイル・冒険
（L102-132, L242-265 の内容）

## 物語の背景
（L266-288 の内容）
```

#### instructions.md に含める内容

```md
# 対話プロトコル

## 最重要ルール
（L1-13 の内容: 50字制限、第一人称、口調）

## 話し方ルール
（L68-72 の内容）

## 会話例
（L74-100 の内容）

## 返答の原則
（L289-303 の内容）
```

#### 4.2 統一ローダー関数（全 Handler 共通）

**新規関数**: `mpu_load_personality_full_prompt()` — `personality-loader.php` に追加

この関数は 4 段階のフォールバックチェーンを持ち、**全 handler が同一の関数を呼び出す**：

```php
/**
 * Load personality full prompt (unified loader)
 *
 * すべての AJAX handler が呼び出す統一ローダー。
 * 4 段階のフォールバックで system prompt を解決する。
 *
 * Priority:
 * 1. instructions.md + personality.md (modular, 新方式)
 * 2. system_prompt.md (legacy, 後方互換)
 * 3. manifest.json の system_prompt フィールド
 * 4. null を返す → 呼び出し側で後台 ai_system_prompt にフォールバック
 *
 * @param string|null $personality_id
 * @return string|false プロンプト文字列、または false (後台 fallback が必要)
 */
function mpu_load_personality_full_prompt($personality_id = null) {
    if ($personality_id === null) {
        $personality_id = mpu_get_current_personality_id();
    } else {
        $personality_id = mpu_sanitize_personality_id($personality_id);
        if (empty($personality_id)) {
            return false;
        }
    }

    $dir = mpu_get_personalities_dir() . '/' . $personality_id;

    // --- Priority 1: モジュール版 (instructions.md + personality.md) ---
    $parts = [];
    $instructions_path = $dir . '/instructions.md';
    $personality_path  = $dir . '/personality.md';

    if (file_exists($instructions_path) && is_readable($instructions_path)) {
        $content = file_get_contents($instructions_path);
        if ($content !== false) $parts[] = $content;
    }

    if (file_exists($personality_path) && is_readable($personality_path)) {
        $content = file_get_contents($personality_path);
        if ($content !== false) $parts[] = $content;
    }

    if (!empty($parts)) {
        mpu_debug_log("[MP Ukagaka] System prompt loaded from modular files: {$personality_id}");
        return implode("\n\n", $parts);
    }

    // --- Priority 2 & 3: レガシー (system_prompt.md → manifest.json) ---
    $legacy = mpu_load_personality_system_prompt($personality_id);
    if ($legacy !== false) {
        return $legacy;
    }

    // --- Priority 4: false → 呼び出し側で後台 fallback ---
    return false;
}
```

**ヘルパー関数**: `mpu_resolve_system_prompt()` — personality ファイル + 後台 fallback を一発で解決

```php
/**
 * Resolve system prompt for a given personality/ukagaka.
 *
 * personality ファイルの読込と後台 fallback を統合し、
 * 全 Handler が 1 行で system prompt を取得できるようにする。
 *
 * @param string|null $personality_id  Personality ID
 * @param array       $mpu_opt        Plugin options
 * @param string      $ukagaka_name   Ukagaka display name (変数置換用)
 * @return string 解決済み system prompt（常に非空）
 */
function mpu_resolve_system_prompt($personality_id, $mpu_opt, $ukagaka_name = '') {
    // 1. personality ファイルから読込
    $prompt = null;
    if (function_exists('mpu_load_personality_full_prompt')) {
        $result = mpu_load_personality_full_prompt($personality_id);
        if ($result !== false && !empty($result)) {
            $prompt = $result;
        }
    }

    // 2. 後台 ai_system_prompt fallback
    if ($prompt === null) {
        $prompt = $mpu_opt['ai_system_prompt']
            ?? 'あなたは「{{ukagaka_display_name}}」というキャラクターです。';
    }

    // 3. 変数置換
    if (!empty($ukagaka_name)) {
        $prompt = str_replace('{{ukagaka_display_name}}', $ukagaka_name, $prompt);
    }

    return $prompt;
}
```

#### 4.3 全 Handler の呼び出し側統一

4 つの handler を以下のように統一する：

```php
// --- 全 Handler 共通（1 行で完了）---
$system_prompt = mpu_resolve_system_prompt($personality_id, $mpu_opt, $ukagaka_display_name);
```

**個別の変更箇所**:

| ファイル | 現在のコード | 変更後 |
|----------|-------------|--------|
| `llm-context-builder.php:917-928` | `mpu_load_personality_system_prompt()` + 手動 fallback | `mpu_resolve_system_prompt()` |
| `user-chat-handler.php:342-355` | `mpu_load_personality_system_prompt()` + 手動 fallback | `mpu_resolve_system_prompt()` |
| `context-handler.php:115-116` | **直接 `$mpu_opt` のみ** (personality 無視) | `mpu_resolve_system_prompt()` |
| `ajax-touch-handlers-llm.php:71-83` | **🐛 typo** `mpu_get_personality_system_prompt` | `mpu_resolve_system_prompt()` |
| `ajax-touch-handlers-llm.php:260-273` | **🐛 typo** (同上、装飾品タッチ) | `mpu_resolve_system_prompt()` |

**変更の効果**:
- `context-handler.php` が初めて personality ファイルを読むようになる（頁面感知の品質向上）
- `touch-handlers` の typo バグが修正される（タッチ反応の品質向上）
- 全 handler で同一のフォールバックチェーンが保証される
- 将来の変更（モジュール化 → ユーザー記憶注入等）が 1 箇所の修正で全体に反映される

#### 4.4 後台ソースインジケーター

後台 `options_page_ai.php` の `ai_system_prompt` textarea の上に、**現在どのソースが実際に使われているか**を表示するインジケーターを追加する。

**変更ファイル**: `options/options_page_ai.php`（textarea の前に PHP ロジック追加）

**UI イメージ**:

```
┌──────────────────────────────────────────────────────────┐
│ 🌐 語言與角色設定                                        │
│                                                          │
│ ┌─ 📋 System Prompt ソース状態 ──────────────────────┐  │
│ │                                                     │  │
│ │  現在のキャラクター: フリーレン                      │  │
│ │  使用中のソース: 📁 ghost/Frieren/system_prompt.md  │  │
│ │  ⚠️ 下記の textarea の内容は使用されていません。     │  │
│ │  personality ファイルが優先されます。                 │  │
│ │                                                     │  │
│ └─────────────────────────────────────────────────────┘  │
│                                                          │
│ 人格設定 (System Prompt)：                               │
│ ┌────────────────────────────────────────────────────┐   │
│ │ あなたは「{{ukagaka_display_name}}」...             │   │
│ └────────────────────────────────────────────────────┘   │
│ 提示：此設定僅在角色沒有專屬 system_prompt 檔案時使用... │
└──────────────────────────────────────────────────────────┘
```

**ソース判定ロジック** (PHP, `options_page_ai.php` に追加):

```php
<?php
// System Prompt ソース検出
$current_ukagaka = $mpu_opt['cur_ukagaka'] ?? 'default_1';
$prompt_source = 'backend'; // デフォルト: 後台 textarea
$prompt_source_path = '';

if (function_exists('mpu_get_personality_id_from_ukagaka_name')) {
    $pid = mpu_get_personality_id_from_ukagaka_name($current_ukagaka);
    if ($pid) {
        $ghost_dir = mpu_get_personalities_dir() . '/' . $pid;

        if (file_exists($ghost_dir . '/instructions.md') || file_exists($ghost_dir . '/personality.md')) {
            $prompt_source = 'modular';
            $files = [];
            if (file_exists($ghost_dir . '/instructions.md')) $files[] = 'instructions.md';
            if (file_exists($ghost_dir . '/personality.md'))  $files[] = 'personality.md';
            $prompt_source_path = "ghost/{$pid}/" . implode(' + ', $files);
        } elseif (file_exists($ghost_dir . '/system_prompt.md')) {
            $prompt_source = 'system_prompt_md';
            $prompt_source_path = "ghost/{$pid}/system_prompt.md";
        } elseif (function_exists('mpu_load_personality_manifest')) {
            $manifest = mpu_load_personality_manifest($pid);
            if (!empty($manifest['system_prompt'])) {
                $prompt_source = 'manifest';
                $prompt_source_path = "ghost/{$pid}/manifest.json → system_prompt";
            }
        }
    }
}
?>
```

**表示する 4 種類のステータス**:

| ソース | 表示 | 色 |
|--------|------|-----|
| `modular` | `📁 ghost/<id>/instructions.md + personality.md を使用中` | 🟢 緑 |
| `system_prompt_md` | `📁 ghost/<id>/system_prompt.md を使用中` | 🟢 緑 |
| `manifest` | `📄 ghost/<id>/manifest.json の system_prompt を使用中` | 🟡 黄 |
| `backend` | `✏️ 下記の textarea の内容を使用中` | 🔵 青 |

personality ファイルが優先されている場合（`modular` / `system_prompt_md` / `manifest`）は、textarea に以下の注意書きを追加:

> ⚠️ 現在のキャラクター「フリーレン」は専用の personality ファイルを使用しているため、この textarea の内容は使用されません。ここの内容は、専用ファイルを持たないキャラクターの fallback として機能します。

#### 4.5 完全な優先度チェーン（最終形）

```
1. ghost/<id>/instructions.md + personality.md   (モジュール化、最優先)
2. ghost/<id>/system_prompt.md                   (レガシー、後方互換)
3. ghost/<id>/manifest.json → system_prompt      (最小構成)
4. 後台 ai_system_prompt textarea                (グローバル fallback)
5. ハードコードデフォルト                         (何も設定されていない場合)
```

この優先度チェーンは `mpu_resolve_system_prompt()` 1 箇所に集約され、全 handler で共有される。

#### 4.6 後方互換性

| ケース | 動作 |
|--------|------|
| `instructions.md` + `personality.md` あり | モジュール版を使用 |
| `system_prompt.md` のみ（レガシー） | 従来通り動作（変更なし） |
| `manifest.json` の `system_prompt` のみ | 従来通り動作 |
| ghost ファイル一切なし | 後台 textarea を使用 |
| ghost フォルダ自体なし（新規キャラ） | 後台 textarea を使用 |

#### 4.7 メリット

1. **バグ修正**: touch-handler の関数名 typo が解消、context-handler が初めて personality ファイルを読む
2. **全 handler の挙動統一**: 5 箇所の分散ロジックが `mpu_resolve_system_prompt()` 1 箇所に集約
3. **キャラクター追加が容易**: `instructions.md` を共有テンプレ化し、`personality.md` のみ新規作成
4. **後台の透明性**: ユーザーが「今どのソースが使われているか」を一目で把握できる
5. **A/B テスト**: ルール調整（instructions）と人格調整（personality）を独立して実験可能
6. **§1（ユーザー記憶）の基盤**: `mpu_resolve_system_prompt()` に記憶注入ポイントを追加するだけで全 handler に反映

### 工数見積

| タスク | 工数 |
|--------|------|
| `mpu_load_personality_full_prompt()` + `mpu_resolve_system_prompt()` 実装 | 0.5日 |
| 4 handler の呼び出し統一 (5 箇所) + touch-handler typo 修正 | 0.5日 |
| `options_page_ai.php` ソースインジケーター | 0.5日 |
| `system_prompt.md` → `personality.md` + `instructions.md` 分割 | 0.5日 |
| テスト（5 パターンの fallback 確認） | 0.5日 |
| **合計** | **約 2.5〜3日** |

---

## ロードマップ要約

### Phase 1: モジュール化 + Handler 統一（§4）— 約 2.5〜3日

1. `personality-loader.php` に `mpu_load_personality_full_prompt()` と `mpu_resolve_system_prompt()` を実装
2. 4 handler (5 箇所) の呼び出しを `mpu_resolve_system_prompt()` に統一
3. `ajax-touch-handlers-llm.php` の関数名 typo を修正（`mpu_get_` → `mpu_load_`、ただし統一後は不要）
4. `context-handler.php` に personality ファイル読込を追加（統一後は自動的に解決）
5. `options_page_ai.php` にソースインジケーター追加
6. `system_prompt.md` を `personality.md` + `instructions.md` に分割
7. 5 パターンの fallback テスト

### Phase 2: ランタイム情報の微調整（§2）— 約 0.5日

1. `system_prompt.md`（または新 `instructions.md`）に感情トリガー規則を明記
2. `dynamics.json` に温度帯テンプレートを追加（任意）
3. **ステータス**: ほぼ完了、プロンプト微調整のみ

### Phase 3: ユーザー記憶 MVP（§1）— 約 2〜3日

1. admin-only + `usermeta` で記憶ストレージ実装
2. `mpu_resolve_system_prompt()` に記憶注入ポイントを追加（全 handler に自動反映）
3. 対話終了時の LLM 記憶抽出（オプション）

### Phase 4: Chain of Thought 実験（§3）— 要検証

1. Gemini thinking mode のみで A/B テスト
2. 効果が確認できれば他プロバイダーに展開
3. **前提**: Phase 1〜3 が安定した後
