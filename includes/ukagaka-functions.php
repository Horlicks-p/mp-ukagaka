<?php

/**
 * 春菜管理功能
 * 
 * @package MP_Ukagaka
 * @subpackage Ukagaka
 */

if (!defined('ABSPATH')) {
    exit();
}

/**
 * 取得春菜列表
 */
function mpu_ukagaka_list()
{
    $mpu_opt = mpu_get_option();
    $html = "";

    if (!empty($mpu_opt["ukagakas"])) {
        $html .= '<div class="ukagaka-list">';
        $html .= __("春菜们", "mp-ukagaka") . "：<br/>";

        foreach ($mpu_opt["ukagakas"] as $key => $value) {
            if (!empty($value["show"])) {
                // 使用 <div>/<span> 避免在 msgbox 內使用無序列表造成樣式問題
                $html .= '<div style="padding-left:10px; padding:3px 0;">';
                $html .=
                    '<a onclick="mpuChange(\'' .
                    esc_attr($key) .
                    '\')" href="javascript:void(0);" style="cursor:pointer;">';
                $html .= mpu_output_filter($value["name"]);
                $html .= "</a></div>";
            }
        }

        $html .= "</div>";
    } else {
        $html = __("沒有可供選擇的春菜", "mp-ukagaka");
    }

    return $html;
}

function mpu_get_ukagaka($num = false)
{
    $mpu_opt = mpu_get_option();
    $name = $num === false ? $mpu_opt["cur_ukagaka"] ?? "default_1" : $num;
    if (empty($mpu_opt["ukagakas"][$name])) {
        // 返回當前設置的春菜或預設春菜
        return $mpu_opt["ukagakas"][$mpu_opt["cur_ukagaka"]] ??
            ($mpu_opt["ukagakas"]["default_1"] ?? []);
    }
    return $mpu_opt["ukagakas"][$name];
}

function mpu_get_shell($num = false, $echo = false)
{
    $mpu_opt = mpu_get_option();
    $name = $num === false ? $mpu_opt["cur_ukagaka"] ?? "default_1" : $num;
    $shell = $mpu_opt["ukagakas"][$name]["shell"] ?? "";
    if ($echo) {
        echo esc_url($shell);
    } else {
        return $shell;
    }
}

function mpu_get_msg($msgnum = 0, $num = false, $echo = false)
{
    $mpu_opt = mpu_get_option();
    $name = $num === false ? $mpu_opt["cur_ukagaka"] ?? "default_1" : $num;
    $msg = isset($mpu_opt["ukagakas"][$name]["msg"][$msgnum])
        ? $mpu_opt["ukagakas"][$name]["msg"][$msgnum]
        : "";
    if ($echo) {
        echo $msg;
    } else {
        return $msg;
    }
}

function mpu_get_random_msg($num = false, $echo = false)
{
    $mpu_opt = mpu_get_option();
    $name = $num === false ? $mpu_opt["cur_ukagaka"] ?? "default_1" : $num;
    $msgs = $mpu_opt["ukagakas"][$name]["msg"] ?? [];
    $total = count($msgs);
    $msg = $total > 0 ? $msgs[mt_rand(0, $total - 1)] : "";
    if ($echo) {
        echo $msg;
    } else {
        return $msg;
    }
}

function mpu_get_default_msg($num = false, $echo = false)
{
    $mpu_opt = mpu_get_option();
    $msg =
        intval($mpu_opt["default_msg"] ?? 0) == 0
        ? mpu_get_random_msg($num, false)
        : mpu_get_msg(0, $num, false);
    if ($echo) {
        echo $msg;
    } else {
        return $msg;
    }
}

function mpu_common_msg()
{
    global $mpu_opt;
    $mpu_opt = mpu_get_option();
    if (!empty($mpu_opt["common_msg"])) {
        $common_arr = mpu_str2array($mpu_opt["common_msg"]);
        foreach ($mpu_opt["ukagakas"] as $key => $value) {
            $mpu_opt["ukagakas"][$key]["msg"] = $common_arr;
        }
    }
}

function mpu_get_msg_arr($num = false)
{
    static $depth = 0;

    // 防止遞迴超過 3 層
    if ($depth > 3) {
        error_log("MPU: mpu_get_msg_arr 遞迴深度超過限制!");
        return [
            "msgall" => 0,
            "auto_msg" => "",
            "msg" => ["載入失敗：遞迴限制"],
        ];
    }

    $depth++;

    try {
        $mpu_opt = mpu_get_option();
        $name = $num === false ? $mpu_opt["cur_ukagaka"] ?? "default_1" : $num;

        if (empty($mpu_opt["ukagakas"][$name])) {
            $name = "default_1";
        }

        if (empty($mpu_opt["ukagakas"][$name])) {
            throw new Exception("找不到春菜: " . $name);
        }

        $ukagaka = $mpu_opt["ukagakas"][$name];

        // ★★★ 修改：一律從外部檔案讀取對話，不再使用內部對話 ★★★
        if (isset($ukagaka["dialog_filename"])) {
            $ukagaka["msg"] = mpu_get_msg_from_file(
                $ukagaka["dialog_filename"]
            );
        } else {
            // 如果沒有設定對話檔案名稱，使用春菜名稱作為檔案名
            $ukagaka["msg"] = mpu_get_msg_from_file($name);
        }

        if (empty($ukagaka["msg"]) || !is_array($ukagaka["msg"])) {
            $ukagaka["msg"] = [__("找不到對話文件", "mp-ukagaka")];
        }

        $msgall = max(0, count($ukagaka["msg"]) - 1);

        $arr = [
            "msgall" => $msgall,
            "auto_msg" => $mpu_opt["auto_msg"] ?? "",
            "msg" => $ukagaka["msg"],
        ];

        // 處理訊息代碼
        $arr["msg"] = mpu_msg_code($arr["msg"]);

        // 確保 auto_msg 處理
        $auto_msg_array = mpu_msg_code([$arr["auto_msg"]]);
        $arr["auto_msg"] = implode(" ", $auto_msg_array);

        $depth--;
        return $arr;
    } catch (Exception $e) {
        error_log("MPU mpu_get_msg_arr 錯誤: " . $e->getMessage());
        $depth--;
        return [
            "msgall" => 0,
            "auto_msg" => "",
            "msg" => ["載入錯誤: " . $e->getMessage()],
        ];
    }
}

