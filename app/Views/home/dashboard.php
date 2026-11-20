<?php
/**
 * Panel general del Administrador (HU-02) - Estilo TurnoPlaner Sage & Pine:
 * Métricas del plantel, alertas de cobertura y calendario mensual interactivo de solo lectura
 * con ventana modal de detalle por día (turnos, enfermeros asignados y matrículas).
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
 * @var int                                                          $ausenciasPendientes HU-16: ausencias pendientes de resolver
 */
$mesActual   = date('Y-m');
$esMesActual = $anioMes === $mesActual;
$urlInicio   = static fn (string $mes, ?int $secId = null): string => base_url('inicio') . '?' . http_build_query([
    'sector' => $secId ?? $sector?->id,
    'mes'    => $mes,
], '', '&amp;');

// Vigencia histórica: muestra cada tipo de turno solo desde su creación y conserva los dados de baja en el pasado
$turnoAplicaEnDia = static function (\App\Entities\TipoTurno $t, string $dia, array $asignados): bool {
    if ($asignados !== []) {
        return true;
    }
    $creado = $t->created_at ? substr((string) $t->created_at, 0, 10) : null;
    if ($creado !== null && $dia < $creado) {
        return false;
    }
    if (! $t->estaActivo()) {
        $baja = $t->deleted_at ? substr((string) $t->deleted_at, 0, 10) : date('Y-m-d');
        if ($dia >= $baja) {
            return false;
        }
    }
    return true;
};

// Prepara estructura JSON segura para alimentar la ventana modal al hacer clic en un día
$detalleDias = [];
if ($sector !== null && $tipos !== []) {
    $minimoDia = (int) $sector->dotacion_minima;
    foreach ($semanas as $semana) {
        foreach ($semana as $dia) {
            if (substr($dia, 0, 7) !== $anioMes) {
                continue;
            }
            $turnosDia      = [];
            $totalAsignados = 0;
            $tiposVigentes  = 0;

            foreach ($tipos as $t) {
                $asignados = $grilla[$dia][(int) $t->id] ?? [];
                if (! $turnoAplicaEnDia($t, $dia, $asignados)) {
                    continue;
                }
                $tiposVigentes++;

                $enfermerosLista = [];
                $activosCount    = 0;

                foreach ($asignados as $a) {
                    $deBaja = $a->enfermeroDeBaja();
                    if ($a->cuentaEnDotacion()) {
                        $activosCount++;
                    }
                    $enfermerosLista[] = [
                        'nombre'    => $a->nombreEnfermero(),
                        'matricula' => $a->matriculaEnfermero(),
                        'de_baja'   => $deBaja,
                    ];
                }

                $totalAsignados += $activosCount;
                $turnosDia[] = [
                    'nombre'     => (string) $t->nombre,
                    'horario'    => $t->rangoTexto(),
                    'color'      => (string) $t->color,
                    'cubiertos'  => $activosCount,
                    'minimo'     => $minimoDia,
                    'enfermeros' => $enfermerosLista,
                ];
            }

            $detalleDias[$dia] = [
                'fecha'          => $dia,
                'titulo'         => ucfirst(dia_semana($dia)) . ' ' . fecha_ar($dia),
                'esHoy'          => $dia === $hoy,
                'totalAsignados' => $totalAsignados,
                'totalRequerido' => $tiposVigentes * $minimoDia,
                'urlPlanificar'  => base_url('admin/planificador') . '?' . http_build_query([
                    'sector' => $sector->id,
                    'mes'    => substr($dia, 0, 7),
                ]),
                'turnos'         => $turnosDia,
            ];
        }
    }
}
?>
<?= $this->extend('layouts/main') ?>

<?= $this->section('contenido') ?>
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h1 class="h3 fw-bold mb-1">Hola, <?= esc($nombre) ?></h1>
        <p class="text-body-secondary mb-0">Panel de gestión y supervisión mensual de dotación</p>
    </div>
    <a href="<?= base_url('admin/usuarios/crear') ?>" class="btn btn-tp px-4 py-2 fw-semibold shadow-sm rounded-3">
        <i class="bi bi-person-plus me-1" aria-hidden="true"></i>Dar de alta enfermero/a
    </a>
</div>

