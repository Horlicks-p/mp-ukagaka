<?php
/**
 * M1a 非串流測試套件 — mpu_normalize_ai_response() 契約驗收（§13.6）
 *
 * Self-contained CLI 測試，不需 WordPress bootstrap。以忠實 stub 取代
 * WP / personality 依賴，逐一驗證 §13.2 契約。
 *
 * 執行：  D:\XAMPP\php\php.exe tests/test-response-normalizer.php
 * 退出碼：全綠 = 0，任一失敗 = 1
 */

// --- 最小環境 stub ---------------------------------------------------------
define('ABSPATH', __DIR__ . '/');
if (!defined('WP_DEBUG')) {
    define('WP_DEBUG', false);
}

$GLOBALS['__mpu_warns'] = [];

/** 收集 warning，供「中段/多 think」案例斷言。 */
function mpu_debug_log($msg)
{
    $GLOBALS['__mpu_warns'][] = $msg;
}

/** 固定 supported 清單（模擬 Frieren emojis/）。 */
function mpu_load_personality_emoji_config($personality_id = null)
{
    return ['supported' => ['laugh', 'angry', 'happy', 'sad', 'surprised']];
}

/** enable_inner_monologue 預設 true（不設 key）。可由測試覆寫。 */
function mpu_get_option()
{
    return $GLOBALS['__mpu_opt'] ?? [];
}

/**
 * 忠實複製 includes/llm/ai-functions.php:136 的 mpu_filter_thinking_content，
 * 確保 display 剝離行為與 production 一致（normalizer 委派此函式）。
 */
function mpu_filter_thinking_content($response)
{
    if (empty($response) || !is_string($response)) {
        return $response;
    }
    $complete_tags = ['think', 'thinking', 'reflection', 'chain_of_thought', 'reasoning', 'inner_monologue', 'redacted_reasoning'];
    foreach ($complete_tags as $tag) {
        $response = preg_replace('/<' . $tag . '>.*?<\/' . $tag . '>/is', '', $response);
    }
    $incomplete_tags = ['think', 'thinking', 'reflection', 'chain_of_thought', 'reasoning', 'inner_monologue'];
    foreach ($incomplete_tags as $tag) {
        $response = preg_replace('/<' . $tag . '>.*$/is', '', $response);
    }
    $response = preg_replace('/\<\|[a-z_]+\|\>/i', '', $response);
    $response = preg_replace('/\n\s*\n\s*\n/s', "\n\n", $response);
    return trim($response);
}

require __DIR__ . '/../includes/llm/response-normalizer.php';

// --- 迷你斷言框架 ----------------------------------------------------------
$tests_run = 0;
$tests_failed = 0;

function check($cond, $label)
{
    global $tests_run, $tests_failed;
    $tests_run++;
    if ($cond) {
        echo "  \xE2\x9C\x93 {$label}\n";
    } else {
        $tests_failed++;
        echo "  \xE2\x9C\x97 FAIL: {$label}\n";
    }
}

/** 契約鎖死：display === history === checksum，且 tts 預設等於 display。 */
function assert_locked($r, $label)
{
    check(
        $r['display_text'] === $r['history_text']
        && $r['history_text'] === $r['checksum_text']
        && $r['tts_text'] === $r['display_text'],
        "{$label}：display===history===checksum===tts"
    );
}

/** primary 同步：tag 與 file 同時 null 或同時有值。 */
function assert_primary_sync($r, $label)
{
    $sync = ($r['primary_emotion_tag'] === null && $r['primary_emotion_file'] === null)
        || ($r['primary_emotion_tag'] !== null && $r['primary_emotion_file'] !== null);
    check($sync, "{$label}：primary_emotion_tag/file 同步");
}

echo "=== M1a Response Normalizer 契約測試 ===\n\n";

