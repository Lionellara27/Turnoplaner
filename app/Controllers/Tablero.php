<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\AsignacionModel;
use App\Models\AusenciaModel;
use DateTimeImmutable;

/**
 * HU-15: Tablero de control de cobertura.
 *
 * Vista semanal (lunes a domingo, ?semana=YYYY-MM-DD) o de un día (?dia=YYYY-MM-DD) de todos los sectores
 * activos, con semáforo por franja: "Cubierto" (asignados >= dotación mínima) o "Falta personal".
 * Se recalcula en cada carga: una ausencia informada descuenta al enfermero/a apenas se reporta.
 * Cada alerta vigente lleva a "Asignar reemplazo" (si la franja tiene una ausencia pendiente) o al Planificador.
 */
class Tablero extends BaseController
{
    public function index(): string
    {
        $hoy    = date('Y-m-d');
        $modelo = new AsignacionModel();

        $dia       = $this->fechaValida($this->textoGet('dia'));
        $modo      = $dia !== null ? 'dia' : 'semana';
        $soloAlert = $this->textoGet('alertas') === '1';

        if ($modo === 'dia') {
            $cobertura = $modelo->coberturaDia($dia);
            $base      = new DateTimeImmutable($dia);
            $nav       = [
                'anterior'  => ['dia' => $base->modify('-1 day')->format('Y-m-d')],
                'actual'    => ['dia' => $hoy],
                'siguiente' => ['dia' => $base->modify('+1 day')->format('Y-m-d')],
                'esActual'  => $dia === $hoy,
            ];
            $periodo = ucfirst(fecha_larga($dia));
        } else {
            $lunes     = AsignacionModel::lunesDe($this->fechaValida($this->textoGet('semana')) ?? $hoy);
            $cobertura = $modelo->coberturaSemana($lunes);
            $base      = new DateTimeImmutable($lunes);
            $nav       = [
                'anterior'  => ['semana' => $base->modify('-7 days')->format('Y-m-d')],
                'actual'    => ['semana' => AsignacionModel::lunesDe($hoy)],
                'siguiente' => ['semana' => $base->modify('+7 days')->format('Y-m-d')],
                'esActual'  => $lunes === AsignacionModel::lunesDe($hoy),
            ];
            $periodo = 'Semana del ' . fecha_ar($cobertura['desde']) . ' al ' . fecha_ar($cobertura['hasta']);
        }

        // Destino de cada alerta y totales del resumen (solo cuentan las franjas que todavía no terminaron).
        $resumen = ['alertas' => 0, 'cubiertas' => 0, 'franjas' => 0, 'alertas_terminadas' => 0];
        foreach ($cobertura['sectores'] as $i => $fila) {
            $sid = (int) $fila['sector']->id;
            foreach ($fila['celdas'] as $fecha => $porTipo) {
                foreach ($porTipo as $tid => $celda) {
                    $url = null;
                    if ($celda['alerta'] && ! $celda['terminado']) {
                        $url = $celda['ausencia_pendiente_id'] !== null
                            ? base_url('admin/ausencias/' . (int) $celda['ausencia_pendiente_id'] . '/reemplazo')
                            : base_url('admin/planificador') . '?' . http_build_query(['sector' => $sid, 'mes' => substr((string) $fecha, 0, 7)], '', '&amp;');
                    }
                    $cobertura['sectores'][$i]['celdas'][$fecha][$tid]['url'] = $url;

                    if (! $celda['aplica']) {
                        continue;
                    }
                    if ($celda['terminado']) {
                        $resumen['alertas_terminadas'] += $celda['alerta'] ? 1 : 0;
                        continue;
                    }
                    $resumen['franjas']++;
                    $resumen[$celda['alerta'] ? 'alertas' : 'cubiertas']++;
                }
            }
        }

        return view('admin/tablero/index', [
            'titulo'              => 'Tablero de cobertura - TurnoPlaner',
            'modo'                => $modo,
            'soloAlertas'         => $soloAlert,
            'cobertura'           => $cobertura,
            'resumen'             => $resumen,
            'periodo'             => $periodo,
            'nav'                 => $nav,
            'hoy'                 => $hoy,
            'pendientesTotales'   => (new AusenciaModel())->contarPendientes(),
        ]);
    }
}
