<?php
namespace App\Core;

final class Request {
  private string $method;
  private string $path;
  private array $query;
  private array $headers;
  private ?array $jsonBody;
  private string $requestId;

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
    if (isset($_SERVER['AUTHORIZATION'])) $h['authorization'] = $_SERVER['AUTHORIZATION'];
    return $h;
  }

  private function readJsonBody(): ?array {
    $ct = strtolower($this->headers['content-type'] ?? '');
    if (!str_contains($ct, 'application/json')) return null;

    $raw = file_get_contents('php://input');
    if (!$raw) return null;

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : null;
  }

  public function getMethod(): string { return $this->method; }
  public function getPath(): string { return $this->path; }
  public function getQuery(): array { return $this->query; }
  public function getHeaders(): array { return $this->headers; }
  public function getJson(): ?array { return $this->jsonBody; }
  public function getRequestId(): string { return $this->requestId; }

  public function bearerToken(): ?string {
    $auth = $this->headers['authorization'] ?? '';
    if (!$auth) return null;
    if (preg_match('/Bearer\s+(.+)/i', $auth, $m)) return trim($m[1]);
    return null;
  }
}
