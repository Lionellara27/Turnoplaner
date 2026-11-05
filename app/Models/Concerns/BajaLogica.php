<?php

declare(strict_types=1);

namespace App\Models\Concerns;

/**
 * Baja lógica compartida por los modelos con columnas `estado` (ACTIVO | BAJA) y `deleted_at`.
 * PROHIBIDO EL DELETE FÍSICO: dar de baja marca estado = BAJA y completa deleted_at.
 *
 * Requiere en el modelo: $useSoftDeletes = true y 'estado' en $allowedFields.
 */
trait BajaLogica
{
    /**
     * Da de baja: estado = BAJA + soft delete, las dos escrituras en una transacción.
     */
    public function darDeBajaLogica(int $id): bool
    {
        $this->db->transStart();
        $this->update($id, ['estado' => 'BAJA']);
        $this->delete($id);
        $this->db->transComplete();

        return $this->db->transStatus();
    }

    /**
     * Da de baja solo si no tiene asignaciones futuras, en una transacción que bloquea la fila:
     * una asignación simultánea (que bloquea la misma fila en modo compartido) espera o se rechaza.
     *
     * @return string|null mensaje de error, o null si se dio de baja.
     */
    public function darDeBajaSiLibre(int $id, string $columnaAsignacion): ?string
    {
        $this->db->transBegin();

        try {
            $this->db->query("SELECT id FROM {$this->table} WHERE id = ? FOR UPDATE", [$id]);

            if ($this->tieneAsignacionesFuturas($id, $columnaAsignacion)) {
                $this->db->transRollback();

                return 'tiene turnos asignados desde hoy en adelante. Reasígnelos o elimínelos primero.';
            }

            $this->update($id, ['estado' => 'BAJA']);
            $this->delete($id);
            $this->db->transCommit();

            return null;
        } catch (\Throwable $e) {
            $this->db->transRollback();
            log_message('error', 'No se pudo dar de baja: ' . $e->getMessage());

            return 'no se pudo dar de baja. Intente nuevamente.';
        }
    }

    /**
     * Reactiva una baja. deleted_at no está en $allowedFields, por eso se limpia con el Query Builder.
     */
    public function reactivar(int $id): bool
    {
        return $this->builder()
            ->where($this->primaryKey, $id)
            ->update([
                'estado'     => 'ACTIVO',
                'deleted_at' => null,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
    }

    /**
     * Aplica el filtro de estado de los listados: 'ACTIVO' (por defecto), 'BAJA' o 'TODOS'.
     */
    protected function filtrarPorEstado(string $estado): static
    {
        if ($estado === 'BAJA') {
            $this->onlyDeleted();
        } elseif ($estado === 'TODOS') {
            $this->withDeleted();
        }

        return $this;
    }

    /**
     * Busca un registro incluyendo las bajas lógicas (para editar o reactivar).
     */
    public function buscarIncluyendoBajas(int $id): ?object
    {
        return $this->withDeleted()->find($id);
    }

    /**
     * ¿Existen asignaciones de turno desde hoy en adelante que usen este registro?
     * La tabla `asignaciones` nace con HU-13; mientras no exista, no hay nada que impida la baja.
     */
    public function tieneAsignacionesFuturas(int $id, string $columna): bool
    {
        if (! $this->db->tableExists('asignaciones')) {
            return false;
        }

        $consulta = $this->db->table('asignaciones')
            ->where($columna, $id)
            ->where('fecha >=', date('Y-m-d'));

        if ($this->db->fieldExists('deleted_at', 'asignaciones')) {
            $consulta->where('deleted_at', null);
        }

        return $consulta->countAllResults() > 0;
    }
}
