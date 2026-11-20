<?php
/**
 * Formulario para rechazar una ausencia pendiente (observación obligatoria, 5 a 255 caracteres).
 * Se usa dentro del modal del listado y, sin JavaScript, en la pantalla de la ausencia.
 *
 * @var \App\Entities\Ausencia $a
 * @var string                 $idCampo  id del textarea ("observacion" en la pantalla, "observacion-N" en el modal)
 * @var bool                   $conErrores true en la pantalla de la ausencia: muestra el error del servidor y lo cargado
 * @var bool                   $enModal  true dentro del modal (botones en el pie)
 */
$conErrores = $conErrores ?? false;
$enModal    = $enModal ?? false;
$min        = \App\Models\AusenciaModel::OBSERVACION_MIN;
$max        = \App\Models\AusenciaModel::OBSERVACION_MAX;
$invalido   = $conErrores ? clase_invalido('observacion') : '';
$valor      = $conErrores ? (string) old('observacion', '', false) : '';
$ayudaId    = 'ayuda-' . $idCampo;
$describe   = $ayudaId . ($invalido !== '' ? ' error-observacion' : '');
?>
<form action="<?= base_url('admin/ausencias/' . (int) $a->id . '/rechazar') ?>" method="post" class="needs-validation" novalidate data-enviando="Rechazando...">
    <?= csrf_field() ?>
    <div class="<?= $enModal ? 'modal-body' : '' ?>">
        <p class="mb-2">El turno vuelve a contar en la dotación y <strong><?= esc($a->nombreEnfermero()) ?></strong> deberá presentarse el <?= esc(fecha_larga((string) $a->fecha)) ?> (<?= esc($a->horarioTexto()) ?>).</p>
        <p class="small text-body-secondary">Los campos con * son obligatorios.</p>
        <div class="form-floating">
            <textarea class="form-control tp-textarea-observacion<?= $invalido ?>" id="<?= esc($idCampo, 'attr') ?>" name="observacion"
                      placeholder="Motivo del rechazo" required minlength="<?= $min ?>" maxlength="<?= $max ?>"
                      aria-describedby="<?= esc($describe, 'attr') ?>"
                      data-mensaje="Indique el motivo del rechazo (entre <?= $min ?> y <?= $max ?> caracteres)."><?= esc($valor) ?></textarea>
            <label for="<?= esc($idCampo, 'attr') ?>">Motivo del rechazo *</label>
            <?= $conErrores ? feedback_campo('observacion') : '' ?>
        </div>
        <div id="<?= esc($ayudaId, 'attr') ?>" class="form-text">Entre <?= $min ?> y <?= $max ?> caracteres. El enfermero/a verá este motivo en su cronograma. Ej: "No se presentó certificado; debe cubrir su guardia."</div>
    </div>
    <div class="<?= $enModal ? 'modal-footer' : 'mt-3 d-flex flex-column flex-sm-row justify-content-sm-end gap-2' ?>">
        <?php if ($enModal): ?>
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
        <?php endif; ?>
        <button type="submit" class="btn btn-danger"><i class="bi bi-x-circle me-1" aria-hidden="true"></i>Rechazar ausencia</button>
    </div>
</form>
