<?php

/**
 * 天氣功能模組
 * 
 * 使用 Open-Meteo 免費 API 獲取天氣資訊，讓角色能夠感知天氣。
 * 
 * @package MP_Ukagaka
 * @subpackage Weather
 */

if (!defined('ABSPATH')) {
    exit();
}

/**
 * 天氣代碼轉換為日文描述
 * 
 * Open-Meteo WMO Weather interpretation codes:
 * https://open-meteo.com/en/docs#weathervariables
 * 
 * @param int $code WMO 天氣代碼
 * @return string 日文天氣描述
 */
function mpu_weather_code_to_text($code)
{
    $weather_codes = [
        0  => '快晴',           // Clear sky
        1  => '晴れ',           // Mainly clear
        2  => '薄曇り',         // Partly cloudy
        3  => '曇り',           // Overcast
        45 => '霧',             // Fog
        48 => '濃霧',           // Depositing rime fog
        51 => '小雨',           // Drizzle: Light
        53 => '霧雨',           // Drizzle: Moderate
        55 => '霧雨',           // Drizzle: Dense
        56 => '凍雨',           // Freezing Drizzle: Light
        57 => '凍雨',           // Freezing Drizzle: Dense
        61 => '小雨',           // Rain: Slight
        63 => '雨',             // Rain: Moderate
        65 => '大雨',           // Rain: Heavy
        66 => '凍雨',           // Freezing Rain: Light
        67 => '凍雨',           // Freezing Rain: Heavy
        71 => '小雪',           // Snow fall: Slight
        73 => '雪',             // Snow fall: Moderate
        75 => '大雪',           // Snow fall: Heavy
        77 => '霰',             // Snow grains
        80 => 'にわか雨',       // Rain showers: Slight
        81 => 'にわか雨',       // Rain showers: Moderate
        82 => '豪雨',           // Rain showers: Violent
        85 => 'にわか雪',       // Snow showers: Slight
        86 => 'にわか雪',       // Snow showers: Heavy
        95 => '雷雨',           // Thunderstorm: Slight or moderate
        96 => '雷雨（雹）',     // Thunderstorm with slight hail
        99 => '雷雨（大雹）',   // Thunderstorm with heavy hail
    ];

    return $weather_codes[$code] ?? '不明';
}

/**
 * 依實際降水量(mm)校正液態降水的天氣標籤.
 *
 * Open-Meteo 的 WMO code 對雨勢分級偏保守，常把不小的雨歸成 drizzle(53/55)，
 * 導致角色把實際不小的雨說成「霧雨」。已取得當期累積降水量 precipitation_sum
 * (24h 累積 mm)，用它把標籤升級到與實際雨量相符的強度.
 *
 * 門檻沿用台灣 CWA 24h 累積雨量(>=80/200/350/500mm)，但標籤用自然日文（角色講日文）：
 * 大雨>=80mm、豪雨>=200mm、猛烈な雨>=350mm、記録的豪雨>=500mm；80mm 以下 10mm 起算為「雨」，
 * 10mm 以下保留原標籤（維持霧雨/小雨語感）.
 * 只取較強的一方，永不降級。僅作用於毛雨／連續雨系 (51,53,55,61,63,65)；
 * 陣雨(80-82)、雷雨、雪、凍雨維持原樣.
 *
 * @param int        $code      WMO 天氣代碼.
 * @param string     $base_text 以 code 翻出的原始標籤.
 * @param float|null $precip_mm 當期累積降水量 mm（今日或明日）.
 * @return string 校正後標籤.
 */
function mpu_weather_refine_rain_text( $code, $base_text, $precip_mm ) {
	// 僅校正液態連續降水系；陣雨/雷雨/雪/凍雨維持原樣.
	$rain_codes = array( 51, 53, 55, 61, 63, 65 );
	if ( ! in_array( $code, $rain_codes, true ) || null === $precip_mm ) {
		return $base_text;
	}

	// code 既有強度 rank：65=大雨、63=雨、其餘(霧雨/小雨)=1.
	if ( 65 === (int) $code ) {
		$code_rank = 3;
	} elseif ( 63 === (int) $code ) {
		$code_rank = 2;
	} else {
		$code_rank = 1;
	}

	// 依 24h 累積雨量(mm)推得強度 rank（CWA 門檻）.
	$mm = (float) $precip_mm;
	if ( $mm < 10.0 ) {
		$mm_rank = 1;
	} elseif ( $mm < 80.0 ) {
		$mm_rank = 2;
	} elseif ( $mm < 200.0 ) {
		$mm_rank = 3;
	} elseif ( $mm < 350.0 ) {
		$mm_rank = 4;
	} elseif ( $mm < 500.0 ) {
		$mm_rank = 5;
	} else {
		$mm_rank = 6;
	}

	// 取較強一方；雨量沒有把強度推得更高時保留原標籤（含霧雨語感）.
	if ( $mm_rank <= $code_rank ) {
		return $base_text;
	}

	$rank_labels = array(
		2 => '雨',
		3 => '大雨',
		4 => '豪雨',
		5 => '猛烈な雨',
		6 => '記録的豪雨',
	);
	return $rank_labels[ $mm_rank ] ?? $base_text;
}

