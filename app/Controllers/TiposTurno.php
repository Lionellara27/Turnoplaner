<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Entities\TipoTurno;
use App\Models\TipoTurnoModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * ABMC de tipos de turno (rol ADMIN).
 *
 * - HU-11: alta con cálculo automático de la duración (incluye turnos que cruzan la medianoche).
 * - HU-12: listado, edición, baja lógica y reactivación. La baja se rechaza si el tipo de turno
 *          tiene asignaciones futuras (se activa cuando exista la tabla de asignaciones, HU-13).
 */
class TiposTurno extends BaseController
{
    private const FILTROS_ESTADO = ['ACTIVO', 'BAJA', 'TODOS'];

    protected TipoTurnoModel $modelo;

    public function __construct()
    {
        $this->modelo = new TipoTurnoModel();
    }

    /** HU-12: listado. */
    public function index(): string
    {
        $estado = strtoupper($this->textoGet('estado'));
        if (! in_array($estado, self::FILTROS_ESTADO, true)) {
            $estado = 'ACTIVO';
        }
        $busqueda = mb_substr(trim($this->textoGet('q')), 0, 60);

        return view('admin/tipos_turno/index', [
            'titulo'  => 'Tipos de turno - TurnoPlaner',
            'tipos'   => $this->modelo->listar($estado, $busqueda),
            'pager'   => $this->modelo->pager,
            'filtros' => ['estado' => $estado, 'q' => $busqueda],
        ]);
    }

    /** HU-11: formulario de alta. */
    public function crear(): string
    {
        return view('admin/tipos_turno/create', [
            'titulo' => 'Nuevo tipo de turno - TurnoPlaner',
            'paleta' => TipoTurnoModel::PALETA,
        ]);
    }

    /** HU-11: guarda el alta. */
    public function guardar(): RedirectResponse
    {
        $datos = $this->datosRecortados();

        if (($error = $this->validar($datos)) !== null) {
            return $error;
        }

        $tipo = new TipoTurno($this->datosTipo($datos) + ['estado' => 'ACTIVO']);

        if (! $this->modelo->save($tipo)) {
            return $this->volverConErrores($this->modelo->errors());
        }

        return redirect()->to(base_url('admin/tipos-turno'))
            ->with('success', "Tipo de turno «{$tipo->nombre}» creado ({$tipo->rangoTexto()}, {$tipo->duracionTexto()}).");
    }

    /** HU-12: formulario de edición. */
    public function editar(int $id): string
    {
        return view('admin/tipos_turno/edit', [
            'titulo' => 'Editar tipo de turno - TurnoPlaner',
            'tipo'   => $this->buscar($id),
            'paleta' => TipoTurnoModel::PALETA,
        ]);
    }

    /** HU-12: guarda la edición. */
    public function actualizar(int $id): RedirectResponse
    {
        $tipo  = $this->buscar($id);
        $datos = $this->datosRecortados();

        if (($error = $this->validar($datos, $id)) !== null) {
            return $error;
        }

        $tipo->fill($this->datosTipo($datos));

        if (($tipo->hasChanged('hora_inicio') || $tipo->hasChanged('hora_fin')) && $this->modelo->tieneAsignacionesFuturas($id, 'tipo_turno_id')) {
            return $this->volverConErrores([
                'hora_inicio' => "No se puede cambiar el horario de «{$tipo->nombre}»: tiene turnos asignados desde hoy en adelante con el horario actual. "
                    . 'Cree un tipo de turno nuevo con el horario distinto, o reasigne esos turnos primero.',
            ]);
        }

        if (! $tipo->hasChanged()) {
            return redirect()->to(base_url('admin/tipos-turno'))->with('info', "No había cambios para guardar en «{$tipo->nombre}».");
        }

        if (! $this->modelo->save($tipo)) {
            return $this->volverConErrores($this->modelo->errors());
        }

        return redirect()->to(base_url('admin/tipos-turno'))
            ->with('success', "Tipo de turno «{$tipo->nombre}» actualizado ({$tipo->rangoTexto()}, {$tipo->duracionTexto()}).");
    }

