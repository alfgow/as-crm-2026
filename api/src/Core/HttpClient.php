<?php
namespace App\Core;

final class HttpClient {
  /**
   * @return array{status:int, body:string, headers:array<string,string>}
   */
  public static function postJson(string $url, array $payload, array $headers = [], int $timeoutSeconds = 10): array {
    $ch = curl_init($url);

    $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $defaultHeaders = [
      'Accept: application/json',
    ];
    $hasContentType = false;
    foreach ($headers as $header) {
      if (stripos($header, 'content-type:') === 0) {
        $hasContentType = true;
        break;
      }
    }
    if (!$hasContentType) {
      $defaultHeaders[] = 'Content-Type: application/json; charset=utf-8';
    }
    $h = array_merge($defaultHeaders, $headers);

    $respHeaders = [];
    curl_setopt_array($ch, [
      CURLOPT_POST => true,
      CURLOPT_POSTFIELDS => $body,
      CURLOPT_HTTPHEADER => $h,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_HEADERFUNCTION => function($curl, $headerLine) use (&$respHeaders) {
        $len = strlen($headerLine);
        $parts = explode(':', $headerLine, 2);
        if (count($parts) === 2) {
          $name = strtolower(trim($parts[0]));
          $value = trim($parts[1]);
          $respHeaders[$name] = $value;
        }
        return $len;
      },
      CURLOPT_TIMEOUT => $timeoutSeconds,
    ]);

    $respBody = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($respBody === false) {
      $err = curl_error($ch);
      curl_close($ch);
      return ['status' => 0, 'body' => $err ?: 'curl_error', 'headers' => $respHeaders];
    }

    curl_close($ch);
    return ['status' => $status, 'body' => (string)$respBody, 'headers' => $respHeaders];
  }

  /**
   * @return array{status:int, body:string, headers:array<string,string>}
   */
  public static function request(string $method, string $url, ?string $body = null, array $headers = [], int $timeoutSeconds = 20): array {
    $ch = curl_init($url);
    $respHeaders = [];

    $options = [
      CURLOPT_CUSTOMREQUEST => strtoupper($method),
      CURLOPT_HTTPHEADER => $headers,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_HEADERFUNCTION => function($curl, $headerLine) use (&$respHeaders) {
        $len = strlen($headerLine);
        $parts = explode(':', $headerLine, 2);
        if (count($parts) === 2) {
          $name = strtolower(trim($parts[0]));
          $value = trim($parts[1]);
          $respHeaders[$name] = $value;
        }
        return $len;
      },
      CURLOPT_TIMEOUT => $timeoutSeconds,
    ];

    if ($body !== null) {
      $options[CURLOPT_POSTFIELDS] = $body;
    }

    curl_setopt_array($ch, $options);

    $respBody = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($respBody === false) {
      $err = curl_error($ch);
      curl_close($ch);
      return ['status' => 0, 'body' => $err ?: 'curl_error', 'headers' => $respHeaders];
    }

    curl_close($ch);
    return ['status' => $status, 'body' => (string)$respBody, 'headers' => $respHeaders];
  }
}