<h2 class="visually-hidden">Resumen del plantel</h2>
<section class="row g-3 mb-4" aria-label="Resumen del plantel">
    <div class="col-12 col-md-4">
        <a href="<?= base_url('admin/usuarios') ?>" class="card border-0 tp-panel-card tarjeta-indicador indicador--ok text-decoration-none h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <span class="indicador__icono" aria-hidden="true"><i class="bi bi-person-check"></i></span>
                <div>
                    <div class="numero text-dark"><?= esc((string) $activos) ?></div>
                    <div class="text-body-secondary small fw-medium">Enfermeros/as activos</div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-12 col-md-4">
        <a href="<?= base_url('admin/usuarios?estado=PENDIENTE') ?>" class="card border-0 tp-panel-card tarjeta-indicador indicador--pendiente text-decoration-none h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <span class="indicador__icono" aria-hidden="true"><i class="bi bi-hourglass-split"></i></span>
                <div>
                    <div class="numero text-dark"><?= esc((string) $pendientes) ?></div>
                    <div class="text-body-secondary small fw-medium">Pendientes de activación</div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-12 col-md-4">
        <a href="<?= base_url('admin/usuarios?estado=BAJA') ?>" class="card border-0 tp-panel-card tarjeta-indicador indicador--baja text-decoration-none h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <span class="indicador__icono" aria-hidden="true"><i class="bi bi-person-slash"></i></span>
                <div>
                    <div class="numero text-dark"><?= esc((string) $bajas) ?></div>
                    <div class="text-body-secondary small fw-medium">Dados de baja</div>
                </div>
            </div>
        </a>
    </div>
</section>

<!-- HU-16: ausencias informadas que esperan reemplazo o rechazo -->
<?php $ausenciasPendientes = (int) ($ausenciasPendientes ?? 0); ?>
<?php if ($ausenciasPendientes > 0): ?>
    <div class="alerta alerta--error d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4" role="status">
        <div class="fw-semibold">
            <i class="bi bi-person-dash-fill me-1" aria-hidden="true"></i>
            <?= $ausenciasPendientes === 1 ? 'Hay 1 ausencia informada pendiente de resolver.' : 'Hay ' . $ausenciasPendientes . ' ausencias informadas pendientes de resolver.' ?>
            <span class="d-block small fw-normal mt-1">Asigne un reemplazo o rechace el aviso para que el turno no quede descubierto.</span>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="<?= base_url('admin/tablero') ?>" class="btn btn-outline-secondary bg-white fw-semibold text-nowrap">
                <i class="bi bi-grid-3x3-gap me-1" aria-hidden="true"></i>Tablero de cobertura
            </a>
            <a href="<?= base_url('admin/ausencias') ?>" class="btn btn-outline-secondary bg-white fw-semibold text-nowrap">
                <i class="bi bi-person-dash me-1" aria-hidden="true"></i>Gestionar ausencias
            </a>
        </div>
    </div>
<?php endif; ?>

<!-- Alertas de cobertura del sector en el mes -->
<?php if ($sector !== null && $alertasCobertura !== []): ?>
    <div class="alerta alerta--aviso d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4" role="status">
        <div>
            <div class="fw-semibold">
                <i class="bi bi-exclamation-triangle-fill me-1" aria-hidden="true"></i>
                Atención: hay <?= count($alertasCobertura) ?> <?= count($alertasCobertura) === 1 ? 'turno sin dotación mínima' : 'turnos sin dotación mínima' ?> en <?= esc($sector->nombre) ?> durante <?= esc($nombreMes) ?>
            </div>
            <div class="small mt-1">
                Próximos descubiertos:
                <?php foreach (array_slice($alertasCobertura, 0, 3) as $idx => $al): ?>
                    <?= $idx > 0 ? ' · ' : '' ?>
                    <strong><?= esc(fecha_ar($al['fecha'])) ?></strong> (<?= esc($al['turno']) ?>: <?= (int) $al['cubiertos'] ?> de <?= (int) $al['minimo'] ?>)
                <?php endforeach; ?>
                <?= count($alertasCobertura) > 3 ? '…' : '' ?>
            </div>
        </div>
        <a href="<?= base_url('admin/planificador') . '?' . http_build_query(['sector' => $sector->id, 'mes' => $anioMes], '', '&amp;') ?>" class="btn btn-outline-secondary bg-white fw-semibold text-nowrap">
            <i class="bi bi-calendar-week me-1" aria-hidden="true"></i>Ir al Planificador
        </a>
    </div>
<?php endif; ?>

