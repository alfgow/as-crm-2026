<?php
namespace App\Core;

final class Hmac {
  public static function sign(string $rawBody, string $secret): string {
    $sig = hash_hmac('sha256', $rawBody, $secret);
    return 'sha256=' . $sig;
  }

  public static function verify(string $rawBody, string $secret, string $providedHeader): bool {
    $expected = self::sign($rawBody, $secret);
    // Comparación timing-safe
    return hash_equals($expected, trim($providedHeader));
  }
}
