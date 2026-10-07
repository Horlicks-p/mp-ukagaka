<?php

/**
 * 核心功能：設定管理
 * 
 * @package MP_Ukagaka
 * @subpackage Core
 */

if (!defined('ABSPATH')) {
    exit();
}

// phpcs:disable WordPress.Arrays, Generic.WhiteSpace.ScopeIndent -- Preserve the legacy defaults layout while pruning deprecated keys.
/**
 * 取得預設設定內容
 * @return {array} 預設設定陣列
 */
function mpu_default_opt()
{
    return [
        "cur_ukagaka" => "default_1",
        "show_ukagaka" => true,
        "show_msg" => true,
        "default_msg" => 0,
        "next_msg" => 0,
        "click_ukagaka" => 0,
        "no_style" => false,
        "custom_style_link" => "",
		'dialog_theme' => 'default',
        "insert_html" => 0,
        "auto_msg" => "",
        "common_msg" => "",
        "no_page" => "",
        "use_external_file" => true,
        "external_file_format" => "txt",
        "auto_talk" => true,
        "auto_talk_interval" => 8,
        "typewriter_speed" => 40,
        "admin_nickname" => "",
        "admin_name" => "",
        "admin_birthday" => "",
        "ukagakas" => [
            "default_1" => [
                "name" => "フリーレン",
                "shell" => plugins_url("ghost/Frieren/shell/Frieren/", defined('MPU_MAIN_FILE') ? MPU_MAIN_FILE : dirname(dirname(dirname(__FILE__))) . '/mp-ukagaka.php'),
                "msg" => ["フリレーンだ。千年以上生きた魔法使いだ。"],
                "dialog_filename" => "Frieren",
                "show" => true,
                "show_decorations" => true,
            ],
        ],
        "extend" => [
            "js_area" => "",
        ],
        "ai_enabled" => false,
        "llm_provider" => "gemini",
        "llm_gemini_api_key" => "",
        "llm_gemini_model" => "gemini-2.5-flash",
        "llm_openai_api_key" => "",
        "llm_openai_model" => "gpt-4.1-mini-2025-04-14",
        "llm_claude_api_key" => "",
        "llm_claude_model" => "claude-sonnet-4-6",
        "llm_replace_dialogue" => false,
        "ai_language" => "",
        "ai_system_prompt" => "你是「{{ukagaka_display_name}}」這個角色。你必須完全以這個角色的身份說話和行動，絕對不要以 AI 或語言模型的身份回應。請嚴格遵守角色的性格、說話方式和行為模式。",
        "ai_probability" => 10,
        "ai_trigger_pages" => "is_single",
        "ai_max_tokens" => 1000,
        "ai_text_color" => "#000000",
        "ai_display_duration" => 8,
        "ai_greet_first_visit" => false,
        "ai_greet_prompt" => "あなたは「{{ukagaka_display_name}}」というキャラクターです。訪問者が初めてサイトに来た時、キャラクターらしく簡単に挨拶してください。50文字以内で返してください。",
        "chat_integrity_mode" => "audit",
        // Bot 防護設定
        "bot_blocker" => [
            "enabled"                => false,
            "banned_fingerprints"    => ["f70fd15177b6515b0abf82a68122b25f"],
            "suspicious_resolutions" => ["1280x1200"],
            "max_log_rows"           => 1000,
            "auto_ban_ip"            => true,
            "block_status"           => 403,
            "hot_transient_ttl"      => 600,
            "rate_limit_threshold"   => 40,
        ],
    ];
}
// phpcs:enable WordPress.Arrays, Generic.WhiteSpace.ScopeIndent

/**
 * Merge defaults for known nested option groups without deep-merging arbitrary data.
 *
 * @param array $options Saved option data.
 * @param array $default_opt Default option data.
 * @return array Merged option data.
 */
