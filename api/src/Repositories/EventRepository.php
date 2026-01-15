<?php
namespace App\Repositories;

use App\Core\Database;

final class EventRepository {
  private \PDO $pdo;

  public function __construct(Database $db) {
    $this->pdo = $db->pdo();
  }

  public function create(array $data): string {
    $correlationId = $data['correlation_id'] ?? uniqid('evt_', true);
    
    $sql = "INSERT INTO event_outbox (correlation_id, event_type, aggregate_type, aggregate_id, payload_json, status, created_at)
            VALUES (:correlation_id, :event_type, :aggregate_type, :aggregate_id, :payload_json, 'pending', NOW())";
    
    $st = $this->pdo->prepare($sql);
    $st->execute([
      ':correlation_id' => $correlationId,
      ':event_type' => $data['event_type'],
      ':aggregate_type' => $data['aggregate_type'],
      ':aggregate_id' => $data['aggregate_id'],
      ':payload_json' => json_encode($data['payload'] ?? []),
    ]);

    return $correlationId;
  }

  public function updateStatus(string $correlationId, string $status, ?string $lastError = null): void {
      $sql = "UPDATE event_outbox SET status = :status, last_error = :last_error, updated_at = NOW() WHERE correlation_id = :correlation_id";
      $st = $this->pdo->prepare($sql);
      $st->execute([
          ':status' => $status,
          ':last_error' => $lastError,
          ':correlation_id' => $correlationId
      ]);
  }
}
