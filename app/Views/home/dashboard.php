<?= $this->extend('layouts/main') ?>

<?= $this->section('contenido') ?>
<h1>Hola, <?= esc($usuario) ?></h1>
<p class="texto-secundario">Entraste como <strong><?= esc($rol) ?></strong>. Esta es la pantalla principal del sistema.</p>

<section class="tarjetas">
    <article class="tarjeta">
        <h2>Personal</h2>
        <p>ABMC de enfermeros/as.</p>
        <span class="etiqueta">Sprint 1-2</span>
    </article>
    <article class="tarjeta">
        <h2>Sectores</h2>
        <p>Servicios del hospital y dotacion minima.</p>
        <span class="etiqueta">Sprint 2</span>
    </article>
    <article class="tarjeta">
        <h2>Tipos de turno</h2>
        <p>Manana, tarde, noche, guardia.</p>
        <span class="etiqueta">Sprint 2</span>
    </article>
    <article class="tarjeta">
        <h2>Planificador</h2>
        <p>Cuadricula semanal de turnos.</p>
        <span class="etiqueta">Sprint 3</span>
    </article>
</section>
<?= $this->endSection() ?>
