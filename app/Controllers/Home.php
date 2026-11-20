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
 * Muestra métricas del plantel, alertas de cobertura y calendario mensual de solo visualización por sector.
 */
class Home extends BaseController
{
    public function index(): string|RedirectResponse
    {
        if (session()->get('rol') !== 'ADMIN') {
            return redirect()->to(base_url('mi-cronograma'));
        }

        $usuarioModelo = new UsuarioModel();
        $sectores      = (new SectorModel())->activos();

        // Sector seleccionado por botón (?sector=ID), por defecto la primera
        $sector = null;
        $pedido = (int) $this->textoGet('sector');
        foreach ($sectores as $s) {
            if ((int) $s->id === $pedido) {
                $sector = $s;
            }
        }
        $sector ??= $sectores[0] ?? null;

        // Tipos de turno activos de ese sector
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
        $semanas = AsignacionModel::semanasDelMes($anioMes);

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
                    $activos   = array_filter($asignados, static fn ($a): bool => $a->cuentaEnDotacion());
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
            'nombreMes'        => AsignacionModel::nombreMes($anioMes),
            'semanas'          => $semanas,
            'anteriorMes'      => $primerDia->modify('-1 month')->format('Y-m'),
            'siguienteMes'     => $primerDia->modify('+1 month')->format('Y-m'),
            'hoy'              => $hoy,
            'grilla'           => $grilla,
            'alertasCobertura' => $alertasCobertura,
            // HU-16: aviso de ausencias pendientes de resolver
            'ausenciasPendientes' => (new \App\Models\AusenciaModel())->contarPendientes(),
        ]);
    }
}