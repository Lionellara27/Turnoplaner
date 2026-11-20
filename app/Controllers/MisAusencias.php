<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Entities\Asignacion;
use App\Models\AsignacionModel;
use App\Models\AusenciaModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * HU-18: aviso de ausencia imprevista (portal del enfermero/a).
 *
 * - "Mis ausencias": los avisos enviados con su estado (en gestión, reemplazo asignado o rechazado con la
 *   observación del administrador) y los próximos turnos sobre los que todavía puede avisar.
 * - Formulario: resumen del turno, motivo y descripción. El aviso queda PENDIENTE para el administrador (HU-16).
 *
 * Privacidad: el enfermero/a sale siempre de la sesión; un turno ajeno o inexistente da 404.
 * Las reglas (turno propio, futuro y sin otro aviso activo) las controla AusenciaModel::reportar().
 */
class MisAusencias extends BaseController
{
    /** Días hacia adelante en los que se listan turnos para avisar (los posteriores, desde Mi cronograma). */
    public const DIAS_PROXIMOS = 31;

    public function index(): string
    {
        $usuarioId = (int) session()->get('usuario_id');
        $ahora     = date('Y-m-d H:i:s');

        // Próximos turnos que todavía admiten un aviso: no empezaron y no tienen uno activo
        $proximos = array_values(array_filter(
            (new AsignacionModel())->delEnfermero($usuarioId, date('Y-m-d'), date('Y-m-d', strtotime('+' . self::DIAS_PROXIMOS . ' days'))),
            static fn (Asignacion $t): bool => (string) $t->inicio > $ahora && ! $t->tieneAusenciaActiva()
        ));

        return view('mis_ausencias/index', [
            'titulo'   => 'Mis ausencias - TurnoPlaner',
            'avisos'   => (new AusenciaModel())->listarDeUsuario($usuarioId),
            'proximos' => $proximos,
            'ahora'    => $ahora,
        ]);
    }

    /** Formulario del aviso para el turno $id. */
    public function nueva(int $id): string|RedirectResponse
    {
        $turno = $this->turnoPropio($id);

        $bloqueo = $this->motivoBloqueo($turno);
        if ($bloqueo !== null) {
            return redirect()->to(base_url('mis-ausencias'))->with('error', $bloqueo);
        }

        return view('mis_ausencias/form', [
            'titulo'  => 'Informar ausencia - TurnoPlaner',
            'turno'   => $turno,
            'motivos' => AusenciaModel::MOTIVOS,
        ]);
    }

    /** Registra el aviso (queda PENDIENTE) y vuelve a "Mis ausencias". */
    public function guardar(int $id): RedirectResponse
    {
        $usuarioId = (int) session()->get('usuario_id');
        $turno     = $this->turnoPropio($id);
        $form      = base_url('mis-ausencias/nueva/' . $id);

        $bloqueo = $this->motivoBloqueo($turno);
        if ($bloqueo !== null) {
            return redirect()->to(base_url('mis-ausencias'))->with('error', $bloqueo);
        }

        $datos  = $this->datosRecortados();
        $reglas = AusenciaModel::REGLAS_REPORTE + [
            'confirmo' => [
                'rules'  => 'required|in_list[1]',
                'errors' => [
                    'required' => 'Confirme que no podrá presentarse a este turno.',
                    'in_list'  => 'Confirme que no podrá presentarse a este turno.',
                ],
            ],
        ];
        if (! $this->validateData($datos, $reglas)) {
            return $this->volverConErrores($this->validator->getErrors(), $form);
        }

        $resultado = (new AusenciaModel())->reportar($usuarioId, $id, (string) $datos['motivo'], (string) $datos['descripcion']);

        if ($resultado['no_encontrada']) {
            throw PageNotFoundException::forPageNotFound('El turno solicitado no existe.');
        }
        if (! $resultado['ok']) {
            $errores = $resultado['errores'];
            if (isset($errores['general'])) {
                return redirect()->to(base_url('mis-ausencias'))->with('error', $errores['general']);
            }

            return $this->volverConErrores($errores, $form);
        }

        return redirect()->to(base_url('mis-ausencias'))
            ->with('success', 'Aviso enviado. La coordinación gestionará su reemplazo; puede seguir el estado en esta pantalla.');
    }

    /** Turno del usuario logueado con sus datos y el aviso activo, o 404. */
    private function turnoPropio(int $id): Asignacion
    {
        $turno = (new AsignacionModel())
            ->select('asignaciones.*, sectores.nombre AS sector_nombre, sectores.es_critico AS sector_critico, '
                . 'tipos_turno.nombre AS tipo_nombre, tipos_turno.color AS tipo_color')
            ->select(AsignacionModel::SQL_DATOS_AUSENCIA, false)
            ->join('sectores', 'sectores.id = asignaciones.sector_id')
            ->join('tipos_turno', 'tipos_turno.id = asignaciones.tipo_turno_id')
            ->where('asignaciones.id', $id)
            ->where('asignaciones.usuario_id', (int) session()->get('usuario_id'))
            ->first();

        if (! $turno instanceof Asignacion) {
            throw PageNotFoundException::forPageNotFound('El turno solicitado no existe.');
        }

        return $turno;
    }

    /** Por qué no se puede avisar sobre el turno (ya empezó o ya tiene un aviso activo), o null. */
    private function motivoBloqueo(Asignacion $turno): ?string
    {
        if ((string) $turno->inicio <= date('Y-m-d H:i:s')) {
            return 'Solo puede informar ausencias en turnos que todavía no empezaron.';
        }
        if ($turno->tieneAusenciaActiva()) {
            return 'Ya informó una ausencia para este turno: puede ver su estado en esta pantalla.';
        }

        return null;
    }
}
