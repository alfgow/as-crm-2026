<?php
namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\AsesorProspectadoComentarioRepository;
use App\Repositories\AsesorProspectadoRepository;

final class AsesoresProspectadosComentariosController {
  private AsesorProspectadoRepository $prospectados;
  private AsesorProspectadoComentarioRepository $comentarios;

  public function __construct(
    AsesorProspectadoRepository $prospectados,
    AsesorProspectadoComentarioRepository $comentarios
  ) {
    $this->prospectados = $prospectados;
    $this->comentarios = $comentarios;
  }

  public function index(Request $req, Response $res, array $ctx): void {
    $query = $req->getQuery();
    $filters = [];

    if (isset($query['id_prospecto']) && (int)$query['id_prospecto'] > 0) {
      $filters['id_prospecto'] = (int)$query['id_prospecto'];
    }

    $items = $this->comentarios->findAll($filters);

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

  public function byProspecto(Request $req, Response $res, array $params): void {
    $id = (int)($params['id'] ?? 0);
    if ($id <= 0) {
      $this->badRequest($req, $res, 'id inválido');
      return;
    }

    if (!$this->prospectados->findById($id)) {
      $this->notFound($req, $res, 'Prospecto no encontrado');
      return;
    }

    $items = $this->comentarios->findAll(['id_prospecto' => $id]);

    $res->json([
      'data' => $items,
      'meta' => [
        'requestId' => $req->getRequestId(),
        'count' => count($items),
        'filters' => ['id_prospecto' => $id],
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

    $item = $this->comentarios->findById($id);
    if (!$item) {
      $this->notFound($req, $res, 'Comentario no encontrado');
      return;
    }

    $res->json([
      'data' => $item,
      'meta' => ['requestId' => $req->getRequestId()],
      'errors' => [],
    ]);
  }

  public function store(Request $req, Response $res, array $ctx): void {
    $this->createFromPayload($req, $res, $req->getJson() ?? []);
  }

  public function storeByProspecto(Request $req, Response $res, array $params): void {
    $id = (int)($params['id'] ?? 0);
    $body = $req->getJson() ?? [];
    $body['id_prospecto'] = $id;
    $this->createFromPayload($req, $res, $body);
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

    if (array_key_exists('comentario', $body) && trim((string)$body['comentario']) === '') {
      $this->validationError($req, $res, 'comentario', 'El campo comentario no puede ir vacío');
      return;
    }

    if (array_key_exists('id_prospecto', $body)) {
      $idProspecto = (int)$body['id_prospecto'];
      if ($idProspecto <= 0) {
        $this->validationError($req, $res, 'id_prospecto', 'El campo id_prospecto es inválido');
        return;
      }
      if (!$this->prospectados->findById($idProspecto)) {
        $this->notFound($req, $res, 'Prospecto no encontrado');
        return;
      }
    }

    try {
      $updated = $this->comentarios->update($id, $body);
      if (!$updated) {
        $this->notFound($req, $res, 'Comentario no encontrado');
        return;
      }

      $res->json([
        'data' => $updated,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [],
      ]);
    } catch (\Throwable $e) {
      $this->handlePersistenceError($req, $res, $e, 'Error al actualizar el comentario');
    }
  }

  public function destroy(Request $req, Response $res, array $params): void {
    $id = (int)($params['id'] ?? 0);
    if ($id <= 0) {
      $this->badRequest($req, $res, 'id inválido');
      return;
    }

    try {
      $deleted = $this->comentarios->delete($id);
      if (!$deleted) {
        $this->notFound($req, $res, 'Comentario no encontrado');
        return;
      }

      $res->json([
        'data' => ['success' => true, 'id' => $id],
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [],
      ]);
    } catch (\Throwable $e) {
      $this->handlePersistenceError($req, $res, $e, 'Error al eliminar el comentario');
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

  private function createFromPayload(Request $req, Response $res, array $body): void {
    $idProspecto = (int)($body['id_prospecto'] ?? 0);
    $comentario = trim((string)($body['comentario'] ?? ''));

    if ($idProspecto <= 0) {
      $this->validationError($req, $res, 'id_prospecto', 'El campo id_prospecto es obligatorio');
      return;
    }
    if ($comentario === '') {
      $this->validationError($req, $res, 'comentario', 'El campo comentario es obligatorio');
      return;
    }
    if (!$this->prospectados->findById($idProspecto)) {
      $this->notFound($req, $res, 'Prospecto no encontrado');
      return;
    }

    if (!array_key_exists('fecha', $body) || trim((string)$body['fecha']) === '') {
      $body['fecha'] = date('Y-m-d H:i:s');
    }

    try {
      $created = $this->comentarios->create($body);
      $res->json([
        'data' => $created,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [],
      ], 201);
    } catch (\Throwable $e) {
      $this->handlePersistenceError($req, $res, $e, 'Error al crear el comentario');
    }
  }
}