function mpu_merge_option_defaults( array $options, array $default_opt ): array {
	$merged = array_merge( $default_opt, $options );

	foreach (array( 'extend', 'bot_blocker' ) as $key) {
		if ( ! isset( $default_opt[ $key ] ) || ! is_array( $default_opt[ $key ] ) ) {
			continue;
		}

		$saved_group = isset( $options[ $key ] ) && is_array( $options[ $key ] )
			? $options[ $key ]
			: array();

		$merged[ $key ] = array_merge( $default_opt[ $key ], $saved_group );
	}

	if ( isset( $default_opt['ukagakas']['default_1'] ) && is_array( $default_opt['ukagakas']['default_1'] ) ) {
		if ( ! isset( $merged['ukagakas'] ) || ! is_array( $merged['ukagakas'] ) ) {
			$merged['ukagakas'] = array();
		}

		$saved_default = isset( $merged['ukagakas']['default_1'] ) && is_array( $merged['ukagakas']['default_1'] )
			? $merged['ukagakas']['default_1']
			: array();

		$merged['ukagakas']['default_1'] = array_merge( $default_opt['ukagakas']['default_1'], $saved_default );
	}

	return $merged;
}

/**
 * 取得選項（統一快取）
 * 使用靜態變數快取，避免重複讀取資料庫
 * @return {array} 選項陣列
 */
function mpu_get_option()
{
    static $mpu_opt = null;

    if ($mpu_opt === null) {
        $options = get_option("mp_ukagaka");
        $default_opt = mpu_default_opt();

        if (!is_array($options) || empty($options)) {
            $mpu_opt = $default_opt;
            update_option("mp_ukagaka", $mpu_opt);
        } else {
            $mpu_opt = mpu_merge_option_defaults($options, $default_opt);
        }
    }

    return $mpu_opt;
}

/**
 * Dialogue colour themes, in the order the settings page lists them.
 *
 * The key is the value of `data-mpu-dialog-theme` and the folder name under
 * images/dialog-themes/ ("default" keeps the original images/msgbox-*.svg).
 * The swatches (frame, brass, paper, link) are for the settings page only;
 * keep them in step with the theme blocks in css/mpu_style.css.
 *
 * @return array<string, array{label: string, swatches: string[]}>
 */
function mpu_get_dialog_themes(): array {
	return array(
		'default'  => array(
			'label'    => __( '旅之魔導書（預設）', 'mp-ukagaka' ),
			'swatches' => array( '#3d2d20', '#c69d63', '#ecdabe', '#224961' ),
		),
		'sapphire' => array(
			'label'    => __( '蒼藍魔導書', 'mp-ukagaka' ),
			'swatches' => array( '#263643', '#8faebe', '#dfe6e4', '#254e68' ),
		),
		'crimson'  => array(
			'label'    => __( '深紅魔導書', 'mp-ukagaka' ),
			'swatches' => array( '#472b29', '#b37868', '#ead9cf', '#64393f' ),
		),
		'forest'   => array(
			'label'    => __( '翠綠魔導書', 'mp-ukagaka' ),
			'swatches' => array( '#29372f', '#91a478', '#e2e2ce', '#2e4e47' ),
		),
	);
}

/**
 * Dialogue colour theme slugs.
 *
 * @return string[]
 */
function mpu_get_dialog_theme_slugs(): array {
	return array_keys( mpu_get_dialog_themes() );
}

/**
 * Reduce any value to a known dialogue theme slug, falling back to "default".
 *
 * @param mixed $value Raw value (option or posted form field).
 * @return string
 */
function mpu_sanitize_dialog_theme( $value ): string {
	$value = is_string( $value ) ? strtolower( trim( $value ) ) : '';

	return in_array( $value, mpu_get_dialog_theme_slugs(), true ) ? $value : 'default';
}

/**
 * The dialogue theme to render. A per-character override, if one is ever
 * added, belongs in a second parameter so $options keeps its meaning.
 *
 * @param array|null $options Plugin options; read from the database when null.
 * @return string
 */
function mpu_get_dialog_theme( ?array $options = null ): string {
	if ( null === $options ) {
		$options = mpu_get_option();
	}

	return mpu_sanitize_dialog_theme( $options['dialog_theme'] ?? 'default' );
}

