<?= $this->extend('layouts/main') ?>

<?= $this->section('contenido') ?>
<section class="login d-flex align-items-center justify-content-center">
    <div class="card shadow-sm border-0 rounded-3 overflow-hidden w-100 login__caja">

        <div class="card-header tp-card-header text-center py-4 px-4">
            <img class="login__logo mb-2" src="<?= base_url('img/logo.png') ?>" alt="Logo TurnoPlaner" width="96" height="96">
            <h1 class="h4 mb-0 fw-semibold text-white">TurnoPlaner</h1>
            <p class="mb-0 small subtitulo">Turnos rotativos de enfermería</p>
        </div>

        <div class="card-body p-4">
            <!-- Alertas dentro de la tarjeta (el controlador pasa alertasEnVista = true) -->
            <?= $this->include('partials/alertas') ?>

            <form action="<?= base_url('login') ?>" method="post" class="needs-validation" novalidate data-enviando="Ingresando...">
                <?= csrf_field() ?>

                <div class="form-floating mb-3">
                    <input type="text" class="form-control<?= clase_invalido('identificador') ?>" id="identificador" name="identificador"
                           placeholder="Email o DNI" value="<?= esc(old('identificador', null, false)) ?>"
                           autocomplete="username" autocapitalize="none" spellcheck="false" maxlength="150" required autofocus data-mensaje="Ingrese su email o DNI."
                           aria-describedby="ayuda-identificador">
                    <label for="identificador">Email o DNI *</label>
                    <?= feedback_campo('identificador') ?>
                </div>
                <div id="ayuda-identificador" class="form-text mt-n2 mb-3">El DNI puede ir con o sin puntos. Ej: 30.123.456</div>

                <div class="input-group mb-4 has-validation">
                    <div class="form-floating<?= clase_invalido('password') ?>">
                        <input type="password" class="form-control<?= clase_invalido('password') ?>" id="password" name="password"
                               placeholder="Contraseña" autocomplete="current-password" maxlength="72" required data-mensaje="Ingrese su contraseña.">
                        <label for="password">Contraseña *</label>
                    </div>
                    <button class="btn btn-outline-secondary btn-ojo" type="button" data-mostrar-clave="#password" aria-label="Mostrar contraseña">
                        <i class="bi bi-eye" aria-hidden="true"></i>
                    </button>
                    <?= feedback_campo('password') ?>
                </div>

                <button type="submit" class="btn btn-tp w-100 py-2 fw-semibold shadow-sm">
                    <i class="bi bi-box-arrow-in-right me-1" aria-hidden="true"></i>Ingresar
                </button>
            </form>

            <p class="text-center small text-body-secondary mt-4 mb-0">
                Las cuentas las crea la administración del hospital.<br>
                Si no tiene usuario o no recuerda su contraseña, solicítelo a la coordinación.
            </p>
        </div>
    </div>
</section>
<?= $this->endSection() ?>
