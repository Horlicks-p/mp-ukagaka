<?php
/**
 * Response Normalizer — AI 回應的單一資料契約來源（§13.2 / M1a）.
 *
 * 這層是 emotion-tag / think-block 後續所有改動的唯一入口。呼叫端（REST chat /
 * dialog、未來 SSE finalize）一律走 mpu_normalize_ai_response()，取得「給各通路用的
 * 乾淨文字 + metadata」，避免 display / history / checksum 三者語意漂移。
 *
 * 契約鎖死規則見 docs：plan/Emotion_Tag_And_Think_Block_Plan.md §13.2。
 * 重點：
 *  1. display_text === history_text === checksum_text（永遠相等，要加效果就新增欄位）。
 *  2. think 僅取「回覆最開頭」的 think 標籤；中段 / 多個 → 剝離 + warning，不回傳。
 *  3. 未知標籤預設保留（避免誤刪 WordPress / TODO 等正常用字）。
 *  4. primary_emotion_tag 與 primary_emotion_file 同步存在或同步為 null。
 *  5. enable_inner_monologue === false 時，think 固定回傳空字串。
 *
 * @package MP_Ukagaka
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

if ( ! defined( 'MPU_INNER_MONOLOGUE_CONTEXT_DEFAULTS' ) ) {
	define(
		'MPU_INNER_MONOLOGUE_CONTEXT_DEFAULTS',
		array(
			'chat'       => true,
			'touch'      => true,
			'page_aware' => true,
			'decoration' => false,
			'give'       => false,
			'initial'    => false,
			'diary'      => false,
		)
	);
}

/**
 * 將 AI 原始回應轉換為各通路所需的乾淨版本與 metadata。
 *
 * @param string      $raw_text       AI 原始輸出.
 * @param string|null $personality_id Personality ID（null = 當前角色）.
 * @param array       $options        正規化選項；支援鍵 progressive_think（漸進 think 模式，
 *                                    預設 false，M1a 僅佔位不影響輸出）與 strip_unknown_tags
 *                                    （未知標籤是否剝離，預設 false → 保留，§15.2.1）.
 * @return array{
 *   display_text:string, tts_text:string, history_text:string, checksum_text:string,
 *   think:string, emotion_tags:string[], emotion_files:string[],
 *   primary_emotion_tag:?string, primary_emotion_file:?string
 * }
 */
function mpu_normalize_ai_response( $raw_text, $personality_id = null, array $options = array() ) {
	$defaults = array(
		'context'            => 'chat',
		'progressive_think'  => false,
		'strip_unknown_tags' => false,
	);
	$options  = array_merge( $defaults, $options );

	if ( ! is_string( $raw_text ) ) {
		$raw_text = '';
	}

	// 抽取「開頭」think 標籤作為內心獨白 bubble 來源.
	$think = mpu_normalize_extract_leading_think( $raw_text );

	// 規則 8：全域開關關閉時，normalizer 仍剝離 think，但 think 欄位固定為空.
	if ( ! mpu_is_inner_monologue_enabled_for_context( $options['context'], $personality_id ) ) {
		if ( '' !== $think ) {
			mpu_normalize_warn(
				sprintf(
					'inner monologue suppressed for context "%s"; LLM emitted think but it was discarded',
					(string) $options['context']
				)
			);
		}
		$think = '';
	}

	// display 文字：剝離所有 think / reasoning 變體（委派既有共用過濾器）.
	if ( function_exists( 'mpu_filter_thinking_content' ) ) {
		$clean = mpu_filter_thinking_content( $raw_text );
	} else {
		// 後備：與 llm-functions.php 既有 fallback 一致.
		$clean = preg_replace( '/<think>.*?<\/think>/is', '', $raw_text );
		$clean = preg_replace( '/<think>.*$/is', '', $clean );
		$clean = is_string( $clean ) ? trim( $clean ) : '';
	}

	// emotion 標籤解析（inline 標籤 + 舊顯式格式），並從 display 剝離.
	$supported = mpu_normalize_get_supported_tags( $personality_id );
	$emotion   = mpu_normalize_extract_emotions( $clean, $supported, $options['strip_unknown_tags'] );
	$clean     = $emotion['text'];

	// 收尾與組裝.
	$clean   = preg_replace( '/\n[ \t]*\n[ \t]*\n+/', "\n\n", $clean );
	$display = is_string( $clean ) ? trim( $clean ) : '';

	return array(
		'display_text'         => $display,
		'tts_text'             => $display,
		'history_text'         => $display,
		'checksum_text'        => $display,
		'think'                => $think,
		'emotion_tags'         => $emotion['tags'],
		'emotion_files'        => $emotion['files'],
		'primary_emotion_tag'  => $emotion['tags'][0] ?? null,
		'primary_emotion_file' => $emotion['files'][0] ?? null,
	);
}

