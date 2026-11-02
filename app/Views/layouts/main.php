<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($titulo ?? 'TurnoPlaner') ?></title>
    
    <!-- Icono fav.icon -->
    <link rel="icon" type="image/png" href="<?= base_url('img/favicon.png') ?>">
    
    <!-- 1. Bootstrap 5 CSS (Agregado para dar estilo a las Vistas de Stitch) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- 2. Bootstrap Icons (Agregado para los iconos de alertas e inputs) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <!-- 3. Tus estilos personalizados (Mantenido) -->
    <link rel="stylesheet" href="<?= base_url('css/app.css') ?>">
</head>
<body class="<?= esc($bodyClass ?? '') ?>">

    <?php if (session()->get('logueado')): ?>
    <header class="barra">
        <div class="barra__marca">
            <img class="barra__logo" src="<?= base_url('img/logo-64.png') ?>" alt="Logo de TurnoPlaner" width="34" height="34">
            <strong>TurnoPlaner</strong>
        </div>
        <nav class="barra__nav">
            <a href="<?= base_url('inicio') ?>">Inicio</a>
            <?php if (session()->get('rol') === 'ADMIN'): ?>
                <a href="<?= base_url('admin/usuarios') ?>">Personal</a>
            <?php endif; ?>
        </nav>
        <div class="barra__usuario">
            <span><?= esc(session()->get('usuario')) ?> (<?= esc(session()->get('rol')) ?>)</span>
            <a class="boton boton--chico" href="<?= base_url('logout') ?>">Cerrar sesion</a>
        </div>
    </header>
    <?php endif; ?>

    <main class="contenido container my-4">
        <?php if (session()->getFlashdata('success') || session()->getFlashdata('ok')): ?>
            <div class="alert alert-success"><?= esc(session()->getFlashdata('success') ?? session()->getFlashdata('ok')) ?></div>
        <?php endif; ?>
        <?php if (session()->getFlashdata('error')): ?>
            <div class="alert alert-danger"><?= esc(session()->getFlashdata('error')) ?></div>
        <?php endif; ?>

        <?= $this->renderSection('contenido') ?>
    </main>

    <footer class="pie">
        TurnoPlaner - Proyecto de Software 2026
    </footer>

    <!-- Bootstrap 5 JS Bundle (Necesario para modales, dropdowns y alertas) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= base_url('js/app.js') ?>"></script>
</body>
</html>
