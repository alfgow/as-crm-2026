<?php

declare(strict_types=1);

namespace App\Controllers\Api;

require_once __DIR__ . '/../../Helpers/NormalizadoHelper.php';
require_once __DIR__ . '/../../Helpers/S3Helper.php';
require_once __DIR__ . '/../../Helpers/SlugHelper.php';
require_once __DIR__ . '/../../Models/InquilinoModel.php';

use App\Helpers\NormalizadoHelper;
use App\Helpers\S3Helper;
use App\Helpers\SlugHelper;
use App\Models\InquilinoModel;

class InquilinoApiController
{
    public function __construct(private readonly InquilinoModel $inquilinoModel = new InquilinoModel())
    {
    }

    public function index(): void
    {
        $s3    = new S3Helper('inquilinos');
        $query = NormalizadoHelper::lower(trim((string) ($_GET['q'] ?? '')));

        $inquilinos = $query !== '' ? $this->inquilinoModel->searchByTerm($query) : [];
        $inquilinos = $this->mapInquilinosForApi($inquilinos, $s3);

        $this->jsonResponse([
            'ok'        => true,
            'query'     => $query,
            'total'     => count($inquilinos),
            'inquilinos'=> $inquilinos,
        ]);
    }

    public function mostrar(string $slug): void
    {
        $inquilino = $this->inquilinoModel->obtenerPorSlug($slug);
        if (!$inquilino) {
            $this->jsonResponse(['ok' => false, 'error' => 'Inquilino no encontrado'], 404);
            return;
        }

        $s3 = new S3Helper('inquilinos');
        $inquilino['archivos'] = $this->mapArchivosConUrl($inquilino['archivos'] ?? [], $s3);
        $inquilino['selfie_url'] = $this->extraerSelfieUrl($inquilino['archivos']);

        $this->jsonResponse([
            'ok'       => true,
            'inquilino'=> $inquilino,
        ]);
    }

    /**
     * @param array<int, array<string, mixed>> $inquilinos
     * @return array<int, array<string, mixed>>
     */
    private function mapInquilinosForApi(array $inquilinos, S3Helper $s3): array
    {
        foreach ($inquilinos as &$inquilino) {
            $archivos = $inquilino['archivos'] ?? [];
            $archivos = $this->mapArchivosConUrl($archivos, $s3);

            $selfieUrl = $this->extraerSelfieUrl($archivos);

            $profile = $inquilino['profile'] ?? [];
            $nombreCompleto = $this->resolverNombreCompleto($profile);
            $pk = (string) ($profile['pk'] ?? '');
            $id = trim(str_replace(['inq#', 'obl#', 'fia#'], '', $pk));
            $slugBase = SlugHelper::fromName($nombreCompleto !== '' ? $nombreCompleto : $pk);
            $profile['slug'] = $id !== '' ? $id . '-' . $slugBase : $slugBase;

            $inquilino = [
                'profile'    => $profile,
                'selfie_url' => $selfieUrl,
                'archivos'   => $archivos,
            ];
        }
        unset($inquilino);

        return $inquilinos;
    }

    /**
     * @param array<int, array<string, mixed>> $archivos
     * @return array<int, array<string, mixed>>
     */
    private function mapArchivosConUrl(array $archivos, S3Helper $s3): array
    {
        foreach ($archivos as &$archivo) {
            if (!empty($archivo['s3_key'])) {
                $archivo['url'] = $s3->getPresignedUrl($archivo['s3_key']);
            }
        }
        unset($archivo);

        return $archivos;
    }

    /**
     * @param array<int, array<string, mixed>> $archivos
     */
    private function extraerSelfieUrl(array $archivos): ?string
    {
        foreach ($archivos as $archivo) {
            if (strtolower((string) ($archivo['tipo'] ?? '')) === 'selfie') {
                if (!empty($archivo['url'])) {
                    return (string) $archivo['url'];
                }
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $profile
     */
    private function resolverNombreCompleto(array $profile): string
    {
        if (!empty($profile['nombre'])) {
            return trim((string) $profile['nombre']);
        }

        return trim(
            ($profile['nombre_inquilino'] ?? '') . ' ' .
            ($profile['apellidop_inquilino'] ?? '') . ' ' .
            ($profile['apellidom_inquilino'] ?? '')
        );
    }

    private function jsonResponse(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
