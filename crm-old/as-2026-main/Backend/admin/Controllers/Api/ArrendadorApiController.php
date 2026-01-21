<?php

declare(strict_types=1);

namespace App\Controllers\Api;

require_once __DIR__ . '/../../Helpers/S3Helper.php';
require_once __DIR__ . '/../../Models/ArrendadorModel.php';
require_once __DIR__ . '/../../Models/AsesorModel.php';

use App\Helpers\S3Helper;
use App\Models\ArrendadorModel;
use App\Models\AsesorModel;

class ArrendadorApiController
{
    public function __construct(
        private readonly ArrendadorModel $arrendadorModel = new ArrendadorModel(),
        private readonly AsesorModel $asesorModel = new AsesorModel(),
    ) {
    }

    public function detalle(string $slug): void
    {
        $slug = trim($slug);
        $arrendador = $slug !== '' ? $this->arrendadorModel->obtenerPorSlug($slug) : null;

        if (!$arrendador && preg_match('/-(\d+)$/', $slug, $matches)) {
            $arrendador = $this->arrendadorModel->obtenerPorId((int) $matches[1]);
        }

        if (!$arrendador && ctype_digit($slug)) {
            $arrendador = $this->arrendadorModel->obtenerPorId((int) $slug);
        }

        if (!$arrendador) {
            $this->jsonResponse(['error' => 'not_found'], 404);
            return;
        }

        $s3 = new S3Helper('arrendadores');
        foreach ($arrendador['archivos'] as &$archivo) {
            if (!empty($archivo['s3_key'])) {
                $archivo['url'] = $s3->getPresignedUrl($archivo['s3_key']);
            }
        }
        unset($archivo);

        $asesorActual = null;
        $profile = $arrendador['profile'] ?? [];
        if (!empty($profile['id_asesor'])) {
            $asesorActual = $this->asesorModel->find((int) $profile['id_asesor']);
        }

        $this->jsonResponse([
            'arrendador'     => $arrendador,
            'asesor_actual'  => $asesorActual,
            'asesores'       => $this->asesorModel->all(),
        ]);
    }

    public function arrendadoresPorAsesor(int $id): void
    {
        $this->jsonResponse([
            'arrendadores' => $this->arrendadorModel->obtenerPorAsesor($id),
        ]);
    }

    private function jsonResponse(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
