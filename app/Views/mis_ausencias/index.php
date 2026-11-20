<?php
/**
 * HU-18: Mis ausencias (portal del enfermero/a).
 *
 * - Avisos enviados, del turno más reciente al más antiguo, con su estado (ícono + texto):
 *   en gestión, reemplazo asignado (con el nombre del reemplazo) o rechazado (con la observación de la coordinación).
 * - Próximos turnos sobre los que todavía puede avisar, cada uno con "Informar ausencia".
 *
 * @var list<\App\Entities\Ausencia>   $avisos
 * @var list<\App\Entities\Asignacion> $proximos
 * @var string                         $ahora
 */
use App\Entities\TipoTurno;

$etiqueta = static fn (string $color, string $nombre): string => '<span class="etiqueta-turno" style="--color-turno: ' . esc($color, 'attr')
    . '; --color-turno-texto: ' . esc(TipoTurno::textoSobre($color), 'attr') . ';">' . esc($nombre) . '</span>';
$pendientes = count(array_filter($avisos, static fn ($a) => $a->estaPendiente()));
?>
<?= $this->extend('layouts/main') ?>

<?= $this->section('contenido') ?>
<div class="card shadow-sm border-0 rounded-3 overflow-hidden">

    <div class="card-header tp-card-header py-3 px-4">
        <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-3">
            <div class="d-flex align-items-center">
                <div class="me-3"><img src="<?= base_url('img/logo-64.png') ?>" alt="Logo TurnoPlaner" class="barra__logo"></div>
                <div>
                    <h1 class="h4 mb-0 fw-semibold text-white">Mis ausencias</h1>
                    <p class="mb-0 small subtitulo">Avisos de inasistencia y su estado</p>
                </div>
            </div>
            <?php if ($proximos !== []): ?>
                <a href="#informar" class="btn btn-light fw-semibold shadow-sm text-nowrap">
                    <i class="bi bi-person-x me-1" aria-hidden="true"></i>Informar una ausencia
                </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="card-body p-3 p-md-4 bg-white">

        <!-- Avisos enviados -->
        <section aria-labelledby="titulo-avisos" class="mb-4">
            <h2 class="h5 fw-semibold mb-1" id="titulo-avisos">Avisos enviados</h2>
            <?php if ($avisos === []): ?>
                <div class="text-center py-5 border rounded-3 mt-3">
                    <i class="bi bi-calendar2-check fs-1 text-body-secondary d-block mb-2" aria-hidden="true"></i>
                    <p class="fw-semibold mb-1">Todavía no informó ninguna ausencia.</p>
                    <p class="text-body-secondary small mb-0">Si no puede presentarse a un turno, avise desde su cronograma o desde la lista de abajo.</p>
                </div>
            <?php else: ?>
                <p class="small text-body-secondary mb-3">
                    <?= count($avisos) === 1 ? '1 aviso' : count($avisos) . ' avisos' ?><?= $pendientes > 0 ? ' · ' . ($pendientes === 1 ? '1 en gestión' : $pendientes . ' en gestión') : '' ?>
                </p>
                <ul class="row g-3 list-unstyled mb-0">
                    <?php foreach ($avisos as $a): ?>
                        <?php $terminado = $a->turnoTerminado($ahora); ?>
                        <li class="col-12 col-xl-6">
                            <article class="tp-aviso tp-aviso--<?= esc(strtolower((string) $a->estado), 'attr') ?> h-100 p-3" aria-labelledby="aviso-<?= (int) $a->id ?>">
                                <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
                                    <h3 class="h6 fw-semibold mb-0" id="aviso-<?= (int) $a->id ?>">
                                        <span class="text-capitalize"><?= esc(fecha_larga((string) $a->fecha)) ?></span>
                                    </h3>
                                    <span class="estado-turno tp-estado-aviso tp-estado-aviso--<?= esc(strtolower((string) $a->estado), 'attr') ?>">
                                        <i class="bi <?= esc($a->iconoEstado(), 'attr') ?>" aria-hidden="true"></i><?= esc($a->estadoParaEnfermero()) ?>
                                    </span>
                                </div>

                                <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                                    <?= $etiqueta($a->colorTipo(), (string) $a->tipo_nombre) ?>
                                    <span class="text-nowrap"><i class="bi bi-clock me-1" aria-hidden="true"></i><?= esc($a->horarioTexto()) ?></span>
                                    <span><i class="bi bi-hospital me-1" aria-hidden="true"></i><?= esc((string) $a->sector_nombre) ?></span>
                                </div>

                                <dl class="tp-aviso__datos small mb-0">
                                    <dt>Motivo</dt>
                                    <dd><?= esc($a->motivoTexto()) ?></dd>
                                    <dt>Descripción</dt>
                                    <dd class="text-break"><?= esc((string) $a->descripcion) ?></dd>
                                    <dt>Enviado</dt>
                                    <dd><?= esc(fecha_ar($a->created_at, true)) ?></dd>
                                </dl>

                                <?php if ($a->estaCubierta()): ?>
                                    <p class="tp-aviso__nota tp-aviso__nota--ok small mb-0 mt-2">
                                        <i class="bi bi-person-check me-1" aria-hidden="true"></i>
                                        Reemplazo: <strong><?= esc($a->nombreReemplazante() !== '' ? $a->nombreReemplazante() : 'asignado por la coordinación') ?></strong>.
                                        <?= $terminado ? '' : 'No debe presentarse a este turno.' ?>
                                    </p>
                                <?php elseif ($a->estaRechazada()): ?>
                                    <div class="tp-aviso__nota tp-aviso__nota--error small mt-2">
                                        <p class="mb-1"><i class="bi bi-chat-left-text me-1" aria-hidden="true"></i><strong>Observación de la coordinación:</strong></p>
                                        <p class="mb-1 text-break"><?= esc($a->observacionAdmin() !== '' ? $a->observacionAdmin() : 'Sin observación.') ?></p>
                                        <?php if (! $terminado): ?><p class="mb-0 fw-semibold">Debe presentarse al turno.</p><?php endif; ?>
                                    </div>
                                <?php elseif (! $terminado): ?>
                                    <p class="small text-body-secondary mb-0 mt-2">La coordinación está buscando un reemplazo. Le avisaremos el resultado en esta pantalla.</p>
                                <?php endif; ?>

                                <?php if (! empty($a->asignacion_eliminada)): ?>
                                    <p class="small text-body-secondary mb-0 mt-2"><i class="bi bi-info-circle me-1" aria-hidden="true"></i>Este turno ya no figura en su cronograma.</p>
                                <?php endif; ?>
                            </article>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <!-- Próximos turnos sobre los que puede avisar -->
        <section aria-labelledby="titulo-informar" id="informar" tabindex="-1" class="tp-informar">
            <h2 class="h5 fw-semibold mb-1" id="titulo-informar">Informar una ausencia</h2>
            <p class="small text-body-secondary mb-3">Elija el turno al que no podrá presentarse. Solo se puede avisar en turnos que todavía no empezaron. Se muestran los próximos <?= \App\Controllers\MisAusencias::DIAS_PROXIMOS ?> días; para turnos posteriores, use <a href="<?= base_url('mi-cronograma') ?>">Mi cronograma</a>.</p>

            <?php if ($proximos === []): ?>
                <div class="text-center py-4 border rounded-3">
                    <i class="bi bi-calendar-x fs-2 text-body-secondary d-block mb-2" aria-hidden="true"></i>
                    <p class="fw-semibold mb-1">No tiene turnos próximos sobre los que pueda avisar.</p>
                    <p class="text-body-secondary small mb-0">Los turnos que ya tienen un aviso enviado figuran arriba.</p>
                </div>
            <?php else: ?>
                <ul class="list-unstyled mb-0 tp-proximos">
                    <?php foreach ($proximos as $t): ?>
                        <li class="tp-proximos__item d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2 p-3">
                            <div class="tp-proximos__datos">
                                <p class="fw-semibold mb-1 text-capitalize"><?= esc(fecha_larga((string) $t->fecha)) ?></p>
                                <div class="d-flex flex-wrap align-items-center gap-2 small">
                                    <?= $etiqueta($t->colorTipo(), (string) $t->tipo_nombre) ?>
                                    <span class="text-nowrap"><i class="bi bi-clock me-1" aria-hidden="true"></i><?= esc($t->horaInicio()) ?> a <?= esc($t->horaFin()) ?><?= $t->terminaAlDiaSiguiente() ? ' (+1 día)' : '' ?></span>
                                    <span><i class="bi bi-hospital me-1" aria-hidden="true"></i><?= esc((string) $t->sector_nombre) ?></span>
                                </div>
                            </div>
                            <a class="btn tp-accion-turno flex-shrink-0" href="<?= base_url('mis-ausencias/nueva/' . (int) $t->id) ?>">
                                <i class="bi bi-person-x" aria-hidden="true"></i>Informar ausencia<span class="visually-hidden"> al turno <?= esc((string) $t->tipo_nombre) ?> del <?= esc(fecha_larga((string) $t->fecha)) ?></span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    </div>
</div>
<?= $this->endSection() ?>
