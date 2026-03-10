<?php
namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\InquilinoRepository;
use App\Repositories\ValidacionAwsRepository;
use App\Services\MediaCopyService;
use App\Services\RekognitionService;

final class LivenessController {
  private InquilinoRepository $inquilinos;
  private ValidacionAwsRepository $validaciones;
  private MediaCopyService $mediaCopy;
  private RekognitionService $rekognition;
  private array $config;

  public function __construct(
    InquilinoRepository $inquilinos,
    ValidacionAwsRepository $validaciones,
    MediaCopyService $mediaCopy,
    RekognitionService $rekognition,
    array $config
  ) {
    $this->inquilinos = $inquilinos;
    $this->validaciones = $validaciones;
    $this->mediaCopy = $mediaCopy;
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
    $livenessPassed = $status === 'SUCCEEDED';
    $confidence = $body['Confidence'] ?? null;
    $auditImages = $body['AuditImages'] ?? null;
    $referenceImage = $this->normalizeReferenceImage($body['ReferenceImage'] ?? null);
    $faceMatch = $livenessPassed ? $this->compareReferenceImageVsSelfie($id, $referenceImage) : null;
    $livenessDecision = $this->buildLivenessDecision($status, $livenessPassed, $faceMatch);

    $payload = [
      'session_id' => $sessionId,
      'status' => $status,
      'confidence' => $confidence,
      'audit_images' => $auditImages,
      'reference_image' => $referenceImage,
      'face_match' => $faceMatch,
      'decision' => $livenessDecision,
      'raw' => $body,
      'ts' => date(DATE_ATOM),
    ];

    $this->validaciones->guardarValidacionLiveness($id, $payload);

    $res->json([
      'data' => [
        'ok' => true,
        'request_ok' => true,
        'liveness_passed' => $livenessPassed,
        'liveness_status' => $status,
        'session_id' => $sessionId,
        'status' => $status,
        'confidence' => $confidence,
        'audit_images' => $auditImages,
        'reference_image' => $referenceImage,
        'face_match' => $faceMatch,
        'liveness_decision' => $livenessDecision,
        'liveness_message' => $livenessDecision['message'],
      ],
      'meta' => ['requestId' => $req->getRequestId()],
      'errors' => [],
    ]);
  }

  /**
   * @return array<string,mixed>|null
   */
  private function normalizeReferenceImage(mixed $referenceImage): ?array {
    if (!is_array($referenceImage)) {
      return null;
    }

    $normalized = [];

    if (isset($referenceImage['Bytes']) && is_string($referenceImage['Bytes']) && $referenceImage['Bytes'] !== '') {
      $normalized['bytes_base64'] = $referenceImage['Bytes'];
    }

    if (isset($referenceImage['S3Object']) && is_array($referenceImage['S3Object'])) {
      $normalized['s3_object'] = $referenceImage['S3Object'];
    }

    if (isset($referenceImage['BoundingBox']) && is_array($referenceImage['BoundingBox'])) {
      $normalized['bounding_box'] = $referenceImage['BoundingBox'];
    }

    if (array_key_exists('Confidence', $referenceImage)) {
      $normalized['confidence'] = $referenceImage['Confidence'];
    }

    return $normalized === [] ? null : $normalized;
  }

