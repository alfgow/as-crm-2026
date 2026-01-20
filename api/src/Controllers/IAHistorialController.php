<?php
namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\IARepository;

final class IAHistorialController {
  private IARepository $ia;

  public function __construct(IARepository $ia) {
    $this->ia = $ia;
  }

  public function index(Request $req, Response $res): void {
    $query = $req->getQuery();
    $limit = isset($query['limit']) ? (int)$query['limit'] : 50;
    $offset = isset($query['offset']) ? (int)$query['offset'] : 0;
    $items = $this->ia->listar($limit, $offset);

    $res->json([
      'data' => $items,
      'meta' => [
        'requestId' => $req->getRequestId(),
        'count' => count($items),
      ],
      'errors' => [],
    ]);
  }

  public function ver(Request $req, Response $res, array $params): void {
    $id = (int)($params['id'] ?? 0);
    if ($id <= 0) {
      $res->json([
        'data' => null,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [['code' => 'bad_request', 'message' => 'id inválido']],
      ], 400);
      return;
    }

    $row = $this->ia->obtener($id);
    if (!$row) {
      $res->json([
        'data' => null,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [['code' => 'not_found', 'message' => 'Interacción no encontrada']],
      ], 404);
      return;
    }

    $res->json([
      'data' => $row,
      'meta' => ['requestId' => $req->getRequestId()],
      'errors' => [],
    ]);
  }
}
