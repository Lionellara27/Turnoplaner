<?= $this->extend('layouts/main') ?>

<?= $this->section('contenido') ?>

<!-- Mensajes Flash de Error (Aprovecha las clases .alerta de tu app.css) -->
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
  
  <!-- Encabezado con var(--color-primario) de tu app.css (#43ab92) -->
  <div class="card-header text-white py-3 px-4" style="background-color: var(--color-primario);">
    <div class="d-flex align-items-center">
      <div class="me-3">
        <!-- Usamos class="barra__logo" para que mantenga los 34px x 34px exactos de la barra superior -->
        <img src="<?= base_url('img/logo-64.png') ?>" alt="Logo TurnoPlaner" class="barra__logo">
      </div>
      <div>
        <h1 class="h4 mb-0 fw-semibold text-white">Registro de enfermero</h1>
        <p class="mb-0 small text-white-50">TurnoPlaner - Gestión de Turnos Hospitalarios</p>
      </div>
    </div>
  </div>

  <!-- Cuerpo del Formulario -->
  <div class="card-body p-4 p-md-5 bg-white">

    <!-- Aviso Informativo -->
    <div class="alert alert-info border-start border-4 border-info d-flex align-items-start mb-4 py-3" role="alert">
      <div class="small">
        <strong class="d-block mb-1 text-dark">Información Importante de Validación</strong>
        <span>Tu cuenta quedará en estado <strong>&laquo;Pendiente de aprobación&raquo;</strong> hasta que un Administrador del centro de salud verifique tus credenciales y la habilite.</span>
      </div>
    </div>

    <!-- Formulario de Registro -->
    <form action="<?= base_url('registro/guardar') ?>" method="post" autocomplete="off">
      <?= csrf_field() ?>

      <!-- Distribución en 2 Columnas -->
      <div class="row g-3">
        
        <!-- Columna 1: Datos Personales e Identificación -->
        <div class="col-12 col-md-6">
          <div class="d-flex flex-column gap-3">
            <div class="form-floating">
              <input type="text" class="form-control" id="nombre" name="nombre" placeholder="Nombre completo" value="<?= old('nombre') ?>" required>
              <label for="nombre">Nombre</label>
            </div>

            <div class="form-floating">
              <input type="text" class="form-control" id="apellido" name="apellido" placeholder="Apellido" value="<?= old('apellido') ?>" required>
              <label for="apellido">Apellido</label>
            </div>

            <div class="form-floating">
              <input type="text" class="form-control" id="dni" name="dni" placeholder="DNI sin puntos" value="<?= old('dni') ?>" pattern="[0-9]{7,8}" required>
              <label for="dni">DNI / Documento</label>
            </div>

            <div class="form-floating">
              <input type="text" class="form-control" id="matricula" name="matricula" placeholder="Matrícula Nacional o Provincial" value="<?= old('matricula') ?>" required>
              <label for="matricula">Matrícula Profesional</label>
            </div>
          </div>
        </div>

        <!-- Columna 2: Contacto y Especialidad -->
        <div class="col-12 col-md-6">
          <div class="d-flex flex-column gap-3">
            <div class="form-floating">
              <input type="email" class="form-control" id="email" name="email" placeholder="nombre@hospital.gob.ar" value="<?= old('email') ?>" required>
              <label for="email">Correo Electrónico Institucional / Personal</label>
            </div>

            <div class="form-floating">
              <input type="tel" class="form-control" id="telefono" name="telefono" placeholder="Teléfono móvil" value="<?= old('telefono') ?>" required>
              <label for="telefono">Teléfono de Contacto</label>
            </div>

            <div class="form-floating">
              <select class="form-select" id="especialidad" name="especialidad" aria-label="Especialidad de Enfermería" required>
                <option value="" disabled <?= old('especialidad') ? '' : 'selected' ?>>Seleccione una especialidad</option>
                <option value="general" <?= old('especialidad') == 'general' ? 'selected' : '' ?>>Enfermería General</option>
                <option value="terapia_intensiva" <?= old('especialidad') == 'terapia_intensiva' ? 'selected' : '' ?>>Cuidados Críticos / Terapia Intensiva (UTI)</option>
                <option value="pediatria" <?= old('especialidad') == 'pediatria' ? 'selected' : '' ?>>Pediatría y Neonatología</option>
                <option value="guardia_emergencias" <?= old('especialidad') == 'guardia_emergencias' ? 'selected' : '' ?>>Guardia y Emergencias</option>
                <option value="quirofano" <?= old('especialidad') == 'quirofano' ? 'selected' : '' ?>>Quirófano / Centro Quirúrgico</option>
                <option value="salud_mental" <?= old('especialidad') == 'salud_mental' ? 'selected' : '' ?>>Salud Mental</option>
                <option value="oncologia" <?= old('especialidad') == 'oncologia' ? 'selected' : '' ?>>Oncología</option>
              </select>
              <label for="especialidad">Especialidad Principal</label>
            </div>

            <div class="form-floating">
              <select class="form-select" id="turno_preferente" name="turno_preferente" aria-label="Disponibilidad o Turno habitual">
                <option value="" selected>Cualquier turno / Rotativo</option>
                <option value="manana" <?= old('turno_preferente') == 'manana' ? 'selected' : '' ?>>Turno Mañana (06:00 - 14:00)</option>
                <option value="tarde" <?= old('turno_preferente') == 'tarde' ? 'selected' : '' ?>>Turno Tarde (14:00 - 22:00)</option>
                <option value="noche" <?= old('turno_preferente') == 'noche' ? 'selected' : '' ?>>Turno Noche (22:00 - 06:00)</option>
                <option value="franquero" <?= old('turno_preferente') == 'franquero' ? 'selected' : '' ?>>SADOFE / Franquero</option>
              </select>
              <label for="turno_preferente">Disponibilidad Preferente</label>
            </div>
          </div>
        </div>

        <!-- Fila Inferior: Contraseña y Confirmación -->
        <div class="col-12 mt-2">
          <hr class="text-secondary opacity-25 my-3">
          <div class="row g-3">
            <div class="col-12 col-md-6">
              <div class="form-floating">
                <input type="password" class="form-control" id="password" name="password" placeholder="Contraseña" minlength="8" required>
                <label for="password">Contraseña (mínimo 8 caracteres)</label>
              </div>
            </div>
            <div class="col-12 col-md-6">
              <div class="form-floating">
                <input type="password" class="form-control" id="pass_confirm" name="pass_confirm" placeholder="Confirmar Contraseña" minlength="8" required>
                <label for="pass_confirm">Confirmar Contraseña</label>
              </div>
            </div>
          </div>
        </div>

      </div>

      <!-- Acciones y Enlaces alineados a la paleta -->
      <div class="mt-4 pt-3 border-top d-flex flex-column flex-sm-row justify-content-between align-items-center gap-3">
        <a href="<?= base_url('login') ?>" class="text-decoration-none fw-medium small order-2 order-sm-1" style="color: var(--color-primario);">
          &iquest;Ya ten&eacute;s una cuenta? Iniciar Sesi&oacute;n
        </a>
        <button type="submit" class="btn text-white px-4 py-2 fw-semibold shadow-sm order-1 order-sm-2" style="background-color: var(--color-primario); border-color: var(--color-primario);">
          Completar Registro
        </button>
      </div>

    </form>

  </div>

</div>

<?= $this->endSection() ?>