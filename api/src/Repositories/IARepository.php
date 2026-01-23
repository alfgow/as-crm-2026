<?php
namespace App\Repositories;

use App\Core\Database;

final class IARepository {
  private \PDO $pdo;

  public function __construct(Database $db) {
    $this->pdo = $db->pdo();
  }

  public function registrarInteraccion(array $data): bool {
    $prompt = isset($data['prompt']) ? (string)$data['prompt'] : '';
    $respuesta = array_key_exists('respuesta', $data) ? (string)$data['respuesta'] : null;
    $modeloKey = mb_substr((string)($data['modelo_key'] ?? ''), 0, 20);
    $modeloId = mb_substr((string)($data['modelo_id'] ?? ''), 0, 200);
    $ip = isset($data['ip']) ? mb_substr((string)$data['ip'], 0, 45) : null;
    $userAgent = isset($data['user_agent']) ? mb_substr((string)$data['user_agent'], 0, 255) : null;
    $durMs = isset($data['duration_ms']) ? (int)$data['duration_ms'] : 0;
    $contexto = $data['contexto'] ?? null;
    if (is_array($contexto)) {
      $contexto = json_encode($contexto, JSON_UNESCAPED_UNICODE);
    }

    $sql = "INSERT INTO ia_interacciones
            (usuario_id, modelo_key, modelo_id, prompt, respuesta, duration_ms, ip, user_agent, contexto)
            VALUES (:usuario_id, :modelo_key, :modelo_id, :prompt, :respuesta, :duration_ms, :ip, :user_agent, :contexto)";
    $stmt = $this->pdo->prepare($sql);
    return $stmt->execute([
      ':usuario_id' => $data['usuario_id'] ?? null,
      ':modelo_key' => $modeloKey,
      ':modelo_id' => $modeloId,
      ':prompt' => $prompt,
      ':respuesta' => $respuesta,
      ':duration_ms' => $durMs,
      ':ip' => $ip,
      ':user_agent' => $userAgent,
      ':contexto' => $contexto,
    ]);
  }

  public function listar(int $limit = 50, int $offset = 0): array {
    $limit = max(1, $limit);
    $offset = max(0, $offset);

    $sql = "SELECT id, usuario_id, modelo_key, modelo_id, duration_ms, created_at
            FROM ia_interacciones
            ORDER BY id DESC
            LIMIT :lim OFFSET :off";
    $stmt = $this->pdo->prepare($sql);
    $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
    $stmt->bindValue(':off', $offset, \PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll() ?: [];
  }

  public function obtener(int $id): ?array {
    $sql = "SELECT * FROM ia_interacciones WHERE id = :id LIMIT 1";
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();

    if ($row && !empty($row['contexto'])) {
      $decoded = json_decode((string)$row['contexto'], true);
      if (json_last_error() === JSON_ERROR_NONE) {
        $row['contexto'] = $decoded;
      }
    }

    return $row ?: null;
  }

  public function buscarInquilinosPorTexto(string $term, int $limit = 10): array {
    $term = trim($term);
    if ($term === '') {
      return [];
    }

    $limit = max(1, $limit);
    $conditions = [
      "CONCAT_WS(' ', i.nombre_inquilino, i.apellidop_inquilino, i.apellidom_inquilino) LIKE :term_like",
      'i.email LIKE :term_like',
      'i.celular LIKE :term_like',
      'COALESCE(i.rfc, "") LIKE :term_like',
      'COALESCE(i.curp, "") LIKE :term_like',
    ];
    $params = [
      ':term_like' => '%' . $term . '%',
    ];

    if (filter_var($term, FILTER_VALIDATE_EMAIL)) {
      $conditions[] = 'i.email = :email_exact';
      $params[':email_exact'] = $term;
    }

    $digits = preg_replace('/\D+/', '', $term);
    if ($digits !== '' && strlen($digits) >= 4) {
      $conditions[] = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(i.celular, ' ', ''), '-', ''), '+', ''), '(', ''), ')', '') LIKE :phone_like";
      $params[':phone_like'] = '%' . $digits . '%';
    }

    if (ctype_digit($term)) {
      $conditions[] = 'i.id = :id_exact';
      $params[':id_exact'] = (int)$term;
    }

    $where = implode(' OR ', array_unique($conditions));
    $sql = "SELECT
              i.id,
              TRIM(CONCAT_WS(' ', i.nombre_inquilino, i.apellidop_inquilino, i.apellidom_inquilino)) AS nombre,
              i.email,
              COALESCE(i.celular, '') AS celular,
              COALESCE(NULLIF(i.tipo, ''), 'inquilino') AS tipo
            FROM inquilinos i
            WHERE i.status = 1
              AND ({$where})
            ORDER BY i.updated_at DESC
            LIMIT :lim";
    $stmt = $this->pdo->prepare($sql);
    foreach ($params as $key => $value) {
      if ($key === ':id_exact') {
        $stmt->bindValue($key, $value, \PDO::PARAM_INT);
      } else {
        $stmt->bindValue($key, $value);
      }
    }
    $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll() ?: [];

    return array_map(static function (array $row): array {
      return [
        'id' => (int)($row['id'] ?? 0),
        'nombre' => (string)($row['nombre'] ?? ''),
        'email' => (string)($row['email'] ?? ''),
        'celular' => (string)($row['celular'] ?? ''),
        'tipo' => (string)($row['tipo'] ?? 'inquilino'),
      ];
    }, $rows);
  }

  public function obtenerPolizasActivasPorInquilino(int $inquilinoId): array {
    $sql = "SELECT
              p.numero_poliza,
              p.monto_poliza,
              p.vigencia,
              inm.direccion_inmueble,
              inm.renta,
              arr.nombre_arrendador AS arrendador
            FROM polizas p
            INNER JOIN inmuebles inm ON p.id_inmueble = inm.id
            INNER JOIN arrendadores arr ON inm.id_arrendador = arr.id
            WHERE p.id_inquilino = :id
              AND p.estado = 1";
    $stmt = $this->pdo->prepare($sql);
    $stmt->bindValue(':id', $inquilinoId, \PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll() ?: [];
  }
}
