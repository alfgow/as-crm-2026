<?php
namespace App\Services;

use App\Repositories\OutboxRepository;

final class OutboxService {
  private OutboxRepository $repo;

  public function __construct(OutboxRepository $repo) {
    $this->repo = $repo;
  }

  public function emit(string $eventType, string $aggregateType, string $aggregateId, array $payload): array {
    $correlationId = 'evt_' . bin2hex(random_bytes(16));

    $id = $this->repo->insertEvent([
      'correlation_id' => $correlationId,
      'event_type' => $eventType,
      'aggregate_type' => $aggregateType,
      'aggregate_id' => $aggregateId,
      'payload' => $payload,
    ]);

    return ['id' => $id, 'correlationId' => $correlationId];
  }

  public static function nextAttemptAt(int $attempts): \DateTimeImmutable {
    // attempts ya incrementado (1..n)
    // Backoff escalonado: 1m, 5m, 15m, 1h, 6h, 6h...
    $minutes =
      ($attempts <= 1) ? 1 :
      (($attempts === 2) ? 5 :
      (($attempts === 3) ? 15 :
      (($attempts === 4) ? 60 : 360)));

    return (new \DateTimeImmutable('now'))->modify("+{$minutes} minutes");
  }
}
