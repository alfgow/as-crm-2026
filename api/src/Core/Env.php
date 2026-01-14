<?php
namespace App\Core;

final class Env {
  public static function load(string $path): void {
    if (!file_exists($path)) return;

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
      $line = trim($line);
      if ($line === '' || str_starts_with($line, '#')) continue;

      [$key, $value] = array_pad(explode('=', $line, 2), 2, '');
      $key = trim($key);
      $value = trim($value);

      // quitar comillas
      $value = trim($value, "\"'");

      if ($key !== '' && getenv($key) === false) {
        putenv($key . '=' . $value);
        $_ENV[$key] = $value;
      }
    }
  }
}
