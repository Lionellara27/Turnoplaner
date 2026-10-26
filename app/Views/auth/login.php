<?= $this->extend('layouts/main') ?>

<?= $this->section('contenido') ?>
<section class="login">
    <div class="login__caja">
        <div class="login__marca">
            <img class="login__logo" src="<?= base_url('img/logo.png') ?>" alt="Logo de TurnoPlaner" width="96" height="96">
            <h1>TurnoPlaner</h1>
            <p>Sistema de turnos rotativos de enfermeria</p>
        </div>

        <form id="form-login" method="post" action="<?= base_url('login') ?>" novalidate>
            <?= csrf_field() ?>

            <div class="campo">
                <label for="usuario">Usuario (DNI o email)</label>
                <input type="text" id="usuario" name="usuario" placeholder="Ej: 30123456"
                       autocomplete="username" autofocus>
                <small class="campo__ayuda">Por ahora podes dejarlo vacio: el login todavia no valida.</small>
            </div>

            <div class="campo">
                <label for="password">Contrasena</label>
                <input type="password" id="password" name="password" placeholder="********"
                       autocomplete="current-password">
            </div>

            <button type="submit" class="boton boton--principal boton--ancho">Ingresar</button>
        </form>

        <p class="login__pie">
            Sos enfermero/a y no tenes cuenta? <a href="#">Registrate</a> <small>(proximamente, HU-01)</small>
        </p>
    </div>
</section>
<?= $this->endSection() ?>
