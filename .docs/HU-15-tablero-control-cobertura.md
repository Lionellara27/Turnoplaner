# HU-15 | Tablero de control de cobertura (Alertas visuales)

**Épica:** ÉPICA E: PLANIFICACIÓN, CUADRÍCULAS Y COBERTURAS (Administrador)  
**Prioridad:** S (Should - Obligatorio Etapa 2 / Final)  
**Puntos de Historia:** 8  

---

## Historia de Usuario

**Como** administrador,  
**quiero** visualizar un tablero general de control que resalte con alertas visuales los sectores y franjas cuya dotación esté por debajo del mínimo requerido  
**para** detectar y resolver descubiertos a tiempo.

---

## Criterios de Aceptación

* **Visión global:** Vista panorámica por semana/día de todos los sectores activos (especialmente áreas críticas: UTI, Guardia, Neonatología).
* **Semáforo de dotación:** Código de colores tipo semáforo por franja: **Verde** (dotación igual o superior al mínimo) y **Rojo / Alerta** (dotación asignada menor a la dotación mínima del sector).
* **Impacto dinámico de ausencias:** Las ausencias reportadas y aprobadas descuentan automáticamente personal disponible de la celda, actualizando la alerta en tiempo real.
* **Navegación contextual:** Al hacer clic sobre una alerta roja, redirige al Planificador (HU-14) o a la carga de reemplazo (HU-16) en ese sector y fecha.

