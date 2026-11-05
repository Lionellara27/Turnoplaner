<?php
/**
 * HU-14: cuadrícula semanal de turnos por sector.
 *
 * @var list<\App\Entities\Sector>                                   $sectores
 * @var list<\App\Entities\TipoTurno>                                $tipos
 * @var \App\Entities\Sector|null                                    $sector
 * @var list<string>                                                 $dias      lunes a domingo (Y-m-d)
 * @var string                                                       $lunes, $anterior, $siguiente, $hoy, $ahora
 * @var array<string, array<int, list<\App\Entities\Asignacion>>>    $grilla
 * @var list<\App\Entities\Usuario>                                $enfermeros     activos
 * @var array<int, int>                                              $minutosSemana  [usuario_id => minutos de la semana]
 */
$enfermeros    = $enfermeros ?? [];
$minutosSemana = $minutosSemana ?? [];
$urlSemana   = static fn (string $semana): string => base_url('admin/planificador') . '?' . http_build_query(['sector' => $sector?->id, 'semana' => $semana], '', '&amp;');
$rangoSemana = 'Semana del ' . fecha_ar($dias[0]) . ' al ' . fecha_ar($dias[6]);
$estaSemana  = $lunes === \App\Models\AsignacionModel::lunesDe($hoy);
$celda       = static fn (\App\Entities\TipoTurno $t, string $dia): string => view('admin/planificador/_celda', [
    'sector' => $sector, 'tipo' => $t, 'dia' => $dia, 'asignados' => $grilla[$dia][(int) $t->id] ?? [], 'ahora' => $ahora,
]);
?>
<?= $this->extend('layouts/main') ?>

