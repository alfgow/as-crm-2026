<?php
namespace App\Controllers;

use App\Core\HttpClient;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\InquilinoRepository;
use App\Repositories\ValidacionAwsRepository;

final class VerificamexController {
  private InquilinoRepository $inquilinos;
  private ValidacionAwsRepository $validaciones;

  public function __construct(InquilinoRepository $inquilinos, ValidacionAwsRepository $validaciones) {
    $this->inquilinos = $inquilinos;
    $this->validaciones = $validaciones;
  }

  public function validar(Request $req, Response $res, array $params): void {
    $id = (int)($params['id'] ?? 0);
    if ($id <= 0) {
      $res->json([
        'data' => null,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [['code' => 'bad_request', 'message' => 'id inválido']],
      ], 400);
      return;
    }

    $inquilino = $this->inquilinos->findById($id);
    if (!$inquilino) {
      $res->json([
        'data' => null,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [['code' => 'not_found', 'message' => 'Inquilino no encontrado']],
      ], 404);
      return;
    }

    $body = $req->getJson();
    if ($body === null) {
      $res->json([
        'data' => null,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [['code' => 'bad_request', 'message' => 'JSON inválido o faltante']],
      ], 400);
      return;
    }

    $requiredFields = ['ine_front', 'ine_back', 'selfie', 'model'];
    $missing = [];
    foreach ($requiredFields as $field) {
      $value = $body[$field] ?? null;
      if (!is_string($value) || trim($value) === '') {
        $missing[] = $field;
      }
    }

    if ($missing !== []) {
      $res->json([
        'data' => [
          'missing_fields' => $missing,
        ],
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [['code' => 'bad_request', 'message' => 'Campos obligatorios faltantes']],
      ], 400);
      return;
    }

    $token = getenv('API_VERIFICAMEX') ?: '';
    if ($token === '') {
      $res->json([
        'data' => null,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [['code' => 'server_error', 'message' => 'API_VERIFICAMEX no configurado']],
      ], 500);
      return;
    }

    $payload = [
      'ine_front' => $this->resolveImagePayload((string)$body['ine_front'], $req, $res, 'ine_front'),
      'ine_back' => $this->resolveImagePayload((string)$body['ine_back'], $req, $res, 'ine_back'),
      'selfie' => $this->resolveImagePayload((string)$body['selfie'], $req, $res, 'selfie'),
      'model' => $body['model'],
    ];
    $headers = [
      'Authorization: Bearer ' . $token,
      'Accept: application/json',
    ];

    $result = HttpClient::postJson(
      'https://api.verificamex.com/identity/v1/validations/basic',
      $payload,
      $headers,
      20
    );

    $decoded = json_decode($result['body'], true);
    $responsePayload = is_array($decoded)
      ? $decoded
      : ['raw' => $result['body']];

    $statusFlag = (bool)($responsePayload['data']['status'] ?? false);
    $proceso = $statusFlag ? 1 : 3;
    $resumen = (string)($responsePayload['message'] ?? '');
    if ($resumen === '') {
      $resumen = $statusFlag ? 'Validación exitosa' : 'Validación rechazada';
    }

    $this->validaciones->guardarValidacionCheck($id, 'verificamex', $proceso, $responsePayload, $resumen);

    if ($result['status'] === 0 || $result['status'] >= 500) {
      $errors = $this->extractVerificamexErrors($responsePayload);
      if ($errors === []) {
        $errors = [['code' => 'verificamex_error', 'message' => 'Error al comunicar con Verificamex']];
      }
      $res->json([
        'data' => [
          'ok' => false,
          'status' => $result['status'],
          'verificamex' => $responsePayload,
        ],
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => $errors,
      ], 502);
      return;
    }

    $errors = $this->extractVerificamexErrors($responsePayload);

    $res->json([
      'data' => [
        'ok' => $statusFlag,
        'status' => $result['status'],
        'proceso_validacion_verificamex' => $proceso,
        'verificamex_resumen' => $resumen,
        'verificamex' => $responsePayload,
      ],
      'meta' => ['requestId' => $req->getRequestId()],
      'errors' => $errors,
    ]);
  }

  private function resolveImagePayload(string $value, Request $req, Response $res, string $field): string {
    $trimmed = trim($value);
    if ($trimmed === '') {
      return '';
    }

    if (!$this->looksLikeUrl($trimmed)) {
      return $trimmed;
    }

    $download = HttpClient::request('GET', $trimmed, null, ['Accept: */*'], 20);
    if ($download['status'] < 200 || $download['status'] >= 300) {
      $res->json([
        'data' => [
          'field' => $field,
          'status' => $download['status'],
          'verificamex' => null,
        ],
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [[
          'code' => 'verificamex_image_download_failed',
          'message' => 'No se pudo descargar la imagen para validación',
        ]],
      ], 502);
      return '';
    }

    if ($download['body'] === '') {
      $res->json([
        'data' => [
          'field' => $field,
          'verificamex' => null,
        ],
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [[
          'code' => 'verificamex_image_empty',
          'message' => 'La imagen descargada está vacía',
        ]],
      ], 502);
      return '';
    }

    return base64_encode($download['body']);
  }

  private function looksLikeUrl(string $value): bool {
    return (bool)filter_var($value, FILTER_VALIDATE_URL);
  }

  /**
   * @return array<int, array{code:string, message:string}>
   */
  private function extractVerificamexErrors(array $payload): array {
    $errors = [];

    if (isset($payload['errors']) && is_array($payload['errors'])) {
      foreach ($payload['errors'] as $error) {
        if (!is_array($error)) {
          continue;
        }
        $code = isset($error['code']) ? (string)$error['code'] : 'verificamex_error';
        $message = isset($error['message']) ? (string)$error['message'] : '';
        if ($message !== '') {
          $errors[] = ['code' => $code, 'message' => $message];
        }
      }
    }

    if ($errors === [] && isset($payload['error']) && is_string($payload['error'])) {
      $errors[] = ['code' => 'verificamex_error', 'message' => $payload['error']];
    }

    if ($errors === [] && isset($payload['message']) && is_string($payload['message'])) {
      $errors[] = ['code' => 'verificamex_error', 'message' => $payload['message']];
    }

    return $errors;
  }
}
