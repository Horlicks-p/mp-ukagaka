# 觸摸反應：補上送禮那次沒做到的一半

> 建立：2026-09-04
> 狀態：**未排程 backlog**（§1 已於同日實作，§2／§3 未開始）
> 起因：`prompts.json` 613-685 的日文句型與其餘全檔明顯不同，追下去發現是架構債的症狀

## 0. 觀察到的現象

`ghost/Frieren/prompts.json` 共 67 個陣列類別。其中**只有 8 個**用了跟其餘 59 個不同的句型 ——
正好就是 `touch_head` / `touch_face` / `touch_chest` / `touch_book` / `touch_legs` 與
`give_food` / `give_gift` / `give_favorite`：

| | 59 個（greeting、casual、magic_collection…） | 8 個（touch_* + give_*） |
|---|---|---|
| 句尾 | 無句號，終止形 `〜する` / `〜語る` | 有句號，`〜して。` |
| 語氣 | 描述**話題**（聊什麼） | 指示**動作**（怎麼做） |
| 例 | `訪問者の流れを観察し、…感想を述べる` | `頭を撫でられた。子供じゃないと少し不満そうに反応して。` |

`て` 形是對模型下的依頼形，其餘是給模型的題目。這個分野本身合理——兩邊消費端不同：
自發對話把候選當話題餵進去，反應類別把候選當演出指示。

**但 touch_* 還多扛了一件 give_* 不用扛的事：陳述事實。**「頭を撫でられた。」是【状況】，
「〜して。」才是演出。兩者被串成同一串等權的祈使句。

## 1. 為什麼 touch_* 非得這樣寫

`includes/rest/class-mpu-rest-touch.php:172-176`：

```php
$user_prompt = $available_prompts[ array_rand( $available_prompts ) ];   // 抽到的那一行 = 整個 user prompt
$user_prompt .= "\n\n【回応ルール】…";
```

抽到的候選**就是全部的 user prompt**。沒有別的地方陳述「發生了什麼」，所以候選只能自己扛。
`:83-91` 的 decoration 路徑同構。

這正是 `89a45e4 fix(gift): let gift reactions see the conversation they happen in` 的
commit message 診斷的形狀：

> The prompt also concatenated fact, motive and stage direction as equally authoritative
> imperatives.

**那次只修了送禮。** touch/zone 與 decoration 仍停在 `89a45e4` 之前：

| | give（已改） | touch／decoration（未改） |
|---|---|---|
| 【状況】區塊 | ✅ 由 items.json 供給 | ❌ 事實混在演出候選裡 |
| 管轄宣告 | ✅ 依來源三分支 | ❌ 無 |
| 「與會話矛盾則丟棄」 | ✅ | ❌ 候選是絕對命令 |
| 對話歷史 | ✅ 20 則視窗，與 `/chat/user` 對齊 | ❌ 完全看不到 |

所以 touch_* 的候選現在只能是「一句一景」的封閉腳本。**句型不同不是風格偏好，是架構債的症狀。**

## 2. §1：補上遺漏的防守句（已完成 2026-09-04）

兩條【回応ルール】是各自手寫的，而且 touch 那條少了一句：

```
personality-items.php:323     …第三者視点の描写は禁止。自分の返答を鉤括弧（「」）で囲まないこと。
class-mpu-rest-touch.php:91   …第三者視点の描写は禁止。                    ← 少
class-mpu-rest-touch.php:176  …第三者視点の描写は禁止。                    ← 少
```

少的是防腳本體例那句。本專案踩過這個坑兩次，記錄在 `personality-items.php:206` 的註解與
`Gift_Message_Attachment_Plan.md`：prompt 裡出現引號括起來的台詞，模型就會照著輸出
`「台詞」動作「台詞」`，而且會跨回合延續到一般對話。

**當時是潛在缺口而非正在發作的 bug**：查過 8 個反應類別的候選，含「」的共 0 條。
但同檔其餘 59 個類別有 **99 條**用了「」（`magic_collection` 幾乎整段、`discovery`、
`daily_life`…），那些走自發對話路徑。哪天有人照著那個風格補一條 touch 候選，
touch 這邊沒有守衛擋著。

已把該句補進兩處。三行改動，無測試影響。

## 3. §2：把【回応ルール】抽成共用函式（未開始）

三處各自手寫同一段規則，已經漂移過一次（就是 §1 修的那次）。抽成一個 helper
（例：`mpu_build_reaction_rules( $ukagaka_name, array $extra = array() )`），
`mpu_build_item_reaction_prompt()` 與 touch 的兩處都改為呼叫它。

- 工數：小
- 風險：`tools/node/test-gift-transport-smoke.js:125` 有一條斷言在比對控制器**不得**自行
  再接一段規則區塊，字面上綁著現行寫法，抽函式時要一併更新
- 效益：規則只有一份，不會再各自漂移

## 4. §3：把 touch 搬到管轄架構（未開始）

根治。範圍等於再做一次 `89a45e4`：

1. `class-mpu-rest-touch.php` 的兩條路徑改用一個 `mpu_build_zone_reaction_prompt()`，
   輸出【状況】＋【演出の候補】＋【回応ルール】三段
2. 【状況】的來源：`touchzones.json` 的 zone 設定加一個 `situation` 欄位
   （`頭を撫でられた。`），或由 zone key 對應一張表
3. 候選改寫成純演出方向 —— `touch_head` 的 5 條把開頭的事實句拿掉，句型即與 `give_*` 一致
4. 加管轄句與「與會話矛盾則丟棄」
5. 接上對話歷史（`MPU_Chat_History_Service::to_llm_messages()` 已是共用的，
   `/touch/give` 就是這樣接的）；注意 `touch_zone` 目前不收 `history` 參數，
   前端與 checksum 都要一起動
6. `ALLOWED_MSG_TYPES` 已含 `touch_decoration`、`touch_zone`
   （`class-mpu-chat-history-service.php:19-23`），這一層不用動

- 工數：中（比送禮那次小，因為沒有 catalog 與 variant，但多了前端傳 history 的部分）
- 風險：`touchzones.json` 是 ghost 資產，改 schema 會影響 Asuna / Sakura_Laurel
  與第三方 ghost；需要對舊格式做 fallback
- 效益：摸頭時她知道剛剛在聊什麼；候選庫的寫法全檔統一

## 5. 為什麼現在不做 §3

touch 目前沒有實際壞掉 —— 沒有回報的錯誤輸出，只有「她摸頭時不記得剛剛的對話」這個
**能力缺口**，而那從來就沒有承諾過。相對地，同一天有兩個真的會被踩到的送禮 bug 待驗
（OK 鈕、幻想警告）。先把已經壞的修完、驗過，再排這個。

真的要排的時候，觸發條件建議是「有人抱怨摸頭反應接不上對話」或「要新增第 6 個 touch zone」——
後者會讓「一句一景」的候選庫再長一輪，屆時改寫成本更高。
