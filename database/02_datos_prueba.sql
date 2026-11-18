SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- Limpieza previa para re-ejecutar sin duplicados
TRUNCATE TABLE asignaciones;
TRUNCATE TABLE tipos_turno;
TRUNCATE TABLE sectores;
TRUNCATE TABLE usuarios;

SET FOREIGN_KEY_CHECKS = 1;

-- Hashes bcrypt válidos para desarrollo:
--   @pass_admin     -> admin123
--   @pass_enfermero -> enfermero123
SET @pass_admin     = '$2y$10$anNSqYPlv1W3q8HSe1E1fORfpZ9wIby4dgNulp1yP6OWEtHsnAa0C';
SET @pass_enfermero = '$2y$10$jXySqRJ1ur32wT/fMe1rP.vxWLoCXxMVtVMELnZYgiuE9PLDbof5u';

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
-- 3. USUARIOS:
--    - 1 Admin (admin@turnoplaner.local / admin123)
--    - 49 Enfermeros/as ACTIVOS ya activados (debe_cambiar_pass = 0):
--        * IDs 2 y 4..41 (39 enfermeros con turnos asignados)
--        * IDs 42..51 (10 enfermeros SIN carga horaria para poder dar de baja)
--    - 7 Enfermeros/as PENDIENTES DE ACTIVACIÓN (ACTIVO + debe_cambiar_pass = 1):
--        * ID 3 (nuevo@turnoplaner.local / enfermero123) + IDs 52..57
--    - 10 Enfermeros/as DADOS DE BAJA (estado = BAJA):
--        * IDs 58..67
-- ==========================================================
INSERT INTO usuarios (id, dni, nombre, apellido, email, telefono, domicilio, password_hash, rol, matricula, especialidad, carga_horaria_max, estado, debe_cambiar_pass, created_at, updated_at, deleted_at) VALUES
-- 3 USUARIOS BASE DE DEMOSTRACIÓN
(1,  '20000000', 'Admin',       'Sistema',     'admin@turnoplaner.local',            '2920000000', NULL,                @pass_admin,     'ADMIN',     NULL,      NULL,                                          NULL, 'ACTIVO', 0, NOW(), NOW(), NULL),
(2,  '30123456', 'María',       'Gómez',       'enfermera@turnoplaner.local',        '2920111111', 'Mitre 123, Viedma', @pass_enfermero, 'ENFERMERO', 'MP-1001', 'Cuidados Críticos / Terapia Intensiva (UTI)', 36,   'ACTIVO', 0, NOW(), NOW(), NULL),
(3,  '31234567', 'Juan',        'Pérez',       'nuevo@turnoplaner.local',            '2920111112', NULL,                @pass_enfermero, 'ENFERMERO', 'MP-1002', 'Guardia y Emergencias',                       40,   'ACTIVO', 1, NOW(), NOW(), NULL),

