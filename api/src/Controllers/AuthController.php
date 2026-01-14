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

  public function __construct(array $config, UserRepository $users, TokenRepository $tokens, ApiLogRepository $apiLogs, Logger $logger) {
    $this->config = $config;
    $this->users = $users;
    $this->tokens = $tokens;
    $this->apiLogs = $apiLogs;
    $this->logger = $logger;
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
    $res->json([
      'data' => [
        'accessToken' => $accessToken,
        'refreshToken' => $refreshToken,
        'user' => $user
      ],
      'meta' => ['requestId' => $req->getRequestId()],
      'errors' => [],
    ]);
  }

  public function refresh(Request $req, Response $res, array $params): void {
    $body = $req->getJson() ?? [];
    $refreshToken = (string)($body['refreshToken'] ?? '');

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
    ];

    $accessToken = Jwt::sign($accessPayload, $this->config['jwt']['access_secret']);

    $this->apiLogs->log($req, 200, 'auth.refresh');

    $res->json([
      'data' => [
        'accessToken' => $accessToken
      ],
      'meta' => ['requestId' => $req->getRequestId()],
      'errors' => [],
    ]);
  }

  public function logout(Request $req, Response $res, array $params): void {
    $body = $req->getJson() ?? [];
    $refreshToken = (string)($body['refreshToken'] ?? '');

    if ($refreshToken === '') {
      $res->json([
        'data' => null,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [[ 'code' => 'bad_request', 'message' => 'refreshToken is required' ]]
      ], 400);
    }

    $payload = Jwt::verify($refreshToken, $this->config['jwt']['refresh_secret']);
    if ($payload && !empty($payload['sub']) && !empty($payload['jti'])) {
      $this->tokens->revokeRefreshToken((int)$payload['sub'], (string)$payload['jti']);
    }

    $this->apiLogs->log($req, 200, 'auth.logout');
    $res->json([
      'data' => ['ok' => true],
      'meta' => ['requestId' => $req->getRequestId()],
      'errors' => [],
    ]);
  }
}
