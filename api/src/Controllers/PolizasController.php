<?php
namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\PolizaRepository;
use App\Services\DocxTemplateService;

final class PolizasController {
  private array $config;
  private \App\Repositories\InmuebleRepository $inmuebles;
  private DocxTemplateService $docx;
  /** @var array<string,string> */
  private array $contractTemplateMap = [
    // Persona Física
    'normal_pf' => 'Contrato_Normal_PF 2025.docx',
    'os_pf' => 'Contrato_ObligadoSolidario_PF 2025.docx',
    'fiador_pf' => 'Contrato_Fiador_PF 2025.docx',
    'os_fiador_pf' => 'Contrato_OS_Fiador_PF.docx',

    // Combinados Arrendador/Inquilino PM-PF
    'arr_pm_inq_pf' => 'Contrato_Arr_PM_Inq_PF.docx',
    'inq_pm_arr_pf' => 'Contrato_Inq_PM_Arr_PF.docx',

    // Persona Moral
    'pmoral' => 'Contrato_Persona_Moral.docx',
    'normal_pm' => 'Contrato_Normal_PM.docx',
    'os_pm' => 'Contrato_ObligadoSolidario_PM.docx',
    'fiador_pm' => 'Contrato_Fiador_PM.docx',
    'os_fiador_pm' => 'Contrato_OS_Fiador_PM.docx',
  ];

