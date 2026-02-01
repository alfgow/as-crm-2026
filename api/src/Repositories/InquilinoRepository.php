<?php
namespace App\Repositories;

use App\Core\Database;

final class InquilinoRepository {
  private \PDO $pdo;

  public function __construct(Database $db) {
    $this->pdo = $db->pdo();
  }

  // --- Main List ---
  /**
   * Obtiene todos los inquilinos con filtros opcionales
   * 
   * @param string|null $search Término de búsqueda para nombre, email o celular
   * @param string|null $status Filtro por status: '1' (Nuevo), '2' (En Proceso), '3' (Aprobado), '4' (Rechazado)
   * @return array Lista de inquilinos
   */
  public function findAll(?string $search = null, ?string $status = null): array {
    $sql = "SELECT id, nombre_inquilino, apellidop_inquilino, apellidom_inquilino, email, celular, status, fecha as fecha_registro
            FROM inquilinos";
            
    $params = [];
    $conditions = [];
    
    // Filtro de búsqueda por texto
    if (!empty($search)) {
        // Use CONCAT_WS for full name search, plus specific fields for email/phone
        $conditions[] = "(CONCAT_WS(' ', nombre_inquilino, apellidop_inquilino, apellidom_inquilino) LIKE :search1
                         OR email LIKE :search2
                         OR celular LIKE :search3)";
        $params[':search1'] = '%' . $search . '%';
        $params[':search2'] = '%' . $search . '%';
        $params[':search3'] = '%' . $search . '%';
    }
    
    // Filtro por status
    if (!empty($status)) {
        $conditions[] = "status = :status";
        $params[':status'] = $status;
    }
    
    // Agregar condiciones WHERE si existen
    if (!empty($conditions)) {
        $sql .= " WHERE " . implode(' AND ', $conditions);
    }

    $sql .= " ORDER BY id DESC";
    
    $st = $this->pdo->prepare($sql);
    $st->execute($params);
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

  public function findBySlug(string $slug): ?array {
    $sql = "SELECT id FROM inquilinos WHERE slug = :slug LIMIT 1";
    $st = $this->pdo->prepare($sql);
    $st->execute([':slug' => $slug]);
    $row = $st->fetch();

    if (!$row || empty($row['id'])) {
      return null;
    }

    return $this->findById((int)$row['id']);
  }

  public function countNuevos(int $dias = 30): int {
    $fechaLimite = (new \DateTimeImmutable(sprintf('-%d days', $dias)))->format('Y-m-d H:i:s');
    $sql = "SELECT COUNT(*) AS total
            FROM inquilinos
            WHERE fecha >= :fecha_limite";
    $st = $this->pdo->prepare($sql);
    $st->execute([':fecha_limite' => $fechaLimite]);
    $row = $st->fetch();

    return (int)($row['total'] ?? 0);
  }

