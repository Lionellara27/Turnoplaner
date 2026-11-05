<?php

declare(strict_types=1);

namespace App\Models;

use App\Entities\TipoTurno;
use App\Models\Concerns\BajaLogica;
use CodeIgniter\Model;

/**
 * Tipos de turno / franjas horarias (HU-11, HU-12).
 */
class TipoTurnoModel extends Model
{
    use BajaLogica;

    /**
     * Colores sugeridos para las etiquetas de la cuadrícula (HU-14).
     * Con texto blanco u oscuro (según el color) todos cumplen contraste AA: ver TipoTurno::colorTexto().
     */
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
    protected $allowedFields  = ['nombre', 'hora_inicio', 'hora_fin', 'duracion_minutos', 'color', 'estado'];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    // Segunda barrera de validación (la primera está en el controlador).
    protected $validationRules = [
        'id'               => 'permit_empty|is_natural_no_zero',
        'nombre'           => 'required|min_length[3]|max_length[60]|is_unique[tipos_turno.nombre,id,{id}]',
        'hora_inicio'      => 'required',
        'hora_fin'         => 'required',
        'duracion_minutos' => 'required|is_natural_no_zero|less_than[1440]',
        'color'            => 'required|regex_match[/^#[0-9a-fA-F]{6}$/]',
        'estado'           => 'permit_empty|in_list[ACTIVO,BAJA]',
    ];

    protected $validationMessages = [
        'nombre' => ['is_unique' => 'Ya existe un tipo de turno con ese nombre.'],
    ];

    /**
     * Duración en minutos entre dos horas "HH:MM". Si el fin es menor o igual al inicio,
     * el turno cruza la medianoche: 22:00 a 06:00 = 480 minutos (8 h).
     * Devuelve 0 si inicio y fin son iguales (turno inválido).
     */
    public static function calcularDuracionMinutos(string $inicio, string $fin): int
    {
        $aMinutos = static function (string $hora): int {
            [$h, $m] = array_map('intval', explode(':', substr($hora, 0, 5)) + [0, 0]);

            return $h * 60 + $m;
        };

        return ($aMinutos($fin) - $aMinutos($inicio) + 1440) % 1440;
    }

    /**
     * Listado con búsqueda por nombre y filtro de estado (HU-12).
     *
     * @return list<TipoTurno>
     */
    public function listar(string $estado = 'ACTIVO', string $busqueda = ''): array
    {
        $this->filtrarPorEstado($estado);

        $busqueda = trim($busqueda);
        if ($busqueda !== '') {
            $this->like('nombre', $busqueda);
        }

        return $this->orderBy('hora_inicio', 'ASC')->orderBy('nombre', 'ASC')->paginate(self::POR_PAGINA);
    }

    /**
     * Tipos de turno activos, ordenados por hora de inicio (para asignar turnos y la cuadrícula).
     *
     * @return list<TipoTurno>
     */
    public function activos(): array
    {
        return $this->orderBy('hora_inicio', 'ASC')->findAll();
    }
}
