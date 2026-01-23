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

  public function index(Request $req, Response $res): void {
    $query = $req->getQuery();
    $anio = (int)($query['anio'] ?? date('Y'));
    $mes = (int)($query['mes'] ?? date('n'));

    if ($anio <= 0 || $mes < 1 || $mes > 12) {
      $res->json([
        'data' => null,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [['code' => 'bad_request', 'message' => 'anio y mes inválidos']],
      ], 400);
      return;
    }

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

  public function modelo(Request $req, Response $res): void {
    $query = $req->getQuery();
    $anio = (int)($query['anio'] ?? date('Y'));

    if ($anio <= 0) {
      $res->json([
        'data' => null,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [['code' => 'bad_request', 'message' => 'anio inválido']],
      ], 400);
      return;
    }

    $totalAnual = $this->financieros->obtenerIngresosAnuales((string)$anio);
    $porMes = $this->financieros->obtenerIngresosPorMes((string)$anio);

    $res->json([
      'data' => [
        'anio' => $anio,
        'total_anual' => $totalAnual,
        'ingresos_por_mes' => $porMes,
      ],
      'meta' => ['requestId' => $req->getRequestId(), 'count' => count($porMes)],
      'errors' => [],
    ]);
  }
}
