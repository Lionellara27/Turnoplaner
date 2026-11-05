<?php
$logueado     = (bool) session()->get('logueado');
$rol          = (string) session()->get('rol');
$activando    = (int) session()->get('debe_cambiar_pass') === 1;
$conMenu      = $logueado && ! $activando;
$nombreSesion = trim(session()->get('nombre') . ' ' . session()->get('apellido'));
$rolVisible   = $rol === 'ADMIN' ? 'Administrador' : 'Enfermero/a';
$inicioRol    = base_url($rol === 'ADMIN' ? 'inicio' : 'mi-cronograma');

/*
 * Menú lateral por rol (DESIGN.md 4.6). Cada HU nueva agrega acá su ítem.
 * Formato: [ruta, ícono de Bootstrap Icons, texto].
 * "Mi perfil" y "Cerrar sesión" van fijos abajo del menú (ver más abajo).
 */
$menu = $rol === 'ADMIN'
    ? [
        'Gestión' => [
            ['inicio', 'bi-speedometer2', 'Panel'],
            ['admin/usuarios', 'bi-people', 'Personal'],
            ['admin/sectores', 'bi-hospital', 'Sectores'],
            ['admin/tipos-turno', 'bi-clock', 'Tipos de turno'],
            ['admin/planificador', 'bi-calendar-week', 'Planificador'],
        ],
    ]
    : [
        'Mis turnos' => [
            ['mi-cronograma', 'bi-calendar-week', 'Mi cronograma'],
        ],
    ];
$menuCuenta = [
    ['mi-perfil', 'bi-person-circle', 'Mi perfil'],
];

$esActual = static fn (string $ruta): bool => url_is($ruta) || url_is($ruta . '/*');

// Un ítem del menú lateral (ícono + texto; en el menú compacto solo queda el ícono).
$itemMenu = static function (array $item) use ($esActual): string {
    [$ruta, $icono, $texto] = $item;
    $actual = $esActual($ruta);

    return '<li class="nav-item"><a class="nav-link' . ($actual ? ' active' : '') . '" href="' . base_url($ruta) . '"'
        . ($actual ? ' aria-current="page"' : '') . ' title="' . esc($texto, 'attr') . '">'
        . '<i class="bi ' . $icono . '" aria-hidden="true"></i><span class="menu-lateral__texto">' . esc($texto) . '</span></a></li>';
};
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($titulo ?? 'TurnoPlaner') ?></title>
    <link rel="icon" type="image/png" href="<?= base_url('img/favicon.png') ?>">

    <!-- Bootstrap 5 y Bootstrap Icons (DESIGN.md: Bootstrap 5 en toda la interfaz). integrity = el navegador rechaza el archivo si fue alterado. -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
          integrity="sha384-XGjxtQfXaH2tnPFa9x+ruJTuLE3Aa6LhHSWRr1XeTyhezb4abCG4ccI5AkVDxqC+" crossorigin="anonymous">

    <!-- Estilos propios (paleta de DESIGN.md) -->
    <link rel="stylesheet" href="<?= base_url('css/app.css') ?>">

    <?php if ($conMenu): ?>
    <!-- Aplica la preferencia "menú compacto" antes de pintar, para que no parpadee -->
    <script>try { if (localStorage.getItem('tp-menu-compacto') === '1') { document.documentElement.classList.add('menu-compacto'); } } catch (e) {}</script>
    <?php endif; ?>