-- ENFERMEROS/AS ACTIVOS CON GUARDIAS ASIGNADAS (IDs 4 al 41 -> 38 enfermeros + ID 2 = 39 con turnos)
(4,  '30100001', 'Lucía',       'Fernández',   'lucia.fernandez@hospital.local',     '2920200001', 'Belgrano 412',      @pass_enfermero, 'ENFERMERO', 'MP-1003', 'Cuidados Críticos / Terapia Intensiva (UTI)', 36,   'ACTIVO', 0, NOW(), NOW(), NULL),
(5,  '30100002', 'Martín',      'Gómez',       'martin.gomez@hospital.local',        '2920200002', 'San Martín 85',     @pass_enfermero, 'ENFERMERO', 'MP-1004', 'Guardia y Emergencias',                       48,   'ACTIVO', 0, NOW(), NOW(), NULL),
(6,  '30100003', 'Valentina',   'Rodríguez',   'valentina.rodriguez@hospital.local', '2920200003', 'Sarmiento 210',     @pass_enfermero, 'ENFERMERO', 'MP-1005', 'Pediatría y Neonatología',                    36,   'ACTIVO', 0, NOW(), NOW(), NULL),
(7,  '30100004', 'Santiago',    'López',       'santiago.lopez@hospital.local',      '2920200004', 'Rivadavia 640',     @pass_enfermero, 'ENFERMERO', 'MP-1006', 'Cuidados Críticos / Terapia Intensiva (UTI)', 36,   'ACTIVO', 0, NOW(), NOW(), NULL),
(8,  '30100005', 'Camila',      'Martínez',    'camila.martinez@hospital.local',     '2920200005', 'Colón 118',         @pass_enfermero, 'ENFERMERO', 'MP-1007', 'Enfermería General',                          48,   'ACTIVO', 0, NOW(), NOW(), NULL),
(9,  '30100006', 'Mateo',       'Díaz',        'mateo.diaz@hospital.local',          '2920200006', '25 de Mayo 530',    @pass_enfermero, 'ENFERMERO', 'MP-1008', 'Guardia y Emergencias',                       48,   'ACTIVO', 0, NOW(), NOW(), NULL),
(10, '30100007', 'Sofía',       'Pérez',       'sofia.perez@hospital.local',         '2920200007', 'Alvear 390',        @pass_enfermero, 'ENFERMERO', 'MP-1009', 'Cuidados Críticos / Terapia Intensiva (UTI)', 36,   'ACTIVO', 0, NOW(), NOW(), NULL),
(11, '30100008', 'Benjamín',    'Sánchez',     'benjamin.sanchez@hospital.local',    '2920200008', 'Moreno 712',        @pass_enfermero, 'ENFERMERO', 'MP-1010', 'Pediatría y Neonatología',                    36,   'ACTIVO', 0, NOW(), NOW(), NULL),
(12, '30100009', 'Martina',     'Romero',      'martina.romero@hospital.local',      '2920200009', 'Laprida 94',        @pass_enfermero, 'ENFERMERO', 'MP-1011', 'Quirófano / Centro Quirúrgico',               48,   'ACTIVO', 0, NOW(), NOW(), NULL),
(13, '30100010', 'Joaquín',     'Sosa',        'joaquin.sosa@hospital.local',        '2920200010', 'Saavedra 450',      @pass_enfermero, 'ENFERMERO', 'MP-1012', 'Cuidados Críticos / Terapia Intensiva (UTI)', 36,   'ACTIVO', 0, NOW(), NOW(), NULL),
(14, '30100011', 'Catalina',    'Torres',      'catalina.torres@hospital.local',     '2920200011', 'Alem 280',          @pass_enfermero, 'ENFERMERO', 'MP-1013', 'Guardia y Emergencias',                       48,   'ACTIVO', 0, NOW(), NOW(), NULL),
(15, '30100012', 'Thiago',      'Álvarez',     'thiago.alvarez@hospital.local',      '2920200012', 'Pueyrredón 615',    @pass_enfermero, 'ENFERMERO', 'MP-1014', 'Cuidados Críticos / Terapia Intensiva (UTI)', 36,   'ACTIVO', 0, NOW(), NOW(), NULL),
(16, '30100013', 'Julieta',     'Ruiz',        'julieta.ruiz@hospital.local',        '2920200013', 'Dorrego 332',       @pass_enfermero, 'ENFERMERO', 'MP-1015', 'Pediatría y Neonatología',                    36,   'ACTIVO', 0, NOW(), NOW(), NULL),
(17, '30100014', 'Bautista',    'Ramírez',     'bautista.ramirez@hospital.local',    '2920200014', 'Urquiza 199',       @pass_enfermero, 'ENFERMERO', 'MP-1016', 'Enfermería General',                          48,   'ACTIVO', 0, NOW(), NOW(), NULL),
(18, '30100015', 'Victoria',    'Flores',      'victoria.flores@hospital.local',     '2920200015', 'Güemes 508',        @pass_enfermero, 'ENFERMERO', 'MP-1017', 'Guardia y Emergencias',                       48,   'ACTIVO', 0, NOW(), NOW(), NULL),
(19, '30100016', 'Lautaro',     'Acosta',      'lautaro.acosta@hospital.local',      '2920200016', 'Mitre 740',         @pass_enfermero, 'ENFERMERO', 'MP-1018', 'Cuidados Críticos / Terapia Intensiva (UTI)', 36,   'ACTIVO', 0, NOW(), NOW(), NULL),
(20, '30100017', 'Emilia',      'Benítez',     'emilia.benitez@hospital.local',      '2920200017', 'Brown 120',         @pass_enfermero, 'ENFERMERO', 'MP-1019', 'Pediatría y Neonatología',                    36,   'ACTIVO', 0, NOW(), NOW(), NULL),
(21, '30100018', 'Tomás',       'Medina',      'tomas.medina@hospital.local',        '2920200018', 'Zatti 890',         @pass_enfermero, 'ENFERMERO', 'MP-1020', 'Enfermería General',                          48,   'ACTIVO', 0, NOW(), NOW(), NULL),
(22, '30100019', 'Delfina',     'Herrera',     'delfina.herrera@hospital.local',     '2920200019', 'Garrone 440',       @pass_enfermero, 'ENFERMERO', 'MP-1021', 'Cuidados Críticos / Terapia Intensiva (UTI)', 36,   'ACTIVO', 0, NOW(), NOW(), NULL),
(23, '30100020', 'Ignacio',     'Suárez',      'ignacio.suarez@hospital.local',      '2920200020', 'Schieroni 310',     @pass_enfermero, 'ENFERMERO', 'MP-1022', 'Guardia y Emergencias',                       48,   'ACTIVO', 0, NOW(), NOW(), NULL),
(24, '30100021', 'Renata',      'Aguirre',     'renata.aguirre@hospital.local',      '2920200021', 'Gallardo 215',      @pass_enfermero, 'ENFERMERO', 'MP-1023', 'Pediatría y Neonatología',                    36,   'ACTIVO', 0, NOW(), NOW(), NULL),
(25, '30100022', 'Santino',     'Giménez',     'santino.gimenez@hospital.local',     '2920200022', 'Guido 602',         @pass_enfermero, 'ENFERMERO', 'MP-1024', 'Cuidados Críticos / Terapia Intensiva (UTI)', 36,   'ACTIVO', 0, NOW(), NOW(), NULL),
(26, '30100023', 'Olivia',      'Gutiérrez',   'olivia.gutierrez@hospital.local',    '2920200023', 'Caseros 145',       @pass_enfermero, 'ENFERMERO', 'MP-1025', 'Quirófano / Centro Quirúrgico',               48,   'ACTIVO', 0, NOW(), NOW(), NULL),
(27, '30100024', 'Felipe',      'Pereyra',     'felipe.pereyra@hospital.local',      '2920200024', 'Alberdi 520',       @pass_enfermero, 'ENFERMERO', 'MP-1026', 'Guardia y Emergencias',                       48,   'ACTIVO', 0, NOW(), NOW(), NULL),
(28, '30100025', 'Alma',        'Rojas',       'alma.rojas@hospital.local',          '2920200025', 'Pellegrini 330',    @pass_enfermero, 'ENFERMERO', 'MP-1027', 'Cuidados Críticos / Terapia Intensiva (UTI)', 36,   'ACTIVO', 0, NOW(), NOW(), NULL),
(29, '30100026', 'Lucas',       'Molina',      'lucas.molina@hospital.local',        '2920200026', ' Roca 780',         @pass_enfermero, 'ENFERMERO', 'MP-1028', 'Pediatría y Neonatología',                    36,   'ACTIVO', 0, NOW(), NOW(), NULL),
(30, '30100027', 'Mía',         'Castro',      'mia.castro@hospital.local',          '2920200027', 'Perón 410',         @pass_enfermero, 'ENFERMERO', 'MP-1029', 'Enfermería General',                          48,   'ACTIVO', 0, NOW(), NOW(), NULL),
(31, '30100028', 'Nicolás',     'Ortiz',       'nicolas.ortiz@hospital.local',       '2920200028', 'O Higgins 260',     @pass_enfermero, 'ENFERMERO', 'MP-1030', 'Cuidados Críticos / Terapia Intensiva (UTI)', 36,   'ACTIVO', 0, NOW(), NOW(), NULL),
(32, '30100029', 'Clara',       'Silva',       'clara.silva@hospital.local',         '2920200029', 'Las Heras 175',     @pass_enfermero, 'ENFERMERO', 'MP-1031', 'Guardia y Emergencias',                       48,   'ACTIVO', 0, NOW(), NOW(), NULL),
(33, '30100030', 'Agustín',     'Núñez',       'agustin.nunez@hospital.local',       '2920200030', 'Balcarce 630',      @pass_enfermero, 'ENFERMERO', 'MP-1032', 'Cuidados Críticos / Terapia Intensiva (UTI)', 36,   'ACTIVO', 0, NOW(), NOW(), NULL),
(34, '30100031', 'Elena',       'Luna',        'elena.luna@hospital.local',          '2920200031', 'Chacabuco 490',     @pass_enfermero, 'ENFERMERO', 'MP-1033', 'Pediatría y Neonatología',                    36,   'ACTIVO', 0, NOW(), NOW(), NULL),
(35, '30100032', 'Facundo',     'Juárez',      'facundo.juarez@hospital.local',      '2920200032', 'Maipú 305',         @pass_enfermero, 'ENFERMERO', 'MP-1034', 'Enfermería General',                          48,   'ACTIVO', 0, NOW(), NOW(), NULL),
(36, '30100033', 'Abril',       'Cabrera',     'abril.cabrera@hospital.local',       '2920200033', 'Tucumán 812',       @pass_enfermero, 'ENFERMERO', 'MP-1035', 'Guardia y Emergencias',                       48,   'ACTIVO', 0, NOW(), NOW(), NULL),
(37, '30100034', 'Franco',      'Ríos',        'franco.rios@hospital.local',         '2920200034', 'Salta 154',         @pass_enfermero, 'ENFERMERO', 'MP-1036', 'Cuidados Críticos / Terapia Intensiva (UTI)', 36,   'ACTIVO', 0, NOW(), NOW(), NULL),
(38, '30100035', 'Pilar',       'Morales',     'pilar.morales@hospital.local',       '2920200035', 'Mendoza 422',       @pass_enfermero, 'ENFERMERO', 'MP-1037', 'Pediatría y Neonatología',                    36,   'ACTIVO', 0, NOW(), NOW(), NULL),
(39, '30100036', 'Ramiro',      'Domínguez',   'ramiro.dominguez@hospital.local',    '2920200036', 'Córdoba 670',       @pass_enfermero, 'ENFERMERO', 'MP-1038', 'Quirófano / Centro Quirúrgico',               48,   'ACTIVO', 0, NOW(), NOW(), NULL),
(40, '30100037', 'Juana',       'Vega',        'juana.vega@hospital.local',          '2920200037', 'Santa Fe 290',      @pass_enfermero, 'ENFERMERO', 'MP-1039', 'Cuidados Críticos / Terapia Intensiva (UTI)', 36,   'ACTIVO', 0, NOW(), NOW(), NULL),
(41, '30100038', 'Gonzalo',     'Reyes',       'gonzalo.reyes@hospital.local',       '2920200038', 'Entre Ríos 515',    @pass_enfermero, 'ENFERMERO', 'MP-1040', 'Guardia y Emergencias',                       48,   'ACTIVO', 0, NOW(), NOW(), NULL),

