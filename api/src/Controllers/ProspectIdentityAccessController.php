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

    return <<<HTML
<!DOCTYPE html>
<html lang="es">
  <head>
    <meta charset="UTF-8" />
    <title>Validación de identidad</title>
  </head>
  <body style="font-family: Arial, sans-serif; background: #f6f7fb; padding: 24px;">
    <table width="100%" cellpadding="0" cellspacing="0" style="max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 8px; padding: 24px;">
      <tr>
        <td>
          <h2 style="color: #1f2937;">Hola {$safeName},</h2>
          <p style="color: #374151; font-size: 16px;">
            Para continuar con tu validación de identidad, da clic en el botón:
          </p>
          <p style="text-align: center; margin: 32px 0;">
            <a href="{$safeLink}" style="background: #2563eb; color: #ffffff; text-decoration: none; padding: 12px 24px; border-radius: 6px; display: inline-block;">
              Validar identidad
            </a>
          </p>
          <p style="color: #6b7280; font-size: 14px;">Este enlace expira en: {$safeExpires}</p>
          <p style="color: #9ca3af; font-size: 12px;">Si no solicitaste esta validación, ignora este mensaje.</p>
        </td>
      </tr>
    </table>
  </body>
</html>
HTML;
  }
}
