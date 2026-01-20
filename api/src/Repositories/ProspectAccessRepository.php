<?php
namespace App\Repositories;

use App\Core\Database;

final class ProspectAccessRepository {
  private \PDO $pdo;

  public function __construct(Database $db) {
    $this->pdo = $db->pdo();
  }

  public function resolveActorByEmail(string $email, ?string $hint = null): ?array {
    $email = strtolower(trim($email));

    if ($hint === 'inquilino' || $hint === 'arrendador') {
      $row = $this->findByEmail($email, $hint);
      if ($row) {
        return [$hint, (int)$row['id'], (string)$row['nombre']];
      }
    } else {
      $row = $this->findByEmail($email, 'inquilino');
      if ($row) {
        return ['inquilino', (int)$row['id'], (string)$row['nombre']];
      }

      $row = $this->findByEmail($email, 'arrendador');
      if ($row) {
        return ['arrendador', (int)$row['id'], (string)$row['nombre']];
      }
    }

    return null;
  }

  public function insertToken(array $row): int {
    $sql = "INSERT INTO prospect_update_tokens
            (actor_type, actor_id, email, jti, otp, otp_hash, token_hash, scope, expires_at)
            VALUES (:actor_type, :actor_id, :email, :jti, :otp, :otp_hash, :token_hash, :scope, :expires_at)";
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute([
      ':actor_type' => $row['actor_type'],
      ':actor_id' => $row['actor_id'],
      ':email' => $row['email'],
      ':jti' => $row['jti'],
      ':otp' => $row['otp'],
      ':otp_hash' => $row['otp_hash'],
      ':token_hash' => $row['token_hash'],
      ':scope' => $row['scope'],
      ':expires_at' => $row['expires_at'],
    ]);

    return (int)$this->pdo->lastInsertId();
  }

  private function findByEmail(string $email, string $actorType): ?array {
    if ($actorType === 'inquilino') {
      $stmt = $this->pdo->prepare(
        "SELECT id,
                CONCAT_WS(' ', nombre_inquilino, apellidop_inquilino, apellidom_inquilino) AS nombre
         FROM inquilinos
         WHERE email = :email
         LIMIT 1"
      );
    } else {
      $stmt = $this->pdo->prepare(
        "SELECT id, nombre_arrendador AS nombre
         FROM arrendadores
         WHERE email = :email
         LIMIT 1"
      );
    }

    $stmt->execute([':email' => $email]);
    $row = $stmt->fetch();

    return $row ?: null;
  }
}
