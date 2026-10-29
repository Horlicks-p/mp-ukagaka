<?php

/**
 * Minimal WP_REST_Response for provider tests that call test_connection().
 */

if (!class_exists('WP_REST_Response')) {
    class WP_REST_Response {
        public $data;
        public $status;

        public function __construct($data = null, $status = 200) {
            $this->data   = $data;
            $this->status = $status;
        }
    }
}
