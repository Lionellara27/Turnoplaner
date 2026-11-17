<?php
/**
 * HU-14: Calendario mensual de turnos por zona/sector (Planificador).
 *
 * @var list<\App\Entities\Sector>                                   $sectores
 * @var list<\App\Entities\TipoTurno>                                $tipos
 * @var \App\Entities\Sector|null                                    $sector
 * @var string                                                       $anioMes      YYYY-MM
 * @var string                                                       $nombreMes    "Noviembre 2026"
 * @var list<list<string>>                                           $semanas      matriz de semanas (Lunes..Domingo)
 * @var string                                                       $anteriorMes, $siguienteMes, $hoy, $fechaMaxima, $ahora
 * @var array<string, array<int, list<\App\Entities\Asignacion>>>    $grilla
 * @var list<\App\Entities\Usuario>                                $enfermeros
 * @var array<int, array{total: int, extra: int}>                    $resumenMes
 */
$enfermeros = $enfermeros ?? [];
$resumenMes = $resumenMes ?? [];
$mesActual  = date('Y-m');
$urlMes     = static fn (string $mes, ?int $secId = null): string => base_url('admin/planificador') . '?' . http_build_query([
    'sector' => $secId ?? $sector?->id,
    'mes'    => $mes,
], '', '&amp;');
$esMesActual = $anioMes === $mesActual;
?>
<?= $this->extend('layouts/main') ?>

