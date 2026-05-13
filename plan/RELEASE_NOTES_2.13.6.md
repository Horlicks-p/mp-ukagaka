# v2.13.6 — Developer Documentation Catch-Up & Cross-Language Sync

Release date: 2026-04-24

本版本為**文件補完版**，無程式邏輯變更。補齊 v2.13.5 之前遺漏的開發者文件更新，並完成繁中 / 英文 / 日文三語同步。

This release is **docs-only** with no code logic changes. It completes the developer documentation updates that were pending before v2.13.5 and brings Traditional Chinese, English, and Japanese docs back into sync.

本バージョンは**ドキュメント補完版**であり、コードロジックの変更はありません。v2.13.5 以前に反映しきれていなかった開発者向け文書を補完し、繁体字中国語・英語・日本語の 3 言語同期を完了しました。

---

## 📖 繁體中文

### 開發文件補完與多語同步

- **補齊昨天遺漏的開發文件更新**：完成 `API_REFERENCE.md`、`DEVELOPER_GUIDE.md`、`CANVAS_CUSTOMIZATION.md`、`DEBUG_SLIMSTAT.md`、`ABILITIES_API.md`、`GHOST_CREATE_GUIDE.md` 的全面校對與修正，讓文件內容與目前程式碼結構一致。
- **REST / Abilities / Personality 架構對齊**：
  - 將開發文件中過時的 AJAX、舊 hooks、舊全域變數與舊模組描述，統一更新為現行 REST controller 架構。
  - 明確區分 Abilities API 的對外概念與內部仍保留的 MCP 命名過渡層。
  - 人格製作指南改以 `instructions.md + personality.md` 為主，保留 `system_prompt.md` / `manifest.json.system_prompt` 的 legacy fallback 說明。
- **補齊目前實際支援的 personality / frontend 文件內容**：`touchzones.json`、`sleep_mode.json`、`calendar.json`、`diary.json`、`emoji-keywords.json`、`scripts` 等現行結構；canvas 裝飾系統、Slimstat/訪客資訊除錯流程、初始化資料與前端腳本載入說明。
- **三語文件同步**：繁中、英文、日文三套開發文件與 changelog 已同步更新，內容保持一致。
- **版本戳對齊**：`API_REFERENCE.md`、`CLAUDE.md`、`readme.txt` 等殘留的舊版本號已一併更新為 2.13.6。

## 📖 English

### Developer Documentation Catch-Up & Cross-Language Sync

- **Completed the missing developer doc updates**: Fully reviewed and updated `API_REFERENCE.md`, `DEVELOPER_GUIDE.md`, `CANVAS_CUSTOMIZATION.md`, `DEBUG_SLIMSTAT.md`, `ABILITIES_API.md`, and `GHOST_CREATE_GUIDE.md` so they now match the current codebase structure.
- **REST / Abilities / Personality architecture alignment**:
  - Replaced outdated AJAX, old hooks, legacy globals, and obsolete module descriptions with the current REST controller architecture.
  - Clarified the split between the public Abilities API concept and the internal MCP-era naming still present in the implementation.
  - Ghost creation guide now uses `instructions.md + personality.md` as the primary prompt structure, with `system_prompt.md` / `manifest.json.system_prompt` retained as documented legacy fallback.
- **Current personality / frontend surface documented**: `touchzones.json`, `sleep_mode.json`, `calendar.json`, `diary.json`, `emoji-keywords.json`, `scripts`; canvas decoration system, Slimstat/visitor-info debug flow, init payload notes, and frontend script loading.
- **Three-language sync complete**: Traditional Chinese, English, and Japanese developer docs and changelogs are now aligned.
- **Version-stamp cleanup**: Stray version references in `API_REFERENCE.md`, `CLAUDE.md`, and `readme.txt` are now all 2.13.6.

## 📖 日本語

### 開発者向け文書の補完と多言語同期

- **昨日反映しきれていなかった開発者向け文書を補完**：`API_REFERENCE.md`、`DEVELOPER_GUIDE.md`、`CANVAS_CUSTOMIZATION.md`、`DEBUG_SLIMSTAT.md`、`ABILITIES_API.md`、`GHOST_CREATE_GUIDE.md` を全面的に見直し、現在のコード構造と一致するよう更新しました。
- **REST / Abilities / Personality アーキテクチャに整合**：
  - 古い AJAX、旧 hooks、旧グローバル変数、廃止済みモジュール説明を現在の REST controller ベース構成に更新しました。
  - 対外的な Abilities API の概念と、実装内部に一部残っている MCP 由来の命名との違いを明確化しました。
  - ゴースト作成ガイドを `instructions.md + personality.md` を主構成とする内容に改め、`system_prompt.md` / `manifest.json.system_prompt` は legacy fallback として整理しました。
- **現在サポートされている personality / frontend 構成を追記**：`touchzones.json`、`sleep_mode.json`、`calendar.json`、`diary.json`、`emoji-keywords.json`、`scripts` 等；canvas 装飾システム、Slimstat / visitor-info デバッグ手順、初期化データ、フロントエンド script 読み込み説明。
- **3 言語同期完了**：繁体字中国語・英語・日本語の開発者向け文書と changelog が同期されました。
- **バージョン表記の整理**：`API_REFERENCE.md`、`CLAUDE.md`、`readme.txt` に残っていた古いバージョン番号をすべて 2.13.6 に揃えました。

---

**Plugin version:** `2.13.6-20260424` (`MPU_VERSION`)
