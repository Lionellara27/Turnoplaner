# Estándares de Base de Datos MySQL y CodeIgniter 4

Esta guía define las convenciones de diseño de base de datos, tipos de datos, integridad referencial y el uso obligatorio de **baja lógica (*Soft Deletes*)** en **MySQL (InnoDB)** con **CodeIgniter 4**.

---

## 1. Convenciones de Nomenclatura (*Naming Conventions*)

1. **Tablas:**
   - En minúsculas y formato `snake_case`.
   - Nombres en **plural** representando colecciones de entidades: `usuarios`, `sectores`, `tipos_turno`, `dotacion_minima`, `asignaciones`, `ausencias`, `coberturas`, `marcaciones`.
2. **Columnas:**
   - En minúsculas y formato `snake_case`: `nombre`, `apellido`, `carga_horaria_max`, `hora_inicio`, `debe_cambiar_pass`.
   - Nombres claros y autoexplicativos; evitar abreviaturas oscuras.
3. **Claves Primarias (PK):**
   - Siempre denominadas simplemente `id`.
   - Tipo: `INT UNSIGNED AUTO_INCREMENT PRIMARY KEY` o `BIGINT UNSIGNED`.
4. **Claves Foráneas (FK):**
   - Formato: `<nombre_tabla_en_singular>_id`.
   - Ejemplos: `sector_id`, `tipo_turno_id`, `usuario_id`, `asignacion_id`, `ausencia_id`.
   - Si la relación es con un rol específico del mismo catálogo, usar prefijo descriptivo: `creado_por`, `confirmado_por`, `usuario_reemplazo_id`.
5. **Columnas de Auditoría:**
   - `created_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP`
   - `updated_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP`
   - `deleted_at DATETIME NULL DEFAULT NULL`

---

## 2. Tipos de Datos y Precisión

| Dato | Tipo MySQL Recomendado | Justificación |
| :--- | :--- | :--- |
| Identificador PK / FK | `INT UNSIGNED` | Consistencia y compatibilidad con índices numéricos rápidos. |
| Booleano / Flags | `TINYINT(1)` | Indicadores lógicos (`es_critico`, `debe_cambiar_pass`). |
| Documento / Matrícula | `VARCHAR(20)` | Soporta formatos alfanuméricos o identificadores con ceros a la izquierda. |
| Correo Electrónico | `VARCHAR(150)` | Longitud suficiente para estándares RFC y clave única. |
| Contraseña Hashed | `VARCHAR(255)` | Longitud necesaria para hashes de `password_hash()` (BCRYPT / Argon2). |
| Estado Operativo | `VARCHAR(20)` o `ENUM` | Valores controlados: `'ACTIVO'`, `'BAJA'`, `'PENDIENTE'`. |
| Fecha de Calendario | `DATE` | Fechas de planificación de guardia (`YYYY-MM-DD`). |
| Horario de Turno | `TIME` | Franja de inicio y fin (`HH:MM:SS`), ej: `06:00:00`, `14:00:00`. |
| Horas / Duración | `INT UNSIGNED` o `DECIMAL(4,2)` | Horas acumuladas y duración (`duracion_hs`, `carga_horaria_max`). |
| Color Hexadecimal | `VARCHAR(7)` | Identificador visual para grillas, ej: `#43ab92`. |

---

## 3. Uso Exclusivo de Baja Lógica (*Soft Deletes*)

### Regla de Oro:
> **PROHIBICIÓN TOTAL DE SENTENCIAS FÍSICAS `DELETE FROM`**  
> Para preservar la integridad histórica de asignaciones, guardias y estadísticas de salud, nunca se elimina ningún registro físico de usuarios, catálogos ni configuraciones.

### Configuración en Modelos de CodeIgniter 4:

```php
protected $useSoftDeletes = true;
protected $deletedField   = 'deleted_at';
```

### Comportamiento del Framework:
1. **Borrado Lógico Automático:**  
   Al ejecutar `$modelo->delete($id)`, CodeIgniter 4 ejecuta internamente:
   ```sql
   UPDATE tabla SET deleted_at = NOW() WHERE id = ?
   ```
2. **Filtrado Automático:**  
   Todas las consultas automáticas (`find()`, `findAll()`, `first()`) agregan de manera transparente la condición `WHERE deleted_at IS NULL`.
3. **Consulta de Historial o Registros Dados de Baja:**
   - Incluir dados de baja: `$modelo->withDeleted()->findAll();`
   - Consultar únicamente dados de baja: `$modelo->onlyDeleted()->findAll();`
4. **Sincronización con Columna `estado`:**  
   Para mantener consistencia con los requerimientos del negocio (vistas de ABMC), la baja lógica debe actualizar tanto `deleted_at` como `estado = 'BAJA'`:

```php
public function darDeBajaLogica(int $id): bool
{
    // Actualiza estado semántico y aplica soft delete
    $this->update($id, ['estado' => 'BAJA']);
    return $this->delete($id);
}

public function reactivar(int $id): bool
{
    // Restaura registro eliminado
    return $this->update($id, [
        'estado'     => 'ACTIVO',
        'deleted_at' => null,
    ]);
}
```

---

## 4. Integridad Referencial y Restricciones (FK)

1. **Motor Obligatorio:** Todas las tablas deben crearse con el motor **`InnoDB`** para soporte de transacciones y llaves foráneas.
2. **Juegos de Caracteres:** `CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci`.
3. **Restricciones de Integridad (`FOREIGN KEY`):**
   - `ON DELETE RESTRICT`: Comportamiento por defecto para relaciones maestras (ej. no permitir dar de baja un `tipo_turno` si existen `asignaciones` vigentes ligadas).
   - En CodeIgniter 4 con Soft Deletes, la foreign key protege la consistencia física, mientras que el modelo valida la no asignación de entidades con `deleted_at IS NOT NULL`.

