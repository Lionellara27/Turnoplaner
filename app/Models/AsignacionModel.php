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

    /** Motivo del último rechazo de eliminarConRecalculo() (mensaje para mostrar), o null. */
    private ?string $ultimoError = null;

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

    /**
     * Estados de un turno en el historial del enfermero/a (HU-19): código => [etiqueta, ícono Bootstrap].
     */
    public const HISTORIAL_CUMPLIDO           = 'cumplido';
    public const HISTORIAL_PROGRAMADO         = 'programado';
    public const HISTORIAL_EN_CURSO           = 'en_curso';
    public const HISTORIAL_AUSENTE_CUBIERTO   = 'ausente_cubierto';
    public const HISTORIAL_AUSENCIA_GESTION   = 'ausencia_en_gestion';
    public const HISTORIAL_AUSENCIA_RECHAZADA = 'ausencia_rechazada';

    public const ESTADOS_HISTORIAL = [
        self::HISTORIAL_CUMPLIDO           => ['Cumplido', 'bi-check-circle-fill'],
        self::HISTORIAL_PROGRAMADO         => ['Programado', 'bi-calendar-event'],
        self::HISTORIAL_EN_CURSO           => ['En curso', 'bi-play-circle-fill'],
        self::HISTORIAL_AUSENTE_CUBIERTO   => ['Ausente justificado (Cubierto)', 'bi-person-check'],
        self::HISTORIAL_AUSENCIA_GESTION   => ['Ausencia en gestión', 'bi-hourglass-split'],
        self::HISTORIAL_AUSENCIA_RECHAZADA => ['Ausencia rechazada', 'bi-x-circle'],
    ];

    /**
     * Columnas calculadas con la ausencia del turno (HU-16 / HU-18), para sumar al SELECT de las consultas
     * que devuelven entidades Asignacion:
     *  - ausencia_id / ausencia_estado: la ausencia activa (PENDIENTE o CUBIERTA), o NULL.
     *  - reemplazo_de_ausencia_id: si este turno se creó para cubrir una ausencia (CUBIERTA), su id.
     * Son subconsultas por índice (asignacion_id / reemplazo_asignacion_id): no multiplican filas.
     */
    public const SQL_DATOS_AUSENCIA =
        "(SELECT au_a.id FROM ausencias au_a WHERE au_a.asignacion_id = asignaciones.id AND au_a.estado IN ('PENDIENTE','CUBIERTA') ORDER BY au_a.id DESC LIMIT 1) AS ausencia_id, "
        . "(SELECT au_b.estado FROM ausencias au_b WHERE au_b.asignacion_id = asignaciones.id AND au_b.estado IN ('PENDIENTE','CUBIERTA') ORDER BY au_b.id DESC LIMIT 1) AS ausencia_estado, "
        . "(SELECT au_c.id FROM ausencias au_c WHERE au_c.reemplazo_asignacion_id = asignaciones.id AND au_c.estado = 'CUBIERTA' ORDER BY au_c.id DESC LIMIT 1) AS reemplazo_de_ausencia_id";

    /**
     * Condición SQL "el turno no tiene ausencia activa" (o, con $soloCubiertas, "no tiene ausencia CUBIERTA").
     *
     * Reglas (HU-16 / HU-18):
     *  - Un turno con ausencia PENDIENTE o CUBIERTA no cuenta para la dotación ni para las horas de la semana.
     *  - Para la superposición solo se ignora la CUBIERTA: la PENDIENTE todavía puede ser rechazada y el
     *    enfermero/a tendría que presentarse.
     */
    public static function sinAusencia(string $tabla = 'asignaciones', bool $soloCubiertas = false): string
    {
        $estados = $soloCubiertas ? "'CUBIERTA'" : "'PENDIENTE','CUBIERTA'";

        return "NOT EXISTS (SELECT 1 FROM ausencias au_x WHERE au_x.asignacion_id = {$tabla}.id AND au_x.estado IN ({$estados}))";
    }

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
     * Un turno con ausencia CUBIERTA no bloquea (otro lo cubre); uno con ausencia PENDIENTE sí.
     */
    public function superpuesta(int $usuarioId, string $inicio, string $fin, ?int $excluirId = null): ?Asignacion
    {
        $this->where('usuario_id', $usuarioId)
            ->where('inicio <', $fin)
            ->where('fin >', $inicio)
            ->where(self::sinAusencia('asignaciones', true), null, false);

        if ($excluirId !== null) {
            $this->where('id !=', $excluirId);
        }

        return $this->first();
    }

    /**
     * Minutos ya asignados al enfermero/a en la semana (lunes a domingo) que contiene la fecha.
     * No cuenta los turnos con ausencia activa.
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
            ->where('deleted_at', null)
            ->where(self::sinAusencia(), null, false);

        if ($excluirId !== null) {
            $builder->where('id !=', $excluirId);
        }

        return (int) ($builder->get()->getRow()->total ?? 0);
    }

    /**
     * Minutos asignados (totales y extras) al enfermero/a en el mes 'YYYY-MM' (sin turnos con ausencia activa).
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
            ->where('deleted_at', null)
            ->where(self::sinAusencia(), null, false);

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
     * Los turnos con ausencia activa (PENDIENTE o CUBIERTA, HU-18) no suman minutos y quedan en 0 extra.
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
            'SELECT id, duracion_minutos, es_extra, minutos_extra, '
            . '(CASE WHEN ' . self::sinAusencia() . ' THEN 0 ELSE 1 END) AS con_ausencia FROM asignaciones '
            . 'WHERE usuario_id = ? AND fecha BETWEEN ? AND ? AND deleted_at IS NULL '
            . 'ORDER BY inicio ASC, id ASC FOR UPDATE',
            [$usuarioId, $lunes, $domingo]
        )->getResultArray();

        $acumulado = 0;
        foreach ($turnos as $turno) {
            $duracion = (int) $turno['duracion_minutos'];

            if ((int) $turno['con_ausencia'] === 1) {
                // Turno con ausencia activa (HU-18): no suma horas ni puede ser extra.
                $minutosExtra = 0;
            } else {
                $acumulado += $duracion;
                // Sin carga horaria definida no hay contra qué medir: no se marca nada como extra.
                $minutosExtra = $maximo > 0 ? max(0, min($duracion, $acumulado - $maximo)) : 0;
            }
            $esExtra = $minutosExtra > 0 ? 1 : 0;

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
            ->where(self::sinAusencia(), null, false)
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
     * No cuenta los turnos con ausencia activa (sus horas no son carga del enfermero/a).
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
            ->where('deleted_at', null)
            ->where(self::sinAusencia(), null, false);

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
     * Una sola consulta para todos (antes era una por enfermero). Los turnos con ausencia CUBIERTA no cuentan.
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
            ->where(self::sinAusencia('asignaciones', true), null, false)
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
                ? $this->db->query('SELECT usuario_id, sector_id, tipo_turno_id, fecha FROM asignaciones WHERE id = ? FOR UPDATE', [$excluirId])->getRow()
                : null;

            // Un turno con ausencia activa (HU-18) se gestiona desde Ausencias, no se reasigna desde acá.
            if ($excluirId !== null && ($bloqueo = $this->motivoBloqueoPorAusencia($excluirId)) !== null) {
                $this->db->transRollback();

                return ['general' => $bloqueo];
            }

            // El turno de un reemplazante no puede pasar al mismo enfermero/a que avisó la ausencia.
            if ($excluirId !== null) {
                $cubierta = $this->db->query(
                    "SELECT usuario_id FROM ausencias WHERE reemplazo_asignacion_id = ? AND estado = 'CUBIERTA' LIMIT 1",
                    [$excluirId]
                )->getRow();
                if ($cubierta !== null && (int) $cubierta->usuario_id === (int) $enfermero->id) {
                    $this->db->transRollback();

                    return ['usuario_id' => 'Este turno cubre una ausencia de ' . $enfermero->getNombreCompleto() . ': elija a otro enfermero/a como reemplazo.'];
                }
            }

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

            // Si el turno era el reemplazo de una ausencia (HU-16) y se movió a otro sector, franja o día,
            // ya no cubre esa ausencia: vuelve a PENDIENTE para que el administrador busque otro reemplazo.
            if ($anterior !== null
                && ((int) $anterior->sector_id !== $sectorId || (int) $anterior->tipo_turno_id !== (int) $tipo->id || (string) $anterior->fecha !== $fecha)) {
                $this->liberarAusenciaCubiertaPor($id);
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
     *
     * HU-16 / HU-18:
     *  - Si el turno tiene una ausencia activa (PENDIENTE o CUBIERTA) NO se elimina: devuelve false y
     *    ultimoError() explica que se gestiona desde Ausencias.
     *  - Si el turno es el de un reemplazante, la ausencia que cubría vuelve a PENDIENTE
     *    (reemplazo_asignacion_id = NULL).
     */
    public function eliminarConRecalculo(Asignacion $asignacion): bool
    {
        $this->ultimoError = null;
        $this->db->transBegin();

        try {
            $this->db->query('SELECT id FROM usuarios WHERE id = ? FOR UPDATE', [(int) $asignacion->usuario_id]);
            $this->db->query('SELECT id FROM asignaciones WHERE id = ? FOR UPDATE', [(int) $asignacion->id]);

            // Un turno con ausencia activa (HU-18) no se quita: se resuelve desde Ausencias.
            if (($bloqueo = $this->motivoBloqueoPorAusencia((int) $asignacion->id)) !== null) {
                $this->ultimoError = $bloqueo;
                $this->db->transRollback();

                return false;
            }

            if (! $this->delete((int) $asignacion->id)) {
                $this->db->transRollback();

                return false;
            }

            // Si era el turno de un reemplazante (HU-16), la ausencia queda otra vez sin cubrir.
            $this->liberarAusenciaCubiertaPor((int) $asignacion->id);

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
     * Incluye los turnos con ausencia (para mostrarlos con su estado); para contar la dotación usar
     * Asignacion::cuentaEnDotacion().
     *
     * @return array<string, array<int, list<Asignacion>>> [fecha][tipo_turno_id] => asignaciones
     */
    public function mesDeSector(int $sectorId, string $anioMes): array
    {
        $desde = $anioMes . '-01';
        $hasta = (new DateTimeImmutable($desde))->modify('last day of this month')->format('Y-m-d');

        $filas = $this->select('asignaciones.*, usuarios.nombre, usuarios.apellido, usuarios.matricula, usuarios.especialidad, usuarios.estado AS usuario_estado')
            ->select(self::SQL_DATOS_AUSENCIA, false)
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
            ->select(self::SQL_DATOS_AUSENCIA, false)
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
     * Turnos de un enfermero/a entre dos fechas (inclusive), con los datos de ausencia (ausencia_id,
     * ausencia_estado, reemplazo_de_ausencia_id) y de marcación (checkin_at, checkout_at, observaciones_pase).
     *
     * @return list<Asignacion>
     */
    public function delEnfermero(int $usuarioId, string $desde, string $hasta): array
    {
        return $this->select('asignaciones.*, sectores.nombre AS sector_nombre, sectores.es_critico AS sector_critico, '
                . 'tipos_turno.nombre AS tipo_nombre, tipos_turno.color AS tipo_color')
            ->select(self::SQL_DATOS_AUSENCIA, false)
            ->join('sectores', 'sectores.id = asignaciones.sector_id')
            ->join('tipos_turno', 'tipos_turno.id = asignaciones.tipo_turno_id')
            ->where('asignaciones.usuario_id', $usuarioId)
            ->where('asignaciones.fecha >=', $desde)
            ->where('asignaciones.fecha <=', $hasta)
            ->orderBy('asignaciones.inicio', 'ASC')
            ->findAll();
    }

    /** Turno en curso o próximo del enfermero/a (no incluye los que tienen ausencia CUBIERTA: no va). */
    public function proximoDelEnfermero(int $usuarioId, string $ahora): ?Asignacion
    {
        return $this->select('asignaciones.*, sectores.nombre AS sector_nombre, sectores.es_critico AS sector_critico, '
                . 'tipos_turno.nombre AS tipo_nombre, tipos_turno.color AS tipo_color')
            ->select(self::SQL_DATOS_AUSENCIA, false)
            ->join('sectores', 'sectores.id = asignaciones.sector_id')
            ->join('tipos_turno', 'tipos_turno.id = asignaciones.tipo_turno_id')
            ->where('asignaciones.usuario_id', $usuarioId)
            ->where('asignaciones.fin >', $ahora)
            ->where(self::sinAusencia('asignaciones', true), null, false)
            ->orderBy('asignaciones.inicio', 'ASC')
            ->first();
    }

    /** Próximo turno que todavía no empezó (no incluye los que tienen ausencia CUBIERTA). */
    public function siguienteProgramado(int $usuarioId, string $ahora): ?Asignacion
    {
        return $this->select('asignaciones.*, sectores.nombre AS sector_nombre, sectores.es_critico AS sector_critico, '
                . 'tipos_turno.nombre AS tipo_nombre, tipos_turno.color AS tipo_color')
            ->select(self::SQL_DATOS_AUSENCIA, false)
            ->join('sectores', 'sectores.id = asignaciones.sector_id')
            ->join('tipos_turno', 'tipos_turno.id = asignaciones.tipo_turno_id')
            ->where('asignaciones.usuario_id', $usuarioId)
            ->where('asignaciones.inicio >', $ahora)
            ->where(self::sinAusencia('asignaciones', true), null, false)
            ->orderBy('asignaciones.inicio', 'ASC')
            ->first();
    }

    /**
     * Mensaje del último rechazo de eliminarConRecalculo() (por ejemplo, turno con ausencia activa), o null
     * si falló por otro motivo o no falló.
     */
    public function ultimoError(): ?string
    {
        return $this->ultimoError;
    }

    /**
     * Si el turno tiene una ausencia activa (PENDIENTE o CUBIERTA), el mensaje que explica por qué no se
     * puede reasignar ni quitar desde el planificador; si no tiene, null.
     */
    public function motivoBloqueoPorAusencia(int $asignacionId): ?string
    {
        $fila = $this->db->query(
            "SELECT estado FROM ausencias WHERE asignacion_id = ? AND estado IN ('PENDIENTE','CUBIERTA') ORDER BY id DESC LIMIT 1",
            [$asignacionId]
        )->getRow();

        if ($fila === null) {
            return null;
        }

        return $fila->estado === 'CUBIERTA'
            ? 'Este turno tiene una ausencia informada y ya fue cubierto por un reemplazo: no se puede reasignar ni quitar. Para cambiar la cobertura, quite el turno del reemplazante o gestiónelo desde Ausencias.'
            : 'Este turno tiene una ausencia informada en gestión: no se puede reasignar ni quitar desde acá. Resuélvala desde Ausencias (asignar reemplazo o rechazar).';
    }

    /**
     * Si $reemplazoAsignacionId era el turno de un reemplazante, la ausencia que cubría vuelve a PENDIENTE.
     * Se llama dentro de la transacción que quitó o movió ese turno.
     */
    private function liberarAusenciaCubiertaPor(int $reemplazoAsignacionId): void
    {
        $this->db->table('ausencias')
            ->where('reemplazo_asignacion_id', $reemplazoAsignacionId)
            ->where('estado', 'CUBIERTA')
            ->update([
                'estado'                  => 'PENDIENTE',
                'reemplazo_asignacion_id' => null,
                'resuelto_por'            => null,
                'resuelto_at'             => null,
                'updated_at'              => date('Y-m-d H:i:s'),
            ]);
    }

    // ------------------------------------------------------------------
    //  HU-15: tablero de cobertura
    // ------------------------------------------------------------------

    /**
     * Cobertura de la semana (lunes a domingo) que contiene $lunes, por sector activo × día × tipo de turno.
     *
     * @return array{
     *     desde: string, hasta: string, dias: list<string>,
     *     sectores: list<array{
     *         sector: \App\Entities\Sector,
     *         tipos: list<TipoTurno>,
     *         celdas: array<string, array<int, array{
     *             asignados: int, minimo: int, alerta: bool, aplica: bool, terminado: bool,
     *             ausencia_pendiente_id: int|null, ausencias_pendientes: int
     *         }>>,
     *         alertas: int
     *     }>,
     *     total_alertas: int,
     *     ausencias_pendientes: int
     * }
     */
    public function coberturaSemana(string $lunes): array
    {
        $lunes = self::lunesDe($lunes);

        return $this->coberturaRango($lunes, (new DateTimeImmutable($lunes))->modify('+6 days')->format('Y-m-d'));
    }

    /**
     * Cobertura de un solo día (misma forma que coberturaSemana(), con un único elemento en 'dias').
     *
     * @return array<string, mixed>
     */
    public function coberturaDia(string $fecha): array
    {
        return $this->coberturaRango($fecha, $fecha);
    }

    /**
     * Arma la cobertura con 4 consultas fijas (sectores, tipos, conteos y ausencias pendientes), sin importar
     * cuántas celdas haya.
     *
     * - asignados: turnos que cuentan para la dotación (enfermero/a activo, sin ausencia PENDIENTE/CUBIERTA).
     * - minimo:    dotación mínima del sector.
     * - alerta:    asignados < minimo, solo en celdas que aplican (el tipo de turno ya existía ese día).
     * - aplica:    false si el tipo de turno se creó después de ese día y no tiene turnos (no se evalúa).
     * - terminado: el turno de ese día ya terminó (según el horario del tipo de turno).
     * - ausencia_pendiente_id: una ausencia PENDIENTE de esa franja (la más próxima a resolver), o null.
     *
     * @return array<string, mixed>
     */
    private function coberturaRango(string $desde, string $hasta): array
    {
        $dias   = [];
        $cursor = new DateTimeImmutable($desde);
        $fin    = new DateTimeImmutable($hasta);
        while ($cursor <= $fin) {
            $dias[] = $cursor->format('Y-m-d');
            $cursor = $cursor->modify('+1 day');
        }

        $sectores = (new SectorModel())->activos();
        $tiposPorSector = [];
        foreach ((new TipoTurnoModel())->activos() as $tipo) {
            $tiposPorSector[(int) $tipo->sector_id][] = $tipo;
        }

        // Conteo de turnos efectivos por sector / fecha / tipo.
        $conteos = [];
        $filas   = $this->db->query(
            'SELECT a.sector_id, a.fecha, a.tipo_turno_id, COUNT(*) AS cantidad FROM asignaciones a '
            . "JOIN usuarios u ON u.id = a.usuario_id AND u.estado = 'ACTIVO' "
            . 'WHERE a.deleted_at IS NULL AND a.fecha BETWEEN ? AND ? AND ' . self::sinAusencia('a') . ' '
            . 'GROUP BY a.sector_id, a.fecha, a.tipo_turno_id',
            [$desde, $hasta]
        )->getResultArray();
        foreach ($filas as $f) {
            $conteos[(int) $f['sector_id']][(string) $f['fecha']][(int) $f['tipo_turno_id']] = (int) $f['cantidad'];
        }

        // Ausencias pendientes por franja.
        $pendientes      = [];
        $totalPendientes = 0;
        $filas           = $this->db->query(
            'SELECT a.sector_id, a.fecha, a.tipo_turno_id, MIN(au.id) AS ausencia_id, COUNT(*) AS cantidad FROM ausencias au '
            . 'JOIN asignaciones a ON a.id = au.asignacion_id AND a.deleted_at IS NULL '
            . "WHERE au.estado = 'PENDIENTE' AND a.fecha BETWEEN ? AND ? "
            . 'GROUP BY a.sector_id, a.fecha, a.tipo_turno_id',
            [$desde, $hasta]
        )->getResultArray();
        foreach ($filas as $f) {
            $pendientes[(int) $f['sector_id']][(string) $f['fecha']][(int) $f['tipo_turno_id']] = [(int) $f['ausencia_id'], (int) $f['cantidad']];
            $totalPendientes += (int) $f['cantidad'];
        }

        $ahora        = date('Y-m-d H:i:s');
        $resultado    = [];
        $totalAlertas = 0;

        foreach ($sectores as $sector) {
            $sid    = (int) $sector->id;
            $tipos  = $tiposPorSector[$sid] ?? [];
            $minimo = (int) $sector->dotacion_minima;
            $celdas = [];
            $alertasSector = 0;

            foreach ($dias as $dia) {
                foreach ($tipos as $tipo) {
                    $tid       = (int) $tipo->id;
                    $asignados = $conteos[$sid][$dia][$tid] ?? 0;
                    [$ausId, $ausCant] = $pendientes[$sid][$dia][$tid] ?? [null, 0];
                    $creado    = $tipo->created_at ? substr((string) $tipo->created_at, 0, 10) : null;
                    $aplica    = $asignados > 0 || $ausCant > 0 || $creado === null || $dia >= $creado;
                    $alerta    = $aplica && $asignados < $minimo;

                    if ($alerta) {
                        $alertasSector++;
                    }

                    $celdas[$dia][$tid] = [
                        'asignados'             => $asignados,
                        'minimo'                => $minimo,
                        'alerta'                => $alerta,
                        'aplica'                => $aplica,
                        'terminado'             => self::calcularHorario($dia, $tipo)['fin'] <= $ahora,
                        'ausencia_pendiente_id' => $ausId,
                        'ausencias_pendientes'  => $ausCant,
                    ];
                }
            }

            $totalAlertas += $alertasSector;
            $resultado[]   = [
                'sector'  => $sector,
                'tipos'   => $tipos,
                'celdas'  => $celdas,
                'alertas' => $alertasSector,
            ];
        }

        return [
            'desde'                => $desde,
            'hasta'                => $hasta,
            'dias'                 => $dias,
            'sectores'             => $resultado,
            'total_alertas'        => $totalAlertas,
            'ausencias_pendientes' => $totalPendientes,
        ];
    }

    // ------------------------------------------------------------------
    //  HU-19: historial del enfermero/a
    // ------------------------------------------------------------------

    /**
     * Turnos del enfermero/a entre $desde y $hasta (inclusive, por fecha de inicio), en orden cronológico,
     * con su estado calculado. Tres consultas fijas (turnos, ausencias, reemplazos).
     *
     * Estado (código en 'estado', etiqueta e ícono en ESTADOS_HISTORIAL):
     *  - ausente_cubierto:    tiene ausencia CUBIERTA.
     *  - ausencia_en_gestion: tiene ausencia PENDIENTE.
     *  - ausencia_rechazada:  su última ausencia fue RECHAZADA (y no tiene otra activa): debía presentarse.
     *  - si no: cumplido (ya terminó) / en_curso / programado.
     * 'cuenta_horas' indica si el turno suma a las horas cumplidas (terminado y sin ausencia activa).
     *
     * @return list<array{
     *     id: int, fecha: string, inicio: string, fin: string, hora_inicio: string, hora_fin: string,
     *     termina_dia_siguiente: bool, sector_id: int, sector_nombre: string, sector_critico: bool,
     *     tipo_turno_id: int, tipo_nombre: string, tipo_color: string,
     *     duracion_minutos: int, horas: float, horas_texto: string,
     *     tipo: string, es_reemplazo: bool,
     *     estado: string, estado_texto: string, estado_icono: string, cuenta_horas: bool,
     *     es_extra: bool, minutos_extra: int,
     *     checkin_at: string|null, checkout_at: string|null, hora_ingreso: string, hora_salida: string,
     *     observaciones_pase: string|null,
     *     ausencia_id: int|null, ausencia_estado: string|null, ausencia_motivo: string|null, observacion_admin: string|null
     * }>
     */
    public function historialDeUsuario(int $usuarioId, string $desde, string $hasta, ?string $ahora = null): array
    {
        $ahora = $ahora ?? date('Y-m-d H:i:s');

        $turnos = $this->db->query(
            'SELECT a.id, a.fecha, a.inicio, a.fin, a.duracion_minutos, a.es_extra, a.minutos_extra, '
            . 'a.checkin_at, a.checkout_at, a.observaciones_pase, a.sector_id, a.tipo_turno_id, '
            . 's.nombre AS sector_nombre, s.es_critico AS sector_critico, t.nombre AS tipo_nombre, t.color AS tipo_color '
            . 'FROM asignaciones a '
            . 'LEFT JOIN sectores s ON s.id = a.sector_id '
            . 'LEFT JOIN tipos_turno t ON t.id = a.tipo_turno_id '
            . 'WHERE a.usuario_id = ? AND a.deleted_at IS NULL AND a.fecha BETWEEN ? AND ? '
            . 'ORDER BY a.inicio ASC, a.id ASC',
            [$usuarioId, $desde, $hasta]
        )->getResultArray();

        if ($turnos === []) {
            return [];
        }

        $ids = array_map(static fn (array $t): int => (int) $t['id'], $turnos);
        $marcadores = implode(',', array_fill(0, count($ids), '?'));

        // Ausencias de esos turnos: se queda con la activa; si no hay activa, con la última rechazada.
        $ausencias = [];
        $filas     = $this->db->query(
            "SELECT id, asignacion_id, estado, motivo, observacion_admin FROM ausencias WHERE asignacion_id IN ({$marcadores}) ORDER BY id ASC",
            $ids
        )->getResultArray();
        foreach ($filas as $f) {
            $aid    = (int) $f['asignacion_id'];
            $actual = $ausencias[$aid] ?? null;
            $activa = in_array($f['estado'], ['PENDIENTE', 'CUBIERTA'], true);
            if ($actual === null || $activa || ! in_array($actual['estado'], ['PENDIENTE', 'CUBIERTA'], true)) {
                $ausencias[$aid] = $f;
            }
        }

        // Turnos que son reemplazo de una ausencia cubierta.
        $reemplazos = [];
        $filas      = $this->db->query(
            "SELECT reemplazo_asignacion_id FROM ausencias WHERE estado = 'CUBIERTA' AND reemplazo_asignacion_id IN ({$marcadores})",
            $ids
        )->getResultArray();
        foreach ($filas as $f) {
            $reemplazos[(int) $f['reemplazo_asignacion_id']] = true;
        }

        $resultado = [];
        foreach ($turnos as $t) {
            $id        = (int) $t['id'];
            $ausencia  = $ausencias[$id] ?? null;
            $ausEstado = $ausencia['estado'] ?? null;
            $activa    = in_array($ausEstado, ['PENDIENTE', 'CUBIERTA'], true);
            $terminado = (string) $t['fin'] <= $ahora;

            if ($ausEstado === 'CUBIERTA') {
                $estado = self::HISTORIAL_AUSENTE_CUBIERTO;
            } elseif ($ausEstado === 'PENDIENTE') {
                $estado = self::HISTORIAL_AUSENCIA_GESTION;
            } elseif ($ausEstado === 'RECHAZADA') {
                $estado = self::HISTORIAL_AUSENCIA_RECHAZADA;
            } elseif ($terminado) {
                $estado = self::HISTORIAL_CUMPLIDO;
            } elseif ((string) $t['inicio'] <= $ahora) {
                $estado = self::HISTORIAL_EN_CURSO;
            } else {
                $estado = self::HISTORIAL_PROGRAMADO;
            }

            $color = (string) ($t['tipo_color'] ?? '');
            $esReemplazo = isset($reemplazos[$id]);

            $resultado[] = [
                'id'                    => $id,
                'fecha'                 => (string) $t['fecha'],
                'inicio'                => (string) $t['inicio'],
                'fin'                   => (string) $t['fin'],
                'hora_inicio'           => substr((string) $t['inicio'], 11, 5),
                'hora_fin'              => substr((string) $t['fin'], 11, 5),
                'termina_dia_siguiente' => substr((string) $t['fin'], 0, 10) > (string) $t['fecha'],
                'sector_id'             => (int) $t['sector_id'],
                'sector_nombre'         => (string) ($t['sector_nombre'] ?? 'Sector'),
                'sector_critico'        => (int) ($t['sector_critico'] ?? 0) === 1,
                'tipo_turno_id'         => (int) $t['tipo_turno_id'],
                'tipo_nombre'           => (string) ($t['tipo_nombre'] ?? 'Turno'),
                'tipo_color'            => preg_match('/^#[0-9a-fA-F]{6}$/', $color) === 1 ? strtolower($color) : TipoTurno::COLOR_DEFECTO,
                'duracion_minutos'      => (int) $t['duracion_minutos'],
                'horas'                 => round((int) $t['duracion_minutos'] / 60, 1),
                'horas_texto'           => self::horasTexto((int) $t['duracion_minutos']),
                'tipo'                  => $esReemplazo ? 'Reemplazo' : 'Normal',
                'es_reemplazo'          => $esReemplazo,
                'estado'                => $estado,
                'estado_texto'          => self::ESTADOS_HISTORIAL[$estado][0],
                'estado_icono'          => self::ESTADOS_HISTORIAL[$estado][1],
                'cuenta_horas'          => $terminado && ! $activa,
                'es_extra'              => (int) $t['es_extra'] === 1,
                'minutos_extra'         => (int) $t['minutos_extra'],
                'checkin_at'            => $t['checkin_at'] !== null ? (string) $t['checkin_at'] : null,
                'checkout_at'           => $t['checkout_at'] !== null ? (string) $t['checkout_at'] : null,
                'hora_ingreso'          => $t['checkin_at'] !== null ? substr((string) $t['checkin_at'], 11, 5) : '',
                'hora_salida'           => $t['checkout_at'] !== null ? substr((string) $t['checkout_at'], 11, 5) : '',
                'observaciones_pase'    => $t['observaciones_pase'] !== null ? (string) $t['observaciones_pase'] : null,
                'ausencia_id'           => $ausencia !== null ? (int) $ausencia['id'] : null,
                'ausencia_estado'       => $ausEstado,
                'ausencia_motivo'       => $ausencia['motivo'] ?? null,
                'observacion_admin'     => $ausencia['observacion_admin'] ?? null,
            ];
        }

        return $resultado;
    }

    /**
     * KPIs del historial (HU-19) para el período [$desde, $hasta].
     *
     * - horas_cumplidas: turnos ya terminados y sin ausencia activa (1 decimal).
     * - carga_contratada: carga_horaria_max semanal × días del período / 7 (1 decimal).
     * - guardias_*: turnos cumplidos (mismo criterio que las horas), separando normales y reemplazos.
     * - ausencias_registradas: ausencias que el enfermero/a informó en turnos del período (cualquier estado).
     *
     * $filas: el resultado de historialDeUsuario() para el mismo período (para no repetir la consulta).
     *
     * @param list<array<string, mixed>>|null $filas
     *
     * @return array{
     *     minutos_cumplidos: int, horas_cumplidas: float, horas_cumplidas_texto: string,
     *     carga_semanal: int, dias_periodo: int, carga_contratada: float, porcentaje: int,
     *     guardias_total: int, guardias_normales: int, guardias_reemplazo: int,
     *     ausencias_registradas: int, turnos_total: int
     * }
     */
    public function resumenHistorial(int $usuarioId, string $desde, string $hasta, ?array $filas = null, ?string $ahora = null): array
    {
        $filas = $filas ?? $this->historialDeUsuario($usuarioId, $desde, $hasta, $ahora);

        $minutos   = 0;
        $normales  = 0;
        $reemplazo = 0;
        foreach ($filas as $f) {
            if (! $f['cuenta_horas']) {
                continue;
            }
            $minutos += (int) $f['duracion_minutos'];
            if ($f['es_reemplazo']) {
                $reemplazo++;
            } else {
                $normales++;
            }
        }

        $carga    = (int) ($this->db->query('SELECT carga_horaria_max FROM usuarios WHERE id = ?', [$usuarioId])->getRow()->carga_horaria_max ?? 0);
        $dias     = (int) (new DateTimeImmutable($desde))->diff(new DateTimeImmutable($hasta))->days + 1;
        $contrato = round($carga * $dias / 7, 1);
        $horas    = round($minutos / 60, 1);

        $ausencias = (int) ($this->db->query(
            'SELECT COUNT(*) AS total FROM ausencias au JOIN asignaciones a ON a.id = au.asignacion_id '
            . 'WHERE au.usuario_id = ? AND a.fecha BETWEEN ? AND ?',
            [$usuarioId, $desde, $hasta]
        )->getRow()->total ?? 0);

        return [
            'minutos_cumplidos'     => $minutos,
            'horas_cumplidas'       => $horas,
            'horas_cumplidas_texto' => self::horasTexto($minutos),
            'carga_semanal'         => $carga,
            'dias_periodo'          => $dias,
            'carga_contratada'      => $contrato,
            'porcentaje'            => $contrato > 0 ? (int) round($horas * 100 / $contrato) : 0,
            'guardias_total'        => $normales + $reemplazo,
            'guardias_normales'     => $normales,
            'guardias_reemplazo'    => $reemplazo,
            'ausencias_registradas' => $ausencias,
            'turnos_total'          => count($filas),
        ];
    }

    // ------------------------------------------------------------------
    //  HU-20: marcación de ingreso y fin de guardia
    // ------------------------------------------------------------------

    /**
     * Marca el ingreso del enfermero/a a su turno.
     * Ventana: desde 30 min antes del inicio hasta el fin. Sin ausencia activa y sin ingreso previo.
     *
     * @return array{ok: bool, error: string|null, no_encontrada: bool, hora: string|null}
     *         no_encontrada = true si el turno no existe o no es del usuario (el controlador responde 404).
     */
    public function marcarIngreso(int $usuarioId, int $asignacionId, ?string $ahora = null): array
    {
        $ahora = $ahora ?? date('Y-m-d H:i:s');
        $turno = $this->turnoPropioParaMarcar($usuarioId, $asignacionId);

        if ($turno === null) {
            return ['ok' => false, 'error' => 'No se encontró el turno.', 'no_encontrada' => true, 'hora' => null];
        }

        $error = null;
        if ($turno->tieneAusenciaActiva()) {
            $error = 'Este turno tiene una ausencia informada: no se puede marcar el ingreso.';
        } elseif ($turno->tieneIngreso()) {
            $error = 'El ingreso a este turno ya fue marcado a las ' . $turno->horaIngreso() . '.';
        } elseif ($ahora < $turno->aperturaIngreso()) {
            $error = 'Todavía no puede marcar el ingreso: se habilita ' . Asignacion::MINUTOS_ANTES_INGRESO
                . ' minutos antes del inicio (a las ' . substr($turno->aperturaIngreso(), 11, 5) . ').';
        } elseif ($ahora >= (string) $turno->fin) {
            $error = 'El turno ya terminó: no se puede marcar el ingreso.';
        }

        if ($error !== null) {
            return ['ok' => false, 'error' => $error, 'no_encontrada' => false, 'hora' => null];
        }

        // La condición "checkin_at IS NULL" evita una doble marca si llegan dos pedidos a la vez.
        $this->db->table($this->table)
            ->where('id', $asignacionId)
            ->where('checkin_at', null)
            ->update(['checkin_at' => $ahora]);

        if ($this->db->affectedRows() !== 1) {
            return ['ok' => false, 'error' => 'El ingreso a este turno ya fue marcado.', 'no_encontrada' => false, 'hora' => null];
        }

        return ['ok' => true, 'error' => null, 'no_encontrada' => false, 'hora' => substr($ahora, 11, 5)];
    }

    /**
     * Finaliza la guardia y guarda las observaciones del pase (opcional, máx. 1000 caracteres).
     * Requiere ingreso marcado; ventana hasta 4 h después del fin. Sin ausencia activa ni salida previa.
     *
     * @return array{ok: bool, error: string|null, no_encontrada: bool, hora: string|null}
     */
    public function marcarSalida(int $usuarioId, int $asignacionId, ?string $observaciones, ?string $ahora = null): array
    {
        $ahora = $ahora ?? date('Y-m-d H:i:s');
        $turno = $this->turnoPropioParaMarcar($usuarioId, $asignacionId);

        if ($turno === null) {
            return ['ok' => false, 'error' => 'No se encontró el turno.', 'no_encontrada' => true, 'hora' => null];
        }

        $observaciones = trim((string) $observaciones);

        $error = null;
        if ($turno->tieneAusenciaActiva()) {
            $error = 'Este turno tiene una ausencia informada: no se puede finalizar la guardia.';
        } elseif (! $turno->tieneIngreso()) {
            $error = 'Primero tiene que marcar el ingreso al turno.';
        } elseif ($turno->tieneSalida()) {
            $error = 'La guardia ya fue finalizada a las ' . $turno->horaSalida() . '.';
        } elseif ($ahora > $turno->cierreSalida()) {
            $error = 'Pasaron más de ' . Asignacion::HORAS_DESPUES_SALIDA . ' horas desde el fin del turno: ya no se puede finalizar la guardia.';
        } elseif (mb_strlen($observaciones) > Asignacion::MAX_OBSERVACIONES_PASE) {
            $error = 'Las observaciones del pase de guardia no pueden superar los ' . Asignacion::MAX_OBSERVACIONES_PASE . ' caracteres.';
        }

        if ($error !== null) {
            return ['ok' => false, 'error' => $error, 'no_encontrada' => false, 'hora' => null];
        }

        $this->db->table($this->table)
            ->where('id', $asignacionId)
            ->where('checkout_at', null)
            ->where('checkin_at IS NOT NULL', null, false)
            ->update([
                'checkout_at'        => $ahora,
                'observaciones_pase' => $observaciones !== '' ? $observaciones : null,
            ]);

        if ($this->db->affectedRows() !== 1) {
            return ['ok' => false, 'error' => 'La guardia ya fue finalizada.', 'no_encontrada' => false, 'hora' => null];
        }

        return ['ok' => true, 'error' => null, 'no_encontrada' => false, 'hora' => substr($ahora, 11, 5)];
    }

    /** Turno no eliminado del usuario, con los datos de ausencia (para validar la marcación). */
    private function turnoPropioParaMarcar(int $usuarioId, int $asignacionId): ?Asignacion
    {
        return $this->select('asignaciones.*')
            ->select(self::SQL_DATOS_AUSENCIA, false)
            ->where('asignaciones.id', $asignacionId)
            ->where('asignaciones.usuario_id', $usuarioId)
            ->first();
    }

    public static function horasTexto(int $minutos): string
    {
        $horas = intdiv($minutos, 60);
        $resto = $minutos % 60;

        return $horas . ' h' . ($resto > 0 ? ' ' . $resto . ' min' : '');
    }
}