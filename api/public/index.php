<?php

declare(strict_types=1);

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