  public function findNuevosConSelfie(int $dias = 30): array {
    $fechaLimite = (new \DateTimeImmutable(sprintf('-%d days', $dias)))->format('Y-m-d H:i:s');
    $sql = "SELECT DISTINCT i.id,
                   i.nombre_inquilino,
                   i.apellidop_inquilino,
                   i.apellidom_inquilino,
                   i.email,
                   i.celular,
                   i.status,
                   i.fecha AS fecha_registro,
                   a.s3_key AS selfie_s3_key
            FROM inquilinos i
            INNER JOIN inquilinos_archivos a
              ON a.id_inquilino = i.id AND a.tipo = 'selfie'
            WHERE i.fecha >= :fecha_limite
            ORDER BY i.fecha DESC";
    $st = $this->pdo->prepare($sql);
    $st->execute([':fecha_limite' => $fechaLimite]);
    return $st->fetchAll();
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

  public function deleteBulk(array $ids): int {
    if (empty($ids)) {
      return 0;
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $sql = "DELETE FROM inquilinos WHERE id IN ($placeholders)";
    $st = $this->pdo->prepare($sql);
    $st->execute($ids);

    return $st->rowCount();
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

  public function updateValidaciones(int $idInquilino, array $data): void {
        $this->upsertSubTable('inquilinos_validaciones', $idInquilino, $data);
    }

    public function findArchivosByInquilinoId(int $idInquilino): array {
        $stmt = $this->pdo->prepare("SELECT * FROM inquilinos_archivos WHERE id_inquilino = :id");
        $stmt->execute([':id' => $idInquilino]);
        return $stmt->fetchAll();
    }

    public function findArchivosByTipos(int $idInquilino, array $tipos): array {
        if (empty($tipos)) {
            return [];
        }

        $placeholders = [];
        $params = [':id' => $idInquilino];
        foreach ($tipos as $index => $tipo) {
            $placeholder = ':tipo' . $index;
            $placeholders[] = $placeholder;
            $params[$placeholder] = $tipo;
        }

        $sql = "SELECT s3_key FROM inquilinos_archivos WHERE id_inquilino = :id AND tipo IN (" . implode(', ', $placeholders) . ")";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(\PDO::FETCH_COLUMN);
        return $rows ?: [];
    }

    public function addArchivo(int $idInquilino, array $data): ?array {
        $allowed = ['tipo', 's3_key', 'mime_type', 'size', 'original_name', 'token', 'categoria'];
        $columns = ['id_inquilino'];
        $placeholders = [':id_inquilino'];
        $params = [':id_inquilino' => $idInquilino];

        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $columns[] = $field;
                $placeholders[] = ':' . $field;
                $params[':' . $field] = $data[$field];
            }
        }

        if (!isset($params[':tipo']) || !isset($params[':s3_key'])) {
            return null;
        }

        $sql = "INSERT INTO inquilinos_archivos (" . implode(', ', $columns) . ")
                VALUES (" . implode(', ', $placeholders) . ")";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $id = (int)$this->pdo->lastInsertId();
        return $this->findArchivoById($idInquilino, $id);
    }

    public function findArchivoById(int $idInquilino, int $archivoId): ?array {
        $sql = "SELECT * FROM inquilinos_archivos WHERE id_inquilino = :id AND id = :archivo_id LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':id' => $idInquilino,
            ':archivo_id' => $archivoId,
        ]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function deleteArchivo(int $idInquilino, int $archivoId): bool {
        $sql = "DELETE FROM inquilinos_archivos WHERE id_inquilino = :id AND id = :archivo_id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':id' => $idInquilino,
            ':archivo_id' => $archivoId,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function updateArchivo(int $idInquilino, int $archivoId, array $data): ?array {
        $allowed = ['tipo', 's3_key', 'mime_type', 'size', 'original_name', 'token', 'categoria'];
        $set = [];
        $params = [
            ':id_inquilino' => $idInquilino,
            ':archivo_id' => $archivoId,
        ];

        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $set[] = "$field = :$field";
                $params[":$field"] = $data[$field];
            }
        }

        if (empty($set)) {
            return $this->findArchivoById($idInquilino, $archivoId);
        }

        $sql = "UPDATE inquilinos_archivos
                SET " . implode(', ', $set) . "
                WHERE id_inquilino = :id_inquilino AND id = :archivo_id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $this->findArchivoById($idInquilino, $archivoId);
    }

    public function findArchivosIdentidad(int $idInquilino): array {
        $tipos = ['selfie', 'ine_frontal', 'ine_reverso', 'pasaporte'];
        $placeholders = [];
        $params = [':id' => $idInquilino];

        foreach ($tipos as $index => $tipo) {
            $placeholder = ':tipo' . $index;
            $placeholders[] = $placeholder;
            $params[$placeholder] = $tipo;
        }

        $sql = "SELECT * FROM inquilinos_archivos WHERE id_inquilino = :id AND tipo IN (" . implode(', ', $placeholders) . ")";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
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
