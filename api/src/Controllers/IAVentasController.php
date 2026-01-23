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

  public function total(Request $req, Response $res): void {
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
}
