<?php

declare(strict_types=1);

namespace App\Controllers\Api;

require_once __DIR__ . '/../../Models/ArrendadorModel.php';
require_once __DIR__ . '/../../Models/InmueblesModel.php';

use App\Models\ArrendadorModel;
use App\Models\InmuebleModel;
use InvalidArgumentException;
use Throwable;

class InmuebleApiController
{
    public function __construct(
        private readonly InmuebleModel $inmuebleModel = new InmuebleModel(),
        private readonly ArrendadorModel $arrendadorModel = new ArrendadorModel(),
    ) {
    }

    public function index(): void
    {
        $query = trim((string) ($_GET['q'] ?? ''));

        if ($query !== '') {
            $resultado = $this->inmuebleModel->buscarPorDireccion($query);
            $items = $resultado['items'] ?? [];
        } else {
            $items = $this->inmuebleModel->obtenerTodos();
        }

        $this->jsonResponse([
            'ok'       => true,
            'query'    => $query,
            'total'    => count($items),
            'items'    => $items,
        ]);
    }

    public function detalle(string $pk, ?string $sk = null): void
    {
        try {
            $inmueble = $this->inmuebleModel->obtenerPorId(rawurldecode($pk), $sk !== null ? rawurldecode($sk) : null);
        } catch (InvalidArgumentException $exception) {
            $this->jsonResponse(['ok' => false, 'error' => $exception->getMessage()], 400);
            return;
        } catch (Throwable $exception) {
            $this->jsonResponse(['ok' => false, 'error' => 'Error al consultar inmueble'], 500);
            return;
        }

        if (!$inmueble) {
            $this->jsonResponse(['ok' => false, 'error' => 'Inmueble no encontrado'], 404);
            return;
        }

        $this->jsonResponse([
            'ok'       => true,
            'inmueble' => $inmueble,
        ]);
    }

    public function info(string $pk, ?string $sk = null): void
    {
        $this->detalle($pk, $sk);
    }

    public function store(): void
    {
        $this->guardar(false);
    }

    public function update(): void
    {
        $this->guardar(true);
    }

    public function delete(): void
    {
        $input = $this->readInput();
        if ($input === null) {
            $this->jsonResponse(['ok' => false, 'error' => 'Solicitud inválida'], 400);
            return;
        }

        $pk = trim((string) ($input['pk'] ?? ''));
        $sk = trim((string) ($input['sk'] ?? ''));
        $id = trim((string) ($input['id'] ?? ''));

        if (($pk === '' || $sk === '') && $id !== '') {
            $inmueble = $this->inmuebleModel->obtenerPorId($id);
            if ($inmueble) {
                $pk = $pk !== '' ? $pk : (string) ($inmueble['pk'] ?? '');
                $sk = $sk !== '' ? $sk : (string) ($inmueble['sk'] ?? '');
            }
        }

        if ($pk === '' || $sk === '') {
            $this->jsonResponse(['ok' => false, 'error' => 'Parámetros inválidos'], 400);
            return;
        }

        try {
            $ok = $this->inmuebleModel->eliminar($pk, $sk);
            $this->jsonResponse(['ok' => (bool) $ok]);
        } catch (Throwable $exception) {
            $this->jsonResponse(['ok' => false, 'error' => 'Error al eliminar inmueble'], 500);
        }
    }

    public function guardarAjax(): void
    {
        $input = $this->readInput();
        if ($input === null) {
            $this->jsonResponse(['ok' => false, 'error' => 'Solicitud inválida'], 400);
            return;
        }

        $pk = isset($input['pk']) ? (string) $input['pk'] : '';

        $calle = trim((string) ($input['calle'] ?? ''));
        $numExt = trim((string) ($input['num_exterior'] ?? ''));
        $numInt = trim((string) ($input['num_interior'] ?? ''));
        $colonia = trim((string) ($input['colonia'] ?? ''));
        $alcaldia = trim((string) ($input['alcaldia'] ?? ''));
        $ciudad = trim((string) ($input['ciudad'] ?? ''));
        $cp = trim((string) ($input['codigo_postal'] ?? ''));

        $direccionInmueble = sprintf(
            '%s %s%s, col. %s, %s, %s, cp %s',
            $calle,
            $numExt,
            $numInt !== '' ? ' int. ' . $numInt : '',
            $colonia,
            $alcaldia,
            $ciudad,
            $cp
        );

        $input['pk'] = $pk;
        $input['direccion_inmueble'] = $direccionInmueble;

        $this->guardar(false, $input);
    }

