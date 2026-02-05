<?php
namespace App\Controllers;

use App\Core\Jwt;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\ApiClientRepository;
use App\Repositories\ApiRefreshTokenRepository;
use App\Repositories\ApiTokenRevocationRepository;

final class AuthApiController {
  private array $config;
  private ApiClientRepository $clients;
  private ApiRefreshTokenRepository $refreshTokens;
  private ApiTokenRevocationRepository $revocations;

  public function __construct(
    array $config,
    ApiClientRepository $clients,
    ApiRefreshTokenRepository $refreshTokens,
    ApiTokenRevocationRepository $revocations
  ) {
    $this->config = $config;
    $this->clients = $clients;
    $this->refreshTokens = $refreshTokens;
    $this->revocations = $revocations;
  }

  public function login(Request $req, Response $res): void {
    $body = $req->getJson();
    if (!is_array($body)) {
      $res->json([
        'data' => null,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [['code' => 'bad_request', 'message' => 'invalid_request']],
      ], 400);
      return;
    }

    $clientId = trim((string)($body['client_id'] ?? ''));
    $clientSecret = (string)($body['client_secret'] ?? '');
    $audience = trim((string)($body['audience'] ?? ''));
    $scopes = isset($body['scopes']) && is_array($body['scopes']) ? $body['scopes'] : [];

    if ($clientId === '' || $clientSecret === '' || $audience === '') {
      $res->json([
        'data' => null,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [['code' => 'bad_request', 'message' => 'invalid_request']],
      ], 400);
      return;
    }

    $expectedAudience = (string)($this->config['api_auth']['expected_audience'] ?? '');
    if ($expectedAudience !== '' && $audience !== $expectedAudience) {
      $res->json([
        'data' => null,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [['code' => 'bad_request', 'message' => 'invalid_audience']],
      ], 400);
      return;
    }

    $client = $this->clients->findByClientId($clientId);
    if (!$client || ($client['status'] ?? '') !== 'active') {
      $res->json([
        'data' => null,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [['code' => 'unauthorized', 'message' => 'invalid_client']],
      ], 401);
      return;
    }

    if (empty($client['secret_hash']) || !password_verify($clientSecret, (string)$client['secret_hash'])) {
      $res->json([
        'data' => null,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [['code' => 'unauthorized', 'message' => 'invalid_client']],
      ], 401);
      return;
    }

    $allowedScopes = is_array($client['allowed_scopes']) ? $client['allowed_scopes'] : [];
    $effectiveScopes = $this->resolveScopes($scopes, $allowedScopes);
    if ($effectiveScopes === []) {
      $res->json([
        'data' => null,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [['code' => 'bad_request', 'message' => 'invalid_scope']],
      ], 400);
      return;
    }

    $payload = $this->issueTokenPair($client, $effectiveScopes, $audience);
    $this->clients->touchLastUsed((int)$client['id']);

    $res->json([
      'data' => $payload,
      'meta' => ['requestId' => $req->getRequestId()],
      'errors' => [],
    ]);
  }

  public function refresh(Request $req, Response $res): void {
    $accessToken = $req->bearerToken();
    if ($accessToken === null) {
      $res->json([
        'data' => null,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [['code' => 'unauthorized', 'message' => 'missing_access_token']],
      ], 401);
      return;
    }

    $body = $req->getJson();
    if (!is_array($body)) {
      $res->json([
        'data' => null,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [['code' => 'bad_request', 'message' => 'invalid_request']],
      ], 400);
      return;
    }

    $refreshToken = trim((string)($body['refresh_token'] ?? ''));
    if ($refreshToken === '') {
      $res->json([
        'data' => null,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [['code' => 'bad_request', 'message' => 'invalid_request']],
      ], 400);
      return;
    }

    $secret = (string)($this->config['api_auth']['jwt_secret'] ?? ($this->config['jwt']['access_secret'] ?? ''));
    $claims = Jwt::verify($refreshToken, $secret);
    if (!$claims || ($claims['type'] ?? '') !== 'refresh') {
      $res->json([
        'data' => null,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [['code' => 'unauthorized', 'message' => 'invalid_token']],
      ], 401);
      return;
    }

    $refreshJti = (string)($claims['jti'] ?? '');
    if ($refreshJti === '') {
      $res->json([
        'data' => null,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [['code' => 'unauthorized', 'message' => 'invalid_token']],
      ], 401);
      return;
    }

    if ($this->revocations->isRevoked($refreshJti)) {
      $res->json([
        'data' => null,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [['code' => 'unauthorized', 'message' => 'token_revoked']],
      ], 401);
      return;
    }

    $stored = $this->refreshTokens->findActiveByJti($refreshJti);
    if (!$stored) {
      $res->json([
        'data' => null,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [['code' => 'unauthorized', 'message' => 'invalid_token']],
      ], 401);
      return;
    }

    $hash = $this->hashToken($refreshToken);
    if (!hash_equals((string)$stored['refresh_token_hash'], $hash)) {
      $res->json([
        'data' => null,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [['code' => 'unauthorized', 'message' => 'invalid_token']],
      ], 401);
      return;
    }

    $clientId = (int)($stored['client_id'] ?? 0);
    $client = $clientId > 0 ? $this->clients->findById($clientId) : null;
    if (!$client || ($client['status'] ?? '') !== 'active') {
      $res->json([
        'data' => null,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [['code' => 'unauthorized', 'message' => 'invalid_client']],
      ], 401);
      return;
    }

    $storedScopes = $this->refreshTokens->extractScopes($stored);
    $effective = $this->resolveScopes($storedScopes, $client['allowed_scopes'] ?? []);
    if ($effective === []) {
      $res->json([
        'data' => null,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [['code' => 'bad_request', 'message' => 'invalid_scope']],
      ], 400);
      return;
    }

    $this->refreshTokens->markConsumed((int)$stored['id']);

    $audience = (string)($claims['aud'] ?? ($this->config['api_auth']['expected_audience'] ?? ''));
    $payload = $this->issueTokenPair($client, $effective, $audience);
    $this->clients->touchLastUsed((int)$client['id']);

    $res->json([
      'data' => $payload,
      'meta' => ['requestId' => $req->getRequestId()],
      'errors' => [],
    ]);
  }

