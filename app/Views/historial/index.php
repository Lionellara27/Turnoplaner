<?php
/**
 * HU-19: Mi historial (portal del enfermero/a).
 *
 * - Filtro por período (año + mes, o todo el año) con form-label visible, botón "Filtrar" y GET (anda sin JS),
 *   más navegación Anterior / Actual / Siguiente.
 * - KPIs: horas cumplidas contra la carga contratada del período (barra de progreso), guardias cumplidas
 *   (normales y reemplazos) y ausencias registradas.
 * - Detalle cronológico: tabla desde 1200 px y tarjetas debajo (mismo corte que _dias.php).
 *
 * @var bool                              $porAnio, $esActual
 * @var string                            $periodo, $hoy
 * @var int                               $anioElegido, $mesElegido
 * @var list<int>                         $anios
 * @var array<string, int|string>         $anterior, $siguiente
 * @var list<array<string, mixed>>        $filas   AsignacionModel::historialDeUsuario()
 * @var array<string, int|float|string>   $resumen AsignacionModel::resumenHistorial()
 */
use App\Entities\TipoTurno;
use App\Models\AsignacionModel;

$url      = static fn (array $q): string => base_url('mi-historial') . ($q !== [] ? '?' . http_build_query($q, '', '&amp;') : '');
$numero   = static fn (float $n): string => str_replace('.', ',', (string) round($n, 1));
$etiqueta = static fn (array $f): string => '<span class="etiqueta-turno" style="--color-turno: ' . esc((string) $f['tipo_color'], 'attr')
    . '; --color-turno-texto: ' . esc(TipoTurno::textoSobre((string) $f['tipo_color']), 'attr') . ';">' . esc((string) $f['tipo_nombre']) . '</span>';
$horario  = static fn (array $f): string => esc($f['hora_inicio'] . ' a ' . $f['hora_fin']) . ($f['termina_dia_siguiente'] ? ' (+1 día)' : '');
$estado   = static fn (array $f): string => '<span class="estado-turno tp-estado-hist tp-estado-hist--' . esc(str_replace('_', '-', (string) $f['estado']), 'attr') . '">'
    . '<i class="bi ' . esc((string) $f['estado_icono'], 'attr') . '" aria-hidden="true"></i>' . esc((string) $f['estado_texto']) . '</span>';
$extra    = static fn (array $f): string => $f['es_extra']
    ? ' <span class="badge-extra"><span aria-hidden="true">HE</span><span class="visually-hidden">Hora extra' . ((int) $f['minutos_extra'] > 0 ? ': ' . esc(AsignacionModel::horasTexto((int) $f['minutos_extra'])) : '') . '</span></span>'
    : '';
$marcas   = static function (array $f): string {
    if ($f['hora_ingreso'] === '' && $f['hora_salida'] === '') {
        return '<span aria-hidden="true">—</span><span class="visually-hidden">Sin marcas registradas</span>';
    }

    return '<span class="text-nowrap">Ingreso ' . esc($f['hora_ingreso'] !== '' ? $f['hora_ingreso'] : '—') . '</span> · '
        . '<span class="text-nowrap">Salida ' . esc($f['hora_salida'] !== '' ? $f['hora_salida'] : '—') . '</span>';
};
// Observaciones del pase y del rechazo, plegadas para no alargar la fila
$notas = static function (array $f): string {
    $html = '';
    if (! empty($f['observaciones_pase'])) {
        $html .= '<details class="tp-historial__nota mt-1"><summary>Pase de guardia</summary><p class="mb-0 mt-1 text-break">' . esc((string) $f['observaciones_pase']) . '</p></details>';
    }
    if ($f['ausencia_estado'] === 'RECHAZADA' && ! empty($f['observacion_admin'])) {
        $html .= '<p class="small text-body-secondary mb-0 mt-1 text-break">Observación de la coordinación: ' . esc((string) $f['observacion_admin']) . '</p>';
    }

    return $html;
};