/**
 * Normalize an MM-DD style birthday value for calendar lookups.
 *
 * @param string $birthday Birthday value from admin settings.
 * @return string Normalized m-d value, or empty string when invalid.
 */
function mpu_normalize_admin_birthday($birthday)
{
    $birthday = trim((string) $birthday);
    if ($birthday === '') {
        return '';
    }

    if (!preg_match('/^(\d{1,2})-(\d{1,2})$/', $birthday, $matches)) {
        return '';
    }

    $month = (int) $matches[1];
    $day   = (int) $matches[2];

    if (!checkdate($month, $day, 2000)) {
        return '';
    }

    return $month . '-' . $day;
}

/**
 * Get admin profile settings used by personality prompts.
 *
 * @param array|null $mpu_opt Plugin options.
 * @return array
 */
function mpu_get_admin_profile_settings($mpu_opt = null)
{
    if ($mpu_opt === null) {
        $mpu_opt = mpu_get_option();
    }

    return [
        'admin_nickname' => isset($mpu_opt['admin_nickname']) ? sanitize_text_field($mpu_opt['admin_nickname']) : '',
        'admin_name'     => isset($mpu_opt['admin_name']) ? sanitize_text_field($mpu_opt['admin_name']) : '',
        'admin_birthday' => isset($mpu_opt['admin_birthday']) ? mpu_normalize_admin_birthday($mpu_opt['admin_birthday']) : '',
    ];
}

/**
 * Get template variables for admin profile placeholders.
 *
 * @param array|null $mpu_opt Plugin options.
 * @return array
 */
function mpu_get_admin_profile_prompt_variables($mpu_opt = null)
{
    $profile = mpu_get_admin_profile_settings($mpu_opt);

    return [
        'admin_nickname' => $profile['admin_nickname'],
        'admin_name'     => $profile['admin_name'],
        'admin_birthday' => $profile['admin_birthday'],
    ];
}

/**
 * Build a short system prompt block from admin profile settings.
 *
 * @param array|null $mpu_opt Plugin options.
 * @return string
 */
function mpu_get_admin_profile_prompt_block($mpu_opt = null)
{
    $profile = mpu_get_admin_profile_settings($mpu_opt);
    $lines = [];

    if ($profile['admin_nickname'] !== '') {
        $lines[] = '- admin_nickname: ' . $profile['admin_nickname'];
    }
    if ($profile['admin_name'] !== '') {
        $lines[] = '- admin_name: ' . $profile['admin_name'];
    }
    if ($profile['admin_birthday'] !== '') {
        $lines[] = '- admin_birthday: ' . $profile['admin_birthday'];
    }

    if (empty($lines)) {
        return '';
    }

    return "Admin profile overrides from WordPress settings:\n" . implode("\n", $lines) . "\nUse these values as authoritative if personality files contain older admin profile values.";
}

/**
 * Apply admin birthday settings to a personality calendar.
 *
 * @param array $calendar Personality calendar config.
 * @param array|null $mpu_opt Plugin options.
 * @return array
 */
function mpu_apply_admin_profile_calendar($calendar, $mpu_opt = null)
{
    if (!is_array($calendar)) {
        return $calendar;
    }

    $profile = mpu_get_admin_profile_settings($mpu_opt);
    if ($profile['admin_birthday'] === '') {
        return $calendar;
    }

    if (empty($calendar['anniversaries']) || !is_array($calendar['anniversaries'])) {
        $calendar['anniversaries'] = [];
    }

    foreach ($calendar['anniversaries'] as $key => $event) {
        if (is_array($event) && !empty($event['prompts']) && in_array('anniversary_admin_birthday', (array) $event['prompts'], true)) {
            unset($calendar['anniversaries'][$key]);
        }
    }

    $calendar['anniversaries'][$profile['admin_birthday']] = [
        'name'        => '管理人の誕生日',
        'prompts'     => ['anniversary_admin_birthday'],
        'description' => 'Configured from WordPress admin profile settings.',
    ];

    return $calendar;
}
