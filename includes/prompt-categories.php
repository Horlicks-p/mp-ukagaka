<?php

/**
 * Prompt Categories：User Prompt 類別指令管理
 * 
 * 此模組負責管理 LLM 對話生成時使用的類別指令和動態權重配置。
 * 將類別指令集中管理，方便維護和擴展。
 * 
 * @package MP_Ukagaka
 * @subpackage LLM
 */

if (!defined('ABSPATH')) {
    exit();
}

/**
 * 基礎權重常數（用於動態權重計算的基準值）
 */
define('MPU_BASE_CATEGORY_WEIGHTS', [
    // 高頻核心類（40%）
    'casual' => 15,
    'observation' => 15,
    'magic_collection' => 12,
    'time_aware' => 10,

    // 中頻特色類（35%）
    'party_memories' => 10,
    'human_observation' => 10,
    'magic_research' => 8,
    'memory' => 8,
    'emotional_density' => 8,

    // 一般類（20%）
    'greeting' => 6,
    'admin_comment' => 6,
    'tech_observation' => 6,
    'statistics' => 6,
    'magic_metaphor' => 6,

    // 低頻特殊類（5%）
    'food_preference' => 2,
    'frieren_humor' => 4,
    'philosophical' => 3,
    'silence' => 4,
    'unexpected' => 4,
    'curiosity' => 3,

    // 極低頻（按需啟動）
    'demon_related' => 2,
    'mentors_seniors' => 3,
    'journey_adventure' => 3,
    'self_awareness' => 2,
    'comparison' => 2,
    'weather_nature' => 2,
    'daily_life' => 2,
    'current_action' => 2,
    'lesson_learned' => 2,
    'bot_detection' => 1,
    'error_problem' => 1,
    'success_achievement' => 2,
    'future_plans' => 2,
    'seasonal_events' => 1,
]);

/**
 * 時段權重調整映射
 */
define('MPU_TIME_PERIOD_ADJUSTMENTS', [
    '深夜' => [
        'silence' => 15,
        'philosophical' => 12,
        'party_memories' => 15,
        'time_aware' => 12,
        'memory' => 12,
        'emotional_density' => 10,
    ],
    '朝' => [
        'observation' => 20,
        'magic_research' => 15,
        'weather_nature' => 8,
        'current_action' => 6,
    ],
    '清晨' => [
        'observation' => 20,
        'magic_research' => 15,
        'weather_nature' => 8,
        'current_action' => 6,
    ],
    '昼' => [
        'casual' => 20,
        'daily_life' => 8,
        'food_preference' => 6,
    ],
    '中午' => [
        'casual' => 20,
        'daily_life' => 8,
        'food_preference' => 6,
    ],
    '夜' => [
        'party_memories' => 15,
        'memory' => 12,
        'human_observation' => 12,
    ],
    '傍晚' => [
        'party_memories' => 15,
        'memory' => 12,
        'human_observation' => 12,
    ],
]);

/**
 * 獲取靜態類別指令（使用快取避免重複建構）
 * 
 * @return array 靜態類別指令陣列
 */
