<?php
/**
 * HU-16: Asignar reemplazo a una ausencia.
 * Muestra el turno y el aviso, los candidatos disponibles como radios reales (funciona sin JavaScript)
 * y, abajo, el formulario para rechazar la ausencia.
 *
 * @var \App\Entities\Ausencia $ausencia
 * @var list<array>            $opciones       ver admin/planificador/_opcion_enfermero.php
 * @var int                    $excluidos      enfermeros/as activos que no se ofrecen (turno superpuesto u hora extra)
 * @var string|null            $error          por qué no se puede asignar un reemplazo (null si se puede)
 * @var list<string>           $especialidades
 * @var string                 $semana         lunes de la semana del turno
 */
$a           = $ausencia;
$errorCampo  = error_campo('usuario_id');
$total       = count($opciones);
$urlPlan     = base_url('admin/planificador') . '?' . http_build_query(['sector' => (int) $a->sector_id, 'mes' => substr((string) $a->fecha, 0, 7)], '', '&amp;');
$urlTablero  = base_url('admin/tablero') . '?' . http_build_query(['dia' => (string) $a->fecha], '', '&amp;');
$textoExcl   = $excluidos === 1
    ? '1 enfermero/a activo no se muestra porque tiene un turno que se superpone o ya completó su carga semanal (haría horas extras).'
    : $excluidos . ' enfermeros/as activos no se muestran porque tienen un turno que se superpone o ya completaron su carga semanal (harían horas extras).';
?>
<?= $this->extend('layouts/main') ?>

