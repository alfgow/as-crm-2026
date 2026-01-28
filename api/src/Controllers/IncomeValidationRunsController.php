<?php
namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\IncomeValidationRunFilesRepository;
use App\Repositories\IncomeValidationRunsRepository;

final class IncomeValidationRunsController {
  private IncomeValidationRunsRepository $runs;
  private IncomeValidationRunFilesRepository $runFiles;

  public function __construct(IncomeValidationRunsRepository $runs, IncomeValidationRunFilesRepository $runFiles) {
    $this->runs = $runs;
    $this->runFiles = $runFiles;
  }

  public function index(Request $req, Response $res, array $ctx): void {
    $query = $req->getQuery();
    $filters = [
      'run_id' => $query['run_id'] ?? null,
      'prospecto_id' => $query['prospecto_id'] ?? null,
      'status' => $query['status'] ?? null,
      'idempotency_key' => $query['idempotency_key'] ?? null,
    ];

    $items = $this->runs->findAll($filters);

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

    $item = $this->runs->findById($id);
    if (!$item) {
      $res->json([
        'data' => null,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [['code' => 'not_found', 'message' => 'Run no encontrado']],
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
    $required = ['run_id', 'prospecto_id', 'idempotency_key'];

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
      $created = $this->runs->create($body);
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
        'errors' => [['code' => $code, 'message' => 'Error al crear el run', 'details' => $e->getMessage()]],
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
      $updated = $this->runs->update($id, $body);
      if (!$updated) {
        $res->json([
          'data' => null,
          'meta' => ['requestId' => $req->getRequestId()],
          'errors' => [['code' => 'not_found', 'message' => 'Run no encontrado']],
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
        'errors' => [['code' => $code, 'message' => 'Error al actualizar el run', 'details' => $e->getMessage()]],
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

    $deleted = $this->runs->delete($id);
    if (!$deleted) {
      $res->json([
        'data' => null,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [['code' => 'not_found', 'message' => 'Run no encontrado']],
      ], 404);
      return;
    }

    $res->json([
      'data' => ['success' => true, 'id' => $id],
      'meta' => ['requestId' => $req->getRequestId()],
      'errors' => [],
    ]);
  }

  public function evaluateStatus(Request $req, Response $res, array $params): void {
    $runId = $params['run_id'] ?? '';

    if ($runId === '') {
      $res->json([
        'data' => null,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [['code' => 'bad_request', 'message' => 'run_id inválido']],
      ], 400);
      return;
    }

    $run = $this->runs->findByRunId($runId);
    if (!$run) {
      $res->json([
        'data' => null,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [['code' => 'not_found', 'message' => 'Run no encontrado']],
      ], 404);
      return;
    }

    $files = $this->runFiles->findAll(['run_id' => $runId]);
    $status = $this->resolveRunStatus($files);

    try {
      $updated = $this->runs->update((int)$run['id'], ['status' => $status]);
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
        'errors' => [['code' => $code, 'message' => 'Error al actualizar el run', 'details' => $e->getMessage()]],
      ], $code === 'conflict' ? 409 : 500);
    }
  }

  public function close(Request $req, Response $res, array $params): void {
    $runId = trim($params['run_id'] ?? '');
    if ($runId === '') {
      $res->json([
        'data' => null,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [['code' => 'bad_request', 'message' => 'run_id inválido']],
      ], 400);
      return;
    }

    $body = $req->getJson() ?? [];
    $status = $body['status'] ?? '';
    if ($status === '') {
      $res->json([
        'data' => null,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [['code' => 'validation_error', 'field' => 'status', 'message' => 'El campo status es obligatorio']],
      ], 400);
      return;
    }

    $status = strtoupper((string)$status);
    $allowed = ['APPROVED', 'REVIEW', 'REJECTED', 'INSUFFICIENT_DATA'];
    if (!in_array($status, $allowed, true)) {
      $res->json([
        'data' => null,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [['code' => 'validation_error', 'field' => 'status', 'message' => 'status inválido']],
      ], 400);
      return;
    }

    $run = $this->runs->findByRunId($runId);
    if (!$run) {
      $res->json([
        'data' => null,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [['code' => 'not_found', 'message' => 'Run no encontrado']],
      ], 404);
      return;
    }

    $body['status'] = $status;

    try {
      $updated = $this->runs->updateByRunId($runId, $body, true);
      if (!$updated) {
        $res->json([
          'data' => null,
          'meta' => ['requestId' => $req->getRequestId()],
          'errors' => [['code' => 'not_found', 'message' => 'Run no encontrado']],
        ], 404);
        return;
      }

      $updated['message'] = 'Run cerrado exitosamente';
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
        'errors' => [['code' => $code, 'message' => 'Error al cerrar el run', 'details' => $e->getMessage()]],
      ], $code === 'conflict' ? 409 : 500);
    }
  }

  private function resolveSqlError(\Throwable $e): string {
    if ($e instanceof \PDOException && $e->getCode() === '23000') {
      return 'conflict';
    }
    return 'server_error';
  }

  private function resolveRunStatus(array $files): string {
    $hasFailed = false;
    $hasProcessing = false;

    foreach ($files as $file) {
      $status = $file['status'] ?? '';
      if ($status === 'failed') {
        $hasFailed = true;
        break;
      }
      if ($status === 'queued' || $status === 'processing') {
        $hasProcessing = true;
      }
    }

    if ($hasFailed) {
      return 'failed';
    }

    if ($hasProcessing) {
      return 'processing';
    }

    return 'done';
  }
}
