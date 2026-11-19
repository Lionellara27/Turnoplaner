<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use RuntimeException;

/**
 * Regla E: cada tipo de turno pertenece a un sector (tipos_turno.sector_id).
 *
 * Esquema final igual a database/turnoplaner.sql:
 *   UNIQUE KEY uq_tipos_turno_nombre (sector_id, nombre)
 *   KEY inx_tipos_turno_sector (sector_id)
 *   CONSTRAINT fk_tipos_turno_sector FOREIGN KEY (sector_id) REFERENCES sectores (id)
 *
 * Datos existentes: los tipos que había (globales) quedan en el primer sector y se COPIAN a cada uno de
 * los demás sectores. Cada asignación pasa a apuntar a la copia del tipo de SU sector; si no, una
 * asignación de UTI quedaría con un tipo de turno de Internación y el planificador no la mostraría.
 */
class AddSectorIdToTiposTurno extends Migration
{
    public function up(): void
    {
        if ($this->db->fieldExists('sector_id', 'tipos_turno')) {
            return;
        }

        // Todos los sectores, también los dados de baja: pueden tener asignaciones históricas.
        $sectores = array_map('intval', array_column(
            $this->db->table('sectores')->select('id')->orderBy('id', 'ASC')->get()->getResultArray(),
            'id'
        ));

        $hayTipos = $this->db->table('tipos_turno')->countAllResults() > 0;
        if ($hayTipos && $sectores === []) {
            throw new RuntimeException(
                'No se pueden asociar los tipos de turno a un sector: hay tipos de turno cargados pero ningún sector. '
                . 'Cree al menos un sector y vuelva a ejecutar la migración. No se modificó nada.'
            );
        }

        $primerSectorId = $sectores[0] ?? null;

        // 1) Columna (con DEFAULT temporal para completar las filas existentes) e índices.
        //    Las sentencias DDL hacen COMMIT implícito en MySQL, por eso van antes de la transacción de datos.
        $columna = ['type' => 'INT', 'unsigned' => true, 'null' => false, 'after' => 'id'];
        if ($primerSectorId !== null) {
            $columna['default'] = $primerSectorId;
        }
        $this->forge->addColumn('tipos_turno', ['sector_id' => $columna]);

        $this->db->query('ALTER TABLE tipos_turno MODIFY sector_id INT UNSIGNED NOT NULL');
        $this->db->query('ALTER TABLE tipos_turno DROP INDEX uq_tipos_turno_nombre');
        $this->db->query('ALTER TABLE tipos_turno ADD UNIQUE KEY uq_tipos_turno_nombre (sector_id, nombre)');
        $this->db->query('ALTER TABLE tipos_turno ADD KEY inx_tipos_turno_sector (sector_id)');
        $this->db->query('ALTER TABLE tipos_turno ADD CONSTRAINT fk_tipos_turno_sector FOREIGN KEY (sector_id) REFERENCES sectores (id)');

        if (count($sectores) < 2) {
            return;
        }

        // 2) Copias por sector + reasignación de las asignaciones de cada sector, todo o nada.
        $tiposBase       = $this->db->table('tipos_turno')->where('sector_id', $primerSectorId)->orderBy('id', 'ASC')->get()->getResultArray();
        $hayAsignaciones = $this->db->tableExists('asignaciones');

        $this->db->transException(true)->transStart();

        foreach (array_slice($sectores, 1) as $sectorId) {
            foreach ($tiposBase as $tb) {
                $this->db->table('tipos_turno')->insert([
                    'sector_id'        => $sectorId,
                    'nombre'           => $tb['nombre'],
                    'hora_inicio'      => $tb['hora_inicio'],
                    'hora_fin'         => $tb['hora_fin'],
                    'duracion_minutos' => $tb['duracion_minutos'],
                    'color'            => $tb['color'],
                    'estado'           => $tb['estado'],
                    'created_at'       => $tb['created_at'],
                    'updated_at'       => $tb['updated_at'],
                    'deleted_at'       => $tb['deleted_at'], // un tipo dado de baja sigue de baja en todas las zonas
                ]);
                $copiaId = (int) $this->db->insertID();

                if ($hayAsignaciones) {
                    $this->db->table('asignaciones')
                        ->where('tipo_turno_id', (int) $tb['id'])
                        ->where('sector_id', $sectorId)
                        ->update(['tipo_turno_id' => $copiaId]);
                }
            }
        }

        $this->db->transComplete();
    }

