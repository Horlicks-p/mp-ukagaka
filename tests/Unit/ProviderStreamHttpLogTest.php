<?php

use PHPUnit\Framework\TestCase;

require_once MPU_TESTS_ROOT . '/includes/llm/provider-stream-http.php';

if (!function_exists('is_wp_error')) {
    function is_wp_error($value) {
        return $value instanceof WP_Error;
    }
}

/**
 * Drives mpu_stream_api_request() against a local `php -S` fake provider and reads
 * back what was actually written to the PHP error log.
 */
final class ProviderStreamHttpLogTest extends TestCase {
    private const SENTINELS = [
        'PROMPT_SENTINEL',
        'ADMIN_NAME_SENTINEL',
        'TOOL_ARG_SENTINEL',
        'API_KEY_SENTINEL',
        'USERINFO_SENTINEL',
        'HEADER_KEY_SENTINEL',
        'RESPONSE_SENTINEL',
    ];

    /** @var resource|null */
    private static $server;
    /** @var int */
    private static $port = 0;

    private $log_file;
    private $previous_error_log;

    public static function setUpBeforeClass(): void {
        if (!function_exists('curl_init') || !function_exists('proc_open')) {
            return;
        }

        $probe = stream_socket_server('tcp://127.0.0.1:0');
        $name  = stream_socket_get_name($probe, false);
        fclose($probe);
        self::$port = (int) substr($name, strrpos($name, ':') + 1);

        $null = stripos(PHP_OS, 'WIN') === 0 ? 'NUL' : '/dev/null';
        self::$server = proc_open(
            [PHP_BINARY, '-S', '127.0.0.1:' . self::$port, MPU_TESTS_ROOT . '/tests/support/fake-provider-server.php'],
            [0 => ['file', $null, 'r'], 1 => ['file', $null, 'w'], 2 => ['file', $null, 'w']],
            $pipes
        );

        $deadline = microtime(true) + 5;
        while (microtime(true) < $deadline) {
            $socket = @fsockopen('127.0.0.1', self::$port, $errno, $errstr, 0.2);
            if ($socket) {
                fclose($socket);
                return;
            }
            usleep(50000);
        }
        self::tearDownAfterClass();
    }

    public static function tearDownAfterClass(): void {
        if (is_resource(self::$server)) {
            proc_terminate(self::$server);
            proc_close(self::$server);
        }
        self::$server = null;
    }

    protected function setUp(): void {
        if (!is_resource(self::$server)) {
            $this->markTestSkipped('cURL, proc_open or the php -S fake provider is unavailable.');
        }
        $this->log_file           = tempnam(sys_get_temp_dir(), 'mpu-log-');
        $this->previous_error_log = ini_set('error_log', $this->log_file);
    }

    protected function tearDown(): void {
        if ($this->log_file !== null) {
            ini_set('error_log', (string) $this->previous_error_log);
            @unlink($this->log_file);
        }
    }

    public function provider_error_cases(): array {
        return [
            'json 400' => ['json', 400],
            'json 401' => ['json', 401],
            'json 429' => ['json', 429],
            'json 500' => ['json', 500],
            'text 401' => ['text', 401],
            'text 503' => ['text', 503],
        ];
    }

