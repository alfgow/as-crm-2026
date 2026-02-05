<?php
namespace App\Repositories;

use App\Core\Database;

final class ApiClientRepository {
  private \PDO $pdo;

  public function __construct(Database $db) {
    $this->pdo = $db->pdo();
  }

  public function findAll(): array {
    $sql = "SELECT id, client_id, name, allowed_scopes, status, rate_limit_per_minute, refresh_ttl_seconds, last_used_at, created_at, updated_at
            FROM api_clients
            ORDER BY created_at DESC";
    $st = $this->pdo->query($sql);
    $rows = $st->fetchAll();

    return array_map(function (array $row): array {
      $row['allowed_scopes'] = $this->decodeScopes($row['allowed_scopes'] ?? '[]');
      return $row;
    }, $rows);
  }

  public function findById(int $id): ?array {
    $sql = "SELECT * FROM api_clients WHERE id = :id LIMIT 1";
    $st = $this->pdo->prepare($sql);
    $st->execute([':id' => $id]);
    $row = $st->fetch();

    if (!$row) {
      return null;
    }

    $row['allowed_scopes'] = $this->decodeScopes($row['allowed_scopes'] ?? '[]');
    return $row;
  }

  public function findByClientId(string $clientId): ?array {
    $sql = "SELECT * FROM api_clients WHERE client_id = :client_id LIMIT 1";
    $st = $this->pdo->prepare($sql);
    $st->execute([':client_id' => $clientId]);
    $row = $st->fetch();

    if (!$row) {
      return null;
    }

    $row['allowed_scopes'] = $this->decodeScopes($row['allowed_scopes'] ?? '[]');
    return $row;
  }

  public function touchLastUsed(int $id): void {
    $sql = "UPDATE api_clients SET last_used_at = NOW(), updated_at = NOW() WHERE id = :id LIMIT 1";
    $st = $this->pdo->prepare($sql);
    $st->execute([':id' => $id]);
  }

  public function createClient(
    string $name,
    array $scopes,
    int $rateLimitPerMinute = 60,
    string $status = 'active',
    ?int $refreshTtlSeconds = null
  ): array {
    $name = trim($name);
    if ($name === '') {
      throw new \RuntimeException('El nombre del cliente es obligatorio.');
    }

    if (empty($scopes)) {
      throw new \RuntimeException('Selecciona al menos un scope.');
    }

    if (!in_array($status, ['active', 'suspended', 'revoked'], true)) {
      throw new \RuntimeException('Estado inválido.');
    }

    $clientId = $this->generateUniqueClientId();
    $clientSecret = $this->generateSecret();
    $secretHash = password_hash($clientSecret, PASSWORD_DEFAULT);

    $sql = "INSERT INTO api_clients (client_id, name, secret_hash, allowed_scopes, status, rate_limit_per_minute, refresh_ttl_seconds, created_at, updated_at)
            VALUES (:client_id, :name, :secret_hash, :scopes, :status, :rate_limit, :refresh_ttl_seconds, NOW(), NOW())";
    $st = $this->pdo->prepare($sql);
    $st->execute([
      ':client_id' => $clientId,
      ':name' => $name,
      ':secret_hash' => $secretHash,
      ':scopes' => json_encode(array_values($scopes), JSON_UNESCAPED_SLASHES),
      ':status' => $status,
      ':rate_limit' => max(1, $rateLimitPerMinute),
      ':refresh_ttl_seconds' => $refreshTtlSeconds,
    ]);

    return [
      'client_id' => $clientId,
      'client_secret' => $clientSecret,
      'name' => $name,
      'scopes' => array_values($scopes),
      'refresh_ttl_seconds' => $refreshTtlSeconds,
    ];
  }

  public function rotateSecret(int $id): array {
    $client = $this->findById($id);
    if (!$client) {
      throw new \RuntimeException('Cliente no encontrado.');
    }

    $secret = $this->generateSecret();
    $secretHash = password_hash($secret, PASSWORD_DEFAULT);

    $sql = "UPDATE api_clients SET secret_hash = :secret, updated_at = NOW() WHERE id = :id LIMIT 1";
    $st = $this->pdo->prepare($sql);
    $st->execute([':secret' => $secretHash, ':id' => $id]);

    return [
      'client_id' => $client['client_id'],
      'client_secret' => $secret,
    ];
  }

  public function revoke(int $id): void {
    $sql = "UPDATE api_clients SET status = 'revoked', updated_at = NOW() WHERE id = :id LIMIT 1";
    $st = $this->pdo->prepare($sql);
    $st->execute([':id' => $id]);
  }

  private function decodeScopes(string $payload): array {
    $decoded = json_decode($payload, true);
    if (!is_array($decoded)) {
      return [];
    }

    return array_values($decoded);
  }

  private function generateUniqueClientId(): string {
    do {
      $candidate = 'cli_' . bin2hex(random_bytes(8));
      $exists = $this->findIdByClientId($candidate);
    } while ($exists !== null);

    return $candidate;
  }

  private function findIdByClientId(string $clientId): ?array {
    $sql = "SELECT id FROM api_clients WHERE client_id = :client_id LIMIT 1";
    $st = $this->pdo->prepare($sql);
    $st->execute([':client_id' => $clientId]);
    $row = $st->fetch();
    return $row ?: null;
  }

  private function generateSecret(): string {
    return bin2hex(random_bytes(32));
  }
}
