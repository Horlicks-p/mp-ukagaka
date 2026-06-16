<?php

/**
 * LLM 功能：上下文建構
 * 
 * 負責建構 LLM 對話所需的上下文資訊，包括：
 * - 時間情境（季節、時間段、睡眠模式）
 * - 訪客資訊（BOT 檢測、地理位置）
 * - WordPress 資訊壓縮
 * - System Prompt 建構
 * 
 * @package MP_Ukagaka
 * @subpackage LLM
 */

if (!defined('ABSPATH')) {
    exit();
}

/**
 * 獲取季節名稱
 * 
 * @param int $month 月份（1-12）
 * @return string 季節名稱（春/夏/秋/冬）
 */
function mpu_get_season($month)
{
    // 台灣季節劃分：
    // 春：3-5月
    // 夏：6-8月
    // 秋：9-11月
    // 冬：12-2月
    if ($month >= 3 && $month <= 5) {
        return '春';
    } elseif ($month >= 6 && $month <= 8) {
        return '夏';
    } elseif ($month >= 9 && $month <= 11) {
        return '秋';
    } else {
        return '冬';
    }
}

/**
 * 獲取星期名稱（日文）
 * 
 * @param int $day_of_week 星期（0=日, 1=月, ..., 6=土）
 * @return string 星期名稱
 */
function mpu_get_weekday_name($day_of_week)
{
    $weekdays = ['日', '月', '火', '水', '木', '金', '土'];
    return $weekdays[$day_of_week] . '曜日';
}

/**
 * 檢測特殊節日
 * 
 * 優先從 personality 的 calendar.json 載入，然後回退到預設硬編碼列表。
 * 
 * @param int $month 月份（1-12）
 * @param int $day 日期（1-31）
 * @param string|null $personality_id 角色 ID（可選）
 * @return string|null 節日名稱，無節日則返回 null
 */
function mpu_get_holiday($month, $day, $personality_id = null)
{
    $key = "{$month}-{$day}";

    // 優先從 JSON 載入
    if (function_exists('mpu_load_personality_calendar')) {
        $calendar = mpu_load_personality_calendar($personality_id);

        // 1. 檢查自訂節日
        if (!empty($calendar['holidays'][$key]['name'])) {
            return $calendar['holidays'][$key]['name'];
        }

        // 2. 檢查紀念日
        if (!empty($calendar['anniversaries'][$key]['name'])) {
            return $calendar['anniversaries'][$key]['name'];
        }

        // 3. 檢查動態節日（每月第 N 個星期 X）
        $dynamic_holiday = mpu_check_dynamic_holiday($month, $day, $calendar);
        if ($dynamic_holiday) {
            return $dynamic_holiday;
        }
    }

    // 回退到預設節日列表（硬編碼）
    $holidays = [
        // === 日本國定假日（祝日）===
        '1-1' => '元日',               // 新年
        '1-2' => '正月二日',
        '1-3' => '正月三日',
        '2-11' => '建国記念の日',      // 建國紀念日
        '2-23' => '天皇誕生日',        // 天皇誕辰
        '3-21' => '春分の日',          // 春分（約略日期）
        '4-29' => '昭和の日',          // 昭和之日
        '5-3' => '憲法記念日',         // 憲法紀念日
        '5-4' => 'みどりの日',         // 綠之日
        '5-5' => 'こどもの日',         // 兒童節
        '8-11' => '山の日',            // 山之日
        '9-23' => '秋分の日',          // 秋分（約略日期）
        '11-3' => '文化の日',          // 文化之日
        '11-23' => '勤労感謝の日',     // 勞動感謝日
        '12-31' => '大晦日',           // 除夕

        // === 西洋節日 ===
        '2-14' => 'バレンタインデー',  // 情人節
        '3-14' => 'ホワイトデー',      // 白色情人節
        '4-1' => 'エイプリルフール',   // 愚人節
        '10-31' => 'ハロウィン',       // 萬聖節
        '12-24' => 'クリスマスイブ',   // 聖誕夜
        '12-25' => 'クリスマス',       // 聖誕節

        // === 東洋節日 ===
        '7-7' => '七夕',               // 七夕
    ];

    return isset($holidays[$key]) ? $holidays[$key] : null;
}

/**
 * 檢查動態計算的節日（每月第 N 個星期 X）
 * 
 * @param int $month 月份
 * @param int $day 日期
 * @param array $calendar 日曆配置
 * @return string|null 節日名稱，無則返回 null
 */
function mpu_check_dynamic_holiday($month, $day, $calendar)
{
    if (empty($calendar['dynamic_holidays'])) {
        return null;
    }

    // 獲取當前年份
    $original_timezone = date_default_timezone_get();
    $wp_timezone = wp_timezone_string();
    date_default_timezone_set($wp_timezone);
    $year = (int) date('Y');
    date_default_timezone_set($original_timezone);

    // 計算這一天是星期幾（0=日, 1=月, ..., 6=土）
    $date_string = sprintf('%04d-%02d-%02d', $year, $month, $day);
    $timestamp = strtotime($date_string);
    if ($timestamp === false) {
        return null;
    }
    $day_of_week = (int) date('w', $timestamp);

    // 計算這是該月的第幾週（第 N 個星期 X）
    // 例如：1月12日是星期一，計算是第幾個星期一
    $first_day_of_month = strtotime(sprintf('%04d-%02d-01', $year, $month));
    $first_dow = (int) date('w', $first_day_of_month);

    // 計算該月第一個與今天相同星期幾的日期
    $days_until_first_same_dow = ($day_of_week - $first_dow + 7) % 7;
    $first_same_dow_day = 1 + $days_until_first_same_dow;

    // 計算今天是第幾個
    $week_of_month = (int) floor(($day - $first_same_dow_day) / 7) + 1;

    // 格式：月-第N週-星期（0=日, 1=月, ..., 6=土）
    $key = "{$month}-{$week_of_month}-{$day_of_week}";

    if (!empty($calendar['dynamic_holidays'][$key]['name'])) {
        return $calendar['dynamic_holidays'][$key]['name'];
    }

    return null;
}

