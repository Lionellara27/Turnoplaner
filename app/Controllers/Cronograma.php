<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Entities\Asignacion;
use App\Models\AsignacionModel;
use App\Models\AusenciaModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;
use DateTimeImmutable;

/**
 * HU-17: Mi Cronograma (portal del enfermero/a).
 *
 * Muestra los turnos asignados al enfermero/a logueado, por semana o por mes, con navegación
 * Anterior / Actual / Siguiente, y arriba un resumen "Hoy / Mañana" que no depende del período elegido.
 * Es la pantalla de destino del rol ENFERMERO tras el login (HU-02) y tras activar la cuenta (HU-01).
 *
 * Privacidad: el enfermero/a sale SIEMPRE de la sesión; la URL solo elige la vista y la fecha.
 *
 * HU-18: cada turno muestra el estado de su aviso de ausencia y, si todavía no empezó, el link "Informar ausencia".
 * HU-20: en el turno de hoy (o el que sigue en curso desde ayer) se marca el ingreso y se finaliza la guardia.
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

        // Turno que empezó ayer y sigue en curso, o que terminó hace poco y todavía puede finalizarse (HU-20)
        $turnoAyer = null;
        foreach ($cercanos as $t) {
            if ((string) $t->fecha < $hoy && ($t->estadoTurno($ahora) === 'en_curso' || $t->puedeMarcarSalida($ahora))) {
                $turnoAyer = $t;
            }
        }

        // Aviso de ausencia de cada turno de la página (activo o, si no, el último rechazado): una sola consulta
        $ids = [];
        foreach (array_merge($todos, $cercanos, $siguienteTurno !== null ? [$siguienteTurno] : []) as $t) {
            $ids[] = (int) $t->id;
        }
        $ausencias = (new AusenciaModel())->ultimaPorAsignaciones($ids);

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
            'turnoAyer'      => $turnoAyer,
            'ausencias'      => $ausencias,
        ]);
    }

    /**
     * HU-20: marca el ingreso al turno propio (desde 30 min antes del inicio hasta el fin).
     * Las reglas las controla AsignacionModel::marcarIngreso(); un turno ajeno o inexistente da 404.
     */
    public function ingreso(int $id): RedirectResponse
    {
        $resultado = (new AsignacionModel())->marcarIngreso((int) session()->get('usuario_id'), $id);

        if ($resultado['no_encontrada']) {
            throw PageNotFoundException::forPageNotFound('El turno solicitado no existe.');
        }
        if (! $resultado['ok']) {
            return redirect()->to(base_url('mi-cronograma'))->with('error', (string) $resultado['error']);
        }

        return redirect()->to(base_url('mi-cronograma'))
            ->with('success', 'Ingreso registrado a las ' . $resultado['hora'] . '. Al terminar, recuerde finalizar la guardia.');
    }

    /**
     * HU-20: finaliza la guardia (hasta 4 h después del fin) con las observaciones del pase (opcional, máx. 1000).
     */
    public function salida(int $id): RedirectResponse
    {
        $campo         = 'observaciones_pase-' . $id; // id del campo en la vista (uno por turno)
        $observaciones = $this->request->getPost('observaciones_pase');
        $observaciones = is_string($observaciones) ? trim($observaciones) : '';

        if (mb_strlen($observaciones) > Asignacion::MAX_OBSERVACIONES_PASE) {
            return $this->volverConErrores(
                [$campo => 'Las observaciones del pase de guardia no pueden superar los ' . Asignacion::MAX_OBSERVACIONES_PASE . ' caracteres.'],
                base_url('mi-cronograma')
            );
        }

        $resultado = (new AsignacionModel())->marcarSalida((int) session()->get('usuario_id'), $id, $observaciones);

        if ($resultado['no_encontrada']) {
            throw PageNotFoundException::forPageNotFound('El turno solicitado no existe.');
        }
        if (! $resultado['ok']) {
            return redirect()->to(base_url('mi-cronograma'))->with('error', (string) $resultado['error']);
        }

        return redirect()->to(base_url('mi-cronograma'))
            ->with('success', 'Guardia finalizada a las ' . $resultado['hora'] . '.' . ($observaciones !== '' ? ' Se guardaron las observaciones del pase.' : ''));
    }
}
