<?php

// Estrategia para IONOS / Hosting Compartido:
// Si existe un archivo 'config.local.php', usarlo prioritariamente.
// Esto evita depender de variables de entorno (.env) que a veces fallan en estos hostings.
$localConfigPath = __DIR__ . '/config.local.php';
if (file_exists($localConfigPath)) {
    return require $localConfigPath;
}

// Fallback: Usar variables de entorno (getenv)
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
    'allow_headers' => ['Authorization', 'Content-Type', 'X-Request-Id', 'X-Auth-Token'],
  ],
  'n8n' => [
    'events_webhook_url' => getenv('N8N_EVENTS_WEBHOOK_URL') ?: '',
    'hmac_secret' => getenv('N8N_HMAC_SECRET') ?: '',
    'http_timeout' => (int)(getenv('OUTBOX_HTTP_TIMEOUT') ?: 10),
    'max_attempts' => (int)(getenv('OUTBOX_MAX_ATTEMPTS') ?: 20),
    'batch_size' => (int)(getenv('OUTBOX_BATCH_SIZE') ?: 20),
  ],
  'media' => [
    'presign_base_url' => getenv('MEDIA_PRESIGN_BASE_URL') ?: '',
  ],
  'google' => [
    'api_key' => getenv('GOOGLE_API_KEY') ?: '',
    'cx' => getenv('GOOGLE_CX') ?: '',
  ],
  'prospect_access' => [
    'jwt_secret' => getenv('PROSPECT_JWT_SECRET') ?: (getenv('JWT_ACCESS_SECRET') ?: ''),
    'frontend_public_base' => getenv('PROSPECT_FRONTEND_PUBLIC_BASE') ?: 'https://arrendamientoseguro.app',
  ],
  'api_auth' => [
    'expected_audience' => getenv('API_EXPECTED_AUDIENCE') ?: 'n8n-integrations',
    'access_ttl' => (int)(getenv('API_ACCESS_TOKEN_TTL') ?: 3600),
    'refresh_ttl' => (int)(getenv('API_REFRESH_TOKEN_TTL') ?: 2592000),
    'jwt_secret' => getenv('API_JWT_SECRET') ?: (getenv('JWT_ACCESS_SECRET') ?: ''),
  ],
];