/**
 * 獲取完整的節日配置（包含提示詞類別等）
 * 
 * @param int $month 月份（1-12）
 * @param int $day 日期（1-31）
 * @param string|null $personality_id 角色 ID（可選）
 * @return array|null 節日配置陣列（包含 name, prompts 等），無節日則返回 null
 */
function mpu_get_holiday_config($month, $day, $personality_id = null)
{
    $key = "{$month}-{$day}";

    if (function_exists('mpu_load_personality_calendar')) {
        $calendar = mpu_load_personality_calendar($personality_id);

        // 1. 自訂節日
        if (!empty($calendar['holidays'][$key])) {
            return $calendar['holidays'][$key];
        }

        // 2. 紀念日（years_passed）
        if (!empty($calendar['anniversaries'][$key])) {
            $config = $calendar['anniversaries'][$key];

            if (!empty($config['year_started'])) {
                $current_year = (int) wp_date('Y');
                $config['years_passed'] = $current_year - (int) $config['year_started'];
            }

            return $config;
        }

        // 3. 動態節日
        if (!empty($calendar['dynamic_holidays'])) {
            $dynamic_key = mpu_get_dynamic_holiday_key($month, $day);
            if ($dynamic_key && !empty($calendar['dynamic_holidays'][$dynamic_key])) {
                return $calendar['dynamic_holidays'][$dynamic_key];
            }
        }

        // 4. 期間節日（holiday_periods）— ✅ 支援跨年
        if (!empty($calendar['holiday_periods']) && is_array($calendar['holiday_periods'])) {
            $tz = wp_timezone();
            $year = (int) wp_date('Y');
            $current = (new DateTimeImmutable('now', $tz))->setDate($year, (int)$month, (int)$day)->setTime(0, 0, 0);

            foreach ($calendar['holiday_periods'] as $period_config) {
                if (empty($period_config['start']) || empty($period_config['end'])) {
                    continue;
                }

                [$start_month, $start_day] = array_map('intval', explode('-', $period_config['start']));
                [$end_month, $end_day]     = array_map('intval', explode('-', $period_config['end']));

                $start = (new DateTimeImmutable('now', $tz))->setDate($year, $start_month, $start_day)->setTime(0, 0, 0);
                $end   = (new DateTimeImmutable('now', $tz))->setDate($year, $end_month, $end_day)->setTime(23, 59, 59);

                // 如果 end < start，代表跨年（例如 12/20 ~ 1/5）
                if ($end < $start) {
                    if ($current >= $start) {
                        // 12月那段：end 推到明年
                        $end = $end->modify('+1 year');
                    } else {
                        // 1月那段：start 拉到去年
                        $start = $start->modify('-1 year');
                    }
                }

                if ($current >= $start && $current <= $end) {
                    return $period_config;
                }
            }
        }
    }

    // 無 JSON 時，回退到預設節日
    $holiday_name = mpu_get_holiday($month, $day);
    if ($holiday_name) {
        return [
            'name' => $holiday_name,
            'prompts' => ['holiday'],
        ];
    }

    return null;
}

/**
 * 獲取指定日期所在的節日期間（僅檢查 holiday_periods，不觸發 early-return 的固定節日查詢）
 *
 * 用於補充 mpu_get_holiday_config 的不足：當某天同時是固定節日（如昭和の日）
 * 且屬於某個期間（如ゴールデンウィーク）時，mpu_get_holiday_config 只會回傳固定節日，
 * 此函數可獨立取得期間資訊。
 *
 * @param int $month 月份（1-12）
 * @param int $day 日期（1-31）
 * @param string|null $personality_id 角色 ID（可選）
 * @return array|null 節日期間配置陣列，或 null
 */
function mpu_get_active_holiday_period($month, $day, $personality_id = null)
{
    if (!function_exists('mpu_load_personality_calendar')) {
        return null;
    }
    $calendar = mpu_load_personality_calendar($personality_id);
    if (empty($calendar['holiday_periods']) || !is_array($calendar['holiday_periods'])) {
        return null;
    }

    $tz      = wp_timezone();
    $year    = (int) wp_date('Y');
    $current = (new DateTimeImmutable('now', $tz))
        ->setDate($year, (int) $month, (int) $day)
        ->setTime(0, 0, 0);

    foreach ($calendar['holiday_periods'] as $period_config) {
        if (empty($period_config['start']) || empty($period_config['end'])) {
            continue;
        }
        [$sm, $sd] = array_map('intval', explode('-', $period_config['start']));
        [$em, $ed] = array_map('intval', explode('-', $period_config['end']));

        $start = (new DateTimeImmutable('now', $tz))->setDate($year, $sm, $sd)->setTime(0, 0, 0);
        $end   = (new DateTimeImmutable('now', $tz))->setDate($year, $em, $ed)->setTime(23, 59, 59);

        if ($end < $start) {
            $current >= $start ? $end = $end->modify('+1 year') : $start = $start->modify('-1 year');
        }

        if ($current >= $start && $current <= $end) {
            return $period_config;
        }
    }
    return null;
}

