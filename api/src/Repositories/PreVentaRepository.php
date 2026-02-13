<?php
namespace App\Repositories;

use App\Core\Database;

final class PreVentaRepository {
  private \PDO $pdo;

  public function __construct(Database $db) {
    $this->pdo = $db->pdo();
  }

  public function findAll(): array {
    $sql = "SELECT
              id_preventa,
              id_poliza,
              numero_poliza,
              id_asesor,
              id_usuario_creador,
              canal_venta,
              concepto_venta,
              monto_venta,
              comision_asesor,
              ganancia_neta,
              estado_preventa,
              observaciones,
              created_at,
              updated_at
            FROM pre_ventas
            ORDER BY created_at DESC";

    $stmt = $this->pdo->query($sql);
    return $stmt->fetchAll();
  }

  public function create(array $data): int {
    $sql = "INSERT INTO pre_ventas (
              id_poliza,
              numero_poliza,
              id_asesor,
              id_usuario_creador,
              canal_venta,
              concepto_venta,
              monto_venta,
              comision_asesor,
              ganancia_neta,
              estado_preventa,
              observaciones
            ) VALUES (
              :id_poliza,
              :numero_poliza,
              :id_asesor,
              :id_usuario_creador,
              :canal_venta,
              :concepto_venta,
              :monto_venta,
              :comision_asesor,
              :ganancia_neta,
              :estado_preventa,
              :observaciones
            )";

    $stmt = $this->pdo->prepare($sql);
    $stmt->execute([
      ':id_poliza' => $data['id_poliza'],
      ':numero_poliza' => $data['numero_poliza'],
      ':id_asesor' => $data['id_asesor'],
      ':id_usuario_creador' => $data['id_usuario_creador'],
      ':canal_venta' => $data['canal_venta'],
      ':concepto_venta' => $data['concepto_venta'],
      ':monto_venta' => $data['monto_venta'],
      ':comision_asesor' => $data['comision_asesor'],
      ':ganancia_neta' => $data['ganancia_neta'],
      ':estado_preventa' => $data['estado_preventa'],
      ':observaciones' => $data['observaciones'],
    ]);

    return (int)$this->pdo->lastInsertId();
  }

  public function findById(int $idPreventa): ?array {
    $sql = "SELECT
              id_preventa,
              id_poliza,
              numero_poliza,
              id_asesor,
              id_usuario_creador,
              canal_venta,
              concepto_venta,
              monto_venta,
              comision_asesor,
              ganancia_neta,
              estado_preventa,
              observaciones,
              created_at,
              updated_at
            FROM pre_ventas
            WHERE id_preventa = :id_preventa
            LIMIT 1";

    $stmt = $this->pdo->prepare($sql);
    $stmt->execute([':id_preventa' => $idPreventa]);

    $row = $stmt->fetch();
    return $row ?: null;
  }

  public function actualizarEstado(int $idPreventa, int $estado): bool {
    $sql = "UPDATE pre_ventas
            SET estado_preventa = :estado_preventa,
                updated_at = NOW()
            WHERE id_preventa = :id_preventa";

    $stmt = $this->pdo->prepare($sql);
    $stmt->execute([
      ':estado_preventa' => $estado,
      ':id_preventa' => $idPreventa,
    ]);

    return $stmt->rowCount() > 0;
  }
}
