<?php
/**
 * HU-17: Mi Cronograma.
 *
 * @var string                                         $vista       'semana' | 'mes'
 * @var string                                         $periodo     "Semana del ... al ..." o "Noviembre 2026"
 * @var string                                         $desde, $hasta, $anterior, $siguiente, $hoy, $manana, $ahora
 * @var bool                                           $esActual
 * @var list<string>                                   $dias        fechas a mostrar
 * @var array<string, list<\App\Entities\Asignacion>>  $porDia
 * @var array<string, \App\Entities\Asignacion>        $salidas
 * @var int                                            $cantidad, $minutos
 * @var \App\Entities\Asignacion|null                  $enCurso, $siguienteTurno
 * @var list<\App\Entities\Asignacion>                 $turnosHoy, $turnosManana
 */
$fechaVista = $esActual ? $hoy : $desde;
$url   = static fn (string $v, string $f): string => base_url('mi-cronograma') . '?' . http_build_query(['vista' => $v, 'fecha' => $f], '', '&amp;');
$pieza = static fn ($t, string $parte): string => view('cronograma/_turno', ['t' => $t, 'ahora' => $ahora, 'parte' => $parte]);
// Un turno del resumen: etiqueta, horario y sector, apilados (sin "·" sueltos).
$resumen = static fn ($t): string => '<div class="d-flex flex-wrap align-items-center gap-2">' . $pieza($t, 'etiqueta')
    . '<span>' . $pieza($t, 'horario') . '</span>'
    . '<span><i class="bi bi-hospital me-1" aria-hidden="true"></i>' . esc((string) $t->sector_nombre) . '</span></div>';
$caption = 'Mis turnos, ' . mb_strtolower($periodo);

// Vista mes del mes actual: los días ya pasados se pliegan para que lo que viene quede arriba.
$pasados = [];
$resto   = $dias;
if ($vista === 'mes' && $esActual) {
    $pasados = array_values(array_filter($dias, static fn ($d) => $d < $hoy));
    $resto   = array_values(array_filter($dias, static fn ($d) => $d >= $hoy));
    if ($resto === []) {
        [$pasados, $resto] = [[], $dias];
    }
}
$datosDias = ['porDia' => $porDia, 'salidas' => $salidas, 'hoy' => $hoy, 'ahora' => $ahora, 'caption' => $caption];
?>
<?= $this->extend('layouts/main') ?>

