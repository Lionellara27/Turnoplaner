<?= $this->extend('layouts/main') ?>

<?= $this->section('contenido') ?>

<!-- Mensajes Flash de Éxito -->
<?php if (session()->getFlashdata('success') || session()->getFlashdata('ok')): ?>
  <div class="alert alert-success alert-dismissible fade show mb-4 border-0 shadow-sm" role="alert">
    <div class="d-flex align-items-center">
      <i class="bi bi-check-circle-fill me-2 fs-5"></i>
      <div><?= esc(session()->getFlashdata('success') ?? session()->getFlashdata('ok')) ?></div>
    </div>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
  </div>
<?php endif; ?>

<!-- Mensajes Flash de Error -->
<?php if (session()->getFlashdata('errors')): ?>
  <div class="alerta alerta--error mb-4" role="alert">
    <strong>Por favor, revise los siguientes errores:</strong>
    <ul class="mb-0 mt-2 ps-3 small">
      <?php foreach (session()->getFlashdata('errors') as $error): ?>
        <li><?= esc($error) ?></li>
      <?php endforeach ?>
    </ul>
  </div>
<?php endif; ?>

<!-- Tarjeta Principal del Formulario -->
<div class="card shadow-sm border-0 rounded-3 overflow-hidden">
  
  <!-- Encabezado con var(--color-primario) (#43ab92) y logo oficial -->
  <div class="card-header text-white py-3 px-4" style="background-color: var(--color-primario);">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
      <div class="d-flex align-items-center">
        <div class="me-3">
          <img src="<?= base_url('img/logo-64.png') ?>" alt="Logo TurnoPlaner" class="barra__logo">
        </div>
        <div>
          <h1 class="h4 mb-0 fw-semibold text-white">Alta de enfermero/a</h1>
          <p class="mb-0 small text-white-50">TurnoPlaner - Panel de Gestión de Personal</p>
        </div>
      </div>
      <div>
        <a href="<?= base_url('admin/usuarios') ?>" class="btn btn-sm btn-light text-nowrap fw-medium shadow-sm">
          <i class="bi bi-people me-1"></i> Listado de Personal
        </a>
      </div>
    </div>
  </div>

  <!-- Cuerpo del Formulario -->
  <div class="card-body p-4 p-md-5 bg-white">

    <!-- Aviso Informativo Institucional (HU-05) -->
    <div class="alert alert-info border-start border-4 border-info d-flex align-items-start mb-4 py-3" role="alert">
      <div class="small">
        <strong class="d-block mb-1 text-dark">Información Institucional del Alta</strong>
        <span>El legajo se registrará directamente en estado <strong>&laquo;Activo&raquo;</strong>. El sistema generará una contraseña temporal inicial y activará la bandera de cambio obligatorio en el primer acceso.</span>
      </div>
    </div>

    <!-- Formulario de Alta -->
    <form action="<?= base_url('admin/usuarios/crear') ?>" method="post" autocomplete="off">
      <?= csrf_field() ?>

      <!-- Distribución en 2 Columnas -->
      <div class="row g-3">
        
        <!-- Columna 1: Datos Personales e Identificación -->
        <div class="col-12 col-md-6">
          <div class="d-flex flex-column gap-3">
            <div class="form-floating">
              <input type="text" class="form-control" id="nombre" name="nombre" placeholder="Nombre completo" value="<?= old('nombre') ?>" required>
              <label for="nombre">Nombre *</label>
            </div>

            <div class="form-floating">
              <input type="text" class="form-control" id="apellido" name="apellido" placeholder="Apellido" value="<?= old('apellido') ?>" required>
              <label for="apellido">Apellido *</label>
            </div>

            <div class="form-floating">
              <input type="text" class="form-control" id="dni" name="dni" placeholder="DNI sin puntos" value="<?= old('dni') ?>" pattern="[0-9]{7,10}" required>
              <label for="dni">DNI / Documento (único) *</label>
            </div>

            <div class="form-floating">
              <input type="text" class="form-control" id="matricula" name="matricula" placeholder="Matrícula Nacional o Provincial" value="<?= old('matricula') ?>" required>
              <label for="matricula">Matrícula Profesional (única) *</label>
            </div>
          </div>
        </div>

        <!-- Columna 2: Contacto, Especialidad y Carga Horaria -->
        <div class="col-12 col-md-6">
          <div class="d-flex flex-column gap-3">
            <div class="form-floating">
              <input type="email" class="form-control" id="email" name="email" placeholder="nombre@hospital.gob.ar" value="<?= old('email') ?>" required>
              <label for="email">Correo Electrónico (único) *</label>
            </div>

            <div class="form-floating">
              <input type="tel" class="form-control" id="telefono" name="telefono" placeholder="Teléfono de contacto" value="<?= old('telefono') ?>">
              <label for="telefono">Teléfono de Contacto (opcional)</label>
            </div>

            <div class="form-floating">
              <select class="form-select" id="especialidad" name="especialidad" aria-label="Especialidad de Enfermería" required>
                <option value="" disabled <?= old('especialidad') ? '' : 'selected' ?>>Seleccione una especialidad *</option>
                <option value="Enfermería General" <?= old('especialidad') === 'Enfermería General' ? 'selected' : '' ?>>Enfermería General</option>
                <option value="Cuidados Críticos / Terapia Intensiva (UTI)" <?= old('especialidad') === 'Cuidados Críticos / Terapia Intensiva (UTI)' ? 'selected' : '' ?>>Cuidados Críticos / Terapia Intensiva (UTI)</option>
                <option value="Pediatría y Neonatología" <?= old('especialidad') === 'Pediatría y Neonatología' ? 'selected' : '' ?>>Pediatría y Neonatología</option>
                <option value="Guardia y Emergencias" <?= old('especialidad') === 'Guardia y Emergencias' ? 'selected' : '' ?>>Guardia y Emergencias</option>
                <option value="Quirófano / Centro Quirúrgico" <?= old('especialidad') === 'Quirófano / Centro Quirúrgico' ? 'selected' : '' ?>>Quirófano / Centro Quirúrgico</option>
                <option value="Salud Mental" <?= old('especialidad') === 'Salud Mental' ? 'selected' : '' ?>>Salud Mental</option>
                <option value="Oncología" <?= old('especialidad') === 'Oncología' ? 'selected' : '' ?>>Oncología</option>
              </select>
              <label for="especialidad">Especialidad Principal *</label>
            </div>

            <div class="form-floating">
              <input type="number" class="form-control" id="carga_horaria_max" name="carga_horaria_max" placeholder="Horas semanales" value="<?= old('carga_horaria_max', '40') ?>" min="1" max="80" required>
              <label for="carga_horaria_max">Carga Horaria Semanal Máxima (hs) *</label>
            </div>
          </div>
        </div>

        <!-- Fila Opcional: Contraseña Provisoria Personalizada -->
        <div class="col-12 mt-2">
          <hr class="text-secondary opacity-25 my-3">
          <div class="row g-3">
            <div class="col-12 col-md-6">
              <div class="form-floating">
                <input type="password" class="form-control" id="password" name="password" placeholder="Clave temporal" minlength="6">
                <label for="password">Contraseña inicial provisoria (opcional - autogenerada si se omite)</label>
              </div>
              <div class="form-text small text-muted">
                Si deja este campo vacío, el sistema generará una clave aleatoria segura automáticamente.
              </div>
            </div>
            <div class="col-12 col-md-6 d-flex align-items-center">
              <div class="card bg-light border-0 p-3 w-100 rounded-3">
                <div class="d-flex align-items-center text-muted small">
                  <i class="bi bi-shield-lock-fill text-secondary me-2 fs-5"></i>
                  <span>Al primer acceso con su clave provisoria, el enfermero/a deberá definir obligatoriamente su contraseña definitiva.</span>
                </div>
              </div>
            </div>
          </div>
        </div>

      </div>

      <!-- Acciones y Enlaces alineados a la paleta -->
      <div class="mt-4 pt-3 border-top d-flex flex-column flex-sm-row justify-content-between align-items-center gap-3">
        <a href="<?= base_url('admin/usuarios') ?>" class="text-decoration-none fw-medium small order-2 order-sm-1" style="color: var(--color-primario);">
          <i class="bi bi-arrow-left me-1"></i> Volver al listado de usuarios
        </a>
        <button type="submit" class="btn text-white px-4 py-2 fw-semibold shadow-sm order-1 order-sm-2" style="background-color: var(--color-primario); border-color: var(--color-primario);">
          <i class="bi bi-check2-circle me-1"></i> Registrar Enfermero/a
        </button>
      </div>

    </form>

  </div>

</div>

<?= $this->endSection() ?>
