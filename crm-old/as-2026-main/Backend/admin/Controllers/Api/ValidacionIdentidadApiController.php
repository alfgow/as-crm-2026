<?php

declare(strict_types=1);

namespace App\Controllers\Api;

require_once __DIR__ . '/../../Helpers/S3Helper.php';
require_once __DIR__ . '/../../Models/InquilinoModel.php';

use App\Helpers\S3Helper;
use App\Models\InquilinoModel;

class ValidacionIdentidadApiController
{
    public function __construct(private readonly InquilinoModel $inquilinoModel = new InquilinoModel())
    {
    }

    public function show(string $slug): void
    {
        $slug = trim($slug);
        if ($slug === '') {
            $this->jsonResponse(['ok' => false, 'error' => 'Inquilino no encontrado'], 404);
            return;
        }

        $inquilino = $this->inquilinoModel->obtenerPorSlug($slug);
        if (!$inquilino) {
            $this->jsonResponse(['ok' => false, 'error' => 'Inquilino no encontrado'], 404);
            return;
        }

        $archivos = $this->inquilinoModel->obtenerArchivosIdentidad((int) ($inquilino['id'] ?? 0));
        $s3 = new S3Helper('inquilinos');

        $payload = [
            'selfie'           => !empty($archivos['selfie']) ? $s3->getS3Url($archivos['selfie']) : null,
            'ine_frontal'      => !empty($archivos['ine_frontal']) ? $s3->getS3Url($archivos['ine_frontal']) : null,
            'ine_reverso'      => !empty($archivos['ine_reverso']) ? $s3->getS3Url($archivos['ine_reverso']) : null,
            'pasaporte'        => !empty($archivos['pasaporte']) ? $s3->getS3Url($archivos['pasaporte']) : null,
            'forma_migratoria' => !empty($archivos['forma_migratoria']) ? $s3->getS3Url($archivos['forma_migratoria']) : null,
        ];

        $this->jsonResponse([
            'ok'        => true,
            'inquilino' => $inquilino,
            'archivos'  => array_filter($payload, static fn($value) => $value !== null),
        ]);
    }

    public function procesar(string $slug): void
    {
        header('Content-Type: application/json; charset=utf-8');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'msg' => 'Método no permitido']);
            return;
        }

        echo json_encode([
            'success' => true,
            'msg'     => 'Validación exitosa',
            'slug'    => $slug,
        ]);
    }

    public function resultado(string $slug): void
    {
        $slug = trim($slug);
        if ($slug === '') {
            $this->jsonResponse(['ok' => false, 'error' => 'Inquilino no encontrado'], 404);
            return;
        }

        $inquilino = $this->inquilinoModel->obtenerPorSlug($slug);
        if (!$inquilino) {
            $this->jsonResponse(['ok' => false, 'error' => 'Inquilino no encontrado'], 404);
            return;
        }

        $s3 = new S3Helper('inquilinos');
        $archivos = $inquilino['archivos'] ?? [];
        if ($archivos === [] && !empty($inquilino['id'])) {
            $archivos = $this->inquilinoModel->obtenerArchivos((int) $inquilino['id']);
        }

        foreach ($archivos as &$archivo) {
            if (!empty($archivo['s3_key'])) {
                $archivo['url'] = $s3->getPresignedUrl($archivo['s3_key']);
            }
        }
        unset($archivo);

        $this->jsonResponse([
            'ok'          => true,
            'inquilino'   => $inquilino,
            'archivos'    => $archivos,
            'validaciones'=> $inquilino['validaciones'] ?? [],
        ]);
    }

    private function jsonResponse(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
