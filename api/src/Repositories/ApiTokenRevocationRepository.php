<?php
namespace App\Repositories;

use App\Core\Database;

final class ApiTokenRevocationRepository {
  private \PDO $pdo;

  public function __construct(Database $db) {
    $this->pdo = $db->pdo();
  }

  public function isRevoked(string $jti): bool {
    $sql = 'SELECT 1 FROM api_token_revocations WHERE jti = :jti AND expires_at > NOW() LIMIT 1';
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute([':jti' => $jti]);

    return (bool)$stmt->fetchColumn();
  }

  public function revoke(string $jti, string $tokenType, ?string $reason, ?string $revokedBy, string $expiresAt): void {
    $sql = 'INSERT INTO api_token_revocations (jti, token_type, reason, revoked_by, revoked_at, expires_at)'
      . ' VALUES (:jti, :token_type, :reason, :revoked_by, NOW(), :expires_at)'
      . ' ON DUPLICATE KEY UPDATE reason = VALUES(reason), revoked_by = VALUES(revoked_by), revoked_at = NOW(), expires_at = VALUES(expires_at)';

    $stmt = $this->pdo->prepare($sql);
    $stmt->execute([
      ':jti' => $jti,
      ':token_type' => $tokenType,
      ':reason' => $reason,
      ':revoked_by' => $revokedBy,
      ':expires_at' => $expiresAt,
    ]);
  }
}
