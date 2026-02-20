<?php
namespace App\Controllers;

use App\Core\Jwt;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\ProspectAccessRepository;
use App\Services\SesEmailService;

final class ProspectAccessController {
  private array $config;
  private ProspectAccessRepository $prospects;
  private SesEmailService $mailer;

  public function __construct(array $config, ProspectAccessRepository $prospects, SesEmailService $mailer) {
    $this->config = $config;
    $this->prospects = $prospects;
    $this->mailer = $mailer;
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
    $magicToken = $this->generateToken();
    $magicTokenHash = hash('sha256', $magicToken);
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
      'magic_token_hash' => $magicTokenHash,
      'otp' => $otp,
      'otp_hash' => $otpHash,
      'token_hash' => $tokenHash,
      'scope' => $scope,
      'expires_at' => $expiresAt,
    ]);

    $magicLink = rtrim($this->frontendPublicBase(), '/') . '/auth/code?t=' . rawurlencode($magicToken);

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

    $subject = 'Acceso para edición - Arrendamiento Seguro';
    $textBody = "Hola {$nombre},\n\nTu código OTP es: {$otp}\n\nTambién puedes usar este enlace de acceso:\n{$link}\n\nEste acceso expira en: {$exp}\n\nSi no solicitaste este acceso, ignora este mensaje.";
    $htmlBody = $this->buildHtmlEmail($nombre, $otp, $link, $exp);

    $result = $this->mailer->sendEmail($email, $subject, $htmlBody, $textBody);

    $res->json([
      'data' => [
        'sent' => (bool)($result['ok'] ?? false),
        'message' => (string)($result['message'] ?? ''),
        'email' => $email,
        'actor_name' => $nombre,
        'expires_at' => $exp,
      ],
      'meta' => ['requestId' => $req->getRequestId()],
      'errors' => [],
    ], ($result['ok'] ?? false) ? 200 : 502);
  }

  public function consume(Request $req, Response $res): void {
    $body = $req->getJson() ?? [];
    $token = trim((string)($body['token'] ?? ''));

    if ($token === '') {
      $res->json([
        'data' => null,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [['code' => 'bad_request', 'message' => 'Token requerido']],
      ], 400);
      return;
    }

    $tokenHash = hash('sha256', $token);
    $row = $this->prospects->consumeMagicToken(
      $tokenHash,
      $this->clientIp($req),
      (string)($req->getHeader('user-agent') ?? '')
    );

    if (!$row) {
      $res->json([
        'data' => null,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [['code' => 'unauthorized', 'message' => 'Token inválido, expirado o ya consumido']],
      ], 401);
      return;
    }

    $res->json([
      'data' => [
        'valid' => true,
        'actor_type' => $row['actor_type'],
        'actor_id' => (int)$row['actor_id'],
        'email' => $row['email'],
        'scope' => $row['scope'],
        'expires_at' => $row['expires_at'],
        'consumed_at' => $row['consumed_at'],
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

  private function generateToken(): string {
    return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
  }

  private function clientIp(Request $req): ?string {
    $xff = trim((string)($req->getHeader('x-forwarded-for') ?? ''));
    if ($xff !== '') {
      $parts = array_map('trim', explode(',', $xff));
      return $parts[0] ?? null;
    }

    $remote = trim((string)($_SERVER['REMOTE_ADDR'] ?? ''));
    return $remote !== '' ? $remote : null;
  }

  private function frontendPublicBase(): string {
    $base = (string)($this->config['prospect_access']['frontend_public_base'] ?? '');
    if ($base !== '') {
      return $base;
    }

    return 'https://arrendamientoseguro.app';
  }

  private function buildHtmlEmail(string $name, string $otp, string $link, string $expiresAt): string {
    $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
    $safeOtp = htmlspecialchars($otp, ENT_QUOTES, 'UTF-8');
    $safeLink = htmlspecialchars($link, ENT_QUOTES, 'UTF-8');
    $safeExpires = htmlspecialchars($expiresAt, ENT_QUOTES, 'UTF-8');
    $year = date('Y');

    return <<<HTML
<!DOCTYPE html>
<html lang="es">
  <head>
    <meta charset="UTF-8" />
    <title>Acceso para edición</title>
    <style>
      body { margin: 0; padding: 20px; background: #fde8e8ca; font-family: 'Segoe UI', Arial, sans-serif; color: #4b1d1d; }
      .container { max-width: 520px; margin: auto; background: #fff; border-radius: 16px; box-shadow: 0 8px 24px rgba(0,0,0,0.12); overflow: hidden; }
      .content { padding: 24px 30px 30px; text-align: center; }
      .otp { display: inline-block; margin: 12px 0; padding: 10px 18px; border-radius: 12px; background: #fdf2f2; font-size: 28px; font-weight: 700; letter-spacing: 3px; color: #de6868; }
      .cta { display: inline-block; background: #de6868; color: #fff; text-decoration: none; padding: 12px 22px; border-radius: 12px; font-weight: 600; margin: 16px 0; }
      .footer { text-align: center; padding: 16px; font-size: 12px; color: #777; background: #fdf2f2; }
    </style>
  </head>
  <body>
    <div class="container">
      <div class="content">
        <h2>Acceso para edición</h2>
        <p>Hola {$safeName}, usa este código OTP para iniciar tu edición:</p>
        <div class="otp">{$safeOtp}</div>
        <p>También puedes entrar directamente desde este enlace:</p>
        <a class="cta" href="{$safeLink}" target="_blank" rel="noopener">Abrir acceso</a>
        <p><strong>Expira:</strong> {$safeExpires}</p>
        <p>Si no solicitaste este acceso, ignora este mensaje.</p>
      </div>
      <div class="footer">© {$year} Arrendamiento Seguro</div>
    </div>
  </body>
</html>
HTML;
  }
}
