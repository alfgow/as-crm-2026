<?php
namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;

final class HealthController {
  public function health(Request $req, Response $res, array $params): void {
    $res->json([
      'data' => ['status' => 'ok', 'ts' => date('c')],
      'meta' => ['requestId' => $req->getRequestId()],
      'errors' => [],
    ]);
  }
}