  public function __construct(array $config, PolizaRepository $polizas, \App\Repositories\InmuebleRepository $inmuebles) {
    $this->config = $config;
    $this->polizas = $polizas;
    $this->inmuebles = $inmuebles;
    $this->docx = new DocxTemplateService();
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

  public function showByNumero(Request $req, Response $res, array $params): void {
      $numero = (int)($params['numero'] ?? 0);
      if ($numero <= 0) {
          $res->json([
              'data' => null,
              'meta' => ['requestId' => $req->getRequestId()],
              'errors' => [['code' => 'bad_request', 'message' => 'numero inválido']]
          ], 400);
          return;
      }

      $item = $this->polizas->findByNumero($numero);
      if (!$item) {
          $res->json([
              'data' => null,
              'meta' => ['requestId' => $req->getRequestId()],
              'errors' => [['code' => 'not_found', 'message' => 'Poliza no encontrada']]
          ], 404);
          return;
      }

      $res->json(['data' => $item, 'meta' => ['requestId' => $req->getRequestId()], 'errors' => []]);
  }

  public function updateByNumero(Request $req, Response $res, array $params): void {
      $numero = (int)($params['numero'] ?? 0);
      $body = $req->getJson();

      if ($numero <= 0) {
          $res->json([
              'data' => null,
              'meta' => ['requestId' => $req->getRequestId()],
              'errors' => [['code' => 'bad_request', 'message' => 'numero inválido']]
          ], 400);
          return;
      }

      if (empty($body)) {
          $res->json([
              'data' => null,
              'meta' => ['requestId' => $req->getRequestId()],
              'errors' => [['code' => 'bad_request', 'message' => 'No data to update']]
          ], 400);
          return;
      }

      $item = $this->polizas->findByNumero($numero);
      if (!$item || empty($item['id_poliza'])) {
          $res->json([
              'data' => null,
              'meta' => ['requestId' => $req->getRequestId()],
              'errors' => [['code' => 'not_found', 'message' => 'Poliza no encontrada']]
          ], 404);
          return;
      }

      $this->polizas->update((int)$item['id_poliza'], $body);
      $updated = $this->polizas->findById((int)$item['id_poliza']);

      $res->json(['data' => $updated, 'meta' => ['requestId' => $req->getRequestId()], 'errors' => []]);
  }

  public function buscar(Request $req, Response $res): void {
      $query = $req->getQuery();
      $numeroParam = $query['numero'] ?? $query['q'] ?? null;
      $numero = $numeroParam !== null ? (int)$numeroParam : 0;

      if ($numero <= 0) {
          $res->json([
              'data' => null,
              'meta' => ['requestId' => $req->getRequestId()],
              'errors' => [['code' => 'bad_request', 'message' => 'numero o q requerido']]
          ], 400);
          return;
      }

      $item = $this->polizas->findByNumero($numero);
      if (!$item) {
          $res->json([
              'data' => null,
              'meta' => ['requestId' => $req->getRequestId()],
              'errors' => [['code' => 'not_found', 'message' => 'Poliza no encontrada']]
          ], 404);
          return;
      }

      $res->json(['data' => $item, 'meta' => ['requestId' => $req->getRequestId()], 'errors' => []]);
  }

  public function renta(Request $req, Response $res, array $params): void {
      $numero = (int)($params['numero'] ?? 0);
      if ($numero <= 0) {
          $res->json([
              'data' => null,
              'meta' => ['requestId' => $req->getRequestId()],
              'errors' => [['code' => 'bad_request', 'message' => 'numero inválido']]
          ], 400);
          return;
      }

      $poliza = $this->polizas->findByNumero($numero);
      if (!$poliza) {
          $res->json([
              'data' => null,
              'meta' => ['requestId' => $req->getRequestId()],
              'errors' => [['code' => 'not_found', 'message' => 'Poliza no encontrada']]
          ], 404);
          return;
      }

      $query = $req->getQuery();
      $inmuebleId = isset($query['id_inmueble']) ? (int)$query['id_inmueble'] : (int)($poliza['id_inmueble'] ?? 0);
      if ($inmuebleId <= 0) {
          $res->json([
              'data' => null,
              'meta' => ['requestId' => $req->getRequestId()],
              'errors' => [['code' => 'not_found', 'message' => 'La póliza no tiene un inmueble asociado']]
          ], 404);
          return;
      }

      $inmueble = $this->inmuebles->findById($inmuebleId);
      if (!$inmueble) {
          $res->json([
              'data' => null,
              'meta' => ['requestId' => $req->getRequestId()],
              'errors' => [['code' => 'not_found', 'message' => 'Inmueble no encontrado']]
          ], 404);
          return;
      }

      $renta = (string)($inmueble['renta'] ?? '');
      $rentaNormalizada = preg_replace('/[^\d.]/', '', $renta);

      $res->json([
          'data' => [
              'monto_renta' => $renta,
              'monto_renta_numerica' => $rentaNormalizada,
              'id_inmueble' => $inmuebleId,
          ],
          'meta' => ['requestId' => $req->getRequestId()],
          'errors' => []
      ]);
  }

  public function renovar(Request $req, Response $res, array $params): void {
      $numero = (int)($params['numero'] ?? 0);
      if ($numero <= 0) {
          $res->json([
              'data' => null,
              'meta' => ['requestId' => $req->getRequestId()],
              'errors' => [['code' => 'bad_request', 'message' => 'numero inválido']]
          ], 400);
          return;
      }

      $poliza = $this->polizas->findByNumero($numero);
      if (!$poliza) {
          $res->json([
              'data' => null,
              'meta' => ['requestId' => $req->getRequestId()],
              'errors' => [['code' => 'not_found', 'message' => 'Poliza no encontrada']]
          ], 404);
          return;
      }

      $body = $req->getJson() ?? [];
      $numeroPoliza = $this->polizas->getNextNumeroPoliza();
      $fechaPoliza = $body['fecha_poliza'] ?? date('Y-m-d');
      $fechaFin = $body['fecha_fin'] ?? date('Y-m-d', strtotime($fechaPoliza . ' + 1 year'));
      $periodo = $body['periodo'] ?? (date('Y', strtotime($fechaPoliza)) . '-' . date('Y', strtotime($fechaFin)));

      $payload = array_merge($poliza, $body, [
          'numero_poliza' => $numeroPoliza,
          'serie_poliza' => $body['serie_poliza'] ?? date('Y'),
          'fecha_poliza' => $fechaPoliza,
          'fecha_fin' => $fechaFin,
          'periodo' => $periodo,
          'mes_vencimiento' => $body['mes_vencimiento'] ?? (int)date('n', strtotime($fechaFin)),
          'year_vencimiento' => $body['year_vencimiento'] ?? (int)date('Y', strtotime($fechaFin)),
          'estado' => $body['estado'] ?? 'Vigente',
      ]);

      unset($payload['id_poliza'], $payload['created_at'], $payload['updated_at']);

      try {
          $id = $this->polizas->create($payload);
          $item = $this->polizas->findById($id);
          $res->json(['data' => $item, 'meta' => ['requestId' => $req->getRequestId()], 'errors' => []], 201);
      } catch (\Throwable $e) {
          $res->json(['data' => null, 'meta' => ['requestId' => $req->getRequestId()], 'errors' => [['code' => 'db_error', 'message' => $e->getMessage()]]], 500);
      }
  }

  public function contrato(Request $req, Response $res, array $params): void {
      $id = (int)($params['id'] ?? 0);
      if ($id <= 0) {
          $res->json([
              'data' => null,
              'meta' => ['requestId' => $req->getRequestId()],
              'errors' => [['code' => 'bad_request', 'message' => 'id inválido']]
          ], 400);
          return;
      }

      $poliza = $this->polizas->findContratoById($id);
      if (!$poliza) {
          $res->json([
              'data' => null,
              'meta' => ['requestId' => $req->getRequestId()],
              'errors' => [['code' => 'not_found', 'message' => 'Poliza no encontrada']]
          ], 404);
          return;
      }

      $payload = $this->buildContratoPayload($poliza);

      $res->json([
          'data' => $payload,
          'meta' => ['requestId' => $req->getRequestId()],
          'errors' => []
      ]);
  }

  public function contratoByNumero(Request $req, Response $res, array $params): void {
      $numero = (int)($params['numero'] ?? 0);
      if ($numero <= 0) {
          $res->json([
              'data' => null,
              'meta' => ['requestId' => $req->getRequestId()],
              'errors' => [['code' => 'bad_request', 'message' => 'numero inválido']]
          ], 400);
          return;
      }

      $poliza = $this->polizas->findContratoByNumero($numero);
      if (!$poliza) {
          $res->json([
              'data' => null,
              'meta' => ['requestId' => $req->getRequestId()],
              'errors' => [['code' => 'not_found', 'message' => 'Poliza no encontrada']]
          ], 404);
          return;
      }

      $payload = $this->buildContratoPayload($poliza);

      $res->json([
          'data' => $payload,
          'meta' => ['requestId' => $req->getRequestId()],
          'errors' => []
      ]);
  }

  public function guardarContrato(Request $req, Response $res, array $params): void {
      $numero = (int)($params['numero'] ?? 0);
      $body = $req->getJson() ?? [];
      $tipoContrato = trim((string)($body['tipo_contrato'] ?? ''));

      if ($numero <= 0 || $tipoContrato === '') {
          $res->json([
              'data' => null,
              'meta' => ['requestId' => $req->getRequestId()],
              'errors' => [['code' => 'bad_request', 'message' => 'numero y tipo_contrato son requeridos']]
          ], 400);
          return;
      }

      $poliza = $this->polizas->findContratoByNumero($numero);
      if (!$poliza) {
          $res->json([
              'data' => null,
              'meta' => ['requestId' => $req->getRequestId()],
              'errors' => [['code' => 'not_found', 'message' => 'Poliza no encontrada']]
          ], 404);
          return;
      }

      $payload = $this->buildContratoPayload($poliza);
      $payload['tipo_contrato'] = $tipoContrato;

      $res->json([
          'data' => $payload,
          'meta' => ['requestId' => $req->getRequestId()],
          'errors' => []
      ]);
  }

  public function descargarPolizaDocx(Request $req, Response $res, array $params): void {
      $numero = (int)($params['numero'] ?? 0);
      if ($numero <= 0) {
          $res->json(['data' => null, 'meta' => ['requestId' => $req->getRequestId()], 'errors' => [['code' => 'bad_request', 'message' => 'numero inválido']]], 400);
      }

      $poliza = $this->polizas->findContratoByNumero($numero);
      if (!$poliza) {
          $res->json(['data' => null, 'meta' => ['requestId' => $req->getRequestId()], 'errors' => [['code' => 'not_found', 'message' => 'Poliza no encontrada']]], 404);
      }

      $tipoPoliza = mb_strtolower(trim((string)($poliza['tipo_poliza'] ?? '')), 'UTF-8');
      $plantillas = [
          'clásica' => 'Plantilla_Poliza_Clásica.docx',
          'clasica' => 'Plantilla_Poliza_Clásica.docx',
          'plus' => 'Plantilla_Poliza_Plus.docx',
      ];

      if (!isset($plantillas[$tipoPoliza])) {
          $res->json(['data' => null, 'meta' => ['requestId' => $req->getRequestId()], 'errors' => [['code' => 'template_not_found', 'message' => 'No existe plantilla para ese tipo de póliza']]], 422);
      }

      $templatePath = $this->templatePath($plantillas[$tipoPoliza]);
      if (!is_file($templatePath)) {
          $res->json(['data' => null, 'meta' => ['requestId' => $req->getRequestId()], 'errors' => [['code' => 'template_not_found', 'message' => 'Plantilla de póliza no encontrada']]], 404);
      }

      $vars = $this->docx->getVariables($templatePath);
      $set = function (array &$out, string $key, $value) use ($vars): void {
          if (in_array($key, $vars, true)) {
              $out[$key] = (string)$value;
          }
      };
      $mayus = fn($v): string => mb_strtoupper(trim((string)$v), 'UTF-8');

      $replacements = [];
      $set($replacements, 'NUM', (string)($poliza['numero_poliza'] ?? ''));
      $set($replacements, 'FECHA_EMISION', (string)($poliza['fecha_poliza'] ?? date('Y-m-d')));
      $set($replacements, 'ARRENDADOR', $mayus($poliza['nombre_arrendador'] ?? ''));
      $set($replacements, 'INQUILINO', $mayus($this->fullName($poliza, 'nombre_inquilino', 'apellidop_inquilino', 'apellidom_inquilino')));
      $set($replacements, 'INMUEBLE', $mayus($poliza['direccion_inmueble'] ?? ''));
      $set($replacements, 'MONTO_RENTA', (string)($poliza['monto_renta'] ?? ''));
      $set($replacements, 'VIGENCIA', (string)($poliza['vigencia'] ?? ''));

      $tmpDocx = tempnam(sys_get_temp_dir(), 'poliza_docx_') . '.docx';
      $this->docx->renderToFile($templatePath, $replacements, $tmpDocx);

      $this->streamDocxAndExit($tmpDocx, $this->safeAsciiFilename(sprintf('Poliza_%s_%s.docx', (string)$numero, (string)($poliza['direccion_inmueble'] ?? 'inmueble'))));
  }

  public function generarContratoDocx(Request $req, Response $res, array $params): void {
      $numero = (int)($params['numero'] ?? 0);
      $body = $req->getJson() ?? [];
      $tipoContratoKey = mb_strtolower(trim((string)($body['tipo_contrato'] ?? '')), 'UTF-8');

      if ($numero <= 0 || $tipoContratoKey === '') {
          $res->json(['data' => null, 'meta' => ['requestId' => $req->getRequestId()], 'errors' => [['code' => 'bad_request', 'message' => 'numero y tipo_contrato son requeridos']]], 400);
      }

      $poliza = $this->polizas->findContratoByNumero($numero);
      if (!$poliza) {
          $res->json(['data' => null, 'meta' => ['requestId' => $req->getRequestId()], 'errors' => [['code' => 'not_found', 'message' => 'Poliza no encontrada']]], 404);
      }

      if (!isset($this->contractTemplateMap[$tipoContratoKey])) {
          $res->json(['data' => null, 'meta' => ['requestId' => $req->getRequestId()], 'errors' => [['code' => 'template_not_found', 'message' => 'Aún no hay plantilla para ese tipo de contrato']]], 422);
      }

      $templatePath = $this->templatePath($this->contractTemplateMap[$tipoContratoKey]);
      if (!is_file($templatePath)) {
          $res->json(['data' => null, 'meta' => ['requestId' => $req->getRequestId()], 'errors' => [['code' => 'template_not_found', 'message' => 'Plantilla de contrato no encontrada']]], 404);
      }

      $vars = $this->docx->getVariables($templatePath);
      $replacements = $this->buildContratoReplacements($poliza, $vars);
      $tmpDocx = tempnam(sys_get_temp_dir(), 'contrato_docx_') . '.docx';
      $this->docx->renderToFile($templatePath, $replacements, $tmpDocx);

      $anio = !empty($poliza['fecha_poliza']) ? (new \DateTime((string)$poliza['fecha_poliza']))->format('Y') : date('Y');
      $filename = $this->safeAsciiFilename('Contrato ' . trim((string)($poliza['direccion_inmueble'] ?? '')) . " v{$anio}.2.0.docx");
      $this->streamDocxAndExit($tmpDocx, $filename);
  }

  private function buildContratoPayload(array $poliza): array {
      $nombreInquilino = trim(sprintf(
          '%s %s %s',
          $poliza['nombre_inquilino'] ?? '',
          $poliza['apellidop_inquilino'] ?? '',
          $poliza['apellidom_inquilino'] ?? ''
      ));
      $nombreFiador = trim(sprintf(
          '%s %s %s',
          $poliza['fiador_nombre'] ?? '',
          $poliza['fiador_apellidop'] ?? '',
          $poliza['fiador_apellidom'] ?? ''
      ));
      $nombreObligado = trim(sprintf(
          '%s %s %s',
          $poliza['obligado_nombre'] ?? '',
          $poliza['obligado_apellidop'] ?? '',
          $poliza['obligado_apellidom'] ?? ''
      ));

      return [
          'poliza' => $poliza,
          'inquilino_nombre' => $nombreInquilino !== '' ? $nombreInquilino : null,
          'fiador_nombre' => $nombreFiador !== '' ? $nombreFiador : null,
          'obligado_nombre' => $nombreObligado !== '' ? $nombreObligado : null,
      ];
  }

  /** @param array<int,string> $vars */
  private function buildContratoReplacements(array $poliza, array $vars): array {
      $set = function (array &$out, string $key, $value) use ($vars): void {
          if (in_array($key, $vars, true)) {
              $out[$key] = (string)$value;
          }
      };
      $mayus = fn($v): string => mb_strtoupper(trim((string)$v), 'UTF-8');

      $inquilino = $this->fullName($poliza, 'nombre_inquilino', 'apellidop_inquilino', 'apellidom_inquilino');
      $fiador = $this->fullName($poliza, 'fiador_nombre', 'fiador_apellidop', 'fiador_apellidom');
      $obligado = $this->fullName($poliza, 'obligado_nombre', 'obligado_apellidop', 'obligado_apellidom');
      $arrendador = trim((string)($poliza['nombre_arrendador'] ?? ''));

      $out = [];
      $set($out, 'ARRENDADOR', $mayus($arrendador));
      $set($out, 'ARRENDATARIO', $mayus($inquilino));
      $set($out, 'FIADOR', $mayus($fiador !== '' ? $fiador : 'N/A'));
      $set($out, 'OBLIGADO SOLIDARIO', $mayus($obligado));
      $set($out, 'INMUEBLE', $mayus((string)($poliza['direccion_inmueble'] ?? '')));
      $set($out, 'VIGENCIA', $mayus((string)($poliza['vigencia'] ?? '')));
      $set($out, 'monto_renta', (string)($poliza['monto_renta'] ?? '0'));
      $set($out, 'monto_mantenimiento', (string)($poliza['monto_mantenimiento'] ?? '0'));
      $set($out, 'DIRECCION_ARRENDADOR', $mayus((string)($poliza['direccion_arrendador'] ?? '')));
      $set($out, 'NACIONALIDAD_ARRENDADOR', $mayus((string)($poliza['arrendador_nacionalidad'] ?? '')));
      $set($out, 'NACIONALIDAD_ARRENDATARIO', $mayus((string)($poliza['inquilino_nacionalidad'] ?? '')));
      $set($out, 'NACIONALIDAD_OBLIGADO', $mayus((string)($poliza['obligado_nacionalidad'] ?? '')));
      $set($out, 'NACIONALIDAD_FIADOR', $mayus((string)($poliza['fiador_nacionalidad'] ?? '')));

      $fechaInicio = (string)($poliza['fecha_poliza'] ?? date('Y-m-d'));
      $fecha = new \DateTime($fechaInicio);
      $fmtMes = new \IntlDateFormatter('es_MX', \IntlDateFormatter::FULL, \IntlDateFormatter::NONE, 'America/Mexico_City', \IntlDateFormatter::GREGORIAN, 'LLLL');
      $fmtLar = new \IntlDateFormatter('es_MX', \IntlDateFormatter::LONG, \IntlDateFormatter::NONE, 'America/Mexico_City', \IntlDateFormatter::GREGORIAN, "dd 'de' MMMM 'de' yyyy");
      $set($out, 'mes_renta', $mayus((string)$fmtMes->format($fecha)));
      $set($out, 'fecha_inicio', $mayus((string)$fmtLar->format($fecha)));

      return $out;
  }

  private function fullName(array $row, string $nombre, string $apPaterno, string $apMaterno): string {
      return trim(sprintf('%s %s %s', (string)($row[$nombre] ?? ''), (string)($row[$apPaterno] ?? ''), (string)($row[$apMaterno] ?? '')));
  }

  private function templatePath(string $file): string {
      $base = getenv('DOCX_TEMPLATES_PATH') ?: (__DIR__ . '/../../plantillas');
      return rtrim($base, '/\\') . DIRECTORY_SEPARATOR . $file;
  }

  private function safeAsciiFilename(string $name, int $max = 180): string {
      $name = str_replace(['"', "'"], '', $name);
      $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name);
      $ascii = is_string($ascii) ? $ascii : 'documento.docx';
      $ascii = preg_replace('/[^A-Za-z0-9 _.\-]/', '', $ascii) ?? 'documento.docx';
      $ascii = trim(preg_replace('/\s+/', ' ', $ascii) ?? 'documento.docx');
      if (!str_ends_with(strtolower($ascii), '.docx')) {
          $ascii .= '.docx';
      }
      return substr($ascii, 0, $max);
  }