// 1. 純文字、無標籤
echo "[1] 純文字、無標籤\n";
$r = mpu_normalize_ai_response('こんにちは、いい天気だね。');
check($r['display_text'] === 'こんにちは、いい天気だね。', '原樣保留');
check($r['emotion_tags'] === [] && $r['emotion_files'] === [], '無 emotion');
check($r['think'] === '', 'think 空');
check($r['primary_emotion_tag'] === null && $r['primary_emotion_file'] === null, 'primary null');
assert_primary_sync($r, '案例1');
assert_locked($r, '案例1');

// 2. 合法 tag 單個
echo "[2] 合法 tag（單個）\n";
$r = mpu_normalize_ai_response('うれしいな [laugh]');
check(strpos($r['display_text'], '[laugh]') === false, '[laugh] 已剝離');
check($r['display_text'] === 'うれしいな', 'display 乾淨（尾端 trim）');
check($r['emotion_tags'] === ['laugh'], "emotion_tags=['laugh']");
check($r['emotion_files'] === ['laugh.png'], "emotion_files=['laugh.png']");
check($r['primary_emotion_tag'] === 'laugh' && $r['primary_emotion_file'] === 'laugh.png', 'primary 正確');
assert_primary_sync($r, '案例2');
assert_locked($r, '案例2');

// 3. 合法 tag 多個 → primary 只取第一
echo "[3] 合法 tag（多個）→ primary 只取第一\n";
$r = mpu_normalize_ai_response('[laugh] あはは [angry] むっ');
check($r['emotion_tags'] === ['laugh', 'angry'], "tags=['laugh','angry'] 依序");
check($r['emotion_files'] === ['laugh.png', 'angry.png'], 'files 對應');
check($r['primary_emotion_tag'] === 'laugh', 'primary_tag=laugh（第一個）');
check($r['primary_emotion_file'] === 'laugh.png', 'primary_file=laugh.png');
check(strpos($r['display_text'], '[laugh]') === false && strpos($r['display_text'], '[angry]') === false, '兩個 tag 都剝離');
assert_locked($r, '案例3');

// 4. 句中內嵌
echo "[4] 合法 tag（句中內嵌）\n";
$r = mpu_normalize_ai_response('そう [laugh] だね');
check(strpos($r['display_text'], '[laugh]') === false, '[laugh] 剝離');
check(strpos($r['display_text'], 'そう') !== false && strpos($r['display_text'], 'だね') !== false, '前後文保留');
check($r['primary_emotion_file'] === 'laugh.png', 'primary 正確');
assert_locked($r, '案例4');

// 5. 未知 tag → 保留、不切表情
echo "[5] 未知 tag → 預設保留\n";
foreach (['[evil]', '[WordPress]', '[TODO]', '[A]'] as $unk) {
    $r = mpu_normalize_ai_response("これは {$unk} だよ");
    check(strpos($r['display_text'], $unk) !== false, "{$unk} 保留於 display");
    check($r['emotion_tags'] === [], "{$unk} 不產生 emotion");
    assert_primary_sync($r, "案例5 {$unk}");
}

// 6. 中文 [表情: xxx] 向下相容
echo "[6] 中文 [表情: xxx] 向下相容\n";
$r = mpu_normalize_ai_response('わかった [表情: happy]');
check(strpos($r['display_text'], '表情') === false, '[表情: happy] 剝離');
check($r['emotion_tags'] === ['happy'] && $r['primary_emotion_file'] === 'happy.png', '解析為 happy');
assert_locked($r, '案例6');
// (emotion: xxx) 與 [emoji: xxx] 變體
$r2 = mpu_normalize_ai_response('test (emotion: sad)');
check($r2['primary_emotion_tag'] === 'sad', '(emotion: sad) 變體相容');
// 顯式格式但不存在的表情 → 視為文字保留
$r3 = mpu_normalize_ai_response('foo [表情: nonexist]');
check($r3['emotion_tags'] === [] && strpos($r3['display_text'], 'nonexist') !== false, '不存在表情 → 保留為文字');

