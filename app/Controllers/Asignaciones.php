<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Entities\Asignacion;
use App\Entities\Sector;
use App\Entities\TipoTurno;
use App\Entities\Usuario;
use App\Models\AsignacionModel;
use App\Models\SectorModel;
use App\Models\TipoTurnoModel;
use App\Models\UsuarioModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * HU-13: asignación mensual de turnos con control de superposición, horas extras con prioridad
 * y ventana habilitada con 2 meses de anticipación.
 */
class Asignaciones extends BaseController
{
    protected AsignacionModel $modelo;

    public function __construct()
    {
        $this->modelo = new AsignacionModel();
    }

    /** Formulario de asignación. Se puede precompletar desde la cuadrícula: ?sector=&fecha=&tipo= */
    public function crear(): string
    {
        $previo = [
            'sector_id'     => (int) $this->textoGet('sector'),
            'tipo_turno_id' => (int) $this->textoGet('tipo'),
            'fecha'         => $this->fechaValida($this->textoGet('fecha')) ?? date('Y-m-d'),
            'usuario_id'    => 0,
        ];

        return view('admin/asignaciones/form', $this->datosFormulario($previo) + [
            'titulo'     => 'Asignar turno - TurnoPlaner',
            'asignacion' => null,
        ]);
    }

    /** Guarda una asignación nueva (formulario o modal AJAX). */
    public function guardar(): ResponseInterface
    {
        $resultado = $this->validarYArmar();
        if (isset($resultado['errores'])) {
            return $this->fallar($resultado['errores']);
        }

        [$enfermero, $sector, $tipo, $fecha] = $resultado;

        $asignacion = new Asignacion($this->datosAsignacion($enfermero, $sector, $tipo, $fecha) + [
            'creado_por' => (int) session()->get('usuario_id'),
        ]);

        $errores = $this->modelo->guardarConControles($asignacion, $enfermero, (int) $sector->id, $tipo, $fecha);
        if ($errores !== []) {
            return $this->fallar($errores);
        }

        $notaExtra = $asignacion->esExtra()
            ? ' (registrado como HORA EXTRA cubierta: ' . AsignacionModel::horasTexto((int) $asignacion->minutos_extra) . ')'
            : '';

        return $this->exito(
            $sector,
            $fecha,
            "Turno asignado: {$enfermero->getNombreCompleto()} · {$sector->nombre} · {$tipo->nombre} ({$tipo->rangoTexto()}) el " . fecha_larga($fecha) . ".{$notaExtra}"
        );
    }

    /** Formulario para reasignar un turno que todavía no terminó. */
    public function editar(int $id): string|RedirectResponse
    {
        $asignacion = $this->buscarFutura($id);
        if ($asignacion instanceof RedirectResponse) {
            return $asignacion;
        }

        return view('admin/asignaciones/form', $this->datosFormulario($asignacion->toRawArray(), $id) + [
            'titulo'     => 'Reasignar turno - TurnoPlaner',
            'asignacion' => $asignacion,
        ]);
    }

    /** Guarda la reasignación. */
    public function actualizar(int $id): ResponseInterface
    {
        $asignacion = $this->buscarFutura($id);
        if ($asignacion instanceof RedirectResponse) {
            return $asignacion;
        }

        // La ventana de 2 meses se controla solo si el turno se mueve a otra fecha: cambiarle el
        // enfermero/a o la franja a un turno ya cargado no debe rechazarse por esa ventana.
        $resultado = $this->validarYArmar((string) $asignacion->fecha);
        if (isset($resultado['errores'])) {
            return $this->fallar($resultado['errores']);
        }

        [$enfermero, $sector, $tipo, $fecha] = $resultado;

        $asignacion->fill($this->datosAsignacion($enfermero, $sector, $tipo, $fecha));

        if (! $asignacion->hasChanged()) {
            return $this->alPlanificador($sector, $fecha)->with('info', 'No había cambios para guardar en el turno.');
        }

        $errores = $this->modelo->guardarConControles($asignacion, $enfermero, (int) $sector->id, $tipo, $fecha, $id);
        if ($errores !== []) {
            return $this->fallar($errores);
        }

        $notaExtra = $asignacion->esExtra()
            ? ' (registrado como HORA EXTRA cubierta: ' . AsignacionModel::horasTexto((int) $asignacion->minutos_extra) . ')'
            : '';

        return $this->exito(
            $sector,
            $fecha,
            "Turno reasignado: {$enfermero->getNombreCompleto()} · {$sector->nombre} · {$tipo->nombre} el " . fecha_larga($fecha) . ".{$notaExtra}"
        );
    }

