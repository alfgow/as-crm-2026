<?php
namespace App\Services;

final class MediaPresignService {
  private array $config;

  public function __construct(array $config) {
    $this->config = $config;
  }

  public function buildPresignedUrl(string $bucket, string $key): ?string {
    $base = rtrim($this->config['media']['presign_base_url'] ?? '', '/');
    if ($base !== '') {
      $encodedKey = $this->encodeKey($key);
      $encodedBucket = rawurlencode($bucket);
      return $base . '/' . $encodedBucket . '/' . $encodedKey;
    }

    return $this->buildS3PresignedUrl($bucket, $key, 'GET');
  }

  public function buildPresignedPutUrl(string $bucket, string $key): ?string {
    return $this->buildS3PresignedUrl($bucket, $key, 'PUT');
  }

  public function buildPresignedDeleteUrl(string $bucket, string $key): ?string {
    return $this->buildS3PresignedUrl($bucket, $key, 'DELETE');
  }

  private function buildS3PresignedUrl(string $bucket, string $key, string $method): ?string {
    $mediaConfig = $this->config['media'] ?? [];
    $s3Config = $mediaConfig['s3'] ?? [];

    $accessKey = (string)($s3Config['access_key'] ?? '');
    $secretKey = (string)($s3Config['secret_key'] ?? '');
    $region = (string)($s3Config['region'] ?? '');
    $copyRegion = trim((string)($s3Config['copy_region'] ?? ''));
    $endpoint = (string)($s3Config['endpoint'] ?? '');
    $sessionToken = (string)($s3Config['session_token'] ?? '');
    $expires = (int)($mediaConfig['presign_expires_seconds'] ?? 900);

    if ($accessKey === '' || $secretKey === '' || $region === '') {
      return null;
    }

    if ($copyRegion !== '') {
      $copyBucket = trim((string)($this->config['aws']['rekognition']['copy_bucket'] ?? ''));
      if ($copyBucket !== '') {
        $resolvedBucket = $this->resolveBucketName($bucket);
        $resolvedCopyBucket = $this->resolveBucketName($copyBucket);
        if ($resolvedBucket !== '' && $resolvedBucket === $resolvedCopyBucket) {
          $region = $copyRegion;
        }
      }
    }

    $bucketName = $this->resolveBucketName($bucket);
    if ($bucketName === '') {
      return null;
    }

    $endpointInfo = $this->resolveEndpoint($endpoint, $region);
    $scheme = $endpointInfo['scheme'];
    $host = $bucketName . '.' . $endpointInfo['host'];
    $basePath = $endpointInfo['path'];

    $encodedKey = $this->encodeKey($key);
    $canonicalUri = $this->buildCanonicalUri($basePath, $encodedKey);

    $amzDate = gmdate('Ymd\THis\Z');
    $dateStamp = gmdate('Ymd');
    $credentialScope = $dateStamp . '/' . $region . '/s3/aws4_request';
    $credential = $accessKey . '/' . $credentialScope;

    $queryParams = [
      'X-Amz-Algorithm' => 'AWS4-HMAC-SHA256',
      'X-Amz-Credential' => $credential,
      'X-Amz-Date' => $amzDate,
      'X-Amz-Expires' => (string)max(1, $expires),
      'X-Amz-SignedHeaders' => 'host',
    ];

    if ($sessionToken !== '') {
      $queryParams['X-Amz-Security-Token'] = $sessionToken;
    }

    $canonicalQuery = $this->buildCanonicalQuery($queryParams);
    $canonicalHeaders = 'host:' . $host . "\n";
    $signedHeaders = 'host';
    $payloadHash = 'UNSIGNED-PAYLOAD';

    $method = strtoupper(trim($method)) ?: 'GET';
    $canonicalRequest = implode("\n", [
      $method,
      $canonicalUri,
      $canonicalQuery,
      $canonicalHeaders,
      $signedHeaders,
      $payloadHash,
    ]);

    $stringToSign = implode("\n", [
      'AWS4-HMAC-SHA256',
      $amzDate,
      $credentialScope,
      hash('sha256', $canonicalRequest),
    ]);

    $signingKey = $this->getSigningKey($secretKey, $dateStamp, $region, 's3');
    $signature = hash_hmac('sha256', $stringToSign, $signingKey);

    $queryParams['X-Amz-Signature'] = $signature;
    $finalQuery = $this->buildQuery($queryParams);

    return sprintf('%s://%s%s?%s', $scheme, $host, $canonicalUri, $finalQuery);
  }

  private function resolveBucketName(string $bucket): string {
    $buckets = $this->config['media']['s3']['buckets'] ?? [];
    $mapped = trim((string)($buckets[$bucket] ?? ''));
    if ($mapped !== '') {
      return $mapped;
    }

    return trim($bucket);
  }

  private function resolveEndpoint(string $endpoint, string $region): array {
    $scheme = 'https';
    $host = '';
    $path = '';

    if ($endpoint !== '') {
      if (str_contains($endpoint, '://')) {
        $parts = parse_url($endpoint);
        if ($parts !== false) {
          $scheme = $parts['scheme'] ?? $scheme;
          $host = $parts['host'] ?? '';
          $path = $parts['path'] ?? '';
        }
      } else {
        $host = $endpoint;
      }
    }

    if ($host === '') {
      $host = 's3.' . $region . '.amazonaws.com';
    }

    return [
      'scheme' => $scheme,
      'host' => $host,
      'path' => $path,
    ];
  }

  private function buildCanonicalUri(string $basePath, string $encodedKey): string {
    $path = rtrim($basePath, '/');
    $path = $path === '' ? '' : '/' . ltrim($path, '/');
    return $path . '/' . ltrim($encodedKey, '/');
  }

  private function encodeKey(string $key): string {
    $encoded = rawurlencode($key);
    return str_replace('%2F', '/', $encoded);
  }

  private function buildCanonicalQuery(array $params): string {
    ksort($params);
    $pieces = [];
    foreach ($params as $name => $value) {
      $pieces[] = rawurlencode($name) . '=' . rawurlencode((string)$value);
    }

    return implode('&', $pieces);
  }

  private function buildQuery(array $params): string {
    ksort($params);
    $pieces = [];
    foreach ($params as $name => $value) {
      $pieces[] = rawurlencode($name) . '=' . rawurlencode((string)$value);
    }

    return implode('&', $pieces);
  }

  private function getSigningKey(string $secretKey, string $dateStamp, string $region, string $service): string {
    $kDate = hash_hmac('sha256', $dateStamp, 'AWS4' . $secretKey, true);
    $kRegion = hash_hmac('sha256', $region, $kDate, true);
    $kService = hash_hmac('sha256', $service, $kRegion, true);
    return hash_hmac('sha256', 'aws4_request', $kService, true);
  }
}
