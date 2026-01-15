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
  public function findAll(): array {
    $sql = "SELECT 
              id, 
              nombre_usuario, 
              apellidos_usuario, 
              usuario, 
              mail_usuario as email, 
              tipo_usuario,
              corto_usuario
            FROM usuarios2 
            ORDER BY id DESC";

    $st = $this->pdo->query($sql);
    $rows = $st->fetchAll();

    return array_map(function($row) {
      $row['rol'] = match ($row['tipo_usuario']) {
        1 => 'admin',
        2 => 'asesor',
        default => 'user',
      };
      return $row;
    }, $rows);
  }

  public function create(array $data): int {
    $sql = "INSERT INTO usuarios2 (
              nombre_usuario, 
              apellidos_usuario, 
              usuario, 
              mail_usuario, 
              password, 
              tipo_usuario,
              corto_usuario
            ) VALUES (
              :nombre, 
              :apellidos, 
              :usuario, 
              :email, 
              :password, 
              :tipo,
              :corto
            )";

    $st = $this->pdo->prepare($sql);
    $st->execute([
      ':nombre'    => $data['nombre_usuario'],
      ':apellidos' => $data['apellidos_usuario'],
      ':usuario'   => $data['usuario'],
      ':email'     => $data['email'],
      ':password'  => password_hash($data['password'], PASSWORD_BCRYPT),
      ':tipo'      => $data['tipo_usuario'] ?? 2,
      ':corto'     => $data['corto_usuario'] ?? substr($data['nombre_usuario'], 0, 2),
    ]);

    return (int)$this->pdo->lastInsertId();
  }

  public function update(int $id, array $data): void {
    $fields = [];
    $params = [':id' => $id];

    if (isset($data['nombre_usuario'])) {
      $fields[] = "nombre_usuario = :nombre";
      $params[':nombre'] = $data['nombre_usuario'];
    }
    if (isset($data['apellidos_usuario'])) {
      $fields[] = "apellidos_usuario = :apellidos";
      $params[':apellidos'] = $data['apellidos_usuario'];
    }
    if (isset($data['usuario'])) {
      $fields[] = "usuario = :usuario";
      $params[':usuario'] = $data['usuario'];
    }
    if (isset($data['email'])) {
      $fields[] = "mail_usuario = :email";
      $params[':email'] = $data['email'];
    }
    if (isset($data['password']) && !empty($data['password'])) {
      $fields[] = "password = :password";
      $params[':password'] = password_hash($data['password'], PASSWORD_BCRYPT);
    }
    if (isset($data['tipo_usuario'])) {
      $fields[] = "tipo_usuario = :tipo";
      $params[':tipo'] = $data['tipo_usuario'];
    }
    if (isset($data['corto_usuario'])) {
      $fields[] = "corto_usuario = :corto";
      $params[':corto'] = $data['corto_usuario'];
    }

    if (empty($fields)) return;

    $sql = "UPDATE usuarios2 SET " . implode(', ', $fields) . " WHERE id = :id";
    $this->pdo->prepare($sql)->execute($params);
  }

  public function delete(int $id): void {
    $sql = "DELETE FROM usuarios2 WHERE id = :id";
    $this->pdo->prepare($sql)->execute([':id' => $id]);
  }
}