-- 10 ENFERMEROS/AS ACTIVOS SIN CARGA HORARIA CUBIERTA (IDs 42 al 51 -> listos para dar de baja en demo)
(42, '30100039', 'Malena',      'Campos',      'malena.campos@hospital.local',       '2920200039', 'Corrientes 188',    @pass_enfermero, 'ENFERMERO', 'MP-1041', 'Oncología',                                   36,   'ACTIVO', 0, NOW(), NOW(), NULL),
(43, '30100040', 'Bruno',       'Cardozo',     'bruno.cardozo@hospital.local',       '2920200040', 'Neuquén 340',       @pass_enfermero, 'ENFERMERO', 'MP-1042', 'Pediatría y Neonatología',                    36,   'ACTIVO', 0, NOW(), NOW(), NULL),
(44, '30100041', 'Lola',        'Vargas',      'lola.vargas@hospital.local',         '2920200041', 'Chubut 720',        @pass_enfermero, 'ENFERMERO', 'MP-1043', 'Enfermería General',                          48,   'ACTIVO', 0, NOW(), NOW(), NULL),
(45, '30100042', 'Ezequiel',    'Méndez',      'ezequiel.mendez@hospital.local',     '2920200042', 'Santa Cruz 95',     @pass_enfermero, 'ENFERMERO', 'MP-1044', 'Guardia y Emergencias',                       48,   'ACTIVO', 0, NOW(), NOW(), NULL),
(46, '30100043', 'Bianca',      'Ponce',       'bianca.ponce@hospital.local',        '2920200043', 'La Pampa 460',      @pass_enfermero, 'ENFERMERO', 'MP-1045', 'Salud Mental',                                36,   'ACTIVO', 0, NOW(), NOW(), NULL),
(47, '30100044', 'Maximiliano', 'Soria',       'maximiliano.soria@hospital.local',   '2920200044', 'Formosa 210',       @pass_enfermero, 'ENFERMERO', 'MP-1046', 'Cuidados Críticos / Terapia Intensiva (UTI)', 36,   'ACTIVO', 0, NOW(), NOW(), NULL),
(48, '30100045', 'Zoe',         'Ibarra',      'zoe.ibarra@hospital.local',          '2920200045', 'Misiones 530',      @pass_enfermero, 'ENFERMERO', 'MP-1047', 'Pediatría y Neonatología',                    36,   'ACTIVO', 0, NOW(), NOW(), NULL),
(49, '30100046', 'Nahuel',      'Peralta',     'nahuel.peralta@hospital.local',      '2920200046', 'Jujuy 114',         @pass_enfermero, 'ENFERMERO', 'MP-1048', 'Enfermería General',                          48,   'ACTIVO', 0, NOW(), NOW(), NULL),
(50, '30100047', 'Candelaria',  'Quiroga',     'candelaria.quiroga@hospital.local',  '2920200047', 'Catamarca 680',     @pass_enfermero, 'ENFERMERO', 'MP-1049', 'Guardia y Emergencias',                       48,   'ACTIVO', 0, NOW(), NOW(), NULL),
(51, '30100048', 'Sebastián',   'Miranda',     'sebastian.miranda@hospital.local',   '2920200048', 'La Rioja 395',      @pass_enfermero, 'ENFERMERO', 'MP-1050', 'Oncología',                                   36,   'ACTIVO', 0, NOW(), NOW(), NULL),

