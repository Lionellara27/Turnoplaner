<?php
/**
 * Una franja (sector + día + tipo de turno) del Tablero de cobertura (HU-15).
 * Semáforo con ícono + texto ("Cubierto" / "Falta personal") y una frase completa para lectores de pantalla.
 * Una alerta vigente es un link: a "Asignar reemplazo" si hay una ausencia pendiente en la franja, si no al Planificador.
 * Las franjas terminadas o no vigentes se diferencian con fondo y texto (nunca con opacidad).
 *
 * @var \App\Entities\Sector   $sector
 * @var \App\Entities\TipoTurno $tipo
 * @var string                 $dia      Y-m-d
 * @var array{asignados:int, minimo:int, alerta:bool, aplica:bool, terminado:bool,
 *            ausencia_pendiente_id:?int, ausencias_pendientes:int, url:?string} $celda
 * @var bool                   $compacto true en la semana: sin horario; el texto de acción solo si hay reemplazo para asignar
 *                                       (si no, un ícono de link: el nombre accesible completo ya dice "Ir al Planificador")
 */
$compacto  = $compacto ?? false;
$asignados = (int) $celda['asignados'];
$minimo    = (int) $celda['minimo'];
$pendAus   = (int) $celda['ausencias_pendientes'];
$url       = $celda['url'] ?? null;
$conReemp  = $url !== null && $celda['ausencia_pendiente_id'] !== null;
$queFranja = $tipo->nombre . ' (' . str_replace(' → ', ' a ', $tipo->rangoTexto()) . ') en ' . $sector->nombre . ', ' . fecha_larga($dia);

if (! $celda['aplica']) {
    $clase  = 'tp-franja tp-franja--no-aplica';
    $frase  = $queFranja . ': el turno no estaba vigente ese día.';
} else {
    $clase = 'tp-franja' . ($celda['alerta'] ? ' tp-franja--alerta' : ' tp-franja--ok') . ($celda['terminado'] ? ' tp-franja--terminada' : '');
    $frase = $queFranja . ': '
        . ($celda['alerta']
            ? 'falta personal, ' . $asignados . ' de ' . $minimo . ' enfermeros mínimos'
            : 'cubierto, ' . $asignados . ' de ' . $minimo . ' enfermeros mínimos')
        . ($celda['terminado'] ? ', turno terminado' : '')
        . ($pendAus > 0 ? ', ' . ($pendAus === 1 ? '1 ausencia pendiente' : $pendAus . ' ausencias pendientes') : '')
        . '.'
        . ($url !== null ? ($conReemp ? ' Asignar reemplazo.' : ' Ir al Planificador.') : '');
}
$etiqueta = $url !== null ? 'a' : 'div';
?>
<li class="tp-franja__item">
    <<?= $etiqueta ?> class="<?= $clase ?><?= $url !== null ? ' tp-franja--link' : '' ?>" style="--color-turno: <?= esc($tipo->color, 'attr') ?>;"<?= $url !== null ? ' href="' . $url . '"' : '' ?>>
        <span class="visually-hidden"><?= esc($frase) ?></span>
        <span class="tp-franja__cabecera" aria-hidden="true">
            <span class="tp-franja__nombre"><?= esc($tipo->nombre) ?></span>
            <?php if (! $compacto): ?><span class="tp-franja__horario"><?= esc($tipo->rangoTexto()) ?></span><?php endif; ?>
            <?php if ($compacto && $url !== null && ! $conReemp): ?><i class="bi bi-box-arrow-up-right tp-franja__ir" aria-hidden="true"></i><?php endif; ?>
        </span>
        <?php if (! $celda['aplica']): ?>
            <span class="tp-franja__nota" aria-hidden="true"><i class="bi bi-dash-circle" aria-hidden="true"></i>No vigente</span>
        <?php else: ?>
            <span class="tp-franja__estado" aria-hidden="true">
                <?php if ($celda['alerta']): ?>
                    <span class="cobertura cobertura--vacio"><i class="bi bi-exclamation-octagon-fill" aria-hidden="true"></i>Falta personal <?= $asignados ?>/<?= $minimo ?></span>
                <?php else: ?>
                    <span class="cobertura cobertura--ok"><i class="bi bi-check-circle-fill" aria-hidden="true"></i>Cubierto <?= $asignados ?>/<?= $minimo ?></span>
                <?php endif; ?>
            </span>
            <?php if ($celda['terminado']): ?>
                <span class="tp-franja__nota" aria-hidden="true"><i class="bi bi-lock" aria-hidden="true"></i>Terminado</span>
            <?php elseif ($pendAus > 0): ?>
                <span class="tp-franja__nota tp-franja__nota--ausencia" aria-hidden="true"><i class="bi bi-person-dash" aria-hidden="true"></i><?= $pendAus === 1 ? '1 ausencia pendiente' : $pendAus . ' ausencias pendientes' ?></span>
            <?php endif; ?>
            <?php if ($url !== null && (! $compacto || $conReemp)): ?>
                <span class="tp-franja__accion" aria-hidden="true"><?= $conReemp ? 'Asignar reemplazo' : 'Ir al Planificador' ?><i class="bi bi-arrow-right-short" aria-hidden="true"></i></span>
            <?php endif; ?>
        <?php endif; ?>
    </<?= $etiqueta ?>>
</li>
