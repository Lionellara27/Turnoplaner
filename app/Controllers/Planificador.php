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
 * HU-14: cuadrícula semanal de turnos por sector.
 * Filas: tipos de turno activos. Columnas: lunes a domingo.
 * Cada celda muestra los enfermeros/as asignados y "asignados / dotación mínima".
 */
class Planificador extends BaseController
{
    public function index(): string
    {
        $sectores = (new SectorModel())->activos();
        $tipos    = (new TipoTurnoModel())->activos();

        $sector = null;
        $pedido = (int) $this->textoGet('sector');
        foreach ($sectores as $s) {
            if ((int) $s->id === $pedido) {
                $sector = $s;
            }
        }
        $sector ??= $sectores[0] ?? null;

        $base   = $this->fechaValida($this->textoGet('semana')) ?? date('Y-m-d');
        $lunes  = AsignacionModel::lunesDe($base);

        $inicioSemana = new DateTimeImmutable($lunes);
        $dias         = [];
        for ($i = 0; $i < 7; $i++) {
            $dias[] = $inicioSemana->modify("+{$i} days")->format('Y-m-d');
        }

        $grilla = $sector instanceof Sector ? (new AsignacionModel())->semanaDeSector((int) $sector->id, $lunes) : [];

        // Historia: si en la semana hay turnos de un tipo que después se dio de baja, su fila también se muestra.
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

        return view('admin/planificador/index', [
            'titulo'   => 'Planificador semanal - TurnoPlaner',
            'sectores' => $sectores,
            'tipos'    => $tipos,
            'sector'   => $sector,
            'dias'     => $dias,
            'lunes'    => $lunes,
            'anterior' => $inicioSemana->modify('-7 days')->format('Y-m-d'),
            'siguiente' => $inicioSemana->modify('+7 days')->format('Y-m-d'),
            'hoy'      => date('Y-m-d'),
            'grilla'   => $grilla,
            // Resumen de balanceo: horas de la semana de cada enfermero/a activo (todos los sectores) contra su máximo.
            'enfermeros'    => (new UsuarioModel())->enfermerosActivos(),
            'minutosSemana' => (new AsignacionModel())->minutosSemanaPorEnfermero($lunes),
            'ahora'    => date('Y-m-d H:i:s'),
        ]);
    }
}
