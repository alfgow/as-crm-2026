<?php
namespace App\Repositories;

use App\Core\Database;

final class AutomationRepository {
  private \PDO $pdo;

  public function __construct(Database $db) {
    $this->pdo = $db->pdo();
  }

  public function upsert(array $data): void {
      // Check if exists
      $sql = "SELECT id FROM automation_runs WHERE correlation_id = :correlation_id LIMIT 1";
      $st = $this->pdo->prepare($sql);
      $st->execute([':correlation_id' => $data['correlation_id']]);
      $exists = $st->fetchColumn();

      if ($exists) {
          $set = [];
          $values = [':correlation_id' => $data['correlation_id']];
          
          if (isset($data['status'])) { $set[] = "status = :status"; $values[':status'] = $data['status']; }
          if (isset($data['n8n_workflow'])) { $set[] = "n8n_workflow = :n8n_workflow"; $values[':n8n_workflow'] = $data['n8n_workflow']; }
          if (isset($data['n8n_execution_id'])) { $set[] = "n8n_execution_id = :n8n_execution_id"; $values[':n8n_execution_id'] = $data['n8n_execution_id']; }
          if (isset($data['finished_at'])) { $set[] = "finished_at = :finished_at"; $values[':finished_at'] = $data['finished_at']; }
          if (isset($data['result_json'])) { $set[] = "result_json = :result_json"; $values[':result_json'] = $data['result_json']; }
          if (isset($data['error_message'])) { $set[] = "error_message = :error_message"; $values[':error_message'] = $data['error_message']; }

          if (!empty($set)) {
              $sql = "UPDATE automation_runs SET " . implode(', ', $set) . ", updated_at = NOW() WHERE correlation_id = :correlation_id";
              $this->pdo->prepare($sql)->execute($values);
          }
      } else {
          $sql = "INSERT INTO automation_runs (correlation_id, event_type, n8n_workflow, n8n_execution_id, status, started_at, finished_at, result_json, error_message, created_at)
                  VALUES (:correlation_id, :event_type, :n8n_workflow, :n8n_execution_id, :status, :started_at, :finished_at, :result_json, :error_message, NOW())";
          
          $st = $this->pdo->prepare($sql);
          $st->execute([
              ':correlation_id' => $data['correlation_id'],
              ':event_type' => $data['event_type'] ?? 'unknown',
              ':n8n_workflow' => $data['n8n_workflow'] ?? null,
              ':n8n_execution_id' => $data['n8n_execution_id'] ?? null,
              ':status' => $data['status'] ?? 'received',
              ':started_at' => $data['started_at'] ?? date('Y-m-d H:i:s'),
              ':finished_at' => $data['finished_at'] ?? null,
              ':result_json' => isset($data['result_json']) ? $data['result_json'] : null,
              ':error_message' => $data['error_message'] ?? null,
          ]);
      }
  }
}