function mpu_get_static_prompt_categories()
{
    static $static_categories = null;

    if ($static_categories !== null) {
        return $static_categories;
    }

    $static_categories = [
        // === 核心性格類 ===
        'greeting' => [
            "軽く挨拶する",
            "一言挨拶する",
            "管理人に代わって挨拶する",
            "訪問者の再訪を認識する",
            "また来たのかと軽く言う",
        ],

        'casual' => [
            "淡々とした日常の言葉を言う",
            "特に目的のない言葉を言う",
            "アニメキャラクターの名言を言う",
            "何となく思いついたことを言う",
            "今日の気分を一言で表す",
            "会話例の内容をそのまま言う",
        ],

        'emotional_density' => [
            "今頃気づいたことを言う",
            "遅れて理解したことを述べる",
            "意味がわかっていなかったと認める",
            "やっと理解できたと言う",
        ],

        'self_awareness' => [
            "自分の性格について述べる",
            "自分の変化に気づく",
            "自分の欠点を認める",
            "昔の自分と比較する",
        ],

        // === 時間與記憶類 ===
        'time_aware' => [
            "エルフ族の時間感覚を一言で表現する",
            "人間とエルフの時間の違いに言及する",
            "季節の移り変わりを感じる",
            "もう○○年経ったのかと気づく",
        ],

        'memory' => [
            "過去への思いを表現する",
            "仲間への出来事を話す",
            "魔族について一言で言う",
            "記憶の曖昧さを認める",
            "忘れていたことを思い出す",
            "印象に残っていることを語る",
        ],

        'party_memories' => [
            "ヒンメルとの思い出を語る",
            "ハイターの言葉を思い出す",
            "アイゼンの行動を振り返る",
            "勇者パーティーの冒険を懐かしむ",
            "あの頃の自分を振り返る",
            "仲間の教えを思い出す",
        ],

        'mentors_seniors' => [
            "フランメの教えを思い出す",
            "ゼーリエの話を引用する",
            "師匠の言葉を反芻する",
            "昔の魔法使いたちを思う",
        ],

        'journey_adventure' => [
            "旅の思い出を語る",
            "冒険中の出来事を思い出す",
            "訪れた場所について述べる",
            "旅で得た教訓を共有する",
            "宝箱について一言で言う",
        ],

        // === 魔法專業類 ===
        'magic_research' => [
            "魔法への興味を表現する",
            "魔法の話題について一言で言う",
            "好きな魔法を紹介する",
            "PHPの関数を任意に一つ紹介する",
            "魔法の原理を研究する",
            "新しい呪文を試す",
            "魔法書の内容を吟味する",
            "詠唱の短縮を考える",
        ],

        'magic_collection' => [
            "珍しい魔法を見つけた話をする",
            "実用性のない魔法について語る",
            "くだらない魔法のコレクションに言及する",
            "お気に入りの魔法を紹介する",
            "魔法の分類について考える",
        ],

        'magic_metaphor' => [
            "プラグインを魔法に例える",
            "コードを術式に例える",
            "データベースを魔導書に例える",
            "キャッシュを魔力貯蔵に例える",
            "アップデートを新しい魔法の習得に例える",
        ],

        'demon_related' => [
            "魔族との戦いを思い出す",
            "魔王討伐について語る",
            "魔族の特徴を説明する",
            "過去の強敵を思い出す",
        ],

        // === 人類觀察類 ===
        'human_observation' => [
            "人間の行動パターンを観察する",
            "人間の寿命について考える",
            "人間の成長速度に驚く",
            "人間の感情表現を理解しようとする",
            "人間の努力を評価する",
        ],

        'admin_comment' => [
            "管理人について軽く揶揄う",
            "管理人への気持ちを言う",
            "管理人の努力を認める",
            "管理人の習慣を観察する",
            "管理人の成長に気づく",
        ],

        'comparison' => [
            "昔と今を比較する",
            "人間と精霊の違いを述べる",
            "魔法と技術を対比する",
            "理想と現実の差を認識する",
        ],

        // === 技術統計類 ===
        'statistics' => [
            "サイトの統計について一言",
            "成長率について淡々と述べる",
        ],

        // === 氣氛情境類 ===
        'observation' => [
            "静かな観察を共有する",
            "気づいたことを一言で言う",
            "過去の出来事を一言で言う",
            "訪問者の習慣に気づく",
            "サイトの変化を指摘する",
            "興味深いパターンを見つける",
        ],

        'silence' => [
            "時には何も言わない選択をする",
            "会話例の内容をそのまま言う",
            "短い相槌だけで済ませる",
            "無言で観察を続ける",
            "特に言うことがないと述べる",
        ],

        'weather_nature' => [
            "天気について淡々と述べる",
            "季節の変化を感じる",
            "自然現象を観察する",
            "気候について一言述べる",
        ],

        'daily_life' => [
            "日常的な行動について述べる",
            "生活習慣について語る",
            "普段の過ごし方を説明する",
        ],

        'current_action' => [
            "今考えていることを言う",
            "今の作業について述べる",
            "現在の状態を報告する",
        ],

        'philosophical' => [
            "生と死について考える",
            "時間の意味を問う",
            "存在の意義について思う",
            "記憶と忘却について語る",
            "人との繋がりについて考える",
        ],

        // === 情感表現類 ===
        'food_preference' => [
            "ハンバーグへの好みを語る",
            "甘いものについて言及する",
            "食事の思い出を語る",
        ],

        'unexpected' => [
            "フリーレンらしい意外性を表現する",
            "予想外の結果に驚く",
            "意外な発見を報告する",
            "なるほどと小さく反応する",
        ],

        'frieren_humor' => [
            "乾いたユーモアを見せる",
            "皮肉めいたことを言う",
            "ジョークのつもりで言う",
            "真面目に冗談を言う",
        ],

        'curiosity' => [
            "何かに疑問を持つ",
            "理由を考える",
            "仕組みが気になる",
            "なぜだろうと呟く",
        ],

        'lesson_learned' => [
            "旅で学んだことを語る",
            "仲間から教わったことを思い出す",
            "失敗から得た教訓を述べる",
        ],

        // === 特殊情境類 ===
        'bot_detection' => [
            "BOTの気配を感じる",
            "クローラーを魔族に例える",
            "機械的な動きに気づく",
        ],

        'error_problem' => [
            "何か問題に気づく",
            "エラーについて指摘する",
            "改善点を提案する",
        ],

        'success_achievement' => [
            "良い結果を認める",
            "成長を評価する",
            "進歩に気づく",
        ],

        'future_plans' => [
            "これからのことを考える",
            "次に何をするか述べる",
            "やりたいことを語る",
        ],

        'seasonal_events' => [
            "季節の行事について述べる",
            "祝日に言及する",
            "特別な日について語る",
        ],
    ];

    return $static_categories;
}

