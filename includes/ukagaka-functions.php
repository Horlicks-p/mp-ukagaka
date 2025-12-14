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

/**
 * 取得 shell 資訊（單張圖片或資料夾）
 * @param string|false $num 春菜編號，false 表示使用當前春菜
 * @return array 包含 type, url, images 的陣列
 */
function mpu_get_shell_info($num = false)
{
    $mpu_opt = mpu_get_option();
    $name = $num === false ? $mpu_opt["cur_ukagaka"] ?? "default_1" : $num;
    $shell = $mpu_opt["ukagakas"][$name]["shell"] ?? "";
    
    if (empty($shell)) {
        return [
            'type' => 'single',
            'url' => '',
            'images' => []
        ];
    }
    
    // 使用已定義的常量獲取主文件路徑
    $main_file = defined('MPU_MAIN_FILE') ? MPU_MAIN_FILE : dirname(dirname(__FILE__)) . '/mp-ukagaka.php';
    $plugin_dir = plugin_dir_path($main_file);
    $plugin_url = plugin_dir_url($main_file);
    
    // 將 URL 轉換為本地路徑
    $shell_url = $shell;
    $shell_path = '';
    
    // 檢查是否為插件內的 URL
    if (strpos($shell_url, $plugin_url) === 0) {
        // 提取相對路徑
        $relative_path = str_replace($plugin_url, '', $shell_url);
        $shell_path = $plugin_dir . $relative_path;
    } else {
        // 可能是外部 URL，嘗試解析
        $parsed_url = parse_url($shell_url);
        if (isset($parsed_url['path'])) {
            // 嘗試從 WordPress 上傳目錄解析
            $upload_dir = wp_upload_dir();
            if (strpos($parsed_url['path'], $upload_dir['baseurl']) === 0) {
                $relative_path = str_replace($upload_dir['baseurl'], '', $parsed_url['path']);
                $shell_path = $upload_dir['basedir'] . $relative_path;
            }
        }
    }
    
    // 如果無法解析為本地路徑，視為單張圖片
    if (empty($shell_path) || !file_exists($shell_path)) {
        return [
            'type' => 'single',
            'url' => $shell_url,
            'images' => []
        ];
    }
    
    // 檢查是文件還是資料夾
    if (is_file($shell_path)) {
        // 單張圖片
        return [
            'type' => 'single',
            'url' => $shell_url,
            'images' => []
        ];
    } elseif (is_dir($shell_path)) {
        // 資料夾，掃描圖片文件
        $images = [];
        $allowed_extensions = ['png', 'jpg', 'jpeg', 'gif', 'webp'];
        
        if ($handle = opendir($shell_path)) {
            while (false !== ($entry = readdir($handle))) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }
                
                $file_path = $shell_path . '/' . $entry;
                if (!is_file($file_path)) {
                    continue;
                }
                
                $extension = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
                if (in_array($extension, $allowed_extensions)) {
                    $images[] = $entry;
                }
            }
            closedir($handle);
        }
        
        // 自然排序圖片文件名
        if (!empty($images)) {
            natsort($images);
            $images = array_values($images); // 重新索引陣列
        }
        
        // 取得資料夾的 URL
        $folder_url = '';
        if (strpos($shell_path, $plugin_dir) === 0) {
            $relative_folder = str_replace($plugin_dir, '', $shell_path);
            $folder_url = $plugin_url . $relative_folder . '/';
        } elseif (isset($upload_dir) && strpos($shell_path, $upload_dir['basedir']) === 0) {
            $relative_folder = str_replace($upload_dir['basedir'], '', $shell_path);
            $folder_url = $upload_dir['baseurl'] . $relative_folder . '/';
        }
        
        return [
            'type' => 'folder',
            'url' => $folder_url,
            'images' => $images
        ];
    }
    
    // 默認返回單張圖片
    return [
        'type' => 'single',
        'url' => $shell_url,
        'images' => []
    ];
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
    // 支援兩種格式：:recentpost[5]: 或 (:recentpost[5]:)
    // 格式1：:recentpost[5]: （無括號，可在字串任何位置）
    // 格式2：(:recentpost[5]:) （有括號，可在字串任何位置）
    // 使用 \(?: 匹配可選的開始括號和冒號，然後匹配類型和數字，最後匹配冒號和可選的結束括號
    $pattern =
        "/\(?:(recentpost|recentposts|randompost|randomposts|commenters)\[(\d*)\]:\)?/";

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

            // 處理留言者列表
            if ($type === "commenters") {
                // 獲取最近留言（取更多留言以確保有足夠的不同留言者）
                $comments = get_comments([
                    "status" => "approve",
                    "number" => $n * 3, // 獲取更多留言以確保有足夠的不同留言者
                    "orderby" => "comment_date",
                    "order" => "DESC",
                ]);

                $commenters = [];
                $seen_authors = []; // 用於去重

                foreach ($comments as $comment) {
                    $author_name = $comment->comment_author;
                    $author_url = $comment->comment_author_url;
                    
                    // 跳過匿名留言者（名稱為空）
                    if (empty($author_name)) {
                        continue;
                    }

                    // 使用 email 作為唯一標識（如果有），否則使用名稱
                    $unique_key = !empty($comment->comment_author_email) 
                        ? strtolower($comment->comment_author_email)
                        : strtolower($author_name);

                    // 如果已見過此留言者，跳過
                    if (isset($seen_authors[$unique_key])) {
                        continue;
                    }

                    $seen_authors[$unique_key] = true;

                    // 如果有網站 URL，生成連結，否則只顯示名稱
                    if (!empty($author_url) && filter_var($author_url, FILTER_VALIDATE_URL)) {
                        $commenters[] =
                            '<a href="' .
                            esc_url($author_url) .
                            '" title="' .
                            esc_attr($author_name) .
                            '" rel="nofollow external">' .
                            esc_html($author_name) .
                            "</a>";
                    } else {
                        $commenters[] = esc_html($author_name);
                    }

                    // 達到所需數量就停止
                    if (count($commenters) >= $n) {
                        break;
                    }
                }

                // 生成 HTML 顯示
                if (!empty($commenters)) {
                    $html = implode("、", $commenters); // 使用頓號分隔
                    $current_value = str_replace($match[0], $html, $current_value);
                } else {
                    // 如果沒有留言者，替換為空字串或預設訊息
                    $current_value = str_replace($match[0], "", $current_value);
                }

                continue; // 處理完留言者後繼續下一個匹配
            }

            // 處理文章列表
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

        if ($type === "recentposts" || $type === "randomposts" || $type === "commenters") {
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