/**
 * 獲取天氣預報（今天+明天）
 * 
 * 使用 Open-Meteo API（免費、無需 API Key）
 * 使用 mpu_fetch_external_api() 統一管理快取和請求。
 * 
 * @param float $latitude 緯度
 * @param float $longitude 經度
 * @return array|null 天氣資料陣列，失敗時返回 null
 */
function mpu_get_weather_forecast($latitude = 25.0330, $longitude = 121.5654)
{
    // 驗證座標範圍
    $latitude = max(-90, min(90, floatval($latitude)));
    $longitude = max(-180, min(180, floatval($longitude)));

    // 獲取 WordPress 時區設定
    $timezone = wp_timezone_string();
    if (empty($timezone) || strpos($timezone, '/') === false) {
        $timezone = 'Asia/Tokyo'; // 預設使用東京時區
    }

    // 建立快取 key
    $cache_key = 'mpu_weather_' . md5($latitude . '_' . $longitude);

    // Open-Meteo API 端點
    // 添加 precipitation_probability_max（降水機率）和 precipitation_sum（降水量）
    $url = sprintf(
        'https://api.open-meteo.com/v1/forecast?latitude=%s&longitude=%s&daily=weather_code,temperature_2m_max,temperature_2m_min,precipitation_probability_max,precipitation_sum&current_weather=true&timezone=%s&forecast_days=2',
        $latitude,
        $longitude,
        urlencode($timezone)
    );

    // 使用通用 API 請求函數
    $data = mpu_fetch_external_api($cache_key, $url, MPU_CACHE_WEATHER, [
        'log_prefix' => 'MP Ukagaka Weather',
    ]);

    if (empty($data) || !isset($data['current_weather']) || !isset($data['daily'])) {
        // API 請求成功但資料結構不符合預期
        if ($data !== null) {
            mpu_log_error('Weather: Invalid API response structure');
            // 清除可能被快取的無效資料
            mpu_clear_api_cache($cache_key);
        }
        return null;
    }

    // 解析天氣資料
    $weather = [
        'current' => [
            'temperature' => $data['current_weather']['temperature'] ?? null,
            'weather_code' => $data['current_weather']['weathercode'] ?? 0,
            'weather_text' => mpu_weather_code_to_text($data['current_weather']['weathercode'] ?? 0),
        ],
        'today' => [
            'date' => $data['daily']['time'][0] ?? '',
            'weather_code' => $data['daily']['weather_code'][0] ?? 0,
            'weather_text' => mpu_weather_code_to_text($data['daily']['weather_code'][0] ?? 0),
            'temp_max' => $data['daily']['temperature_2m_max'][0] ?? null,
            'temp_min' => $data['daily']['temperature_2m_min'][0] ?? null,
            'precipitation_probability' => $data['daily']['precipitation_probability_max'][0] ?? null,
            'precipitation_sum' => $data['daily']['precipitation_sum'][0] ?? null,
        ],
        'tomorrow' => [
            'date' => $data['daily']['time'][1] ?? '',
            'weather_code' => $data['daily']['weather_code'][1] ?? 0,
            'weather_text' => mpu_weather_code_to_text($data['daily']['weather_code'][1] ?? 0),
            'temp_max' => $data['daily']['temperature_2m_max'][1] ?? null,
            'temp_min' => $data['daily']['temperature_2m_min'][1] ?? null,
            'precipitation_probability' => $data['daily']['precipitation_probability_max'][1] ?? null,
            'precipitation_sum' => $data['daily']['precipitation_sum'][1] ?? null,
        ],
        'fetched_at' => current_time('mysql'),
    ];

	// 依實際降水量校正「明日」預報雨勢標籤，避免 drizzle code 低估實際雨量導致角色誤說「霧雨」.
	// precipitation_sum 是 24h 累積量，僅用於明日 forecast label。
	// 今日刻意不在此校正：current.weather_text 維持即時 weather code 表示「現在」，今日累積
	// 雨量改由 mpu_get_weather_context() 以「（本日Nmm）」文字補充，避免把全日預報量誤讀成當下雨勢.
	$tomorrow_precip_sum = $weather['tomorrow']['precipitation_sum'] ?? null;

	$weather['tomorrow']['weather_text'] = mpu_weather_refine_rain_text(
		$weather['tomorrow']['weather_code'],
		$weather['tomorrow']['weather_text'],
		$tomorrow_precip_sum
	);

    return $weather;
}

/**
 * 獲取天氣上下文字串（供 LLM 使用）
 * 
 * 從後台設定讀取座標，呼叫 API 獲取天氣，
 * 並格式化為易讀的上下文字串。
 * 
 * @return string 天氣上下文字串，未啟用時返回空字串
 */
