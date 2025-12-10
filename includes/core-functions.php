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
 * 預設設定內容
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
        "insert_html" => 0,
        "auto_msg" => "",
        "common_msg" => "",
        "no_page" => "",
        "use_external_file" => true,  // ★★★ 修改：系統已固定使用外部對話文件 ★★★
        "external_file_format" => "txt",
        "auto_talk" => true,
        "auto_talk_interval" => 8,
        "typewriter_speed" => 40, // 打字速度（毫秒/字元），預設 40ms
        "ukagakas" => [
            "default_1" => [
                "name" => "初音",
                "shell" => plugins_url("images/shell/shell_1.png", defined('MPU_MAIN_FILE') ? MPU_MAIN_FILE : dirname(dirname(__FILE__)) . '/mp-ukagaka.php'),
                "msg" => ["歡迎光臨～"],
                "dialog_filename" => "miku",
                "show" => true,
            ],
        ],
        "extend" => [
            "js_area" => "",
        ],
        // AI 設定 (Context Awareness)
        "ai_enabled" => false,
        "ai_provider" => "gemini",
        "ai_api_key" => "", // Gemini API Key
        "openai_api_key" => "", // OpenAI API Key
        "openai_model" => "gpt-4o-mini", // OpenAI 模型
        "claude_api_key" => "", // Claude API Key
        "claude_model" => "claude-sonnet-4-5-20250929", // Claude 模型
        "ai_language" => "zh-TW",
        "ai_system_prompt" => "你是一個傲嬌的桌面助手「春菜」。你會用簡短、帶點傲嬌的語氣評論文章內容。回應請保持在 40 字以內。",
        "ai_probability" => 10,
        "ai_trigger_pages" => "is_single",
        "ai_text_color" => "#000000", // AI 對話文字顏色
        "ai_display_duration" => 8,   // AI 對話顯示時間（秒）
        "ai_greet_first_visit" => false, // 首次訪客打招呼
        "ai_greet_prompt" => "你是一個友善的桌面助手「春菜」。當有訪客第一次來到網站時，你會根據訪客的來源（referrer）用親切的語氣打招呼。回應請保持在 50 字以內。", // 首次訪客打招呼的提示詞
    ];
}

/**
 * 預設設定：統一快取選項。
 */
function mpu_get_option()
{
    static $mpu_opt = null;

    if ($mpu_opt === null) {
        $options = get_option("mp_ukagaka");

        if (!is_array($options) || empty($options)) {
            $mpu_opt = mpu_default_opt();
            update_option("mp_ukagaka", $mpu_opt);
        } else {
            $mpu_opt = array_merge(mpu_default_opt(), $options);
        }
    }

    return $mpu_opt;
}

/**
 * 啟用時建立目錄
 * 注意：這個 hook 需要在主文件中註冊，因為需要在定義 MPU_MAIN_FILE 之後
 */