  private function streamDocxAndExit(string $path, string $filename): void {
      while (function_exists('ob_get_level') && ob_get_level() > 0) {
          ob_end_clean();
      }

      header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
      header('Content-Length: ' . filesize($path));
      header('Content-Disposition: attachment; filename="' . $filename . '"');
      readfile($path);
      @unlink($path);
      exit;
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

  public function destroyByNumero(Request $req, Response $res, array $params): void {
      $numero = (int)($params['numero'] ?? 0);
      if ($numero <= 0) {
          $res->json([
              'data' => null,
              'meta' => ['requestId' => $req->getRequestId()],
              'errors' => [['code' => 'bad_request', 'message' => 'numero inválido']]
          ], 400);
          return;
      }

      $poliza = $this->polizas->findByNumero($numero);
      if (!$poliza) {
          $res->json([
              'data' => null,
              'meta' => ['requestId' => $req->getRequestId()],
              'errors' => [['code' => 'not_found', 'message' => 'Poliza no encontrada']]
          ], 404);
          return;
      }

      $id = (int)($poliza['id_poliza'] ?? 0);
      if ($id > 0) {
          $this->polizas->delete($id);
      }

      $res->json([
          'data' => ['success' => true, 'numero' => $numero, 'id' => $id],
          'meta' => ['requestId' => $req->getRequestId()],
          'errors' => []
      ]);
  }
}
