<?php

declare(strict_types=1);

namespace App\Models;

use App\Entities\Asignacion;
use App\Entities\TipoTurno;
use App\Entities\Usuario;
use CodeIgniter\Model;
use DateTimeImmutable;

/**
 * Asignaciones de turnos (HU-13) y su lectura semanal para la cuadrícula (HU-14).
 */
class AsignacionModel extends Model
{
    protected $table          = 'asignaciones';
    protected $primaryKey     = 'id';
    protected $returnType     = Asignacion::class;
    protected $useSoftDeletes = true;
    protected $protectFields  = true;
    protected $allowedFields  = [
        'usuario_id', 'sector_id', 'tipo_turno_id', 'fecha', 'inicio', 'fin', 'duracion_minutos', 'creado_por',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    protected $validationRules = [
        'usuario_id'       => 'required|is_natural_no_zero',
        'sector_id'        => 'required|is_natural_no_zero',
        'tipo_turno_id'    => 'required|is_natural_no_zero',
        'fecha'            => 'required|valid_date[Y-m-d]',
        'inicio'           => 'required',
        'fin'              => 'required',
        'duracion_minutos' => 'required|is_natural_no_zero',
    ];

    /**
     * Lunes de la semana (de lunes a domingo) que contiene la fecha.
     */
    public static function lunesDe(string $fecha): string
    {
        $dia = new DateTimeImmutable($fecha);

        return $dia->modify('-' . ((int) $dia->format('N') - 1) . ' days')->format('Y-m-d');
    }

    /**
     * Inicio y fin reales de un turno que empieza en $fecha con el horario del tipo de turno.
     * Un turno de 22:00 a 06:00 termina al día siguiente.
     *
     * @return array{inicio: string, fin: string, duracion_minutos: int}
     */
    public static function calcularHorario(string $fecha, TipoTurno $tipo): array
    {
        $inicio = new DateTimeImmutable($fecha . ' ' . $tipo->inicio() . ':00');
        $fin    = $inicio->modify('+' . (int) $tipo->duracion_minutos . ' minutes');

        return [
            'inicio'           => $inicio->format('Y-m-d H:i:s'),
            'fin'              => $fin->format('Y-m-d H:i:s'),
            'duracion_minutos' => (int) $tipo->duracion_minutos,
        ];
    }

    /**
     * Turno del mismo enfermero/a que se pisa en el tiempo con [$inicio, $fin).
     * Compara fechas y horas completas: la noche del lunes (22 a 06) choca con la mañana del martes (06 a 14)
     * solo si se superponen; terminar a las 06:00 y empezar a las 06:00 no es superposición.
     */
    public function superpuesta(int $usuarioId, string $inicio, string $fin, ?int $excluirId = null): ?Asignacion
    {
        $this->where('usuario_id', $usuarioId)
            ->where('inicio <', $fin)
            ->where('fin >', $inicio);

        if ($excluirId !== null) {
            $this->where('id !=', $excluirId);
        }

        return $this->first();
    }

    /**
     * Minutos ya asignados al enfermero/a en la semana (lunes a domingo) que contiene la fecha.
     * Los turnos cuentan en la semana del día en que empiezan.
     */
    public function minutosEnSemana(int $usuarioId, string $fecha, ?int $excluirId = null): int
    {
        $lunes   = self::lunesDe($fecha);
        $domingo = (new DateTimeImmutable($lunes))->modify('+6 days')->format('Y-m-d');

        $builder = $this->builder()
            ->selectSum('duracion_minutos', 'total')
            ->where('usuario_id', $usuarioId)
            ->where('fecha >=', $lunes)
            ->where('fecha <=', $domingo)
            ->where('deleted_at', null);

        if ($excluirId !== null) {
            $builder->where('id !=', $excluirId);
        }

        return (int) ($builder->get()->getRow()->total ?? 0);
    }

    /**
     * Reglas de negocio de HU-13. Devuelve los errores [campo => mensaje]; vacío si se puede asignar.
     *
     * @return array<string, string>
     */
    public function validarReglas(Usuario $enfermero, TipoTurno $tipo, string $fecha, ?int $excluirId = null): array
    {
        $horario = self::calcularHorario($fecha, $tipo);

        $choque = $this->superpuesta((int) $enfermero->id, $horario['inicio'], $horario['fin'], $excluirId);
        if ($choque !== null) {
            $desde      = (new DateTimeImmutable((string) $choque->inicio))->format('d/m/Y H:i');
            $hasta      = (new DateTimeImmutable((string) $choque->fin))->format('d/m/Y H:i');
            $tipoChoque = (new TipoTurnoModel())->withDeleted()->find((int) $choque->tipo_turno_id);
            $secChoque  = (new SectorModel())->withDeleted()->find((int) $choque->sector_id);
            $detalle    = ($tipoChoque !== null ? " de {$tipoChoque->nombre}" : '') . ($secChoque !== null ? " en {$secChoque->nombre}" : '');

            return ['tipo_turno_id' => "{$enfermero->getNombreCompleto()} ya tiene un turno{$detalle} que se superpone: del {$desde} al {$hasta}. Elija otra fecha, otra franja u otro enfermero/a."];
        }

        $maximo = (int) ($enfermero->carga_horaria_max ?? 0) * 60;
        if ($maximo <= 0) {
            return ['usuario_id' => "{$enfermero->getNombreCompleto()} no tiene cargada su carga horaria máxima. Complétela en su legajo antes de asignarle turnos."];
        }
        if ($maximo > 0) {
            $yaAsignados = $this->minutosEnSemana((int) $enfermero->id, $fecha, $excluirId);
            $total       = $yaAsignados + $horario['duracion_minutos'];

            if ($total > $maximo) {
                $lunes = new DateTimeImmutable(self::lunesDe($fecha));

                return ['usuario_id' => sprintf(
                    'Supera la carga horaria de %s: en la semana del %s al %s ya tiene %s asignadas; con este turno (%s) llegaría a %s y su máximo contratado es %d h.',
                    $enfermero->getNombreCompleto(),
                    $lunes->format('d/m'),
                    $lunes->modify('+6 days')->format('d/m'),
                    self::horasTexto($yaAsignados),
                    self::horasTexto($horario['duracion_minutos']),
                    self::horasTexto($total),
                    (int) $enfermero->carga_horaria_max
                )];
            }
        }

        return [];
    }

    /**
     * Minutos asignados a cada enfermero/a en la semana que empieza el lunes indicado, sumando todos los sectores
     * (resumen de balanceo de cargas de HU-14).
     *
     * @return array<int, int> [usuario_id => minutos]
     */
    public function minutosSemanaPorEnfermero(string $lunes): array
    {
        $domingo = (new DateTimeImmutable($lunes))->modify('+6 days')->format('Y-m-d');

        $filas = $this->builder()
            ->select('usuario_id')
            ->selectSum('duracion_minutos', 'total')
            ->where('fecha >=', $lunes)
            ->where('fecha <=', $domingo)
            ->where('deleted_at', null)
            ->groupBy('usuario_id')
            ->get()
            ->getResultArray();

        $resultado = [];
        foreach ($filas as $fila) {
            $resultado[(int) $fila['usuario_id']] = (int) $fila['total'];
        }

        return $resultado;
    }

    /**
     * Disponibilidad de cada enfermero/a para un turno y fecha (para el formulario de asignación):
     * horas que ya tiene esa semana y, si no puede tomar el turno, el motivo.
     *
     * @param list<Usuario> $enfermeros
     *
     * @return array<int, array{minutos: int, motivo: string}>
     */
    public function disponibilidad(array $enfermeros, TipoTurno $tipo, string $fecha, ?int $excluirId = null): array
    {
        $horario   = self::calcularHorario($fecha, $tipo);
        $resultado = [];

        foreach ($enfermeros as $e) {
            $id      = (int) $e->id;
            $minutos = $this->minutosEnSemana($id, $fecha, $excluirId);
            $motivo  = '';

            if ($this->superpuesta($id, $horario['inicio'], $horario['fin'], $excluirId) !== null) {
                $motivo = 'ya tiene un turno que se superpone';
            } elseif ($minutos + $horario['duracion_minutos'] > (int) ($e->carga_horaria_max ?? 0) * 60) {
                $motivo = 'superaría su máximo semanal';
            }

            $resultado[$id] = ['minutos' => $minutos, 'motivo' => $motivo];
        }

        return $resultado;
    }

    /**
     * Valida las reglas de HU-13 y guarda, todo en una transacción con bloqueos:
     * - bloquea la fila del enfermero/a (FOR UPDATE): si dos administradores le asignan turnos a la vez,
     *   el segundo espera y vuelve a validar con lo que guardó el primero (sin superposiciones ni excesos);
     * - bloquea en modo compartido el sector y el tipo de turno y verifica que sigan activos:
     *   así no se pueden dar de baja mientras se les está asignando un turno.
     *
     * @return array<string, string> errores [campo => mensaje]; vacío si se guardó.
     */
    public function guardarConControles(Asignacion $asignacion, Usuario $enfermero, int $sectorId, TipoTurno $tipo, string $fecha, ?int $excluirId = null): array
    {
        $this->db->transBegin();

        try {
            $this->db->query('SELECT id FROM usuarios WHERE id = ? FOR UPDATE', [(int) $enfermero->id]);

            $sectorActivo = $this->db->query('SELECT id FROM sectores WHERE id = ? AND deleted_at IS NULL LOCK IN SHARE MODE', [$sectorId])->getRow();
            $tipoActivo   = $this->db->query('SELECT id FROM tipos_turno WHERE id = ? AND deleted_at IS NULL LOCK IN SHARE MODE', [(int) $tipo->id])->getRow();

            $errores = [];
            if ($sectorActivo === null) {
                $errores['sector_id'] = 'El sector elegido no está activo.';
            }
            if ($tipoActivo === null) {
                $errores['tipo_turno_id'] = 'El turno elegido no está activo.';
            }
            if ($errores === []) {
                $errores = $this->validarReglas($enfermero, $tipo, $fecha, $excluirId);
            }

            if ($errores === [] && ! $this->save($asignacion)) {
                $errores = $this->errors();
            }

            if ($errores !== []) {
                $this->db->transRollback();

                return $errores;
            }

            $this->db->transCommit();

            return [];
        } catch (\Throwable $e) {
            $this->db->transRollback();
            log_message('error', 'No se pudo guardar la asignación: ' . $e->getMessage());

            return ['fecha' => 'No se pudo guardar el turno. Intente nuevamente.'];
        }
    }

    /**
     * Turnos de un sector en la semana que empieza el lunes indicado, agrupados por fecha y tipo de turno,
     * con el nombre del enfermero/a (HU-14).
     *
     * @return array<string, array<int, list<Asignacion>>> [fecha][tipo_turno_id] => asignaciones
     */
    public function semanaDeSector(int $sectorId, string $lunes): array
    {
        $domingo = (new DateTimeImmutable($lunes))->modify('+6 days')->format('Y-m-d');

        $filas = $this->select('asignaciones.*, usuarios.nombre, usuarios.apellido, usuarios.estado AS usuario_estado')
            ->join('usuarios', 'usuarios.id = asignaciones.usuario_id')
            ->where('asignaciones.sector_id', $sectorId)
            ->where('asignaciones.fecha >=', $lunes)
            ->where('asignaciones.fecha <=', $domingo)
            ->orderBy('usuarios.apellido', 'ASC')
            ->findAll();

        $grilla = [];
        foreach ($filas as $fila) {
            $grilla[(string) $fila->fecha][(int) $fila->tipo_turno_id][] = $fila;
        }

        return $grilla;
    }

    /**
     * Turnos de un enfermero/a entre dos fechas (inclusive), con el sector y el tipo de turno (HU-17).
     * Incluye sectores y tipos dados de baja después de asignar, para no perder la historia.
     * Siempre filtra por el usuario recibido: el controlador le pasa el de la sesión, nunca uno de la URL.
     *
     * @return list<Asignacion>
     */
    public function delEnfermero(int $usuarioId, string $desde, string $hasta): array
    {
        return $this->select('asignaciones.*, sectores.nombre AS sector_nombre, sectores.es_critico AS sector_critico, '
                . 'tipos_turno.nombre AS tipo_nombre, tipos_turno.color AS tipo_color')
            ->join('sectores', 'sectores.id = asignaciones.sector_id')
            ->join('tipos_turno', 'tipos_turno.id = asignaciones.tipo_turno_id')
            ->where('asignaciones.usuario_id', $usuarioId)
            ->where('asignaciones.fecha >=', $desde)
            ->where('asignaciones.fecha <=', $hasta)
            ->orderBy('asignaciones.inicio', 'ASC')
            ->findAll();
    }

    /**
     * Próximo turno del enfermero/a que todavía no terminó (el que está en curso o el siguiente).
     */
    public function proximoDelEnfermero(int $usuarioId, string $ahora): ?Asignacion
    {
        return $this->select('asignaciones.*, sectores.nombre AS sector_nombre, sectores.es_critico AS sector_critico, '
                . 'tipos_turno.nombre AS tipo_nombre, tipos_turno.color AS tipo_color')
            ->join('sectores', 'sectores.id = asignaciones.sector_id')
            ->join('tipos_turno', 'tipos_turno.id = asignaciones.tipo_turno_id')
            ->where('asignaciones.usuario_id', $usuarioId)
            ->where('asignaciones.fin >', $ahora)
            ->orderBy('asignaciones.inicio', 'ASC')
            ->first();
    }

    /**
     * Siguiente turno del enfermero/a que todavía no empezó (para el resumen "Siguiente" del cronograma).
     */
    public function siguienteProgramado(int $usuarioId, string $ahora): ?Asignacion
    {
        return $this->select('asignaciones.*, sectores.nombre AS sector_nombre, sectores.es_critico AS sector_critico, '
                . 'tipos_turno.nombre AS tipo_nombre, tipos_turno.color AS tipo_color')
            ->join('sectores', 'sectores.id = asignaciones.sector_id')
            ->join('tipos_turno', 'tipos_turno.id = asignaciones.tipo_turno_id')
            ->where('asignaciones.usuario_id', $usuarioId)
            ->where('asignaciones.inicio >', $ahora)
            ->orderBy('asignaciones.inicio', 'ASC')
            ->first();
    }

    /** 450 -> "7 h 30 min"; 480 -> "8 h". */
    public static function horasTexto(int $minutos): string
    {
        $horas = intdiv($minutos, 60);
        $resto = $minutos % 60;

        return $horas . ' h' . ($resto > 0 ? ' ' . $resto . ' min' : '');
    }
}
