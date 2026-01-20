<?php
namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\InmuebleRepository;

final class InmueblesController {
  private array $config;
  private InmuebleRepository $inmuebles;

  public function __construct(array $config, InmuebleRepository $inmuebles) {
    $this->config = $config;
    $this->inmuebles = $inmuebles;
  }

  public function index(Request $req, Response $res, array $ctx): void {
    $all = $this->inmuebles->findAll();

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
    
    // Validar campos requeridos (según NOT NULL de schema)
    $required = [
        'id_arrendador', 'id_asesor', 'direccion_inmueble', 
        'tipo', 'renta'
    ];
    
    foreach ($required as $field) {
        if (empty($body[$field])) {
             $res->json([
                'data' => null,
                'meta' => ['requestId' => $req->getRequestId()],
                'errors' => [['code' => 'validation_error', 'message' => "Missing required field: $field"]]
            ], 400);
        }
    }

    try {
        $id = $this->inmuebles->create($body);
        $item = $this->inmuebles->findById($id);

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
      $item = $this->inmuebles->findById($id);

      if (!$item) {
          $res->json([
              'data' => null,
              'meta' => ['requestId' => $req->getRequestId()],
              'errors' => [['code' => 'not_found', 'message' => 'Inmueble not found']]
          ], 404);
      }

      $res->json([
          'data' => $item,
          'meta' => ['requestId' => $req->getRequestId()],
          'errors' => []
      ]);
  }

  public function info(Request $req, Response $res, array $params): void {
      $id = (int)($params['id'] ?? 0);

      if ($id <= 0) {
          $res->json([
              'data' => null,
              'meta' => ['requestId' => $req->getRequestId()],
              'errors' => [['code' => 'bad_request', 'message' => 'Invalid inmueble id']]
          ], 400);
          return;
      }

      $item = $this->inmuebles->findById($id);

      if (!$item) {
          $res->json([
              'data' => null,
              'meta' => ['requestId' => $req->getRequestId()],
              'errors' => [['code' => 'not_found', 'message' => 'Inmueble not found']]
          ], 404);
          return;
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

      $this->inmuebles->update($id, $body);
      $updated = $this->inmuebles->findById($id);

      $res->json([
          'data' => $updated,
          'meta' => ['requestId' => $req->getRequestId()],
          'errors' => []
      ]);
  }

  public function destroy(Request $req, Response $res, array $params): void {
      $id = (int)($params['id'] ?? 0);
      $this->inmuebles->delete($id);

      $res->json([
          'data' => ['success' => true, 'id' => $id],
          'meta' => ['requestId' => $req->getRequestId()],
          'errors' => []
      ]);
  }

  public function byArrendador(Request $req, Response $res, array $params): void {
      $arrendadorId = (int)($params['id'] ?? 0);

      if ($arrendadorId <= 0) {
          $res->json([
              'data' => [],
              'meta' => ['requestId' => $req->getRequestId()],
              'errors' => [['code' => 'bad_request', 'message' => 'Invalid arrendador id']]
          ], 400);
          return;
      }

      $items = $this->inmuebles->findByArrendadorId($arrendadorId);

      $res->json([
          'data' => $items,
          'meta' => ['requestId' => $req->getRequestId(), 'count' => count($items)],
          'errors' => []
      ]);
  }
}
