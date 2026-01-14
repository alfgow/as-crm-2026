<?php
// scripts/test_login.php

$url = 'http://localhost/as-crm-2026/api/public/api/v1/auth/login';
$data = [
    'email' => 'test@arrendamientoseguro.app',
    'password' => 'password123'
];

$options = [
    'http' => [
        'header'  => "Content-type: application/json\r\n",
        'method'  => 'POST',
        'content' => json_encode($data),
        'ignore_errors' => true // Capture error responses
    ]
];

$context  = stream_context_create($options);
$result = file_get_contents($url, false, $context);
$headers = $http_response_header;

echo "--- HTTP Headers ---\n";
print_r($headers);
echo "\n--- Response Body ---\n";
echo $result . "\n";
