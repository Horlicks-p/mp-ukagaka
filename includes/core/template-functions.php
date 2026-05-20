<?php

/**
 * Template and string helper functions.
 *
 * @package MP_Ukagaka
 * @subpackage Utility
 */

if (!defined('ABSPATH')) {
    exit();
}

/**
 * 將陣列轉換為字串
 */
function mpu_array2str($arr = [])
{
    if (empty($arr)) {
        return "";
    }

    // 使用 PHP_EOL 代替硬編碼的 \n\n 增強跨平台相容性
    return implode(PHP_EOL . PHP_EOL, $arr);
}

/**
 * 【★ 修正】將字串轉換為陣列 (使用更穩健的 explode)
 * 舊版使用 preg_split 容易在長字串時失敗
 */
function mpu_str2array($str = "")
{
    $arr = [];
    if (is_string($str) && !empty($str)) {
        $normalized_str = str_replace(["\r\n", "\r"], "\n", $str);
        $lines = explode("\n", $normalized_str);

        foreach ($lines as $value) {
            $trimmed_value = trim($value);
            if ($trimmed_value !== "") {
                $arr[] = $trimmed_value;
            }
        }
    }
    return $arr;
}

/**
 * 輸出過濾器：HTML 輸出
 */
function mpu_output_filter($str)
{
    return esc_html($str);
}

/**
 * 輸出過濾器：JavaScript 輸出
 */
function mpu_js_filter($str)
{
    return esc_js($str);
}

/**
 * 渲染提示詞模板，替換 {{變數名}} 為實際值
 * 
 * @param string $template 模板字串，包含 {{變數名}} 佔位符
 * @param array $variables 變數陣列，鍵為變數名（不含 {{}}），值為要替換的內容
 * @return string 替換後的字串
 */
function mpu_render_prompt_template($template, $variables = [])
{
    if (empty($template) || !is_string($template)) {
        return $template;
    }

    if (empty($variables) || !is_array($variables)) {
        return $template;
    }

    // 使用 preg_replace_callback 進行安全替換
    // 支援變數名包含字母、數字、下劃線和連字符（如 {{ukagaka-name}}）
    $result = preg_replace_callback(
        '/\{\{([\w\-]+)\}\}/',
        function ($matches) use ($variables) {
            $var_name = $matches[1];

            // 只替換存在的變數
            if (isset($variables[$var_name])) {
                $value = $variables[$var_name];

                // 只處理純量值（字串、數字、布林值）
                if (is_scalar($value)) {
                    // 轉換為字串
                    return (string) $value;
                }
            }

            // 未定義的變數移除，避免 template 語法直接送進 LLM
            return '';
        },
        $template
    );

    return $result;
}

/**
 * 建構用戶資訊的 User Prompt 區塊
 * 格式化登入狀態、角色、管理員身份為 LLM 可讀的文字
 *
 * @param array $user_info mpu_get_current_user_info() 的返回值
 * @return string 格式化的用戶資訊提示詞（含尾部換行）
 */
function mpu_build_user_info_prompt(array $user_info): string
{
    $role_labels = [
        'administrator' => '管理人',
        'editor'        => '編集者',
        'author'        => '作者',
        'contributor'   => '貢献者',
        'subscriber'    => '購読者',
    ];

    $parts = [];
    $parts[] = "【現在のユーザー情報】";

    if ($user_info['is_logged_in']) {
        $role_label = $role_labels[$user_info['primary_role']] ?? $user_info['primary_role'];
        $user_lines = [];
        $user_lines[] = "ユーザーがログインしています：{$user_info['display_name']} ({$user_info['username']})";
        $user_lines[] = "役割：{$role_label}";
        if ($user_info['is_admin']) {
            $user_lines[] = "このユーザーはサイト管理人です。";
        }
        $parts[] = implode("\n", $user_lines);
    } else {
        $parts[] = "ユーザーがログインしていません（訪問者）。";
    }

    return implode("\n", $parts);
}
