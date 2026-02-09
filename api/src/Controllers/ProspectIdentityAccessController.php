<?php
namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\ProspectAccessRepository;
use App\Services\SesEmailService;

final class ProspectIdentityAccessController {
  private array $config;
  private ProspectAccessRepository $prospects;
  private SesEmailService $mailer;

  public function __construct(array $config, ProspectAccessRepository $prospects, SesEmailService $mailer) {
    $this->config = $config;
    $this->prospects = $prospects;
    $this->mailer = $mailer;
  }

  public function issue(Request $req, Response $res): void {
    $body = $req->getJson();
    $email = strtolower(trim((string)($body['email'] ?? '')));
    $actor = isset($body['actor']) ? strtolower(trim((string)$body['actor'])) : null;
    $ttlMinutes = (int)($body['ttl_minutes'] ?? ($this->config['identity_validation']['token_ttl_minutes'] ?? 60));
    $ttlMinutes = max(5, $ttlMinutes);
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

    $tokenRaw = $this->generateToken();
    $tokenHash = hash('sha256', $tokenRaw);
    $jti = $this->uuidv4();
    $now = time();
    $exp = $now + $ttlSeconds;
    $expiresAt = (new \DateTimeImmutable("@{$exp}"))->format('Y-m-d H:i:s');
    $scope = 'identity:validation';

    $this->prospects->insertIdentityToken([
      'actor_type' => $actorType,
      'actor_id' => $actorId,
      'email' => $email,
      'jti' => $jti,
      'token_hash' => $tokenHash,
      'scope' => $scope,
      'expires_at' => $expiresAt,
    ]);

    $base = rtrim((string)($this->config['identity_validation']['public_base'] ?? ''), '/');
    if ($base === '') {
      $base = 'https://mail.arrendamientoseguro.app/validacion-identidad';
    }
    $link = $base . '/' . $tokenRaw;

    $res->json([
      'data' => [
        'email' => $email,
        'identity_link' => $link,
        'expires_at' => $expiresAt,
        'actor_type' => $actorType,
        'actor_id' => $actorId,
        'actor_name' => $actorName,
      ],
      'meta' => ['requestId' => $req->getRequestId()],
      'errors' => [],
    ]);
  }

  public function sendEmail(Request $req, Response $res): void {
    $body = $req->getJson();
    $email = trim((string)($body['email'] ?? ''));
    $link = trim((string)($body['identity_link'] ?? ''));
    $exp = trim((string)($body['expires_at'] ?? ''));
    $nombre = trim((string)($body['actor_name'] ?? 'Usuario'));

    if ($email === '' || $link === '') {
      $res->json([
        'data' => null,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [['code' => 'bad_request', 'message' => 'Datos incompletos']],
      ], 400);
      return;
    }

    $subject = 'Validación de identidad - Arrendamiento Seguro';
    $textBody = "Hola {$nombre},\n\nPara continuar con tu validación de identidad, abre el siguiente enlace:\n{$link}\n\nEste enlace expira en: {$exp}\n\nSi no solicitaste esta validación, ignora este mensaje.";
    $htmlBody = $this->buildHtmlEmail($nombre, $link, $exp);

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

  public function validate(Request $req, Response $res): void {
    $body = $req->getJson();
    $tokenRaw = trim((string)($body['token'] ?? ''));

    if ($tokenRaw === '') {
      $res->json([
        'data' => null,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [['code' => 'bad_request', 'message' => 'Token requerido']],
      ], 400);
      return;
    }

    $tokenHash = hash('sha256', $tokenRaw);
    $row = $this->prospects->findIdentityTokenByHash($tokenHash);
    if (!$row) {
      $res->json([
        'data' => null,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [['code' => 'unauthorized', 'message' => 'Token inválido o expirado']],
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
      ],
      'meta' => ['requestId' => $req->getRequestId()],
      'errors' => [],
    ]);
  }

  private function generateToken(): string {
    return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
  }

  private function uuidv4(): string {
    $d = random_bytes(16);
    $d[6] = chr((ord($d[6]) & 0x0f) | 0x40);
    $d[8] = chr((ord($d[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($d), 4));
  }

  private function buildHtmlEmail(string $name, string $link, string $expiresAt): string {
    $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
    $safeLink = htmlspecialchars($link, ENT_QUOTES, 'UTF-8');
    $safeExpires = htmlspecialchars($expiresAt, ENT_QUOTES, 'UTF-8');
    $year = date('Y');

    return <<<HTML
<!DOCTYPE html>
<html lang="es">
  <head>
    <meta charset="UTF-8" />
    <title>Validación de identidad</title>
    <style>
      body {
        margin: 0;
        padding: 20px;
        background: #fde8e8ca;
        font-family: 'Segoe UI', Arial, sans-serif;
        color: #4b1d1d;
      }
      .container {
        max-width: 520px;
        margin: auto;
        background: #fff;
        border-radius: 16px;
        box-shadow: 0 8px 24px rgba(0,0,0,0.12);
        overflow: hidden;
      }
      .header {
        text-align: center;
        padding: 24px 20px 10px;
      }
      .header img.logo {
        width: 140px;
        border-radius: 50%;
        margin-bottom: 10px;
      }
      .content {
        padding: 20px 30px 30px;
        text-align: center;
      }
      .content h2 {
        margin: 10px 0;
        font-size: 20px;
        color: #4b1d1d;
      }
      .content p {
        margin: 8px 0;
        color: #4b1d1d;
      }
      .cta {
        display: inline-block;
        background: #de6868;
        color: #fff;
        text-decoration: none;
        padding: 12px 22px;
        border-radius: 12px;
        font-weight: 600;
        margin: 16px 0;
      }
      .footer {
        text-align: center;
        padding: 16px;
        font-size: 12px;
        color: #777;
        background: #fdf2f2;
      }
    </style>
  </head>
  <body>
    <div class="container">
      <div class="header">
        <img src="https://alfgow.s3.mx-central-1.amazonaws.com/Logo+Circular.png" alt="Arrendamiento Seguro" class="logo" />
      </div>
      <div class="content">
        <h2>Hola {$safeName},</h2>
        <p>Para continuar con tu validación de identidad, da clic en el botón:</p>
        <a href="{$safeLink}" class="cta">Validar identidad</a>
        <p>Este enlace expira en: {$safeExpires}</p>
        <p>Si no solicitaste esta validación, ignora este mensaje.</p>
      </div>
      <div class="footer">
        © {$year} Arrendamiento Seguro · Todos los derechos reservados.
      </div>
    </div>
  </body>
</html>
HTML;
  }
}