    /** Elimina (baja lógica) un turno que todavía no terminó. */
    public function eliminar(int $id): ResponseInterface
    {
        $asignacion = $this->buscarFutura($id);
        if ($asignacion instanceof RedirectResponse) {
            return $this->request->isAJAX()
                ? $this->fallar(['general' => 'Los turnos que ya terminaron no se pueden modificar ni eliminar.'])
                : $asignacion;
        }

        $sector = (new SectorModel())->withDeleted()->find((int) $asignacion->sector_id);

        // Baja lógica + recálculo de las horas extras de esa semana, en una sola transacción (modelo).
        if (! $this->modelo->eliminarConRecalculo($asignacion)) {
            return $this->fallar(['general' => $this->modelo->ultimoError() ?? 'No se pudo eliminar el turno. Intente nuevamente.']);
        }

        $mensaje = 'Turno eliminado del ' . fecha_larga((string) $asignacion->fecha)
            . ($sector instanceof Sector ? " en {$sector->nombre}." : '.');

        if ($this->request->isAJAX()) {
            session()->setFlashdata('success', $mensaje);

            return $this->response->setJSON(['ok' => true, 'mensaje' => $mensaje, 'csrf' => csrf_hash()]);
        }

        $destino = base_url('admin/planificador') . '?' . http_build_query([
            'sector' => (int) $asignacion->sector_id,
            'mes'    => substr((string) $asignacion->fecha, 0, 7),
        ]);

        return redirect()->to($destino)->with('success', $mensaje);
    }

    /**
     * Disponibilidad de enfermeros/as para el modal del planificador (HU-13 / HU-14). Solo AJAX o JSON.
     *
     * GET admin/asignaciones/disponibilidad?sector_id=N&tipo_turno_id=N&fecha=YYYY-MM-DD[&excluir=ID]
     *   - sector_id:     sector activo.
     *   - tipo_turno_id: tipo de turno activo que pertenece a ese sector.
     *   - fecha:         día en que empieza el turno.
     *   - excluir:       (opcional) id del turno que se está reasignando: no cuenta ni para las horas
     *                    de la semana ni para la superposición.
     *
     * 200: {"ok": true, "enfermeros": [{
     *          "id": int, "nombre": "Apellido, Nombre", "especialidad": string, "matricula": string,
     *          "minutos_semana": int, "maximo_minutos": int, "horas_texto": "32 h de 36 h esta semana",
     *          "estado": "disponible" | "extra" | "no_disponible", "motivo": string | null
     *      }, ...]}
     *      Mismo orden que AsignacionModel::evaluarDisponibilidad(): primero los disponibles (menos horas
     *      primero), después los que entrarían como hora extra y al final los no disponibles con su motivo.
     * 422: {"ok": false, "errores": {"campo": "mensaje", ...}} si algún parámetro es inválido.
     * 404: si se abre como página normal (no es una pantalla, solo datos para el modal).
     *
     * Es GET y no modifica nada, por eso no consume ni regenera el token CSRF.
     */
    public function disponibilidad(): ResponseInterface
    {
        if (! $this->request->isAJAX() && ! str_contains($this->request->getHeaderLine('Accept'), 'application/json')) {
            throw PageNotFoundException::forPageNotFound();
        }

        $datos = [
            'sector_id'     => $this->textoGet('sector_id'),
            'tipo_turno_id' => $this->textoGet('tipo_turno_id'),
            'fecha'         => $this->textoGet('fecha'),
            'excluir'       => $this->textoGet('excluir'),
        ];
        $reglas = [
            'sector_id' => [
                'rules'  => 'required|is_natural_no_zero',
                'errors' => ['required' => 'Elija el sector.', 'is_natural_no_zero' => 'Elija el sector.'],
            ],
            'tipo_turno_id' => [
                'rules'  => 'required|is_natural_no_zero',
                'errors' => ['required' => 'Elija el turno.', 'is_natural_no_zero' => 'Elija el turno.'],
            ],
            'fecha' => [
                'rules'  => 'required|valid_date[Y-m-d]',
                'errors' => ['required' => 'Elija la fecha.', 'valid_date' => 'Ingrese una fecha válida (AAAA-MM-DD).'],
            ],
            'excluir' => [
                'rules'  => 'permit_empty|is_natural_no_zero',
                'errors' => ['is_natural_no_zero' => 'El turno a excluir no es válido.'],
            ],
        ];

        if (! $this->validateData($datos, $reglas)) {
            return $this->response->setStatusCode(422)->setJSON(['ok' => false, 'errores' => $this->validator->getErrors()]);
        }
        if ($this->fechaValida($datos['fecha']) === null) {
            return $this->response->setStatusCode(422)->setJSON(['ok' => false, 'errores' => ['fecha' => 'Ingrese una fecha válida (AAAA-MM-DD).']]);
        }

        $sector = (new SectorModel())->find((int) $datos['sector_id']);
        $tipo   = (new TipoTurnoModel())->find((int) $datos['tipo_turno_id']);

        $errores = [];
        if (! $sector instanceof Sector) {
            $errores['sector_id'] = 'El sector elegido no está activo.';
        }
        if (! $tipo instanceof TipoTurno) {
            $errores['tipo_turno_id'] = 'El turno elegido no está activo.';
        } elseif ($sector instanceof Sector && (int) $tipo->sector_id !== (int) $sector->id) {
            $errores['tipo_turno_id'] = "El turno «{$tipo->nombre}» no corresponde al sector «{$sector->nombre}».";
        }
        if ($errores !== []) {
            return $this->response->setStatusCode(422)->setJSON(['ok' => false, 'errores' => $errores]);
        }

        $excluir = $datos['excluir'] !== '' ? (int) $datos['excluir'] : null;
        $filas   = $this->modelo->evaluarDisponibilidad((new UsuarioModel())->enfermerosActivos(), $tipo, $datos['fecha'], $excluir);

        $enfermeros = [];
        foreach ($filas as $fila) {
            $e      = $fila['enfermero'];
            $maximo = $fila['maximo_minutos'];

            $enfermeros[] = [
                'id'             => (int) $e->id,
                'nombre'         => "{$e->apellido}, {$e->nombre}",
                'especialidad'   => (string) ($e->especialidad ?? ''),
                'matricula'      => (string) ($e->matricula ?? ''),
                'minutos_semana' => $fila['minutos_semana'],
                'maximo_minutos' => $maximo,
                'horas_texto'    => AsignacionModel::horasTexto($fila['minutos_semana'])
                    . ($maximo > 0 ? ' de ' . AsignacionModel::horasTexto($maximo) : '') . ' esta semana',
                'estado'         => $fila['estado'],
                'motivo'         => $fila['motivo'],
            ];
        }

        return $this->response->setJSON(['ok' => true, 'enfermeros' => $enfermeros]);
    }

