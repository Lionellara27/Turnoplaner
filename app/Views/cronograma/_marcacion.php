<?php
/**
 * HU-20: registro de cumplimiento del turno de hoy (o del que sigue en curso desde ayer).
 *
 * Según el momento y lo ya marcado muestra:
 *  - "Marcar ingreso" (desde 30 min antes del inicio hasta el fin).
 *  - Antes de esa ventana, desde qué hora se habilita (en vez de un botón deshabilitado).
 *  - Con el ingreso marcado: "Ingreso registrado a las HH:MM" y "Finalizar guardia" con las
 *    observaciones del pase (opcional, máx. 1000 caracteres), hasta 4 h después del fin.
 *  - Con aviso de ausencia activo no se ofrece marcar (el estado del aviso ya se ve arriba).
 * Las reglas definitivas las controla el servidor (AsignacionModel::marcarIngreso / marcarSalida).
 *
 * @var \App\Entities\Asignacion           $t
 * @var string                             $ahora Y-m-d H:i:s
 * @var array<int, \App\Entities\Ausencia> $ausencias
 */
use App\Entities\Asignacion;

$aviso = ($ausencias ?? [])[(int) $t->id] ?? null;
if ($t->tieneAusenciaActiva() || ($aviso !== null && $aviso->esActiva())) {
    return; // No corresponde marcar: el turno tiene un aviso de ausencia en gestión o cubierto
}

$id        = (int) $t->id;
$hoy       = substr($ahora, 0, 10);
$apertura  = $t->aperturaIngreso();
$cierre    = $t->cierreSalida();
$campo     = 'observaciones_pase-' . $id;
$conError  = error_campo($campo) !== '';
$momento   = static fn (string $fechaHora): string => substr($fechaHora, 11, 5)
    . (substr($fechaHora, 0, 10) !== $hoy ? ' del ' . fecha_ar(substr($fechaHora, 0, 10)) : '');
$nombre    = esc((string) $t->tipo_nombre) . ' del ' . esc(fecha_larga((string) $t->fecha));
?>
<div class="tp-marcacion mt-2 p-3">
    <h4 class="h6 fw-semibold mb-2"><i class="bi bi-clipboard-check me-1" aria-hidden="true"></i>Registro de la guardia</h4>

    <?php if ($t->tieneSalida()): ?>
        <p class="mb-0 tp-marcacion__hecho"><i class="bi bi-check-circle-fill me-1" aria-hidden="true"></i>Ingreso a las <?= esc($t->horaIngreso()) ?> · Guardia finalizada a las <?= esc($t->horaSalida()) ?></p>
        <?php if ($t->observacionesPase() !== ''): ?>
            <p class="small text-body-secondary mb-0 mt-1">Dejó observaciones para el pase de guardia.</p>
        <?php endif; ?>

    <?php elseif ($t->tieneIngreso()): ?>
        <p class="mb-2 tp-marcacion__hecho"><i class="bi bi-check-circle-fill me-1" aria-hidden="true"></i>Ingreso registrado a las <?= esc($t->horaIngreso()) ?></p>

        <?php if ($t->puedeMarcarSalida($ahora)): ?>
            <form action="<?= base_url('mi-cronograma/' . $id . '/salida') ?>" method="post" class="needs-validation" novalidate data-enviando="Finalizando...">
                <?= csrf_field() ?>
                <div class="mb-2">
                    <label class="form-label small fw-semibold" for="<?= esc($campo, 'attr') ?>">Observaciones del pase de guardia (opcional)</label>
                    <textarea class="form-control tp-textarea<?= clase_invalido($campo) ?>" id="<?= esc($campo, 'attr') ?>" name="observaciones_pase" rows="3"
                              maxlength="<?= Asignacion::MAX_OBSERVACIONES_PASE ?>" aria-describedby="ayuda-<?= esc($campo, 'attr') ?> contador-<?= esc($campo, 'attr') ?>"
                              data-contador="#contador-<?= esc($campo, 'attr') ?>"
                              data-mensaje="Las observaciones no pueden superar los <?= Asignacion::MAX_OBSERVACIONES_PASE ?> caracteres."><?= $conError ? esc((string) old('observaciones_pase', '', false)) : '' ?></textarea>
                    <?= feedback_campo($campo) ?>
                    <div class="d-flex justify-content-between flex-wrap gap-2 form-text">
                        <span id="ayuda-<?= esc($campo, 'attr') ?>">Novedades para quien recibe la guardia: pacientes, pendientes, insumos.</span>
                        <span id="contador-<?= esc($campo, 'attr') ?>" class="tp-contador" data-max="<?= Asignacion::MAX_OBSERVACIONES_PASE ?>">Máximo <?= Asignacion::MAX_OBSERVACIONES_PASE ?> caracteres</span>
                    </div>
                </div>
                <button type="submit" class="btn btn-tp px-4 py-2 fw-semibold shadow-sm">
                    <i class="bi bi-box-arrow-right me-1" aria-hidden="true"></i>Finalizar guardia<span class="visually-hidden"> del turno <?= $nombre ?></span>
                </button>
                <p class="small text-body-secondary mb-0 mt-2">Puede finalizarla hasta las <?= esc($momento($cierre)) ?>.</p>
            </form>
        <?php else: ?>
            <p class="small text-body-secondary mb-0">No se registró el fin de la guardia: el plazo para finalizarla venció a las <?= esc($momento($cierre)) ?>.</p>
        <?php endif; ?>

    <?php elseif ($t->puedeMarcarIngreso($ahora)): ?>
        <form action="<?= base_url('mi-cronograma/' . $id . '/ingreso') ?>" method="post" class="m-0" data-enviando="Registrando...">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-tp px-4 py-2 fw-semibold shadow-sm">
                <i class="bi bi-box-arrow-in-right me-1" aria-hidden="true"></i>Marcar ingreso<span class="visually-hidden"> al turno <?= $nombre ?></span>
            </button>
        </form>
        <p class="small text-body-secondary mb-0 mt-2">Puede marcarlo hasta las <?= esc($momento((string) $t->fin)) ?>, fin del turno.</p>

    <?php elseif ($ahora < $apertura): ?>
        <p class="mb-0 small"><i class="bi bi-clock me-1" aria-hidden="true"></i>Podrá marcar el ingreso desde las <strong><?= esc($momento($apertura)) ?></strong> (<?= Asignacion::MINUTOS_ANTES_INGRESO ?> minutos antes del inicio).</p>

    <?php else: ?>
        <p class="mb-0 small text-body-secondary"><i class="bi bi-dash-circle me-1" aria-hidden="true"></i>No se registró el ingreso a este turno.</p>
    <?php endif; ?>
</div>
