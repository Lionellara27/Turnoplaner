<?php

declare(strict_types=1);

namespace App\Models;

use App\Entities\Asignacion;
use App\Entities\Ausencia;
use App\Entities\TipoTurno;
use App\Entities\Usuario;
use CodeIgniter\Model;

/**
 * Ausencias informadas por los enfermeros/as (HU-18) y su gestión por el administrador:
 * reemplazo (HU-16) o rechazo.
 *
 * Nunca se borran: los cambios son de estado (PENDIENTE -> CUBIERTA / RECHAZADA, y CUBIERTA -> PENDIENTE
 * si se quita el turno del reemplazante). Cada cambio recalcula las horas extras de la semana afectada.
 */
class AusenciaModel extends Model
{
    public const PENDIENTE = 'PENDIENTE';
    public const CUBIERTA  = 'CUBIERTA';
    public const RECHAZADA = 'RECHAZADA';

    /** Estados que sacan al turno de la dotación y de las horas semanales. */
    public const ESTADOS_ACTIVOS = [self::PENDIENTE, self::CUBIERTA];

    /** Valor del filtro del listado del administrador que muestra todos los estados. */
    public const FILTRO_TODAS = 'TODAS';

    /** Etiquetas del estado para el administrador. */
    public const ESTADOS = [
        self::PENDIENTE => 'Pendiente',
        self::CUBIERTA  => 'Cubierta',
        self::RECHAZADA => 'Rechazada',
    ];

    /** Opciones del filtro del listado del administrador (valor => etiqueta). */
    public const FILTROS = [
        self::PENDIENTE    => 'Pendientes',
        self::CUBIERTA     => 'Cubiertas',
        self::RECHAZADA    => 'Rechazadas',
        self::FILTRO_TODAS => 'Todas',
    ];

    /** Estado tal como lo ve el enfermero/a. */
    public const ESTADOS_ENFERMERO = [
        self::PENDIENTE => 'Aviso enviado, en gestión',
        self::CUBIERTA  => 'Reemplazo asignado',
        self::RECHAZADA => 'Rechazada: debe presentarse al turno',
    ];

    /** Íconos Bootstrap por estado (siempre con texto al lado). */
    public const ICONOS_ESTADO = [
        self::PENDIENTE => 'bi-hourglass-split',
        self::CUBIERTA  => 'bi-check-circle-fill',
        self::RECHAZADA => 'bi-x-circle-fill',
    ];

    /** Motivos (valor del ENUM => etiqueta). */
    public const MOTIVOS = [
        'ENFERMEDAD'      => 'Enfermedad',
        'FUERZA_MAYOR'    => 'Fuerza mayor',
        'LICENCIA_MEDICA' => 'Licencia médica',
        'OTRO'            => 'Otro',
    ];

    public const DESCRIPCION_MIN = 10;
    public const DESCRIPCION_MAX = 500;
    public const OBSERVACION_MIN = 5;
    public const OBSERVACION_MAX = 255;

    protected $table          = 'ausencias';
    protected $primaryKey     = 'id';
    protected $returnType     = Ausencia::class;
    protected $useSoftDeletes = false; // no hay borrado: solo cambios de estado
    protected $protectFields  = true;
    protected $allowedFields  = [
        'asignacion_id', 'usuario_id', 'motivo', 'descripcion', 'estado',
        'reemplazo_asignacion_id', 'resuelto_por', 'resuelto_at', 'observacion_admin',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'asignacion_id'     => 'required|is_natural_no_zero',
        'usuario_id'        => 'required|is_natural_no_zero',
        'motivo'            => 'required|in_list[ENFERMEDAD,FUERZA_MAYOR,LICENCIA_MEDICA,OTRO]',
        'descripcion'       => 'required|min_length[10]|max_length[500]',
        'estado'            => 'permit_empty|in_list[PENDIENTE,CUBIERTA,RECHAZADA]',
        'observacion_admin' => 'permit_empty|max_length[255]',
    ];

    protected $validationMessages = [
        'motivo' => [
            'required' => 'Elija el motivo de la ausencia.',
            'in_list'  => 'Elija un motivo válido.',
        ],
        'descripcion' => [
            'required'   => 'Describa brevemente el motivo de la ausencia.',
            'min_length' => 'La descripción debe tener al menos 10 caracteres.',
            'max_length' => 'La descripción no puede superar los 500 caracteres.',
        ],
    ];

