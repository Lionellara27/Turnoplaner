<?= $this->extend('layouts/main') ?>

<?= $this->section('contenido') ?>

<!-- Mensajes Flash de Éxito y Error -->
<?php if (session()->getFlashdata('success') || session()->getFlashdata('ok')): ?>
  <div class="alert alert-success alert-dismissible fade show mb-4 border-0 shadow-sm" role="alert">
    <div class="d-flex align-items-center">
      <i class="bi bi-check-circle-fill me-2 fs-5"></i>
      <div><?= esc(session()->getFlashdata('success') ?? session()->getFlashdata('ok')) ?></div>
    </div>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
  </div>
<?php endif; ?>

<?php if (session()->getFlashdata('error')): ?>
  <div class="alert alert-danger alert-dismissible fade show mb-4 border-0 shadow-sm" role="alert">
    <div class="d-flex align-items-center">
      <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
      <div><?= esc(session()->getFlashdata('error')) ?></div>
    </div>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
  </div>
<?php endif; ?>

<div class="card shadow-sm border-0 rounded-3 overflow-hidden">
  
  <!-- Encabezado con var(--color-primario) y logo oficial -->
  <div class="card-header text-white py-3 px-4" style="background-color: var(--color-primario);">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
      <div class="d-flex align-items-center">
        <div class="me-3">
          <img src="<?= base_url('img/logo-64.png') ?>" alt="Logo TurnoPlaner" class="barra__logo">
        </div>
        <div>
          <h1 class="h4 mb-0 fw-semibold text-white">Gestión de Personal de Enfermería</h1>
          <p class="mb-0 small text-white-50">Plantel activo y administración de legajos</p>
        </div>
      </div>
      <div>
        <a href="<?= base_url('admin/usuarios/crear') ?>" class="btn btn-light text-nowrap fw-semibold shadow-sm" style="color: var(--color-primario-oscuro, #307f6d);">
          <i class="bi bi-person-plus-fill me-1"></i> Dar de Alta Enfermero/a
        </a>
      </div>
    </div>
  </div>

  <div class="card-body p-4 bg-white">
    
    <!-- Filtros de búsqueda -->
    <form method="get" action="<?= base_url('admin/usuarios') ?>" class="row g-2 mb-4 align-items-center">
      <div class="col-12 col-md-8 col-lg-6">
        <div class="input-group">
          <span class="input-group-text bg-light border-end-0">
            <i class="bi bi-search text-muted"></i>
          </span>
          <input type="text" name="q" class="form-control border-start-0" placeholder="Buscar por apellido, nombre, DNI o matrícula..." value="<?= esc(service('request')->getGet('q')) ?>">
          <button class="btn btn-outline-secondary" type="submit">Buscar</button>
        </div>
      </div>
      <?php if (service('request')->getGet('q')): ?>
        <div class="col-auto">
          <a href="<?= base_url('admin/usuarios') ?>" class="btn btn-link text-decoration-none text-muted btn-sm">
            <i class="bi bi-x-circle me-1"></i> Limpiar filtro
          </a>
        </div>
      <?php endif; ?>
    </form>

    <!-- Tabla Responsiva de Personal -->
    <div class="table-responsive">
      <table class="table table-hover align-middle border">
        <thead class="table-light">
          <tr>
            <th scope="col" class="text-nowrap">Matrícula</th>
            <th scope="col">Profesional</th>
            <th scope="col">DNI</th>
            <th scope="col">Contacto</th>
            <th scope="col">Especialidad</th>
            <th scope="col" class="text-center">Carga Hs</th>
            <th scope="col" class="text-center">Estado</th>
            <th scope="col" class="text-center">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php if (! empty($usuarios)): ?>
            <?php foreach ($usuarios as $u): ?>
              <tr>
                <td class="fw-medium text-nowrap">
                  <span class="badge bg-light text-dark border">
                    <?= esc($u->matricula ?: 'S/D') ?>
                  </span>
                </td>
                <td>
                  <div class="fw-semibold text-dark"><?= esc($u->getNombreCompleto()) ?></div>
                  <small class="text-muted"><?= esc($u->rol) ?></small>
                </td>
                <td class="text-nowrap"><?= esc($u->dni) ?></td>
                <td>
                  <div><a href="mailto:<?= esc($u->email) ?>" class="text-decoration-none small text-secondary"><?= esc($u->email) ?></a></div>
                  <?php if (! empty($u->telefono)): ?>
                    <small class="text-muted"><i class="bi bi-telephone me-1"></i><?= esc($u->telefono) ?></small>
                  <?php endif; ?>
                </td>
                <td>
                  <span class="small"><?= esc($u->especialidad ?: 'General') ?></span>
                </td>
                <td class="text-center">
                  <span class="badge rounded-pill bg-info-subtle text-info-emphasis border border-info-subtle">
                    <?= esc((string) ($u->carga_horaria_max ?? '-')) ?> hs
                  </span>
                </td>
                <td class="text-center">
                  <?php if ($u->estado === 'ACTIVO'): ?>
                    <span class="badge bg-success-subtle text-success-emphasis border border-success-subtle">Activo</span>
                  <?php elseif ($u->estado === 'PENDIENTE'): ?>
                    <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle">Pendiente</span>
                  <?php else: ?>
                    <span class="badge bg-secondary-subtle text-secondary-emphasis border border-secondary-subtle"><?= esc($u->estado) ?></span>
                  <?php endif; ?>
                </td>
                <td class="text-center">
                  <?php if ($u->rol !== 'ADMIN'): ?>
                    <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#modalBaja<?= $u->id ?>" title="Dar de baja">
                      <i class="bi bi-person-x"></i>
                    </button>

                    <!-- Modal de Confirmación de Baja Lógica (Sin DELETE Físico) -->
                    <div class="modal fade" id="modalBaja<?= $u->id ?>" tabindex="-1" aria-labelledby="modalBajaLabel<?= $u->id ?>" aria-hidden="true">
                      <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                          <div class="modal-header bg-danger text-white">
                            <h5 class="modal-title h6" id="modalBajaLabel<?= $u->id ?>">
                              <i class="bi bi-exclamation-triangle-fill me-1"></i> Confirmar Baja de Personal
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                          </div>
                          <div class="modal-body text-start">
                            <p class="mb-2">¿Está seguro de que desea dar de baja al/la profesional <strong><?= esc($u->getNombreCompleto()) ?></strong> (Matrícula: <?= esc($u->matricula) ?>)?</p>
                            <div class="alert alert-warning py-2 mb-0 small">
                              <i class="bi bi-info-circle me-1"></i> Esta acción aplicará una <strong>baja lógica</strong>, conservando todo el historial de turnos y asignaciones previas.
                            </div>
                          </div>
                          <div class="modal-footer">
                            <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Cancelar</button>
                            <form action="<?= base_url('admin/usuarios/baja/' . $u->id) ?>" method="post" class="d-inline">
                              <?= csrf_field() ?>
                              <button type="submit" class="btn btn-danger btn-sm">Confirmar Baja</button>
                            </form>
                          </div>
                        </div>
                      </div>
                    </div>
                  <?php else: ?>
                    <span class="text-muted small"><i class="bi bi-shield-check" title="Usuario Administrador"></i></span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr>
              <td colspan="8" class="text-center py-4 text-muted">
                <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                No se encontraron enfermeros/as registrados con los criterios seleccionados.
              </td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

  </div>
</div>

<?= $this->endSection() ?>