-- 6 PENDIENTES DE ACTIVACIÓN ADICIONALES (IDs 52 al 57 + ID 3 = 7 pendientes en total)
(52, '30100049', 'Josefina',    'Vera',        'josefina.vera@hospital.local',       '2920200049', NULL,                @pass_enfermero, 'ENFERMERO', 'MP-1051', 'Quirófano / Centro Quirúrgico',               48,   'ACTIVO', 1, NOW(), NOW(), NULL),
(53, '30100050', 'Leandro',     'Navarro',     'leandro.navarro@hospital.local',     '2920200050', NULL,                @pass_enfermero, 'ENFERMERO', 'MP-1052', 'Guardia y Emergencias',                       48,   'ACTIVO', 1, NOW(), NOW(), NULL),
(54, '30100051', 'Antonella',   'Paz',         'antonella.paz@hospital.local',       '2920200051', NULL,                @pass_enfermero, 'ENFERMERO', 'MP-1053', 'Pediatría y Neonatología',                    36,   'ACTIVO', 1, NOW(), NOW(), NULL),
(55, '30100052', 'Matías',      'Godoy',       'matias.godoy@hospital.local',        '2920200052', NULL,                @pass_enfermero, 'ENFERMERO', 'MP-1054', 'Cuidados Críticos / Terapia Intensiva (UTI)', 36,   'ACTIVO', 1, NOW(), NOW(), NULL),
(56, '30100053', 'Florencia',   'Bravo',       'florencia.bravo@hospital.local',     '2920200053', NULL,                @pass_enfermero, 'ENFERMERO', 'MP-1055', 'Enfermería General',                          48,   'ACTIVO', 1, NOW(), NOW(), NULL),
(57, '30100054', 'Damián',      'Correa',      'damian.correa@hospital.local',       '2920200054', NULL,                @pass_enfermero, 'ENFERMERO', 'MP-1056', 'Salud Mental',                                36,   'ACTIVO', 1, NOW(), NOW(), NULL),

