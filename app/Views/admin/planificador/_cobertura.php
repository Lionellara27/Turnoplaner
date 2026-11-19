<?php
/**
 * Estado de cobertura de un turno (DESIGN.md 4.8): ícono + texto visible + texto completo para lectores de pantalla.
 * Nunca solo color. Lo usan el Planificador (_celda.php) y el Panel (home/dashboard.php).
 * app.js arma el mismo marcado en el panel lateral y en el detalle del día (función coberturaChip).
 *
 * @var int $cubiertos enfermeros/as activos asignados
 * @var int $minimo    dotación mínima del sector
 */
$cubiertos = (int) $cubiertos;
$minimo    = (int) $minimo;

if ($cubiertos >= $minimo) {
    $clase   = 'cobertura--ok';
    $icono   = 'bi-check-circle-fill';
    $visible = "Cubierto {$cubiertos}/{$minimo}";
    $oculto  = "Cubierto: {$cubiertos} de {$minimo} enfermeros mínimos.";
} elseif ($cubiertos > 0) {
    $faltan  = $minimo - $cubiertos;
    $clase   = 'cobertura--parcial';
    $icono   = 'bi-exclamation-triangle-fill';
    $visible = "Faltan {$faltan} · {$cubiertos}/{$minimo}";
    $oculto  = ($faltan === 1 ? 'Falta 1 enfermero' : "Faltan {$faltan} enfermeros") . ": {$cubiertos} de {$minimo} asignados.";
} else {
    $clase   = 'cobertura--vacio';
    $icono   = 'bi-x-circle-fill';
    $visible = "Sin cubrir 0/{$minimo}";
    $oculto  = "Sin cubrir: 0 de {$minimo} enfermeros mínimos.";
}
?>
<span class="cobertura <?= $clase ?>"><i class="bi <?= $icono ?>" aria-hidden="true"></i><span aria-hidden="true"><?= esc($visible) ?></span><span class="visually-hidden"><?= esc($oculto) ?></span></span>
