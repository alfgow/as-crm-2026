<?php
namespace App\Repositories;

use App\Core\Database;

final class InquilinoRepository {
  private \PDO $pdo;

  public function __construct(Database $db) {
    $this->pdo = $db->pdo();
  }

  // --- Main List ---
  public function findAll(): array {
    $sql = "SELECT id, nombre_inquilino, apellidop_inquilino, apellidom_inquilino, email, celular, status, fecha as fecha_registro
            FROM inquilinos 
            ORDER BY id DESC";
    $st = $this->pdo->query($sql);
    return $st->fetchAll();
  }

  // --- Full Profile ---
  public function findById(int $id): ?array {
    // 1. Main
    $sql = "SELECT * FROM inquilinos WHERE id = :id LIMIT 1";
    $st = $this->pdo->prepare($sql);
    $st->execute([':id' => $id]);
    $main = $st->fetch();

    if (!$main) return null;

    // 2. Direccion
    $main['direccion'] = $this->fetchOne('inquilinos_direccion', $id);
    
    // 3. Trabajo
    $main['trabajo'] = $this->fetchOne('inquilinos_trabajo', $id);

    // 4. Fiador
    $main['fiador'] = $this->fetchOne('inquilinos_fiador', $id);

    // 5. Historial
    $main['historial_vivienda'] = $this->fetchOne('inquilinos_historial_vivienda', $id);

    // 6. Validaciones
    $main['validaciones'] = $this->fetchOne('inquilinos_validaciones', $id);

    // 7. Archivos (List)
    $stmtFiles = $this->pdo->prepare("SELECT * FROM inquilinos_archivos WHERE id_inquilino = :id");
    $stmtFiles->execute([':id' => $id]);
    $main['archivos'] = $stmtFiles->fetchAll();

    return $main;
  }

  private function fetchOne(string $table, int $idInquilino) {
      $st = $this->pdo->prepare("SELECT * FROM $table WHERE id_inquilino = :id LIMIT 1");
      $st->execute([':id' => $idInquilino]);
      $res = $st->fetch();
      return $res ?: null;
  }

  // --- CRUD Main Inquilino ---
  public function create(array $data): int {
    $fields = [
      'id_asesor', 'tipo', 'nombre_inquilino', 'apellidop_inquilino', 'apellidom_inquilino',
      'representante', 'estadocivil', 'rfc', 'curp', 'email', 'celular', 'nacionalidad',
      'tipo_id', 'num_id', 'conyuge', 'device_id', 'ip', 'status', 'slug'
    ];
    
    // Auto-slug if not present or empty?
    if (empty($data['slug']) && !empty($data['nombre_inquilino'])) {
        $slugBase = strtolower(trim($data['nombre_inquilino'] . '-' . ($data['apellidop_inquilino'] ?? '')));
        $data['slug'] = preg_replace('/[^a-z0-9]+/', '-', $slugBase) . '-' . time();
    }

    $columns = [];
    $placeholders = [];
    $values = [];

    foreach ($fields as $field) {
        if (array_key_exists($field, $data)) {
            $columns[] = $field;
            $placeholders[] = ":$field";
            $values[":$field"] = $data[$field];
        }
    }

    $sql = "INSERT INTO inquilinos (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
    $st = $this->pdo->prepare($sql);
    $st->execute($values);
    
    return (int)$this->pdo->lastInsertId();
  }

  public function update(int $id, array $data): void {
    $fields = [
      'id_asesor', 'tipo', 'nombre_inquilino', 'apellidop_inquilino', 'apellidom_inquilino',
      'representante', 'estadocivil', 'rfc', 'curp', 'email', 'celular', 'nacionalidad',
      'tipo_id', 'num_id', 'conyuge', 'device_id', 'ip', 'status', 'slug'
    ];

    $set = [];
    $values = [':id' => $id];

    foreach ($fields as $field) {
        if (array_key_exists($field, $data)) {
            $set[] = "$field = :$field";
            $values[":$field"] = $data[$field];
        }
    }

    if (empty($set)) return;

    $sql = "UPDATE inquilinos SET " . implode(', ', $set) . " WHERE id = :id";
    $this->pdo->prepare($sql)->execute($values);
  }

  public function delete(int $id): void {
     // Optional: Delete from related tables manually if no ON DELETE CASCADE
     // We will assume DB handles cascades or we leave orphans for safety in this version.
     // To be safe, let's delete main.
     $sql = "DELETE FROM inquilinos WHERE id = :id";
     $this->pdo->prepare($sql)->execute([':id' => $id]);
  }

    // --- Sub-Entity Updates (Simplified) ---
    // Update Direccion
    public function updateDireccion(int $idInquilino, array $data): void {
        $this->upsertSubTable('inquilinos_direccion', $idInquilino, $data);
    }
    // Update Trabajo
    public function updateTrabajo(int $idInquilino, array $data): void {
        $this->upsertSubTable('inquilinos_trabajo', $idInquilino, $data);
    }
    // Update Fiador
    public function updateFiador(int $idInquilino, array $data): void {
        $this->upsertSubTable('inquilinos_fiador', $idInquilino, $data);
    }
    // Update Historial
    public function updateHistorial(int $idInquilino, array $data): void {
        $this->upsertSubTable('inquilinos_historial_vivienda', $idInquilino, $data);
    }

    private function upsertSubTable(string $table, int $idInquilino, array $data): void {
        // Check existence
        $exists = $this->fetchOne($table, $idInquilino);
        
        // Remove id/id_inquilino from data if present to avoid overwrite errors
        unset($data['id'], $data['id_inquilino']);
        
        if (empty($data)) return;

        if ($exists) {
            // Update
            $set = [];
            $values = [':id_inquilino' => $idInquilino];
            foreach ($data as $k => $v) {
                $set[] = "$k = :$k";
                $values[":$k"] = $v;
            }
            $sql = "UPDATE $table SET " . implode(', ', $set) . " WHERE id_inquilino = :id_inquilino";
            $this->pdo->prepare($sql)->execute($values);
        } else {
            // Insert
            $data['id_inquilino'] = $idInquilino;
            $cols = array_keys($data);
            $vals = [];
            foreach ($data as $k => $v) {
                $vals[":$k"] = $v;
            }
            $sql = "INSERT INTO $table (" . implode(', ', $cols) . ") VALUES (" . implode(', ', array_keys($vals)) . ")";
            $this->pdo->prepare($sql)->execute($vals);
        }
    }

}
