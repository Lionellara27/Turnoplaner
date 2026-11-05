<?php
/**
 * HU-13: asignar (alta) o reasignar (edición) un turno.
 *
 * @var \App\Entities\Asignacion|null   $asignacion  null al asignar
 * @var list<\App\Entities\Usuario>     $enfermeros  activos
 * @var list<\App\Entities\Sector>      $sectores    activos
 * @var list<\App\Entities\TipoTurno>   $tipos       activos
 * @var array<string, mixed>            $previo      valores iniciales
 * @var array<int, array{minutos:int, motivo:string}> $disponibilidad  por enfermero/a (si ya se sabe turno y fecha)
 * @var string                          $calculoPara texto "Noche del lunes 09/11/2026" o vacío
 * @var string                          $fechaMaxima Y-m-d
 */
$disponibilidad = $disponibilidad ?? [];
$editando = $asignacion !== null;
$valor    = static fn (string $campo): string => (string) old($campo, (string) ($previo[$campo] ?? ''), false);
$accion   = $editando ? base_url('admin/asignaciones/' . $asignacion->id . '/editar') : base_url('admin/asignaciones/crear');
$volver   = base_url('admin/planificador') . '?' . http_build_query(array_filter([
    'sector' => (int) ($previo['sector_id'] ?? 0) ?: null,
    'semana' => \App\Models\AsignacionModel::lunesDe($valor('fecha') ?: date('Y-m-d')),
]), '', '&amp;');
$faltanDatos = $enfermeros === [] || $sectores === [] || $tipos === [];
?>
<?= $this->extend('layouts/main') ?>

<?= $this->section('contenido') ?>
<div class="card shadow-sm border-0 rounded-3 overflow-hidden">
    <div class="card-header tp-card-header py-3 px-4">
        <div class="d-flex align-items-center">
            <div class="me-3"><img src="<?= base_url('img/logo-64.png') ?>" alt="Logo TurnoPlaner" class="barra__logo"></div>
            <div>
                <h1 class="h4 mb-0 fw-semibold text-white"><?= $editando ? 'Reasignar turno' : 'Asignar turno' ?></h1>
                <p class="mb-0 small subtitulo">Enfermero/a, sector, fecha y franja horaria</p>
            </div>
        </div>
    </div>

    <div class="card-body p-4 p-md-5 bg-white">

        <?php if ($faltanDatos): ?>
            <!-- Estado vacío: faltan catálogos para poder asignar -->
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
                El sistema rechaza el turno si el enfermero/a ya tiene otro que se superpone en el horario,
                o si con este turno supera su <strong>carga horaria semanal contratada</strong> (semana de lunes a domingo).
            </div>
        </div>

        <form action="<?= $accion ?>" method="post" class="needs-validation" novalidate data-enviando="Guardando...">
            <?= csrf_field() ?>
            <p class="small text-body-secondary">Los campos con * son obligatorios.</p>

            <div class="row g-3">
                <div class="col-12">
                    <?php if (count($enfermeros) > 8): ?>
                        <!-- Buscador: filtra la lista de enfermeros/as mientras se escribe -->
                        <label for="buscar-enfermero" class="form-label small fw-semibold mb-1">Buscar enfermero/a</label>
                        <input type="search" id="buscar-enfermero" class="form-control mb-2" placeholder="Apellido o nombre"
                               aria-controls="usuario_id" data-filtrar-select="#usuario_id" autocomplete="off">
                    <?php endif; ?>
                    <div class="form-floating">
                        <select class="form-select<?= clase_invalido('usuario_id') ?>" id="usuario_id" name="usuario_id" required
                                aria-describedby="ayuda-enfermero" data-mensaje="Elija el enfermero/a.">
                            <option value="" <?= $valor('usuario_id') === '' || $valor('usuario_id') === '0' ? 'selected' : '' ?> disabled>Elija un enfermero/a</option>
                            <?php foreach ($enfermeros as $e): ?>
                                <?php
                                $info   = $disponibilidad[(int) $e->id] ?? null;
                                $maximo = (int) $e->carga_horaria_max;
                                $horas  = $info !== null
                                    ? \App\Models\AsignacionModel::horasTexto($info['minutos']) . " de {$maximo} h esta semana"
                                    : "máximo {$maximo} h por semana";
                                $noPuede = $info !== null && $info['motivo'] !== '' && $valor('usuario_id') !== (string) $e->id;
                                ?>
                                <option value="<?= (int) $e->id ?>" <?= $valor('usuario_id') === (string) $e->id ? 'selected' : '' ?> <?= $noPuede ? 'disabled' : '' ?>>
                                    <?= esc($e->getNombreCompleto()) ?> · <?= esc($horas) ?><?= $noPuede ? ' · no disponible: ' . esc($info['motivo']) : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <label for="usuario_id">Enfermero/a *</label>
                        <?= feedback_campo('usuario_id') ?>
                    </div>
                    <div id="ayuda-enfermero" class="form-text">
                        Solo aparecen los enfermeros/as activos.
                        <?php if ($calculoPara !== ''): ?>
                            Las horas y la disponibilidad están calculadas para <strong><?= esc($calculoPara) ?></strong>; si cambia la fecha o el turno, se vuelve a controlar al guardar.
                        <?php else: ?>
                            Al lado figura su carga horaria máxima semanal.
                        <?php endif; ?>
                    </div>
                </div>

                <div class="col-12 col-md-6">
                    <div class="form-floating">
                        <select class="form-select<?= clase_invalido('sector_id') ?>" id="sector_id" name="sector_id" required data-mensaje="Elija el sector.">
                            <option value="" <?= in_array($valor('sector_id'), ['', '0'], true) ? 'selected' : '' ?> disabled>Elija un sector</option>
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
                               aria-describedby="ayuda-fecha" data-mensaje="Elija una fecha de hoy en adelante.">
                        <label for="fecha">Fecha de inicio del turno *</label>
                        <?= feedback_campo('fecha') ?>
                    </div>
                    <div id="ayuda-fecha" class="form-text">Día en que empieza el turno. Un turno noche termina al día siguiente.</div>
                </div>

                <div class="col-12">
                    <div class="form-floating">
                        <select class="form-select<?= clase_invalido('tipo_turno_id') ?>" id="tipo_turno_id" name="tipo_turno_id" required data-mensaje="Elija el turno.">
                            <option value="" <?= in_array($valor('tipo_turno_id'), ['', '0'], true) ? 'selected' : '' ?> disabled>Elija un turno</option>
                            <?php foreach ($tipos as $t): ?>
                                <option value="<?= (int) $t->id ?>" <?= $valor('tipo_turno_id') === (string) $t->id ? 'selected' : '' ?>>
                                    <?= esc($t->nombre) ?> · <?= esc($t->rangoTexto()) ?> · <?= esc($t->duracionTexto()) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <label for="tipo_turno_id">Turno (franja horaria) *</label>
                        <?= feedback_campo('tipo_turno_id') ?>
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
