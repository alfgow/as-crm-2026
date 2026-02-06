<?php
namespace App\Controllers;

use App\Core\Jwt;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\ApiLogRepository;
use App\Repositories\TokenRepository;
use App\Repositories\UserRepository;
use App\Core\Logger;

final class AuthController {
  private array $config;
  private UserRepository $users;
  private TokenRepository $tokens;
  private ApiLogRepository $apiLogs;
  private Logger $logger;
  private array $cookieConfig;

  public function __construct(array $config, UserRepository $users, TokenRepository $tokens, ApiLogRepository $apiLogs, Logger $logger) {
    $this->config = $config;
    $this->users = $users;
    $this->tokens = $tokens;
    $this->apiLogs = $apiLogs;
    $this->logger = $logger;
    $this->cookieConfig = $config['auth_cookies'] ?? [];
  }

  public function login(Request $req, Response $res, array $params): void {
    $body = $req->getJson() ?? [];
    $email = trim((string)($body['email'] ?? ''));
    $password = (string)($body['password'] ?? '');

    if ($email === '' || $password === '') {
      $res->json([
        'data' => null,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [[ 'code' => 'bad_request', 'message' => 'email and password are required' ]]
      ], 400);
    }

    $user = $this->users->findByEmail($email);
    if (!$user || empty($user['password'])) {
      $this->apiLogs->log($req, 401, 'auth.login');
      $res->json([
        'data' => null,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [[ 'code' => 'invalid_credentials', 'message' => 'Invalid credentials' ]]
      ], 401);
    }

    // IMPORTANTE: aquí asumimos password_hash() moderno.
    // Si hoy tu tabla guarda hashes legacy, ajustamos el verificador en la siguiente iteración.
    if (!password_verify($password, (string)$user['password'])) {
      $this->apiLogs->log($req, 401, 'auth.login');
      $res->json([
        'data' => null,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [[ 'code' => 'invalid_credentials', 'message' => 'Invalid credentials' ]]
      ], 401);
    }

    $now = time();
    $accessPayload = [
      'iss' => $this->config['env'],
      'sub' => (int)$user['id'],
      'role' => $user['rol'] ?? 'admin',
      'iat' => $now,
      'exp' => $now + $this->config['jwt']['access_ttl'],
      'jti' => bin2hex(random_bytes(16)), // Add Unique Identifier
    ];

    $refreshPayload = [
      'iss' => $this->config['env'],
      'sub' => (int)$user['id'],
      'iat' => $now,
      'exp' => $now + $this->config['jwt']['refresh_ttl'],
      'jti' => bin2hex(random_bytes(16)),
    ];

    $accessToken = Jwt::sign($accessPayload, $this->config['jwt']['access_secret']);
    $refreshToken = Jwt::sign($refreshPayload, $this->config['jwt']['refresh_secret']);

    // Persist refresh token (hash) para revocación/rotación
    $this->tokens->storeRefreshToken([
      'user_id' => (int)$user['id'],
      'jti' => $refreshPayload['jti'],
      'token' => $refreshToken,
      'expires_at' => date('Y-m-d H:i:s', $refreshPayload['exp']),
    ]);

    $this->apiLogs->log($req, 200, 'auth.login');

    unset($user['password']);
    $this->setAuthCookies($accessToken, $refreshToken, $accessPayload['exp'], $refreshPayload['exp']);

    $data = [
      'user' => $user
    ];
    if ($this->shouldExposeTokens()) {
      $data['accessToken'] = $accessToken;
      $data['refreshToken'] = $refreshToken;
    }

    $res->json([
      'data' => $data,
      'meta' => ['requestId' => $req->getRequestId()],
      'errors' => [],
    ]);
  }

