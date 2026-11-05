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
use DateTimeImmutable;

/**
 * HU-13: asignación individual de turnos con control de superposición y de carga horaria semanal.
 * Permite reasignar (editar) o eliminar turnos que todavía no terminaron.
 *
 * Responde de dos formas con las mismas validaciones:
 * - formulario común (redirige con mensajes), y
 * - AJAX desde el modal de la cuadrícula (HU-14): devuelve JSON con los errores o el resultado.
 */
class Asignaciones extends BaseController
{
    /** Hasta cuántos días hacia adelante se pueden asignar turnos. */
    private const DIAS_MAXIMOS = 365;

    protected AsignacionModel $modelo;

    public function __construct()
    {
        $this->modelo = new AsignacionModel();
    }

    /** Formulario de asignación. Se puede precompletar desde la cuadrícula: ?sector=&fecha=&tipo= */
    public function crear(): string
    {
        $previo = [
            'sector_id'     => (int) $this->request->getGet('sector'),
            'tipo_turno_id' => (int) $this->request->getGet('tipo'),
            'fecha'         => $this->fechaValida((string) $this->request->getGet('fecha')) ?? date('Y-m-d'),
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

        return $this->exito($sector, $fecha, "Turno asignado: {$enfermero->getNombreCompleto()} · {$sector->nombre} · {$tipo->nombre} ({$tipo->rangoTexto()}) el " . fecha_larga($fecha) . '.');
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

    /** Guarda la reasignación (cambio de enfermero/a, sector, turno o fecha). */
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

        return $this->exito($sector, $fecha, "Turno reasignado: {$enfermero->getNombreCompleto()} · {$sector->nombre} · {$tipo->nombre} el " . fecha_larga($fecha) . '.');
    }

    /** Elimina (baja lógica) un turno que todavía no terminó (formulario o modal AJAX de la cuadrícula). */
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
            'semana' => AsignacionModel::lunesDe((string) $asignacion->fecha),
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

        // Formato exacto AAAA-MM-DD: "2026-11-4" pasaría un valid_date y compararía mal como texto.
        $fecha = $this->fechaValida((string) $datos['fecha']);
        if ($fecha === null) {
            return ['errores' => ['fecha' => 'Ingrese una fecha válida.']];
        }
        if ($fecha < date('Y-m-d')) {
            return ['errores' => ['fecha' => 'Solo se pueden asignar turnos de hoy en adelante.']];
        }
        if ($fecha > date('Y-m-d', strtotime('+' . self::DIAS_MAXIMOS . ' days'))) {
            return ['errores' => ['fecha' => 'Solo se pueden asignar turnos hasta un año hacia adelante.']];
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
        }

        if ($errores !== []) {
            return ['errores' => $errores];
        }

        // Un turno de hoy que ya terminó (por ejemplo la mañana, a las 15:00) no se puede asignar.
        if (AsignacionModel::calcularHorario($fecha, $tipo)['fin'] <= date('Y-m-d H:i:s')) {
            return ['errores' => ['tipo_turno_id' => "El turno {$tipo->nombre} de hoy ya terminó. Elija otra franja u otra fecha."]];
        }

        return [$enfermero, $sector, $tipo, $fecha];
    }

    /**
     * Respuesta de error: JSON para el modal de la cuadrícula, o volver al formulario.
     *
     * @param array<string, string> $errores
     */
    private function fallar(array $errores): ResponseInterface
    {
        if ($this->request->isAJAX()) {
            // El token CSRF se regenera en cada envío: se devuelve el nuevo para el próximo intento.
            return $this->response->setStatusCode(422)->setJSON(['ok' => false, 'errores' => $errores, 'csrf' => csrf_hash()]);
        }

        return $this->volverConErrores($errores);
    }

    /**
     * Respuesta de éxito: JSON para el modal (el mensaje queda en flash para cuando la cuadrícula se recarga),
     * o redirección a la cuadrícula de esa semana.
     */
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

        // Strings, igual que los devuelve la base, para que hasChanged() compare bien.
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
     * Listas para los selects, valores precargados y, si ya se sabe el turno y la fecha,
     * la disponibilidad de cada enfermero/a (horas de la semana y motivo si no puede).
     *
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

        $disponibilidad = ($tipo !== null && $fecha !== null)
            ? $this->modelo->disponibilidad($enfermeros, $tipo, $fecha, $excluirId)
            : [];

        return [
            'enfermeros'     => $enfermeros,
            'sectores'       => (new SectorModel())->activos(),
            'tipos'          => $tipos,
            'previo'         => $previo,
            'disponibilidad' => $disponibilidad,
            'calculoPara'    => $tipo !== null && $fecha !== null ? "{$tipo->nombre} del " . fecha_larga($fecha) : '',
            'fechaMaxima'    => date('Y-m-d', strtotime('+' . self::DIAS_MAXIMOS . ' days')),
        ];
    }

    /** Busca un turno que todavía no terminó; los terminados no se modifican. */
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
            'semana' => AsignacionModel::lunesDe($fecha),
        ]));
    }

    private function fechaValida(string $fecha): ?string
    {
        $d = DateTimeImmutable::createFromFormat('!Y-m-d', $fecha);

        return $d !== false && $d->format('Y-m-d') === $fecha ? $fecha : null;
    }
}
