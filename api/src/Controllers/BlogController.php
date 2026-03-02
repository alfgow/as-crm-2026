<?php
namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\BlogRepository;
use App\Services\MediaUploadService;

final class BlogController {
  private BlogRepository $blog;
  private MediaUploadService $uploads;

  public function __construct(BlogRepository $blog, MediaUploadService $uploads) {
    $this->blog = $blog;
    $this->uploads = $uploads;
  }

  public function index(Request $req, Response $res): void {
    $posts = $this->blog->findAll();

    $res->json([
      'data' => $posts,
      'meta' => [
        'requestId' => $req->getRequestId(),
        'count' => count($posts),
      ],
      'errors' => [],
    ]);
  }

  public function show(Request $req, Response $res, array $params): void {
    $id = (int)($params['id'] ?? 0);
    $post = $this->blog->findById($id);

    if (!$post) {
      $res->json([
        'data' => null,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [['code' => 'not_found', 'message' => 'Post not found']],
      ], 404);
      return;
    }

    $res->json([
      'data' => $post,
      'meta' => ['requestId' => $req->getRequestId()],
      'errors' => [],
    ]);
  }

  public function showBySlug(Request $req, Response $res, array $params): void {
    $slug = trim((string)($params['slug'] ?? ''));

    if ($slug === '') {
      $res->json([
        'data' => null,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [['code' => 'bad_request', 'message' => 'slug is required']],
      ], 400);
      return;
    }

    $post = $this->blog->findBySlug($slug);

    if (!$post) {
      $res->json([
        'data' => null,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [['code' => 'not_found', 'message' => 'Post not found']],
      ], 404);
      return;
    }

    $res->json([
      'data' => $post,
      'meta' => ['requestId' => $req->getRequestId()],
      'errors' => [],
    ]);
  }

  public function store(Request $req, Response $res): void {
    $body = $req->getJson() ?? [];

    try {
      $created = $this->blog->create($body);
      $res->json([
        'data' => $created,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [],
      ], 201);
    } catch (\Throwable $e) {
      $res->json([
        'data' => null,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [['code' => 'bad_request', 'message' => $e->getMessage()]],
      ], 400);
    }
  }

  public function uploadArchivo(Request $req, Response $res, array $params): void {
    $id = (int)($params['id'] ?? 0);
    $tipo = trim((string)($_POST['tipo'] ?? ''));
    $file = $_FILES['file'] ?? null;

    if ($id <= 0) {
      $res->json([
        'data' => null,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [['code' => 'bad_request', 'message' => 'Invalid post id']],
      ], 400);
      return;
    }

    if ($tipo === '') {
      $res->json([
        'data' => null,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [['code' => 'bad_request', 'message' => 'tipo is required']],
      ], 400);
      return;
    }

    if (!$file || !isset($file['tmp_name'])) {
      $res->json([
        'data' => null,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [['code' => 'bad_request', 'message' => 'file is required']],
      ], 400);
      return;
    }

    if (!empty($file['error'])) {
      $res->json([
        'data' => null,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [['code' => 'bad_request', 'message' => 'File upload error']],
      ], 400);
      return;
    }

    $post = $this->blog->findById($id);
    if (!$post) {
      $res->json([
        'data' => null,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [['code' => 'not_found', 'message' => 'Post not found']],
      ], 404);
      return;
    }

    $originalName = (string)($file['name'] ?? 'archivo');
    $extension = pathinfo($originalName, PATHINFO_EXTENSION);
    $suffix = $extension !== '' ? '.' . strtolower($extension) : '';
    $key = sprintf('blog/%d/%s%s', $id, bin2hex(random_bytes(16)), $suffix);
    $mimeType = (string)($file['type'] ?? 'application/octet-stream');
    $size = (int)($file['size'] ?? 0);

    $upload = $this->uploads->uploadFromPath('blog', $key, (string)$file['tmp_name'], $mimeType);
    if (!$upload['ok']) {
      $res->json([
        'data' => null,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [['code' => 'upload_failed', 'message' => 'Unable to upload file']],
      ], 500);
      return;
    }

    $updated = $this->blog->updateImageKey($id, $key);
    if (!$updated) {
      $res->json([
        'data' => null,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [['code' => 'db_error', 'message' => 'Unable to save image key']],
      ], 500);
      return;
    }

    $res->json([
      'data' => [
        'post' => $updated,
        'archivo' => [
          'tipo' => $tipo,
          's3_key' => $key,
          'mime_type' => $mimeType,
          'size' => $size > 0 ? $size : null,
        ],
      ],
      'meta' => ['requestId' => $req->getRequestId()],
      'errors' => [],
    ], 201);
  }

  public function update(Request $req, Response $res, array $params): void {
    $id = (int)($params['id'] ?? 0);
    $body = $req->getJson() ?? [];

    if ($id <= 0) {
      $res->json([
        'data' => null,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [['code' => 'bad_request', 'message' => 'Invalid post id']],
      ], 400);
      return;
    }

    $updated = $this->blog->update($id, $body);
    if (!$updated) {
      $res->json([
        'data' => null,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [['code' => 'not_found', 'message' => 'Post not found']],
      ], 404);
      return;
    }

    $res->json([
      'data' => $updated,
      'meta' => ['requestId' => $req->getRequestId()],
      'errors' => [],
    ]);
  }

  public function destroy(Request $req, Response $res, array $params): void {
    $id = (int)($params['id'] ?? 0);

    if ($id <= 0) {
      $res->json([
        'data' => null,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [['code' => 'bad_request', 'message' => 'Invalid post id']],
      ], 400);
      return;
    }

    $deleted = $this->blog->delete($id);
    if (!$deleted) {
      $res->json([
        'data' => null,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [['code' => 'not_found', 'message' => 'Post not found']],
      ], 404);
      return;
    }

    $res->json([
      'data' => ['success' => true, 'id' => $id],
      'meta' => ['requestId' => $req->getRequestId()],
      'errors' => [],
    ]);
  }
}
