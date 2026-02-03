<?php
namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\InquilinoRepository;
use App\Repositories\ValidacionAwsRepository;
use App\Services\RekognitionService;

final class InquilinoValidacionAwsController {
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

  public function validarIngresosPDFSimple(Request $req, Response $res, array $params): void {
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

    $archivos = $this->inquilinos->findArchivosByTipos($id, ['comprobante_ingreso']);
    $pdfs = [];
    foreach ($archivos as $key) {
      $key = (string)$key;
      $ext = strtolower(pathinfo($key, PATHINFO_EXTENSION));
      if ($ext === 'pdf') {
        $pdfs[] = [
          's3_key' => $key,
          'ext' => $ext,
        ];
      }
    }

    $n = count($pdfs);
    $status = ($n >= 3) ? 'OK' : (($n >= 1) ? 'REVIEW' : 'FAIL');
    $payload = [
      'tipo' => 'ingresos_pdf_simple',
      'conteo' => $n,
      'archivos' => $pdfs,
      'reglas' => [
        'min_recomendado' => 3,
        'criterio' => 'OK si hay >= 3 PDFs; REVIEW si 1-2; FAIL si 0.',
      ],
      'status' => $status,
      'ts' => date('c'),
    ];

    $proceso = ($status === 'OK') ? 1 : (($status === 'FAIL') ? 0 : 2);
    $resumen = $status === 'OK'
      ? '☑️ Ingresos completos'
      : ($status === 'FAIL' ? '✖️ Sin comprobantes de ingreso' : '⚠️ Ingresos incompletos');

    $this->validaciones->guardarValidacionIngresosSimple($id, $proceso, $payload, $resumen);

    $res->json([
      'data' => [
        'ok' => true,
        'proceso' => $proceso,
        'resumen' => $resumen,
        'payload' => $payload,
      ],
      'meta' => ['requestId' => $req->getRequestId()],
      'errors' => [],
    ]);
  }

  public function obtenerArchivos(Request $req, Response $res): void {
    $query = $req->getQuery();
    $slug = trim((string)($query['slug'] ?? ''));
    $inquilino = $slug !== '' ? $this->inquilinos->findBySlug($slug) : null;
    if (!$inquilino || empty($inquilino['id'])) {
      $res->json([
        'data' => null,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [['code' => 'bad_request', 'message' => 'slug inválido']],
      ], 400);
      return;
    }

    $archivos = $this->inquilinos->findArchivosByInquilinoId((int)$inquilino['id']);
    $res->json([
      'data' => $archivos,
      'meta' => ['requestId' => $req->getRequestId()],
      'errors' => [],
    ]);
  }

  public function obtenerArchivosPorSlug(Request $req, Response $res, array $params): void {
    $slug = trim((string)($params['slug'] ?? ''));
    if ($slug === '') {
      $res->json([
        'data' => null,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [['code' => 'bad_request', 'message' => 'slug inválido']],
      ], 400);
      return;
    }

    $inquilino = $this->inquilinos->findBySlug($slug);
    if (!$inquilino || empty($inquilino['id'])) {
      $res->json([
        'data' => null,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [['code' => 'not_found', 'message' => 'Inquilino no encontrado']],
      ], 404);
      return;
    }

    $archivos = $this->inquilinos->findArchivosByInquilinoId((int)$inquilino['id']);
    $res->json([
      'data' => $archivos,
      'meta' => ['requestId' => $req->getRequestId()],
      'errors' => [],
    ]);
  }

  public function validarCheck(Request $req, Response $res, array $params): void {
    $slug = trim((string)($params['slug'] ?? ''));
    if ($slug === '') {
      $res->json([
        'data' => null,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [['code' => 'bad_request', 'message' => 'slug inválido']],
      ], 400);
      return;
    }

    $inquilino = $this->inquilinos->findBySlug($slug);
    if (!$inquilino || empty($inquilino['id'])) {
      $res->json([
        'data' => null,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [['code' => 'not_found', 'message' => 'Inquilino no encontrado']],
      ], 404);
      return;
    }

    $query = $req->getQuery();
    $body = $req->getJson() ?? [];
    $check = trim((string)($query['check'] ?? $body['check'] ?? ''));

    $allowed = [
      'archivos',
      'faces',
      'liveness',
      'ocr',
      'parse',
      'nombres',
      'kv',
      'match',
      'save_match',
      'save_face',
      'status',
      'ingresos_list',
      'ingresos_ocr',
      'resumen_full',
      'verificamex',
    ];

    if ($check === '' || !in_array($check, $allowed, true)) {
      $res->json([
        'data' => [
          'checks' => $allowed,
          'mensaje' => 'check inválido o faltante',
        ],
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [['code' => 'bad_request', 'message' => 'check inválido']],
      ], 400);
      return;
    }

    $idInquilino = (int)$inquilino['id'];
    $archivos = $inquilino['archivos'] ?? [];
    if ($archivos === []) {
      $archivos = $this->inquilinos->findArchivosByInquilinoId($idInquilino);
    }

    $flags = $this->buildArchivoFlags($archivos);
    $payload = [
      'check' => $check,
      'archivos' => $flags,
      'timestamp' => date(DATE_ATOM),
    ];

    $resumen = sprintf('Validación %s ejecutada', $check);
    $this->validaciones->guardarValidacionCheck($idInquilino, $check, 2, $payload, $resumen);

    $res->json([
      'data' => [
        'ok' => true,
        'check' => $check,
        'resumen' => $resumen,
        'payload' => $payload,
      ],
      'meta' => ['requestId' => $req->getRequestId()],
      'errors' => [],
    ]);
  }

