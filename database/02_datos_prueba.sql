SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- Limpieza previa para re-ejecutar sin duplicados
TRUNCATE TABLE asignaciones;
TRUNCATE TABLE tipos_turno;
TRUNCATE TABLE sectores;
TRUNCATE TABLE usuarios;

SET FOREIGN_KEY_CHECKS = 1;

-- Hash bcrypt válido para 'Admin1234!'
SET @pass_hash = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';

-- ==========================================================
-- 1. SECTORES (4 áreas hospitalarias)
-- ==========================================================
INSERT INTO sectores (id, nombre, descripcion, es_critico, dotacion_minima, estado, created_at, updated_at) VALUES
(1, 'Guardia', 'Atención de emergencias y urgencias 24 hs', 1, 3, 'ACTIVO', NOW(), NOW()),
(2, 'Neonatología', 'Cuidados intensivos e intermedios neonatales', 1, 2, 'ACTIVO', NOW(), NOW()),
(3, 'UTI', 'Unidad de Terapia Intensiva de adultos (esquema 6 hs)', 1, 3, 'ACTIVO', NOW(), NOW()),
(4, 'Internación general', 'Sala común de clínica médica y quirúrgica', 0, 2, 'ACTIVO', NOW(), NOW());

-- ==========================================================
-- 2. TIPOS DE TURNO
--    - Sectores 1, 2 y 4: 3 turnos de 8 horas (480 min)
--    - Sector 3 (UTI): 4 turnos de 6 horas (360 min)
-- ==========================================================
INSERT INTO tipos_turno (id, sector_id, nombre, hora_inicio, hora_fin, duracion_minutos, color, estado, created_at, updated_at) VALUES
-- Guardia (8h)
(1,  1, 'Mañana',     '06:00:00', '14:00:00', 480, '#d9a000', 'ACTIVO', NOW(), NOW()),
(2,  1, 'Tarde',      '14:00:00', '22:00:00', 480, '#b85d19', 'ACTIVO', NOW(), NOW()),
(3,  1, 'Noche',      '22:00:00', '06:00:00', 480, '#2c4c7e', 'ACTIVO', NOW(), NOW()),

-- Neonatología (8h)
(4,  2, 'Mañana',     '06:00:00', '14:00:00', 480, '#d9a000', 'ACTIVO', NOW(), NOW()),
(5,  2, 'Tarde',      '14:00:00', '22:00:00', 480, '#b85d19', 'ACTIVO', NOW(), NOW()),
(6,  2, 'Noche',      '22:00:00', '06:00:00', 480, '#2c4c7e', 'ACTIVO', NOW(), NOW()),

-- UTI (6h: Vespertino 00-06, Mañana 06-12, Tarde 12-18, Noche 18-00)
(7,  3, 'Vespertino', '00:00:00', '06:00:00', 360, '#6a4c93', 'ACTIVO', NOW(), NOW()),
(8,  3, 'Mañana',     '06:00:00', '12:00:00', 360, '#2d6855', 'ACTIVO', NOW(), NOW()),
(9,  3, 'Tarde',      '12:00:00', '18:00:00', 360, '#d9a000', 'ACTIVO', NOW(), NOW()),
(10, 3, 'Noche',      '18:00:00', '00:00:00', 360, '#2c4c7e', 'ACTIVO', NOW(), NOW()),

-- Internación general (8h)
(11, 4, 'Mañana',     '06:00:00', '14:00:00', 480, '#d9a000', 'ACTIVO', NOW(), NOW()),
(12, 4, 'Tarde',      '14:00:00', '22:00:00', 480, '#b85d19', 'ACTIVO', NOW(), NOW()),
(13, 4, 'Noche',      '22:00:00', '06:00:00', 480, '#2c4c7e', 'ACTIVO', NOW(), NOW());

-- ==========================================================
-- 3. USUARIOS: 1 Administrador + 50 Enfermeros/as
-- ==========================================================
INSERT INTO usuarios (id, dni, nombre, apellido, email, password_hash, rol, matricula, especialidad, carga_horaria_max, estado, debe_cambiar_pass, created_at, updated_at, deleted_at) VALUES
(1, '25000001', 'Admin', 'Sistema', 'admin@turnoplaner.local', @pass_hash, 'ADMIN', NULL, NULL, NULL, 'ACTIVO', 0, NOW(), NOW(), NULL),

