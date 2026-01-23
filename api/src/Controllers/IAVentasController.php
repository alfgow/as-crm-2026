<?php
namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\FinancieroRepository;

final class IAVentasController {
  private FinancieroRepository $financieros;

  public function __construct(FinancieroRepository $financieros) {
    $this->financieros = $financieros;
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

  private function obtenerRangoFechas(Request $req, Response $res): ?array {
    $query = $req->getQuery();
    $inicio = (string)($query['inicio'] ?? '');
    $fin = (string)($query['fin'] ?? '');

    if (!preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $inicio)
      || !preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $fin)) {
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

  public function index(Request $req, Response $res): void {
    $periodo = $this->obtenerPeriodo($req, $res);
    if (!$periodo) {
      return;
    }
    [$anio, $mes] = $periodo;

    $resumen = $this->financieros->obtenerResumenPorAnioMes($anio, $mes);
    $ventas = $this->financieros->listarVentasPorAnioMes($anio, $mes);

    $res->json([
      'data' => [
        'periodo' => ['anio' => $anio, 'mes' => $mes],
        'resumen' => $resumen,
        'ventas' => $ventas,
      ],
      'meta' => ['requestId' => $req->getRequestId(), 'count' => count($ventas)],
      'errors' => [],
    ]);
  }

  public function total(Request $req, Response $res): void {
    $periodo = $this->obtenerPeriodo($req, $res);
    if (!$periodo) {
      return;
    }
    [$anio, $mes] = $periodo;

    $resumen = $this->financieros->obtenerResumenPorAnioMes($anio, $mes);

    $res->json([
      'data' => [
        'periodo' => ['anio' => $anio, 'mes' => $mes],
        'resumen' => $resumen,
      ],
      'meta' => ['requestId' => $req->getRequestId()],
      'errors' => [],
    ]);
  }

  public function canal(Request $req, Response $res): void {
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

  public function modelo(Request $req, Response $res): void {
    $periodo = $this->obtenerPeriodo($req, $res);
    if (!$periodo) {
      return;
    }
    [$anio, $mes] = $periodo;

    $ventas = $this->financieros->listarVentasPorAnioMes($anio, $mes);
    $modelos = [];
    foreach ($ventas as $venta) {
      $modelo = (string)($venta['concepto_venta'] ?? 'Sin concepto');
      if (!isset($modelos[$modelo])) {
        $modelos[$modelo] = [
          'modelo' => $modelo,
          'total_bruto' => 0.0,
          'total_neto' => 0.0,
          'polizas' => 0,
        ];
      }
      $modelos[$modelo]['total_bruto'] += (float)($venta['monto_venta'] ?? 0);
      $modelos[$modelo]['total_neto'] += (float)($venta['ganancia_neta'] ?? 0);
      $modelos[$modelo]['polizas'] += 1;
    }

    $res->json([
      'data' => [
        'periodo' => ['anio' => $anio, 'mes' => $mes],
        'agrupado_por' => 'concepto_venta',
        'modelos' => array_values($modelos),
      ],
      'meta' => ['requestId' => $req->getRequestId(), 'count' => count($modelos)],
      'errors' => [],
    ]);
  }

  public function fecha(Request $req, Response $res): void {
    $rango = $this->obtenerRangoFechas($req, $res);
    if (!$rango) {
      return;
    }
    [$inicio, $fin] = $rango;

    $ventas = $this->financieros->listarVentasPorPeriodo($inicio, $fin);
    $fechas = [];
    foreach ($ventas as $venta) {
      $fecha = (string)($venta['fecha'] ?? $venta['fecha_venta'] ?? '');
      if ($fecha === '') {
        continue;
      }
      if (!isset($fechas[$fecha])) {
        $fechas[$fecha] = [
          'fecha' => $fecha,
          'total_bruto' => 0.0,
          'total_neto' => 0.0,
          'polizas' => 0,
        ];
      }
      $fechas[$fecha]['total_bruto'] += (float)($venta['monto_venta'] ?? 0);
      $fechas[$fecha]['total_neto'] += (float)($venta['ganancia_neta'] ?? 0);
      $fechas[$fecha]['polizas'] += 1;
    }

    $res->json([
      'data' => [
        'periodo' => ['inicio' => $inicio, 'fin' => $fin],
        'fechas' => array_values($fechas),
      ],
      'meta' => ['requestId' => $req->getRequestId(), 'count' => count($fechas)],
      'errors' => [],
    ]);
  }

  public function usuario(Request $req, Response $res): void {
    $periodo = $this->obtenerPeriodo($req, $res);
    if (!$periodo) {
      return;
    }
    [$anio, $mes] = $periodo;

    $ventas = $this->financieros->listarVentasPorAnioMes($anio, $mes);
    $usuarios = [];
    foreach ($ventas as $venta) {
      $usuario = (string)($venta['comision_asesor'] ?? 'Sin asesor');
      if (!isset($usuarios[$usuario])) {
        $usuarios[$usuario] = [
          'usuario' => $usuario,
          'total_bruto' => 0.0,
          'total_neto' => 0.0,
          'polizas' => 0,
        ];
      }
      $usuarios[$usuario]['total_bruto'] += (float)($venta['monto_venta'] ?? 0);
      $usuarios[$usuario]['total_neto'] += (float)($venta['ganancia_neta'] ?? 0);
      $usuarios[$usuario]['polizas'] += 1;
    }

    $res->json([
      'data' => [
        'periodo' => ['anio' => $anio, 'mes' => $mes],
        'agrupado_por' => 'comision_asesor',
        'usuarios' => array_values($usuarios),
      ],
      'meta' => ['requestId' => $req->getRequestId(), 'count' => count($usuarios)],
      'errors' => [],
    ]);
  }

  public function proceso(Request $req, Response $res): void {
    $periodo = $this->obtenerPeriodo($req, $res);
    if (!$periodo) {
      return;
    }
    [$anio, $mes] = $periodo;

    $ventas = $this->financieros->listarVentasPorAnioMes($anio, $mes);
    $procesos = [];
    foreach ($ventas as $venta) {
      $proceso = (string)($venta['canal_venta'] ?? 'Sin canal');
      if (!isset($procesos[$proceso])) {
        $procesos[$proceso] = [
          'proceso' => $proceso,
          'total_bruto' => 0.0,
          'total_neto' => 0.0,
          'polizas' => 0,
        ];
      }
      $procesos[$proceso]['total_bruto'] += (float)($venta['monto_venta'] ?? 0);
      $procesos[$proceso]['total_neto'] += (float)($venta['ganancia_neta'] ?? 0);
      $procesos[$proceso]['polizas'] += 1;
    }

    $res->json([
      'data' => [
        'periodo' => ['anio' => $anio, 'mes' => $mes],
        'agrupado_por' => 'canal_venta',
        'procesos' => array_values($procesos),
      ],
      'meta' => ['requestId' => $req->getRequestId(), 'count' => count($procesos)],
      'errors' => [],
    ]);
  }

  public function canalPeriodo(Request $req, Response $res): void {
    $rango = $this->obtenerRangoFechas($req, $res);
    if (!$rango) {
      return;
    }
    [$inicio, $fin] = $rango;

    $sumatorias = $this->financieros->obtenerSumatoriasPorCanalRango($inicio, $fin);

    $res->json([
      'data' => [
        'periodo' => ['inicio' => $inicio, 'fin' => $fin],
        'canales' => $sumatorias,
      ],
      'meta' => ['requestId' => $req->getRequestId()],
      'errors' => [],
    ]);
  }
}
