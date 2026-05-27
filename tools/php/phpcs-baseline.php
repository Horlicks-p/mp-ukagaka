<?php
/**
 * PHPCS baseline runner for MP Ukagaka.
 *
 * Usage:
 *   php tools/php/phpcs-baseline.php generate
 *   php tools/php/phpcs-baseline.php check
 *   php tools/php/phpcs-baseline.php summary
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be run from the command line.\n");
    exit(1);
}

$repo_root = dirname(__DIR__, 2);
$baseline_file = $repo_root . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'php' . DIRECTORY_SEPARATOR . 'phpcs-baseline.json';
$ruleset_file = $repo_root . DIRECTORY_SEPARATOR . 'phpcs.xml.dist';
$command = $argv[1] ?? 'check';

function mpu_phpcs_usage()
{
    fwrite(STDERR, "Usage: php tools/php/phpcs-baseline.php <generate|check|summary>\n");
}

function mpu_phpcs_bin($repo_root)
{
    $candidates = [
        $repo_root . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'php' . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'phpcs.bat',
        $repo_root . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'php' . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'phpcs',
    ];

    foreach ($candidates as $candidate) {
        if (is_file($candidate)) {
            return $candidate;
        }
    }

    return null;
}

function mpu_phpcs_relative_path($repo_root, $path)
{
    $path = str_replace('\\', '/', $path);
    $root = str_replace('\\', '/', $repo_root);
    if (strpos($path, $root . '/') === 0) {
        return substr($path, strlen($root) + 1);
    }
    return $path;
}

function mpu_phpcs_key($file, array $message)
{
    $source = $message['source'] ?? 'unknown';
    $text = preg_replace('/\s+/', ' ', trim((string) ($message['message'] ?? '')));
    return $file . "\n" . $source . "\n" . $text;
}

function mpu_phpcs_run($repo_root, $ruleset_file)
{
    $phpcs = mpu_phpcs_bin($repo_root);
    if ($phpcs === null) {
        fwrite(STDERR, "PHPCS is not installed at tools/php/vendor/bin/phpcs.\n");
        fwrite(STDERR, "Install it from tools/php, for example:\n");
        fwrite(STDERR, "  composer require --dev squizlabs/php_codesniffer:^3.10 wp-coding-standards/wpcs:^3.1 phpcompatibility/phpcompatibility-wp:^2.1\n");
        exit(2);
    }

    $cmd = [
        escapeshellarg($phpcs),
        '--standard=' . escapeshellarg($ruleset_file),
        '--report=json',
        '--runtime-set',
        'ignore_errors_on_exit',
        '1',
        '--runtime-set',
        'ignore_warnings_on_exit',
        '1',
    ];

    $descriptor_spec = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $process = proc_open(implode(' ', $cmd), $descriptor_spec, $pipes, $repo_root);
    if (!is_resource($process)) {
        fwrite(STDERR, "Unable to start PHPCS.\n");
        exit(2);
    }

    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($process);

    $data = json_decode($stdout, true);
    if (!is_array($data)) {
        fwrite(STDERR, "PHPCS did not return valid JSON.\n");
        if ($stderr !== '') {
            fwrite(STDERR, $stderr . "\n");
        }
        exit(2);
    }

    return $data;
}

function mpu_phpcs_collect($repo_root, array $report)
{
    $entries = [];

    foreach (($report['files'] ?? []) as $file => $file_report) {
        $relative_file = mpu_phpcs_relative_path($repo_root, $file);
        foreach (($file_report['messages'] ?? []) as $message) {
            $key = mpu_phpcs_key($relative_file, $message);
            if (!isset($entries[$key])) {
                $entries[$key] = [
                    'file' => $relative_file,
                    'source' => $message['source'] ?? 'unknown',
                    'message' => preg_replace('/\s+/', ' ', trim((string) ($message['message'] ?? ''))),
                    'type' => $message['type'] ?? 'UNKNOWN',
                    'count' => 0,
                    'firstLines' => [],
                ];
            }
            $entries[$key]['count']++;
            if (count($entries[$key]['firstLines']) < 5 && isset($message['line'])) {
                $entries[$key]['firstLines'][] = (int) $message['line'];
            }
        }
    }

    ksort($entries);
    return $entries;
}

function mpu_phpcs_write_baseline($baseline_file, array $entries)
{
    $payload = [
        'version' => 1,
        'generatedAt' => gmdate('c'),
        'description' => 'PHPCS baseline grouped by file + sniff source + message. Line numbers are metadata only.',
        'entries' => array_values($entries),
    ];

    file_put_contents(
        $baseline_file,
        json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n"
    );
}

function mpu_phpcs_load_baseline($baseline_file)
{
    if (!is_file($baseline_file)) {
        fwrite(STDERR, "Missing PHPCS baseline: tools/php/phpcs-baseline.json\n");
        fwrite(STDERR, "Run: php tools/php/phpcs-baseline.php generate\n");
        exit(2);
    }

    $payload = json_decode(file_get_contents($baseline_file), true);
    if (!is_array($payload) || !isset($payload['entries']) || !is_array($payload['entries'])) {
        fwrite(STDERR, "Invalid PHPCS baseline JSON.\n");
        exit(2);
    }

    $entries = [];
    foreach ($payload['entries'] as $entry) {
        $key = $entry['file'] . "\n" . $entry['source'] . "\n" . $entry['message'];
        $entries[$key] = $entry;
    }

    return [$payload, $entries];
}

function mpu_phpcs_print_summary(array $entries, $label)
{
    $total = 0;
    foreach ($entries as $entry) {
        $total += (int) $entry['count'];
    }
    echo $label . ': ' . $total . ' findings across ' . count($entries) . " grouped entries\n";
}

if (!in_array($command, ['generate', 'check', 'summary'], true)) {
    mpu_phpcs_usage();
    exit(1);
}

if ($command === 'summary') {
    [$payload, $baseline] = mpu_phpcs_load_baseline($baseline_file);
    mpu_phpcs_print_summary($baseline, 'Baseline');
    echo 'Generated at: ' . ($payload['generatedAt'] ?? 'unknown') . "\n";
    exit(0);
}

$report = mpu_phpcs_run($repo_root, $ruleset_file);
$current = mpu_phpcs_collect($repo_root, $report);

if ($command === 'generate') {
    mpu_phpcs_write_baseline($baseline_file, $current);
    mpu_phpcs_print_summary($current, 'Generated baseline');
    exit(0);
}

[$payload, $baseline] = mpu_phpcs_load_baseline($baseline_file);
$new_findings = [];

foreach ($current as $key => $entry) {
    $allowed = isset($baseline[$key]) ? (int) $baseline[$key]['count'] : 0;
    $actual = (int) $entry['count'];
    if ($actual > $allowed) {
        $entry['newCount'] = $actual - $allowed;
        $entry['allowedCount'] = $allowed;
        $new_findings[] = $entry;
    }
}

if ($new_findings) {
    fwrite(STDERR, "PHPCS baseline check failed. New findings:\n");
    foreach ($new_findings as $entry) {
        fwrite(
            STDERR,
            sprintf(
                "- %s [%s] +%d: %s\n",
                $entry['file'],
                $entry['source'],
                $entry['newCount'],
                $entry['message']
            )
        );
    }
    exit(1);
}

mpu_phpcs_print_summary($current, 'Current PHPCS findings within baseline');
exit(0);
