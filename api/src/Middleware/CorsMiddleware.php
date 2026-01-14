<?php
namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;

final class CorsMiddleware {
  private array $cfg;

  public function __construct(array $cfg) {
    $this->cfg = $cfg;
  }

  public function handle(Request $req, Response $res): void {
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $allowed = $this->cfg['allow_origins'] ?? [];

    if ($origin && (in_array($origin, $allowed, true) || in_array('*', $allowed, true))) {
      header("Access-Control-Allow-Origin: $origin");
      header("Vary: Origin");
    }

    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Methods: ' . implode(',', $this->cfg['allow_methods'] ?? []));
    header('Access-Control-Allow-Headers: ' . implode(',', $this->cfg['allow_headers'] ?? []));

    if ($req->getMethod() === 'OPTIONS') {
      http_response_code(204);
      exit;
    }
  }
}