<?= $this->section('contenido') ?>
<div class="card shadow-sm border-0 rounded-3 overflow-hidden">

    <div class="card-header tp-card-header py-3 px-4">
        <div class="d-flex align-items-center">
            <div class="me-3"><img src="<?= base_url('img/logo-64.png') ?>" alt="Logo TurnoPlaner" class="barra__logo"></div>
            <div>
                <h1 class="h4 mb-0 fw-semibold text-white">Mi cronograma</h1>
                <p class="mb-0 small subtitulo"><?= esc($periodo) ?></p>
            </div>
        </div>
    </div>

    <div class="card-body p-3 p-md-4 bg-white">

        <!-- Hoy y mañana: lo primero que quiere saber la enfermera, sin importar el período elegido -->
        <section class="proximo-turno p-3 mb-4" aria-labelledby="titulo-resumen">
            <h2 class="visually-hidden" id="titulo-resumen">Hoy y mañana</h2>

            <?php if ($enCurso !== null && (string) $enCurso->fecha !== $hoy): ?>
                <div class="mb-3">
                    <h3 class="h6 fw-semibold mb-2"><i class="bi bi-play-circle-fill me-1" aria-hidden="true"></i>Turno en curso (empezó ayer)</h3>
                    <?= $resumen($enCurso) ?>
                </div>
            <?php endif; ?>

            <div class="row g-3">
                <div class="col-12 col-md-6">
                    <h3 class="h6 fw-semibold mb-2"><i class="bi bi-sun me-1" aria-hidden="true"></i>Hoy <span class="fw-normal text-body-secondary text-capitalize"><?= esc(fecha_larga($hoy)) ?></span></h3>
                    <?php if ($turnosHoy === []): ?>
                        <p class="mb-0 text-body-secondary">Sin turno asignado</p>
                    <?php else: ?>
                        <div class="vstack gap-2">
                            <?php foreach ($turnosHoy as $t): ?>
                                <div><?= $resumen($t) ?><div class="mt-1"><?= $pieza($t, 'estado') ?></div></div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="col-12 col-md-6">
                    <h3 class="h6 fw-semibold mb-2"><i class="bi bi-sunrise me-1" aria-hidden="true"></i>Mañana <span class="fw-normal text-body-secondary text-capitalize"><?= esc(fecha_larga($manana)) ?></span></h3>
                    <?php if ($turnosManana === []): ?>
                        <p class="mb-0 text-body-secondary">Sin turno asignado</p>
                    <?php else: ?>
                        <div class="vstack gap-2">
                            <?php foreach ($turnosManana as $t): ?>
                                <?= $resumen($t) ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($siguienteTurno !== null): ?>
                <p class="mb-0 mt-3"><span class="fw-semibold">Próximo turno:</span> <span class="text-capitalize"><?= esc(fecha_larga((string) $siguienteTurno->fecha)) ?></span></p>
                <div class="mt-1"><?= $resumen($siguienteTurno) ?></div>
            <?php elseif ($turnosHoy === [] && $turnosManana === [] && $enCurso === null): ?>
                <p class="mb-0 mt-3 text-body-secondary">No tiene turnos programados por ahora.</p>
            <?php endif; ?>
        </section>

        <!-- Vista (semana / mes) y navegación -->
        <div class="d-flex flex-column flex-md-row justify-content-between gap-2 mb-3">
            <nav aria-label="Elegir vista">
                <div class="btn-group w-100" role="group">
                    <a class="btn btn-outline-secondary<?= $vista === 'semana' ? ' active' : '' ?>" href="<?= $url('semana', $fechaVista) ?>" <?= $vista === 'semana' ? 'aria-current="page"' : '' ?>>
                        <i class="bi bi-calendar-week me-1" aria-hidden="true"></i>Semana
                    </a>
                    <a class="btn btn-outline-secondary<?= $vista === 'mes' ? ' active' : '' ?>" href="<?= $url('mes', $fechaVista) ?>" <?= $vista === 'mes' ? 'aria-current="page"' : '' ?>>
                        <i class="bi bi-calendar3 me-1" aria-hidden="true"></i>Mes
                    </a>
                </div>
            </nav>
            <nav aria-label="Cambiar de <?= $vista === 'semana' ? 'semana' : 'mes' ?>">
                <div class="btn-group w-100" role="group">
                    <a class="btn btn-outline-secondary text-nowrap flex-grow-0" href="<?= $url($vista, $anterior) ?>"><i class="bi bi-chevron-left" aria-hidden="true"></i><span class="d-none d-sm-inline"> Anterior</span><span class="visually-hidden d-sm-none"><?= $vista === 'semana' ? 'Semana anterior' : 'Mes anterior' ?></span></a>
                    <a class="btn btn-outline-secondary text-nowrap<?= $esActual ? ' active' : '' ?>" href="<?= $url($vista, $hoy) ?>" <?= $esActual ? 'aria-current="true"' : '' ?>><?= $vista === 'semana' ? 'Esta semana' : 'Este mes' ?></a>
                    <a class="btn btn-outline-secondary text-nowrap flex-grow-0" href="<?= $url($vista, $siguiente) ?>"><span class="d-none d-sm-inline">Siguiente </span><span class="visually-hidden d-sm-none"><?= $vista === 'semana' ? 'Semana siguiente' : 'Mes siguiente' ?></span><i class="bi bi-chevron-right" aria-hidden="true"></i></a>
                </div>
            </nav>
        </div>

        <?php if ($cantidad === 0): ?>
            <!-- Estado vacío (semana o mes) -->
            <div class="text-center py-5 border rounded-3">
                <i class="bi bi-calendar-x fs-1 text-body-secondary d-block mb-2" aria-hidden="true"></i>
                <p class="fw-semibold mb-1">No tiene turnos asignados <?= $vista === 'semana' ? 'en esta semana' : 'en ' . esc(mb_strtolower($periodo)) ?>.</p>
                <p class="text-body-secondary small mb-3">Cuando la jefatura le asigne turnos, los va a ver acá con su sector, día y horario.</p>
                <?php if (! $esActual): ?>
                    <a href="<?= $url($vista, $hoy) ?>" class="btn btn-outline-secondary"><?= $vista === 'semana' ? 'Ir a la semana actual' : 'Ir al mes actual' ?></a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <p class="small text-body-secondary mb-3">
                <?= $cantidad === 1 ? '1 turno' : esc((string) $cantidad) . ' turnos' ?> en este período ·
                <?= esc(\App\Models\AsignacionModel::horasTexto($minutos)) ?> en total
            </p>

            <?php if ($pasados !== []): ?>
                <details class="dias-pasados mb-3">
                    <summary class="mb-2">Ver <?= count($pasados) === 1 ? '1 día anterior' : count($pasados) . ' días anteriores' ?> de este mes</summary>
                    <?= view('cronograma/_dias', $datosDias + ['dias' => $pasados]) ?>
                </details>
            <?php endif; ?>

            <?= view('cronograma/_dias', $datosDias + ['dias' => $resto]) ?>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>
