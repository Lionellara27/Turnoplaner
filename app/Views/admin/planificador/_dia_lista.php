<?php
/**
 * Un día del mes en la lista del Planificador (debajo de 1400 px, donde no entra la tabla).
 *
 * @var string                                                    $dia   Y-m-d
 * @var string                                                    $hoy   Y-m-d
 * @var \App\Entities\Sector                                      $sector
 * @var list<\App\Entities\TipoTurno>                             $tipos
 * @var array<string, array<int, list<\App\Entities\Asignacion>>> $grilla
 * @var string                                                    $ahora
 * @var string                                                    $fechaMaxima
 * @var callable                                                  $turnoAplicaEnDia
 */
$esHoy    = $dia === $hoy;
$esPasado = $dia < $hoy;
$clase    = $esHoy ? ' dia-lista--hoy' : ($esPasado ? ' dia-lista--pasado' : '');
?>
<li class="col">
    <article class="dia-lista h-100<?= $clase ?>" aria-labelledby="titulo-dia-<?= esc($dia, 'attr') ?>"<?= $esHoy ? ' aria-current="date"' : '' ?>>
        <div class="dia-lista__cabecera">
            <h3 class="dia-lista__titulo" id="titulo-dia-<?= esc($dia, 'attr') ?>">
                <span class="text-capitalize"><?= esc(dia_semana($dia)) ?></span> <?= esc(fecha_ar($dia)) ?>
                <?php if ($esHoy): ?><span class="badge-hoy ms-1">Hoy</span><?php elseif ($esPasado): ?><span class="small fw-normal text-body-secondary ms-1">(pasado)</span><?php endif; ?>
            </h3>
            <button type="button" class="btn btn-outline-secondary" data-abrir-drawer="<?= esc($dia, 'attr') ?>">
                <i class="bi bi-people me-1" aria-hidden="true"></i>Ver día<span class="visually-hidden"> <?= esc(fecha_larga($dia)) ?>: personal asignado</span>
            </button>
        </div>
        <ul class="tp-celda-dia__turnos dia-lista__cuerpo">
            <?php foreach ($tipos as $t): ?>
                <?php
                $asignadosCelda = $grilla[$dia][(int) $t->id] ?? [];
                if (! $turnoAplicaEnDia($t, $dia, $asignadosCelda)) {
                    continue;
                }
                ?>
                <?= view('admin/planificador/_celda', [
                    'sector'      => $sector,
                    'tipo'        => $t,
                    'dia'         => $dia,
                    'asignados'   => $asignadosCelda,
                    'ahora'       => $ahora,
                    'fechaMaxima' => $fechaMaxima,
                    'conTexto'    => true,
                ]) ?>
            <?php endforeach; ?>
        </ul>
    </article>
</li>
