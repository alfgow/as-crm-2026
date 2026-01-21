<?php

declare(strict_types=1);

namespace App\Controllers\Api;

require_once __DIR__ . '/../../Models/InquilinoModel.php';
require_once __DIR__ . '/../../Models/PolizaModel.php';

use App\Models\InquilinoModel;
use App\Models\PolizaModel;
use DateTimeImmutable;
use DateTimeInterface;
use Throwable;

class DashboardApiController
{
    public function __construct(
        private readonly InquilinoModel $inquilinoModel = new InquilinoModel(),
        private readonly PolizaModel $polizaModel = new PolizaModel(),
    ) {
    }

    public function index(): void
    {
        $totalInquilinosNuevos = $this->obtenerTotalInquilinosNuevos();
        $inquilinosNuevos      = $this->obtenerInquilinosNuevosConSelfie();
        $vencimientosProximos  = $this->obtenerVencimientosProximos();

        $vencimientosProximos = $this->ordenarVencimientosProximos($vencimientosProximos);
        $vencimientosProximos = $this->mapearVencimientosConFecha($vencimientosProximos);

        $payload = [
            'kpis' => [
                'total_inquilinos_nuevos' => $totalInquilinosNuevos,
                'ultima_poliza_emitida'   => $this->obtenerUltimaPolizaEmitida(),
            ],
            'inquilinos_nuevos'     => $inquilinosNuevos,
            'vencimientos_proximos' => $vencimientosProximos,
        ];

        $this->jsonResponse($payload);
    }

    private function obtenerTotalInquilinosNuevos(): int
    {
        return (int) $this->inquilinoModel->contarInquilinosNuevos();
    }

    private function obtenerInquilinosNuevosConSelfie(): array
    {
        return (array) $this->inquilinoModel->getInquilinosNuevosConSelfie();
    }

    private function obtenerVencimientosProximos(): array
    {
        return (array) $this->polizaModel->obtenerVencimientosProximos();
    }

    private function obtenerUltimaPolizaEmitida(): string
    {
        return (string) $this->polizaModel->obtenerUltimaPolizaEmitida();
    }

    private function ordenarVencimientosProximos(array $vencimientos): array
    {
        $normalizados = array_map(function (array $poliza): array {
            $poliza['fecha_vencimiento_normalizada'] = $this->normalizarFechaVencimiento($poliza);

            return $poliza;
        }, $vencimientos);

        usort(
            $normalizados,
            function (array $a, array $b): int {
                $fechaA = $a['fecha_vencimiento_normalizada'] ?? null;
                $fechaB = $b['fecha_vencimiento_normalizada'] ?? null;

                if (!$fechaA instanceof DateTimeInterface && !$fechaB instanceof DateTimeInterface) {
                    return 0;
                }

                if (!$fechaA instanceof DateTimeInterface) {
                    return 1;
                }

                if (!$fechaB instanceof DateTimeInterface) {
                    return -1;
                }

                return $fechaA->getTimestamp() <=> $fechaB->getTimestamp();
            }
        );

        return $normalizados;
    }

    private function mapearVencimientosConFecha(array $vencimientos): array
    {
        return array_map(function (array $poliza): array {
            $fecha = $poliza['fecha_vencimiento_normalizada'] ?? null;
            if ($fecha instanceof DateTimeInterface) {
                $poliza['fecha_vencimiento_normalizada'] = $fecha->format('Y-m-d');
            } else {
                $poliza['fecha_vencimiento_normalizada'] = null;
            }

            return $poliza;
        }, $vencimientos);
    }

    private function normalizarFechaVencimiento(array $poliza): ?DateTimeImmutable
    {
        $fechaFin = $poliza['fecha_fin'] ?? null;

        if (!empty($fechaFin)) {
            try {
                return (new DateTimeImmutable((string) $fechaFin))->setTime(0, 0);
            } catch (Throwable $exception) {
                // Continuar con las demás opciones de normalización
            }
        }

        $vigencia = $poliza['vigencia'] ?? null;

        if (is_string($vigencia)) {
            $patron = '/al\s+(\d{1,2})\s+de\s+([[:alpha:]]+)\s+de\s+(\d{4})/iu';

            if (preg_match($patron, $vigencia, $coincidencias)) {
                $dia       = (int) $coincidencias[1];
                $mesNombre = mb_strtolower($coincidencias[2], 'UTF-8');
                $anio      = (int) $coincidencias[3];

                $meses = [
                    'enero'      => 1,
                    'febrero'    => 2,
                    'marzo'      => 3,
                    'abril'      => 4,
                    'mayo'       => 5,
                    'junio'      => 6,
                    'julio'      => 7,
                    'agosto'     => 8,
                    'septiembre' => 9,
                    'setiembre'  => 9,
                    'octubre'    => 10,
                    'noviembre'  => 11,
                    'diciembre'  => 12,
                ];

                if (array_key_exists($mesNombre, $meses)) {
                    $fecha = sprintf('%04d-%02d-%02d', $anio, $meses[$mesNombre], $dia);

                    try {
                        return (new DateTimeImmutable($fecha))->setTime(0, 0);
                    } catch (Throwable $exception) {
                        // Continuar con las demás opciones de normalización
                    }
                }
            }
        }

        $mes  = $poliza['mes_vencimiento'] ?? null;
        $anio = $poliza['year_vencimiento'] ?? null;

        if ($mes !== null && $anio !== null) {
            $mesFormateado   = str_pad((string) $mes, 2, '0', STR_PAD_LEFT);
            $fechaConstruida = sprintf('%s-%s-01', (string) $anio, $mesFormateado);

            try {
                return (new DateTimeImmutable($fechaConstruida))->setTime(0, 0);
            } catch (Throwable $exception) {
                return null;
            }
        }

        return null;
    }

    private function jsonResponse(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
    }
}
