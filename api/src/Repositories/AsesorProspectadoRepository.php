<?php
namespace App\Repositories;

use App\Core\Database;

final class AsesorProspectadoRepository {
  private \PDO $pdo;

  public function __construct(Database $db) {
    $this->pdo = $db->pdo();
  }

  public function findAll(): array {
    $sql = "SELECT `id`, `nombre`, `telefono`, `estatus`, `fecha`
            FROM `asesores-prospectados`
            ORDER BY `id` DESC";
    $st = $this->pdo->query($sql);
    return $st->fetchAll();
  }

  public function findById(int $id): ?array {
    $sql = "SELECT `id`, `nombre`, `telefono`, `estatus`, `fecha`
            FROM `asesores-prospectados`
            WHERE `id` = :id
            LIMIT 1";
    $st = $this->pdo->prepare($sql);
    $st->execute([':id' => $id]);
    $row = $st->fetch();
    return $row ?: null;
  }

  public function create(array $data): array {
    $sql = "INSERT INTO `asesores-prospectados` (`nombre`, `telefono`, `estatus`, `fecha`)
            VALUES (:nombre, :telefono, :estatus, :fecha)";
    $st = $this->pdo->prepare($sql);
    $st->execute([
      ':nombre' => $this->normalizeNullableString($data['nombre'] ?? null),
      ':telefono' => trim((string)$data['telefono']),
      ':estatus' => trim((string)$data['estatus']),
      ':fecha' => $this->normalizeNullableString($data['fecha'] ?? null),
    ]);

    return $this->findById((int)$this->pdo->lastInsertId()) ?? [];
  }

  public function update(int $id, array $data): ?array {
    $fields = [];
    $params = [':id' => $id];

    if (array_key_exists('nombre', $data)) {
      $fields[] = "`nombre` = :nombre";
      $params[':nombre'] = $this->normalizeNullableString($data['nombre']);
    }
    if (array_key_exists('telefono', $data)) {
      $fields[] = "`telefono` = :telefono";
      $params[':telefono'] = trim((string)$data['telefono']);
    }
    if (array_key_exists('fecha', $data)) {
      $fields[] = "`fecha` = :fecha";
      $params[':fecha'] = $this->normalizeNullableString($data['fecha']);
    }
    if (array_key_exists('estatus', $data)) {
      $fields[] = "`estatus` = :estatus";
      $params[':estatus'] = trim((string)$data['estatus']);
    }

    if ($fields === []) {
      return $this->findById($id);
    }

    $sql = "UPDATE `asesores-prospectados`
            SET " . implode(', ', $fields) . "
            WHERE `id` = :id";
    $st = $this->pdo->prepare($sql);
    $st->execute($params);

    if ($st->rowCount() === 0 && $this->findById($id) === null) {
      return null;
    }

    return $this->findById($id);
  }

  public function delete(int $id): bool {
    $sql = "DELETE FROM `asesores-prospectados` WHERE `id` = :id";
    $st = $this->pdo->prepare($sql);
    $st->execute([':id' => $id]);
    return $st->rowCount() > 0;
  }

  private function normalizeNullableString(mixed $value): ?string {
    if ($value === null) {
      return null;
    }

    $value = trim((string)$value);
    return $value === '' ? null : $value;
  }
}
