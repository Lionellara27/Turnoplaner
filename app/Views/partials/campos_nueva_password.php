<?php
/**
 * Campos "nueva contraseña" + "confirmación" con requisitos visibles (HU-01 y HU-03).
 * Reglas del servidor: BaseController::reglasNuevaPassword().
 */
?>
<div class="row g-3">
    <div class="col-12 col-md-6">
        <div class="input-group has-validation">
            <div class="form-floating<?= clase_invalido('password') ?>">
                <input type="password" class="form-control<?= clase_invalido('password') ?>" id="password" name="password"
                       placeholder="Nueva contraseña" autocomplete="new-password" minlength="8" maxlength="72"
                       pattern="(?=.*[A-Za-z])(?=.*\d).{8,72}" required aria-describedby="requisitos-password" data-requisitos="#requisitos-password"
                       data-mensaje="La contraseña debe tener al menos 8 caracteres, con letras y números.">
                <label for="password">Nueva contraseña *</label>
            </div>
            <button class="btn btn-outline-secondary btn-ojo" type="button" data-mostrar-clave="#password" aria-label="Mostrar contraseña">
                <i class="bi bi-eye" aria-hidden="true"></i>
            </button>
            <?= feedback_campo('password') ?>
        </div>
        <ul id="requisitos-password" class="requisitos list-unstyled small mt-2 mb-0">
            <li data-regla="largo"><i class="bi bi-circle me-1" aria-hidden="true"></i>Al menos 8 caracteres</li>
            <li data-regla="letra"><i class="bi bi-circle me-1" aria-hidden="true"></i>Al menos una letra</li>
            <li data-regla="numero"><i class="bi bi-circle me-1" aria-hidden="true"></i>Al menos un número</li>
        </ul>
    </div>
    <div class="col-12 col-md-6">
        <div class="input-group has-validation">
            <div class="form-floating<?= clase_invalido('password_confirm') ?>">
                <input type="password" class="form-control<?= clase_invalido('password_confirm') ?>" id="password_confirm" name="password_confirm"
                       placeholder="Repetir contraseña" autocomplete="new-password" maxlength="72" required data-igual-a="#password" data-mensaje="Las contraseñas no coinciden.">
                <label for="password_confirm">Repetir nueva contraseña *</label>
            </div>
            <button class="btn btn-outline-secondary btn-ojo" type="button" data-mostrar-clave="#password_confirm" aria-label="Mostrar contraseña">
                <i class="bi bi-eye" aria-hidden="true"></i>
            </button>
            <?= feedback_campo('password_confirm') ?>
        </div>
    </div>
</div>
