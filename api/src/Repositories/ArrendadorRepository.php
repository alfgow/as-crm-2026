<?php
namespace App\Repositories;

use App\Core\Database;

final class ArrendadorRepository {
  private \PDO $pdo;

  public function __construct(Database $db) {
    $this->pdo = $db->pdo();
  }

  public function findAll(): array {
    $sql = "SELECT * FROM arrendadores ORDER BY id DESC";
    $st = $this->pdo->query($sql);
    return $st->fetchAll();
  }

  public function findById(int $id): ?array {
    $sql = "SELECT * FROM arrendadores WHERE id = :id LIMIT 1";
    $st = $this->pdo->prepare($sql);
    $st->execute([':id' => $id]);
    $row = $st->fetch();
    return $row ?: null;
  }

  public function findBySlug(string $slug): ?array {
    $sql = "SELECT * FROM arrendadores WHERE slug = :slug LIMIT 1";
    $st = $this->pdo->prepare($sql);
    $st->execute([':slug' => $slug]);
    $row = $st->fetch();
    return $row ?: null;
  }

  public function create(array $data): int {
    $fields = [
        'nombre_arrendador', 'nombre_representante', 'email', 'device_id', 'celular', 'telefono',
        'direccion_arrendador', 'estadocivil', 'rfc', 'id_asesor', 'nacionalidad', 
        'tipo_id', 'num_id', 'banco', 'cuenta', 'clabe', 'comentarios', 'estatus', 'slug'
    ];

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
    
    // Required constraints check (simple DB level try/catch in Controller, but good to be aware)
    
    $sql = "INSERT INTO arrendadores (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
    
    $st = $this->pdo->prepare($sql);
    $st->execute($values);

    return (int)$this->pdo->lastInsertId();
  }

  public function update(int $id, array $data): void {
    $possibleFields = [
        'nombre_arrendador', 'nombre_representante', 'email', 'device_id', 'celular', 'telefono',
        'direccion_arrendador', 'estadocivil', 'rfc', 'id_asesor', 'nacionalidad', 
        'tipo_id', 'num_id', 'banco', 'cuenta', 'clabe', 'comentarios', 'estatus', 'slug'
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

    $sql = "UPDATE arrendadores SET " . implode(', ', $set) . " WHERE id = :id";
    $st = $this->pdo->prepare($sql);
    $st->execute($values);
  }

  public function delete(int $id): void {
    $sql = "DELETE FROM arrendadores WHERE id = :id";
    $st = $this->pdo->prepare($sql);
    $st->execute([':id' => $id]);
  }

  public function findByAsesorId(int $asesorId): array {
    $sql = "SELECT *
            FROM arrendadores
            WHERE id_asesor = :asesor_id
            ORDER BY id DESC";
    $st = $this->pdo->prepare($sql);
    $st->execute([':asesor_id' => $asesorId]);
    return $st->fetchAll();
  }

  public function findArchivosByArrendadorId(int $arrendadorId): array {
    $sql = "SELECT * FROM arrendadores_archivos WHERE id_arrendador = :id ORDER BY id_archivo DESC";
    $st = $this->pdo->prepare($sql);
    $st->execute([':id' => $arrendadorId]);
    return $st->fetchAll();
  }

  public function addArchivo(int $arrendadorId, array $data): ?array {
    $sql = "INSERT INTO arrendadores_archivos (id_arrendador, s3_key, tipo)
            VALUES (:id_arrendador, :s3_key, :tipo)";
    $st = $this->pdo->prepare($sql);
    $st->execute([
      ':id_arrendador' => $arrendadorId,
      ':s3_key' => $data['s3_key'],
      ':tipo' => $data['tipo'],
    ]);

    $id = (int)$this->pdo->lastInsertId();
    return $this->findArchivoById($arrendadorId, $id);
  }

  public function deleteArchivo(int $arrendadorId, int $archivoId): bool {
    $sql = "DELETE FROM arrendadores_archivos WHERE id_arrendador = :id AND id_archivo = :archivo_id";
    $st = $this->pdo->prepare($sql);
    $st->execute([
      ':id' => $arrendadorId,
      ':archivo_id' => $archivoId,
    ]);

    return $st->rowCount() > 0;
  }

  public function updateArchivo(int $arrendadorId, int $archivoId, array $data): ?array {
    $allowed = ['s3_key', 'tipo'];
    $set = [];
    $values = [
      ':id_arrendador' => $arrendadorId,
      ':id_archivo' => $archivoId,
    ];

    foreach ($allowed as $field) {
      if (array_key_exists($field, $data)) {
        $set[] = "$field = :$field";
        $values[":$field"] = $data[$field];
      }
    }

    if (empty($set)) {
      return $this->findArchivoById($arrendadorId, $archivoId);
    }

    $sql = "UPDATE arrendadores_archivos
            SET " . implode(', ', $set) . "
            WHERE id_arrendador = :id_arrendador AND id_archivo = :id_archivo";
    $st = $this->pdo->prepare($sql);
    $st->execute($values);

    return $this->findArchivoById($arrendadorId, $archivoId);
  }

  private function findArchivoById(int $arrendadorId, int $archivoId): ?array {
    $sql = "SELECT * FROM arrendadores_archivos WHERE id_arrendador = :id AND id_archivo = :archivo_id LIMIT 1";
    $st = $this->pdo->prepare($sql);
    $st->execute([
      ':id' => $arrendadorId,
      ':archivo_id' => $archivoId,
    ]);
    $row = $st->fetch();
    return $row ?: null;
  }
}
