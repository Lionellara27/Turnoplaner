<?php
/**
 * HU-16: listado de ausencias informadas por el personal, con filtro de estado (Pendientes por defecto)
 * y las acciones "Asignar reemplazo" y "Rechazar" sobre las pendientes.
 * Debajo de 1200 px, una tarjeta por ausencia; desde 1200 px, tabla.
 *
 * @var string                       $estado    filtro activo (PENDIENTE, CUBIERTA, RECHAZADA o TODAS)
 * @var array<string, int>           $conteos   cantidad por filtro
 * @var list<\App\Entities\Ausencia> $ausencias
 */
use App\Models\AusenciaModel;

$total = count($ausencias);
$vacio = [
    AusenciaModel::PENDIENTE    => 'No hay ausencias pendientes de resolver.',
    AusenciaModel::CUBIERTA     => 'Todavía no hay ausencias cubiertas con reemplazo.',
    AusenciaModel::RECHAZADA    => 'No hay ausencias rechazadas.',
    AusenciaModel::FILTRO_TODAS => 'El personal todavía no informó ausencias.',
];
?>
<?= $this->extend('layouts/main') ?>

<?= $this->section('contenido') ?>
<div class="card shadow-sm border-0 rounded-3 overflow-hidden">

    <div class="card-header tp-card-header py-3 px-4">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="d-flex align-items-center min-w-0">
                <div class="me-3"><img src="<?= base_url('img/logo-64.png') ?>" alt="Logo TurnoPlaner" class="barra__logo"></div>
                <div class="min-w-0">
                    <h1 class="h4 mb-0 fw-semibold text-white">Ausencias</h1>
                    <p class="mb-0 small subtitulo">Avisos del personal: asigne un reemplazo o rechace el aviso</p>
                </div>
            </div>
            <a href="<?= base_url('admin/tablero') ?>" class="btn btn-light text-nowrap fw-semibold shadow-sm">
                <i class="bi bi-grid-3x3-gap me-1" aria-hidden="true"></i>Tablero de cobertura
            </a>
        </div>
    </div>

    <div class="card-body p-3 p-md-4 bg-white">

        <!-- Filtro de estado: links (sin autoenvío), el activo con aria-current, tilde y borde -->
        <nav aria-label="Filtrar ausencias por estado" class="mb-3">
            <ul class="selector-sector list-unstyled d-flex flex-wrap gap-2 mb-0">
                <?php foreach (AusenciaModel::FILTROS as $valor => $texto): ?>
                    <?php $activo = $valor === $estado; ?>
                    <li>
                        <a href="<?= base_url('admin/ausencias') . '?' . http_build_query(['estado' => $valor], '', '&amp;') ?>" class="btn btn-outline-secondary"<?= $activo ? ' aria-current="page"' : '' ?>>
                            <?php if ($activo): ?><i class="bi bi-check2" aria-hidden="true"></i><?php endif; ?>
                            <?= esc($texto) ?>
                            <span class="badge rounded-pill tp-badge-contador"><?= (int) ($conteos[$valor] ?? 0) ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </nav>

        <p class="small text-body-secondary mb-2" aria-live="polite">
            <?= $total === 1 ? '1 ausencia' : $total . ' ausencias' ?> · <?= esc(AusenciaModel::FILTROS[$estado]) ?>
            <?= $estado === AusenciaModel::PENDIENTE && $total > 0 ? '· ordenadas por el turno más próximo' : '' ?>
        </p>

        <?php if ($ausencias === []): ?>
            <div class="text-center py-5 border rounded-3">
                <i class="bi bi-inbox fs-1 text-body-secondary d-block mb-2" aria-hidden="true"></i>
                <p class="fw-semibold mb-1"><?= esc($vacio[$estado]) ?></p>
                <?php if ($estado === AusenciaModel::PENDIENTE): ?>
                    <p class="small text-body-secondary mb-3">Cuando un enfermero/a informe una ausencia, aparecerá acá para gestionarla.</p>
                    <a href="<?= base_url('admin/tablero') ?>" class="btn btn-outline-secondary">Ver el tablero de cobertura</a>
                <?php else: ?>
                    <a href="<?= base_url('admin/ausencias') ?>" class="btn btn-outline-secondary mt-2">Ver las pendientes</a>
                <?php endif; ?>
            </div>
        <?php else: ?>

            <!-- Menos de 1200 px: una tarjeta por ausencia -->
            <ul class="list-unstyled row row-cols-1 row-cols-md-2 g-2 d-xl-none mb-0">
                <?php foreach ($ausencias as $a): ?>
                    <li class="col">
                        <article class="border rounded-3 p-3 h-100 d-flex flex-column gap-2" aria-labelledby="ausencia-<?= (int) $a->id ?>">
                            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                                <div class="min-w-0">
                                    <h2 class="h6 fw-semibold mb-0" id="ausencia-<?= (int) $a->id ?>"><?= esc($a->nombreEnfermero()) ?></h2>
                                    <span class="tp-matricula"><?= ! empty($a->enfermero_matricula) ? 'Mat. ' . esc($a->enfermero_matricula) : 'Sin matrícula' ?></span>
                                </div>
                                <?= view('admin/ausencias/_estado', ['a' => $a]) ?>
                            </div>
                            <div><?= view('admin/ausencias/_turno', ['a' => $a]) ?></div>
                            <div class="small">
                                <span class="fw-semibold"><?= esc($a->motivoTexto()) ?>:</span>
                                <span class="text-break"><?= esc((string) $a->descripcion) ?></span>
                                <span class="d-block text-body-secondary">Informada el <?= esc(fecha_ar($a->created_at, true)) ?></span>
                            </div>
                            <?= view('admin/ausencias/_resolucion', ['a' => $a]) ?>
                            <div class="d-flex flex-wrap gap-2 mt-auto pt-1">
                                <?= view('admin/ausencias/_acciones', ['a' => $a]) ?>
                            </div>
                        </article>
                    </li>
                <?php endforeach; ?>
            </ul>

            <!-- 1200 px en adelante: tabla -->
            <div class="table-responsive d-none d-xl-block">
                <table class="table table-hover align-middle border mb-0">
                    <caption class="visually-hidden">Ausencias informadas: <?= esc(AusenciaModel::FILTROS[$estado]) ?></caption>
                    <thead class="table-light">
                        <tr>
                            <th scope="col">Enfermero/a <span class="fw-normal text-body-secondary">· Informada</span></th>
                            <th scope="col">Turno <span class="fw-normal text-body-secondary">· Sector</span></th>
                            <th scope="col">Motivo y descripción</th>
                            <th scope="col">Estado</th>
                            <th scope="col" class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ausencias as $a): ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold"><?= esc($a->nombreEnfermero()) ?></div>
                                    <span class="tp-matricula d-block"><?= ! empty($a->enfermero_matricula) ? 'Mat. ' . esc($a->enfermero_matricula) : 'Sin matrícula' ?></span>
                                    <small class="text-body-secondary"><?= esc(fecha_ar($a->created_at, true)) ?></small>
                                </td>
                                <td><?= view('admin/ausencias/_turno', ['a' => $a]) ?></td>
                                <td class="tp-ausencia-descripcion">
                                    <div class="fw-semibold small"><?= esc($a->motivoTexto()) ?></div>
                                    <div class="small text-break"><?= esc((string) $a->descripcion) ?></div>
                                </td>
                                <td>
                                    <?= view('admin/ausencias/_estado', ['a' => $a]) ?>
                                    <div class="mt-1"><?= view('admin/ausencias/_resolucion', ['a' => $a]) ?></div>
                                </td>
                                <td class="text-end">
                                    <div class="d-flex flex-column align-items-end gap-2">
                                        <?= view('admin/ausencias/_acciones', ['a' => $a]) ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modales de rechazo (fuera de la tabla) -->
<?php foreach ($ausencias as $a): ?>
    <?php if ($a->puedeRechazarse()): ?>
        <div class="modal fade" id="modalRechazar<?= (int) $a->id ?>" tabindex="-1" aria-labelledby="tituloRechazar<?= (int) $a->id ?>" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2 class="modal-title h6" id="tituloRechazar<?= (int) $a->id ?>"><i class="bi bi-x-circle me-1" aria-hidden="true"></i>Rechazar la ausencia de <?= esc($a->nombreEnfermero()) ?></h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <?= view('admin/ausencias/_form_rechazo', ['a' => $a, 'idCampo' => 'observacion-' . (int) $a->id, 'enModal' => true]) ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
<?php endforeach; ?>
<?= $this->endSection() ?>
