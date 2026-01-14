<?php

declare(strict_types=1);

// Simple SPL Autoloader for 'App\' namespace
spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $base_dir = __DIR__ . '/../src/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    if (file_exists($file)) require $file;
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
  $payload = [
    'data' => null,
    'meta' => ['requestId' => $request->getRequestId()],
    'errors' => [[
      'code' => 'internal_error',
      'message' => $config['debug'] ? $e->getMessage() : 'Internal Server Error',
    ]],
  ];

  $response->json($payload, 500);
}
