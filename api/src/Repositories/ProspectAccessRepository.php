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
            (actor_type, actor_id, email, jti, magic_token_hash, otp, otp_hash, token_hash, scope, expires_at)
            VALUES (:actor_type, :actor_id, :email, :jti, :magic_token_hash, :otp, :otp_hash, :token_hash, :scope, :expires_at)";
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute([
      ':actor_type' => $row['actor_type'],
      ':actor_id' => $row['actor_id'],
      ':email' => $row['email'],
      ':jti' => $row['jti'],
      ':magic_token_hash' => $row['magic_token_hash'] ?? null,
      ':otp' => $row['otp'],
      ':otp_hash' => $row['otp_hash'],
      ':token_hash' => $row['token_hash'],
      ':scope' => $row['scope'],
      ':expires_at' => $row['expires_at'],
    ]);

    return (int)$this->pdo->lastInsertId();
  }

  public function insertIdentityToken(array $row): int {
    $sql = "INSERT INTO prospect_update_tokens
            (actor_type, actor_id, email, jti, otp, otp_hash, token_hash, scope, expires_at)
            VALUES (:actor_type, :actor_id, :email, :jti, NULL, NULL, :token_hash, :scope, :expires_at)";
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute([
      ':actor_type' => $row['actor_type'],
      ':actor_id' => $row['actor_id'],
      ':email' => $row['email'],
      ':jti' => $row['jti'],
      ':token_hash' => $row['token_hash'],
      ':scope' => $row['scope'],
      ':expires_at' => $row['expires_at'],
    ]);

    return (int)$this->pdo->lastInsertId();
  }

  public function findIdentityTokenByHash(string $tokenHash): ?array {
    $sql = "SELECT actor_type, actor_id, email, scope, expires_at
            FROM prospect_update_tokens
            WHERE token_hash = :token_hash
              AND scope = 'identity:validation'
              AND expires_at > NOW()
            LIMIT 1";
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute([':token_hash' => $tokenHash]);
    $row = $stmt->fetch();

    return $row ?: null;
  }

  public function consumeMagicToken(string $tokenHash, ?string $ip = null, ?string $userAgent = null): ?array {
    try {
      $this->pdo->beginTransaction();

      $sql = "SELECT id, actor_type, actor_id, email, scope, expires_at
              FROM prospect_update_tokens
              WHERE magic_token_hash = :magic_token_hash
                AND scope = 'self:update'
                AND expires_at > NOW()
                AND consumed_at IS NULL
              LIMIT 1
              FOR UPDATE";
      $stmt = $this->pdo->prepare($sql);
      $stmt->execute([':magic_token_hash' => $tokenHash]);
      $row = $stmt->fetch();

      if (!$row) {
        $this->pdo->rollBack();
        return null;
      }

      $upd = $this->pdo->prepare(
        "UPDATE prospect_update_tokens
         SET consumed_at = NOW(),
             consumed_ip = :consumed_ip,
             consumed_user_agent = :consumed_user_agent
         WHERE id = :id"
      );
      $upd->execute([
        ':consumed_ip' => $ip,
        ':consumed_user_agent' => $userAgent !== null ? substr($userAgent, 0, 255) : null,
        ':id' => $row['id'],
      ]);

      $this->pdo->commit();

      $row['consumed_at'] = (new \DateTimeImmutable('now'))->format('Y-m-d H:i:s');
      return $row;
    } catch (\Throwable $e) {
      if ($this->pdo->inTransaction()) {
        $this->pdo->rollBack();
      }
      throw $e;
    }
  }

  public function findSelfieS3Key(string $actorType, int $actorId): ?string {
    if ($actorId <= 0) {
      return null;
    }

    if ($actorType === 'inquilino') {
      $sql = "SELECT s3_key
              FROM inquilinos_archivos
              WHERE id_inquilino = :actor_id
                AND tipo = 'selfie'
              ORDER BY id DESC
              LIMIT 1";
    } elseif ($actorType === 'arrendador') {
      $sql = "SELECT s3_key
              FROM arrendadores_archivos
              WHERE id_arrendador = :actor_id
                AND tipo = 'selfie'
              ORDER BY id DESC
              LIMIT 1";
    } else {
      return null;
    }

    $stmt = $this->pdo->prepare($sql);
    $stmt->execute([':actor_id' => $actorId]);
    $row = $stmt->fetch();

    if (!$row) {
      return null;
    }

    $key = trim((string)($row['s3_key'] ?? ''));
    return $key !== '' ? $key : null;
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
