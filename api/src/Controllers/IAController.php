<?php
namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\IARepository;

final class IAController {
  private IARepository $ia;

  public function __construct(IARepository $ia) {
    $this->ia = $ia;
  }

  public function index(Request $req, Response $res): void {
    $res->json([
      'data' => [
        'service' => 'ia',
        'status' => 'ready',
      ],
      'meta' => ['requestId' => $req->getRequestId()],
      'errors' => [],
    ]);
  }

  public function chat(Request $req, Response $res): void {
    $body = $req->getJson() ?? [];
    $prompt = trim((string)($body['prompt'] ?? ''));
    $modelKey = strtolower(trim((string)($body['model'] ?? 'direct')));

    if ($prompt === '') {
      $res->json([
        'data' => null,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [['code' => 'bad_request', 'message' => 'El campo "prompt" es requerido.']],
      ], 400);
      return;
    }

    $output = $this->respuestaDirecta($prompt);
    if ($output === null) {
      $output = 'Por ahora no hay un modelo IA configurado para esta consulta.';
    }

    $this->ia->registrarInteraccion([
      'usuario_id' => null,
      'modelo_key' => $modelKey,
      'modelo_id' => $modelKey,
      'prompt' => $prompt,
      'respuesta' => $output,
      'duration_ms' => 0,
      'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
      'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
    ]);

    $res->json([
      'data' => [
        'ok' => true,
        'model' => $modelKey,
        'output' => $output,
        'durationMs' => 0,
      ],
      'meta' => ['requestId' => $req->getRequestId()],
      'errors' => [],
    ]);
  }

  private function respuestaDirecta(string $prompt): ?string {
    $pNorm = $this->normalize($prompt);

    if (preg_match('/inquilino(s)?/u', $pNorm)) {
      $term = $this->extraerTerminoSimple($prompt);
      if ($term === null) {
        return '¿Me pasas nombre, correo o teléfono para buscar al inquilino?';
      }

      $rows = $this->ia->buscarInquilinosPorTexto($term, 1);
      if (!$rows) {
        return "No encontré inquilinos que coincidan con “{$term}”.";
      }

      $r = $rows[0];
      $idInquilino = (int)$r['id'];
      $celular = (string)($r['celular'] ?? '');
      $contacto = $celular !== '' ? " y celular {$celular}" : '';
      $info = "Sí, tenemos registrado a {$r['nombre']} con correo {$r['email']}{$contacto}.";

      $polizas = $this->ia->obtenerPolizasActivasPorInquilino($idInquilino);
      if ($polizas) {
        foreach ($polizas as $p) {
          $info .= " Encontré que tiene relacionada la póliza número {$p['numero_poliza']} vigente hasta {$p['vigencia']}.";
          $info .= " Correspondiente al inmueble: {$p['direccion_inmueble']}.";
          $info .= " Arrendador: {$p['arrendador']}.";
          $info .= " Monto de renta: {$p['renta']}.";
          $info .= " Costo de la póliza: {$p['monto_poliza']}.";
        }
      } else {
        $info .= " Actualmente no tiene pólizas vigentes registradas.";
      }

      return $info;
    }

    return null;
  }

  private function extraerTerminoSimple(string $prompt): ?string {
    if (filter_var($prompt, FILTER_VALIDATE_EMAIL)) {
      return $prompt;
    }

    if (preg_match('/([A-ZÁÉÍÓÚÑ][a-záéíóúñ]+(?:\s+[A-ZÁÉÍÓÚÑ][a-záéíóúñ]+){1,3})/u', $prompt, $m)) {
      return $m[1];
    }

    $digits = preg_replace('/\D+/', '', $prompt);
    if ($digits !== '' && strlen($digits) >= 4) {
      return $digits;
    }

    $tokens = preg_split('/\s+/', trim($prompt));
    $candidate = $tokens ? trim((string)$tokens[0]) : '';
    return $candidate !== '' ? $candidate : null;
  }

  private function normalize(string $value): string {
    $value = mb_strtolower($value, 'UTF-8');
    return strtr($value, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);
  }
}
