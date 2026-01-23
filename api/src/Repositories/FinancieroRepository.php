<?php
namespace App\Repositories;

use App\Core\Database;

final class FinancieroRepository {
  private \PDO $pdo;

  public function __construct(Database $db) {
    $this->pdo = $db->pdo();
  }

  public function obtenerResumenPorAnioMes(int $anio, int $mes): array {
    $sql = "SELECT
              COALESCE(SUM(ganancia_neta),0) AS total_mes,
              COUNT(*) AS polizas_mes
            FROM ventasvillanuevagarcia
            WHERE YEAR(fecha_venta) = :y
              AND MONTH(fecha_venta) = :m";
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute([':y' => $anio, ':m' => $mes]);
    $row = $stmt->fetch() ?: ['total_mes' => 0, 'polizas_mes' => 0];
    return [
      'total_mes' => (float)$row['total_mes'],
      'polizas_mes' => (int)$row['polizas_mes'],
    ];
  }

  public function obtenerIngresosAnuales(string $anio): float {
    $sql = "SELECT COALESCE(SUM(ganancia_neta),0) AS total_anual
            FROM ventasvillanuevagarcia
            WHERE YEAR(fecha_venta) = :y";
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute([':y' => $anio]);
    $row = $stmt->fetch() ?: ['total_anual' => 0];
    return (float)$row['total_anual'];
  }

  public function obtenerIngresosPorMes(string $anio): array {
    $sql = "SELECT
              MONTH(fecha_venta) AS mes,
              COALESCE(SUM(monto_venta),0) AS total,
              COALESCE(SUM(ganancia_neta),0) AS neto
            FROM ventasvillanuevagarcia
            WHERE YEAR(fecha_venta) = :y
            GROUP BY MONTH(fecha_venta)
            ORDER BY MONTH(fecha_venta)";
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute([':y' => $anio]);
    return $stmt->fetchAll();
  }

  public function obtenerSumatoriasPorCanalAnioMes(int $anio, int $mes): array {
    $sql = "SELECT
              COALESCE(SUM(CASE WHEN canal_venta = 'Arrendamiento Seguro'
                THEN ganancia_neta ELSE 0 END),0) AS total_arrendamiento,
              COALESCE(SUM(CASE WHEN canal_venta <> 'Arrendamiento Seguro'
                THEN ganancia_neta ELSE 0 END),0) AS total_inmobiliaria
            FROM ventasvillanuevagarcia
            WHERE YEAR(fecha_venta) = :y
              AND MONTH(fecha_venta) = :m";
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute([':y' => $anio, ':m' => $mes]);
    $row = $stmt->fetch() ?: ['total_arrendamiento' => 0, 'total_inmobiliaria' => 0];
    return [
      'total_arrendamiento' => (float)$row['total_arrendamiento'],
      'total_inmobiliaria' => (float)$row['total_inmobiliaria'],
    ];
  }

  public function listarVentasPorAnioMes(int $anio, int $mes): array {
    $sql = "SELECT
              id_venta,
              fecha_venta,
              canal_venta,
              concepto_venta,
              monto_venta,
              comision_asesor,
              ganancia_neta
            FROM ventasvillanuevagarcia
            WHERE YEAR(fecha_venta) = :y
              AND MONTH(fecha_venta) = :m
            ORDER BY fecha_venta DESC";
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute([':y' => $anio, ':m' => $mes]);
    return $stmt->fetchAll();
  }

  public function listarVentasPorPeriodo(string $inicio, string $fin): array {
    $sql = "SELECT
              id_venta,
              DATE(fecha_venta) AS fecha,
              fecha_venta,
              canal_venta,
              concepto_venta,
              monto_venta,
              comision_asesor,
              ganancia_neta
            FROM ventasvillanuevagarcia
            WHERE DATE(fecha_venta) BETWEEN :inicio AND :fin
            ORDER BY fecha_venta DESC";
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute([':inicio' => $inicio, ':fin' => $fin]);
    return $stmt->fetchAll();
  }

  public function obtenerSumatoriasPorCanalRango(string $inicio, string $fin): array {
    $sql = "SELECT
              COALESCE(SUM(CASE WHEN canal_venta = 'Arrendamiento Seguro'
                THEN ganancia_neta ELSE 0 END),0) AS total_arrendamiento,
              COALESCE(SUM(CASE WHEN canal_venta <> 'Arrendamiento Seguro'
                THEN ganancia_neta ELSE 0 END),0) AS total_inmobiliaria
            FROM ventasvillanuevagarcia
            WHERE DATE(fecha_venta) BETWEEN :inicio AND :fin";
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute([':inicio' => $inicio, ':fin' => $fin]);
    $row = $stmt->fetch() ?: ['total_arrendamiento' => 0, 'total_inmobiliaria' => 0];
    return [
      'total_arrendamiento' => (float)$row['total_arrendamiento'],
      'total_inmobiliaria' => (float)$row['total_inmobiliaria'],
    ];
  }

  public function crearVenta(array $data): int {
    $sql = "INSERT INTO ventasvillanuevagarcia (
              fecha_venta,
              canal_venta,
              concepto_venta,
              monto_venta,
              comision_asesor,
              ganancia_neta
            ) VALUES (
              :fecha_venta,
              :canal_venta,
              :concepto_venta,
              :monto_venta,
              :comision_asesor,
              :ganancia_neta
            )";
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute([
      ':fecha_venta' => $data['fecha_venta'],
      ':canal_venta' => $data['canal_venta'],
      ':concepto_venta' => $data['concepto_venta'],
      ':monto_venta' => $data['monto_venta'],
      ':comision_asesor' => $data['comision_asesor'],
      ':ganancia_neta' => $data['ganancia_neta'],
    ]);

    return (int)$this->pdo->lastInsertId();
  }

  public function buscarVentaPorId(int $id): ?array {
    $sql = "SELECT
              id_venta,
              fecha_venta,
              canal_venta,
              concepto_venta,
              monto_venta,
              comision_asesor,
              ganancia_neta
            FROM ventasvillanuevagarcia
            WHERE id_venta = :id
            LIMIT 1";
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    return $row ?: null;
  }

  public function actualizarVenta(int $id, array $data): bool {
    $fields = [
      'fecha_venta',
      'canal_venta',
      'concepto_venta',
      'monto_venta',
      'comision_asesor',
      'ganancia_neta',
    ];

    $set = [];
    $values = [':id' => $id];
    foreach ($fields as $field) {
      if (array_key_exists($field, $data)) {
        $set[] = "$field = :$field";
        $values[":$field"] = $data[$field];
      }
    }

    if (!$set) {
      return false;
    }

    $sql = "UPDATE ventasvillanuevagarcia SET " . implode(', ', $set) . " WHERE id_venta = :id";
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute($values);
    return $stmt->rowCount() > 0;
  }

  public function eliminarVenta(int $id): bool {
    $sql = "DELETE FROM ventasvillanuevagarcia WHERE id_venta = :id";
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute([':id' => $id]);
    return $stmt->rowCount() > 0;
  }
}