<?= $this->section('contenido') ?>
<div class="card shadow-sm border-0 rounded-3 overflow-hidden">

    <div class="card-header tp-card-header py-3 px-4">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="d-flex align-items-center">
                <div class="me-3"><img src="<?= base_url('img/logo-64.png') ?>" alt="Logo TurnoPlaner" class="barra__logo"></div>
                <div>
                    <h1 class="h4 mb-0 fw-semibold text-white">Planificador semanal</h1>
                    <p class="mb-0 small subtitulo"><?= $sector !== null ? esc($sector->nombre) . ' · ' : '' ?><?= esc($rangoSemana) ?></p>
                </div>
            </div>
            <?php if ($sector !== null && $tipos !== []): ?>
                <a href="<?= base_url('admin/asignaciones/crear') . '?' . http_build_query(['sector' => $sector->id], '', '&amp;') ?>" class="btn btn-light text-nowrap fw-semibold shadow-sm">
                    <i class="bi bi-calendar-plus me-1" aria-hidden="true"></i>Asignar turno
                </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="card-body p-3 p-md-4 bg-white">

        <?php if ($sector === null || $tipos === []): ?>
            <!-- Estado vacío -->
            <div class="text-center py-5 border rounded-3">
                <i class="bi bi-calendar-x fs-1 text-body-secondary d-block mb-2" aria-hidden="true"></i>
                <p class="fw-semibold mb-2">Para planificar hace falta tener cargado:</p>
                <ul class="list-unstyled mb-0">
                    <?php if ($sector === null): ?><li><a href="<?= base_url('admin/sectores/crear') ?>">al menos un sector activo</a></li><?php endif; ?>
                    <?php if ($tipos === []): ?><li><a href="<?= base_url('admin/tipos-turno/crear') ?>">al menos un tipo de turno activo</a></li><?php endif; ?>
                </ul>
            </div>
        <?php else: ?>

        <!-- Sector y navegación de semanas -->
        <div class="row g-2 align-items-end mb-3">
            <form method="get" action="<?= base_url('admin/planificador') ?>" class="col-12 col-lg-6 d-flex gap-2 align-items-end">
                <input type="hidden" name="semana" value="<?= esc($lunes) ?>">
                <div class="flex-grow-1">
                    <label for="sector" class="form-label small fw-semibold mb-1">Sector</label>
                    <select id="sector" name="sector" class="form-select">
                        <?php foreach ($sectores as $s): ?>
                            <option value="<?= (int) $s->id ?>" <?= (int) $s->id === (int) $sector->id ? 'selected' : '' ?>>
                                <?= esc($s->nombre) ?><?= $s->esCritico() ? ' (área crítica)' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-outline-secondary">Ver</button>
            </form>
            <nav class="col-12 col-lg-6 d-flex justify-content-lg-end" aria-label="Cambiar de semana">
                <div class="btn-group w-100 w-lg-auto" role="group">
                    <a class="btn btn-outline-secondary" href="<?= $urlSemana($anterior) ?>"><i class="bi bi-chevron-left" aria-hidden="true"></i> Anterior</a>
                    <a class="btn btn-outline-secondary<?= $estaSemana ? ' active' : '' ?>" href="<?= $urlSemana($hoy) ?>" <?= $estaSemana ? 'aria-current="true"' : '' ?>>Esta semana</a>
                    <a class="btn btn-outline-secondary" href="<?= $urlSemana($siguiente) ?>">Siguiente <i class="bi bi-chevron-right" aria-hidden="true"></i></a>
                </div>
            </nav>
        </div>

        <p class="small text-body-secondary mb-3">
            <i class="bi bi-people me-1" aria-hidden="true"></i>Dotación mínima de <?= esc($sector->nombre) ?>: <strong><?= esc($sector->dotacionTexto()) ?></strong>.
            Seleccione un nombre para reasignar o eliminar ese turno (solo turnos que todavía no terminaron).
        </p>

        <!-- Computadora grande (1400 px o más): tabla turnos x días -->
        <div class="table-responsive d-none d-xxl-block">
            <table class="table table-bordered align-top mb-0 cuadricula">
                <caption class="visually-hidden">Turnos de <?= esc($sector->nombre) ?>, <?= esc(mb_strtolower($rangoSemana)) ?></caption>
                <thead class="table-light">
                    <tr>
                        <th scope="col">Turno</th>
                        <?php foreach ($dias as $dia): ?>
                            <th scope="col" class="<?= $dia === $hoy ? 'cuadricula__hoy' : '' ?><?= $dia < $hoy ? ' cuadricula__pasado' : '' ?>" <?= $dia === $hoy ? 'aria-current="date"' : '' ?>>
                                <span class="text-capitalize"><?= esc(dia_semana($dia)) ?></span>
                                <span class="d-block small fw-normal"><?= esc(substr(fecha_ar($dia), 0, 5)) ?><?= $dia === $hoy ? ' · hoy' : '' ?><?= $dia < $hoy ? ' · pasado' : '' ?></span>
                            </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tipos as $t): ?>
                        <tr>
                            <th scope="row">
                                <?= view('admin/tipos_turno/_etiqueta', ['t' => $t]) ?>
                                <span class="d-block small fw-normal mt-1"><?= view('admin/tipos_turno/_rango', ['t' => $t]) ?></span>
                                <?php if (! $t->estaActivo()): ?><span class="d-block small fw-normal text-body-secondary">Turno dado de baja</span><?php endif; ?>
                            </th>
                            <?php foreach ($dias as $dia): ?>
                                <td class="<?= $dia === $hoy ? 'cuadricula__hoy' : '' ?><?= $dia < $hoy ? ' cuadricula__pasado' : '' ?>"><?= $celda($t, $dia) ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Celular, tablet y notebook: un bloque por día -->
        <div class="d-xxl-none">
            <?php if (in_array($hoy, $dias, true)): ?>
                <a class="btn btn-outline-secondary btn-sm mb-3" href="#dia-<?= esc($hoy) ?>"><i class="bi bi-arrow-down" aria-hidden="true"></i> Ir a hoy</a>
            <?php endif; ?>

            <?php foreach ($dias as $dia): ?>
                <?php
                $tituloDia = '<span class="text-capitalize">' . esc(dia_semana($dia)) . '</span> ' . esc(fecha_ar($dia));
                $contenido = '<div class="row row-cols-1 row-cols-md-2 g-0">';
                foreach ($tipos as $t) {
                    $contenido .= '<div class="col p-3 border-bottom">'
                        . '<div class="d-flex align-items-center gap-2 flex-wrap mb-1">'
                        . view('admin/tipos_turno/_etiqueta', ['t' => $t])
                        . '<span class="small text-body-secondary">' . view('admin/tipos_turno/_rango', ['t' => $t]) . '</span></div>'
                        . $celda($t, $dia) . '</div>';
                }
                $contenido .= '</div>';
                ?>
                <?php if ($dia < $hoy): ?>
                    <!-- Días pasados plegados: el jefe llega más rápido a hoy -->
                    <details class="dia-planificador dia-planificador--pasado border rounded-3 mb-3" id="dia-<?= esc($dia) ?>">
                        <summary class="px-3 py-2 fw-semibold"><?= $tituloDia ?> <span class="small fw-normal text-body-secondary">· pasado</span></summary>
                        <?= $contenido ?>
                    </details>
                <?php else: ?>
                    <section class="dia-planificador border rounded-3 mb-3<?= $dia === $hoy ? ' dia-planificador--hoy' : '' ?>" id="dia-<?= esc($dia) ?>" aria-labelledby="titulo-dia-<?= esc($dia) ?>">
                        <h2 class="h6 fw-semibold px-3 py-2 mb-0 border-bottom" id="titulo-dia-<?= esc($dia) ?>" <?= $dia === $hoy ? 'aria-current="date"' : '' ?>>
                            <?= $tituloDia ?>
                            <?php if ($dia === $hoy): ?><span class="badge bg-success-subtle text-success-emphasis ms-1">Hoy</span><?php endif; ?>
                        </h2>
                        <?= $contenido ?>
                    </section>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>

        <!-- Resumen de balanceo de cargas: horas de la semana de cada enfermero/a (todos los sectores) -->
        <section class="mt-4" aria-labelledby="titulo-horas">
            <h2 class="h6 fw-semibold mb-1" id="titulo-horas"><i class="bi bi-hourglass-split me-1" aria-hidden="true"></i>Horas de la semana por enfermero/a</h2>
            <p class="small text-body-secondary mb-2">Suma de todos los sectores, semana del <?= esc(fecha_ar($dias[0])) ?> al <?= esc(fecha_ar($dias[6])) ?>, contra la carga horaria máxima contratada.</p>
            <?php if ($enfermeros === []): ?>
                <p class="small text-body-secondary mb-0">No hay enfermeros/as activos.</p>
            <?php else: ?>
                <ul class="list-unstyled row row-cols-1 row-cols-md-2 row-cols-xl-3 g-2 mb-0">
                    <?php foreach ($enfermeros as $e): ?>
                        <?php
                        $min     = $minutosSemana[(int) $e->id] ?? 0;
                        $max     = (int) $e->carga_horaria_max * 60;
                        $porc    = $max > 0 ? min(100, (int) round($min * 100 / $max)) : 0;
                        $libre   = max(0, $max - $min);
                        $estado  = $min >= $max && $max > 0 ? 'completo' : ($porc >= 75 ? 'alto' : 'ok');
                        ?>
                        <li class="col">
                            <div class="carga-semana carga-semana--<?= $estado ?> border rounded-3 p-2 h-100">
                                <div class="d-flex justify-content-between gap-2 small">
                                    <span class="fw-semibold"><?= esc($e->getNombreCompleto()) ?></span>
                                    <span class="text-nowrap"><?= esc(\App\Models\AsignacionModel::horasTexto($min)) ?> de <?= (int) $e->carga_horaria_max ?> h</span>
                                </div>
                                <div class="progress mt-1" role="progressbar" aria-label="Horas asignadas a <?= esc($e->getNombreCompleto()) ?>"
                                     aria-valuenow="<?= $porc ?>" aria-valuemin="0" aria-valuemax="100" aria-valuetext="<?= $porc ?> por ciento de su máximo">
                                    <div class="progress-bar" style="width: <?= $porc ?>%"></div>
                                </div>
                                <span class="small text-body-secondary">
                                    <?php if ($estado === 'completo'): ?>
                                        <i class="bi bi-lock-fill" aria-hidden="true"></i> Sin horas libres esta semana
                                    <?php else: ?>
                                        Le quedan <?= esc(\App\Models\AsignacionModel::horasTexto($libre)) ?> libres
                                    <?php endif; ?>
                                </span>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <!-- Referencias -->
        <p class="small text-body-secondary mt-3 mb-0">
            <span class="cobertura cobertura--ok"><i class="bi bi-check-circle-fill" aria-hidden="true"></i>Cubierto</span> alcanza la dotación mínima ·
            <span class="cobertura cobertura--parcial"><i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>Faltan</span> hay asignados pero no alcanza ·
            <span class="cobertura cobertura--vacio"><i class="bi bi-x-circle-fill" aria-hidden="true"></i>Sin cubrir</span> nadie asignado.
        </p>
        <?php endif; ?>
    </div>
