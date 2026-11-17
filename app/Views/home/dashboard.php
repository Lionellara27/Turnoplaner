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
 */
$mesActual   = date('Y-m');
$esMesActual = $anioMes === $mesActual;
$urlInicio   = static fn (string $mes, ?int $secId = null): string => base_url('inicio') . '?' . http_build_query([
    'sector' => $secId ?? $sector?->id,
    'mes'    => $mes,
], '', '&amp;');

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
            $totalRequerido = count($tipos) * $minimoDia;

            foreach ($tipos as $t) {
                $asignados = $grilla[$dia][(int) $t->id] ?? [];
                $enfermerosLista = [];
                $activosCount    = 0;

                foreach ($asignados as $a) {
                    $deBaja = $a->enfermeroDeBaja();
                    if (! $deBaja) {
                        $activosCount++;
                    }
                    $enfermerosLista[] = [
                        'nombre'       => $a->nombreEnfermero(),
                        'matricula'    => $a->matriculaEnfermero(),
                        'especialidad' => $a->especialidadEnfermero(),
                        'de_baja'      => $deBaja,
                    ];
                }

                $totalAsignados += $activosCount;
                $turnosDia[] = [
                    'nombre'     => (string) $t->nombre,
                    'horario'    => $t->rangoTexto(),
                    'color'      => (string) $t->color,
                    'colorTexto' => \App\Entities\TipoTurno::textoSobre((string) $t->color),
                    'cubiertos'  => $activosCount,
                    'minimo'     => $minimoDia,
                    'enfermeros' => $enfermerosLista,
                ];
            }

            $detalleDias[$dia] = [
                'fecha'          => $dia,
                'titulo'         => ucfirst(dia_semana($dia)) . ', ' . fecha_ar($dia),
                'esHoy'          => $dia === $hoy,
                'totalAsignados' => $totalAsignados,
                'totalRequerido' => $totalRequerido,
                'urlPlanificar'  => base_url('admin/planificador') . '?' . http_build_query([
                    'sector' => $sector->id,
                    'fecha'  => $dia,
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

<!-- Alertas de cobertura del sector en el mes -->
<?php if ($sector !== null && $alertasCobertura !== []): ?>
    <div class="alerta alerta--aviso shadow-sm d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4" role="status">
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
        <a href="<?= base_url('admin/planificador') . '?' . http_build_query(['sector' => $sector->id], '', '&amp;') ?>" class="btn btn-sm btn-tp fw-semibold text-nowrap">
            <i class="bi bi-calendar-week me-1" aria-hidden="true"></i>Ir a planificar
        </a>
    </div>
<?php endif; ?>

<!-- Calendario Mensual Interactivo (Solo visualización + Modal de detalle por día) -->
<section class="tp-panel-card overflow-hidden" aria-labelledby="titulo-calendario-inicio">
    <div class="bg-white border-bottom py-3 px-4">
        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
            <div>
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <h2 class="h5 fw-bold mb-0" id="titulo-calendario-inicio">
                        <i class="bi bi-calendar3 me-1" style="color: var(--color-primario-oscuro);" aria-hidden="true"></i>
                        Cronograma mensual · <?= esc($nombreMes) ?>
                    </h2>
                    <?php if ($esMesActual && isset($detalleDias[$hoy])): ?>
                        <button type="button" class="btn btn-sm rounded-pill fw-semibold px-3 py-0"
                                style="background: var(--color-primario-suave); color: var(--color-primario-oscuro); border: 1px solid rgba(27,77,62,0.25); font-size: 0.78rem;"
                                data-abrir-dia="<?= esc($hoy, 'attr') ?>">
                            <i class="bi bi-circle-fill me-1" style="font-size: 0.45rem; vertical-align: middle;"></i>Ver hoy (<?= esc(fecha_ar($hoy)) ?>)
                        </button>
                    <?php endif; ?>
                </div>
                <p class="small text-body-secondary mb-0 mt-1">
                    Hacé clic en cualquier día para desplegar los turnos y los enfermeros asignados con su matrícula.
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
                    <a href="<?= base_url('admin/planificador') . '?' . http_build_query(['sector' => $sector->id], '', '&amp;') ?>" class="btn btn-tp btn-sm fw-semibold">
                        <i class="bi bi-pencil-square me-1" aria-hidden="true"></i>Gestionar en Planificador
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($sectores !== []): ?>
            <div class="mt-3 pt-3 border-top d-flex flex-wrap align-items-center gap-2" role="group" aria-label="Seleccionar zona">
                <span class="small fw-semibold text-body-secondary me-1">
                    <i class="bi bi-hospital me-1" aria-hidden="true"></i>Zona clínica:
                </span>
                <?php foreach ($sectores as $s): ?>
                    <?php $activoSec = $sector !== null && (int) $s->id === (int) $sector->id; ?>
                    <a href="<?= $urlInicio($anioMes, (int) $s->id) ?>"
                       class="btn btn-sm rounded-3 <?= $activoSec ? 'btn-tp fw-semibold shadow-sm' : 'btn-outline-secondary' ?>"
                       <?= $activoSec ? 'aria-current="page"' : '' ?>>
                        <?= esc($s->nombre) ?>
                        <?php if ($s->esCritico()): ?>
                            <span class="badge rounded-pill ms-1 <?= $activoSec ? 'bg-white text-dark' : 'bg-danger-subtle text-danger-emphasis' ?>">Crítica</span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="p-3 p-md-4 bg-white">
        <?php if ($sector === null): ?>
            <p class="text-body-secondary text-center py-4 mb-0">No hay sectores activos configurados.</p>
        <?php elseif ($tipos === []): ?>
            <p class="text-body-secondary text-center py-4 mb-0">
                La zona <strong><?= esc($sector->nombre) ?></strong> no tiene horarios (tipos de turno) configurados todavía.
            </p>
        <?php else: ?>
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 small text-body-secondary mb-3">
                <span>
                    <i class="bi bi-info-circle me-1" style="color: var(--color-primario-oscuro);" aria-hidden="true"></i>
                    Dotación mínima requerida en <strong><?= esc($sector->nombre) ?></strong>: <strong><?= esc($sector->dotacionTexto()) ?></strong>.
                </span>
                <div class="d-flex flex-wrap gap-2">
                    <span class="tp-turno-pill__cupo tp-cupo--ok"><i class="bi bi-check-circle-fill me-1"></i>Cubierto</span>
                    <span class="tp-turno-pill__cupo tp-cupo--parcial"><i class="bi bi-exclamation-circle-fill me-1"></i>Incompleto</span>
                    <span class="tp-turno-pill__cupo tp-cupo--vacio"><i class="bi bi-x-circle-fill me-1"></i>Sin cubrir</span>
                </div>
            </div>

            <!-- Calendario mensual (Escritorio) -->
            <div class="d-none d-lg-block rounded-3 border overflow-hidden">
                <table class="table table-bordered mb-0 tp-calendario">
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
                                    ?>
                                    <?php if (! $perteneceMes): ?>
                                        <td class="tp-celda-dia--fuera">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <span class="small fw-medium"><?= (int) substr($dia, 8, 2) ?></span>
                                            </div>
                                            <div class="text-center py-4 opacity-50">—</div>
                                        </td>
                                    <?php else: ?>
                                        <td class="tp-celda-dia <?= $esHoy ? 'tp-celda-dia--hoy' : '' ?>"
                                            role="button"
                                            tabindex="0"
                                            data-abrir-dia="<?= esc($dia, 'attr') ?>"
                                            aria-label="Ver detalle del <?= esc(dia_semana($dia) . ' ' . fecha_ar($dia), 'attr') ?>"
                                            <?= $esHoy ? 'aria-current="date"' : '' ?>>
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <div class="d-flex align-items-center gap-1">
                                                    <span class="tp-num-dia"><?= (int) substr($dia, 8, 2) ?></span>
                                                    <?php if ($esHoy): ?>
                                                        <span class="badge rounded-pill text-uppercase" style="background: var(--color-primario-oscuro); font-size: 0.62rem;">Hoy</span>
                                                    <?php endif; ?>
                                                </div>
                                                <i class="bi bi-arrows-angle-expand text-body-secondary opacity-50" style="font-size: 0.72rem;" aria-hidden="true"></i>
                                            </div>

                                            <div class="d-flex flex-column gap-1">
                                                <?php foreach ($tipos as $t): ?>
                                                    <?php
                                                    $asignados = $grilla[$dia][(int) $t->id] ?? [];
                                                    $activosT  = array_values(array_filter($asignados, static fn ($a): bool => ! $a->enfermeroDeBaja()));
                                                    $cant      = count($activosT);
                                                    $min       = (int) $sector->dotacion_minima;
                                                    $claseCupo = $cant >= $min ? 'tp-cupo--ok' : ($cant > 0 ? 'tp-cupo--parcial' : 'tp-cupo--vacio');
                                                    ?>
                                                    <div class="tp-turno-pill" style="--color-turno: <?= esc($t->color, 'attr') ?>;">
                                                        <span class="tp-turno-pill__nombre text-truncate"><?= esc($t->nombre) ?></span>
                                                        <span class="tp-turno-pill__cupo <?= $claseCupo ?>"><?= $cant ?>/<?= $min ?></span>
                                                    </div>
                                                    <?php if ($activosT !== []): ?>
                                                        <div class="px-1 text-truncate text-body-secondary" style="font-size: 0.68rem;">
                                                            <?= esc(implode(' · ', array_map(static fn ($a): string => $a->nombreEnfermero(), $activosT))) ?>
                                                        </div>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                            </div>
                                        </td>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Vista Móvil (al tocar un día abre el mismo modal) -->
            <div class="d-lg-none d-flex flex-column gap-2">
                <?php foreach ($semanas as $semana): ?>
                    <?php foreach ($semana as $dia): ?>
                        <?php if (substr($dia, 0, 7) !== $anioMes) continue; ?>
                        <?php $esHoy = $dia === $hoy; ?>
                        <div class="border rounded-3 p-3 tp-celda-dia <?= $esHoy ? 'tp-celda-dia--hoy' : '' ?>"
                             role="button" tabindex="0" data-abrir-dia="<?= esc($dia, 'attr') ?>">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="fw-bold text-capitalize"><?= esc(dia_semana($dia)) ?> <?= esc(fecha_ar($dia)) ?></span>
                                <div class="d-flex align-items-center gap-2">
                                    <?php if ($esHoy): ?><span class="badge rounded-pill" style="background: var(--color-primario-oscuro);">Hoy</span><?php endif; ?>
                                    <span class="small text-body-secondary">Ver detalle <i class="bi bi-chevron-right"></i></span>
                                </div>
                            </div>
                            <div class="d-flex flex-column gap-1">
                                <?php foreach ($tipos as $t): ?>
                                    <?php
                                    $asignados = $grilla[$dia][(int) $t->id] ?? [];
                                    $cant      = count(array_filter($asignados, static fn ($a): bool => ! $a->enfermeroDeBaja()));
                                    $min       = (int) $sector->dotacion_minima;
                                    $claseCupo = $cant >= $min ? 'tp-cupo--ok' : ($cant > 0 ? 'tp-cupo--parcial' : 'tp-cupo--vacio');
                                    ?>
                                    <div class="tp-turno-pill" style="--color-turno: <?= esc($t->color, 'attr') ?>;">
                                        <span class="tp-turno-pill__nombre"><?= esc($t->nombre) ?></span>
                                        <span class="tp-turno-pill__cupo <?= $claseCupo ?>"><?= $cant ?>/<?= $min ?></span>
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

<!-- Ventana Modal de Detalle del Día (Enfermeros y Matrículas) -->
<div class="modal fade" id="modalDetalleDia" tabindex="-1" aria-labelledby="modalDetalleTitulo" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header border-bottom py-3 px-4" style="background: #f1f5f3;">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-3 d-flex align-items-center justify-content-center text-white flex-shrink-0"
                         style="width: 42px; height: 42px; background: var(--color-primario-oscuro);">
                        <i class="bi bi-calendar-event fs-5" aria-hidden="true"></i>
                    </div>
                    <div>
                        <div class="d-flex align-items-center gap-2">
                            <h3 class="modal-title h5 fw-bold mb-0" id="modalDetalleTitulo">Detalle del día</h3>
                            <span id="modalBadgeHoy" class="badge rounded-pill text-uppercase d-none" style="background: var(--color-primario-oscuro); font-size: 0.65rem;">Hoy</span>
                        </div>
                        <p class="small text-body-secondary mb-0">
                            Personal asignado en <strong style="color: var(--color-primario-oscuro);"><?= esc($sector?->nombre ?? '') ?></strong>
                        </p>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>

            <div class="modal-body p-4" style="background: var(--color-fondo);">
                <!-- Resumen superior de cobertura del día -->
                <div id="modalResumenDia" class="rounded-3 p-3 mb-3 d-flex flex-wrap justify-content-between align-items-center gap-2"
                     style="background: var(--color-primario-suave); border: 1px solid rgba(27,77,62,0.2);">
                    <span class="small fw-semibold" style="color: var(--color-primario-oscuro);">
                        <i class="bi bi-people-fill me-1" aria-hidden="true"></i>
                        <span id="modalResumenTexto">0 de 0 enfermeros/as asignados</span>
                    </span>
                    <span id="modalResumenBadge" class="tp-turno-pill__cupo tp-cupo--ok">Cubierto</span>
                </div>

                <!-- Contenedor dinámico de turnos -->
                <div id="modalTurnosLista" class="d-flex flex-column gap-3"></div>
            </div>

            <div class="modal-footer border-top bg-white px-4 py-3 justify-content-between">
                <span class="small text-body-secondary">
                    <i class="bi bi-shield-check me-1" style="color: var(--color-primario-oscuro);"></i>Vista de supervisión
                </span>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
                    <a id="modalBtnPlanificar" href="<?= base_url('admin/planificador') ?>" class="btn btn-tp fw-semibold">
                        <i class="bi bi-pencil-square me-1" aria-hidden="true"></i>Gestionar en Planificador
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const datosDias = <?= json_encode($detalleDias, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
    const modalEl   = document.getElementById('modalDetalleDia');
    if (!modalEl || typeof bootstrap === 'undefined') return;

    const modal       = new bootstrap.Modal(modalEl);
    const tituloEl    = document.getElementById('modalDetalleTitulo');
    const badgeHoyEl  = document.getElementById('modalBadgeHoy');
    const resumenTxt  = document.getElementById('modalResumenTexto');
    const resumenBdg  = document.getElementById('modalResumenBadge');
    const listaTurnos = document.getElementById('modalTurnosLista');
    const btnPlanif   = document.getElementById('modalBtnPlanificar');

    function escapar(str) {
        const div = document.createElement('div');
        div.textContent = str ?? '';
        return div.innerHTML;
    }

    function abrirModalDia(fecha) {
        const info = datosDias[fecha];
        if (!info) return;

        tituloEl.textContent = info.titulo;
        badgeHoyEl.classList.toggle('d-none', !info.esHoy);
        btnPlanif.href = info.urlPlanificar;

        resumenTxt.textContent = info.totalAsignados + ' de ' + info.totalRequerido + ' puestos mínimos cubiertos en el día';
        if (info.totalAsignados >= info.totalRequerido && info.totalRequerido > 0) {
            resumenBdg.className = 'tp-turno-pill__cupo tp-cupo--ok';
            resumenBdg.textContent = '100% Cubierto';
        } else if (info.totalAsignados > 0) {
            resumenBdg.className = 'tp-turno-pill__cupo tp-cupo--parcial';
            resumenBdg.textContent = 'Cobertura parcial';
        } else {
            resumenBdg.className = 'tp-turno-pill__cupo tp-cupo--vacio';
            resumenBdg.textContent = 'Sin asignar';
        }

        listaTurnos.innerHTML = info.turnos.map(function (turno) {
            const claseCupo = turno.cubiertos >= turno.minimo
                ? 'tp-cupo--ok'
                : (turno.cubiertos > 0 ? 'tp-cupo--parcial' : 'tp-cupo--vacio');

            let cuerpo = '';
            if (turno.enfermeros.length === 0) {
                cuerpo = '<p class="small text-body-secondary mb-0 fst-italic">Ningún enfermero/a asignado en esta franja.</p>';
            } else {
                cuerpo = '<div class="row g-2">' + turno.enfermeros.map(function (enf) {
                    const esp = enf.especialidad
                        ? '<span class="badge rounded-pill" style="background: var(--color-primario-suave); color: var(--color-primario-oscuro); font-size: 0.68rem;">' + escapar(enf.especialidad) + '</span>'
                        : '';
                    const baja = enf.de_baja
                        ? '<span class="badge bg-danger-subtle text-danger-emphasis ms-1" style="font-size: 0.65rem;">Baja</span>'
                        : '';
                    return '<div class="col-12 col-md-4">' +
                        '<div class="tp-enfermero-card h-100">' +
                            '<div class="d-flex justify-content-between align-items-start gap-1 mb-1">' +
                                esp +
                                '<i class="bi bi-person-badge" style="color: var(--color-primario-oscuro);"></i>' +
                            '</div>' +
                            '<div class="fw-semibold text-dark small">' + escapar(enf.nombre) + baja + '</div>' +
                            '<div class="tp-matricula mt-1">Mat: <strong>' + escapar(enf.matricula) + '</strong></div>' +
                        '</div>' +
                    '</div>';
                }).join('') + '</div>';
            }

            return '<section class="rounded-3 bg-white border p-3 shadow-sm">' +
                '<div class="d-flex justify-content-between align-items-center pb-2 mb-2 border-bottom">' +
                    '<div class="d-flex align-items-center gap-2">' +
                        '<span class="badge rounded-pill px-2 py-1" style="background-color:' + escapar(turno.color) + '; color:' + escapar(turno.colorTexto) + ';">' +
                            escapar(turno.nombre) +
                        '</span>' +
                        '<span class="small text-body-secondary">' + escapar(turno.horario) + '</span>' +
                    '</div>' +
                    '<span class="tp-turno-pill__cupo ' + claseCupo + '">' + turno.cubiertos + '/' + turno.minimo + ' asignados</span>' +
                '</div>' +
                cuerpo +
            '</section>';
        }).join('');

        modal.show();
    }

    document.querySelectorAll('[data-abrir-dia]').forEach(function (el) {
        el.addEventListener('click', function () {
            abrirModalDia(this.getAttribute('data-abrir-dia'));
        });
        el.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                abrirModalDia(this.getAttribute('data-abrir-dia'));
            }
        });
    });
});
</script>
<?= $this->endSection() ?>