  public function compararRostros(Request $req, Response $res, array $params): void {
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

    $body = $req->getJson() ?? [];
    $selfieKey = trim((string)($body['selfie_key'] ?? ''));
    $ineKey = trim((string)($body['ine_frontal_key'] ?? ''));
    $pasaporteKey = trim((string)($body['pasaporte_key'] ?? ''));
    $formaKey = trim((string)($body['forma_frontal_key'] ?? ''));
    $targetKey = $ineKey !== '' ? $ineKey : ($pasaporteKey !== '' ? $pasaporteKey : $formaKey);

    if ($selfieKey === '' || $targetKey === '') {
      $res->json([
        'data' => null,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [[
          'code' => 'bad_request',
          'message' => 'Falta selfie_key o ine_frontal_key/pasaporte_key/forma_frontal_key',
        ]],
      ], 400);
      return;
    }

    $defaultBucket = (string)($this->config['media']['s3']['buckets']['inquilinos'] ?? '');
    $bucket = trim((string)($body['bucket'] ?? $defaultBucket));
    if ($bucket === '') {
      $res->json([
        'data' => null,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [['code' => 'bad_request', 'message' => 'bucket inválido']],
      ], 400);
      return;
    }

    $thresholdConfig = (float)($this->config['aws']['rekognition']['similarity_threshold'] ?? 85);
    $threshold = (float)($body['similarity_threshold'] ?? $thresholdConfig);
    $threshold = max(0, min(100, $threshold));

    $response = $this->rekognition->compareFacesS3($bucket, $selfieKey, $bucket, $targetKey, $threshold);
    $responseBody = is_array($response['body'] ?? null) ? $response['body'] : [];
    $bestSimilarity = $this->getBestSimilarity($responseBody);
    $matched = $response['ok'] && $bestSimilarity >= $threshold;

    if (!$response['ok']) {
      $resumen = '❌ Error en Rekognition';
      if (!empty($responseBody['message'])) {
        $resumen .= ': ' . $responseBody['message'];
      } elseif (!empty($response['error'])) {
        $resumen .= ': ' . $response['error'];
      }
      $proceso = 0;
    } else {
      $similarityText = number_format($bestSimilarity, 2) . '%';
      $tipoDocumento = $ineKey !== '' ? 'INE' : ($pasaporteKey !== '' ? 'pasaporte' : 'forma migratoria');
      $resumen = $matched
        ? "✅ Rostro coincide con {$tipoDocumento} ({$similarityText})"
        : "❌ Rostro no coincide con {$tipoDocumento} ({$similarityText})";
      $proceso = $matched ? 1 : 0;
    }

    $payload = [
      'check' => 'faces',
      'bucket' => $bucket,
      'selfie_key' => $selfieKey,
      'target_key' => $targetKey,
      'target_tipo' => $ineKey !== '' ? 'ine_frontal' : ($pasaporteKey !== '' ? 'pasaporte' : 'forma_frontal'),
      'similarity_threshold' => $threshold,
      'best_similarity' => $bestSimilarity,
      'rekognition' => $response,
      'timestamp' => date(DATE_ATOM),
    ];

    $this->validaciones->guardarValidacionCheck($id, 'faces', $proceso, $payload, $resumen);

    $res->json([
      'data' => [
        'ok' => $response['ok'],
        'proceso' => $proceso,
        'resumen' => $resumen,
        'payload' => $payload,
      ],
      'meta' => ['requestId' => $req->getRequestId()],
      'errors' => [],
    ], $response['ok'] ? 200 : 502);
  }

  private function getBestSimilarity(array $body): float {
    $matches = $body['FaceMatches'] ?? [];
    $best = 0.0;
    foreach ($matches as $match) {
      $similarity = (float)($match['Similarity'] ?? 0);
      if ($similarity > $best) {
        $best = $similarity;
      }
    }
    return $best;
  }

  private function buildArchivoFlags(array $archivos): array {
    $flags = [
      'selfie' => false,
      'ine_frontal' => false,
      'ine_reverso' => false,
      'pasaporte' => false,
      'forma_migratoria' => false,
      'comprobantes' => 0,
    ];

    foreach ($archivos as $archivo) {
      $tipo = strtolower((string)($archivo['tipo'] ?? ''));
      switch ($tipo) {
        case 'selfie':
          $flags['selfie'] = true;
          break;
        case 'ine_frontal':
          $flags['ine_frontal'] = true;
          break;
        case 'ine_reverso':
          $flags['ine_reverso'] = true;
          break;
        case 'pasaporte':
          $flags['pasaporte'] = true;
          break;
        case 'forma_migratoria':
        case 'forma_migratoria_frontal':
        case 'forma_migratoria_reverso':
          $flags['forma_migratoria'] = true;
          break;
        case 'comprobante_ingreso':
          $flags['comprobantes']++;
          break;
      }
    }

    return $flags;
  }
}
