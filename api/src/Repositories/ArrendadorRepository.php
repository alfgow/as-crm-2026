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

  public function findArchivosByArrendadorId(int $idArrendador): array {
    $sql = "SELECT id_archivo, id_arrendador, s3_key, tipo, fecha_subida
            FROM arrendadores_archivos
            WHERE id_arrendador = :id
            ORDER BY fecha_subida DESC";
    $st = $this->pdo->prepare($sql);
    $st->execute([':id' => $idArrendador]);
    return $st->fetchAll();
  }

  public function findArchivoByTipo(int $idArrendador, string $tipo): ?array {
    $sql = "SELECT id_archivo, id_arrendador, s3_key, tipo, fecha_subida
            FROM arrendadores_archivos
            WHERE id_arrendador = :id AND tipo = :tipo
            ORDER BY fecha_subida DESC
            LIMIT 1";
    $st = $this->pdo->prepare($sql);
    $st->execute([':id' => $idArrendador, ':tipo' => $tipo]);
    $row = $st->fetch();
    return $row ?: null;
  }

  public function addArchivo(int $idArrendador, string $tipo, string $s3Key): array {
    $existing = $this->findArchivoByTipo($idArrendador, $tipo);

    if ($existing) {
      $sql = "UPDATE arrendadores_archivos
              SET s3_key = :s3_key, fecha_subida = NOW()
              WHERE id_archivo = :id";
      $st = $this->pdo->prepare($sql);
      $st->execute([
        ':s3_key' => $s3Key,
        ':id' => (int)$existing['id_archivo']
      ]);
      $id = (int)$existing['id_archivo'];
    } else {
      $sql = "INSERT INTO arrendadores_archivos (id_arrendador, s3_key, tipo)
              VALUES (:id_arrendador, :s3_key, :tipo)";
      $st = $this->pdo->prepare($sql);
      $st->execute([
        ':id_arrendador' => $idArrendador,
        ':s3_key' => $s3Key,
        ':tipo' => $tipo
      ]);
      $id = (int)$this->pdo->lastInsertId();
    }

    $fetch = $this->pdo->prepare("SELECT id_archivo, id_arrendador, s3_key, tipo, fecha_subida FROM arrendadores_archivos WHERE id_archivo = :id LIMIT 1");
    $fetch->execute([':id' => $id]);
    $row = $fetch->fetch();

    return $row ?: [];
  }

  public function deleteArchivo(int $idArrendador, int $archivoId): bool {
    $sql = "DELETE FROM arrendadores_archivos WHERE id_archivo = :id AND id_arrendador = :id_arrendador";
    $st = $this->pdo->prepare($sql);
    $st->execute([':id' => $archivoId, ':id_arrendador' => $idArrendador]);
    return $st->rowCount() > 0;
  }
}