  /**
   * @param array<string,mixed>|null $referenceImage
   * @return array<string,mixed>|null
   */
  private function compareReferenceImageVsSelfie(int $id, ?array $referenceImage): ?array {
    if ($referenceImage === null) {
      return [
        'attempted' => false,
        'request_ok' => false,
        'matched' => false,
        'reason' => 'reference_image_missing',
        'reason_message' => 'AWS no devolvió ReferenceImage para esta sesión.',
      ];
    }

    $selfies = $this->inquilinos->findArchivosByTipos($id, ['selfie']);
    $selfieKey = trim((string)($selfies[0] ?? ''));
    if ($selfieKey === '') {
      return [
        'attempted' => false,
        'request_ok' => false,
        'matched' => false,
        'reason' => 'selfie_missing',
        'reason_message' => 'No existe selfie registrada del inquilino para comparar.',
      ];
    }

    $bucket = (string)($this->config['media']['s3']['buckets']['inquilinos'] ?? '');
    if ($bucket === '') {
      return [
        'attempted' => false,
        'request_ok' => false,
        'matched' => false,
        'reason' => 'selfie_bucket_missing',
        'reason_message' => 'No está configurado el bucket de selfies en backend.',
      ];
    }

    $sourceImage = $this->buildSourceImageForCompare($referenceImage);
    if ($sourceImage === null) {
      return [
        'attempted' => false,
        'request_ok' => false,
        'matched' => false,
        'reason' => 'reference_image_invalid',
        'reason_message' => 'ReferenceImage no tiene formato usable (Bytes o S3Object).',
      ];
    }

    $threshold = (float)($this->config['aws']['rekognition']['similarity_threshold'] ?? 85);
    $threshold = max(0, min(100, $threshold));

    $targetImage = [
      'S3Object' => [
        'Bucket' => $bucket,
        'Name' => $selfieKey,
      ],
    ];

    $compare = $this->rekognition->compareFaces($sourceImage, $targetImage, $threshold);
    $usedFallbackBucket = false;
    $fallbackDetails = null;

    if ($this->isInvalidS3ObjectError($compare)) {
      $fallback = $this->retryCompareWithCopyBucket($id, $selfieKey, $sourceImage, $threshold, $bucket);
      if (($fallback['attempted'] ?? false) === true) {
        $usedFallbackBucket = true;
        $fallbackDetails = [
          'copy_ok' => (bool)($fallback['copy_ok'] ?? false),
          'copy_status' => (int)($fallback['copy_status'] ?? 0),
          'copy_error' => $fallback['copy_error'] ?? null,
        ];
      }

      if (($fallback['compare'] ?? null) !== null) {
        $compare = (array)$fallback['compare'];
      }
    }

    $responseBody = is_array($compare['body'] ?? null) ? $compare['body'] : [];
    $bestSimilarity = $this->getBestSimilarity($responseBody);

    $requestOk = (bool)($compare['ok'] ?? false);
    $matched = $requestOk && $bestSimilarity >= $threshold;
    $reason = $requestOk ? ($matched ? 'match' : 'not_match') : 'compare_faces_error';
    $reasonMessage = $requestOk
      ? ($matched ? 'La selfie coincide con la imagen de referencia.' : 'La selfie no coincide con la imagen de referencia.')
      : 'No fue posible completar CompareFaces en Rekognition.';

    if (!$requestOk && $usedFallbackBucket && is_array($fallbackDetails) && !($fallbackDetails['copy_ok'] ?? false)) {
      $reason = 'selfie_access_error';
      $reasonMessage = 'No fue posible descargar la selfie del bucket configurado; revisa key/permisos/región.';
    }

    return [
      'attempted' => true,
      'request_ok' => $requestOk,
      'matched' => $matched,
      'reason' => $reason,
      'reason_message' => $reasonMessage,
      'similarity_threshold' => $threshold,
      'best_similarity' => $bestSimilarity,
      'selfie_key' => $selfieKey,
      'used_fallback_bucket' => $usedFallbackBucket,
      'fallback_details' => $fallbackDetails,
      'rekognition' => $compare,
    ];
  }

  private function isInvalidS3ObjectError(array $compare): bool {
    if (($compare['ok'] ?? false) === true) {
      return false;
    }

    $body = $compare['body'] ?? null;
    if (!is_array($body)) {
      return false;
    }

    $code = strtoupper((string)($body['Code'] ?? $body['__type'] ?? ''));
    return str_contains($code, 'INVALIDS3OBJECTEXCEPTION');
  }

