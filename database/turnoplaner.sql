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

-- ------------------------------------------------------------
--  TABLA: usuarios   (HU-01, HU-02, HU-03, HU-05 a HU-08)
--  Un solo tipo de usuario con columna "rol". El enfermero/a
--  guarda ademas sus datos profesionales.
--  Baja logica: columna "estado", nunca DELETE.
-- ------------------------------------------------------------
DROP TABLE IF EXISTS usuarios;

CREATE TABLE usuarios (
    id                  INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    nombre              VARCHAR(60)      NOT NULL,
    apellido            VARCHAR(60)      NOT NULL,
    dni                 VARCHAR(10)      NOT NULL,
    matricula           VARCHAR(20)      NULL,           -- solo enfermeros/as
    email               VARCHAR(120)     NOT NULL,
    telefono            VARCHAR(30)      NULL,
    password_hash       VARCHAR(255)     NOT NULL,       -- password_hash() de PHP
    rol                 ENUM('ADMIN', 'ENFERMERO') NOT NULL DEFAULT 'ENFERMERO',
    especialidad        VARCHAR(80)      NULL,           -- solo enfermeros/as
    carga_horaria_max   TINYINT UNSIGNED NULL,           -- horas semanales contratadas
    estado              ENUM('PENDIENTE', 'ACTIVO', 'BAJA') NOT NULL DEFAULT 'PENDIENTE',
    debe_cambiar_pass   TINYINT(1)       NOT NULL DEFAULT 0,
    created_at          DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME         NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_usuarios_dni (dni),
    UNIQUE KEY uq_usuarios_email (email),
    UNIQUE KEY uq_usuarios_matricula (matricula)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
--  DATOS DE PRUEBA
--  Contrasenas (en texto plano, solo para desarrollo):
--    admin@turnoplaner.local      -> admin123
--    enfermera@turnoplaner.local  -> enfermero123
--    pendiente@turnoplaner.local  -> enfermero123  (todavia no habilitado)
-- ------------------------------------------------------------
INSERT INTO usuarios
    (nombre, apellido, dni, matricula, email, telefono, password_hash, rol, especialidad, carga_horaria_max, estado)
VALUES
    ('Admin', 'Sistema', '20000000', NULL, 'admin@turnoplaner.local', '2920000000',
     '$2y$10$anNSqYPlv1W3q8HSe1E1fORfpZ9wIby4dgNulp1yP6OWEtHsnAa0C', 'ADMIN', NULL, NULL, 'ACTIVO'),

    ('Maria', 'Gomez', '30123456', 'MP-1001', 'enfermera@turnoplaner.local', '2920111111',
     '$2y$10$jXySqRJ1ur32wT/fMe1rP.vxWLoCXxMVtVMELnZYgiuE9PLDbof5u', 'ENFERMERO', 'Terapia intensiva', 36, 'ACTIVO'),

    ('Juan', 'Perez', '31234567', 'MP-1002', 'pendiente@turnoplaner.local', '2920222222',
     '$2y$10$jXySqRJ1ur32wT/fMe1rP.vxWLoCXxMVtVMELnZYgiuE9PLDbof5u', 'ENFERMERO', 'Guardia', 40, 'PENDIENTE');

-- ------------------------------------------------------------
--  PROXIMAS TABLAS (se agregan en su HU, no antes):
--    sectores          -> HU-09
--    tipos_turno       -> HU-11
--    dotacion_minima   -> HU-09
--    asignaciones      -> HU-13
--    ausencias         -> HU-18
--    coberturas        -> HU-16
-- ------------------------------------------------------------
