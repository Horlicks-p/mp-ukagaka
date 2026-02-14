<?php

/**
 * 表情映射與情緒分析
 * 
 * 根據對話內容的情緒自動選擇對應的表情圖案
 * 支援從 personality JSON 配置載入關鍵字映射
 * 
 * @package MP_Ukagaka
 * @subpackage Emoji
 */

if (!defined('ABSPATH')) {
    exit();
}

/**
 * 獲取表情映射表
 * 
 * 優先從 personality 的 emoji-keywords.json 載入，
 * 如果不存在則使用內建的預設映射。
 * 
 * @param string|null $personality_id Personality ID, or null for current
 * @return array 表情映射表
 */
function mpu_get_emoji_mappings($personality_id = null)
{
    // 嘗試從 personality JSON 載入
    if (function_exists('mpu_load_personality_emoji_keywords')) {
        $json_mappings = mpu_load_personality_emoji_keywords($personality_id);
        if (!empty($json_mappings)) {
            return $json_mappings;
        }
    }

    // 回退到內建預設映射（保持向後兼容）
    return mpu_get_default_emoji_mappings();
}

/**
 * 獲取內建預設表情映射表
 * 
 * 基於 Frieren 的 emoji-keywords.json 結構
 * 作為沒有自訂 emoji-keywords.json 的角色的 fallback
 * 
 * @return array 預設表情映射表
 */
