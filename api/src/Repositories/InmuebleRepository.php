<?php
namespace App\Repositories;

use App\Core\Database;

final class InmuebleRepository {
  private \PDO $pdo;
  private const ARRENDADOR_PK_PREFIX = 'arr#';
  private const INMUEBLE_SK_PREFIX = 'INM#';

  public function __construct(Database $db) {
    $this->pdo = $db->pdo();
  }

  public function findAll(): array {
    // Joins opcionales para traer nombres, pero el requerimiento básico es el registro
    // Podemos hacer un fetch simple o un join básico para 'enriquecer' la respuesta
    // Por simplicidad y rendimiento inicial, hacemos select de la tabla.
    $sql = "SELECT i.*, 
                   a.nombre_arrendador, 
                   ase.nombre_asesor 
            FROM inmuebles i
            LEFT JOIN arrendadores a ON i.id_arrendador = a.id
            LEFT JOIN asesores ase ON i.id_asesor = ase.id
            ORDER BY i.id DESC";
    $st = $this->pdo->query($sql);
    return $st->fetchAll();
  }

  public function findById(int $id): ?array {
    $sql = "SELECT i.*, 
                   a.nombre_arrendador, 
                   ase.nombre_asesor 
            FROM inmuebles i
            LEFT JOIN arrendadores a ON i.id_arrendador = a.id
            LEFT JOIN asesores ase ON i.id_asesor = ase.id
            WHERE i.id = :id 
            LIMIT 1";
    $st = $this->pdo->prepare($sql);
    $st->execute([':id' => $id]);
    $row = $st->fetch();
    return $row ?: null;
  }

  public function create(array $data): int {
    $fields = [
      'id_arrendador', 'id_asesor', 'direccion_inmueble', 'tipo', 'renta', 
      'mantenimiento', 'monto_mantenimiento', 'deposito', 'estacionamiento', 
      'mascotas', 'comentarios'
    ];

    $columns = [];
    $placeholders = [];
    $values = [];

    foreach ($fields as $field) {
        // Permitimos que vengan algunos campos vacíos/null si la DB lo aguanta o lógica de negocio
        // Según schema: Null: NO en la mayoría. Validaremos en Controller.
        if (array_key_exists($field, $data)) {
            $columns[] = $field;
            $placeholders[] = ":$field";
            $values[":$field"] = $data[$field];
        } else {
             // Si falta y es NOT NULL, la DB fallará. 
             // O podríamos poner defaults aquí. 
             // Dejamos que falle o insertamos '' según corresponda.
             // Para 'comentarios', 'mascotas', etc, es mejor enviar '' si no viene.
        }
    }

    $sql = "INSERT INTO inmuebles (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
    
    $st = $this->pdo->prepare($sql);
    $st->execute($values);

    return (int)$this->pdo->lastInsertId();
  }

  public function update(int $id, array $data): void {
    $possibleFields = [
      'id_arrendador', 'id_asesor', 'direccion_inmueble', 'tipo', 'renta', 
      'mantenimiento', 'monto_mantenimiento', 'deposito', 'estacionamiento', 
      'mascotas', 'comentarios'
    ];

    $set = [];
    $values = [':id' => $id];

    foreach ($possibleFields as $field) {
        if (array_key_exists($field, $data)) {
            $set[] = "$field = :$field";
            $values[":$field"] = $data[$field];
        }
    }

    if (empty($set)) return;

    $sql = "UPDATE inmuebles SET " . implode(', ', $set) . " WHERE id = :id";
    $st = $this->pdo->prepare($sql);
    $st->execute($values);
  }

  public function delete(int $id): void {
    $sql = "DELETE FROM inmuebles WHERE id = :id";
    $this->pdo->prepare($sql)->execute([':id' => $id]);
  }

  public function deleteBulk(array $ids): int {
    if (empty($ids)) {
      return 0;
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $sql = "DELETE FROM inmuebles WHERE id IN ($placeholders)";
    $st = $this->pdo->prepare($sql);
    $st->execute($ids);

    return $st->rowCount();
  }

  public function findByArrendadorId(int $arrendadorId): array {
    $sql = "SELECT i.*, 
                   a.nombre_arrendador, 
                   ase.nombre_asesor 
            FROM inmuebles i
            LEFT JOIN arrendadores a ON i.id_arrendador = a.id
            LEFT JOIN asesores ase ON i.id_asesor = ase.id
            WHERE i.id_arrendador = :arrendador_id
            ORDER BY i.id DESC";
    $st = $this->pdo->prepare($sql);
    $st->execute([':arrendador_id' => $arrendadorId]);
    return $st->fetchAll();
  }

  public function findByLegacyKeys(string $pk, ?string $sk = null): ?array {
    $pk = trim($pk);
    $sk = $sk !== null ? trim($sk) : null;

    if ($sk === null && str_contains($pk, '|')) {
      [$pk, $sk] = array_map('trim', explode('|', $pk, 2));
    }

    if ($sk === null) {
      $legacyId = $this->parseNumericId($pk);
      return $legacyId ? $this->findById($legacyId) : null;
    }

    $arrendadorId = $this->parseArrendadorPk($pk);
    $inmuebleId = $this->parseInmuebleSk($sk);
    if (!$inmuebleId) {
      return null;
    }

    $inmueble = $this->findById($inmuebleId);
    if (!$inmueble) {
      return null;
    }

    if ($arrendadorId !== null && (int)$inmueble['id_arrendador'] !== $arrendadorId) {
      return null;
    }

    return $inmueble;
  }

  public function withLegacyKeys(array $inmueble): array {
    $id = (int)($inmueble['id'] ?? 0);
    $arrendadorId = (int)($inmueble['id_arrendador'] ?? 0);
    $pk = $arrendadorId > 0 ? self::ARRENDADOR_PK_PREFIX . $arrendadorId : null;
    $sk = $id > 0 ? self::INMUEBLE_SK_PREFIX . $id : null;

    $inmueble['pk'] = $pk;
    $inmueble['sk'] = $sk;
    $inmueble['id_virtual'] = ($pk && $sk) ? $pk . '|' . $sk : null;

    return $inmueble;
  }

  private function parseArrendadorPk(string $pk): ?int {
    if ($pk === '') {
      return null;
    }

    if (str_starts_with($pk, self::ARRENDADOR_PK_PREFIX)) {
      $pk = substr($pk, strlen(self::ARRENDADOR_PK_PREFIX));
    }

    return $this->parseNumericId($pk);
  }

  private function parseInmuebleSk(string $sk): ?int {
    if ($sk === '') {
      return null;
    }

    if (str_starts_with($sk, self::INMUEBLE_SK_PREFIX)) {
      $sk = substr($sk, strlen(self::INMUEBLE_SK_PREFIX));
    }

    return $this->parseNumericId($sk);
  }

  private function parseNumericId(string $value): ?int {
    $value = trim($value);
    if ($value === '' || !ctype_digit($value)) {
      return null;
    }

    $parsed = (int)$value;
    return $parsed > 0 ? $parsed : null;
  }
}
