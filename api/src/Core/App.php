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
    $authMw = new AuthMiddleware($this->config['jwt']['access_secret'], $tokenRepo);

    $this->router->add('GET', '/api/v1/users/me', function(Request $req, Response $res, array $params) use ($authMw, $users) {
      $ctx = $authMw->handle($req, $res);
      $users->me($req, $res, $ctx);
    });

    // Users CRUD
    $this->router->add('GET', '/api/v1/users', function(Request $req, Response $res) use ($authMw, $users) {
      $ctx = $authMw->handle($req, $res);
      $users->index($req, $res, $ctx);
    });
    
    $this->router->add('POST', '/api/v1/users', function(Request $req, Response $res) use ($authMw, $users) {
      $ctx = $authMw->handle($req, $res);
      $users->store($req, $res, $ctx);
    });

    $this->router->add('GET', '/api/v1/users/{id}', function(Request $req, Response $res, array $params) use ($authMw, $users) {
      $ctx = $authMw->handle($req, $res);
      $users->show($req, $res, $params);
    });

    $this->router->add('PUT', '/api/v1/users/{id}', function(Request $req, Response $res, array $params) use ($authMw, $users) {
      $ctx = $authMw->handle($req, $res);
      $users->update($req, $res, $params);
    });

    $this->router->add('DELETE', '/api/v1/users/{id}', function(Request $req, Response $res, array $params) use ($authMw, $users) {
      $ctx = $authMw->handle($req, $res);
      $users->destroy($req, $res, $params);
    });

    // Arrendadores CRUD
    $arrendadorRepo = new \App\Repositories\ArrendadorRepository($this->db);
    $arrendadores = new \App\Controllers\ArrendadoresController($this->config, $arrendadorRepo);

    $this->router->add('GET', '/api/v1/arrendadores', function(Request $req, Response $res) use ($authMw, $arrendadores) {
      $ctx = $authMw->handle($req, $res);
      $arrendadores->index($req, $res, $ctx);
    });
    
    $this->router->add('POST', '/api/v1/arrendadores', function(Request $req, Response $res) use ($authMw, $arrendadores) {
      $ctx = $authMw->handle($req, $res);
      $arrendadores->store($req, $res, $ctx);
    });

    $this->router->add('GET', '/api/v1/arrendadores/{id}', function(Request $req, Response $res, array $params) use ($authMw, $arrendadores) {
      $ctx = $authMw->handle($req, $res);
      $arrendadores->show($req, $res, $params);
    });

    $this->router->add('PUT', '/api/v1/arrendadores/{id}', function(Request $req, Response $res, array $params) use ($authMw, $arrendadores) {
      $ctx = $authMw->handle($req, $res);
      $arrendadores->update($req, $res, $params);
    });

    $this->router->add('DELETE', '/api/v1/arrendadores/{id}', function(Request $req, Response $res, array $params) use ($authMw, $arrendadores) {
      $ctx = $authMw->handle($req, $res);
      $arrendadores->destroy($req, $res, $params);
    });

    // Asesores CRUD
    $asesorRepo = new \App\Repositories\AsesorRepository($this->db);
    $asesores = new \App\Controllers\AsesoresController($this->config, $asesorRepo);

    $this->router->add('GET', '/api/v1/asesores', function(Request $req, Response $res) use ($authMw, $asesores) {
      $ctx = $authMw->handle($req, $res);
      $asesores->index($req, $res, $ctx);
    });
    
    $this->router->add('POST', '/api/v1/asesores', function(Request $req, Response $res) use ($authMw, $asesores) {
      $ctx = $authMw->handle($req, $res);
      $asesores->store($req, $res, $ctx);
    });

    $this->router->add('GET', '/api/v1/asesores/{id}', function(Request $req, Response $res, array $params) use ($authMw, $asesores) {
      $ctx = $authMw->handle($req, $res);
      $asesores->show($req, $res, $params);
    });

    $this->router->add('PUT', '/api/v1/asesores/{id}', function(Request $req, Response $res, array $params) use ($authMw, $asesores) {
      $ctx = $authMw->handle($req, $res);
      $asesores->update($req, $res, $params);
    });

    $this->router->add('DELETE', '/api/v1/asesores/{id}', function(Request $req, Response $res, array $params) use ($authMw, $asesores) {
      $ctx = $authMw->handle($req, $res);
      $asesores->destroy($req, $res, $params);
    });

    // Inmuebles CRUD
    $inmuebleRepo = new \App\Repositories\InmuebleRepository($this->db);
    $inmuebles = new \App\Controllers\InmueblesController($this->config, $inmuebleRepo);

    $this->router->add('GET', '/api/v1/inmuebles', function(Request $req, Response $res) use ($authMw, $inmuebles) {
      $ctx = $authMw->handle($req, $res);
      $inmuebles->index($req, $res, $ctx);
    });
    
    $this->router->add('POST', '/api/v1/inmuebles', function(Request $req, Response $res) use ($authMw, $inmuebles) {
      $ctx = $authMw->handle($req, $res);
      $inmuebles->store($req, $res, $ctx);
    });

    $this->router->add('GET', '/api/v1/inmuebles/{id}', function(Request $req, Response $res, array $params) use ($authMw, $inmuebles) {
      $ctx = $authMw->handle($req, $res);
      $inmuebles->show($req, $res, $params);
    });

    $this->router->add('PUT', '/api/v1/inmuebles/{id}', function(Request $req, Response $res, array $params) use ($authMw, $inmuebles) {
      $ctx = $authMw->handle($req, $res);
      $inmuebles->update($req, $res, $params);
    });

    $this->router->add('DELETE', '/api/v1/inmuebles/{id}', function(Request $req, Response $res, array $params) use ($authMw, $inmuebles) {
      $ctx = $authMw->handle($req, $res);
      $inmuebles->destroy($req, $res, $params);
    });

    // Inquilinos CRUD
    $inquilinoRepo = new \App\Repositories\InquilinoRepository($this->db);
    $inquilinos = new \App\Controllers\InquilinosController($this->config, $inquilinoRepo);

    $this->router->add('GET', '/api/v1/inquilinos', function(Request $req, Response $res) use ($authMw, $inquilinos) {
      $ctx = $authMw->handle($req, $res);
      $inquilinos->index($req, $res, $ctx);
    });
    
    $this->router->add('POST', '/api/v1/inquilinos', function(Request $req, Response $res) use ($authMw, $inquilinos) {
      $ctx = $authMw->handle($req, $res);
      $inquilinos->store($req, $res, $ctx);
    });

    $this->router->add('GET', '/api/v1/inquilinos/{id}', function(Request $req, Response $res, array $params) use ($authMw, $inquilinos) {
      $ctx = $authMw->handle($req, $res);
      $inquilinos->show($req, $res, $params);
    });

    $this->router->add('PUT', '/api/v1/inquilinos/{id}', function(Request $req, Response $res, array $params) use ($authMw, $inquilinos) {
      $ctx = $authMw->handle($req, $res);
      $inquilinos->update($req, $res, $params);
    });

    $this->router->add('DELETE', '/api/v1/inquilinos/{id}', function(Request $req, Response $res, array $params) use ($authMw, $inquilinos) {
      $ctx = $authMw->handle($req, $res);
      $inquilinos->destroy($req, $res, $params);
    });

    // Polizas CRUD
    $polizaRepo = new \App\Repositories\PolizaRepository($this->db);
    $polizas = new \App\Controllers\PolizasController($this->config, $polizaRepo, $inmuebleRepo);

    $this->router->add('GET', '/api/v1/polizas', function(Request $req, Response $res) use ($authMw, $polizas) {
      $ctx = $authMw->handle($req, $res);
      $polizas->index($req, $res, $ctx);
    });
    
    $this->router->add('POST', '/api/v1/polizas', function(Request $req, Response $res) use ($authMw, $polizas) {
      $ctx = $authMw->handle($req, $res);
      $polizas->store($req, $res, $ctx);
    });

    $this->router->add('GET', '/api/v1/polizas/{id}', function(Request $req, Response $res, array $params) use ($authMw, $polizas) {
      $ctx = $authMw->handle($req, $res);
      $polizas->show($req, $res, $params);
    });

    $this->router->add('PUT', '/api/v1/polizas/{id}', function(Request $req, Response $res, array $params) use ($authMw, $polizas) {
      $ctx = $authMw->handle($req, $res);
      $polizas->update($req, $res, $params);
    });

    $this->router->add('DELETE', '/api/v1/polizas/{id}', function(Request $req, Response $res, array $params) use ($authMw, $polizas) {
      $ctx = $authMw->handle($req, $res);
      $polizas->destroy($req, $res, $params);
    });

    // Validaciones CRUD
    $validacionRepo = new \App\Repositories\ValidacionRepository($this->db);
    $validaciones = new \App\Controllers\ValidacionesController($this->config, $validacionRepo, $inquilinoRepo);

    $this->router->add('GET', '/api/v1/inquilinos/{id}/validaciones', function(Request $req, Response $res, array $params) use ($authMw, $validaciones) {
      $ctx = $authMw->handle($req, $res);
      $validaciones->show($req, $res, $params);
    });

    $this->router->add('PUT', '/api/v1/inquilinos/{id}/validaciones', function(Request $req, Response $res, array $params) use ($authMw, $validaciones) {
      $ctx = $authMw->handle($req, $res);
      $validaciones->update($req, $res, $params);
    });

    // Events & Automations
    $outboxRepo = new \App\Repositories\OutboxRepository($this->db);
    $outboxService = new \App\Services\OutboxService($outboxRepo);
    $runsRepo = new \App\Repositories\AutomationRunsRepository($this->db);
    
    $eventsCtrl = new \App\Controllers\EventsController($outboxService);
    $autoCtrl = new \App\Controllers\AutomationsController($this->config, $runsRepo);

    // 3.1 Emitir evento (interno)
    $this->router->add('POST', '/api/v1/events/emit', function(Request $req, Response $res) use ($authMw, $eventsCtrl) {
      $authMw->handle($req, $res);
      $eventsCtrl->emit($req, $res, []);
    });

    // 3.3 Callback resultados n8n
    // Nota: Aunque n8n llama esto, debe estar autenticado (via token de usuario sistema o similar) o usar HMAC para validar.
    // El controller verifica HMAC si se configura, pero también podemos exigir Auth.
    // Por ahora lo dejamos tras AuthMiddleware para mayor seguridad (n8n debe hacer login), o podemos hacerlo público si solo confiamos en HMAC.
    // Recomendación: Login + HMAC.
    $this->router->add('POST', '/api/v1/automations/callbacks/{correlationId}', function(Request $req, Response $res, array $params) use ($autoCtrl) {
       // Si queremos que sea público (solo HMAC), no llamamos a $authMw->handle().
       // Dado que el controller valida HMAC, podemos permitir acceso público aquí.
       $autoCtrl->callback($req, $res, $params);
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
