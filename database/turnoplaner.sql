-- ============================================================
--  TurnoPlaner - Script de base de datos
--  Proyecto de Software 2026
--
--  Como ejecutarlo (XAMPP, desde la carpeta del proyecto):
--    C:\xampp\mysql\bin\mysql.exe -u root < database\turnoplaner.sql
--  o importarlo desde phpMyAdmin (http://localhost/phpmyadmin).
--
--  Se puede correr varias veces: borra y vuelve a crear las tablas.
--  Regla del equipo: cada HU que toque la BD actualiza ESTE archivo.
-- ============================================================

CREATE DATABASE IF NOT EXISTS turnoplaner
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE turnoplaner;

-- Asegura que los acentos se guarden bien al importar desde la consola de Windows
SET NAMES utf8mb4;

-- Permite recrear tablas que se referencian entre si (ausencias -> asignaciones -> usuarios, sectores, tipos_turno)
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS ausencias;
DROP TABLE IF EXISTS asignaciones;

-- ------------------------------------------------------------
--  TABLA: usuarios   (HU-01, HU-02, HU-03, HU-05 a HU-08)
--  Un solo tipo de usuario con columna "rol". El enfermero/a
--  guarda ademas sus datos profesionales.
--  NO hay registro publico: las cuentas las crea el ADMIN con una
--  contrasena temporal (debe_cambiar_pass = 1).
--  Baja logica: columna "estado", nunca DELETE.
-- ------------------------------------------------------------
DROP TABLE IF EXISTS usuarios;

CREATE TABLE usuarios (
    id                  INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    nombre              VARCHAR(100)     NOT NULL,
    apellido            VARCHAR(100)     NOT NULL,
    dni                 VARCHAR(10)      NOT NULL,
    matricula           VARCHAR(20)      NULL,           -- solo enfermeros/as
    email               VARCHAR(150)     NOT NULL,
    telefono            VARCHAR(30)      NULL,
    domicilio           VARCHAR(150)     NULL,           -- lo completa el enfermero/a en su primer ingreso (HU-01)
    password_hash       VARCHAR(255)     NOT NULL,       -- password_hash() de PHP
    rol                 ENUM('ADMIN', 'ENFERMERO') NOT NULL DEFAULT 'ENFERMERO',
    especialidad        VARCHAR(100)     NULL,           -- solo enfermeros/as
    carga_horaria_max   TINYINT UNSIGNED NULL,           -- horas semanales contratadas
    estado              ENUM('ACTIVO', 'BAJA') NOT NULL DEFAULT 'ACTIVO',
    debe_cambiar_pass   TINYINT(1)       NOT NULL DEFAULT 1,  -- 1 = primer ingreso, debe cambiarla
    created_at          DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME         NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at          DATETIME         NULL DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_usuarios_dni (dni),
    UNIQUE KEY uq_usuarios_email (email),
    UNIQUE KEY uq_usuarios_matricula (matricula),
    KEY idx_usuarios_estado (estado),
    KEY idx_usuarios_rol (rol)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
--  DATOS DE PRUEBA
--  Contrasenas (en texto plano, solo para desarrollo):
--    admin@turnoplaner.local      -> admin123
--    enfermera@turnoplaner.local  -> enfermero123
--    nuevo@turnoplaner.local      -> enfermero123  (recien creado, debe cambiarla)
-- ------------------------------------------------------------
INSERT INTO usuarios
    (nombre, apellido, dni, matricula, email, telefono, domicilio, password_hash, rol, especialidad, carga_horaria_max, estado, debe_cambiar_pass)
VALUES
    ('Admin', 'Sistema', '20000000', NULL, 'admin@turnoplaner.local', '2920000000', NULL,
     '$2y$10$anNSqYPlv1W3q8HSe1E1fORfpZ9wIby4dgNulp1yP6OWEtHsnAa0C', 'ADMIN', NULL, NULL, 'ACTIVO', 0),

    ('Maria', 'Gomez', '30123456', 'MP-1001', 'enfermera@turnoplaner.local', '2920111111', 'Mitre 123, Viedma',
     '$2y$10$jXySqRJ1ur32wT/fMe1rP.vxWLoCXxMVtVMELnZYgiuE9PLDbof5u', 'ENFERMERO', 'Cuidados Críticos / Terapia Intensiva (UTI)', 36, 'ACTIVO', 0),

    ('Juan', 'Perez', '31234567', 'MP-1002', 'nuevo@turnoplaner.local', NULL, NULL,
     '$2y$10$jXySqRJ1ur32wT/fMe1rP.vxWLoCXxMVtVMELnZYgiuE9PLDbof5u', 'ENFERMERO', 'Guardia y Emergencias', 40, 'ACTIVO', 1);

-- ------------------------------------------------------------
--  TABLA: sectores   (HU-09 y HU-10)
--  Servicios del hospital. dotacion_minima = cantidad minima de
--  enfermeros/as requerida en cualquier turno del sector.
-- ------------------------------------------------------------
DROP TABLE IF EXISTS sectores;

CREATE TABLE sectores (
    id                  INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    nombre              VARCHAR(80)       NOT NULL,
    descripcion         VARCHAR(255)      NULL,
    es_critico          TINYINT(1)        NOT NULL DEFAULT 0,  -- 1 = area critica (UTI, Guardia, Neonatologia)
    dotacion_minima     TINYINT UNSIGNED  NOT NULL DEFAULT 1,
    estado              ENUM('ACTIVO', 'BAJA') NOT NULL DEFAULT 'ACTIVO',
    created_at          DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME          NULL DEFAULT NULL,
    deleted_at          DATETIME          NULL DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sectores_nombre (nombre),
    KEY idx_sectores_estado (estado)
) ENGINE=InnoDB;

INSERT INTO sectores (id, nombre, descripcion, es_critico, dotacion_minima) VALUES
    (1, 'Internación general', 'Sala común de clínica médica y quirúrgica (Enfermería General).', 0, 3),
    (2, 'Guardia',             'Guardia general y emergencias 24 hs.',                            1, 3),
    (3, 'UTI',                 'Unidad de Terapia Intensiva de adultos.',                         1, 3),
    (4, 'Neonatología',        'Cuidados intensivos e intermedios neonatales.',                   1, 2),
    (5, 'Quirófano',           'Centro quirúrgico y recuperación postanestésica.',                1, 2),
    (6, 'Salud Mental',        'Internación y guardia interdisciplinaria de salud mental.',       0, 2),
    (7, 'Oncología',           'Hospital de día y cuidados oncológicos.',                         0, 2);
    
-- ------------------------------------------------------------
--  TABLA: tipos_turno   (HU-11 y HU-12)
--  Franjas horarias rotativas. La duracion la calcula el sistema
--  (un turno de 22:00 a 06:00 cruza la medianoche y dura 8 h).
--  Baja logica: estado + deleted_at, nunca DELETE.
-- ------------------------------------------------------------
DROP TABLE IF EXISTS tipos_turno;

CREATE TABLE tipos_turno (
    id                  INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    sector_id           INT UNSIGNED      NOT NULL, 
    nombre              VARCHAR(60)       NOT NULL,
    hora_inicio         TIME              NOT NULL,
    hora_fin            TIME              NOT NULL,
    duracion_minutos    SMALLINT UNSIGNED NOT NULL,
    color               CHAR(7)           NOT NULL DEFAULT '#307f6d',
    estado              ENUM('ACTIVO', 'BAJA') NOT NULL DEFAULT 'ACTIVO',
    created_at          DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME          NULL DEFAULT NULL,
    deleted_at          DATETIME          NULL DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_tipos_turno_nombre (sector_id, nombre),
    KEY idx_tipos_turno_estado (estado),
    KEY inx_tipos_turno_sector (sector_id),
    CONSTRAINT fk_tipos_turno_sector FOREIGN KEY (sector_id) REFERENCES sectores (id)
) ENGINE=InnoDB;

INSERT INTO tipos_turno (sector_id, nombre, hora_inicio, hora_fin, duracion_minutos, color) VALUES
    -- 1. Internación general (8h)
    (1, 'Mañana',     '06:00:00', '14:00:00', 480, '#c98a00'),
    (1, 'Tarde',      '14:00:00', '22:00:00', 480, '#b34700'),
    (1, 'Noche',      '22:00:00', '06:00:00', 480, '#3d5a99'),
    -- 2. Guardia (8h)
    (2, 'Mañana',     '06:00:00', '14:00:00', 480, '#c98a00'),
    (2, 'Tarde',      '14:00:00', '22:00:00', 480, '#b34700'),
    (2, 'Noche',      '22:00:00', '06:00:00', 480, '#3d5a99'),
    -- 3. UTI (6h)
    (3, 'Vespertino', '00:00:00', '06:00:00', 360, '#7b4ea3'),
    (3, 'Mañana',     '06:00:00', '12:00:00', 360, '#307f6d'),
    (3, 'Tarde',      '12:00:00', '18:00:00', 360, '#c98a00'),
    (3, 'Noche',      '18:00:00', '00:00:00', 360, '#3d5a99'),
    -- 4. Neonatología (8h)
    (4, 'Mañana',     '06:00:00', '14:00:00', 480, '#c98a00'),
    (4, 'Tarde',      '14:00:00', '22:00:00', 480, '#b34700'),
    (4, 'Noche',      '22:00:00', '06:00:00', 480, '#3d5a99'),
    -- 5. Quirófano (8h)
    (5, 'Mañana',     '06:00:00', '14:00:00', 480, '#307f6d'),
    (5, 'Tarde',      '14:00:00', '22:00:00', 480, '#b34700'),
    (5, 'Guardia',    '22:00:00', '06:00:00', 480, '#3d5a99'),
    -- 6. Salud Mental (8h)
    (6, 'Mañana',     '06:00:00', '14:00:00', 480, '#c98a00'),
    (6, 'Tarde',      '14:00:00', '22:00:00', 480, '#b34700'),
    (6, 'Noche',      '22:00:00', '06:00:00', 480, '#3d5a99'),
    -- 7. Oncología (8h)
    (7, 'Mañana',     '06:00:00', '14:00:00', 480, '#307f6d'),
    (7, 'Tarde',      '14:00:00', '22:00:00', 480, '#7b4ea3');

-- ------------------------------------------------------------
--  TABLA: asignaciones   (HU-13 y HU-14)
--  Un turno asignado a un enfermero/a en un sector, fecha y franja.
--  inicio / fin se guardan completos (fecha + hora) al asignar:
--    - un turno noche de 22:00 a 06:00 termina al dia siguiente;
--    - si despues cambia el horario del tipo de turno, lo ya asignado no cambia.
--  Eliminar una asignacion futura = baja logica (deleted_at).
--  checkin_at / checkout_at / observaciones_pase: marcacion de ingreso,
--  salida y pase de guardia que hace el enfermero/a (HU-20).
-- ------------------------------------------------------------
CREATE TABLE asignaciones (
    id                  INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    usuario_id          INT UNSIGNED      NOT NULL,
    sector_id           INT UNSIGNED      NOT NULL,
    tipo_turno_id       INT UNSIGNED      NOT NULL,
    fecha               DATE              NOT NULL,          -- dia en que empieza el turno
    inicio              DATETIME          NOT NULL,
    fin                 DATETIME          NOT NULL,
    duracion_minutos    SMALLINT UNSIGNED NOT NULL,
    es_extra            TINYINT(1)        NOT NULL DEFAULT 0,
    minutos_extra       SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    creado_por          INT UNSIGNED      NULL,              -- administrador que lo asigno
    checkin_at          DATETIME          NULL DEFAULT NULL, -- ingreso marcado por el enfermero/a (HU-20)
    checkout_at         DATETIME          NULL DEFAULT NULL, -- fin de guardia marcado (HU-20)
    observaciones_pase  VARCHAR(1000)     NULL DEFAULT NULL, -- observaciones del pase de guardia (HU-20)
    created_at          DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME          NULL DEFAULT NULL,
    deleted_at          DATETIME          NULL DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_asignaciones_usuario_inicio (usuario_id, inicio),
    KEY idx_asignaciones_sector_fecha (sector_id, fecha),
    KEY idx_asignaciones_tipo_fecha (tipo_turno_id, fecha),
    CONSTRAINT fk_asignaciones_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id),
    CONSTRAINT fk_asignaciones_sector FOREIGN KEY (sector_id) REFERENCES sectores (id),
    CONSTRAINT fk_asignaciones_tipo_turno FOREIGN KEY (tipo_turno_id) REFERENCES tipos_turno (id),
    CONSTRAINT fk_asignaciones_creado_por FOREIGN KEY (creado_por) REFERENCES usuarios (id)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
--  TABLA: ausencias   (HU-18 aviso del enfermero/a, HU-16 reemplazo)
--  El enfermero/a avisa que no puede cumplir un turno futuro suyo.
--  Estados:
--    PENDIENTE -> aviso enviado, el administrador lo esta gestionando
--    CUBIERTA  -> se asigno un reemplazo (reemplazo_asignacion_id)
--    RECHAZADA -> el administrador lo rechazo: debe presentarse
--  "Ausencia activa" = PENDIENTE o CUBIERTA: ese turno no cuenta para
--  la dotacion ni para las horas semanales. Una asignacion tiene como
--  maximo una ausencia activa (lo controla la aplicacion).
--  Nunca se borra: los cambios son de estado.
-- ------------------------------------------------------------
CREATE TABLE ausencias (
    id                       INT UNSIGNED   NOT NULL AUTO_INCREMENT,
    asignacion_id            INT UNSIGNED   NOT NULL,          -- turno que no va a cumplir
    usuario_id               INT UNSIGNED   NOT NULL,          -- enfermero/a que avisa
    motivo                   ENUM('ENFERMEDAD', 'FUERZA_MAYOR', 'LICENCIA_MEDICA', 'OTRO') NOT NULL,
    descripcion              VARCHAR(500)   NOT NULL,
    estado                   ENUM('PENDIENTE', 'CUBIERTA', 'RECHAZADA') NOT NULL DEFAULT 'PENDIENTE',
    reemplazo_asignacion_id  INT UNSIGNED   NULL DEFAULT NULL, -- turno creado para el reemplazante
    resuelto_por             INT UNSIGNED   NULL DEFAULT NULL, -- administrador que cubrio o rechazo
    resuelto_at              DATETIME       NULL DEFAULT NULL,
    observacion_admin        VARCHAR(255)   NULL DEFAULT NULL, -- motivo del rechazo
    created_at               DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at               DATETIME       NULL DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_ausencias_asignacion_estado (asignacion_id, estado),
    KEY idx_ausencias_estado (estado),
    KEY idx_ausencias_usuario (usuario_id),
    KEY idx_ausencias_reemplazo (reemplazo_asignacion_id),
    CONSTRAINT fk_ausencias_asignacion FOREIGN KEY (asignacion_id) REFERENCES asignaciones (id),
    CONSTRAINT fk_ausencias_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id),
    CONSTRAINT fk_ausencias_reemplazo FOREIGN KEY (reemplazo_asignacion_id) REFERENCES asignaciones (id),
    CONSTRAINT fk_ausencias_resuelto_por FOREIGN KEY (resuelto_por) REFERENCES usuarios (id)
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS = 1;
