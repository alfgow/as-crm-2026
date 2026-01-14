<?php
namespace App\Repositories;

use App\Core\Database;
use App\Core\Request;

final class ApiLogRepository {
  private \PDO $pdo;

  public function __construct(Database $db) {
    $this->pdo = $db->pdo();
  }

  public function log(Request $req, int $statusCode, string $routeName): void {
    // Mapeo a tabla `api_logs` existente
    // request_id, method, path, status_code, ip_address, user_agent, occurred_at
    // user_id lo intentaremos sacar del token si es posible, o dejaremos NULL por ahora (se podría mejorar pasando userId)
    
    $sql = "INSERT INTO api_logs (
              request_id, 
              method, 
              path, 
              status_code, 
              ip_address, 
              user_agent, 
              occurred_at
            ) VALUES (
              :request_id, 
              :method, 
              :path, 
              :status_code, 
              :ip_address, 
              :user_agent, 
              NOW()
            )";

    $st = $this->pdo->prepare($sql);
    
    // Remote Addr fallback
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    // User Agent
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';

    $st->execute([
      ':request_id' => $req->getRequestId(),
      ':method' => $req->getMethod(),
      ':path' => $req->getPath(),
      ':status_code' => $statusCode,
      ':ip_address' => substr($ip, 0, 45),
      ':user_agent' => substr($ua, 0, 255),
    ]);
  }
}
