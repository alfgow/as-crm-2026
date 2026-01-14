<?php
namespace App\Core;

final class Jwt {
  public static function sign(array $payload, string $secret): string {
    $header = ['typ' => 'JWT', 'alg' => 'HS256'];
    $h = Security::base64UrlEncode(json_encode($header));
    $p = Security::base64UrlEncode(json_encode($payload));
    $sig = hash_hmac('sha256', "$h.$p", $secret, true);
    $s = Security::base64UrlEncode($sig);
    return "$h.$p.$s";
  }

  public static function verify(string $jwt, string $secret): ?array {
    $parts = explode('.', $jwt);
    if (count($parts) !== 3) return null;
    [$h, $p, $s] = $parts;

    $sig = Security::base64UrlEncode(hash_hmac('sha256', "$h.$p", $secret, true));
    if (!hash_equals($sig, $s)) return null;

    $payloadJson = Security::base64UrlDecode($p);
    $payload = json_decode($payloadJson, true);
    if (!is_array($payload)) return null;

    // exp check
    if (isset($payload['exp']) && is_numeric($payload['exp'])) {
      if ((int)$payload['exp'] < time()) return null;
    }

    return $payload;
  }
}
