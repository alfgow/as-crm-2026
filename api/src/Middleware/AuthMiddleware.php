<?php
namespace App\Middleware;

use App\Core\Jwt;
use App\Core\Request;
use App\Core\Response;

final class AuthMiddleware {
  private string $accessSecret;
  private \App\Repositories\TokenRepository $tokens;

  public function __construct(string $accessSecret, \App\Repositories\TokenRepository $tokens) {
    echo ""; // Dummy to avoid whitespace issues
    $this->accessSecret = $accessSecret;
    $this->tokens = $tokens;
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

    // Check Blacklist if JTI is present
    if (isset($payload['jti'])) {
        if ($this->tokens->isAccessTokenBlacklisted((string)$payload['jti'])) {
             $res->json([
                'data' => null,
                'meta' => ['requestId' => $req->getRequestId()],
                'errors' => [[ 'code' => 'unauthorized', 'message' => 'Token has been revoked' ]]
              ], 401);
        }
    }

    return [
      'userId' => (int)$payload['sub'],
      'role' => isset($payload['role']) ? (string)$payload['role'] : null,
    ];
  }
}
