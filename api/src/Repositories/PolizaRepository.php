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

  public function findByNumero(int $numero): ?array {
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
            WHERE p.numero_poliza = :numero
            LIMIT 1";
    $st = $this->pdo->prepare($sql);
    $st->execute([':numero' => $numero]);
    $row = $st->fetch();
    return $row ?: null;
  }

  public function findContratoByNumero(int $numero): ?array {
    return $this->fetchContrato('numero_poliza', $numero);
  }

  public function findContratoById(int $id): ?array {
    return $this->fetchContrato('id_poliza', $id);
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

  public function findVencimientosProximos(): array {
    $mesActual = (int)date('n');
    $anioActual = (int)date('Y');

    $mesSiguiente = $mesActual + 1;
    $anioSiguiente = $anioActual;
    if ($mesSiguiente > 12) {
      $mesSiguiente = 1;
      $anioSiguiente++;
    }

    return $this->findVencimientosPorMesAnio($mesSiguiente, $anioSiguiente);
  }

  public function findVencimientosPorMesAnio(int $mes, int $anio): array {
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
            WHERE p.estado = :estado
              AND p.mes_vencimiento = :mes
              AND p.year_vencimiento = :anio
            ORDER BY p.fecha_poliza ASC";
    $st = $this->pdo->prepare($sql);
    $st->execute([
      ':estado' => '1',
      ':mes' => $mes,
      ':anio' => $anio,
    ]);
    return $st->fetchAll();
  }

  public function getUltimaPolizaEmitida(): string {
    $sql = "SELECT numero_poliza FROM polizas ORDER BY id_poliza DESC LIMIT 1";
    $st = $this->pdo->prepare($sql);
    $st->execute();
    $row = $st->fetch();
    return (string)($row['numero_poliza'] ?? '0');
  }

  public function getNextNumeroPoliza(): int {
    $sql = "SELECT MAX(numero_poliza) as max_num FROM polizas";
    $st = $this->pdo->query($sql);
    $row = $st->fetch();
    $max = $row['max_num'] ?? 0;
    return (int)$max + 1;
  }

  private function fetchContrato(string $field, int $value): ?array {
    $sql = "SELECT p.*,
                   a.nombre_arrendador,
                   a.direccion_arrendador,
                   a.nacionalidad AS arrendador_nacionalidad,
                   a.tipo_id AS arrendador_tipo_id,
                   a.num_id AS arrendador_num_id,
                   inq.nombre_inquilino,
                   inq.apellidop_inquilino,
                   inq.apellidom_inquilino,
                   inq.nacionalidad AS inquilino_nacionalidad,
                   inq.tipo_id AS inquilino_tipo_id,
                   inq.num_id AS inquilino_num_id,
                   fia.nombre_inquilino AS fiador_nombre,
                   fia.apellidop_inquilino AS fiador_apellidop,
                   fia.apellidom_inquilino AS fiador_apellidom,
                   fia.nacionalidad AS fiador_nacionalidad,
                   fia.tipo_id AS fiador_tipo_id,
                   fia.num_id AS fiador_num_id,
                   obl.nombre_inquilino AS obligado_nombre,
                   obl.apellidop_inquilino AS obligado_apellidop,
                   obl.apellidom_inquilino AS obligado_apellidom,
                   obl.nacionalidad AS obligado_nacionalidad,
                   obl.tipo_id AS obligado_tipo_id,
                   obl.num_id AS obligado_num_id,
                   inm.direccion_inmueble,
                   inm.mantenimiento AS mantenimiento_inmueble,
                   inm.monto_mantenimiento,
                   inm.estacionamiento AS estacionamiento_inmueble,
                   inm.mascotas AS mascotas_inmueble,
                   ase.nombre_asesor
            FROM polizas p
            LEFT JOIN arrendadores a ON p.id_arrendador = a.id
            LEFT JOIN inquilinos inq ON p.id_inquilino = inq.id
            LEFT JOIN inquilinos fia ON p.id_fiador = fia.id
            LEFT JOIN inquilinos obl ON p.id_obligado = obl.id
            LEFT JOIN inmuebles inm ON p.id_inmueble = inm.id
            LEFT JOIN asesores ase ON p.id_asesor = ase.id
            WHERE p.$field = :value
            LIMIT 1";
    $st = $this->pdo->prepare($sql);
    $st->execute([':value' => $value]);
    $row = $st->fetch();
    return $row ?: null;
  }
}
