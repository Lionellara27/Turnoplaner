<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\AsignacionModel;
use DateTimeImmutable;

/**
 * HU-17: Mi Cronograma (portal del enfermero/a).
 *
 * Muestra los turnos asignados al enfermero/a logueado, por semana o por mes, con navegación
 * Anterior / Actual / Siguiente, y arriba un resumen "Hoy / Mañana" que no depende del período elegido.
 * Es la pantalla de destino del rol ENFERMERO tras el login (HU-02) y tras activar la cuenta (HU-01).
 *
 * Privacidad: el enfermero/a sale SIEMPRE de la sesión; la URL solo elige la vista y la fecha.
 */
class Cronograma extends BaseController
{
    private const VISTAS = ['semana', 'mes'];

    public function index(): string
    {
        $vista = $this->textoGet('vista');
        if (! in_array($vista, self::VISTAS, true)) {
            $vista = 'mes'; // Pantalla inicial en calendario mensual
        }

        $hoy    = date('Y-m-d');
        $manana = date('Y-m-d', strtotime('+1 day'));
        $ahora  = date('Y-m-d H:i:s');
        $base   = $this->fechaValida($this->textoGet('fecha')) ?? $hoy;

        $semanas = [];
        if ($vista === 'semana') {
            $desde     = AsignacionModel::lunesDe($base);
            $inicio    = new DateTimeImmutable($desde);
            $hasta     = $inicio->modify('+6 days')->format('Y-m-d');
            $anterior  = $inicio->modify('-7 days')->format('Y-m-d');
            $siguiente = $inicio->modify('+7 days')->format('Y-m-d');
            $actual    = AsignacionModel::lunesDe($hoy) === $desde;
            $periodo   = 'Semana del ' . fecha_ar($desde) . ' al ' . fecha_ar($hasta);
        } else {
            $inicio    = new DateTimeImmutable(substr($base, 0, 7) . '-01');
            $desde     = $inicio->format('Y-m-d');
            $hasta     = $inicio->modify('last day of this month')->format('Y-m-d');
            $anterior  = $inicio->modify('-1 month')->format('Y-m-d');
            $siguiente = $inicio->modify('+1 month')->format('Y-m-d');
            $actual    = substr($hoy, 0, 7) === substr($desde, 0, 7);
            $periodo   = AsignacionModel::nombreMes($desde);

            // Matriz de semanas (Lunes a Domingo) para dibujar la cuadrícula del calendario mensual
            $semanas = AsignacionModel::semanasDelMes($desde);
        }

        $usuarioId = (int) session()->get('usuario_id');
        $modelo    = new AsignacionModel();

        $diaPrevio = $inicio->modify('-1 day')->format('Y-m-d');
        $todos     = $modelo->delEnfermero($usuarioId, $diaPrevio, $hasta);

        $porDia       = [];
        $salidas      = [];
        $minutos      = 0;
        $minutosExtra = 0;
        $cantidad     = 0;
        foreach ($todos as $t) {
            if ($t->terminaAlDiaSiguiente()) {
                $salidas[substr((string) $t->fin, 0, 10)] = $t;
            }
            if ((string) $t->fecha < $desde) {
                continue;
            }
            $porDia[(string) $t->fecha][] = $t;
            $minutos += (int) $t->duracion_minutos;
            $minutosExtra += (int) ($t->minutos_extra ?? 0);
            $cantidad++;
        }

        $dias = [];
        if ($vista === 'semana') {
            for ($i = 0; $i < 7; $i++) {
                $dias[] = $inicio->modify("+{$i} days")->format('Y-m-d');
            }
        } else {
            $dias = array_keys($porDia);
        }

        $cercanos  = $modelo->delEnfermero($usuarioId, date('Y-m-d', strtotime('-1 day')), $manana);
        $enCurso   = null;
        $turnosHoy = [];
        $turnosMan = [];
        foreach ($cercanos as $t) {
            if ($t->estadoTurno($ahora) === 'en_curso') {
                $enCurso = $t;
            }
            if ((string) $t->fecha === $hoy) {
                $turnosHoy[] = $t;
            } elseif ((string) $t->fecha === $manana) {
                $turnosMan[] = $t;
            }
        }
        $siguienteTurno = ($turnosHoy === [] && $turnosMan === []) ? $modelo->siguienteProgramado($usuarioId, $ahora) : null;

        return view('cronograma/index', [
            'titulo'         => 'Mi cronograma - TurnoPlaner',
            'vista'          => $vista,
            'periodo'        => $periodo,
            'desde'          => $desde,
            'hasta'          => $hasta,
            'anterior'       => $anterior,
            'siguiente'      => $siguiente,
            'esActual'       => $actual,
            'hoy'            => $hoy,
            'manana'         => $manana,
            'ahora'          => $ahora,
            'dias'           => $dias,
            'semanas'        => $semanas,
            'porDia'         => $porDia,
            'salidas'        => $salidas,
            'cantidad'       => $cantidad,
            'minutos'        => $minutos,
            'minutosExtra'   => $minutosExtra,
            'enCurso'        => $enCurso,
            'turnosHoy'      => $turnosHoy,
            'turnosManana'   => $turnosMan,
            'siguienteTurno' => $siguienteTurno,
        ]);
    }
}
