<?php
namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\InquilinoRepository;
use App\Repositories\ValidacionAwsRepository;

final class ValidacionAwsController {
  private InquilinoRepository $inquilinos;
  private ValidacionAwsRepository $validaciones;

  public function __construct(InquilinoRepository $inquilinos, ValidacionAwsRepository $validaciones) {
    $this->inquilinos = $inquilinos;
    $this->validaciones = $validaciones;
  }

  public function manual(Request $req, Response $res): void {
    $slug = $this->getSlugFromRequest($req);
    $this->validar($req, $res, ['slug' => $slug]);
  }

  public function procesar(Request $req, Response $res): void {
    $slug = $this->getSlugFromRequest($req);
    $this->validar($req, $res, ['slug' => $slug]);
  }

  public function validar(Request $req, Response $res, array $params): void {
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

    $idInquilino = (int)$inquilino['id'];
    $archivos = $inquilino['archivos'] ?? [];
    if ($archivos === []) {
      $archivos = $this->inquilinos->findArchivosByInquilinoId($idInquilino);
    }

    $flags = [
      'selfie' => false,
      'ine_frontal' => false,
      'ine_reverso' => false,
      'pasaporte' => false,
      'forma_migratoria' => false,
      'comprobantes' => 0,
    ];

    foreach ($archivos as $archivo) {
      $tipo = strtolower((string)($archivo['tipo'] ?? ''));
      switch ($tipo) {
        case 'selfie':
          $flags['selfie'] = true;
          break;
        case 'ine_frontal':
          $flags['ine_frontal'] = true;
          break;
        case 'ine_reverso':
          $flags['ine_reverso'] = true;
          break;
        case 'pasaporte':
          $flags['pasaporte'] = true;
          break;
        case 'forma_migratoria':
          $flags['forma_migratoria'] = true;
          break;
        case 'comprobante_ingreso':
          $flags['comprobantes']++;
          break;
      }
    }

    $comentario = sprintf(
      "[%s] Validación iniciada manualmente (mock). Archivos: selfie=%s, INE(F)=%s, INE(R)=%s, pasaporte=%s, FM=%s, comprobantes=%d",
      date('Y-m-d H:i:s'),
      $flags['selfie'] ? 'sí' : 'no',
      $flags['ine_frontal'] ? 'sí' : 'no',
      $flags['ine_reverso'] ? 'sí' : 'no',
      $flags['pasaporte'] ? 'sí' : 'no',
      $flags['forma_migratoria'] ? 'sí' : 'no',
      $flags['comprobantes']
    );

    $payload = [
      'evento' => 'validacion_iniciada',
      'comentario' => $comentario,
      'archivos' => $flags,
      'timestamp' => date(DATE_ATOM),
    ];

    try {
      $this->validaciones->guardarValidacionMock($idInquilino, $comentario, $payload);
    } catch (\Throwable $e) {
      $res->json([
        'data' => null,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [['code' => 'server_error', 'message' => 'Error al iniciar la validación', 'details' => $e->getMessage()]],
      ], 500);
      return;
    }

    $res->json([
      'data' => [
        'ok' => true,
        'mensaje' => 'Validación iniciada. (Siguiente paso: integrar llamadas a AWS Textract/Rekognition).',
        'resumen' => [
          'slug' => $inquilino['slug'] ?? $slug,
          'nombre' => trim((string)($inquilino['nombre_inquilino'] ?? '') . ' ' . (string)($inquilino['apellidop_inquilino'] ?? '') . ' ' . (string)($inquilino['apellidom_inquilino'] ?? '')),
          'tipo_id' => $inquilino['tipo_id'] ?? null,
          'archivos' => $flags,
        ],
        'validacion_actualizada' => $payload,
      ],
      'meta' => ['requestId' => $req->getRequestId()],
      'errors' => [],
    ]);
  }

  private function getSlugFromRequest(Request $req): string {
    $query = $req->getQuery();
    $body = $req->getJson() ?? [];
    return trim((string)($query['slug'] ?? $body['slug'] ?? ''));
  }
}
