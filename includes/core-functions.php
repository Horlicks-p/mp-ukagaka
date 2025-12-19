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
        "insert_html" => 0,
        "auto_msg" => "",
        "common_msg" => "",
        "no_page" => "",
        "use_external_file" => true,  // 系統已固定使用外部對話文件
        "external_file_format" => "txt",
        "auto_talk" => true,
        "auto_talk_interval" => 8,
        "typewriter_speed" => 40, // 打字速度（毫秒/字元），預設 40ms
        "ukagakas" => [
            "default_1" => [
                "name" => "フリーレン",
                "shell" => plugins_url("images/shell/Frieren/", defined('MPU_MAIN_FILE') ? MPU_MAIN_FILE : dirname(dirname(__FILE__)) . '/mp-ukagaka.php'),
                "msg" => ["フリレーンだ。千年以上生きた魔法使いだ。"],
                "dialog_filename" => "Frieren",
                "show" => true,
            ],
        ],
        "extend" => [
            "js_area" => "",
        ],
        // AI 設定 (Context Awareness)
        "ai_enabled" => false,
        // LLM 設定（新架構）
        "llm_provider" => "gemini", // 統一使用 llm_provider
        "llm_gemini_api_key" => "", // Gemini API Key (加密)
        "llm_gemini_model" => "gemini-2.5-flash", // Gemini 模型
        "llm_openai_api_key" => "", // OpenAI API Key (加密)
        "llm_openai_model" => "gpt-4.1-mini-2025-04-14", // OpenAI 模型
        "llm_claude_api_key" => "", // Claude API Key (加密)
        "llm_claude_model" => "claude-sonnet-4-5-20250929", // Claude 模型
        "llm_replace_dialogue" => false, // 使用 LLM 取代內建對話（支援所有提供商）
        // 向後兼容設定鍵（保留）
        "ai_provider" => "gemini",
        "ai_api_key" => "", // Gemini API Key (向後兼容)
        "gemini_model" => "gemini-2.5-flash", // Gemini 模型 (向後兼容)
        "openai_api_key" => "", // OpenAI API Key (向後兼容)
        "openai_model" => "gpt-4.1-mini-2025-04-14", // OpenAI 模型 (向後兼容)
        "claude_api_key" => "", // Claude API Key (向後兼容)
        "claude_model" => "claude-sonnet-4-5-20250929", // Claude 模型 (向後兼容)
        "ollama_replace_dialogue" => false, // Ollama 取代對話 (向後兼容)
        // 頁面感知設定（保留在 AI 設定頁面）
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
            // 合併設定
            $mpu_opt = array_merge($default_opt, $options);

            // 確保 default_1 的預設值被應用（如果名稱還是舊的「初音」，則更新）
            if (isset($default_opt['ukagakas']['default_1'])) {
                if (!isset($mpu_opt['ukagakas']['default_1'])) {
                    $mpu_opt['ukagakas']['default_1'] = $default_opt['ukagakas']['default_1'];
                } else {
                    // 檢查是否為舊的預設值（名稱包含「初音」或「Miku」），如果是則更新為新的預設值
                    $current_name = $mpu_opt['ukagakas']['default_1']['name'] ?? '';
                    // 檢查多種可能的舊名稱變體
                    $is_old_default = (
                        $current_name === '初音' ||
                        $current_name === '初音ミク' ||
                        stripos($current_name, '初音') !== false ||
                        stripos($current_name, 'miku') !== false ||
                        stripos($current_name, 'ミク') !== false
                    );
                    if ($is_old_default) {
                        // 只更新名稱、shell、msg 和 dialog_filename，保留其他設定（如 show）
                        $mpu_opt['ukagakas']['default_1']['name'] = $default_opt['ukagakas']['default_1']['name'];
                        $mpu_opt['ukagakas']['default_1']['shell'] = $default_opt['ukagakas']['default_1']['shell'];
                        $mpu_opt['ukagakas']['default_1']['msg'] = $default_opt['ukagakas']['default_1']['msg'];
                        $mpu_opt['ukagakas']['default_1']['dialog_filename'] = $default_opt['ukagakas']['default_1']['dialog_filename'];
                        // 儲存更新後的設定
                        update_option("mp_ukagaka", $mpu_opt);
                        // 清除物件快取（如果有的話）
                        wp_cache_delete("mp_ukagaka", "options");
                    }
                }
            }
        }
    }

    return $mpu_opt;
}

/**
 * 啟用時建立目錄
 * 注意：這個 hook 需要在主文件中註冊，因為需要在定義 MPU_MAIN_FILE 之後
 */
