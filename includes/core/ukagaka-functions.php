<?php

/**
 * 偽春菜管理功能
 * 
 * @package MP_Ukagaka
 * @subpackage Ukagaka
 */

if (!defined('ABSPATH')) {
    exit();
}

function mpu_get_ukagaka($num = false)
{
    $mpu_opt = mpu_get_option();
    $name = $num === false ? $mpu_opt["cur_ukagaka"] ?? "default_1" : $num;
    if (empty($mpu_opt["ukagakas"][$name])) {
        // 返回當前設置的偽春菜或預設偽春菜
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
 * @param string|false $num 偽春菜編號，false 表示使用當前偽春菜
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
    $main_file = defined('MPU_MAIN_FILE') ? MPU_MAIN_FILE : dirname(dirname(dirname(__FILE__))) . '/mp-ukagaka.php';
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
        $images = mpu_list_shell_images($shell_path);

        // 取得資料夾的 URL
        $folder_url = '';
        if (strpos($shell_path, $plugin_dir) === 0) {
            $relative_folder = str_replace($plugin_dir, '', $shell_path);
            $folder_url = rtrim($plugin_url . $relative_folder, '/') . '/';
        } elseif (isset($upload_dir) && strpos($shell_path, $upload_dir['basedir']) === 0) {
            $relative_folder = str_replace($upload_dir['basedir'], '', $shell_path);
            $folder_url = rtrim($upload_dir['baseurl'] . $relative_folder, '/') . '/';
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

/**
 * 列出 shell 資料夾內的圖片（不含子資料夾），依檔名自然排序。
 * 點陣圖各自算一張（同 basename 不同格式也是，與 SVG 化之前相同）；
 * 同 basename 有 SVG 時，SVG 取代該 basename 的所有點陣圖。
 *
 * @param string $dir shell 資料夾的本機路徑.
 * @return string[] 檔名陣列.
 */
function mpu_list_shell_images( $dir ) {
	$raster_extensions = array( 'png', 'jpg', 'jpeg', 'gif', 'webp' );
	$svg_by_base       = array();
	$rasters           = array();

	$entries = is_dir( $dir ) ? scandir( $dir ) : false;
	if ( false === $entries ) {
		return array();
	}
	foreach ( $entries as $entry ) {
		if ( '.' === $entry || '..' === $entry || ! is_file( $dir . '/' . $entry ) ) {
			continue;
		}
		$extension = strtolower( pathinfo( $entry, PATHINFO_EXTENSION ) );
		if ( 'svg' === $extension ) {
			$svg_by_base[ pathinfo( $entry, PATHINFO_FILENAME ) ] = $entry;
		} elseif ( in_array( $extension, $raster_extensions, true ) ) {
			$rasters[] = $entry;
		}
	}

	$images = array_values( $svg_by_base );
	foreach ( $rasters as $entry ) {
		if ( ! isset( $svg_by_base[ pathinfo( $entry, PATHINFO_FILENAME ) ] ) ) {
			$images[] = $entry;
		}
	}
	natsort( $images );
	return array_values( $images );
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

/**
 * Return the request-cached union of resolved emotion tags from installed personalities.
 *
 * @return string[]
 */
function mpu_get_builtin_dialog_emotion_tags() {
	static $tags = null;

	if ( null !== $tags ) {
		return $tags;
	}

	$tags = array();
	if ( ! function_exists( 'mpu_get_available_personalities' ) || ! function_exists( 'mpu_normalize_get_supported_tags' ) ) {
		return $tags;
	}

	$seen = array();
	foreach ( array_keys( mpu_get_available_personalities( false ) ) as $personality_id ) {
		foreach ( mpu_normalize_get_supported_tags( $personality_id ) as $tag ) {
			if ( ! is_string( $tag ) || '' === $tag ) {
				continue;
			}

			$key = strtolower( $tag );
			if ( isset( $seen[ $key ] ) ) {
				continue;
			}

			$seen[ $key ] = true;
			$tags[]       = $tag;
		}
	}

	return $tags;
}

/**
 * Expand built-in dialogue while preserving personality-scoped emoji metadata.
 *
 * @param array       $source_messages Unexpanded dialogue strings.
 * @param string|null $personality_id  Resolved active personality, or null for no emoji.
 * @param array|null  $installed_tags  Optional installed-tag registry for tests/callers.
 * @return array{msg:string[],msg_emojis:array}
 */
function mpu_build_builtin_dialog_messages( $source_messages, $personality_id, $installed_tags = null ) {
	$messages = array();
	$emojis   = array();
	$seen     = array();

	if ( ! is_array( $source_messages ) ) {
		return array(
			'msg'        => $messages,
			'msg_emojis' => $emojis,
		);
	}

	if ( ! is_array( $installed_tags ) ) {
		$installed_tags = mpu_get_builtin_dialog_emotion_tags();
	}

	$active_tags = is_string( $personality_id ) && '' !== $personality_id
		? mpu_normalize_get_supported_tags( $personality_id )
		: array();

	foreach ( $source_messages as $source_message ) {
		if ( ! is_string( $source_message ) ) {
			continue;
		}

		$emotion = mpu_normalize_extract_emotions( $source_message, $installed_tags, false );
		$clean   = $emotion['text'];
		if ( ! empty( $emotion['tags'] ) ) {
			preg_match( '/^\s*/u', $source_message, $leading_whitespace );
			preg_match( '/\s*$/u', $source_message, $trailing_whitespace );
			$clean = ( $leading_whitespace[0] ?? '' )
				. trim( $clean )
				. ( $trailing_whitespace[0] ?? '' );
		}
		$emoji = null;

		foreach ( $emotion['tags'] as $tag ) {
			$canonical = mpu_normalize_resolve_emotion_tag( $tag, $active_tags );
			if ( null !== $canonical ) {
				$emoji = $canonical . '.png';
				break;
			}
		}

		foreach ( mpu_msg_code( array( $clean ) ) as $expanded_message ) {
			if ( ! is_string( $expanded_message ) || array_key_exists( $expanded_message, $seen ) ) {
				continue;
			}

			$seen[ $expanded_message ] = true;
			$messages[]                = $expanded_message;
			$emojis[]                  = $emoji;
		}
	}

	return array(
		'msg'        => array_values( $messages ),
		'msg_emojis' => array_values( $emojis ),
	);
}

/**
 * Build the REST-normalized shape for an already parsed built-in line.
 *
 * @param string      $message Visible dialogue text.
 * @param string|null $emoji  Selected PNG filename.
 * @return array
 */
function mpu_normalize_builtin_dialog_for_rest( $message, $emoji = null ) {
	$message = (string) $message;
	$emoji   = is_string( $emoji ) && '' !== $emoji ? $emoji : null;
	$tag     = null !== $emoji ? pathinfo( $emoji, PATHINFO_FILENAME ) : null;

	return array(
		'display_text'         => $message,
		'tts_text'             => $message,
		'history_text'         => $message,
		'checksum_text'        => $message,
		'think'                => '',
		'emotion_tags'         => null !== $tag ? array( $tag ) : array(),
		'emotion_files'        => null !== $emoji ? array( $emoji ) : array(),
		'primary_emotion_tag'  => $tag,
		'primary_emotion_file' => $emoji,
		'emoji'                => $emoji,
	);
}

// phpcs:disable Generic.Formatting.MultipleStatementAlignment, WordPress.Arrays.MultipleStatementAlignment, Squiz.Strings.DoubleQuoteUsage, Universal.Arrays.DisallowShortArraySyntax, NormalizedArrays.Arrays.ArrayBraceSpacing -- Legacy function style.
function mpu_get_msg_arr($num = false)
{
    static $depth = 0;

    // 防止遞迴超過 3 層
    if ($depth > 3) {
        mpu_log_error("mpu_get_msg_arr 遞迴深度超過限制!");
        return [
            "msgall" => 0,
            "auto_msg" => "",
            "msg" => [__("読み込み失敗：再帰制限", "mp-ukagaka")],
            "msg_emojis" => [null],
        ];
    }

    $depth++;

    try {
        $mpu_opt = mpu_get_option();
        $name = $num === false ? $mpu_opt["cur_ukagaka"] ?? "default_1" : $num;
        $requested_name = $num === false ? null : $num;

        if (empty($mpu_opt["ukagakas"][$name])) {
            $name = "default_1";
        }

        if (empty($mpu_opt["ukagakas"][$name])) {
            throw new Exception("找不到偽春菜: " . $name);
        }

        $ukagaka = $mpu_opt["ukagakas"][$name];

        // ★★★ 修改：一律從外部檔案讀取對話，不再使用內部對話 ★★★
        $is_file_error = false;
        if (isset($ukagaka["dialog_filename"])) {
            $ukagaka["msg"] = mpu_get_msg_from_file(
                $ukagaka["dialog_filename"],
                $is_file_error
            );
        } else {
            // 如果沒有設定對話檔案名稱，使用偽春菜名稱作為檔案名
            $ukagaka["msg"] = mpu_get_msg_from_file($name, $is_file_error);
        }

        if (empty($ukagaka["msg"]) || !is_array($ukagaka["msg"])) {
            $is_file_error = true;
            $ukagaka["msg"] = [__("ダイアログファイルが見つかりません", "mp-ukagaka")];
        }

        $msgall = max(0, count($ukagaka["msg"]) - 1);

        if ($is_file_error) {
            $built = [
                'msg'        => array_values($ukagaka["msg"]),
                'msg_emojis' => array_fill(0, count($ukagaka["msg"]), null),
            ];
        } else {
            $personality_id = function_exists('mpu_resolve_personality_id')
                ? mpu_resolve_personality_id($requested_name, false)
                : null;
            $built = mpu_build_builtin_dialog_messages($ukagaka["msg"], $personality_id);
        }

        $arr = [
            "msgall" => $msgall,
            "auto_msg" => $mpu_opt["auto_msg"] ?? "",
            "msg" => $built["msg"],
            "msg_emojis" => $built["msg_emojis"],
        ];

        // 確保 auto_msg 處理
        $auto_msg_array = mpu_msg_code([$arr["auto_msg"]]);
        $arr["auto_msg"] = implode(" ", $auto_msg_array);

        $depth--;
        return $arr;
    } catch (Exception $e) {
        mpu_log_error("mpu_get_msg_arr 錯誤: " . $e->getMessage());
        $depth--;
        return [
            "msgall" => 0,
            "auto_msg" => "",
            "msg" => [__("読み込みエラー", "mp-ukagaka") . ': ' . $e->getMessage()],
            "msg_emojis" => [null],
        ];
    }
}
// phpcs:enable Generic.Formatting.MultipleStatementAlignment, WordPress.Arrays.MultipleStatementAlignment, Squiz.Strings.DoubleQuoteUsage, Universal.Arrays.DisallowShortArraySyntax, NormalizedArrays.Arrays.ArrayBraceSpacing

function mpu_msg_code($msglist = [])
{
    static $depth = 0;

    // 防止無限遞迴
    if ($depth > 5) {
        mpu_log_error("mpu_msg_code 遞迴深度超過限制!");
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
 *
 * @param string $filename_base Dialogue filename without its extension.
 * @param bool   $is_error      Set to true when the returned line is an error message.
 * @return string[]
 */
function mpu_get_msg_from_file($filename_base, &$is_error = null)
{
    $is_error = false;

    // 單次請求內快取（避免同一請求重複讀取相同對話檔案）
    static $cache = [];
    if (isset($cache[$filename_base])) {
        return $cache[$filename_base];
    }

    $mpu_opt = mpu_get_option();
    $ext = $mpu_opt["external_file_format"] ?? "txt";

    // 驗證文件名（只允許字母、數字、下劃線、連字符）
    if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $filename_base)) {
        $is_error = true;
        mpu_log_error('不合法的對話文件名: ' . $filename_base);
        return [__("不正なダイアログファイル名です", "mp-ukagaka")];
    }

    $file_path = mpu_get_dialogs_dir() . "/" . $filename_base . "." . $ext;

    // 【安全性強化】使用安全文件讀取函數
    $content = mpu_secure_file_read($file_path);

    if (is_wp_error($content)) {
        $is_error   = true;
        $error_code = $content->get_error_code();
        if ($error_code === 'file_not_found') {
            return [__("ダイアログファイルが見つかりません", "mp-ukagaka")];
        } elseif ($error_code === 'file_too_large') {
            return [__("ダイアログファイルが大きすぎます。読み込みに失敗しました", "mp-ukagaka")];
        } else {
            return [__("ダイアログファイルを読み込めません", "mp-ukagaka")];
        }
    }

    if ($ext === "json") {
        $json = json_decode($content, true);
        if (
            json_last_error() === JSON_ERROR_NONE &&
            !empty($json["messages"])
        ) {
            $cache[$filename_base] = $json["messages"];
            return $cache[$filename_base];
        }
        $is_error = true;
        return [__("JSONファイルの形式が正しくありません", "mp-ukagaka")];
    }

    $cache[$filename_base] = mpu_str2array($content);
    return $cache[$filename_base];
}