    /**
     * @dataProvider provider_error_cases
     */
    public function test_http_error_log_keeps_metadata_but_no_content(string $kind, int $status): void {
        $chunks = [];
        $result = mpu_stream_api_request(
            $this->url('/' . $kind . '/' . $status, true),
            $this->sensitive_args(),
            static function ($data) use (&$chunks) {
                $chunks[] = $data;
            }
        );

        $log = (string) file_get_contents($this->log_file);

        $this->assertStringContainsString('[MP Ukagaka][provider-http] HTTP ' . $status, $log);
        $this->assertStringContainsString('http://127.0.0.1:' . self::$port . '/' . $kind . '/' . $status, $log);
        $this->assertStringContainsString('request body=' . strlen($this->sensitive_args()['body']) . ' bytes', $log);
        if ($kind === 'json') {
            $this->assertStringContainsString('type=invalid_request_error', $log);
            $this->assertStringContainsString('code=rate_limit_exceeded', $log);
        }
        foreach (self::SENTINELS as $sentinel) {
            $this->assertStringNotContainsString($sentinel, $log, $sentinel . ' leaked into the error log');
        }
        $this->assertStringNotContainsString('key=', $log);

        // The caller-facing error contract is unchanged.
        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('http_error', $result->get_error_code());
        $data = $result->get_error_data();
        $this->assertSame($status, $data['status']);
        $this->assertSame(trim(implode('', $chunks)), $data['body']);
        $expected_message = $kind === 'json'
            ? 'AI Provider returned HTTP ' . $status . ': Echoed input: RESPONSE_SENTINEL'
            : 'AI Provider returned HTTP ' . $status . ': Upstream failure near RESPONSE_SENTINEL';
        $this->assertSame($expected_message, $result->get_error_message());
    }

    public function test_successful_stream_is_untouched_and_logs_nothing(): void {
        $events = [];
        $result = mpu_stream_sse_events(
            $this->url('/ok', false),
            $this->sensitive_args(),
            static function ($event_type, $data_str) use (&$events) {
                $events[] = [$event_type, $data_str];
            }
        );

        $this->assertNull($result);
        $this->assertSame([['', '{"n":1}'], ['', '{"n":2}']], $events);
        $this->assertSame('', (string) file_get_contents($this->log_file));
    }

    public function test_log_line_reads_error_ids_from_wrapped_gemini_errors_only(): void {
        $line = mpu_stream_http_error_log_line(
            400,
            'https://generativelanguage.googleapis.com/v1beta/models/gemini:streamGenerateContent?alt=sse&key=API_KEY_SENTINEL',
            12,
            '[{"error":{"code":400,"message":"PROMPT_SENTINEL is invalid","status":"INVALID_ARGUMENT"}}]'
        );

        $this->assertSame(
            '[MP Ukagaka][provider-http] HTTP 400 to https://generativelanguage.googleapis.com/v1beta/models/gemini:streamGenerateContent'
            . ' (request body=12 bytes, response tail=91 bytes, provider error code=400 status=INVALID_ARGUMENT)',
            $line
        );
    }

    public function test_log_line_drops_free_text_error_ids(): void {
        $line = mpu_stream_http_error_log_line(
            500,
            'not a url',
            0,
            '{"error":{"type":"has spaces PROMPT_SENTINEL","code":"ok_code"}}'
        );

        $this->assertStringContainsString('to (unparsable url)', $line);
        $this->assertStringContainsString('provider error code=ok_code)', $line);
        $this->assertStringNotContainsString('PROMPT_SENTINEL', $line);
    }

    private function url(string $path, bool $with_secrets): string {
        $auth = $with_secrets ? 'user:USERINFO_SENTINEL@' : '';
        $query = $with_secrets ? '?alt=sse&key=API_KEY_SENTINEL' : '';
        return 'http://' . $auth . '127.0.0.1:' . self::$port . $path . $query;
    }

    private function sensitive_args(): array {
        return [
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer HEADER_KEY_SENTINEL',
            ],
            'body' => json_encode([
                'model'    => 'gpt-test',
                'messages' => [
                    ['role' => 'system', 'content' => 'PROMPT_SENTINEL You call the admin ADMIN_NAME_SENTINEL.'],
                    ['role' => 'user', 'content' => 'hello'],
                    [
                        'role'       => 'assistant',
                        'tool_calls' => [[
                            'id'       => 'call_1',
                            'type'     => 'function',
                            'function' => ['name' => 'ban_ip', 'arguments' => '{"ip":"TOOL_ARG_SENTINEL"}'],
                        ]],
                    ],
                ],
            ]),
            'timeout' => 10,
        ];
    }
}
