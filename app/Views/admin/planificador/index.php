<?php
/**
 * HU-14: Planificador mensual de turnos por sector (estética Sage & Pine).
 * Tabla mensual desde 1400 px (d-xxl) y lista por día debajo. Cada día abre un panel lateral (offcanvas)
 * para reasignar o quitar; el "+" de cada turno abre el modal de asignación (o el formulario sin JavaScript).
 * Todo el comportamiento está en public/js/app.js; los datos del servidor van en <script type="application/json">.
 *
 * @var list<\App\Entities\Sector>                                   $sectores
 * @var list<\App\Entities\TipoTurno>                                $tipos
 * @var \App\Entities\Sector|null                                    $sector
 * @var string                                                       $anioMes      YYYY-MM
 * @var string                                                       $nombreMes    "Noviembre 2026"
 * @var list<list<string>>                                           $semanas      matriz de semanas (lunes a domingo)
 * @var string                                                       $anteriorMes, $siguienteMes, $hoy, $fechaMaxima, $ahora
 * @var array<string, array<int, list<\App\Entities\Asignacion>>>    $grilla
 * @var list<\App\Entities\Usuario>                                  $enfermeros
 * @var array<int, array{total: int, extra: int}>                    $resumenMes
 * @var array<int, int>|null                                         $resumenSemana minutos de la semana actual por enfermero/a (opcional)
 * @var array<int, list<array<string, mixed>>>                       $turnosPorEnfermero
 */
$enfermeros         = $enfermeros ?? [];
$especialidades     = $especialidades ?? \App\Models\UsuarioModel::ESPECIALIDADES;
$resumenMes         = $resumenMes ?? [];
$resumenSemana      = $resumenSemana ?? null;
$turnosPorEnfermero = $turnosPorEnfermero ?? [];
$mesActual          = date('Y-m');
$urlMes             = static fn (string $mes, ?int $secId = null): string => base_url('admin/planificador') . '?' . http_build_query([
    'sector' => $secId ?? $sector?->id,
    'mes'    => $mes,
], '', '&amp;');
$esMesActual = $anioMes === $mesActual;
$horasTexto  = static fn (int $minutos): string => \App\Models\AsignacionModel::horasTexto($minutos);
$jsonSeguro  = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
$lunesActual = (new DateTimeImmutable($hoy))->modify('monday this week')->format('Y-m-d');

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

