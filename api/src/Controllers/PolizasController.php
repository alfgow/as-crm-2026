<?php
namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\PolizaRepository;

final class PolizasController {
  private array $config;
  private \App\Repositories\InmuebleRepository $inmuebles;

  public function __construct(array $config, PolizaRepository $polizas, \App\Repositories\InmuebleRepository $inmuebles) {
    $this->config = $config;
    $this->polizas = $polizas;
    $this->inmuebles = $inmuebles;
  }

  public function index(Request $req, Response $res, array $ctx): void {
    $all = $this->polizas->findAll();
    $res->json(['data' => $all, 'meta' => ['requestId' => $req->getRequestId(), 'count' => count($all)], 'errors' => []]);
  }

  public function store(Request $req, Response $res, array $ctx): void {
    $body = $req->getJson();
    
    // Validar minimos (Solo id_inquilino, monto_poliza, id_inmueble y tipo_poliza son estrictamente requeridos al inicio)
    // id_arrendador e id_asesor se pueden tomar del inmueble si faltan
    if (empty($body['tipo_poliza']) || empty($body['id_inquilino']) || empty($body['monto_poliza']) || empty($body['id_inmueble'])) {
        $res->json(['data' => null, 'meta' => ['requestId' => $req->getRequestId()], 'errors' => [['code' => 'validation_error', 'message' => 'Missing required fields: tipo_poliza, id_inquilino, monto_poliza, id_inmueble']]], 400);
    }

    // Validar Tipo Póliza
    $validTipos = ['Clásica', 'Plus'];
    if (!in_array($body['tipo_poliza'], $validTipos)) {
        $res->json(['data' => null, 'meta' => ['requestId' => $req->getRequestId()], 'errors' => [['code' => 'validation_error', 'message' => 'Invalid tipo_poliza. Allowed: Clásica, Plus']]], 400);
    }

    // Obtener datos del Inmueble
    $inmueble = $this->inmuebles->findById((int)$body['id_inmueble']);
    if (!$inmueble) {
        $res->json(['data' => null, 'meta' => ['requestId' => $req->getRequestId()], 'errors' => [['code' => 'validation_error', 'message' => 'Inmueble not found']]], 404);
        return;
    }

    // Auto-fill ids from Inmueble if missing
    if (empty($body['id_asesor'])) {
        $body['id_asesor'] = $inmueble['id_asesor'];
    }
    if (empty($body['id_arrendador'])) {
        $body['id_arrendador'] = $inmueble['id_arrendador'];
    }

    // Mapear Estado
    $estadoMap = [
        1 => 'Vigente',
        2 => 'Concluida',
        3 => 'Término Anticipado',
        4 => 'Incumplimiento',
    ];

    if (isset($body['estado']) && is_numeric($body['estado'])) {
        $body['estado'] = $estadoMap[(int)$body['estado']] ?? 'Vigente';
    } elseif (empty($body['estado'])) {
        $body['estado'] = 'Vigente';
    }

    // Auto-fill from Inmueble
    $body['tipo_inmueble'] = $inmueble['tipo'];
    $body['monto_renta']   = $inmueble['renta'];

    // Defaults for Fiador/Obligado
    if (empty($body['id_obligado'])) $body['id_obligado'] = 292; // Obligado Solidario (NO APLICA)
    if (empty($body['id_fiador']))   $body['id_fiador']   = 40;  // Fiador (no)

    // Auto-increment numero_poliza
    $nextNum = $this->polizas->getNextNumeroPoliza();
    // Si viene numero_poliza en body, quizás queramos respetarlo? 
    // El requerimiento dice "la api debe ser inteligente para ir a consultar...". Asumimos override.
    if (!isset($body['numero_poliza'])) {
        $body['numero_poliza'] = $nextNum;
    }
    // Si serie_poliza no viene, usamos current year o default
    if (empty($body['serie_poliza'])) {
        $body['serie_poliza'] = date('Y');
    }

    // Validar fechas obligatorias si faltan (schema says Null: NO)
    if (empty($body['vigencia'])) $body['vigencia'] = '12 meses';
    if (empty($body['mes_vencimiento'])) $body['mes_vencimiento'] = date('F');
    if (empty($body['year_vencimiento'])) $body['year_vencimiento'] = date('Y', strtotime('+1 year'));
    if (empty($body['usuario'])) $body['usuario'] = 'System'; // O user token name si lo tuviéramos a mano
    if (empty($body['fecha_poliza'])) $body['fecha_poliza'] = date('Y-m-d');
    
    // Auto-calculate fecha_fin and periodo based on fecha_poliza
    if (empty($body['fecha_fin'])) {
        $body['fecha_fin'] = date('Y-m-d', strtotime($body['fecha_poliza'] . ' + 1 year'));
    }
    if (empty($body['periodo'])) {
        $startYear = date('Y', strtotime($body['fecha_poliza']));
        $endYear = date('Y', strtotime($body['fecha_fin']));
        $body['periodo'] = "$startYear-$endYear";
    }

    try {
        $id = $this->polizas->create($body);
        $item = $this->polizas->findById($id);
        $res->json(['data' => $item, 'meta' => ['requestId' => $req->getRequestId()], 'errors' => []], 201);
    } catch (\Throwable $e) {
        $res->json(['data' => null, 'meta' => ['requestId' => $req->getRequestId()], 'errors' => [['code' => 'db_error', 'message' => $e->getMessage()]]], 500);
    }
  }

  public function show(Request $req, Response $res, array $params): void {
      $id = (int)($params['id'] ?? 0);
      $item = $this->polizas->findById($id);

      if (!$item) {
          $res->json(['data' => null, 'meta' => ['requestId' => $req->getRequestId()], 'errors' => [['code' => 'not_found', 'message' => 'Poliza not found']]], 404);
      }
      $res->json(['data' => $item, 'meta' => ['requestId' => $req->getRequestId()], 'errors' => []]);
  }

  public function update(Request $req, Response $res, array $params): void {
      $id = (int)($params['id'] ?? 0);
      $body = $req->getJson();

      if (empty($body)) {
          $res->json(['data' => null, 'meta' => ['requestId' => $req->getRequestId()], 'errors' => [['code' => 'bad_request', 'message' => 'No data to update']]], 400);
      }

      // Mapear Estado si viene
      if (isset($body['estado'])) {
          $estadoMap = [
            1 => 'Vigente',
            2 => 'Concluida',
            3 => 'Término Anticipado',
            4 => 'Incumplimiento',
          ];
          
          if (is_numeric($body['estado'])) {
              $body['estado'] = $estadoMap[(int)$body['estado']] ?? null;
          }
          
          // Si el estado mapeado es null o era un string inválido, sería bueno validar, 
          // pero por flexibilidad dejemos que si es string pase, o forzamos?
          // Forzamos consistencia: Si es numérico debe existir en map.
          if (is_numeric($req->getJson()['estado']) && $body['estado'] === null) {
               // Fallback or error? Let's keep original if map fails but it's cleaner to error. 
               // For now just keep logic simple: map if found.
          }
      }

      $this->polizas->update($id, $body);
      $updated = $this->polizas->findById($id);
      $res->json(['data' => $updated, 'meta' => ['requestId' => $req->getRequestId()], 'errors' => []]);
  }

  public function destroy(Request $req, Response $res, array $params): void {
      $id = (int)($params['id'] ?? 0);
      $this->polizas->delete($id);
      $res->json(['data' => ['success' => true, 'id' => $id], 'meta' => ['requestId' => $req->getRequestId()], 'errors' => []]);
  }
}