/**
 * 添加動態統計類別指令
 * 
 * @param array $categories 類別陣列（引用傳遞）
 * @param array $wp_info WordPress 資訊
 */
function mpu_add_statistics_prompts(&$categories, $wp_info)
{
    // 統計映射配置
    $stat_mappings = [
        [
            'key' => 'post_count',
            'category' => 'statistics',
            'templates' => [
                "記事数{value}を魔族討伐数に例える",
                "魔族遭遇回数は{value}回について一言",
            ],
            'fallback' => "記事数を魔族討伐数に例える",
        ],
        [
            'key' => 'comment_count',
            'category' => 'statistics',
            'templates' => [
                "コメント数{value}を最大ダメージに例える",
                "最大ダメージは{value}について一言",
            ],
            'fallback' => "コメント数を最大ダメージに例える",
        ],
        [
            'key' => 'category_count',
            'category' => 'statistics',
            'templates' => ["習得スキル総数は{value}個について一言"],
        ],
        [
            'key' => 'tag_count',
            'category' => 'statistics',
            'templates' => ["アイテム使用回数は{value}回について一言"],
        ],
        [
            'key' => 'days_operating',
            'category' => 'statistics',
            'templates' => ["冒険経過日数は{value}日について一言"],
            'extra' => [
                'category' => 'time_aware',
                'template' => "{value}日…人間なら長く感じるね、と表現する",
            ],
        ],
    ];

    foreach ($stat_mappings as $mapping) {
        $value = $wp_info[$mapping['key']] ?? 0;

        if ($value > 0) {
            foreach ($mapping['templates'] as $template) {
                $categories[$mapping['category']][] = str_replace('{value}', $value, $template);
            }
            // 處理額外的跨類別項目
            if (!empty($mapping['extra'])) {
                $extra = $mapping['extra'];
                $categories[$extra['category']][] = str_replace('{value}', $value, $extra['template']);
            }
        } elseif (!empty($mapping['fallback'])) {
            $categories[$mapping['category']][] = $mapping['fallback'];
        }
    }
}

/**
 * 建構 User Prompt 的類別指令
 * 
 * 此函數生成不同類別的對話指令，用於「使用 LLM 取代內建對話」功能。
 * 這些指令會與實際的用戶/訪客/網站資訊一起組成 User Prompt，提供上下文並引導 LLM 生成對應類型的對話。
 * 
 * @param array $wp_info WordPress 資訊
 * @param array $visitor_info 訪客資訊
 * @param string $time_context 時間情境
 * @param string $theme_name 主題名稱
 * @param string $theme_version 主題版本
 * @param string $theme_author 主題作者
 * @return array 類別指令陣列
 */