$cargaContratada = (float) $resumen['carga_contratada'];
$porcentaje      = (int) $resumen['porcentaje'];
$ancho           = max(0, min(100, $porcentaje));
$caption         = 'Mis turnos de ' . mb_strtolower($periodo);
?>
<?= $this->extend('layouts/main') ?>

<?= $this->section('contenido') ?>
<div class="card shadow-sm border-0 rounded-3 overflow-hidden">

    <div class="card-header tp-card-header py-3 px-4">
        <div class="d-flex align-items-center">
            <div class="me-3"><img src="<?= base_url('img/logo-64.png') ?>" alt="Logo TurnoPlaner" class="barra__logo"></div>
            <div>
                <h1 class="h4 mb-0 fw-semibold text-white">Mi historial</h1>
                <p class="mb-0 small subtitulo"><?= esc($periodo) ?> · horas, guardias y ausencias</p>
            </div>
        </div>
    </div>

    <div class="card-body p-3 p-md-4 bg-white">

        <!-- Filtro de período (GET, sin autoenvío) y navegación -->
        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-end gap-3 mb-4">
            <form action="<?= base_url('mi-historial') ?>" method="get" class="tp-historial__filtro" aria-label="Elegir período">
                <div class="row g-2 align-items-end">
                    <div class="col-6 col-sm-auto">
                        <label for="mes_numero" class="form-label small fw-semibold mb-1">Mes</label>
                        <select id="mes_numero" name="mes_numero" class="form-select">
                            <option value="" <?= $porAnio ? 'selected' : '' ?>>Todo el año</option>
                            <?php foreach (AsignacionModel::MESES as $n => $nombreMes): ?>
                                <option value="<?= $n ?>" <?= $mesElegido === $n ? 'selected' : '' ?>><?= esc($nombreMes) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6 col-sm-auto">
                        <label for="anio" class="form-label small fw-semibold mb-1">Año</label>
                        <select id="anio" name="anio" class="form-select">
                            <?php foreach ($anios as $a): ?>
                                <option value="<?= $a ?>" <?= $anioElegido === $a ? 'selected' : '' ?>><?= $a ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12 col-sm-auto">
                        <button type="submit" class="btn btn-outline-secondary w-100 tp-boton-filtrar">
                            <i class="bi bi-funnel me-1" aria-hidden="true"></i>Filtrar
                        </button>
                    </div>
                </div>
            </form>

            <nav aria-label="Cambiar de <?= $porAnio ? 'año' : 'mes' ?>">
                <div class="btn-group w-100" role="group">
                    <a class="btn btn-outline-secondary text-nowrap flex-grow-0" href="<?= $url($anterior) ?>"><i class="bi bi-chevron-left" aria-hidden="true"></i><span class="d-none d-sm-inline"> Anterior</span><span class="visually-hidden d-sm-none"><?= $porAnio ? 'Año anterior' : 'Mes anterior' ?></span></a>
                    <a class="btn btn-outline-secondary text-nowrap<?= $esActual ? ' active' : '' ?>" href="<?= $url($porAnio ? ['anio' => (int) date('Y')] : []) ?>" <?= $esActual ? 'aria-current="true"' : '' ?>><?= $porAnio ? 'Este año' : 'Este mes' ?></a>
                    <a class="btn btn-outline-secondary text-nowrap flex-grow-0" href="<?= $url($siguiente) ?>"><span class="d-none d-sm-inline">Siguiente </span><span class="visually-hidden d-sm-none"><?= $porAnio ? 'Año siguiente' : 'Mes siguiente' ?></span><i class="bi bi-chevron-right" aria-hidden="true"></i></a>
                </div>
            </nav>
        </div>

        <!-- Indicadores del período -->
        <h2 class="visually-hidden">Resumen de <?= esc(mb_strtolower($periodo)) ?></h2>
        <div class="row g-3 mb-4">
            <div class="col-12 col-md-4">
                <section class="card border-0 tp-panel-card tarjeta-indicador indicador--ok h-100" aria-labelledby="kpi-horas">
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-3">
                            <span class="indicador__icono" aria-hidden="true"><i class="bi bi-clock-history"></i></span>
                            <div>
                                <h3 class="text-body-secondary small fw-medium mb-1" id="kpi-horas">Horas cumplidas</h3>
                                <p class="numero mb-0"><?= esc((string) $resumen['horas_cumplidas_texto']) ?></p>
                            </div>
                        </div>
                        <?php if ($cargaContratada > 0): ?>
                            <p class="small mb-1 mt-3">de <strong><?= esc($numero($cargaContratada)) ?> h</strong> de carga contratada (<?= $porcentaje ?> %)</p>
                            <div class="carga-semana carga-semana--ok">
                                <div class="progress" role="progressbar" aria-label="Horas cumplidas sobre la carga contratada"
                                     aria-valuenow="<?= $ancho ?>" aria-valuemin="0" aria-valuemax="100" aria-valuetext="<?= $porcentaje ?> % de la carga contratada">
                                    <div class="progress-bar" style="width: <?= $ancho ?>%"></div>
                                </div>
                            </div>
                            <p class="small text-body-secondary mb-0 mt-1">Carga semanal de <?= (int) $resumen['carga_semanal'] ?> h, proporcional a los <?= (int) $resumen['dias_periodo'] ?> días del período.</p>
                        <?php else: ?>
                            <p class="small text-body-secondary mb-0 mt-3">No tiene una carga horaria contratada registrada.</p>
                        <?php endif; ?>
                    </div>
                </section>
            </div>
            <div class="col-12 col-md-4">
                <section class="card border-0 tp-panel-card tarjeta-indicador tp-indicador--info h-100" aria-labelledby="kpi-guardias">
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-3">
                            <span class="indicador__icono" aria-hidden="true"><i class="bi bi-shield-check"></i></span>
                            <div>
                                <h3 class="text-body-secondary small fw-medium mb-1" id="kpi-guardias">Guardias cumplidas</h3>
                                <p class="numero mb-0"><?= (int) $resumen['guardias_total'] ?></p>
                            </div>
                        </div>
                        <ul class="list-unstyled small mb-0 mt-3">
                            <li><i class="bi bi-calendar-check me-1" aria-hidden="true"></i><?= (int) $resumen['guardias_normales'] === 1 ? '1 normal' : (int) $resumen['guardias_normales'] . ' normales' ?></li>
                            <li><i class="bi bi-arrow-left-right me-1" aria-hidden="true"></i><?= (int) $resumen['guardias_reemplazo'] === 1 ? '1 reemplazo' : (int) $resumen['guardias_reemplazo'] . ' reemplazos' ?></li>
                        </ul>
                    </div>
                </section>
            </div>
            <div class="col-12 col-md-4">
                <section class="card border-0 tp-panel-card tarjeta-indicador indicador--pendiente h-100" aria-labelledby="kpi-ausencias">
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-3">
                            <span class="indicador__icono" aria-hidden="true"><i class="bi bi-person-x"></i></span>
                            <div>
                                <h3 class="text-body-secondary small fw-medium mb-1" id="kpi-ausencias">Ausencias registradas</h3>
                                <p class="numero mb-0"><?= (int) $resumen['ausencias_registradas'] ?></p>
                            </div>
                        </div>
                        <p class="small mb-0 mt-3">Avisos que informó en turnos del período, en cualquier estado.</p>
                        <a href="<?= base_url('mis-ausencias') ?>" class="tp-link-44 small">Ver mis ausencias</a>
                    </div>
                </section>
            </div>
        </div>

        <!-- Detalle cronológico -->
        <section aria-labelledby="titulo-detalle">
            <h2 class="h5 fw-semibold mb-1" id="titulo-detalle">Detalle de turnos</h2>

            <?php if ($filas === []): ?>
                <div class="text-center py-5 border rounded-3 mt-3">
                    <i class="bi bi-calendar-x fs-1 text-body-secondary d-block mb-2" aria-hidden="true"></i>
                    <p class="fw-semibold mb-1">No tiene turnos registrados en <?= esc(mb_strtolower($periodo)) ?>.</p>
                    <p class="text-body-secondary small mb-3">Elija otro período para ver su historial.</p>
                    <?php if (! $esActual): ?>
                        <a href="<?= $url([]) ?>" class="btn btn-outline-secondary">Ir al mes actual</a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <p class="small text-body-secondary mb-3"><?= count($filas) === 1 ? '1 turno' : count($filas) . ' turnos' ?> en el período, del más antiguo al más reciente.</p>

                <!-- Computadora: tabla -->
                <div class="table-responsive border rounded-3 d-none d-xl-block">
                    <table class="table table-hover align-middle mb-0">
                        <caption class="visually-hidden"><?= esc($caption) ?></caption>
                        <thead class="table-light">
                            <tr>
                                <th scope="col">Fecha</th>
                                <th scope="col">Sector</th>
                                <th scope="col">Turno y horario</th>
                                <th scope="col">Horas</th>
                                <th scope="col">Tipo</th>
                                <th scope="col">Estado</th>
                                <th scope="col">Ingreso / salida</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($filas as $f): ?>
                                <tr class="<?= $f['fecha'] === $hoy ? 'cronograma__hoy' : '' ?>">
                                    <th scope="row" class="fw-normal text-nowrap" <?= $f['fecha'] === $hoy ? 'aria-current="date"' : '' ?>>
                                        <span class="text-capitalize fw-semibold"><?= esc(mb_substr(dia_semana($f['fecha']), 0, 3)) ?></span> <?= esc(fecha_ar($f['fecha'])) ?>
                                    </th>
                                    <td><?= esc((string) $f['sector_nombre']) ?><?= $f['sector_critico'] ? ' <span class="small text-body-secondary">(área crítica)</span>' : '' ?></td>
                                    <td><?= $etiqueta($f) ?> <span class="text-nowrap"><?= $horario($f) ?></span></td>
                                    <td class="text-nowrap"><?= esc((string) $f['horas_texto']) ?><?= $extra($f) ?></td>
                                    <td><?= esc((string) $f['tipo']) ?></td>
                                    <td><?= $estado($f) ?><?= $notas($f) ?></td>
                                    <td class="small"><?= $marcas($f) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Celular y tablet: tarjetas -->
                <ul class="list-unstyled d-xl-none mb-0">
                    <?php foreach ($filas as $f): ?>
                        <li class="dia-cronograma border rounded-3 mb-2<?= $f['fecha'] === $hoy ? ' dia-cronograma--hoy' : '' ?>">
                            <h3 class="h6 fw-semibold px-3 py-2 mb-0 border-bottom d-flex flex-wrap justify-content-between align-items-center gap-2" <?= $f['fecha'] === $hoy ? 'aria-current="date"' : '' ?>>
                                <span class="text-nowrap"><span class="text-capitalize"><?= esc(dia_semana($f['fecha'])) ?></span> <?= esc(fecha_ar($f['fecha'])) ?></span>
                                <?= $estado($f) ?>
                            </h3>
                            <div class="px-3 py-2">
                                <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                                    <?= $etiqueta($f) ?>
                                    <span class="text-nowrap"><i class="bi bi-clock me-1" aria-hidden="true"></i><?= $horario($f) ?></span>
                                </div>
                                <div class="small text-body-secondary mb-1"><i class="bi bi-hospital me-1" aria-hidden="true"></i><?= esc((string) $f['sector_nombre']) ?><?= $f['sector_critico'] ? ' (área crítica)' : '' ?></div>
                                <dl class="tp-historial__datos small mb-0">
                                    <div><dt>Horas</dt><dd><?= esc((string) $f['horas_texto']) ?><?= $extra($f) ?></dd></div>
                                    <div><dt>Tipo</dt><dd><?= esc((string) $f['tipo']) ?></dd></div>
                                    <div><dt>Marcas</dt><dd><?= $marcas($f) ?></dd></div>
                                </dl>
                                <?= $notas($f) ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    </div>
</div>
<?= $this->endSection() ?>
