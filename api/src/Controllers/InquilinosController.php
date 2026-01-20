<?php
namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\InquilinoRepository;

final class InquilinosController {
  private array $config;
  private InquilinoRepository $inquilinos;

  public function __construct(array $config, InquilinoRepository $inquilinos) {
    $this->config = $config;
    $this->inquilinos = $inquilinos;
  }

  public function index(Request $req, Response $res, array $ctx): void {
    $search = $req->getQuery()['search'] ?? null;
    $all = $this->inquilinos->findAll($search);
    $res->json([
      'data' => $all,
      'meta' => ['requestId' => $req->getRequestId(), 'count' => count($all)],
      'errors' => [],
    ]);
  }

  public function store(Request $req, Response $res, array $ctx): void {
    $body = $req->getJson();

    // Required fields for main table
    $required = ['nombre_inquilino', 'apellidop_inquilino', 'email', 'id_asesor', 'tipo', 'celular', 'nacionalidad', 'tipo_id'];
    foreach ($required as $field) {
        if (empty($body[$field])) {
             $res->json(['data' => null, 'meta' => ['requestId' => $req->getRequestId()], 'errors' => [['code' => 'validation_error', 'message' => "Missing required field: $field"]]], 400);
        }
    }

    $validTypes = ['Arrendatario', 'Obligado Solidario', 'Fiador'];
    if (!in_array($body['tipo'], $validTypes)) {
        $res->json(['data' => null, 'meta' => ['requestId' => $req->getRequestId()], 'errors' => [['code' => 'validation_error', 'message' => 'Invalid tipo. Allowed values: ' . implode(', ', $validTypes)]]], 400);
    }

    try {
        $id = $this->inquilinos->create($body);
        
        // Handle nested data creation immediately?
        if (!empty($body['direccion']) && is_array($body['direccion'])) {
            $this->inquilinos->updateDireccion($id, $body['direccion']);
        }
        if (!empty($body['trabajo']) && is_array($body['trabajo'])) {
            $this->inquilinos->updateTrabajo($id, $body['trabajo']);
        }
        if (!empty($body['fiador']) && is_array($body['fiador'])) {
            $this->inquilinos->updateFiador($id, $body['fiador']);
        }
        if (!empty($body['historial_vivienda']) && is_array($body['historial_vivienda'])) {
            $this->inquilinos->updateHistorial($id, $body['historial_vivienda']);
        }

        $item = $this->inquilinos->findById($id);

        $res->json(['data' => $item, 'meta' => ['requestId' => $req->getRequestId()], 'errors' => []], 201);
    } catch (\Throwable $e) {
        $res->json(['data' => null, 'meta' => ['requestId' => $req->getRequestId()], 'errors' => [['code' => 'db_error', 'message' => $e->getMessage()]]], 500);
    }
  }

  public function show(Request $req, Response $res, array $params): void {
      $id = (int)($params['id'] ?? 0);
      $item = $this->inquilinos->findById($id);

      if (!$item) {
          $res->json(['data' => null, 'meta' => ['requestId' => $req->getRequestId()], 'errors' => [['code' => 'not_found', 'message' => 'Inquilino not found']]], 404);
      }

      $res->json(['data' => $item, 'meta' => ['requestId' => $req->getRequestId()], 'errors' => []]);
  }

  public function update(Request $req, Response $res, array $params): void {
      $id = (int)($params['id'] ?? 0);
      $body = $req->getJson();

      if (empty($body)) {
          $res->json(['data' => null, 'meta' => ['requestId' => $req->getRequestId()], 'errors' => [['code' => 'bad_request', 'message' => 'No data to update']]], 400);
      }
      
      // Update Main
      $this->inquilinos->update($id, $body);

      // Update Nested if present
      if (!empty($body['direccion']) && is_array($body['direccion'])) {
          $this->inquilinos->updateDireccion($id, $body['direccion']);
      }
      if (!empty($body['trabajo']) && is_array($body['trabajo'])) {
          $this->inquilinos->updateTrabajo($id, $body['trabajo']);
      }
      if (!empty($body['fiador']) && is_array($body['fiador'])) {
          $this->inquilinos->updateFiador($id, $body['fiador']);
      }
      if (!empty($body['historial_vivienda']) && is_array($body['historial_vivienda'])) {
          $this->inquilinos->updateHistorial($id, $body['historial_vivienda']);
      }

      $updated = $this->inquilinos->findById($id);
      $res->json(['data' => $updated, 'meta' => ['requestId' => $req->getRequestId()], 'errors' => []]);
  }

  public function destroy(Request $req, Response $res, array $params): void {
      $id = (int)($params['id'] ?? 0);
      $this->inquilinos->delete($id);
      $res->json(['data' => ['success' => true, 'id' => $id], 'meta' => ['requestId' => $req->getRequestId()], 'errors' => []]);
  }

  public function showBySlug(Request $req, Response $res, array $params): void {
      $slug = trim((string)($params['slug'] ?? ''));

      if ($slug === '') {
          $res->json(['data' => null, 'meta' => ['requestId' => $req->getRequestId()], 'errors' => [['code' => 'bad_request', 'message' => 'Slug is required']]], 400);
          return;
      }

      $item = $this->inquilinos->findBySlug($slug);

      if (!$item) {
          $res->json(['data' => null, 'meta' => ['requestId' => $req->getRequestId()], 'errors' => [['code' => 'not_found', 'message' => 'Inquilino not found']]], 404);
          return;
      }

      $res->json(['data' => $item, 'meta' => ['requestId' => $req->getRequestId()], 'errors' => []]);
  }

  public function updateStatus(Request $req, Response $res, array $params): void {
      $id = (int)($params['id'] ?? 0);
      $body = $req->getJson();
      $status = trim((string)($body['status'] ?? ''));

      if ($id <= 0 || $status === '') {
          $res->json([
              'data' => null,
              'meta' => ['requestId' => $req->getRequestId()],
              'errors' => [['code' => 'bad_request', 'message' => 'id and status are required']]
          ], 400);
          return;
      }

      if (!in_array($status, ['1', '2', '3', '4'], true)) {
          $status = '1';
      }

      $this->inquilinos->update($id, ['status' => $status]);
      $updated = $this->inquilinos->findById($id);

      $res->json([
          'data' => $updated,
          'meta' => ['requestId' => $req->getRequestId()],
          'errors' => []
      ]);
  }
}
