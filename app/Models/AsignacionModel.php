<?php

declare(strict_types=1);

namespace App\Models;

use App\Entities\Asignacion;
use App\Entities\TipoTurno;
use App\Entities\Usuario;
use CodeIgniter\Model;
use DateTimeImmutable;

/**
 * Asignaciones de turnos (HU-13) y lectura mensual/semanal (HU-14).
 */
class AsignacionModel extends Model
{
    protected $table          = 'asignaciones';
    protected $primaryKey     = 'id';
    protected $returnType     = Asignacion::class;
    protected $useSoftDeletes = true;
    protected $protectFields  = true;
    protected $allowedFields  = [
        'usuario_id', 'sector_id', 'tipo_turno_id', 'fecha', 'inicio', 'fin',
        'duracion_minutos', 'es_extra', 'minutos_extra', 'creado_por',
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
     * Fecha máxima habilitada para asignar turnos:
     * la planificación mensual está disponible 2 mes antes (hasta el último día del mes siguiente al actual).
     */
    public static function fechaMaximaHabilitada(): string
    {
        return (new DateTimeImmutable('today'))
            ->modify('+2 months')
            ->format('Y-m-d');
    }

    /**
     * Inicio y fin reales de un turno que empieza en $fecha con el horario del tipo de turno.
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
     * Minutos asignados (totales y extras) al enfermero/a en el mes 'YYYY-MM'.
     *
     * @return array{total: int, extra: int}
     */
    public function resumenMesEnfermero(int $usuarioId, string $anioMes, ?int $excluirId = null): array
    {
        $desde = $anioMes . '-01';
        $hasta = (new DateTimeImmutable($desde))->modify('last day of this month')->format('Y-m-d');

        $builder = $this->builder()
            ->selectSum('duracion_minutos', 'total')
            ->selectSum('minutos_extra', 'extra')
            ->where('usuario_id', $usuarioId)
            ->where('fecha >=', $desde)
            ->where('fecha <=', $hasta)
            ->where('deleted_at', null);

        if ($excluirId !== null) {
            $builder->where('id !=', $excluirId);
        }

        $fila = $builder->get()->getRow();

        return [
            'total' => (int) ($fila->total ?? 0),
            'extra' => (int) ($fila->extra ?? 0),
        ];
    }

    /**
     * Calcula si el turno computa como hora extra y cuántos minutos extras representa,
     * comparando los minutos semanales ya asignados contra carga_horaria_max.
     *
     * @return array{es_extra: int, minutos_extra: int}
     */
    public function calcularHorasExtras(Usuario $enfermero, string $fecha, int $duracionMinutos, ?int $excluirId = null): array
    {
        $maximoMinutos = (int) ($enfermero->carga_horaria_max ?? 0) * 60;
        if ($maximoMinutos <= 0) {
            return ['es_extra' => 0, 'minutos_extra' => 0];
        }

        $yaAsignados = $this->minutosEnSemana((int) $enfermero->id, $fecha, $excluirId);
        $nuevoTotal  = $yaAsignados + $duracionMinutos;

        if ($nuevoTotal <= $maximoMinutos) {
            return ['es_extra' => 0, 'minutos_extra' => 0];
        }

        // Si ya había cumplido el tope antes de este turno, todo el turno es extra;
        // si lo cruza con este turno, solo el excedente es extra.
        $excedente = $yaAsignados >= $maximoMinutos
            ? $duracionMinutos
            : ($nuevoTotal - $maximoMinutos);

        return [
            'es_extra'      => 1,
            'minutos_extra' => $excedente,
        ];
    }

    /**
     * Reglas de negocio:
     * - Verifica que el tipo de turno pertenezca al sector elegido.
     * - Rechaza superposición horaria del mismo enfermero/a.
     * - Si supera su carga horaria contratada, NO bloquea: permite tomarlo como hora extra.
     *
     * @return array<string, string>
     */
    public function validarReglas(Usuario $enfermero, int $sectorId, TipoTurno $tipo, string $fecha, ?int $excluirId = null): array
    {
        if ((int) $tipo->sector_id !== $sectorId) {
            return ['tipo_turno_id' => "El turno «{$tipo->nombre}» no corresponde a la zona seleccionada."];
        }

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

        if ((int) ($enfermero->carga_horaria_max ?? 0) <= 0) {
            return ['usuario_id' => "{$enfermero->getNombreCompleto()} no tiene cargada su carga horaria máxima. Complétela en su legajo antes de asignarle turnos."];
        }

        return [];
    }

    /**
     * Minutos asignados a cada enfermero/a en el mes 'YYYY-MM' (para resumen de balanceo mensual).
     *
     * @return array<int, array{total: int, extra: int}> [usuario_id => ['total' => ..., 'extra' => ...]]
     */
    public function minutosMesPorEnfermero(string $anioMes): array
    {
        $desde = $anioMes . '-01';
        $hasta = (new DateTimeImmutable($desde))->modify('last day of this month')->format('Y-m-d');

        $filas = $this->builder()
            ->select('usuario_id')
            ->selectSum('duracion_minutos', 'total')
            ->selectSum('minutos_extra', 'extra')
            ->where('fecha >=', $desde)
            ->where('fecha <=', $hasta)
            ->where('deleted_at', null)
            ->groupBy('usuario_id')
            ->get()
            ->getResultArray();

        $resultado = [];
        foreach ($filas as $fila) {
            $resultado[(int) $fila['usuario_id']] = [
                'total' => (int) $fila['total'],
                'extra' => (int) $fila['extra'],
            ];
        }

        return $resultado;
    }

    /**
     * Minutos asignados a cada enfermero/a en la semana que empieza el lunes indicado.
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
     * Disponibilidad y prioridad de cada enfermero/a para un turno y fecha:
     * - Prioridad 1: enfermeros que todavía NO cumplieron su carga horaria (ordenados de menor a mayor carga).
     * - Prioridad 2: enfermeros que YA cumplieron su carga y pueden cubrir como HORA EXTRA.
     * - Prioridad 3: enfermeros no disponibles por superposición horaria.
     *
     * @param list<Usuario> $enfermeros
     *
     * @return array{
     *     ordenados: list<Usuario>,
     *     info: array<int, array{minutos: int, cumple_carga: bool, es_extra: bool, motivo: string}>
     * }
     */
    public function disponibilidadConPrioridad(array $enfermeros, TipoTurno $tipo, string $fecha, ?int $excluirId = null): array
    {
        $horario = self::calcularHorario($fecha, $tipo);
        $info    = [];

        foreach ($enfermeros as $e) {
            $id          = (int) $e->id;
            $minutos     = $this->minutosEnSemana($id, $fecha, $excluirId);
            $maxMinutos  = (int) ($e->carga_horaria_max ?? 0) * 60;
            $superpuesto = $this->superpuesta($id, $horario['inicio'], $horario['fin'], $excluirId) !== null;
            $cumpleCarga = $maxMinutos > 0 && $minutos >= $maxMinutos;
            $seraExtra   = $maxMinutos > 0 && ($minutos + $horario['duracion_minutos']) > $maxMinutos;

            $info[$id] = [
                'minutos'      => $minutos,
                'cumple_carga' => $cumpleCarga,
                'es_extra'     => $seraExtra,
                'motivo'       => $superpuesto ? 'ya tiene un turno que se superpone' : '',
            ];
        }

        $ordenados = $enfermeros;
        usort($ordenados, static function (Usuario $a, Usuario $b) use ($info): int {
            $ia = $info[(int) $a->id];
            $ib = $info[(int) $b->id];

            // Grupo: 0 = disponible sin cumplir tope (máxima prioridad), 1 = disponible para hora extra, 2 = superpuesto
            $grupoA = $ia['motivo'] !== '' ? 2 : ($ia['cumple_carga'] ? 1 : 0);
            $grupoB = $ib['motivo'] !== '' ? 2 : ($ib['cumple_carga'] ? 1 : 0);

            if ($grupoA !== $grupoB) {
                return $grupoA <=> $grupoB;
            }

            if ($ia['minutos'] !== $ib['minutos']) {
                return $ia['minutos'] <=> $ib['minutos'];
            }

            return strcmp((string) $a->apellido, (string) $b->apellido);
        });

        return [
            'ordenados' => $ordenados,
            'info'      => $info,
        ];
    }

    /**
     * Valida las reglas y guarda en una transacción con bloqueos, recalculando horas extras dentro del bloqueo
     * para evitar condiciones de carrera si dos administradores asignan a la vez.
     *
     * @return array<string, string> errores [campo => mensaje]; vacío si se guardó.
     */
    public function guardarConControles(Asignacion $asignacion, Usuario $enfermero, int $sectorId, TipoTurno $tipo, string $fecha, ?int $excluirId = null): array
    {
        $this->db->transBegin();

        try {
            $this->db->query('SELECT id FROM usuarios WHERE id = ? FOR UPDATE', [(int) $enfermero->id]);

            $sectorActivo = $this->db->query('SELECT id FROM sectores WHERE id = ? AND deleted_at IS NULL LOCK IN SHARE MODE', [$sectorId])->getRow();
            $tipoActivo   = $this->db->query('SELECT id, sector_id FROM tipos_turno WHERE id = ? AND deleted_at IS NULL LOCK IN SHARE MODE', [(int) $tipo->id])->getRow();

            $errores = [];
            if ($sectorActivo === null) {
                $errores['sector_id'] = 'El sector elegido no está activo.';
            }
            if ($tipoActivo === null) {
                $errores['tipo_turno_id'] = 'El turno elegido no está activo.';
            } elseif ((int) $tipoActivo->sector_id !== $sectorId) {
                $errores['tipo_turno_id'] = 'El turno elegido no pertenece al sector seleccionado.';
            }

            if ($errores === []) {
                $errores = $this->validarReglas($enfermero, $sectorId, $tipo, $fecha, $excluirId);
            }

            if ($errores === []) {
                // Recalcular dentro del bloqueo transaccional si computa como hora extra
                $extras = $this->calcularHorasExtras($enfermero, $fecha, (int) $asignacion->duracion_minutos, $excluirId);
                $asignacion->es_extra      = $extras['es_extra'];
                $asignacion->minutos_extra = $extras['minutos_extra'];

                if (! $this->save($asignacion)) {
                    $errores = $this->errors();
                }
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
     * Turnos de un sector en un mes 'YYYY-MM', agrupados por fecha y tipo de turno,
     * con los datos del enfermero/a (para el calendario mensual del administrador).
     *
     * @return array<string, array<int, list<Asignacion>>> [fecha][tipo_turno_id] => asignaciones
     */
    public function mesDeSector(int $sectorId, string $anioMes): array
    {
        $desde = $anioMes . '-01';
        $hasta = (new DateTimeImmutable($desde))->modify('last day of this month')->format('Y-m-d');

        $filas = $this->select('asignaciones.*, usuarios.nombre, usuarios.apellido, usuarios.matricula, usuarios.especialidad, usuarios.estado AS usuario_estado')
            ->join('usuarios', 'usuarios.id = asignaciones.usuario_id')
            ->where('asignaciones.sector_id', $sectorId)
            ->where('asignaciones.fecha >=', $desde)
            ->where('asignaciones.fecha <=', $hasta)
            ->orderBy('usuarios.apellido', 'ASC')
            ->findAll();

        $grilla = [];
        foreach ($filas as $fila) {
            $grilla[(string) $fila->fecha][(int) $fila->tipo_turno_id][] = $fila;
        }

        return $grilla;
    }

    /**
     * Turnos de un sector en la semana que empieza el lunes indicado (se mantiene por compatibilidad).
     *
     * @return array<string, array<int, list<Asignacion>>>
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
     * Turnos de un enfermero/a entre dos fechas (inclusive).
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

    public static function horasTexto(int $minutos): string
    {
        $horas = intdiv($minutos, 60);
        $resto = $minutos % 60;

        return $horas . ' h' . ($resto > 0 ? ' ' . $resto . ' min' : '');
    }
}