# 文件單一語言化プラン（Docs Consolidation）

> 📅 作成：2026-06-11（公司CLAUDE(Opus 4.8) と 維護者の協議。CODEX / Antigravity も英文 canonical を推奨）
> 🎯 想定読者：御三家（実装担当）
> 📌 性質：**設計のみ／未着手**。コード挙動には一切触れない（docs / readme のみ）。
> 🔑 結論：散文ドキュメントを **英語単一 canonical** に収斂。UI 在地化（.po/.mo）と `readme.txt` は対象外。

---

## 0. 核心原則 ── 3 種類を混同しない

維護負担は「全部を多言語で持っている」という思い込みから来る。実際は別物が 3 つある：

| 区分 | ファイル | 判定 |
|---|---|---|
| ① UI 在地化 | `languages/*.po/.mo`（en_US / ja / zh_TW） | **全保持**。外掛 UI 文字列の正規 i18n 管道。終端使用者はここで自国語を得る。本プランの対象外。 |
| ② WP.org 正規 readme | `readme.txt` | **無変更**。WordPress.org が機械解析する英語の正規フォーマット。 |
| ③ 散文ドキュメント三胞胎 | `docs/`(zh-TW) + `docs-en/`(en) + `docs-jp/`(ja) + `README_zh-TW.md` + `README_ja.md` | **これだけ収斂**。手書き ~23,000 行・発版ごとに ×3 改修・既に漂移実証あり（API 簽名が三言語とも過時）。 |

**根拠（audience 分離）**：repo の markdown docs を読むのは貢献者 / CI / GitHub 訪客 / AI agent / 維護チーム ── 終端使用者は repo を clone しない。使用者の言語需要は ① が既に満たしている。よって repo docs は英語が最適、流量言語との矛盾はない。

---

## 1. 決定事項

- **canonical = `docs-en/`（英語）**。CLAUDE.md の文件連結も既に `docs-en/` を指しており、de-facto canonical。
- **`docs/`（zh-TW）と `docs-jp/`（ja）は削除**。git history が保存するので消失しない。過時翻訳を tree に残すと現役扱いされ誤導する。
- **CHANGELOG は 1 本（英語 `docs-en/CHANGELOG.md`）**。多言語 release note が要るなら GitHub Release 作成時にその場で書く（常駐させない）。
- **`README_zh-TW.md` / `README_ja.md` は導覽頁に縮小、または削除**。英語 `README.md` を主とする。
- **`readme.txt` と `languages/*` は無変更**。

---

## 2. 実装ステップ（順序＝投報率順）

1. **CHANGELOG 三併一（最優先・即効）**
   `docs/CHANGELOG.md`・`docs-jp/CHANGELOG.md` を削除し、`docs-en/CHANGELOG.md` を唯一に。
   （理由：最も漂移しやすく、最も翻訳価値が低い。v2.25.5/2.25.6 で実際に 3 本手動同期した toil をここで終わらせる。）

2. **`docs/`(zh-TW) と `docs-jp/`(ja) を削除**
   `docs-en/` を唯一の docs ディレクトリにする。
   ⚠️ **ディレクトリ名の決定（§4 の唯一の open item）**：`docs-en/` のまま残すか、`docs/` にリネームするか。

3. **多言語 README の処理**
   `README_zh-TW.md` / `README_ja.md` を **導覽頁**（簡介＋インストール＋主文件への連結＋対応バージョン状態のみ）に縮小、または削除。
   ⚠️ **導覽頁の鉄律：発版ごとに変わる事実を一切載せない**（版本号・API 簽名・changelog 摘要・詳細版本支援表は禁止）。
   さもなくば発版時に手改が要り、収斂の意味が消える。GitHub は既定で `README.md` のみ表示するため、英語 `README.md` 冒頭に「繁中 / 日本語 ↓」案内を 1 行置くだけで足りる可能性も検討。

4. **`CLAUDE.md` の doc 構造記述を更新**
   - `:106-108`：`docs-en/` `docs-jp/` `docs/`（mirrors）の 3 行を、単一 canonical の記述に置換。
   - `:316-322`：Key Documentation References の `docs-en/...` 連結は canonical 名に合わせる（§4 の決定に従う）。

---

## 3. ガードレール（将来の再漂移防止）

- **翻訳が将来どうしても要るなら「来源」ではなく「産物」にする**：発版時に AI / script で全檔再生成し、ヘッダに `machine-generated, do not hand-edit` を明記。整檔再生なので漂移しない。手書き三胞胎には二度と戻さない。
- **新しい doc を足す時は canonical 1 本だけ**。多言語コピーを作らない。

---

## 4. 着手前に確定する唯一の論点

**canonical ディレクトリ名を `docs-en/` のままにするか `docs/` にリネームするか。**

| 案 | 利点 | 欠点 |
|---|---|---|
| **A. `docs-en/` 維持（推奨）** | CLAUDE.md / README の既存連結が `docs-en/` を指すため**リンク改修ゼロ**。最小 churn。 | 唯一の docs dir なのに `-en` 接尾が冗長。 |
| B. `docs/` にリネーム | 慣例的に綺麗（canonical = `docs/`）。 | `docs/`(zh) 削除後にリネーム要。全 `docs-en/...` 参照（CLAUDE.md・README・コード内コメント等）を grep で改修。 |

推奨は **A（`docs-en/` 維持）**で churn 最小。綺麗さ優先なら B。

---

## 5. 検証

- `grep -rn "docs-jp/\|docs/[A-Z]" --include=*.md --include=*.php` で**死リンクが残っていない**ことを確認（特に CLAUDE.md・README・root の各種参照）。
- `readme.txt`・`languages/*.po/.mo` が **diff に出ていない**こと（対象外を誤って触らない）。
- GitHub 上で `README.md` が壊れず表示され、導覽頁（残す場合）のリンクが生きていること。
- これは docs only。`php -l` / PHPUnit / build は不要だが、念のため `git diff --check`。

---

## 6. スコープ外（念のため）

- `languages/*.po/.mo`（UI 在地化）── 全言語保持。むしろ流量集中言語の `.po` は手厚く維持するのが正しい。
- `readme.txt` ── WP.org 正規、無変更。
- `plan/*` の既存多言語混在 ── 開発内部資料。本プランは公開向け docs/readme のみ対象。
