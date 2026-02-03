<?php
namespace App\Services;

use App\Core\HttpClient;

final class MediaCopyService {
  private MediaPresignService $presign;

  public function __construct(MediaPresignService $presign) {
    $this->presign = $presign;
  }

  /**
   * @return array{ok:bool, status:int, error:?string}
   */
  public function copyObject(string $sourceBucket, string $sourceKey, string $destBucket, string $destKey): array {
    $getUrl = $this->presign->buildPresignedUrl($sourceBucket, $sourceKey);
    $putUrl = $this->presign->buildPresignedPutUrl($destBucket, $destKey);

    if (!$getUrl || !$putUrl) {
      return $this->result(false, 0, 'presign_failed');
    }

    $getResponse = HttpClient::request('GET', $getUrl, null, [], 30);
    if ($getResponse['status'] < 200 || $getResponse['status'] >= 300) {
      return $this->result(false, $getResponse['status'], 'source_fetch_failed');
    }

    $body = $getResponse['body'];
    $headers = [
      'Content-Length: ' . strlen($body),
      'Content-Type: application/octet-stream',
    ];

    $putResponse = HttpClient::request('PUT', $putUrl, $body, $headers, 30);
    if ($putResponse['status'] < 200 || $putResponse['status'] >= 300) {
      return $this->result(false, $putResponse['status'], 'dest_put_failed');
    }

    return $this->result(true, $putResponse['status'], null);
  }

  /**
   * @return array{ok:bool, status:int, error:?string}
   */
  public function deleteObject(string $bucket, string $key): array {
    $deleteUrl = $this->presign->buildPresignedDeleteUrl($bucket, $key);
    if (!$deleteUrl) {
      return $this->result(false, 0, 'presign_failed');
    }

    $deleteResponse = HttpClient::request('DELETE', $deleteUrl, null, [], 20);
    if ($deleteResponse['status'] < 200 || $deleteResponse['status'] >= 300) {
      return $this->result(false, $deleteResponse['status'], 'dest_delete_failed');
    }

    return $this->result(true, $deleteResponse['status'], null);
  }

  /**
   * Copia un objeto y elimina el objeto destino al finalizar.
   *
   * @return array{ok:bool, status:int, error:?string}
   */
  public function copyAndCleanup(string $sourceBucket, string $sourceKey, string $destBucket, string $destKey): array {
    $copy = $this->copyObject($sourceBucket, $sourceKey, $destBucket, $destKey);
    if (!$copy['ok']) {
      return $copy;
    }

    $delete = $this->deleteObject($destBucket, $destKey);
    if (!$delete['ok']) {
      return $delete;
    }

    return $delete;
  }

  /**
   * @return array{ok:bool, status:int, error:?string}
   */
  private function result(bool $ok, int $status, ?string $error): array {
    return [
      'ok' => $ok,
      'status' => $status,
      'error' => $error,
    ];
  }
}
