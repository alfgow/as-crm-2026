<?php
namespace App\Middleware;

use App\Core\Jwt;
use App\Core\Request;
use App\Core\Response;

final class AuthMiddleware {
  private array $config;
  private \App\Repositories\TokenRepository $tokens;
  private string $accessCookie;
  private string $refreshCookie;
  private bool $cookiesEnabled;
  private array $cookieConfig;

  public function __construct(array $config, \App\Repositories\TokenRepository $tokens) {
    $this->config = $config;
    $this->tokens = $tokens;
    $this->cookieConfig = $config['auth_cookies'] ?? [];
    $this->accessCookie = (string)($this->cookieConfig['access_cookie'] ?? 'as_access_token');
    $this->refreshCookie = (string)($this->cookieConfig['refresh_cookie'] ?? 'as_refresh_token');
    $this->cookiesEnabled = (bool)($this->cookieConfig['enabled'] ?? false);
  }

  /**
   * @return array{userId:int, role?:string}
   */
  public function handle(Request $req, Response $res): array {
    $token = (string)($req->bearerToken() ?? '');
    if ($token === '') {
      $token = (string)($req->getCookie($this->accessCookie) ?? '');
    }

    $payload = null;
    if ($token !== '') {
      $payload = Jwt::verify($token, $this->config['jwt']['access_secret']);
      if ($payload && isset($payload['jti']) && $this->tokens->isAccessTokenBlacklisted((string)$payload['jti'])) {
        $payload = null;
      }
    }

    if (!$payload) {
      $payload = $this->issueAccessFromRefreshCookie($req);
    }

    if (!$payload || empty($payload['sub'])) {
      $message = $token === ''
        ? 'Missing bearer token'
        : 'Invalid or expired token';
      $res->json([
        'data' => null,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [[ 'code' => 'unauthorized', 'message' => $message ]]
      ], 401);
    }

    return [
      'userId' => (int)$payload['sub'],
      'role' => isset($payload['role']) ? (string)$payload['role'] : null,
    ];
  }

  private function issueAccessFromRefreshCookie(Request $req): ?array {
    if (!$this->cookiesEnabled) {
      return null;
    }

    $refreshToken = (string)($req->getCookie($this->refreshCookie) ?? '');
    if ($refreshToken === '') {
      return null;
    }

    $refreshPayload = Jwt::verify($refreshToken, $this->config['jwt']['refresh_secret']);
    if (!$refreshPayload || empty($refreshPayload['sub']) || empty($refreshPayload['jti'])) {
      return null;
    }

    if (!$this->tokens->isRefreshTokenActive((int)$refreshPayload['sub'], (string)$refreshPayload['jti'])) {
      return null;
    }

    $now = time();
    $accessPayload = [
      'iss' => $this->config['env'],
      'sub' => (int)$refreshPayload['sub'],
      'role' => (string)($refreshPayload['role'] ?? 'admin'),
      'iat' => $now,
      'exp' => $now + (int)$this->config['jwt']['access_ttl'],
      'jti' => bin2hex(random_bytes(16)),
    ];

    $accessToken = Jwt::sign($accessPayload, $this->config['jwt']['access_secret']);
    $this->setCookie($this->accessCookie, $accessToken, (int)$accessPayload['exp']);

    return $accessPayload;
  }

  private function setCookie(string $name, string $value, int $expiresAt): void {
    $options = [
      'expires' => $expiresAt,
      'path' => (string)($this->cookieConfig['path'] ?? '/'),
      'secure' => (bool)($this->cookieConfig['secure'] ?? true),
      'httponly' => (bool)($this->cookieConfig['http_only'] ?? true),
      'samesite' => (string)($this->cookieConfig['same_site'] ?? 'None'),
    ];

    $domain = (string)($this->cookieConfig['domain'] ?? '');
    if ($domain !== '') {
      $options['domain'] = $domain;
    }

    setcookie($name, $value, $options);
  }
}
