<?php
namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\InquilinoRepository;
use App\Repositories\ValidacionAwsRepository;

final class InquilinoValidacionAwsController {
  private InquilinoRepository $inquilinos;
  private ValidacionAwsRepository $validaciones;

  public function __construct(InquilinoRepository $inquilinos, ValidacionAwsRepository $validaciones) {
    $this->inquilinos = $inquilinos;
    $this->validaciones = $validaciones;
  }

  public function validarIngresosPDFSimple(Request $req, Response $res, array $params): void {
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

    $archivos = $this->inquilinos->findArchivosByTipos($id, ['comprobante_ingreso']);
    $pdfs = [];
    foreach ($archivos as $key) {
      $key = (string)$key;
      $ext = strtolower(pathinfo($key, PATHINFO_EXTENSION));
      if ($ext === 'pdf') {
        $pdfs[] = [
          's3_key' => $key,
          'ext' => $ext,
        ];
      }
    }

    $n = count($pdfs);
    $status = ($n >= 3) ? 'OK' : (($n >= 1) ? 'REVIEW' : 'FAIL');
    $payload = [
      'tipo' => 'ingresos_pdf_simple',
      'conteo' => $n,
      'archivos' => $pdfs,
      'reglas' => [
        'min_recomendado' => 3,
        'criterio' => 'OK si hay >= 3 PDFs; REVIEW si 1-2; FAIL si 0.',
      ],
      'status' => $status,
      'ts' => date('c'),
    ];

    $proceso = ($status === 'OK') ? 1 : (($status === 'FAIL') ? 0 : 2);
    $resumen = $status === 'OK'
      ? '☑️ Ingresos completos'
      : ($status === 'FAIL' ? '✖️ Sin comprobantes de ingreso' : '⚠️ Ingresos incompletos');

    $this->validaciones->guardarValidacionIngresosSimple($id, $proceso, $payload, $resumen);

    $res->json([
      'data' => [
        'ok' => true,
        'proceso' => $proceso,
        'resumen' => $resumen,
        'payload' => $payload,
      ],
      'meta' => ['requestId' => $req->getRequestId()],
      'errors' => [],
    ]);
  }

  public function obtenerArchivos(Request $req, Response $res): void {
    $query = $req->getQuery();
    $slug = trim((string)($query['slug'] ?? ''));
    $inquilino = $slug !== '' ? $this->inquilinos->findBySlug($slug) : null;
    if (!$inquilino || empty($inquilino['id'])) {
      $res->json([
        'data' => null,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [['code' => 'bad_request', 'message' => 'slug inválido']],
      ], 400);
      return;
    }

    $archivos = $this->inquilinos->findArchivosByInquilinoId((int)$inquilino['id']);
    $res->json([
      'data' => $archivos,
      'meta' => ['requestId' => $req->getRequestId()],
      'errors' => [],
    ]);
  }

  public function obtenerArchivosPorSlug(Request $req, Response $res, array $params): void {
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

    $archivos = $this->inquilinos->findArchivosByInquilinoId((int)$inquilino['id']);
    $res->json([
      'data' => $archivos,
      'meta' => ['requestId' => $req->getRequestId()],
      'errors' => [],
    ]);
  }
}