function mpu_get_weather_context()
{
    // 檢查是否啟用天氣功能
    $mpu_opt = mpu_get_option();

    if (empty($mpu_opt['weather_enabled'])) {
        return '';
    }

    // 獲取座標設定
    $latitude = isset($mpu_opt['weather_latitude']) ? floatval($mpu_opt['weather_latitude']) : 25.0330;
    $longitude = isset($mpu_opt['weather_longitude']) ? floatval($mpu_opt['weather_longitude']) : 121.5654;

    // 獲取天氣資料
    $weather = mpu_get_weather_forecast($latitude, $longitude);

    if ($weather === null) {
        return '';
    }

    // 格式化天氣上下文
    $current_temp = $weather['current']['temperature'] ?? null;
    $current_weather = $weather['current']['weather_text'] ?? '不明';
    $today_precip_prob = $weather['today']['precipitation_probability'] ?? null;
    $tomorrow_weather = $weather['tomorrow']['weather_text'] ?? '不明';
    $tomorrow_temp_max = $weather['tomorrow']['temp_max'] ?? null;
    $tomorrow_temp_min = $weather['tomorrow']['temp_min'] ?? null;
    $tomorrow_precip_prob = $weather['tomorrow']['precipitation_probability'] ?? null;

	// 在天氣標籤後附上當日實際累積雨量(mm)，讓角色掌握真實雨勢、不會低估.
	$today_precip_sum = $weather['today']['precipitation_sum'] ?? null;
	if ( null !== $today_precip_sum && $today_precip_sum >= 1 ) {
		$current_weather .= sprintf( '（本日%dmm）', round( $today_precip_sum ) );
	}

    // 處理 null 值，確保格式化不會失敗
    $current_temp_display = $current_temp !== null ? round($current_temp) : '-';
    $tomorrow_min_display = $tomorrow_temp_min !== null ? round($tomorrow_temp_min) : '-';
    $tomorrow_max_display = $tomorrow_temp_max !== null ? round($tomorrow_temp_max) : '-';

    // 格式化降水機率顯示（只有當機率 > 0 時才顯示）
    $today_precip_text = '';
    if ($today_precip_prob !== null && $today_precip_prob > 0) {
        // 根據天氣代碼判斷是降雨還是降雪
        $today_code = $weather['today']['weather_code'] ?? 0;
        $precip_type = ($today_code >= 71 && $today_code <= 77) || ($today_code >= 85 && $today_code <= 86) ? '降雪' : '降水';
        $today_precip_text = sprintf(' %s%d%%', $precip_type, $today_precip_prob);
    }

    $tomorrow_precip_text = '';
    if ($tomorrow_precip_prob !== null && $tomorrow_precip_prob > 0) {
        // 根據天氣代碼判斷是降雨還是降雪
        $tomorrow_code = $weather['tomorrow']['weather_code'] ?? 0;
        $precip_type = ($tomorrow_code >= 71 && $tomorrow_code <= 77) || ($tomorrow_code >= 85 && $tomorrow_code <= 86) ? '降雪' : '降水';
        $tomorrow_precip_text = sprintf(' %s%d%%', $precip_type, $tomorrow_precip_prob);
    }

    // 建構上下文字串（確保 min~max 順序正確，並包含降水機率）
    if ($tomorrow_min_display !== '-' && $tomorrow_max_display !== '-') {
        $context = sprintf(
            '【天気：%s %s°C%s / 明日：%s %s~%s°C%s】',
            $current_weather,
            $current_temp_display,
            $today_precip_text,
            $tomorrow_weather,
            $tomorrow_min_display,
            $tomorrow_max_display,
            $tomorrow_precip_text
        );
    } else {
        // 如果明日溫度數據不完整，只顯示當前天氣
        $context = sprintf(
            '【天気：%s %s°C%s】',
            $current_weather,
            $current_temp_display,
            $today_precip_text
        );
    }

    return $context;
}

/**
 * 清除天氣快取
 * 
 * @param float|null $latitude 緯度（可選，不指定則清除所有）
 * @param float|null $longitude 經度（可選）
 * @return bool 是否成功清除
 */
function mpu_clear_weather_cache($latitude = null, $longitude = null)
{
    if ($latitude !== null && $longitude !== null) {
        $cache_key = 'mpu_weather_' . md5($latitude . '_' . $longitude);
        return delete_transient($cache_key);
    }

    // 如果沒有指定座標，清除目前設定的快取
    $mpu_opt = mpu_get_option();
    $lat = isset($mpu_opt['weather_latitude']) ? floatval($mpu_opt['weather_latitude']) : 25.0330;
    $lon = isset($mpu_opt['weather_longitude']) ? floatval($mpu_opt['weather_longitude']) : 121.5654;

    $cache_key = 'mpu_weather_' . md5($lat . '_' . $lon);
    return delete_transient($cache_key);
}
