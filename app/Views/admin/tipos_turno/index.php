<?php
/**
 * HU-12: consulta, edición y baja lógica de tipos de turno.
 *
 * @var list<\App\Entities\TipoTurno>        $tipos
 * @var \CodeIgniter\Pager\Pager             $pager
 * @var array{estado:string, q:string}       $filtros
 */
$tipos      = $tipos ?? [];
$filtros    = ($filtros ?? []) + ['estado' => 'ACTIVO', 'q' => ''];
$hayFiltros = $filtros['q'] !== '' || $filtros['estado'] !== 'ACTIVO';
$total      = isset($pager) ? $pager->getTotal() : count($tipos);
$detalleBaja = 'Ya no aparecerá para asignar turnos. No se permite si tiene turnos asignados desde hoy en adelante.';
?>
<?= $this->extend('layouts/main') ?>

<?= $this->section('contenido') ?>
<div class="card shadow-sm border-0 rounded-3 overflow-hidden">

    <div class="card-header tp-card-header py-3 px-4">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="d-flex align-items-center">
                <div class="me-3">
                    <img src="<?= base_url('img/logo-64.png') ?>" alt="Logo TurnoPlaner" class="barra__logo">
                </div>
                <div>
                    <h1 class="h4 mb-0 fw-semibold text-white">Tipos de turno</h1>
                    <p class="mb-0 small subtitulo">Franjas horarias para planificar las guardias</p>
                </div>
            </div>
            <a href="<?= base_url('admin/tipos-turno/crear') ?>" class="btn btn-light text-nowrap fw-semibold shadow-sm">
                <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Nuevo tipo de turno
            </a>
        </div>
    </div>

    <div class="card-body p-4 bg-white">

        <form method="get" action="<?= base_url('admin/tipos-turno') ?>" class="row g-2 mb-3 align-items-end" role="search" aria-label="Buscar tipos de turno">
            <div class="col-12 col-md-6 col-lg-6">
                <label for="q" class="form-label small fw-semibold mb-1">Buscar</label>
                <div class="input-group">
                    <span class="input-group-text bg-light"><i class="bi bi-search" aria-hidden="true"></i></span>
                    <input type="search" id="q" name="q" class="form-control" maxlength="60" placeholder="Nombre del turno" value="<?= esc($filtros['q']) ?>">
                </div>
            </div>
            <div class="col-12 col-sm-6 col-md-3">
                <label for="estado" class="form-label small fw-semibold mb-1">Estado</label>
                <select id="estado" name="estado" class="form-select">
                    <option value="ACTIVO" <?= $filtros['estado'] === 'ACTIVO' ? 'selected' : '' ?>>Activos</option>
                    <option value="BAJA" <?= $filtros['estado'] === 'BAJA' ? 'selected' : '' ?>>Dados de baja</option>
                    <option value="TODOS" <?= $filtros['estado'] === 'TODOS' ? 'selected' : '' ?>>Todos</option>
                </select>
            </div>
            <div class="col-12 col-sm-6 col-md-3 d-flex gap-2">
                <button class="btn btn-outline-secondary flex-fill" type="submit"><i class="bi bi-funnel me-1" aria-hidden="true"></i>Filtrar</button>
                <?php if ($hayFiltros): ?>
                    <a href="<?= base_url('admin/tipos-turno') ?>" class="btn btn-outline-secondary" title="Limpiar filtros" aria-label="Limpiar filtros">
                        <i class="bi bi-x-lg" aria-hidden="true"></i>
                    </a>
                <?php endif; ?>
            </div>
        </form>

        <p class="small text-body-secondary mb-2" aria-live="polite"><?= $total === 1 ? '1 resultado' : esc((string) $total) . ' resultados' ?></p>

        <?php if ($tipos === []): ?>
            <div class="text-center py-5 border rounded-3">
                <i class="bi bi-clock fs-1 text-body-secondary d-block mb-2" aria-hidden="true"></i>
                <?php if ($hayFiltros): ?>
                    <p class="fw-semibold mb-1">No hay tipos de turno para esa búsqueda.</p>
                    <a href="<?= base_url('admin/tipos-turno') ?>" class="btn btn-outline-secondary btn-sm">Quitar filtros</a>
                <?php else: ?>
                    <p class="fw-semibold mb-1">Todavía no hay tipos de turno.</p>
                    <p class="text-body-secondary small mb-3">Cree las franjas (Mañana, Tarde, Noche…) para poder asignar turnos.</p>
                    <a href="<?= base_url('admin/tipos-turno/crear') ?>" class="btn btn-tp btn-sm">Nuevo tipo de turno</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <!-- Menos de 1200 px (con el menú lateral abierto la tabla no entra): tarjetas -->
            <ul class="list-unstyled row row-cols-1 row-cols-md-2 g-2 d-xl-none mb-0">
                <?php foreach ($tipos as $t): ?>
                    <li class="col"><div class="border rounded-3 p-3 h-100">
                        <div class="d-flex justify-content-between align-items-start flex-wrap gap-1 mb-2">
                            <?= view('admin/tipos_turno/_etiqueta', ['t' => $t]) ?>
                            <?= view('partials/estado_registro', ['activo' => $t->estaActivo()]) ?>
                        </div>
                        <p class="mb-1"><i class="bi bi-clock me-1" aria-hidden="true"></i><?= view('admin/tipos_turno/_rango', ['t' => $t]) ?></p>
                        <p class="small text-body-secondary mb-2"><?= esc($t->duracionTexto()) ?><?= $t->cruzaMedianoche() ? ' · cruza la medianoche' : '' ?></p>
                        <div class="d-flex flex-wrap gap-2">
                            <?= view('partials/acciones_catalogo', ['id' => (int) $t->id, 'nombre' => $t->nombre, 'activo' => $t->estaActivo(), 'ruta' => 'admin/tipos-turno', 'prefijo' => 'Tipo', 'conTexto' => true]) ?>
                        </div>
                    </div></li>
                <?php endforeach; ?>
            </ul>

            <!-- 1200 px o más: tabla -->
            <div class="table-responsive d-none d-xl-block">
                <table class="table table-hover align-middle border mb-0">
                    <caption class="visually-hidden">Listado de tipos de turno</caption>
                    <thead class="table-light">
                        <tr>
                            <th scope="col">Turno</th>
                            <th scope="col">Horario</th>
                            <th scope="col" class="text-center">Horas</th>
                            <th scope="col">Estado</th>
                            <th scope="col" class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($tipos as $t): ?>
                        <tr>
                            <td><?= view('admin/tipos_turno/_etiqueta', ['t' => $t]) ?></td>
                            <td class="text-nowrap">
                                <?= view('admin/tipos_turno/_rango', ['t' => $t]) ?>
                                <?php if ($t->cruzaMedianoche()): ?>
                                    <small class="d-block text-body-secondary"><i class="bi bi-moon-stars me-1" aria-hidden="true"></i>Cruza la medianoche</small>
                                <?php endif; ?>
                            </td>
                            <td class="text-center text-nowrap"><?= esc($t->duracionTexto()) ?></td>
                            <td><?= view('partials/estado_registro', ['activo' => $t->estaActivo()]) ?></td>
                            <td class="text-end text-nowrap">
                                <?= view('partials/acciones_catalogo', ['id' => (int) $t->id, 'nombre' => $t->nombre, 'activo' => $t->estaActivo(), 'ruta' => 'admin/tipos-turno', 'prefijo' => 'Tipo', 'conTexto' => false]) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <nav class="mt-3 d-flex justify-content-center" aria-label="Paginación del listado">
                <?= isset($pager) ? $pager->links('default', 'bootstrap_tp') : '' ?>
            </nav>
        <?php endif; ?>
    </div>
</div>

<?php foreach ($tipos as $t): ?>
    <?php if ($t->estaActivo()): ?>
        <?= view('partials/modal_baja_catalogo', ['id' => (int) $t->id, 'nombre' => 'el turno ' . $t->nombre, 'ruta' => 'admin/tipos-turno', 'prefijo' => 'Tipo', 'detalle' => $detalleBaja]) ?>
    <?php endif; ?>
<?php endforeach; ?>
<?= $this->endSection() ?>
