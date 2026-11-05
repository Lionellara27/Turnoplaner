<?php
/**
 * Campos del legajo de enfermero/a, compartidos por el alta (HU-05) y la edición (HU-07).
 *
 * @var \App\Entities\Usuario|null $usuario        null en el alta
 * @var list<string>               $especialidades
 */
$u              = $usuario ?? null;
$especialidades = $especialidades ?? \App\Models\UsuarioModel::ESPECIALIDADES;
// old() sin escapar (false): se escapa una sola vez con esc() al imprimir.
$val            = static fn (string $campo, mixed $defecto = ''): string => (string) old($campo, $u?->{$campo} ?? $defecto, false);
?>
<p class="small text-body-secondary">Los campos con * son obligatorios.</p>

<div class="row g-3">
    <!-- Columna 1: identificación -->
    <div class="col-12 col-md-6">
        <div class="d-flex flex-column gap-3">
            <div class="form-floating">
                <input type="text" class="form-control<?= clase_invalido('nombre') ?>" id="nombre" name="nombre" placeholder="Nombre"
                       value="<?= esc($val('nombre')) ?>" autocomplete="given-name" minlength="2" maxlength="100" required
                       data-mensaje="Ingrese el nombre (al menos 2 letras).">
                <label for="nombre">Nombre *</label>
                <?= feedback_campo('nombre') ?>
            </div>

            <div class="form-floating">
                <input type="text" class="form-control<?= clase_invalido('apellido') ?>" id="apellido" name="apellido" placeholder="Apellido"
                       value="<?= esc($val('apellido')) ?>" autocomplete="family-name" minlength="2" maxlength="100" required
                       data-mensaje="Ingrese el apellido (al menos 2 letras).">
                <label for="apellido">Apellido *</label>
                <?= feedback_campo('apellido') ?>
            </div>

            <div>
                <div class="form-floating">
                    <input type="text" class="form-control<?= clase_invalido('dni') ?>" id="dni" name="dni" placeholder="DNI"
                           value="<?= esc($val('dni')) ?>" inputmode="numeric" pattern="[0-9]{7,10}" maxlength="10" required
                           aria-describedby="ayuda-dni" data-mensaje="El DNI debe tener entre 7 y 10 números, sin puntos.">
                    <label for="dni">DNI *</label>
                    <?= feedback_campo('dni') ?>
                </div>
                <div id="ayuda-dni" class="form-text">Solo números, sin puntos. Ej: 30123456</div>
            </div>

            <div>
                <div class="form-floating">
                    <input type="text" class="form-control<?= clase_invalido('matricula') ?>" id="matricula" name="matricula" placeholder="Matrícula"
                           value="<?= esc($val('matricula')) ?>" pattern="[A-Za-z0-9\s\-\/]{1,20}" maxlength="20" required
                           aria-describedby="ayuda-matricula" data-mensaje="Ingrese la matrícula: letras, números, guiones o barras.">
                    <label for="matricula">Matrícula profesional *</label>
                    <?= feedback_campo('matricula') ?>
                </div>
                <div id="ayuda-matricula" class="form-text">Ej: MP-1001</div>
            </div>
        </div>
    </div>

    <!-- Columna 2: contacto y contratación -->
    <div class="col-12 col-md-6">
        <div class="d-flex flex-column gap-3">
            <div>
                <div class="form-floating">
                    <input type="email" class="form-control<?= clase_invalido('email') ?>" id="email" name="email" placeholder="Correo electrónico"
                           value="<?= esc($val('email')) ?>" autocomplete="email" maxlength="150" required
                           aria-describedby="ayuda-email" data-mensaje="Ingrese un correo electrónico válido. Ej: nombre@hospital.gob.ar">
                    <label for="email">Correo electrónico *</label>
                    <?= feedback_campo('email') ?>
                </div>
                <div id="ayuda-email" class="form-text">Será su usuario para ingresar, junto con el DNI.</div>
            </div>

            <div>
                <div class="form-floating">
                    <input type="tel" class="form-control<?= clase_invalido('telefono') ?>" id="telefono" name="telefono" placeholder="Teléfono"
                           value="<?= esc($val('telefono')) ?>" inputmode="tel" pattern="[0-9+\(\)\s\-]{6,30}" maxlength="30" aria-describedby="ayuda-telefono"
                           data-mensaje="Ingrese un teléfono válido: al menos 6 números; puede usar espacios, guiones, paréntesis o +.">
                    <label for="telefono">Teléfono (opcional)</label>
                    <?= feedback_campo('telefono') ?>
                </div>
                <div id="ayuda-telefono" class="form-text">Con característica. Ej: 2920 412345</div>
            </div>

            <div class="form-floating">
                <select class="form-select<?= clase_invalido('especialidad') ?>" id="especialidad" name="especialidad" required
                        data-mensaje="Seleccione la especialidad.">
                    <option value="" <?= $val('especialidad') === '' ? 'selected' : '' ?> disabled>Seleccione una especialidad</option>
                    <?php foreach ($especialidades as $especialidad): ?>
                        <option value="<?= esc($especialidad) ?>" <?= $val('especialidad') === $especialidad ? 'selected' : '' ?>><?= esc($especialidad) ?></option>
                    <?php endforeach; ?>
                </select>
                <label for="especialidad">Especialidad *</label>
                <?= feedback_campo('especialidad') ?>
            </div>

            <div>
                <div class="form-floating">
                    <input type="number" class="form-control<?= clase_invalido('carga_horaria_max') ?>" id="carga_horaria_max" name="carga_horaria_max"
                           placeholder="Horas" value="<?= esc($val('carga_horaria_max', '40')) ?>" min="1" max="80" step="1" required
                           aria-describedby="ayuda-carga" data-mensaje="Ingrese las horas semanales: un número entero entre 1 y 80.">
                    <label for="carga_horaria_max">Carga horaria semanal máxima (hs) *</label>
                    <?= feedback_campo('carga_horaria_max') ?>
                </div>
                <div id="ayuda-carga" class="form-text">Horas semanales contratadas, entre 1 y 80. Ej: 36 o 40.</div>
            </div>
        </div>
    </div>
</div>
