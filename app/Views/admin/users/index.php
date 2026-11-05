<?php
/**
 * HU-06: Consulta y búsqueda de personal (con acciones de HU-04, HU-07 y HU-08).
 *
 * @var list<\App\Entities\Usuario>       $usuarios
 * @var \CodeIgniter\Pager\Pager          $pager
 * @var array{estado:string, especialidad:string, q:string} $filtros
 * @var list<string>                      $especialidades
 */
// Valores por defecto: la vista también se renderiza sin filtros ni paginación (por ejemplo, desde spark test:hu05).
$usuarios       = $usuarios ?? [];
$pager          = $pager ?? null;
$filtros        = ($filtros ?? []) + ['estado' => 'ACTIVO', 'especialidad' => '', 'q' => ''];
$especialidades = $especialidades ?? \App\Models\UsuarioModel::ESPECIALIDADES;

$hayFiltros = $filtros['q'] !== '' || $filtros['especialidad'] !== '' || $filtros['estado'] !== 'ACTIVO';
$total      = $pager !== null ? $pager->getTotal() : count($usuarios);
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
                    <h1 class="h4 mb-0 fw-semibold text-white">Gestión de Personal de Enfermería</h1>
                    <p class="mb-0 small subtitulo">Plantel y administración de legajos</p>
                </div>
            </div>
            <a href="<?= base_url('admin/usuarios/crear') ?>" class="btn btn-light text-nowrap fw-semibold shadow-sm">
                <i class="bi bi-person-plus-fill me-1" aria-hidden="true"></i>Dar de alta
            </a>
        </div>
    </div>

    <div class="card-body p-4 bg-white">

        <!-- Búsqueda y filtros -->
        <form method="get" action="<?= base_url('admin/usuarios') ?>" class="row g-2 mb-3 align-items-end" role="search" aria-label="Buscar personal">
            <div class="col-12 col-lg-5">
                <label for="q" class="form-label small fw-semibold mb-1">Buscar</label>
                <div class="input-group">
                    <span class="input-group-text bg-light"><i class="bi bi-search" aria-hidden="true"></i></span>
                    <input type="search" id="q" name="q" class="form-control" maxlength="100"
                           placeholder="Nombre, apellido, DNI o matrícula" value="<?= esc($filtros['q']) ?>">
                </div>
            </div>
            <div class="col-6 col-lg-2">
                <label for="estado" class="form-label small fw-semibold mb-1">Estado</label>
                <select id="estado" name="estado" class="form-select">
                    <option value="ACTIVO" <?= $filtros['estado'] === 'ACTIVO' ? 'selected' : '' ?>>Activos</option>
                    <option value="PENDIENTE" <?= $filtros['estado'] === 'PENDIENTE' ? 'selected' : '' ?>>Pendientes de activación</option>
                    <option value="BAJA" <?= $filtros['estado'] === 'BAJA' ? 'selected' : '' ?>>Dados de baja</option>
                    <option value="TODOS" <?= $filtros['estado'] === 'TODOS' ? 'selected' : '' ?>>Todos</option>
                </select>
            </div>
            <div class="col-6 col-lg-3">
                <label for="especialidad" class="form-label small fw-semibold mb-1">Especialidad</label>
                <select id="especialidad" name="especialidad" class="form-select">
                    <option value="">Todas</option>
                    <?php foreach ($especialidades as $especialidad): ?>
                        <option value="<?= esc($especialidad) ?>" <?= $filtros['especialidad'] === $especialidad ? 'selected' : '' ?>><?= esc($especialidad) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-lg-2 d-flex gap-2">
                <button class="btn btn-outline-secondary flex-fill" type="submit"><i class="bi bi-funnel me-1" aria-hidden="true"></i>Filtrar</button>
                <?php if ($hayFiltros): ?>
                    <a href="<?= base_url('admin/usuarios') ?>" class="btn btn-outline-secondary" title="Limpiar filtros" aria-label="Limpiar filtros">
                        <i class="bi bi-x-lg" aria-hidden="true"></i>
                    </a>
                <?php endif; ?>
            </div>
        </form>

        <p class="small text-body-secondary mb-2" aria-live="polite">
            <?= $total === 1 ? '1 resultado' : esc((string) $total) . ' resultados' ?>
        </p>

        <?php if ($usuarios === []): ?>
            <!-- Estado vacío -->
            <div class="text-center py-5 border rounded-3">
                <i class="bi bi-inbox fs-1 text-body-secondary d-block mb-2" aria-hidden="true"></i>
                <?php if ($hayFiltros): ?>
                    <p class="fw-semibold mb-1">No hay resultados para esa búsqueda.</p>
                    <p class="text-body-secondary small mb-3">Pruebe con otro nombre, DNI o quite los filtros.</p>
                    <a href="<?= base_url('admin/usuarios') ?>" class="btn btn-outline-secondary btn-sm">Quitar filtros</a>
                <?php else: ?>
                    <p class="fw-semibold mb-1">Todavía no hay enfermeros/as cargados.</p>
                    <p class="text-body-secondary small mb-3">Dé de alta al primer integrante del plantel.</p>
                    <a href="<?= base_url('admin/usuarios/crear') ?>" class="btn btn-tp btn-sm">Dar de alta enfermero/a</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <!-- Menos de 1200 px (celular, tablet y notebook chica con el menú lateral): una tarjeta por persona, con las acciones visibles -->
            <ul class="list-unstyled row row-cols-1 row-cols-md-2 g-2 d-xl-none mb-0">
                <?php foreach ($usuarios as $u): ?>
                    <li class="col"><div class="border rounded-3 p-3 h-100">
                        <div class="d-flex justify-content-between align-items-start flex-wrap gap-1">
                            <div class="fw-semibold"><?= esc($u->getNombreCompleto()) ?></div>
                            <?= view('admin/users/_estado', ['u' => $u]) ?>
                        </div>
                        <small class="d-block text-body-secondary text-break mb-2"><?= esc($u->email) ?></small>
                        <dl class="row small mb-2 g-0">
                            <dt class="col-5 fw-normal text-body-secondary">DNI</dt><dd class="col-7 mb-0"><?= esc($u->dni) ?></dd>
                            <dt class="col-5 fw-normal text-body-secondary">Matrícula</dt><dd class="col-7 mb-0"><?= esc($u->matricula ?: '-') ?></dd>
                            <dt class="col-5 fw-normal text-body-secondary">Especialidad</dt><dd class="col-7 mb-0"><?= esc($u->especialidad ?: '-') ?></dd>
                            <dt class="col-5 fw-normal text-body-secondary">Carga máx.</dt><dd class="col-7 mb-0"><?= $u->carga_horaria_max !== null ? esc((string) $u->carga_horaria_max) . ' hs' : '-' ?></dd>
                            <dt class="col-5 fw-normal text-body-secondary">Rol</dt><dd class="col-7 mb-0"><?= $u->esAdmin() ? 'Administrador' : 'Enfermero/a' ?></dd>
                        </dl>
                        <div class="d-flex flex-wrap gap-2">
                            <?= view('admin/users/_acciones', ['u' => $u, 'conTexto' => true]) ?>
                        </div>
                    </div></li>
                <?php endforeach; ?>
            </ul>

            <!-- 1200 px en adelante: tabla completa -->
            <div class="table-responsive d-none d-xl-block">
                <table class="table table-hover align-middle border mb-0">
                    <caption class="visually-hidden">Listado del personal de enfermería</caption>
                    <thead class="table-light">
                        <tr>
                            <!-- Columnas agrupadas para que entre junto al menú lateral (HU-06 pide todos estos datos) -->
                            <th scope="col">Nombre y apellido <span class="fw-normal text-body-secondary">· Rol</span></th>
                            <th scope="col" class="text-nowrap">DNI <span class="fw-normal text-body-secondary">· Matrícula</span></th>
                            <th scope="col">Especialidad</th>
                            <th scope="col" class="text-center text-nowrap">Carga máx.</th>
                            <th scope="col">Estado</th>
                            <th scope="col" class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($usuarios as $u): ?>
                        <tr>
                            <td>
                                <div class="fw-semibold"><?= esc($u->getNombreCompleto()) ?></div>
                                <small class="text-body-secondary d-block"><?= esc($u->email) ?></small>
                                <small class="text-body-secondary"><?= $u->esAdmin() ? 'Administrador' : 'Enfermero/a' ?></small>
                            </td>
                            <td class="text-nowrap">
                                <div><?= esc($u->dni) ?></div>
                                <small class="text-body-secondary"><?= esc($u->matricula ?: 'Sin matrícula') ?></small>
                            </td>
                            <td><span class="small"><?= esc($u->especialidad ?: '-') ?></span></td>
                            <td class="text-center text-nowrap"><?= $u->carga_horaria_max !== null ? esc((string) $u->carga_horaria_max) . ' hs' : '-' ?></td>
                            <td><?= view('admin/users/_estado', ['u' => $u]) ?></td>
                            <td class="text-end text-nowrap"><?= view('admin/users/_acciones', ['u' => $u, 'conTexto' => false]) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <nav class="mt-3 d-flex justify-content-center" aria-label="Paginación del listado">
                <?= $pager !== null ? $pager->links('default', 'bootstrap_tp') : '' ?>
            </nav>
        <?php endif; ?>
    </div>
