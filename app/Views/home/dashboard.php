<?= $this->extend('layouts/main') ?>

<?= $this->section('contenido') ?>
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h1 class="h3 fw-semibold mb-1">Hola, <?= esc($nombre) ?></h1>
        <p class="text-body-secondary mb-0">Panel de gestión y planificación</p>
    </div>
    <a href="<?= base_url('admin/usuarios/crear') ?>" class="btn btn-tp px-4 py-2 fw-semibold shadow-sm">
        <i class="bi bi-person-plus me-1" aria-hidden="true"></i>Dar de alta enfermero/a
    </a>
</div>

<h2 class="visually-hidden">Resumen del plantel</h2>
<section class="row g-3 mb-4" aria-label="Resumen del plantel">
    <div class="col-12 col-md-4">
        <a href="<?= base_url('admin/usuarios') ?>" class="card shadow-sm border-0 tarjeta-indicador indicador--ok text-decoration-none h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <span class="indicador__icono" aria-hidden="true"><i class="bi bi-person-check"></i></span>
                <div>
                    <div class="numero text-dark"><?= esc((string) $activos) ?></div>
                    <div class="text-body-secondary">Enfermeros/as activos</div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-12 col-md-4">
        <a href="<?= base_url('admin/usuarios?estado=PENDIENTE') ?>" class="card shadow-sm border-0 tarjeta-indicador indicador--pendiente text-decoration-none h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <span class="indicador__icono" aria-hidden="true"><i class="bi bi-hourglass-split"></i></span>
                <div>
                    <div class="numero text-dark"><?= esc((string) $pendientes) ?></div>
                    <div class="text-body-secondary">Pendientes de activación</div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-12 col-md-4">
        <a href="<?= base_url('admin/usuarios?estado=BAJA') ?>" class="card shadow-sm border-0 tarjeta-indicador indicador--baja text-decoration-none h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <span class="indicador__icono" aria-hidden="true"><i class="bi bi-person-slash"></i></span>
                <div>
                    <div class="numero text-dark"><?= esc((string) $bajas) ?></div>
                    <div class="text-body-secondary">Dados de baja</div>
                </div>
            </div>
        </a>
    </div>
</section>

<h2 class="h5 fw-semibold mb-3">Módulos</h2>
<section class="row g-3" aria-label="Módulos del sistema">
    <div class="col-12 col-md-6 col-xl-3">
        <a href="<?= base_url('admin/usuarios') ?>" class="card shadow-sm border-0 h-100 text-decoration-none tarjeta-modulo">
            <div class="card-body">
                <h3 class="h6 fw-semibold text-dark"><i class="bi bi-people me-1" aria-hidden="true"></i>Personal</h3>
                <p class="small text-body-secondary mb-0">Alta, búsqueda, edición y credenciales del plantel.</p>
            </div>
        </a>
    </div>
    <div class="col-12 col-md-6 col-xl-3">
        <a href="<?= base_url('admin/sectores') ?>" class="card shadow-sm border-0 h-100 text-decoration-none tarjeta-modulo">
            <div class="card-body">
                <h3 class="h6 fw-semibold text-dark"><i class="bi bi-hospital me-1" aria-hidden="true"></i>Sectores</h3>
                <p class="small text-body-secondary mb-0">Servicios del hospital y su dotación mínima por turno.</p>
            </div>
        </a>
    </div>
    <div class="col-12 col-md-6 col-xl-3">
        <a href="<?= base_url('admin/tipos-turno') ?>" class="card shadow-sm border-0 h-100 text-decoration-none tarjeta-modulo">
            <div class="card-body">
                <h3 class="h6 fw-semibold text-dark"><i class="bi bi-clock me-1" aria-hidden="true"></i>Tipos de turno</h3>
                <p class="small text-body-secondary mb-0">Franjas horarias: mañana, tarde, noche y guardias.</p>
            </div>
        </a>
    </div>
    <div class="col-12 col-md-6 col-xl-3">
        <a href="<?= base_url('admin/planificador') ?>" class="card shadow-sm border-0 h-100 text-decoration-none tarjeta-modulo">
            <div class="card-body">
                <h3 class="h6 fw-semibold text-dark"><i class="bi bi-calendar-week me-1" aria-hidden="true"></i>Planificador</h3>
                <p class="small text-body-secondary mb-0">Cuadrícula semanal y asignación de turnos.</p>
            </div>
        </a>
    </div>
</section>
<?= $this->endSection() ?>
