<?php
require __DIR__ . '/../src/Core/Request.php';

$headers = [];
foreach ($_SERVER as $key => $value) {
    if (str_starts_with($key, 'HTTP_')) {
        $headers[$key] = $value;
    }
}

$apacheHeaders = function_exists('apache_request_headers') ? apache_request_headers() : 'NOT_AVAILABLE';

header('Content-Type: application/json');
echo json_encode([
    'headers_from_server' => $headers,
    'headers_from_apache' => $apacheHeaders,
    'authorization_header' => $_SERVER['HTTP_AUTHORIZATION'] ?? 'NOT_SET',
    'x_authorization_token' => $_SERVER['HTTP_X_AUTHORIZATION_TOKEN'] ?? 'NOT_SET',
    'x_auth_token' => $_SERVER['HTTP_X_AUTH_TOKEN'] ?? 'NOT_SET',
    'apache_env_auth' => getenv('HTTP_AUTHORIZATION'),
    'request_method' => $_SERVER['REQUEST_METHOD'],
]);
