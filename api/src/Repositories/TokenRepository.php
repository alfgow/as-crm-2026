<?php
namespace App\Repositories;

use App\Core\Database;

final class TokenRepository {
  private \PDO $pdo;

  public function __construct(Database $db) {
    $this->pdo = $db->pdo();
  }

  // NOTA: La tabla `api_refresh_tokens` existente parece estar ligada a `client_id` (OAuth)
  // y no tiene `user_id`. Para este sistema de autenticación de usuarios,
  // usaremos una nueva tabla específica: `usuarios_refresh_tokens`.
  
  /*
  CREATE TABLE `usuarios_refresh_tokens` (
    `id` bigint unsigned NOT NULL AUTO_INCREMENT,
    `user_id` int NOT NULL,
    `jti` varchar(36) NOT NULL,
    `token_hash` varchar(255) NOT NULL,
    `expires_at` datetime NOT NULL,
    `revoked_at` datetime DEFAULT NULL,
    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_user_jti` (`user_id`, `jti`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
  */

  public function storeRefreshToken(array $data): void {
    $tokenHash = hash('sha256', $data['token']);

    $sql = "INSERT INTO usuarios_refresh_tokens (user_id, jti, token_hash, expires_at, created_at)
            VALUES (:user_id, :jti, :token_hash, :expires_at, NOW())";
    
    $st = $this->pdo->prepare($sql);
    $st->execute([
      ':user_id' => $data['user_id'],
      ':jti' => $data['jti'],
      ':token_hash' => $tokenHash,
      ':expires_at' => $data['expires_at'],
    ]);
  }

  public function isRefreshTokenActive(int $userId, string $jti): bool {
    $sql = "SELECT 1
            FROM usuarios_refresh_tokens
            WHERE user_id = :user_id AND jti = :jti AND revoked_at IS NULL AND expires_at > NOW()
            LIMIT 1";
    $st = $this->pdo->prepare($sql);
    $st->execute([':user_id' => $userId, ':jti' => $jti]);
    return (bool)$st->fetchColumn();
  }

  public function revokeRefreshToken(int $userId, string $jti): void {
    $sql = "UPDATE usuarios_refresh_tokens SET revoked_at = NOW()
            WHERE user_id = :user_id AND jti = :jti AND revoked_at IS NULL";
    $st = $this->pdo->prepare($sql);
    $st->execute([':user_id' => $userId, ':jti' => $jti]);
  }
}
