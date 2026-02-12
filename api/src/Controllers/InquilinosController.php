<?php
namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\InquilinoRepository;
use App\Services\MediaUploadService;

final class InquilinosController {
  private array $config;
  private InquilinoRepository $inquilinos;
  private MediaUploadService $uploads;

  public function __construct(array $config, InquilinoRepository $inquilinos, MediaUploadService $uploads) {
      $this->config = $config;
      $this->inquilinos = $inquilinos;
      $this->uploads = $uploads;
  }

  /**
   * Lista todos los inquilinos con filtros opcionales
   * 
   * Query params:
   * - search: Búsqueda por nombre, email o celular
   * - status: Filtro por status (1=Nuevo, 2=En Proceso, 3=Aprobado, 4=Rechazado)
   * 
   * Ejemplos:
   * - GET /api/v1/inquilinos
   * - GET /api/v1/inquilinos?search=juan
   * - GET /api/v1/inquilinos?status=1
   * - GET /api/v1/inquilinos?status=1&search=juan
   */
  public function index(Request $req, Response $res, array $ctx): void {
    $search = $req->getQuery()['search'] ?? null;
    $status = $req->getQuery()['status'] ?? null;
    
    // Validar que el status sea válido si se proporciona
    if ($status !== null && !in_array($status, ['1', '2', '3', '4'], true)) {
      $res->json([
        'data' => null,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [['code' => 'bad_request', 'message' => 'Invalid status. Allowed values: 1 (Nuevo), 2 (En Proceso), 3 (Aprobado), 4 (Rechazado)']]
      ], 400);
      return;
    }
    
    $all = $this->inquilinos->findAll($search, $status);
    $res->json([
      'data' => $all,
      'meta' => [
        'requestId' => $req->getRequestId(), 
        'count' => count($all),
        'filters' => [
          'search' => $search,
          'status' => $status
        ]
      ],
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

      if ($id <= 0) {
          $res->json(['data' => null, 'meta' => ['requestId' => $req->getRequestId()], 'errors' => [['code' => 'bad_request', 'message' => 'Invalid inquilino id']]], 400);
          return;
      }

      if (empty($body)) {
          $res->json(['data' => null, 'meta' => ['requestId' => $req->getRequestId()], 'errors' => [['code' => 'bad_request', 'message' => 'No data to update']]], 400);
          return;
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
      if (!$updated) {
          $res->json(['data' => null, 'meta' => ['requestId' => $req->getRequestId()], 'errors' => [['code' => 'not_found', 'message' => 'Inquilino not found']]], 404);
          return;
      }
      $res->json(['data' => $updated, 'meta' => ['requestId' => $req->getRequestId()], 'errors' => []]);
  }

  public function updateDatosPersonales(Request $req, Response $res, array $params): void {
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

      $allowed = [
          'tipo',
          'nombre_inquilino',
          'apellidop_inquilino',
          'apellidom_inquilino',
          'representante',
          'estadocivil',
          'rfc',
          'curp',
          'email',
          'celular',
          'nacionalidad',
          'tipo_id',
          'num_id',
          'conyuge',
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

      $inquilino = $this->inquilinos->findById($id);

      if (!$inquilino) {
          $res->json([
              'data' => null,
              'meta' => ['requestId' => $req->getRequestId()],
              'errors' => [['code' => 'not_found', 'message' => 'Inquilino not found']]
          ], 404);
          return;
      }

      $this->inquilinos->update($id, $payload);
      $updated = $this->inquilinos->findById($id);

      $res->json([
          'data' => $updated,
          'meta' => ['requestId' => $req->getRequestId()],
          'errors' => []
      ]);
  }

  public function destroy(Request $req, Response $res, array $params): void {
      $id = (int)($params['id'] ?? 0);

      if ($id <= 0) {
          $res->json([
              'data' => null,
              'meta' => ['requestId' => $req->getRequestId()],
              'errors' => [['code' => 'bad_request', 'message' => 'Invalid inquilino id']]
          ], 400);
          return;
      }

      try {
          $result = $this->inquilinos->deleteComplete($id);

          if (!$result['deleted']) {
              $res->json([
                  'data' => null,
                  'meta' => ['requestId' => $req->getRequestId()],
                  'errors' => [['code' => 'not_found', 'message' => 'Inquilino not found']]
              ], 404);
              return;
          }

          $s3Deleted = [];
          $s3Errors = [];
          foreach ($result['s3_keys'] as $key) {
              $deleteResult = $this->uploads->deleteObject('inquilinos', (string)$key);
              if ($deleteResult['ok']) {
                  $s3Deleted[] = $key;
                  continue;
              }

              $s3Errors[] = [
                  'key' => $key,
                  'status' => (int)($deleteResult['status'] ?? 0),
                  'error' => (string)($deleteResult['error'] ?? 'delete_failed'),
              ];
          }

          $res->json([
              'data' => [
                  'success' => true,
                  'id' => $id,
                  's3_deleted_count' => count($s3Deleted),
                  's3_error_count' => count($s3Errors),
                  's3_errors' => $s3Errors,
              ],
              'meta' => ['requestId' => $req->getRequestId()],
              'errors' => []
          ]);
      } catch (\Throwable $e) {
          $res->json([
              'data' => null,
              'meta' => ['requestId' => $req->getRequestId()],
              'errors' => [['code' => 'server_error', 'message' => 'Unable to delete inquilino completely']]
          ], 500);
      }
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

      $deleted = $this->inquilinos->deleteBulk($ids);

      $res->json([
          'data' => ['success' => true, 'deleted' => $deleted, 'ids' => $ids],
          'meta' => ['requestId' => $req->getRequestId()],
          'errors' => []
      ]);
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
      ]);

      $res->json([
          'data' => $archivo,
          'meta' => ['requestId' => $req->getRequestId()],
          'errors' => []
      ], 201);
  }

  public function uploadArchivo(Request $req, Response $res, array $params): void {
      $id = (int)($params['id'] ?? 0);
      $tipo = trim((string)($_POST['tipo'] ?? ''));
      $file = $_FILES['file'] ?? null;

      if ($id <= 0) {
          $res->json([
              'data' => null,
              'meta' => ['requestId' => $req->getRequestId()],
              'errors' => [['code' => 'bad_request', 'message' => 'Invalid inquilino id']]
          ], 400);
          return;
      }

      if ($tipo === '') {
          $res->json([
              'data' => null,
              'meta' => ['requestId' => $req->getRequestId()],
              'errors' => [['code' => 'bad_request', 'message' => 'tipo is required']]
          ], 400);
          return;
      }

      if (!$file || !isset($file['tmp_name'])) {
          $res->json([
              'data' => null,
              'meta' => ['requestId' => $req->getRequestId()],
              'errors' => [['code' => 'bad_request', 'message' => 'file is required']]
          ], 400);
          return;
      }

      if (!empty($file['error'])) {
          $res->json([
              'data' => null,
              'meta' => ['requestId' => $req->getRequestId()],
              'errors' => [['code' => 'bad_request', 'message' => 'File upload error']]
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

      $originalName = (string)($file['name'] ?? 'archivo');
      $extension = pathinfo($originalName, PATHINFO_EXTENSION);
      $suffix = $extension !== '' ? '.' . strtolower($extension) : '';
      $key = sprintf('inquilinos/%d/%s%s', $id, bin2hex(random_bytes(16)), $suffix);
      $mimeType = (string)($file['type'] ?? 'application/octet-stream');
      $size = (int)($file['size'] ?? 0);

      $upload = $this->uploads->uploadFromPath('inquilinos', $key, (string)$file['tmp_name'], $mimeType);
      if (!$upload['ok']) {
          $res->json([
              'data' => null,
              'meta' => ['requestId' => $req->getRequestId()],
              'errors' => [['code' => 'upload_failed', 'message' => 'Unable to upload file']]
          ], 500);
          return;
      }

      $archivo = $this->inquilinos->addArchivo($id, [
          'tipo' => $tipo,
          's3_key' => $key,
          'mime_type' => $mimeType,
          'size' => $size > 0 ? $size : null,
      ]);

      if (!$archivo) {
          $res->json([
              'data' => null,
              'meta' => ['requestId' => $req->getRequestId()],
              'errors' => [['code' => 'db_error', 'message' => 'Unable to save archivo']]
          ], 500);
          return;
      }

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

  public function updateArchivoUpload(Request $req, Response $res, array $params): void {
      $id = (int)($params['id'] ?? 0);
      $archivoId = (int)($params['archivoId'] ?? 0);
      $tipo = trim((string)($_POST['tipo'] ?? ''));
      $file = $_FILES['file'] ?? null;

      if ($id <= 0 || $archivoId <= 0) {
          $res->json([
              'data' => null,
              'meta' => ['requestId' => $req->getRequestId()],
              'errors' => [['code' => 'bad_request', 'message' => 'Invalid inquilino or archivo id']]
          ], 400);
          return;
      }

      if ($tipo === '') {
          $res->json([
              'data' => null,
              'meta' => ['requestId' => $req->getRequestId()],
              'errors' => [['code' => 'bad_request', 'message' => 'tipo is required']]
          ], 400);
          return;
      }

      if (!$file || !isset($file['tmp_name'])) {
          $res->json([
              'data' => null,
              'meta' => ['requestId' => $req->getRequestId()],
              'errors' => [['code' => 'bad_request', 'message' => 'file is required']]
          ], 400);
          return;
      }

      if (!empty($file['error'])) {
          $res->json([
              'data' => null,
              'meta' => ['requestId' => $req->getRequestId()],
              'errors' => [['code' => 'bad_request', 'message' => 'File upload error']]
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

      $archivoActual = $this->inquilinos->findArchivoById($id, $archivoId);
      if (!$archivoActual) {
          $res->json([
              'data' => null,
              'meta' => ['requestId' => $req->getRequestId()],
              'errors' => [['code' => 'not_found', 'message' => 'Archivo not found']]
          ], 404);
          return;
      }

      $originalName = (string)($file['name'] ?? 'archivo');
      $extension = pathinfo($originalName, PATHINFO_EXTENSION);
      $suffix = $extension !== '' ? '.' . strtolower($extension) : '';
      $key = sprintf('inquilinos/%d/%s%s', $id, bin2hex(random_bytes(16)), $suffix);
      $mimeType = (string)($file['type'] ?? 'application/octet-stream');
      $size = (int)($file['size'] ?? 0);

      $upload = $this->uploads->uploadFromPath('inquilinos', $key, (string)$file['tmp_name'], $mimeType);
      if (!$upload['ok']) {
          $res->json([
              'data' => null,
              'meta' => ['requestId' => $req->getRequestId()],
              'errors' => [['code' => 'upload_failed', 'message' => 'Unable to upload file']]
          ], 500);
          return;
      }

      $archivo = $this->inquilinos->updateArchivo($id, $archivoId, [
          'tipo' => $tipo,
          's3_key' => $key,
          'mime_type' => $mimeType,
          'size' => $size > 0 ? $size : null,
          'token' => $_POST['token'] ?? null,
          'categoria' => $_POST['categoria'] ?? null,
      ]);

      if (!$archivo) {
          $res->json([
              'data' => null,
              'meta' => ['requestId' => $req->getRequestId()],
              'errors' => [['code' => 'db_error', 'message' => 'Unable to update archivo']]
          ], 500);
          return;
      }

      $res->json([
          'data' => $archivo,
          'meta' => ['requestId' => $req->getRequestId()],
          'errors' => []
      ]);
  }
}
