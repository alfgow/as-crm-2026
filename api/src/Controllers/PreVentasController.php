<?php
namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\FinancieroRepository;
use App\Repositories\PreVentaRepository;

final class PreVentasController {
  private PreVentaRepository $preVentas;
  private FinancieroRepository $financieros;

  public function __construct(PreVentaRepository $preVentas, FinancieroRepository $financieros) {
    $this->preVentas = $preVentas;
    $this->financieros = $financieros;
  }

  public function index(Request $req, Response $res): void {
    $items = $this->preVentas->findAll();

    $res->json([
      'data' => $items,
      'meta' => [
        'requestId' => $req->getRequestId(),
        'count' => count($items),
      ],
      'errors' => [],
    ]);
  }

  public function store(Request $req, Response $res): void {
    $payload = $req->getJson() ?? [];

    $required = ['id_poliza', 'numero_poliza', 'canal_venta', 'concepto_venta', 'monto_venta'];
    $validationErrors = [];

    foreach ($required as $field) {
      if (!array_key_exists($field, $payload) || $payload[$field] === '' || $payload[$field] === null) {
        $validationErrors[] = ['field' => $field, 'message' => 'campo requerido'];
      }
    }

    if (array_key_exists('monto_venta', $payload) && (!is_numeric($payload['monto_venta']) || (float)$payload['monto_venta'] < 0)) {
      $validationErrors[] = ['field' => 'monto_venta', 'message' => 'debe ser numérico y >= 0'];
    }

    if (array_key_exists('ganancia_neta', $payload) && !is_numeric($payload['ganancia_neta'])) {
      $validationErrors[] = ['field' => 'ganancia_neta', 'message' => 'debe ser numérico'];
    }

    if (array_key_exists('comision_asesor', $payload) && !is_numeric($payload['comision_asesor'])) {
      $validationErrors[] = ['field' => 'comision_asesor', 'message' => 'debe ser numérico'];
    }

    if ($validationErrors !== []) {
      $res->json([
        'data' => null,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [[
          'code' => 'validation_error',
          'message' => 'payload inválido',
          'details' => $validationErrors,
        ]],
      ], 422);
      return;
    }

    $data = [
      'id_poliza' => (int)$payload['id_poliza'],
      'numero_poliza' => (int)$payload['numero_poliza'],
      'id_asesor' => isset($payload['id_asesor']) && is_numeric($payload['id_asesor']) ? (int)$payload['id_asesor'] : null,
      'id_usuario_creador' => isset($payload['id_usuario_creador']) && is_numeric($payload['id_usuario_creador']) ? (int)$payload['id_usuario_creador'] : null,
      'canal_venta' => (string)$payload['canal_venta'],
      'concepto_venta' => (string)$payload['concepto_venta'],
      'monto_venta' => number_format((float)$payload['monto_venta'], 2, '.', ''),
      'comision_asesor' => number_format((float)($payload['comision_asesor'] ?? 0), 2, '.', ''),
      'ganancia_neta' => number_format((float)($payload['ganancia_neta'] ?? $payload['monto_venta']), 2, '.', ''),
      'estado_preventa' => 1,
      'observaciones' => isset($payload['observaciones']) ? (string)$payload['observaciones'] : null,
    ];

    try {
      $id = $this->preVentas->create($data);
      $created = $this->preVentas->findById($id);

      $res->json([
        'data' => $created,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [],
      ], 201);
    } catch (\Throwable $e) {
      $res->json([
        'data' => null,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [[
          'code' => 'db_error',
          'message' => $e->getMessage(),
        ]],
      ], 500);
    }
  }

  public function cerrar(Request $req, Response $res, array $params): void {
    $idPreventa = (int)($params['id_preventa'] ?? 0);
    $preventa = $this->preVentas->findById($idPreventa);

    if (!$preventa) {
      $res->json([
        'data' => null,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [['code' => 'not_found', 'message' => 'Preventa no encontrada']],
      ], 404);
      return;
    }

    $estadoActual = (int)$preventa['estado_preventa'];
    if ($estadoActual === 2) {
      $res->json([
        'data' => $preventa,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [],
      ]);
      return;
    }

    if ($estadoActual !== 1) {
      $res->json([
        'data' => null,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [[
          'code' => 'conflict',
          'message' => 'Solo se pueden cerrar preventas pendientes',
        ]],
      ], 409);
      return;
    }

    try {
      $this->preVentas->actualizarEstado($idPreventa, 2);

      $this->financieros->crearVenta([
        'fecha_venta' => date('Y-m-d'),
        'canal_venta' => (string)$preventa['canal_venta'],
        'concepto_venta' => (string)$preventa['concepto_venta'],
        'monto_venta' => (float)$preventa['monto_venta'],
        'comision_asesor' => (float)$preventa['comision_asesor'],
        'ganancia_neta' => (float)$preventa['ganancia_neta'],
        'id_usuario' => (int)($preventa['id_usuario_creador'] ?? 0),
      ]);

      $updated = $this->preVentas->findById($idPreventa);

      $res->json([
        'data' => $updated,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [],
      ]);
    } catch (\Throwable $e) {
      $res->json([
        'data' => null,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [[
          'code' => 'db_error',
          'message' => $e->getMessage(),
        ]],
      ], 500);
    }
  }

  public function cancelar(Request $req, Response $res, array $params): void {
    $idPreventa = (int)($params['id_preventa'] ?? 0);
    $preventa = $this->preVentas->findById($idPreventa);

    if (!$preventa) {
      $res->json([
        'data' => null,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [['code' => 'not_found', 'message' => 'Preventa no encontrada']],
      ], 404);
      return;
    }

    $estadoActual = (int)$preventa['estado_preventa'];
    if ($estadoActual === 3) {
      $res->json([
        'data' => $preventa,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [],
      ]);
      return;
    }

    if ($estadoActual !== 1) {
      $res->json([
        'data' => null,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [[
          'code' => 'conflict',
          'message' => 'Solo se pueden cancelar preventas pendientes',
        ]],
      ], 409);
      return;
    }

    try {
      $this->preVentas->actualizarEstado($idPreventa, 3);
      $updated = $this->preVentas->findById($idPreventa);

      $res->json([
        'data' => $updated,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [],
      ]);
    } catch (\Throwable $e) {
      $res->json([
        'data' => null,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [[
          'code' => 'db_error',
          'message' => $e->getMessage(),
        ]],
      ], 500);
    }
  }
}
