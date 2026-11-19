<?php

declare(strict_types=1);

namespace App\Models;

use App\Entities\Sector;
use App\Models\Concerns\BajaLogica;
use CodeIgniter\Model;

/**
 * Sectores / servicios del hospital con dotación mínima (HU-09, HU-10).
 */
class SectorModel extends Model
{
    use BajaLogica;

    public const POR_PAGINA = 10;

    /** Dotación mínima máxima permitida por turno. */
    public const DOTACION_MAXIMA = 50;

    protected $table          = 'sectores';
    protected $primaryKey     = 'id';
    protected $returnType     = Sector::class;
    protected $useSoftDeletes = true;
    protected $protectFields  = true;
    protected $allowedFields  = ['nombre', 'descripcion', 'es_critico', 'dotacion_minima', 'estado'];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    protected $validationRules = [
        'id'              => 'permit_empty|is_natural_no_zero',
        'nombre'          => 'required|min_length[2]|max_length[80]|is_unique[sectores.nombre,id,{id}]',
        'descripcion'     => 'permit_empty|max_length[255]',
        'es_critico'      => 'permit_empty|in_list[0,1]',
        'dotacion_minima' => 'required|is_natural_no_zero|less_than_equal_to[' . self::DOTACION_MAXIMA . ']',
        'estado'          => 'permit_empty|in_list[ACTIVO,BAJA]',
    ];

    protected $validationMessages = [
        'nombre' => ['is_unique' => 'Ya existe un sector con ese nombre.'],
    ];

    /**
     * Listado con búsqueda, filtro de estado y de tipo (crítico / general) (HU-10).
     *
     * @param string $tipo 'CRITICO', 'GENERAL' o vacío.
     *
     * @return list<Sector>
     */
    public function listar(string $estado = 'ACTIVO', string $tipo = '', string $busqueda = ''): array
    {
        $this->filtrarPorEstado($estado);

        if ($tipo === 'CRITICO') {
            $this->where('es_critico', 1);
        } elseif ($tipo === 'GENERAL') {
            $this->where('es_critico', 0);
        }

        $busqueda = trim($busqueda);
        if ($busqueda !== '') {
            $this->groupStart()->like('nombre', $busqueda)->orLike('descripcion', $busqueda)->groupEnd();
        }

        return $this->orderBy('es_critico', 'DESC')->orderBy('nombre', 'ASC')->paginate(self::POR_PAGINA);
    }

    /**
     * ¿Se puede asociar un tipo de turno a este sector? Solo si está ACTIVO y sin baja lógica (HU-10:
     * un sector inactivo no se puede elegir). En la edición se permite conservar el sector que el tipo
     * ya tenía aunque esté de baja ($sectorActualId), para poder corregir el nombre o el color del turno
     * sin obligar a moverlo de sector.
     */
    public function esElegibleParaTipoTurno(int $sectorId, ?int $sectorActualId = null): bool
    {
        if ($sectorActualId !== null && $sectorId === $sectorActualId) {
            return $this->withDeleted()->find($sectorId) !== null;
        }

        return $this->where('estado', 'ACTIVO')->find($sectorId) !== null; // find() ya excluye deleted_at
    }

    /**
     * Sectores para el select del formulario de tipos de turno: los activos y, en la edición, también el
     * sector actual del tipo aunque esté de baja (si no, el select perdería la selección).
     *
     * @return list<Sector>
     */
    public function paraFormularioTipoTurno(?int $sectorActualId = null): array
    {
        $this->withDeleted()
            ->groupStart()
                ->groupStart()->where('estado', 'ACTIVO')->where('deleted_at', null)->groupEnd();

        if ($sectorActualId !== null) {
            $this->orWhere('id', $sectorActualId);
        }

        return $this->groupEnd()->orderBy('nombre', 'ASC')->findAll();
    }

    /**
     * Sectores activos, primero los críticos (para asignar turnos y la cuadrícula).
     *
     * @return list<Sector>
     */
    public function activos(): array
    {
        return $this->orderBy('es_critico', 'DESC')->orderBy('nombre', 'ASC')->findAll();
    }
}
