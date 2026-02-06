<?php
namespace App\Middleware;

use App\Core\Jwt;
use App\Core\Request;
use App\Core\Response;

final class AuthMiddleware {
  private string $accessSecret;
  private \App\Repositories\TokenRepository $tokens;
  private string $accessCookie;

  public function __construct(string $accessSecret, \App\Repositories\TokenRepository $tokens, string $accessCookie = 'as_access_token') {
    echo ""; // Dummy to avoid whitespace issues
    $this->accessSecret = $accessSecret;
    $this->tokens = $tokens;
    $this->accessCookie = $accessCookie;
  }

  /**
   * @return array{userId:int, role?:string}
   */
  public function handle(Request $req, Response $res): array {
    $token = $req->bearerToken();
    if (!$token) {
      $token = $req->getCookie($this->accessCookie);
    }
    if (!$token) {
      error_log("AuthMiddleware: Missing or malformed Bearer token. Header: " . ($req->getHeader('authorization') ?? 'NULL'));
      $res->json([
        'data' => null,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [[ 'code' => 'unauthorized', 'message' => 'Missing bearer token' ]]
      ], 401);
    }

    $payload = Jwt::verify($token, $this->accessSecret);
    if (!$payload || empty($payload['sub'])) {
      error_log("AuthMiddleware: Token verification failed for token: " . substr($token, 0, 15) . "...");
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
