# PHPCS Baseline Line Ending Memo

## TL;DR

PHPCS baseline 在家裡紅 6 個，根因不是 PHP 版本，也不是 PHPCS / WPCS 工具鏈壞掉，而是 repo 沒有固定 line ending policy。公司產生 baseline 時的工作區換行狀態，和家裡 `core.autocrlf=true` checkout 後的 CRLF 狀態不同，導致 PHPCS 讀到的實體檔案內容不同，baseline 無法穩定抵消既有 finding。

這是 baseline reproducibility 問題。若 `verify` 要成為共同品質閘，建議用獨立 PR 固定 `.gitattributes`，renormalize，並在一致的 LF 環境重產 baseline。

## Observed Symptoms

家裡執行 `lint:phpcs` / `verify` 時，工具鏈可以正常啟動：

- `composer.phar` 可用
- PHPCS 3.13.5 可用
- WordPress / PHPCompatibilityWP standards 可透過 `phpcs.xml.dist` 的 `installed_paths` 正確載入
- baseline 比對流程本身可執行

但會出現 6 個未被 baseline 抵消的 finding：

| File | Finding | Nature |
| --- | --- | --- |
| `class-mpu-log-i18n-builder.php` | `InvalidEOLChar` +1 | pure CRLF |
| `class-mpu-rest-memory.php` | `InvalidEOLChar` +1 | pure CRLF |
| `frontend-functions.php` | `SpacesUsed` +1 | baseline line-ending drift |
| `bot-blocker-integration.php` | `SpacesUsed` +7 | baseline line-ending drift |
| `class-mpu-rest-observation.php` | `SpacesUsed` +6 | baseline line-ending drift |
| `options.php` | `SpacesUsed` +10 | baseline line-ending drift |

目前判斷這些是環境假陽性，不是新的程式碼品質問題。

## Root Cause

問題鏈路如下：

1. 公司端在某個 checkout 狀態下產生 `tools/php/phpcs-baseline.json`。
2. 該 checkout 內部分 PHP 檔案含有 LF / CRLF / mixed EOL 的歷史狀態。
3. repo 沒有 `.gitattributes` 明確規定 PHP / text files 的 EOL。
4. 家裡 Git 設定為 `core.autocrlf=true`。
5. 家裡 checkout 時，文字檔會被轉成 CRLF。
6. PHPCS 檢查的是 working tree 的實體檔案，不是 git blob。
7. 同一個 commit 在兩台機器上，PHPCS 看到的換行狀態不同。
8. baseline 記錄的是公司端當時的 finding；家裡重新跑出的 whitespace / EOL finding 不完全相同，因此 baseline 抵消失敗。

換句話說：baseline 是在 A 換行環境產生的，家裡用 B 換行環境跑，所以對不上。

### Why `SpacesUsed` Can Drift From Line Endings

`SpacesUsed` 乍看是 tab / spaces 問題，和換行無關；這也是最容易被誤解的地方。實測結果顯示，家裡單純在 CRLF vs LF 之間切換時，`SpacesUsed` 數量本身不會因此增加。例如 `bot-blocker-integration.php` 在本機 CRLF 與 LF 下都是同一組 `SpacesUsed` 結果。

真正的問題不是「家裡 CRLF 產生新的縮排錯誤」，而是公司端產生 committed baseline 時，部分檔案處於 mixed EOL 狀態。mixed EOL 會讓 PHPCS 對檔案內容做不同的 line splitting / normalization，進而影響同一個 sniff finding 的 grouping、行號或計數。這使得 baseline 中記錄的 `SpacesUsed` finding 和家裡乾淨 CRLF checkout 重新掃出的 finding 無法一對一抵消。

目前比對到的證據：

- 家裡 CRLF vs LF：`SpacesUsed` 結果不因單純換行轉換而新增。
- committed baseline 比家裡掃描結果多出 `Internal.LineEndings.Mixed` 與 `InvalidEOLChar` 類 finding（依 sniff source 淨差，家裡比公司多 24 個 `SpacesUsed`、少 9 個 `InvalidEOLChar`、少 4 個 `Internal.LineEndings.Mixed`），表示 baseline 產生時存在 mixed / non-normalized EOL。
- committed baseline 約為 3503 entries / 48005 findings；家裡重新掃描約為 3483 entries / 48002 findings。差異集中在 line ending 與 whitespace grouping，不是功能邏輯變更。

