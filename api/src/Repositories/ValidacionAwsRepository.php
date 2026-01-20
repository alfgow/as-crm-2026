<?php
namespace App\Repositories;

use App\Core\Database;

final class ValidacionAwsRepository {
  private \PDO $pdo;

  public function __construct(Database $db) {
    $this->pdo = $db->pdo();
  }

  public function guardarValidacionMock(int $idInquilino, string $comentario, array $payload): void {
    $stmt = $this->pdo->prepare('SELECT id FROM inquilinos_validaciones WHERE id_inquilino = :id LIMIT 1');
    $stmt->execute([':id' => $idInquilino]);
    $row = $stmt->fetch();

    $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
    $resumen = $payload['comentario'] ?? $comentario;

    if ($row && isset($row['id'])) {
      $sql = "UPDATE inquilinos_validaciones
              SET comentarios = CASE
                  WHEN COALESCE(TRIM(comentarios), '') = '' THEN :comentario
                  ELSE CONCAT(comentarios, '\n', :comentario)
                END,
                proceso_validacion_documentos = :proceso,
                validacion_documentos_resumen = :resumen,
                validacion_documentos_json = :json,
                updated_at = NOW()
              WHERE id_inquilino = :id";
      $stmt = $this->pdo->prepare($sql);
      $stmt->execute([
        ':comentario' => $comentario,
        ':proceso' => 2,
        ':resumen' => $resumen,
        ':json' => $json,
        ':id' => $idInquilino,
      ]);
      return;
    }

    $sql = "INSERT INTO inquilinos_validaciones
              (id_inquilino, comentarios, proceso_validacion_documentos, validacion_documentos_resumen, validacion_documentos_json)
            VALUES
              (:id, :comentario, :proceso, :resumen, :json)";
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute([
      ':id' => $idInquilino,
      ':comentario' => $comentario,
      ':proceso' => 2,
      ':resumen' => $resumen,
      ':json' => $json,
    ]);
  }

  public function guardarValidacionIngresosSimple(int $idInquilino, int $proceso, array $payload, string $resumen): void {
    $stmt = $this->pdo->prepare('SELECT id FROM inquilinos_validaciones WHERE id_inquilino = :id LIMIT 1');
    $stmt->execute([':id' => $idInquilino]);
    $row = $stmt->fetch();

    $json = json_encode($payload, JSON_UNESCAPED_UNICODE);

    if ($row && isset($row['id'])) {
      $sql = "UPDATE inquilinos_validaciones
              SET proceso_validacion_ingresos = :proceso,
                  validacion_ingresos_resumen = :resumen,
                  validacion_ingresos_json = :json,
                  updated_at = NOW()
              WHERE id_inquilino = :id";
      $stmt = $this->pdo->prepare($sql);
      $stmt->execute([
        ':proceso' => $proceso,
        ':resumen' => $resumen,
        ':json' => $json,
        ':id' => $idInquilino,
      ]);
      return;
    }

    $sql = "INSERT INTO inquilinos_validaciones
              (id_inquilino, proceso_validacion_ingresos, validacion_ingresos_resumen, validacion_ingresos_json)
            VALUES
              (:id, :proceso, :resumen, :json)";
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute([
      ':id' => $idInquilino,
      ':proceso' => $proceso,
      ':resumen' => $resumen,
      ':json' => $json,
    ]);
  }
}
