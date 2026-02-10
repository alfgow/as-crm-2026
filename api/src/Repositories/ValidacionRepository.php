<?php
namespace App\Repositories;

use App\Core\Database;

final class ValidacionRepository {
  private \PDO $pdo;

  public function __construct(Database $db) {
    $this->pdo = $db->pdo();
  }

  // Obtiene el registro de validaciones de un inquilino
  public function findByInquilinoId(int $idInquilino): ?array {
    $sql = "SELECT * FROM inquilinos_validaciones WHERE id_inquilino = :id LIMIT 1";
    $st = $this->pdo->prepare($sql);
    $st->execute([':id' => $idInquilino]);
    $row = $st->fetch();
    return $row ?: null;
  }

  // Crea o Actualiza el registro de validaciones
  public function upsert(int $idInquilino, array $data): array {
    // Campos permitidos
    $fields = [
      'proceso_validacion_documentos', 'validacion_documentos_resumen', 'validacion_documentos_json',
      'proceso_validacion_archivos', 'validacion_archivos_resumen', 'validacion_archivos_json',
      'proceso_validacion_rostro', 'validacion_rostro_resumen', 'validacion_rostro_json',
      'liveness_process',
      'proceso_validacion_id', 'validacion_id_resumen', 'validacion_id_json',
      'proceso_validacion_ingresos', 'validacion_ingresos_resumen', 'validacion_ingresos_json',
      'proceso_pago_inicial', 'pago_inicial_resumen', 'pago_inicial_json',
      'proceso_inv_demandas', 'inv_demandas_resumen', 'inv_demandas_json', 
      'proceso_validacion_verificamex', 'verificamex_resumen', 'verificamex_json',
      'comentarios'
    ];

    // Verificar si existe
    $exists = $this->findByInquilinoId($idInquilino);

    if ($exists) {
        $set = [];
        $values = [':id_inquilino' => $idInquilino];
        foreach ($fields as $f) {
            if (array_key_exists($f, $data)) {
                $set[] = "$f = :$f";
                // Handle JSON inputs if array
                $val = $data[$f];
                if (is_array($val) && (strpos($f, '_json') !== false || $f === 'liveness_process')) {
                    $val = json_encode($val);
                }
                $values[":$f"] = $val;
            }
        }
        
        if (!empty($set)) {
            $sql = "UPDATE inquilinos_validaciones SET " . implode(', ', $set) . ", updated_at = NOW() WHERE id_inquilino = :id_inquilino";
            $this->pdo->prepare($sql)->execute($values);
        }
    } else {
        $cols = ['id_inquilino', 'created_at', 'updated_at'];
        $vals = [':id_inquilino' => $idInquilino];
        
        foreach ($fields as $f) {
            if (array_key_exists($f, $data)) {
                $cols[] = $f;
                // Handle JSON
                $val = $data[$f];
                if (is_array($val) && (strpos($f, '_json') !== false || $f === 'liveness_process')) {
                    $val = json_encode($val);
                }
                $vals[":$f"] = $val;
            }
        }
        
        // Prepare placeholders
        $placeholders = [];
        foreach ($cols as $c) {
            if ($c === 'created_at' || $c === 'updated_at') {
                $placeholders[] = 'NOW()';
            } else {
                $placeholders[] = ":$c";
            }
        }
        
        $sql = "INSERT INTO inquilinos_validaciones (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $placeholders) . ")";
        $this->pdo->prepare($sql)->execute($vals);
    }
    
    return $this->findByInquilinoId($idInquilino);
  }
}
