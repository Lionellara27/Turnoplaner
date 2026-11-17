<?php
/**
 * HU-14: Calendario mensual de turnos por zona/sector (pantalla inicial del ADMIN).
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
<div class="card shadow-sm border-0 rounded-3 overflow-hidden">

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
                <a href="<?= base_url('admin/asignaciones/crear') . '?' . http_build_query(['sector' => $sector->id], '', '&amp;') ?>" class="btn btn-light text-nowrap fw-semibold shadow-sm">
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
                    <span class="d-block small fw-semibold text-body-secondary mb-1">Zona de trabajo:</span>
                    <div class="d-flex flex-wrap gap-2" role="group" aria-label="Seleccionar zona de trabajo">
                        <?php foreach ($sectores as $s): ?>
                            <?php $activoSec = $sector !== null && (int) $s->id === (int) $sector->id; ?>
                            <a href="<?= $urlMes($anioMes, (int) $s->id) ?>"
                               class="btn btn-sm <?= $activoSec ? 'btn-tp fw-semibold shadow-sm' : 'btn-outline-secondary' ?>"
                               <?= $activoSec ? 'aria-current="page"' : '' ?>>
                                <i class="bi bi-hospital me-1" aria-hidden="true"></i><?= esc($s->nombre) ?>
                                <?php if ($s->esCritico()): ?>
                                    <span class="badge <?= $activoSec ? 'bg-light text-dark' : 'bg-danger-subtle text-danger-emphasis' ?> ms-1">Crítica</span>
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
                        <i class="bi bi-info-circle me-1" aria-hidden="true"></i>
                        Hacé clic en <strong>+</strong> para asignar un enfermero/a o en su nombre para reasignar.
                        Planificación habilitada hasta el <strong><?= esc(fecha_ar($fechaMaxima)) ?></strong>.
                    </span>
                    <div class="d-flex flex-wrap gap-2">
                        <span class="badge bg-success-subtle text-success-emphasis border border-success-subtle">Cubierto</span>
                        <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle">Parcial</span>
                        <span class="badge bg-danger-subtle text-danger-emphasis border border-danger-subtle">Sin cubrir</span>
                        <span class="badge bg-warning-subtle text-warning-emphasis border">HE = Hora extra</span>
                    </div>
                </div>

                <!-- Calendario Mensual (Escritorio / Tablet horizontal) -->
                <div class="table-responsive d-none d-lg-block">
                    <table class="table table-bordered align-top mb-0" style="table-layout: fixed; min-width: 980px;">
                        <caption class="visually-hidden">Calendario mensual de <?= esc($sector->nombre) ?>, <?= esc($nombreMes) ?></caption>
                        <thead class="table-light text-center">
                            <tr>
                                <th scope="col">Lunes</th>
                                <th scope="col">Martes</th>
                                <th scope="col">Miércoles</th>
                                <th scope="col">Jueves</th>
                                <th scope="col">Viernes</th>
                                <th scope="col">Sábado</th>
                                <th scope="col">Domingo</th>
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
                                        $claseCelda   = ! $perteneceMes ? 'bg-light text-body-secondary opacity-75'
                                            : ($esHoy ? 'border-primary border-2 bg-primary-subtle bg-opacity-10' : ($esPasado ? 'bg-light-subtle' : ''));
                                        ?>
                                        <td class="p-2 <?= $claseCelda ?>" <?= $esHoy ? 'aria-current="date"' : '' ?>>
                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                <span class="fw-bold small <?= $esHoy ? 'badge bg-primary' : '' ?>">
                                                    <?= (int) substr($dia, 8, 2) ?><?= ! $perteneceMes ? '/' . substr($dia, 5, 2) : '' ?>
                                                </span>
                                                <?php if ($esHoy): ?>
                                                    <span class="small fw-semibold text-primary">Hoy</span>
                                                <?php endif; ?>
                                            </div>

                                            <?php if ($perteneceMes): ?>
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
                                            <?php else: ?>
                                                <div class="small text-body-secondary text-center py-3">—</div>
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
                            <div class="border rounded-3 mb-2 <?= $esHoy ? 'border-primary border-2' : '' ?>">
                                <div class="px-3 py-2 bg-light border-bottom d-flex justify-content-between align-items-center">
                                    <span class="fw-semibold text-capitalize">
                                        <?= esc(dia_semana($dia)) ?> <?= esc(fecha_ar($dia)) ?>
                                    </span>
                                    <?php if ($esHoy): ?>
                                        <span class="badge bg-primary">Hoy</span>
                                    <?php elseif ($esPasado): ?>
                                        <span class="small text-body-secondary">Pasado</span>
                                    <?php endif; ?>
                                </div>
                                <div class="p-2">
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
                        <i class="bi bi-hourglass-split me-1" aria-hidden="true"></i>Carga horaria del mes (<?= esc($nombreMes) ?>)
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
                                    <div class="border rounded-3 p-2 h-100 d-flex justify-content-between align-items-center gap-2">
                                        <div>
                                            <div class="fw-semibold small"><?= esc($e->getNombreCompleto()) ?></div>
                                            <div class="small text-body-secondary">Tope semanal: <?= (int) $e->carga_horaria_max ?> h</div>
                                        </div>
                                        <div class="text-end">
                                            <span class="badge bg-light text-dark border">
                                                Total: <?= esc(\App\Models\AsignacionModel::horasTexto($datosE['total'])) ?>
                                            </span>
                                            <?php if ($datosE['extra'] > 0): ?>
                                                <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle d-block mt-1">
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
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-tp">Asignar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: quitar a un enfermero/a de un turno -->
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