<?php

/**
 * Router for `php -S`, standing in for an AI provider in ProviderStreamHttpLogTest.
 *
 *   /ok             200 SSE stream with two data events
 *   /json/<status>  <status> with a provider-style JSON error body
 *   /text/<status>  <status> with a plain-text error body
 *
 * Every error body carries RESPONSE_SENTINEL so the test can prove the provider's
 * reply never reaches the PHP error log.
 */

$path = (string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if ($path === '/ok') {
    header('Content-Type: text/event-stream');
    echo "data: {\"n\":1}\n\n";
    echo "data: {\"n\":2}\n\n";
    echo "data: [DONE]\n\n";
    return true;
}

if (preg_match('#^/(json|text)/(\d{3})$#', $path, $m)) {
    http_response_code((int) $m[2]);
    if ($m[1] === 'json') {
        header('Content-Type: application/json');
        echo json_encode([
            'error' => [
                'message' => 'Echoed input: RESPONSE_SENTINEL',
                'type'    => 'invalid_request_error',
                'code'    => 'rate_limit_exceeded',
            ],
        ]);
    } else {
        header('Content-Type: text/plain');
        echo 'Upstream failure near RESPONSE_SENTINEL';
    }
    return true;
}

http_response_code(404);
echo 'not found';
return true;
