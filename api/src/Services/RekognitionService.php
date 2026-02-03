<?php
namespace App\Services;

use App\Core\HttpClient;

final class RekognitionService {
  private array $config;

  public function __construct(array $config) {
    $this->config = $config;
  }

  /**
   * @return array{ok:bool,status:int,body:mixed,raw:string,error?:string}
   */
  public function compareFacesS3(
    string $sourceBucket,
    string $sourceKey,
    string $targetBucket,
    string $targetKey,
    float $similarityThreshold
  ): array {
    $payload = [
      'SourceImage' => [
        'S3Object' => [
          'Bucket' => $sourceBucket,
          'Name' => $sourceKey,
        ],
      ],
      'TargetImage' => [
        'S3Object' => [
          'Bucket' => $targetBucket,
          'Name' => $targetKey,
        ],
      ],
      'SimilarityThreshold' => $similarityThreshold,
    ];

    return $this->request('CompareFaces', $payload);
  }

  /**
   * @return array{ok:bool,status:int,body:mixed,raw:string,error?:string}
   */
  private function request(string $operation, array $payload): array {
    $rekognition = $this->config['aws']['rekognition'] ?? [];
    $accessKey = (string)($rekognition['access_key'] ?? '');
    $secretKey = (string)($rekognition['secret_key'] ?? '');
    $sessionToken = (string)($rekognition['session_token'] ?? '');
    $region = (string)($rekognition['region'] ?? '');

    if ($accessKey === '' || $secretKey === '' || $region === '') {
      return [
        'ok' => false,
        'status' => 0,
        'body' => null,
        'raw' => '',
        'error' => 'Configuración AWS Rekognition incompleta',
      ];
    }

    $host = 'rekognition.' . $region . '.amazonaws.com';
    $url = 'https://' . $host . '/';
    $amzDate = gmdate('Ymd\THis\Z');
    $dateStamp = gmdate('Ymd');
    $target = 'RekognitionService.' . $operation;
    $contentType = 'application/x-amz-json-1.1';

    $jsonBody = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($jsonBody === false) {
      return [
        'ok' => false,
        'status' => 0,
        'body' => null,
        'raw' => '',
        'error' => 'No fue posible serializar la petición',
      ];
    }

    $headers = [
      'content-type' => $contentType,
      'host' => $host,
      'x-amz-date' => $amzDate,
      'x-amz-target' => $target,
    ];
    if ($sessionToken !== '') {
      $headers['x-amz-security-token'] = $sessionToken;
    }

    ksort($headers);
    $canonicalHeaders = '';
    foreach ($headers as $name => $value) {
      $canonicalHeaders .= $name . ':' . trim((string)$value) . "\n";
    }
    $signedHeaders = implode(';', array_keys($headers));

    $payloadHash = hash('sha256', $jsonBody);
    $canonicalRequest = implode("\n", [
      'POST',
      '/',
      '',
      $canonicalHeaders,
      $signedHeaders,
      $payloadHash,
    ]);

    $credentialScope = $dateStamp . '/' . $region . '/rekognition/aws4_request';
    $stringToSign = implode("\n", [
      'AWS4-HMAC-SHA256',
      $amzDate,
      $credentialScope,
      hash('sha256', $canonicalRequest),
    ]);
    $signingKey = $this->getSigningKey($secretKey, $dateStamp, $region, 'rekognition');
    $signature = hash_hmac('sha256', $stringToSign, $signingKey);
    $authorization = sprintf(
      'AWS4-HMAC-SHA256 Credential=%s/%s, SignedHeaders=%s, Signature=%s',
      $accessKey,
      $credentialScope,
      $signedHeaders,
      $signature
    );

    $requestHeaders = [
      'Content-Type: ' . $contentType,
      'X-Amz-Date: ' . $amzDate,
      'X-Amz-Target: ' . $target,
      'Authorization: ' . $authorization,
    ];
    if ($sessionToken !== '') {
      $requestHeaders[] = 'X-Amz-Security-Token: ' . $sessionToken;
    }

    $response = HttpClient::postJson($url, $payload, $requestHeaders, 20);
    $decoded = json_decode($response['body'], true);
    $ok = $response['status'] >= 200 && $response['status'] < 300;

    return [
      'ok' => $ok,
      'status' => $response['status'],
      'body' => $decoded ?? $response['body'],
      'raw' => $response['body'],
    ];
  }

  private function getSigningKey(string $secretKey, string $dateStamp, string $region, string $service): string {
    $kDate = hash_hmac('sha256', $dateStamp, 'AWS4' . $secretKey, true);
    $kRegion = hash_hmac('sha256', $region, $kDate, true);
    $kService = hash_hmac('sha256', $service, $kRegion, true);
    return hash_hmac('sha256', 'aws4_request', $kService, true);
  }
}