// Datos por día para el panel lateral (app.js arma los nodos con textContent: nada de HTML armado a mano)
$detallePlanificador = [];
$diasDelMes          = [];
if ($sector !== null && $tipos !== []) {
    $minimoDia = (int) $sector->dotacion_minima;
    $maxHab    = $fechaMaxima ?? \App\Models\AsignacionModel::fechaMaximaHabilitada();

    foreach ($semanas as $semana) {
        foreach ($semana as $dia) {
            if (substr($dia, 0, 7) !== $anioMes) {
                continue;
            }
            $diasDelMes[]   = $dia;
            $turnosDia      = [];
            $totalAsignados = 0;
            $tiposVigentes  = 0;
            $tituloDia      = ucfirst(dia_semana($dia)) . ' ' . fecha_ar($dia);

            foreach ($tipos as $t) {
                $asignados = $grilla[$dia][(int) $t->id] ?? [];
                if (! $turnoAplicaEnDia($t, $dia, $asignados)) {
                    continue;
                }
                $tiposVigentes++;

                $terminado    = \App\Models\AsignacionModel::calcularHorario($dia, $t)['fin'] <= $ahora;
                $puedeAsignar = ! $terminado && $dia <= $maxHab && $t->estaActivo();

                $listaEnf     = [];
                $activosCount = 0;
                foreach ($asignados as $a) {
                    $deBaja = $a->enfermeroDeBaja();
                    if (! $deBaja) {
                        $activosCount++;
                    }
                    $listaEnf[] = [
                        'id'          => (int) $a->id,
                        'usuario_id'  => (int) $a->usuario_id,
                        'nombre'      => $a->nombreEnfermero(),
                        'matricula'   => $a->matriculaEnfermero(),
                        'es_futura'   => $a->esFutura(),
                        'es_extra'    => $a->esExtra(),
                        'de_baja'     => $deBaja,
                        'url_editar'  => base_url('admin/asignaciones/' . $a->id . '/editar'),
                        'desc_quitar' => $a->nombreEnfermero() . ' del turno ' . $t->nombre . ' del ' . fecha_larga($dia),
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
                'titulo'         => $tituloDia,
                'esHoy'          => $dia === $hoy,
                'sectorId'       => (int) $sector->id,
                'totalAsignados' => $totalAsignados,
                'totalRequerido' => $tiposVigentes * $minimoDia,
                'turnos'         => $turnosDia,
            ];
        }
    }
}

// En el mes actual, los días ya pasados de la lista se pliegan en un <details>; en un mes pasado se muestran todos
$diasAnteriores = $esMesActual ? array_values(array_filter($diasDelMes, static fn (string $d): bool => $d < $hoy)) : [];
$diasVisibles   = array_values(array_diff($diasDelMes, $diasAnteriores));
$datosDiaLista  = static fn (string $dia): array => [
    'dia'              => $dia,
    'hoy'              => $hoy,
    'sector'           => $sector,
    'tipos'            => $tipos,
    'grilla'           => $grilla,
    'ahora'            => $ahora,
    'fechaMaxima'      => $fechaMaxima,
    'turnoAplicaEnDia' => $turnoAplicaEnDia,
];
?>
<?= $this->extend('layouts/main') ?>

<?= $this->section('contenido') ?>
<div class="tp-panel-card overflow-hidden">

    <div class="card-header tp-card-header py-3 px-4">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="d-flex align-items-center min-w-0">
                <div class="me-3"><img src="<?= base_url('img/logo-64.png') ?>" alt="Logo TurnoPlaner" class="barra__logo"></div>
                <div class="min-w-0">
                    <h1 class="h4 mb-0 fw-semibold text-white">Planificador</h1>
                    <p class="mb-0 small subtitulo">
                        <?= $sector !== null
                            ? 'Sector ' . esc($sector->nombre) . ' · ' . esc($nombreMes) . ' · Dotación mínima: ' . esc($sector->dotacionTexto())
                            : 'Todavía no hay sectores cargados' ?>
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
                <p class="fw-semibold mb-2">Todavía no hay sectores cargados.</p>
                <a href="<?= base_url('admin/sectores/crear') ?>" class="btn btn-tp">Crear el primer sector</a>
            </div>
        <?php else: ?>

            <!-- Sector y mes -->
            <div class="d-flex flex-column flex-xl-row justify-content-between align-items-xl-end gap-3 mb-3 pb-3 border-bottom">
                <div>
                    <p class="small fw-semibold text-body-secondary mb-1" id="titulo-selector-sector">
                        <i class="bi bi-hospital me-1" aria-hidden="true"></i>Sector
                    </p>
                    <div class="selector-sector d-flex flex-wrap gap-2" role="group" aria-labelledby="titulo-selector-sector">
                        <?php foreach ($sectores as $s): ?>
                            <?php $activoSec = $sector !== null && (int) $s->id === (int) $sector->id; ?>
                            <a href="<?= $urlMes($anioMes, (int) $s->id) ?>" class="btn btn-outline-secondary"<?= $activoSec ? ' aria-current="page"' : '' ?>>
                                <?php if ($activoSec): ?><i class="bi bi-check2" aria-hidden="true"></i><?php endif; ?>
                                <?= esc($s->nombre) ?>
                                <?php if ($s->esCritico()): ?>
                                    <span class="badge rounded-pill bg-danger-subtle text-danger-emphasis border border-danger-subtle">Crítica</span>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <nav aria-label="Cambiar de mes">
                    <div class="btn-group nav-mes w-100" role="group">
                        <a class="btn btn-outline-secondary" href="<?= $urlMes($anteriorMes) ?>">
                            <i class="bi bi-chevron-left d-none d-sm-inline-block" aria-hidden="true"></i> Anterior
                        </a>
                        <a class="btn btn-outline-secondary<?= $esMesActual ? ' active' : '' ?>" href="<?= $urlMes($mesActual) ?>"<?= $esMesActual ? ' aria-current="date"' : '' ?>>
                            Mes actual
                        </a>
                        <a class="btn btn-outline-secondary" href="<?= $urlMes($siguienteMes) ?>">
                            Siguiente <i class="bi bi-chevron-right d-none d-sm-inline-block" aria-hidden="true"></i>
                        </a>
                    </div>
                </nav>
            </div>

            <?php if ($tipos === []): ?>
                <div class="text-center py-5 px-3 border rounded-3">
                    <i class="bi bi-clock fs-1 text-body-secondary d-block mb-2" aria-hidden="true"></i>
                    <p class="fw-semibold mb-1">El sector «<?= esc($sector->nombre) ?>» todavía no tiene tipos de turno.</p>
                    <p class="small text-body-secondary mb-3">Cree las franjas horarias de este sector para comenzar a asignar enfermeros/as.</p>
                    <a href="<?= base_url('admin/tipos-turno/crear') ?>" class="btn btn-tp">Crear tipo de turno</a>
                </div>
            <?php else: ?>

                <!-- Leyenda de turnos y de estados de cobertura -->
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 small mb-3">
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <span class="tp-leyenda__titulo">Turnos:</span>
                        <?php foreach ($tipos as $t): ?>
                            <?php if (! $t->estaActivo()) continue; ?>
                            <span class="etiqueta-turno" style="--color-turno: <?= esc($t->color, 'attr') ?>;">
                                <?= esc($t->nombre) ?>
                                <span class="etiqueta-turno__horario"><?= esc($t->rangoTexto()) ?></span>
                            </span>
                        <?php endforeach; ?>
                    </div>
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <span class="tp-leyenda__titulo">Cobertura:</span>
                        <span class="cobertura cobertura--ok"><i class="bi bi-check-circle-fill" aria-hidden="true"></i>Cubierto</span>
                        <span class="cobertura cobertura--parcial"><i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>Faltan</span>
                        <span class="cobertura cobertura--vacio"><i class="bi bi-x-circle-fill" aria-hidden="true"></i>Sin cubrir</span>
                        <span><span class="badge-extra">HE</span> = hora extra</span>
                    </div>
                </div>

                <h2 class="visually-hidden">Calendario de <?= esc($nombreMes) ?> en <?= esc($sector->nombre) ?></h2>

                <!-- Desde 1400 px: tabla mensual -->
                <div class="d-none d-xxl-block rounded-3 border overflow-hidden">
                    <table class="table table-bordered align-top mb-0 tp-calendario">
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
                                            <td class="tp-celda-dia<?= $esHoy ? ' tp-celda-dia--hoy' : ($esPasado ? ' tp-celda-dia--pasado' : '') ?>"<?= $esHoy ? ' aria-current="date"' : '' ?>>
                                                <button type="button" class="tp-celda-dia__abrir" data-abrir-drawer="<?= esc($dia, 'attr') ?>">
                                                    <span class="d-inline-flex align-items-center gap-1" aria-hidden="true">
                                                        <span class="tp-num-dia"><?= $numDia ?></span>
                                                        <?php if ($esHoy): ?><span class="badge-hoy">Hoy</span><?php endif; ?>
                                                    </span>
                                                    <span class="tp-celda-dia__ver" aria-hidden="true">Ver <i class="bi bi-chevron-right"></i></span>
                                                    <span class="visually-hidden">Ver día <?= $numDia ?><?= $esHoy ? ', hoy' : '' ?> (<?= esc(fecha_larga($dia)) ?>): personal asignado</span>
                                                </button>
                                                <ul class="tp-celda-dia__turnos">
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
                                                </ul>
                                            </td>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Debajo de 1400 px: lista por día -->
                <div class="d-xxl-none">
                    <?php if ($diasAnteriores !== []): ?>
                        <details class="plan-dias-anteriores mb-2">
                            <summary>
                                <i class="bi bi-clock-history" aria-hidden="true"></i>
                                Ver <?= count($diasAnteriores) === 1 ? 'el día anterior' : 'los ' . count($diasAnteriores) . ' días anteriores' ?> de este mes
                            </summary>
                            <ul class="list-unstyled row row-cols-1 row-cols-md-2 row-cols-xl-3 g-2 mb-0">
                                <?php foreach ($diasAnteriores as $dia): ?>
                                    <?= view('admin/planificador/_dia_lista', $datosDiaLista($dia)) ?>
                                <?php endforeach; ?>
                            </ul>
                        </details>
                    <?php endif; ?>
                    <ul class="list-unstyled row row-cols-1 row-cols-md-2 row-cols-xl-3 g-2 mb-0">
                        <?php foreach ($diasVisibles as $dia): ?>
                            <?= view('admin/planificador/_dia_lista', $datosDiaLista($dia)) ?>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <p class="mt-3 mb-0 small text-body-secondary">
                    <i class="bi bi-info-circle me-1" aria-hidden="true"></i>
                    Use «Ver día» para consultar el personal asignado, reasignar o quitar turnos, y «+» para asignar.
                    Planificación habilitada hasta el <strong><?= esc(fecha_ar($fechaMaxima)) ?></strong>.
                </p>

                <!-- Carga horaria por enfermero/a -->
                <section class="mt-4 pt-3 border-top" aria-labelledby="titulo-horas-mes">
                    <h2 class="h5 fw-semibold mb-1" id="titulo-horas-mes">
                        <i class="bi bi-hourglass-split me-1" aria-hidden="true"></i>Carga horaria del personal
                    </h2>
                    <p class="small text-body-secondary mb-2">
                        <?php if ($resumenSemana !== null): ?>
                            Horas de esta semana (desde el lunes <?= esc(fecha_ar($lunesActual)) ?>) contra el tope semanal de cada contrato, y total de <?= esc($nombreMes) ?>. Suma todos los sectores.
                        <?php else: ?>
                            Total de horas de <?= esc($nombreMes) ?> en todos los sectores. El tope de cada contrato es semanal.
                        <?php endif; ?>
                    </p>

                    <?php if ($enfermeros === []): ?>
                        <p class="small text-body-secondary mb-0">No hay enfermeros/as activos.</p>
                    <?php else: ?>
                        <?php
                        $datosEnfermerosModal = [];
                        foreach ($enfermeros as $e) {
                            $eid    = (int) $e->id;
                            $datosE = $resumenMes[$eid] ?? ['total' => 0, 'extra' => 0];
                            $norm   = max(0, (int) $datosE['total'] - (int) $datosE['extra']);
                            $tope   = (int) $e->carga_horaria_max;
                            $semMin = $resumenSemana !== null ? (int) ($resumenSemana[$eid] ?? 0) : null;

                            $datosEnfermerosModal[$eid] = [
                                'nombre'       => $e->getNombreCompleto(),
                                'dni'          => (string) ($e->dni ?? '—'),
                                'matricula'    => (string) ($e->matricula ?: 'Sin matrícula'),
                                'especialidad' => (string) ($e->especialidad ?: 'Sin especialidad'),
                                'email'        => (string) ($e->email ?? '—'),
                                'telefono'     => (string) ($e->telefono ?: 'No registrado'),
                                'topeSemanal'  => $tope . ' h por semana',
                                'semana'       => $semMin !== null ? $horasTexto($semMin) . ' de ' . $tope . ' h' : null,
                                'totalMes'     => $horasTexto((int) $datosE['total']),
                                'normalesMes'  => $horasTexto($norm),
                                'extrasMes'    => $horasTexto((int) $datosE['extra']),
                                'urlPerfil'    => base_url('admin/usuarios/' . $eid . '/editar'),
                                'turnos'       => $turnosPorEnfermero[$eid] ?? [],
                            ];
                        }
                        ?>
                        <div data-filtro-lista>
                            <div class="tp-filtro-barra p-3 mb-2">
                                <div class="row g-2 align-items-end">
                                    <div class="col-12 col-md-6 col-xl-3">
                                        <label for="carga-buscar" class="form-label small fw-semibold mb-1">Buscar</label>
                                        <input type="search" class="form-control" id="carga-buscar" data-filtro-texto
                                               placeholder="Nombre o matrícula" autocomplete="off" enterkeyhint="search">
                                    </div>
                                    <div class="col-12 col-sm-6 col-md-6 col-xl-3">
                                        <label for="carga-especialidad" class="form-label small fw-semibold mb-1">Especialidad</label>
                                        <select class="form-select" id="carga-especialidad" data-filtro-campo="especialidad">
                                            <option value="" selected>Todas</option>
                                            <?php foreach ($especialidades as $esp): ?>
                                                <option value="<?= esc($esp, 'attr') ?>"><?= esc($esp) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-12 col-sm-6 col-md-6 col-xl-3">
                                        <label for="carga-filtro" class="form-label small fw-semibold mb-1">Carga</label>
                                        <select class="form-select" id="carga-filtro" data-filtro-grupo>
                                            <option value="" selected>Todas</option>
                                            <?php if ($resumenSemana !== null): ?>
                                                <option value="con-horas-libres">Con horas libres esta semana</option>
                                                <option value="sin-horas-libres">Sin horas libres esta semana</option>
                                            <?php endif; ?>
                                            <option value="con-guardias">Con guardias en el mes</option>
                                            <option value="sin-guardias">Sin guardias en el mes</option>
                                            <option value="con-extras">Con horas extra en el mes</option>
                                        </select>
                                    </div>
                                    <div class="col-12 col-md-6 col-xl-3">
                                        <label for="carga-orden" class="form-label small fw-semibold mb-1">Ordenar por</label>
                                        <select class="form-select" id="carga-orden" data-filtro-orden>
                                            <option value="nombre:asc" selected>Apellido (A a Z)</option>
                                            <option value="nombre:desc">Apellido (Z a A)</option>
                                            <?php if ($resumenSemana !== null): ?>
                                                <option value="semana:asc">Menos horas esta semana</option>
                                            <?php endif; ?>
                                            <option value="minutos:desc">Más horas en el mes</option>
                                            <option value="minutos:asc">Menos horas en el mes</option>
                                            <option value="extras:desc">Más horas extra</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                                <p class="small text-body-secondary mb-0" role="status" data-filtro-contador data-singular="enfermero/a" data-plural="enfermeros/as">
                                    <?= count($enfermeros) ?> <?= count($enfermeros) === 1 ? 'enfermero/a' : 'enfermeros/as' ?>
                                </p>
                                <button type="button" class="btn btn-outline-secondary d-none" data-filtro-restablecer data-solo-con-filtro>
                                    <i class="bi bi-arrow-counterclockwise me-1" aria-hidden="true"></i>Restablecer filtros
                                </button>
                            </div>

                            <ul class="list-unstyled row row-cols-1 row-cols-md-2 row-cols-xl-3 g-2 mb-0" data-filtro-items>
                                <?php foreach ($enfermeros as $e): ?>
                                    <?php
                                    $eid     = (int) $e->id;
                                    $datosE  = $resumenMes[$eid] ?? ['total' => 0, 'extra' => 0];
                                    $tope    = (int) $e->carga_horaria_max;
                                    $semMin  = $resumenSemana !== null ? (int) ($resumenSemana[$eid] ?? 0) : null;
                                    $grupos  = [(int) $datosE['total'] > 0 ? 'con-guardias' : 'sin-guardias'];
                                    if ((int) $datosE['extra'] > 0) {
                                        $grupos[] = 'con-extras';
                                    }
                                    $pct = 0;
                                    $claseCarga = 'carga-semana--ok';
                                    if ($semMin !== null) {
                                        $grupos[] = $tope > 0 && $semMin >= $tope * 60 ? 'sin-horas-libres' : 'con-horas-libres';
                                        $pct = $tope > 0 ? min(100, (int) round($semMin * 100 / ($tope * 60))) : 0;
                                        $claseCarga = $pct >= 100 ? 'carga-semana--completo' : ($pct >= 80 ? 'carga-semana--alto' : 'carga-semana--ok');
                                    }
                                    $idNombre = 'carga-nombre-' . $eid;
                                    ?>
                                    <li class="col" data-filtro-item
                                        data-buscar="<?= esc(mb_strtolower($e->getNombreCompleto() . ' ' . $e->matricula), 'attr') ?>"
                                        data-especialidad="<?= esc($e->especialidad ?? '', 'attr') ?>"
                                        data-grupos="<?= esc(implode(' ', $grupos), 'attr') ?>"
                                        data-orden-nombre="<?= esc(mb_strtolower($e->getNombreCompleto()), 'attr') ?>"
                                        data-orden-minutos="<?= (int) $datosE['total'] ?>"
                                        data-orden-extras="<?= (int) $datosE['extra'] ?>"
                                        data-orden-semana="<?= (int) ($semMin ?? 0) ?>">
                                        <div class="tp-carga-item">
                                            <span class="tp-carga-item__nombre" id="<?= $idNombre ?>"><?= esc($e->getNombreCompleto()) ?></span>
                                            <span class="tp-carga-item__datos">
                                                <?php if (! empty($e->especialidad)): ?>
                                                    <span class="badge tp-badge-especialidad"><?= esc($e->especialidad) ?></span>
                                                <?php endif; ?>
                                                <span class="tp-matricula"><?= ! empty($e->matricula) ? 'Mat. ' . esc((string) $e->matricula) : 'Sin matrícula' ?></span>
                                            </span>

                                            <?php if ($semMin !== null): ?>
                                                <div class="carga-semana <?= $claseCarga ?>">
                                                    <div class="d-flex justify-content-between gap-2 small">
                                                        <span><strong><?= esc($horasTexto($semMin)) ?> de <?= $tope ?> h</strong> esta semana</span>
                                                    </div>
                                                    <div class="progress mt-1" role="progressbar" aria-labelledby="<?= $idNombre ?>"
                                                         aria-valuenow="<?= $pct ?>" aria-valuemin="0" aria-valuemax="100" aria-valuetext="<?= esc($horasTexto($semMin) . ' de ' . $tope . ' h esta semana', 'attr') ?>">
                                                        <div class="progress-bar" style="width: <?= $pct ?>%"></div>
                                                    </div>
                                                    <p class="small mb-0 mt-1">
                                                        <?php if ($tope > 0 && $semMin >= $tope * 60): ?>
                                                            <i class="bi bi-exclamation-circle me-1" aria-hidden="true"></i>Sin horas libres esta semana
                                                        <?php else: ?>
                                                            <i class="bi bi-check-circle me-1" aria-hidden="true"></i>Le quedan <?= esc($horasTexto(max(0, $tope * 60 - $semMin))) ?> libres
                                                        <?php endif; ?>
                                                    </p>
                                                </div>
                                            <?php endif; ?>

                                            <span class="small">
                                                <?= esc($nombreMes) ?>: <strong><?= esc($horasTexto((int) $datosE['total'])) ?></strong>
                                                <?php if ($semMin === null): ?> · Tope semanal: <?= $tope ?> h<?php endif; ?>
                                                <?php if ((int) $datosE['extra'] > 0): ?>
                                                    <span class="badge-extra ms-1">Extra: <?= esc($horasTexto((int) $datosE['extra'])) ?></span>
                                                <?php endif; ?>
                                            </span>

                                            <button type="button" class="btn btn-outline-secondary align-self-start mt-auto" data-abrir-enfermero="<?= $eid ?>">
                                                <i class="bi bi-person-vcard me-1" aria-hidden="true"></i>Ver ficha<span class="visually-hidden"> y guardias de <?= esc($e->getNombreCompleto()) ?></span>
                                            </button>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>

                            <div class="text-center py-4 px-3 border rounded-3 d-none mt-2" data-filtro-vacio>
                                <i class="bi bi-funnel text-body-secondary fs-2 d-block mb-1" aria-hidden="true"></i>
                                <p class="small text-body-secondary mb-2">No hay enfermeros/as que coincidan con los filtros.</p>
                                <button type="button" class="btn btn-outline-secondary" data-filtro-restablecer>
                                    <i class="bi bi-arrow-counterclockwise me-1" aria-hidden="true"></i>Restablecer filtros
                                </button>
                            </div>
                        </div>

                        <script type="application/json" id="datos-enfermeros-mes"><?= json_encode($datosEnfermerosModal, $jsonSeguro) ?></script>
                    <?php endif; ?>
                </section>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php if ($sector !== null && $tipos !== []): ?>
    <script type="application/json" id="datos-planificador"><?= json_encode($detallePlanificador, $jsonSeguro) ?></script>

    <!-- Modal: ficha y guardias del mes de un enfermero/a -->
    <div class="modal fade" id="modalDetalleEnfermero" tabindex="-1" aria-labelledby="modalEnfTitulo" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable modal-fullscreen-sm-down">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header tp-modal-cabecera py-3 px-4">
                    <div class="d-flex align-items-center gap-3 min-w-0">
                        <span class="tp-icono-circulo" aria-hidden="true"><i class="bi bi-person-vcard"></i></span>
                        <div class="min-w-0">
                            <h2 class="modal-title h5 fw-bold mb-0" id="modalEnfTitulo">Ficha del enfermero/a</h2>
                            <p class="small text-body-secondary mb-0">
                                <span id="modalEnfEspecialidad">—</span> · <span class="tp-matricula" id="modalEnfMatricula">—</span> · Tope: <span id="modalEnfTope">—</span>
                            </p>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>

                <div class="modal-body tp-modal-cuerpo p-3 p-md-4">
                    <h3 class="visually-hidden">Datos de contacto</h3>
                    <div class="row g-2 mb-3">
                        <div class="col-12 col-sm-4"><div class="tp-dato"><div class="tp-dato__etiqueta">DNI</div><div class="tp-dato__valor" id="modalEnfDni">—</div></div></div>
                        <div class="col-12 col-sm-4"><div class="tp-dato"><div class="tp-dato__etiqueta">Correo electrónico</div><div class="tp-dato__valor" id="modalEnfEmail">—</div></div></div>
                        <div class="col-12 col-sm-4"><div class="tp-dato"><div class="tp-dato__etiqueta">Teléfono</div><div class="tp-dato__valor" id="modalEnfTelefono">—</div></div></div>
                    </div>

                    <h3 class="visually-hidden">Horas</h3>
                    <div class="row g-2 mb-3">
                        <div class="col-6 col-md-3 d-none" id="modalEnfSemanaCol"><div class="tp-dato tp-dato--destacado"><div class="tp-dato__etiqueta">Esta semana</div><div class="tp-dato__valor" id="modalEnfSemana">—</div></div></div>
                        <div class="col-6 col-md-3"><div class="tp-dato tp-dato--destacado"><div class="tp-dato__etiqueta">Total del mes</div><div class="tp-dato__valor" id="modalEnfTotalMes">0 h</div></div></div>
                        <div class="col-6 col-md-3"><div class="tp-dato"><div class="tp-dato__etiqueta">Horas regulares</div><div class="tp-dato__valor" id="modalEnfNormalesMes">0 h</div></div></div>
                        <div class="col-6 col-md-3"><div class="tp-dato tp-dato--extra"><div class="tp-dato__etiqueta">Horas extra</div><div class="tp-dato__valor" id="modalEnfExtrasMes">0 h</div></div></div>
                    </div>

                    <h3 class="h6 fw-bold mb-2">Guardias de <?= esc($nombreMes) ?></h3>
                    <ul id="modalEnfListaTurnos" class="list-unstyled d-flex flex-column gap-2 mb-0"></ul>
                </div>

                <div class="modal-footer bg-white px-4 py-3">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
                    <a id="modalEnfBtnEditar" href="<?= base_url('admin/usuarios') ?>" class="btn btn-tp fw-semibold">
                        <i class="bi bi-person-gear me-1" aria-hidden="true"></i>Editar legajo
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Panel lateral: detalle y gestión del día -->
    <div class="offcanvas offcanvas-end tp-drawer" tabindex="-1" id="drawerDetalleDia" aria-labelledby="drawerTituloDia">
        <div class="offcanvas-header tp-modal-cabecera border-bottom py-3 px-4">
            <div class="d-flex align-items-center gap-3 min-w-0">
                <span id="drawerNum" class="tp-icono-circulo" aria-hidden="true">1</span>
                <div class="min-w-0">
                    <h2 class="offcanvas-title h6 fw-bold mb-0" id="drawerTituloDia">Detalle del día</h2>
                    <p class="small text-body-secondary mb-0">
                        Sector <strong><?= esc($sector->nombre) ?></strong>
                        <span id="drawerTagHoy" class="badge-hoy ms-1 d-none">Hoy</span>
                    </p>
                </div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Cerrar panel del día"></button>
        </div>

        <div class="offcanvas-body tp-modal-cuerpo p-3 p-md-4">
            <div class="tp-resumen-dia mb-3">
                <span><i class="bi bi-people-fill me-1" aria-hidden="true"></i><span id="drawerResumenTexto">Dotación del día</span></span>
                <span id="drawerResumenEstado"></span>
            </div>
            <div id="drawerListaTurnos" class="d-flex flex-column gap-3"></div>
        </div>

        <div class="p-3 border-top bg-white d-flex justify-content-end">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="offcanvas">Cerrar</button>
        </div>
    </div>

    <!-- Modal: asignar o reasignar (AJAX). Las opciones las trae app.js desde admin/asignaciones/disponibilidad -->
    <div class="modal fade" id="modalAsignar" tabindex="-1" aria-labelledby="tituloModalAsignar" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable modal-fullscreen-sm-down">
            <form class="modal-content border-0 shadow-lg"
                  action="<?= base_url('admin/asignaciones/crear') ?>"
                  data-action-crear="<?= base_url('admin/asignaciones/crear') ?>"
                  data-base-asignaciones="<?= base_url('admin/asignaciones') ?>"
                  method="post" data-ajax-asignar novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="sector_id" value="">
                <input type="hidden" name="tipo_turno_id" value="">
                <input type="hidden" name="fecha" value="">

                <div class="modal-header tp-modal-cabecera py-3 px-4">
                    <div class="d-flex align-items-center gap-3 min-w-0">
                        <span class="tp-icono-circulo" aria-hidden="true"><i class="bi bi-person-plus-fill" id="iconoModalAsignar"></i></span>
                        <div class="min-w-0">
                            <h2 class="modal-title h6 fw-bold mb-0" id="tituloModalAsignar">Asignar turno</h2>
                            <p class="small text-body-secondary mb-0" id="subtituloModalAsignar">Seleccione el enfermero/a según disponibilidad y carga horaria.</p>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>

                <div class="modal-body tp-modal-cuerpo p-3 p-md-4">
                    <div class="tp-resumen-dia mb-3">
                        <div>
                            <span class="d-block small text-body-secondary fw-normal">Turno</span>
                            <span data-descripcion-turno>—</span>
                            <span id="bloqueReasignarActual" class="d-block small fw-normal text-body-secondary mt-1 d-none">
                                Asignado actualmente: <strong class="text-body" id="nombreEnfermeroActualReasignar">—</strong>
                            </span>
                        </div>
                    </div>

                    <div class="alerta alerta--error mb-3 d-none" role="alert" data-errores-modal></div>

                    <?= view('admin/planificador/_selector_enfermeros', [
                        'prefijo'        => 'modal',
                        'idCampo'        => 'modal_usuario_id',
                        'especialidades' => $especialidades,
                        'opciones'       => [],
                        'conEstados'     => false,
                        'ayudaId'        => 'ayuda-modal-enfermero',
                    ]) ?>
                    <p id="ayuda-modal-enfermero" class="form-text mb-0">Primero aparecen quienes tienen horas disponibles en la semana, luego quienes tomarían el turno como hora extra y al final quienes no pueden tomarlo, con el motivo.</p>
                </div>

                <div class="modal-footer bg-white px-4 py-3">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-tp fw-semibold px-4">
                        <i class="bi bi-person-check me-1" aria-hidden="true" id="iconoBtnSubmitAsignar"></i><span id="textoBtnSubmitAsignar">Asignar turno</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: quitar a un enfermero/a de un turno -->
    <div class="modal fade" id="modalQuitar" tabindex="-1" aria-labelledby="tituloModalQuitar" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content border-0 shadow-lg" action="" method="post" data-ajax-quitar data-base="<?= base_url('admin/asignaciones') ?>" novalidate>
                <?= csrf_field() ?>
                <div class="modal-header tp-modal-cabecera">
                    <h2 class="modal-title h6 fw-bold" id="tituloModalQuitar"><i class="bi bi-x-circle me-1" aria-hidden="true"></i>Quitar del turno</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-2">¿Quitar a <strong data-descripcion-turno></strong>?</p>
                    <p class="small text-body-secondary mb-0">El sector puede quedar por debajo de su dotación mínima.</p>
                    <div class="alerta alerta--error mt-3 d-none" role="alert" data-errores-modal></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger fw-semibold">Quitar</button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>
<?= $this->endSection() ?>
