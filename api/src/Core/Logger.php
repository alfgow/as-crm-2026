<?php
namespace App\Core;

final class Logger {
  private string $file;

  public function __construct(string $file) {
    $this->file = $file;
  }

  public function info(string $message, array $context = []): void {
    $this->write('INFO', $message, $context);
  }

  public function error(string $message, array $context = []): void {
    $this->write('ERROR', $message, $context);
  }

  private function write(string $level, string $message, array $context): void {
    $line = json_encode([
      'ts' => date('c'),
      'level' => $level,
      'message' => $message,
      'context' => $context,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    @file_put_contents($this->file, $line . PHP_EOL, FILE_APPEND);
  }
}