function mpu_get_default_emoji_mappings()
{
    return [
        'notice' => [
            'keywords' => [
                '發現到', '注意到', '気づいた', 'notice', '気づく', '察覺到', '知らない人間', '感知到',
                'BOT', 'クローラー',
            ],
            'file' => 'notice.png',
            'weight' => 6,
        ],
        'thinking' => [
            'keywords' => [
                '思考中', '考え中', '考えている', '檢討中', '考慮', '構思', 'thinking',
                '沉思', '深思', '思索', '琢磨', '想', '思う', '悩む', 'ponder', 'contemplate',
            ],
            'file' => 'thinking.png',
            'weight' => 9,
        ],
        'upset' => [
            'keywords' => [
                '懊惱', '後悔', '残念', 'くよくよする', '悔しい', '苦澀', '挫敗', '挫折', '煩惱', '屈辱',
                '失望', '沮喪', '灰心', '失落', 'がっかり', 'しょんぼり', 'regret', 'disappointed', 'frustrated',
            ],
            'file' => 'upset.png',
            'weight' => 9,
        ],
        'laugh' => [
            'keywords' => [
                '喜ぶ', '笑う', '嬉しい', '楽しい', '愉快', '愉悅',
                '笑顔', '面白い', '開心', '興高采烈', '有趣', '高興',
                'laugh', 'lol', 'haha', 'lmao',
            ],
            'file' => 'laugh.png',
            'weight' => 8,
        ],
        'shy' => [
            'keywords' => [
                '害羞', '不好意思', '臉紅', '恥ずかしい', '恥ずかしい顔', 'shy',
                '心跳', '心動', 'ドキドキ', '羞澀', '靦腆', '赤面', '照れる', '照れ',
                'blush', 'bashful', 'flustered', 'わくわく', '緊張',
            ],
            'file' => 'shy.png',
            'weight' => 8,
        ],
        'angry' => [
            'keywords' => [
                '生氣', '憤怒', '氣', '怒', '惱火', '煩', '不爽', '不悅',
                '怒る', '怒り', 'ムカつく', 'イライラ', 'うざい', 'やめて',
                'angry', 'mad', 'annoyed', 'get_angry',
            ],
            'file' => 'angry.png',
            'weight' => 10,
        ],
        'talking' => [
            'keywords' => [
                '聊得起勁', '聊得興奮', '無話不談', '聊到忘我', 'talk', 'talking',
                '聊天', '對話', '交談', '暢談', '話す', 'おしゃべり', '会話', 'chat', 'conversation',
            ],
            'file' => 'talking.png',
            'weight' => 9,
        ],
        'amazed' => [
            'keywords' => [
                '驚訝', '驚', '嚇', '意外', '沒想到',
                '驚き', '驚いた', 'びっくり', 'まさか', 'えっ', 'え？', 'おお',
                'surprised', 'startled', 'wow', 'whoa', 'discovery',
            ],
            'file' => 'amazed.png',
            'weight' => 8,
        ],
        'sigh' => [
            'keywords' => [
                '嘆氣', '嘆息', '吐氣', '無奈', '沒想到', 'sigh',
                'ため息をつく', '溜息', '深く嘆く', 'はぁ', 'ふぅ', 'やれやれ',
                '算了', '沒辦法', '仕方ない', 'helpless', 'exasperated',
            ],
            'file' => 'sigh.png',
            'weight' => 8,
        ],
        'stun' => [
            'keywords' => [
                'stunned', '昏倒', '暈倒', '驚倒', '動彈不得', '嚇死了', '靈魂出竅',
                '呆住', '傻住', '石化', '固まる', '呆然', '茫然', 'frozen', 'petrified', 'shocked',
            ],
            'file' => 'stun.png',
            'weight' => 9,
        ],
        'brainwave' => [
            'keywords' => [
                '靈機一動', '靈光乍現', '突然想到', '考え付いた', 'ひらめき', 'インスピレーション', '靈感',
                'brainwave', 'Light-bulb moment', 'come up in my mind',
                '想到了', '有了', '閃過', '思いついた', 'ピンときた', 'eureka', 'idea', 'inspiration',
            ],
            'file' => 'brainwave.png',
            'weight' => 7,
        ],
        'scared' => [
            'keywords' => [
                '害怕', '恐懼', '恐怖', '可怕', '嚇人', 'フェルンの怒り顔',
                '怖い', 'こわい', '恐ろしい', 'やばい', 'ひぃ',
                'scared', 'frightened', 'scary', 'scared_to_death',
            ],
            'file' => 'scared.png',
            'weight' => 8,
        ],
        'love' => [
            'keywords' => [
                '愛', '喜歡', '愛心', '心動', '愛情', '戀愛', '感情',
                '好き', '大好き', '愛してる', 'すき', '可愛い', 'かわいい', '恋',
                'love', 'like', 'heart', 'adore', 'cute', 'affection', 'fond', 'cherish',
            ],
            'file' => 'love.png',
            'weight' => 9,
        ],
        'kiss' => [
            'keywords' => ['kiss', '投げキス', '親', '吻', 'キス', 'チュー', 'ちゅ'],
            'file' => 'kiss.png',
            'weight' => 8,
        ],
        'sleepy' => [
            'keywords' => [
                '困', '睏', '想睡', '累', '疲',
                '眠い', 'ねむい', '眠たい', 'うとうと', 'zzz', 'むにゃ', 'すぅ',
                'sleepy', 'tired', 'drowsy', 'exhausted',
            ],
            'file' => 'sleepy.png',
            'weight' => 9,
        ],
        'embarrass' => [
            'keywords' => [
                '尷尬', '窘', '為難', '冷たい', '無言以對', '冷笑話', '冷たい顔',
                '困る', 'こまる', '微妙', 'うーん', 'えーと', 'あの',
                'awkward', 'embarrassed', 'hmm',
            ],
            'file' => 'embarrass.png',
            'weight' => 8,
        ],
        'smirk' => [
            'keywords' => [
                '驕傲', '自豪', '厲害', '得意な顔', '服だけ溶かす薬',
                '誇り', 'すごい', 'やった', 'できた', '自慢する',
                'proud', 'pride', 'amazing', 'accomplished',
            ],
            'file' => 'smirk.png',
            'weight' => 7,
        ],
        'smug_nod' => [
            'keywords' => [
                '求誇獎', '點頭', '厲害吧', '好喔', 'OK', '當然', 'of course',
                'どや', 'どや顔', 'ほめて', '褒めて', 'ふふん', 'えっへん',
                '了解した', '当然でしょ', 'もちろん',
                'smug', 'smug_nod', 'praise me', 'right?', 'of course',
            ],
            'file' => 'smug_nod.png',
            'weight' => 6,
        ],
        'what' => [
            'keywords' => [
                '懷疑', '可疑', '奇怪', 'わからない', '不確定', '黑人問號',
                '怪しい', 'あやしい', '疑わしい', 'ん？', 'おかしい',
                'what', 'suspicious', 'doubt', 'strange', '困惑', '疑問', '納悶', 'huh', 'confused',
            ],
            'file' => 'what.png',
            'weight' => 7,
        ],
        'whats_up' => [
            'keywords' => [
                '問號', '幹嘛', '怎麼了', '不確定', 'なになに', 'なんだ', 'どうしたの', 'what happen',
                '有什麼事', '有什麼問題嗎', "what's up", "what's going on",
            ],
            'file' => 'whats_up.png',
            'weight' => 7,
        ],
        'speechless' => [
            'keywords' => [
                '聳肩', '傻眼', '尷尬', '想說點什麼但還是算了',
                '無言以對', 'ジレンマ', '気まずい', 'shrug',
                '無語', '不知說什麼', '言葉が出難い', '何も言えない', 'dunno', 'whatever', 'meh',
            ],
            'file' => 'speechless.png',
            'weight' => 6,
        ],
        'want' => [
            'keywords' => [
                '想要', '渴望', '眼睛一亮', '計画通り',
                '欲しい', 'ほしい', 'したい', '憧れる', '望む',
                'want', 'desire', 'wish', 'crave', '期待', '期盼', 'longing', 'yearn',
            ],
            'file' => 'want.png',
            'weight' => 6,
        ],
        'drooling' => [
            'keywords' => [
                '流口水', '垂涎', '好吃', '美味', '想吃想到流口水', '食べたい',
                '美味しい', 'おいしい', 'うまい', 'メルクーアプリン',
                'drooling', 'drool', 'yummy', 'delicious',
            ],
            'file' => 'drooling.png',
            'weight' => 7,
        ],
        'money' => [
            'keywords' => [
                '錢', '金錢', '金', '報酬', '巨大な頭蓋骨',
                'お金', 'ゴールド', '買い物', '無駄遣い',
                'money', 'gold', 'reward', 'pay',
            ],
            'file' => 'money.png',
            'weight' => 5,
        ],
        'vomit' => [
            'keywords' => [
                '喝掛', '爛醉', '吐了', '喝醉', '宿醉', '廢掉', '廢了', '吃壞肚子',
                '酔っ払い', '生臭坊主', 'お酒', '二日酔',
                'drunk', 'intoxicated', 'wasted', 'hangover',
            ],
            'file' => 'vomit.png',
            'weight' => 8,
        ],
        'thank' => [
            'keywords' => [
                'ありがとう', '感謝', '謝謝', '世話になった',
                'thank', 'thanks', 'thank you', 'ご苦勞様',
                '多謝', '感激', '感恩', 'お礼', 'gratitude', 'grateful', 'appreciate', '助かった',
            ],
            'file' => 'thank.png',
            'weight' => 8,
        ],
        'sorry' => [
            'keywords' => [
                'ごめんなさい', '申し訳ありません', '抱歉', '不好意思', '悪かった', '謝罪',
                'sorry', 'apologize', '對不起', '道歉', 'すまんなかった', 'すみません',
            ],
            'file' => 'sorry.png',
            'weight' => 8,
        ],
        'good' => [
            'keywords' => [
                '良い', 'いい', '素晴らしい', '幹得好', '幹得漂亮', 'good job',
                'great', 'awesome', 'good', 'nice', 'well done', '你真棒', '厲害',
            ],
            'file' => 'good.png',
            'weight' => 8,
        ],
        'sad' => [
            'keywords' => [
                '悲しい', '悲傷', '難過', '哭泣', '泣きそう', '眼淚', '涙そう',
                'sad', 'sorrow', 'melancholy', '傷心', '心痛', '寂しい', 'さみしい',
                'crying', 'tearful', 'upset', 'unhappy', 'blue', 'gloomy',
            ],
            'file' => 'sad.png',
            'weight' => 8,
        ],
        'exhausted' => [
            'keywords' => [
                '精疲力盡', '疲憊', '疲れた', '残業', '忙しい', 'もう無理', 'へとへと', 'くたくた', 'バテる',
                'ふらふら', '身心俱疲', '累壞了', '虛脫', 'exhausted', 'worn out', 'drained', 'burned out',
            ],
            'file' => 'exhausted.png',
            'weight' => 8,
        ],
        'dizzy' => [
            'keywords' => [
                '頭昏腦脹', '頭昏眼花', '天旋地轉', '暈眩', '目が回る', 'クラクラ',
                '頭暈', '眩暈', 'めまい', 'ぐるぐる', 'dizzy', 'spinning', 'lightheaded', 'woozy',
            ],
            'file' => 'dizzy.png',
            'weight' => 8,
        ],
    ];
}

