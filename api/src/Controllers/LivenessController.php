<?php
namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\InquilinoRepository;
use App\Repositories\ValidacionAwsRepository;
use App\Services\RekognitionService;

final class LivenessController {
  private InquilinoRepository $inquilinos;
  private ValidacionAwsRepository $validaciones;
  private RekognitionService $rekognition;
  private array $config;

  public function __construct(
    InquilinoRepository $inquilinos,
    ValidacionAwsRepository $validaciones,
    RekognitionService $rekognition,
    array $config
  ) {
    $this->inquilinos = $inquilinos;
    $this->validaciones = $validaciones;
    $this->rekognition = $rekognition;
    $this->config = $config;
  }

  public function startSession(Request $req, Response $res, array $params): void {
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

    $payload = $this->buildStartPayload($req);
    $result = $this->rekognition->startFaceLivenessSession($payload);
    if (!$result['ok']) {
      $res->json([
        'data' => [
          'ok' => false,
          'error' => $result['body'] ?? $result['error'] ?? 'Error en Rekognition',
        ],
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [['code' => 'rekognition_error', 'message' => 'No fue posible iniciar la sesión']],
      ], 502);
      return;
    }

    $body = is_array($result['body']) ? $result['body'] : [];
    $sessionId = (string)($body['SessionId'] ?? '');

    $res->json([
      'data' => [
        'ok' => true,
        'session_id' => $sessionId,
        'status' => $body['Status'] ?? null,
        'confidence' => $body['Confidence'] ?? null,
        'audit_images' => $body['AuditImages'] ?? null,
        'raw' => $body,
      ],
      'meta' => ['requestId' => $req->getRequestId()],
      'errors' => [],
    ]);
  }

  public function getResult(Request $req, Response $res, array $params): void {
    $id = (int)($params['id'] ?? 0);
    $sessionId = trim((string)($params['sessionId'] ?? ''));

    if ($id <= 0 || $sessionId === '') {
      $res->json([
        'data' => null,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [['code' => 'bad_request', 'message' => 'id o sessionId inválido']],
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

    $result = $this->rekognition->getFaceLivenessSessionResults($sessionId);
    if (!$result['ok']) {
      $res->json([
        'data' => [
          'ok' => false,
          'error' => $result['body'] ?? $result['error'] ?? 'Error en Rekognition',
        ],
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [['code' => 'rekognition_error', 'message' => 'No fue posible obtener resultados']],
      ], 502);
      return;
    }

    $body = is_array($result['body']) ? $result['body'] : [];
    $status = (string)($body['Status'] ?? '');
    $confidence = $body['Confidence'] ?? null;
    $auditImages = $body['AuditImages'] ?? null;

    $payload = [
      'session_id' => $sessionId,
      'status' => $status,
      'confidence' => $confidence,
      'audit_images' => $auditImages,
      'raw' => $body,
      'ts' => date(DATE_ATOM),
    ];

    $proceso = $status === 'SUCCEEDED' ? 1 : 2;
    $resumen = $status === 'SUCCEEDED'
      ? '☑️ Liveness aprobado'
      : '⚠️ Liveness en revisión';
    $this->validaciones->guardarValidacionLiveness($id, $proceso, $payload, $resumen);

    $res->json([
      'data' => [
        'ok' => true,
        'session_id' => $sessionId,
        'status' => $status,
        'confidence' => $confidence,
        'audit_images' => $auditImages,
      ],
      'meta' => ['requestId' => $req->getRequestId()],
      'errors' => [],
    ]);
  }

  private function buildStartPayload(Request $req): array {
    $body = $req->getJson() ?? [];
    $payload = [];

    if (isset($body['settings']) && is_array($body['settings'])) {
      $payload['Settings'] = $body['settings'];
    }

    if (isset($body['output_config']) && is_array($body['output_config'])) {
      $payload['OutputConfig'] = $body['output_config'];
    } elseif (isset($body['output']) && is_array($body['output'])) {
      $payload['OutputConfig'] = $body['output'];
    }

    $auditKey = (string)($body['audit_image_s3_key'] ?? '');
    $bucket = (string)($body['bucket'] ?? '');
    if ($auditKey !== '' && $bucket !== '') {
      $payload['OutputConfig'] = [
        'S3Bucket' => $bucket,
        'S3KeyPrefix' => $auditKey,
      ];
    }

    $defaultBucket = (string)($this->config['aws']['rekognition']['liveness_bucket'] ?? '');
    $defaultPrefix = (string)($this->config['aws']['rekognition']['liveness_prefix'] ?? '');
    if ($payload === [] && $defaultBucket !== '') {
      $payload['OutputConfig'] = [
        'S3Bucket' => $defaultBucket,
        'S3KeyPrefix' => $defaultPrefix,
      ];
    }

    return $payload;
  }
}
