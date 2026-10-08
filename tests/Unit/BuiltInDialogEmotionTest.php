<?php

use PHPUnit\Framework\TestCase;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class BuiltInDialogEmotionTest extends TestCase {
    public static function setUpBeforeClass(): void {
        if (!function_exists('get_posts')) {
            function get_posts($args = []) {
                $posts = array_values($GLOBALS['_mpu_test_posts'] ?? []);
                $limit = max(0, (int) ($args['numberposts'] ?? count($posts)));
                return array_slice($posts, 0, $limit);
            }
        }
        if (!function_exists('get_comments')) {
            function get_comments($args = []) {
                return array_values($GLOBALS['_mpu_test_comments'] ?? []);
            }
        }
        if (!function_exists('get_permalink')) {
            function get_permalink($post_id) {
                return 'https://example.test/?p=' . (int) $post_id;
            }
        }
        if (!function_exists('esc_url')) {
            function esc_url($value) {
                return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
            }
        }
        if (!function_exists('esc_attr')) {
            function esc_attr($value) {
                return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
            }
        }
        if (!function_exists('esc_html')) {
            function esc_html($value) {
                return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
            }
        }
        if (!function_exists('plugin_dir_path')) {
            function plugin_dir_path($file) {
                return rtrim(dirname((string) $file), '/\\') . DIRECTORY_SEPARATOR;
            }
        }
        if (!function_exists('is_wp_error')) {
            function is_wp_error($value) {
                return $value instanceof WP_Error;
            }
        }
        if (!function_exists('mpu_get_option')) {
            function mpu_get_option() {
                return $GLOBALS['_mpu_test_mpu_opt'] ?? [];
            }
        }
        if (!function_exists('mpu_load_personality_emoji_config')) {
            function mpu_load_personality_emoji_config($personality_id = null) {
                if (isset($GLOBALS['_mpu_test_emoji_supported_by_personality'][$personality_id])) {
                    return ['supported' => $GLOBALS['_mpu_test_emoji_supported_by_personality'][$personality_id]];
                }
                return ['supported' => $GLOBALS['_mpu_test_emoji_supported'] ?? []];
            }
        }
        if (!function_exists('mpu_get_available_personalities')) {
            function mpu_get_available_personalities($include_placeholders = false) {
                return $GLOBALS['_mpu_test_personalities'] ?? [];
            }
        }
        if (!function_exists('mpu_get_personality_id_from_ukagaka_name')) {
            function mpu_get_personality_id_from_ukagaka_name($ukagaka_name) {
                return $GLOBALS['_mpu_test_personality_map'][$ukagaka_name] ?? null;
            }
        }
        if (!function_exists('mpu_get_current_personality_id')) {
            function mpu_get_current_personality_id() {
                return $GLOBALS['_mpu_test_current_personality'] ?? 'Frieren';
            }
        }

        require_once MPU_TESTS_ROOT . '/includes/llm/response-normalizer.php';
        require_once MPU_TESTS_ROOT . '/includes/core/ukagaka-functions.php';
    }

    protected function setUp(): void {
        $GLOBALS['_mpu_test_emoji_supported'] = ['laugh', 'sad'];
        $GLOBALS['_mpu_test_emoji_supported_by_personality'] = [
            'Frieren' => ['laugh', 'sad'],
            'Fern'    => ['calm'],
        ];
        $GLOBALS['_mpu_test_personalities'] = [
            'Frieren' => [],
            'Fern'    => [],
        ];
        $GLOBALS['_mpu_test_current_personality'] = 'Frieren';
        $GLOBALS['_mpu_test_personality_map'] = [
            'default_1' => 'Frieren',
            'default_2' => 'Fern',
        ];
        $GLOBALS['_mpu_test_mpu_opt'] = [
            'external_file_format' => 'txt',
            'cur_ukagaka'          => 'default_1',
            'ukagakas'             => [
                'default_1' => ['dialog_filename' => 'Frieren'],
                'default_2' => ['dialog_filename' => 'Fern'],
                'shared'    => ['dialog_filename' => 'shared_dialog'],
            ],
        ];
        $GLOBALS['_mpu_test_posts'] = [];
    }

    protected function tearDown(): void {
        unset(
            $GLOBALS['_mpu_test_emoji_supported_by_personality'],
            $GLOBALS['_mpu_test_posts']
        );
    }

    public function test_builder_returns_clean_text_and_aligned_png(): void {
        $result = mpu_build_builtin_dialog_messages(
            ['Hello [LAUGH]', 'Plain'],
            'Frieren',
            ['laugh', 'sad', 'calm']
        );

        $this->assertSame(['Hello', 'Plain'], $result['msg']);
        $this->assertSame(['laugh.png', null], $result['msg_emojis']);
    }

    public function test_builder_preserves_json_message_whitespace(): void {
        $result = mpu_build_builtin_dialog_messages(['  padded  '], 'Frieren', ['laugh']);

        $this->assertSame(['  padded  '], $result['msg']);
    }

    public function test_dynamic_expansion_copies_the_selected_emoji_to_every_line(): void {
        $GLOBALS['_mpu_test_posts'] = [
            1 => (object) ['ID' => 1, 'post_title' => 'One'],
            2 => (object) ['ID' => 2, 'post_title' => 'Two'],
        ];

        $result = mpu_build_builtin_dialog_messages(
            [':recentpost[2]: [laugh]'],
            'Frieren',
            ['laugh']
        );

        $this->assertCount(2, $result['msg']);
        $this->assertSame(['laugh.png', 'laugh.png'], $result['msg_emojis']);
    }

    public function test_dynamic_shortcode_is_not_an_emotion_tag(): void {
        $result = mpu_normalize_extract_emotions(':recentpost[5]:', ['laugh'], false);

        $this->assertSame(':recentpost[5]:', $result['text']);
        $this->assertSame([], $result['tags']);
    }

    public function test_expanded_post_title_is_not_reparsed_for_emotions(): void {
        $GLOBALS['_mpu_test_posts'] = [
            1 => (object) ['ID' => 1, 'post_title' => 'Article [sad]'],
        ];

        $result = mpu_build_builtin_dialog_messages(
            [':recentpost[1]: [laugh]'],
            'Frieren',
            ['laugh', 'sad']
        );

        $this->assertStringContainsString('[sad]', $result['msg'][0]);
        $this->assertSame(['laugh.png'], $result['msg_emojis']);
    }

    public function test_other_personality_tag_is_hidden_without_cross_personality_asset(): void {
        $result = mpu_build_builtin_dialog_messages(['Quiet [laugh]'], 'Fern', ['laugh', 'calm']);

        $this->assertSame(['Quiet'], $result['msg']);
        $this->assertSame([null], $result['msg_emojis']);
    }

    public function test_first_tag_supported_by_the_active_personality_wins(): void {
        $result = mpu_build_builtin_dialog_messages(
            ['Choice [calm] [sad] [laugh]'],
            'Frieren',
            ['calm', 'sad', 'laugh']
        );

        $this->assertSame(['sad.png'], $result['msg_emojis']);
    }

    public function test_unknown_tag_remains_visible(): void {
        $result = mpu_build_builtin_dialog_messages(['Keep [note]'], 'Frieren', ['laugh']);

        $this->assertSame(['Keep [note]'], $result['msg']);
        $this->assertSame([null], $result['msg_emojis']);
    }

    public function test_gif_only_asset_contract_still_emits_png_name(): void {
        $result = mpu_build_builtin_dialog_messages(['Animated [laugh]'], 'Frieren', ['laugh']);

        $this->assertSame(['laugh.png'], $result['msg_emojis']);
    }

    public function test_duplicate_visible_lines_are_first_wins_and_sequential(): void {
        $result = mpu_build_builtin_dialog_messages(
            ['Same[laugh]', 'Same[sad]'],
            'Frieren',
            ['laugh', 'sad']
        );

        $this->assertSame(['Same'], $result['msg']);
        $this->assertSame(['laugh.png'], $result['msg_emojis']);
        $this->assertSame('["Same"]', json_encode($result['msg']));
    }

    public function test_get_msg_arr_error_keeps_emoji_array_aligned(): void {
        $GLOBALS['_mpu_test_mpu_opt']['ukagakas']['default_1']['dialog_filename'] = 'does_not_exist';

        $result = mpu_get_msg_arr('default_1');

        $this->assertCount(1, $result['msg']);
        $this->assertSame([null], $result['msg_emojis']);
    }

    public function test_builtin_rest_shape_never_keyword_guesses(): void {
        $result = mpu_normalize_builtin_dialog_for_rest('angry keyword', null);

        $this->assertSame('angry keyword', $result['display_text']);
        $this->assertNull($result['emoji']);
        $this->assertSame([], $result['emotion_tags']);
        $this->assertSame('', $result['think']);
    }

    public function test_strict_resolver_uses_three_state_contract(): void {
        $this->assertSame('Frieren', mpu_resolve_personality_id(null, false));
        $this->assertSame('Fern', mpu_resolve_personality_id('default_2', false));
        $this->assertNull(mpu_resolve_personality_id('shared', false));
        $this->assertSame('Frieren', mpu_resolve_personality_id('missing', false));
        $this->assertSame('Frieren', mpu_resolve_personality_id('shared'));
    }
}
