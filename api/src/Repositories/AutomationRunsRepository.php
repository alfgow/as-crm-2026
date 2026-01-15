<?php
namespace App\Repositories;

use App\Core\Database;

final class AutomationRunsRepository {
  private \PDO $pdo;

  public function __construct(Database $db) {
    $this->pdo = $db->pdo();
  }

  public function upsertReceived(string $correlationId, string $eventType, ?string $workflow, ?string $executionId): void {
    $sql = "INSERT INTO automation_runs
              (correlation_id, event_type, n8n_workflow, n8n_execution_id, status, started_at, created_at)
            VALUES
              (:cid, :etype, :wf, :eid, 'received', NOW(), NOW())
            ON DUPLICATE KEY UPDATE
              n8n_workflow = VALUES(n8n_workflow),
              n8n_execution_id = VALUES(n8n_execution_id),
              updated_at = NOW()";
    $st = $this->pdo->prepare($sql);
    $st->execute([
      ':cid' => $correlationId,
      ':etype' => $eventType,
      ':wf' => $workflow,
      ':eid' => $executionId,
    ]);
  }

  public function markSucceeded(string $correlationId, array $result): void {
    $sql = "UPDATE automation_runs
            SET status='succeeded', finished_at=NOW(), result_json=CAST(:r AS JSON), updated_at=NOW()
            WHERE correlation_id=:cid";
    $st = $this->pdo->prepare($sql);
    $st->execute([
      ':cid' => $correlationId,
      ':r' => json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);
  }

  public function markFailed(string $correlationId, string $error, ?array $result = null): void {
    $sql = "UPDATE automation_runs
            SET status='failed', finished_at=NOW(),
                error_message=:e,
                result_json=" . ($result ? "CAST(:r AS JSON)" : "NULL") . ",
                updated_at=NOW()
            WHERE correlation_id=:cid";
    $st = $this->pdo->prepare($sql);
    $params = [':cid' => $correlationId, ':e' => mb_substr($error, 0, 60000)];
    if ($result) $params[':r'] = json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $st->execute($params);
  }
}
