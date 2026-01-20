<?php
namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\InquilinoRepository;

final class ValidacionIdentidadController {
  private InquilinoRepository $inquilinos;

  public function __construct(InquilinoRepository $inquilinos) {
    $this->inquilinos = $inquilinos;
  }

  public function index(Request $req, Response $res, array $params): void {
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

    $archivosIdentidad = $this->inquilinos->findArchivosIdentidad((int)$inquilino['id']);

    $res->json([
      'data' => [
        'inquilino' => $inquilino,
        'archivos_identidad' => $archivosIdentidad,
      ],
      'meta' => ['requestId' => $req->getRequestId()],
      'errors' => [],
    ]);
  }

  public function procesar(Request $req, Response $res): void {
    $res->json([
      'data' => [
        'success' => true,
        'msg' => 'Validación exitosa',
      ],
      'meta' => ['requestId' => $req->getRequestId()],
      'errors' => [],
    ]);
  }

  public function resultado(Request $req, Response $res, array $params): void {
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

    $archivosIdentidad = $this->inquilinos->findArchivosIdentidad((int)$inquilino['id']);

    $res->json([
      'data' => [
        'inquilino' => $inquilino,
        'archivos_identidad' => $archivosIdentidad,
        'validaciones' => $inquilino['validaciones'] ?? null,
      ],
      'meta' => ['requestId' => $req->getRequestId()],
      'errors' => [],
    ]);
  }
}