<?= $this->section('contenido') ?>
<div class="card shadow-sm border-0 rounded-3 overflow-hidden mb-4">

    <div class="card-header tp-card-header py-3 px-4">
        <div class="d-flex align-items-center min-w-0">
            <div class="me-3"><img src="<?= base_url('img/logo-64.png') ?>" alt="Logo TurnoPlaner" class="barra__logo"></div>
            <div class="min-w-0">
                <h1 class="h4 mb-0 fw-semibold text-white">Asignar reemplazo</h1>
                <p class="mb-0 small subtitulo"><?= esc((string) $a->tipo_nombre) ?> del <?= esc(fecha_larga((string) $a->fecha)) ?> en <?= esc((string) $a->sector_nombre) ?></p>
            </div>
        </div>
    </div>

    <div class="card-body p-3 p-md-4 bg-white">

        <!-- Turno y aviso -->
        <section class="row g-3 mb-4" aria-label="Datos del turno y de la ausencia">
            <div class="col-12 col-lg-6">
                <div class="tp-ausencia-ficha h-100">
                    <h2 class="h6 fw-bold mb-2"><i class="bi bi-calendar-event me-1" aria-hidden="true"></i>Turno a cubrir</h2>
                    <?= view('admin/ausencias/_turno', ['a' => $a]) ?>
                    <a href="<?= $urlTablero ?>" class="small mt-2 tp-link-dia">Ver la cobertura de ese día</a>
                </div>
            </div>
            <div class="col-12 col-lg-6">
                <div class="tp-ausencia-ficha h-100">
                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
                        <h2 class="h6 fw-bold mb-0"><i class="bi bi-person-dash me-1" aria-hidden="true"></i>Ausencia informada</h2>
                        <?= view('admin/ausencias/_estado', ['a' => $a]) ?>
                    </div>
                    <div class="row g-2">
                        <div class="col-12 col-sm-6"><dl class="dato-lectura mb-0"><dt>Enfermero/a</dt><dd><?= esc($a->nombreEnfermero()) ?><span class="d-block tp-matricula"><?= ! empty($a->enfermero_matricula) ? 'Mat. ' . esc($a->enfermero_matricula) : 'Sin matrícula' ?></span></dd></dl></div>
                        <div class="col-12 col-sm-6"><dl class="dato-lectura mb-0"><dt>Motivo</dt><dd><?= esc($a->motivoTexto()) ?></dd></dl></div>
                        <div class="col-12"><dl class="dato-lectura mb-0"><dt>Descripción</dt><dd class="text-break"><?= esc((string) $a->descripcion) ?></dd></dl></div>
                        <div class="col-12"><dl class="dato-lectura mb-0"><dt>Informada el</dt><dd><?= esc(fecha_ar($a->created_at, true)) ?></dd></dl></div>
                    </div>
                    <?php if (! $a->estaPendiente()): ?>
                        <div class="mt-2"><?= view('admin/ausencias/_resolucion', ['a' => $a]) ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <?php if ($error !== null): ?>
            <!-- La ausencia ya no admite reemplazo (resuelta o turno terminado) -->
            <div class="alerta alerta--info d-flex align-items-start gap-2 mb-3" role="status">
                <i class="bi bi-info-circle-fill fs-5" aria-hidden="true"></i>
                <div><?= $a->estaPendiente() ? esc($error) : 'Esta ausencia ya fue resuelta (' . esc(mb_strtolower($a->estadoTexto())) . '): no necesita reemplazo.' ?></div>
            </div>
            <a href="<?= base_url('admin/ausencias') ?>" class="btn btn-outline-secondary px-4 py-2 fw-medium">
                <i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Volver a Ausencias
            </a>

        <?php elseif ($opciones === []): ?>
            <!-- Estado vacío: nadie disponible -->
            <div class="text-center py-5 px-3 border rounded-3">
                <i class="bi bi-person-x fs-1 text-body-secondary d-block mb-2" aria-hidden="true"></i>
                <p class="fw-semibold mb-1">No hay enfermeros/as disponibles para cubrir este turno.</p>
                <p class="small text-body-secondary mb-3"><?= $excluidos > 0 ? esc($textoExcl) . ' ' : '' ?>Puede reorganizar los turnos del sector desde el Planificador o rechazar la ausencia.</p>
                <div class="d-flex flex-column flex-sm-row justify-content-center gap-2">
                    <a href="<?= base_url('admin/ausencias') ?>" class="btn btn-outline-secondary order-2 order-sm-1">Volver a Ausencias</a>
                    <a href="<?= $urlPlan ?>" class="btn btn-tp order-1 order-sm-2"><i class="bi bi-calendar-week me-1" aria-hidden="true"></i>Ir al Planificador</a>
                </div>
            </div>

        <?php else: ?>
            <form action="<?= base_url('admin/ausencias/' . (int) $a->id . '/reemplazo') ?>" method="post" class="needs-validation" novalidate data-enviando="Asignando reemplazo...">
                <?= csrf_field() ?>
                <fieldset class="selector-enfermeros<?= $errorCampo !== '' ? ' is-invalid' : '' ?>" id="usuario_id" tabindex="-1"
                          data-filtro-lista data-mensaje="Seleccione el enfermero/a que cubrirá el turno."
                          aria-describedby="ayuda-reemplazo<?= $errorCampo !== '' ? ' error-usuario_id' : '' ?>">
                    <legend class="form-label fw-semibold mb-1">Enfermero/a reemplazante *</legend>
                    <p class="small text-body-secondary mb-2" id="ayuda-reemplazo">
                        Solo se muestran enfermeros/as activos sin turno en ese horario y con horas disponibles en la semana del turno, con menos horas primero.
                    </p>
                    <?php if ($errorCampo !== ''): ?>
                        <div class="invalid-feedback d-block mt-0 mb-2" id="error-usuario_id"><?= esc($errorCampo) ?></div>
                    <?php endif; ?>

                    <?php if ($excluidos > 0): ?>
                        <p class="alerta alerta--info small d-flex align-items-start gap-2 mb-2">
                            <i class="bi bi-funnel" aria-hidden="true"></i><span><?= esc($textoExcl) ?></span>
                        </p>
                    <?php endif; ?>

                    <?php if ($total > 5): ?>
                        <div class="tp-filtro-barra p-3 mb-2">
                            <div class="row g-2 align-items-end">
                                <div class="col-12 col-md-7">
                                    <label for="reemp-buscar" class="form-label small fw-semibold mb-1">Buscar</label>
                                    <input type="search" class="form-control" id="reemp-buscar" data-filtro-texto
                                           placeholder="Apellido, nombre o matrícula" autocomplete="off" enterkeyhint="search">
                                </div>
                                <div class="col-12 col-md-5">
                                    <label for="reemp-especialidad" class="form-label small fw-semibold mb-1">Especialidad</label>
                                    <select class="form-select" id="reemp-especialidad" data-filtro-campo="especialidad">
                                        <option value="" selected>Todas</option>
                                        <?php foreach ($especialidades as $esp): ?>
                                            <option value="<?= esc($esp, 'attr') ?>"><?= esc($esp) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <p class="small text-body-secondary mb-2" role="status" data-filtro-contador data-singular="enfermero/a disponible" data-plural="enfermeros/as disponibles"><?= $total ?> <?= $total === 1 ? 'enfermero/a disponible' : 'enfermeros/as disponibles' ?></p>

                    <div class="tp-enfermeros-scroll" data-filtro-items>
                        <?php foreach ($opciones as $o): ?>
                            <?= view('admin/planificador/_opcion_enfermero', ['o' => $o, 'prefijo' => 'reemp']) ?>
                        <?php endforeach; ?>
                    </div>

                    <div class="text-center py-4 px-3 border rounded-3 d-none" data-filtro-vacio>
                        <p class="small text-body-secondary mb-2">No hay enfermeros/as que coincidan con la búsqueda.</p>
                        <button type="button" class="btn btn-outline-secondary" data-filtro-restablecer>
                            <i class="bi bi-arrow-counterclockwise me-1" aria-hidden="true"></i>Restablecer filtros
                        </button>
                    </div>
                </fieldset>

                <div class="mt-4 pt-3 border-top d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3">
                    <a href="<?= base_url('admin/ausencias') ?>" class="btn btn-outline-secondary px-4 py-2 fw-medium order-2 order-sm-1">Cancelar</a>
                    <button type="submit" class="btn btn-tp px-4 py-2 fw-semibold shadow-sm order-1 order-sm-2">
                        <i class="bi bi-person-check me-1" aria-hidden="true"></i>Confirmar reemplazo
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php if ($a->puedeRechazarse()): ?>
    <!-- Alternativa: rechazar la ausencia (también es el destino del botón "Rechazar" del listado sin JavaScript) -->
    <section class="card shadow-sm border-0 rounded-3 tp-rechazo-seccion" id="rechazar" aria-labelledby="titulo-rechazar" tabindex="-1">
        <div class="card-body p-3 p-md-4">
            <h2 class="h5 fw-bold mb-2" id="titulo-rechazar"><i class="bi bi-x-circle me-1" aria-hidden="true"></i>¿No corresponde la ausencia? Rechácela</h2>
            <?= view('admin/ausencias/_form_rechazo', ['a' => $a, 'idCampo' => 'observacion', 'conErrores' => true]) ?>
        </div>
    </section>
<?php endif; ?>
<?= $this->endSection() ?>
