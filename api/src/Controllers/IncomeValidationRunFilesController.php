<?php
namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\IncomeValidationRunFilesRepository;

final class IncomeValidationRunFilesController {
  private IncomeValidationRunFilesRepository $files;

  public function __construct(IncomeValidationRunFilesRepository $files) {
    $this->files = $files;
  }

  public function index(Request $req, Response $res, array $ctx): void {
    $query = $req->getQuery();
    $filters = [
      'run_id' => $query['run_id'] ?? null,
      'archivo_id' => $query['archivo_id'] ?? null,
      'status' => $query['status'] ?? null,
      'tipo' => $query['tipo'] ?? null,
    ];

    $items = $this->files->findAll($filters);

    $res->json([
      'data' => $items,
      'meta' => [
        'requestId' => $req->getRequestId(),
        'count' => count($items),
        'filters' => $filters,
      ],
      'errors' => [],
    ]);
  }

  public function show(Request $req, Response $res, array $params): void {
    $id = (int)($params['id'] ?? 0);
    if ($id <= 0) {
      $res->json([
        'data' => null,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [['code' => 'bad_request', 'message' => 'id inválido']],
      ], 400);
      return;
    }

    $item = $this->files->findById($id);
    if (!$item) {
      $res->json([
        'data' => null,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [['code' => 'not_found', 'message' => 'Archivo no encontrado']],
      ], 404);
      return;
    }

    $res->json([
      'data' => $item,
      'meta' => ['requestId' => $req->getRequestId()],
      'errors' => [],
    ]);
  }

  public function store(Request $req, Response $res, array $ctx): void {
    $body = $req->getJson() ?? [];
    $required = ['run_id', 'archivo_id', 's3_key', 'tipo'];

    foreach ($required as $field) {
      if (!isset($body[$field]) || $body[$field] === '') {
        $res->json([
          'data' => null,
          'meta' => ['requestId' => $req->getRequestId()],
          'errors' => [['code' => 'validation_error', 'field' => $field, 'message' => "El campo {$field} es obligatorio"]],
        ], 400);
        return;
      }
    }

    try {
      $created = $this->files->create($body);
      $res->json([
        'data' => $created,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [],
      ], 201);
    } catch (\Throwable $e) {
      $code = $this->resolveSqlError($e);
      $res->json([
        'data' => null,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [['code' => $code, 'message' => 'Error al crear el archivo del run', 'details' => $e->getMessage()]],
      ], $code === 'conflict' ? 409 : 500);
    }
  }

  public function update(Request $req, Response $res, array $params): void {
    $id = (int)($params['id'] ?? 0);
    $body = $req->getJson() ?? [];

    if ($id <= 0) {
      $res->json([
        'data' => null,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [['code' => 'bad_request', 'message' => 'id inválido']],
      ], 400);
      return;
    }

    if (!$body) {
      $res->json([
        'data' => null,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [['code' => 'bad_request', 'message' => 'No hay datos para actualizar']],
      ], 400);
      return;
    }

    try {
      $updated = $this->files->update($id, $body);
      if (!$updated) {
        $res->json([
          'data' => null,
          'meta' => ['requestId' => $req->getRequestId()],
          'errors' => [['code' => 'not_found', 'message' => 'Archivo no encontrado']],
        ], 404);
        return;
      }

      $res->json([
        'data' => $updated,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [],
      ]);
    } catch (\Throwable $e) {
      $code = $this->resolveSqlError($e);
      $res->json([
        'data' => null,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [['code' => $code, 'message' => 'Error al actualizar el archivo del run', 'details' => $e->getMessage()]],
      ], $code === 'conflict' ? 409 : 500);
    }
  }

  public function destroy(Request $req, Response $res, array $params): void {
    $id = (int)($params['id'] ?? 0);

    if ($id <= 0) {
      $res->json([
        'data' => null,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [['code' => 'bad_request', 'message' => 'id inválido']],
      ], 400);
      return;
    }

    $deleted = $this->files->delete($id);
    if (!$deleted) {
      $res->json([
        'data' => null,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [['code' => 'not_found', 'message' => 'Archivo no encontrado']],
      ], 404);
      return;
    }

    $res->json([
      'data' => ['success' => true, 'id' => $id],
      'meta' => ['requestId' => $req->getRequestId()],
      'errors' => [],
    ]);
  }

  private function resolveSqlError(\Throwable $e): string {
    if ($e instanceof \PDOException && $e->getCode() === '23000') {
      return 'conflict';
    }
    return 'server_error';
  }
}