  /**
   * @param array<string,mixed> $sourceImage
   * @return array{attempted:bool,copy_ok:bool,copy_status:int,copy_error:?string,compare:?array{ok:bool,status:int,body:mixed,raw:string}}
   */
  private function retryCompareWithCopyBucket(
    int $id,
    string $selfieKey,
    array $sourceImage,
    float $threshold,
    string $sourceBucket
  ): array {
    $copyBucket = trim((string)($this->config['aws']['rekognition']['copy_bucket'] ?? ''));
    if ($copyBucket === '') {
      return [
        'attempted' => false,
        'copy_ok' => false,
        'copy_status' => 0,
        'copy_error' => 'copy_bucket_missing',
        'compare' => null,
      ];
    }

    $tempKey = 'tmp/liveness/' . $id . '/' . bin2hex(random_bytes(8)) . '_selfie';
    $copied = false;

    try {
      $copy = $this->mediaCopy->copyObject($sourceBucket, $selfieKey, $copyBucket, $tempKey);
      if (!$copy['ok']) {
        return [
          'attempted' => true,
          'copy_ok' => false,
          'copy_status' => (int)($copy['status'] ?? 0),
          'copy_error' => $copy['error'] ?? 'copy_failed',
          'compare' => null,
        ];
      }

      $copied = true;
      $targetImage = [
        'S3Object' => [
          'Bucket' => $copyBucket,
          'Name' => $tempKey,
        ],
      ];

      return [
        'attempted' => true,
        'copy_ok' => true,
        'copy_status' => (int)($copy['status'] ?? 0),
        'copy_error' => null,
        'compare' => $this->rekognition->compareFaces($sourceImage, $targetImage, $threshold),
      ];
    } finally {
      if ($copied) {
        $this->mediaCopy->deleteObject($copyBucket, $tempKey);
      }
    }
  }

  /**
   * @param array<string,mixed> $referenceImage
   * @return array<string,mixed>|null
   */
  private function buildSourceImageForCompare(array $referenceImage): ?array {
    $bytesBase64 = trim((string)($referenceImage['bytes_base64'] ?? ''));
    if ($bytesBase64 !== '') {
      return ['Bytes' => $bytesBase64];
    }

    $s3Object = $referenceImage['s3_object'] ?? null;
    if (is_array($s3Object)) {
      return ['S3Object' => $s3Object];
    }

    return null;
  }

  /**
   * @param array<string,mixed>|null $faceMatch
   * @return array<string,mixed>
   */
  private function buildLivenessDecision(string $status, bool $livenessPassed, ?array $faceMatch): array {
    if (!$livenessPassed) {
      return [
        'approved' => false,
        'code' => $status === 'EXPIRED' ? 'liveness_expired' : 'liveness_failed',
        'message' => $status === 'EXPIRED'
          ? 'La validación de vida expiró; solicita una nueva sesión.'
          : 'La validación de vida no fue exitosa.',
      ];
    }

    if (!is_array($faceMatch) || !($faceMatch['attempted'] ?? false)) {
      $reason = (string)($faceMatch['reason'] ?? 'compare_not_attempted');
      $reasonMessage = (string)($faceMatch['reason_message'] ?? 'No se pudo ejecutar la comparación facial automática.');

      return [
        'approved' => true,
        'code' => 'liveness_passed_compare_pending',
        'message' => 'Liveness aprobado; comparación facial pendiente.',
        'face_match_reason' => $reason,
        'face_match_reason_message' => $reasonMessage,
      ];
    }

    if (($faceMatch['request_ok'] ?? false) && ($faceMatch['matched'] ?? false)) {
      $similarityText = number_format((float)($faceMatch['best_similarity'] ?? 0), 2);
      return [
        'approved' => true,
        'code' => 'liveness_passed_face_match',
        'message' => "Liveness aprobado y selfie coincidente ({$similarityText}%).",
      ];
    }

    if ($faceMatch['request_ok'] ?? false) {
      $similarityText = number_format((float)($faceMatch['best_similarity'] ?? 0), 2);
      return [
        'approved' => false,
        'code' => 'liveness_passed_face_mismatch',
        'message' => "Liveness aprobado pero selfie no coincide ({$similarityText}%).",
      ];
    }

    $reasonMessage = (string)($faceMatch['reason_message'] ?? 'No se pudo ejecutar CompareFaces.');
    return [
      'approved' => true,
      'code' => 'liveness_passed_compare_error',
      'message' => 'Liveness aprobado; comparación facial con error técnico.',
      'face_match_reason' => (string)($faceMatch['reason'] ?? 'compare_faces_error'),
      'face_match_reason_message' => $reasonMessage,
    ];
  }

  private function getBestSimilarity(array $body): float {
    $matches = $body['FaceMatches'] ?? [];
    if (!is_array($matches)) {
      return 0.0;
    }

    $best = 0.0;
    foreach ($matches as $match) {
      if (!is_array($match)) {
        continue;
      }
      $similarity = (float)($match['Similarity'] ?? 0);
      if ($similarity > $best) {
        $best = $similarity;
      }
    }

    return $best;
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
