<?php

/**
 * Keeps checksum-mismatch diagnostics from leaking out of a test.
 *
 * mpu_chat_integrity_dump_mismatch() always writes to <plugin>/logs/checksum-mismatch.log
 * (and creates logs/.htaccess on first use), in every integrity mode. The guard snapshots
 * the exact bytes before a test and puts them back afterwards. If the files changed in a way
 * the test cannot have caused (original bytes no longer a prefix), it refuses to clean up
 * and reports the problem so the evidence stays on disk.
 */
final class MPU_Test_Integrity_Log_Guard {
    private $dir;
    private $files;
    private $dir_existed;
    private $snapshots = [];

    public function __construct() {
        $this->dir   = MPU_TESTS_ROOT . '/logs';
        $this->files = [
            $this->dir . '/checksum-mismatch.log',
            $this->dir . '/.htaccess',
        ];
        $this->dir_existed = is_dir($this->dir);
        foreach ($this->files as $file) {
            $this->snapshots[$file] = is_file($file) ? file_get_contents($file) : null;
        }
    }

    public function log_path(): string {
        return $this->files[0];
    }

    /**
     * Bytes the test appended to the mismatch log so far.
     */
    public function appended_log(): string {
        $file = $this->log_path();
        if (!is_file($file)) {
            return '';
        }
        $current  = (string) file_get_contents($file);
        $original = (string) ($this->snapshots[$file] ?? '');
        return strncmp($current, $original, strlen($original)) === 0 ? substr($current, strlen($original)) : '';
    }

    /**
     * Restore the snapshot. Returns a list of problems; empty means fully restored.
     *
     * @return string[]
     */
    public function restore(): array {
        $problems = [];

        foreach ($this->files as $file) {
            $original = $this->snapshots[$file];
            if (!is_file($file)) {
                if ($original !== null) {
                    $problems[] = "{$file} disappeared during the test";
                }
                continue;
            }
            $current = (string) file_get_contents($file);
            if ($original !== null && strncmp($current, $original, strlen($original)) !== 0) {
                $problems[] = "{$file} was modified outside this test; left untouched";
                continue;
            }
            if ($original === null) {
                unlink($file);
            } elseif ($current !== $original) {
                file_put_contents($file, $original);
            }
        }

        if (!$this->dir_existed && is_dir($this->dir)) {
            $left = array_diff(scandir($this->dir), ['.', '..']);
            if ($left === []) {
                rmdir($this->dir);
            } else {
                $problems[] = $this->dir . ' has unexpected files: ' . implode(', ', $left);
            }
        }

        return $problems;
    }
}
