<?php
namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\PaginatedResponse;
use App\Repositories\ArrendadorRepository;
use App\Repositories\MediaRepository;
use App\Services\MediaPresignService;

final class ArrendadorArchivosController {
  private ArrendadorRepository $arrendadores;
  private MediaRepository $media;
  private MediaPresignService $presign;

  public function __construct(ArrendadorRepository $arrendadores, MediaRepository $media, MediaPresignService $presign) {
    $this->arrendadores = $arrendadores;
    $this->media = $media;
    $this->presign = $presign;
  }

  /**
   * GET /api/v1/arrendadores/{id}/archivos-presignados
   *
   * Query params:
   * - page: número de página (default: 1)
   * - per_page: items por página (default: 20, max: 100)
   * - all: si es "true", devuelve todos los archivos sin paginar
   */
  public function presignById(Request $req, Response $res, array $params): void {
    $id = (int)($params['id'] ?? 0);
    if ($id <= 0) {
      $res->json([
        'data' => null,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [['code' => 'bad_request', 'message' => 'id inválido']],
      ], 400);
      return;
    }

    $query = $req->getQuery();
    $returnAll = ($query['all'] ?? '') === 'true';

    if ($returnAll) {
      $page = null;
      $perPage = null;
    } else {
      [$page, $perPage] = PaginatedResponse::parseParams($query, 1, 20, 100);
    }

    $result = $this->arrendadores->findArchivosPaginated($id, $page, $perPage);

    if ($result === null) {
      $res->json([
        'data' => null,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [['code' => 'not_found', 'message' => 'Arrendador no encontrado']],
      ], 404);
      return;
    }

    $this->respondWithPresignedOptimized($req, $res, $result['items'], $result['total'], $page, $perPage);
  }

  private function respondWithPresignedOptimized(
    Request $req,
    Response $res,
    array $archivos,
    int $total,
    ?int $page,
    ?int $perPage
  ): void {
    if (empty($archivos)) {
      if ($page !== null && $perPage !== null) {
        PaginatedResponse::json([], 0, $page, $perPage, $req->getRequestId(), ['omitidos' => []]);
      } else {
        $res->json([
          'data' => ['items' => [], 'omitidos' => []],
          'meta' => ['requestId' => $req->getRequestId()],
          'errors' => [],
        ]);
      }
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
      if ($page !== null && $perPage !== null) {
        PaginatedResponse::json([], $total, $page, $perPage, $req->getRequestId(), ['omitidos' => []]);
      } else {
        $res->json([
          'data' => ['items' => [], 'omitidos' => []],
          'meta' => ['requestId' => $req->getRequestId()],
          'errors' => [],
        ]);
      }
      return;
    }

    $validKeys = $this->media->filterValidKeysBatched($keys, 'arrendadores', 100);

    $items = [];
    foreach ($validKeys as $key) {
      $url = $this->presign->buildPresignedUrl('arrendadores', $key);
      if (!$url) {
        continue;
      }
      $archivo = $metadataByKey[$key] ?? [];
      $items[] = [
        'key' => $key,
        'url' => $url,
        'tipo' => $archivo['tipo'] ?? null,
        'archivo_id' => $archivo['id_archivo'] ?? null,
        'mime_type' => $archivo['mime_type'] ?? null,
        'size' => isset($archivo['size']) ? (int)$archivo['size'] : null,
      ];
    }

    $omitidos = array_values(array_diff(array_unique($keys), $validKeys));

    if ($page !== null && $perPage !== null) {
      PaginatedResponse::json($items, $total, $page, $perPage, $req->getRequestId(), ['omitidos' => $omitidos]);
    } else {
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
}