/**
 * REST caller helper：normalizer 優先，沒有合法 tag 時才退回既有 keyword scorer。
 *
 * @param string      $raw_text       AI 原始輸出.
 * @param string|null $personality_id Personality ID.
 * @param array       $options        Normalizer options.
 * @return array{
 *   display_text:string, tts_text:string, history_text:string, checksum_text:string,
 *   think:string, emotion_tags:string[], emotion_files:string[],
 *   primary_emotion_tag:?string, primary_emotion_file:?string, emoji:?string
 * }
 */
function mpu_normalize_ai_response_for_rest( $raw_text, $personality_id = null, array $options = array() ) {
	$normalized = mpu_normalize_ai_response( $raw_text, $personality_id, $options );
	$emoji      = $normalized['primary_emotion_file'];

	if ( null === $emoji && function_exists( 'mpu_analyze_emoji_from_text' ) && '' !== $normalized['display_text'] ) {
		$emoji = mpu_analyze_emoji_from_text( $normalized['display_text'], $personality_id );
	}

	$normalized['emoji'] = $emoji;
	return $normalized;
}

/**
 * Apply the existing REST display limit after stripping think/emotion tags.
 *
 * @param array $normalized Normalized response payload.
 * @param int   $max_length Maximum display length.
 * @return array
 */
function mpu_normalize_ai_response_apply_display_limit( array $normalized, $max_length ) {
	$max_length = intval( $max_length );
	if ( $max_length <= 0 || mb_strlen( $normalized['display_text'], 'UTF-8' ) <= $max_length ) {
		return $normalized;
	}

	$display = mb_substr( $normalized['display_text'], 0, $max_length, 'UTF-8' ) . '...';

	$normalized['display_text']  = $display;
	$normalized['tts_text']      = $display;
	$normalized['history_text']  = $display;
	$normalized['checksum_text'] = $display;

	return $normalized;
}

/**
 * Build non-streaming REST response fields from normalized data.
 *
 * @param array $normalized Normalized response payload.
 * @return array
 */
function mpu_normalize_ai_response_rest_fields( array $normalized ) {
	return array(
		'msg'                  => $normalized['display_text'],
		'emoji'                => $normalized['emoji'] ?? null,
		'emotion_tags'         => $normalized['emotion_tags'],
		'emotion_files'        => $normalized['emotion_files'],
		'primary_emotion_tag'  => $normalized['primary_emotion_tag'],
		'primary_emotion_file' => $normalized['primary_emotion_file'],
		'think'                => $normalized['think'],
	);
}

/**
 * 抽取回覆「最開頭」的 think / thinking 內容。
 *
 * 行為（§13.2 規則 6 / §13.6）：
 *  - 僅承認位於字串最前端（允許前導空白）的 think；中段 think 不回傳。
 *  - 多個 think → 只取第一個，其餘忽略；並寫 warning log。
 *  - 開頭 think 未閉合（max_tokens 截斷）→ 將其後全部內容視為 think（Defense A 精神）。
 *
 * 注意：此處只認 think / thinking（內心獨白標籤）；reasoning / reflection 等原生
 * 推理家族不作為 bubble 來源，僅由 mpu_filter_thinking_content 從 display 剝離。
 *
 * @param string $raw_text AI 原始輸出.
 * @return string think 內容（無則空字串）.
 */
function mpu_normalize_extract_leading_think( $raw_text ) {
	if ( ! is_string( $raw_text ) || '' === $raw_text ) {
		return '';
	}

	$think         = '';
	$leading_found = false;

	// 開頭閉合 think：起始標籤直到對應結束標籤.
	if ( preg_match( '/^\s*<(think|thinking)>(.*?)<\/\1>/is', $raw_text, $m ) ) {
		$think         = trim( $m[2] );
		$leading_found = true;
	} elseif ( preg_match( '/^\s*<(think|thinking)>(.*)$/is', $raw_text, $m ) ) {
		// 開頭未閉合 think（截斷）→ 其後全部視為 think.
		$think         = trim( $m[2] );
		$leading_found = true;
		mpu_normalize_warn( 'leading think tag not closed (truncated); captured remainder as think' );
	}

	// 開頭已取得 think，但後方仍有額外 think 開標籤 → 只保留第一個.
	$open_count = preg_match_all( '/<(think|thinking)>/i', $raw_text );
	if ( $leading_found ) {
		if ( $open_count > 1 ) {
			mpu_normalize_warn( 'multiple think blocks; only the leading one is kept for the bubble' );
		}
	} elseif ( $open_count > 0 ) {
		// 開頭沒有 think，但中段出現 → 剝離 + warning，think 維持空字串（規則 6）.
		mpu_normalize_warn( 'mid-response think block stripped; not rendered as bubble' );
	}

	return $think;
}

