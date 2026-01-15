<?php
namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\OutboxService;

final class EventsController {
  private OutboxService $outbox;

  public function __construct(OutboxService $outbox) {
    $this->outbox = $outbox;
  }

  public function emit(Request $req, Response $res, array $params): void {
    $body = $req->getJson() ?? [];

    $eventType = trim((string)($body['eventType'] ?? ''));
    $aggregateType = trim((string)($body['aggregateType'] ?? ''));
    $aggregateId = trim((string)($body['aggregateId'] ?? ''));
    $data = $body['data'] ?? [];

    if ($eventType === '' || $aggregateType === '' || $aggregateId === '' || !is_array($data)) {
      $res->json([
        'data' => null,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [[ 'code' => 'bad_request', 'message' => 'eventType, aggregateType, aggregateId, data are required' ]]
      ], 400);
    }

    $created = $this->outbox->emit($eventType, $aggregateType, $aggregateId, [
      'data' => $data,
      'actor' => $body['actor'] ?? null,
      'ts' => date('c'),
    ]);

    $res->json([
      'data' => $created,
      'meta' => ['requestId' => $req->getRequestId()],
      'errors' => [],
    ], 201);
  }
}