    /** Reglas del formulario del enfermero/a (HU-18), para usar también en el controlador. */
    public const REGLAS_REPORTE = [
        'motivo' => [
            'rules'  => 'required|in_list[ENFERMEDAD,FUERZA_MAYOR,LICENCIA_MEDICA,OTRO]',
            'errors' => ['required' => 'Elija el motivo de la ausencia.', 'in_list' => 'Elija un motivo válido.'],
        ],
        'descripcion' => [
            'rules'  => 'required|min_length[10]|max_length[500]',
            'errors' => [
                'required'   => 'Describa brevemente el motivo de la ausencia.',
                'min_length' => 'La descripción debe tener al menos 10 caracteres.',
                'max_length' => 'La descripción no puede superar los 500 caracteres.',
            ],
        ],
    ];

    /** Reglas del rechazo del administrador. */
    public const REGLAS_RECHAZO = [
        'observacion' => [
            'rules'  => 'required|min_length[5]|max_length[255]',
            'errors' => [
                'required'   => 'Indique el motivo del rechazo.',
                'min_length' => 'El motivo del rechazo debe tener al menos 5 caracteres.',
                'max_length' => 'El motivo del rechazo no puede superar los 255 caracteres.',
            ],
        ],
    ];

    /**
     * Columnas del listado con detalle: turno, enfermero/a, sector, tipo de turno, reemplazante y
     * administrador que resolvió.
     */
    private const SELECT_DETALLE = 'ausencias.*, '
        . 'a.fecha, a.inicio, a.fin, a.duracion_minutos, a.sector_id, a.tipo_turno_id, a.deleted_at AS asignacion_eliminada, '
        . 'u.nombre AS enfermero_nombre, u.apellido AS enfermero_apellido, u.matricula AS enfermero_matricula, '
        . 's.nombre AS sector_nombre, s.es_critico AS sector_critico, '
        . 't.nombre AS tipo_nombre, t.color AS tipo_color, '
        . 'ra.usuario_id AS reemplazante_id, ru.nombre AS reemplazante_nombre, ru.apellido AS reemplazante_apellido, '
        . 'adm.nombre AS resuelto_por_nombre, adm.apellido AS resuelto_por_apellido';

    // ------------------------------------------------------------------
    //  HU-18: el enfermero/a informa la ausencia
    // ------------------------------------------------------------------

    /**
     * Registra el aviso de ausencia (queda PENDIENTE) y recalcula las horas de la semana del enfermero/a.
     * Reglas: el turno es suyo, no terminó ni empezó (inicio > ahora) y no tiene otra ausencia activa.
     *
     * @return array{ok: bool, id: int|null, errores: array<string, string>, no_encontrada: bool}
     *         no_encontrada = true si el turno no existe o no es del usuario (el controlador responde 404).
     */
    public function reportar(int $usuarioId, int $asignacionId, string $motivo, string $descripcion): array
    {
        $descripcion = trim($descripcion);
        $errores     = $this->validarDatos(['motivo' => $motivo, 'descripcion' => $descripcion], self::REGLAS_REPORTE);
        if ($errores !== []) {
            return ['ok' => false, 'id' => null, 'errores' => $errores, 'no_encontrada' => false];
        }

        $this->db->transBegin();

        try {
            $turno = $this->db->query(
                'SELECT id, usuario_id, fecha, inicio FROM asignaciones WHERE id = ? AND usuario_id = ? AND deleted_at IS NULL FOR UPDATE',
                [$asignacionId, $usuarioId]
            )->getRow();

            if ($turno === null) {
                $this->db->transRollback();

                return ['ok' => false, 'id' => null, 'errores' => ['general' => 'No se encontró el turno.'], 'no_encontrada' => true];
            }

            $error = null;
            if ((string) $turno->inicio <= date('Y-m-d H:i:s')) {
                $error = 'Solo puede informar ausencias en turnos que todavía no empezaron.';
            } elseif ($this->activaDeAsignacion($asignacionId) !== null) {
                $error = 'Ya informó una ausencia para este turno.';
            }
            if ($error !== null) {
                $this->db->transRollback();

                return ['ok' => false, 'id' => null, 'errores' => ['general' => $error], 'no_encontrada' => false];
            }

            $ok = $this->insert([
                'asignacion_id' => $asignacionId,
                'usuario_id'    => $usuarioId,
                'motivo'        => $motivo,
                'descripcion'   => $descripcion,
                'estado'        => self::PENDIENTE,
            ]);
            if ($ok === false) {
                $errores = $this->errors();
                $this->db->transRollback();

                return ['ok' => false, 'id' => null, 'errores' => $errores, 'no_encontrada' => false];
            }
            $id = (int) $this->getInsertID();

            (new AsignacionModel())->recalcularExtrasSemana($usuarioId, (string) $turno->fecha);

            $this->db->transCommit();

            return ['ok' => true, 'id' => $id, 'errores' => [], 'no_encontrada' => false];
        } catch (\Throwable $e) {
            $this->db->transRollback();
            log_message('error', 'No se pudo registrar la ausencia: ' . $e->getMessage());

            return ['ok' => false, 'id' => null, 'errores' => ['general' => 'No se pudo registrar el aviso. Intente nuevamente.'], 'no_encontrada' => false];
        }
    }

