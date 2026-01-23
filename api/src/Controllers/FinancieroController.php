<?php
namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\FinancieroRepository;

final class FinancieroController {
  private FinancieroRepository $financieros;

  public function __construct(FinancieroRepository $financieros) {
    $this->financieros = $financieros;
  }

  public function index(Request $req, Response $res): void {
    $query = $req->getQuery();
    $mesSeleccionado = $query['mes'] ?? date('Y-m');
    if (!preg_match('/^\\d{4}-\\d{2}$/', (string)$mesSeleccionado)) {
      $mesSeleccionado = date('Y-m');
    }

    $anioConsulta = (int)date('Y', strtotime($mesSeleccionado));
    $mesNumerico = (int)date('m', strtotime($mesSeleccionado));

    $ingresosPorMesRaw = $this->financieros->obtenerIngresosPorMes((string)$anioConsulta);
    $resumen = $this->financieros->obtenerResumenPorAnioMes($anioConsulta, $mesNumerico);
    $ingresosAcumulados = $this->financieros->obtenerIngresosAnuales((string)$anioConsulta);
    $sumatorias = $this->financieros->obtenerSumatoriasPorCanalAnioMes($anioConsulta, $mesNumerico);

    $ingresosMes = (float)($resumen['total_mes'] ?? 0);
    $polizasMes = (int)($resumen['polizas_mes'] ?? 0);
    $totalArrendamiento = (float)($sumatorias['total_arrendamiento'] ?? 0);
    $totalInmobiliaria = (float)($sumatorias['total_inmobiliaria'] ?? 0);

    $ingresosPorMes = array_map(static function (array $row): array {
      return [
        'mes' => (int)($row['mes'] ?? 0),
        'total' => (float)($row['total'] ?? 0),
        'neto' => (float)($row['neto'] ?? 0),
      ];
    }, $ingresosPorMesRaw);

    $ventasPeriodo = $this->financieros->listarVentasPorAnioMes($anioConsulta, $mesNumerico);
    $totalBrutoPeriodo = 0.0;
    $totalNetoPeriodo = 0.0;
    foreach ($ventasPeriodo as $venta) {
      $totalBrutoPeriodo += (float)($venta['monto_venta'] ?? 0);
      $totalNetoPeriodo += (float)($venta['ganancia_neta'] ?? 0);
    }

    $res->json([
      'data' => [
        'mes' => $mesSeleccionado,
        'resumen' => [
          'ingresos_mes' => $ingresosMes,
          'polizas_mes' => $polizasMes,
          'ingresos_anuales' => $ingresosAcumulados,
          'total_arrendamiento' => $totalArrendamiento,
          'total_inmobiliaria' => $totalInmobiliaria,
        ],
        'ingresos_por_mes' => $ingresosPorMes,
        'ventas_periodo' => $ventasPeriodo,
        'totales_periodo' => [
          'total_bruto' => $totalBrutoPeriodo,
          'total_neto' => $totalNetoPeriodo,
        ],
      ],
      'meta' => [
        'requestId' => $req->getRequestId(),
      ],
      'errors' => [],
    ]);
  }

  public function registroVenta(Request $req, Response $res): void {
    $res->json([
      'data' => [
        'mensaje' => 'Formulario de registro de venta disponible en frontend.',
      ],
      'meta' => [
        'requestId' => $req->getRequestId(),
      ],
      'errors' => [],
    ]);
  }

