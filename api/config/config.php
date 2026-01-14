<?php

return [
  'env' => getenv('APP_ENV') ?: 'production',
  'debug' => (getenv('APP_DEBUG') === 'true'),

  'db' => [
    'host' => getenv('DB_HOST') ?: 'localhost',
    'name' => getenv('DB_NAME') ?: '',
    'user' => getenv('DB_USER') ?: '',
    'pass' => getenv('DB_PASS') ?: '',
    'port' => (int)(getenv('DB_PORT') ?: 3306),
    'charset' => 'utf8mb4',
  ],

  'jwt' => [
    'access_secret' => getenv('JWT_ACCESS_SECRET') ?: '',
    'refresh_secret' => getenv('JWT_REFRESH_SECRET') ?: '',
    'access_ttl' => (int)(getenv('JWT_ACCESS_TTL_SECONDS') ?: 900),       // 15 min
    'refresh_ttl' => (int)(getenv('JWT_REFRESH_TTL_SECONDS') ?: 2592000), // 30 días
  ],

  'cors' => [
    'allow_origins' => array_values(array_filter(array_map('trim', explode(',', getenv('CORS_ALLOW_ORIGINS') ?: '')))),
    'allow_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
    'allow_headers' => ['Authorization', 'Content-Type', 'X-Request-Id'],
  ],
];
