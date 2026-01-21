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
}