-- 10 ENFERMEROS/AS DADOS DE BAJA (IDs 58 al 67)
(58, '30100055', 'Rodrigo',     'Ferreyra',    'rodrigo.ferreyra@hospital.local',    '2920200055', 'San Luis 240',      @pass_enfermero, 'ENFERMERO', 'MP-1057', 'Guardia y Emergencias',                       48,   'BAJA',   0, NOW(), NOW(), NOW()),
(59, '30100056', 'Valeria',     'Benavidez',   'valeria.benavidez@hospital.local',   '2920200056', 'San Juan 510',      @pass_enfermero, 'ENFERMERO', 'MP-1058', 'Enfermería General',                          36,   'BAJA',   0, NOW(), NOW(), NOW()),
(60, '30100057', 'Cristian',    'Maidana',     'cristian.maidana@hospital.local',    '2920200057', 'Chaco 325',         @pass_enfermero, 'ENFERMERO', 'MP-1059', 'Cuidados Críticos / Terapia Intensiva (UTI)', 48,   'BAJA',   0, NOW(), NOW(), NOW()),
(61, '30100058', 'Gabriela',    'Bustamante',  'gabriela.bustamante@hospital.local', '2920200058', 'Santiago 140',      @pass_enfermero, 'ENFERMERO', 'MP-1060', 'Pediatría y Neonatología',                    36,   'BAJA',   0, NOW(), NOW(), NOW()),
(62, '30100059', 'Hernán',      'Cáceres',     'hernan.caceres@hospital.local',      '2920200059', 'Lavalle 480',       @pass_enfermero, 'ENFERMERO', 'MP-1061', 'Quirófano / Centro Quirúrgico',               48,   'BAJA',   0, NOW(), NOW(), NOW()),
(63, '30100060', 'Natalia',     'Roldán',      'natalia.roldan@hospital.local',      '2920200060', 'Castelli 610',      @pass_enfermero, 'ENFERMERO', 'MP-1062', 'Oncología',                                   36,   'BAJA',   0, NOW(), NOW(), NULL),
(64, '30100061', 'Esteban',     'Villalba',    'esteban.villalba@hospital.local',    '2920200061', 'Paso 190',          @pass_enfermero, 'ENFERMERO', 'MP-1063', 'Guardia y Emergencias',                       48,   'BAJA',   0, NOW(), NOW(), NOW()),
(65, '30100062', 'Romina',      'Salazar',     'romina.salazar@hospital.local',      '2920200062', 'Matheu 370',        @pass_enfermero, 'ENFERMERO', 'MP-1064', 'Enfermería General',                          36,   'BAJA',   0, NOW(), NOW(), NOW()),
(66, '30100063', 'Federico',    'Ojeda',       'federico.ojeda@hospital.local',      '2920200063', 'Azcuénaga 540',     @pass_enfermero, 'ENFERMERO', 'MP-1065', 'Salud Mental',                                36,   'BAJA',   0, NOW(), NOW(), NOW()),
(67, '30100064', 'Carina',      'Escobar',     'carina.escobar@hospital.local',      '2920200064', 'Larrea 280',        @pass_enfermero, 'ENFERMERO', 'MP-1066', 'Pediatría y Neonatología',                    48,   'BAJA',   0, NOW(), NOW(), NOW());

