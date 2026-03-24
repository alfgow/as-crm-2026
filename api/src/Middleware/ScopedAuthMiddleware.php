<?php
namespace App\Middleware;

use App\Core\Jwt;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\ApiTokenRevocationRepository;
use App\Repositories\TokenRepository;

final class ScopedAuthMiddleware {
  private array $config;
  private TokenRepository $tokens;
  private ApiTokenRevocationRepository $apiRevocations;
  private string $accessCookie;
  private string $refreshCookie;
  private bool $cookiesEnabled;
  private array $cookieConfig;

  public function __construct(array $config, TokenRepository $tokens, ApiTokenRevocationRepository $apiRevocations) {
    $this->config = $config;
    $this->tokens = $tokens;
    $this->apiRevocations = $apiRevocations;
    $this->cookieConfig = $config['auth_cookies'] ?? [];
    $this->accessCookie = (string)($this->cookieConfig['access_cookie'] ?? 'as_access_token');
    $this->refreshCookie = (string)($this->cookieConfig['refresh_cookie'] ?? 'as_refresh_token');
    $this->cookiesEnabled = (bool)($this->cookieConfig['enabled'] ?? false);
  }

  /**
   * @return array<string, mixed>
   */
  public function handle(Request $req, Response $res, array $requiredScopes = []): array {
    $token = (string)($req->bearerToken() ?? '');
    $tokenFromCookie = false;

    if ($token === '') {
      $token = (string)($req->getCookie($this->accessCookie) ?? '');
      $tokenFromCookie = $token !== '';
    }

    if ($token !== '') {
      $userPayload = $this->verifyUserToken($token);
      if ($userPayload !== null) {
        return [
          'actorType' => 'user',
          'userId' => (int)$userPayload['sub'],
          'role' => isset($userPayload['role']) ? (string)$userPayload['role'] : null,
          'scopes' => [],
        ];
      }

      if (!$tokenFromCookie) {
        $apiPayload = $this->verifyApiClientToken($token);
        if ($apiPayload !== null) {
          $scopes = $this->parseScopes((string)($apiPayload['scope'] ?? ''));
          if (!$this->hasRequiredScopes($scopes, $requiredScopes)) {
            $res->json([
              'data' => null,
              'meta' => ['requestId' => $req->getRequestId()],
              'errors' => [[
                'code' => 'insufficient_scope',
                'message' => 'El token API no cuenta con permisos para esta operación',
                'required_scopes' => array_values($requiredScopes),
              ]],
            ], 403);
          }

          return [
            'actorType' => 'client',
            'clientId' => (int)($apiPayload['cid'] ?? 0),
            'subject' => (string)($apiPayload['sub'] ?? ''),
            'scopes' => $scopes,
          ];
        }
      }
    }

    $refreshPayload = $this->issueUserAccessFromRefreshCookie($req);
    if ($refreshPayload !== null) {
      return [
        'actorType' => 'user',
        'userId' => (int)$refreshPayload['sub'],
        'role' => isset($refreshPayload['role']) ? (string)$refreshPayload['role'] : null,
        'scopes' => [],
      ];
    }

    $message = $token === ''
      ? 'Missing bearer token'
      : 'Invalid or expired token';
    $res->json([
      'data' => null,
      'meta' => ['requestId' => $req->getRequestId()],
      'errors' => [['code' => 'unauthorized', 'message' => $message]],
    ], 401);
  }

  private function verifyUserToken(string $token): ?array {
    $secret = (string)($this->config['jwt']['access_secret'] ?? '');
    if ($secret === '') {
      return null;
    }

    $payload = Jwt::verify($token, $secret);
    if (!$payload || !isset($payload['sub']) || !is_numeric($payload['sub'])) {
      return null;
    }

    if (isset($payload['jti']) && $this->tokens->isAccessTokenBlacklisted((string)$payload['jti'])) {
      return null;
    }

    return $payload;
  }

  private function verifyApiClientToken(string $token): ?array {
    $secret = (string)($this->config['api_auth']['jwt_secret'] ?? '');
    if ($secret === '') {
      return null;
    }

    $payload = Jwt::verify($token, $secret);
    if (!$payload) {
      return null;
    }

    $subject = (string)($payload['sub'] ?? '');
    if (($payload['type'] ?? '') !== 'access' || !str_starts_with($subject, 'client:')) {
      return null;
    }

    $jti = (string)($payload['jti'] ?? '');
    if ($jti !== '' && $this->apiRevocations->isRevoked($jti)) {
      return null;
    }

    return $payload;
  }

  private function parseScopes(string $scopeString): array {
    $scopes = preg_split('/\s+/', trim($scopeString)) ?: [];
    $normalized = [];

    foreach ($scopes as $scope) {
      $scope = trim($scope);
      if ($scope === '') {
        continue;
      }

      $normalized[$scope] = true;
    }

    return array_values(array_keys($normalized));
  }

  private function hasRequiredScopes(array $grantedScopes, array $requiredScopes): bool {
    if ($requiredScopes === []) {
      return true;
    }

    $granted = array_fill_keys($grantedScopes, true);
    foreach ($requiredScopes as $scope) {
      if (!isset($granted[$scope])) {
        return false;
      }
    }

    return true;
  }

  private function issueUserAccessFromRefreshCookie(Request $req): ?array {
    if (!$this->cookiesEnabled) {
      return null;
    }

    $refreshToken = (string)($req->getCookie($this->refreshCookie) ?? '');
    if ($refreshToken === '') {
      return null;
    }

    $refreshSecret = (string)($this->config['jwt']['refresh_secret'] ?? '');
    if ($refreshSecret === '') {
      return null;
    }

    $refreshPayload = Jwt::verify($refreshToken, $refreshSecret);
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

    $accessToken = Jwt::sign($accessPayload, (string)$this->config['jwt']['access_secret']);
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
