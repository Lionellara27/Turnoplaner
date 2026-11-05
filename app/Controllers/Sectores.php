<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Entities\Sector;
use App\Models\SectorModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * ABMC de sectores del hospital (rol ADMIN).
 *
 * - HU-09: alta con dotación mínima de enfermeros/as por turno.
 * - HU-10: listado, edición, baja lógica y reactivación. Un sector dado de baja
 *          no puede elegirse para nuevas asignaciones (HU-13 usa solo los activos).
 */
class Sectores extends BaseController
{
    private const FILTROS_ESTADO = ['ACTIVO', 'BAJA', 'TODOS'];
    private const FILTROS_TIPO   = ['CRITICO', 'GENERAL'];

    protected SectorModel $modelo;

    public function __construct()
    {
        $this->modelo = new SectorModel();
    }

    /** HU-10: listado. */
    public function index(): string
    {
        $estado = strtoupper((string) $this->request->getGet('estado'));
        if (! in_array($estado, self::FILTROS_ESTADO, true)) {
            $estado = 'ACTIVO';
        }
        $tipo = strtoupper((string) $this->request->getGet('tipo'));
        if (! in_array($tipo, self::FILTROS_TIPO, true)) {
            $tipo = '';
        }
        $busqueda = mb_substr(trim((string) $this->request->getGet('q')), 0, 80);

        return view('admin/sectores/index', [
            'titulo'   => 'Sectores - TurnoPlaner',
            'sectores' => $this->modelo->listar($estado, $tipo, $busqueda),
            'pager'    => $this->modelo->pager,
            'filtros'  => ['estado' => $estado, 'tipo' => $tipo, 'q' => $busqueda],
        ]);
    }

    /** HU-09: formulario de alta. */
    public function crear(): string
    {
        return view('admin/sectores/create', ['titulo' => 'Nuevo sector - TurnoPlaner']);
    }

    /** HU-09: guarda el alta. */
    public function guardar(): RedirectResponse
    {
        $datos = $this->datosRecortados();

        if (! $this->validateData($datos, $this->reglas())) {
            return $this->volverConErrores($this->validator->getErrors());
        }

        $sector = new Sector($this->datosSector($datos) + ['estado' => 'ACTIVO']);

        if (! $this->modelo->save($sector)) {
            return $this->volverConErrores($this->modelo->errors());
        }

        return redirect()->to(base_url('admin/sectores'))
            ->with('success', "Sector «{$sector->nombre}» creado. Dotación mínima: {$sector->dotacionTexto()}.");
    }

    /** HU-10: formulario de edición. */
    public function editar(int $id): string
    {
        return view('admin/sectores/edit', [
            'titulo' => 'Editar sector - TurnoPlaner',
            'sector' => $this->buscar($id),
        ]);
    }

    /** HU-10: guarda la edición. */
    public function actualizar(int $id): RedirectResponse
    {
        $sector = $this->buscar($id);
        $datos  = $this->datosRecortados();

        if (! $this->validateData($datos, $this->reglas($id))) {
            return $this->volverConErrores($this->validator->getErrors());
        }

        $sector->fill($this->datosSector($datos));

        if (! $sector->hasChanged()) {
            return redirect()->to(base_url('admin/sectores'))->with('info', "No había cambios para guardar en «{$sector->nombre}».");
        }

        if (! $this->modelo->save($sector)) {
            return $this->volverConErrores($this->modelo->errors());
        }

        return redirect()->to(base_url('admin/sectores'))->with('success', "Sector «{$sector->nombre}» actualizado.");
    }

    /** HU-10: baja lógica. */
    public function darDeBaja(int $id): RedirectResponse
    {
        $sector = $this->buscar($id);

        if (! $sector->estaActivo()) {
            return redirect()->back()->with('info', "«{$sector->nombre}» ya estaba dado de baja.");
        }

        // Control y baja en una misma transacción con bloqueo (nadie puede asignar en el medio).
        $motivo = $this->modelo->darDeBajaSiLibre($id, 'sector_id');
        if ($motivo !== null) {
            return redirect()->back()->with('error', "No se puede dar de baja «{$sector->nombre}»: {$motivo}");
        }

        return redirect()->back()->with('success', "«{$sector->nombre}» fue dado de baja: ya no aparece para asignar turnos.");
    }

    /** HU-10: reactivación. */
    public function reactivar(int $id): RedirectResponse
    {
        $sector = $this->buscar($id);

        if ($sector->estaActivo()) {
            return redirect()->back()->with('info', "«{$sector->nombre}» ya estaba activo.");
        }

        $this->modelo->reactivar($id);

        return redirect()->back()->with('success', "«{$sector->nombre}» fue reactivado.");
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function reglas(?int $id = null): array
    {
        $ignorar = $id !== null ? ",id,{$id}" : '';

        return [
            'nombre' => [
                'rules'  => "required|min_length[2]|max_length[80]|is_unique[sectores.nombre{$ignorar}]",
                'errors' => [
                    'required'   => 'El nombre del sector es obligatorio.',
                    'min_length' => 'El nombre debe tener al menos 2 caracteres.',
                    'max_length' => 'El nombre no puede superar los 80 caracteres.',
                    'is_unique'  => 'Ya existe un sector con ese nombre (si está dado de baja, búsquelo con el filtro «Dados de baja» y reactívelo).',
                ],
            ],
            'descripcion' => [
                'rules'  => 'permit_empty|max_length[255]',
                'errors' => ['max_length' => 'La descripción no puede superar los 255 caracteres.'],
            ],
            'es_critico' => [
                'rules'  => 'permit_empty|in_list[0,1]',
                'errors' => ['in_list' => 'Indique si es un área crítica.'],
            ],
            'dotacion_minima' => [
                'rules'  => 'required|is_natural_no_zero|less_than_equal_to[' . SectorModel::DOTACION_MAXIMA . ']',
                'errors' => [
                    'required'           => 'La dotación mínima es obligatoria.',
                    'is_natural_no_zero' => 'La dotación mínima debe ser un número entero mayor a cero.',
                    'less_than_equal_to' => 'La dotación mínima no puede superar ' . SectorModel::DOTACION_MAXIMA . ' enfermeros/as por turno.',
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $datos
     *
     * @return array<string, mixed>
     */
    private function datosSector(array $datos): array
    {
        $descripcion = (string) ($datos['descripcion'] ?? '');

        // Strings, igual que los devuelve la base, para que hasChanged() compare bien.
        return [
            'nombre'          => (string) $datos['nombre'],
            'descripcion'     => $descripcion !== '' ? $descripcion : null,
            'es_critico'      => ($datos['es_critico'] ?? '0') === '1' ? '1' : '0',
            'dotacion_minima' => (string) (int) $datos['dotacion_minima'],
        ];
    }

    private function buscar(int $id): Sector
    {
        $sector = $this->modelo->buscarIncluyendoBajas($id);

        if (! $sector instanceof Sector) {
            throw PageNotFoundException::forPageNotFound('El sector solicitado no existe.');
        }

        return $sector;
    }
}
