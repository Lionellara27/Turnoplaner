<?php
/**
 * HU-17: Mi cronograma (pantalla inicial del rol ENFERMERO). DESIGN.md 4.11.
 *
 * - Arriba, siempre: resumen Hoy / Mañana (independiente del período elegido).
 * - Vista mes (por defecto): calendario mensual desde 1200 px y agenda por día debajo
 *   (en el mes actual, los días ya pasados se pliegan en un <details>).
 * - Vista semana: los 7 días, con "Sin turno asignado" en los libres.
 * Privacidad: muestra exclusivamente los turnos del usuario autenticado (el controlador lo toma de la sesión).
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
 * @var \App\Entities\Asignacion|null                 $turnoAyer  empezó ayer y sigue en curso o falta finalizarlo (HU-20)
 * @var array<int, \App\Entities\Ausencia>            $ausencias  aviso de ausencia por turno (HU-18)
 */
use App\Models\AsignacionModel;

$semanas      = $semanas ?? [];
$minutosExtra = $minutosExtra ?? 0;
$anioMes      = substr($desde, 0, 7);
$fechaVista   = $esActual ? $hoy : $desde;
$url          = static fn (string $v, string $f): string => base_url('mi-cronograma') . '?' . http_build_query(['vista' => $v, 'fecha' => $f], '', '&amp;');
$ausencias    = $ausencias ?? [];
$turnoAyer    = $turnoAyer ?? null;
$pieza        = static fn ($t, string $parte): string => trim(view('cronograma/_turno', ['t' => $t, 'ahora' => $ahora, 'parte' => $parte, 'ausencias' => $ausencias]));
// HU-20: bloque "Marcar ingreso / Finalizar guardia" del turno de hoy o en curso
$marcacion    = static fn ($t): string => view('cronograma/_marcacion', ['t' => $t, 'ahora' => $ahora, 'ausencias' => $ausencias]);
// HU-18: "Informar ausencia" debajo del turno, solo si corresponde
$acciones     = static function ($t) use ($pieza): string {
    $html = $pieza($t, 'acciones');

    return $html !== '' ? '<div class="mt-2">' . $html . '</div>' : '';
};
// Un turno del resumen: etiqueta, horario y sector, apilados (sin "·" sueltos).
$resumen = static fn ($t): string => '<div class="d-flex flex-wrap align-items-center gap-2">' . $pieza($t, 'etiqueta')
    . '<span>' . $pieza($t, 'horario') . '</span>'
    . '<span><i class="bi bi-hospital me-1" aria-hidden="true"></i>' . esc((string) $t->sector_nombre) . '</span>'
    . $pieza($t, 'extra') . '</div>';
$caption   = 'Mis turnos, ' . mb_strtolower($periodo);
$unidad    = $vista === 'semana' ? 'semana' : 'mes';
$nombresDia = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];

