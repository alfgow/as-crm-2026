<?php
namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\ArrendadorRepository;

final class ArrendadoresController {
  private array $config;
  private ArrendadorRepository $arrendadores;

  public function __construct(array $config, ArrendadorRepository $arrendadores) {
    $this->config = $config;
    $this->arrendadores = $arrendadores;
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

      $this->arrendadores->update($id, ['id_asesor' => $asesorId]);
      $updated = $this->arrendadores->findById($id);

      $res->json([
          'data' => $updated,
          'meta' => ['requestId' => $req->getRequestId()],
          'errors' => []
      ]);
  }

  public function updateDatosPersonales(Request $req, Response $res, array $params): void {
      $id = (int)($params['id'] ?? 0);
      $body = $req->getJson();

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

      $allowed = [
          'nombre_arrendador',
          'email',
          'celular',
          'direccion_arrendador',
          'estadocivil',
          'nacionalidad',
          'rfc',
          'tipo_id',
          'num_id'
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

      $this->arrendadores->update($id, $payload);
      $updated = $this->arrendadores->findById($id);

      $res->json([
          'data' => $updated,
          'meta' => ['requestId' => $req->getRequestId()],
          'errors' => []
      ]);
  }

  public function updateInfoBancaria(Request $req, Response $res, array $params): void {
      $id = (int)($params['id'] ?? 0);
      $body = $req->getJson();

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

      $allowed = ['banco', 'cuenta', 'clabe'];
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

      $this->arrendadores->update($id, $payload);
      $updated = $this->arrendadores->findById($id);

      $res->json([
          'data' => $updated,
          'meta' => ['requestId' => $req->getRequestId()],
          'errors' => []
      ]);
  }

  public function updateComentarios(Request $req, Response $res, array $params): void {
      $id = (int)($params['id'] ?? 0);
      $body = $req->getJson();
      $comentarios = $body['comentarios'] ?? null;

      if ($id <= 0) {
          $res->json([
              'data' => null,
              'meta' => ['requestId' => $req->getRequestId()],
              'errors' => [['code' => 'bad_request', 'message' => 'Invalid arrendador id']]
          ], 400);
          return;
      }

      if ($comentarios === null) {
          $res->json([
              'data' => null,
              'meta' => ['requestId' => $req->getRequestId()],
              'errors' => [['code' => 'bad_request', 'message' => 'comentarios is required']]
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

      $payload = [
          'comentarios' => is_string($comentarios) ? trim($comentarios) : $comentarios
      ];

      $this->arrendadores->update($id, $payload);
      $updated = $this->arrendadores->findById($id);

      $res->json([
          'data' => $updated,
          'meta' => ['requestId' => $req->getRequestId()],
          'errors' => []
      ]);
  }
}