  private function resolveScopes(array $requested, array $allowed): array {
    $allowed = $this->filterScopes($allowed);
    if ($allowed === []) {
      return [];
    }

    if ($requested === []) {
      return $allowed;
    }

    $requested = $this->filterScopes($requested);
    $scopes = array_values(array_intersect($requested, $allowed));

    return $scopes === [] ? $allowed : $scopes;
  }

  private function filterScopes(array $scopes): array {
    $normalized = [];

    foreach ($scopes as $scope) {
      if (!is_string($scope)) {
        continue;
      }

      $scope = trim($scope);
      if ($scope === '') {
        continue;
      }

      $normalized[$scope] = true;
    }

    return array_values(array_keys($normalized));
  }

  private function issueTokenPair(array $client, array $scopes, string $audience): array {
    $accessTtl = (int)($this->config['api_auth']['access_ttl'] ?? 3600);
    $refreshTtl = (int)($this->config['api_auth']['refresh_ttl'] ?? 2592000);
    $clientRefreshTtl = isset($client['refresh_ttl_seconds']) ? (int)$client['refresh_ttl_seconds'] : 0;
    if ($clientRefreshTtl > 0) {
      $refreshTtl = $clientRefreshTtl;
    }
    $secret = (string)($this->config['api_auth']['jwt_secret'] ?? ($this->config['jwt']['access_secret'] ?? ''));

    $scopeString = implode(' ', $scopes);
    $accessJti = $this->uuid();
    $refreshJti = $this->uuid();
    $subject = 'client:' . (string)($client['client_id'] ?? '');
    $now = time();

    $accessClaims = [
      'sub' => $subject,
      'cid' => (int)($client['id'] ?? 0),
      'scope' => $scopeString,
      'aud' => $audience,
      'jti' => $accessJti,
      'type' => 'access',
      'exp' => $now + $accessTtl,
    ];

    $accessToken = Jwt::sign($accessClaims, $secret);

    $refreshClaims = [
      'sub' => $subject,
      'cid' => (int)($client['id'] ?? 0),
      'scope' => $scopeString,
      'aud' => $audience,
      'jti' => $refreshJti,
      'type' => 'refresh',
      'exp' => $now + $refreshTtl,
    ];

    $refreshToken = Jwt::sign($refreshClaims, $secret);
    $refreshExpiry = (new \DateTimeImmutable())->setTimestamp($now + $refreshTtl)->format('Y-m-d H:i:s');

    $this->refreshTokens->create(
      (int)($client['id'] ?? 0),
      $refreshJti,
      $this->hashToken($refreshToken),
      $accessJti,
      $scopes,
      $refreshExpiry
    );

    return [
      'token_type' => 'Bearer',
      'access_token' => $accessToken,
      'refresh_token' => $refreshToken,
      'expires_in' => $accessTtl,
      'refresh_expires_in' => $refreshTtl,
      'scope' => $scopeString,
      'jti' => $accessJti,
      'client_id' => (string)($client['client_id'] ?? ''),
    ];
  }

  private function hashToken(string $token): string {
    return hash('sha256', $token);
  }

  private function uuid(): string {
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
  }
}
