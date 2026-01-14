<?php
namespace App\Repositories;

use App\Core\Database;

final class UserRepository {
  private \PDO $pdo;

  public function __construct(Database $db) {
    $this->pdo = $db->pdo();
  }

  public function findByEmail(string $email): ?array {
    // Mapeo: email -> mail_usuario
    $sql = "SELECT 
              id, 
              nombre_usuario, 
              apellidos_usuario, 
              usuario, 
              mail_usuario as email, 
              password, 
              tipo_usuario 
            FROM usuarios2 
            WHERE mail_usuario = :email 
            LIMIT 1";
            
    $st = $this->pdo->prepare($sql);
    $st->execute([':email' => $email]);
    $row = $st->fetch();
    
    if ($row) {
      // Mapeo de rol simple basado en tipo_usuario (int)
      // Puedes ajustar esta lógica según tus tipos reales (1=admin, 2=asesor, etc.)
      $row['rol'] = match ($row['tipo_usuario']) {
        1 => 'admin',
        2 => 'asesor',
        default => 'user',
      };
    }

    return $row ?: null;
  }

  public function findById(int $id): ?array {
    $sql = "SELECT 
              id, 
              nombre_usuario, 
              apellidos_usuario, 
              usuario, 
              mail_usuario as email, 
              password, 
              tipo_usuario 
            FROM usuarios2 
            WHERE id = :id 
            LIMIT 1";

    $st = $this->pdo->prepare($sql);
    $st->execute([':id' => $id]);
    $row = $st->fetch();

    if ($row) {
      $row['rol'] = match ($row['tipo_usuario']) {
        1 => 'admin',
        2 => 'asesor',
        default => 'user',
      };
    }

    return $row ?: null;
  }
}
