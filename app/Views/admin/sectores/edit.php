<?php
/**
 * HU-10: edición de sector.
 *
 * @var \App\Entities\Sector $sector
 */
?>
<?= $this->extend('layouts/main') ?>

<?= $this->section('contenido') ?>
<div class="card shadow-sm border-0 rounded-3 overflow-hidden">
    <div class="card-header tp-card-header py-3 px-4">
        <div class="d-flex align-items-center">
            <div class="me-3"><img src="<?= base_url('img/logo-64.png') ?>" alt="Logo TurnoPlaner" class="barra__logo"></div>
            <div>
                <h1 class="h4 mb-0 fw-semibold text-white">Editar sector</h1>
                <p class="mb-0 small subtitulo"><?= esc($sector->nombre) ?></p>
            </div>
        </div>
    </div>

    <div class="card-body p-4 p-md-5 bg-white">
        <?php if (! $sector->estaActivo()): ?>
            <div class="alerta alerta--aviso d-flex align-items-start gap-2 mb-4" role="note">
                <i class="bi bi-exclamation-circle-fill fs-5" aria-hidden="true"></i>
                <div class="small">Este sector está <strong>dado de baja</strong>. Puede corregir sus datos, pero no aparecerá para asignar turnos hasta que lo reactive desde el listado.</div>
            </div>
        <?php endif; ?>

        <form action="<?= base_url('admin/sectores/' . $sector->id . '/editar') ?>" method="post" class="needs-validation" novalidate data-enviando="Guardando...">
            <?= csrf_field() ?>
            <?= $this->include('admin/sectores/_form') ?>

            <div class="mt-4 pt-3 border-top d-flex flex-column flex-sm-row justify-content-between align-items-center gap-3">
                <a href="<?= base_url('admin/sectores') ?>" class="btn btn-outline-secondary px-4 py-2 fw-medium order-2 order-sm-1">Cancelar</a>
                <button type="submit" class="btn btn-tp px-4 py-2 fw-semibold shadow-sm order-1 order-sm-2">
                    <i class="bi bi-check2-circle me-1" aria-hidden="true"></i>Guardar cambios
                </button>
            </div>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