// 7. Markdown link 不被破壞
echo "[7] Markdown link 不被破壞\n";
$r = mpu_normalize_ai_response('詳細は [docs](https://example.com) を参照');
check(strpos($r['display_text'], '[docs](https://example.com)') !== false, 'markdown link 完整保留');
check($r['emotion_tags'] === [], 'link 不產生 emotion');
// 即使 link text 剛好是 supported tag 名，negative lookahead 仍保護
$r2 = mpu_normalize_ai_response('[laugh](https://x.com)');
check(strpos($r2['display_text'], '[laugh](https://x.com)') !== false, '[laugh](url) 不被當 emotion 剝離');
check($r2['emotion_tags'] === [], '[laugh](url) 無 emotion');

// 8. 未閉合 <think>（截斷）
echo "[8] 未閉合 <think>（截斷）\n";
$r = mpu_normalize_ai_response('<think>途中で切れた思考');
check($r['display_text'] === '', 'display 剝離乾淨');
check($r['think'] === '途中で切れた思考', 'think 捕捉截斷內容（Defense A）');
assert_locked($r, '案例8');

// 9. 多個 <think> → 只取第一 + warning
echo "[9] 多個 <think> → 只取第一\n";
$GLOBALS['__mpu_warns'] = [];
$r = mpu_normalize_ai_response('<think>最初の思考</think>表面の返事<think>二番目</think>');
check($r['think'] === '最初の思考', 'think 只取第一個');
check($r['display_text'] === '表面の返事', '兩段 think 都從 display 剝離');
$has_warn = false;
foreach ($GLOBALS['__mpu_warns'] as $w) {
    if (strpos($w, 'multiple') !== false) { $has_warn = true; }
}
check($has_warn, 'multiple think warning 已記錄');
assert_locked($r, '案例9');

// 10. 中段 <think> → 剝離 + warning，think 空
echo "[10] 中段 <think> → think 空 + warning\n";
$GLOBALS['__mpu_warns'] = [];
$r = mpu_normalize_ai_response('こんにちは<think>これは内心</think>');
check($r['think'] === '', 'think 欄位為空（中段不渲染）');
check($r['display_text'] === 'こんにちは', 'display 剝離 think');
$has_mid = false;
foreach ($GLOBALS['__mpu_warns'] as $w) {
    if (strpos($w, 'mid-response') !== false) { $has_mid = true; }
}
check($has_mid, 'mid-response think warning 已記錄');
assert_locked($r, '案例10');

// 11. [laugh] 與 <think> 混合
echo "[11] [laugh] + <think> 混合\n";
$r = mpu_normalize_ai_response('<think>笑っておこう</think>あはは [laugh]');
check($r['think'] === '笑っておこう', 'think 捕捉');
check($r['primary_emotion_file'] === 'laugh.png', 'emotion 捕捉');
check(strpos($r['display_text'], '[laugh]') === false && strpos($r['display_text'], 'think') === false, 'display 同時剝離 think 與 tag');
check(strpos($r['display_text'], 'あはは') !== false, '正文保留');
assert_locked($r, '案例11');

// 12. enable_inner_monologue=false → think 固定空（規則 8）
echo "[12] enable_inner_monologue=false → think 空\n";
$GLOBALS['__mpu_opt'] = ['enable_inner_monologue' => false];
$r = mpu_normalize_ai_response('<think>内心</think>表面');
check($r['think'] === '', 'gate 關閉時 think 空');
check($r['display_text'] === '表面', 'display 仍剝離 think');
$GLOBALS['__mpu_opt'] = []; // 還原

// 13. strip_unknown_tags=true → 未知 inline tag 也剝離
echo "[13] strip_unknown_tags=true\n";
$r = mpu_normalize_ai_response('これは [evil] だ', null, ['strip_unknown_tags' => true]);
check(strpos($r['display_text'], '[evil]') === false, '[evil] 被剝離');
check($r['emotion_tags'] === [], '仍不產生 emotion');

// --- 結算 ------------------------------------------------------------------
echo "\n=== 結果：" . ($tests_run - $tests_failed) . "/{$tests_run} 通過 ===\n";
if ($tests_failed > 0) {
    echo "✗ {$tests_failed} 個失敗\n";
    exit(1);
}
echo "✓ 全綠\n";
exit(0);
