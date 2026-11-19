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

    /** Nombres de los meses (índice 1 a 12), únicos para el panel, el planificador y Mi cronograma. */
    public const MESES = [
        1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
        5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
        9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
    ];

    /** Estados de disponibilidad de un enfermero/a para un turno (modal del planificador y formulario). */
    public const DISPONIBLE    = 'disponible';
    public const EXTRA         = 'extra';
    public const NO_DISPONIBLE = 'no_disponible';

    /** "Noviembre 2026" a partir de 'YYYY-MM' o 'YYYY-MM-DD'. */
    public static function nombreMes(string $fecha): string
    {
        $dia = new DateTimeImmutable(substr($fecha, 0, 7) . '-01');

        return self::MESES[(int) $dia->format('n')] . ' ' . $dia->format('Y');
    }

    /**
     * Semanas (de lunes a domingo) que cubren el mes 'YYYY-MM', para dibujar la cuadrícula mensual.
     * Incluye los días del mes anterior/siguiente que completan la primera y la última semana.
     * La usan el panel (Home), el planificador y Mi cronograma: una sola fuente para no desalinear calendarios.
     *
     * @return list<list<string>> cada semana es una lista de 7 fechas 'Y-m-d'
     */
    public static function semanasDelMes(string $anioMes): array
    {
        $primerDia = new DateTimeImmutable(substr($anioMes, 0, 7) . '-01');
        $cursor    = new DateTimeImmutable(self::lunesDe($primerDia->format('Y-m-d')));
        $finCal    = (new DateTimeImmutable(self::lunesDe($primerDia->modify('last day of this month')->format('Y-m-d'))))->modify('+6 days');

        $semanas = [];
        while ($cursor <= $finCal) {
            $fila = [];
            for ($i = 0; $i < 7; $i++) {
                $fila[] = $cursor->modify("+{$i} days")->format('Y-m-d');
            }
            $semanas[] = $fila;
            $cursor    = $cursor->modify('+7 days');
        }

        return $semanas;
    }

    /**
     * Lunes de la semana (de lunes a domingo) que contiene la fecha.
     */
    public static function lunesDe(string $fecha): string
    {
        $dia = new DateTimeImmutable($fecha);

        return $dia->modify('-' . ((int) $dia->format('N') - 1) . ' days')->format('Y-m-d');
    }

    /**
     * Fecha máxima habilitada para asignar turnos: hoy + 2 meses (por ejemplo, el 19/11 se puede
     * planificar hasta el 19/01). Es una ventana móvil, no "hasta fin de mes": así el administrador
     * siempre tiene la misma anticipación sin importar en qué día del mes esté.
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
     * Recalcula es_extra / minutos_extra de TODOS los turnos (no eliminados) del enfermero/a en la
     * semana (lunes a domingo) que contiene $fecha.
     *
     * Por qué recalcular la semana entera y no solo el turno que se toca: las horas extras dependen de
     * los demás turnos de la semana. Si se elimina el turno del lunes, el del viernes que era "extra"
     * deja de serlo; si se carga un turno el martes cuando el viernes ya estaba cargado, el excedente
     * corresponde al viernes (es el que cruza el tope), no al martes.
     *
     * Regla: se recorren los turnos en orden cronológico de inicio (y por id ante empates) acumulando
     * minutos. Un turno es extra en la parte que queda por encima de carga_horaria_max * 60:
     * minutos_extra = clamp(acumulado - máximo, 0, duración). Así el resultado depende solo de los
     * turnos que existen, nunca del orden en que se cargaron.
     *
     * Debe llamarse DENTRO de la transacción que modificó los turnos: bloquea las filas de la semana
     * (FOR UPDATE) y escribe con el Query Builder (sin validación del modelo ni cambio de updated_at,
     * porque no es una edición del administrador sino un dato derivado).
     */
    public function recalcularExtrasSemana(int $usuarioId, string $fecha): void
    {
        $lunes   = self::lunesDe($fecha);
        $domingo = (new DateTimeImmutable($lunes))->modify('+6 days')->format('Y-m-d');

        $carga  = $this->db->query('SELECT carga_horaria_max FROM usuarios WHERE id = ?', [$usuarioId])->getRow();
        $maximo = (int) ($carga->carga_horaria_max ?? 0) * 60;

        $turnos = $this->db->query(
            'SELECT id, duracion_minutos, es_extra, minutos_extra FROM asignaciones '
            . 'WHERE usuario_id = ? AND fecha BETWEEN ? AND ? AND deleted_at IS NULL '
            . 'ORDER BY inicio ASC, id ASC FOR UPDATE',
            [$usuarioId, $lunes, $domingo]
        )->getResultArray();

        $acumulado = 0;
        foreach ($turnos as $turno) {
            $duracion = (int) $turno['duracion_minutos'];
            $acumulado += $duracion;

            // Sin carga horaria definida no hay contra qué medir: no se marca nada como extra.
            $minutosExtra = $maximo > 0 ? max(0, min($duracion, $acumulado - $maximo)) : 0;
            $esExtra      = $minutosExtra > 0 ? 1 : 0;

            if ((int) $turno['es_extra'] !== $esExtra || (int) $turno['minutos_extra'] !== $minutosExtra) {
                $this->db->table($this->table)
                    ->where('id', (int) $turno['id'])
                    ->update(['es_extra' => $esExtra, 'minutos_extra' => $minutosExtra]);
            }
        }
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
            return ['tipo_turno_id' => "El turno «{$tipo->nombre}» no corresponde al sector seleccionado."];
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
     * $excluirId deja afuera un turno (el que se está reasignando, para no contarlo dos veces).
     *
     * @return array<int, int> [usuario_id => minutos]
     */
    public function minutosSemanaPorEnfermero(string $lunes, ?int $excluirId = null): array
    {
        $domingo = (new DateTimeImmutable($lunes))->modify('+6 days')->format('Y-m-d');

        $builder = $this->builder()
            ->select('usuario_id')
            ->selectSum('duracion_minutos', 'total')
            ->where('fecha >=', $lunes)
            ->where('fecha <=', $domingo)
            ->where('deleted_at', null);

        if ($excluirId !== null) {
            $builder->where('id !=', $excluirId);
        }

        $filas = $builder->groupBy('usuario_id')
            ->get()
            ->getResultArray();

        $resultado = [];
        foreach ($filas as $fila) {
            $resultado[(int) $fila['usuario_id']] = (int) $fila['total'];
        }

        return $resultado;
    }

    /**
     * Evalúa en UNA sola pasada a qué grupo pertenece cada enfermero/a para cubrir el turno $tipo del día
     * $fecha, y devuelve la lista ya ordenada por prioridad. Es la única fuente de verdad del orden: la usan
     * el formulario de asignación (disponibilidadConPrioridad) y el endpoint JSON del modal del planificador.
     *
     * Consultas fijas (sin N+1, no importa cuántos enfermeros haya): una para los minutos de la semana de
     * todos (minutosSemanaPorEnfermero) y una para las superposiciones de todos.
     *
     * Grupos y orden:
     *  1. DISPONIBLE: no se superpone y con este turno queda dentro de su carga semanal.
     *     Ordenados por menos horas en la semana (balancea la carga).
     *  2. EXTRA: no se superpone pero con este turno supera su carga: se registraría como hora extra
     *     (regla aceptada por el grupo: no se rechaza). Ordenados por menos horas.
     *  3. NO_DISPONIBLE: se superpone con otro turno suyo, o no tiene carga horaria definida
     *     (carga_horaria_max vacía o 0: no hay contra qué medir y validarReglas() rechaza la asignación).
     * Ante empate de horas: apellido y nombre.
     *
     * @param list<Usuario> $enfermeros
     *
     * @return list<array{enfermero: Usuario, minutos_semana: int, maximo_minutos: int, estado: string, motivo: string|null}>
     */
    public function evaluarDisponibilidad(array $enfermeros, TipoTurno $tipo, string $fecha, ?int $excluirId = null): array
    {
        if ($enfermeros === []) {
            return [];
        }

        $horario  = self::calcularHorario($fecha, $tipo);
        $minutos  = $this->minutosSemanaPorEnfermero(self::lunesDe($fecha), $excluirId);
        $choques  = $this->superposicionesPorEnfermero(
            array_map(static fn (Usuario $e): int => (int) $e->id, $enfermeros),
            $horario['inicio'],
            $horario['fin'],
            $excluirId
        );

        $filas = [];
        foreach ($enfermeros as $e) {
            $id      = (int) $e->id;
            $semana  = $minutos[$id] ?? 0;
            $maximo  = (int) ($e->carga_horaria_max ?? 0) * 60;

            if ($maximo <= 0) {
                $estado = self::NO_DISPONIBLE;
                $motivo = 'Sin carga horaria definida';
            } elseif (isset($choques[$id])) {
                $estado = self::NO_DISPONIBLE;
                $motivo = $choques[$id];
            } elseif ($semana + $horario['duracion_minutos'] > $maximo) {
                $extra  = min($horario['duracion_minutos'], $semana + $horario['duracion_minutos'] - $maximo);
                $estado = self::EXTRA;
                $motivo = 'Supera su carga semanal: ' . self::horasTexto($extra) . ' se registrarían como hora extra';
            } else {
                $estado = self::DISPONIBLE;
                $motivo = null;
            }

            $filas[] = [
                'enfermero'      => $e,
                'minutos_semana' => $semana,
                'maximo_minutos' => $maximo,
                'estado'         => $estado,
                'motivo'         => $motivo,
            ];
        }

        $orden = [self::DISPONIBLE => 0, self::EXTRA => 1, self::NO_DISPONIBLE => 2];
        usort($filas, static function (array $a, array $b) use ($orden): int {
            return [$orden[$a['estado']], $a['minutos_semana'], (string) $a['enfermero']->apellido, (string) $a['enfermero']->nombre]
                <=> [$orden[$b['estado']], $b['minutos_semana'], (string) $b['enfermero']->apellido, (string) $b['enfermero']->nombre];
        });

        return $filas;
    }

    /**
     * Para cada enfermero/a que tenga un turno que se pisa con [$inicio, $fin), un texto que explica con cuál.
     * Una sola consulta para todos (antes era una por enfermero).
     *
     * @param list<int> $usuarioIds
     *
     * @return array<int, string> [usuario_id => motivo]
     */
    private function superposicionesPorEnfermero(array $usuarioIds, string $inicio, string $fin, ?int $excluirId): array
    {
        $builder = $this->builder()
            ->select('asignaciones.usuario_id, asignaciones.inicio, asignaciones.fin, tipos_turno.nombre AS tipo_nombre, sectores.nombre AS sector_nombre')
            ->join('tipos_turno', 'tipos_turno.id = asignaciones.tipo_turno_id', 'left')
            ->join('sectores', 'sectores.id = asignaciones.sector_id', 'left')
            ->whereIn('asignaciones.usuario_id', $usuarioIds)
            ->where('asignaciones.inicio <', $fin)
            ->where('asignaciones.fin >', $inicio)
            ->where('asignaciones.deleted_at', null)
            ->orderBy('asignaciones.inicio', 'ASC');

        if ($excluirId !== null) {
            $builder->where('asignaciones.id !=', $excluirId);
        }

        $motivos = [];
        foreach ($builder->get()->getResultArray() as $fila) {
            $uid = (int) $fila['usuario_id'];
            if (isset($motivos[$uid])) {
                continue; // alcanza con informar el primero
            }
            $desde = (new DateTimeImmutable((string) $fila['inicio']))->format('d/m H:i');
            $hasta = (new DateTimeImmutable((string) $fila['fin']))->format('d/m H:i');

            $motivos[$uid] = 'Ya tiene un turno que se superpone: ' . ($fila['tipo_nombre'] ?? 'turno')
                . ' en ' . ($fila['sector_nombre'] ?? 'otro sector') . " ({$desde} a {$hasta})";
        }

        return $motivos;
    }

    /**
     * Adaptador de evaluarDisponibilidad() para el formulario de asignación (admin/asignaciones/form),
     * que espera los enfermeros ordenados y un mapa de información por id.
     * En 'info', 'motivo' viene vacío salvo para los NO disponibles (la vista lo usa para deshabilitarlos).
     *
     * @param list<Usuario> $enfermeros
     *
     * @return array{
     *     ordenados: list<Usuario>,
     *     info: array<int, array{minutos: int, cumple_carga: bool, es_extra: bool, motivo: string, estado: string}>
     * }
     */
    public function disponibilidadConPrioridad(array $enfermeros, TipoTurno $tipo, string $fecha, ?int $excluirId = null): array
    {
        $ordenados = [];
        $info      = [];

        foreach ($this->evaluarDisponibilidad($enfermeros, $tipo, $fecha, $excluirId) as $fila) {
            $id          = (int) $fila['enfermero']->id;
            $ordenados[] = $fila['enfermero'];
            $info[$id]   = [
                'minutos'      => $fila['minutos_semana'],
                'cumple_carga' => $fila['maximo_minutos'] > 0 && $fila['minutos_semana'] >= $fila['maximo_minutos'],
                'es_extra'     => $fila['estado'] === self::EXTRA,
                'motivo'       => $fila['estado'] === self::NO_DISPONIBLE ? (string) $fila['motivo'] : '',
                'estado'       => $fila['estado'],
            ];
        }

        return [
            'ordenados' => $ordenados,
            'info'      => $info,
        ];
    }

    /**
     * Valida las reglas y guarda en una transacción con bloqueos. Después de guardar recalcula las horas
     * extras de la semana afectada (y de la semana/enfermero anterior si la reasignación los cambió),
     * todo dentro de la misma transacción: si algo falla no queda un turno guardado con extras viejos.
     *
     * Bloqueos: las filas de usuarios involucrados (el nuevo y, si es reasignación, el anterior) se toman
     * FOR UPDATE en orden de id para que dos administradores no se crucen (ni se bloqueen mutuamente).
     *
     * @return array<string, string> errores [campo => mensaje]; vacío si se guardó.
     */
    public function guardarConControles(Asignacion $asignacion, Usuario $enfermero, int $sectorId, TipoTurno $tipo, string $fecha, ?int $excluirId = null): array
    {
        $this->db->transBegin();

        try {
            // Datos que tenía el turno antes de reasignarlo (enfermero y semana viejos), leídos con bloqueo.
            $anterior = $excluirId !== null
                ? $this->db->query('SELECT usuario_id, fecha FROM asignaciones WHERE id = ? FOR UPDATE', [$excluirId])->getRow()
                : null;

            $usuariosABloquear = array_unique(array_filter([(int) $enfermero->id, (int) ($anterior->usuario_id ?? 0)]));
            sort($usuariosABloquear);
            foreach ($usuariosABloquear as $uid) {
                $this->db->query('SELECT id FROM usuarios WHERE id = ? FOR UPDATE', [$uid]);
            }

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

            if ($errores === [] && ! $this->save($asignacion)) {
                $errores = $this->errors();
            }

            if ($errores !== []) {
                $this->db->transRollback();

                return $errores;
            }

            $id = $excluirId ?? (int) $this->getInsertID();

            // Semana nueva (donde quedó el turno) y, si cambió el enfermero o la semana, también la vieja:
            // allí el turno ya no está y otros turnos pueden dejar de ser extra.
            $this->recalcularExtrasSemana((int) $enfermero->id, $fecha);
            if ($anterior !== null
                && ((int) $anterior->usuario_id !== (int) $enfermero->id || self::lunesDe((string) $anterior->fecha) !== self::lunesDe($fecha))) {
                $this->recalcularExtrasSemana((int) $anterior->usuario_id, (string) $anterior->fecha);
            }

            // Devolver al controlador el valor definitivo (para el mensaje "registrado como hora extra").
            $final = $this->db->query('SELECT es_extra, minutos_extra FROM asignaciones WHERE id = ?', [$id])->getRow();
            $asignacion->id            = $id;
            $asignacion->es_extra      = (int) ($final->es_extra ?? 0);
            $asignacion->minutos_extra = (int) ($final->minutos_extra ?? 0);

            $this->db->transCommit();

            return [];
        } catch (\Throwable $e) {
            $this->db->transRollback();
            log_message('error', 'No se pudo guardar la asignación: ' . $e->getMessage());

            return ['fecha' => 'No se pudo guardar el turno. Intente nuevamente.'];
        }
    }

    /**
     * Elimina un turno (baja lógica: deleted_at, nunca DELETE físico) y recalcula las horas extras de esa
     * semana del enfermero/a en la misma transacción: al sacar un turno, otros de la semana pueden dejar
     * de ser hora extra.
     */
    public function eliminarConRecalculo(Asignacion $asignacion): bool
    {
        $this->db->transBegin();

        try {
            $this->db->query('SELECT id FROM usuarios WHERE id = ? FOR UPDATE', [(int) $asignacion->usuario_id]);

            if (! $this->delete((int) $asignacion->id)) {
                $this->db->transRollback();

                return false;
            }

            // El turno eliminado conserva sus valores como historia; los que quedan se recalculan.
            $this->recalcularExtrasSemana((int) $asignacion->usuario_id, (string) $asignacion->fecha);

            $this->db->transCommit();

            return true;
        } catch (\Throwable $e) {
            $this->db->transRollback();
            log_message('error', 'No se pudo eliminar la asignación: ' . $e->getMessage());

            return false;
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