  public function refresh(Request $req, Response $res, array $params): void {
    $body = $req->getJson() ?? [];
    $refreshToken = (string)($body['refreshToken'] ?? '');
    if ($refreshToken === '') {
      $refreshToken = (string)($req->getCookie($this->refreshCookieName()) ?? '');
    }

    if ($refreshToken === '') {
      $res->json([
        'data' => null,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [[ 'code' => 'bad_request', 'message' => 'refreshToken is required' ]]
      ], 400);
    }

    $payload = Jwt::verify($refreshToken, $this->config['jwt']['refresh_secret']);
    if (!$payload || empty($payload['sub']) || empty($payload['jti'])) {
      $this->apiLogs->log($req, 401, 'auth.refresh');
      $res->json([
        'data' => null,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [[ 'code' => 'unauthorized', 'message' => 'Invalid refresh token' ]]
      ], 401);
    }

    // Check token exists & not revoked
    if (!$this->tokens->isRefreshTokenActive((int)$payload['sub'], (string)$payload['jti'])) {
      $this->apiLogs->log($req, 401, 'auth.refresh');
      $res->json([
        'data' => null,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [[ 'code' => 'unauthorized', 'message' => 'Refresh token revoked' ]]
      ], 401);
    }

    $now = time();
    $accessPayload = [
      'iss' => $this->config['env'],
      'sub' => (int)$payload['sub'],
      'role' => $payload['role'] ?? 'admin',
      'iat' => $now,
      'exp' => $now + $this->config['jwt']['access_ttl'],
      'jti' => bin2hex(random_bytes(16)), // New JTI for new access token
    ];

    $accessToken = Jwt::sign($accessPayload, $this->config['jwt']['access_secret']);

    $this->apiLogs->log($req, 200, 'auth.refresh');

    $this->setAuthCookies($accessToken, $refreshToken, $accessPayload['exp'], (int)($payload['exp'] ?? 0));

    $res->json([
      'data' => $this->shouldExposeTokens() ? ['accessToken' => $accessToken] : [],
      'meta' => ['requestId' => $req->getRequestId()],
      'errors' => [],
    ]);
  }

  public function logout(Request $req, Response $res, array $params): void {
    // 1. Revoke Access Token (Bearer) if present
    $authHeader = $req->getHeader('Authorization');
    $bearerToken = '';
    if ($authHeader && preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
      $bearerToken = $matches[1];
    }
    if ($bearerToken === '') {
      $bearerToken = (string)($req->getCookie($this->accessCookieName()) ?? '');
    }

    if ($bearerToken !== '') {
      $payload = Jwt::verify($bearerToken, $this->config['jwt']['access_secret']);
      if ($payload && isset($payload['jti'], $payload['sub'], $payload['exp'])) {
        $this->tokens->blacklistAccessToken((string)$payload['jti'], (int)$payload['sub'], (int)$payload['exp']);
      }
    }

    // 2. Revoke Refresh Token if passed
    $body = $req->getJson() ?? [];
    $refreshToken = (string)($body['refreshToken'] ?? '');
    if ($refreshToken === '') {
      $refreshToken = (string)($req->getCookie($this->refreshCookieName()) ?? '');
    }

    if ($refreshToken !== '') {
        $payload = Jwt::verify($refreshToken, $this->config['jwt']['refresh_secret']);
        if ($payload && !empty($payload['sub']) && !empty($payload['jti'])) {
             $this->tokens->revokeRefreshToken((int)$payload['sub'], (string)$payload['jti']);
        }
    }

    $this->apiLogs->log($req, 200, 'auth.logout');
    $this->clearAuthCookies();
    $res->json([
      'data' => ['ok' => true, 'message' => 'Logged out successfully (Access Token blacklisted, Refresh Token revoked)'],
      'meta' => ['requestId' => $req->getRequestId()],
      'errors' => [],
    ]);
  }

  private function shouldExposeTokens(): bool {
    if (!$this->cookiesEnabled()) {
      return true;
    }
    return (bool)($this->cookieConfig['expose_tokens'] ?? false);
  }

  private function cookiesEnabled(): bool {
    return (bool)($this->cookieConfig['enabled'] ?? false);
  }

  private function accessCookieName(): string {
    return (string)($this->cookieConfig['access_cookie'] ?? 'as_access_token');
  }

  private function refreshCookieName(): string {
    return (string)($this->cookieConfig['refresh_cookie'] ?? 'as_refresh_token');
  }

  private function setAuthCookies(string $accessToken, string $refreshToken, int $accessExp, int $refreshExp): void {
    if (!$this->cookiesEnabled()) {
      return;
    }

    $this->setCookie($this->accessCookieName(), $accessToken, $accessExp);
    $this->setCookie($this->refreshCookieName(), $refreshToken, $refreshExp);
  }

  private function clearAuthCookies(): void {
    if (!$this->cookiesEnabled()) {
      return;
    }

    $expired = time() - 3600;
    $this->setCookie($this->accessCookieName(), '', $expired);
    $this->setCookie($this->refreshCookieName(), '', $expired);
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