</div>
<?php if ($sector !== null && $tipos !== []): ?>
    <!-- Modal: agregar personal a una celda (HU-14). Envía por AJAX y aplica las validaciones de HU-13. -->
    <div class="modal fade" id="modalAsignar" tabindex="-1" aria-labelledby="tituloModalAsignar" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content" action="<?= base_url('admin/asignaciones/crear') ?>" method="post" data-ajax-asignar novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="sector_id" value="">
                <input type="hidden" name="tipo_turno_id" value="">
                <input type="hidden" name="fecha" value="">
                <div class="modal-header">
                    <h2 class="modal-title h6" id="tituloModalAsignar"><i class="bi bi-calendar-plus me-1" aria-hidden="true"></i>Asignar turno</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-3" data-descripcion-turno></p>
                    <div class="alerta alerta--error mb-3 d-none" role="alert" data-errores-modal></div>
                    <div class="form-floating">
                        <select class="form-select" id="modal_usuario_id" name="usuario_id" required>
                            <option value="" selected disabled>Elija un enfermero/a</option>
                            <?php foreach ($enfermeros as $e): ?>
                                <option value="<?= (int) $e->id ?>">
                                    <?= esc($e->getNombreCompleto()) ?> · <?= esc(\App\Models\AsignacionModel::horasTexto($minutosSemana[(int) $e->id] ?? 0)) ?> de <?= (int) $e->carga_horaria_max ?> h esta semana
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <label for="modal_usuario_id">Enfermero/a *</label>
                    </div>
                    <p class="form-text mb-0">Se controla que no tenga otro turno superpuesto ni supere su carga horaria semanal.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-tp">Asignar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: quitar a un enfermero/a de un turno (baja lógica de la asignación) -->
    <div class="modal fade" id="modalQuitar" tabindex="-1" aria-labelledby="tituloModalQuitar" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content" action="" method="post" data-ajax-quitar data-base="<?= base_url('admin/asignaciones') ?>" novalidate>
                <?= csrf_field() ?>
                <div class="modal-header">
                    <h2 class="modal-title h6" id="tituloModalQuitar"><i class="bi bi-x-circle me-1" aria-hidden="true"></i>Quitar del turno</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-2">¿Quitar a <strong data-descripcion-turno></strong>?</p>
                    <p class="small text-body-secondary mb-0">El sector puede quedar por debajo de su dotación mínima.</p>
                    <div class="alerta alerta--error mt-3 d-none" role="alert" data-errores-modal></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger">Quitar</button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>
<?= $this->endSection() ?>
