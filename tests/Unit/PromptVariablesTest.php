<?php

use PHPUnit\Framework\TestCase;

/**
 * The user chat path and the auto context/greet path must hand the same template
 * variables to mpu_resolve_system_prompt(); a placeholder that one path leaves out is
 * silently rendered as an empty string (#30).
 *
 * Tests run in separate processes for the same reasons as RestChatPrepareArgsTest, and
 * because the fixture case points MPU_MAIN_FILE (and so the ghost directory) elsewhere.
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

    private function request(array $params = []): WP_REST_Request {
        return new WP_REST_Request($params + ['cur_num' => 'default_1', 'session_id' => self::SESSION]);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_user_context_and_greet_paths_render_the_same_placeholders(): void {
        define('MPU_MAIN_FILE', MPU_TESTS_ROOT . '/tests/fixtures/prompt-variables/mp-ukagaka.php');
        $this->load([
            'ukagakas'        => ['default_1' => ['name' => 'Fixture', 'dialog_filename' => 'Fixture']],
            'cur_ukagaka'     => 'default_1',
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

        $expected = implode("\n", [
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
        ]);
        $this->assertStringContainsString($expected, $context['system_prompt']);
        $this->assertSame($context['system_prompt'], $greet['system_prompt']);
        // The user path puts the same resolved prompt first, then appends its own blocks.
        $this->assertStringStartsWith($context['system_prompt'] . "\n\n", $user['system_prompt']);

        $this->assertSame(
            'Greet Test Site (12 plugins, 345 visits)',
            mpu_render_prompt_template(mpu_get_option()['ai_greet_prompt'], $greet['variables'])
        );
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
}
