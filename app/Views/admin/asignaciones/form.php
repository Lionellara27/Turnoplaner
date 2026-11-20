<?php
/**
 * HU-13: asignar (alta) o reasignar (edición) un turno.
 *
 * @var \App\Entities\Asignacion|null   $asignacion  null al asignar
 * @var list<\App\Entities\Usuario>     $enfermeros  activos (ordenados por prioridad si se conoce turno y fecha)
 * @var list<\App\Entities\Sector>      $sectores    activos
 * @var list<\App\Entities\TipoTurno>   $tipos       activos
 * @var array<string, mixed>            $previo      valores iniciales
 * @var array<int, array{minutos:int, cumple_carga:bool, es_extra:bool, motivo:string, estado?:string, horas_texto?:string}> $disponibilidad
 * @var string                          $calculoPara texto "Noche del lunes 09/11/2026" o vacío
 * @var string                          $fechaMaxima Y-m-d
 */
$disponibilidad = $disponibilidad ?? [];
$especialidades = $especialidades ?? \App\Models\UsuarioModel::ESPECIALIDADES;
$editando = $asignacion !== null;
$valor    = static fn (string $campo): string => (string) old($campo, (string) ($previo[$campo] ?? ''), false);
$accion   = $editando ? base_url('admin/asignaciones/' . $asignacion->id . '/editar') : base_url('admin/asignaciones/crear');
$volver   = base_url('admin/planificador') . '?' . http_build_query(array_filter([
    'sector' => (int) ($previo['sector_id'] ?? 0) ?: null,
    'mes'    => substr($valor('fecha') ?: date('Y-m-d'), 0, 7),
]), '', '&amp;');
$faltanDatos = $enfermeros === [] || $sectores === [] || $tipos === [];

// Opciones del selector (mismo formato que GET admin/asignaciones/disponibilidad). El orden lo decide el servidor.
$elegido  = $valor('usuario_id');
$opciones = [];
foreach ($enfermeros as $e) {
    $eid    = (int) $e->id;
    $info   = $disponibilidad[$eid] ?? null;
    $maximo = (int) $e->carga_horaria_max;
    if ($info !== null) {
        $motivo = (string) ($info['motivo'] ?? '');
        $estado = (string) ($info['estado'] ?? ($motivo !== '' ? 'no_disponible' : (! empty($info['es_extra']) ? 'extra' : 'disponible')));
        $horas  = (string) ($info['horas_texto'] ?? (\App\Models\AsignacionModel::horasTexto((int) $info['minutos']) . " de {$maximo} h esta semana"));
    } else {
        $motivo = '';
        $estado = '';
        $horas  = "Tope: {$maximo} h semanales";
    }
    $opciones[] = [
        'id'           => $eid,
        'nombre'       => $e->getNombreCompleto(),
        'especialidad' => (string) ($e->especialidad ?? ''),
        'matricula'    => (string) ($e->matricula ?? ''),
        'horas_texto'  => $horas,
        'minutos'      => $info !== null ? (int) ($info['minutos'] ?? 0) : 0,
        'orden'        => count($opciones),
        'estado'       => $estado,
        'motivo'       => $motivo,
        'checked'      => $elegido === (string) $eid,
        'actual'       => $editando && (int) $asignacion->usuario_id === $eid,
    ];
}
?>
<?= $this->extend('layouts/main') ?>

