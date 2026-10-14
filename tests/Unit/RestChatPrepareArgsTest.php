<?php

use PHPUnit\Framework\TestCase;

/**
 * Characterization tests for MPU_REST_Chat::prepare_user_chat_args().
 *
 * These pin the behavior that exists today (early-return order, lock ownership,
 * full integrity history vs. 20-entry LLM context, Observation drain timing).
 * They describe current behavior, not an ideal one.
 *
 * Every test runs in a separate process: the controller needs the real
 * core-functions.php / personality-loader.php, which would redeclare functions
 * that other test files stub at file scope, and mpu_get_option() caches statically.
 */
final class RestChatPrepareArgsTest extends TestCase {
    private const SESSION = 'restchatprepare01';

    /** @var MPU_Test_Integrity_Log_Guard|null */
    private $log_guard;

    private function load(array $mpu_opt = []): void {
        require_once MPU_TESTS_ROOT . '/tests/support/rest-chat-harness.php';

        // Without these the controller would silently take function_exists()/class_exists()
        // fallbacks that production never takes.
        foreach (['MPU_Chat_Lock', 'MPU_Chat_History_Service', 'MPU_Input_Role', 'MPU_Observation_Buffer'] as $class) {
            $this->assertTrue(class_exists($class), "{$class} must be the real class");
        }
        foreach ([
            'mpu_set_request_input_context',
            'mpu_resolve_personality_id',
            'mpu_resolve_language_code',
            'mpu_resolve_language',
            'mpu_resolve_system_prompt',
            'mpu_load_personality_manifest',
            'mpu_load_personality_dynamic_prompts',
            'mpu_get_client_ip_strict',
        ] as $function) {
            $this->assertTrue(function_exists($function), "{$function} must be loaded");
        }

        update_option('mp_ukagaka', array_merge(['llm_provider' => 'ollama'], $mpu_opt));
        // Production cache-hit path of mpu_get_wordpress_info(); avoids $wpdb/get_plugins().
        set_transient('mpu_wordpress_info', [
            'wp_version'     => '6.4',
            'php_version'    => PHP_VERSION,
            'theme_name'     => 'Test Theme',
            'post_count'     => 3,
            'comment_count'  => 2,
            'category_count' => 1,
            'tag_count'      => 1,
            'days_operating' => 10,
        ]);

        $this->log_guard = new MPU_Test_Integrity_Log_Guard();
    }

    protected function tearDown(): void {
        if ($this->log_guard !== null) {
            $problems = $this->log_guard->restore();
            $this->log_guard = null;
            $this->assertSame([], $problems, 'checksum-mismatch log was not restored cleanly');
        }
    }

    private function controller(): MPU_Test_REST_Chat {
        return new MPU_Test_REST_Chat();
    }

    private function request(array $params, array $headers = []): WP_REST_Request {
        return new WP_REST_Request($params + ['session_id' => self::SESSION], $headers);
    }

    private function token(): string {
        return mpu_generate_session_token();
    }

    private function use_integrity_mode(string $mode): void {
        add_filter('mpu_chat_integrity_mode', static function () use ($mode) {
            return $mode;
        });
    }

    /**
     * 26 entries. The 8 checksum-bearing chat replies all sit in the first 16 entries, and the
     * last 10 are synthetic/auto_talk/give pairs, so the last 20 entries contain only 5 chat
     * replies. Slicing to 20 before the checksum therefore changes the checksum.
     */
    private function long_history(): array {
        $history = [];
        for ($i = 1; $i <= 8; $i++) {
            $history[] = ['role' => 'user', 'content' => "question {$i}", 'type' => 'chat'];
            $history[] = ['role' => 'assistant', 'content' => "reply {$i}", 'type' => 'chat'];
        }
        foreach (['auto_talk', 'give', 'auto_talk', 'give', 'auto_talk'] as $i => $type) {
            $history[] = ['role' => 'user', 'content' => "anchor {$i}", 'type' => 'synthetic'];
            $history[] = ['role' => 'assistant', 'content' => "{$type} line {$i}", 'type' => $type];
        }
        return $history;
    }

    private function store_baseline_checksum(array $history): void {
        $normalized = MPU_Chat_History_Service::filter_orphan_assistants(
            MPU_Chat_History_Service::normalize_history($history)
        );
        $this->assertTrue(mpu_chat_integrity_store_history(
            self::SESSION,
            mpu_chat_integrity_slice_for_store($normalized, 10)
        ));
    }

