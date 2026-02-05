<?php
namespace App\Core;

final class PaginatedResponse {
  /**
   * Send a paginated JSON response
   *
   * @param array $items Items to return
   * @param int $total Total count of items
   * @param int $page Current page
   * @param int $perPage Items per page
   * @param string $requestId Request ID for tracking
   * @param array $extra Additional data to include
   */
  public static function json(
    array $items,
    int $total,
    int $page,
    int $perPage,
    string $requestId,
    array $extra = []
  ): void {
    $totalPages = (int) ceil($total / $perPage);
    
    $data = array_merge([
      'items' => $items,
      'pagination' => [
        'page' => $page,
        'per_page' => $perPage,
        'total' => $total,
        'total_pages' => $totalPages,
        'has_next' => $page < $totalPages,
        'has_prev' => $page > 1,
      ],
    ], $extra);

    http_response_code(200);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
      'data' => $data,
      'meta' => ['requestId' => $requestId],
      'errors' => [],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
  }

  /**
   * Parse pagination parameters from request
   *
   * @param array $query Query parameters
   * @param int $defaultPage Default page number
   * @param int $defaultPerPage Default items per page
   * @param int $maxPerPage Maximum allowed items per page
   * @return array [page, per_page]
   */
  public static function parseParams(
    array $query,
    int $defaultPage = 1,
    int $defaultPerPage = 20,
    int $maxPerPage = 100
  ): array {
    $page = (int) ($query['page'] ?? $defaultPage);
    $perPage = (int) ($query['per_page'] ?? $defaultPerPage);

    if ($page < 1) {
      $page = 1;
    }
    if ($perPage < 1) {
      $perPage = $defaultPerPage;
    }
    if ($perPage > $maxPerPage) {
      $perPage = $maxPerPage;
    }

    return [$page, $perPage];
  }
}
