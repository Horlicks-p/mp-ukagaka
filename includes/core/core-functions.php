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
                "shell" => plugins_url("ghost/Frieren/shell/", defined('MPU_MAIN_FILE') ? MPU_MAIN_FILE : dirname(dirname(dirname(__FILE__))) . '/mp-ukagaka.php'),
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
        "ai_text_color" => "#000000",
        "ai_display_duration" => 8,
        "ai_greet_first_visit" => false,
        "ai_greet_prompt" => "あなたは「{{ukagaka_display_name}}」というキャラクターです。訪問者が初めてサイトに来た時、キャラクターらしく簡単に挨拶してください。50文字以内で返してください。",
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
                        update_option("mp_ukagaka", $mpu_opt);
                        wp_cache_delete("mp_ukagaka", "options");
                    } else {
                        // 檢查 shell 路徑是否需要更新（即使名稱不是舊的「初音」，也要檢查路徑）
                        $current_shell = $mpu_opt['ukagakas']['default_1']['shell'] ?? '';
                        $new_shell = $default_opt['ukagakas']['default_1']['shell'];

                        // 如果當前路徑不包含新的路徑結構，則更新
                        // 檢查是否包含舊的路徑格式：images/Frieren/ 或 personalities/Frieren/
                        $needs_path_update = (
                            strpos($current_shell, 'ghost/Frieren/shell/') === false &&
                            (
                                strpos($current_shell, 'images/Frieren/') !== false ||
                                strpos($current_shell, 'personalities/Frieren/') !== false ||
                                // 如果 dialog_filename 是 Frieren，但路徑不正確，也更新
                                (isset($mpu_opt['ukagakas']['default_1']['dialog_filename']) &&
                                    $mpu_opt['ukagakas']['default_1']['dialog_filename'] === 'Frieren' &&
                                    $current_shell !== $new_shell)
                            )
                        );

                        if ($needs_path_update) {
                            $mpu_opt['ukagakas']['default_1']['shell'] = $new_shell;
                            update_option("mp_ukagaka", $mpu_opt);
                            wp_cache_delete("mp_ukagaka", "options");
                        }
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
