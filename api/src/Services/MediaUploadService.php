<?php
namespace App\Services;

final class MediaUploadService {
  private MediaPresignService $presign;

  public function __construct(MediaPresignService $presign) {
    $this->presign = $presign;
  }

  public function uploadFromPath(string $bucket, string $key, string $filePath, ?string $mimeType = null): array {
    $url = $this->presign->buildPresignedPutUrl($bucket, $key);
    if (!$url) {
      return ['ok' => false, 'status' => 0, 'error' => 'presign_failed'];
    }

    $body = @file_get_contents($filePath);
    if ($body === false) {
      return ['ok' => false, 'status' => 0, 'error' => 'file_read_failed'];
    }

    $headers = [
      'Content-Type: ' . ($mimeType ?: 'application/octet-stream'),
      'Content-Length: ' . strlen($body),
    ];

    $context = stream_context_create([
      'http' => [
        'method' => 'PUT',
        'header' => implode("\r\n", $headers),
        'content' => $body,
        'ignore_errors' => true,
      ],
    ]);

    $result = @file_get_contents($url, false, $context);
    $status = $this->extractStatusCode($http_response_header ?? []);

    if ($result === false && $status === 0) {
      return ['ok' => false, 'status' => 0, 'error' => 'upload_failed'];
    }

    return [
      'ok' => $status >= 200 && $status < 300,
      'status' => $status,
      'error' => $status >= 200 && $status < 300 ? null : 'upload_failed',
    ];
  }

  private function extractStatusCode(array $headers): int {
    if (empty($headers)) {
      return 0;
    }

    $line = $headers[0] ?? '';
    if (preg_match('/\s(\d{3})\s/', $line, $matches)) {
      return (int)$matches[1];
    }

    return 0;
  }
}