/**
 * 解析文字中的 emotion 標籤並從文字剝離（合法者），回傳順序化結果。
 *
 * 支援兩種格式：
 *  - 新內嵌：方括號加標籤名（須符合白名單字元規則且在 supported 清單內）。
 *  - 舊顯式：表情 / emoji / emotion 冒號語法（向下相容）。
 *
 * 防呆：
 *  - negative lookahead 避免吃掉 markdown link 的文字部分。
 *  - 未知標籤預設保留（除非 $strip_unknown 為 true）— 不影響 emotion 陣列。
 *
 * @param string   $text          待解析文字.
 * @param string[] $supported     角色 supported 標籤名清單.
 * @param bool     $strip_unknown 未知標籤是否一併剝離.
 * @return array{text:string, tags:string[], files:string[]}
 */
function mpu_normalize_extract_emotions( $text, array $supported, $strip_unknown = false ) {
	if ( ! is_string( $text ) || '' === $text ) {
		return array(
			'text'  => (string) $text,
			'tags'  => array(),
			'files' => array(),
		);
	}

	// 收集所有命中（含位移）；tag 為 null 表示未知 inline 標籤，依 $strip_unknown 決定是否剝離.
	$hits = array();

	// 新內嵌標籤.
	$inline_patterns = array(
		'/\[([ \t]*[a-z][a-z0-9_-]{0,31}[ \t]*(?:[.!?。．、，,;；:：…]+[ \t]*)?)\](?!\()/iu',
		'/【([ \t]*[a-z][a-z0-9_-]{0,31}[ \t]*(?:[.!?。．、，,;；:：…]+[ \t]*)?)】/iu',
		'/［([ \t]*[a-z][a-z0-9_-]{0,31}[ \t]*(?:[.!?。．、，,;；:：…]+[ \t]*)?)］/iu',
	);
	foreach ( $inline_patterns as $pattern ) {
		if ( preg_match_all( $pattern, $text, $mm, PREG_OFFSET_CAPTURE ) ) {
			foreach ( $mm[0] as $i => $whole ) {
				$tag_name = mpu_normalize_clean_inline_emotion_tag( $mm[1][ $i ][0] );
				$canon    = mpu_normalize_resolve_emotion_tag( $tag_name, $supported );
				if ( null !== $canon ) {
					$hits[] = array(
						'offset' => $whole[1],
						'len'    => strlen( $whole[0] ),
						'tag'    => $canon,
					);
				} elseif ( $strip_unknown ) {
					$hits[] = array(
						'offset' => $whole[1],
						'len'    => strlen( $whole[0] ),
						'tag'    => null,
					);
				}
			}
		}
	}

	// 舊顯式格式.
	$explicit_patterns = array(
		'/\[(?:表情|emoji|emotion)\s*:\s*([^\]]+)\]/u',
		'/\((?:表情|emoji|emotion)\s*:\s*([^\)]+)\)/u',
	);
	foreach ( $explicit_patterns as $pattern ) {
		if ( preg_match_all( $pattern, $text, $mm, PREG_OFFSET_CAPTURE ) ) {
			foreach ( $mm[0] as $i => $whole ) {
				$canon = mpu_normalize_resolve_emotion_tag( $mm[1][ $i ][0], $supported );
				if ( null !== $canon ) {
					$hits[] = array(
						'offset' => $whole[1],
						'len'    => strlen( $whole[0] ),
						'tag'    => $canon,
					);
				}
				// 顯式格式但解析不到對應表情 → 視為一般文字，保留（與既有 extract 行為一致）.
			}
		}
	}

	if ( empty( $hits ) ) {
		return array(
			'text'  => $text,
			'tags'  => array(),
			'files' => array(),
		);
	}

	// 依出現順序排序，組裝 emotion 陣列.
	usort(
		$hits,
		function ( $a, $b ) {
			return $a['offset'] <=> $b['offset'];
		}
	);

	$tags  = array();
	$files = array();
	foreach ( $hits as $h ) {
		if ( null !== $h['tag'] ) {
			$tags[]  = $h['tag'];
			$files[] = $h['tag'] . '.png';
		}
	}

	// 由後往前剝離命中片段（避免位移失效）.
	usort(
		$hits,
		function ( $a, $b ) {
			return $b['offset'] <=> $a['offset'];
		}
	);
	foreach ( $hits as $h ) {
		$text = substr( $text, 0, $h['offset'] ) . substr( $text, $h['offset'] + $h['len'] );
	}

	return array(
		'text'  => $text,
		'tags'  => $tags,
		'files' => $files,
	);
}

