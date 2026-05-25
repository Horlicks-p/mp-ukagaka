<?php
/**
 * Builder for front-end console log i18n payloads.
 *
 * @package MP_Ukagaka
 */

if (!defined('ABSPATH')) {
    exit;
}

class MPU_Log_I18n_Builder
{
    /**
     * @var array<string, string>
     */
    private $logs = [];

    /**
     * @var array<string, string>
     */
    private $logs_debug = [];

    /**
     * Add a production-visible log string.
     *
     * @param string $key
     * @param string $message
     * @param array<string, mixed> $args
     * @return void
     */
    public function always($key, $message, $args = [])
    {
        $this->add('logs', $key, $message, $args);
    }

    /**
     * Add a debug-only log string.
     *
     * @param string $key
     * @param string $message
     * @param array<string, mixed> $args
     * @return void
     */
    public function debug($key, $message, $args = [])
    {
        $this->add('logsDebug', $key, $message, $args);
    }

    /**
     * Get production-visible log strings.
     *
     * @return array<string, string>
     */
    public function logs()
    {
        return $this->logs;
    }

    /**
     * Get debug-only log strings.
     *
     * @return array<string, string>
     */
    public function logs_debug()
    {
        return $this->logs_debug;
    }

    /**
     * @param string $bucket
     * @param string $key
     * @param string $message
     * @param array<string, mixed> $args
     * @return void
     */
    private function add($bucket, $key, $message, $args)
    {
        $this->validate_key($key, $args);

        if (array_key_exists($key, $this->logs) || array_key_exists($key, $this->logs_debug)) {
            throw new InvalidArgumentException(sprintf('Duplicate console log i18n key: %s', $key));
        }

        if ($bucket === 'logs') {
            $this->logs[$key] = (string) $message;
            return;
        }

        if ($bucket === 'logsDebug') {
            $this->logs_debug[$key] = (string) $message;
            return;
        }

        throw new InvalidArgumentException(sprintf('Unknown console log i18n bucket: %s', $bucket));
    }

    /**
     * @param string $key
     * @param array<string, mixed> $args
     * @return void
     */
    private function validate_key($key, $args)
    {
        if (!is_string($key) || !preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $key)) {
            throw new InvalidArgumentException(sprintf('Invalid console log i18n key: %s', (string) $key));
        }

        if (!empty($args['scope']) && $args['scope'] === 'frieren' && strpos($key, 'frieren') !== 0) {
            throw new InvalidArgumentException(sprintf('Frieren console log i18n key must use frieren prefix: %s', $key));
        }
    }
}

/**
 * Create a per-request console log i18n builder.
 *
 * @return MPU_Log_I18n_Builder
 */
function mpu_console_log_i18n_builder()
{
    return new MPU_Log_I18n_Builder();
}
