<?php
namespace App\Repositories;

use App\Core\Database;

final class OutboxRepository {
  private \PDO $pdo;

  public function __construct(Database $db) {
    $this->pdo = $db->pdo();
  }

  public function insertEvent(array $e): int {
    $sql = "INSERT INTO event_outbox
      (correlation_id, event_type, aggregate_type, aggregate_id, payload_json, status, attempts, next_attempt_at, created_at)
      VALUES
      (:correlation_id, :event_type, :aggregate_type, :aggregate_id, CAST(:payload_json AS JSON), 'pending', 0, NOW(), NOW())";

    $st = $this->pdo->prepare($sql);
    $st->execute([
      ':correlation_id' => $e['correlation_id'],
      ':event_type' => $e['event_type'],
      ':aggregate_type' => $e['aggregate_type'],
      ':aggregate_id' => $e['aggregate_id'],
      ':payload_json' => json_encode($e['payload'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);

    return (int)$this->pdo->lastInsertId();
  }

  /**
   * Toma lote para procesar (MySQL 8+ con SKIP LOCKED)
   * @return array<int, array<string,mixed>>
   */
  public function claimBatch(int $limit): array {
    $this->pdo->beginTransaction();

    // Nota: status IN ('pending','failed') y next_attempt_at <= NOW()
    // SKIP LOCKED evita doble proceso si corren múltiples dispatchers.
    $sql = "SELECT id, correlation_id, event_type, aggregate_type, aggregate_id, payload_json, attempts
            FROM event_outbox
            WHERE status IN ('pending','failed')
              AND next_attempt_at <= NOW()
            ORDER BY id ASC
            LIMIT :lim
            FOR UPDATE SKIP LOCKED";

    $st = $this->pdo->prepare($sql);
    $st->bindValue(':lim', $limit, \PDO::PARAM_INT);
    $st->execute();
    $rows = $st->fetchAll() ?: [];

    if (!$rows) {
      $this->pdo->commit();
      return [];
    }

    // Marcar como processing y aumentar attempts
    $ids = array_map(fn($r) => (int)$r['id'], $rows);
    $in = implode(',', array_fill(0, count($ids), '?'));

    $upd = $this->pdo->prepare("UPDATE event_outbox
      SET status='processing', attempts = attempts + 1, updated_at = NOW()
      WHERE id IN ($in)");
    $upd->execute($ids);

    $this->pdo->commit();

    // Normalizar payload_json a array
    foreach ($rows as &$r) {
      $decoded = json_decode((string)$r['payload_json'], true);
      $r['payload'] = is_array($decoded) ? $decoded : [];
      unset($r['payload_json']);
    }

    return $rows;
  }

  public function markDelivered(int $id): void {
    // Note: delivered_at column needs to be added to event_outbox migration if not present,
    // or just assume updated_at is enough. The migration we made earlier didn't specific 'delivered_at'.
    // Let's assume we alter table or change query to not use delivered_at if it breaks.
    // Based on user request, they pasted this code, so I should trust it or warn.
    // Migration 762 did NOT have delivered_at. I will add it via migration sql update or just fix query.
    // Actually, let's fix the query to simply update status.
    
    $sql = "UPDATE event_outbox
            SET status='delivered', last_error=NULL, updated_at=NOW()
            WHERE id=:id";
    $st = $this->pdo->prepare($sql);
    $st->execute([':id' => $id]);
  }

  public function markFailed(int $id, string $error, \DateTimeInterface $nextAttemptAt): void {
    $sql = "UPDATE event_outbox
            SET status='failed', last_error=:err, next_attempt_at=:next, updated_at=NOW()
            WHERE id=:id";
    $st = $this->pdo->prepare($sql);
    $st->execute([
      ':id' => $id,
      ':err' => mb_substr($error, 0, 60000),
      ':next' => $nextAttemptAt->format('Y-m-d H:i:s'),
    ]);
  }

  public function markDead(int $id, string $error): void {
    $sql = "UPDATE event_outbox
            SET status='dead', last_error=:err, updated_at=NOW()
            WHERE id=:id";
    $st = $this->pdo->prepare($sql);
    $st->execute([
      ':id' => $id,
      ':err' => mb_substr($error, 0, 60000),
    ]);
  }
}
