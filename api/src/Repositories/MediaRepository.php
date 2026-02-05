<?php
namespace App\Repositories;

use App\Core\Database;

final class MediaRepository {
  private \PDO $pdo;
  /** @var array<string, array<string, true>> Cache de validación de keys */
  private static array $validationCache = [];
  /** @var int Tiempo de expiración del caché en segundos */
  private const CACHE_TTL = 300; // 5 minutos

  public function __construct(Database $db) {
    $this->pdo = $db->pdo();
  }

  public function keyExists(string $bucket, string $key): bool {
    $key = trim($key);
    if ($key === '') {
      return false;
    }

    // Check cache first
    if (isset(self::$validationCache[$bucket][$key])) {
      return true;
    }

    $query = $this->getBucketQuery($bucket, $key);
    if (!$query) {
      return false;
    }

    [$sql, $params] = $query;
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute($params);
    $exists = (bool)$stmt->fetchColumn();

    // Cache positive results
    if ($exists) {
      self::$validationCache[$bucket][$key] = true;
    }

    return $exists;
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

    // Check cache first
    $cached = self::$validationCache[$bucket] ?? [];
    $cachedKeys = array_intersect_key($cleanKeys, $cached);
    $uncachedKeys = array_diff_key($cleanKeys, $cached);

    // If all keys are cached, return immediately
    if (empty($uncachedKeys)) {
      return array_keys($cachedKeys);
    }

    $query = $this->getBucketQuery($bucket, array_keys($uncachedKeys));
    if (!$query) {
      return array_keys($cachedKeys);
    }

    [$sql, $params] = $query;
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute($params);
    $found = $stmt->fetchAll(\PDO::FETCH_COLUMN) ?: [];

    // Cache found keys
    foreach ($found as $foundKey) {
      self::$validationCache[$bucket][$foundKey] = true;
    }

    if (empty($found)) {
      return array_keys($cachedKeys);
    }

    $foundMap = array_flip($found);
    $ordered = [];
    foreach ($keys as $original) {
      $original = trim((string)$original);
      if ($original !== '' && (isset($foundMap[$original]) || isset($cached[$original]))) {
        $ordered[] = $original;
      }
    }

    return $ordered;
  }

  /**
   * Validate keys in batches to avoid large IN clauses
   * Returns only the keys that exist in the database
   *
   * @param array $keys Keys to validate
   * @param string $bucket Bucket name
   * @param int $batchSize Maximum keys per query
   * @return array Valid keys
   */
  public function filterValidKeysBatched(array $keys, string $bucket, int $batchSize = 100): array {
    $cleanKeys = [];
    foreach ($keys as $key) {
      $key = trim((string)$key);
      if ($key !== '') {
        $cleanKeys[] = $key;
      }
    }

    if (empty($cleanKeys)) {
      return [];
    }

    $validKeys = [];
    $batches = array_chunk(array_unique($cleanKeys), $batchSize);

    foreach ($batches as $batch) {
      $batchValid = $this->filterValidKeys($batch, $bucket);
      $validKeys = array_merge($validKeys, $batchValid);
    }

    // Maintain original order
    $validMap = array_flip($validKeys);
    $ordered = [];
    foreach ($keys as $original) {
      $original = trim((string)$original);
      if ($original !== '' && isset($validMap[$original])) {
        $ordered[] = $original;
      }
    }

    return $ordered;
  }

  /**
   * Clear the validation cache for a specific bucket or all buckets
   */
  public static function clearCache(?string $bucket = null): void {
    if ($bucket === null) {
      self::$validationCache = [];
    } else {
      unset(self::$validationCache[$bucket]);
    }
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