    public function inmueblesPorArrendador(string $identificador): void
    {
        $identificador = trim(rawurldecode($identificador));

        if ($identificador === '') {
            $this->jsonResponse(['ok' => false, 'error' => 'Identificador de arrendador inválido'], 400);
            return;
        }

        $idOClave = ctype_digit($identificador)
            ? (int) $identificador
            : $identificador;

        try {
            $inmuebles = $this->inmuebleModel->obtenerPorArrendador($idOClave);
            $this->jsonResponse([
                'ok'        => true,
                'items'     => $inmuebles ?? [],
            ]);
        } catch (Throwable $exception) {
            $this->jsonResponse(['ok' => false, 'error' => 'Error al consultar inmuebles'], 500);
        }
    }

    private function guardar(bool $isUpdate, ?array $input = null): void
    {
        $input = $input ?? $this->readInput();
        if ($input === null) {
            $this->jsonResponse(['ok' => false, 'error' => 'Solicitud inválida'], 400);
            return;
        }

        try {
            $payload = $this->prepareInmueblePayload($input, $isUpdate);
            $id = null;

            if ($isUpdate) {
                $id = (int) $payload['id'];
                unset($payload['id']);
                $ok = $this->inmuebleModel->actualizarPorId($id, $payload);
            } else {
                $ok = $this->inmuebleModel->crear($payload);
            }

            $this->jsonResponse([
                'ok' => (bool) $ok,
                'id' => $id,
            ]);
        } catch (InvalidArgumentException $exception) {
            $this->jsonResponse(['ok' => false, 'error' => $exception->getMessage()], 422);
        } catch (Throwable $exception) {
            $this->jsonResponse(['ok' => false, 'error' => 'Error al guardar inmueble'], 500);
        }
    }

    private function readInput(): ?array
    {
        $contentType = (string) ($_SERVER['CONTENT_TYPE'] ?? '');
        if (stripos($contentType, 'application/json') !== false) {
            $input = file_get_contents('php://input');
            if ($input === false || trim($input) === '') {
                return null;
            }

            $decoded = json_decode($input, true);
            if (!is_array($decoded)) {
                return null;
            }

            return $decoded;
        }

        return $_POST ?: null;
    }

    /**
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>
     */
    private function prepareInmueblePayload(array $input, bool $isUpdate = false): array
    {
        $pkRaw = $input['pk'] ?? ($input['arrendador_pk'] ?? ($input['id_arrendador'] ?? ''));
        if (is_array($pkRaw)) {
            $pkRaw = reset($pkRaw);
        }
        $pkInput = trim((string) $pkRaw);

        if ($pkInput === '') {
            throw new InvalidArgumentException('Debe seleccionar un arrendador');
        }

        $arrendadorId = $this->parseArrendadorId($pkInput);
        if ($arrendadorId === null) {
            throw new InvalidArgumentException('Identificador de arrendador inválido');
        }

        $inmuebleId = null;
        if ($isUpdate) {
            $skRaw = $input['sk'] ?? ($input['id'] ?? '');
            if (is_array($skRaw)) {
                $skRaw = reset($skRaw);
            }
            $skInput = trim((string) $skRaw);
            if ($skInput === '') {
                throw new InvalidArgumentException('Identificador del inmueble requerido');
            }

            $inmuebleId = $this->parseInmuebleId($skInput);
            if ($inmuebleId === null) {
                throw new InvalidArgumentException('Identificador del inmueble inválido');
            }
        }

        $direccion = trim((string) ($input['direccion_inmueble'] ?? ''));
        $tipo      = trim((string) ($input['tipo'] ?? ''));
        $rentaRaw  = (string) ($input['renta'] ?? '');

        if ($direccion === '' || $tipo === '' || trim($rentaRaw) === '') {
            throw new InvalidArgumentException('Faltan datos obligatorios del inmueble');
        }

        $mantenimiento = $this->normalizarMantenimiento((string) ($input['mantenimiento'] ?? ''));

        $montoMantenimiento = $this->normalizarMonto((string) ($input['monto_mantenimiento'] ?? '0'));
        $deposito           = $this->normalizarMonto((string) ($input['deposito'] ?? '0'));

        $estacionamientoVal = $input['estacionamiento'] ?? 0;
        if (is_array($estacionamientoVal)) {
            $estacionamientoVal = reset($estacionamientoVal);
        }
        $estacionamiento = max(0, (int) $estacionamientoVal);

        $mascotasRaw = strtoupper(trim((string) ($input['mascotas'] ?? 'NO')));
        $mascotas    = $mascotasRaw === 'SI' ? 'SI' : 'NO';

        $comentarios = trim((string) ($input['comentarios'] ?? ''));

        $asesorRaw = $input['asesor_pk'] ?? ($input['id_asesor'] ?? '');
        if (is_array($asesorRaw)) {
            $asesorRaw = reset($asesorRaw);
        }
        $asesorInput = trim((string) $asesorRaw);
        $asesorId = $this->parseAsesorId($asesorInput);

        if ($asesorId === null) {
            $profile = $this->arrendadorModel->obtenerProfilePorPk('arr#' . $arrendadorId);
            if ($profile && array_key_exists('id_asesor', $profile)) {
                $asesorId = $profile['id_asesor'] !== null
                    ? (int) $profile['id_asesor']
                    : null;
            }
        }

        $data = [
            'id_arrendador'       => $arrendadorId,
            'id_asesor'           => $asesorId,
            'direccion_inmueble'  => $direccion,
            'tipo'                => $tipo,
            'renta'               => $this->normalizarMonto($rentaRaw),
            'mantenimiento'       => $mantenimiento,
            'monto_mantenimiento' => $montoMantenimiento,
            'deposito'            => $deposito,
            'estacionamiento'     => $estacionamiento,
            'mascotas'            => $mascotas,
            'comentarios'         => $comentarios,
        ];

        if ($isUpdate && $inmuebleId !== null) {
            $data['id'] = $inmuebleId;
        }

        return $data;
    }

