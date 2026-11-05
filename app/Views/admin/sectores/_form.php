<?php
/**
 * Campos del sector, compartidos por el alta (HU-09) y la edición (HU-10).
 *
 * @var \App\Entities\Sector|null $sector  null en el alta
 */
$sec = $sector ?? null;
// old() sin escapar (false): se escapa una sola vez con esc() al imprimir.
$val = static fn (string $campo, mixed $defecto = ''): string => (string) old($campo, $sec?->{$campo} ?? $defecto, false);
$critico = old('es_critico', null, false);
$critico = $critico !== null ? $critico === '1' : ($sec?->esCritico() ?? false);
?>
<p class="small text-body-secondary">Los campos con * son obligatorios.</p>

<div class="row g-3">
    <div class="col-12 col-md-7">
        <div class="form-floating">
            <input type="text" class="form-control<?= clase_invalido('nombre') ?>" id="nombre" name="nombre" placeholder="Nombre del sector"
                   value="<?= esc($val('nombre')) ?>" minlength="2" maxlength="80" required
                   aria-describedby="ayuda-nombre" data-mensaje="Ingrese el nombre del sector (al menos 2 caracteres).">
            <label for="nombre">Nombre del sector *</label>
            <?= feedback_campo('nombre') ?>
        </div>
        <div id="ayuda-nombre" class="form-text">Ej: UTI, Guardia, Neonatología, Internación general. No puede repetirse.</div>
    </div>

    <div class="col-12 col-md-5">
        <div class="form-floating">
            <input type="number" class="form-control<?= clase_invalido('dotacion_minima') ?>" id="dotacion_minima" name="dotacion_minima"
                   placeholder="Dotación mínima" value="<?= esc($val('dotacion_minima', '1')) ?>" min="1" max="50" step="1" required
                   aria-describedby="ayuda-dotacion" data-mensaje="Ingrese la dotación mínima: un número entero entre 1 y 50.">
            <label for="dotacion_minima">Dotación mínima por turno *</label>
            <?= feedback_campo('dotacion_minima') ?>
        </div>
        <div id="ayuda-dotacion" class="form-text">Cantidad mínima de enfermeros/as que tiene que haber en cualquier turno del sector (entre 1 y 50).</div>
    </div>

    <div class="col-12">
        <div class="form-floating">
            <textarea class="form-control<?= clase_invalido('descripcion') ?>" id="descripcion" name="descripcion" placeholder="Descripción"
                      maxlength="255" aria-describedby="ayuda-descripcion"
                      data-mensaje="La descripción no puede superar los 255 caracteres."><?= esc($val('descripcion')) ?></textarea>
            <label for="descripcion">Descripción (opcional)</label>
            <?= feedback_campo('descripcion') ?>
        </div>
        <div id="ayuda-descripcion" class="form-text">Breve detalle del servicio. Máximo 255 caracteres.</div>
    </div>

    <div class="col-12">
        <fieldset id="es_critico" tabindex="-1" aria-describedby="error-es_critico">
            <legend class="form-label fw-semibold small mb-1">¿Es un área crítica? *</legend>
            <div class="d-flex flex-wrap gap-3">
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="es_critico" id="es_critico_si" value="1" <?= $critico ? 'checked' : '' ?> required>
                    <label class="form-check-label" for="es_critico_si">Sí, área crítica (UTI, Guardia, Neonatología…)</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="es_critico" id="es_critico_no" value="0" <?= $critico ? '' : 'checked' ?>>
                    <label class="form-check-label" for="es_critico_no">No, área general</label>
                </div>
            </div>
            <div class="form-text">Las áreas críticas se muestran primero y se controlan con prioridad en el tablero de cobertura.</div>
            <?php if (error_campo('es_critico') !== ''): ?>
                <div class="invalid-feedback d-block" id="error-es_critico"><?= esc(error_campo('es_critico')) ?></div>
            <?php endif; ?>
        </fieldset>
    </div>
</div>
