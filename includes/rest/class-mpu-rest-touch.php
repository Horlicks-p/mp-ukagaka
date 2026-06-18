<?php

/**
 * REST Controller: Touch & Decoration Handlers
 *
 * 取代 rest-touch.php。路由 URL、HTTP method、permission、
 * rate limit key、response 結構與 WP_Error code 均與舊 procedural 實作一致。
 *
 * 端點：
 *   POST /mp-ukagaka/v1/touch/decoration
 *   POST /mp-ukagaka/v1/touch/zone
 *
 * @package MP_Ukagaka
 * @subpackage REST
 */

if (!defined('ABSPATH')) {
    exit();
}

class MPU_REST_Touch extends MPU_REST_Base {

    /**
     * 向 WordPress 註冊所有 Touch 端點。
     * 由 bootstrap.php 集中掛載到 rest_api_init。
     */
    public function register_routes() {
        register_rest_route($this->namespace, '/touch/decoration', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'decoration_chat'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route($this->namespace, '/touch/zone', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'touch_zone_chat'],
            'permission_callback' => '__return_true',
        ]);

		register_rest_route(
			$this->namespace,
			'/touch/give',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'give_item' ),
				'permission_callback' => '__return_true',
			)
		);
    }

    /**
     * POST /touch/decoration — 裝飾物點擊對話
     * Rate limit: 20 次 / 60 秒（與舊實作相同）
     */
    public function decoration_chat(WP_REST_Request $request) {
        $st = $this->check_session_token($request);
        if (null !== $st) {
            return $st;
        }

        $mpu_opt = mpu_get_option();

        if (empty($mpu_opt['ai_enabled'])) {
            return $this->fail('rest_error', __('AI機能が有効になっていません', 'mp-ukagaka'), 400);
        }

        $rl = $this->rate_limit('decoration_chat', 20, 60);
        if ($rl !== null) return $rl;

        $decoration_type_param = $request->get_param('decoration_type');
        $decoration_type = !empty($decoration_type_param)
            ? sanitize_text_field(wp_unslash($decoration_type_param))
            : '';

        if (empty($decoration_type)) {
            return $this->fail('rest_error', __('装飾の種類が指定されていません', 'mp-ukagaka'), 400);
        }

        $personality_id = function_exists('mpu_get_current_personality_id')
            ? mpu_get_current_personality_id()
            : null;

        $user_prompt = mpu_get_decoration_prompt($decoration_type, $personality_id);

        if ($user_prompt === false) {
            return $this->fail('rest_error', __('不明な装飾の種類です', 'mp-ukagaka'), 400);
        }

        $ukagaka_name = $mpu_opt['ukagakas'][$mpu_opt['cur_ukagaka']]['name'] ?? 'キャラクター';

        $user_prompt .= "\n\n【回応ルール】淡々とした常体で、30-150文字で{$ukagaka_name}として直接反応すること。第三者視点の描写は禁止。";

		$normalized = $this->run_reaction( $user_prompt, $personality_id, 'decoration' );
		if ( is_wp_error( $normalized ) ) {
			return $this->fail( 'rest_error', $normalized->get_error_message(), 400 );
		}
        $result = $normalized['display_text'];

        if (function_exists('mpu_record_conversation')) {
            mpu_record_conversation('decoration');
        }

        if (function_exists('mpu_observation_push_touch')) {
            mpu_observation_push_touch($request, 'decoration_' . sanitize_key($decoration_type));
        }

        return $this->ok(mpu_normalize_ai_response_rest_fields($normalized));
    }

    /**
     * POST /touch/zone — 角色觸摸區域對話
     * Rate limit: 20 次 / 60 秒（與舊實作相同）
     */
    public function touch_zone_chat(WP_REST_Request $request) {
        $st = $this->check_session_token($request);
        if (null !== $st) {
            return $st;
        }

        $mpu_opt = mpu_get_option();

        if (empty($mpu_opt['ai_enabled'])) {
            return $this->fail('rest_error', __('AI機能が有効になっていません', 'mp-ukagaka'), 400);
        }

        $rl = $this->rate_limit('touch_zone_chat', 20, 60);
        if ($rl !== null) return $rl;

        $touch_zone_param = $request->get_param('touch_zone');
        $touch_zone = !empty($touch_zone_param)
            ? sanitize_text_field(wp_unslash($touch_zone_param))
            : '';

        if (empty($touch_zone)) {
            return $this->fail('rest_error', __('タッチエリアが指定されていません', 'mp-ukagaka'), 400);
        }

        $ukagaka_name = $mpu_opt['ukagakas'][$mpu_opt['cur_ukagaka']]['name'] ?? 'キャラクター';

        $personality_id = null;
        if (function_exists('mpu_get_personality_id_from_ukagaka_name')) {
            $personality_id = mpu_get_personality_id_from_ukagaka_name($mpu_opt['cur_ukagaka']);
        }
        if ($personality_id === null && function_exists('mpu_get_current_personality_id')) {
            $personality_id = mpu_get_current_personality_id();
        }

        $touchzones = [];
        if (function_exists('mpu_load_personality_touchzones')) {
            $touchzones = mpu_load_personality_touchzones($personality_id);
        }

        $zone_config = $touchzones['zones'][$touch_zone] ?? null;
        if (!$zone_config) {
            return $this->fail('rest_error', __('不明なタッチエリアです', 'mp-ukagaka'), 400);
        }

        $prompt_categories = $zone_config['reactions'] ?? ['touch_body'];

        $prompts_data = [];
        if (function_exists('mpu_load_personality_prompts')) {
            $prompts_data = mpu_load_personality_prompts($personality_id);
        }

        $available_prompts = [];
        foreach ($prompt_categories as $category) {
            if (!empty($prompts_data[$category]) && is_array($prompts_data[$category])) {
                $available_prompts = array_merge($available_prompts, $prompts_data[$category]);
            }
        }

        $user_prompt = empty($available_prompts)
            ? '触られた反応をする'
            : $available_prompts[array_rand($available_prompts)];

        $user_prompt .= "\n\n【回応ルール】淡々とした常体で、30-150文字で{$ukagaka_name}として直接反応すること。第三者視点の描写は禁止。";

		$normalized = $this->run_reaction( $user_prompt, $personality_id, 'touch' );
		if ( is_wp_error( $normalized ) ) {
			return $this->fail( 'rest_error', $normalized->get_error_message(), 400 );
		}
        $result = $normalized['display_text'];

        if (function_exists('mpu_record_conversation')) {
            mpu_record_conversation('touch');
        }

        if (function_exists('mpu_observation_push_touch')) {
            mpu_observation_push_touch($request, sanitize_key($touch_zone));
        }

        $response         = mpu_normalize_ai_response_rest_fields($normalized);
        $response['zone'] = $touch_zone;

        return $this->ok($response);
    }

	// phpcs:disable Generic.Formatting.MultipleStatementAlignment
	/**
	 * React to a gift or food item.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response|WP_Error REST response.
	 */
	public function give_item( WP_REST_Request $request ) {
		$session_token_error = $this->check_session_token( $request );
		if ( null !== $session_token_error ) {
			return $session_token_error;
		}

		$mpu_opt = mpu_get_option();
		if ( empty( $mpu_opt['ai_enabled'] ) ) {
			return $this->fail( 'rest_error', __( 'AI機能が有効になっていません', 'mp-ukagaka' ), 400 );
		}

		$rate_limit = $this->rate_limit( 'give_item', 20, 60 );
		if ( null !== $rate_limit ) {
			return $rate_limit;
		}

		$item_id = sanitize_key( wp_unslash( (string) $request->get_param( 'item_id' ) ) );
		$personality_id = function_exists( 'mpu_get_current_personality_id' )
			? mpu_get_current_personality_id()
			: null;
		$item = function_exists( 'mpu_get_personality_item' )
			? mpu_get_personality_item( $item_id, $personality_id )
			: false;
		if ( false === $item ) {
			return $this->fail( 'rest_error', __( '不明なアイテムです', 'mp-ukagaka' ), 400 );
		}

		$session_id = MPU_Chat_History_Service::get_session_id( $request );
		if ( '' === $session_id ) {
			return $this->fail( 'rest_error', __( '会話セッションが指定されていません', 'mp-ukagaka' ), 400 );
		}

		if ( ! $request->has_param( 'history' ) ) {
			return $this->fail( 'rest_error', __( '会話履歴が指定されていません', 'mp-ukagaka' ), 400 );
		}

		$raw_history = $request->get_param( 'history' );
		$decoded_history = is_string( $raw_history )
			? json_decode( wp_unslash( $raw_history ), true )
			: $raw_history;
		if ( ! is_array( $decoded_history ) ) {
			return $this->fail( 'rest_error', __( '会話履歴の形式が正しくありません', 'mp-ukagaka' ), 400 );
		}
		$history = MPU_Chat_History_Service::normalize_history( $decoded_history );

		$ukagaka_name = $mpu_opt['ukagakas'][ $mpu_opt['cur_ukagaka'] ]['name'] ?? 'キャラクター';
		$user_prompt = $item['prompt'];
		$user_prompt .= 'food' === $item['kind']
			? "\n\n差し出された食べ物を受け取って食べ、味の感想を述べること。"
			: "\n\n差し出された贈り物を受け取り、お礼を述べること。";

		// item の reactions が指す prompts.json カテゴリからランダムに 1 件抽き、
		// 演出の角度を毎回ばらつかせる (touch_zone_chat と同型).
		$reaction_pool = array();
		if ( ! empty( $item['reactions'] ) && function_exists( 'mpu_load_personality_prompts' ) ) {
			$prompts_data = mpu_load_personality_prompts( $personality_id );
			foreach ( $item['reactions'] as $category ) {
				if ( empty( $prompts_data[ $category ] ) || ! is_array( $prompts_data[ $category ] ) ) {
					continue;
				}
				// malformed JSON 對策：非字串・空白のみの行は候選から除外する.
				foreach ( $prompts_data[ $category ] as $line ) {
					if ( is_string( $line ) && '' !== trim( $line ) ) {
						$reaction_pool[] = $line;
					}
				}
			}
		}
		if ( ! empty( $reaction_pool ) ) {
			$user_prompt .= "\n" . $reaction_pool[ array_rand( $reaction_pool ) ];
		} elseif ( ! empty( $item['favorite'] ) ) {
			$user_prompt .= "\n特別に喜ぶ反応をすること。";
		}
		$user_prompt .= "\n\n【回応ルール】淡々とした常体で、30-150文字で{$ukagaka_name}として直接反応すること。第三者視点の描写は禁止。";

		$normalized = $this->run_reaction( $user_prompt, $personality_id, 'give' );
		if ( is_wp_error( $normalized ) ) {
			return $this->fail( 'rest_error', $normalized->get_error_message(), 400 );
		}

		if ( function_exists( 'mpu_record_conversation' ) ) {
			mpu_record_conversation( 'give' );
		}
		if ( function_exists( 'mpu_observation_push_item' ) ) {
			mpu_observation_push_item( $request, $item['kind'], $item['id'] );
		}

		$user_anchor = sprintf(
			/* translators: %s is an item name. */
			__( '（%sを差し出した）', 'mp-ukagaka' ),
			$item['name']
		);
		$history[] = array(
			'role'    => 'user',
			'content' => $user_anchor,
			'type'    => 'synthetic',
		);
		MPU_Chat_History_Service::store_after_auto(
			$session_id,
			$history,
			$normalized['checksum_text'],
			'give'
		);

		$response = mpu_normalize_ai_response_rest_fields( $normalized );
		$response['item_id'] = $item['id'];
		$response['kind'] = $item['kind'];
		$response['user_anchor'] = $user_anchor;

		return $this->ok( $response );
	}

	/**
	 * Execute the shared AI reaction pipeline.
	 *
	 * @param string      $user_prompt    User prompt.
	 * @param string|null $personality_id Personality ID.
	 * @param string      $context        Normalizer context.
	 * @return array|WP_Error Normalized response or error.
	 */
	private function run_reaction( $user_prompt, $personality_id, $context ) {
		$mpu_opt = mpu_get_option();
		$ukagaka_name = $mpu_opt['ukagakas'][ $mpu_opt['cur_ukagaka'] ]['name'] ?? 'キャラクター';
		$language = $mpu_opt['ai_language'] ?? 'ja';
		$system_prompt = mpu_resolve_system_prompt( $personality_id, $mpu_opt, $ukagaka_name );
		$provider = mpu_get_current_provider( $mpu_opt );
		$api_key = mpu_get_provider_api_key( $provider, $mpu_opt );

		if ( 'ollama' !== $provider && empty( $api_key ) ) {
			return new WP_Error(
				'rest_error',
				sprintf( __( '%s API Key が設定されていません', 'mp-ukagaka' ), ucfirst( $provider ) ),
				array( 'status' => 400 )
			);
		}

		$max_tokens = 800;
		if ( function_exists( 'mpu_get_personality_max_tokens' ) ) {
			$max_tokens = mpu_get_personality_max_tokens( $personality_id );
		}

		$result = mpu_call_ai_api(
			$provider,
			$api_key,
			$system_prompt,
			$user_prompt,
			$language,
			$mpu_opt,
			$max_tokens
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$normalized = mpu_normalize_ai_response_for_rest(
			$result,
			$personality_id,
			array( 'context' => $context )
		);
		$max_length = 500;
		if ( function_exists( 'mpu_get_personality_max_response_length' ) ) {
			$max_length = mpu_get_personality_max_response_length( $personality_id );
		}

		return mpu_normalize_ai_response_apply_display_limit( $normalized, $max_length );
	}
	// phpcs:enable Generic.Formatting.MultipleStatementAlignment
}