---

## 5. Esquema Relacional de Referencia (DDL)

```sql
-- 1. Tabla de Usuarios (Administradores y Enfermeros)
CREATE TABLE IF NOT EXISTS `usuarios` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `nombre` VARCHAR(100) NOT NULL,
    `apellido` VARCHAR(100) NOT NULL,
    `dni` VARCHAR(20) NOT NULL UNIQUE,
    `matricula` VARCHAR(20) NOT NULL UNIQUE,
    `mail` VARCHAR(150) NOT NULL UNIQUE,
    `telefono` VARCHAR(30) NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `rol` ENUM('ADMIN', 'ENFERMERO') NOT NULL DEFAULT 'ENFERMERO',
    `especialidad` VARCHAR(100) NULL,
    `carga_horaria_max` INT UNSIGNED NOT NULL DEFAULT 40,
    `estado` ENUM('PENDIENTE', 'ACTIVO', 'BAJA') NOT NULL DEFAULT 'ACTIVO',
    `debe_cambiar_pass` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME NULL DEFAULT NULL,
    INDEX `idx_usuarios_estado` (`estado`),
    INDEX `idx_usuarios_rol` (`rol`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Tabla de Sectores Hospitalarios
CREATE TABLE IF NOT EXISTS `sectores` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `nombre` VARCHAR(100) NOT NULL UNIQUE,
    `descripcion` TEXT NULL,
    `es_critico` TINYINT(1) NOT NULL DEFAULT 0,
    `dotacion_minima_general` INT UNSIGNED NOT NULL DEFAULT 1,
    `estado` ENUM('ACTIVO', 'BAJA') NOT NULL DEFAULT 'ACTIVO',
    `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME NULL DEFAULT NULL,
    INDEX `idx_sectores_estado` (`estado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Tabla de Tipos de Turno
CREATE TABLE IF NOT EXISTS `tipos_turno` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `nombre` VARCHAR(50) NOT NULL UNIQUE,
    `hora_inicio` TIME NOT NULL,
    `hora_fin` TIME NOT NULL,
    `duracion_hs` INT UNSIGNED NOT NULL,
    `color` VARCHAR(7) NOT NULL DEFAULT '#43ab92',
    `estado` ENUM('ACTIVO', 'BAJA') NOT NULL DEFAULT 'ACTIVO',
    `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME NULL DEFAULT NULL,
    INDEX `idx_tipos_turno_estado` (`estado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Dotación Mínima Específica por Turno (Opcional / Extensible)
CREATE TABLE IF NOT EXISTS `dotacion_minima` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `sector_id` INT UNSIGNED NOT NULL,
    `tipo_turno_id` INT UNSIGNED NOT NULL,
    `cantidad_minima` INT UNSIGNED NOT NULL DEFAULT 1,
    CONSTRAINT `fk_dotacion_sector` FOREIGN KEY (`sector_id`) REFERENCES `sectores`(`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_dotacion_turno` FOREIGN KEY (`tipo_turno_id`) REFERENCES `tipos_turno`(`id`) ON DELETE RESTRICT,
    UNIQUE KEY `uk_sector_turno` (`sector_id`, `tipo_turno_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Tabla de Asignaciones (Cuadrícula y Cronogramas)
CREATE TABLE IF NOT EXISTS `asignaciones` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `usuario_id` INT UNSIGNED NOT NULL,
    `sector_id` INT UNSIGNED NOT NULL,
    `tipo_turno_id` INT UNSIGNED NOT NULL,
    `fecha` DATE NOT NULL,
    `estado` ENUM('ASIGNADO', 'AUSENTE', 'CUBIERTO', 'CUMPLIDO') NOT NULL DEFAULT 'ASIGNADO',
    `creado_por` INT UNSIGNED NULL,
    `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME NULL DEFAULT NULL,
    CONSTRAINT `fk_asig_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios`(`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_asig_sector` FOREIGN KEY (`sector_id`) REFERENCES `sectores`(`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_asig_turno` FOREIGN KEY (`tipo_turno_id`) REFERENCES `tipos_turno`(`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_asig_creador` FOREIGN KEY (`creado_por`) REFERENCES `usuarios`(`id`) ON DELETE SET NULL,
    INDEX `idx_asig_fecha_sector` (`fecha`, `sector_id`),
    INDEX `idx_asig_usuario_fecha` (`usuario_id`, `fecha`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Tabla de Ausencias
CREATE TABLE IF NOT EXISTS `ausencias` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `asignacion_id` INT UNSIGNED NOT NULL,
    `motivo` VARCHAR(100) NOT NULL,
    `observacion` TEXT NULL,
    `estado` ENUM('PENDIENTE', 'APROBADA', 'RECHAZADA', 'CUBIERTA') NOT NULL DEFAULT 'PENDIENTE',
    `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME NULL DEFAULT NULL,
    CONSTRAINT `fk_ausencia_asig` FOREIGN KEY (`asignacion_id`) REFERENCES `asignaciones`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Tabla de Coberturas de Reemplazo
CREATE TABLE IF NOT EXISTS `coberturas` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `ausencia_id` INT UNSIGNED NOT NULL,
    `usuario_reemplazo_id` INT UNSIGNED NOT NULL,
    `confirmado_por` INT UNSIGNED NOT NULL,
    `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME NULL DEFAULT NULL,
    CONSTRAINT `fk_cob_ausencia` FOREIGN KEY (`ausencia_id`) REFERENCES `ausencias`(`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_cob_reemplazo` FOREIGN KEY (`usuario_reemplazo_id`) REFERENCES `usuarios`(`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_cob_admin` FOREIGN KEY (`confirmado_por`) REFERENCES `usuarios`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

