<?php
/**
 * Which character a visitor's AI request is about.
 */

use PHPUnit\Framework\TestCase;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class RequestUkagakaTest extends TestCase {
    private array $opt = [
        'cur_ukagaka' => 'default_1',
        'ukagakas'    => [
            'default_1' => ['name' => 'フリーレン', 'dialog_filename' => 'Frieren'],
            'asuna'     => ['name' => 'アスナ', 'dialog_filename' => 'Asuna'],
            'sakura'    => ['name' => 'サクラ', 'dialog_filename' => 'Sakura_Laurel'],
        ],
    ];

    protected function setUp(): void {
        if (!defined('COOKIEHASH')) {
            define('COOKIEHASH', 'testhash');
        }
        require_once MPU_TESTS_ROOT . '/includes/core/wp-info-functions.php';
        $_COOKIE = [];
    }

    public function test_valid_request_param_wins_over_cookie(): void {
        $_COOKIE['mpu_ukagaka_testhash'] = 'sakura';

        $this->assertSame('asuna', mpu_resolve_request_ukagaka_num('asuna', $this->opt));
    }

    public function test_invalid_param_falls_back_to_the_visitor_cookie(): void {
        $_COOKIE['mpu_ukagaka_testhash'] = 'sakura';

        $this->assertSame('sakura', mpu_resolve_request_ukagaka_num('nobody', $this->opt));
        $this->assertSame('sakura', mpu_resolve_request_ukagaka_num(null, $this->opt));
        $this->assertSame('sakura', mpu_resolve_request_ukagaka_num('', $this->opt));
    }

    public function test_invalid_cookie_falls_back_to_the_admin_character(): void {
        $_COOKIE['mpu_ukagaka_testhash'] = 'nobody';

        $this->assertSame('default_1', mpu_resolve_request_ukagaka_num('', $this->opt));
    }

    public function test_without_param_or_cookie_the_admin_character_is_used(): void {
        $opt = $this->opt;
        $opt['cur_ukagaka'] = 'asuna';

        $this->assertSame('asuna', mpu_resolve_request_ukagaka_num(null, $opt));
    }

    public function test_non_string_values_are_ignored(): void {
        $_COOKIE['mpu_ukagaka_testhash'] = ['asuna'];

        $this->assertSame('default_1', mpu_resolve_request_ukagaka_num(['asuna'], $this->opt));
    }
}
