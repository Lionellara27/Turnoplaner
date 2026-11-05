<?php
/**
 * HU-10: consulta, edición y baja lógica de sectores.
 *
 * @var list<\App\Entities\Sector>                       $sectores
 * @var \CodeIgniter\Pager\Pager                         $pager
 * @var array{estado:string, tipo:string, q:string}      $filtros
 */
$sectores   = $sectores ?? [];
$filtros    = ($filtros ?? []) + ['estado' => 'ACTIVO', 'tipo' => '', 'q' => ''];
$hayFiltros = $filtros['q'] !== '' || $filtros['tipo'] !== '' || $filtros['estado'] !== 'ACTIVO';
$total      = isset($pager) ? $pager->getTotal() : count($sectores);
$detalleBaja = 'No podrá elegirse para nuevas asignaciones de turnos. Los datos del sector se conservan.';
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
                    <h1 class="h4 mb-0 fw-semibold text-white">Sectores del hospital</h1>
                    <p class="mb-0 small subtitulo">Servicios y dotación mínima de enfermería</p>
                </div>
            </div>
            <a href="<?= base_url('admin/sectores/crear') ?>" class="btn btn-light text-nowrap fw-semibold shadow-sm">
                <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Nuevo sector
            </a>
        </div>
    </div>

    <div class="card-body p-4 bg-white">

        <form method="get" action="<?= base_url('admin/sectores') ?>" class="row g-2 mb-3 align-items-end" role="search" aria-label="Buscar sectores">
            <div class="col-12 col-lg-4">
                <label for="q" class="form-label small fw-semibold mb-1">Buscar</label>
                <div class="input-group">
                    <span class="input-group-text bg-light"><i class="bi bi-search" aria-hidden="true"></i></span>
                    <input type="search" id="q" name="q" class="form-control" maxlength="80"
                           placeholder="Nombre o descripción" value="<?= esc($filtros['q']) ?>">
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <label for="estado" class="form-label small fw-semibold mb-1">Estado</label>
                <select id="estado" name="estado" class="form-select">
                    <option value="ACTIVO" <?= $filtros['estado'] === 'ACTIVO' ? 'selected' : '' ?>>Activos</option>
                    <option value="BAJA" <?= $filtros['estado'] === 'BAJA' ? 'selected' : '' ?>>Dados de baja</option>
                    <option value="TODOS" <?= $filtros['estado'] === 'TODOS' ? 'selected' : '' ?>>Todos</option>
                </select>
            </div>
            <div class="col-6 col-lg-3">
                <label for="tipo" class="form-label small fw-semibold mb-1">Tipo de área</label>
                <select id="tipo" name="tipo" class="form-select">
                    <option value="">Todas</option>
                    <option value="CRITICO" <?= $filtros['tipo'] === 'CRITICO' ? 'selected' : '' ?>>Críticas</option>
                    <option value="GENERAL" <?= $filtros['tipo'] === 'GENERAL' ? 'selected' : '' ?>>Generales</option>
                </select>
            </div>
            <div class="col-12 col-lg-2 d-flex gap-2">
                <button class="btn btn-outline-secondary flex-fill" type="submit"><i class="bi bi-funnel me-1" aria-hidden="true"></i>Filtrar</button>
                <?php if ($hayFiltros): ?>
                    <a href="<?= base_url('admin/sectores') ?>" class="btn btn-outline-secondary" title="Limpiar filtros" aria-label="Limpiar filtros">
                        <i class="bi bi-x-lg" aria-hidden="true"></i>
                    </a>
                <?php endif; ?>
            </div>
        </form>

        <p class="small text-body-secondary mb-2" aria-live="polite"><?= $total === 1 ? '1 resultado' : esc((string) $total) . ' resultados' ?></p>

        <?php if ($sectores === []): ?>
            <div class="text-center py-5 border rounded-3">
                <i class="bi bi-hospital fs-1 text-body-secondary d-block mb-2" aria-hidden="true"></i>
                <?php if ($hayFiltros): ?>
                    <p class="fw-semibold mb-1">No hay sectores para esa búsqueda.</p>
                    <p class="text-body-secondary small mb-3">Pruebe con otro nombre o quite los filtros.</p>
                    <a href="<?= base_url('admin/sectores') ?>" class="btn btn-outline-secondary btn-sm">Quitar filtros</a>
                <?php else: ?>
                    <p class="fw-semibold mb-1">Todavía no hay sectores cargados.</p>
                    <p class="text-body-secondary small mb-3">Cree el primero (por ejemplo UTI o Guardia) para poder asignar turnos.</p>
                    <a href="<?= base_url('admin/sectores/crear') ?>" class="btn btn-tp btn-sm">Nuevo sector</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <!-- Menos de 1200 px (con el menú lateral abierto la tabla no entra): tarjetas -->
            <ul class="list-unstyled row row-cols-1 row-cols-md-2 g-2 d-xl-none mb-0">
                <?php foreach ($sectores as $s): ?>
                    <li class="col"><div class="border rounded-3 p-3 h-100">
                        <div class="d-flex justify-content-between align-items-start flex-wrap gap-1">
                            <div class="fw-semibold"><?= esc($s->nombre) ?></div>
                            <?= view('partials/estado_registro', ['activo' => $s->estaActivo()]) ?>
                        </div>
                        <div class="mb-2"><?= view('admin/sectores/_tipo', ['s' => $s]) ?></div>
                        <p class="small mb-1"><i class="bi bi-people me-1" aria-hidden="true"></i><?= esc($s->dotacionTexto()) ?></p>
                        <?php if (! empty($s->descripcion)): ?>
                            <p class="small text-body-secondary mb-2"><?= esc($s->descripcion) ?></p>
                        <?php endif; ?>
                        <div class="d-flex flex-wrap gap-2">
                            <?= view('partials/acciones_catalogo', ['id' => (int) $s->id, 'nombre' => $s->nombre, 'activo' => $s->estaActivo(), 'ruta' => 'admin/sectores', 'prefijo' => 'Sector', 'conTexto' => true]) ?>
                        </div>
                    </div></li>
                <?php endforeach; ?>
            </ul>

            <!-- 1200 px o más: tabla -->
            <div class="table-responsive d-none d-xl-block">
                <table class="table table-hover align-middle border mb-0">
                    <caption class="visually-hidden">Listado de sectores del hospital</caption>
                    <thead class="table-light">
                        <tr>
                            <th scope="col">Sector</th>
                            <th scope="col">Tipo</th>
                            <th scope="col">Dotación mínima</th>
                            <th scope="col">Estado</th>
                            <th scope="col" class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($sectores as $s): ?>
                        <tr>
                            <td>
                                <div class="fw-semibold"><?= esc($s->nombre) ?></div>
                                <?php if (! empty($s->descripcion)): ?>
                                    <small class="text-body-secondary"><?= esc($s->descripcion) ?></small>
                                <?php endif; ?>
                            </td>
                            <td class="text-nowrap"><?= view('admin/sectores/_tipo', ['s' => $s]) ?></td>
                            <td class="text-nowrap"><?= esc($s->dotacionTexto()) ?></td>
                            <td><?= view('partials/estado_registro', ['activo' => $s->estaActivo()]) ?></td>
                            <td class="text-end text-nowrap">
                                <?= view('partials/acciones_catalogo', ['id' => (int) $s->id, 'nombre' => $s->nombre, 'activo' => $s->estaActivo(), 'ruta' => 'admin/sectores', 'prefijo' => 'Sector', 'conTexto' => false]) ?>
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

<?php foreach ($sectores as $s): ?>
    <?php if ($s->estaActivo()): ?>
        <?= view('partials/modal_baja_catalogo', ['id' => (int) $s->id, 'nombre' => 'el sector ' . $s->nombre, 'ruta' => 'admin/sectores', 'prefijo' => 'Sector', 'detalle' => $detalleBaja]) ?>
    <?php endif; ?>
<?php endforeach; ?>
<?= $this->endSection() ?>
