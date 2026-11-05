<?= $this->extend('layouts/main') ?>

<?= $this->section('contenido') ?>
<div class="card shadow-sm border-0 rounded-3 overflow-hidden">

    <div class="card-header tp-card-header py-3 px-4">
        <div class="d-flex align-items-center">
            <div class="me-3">
                <img src="<?= base_url('img/logo-64.png') ?>" alt="Logo TurnoPlaner" class="barra__logo">
            </div>
            <div>
                <h1 class="h4 mb-0 fw-semibold text-white">Alta de enfermero/a</h1>
                <p class="mb-0 small subtitulo">TurnoPlaner - Gestión de Turnos Hospitalarios</p>
            </div>
        </div>
    </div>

    <div class="card-body p-4 p-md-5 bg-white">

        <div class="alerta alerta--info d-flex align-items-start gap-2 mb-4" role="note">
            <i class="bi bi-info-circle-fill fs-5" aria-hidden="true"></i>
            <div class="small">
                El legajo queda <strong>activo</strong> al guardarlo. El sistema le va a mostrar una
                <strong>contraseña temporal</strong> para entregarle al enfermero/a, que deberá cambiarla en su primer ingreso.
            </div>
        </div>

        <form action="<?= base_url('admin/usuarios/crear') ?>" method="post" class="needs-validation" novalidate autocomplete="off" data-enviando="Registrando...">
            <?= csrf_field() ?>

            <?= $this->include('admin/users/_form') ?>

            <!-- Contraseña temporal opcional -->
            <hr class="text-body-secondary opacity-25 my-4">
            <div class="row g-3">
                <div class="col-12 col-md-6">
                    <div class="input-group has-validation">
                        <div class="form-floating<?= clase_invalido('password') ?>">
                            <input type="password" class="form-control<?= clase_invalido('password') ?>" id="password" name="password"
                                   placeholder="Contraseña temporal" autocomplete="new-password" minlength="8" maxlength="72"
                                   aria-describedby="ayuda-password" data-mensaje="La contraseña temporal debe tener al menos 8 caracteres.">
                            <label for="password">Contraseña temporal (opcional)</label>
                        </div>
                        <button class="btn btn-outline-secondary btn-ojo" type="button" data-mostrar-clave="#password" aria-label="Mostrar contraseña">
                            <i class="bi bi-eye" aria-hidden="true"></i>
                        </button>
                        <?= feedback_campo('password') ?>
                    </div>
                    <div id="ayuda-password" class="form-text">Si la deja vacía, el sistema genera una clave segura automáticamente.</div>
                </div>
            </div>

            <div class="mt-4 pt-3 border-top d-flex flex-column flex-sm-row justify-content-between align-items-center gap-3">
                <a href="<?= base_url('admin/usuarios') ?>" class="btn btn-outline-secondary px-4 py-2 fw-medium order-2 order-sm-1">
                    Cancelar
                </a>
                <button type="submit" class="btn btn-tp px-4 py-2 fw-semibold shadow-sm order-1 order-sm-2">
                    <i class="bi bi-check2-circle me-1" aria-hidden="true"></i>Registrar enfermero/a
                </button>
            </div>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