    /** HU-12: baja lógica, rechazada si hay asignaciones futuras con este tipo de turno. */
    public function darDeBaja(int $id): RedirectResponse
    {
        $tipo = $this->buscar($id);

        if (! $tipo->estaActivo()) {
            return redirect()->back()->with('info', "«{$tipo->nombre}» ya estaba dado de baja.");
        }

        // Control y baja en una misma transacción con bloqueo (nadie puede asignar en el medio).
        $motivo = $this->modelo->darDeBajaSiLibre($id, 'tipo_turno_id');
        if ($motivo !== null) {
            return redirect()->back()->with('error', "No se puede dar de baja «{$tipo->nombre}»: {$motivo}");
        }

        return redirect()->back()->with('success', "«{$tipo->nombre}» fue dado de baja: ya no aparece para asignar turnos.");
    }

    /** HU-12: reactivación. */
    public function reactivar(int $id): RedirectResponse
    {
        $tipo = $this->buscar($id);

        if ($tipo->estaActivo()) {
            return redirect()->back()->with('info', "«{$tipo->nombre}» ya estaba activo.");
        }

        $this->modelo->reactivar($id);

        return redirect()->back()->with('success', "«{$tipo->nombre}» fue reactivado.");
    }

    /**
     * Valida el formulario. Devuelve null si está todo bien, o la redirección con los errores.
     *
     * @param array<string, mixed> $datos
     */
    private function validar(array $datos, ?int $id = null): ?RedirectResponse
    {
        $ignorar = $id !== null ? ",id,{$id}" : '';
        $hora    = 'regex_match[/^([01][0-9]|2[0-3]):[0-5][0-9]$/]';

        $reglas = [
            'nombre' => [
                'rules'  => "required|min_length[3]|max_length[60]|is_unique[tipos_turno.nombre{$ignorar}]",
                'errors' => [
                    'required'   => 'El nombre del turno es obligatorio.',
                    'min_length' => 'El nombre debe tener al menos 3 caracteres.',
                    'max_length' => 'El nombre no puede superar los 60 caracteres.',
                    'is_unique'  => 'Ya existe un tipo de turno con ese nombre (si está dado de baja, búsquelo con el filtro «Dados de baja» y reactívelo).',
                ],
            ],
            'hora_inicio' => [
                'rules'  => "required|{$hora}",
                'errors' => ['required' => 'La hora de inicio es obligatoria.', 'regex_match' => 'Ingrese la hora de inicio en formato 24 h (HH:MM).'],
            ],
            'hora_fin' => [
                'rules'  => "required|{$hora}",
                'errors' => ['required' => 'La hora de fin es obligatoria.', 'regex_match' => 'Ingrese la hora de fin en formato 24 h (HH:MM).'],
            ],
            'color' => [
                'rules'  => 'required|in_list[' . implode(',', array_keys(TipoTurnoModel::PALETA)) . ']',
                'errors' => ['required' => 'Elija un color para la etiqueta.', 'in_list' => 'Elija uno de los colores de la lista.'],
            ],
        ];

        if (! $this->validateData($datos, $reglas)) {
            return $this->volverConErrores($this->validator->getErrors());
        }

        if (TipoTurnoModel::calcularDuracionMinutos((string) $datos['hora_inicio'], (string) $datos['hora_fin']) === 0) {
            return $this->volverConErrores(['hora_fin' => 'La hora de fin no puede ser igual a la de inicio.']);
        }

        return null;
    }

    /**
     * @param array<string, mixed> $datos
     *
     * @return array<string, mixed>
     */
    private function datosTipo(array $datos): array
    {
        $inicio = (string) $datos['hora_inicio'];
        $fin    = (string) $datos['hora_fin'];

        // Formato igual al que devuelve la base (HH:MM:SS) para que hasChanged() compare bien.
        return [
            'nombre'           => (string) $datos['nombre'],
            'hora_inicio'      => $inicio . ':00',
            'hora_fin'         => $fin . ':00',
            'duracion_minutos' => (string) TipoTurnoModel::calcularDuracionMinutos($inicio, $fin),
            'color'            => strtolower((string) $datos['color']),
        ];
    }

    private function buscar(int $id): TipoTurno
    {
        $tipo = $this->modelo->buscarIncluyendoBajas($id);

        if (! $tipo instanceof TipoTurno) {
            throw PageNotFoundException::forPageNotFound('El tipo de turno solicitado no existe.');
        }

        return $tipo;
    }
}
