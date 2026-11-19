<?php

use PHPUnit\Framework\TestCase;

class MPU_Story_Test_REST_Response {
    private $data;
    public function __construct($data, $status = 200) { $this->data = $data; }
    public function get_data() { return $this->data; }
}

class MPU_Story_Test_REST_Request {
    private $params;
    public function __construct($params) { $this->params = $params; }
    public function get_param($key) { return $this->params[$key] ?? null; }
}

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class StoryTalkLoaderTest extends TestCase {
    private $root;

    private function loadDependencies(): void {
        if (!function_exists('plugin_dir_path')) {
            function plugin_dir_path($file) { return $GLOBALS['_story_test_root'] . '/'; }
        }
        if (!function_exists('wp_normalize_path')) {
            function wp_normalize_path($path) { return str_replace('\\', '/', $path); }
        }
        if (!function_exists('trailingslashit')) {
            function trailingslashit($path) { return rtrim($path, '/\\') . '/'; }
        }
        if (!function_exists('mpu_load_personality_emoji_config')) {
            function mpu_load_personality_emoji_config($id = null) { return ['supported' => ['thinking', 'smirk']]; }
        }
        if (!function_exists('mpu_get_option')) {
            function mpu_get_option() { return []; }
        }
        if (!function_exists('mpu_resolve_personality_id')) {
            function mpu_resolve_personality_id($id, $fallback = true) { return 'Frieren'; }
        }
        if (!function_exists('mpu_build_builtin_dialog_messages')) {
            function mpu_build_builtin_dialog_messages($messages, $id) { return ['msg' => $messages, 'msg_emojis' => array_fill(0, count($messages), null)]; }
        }
        if (!function_exists('mpu_msg_code')) {
            function mpu_msg_code($messages) { return $messages; }
        }
        if (!function_exists('mpu_str2array')) {
            function mpu_str2array($text) { return array_values(array_filter(explode("\n", trim($text)))); }
        }
        if (!function_exists('is_wp_error')) {
            function is_wp_error($value) { return $value instanceof WP_Error; }
        }
        if (!function_exists('mpu_log_warning')) {
            function mpu_log_warning($message) {}
        }
        if (!class_exists('WP_REST_Response')) {
            class_alias(MPU_Story_Test_REST_Response::class, 'WP_REST_Response');
        }
        if (!class_exists('WP_REST_Request')) {
            class_alias(MPU_Story_Test_REST_Request::class, 'WP_REST_Request');
        }
        require_once MPU_TESTS_ROOT . '/includes/core/file-functions.php';
        require_once MPU_TESTS_ROOT . '/includes/personality/personality-loader.php';
        require_once MPU_TESTS_ROOT . '/includes/llm/response-normalizer.php';
        require_once MPU_TESTS_ROOT . '/includes/personality/personality-stories.php';
        require_once MPU_TESTS_ROOT . '/includes/rest/class-mpu-rest-base.php';
        require_once MPU_TESTS_ROOT . '/includes/rest/class-mpu-rest-dialog.php';
    }

    protected function setUp(): void {
        $this->loadDependencies();
        $this->root = sys_get_temp_dir() . '/mpu-story-' . bin2hex(random_bytes(8));
        $GLOBALS['_story_test_root'] = $this->root;
        mkdir($this->root . '/ghost/Frieren', 0777, true);
        mkdir($this->root . '/dialogs');
        $GLOBALS['wp_filesystem'] = new class {
            public function exists($path) { return file_exists($path); }
            public function get_contents($path) { return file_get_contents($path); }
        };
    }

    protected function tearDown(): void {
        foreach ([$this->root . '/ghost/Frieren/stories.json', $this->root . '/dialogs/story.txt', $this->root . '/dialogs/story.json'] as $file) {
            if (file_exists($file)) unlink($file);
        }
        rmdir($this->root . '/ghost/Frieren');
        rmdir($this->root . '/ghost');
        rmdir($this->root . '/dialogs');
        rmdir($this->root);
        unset($GLOBALS['wp_filesystem'], $GLOBALS['_story_test_root']);
    }

    /** Fixed test catalog, independent of the stories authors ship. */
    private function catalog(): array {
        return [
            'format_version' => 1,
            'enabled'        => true,
            'config'         => [
                'start_chance'     => 0.05,
                'cooldown_minutes' => 360,
                'step_pause'       => ['base_ms' => 1200, 'per_char_ms' => 50, 'min_ms' => 1500, 'max_ms' => 4000],
            ],
            'stories'        => [[
                'id'    => 'treasure_chest',
                'title' => '宝箱の誘惑',
                'steps' => [
                    ['text' => 'この宝箱、何が入っているんだろう……', 'emotion' => 'thinking'],
                    ['text' => 'ミミックの可能性はあるけど……', 'emotion' => 'what'],
                    ['text' => 'でも、開けない理由にはならないよね。', 'emotion' => 'smirk'],
                ],
            ]],
        ];
    }

    public function test_shipped_catalog_is_disabled_and_valid_when_enabled(): void {
        $shipped = json_decode(file_get_contents(MPU_TESTS_ROOT . '/ghost/Frieren/stories.json'), true);
        $this->assertFalse($shipped['enabled']);
        $this->assertNull(mpu_normalize_personality_stories($shipped, 'Frieren'));
        $shipped['enabled'] = true;
        $this->assertCount(count($shipped['stories']), mpu_normalize_personality_stories($shipped, 'Frieren')['stories']);
    }

    public function test_public_shape_is_normalized(): void {
        $result = mpu_normalize_personality_stories($this->catalog(), 'Frieren');
        $this->assertSame(360, $result['config']['cooldown_minutes']);
        $this->assertSame('thinking.png', $result['stories'][0]['steps'][0]['emoji']);
        $this->assertNull($result['stories'][0]['steps'][1]['emoji']);
        $this->assertArrayNotHasKey('title', $result['stories'][0]);
        $this->assertCount(3, $result['stories'][0]['steps']);
    }

    public function test_real_loader_accepts_ghost_path_but_dialog_reader_does_not(): void {
        $path = $this->root . '/ghost/Frieren/stories.json';
        file_put_contents($path, json_encode($this->catalog()));
        $this->assertSame('Frieren', mpu_load_personality_stories('Frieren')['personality']);
        $this->assertSame('path_not_allowed', mpu_secure_file_read($path)->get_error_code());
        $this->assertNull(mpu_load_personality_stories('../Frieren'));
        $this->assertNull(mpu_load_personality_stories('missing'));
        $this->assertNull(mpu_load_personality_stories(null));
    }

    public function test_missing_and_bad_json_disable_story(): void {
        $this->assertNull(mpu_load_personality_stories('Frieren'));
        file_put_contents($this->root . '/ghost/Frieren/stories.json', '{broken');
        $this->assertNull(mpu_load_personality_stories('Frieren'));
    }

    /** @dataProvider invalidCatalogs */
    public function test_invalid_contract_is_disabled(array $replacement): void {
        $raw = array_replace_recursive($this->catalog(), $replacement);
        $this->assertNull(mpu_normalize_personality_stories($raw, 'Frieren'));
    }

    public function invalidCatalogs(): array {
        return [
            [['format_version' => 2]], [['format_version' => '1']], [['enabled' => 1]],
            [['config' => 'bad']], [['config' => null]], [['config' => ['step_pause' => null]]],
            [['config' => ['start_chance' => -1]]],
            [['config' => ['start_chance' => '0.05']]], [['config' => ['start_chance' => NAN]]],
            [['config' => ['cooldown_minutes' => 1441]]],
            [['config' => ['step_pause' => ['min_ms' => 5000, 'max_ms' => 4000]]]],
            [['config' => ['step_pause' => ['base_ms' => 0.5]]]],
        ];
    }

    public function test_defaults_and_probability_boundaries(): void {
        $raw = $this->catalog();
        unset($raw['config']);
        $this->assertSame(0.05, mpu_normalize_personality_stories($raw, 'Frieren')['config']['start_chance']);
        foreach ([0, 1] as $chance) {
            $raw['config']['start_chance'] = $chance;
            $this->assertSame($chance, mpu_normalize_personality_stories($raw, 'Frieren')['config']['start_chance']);
        }
    }

    public function test_bad_story_is_skipped_without_hiding_other_stories(): void {
        foreach (['<img onerror=alert(1)>', 'A&B', '>', '', '   ', str_repeat('あ', 121)] as $bad) {
            $raw = $this->catalog();
            $badStory = $raw['stories'][0];
            $badStory['id'] = 'bad';
            $badStory['steps'][0]['text'] = $bad;
            $raw['stories'][] = $badStory;
            $this->assertCount(1, mpu_normalize_personality_stories($raw, 'Frieren')['stories']);
        }
        $raw['stories'][1]['id'] = $raw['stories'][0]['id'];
        $this->assertNull(mpu_normalize_personality_stories($raw, 'Frieren'));
    }

    public function test_step_count_and_unicode_length_boundaries(): void {
        foreach ([1, 2, 5, 6] as $count) {
            $raw = $this->catalog();
            $raw['stories'][0]['steps'] = array_fill(0, $count, ['text' => str_repeat('🍰', 120), 'emotion' => '../smirk.png']);
            $result = mpu_normalize_personality_stories($raw, 'Frieren');
            if ($count === 2 || $count === 5) {
                $this->assertCount($count, $result['stories'][0]['steps']);
                $this->assertNull($result['stories'][0]['steps'][0]['emoji']);
            } else $this->assertNull($result);
        }
    }

    public function test_invalid_ids_and_array_shapes_fail_closed(): void {
        foreach (['../chest', 'Uppercase', '', str_repeat('a', 65)] as $id) {
            $raw = $this->catalog();
            $raw['stories'][0]['id'] = $id;
            $this->assertNull(mpu_normalize_personality_stories($raw, 'Frieren'));
        }
        $raw = $this->catalog();
        $raw['stories'] = ['not_a_list' => $raw['stories'][0]];
        $this->assertNull(mpu_normalize_personality_stories($raw, 'Frieren'));
        $raw = $this->catalog();
        $raw['stories'][0]['steps'][0]['emotion'] = 'THINKING';
        $this->assertSame('thinking.png', mpu_normalize_personality_stories($raw, 'Frieren')['stories'][0]['steps'][0]['emoji']);
    }

    public function test_dialog_response_adds_stories_and_keeps_empty_file_errors(): void {
        file_put_contents($this->root . '/ghost/Frieren/stories.json', json_encode($this->catalog()));
        $controller = new class extends MPU_REST_Dialog {
            protected function rate_limit(string $key, int $max, int $window): ?WP_REST_Response { return null; }
        };
        foreach (['story.json' => '{"messages":[]}', 'story.txt' => ''] as $filename => $content) {
            file_put_contents($this->root . '/dialogs/' . $filename, $content);
            $error = $controller->load_dialog(new WP_REST_Request(['file' => $filename, 'cur_num' => 'default_1']));
            $this->assertInstanceOf(WP_Error::class, $error);
            $this->assertSame(400, $error->get_error_data()['status']);
        }
        file_put_contents($this->root . '/dialogs/story.json', '{"messages":["fallback"]}');
        $response = $controller->load_dialog(new WP_REST_Request(['file' => 'story.json', 'cur_num' => 'default_1']))->get_data();
        $this->assertSame(['fallback'], $response['msg']);
        $this->assertSame('Frieren', $response['stories']['personality']);
    }
}
