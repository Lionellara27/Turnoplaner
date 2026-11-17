<?php
/**
 * HU-17: Calendario mensual personal del enfermero/a (pantalla inicial del rol ENFERMERO).
 * Privacidad: muestra exclusivamente los turnos del usuario autenticado.
 *
 * @var string                                        $vista, $periodo, $desde, $hasta, $anterior, $siguiente
 * @var bool                                          $esActual
 * @var string                                        $hoy, $manana, $ahora
 * @var list<string>                                  $dias
 * @var list<list<string>>                            $semanas
 * @var array<string, list<\App\Entities\Asignacion>> $porDia
 * @var array<string, \App\Entities\Asignacion>       $salidas
 * @var int                                           $cantidad, $minutos, $minutosExtra
 * @var \App\Entities\Asignacion|null                 $enCurso, $siguienteTurno
 * @var list<\App\Entities\Asignacion>                $turnosHoy, $turnosManana
 */
$semanas      = $semanas ?? [];
$minutosExtra = $minutosExtra ?? 0;
$anioMes      = substr($desde, 0, 7);
$urlNav       = static fn (string $fecha, string $v = ''): string => base_url('mi-cronograma') . '?' . http_build_query([
    'vista' => $v !== '' ? $v : $vista,
    'fecha' => $fecha,
], '', '&amp;');
$pieza = static fn ($t, string $parte): string => view('cronograma/_turno', ['t' => $t, 'ahora' => $ahora, 'parte' => $parte]);
?>
<?= $this->extend('layouts/main') ?>