function mpu_get_next_msg($num = false, $msgnum = 0)
{
    $mpu_opt = mpu_get_option();
    $name = $num === false ? $mpu_opt["cur_ukagaka"] ?? "default_1" : $num;
    $msgs = $mpu_opt["ukagakas"][$name]["msg"] ?? [];

    if (($mpu_opt["next_msg"] ?? 0) == 0) {
        $next = $msgnum + 1;
        if (isset($msgs[$next])) {
            $msg = $msgs[$next];
        } else {
            $msg = $msgs[0] ?? "";
        }
    } else {
        $msg = mpu_get_random_msg($num, false);
    }
    return $msg;
}

function mpu_msg_code($msglist = [])
{
    static $depth = 0;

    // 防止無限遞迴
    if ($depth > 5) {
        error_log("MPU: mpu_msg_code 遞迴深度超過限制!");
        return $msglist;
    }

    $depth++;

    if (!is_array($msglist)) {
        $depth--;
        return [];
    }

    $templist = [];

    // 預先編譯正則表達式，略微提升效能
    $pattern =
        "/\(:(recentpost|recentposts|randompost|randomposts)\[(\d*)\]\)/";

    foreach ($msglist as $value) {
        if (!is_string($value)) {
            continue;
        }

        if (!preg_match($pattern, $value)) {
            $templist[] = $value;
            continue;
        }

        $matches = [];
        preg_match_all($pattern, $value, $matches, PREG_SET_ORDER);

        $current_value = $value;

        foreach ($matches as $match) {
            $type = $match[1];
            $n = intval($match[2]);

            if ($n <= 0) {
                $n = 5;
            }

            $orderby = strpos($type, "random") !== false ? "rand" : "date";
            $posts = get_posts([
                "numberposts" => $n,
                "orderby" => $orderby,
                "post_status" => "publish",
                "suppress_filters" => true,
            ]);

            $links = [];

            foreach ($posts as $post) {
                $title = get_the_title($post->ID);
                $permalink = get_permalink($post->ID);
                $links[] =
                    '<a href="' .
                    esc_url($permalink) .
                    '" title="' .
                    esc_attr($title) .
                    '">' .
                    esc_html($title) .
                    "</a>";
            }

            if ($type === "recentpost" || $type === "randompost") {
                foreach ($links as $link) {
                    $templist[] = str_replace($match[0], $link, $value);
                }
            } else {
                $html = implode("<br/>", $links);
                $current_value = str_replace($match[0], $html, $current_value);
            }
        }

        if ($type === "recentposts" || $type === "randomposts") {
            $templist[] = $current_value;
        }
    }

    $depth--;
    return array_unique($templist);
}

function mpu_get_msg_key($num = false, $msg = "")
{
    $mpu_opt = mpu_get_option();
    $name = $num === false ? $mpu_opt["cur_ukagaka"] ?? "default_1" : $num;
    $msgnum = array_search(
        $msg,
        $mpu_opt["ukagakas"][$name]["msg"] ?? [],
        true
    );
    if ($msgnum === false) {
        $msgnum = 0;
    }
    return $msgnum;
}

function mpu_count_msg($num = false)
{
    $mpu_opt = mpu_get_option();
    $name = $num === false ? $mpu_opt["cur_ukagaka"] ?? "default_1" : $num;
    return max(0, count($mpu_opt["ukagakas"][$name]["msg"] ?? []) - 1);
}

function mpu_count_total_msg()
{
    $mpu_opt = mpu_get_option();
    $n = 0;
    foreach ($mpu_opt["ukagakas"] as $value) {
        $n += count($value["msg"] ?? []);
    }
    return $n;
}

/**
 * 從文件讀取訊息
 * 【安全性強化】使用 mpu_secure_file_read 替代 file_get_contents
 */
function mpu_get_msg_from_file($filename_base)
{
    $mpu_opt = mpu_get_option();
    $ext = $mpu_opt["external_file_format"] ?? "txt";

    // 驗證文件名（只允許字母、數字、下劃線、連字符）
    if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $filename_base)) {
        error_log('MP Ukagaka: 不合法的對話文件名: ' . $filename_base);
        return [__("不合法的對話文件名", "mp-ukagaka")];
    }

    $file_path = mpu_get_dialogs_dir() . "/" . $filename_base . "." . $ext;

    // 【安全性強化】使用安全文件讀取函數
    $content = mpu_secure_file_read($file_path);

    if (is_wp_error($content)) {
        $error_code = $content->get_error_code();
        if ($error_code === 'file_not_found') {
            return [__("找不到對話文件", "mp-ukagaka")];
        } elseif ($error_code === 'file_too_large') {
            return [__("對話文件過大，載入失敗", "mp-ukagaka")];
        } else {
            return [__("無法讀取對話文件", "mp-ukagaka")];
        }
    }

    if ($ext === "json") {
        $json = json_decode($content, true);
        if (
            json_last_error() === JSON_ERROR_NONE &&
            !empty($json["messages"])
        ) {
            return $json["messages"];
        }
        return [__("JSON 檔案格式錯誤", "mp-ukagaka")];
    }

    return mpu_str2array($content);
}
