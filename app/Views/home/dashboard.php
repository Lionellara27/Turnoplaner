<?php
/**
 * Panel general del Administrador (HU-02):
 * Métricas del plantel, alertas de cobertura y calendario mensual de solo visualización por zona.
 *
 * @var string                                                       $nombre
 * @var int                                                          $activos, $pendientes, $bajas
 * @var list<\App\Entities\Sector>                                   $sectores
 * @var \App\Entities\Sector|null                                    $sector
 * @var list<\App\Entities\TipoTurno>                                $tipos
 * @var string                                                       $anioMes, $nombreMes, $anteriorMes, $siguienteMes, $hoy
 * @var list<list<string>>                                           $semanas
 * @var array<string, array<int, list<\App\Entities\Asignacion>>>    $grilla
 * @var list<array{fecha:string, turno:string, horario:string, cubiertos:int, minimo:int}> $alertasCobertura
 */
$mesActual   = date('Y-m');
$esMesActual = $anioMes === $mesActual;
$urlInicio   = static fn (string $mes, ?int $secId = null): string => base_url('inicio') . '?' . http_build_query([
    'sector' => $secId ?? $sector?->id,
    'mes'    => $mes,
], '', '&amp;');
?>
<?= $this->extend('layouts/main') ?>

<?= $this->section('contenido') ?>
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h1 class="h3 fw-semibold mb-1">Hola, <?= esc($nombre) ?></h1>
        <p class="text-body-secondary mb-0">Panel de gestión y visualización mensual</p>
    </div>
    <a href="<?= base_url('admin/usuarios/crear') ?>" class="btn btn-tp px-4 py-2 fw-semibold shadow-sm">
        <i class="bi bi-person-plus me-1" aria-hidden="true"></i>Dar de alta enfermero/a
    </a>
</div>

<h2 class="visually-hidden">Resumen del plantel</h2>
<section class="row g-3 mb-4" aria-label="Resumen del plantel">
    <div class="col-12 col-md-4">
        <a href="<?= base_url('admin/usuarios') ?>" class="card shadow-sm border-0 tarjeta-indicador indicador--ok text-decoration-none h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <span class="indicador__icono" aria-hidden="true"><i class="bi bi-person-check"></i></span>
                <div>
                    <div class="numero text-dark"><?= esc((string) $activos) ?></div>
                    <div class="text-body-secondary">Enfermeros/as activos</div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-12 col-md-4">
        <a href="<?= base_url('admin/usuarios?estado=PENDIENTE') ?>" class="card shadow-sm border-0 tarjeta-indicador indicador--pendiente text-decoration-none h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <span class="indicador__icono" aria-hidden="true"><i class="bi bi-hourglass-split"></i></span>
                <div>
                    <div class="numero text-dark"><?= esc((string) $pendientes) ?></div>
                    <div class="text-body-secondary">Pendientes de activación</div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-12 col-md-4">
        <a href="<?= base_url('admin/usuarios?estado=BAJA') ?>" class="card shadow-sm border-0 tarjeta-indicador indicador--baja text-decoration-none h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <span class="indicador__icono" aria-hidden="true"><i class="bi bi-person-slash"></i></span>
                <div>
                    <div class="numero text-dark"><?= esc((string) $bajas) ?></div>
                    <div class="text-body-secondary">Dados de baja</div>
                </div>
            </div>
        </a>
    </div>
</section>

<!-- Alertas de cobertura del sector en el mes -->
<?php if ($sector !== null && $alertasCobertura !== []): ?>
    <div class="alert alert-warning border-warning-subtle shadow-sm d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4" role="status">
        <div>
            <div class="fw-semibold">
                <i class="bi bi-exclamation-triangle-fill me-1" aria-hidden="true"></i>
                Atención: hay <?= count($alertasCobertura) ?> <?= count($alertasCobertura) === 1 ? 'turno sin dotación mínima' : 'turnos sin dotación mínima' ?> en <?= esc($sector->nombre) ?> durante <?= esc($nombreMes) ?>
            </div>
            <div class="small mt-1">
                Próximos descubiertos:
                <?php foreach (array_slice($alertasCobertura, 0, 3) as $idx => $al): ?>
                    <?= $idx > 0 ? ' · ' : '' ?>
                    <strong><?= esc(fecha_ar($al['fecha'])) ?></strong> (<?= esc($al['turno']) ?>: <?= $al['cubiertos'] ?>/<?= $al['minimo'] ?>)
                <?php endforeach; ?>
                <?= count($alertasCobertura) > 3 ? '…' : '' ?>
            </div>
        </div>
        <a href="<?= base_url('admin/planificador') . '?' . http_build_query(['sector' => $sector->id], '', '&amp;') ?>" class="btn btn-sm btn-warning fw-semibold text-nowrap">
            <i class="bi bi-calendar-week me-1" aria-hidden="true"></i>Ir a planificar
        </a>
    </div>
