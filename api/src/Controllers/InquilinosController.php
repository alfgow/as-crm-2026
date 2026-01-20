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

  public function archivos(Request $req, Response $res, array $params): void {
      $id = (int)($params['id'] ?? 0);

      if ($id <= 0) {
          $res->json([
              'data' => [],
              'meta' => ['requestId' => $req->getRequestId()],
              'errors' => [['code' => 'bad_request', 'message' => 'Invalid inquilino id']]
          ], 400);
          return;
      }

      $item = $this->inquilinos->findById($id);
      if (!$item) {
          $res->json([
              'data' => null,
              'meta' => ['requestId' => $req->getRequestId()],
              'errors' => [['code' => 'not_found', 'message' => 'Inquilino not found']]
          ], 404);
          return;
      }

      $archivos = $this->inquilinos->findArchivosByInquilinoId($id);

      $res->json([
          'data' => $archivos,
          'meta' => ['requestId' => $req->getRequestId(), 'count' => count($archivos)],
          'errors' => []
      ]);
  }

  public function updateAsesor(Request $req, Response $res, array $params): void {
      $id = (int)($params['id'] ?? 0);
      $body = $req->getJson();
      $asesorId = (int)($body['id_asesor'] ?? 0);

      if ($id <= 0 || $asesorId <= 0) {
          $res->json([
              'data' => null,
              'meta' => ['requestId' => $req->getRequestId()],
              'errors' => [['code' => 'bad_request', 'message' => 'id and id_asesor are required']]
          ], 400);
          return;
      }

      $this->inquilinos->update($id, ['id_asesor' => $asesorId]);
      $updated = $this->inquilinos->findById($id);

      $res->json([
          'data' => $updated,
          'meta' => ['requestId' => $req->getRequestId()],
          'errors' => []
      ]);
  }

  public function updateDireccion(Request $req, Response $res, array $params): void {
      $id = (int)($params['id'] ?? 0);
      $body = $req->getJson();
      $direccion = $body['direccion'] ?? null;

      if ($id <= 0 || !is_array($direccion) || $direccion === []) {
          $res->json([
              'data' => null,
              'meta' => ['requestId' => $req->getRequestId()],
              'errors' => [['code' => 'bad_request', 'message' => 'id and direccion are required']]
          ], 400);
          return;
      }

      $this->inquilinos->updateDireccion($id, $direccion);
      $updated = $this->inquilinos->findById($id);

      $res->json([
          'data' => $updated,
          'meta' => ['requestId' => $req->getRequestId()],
          'errors' => []
      ]);
  }

  public function updateTrabajo(Request $req, Response $res, array $params): void {
      $id = (int)($params['id'] ?? 0);
      $body = $req->getJson();
      $trabajo = $body['trabajo'] ?? null;

      if ($id <= 0 || !is_array($trabajo) || $trabajo === []) {
          $res->json([
              'data' => null,
              'meta' => ['requestId' => $req->getRequestId()],
              'errors' => [['code' => 'bad_request', 'message' => 'id and trabajo are required']]
          ], 400);
          return;
      }

      $this->inquilinos->updateTrabajo($id, $trabajo);
      $updated = $this->inquilinos->findById($id);

      $res->json([
          'data' => $updated,
          'meta' => ['requestId' => $req->getRequestId()],
          'errors' => []
      ]);
  }

  public function updateFiador(Request $req, Response $res, array $params): void {
      $id = (int)($params['id'] ?? 0);
      $body = $req->getJson();
      $fiador = $body['fiador'] ?? null;

      if ($id <= 0 || !is_array($fiador) || $fiador === []) {
          $res->json([
              'data' => null,
              'meta' => ['requestId' => $req->getRequestId()],
              'errors' => [['code' => 'bad_request', 'message' => 'id and fiador are required']]
          ], 400);
          return;
      }

      $this->inquilinos->updateFiador($id, $fiador);
      $updated = $this->inquilinos->findById($id);

      $res->json([
          'data' => $updated,
          'meta' => ['requestId' => $req->getRequestId()],
          'errors' => []
      ]);
  }

  public function updateHistorial(Request $req, Response $res, array $params): void {
      $id = (int)($params['id'] ?? 0);
      $body = $req->getJson();
      $historial = $body['historial_vivienda'] ?? null;

      if ($id <= 0 || !is_array($historial) || $historial === []) {
          $res->json([
              'data' => null,
              'meta' => ['requestId' => $req->getRequestId()],
              'errors' => [['code' => 'bad_request', 'message' => 'id and historial_vivienda are required']]
          ], 400);
          return;
      }

      $this->inquilinos->updateHistorial($id, $historial);
      $updated = $this->inquilinos->findById($id);

      $res->json([
          'data' => $updated,
          'meta' => ['requestId' => $req->getRequestId()],
          'errors' => []
      ]);
  }

  public function updateValidaciones(Request $req, Response $res, array $params): void {
      $id = (int)($params['id'] ?? 0);
      $body = $req->getJson();

      if ($id <= 0) {
          $res->json([
              'data' => null,
              'meta' => ['requestId' => $req->getRequestId()],
              'errors' => [['code' => 'bad_request', 'message' => 'Invalid inquilino id']]
          ], 400);
          return;
      }

      if (empty($body)) {
          $res->json([
              'data' => null,
              'meta' => ['requestId' => $req->getRequestId()],
              'errors' => [['code' => 'bad_request', 'message' => 'No data to update']]
          ], 400);
          return;
      }

      $inquilino = $this->inquilinos->findById($id);

      if (!$inquilino) {
          $res->json([
              'data' => null,
              'meta' => ['requestId' => $req->getRequestId()],
              'errors' => [['code' => 'not_found', 'message' => 'Inquilino not found']]
          ], 404);
          return;
      }

      $this->inquilinos->updateValidaciones($id, $body);
      $updated = $this->inquilinos->findById($id);

      $res->json([
          'data' => $updated,
          'meta' => ['requestId' => $req->getRequestId()],
          'errors' => []
      ]);
  }

  public function addArchivo(Request $req, Response $res, array $params): void {
      $id = (int)($params['id'] ?? 0);
      $body = $req->getJson();
      $tipo = trim((string)($body['tipo'] ?? ''));
      $s3Key = trim((string)($body['s3_key'] ?? ''));

      if ($id <= 0 || $tipo === '' || $s3Key === '') {
          $res->json([
              'data' => null,
              'meta' => ['requestId' => $req->getRequestId()],
              'errors' => [['code' => 'bad_request', 'message' => 'id, tipo, and s3_key are required']]
          ], 400);
          return;
      }

      $item = $this->inquilinos->findById($id);
      if (!$item) {
          $res->json([
              'data' => null,
              'meta' => ['requestId' => $req->getRequestId()],
              'errors' => [['code' => 'not_found', 'message' => 'Inquilino not found']]
          ], 404);
          return;
      }

      $archivo = $this->inquilinos->addArchivo($id, [
          'tipo' => $tipo,
          's3_key' => $s3Key,
          'mime_type' => $body['mime_type'] ?? null,
          'size' => $body['size'] ?? null,
          'original_name' => $body['original_name'] ?? null,
          'token' => $body['token'] ?? null,
          'categoria' => $body['categoria'] ?? null,
      ]);

      $res->json([
          'data' => $archivo,
          'meta' => ['requestId' => $req->getRequestId()],
          'errors' => []
      ], 201);
  }

  public function deleteArchivo(Request $req, Response $res, array $params): void {
      $id = (int)($params['id'] ?? 0);
      $archivoId = (int)($params['archivoId'] ?? 0);

      if ($id <= 0 || $archivoId <= 0) {
          $res->json([
              'data' => null,
              'meta' => ['requestId' => $req->getRequestId()],
              'errors' => [['code' => 'bad_request', 'message' => 'id and archivoId are required']]
          ], 400);
          return;
      }

      $deleted = $this->inquilinos->deleteArchivo($id, $archivoId);
      if (!$deleted) {
          $res->json([
              'data' => null,
              'meta' => ['requestId' => $req->getRequestId()],
              'errors' => [['code' => 'not_found', 'message' => 'Archivo not found']]
          ], 404);
          return;
      }

      $res->json([
          'data' => ['success' => true, 'id' => $archivoId],
          'meta' => ['requestId' => $req->getRequestId()],
          'errors' => []
      ]);
  }

  public function updateArchivo(Request $req, Response $res, array $params): void {
      $id = (int)($params['id'] ?? 0);
      $archivoId = (int)($params['archivoId'] ?? 0);
      $body = $req->getJson();

      if ($id <= 0 || $archivoId <= 0) {
          $res->json([
              'data' => null,
              'meta' => ['requestId' => $req->getRequestId()],
              'errors' => [['code' => 'bad_request', 'message' => 'Invalid inquilino or archivo id']]
          ], 400);
          return;
      }

      $inquilino = $this->inquilinos->findById($id);

      if (!$inquilino) {
          $res->json([
              'data' => null,
              'meta' => ['requestId' => $req->getRequestId()],
              'errors' => [['code' => 'not_found', 'message' => 'Inquilino not found']]
          ], 404);
          return;
      }

      $allowed = [
          'tipo',
          's3_key',
          'mime_type',
          'size',
          'original_name',
          'token',
          'categoria',
      ];

      $payload = [];

      foreach ($allowed as $field) {
          if (array_key_exists($field, $body)) {
              $value = $body[$field];
              $payload[$field] = is_string($value) ? trim($value) : $value;
          }
      }

      if (empty($payload)) {
          $res->json([
              'data' => null,
              'meta' => ['requestId' => $req->getRequestId()],
              'errors' => [['code' => 'bad_request', 'message' => 'No data to update']]
          ], 400);
          return;
      }

      $archivo = $this->inquilinos->updateArchivo($id, $archivoId, $payload);

      if (!$archivo) {
          $res->json([
              'data' => null,
              'meta' => ['requestId' => $req->getRequestId()],
              'errors' => [['code' => 'not_found', 'message' => 'Archivo not found']]
          ], 404);
          return;
      }

      $res->json([
          'data' => $archivo,
          'meta' => ['requestId' => $req->getRequestId()],
          'errors' => []
      ]);
  }
}
