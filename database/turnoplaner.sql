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

-- Permite recrear tablas que se referencian entre si (asignaciones -> usuarios, sectores, tipos_turno)
SET FOREIGN_KEY_CHECKS = 0;
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
--  TABLA: tipos_turno   (HU-11 y HU-12)
--  Franjas horarias rotativas. La duracion la calcula el sistema
--  (un turno de 22:00 a 06:00 cruza la medianoche y dura 8 h).
--  Baja logica: estado + deleted_at, nunca DELETE.
-- ------------------------------------------------------------
DROP TABLE IF EXISTS tipos_turno;

CREATE TABLE tipos_turno (
    id                  INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    nombre              VARCHAR(60)       NOT NULL,
    hora_inicio         TIME              NOT NULL,
    hora_fin            TIME              NOT NULL,
    duracion_minutos    SMALLINT UNSIGNED NOT NULL,          -- calculada al guardar
    color               CHAR(7)           NOT NULL DEFAULT '#307f6d',  -- etiqueta para la cuadricula
    estado              ENUM('ACTIVO', 'BAJA') NOT NULL DEFAULT 'ACTIVO',
    created_at          DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME          NULL DEFAULT NULL,
    deleted_at          DATETIME          NULL DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_tipos_turno_nombre (nombre),
    KEY idx_tipos_turno_estado (estado)
) ENGINE=InnoDB;

INSERT INTO tipos_turno (nombre, hora_inicio, hora_fin, duracion_minutos, color) VALUES
    ('Mañana',           '06:00:00', '14:00:00', 480, '#c98a00'),
    ('Tarde',            '14:00:00', '22:00:00', 480, '#b34700'),
    ('Noche',            '22:00:00', '06:00:00', 480, '#3d5a99'),
    ('Guardia especial', '08:00:00', '20:00:00', 720, '#7b4ea3');

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

INSERT INTO sectores (nombre, descripcion, es_critico, dotacion_minima) VALUES
    ('UTI',                 'Unidad de Terapia Intensiva de adultos.',            1, 3),
    ('Guardia',             'Guardia general y emergencias.',                     1, 2),
    ('Neonatología',        'Cuidados neonatales e incubadoras.',                 1, 2),
    ('Internación general', 'Sala de internación clínica y quirúrgica.',          0, 2);

-- ------------------------------------------------------------
--  TABLA: asignaciones   (HU-13 y HU-14)
--  Un turno asignado a un enfermero/a en un sector, fecha y franja.
--  inicio / fin se guardan completos (fecha + hora) al asignar:
--    - un turno noche de 22:00 a 06:00 termina al dia siguiente;
--    - si despues cambia el horario del tipo de turno, lo ya asignado no cambia.
--  Eliminar una asignacion futura = baja logica (deleted_at).
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
    creado_por          INT UNSIGNED      NULL,              -- administrador que lo asigno
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

SET FOREIGN_KEY_CHECKS = 1;

-- ------------------------------------------------------------
--  PROXIMAS TABLAS (se agregan en su HU, no antes):
--    ausencias         -> HU-18
--    coberturas        -> HU-16
-- ------------------------------------------------------------