    // ------------------------------------------------------------------
    //  Consultas
    // ------------------------------------------------------------------

    /**
     * Listado del administrador con todos los datos para mostrar. $estado: PENDIENTE (por defecto),
     * CUBIERTA, RECHAZADA o TODAS (cualquier otro valor se toma como PENDIENTE).
     * Orden: pendientes primero; dentro de cada estado, por inicio del turno (el más próximo primero).
     *
     * @return list<Ausencia>
     */
    public function listarParaAdmin(string $estado = self::PENDIENTE): array
    {
        $estado  = array_key_exists($estado, self::FILTROS) ? $estado : self::PENDIENTE;
        $builder = $this->conDetalle();

        if ($estado !== self::FILTRO_TODAS) {
            $builder->where('ausencias.estado', $estado);
        }

        return $builder->orderBy("FIELD(ausencias.estado, 'PENDIENTE', 'CUBIERTA', 'RECHAZADA')", '', false)
            ->orderBy('a.inicio', 'ASC')
            ->orderBy('ausencias.id', 'ASC')
            ->findAll();
    }

    /** Una ausencia con todo el detalle (o null si no existe). */
    public function buscarConDetalle(int $ausenciaId): ?Ausencia
    {
        return $this->conDetalle()->where('ausencias.id', $ausenciaId)->first();
    }

    /**
     * Ausencias PENDIENTES cuyo turno todavía no terminó (las que el administrador puede resolver).
     * Para el contador del menú y el resumen del tablero.
     */
    public function contarPendientes(): int
    {
        return (int) ($this->db->query(
            "SELECT COUNT(*) AS total FROM ausencias au JOIN asignaciones a ON a.id = au.asignacion_id AND a.deleted_at IS NULL "
            . "WHERE au.estado = 'PENDIENTE' AND a.fin > ?",
            [date('Y-m-d H:i:s')]
        )->getRow()->total ?? 0);
    }

    /**
     * Ausencias informadas por el enfermero/a (todas), la del turno más reciente primero, con detalle.
     *
     * @return list<Ausencia>
     */
    public function listarDeUsuario(int $usuarioId): array
    {
        return $this->conDetalle()
            ->where('ausencias.usuario_id', $usuarioId)
            ->orderBy('a.inicio', 'DESC')
            ->orderBy('ausencias.id', 'DESC')
            ->findAll();
    }

    /** Ausencia activa (PENDIENTE o CUBIERTA) del turno, o null. */
    public function activaDeAsignacion(int $asignacionId): ?Ausencia
    {
        return $this->where('asignacion_id', $asignacionId)
            ->whereIn('estado', self::ESTADOS_ACTIVOS)
            ->orderBy('id', 'DESC')
            ->first();
    }

    /**
     * Ausencias activas de varios turnos en una sola consulta (para pintar estados sin N+1).
     *
     * @param list<int> $asignacionIds
     *
     * @return array<int, Ausencia> [asignacion_id => Ausencia]
     */
    public function activasPorAsignaciones(array $asignacionIds): array
    {
        $asignacionIds = array_values(array_unique(array_map('intval', $asignacionIds)));
        if ($asignacionIds === []) {
            return [];
        }

        $mapa = [];
        foreach ($this->whereIn('asignacion_id', $asignacionIds)->whereIn('estado', self::ESTADOS_ACTIVOS)->orderBy('id', 'ASC')->findAll() as $a) {
            $mapa[(int) $a->asignacion_id] = $a;
        }

        return $mapa;
    }

