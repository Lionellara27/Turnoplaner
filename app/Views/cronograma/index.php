<?= $this->extend('layouts/main') ?>

<?= $this->section('contenido') ?>
<div class="card shadow-sm border-0 rounded-3 overflow-hidden">

    <div class="card-header tp-card-header py-3 px-4">
        <div class="d-flex align-items-center">
            <div class="me-3">
                <img src="<?= base_url('img/logo-64.png') ?>" alt="Logo TurnoPlaner" class="barra__logo">
            </div>
            <div>
                <h1 class="h4 mb-0 fw-semibold text-white">Mi cronograma</h1>
                <p class="mb-0 small subtitulo">Turnos asignados del mes</p>
            </div>
        </div>
    </div>

    <div class="card-body p-4 p-md-5 bg-white text-center">
        <!-- Estado vacío (skill ux-ui-expert, sección 3). Los turnos llegan con HU-13 y HU-17. -->
        <i class="bi bi-calendar-x fs-1 text-body-secondary d-block mb-3" aria-hidden="true"></i>
        <h2 class="h5 fw-semibold">Todavía no tiene turnos asignados</h2>
        <p class="text-body-secondary mb-4">
            Cuando la jefatura publique la cuadrícula de turnos, los va a ver acá con su sector, día y horario.
        </p>
        <a href="<?= base_url('mi-perfil') ?>" class="btn btn-outline-secondary px-4 py-2 fw-medium">
            <i class="bi bi-person-circle me-1" aria-hidden="true"></i>Revisar mis datos
        </a>
    </div>
</div>
<?= $this->endSection() ?>
