<?php
/**
 * HU-18: formulario de aviso de ausencia para un turno futuro propio.
 *
 * Resumen del turno (solo lectura), motivo (radios), descripción (10 a 500 caracteres, con contador) y
 * confirmación. Validación en el navegador (needs-validation) y siempre en el servidor.
 *
 * @var \App\Entities\Asignacion $turno
 * @var array<string, string>    $motivos valor => etiqueta (AusenciaModel::MOTIVOS)
 */
use App\Entities\TipoTurno;
use App\Models\AusenciaModel;

$motivoElegido = (string) old('motivo', '', false);
$errorMotivo   = error_campo('motivo');
$errorConfirmo = error_campo('confirmo');
$salida        = substr((string) $turno->fin, 0, 10);
$min           = AusenciaModel::DESCRIPCION_MIN;
$max           = AusenciaModel::DESCRIPCION_MAX;
$iconos        = ['ENFERMEDAD' => 'bi-thermometer-half', 'FUERZA_MAYOR' => 'bi-exclamation-octagon', 'LICENCIA_MEDICA' => 'bi-file-earmark-medical', 'OTRO' => 'bi-three-dots'];
?>
<?= $this->extend('layouts/main') ?>

<?= $this->section('contenido') ?>
<div class="card shadow-sm border-0 rounded-3 overflow-hidden">
    <div class="card-header tp-card-header py-3 px-4">
        <div class="d-flex align-items-center">
            <div class="me-3"><img src="<?= base_url('img/logo-64.png') ?>" alt="Logo TurnoPlaner" class="barra__logo"></div>
            <div>
                <h1 class="h4 mb-0 fw-semibold text-white">Informar ausencia</h1>
                <p class="mb-0 small subtitulo">La coordinación recibirá el aviso para gestionar su reemplazo</p>
            </div>
        </div>
    </div>

    <div class="card-body p-3 p-md-4 bg-white">
        <!-- Resumen del turno -->
        <section class="proximo-turno p-3 mb-4" aria-labelledby="titulo-turno">
            <h2 class="h6 fw-semibold mb-2" id="titulo-turno">Turno al que no podrá presentarse</h2>
            <div class="row g-2">
                <div class="col-12 col-sm-6"><dl class="dato-lectura mb-0"><dt>Fecha</dt><dd class="text-capitalize"><?= esc(fecha_larga((string) $turno->fecha)) ?></dd></dl></div>
                <div class="col-12 col-sm-6"><dl class="dato-lectura mb-0"><dt>Turno</dt><dd>
                    <span class="etiqueta-turno" style="--color-turno: <?= esc($turno->colorTipo(), 'attr') ?>; --color-turno-texto: <?= esc(TipoTurno::textoSobre($turno->colorTipo()), 'attr') ?>;"><?= esc((string) $turno->tipo_nombre) ?></span>
                </dd></dl></div>
                <div class="col-12 col-sm-6"><dl class="dato-lectura mb-0"><dt>Horario</dt><dd>
                    <?= esc($turno->horaInicio()) ?> a <?= esc($turno->horaFin()) ?><?= $turno->terminaAlDiaSiguiente() ? ' del ' . esc(fecha_larga($salida)) : '' ?>
                </dd></dl></div>
                <div class="col-12 col-sm-6"><dl class="dato-lectura mb-0"><dt>Sector</dt><dd>
                    <?= esc((string) $turno->sector_nombre) ?><?= (int) $turno->sector_critico === 1 ? ' (área crítica)' : '' ?>
                </dd></dl></div>
            </div>
        </section>

        <form action="<?= base_url('mis-ausencias/nueva/' . (int) $turno->id) ?>" method="post" class="needs-validation" novalidate data-enviando="Enviando aviso...">
            <?= csrf_field() ?>
            <p class="small text-body-secondary">Los campos con * son obligatorios.</p>

            <fieldset id="motivo" tabindex="-1" class="mb-3<?= $errorMotivo !== '' ? ' is-invalid' : '' ?>" data-mensaje="Elija el motivo de la ausencia."
                      <?= $errorMotivo !== '' ? 'aria-describedby="error-motivo"' : '' ?>>
                <legend class="form-label fw-semibold mb-2 fs-6">Motivo *</legend>
                <?php if ($errorMotivo !== ''): ?>
                    <div class="invalid-feedback d-block mt-0 mb-2" id="error-motivo"><?= esc($errorMotivo) ?></div>
                <?php endif; ?>
                <div class="row g-2">
                    <?php foreach ($motivos as $valor => $texto): ?>
                        <div class="col-12 col-sm-6">
                            <input type="radio" class="btn-check" name="motivo" id="motivo-<?= esc(strtolower($valor), 'attr') ?>" value="<?= esc($valor, 'attr') ?>"
                                   autocomplete="off" required <?= $motivoElegido === $valor ? 'checked' : '' ?>>
                            <label class="tp-opcion-motivo w-100" for="motivo-<?= esc(strtolower($valor), 'attr') ?>">
                                <i class="bi <?= $iconos[$valor] ?? 'bi-circle' ?>" aria-hidden="true"></i><?= esc($texto) ?>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
            </fieldset>

            <div class="mb-3">
                <div class="form-floating">
                    <textarea class="form-control tp-textarea<?= clase_invalido('descripcion') ?>" id="descripcion" name="descripcion" placeholder="Descripción"
                              minlength="<?= $min ?>" maxlength="<?= $max ?>" required
                              aria-describedby="ayuda-descripcion contador-descripcion" data-contador="#contador-descripcion"
                              data-mensaje="Describa el motivo con al menos <?= $min ?> caracteres (máximo <?= $max ?>)."><?= esc((string) old('descripcion', '', false)) ?></textarea>
                    <label for="descripcion">Descripción *</label>
                    <?= feedback_campo('descripcion') ?>
                </div>
                <div class="d-flex justify-content-between flex-wrap gap-2 form-text">
                    <span id="ayuda-descripcion">Explique brevemente la situación. Entre <?= $min ?> y <?= $max ?> caracteres.</span>
                    <span id="contador-descripcion" class="tp-contador" data-min="<?= $min ?>" data-max="<?= $max ?>">Máximo <?= $max ?> caracteres</span>
                </div>
            </div>

            <div class="mb-4">
                <div class="form-check tp-check-grande">
                    <input class="form-check-input<?= $errorConfirmo !== '' ? ' is-invalid' : '' ?>" type="checkbox" id="confirmo" name="confirmo" value="1" required
                           data-mensaje="Confirme que no podrá presentarse a este turno."
                           <?= old('confirmo') === '1' ? 'checked' : '' ?> <?= $errorConfirmo !== '' ? 'aria-describedby="error-confirmo"' : '' ?>>
                    <label class="form-check-label" for="confirmo">Confirmo que no podré presentarme a este turno *</label>
                    <?= feedback_campo('confirmo') ?>
                </div>
            </div>

            <div class="mt-4 pt-3 border-top d-flex flex-column flex-sm-row justify-content-between align-items-stretch align-items-sm-center gap-3">
                <a href="<?= base_url('mis-ausencias') ?>" class="btn btn-outline-secondary px-4 py-2 fw-medium order-2 order-sm-1">Cancelar</a>
                <button type="submit" class="btn btn-tp px-4 py-2 fw-semibold shadow-sm order-1 order-sm-2">
                    <i class="bi bi-send me-1" aria-hidden="true"></i>Enviar aviso
                </button>
            </div>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
