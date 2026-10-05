<?php

use PHPUnit\Framework\TestCase;

require_once MPU_TESTS_ROOT . '/includes/core/ukagaka-functions.php';

final class ShellImageListTest extends TestCase {
    private $dir;

    protected function setUp(): void {
        $this->dir = sys_get_temp_dir() . '/mpu-shell-' . uniqid();
        mkdir($this->dir);
    }

    protected function tearDown(): void {
        foreach ((array) scandir($this->dir) as $entry) {
            $path = $this->dir . '/' . $entry;
            if (is_file($path)) {
                unlink($path);
            } elseif ('.' !== $entry && '..' !== $entry && is_dir($path)) {
                rmdir($path);
            }
        }
        rmdir($this->dir);
    }

    private function touch(array $names): void {
        foreach ($names as $name) {
            file_put_contents($this->dir . '/' . $name, 'x');
        }
    }

    public function test_raster_shell_keeps_natural_order(): void {
        $this->touch(['shell10.png', 'shell2.png', 'shell1.png', 'notes.txt']);

        $this->assertSame(['shell1.png', 'shell2.png', 'shell10.png'], mpu_list_shell_images($this->dir));
    }

    public function test_svg_is_listed_and_preferred_over_same_basename_raster(): void {
        $this->touch(['a1.png', 'a1.svg', 'a2.svg', 'a10.webp']);

        $this->assertSame(['a1.svg', 'a2.svg', 'a10.webp'], mpu_list_shell_images($this->dir));
    }

    public function test_same_basename_raster_formats_are_preserved(): void {
        $this->touch(['a1.png', 'a1.webp', 'a1.gif', 'a2.png']);

        $this->assertSame(['a1.gif', 'a1.png', 'a1.webp', 'a2.png'], mpu_list_shell_images($this->dir));
    }

    public function test_svg_replaces_same_basename_rasters(): void {
        $this->touch(['a1.png', 'a1.webp', 'a1.jpg', 'a1.svg', 'a2.png']);

        $this->assertSame(['a1.svg', 'a2.png'], mpu_list_shell_images($this->dir));
    }

    public function test_natural_order_across_formats(): void {
        $this->touch(['frame10.png', 'frame2.png', 'frame1.webp']);

        $this->assertSame(['frame1.webp', 'frame2.png', 'frame10.png'], mpu_list_shell_images($this->dir));
    }

    public function test_subfolders_and_missing_dir_are_ignored(): void {
        mkdir($this->dir . '/idle');
        $this->touch(['assets.json']);

        $this->assertSame([], mpu_list_shell_images($this->dir));
        $this->assertSame([], mpu_list_shell_images($this->dir . '/missing'));
    }
}
