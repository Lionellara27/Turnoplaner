<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\AsignacionModel;
use DateTimeImmutable;

/**
 * HU-19: historial personal de horas y guardias (portal del enfermero/a).
 *
 * Período por mes (?mes=AAAA-MM, por defecto el actual) o por año (?anio=AAAA). El formulario de filtro
 * envía anio + mes_numero (vacío = todo el año) por GET, así funciona sin JavaScript.
 * KPIs y detalle salen de AsignacionModel::historialDeUsuario() / resumenHistorial().
 *
 * Privacidad: el enfermero/a sale siempre de la sesión.
 */
class Historial extends BaseController
{
    public function index(): string
    {
        $hoy       = date('Y-m-d');
        $anioHoy   = (int) date('Y');
        $porAnio   = false;
        $anioMes   = substr($hoy, 0, 7);

        $mes  = $this->textoGet('mes');
        $anio = $this->textoGet('anio');
        $num  = $this->textoGet('mes_numero');

        if ($this->fechaValida($mes . '-01') !== null && strlen($mes) === 7) {
            $anioMes = $mes;
        } elseif (preg_match('/^\d{4}$/', $anio) === 1 && $this->fechaValida($anio . '-01-01') !== null) {
            if (preg_match('/^(0?[1-9]|1[0-2])$/', $num) === 1) {
                $anioMes = $anio . '-' . str_pad((string) (int) $num, 2, '0', STR_PAD_LEFT);
            } else {
                $porAnio = true;
                $anioMes = $anio . '-01';
            }
        }

        $inicio = new DateTimeImmutable($anioMes . '-01');
        if ($porAnio) {
            $desde     = $inicio->format('Y-01-01');
            $hasta     = $inicio->format('Y-12-31');
            $periodo   = 'Año ' . $inicio->format('Y');
            $anterior  = ['anio' => (int) $inicio->format('Y') - 1];
            $siguiente = ['anio' => (int) $inicio->format('Y') + 1];
            $esActual  = (int) $inicio->format('Y') === $anioHoy;
        } else {
            $desde     = $inicio->format('Y-m-d');
            $hasta     = $inicio->modify('last day of this month')->format('Y-m-d');
            $periodo   = AsignacionModel::nombreMes($desde);
            $anterior  = ['mes' => $inicio->modify('-1 month')->format('Y-m')];
            $siguiente = ['mes' => $inicio->modify('+1 month')->format('Y-m')];
            $esActual  = $anioMes === substr($hoy, 0, 7);
        }

        $usuarioId = (int) session()->get('usuario_id');
        $modelo    = new AsignacionModel();
        $filas     = $modelo->historialDeUsuario($usuarioId, $desde, $hasta);
        $resumen   = $modelo->resumenHistorial($usuarioId, $desde, $hasta, $filas);

        $anioElegido = (int) $inicio->format('Y');
        $anios       = range(min($anioHoy - 3, $anioElegido), max($anioHoy + 1, $anioElegido));

        return view('historial/index', [
            'titulo'      => 'Mi historial - TurnoPlaner',
            'porAnio'     => $porAnio,
            'periodo'     => $periodo,
            'anioElegido' => $anioElegido,
            'mesElegido'  => $porAnio ? 0 : (int) $inicio->format('n'),
            'anios'       => array_reverse($anios),
            'anterior'    => $anterior,
            'siguiente'   => $siguiente,
            'esActual'    => $esActual,
            'filas'       => $filas,
            'resumen'     => $resumen,
            'hoy'         => $hoy,
        ]);
    }
}