-- 50 Enfermeros/as (IDs 2 al 51)
(2,  '30100001', 'Lucía',      'Fernández',   'lucia.fernandez@hospital.local',    @pass_hash, 'ENFERMERO', 'MP-1001', 'Cuidados Críticos / Terapia Intensiva (UTI)', 36, 'ACTIVO', 0, NOW(), NOW(), NULL),
(3,  '30100002', 'Martín',     'Gómez',       'martin.gomez@hospital.local',       @pass_hash, 'ENFERMERO', 'MP-1002', 'Guardia y Emergencias',                       48, 'ACTIVO', 0, NOW(), NOW(), NULL),
(4,  '30100003', 'Valentina',  'Rodríguez',   'valentina.rodriguez@hospital.local',@pass_hash, 'ENFERMERO', 'MP-1003', 'Pediatría y Neonatología',                    36, 'ACTIVO', 0, NOW(), NOW(), NULL),
(5,  '30100004', 'Santiago',   'López',       'santiago.lopez@hospital.local',     @pass_hash, 'ENFERMERO', 'MP-1004', 'Cuidados Críticos / Terapia Intensiva (UTI)', 36, 'ACTIVO', 0, NOW(), NOW(), NULL),
(6,  '30100005', 'Camila',     'Martínez',    'camila.martinez@hospital.local',    @pass_hash, 'ENFERMERO', 'MP-1005', 'Enfermería General',                          48, 'ACTIVO', 0, NOW(), NOW(), NULL),
(7,  '30100006', 'Mateo',      'Díaz',        'mateo.diaz@hospital.local',         @pass_hash, 'ENFERMERO', 'MP-1006', 'Guardia y Emergencias',                       48, 'ACTIVO', 0, NOW(), NOW(), NULL),
(8,  '30100007', 'Sofía',      'Pérez',       'sofia.perez@hospital.local',        @pass_hash, 'ENFERMERO', 'MP-1007', 'Cuidados Críticos / Terapia Intensiva (UTI)', 36, 'ACTIVO', 0, NOW(), NOW(), NULL),
(9,  '30100008', 'Benjamín',   'Sánchez',     'benjamin.sanchez@hospital.local',   @pass_hash, 'ENFERMERO', 'MP-1008', 'Pediatría y Neonatología',                    36, 'ACTIVO', 0, NOW(), NOW(), NULL),
(10, '30100009', 'Martina',    'Romero',      'martina.romero@hospital.local',     @pass_hash, 'ENFERMERO', 'MP-1009', 'Quirófano / Centro Quirúrgico',               48, 'ACTIVO', 0, NOW(), NOW(), NULL),
(11, '30100010', 'Joaquín',    'Sosa',        'joaquin.sosa@hospital.local',       @pass_hash, 'ENFERMERO', 'MP-1010', 'Cuidados Críticos / Terapia Intensiva (UTI)', 36, 'ACTIVO', 0, NOW(), NOW(), NULL),
(12, '30100011', 'Catalina',   'Torres',      'catalina.torres@hospital.local',    @pass_hash, 'ENFERMERO', 'MP-1011', 'Guardia y Emergencias',                       48, 'ACTIVO', 0, NOW(), NOW(), NULL),
(13, '30100012', 'Thiago',     'Álvarez',     'thiago.alvarez@hospital.local',     @pass_hash, 'ENFERMERO', 'MP-1012', 'Cuidados Críticos / Terapia Intensiva (UTI)', 36, 'ACTIVO', 0, NOW(), NOW(), NULL),
(14, '30100013', 'Julieta',    'Ruiz',        'julieta.ruiz@hospital.local',       @pass_hash, 'ENFERMERO', 'MP-1013', 'Pediatría y Neonatología',                    36, 'ACTIVO', 0, NOW(), NOW(), NULL),
(15, '30100014', 'Bautista',   'Ramírez',     'bautista.ramirez@hospital.local',   @pass_hash, 'ENFERMERO', 'MP-1014', 'Enfermería General',                          48, 'ACTIVO', 0, NOW(), NOW(), NULL),
(16, '30100015', 'Victoria',   'Flores',      'victoria.flores@hospital.local',    @pass_hash, 'ENFERMERO', 'MP-1015', 'Guardia y Emergencias',                       48, 'ACTIVO', 0, NOW(), NOW(), NULL),
(17, '30100016', 'Lautaro',    'Acosta',      'lautaro.acosta@hospital.local',     @pass_hash, 'ENFERMERO', 'MP-1016', 'Cuidados Críticos / Terapia Intensiva (UTI)', 36, 'ACTIVO', 0, NOW(), NOW(), NULL),
(18, '30100017', 'Emilia',     'Benítez',     'emilia.benitez@hospital.local',     @pass_hash, 'ENFERMERO', 'MP-1017', 'Pediatría y Neonatología',                    36, 'ACTIVO', 0, NOW(), NOW(), NULL),
(19, '30100018', 'Tomás',      'Medina',      'tomas.medina@hospital.local',       @pass_hash, 'ENFERMERO', 'MP-1018', 'Enfermería General',                          48, 'ACTIVO', 0, NOW(), NOW(), NULL),
(20, '30100019', 'Delfina',    'Herrera',     'delfina.herrera@hospital.local',    @pass_hash, 'ENFERMERO', 'MP-1019', 'Cuidados Críticos / Terapia Intensiva (UTI)', 36, 'ACTIVO', 0, NOW(), NOW(), NULL),
(21, '30100020', 'Ignacio',    'Suárez',      'ignacio.suarez@hospital.local',     @pass_hash, 'ENFERMERO', 'MP-1020', 'Guardia y Emergencias',                       48, 'ACTIVO', 0, NOW(), NOW(), NULL),
(22, '30100021', 'Renata',     'Aguirre',     'renata.aguirre@hospital.local',     @pass_hash, 'ENFERMERO', 'MP-1021', 'Pediatría y Neonatología',                    36, 'ACTIVO', 0, NOW(), NOW(), NULL),
(23, '30100022', 'Santino',    'Giménez',     'santino.gimenez@hospital.local',    @pass_hash, 'ENFERMERO', 'MP-1022', 'Cuidados Críticos / Terapia Intensiva (UTI)', 36, 'ACTIVO', 0, NOW(), NOW(), NULL),
(24, '30100023', 'Olivia',     'Gutiérrez',   'olivia.gutierrez@hospital.local',   @pass_hash, 'ENFERMERO', 'MP-1023', 'Quirófano / Centro Quirúrgico',               48, 'ACTIVO', 0, NOW(), NOW(), NULL),
(25, '30100024', 'Felipe',     'Pereyra',     'felipe.pereyra@hospital.local',     @pass_hash, 'ENFERMERO', 'MP-1024', 'Guardia y Emergencias',                       48, 'ACTIVO', 0, NOW(), NOW(), NULL),
(26, '30100025', 'Alma',       'Rojas',       'alma.rojas@hospital.local',         @pass_hash, 'ENFERMERO', 'MP-1025', 'Cuidados Críticos / Terapia Intensiva (UTI)', 36, 'ACTIVO', 0, NOW(), NOW(), NULL),
(27, '30100026', 'Lucas',      'Molina',      'lucas.molina@hospital.local',       @pass_hash, 'ENFERMERO', 'MP-1026', 'Pediatría y Neonatología',                    36, 'ACTIVO', 0, NOW(), NOW(), NULL),
(28, '30100027', 'Mía',        'Castro',      'mia.castro@hospital.local',         @pass_hash, 'ENFERMERO', 'MP-1027', 'Enfermería General',                          48, 'ACTIVO', 0, NOW(), NOW(), NULL),
(29, '30100028', 'Nicolás',    'Ortiz',       'nicolas.ortiz@hospital.local',      @pass_hash, 'ENFERMERO', 'MP-1028', 'Cuidados Críticos / Terapia Intensiva (UTI)', 36, 'ACTIVO', 0, NOW(), NOW(), NULL),
(30, '30100029', 'Clara',      'Silva',       'clara.silva@hospital.local',        @pass_hash, 'ENFERMERO', 'MP-1029', 'Guardia y Emergencias',                       48, 'ACTIVO', 0, NOW(), NOW(), NULL),
(31, '30100030', 'Agustín',    'Núñez',       'agustin.nunez@hospital.local',      @pass_hash, 'ENFERMERO', 'MP-1030', 'Cuidados Críticos / Terapia Intensiva (UTI)', 36, 'ACTIVO', 0, NOW(), NOW(), NULL),
(32, '30100031', 'Elena',      'Luna',        'elena.luna@hospital.local',         @pass_hash, 'ENFERMERO', 'MP-1031', 'Pediatría y Neonatología',                    36, 'ACTIVO', 0, NOW(), NOW(), NULL),
(33, '30100032', 'Facundo',    'Juárez',      'facundo.juarez@hospital.local',     @pass_hash, 'ENFERMERO', 'MP-1032', 'Enfermería General',                          48, 'ACTIVO', 0, NOW(), NOW(), NULL),
(34, '30100033', 'Abril',      'Cabrera',     'abril.cabrera@hospital.local',      @pass_hash, 'ENFERMERO', 'MP-1033', 'Guardia y Emergencias',                       48, 'ACTIVO', 0, NOW(), NOW(), NULL),
(35, '30100034', 'Franco',     'Ríos',        'franco.rios@hospital.local',        @pass_hash, 'ENFERMERO', 'MP-1034', 'Cuidados Críticos / Terapia Intensiva (UTI)', 36, 'ACTIVO', 0, NOW(), NOW(), NULL),
(36, '30100035', 'Pilar',      'Morales',     'pilar.morales@hospital.local',      @pass_hash, 'ENFERMERO', 'MP-1035', 'Pediatría y Neonatología',                    36, 'ACTIVO', 0, NOW(), NOW(), NULL),
(37, '30100036', 'Ramiro',     'Domínguez',   'ramiro.dominguez@hospital.local',   @pass_hash, 'ENFERMERO', 'MP-1036', 'Quirófano / Centro Quirúrgico',               48, 'ACTIVO', 0, NOW(), NOW(), NULL),
(38, '30100037', 'Juana',      'Vega',        'juana.vega@hospital.local',         @pass_hash, 'ENFERMERO', 'MP-1037', 'Cuidados Críticos / Terapia Intensiva (UTI)', 36, 'ACTIVO', 0, NOW(), NOW(), NULL),
(39, '30100038', 'Gonzalo',    'Reyes',       'gonzalo.reyes@hospital.local',      @pass_hash, 'ENFERMERO', 'MP-1038', 'Guardia y Emergencias',                       48, 'ACTIVO', 0, NOW(), NOW(), NULL),
(40, '30100039', 'Malena',     'Campos',      'malena.campos@hospital.local',      @pass_hash, 'ENFERMERO', 'MP-1039', 'Oncología',                                   36, 'ACTIVO', 0, NOW(), NOW(), NULL),
(41, '30100040', 'Bruno',      'Cardozo',     'bruno.cardozo@hospital.local',      @pass_hash, 'ENFERMERO', 'MP-1040', 'Pediatría y Neonatología',                    36, 'ACTIVO', 0, NOW(), NOW(), NULL),
(42, '30100041', 'Lola',       'Vargas',      'lola.vargas@hospital.local',        @pass_hash, 'ENFERMERO', 'MP-1041', 'Enfermería General',                          48, 'ACTIVO', 0, NOW(), NOW(), NULL),
(43, '30100042', 'Ezequiel',   'Méndez',      'ezequiel.mendez@hospital.local',    @pass_hash, 'ENFERMERO', 'MP-1042', 'Guardia y Emergencias',                       48, 'ACTIVO', 0, NOW(), NOW(), NULL),
(44, '30100043', 'Bianca',     'Ponce',       'bianca.ponce@hospital.local',       @pass_hash, 'ENFERMERO', 'MP-1043', 'Salud Mental',                                36, 'ACTIVO', 0, NOW(), NOW(), NULL),
(45, '30100044', 'Maximiliano','Soria',       'maximiliano.soria@hospital.local',  @pass_hash, 'ENFERMERO', 'MP-1044', 'Cuidados Críticos / Terapia Intensiva (UTI)', 36, 'ACTIVO', 0, NOW(), NOW(), NULL),
(46, '30100045', 'Zoe',        'Ibarra',      'zoe.ibarra@hospital.local',         @pass_hash, 'ENFERMERO', 'MP-1045', 'Pediatría y Neonatología',                    36, 'ACTIVO', 0, NOW(), NOW(), NULL),
(47, '30100046', 'Nahuel',     'Peralta',     'nahuel.peralta@hospital.local',     @pass_hash, 'ENFERMERO', 'MP-1046', 'Enfermería General',                          48, 'ACTIVO', 0, NOW(), NOW(), NULL),
(48, '30100047', 'Candelaria', 'Quiroga',     'candelaria.quiroga@hospital.local', @pass_hash, 'ENFERMERO', 'MP-1047', 'Guardia y Emergencias',                       48, 'ACTIVO', 0, NOW(), NOW(), NULL),
(49, '30100048', 'Sebastián',  'Miranda',     'sebastian.miranda@hospital.local',  @pass_hash, 'ENFERMERO', 'MP-1048', 'Oncología',                                   36, 'ACTIVO', 0, NOW(), NOW(), NULL),
(50, '30100049', 'Josefina',   'Vera',        'josefina.vera@hospital.local',      @pass_hash, 'ENFERMERO', 'MP-1049', 'Quirófano / Centro Quirúrgico',               48, 'ACTIVO', 1, NOW(), NOW(), NULL),
(51, '30100050', 'Rodrigo',    'Ferreyra',    'rodrigo.ferreyra@hospital.local',   @pass_hash, 'ENFERMERO', 'MP-1050', 'Guardia y Emergencias',                       48, 'BAJA',   0, NOW(), NOW(), NOW());

