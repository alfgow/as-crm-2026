<?php
namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\AsesorRepository;

final class AsesoresController {
  private array $config;
  private AsesorRepository $asesores;

  public function __construct(array $config, AsesorRepository $asesores) {
    $this->config = $config;
    $this->asesores = $asesores;
  }

  public function index(Request $req, Response $res, array $ctx): void {
    $all = $this->asesores->findAll();

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
    $body = $req->getJson();
    
    // Validar requeridos
    if (empty($body['nombre_asesor']) || empty($body['email']) || empty($body['celular'])) {
        $res->json([
            'data' => null,
            'meta' => ['requestId' => $req->getRequestId()],
            'errors' => [['code' => 'validation_error', 'message' => 'Missing required fields']]
        ], 400);
    }

    try {
        $id = $this->asesores->create($body);
        $item = $this->asesores->findById($id);

        $res->json([
            'data' => $item,
            'meta' => ['requestId' => $req->getRequestId()],
            'errors' => []
        ], 201);
    } catch (\Throwable $e) {
        $res->json([
            'data' => null,
            'meta' => ['requestId' => $req->getRequestId()],
            'errors' => [['code' => 'db_error', 'message' => $e->getMessage()]]
        ], 500);
    }
  }

  public function show(Request $req, Response $res, array $params): void {
      $id = (int)($params['id'] ?? 0);
      $item = $this->asesores->findById($id);

      if (!$item) {
          $res->json([
              'data' => null,
              'meta' => ['requestId' => $req->getRequestId()],
              'errors' => [['code' => 'not_found', 'message' => 'Asesor not found']]
          ], 404);
      }

      $res->json([
          'data' => $item,
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

      $this->asesores->update($id, $body);
      $updated = $this->asesores->findById($id);

      $res->json([
          'data' => $updated,
          'meta' => ['requestId' => $req->getRequestId()],
          'errors' => []
      ]);
  }

  public function destroy(Request $req, Response $res, array $params): void {
      $id = (int)($params['id'] ?? 0);
      $this->asesores->delete($id);

      $res->json([
          'data' => ['success' => true, 'id' => $id],
          'meta' => ['requestId' => $req->getRequestId()],
          'errors' => []
      ]);
  }

  public function deleteBulk(Request $req, Response $res): void {
      $body = $req->getJson();
      $ids = $body['ids'] ?? [];

      if (!is_array($ids)) {
          $res->json([
              'data' => null,
              'meta' => ['requestId' => $req->getRequestId()],
              'errors' => [['code' => 'bad_request', 'message' => 'ids must be an array']]
          ], 400);
          return;
      }

      $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn ($id) => $id > 0)));

      if (empty($ids)) {
          $res->json([
              'data' => null,
              'meta' => ['requestId' => $req->getRequestId()],
              'errors' => [['code' => 'bad_request', 'message' => 'ids is required']]
          ], 400);
          return;
      }

      $deleted = $this->asesores->deleteBulk($ids);

      $res->json([
          'data' => ['success' => true, 'deleted' => $deleted, 'ids' => $ids],
          'meta' => ['requestId' => $req->getRequestId()],
          'errors' => []
      ]);
  }
}
