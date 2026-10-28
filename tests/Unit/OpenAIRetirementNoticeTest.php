<?php

use PHPUnit\Framework\TestCase;

/**
 * Admin notice for OpenAI models retiring on 2027-01-14.
 *
 * Each test runs in its own process: mpu_get_option() caches the merged options in a
 * static, and the notice must be judged on what the real merge produces.
 */
final class OpenAIRetirementNoticeTest extends TestCase {
    private function load(?array $stored, bool $is_admin = true): void {
        if (!function_exists('plugins_url')) {
            function plugins_url($path = '', $plugin = '') {
                return 'https://example.test/wp-content/plugins/mp-ukagaka/' . ltrim((string) $path, '/');
            }
        }
        if (!function_exists('admin_url')) {
            function admin_url($path = '') {
                return 'https://example.test/wp-admin/' . ltrim((string) $path, '/');
            }
        }
        if (!function_exists('esc_html')) {
            function esc_html($text) {
                return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
            }
        }
        if (!function_exists('esc_html__')) {
            function esc_html__($text, $domain = 'default') {
                return esc_html($text);
            }
        }
        if (!function_exists('esc_url')) {
            function esc_url($url) {
                return htmlspecialchars((string) $url, ENT_QUOTES, 'UTF-8');
            }
        }

        require_once MPU_TESTS_ROOT . '/includes/core/core-functions.php';
        require_once MPU_TESTS_ROOT . '/includes/admin-functions.php';

        if ($stored !== null) {
            $GLOBALS['_mpu_test_options']['mp_ukagaka'] = $stored;
        }
        $GLOBALS['_mpu_test_current_user_can'] = $is_admin;
    }

    private function notice(): string {
        ob_start();
        mpu_render_openai_retirement_notice();
        return (string) ob_get_clean();
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_retiring_list_is_exact_ids_and_fine_tunes_of_them(): void {
        require_once MPU_TESTS_ROOT . '/includes/admin-functions.php';

        foreach (['gpt-4o', 'gpt-4o-2024-08-06', 'gpt-4o-2024-11-20', 'gpt-4o-mini', 'gpt-4o-mini-2024-07-18',
            'gpt-4.1', 'gpt-4.1-2025-04-14', 'gpt-4.1-mini', 'gpt-4.1-mini-2025-04-14',
            ' GPT-4.1-Mini ', 'ft:gpt-4o-mini-2024-07-18:acme::abc123'] as $model) {
            $this->assertTrue(mpu_is_retiring_openai_model($model), $model);
        }
        foreach (['gpt-6-luna', 'gpt-5.6-luna', 'gpt-4.1-nano', 'gpt-4.1-2099-01-01', 'gpt-4o-audio-preview',
            'gpt-4', 'ft:gpt-6-luna:acme::abc', 'ft:', 'my-gpt-4o', ''] as $model) {
            $this->assertFalse(mpu_is_retiring_openai_model($model), $model);
        }
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_legacy_site_without_saved_model_sees_the_notice(): void {
        // 沒存過 llm_openai_model 的舊網站，相容邏輯會讓它實際跑 gpt-4.1-mini-2025-04-14.
        $this->load(['llm_provider' => 'openai', 'llm_openai_api_key' => 'x']);

        $html = $this->notice();

        $this->assertStringContainsString('2027-01-14', $html);
        $this->assertStringContainsString('gpt-6-luna', $html);
        $this->assertStringContainsString('<code>gpt-4.1-mini-2025-04-14</code>', $html);
        $this->assertStringContainsString('cur_page=6', $html);
        $this->assertStringNotContainsString('cur_page=7', $html);
        $this->assertStringNotContainsString('is-dismissible', $html);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_main_and_diary_hits_share_one_notice(): void {
        $this->load([
            'llm_provider'       => 'openai',
            'llm_openai_model'   => 'gpt-4o',
            'diary_enabled'      => true,
            'diary_provider'     => 'openai',
            'diary_openai_model' => 'gpt-4o-mini',
        ]);

        $html = $this->notice();

        $this->assertSame(1, substr_count($html, 'class="notice notice-warning"'));
        $this->assertStringContainsString('cur_page=6', $html);
        $this->assertStringContainsString('<code>gpt-4o</code>', $html);
        $this->assertStringContainsString('cur_page=7', $html);
        $this->assertStringContainsString('<code>gpt-4o-mini</code>', $html);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_diary_only_counts_when_enabled_and_on_openai(): void {
        $this->load([
            'llm_provider'       => 'gemini',
            'llm_openai_model'   => 'gpt-4o',
            'diary_enabled'      => false,
            'diary_provider'     => 'openai',
            'diary_openai_model' => 'gpt-4o',
        ]);
        $this->assertSame([], mpu_get_retiring_openai_model_settings(mpu_get_option()));

        $opt = mpu_get_option();
        $opt['diary_enabled']  = true;
        $opt['diary_provider'] = 'claude';
        $this->assertSame([], mpu_get_retiring_openai_model_settings($opt));

        $opt['diary_provider'] = 'openai';
        $this->assertSame(['gpt-4o'], array_column(mpu_get_retiring_openai_model_settings($opt), 'model'));
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_supported_model_shows_nothing(): void {
        $this->load([
            'llm_provider'       => 'openai',
            'llm_openai_model'   => 'gpt-6-luna',
            'diary_enabled'      => true,
            'diary_provider'     => 'openai',
            'diary_openai_model' => 'gpt-6-luna',
        ]);

        $this->assertSame('', $this->notice());
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_non_admin_sees_nothing(): void {
        $this->load(['llm_provider' => 'openai', 'llm_openai_model' => 'gpt-4o'], false);

        $this->assertSame('', $this->notice());
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_custom_model_name_is_escaped(): void {
        // 自訂模型欄位是任意文字；判斷只認精確 ID，但輸出一律 escape.
        $this->load([
            'llm_provider'       => 'openai',
            'llm_openai_model'   => 'gpt-6-luna',
            'diary_enabled'      => true,
            'diary_provider'     => 'openai',
            'diary_openai_model' => 'ft:gpt-4o-2024-08-06:<b>x</b>::1',
        ]);

        $html = $this->notice();

        $this->assertStringContainsString('<code>ft:gpt-4o-2024-08-06:&lt;b&gt;x&lt;/b&gt;::1</code>', $html);
        $this->assertStringNotContainsString('<b>x</b>', $html);
    }
}
