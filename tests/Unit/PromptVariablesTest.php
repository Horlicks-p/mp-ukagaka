<?php

use PHPUnit\Framework\TestCase;

/**
 * Every path that resolves a character system prompt must hand it the same template
 * variables: user chat, auto context/greet (#30) and the touch/decoration/gift reaction
 * pipeline (#33). A placeholder that one path leaves out is silently rendered as an
 * empty string.
 *
 * Tests run in separate processes for the same reasons as RestChatPrepareArgsTest, and
 * because the fixture cases point MPU_MAIN_FILE (and so the ghost directory) elsewhere.
 */
final class PromptVariablesTest extends TestCase {
    private const SESSION = 'promptvariables01';

    /** The keys only the user chat path used to provide. */
    private const FORMERLY_USER_ONLY = [
        'is_child_theme',
        'parent_theme',
        'is_block_theme',
        'site_name',
        'site_description',
        'is_multisite',
        'active_plugins_count',
        'active_plugins_list',
        'slimstat_total_visits',
    ];

    private function load(array $mpu_opt = []): void {
        require_once MPU_TESTS_ROOT . '/tests/support/rest-chat-harness.php';

        update_option('mp_ukagaka', array_merge([
            'llm_provider'         => 'ollama',
            'ai_enabled'           => 1,
            'ai_greet_first_visit' => 1,
        ], $mpu_opt));
        $plugins = [];
        for ($i = 1; $i <= 12; $i++) {
            $plugins[] = sprintf('Plugin %02d', $i);
        }
        // Production cache-hit path of mpu_get_wordpress_info(); avoids $wpdb/get_plugins().
        set_transient('mpu_wordpress_info', [
            'wp_version'            => '6.4',
            'php_version'           => PHP_VERSION,
            'theme_name'            => 'Child Theme',
            'theme_version'         => '1.2',
            'theme_author'          => 'Someone',
            'is_child_theme'        => true,
            'parent_theme'          => 'parent-theme',
            'is_block_theme'        => true,
            'site_name'             => 'Test Site',
            'site_description'      => 'Just testing',
            'is_multisite'          => true,
            'active_plugins_count'  => 12,
            'active_plugins_list'   => $plugins,
            'post_count'            => 3,
            'comment_count'         => 2,
            'category_count'        => 1,
            'tag_count'             => 1,
            'days_operating'        => 10,
            'slimstat_total_visits' => 345,
        ]);
    }

    private function use_fixture_ghost(array $mpu_opt = []): void {
        define('MPU_MAIN_FILE', MPU_TESTS_ROOT . '/tests/fixtures/prompt-variables/mp-ukagaka.php');
        $this->load(array_merge([
            'ukagakas'    => ['default_1' => ['name' => 'Fixture', 'dialog_filename' => 'Fixture']],
            'cur_ukagaka' => 'default_1',
        ], $mpu_opt));
    }

    private function request(array $params = []): WP_REST_Request {
        return new WP_REST_Request($params + ['cur_num' => 'default_1', 'session_id' => self::SESSION]);
    }

    /**
     * Checks the fixture ghost's rendered lines. time_context depends on the clock, so
     * only its shape is checked.
     */
    private function assert_fixture_rendered(string $system_prompt): void {
        $this->assertMatchesRegularExpression('/^time_context=\d{4}年\d{1,2}月\d{1,2}日/mu', $system_prompt);
        $this->assertStringContainsString(implode("\n", [
            'wp_version=6.4',
            'php_version=' . PHP_VERSION,
            'theme_name=Child Theme',
            'theme_version=1.2',
            'theme_author=Someone',
            'is_child_theme=1',
            'parent_theme=parent-theme',
            'is_block_theme=1',
            'site_name=Test Site',
            'site_description=Just testing',
            'is_multisite=1',
            'active_plugins_count=12',
            // At most 10 names, joined with 、.
            'active_plugins_list=Plugin 01、Plugin 02、Plugin 03、Plugin 04、Plugin 05、Plugin 06、Plugin 07、Plugin 08、Plugin 09、Plugin 10',
            'slimstat_total_visits=345',
            'post_count=3',
            'comment_count=2',
            'category_count=1',
            'tag_count=1',
            'days_operating=10',
        ]), $system_prompt);
    }

