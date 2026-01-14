<?php
namespace App\Core;

use App\Controllers\AuthController;
use App\Controllers\HealthController;
use App\Controllers\UsersController;
use App\Middleware\AuthMiddleware;
use App\Repositories\ApiLogRepository;
use App\Repositories\TokenRepository;
use App\Repositories\UserRepository;

final class App {
  private array $config;
  private Router $router;
  private Database $db;
  private Logger $logger;

  public function __construct(array $config) {
    $this->config = $config;
    $this->router = new Router();
    $this->db = new Database($config['db']);
    $this->logger = new Logger(__DIR__ . '/../../storage/logs/api.log');

    $this->registerRoutes();
  }

  private function registerRoutes(): void {
    $userRepo = new UserRepository($this->db);
    $tokenRepo = new TokenRepository($this->db);
    $apiLogRepo = new ApiLogRepository($this->db);

    $health = new HealthController();
    $auth = new AuthController($this->config, $userRepo, $tokenRepo, $apiLogRepo, $this->logger);
    $users = new UsersController($this->config, $userRepo);

    // Public
    $this->router->add('GET',  '/api/v1/health', [$health, 'health']);
    $this->router->add('POST', '/api/v1/auth/login', [$auth, 'login']);
    $this->router->add('POST', '/api/v1/auth/refresh', [$auth, 'refresh']);
    $this->router->add('POST', '/api/v1/auth/logout', [$auth, 'logout']);

    // Protected
    $authMw = new AuthMiddleware($this->config['jwt']['access_secret']);

    $this->router->add('GET', '/api/v1/users/me', function(Request $req, Response $res, array $params) use ($authMw, $users) {
      $ctx = $authMw->handle($req, $res);
      $users->me($req, $res, $ctx);
    });
  }

  public function handle(Request $req, Response $res): void {
    $match = $this->router->match($req->getMethod(), $req->getPath());

    if (!$match) {
      $res->json([
        'data' => null,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [[ 'code' => 'not_found', 'message' => 'Not Found' ]]
      ], 404);
    }

    $handler = $match['handler'];
    $params = $match['params'];

    // Llamada uniforme
    $handler($req, $res, $params);
  }
}