/**
 * 獲取動態節日的配置 key
 *
 * @param int $month 月份
 * @param int $day 日期
 * @return string|null 動態節日 key（格式：月-第N週-星期），或 null
 */
function mpu_get_dynamic_holiday_key($month, $day)
{
    $original_timezone = date_default_timezone_get();
    $wp_timezone = wp_timezone_string();
    date_default_timezone_set($wp_timezone);
    $year = (int) date('Y');
    date_default_timezone_set($original_timezone);

    $date_string = sprintf('%04d-%02d-%02d', $year, $month, $day);
    $timestamp = strtotime($date_string);
    if ($timestamp === false) {
        return null;
    }

    $day_of_week = (int) date('w', $timestamp);
    $first_day_of_month = strtotime(sprintf('%04d-%02d-01', $year, $month));
    $first_dow = (int) date('w', $first_day_of_month);

    $days_until_first_same_dow = ($day_of_week - $first_dow + 7) % 7;
    $first_same_dow_day = 1 + $days_until_first_same_dow;
    $week_of_month = (int) floor(($day - $first_same_dow_day) / 7) + 1;

    return "{$month}-{$week_of_month}-{$day_of_week}";
}

/**
 * 獲取時間情境（完整版：日期 + 星期 + 季節 + 時間段 + 節日 + 天氣）
 * 
 * @param string|null $personality_id 角色 ID（可選，用於讀取人格專屬日曆）
 * @return string 時間情境字串，如「1月2日（木曜日）・冬の朝」或「12月25日（水曜日）・冬の夜【クリスマス】【天気：晴れ 18°C / 明日：曇り 15°C】」
 */
function mpu_get_time_context($personality_id = null)
{
    // 站點時區的現在時間
    $hour        = (int) wp_date('G');
    $month       = (int) wp_date('n');
    $day         = (int) wp_date('j');
    $day_of_week = (int) wp_date('w');

    $season  = mpu_get_season($month);
    $weekday = mpu_get_weekday_name($day_of_week);

    // ✅ 先拿「完整節日設定」（包含 personality calendar / periods / dynamic / anniversary）
    $holiday_config = mpu_get_holiday_config($month, $day, $personality_id);
    $holiday_name = !empty($holiday_config['name']) ? $holiday_config['name'] : null;

    // 判定時間段
    if ($hour >= 5 && $hour < 12) {
        $time_period = '朝';
    } elseif ($hour >= 12 && $hour < 18) {
        $time_period = '昼';
    } elseif ($hour >= 18 && $hour < 22) {
        $time_period = '夜';
    } else {
        $time_period = '深夜';
    }

    // 加入具體時間（小時:分鐘）和年份
    $minute = (int) wp_date('i');
    $year = (int) wp_date('Y');
    $context = "{$year}年{$month}月{$day}日（{$weekday}）{$hour}時{$minute}分・{$season}の{$time_period}";

    if ($holiday_name) {
        $context .= "【{$holiday_name}】";
    }

    // 附加天氣資訊（如果啟用）
    if (function_exists('mpu_get_weather_context')) {
        $weather_context = mpu_get_weather_context();
        if (!empty($weather_context)) {
            $context .= $weather_context;
        }
    }

    return $context;
}

/**
 * 睡眠邏輯（每個人格獨立）：
 * 1) 深夜：deep_sleep_start ~ deep_sleep_end（例：00:00~06:00）必睡，不檢查 IP；刷新頁面會繼續睡
 * 2) 賴床：deep_sleep_end ~ 今日 oversleep_end（例：06:00~06/07/08）可能繼續睡；若被叫醒則記 IP 3 小時，3 小時內刷新保持清醒
 * 3) 清醒：>= 今日 oversleep_end
 * 
 * ★ 重要：IP 記錄機制在整個「可能賴床時間範圍」(deep_sleep_end ~ oversleep_max_hour) 內都有效
 *    這確保了即使當天抽中「不賴床」，被叫醒的 IP 在 06:00~08:00 期間刷新仍保持清醒
 */

/**
 * 判斷是否在深度睡眠時間帶（支援賴床功能）
 *
 * @param string|null $personality_id 角色 ID
 * @return bool 是否在深度睡眠時間
 */
function mpu_is_deep_sleep_time($personality_id = null)
{
	// 午休時段（白天小睡）也視為「睡著」，讓所有下游行為（降頻、夢話、
	// 摸頭／叫醒反應、權重調整）自動沿用晚睡的機制.
	if ( mpu_is_nap_time( $personality_id ) ) {
		return true;
	}

    // 目前時間（站點時區），minutes-of-day (0–1439)
    $current_mod = (int) wp_date('G') * 60 + (int) wp_date('i');

    // 載入角色睡眠設定
    $sleep_settings     = mpu_get_sleep_settings($personality_id);
    $start_mod          = (int) mpu_get_daily_deep_sleep_start_mod($personality_id);
    $end_mod            = mpu_sleep_hour_to_boundary_mod($sleep_settings['deep_sleep_end']);
    $oversleep_enabled  = !empty($sleep_settings['oversleep_enabled']);

    // ===== 階段 1：深夜必睡（不檢查 IP）=====
    $in_deep_sleep = false;

    if ($start_mod < $end_mod) {
        // 不跨午夜：例如 00:00 ~ 07:00
        $in_deep_sleep = ($current_mod >= $start_mod && $current_mod < $end_mod);
    } else {
        // 跨午夜：例如 22:14 ~ 07:00
        $in_deep_sleep = ($current_mod >= $start_mod || $current_mod < $end_mod);
    }

    if ($in_deep_sleep) {
        return true;
    }

    // ===== 階段 2：賴床時間（才檢查 IP）=====
    if (!$oversleep_enabled) {
        return false;
    }

    // 今日賴床結束時間（minutes-of-day）
    $oversleep_end_mod = (int) mpu_get_daily_oversleep_end_mod($personality_id);

    // 今天不賴床（== deep_sleep_end 整點），準時起床
    if ($oversleep_end_mod <= $end_mod) {
        return false;
    }

    // 不在賴床區間就清醒
    $in_oversleep_range = ($current_mod >= $end_mod && $current_mod < $oversleep_end_mod);
    if (!$in_oversleep_range) {
        return false;
    }

    // 賴床區間內：被叫醒就不再睡
    if (mpu_is_ip_woken_today($personality_id)) {
        return false;
    }

    return true;
}