    /**
     * Para cada turno, la ausencia que corresponde mostrarle al enfermero/a: la activa si hay; si no, la
     * última RECHAZADA (para avisarle que debe presentarse, con la observación del administrador).
     *
     * @param list<int> $asignacionIds
     *
     * @return array<int, Ausencia> [asignacion_id => Ausencia]
     */
    public function ultimaPorAsignaciones(array $asignacionIds): array
    {
        $asignacionIds = array_values(array_unique(array_map('intval', $asignacionIds)));
        if ($asignacionIds === []) {
            return [];
        }

        $mapa = [];
        foreach ($this->whereIn('asignacion_id', $asignacionIds)->orderBy('id', 'ASC')->findAll() as $a) {
            $aid    = (int) $a->asignacion_id;
            $actual = $mapa[$aid] ?? null;
            if ($actual === null || $a->esActiva() || ! $actual->esActiva()) {
                $mapa[$aid] = $a;
            }
        }

        return $mapa;
    }

    // ------------------------------------------------------------------
    //  HU-16: reemplazo
    // ------------------------------------------------------------------

    /**
     * Candidatos para cubrir la ausencia: enfermeros/as activos, distintos del ausente, sin turno
     * superpuesto y con margen en su carga semanal (estado DISPONIBLE de AsignacionModel::evaluarDisponibilidad,
     * que ya no cuenta los turnos con ausencia activa). Los demás no se ofrecen; se informa cuántos quedaron afuera.
     *
     * @return array{
     *     ausencia: Ausencia|null,
     *     candidatos: list<array{enfermero: Usuario, minutos_semana: int, maximo_minutos: int, estado: string, motivo: string|null}>,
     *     excluidos: int,
     *     error: string|null
     * }
     *  error != null si la ausencia no existe, no está PENDIENTE o el turno ya terminó (candidatos vacío).
     */
    public function candidatosReemplazo(int $ausenciaId): array
    {
        $ausencia = $this->buscarConDetalle($ausenciaId);
        if ($ausencia === null) {
            return ['ausencia' => null, 'candidatos' => [], 'excluidos' => 0, 'error' => 'No se encontró la ausencia.'];
        }

        $error = $this->motivoNoCubrible($ausencia);
        if ($error !== null) {
            return ['ausencia' => $ausencia, 'candidatos' => [], 'excluidos' => 0, 'error' => $error];
        }

        $evaluacion = $this->evaluarCandidatos($ausencia);
        $candidatos = array_values(array_filter($evaluacion, static fn (array $f): bool => $f['estado'] === AsignacionModel::DISPONIBLE));

        return [
            'ausencia'   => $ausencia,
            'candidatos' => $candidatos,
            'excluidos'  => count($evaluacion) - count($candidatos),
            'error'      => null,
        ];
    }

