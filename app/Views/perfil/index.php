<?php
/**
 * HU-03: Gestión de Mi Perfil.
 *
 * @var \App\Entities\Usuario $usuario
 */
?>
<?= $this->extend('layouts/main') ?>

<?= $this->section('contenido') ?>
<?php
// Para el enfermero/a, teléfono y domicilio son obligatorios (igual que en la activación, HU-01).
$obligatorio = $usuario->esEnfermero();
$inicio      = $usuario->esAdmin() ? 'inicio' : 'mi-cronograma';
?>
<div class="card shadow-sm border-0 rounded-3 overflow-hidden">

    <div class="card-header tp-card-header py-3 px-4">
        <div class="d-flex align-items-center">
            <div class="me-3">
                <img src="<?= base_url('img/logo-64.png') ?>" alt="Logo TurnoPlaner" class="barra__logo">
            </div>
            <div>
                <h1 class="h4 mb-0 fw-semibold text-white">Mi perfil</h1>
                <p class="mb-0 small subtitulo"><?= esc($usuario->getNombreCompleto()) ?> · <?= $usuario->esAdmin() ? 'Administrador' : 'Enfermero/a' ?></p>
            </div>
        </div>
    </div>

    <div class="card-body p-4 p-md-5 bg-white">

        <?php if ($usuario->esEnfermero()): ?>
            <h2 class="h6 text-body-secondary fw-semibold mb-2">Datos administrativos</h2>
            <div class="row g-2 mb-2">
                <div class="col-6 col-md-3"><dl class="dato-lectura mb-0"><dt>DNI</dt><dd><?= esc($usuario->dni) ?></dd></dl></div>
                <div class="col-6 col-md-3"><dl class="dato-lectura mb-0"><dt>Matrícula</dt><dd><?= esc($usuario->matricula ?: '-') ?></dd></dl></div>
                <div class="col-12 col-md-4"><dl class="dato-lectura mb-0"><dt>Especialidad</dt><dd><?= esc($usuario->especialidad ?: '-') ?></dd></dl></div>
                <div class="col-12 col-md-2"><dl class="dato-lectura mb-0"><dt>Carga horaria</dt><dd><?= esc((string) ($usuario->carga_horaria_max ?? '-')) ?> hs</dd></dl></div>
            </div>
            <p class="form-text mb-4">
                <i class="bi bi-lock me-1" aria-hidden="true"></i>Solo la administración puede modificar estos datos.
            </p>
        <?php else: ?>
            <div class="row g-2 mb-4">
                <div class="col-12 col-md-4"><dl class="dato-lectura mb-0"><dt>DNI</dt><dd><?= esc($usuario->dni) ?></dd></dl></div>
            </div>
        <?php endif; ?>

        <!-- Datos de contacto -->
        <section aria-labelledby="titulo-contacto" class="mb-5">
            <h2 id="titulo-contacto" class="h5 fw-semibold mb-3">Datos de contacto</h2>
            <form action="<?= base_url('mi-perfil/datos') ?>" method="post" class="needs-validation" novalidate data-enviando="Guardando...">
                <?= csrf_field() ?>
                <p class="small text-body-secondary">Los campos con * son obligatorios.</p>
                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <div class="form-floating">
                            <input type="email" class="form-control<?= clase_invalido('email') ?>" id="email" name="email"
                                   placeholder="Correo electrónico" value="<?= esc(old('email', $usuario->email, false)) ?>"
                                   autocomplete="email" maxlength="150" required aria-describedby="ayuda-email"
                                   data-mensaje="Ingrese un correo electrónico válido. Ej: nombre@hospital.gob.ar">
                            <label for="email">Correo electrónico *</label>
                            <?= feedback_campo('email') ?>
                        </div>
                        <div id="ayuda-email" class="form-text">También es su usuario para ingresar al sistema.</div>
                    </div>
                    <div class="col-12 col-md-6">
                        <div class="form-floating">
                            <input type="tel" class="form-control<?= clase_invalido('telefono') ?>" id="telefono" name="telefono"
                                   placeholder="Teléfono" value="<?= esc(old('telefono', $usuario->telefono ?? '', false)) ?>"
                                   autocomplete="tel" inputmode="tel" pattern="[0-9+\(\)\s\-]{6,30}" maxlength="30"
                                   aria-describedby="ayuda-telefono"<?= $obligatorio ? ' required' : '' ?>
                                   data-mensaje="Ingrese un teléfono válido: al menos 6 números; puede usar espacios, guiones, paréntesis o +.">
                            <label for="telefono">Teléfono<?= $obligatorio ? ' *' : '' ?></label>
                            <?= feedback_campo('telefono') ?>
                        </div>
                        <div id="ayuda-telefono" class="form-text">Con característica. Ej: 2920 412345</div>
                    </div>
                    <div class="col-12">
                        <div class="form-floating">
                            <input type="text" class="form-control<?= clase_invalido('domicilio') ?>" id="domicilio" name="domicilio"
                                   placeholder="Domicilio" value="<?= esc(old('domicilio', $usuario->domicilio ?? '', false)) ?>"
                                   autocomplete="street-address" minlength="5" maxlength="150"<?= $obligatorio ? ' required' : '' ?>
                                   data-mensaje="Ingrese su domicilio: calle, número y localidad.">
                            <label for="domicilio">Domicilio<?= $obligatorio ? ' *' : '' ?></label>
                            <?= feedback_campo('domicilio') ?>
                        </div>
                    </div>
                </div>
                <div class="mt-3 d-flex flex-column flex-sm-row justify-content-between align-items-center gap-3">
                    <a href="<?= base_url($inicio) ?>" class="btn btn-outline-secondary px-4 py-2 fw-medium order-2 order-sm-1">Volver</a>
                    <button type="submit" class="btn btn-tp px-4 py-2 fw-semibold shadow-sm order-1 order-sm-2">
                        <i class="bi bi-check2-circle me-1" aria-hidden="true"></i>Guardar datos
                    </button>
                </div>
            </form>
        </section>

        <!-- Cambio de contraseña -->
        <section id="seguridad" aria-labelledby="titulo-seguridad" class="pt-4 border-top">
            <h2 id="titulo-seguridad" class="h5 fw-semibold mb-3">Cambiar contraseña</h2>
            <form action="<?= base_url('mi-perfil/password') ?>" method="post" class="needs-validation" novalidate data-enviando="Cambiando...">
                <?= csrf_field() ?>
                <div class="row g-3 mb-3">
                    <div class="col-12 col-md-6">
                        <div class="input-group has-validation">
                            <div class="form-floating<?= clase_invalido('password_actual') ?>">
                                <input type="password" class="form-control<?= clase_invalido('password_actual') ?>" id="password_actual" name="password_actual"
                                       placeholder="Contraseña actual" autocomplete="current-password" maxlength="72" required
                                       data-mensaje="Ingrese su contraseña actual.">
                                <label for="password_actual">Contraseña actual *</label>
                            </div>
                            <button class="btn btn-outline-secondary btn-ojo" type="button" data-mostrar-clave="#password_actual" aria-label="Mostrar contraseña">
                                <i class="bi bi-eye" aria-hidden="true"></i>
                            </button>
                            <?= feedback_campo('password_actual') ?>
                        </div>
                    </div>
                </div>
                <?= $this->include('partials/campos_nueva_password') ?>
                <div class="mt-3 d-flex flex-column flex-sm-row justify-content-end">
                    <button type="submit" class="btn btn-outline-secondary px-4 py-2 fw-medium">
                        <i class="bi bi-shield-lock me-1" aria-hidden="true"></i>Cambiar contraseña
                    </button>
                </div>
            </form>
        </section>
    </div>
</div>
<?= $this->endSection() ?>