</head>
<body>

    <?php if ($logueado): ?>
    <!-- Barra superior: botón de menú (celular/tablet), marca y usuario -->
    <header class="navbar navbar-dark barra sticky-top">
        <div class="container-fluid gap-2 flex-nowrap">
            <?php if ($conMenu): ?>
                <button class="navbar-toggler d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#menuLateral"
                        aria-controls="menuLateral" aria-label="Abrir menú">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <a class="navbar-brand d-flex align-items-center gap-2 me-auto" href="<?= $inicioRol ?>">
            <?php else: ?>
                <span class="navbar-brand d-flex align-items-center gap-2 me-auto">
            <?php endif; ?>
                    <img class="barra__logo" src="<?= base_url('img/logo-64.png') ?>" alt="Logo TurnoPlaner" width="34" height="34">
                    <span>TurnoPlaner</span>
            <?= $conMenu ? '</a>' : '</span>' ?>

            <span class="barra__usuario d-none d-md-inline">
                <i class="bi bi-person-badge me-1" aria-hidden="true"></i><?= esc($nombreSesion) ?>
                <span class="badge bg-light text-dark ms-1"><?= $rolVisible ?></span>
            </span>

            <?php if (! $conMenu): ?>
                <!-- Sin menú (activación de cuenta, HU-01): cerrar sesión queda arriba porque es la única salida -->
                <form action="<?= base_url('logout') ?>" method="post" class="m-0" data-enviando="Saliendo...">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-outline-light btn-sm text-nowrap">
                        <i class="bi bi-box-arrow-right me-1" aria-hidden="true"></i>Cerrar sesión
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </header>
    <?php endif; ?>

    <div class="d-flex flex-grow-1">
        <?php if ($conMenu): ?>
        <!-- Menú lateral: fijo en computadora (lg en adelante); en celular y tablet se abre desde las tres rayitas -->
        <aside class="offcanvas-lg offcanvas-start menu-lateral" tabindex="-1" id="menuLateral" aria-labelledby="tituloMenuLateral">
            <div class="offcanvas-header border-bottom">
                <div>
                    <h2 class="offcanvas-title h6 mb-0" id="tituloMenuLateral"><?= esc($nombreSesion) ?></h2>
                    <span class="small text-body-secondary"><?= $rolVisible ?></span>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="offcanvas" data-bs-target="#menuLateral" aria-label="Cerrar menú"></button>
            </div>

            <div class="offcanvas-body menu-lateral__cuerpo">
                <!-- Arriba: secciones del rol -->
                <nav aria-label="Menú principal" class="flex-grow-1">
                    <?php foreach ($menu as $seccion => $items): ?>
                        <p class="menu-lateral__seccion"><?= esc($seccion) ?></p>
                        <ul class="nav flex-column mb-3">
                            <?php foreach ($items as $item): ?>
                                <?= $itemMenu($item) ?>
                            <?php endforeach; ?>
                        </ul>
                    <?php endforeach; ?>
                </nav>

                <!-- Abajo, fijo: Mi perfil y Cerrar sesión (la línea divisoria los separa del resto, sin título) -->
                <div class="menu-lateral__pie">
                    <ul class="nav flex-column mb-2" aria-label="Mi cuenta">
                        <?php foreach ($menuCuenta as $item): ?>
                            <?= $itemMenu($item) ?>
                        <?php endforeach; ?>
                    </ul>
                    <form action="<?= base_url('logout') ?>" method="post" class="m-0" data-enviando="Saliendo...">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-outline-danger w-100 menu-lateral__salir" title="Cerrar sesión">
                            <i class="bi bi-box-arrow-left" aria-hidden="true"></i>
                            <span class="menu-lateral__texto">Cerrar sesión</span>
                        </button>
                    </form>
                </div>
            </div>

            <!-- Solo en computadora: botón clavado sobre la línea del menú, a mitad de altura, para achicarlo a íconos -->
            <button type="button" class="menu-lateral__achicar d-none d-lg-flex" data-menu-compacto
                    aria-controls="menuLateral" aria-pressed="false" aria-label="Achicar menú" title="Achicar menú">
                <i class="bi bi-chevron-left" aria-hidden="true"></i>
            </button>
        </aside>
        <?php endif; ?>

        <div class="d-flex flex-column flex-grow-1 min-w-0">
            <main class="contenido container-xxl px-3 px-lg-4 py-4">
                <?php if (empty($alertasEnVista)): ?>
                    <?= $this->include('partials/alertas') ?>
                <?php endif; ?>

                <?= $this->renderSection('contenido') ?>
            </main>

            <footer class="pie">
                TurnoPlaner - Proyecto de Software 2026
            </footer>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
            integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script src="<?= base_url('js/app.js') ?>"></script>
</body>
</html>
