<?php
namespace App\Services;

final class SesEmailService {
  private array $config;

  public function __construct(array $config) {
    $this->config = $config;
  }

  public function sendEmail(string $to, string $subject, string $htmlBody, string $textBody): array {
    $sesConfig = $this->config['ses'] ?? [];
    $accessKey = (string)($sesConfig['access_key'] ?? '');
    $secretKey = (string)($sesConfig['secret_key'] ?? '');
    $region = (string)($sesConfig['region'] ?? 'us-east-1');
    $sourceEmail = (string)($sesConfig['source_email'] ?? '');

    if ($accessKey === '' || $secretKey === '' || $sourceEmail === '') {
      return [
        'ok' => false,
        'message' => 'SES no configurado (credenciales o remitente faltantes).',
      ];
    }

    $host = "email.{$region}.amazonaws.com";
    $service = 'ses';
    $method = 'POST';
    $uri = '/';
    $amzDate = gmdate('Ymd\THis\Z');
    $date = gmdate('Ymd');

    $params = [
      'Action' => 'SendEmail',
      'Source' => $sourceEmail,
      'Destination.ToAddresses.member.1' => $to,
      'Message.Subject.Data' => $subject,
      'Message.Body.Html.Data' => $htmlBody,
      'Message.Body.Text.Data' => $textBody,
    ];

    $payload = http_build_query($params, '', '&', PHP_QUERY_RFC3986);

    $canonicalHeaders = implode("\n", [
      'content-type:application/x-www-form-urlencoded; charset=utf-8',
      "host:{$host}",
      "x-amz-date:{$amzDate}",
      '',
    ]);
    $signedHeaders = 'content-type;host;x-amz-date';
    $payloadHash = hash('sha256', $payload);

    $canonicalRequest = implode("\n", [
      $method,
      $uri,
      '',
      $canonicalHeaders,
      $signedHeaders,
      $payloadHash,
    ]);

    $credentialScope = "{$date}/{$region}/{$service}/aws4_request";
    $stringToSign = implode("\n", [
      'AWS4-HMAC-SHA256',
      $amzDate,
      $credentialScope,
      hash('sha256', $canonicalRequest),
    ]);

    $signingKey = $this->getSignatureKey($secretKey, $date, $region, $service);
    $signature = hash_hmac('sha256', $stringToSign, $signingKey);
    $authorization = sprintf(
      'AWS4-HMAC-SHA256 Credential=%s/%s, SignedHeaders=%s, Signature=%s',
      $accessKey,
      $credentialScope,
      $signedHeaders,
      $signature
    );

    $headers = [
      'Content-Type: application/x-www-form-urlencoded; charset=utf-8',
      'X-Amz-Date: ' . $amzDate,
      'Authorization: ' . $authorization,
      'Host: ' . $host,
    ];

    $url = "https://{$host}{$uri}";
    $ch = curl_init($url);
    curl_setopt_array($ch, [
      CURLOPT_POST => true,
      CURLOPT_HTTPHEADER => $headers,
      CURLOPT_POSTFIELDS => $payload,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_TIMEOUT => 15,
    ]);
    $response = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
      return [
        'ok' => false,
        'message' => 'SES request failed: ' . $error,
      ];
    }

    if ($status < 200 || $status >= 300) {
      return [
        'ok' => false,
        'message' => 'SES error (' . $status . '): ' . $response,
      ];
    }

    return [
      'ok' => true,
      'message' => 'Email enviado',
      'status' => $status,
      'response' => $response,
    ];
  }

  private function getSignatureKey(string $secret, string $date, string $region, string $service): string {
    $kDate = hash_hmac('sha256', $date, 'AWS4' . $secret, true);
    $kRegion = hash_hmac('sha256', $region, $kDate, true);
    $kService = hash_hmac('sha256', $service, $kRegion, true);
    return hash_hmac('sha256', 'aws4_request', $kService, true);
  }
}
