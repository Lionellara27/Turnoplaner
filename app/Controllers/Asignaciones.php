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
 * y ventana habilitada con 1 mes de anticipación.
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

        $resultado = $this->validarYArmar();
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
        $this->modelo->delete($id);

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
     * Valida el formulario y busca enfermero/a, sector y tipo de turno activos.
     *
     * @return array{0: Usuario, 1: Sector, 2: TipoTurno, 3: string}|array{errores: array<string, string>}
     */
    private function validarYArmar(): array
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
        if ($fecha > $fechaMaxima) {
            return ['errores' => ['fecha' => 'La planificación mensual se habilita con 1 mes de anticipación (máximo hasta ' . fecha_ar($fechaMaxima) . ').']];
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