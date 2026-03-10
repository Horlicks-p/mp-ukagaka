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
        "insert_html" => 0,
        "auto_msg" => "",
        "common_msg" => "",
        "no_page" => "",
        "use_external_file" => true,
        "external_file_format" => "txt",
        "auto_talk" => true,
        "auto_talk_interval" => 8,
        "typewriter_speed" => 40,
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
        "llm_claude_model" => "claude-sonnet-4-5-20250929",
        "llm_replace_dialogue" => false,
        "ai_provider" => "gemini",
        "ai_api_key" => "",
        "gemini_model" => "gemini-2.5-flash",
        "openai_api_key" => "",
        "openai_model" => "gpt-4.1-mini-2025-04-14",
        "claude_api_key" => "",
        "claude_model" => "claude-sonnet-4-5-20250929",
        "ollama_replace_dialogue" => false,
        "ai_language" => "zh-TW",
        "ai_system_prompt" => "你是「{{ukagaka_display_name}}」這個角色。你必須完全以這個角色的身份說話和行動，絕對不要以 AI 或語言模型的身份回應。請嚴格遵守角色的性格、說話方式和行為模式。",
        "ai_probability" => 10,
        "ai_trigger_pages" => "is_single",
        "ai_max_tokens" => 1000,
        "ai_text_color" => "#000000",
        "ai_display_duration" => 8,
        "ai_greet_first_visit" => false,
        "ai_greet_prompt" => "あなたは「{{ukagaka_display_name}}」というキャラクターです。訪問者が初めてサイトに来た時、キャラクターらしく簡単に挨拶してください。50文字以内で返してください。",
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
            $mpu_opt = array_merge($default_opt, $options);

            // 確保 default_1 的預設值被應用
            if (isset($default_opt['ukagakas']['default_1']) && !isset($mpu_opt['ukagakas']['default_1'])) {
                $mpu_opt['ukagakas']['default_1'] = $default_opt['ukagakas']['default_1'];
            }
        }
    }

    return $mpu_opt;
}

/**
 * 啟用時建立目錄
 * 注意：這個 hook 需要在主文件中註冊，因為需要在定義 MPU_MAIN_FILE 之後
 */