  private function validarFecha(string $fecha): bool {
    if (!preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $fecha)) {
      return false;
    }
    $date = \DateTimeImmutable::createFromFormat('Y-m-d', $fecha);
    return $date !== false;
  }

  private function obtenerRangoFechas(Request $req, Response $res): ?array {
    $query = $req->getQuery();
    $inicio = (string)($query['inicio'] ?? '');
    $fin = (string)($query['fin'] ?? '');

    if (!$this->validarFecha($inicio) || !$this->validarFecha($fin)) {
      $res->json([
        'data' => null,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [[
          'code' => 'bad_request',
          'message' => 'inicio y fin deben estar en formato YYYY-MM-DD',
        ]],
      ], 400);
      return null;
    }

    $inicioDate = \DateTimeImmutable::createFromFormat('Y-m-d', $inicio);
    $finDate = \DateTimeImmutable::createFromFormat('Y-m-d', $fin);
    if (!$inicioDate || !$finDate || $inicioDate > $finDate) {
      $res->json([
        'data' => null,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [[
          'code' => 'bad_request',
          'message' => 'rango de fechas inválido',
        ]],
      ], 400);
      return null;
    }

    return [$inicio, $fin];
  }

  private function obtenerPeriodo(Request $req, Response $res): ?array {
    $query = $req->getQuery();
    $anio = (int)($query['anio'] ?? date('Y'));
    $mes = (int)($query['mes'] ?? date('n'));

    if ($anio <= 0 || $mes < 1 || $mes > 12) {
      $res->json([
        'data' => null,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [['code' => 'bad_request', 'message' => 'anio y mes inválidos']],
      ], 400);
      return null;
    }

    return [$anio, $mes];
  }

  private function validarVentaPayload(array $payload, bool $requireAll): array {
    $errors = [];
    $data = [];
    $fields = [
      'fecha_venta',
      'canal_venta',
      'concepto_venta',
      'monto_venta',
      'comision_asesor',
      'ganancia_neta',
    ];

    foreach ($fields as $field) {
      if (!array_key_exists($field, $payload)) {
        if ($requireAll) {
          $errors[] = ['field' => $field, 'message' => 'campo requerido'];
        }
        continue;
      }

      $value = $payload[$field];
      if ($field === 'fecha_venta') {
        if (!is_string($value) || !$this->validarFecha($value)) {
          $errors[] = ['field' => $field, 'message' => 'fecha inválida (YYYY-MM-DD)'];
          continue;
        }
        $data[$field] = $value;
        continue;
      }

      if (in_array($field, ['monto_venta', 'ganancia_neta'], true)) {
        if (!is_numeric($value)) {
          $errors[] = ['field' => $field, 'message' => 'debe ser numérico'];
          continue;
        }
        $data[$field] = (float)$value;
        continue;
      }

      if (!is_string($value) || trim($value) === '') {
        $errors[] = ['field' => $field, 'message' => 'debe ser texto no vacío'];
        continue;
      }
      $data[$field] = trim($value);
    }

    return [$data, $errors];
  }

  public function crearVenta(Request $req, Response $res): void {
    $payload = $req->getJson();
    if (!$payload) {
      $res->json([
        'data' => null,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [['code' => 'bad_request', 'message' => 'JSON inválido']],
      ], 400);
      return;
    }

    [$data, $errors] = $this->validarVentaPayload($payload, true);
    if ($errors) {
      $res->json([
        'data' => null,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => $errors,
      ], 422);
      return;
    }

    $id = $this->financieros->crearVenta($data);
    $venta = $this->financieros->buscarVentaPorId($id);

    $res->json([
      'data' => $venta,
      'meta' => ['requestId' => $req->getRequestId()],
      'errors' => [],
    ], 201);
  }

  public function actualizarVenta(Request $req, Response $res, array $params): void {
    $id = (int)($params['id_venta'] ?? 0);
    if ($id <= 0) {
      $res->json([
        'data' => null,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [['code' => 'bad_request', 'message' => 'id_venta inválido']],
      ], 400);
      return;
    }

    $payload = $req->getJson();
    if (!$payload) {
      $res->json([
        'data' => null,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [['code' => 'bad_request', 'message' => 'JSON inválido']],
      ], 400);
      return;
    }

    [$data, $errors] = $this->validarVentaPayload($payload, false);
    if ($errors) {
      $res->json([
        'data' => null,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => $errors,
      ], 422);
      return;
    }

    if (!$data) {
      $res->json([
        'data' => null,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [['code' => 'bad_request', 'message' => 'sin cambios para actualizar']],
      ], 400);
      return;
    }

    $venta = $this->financieros->buscarVentaPorId($id);
    if (!$venta) {
      $res->json([
        'data' => null,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [['code' => 'not_found', 'message' => 'venta no encontrada']],
      ], 404);
      return;
    }

    $this->financieros->actualizarVenta($id, $data);
    $ventaActualizada = $this->financieros->buscarVentaPorId($id);

    $res->json([
      'data' => $ventaActualizada,
      'meta' => ['requestId' => $req->getRequestId()],
      'errors' => [],
    ]);
  }

  public function eliminarVenta(Request $req, Response $res, array $params): void {
    $id = (int)($params['id_venta'] ?? 0);
    if ($id <= 0) {
      $res->json([
        'data' => null,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [['code' => 'bad_request', 'message' => 'id_venta inválido']],
      ], 400);
      return;
    }

    $venta = $this->financieros->buscarVentaPorId($id);
    if (!$venta) {
      $res->json([
        'data' => null,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [['code' => 'not_found', 'message' => 'venta no encontrada']],
      ], 404);
      return;
    }

    $this->financieros->eliminarVenta($id);

    $res->json([
      'data' => ['id_venta' => $id, 'eliminado' => true],
      'meta' => ['requestId' => $req->getRequestId()],
      'errors' => [],
    ]);
  }

  public function ventasPorPeriodo(Request $req, Response $res): void {
    $rango = $this->obtenerRangoFechas($req, $res);
    if (!$rango) {
      return;
    }
    [$inicio, $fin] = $rango;

    $ventas = $this->financieros->listarVentasPorPeriodo($inicio, $fin);
    $totales = ['total_bruto' => 0.0, 'total_neto' => 0.0];
    foreach ($ventas as $venta) {
      $totales['total_bruto'] += (float)($venta['monto_venta'] ?? 0);
      $totales['total_neto'] += (float)($venta['ganancia_neta'] ?? 0);
    }
    $canales = $this->financieros->obtenerSumatoriasPorCanalRango($inicio, $fin);

    $res->json([
      'data' => [
        'periodo' => ['inicio' => $inicio, 'fin' => $fin],
        'ventas' => $ventas,
        'totales' => $totales,
        'canales' => $canales,
      ],
      'meta' => ['requestId' => $req->getRequestId(), 'count' => count($ventas)],
      'errors' => [],
    ]);
  }

  public function ventasPorCanal(Request $req, Response $res): void {
    $periodo = $this->obtenerPeriodo($req, $res);
    if (!$periodo) {
      return;
    }
    [$anio, $mes] = $periodo;

    $sumatorias = $this->financieros->obtenerSumatoriasPorCanalAnioMes($anio, $mes);

    $res->json([
      'data' => [
        'periodo' => ['anio' => $anio, 'mes' => $mes],
        'canales' => $sumatorias,
      ],
      'meta' => ['requestId' => $req->getRequestId()],
      'errors' => [],
    ]);
  }

  public function exportarVentas(Request $req, Response $res): void {
    $rango = $this->obtenerRangoFechas($req, $res);
    if (!$rango) {
      return;
    }
    [$inicio, $fin] = $rango;

    $ventas = $this->financieros->listarVentasPorPeriodo($inicio, $fin);

    $filename = "ventas_{$inicio}_{$fin}.csv";
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');
    fputcsv($output, [
      'id_venta',
      'fecha_venta',
      'canal_venta',
      'concepto_venta',
      'monto_venta',
      'comision_asesor',
      'ganancia_neta',
    ]);
    foreach ($ventas as $venta) {
      fputcsv($output, [
        $venta['id_venta'] ?? null,
        $venta['fecha_venta'] ?? null,
        $venta['canal_venta'] ?? null,
        $venta['concepto_venta'] ?? null,
        $venta['monto_venta'] ?? null,
        $venta['comision_asesor'] ?? null,
        $venta['ganancia_neta'] ?? null,
      ]);
    }
    fclose($output);
    exit;
  }
}
