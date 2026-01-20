<?php
namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\MediaRepository;

final class MediaController {
  private array $config;
  private MediaRepository $media;

  public function __construct(array $config, MediaRepository $media) {
    $this->config = $config;
    $this->media = $media;
  }

  public function presign(Request $req, Response $res): void {
    $query = $req->getQuery();
    $key = trim((string)($query['key'] ?? ''));
    $bucket = trim((string)($query['bucket'] ?? 'blog'));

    if ($key === '') {
      $res->json([
        'data' => null,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [['code' => 'bad_request', 'message' => 'key is required']],
      ], 400);
      return;
    }

    if (!$this->media->keyExists($bucket, $key)) {
      $res->json([
        'data' => null,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [['code' => 'not_found', 'message' => 'File not found']],
      ], 404);
      return;
    }

    $url = $this->buildPresignedUrl($bucket, $key);
    if (!$url) {
      $res->json([
        'data' => null,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [['code' => 'presign_failed', 'message' => 'Unable to presign file']],
      ], 500);
      return;
    }

    $res->json([
      'data' => ['key' => $key, 'url' => $url],
      'meta' => ['requestId' => $req->getRequestId()],
      'errors' => [],
    ]);
  }

  public function presignMany(Request $req, Response $res): void {
    $body = $req->getJson() ?? [];
    $keys = $body['keys'] ?? [];
    $bucket = trim((string)($body['bucket'] ?? 'blog'));

    if (!is_array($keys) || empty($keys)) {
      $res->json([
        'data' => ['items' => [], 'omitidos' => []],
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [['code' => 'bad_request', 'message' => 'keys is required']],
      ], 400);
      return;
    }

    $validKeys = $this->media->filterValidKeys($keys, $bucket);

    if (empty($validKeys)) {
      $res->json([
        'data' => [
          'items' => [],
          'omitidos' => array_values(array_unique(array_map('strval', $keys))),
        ],
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [],
      ]);
      return;
    }

    $items = [];
    foreach ($validKeys as $key) {
      $url = $this->buildPresignedUrl($bucket, $key);
      if ($url) {
        $items[] = ['key' => $key, 'url' => $url];
      }
    }

    $omitidos = array_values(array_diff(array_unique(array_map('strval', $keys)), $validKeys));

    $res->json([
      'data' => [
        'items' => $items,
        'omitidos' => $omitidos,
      ],
      'meta' => ['requestId' => $req->getRequestId()],
      'errors' => [],
    ]);
  }

  private function buildPresignedUrl(string $bucket, string $key): ?string {
    $base = rtrim($this->config['media']['presign_base_url'] ?? '', '/');
    if ($base === '') {
      return null;
    }

    $encodedKey = rawurlencode($key);
    $encodedBucket = rawurlencode($bucket);
    return $base . '/' . $encodedBucket . '/' . $encodedKey;
  }
}
