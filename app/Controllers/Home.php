<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Entities\Sector;
use App\Models\AsignacionModel;
use App\Models\SectorModel;
use App\Models\TipoTurnoModel;
use App\Models\UsuarioModel;
use CodeIgniter\HTTP\RedirectResponse;
use DateTimeImmutable;

/**
 * Panel general del Administrador (HU-02: destino del rol ADMIN tras el login).
 * Muestra métricas del plantel, alertas de cobertura y calendario mensual de solo visualización por zona.
 */
class Home extends BaseController
{
    private const MESES = [
        1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
        5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
        9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
    ];

    public function index(): string|RedirectResponse
    {
        if (session()->get('rol') !== 'ADMIN') {
            return redirect()->to(base_url('mi-cronograma'));
        }

        $usuarioModelo = new UsuarioModel();
        $sectores      = (new SectorModel())->activos();

        // Zona seleccionada por botón (?sector=ID), por defecto la primera
        $sector = null;
        $pedido = (int) $this->textoGet('sector');
        foreach ($sectores as $s) {
            if ((int) $s->id === $pedido) {
                $sector = $s;
            }
        }
        $sector ??= $sectores[0] ?? null;

        // Tipos de turno activos de esa zona
        $tipos = $sector instanceof Sector
            ? (new TipoTurnoModel())->activosPorSector((int) $sector->id)
            : [];

        // Mes seleccionado (?mes=YYYY-MM), por defecto el actual
        $mesParam = $this->textoGet('mes');
        $anioMes  = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $mesParam) === 1 ? $mesParam : date('Y-m');

        $primerDia = new DateTimeImmutable($anioMes . '-01');
        $ultimoDia = $primerDia->modify('last day of this month');
        $hoy       = date('Y-m-d');

        // Matriz de semanas (Lunes a Domingo) para dibujar la cuadrícula mensual
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

        $grilla = $sector instanceof Sector
            ? (new AsignacionModel())->mesDeSector((int) $sector->id, $anioMes)
            : [];

        // Alertas de cobertura (turnos de hoy en adelante en el mes que no alcanzan la dotación mínima)
        $alertasCobertura = [];
        if ($sector instanceof Sector && $tipos !== []) {
            $minimo     = (int) $sector->dotacion_minima;
            $diaIter    = new DateTimeImmutable(max($hoy, $primerDia->format('Y-m-d')));
            $limiteIter = $ultimoDia;

            while ($diaIter <= $limiteIter) {
                $fechaStr = $diaIter->format('Y-m-d');
                foreach ($tipos as $t) {
                    $asignados = $grilla[$fechaStr][(int) $t->id] ?? [];
                    $activos   = array_filter($asignados, static fn ($a): bool => ! $a->enfermeroDeBaja());
                    $cubiertos = count($activos);
                    if ($cubiertos < $minimo) {
                        $alertasCobertura[] = [
                            'fecha'     => $fechaStr,
                            'turno'     => (string) $t->nombre,
                            'horario'   => $t->rangoTexto(),
                            'cubiertos' => $cubiertos,
                            'minimo'    => $minimo,
                        ];
                    }
                }
                $diaIter = $diaIter->modify('+1 day');
            }
        }

        return view('home/dashboard', [
            'titulo'           => 'Panel de gestión - TurnoPlaner',
            'nombre'           => (string) session()->get('nombre'),
            'activos'          => $usuarioModelo->where('rol', 'ENFERMERO')->countAllResults(),
            'pendientes'       => $usuarioModelo->where('rol', 'ENFERMERO')->where('debe_cambiar_pass', 1)->countAllResults(),
            'bajas'            => $usuarioModelo->onlyDeleted()->where('rol', 'ENFERMERO')->countAllResults(),
            'sectores'         => $sectores,
            'sector'           => $sector,
            'tipos'            => $tipos,
            'anioMes'          => $anioMes,
            'nombreMes'        => self::MESES[(int) $primerDia->format('n')] . ' ' . $primerDia->format('Y'),
            'semanas'          => $semanas,
            'anteriorMes'      => $primerDia->modify('-1 month')->format('Y-m'),
            'siguienteMes'     => $primerDia->modify('+1 month')->format('Y-m'),
            'hoy'              => $hoy,
            'grilla'           => $grilla,
            'alertasCobertura' => $alertasCobertura,
        ]);
    }
}