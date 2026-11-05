<?php
/**
 * Alertas globales del sistema (DESIGN.md 4.3).
 *
 * El layout las incluye una sola vez arriba del contenido; las vistas NO repiten estos bloques.
 * Una vista angosta (por ejemplo, el login) puede mostrarlas dentro de su tarjeta:
 * el controlador pasa 'alertasEnVista' => true y la vista incluye este parcial donde quiera.
 *
 * Flashdata que reconoce:
 *   success / ok   -> confirmación (role="status")
 *   info           -> aviso neutro, por ejemplo "No había cambios"
 *   error          -> error general (role="alert")
 *   errors         -> errores de validación [campo => mensaje]: resumen con enlaces a cada campo
 *   credenciales   -> usuario y clave temporal para entregar al enfermero/a (se muestran una sola vez)
 */
$exito        = session()->getFlashdata('success') ?? session()->getFlashdata('ok');
$info         = session()->getFlashdata('info');
$error        = session()->getFlashdata('error');
$errores      = session()->getFlashdata('errors');
$credenciales = session()->getFlashdata('credenciales');
?>

<?php if ($exito): ?>
    <div class="alerta alerta--ok mb-4 d-flex align-items-start gap-2" role="status">
        <i class="bi bi-check-circle-fill fs-5" aria-hidden="true"></i>
        <div><?= esc($exito) ?></div>
    </div>
<?php endif; ?>

<?php if ($info): ?>
    <div class="alerta alerta--info mb-4 d-flex align-items-start gap-2" role="status">
        <i class="bi bi-info-circle-fill fs-5" aria-hidden="true"></i>
        <div><?= esc($info) ?></div>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alerta alerta--error mb-4 d-flex align-items-start gap-2" role="alert">
        <i class="bi bi-exclamation-triangle-fill fs-5" aria-hidden="true"></i>
        <div><?= esc($error) ?></div>
    </div>
<?php endif; ?>

<?php if (is_array($errores) && $errores !== []): ?>
    <div class="alerta alerta--error mb-4" role="alert" id="resumen-errores" tabindex="-1">
        <strong>
            <?= count($errores) === 1 ? 'Por favor, revise el campo marcado:' : 'Por favor, revise los ' . count($errores) . ' campos marcados:' ?>
        </strong>
        <ul class="mb-0 mt-2 ps-3 small">
            <?php foreach ($errores as $campo => $mensaje): ?>
                <li>
                    <?php if (is_string($campo)): ?>
                        <a href="#<?= esc($campo, 'attr') ?>"><?= esc($mensaje) ?></a>
                    <?php else: ?>
                        <?= esc($mensaje) ?>
                    <?php endif; ?>
                </li>
            <?php endforeach ?>
        </ul>
    </div>
<?php endif; ?>

<?php if (is_array($credenciales)): ?>
    <section class="credenciales p-3 p-md-4 mb-4" aria-labelledby="titulo-credenciales">
        <h2 id="titulo-credenciales" class="h6 fw-semibold mb-2">
            <i class="bi bi-key-fill me-1" aria-hidden="true"></i>Credenciales temporales de <?= esc($credenciales['nombre']) ?>
        </h2>
        <p class="small mb-3">Entrégueselas al enfermero/a. Por seguridad <strong>no se volverán a mostrar</strong>. En su primer ingreso deberá definir una contraseña propia.</p>
        <dl class="row mb-0 small">
            <dt class="col-sm-4">Usuario (email o DNI)</dt>
            <dd class="col-sm-8"><code><?= esc($credenciales['usuario']) ?></code> o <code><?= esc($credenciales['dni']) ?></code></dd>
            <dt class="col-sm-4">Contraseña temporal</dt>
            <dd class="col-sm-8 mb-0 d-flex align-items-center gap-2 flex-wrap">
                <code id="clave-temporal"><?= esc($credenciales['clave']) ?></code>
                <button type="button" class="btn btn-outline-secondary btn-sm" data-copiar="#clave-temporal" data-estado="#estado-copia">
                    <i class="bi bi-clipboard me-1" aria-hidden="true"></i>Copiar
                </button>
                <span id="estado-copia" class="small" aria-live="polite"></span>
            </dd>
        </dl>
    </section>
<?php endif; ?>