    /**
     * Confirma el reemplazo en una transacción: vuelve a validar que el elegido siga disponible, crea su
     * turno (mismo sector, tipo, fecha y horario; creado_por = administrador), marca la ausencia CUBIERTA y
     * recalcula las semanas del reemplazante y del ausente.
     *
     * @return array{ok: bool, asignacion_id: int|null, errores: array<string, string>, no_encontrada: bool}
     *         errores con clave 'reemplazante_id' (el elegido no sirve) o 'general'.
     */
    public function cubrirConReemplazo(int $ausenciaId, int $reemplazanteId, int $adminId): array
    {
        $fallo = static fn (string $campo, string $msg, bool $noEncontrada = false): array => [
            'ok' => false, 'asignacion_id' => null, 'errores' => [$campo => $msg], 'no_encontrada' => $noEncontrada,
        ];

        $this->db->transBegin();

        try {
            $bloqueada = $this->db->query('SELECT id FROM ausencias WHERE id = ? FOR UPDATE', [$ausenciaId])->getRow();
            $ausencia  = $bloqueada !== null ? $this->buscarConDetalle($ausenciaId) : null;
            if ($ausencia === null) {
                $this->db->transRollback();

                return $fallo('general', 'No se encontró la ausencia.', true);
            }

            if (($error = $this->motivoNoCubrible($ausencia)) !== null) {
                $this->db->transRollback();

                return $fallo('general', $error);
            }

            if ($reemplazanteId === (int) $ausencia->usuario_id) {
                $this->db->transRollback();

                return $fallo('reemplazante_id', 'El reemplazante tiene que ser otro enfermero/a.');
            }

            // Bloqueo de los dos enfermeros en orden de id (igual que AsignacionModel::guardarConControles).
            $ids = [$reemplazanteId, (int) $ausencia->usuario_id];
            sort($ids);
            foreach ($ids as $uid) {
                $this->db->query('SELECT id FROM usuarios WHERE id = ? FOR UPDATE', [$uid]);
            }

            $reemplazante = (new UsuarioModel())
                ->where('rol', 'ENFERMERO')
                ->where('estado', 'ACTIVO')
                ->find($reemplazanteId);
            if (! $reemplazante instanceof Usuario) {
                $this->db->transRollback();

                return $fallo('reemplazante_id', 'El enfermero/a elegido no está activo.');
            }

            $evaluacion = $this->evaluarCandidatos($ausencia, [$reemplazante]);
            $fila       = $evaluacion[0] ?? null;
            if ($fila === null || $fila['estado'] !== AsignacionModel::DISPONIBLE) {
                $this->db->transRollback();
                $detalle = $fila !== null && $fila['motivo'] !== null ? ' (' . $fila['motivo'] . ')' : '';

                return $fallo('reemplazante_id', $reemplazante->getNombreCompleto() . ' ya no está disponible para este turno' . $detalle . '. Elija otro enfermero/a.');
            }

            $ahora = date('Y-m-d H:i:s');
            $this->db->table('asignaciones')->insert([
                'usuario_id'       => $reemplazanteId,
                'sector_id'        => (int) $ausencia->sector_id,
                'tipo_turno_id'    => (int) $ausencia->tipo_turno_id,
                'fecha'            => (string) $ausencia->fecha,
                'inicio'           => (string) $ausencia->inicio,
                'fin'              => (string) $ausencia->fin,
                'duracion_minutos' => (int) $ausencia->duracion_minutos,
                'es_extra'         => 0,
                'minutos_extra'    => 0,
                'creado_por'       => $adminId,
                'created_at'       => $ahora,
                'updated_at'       => $ahora,
            ]);
            $nuevaId = (int) $this->db->insertID();

            $this->db->table($this->table)->where('id', $ausenciaId)->update([
                'estado'                  => self::CUBIERTA,
                'reemplazo_asignacion_id' => $nuevaId,
                'resuelto_por'            => $adminId,
                'resuelto_at'             => $ahora,
                'updated_at'              => $ahora,
            ]);

            $asignaciones = new AsignacionModel();
            $asignaciones->recalcularExtrasSemana($reemplazanteId, (string) $ausencia->fecha);
            $asignaciones->recalcularExtrasSemana((int) $ausencia->usuario_id, (string) $ausencia->fecha);

            $this->db->transCommit();

            return ['ok' => true, 'asignacion_id' => $nuevaId, 'errores' => [], 'no_encontrada' => false];
        } catch (\Throwable $e) {
            $this->db->transRollback();
            log_message('error', 'No se pudo asignar el reemplazo: ' . $e->getMessage());

            return $fallo('general', 'No se pudo asignar el reemplazo. Intente nuevamente.');
        }
    }

    /**
     * Rechaza la ausencia (observación obligatoria de 5 a 255 caracteres): el turno vuelve a contar para la
     * dotación y las horas, y se recalcula la semana del enfermero/a.
     *
     * @return array{ok: bool, errores: array<string, string>, no_encontrada: bool}
     *         errores con clave 'observacion' o 'general'.
     */
    public function rechazar(int $ausenciaId, int $adminId, string $observacion): array
    {
        $observacion = trim($observacion);
        $errores     = $this->validarDatos(['observacion' => $observacion], self::REGLAS_RECHAZO);
        if ($errores !== []) {
            return ['ok' => false, 'errores' => $errores, 'no_encontrada' => false];
        }

        $this->db->transBegin();

        try {
            $fila = $this->db->query(
                'SELECT au.id, au.estado, au.usuario_id, au.asignacion_id, a.fecha, a.inicio, a.fin FROM ausencias au '
                . 'JOIN asignaciones a ON a.id = au.asignacion_id WHERE au.id = ? FOR UPDATE',
                [$ausenciaId]
            )->getRow();

            if ($fila === null) {
                $this->db->transRollback();

                return ['ok' => false, 'errores' => ['general' => 'No se encontró la ausencia.'], 'no_encontrada' => true];
            }
            if ($fila->estado !== self::PENDIENTE) {
                $this->db->transRollback();

                return ['ok' => false, 'errores' => ['general' => 'Solo se pueden rechazar ausencias pendientes.'], 'no_encontrada' => false];
            }

            // Si mientras tanto el enfermero/a quedó con otro turno en ese horario, no puede volver a este.
            $asignaciones = new AsignacionModel();
            $choque       = $asignaciones->superpuesta((int) $fila->usuario_id, (string) $fila->inicio, (string) $fila->fin, (int) $fila->asignacion_id);
            if ($choque !== null) {
                $this->db->transRollback();

                return ['ok' => false, 'errores' => ['general' => 'No se puede rechazar: el enfermero/a ya tiene otro turno que se superpone con este. Asigne un reemplazo.'], 'no_encontrada' => false];
            }

            $ahora = date('Y-m-d H:i:s');
            $this->db->table($this->table)->where('id', $ausenciaId)->update([
                'estado'            => self::RECHAZADA,
                'resuelto_por'      => $adminId,
                'resuelto_at'       => $ahora,
                'observacion_admin' => $observacion,
                'updated_at'        => $ahora,
            ]);

            $asignaciones->recalcularExtrasSemana((int) $fila->usuario_id, (string) $fila->fecha);

            $this->db->transCommit();

            return ['ok' => true, 'errores' => [], 'no_encontrada' => false];
        } catch (\Throwable $e) {
            $this->db->transRollback();
            log_message('error', 'No se pudo rechazar la ausencia: ' . $e->getMessage());

            return ['ok' => false, 'errores' => ['general' => 'No se pudo rechazar la ausencia. Intente nuevamente.'], 'no_encontrada' => false];
        }
    }

