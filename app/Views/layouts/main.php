<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($titulo ?? 'TurnoPlaner') ?> - TurnoPlaner</title>
    <link rel="stylesheet" href="<?= base_url('css/app.css') ?>">
</head>
<body class="<?= esc($bodyClass ?? '') ?>">

    <?php if (session()->get('logueado')): ?>
    <header class="barra">
        <div class="barra__marca">
            <span class="barra__logo">TP</span>
            <strong>TurnoPlaner</strong>
        </div>
        <nav class="barra__nav">
            <a href="<?= base_url('inicio') ?>">Inicio</a>
            <!-- Aca se van a ir sumando los menus por rol (ABMC, planificador, mi cronograma...) -->
        </nav>
        <div class="barra__usuario">
            <span><?= esc(session()->get('usuario')) ?> (<?= esc(session()->get('rol')) ?>)</span>
            <a class="boton boton--chico" href="<?= base_url('logout') ?>">Cerrar sesion</a>
        </div>
    </header>
    <?php endif; ?>

    <main class="contenido">
        <?php if (session()->getFlashdata('ok')): ?>
            <div class="alerta alerta--ok"><?= esc(session()->getFlashdata('ok')) ?></div>
        <?php endif; ?>
        <?php if (session()->getFlashdata('error')): ?>
            <div class="alerta alerta--error"><?= esc(session()->getFlashdata('error')) ?></div>
        <?php endif; ?>

        <?= $this->renderSection('contenido') ?>
    </main>

    <footer class="pie">
        TurnoPlaner - Proyecto de Software 2026
    </footer>

    <script src="<?= base_url('js/app.js') ?>"></script>
</body>
</html>
