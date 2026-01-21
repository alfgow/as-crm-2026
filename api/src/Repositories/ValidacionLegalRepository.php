<?php
namespace App\Repositories;

use App\Core\Database;

final class ValidacionLegalRepository {
  private \PDO $pdo;
  private array $config;

  public function __construct(Database $db, array $config) {
    $this->pdo = $db->pdo();
    $this->config = $config;
  }

  public function obtenerValidaciones(int $idInquilino): array {
    $sql = "SELECT * FROM inquilinos_validaciones WHERE id_inquilino = :id LIMIT 1";
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute([':id' => $idInquilino]);
    $row = $stmt->fetch();
    return $row ?: [];
  }

  public function actualizarProcesoDemandas(int $idInquilino, int $estado): bool {
    $sql = "UPDATE inquilinos_validaciones
            SET proceso_inv_demandas = :estado, updated_at = NOW()
            WHERE id_inquilino = :id";
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute([':estado' => $estado, ':id' => $idInquilino]);
    return true;
  }

  public function obtenerHistorialPorInquilino(int $idInquilino): array {
    $sql = "SELECT * FROM validaciones_legal
            WHERE id_inquilino = :id
            ORDER BY searched_at DESC";
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute([':id' => $idInquilino]);
    return $stmt->fetchAll();
  }

  public function obtenerUltimoReportePorInquilino(int $idInquilino): ?array {
    $sql = "SELECT * FROM validaciones_legal
            WHERE id_inquilino = :id
            ORDER BY searched_at DESC, id DESC
            LIMIT 1";
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute([':id' => $idInquilino]);
    $row = $stmt->fetch();
    return $row ?: null;
  }

  public function buscarEnGoogle(
    int $idInquilino,
    string $nombreCompleto,
    string $nombreSolo,
    string $apellidoP,
    string $apellidoM,
    ?string $curp = null,
    ?string $rfc = null
  ): array {
    $apiKey = $this->config['google']['api_key'] ?? null;
    $cx = $this->config['google']['cx'] ?? null;

    if (!$apiKey || !$cx) {
      return ['ok' => false, 'error' => 'Faltan credenciales Google API'];
    }

    $q = urlencode($nombreCompleto);
    $url = "https://www.googleapis.com/customsearch/v1?key={$apiKey}&cx={$cx}&q={$q}";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $resp = curl_exec($ch);
    curl_close($ch);

    if (!$resp) {
      return ['ok' => false, 'error' => 'Error al llamar Google API'];
    }

    $data = json_decode($resp, true);
    if (!isset($data['items'])) {
      $data['items'] = [];
    }

    $normalize = function (string $value): string {
      $normalized = \Normalizer::normalize(mb_strtolower($value, 'UTF-8'), \Normalizer::FORM_D);
      return preg_replace('/\p{Mn}/u', '', $normalized);
    };

    $var1 = trim("$nombreSolo $apellidoP $apellidoM");
    $var2 = trim("$apellidoP $apellidoM $nombreSolo");
    $variantes = [$normalize($var1), $normalize($var2)];

    $resultados = [];
    foreach ($data['items'] as $item) {
      $texto = $normalize(($item['title'] ?? '') . ' ' . ($item['snippet'] ?? ''));
      foreach ($variantes as $variante) {
        if (strpos($texto, $variante) !== false) {
          $resultados[] = [
            'titulo' => $item['title'] ?? '',
            'link' => $item['link'] ?? '',
            'snippet' => $item['snippet'] ?? '',
          ];
          break;
        }
      }
    }

    $clasificacion = count($resultados) > 0 ? 'match_alto' : 'sin_evidencia';
    $status = count($resultados) > 0 ? 'ok' : 'no_data';

    $sql = "INSERT INTO validaciones_legal
            (id_inquilino, nombre, apellido_p, apellido_m, curp, rfc,
             query_usada, resultado, clasificacion, status, searched_at)
            VALUES (:id_inq, :nombre, :ap_p, :ap_m, :curp, :rfc,
             :query, :resultado, :clasif, :status, NOW())";
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute([
      ':id_inq' => $idInquilino,
      ':nombre' => $nombreSolo,
      ':ap_p' => $apellidoP,
      ':ap_m' => $apellidoM,
      ':curp' => $curp,
      ':rfc' => $rfc,
      ':query' => json_encode(['variante1' => $var1, 'variante2' => $var2], JSON_UNESCAPED_UNICODE),
      ':resultado' => json_encode($resultados, JSON_UNESCAPED_UNICODE),
      ':clasif' => $clasificacion,
      ':status' => $status,
    ]);

    if (count($resultados) === 0) {
      $sqlUpd = "UPDATE inquilinos_validaciones
                 SET proceso_inv_demandas = 1,
                     inv_demandas_resumen = '⚖️ No se encontraron coincidencias jurídicas para este inquilino.',
                     updated_at = NOW()
                 WHERE id_inquilino = :id LIMIT 1";
      $upd = $this->pdo->prepare($sqlUpd);
      $upd->execute([':id' => $idInquilino]);
    }

    return [
      'ok' => true,
      'query' => $nombreCompleto,
      'variantes' => [$var1, $var2],
      'total' => count($resultados),
      'resultados' => $resultados,
      'clasificacion' => $clasificacion,
      'status' => $status,
    ];
  }
}