// Vista mes del mes actual: en la agenda (pantallas chicas) los días ya pasados se pliegan.
$pasados = [];
$resto   = $dias;
if ($vista === 'mes' && $esActual) {
    $pasados = array_values(array_filter($dias, static fn ($d) => $d < $hoy));
    $resto   = array_values(array_filter($dias, static fn ($d) => $d >= $hoy));
    if ($resto === []) {
        [$pasados, $resto] = [[], $dias];
    }
}
$datosDias = ['porDia' => $porDia, 'salidas' => $salidas, 'hoy' => $hoy, 'ahora' => $ahora, 'caption' => $caption, 'ausencias' => $ausencias];
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

            <?php if ($turnoAyer !== null): ?>
                <div class="mb-3">
                    <?php if ($turnoAyer->estadoTurno($ahora) === 'en_curso'): ?>
                        <h3 class="h6 fw-semibold mb-2"><i class="bi bi-play-circle-fill me-1" aria-hidden="true"></i>Turno en curso (empezó ayer)</h3>
                    <?php else: ?>
                        <h3 class="h6 fw-semibold mb-2"><i class="bi bi-moon-stars me-1" aria-hidden="true"></i>Turno de ayer: falta finalizar la guardia</h3>
                    <?php endif; ?>
                    <?= $resumen($turnoAyer) ?>
                    <?= $marcacion($turnoAyer) ?>
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
                                <div>
                                    <?= $resumen($t) ?>
                                    <div class="mt-1"><?= $pieza($t, 'estado') ?></div>
                                    <?= $marcacion($t) ?>
                                    <?= $acciones($t) ?>
                                </div>
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
                                <div>
                                    <?= $resumen($t) ?>
                                    <?php if (isset($ausencias[(int) $t->id])): ?><div class="mt-1"><?= $pieza($t, 'estado') ?></div><?php endif; ?>
                                    <?= $acciones($t) ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($siguienteTurno !== null): ?>
                <p class="mb-0 mt-3"><span class="fw-semibold">Próximo turno:</span> <span class="text-capitalize"><?= esc(fecha_larga((string) $siguienteTurno->fecha)) ?></span></p>
                <div class="mt-1"><?= $resumen($siguienteTurno) ?></div>
                <?php if (isset($ausencias[(int) $siguienteTurno->id])): ?><div class="mt-1"><?= $pieza($siguienteTurno, 'estado') ?></div><?php endif; ?>
                <?= $acciones($siguienteTurno) ?>
            <?php elseif ($turnosHoy === [] && $turnosManana === [] && $enCurso === null): ?>
                <p class="mb-0 mt-3 text-body-secondary">No tiene turnos programados por ahora.</p>
            <?php endif; ?>
        </section>

        <!-- Vista (mes / semana) y navegación -->
        <div class="d-flex flex-column flex-md-row justify-content-between gap-2 mb-3">
            <nav aria-label="Elegir vista">
                <div class="btn-group w-100" role="group">
                    <a class="btn btn-outline-secondary<?= $vista === 'mes' ? ' active' : '' ?>" href="<?= $url('mes', $fechaVista) ?>" <?= $vista === 'mes' ? 'aria-current="page"' : '' ?>>
                        <i class="bi bi-calendar3 me-1" aria-hidden="true"></i>Mes
                    </a>
                    <a class="btn btn-outline-secondary<?= $vista === 'semana' ? ' active' : '' ?>" href="<?= $url('semana', $fechaVista) ?>" <?= $vista === 'semana' ? 'aria-current="page"' : '' ?>>
                        <i class="bi bi-calendar-week me-1" aria-hidden="true"></i>Semana
                    </a>
                </div>
            </nav>
            <nav aria-label="Cambiar de <?= $unidad ?>">
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
                <?= esc(AsignacionModel::horasTexto($minutos)) ?> en total<?php if ($minutosExtra > 0): ?> ·
                    <span class="badge-extra"><span aria-hidden="true">HE</span><span class="visually-hidden">Horas extras:</span></span> <?= esc(AsignacionModel::horasTexto($minutosExtra)) ?> de horas extras<?php endif; ?>
            </p>

            <?php if ($vista === 'mes' && $semanas !== []): ?>
                <!-- Computadora: calendario mensual -->
                <div class="table-responsive border rounded-3 d-none d-xl-block mb-3">
                    <table class="table align-top mb-0 tp-calendario">
                        <caption class="visually-hidden"><?= esc($caption) ?></caption>
                        <thead class="table-light text-center">
                            <tr>
                                <?php foreach ($nombresDia as $nombreDia): ?>
                                    <th scope="col"><?= $nombreDia ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($semanas as $semana): ?>
                                <tr>
                                    <?php foreach ($semana as $dia): ?>
                                        <?php
                                        $delMes    = substr($dia, 0, 7) === $anioMes;
                                        $esHoy     = $dia === $hoy;
                                        $turnosDia = $delMes ? ($porDia[$dia] ?? []) : [];
                                        $salida    = $delMes ? ($salidas[$dia] ?? null) : null;
                                        $clase     = ! $delMes ? 'tp-calendario__dia--fuera' : ($esHoy ? 'tp-calendario__dia--hoy' : '');
                                        ?>
                                        <td class="tp-calendario__dia <?= $clase ?>" <?= $esHoy ? 'aria-current="date"' : '' ?>>
                                            <?php if (! $delMes): ?>
                                                <span class="tp-calendario__numero" aria-hidden="true"><?= (int) substr($dia, 8, 2) ?></span>
                                            <?php else: ?>
                                                <div class="d-flex justify-content-between align-items-center mb-1">
                                                    <span class="tp-calendario__numero"><span class="visually-hidden"><?= esc(fecha_larga($dia)) ?></span><span aria-hidden="true"><?= (int) substr($dia, 8, 2) ?></span></span>
                                                    <?php if ($esHoy): ?><span class="badge-hoy">Hoy</span><?php endif; ?>
                                                </div>

                                                <?php if ($salida !== null): ?>
                                                    <div class="tp-calendario__nota mb-1">
                                                        <i class="bi bi-moon-stars me-1" aria-hidden="true"></i>Sale de <?= esc((string) $salida->tipo_nombre) ?> a las <?= esc($salida->horaFin()) ?>
                                                    </div>
                                                <?php endif; ?>

                                                <?php if ($turnosDia === []): ?>
                                                    <span class="tp-calendario__nota">Sin turno asignado</span>
                                                <?php else: ?>
                                                    <?php foreach ($turnosDia as $t): ?>
                                                        <div class="tp-calendario__turno">
                                                            <?= $pieza($t, 'frase') ?>
                                                            <div aria-hidden="true">
                                                                <div class="d-flex justify-content-between align-items-center gap-1">
                                                                    <?= $pieza($t, 'etiqueta') ?>
                                                                    <?= $pieza($t, 'extra') ?>
                                                                </div>
                                                                <div class="tp-calendario__sector"><i class="bi bi-hospital me-1"></i><?= esc((string) $t->sector_nombre) ?></div>
                                                                <div class="tp-calendario__nota"><i class="bi bi-clock me-1"></i><?= $pieza($t, 'horario') ?></div>
                                                                <div class="mt-1"><?= $pieza($t, 'estado') ?></div>
                                                            </div>
                                                            <?= $acciones($t) ?>
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

                <!-- Celular y tablet: agenda por día (los días pasados del mes actual, plegados) -->
                <div class="d-xl-none">
                    <?php if ($pasados !== []): ?>
                        <details class="dias-pasados mb-3">
                            <summary class="mb-2">Ver <?= count($pasados) === 1 ? '1 día anterior' : count($pasados) . ' días anteriores' ?> de este mes</summary>
                            <?= view('cronograma/_dias', $datosDias + ['dias' => $pasados]) ?>
                        </details>
                    <?php endif; ?>
                    <?= view('cronograma/_dias', $datosDias + ['dias' => $resto]) ?>
                </div>
            <?php else: ?>
                <!-- Vista semana -->
                <?= view('cronograma/_dias', $datosDias + ['dias' => $dias]) ?>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>
