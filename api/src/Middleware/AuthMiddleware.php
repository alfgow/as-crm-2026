<?php
namespace App\Middleware;

use App\Core\Jwt;
use App\Core\Request;
use App\Core\Response;

final class AuthMiddleware {
  private string $accessSecret;

  public function __construct(string $accessSecret) {
    $this->accessSecret = $accessSecret;
  }

  /**
   * @return array{userId:int, role?:string}
   */
  public function handle(Request $req, Response $res): array {
    $token = $req->bearerToken();
    if (!$token) {
      $res->json([
        'data' => null,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [[ 'code' => 'unauthorized', 'message' => 'Missing bearer token' ]]
      ], 401);
    }

    $payload = Jwt::verify($token, $this->accessSecret);
    if (!$payload || empty($payload['sub'])) {
      $res->json([
        'data' => null,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [[ 'code' => 'unauthorized', 'message' => 'Invalid or expired token' ]]
      ], 401);
    }

    return [
      'userId' => (int)$payload['sub'],
      'role' => isset($payload['role']) ? (string)$payload['role'] : null,
    ];
  }
}