<!-- Calendario mensual de solo lectura: cada día abre el detalle con el personal asignado -->
<section class="tp-panel-card overflow-hidden" aria-labelledby="titulo-calendario-inicio">
    <div class="bg-white border-bottom py-3 px-3 px-md-4">
        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
            <div>
                <h2 class="h5 fw-bold mb-0" id="titulo-calendario-inicio">
                    <i class="bi bi-calendar3 me-1" aria-hidden="true"></i>Cronograma mensual · <?= esc($nombreMes) ?>
                </h2>
                <p class="small text-body-secondary mb-0 mt-1">
                    Seleccione un día para ver los turnos y el personal asignado con su matrícula.
                </p>
            </div>

            <div class="d-flex flex-wrap align-items-center gap-2">
                <?php if ($esMesActual && isset($detalleDias[$hoy])): ?>
                    <button type="button" class="btn btn-ver-hoy rounded-pill px-3" data-abrir-dia="<?= esc($hoy, 'attr') ?>">
                        <i class="bi bi-calendar-check me-1" aria-hidden="true"></i>Ver hoy (<?= esc(fecha_ar($hoy)) ?>)
                    </button>
                <?php endif; ?>
                <nav aria-label="Cambiar de mes">
                    <div class="btn-group nav-mes" role="group">
                        <a class="btn btn-outline-secondary" href="<?= $urlInicio($anteriorMes) ?>">
                            <i class="bi bi-chevron-left d-none d-sm-inline-block" aria-hidden="true"></i> Anterior
                        </a>
                        <a class="btn btn-outline-secondary<?= $esMesActual ? ' active' : '' ?>" href="<?= $urlInicio($mesActual) ?>"<?= $esMesActual ? ' aria-current="date"' : '' ?>>
                            Mes actual
                        </a>
                        <a class="btn btn-outline-secondary" href="<?= $urlInicio($siguienteMes) ?>">
                            Siguiente <i class="bi bi-chevron-right d-none d-sm-inline-block" aria-hidden="true"></i>
                        </a>
                    </div>
                </nav>
                <?php if ($sector !== null): ?>
                    <a href="<?= base_url('admin/planificador') . '?' . http_build_query(['sector' => $sector->id, 'mes' => $anioMes], '', '&amp;') ?>" class="btn btn-outline-secondary fw-semibold">
                        <i class="bi bi-pencil-square me-1" aria-hidden="true"></i>Gestionar en el Planificador
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($sectores !== []): ?>
            <div class="mt-3 pt-3 border-top">
                <p class="small fw-semibold text-body-secondary mb-1" id="titulo-selector-sector">
                    <i class="bi bi-hospital me-1" aria-hidden="true"></i>Sector
                </p>
                <div class="selector-sector d-flex flex-wrap gap-2" role="group" aria-labelledby="titulo-selector-sector">
                    <?php foreach ($sectores as $s): ?>
                        <?php $activoSec = $sector !== null && (int) $s->id === (int) $sector->id; ?>
                        <a href="<?= $urlInicio($anioMes, (int) $s->id) ?>" class="btn btn-outline-secondary"<?= $activoSec ? ' aria-current="page"' : '' ?>>
                            <?php if ($activoSec): ?><i class="bi bi-check2" aria-hidden="true"></i><?php endif; ?>
                            <?= esc($s->nombre) ?>
                            <?php if ($s->esCritico()): ?>
                                <span class="badge rounded-pill bg-danger-subtle text-danger-emphasis border border-danger-subtle">Crítica</span>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="p-3 p-md-4 bg-white">
        <?php if ($sector === null): ?>
            <p class="text-body-secondary text-center py-4 mb-0">No hay sectores activos configurados.</p>
        <?php elseif ($tipos === []): ?>
            <p class="text-body-secondary text-center py-4 mb-0">
                El sector <strong><?= esc($sector->nombre) ?></strong> todavía no tiene tipos de turno configurados.
            </p>
        <?php else: ?>
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 small text-body-secondary mb-3">
                <span>
                    <i class="bi bi-info-circle me-1" aria-hidden="true"></i>
                    Dotación mínima en <strong><?= esc($sector->nombre) ?></strong>: <strong><?= esc($sector->dotacionTexto()) ?></strong>.
                </span>
                <div class="d-flex flex-wrap gap-2">
                    <span class="cobertura cobertura--ok"><i class="bi bi-check-circle-fill" aria-hidden="true"></i>Cubierto</span>
                    <span class="cobertura cobertura--parcial"><i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>Faltan</span>
                    <span class="cobertura cobertura--vacio"><i class="bi bi-x-circle-fill" aria-hidden="true"></i>Sin cubrir</span>
                </div>
            </div>

            <!-- Desde 1200 px: tabla mensual (toda la celda es un botón) -->
            <div class="d-none d-xl-block rounded-3 border overflow-hidden">
                <table class="table table-bordered mb-0 tp-calendario">
                    <caption class="visually-hidden">Calendario mensual de <?= esc($sector->nombre) ?>, <?= esc($nombreMes) ?>. Cada día muestra la cobertura de sus turnos.</caption>
                    <thead class="text-center">
                        <tr>
                            <th scope="col">Lunes</th>
                            <th scope="col">Martes</th>
                            <th scope="col">Miércoles</th>
                            <th scope="col">Jueves</th>
                            <th scope="col">Viernes</th>
                            <th scope="col" class="cal-mes__finde">Sábado</th>
                            <th scope="col" class="cal-mes__finde">Domingo</th>
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
                                    $numDia       = (int) substr($dia, 8, 2);
                                    ?>
                                    <?php if (! $perteneceMes): ?>
                                        <td class="tp-celda-dia--fuera">
                                            <span class="tp-num-dia text-body-secondary"><?= $numDia ?>/<?= substr($dia, 5, 2) ?></span>
                                            <span class="visually-hidden">(otro mes)</span>
                                        </td>
                                    <?php else: ?>
                                        <td class="tp-celda-dia tp-celda-dia--con-boton<?= $esHoy ? ' tp-celda-dia--hoy' : ($esPasado ? ' tp-celda-dia--pasado' : '') ?>"<?= $esHoy ? ' aria-current="date"' : '' ?>>
                                            <button type="button" class="tp-celda-dia__boton" data-abrir-dia="<?= esc($dia, 'attr') ?>">
                                                <span class="d-inline-flex align-items-center gap-1" aria-hidden="true">
                                                    <span class="tp-num-dia"><?= $numDia ?></span>
                                                    <?php if ($esHoy): ?><span class="badge-hoy">Hoy</span><?php endif; ?>
                                                </span>
                                                <span class="visually-hidden">Ver día <?= $numDia ?><?= $esHoy ? ', hoy' : '' ?> (<?= esc(fecha_larga($dia)) ?>).</span>
                                                <?php foreach ($tipos as $t): ?>
                                                    <?php
                                                    $asignados = $grilla[$dia][(int) $t->id] ?? [];
                                                    if (! $turnoAplicaEnDia($t, $dia, $asignados)) {
                                                        continue;
                                                    }
                                                    $cant = count(array_filter($asignados, static fn ($a): bool => $a->cuentaEnDotacion()));
                                                    ?>
                                                    <span class="tp-turno-pill" style="--color-turno: <?= esc($t->color, 'attr') ?>;">
                                                        <span class="tp-turno-pill__nombre"><?= esc($t->nombre) ?></span>
                                                        <?= view('admin/planificador/_cobertura', ['cubiertos' => $cant, 'minimo' => (int) $sector->dotacion_minima]) ?>
                                                    </span>
                                                <?php endforeach; ?>
                                            </button>
                                        </td>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Debajo de 1200 px: un botón por día (en el mes actual, los días pasados quedan plegados) -->
            <?php
            $diasMes        = [];
            foreach ($semanas as $semana) {
                foreach ($semana as $dia) {
                    if (substr($dia, 0, 7) === $anioMes) {
                        $diasMes[] = $dia;
                    }
                }
            }
            $diasAnteriores = $esMesActual ? array_values(array_filter($diasMes, static fn (string $d): bool => $d < $hoy)) : [];
            $diasVisibles   = array_values(array_diff($diasMes, $diasAnteriores));
            $pintarDia      = static function (string $dia) use ($hoy, $tipos, $grilla, $turnoAplicaEnDia, $sector): void {
                $esHoy    = $dia === $hoy;
                $esPasado = $dia < $hoy;
                ?>
                <li class="col">
                    <div class="dia-lista h-100<?= $esHoy ? ' dia-lista--hoy' : ($esPasado ? ' dia-lista--pasado' : '') ?>"<?= $esHoy ? ' aria-current="date"' : '' ?>>
                        <button type="button" class="dia-lista__boton h-100" data-abrir-dia="<?= esc($dia, 'attr') ?>">
                            <span class="dia-lista__cabecera">
                                <span class="dia-lista__titulo">
                                    <span class="text-capitalize"><?= esc(dia_semana($dia)) ?></span> <?= esc(fecha_ar($dia)) ?>
                                    <?php if ($esHoy): ?><span class="badge-hoy ms-1">Hoy</span><?php elseif ($esPasado): ?><span class="small fw-normal text-body-secondary ms-1">(pasado)</span><?php endif; ?>
                                </span>
                                <span class="small text-body-secondary">Ver detalle <i class="bi bi-chevron-right" aria-hidden="true"></i></span>
                            </span>
                            <span class="dia-lista__cuerpo d-flex flex-column gap-1">
                                <?php foreach ($tipos as $t): ?>
                                    <?php
                                    $asignados = $grilla[$dia][(int) $t->id] ?? [];
                                    if (! $turnoAplicaEnDia($t, $dia, $asignados)) {
                                        continue;
                                    }
                                    $cant = count(array_filter($asignados, static fn ($a): bool => $a->cuentaEnDotacion()));
                                    ?>
                                    <span class="tp-turno-pill" style="--color-turno: <?= esc($t->color, 'attr') ?>;">
                                        <span class="tp-turno-pill__nombre"><?= esc($t->nombre) ?></span>
                                        <?= view('admin/planificador/_cobertura', ['cubiertos' => $cant, 'minimo' => (int) $sector->dotacion_minima]) ?>
                                    </span>
                                <?php endforeach; ?>
                            </span>
                        </button>
                    </div>
                </li>
                <?php
            };
            ?>
            <div class="d-xl-none">
                <h3 class="visually-hidden">Días de <?= esc($nombreMes) ?></h3>
                <?php if ($diasAnteriores !== []): ?>
                    <details class="plan-dias-anteriores mb-2">
                        <summary>
                            <i class="bi bi-clock-history" aria-hidden="true"></i>
                            Ver <?= count($diasAnteriores) === 1 ? 'el día anterior' : 'los ' . count($diasAnteriores) . ' días anteriores' ?> de este mes
                        </summary>
                        <ul class="list-unstyled row row-cols-1 row-cols-md-2 g-2 mb-0">
                            <?php foreach ($diasAnteriores as $dia) { $pintarDia($dia); } ?>
                        </ul>
                    </details>
                <?php endif; ?>
                <ul class="list-unstyled row row-cols-1 row-cols-md-2 g-2 mb-0">
                    <?php foreach ($diasVisibles as $dia) { $pintarDia($dia); } ?>
                </ul>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php if ($detalleDias !== []): ?>
    <script type="application/json" id="datos-panel-dias"><?= json_encode($detalleDias, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>

    <!-- Modal: detalle del día (personal asignado y matrículas) -->
    <div class="modal fade" id="modalDetalleDia" tabindex="-1" aria-labelledby="modalDetalleTitulo" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable modal-fullscreen-sm-down">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header tp-modal-cabecera py-3 px-4">
                    <div class="d-flex align-items-center gap-3 min-w-0">
                        <span class="tp-icono-circulo" aria-hidden="true"><i class="bi bi-calendar-event"></i></span>
                        <div class="min-w-0">
                            <h2 class="modal-title h5 fw-bold mb-0" id="modalDetalleTitulo">Detalle del día</h2>
                            <p class="small text-body-secondary mb-0">
                                Personal asignado en <strong><?= esc($sector?->nombre ?? '') ?></strong>
                                <span id="modalBadgeHoy" class="badge-hoy ms-1 d-none">Hoy</span>
                            </p>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>

                <div class="modal-body tp-modal-cuerpo p-3 p-md-4">
                    <div class="tp-resumen-dia mb-3">
                        <span><i class="bi bi-people-fill me-1" aria-hidden="true"></i><span id="modalResumenTexto">Dotación del día</span></span>
                        <span id="modalResumenEstado"></span>
                    </div>
                    <div id="modalTurnosLista" class="d-flex flex-column gap-3"></div>
                </div>

                <div class="modal-footer bg-white px-4 py-3">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
                    <a id="modalBtnPlanificar" href="<?= base_url('admin/planificador') ?>" class="btn btn-tp fw-semibold">
                        <i class="bi bi-pencil-square me-1" aria-hidden="true"></i>Gestionar en el Planificador
                    </a>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>
<?= $this->endSection() ?>