/**
 * 分析對話內容的情緒，返回對應的表情文件名
 * 
 * @param string $text 對話內容
 * @param string|null $personality_id Personality ID (可選，用於載入角色專屬關鍵字)
 * @return string|null 表情文件名（如 'happy.png'），如果無法匹配則返回 null
 */
function mpu_analyze_emoji_from_text($text, $personality_id = null)
{
    if (empty($text) || !is_string($text)) {
        return null;
    }

    $emoji_map = mpu_get_emoji_mappings($personality_id);
    $text_lower = mb_strtolower($text, 'UTF-8');
    $text_original = $text;
    $best_match = null;
    $best_weight = 0;

    foreach ($emoji_map as $emoji_key => $emoji_data) {
        $match_count = 0;
        $keywords = $emoji_data['keywords'] ?? [];
        $weight = $emoji_data['weight'] ?? 5;

        foreach ($keywords as $keyword) {
            if (preg_match('/[\x{4e00}-\x{9fff}\x{3040}-\x{309f}\x{30a0}-\x{30ff}]/u', $keyword)) {
                if (mb_strpos($text_original, $keyword, 0, 'UTF-8') !== false) {
                    $match_count++;
                }
            } else {
                if (strpos($text_lower, mb_strtolower($keyword, 'UTF-8')) !== false) {
                    $match_count++;
                }
            }
        }

        if ($match_count > 0) {
            $score = $match_count * $weight;
            if ($score > $best_weight) {
                $best_weight = $score;
                $best_match = $emoji_data['file'] ?? null;
            }
        }
    }

    return $best_match;
}
