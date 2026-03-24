<?php
namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\AsesorProspectadoRepository;

final class AsesoresProspectadosController {
  private AsesorProspectadoRepository $prospectados;

  public function __construct(AsesorProspectadoRepository $prospectados) {
    $this->prospectados = $prospectados;
  }

  public function index(Request $req, Response $res, array $ctx): void {
    $items = $this->prospectados->findAll();

    $res->json([
      'data' => $items,
      'meta' => [
        'requestId' => $req->getRequestId(),
        'count' => count($items),
      ],
      'errors' => [],
    ]);
  }

  public function show(Request $req, Response $res, array $params): void {
    $id = (int)($params['id'] ?? 0);
    if ($id <= 0) {
      $this->badRequest($req, $res, 'id inválido');
      return;
    }

    $item = $this->prospectados->findById($id);
    if (!$item) {
      $this->notFound($req, $res, 'Prospecto no encontrado');
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
    $telefono = trim((string)($body['telefono'] ?? ''));

    if ($telefono === '') {
      $this->validationError($req, $res, 'telefono', 'El campo telefono es obligatorio');
      return;
    }

    if (!array_key_exists('fecha', $body) || trim((string)$body['fecha']) === '') {
      $body['fecha'] = date('Y-m-d H:i:s');
    }

    try {
      $created = $this->prospectados->create($body);
      $res->json([
        'data' => $created,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [],
      ], 201);
    } catch (\Throwable $e) {
      $this->handlePersistenceError($req, $res, $e, 'Error al crear el prospecto');
    }
  }

  public function update(Request $req, Response $res, array $params): void {
    $id = (int)($params['id'] ?? 0);
    $body = $req->getJson() ?? [];

    if ($id <= 0) {
      $this->badRequest($req, $res, 'id inválido');
      return;
    }

    if ($body === []) {
      $this->badRequest($req, $res, 'No hay datos para actualizar');
      return;
    }

    if (array_key_exists('telefono', $body) && trim((string)$body['telefono']) === '') {
      $this->validationError($req, $res, 'telefono', 'El campo telefono no puede ir vacío');
      return;
    }

    try {
      $updated = $this->prospectados->update($id, $body);
      if (!$updated) {
        $this->notFound($req, $res, 'Prospecto no encontrado');
        return;
      }

      $res->json([
        'data' => $updated,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [],
      ]);
    } catch (\Throwable $e) {
      $this->handlePersistenceError($req, $res, $e, 'Error al actualizar el prospecto');
    }
  }

  public function destroy(Request $req, Response $res, array $params): void {
    $id = (int)($params['id'] ?? 0);
    if ($id <= 0) {
      $this->badRequest($req, $res, 'id inválido');
      return;
    }

    try {
      $deleted = $this->prospectados->delete($id);
      if (!$deleted) {
        $this->notFound($req, $res, 'Prospecto no encontrado');
        return;
      }

      $res->json([
        'data' => ['success' => true, 'id' => $id],
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [],
      ]);
    } catch (\Throwable $e) {
      $this->handlePersistenceError($req, $res, $e, 'Error al eliminar el prospecto');
    }
  }

  private function resolveSqlError(\Throwable $e): string {
    if ($e instanceof \PDOException && $e->getCode() === '23000') {
      return 'conflict';
    }
    return 'server_error';
  }

  private function handlePersistenceError(Request $req, Response $res, \Throwable $e, string $message): void {
    $code = $this->resolveSqlError($e);
    $status = $code === 'conflict' ? 409 : 500;

    $res->json([
      'data' => null,
      'meta' => ['requestId' => $req->getRequestId()],
      'errors' => [[
        'code' => $code,
        'message' => $message,
        'details' => $e->getMessage(),
      ]],
    ], $status);
  }

  private function badRequest(Request $req, Response $res, string $message): void {
    $res->json([
      'data' => null,
      'meta' => ['requestId' => $req->getRequestId()],
      'errors' => [['code' => 'bad_request', 'message' => $message]],
    ], 400);
  }

  private function validationError(Request $req, Response $res, string $field, string $message): void {
    $res->json([
      'data' => null,
      'meta' => ['requestId' => $req->getRequestId()],
      'errors' => [[
        'code' => 'validation_error',
        'field' => $field,
        'message' => $message,
      ]],
    ], 400);
  }

  private function notFound(Request $req, Response $res, string $message): void {
    $res->json([
      'data' => null,
      'meta' => ['requestId' => $req->getRequestId()],
      'errors' => [['code' => 'not_found', 'message' => $message]],
    ], 404);
  }
}
