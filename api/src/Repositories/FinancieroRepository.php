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
}