/**
 * Normalize loose inline [tag] spelling before supported-tag lookup.
 *
 * @param string $name Raw inline tag body.
 * @return string
 */
function mpu_normalize_clean_inline_emotion_tag( $name ) {
	$name = trim( (string) $name );
	$name = preg_replace( '/[.!?。．、，,;；:：…]+\s*$/u', '', $name );
	return is_string( $name ) ? trim( $name ) : '';
}

/**
 * 將標籤名解析為角色 supported 清單中的正規名（不分大小寫）。
 *
 * @param string   $name      待解析的標籤名.
 * @param string[] $supported 角色 supported 標籤名清單.
 * @return string|null 正規 tag 名，無對應則 null.
 */
function mpu_normalize_resolve_emotion_tag( $name, array $supported ) {
	$name = trim( (string) $name );
	if ( '' === $name || empty( $supported ) ) {
		return null;
	}
	if ( in_array( $name, $supported, true ) ) {
		return $name;
	}
	foreach ( $supported as $s ) {
		if ( 0 === strcasecmp( (string) $s, $name ) ) {
			return (string) $s;
		}
	}
	return null;
}

/**
 * 取得角色 supported 表情標籤名清單。
 *
 * @param string|null $personality_id Personality ID（null = 當前角色）.
 * @return string[]
 */
function mpu_normalize_get_supported_tags( $personality_id = null ) {
	if ( function_exists( 'mpu_load_personality_emoji_config' ) ) {
		$config = mpu_load_personality_emoji_config( $personality_id );
		if ( ! empty( $config['supported'] ) && is_array( $config['supported'] ) ) {
			return array_values( $config['supported'] );
		}
	}
	return array();
}

/**
 * 全域內心獨白開關（§13.2 規則 8 / §13.4 Q6）。
 *
 * 跨 provider 的 UI 演出層開關，預設 true。**不可**沿用 ollama_disable_thinking
 * （那是 Ollama 原生 reasoning 引擎層開關，職責不同）。
 *
 * 注意：admin 切換 UI 與 per-personality manifest features.inner_monologue 於 M3 落地，
 * 屆時會在此補上 personality 覆寫參數；M1a 僅提供全域讀取入口（預設 true → 行為不變）。
 *
 * @return bool
 */
function mpu_is_inner_monologue_enabled() {
	if ( function_exists( 'mpu_get_option' ) ) {
		$opt = mpu_get_option();
		if ( is_array( $opt ) && array_key_exists( 'enable_inner_monologue', $opt ) ) {
			return (bool) $opt['enable_inner_monologue'];
		}
	}
	return true;
}

/**
 * Check the inner-monologue policy for a request context.
 *
 * @param string|null $context        Request context.
 * @param string|null $personality_id Personality ID.
 * @return bool
 */
function mpu_is_inner_monologue_enabled_for_context( $context = 'chat', $personality_id = null ) {
	if ( ! mpu_is_inner_monologue_enabled() ) {
		return false;
	}

	$context  = is_string( $context ) && '' !== $context ? $context : 'chat';
	$defaults = MPU_INNER_MONOLOGUE_CONTEXT_DEFAULTS;

	if ( function_exists( 'mpu_load_personality_manifest' ) ) {
		$manifest = mpu_load_personality_manifest( $personality_id );
		if (
			is_array( $manifest )
			&& isset( $manifest['features']['inner_monologue_contexts'] )
			&& is_array( $manifest['features']['inner_monologue_contexts'] )
			&& array_key_exists( $context, $manifest['features']['inner_monologue_contexts'] )
		) {
			return (bool) $manifest['features']['inner_monologue_contexts'][ $context ];
		}
	}

	if ( array_key_exists( $context, $defaults ) ) {
		return (bool) $defaults[ $context ];
	}

	return false;
}

/**
 * Normalizer 內部 warning log（去重交給 debug 系統）。
 *
 * @param string $message Warning 訊息.
 * @return void
 */
function mpu_normalize_warn( $message ) {
	if ( function_exists( 'mpu_debug_log' ) ) {
		mpu_debug_log( 'mpu_normalize_ai_response: ' . $message );
	}
}
