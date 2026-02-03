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

  public function guardarValidacionCheck(int $idInquilino, string $check, int $proceso, array $payload, string $resumen): void {
    $map = $this->mapCheckToColumns($check);
    if ($map === null) {
      return;
    }

    $json = json_encode($payload, JSON_UNESCAPED_UNICODE);

    $stmt = $this->pdo->prepare('SELECT id FROM inquilinos_validaciones WHERE id_inquilino = :id LIMIT 1');
    $stmt->execute([':id' => $idInquilino]);
    $row = $stmt->fetch();

    $columns = sprintf(
      '%s = :proceso, %s = :resumen, %s = :json, updated_at = NOW()',
      $map['proceso'],
      $map['resumen'],
      $map['json']
    );

    if ($row && isset($row['id'])) {
      $sql = "UPDATE inquilinos_validaciones SET $columns WHERE id_inquilino = :id";
      $stmt = $this->pdo->prepare($sql);
      $stmt->execute([
        ':proceso' => $proceso,
        ':resumen' => $resumen,
        ':json' => $json,
        ':id' => $idInquilino,
      ]);
      return;
    }

    $sql = sprintf(
      "INSERT INTO inquilinos_validaciones (id_inquilino, %s, %s, %s)
       VALUES (:id, :proceso, :resumen, :json)",
      $map['proceso'],
      $map['resumen'],
      $map['json']
    );
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute([
      ':id' => $idInquilino,
      ':proceso' => $proceso,
      ':resumen' => $resumen,
      ':json' => $json,
    ]);
  }

  public function guardarValidacionLiveness(int $idInquilino, int $proceso, array $payload, string $resumen): void {
    $stmt = $this->pdo->prepare('SELECT id FROM inquilinos_validaciones WHERE id_inquilino = :id LIMIT 1');
    $stmt->execute([':id' => $idInquilino]);
    $row = $stmt->fetch();

    $json = json_encode($payload, JSON_UNESCAPED_UNICODE);

    if ($row && isset($row['id'])) {
      $sql = "UPDATE inquilinos_validaciones
              SET proceso_validacion_rostro = :proceso,
                  validacion_rostro_resumen = :resumen,
                  validacion_rostro_json = :json,
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
              (id_inquilino, proceso_validacion_rostro, validacion_rostro_resumen, validacion_rostro_json)
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

  private function mapCheckToColumns(string $check): ?array {
    $check = strtolower(trim($check));

    $map = [
      'archivos' => [
        'proceso' => 'proceso_validacion_archivos',
        'resumen' => 'validacion_archivos_resumen',
        'json' => 'validacion_archivos_json',
      ],
      'faces' => [
        'proceso' => 'proceso_validacion_rostro',
        'resumen' => 'validacion_rostro_resumen',
        'json' => 'validacion_rostro_json',
      ],
      'liveness' => [
        'proceso' => 'proceso_validacion_rostro',
        'resumen' => 'validacion_rostro_resumen',
        'json' => 'validacion_rostro_json',
      ],
      'save_face' => [
        'proceso' => 'proceso_validacion_rostro',
        'resumen' => 'validacion_rostro_resumen',
        'json' => 'validacion_rostro_json',
      ],
      'ocr' => [
        'proceso' => 'proceso_validacion_id',
        'resumen' => 'validacion_id_resumen',
        'json' => 'validacion_id_json',
      ],
      'parse' => [
        'proceso' => 'proceso_validacion_id',
        'resumen' => 'validacion_id_resumen',
        'json' => 'validacion_id_json',
      ],
      'nombres' => [
        'proceso' => 'proceso_validacion_id',
        'resumen' => 'validacion_id_resumen',
        'json' => 'validacion_id_json',
      ],
      'kv' => [
        'proceso' => 'proceso_validacion_id',
        'resumen' => 'validacion_id_resumen',
        'json' => 'validacion_id_json',
      ],
      'match' => [
        'proceso' => 'proceso_validacion_id',
        'resumen' => 'validacion_id_resumen',
        'json' => 'validacion_id_json',
      ],
      'save_match' => [
        'proceso' => 'proceso_validacion_id',
        'resumen' => 'validacion_id_resumen',
        'json' => 'validacion_id_json',
      ],
      'ingresos_list' => [
        'proceso' => 'proceso_validacion_ingresos',
        'resumen' => 'validacion_ingresos_resumen',
        'json' => 'validacion_ingresos_json',
      ],
      'ingresos_ocr' => [
        'proceso' => 'proceso_validacion_ingresos',
        'resumen' => 'validacion_ingresos_resumen',
        'json' => 'validacion_ingresos_json',
      ],
      'status' => [
        'proceso' => 'proceso_validacion_documentos',
        'resumen' => 'validacion_documentos_resumen',
        'json' => 'validacion_documentos_json',
      ],
      'resumen_full' => [
        'proceso' => 'proceso_validacion_documentos',
        'resumen' => 'validacion_documentos_resumen',
        'json' => 'validacion_documentos_json',
      ],
      'verificamex' => [
        'proceso' => 'proceso_validacion_verificamex',
        'resumen' => 'verificamex_resumen',
        'json' => 'verificamex_json',
      ],
    ];

    return $map[$check] ?? null;
  }
}