    /**
     * Vuelve al esquema anterior (nombre único global, sin sector_id) SIN borrar filas.
     *
     * Si hay nombres repetidos entre sectores (lo normal después de up()), por cada nombre se conserva
     * el tipo de id más bajo y con las demás copias:
     *  - si alguna copia tiene asignaciones (aunque estén dadas de baja: la FK las sigue referenciando),
     *    se lanza una excepción ANTES de tocar nada, con la lista de nombres: fusionarlas cambiaría el
     *    tipo de turno de esas asignaciones y la tabla no debe quedar a medias;
     *  - si ninguna tiene asignaciones, se dan de baja lógica (estado BAJA + deleted_at) y se renombran
     *    "Nombre #id" para poder restaurar el índice único por nombre.
     */
    public function down(): void
    {
        if (! $this->db->fieldExists('sector_id', 'tipos_turno')) {
            return;
        }

        $tipos = $this->db->table('tipos_turno')->select('id, nombre, deleted_at')->orderBy('id', 'ASC')->get()->getResultArray();

        $vistos = [];
        $copias = [];
        foreach ($tipos as $t) {
            $clave = mb_strtolower((string) $t['nombre']); // la intercalación *_ci compara sin mayúsculas
            if (isset($vistos[$clave])) {
                $copias[] = $t;
            } else {
                $vistos[$clave] = true;
            }
        }

        if ($copias !== [] && $this->db->tableExists('asignaciones')) {
            $conAsignaciones = $this->db->table('asignaciones')
                ->select('tipos_turno.nombre')
                ->distinct()
                ->join('tipos_turno', 'tipos_turno.id = asignaciones.tipo_turno_id')
                ->whereIn('asignaciones.tipo_turno_id', array_map('intval', array_column($copias, 'id')))
                ->get()
                ->getResultArray();

            if ($conAsignaciones !== []) {
                throw new RuntimeException(
                    'No se puede revertir AddSectorIdToTiposTurno: estos tipos de turno existen en varios sectores y sus '
                    . 'copias tienen asignaciones: ' . implode(', ', array_column($conAsignaciones, 'nombre')) . '. '
                    . 'Sin sector_id esas asignaciones quedarían con un tipo ambiguo. Renombre los tipos repetidos '
                    . '(por ejemplo "Mañana UTI") o reasigne esos turnos y vuelva a intentar. No se modificó nada.'
                );
            }
        }

        if ($copias !== []) {
            $this->db->transException(true)->transStart();
            $ahora = date('Y-m-d H:i:s');
            foreach ($copias as $c) {
                $this->db->table('tipos_turno')->where('id', (int) $c['id'])->update([
                    'nombre'     => mb_substr((string) $c['nombre'], 0, 48) . ' #' . $c['id'],
                    'estado'     => 'BAJA',
                    'deleted_at' => $c['deleted_at'] ?? $ahora,
                    'updated_at' => $ahora,
                ]);
            }
            $this->db->transComplete();
        }

        $this->db->query('ALTER TABLE tipos_turno DROP FOREIGN KEY fk_tipos_turno_sector');
        $this->db->query('ALTER TABLE tipos_turno DROP INDEX uq_tipos_turno_nombre');
        $this->db->query('ALTER TABLE tipos_turno DROP INDEX inx_tipos_turno_sector');
        $this->db->query('ALTER TABLE tipos_turno ADD UNIQUE KEY uq_tipos_turno_nombre (nombre)');
        $this->forge->dropColumn('tipos_turno', 'sector_id');
    }
}
