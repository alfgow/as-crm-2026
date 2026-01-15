<?php
namespace App\Repositories;

use App\Core\Database;

final class PolizaRepository {
  private \PDO $pdo;

  public function __construct(Database $db) {
    $this->pdo = $db->pdo();
  }

  public function findAll(): array {
    // Join opcional para traer nombres. 
    // Dado que son muchas relaciones, un 'Select *' + joins básicos ayuda.
    $sql = "SELECT p.*,
                   a.nombre_arrendador,
                   i.nombre_inquilino,
                   ase.nombre_asesor,
                   inm.direccion_inmueble
            FROM polizas p
            LEFT JOIN arrendadores a ON p.id_arrendador = a.id
            LEFT JOIN inquilinos i ON p.id_inquilino = i.id
            LEFT JOIN asesores ase ON p.id_asesor = ase.id
            LEFT JOIN inmuebles inm ON p.id_inmueble = inm.id
            ORDER BY p.id_poliza DESC";
    $st = $this->pdo->query($sql);
    return $st->fetchAll();
  }

  public function findById(int $id): ?array {
    $sql = "SELECT p.*,
                   a.nombre_arrendador,
                   i.nombre_inquilino,
                   ase.nombre_asesor,
                   inm.direccion_inmueble
            FROM polizas p
            LEFT JOIN arrendadores a ON p.id_arrendador = a.id
            LEFT JOIN inquilinos i ON p.id_inquilino = i.id
            LEFT JOIN asesores ase ON p.id_asesor = ase.id
            LEFT JOIN inmuebles inm ON p.id_inmueble = inm.id
            WHERE p.id_poliza = :id 
            LIMIT 1";
    $st = $this->pdo->prepare($sql);
    $st->execute([':id' => $id]);
    $row = $st->fetch();
    return $row ?: null;
  }

  public function create(array $data): int {
    $fields = [
      'tipo_poliza', 'id_asesor', 'id_arrendador', 'id_inquilino', 'id_obligado', 'id_fiador', 
      'id_inmueble', 'tipo_inmueble', 'monto_renta', 'monto_poliza', 'estado', 'vigencia', 
      'mes_vencimiento', 'year_vencimiento', 'usuario', 'serie_poliza', 'numero_poliza', 
      'fecha_poliza', 'fecha_fin', 'periodo', 'comentarios'
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

    $sql = "INSERT INTO polizas (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
    
    $st = $this->pdo->prepare($sql);
    $st->execute($values);

    return (int)$this->pdo->lastInsertId();
  }

  public function update(int $id, array $data): void {
    $possibleFields = [
      'tipo_poliza', 'id_asesor', 'id_arrendador', 'id_inquilino', 'id_obligado', 'id_fiador', 
      'id_inmueble', 'tipo_inmueble', 'monto_renta', 'monto_poliza', 'estado', 'vigencia', 
      'mes_vencimiento', 'year_vencimiento', 'usuario', 'serie_poliza', 'numero_poliza', 
      'fecha_poliza', 'fecha_fin', 'periodo', 'comentarios'
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

    $sql = "UPDATE polizas SET " . implode(', ', $set) . " WHERE id_poliza = :id";
    $st = $this->pdo->prepare($sql);
    $st->execute($values);
  }

  public function delete(int $id): void {
    $sql = "DELETE FROM polizas WHERE id_poliza = :id";
    $this->pdo->prepare($sql)->execute([':id' => $id]);
  }
  public function getNextNumeroPoliza(): int {
    $sql = "SELECT MAX(numero_poliza) as max_num FROM polizas";
    $st = $this->pdo->query($sql);
    $row = $st->fetch();
    $max = $row['max_num'] ?? 0;
    return (int)$max + 1;
  }
}