<?= $this->section('contenido') ?>
<div class="tp-panel-card overflow-hidden">

    <div class="card-header tp-card-header py-3 px-4">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="d-flex align-items-center">
                <div class="me-3"><img src="<?= base_url('img/logo-64.png') ?>" alt="Logo TurnoPlaner" class="barra__logo"></div>
                <div>
                    <h1 class="h4 mb-0 fw-semibold text-white">Calendario de turnos · <?= esc($nombreMes) ?></h1>
                    <p class="mb-0 small subtitulo">
                        <?= $sector !== null ? 'Zona: ' . esc($sector->nombre) . ' · Dotación mínima: ' . esc($sector->dotacionTexto()) : 'Sin zonas configuradas' ?>
                    </p>
                </div>
            </div>
            <?php if ($sector !== null && $tipos !== []): ?>
                <a href="<?= base_url('admin/asignaciones/crear') . '?' . http_build_query(['sector' => $sector->id], '', '&amp;') ?>" class="btn btn-light text-nowrap fw-semibold shadow-sm rounded-3">
                    <i class="bi bi-calendar-plus me-1" aria-hidden="true"></i>Asignar turno
                </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="card-body p-3 p-md-4 bg-white">

        <?php if ($sectores === []): ?>
            <div class="text-center py-5 border rounded-3">
                <i class="bi bi-calendar-x fs-1 text-body-secondary d-block mb-2" aria-hidden="true"></i>
                <p class="fw-semibold mb-2">Todavía no hay zonas/sectores cargados.</p>
                <a href="<?= base_url('admin/sectores/crear') ?>" class="btn btn-tp btn-sm">Crear primer sector</a>
            </div>
        <?php else: ?>

            <!-- Botones de selección rápida de Zona/Sector + Navegación de Mes -->
            <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-3 pb-3 border-bottom">
                <div>
                    <span class="d-block small fw-semibold text-body-secondary mb-1">
                        <i class="bi bi-hospital me-1" aria-hidden="true"></i>Zona de trabajo:
                    </span>
                    <div class="d-flex flex-wrap gap-2" role="group" aria-label="Seleccionar zona de trabajo">
                        <?php foreach ($sectores as $s): ?>
                            <?php $activoSec = $sector !== null && (int) $s->id === (int) $sector->id; ?>
                            <a href="<?= $urlMes($anioMes, (int) $s->id) ?>"
                               class="btn btn-sm rounded-3 <?= $activoSec ? 'btn-tp fw-semibold shadow-sm' : 'btn-outline-secondary' ?>"
                               <?= $activoSec ? 'aria-current="page"' : '' ?>>
                                <?= esc($s->nombre) ?>
                                <?php if ($s->esCritico()): ?>
                                    <span class="badge rounded-pill <?= $activoSec ? 'bg-white text-dark' : 'bg-danger-subtle text-danger-emphasis' ?> ms-1">Crítica</span>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <nav aria-label="Cambiar de mes" class="align-self-stretch align-self-lg-end">
                    <div class="btn-group w-100 w-lg-auto" role="group">
                        <a class="btn btn-outline-secondary btn-sm" href="<?= $urlMes($anteriorMes) ?>">
                            <i class="bi bi-chevron-left" aria-hidden="true"></i> Mes anterior
                        </a>
                        <a class="btn btn-outline-secondary btn-sm<?= $esMesActual ? ' active' : '' ?>" href="<?= $urlMes($mesActual) ?>" <?= $esMesActual ? 'aria-current="true"' : '' ?>>
                            Mes actual
                        </a>
                        <a class="btn btn-outline-secondary btn-sm" href="<?= $urlMes($siguienteMes) ?>">
                            Mes siguiente <i class="bi bi-chevron-right" aria-hidden="true"></i>
                        </a>
                    </div>
                </nav>
            </div>

            <?php if ($tipos === []): ?>
                <div class="text-center py-5 border rounded-3">
                    <i class="bi bi-clock fs-1 text-body-secondary d-block mb-2" aria-hidden="true"></i>
                    <p class="fw-semibold mb-1">La zona «<?= esc($sector->nombre) ?>» todavía no tiene horarios (tipos de turno) creados.</p>
                    <p class="small text-body-secondary mb-3">Cree los turnos correspondientes a esta zona para comenzar a asignar enfermeros.</p>
                    <a href="<?= base_url('admin/tipos-turno/crear') ?>" class="btn btn-tp btn-sm">Crear tipo de turno para <?= esc($sector->nombre) ?></a>
                </div>
            <?php else: ?>

                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 small text-body-secondary mb-3">
                    <span>
                        <i class="bi bi-info-circle me-1" style="color: var(--color-primario-oscuro);" aria-hidden="true"></i>
                        Hacé clic en <strong>+</strong> para asignar un enfermero/a o en su nombre para reasignar.
                        Planificación habilitada hasta el <strong><?= esc(fecha_ar($fechaMaxima)) ?></strong>.
                    </span>
                    <div class="d-flex flex-wrap gap-2">
                        <span class="tp-turno-pill__cupo tp-cupo--ok"><i class="bi bi-check-circle-fill me-1"></i>Cubierto</span>
                        <span class="tp-turno-pill__cupo tp-cupo--parcial"><i class="bi bi-exclamation-circle-fill me-1"></i>Parcial</span>
                        <span class="tp-turno-pill__cupo tp-cupo--vacio"><i class="bi bi-x-circle-fill me-1"></i>Sin cubrir</span>
                        <span class="tp-turno-pill__cupo" style="background: #fbf3dc; color: #6d5016; border: 1px solid rgba(109,80,22,0.25);">HE = Hora extra</span>
                    </div>
                </div>

                <!-- Calendario Mensual (Escritorio) -->
                <div class="d-none d-lg-block rounded-3 border overflow-hidden">
                    <table class="table table-bordered align-top mb-0 tp-calendario">
                        <caption class="visually-hidden">Calendario mensual de <?= esc($sector->nombre) ?>, <?= esc($nombreMes) ?></caption>
                        <thead class="text-center">
                            <tr>
                                <th scope="col">Lunes</th>
                                <th scope="col">Martes</th>
                                <th scope="col">Miércoles</th>
                                <th scope="col">Jueves</th>
                                <th scope="col">Viernes</th>
                                <th scope="col" style="color: var(--color-primario-oscuro);">Sábado</th>
                                <th scope="col" style="color: var(--color-primario-oscuro);">Domingo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($semanas as $semana): ?>
                                <tr>
                                    <?php foreach ($semana as $dia): ?>
                                        <?php
                                        $perteneceMes = substr($dia, 0, 7) === $anioMes;
                                        $esHoy        = $dia === $hoy;
                                        $esPasado     = $dia < $hoy;
                                        $claseCelda   = ! $perteneceMes ? 'tp-celda-dia--fuera'
                                            : ($esHoy ? 'tp-celda-dia--hoy' : ($esPasado ? 'bg-light-subtle' : 'bg-white'));
                                        ?>
                                        <td class="p-2 <?= $claseCelda ?>" <?= $esHoy ? 'aria-current="date"' : '' ?>>
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <div class="d-flex align-items-center gap-1">
                                                    <span class="tp-num-dia">
                                                        <?= (int) substr($dia, 8, 2) ?><?= ! $perteneceMes ? '/' . substr($dia, 5, 2) : '' ?>
                                                    </span>
                                                    <?php if ($esHoy): ?>
                                                        <span class="badge rounded-pill text-uppercase" style="background: var(--color-primario-oscuro); font-size: 0.62rem;">Hoy</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>

                                            <?php if ($perteneceMes): ?>
                                                <div class="d-flex flex-column gap-1">
                                                    <?php foreach ($tipos as $t): ?>
                                                        <?= view('admin/planificador/_celda', [
                                                            'sector'      => $sector,
                                                            'tipo'        => $t,
                                                            'dia'         => $dia,
                                                            'asignados'   => $grilla[$dia][(int) $t->id] ?? [],
                                                            'ahora'       => $ahora,
                                                            'fechaMaxima' => $fechaMaxima,
                                                        ]) ?>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php else: ?>
                                                <div class="small text-body-secondary text-center py-4 opacity-50">—</div>
                                            <?php endif; ?>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Vista Móvil: lista cronológica de días del mes -->
                <div class="d-lg-none">
                    <?php foreach ($semanas as $semana): ?>
                        <?php foreach ($semana as $dia): ?>
                            <?php if (substr($dia, 0, 7) !== $anioMes) continue; ?>
                            <?php $esHoy = $dia === $hoy; $esPasado = $dia < $hoy; ?>
                            <div class="border rounded-3 mb-2 <?= $esHoy ? 'tp-celda-dia--hoy' : '' ?>">
                                <div class="px-3 py-2 border-bottom d-flex justify-content-between align-items-center" style="background: #f1f5f3;">
                                    <span class="fw-semibold text-capitalize">
                                        <?= esc(dia_semana($dia)) ?> <?= esc(fecha_ar($dia)) ?>
                                    </span>
                                    <?php if ($esHoy): ?>
                                        <span class="badge rounded-pill" style="background: var(--color-primario-oscuro);">Hoy</span>
                                    <?php elseif ($esPasado): ?>
                                        <span class="small text-body-secondary">Pasado</span>
                                    <?php endif; ?>
                                </div>
                                <div class="p-2 d-flex flex-column gap-1">
                                    <?php foreach ($tipos as $t): ?>
                                        <?= view('admin/planificador/_celda', [
                                            'sector'      => $sector,
                                            'tipo'        => $t,
                                            'dia'         => $dia,
                                            'asignados'   => $grilla[$dia][(int) $t->id] ?? [],
                                            'ahora'       => $ahora,
                                            'fechaMaxima' => $fechaMaxima,
                                        ]) ?>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </div>

                <!-- Resumen mensual de carga horaria y horas extras cubiertas por enfermero/a -->
                <section class="mt-4 pt-3 border-top" aria-labelledby="titulo-horas-mes">
                    <h2 class="h6 fw-semibold mb-1" id="titulo-horas-mes">
                        <i class="bi bi-hourglass-split me-1" style="color: var(--color-primario-oscuro);" aria-hidden="true"></i>Carga horaria del mes (<?= esc($nombreMes) ?>)
                    </h2>
                    <p class="small text-body-secondary mb-2">Horas totales y horas extras cubiertas por cada enfermero/a activo en todos los sectores.</p>
                    <?php if ($enfermeros === []): ?>
                        <p class="small text-body-secondary mb-0">No hay enfermeros/as activos.</p>
                    <?php else: ?>
                        <ul class="list-unstyled row row-cols-1 row-cols-md-2 row-cols-xl-3 g-2 mb-0">
                            <?php foreach ($enfermeros as $e): ?>
                                <?php
                                $datosE = $resumenMes[(int) $e->id] ?? ['total' => 0, 'extra' => 0];
                                ?>
                                <li class="col">
                                    <div class="tp-enfermero-card h-100 d-flex justify-content-between align-items-center gap-2">
                                        <div>
                                            <div class="fw-semibold small"><?= esc($e->getNombreCompleto()) ?></div>
                                            <div class="small text-body-secondary">Tope semanal: <?= (int) $e->carga_horaria_max ?> h</div>
                                        </div>
                                        <div class="text-end">
                                            <span class="badge rounded-pill" style="background: #f1f5f3; color: var(--color-texto); border: 1px solid var(--color-borde);">
                                                Total: <?= esc(\App\Models\AsignacionModel::horasTexto($datosE['total'])) ?>
                                            </span>
                                            <?php if ($datosE['extra'] > 0): ?>
                                                <span class="badge rounded-pill d-block mt-1" style="background: #fbf3dc; color: #6d5016; border: 1px solid rgba(109,80,22,0.25);">
                                                    Extras: <?= esc(\App\Models\AsignacionModel::horasTexto($datosE['extra'])) ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </section>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php if ($sector !== null && $tipos !== []): ?>
    <!-- Modal: agregar personal a una celda (AJAX) -->
    <div class="modal fade" id="modalAsignar" tabindex="-1" aria-labelledby="tituloModalAsignar" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content border-0 shadow-lg rounded-4 overflow-hidden" action="<?= base_url('admin/asignaciones/crear') ?>" method="post" data-ajax-asignar novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="sector_id" value="">
                <input type="hidden" name="tipo_turno_id" value="">
                <input type="hidden" name="fecha" value="">
                <div class="modal-header border-bottom" style="background: #f1f5f3;">
                    <h2 class="modal-title h6 fw-bold" id="tituloModalAsignar"><i class="bi bi-calendar-plus me-1" style="color: var(--color-primario-oscuro);" aria-hidden="true"></i>Asignar turno</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-3" data-descripcion-turno></p>
                    <div class="alerta alerta--error mb-3 d-none" role="alert" data-errores-modal></div>
                    <div class="form-floating">
                        <select class="form-select" id="modal_usuario_id" name="usuario_id" required>
                            <option value="" selected disabled>Elija un enfermero/a</option>
                            <?php foreach ($enfermeros as $e): ?>
                                <?php $minMes = $resumenMes[(int) $e->id]['total'] ?? 0; ?>
                                <option value="<?= (int) $e->id ?>">
                                    <?= esc($e->getNombreCompleto()) ?> · <?= esc(\App\Models\AsignacionModel::horasTexto($minMes)) ?> en el mes (tope <?= (int) $e->carga_horaria_max ?> h/sem)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <label for="modal_usuario_id">Enfermero/a *</label>
                    </div>
                    <p class="form-text mb-0">
                        Tienen prioridad quienes no completaron su carga semanal; si ya la cumplieron, el turno se registrará como <strong>hora extra cubierta</strong>.
                    </p>
                </div>
                <div class="modal-footer border-top" style="background: #f8faf9;">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-tp fw-semibold">Asignar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: quitar a un enfermero/a de un turno -->
    <div class="modal fade" id="modalQuitar" tabindex="-1" aria-labelledby="tituloModalQuitar" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content border-0 shadow-lg rounded-4 overflow-hidden" action="" method="post" data-ajax-quitar data-base="<?= base_url('admin/asignaciones') ?>" novalidate>
                <?= csrf_field() ?>
                <div class="modal-header border-bottom" style="background: #f1f5f3;">
                    <h2 class="modal-title h6 fw-bold" id="tituloModalQuitar"><i class="bi bi-x-circle me-1 text-danger" aria-hidden="true"></i>Quitar del turno</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-2">¿Quitar a <strong data-descripcion-turno></strong>?</p>
                    <p class="small text-body-secondary mb-0">El sector puede quedar por debajo de su dotación mínima.</p>
                    <div class="alerta alerta--error mt-3 d-none" role="alert" data-errores-modal></div>
                </div>
                <div class="modal-footer border-top" style="background: #f8faf9;">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger fw-semibold">Quitar</button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>
<?= $this->endSection() ?>