/**
 * 獲取角色的睡眠設定
 *
 * @param string|null $personality_id 角色 ID
 * @return array 睡眠設定
 */
function mpu_get_sleep_settings($personality_id = null)
{
    $defaults = [
        'deep_sleep_start'        => 0,    // 00:00
        'deep_sleep_end'          => 6,    // 06:00
        'oversleep_enabled'       => true, // 是否啟用賴床（人格可不同）
        'oversleep_max_hour'      => 8,    // 最晚 08:00
        'oversleep_probability'   => 0.5,  // 50% 機率賴床
		// 午休（白天小睡）：預設關閉，人格可於 manifest 的 sleep_settings.nap 開啟.
		// window_* 為 minutes-of-day（分鐘精度），與晚睡的整點 hour 不同.
		'nap'                   => array(
			'enabled'      => false, // 是否啟用午休.
			'window_start' => 750,   // 12:30（午休可發生的最早時刻）.
			'window_end'   => 810,   // 13:30（午休必須結束的最晚時刻）.
			'probability'  => 0.4,   // 當天午休的機率（0.4 ≈ 每週 2.8 次）.
			'min_minutes'  => 30,    // 最短午睡（分鐘）.
			'max_minutes'  => 60,    // 最長午睡（分鐘）.
		),
    ];

    if (function_exists('mpu_load_personality_manifest')) {
        $manifest = mpu_load_personality_manifest($personality_id);
        if (!empty($manifest['sleep_settings']) && is_array($manifest['sleep_settings'])) {
			$settings = array_merge($defaults, $manifest['sleep_settings']);
			if ( ! empty( $settings['nap'] ) && is_array( $settings['nap'] ) ) {
				$settings['nap'] = array_merge( $defaults['nap'], $settings['nap'] );
			}
			return $settings;
        }
    }

    return $defaults;
}

/**
 * 把人格 ID 正規化成適合當 transient key 的字串
 *
 * @param string|null $personality_id
 * @return string
 */
function mpu_norm_personality_key($personality_id = null)
{
    if (empty($personality_id)) {
        return 'default';
    }
    return sanitize_key((string) $personality_id);
}

/**
 * 將分鐘值限制在 minutes-of-day 範圍。
 *
 * @param int $minute 分鐘值
 * @return int 0–1439
 */
function mpu_sleep_clamp_mod($minute)
{
    return max(0, min(1439, (int) $minute));
}

/**
 * 將 manifest hour 轉成 minutes-of-day 起點。
 *
 * deep_sleep_start 的舊語義允許 24 代表跨日午夜，因此保留 24 → 0。
 *
 * @param mixed $hour 整點 hour
 * @return int 0–1439
 */
function mpu_sleep_hour_to_start_mod($hour)
{
    $hour = (int) $hour;
    if ($hour === 24) {
        return 0;
    }
    return mpu_sleep_clamp_mod($hour * 60);
}

/**
 * 將 manifest hour 轉成 minutes-of-day 範圍結尾。
 *
 * @param mixed $hour 整點 hour
 * @return int 0–1439
 */
function mpu_sleep_hour_to_end_mod($hour)
{
    return mpu_sleep_clamp_mod((int) $hour * 60 + 59);
}

/**
 * 將 manifest hour 轉成整點 boundary。
 *
 * @param mixed $hour 整點 hour
 * @return int 0–1439
 */
function mpu_sleep_hour_to_boundary_mod($hour)
{
    return mpu_sleep_clamp_mod((int) $hour * 60);
}


/**
 * 獲取今天的隨機賴床結束時間（分鐘精度，每個人格每天各自抽一次）
 *
 * manifest 的 deep_sleep_end / oversleep_max_hour 仍寫整點 hour，
 * 本函數將其轉換為 minutes-of-day (0–1439) 並加入分鐘隨機。
 * 例如 deep_sleep_end=7, oversleep_max_hour=9 且賴床 → random_int(420, 599)，
 * 即 07:00 ~ 09:59。
 *
 * @param string|null $personality_id 角色 ID
 * @return int 賴床結束的 minutes-of-day (0–1439)
 */