    private function parseArrendadorId(string $valor): ?int
    {
        $valor = trim($valor);
        if ($valor === '') {
            return null;
        }

        if (ctype_digit($valor)) {
            $id = (int) $valor;

            return $id > 0 ? $id : null;
        }

        if (preg_match('/^arr#(\d+)$/i', $valor, $matches)) {
            $id = (int) $matches[1];

            return $id > 0 ? $id : null;
        }

        return null;
    }

    private function parseAsesorId(?string $valor): ?int
    {
        if ($valor === null) {
            return null;
        }

        $valor = trim($valor);
        if ($valor === '') {
            return null;
        }

        if (ctype_digit($valor)) {
            $id = (int) $valor;

            return $id > 0 ? $id : null;
        }

        if (preg_match('/^ase#(\d+)$/i', $valor, $matches)) {
            $id = (int) $matches[1];

            return $id > 0 ? $id : null;
        }

        return null;
    }

    private function parseInmuebleId(string $valor): ?int
    {
        $valor = trim($valor);
        if ($valor === '') {
            return null;
        }

        if (ctype_digit($valor)) {
            $id = (int) $valor;

            return $id > 0 ? $id : null;
        }

        if (preg_match('/^INM#(\d+)$/i', $valor, $matches)) {
            $id = (int) $matches[1];

            return $id > 0 ? $id : null;
        }

        return null;
    }

    private function normalizarMantenimiento(string $valor): string
    {
        $normalizado = strtoupper(str_replace(' ', '_', trim($valor)));

        switch ($normalizado) {
            case 'SI':
                return 'Si';
            case 'NO':
                return 'No';
            case 'NO_APLICA':
            case 'NA':
                return 'na';
            default:
                return 'No';
        }
    }

    private function normalizarMonto(string $valor): string
    {
        $v = trim($valor);
        if ($v === '') {
            return '0.00';
        }

        $v = str_replace(['$', ' '], '', $v);

        if (preg_match('/^\d{1,3}(\.\d{3})+,\d{2}$/', $v)) {
            $v = str_replace('.', '', $v);
            $v = str_replace(',', '.', $v);
        } else {
            $v = str_replace(',', '', $v);
        }

        if (!str_contains($v, '.')) {
            $v .= '.00';
        } else {
            $parts = explode('.', $v, 2);
            $dec = substr($parts[1] . '00', 0, 2);
            $v = $parts[0] . '.' . $dec;
        }

        if (!preg_match('/^\d+(\.\d{2})$/', $v)) {
            return '0.00';
        }

        return $v;
    }

    private function jsonResponse(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