    /**
     * Replaces both LLM entry points that MPU_REST_Touch::run_reaction() can take, records
     * the system prompt each receives, and stops the pipeline there with a WP_Error.
     * The harness loads neither ai-functions.php nor chat-api-handlers.php.
     */
    private function capture_ai_calls(): void {
        $GLOBALS['_mpu_test_ai_calls'] = [];
        function mpu_call_ai_api($provider, $api_key, $system_prompt, $user_prompt, $language, $mpu_opt = null, $max_tokens = null) {
            $GLOBALS['_mpu_test_ai_calls'][] = ['single', $system_prompt];
            return new WP_Error('captured', 'captured');
        }
        function mpu_call_ai_api_with_messages($provider, $api_key, $system_prompt, $messages, $language, $options = []) {
            $GLOBALS['_mpu_test_ai_calls'][] = ['messages', $system_prompt];
            return new WP_Error('captured', 'captured');
        }
    }

    /**
     * Runs the shared reaction pipeline and returns the [branch, system prompt] it sent.
     * run_reaction() is private; touch and decoration call it without messages, give with.
     */
    private function run_reaction(string $personality_id, ?array $messages): array {
        $before = count($GLOBALS['_mpu_test_ai_calls']);
        $method = new ReflectionMethod(MPU_REST_Touch::class, 'run_reaction');
        if (PHP_VERSION_ID < 80100) {
            // Needed before PHP 8.1; deprecated from 8.5, so only call it where it matters.
            $method->setAccessible(true);
        }
        $result = $method->invoke(new MPU_REST_Touch(), 'user prompt', $personality_id, 'touch', $messages);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('captured', $result->get_error_code());
        $this->assertCount($before + 1, $GLOBALS['_mpu_test_ai_calls']);
        return end($GLOBALS['_mpu_test_ai_calls']);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_user_context_and_greet_paths_render_the_same_placeholders(): void {
        $this->use_fixture_ghost([
            'ai_greet_prompt' => 'Greet {{site_name}} ({{active_plugins_count}} plugins, {{slimstat_total_visits}} visits)',
        ]);
        $controller = new MPU_Test_REST_Chat();

        $context = $controller->prepare_auto($this->request());
        $greet   = $controller->prepare_auto($this->request(), ['require_first_visit_greeting' => true]);
        $user    = $controller->prepare($this->request([
            'message'       => 'hello',
            'session_token' => mpu_generate_session_token(),
        ]));

        $this->assertIsArray($context);
        $this->assertIsArray($greet);
        $this->assertIsArray($user);
        $this->assertSame('Fixture', $context['personality_id'], 'the fixture ghost must be the one rendered');

        $this->assert_fixture_rendered($context['system_prompt']);
        $this->assertSame($context['system_prompt'], $greet['system_prompt']);
        // The user path puts the same resolved prompt first, then appends its own blocks.
        $this->assertStringStartsWith($context['system_prompt'] . "\n\n", $user['system_prompt']);

        $this->assertSame(
            'Greet Test Site (12 plugins, 345 visits)',
            mpu_render_prompt_template(mpu_get_option()['ai_greet_prompt'], $greet['variables'])
        );
    }

    /**
     * The backend default is character A, the request asks for B, and the language comes
     * from B's manifest (ai_language is empty).
     */
    private function use_non_default_character(): void {
        $this->use_fixture_ghost([
            'ukagakas'    => [
                'default_1' => ['name' => 'Alpha', 'dialog_filename' => 'Fixture'],
                'default_2' => ['name' => 'Beta', 'dialog_filename' => 'FixtureB'],
            ],
            'cur_ukagaka' => 'default_1',
            'ai_language' => '',
        ]);
    }

    /**
     * Checks B's personality, language and time context, not just its display name.
     */
    private function assert_resolves_character_b(array $args): void {
        $this->assertSame('default_2', $args['ukagaka_name']);
        $this->assertSame('Beta', $args['ukagaka_display_name']);
        $this->assertSame('FixtureB', $args['personality_id']);
        $this->assertSame('en', $args['language'], 'the code must come from FixtureB\'s manifest');
        $this->assertStringStartsWith("FixtureB prompt for Beta.\nlanguage=English\ntime_context=", $args['system_prompt']);
        $this->assertStringContainsString('【FixtureB Season】', $args['system_prompt']);
    }

    /**
     * time_context carries the current hour and minute, which may tick between two calls.
     */
    private function without_clock(string $prompt): string {
        return preg_replace('/\d{1,2}時\d{1,2}分/u', 'H時M分', $prompt);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_auto_path_resolves_the_requested_non_default_character(): void {
        $this->use_non_default_character();

        $auto = (new MPU_Test_REST_Chat())->prepare_auto($this->request(['cur_num' => 'default_2']));

        $this->assertIsArray($auto);
        $this->assert_resolves_character_b($auto);
        $this->assertSame('Beta', $auto['variables']['ukagaka_display_name']);
        $this->assertSame('English', $auto['variables']['language']);
        $this->assertStringEndsWith('【FixtureB Season】', $auto['variables']['time_context']);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_user_path_resolves_the_requested_non_default_character(): void {
        $this->use_non_default_character();
        $controller = new MPU_Test_REST_Chat();

        $user = $controller->prepare($this->request([
            'cur_num'       => 'default_2',
            'message'       => 'hello',
            'session_token' => mpu_generate_session_token(),
        ]));

        $this->assertIsArray($user);
        $this->assert_resolves_character_b($user);

        // The user prompt starts with the same base prompt the auto path resolves.
        $auto = $controller->prepare_auto($this->request(['cur_num' => 'default_2']));
        $this->assertIsArray($auto);
        $this->assertStringStartsWith(
            $this->without_clock($auto['system_prompt']) . "\n\n",
            $this->without_clock($user['system_prompt'])
        );
    }

    /**
     * Touch and decoration reach mpu_call_ai_api(); give passes its conversation and
     * reaches mpu_call_ai_api_with_messages(). Both must send the chat path's prompt.
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_reaction_branches_render_the_same_placeholders_as_chat(): void {
        $this->use_fixture_ghost();
        $this->capture_ai_calls();
        $context = (new MPU_Test_REST_Chat())->prepare_auto($this->request());
        $this->assertIsArray($context);

        [$single_branch, $single_prompt]     = $this->run_reaction('Fixture', null);
        [$messages_branch, $messages_prompt] = $this->run_reaction('Fixture', [['role' => 'user', 'content' => 'here']]);

        $this->assertSame('single', $single_branch);
        $this->assertSame('messages', $messages_branch);
        $this->assert_fixture_rendered($single_prompt);
        $this->assertSame($context['system_prompt'], $single_prompt);
        $this->assertSame($context['system_prompt'], $messages_prompt);
    }

    /**
     * The 9 keys are new to the context/greet path; no built-in ghost references them,
     * so its rendered prompt must not change.
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_built_in_ghost_prompts_do_not_use_the_formerly_user_only_keys(): void {
        $this->load();
        $variables = (new MPU_Test_REST_Chat())->prepare_auto($this->request())['variables'];
        foreach (self::FORMERLY_USER_ONLY as $key) {
            $this->assertArrayHasKey($key, $variables);
        }
        $without = array_diff_key($variables, array_flip(self::FORMERLY_USER_ONLY));

        $ghosts = array_keys(mpu_get_available_personalities());
        $this->assertNotEmpty($ghosts);
        $mpu_opt = mpu_get_option();
        foreach ($ghosts as $ghost) {
            $this->assertSame(
                mpu_resolve_system_prompt($ghost, $mpu_opt, 'Name', $without),
                mpu_resolve_system_prompt($ghost, $mpu_opt, 'Name', $variables),
                "{$ghost} prompt changed"
            );
        }
    }

    /**
     * The admin's name and birthday only reach the system prompt while the admin is the
     * listener. A guest must never get them, or the model greets the guest by that name.
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_admin_profile_reaches_the_prompt_only_for_the_admin(): void {
        $this->load([
            'admin_nickname' => 'テスト管理ニック',
            'admin_name'     => 'テスト管理ネーム',
            'admin_birthday' => '10-18',
        ]);
        function get_user_meta($user_id, $key = '', $single = false) {
            return '';
        }
        $mpu_opt = mpu_get_option();

        foreach ([[false, false], [true, false]] as [$logged_in, $can_manage]) {
            $GLOBALS['_mpu_test_is_user_logged_in'] = $logged_in;
            $GLOBALS['_mpu_test_current_user_can']  = $can_manage;
            $prompt = mpu_resolve_system_prompt('Frieren', $mpu_opt, 'フリーレン');
            $this->assertStringNotContainsString('テスト管理ニック', $prompt);
            $this->assertStringNotContainsString('テスト管理ネーム', $prompt);
            $this->assertStringNotContainsString('10-18', $prompt);

            $user_block = mpu_build_user_info_prompt(mpu_get_current_user_info());
            $this->assertStringContainsString(mpu_get_non_admin_audience_instruction(), $user_block);
        }

        $GLOBALS['_mpu_test_is_user_logged_in'] = true;
        $GLOBALS['_mpu_test_current_user_can']  = true;
        $prompt = mpu_resolve_system_prompt('Frieren', $mpu_opt, 'フリーレン');
        $this->assertStringContainsString('テスト管理ニック', $prompt);
        $this->assertStringContainsString('テスト管理ネーム', $prompt);
        $this->assertStringContainsString('10-18', $prompt);
    }

    /**
     * The admin birthday reaches time_context through the calendar, so the calendar is
     * gated on the same audience check.
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_admin_birthday_stays_out_of_the_calendar_for_guests(): void {
        $this->load(['admin_birthday' => '10-18']);
        $mpu_opt  = mpu_get_option();
        $calendar = [
            'anniversaries' => [
                // calendar.json に残っている旧来の誕生日エントリも対象.
                'MM-DD' => ['name' => '管理人の誕生日', 'prompts' => ['anniversary_admin_birthday']],
                '04-01' => ['name' => '別の記念日', 'prompts' => ['holiday_aprilfool']],
            ],
        ];

        $guest = mpu_apply_admin_profile_calendar($calendar, $mpu_opt, false);
        $this->assertSame(['04-01'], array_keys($guest['anniversaries']));

        $admin = mpu_apply_admin_profile_calendar($calendar, $mpu_opt, true);
        $this->assertSame(['04-01', '10-18'], array_keys($admin['anniversaries']));
        $this->assertSame(['anniversary_admin_birthday'], $admin['anniversaries']['10-18']['prompts']);

        // 誕生日未設定の管理人には calendar.json のまま渡す（従来どおり）.
        $unset = mpu_apply_admin_profile_calendar($calendar, ['admin_birthday' => ''], true);
        $this->assertSame($calendar, $unset);

        // 引数を省略すると現在のユーザーから判定する.
        $GLOBALS['_mpu_test_is_user_logged_in'] = false;
        $GLOBALS['_mpu_test_current_user_can']  = false;
        $this->assertSame(['04-01'], array_keys(mpu_apply_admin_profile_calendar($calendar, $mpu_opt)['anniversaries']));
    }

    /**
     * The reaction pipeline used to call mpu_resolve_system_prompt() without variables.
     * No built-in ghost references the ones it now passes, so its prompt is unchanged.
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_reaction_prompt_for_built_in_ghosts_is_unchanged(): void {
        $this->load();
        $this->capture_ai_calls();
        $mpu_opt = mpu_get_option();
        // run_reaction() names the backend character, as before.
        $name = $mpu_opt['ukagakas'][$mpu_opt['cur_ukagaka']]['name'] ?? 'キャラクター';

        $ghosts = array_keys(mpu_get_available_personalities());
        $this->assertNotEmpty($ghosts);
        foreach ($ghosts as $ghost) {
            [, $prompt] = $this->run_reaction($ghost, null);
            $this->assertSame(
                mpu_resolve_system_prompt($ghost, $mpu_opt, $name),
                $prompt,
                "{$ghost} reaction prompt changed"
            );
        }
    }
}