function mpu_get_daily_oversleep_end_mod($personality_id = null)
{
    $today = wp_date('Y-m-d');
    $pid   = mpu_norm_personality_key($personality_id);

    $cache_key = "mpu_oversleep_end_mod_{$today}_{$pid}";
    $cached = get_transient($cache_key);
    if ($cached !== false) {
        return (int) $cached;
    }

    $sleep_settings     = mpu_get_sleep_settings($personality_id);
    $deep_sleep_end     = (int) $sleep_settings['deep_sleep_end'];
    $oversleep_max_hour = (int) $sleep_settings['oversleep_max_hour'];
    $probability        = (float) $sleep_settings['oversleep_probability'];
    $oversleep_enabled  = !empty($sleep_settings['oversleep_enabled']);

    // 到午夜的秒數（站點時區）
    $tz  = wp_timezone();
    $now = new DateTimeImmutable('now', $tz);
    $mid = new DateTimeImmutable('tomorrow', $tz);
    $seconds_until_midnight = max(60, $mid->getTimestamp() - $now->getTimestamp());

    $deep_sleep_end_mod = mpu_sleep_hour_to_boundary_mod($deep_sleep_end);
    $oversleep_max_mod  = mpu_sleep_hour_to_end_mod($oversleep_max_hour);
    $end_mod = $deep_sleep_end_mod; // 不賴床的預設值

    // 未啟用賴床：直接固定為 deep_sleep_end 整點
    if (!$oversleep_enabled) {
        set_transient($cache_key, $end_mod, $seconds_until_midnight);
        return $end_mod;
    }

    // 設定合理性檢查
    if ($oversleep_max_mod <= $deep_sleep_end_mod) {
        mpu_log_warning('睡眠設定錯誤：oversleep_max_hour 必須大於 deep_sleep_end');
        set_transient($cache_key, $end_mod, $seconds_until_midnight);
        return $end_mod;
    }

    // 抽籤決定是否賴床
    if (random_int(1, 100) <= $probability * 100) {
        // 賴床：deep_sleep_end:00 ~ oversleep_max_hour:59
        $end_mod = random_int($deep_sleep_end_mod, $oversleep_max_mod);
    }
    // else: 不賴床，維持 deep_sleep_end * 60

    set_transient($cache_key, $end_mod, $seconds_until_midnight);
    return $end_mod;
}


/**
 * 獲取今天的隨機睡眠開始時間（分鐘精度，每個人格每天各自抽一次）
 *
 * manifest 的 deep_sleep_start 仍寫整點 hour（scalar 或 [min, max]），
 * 本函數將其轉換為 minutes-of-day (0–1439) 並加入分鐘隨機。
 * 例如 [22, 23] 解為「22:00 ~ 23:59」→ random_int(1320, 1439)。
 *
 * @param string|null $personality_id 角色 ID
 * @return int 睡眠開始的 minutes-of-day (0–1439)
 */
function mpu_get_daily_deep_sleep_start_mod($personality_id = null)
{
    $today = wp_date('Y-m-d');
    $pid   = mpu_norm_personality_key($personality_id);

    $cache_key = "mpu_deep_sleep_start_mod_{$today}_{$pid}";
    $cached = get_transient($cache_key);
    if ($cached !== false) {
        return (int) $cached;
    }

    $sleep_settings = mpu_get_sleep_settings($personality_id);
    $start_setting = $sleep_settings['deep_sleep_start'] ?? 0;

    // 到午夜的秒數（站點時區）
    $tz  = wp_timezone();
    $now = new DateTimeImmutable('now', $tz);
    $mid = new DateTimeImmutable('tomorrow', $tz);
    $seconds_until_midnight = max(60, $mid->getTimestamp() - $now->getTimestamp());

    if (is_array($start_setting)) {
        if (count($start_setting) === 2) {
            $a = (int) $start_setting[0];
            $b = (int) $start_setting[1];
            $min_hour = min($a, $b);
            $max_hour = max($a, $b);
            $min_mod = mpu_sleep_hour_to_start_mod($min_hour);
            $max_mod = mpu_sleep_hour_to_end_mod($max_hour);
            // [22, 23] → 22:00 ~ 23:59 → random_int(1320, 1439)
            $start_mod = $max_mod > $min_mod ? random_int($min_mod, $max_mod) : $min_mod;
        } else {
            mpu_log_warning('睡眠設定錯誤：deep_sleep_start array must have exactly 2 elements');
            $start_mod = mpu_sleep_hour_to_start_mod($start_setting[0] ?? 0);
        }
    } else {
        $start_mod = mpu_sleep_hour_to_start_mod($start_setting);
    }

    set_transient($cache_key, $start_mod, $seconds_until_midnight);
    return $start_mod;
}

/**
 * 獲取今天的午休時段（白天小睡，每個人格每天各自抽一次）
 *
 * 與晚睡不同，午休：
 *   1) 不是每天都發生（依 nap.probability 抽籤，例如 0.4 ≈ 每週 2.8 次）
 *   2) 起訖採分鐘精度（window_start / window_end 為 minutes-of-day）
 *   3) 午睡長度隨機（min_minutes ~ max_minutes），整段保證落在 window 內
 *
 * 結果以 transient 快取到午夜，確保同一天判斷穩定（刷新頁面不會重抽）。
 *
 * @param string|null $personality_id 角色 ID.
 * @return array|null [start_mod, end_mod]；今天不午休或未啟用時回 null
 */
