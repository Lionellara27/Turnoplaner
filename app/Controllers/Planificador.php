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
 * HU-14: Calendario mensual de turnos por zona/sector (pantalla inicial del ADMIN).
 * Muestra todos los días del mes en cuadrícula de calendario, botones para cambiar entre zonas,
 * y dentro de cada día los horarios del sector con los enfermeros asignados y dotación mínima.
 */
class Planificador extends BaseController
{
    private const MESES = [
        1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
        5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
        9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
    ];

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

        // Solo los tipos de turno activos de la zona seleccionada
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

        // Armar semanas del calendario (de lunes a domingo) cubriendo todo el mes
        $cursor  = new DateTimeImmutable(AsignacionModel::lunesDe($primerDia->format('Y-m-d')));
        $finCal  = (new DateTimeImmutable(AsignacionModel::lunesDe($ultimoDia->format('Y-m-d'))))->modify('+6 days');
        $semanas = [];
        while ($cursor <= $finCal) {
            $fila = [];
            for ($i = 0; $i < 7; $i++) {
                $fila[] = $cursor->modify("+{$i} days")->format('Y-m-d');
            }
            $semanas[] = $fila;
            $cursor    = $cursor->modify('+7 days');
        }

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

        // Turnos del mes por enfermero (en todos los sectores) para el modal de ficha y detalle horario
        $turnosPorEnfermero = [];
        $rowsMes = $asignacionModel
            ->select('asignaciones.*, sectores.nombre AS sector_nombre, tipos_turno.nombre AS tipo_nombre, tipos_turno.hora_inicio, tipos_turno.hora_fin, tipos_turno.color AS tipo_color')
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
                'horario'  => substr((string) ($asig->hora_inicio ?? ''), 0, 5) . ' - ' . substr((string) ($asig->hora_fin ?? ''), 0, 5),
                'color'    => (string) ($asig->tipo_color ?? '#1B4D3E'),
                'duracion' => AsignacionModel::horasTexto((int) $asig->duracion_minutos),
                'extra'    => $asig->esExtra() ? AsignacionModel::horasTexto((int) $asig->minutos_extra) : null,
            ];
        }

        $nombreMes = self::MESES[(int) $primerDia->format('n')] . ' ' . $primerDia->format('Y');

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
            'resumenMes'         => $asignacionModel->minutosMesPorEnfermero($anioMes),
            'turnosPorEnfermero' => $turnosPorEnfermero,
            'ahora'              => date('Y-m-d H:i:s'),
        ]);
    }
}