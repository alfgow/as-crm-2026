<?php
namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\ApiClientRepository;

final class ApiClientsController {
  private ApiClientRepository $clients;

  public function __construct(ApiClientRepository $clients) {
    $this->clients = $clients;
  }

  public function index(Request $req, Response $res, array $ctx): void {
    $items = $this->clients->findAll();

    $res->json([
      'data' => $items,
      'meta' => [
        'requestId' => $req->getRequestId(),
        'count' => count($items),
      ],
      'errors' => [],
    ]);
  }

  public function store(Request $req, Response $res, array $ctx): void {
    $body = $req->getJson();
    $name = trim((string)($body['name'] ?? ''));
    $scopes = $body['scopes'] ?? [];
    $rateLimit = (int)($body['rate_limit'] ?? 60);

    if ($name === '' || !is_array($scopes) || empty($scopes)) {
      $res->json([
        'data' => null,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [['code' => 'bad_request', 'message' => 'name and scopes are required']],
      ], 400);
      return;
    }

    try {
      $created = $this->clients->createClient($name, $scopes, $rateLimit);
      $res->json([
        'data' => $created,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [],
      ], 201);
    } catch (\Throwable $e) {
      $res->json([
        'data' => null,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [['code' => 'db_error', 'message' => $e->getMessage()]],
      ], 500);
    }
  }

  public function rotateSecret(Request $req, Response $res, array $params): void {
    $id = (int)($params['id'] ?? 0);

    if ($id <= 0) {
      $res->json([
        'data' => null,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [['code' => 'bad_request', 'message' => 'Invalid client id']],
      ], 400);
      return;
    }

    try {
      $result = $this->clients->rotateSecret($id);
      $res->json([
        'data' => $result,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [],
      ]);
    } catch (\Throwable $e) {
      $res->json([
        'data' => null,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [['code' => 'db_error', 'message' => $e->getMessage()]],
      ], 500);
    }
  }
}