function mpu_get_daily_nap_window( $personality_id = null ) {
	$sleep_settings = mpu_get_sleep_settings( $personality_id );
	$nap            = ( isset( $sleep_settings['nap'] ) && is_array( $sleep_settings['nap'] ) )
		? $sleep_settings['nap']
		: array();

	// 未啟用午休：直接視為今天不午休.
	if ( empty( $nap['enabled'] ) ) {
		return null;
	}

	$today = wp_date( 'Y-m-d' );
	$pid   = mpu_norm_personality_key( $personality_id );

	$cache_key = "mpu_nap_window_{$today}_{$pid}";
	$cached    = get_transient( $cache_key );
	if ( is_array( $cached ) ) {
		return ! empty( $cached['has_nap'] )
			? array( (int) $cached['start'], (int) $cached['end'] )
			: null;
	}

	// 解析設定（minutes-of-day）.
	$window_start = mpu_sleep_clamp_mod( $nap['window_start'] ?? 750 );
	$window_end   = mpu_sleep_clamp_mod( $nap['window_end'] ?? 810 );
	$min_minutes  = max( 1, (int) ( $nap['min_minutes'] ?? 30 ) );
	$max_minutes  = max( $min_minutes, (int) ( $nap['max_minutes'] ?? 60 ) );
	$probability  = max( 0.0, min( 1.0, (float) ( $nap['probability'] ?? 0.4 ) ) );

	// 到午夜的秒數（站點時區）.
	$tz                     = wp_timezone();
	$now                    = new DateTimeImmutable( 'now', $tz );
	$mid                    = new DateTimeImmutable( 'tomorrow', $tz );
	$seconds_until_midnight = max( 60, $mid->getTimestamp() - $now->getTimestamp() );

	$result = array(
		'has_nap' => false,
		'start'   => 0,
		'end'     => 0,
	);

	// 設定合理性檢查：window 至少要容得下最短午睡.
	$window_span = $window_end - $window_start;
	if ( $window_span < $min_minutes ) {
		mpu_log_warning( '午休設定錯誤：nap window 太窄，無法容納 min_minutes' );
		set_transient( $cache_key, $result, $seconds_until_midnight );
		return null;
	}

	// 抽籤決定今天是否午休.
	if ( random_int( 1, 10000 ) <= (int) round( $probability * 10000 ) ) {
		// 午睡長度受 window 寬度上限約束，保證整段落在 window 內.
		$duration     = random_int( $min_minutes, min( $max_minutes, $window_span ) );
		$latest_start = $window_end - $duration;
		$start_mod    = random_int( $window_start, $latest_start );
		$result       = array(
			'has_nap' => true,
			'start'   => $start_mod,
			'end'     => $start_mod + $duration,
		);
	}

	set_transient( $cache_key, $result, $seconds_until_midnight );
	return ! empty( $result['has_nap'] ) ? array( $result['start'], $result['end'] ) : null;
}

/**
 * 判斷當前是否在午休時段內
 *
 * @param string|null $personality_id 角色 ID.
 * @return bool
 */
function mpu_is_nap_time( $personality_id = null ) {
	$window = mpu_get_daily_nap_window( $personality_id );
	if ( null === $window ) {
		return false;
	}

	$current_mod = (int) wp_date( 'G' ) * 60 + (int) wp_date( 'i' );
	return $current_mod >= $window[0] && $current_mod < $window[1];
}

/**
 * 檢查當前 IP 是否已被叫醒
 *
 * ★ 在「可能賴床的時間範圍」(deep_sleep_end ~ oversleep_max_hour) 內檢查 IP 記錄
 * ★ 使用 oversleep_max_hour 而非當天抽中的 oversleep_end，確保被叫醒後刷新頁面仍保持清醒
 * ★ 深夜（deep_sleep_start ~ deep_sleep_end）不檢查 IP，總是返回 false（刷新頁面照樣睡）
 *
 * @param string|null $personality_id 角色 ID
 * @return bool
 */
function mpu_is_ip_woken_today($personality_id = null)
{
    $sleep_settings = mpu_get_sleep_settings($personality_id);
    if (empty($sleep_settings['oversleep_enabled'])) {
        return false;
    }

    $current_mod = (int) wp_date('G') * 60 + (int) wp_date('i');
    $today       = wp_date('Y-m-d');
    $pid         = mpu_norm_personality_key($personality_id);

    $deep_sleep_end_mod = mpu_sleep_hour_to_boundary_mod($sleep_settings['deep_sleep_end']);
    $oversleep_max_mod  = mpu_sleep_hour_to_end_mod($sleep_settings['oversleep_max_hour']);

    // 在可能賴床的時間範圍內（07:00~09:59）都檢查 IP 記錄
    if ($current_mod < $deep_sleep_end_mod || $current_mod > $oversleep_max_mod) {
        return false;
    }

    $ip = function_exists('mpu_get_client_ip')
        ? mpu_get_client_ip()
        : ($_SERVER['REMOTE_ADDR'] ?? '');

    $cache_key = "mpu_woken_ips_{$today}_{$pid}";
    $woken_ips = get_transient($cache_key);

    if ($woken_ips === false) {
        return false;
    }

    return in_array($ip, (array) $woken_ips, true);
}

/**
 * 記錄 IP 已被叫醒
 *
 * ★ 在「可能賴床的時間範圍」(deep_sleep_end ~ oversleep_max_hour) 內記錄 IP，持續 3 小時
 * ★ 使用 oversleep_max_hour 而非當天抽中的 oversleep_end，確保記錄範圍一致
 * ★ 深夜喚醒不記錄（刷新頁面會恢復睡眠）
 *
 * @param string|null $personality_id 角色 ID
 * @return bool false 表示不在賴床時間／或未啟用賴床，不記錄
 */