<?= $this->section('contenido') ?>
<div class="card shadow-sm border-0 rounded-3 overflow-hidden">

    <div class="card-header tp-card-header py-3 px-4">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="d-flex align-items-center">
                <div class="me-3"><img src="<?= base_url('img/logo-64.png') ?>" alt="Logo TurnoPlaner" class="barra__logo"></div>
                <div>
                    <h1 class="h4 mb-0 fw-semibold text-white">Mi calendario de turnos</h1>
                    <p class="mb-0 small subtitulo"><?= esc($periodo) ?> · <?= $cantidad === 1 ? '1 turno asignado' : "{$cantidad} turnos asignados" ?></p>
                </div>
            </div>
            <div class="text-end text-white small">
                <span class="badge bg-light text-dark fw-semibold">
                    Total del período: <?= esc(\App\Models\AsignacionModel::horasTexto($minutos)) ?>
                </span>
                <?php if ($minutosExtra > 0): ?>
                    <span class="badge bg-warning text-dark fw-semibold ms-1">
                        Horas extras: <?= esc(\App\Models\AsignacionModel::horasTexto($minutosExtra)) ?>
                    </span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="card-body p-3 p-md-4 bg-white">

        <!-- Resumen rápido Hoy / Mañana / Próximo -->
        <?php if ($enCurso !== null || $turnosHoy !== [] || $turnosManana !== [] || $siguienteTurno !== null): ?>
            <div class="row g-2 mb-4">
                <?php if ($enCurso !== null): ?>
                    <div class="col-12 col-md-6">
                        <div class="border border-success rounded-3 p-3 bg-success-subtle bg-opacity-25 h-100">
                            <div class="small fw-semibold text-success-emphasis mb-1"><i class="bi bi-play-circle-fill me-1" aria-hidden="true"></i>Turno en curso ahora</div>
                            <div class="fw-bold"><?= esc((string) $enCurso->sector_nombre) ?> · <?= $pieza($enCurso, 'etiqueta') ?></div>
                            <div class="small mt-1"><?= $pieza($enCurso, 'horario') ?></div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($turnosHoy !== []): ?>
                    <div class="col-12 col-md-6">
                        <div class="border rounded-3 p-3 bg-light h-100">
                            <div class="small fw-semibold text-body-secondary mb-1">Hoy (<?= esc(fecha_ar($hoy)) ?>)</div>
                            <?php foreach ($turnosHoy as $t): ?>
                                <div class="d-flex justify-content-between align-items-center flex-wrap gap-1 mt-1">
                                    <span><strong><?= esc((string) $t->sector_nombre) ?></strong> · <?= $pieza($t, 'etiqueta') ?></span>
                                    <span class="small"><?= $pieza($t, 'horario') ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($turnosManana !== []): ?>
                    <div class="col-12 col-md-6">
                        <div class="border rounded-3 p-3 bg-light h-100">
                            <div class="small fw-semibold text-body-secondary mb-1">Mañana (<?= esc(fecha_ar($manana)) ?>)</div>
                            <?php foreach ($turnosManana as $t): ?>
                                <div class="d-flex justify-content-between align-items-center flex-wrap gap-1 mt-1">
                                    <span><strong><?= esc((string) $t->sector_nombre) ?></strong> · <?= $pieza($t, 'etiqueta') ?></span>
                                    <span class="small"><?= $pieza($t, 'horario') ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($siguienteTurno !== null): ?>
                    <div class="col-12">
                        <div class="border rounded-3 p-3 bg-light">
                            <span class="small fw-semibold text-body-secondary me-2">Próximo turno programado:</span>
                            <strong class="text-capitalize"><?= esc(dia_semana((string) $siguienteTurno->fecha)) ?> <?= esc(fecha_ar((string) $siguienteTurno->fecha)) ?></strong>
                            · <?= esc((string) $siguienteTurno->sector_nombre) ?> · <?= $pieza($siguienteTurno, 'etiqueta') ?> (<?= $pieza($siguienteTurno, 'horario') ?>)
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Controles de navegación y cambio de vista -->
        <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2 mb-3">
            <div class="btn-group" role="group" aria-label="Tipo de vista">
                <a href="<?= $urlNav($desde, 'mes') ?>" class="btn btn-sm <?= $vista === 'mes' ? 'btn-tp fw-semibold' : 'btn-outline-secondary' ?>">
                    <i class="bi bi-calendar3 me-1" aria-hidden="true"></i>Calendario mensual
                </a>
                <a href="<?= $urlNav($desde, 'semana') ?>" class="btn btn-sm <?= $vista === 'semana' ? 'btn-tp fw-semibold' : 'btn-outline-secondary' ?>">
                    <i class="bi bi-list-week me-1" aria-hidden="true"></i>Vista semanal
                </a>
            </div>

            <nav aria-label="Navegar período">
                <div class="btn-group w-100 w-sm-auto" role="group">
                    <a class="btn btn-outline-secondary btn-sm" href="<?= $urlNav($anterior) ?>">
                        <i class="bi bi-chevron-left" aria-hidden="true"></i> Anterior
                    </a>
                    <a class="btn btn-outline-secondary btn-sm<?= $esActual ? ' active' : '' ?>" href="<?= $urlNav($hoy) ?>">
                        Actual
                    </a>
                    <a class="btn btn-outline-secondary btn-sm" href="<?= $urlNav($siguiente) ?>">
                        Siguiente <i class="bi bi-chevron-right" aria-hidden="true"></i>
                    </a>
                </div>
            </nav>
        </div>

        <?php if ($vista === 'mes' && $semanas !== []): ?>
            <!-- Calendario Mensual Personal (Escritorio) -->
            <div class="table-responsive d-none d-lg-block">
                <table class="table table-bordered align-top mb-0" style="table-layout: fixed;">
                    <caption class="visually-hidden">Mi calendario mensual, <?= esc($periodo) ?></caption>
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
                                    $turnosDia    = $perteneceMes ? ($porDia[$dia] ?? []) : [];
                                    $notaSalida   = ($perteneceMes && isset($salidas[$dia])) ? 'Sale de ' . esc((string) $salidas[$dia]->tipo_nombre) . ' ' . esc($salidas[$dia]->horaFin()) : '';
                                    $claseCelda   = ! $perteneceMes ? 'bg-light text-body-secondary opacity-75'
                                        : ($esHoy ? 'border-primary border-2 bg-primary-subtle bg-opacity-10' : ($turnosDia !== [] ? 'bg-white' : 'bg-light-subtle'));
                                    ?>
                                    <td class="p-2 <?= $claseCelda ?>" style="height: 115px;" <?= $esHoy ? 'aria-current="date"' : '' ?>>
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <span class="fw-bold small <?= $esHoy ? 'badge bg-primary' : '' ?>">
                                                <?= (int) substr($dia, 8, 2) ?>
                                            </span>
                                            <?php if ($esHoy): ?><span class="small fw-semibold text-primary">Hoy</span><?php endif; ?>
                                        </div>

                                        <?php if ($notaSalida !== ''): ?>
                                            <div class="small text-body-secondary mb-1" style="font-size: 0.72rem;">
                                                <i class="bi bi-moon-stars me-1" aria-hidden="true"></i><?= $notaSalida ?>
                                            </div>
                                        <?php endif; ?>

                                        <?php if ($perteneceMes): ?>
                                            <?php if ($turnosDia === []): ?>
                                                <span class="small text-body-secondary" style="font-size: 0.75rem;">Franco / Sin turno</span>
                                            <?php else: ?>
                                                <?php foreach ($turnosDia as $t): ?>
                                                    <div class="border rounded-2 p-1 mb-1 bg-white shadow-2xs">
                                                        <div class="d-flex justify-content-between align-items-center gap-1">
                                                            <?= $pieza($t, 'etiqueta') ?>
                                                            <?php if ($t->esExtra()): ?>
                                                                <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle" style="font-size: 0.65rem;" title="Hora extra">HE</span>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="fw-semibold mt-1" style="font-size: 0.78rem;">
                                                            <i class="bi bi-hospital me-1" aria-hidden="true"></i><?= esc((string) $t->sector_nombre) ?>
                                                        </div>
                                                        <div class="text-body-secondary" style="font-size: 0.74rem;">
                                                            <i class="bi bi-clock me-1" aria-hidden="true"></i><?= $pieza($t, 'horario') ?>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Móvil en vista mes: reutiliza la agenda por día -->
            <div class="d-lg-none">
                <?php if ($dias === []): ?>
                    <div class="text-center py-4 border rounded-3 text-body-secondary">
                        No tenés turnos asignados en <?= esc($periodo) ?>.
                    </div>
                <?php else: ?>
                    <?= view('cronograma/_dias', [
                        'dias'    => $dias,
                        'porDia'  => $porDia,
                        'salidas' => $salidas,
                        'hoy'     => $hoy,
                        'ahora'   => $ahora,
                        'caption' => 'Mis turnos de ' . $periodo,
                    ]) ?>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <!-- Vista semanal / lista -->
            <?= view('cronograma/_dias', [
                'dias'    => $dias,
                'porDia'  => $porDia,
                'salidas' => $salidas,
                'hoy'     => $hoy,
                'ahora'   => $ahora,
                'caption' => 'Mis turnos de ' . $periodo,
            ]) ?>
        <?php endif; ?>

    </div>
</div>
<?= $this->endSection() ?>