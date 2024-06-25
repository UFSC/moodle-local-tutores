<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Version information
 *
 * @package local_tutores
 * @copyright 2016 Roberto Silvino <roberto.silvino@ufsc.br>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_tutores;

use core_plugin_manager;

defined('MOODLE_INTERNAL') || die();

class categoria {

    /**
     * Recupera a categoria do CursoUFSC
     *
     * @param $courseid int Moodle course id
     * @return bool|mixed
     */
    static function curso_ufsc($courseid) {
        global $DB;

        $path_list = trim(self::get_path_category_by_course($courseid));

        // verifica se o sistema de inscrição está instalado
        if (class_exists('local_inscricoes\inscricao_ufsc')) {
            // sistema de inscrição está instalado
            $sql = "SELECT instanceid AS category_id
                      FROM {inscricoes_activities} ia
                      JOIN {context} c
                        ON (ia.contextid = c.id)
                     WHERE (ia.enable = 1)
                       AND (c.contextlevel = :context)
                       AND (c.instanceid IN ($path_list))";

        } else {
            // sistema de inscrição não está instalado
            $sql = "SELECT cc1.id as category_id
                      FROM {course_categories} cc1
                      JOIN {context} c1
                        ON (cc1.id = c1.instanceid)
                     WHERE (c1.contextlevel = :context)
                       AND (cc1.id IN ($path_list))
                       AND (cc1.idnumber like 'curso_%')";
        }

        $params = array('context' => CONTEXT_COURSECAT);
        return $DB->get_field_sql($sql, $params);
    }

    /**
     * Recupera a categoria da turma de um CursoUFSC, que é caracterizada por agrupar relacionamentos de tutores,
     * orientadores
     *
     * @param $courseid int
     * @return bool|mixed
     */
    static function turma_ufsc($courseid) {
        // Se não existir o relationship não faz a consulta
        if (empty(core_plugin_manager::instance()->get_installed_plugins('local')['relationship'])) {
            return false;
        } else {
            global $DB;

            $path_list = trim(self::get_path_category_by_course($courseid));

            $sql = "SELECT DISTINCT ct.instanceid AS category_id
                          FROM {tag} t
                          JOIN {tag_instance} ti
                            ON (t.id = ti.tagid)
                          JOIN {relationship} rl
                            ON (rl.id = ti.itemid)
                          JOIN (SELECT id, instanceid
                                  FROM {context}
                                 WHERE instanceid IN ($path_list)
                                   AND contextlevel = :context1 )  ct
                           ON (ct.id = rl.contextid )
                           JOIN {course} co
                           ON (co.category IN ($path_list)) # = ct.instanceid)
                        WHERE t.name IN ('grupo_orientacao', 'grupo_tutoria')
                        AND ti.itemtype = 'relationship'
                        AND co.id = :courseid";

            $params = array('context1' => CONTEXT_COURSECAT, 'courseid' => $courseid);

            return $DB->get_field_sql($sql, $params);
        }
    }

    /**
     * Monta uma string para ser utilizada na clausula IN de uma SELECT
     * A informação será utilizada para a tabela CONTEXT com o campo "instanceid"
     *
     * @param $courseid Moodle course id
     * @return bool|mixed
     */
    static private function get_path_category_by_course($courseid) {
        global $DB;

        $sql = "SELECT SUBSTRING(REPLACE(cc.path, '/', ', '),2) path_list
			      FROM {course} co
			      JOIN {course_categories} cc
			        ON (co.category = cc.id)
	 		     WHERE co.id = :courseid";

        return $DB->get_field_sql($sql, array('courseid' => $courseid));

    }

    /**
     * Recupera o contextid da categoria da turma, para ser utilizada nos relatórios, no path do context
     * Exemplo: 'AND (context.path like '%/$contexto_turma_ufsc/%' ) AND (context.contextlevel = 70)'
     *
     * @param $categoria_turma int
     * @return bool|mixed
     */
    static function contexto_turma_ufsc($categoria_turma) {
        global $DB;

        $sql = "SELECT id
                  FROM {context}
                 WHERE instanceid = (:categoria_turma)
                   AND contextlevel = :context_level;";
        $params = array('categoria_turma' => $categoria_turma,'context_level' => CONTEXT_COURSECAT);

        $retorno = $DB->get_field_sql($sql, $params);
        return $retorno;
    }

}