function mpu_mark_ip_as_woken($personality_id = null)
{
    $sleep_settings = mpu_get_sleep_settings($personality_id);
    if (empty($sleep_settings['oversleep_enabled'])) {
        return false;
    }

    $current_mod = (int) wp_date('G') * 60 + (int) wp_date('i');
    $today       = wp_date('Y-m-d');
    $pid         = mpu_norm_personality_key($personality_id);

    $deep_sleep_end_mod = mpu_sleep_hour_to_boundary_mod($sleep_settings['deep_sleep_end']);
    $oversleep_max_mod  = mpu_sleep_hour_to_end_mod($sleep_settings['oversleep_max_hour']);

    // 在可能賴床的時間範圍內（07:00~09:59）都可記錄 IP
    if ($current_mod < $deep_sleep_end_mod || $current_mod > $oversleep_max_mod) {
        return false;
    }

    $ip = function_exists('mpu_get_client_ip')
        ? mpu_get_client_ip()
        : ($_SERVER['REMOTE_ADDR'] ?? '');

    $cache_key = "mpu_woken_ips_{$today}_{$pid}";
    $woken_ips = get_transient($cache_key);
    if ($woken_ips === false) {
        $woken_ips = [];
    }

    if (!in_array($ip, $woken_ips, true)) {
        $woken_ips[] = $ip;
        // 3 小時過期，避免跨到晚上
        set_transient($cache_key, $woken_ips, 3 * HOUR_IN_SECONDS);
    }

    return true;
}

/**
 * 獲取睡眠模式設定
 * 
 * 當在深度睡眠時間時，返回減少發話頻率的設定
 * - frequency_multiplier: 頻率倍率（0.2 = 20%，間隔延長 5 倍）
 * 
 * @param string|null $personality_id 角色 ID（可選）
 * @return array 睡眠模式設定陣列
 */
function mpu_get_sleep_mode_settings($personality_id = null)
{
    if (!mpu_is_deep_sleep_time($personality_id)) {
        return [
            'enabled' => false,
            'frequency_multiplier' => 1.0,
        ];
    }

    return [
        'enabled' => true,
        'frequency_multiplier' => 0.0667,  // 頻率降為 6.7%（間隔延長 15 倍，約 180 秒）
    ];
}

/**
 * 獲取訪客資訊（包括 BOT 資訊）供 LLM 使用
 * 此函數類似於 mpu_ajax_get_visitor_info()，但返回陣列而非 JSON
 * 
 * @return array 訪客資訊陣列，包含 is_bot, browser_name, browser_type, slimstat_country, slimstat_city 等
 */
function mpu_get_visitor_info_for_llm()
{
    global $wpdb;

    // 從 $_SERVER 獲取基本資訊（使用通用 IP 獲取函數，支援 Cloudflare 等反向代理）
    $ip = mpu_get_client_ip();

    // 準備返回的資訊
    $visitor_info = [
        "is_bot" => false,
        "browser_type" => 0,
        "browser_name" => "",
        "slimstat_enabled" => false,
    ];

    // 使用 Slimstat 獲取更詳細的訪客資訊
    if (class_exists('wp_slimstat')) {
        $visitor_info["slimstat_enabled"] = true;

        // 直接查詢 Slimstat 資料庫
        $slimstat_table = $wpdb->prefix . 'slim_stats';

        // 使用 prepare 防止 SQL 注入（安全性）
        $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $slimstat_table));
        if ($table_exists == $slimstat_table) {
            // 查詢當前 IP 最近的完整記錄（包含 BOT 資訊）
            $query = $wpdb->prepare(
                "SELECT country, city, browser, browser_type FROM {$slimstat_table} WHERE ip = %s ORDER BY dt DESC LIMIT 1",
                $ip
            );
            $result = $wpdb->get_row($query, OBJECT);

            if (!empty($result)) {
                // 獲取 country
                if (!empty($result->country)) {
                    $visitor_info["slimstat_country"] = sanitize_text_field($result->country);
                }

                // 獲取 city（可選）
                if (!empty($result->city)) {
                    $visitor_info["slimstat_city"] = sanitize_text_field($result->city);
                }

                if (isset($result->browser_type)) {
                    $visitor_info["is_bot"] = (intval($result->browser_type) === 1);
                    $visitor_info["browser_type"] = intval($result->browser_type);
                }

                if (!empty($result->browser)) {
                    $visitor_info["browser_name"] = sanitize_text_field($result->browser);
                }
            } else {
                if (class_exists('\SlimStat\Services\Browscap')) {
                    $browscap_class = '\SlimStat\Services\Browscap';
                    $browser = $browscap_class::get_browser();
                    if (!empty($browser)) {
                        $visitor_info["is_bot"] = (isset($browser['browser_type']) && intval($browser['browser_type']) === 1);
                        $visitor_info["browser_type"] = isset($browser['browser_type']) ? intval($browser['browser_type']) : 0;
                        if (!empty($browser['browser'])) {
                            $visitor_info["browser_name"] = sanitize_text_field($browser['browser']);
                        }
                    }
                }
            }
        }
    }

    return $visitor_info;
}

/**
 * 獲取訪客狀態文字（BOT 或地理位置）
 * 
 * @param array $visitor_info 訪客資訊
 * @return string 訪客狀態描述
 */
function mpu_get_visitor_status_text($visitor_info)
{
    // BOT 檢測優先
    if (!empty($visitor_info['is_bot']) && $visitor_info['is_bot'] === true) {
        $bot_name = !empty($visitor_info['browser_name'])
            ? $visitor_info['browser_name']
            : '未知機器人';
        return "🤖 BOT: {$bot_name}";
    }

    // 地理位置資訊
    if (!empty($visitor_info['slimstat_country'])) {
        $location = function_exists('mpu_country_code_to_name') ? mpu_country_code_to_name($visitor_info['slimstat_country']) : $visitor_info['slimstat_country'];
        if (!empty($visitor_info['slimstat_city'])) {
            $location .= " / {$visitor_info['slimstat_city']}";
        }
        return "來自 {$location}";
    }

    return '';
}