因此，`SpacesUsed` 的紅燈應解讀為 baseline 產生環境不可重現，而不是家裡 checkout 新增了縮排品質問題。這也強化了結論：需要 repo-level line ending policy，不能只靠某一台機器的 Git config。

## Not Caused By PHP Version

這次不是 PHP 版本造成。

PHP 版本差異可能影響 parser、runtime behavior 或 compatibility sniff，但這次紅的是：

- `InvalidEOLChar`
- `SpacesUsed`

這類 finding 直接來自檔案換行與 whitespace。工具鏈也已確認能正常載入 PHPCS / WPCS / PHPCompatibilityWP，因此問題核心是 Git line ending normalization，而不是 PHP / Composer / PHPCS 安裝失敗。

## Why Local Git Config Alone Is Not Enough

只把家裡改成：

```powershell
git config core.autocrlf input
```

可以改善未來 commit 習慣，但不能完整修復目前問題，原因是 committed baseline 本身是在另一個換行狀態下產生的。即使家裡改成本機 LF 習慣，baseline 仍可能和公司產出的歷史 baseline 不一致。

真正要讓 `lint:phpcs` 跨機器穩定，需要 repo 本身規定 line ending，並在規定後重產 baseline。

## Recommended Fix

建議獨立 PR 處理，不混進功能 PR：

1. 新增 `.gitattributes`，固定文字檔 LF。
2. 對 repo 做 renormalize。
3. 在 LF policy 下重產 `tools/php/phpcs-baseline.json`。
4. 跑 `verify`。
5. 請公司端也在 clean checkout 驗證。

### Composer Setup Note

若要在 PHP 8.2 / PHP 8.3 環境安裝 `tools/php` 依賴並重產 PHPCS baseline，可能會遇到 lock file 內 `doctrine/instantiator` 2.1.0 要求 PHP `^8.4` 的平台檢查。這是 PHPUnit transitive dependency，和 PHPCS baseline 工具本身無直接關係。

在 PHP < 8.4 的機器上，可用：

```powershell
composer install --working-dir=tools/php --ignore-platform-req=php
```

公司端若不是 PHP 8.4，也可能需要同樣參數才能重建工具鏈。

建議 `.gitattributes` 起點：

```gitattributes
* text=auto eol=lf

*.bat text eol=crlf
*.cmd text eol=crlf
*.ps1 text eol=crlf

*.png binary
*.jpg binary
*.jpeg binary
*.gif binary
*.ico binary
*.mo binary
```

實作指令方向：

```powershell
git add --renormalize .
# regenerate tools/php/phpcs-baseline.json under the normalized checkout
# run verify
```

## Tradeoffs

### Option 1: Add `.gitattributes` and Regenerate Baseline

Pros:

- 一次解決跨機器 PHPCS baseline 不一致。
- `verify` 可以成為穩定品質閘。
- 之後不需要每次分辨 PHPCS 紅燈是真是假。

Cons:

- 會動到較多檔案的 line ending metadata / diff。
- 必須重產 baseline。
- 最好先知會公司端，避免同時修改 baseline 的 PR 互相衝突。

### Option 2: Only Change Local Git Config

Pros:

- 改動小。
- 可改善個人未來 checkout / commit 習慣。

Cons:

- 不能修復已 committed baseline 的環境差異。
- `lint:phpcs` 仍可能在家裡紅。
- 團隊其他機器仍可能重現同樣問題。

### Option 3: Do Nothing Temporarily

Pros:

- 零改動。
- 不阻塞短期功能開發。

Cons:

- 本機 `verify` 會持續不可信。
- baseline 假陽性會消耗 review / debug 時間。
- 長期會削弱 PHPCS 接入 `verify` 的價值。

## Proposed Decision

短期若有功能收尾壓力，可以暫時把這 6 個 finding 記為已知 line-ending 假陽性，以公司端或 CI 的 clean LF checkout 結果為準。

中期建議採用 Option 1，開獨立 PR：

> Normalize line endings and refresh PHPCS baseline

這樣可以把 repo policy、renormalization、baseline refresh 放在同一個清楚的 review scope 內。
