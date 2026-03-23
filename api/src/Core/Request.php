<?php
namespace App\Core;

final class Request {
  private string $method;
  private string $path;
  private array $query;
  private array $headers;
  private array $cookies;
  private ?array $jsonBody;
  private string $requestId;
  private ?string $rawBody = null;

  public function __construct() {
    $this->method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

    $uri = $_SERVER['REQUEST_URI'] ?? '/';
    $uri = explode('?', $uri, 2)[0];
    
    // Detect base path to support running in subdirectories
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    $dirname = dirname($scriptName);
    $dirname = str_replace('\\', '/', $dirname); // Normalize windows paths

    if ($dirname !== '/' && str_starts_with($uri, $dirname)) {
        $uri = substr($uri, strlen($dirname));
    }

    $this->path = '/' . ltrim($uri, '/');

    $this->query = $_GET ?? [];
    $this->headers = $this->readHeaders();
    $this->cookies = $_COOKIE ?? [];
    $this->normalizeBodyPayload();
    $this->jsonBody = $this->readJsonBody();
    $this->requestId = $this->headers['x-request-id'] ?? bin2hex(random_bytes(12));
  }

  private function readHeaders(): array {
    $h = [];
    foreach ($_SERVER as $k => $v) {
      if (str_starts_with($k, 'HTTP_')) {
        $name = strtolower(str_replace('_', '-', substr($k, 5)));
        $h[$name] = $v;
      }
    }
    if (isset($_SERVER['CONTENT_TYPE'])) $h['content-type'] = $_SERVER['CONTENT_TYPE'];
    
    // Explicit checks for Authorization in various server vars
    $auth = $_SERVER['HTTP_AUTHORIZATION'] 
         ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] 
         ?? $_SERVER['HTTP_X_AUTHORIZATION_TOKEN']
         ?? $_SERVER['HTTP_X_AUTH_TOKEN'] // <--- NUEVO: Header personalizado
         ?? $_SERVER['AUTHORIZATION'] 
         ?? null;
         
    if ($auth) $h['authorization'] = $auth;
    return $h;
  }

  private function readJsonBody(): ?array {
    $ct = strtolower($this->headers['content-type'] ?? '');
    if (!str_contains($ct, 'application/json')) return null;

    $raw = $this->readRawBody();
    if (!$raw) return null;

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : null;
  }

  private function readRawBody(): string {
    if ($this->rawBody !== null) {
      return $this->rawBody;
    }

    $this->rawBody = file_get_contents('php://input') ?: '';
    return $this->rawBody;
  }

  private function normalizeBodyPayload(): void {
    if ($this->method === 'POST') {
      return;
    }

    $rawContentType = $this->headers['content-type'] ?? '';
    if ($rawContentType === '') {
      return;
    }
    $ct = strtolower($rawContentType);

    $raw = $this->readRawBody();
    if ($raw === '') {
      return;
    }

    if (str_contains($ct, 'application/x-www-form-urlencoded') && empty($_POST)) {
      parse_str($raw, $parsed);
      if (is_array($parsed) && $parsed !== []) {
        $_POST = array_replace_recursive($_POST, $parsed);
      }
      return;
    }

    if (!str_contains($ct, 'multipart/form-data')) {
      return;
    }

    $parsed = $this->parseMultipartFormData($raw, $rawContentType);
    if ($parsed['post'] !== []) {
      $_POST = array_replace_recursive($_POST, $parsed['post']);
    }
    if ($parsed['files'] !== []) {
      $_FILES = array_replace_recursive($_FILES, $parsed['files']);
    }
  }

  private function parseMultipartFormData(string $raw, string $contentType): array {
    if (!preg_match('/boundary=(?:"([^"]+)"|([^;]+))/i', $contentType, $matches)) {
      return ['post' => [], 'files' => []];
    }

    $boundary = trim((string)($matches[1] !== '' ? $matches[1] : ($matches[2] ?? '')));
    if ($boundary === '') {
      return ['post' => [], 'files' => []];
    }

    $post = [];
    $files = [];
    $delimiter = '--' . $boundary;
    $parts = explode($delimiter, $raw);

    foreach ($parts as $part) {
      $part = ltrim($part, "\r\n");
      if ($part === '' || $part === '--' || $part === "--\r\n") {
        continue;
      }

      if (str_ends_with($part, "\r\n")) {
        $part = substr($part, 0, -2);
      } elseif (str_ends_with($part, "\n")) {
        $part = substr($part, 0, -1);
      }

      if ($part === '--') {
        continue;
      }

      $separator = strpos($part, "\r\n\r\n");
      $separatorLength = 4;
      if ($separator === false) {
        $separator = strpos($part, "\n\n");
        $separatorLength = 2;
      }
      if ($separator === false) {
        continue;
      }

      $rawHeaders = substr($part, 0, $separator);
      $body = substr($part, $separator + $separatorLength);
      $headers = $this->parsePartHeaders($rawHeaders);
      $disposition = $headers['content-disposition'] ?? '';

      if ($disposition === '' || !preg_match('/name="([^"]+)"/i', $disposition, $nameMatch)) {
        continue;
      }

      $field = $nameMatch[1];
      if (!preg_match('/filename="([^"]*)"/i', $disposition, $fileMatch)) {
        $post[$field] = $body;
        continue;
      }

      $filename = $fileMatch[1];
      $tmpPath = tempnam(sys_get_temp_dir(), 'req_');
      if ($tmpPath === false) {
        continue;
      }

      file_put_contents($tmpPath, $body);
      register_shutdown_function(static function(string $path): void {
        if (is_file($path)) {
          @unlink($path);
        }
      }, $tmpPath);

      $files[$field] = [
        'name' => $filename,
        'full_path' => $filename,
        'type' => $headers['content-type'] ?? 'application/octet-stream',
        'tmp_name' => $tmpPath,
        'error' => UPLOAD_ERR_OK,
        'size' => strlen($body),
      ];
    }

    return ['post' => $post, 'files' => $files];
  }

  private function parsePartHeaders(string $rawHeaders): array {
    $headers = [];

    foreach (preg_split("/\r\n|\n|\r/", $rawHeaders) ?: [] as $line) {
      $line = trim($line);
      if ($line === '' || !str_contains($line, ':')) {
        continue;
      }

      [$name, $value] = explode(':', $line, 2);
      $headers[strtolower(trim($name))] = trim($value);
    }

    return $headers;
  }

  public function getMethod(): string { return $this->method; }
  public function getPath(): string { return $this->path; }
  public function getQuery(): array { return $this->query; }
  public function getHeaders(): array { return $this->headers; }
  public function getJson(): ?array { return $this->jsonBody; }
  public function getRequestId(): string { return $this->requestId; }
  public function getHeader(string $name): ?string { return $this->headers[strtolower($name)] ?? null; }
  public function getCookie(string $name): ?string { return $this->cookies[$name] ?? null; }

  public function bearerToken(): ?string {
    $auth = $this->headers['authorization'] ?? '';
    if (!$auth) return null;
    if (preg_match('/Bearer\s+(.+)/i', $auth, $m)) return trim($m[1]);
    return trim($auth);
  }
}
