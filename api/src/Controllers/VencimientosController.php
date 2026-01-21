<?php
namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\PolizaRepository;
use DateTimeImmutable;
use DateTimeInterface;

final class VencimientosController {
  private PolizaRepository $polizas;

  public function __construct(PolizaRepository $polizas) {
    $this->polizas = $polizas;
  }

  public function index(Request $req, Response $res): void {
    $query = $req->getQuery();
    $mes = isset($query['mes']) ? (int)$query['mes'] : null;
    $anio = isset($query['anio']) ? (int)$query['anio'] : null;

    if ($mes && $anio) {
      $polizas = $this->polizas->findVencimientosPorMesAnio($mes, $anio);
    } else {
      $polizas = $this->polizas->findVencimientosProximos();

      $mesActual = (int)date('n');
      $anioActual = (int)date('Y');
      $mes = $mesActual + 1;
      $anio = $anioActual;
      if ($mes > 12) {
        $mes = 1;
        $anio++;
      }
    }

    $polizas = array_map(function (array $poliza): array {
      $fecha = $this->normalizarFechaVencimiento($poliza);
      $poliza['fecha_vencimiento_normalizada'] = $fecha;
      $poliza['fecha_vencimiento_formateada'] = $fecha instanceof DateTimeImmutable
        ? $fecha->format('d/m/Y')
        : null;

      return $poliza;
    }, $polizas);

    usort($polizas, function (array $a, array $b): int {
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
    });

    $res->json([
      'data' => $polizas,
      'meta' => [
        'requestId' => $req->getRequestId(),
        'count' => count($polizas),
        'mes' => $mes,
        'anio' => $anio,
      ],
      'errors' => [],
    ]);
  }

  private function normalizarFechaVencimiento(array $poliza): ?DateTimeImmutable {
    $fechaFin = $poliza['fecha_fin'] ?? null;

    if (!empty($fechaFin)) {
      try {
        return (new DateTimeImmutable((string)$fechaFin))->setTime(0, 0);
      } catch (\Throwable $exception) {
        // continuar con las demás opciones
      }
    }

    $vigencia = $poliza['vigencia'] ?? null;
    if (is_string($vigencia)) {
      $patron = '/al\\s+(\\d{1,2})\\s+de\\s+([[:alpha:]]+)\\s+de\\s+(\\d{4})/iu';

      if (preg_match($patron, $vigencia, $coincidencias)) {
        $dia = (int)$coincidencias[1];
        $mesNombre = mb_strtolower($coincidencias[2], 'UTF-8');
        $anio = (int)$coincidencias[3];

        $meses = [
          'enero' => 1,
          'febrero' => 2,
          'marzo' => 3,
          'abril' => 4,
          'mayo' => 5,
          'junio' => 6,
          'julio' => 7,
          'agosto' => 8,
          'septiembre' => 9,
          'setiembre' => 9,
          'octubre' => 10,
          'noviembre' => 11,
          'diciembre' => 12,
        ];

        if (array_key_exists($mesNombre, $meses)) {
          $fecha = sprintf('%04d-%02d-%02d', $anio, $meses[$mesNombre], $dia);
          try {
            return (new DateTimeImmutable($fecha))->setTime(0, 0);
          } catch (\Throwable $exception) {
            // continuar con las demás opciones
          }
        }
      }
    }

    $mes = $poliza['mes_vencimiento'] ?? null;
    $anio = $poliza['year_vencimiento'] ?? null;

    if ($mes !== null && $anio !== null) {
      $mesFormateado = str_pad((string)$mes, 2, '0', STR_PAD_LEFT);
      $fechaConstruida = sprintf('%s-%s-01', (string)$anio, $mesFormateado);
      try {
        return (new DateTimeImmutable($fechaConstruida))->setTime(0, 0);
      } catch (\Throwable $exception) {
        return null;
      }
    }

    return null;
  }
}
