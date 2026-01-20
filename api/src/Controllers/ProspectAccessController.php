<?php
namespace App\Controllers;

use App\Core\Jwt;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\ProspectAccessRepository;

final class ProspectAccessController {
  private array $config;
  private ProspectAccessRepository $prospects;

  public function __construct(array $config, ProspectAccessRepository $prospects) {
    $this->config = $config;
    $this->prospects = $prospects;
  }

  public function code(Request $req, Response $res): void {
    $prefillEmail = trim((string)($req->getQueryParams()['email'] ?? ''));

    $res->json([
      'data' => [
        'title' => 'Emitir acceso - AS',
        'headerTitle' => 'Acceso para edición',
        'prefill_email' => $prefillEmail,
      ],
      'meta' => ['requestId' => $req->getRequestId()],
      'errors' => [],
    ]);
  }

  public function issue(Request $req, Response $res): void {
    $body = $req->getJson();
    $email = strtolower(trim((string)($body['email'] ?? '')));
    $actor = isset($body['actor']) ? strtolower(trim((string)$body['actor'])) : null;
    $ttlMinutes = max(5, (int)($body['ttl_minutes'] ?? 1440));
    $ttlSeconds = $ttlMinutes * 60;

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
      $res->json([
        'data' => null,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [['code' => 'bad_request', 'message' => 'Email inválido']],
      ], 400);
      return;
    }

    $resolved = $this->prospects->resolveActorByEmail($email, $actor);
    if (!$resolved) {
      $res->json([
        'data' => null,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [['code' => 'not_found', 'message' => 'No encontramos inquilino/arrendador con ese email']],
      ], 404);
      return;
    }

    [$actorType, $actorId, $actorName] = $resolved;

    $otp = (string)random_int(100000, 999999);
    $otpHash = password_hash($otp, PASSWORD_BCRYPT);

    $jti = $this->uuidv4();
    $now = time();
    $exp = $now + $ttlSeconds;
    $scope = 'self:update';

    $payload = [
      'iss' => 'as-backend',
      'aud' => 'as-frontend',
      'iat' => $now,
      'nbf' => $now,
      'exp' => $exp,
      'jti' => $jti,
      'sub' => (string)$actorId,
      'type' => 'prospect_update',
      'scope' => $scope,
      'actor_type' => $actorType,
    ];

    $secret = $this->config['prospect_access']['jwt_secret'] ?? ($this->config['jwt']['access_secret'] ?? '');
    $tokenRaw = Jwt::sign($payload, $secret);
    $tokenHash = hash('sha256', $tokenRaw);
    $expiresAt = (new \DateTimeImmutable("@{$exp}"))->format('Y-m-d H:i:s');

    $this->prospects->insertToken([
      'actor_type' => $actorType,
      'actor_id' => $actorId,
      'email' => $email,
      'jti' => $jti,
      'otp' => $otp,
      'otp_hash' => $otpHash,
      'token_hash' => $tokenHash,
      'scope' => $scope,
      'expires_at' => $expiresAt,
    ]);

    $magicLink = rtrim($this->frontendPublicBase(), '/') . '/auth/code?j=' . $jti;

    $res->json([
      'data' => [
        'email' => $email,
        'otp' => $otp,
        'magic_link' => $magicLink,
        'expires_at' => $expiresAt,
        'actor_type' => $actorType,
        'actor_id' => $actorId,
        'actor_name' => $actorName,
      ],
      'meta' => ['requestId' => $req->getRequestId()],
      'errors' => [],
    ]);
  }

  public function sendEmails(Request $req, Response $res): void {
    $body = $req->getJson();
    $email = trim((string)($body['email'] ?? ''));
    $otp = trim((string)($body['otp'] ?? ''));
    $link = trim((string)($body['magic_link'] ?? ''));
    $exp = trim((string)($body['expires_at'] ?? ''));
    $nombre = trim((string)($body['actor_name'] ?? 'Usuario'));

    if ($email === '' || $otp === '' || $link === '') {
      $res->json([
        'data' => null,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [['code' => 'bad_request', 'message' => 'Datos incompletos']],
      ], 400);
      return;
    }

    $res->json([
      'data' => [
        'sent' => false,
        'message' => 'Email dispatch no configurado',
        'email' => $email,
        'actor_name' => $nombre,
        'expires_at' => $exp,
      ],
      'meta' => ['requestId' => $req->getRequestId()],
      'errors' => [],
    ]);
  }

  private function uuidv4(): string {
    $d = random_bytes(16);
    $d[6] = chr((ord($d[6]) & 0x0f) | 0x40);
    $d[8] = chr((ord($d[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($d), 4));
  }

  private function frontendPublicBase(): string {
    $base = (string)($this->config['prospect_access']['frontend_public_base'] ?? '');
    if ($base !== '') {
      return $base;
    }

    return 'https://arrendamientoseguro.app';
  }
}
