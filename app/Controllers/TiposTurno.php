<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Entities\TipoTurno;
use App\Models\SectorModel;
use App\Models\TipoTurnoModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;

class TiposTurno extends BaseController
{
    private const FILTROS_ESTADO = ['ACTIVO', 'BAJA', 'TODOS'];

    protected TipoTurnoModel $modelo;
    protected SectorModel $sectorModelo;

    public function __construct()
    {
        $this->modelo       = new TipoTurnoModel();
        $this->sectorModelo = new SectorModel();
    }

    /** HU-12: listado. */
    public function index(): string
    {
        $estado = strtoupper($this->textoGet('estado'));
        if (! in_array($estado, self::FILTROS_ESTADO, true)) {
            $estado = 'ACTIVO';
        }
        $busqueda = mb_substr(trim($this->textoGet('q')), 0, 60);
        $sectorId = (int) $this->textoGet('sector_id');
        if ($sectorId <= 0) {
            $sectorId = null;
        }

        return view('admin/tipos_turno/index', [
            'titulo'   => 'Tipos de turno - TurnoPlaner',
            'tipos'    => $this->modelo->listar($estado, $busqueda, $sectorId),
            'pager'    => $this->modelo->pager,
            'sectores' => $this->sectorModelo->where('estado', 'ACTIVO')->orderBy('nombre', 'ASC')->findAll(),
            'filtros'  => ['estado' => $estado, 'q' => $busqueda, 'sector_id' => $sectorId],
        ]);
    }

    /** HU-11: formulario de alta. */
    public function crear(): string
    {
        return view('admin/tipos_turno/create', [
            'titulo'   => 'Nuevo tipo de turno - TurnoPlaner',
            'paleta'   => TipoTurnoModel::PALETA,
            'sectores' => $this->sectorModelo->where('estado', 'ACTIVO')->orderBy('nombre', 'ASC')->findAll(),
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
        $tipo = $this->buscar($id);

        // Traemos sectores activos (y si el sector actual del turno estuviera dado de baja, lo incluimos igual para no perder la selección)
        $sectores = $this->sectorModelo
            ->groupStart()
                ->where('estado', 'ACTIVO')
                ->orWhere('id', (int) $tipo->sector_id)
            ->groupEnd()
            ->orderBy('nombre', 'ASC')
            ->findAll();

        return view('admin/tipos_turno/edit', [
            'titulo'   => 'Editar tipo de turno - TurnoPlaner',
            'tipo'     => $tipo,
            'paleta'   => TipoTurnoModel::PALETA,
            'sectores' => $sectores,
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

        if (($tipo->hasChanged('hora_inicio') || $tipo->hasChanged('hora_fin') || $tipo->hasChanged('sector_id'))
            && $this->modelo->tieneAsignacionesFuturas($id, 'tipo_turno_id')) {
            return $this->volverConErrores([
                'hora_inicio' => "No se puede cambiar el horario ni la zona de «{$tipo->nombre}»: tiene turnos asignados desde hoy en adelante. "
                    . 'Cree un tipo de turno nuevo o reasigne esos turnos primero.',
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

    /** HU-12: baja lógica. */
    public function darDeBaja(int $id): RedirectResponse
    {
        $tipo = $this->buscar($id);

        if (! $tipo->estaActivo()) {
            return redirect()->back()->with('info', "«{$tipo->nombre}» ya estaba dado de baja.");
        }

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
     * @param array<string, mixed> $datos
     */
    private function validar(array $datos, ?int $id = null): ?RedirectResponse
    {
        $hora = 'regex_match[/^([01][0-9]|2[0-3]):[0-5][0-9]$/]';

        $reglas = [
            'sector_id' => [
                'rules'  => 'required|is_natural_no_zero|is_not_unique[sectores.id]',
                'errors' => [
                    'required'           => 'Debe seleccionar la zona de trabajo (sector).',
                    'is_natural_no_zero' => 'Seleccione una zona de trabajo válida.',
                    'is_not_unique'      => 'La zona de trabajo seleccionada no existe.',
                ],
            ],
            'nombre' => [
                'rules'  => 'required|min_length[3]|max_length[60]',
                'errors' => [
                    'required'   => 'El nombre del turno es obligatorio.',
                    'min_length' => 'El nombre debe tener al menos 3 caracteres.',
                    'max_length' => 'El nombre no puede superar los 60 caracteres.',
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

        // Validar unicidad de nombre DENTRO del mismo sector (incluyendo dados de baja)
        $sectorId = (int) $datos['sector_id'];
        $nombre   = trim((string) $datos['nombre']);

        $duplicado = $this->modelo->withDeleted()
            ->where('sector_id', $sectorId)
            ->where('nombre', $nombre);

        if ($id !== null) {
            $duplicado->where('id !=', $id);
        }

        if ($duplicado->first() !== null) {
            return $this->volverConErrores([
                'nombre' => 'Ya existe un tipo de turno con ese nombre en la zona seleccionada (si está dado de baja, búsquelo con el filtro «Dados de baja» y reactívelo).',
            ]);
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

        return [
            'sector_id'        => (int) $datos['sector_id'],
            'nombre'           => trim((string) $datos['nombre']),
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