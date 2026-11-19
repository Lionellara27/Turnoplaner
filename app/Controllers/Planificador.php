<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Entities\Sector;
use App\Models\AsignacionModel;
use App\Models\SectorModel;
use App\Models\TipoTurnoModel;
use App\Models\UsuarioModel;
use DateTimeImmutable;

/**
 * HU-14: Calendario mensual de turnos por sector (pantalla inicial del ADMIN).
 * Muestra todos los días del mes en cuadrícula de calendario, botones para cambiar entre sectores,
 * y dentro de cada día los horarios del sector con los enfermeros asignados y dotación mínima.
 */
class Planificador extends BaseController
{
    public function index(): string
    {
        $sectores = (new SectorModel())->activos();

        $sector = null;
        $pedido = (int) $this->textoGet('sector');
        foreach ($sectores as $s) {
            if ((int) $s->id === $pedido) {
                $sector = $s;
            }
        }
        $sector ??= $sectores[0] ?? null;

        // Solo los tipos de turno activos del sector seleccionado
        $tipos = $sector instanceof Sector
            ? (new TipoTurnoModel())->activosPorSector((int) $sector->id)
            : [];

        // Mes seleccionado en formato YYYY-MM (por defecto el mes actual; acepta también ?semana=YYYY-MM-DD por compatibilidad)
        $mesParam = $this->textoGet('mes');
        if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $mesParam) === 1) {
            $anioMes = $mesParam;
        } elseif (($semFecha = $this->fechaValida($this->textoGet('semana'))) !== null) {
            $anioMes = substr($semFecha, 0, 7);
        } else {
            $anioMes = date('Y-m');
        }

        $primerDia = new DateTimeImmutable($anioMes . '-01');
        $ultimoDia = $primerDia->modify('last day of this month');
        $hoy       = date('Y-m-d');

        // Semanas del calendario (de lunes a domingo) cubriendo todo el mes
        $semanas = AsignacionModel::semanasDelMes($anioMes);

        $asignacionModel = new AsignacionModel();
        $grilla          = $sector instanceof Sector ? $asignacionModel->mesDeSector((int) $sector->id, $anioMes) : [];

        // Historia: si en el mes hay turnos de un tipo que después se dio de baja, lo incluimos
        $idsVisibles = array_map(static fn ($t): int => (int) $t->id, $tipos);
        $idsConTurno = [];
        foreach ($grilla as $porTipo) {
            $idsConTurno += array_fill_keys(array_keys($porTipo), true);
        }
        $faltantes = array_diff(array_keys($idsConTurno), $idsVisibles);
        if ($faltantes !== []) {
            $tipos = array_merge($tipos, (new TipoTurnoModel())->withDeleted()->whereIn('id', $faltantes)->findAll());
            usort($tipos, static fn ($a, $b): int => strcmp((string) $a->hora_inicio, (string) $b->hora_inicio));
        }

        // Turnos del mes por enfermero (en todos los sectores) para el modal de ficha y detalle horario.
        // El horario sale de asignaciones.inicio/fin (lo que se asignó de verdad), no del horario ACTUAL del
        // tipo de turno: si después se edita el tipo, la ficha no debe mostrar un horario que nunca tuvo.
        $turnosPorEnfermero = [];
        $rowsMes = $asignacionModel
            ->select('asignaciones.*, sectores.nombre AS sector_nombre, tipos_turno.nombre AS tipo_nombre, tipos_turno.color AS tipo_color')
            ->join('sectores', 'sectores.id = asignaciones.sector_id', 'left')
            ->join('tipos_turno', 'tipos_turno.id = asignaciones.tipo_turno_id', 'left')
            ->where('asignaciones.fecha >=', $primerDia->format('Y-m-d'))
            ->where('asignaciones.fecha <=', $ultimoDia->format('Y-m-d'))
            ->orderBy('asignaciones.fecha', 'ASC')
            ->orderBy('asignaciones.inicio', 'ASC')
            ->findAll();

        foreach ($rowsMes as $asig) {
            $uid = (int) $asig->usuario_id;
            $turnosPorEnfermero[$uid][] = [
                'fecha'    => fecha_ar((string) $asig->fecha),
                'dia_sem'  => ucfirst(dia_semana((string) $asig->fecha)),
                'sector'   => (string) ($asig->sector_nombre ?? 'Sector'),
                'turno'    => (string) ($asig->tipo_nombre ?? 'Turno'),
                'horario'  => $asig->horaInicio() . ' - ' . $asig->horaFin(),
                'color'    => (string) ($asig->tipo_color ?? '#1B4D3E'),
                'duracion' => AsignacionModel::horasTexto((int) $asig->duracion_minutos),
                'extra'    => $asig->esExtra() ? AsignacionModel::horasTexto((int) $asig->minutos_extra) : null,
            ];
        }

        $nombreMes = AsignacionModel::nombreMes($anioMes);

        return view('admin/planificador/index', [
            'titulo'             => 'Planificador mensual - TurnoPlaner',
            'sectores'           => $sectores,
            'tipos'              => $tipos,
            'sector'             => $sector,
            'anioMes'            => $anioMes,
            'nombreMes'          => $nombreMes,
            'semanas'            => $semanas,
            'anteriorMes'        => $primerDia->modify('-1 month')->format('Y-m'),
            'siguienteMes'       => $primerDia->modify('+1 month')->format('Y-m'),
            'hoy'                => $hoy,
            'fechaMaxima'        => AsignacionModel::fechaMaximaHabilitada(),
            'grilla'             => $grilla,
            'enfermeros'         => (new UsuarioModel())->enfermerosActivos(),
            'especialidades'     => UsuarioModel::ESPECIALIDADES,
            'resumenMes'         => $asignacionModel->minutosMesPorEnfermero($anioMes),
            // Horas de la semana actual contra el tope semanal (HU-14: balanceo de carga, DESIGN 4.9).
            'resumenSemana'      => $asignacionModel->minutosSemanaPorEnfermero(AsignacionModel::lunesDe($hoy)),
            'turnosPorEnfermero' => $turnosPorEnfermero,
            'ahora'              => date('Y-m-d H:i:s'),
        ]);
    }
}