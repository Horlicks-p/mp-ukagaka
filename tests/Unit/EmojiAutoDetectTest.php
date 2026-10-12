<?php
/**
 * Emoji tag auto-detection for personalities without an emoji block in the manifest.
 */

use PHPUnit\Framework\TestCase;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class EmojiAutoDetectTest extends TestCase {
    private string $root;

    protected function setUp(): void {
        $this->root = sys_get_temp_dir() . '/mpu-emoji-detect-' . uniqid();
        mkdir($this->root . '/Tester/emojis', 0777, true);
        foreach (['laugh.png', 'sad.gif', 'wink.apng', 'sleepy.png'] as $file) {
            file_put_contents($this->root . '/Tester/emojis/' . $file, 'x');
        }
        $GLOBALS['mpu_test_personalities_dir'] = $this->root;

        if (!function_exists('mpu_get_personalities_dir')) {
            function mpu_get_personalities_dir() {
                return $GLOBALS['mpu_test_personalities_dir'];
            }
        }
        if (!function_exists('mpu_sanitize_personality_id')) {
            function mpu_sanitize_personality_id($id) {
                return (string) $id;
            }
        }
        if (!function_exists('mpu_load_personality_manifest')) {
            function mpu_load_personality_manifest($personality_id = null) {
                return [];
            }
        }
        require_once MPU_TESTS_ROOT . '/includes/personality/personality-emoji.php';
    }

    protected function tearDown(): void {
        foreach (glob($this->root . '/Tester/emojis/*') as $file) {
            unlink($file);
        }
        rmdir($this->root . '/Tester/emojis');
        rmdir($this->root . '/Tester');
        rmdir($this->root);
    }

    public function test_only_png_files_become_tags(): void {
        $config = mpu_load_personality_emoji_config('Tester');

        $supported = $config['supported'];
        sort($supported);
        $this->assertSame(['laugh', 'sleepy'], $supported);
    }
}
