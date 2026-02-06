<?php

declare(strict_types=1);

// Simple SPL Autoloader for 'App\' namespace
spl_autoload_register(function ($class) {
  $prefix = 'App\\';
  $base_dir = __DIR__ . '/../src/';
  $len = strlen($prefix);
  if (strncmp($prefix, $class, $len) !== 0)
    return;
  $relative_class = substr($class, $len);
  $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
  if (file_exists($file))
    require $file;
});

require __DIR__ . '/../src/Core/Env.php';

use App\Core\App;
use App\Core\Request;
use App\Core\Response;
use App\Middleware\CorsMiddleware;

\App\Core\Env::load(__DIR__ . '/../.env');

$config = require __DIR__ . '/../config/config.php';

$request = new Request();
$response = new Response();

try {
  // CORS primero (responde OPTIONS)
  (new CorsMiddleware($config['cors']))->handle($request, $response);

  $app = new App($config);
  $app->handle($request, $response);
} catch (\Throwable $e) {
  // Emergency CORS headers for error responses
  $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
  $allowedOrigins = getenv('CORS_ALLOW_ORIGINS') ? explode(',', getenv('CORS_ALLOW_ORIGINS')) : [];

  // Basic cleaning of origins
  $allowedOrigins = array_map('trim', $allowedOrigins);

  if ($origin && (in_array($origin, $allowedOrigins) || in_array('*', $allowedOrigins))) {
    header("Access-Control-Allow-Origin: $origin");
    header("Access-Control-Allow-Credentials: true");
    header("Access-Control-Allow-Headers: Authorization, Content-Type, X-Request-Id");
  }

  $isDebug = (isset($config) && isset($config['debug'])) ? $config['debug'] : (getenv('APP_DEBUG') === 'true');

  $payload = [
    'data' => null,
    'meta' => ['requestId' => $request->getRequestId()],
    'errors' => [
      [
        'code' => 'internal_error',
        'message' => $isDebug ? $e->getMessage() : 'Internal Server Error',
        'trace' => $isDebug ? $e->getTrace() : [],
      ]
    ],
  ];

  $response->json($payload, 500);
}
