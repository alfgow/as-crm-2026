<?php
namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;

final class HealthController {
  private \App\Core\Database $db;

  public function __construct(\App\Core\Database $db) {
    $this->db = $db;
  }

  public function health(Request $req, Response $res, array $params): void {
    $dbStatus = 'ok';
    try {
      $this->db->pdo()->query('SELECT 1');
    } catch (\Throwable $e) {
      $dbStatus = 'error: ' . $e->getMessage();
    }

    $res->json([
      'data' => [
        'status' => 'ok',
        'db' => $dbStatus,
        'ts' => date('c')
      ],
      'meta' => ['requestId' => $req->getRequestId()],
      'errors' => [],
    ]);
  }
}