</div>

<!-- Modales de confirmación (fuera de la tabla para que el HTML sea válido) -->
<?php foreach ($usuarios as $u): ?>
    <?php if ($u->esEnfermero() && $u->estaActivo()): ?>
        <div class="modal fade" id="modalRestablecer<?= (int) $u->id ?>" tabindex="-1" aria-labelledby="tituloRestablecer<?= (int) $u->id ?>" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2 class="modal-title h6" id="tituloRestablecer<?= (int) $u->id ?>"><i class="bi bi-key me-1" aria-hidden="true"></i>Restablecer contraseña</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-2">¿Generar una contraseña temporal nueva para <strong><?= esc($u->getNombreCompleto()) ?></strong>?</p>
                        <p class="small text-body-secondary mb-0">Su contraseña actual dejará de funcionar. Se le mostrará la clave nueva una sola vez para que se la entregue, y deberá cambiarla en su próximo ingreso.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <form action="<?= base_url('admin/usuarios/' . $u->id . '/restablecer') ?>" method="post" class="d-inline" data-enviando="Restableciendo...">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-tp">Restablecer</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="modalBaja<?= (int) $u->id ?>" tabindex="-1" aria-labelledby="tituloBaja<?= (int) $u->id ?>" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2 class="modal-title h6" id="tituloBaja<?= (int) $u->id ?>"><i class="bi bi-person-x me-1" aria-hidden="true"></i>Dar de baja</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-2">¿Dar de baja a <strong><?= esc($u->getNombreCompleto()) ?></strong> (matrícula <?= esc($u->matricula ?: '-') ?>)?</p>
                        <p class="small text-body-secondary mb-0">No podrá ingresar ni ser asignado/a a turnos. Su historial se conserva y puede reactivarlo/a cuando quiera.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <form action="<?= base_url('admin/usuarios/baja/' . $u->id) ?>" method="post" class="d-inline" data-enviando="Dando de baja...">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-danger">Dar de baja</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
<?php endforeach; ?>
<?= $this->endSection() ?>
