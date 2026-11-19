<?php
/**
 * HU-14: Calendario mensual de turnos por zona/sector (Planificador) - Estética Stitch Sage & Pine.
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
 * @var array<int, list<array<string, mixed>>>                       $turnosPorEnfermero
 */
$enfermeros         = $enfermeros ?? [];
$especialidades     = $especialidades ?? \App\Models\UsuarioModel::ESPECIALIDADES;
$resumenMes         = $resumenMes ?? [];
$turnosPorEnfermero = $turnosPorEnfermero ?? [];
$mesActual          = date('Y-m');
$urlMes             = static fn (string $mes, ?int $secId = null): string => base_url('admin/planificador') . '?' . http_build_query([
    'sector' => $secId ?? $sector?->id,
    'mes'    => $mes,
], '', '&amp;');
$esMesActual = $anioMes === $mesActual;

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

// Prepara datos estructurados por día para alimentar el Drawer lateral derecho
$detallePlanificador = [];
if ($sector !== null && $tipos !== []) {
    $minimoDia = (int) $sector->dotacion_minima;
    $maxHab    = $fechaMaxima ?? \App\Models\AsignacionModel::fechaMaximaHabilitada();

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

                $terminado    = \App\Models\AsignacionModel::calcularHorario($dia, $t)['fin'] <= $ahora;
                $fueraVentana = $dia > $maxHab;
                $puedeAsignar = ! $terminado && ! $fueraVentana && $t->estaActivo();

                $listaEnf     = [];
                $activosCount = 0;
                foreach ($asignados as $a) {
                    $deBaja = $a->enfermeroDeBaja();
                    if (! $deBaja) {
                        $activosCount++;
                    }
                    $listaEnf[] = [
                        'id'         => (int) $a->id,
                        'usuario_id' => (int) $a->usuario_id,
                        'nombre'     => $a->nombreEnfermero(),
                        'matricula'  => $a->matriculaEnfermero(),
                        'es_futura'  => $a->esFutura(),
                        'es_extra'   => $a->esExtra(),
                        'de_baja'    => $deBaja,
                        'url_editar' => base_url('admin/asignaciones/' . $a->id . '/editar'),
                        'desc_quitar'=> $a->nombreEnfermero() . ' · ' . $t->nombre . ' del ' . fecha_larga($dia),
                    ];
                }

                $totalAsignados += $activosCount;
                $turnosDia[] = [
                    'tipo_id'       => (int) $t->id,
                    'nombre'        => (string) $t->nombre,
                    'horario'       => $t->rangoTexto(),
                    'color'         => (string) $t->color,
                    'cubiertos'     => $activosCount,
                    'minimo'        => $minimoDia,
                    'puede_asignar' => $puedeAsignar,
                    'url_asignar'   => base_url('admin/asignaciones/crear') . '?' . http_build_query([
                        'sector' => (int) $sector->id,
                        'tipo'   => (int) $t->id,
                        'fecha'  => $dia,
                    ]),
                    'desc_asignar'  => $t->nombre . ' (' . $t->rangoTexto() . ') del ' . fecha_larga($dia) . ' en ' . $sector->nombre,
                    'enfermeros'    => $listaEnf,
                ];
            }

            $detallePlanificador[$dia] = [
                'fecha'          => $dia,
                'numDia'         => (int) substr($dia, 8, 2),
                'titulo'         => ucfirst(dia_semana($dia)) . ', ' . fecha_ar($dia),
                'esHoy'          => $dia === $hoy,
                'esPasado'       => $dia < $hoy,
                'sectorId'       => (int) $sector->id,
                'totalAsignados' => $totalAsignados,
                'totalRequerido' => $tiposVigentes * $minimoDia,
                'turnos'         => $turnosDia,
            ];
        }
    }
}
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
                        <i class="bi bi-hospital me-1" aria-hidden="true"></i>Zona clínica:
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

                <!-- Leyenda Compacta de Turnos y Estados -->
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 small mb-3">
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <span class="text-uppercase fw-semibold text-body-secondary" style="font-size: 0.7rem; letter-spacing: 0.04em;">Turnos:</span>
                        <?php foreach ($tipos as $t): ?>
                            <?php if (! $t->estaActivo()) continue; ?>
                            <span class="etiqueta-turno py-1 px-2" style="--color-turno: <?= esc($t->color, 'attr') ?>; font-size: 0.75rem;">
                                <?= esc($t->nombre) ?>
                                <span class="text-body-secondary fw-normal" style="font-size: 0.68rem;">(<?= esc($t->rangoTexto()) ?>)</span>
                            </span>
                        <?php endforeach; ?>
                    </div>

                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <span class="tp-turno-pill__cupo tp-cupo--ok"><i class="bi bi-check-circle-fill me-1"></i>Completo</span>
                        <span class="tp-turno-pill__cupo tp-cupo--parcial"><i class="bi bi-exclamation-circle-fill me-1"></i>Parcial</span>
                        <span class="tp-turno-pill__cupo tp-cupo--vacio"><i class="bi bi-x-circle-fill me-1"></i>Sin cubrir</span>
                    </div>
                </div>

                <!-- Calendario Mensual Compacto (Escritorio) -->
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
                                            : ($esHoy ? 'tp-celda-dia tp-celda-dia--hoy' : ($esPasado ? 'tp-celda-dia tp-celda-dia--pasado' : 'tp-celda-dia'));
                                        ?>
                                        <?php if (! $perteneceMes): ?>
                                            <td class="p-2 tp-celda-dia--fuera">
                                                <span class="tp-num-dia opacity-50"><?= (int) substr($dia, 8, 2) ?>/<?= substr($dia, 5, 2) ?></span>
                                                <div class="small text-body-secondary text-center py-4 opacity-50">—</div>
                                            </td>
                                        <?php else: ?>
                                            <td class="p-2 <?= $claseCelda ?>"
                                                role="button"
                                                tabindex="0"
                                                data-abrir-drawer="<?= esc($dia, 'attr') ?>"
                                                <?= $esHoy ? 'aria-current="date"' : '' ?>>
                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                    <div class="d-flex align-items-center gap-1">
                                                        <span class="tp-num-dia"><?= (int) substr($dia, 8, 2) ?></span>
                                                        <?php if ($esHoy): ?>
                                                            <span class="badge rounded-pill text-uppercase" style="background: var(--color-primario-oscuro); font-size: 0.62rem;">Hoy</span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <?php if ($esPasado): ?>
                                                        <span class="text-body-secondary" style="font-size: 0.65rem;">Finalizado</span>
                                                    <?php else: ?>
                                                        <span class="text-body-secondary opacity-75" style="font-size: 0.68rem;">Detalle <i class="bi bi-chevron-right"></i></span>
                                                    <?php endif; ?>
                                                </div>

                                                <div class="d-flex flex-column gap-1">
                                                    <?php foreach ($tipos as $t): ?>
                                                        <?php
                                                        $asignadosCelda = $grilla[$dia][(int) $t->id] ?? [];
                                                        if (! $turnoAplicaEnDia($t, $dia, $asignadosCelda)) {
                                                            continue;
                                                        }
                                                        ?>
                                                        <?= view('admin/planificador/_celda', [
                                                            'sector'      => $sector,
                                                            'tipo'        => $t,
                                                            'dia'         => $dia,
                                                            'asignados'   => $asignadosCelda,
                                                            'ahora'       => $ahora,
                                                            'fechaMaxima' => $fechaMaxima,
                                                        ]) ?>
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

                <!-- Vista Móvil: lista cronológica de días del mes -->
                <div class="d-lg-none">
                    <?php foreach ($semanas as $semana): ?>
                        <?php foreach ($semana as $dia): ?>
                            <?php if (substr($dia, 0, 7) !== $anioMes) continue; ?>
                            <?php $esHoy = $dia === $hoy; $esPasado = $dia < $hoy; ?>
                            <div class="border rounded-3 mb-2 tp-celda-dia <?= $esHoy ? 'tp-celda-dia--hoy' : ($esPasado ? 'tp-celda-dia--pasado' : '') ?>"
                                 role="button" tabindex="0" data-abrir-drawer="<?= esc($dia, 'attr') ?>">
                                <div class="px-3 py-2 border-bottom d-flex justify-content-between align-items-center">
                                    <span class="fw-semibold text-capitalize">
                                        <?= esc(dia_semana($dia)) ?> <?= esc(fecha_ar($dia)) ?>
                                    </span>
                                    <?php if ($esHoy): ?>
                                        <span class="badge rounded-pill" style="background: var(--color-primario-oscuro);">Hoy</span>
                                    <?php elseif ($esPasado): ?>
                                        <span class="small text-body-secondary">Finalizado</span>
                                    <?php endif; ?>
                                </div>
                                <div class="p-2 d-flex flex-column gap-1">
                                    <?php foreach ($tipos as $t): ?>
                                        <?php
                                        $asignadosCelda = $grilla[$dia][(int) $t->id] ?? [];
                                        if (! $turnoAplicaEnDia($t, $dia, $asignadosCelda)) {
                                            continue;
                                        }
                                        ?>
                                        <?= view('admin/planificador/_celda', [
                                            'sector'      => $sector,
                                            'tipo'        => $t,
                                            'dia'         => $dia,
                                            'asignados'   => $asignadosCelda,
                                            'ahora'       => $ahora,
                                            'fechaMaxima' => $fechaMaxima,
                                        ]) ?>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </div>

                <!-- Pie del calendario -->
                <div class="mt-3 pt-2 d-flex flex-wrap justify-content-between align-items-center gap-2 small text-body-secondary">
                    <span>
                        <i class="bi bi-info-circle me-1" style="color: var(--color-primario-oscuro);" aria-hidden="true"></i>
                        Hacé clic en cualquier día para ver los enfermeros asignados, reasignar o quitar turnos. Planificación habilitada hasta el <strong><?= esc(fecha_ar($fechaMaxima)) ?></strong>.
                    </span>
                    <span class="tp-turno-pill__cupo" style="background: #fbf3dc; color: #6d5016; border: 1px solid rgba(109,80,22,0.25);">HE = Hora extra</span>
                </div>

                <!-- Resumen mensual de carga horaria y horas extras cubiertas por enfermero/a -->
                <section class="mt-4 pt-3 border-top" aria-labelledby="titulo-horas-mes">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                        <div>
                            <h2 class="h6 fw-semibold mb-0" id="titulo-horas-mes">
                                <i class="bi bi-hourglass-split me-1" style="color: var(--color-primario-oscuro);" aria-hidden="true"></i>Carga horaria del mes (<?= esc($nombreMes) ?>)
                            </h2>
                            <p class="small text-body-secondary mb-0">Hacé clic en un enfermero/a para ver sus datos personales y el detalle de sus guardias en todos los sectores.</p>
                        </div>
                    </div>

                    <?php if ($enfermeros === []): ?>
                        <p class="small text-body-secondary mb-0">No hay enfermeros/as activos.</p>
                    <?php else: ?>
                        <?php
                        $datosEnfermerosModal = [];
                        foreach ($enfermeros as $e) {
                            $eid    = (int) $e->id;
                            $datosE = $resumenMes[$eid] ?? ['total' => 0, 'extra' => 0];
                            $norm   = max(0, (int) $datosE['total'] - (int) $datosE['extra']);

                            $datosEnfermerosModal[$eid] = [
                                'id'           => $eid,
                                'nombre'       => $e->getNombreCompleto(),
                                'dni'          => (string) ($e->dni ?? '—'),
                                'matricula'    => (string) ($e->matricula ?? 'Sin matrícula'),
                                'especialidad' => (string) ($e->especialidad ?? 'Sin especialidad'),
                                'email'        => (string) ($e->email ?? '—'),
                                'telefono'     => (string) ($e->telefono ?? 'No registrado'),
                                'topeSemanal'  => (int) $e->carga_horaria_max . ' h / semana',
                                'totalMes'     => \App\Models\AsignacionModel::horasTexto((int) $datosE['total']),
                                'normalesMes'  => \App\Models\AsignacionModel::horasTexto($norm),
                                'extrasMes'    => (int) $datosE['extra'] > 0 ? \App\Models\AsignacionModel::horasTexto((int) $datosE['extra']) : '0 h',
                                'tieneExtras'  => (int) $datosE['extra'] > 0,
                                'urlPerfil'    => base_url('admin/usuarios/' . $eid . '/editar'),
                                'turnos'       => $turnosPorEnfermero[$eid] ?? [],
                            ];
                        }
                        ?>

                        <!-- Barra de búsqueda, filtros y ordenamiento de Carga Horaria -->
                        <div class="tp-filtro-barra p-3 mb-3 mt-2">
                            <div class="row g-2 align-items-center">
                                <div class="col-12 col-md-4 col-xl-3">
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text bg-white border-end-0 text-body-secondary">
                                            <i class="bi bi-search" aria-hidden="true"></i>
                                        </span>
                                        <input type="search" class="form-control border-start-0 ps-0" id="buscarEnfermeroMes"
                                               placeholder="Buscar por nombre o matrícula..." autocomplete="off"
                                               aria-label="Buscar enfermero/a por nombre o matrícula">
                                    </div>
                                </div>
                                <div class="col-6 col-md-3 col-xl-3">
                                    <select class="form-select form-select-sm" id="filtroEspecialidadMes" aria-label="Filtrar por especialidad">
                                        <option value="">Todas las especialidades</option>
                                        <?php foreach ($especialidades as $esp): ?>
                                            <option value="<?= esc($esp, 'attr') ?>"><?= esc($esp) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-6 col-md-2 col-xl-2">
                                    <select class="form-select form-select-sm" id="filtroCargaMes" aria-label="Filtrar por carga horaria">
                                        <option value="todos">Todas las cargas</option>
                                        <option value="con-guardias">Con guardias asignadas</option>
                                        <option value="sin-guardias">Sin guardias (0 h)</option>
                                        <option value="con-extras">Con horas extras</option>
                                    </select>
                                </div>
                                <div class="col-12 col-md-3 col-xl-3">
                                    <div class="d-flex align-items-center gap-1">
                                        <span class="small text-body-secondary text-nowrap"><i class="bi bi-sort-down me-1" aria-hidden="true"></i>Orden:</span>
                                        <select class="form-select form-select-sm" id="ordenarMes" aria-label="Ordenar listado de carga horaria">
                                            <option value="alfabetico-asc">Alfabético (A - Z)</option>
                                            <option value="alfabetico-desc">Alfabético (Z - A)</option>
                                            <option value="horas-desc">Mayor carga horaria</option>
                                            <option value="horas-asc">Menor carga horaria</option>
                                            <option value="extras-desc">Más horas extras</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-12 col-xl-1 text-xl-end d-flex justify-content-between justify-content-xl-end align-items-center gap-2">
                                    <span class="badge rounded-pill bg-light text-dark border small" id="contadorEnfermerosMes">
                                        <?= count($enfermeros) ?> enfermeros
                                    </span>
                                    <button type="button" class="btn btn-sm btn-link text-decoration-none py-0 px-1 text-danger d-none" id="btnLimpiarFiltrosMes" title="Restablecer filtros">
                                        <i class="bi bi-x-circle me-1" aria-hidden="true"></i>Limpiar
                                    </button>
                                </div>
                            </div>
                        </div>

                        <ul class="list-unstyled row row-cols-1 row-cols-md-2 row-cols-xl-3 g-2 mb-0" id="listaCargaHorariaMes">
                            <?php foreach ($enfermeros as $e): ?>
                                <?php
                                $eid    = (int) $e->id;
                                $datosE = $resumenMes[$eid] ?? ['total' => 0, 'extra' => 0];
                                ?>
                                <li class="col tp-item-enfermero"
                                    data-nombre="<?= esc(mb_strtolower($e->getNombreCompleto()), 'attr') ?>"
                                    data-matricula="<?= esc(mb_strtolower((string) $e->matricula), 'attr') ?>"
                                    data-especialidad="<?= esc($e->especialidad ?? '', 'attr') ?>"
                                    data-minutos="<?= (int) $datosE['total'] ?>"
                                    data-extras="<?= (int) $datosE['extra'] ?>">
                                    <div class="tp-enfermero-card h-100 d-flex justify-content-between align-items-center gap-2"
                                         role="button"
                                         tabindex="0"
                                         data-abrir-enfermero="<?= $eid ?>"
                                         title="Ver ficha y guardias de <?= esc($e->getNombreCompleto(), 'attr') ?>">
                                        <div class="min-w-0">
                                            <div class="fw-semibold small text-truncate">
                                                <?= esc($e->getNombreCompleto()) ?>
                                            </div>
                                            <div class="small text-body-secondary d-flex flex-wrap align-items-center gap-1 mt-1">
                                                <?php if (! empty($e->especialidad)): ?>
                                                    <span class="badge tp-badge-especialidad"><?= esc($e->especialidad) ?></span>
                                                <?php endif; ?>
                                                <span>Tope: <?= (int) $e->carga_horaria_max ?> h</span>
                                                <?php if (! empty($e->matricula)): ?>
                                                    · <span class="tp-matricula">Mat: <strong><?= esc((string) $e->matricula) ?></strong></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="text-end flex-shrink-0">
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

                        <div id="sinResultadosMes" class="text-center py-4 border rounded-3 bg-light-subtle d-none mt-2">
                            <i class="bi bi-funnel text-body-secondary fs-2 d-block mb-1" aria-hidden="true"></i>
                            <p class="small text-body-secondary mb-1">No se encontraron enfermeros con los filtros seleccionados.</p>
                            <button type="button" class="btn btn-outline-secondary btn-sm mt-1" id="btnRestablecerMes">
                                <i class="bi bi-arrow-counterclockwise me-1" aria-hidden="true"></i>Restablecer filtros
                            </button>
                        </div>

                        <!-- Modal: Ficha y detalle horario del Enfermero/a -->
                        <div class="modal fade" id="modalDetalleEnfermero" tabindex="-1" aria-labelledby="modalEnfTitulo" aria-hidden="true">
                            <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                                <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                                    <div class="modal-header border-bottom py-3 px-4" style="background: #f1f5f3;">
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="rounded-circle d-flex align-items-center justify-content-center text-white flex-shrink-0"
                                                 style="width: 44px; height: 44px; background: var(--color-primario-oscuro);">
                                                 <i class="bi bi-person-vcard fs-5" aria-hidden="true"></i>
                                            </div>
                                            <div>
                                                <h3 class="modal-title h5 fw-bold mb-0" id="modalEnfTitulo">Nombre del Enfermero/a</h3>
                                                <div class="small text-body-secondary mb-0 d-flex flex-wrap align-items-center gap-1 mt-0.5">
                                                    <span>Matrícula: <strong id="modalEnfMatricula" style="color: var(--color-primario-oscuro);">—</strong></span>
                                                    <span>· Especialidad: <span class="badge tp-badge-especialidad" id="modalEnfEspecialidad">—</span></span>
                                                    <span>· Tope: <strong id="modalEnfTope">—</strong></span>
                                                </div>
                                            </div>
                                        </div>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                                    </div>

                                    <div class="modal-body p-4" style="background: var(--color-fondo);">
                                        <!-- Datos personales -->
                                        <div class="row g-2 mb-3">
                                            <div class="col-12 col-sm-4">
                                                <div class="rounded-3 bg-white border p-2 px-3 h-100">
                                                    <div class="text-body-secondary" style="font-size: 0.7rem;">DNI</div>
                                                    <div class="fw-semibold small" id="modalEnfDni">—</div>
                                                </div>
                                            </div>
                                            <div class="col-12 col-sm-4">
                                                <div class="rounded-3 bg-white border p-2 px-3 h-100">
                                                    <div class="text-body-secondary" style="font-size: 0.7rem;">Correo electrónico</div>
                                                    <div class="fw-semibold small text-truncate" id="modalEnfEmail">—</div>
                                                </div>
                                            </div>
                                            <div class="col-12 col-sm-4">
                                                <div class="rounded-3 bg-white border p-2 px-3 h-100">
                                                    <div class="text-body-secondary" style="font-size: 0.7rem;">Teléfono</div>
                                                    <div class="fw-semibold small" id="modalEnfTelefono">—</div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Resumen de horas del mes -->
                                        <div class="row g-2 mb-3">
                                            <div class="col-4">
                                                <div class="rounded-3 p-2 px-3 text-center" style="background: var(--color-primario-suave); border: 1px solid rgba(27,77,62,0.2);">
                                                    <div class="small text-body-secondary">Total del mes</div>
                                                    <div class="fw-bold" style="color: var(--color-primario-oscuro);" id="modalEnfTotalMes">0 h</div>
                                                </div>
                                            </div>
                                            <div class="col-4">
                                                <div class="rounded-3 bg-white border p-2 px-3 text-center">
                                                    <div class="small text-body-secondary">Horas regulares</div>
                                                    <div class="fw-bold text-dark" id="modalEnfNormalesMes">0 h</div>
                                                </div>
                                            </div>
                                            <div class="col-4">
                                                <div class="rounded-3 p-2 px-3 text-center" style="background: #fbf3dc; border: 1px solid rgba(109,80,22,0.25);">
                                                    <div class="small" style="color: #6d5016;">Horas extras</div>
                                                    <div class="fw-bold" style="color: #6d5016;" id="modalEnfExtrasMes">0 h</div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Listado de guardias del mes -->
                                        <h4 class="h6 fw-bold mb-2">Guardias asignadas en <?= esc($nombreMes) ?></h4>
                                        <div id="modalEnfListaTurnos" class="d-flex flex-column gap-2"></div>
                                    </div>

                                    <div class="modal-footer border-top bg-white px-4 py-3 justify-content-between">
                                        <span class="small text-body-secondary">Cálculo semanal (Lun a Dom)</span>
                                        <div class="d-flex gap-2">
                                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
                                            <a id="modalEnfBtnEditar" href="#" class="btn btn-tp fw-semibold">
                                                <i class="bi bi-person-gear me-1" aria-hidden="true"></i>Editar perfil
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <script>
                        document.addEventListener('DOMContentLoaded', function () {
                            const datosEnf = <?= json_encode($datosEnfermerosModal, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
                            const modalEl  = document.getElementById('modalDetalleEnfermero');
                            if (!modalEl || typeof bootstrap === 'undefined') return;

                            const modal = new bootstrap.Modal(modalEl);

                            function escHtml(str) {
                                const d = document.createElement('div');
                                d.textContent = str ?? '';
                                return d.innerHTML;
                            }

                            function abrirEnfermero(id) {
                                const info = datosEnf[id];
                                if (!info) return;

                                document.getElementById('modalEnfTitulo').textContent       = info.nombre;
                                document.getElementById('modalEnfMatricula').textContent    = info.matricula;
                                document.getElementById('modalEnfEspecialidad').textContent = info.especialidad;
                                document.getElementById('modalEnfTope').textContent         = info.topeSemanal;
                                document.getElementById('modalEnfDni').textContent          = info.dni;
                                document.getElementById('modalEnfEmail').textContent        = info.email;
                                document.getElementById('modalEnfTelefono').textContent     = info.telefono;
                                document.getElementById('modalEnfTotalMes').textContent     = info.totalMes;
                                document.getElementById('modalEnfNormalesMes').textContent  = info.normalesMes;
                                document.getElementById('modalEnfExtrasMes').textContent    = info.extrasMes;
                                document.getElementById('modalEnfBtnEditar').href           = info.urlPerfil;

                                const cont = document.getElementById('modalEnfListaTurnos');
                                if (!info.turnos || info.turnos.length === 0) {
                                    cont.innerHTML = '<div class="rounded-3 bg-white border p-3 text-center small text-body-secondary fst-italic">No tiene guardias asignadas en este mes.</div>';
                                } else {
                                    cont.innerHTML = info.turnos.map(function (t) {
                                        const badgeExtra = t.extra
                                            ? '<span class="badge rounded-pill ms-1" style="background: #fbf3dc; color: #6d5016; border: 1px solid rgba(109,80,22,0.25);">HE: ' + escHtml(t.extra) + '</span>'
                                            : '';
                                        return '<div class="rounded-3 bg-white border p-2 px-3 d-flex justify-content-between align-items-center gap-2" style="border-left: 4px solid ' + escHtml(t.color) + ' !important;">' +
                                            '<div>' +
                                                '<div class="fw-semibold small text-dark">' + escHtml(t.dia_sem) + ' ' + escHtml(t.fecha) + ' · <span style="color: var(--color-primario-oscuro);">' + escHtml(t.sector) + '</span></div>' +
                                                '<div class="small text-body-secondary">' + escHtml(t.turno) + ' (' + escHtml(t.horario) + ')</div>' +
                                            '</div>' +
                                            '<div class="text-end">' +
                                                '<span class="badge rounded-pill" style="background: #f1f5f3; color: var(--color-texto); border: 1px solid var(--color-borde);">' + escHtml(t.duracion) + '</span>' +
                                                badgeExtra +
                                            '</div>' +
                                        '</div>';
                                    }).join('');
                                }

                                modal.show();
                            }

                            document.querySelectorAll('[data-abrir-enfermero]').forEach(function (card) {
                                card.addEventListener('click', function () {
                                    abrirEnfermero(this.getAttribute('data-abrir-enfermero'));
                                });
                                card.addEventListener('keydown', function (e) {
                                    if (e.key === 'Enter' || e.key === ' ') {
                                        e.preventDefault();
                                        abrirEnfermero(this.getAttribute('data-abrir-enfermero'));
                                    }
                                });
                            });

                            /* ---------- Filtrado y ordenamiento interactivo de Carga Horaria del mes ---------- */
                            const listaMes    = document.getElementById('listaCargaHorariaMes');
                            const inputBuscar = document.getElementById('buscarEnfermeroMes');
                            const selectEsp   = document.getElementById('filtroEspecialidadMes');
                            const selectCarga = document.getElementById('filtroCargaMes');
                            const selectOrden = document.getElementById('ordenarMes');
                            const contadorMes = document.getElementById('contadorEnfermerosMes');
                            const sinResMes   = document.getElementById('sinResultadosMes');
                            const btnLimpiar  = document.getElementById('btnLimpiarFiltrosMes');
                            const btnRestab   = document.getElementById('btnRestablecerMes');

                            function aplicarFiltrosYOrdenMes() {
                                if (!listaMes) return;
                                const q     = (inputBuscar ? inputBuscar.value.trim().toLowerCase() : '');
                                const esp   = (selectEsp ? selectEsp.value : '');
                                const carga = (selectCarga ? selectCarga.value : 'todos');
                                const orden = (selectOrden ? selectOrden.value : 'alfabetico-asc');

                                const hayFiltro = q !== '' || esp !== '' || carga !== 'todos' || orden !== 'alfabetico-asc';
                                if (btnLimpiar) {
                                    btnLimpiar.classList.toggle('d-none', !hayFiltro);
                                }

                                const items = Array.from(listaMes.querySelectorAll('.tp-item-enfermero'));
                                let visibles = 0;

                                items.forEach(function (el) {
                                    const nombre  = (el.getAttribute('data-nombre') || '').toLowerCase();
                                    const mat     = (el.getAttribute('data-matricula') || '').toLowerCase();
                                    const espItem = el.getAttribute('data-especialidad') || '';
                                    const min     = parseInt(el.getAttribute('data-minutos') || '0', 10);
                                    const extra   = parseInt(el.getAttribute('data-extras') || '0', 10);

                                    const matchTexto = !q || nombre.includes(q) || mat.includes(q);
                                    const matchEsp   = !esp || espItem === esp;
                                    let matchCarga   = true;
                                    if (carga === 'con-guardias') {
                                        matchCarga = min > 0;
                                    } else if (carga === 'sin-guardias') {
                                        matchCarga = min === 0;
                                    } else if (carga === 'con-extras') {
                                        matchCarga = extra > 0;
                                    }

                                    const mostrar = matchTexto && matchEsp && matchCarga;
                                    el.classList.toggle('d-none', !mostrar);
                                    if (mostrar) {
                                        visibles++;
                                    }
                                });

                                items.sort(function (a, b) {
                                    if (orden === 'alfabetico-asc') {
                                        return (a.getAttribute('data-nombre') || '').localeCompare(b.getAttribute('data-nombre') || '', 'es', { sensitivity: 'base' });
                                    }
                                    if (orden === 'alfabetico-desc') {
                                        return (b.getAttribute('data-nombre') || '').localeCompare(a.getAttribute('data-nombre') || '', 'es', { sensitivity: 'base' });
                                    }
                                    const minA   = parseInt(a.getAttribute('data-minutos') || '0', 10);
                                    const minB   = parseInt(b.getAttribute('data-minutos') || '0', 10);
                                    const extraA = parseInt(a.getAttribute('data-extras') || '0', 10);
                                    const extraB = parseInt(b.getAttribute('data-extras') || '0', 10);

                                    if (orden === 'horas-desc') {
                                        return minB - minA;
                                    }
                                    if (orden === 'horas-asc') {
                                        return minA - minB;
                                    }
                                    if (orden === 'extras-desc') {
                                        return extraB - extraA;
                                    }
                                    return 0;
                                });

                                items.forEach(function (el) {
                                    listaMes.appendChild(el);
                                });

                                if (contadorMes) {
                                    contadorMes.textContent = visibles + (visibles === 1 ? ' enfermero' : ' enfermeros');
                                }
                                if (sinResMes) {
                                    sinResMes.classList.toggle('d-none', visibles > 0);
                                }
                            }

                            function restablecerFiltrosMes() {
                                if (inputBuscar) inputBuscar.value = '';
                                if (selectEsp) selectEsp.value = '';
                                if (selectCarga) selectCarga.value = 'todos';
                                if (selectOrden) selectOrden.value = 'alfabetico-asc';
                                aplicarFiltrosYOrdenMes();
                                if (inputBuscar) inputBuscar.focus();
                            }

                            if (inputBuscar) inputBuscar.addEventListener('input', aplicarFiltrosYOrdenMes);
                            if (selectEsp) selectEsp.addEventListener('change', aplicarFiltrosYOrdenMes);
                            if (selectCarga) selectCarga.addEventListener('change', aplicarFiltrosYOrdenMes);
                            if (selectOrden) selectOrden.addEventListener('change', aplicarFiltrosYOrdenMes);
                            if (btnLimpiar) btnLimpiar.addEventListener('click', restablecerFiltrosMes);
                            if (btnRestab) btnRestab.addEventListener('click', restablecerFiltrosMes);
                        });
                        </script>
                    <?php endif; ?>
                </section>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php if ($sector !== null && $tipos !== []): ?>
    <!-- Drawer Lateral Derecho (Detalle y Gestión por Día - Estilo Stitch) -->
    <div class="offcanvas offcanvas-end" tabindex="-1" id="drawerDetalleDia" aria-labelledby="drawerTituloDia" style="width: 440px; max-width: 100vw;">
        <div class="offcanvas-header border-bottom py-3 px-4" style="background: #f1f5f3;">
            <div class="d-flex align-items-center gap-3">
                <div id="drawerBadgeNum" class="rounded-circle d-flex align-items-center justify-content-center text-white fw-bold flex-shrink-0"
                     style="width: 40px; height: 40px; background: var(--color-primario-oscuro);">
                    18
                </div>
                <div>
                    <div class="d-flex align-items-center gap-2">
                        <h2 class="offcanvas-title h6 fw-bold mb-0" id="drawerTituloDia">Detalle del día</h2>
                        <span id="drawerTagHoy" class="badge rounded-pill text-uppercase d-none" style="background: var(--color-primario-oscuro); font-size: 0.62rem;">Hoy</span>
                    </div>
                    <p class="small text-body-secondary mb-0">Sector: <strong style="color: var(--color-primario-oscuro);"><?= esc($sector->nombre) ?></strong></p>
                </div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Cerrar"></button>
        </div>

        <div class="offcanvas-body p-4" style="background: var(--color-fondo);">
            <div class="rounded-3 p-3 mb-3 d-flex justify-content-between align-items-center"
                 style="background: var(--color-primario-suave); border: 1px solid rgba(27,77,62,0.2);">
                <span class="small fw-semibold" style="color: var(--color-primario-oscuro);">
                    <i class="bi bi-people-fill me-1"></i>
                    <span id="drawerResumenTexto">Dotación del día: 0/0</span>
                </span>
                <span id="drawerResumenBadge" class="tp-turno-pill__cupo tp-cupo--ok">Cubierto</span>
            </div>

            <div id="drawerListaTurnos" class="d-flex flex-column gap-3"></div>
        </div>

        <div class="p-3 border-top bg-white d-flex justify-content-between align-items-center">
            <span class="small text-body-secondary">Gestión rápida de guardia</span>
            <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="offcanvas">Cerrar</button>
        </div>
    </div>

    <!-- Modal: agregar personal a una celda (AJAX) - Selector Visual Mejorado -->
    <div class="modal fade" id="modalAsignar" tabindex="-1" aria-labelledby="tituloModalAsignar" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <form class="modal-content border-0 shadow-lg rounded-4 overflow-hidden"
                  action="<?= base_url('admin/asignaciones/crear') ?>"
                  data-action-crear="<?= base_url('admin/asignaciones/crear') ?>"
                  data-base-asignaciones="<?= base_url('admin/asignaciones') ?>"
                  method="post"
                  data-ajax-asignar
                  novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="sector_id" value="">
                <input type="hidden" name="tipo_turno_id" value="">
                <input type="hidden" name="fecha" value="">
                <input type="hidden" name="usuario_id" id="modal_usuario_id" value="">

                <div class="modal-header border-bottom py-3 px-4" style="background: #f1f5f3;">
                    <div class="d-flex align-items-center gap-3">
                        <div class="rounded-circle d-flex align-items-center justify-content-center text-white flex-shrink-0"
                             style="width: 42px; height: 42px; background: var(--color-primario-oscuro);">
                            <i class="bi bi-person-plus-fill fs-5" aria-hidden="true" id="iconoModalAsignar"></i>
                        </div>
                        <div>
                            <h2 class="modal-title h6 fw-bold mb-0" id="tituloModalAsignar">Asignar turno de enfermería</h2>
                            <p class="small text-body-secondary mb-0" id="subtituloModalAsignar">Selección de personal según dotación, especialidad y carga horaria</p>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>

                <div class="modal-body p-3 p-md-4" style="background: var(--color-fondo);">
                    <!-- Resumen del turno a asignar -->
                    <div class="rounded-3 p-3 mb-3 d-flex flex-wrap justify-content-between align-items-center gap-2"
                         style="background: var(--color-primario-suave); border: 1px solid rgba(27,77,62,0.2);">
                        <div>
                            <span class="small text-uppercase fw-semibold text-body-secondary d-block" style="font-size: 0.68rem; letter-spacing: 0.04em;">Turno seleccionado:</span>
                            <div class="fw-bold text-dark mt-0.5" data-descripcion-turno>
                                Cargando información de la guardia...
                            </div>
                            <div id="bloqueReasignarActual" class="mt-2 pt-2 border-top d-none small">
                                <span class="text-body-secondary">Reemplazando a:</span>
                                <strong class="text-dark" id="nombreEnfermeroActualReasignar">—</strong>
                            </div>
                        </div>
                        <span class="badge rounded-pill" style="background: #fbf3dc; color: #6d5016; border: 1px solid rgba(109,80,22,0.25); font-size: 0.72rem;">
                            <i class="bi bi-info-circle me-1" aria-hidden="true"></i>Prioridad: horas disponibles
                        </span>
                    </div>

                    <div class="alerta alerta--error mb-3 d-none" role="alert" data-errores-modal></div>

                    <!-- Barra de filtros del modal -->
                    <div class="tp-filtro-barra p-3 mb-3">
                        <div class="row g-2 align-items-center">
                            <div class="col-12 col-md-5">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text bg-white border-end-0 text-body-secondary">
                                        <i class="bi bi-search" aria-hidden="true"></i>
                                    </span>
                                    <input type="search" class="form-control border-start-0 ps-0" id="buscarEnfermeroModal"
                                           placeholder="Buscar por apellido, nombre o matrícula..." autocomplete="off"
                                           aria-label="Buscar enfermero/a">
                                </div>
                            </div>
                            <div class="col-6 col-md-4">
                                <select class="form-select form-select-sm" id="filtroEspecialidadModal" aria-label="Filtrar por especialidad">
                                    <option value="">Todas las especialidades</option>
                                    <?php foreach ($especialidades as $esp): ?>
                                        <option value="<?= esc($esp, 'attr') ?>"><?= esc($esp) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-6 col-md-3">
                                <select class="form-select form-select-sm" id="filtroCargaModal" aria-label="Filtrar por carga horaria">
                                    <option value="todos">Todas las cargas</option>
                                    <option value="disponibles">Con horas disponibles</option>
                                    <option value="extras">Con horas extras</option>
                                </select>
                            </div>
                        </div>

                        <div class="d-flex flex-wrap justify-content-between align-items-center mt-2 pt-2 border-top" style="border-color: var(--color-borde-suave) !important;">
                            <span class="small text-body-secondary" id="contadorEnfermerosModal" style="font-size: 0.75rem;">
                                Mostrando <?= count($enfermeros) ?> enfermeros/as
                            </span>
                            <div class="d-flex align-items-center gap-2">
                                <span class="small text-body-secondary d-none d-sm-inline" style="font-size: 0.72rem;"><i class="bi bi-sort-down me-1" aria-hidden="true"></i>Ordenar:</span>
                                <select class="form-select form-select-sm py-0 px-2" id="ordenarEnfermeroModal" style="font-size: 0.75rem; width: auto;" aria-label="Ordenar enfermeros">
                                    <option value="carga-asc" selected>Menos horas (prioridad)</option>
                                    <option value="carga-desc">Mayor carga horaria</option>
                                    <option value="alfabetico">Alfabético (A - Z)</option>
                                </select>
                                <button type="button" class="btn btn-sm btn-link text-decoration-none py-0 px-1 text-danger d-none" id="btnLimpiarFiltrosModal" style="font-size: 0.75rem;" title="Restablecer filtros">
                                    <i class="bi bi-x-circle me-1" aria-hidden="true"></i>Limpiar
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Listado interactivo de enfermeros/as en tarjetas -->
                    <div class="tp-enfermeros-scroll" id="listaEnfermerosModal" role="radiogroup" aria-label="Lista de enfermeros/as para asignar">
                        <?php foreach ($enfermeros as $e): ?>
                            <?php
                            $eid         = (int) $e->id;
                            $datosE      = $resumenMes[$eid] ?? ['total' => 0, 'extra' => 0];
                            $tieneExtras = $datosE['extra'] > 0;
                            $iniciales   = mb_strtoupper(mb_substr($e->apellido ?? '', 0, 1) . mb_substr($e->nombre ?? '', 0, 1));
                            ?>
                            <div class="tp-enfermero-opcion mb-2"
                                 role="radio"
                                 tabindex="0"
                                 aria-checked="false"
                                 data-id="<?= $eid ?>"
                                 data-nombre="<?= esc(mb_strtolower($e->getNombreCompleto()), 'attr') ?>"
                                 data-nombre-mostrar="<?= esc($e->getNombreCompleto(), 'attr') ?>"
                                 data-matricula="<?= esc(mb_strtolower((string) $e->matricula), 'attr') ?>"
                                 data-matricula-mostrar="<?= esc((string) $e->matricula, 'attr') ?>"
                                 data-especialidad="<?= esc($e->especialidad ?? '', 'attr') ?>"
                                 data-minutos="<?= (int) $datosE['total'] ?>"
                                 data-extras="<?= (int) $datosE['extra'] ?>"
                                 data-tiene-extras="<?= $tieneExtras ? '1' : '0' ?>">
                                <div class="d-flex align-items-center gap-3 min-w-0">
                                    <div class="tp-avatar-circulo" aria-hidden="true"><?= esc($iniciales) ?></div>
                                    <div class="min-w-0">
                                        <div class="fw-semibold text-truncate small text-dark">
                                            <?= esc($e->getNombreCompleto()) ?>
                                        </div>
                                        <div class="d-flex flex-wrap align-items-center gap-1 small text-body-secondary mt-1">
                                            <?php if (! empty($e->especialidad)): ?>
                                                <span class="badge tp-badge-especialidad"><?= esc($e->especialidad) ?></span>
                                            <?php endif; ?>
                                            <?php if (! empty($e->matricula)): ?>
                                                <span class="tp-matricula">Mat: <strong><?= esc((string) $e->matricula) ?></strong></span>
                                            <?php endif; ?>
                                            <span>· Tope: <?= (int) $e->carga_horaria_max ?> h/sem</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="d-flex align-items-center gap-3 flex-shrink-0">
                                    <div class="text-end">
                                        <span class="badge rounded-pill" style="background: #f1f5f3; color: var(--color-texto); border: 1px solid var(--color-borde); font-size: 0.72rem;">
                                            Total mes: <?= esc(\App\Models\AsignacionModel::horasTexto($datosE['total'])) ?>
                                        </span>
                                        <?php if ($tieneExtras): ?>
                                            <span class="badge rounded-pill d-block mt-1" style="background: #fbf3dc; color: #6d5016; border: 1px solid rgba(109,80,22,0.25); font-size: 0.68rem;">
                                                Genera HE: <?= esc(\App\Models\AsignacionModel::horasTexto($datosE['extra'])) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-success-subtle text-success-emphasis border border-success-subtle rounded-pill d-block mt-1" style="font-size: 0.68rem;">
                                                <i class="bi bi-check-circle me-1" aria-hidden="true"></i>Disponible
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="tp-check-circulo" aria-hidden="true">
                                        <i class="bi bi-check-lg"></i>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <div id="sinResultadosModal" class="text-center py-4 border rounded-3 bg-light-subtle d-none">
                            <i class="bi bi-person-x fs-2 text-body-secondary d-block mb-1" aria-hidden="true"></i>
                            <p class="small text-body-secondary mb-1">No se encontraron enfermeros con los criterios de búsqueda.</p>
                            <button type="button" class="btn btn-outline-secondary btn-sm mt-1" id="btnRestablecerModal">
                                <i class="bi bi-arrow-counterclockwise me-1" aria-hidden="true"></i>Restablecer filtros
                            </button>
                        </div>
                    </div>

                    <!-- Enfermero seleccionado -->
                    <div class="mt-3 p-2 px-3 rounded-3 border bg-white d-flex align-items-center justify-content-between gap-2">
                        <div class="small">
                            <span class="text-body-secondary me-1">Seleccionado:</span>
                            <span id="textoEnfermeroSeleccionado"><span class="text-body-secondary fst-italic">Ningún enfermero/a seleccionado</span></span>
                        </div>
                        <span class="text-body-secondary small fst-italic d-none d-md-inline" style="font-size: 0.72rem;">Hacé clic sobre una tarjeta para seleccionar</span>
                    </div>
                </div>

                <div class="modal-footer border-top bg-white px-4 py-3 justify-content-between">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-tp fw-semibold px-4" id="btnSubmitAsignar">
                        <i class="bi bi-person-check me-1" aria-hidden="true" id="iconoBtnSubmitAsignar"></i><span id="textoBtnSubmitAsignar">Asignar guardia</span>
                    </button>
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

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const datosPlanif = <?= json_encode($detallePlanificador, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
        const drawerEl    = document.getElementById('drawerDetalleDia');
        if (!drawerEl || typeof bootstrap === 'undefined') return;

        const drawer       = new bootstrap.Offcanvas(drawerEl);
        const badgeNumEl   = document.getElementById('drawerBadgeNum');
        const tituloEl     = document.getElementById('drawerTituloDia');
        const tagHoyEl     = document.getElementById('drawerTagHoy');
        const resumenTxt   = document.getElementById('drawerResumenTexto');
        const resumenBdg   = document.getElementById('drawerResumenBadge');
        const listaTurnos  = document.getElementById('drawerListaTurnos');

        function escapar(str) {
            const div = document.createElement('div');
            div.textContent = str ?? '';
            return div.innerHTML;
        }

        function abrirDrawerDia(fecha) {
            const info = datosPlanif[fecha];
            if (!info) return;

            badgeNumEl.textContent = info.numDia;
            tituloEl.textContent   = info.titulo;
            tagHoyEl.classList.toggle('d-none', !info.esHoy);

            resumenTxt.textContent = 'Dotación del día: ' + info.totalAsignados + ' / ' + info.totalRequerido + ' cubiertos';
            if (info.totalAsignados >= info.totalRequerido && info.totalRequerido > 0) {
                resumenBdg.className   = 'tp-turno-pill__cupo tp-cupo--ok';
                resumenBdg.textContent = '100% Cubierto';
            } else if (info.totalAsignados > 0) {
                resumenBdg.className   = 'tp-turno-pill__cupo tp-cupo--parcial';
                resumenBdg.textContent = 'Parcial';
            } else {
                resumenBdg.className   = 'tp-turno-pill__cupo tp-cupo--vacio';
                resumenBdg.textContent = 'Sin cubrir';
            }

            listaTurnos.innerHTML = info.turnos.map(function (t) {
                const claseCupo = t.cubiertos >= t.minimo ? 'tp-cupo--ok' : (t.cubiertos > 0 ? 'tp-cupo--parcial' : 'tp-cupo--vacio');

                let btnAsignarHtml = '';
                if (t.puede_asignar) {
                    btnAsignarHtml = '<a href="' + escapar(t.url_asignar) + '" ' +
                        'class="btn btn-sm btn-tp py-0 px-2 fw-semibold" style="font-size: 0.74rem;" ' +
                        'data-modal-asignar data-sector="' + info.sectorId + '" data-tipo="' + t.tipo_id + '" ' +
                        'data-fecha="' + escapar(info.fecha) + '" data-descripcion="' + escapar(t.desc_asignar) + '">' +
                        '<i class="bi bi-person-plus me-1"></i>Asignar</a>';
                }

                let cuerpoEnf = '';
                if (t.enfermeros.length === 0) {
                    cuerpoEnf = '<div class="rounded-2 p-2 text-center border border-dashed bg-light-subtle">' +
                        '<p class="small text-body-secondary mb-1 fst-italic">Sin enfermeros/as asignados</p>' +
                        btnAsignarHtml +
                    '</div>';
                } else {
                    cuerpoEnf = '<div class="d-flex flex-column gap-2">' + t.enfermeros.map(function (enf) {
                        const badgeHe = enf.es_extra
                            ? '<span class="badge rounded-pill ms-1" style="background: #fbf3dc; color: #6d5016; border: 1px solid rgba(109,80,22,0.25); font-size: 0.62rem;">HE</span>'
                            : '';
                        const badgeBaja = enf.de_baja
                            ? '<span class="badge bg-secondary-subtle text-secondary-emphasis ms-1" style="font-size: 0.62rem;">Baja</span>'
                            : '';

                        const datosReasignar = enf.es_futura
                            ? 'data-modal-reasignar data-id="' + enf.id + '" ' +
                              'data-sector="' + info.sectorId + '" ' +
                              'data-tipo="' + t.tipo_id + '" ' +
                              'data-fecha="' + escapar(info.fecha) + '" ' +
                              'data-enfermero-actual-id="' + (enf.usuario_id || '') + '" ' +
                              'data-enfermero-actual-nombre="' + escapar(enf.nombre) + '" ' +
                              'data-descripcion="' + escapar(t.nombre + ' (' + t.horario + ') del ' + info.titulo) + '"'
                            : '';

                        const nombreHtml = enf.es_futura
                            ? '<a href="' + escapar(enf.url_editar) + '" class="fw-semibold text-decoration-none text-dark" ' + datosReasignar + ' title="Reasignar turno">' + escapar(enf.nombre) + '</a>'
                            : '<span class="fw-semibold text-dark">' + escapar(enf.nombre) + '</span>';

                        const accionesHtml = enf.es_futura
                            ? '<div class="d-flex align-items-center gap-2">' +
                                '<a href="' + escapar(enf.url_editar) + '" class="btn btn-sm btn-outline-secondary py-0 px-2" style="font-size: 0.7rem;" ' + datosReasignar + ' title="Reasignar turno"><i class="bi bi-arrow-left-right"></i></a>' +
                                '<button type="button" class="btn btn-sm btn-outline-danger py-0 px-2" style="font-size: 0.7rem;" ' +
                                    'data-quitar="' + enf.id + '" data-descripcion="' + escapar(enf.desc_quitar) + '" title="Quitar del turno">' +
                                    '<i class="bi bi-x-lg"></i></button>' +
                              '</div>'
                            : '';

                        return '<div class="tp-enfermero-card d-flex justify-content-between align-items-center gap-2">' +
                            '<div class="min-w-0">' +
                                '<div class="small text-truncate">' + nombreHtml + badgeHe + badgeBaja + '</div>' +
                                '<div class="tp-matricula">Mat: <strong>' + escapar(enf.matricula) + '</strong></div>' +
                            '</div>' +
                            accionesHtml +
                        '</div>';
                    }).join('') + (btnAsignarHtml ? '<div class="text-end mt-1">' + btnAsignarHtml + '</div>' : '') + '</div>';
                }

                return '<section class="rounded-3 bg-white border p-3 shadow-sm" style="border-left: 4px solid ' + escapar(t.color) + ' !important;">' +
                    '<div class="d-flex justify-content-between align-items-center gap-2 pb-2 mb-2 border-bottom">' +
                        '<div>' +
                            '<div class="fw-bold small text-dark">' + escapar(t.nombre) + '</div>' +
                            '<div class="text-body-secondary" style="font-size: 0.72rem;">' + escapar(t.horario) + '</div>' +
                        '</div>' +
                        '<span class="tp-turno-pill__cupo ' + claseCupo + '">' + t.cubiertos + '/' + t.minimo + '</span>' +
                    '</div>' +
                    cuerpoEnf +
                '</section>';
            }).join('');

            drawer.show();
        }

        document.querySelectorAll('[data-abrir-drawer]').forEach(function (el) {
            el.addEventListener('click', function (e) {
                if (e.target.closest('[data-modal-asignar]') || e.target.closest('[data-modal-reasignar]') || e.target.closest('[data-quitar]')) {
                    return;
                }
                abrirDrawerDia(this.getAttribute('data-abrir-drawer'));
            });
            el.addEventListener('keydown', function (e) {
                if (e.target.closest('[data-modal-asignar]') || e.target.closest('[data-modal-reasignar]') || e.target.closest('[data-quitar]')) {
                    return;
                }
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    abrirDrawerDia(this.getAttribute('data-abrir-drawer'));
                }
            });
        });

        // Cierra el drawer al abrir los modales AJAX de asignar, reasignar o quitar para evitar superposición de backdrops
        drawerEl.addEventListener('click', function (e) {
            if (e.target.closest('[data-modal-asignar]') || e.target.closest('[data-modal-reasignar]') || e.target.closest('[data-quitar]')) {
                drawer.hide();
            }
        });
    });
    </script>
<?php endif; ?>
<?= $this->endSection() ?>