    /**
     * Valida el formulario y busca enfermero/a, sector y tipo de turno activos.
     *
     * @param string|null $fechaActual fecha que ya tenía el turno (reasignación): si no cambia, no se
     *                                 aplica el tope de la ventana habilitada (fechaMaximaHabilitada).
     *
     * @return array{0: Usuario, 1: Sector, 2: TipoTurno, 3: string}|array{errores: array<string, string>}
     */
    private function validarYArmar(?string $fechaActual = null): array
    {
        $datos  = $this->datosRecortados();
        $reglas = [
            'usuario_id' => [
                'rules'  => 'required|is_natural_no_zero',
                'errors' => ['required' => 'Elija el enfermero/a.', 'is_natural_no_zero' => 'Elija el enfermero/a.'],
            ],
            'sector_id' => [
                'rules'  => 'required|is_natural_no_zero',
                'errors' => ['required' => 'Elija el sector.', 'is_natural_no_zero' => 'Elija el sector.'],
            ],
            'tipo_turno_id' => [
                'rules'  => 'required|is_natural_no_zero',
                'errors' => ['required' => 'Elija el turno.', 'is_natural_no_zero' => 'Elija el turno.'],
            ],
            'fecha' => [
                'rules'  => 'required',
                'errors' => ['required' => 'Elija la fecha.'],
            ],
        ];

        if (! $this->validateData($datos, $reglas)) {
            return ['errores' => $this->validator->getErrors()];
        }

        $fecha = $this->fechaValida((string) $datos['fecha']);
        if ($fecha === null) {
            return ['errores' => ['fecha' => 'Ingrese una fecha válida.']];
        }
        if ($fecha < date('Y-m-d')) {
            return ['errores' => ['fecha' => 'Solo se pueden asignar turnos de hoy en adelante.']];
        }

        $fechaMaxima = AsignacionModel::fechaMaximaHabilitada();
        if ($fecha !== $fechaActual && $fecha > $fechaMaxima) {
            return ['errores' => ['fecha' => 'La planificación mensual se habilita con 2 meses de anticipación (máximo hasta ' . fecha_ar($fechaMaxima) . ').']];
        }

        $errores = [];

        $enfermero = (new UsuarioModel())->find((int) $datos['usuario_id']);
        if (! $enfermero instanceof Usuario || ! $enfermero->esEnfermero() || ! $enfermero->estaActivo()) {
            $errores['usuario_id'] = 'El enfermero/a elegido no está activo.';
        }

        $sector = (new SectorModel())->find((int) $datos['sector_id']);
        if (! $sector instanceof Sector) {
            $errores['sector_id'] = 'El sector elegido no está activo.';
        }

        $tipo = (new TipoTurnoModel())->find((int) $datos['tipo_turno_id']);
        if (! $tipo instanceof TipoTurno) {
            $errores['tipo_turno_id'] = 'El turno elegido no está activo.';
        } elseif ($sector instanceof Sector && (int) $tipo->sector_id !== (int) $sector->id) {
            $errores['tipo_turno_id'] = "El turno «{$tipo->nombre}» no corresponde al sector «{$sector->nombre}».";
        }

        if ($errores !== []) {
            return ['errores' => $errores];
        }

        if (AsignacionModel::calcularHorario($fecha, $tipo)['fin'] <= date('Y-m-d H:i:s')) {
            return ['errores' => ['tipo_turno_id' => "El turno {$tipo->nombre} de hoy ya terminó. Elija otra franja u otra fecha."]];
        }

        return [$enfermero, $sector, $tipo, $fecha];
    }