    private function assert_not_locked(): void {
        $this->assertFalse(MPU_Chat_Lock::is_locked(self::SESSION), 'no chat lock may be held');
    }

    /**
     * Runs prepare() expecting it to throw, then proves the lock had been taken first,
     * so a throw that happens before acquire() cannot pass vacuously.
     *
     * Takes params rather than a request object: a failure trace carrying the request stub
     * cannot be unserialized by the parent PHPUnit process, which hides the real failure.
     */
    private function assert_throw_after_acquire_releases_lock(array $params, string $message): void {
        // PHPUnit's own assertion failures extend RuntimeException, so record the
        // caught exception instead of calling fail() inside the try.
        $thrown = null;
        try {
            $this->controller()->prepare($this->request($params));
        } catch (RuntimeException $e) {
            $thrown = $e->getMessage();
        }
        $this->assertSame($message, $thrown, 'prepare() was expected to rethrow');

        $acquired = array_values(array_filter($GLOBALS['_mpu_test_action_log'], static function ($entry) {
            return $entry[0] === 'mpu_chat_lock_acquired' && $entry[1][0] === self::SESSION;
        }));
        $this->assertCount(1, $acquired, 'the lock must have been acquired before the throw');
        $this->assert_not_locked();
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_missing_session_token_returns_403_response_before_lock(): void {
        $this->load();

        $result = $this->controller()->prepare($this->request(['message' => 'hello']));

        $this->assertInstanceOf(WP_REST_Response::class, $result);
        $this->assertSame(403, $result->get_status());
        $this->assertSame('missing_session_token', $result->get_data()['code']);
        $this->assert_not_locked();
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_rate_limit_returns_429_response_before_lock(): void {
        $this->load();
        $token = $this->token();
        set_transient('mpu_rl_user_chat_' . md5(mpu_get_client_ip_strict()), [
            'count'         => 30,
            'first_request' => time(),
        ]);

        $result = $this->controller()->prepare($this->request(['message' => 'hello', 'session_token' => $token]));

        $this->assertInstanceOf(WP_REST_Response::class, $result);
        $this->assertSame(429, $result->get_status());
        $this->assertSame('rest_rate_limit_exceeded', $result->get_data()['code']);
        $this->assert_not_locked();
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_empty_message_returns_400_error_before_lock(): void {
        $this->load();

        $result = $this->controller()->prepare($this->request(['message' => '', 'session_token' => $this->token()]));

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('rest_error', $result->get_error_code());
        $this->assertSame(400, $result->get_error_data()['status']);
        $this->assert_not_locked();
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_integrity_uses_full_history_while_llm_context_keeps_last_20(): void {
        $this->load();
        $this->use_integrity_mode('block');
        $history = $this->long_history();
        $this->store_baseline_checksum($history);

        $args = $this->controller()->prepare($this->request([
            'message'       => 'hello',
            'session_token' => $this->token(),
            'history'       => wp_json_encode($history),
        ]));

        $this->assertIsArray($args, 'matching checksum over the full history must pass in block mode');
        $this->assertCount(26, $args['integrity_history']);
        $this->assertCount(20, $args['chat_history']);
        $this->assertSame(array_slice($args['integrity_history'], -20), $args['chat_history']);
        // LLM messages = 20 context entries + the new user message.
        $this->assertCount(21, $args['messages']);
        $this->assertSame(['role' => 'user', 'content' => 'hello'], end($args['messages']));
        $this->assertNotNull($args['chat_lock']);
        $this->assertTrue(MPU_Chat_Lock::is_locked(self::SESSION));
        $this->assertSame('', $this->log_guard->appended_log());
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_block_mode_mismatch_returns_409_and_releases_lock(): void {
        $this->load();
        $this->use_integrity_mode('block');
        $history = $this->long_history();
        $this->store_baseline_checksum($history);
        // Tamper with the oldest reply: outside the 20-entry LLM window, inside the checksum.
        $history[1]['content'] = 'reply 1 (edited)';

        $result = $this->controller()->prepare($this->request([
            'message'       => 'hello',
            'session_token' => $this->token(),
            'history'       => wp_json_encode($history),
        ]));

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('rest_error', $result->get_error_code());
        $this->assertSame(409, $result->get_error_data()['status']);
        $this->assert_not_locked();
        $this->assertStringContainsString(self::SESSION, $this->log_guard->appended_log());
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_audit_mode_mismatch_continues_and_keeps_lock_for_caller(): void {
        $this->load();
        $this->use_integrity_mode('audit');
        $history = $this->long_history();
        $this->store_baseline_checksum($history);
        $history[1]['content'] = 'reply 1 (edited)';

        $args = $this->controller()->prepare($this->request([
            'message'       => 'hello',
            'session_token' => $this->token(),
            'history'       => wp_json_encode($history),
        ]));

        $this->assertIsArray($args);
        $this->assertNotNull($args['chat_lock']);
        $this->assertTrue(MPU_Chat_Lock::is_locked(self::SESSION));

        $mismatches = array_values(array_filter($GLOBALS['_mpu_test_action_log'], static function ($entry) {
            return $entry[0] === 'mpu_chat_integrity_mismatch';
        }));
        $this->assertCount(1, $mismatches);
        $this->assertSame('audit', $mismatches[0][1][0]['decision']);
        $this->assertNotSame('', $this->log_guard->appended_log());
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_busy_lock_returns_429_and_leaves_other_owner_lock(): void {
        $this->load();
        $owner = MPU_Chat_Lock::acquire(self::SESSION, ['route' => 'user']);
        $this->assertIsArray($owner);
        $stored = get_option(MPU_Chat_Lock::option_key(self::SESSION));

        $result = $this->controller()->prepare($this->request(['message' => 'hello', 'session_token' => $this->token()]));

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame(429, $result->get_error_data()['status']);
        $this->assertSame($stored, get_option(MPU_Chat_Lock::option_key(self::SESSION)));
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_missing_api_key_returns_400_releases_lock_and_keeps_observations(): void {
        $this->load(['llm_provider' => 'gemini']);
        $token = $this->token();
        $this->assertTrue(MPU_Observation_Buffer::push('lifecycle_event', 'wake', $token));

        $result = $this->controller()->prepare($this->request(['message' => 'hello', 'session_token' => $token]));

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame(400, $result->get_error_data()['status']);
        $this->assert_not_locked();
        // drain() runs after the API-key check, so an early return leaves the buffer alone.
        $this->assertCount(1, MPU_Observation_Buffer::peek($token));
    }

    /**
     * Throw right after acquire(): the integrity mode filter runs inside verify() on a mismatch.
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_throw_during_integrity_check_releases_lock(): void {
        $this->load();
        $history = $this->long_history();
        $this->store_baseline_checksum($history);
        $history[1]['content'] = 'reply 1 (edited)';
        add_filter('mpu_chat_integrity_mode', static function () {
            throw new RuntimeException('integrity boom');
        });

        $this->assert_throw_after_acquire_releases_lock([
            'message'       => 'hello',
            'session_token' => $this->token(),
            'history'       => wp_json_encode($history),
        ], 'integrity boom');
    }

    /**
     * Throw late in prompt building, after the API-key check: mpu_get_current_user_info()
     * is the only caller of wp_get_current_user() on this path, and only for a logged-in
     * user. Defining it before load() replaces the harness stub, which is
     * function_exists()-guarded.
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_throw_during_prompt_building_releases_lock(): void {
        $this->assertFalse(function_exists('wp_get_current_user'));
        function wp_get_current_user() {
            throw new RuntimeException('prompt boom');
        }
        $this->load();
        $GLOBALS['_mpu_test_is_user_logged_in'] = true;

        $this->assert_throw_after_acquire_releases_lock(
            ['message' => 'hello', 'session_token' => $this->token()],
            'prompt boom'
        );
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_success_hands_lock_to_caller_and_drains_observations_into_prompt(): void {
        $this->load();
        $token = $this->token();
        $this->assertTrue(MPU_Observation_Buffer::push('lifecycle_event', 'wake', $token));
        $expected_block = MPU_Observation_Buffer::format_prompt_block(MPU_Observation_Buffer::peek($token));
        $this->assertNotSame('', $expected_block);

        $args = $this->controller()->prepare($this->request(['message' => 'hello', 'session_token' => $token]));

        $this->assertIsArray($args);
        $this->assertSame('ollama', $args['provider']);
        $this->assertSame(self::SESSION, $args['chat_session_id']);
        $this->assertSame($token, $args['runtime_session_token']);
        $this->assertNotNull($args['chat_lock']);
        $this->assertTrue(MPU_Chat_Lock::is_locked(self::SESSION), 'prepare must not release on success');
        $this->assertSame([], MPU_Observation_Buffer::peek($token));
        $this->assertStringContainsString($expected_block, $args['system_prompt']);
    }
}
