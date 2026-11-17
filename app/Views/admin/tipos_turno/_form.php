<?php
/**
 * Campos del tipo de turno, compartidos por el alta (HU-11) y la edición (HU-12).
 *
 * @var \App\Entities\TipoTurno|null   $tipo     null en el alta
 * @var array<string, string>          $paleta   colores sugeridos [hex => nombre]
 * @var list<\App\Entities\Sector>     $sectores sectores activos disponibles
 */
$tt       = $tipo ?? null;
$paleta   = $paleta ?? \App\Models\TipoTurnoModel::PALETA;
$sectores = $sectores ?? (new \App\Models\SectorModel())->where('estado', 'ACTIVO')->orderBy('nombre', 'ASC')->findAll();
$sectorId = (int) old('sector_id', (string) ($tt?->sector_id ?? ''), false);
$inicio   = (string) old('hora_inicio', $tt?->inicio() ?? '', false);
$fin      = (string) old('hora_fin', $tt?->fin() ?? '', false);
$color    = (string) old('color', $tt?->color ?? array_key_first($paleta), false);
?>
<p class="small text-body-secondary">Los campos con * son obligatorios.</p>

<div class="row g-3">
    <div class="col-12">
        <div class="form-floating">
            <select class="form-select<?= clase_invalido('sector_id') ?>" id="sector_id" name="sector_id" required
                    aria-describedby="ayuda-sector" data-mensaje="Seleccione la zona de trabajo.">
                <option value="">Seleccione una zona...</option>
                <?php foreach ($sectores as $sec): ?>
                    <option value="<?= (int) $sec->id ?>" <?= $sectorId === (int) $sec->id ? 'selected' : '' ?>>
                        <?= esc($sec->nombre) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <label for="sector_id">Zona de trabajo (Sector) *</label>
            <?= feedback_campo('sector_id') ?>
        </div>
        <div id="ayuda-sector" class="form-text">El turno quedará disponible únicamente para planificar esta zona (ej. UTI, Guardia).</div>
    </div>

    <div class="col-12">
        <div class="form-floating">
            <input type="text" class="form-control<?= clase_invalido('nombre') ?>" id="nombre" name="nombre" placeholder="Nombre del turno"
                   value="<?= esc((string) old('nombre', $tt?->nombre ?? '', false)) ?>" minlength="3" maxlength="60" required
                   aria-describedby="ayuda-nombre" data-mensaje="Ingrese el nombre del turno (al menos 3 caracteres).">
            <label for="nombre">Nombre del turno *</label>
            <?= feedback_campo('nombre') ?>
        </div>
        <div id="ayuda-nombre" class="form-text">Ej: Mañana, Tarde, Noche, Guardia especial. No puede repetirse dentro de la misma zona.</div>
    </div>

    <div class="col-6">
        <div class="form-floating">
            <input type="time" class="form-control<?= clase_invalido('hora_inicio') ?>" id="hora_inicio" name="hora_inicio" placeholder="Inicio"
                   value="<?= esc($inicio) ?>" required step="60" data-duracion="inicio" aria-describedby="ayuda-horario"
                   data-mensaje="Ingrese la hora de inicio (formato 24 h).">
            <label for="hora_inicio">Hora de inicio *</label>
            <?= feedback_campo('hora_inicio') ?>
        </div>
    </div>
    <div class="col-6">
        <div class="form-floating">
            <input type="time" class="form-control<?= clase_invalido('hora_fin') ?>" id="hora_fin" name="hora_fin" placeholder="Fin"
                   value="<?= esc($fin) ?>" required step="60" data-duracion="fin" aria-describedby="ayuda-horario"
                   data-mensaje="Ingrese la hora de fin (formato 24 h).">
            <label for="hora_fin">Hora de fin *</label>
            <?= feedback_campo('hora_fin') ?>
        </div>
    </div>
    <div class="col-12">
        <div id="ayuda-horario" class="form-text">Formato 24 h. Si la hora de fin es menor que la de inicio, el turno termina al día siguiente (ej. Noche 22:00 a 06:00).</div>
        <div class="duracion-calculada mt-2" id="duracion-calculada" aria-live="polite">
            <i class="bi bi-hourglass-split me-1" aria-hidden="true"></i><span data-duracion-texto>Complete el horario para ver la duración.</span>
        </div>
    </div>

    <div class="col-12">
        <fieldset id="color" tabindex="-1" aria-describedby="ayuda-color error-color">
            <legend class="form-label fw-semibold small mb-1">Color de la etiqueta *</legend>
            <div class="form-text mt-0 mb-2" id="ayuda-color">Identifica el turno en el calendario. Siempre se muestra junto con el nombre.</div>
            <div class="d-flex flex-wrap gap-2">
                <?php foreach ($paleta as $hex => $nombreColor): ?>
                    <?php $idColor = 'color_' . ltrim($hex, '#'); ?>
                    <input type="radio" class="btn-check" name="color" id="<?= esc($idColor, 'attr') ?>" value="<?= esc($hex, 'attr') ?>"
                           <?= strtolower($color) === $hex ? 'checked' : '' ?> required>
                    <label class="btn opcion-color" for="<?= esc($idColor, 'attr') ?>"
                           style="--color-turno: <?= esc($hex, 'attr') ?>; --color-turno-texto: <?= esc(\App\Entities\TipoTurno::textoSobre($hex), 'attr') ?>;">
                        <?= esc($nombreColor) ?>
                    </label>
                <?php endforeach; ?>
            </div>
            <?php if (error_campo('color') !== ''): ?>
                <div class="invalid-feedback d-block" id="error-color"><?= esc(error_campo('color')) ?></div>
            <?php endif; ?>
        </fieldset>
    </div>
</div>