<?php endif; ?>

<!-- Calendario Mensual de solo visualización por zona -->
<section class="card shadow-sm border-0 rounded-3 overflow-hidden" aria-labelledby="titulo-calendario-inicio">
    <div class="card-header bg-white border-bottom py-3 px-4">
        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
            <div>
                <h2 class="h5 fw-semibold mb-0" id="titulo-calendario-inicio">
                    <i class="bi bi-calendar3 me-1 text-success" aria-hidden="true"></i>Cronograma mensual · <?= esc($nombreMes) ?>
                </h2>
                <p class="small text-body-secondary mb-0">
                    Vista general de personal asignado por zona (solo lectura)
                </p>
            </div>

            <div class="d-flex flex-wrap align-items-center gap-2">
                <div class="btn-group" role="group" aria-label="Cambiar mes">
                    <a class="btn btn-outline-secondary btn-sm" href="<?= $urlInicio($anteriorMes) ?>">
                        <i class="bi bi-chevron-left" aria-hidden="true"></i> Anterior
                    </a>
                    <a class="btn btn-outline-secondary btn-sm<?= $esMesActual ? ' active' : '' ?>" href="<?= $urlInicio($mesActual) ?>">
                        Mes actual
                    </a>
                    <a class="btn btn-outline-secondary btn-sm" href="<?= $urlInicio($siguienteMes) ?>">
                        Siguiente <i class="bi bi-chevron-right" aria-hidden="true"></i>
                    </a>
                </div>
                <?php if ($sector !== null): ?>
                    <a href="<?= base_url('admin/planificador') . '?' . http_build_query(['sector' => $sector->id], '', '&amp;') ?>" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-pencil-square me-1" aria-hidden="true"></i>Gestionar en Planificador
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($sectores !== []): ?>
            <div class="mt-3 pt-3 border-top d-flex flex-wrap align-items-center gap-2" role="group" aria-label="Seleccionar zona">
                <span class="small fw-semibold text-body-secondary me-1">Zona:</span>
                <?php foreach ($sectores as $s): ?>
                    <?php $activoSec = $sector !== null && (int) $s->id === (int) $sector->id; ?>
                    <a href="<?= $urlInicio($anioMes, (int) $s->id) ?>"
                       class="btn btn-sm <?= $activoSec ? 'btn-tp fw-semibold' : 'btn-outline-secondary' ?>"
                       <?= $activoSec ? 'aria-current="page"' : '' ?>>
                        <?= esc($s->nombre) ?>
                        <?php if ($s->esCritico()): ?>
                            <span class="badge <?= $activoSec ? 'bg-light text-dark' : 'bg-danger-subtle text-danger-emphasis' ?> ms-1">Crítica</span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="card-body p-3 p-md-4 bg-white">
        <?php if ($sector === null): ?>
            <p class="text-body-secondary text-center py-4 mb-0">No hay sectores activos configurados.</p>
        <?php elseif ($tipos === []): ?>
            <p class="text-body-secondary text-center py-4 mb-0">
                La zona <strong><?= esc($sector->nombre) ?></strong> no tiene horarios (tipos de turno) configurados todavía.
            </p>
        <?php else: ?>
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 small text-body-secondary mb-3">
                <span>Dotación mínima requerida en <strong><?= esc($sector->nombre) ?></strong>: <strong><?= esc($sector->dotacionTexto()) ?></strong>.</span>
                <div class="d-flex flex-wrap gap-2">
                    <span class="badge bg-success-subtle text-success-emphasis border border-success-subtle">Cubierto</span>
                    <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle">Incompleto</span>
                    <span class="badge bg-danger-subtle text-danger-emphasis border border-danger-subtle">Sin cubrir</span>
                    <span class="badge bg-warning-subtle text-warning-emphasis border">HE = Hora extra</span>
                </div>
            </div>

            <!-- Calendario mensual solo lectura (Escritorio) -->
            <div class="table-responsive d-none d-lg-block">
                <table class="table table-bordered align-top mb-0" style="table-layout: fixed; min-width: 960px;">
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
                                    $claseCelda   = ! $perteneceMes ? 'bg-light text-body-secondary opacity-75'
                                        : ($esHoy ? 'border-primary border-2 bg-primary-subtle bg-opacity-10' : '');
                                    ?>
                                    <td class="p-2 <?= $claseCelda ?>" <?= $esHoy ? 'aria-current="date"' : '' ?>>
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <span class="fw-bold small <?= $esHoy ? 'badge bg-primary' : '' ?>">
                                                <?= (int) substr($dia, 8, 2) ?>
                                            </span>
                                            <?php if ($esHoy): ?><span class="small fw-semibold text-primary">Hoy</span><?php endif; ?>
                                        </div>

                                        <?php if ($perteneceMes): ?>
                                            <?php foreach ($tipos as $t): ?>
                                                <?php
                                                $asignados = $grilla[$dia][(int) $t->id] ?? [];
                                                $activosT  = array_filter($asignados, static fn ($a): bool => ! $a->enfermeroDeBaja());
                                                $cant      = count($activosT);
                                                $min       = (int) $sector->dotacion_minima;
                                                $badgeCob  = $cant >= $min ? 'bg-success-subtle text-success-emphasis border-success-subtle'
                                                    : ($cant > 0 ? 'bg-warning-subtle text-warning-emphasis border-warning-subtle'
                                                    : 'bg-danger-subtle text-danger-emphasis border-danger-subtle');
                                                ?>
                                                <div class="border rounded-2 p-1 mb-1 bg-white">
                                                    <div class="d-flex justify-content-between align-items-center gap-1">
                                                        <span class="badge text-truncate" style="background-color: <?= esc($t->color, 'attr') ?>; color: <?= esc(\App\Entities\TipoTurno::textoSobre($t->color), 'attr') ?>; max-width: 115px;">
                                                            <?= esc($t->nombre) ?>
                                                        </span>
                                                        <span class="badge border <?= $badgeCob ?>" style="font-size: 0.68rem;">
                                                            <?= $cant ?>/<?= $min ?>
                                                        </span>
                                                    </div>
                                                    <?php if ($asignados === []): ?>
                                                        <div class="text-body-secondary mt-1" style="font-size: 0.72rem;">Sin asignar</div>
                                                    <?php else: ?>
                                                        <ul class="list-unstyled mb-0 mt-1" style="font-size: 0.76rem;">
                                                            <?php foreach ($asignados as $a): ?>
                                                                <li class="text-truncate border-top pt-1 mt-1">
                                                                    <?= esc($a->nombreEnfermero()) ?>
                                                                    <?php if ($a->esExtra()): ?>
                                                                        <span class="badge bg-warning-subtle text-warning-emphasis border" style="font-size: 0.62rem;">HE</span>
                                                                    <?php endif; ?>
                                                                </li>
                                                            <?php endforeach; ?>
                                                        </ul>
                                                    <?php endif; ?>
                                                </div>
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

            <!-- Vista Móvil (solo lectura) -->
            <div class="d-lg-none">
                <?php foreach ($semanas as $semana): ?>
                    <?php foreach ($semana as $dia): ?>
                        <?php if (substr($dia, 0, 7) !== $anioMes) continue; ?>
                        <?php $esHoy = $dia === $hoy; ?>
                        <div class="border rounded-3 mb-2 <?= $esHoy ? 'border-primary border-2' : '' ?>">
                            <div class="px-3 py-2 bg-light border-bottom d-flex justify-content-between align-items-center">
                                <span class="fw-semibold text-capitalize"><?= esc(dia_semana($dia)) ?> <?= esc(fecha_ar($dia)) ?></span>
                                <?php if ($esHoy): ?><span class="badge bg-primary">Hoy</span><?php endif; ?>
                            </div>
                            <div class="p-2">
                                <?php foreach ($tipos as $t): ?>
                                    <?php $asignados = $grilla[$dia][(int) $t->id] ?? []; ?>
                                    <div class="d-flex justify-content-between align-items-start border-bottom py-1">
                                        <div>
                                            <span class="badge" style="background-color: <?= esc($t->color, 'attr') ?>; color: <?= esc(\App\Entities\TipoTurno::textoSobre($t->color), 'attr') ?>;">
                                                <?= esc($t->nombre) ?>
                                            </span>
                                            <div class="small mt-1">
                                                <?= $asignados === [] ? '<span class="text-body-secondary">Sin asignar</span>' : implode(', ', array_map(static fn ($a): string => esc($a->nombreEnfermero()) . ($a->esExtra() ? ' (HE)' : ''), $asignados)) ?>
                                            </div>
                                        </div>
                                        <span class="badge bg-light text-dark border"><?= count($asignados) ?>/<?= (int) $sector->dotacion_minima ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
<?= $this->endSection() ?>