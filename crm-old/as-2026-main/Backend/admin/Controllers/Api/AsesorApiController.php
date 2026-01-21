<?php

declare(strict_types=1);

namespace App\Controllers\Api;

require_once __DIR__ . '/../../Models/AsesorModel.php';

use App\Models\AsesorModel;
use RuntimeException;
use Throwable;

class AsesorApiController
{
    public function __construct(private readonly AsesorModel $asesorModel = new AsesorModel())
    {
    }

    public function index(): void
    {
        $this->jsonResponse([
            'asesores' => $this->asesorModel->all(),
        ]);
    }

    public function store(): void
    {
        try {
            $data = $this->readJsonInput();
            if ($data === null) {
                $this->jsonResponse(['ok' => false, 'error' => 'Solicitud inválida.'], 400);
                return;
            }

            $payload = $this->sanitizeData($data);
            $payload = $this->normalizeStrings($payload);

            if (!filter_var($payload['email'], FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('El correo electrónico no es válido.');
            }

            if ($this->asesorModel->existsByEmailOrPhone($payload['email'], $payload['celular'] ?? null)) {
                throw new RuntimeException('Asesor previamente registrado.');
            }

            $id     = $this->asesorModel->create($payload);
            $asesor = $this->asesorModel->find($id);

            if ($asesor === null) {
                throw new RuntimeException('No se pudo recuperar el asesor recién creado.');
            }

            $this->jsonResponse([
                'ok'      => true,
                'message' => 'Asesor creado correctamente.',
                'asesor'  => $asesor,
            ]);
        } catch (RuntimeException $exception) {
            $this->jsonResponse([
                'ok'    => false,
                'error' => $exception->getMessage(),
            ], 400);
        } catch (Throwable $exception) {
            $this->jsonResponse([
                'ok'    => false,
                'error' => 'Error interno del servidor.',
            ], 500);
        }
    }

    public function update(): void
    {
        try {
            $data = $this->readJsonInput();
            if ($data === null) {
                $this->jsonResponse(['ok' => false, 'error' => 'Solicitud inválida.'], 400);
                return;
            }

            $id = (int) ($data['id'] ?? 0);
            if ($id <= 0) {
                throw new RuntimeException('Identificador de asesor inválido.');
            }

            $payload = $this->sanitizeData($data);
            $payload = $this->normalizeStrings($payload);

            if (!filter_var($payload['email'], FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('El correo electrónico no es válido.');
            }

            $this->asesorModel->update($id, $payload);
            $asesor = $this->asesorModel->find($id);

            if ($asesor === null) {
                throw new RuntimeException('No se pudo recuperar la información actualizada del asesor.');
            }

            $this->jsonResponse([
                'ok'      => true,
                'message' => 'Asesor actualizado correctamente.',
                'asesor'  => $asesor,
            ]);
        } catch (RuntimeException $exception) {
            $this->jsonResponse([
                'ok'    => false,
                'error' => $exception->getMessage(),
            ], 400);
        } catch (Throwable $exception) {
            $this->jsonResponse([
                'ok'    => false,
                'error' => 'Error interno del servidor.',
            ], 500);
        }
    }

    public function delete(): void
    {
        try {
            $data = $this->readJsonInput();
            if ($data === null) {
                $this->jsonResponse(['ok' => false, 'error' => 'Solicitud inválida.'], 400);
                return;
            }

            $id = (int) ($data['id'] ?? 0);
            if ($id <= 0) {
                throw new RuntimeException('Identificador de asesor inválido.');
            }

            if (!$this->asesorModel->delete($id)) {
                throw new RuntimeException('El asesor tiene inquilinos asignados, reasigna antes de eliminar.');
            }

            $this->jsonResponse([
                'ok'      => true,
                'message' => 'Asesor eliminado correctamente.',
                'id'      => $id,
            ]);
        } catch (RuntimeException $exception) {
            $this->jsonResponse([
                'ok'    => false,
                'error' => $exception->getMessage(),
            ], 400);
        } catch (Throwable $exception) {
            $this->jsonResponse([
                'ok'    => false,
                'error' => 'Error interno del servidor.',
            ], 500);
        }
    }

    private function sanitizeData(array $input): array
    {
        return [
            'nombre_asesor' => trim((string) ($input['nombre_asesor'] ?? '')),
            'email'         => trim((string) ($input['email'] ?? '')),
            'celular'       => trim((string) ($input['celular'] ?? '')),
        ];
    }

    private function normalizeStrings(array $data): array
    {
        return array_map(static function ($value) {
            if (is_string($value)) {
                return mb_strtolower(trim($value), 'UTF-8');
            }

            return $value;
        }, $data);
    }

    private function readJsonInput(): ?array
    {
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

    /**
     * @param array<string, mixed> $payload
     */
    private function jsonResponse(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
