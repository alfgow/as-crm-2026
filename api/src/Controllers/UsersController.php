<?php
namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\UserRepository;

final class UsersController {
  private array $config;
  private UserRepository $users;

  public function __construct(array $config, UserRepository $users) {
    $this->config = $config;
    $this->users = $users;
  }

  public function me(Request $req, Response $res, array $ctx): void {
    $user = $this->users->findById($ctx['userId']);
    if (!$user) {
      $res->json([
        'data' => null,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [[ 'code' => 'not_found', 'message' => 'User not found' ]],
      ], 404);
    }

    unset($user['password']); // nunca regreses hashes
    // Compatibilidad: algunos frontends esperan `data.user`,
    // mientras otros consumen `data` directamente como objeto usuario.
    $data = $user;
    $data['user'] = $user;

    $res->json([
      'data' => $data,
      'meta' => ['requestId' => $req->getRequestId()],
      'errors' => [],
    ]);
  }
  public function index(Request $req, Response $res, array $ctx): void {
    // TODO: Pagination support
    $all = $this->users->findAll();
    
    // Clean passwords
    $all = array_map(function($u) {
      unset($u['password']);
      return $u;
    }, $all);

    $res->json([
      'data' => $all,
      'meta' => [
        'requestId' => $req->getRequestId(),
        'count' => count($all)
      ],
      'errors' => [],
    ]);
  }

  public function store(Request $req, Response $res, array $ctx): void {
    // Only admins? 
    // if ($ctx['role'] !== 'admin') ...

    $body = $req->getJson();
    // Validate required fields
    if (empty($body['email']) || empty($body['password']) || empty($body['nombre_usuario'])) {
        $res->json([
            'data' => null,
            'meta' => ['requestId' => $req->getRequestId()],
            'errors' => [['code' => 'validation_error', 'message' => 'Missing required fields']]
        ], 400);
    }

    try {
        $id = $this->users->create($body);
        $user = $this->users->findById($id);
        unset($user['password']);

        $res->json([
            'data' => $user,
            'meta' => ['requestId' => $req->getRequestId()],
            'errors' => []
        ], 201);
    } catch (\Throwable $e) {
        // e.g. duplicate email
        $res->json([
            'data' => null,
            'meta' => ['requestId' => $req->getRequestId()],
            'errors' => [['code' => 'db_error', 'message' => $e->getMessage()]]
        ], 500);
    }
  }

  public function show(Request $req, Response $res, array $params): void {
      // params contains route params if router logic supports it, e.g. ['id' => 123]
      // But my simple router in App.php passes params as last arg
      // Need to verifying how App.php calls this. `handler($req, $res, $params)`
      $id = (int)($params['id'] ?? 0);
      $user = $this->users->findById($id);

      if (!$user) {
          $res->json([
              'data' => null,
              'meta' => ['requestId' => $req->getRequestId()],
              'errors' => [['code' => 'not_found', 'message' => 'User not found']]
          ], 404);
      }

      unset($user['password']);
      $res->json([
          'data' => $user,
          'meta' => ['requestId' => $req->getRequestId()],
          'errors' => []
      ]);
  }

  public function update(Request $req, Response $res, array $params): void {
      $id = (int)($params['id'] ?? 0);
      $body = $req->getJson();

      if (empty($body)) {
          $res->json([
              'data' => null,
              'meta' => ['requestId' => $req->getRequestId()],
              'errors' => [['code' => 'bad_request', 'message' => 'No data to update']]
          ], 400);
      }

      $this->users->update($id, $body);
      $updated = $this->users->findById($id);
      unset($updated['password']);

      $res->json([
          'data' => $updated,
          'meta' => ['requestId' => $req->getRequestId()],
          'errors' => []
      ]);
  }

  public function destroy(Request $req, Response $res, array $params): void {
      $id = (int)($params['id'] ?? 0);
      $this->users->delete($id);

      $res->json([
          'data' => ['success' => true, 'id' => $id],
          'meta' => ['requestId' => $req->getRequestId()],
          'errors' => []
      ]);
  }
}  
