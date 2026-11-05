<?php
/**
 * HU-01: activación de cuenta en el primer ingreso.
 *
 * @var \App\Entities\Usuario $usuario
 */
?>
<?= $this->extend('layouts/main') ?>

<?= $this->section('contenido') ?>
<div class="row justify-content-center">
    <div class="col-12 col-lg-9 col-xl-8">
        <div class="card shadow-sm border-0 rounded-3 overflow-hidden">

            <div class="card-header tp-card-header py-3 px-4">
                <div class="d-flex align-items-center">
                    <div class="me-3">
                        <img src="<?= base_url('img/logo-64.png') ?>" alt="Logo TurnoPlaner" class="barra__logo">
                    </div>
                    <div>
                        <h1 class="h4 mb-0 fw-semibold text-white">Activar mi cuenta</h1>
                        <p class="mb-0 small subtitulo">Paso obligatorio antes de usar el sistema</p>
                    </div>
                </div>
            </div>

            <div class="card-body p-4 p-md-5 bg-white">
                <p class="mb-4">
                    Hola, <strong><?= esc($usuario->nombre) ?></strong>. Ingresó con una contraseña temporal.
                    Defina su contraseña personal y complete sus datos de contacto para continuar.
                </p>

                <h2 class="h6 text-body-secondary fw-semibold mb-2">Datos cargados por la administración</h2>
                <div class="row g-2 mb-4">
                    <div class="col-6 col-md-4"><dl class="dato-lectura mb-0"><dt>Nombre</dt><dd><?= esc($usuario->getNombreCompleto()) ?></dd></dl></div>
                    <div class="col-6 col-md-4"><dl class="dato-lectura mb-0"><dt>DNI</dt><dd><?= esc($usuario->dni) ?></dd></dl></div>
                    <div class="col-6 col-md-4"><dl class="dato-lectura mb-0"><dt>Matrícula</dt><dd><?= esc($usuario->matricula ?: '-') ?></dd></dl></div>
                    <div class="col-6 col-md-8"><dl class="dato-lectura mb-0"><dt>Especialidad</dt><dd><?= esc($usuario->especialidad ?: '-') ?></dd></dl></div>
                    <div class="col-12 col-md-4"><dl class="dato-lectura mb-0"><dt>Carga horaria</dt><dd><?= esc((string) ($usuario->carga_horaria_max ?? '-')) ?> hs semanales</dd></dl></div>
                </div>
                <p class="form-text mt-n3 mb-4">
                    <i class="bi bi-lock me-1" aria-hidden="true"></i>Estos datos solo los puede modificar la administración. Si hay un error, avísele a su jefe/a.
                </p>

                <form action="<?= base_url('activar-cuenta') ?>" method="post" class="needs-validation" novalidate data-enviando="Activando...">
                    <?= csrf_field() ?>
                    <p class="small text-body-secondary">Los campos con * son obligatorios.</p>

                    <h2 class="h6 text-body-secondary fw-semibold mb-3">Datos de contacto</h2>
                    <div class="row g-3 mb-4">
                        <div class="col-12 col-md-5">
                            <div class="form-floating">
                                <input type="tel" class="form-control<?= clase_invalido('telefono') ?>" id="telefono" name="telefono"
                                       placeholder="Teléfono" value="<?= esc(old('telefono', $usuario->telefono ?? '', false)) ?>"
                                       autocomplete="tel" inputmode="tel" pattern="[0-9+\(\)\s\-]{6,30}" maxlength="30" required
                                       data-mensaje="Ingrese un teléfono válido: solo números, espacios, guiones, paréntesis o +."
                                       aria-describedby="ayuda-telefono">
                                <label for="telefono">Teléfono de contacto *</label>
                                <?= feedback_campo('telefono') ?>
                            </div>
                            <div id="ayuda-telefono" class="form-text">Con característica. Ej: 2920 412345</div>
                        </div>
                        <div class="col-12 col-md-7">
                            <div class="form-floating">
                                <input type="text" class="form-control<?= clase_invalido('domicilio') ?>" id="domicilio" name="domicilio"
                                       placeholder="Domicilio" value="<?= esc(old('domicilio', $usuario->domicilio ?? '', false)) ?>"
                                       autocomplete="street-address" minlength="5" maxlength="150" required
                                       data-mensaje="Ingrese su domicilio: calle, número y localidad."
                                       aria-describedby="ayuda-domicilio">
                                <label for="domicilio">Domicilio *</label>
                                <?= feedback_campo('domicilio') ?>
                            </div>
                            <div id="ayuda-domicilio" class="form-text">Calle, número y localidad. Ej: Mitre 123, Viedma</div>
                        </div>
                    </div>

                    <h2 class="h6 text-body-secondary fw-semibold mb-3">Contraseña personal</h2>
                    <?= $this->include('partials/campos_nueva_password') ?>

                    <div class="mt-4 pt-3 border-top d-flex flex-column flex-sm-row justify-content-end align-items-center gap-3">
                        <button type="submit" class="btn btn-tp px-4 py-2 fw-semibold shadow-sm">
                            <i class="bi bi-check2-circle me-1" aria-hidden="true"></i>Activar cuenta
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