-- ==========================================================
-- 4. POBLADO REALISTA DE TURNOS (15 Nov 2026 al 31 Dic 2026)
--    Respeta no-superposición, 2 francos semanales por persona,
--    turnos de 6h en UTI (4 turnos) y 8h en el resto (3 turnos),
--    y genera casos reales de cobertura completa, parcial y HE.
-- ==========================================================
DROP PROCEDURE IF EXISTS sp_poblar_turnos_demo;
DELIMITER $$

CREATE PROCEDURE sp_poblar_turnos_demo()
BEGIN
    DECLARE v_fecha DATE DEFAULT '2026-11-15';
    DECLARE v_fin   DATE DEFAULT '2026-12-31';
    DECLARE v_dia_idx INT DEFAULT 0;
    DECLARE v_offset INT;
    DECLARE v_uid INT;
    DECLARE v_inicio DATETIME;
    DECLARE v_termino DATETIME;
    DECLARE v_min_sem INT;
    DECLARE v_max_sem INT;
    DECLARE v_extra TINYINT;
    DECLARE v_min_extra INT;
    DECLARE v_lunes DATE;
    DECLARE v_domingo DATE;

    WHILE v_fecha <= v_fin DO
        SET v_lunes   = DATE_SUB(v_fecha, INTERVAL WEEKDAY(v_fecha) DAY);
        SET v_domingo = DATE_ADD(v_lunes, INTERVAL 6 DAY);

        -- ------------------------------------------------------
        -- A) GUARDIA (Sector 1, IDs enfermeros 2..15, turnos 8h)
        -- ------------------------------------------------------
        -- Mañana (tipo 1: 06:00-14:00) -> 3 enfermeros (cubierto)
        SET v_offset = 0;
        WHILE v_offset < 3 DO
            SET v_uid = 2 + ((v_dia_idx * 3 + v_offset) % 14);
            SET v_inicio = TIMESTAMP(v_fecha, '06:00:00');
            SET v_termino = TIMESTAMP(v_fecha, '14:00:00');
            IF NOT EXISTS (SELECT 1 FROM asignaciones WHERE usuario_id = v_uid AND inicio < v_termino AND fin > v_inicio) THEN
                SELECT COALESCE(SUM(duracion_minutos), 0) INTO v_min_sem FROM asignaciones WHERE usuario_id = v_uid AND fecha BETWEEN v_lunes AND v_domingo;
                SELECT carga_horaria_max * 60 INTO v_max_sem FROM usuarios WHERE id = v_uid;
                SET v_extra = IF(v_min_sem + 480 > v_max_sem, 1, 0);
                SET v_min_extra = IF(v_extra = 1, LEAST(480, v_min_sem + 480 - v_max_sem), 0);
                INSERT INTO asignaciones (usuario_id, sector_id, tipo_turno_id, fecha, inicio, fin, duracion_minutos, es_extra, minutos_extra, creado_por, created_at, updated_at)
                VALUES (v_uid, 1, 1, v_fecha, v_inicio, v_termino, 480, v_extra, v_min_extra, 1, NOW(), NOW());
            END IF;
            SET v_offset = v_offset + 1;
        END WHILE;

        -- Tarde (tipo 2: 14:00-22:00) -> 3 enfermeros (cada 4 días deja 2 para simular parcial)
        SET v_offset = 0;
        WHILE v_offset < IF(v_dia_idx % 4 = 0, 2, 3) DO
            SET v_uid = 2 + ((v_dia_idx * 3 + 5 + v_offset) % 14);
            SET v_inicio = TIMESTAMP(v_fecha, '14:00:00');
            SET v_termino = TIMESTAMP(v_fecha, '22:00:00');
            IF NOT EXISTS (SELECT 1 FROM asignaciones WHERE usuario_id = v_uid AND inicio < v_termino AND fin > v_inicio) THEN
                SELECT COALESCE(SUM(duracion_minutos), 0) INTO v_min_sem FROM asignaciones WHERE usuario_id = v_uid AND fecha BETWEEN v_lunes AND v_domingo;
                SELECT carga_horaria_max * 60 INTO v_max_sem FROM usuarios WHERE id = v_uid;
                SET v_extra = IF(v_min_sem + 480 > v_max_sem, 1, 0);
                SET v_min_extra = IF(v_extra = 1, LEAST(480, v_min_sem + 480 - v_max_sem), 0);
                INSERT INTO asignaciones (usuario_id, sector_id, tipo_turno_id, fecha, inicio, fin, duracion_minutos, es_extra, minutos_extra, creado_por, created_at, updated_at)
                VALUES (v_uid, 1, 2, v_fecha, v_inicio, v_termino, 480, v_extra, v_min_extra, 1, NOW(), NOW());
            END IF;
            SET v_offset = v_offset + 1;
        END WHILE;

        -- Noche (tipo 3: 22:00-06:00 +1d) -> 3 enfermeros
        SET v_offset = 0;
        WHILE v_offset < 3 DO
            SET v_uid = 2 + ((v_dia_idx * 3 + 10 + v_offset) % 14);
            SET v_inicio = TIMESTAMP(v_fecha, '22:00:00');
            SET v_termino = TIMESTAMP(DATE_ADD(v_fecha, INTERVAL 1 DAY), '06:00:00');
            IF NOT EXISTS (SELECT 1 FROM asignaciones WHERE usuario_id = v_uid AND inicio < v_termino AND fin > v_inicio) THEN
                SELECT COALESCE(SUM(duracion_minutos), 0) INTO v_min_sem FROM asignaciones WHERE usuario_id = v_uid AND fecha BETWEEN v_lunes AND v_domingo;
                SELECT carga_horaria_max * 60 INTO v_max_sem FROM usuarios WHERE id = v_uid;
                SET v_extra = IF(v_min_sem + 480 > v_max_sem, 1, 0);
                SET v_min_extra = IF(v_extra = 1, LEAST(480, v_min_sem + 480 - v_max_sem), 0);
                INSERT INTO asignaciones (usuario_id, sector_id, tipo_turno_id, fecha, inicio, fin, duracion_minutos, es_extra, minutos_extra, creado_por, created_at, updated_at)
                VALUES (v_uid, 1, 3, v_fecha, v_inicio, v_termino, 480, v_extra, v_min_extra, 1, NOW(), NOW());
            END IF;
            SET v_offset = v_offset + 1;
        END WHILE;

        -- ------------------------------------------------------
        -- B) NEONATOLOGÍA (Sector 2, IDs enfermeros 16..25, 8h)
        -- ------------------------------------------------------
        SET v_offset = 0;
        WHILE v_offset < 2 DO
            -- Mañana (tipo 4)
            SET v_uid = 16 + ((v_dia_idx * 2 + v_offset) % 10);
            SET v_inicio = TIMESTAMP(v_fecha, '06:00:00');
            SET v_termino = TIMESTAMP(v_fecha, '14:00:00');
            IF NOT EXISTS (SELECT 1 FROM asignaciones WHERE usuario_id = v_uid AND inicio < v_termino AND fin > v_inicio) THEN
                SELECT COALESCE(SUM(duracion_minutos), 0) INTO v_min_sem FROM asignaciones WHERE usuario_id = v_uid AND fecha BETWEEN v_lunes AND v_domingo;
                SELECT carga_horaria_max * 60 INTO v_max_sem FROM usuarios WHERE id = v_uid;
                SET v_extra = IF(v_min_sem + 480 > v_max_sem, 1, 0);
                SET v_min_extra = IF(v_extra = 1, LEAST(480, v_min_sem + 480 - v_max_sem), 0);
                INSERT INTO asignaciones (usuario_id, sector_id, tipo_turno_id, fecha, inicio, fin, duracion_minutos, es_extra, minutos_extra, creado_por, created_at, updated_at)
                VALUES (v_uid, 2, 4, v_fecha, v_inicio, v_termino, 480, v_extra, v_min_extra, 1, NOW(), NOW());
            END IF;

            -- Tarde (tipo 5)
            SET v_uid = 16 + ((v_dia_idx * 2 + 4 + v_offset) % 10);
            SET v_inicio = TIMESTAMP(v_fecha, '14:00:00');
            SET v_termino = TIMESTAMP(v_fecha, '22:00:00');
            IF NOT EXISTS (SELECT 1 FROM asignaciones WHERE usuario_id = v_uid AND inicio < v_termino AND fin > v_inicio) THEN
                SELECT COALESCE(SUM(duracion_minutos), 0) INTO v_min_sem FROM asignaciones WHERE usuario_id = v_uid AND fecha BETWEEN v_lunes AND v_domingo;
                SELECT carga_horaria_max * 60 INTO v_max_sem FROM usuarios WHERE id = v_uid;
                SET v_extra = IF(v_min_sem + 480 > v_max_sem, 1, 0);
                SET v_min_extra = IF(v_extra = 1, LEAST(480, v_min_sem + 480 - v_max_sem), 0);
                INSERT INTO asignaciones (usuario_id, sector_id, tipo_turno_id, fecha, inicio, fin, duracion_minutos, es_extra, minutos_extra, creado_por, created_at, updated_at)
                VALUES (v_uid, 2, 5, v_fecha, v_inicio, v_termino, 480, v_extra, v_min_extra, 1, NOW(), NOW());
            END IF;

            -- Noche (tipo 6)
            SET v_uid = 16 + ((v_dia_idx * 2 + 7 + v_offset) % 10);
            SET v_inicio = TIMESTAMP(v_fecha, '22:00:00');
            SET v_termino = TIMESTAMP(DATE_ADD(v_fecha, INTERVAL 1 DAY), '06:00:00');
            IF NOT EXISTS (SELECT 1 FROM asignaciones WHERE usuario_id = v_uid AND inicio < v_termino AND fin > v_inicio) THEN
                SELECT COALESCE(SUM(duracion_minutos), 0) INTO v_min_sem FROM asignaciones WHERE usuario_id = v_uid AND fecha BETWEEN v_lunes AND v_domingo;
                SELECT carga_horaria_max * 60 INTO v_max_sem FROM usuarios WHERE id = v_uid;
                SET v_extra = IF(v_min_sem + 480 > v_max_sem, 1, 0);
                SET v_min_extra = IF(v_extra = 1, LEAST(480, v_min_sem + 480 - v_max_sem), 0);
                INSERT INTO asignaciones (usuario_id, sector_id, tipo_turno_id, fecha, inicio, fin, duracion_minutos, es_extra, minutos_extra, creado_por, created_at, updated_at)
                VALUES (v_uid, 2, 6, v_fecha, v_inicio, v_termino, 480, v_extra, v_min_extra, 1, NOW(), NOW());
            END IF;

            SET v_offset = v_offset + 1;
        END WHILE;

        -- ------------------------------------------------------
        -- C) UTI (Sector 3, IDs enfermeros 26..41, 4 turnos x 6h)
        -- ------------------------------------------------------
        -- Vespertino UTI (tipo 7: 00:00-06:00) -> 3 enfermeros (deja el 20/11 sin cubrir como caso de prueba)
        IF v_fecha <> '2026-11-20' THEN
            SET v_offset = 0;
            WHILE v_offset < 3 DO
                SET v_uid = 26 + ((v_dia_idx * 3 + v_offset) % 16);
                SET v_inicio = TIMESTAMP(v_fecha, '00:00:00');
                SET v_termino = TIMESTAMP(v_fecha, '06:00:00');
                IF NOT EXISTS (SELECT 1 FROM asignaciones WHERE usuario_id = v_uid AND inicio < v_termino AND fin > v_inicio) THEN
                    SELECT COALESCE(SUM(duracion_minutos), 0) INTO v_min_sem FROM asignaciones WHERE usuario_id = v_uid AND fecha BETWEEN v_lunes AND v_domingo;
                    SELECT carga_horaria_max * 60 INTO v_max_sem FROM usuarios WHERE id = v_uid;
                    SET v_extra = IF(v_min_sem + 360 > v_max_sem, 1, 0);
                    SET v_min_extra = IF(v_extra = 1, LEAST(360, v_min_sem + 360 - v_max_sem), 0);
                    INSERT INTO asignaciones (usuario_id, sector_id, tipo_turno_id, fecha, inicio, fin, duracion_minutos, es_extra, minutos_extra, creado_por, created_at, updated_at)
                    VALUES (v_uid, 3, 7, v_fecha, v_inicio, v_termino, 360, v_extra, v_min_extra, 1, NOW(), NOW());
                END IF;
                SET v_offset = v_offset + 1;
            END WHILE;
        END IF;

        -- Mañana UTI (tipo 8: 06:00-12:00) -> 3 enfermeros
        SET v_offset = 0;
        WHILE v_offset < 3 DO
            SET v_uid = 26 + ((v_dia_idx * 3 + 4 + v_offset) % 16);
            SET v_inicio = TIMESTAMP(v_fecha, '06:00:00');
            SET v_termino = TIMESTAMP(v_fecha, '12:00:00');
            IF NOT EXISTS (SELECT 1 FROM asignaciones WHERE usuario_id = v_uid AND inicio < v_termino AND fin > v_inicio) THEN
                SELECT COALESCE(SUM(duracion_minutos), 0) INTO v_min_sem FROM asignaciones WHERE usuario_id = v_uid AND fecha BETWEEN v_lunes AND v_domingo;
                SELECT carga_horaria_max * 60 INTO v_max_sem FROM usuarios WHERE id = v_uid;
                SET v_extra = IF(v_min_sem + 360 > v_max_sem, 1, 0);
                SET v_min_extra = IF(v_extra = 1, LEAST(360, v_min_sem + 360 - v_max_sem), 0);
                INSERT INTO asignaciones (usuario_id, sector_id, tipo_turno_id, fecha, inicio, fin, duracion_minutos, es_extra, minutos_extra, creado_por, created_at, updated_at)
                VALUES (v_uid, 3, 8, v_fecha, v_inicio, v_termino, 360, v_extra, v_min_extra, 1, NOW(), NOW());
            END IF;
            SET v_offset = v_offset + 1;
        END WHILE;

        -- Tarde UTI (tipo 9: 12:00-18:00) -> 3 enfermeros (cada 5 días deja 2 para parcial)
        SET v_offset = 0;
        WHILE v_offset < IF(v_dia_idx % 5 = 2, 2, 3) DO
            SET v_uid = 26 + ((v_dia_idx * 3 + 8 + v_offset) % 16);
            SET v_inicio = TIMESTAMP(v_fecha, '12:00:00');
            SET v_termino = TIMESTAMP(v_fecha, '18:00:00');
            IF NOT EXISTS (SELECT 1 FROM asignaciones WHERE usuario_id = v_uid AND inicio < v_termino AND fin > v_inicio) THEN
                SELECT COALESCE(SUM(duracion_minutos), 0) INTO v_min_sem FROM asignaciones WHERE usuario_id = v_uid AND fecha BETWEEN v_lunes AND v_domingo;
                SELECT carga_horaria_max * 60 INTO v_max_sem FROM usuarios WHERE id = v_uid;
                SET v_extra = IF(v_min_sem + 360 > v_max_sem, 1, 0);
                SET v_min_extra = IF(v_extra = 1, LEAST(360, v_min_sem + 360 - v_max_sem), 0);
                INSERT INTO asignaciones (usuario_id, sector_id, tipo_turno_id, fecha, inicio, fin, duracion_minutos, es_extra, minutos_extra, creado_por, created_at, updated_at)
                VALUES (v_uid, 3, 9, v_fecha, v_inicio, v_termino, 360, v_extra, v_min_extra, 1, NOW(), NOW());
            END IF;
            SET v_offset = v_offset + 1;
        END WHILE;

        -- Noche UTI (tipo 10: 18:00-00:00 +1d) -> 3 enfermeros
        SET v_offset = 0;
        WHILE v_offset < 3 DO
            SET v_uid = 26 + ((v_dia_idx * 3 + 12 + v_offset) % 16);
            SET v_inicio = TIMESTAMP(v_fecha, '18:00:00');
            SET v_termino = TIMESTAMP(DATE_ADD(v_fecha, INTERVAL 1 DAY), '00:00:00');
            IF NOT EXISTS (SELECT 1 FROM asignaciones WHERE usuario_id = v_uid AND inicio < v_termino AND fin > v_inicio) THEN
                SELECT COALESCE(SUM(duracion_minutos), 0) INTO v_min_sem FROM asignaciones WHERE usuario_id = v_uid AND fecha BETWEEN v_lunes AND v_domingo;
                SELECT carga_horaria_max * 60 INTO v_max_sem FROM usuarios WHERE id = v_uid;
                SET v_extra = IF(v_min_sem + 360 > v_max_sem, 1, 0);
                SET v_min_extra = IF(v_extra = 1, LEAST(360, v_min_sem + 360 - v_max_sem), 0);
                INSERT INTO asignaciones (usuario_id, sector_id, tipo_turno_id, fecha, inicio, fin, duracion_minutos, es_extra, minutos_extra, creado_por, created_at, updated_at)
                VALUES (v_uid, 3, 10, v_fecha, v_inicio, v_termino, 360, v_extra, v_min_extra, 1, NOW(), NOW());
            END IF;
            SET v_offset = v_offset + 1;
        END WHILE;

        -- ------------------------------------------------------
        -- D) INTERNACIÓN GENERAL (Sector 4, IDs 42..49, 8h)
        -- ------------------------------------------------------
        SET v_offset = 0;
        WHILE v_offset < 2 DO
            -- Mañana (tipo 11)
            SET v_uid = 42 + ((v_dia_idx * 2 + v_offset) % 8);
            SET v_inicio = TIMESTAMP(v_fecha, '06:00:00');
            SET v_termino = TIMESTAMP(v_fecha, '14:00:00');
            IF NOT EXISTS (SELECT 1 FROM asignaciones WHERE usuario_id = v_uid AND inicio < v_termino AND fin > v_inicio) THEN
                SELECT COALESCE(SUM(duracion_minutos), 0) INTO v_min_sem FROM asignaciones WHERE usuario_id = v_uid AND fecha BETWEEN v_lunes AND v_domingo;
                SELECT carga_horaria_max * 60 INTO v_max_sem FROM usuarios WHERE id = v_uid;
                SET v_extra = IF(v_min_sem + 480 > v_max_sem, 1, 0);
                SET v_min_extra = IF(v_extra = 1, LEAST(480, v_min_sem + 480 - v_max_sem), 0);
                INSERT INTO asignaciones (usuario_id, sector_id, tipo_turno_id, fecha, inicio, fin, duracion_minutos, es_extra, minutos_extra, creado_por, created_at, updated_at)
                VALUES (v_uid, 4, 11, v_fecha, v_inicio, v_termino, 480, v_extra, v_min_extra, 1, NOW(), NOW());
            END IF;

            -- Tarde (tipo 12)
            SET v_uid = 42 + ((v_dia_idx * 2 + 3 + v_offset) % 8);
            SET v_inicio = TIMESTAMP(v_fecha, '14:00:00');
            SET v_termino = TIMESTAMP(v_fecha, '22:00:00');
            IF NOT EXISTS (SELECT 1 FROM asignaciones WHERE usuario_id = v_uid AND inicio < v_termino AND fin > v_inicio) THEN
                SELECT COALESCE(SUM(duracion_minutos), 0) INTO v_min_sem FROM asignaciones WHERE usuario_id = v_uid AND fecha BETWEEN v_lunes AND v_domingo;
                SELECT carga_horaria_max * 60 INTO v_max_sem FROM usuarios WHERE id = v_uid;
                SET v_extra = IF(v_min_sem + 480 > v_max_sem, 1, 0);
                SET v_min_extra = IF(v_extra = 1, LEAST(480, v_min_sem + 480 - v_max_sem), 0);
                INSERT INTO asignaciones (usuario_id, sector_id, tipo_turno_id, fecha, inicio, fin, duracion_minutos, es_extra, minutos_extra, creado_por, created_at, updated_at)
                VALUES (v_uid, 4, 12, v_fecha, v_inicio, v_termino, 480, v_extra, v_min_extra, 1, NOW(), NOW());
            END IF;

            -- Noche (tipo 13)
            SET v_uid = 42 + ((v_dia_idx * 2 + 6 + v_offset) % 8);
            SET v_inicio = TIMESTAMP(v_fecha, '22:00:00');
            SET v_termino = TIMESTAMP(DATE_ADD(v_fecha, INTERVAL 1 DAY), '06:00:00');
            IF NOT EXISTS (SELECT 1 FROM asignaciones WHERE usuario_id = v_uid AND inicio < v_termino AND fin > v_inicio) THEN
                SELECT COALESCE(SUM(duracion_minutos), 0) INTO v_min_sem FROM asignaciones WHERE usuario_id = v_uid AND fecha BETWEEN v_lunes AND v_domingo;
                SELECT carga_horaria_max * 60 INTO v_max_sem FROM usuarios WHERE id = v_uid;
                SET v_extra = IF(v_min_sem + 480 > v_max_sem, 1, 0);
                SET v_min_extra = IF(v_extra = 1, LEAST(480, v_min_sem + 480 - v_max_sem), 0);
                INSERT INTO asignaciones (usuario_id, sector_id, tipo_turno_id, fecha, inicio, fin, duracion_minutos, es_extra, minutos_extra, creado_por, created_at, updated_at)
                VALUES (v_uid, 4, 13, v_fecha, v_inicio, v_termino, 480, v_extra, v_min_extra, 1, NOW(), NOW());
            END IF;

            SET v_offset = v_offset + 1;
        END WHILE;

        SET v_fecha   = DATE_ADD(v_fecha, INTERVAL 1 DAY);
        SET v_dia_idx = v_dia_idx + 1;
    END WHILE;
END$$

DELIMITER ;

CALL sp_poblar_turnos_demo();
DROP PROCEDURE sp_poblar_turnos_demo;