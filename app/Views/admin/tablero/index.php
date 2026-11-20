<?php
/**
 * HU-15: Tablero de control de cobertura (vista semanal o de un día).
 * Desde 1400 px la semana se ve como tabla (filas = sectores, columnas = días); debajo, lista por día.
 * La vista de un día es una tarjeta por sector. Sin JavaScript: todo son links.
 *
 * @var string $modo        "semana" | "dia"
 * @var bool   $soloAlertas mostrar solo las franjas vigentes con falta de personal
 * @var array  $cobertura   ver AsignacionModel::coberturaSemana() (+ 'url' en cada celda)
 * @var array{alertas:int, cubiertas:int, franjas:int, alertas_terminadas:int} $resumen
 * @var string $periodo     texto del período mostrado
 * @var array  $nav         anterior / actual / siguiente (parámetros GET) y esActual
 * @var string $hoy
 * @var int    $pendientesTotales ausencias pendientes que todavía se pueden resolver (todas las fechas)
 */
$dias       = $cobertura['dias'];
$filas      = $cobertura['sectores'];
$extra      = $soloAlertas ? ['alertas' => '1'] : [];
$urlTablero = static fn (array $p): string => base_url('admin/tablero') . '?' . http_build_query($p, '', '&amp;');
$esSemana   = $modo === 'semana';
$diaVista   = $esSemana ? (in_array($hoy, $dias, true) ? $hoy : $dias[0]) : $dias[0];
$lunesVista = \App\Models\AsignacionModel::lunesDe($dias[0]);

// Con el filtro "solo alertas" se muestran únicamente las franjas que faltan cubrir y todavía no terminaron.
$seMuestra = static fn (array $c): bool => ! $soloAlertas || ($c['alerta'] && ! $c['terminado']);
$visiblesDe = static function (array $fila, string $dia) use ($seMuestra): array {
    $lista = [];
    foreach ($fila['tipos'] as $t) {
        $c = $fila['celdas'][$dia][(int) $t->id] ?? null;
        if ($c !== null && $seMuestra($c)) {
            $lista[] = [$t, $c];
        }
    }

    return $lista;
};
$hayVisibles = false;
foreach ($filas as $f) {
    foreach ($dias as $d) {
        if ($visiblesDe($f, $d) !== []) {
            $hayVisibles = true;
            break 2;
        }
    }
}
$etiquetaDia = static fn (string $d): string => ucfirst(dia_semana($d)) . ' ' . fecha_ar($d);
$badgeCritica = '<span class="badge tp-badge-critica"><i class="bi bi-heart-pulse-fill me-1" aria-hidden="true"></i>Área crítica</span>';
?>
<?= $this->extend('layouts/main') ?>

