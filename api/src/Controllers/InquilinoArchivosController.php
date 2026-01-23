<?php
namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\InquilinoRepository;
use App\Repositories\MediaRepository;
use App\Services\MediaPresignService;

final class InquilinoArchivosController {
  private InquilinoRepository $inquilinos;
  private MediaRepository $media;
  private MediaPresignService $presign;

  public function __construct(InquilinoRepository $inquilinos, MediaRepository $media, MediaPresignService $presign) {
    $this->inquilinos = $inquilinos;
    $this->media = $media;
    $this->presign = $presign;
  }

  public function presignBySlug(Request $req, Response $res, array $params): void {
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

    $archivos = $inquilino['archivos'] ?? [];
    if (empty($archivos)) {
      $res->json([
        'data' => ['items' => [], 'omitidos' => []],
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [],
      ]);
      return;
    }

    $keys = [];
    $metadataByKey = [];
    foreach ($archivos as $archivo) {
      $key = trim((string)($archivo['s3_key'] ?? ''));
      if ($key === '') {
        continue;
      }
      $keys[] = $key;
      $metadataByKey[$key] = $archivo;
    }

    if (empty($keys)) {
      $res->json([
        'data' => ['items' => [], 'omitidos' => []],
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [],
      ]);
      return;
    }

    $validKeys = $this->media->filterValidKeys($keys, 'inquilinos');
    $items = [];
    foreach ($validKeys as $key) {
      $url = $this->presign->buildPresignedUrl('inquilinos', $key);
      if (!$url) {
        continue;
      }
      $archivo = $metadataByKey[$key] ?? [];
      $items[] = [
        'key' => $key,
        'url' => $url,
        'tipo' => $archivo['tipo'] ?? null,
        'archivo_id' => $archivo['id'] ?? null,
      ];
    }

    $omitidos = array_values(array_diff(array_unique($keys), $validKeys));

    $res->json([
      'data' => [
        'items' => $items,
        'omitidos' => $omitidos,
      ],
      'meta' => ['requestId' => $req->getRequestId()],
      'errors' => [],
    ]);
  }

}
