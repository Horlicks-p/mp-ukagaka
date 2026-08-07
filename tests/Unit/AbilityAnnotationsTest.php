<?php

use PHPUnit\Framework\TestCase;

// wp_register_ability() を捕獲用スタブに差し替え、各 Ability クラスの register() が
// 実際に core へ渡す引数をそのまま検査する。
if (!function_exists('wp_register_ability')) {
    function wp_register_ability($name, $args) {
        $GLOBALS['_mpu_test_abilities'][$name] = $args;
        return true;
    }
}

// Wp_Bot_Blocker_Ability::register() は Bot Blocker の存在確認で早期返却するため、
// 登録経路を通すには最小のスタブが要る。
if (!function_exists('mpu_bb_ban_ip')) {
    function mpu_bb_ban_ip($ip) {
        return true;
    }
}

require_once MPU_TESTS_ROOT . '/includes/mcp-tools/abilities/class-ai-crawler-ability.php';
require_once MPU_TESTS_ROOT . '/includes/mcp-tools/abilities/class-visitor-pulse-ability.php';
require_once MPU_TESTS_ROOT . '/includes/mcp-tools/abilities/class-wp-bot-blocker-ability.php';
require_once MPU_TESTS_ROOT . '/includes/mcp-tools/abilities/class-wp-postviews-ability.php';

final class AbilityAnnotationsTest extends TestCase {
    /**
     * @return array<string, array> ability 名 => wp_register_ability() の引数.
     */
    private function registerAll(): array {
        $GLOBALS['_mpu_test_abilities'] = [];

        \MP_Ukagaka\McpTools\Abilities\AI_Crawler_Ability::register();
        \MP_Ukagaka\McpTools\Abilities\Visitor_Pulse_Ability::register();
        \MP_Ukagaka\McpTools\Abilities\Wp_Bot_Blocker_Ability::register();
        \MP_Ukagaka\McpTools\Abilities\Wp_PostViews_Ability::register();

        return $GLOBALS['_mpu_test_abilities'];
    }

    public function test_every_ability_declares_all_three_annotations(): void {
        $abilities = $this->registerAll();

        // 新しい ability を足したらこの件数も更新すること。件数を固定しておかないと、
        // 注釈なしの ability が「登録されていないから検査されない」形ですり抜ける。
        $this->assertCount(6, $abilities);

        foreach ($abilities as $name => $args) {
            $annotations = $args['meta']['annotations'] ?? null;

            // core は meta.annotations を実行時に検証せず、未指定は「挙動不明」として扱われる。
            // 呼び出し前に安全性を判断する消費側にとっては false より悪い信号なので、
            // 欠落そのものを不具合として扱う。
            $this->assertIsArray(
                $annotations,
                "{$name}: meta.annotations が宣言されていない"
            );

            foreach (['readonly', 'destructive', 'idempotent'] as $key) {
                $this->assertArrayHasKey($key, $annotations, "{$name}: {$key} が欠落");
                $this->assertIsBool($annotations[$key], "{$name}: {$key} は bool であること");
            }

            // readonly な ability が destructive を名乗るのは定義上ありえない。
            if (true === $annotations['readonly']) {
                $this->assertFalse(
                    $annotations['destructive'],
                    "{$name}: readonly なのに destructive"
                );
            }
        }
    }

    public function test_annotations_match_the_documented_behavior_of_each_ability(): void {
        $abilities = $this->registerAll();

        $expected = [
            // 読み取り専用のクエリ系。
            'mp-ukagaka/get-recent-ai-crawlers'    => [true, false, true],
            'mp-ukagaka/get-visitor-pulse'         => [true, false, true],
            'mp-ukagaka/get-popular-posts'         => [true, false, true],
            'mp-ukagaka/get-bot-blocker-stats'     => [true, false, true],
            // ban リストへの追加のみ。既に ban 済みなら早期返却するので idempotent。
            'mp-ukagaka/ban-ip'                    => [false, false, true],
            // 唯一の destructive。ban リストとログを実際に削除し、復元手段はない。
            'mp-ukagaka/clear-bot-blocker-data'    => [false, true, true],
        ];

        foreach ($expected as $name => [$readonly, $destructive, $idempotent]) {
            $this->assertArrayHasKey($name, $abilities, "{$name} が登録されていない");
            $this->assertSame(
                ['readonly' => $readonly, 'destructive' => $destructive, 'idempotent' => $idempotent],
                $abilities[$name]['meta']['annotations'],
                "{$name}: 注釈が想定と一致しない"
            );
        }
    }

    public function test_zero_arg_abilities_declare_a_top_level_schema_default(): void {
        $abilities = $this->registerAll();

        foreach ($abilities as $name => $args) {
            $schema = $args['input_schema'] ?? null;
            $this->assertIsArray($schema, "{$name}: input_schema がない");

            // required があるなら零引数呼び出しはそもそも不正なので default は不要。
            // required がない = 零引数を許す ability であり、間接パス（$ability->execute()
            // / REST / MCP）は null を渡してくる。null は object ではないので、頂層 default
            // がないと validate_input() が callback 到達前に ability_invalid_input で弾く。
            if (!empty($schema['required'])) {
                $this->assertArrayNotHasKey(
                    'default',
                    $schema,
                    "{$name}: required がある ability に零引数 default は矛盾"
                );
                continue;
            }

            $this->assertArrayHasKey(
                'default',
                $schema,
                "{$name}: 零引数を許すのに頂層 default がない"
            );
            $this->assertEquals(
                new \stdClass(),
                $schema['default'],
                "{$name}: 頂層 default は空オブジェクトであること（空配列だと JSON で [] になる）"
            );
        }
    }

    public function test_every_ability_gates_execution_behind_a_permission_callback(): void {
        $abilities = $this->registerAll();

        // 注釈は消費側への申告にすぎず、アクセス制御ではない。実際の権限は
        // permission_callback（MPU_Input_Role のホワイトリスト）が単独で担保する。
        foreach ($abilities as $name => $args) {
            $this->assertArrayHasKey('permission_callback', $args, "{$name}: permission_callback なし");
            $this->assertIsCallable($args['permission_callback'], "{$name}: permission_callback が callable でない");
        }

        // 非管理者は破壊的 ability に到達できないこと（ホワイトリストの回帰防止）。
        $GLOBALS['_mpu_test_current_user_can'] = false;
        foreach (['mp-ukagaka/ban-ip', 'mp-ukagaka/clear-bot-blocker-data'] as $name) {
            $this->assertFalse(
                MPU_Input_Role::can_use_ability($name, MPU_Input_Role::VISITOR),
                "{$name}: 訪客に開放されている"
            );
            $this->assertFalse(
                MPU_Input_Role::can_use_ability($name, MPU_Input_Role::SYSTEM),
                "{$name}: system 経路に開放されている"
            );
        }
    }
}
