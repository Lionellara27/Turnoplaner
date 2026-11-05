<?= $this->extend('layouts/main') ?>

<?= $this->section('contenido') ?>
<div class="card shadow-sm border-0 rounded-3 overflow-hidden">
    <div class="card-header tp-card-header py-3 px-4">
        <div class="d-flex align-items-center">
            <div class="me-3"><img src="<?= base_url('img/logo-64.png') ?>" alt="Logo TurnoPlaner" class="barra__logo"></div>
            <div>
                <h1 class="h4 mb-0 fw-semibold text-white">Nuevo tipo de turno</h1>
                <p class="mb-0 small subtitulo">Franja horaria para planificar las guardias</p>
            </div>
        </div>
    </div>

    <div class="card-body p-4 p-md-5 bg-white">
        <form action="<?= base_url('admin/tipos-turno/crear') ?>" method="post" class="needs-validation" novalidate data-enviando="Guardando...">
            <?= csrf_field() ?>
            <?= $this->include('admin/tipos_turno/_form') ?>

            <div class="mt-4 pt-3 border-top d-flex flex-column flex-sm-row justify-content-between align-items-center gap-3">
                <a href="<?= base_url('admin/tipos-turno') ?>" class="btn btn-outline-secondary px-4 py-2 fw-medium order-2 order-sm-1">Cancelar</a>
                <button type="submit" class="btn btn-tp px-4 py-2 fw-semibold shadow-sm order-1 order-sm-2">
                    <i class="bi bi-check2-circle me-1" aria-hidden="true"></i>Crear tipo de turno
                </button>
            </div>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
