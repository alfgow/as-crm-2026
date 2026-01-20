<?php
namespace App\Repositories;

use App\Core\Database;

final class ApiRefreshTokenRepository {
  private \PDO $pdo;

  public function __construct(Database $db) {
    $this->pdo = $db->pdo();
  }

  public function create(
    int $clientId,
    string $refreshJti,
    string $refreshTokenHash,
    string $accessJti,
    array $scopes,
    string $expiresAt
  ): void {
    $sql = 'INSERT INTO api_refresh_tokens'
      . ' (client_id, refresh_jti, refresh_token_hash, access_jti, scopes, expires_at, issued_at)'
      . ' VALUES (:client_id, :refresh_jti, :hash, :access_jti, :scopes, :expires_at, NOW())';

    $stmt = $this->pdo->prepare($sql);
    $stmt->execute([
      ':client_id' => $clientId,
      ':refresh_jti' => $refreshJti,
      ':hash' => $refreshTokenHash,
      ':access_jti' => $accessJti,
      ':scopes' => json_encode(array_values($scopes), JSON_UNESCAPED_SLASHES),
      ':expires_at' => $expiresAt,
    ]);
  }

  public function findActiveByJti(string $refreshJti): ?array {
    $sql = 'SELECT * FROM api_refresh_tokens'
      . ' WHERE refresh_jti = :jti AND consumed_at IS NULL AND expires_at > NOW()'
      . ' LIMIT 1';
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute([':jti' => $refreshJti]);
    $row = $stmt->fetch();

    return $row ?: null;
  }

  public function markConsumed(int $id): void {
    $sql = 'UPDATE api_refresh_tokens SET consumed_at = NOW() WHERE id = :id LIMIT 1';
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute([':id' => $id]);
  }

  public function extractScopes(array $row): array {
    $payload = $row['scopes'] ?? '[]';
    $decoded = json_decode((string)$payload, true);
    if (!is_array($decoded)) {
      return [];
    }

    return array_values(array_filter(array_map('strval', $decoded)));
  }
}
