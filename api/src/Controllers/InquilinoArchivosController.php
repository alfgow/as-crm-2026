<?php
namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\PaginatedResponse;
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

  /**
   * GET /api/v1/inquilinos/slug/{slug}/archivos-presignados
   * 
   * Query params:
   * - page: número de página (default: 1)
   * - per_page: items por página (default: 20, max: 100)
   * - all: si es "true", devuelve todos los archivos sin paginar (cuidado con muchos archivos)
   */
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

    // Parse pagination params
    $query = $req->getQuery();
    $returnAll = ($query['all'] ?? '') === 'true';
    
    if ($returnAll) {
      $page = null;
      $perPage = null;
    } else {
      [$page, $perPage] = PaginatedResponse::parseParams($query, 1, 20, 100);
    }

    // Fetch archivos with pagination (optimizado - solo carga archivos, no todo el inquilino)
    $result = $this->inquilinos->findArchivosBySlugPaginated($slug, $page, $perPage);
    
    if ($result === null) {
      $res->json([
        'data' => null,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [['code' => 'not_found', 'message' => 'Inquilino no encontrado']],
      ], 404);
      return;
    }

    $this->respondWithPresignedOptimized($req, $res, $result['items'], $result['total'], $page, $perPage);
  }

  /**
   * GET /api/v1/inquilinos/{id}/archivos-presignados
   * 
   * Query params:
   * - page: número de página (default: 1)
   * - per_page: items por página (default: 20, max: 100)
   * - all: si es "true", devuelve todos los archivos sin paginar (cuidado con muchos archivos)
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

    // Parse pagination params
    $query = $req->getQuery();
    $returnAll = ($query['all'] ?? '') === 'true';
    
    if ($returnAll) {
      $page = null;
      $perPage = null;
    } else {
      [$page, $perPage] = PaginatedResponse::parseParams($query, 1, 20, 100);
    }

    // Fetch archivos with pagination (optimizado - solo carga archivos, no todo el inquilino)
    $result = $this->inquilinos->findArchivosPaginated($id, $page, $perPage);
    
    if ($result === null) {
      $res->json([
        'data' => null,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [['code' => 'not_found', 'message' => 'Inquilino no encontrado']],
      ], 404);
      return;
    }

    $this->respondWithPresignedOptimized($req, $res, $result['items'], $result['total'], $page, $perPage);
  }

  /**
   * Versión optimizada que:
   * 1. Usa paginación para evitar cargar miles de archivos
   * 2. Valida keys en lotes para reducir queries
   * 3. Genera URLs presignadas solo para archivos válidos
   * 4. Usa caché en memoria para validaciones
   */
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

    // Extraer keys y metadata
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

    // Validar keys en lotes (batch size de 100 para evitar queries muy grandes)
    // Usa caché en memoria para no repetir validaciones en la misma request
    $validKeys = $this->media->filterValidKeysBatched($keys, 'inquilinos', 100);
    
    // Generar URLs presignadas solo para archivos válidos
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
        'mime_type' => $archivo['mime_type'] ?? null,
        'size' => isset($archivo['size']) ? (int)$archivo['size'] : null,
      ];
    }

    $omitidos = array_values(array_diff(array_unique($keys), $validKeys));

    // Respuesta paginada o completa según el modo
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