-- ==========================================================
-- 4. POBLADO REALISTA DE TURNOS (15 Nov 2026 al 31 Dic 2026)
--    Usa solo los enfermeros activos IDs 2 y 4..41 (39 personas).
--    Los IDs 42..51 quedan con 0 h asignadas para poder dar de baja.
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
        -- A) GUARDIA (Sector 1, IDs enfermeros 4..17, turnos 8h)
        -- ------------------------------------------------------
        SET v_offset = 0;
        WHILE v_offset < 3 DO
            SET v_uid = 4 + ((v_dia_idx * 3 + v_offset) % 14);
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

        SET v_offset = 0;
        WHILE v_offset < IF(v_dia_idx % 4 = 0, 2, 3) DO
            SET v_uid = 4 + ((v_dia_idx * 3 + 5 + v_offset) % 14);
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

        SET v_offset = 0;
        WHILE v_offset < 3 DO
            SET v_uid = 4 + ((v_dia_idx * 3 + 10 + v_offset) % 14);
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
        -- B) NEONATOLOGÍA (Sector 2, IDs enfermeros 18..26, 8h)
        -- ------------------------------------------------------
        SET v_offset = 0;
        WHILE v_offset < 2 DO
            SET v_uid = 18 + ((v_dia_idx * 2 + v_offset) % 9);
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

            SET v_uid = 18 + ((v_dia_idx * 2 + 3 + v_offset) % 9);
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

            SET v_uid = 18 + ((v_dia_idx * 2 + 6 + v_offset) % 9);
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
        -- C) UTI (Sector 3, ID 2 enfermera@turnoplaner.local + IDs 27..35 -> 10 personas, 4 turnos x 6h)
        -- ------------------------------------------------------
        IF v_fecha <> '2026-11-20' THEN
            SET v_offset = 0;
            WHILE v_offset < 3 DO
                SET v_uid = IF(((v_dia_idx * 3 + v_offset) % 10) = 0, 2, 26 + ((v_dia_idx * 3 + v_offset) % 10));
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

        SET v_offset = 0;
        WHILE v_offset < 3 DO
            SET v_uid = IF(((v_dia_idx * 3 + 3 + v_offset) % 10) = 0, 2, 26 + ((v_dia_idx * 3 + 3 + v_offset) % 10));
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

        SET v_offset = 0;
        WHILE v_offset < IF(v_dia_idx % 5 = 2, 2, 3) DO
            SET v_uid = IF(((v_dia_idx * 3 + 6 + v_offset) % 10) = 0, 2, 26 + ((v_dia_idx * 3 + 6 + v_offset) % 10));
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

        SET v_offset = 0;
        WHILE v_offset < 3 DO
            SET v_uid = IF(((v_dia_idx * 3 + 8 + v_offset) % 10) = 0, 2, 26 + ((v_dia_idx * 3 + 8 + v_offset) % 10));
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
        -- D) INTERNACIÓN GENERAL (Sector 4, IDs 36..41 -> 6 personas, 8h)
        -- ------------------------------------------------------
        SET v_offset = 0;
        WHILE v_offset < 2 DO
            SET v_uid = 36 + ((v_dia_idx * 2 + v_offset) % 6);
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

            SET v_uid = 36 + ((v_dia_idx * 2 + 2 + v_offset) % 6);
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

            SET v_uid = 36 + ((v_dia_idx * 2 + 4 + v_offset) % 6);
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