<?php
namespace App\Repositories;

use App\Core\Database;

final class IncomeValidationRunsRepository {
  private \PDO $pdo;
  private const UPDATE_FIELDS = [
    'run_id',
    'prospecto_id',
    'idempotency_key',
    'status',
    'confidence_avg',
    'total_files',
    'approved_files',
    'review_files',
    'rejected_files',
    'insufficient_files',
    'promedio_mensual_estimado',
    'sueldo_declarado',
    'resumen_archivos',
    'resumen_completo',
    'closed_at',
  ];

  public function __construct(Database $db) {
    $this->pdo = $db->pdo();
  }

  public function findAll(array $filters = []): array {
    $conditions = [];
    $params = [];

    if (!empty($filters['run_id'])) {
      $conditions[] = 'run_id = :run_id';
      $params[':run_id'] = $filters['run_id'];
    }

    if (!empty($filters['prospecto_id'])) {
      $conditions[] = 'prospecto_id = :prospecto_id';
      $params[':prospecto_id'] = (int)$filters['prospecto_id'];
    }

    if (!empty($filters['status'])) {
      $conditions[] = 'status = :status';
      $params[':status'] = $filters['status'];
    }

    if (!empty($filters['idempotency_key'])) {
      $conditions[] = 'idempotency_key = :idempotency_key';
      $params[':idempotency_key'] = $filters['idempotency_key'];
    }

    $where = $conditions ? ('WHERE ' . implode(' AND ', $conditions)) : '';

    $sql = "SELECT * FROM income_validation_runs {$where} ORDER BY created_at DESC, id DESC";
    $st = $this->pdo->prepare($sql);
    $st->execute($params);
    return $st->fetchAll() ?: [];
  }

  public function findById(int $id): ?array {
    $sql = "SELECT * FROM income_validation_runs WHERE id = :id LIMIT 1";
    $st = $this->pdo->prepare($sql);
    $st->execute([':id' => $id]);
    $row = $st->fetch();
    return $row ?: null;
  }

  public function findByRunId(string $runId): ?array {
    $sql = "SELECT * FROM income_validation_runs WHERE run_id = :run_id LIMIT 1";
    $st = $this->pdo->prepare($sql);
    $st->execute([':run_id' => $runId]);
    $row = $st->fetch();
    return $row ?: null;
  }

  public function create(array $data): array {
    $sql = "INSERT INTO income_validation_runs
            (run_id, prospecto_id, idempotency_key, status)
            VALUES (:run_id, :prospecto_id, :idempotency_key, :status)";
    $st = $this->pdo->prepare($sql);
    $st->execute([
      ':run_id' => $data['run_id'],
      ':prospecto_id' => (int)$data['prospecto_id'],
      ':idempotency_key' => $data['idempotency_key'],
      ':status' => $data['status'] ?? 'processing',
    ]);

    $id = (int)$this->pdo->lastInsertId();
    return $this->findById($id) ?? [];
  }

  public function update(int $id, array $data): ?array {
    $existing = $this->findById($id);
    if (!$existing) {
      return null;
    }

    [$fields, $params] = $this->buildUpdateFields($data);
    $params[':id'] = $id;

    if (!$fields) {
      return $existing;
    }

    $sql = "UPDATE income_validation_runs SET " . implode(', ', $fields) . ", updated_at = NOW() WHERE id = :id";
    $st = $this->pdo->prepare($sql);
    $st->execute($params);

    return $this->findById($id);
  }

  public function updateByRunId(string $runId, array $data, bool $setClosedAtNow = false): ?array {
    $existing = $this->findByRunId($runId);
    if (!$existing) {
      return null;
    }

    [$fields, $params] = $this->buildUpdateFields($data, $setClosedAtNow);
    $params[':run_id'] = $runId;

    if (!$fields) {
      return $existing;
    }

    $sql = "UPDATE income_validation_runs SET " . implode(', ', $fields) . ", updated_at = NOW() WHERE run_id = :run_id";
    $st = $this->pdo->prepare($sql);
    $st->execute($params);

    return $this->findByRunId($runId);
  }

  public function delete(int $id): bool {
    $sql = "DELETE FROM income_validation_runs WHERE id = :id";
    $st = $this->pdo->prepare($sql);
    $st->execute([':id' => $id]);
    return $st->rowCount() > 0;
  }

  private function buildUpdateFields(array $data, bool $setClosedAtNow = false): array {
    $fields = [];
    $params = [];

    foreach (self::UPDATE_FIELDS as $field) {
      if (!array_key_exists($field, $data)) {
        continue;
      }

      $fields[] = "{$field} = :{$field}";
      $params[":{$field}"] = $this->normalizeValue($field, $data[$field]);
    }

    if ($setClosedAtNow && !array_key_exists('closed_at', $data)) {
      $fields[] = "closed_at = NOW()";
    }

    return [$fields, $params];
  }

  private function normalizeValue(string $field, mixed $value): mixed {
    if (in_array($field, ['prospecto_id', 'total_files', 'approved_files', 'review_files', 'rejected_files', 'insufficient_files'], true)) {
      return $value === null ? null : (int)$value;
    }

    if (in_array($field, ['confidence_avg', 'promedio_mensual_estimado', 'sueldo_declarado'], true)) {
      return $value === null ? null : (float)$value;
    }

    if (in_array($field, ['resumen_archivos', 'resumen_completo'], true) && is_array($value)) {
      return json_encode($value, JSON_UNESCAPED_UNICODE);
    }

    return $value;
  }
}
