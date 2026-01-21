<?php
namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\InquilinoRepository;
use App\Repositories\ValidacionLegalRepository;

final class ValidacionLegalController {
  private ValidacionLegalRepository $validaciones;
  private InquilinoRepository $inquilinos;

  public function __construct(ValidacionLegalRepository $validaciones, InquilinoRepository $inquilinos) {
    $this->validaciones = $validaciones;
    $this->inquilinos = $inquilinos;
  }

  public function status(Request $req, Response $res, array $params): void {
    $id = (int)($params['id'] ?? 0);
    if ($id <= 0) {
      $res->json([
        'data' => null,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [['code' => 'bad_request', 'message' => 'id inválido']],
      ], 400);
      return;
    }

    $data = $this->validaciones->obtenerValidaciones($id);
    $res->json([
      'data' => $data,
      'meta' => ['requestId' => $req->getRequestId()],
      'errors' => [],
    ]);
  }

  public function run(Request $req, Response $res, array $params): void {
    $id = (int)($params['id'] ?? 0);
    if ($id <= 0) {
      $res->json([
        'data' => null,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [['code' => 'bad_request', 'message' => 'id inválido']],
      ], 400);
      return;
    }

    $inquilino = $this->inquilinos->findById($id);
    if (!$inquilino) {
      $res->json([
        'data' => null,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [['code' => 'not_found', 'message' => 'Inquilino no encontrado']],
      ], 404);
      return;
    }

    $nombre = trim((string)($inquilino['nombre_inquilino'] ?? ''));
    $apellidoP = trim((string)($inquilino['apellidop_inquilino'] ?? ''));
    $apellidoM = trim((string)($inquilino['apellidom_inquilino'] ?? ''));
    $curp = $inquilino['curp'] ?? null;
    $rfc = $inquilino['rfc'] ?? null;
    $nombreCompleto = trim($nombre . ' ' . $apellidoP . ' ' . $apellidoM);

    if ($nombre === '' || $apellidoP === '') {
      $res->json([
        'data' => null,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [['code' => 'bad_request', 'message' => 'Nombre y apellido paterno son obligatorios']],
      ], 400);
      return;
    }

    try {
      $resultado = $this->validaciones->buscarEnGoogle(
        $id,
        $nombreCompleto,
        $nombre,
        $apellidoP,
        $apellidoM,
        $curp,
        $rfc
      );
      $res->json([
        'data' => $resultado,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [],
      ]);
    } catch (\Throwable $e) {
      $res->json([
        'data' => null,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [['code' => 'server_error', 'message' => 'Error interno', 'details' => $e->getMessage()]],
      ], 500);
    }
  }

  public function ultimo(Request $req, Response $res, array $params): void {
    $id = (int)($params['id'] ?? 0);
    if ($id <= 0) {
      $res->json([
        'data' => null,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [['code' => 'bad_request', 'message' => 'id inválido']],
      ], 400);
      return;
    }

    $reporte = $this->validaciones->obtenerUltimoReportePorInquilino($id);
    if ($reporte && !empty($reporte['resultado'])) {
      $decoded = json_decode((string)$reporte['resultado'], true);
      $reporte['resultado'] = json_last_error() === JSON_ERROR_NONE ? $decoded : [];
    }

    $res->json([
      'data' => $reporte,
      'meta' => ['requestId' => $req->getRequestId()],
      'errors' => [],
    ]);
  }

  public function historial(Request $req, Response $res, array $params): void {
    $id = (int)($params['id'] ?? 0);
    if ($id <= 0) {
      $res->json([
        'data' => null,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [['code' => 'bad_request', 'message' => 'id inválido']],
      ], 400);
      return;
    }

    $inquilino = $this->inquilinos->findById($id);
    if (!$inquilino) {
      $res->json([
        'data' => null,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [['code' => 'not_found', 'message' => 'Inquilino no encontrado']],
      ], 404);
      return;
    }

    $archivos = $this->inquilinos->findArchivosByInquilinoId($id);
    $categorias = [];
    foreach ($archivos as $archivo) {
      $tipo = strtolower((string)($archivo['tipo'] ?? '')) ?: 'otros';
      $categorias[$tipo][] = $archivo;
    }

    $historial = $this->validaciones->obtenerHistorialPorInquilino($id);

    $res->json([
      'data' => [
        'inquilino' => $inquilino,
        'archivos' => $archivos,
        'categorias' => $categorias,
        'historial' => $historial,
      ],
      'meta' => ['requestId' => $req->getRequestId()],
      'errors' => [],
    ]);
  }

  public function historialJson(Request $req, Response $res, array $params): void {
    $id = (int)($params['id'] ?? 0);
    if ($id <= 0) {
      $res->json([
        'data' => null,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [['code' => 'bad_request', 'message' => 'id inválido']],
      ], 400);
      return;
    }

    $historial = $this->validaciones->obtenerHistorialPorInquilino($id);
    $res->json([
      'data' => $historial,
      'meta' => ['requestId' => $req->getRequestId()],
      'errors' => [],
    ]);
  }

  public function toggleDemandas(Request $req, Response $res, array $params): void {
    $id = (int)($params['id'] ?? 0);
    if ($id <= 0) {
      $res->json([
        'data' => null,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [['code' => 'bad_request', 'message' => 'id inválido']],
      ], 400);
      return;
    }

    $body = $req->getJson();
    $estado = (int)($body['proceso_inv_demandas'] ?? 2);
    $this->validaciones->actualizarProcesoDemandas($id, $estado);

    $res->json([
      'data' => [
        'ok' => true,
        'nuevo_estado' => $estado,
      ],
      'meta' => ['requestId' => $req->getRequestId()],
      'errors' => [],
    ]);
  }

  public function historialPorSlug(Request $req, Response $res, array $params): void {
    $slug = trim((string)($params['slug'] ?? ''));
    if ($slug === '') {
      $res->json([
        'data' => null,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [['code' => 'bad_request', 'message' => 'slug inválido']],
      ], 400);
      return;
    }

    $inquilino = $this->inquilinos->findBySlug($slug);
    if (!$inquilino || empty($inquilino['id'])) {
      $res->json([
        'data' => null,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [['code' => 'not_found', 'message' => 'Inquilino no encontrado']],
      ], 404);
      return;
    }

    $params['id'] = (int)$inquilino['id'];
    $this->historial($req, $res, $params);
  }
}