    // ------------------------------------------------------------------
    //  Auxiliares
    // ------------------------------------------------------------------

    /** Builder con los joins del detalle. */
    private function conDetalle(): self
    {
        return $this->select(self::SELECT_DETALLE)
            ->join('asignaciones a', 'a.id = ausencias.asignacion_id')
            ->join('usuarios u', 'u.id = ausencias.usuario_id')
            ->join('sectores s', 's.id = a.sector_id', 'left')
            ->join('tipos_turno t', 't.id = a.tipo_turno_id', 'left')
            ->join('asignaciones ra', 'ra.id = ausencias.reemplazo_asignacion_id', 'left')
            ->join('usuarios ru', 'ru.id = ra.usuario_id', 'left')
            ->join('usuarios adm', 'adm.id = ausencias.resuelto_por', 'left');
    }

    /** Por qué no se puede asignar un reemplazo, o null si se puede. */
    private function motivoNoCubrible(Ausencia $ausencia): ?string
    {
        if (! $ausencia->estaPendiente()) {
            return 'Solo se puede asignar un reemplazo a una ausencia pendiente.';
        }
        if (! empty($ausencia->asignacion_eliminada)) {
            return 'El turno de esta ausencia fue eliminado.';
        }
        if ($ausencia->turnoTerminado()) {
            return 'El turno ya terminó: no se puede asignar un reemplazo.';
        }

        return null;
    }

    /**
     * Evalúa la disponibilidad para el horario REAL del turno ausente (inicio y duración guardados), no el
     * horario actual del tipo de turno (puede haber cambiado después de asignarlo).
     *
     * @param list<Usuario>|null $enfermeros null = todos los enfermeros/as activos menos el ausente
     *
     * @return list<array{enfermero: Usuario, minutos_semana: int, maximo_minutos: int, estado: string, motivo: string|null}>
     */
    private function evaluarCandidatos(Ausencia $ausencia, ?array $enfermeros = null): array
    {
        if ($enfermeros === null) {
            $ausente    = (int) $ausencia->usuario_id;
            $enfermeros = array_values(array_filter(
                (new UsuarioModel())->enfermerosActivos(),
                static fn (Usuario $u): bool => (int) $u->id !== $ausente
            ));
        }

        $tipo = (new TipoTurnoModel())->withDeleted()->find((int) $ausencia->tipo_turno_id);
        $tipoEval = $tipo instanceof TipoTurno ? clone $tipo : new TipoTurno();
        $tipoEval->hora_inicio      = substr((string) $ausencia->inicio, 11, 8);
        $tipoEval->duracion_minutos = (int) $ausencia->duracion_minutos;
        $tipoEval->sector_id        = (int) $ausencia->sector_id;

        return (new AsignacionModel())->evaluarDisponibilidad($enfermeros, $tipoEval, (string) $ausencia->fecha);
    }

    /**
     * Valida datos sueltos con reglas propias (no las del modelo).
     *
     * @param array<string, mixed> $datos
     * @param array<string, mixed> $reglas
     *
     * @return array<string, string>
     */
    private function validarDatos(array $datos, array $reglas): array
    {
        $validacion = service('validation', null, false);
        $validacion->setRules($reglas);

        return $validacion->run($datos) ? [] : $validacion->getErrors();
    }
}
