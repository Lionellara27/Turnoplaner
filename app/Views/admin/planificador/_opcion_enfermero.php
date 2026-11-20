<?php
/**
 * Una opción del selector de enfermeros/as: radio real (.btn-check) + label con forma de tarjeta.
 * El navegador resuelve foco, flechas, disabled, required y validación (funciona sin JavaScript).
 * app.js arma el mismo marcado cuando recibe la disponibilidad por AJAX (función opcionEnfermero).
 *
 * @var string $prefijo prefijo de los id ("form" o "modal")
 * @var array{id:int, nombre:string, especialidad:?string, matricula:?string, horas_texto:string,
 *            estado:string, motivo:string, checked?:bool, actual?:bool, bloqueado?:bool} $o
 *     estado: "disponible" | "extra" | "no_disponible" | "" (todavía no calculado)
 *     orden: posición en que la mandó el servidor (prioridad); minutos: carga de la semana (para ordenar)
 */
$id        = (int) $o['id'];
$idInput   = $prefijo . '-enf-' . $id;
$nombre    = (string) $o['nombre'];
$estado    = (string) ($o['estado'] ?? '');
$motivo    = trim((string) ($o['motivo'] ?? ''));
$actual    = ! empty($o['actual']);
// Si ya venía elegido (vuelta con errores), no se deshabilita: se conserva lo cargado y el servidor vuelve a validar.
$disabled  = ($estado === 'no_disponible' && empty($o['checked'])) || ! empty($o['bloqueado']);
$matricula = trim((string) ($o['matricula'] ?? ''));
$esp       = trim((string) ($o['especialidad'] ?? ''));
$partes    = preg_split('/[\s,]+/u', trim($nombre), -1, PREG_SPLIT_NO_EMPTY) ?: [''];
$iniciales = mb_strtoupper(mb_substr($partes[0], 0, 1) . mb_substr($partes[1] ?? '', 0, 1));
$grupos    = trim($estado . ($actual ? ' actual' : ''));
?>
<div class="tp-enfermero-item" data-filtro-item
     data-buscar="<?= esc(mb_strtolower($nombre . ' ' . $matricula), 'attr') ?>"
     data-especialidad="<?= esc($esp, 'attr') ?>"
     data-grupos="<?= esc($grupos, 'attr') ?>"
     data-orden-prioridad="<?= (int) ($o['orden'] ?? 0) ?>"
     data-orden-minutos="<?= (int) ($o['minutos'] ?? 0) ?>"
     data-orden-nombre="<?= esc($nombre, 'attr') ?>">
    <input type="radio" class="btn-check" name="usuario_id" id="<?= esc($idInput, 'attr') ?>" value="<?= $id ?>" required
           aria-labelledby="<?= esc($idInput, 'attr') ?>-nombre" aria-describedby="<?= esc($idInput, 'attr') ?>-datos <?= esc($idInput, 'attr') ?>-estado"
           <?= ! empty($o['checked']) ? 'checked' : '' ?> <?= $disabled ? 'disabled' : '' ?>>
    <label class="tp-enfermero-opcion" for="<?= esc($idInput, 'attr') ?>">
        <span class="tp-enfermero-opcion__principal">
            <span class="tp-avatar-circulo" aria-hidden="true"><?= esc($iniciales) ?></span>
            <span class="tp-enfermero-opcion__cuerpo">
                <span class="tp-enfermero-opcion__nombre" id="<?= esc($idInput, 'attr') ?>-nombre"><?= esc($nombre) ?></span>
                <span class="tp-enfermero-opcion__datos" id="<?= esc($idInput, 'attr') ?>-datos">
                    <?php if ($esp !== ''): ?><span class="badge tp-badge-especialidad"><?= esc($esp) ?></span><?php endif; ?>
                    <span class="tp-matricula"><?= $matricula !== '' ? 'Mat. ' . esc($matricula) : 'Sin matrícula' ?></span>
                    <span><?= esc((string) $o['horas_texto']) ?></span>
                </span>
            </span>
        </span>
        <span class="tp-enfermero-opcion__estado" id="<?= esc($idInput, 'attr') ?>-estado">
            <?php if ($actual): ?>
                <span class="estado-disponibilidad estado-disponibilidad--actual"><i class="bi bi-person-check" aria-hidden="true"></i>Asignado actualmente</span>
            <?php endif; ?>
            <?php if ($estado === 'disponible'): ?>
                <span class="estado-disponibilidad estado-disponibilidad--disponible"><i class="bi bi-check-circle" aria-hidden="true"></i>Disponible</span>
            <?php elseif ($estado === 'extra'): ?>
                <span class="estado-disponibilidad estado-disponibilidad--extra"><i class="bi bi-clock-history" aria-hidden="true"></i>Hora extra<?= $motivo !== '' ? ': ' . esc($motivo) : '' ?></span>
            <?php elseif ($estado === 'no_disponible'): ?>
                <span class="estado-disponibilidad estado-disponibilidad--no-disponible"><i class="bi bi-slash-circle" aria-hidden="true"></i>No disponible<?= $motivo !== '' ? ': ' . esc($motivo) : '' ?></span>
            <?php endif; ?>
            <span class="tp-check-circulo" aria-hidden="true"><i class="bi bi-check-lg"></i></span>
        </span>
    </label>
</div>
