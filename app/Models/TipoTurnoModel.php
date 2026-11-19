<?php

declare(strict_types=1);

namespace App\Models;

use App\Entities\TipoTurno;
use App\Models\Concerns\BajaLogica;
use CodeIgniter\Model;

/**
 * Tipos de turno / franjas horarias (HU-11, HU-12), asociados a un sector.
 */
class TipoTurnoModel extends Model
{
    use BajaLogica;

    public const PALETA = [
        '#c98a00' => 'Ámbar',
        '#b34700' => 'Naranja',
        '#3d5a99' => 'Azul noche',
        '#7b4ea3' => 'Violeta',
        '#307f6d' => 'Verde institucional',
        '#b03a5b' => 'Bordó',
        '#1f6f8b' => 'Azul petróleo',
        '#5c6b75' => 'Gris',
    ];

    public const POR_PAGINA = 10;

    protected $table          = 'tipos_turno';
    protected $primaryKey     = 'id';
    protected $returnType     = TipoTurno::class;
    protected $useSoftDeletes = true;
    protected $protectFields  = true;
    protected $allowedFields  = ['sector_id', 'nombre', 'hora_inicio', 'hora_fin', 'duracion_minutos', 'color', 'estado'];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    /**
     * sector_id: el sector tiene que existir y estar ACTIVO (la baja lógica pone estado = BAJA y deleted_at
     * juntos). Al editar, save() de una entidad solo valida los campos que cambiaron, así que conservar el
     * sector actual aunque esté de baja sigue permitido; moverlo a un sector de baja, no.
     */
    protected $validationRules = [
        'id'               => 'permit_empty|is_natural_no_zero',
        'sector_id'        => 'required|is_natural_no_zero|is_not_unique[sectores.id,estado,ACTIVO]',
        'nombre'           => 'required|min_length[3]|max_length[60]',
        'hora_inicio'      => 'required',
        'hora_fin'         => 'required',
        'duracion_minutos' => 'required|is_natural_no_zero|less_than[1440]',
        'color'            => 'required|regex_match[/^#[0-9a-fA-F]{6}$/]',
        'estado'           => 'permit_empty|in_list[ACTIVO,BAJA]',
    ];

    protected $validationMessages = [
        'sector_id' => ['is_not_unique' => 'El sector seleccionado no existe o está dado de baja.'],
    ];

    public static function calcularDuracionMinutos(string $inicio, string $fin): int
    {
        $aMinutos = static function (string $hora): int {
            [$h, $m] = array_map('intval', explode(':', substr($hora, 0, 5)) + [0, 0]);

            return $h * 60 + $m;
        };

        return ($aMinutos($fin) - $aMinutos($inicio) + 1440) % 1440;
    }

    /**
     * Listado con búsqueda por nombre, filtro de estado y filtro opcional por sector (HU-12).
     *
     * @return list<TipoTurno>
     */
    public function listar(string $estado = 'ACTIVO', string $busqueda = '', ?int $sectorId = null): array
    {
        $this->select('tipos_turno.*, sectores.nombre AS sector_nombre')
            ->join('sectores', 'sectores.id = tipos_turno.sector_id', 'left');

        // La baja lógica también completa deleted_at (BajaLogica::darDeBajaSiLibre usa delete()), así que
        // "Dados de baja" y "Todos" tienen que incluir los borrados: si no, el filtro BAJA siempre sale vacío.
        // El estado se califica con la tabla para no chocar con sectores.estado del JOIN.
        if ($estado === 'BAJA') {
            $this->withDeleted()->where('tipos_turno.estado', 'BAJA');
        } elseif ($estado === 'TODOS') {
            $this->withDeleted();
        } else {
            $this->where('tipos_turno.estado', 'ACTIVO');
        }

        if ($sectorId !== null && $sectorId > 0) {
            $this->where('tipos_turno.sector_id', $sectorId);
        }

        $busqueda = trim($busqueda);
        if ($busqueda !== '') {
            $this->like('tipos_turno.nombre', $busqueda);
        }

        return $this->orderBy('sectores.nombre', 'ASC')
            ->orderBy('tipos_turno.hora_inicio', 'ASC')
            ->orderBy('tipos_turno.nombre', 'ASC')
            ->paginate(self::POR_PAGINA);
    }

    /**
     * Tipos de turno activos de un sector específico, ordenados por hora de inicio.
     *
     * @return list<TipoTurno>
     */
    public function activosPorSector(int $sectorId): array
    {
        return $this->where('estado', 'ACTIVO')
            ->where('sector_id', $sectorId)
            ->orderBy('hora_inicio', 'ASC')
            ->findAll();
    }

    /**
     * Tipos de turno activos, ordenados por hora de inicio.
     *
     * @return list<TipoTurno>
     */
    public function activos(): array
    {
        return $this->where('estado', 'ACTIVO')
            ->orderBy('hora_inicio', 'ASC')
            ->findAll();
    }
}