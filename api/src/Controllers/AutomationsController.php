<?php
namespace App\Controllers;

use App\Core\Hmac;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\AutomationRunsRepository;

final class AutomationsController {
  private array $config;
  private AutomationRunsRepository $runs;

  public function __construct(array $config, AutomationRunsRepository $runs) {
    $this->config = $config;
    $this->runs = $runs;
  }

  public function callback(Request $req, Response $res, array $params): void {
    $correlationId = (string)($params['correlationId'] ?? '');
    if ($correlationId === '') {
      $res->json([
        'data' => null,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [[ 'code' => 'bad_request', 'message' => 'Missing correlationId' ]]
      ], 400);
    }

    // Validación firma HMAC (n8n -> API)
    $sig = $req->getHeaders()['x-signature'] ?? '';
    // Reading raw input again might be tricky if not buffered, assuming PHP default request handling
    $raw = file_get_contents('php://input') ?: '';
    $secret = getenv('N8N_HMAC_SECRET') ?: ($this->config['n8n']['hmac_secret'] ?? '');

    if ($secret && !$sig) {
      $res->json([
        'data' => null,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [[ 'code' => 'unauthorized', 'message' => 'Missing X-Signature' ]]
      ], 401);
    }
    if ($secret && !Hmac::verify($raw, $secret, (string)$sig)) {
      $res->json([
        'data' => null,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [[ 'code' => 'unauthorized', 'message' => 'Invalid signature' ]]
      ], 401);
    }

    $body = $req->getJson() ?? [];
    $status = (string)($body['status'] ?? '');
    $eventType = (string)($body['eventType'] ?? '');
    $workflow = $body['n8n_workflow'] ?? null;
    $executionId = $body['n8n_execution_id'] ?? null;
    $result = $body['result'] ?? null;
    $error = (string)($body['error'] ?? '');

    // Upsert base (por si llega callback antes de que hayamos creado el run)
    $this->runs->upsertReceived($correlationId, $eventType ?: 'unknown', is_string($workflow) ? $workflow : null, is_string($executionId) ? $executionId : null);

    if ($status === 'succeeded') {
      $this->runs->markSucceeded($correlationId, is_array($result) ? $result : ['ok' => true]);
    } elseif ($status === 'failed') {
      $this->runs->markFailed($correlationId, $error ?: 'n8n_failed', is_array($result) ? $result : null);
    }

    $res->json([
      'data' => ['ok' => true, 'correlationId' => $correlationId],
      'meta' => ['requestId' => $req->getRequestId()],
      'errors' => [],
    ]);
  }
}
