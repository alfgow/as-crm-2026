<?php
namespace App\Repositories;

use App\Core\Database;

final class IncomeValidationRunFilesRepository {
  private \PDO $pdo;

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

    if (!empty($filters['archivo_id'])) {
      $conditions[] = 'archivo_id = :archivo_id';
      $params[':archivo_id'] = (int)$filters['archivo_id'];
    }

    if (!empty($filters['status'])) {
      $conditions[] = 'status = :status';
      $params[':status'] = $filters['status'];
    }

    if (!empty($filters['tipo'])) {
      $conditions[] = 'tipo = :tipo';
      $params[':tipo'] = $filters['tipo'];
    }

    $where = $conditions ? ('WHERE ' . implode(' AND ', $conditions)) : '';

    $sql = "SELECT * FROM income_validation_run_files {$where} ORDER BY created_at DESC, id DESC";
    $st = $this->pdo->prepare($sql);
    $st->execute($params);
    return $st->fetchAll() ?: [];
  }

  public function findById(int $id): ?array {
    $sql = "SELECT * FROM income_validation_run_files WHERE id = :id LIMIT 1";
    $st = $this->pdo->prepare($sql);
    $st->execute([':id' => $id]);
    $row = $st->fetch();
    return $row ?: null;
  }

  public function create(array $data): array {
    $sql = "INSERT INTO income_validation_run_files
            (run_id, archivo_id, s3_key, tipo, presigned_url, status, file_name, mime_type, file_size_bytes, maybe_text_layer)
            VALUES
            (:run_id, :archivo_id, :s3_key, :tipo, :presigned_url, :status, :file_name, :mime_type, :file_size_bytes, :maybe_text_layer)";
    $st = $this->pdo->prepare($sql);
    $st->execute([
      ':run_id' => $data['run_id'],
      ':archivo_id' => (int)$data['archivo_id'],
      ':s3_key' => $data['s3_key'],
      ':tipo' => $data['tipo'],
      ':presigned_url' => $data['presigned_url'] ?? null,
      ':status' => $data['status'] ?? 'queued',
      ':file_name' => $data['file_name'] ?? null,
      ':mime_type' => $data['mime_type'] ?? null,
      ':file_size_bytes' => $this->normalizeInt($data['file_size_bytes'] ?? null),
      ':maybe_text_layer' => $this->normalizeBool($data['maybe_text_layer'] ?? null),
    ]);

    $id = (int)$this->pdo->lastInsertId();
    return $this->findById($id) ?? [];
  }

  public function update(int $id, array $data): ?array {
    $existing = $this->findById($id);
    if (!$existing) {
      return null;
    }

    $fields = [];
    $params = [':id' => $id];
    $allowed = [
      'run_id',
      'archivo_id',
      's3_key',
      'tipo',
      'presigned_url',
      'status',
      'file_name',
      'mime_type',
      'file_size_bytes',
      'maybe_text_layer',
    ];

    foreach ($allowed as $field) {
      if (array_key_exists($field, $data)) {
        $fields[] = "{$field} = :{$field}";

        if ($field === 'archivo_id') {
          $params[":{$field}"] = (int)$data[$field];
        } elseif ($field === 'file_size_bytes') {
          $params[":{$field}"] = $this->normalizeInt($data[$field]);
        } elseif ($field === 'maybe_text_layer') {
          $params[":{$field}"] = $this->normalizeBool($data[$field]);
        } else {
          $params[":{$field}"] = $data[$field];
        }
      }
    }

    if (!$fields) {
      return $existing;
    }

    $sql = "UPDATE income_validation_run_files SET " . implode(', ', $fields) . ", updated_at = NOW() WHERE id = :id";
    $st = $this->pdo->prepare($sql);
    $st->execute($params);

    return $this->findById($id);
  }

  public function delete(int $id): bool {
    $sql = "DELETE FROM income_validation_run_files WHERE id = :id";
    $st = $this->pdo->prepare($sql);
    $st->execute([':id' => $id]);
    return $st->rowCount() > 0;
  }

  private function normalizeInt($value): ?int {
    if ($value === null || $value === '') {
      return null;
    }
    return (int)$value;
  }

  private function normalizeBool($value): ?int {
    if ($value === null || $value === '') {
      return null;
    }
    if (is_bool($value)) {
      return $value ? 1 : 0;
    }
    return (int)(bool)$value;
  }
}
