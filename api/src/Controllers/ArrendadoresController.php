<?php
namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\ArrendadorRepository;
use App\Services\MediaUploadService;

final class ArrendadoresController {
  private array $config;
  private ArrendadorRepository $arrendadores;
  private MediaUploadService $uploads;

  public function __construct(array $config, ArrendadorRepository $arrendadores, MediaUploadService $uploads) {
    $this->config = $config;
    $this->arrendadores = $arrendadores;
    $this->uploads = $uploads;
  }

  public function index(Request $req, Response $res, array $ctx): void {
    $all = $this->arrendadores->findAll();

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
    
    // Validar campos requeridos mínimos
    if (empty($body['nombre_arrendador']) || empty($body['email']) || empty($body['celular'])) {
        $res->json([
            'data' => null,
            'meta' => ['requestId' => $req->getRequestId()],
            'errors' => [['code' => 'validation_error', 'message' => 'Missing required fields: nombre_arrendador, email, celular']]
        ], 400);
    }

    try {
        $id = $this->arrendadores->create($body);
        $item = $this->arrendadores->findById($id);

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
      $item = $this->arrendadores->findById($id);

      if (!$item) {
          $res->json([
              'data' => null,
              'meta' => ['requestId' => $req->getRequestId()],
              'errors' => [['code' => 'not_found', 'message' => 'Arrendador not found']]
          ], 404);
      }

      $res->json([
          'data' => $item,
          'meta' => ['requestId' => $req->getRequestId()],
          'errors' => []
      ]);
  }

  public function showBySlug(Request $req, Response $res, array $params): void {
      $slug = trim((string)($params['slug'] ?? ''));

      if ($slug === '') {
          $res->json([
              'data' => null,
              'meta' => ['requestId' => $req->getRequestId()],
              'errors' => [['code' => 'bad_request', 'message' => 'slug is required']]
          ], 400);
          return;
      }

      $item = $this->arrendadores->findBySlug($slug);

      if (!$item) {
          $res->json([
              'data' => null,
              'meta' => ['requestId' => $req->getRequestId()],
              'errors' => [['code' => 'not_found', 'message' => 'Arrendador not found']]
          ], 404);
          return;
      }

      $res->json([
          'data' => $item,
          'meta' => ['requestId' => $req->getRequestId()],
          'errors' => []
      ]);
  }

  public function showDetalle(Request $req, Response $res, array $params): void {
      $id = (int)($params['id'] ?? 0);

      if ($id <= 0) {
          $res->json([
              'data' => null,
              'meta' => ['requestId' => $req->getRequestId()],
              'errors' => [['code' => 'bad_request', 'message' => 'Invalid arrendador id']]
          ], 400);
          return;
      }

      $item = $this->arrendadores->findById($id);

      if (!$item) {
          $res->json([
              'data' => null,
              'meta' => ['requestId' => $req->getRequestId()],
              'errors' => [['code' => 'not_found', 'message' => 'Arrendador not found']]
          ], 404);
          return;
      }

      $archivos = $this->arrendadores->findArchivosByArrendadorId($id);

      $res->json([
          'data' => [
              'arrendador' => $item,
              'archivos' => $archivos,
          ],
          'meta' => ['requestId' => $req->getRequestId()],
          'errors' => []
      ]);
  }

  public function archivos(Request $req, Response $res, array $params): void {
      $id = (int)($params['id'] ?? 0);

      if ($id <= 0) {
          $res->json([
              'data' => null,
              'meta' => ['requestId' => $req->getRequestId()],
              'errors' => [['code' => 'bad_request', 'message' => 'Invalid arrendador id']]
          ], 400);
          return;
      }

      $arrendador = $this->arrendadores->findById($id);
      if (!$arrendador) {
          $res->json([
              'data' => null,
              'meta' => ['requestId' => $req->getRequestId()],
              'errors' => [['code' => 'not_found', 'message' => 'Arrendador not found']]
          ], 404);
          return;
      }

      $archivos = $this->arrendadores->findArchivosByArrendadorId($id);

      $res->json([
          'data' => $archivos,
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

      $arrendador = $this->arrendadores->findById($id);
      if (!$arrendador) {
          $res->json([
              'data' => null,
              'meta' => ['requestId' => $req->getRequestId()],
              'errors' => [['code' => 'not_found', 'message' => 'Arrendador not found']]
          ], 404);
          return;
      }

      $archivo = $this->arrendadores->addArchivo($id, [
          'tipo' => $tipo,
          's3_key' => $s3Key,
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
              'errors' => [['code' => 'bad_request', 'message' => 'Invalid arrendador id']]
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

      $arrendador = $this->arrendadores->findById($id);
      if (!$arrendador) {
          $res->json([
              'data' => null,
              'meta' => ['requestId' => $req->getRequestId()],
              'errors' => [['code' => 'not_found', 'message' => 'Arrendador not found']]
          ], 404);
          return;
      }

      $originalName = (string)($file['name'] ?? 'archivo');
      $extension = pathinfo($originalName, PATHINFO_EXTENSION);
      $suffix = $extension !== '' ? '.' . strtolower($extension) : '';
      $key = sprintf('arrendadores/%d/%s%s', $id, bin2hex(random_bytes(16)), $suffix);
      $mimeType = (string)($file['type'] ?? 'application/octet-stream');

      $upload = $this->uploads->uploadFromPath('arrendadores', $key, (string)$file['tmp_name'], $mimeType);
      if (!$upload['ok']) {
          $res->json([
              'data' => null,
              'meta' => ['requestId' => $req->getRequestId()],
              'errors' => [['code' => 'upload_failed', 'message' => 'Unable to upload file']]
          ], 500);
          return;
      }

      $archivo = $this->arrendadores->addArchivo($id, [
          'tipo' => $tipo,
          's3_key' => $key,
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

      $deleted = $this->arrendadores->deleteArchivo($id, $archivoId);
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
              'errors' => [['code' => 'bad_request', 'message' => 'Invalid arrendador or archivo id']]
          ], 400);
          return;
      }

      $arrendador = $this->arrendadores->findById($id);
      if (!$arrendador) {
          $res->json([
              'data' => null,
              'meta' => ['requestId' => $req->getRequestId()],
              'errors' => [['code' => 'not_found', 'message' => 'Arrendador not found']]
          ], 404);
          return;
      }

      $allowed = ['tipo', 's3_key'];
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

      $archivo = $this->arrendadores->updateArchivo($id, $archivoId, $payload);
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
              'errors' => [['code' => 'bad_request', 'message' => 'Invalid arrendador or archivo id']]
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

      $arrendador = $this->arrendadores->findById($id);
      if (!$arrendador) {
          $res->json([
              'data' => null,
              'meta' => ['requestId' => $req->getRequestId()],
              'errors' => [['code' => 'not_found', 'message' => 'Arrendador not found']]
          ], 404);
          return;
      }

      $archivoActual = $this->arrendadores->findArchivoById($id, $archivoId);
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
      $key = sprintf('arrendadores/%d/%s%s', $id, bin2hex(random_bytes(16)), $suffix);
      $mimeType = (string)($file['type'] ?? 'application/octet-stream');

      $upload = $this->uploads->uploadFromPath('arrendadores', $key, (string)$file['tmp_name'], $mimeType);
      if (!$upload['ok']) {
          $res->json([
              'data' => null,
              'meta' => ['requestId' => $req->getRequestId()],
              'errors' => [['code' => 'upload_failed', 'message' => 'Unable to upload file']]
          ], 500);
          return;
      }

      $archivo = $this->arrendadores->updateArchivo($id, $archivoId, [
          'tipo' => $tipo,
          's3_key' => $key,
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

      $this->arrendadores->update($id, $body);
      $updated = $this->arrendadores->findById($id);

      $res->json([
          'data' => $updated,
          'meta' => ['requestId' => $req->getRequestId()],
          'errors' => []
      ]);
  }

  public function destroy(Request $req, Response $res, array $params): void {
      $id = (int)($params['id'] ?? 0);
      $this->arrendadores->delete($id);

      $res->json([
          'data' => ['success' => true, 'id' => $id],
          'meta' => ['requestId' => $req->getRequestId()],
          'errors' => []
      ]);
  }

  public function byAsesor(Request $req, Response $res, array $params): void {
      $asesorId = (int)($params['id'] ?? 0);

      if ($asesorId <= 0) {
          $res->json([
              'data' => [],
              'meta' => ['requestId' => $req->getRequestId()],
              'errors' => [['code' => 'bad_request', 'message' => 'Invalid asesor id']]
          ], 400);
          return;
      }

      $items = $this->arrendadores->findByAsesorId($asesorId);

      $res->json([
          'data' => $items,
          'meta' => ['requestId' => $req->getRequestId(), 'count' => count($items)],
          'errors' => []
      ]);
  }
}
