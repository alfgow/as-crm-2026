<?php
namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\BlogRepository;

final class BlogController {
  private BlogRepository $blog;

  public function __construct(BlogRepository $blog) {
    $this->blog = $blog;
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
