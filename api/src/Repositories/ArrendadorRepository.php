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
}