<?= $this->section('contenido') ?>
<div class="card shadow-sm border-0 rounded-3 overflow-hidden">
    <div class="card-header tp-card-header py-3 px-4">
        <div class="d-flex align-items-center">
            <div class="me-3"><img src="<?= base_url('img/logo-64.png') ?>" alt="Logo TurnoPlaner" class="barra__logo"></div>
            <div>
                <h1 class="h4 mb-0 fw-semibold text-white"><?= $editando ? 'Reasignar turno' : 'Asignar turno' ?></h1>
                <p class="mb-0 small subtitulo">Planificación mensual · Sector, fecha, turno y enfermero/a</p>
            </div>
        </div>
    </div>

    <div class="card-body p-4 p-md-5 bg-white">

        <?php if ($faltanDatos): ?>
            <div class="text-center py-4">
                <i class="bi bi-exclamation-circle fs-1 text-body-secondary d-block mb-2" aria-hidden="true"></i>
                <p class="fw-semibold mb-2">Para asignar turnos hace falta tener cargado:</p>
                <ul class="list-unstyled mb-3">
                    <?php if ($enfermeros === []): ?><li><a href="<?= base_url('admin/usuarios/crear') ?>">al menos un enfermero/a activo</a></li><?php endif; ?>
                    <?php if ($sectores === []): ?><li><a href="<?= base_url('admin/sectores/crear') ?>">al menos un sector activo</a></li><?php endif; ?>
                    <?php if ($tipos === []): ?><li><a href="<?= base_url('admin/tipos-turno/crear') ?>">al menos un tipo de turno activo</a></li><?php endif; ?>
                </ul>
            </div>
        <?php else: ?>

        <div class="alerta alerta--info d-flex align-items-start gap-2 mb-4" role="note">
            <i class="bi bi-info-circle-fill fs-5" aria-hidden="true"></i>
            <div class="small">
                La planificación mensual está habilitada hasta <strong>2 meses por adelantado</strong> (hasta el <?= esc(fecha_ar($fechaMaxima)) ?>).
                Tienen prioridad quienes aún no completaron su carga horaria; quienes ya la cumplieron pueden seguir tomando turnos como <strong>horas extras cubiertas</strong> (siempre que no se superpongan).
            </div>
        </div>

        <form action="<?= $accion ?>" method="post" class="needs-validation" novalidate data-enviando="Guardando..." data-form-asignacion>
            <?= csrf_field() ?>
            <p class="small text-body-secondary">Los campos con * son obligatorios.</p>

            <div class="row g-3">
                <div class="col-12 col-md-6">
                    <div class="form-floating">
                        <select class="form-select<?= clase_invalido('sector_id') ?>" id="sector_id" name="sector_id" required data-mensaje="Seleccione el sector.">
                            <option value="" <?= in_array($valor('sector_id'), ['', '0'], true) ? 'selected' : '' ?> disabled>Seleccione un sector</option>
                            <?php foreach ($sectores as $s): ?>
                                <option value="<?= (int) $s->id ?>" <?= $valor('sector_id') === (string) $s->id ? 'selected' : '' ?>>
                                    <?= esc($s->nombre) ?><?= $s->esCritico() ? ' (área crítica)' : '' ?> · mínimo <?= (int) $s->dotacion_minima ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <label for="sector_id">Sector *</label>
                        <?= feedback_campo('sector_id') ?>
                    </div>
                </div>

                <div class="col-12 col-md-6">
                    <div class="form-floating">
                        <input type="date" class="form-control<?= clase_invalido('fecha') ?>" id="fecha" name="fecha"
                               value="<?= esc($valor('fecha')) ?>" min="<?= date('Y-m-d') ?>" max="<?= esc($fechaMaxima ?? '') ?>" required
                               aria-describedby="ayuda-fecha" data-mensaje="Seleccione una fecha válida dentro del período habilitado.">
                        <label for="fecha">Fecha de inicio del turno *</label>
                        <?= feedback_campo('fecha') ?>
                    </div>
                    <div id="ayuda-fecha" class="form-text">Habilitado hasta el <?= esc(fecha_ar($fechaMaxima)) ?> (2 meses por adelantado).</div>
                </div>

                <div class="col-12">
                    <div class="form-floating">
                        <select class="form-select<?= clase_invalido('tipo_turno_id') ?>" id="tipo_turno_id" name="tipo_turno_id" required
                                data-mensaje="Seleccione el turno." data-filtrar-por-sector="#sector_id">
                            <option value="" <?= in_array($valor('tipo_turno_id'), ['', '0'], true) ? 'selected' : '' ?> disabled>Seleccione un turno</option>
                            <?php foreach ($tipos as $t): ?>
                                <option value="<?= (int) $t->id ?>" data-sector="<?= (int) $t->sector_id ?>"
                                        <?= $valor('tipo_turno_id') === (string) $t->id ? 'selected' : '' ?>>
                                    <?= esc($t->nombre) ?> · <?= esc($t->rangoTexto()) ?> · <?= esc($t->duracionTexto()) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <label for="tipo_turno_id">Turno del sector (franja horaria) *</label>
                        <?= feedback_campo('tipo_turno_id') ?>
                    </div>
                </div>

                <div class="col-12">
                    <?= view('admin/planificador/_selector_enfermeros', [
                        'prefijo'        => 'form',
                        'idCampo'        => 'usuario_id',
                        'especialidades' => $especialidades,
                        'opciones'       => $opciones,
                        'conEstados'     => $disponibilidad !== [],
                        'ayudaId'        => 'ayuda-enfermero',
                        'excluir'        => $editando ? (int) $asignacion->id : null,
                        'actual'         => $editando ? (int) $asignacion->usuario_id : null,
                        'error'          => error_campo('usuario_id'),
                    ]) ?>
                    <div id="ayuda-enfermero" class="form-text">
                        Primero aparecen quienes tienen horas disponibles en la semana, luego quienes tomarían el turno como hora extra y al final quienes no pueden tomarlo, con el motivo.
                        <span data-sel-calculo><?= $calculoPara !== '' ? 'Disponibilidad calculada para ' . esc($calculoPara) . '.' : 'Seleccione sector, fecha y turno para ver la disponibilidad de cada enfermero/a.' ?></span>
                    </div>
                </div>
            </div>

            <div class="mt-4 pt-3 border-top d-flex flex-column flex-sm-row justify-content-between align-items-center gap-3">
                <div class="d-flex flex-column flex-sm-row gap-2 order-2 order-sm-1 align-self-stretch align-self-sm-auto">
                    <a href="<?= $volver ?>" class="btn btn-outline-secondary px-4 py-2 fw-medium">Cancelar</a>
                    <?php if ($editando): ?>
                        <button type="button" class="btn btn-outline-danger px-4 py-2 fw-medium" data-bs-toggle="modal" data-bs-target="#modalEliminarTurno">
                            <i class="bi bi-trash me-1" aria-hidden="true"></i>Eliminar turno
                        </button>
                    <?php endif; ?>
                </div>
                <button type="submit" class="btn btn-tp px-4 py-2 fw-semibold shadow-sm order-1 order-sm-2">
                    <i class="bi bi-check2-circle me-1" aria-hidden="true"></i><?= $editando ? 'Guardar cambios' : 'Asignar turno' ?>
                </button>
            </div>
        </form>
        <?php endif; ?>
    </div>
</div>

<?php if ($editando): ?>
    <div class="modal fade" id="modalEliminarTurno" tabindex="-1" aria-labelledby="tituloEliminarTurno" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title h6" id="tituloEliminarTurno"><i class="bi bi-trash me-1" aria-hidden="true"></i>Eliminar turno</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-2">¿Eliminar el turno del <strong><?= esc(fecha_larga((string) $asignacion->fecha)) ?></strong>?</p>
                    <p class="small text-body-secondary mb-0">El enfermero/a deja de tenerlo en su cronograma y el sector puede quedar por debajo de su dotación mínima.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <form action="<?= base_url('admin/asignaciones/' . $asignacion->id . '/eliminar') ?>" method="post" class="d-inline" data-enviando="Eliminando...">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-danger">Eliminar turno</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>
<?= $this->endSection() ?>