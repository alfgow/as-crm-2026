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

  public function showLegacy(Request $req, Response $res, array $params): void {
      $pk = (string)($params['pk'] ?? '');
      $sk = isset($params['sk']) ? (string)$params['sk'] : null;

      if ($pk === '') {
          $res->json([
              'data' => null,
              'meta' => ['requestId' => $req->getRequestId()],
              'errors' => [['code' => 'bad_request', 'message' => 'pk is required']]
          ], 400);
          return;
      }

      $item = $this->inmuebles->findByLegacyKeys($pk, $sk);
      if (!$item) {
          $res->json([
              'data' => null,
              'meta' => ['requestId' => $req->getRequestId()],
              'errors' => [['code' => 'not_found', 'message' => 'Inmueble not found']]
          ], 404);
          return;
      }

      $item = $this->inmuebles->withLegacyKeys($item);

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

  public function infoLegacy(Request $req, Response $res, array $params): void {
      $pk = (string)($params['pk'] ?? '');
      $sk = isset($params['sk']) ? (string)$params['sk'] : null;

      if ($pk === '') {
          $res->json([
              'data' => null,
              'meta' => ['requestId' => $req->getRequestId()],
              'errors' => [['code' => 'bad_request', 'message' => 'pk is required']]
          ], 400);
          return;
      }

      $item = $this->inmuebles->findByLegacyKeys($pk, $sk);
      if (!$item) {
          $res->json([
              'data' => null,
              'meta' => ['requestId' => $req->getRequestId()],
              'errors' => [['code' => 'not_found', 'message' => 'Inmueble not found']]
          ], 404);
          return;
      }

      $item = $this->inmuebles->withLegacyKeys($item);

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
          return;
      }

      $inmueble = $this->inmuebles->findById($id);

      if (!$inmueble) {
          $res->json([
              'data' => null,
              'meta' => ['requestId' => $req->getRequestId()],
              'errors' => [['code' => 'not_found', 'message' => 'Inmueble not found']]
          ], 404);
          return;
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

      $deleted = $this->inmuebles->deleteBulk($ids);

      $res->json([
          'data' => ['success' => true, 'deleted' => $deleted, 'ids' => $ids],
          'meta' => ['requestId' => $req->getRequestId()],
          'errors' => []
      ]);
  }

  public function guardarAjax(Request $req, Response $res, array $ctx): void {
      $body = $req->getJson() ?? [];

      $direccion = $this->buildDireccion($body);
      $idArrendador = $this->parsePrefixedId($body['id_arrendador'] ?? $body['pk'] ?? null, 'arr#');
      $idAsesor = $this->parsePrefixedId($body['id_asesor'] ?? $body['asesor_pk'] ?? null, 'ase#');

      if ($idArrendador <= 0 || $idAsesor <= 0 || $direccion === '') {
          $res->json([
              'data' => null,
              'meta' => ['requestId' => $req->getRequestId()],
              'errors' => [['code' => 'bad_request', 'message' => 'id_arrendador, id_asesor, and direccion_inmueble are required']]
          ], 400);
          return;
      }

      $payload = [
          'id_arrendador' => $idArrendador,
          'id_asesor' => $idAsesor,
          'direccion_inmueble' => $direccion,
          'tipo' => $body['tipo'] ?? '',
          'renta' => $body['renta'] ?? '',
          'mantenimiento' => $body['mantenimiento'] ?? '',
          'monto_mantenimiento' => $body['monto_mantenimiento'] ?? '',
          'deposito' => $body['deposito'] ?? '',
          'estacionamiento' => $body['estacionamiento'] ?? 0,
          'mascotas' => $body['mascotas'] ?? '',
          'comentarios' => $body['comentarios'] ?? '',
      ];

      if (empty($payload['tipo']) || empty($payload['renta'])) {
          $res->json([
              'data' => null,
              'meta' => ['requestId' => $req->getRequestId()],
              'errors' => [['code' => 'validation_error', 'message' => 'tipo and renta are required']]
          ], 400);
          return;
      }

      try {
          $id = $this->inmuebles->create($payload);
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

  private function parsePrefixedId($value, string $prefix): int {
      if ($value === null) {
          return 0;
      }

      if (is_numeric($value)) {
          return (int)$value;
      }

      $text = trim((string)$value);
      if ($text === '') {
          return 0;
      }

      if (str_starts_with($text, $prefix)) {
          $text = substr($text, strlen($prefix));
      }

      return ctype_digit($text) ? (int)$text : 0;
  }

  private function buildDireccion(array $body): string {
      $parts = [
          trim((string)($body['calle'] ?? '')),
          trim((string)($body['num_exterior'] ?? $body['numExt'] ?? '')),
      ];

      $numInt = trim((string)($body['num_interior'] ?? $body['numInt'] ?? ''));
      if ($numInt !== '') {
          $parts[1] .= ' int. ' . $numInt;
      }

      $colonia = trim((string)($body['colonia'] ?? ''));
      $alcaldia = trim((string)($body['alcaldia'] ?? ''));
      $ciudad = trim((string)($body['ciudad'] ?? ''));
      $cp = trim((string)($body['codigo_postal'] ?? $body['cp'] ?? ''));

      $direccion = trim(implode(' ', array_filter($parts)));
      if ($colonia !== '') {
          $direccion .= ($direccion !== '' ? ', ' : '') . 'col. ' . $colonia;
      }
      if ($alcaldia !== '') {
          $direccion .= ($direccion !== '' ? ', ' : '') . $alcaldia;
      }
      if ($ciudad !== '') {
          $direccion .= ($direccion !== '' ? ', ' : '') . $ciudad;
      }
      if ($cp !== '') {
          $direccion .= ($direccion !== '' ? ', ' : '') . 'cp ' . $cp;
      }

      return trim($direccion);
  }
}