/**
 * 獲取隨機文章供 LLM 推薦使用
 * 
 * @param int $count 要獲取的文章數量（1-3篇）
 * @return array 文章陣列，每個元素包含 'title' 和 'url'
 */
function mpu_get_random_posts_for_llm($count = 2)
{
    // 限制數量範圍
    $count = max(1, min(3, intval($count)));

    // 查詢隨機的已發布文章
    $posts = get_posts([
        "numberposts" => $count,
        "orderby" => "rand",
        "post_status" => "publish",
        "suppress_filters" => true,
    ]);

    $articles = [];

    foreach ($posts as $post) {
        $title = get_the_title($post->ID);
        $permalink = get_permalink($post->ID);

        $articles[] = [
            'title' => $title,
            'url' => $permalink,
        ];
    }

    return $articles;
}

/**
 * 加權隨機選擇函數
 * 
 * 根據權重陣列，從類別陣列中隨機選擇一個類別
 * 權重越高，被選中的機率越大
 * 
 * @param array $categories 類別陣列（key => value）
 * @param array $weights 權重陣列（key => weight）
 * @return string 選中的類別 key
 */
function mpu_weighted_random_select($categories, $weights)
{
    // 計算總權重
    $total_weight = 0;
    $weighted_keys = [];

    foreach ($categories as $key => $value) {
        // 如果該類別有設定權重，使用設定的權重；否則使用預設權重 5
        $weight = isset($weights[$key]) ? $weights[$key] : 5;
        $total_weight += $weight;
        $weighted_keys[$key] = $weight;
    }

    // 如果總權重為 0，使用均勻隨機
    if ($total_weight <= 0) {
        return array_rand($categories);
    }

    // 生成 0 到總權重之間的隨機數
    $random = wp_rand(1, $total_weight);

    // 根據權重區間選擇類別
    $current_weight = 0;
    foreach ($weighted_keys as $key => $weight) {
        $current_weight += $weight;
        if ($random <= $current_weight) {
            return $key;
        }
    }

    // 如果沒有選中（理論上不應該發生），返回第一個類別
    return array_key_first($categories);
}

/**
 * 建構優化後的 System Prompt（XML 結構化版本）
 * 
 * @param array $mpu_opt 外掛設定
 * @param array $wp_info WordPress 資訊
 * @param array $user_info 用戶資訊
 * @param array $visitor_info 訪客資訊
 * @param string $ukagaka_name 偽春菜名稱
 * @param string $time_context 時間情境（早上/下午/晚上/深夜）
 * @param string $language 語言設定
 * @return string 優化後的 system prompt
 */
function mpu_build_optimized_system_prompt(
    $mpu_opt,
    $wp_info,
    $user_info,
    $visitor_info,
    $ukagaka_name,
    $time_context,
    $language,
    $personality_id = null // 新增可選參數：如果已解析好則直接使用，否則內部解析
) {
    $ukagaka_display_name = $mpu_opt['ukagakas'][$ukagaka_name]['name'] ?? '偽春菜';

    // 解析 personality_id（如果未提供）
    if ($personality_id === null && function_exists('mpu_get_personality_id_from_ukagaka_name')) {
        $personality_id = mpu_get_personality_id_from_ukagaka_name($ukagaka_name);
    }

    // 準備變數陣列
    $variables = [
        'ukagaka_display_name' => $ukagaka_display_name,
        'language' => function_exists('mpu_resolve_language')
            ? mpu_resolve_language($personality_id, $language)
            : $language,
        'time_context' => $time_context,
        'wp_version' => $wp_info['wp_version'] ?? '',
        'php_version' => $wp_info['php_version'] ?? '',
        'post_count' => $wp_info['post_count'] ?? 0,
        'comment_count' => $wp_info['comment_count'] ?? 0,
        'category_count' => $wp_info['category_count'] ?? 0,
        'tag_count' => $wp_info['tag_count'] ?? 0,
        'days_operating' => $wp_info['days_operating'] ?? 0,
        'theme_name' => $wp_info['theme_name'] ?? '',
        'theme_version' => $wp_info['theme_version'] ?? '',
        'theme_author' => $wp_info['theme_author'] ?? '',
        'spam_count' => $wp_info['spam_count'] ?? 0, // 新增垃圾留言統計
    ];

    // 添加 Slimstat 統計數據變數
    $slimstat_total_visits = $wp_info['slimstat_total_visits'] ?? 0;
    $variables['slimstat_total_visits'] = $slimstat_total_visits;

    // 格式化最熱門文章列表為易讀的文字
    $top_resources = $wp_info['slimstat_top_resources'] ?? [];
    $top_resources_text = '';
    if (!empty($top_resources)) {
        $resource_list = [];
        foreach ($top_resources as $resource) {
            $url = $resource['url'] ?? '';
            $title = $resource['title'] ?? '';
            if (!empty($url)) {
                if (!empty($title)) {
                    $resource_list[] = "{$title} ({$url})";
                } else {
                    $resource_list[] = $url;
                }
            }
        }
        if (!empty($resource_list)) {
            $top_resources_text = implode('、', $resource_list);
        }
    }
    $variables['slimstat_top_resources'] = $top_resources_text;

    // 統一系統提示詞解析（支援模組化檔案 → 舊版檔案 → 後台設定 → 預設值）
    $system_prompt = mpu_resolve_system_prompt($personality_id, $mpu_opt, $ukagaka_display_name, $variables);

    return $system_prompt;
}