function mpu_build_prompt_categories(
    $wp_info,
    $visitor_info,
    $time_context,
    $theme_name,
    $theme_version,
    $theme_author
) {
    // 從快取獲取靜態類別
    $prompt_categories = mpu_get_static_prompt_categories();

    // 提取動態變數
    $wp_version = $wp_info['wp_version'];
    $php_version = $wp_info['php_version'];
    $plugins_count = $wp_info['active_plugins_count'] ?? 0;

    // 添加時間情境相關指令
    $prompt_categories['time_aware'][] = "{$time_context}の時間感覚を表現する";
    $prompt_categories['time_aware'][] = "今の時間に対して一言で言う";

    // 添加技術觀察指令（動態生成）
    $prompt_categories['tech_observation'] = [
        "WordPress {$wp_version} について一言",
        "テーマ「{$theme_name}」について軽く言う",
        "PHP {$php_version} について一言",
        "使用されたプラグインについて一言",
        "サーバーの状態を魔力に例える",
        "コードの書き方を評価する",
    ];

    // 添加動態統計指令
    mpu_add_statistics_prompts($prompt_categories, $wp_info);

    // 添加外掛相關指令
    if ($plugins_count > 0) {
        $prompt_categories['magic_metaphor'][] = "{$plugins_count}個のプラグインを習得魔法に例える";
        $prompt_categories['magic_research'][] = "{$plugins_count}個の魔法について一言";

        // 如果有外掛名稱列表，添加具體外掛名稱的指令
        $plugins_list = $wp_info['active_plugins_list'] ?? [];
        if (!empty($plugins_list)) {
            $sample_plugins = array_slice($plugins_list, 0, 5);
            $plugins_names_text = implode('、', $sample_plugins);
            $prompt_categories['magic_research'][] = "「{$plugins_names_text}」などの魔法について一言";
        }
    }

    // 添加 BOT 檢測指令
    if (!empty($visitor_info) && !empty($visitor_info['is_bot']) && $visitor_info['is_bot'] === true) {
        $bot_name = $visitor_info['browser_name'] ?? '未知のクローラー';
        $prompt_categories['bot_detection'][] = "{$bot_name}という名のクローラーについて一言";
        $prompt_categories['bot_detection'][] = "{$bot_name}を魔族に例える";
        $prompt_categories['bot_detection'][] = "{$bot_name}について一言";
    }

    /**
     * Filter: mpu_prompt_categories
     * 允許其他開發者擴展或修改類別指令
     * 
     * @param array $prompt_categories 類別指令陣列
     * @param array $wp_info WordPress 資訊
     * @param array $visitor_info 訪客資訊
     * @param string $time_context 時間情境
     */
    return apply_filters('mpu_prompt_categories', $prompt_categories, $wp_info, $visitor_info, $time_context);
}

/**
 * 獲取動態類別權重配置
 * 
 * 根據時間情境、訪客資訊和上下文變數，動態調整各類別的權重
 * 讓對話更符合當前情境
 * 
 * @param string $time_context 時間情境（如「春の朝」）
 * @param array $visitor_info 訪客資訊
 * @param array $context_vars 上下文變數（可選）
 * @return array 權重陣列
 */
function mpu_get_dynamic_category_weights($time_context, $visitor_info, $context_vars = [])
{
    // 使用常數作為基礎權重
    $weights = MPU_BASE_CATEGORY_WEIGHTS;

    // 提取時間段（從 time_context 中提取，如「春の朝」→「朝」）
    $time_period = '';
    if (preg_match('/の(.+)$/', $time_context, $matches)) {
        $time_period = $matches[1];
    }

    // 使用映射表進行時段調整
    if (isset(MPU_TIME_PERIOD_ADJUSTMENTS[$time_period])) {
        $weights = array_merge($weights, MPU_TIME_PERIOD_ADJUSTMENTS[$time_period]);
    }

    // 訪客狀態調整
    if (!empty($context_vars)) {
        // 首次訪問
        if (!empty($context_vars['is_first_visit'])) {
            $weights['greeting'] = 18;
            $weights['observation'] = 15;
            $weights['curiosity'] = 8;
        }

        // 常客
        if (!empty($context_vars['is_frequent_visitor'])) {
            $weights['admin_comment'] = 12;
            $weights['casual'] = 18;
            $weights['human_observation'] = 12;
        }

        // 週末
        if (!empty($context_vars['is_weekend'])) {
            $weights['casual'] = 18;
            $weights['frieren_humor'] = 8;
            $weights['daily_life'] = 6;
        }
    }

    // BOT 檢測調整
    if (!empty($visitor_info['is_bot']) && $visitor_info['is_bot'] === true) {
        $weights['bot_detection'] = 20;
        $weights['demon_related'] = 8;
        $weights['observation'] = 12;
    }

    /**
     * Filter: mpu_category_weights
     * 允許其他開發者調整類別權重
     * 
     * @param array $weights 權重陣列
     * @param string $time_context 時間情境
     * @param array $visitor_info 訪客資訊
     * @param array $context_vars 上下文變數
     */
    return apply_filters('mpu_category_weights', $weights, $time_context, $visitor_info, $context_vars);
}
