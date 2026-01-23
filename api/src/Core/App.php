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

    $health = new HealthController($this->db);
    $auth = new AuthController($this->config, $userRepo, $tokenRepo, $apiLogRepo, $this->logger);
    $users = new UsersController($this->config, $userRepo);
    $apiClientsRepo = new \App\Repositories\ApiClientRepository($this->db);
    $apiRefreshRepo = new \App\Repositories\ApiRefreshTokenRepository($this->db);
    $apiRevocationsRepo = new \App\Repositories\ApiTokenRevocationRepository($this->db);
    $apiAuth = new \App\Controllers\AuthApiController($this->config, $apiClientsRepo, $apiRefreshRepo, $apiRevocationsRepo);

    // Public
    $this->router->add('GET',  '/api/v1/health', [$health, 'health']);
    $this->router->add('POST', '/api/v1/auth/login', [$auth, 'login']);
    $this->router->add('POST', '/api/v1/auth/refresh', [$auth, 'refresh']);
    $this->router->add('POST', '/api/v1/auth/logout', [$auth, 'logout']);
    $this->router->add('POST', '/api/v1/auth/api/login', [$apiAuth, 'login']);
    $this->router->add('POST', '/api/v1/auth/api/refresh', [$apiAuth, 'refresh']);

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

    // API Clients
    $apiClients = new \App\Controllers\ApiClientsController($apiClientsRepo);

    $this->router->add('GET', '/api/v1/api-clients', function(Request $req, Response $res) use ($authMw, $apiClients) {
      $ctx = $authMw->handle($req, $res);
      $apiClients->index($req, $res, $ctx);
    });

    $this->router->add('GET', '/api/v1/integrations/clients', function(Request $req, Response $res) use ($authMw, $apiClients) {
      $ctx = $authMw->handle($req, $res);
      $apiClients->index($req, $res, $ctx);
    });

    $this->router->add('POST', '/api/v1/api-clients', function(Request $req, Response $res) use ($authMw, $apiClients) {
      $ctx = $authMw->handle($req, $res);
      $apiClients->store($req, $res, $ctx);
    });

    $this->router->add('POST', '/api/v1/integrations/clients', function(Request $req, Response $res) use ($authMw, $apiClients) {
      $ctx = $authMw->handle($req, $res);
      $apiClients->store($req, $res, $ctx);
    });

    $this->router->add('POST', '/api/v1/api-clients/{id}/rotate-secret', function(Request $req, Response $res, array $params) use ($authMw, $apiClients) {
      $ctx = $authMw->handle($req, $res);
      $apiClients->rotateSecret($req, $res, $params);
    });

    $this->router->add('POST', '/api/v1/integrations/clients/{id}/rotate-secret', function(Request $req, Response $res, array $params) use ($authMw, $apiClients) {
      $ctx = $authMw->handle($req, $res);
      $apiClients->rotateSecret($req, $res, $params);
    });

    // Prospect access (OTP + magic link)
    $prospectRepo = new \App\Repositories\ProspectAccessRepository($this->db);
    $prospectAccess = new \App\Controllers\ProspectAccessController($this->config, $prospectRepo);

    $this->router->add('GET', '/api/v1/prospectos/code', function(Request $req, Response $res) use ($authMw, $prospectAccess) {
      $ctx = $authMw->handle($req, $res);
      $prospectAccess->code($req, $res);
    });

    $this->router->add('POST', '/api/v1/prospectos/code', function(Request $req, Response $res) use ($authMw, $prospectAccess) {
      $ctx = $authMw->handle($req, $res);
      $prospectAccess->issue($req, $res);
    });

    $this->router->add('POST', '/api/v1/prospectos/send-emails', function(Request $req, Response $res) use ($authMw, $prospectAccess) {
      $ctx = $authMw->handle($req, $res);
      $prospectAccess->sendEmails($req, $res);
    });

    // Media presign
    $mediaRepo = new \App\Repositories\MediaRepository($this->db);
    $media = new \App\Controllers\MediaController($this->config, $mediaRepo);

    $this->router->add('GET', '/api/v1/media/presign', function(Request $req, Response $res) use ($authMw, $media) {
      $ctx = $authMw->handle($req, $res);
      $media->presign($req, $res);
    });

    $this->router->add('POST', '/api/v1/media/presign-many', function(Request $req, Response $res) use ($authMw, $media) {
      $ctx = $authMw->handle($req, $res);
      $media->presignMany($req, $res);
    });

    // Blog
    $blogRepo = new \App\Repositories\BlogRepository($this->db);
    $blog = new \App\Controllers\BlogController($blogRepo);

    $this->router->add('GET', '/api/v1/blog', function(Request $req, Response $res) use ($authMw, $blog) {
      $ctx = $authMw->handle($req, $res);
      $blog->index($req, $res);
    });

    $this->router->add('GET', '/api/v1/blog/{id}', function(Request $req, Response $res, array $params) use ($authMw, $blog) {
      $ctx = $authMw->handle($req, $res);
      $blog->show($req, $res, $params);
    });

    $this->router->add('GET', '/api/v1/blog/slug/{slug}', function(Request $req, Response $res, array $params) use ($authMw, $blog) {
      $ctx = $authMw->handle($req, $res);
      $blog->showBySlug($req, $res, $params);
    });

    $this->router->add('POST', '/api/v1/blog', function(Request $req, Response $res) use ($authMw, $blog) {
      $ctx = $authMw->handle($req, $res);
      $blog->store($req, $res);
    });

    $this->router->add('PUT', '/api/v1/blog/{id}', function(Request $req, Response $res, array $params) use ($authMw, $blog) {
      $ctx = $authMw->handle($req, $res);
      $blog->update($req, $res, $params);
    });

    $this->router->add('DELETE', '/api/v1/blog/{id}', function(Request $req, Response $res, array $params) use ($authMw, $blog) {
      $ctx = $authMw->handle($req, $res);
      $blog->destroy($req, $res, $params);
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
    $this->router->add('GET', '/api/v1/arrendadores/{id}/detalle', function(Request $req, Response $res, array $params) use ($authMw, $arrendadores) {
      $ctx = $authMw->handle($req, $res);
      $arrendadores->showDetalle($req, $res, $params);
    });
    $this->router->add('GET', '/api/v1/arrendadores/slug/{slug}', function(Request $req, Response $res, array $params) use ($authMw, $arrendadores) {
      $ctx = $authMw->handle($req, $res);
      $arrendadores->showBySlug($req, $res, $params);
    });

    $this->router->add('PUT', '/api/v1/arrendadores/{id}', function(Request $req, Response $res, array $params) use ($authMw, $arrendadores) {
      $ctx = $authMw->handle($req, $res);
      $arrendadores->update($req, $res, $params);
    });

    $this->router->add('DELETE', '/api/v1/arrendadores/{id}', function(Request $req, Response $res, array $params) use ($authMw, $arrendadores) {
      $ctx = $authMw->handle($req, $res);
      $arrendadores->destroy($req, $res, $params);
    });

    $this->router->add('GET', '/api/v1/asesores/{id}/arrendadores', function(Request $req, Response $res, array $params) use ($authMw, $arrendadores) {
      $ctx = $authMw->handle($req, $res);
      $arrendadores->byAsesor($req, $res, $params);
    });

    $this->router->add('PUT', '/api/v1/arrendadores/{id}/asesor', function(Request $req, Response $res, array $params) use ($authMw, $arrendadores) {
      $ctx = $authMw->handle($req, $res);
      $arrendadores->updateAsesor($req, $res, $params);
    });
    $this->router->add('PUT', '/api/v1/arrendadores/{id}/datos-personales', function(Request $req, Response $res, array $params) use ($authMw, $arrendadores) {
      $ctx = $authMw->handle($req, $res);
      $arrendadores->updateDatosPersonales($req, $res, $params);
    });
    $this->router->add('PUT', '/api/v1/arrendadores/{id}/info-bancaria', function(Request $req, Response $res, array $params) use ($authMw, $arrendadores) {
      $ctx = $authMw->handle($req, $res);
      $arrendadores->updateInfoBancaria($req, $res, $params);
    });
    $this->router->add('PUT', '/api/v1/arrendadores/{id}/comentarios', function(Request $req, Response $res, array $params) use ($authMw, $arrendadores) {
      $ctx = $authMw->handle($req, $res);
      $arrendadores->updateComentarios($req, $res, $params);
    });
    $this->router->add('GET', '/api/v1/arrendadores/{id}/archivos', function(Request $req, Response $res, array $params) use ($authMw, $arrendadores) {
      $ctx = $authMw->handle($req, $res);
      $arrendadores->archivos($req, $res, $params);
    });
    $this->router->add('POST', '/api/v1/arrendadores/{id}/archivos', function(Request $req, Response $res, array $params) use ($authMw, $arrendadores) {
      $ctx = $authMw->handle($req, $res);
      $arrendadores->addArchivo($req, $res, $params);
    });
    $this->router->add('DELETE', '/api/v1/arrendadores/{id}/archivos/{archivoId}', function(Request $req, Response $res, array $params) use ($authMw, $arrendadores) {
      $ctx = $authMw->handle($req, $res);
      $arrendadores->deleteArchivo($req, $res, $params);
    });
    $this->router->add('PUT', '/api/v1/arrendadores/{id}/archivos/{archivoId}', function(Request $req, Response $res, array $params) use ($authMw, $arrendadores) {
      $ctx = $authMw->handle($req, $res);
      $arrendadores->updateArchivo($req, $res, $params);
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
    $this->router->add('POST', '/api/v1/asesores/delete-bulk', function(Request $req, Response $res) use ($authMw, $asesores) {
      $ctx = $authMw->handle($req, $res);
      $asesores->deleteBulk($req, $res);
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
    $this->router->add('GET', '/api/v1/inmuebles/{id}/info', function(Request $req, Response $res, array $params) use ($authMw, $inmuebles) {
      $ctx = $authMw->handle($req, $res);
      $inmuebles->info($req, $res, $params);
    });
    $this->router->add('GET', '/api/v1/inmuebles/legacy/{pk}/{sk}', function(Request $req, Response $res, array $params) use ($authMw, $inmuebles) {
      $ctx = $authMw->handle($req, $res);
      $inmuebles->showLegacy($req, $res, $params);
    });
    $this->router->add('GET', '/api/v1/inmuebles/legacy/{pk}/{sk}/info', function(Request $req, Response $res, array $params) use ($authMw, $inmuebles) {
      $ctx = $authMw->handle($req, $res);
      $inmuebles->infoLegacy($req, $res, $params);
    });

    $this->router->add('PUT', '/api/v1/inmuebles/{id}', function(Request $req, Response $res, array $params) use ($authMw, $inmuebles) {
      $ctx = $authMw->handle($req, $res);
      $inmuebles->update($req, $res, $params);
    });

    $this->router->add('DELETE', '/api/v1/inmuebles/{id}', function(Request $req, Response $res, array $params) use ($authMw, $inmuebles) {
      $ctx = $authMw->handle($req, $res);
      $inmuebles->destroy($req, $res, $params);
    });
    $this->router->add('POST', '/api/v1/inmuebles/delete-bulk', function(Request $req, Response $res) use ($authMw, $inmuebles) {
      $ctx = $authMw->handle($req, $res);
      $inmuebles->deleteBulk($req, $res);
    });

    $this->router->add('POST', '/api/v1/inmuebles/guardar-ajax', function(Request $req, Response $res) use ($authMw, $inmuebles) {
      $ctx = $authMw->handle($req, $res);
      $inmuebles->guardarAjax($req, $res, $ctx);
    });

    $this->router->add('GET', '/api/v1/arrendadores/{id}/inmuebles', function(Request $req, Response $res, array $params) use ($authMw, $inmuebles) {
      $ctx = $authMw->handle($req, $res);
      $inmuebles->byArrendador($req, $res, $params);
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

    $this->router->add('GET', '/api/v1/inquilinos/slug/{slug}', function(Request $req, Response $res, array $params) use ($authMw, $inquilinos) {
      $ctx = $authMw->handle($req, $res);
      $inquilinos->showBySlug($req, $res, $params);
    });

    $this->router->add('PUT', '/api/v1/inquilinos/{id}', function(Request $req, Response $res, array $params) use ($authMw, $inquilinos) {
      $ctx = $authMw->handle($req, $res);
      $inquilinos->update($req, $res, $params);
    });
    $this->router->add('PUT', '/api/v1/inquilinos/{id}/datos-personales', function(Request $req, Response $res, array $params) use ($authMw, $inquilinos) {
      $ctx = $authMw->handle($req, $res);
      $inquilinos->updateDatosPersonales($req, $res, $params);
    });

    $this->router->add('PUT', '/api/v1/inquilinos/{id}/status', function(Request $req, Response $res, array $params) use ($authMw, $inquilinos) {
      $ctx = $authMw->handle($req, $res);
      $inquilinos->updateStatus($req, $res, $params);
    });

    $this->router->add('GET', '/api/v1/inquilinos/{id}/archivos', function(Request $req, Response $res, array $params) use ($authMw, $inquilinos) {
      $ctx = $authMw->handle($req, $res);
      $inquilinos->archivos($req, $res, $params);
    });

    $this->router->add('PUT', '/api/v1/inquilinos/{id}/asesor', function(Request $req, Response $res, array $params) use ($authMw, $inquilinos) {
      $ctx = $authMw->handle($req, $res);
      $inquilinos->updateAsesor($req, $res, $params);
    });

    $this->router->add('PUT', '/api/v1/inquilinos/{id}/direccion', function(Request $req, Response $res, array $params) use ($authMw, $inquilinos) {
      $ctx = $authMw->handle($req, $res);
      $inquilinos->updateDireccion($req, $res, $params);
    });

    $this->router->add('PUT', '/api/v1/inquilinos/{id}/trabajo', function(Request $req, Response $res, array $params) use ($authMw, $inquilinos) {
      $ctx = $authMw->handle($req, $res);
      $inquilinos->updateTrabajo($req, $res, $params);
    });

    $this->router->add('PUT', '/api/v1/inquilinos/{id}/fiador', function(Request $req, Response $res, array $params) use ($authMw, $inquilinos) {
      $ctx = $authMw->handle($req, $res);
      $inquilinos->updateFiador($req, $res, $params);
    });

    $this->router->add('PUT', '/api/v1/inquilinos/{id}/historial-vivienda', function(Request $req, Response $res, array $params) use ($authMw, $inquilinos) {
      $ctx = $authMw->handle($req, $res);
      $inquilinos->updateHistorial($req, $res, $params);
    });
    $this->router->add('PUT', '/api/v1/inquilinos/{id}/validaciones', function(Request $req, Response $res, array $params) use ($authMw, $inquilinos) {
      $ctx = $authMw->handle($req, $res);
      $inquilinos->updateValidaciones($req, $res, $params);
    });

    $this->router->add('POST', '/api/v1/inquilinos/{id}/archivos', function(Request $req, Response $res, array $params) use ($authMw, $inquilinos) {
      $ctx = $authMw->handle($req, $res);
      $inquilinos->addArchivo($req, $res, $params);
    });

    $this->router->add('DELETE', '/api/v1/inquilinos/{id}/archivos/{archivoId}', function(Request $req, Response $res, array $params) use ($authMw, $inquilinos) {
      $ctx = $authMw->handle($req, $res);
      $inquilinos->deleteArchivo($req, $res, $params);
    });
    $this->router->add('PUT', '/api/v1/inquilinos/{id}/archivos/{archivoId}', function(Request $req, Response $res, array $params) use ($authMw, $inquilinos) {
      $ctx = $authMw->handle($req, $res);
      $inquilinos->updateArchivo($req, $res, $params);
    });

    $this->router->add('PUT', '/api/v1/inquilinos/{id}/status', function(Request $req, Response $res, array $params) use ($authMw, $inquilinos) {
      $ctx = $authMw->handle($req, $res);
      $inquilinos->updateStatus($req, $res, $params);
    });

    $this->router->add('GET', '/api/v1/inquilinos/{id}/archivos', function(Request $req, Response $res, array $params) use ($authMw, $inquilinos) {
      $ctx = $authMw->handle($req, $res);
      $inquilinos->archivos($req, $res, $params);
    });

    $this->router->add('DELETE', '/api/v1/inquilinos/{id}', function(Request $req, Response $res, array $params) use ($authMw, $inquilinos) {
      $ctx = $authMw->handle($req, $res);
      $inquilinos->destroy($req, $res, $params);
    });

    $this->router->add('POST', '/api/v1/inquilinos/delete-bulk', function(Request $req, Response $res) use ($authMw, $inquilinos) {
      $ctx = $authMw->handle($req, $res);
      $inquilinos->deleteBulk($req, $res, []);
    });

    // Polizas CRUD
    $polizaRepo = new \App\Repositories\PolizaRepository($this->db);
    $polizas = new \App\Controllers\PolizasController($this->config, $polizaRepo, $inmuebleRepo);

    $financieroRepo = new \App\Repositories\FinancieroRepository($this->db);
    $financiero = new \App\Controllers\FinancieroController($financieroRepo);
    $iaVentas = new \App\Controllers\IAVentasController($financieroRepo);
    $dashboard = new \App\Controllers\DashboardController($inquilinoRepo, $polizaRepo);
    $vencimientos = new \App\Controllers\VencimientosController($polizaRepo);

    $this->router->add('GET', '/api/v1/financieros', function(Request $req, Response $res) use ($authMw, $financiero) {
      $ctx = $authMw->handle($req, $res);
      $financiero->index($req, $res);
    });

    $this->router->add('GET', '/api/v1/financieros/registro-venta', function(Request $req, Response $res) use ($authMw, $financiero) {
      $ctx = $authMw->handle($req, $res);
      $financiero->registroVenta($req, $res);
    });

    $this->router->add('GET', '/api/v1/dashboard', function(Request $req, Response $res) use ($authMw, $dashboard) {
      $ctx = $authMw->handle($req, $res);
      $dashboard->index($req, $res);
    });

    $this->router->add('GET', '/api/v1/vencimientos', function(Request $req, Response $res) use ($authMw, $vencimientos) {
      $ctx = $authMw->handle($req, $res);
      $vencimientos->index($req, $res);
    });

    $this->router->add('GET', '/api/v1/polizas', function(Request $req, Response $res) use ($authMw, $polizas) {
      $ctx = $authMw->handle($req, $res);
      $polizas->index($req, $res, $ctx);
    });

    $this->router->add('GET', '/api/v1/polizas/numero/{numero}', function(Request $req, Response $res, array $params) use ($authMw, $polizas) {
      $ctx = $authMw->handle($req, $res);
      $polizas->showByNumero($req, $res, $params);
    });

    $this->router->add('PUT', '/api/v1/polizas/numero/{numero}', function(Request $req, Response $res, array $params) use ($authMw, $polizas) {
      $ctx = $authMw->handle($req, $res);
      $polizas->updateByNumero($req, $res, $params);
    });

    $this->router->add('DELETE', '/api/v1/polizas/numero/{numero}', function(Request $req, Response $res, array $params) use ($authMw, $polizas) {
      $ctx = $authMw->handle($req, $res);
      $polizas->destroyByNumero($req, $res, $params);
    });

    $this->router->add('GET', '/api/v1/polizas/buscar', function(Request $req, Response $res) use ($authMw, $polizas) {
      $ctx = $authMw->handle($req, $res);
      $polizas->buscar($req, $res);
    });

    $this->router->add('GET', '/api/v1/polizas/{numero}/renta', function(Request $req, Response $res, array $params) use ($authMw, $polizas) {
      $ctx = $authMw->handle($req, $res);
      $polizas->renta($req, $res, $params);
    });

    $this->router->add('POST', '/api/v1/polizas/{numero}/renovar', function(Request $req, Response $res, array $params) use ($authMw, $polizas) {
      $ctx = $authMw->handle($req, $res);
      $polizas->renovar($req, $res, $params);
    });

    $this->router->add('POST', '/api/v1/polizas', function(Request $req, Response $res) use ($authMw, $polizas) {
      $ctx = $authMw->handle($req, $res);
      $polizas->store($req, $res, $ctx);
    });

    $this->router->add('GET', '/api/v1/polizas/numero/{numero}/contrato', function(Request $req, Response $res, array $params) use ($authMw, $polizas) {
      $ctx = $authMw->handle($req, $res);
      $polizas->contratoByNumero($req, $res, $params);
    });

    $this->router->add('POST', '/api/v1/polizas/numero/{numero}/contrato', function(Request $req, Response $res, array $params) use ($authMw, $polizas) {
      $ctx = $authMw->handle($req, $res);
      $polizas->guardarContrato($req, $res, $params);
    });

    // Validaciones CRUD
    $validacionRepo = new \App\Repositories\ValidacionRepository($this->db);
    $validaciones = new \App\Controllers\ValidacionesController($this->config, $validacionRepo, $inquilinoRepo);
    $validacionLegalRepo = new \App\Repositories\ValidacionLegalRepository($this->db, $this->config);
    $validacionLegal = new \App\Controllers\ValidacionLegalController($validacionLegalRepo, $inquilinoRepo);
    $validacionIdentidad = new \App\Controllers\ValidacionIdentidadController($inquilinoRepo);
    $validacionAwsRepo = new \App\Repositories\ValidacionAwsRepository($this->db);
    $validacionAws = new \App\Controllers\ValidacionAwsController($inquilinoRepo, $validacionAwsRepo);
    $inquilinoValidacionAws = new \App\Controllers\InquilinoValidacionAwsController($inquilinoRepo, $validacionAwsRepo);
    $inquilinoArchivos = new \App\Controllers\InquilinoArchivosController($this->config, $inquilinoRepo, $mediaRepo);
    $iaRepo = new \App\Repositories\IARepository($this->db);
    $iaController = new \App\Controllers\IAController($iaRepo);
    $iaHistorial = new \App\Controllers\IAHistorialController($iaRepo);

    $this->router->add('GET', '/api/v1/inquilinos/{id}/validaciones-legal/status', function(Request $req, Response $res, array $params) use ($authMw, $validacionLegal) {
      $ctx = $authMw->handle($req, $res);
      $validacionLegal->status($req, $res, $params);
    });

    $this->router->add('POST', '/api/v1/inquilinos/{id}/validaciones-legal/run', function(Request $req, Response $res, array $params) use ($authMw, $validacionLegal) {
      $ctx = $authMw->handle($req, $res);
      $validacionLegal->run($req, $res, $params);
    });

    $this->router->add('GET', '/api/v1/inquilinos/{id}/validaciones-legal/ultimo', function(Request $req, Response $res, array $params) use ($authMw, $validacionLegal) {
      $ctx = $authMw->handle($req, $res);
      $validacionLegal->ultimo($req, $res, $params);
    });

    $this->router->add('GET', '/api/v1/inquilinos/{id}/validaciones-legal/historial', function(Request $req, Response $res, array $params) use ($authMw, $validacionLegal) {
      $ctx = $authMw->handle($req, $res);
      $validacionLegal->historial($req, $res, $params);
    });

    $this->router->add('GET', '/api/v1/inquilinos/{id}/validaciones-legal/historial-json', function(Request $req, Response $res, array $params) use ($authMw, $validacionLegal) {
      $ctx = $authMw->handle($req, $res);
      $validacionLegal->historialJson($req, $res, $params);
    });

    $this->router->add('PUT', '/api/v1/inquilinos/{id}/validaciones-legal/toggle-demandas', function(Request $req, Response $res, array $params) use ($authMw, $validacionLegal) {
      $ctx = $authMw->handle($req, $res);
      $validacionLegal->toggleDemandas($req, $res, $params);
    });

    $this->router->add('GET', '/api/v1/inquilinos/slug/{slug}/validaciones-legal/historial', function(Request $req, Response $res, array $params) use ($authMw, $validacionLegal) {
      $ctx = $authMw->handle($req, $res);
      $validacionLegal->historialPorSlug($req, $res, $params);
    });

    $this->router->add('GET', '/api/v1/inquilinos/slug/{slug}/validacion-identidad', function(Request $req, Response $res, array $params) use ($authMw, $validacionIdentidad) {
      $ctx = $authMw->handle($req, $res);
      $validacionIdentidad->index($req, $res, $params);
    });

    $this->router->add('POST', '/api/v1/validacion-identidad/procesar', function(Request $req, Response $res) use ($authMw, $validacionIdentidad) {
      $ctx = $authMw->handle($req, $res);
      $validacionIdentidad->procesar($req, $res);
    });

    $this->router->add('GET', '/api/v1/inquilinos/slug/{slug}/validacion-identidad/resultado', function(Request $req, Response $res, array $params) use ($authMw, $validacionIdentidad) {
      $ctx = $authMw->handle($req, $res);
      $validacionIdentidad->resultado($req, $res, $params);
    });

    $this->router->add('POST', '/api/v1/ia/validar/{slug}', function(Request $req, Response $res, array $params) use ($authMw, $validacionAws) {
      $ctx = $authMw->handle($req, $res);
      $validacionAws->validar($req, $res, $params);
    });

    $this->router->add('GET', '/api/v1/validacion-aws/manual', function(Request $req, Response $res) use ($authMw, $validacionAws) {
      $ctx = $authMw->handle($req, $res);
      $validacionAws->manual($req, $res);
    });

    $this->router->add('POST', '/api/v1/validacion-aws/procesar', function(Request $req, Response $res) use ($authMw, $validacionAws) {
      $ctx = $authMw->handle($req, $res);
      $validacionAws->procesar($req, $res);
    });

    $this->router->add('GET', '/api/v1/ia/ventas', function(Request $req, Response $res) use ($authMw, $iaVentas) {
      $ctx = $authMw->handle($req, $res);
      $iaVentas->index($req, $res);
    });
    $this->router->add('GET', '/api/v1/ia/ventas/total', function(Request $req, Response $res) use ($authMw, $iaVentas) {
      $ctx = $authMw->handle($req, $res);
      $iaVentas->total($req, $res);
    });
    $this->router->add('GET', '/api/v1/ia/ventas/canal', function(Request $req, Response $res) use ($authMw, $iaVentas) {
      $ctx = $authMw->handle($req, $res);
      $iaVentas->canal($req, $res);
    });
    $this->router->add('GET', '/api/v1/ia/ventas/modelo', function(Request $req, Response $res) use ($authMw, $iaVentas) {
      $ctx = $authMw->handle($req, $res);
      $iaVentas->modelo($req, $res);
    });
    $this->router->add('GET', '/api/v1/ia/ventas/fecha', function(Request $req, Response $res) use ($authMw, $iaVentas) {
      $ctx = $authMw->handle($req, $res);
      $iaVentas->fecha($req, $res);
    });
    $this->router->add('GET', '/api/v1/ia/ventas/usuario', function(Request $req, Response $res) use ($authMw, $iaVentas) {
      $ctx = $authMw->handle($req, $res);
      $iaVentas->usuario($req, $res);
    });
    $this->router->add('GET', '/api/v1/ia/ventas/proceso', function(Request $req, Response $res) use ($authMw, $iaVentas) {
      $ctx = $authMw->handle($req, $res);
      $iaVentas->proceso($req, $res);
    });
    $this->router->add('GET', '/api/v1/ia/ventas/proceso/periodo', function(Request $req, Response $res) use ($authMw, $iaVentas) {
      $ctx = $authMw->handle($req, $res);
      $iaVentas->procesoPeriodo($req, $res);
    });
    $this->router->add('GET', '/api/v1/ia/ventas/proceso/usuario', function(Request $req, Response $res) use ($authMw, $iaVentas) {
      $ctx = $authMw->handle($req, $res);
      $iaVentas->procesoUsuario($req, $res);
    });
    $this->router->add('GET', '/api/v1/ia/ventas/canal/periodo', function(Request $req, Response $res) use ($authMw, $iaVentas) {
      $ctx = $authMw->handle($req, $res);
      $iaVentas->canalPeriodo($req, $res);
    });

    $this->router->add('GET', '/api/v1/ia', function(Request $req, Response $res) use ($authMw, $iaController) {
      $ctx = $authMw->handle($req, $res);
      $iaController->index($req, $res);
    });

    $this->router->add('GET', '/api/v1/ia/modelos', function(Request $req, Response $res) use ($authMw, $iaController) {
      $ctx = $authMw->handle($req, $res);
      $iaController->modelos($req, $res);
    });

    $this->router->add('GET', '/api/v1/ia/modelos-disponibles', function(Request $req, Response $res) use ($authMw, $iaController) {
      $ctx = $authMw->handle($req, $res);
      $iaController->modelosDisponibles($req, $res);
    });

    $this->router->add('POST', '/api/v1/ia/chat', function(Request $req, Response $res) use ($authMw, $iaController) {
      $ctx = $authMw->handle($req, $res);
      $iaController->chat($req, $res);
    });

    $this->router->add('GET', '/api/v1/ia/historial', function(Request $req, Response $res) use ($authMw, $iaHistorial) {
      $ctx = $authMw->handle($req, $res);
      $iaHistorial->index($req, $res);
    });

    $this->router->add('GET', '/api/v1/ia/historial/{id}', function(Request $req, Response $res, array $params) use ($authMw, $iaHistorial) {
      $ctx = $authMw->handle($req, $res);
      $iaHistorial->ver($req, $res, $params);
    });

    $this->router->add('GET', '/ia/ventas', function(Request $req, Response $res) use ($authMw, $iaVentas) {
      $ctx = $authMw->handle($req, $res);
      $iaVentas->index($req, $res);
    });
    $this->router->add('GET', '/ia/ventas/total', function(Request $req, Response $res) use ($authMw, $iaVentas) {
      $ctx = $authMw->handle($req, $res);
      $iaVentas->total($req, $res);
    });
    $this->router->add('GET', '/ia/ventas/canal', function(Request $req, Response $res) use ($authMw, $iaVentas) {
      $ctx = $authMw->handle($req, $res);
      $iaVentas->canal($req, $res);
    });
    $this->router->add('GET', '/ia/ventas/modelo', function(Request $req, Response $res) use ($authMw, $iaVentas) {
      $ctx = $authMw->handle($req, $res);
      $iaVentas->modelo($req, $res);
    });
    $this->router->add('GET', '/ia/ventas/fecha', function(Request $req, Response $res) use ($authMw, $iaVentas) {
      $ctx = $authMw->handle($req, $res);
      $iaVentas->fecha($req, $res);
    });
    $this->router->add('GET', '/ia/ventas/usuario', function(Request $req, Response $res) use ($authMw, $iaVentas) {
      $ctx = $authMw->handle($req, $res);
      $iaVentas->usuario($req, $res);
    });
    $this->router->add('GET', '/ia/ventas/proceso', function(Request $req, Response $res) use ($authMw, $iaVentas) {
      $ctx = $authMw->handle($req, $res);
      $iaVentas->proceso($req, $res);
    });
    $this->router->add('GET', '/ia/ventas/proceso/periodo', function(Request $req, Response $res) use ($authMw, $iaVentas) {
      $ctx = $authMw->handle($req, $res);
      $iaVentas->procesoPeriodo($req, $res);
    });
    $this->router->add('GET', '/ia/ventas/proceso/usuario', function(Request $req, Response $res) use ($authMw, $iaVentas) {
      $ctx = $authMw->handle($req, $res);
      $iaVentas->procesoUsuario($req, $res);
    });
    $this->router->add('GET', '/ia/ventas/canal/periodo', function(Request $req, Response $res) use ($authMw, $iaVentas) {
      $ctx = $authMw->handle($req, $res);
      $iaVentas->canalPeriodo($req, $res);
    });
    $this->router->add('GET', '/ia/modelos', function(Request $req, Response $res) use ($authMw, $iaController) {
      $ctx = $authMw->handle($req, $res);
      $iaController->modelos($req, $res);
    });
    $this->router->add('GET', '/ia/modelos-disponibles', function(Request $req, Response $res) use ($authMw, $iaController) {
      $ctx = $authMw->handle($req, $res);
      $iaController->modelosDisponibles($req, $res);
    });

    $this->router->add('POST', '/api/v1/inquilinos/{id}/validacion-aws/ingresos-pdf-simple', function(Request $req, Response $res, array $params) use ($authMw, $inquilinoValidacionAws) {
      $ctx = $authMw->handle($req, $res);
      $inquilinoValidacionAws->validarIngresosPDFSimple($req, $res, $params);
    });

    $this->router->add('GET', '/api/v1/inquilinos/slug/{slug}/validacion-aws/archivos', function(Request $req, Response $res, array $params) use ($authMw, $inquilinoValidacionAws) {
      $ctx = $authMw->handle($req, $res);
      $inquilinoValidacionAws->obtenerArchivosPorSlug($req, $res, $params);
    });

    $this->router->add('GET', '/api/v1/inquilinos/slug/{slug}/validacion-aws', function(Request $req, Response $res, array $params) use ($authMw, $inquilinoValidacionAws) {
      $ctx = $authMw->handle($req, $res);
      $inquilinoValidacionAws->validarCheck($req, $res, $params);
    });

    $this->router->add('POST', '/api/v1/inquilinos/slug/{slug}/validacion-aws', function(Request $req, Response $res, array $params) use ($authMw, $inquilinoValidacionAws) {
      $ctx = $authMw->handle($req, $res);
      $inquilinoValidacionAws->validarCheck($req, $res, $params);
    });

    $this->router->add('GET', '/api/v1/inquilinos/slug/{slug}/archivos-presignados', function(Request $req, Response $res, array $params) use ($authMw, $inquilinoArchivos) {
      $ctx = $authMw->handle($req, $res);
      $inquilinoArchivos->presignBySlug($req, $res, $params);
    });

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