<?= $this->section('contenido') ?>
<div class="tp-panel-card overflow-hidden">

    <div class="card-header tp-card-header py-3 px-4">
        <div class="d-flex align-items-center min-w-0">
            <div class="me-3"><img src="<?= base_url('img/logo-64.png') ?>" alt="Logo TurnoPlaner" class="barra__logo"></div>
            <div class="min-w-0">
                <h1 class="h4 mb-0 fw-semibold text-white">Tablero de cobertura</h1>
                <p class="mb-0 small subtitulo"><?= esc($periodo) ?> · Todos los sectores activos</p>
            </div>
        </div>
    </div>

    <div class="card-body p-3 p-md-4 bg-white">

        <!-- Vista, período y filtro -->
        <div class="d-flex flex-column flex-lg-row flex-wrap justify-content-between align-items-lg-center gap-2 mb-3 pb-3 border-bottom">
            <div class="d-flex flex-wrap gap-2">
                <nav aria-label="Tipo de vista">
                    <div class="btn-group nav-mes" role="group">
                        <a class="btn btn-outline-secondary<?= $esSemana ? ' active' : '' ?>" href="<?= $urlTablero(['semana' => $lunesVista] + $extra) ?>"<?= $esSemana ? ' aria-current="page"' : '' ?>>
                            <i class="bi bi-calendar-week me-1" aria-hidden="true"></i>Semana
                        </a>
                        <a class="btn btn-outline-secondary<?= ! $esSemana ? ' active' : '' ?>" href="<?= $urlTablero(['dia' => $diaVista] + $extra) ?>"<?= ! $esSemana ? ' aria-current="page"' : '' ?>>
                            <i class="bi bi-calendar-day me-1" aria-hidden="true"></i>Día
                        </a>
                    </div>
                </nav>
                <nav aria-label="<?= $esSemana ? 'Cambiar de semana' : 'Cambiar de día' ?>">
                    <div class="btn-group nav-mes" role="group">
                        <a class="btn btn-outline-secondary" href="<?= $urlTablero($nav['anterior'] + $extra) ?>">
                            <i class="bi bi-chevron-left" aria-hidden="true"></i><span class="d-none d-sm-inline"> Anterior</span><span class="visually-hidden d-sm-none"><?= $esSemana ? 'Semana anterior' : 'Día anterior' ?></span>
                        </a>
                        <a class="btn btn-outline-secondary<?= $nav['esActual'] ? ' active' : '' ?>" href="<?= $urlTablero($nav['actual'] + $extra) ?>"<?= $nav['esActual'] ? ' aria-current="date"' : '' ?>>
                            <?= $esSemana ? 'Esta semana' : 'Hoy' ?>
                        </a>
                        <a class="btn btn-outline-secondary" href="<?= $urlTablero($nav['siguiente'] + $extra) ?>">
                            <span class="d-none d-sm-inline">Siguiente </span><span class="visually-hidden d-sm-none"><?= $esSemana ? 'Semana siguiente' : 'Día siguiente' ?></span><i class="bi bi-chevron-right" aria-hidden="true"></i>
                        </a>
                    </div>
                </nav>
            </div>
            <?php $paramsVista = $esSemana ? ['semana' => $lunesVista] : ['dia' => $dias[0]]; ?>
            <a class="btn btn-outline-secondary" href="<?= $urlTablero($paramsVista + ($soloAlertas ? [] : ['alertas' => '1'])) ?>">
                <?php if ($soloAlertas): ?>
                    <i class="bi bi-funnel-fill me-1" aria-hidden="true"></i>Mostrar todas las franjas
                <?php else: ?>
                    <i class="bi bi-funnel me-1" aria-hidden="true"></i>Ver solo lo que falta cubrir
                <?php endif; ?>
            </a>
        </div>

        <!-- Resumen -->
        <h2 class="visually-hidden">Resumen del período</h2>
        <div class="row g-2 g-md-3 mb-3">
            <div class="col-12 col-md-4">
                <div class="tp-resumen-tablero <?= $resumen['alertas'] > 0 ? 'tp-resumen-tablero--alerta' : 'tp-resumen-tablero--ok' ?> h-100">
                    <span class="indicador__icono" aria-hidden="true"><i class="bi <?= $resumen['alertas'] > 0 ? 'bi-exclamation-octagon-fill' : 'bi-check-circle-fill' ?>"></i></span>
                    <div>
                        <div class="tp-resumen-tablero__numero"><?= (int) $resumen['alertas'] ?></div>
                        <div class="small fw-medium"><?= $resumen['alertas'] === 1 ? 'franja con falta de personal' : 'franjas con falta de personal' ?></div>
                        <?php if ($resumen['alertas_terminadas'] > 0): ?>
                            <div class="small text-body-secondary">Además, <?= (int) $resumen['alertas_terminadas'] ?> ya <?= $resumen['alertas_terminadas'] === 1 ? 'terminó' : 'terminaron' ?> sin cubrir.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="tp-resumen-tablero tp-resumen-tablero--ok h-100">
                    <span class="indicador__icono" aria-hidden="true"><i class="bi bi-shield-check"></i></span>
                    <div>
                        <div class="tp-resumen-tablero__numero"><?= (int) $resumen['cubiertas'] ?> <span class="tp-resumen-tablero__de">de <?= (int) $resumen['franjas'] ?></span></div>
                        <div class="small fw-medium">franjas por venir cubiertas</div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <a href="<?= base_url('admin/ausencias') ?>" class="tp-resumen-tablero <?= $pendientesTotales > 0 ? 'tp-resumen-tablero--aviso' : 'tp-resumen-tablero--neutro' ?> tp-resumen-tablero--link h-100">
                    <span class="indicador__icono" aria-hidden="true"><i class="bi bi-person-dash"></i></span>
                    <div>
                        <div class="tp-resumen-tablero__numero"><?= (int) $pendientesTotales ?></div>
                        <div class="small fw-medium"><?= $pendientesTotales === 1 ? 'ausencia pendiente de resolver' : 'ausencias pendientes de resolver' ?></div>
                        <div class="small tp-resumen-tablero__ir">Gestionar ausencias<i class="bi bi-arrow-right-short" aria-hidden="true"></i></div>
                    </div>
                </a>
            </div>
        </div>

        <!-- Leyenda -->
        <div class="d-flex flex-wrap align-items-center gap-2 small mb-3">
            <span class="tp-leyenda__titulo">Referencias:</span>
            <span class="cobertura cobertura--ok"><i class="bi bi-check-circle-fill" aria-hidden="true"></i>Cubierto</span>
            <span class="cobertura cobertura--vacio"><i class="bi bi-exclamation-octagon-fill" aria-hidden="true"></i>Falta personal</span>
            <span class="tp-leyenda-tablero tp-leyenda-tablero--terminada"><i class="bi bi-lock" aria-hidden="true"></i>Terminado</span>
            <span class="tp-leyenda-tablero tp-leyenda-tablero--no-aplica"><i class="bi bi-dash-circle" aria-hidden="true"></i>No vigente</span>
            <?= $badgeCritica ?>
            <span class="text-body-secondary">Toque una franja en rojo para resolverla.</span>
        </div>

        <?php if ($filas === []): ?>
            <div class="text-center py-5 border rounded-3">
                <i class="bi bi-hospital fs-1 text-body-secondary d-block mb-2" aria-hidden="true"></i>
                <p class="fw-semibold mb-2">Todavía no hay sectores activos.</p>
                <a href="<?= base_url('admin/sectores/crear') ?>" class="btn btn-tp">Crear sector</a>
            </div>
        <?php elseif (! $hayVisibles): ?>
            <div class="text-center py-5 border rounded-3">
                <i class="bi bi-check-circle fs-1 text-body-secondary d-block mb-2" aria-hidden="true"></i>
                <p class="fw-semibold mb-1"><?= $soloAlertas ? 'No hay franjas por venir con falta de personal en este período.' : 'No hay tipos de turno configurados en los sectores activos.' ?></p>
                <?php if ($soloAlertas): ?>
                    <a href="<?= $urlTablero($paramsVista) ?>" class="btn btn-outline-secondary mt-2">Mostrar todas las franjas</a>
                <?php else: ?>
                    <a href="<?= base_url('admin/tipos-turno/crear') ?>" class="btn btn-tp mt-2">Crear tipo de turno</a>
                <?php endif; ?>
            </div>
        <?php elseif ($esSemana): ?>

            <h2 class="visually-hidden">Cobertura por sector y día</h2>

            <!-- Desde 1400 px: tabla semanal -->
            <div class="d-none d-xxl-block rounded-3 border overflow-hidden">
                <table class="table table-bordered align-top mb-0 tp-tablero">
                    <caption class="visually-hidden">Cobertura de la <?= esc(lcfirst($periodo)) ?>: una fila por sector y una columna por día.</caption>
                    <thead>
                        <tr>
                            <th scope="col" class="tp-tablero__sector">Sector</th>
                            <?php foreach ($dias as $d): ?>
                                <th scope="col" class="text-center<?= $d === $hoy ? ' tp-tablero__hoy' : '' ?>"<?= $d === $hoy ? ' aria-current="date"' : '' ?>>
                                    <a href="<?= $urlTablero(['dia' => $d] + $extra) ?>" class="tp-tablero__dia">
                                        <span class="text-capitalize"><?= esc(dia_semana($d)) ?></span>
                                        <span class="d-block"><?= esc(substr(fecha_ar($d), 0, 5)) ?></span>
                                        <span class="visually-hidden">: ver el detalle del día <?= esc(fecha_ar($d)) ?></span>
                                    </a>
                                    <?php if ($d === $hoy): ?><span class="badge-hoy">Hoy</span><?php endif; ?>
                                </th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($filas as $f): ?>
                            <?php
                            $tieneAlgo = false;
                            foreach ($dias as $d) {
                                if ($visiblesDe($f, $d) !== []) {
                                    $tieneAlgo = true;
                                    break;
                                }
                            }
                            if (! $tieneAlgo) {
                                continue;
                            }
                            ?>
                            <tr<?= $f['sector']->esCritico() ? ' class="tp-tablero__critica"' : '' ?>>
                                <th scope="row" class="tp-tablero__sector">
                                    <span class="d-block fw-semibold"><?= esc($f['sector']->nombre) ?></span>
                                    <?php if ($f['sector']->esCritico()): ?><?= $badgeCritica ?><?php endif; ?>
                                    <span class="d-block small fw-normal text-body-secondary mt-1">Mínimo <?= (int) $f['sector']->dotacion_minima ?> por turno</span>
                                    <?php if ($f['alertas'] > 0): ?>
                                        <span class="d-block small fw-semibold tp-texto-error mt-1"><i class="bi bi-exclamation-octagon-fill me-1" aria-hidden="true"></i><?= (int) $f['alertas'] ?> <?= $f['alertas'] === 1 ? 'alerta' : 'alertas' ?></span>
                                    <?php endif; ?>
                                </th>
                                <?php foreach ($dias as $d): ?>
                                    <?php $lista = $visiblesDe($f, $d); ?>
                                    <td class="<?= $d === $hoy ? 'tp-tablero__hoy' : '' ?>">
                                        <?php if ($lista === []): ?>
                                            <span class="text-body-secondary" aria-hidden="true">—</span><span class="visually-hidden">Sin franjas para mostrar.</span>
                                        <?php else: ?>
                                            <ul class="tp-franjas">
                                                <?php foreach ($lista as [$t, $c]): ?>
                                                    <?= view('admin/tablero/_franja', ['sector' => $f['sector'], 'tipo' => $t, 'dia' => $d, 'celda' => $c, 'compacto' => true]) ?>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Debajo de 1400 px: lista por día (los días ya pasados de la semana actual van plegados) -->
            <?php
            $anteriores = array_values(array_filter($dias, static fn (string $d): bool => $d < $hoy && $nav['esActual']));
            $restantes  = array_values(array_diff($dias, $anteriores));
            $dibujarDia = static function (string $d) use ($filas, $visiblesDe, $hoy, $urlTablero, $extra, $badgeCritica, $etiquetaDia): string {
                $html  = '';
                $esHoy = $d === $hoy;
                foreach ($filas as $f) {
                    $lista = $visiblesDe($f, $d);
                    if ($lista === []) {
                        continue;
                    }
                    $html .= '<li class="tp-tablero-sector"><h4 class="tp-tablero-sector__titulo">' . esc($f['sector']->nombre)
                        . ($f['sector']->esCritico() ? ' ' . $badgeCritica : '') . '</h4><ul class="tp-franjas tp-franjas--fila">';
                    foreach ($lista as [$t, $c]) {
                        $html .= view('admin/tablero/_franja', ['sector' => $f['sector'], 'tipo' => $t, 'dia' => $d, 'celda' => $c, 'compacto' => true]);
                    }
                    $html .= '</ul></li>';
                }
                $id = 'tablero-dia-' . $d;

                return '<li class="col"><article class="dia-lista h-100' . ($esHoy ? ' dia-lista--hoy' : ($d < $hoy ? ' dia-lista--pasado' : '')) . '" aria-labelledby="' . $id . '"' . ($esHoy ? ' aria-current="date"' : '') . '>'
                    . '<div class="dia-lista__cabecera"><h3 class="dia-lista__titulo" id="' . $id . '">' . esc($etiquetaDia($d))
                    . ($esHoy ? ' <span class="badge-hoy ms-1">Hoy</span>' : '') . '</h3>'
                    . '<a class="btn btn-outline-secondary" href="' . $urlTablero(['dia' => $d] + $extra) . '">Ver día<span class="visually-hidden"> ' . esc(fecha_larga($d)) . '</span></a></div>'
                    . ($html === ''
                        ? '<p class="small text-body-secondary mb-0 p-3">Sin franjas con falta de personal.</p>'
                        : '<ul class="list-unstyled mb-0 dia-lista__cuerpo">' . $html . '</ul>')
                    . '</article></li>';
            };
            ?>
            <div class="d-xxl-none">
                <?php if ($anteriores !== []): ?>
                    <details class="plan-dias-anteriores mb-2">
                        <summary><i class="bi bi-clock-history" aria-hidden="true"></i>Ver <?= count($anteriores) ?> <?= count($anteriores) === 1 ? 'día anterior' : 'días anteriores' ?> de esta semana</summary>
                        <ul class="list-unstyled row row-cols-1 row-cols-md-2 g-2 mb-0">
                            <?php foreach ($anteriores as $d): ?><?= $dibujarDia($d) ?><?php endforeach; ?>
                        </ul>
                    </details>
                <?php endif; ?>
                <ul class="list-unstyled row row-cols-1 row-cols-md-2 g-2 mb-0">
                    <?php foreach ($restantes as $d): ?><?= $dibujarDia($d) ?><?php endforeach; ?>
                </ul>
            </div>

        <?php else: ?>

            <!-- Vista de un día: una tarjeta por sector -->
            <h2 class="h5 fw-bold mb-3"><?= esc($etiquetaDia($dias[0])) ?><?= $dias[0] === $hoy ? ' <span class="badge-hoy ms-1">Hoy</span>' : '' ?></h2>
            <ul class="list-unstyled row row-cols-1 row-cols-md-2 row-cols-xxl-3 g-3 mb-0">
                <?php foreach ($filas as $f): ?>
                    <?php $lista = $visiblesDe($f, $dias[0]); ?>
                    <?php if ($lista === []) continue; ?>
                    <li class="col">
                        <section class="dia-lista h-100" aria-labelledby="tablero-sector-<?= (int) $f['sector']->id ?>">
                            <div class="dia-lista__cabecera">
                                <h3 class="dia-lista__titulo" id="tablero-sector-<?= (int) $f['sector']->id ?>">
                                    <?= esc($f['sector']->nombre) ?>
                                    <?php if ($f['sector']->esCritico()): ?><?= $badgeCritica ?><?php endif; ?>
                                </h3>
                                <span class="small text-body-secondary">Mínimo <?= (int) $f['sector']->dotacion_minima ?> por turno</span>
                            </div>
                            <ul class="tp-franjas dia-lista__cuerpo">
                                <?php foreach ($lista as [$t, $c]): ?>
                                    <?= view('admin/tablero/_franja', ['sector' => $f['sector'], 'tipo' => $t, 'dia' => $dias[0], 'celda' => $c, 'compacto' => false]) ?>
                                <?php endforeach; ?>
                            </ul>
                            <div class="px-2 pb-2">
                                <a class="btn btn-outline-secondary w-100" href="<?= base_url('admin/planificador') . '?' . http_build_query(['sector' => (int) $f['sector']->id, 'mes' => substr($dias[0], 0, 7)], '', '&amp;') ?>">
                                    <i class="bi bi-calendar-week me-1" aria-hidden="true"></i>Abrir en el Planificador<span class="visually-hidden"> (<?= esc($f['sector']->nombre) ?>)</span>
                                </a>
                            </div>
                        </section>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>