    private function fallar(array $errores): ResponseInterface
    {
        if ($this->request->isAJAX()) {
            return $this->response->setStatusCode(422)->setJSON(['ok' => false, 'errores' => $errores, 'csrf' => csrf_hash()]);
        }

        return $this->volverConErrores($errores);
    }

    private function exito(Sector $sector, string $fecha, string $mensaje): ResponseInterface
    {
        if ($this->request->isAJAX()) {
            session()->setFlashdata('success', $mensaje);

            return $this->response->setJSON(['ok' => true, 'mensaje' => $mensaje, 'csrf' => csrf_hash()]);
        }

        return $this->alPlanificador($sector, $fecha)->with('success', $mensaje);
    }

    /**
     * @return array<string, mixed>
     */
    private function datosAsignacion(Usuario $enfermero, Sector $sector, TipoTurno $tipo, string $fecha): array
    {
        $horario = AsignacionModel::calcularHorario($fecha, $tipo);

        return [
            'usuario_id'       => (string) $enfermero->id,
            'sector_id'        => (string) $sector->id,
            'tipo_turno_id'    => (string) $tipo->id,
            'fecha'            => $fecha,
            'inicio'           => $horario['inicio'],
            'fin'              => $horario['fin'],
            'duracion_minutos' => (string) $horario['duracion_minutos'],
        ];
    }

    /**
     * @param array<string, mixed> $previo
     *
     * @return array<string, mixed>
     */
    private function datosFormulario(array $previo, ?int $excluirId = null): array
    {
        $enfermeros = (new UsuarioModel())->enfermerosActivos();
        $tipos      = (new TipoTurnoModel())->activos();

        $fecha  = $this->fechaValida((string) old('fecha', (string) ($previo['fecha'] ?? ''), false));
        $tipoId = (int) old('tipo_turno_id', (string) ($previo['tipo_turno_id'] ?? ''), false);
        $tipo   = null;
        foreach ($tipos as $t) {
            if ((int) $t->id === $tipoId) {
                $tipo = $t;
            }
        }

        $disponibilidad = [];
        if ($tipo !== null && $fecha !== null) {
            $priorizados    = $this->modelo->disponibilidadConPrioridad($enfermeros, $tipo, $fecha, $excluirId);
            $enfermeros     = $priorizados['ordenados'];
            $disponibilidad = $priorizados['info'];
        }

        return [
            'enfermeros'     => $enfermeros,
            'especialidades' => UsuarioModel::ESPECIALIDADES,
            'sectores'       => (new SectorModel())->activos(),
            'tipos'          => $tipos,
            'previo'         => $previo,
            'disponibilidad' => $disponibilidad,
            'calculoPara'    => $tipo !== null && $fecha !== null ? "{$tipo->nombre} del " . fecha_larga($fecha) : '',
            'fechaMaxima'    => AsignacionModel::fechaMaximaHabilitada(),
        ];
    }

    private function buscarFutura(int $id): Asignacion|RedirectResponse
    {
        $asignacion = $this->modelo->find($id);

        if (! $asignacion instanceof Asignacion) {
            throw PageNotFoundException::forPageNotFound('El turno solicitado no existe.');
        }

        if (! $asignacion->esFutura()) {
            return redirect()->to(base_url('admin/planificador'))
                ->with('error', 'Los turnos que ya terminaron no se pueden modificar ni eliminar.');
        }

        return $asignacion;
    }

    private function alPlanificador(Sector $sector, string $fecha): RedirectResponse
    {
        return redirect()->to(base_url('admin/planificador') . '?' . http_build_query([
            'sector' => (int) $sector->id,
            'mes'    => substr($fecha, 0, 7),
        ]));
    }
}