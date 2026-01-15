<?php
namespace App\Repositories;

use App\Core\Database;

final class InmuebleRepository {
  private \PDO $pdo;

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
}
