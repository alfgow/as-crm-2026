<?php
namespace App\Repositories;

use App\Core\Database;

final class MediaRepository {
  private \PDO $pdo;

  public function __construct(Database $db) {
    $this->pdo = $db->pdo();
  }

  public function keyExists(string $bucket, string $key): bool {
    $key = trim($key);
    if ($key === '') {
      return false;
    }

    $query = $this->getBucketQuery($bucket, $key);
    if (!$query) {
      return false;
    }

    [$sql, $params] = $query;
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute($params);

    return (bool)$stmt->fetchColumn();
  }

  public function filterValidKeys(array $keys, string $bucket): array {
    $cleanKeys = [];
    foreach ($keys as $key) {
      $key = trim((string)$key);
      if ($key !== '') {
        $cleanKeys[$key] = true;
      }
    }

    if (empty($cleanKeys)) {
      return [];
    }

    $query = $this->getBucketQuery($bucket, array_keys($cleanKeys));
    if (!$query) {
      return [];
    }

    [$sql, $params] = $query;
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute($params);
    $found = $stmt->fetchAll(\PDO::FETCH_COLUMN) ?: [];

    if (empty($found)) {
      return [];
    }

    $foundMap = array_flip($found);
    $ordered = [];
    foreach ($keys as $original) {
      $original = trim((string)$original);
      if ($original !== '' && isset($foundMap[$original])) {
        $ordered[] = $original;
      }
    }

    return $ordered;
  }

  private function getBucketQuery(string $bucket, $key): ?array {
    switch ($bucket) {
      case 'inquilinos':
        $column = 's3_key';
        $table = 'inquilinos_archivos';
        break;
      case 'arrendadores':
        $column = 's3_key';
        $table = 'arrendadores_archivos';
        break;
      case 'blog':
        $column = 'imagen_key';
        $table = 'blog_posts';
        break;
      default:
        return null;
    }

    if (is_array($key)) {
      $placeholders = [];
      $params = [];
      foreach ($key as $idx => $value) {
        $placeholder = ':k' . $idx;
        $placeholders[] = $placeholder;
        $params[$placeholder] = $value;
      }

      if (empty($placeholders)) {
        return null;
      }

      $sql = sprintf(
        'SELECT DISTINCT %s FROM %s WHERE %s IN (%s)',
        $column,
        $table,
        $column,
        implode(', ', $placeholders)
      );

      return [$sql, $params];
    }

    $sql = sprintf(
      'SELECT %s FROM %s WHERE %s = :key LIMIT 1',
      $column,
      $table,
      $column
    );

    return [$sql, [':key' => $key]];
  }
}
