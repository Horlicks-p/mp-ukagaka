<?php

use PHPUnit\Framework\TestCase;

final class DialogThemeTest extends TestCase {
    private function load(): void {
        if (!function_exists('plugins_url')) {
            function plugins_url($path = '', $plugin = '') {
                return 'https://example.test/wp-content/plugins/mp-ukagaka/' . ltrim((string) $path, '/');
            }
        }
        if (!function_exists('wp_kses')) {
            function wp_kses($value, $allowed) {
                return $value;
            }
        }
        if (!function_exists('wp_kses_post')) {
            function wp_kses_post($value) {
                return $value;
            }
        }
        if (!function_exists('sanitize_hex_color')) {
            function sanitize_hex_color($value) {
                return $value;
            }
        }

        require_once MPU_TESTS_ROOT . '/includes/core/core-functions.php';
        require_once MPU_TESTS_ROOT . '/includes/personality/personality-loader.php';
        require_once MPU_TESTS_ROOT . '/includes/admin-functions.php';
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_sanitizer_keeps_known_slugs_and_falls_back_to_default(): void {
        $this->load();

        foreach (['default', 'sapphire', 'crimson', 'forest'] as $slug) {
            $this->assertSame($slug, mpu_sanitize_dialog_theme($slug));
        }
        $this->assertSame('sapphire', mpu_sanitize_dialog_theme(' Sapphire '));

        foreach (['evil/path', '../../x', 'unknown', '', null, ['forest'], 3] as $bad) {
            $this->assertSame('default', mpu_sanitize_dialog_theme($bad));
        }
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_old_installs_and_bad_saved_values_render_default(): void {
        $this->load();

        $this->assertSame('default', mpu_default_opt()['dialog_theme']);

        $merged = mpu_merge_option_defaults(['cur_ukagaka' => 'default_1'], mpu_default_opt());
        $this->assertSame('default', mpu_get_dialog_theme($merged));

        $this->assertSame('default', mpu_get_dialog_theme(['dialog_theme' => '"><script>']));
        $this->assertSame('forest', mpu_get_dialog_theme(['dialog_theme' => 'forest']));

        $options = ['dialog_theme' => 'crimson'];
        mpu_reset_options($options);
        $this->assertSame('default', $options['dialog_theme']);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_general_save_validates_the_posted_theme(): void {
        $this->load();

        $_POST = ['dialog_theme' => 'sapphire'];
        $options = mpu_default_opt();
        mpu_save_general_settings($options);
        $this->assertSame('sapphire', $options['dialog_theme']);

        $_POST = ['dialog_theme' => '../../x'];
        mpu_save_general_settings($options);
        $this->assertSame('default', $options['dialog_theme']);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_saving_the_ai_page_keeps_the_theme(): void {
        $this->load();

        $GLOBALS['_mpu_test_options']['mp_ukagaka'] = array_merge(mpu_default_opt(), ['dialog_theme' => 'forest']);
        $options = mpu_get_option();

        $_POST = ['ai_probability' => '20'];
        mpu_save_ai_settings($options);

        $this->assertSame('forest', $options['dialog_theme']);
    }
}
