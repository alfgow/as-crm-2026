<?php
namespace App\Repositories;

use App\Core\Database;

final class AsesorRepository {
  private \PDO $pdo;

  public function __construct(Database $db) {
    $this->pdo = $db->pdo();
  }

  public function findAll(): array {
    $sql = "SELECT * FROM asesores ORDER BY id DESC";
    $st = $this->pdo->query($sql);
    return $st->fetchAll();
  }

  public function findById(int $id): ?array {
    $sql = "SELECT * FROM asesores WHERE id = :id LIMIT 1";
    $st = $this->pdo->prepare($sql);
    $st->execute([':id' => $id]);
    $row = $st->fetch();
    return $row ?: null;
  }

  public function create(array $data): int {
    // schema: nombre_asesor, email, celular, telefono
    $sql = "INSERT INTO asesores (nombre_asesor, email, celular, telefono) 
            VALUES (:nombre, :email, :celular, :telefono)";
    
    $st = $this->pdo->prepare($sql);
    $st->execute([
      ':nombre'   => $data['nombre_asesor'],
      ':email'    => $data['email'],
      ':celular'  => $data['celular'],
      ':telefono' => $data['telefono'] ?? '', // opcional si viene null
    ]);

    return (int)$this->pdo->lastInsertId();
  }

  public function update(int $id, array $data): void {
    $fields = [];
    $params = [':id' => $id];

    if (isset($data['nombre_asesor'])) {
      $fields[] = "nombre_asesor = :nombre";
      $params[':nombre'] = $data['nombre_asesor'];
    }
    if (isset($data['email'])) {
      $fields[] = "email = :email";
      $params[':email'] = $data['email'];
    }
    if (isset($data['celular'])) {
      $fields[] = "celular = :celular";
      $params[':celular'] = $data['celular'];
    }
    if (isset($data['telefono'])) {
      $fields[] = "telefono = :telefono";
      $params[':telefono'] = $data['telefono'];
    }

    if (empty($fields)) return;

    $sql = "UPDATE asesores SET " . implode(', ', $fields) . " WHERE id = :id";
    $this->pdo->prepare($sql)->execute($params);
  }

  public function delete(int $id): void {
    $sql = "DELETE FROM asesores WHERE id = :id";
    $this->pdo->prepare($sql)->execute([':id' => $id]);
  }

  public function deleteBulk(array $ids): int {
    if (empty($ids)) {
      return 0;
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $sql = "DELETE FROM asesores WHERE id IN ($placeholders)";
    $st = $this->pdo->prepare($sql);
    $st->execute($ids);

    return $st->rowCount();
  }
}
