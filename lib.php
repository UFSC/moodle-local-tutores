<?php

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/user/selector/lib.php');
require_once($CFG->dirroot.'/local/tutores/middlewarelib.php');

define('GRUPO_TUTORIA_TIPO_ESTUDANTE', 'E');
define('GRUPO_TUTORIA_TIPO_TUTOR', 'T');
define('GRUPO_ORIENTACAO_TIPO_ORIENTADOR', 'O');

function local_tutores_extends_settings_navigation(navigation_node $navigation) {
    global $PAGE;

    if (is_a($PAGE->context, 'context_coursecat') && has_capability('local/tutores:manage', $PAGE->context)) {
        $category_node = $navigation->get('categorysettings');

        // Se por algum motivo a chave for alterada em uma nova versão do Moodle,
        // Não quebrar o código.
        if ($category_node) {
            $category_node->add(
                    get_string('tutorship_groups', 'local_tutores'),
                    new moodle_url('/local/tutores/index.php', array('categoryid' => $PAGE->context->instanceid)),
                    navigation_node::TYPE_SETTING, null, null, new pix_icon('icon', '', 'local_tutores'));
        }
    }
}

class grupos_tutoria {

    /**
     * Retorna os papéis que estão sendo considerados como estudantes
     *
     * @static
     * @return array
     */
    static function get_papeis_estudantes() {
        global $CFG;

        return explode(',', $CFG->local_tutores_student_roles);
    }

    /**
     * Retorna os papéis que estão sendo considerados como tutores
     *
     * @static
     * @return array
     */
    static function get_papeis_tutores() {
        global $CFG;

        return explode(',', $CFG->local_tutores_tutor_roles);
    }

    /**
     * Retorna lista orientadores de um determinado curso ufsc
     * FIXME: orientadores não estão sendo usados na filtragem
     *
     * @param string $curso_ufsc
     * @param null $orientadores
     * @return array
     */
    static function get_grupos_orientacao($curso_ufsc, $orientadores = null) {
        $middleware = Middleware::singleton();

        $sql = "SELECT DISTINCT u.id, ao.username_orientador, u.firstname
                  FROM {view_Alunos_Orientadores} ao
                  JOIN {user} u
                    ON (ao.username_orientador=u.username)
              ORDER BY u.firstname
                ";

        return $middleware->get_records_sql($sql, array('curso_ufsc' => $curso_ufsc, 'tipo' => GRUPO_ORIENTACAO_TIPO_ORIENTADOR));
    }

    /**
     * Retorna o tutor responsável em um curso_ufsc por um estudante
     *
     * @param string $curso_ufsc
     * @param $student_userid
     * @return bool|mixed
     */
    static function get_tutor_responsavel_estudante($curso_ufsc, $student_userid) {
        global $DB;

        $relationship = self::get_relationship_tutoria($curso_ufsc);
        $cohort_estudantes = self::get_relationship_cohort_estudantes($relationship->id);
        $cohort_tutores = self::get_relationship_cohort_tutores($relationship->id);

        $sql = "SELECT DISTINCT u.id, u.username, CONCAT(firstname,' ',lastname) AS fullname
                  FROM {user} u
                  JOIN {relationship_members} rm
                    ON (rm.userid=u.id AND rm.relationshipcohortid=:cohort_tutores)
                  JOIN {relationship_groups} rg
                    ON (rg.relationshipid=:relationship_id1 AND rg.id=rm.relationshipgroupid)
                  JOIN (
                          SELECT DISTINCT rg.*
                            FROM {user} u
                            JOIN {relationship_members} rm
                              ON (rm.userid=u.id AND rm.relationshipcohortid=:cohort_estudantes)
                            JOIN {relationship_groups} rg
                              ON (rg.relationshipid=:relationship_id2 AND rg.id=rm.relationshipgroupid)
                           WHERE u.id=:estudante
                       ) grupo_estudante
                    ON (rg.id=grupo_estudante.id)";

        $params = array(
                'relationship_id1' => $relationship->id,
                'relationship_id2' => $relationship->id,
                'cohort_estudantes' => $cohort_estudantes->id,
                'cohort_tutores' => $cohort_tutores->id,
                'estudante' => $student_userid);

        return $DB->get_record_sql($sql, $params);
    }

    /**
     * Listagem de estudantes que participam de um curso UFSC e estão relacionados a um tutor
     *
     * @param $curso_ufsc
     * @return array [id][fullname]
     */
    static function get_estudantes_curso_ufsc($curso_ufsc) {
        global $DB;

        $relationship = self::get_relationship_tutoria($curso_ufsc);
        $cohort_estudantes = self::get_relationship_cohort_estudantes($relationship->id);

        $sql = "SELECT DISTINCT u.id, CONCAT(firstname,' ',lastname) AS fullname
                  FROM {user} u
                  JOIN {relationship_members} rm
                    ON (rm.userid=u.id AND rm.relationshipcohortid=:cohort_id)
                  JOIN {relationship_groups} rg
                    ON (rg.relationshipid=:relationship_id AND rg.id=rm.relationshipgroupid)
              ORDER BY u.firstname";

        $params = array('relationship_id' => $relationship->id, 'cohort_id' => $cohort_estudantes->id);

        return $DB->get_records_sql_menu($sql, $params);
    }

    /**
     * Retorna lista de grupos de tutoria de um determinado curso ufsc
     *
     * @param string $curso_ufsc
     * @param array $tutores
     * @return array
     */
    static function get_grupos_tutoria($curso_ufsc, $tutores = null) {
        global $DB;
        $relationship = self::get_relationship_tutoria($curso_ufsc);

        if (is_null($tutores)) {
            $sql = "SELECT rg.*
                      FROM {relationship_groups} rg
                     WHERE rg.relationshipid = :relationshipid
                  GROUP BY rg.id
                  ORDER BY name";
            $params = array('relationshipid' => $relationship->id);
        } else {
            $tutores_sql = int_array_to_sql($tutores);
            $cohort_tutores = self::get_relationship_cohort_tutores($relationship->id);

            $sql = "SELECT rg.*
                      FROM {relationship_groups} rg
                      JOIN {relationship_members} rm
                        ON (rg.id=rm.relationshipgroupid AND rm.relationshipcohortid=:cohort_id)
                     WHERE rg.relationshipid = :relationshipid
                       AND rm.userid IN ({$tutores_sql})
                  GROUP BY rg.id
                  ORDER BY name";
            $params = array('relationshipid' => $relationship->id, 'cohort_id' => $cohort_tutores->id);
        }

        return $DB->get_records_sql($sql, $params);
    }

    /**
     * Retorna a string que é utilizada no agrupamento por grupos de tutoria
     *
     * O padrão é $nome_do_grupo - Tutor(es) responsaveis
     * @param $curso_ufsc
     * @param $id
     * @return string
     * @throws dml_read_exception
     */
    static function grupo_tutoria_to_string($curso_ufsc, $id) {
        global $DB;

        $relationship = self::get_relationship_tutoria($curso_ufsc);
        $cohort_tutores = self::get_relationship_cohort_tutores($relationship->id);

        $params = array('relationshipid' => $relationship->id, 'cohort_id' => $cohort_tutores->id, 'grupo_id' => $id);

        $sql = "SELECT rg.*
                  FROM {relationship_groups} rg
             LEFT JOIN {relationship_members} rm
                    ON (rg.id=rm.relationshipgroupid AND rm.relationshipcohortid=:cohort_id)
                 WHERE rg.relationshipid = :relationshipid
                   AND rg.id=:grupo_id
              GROUP BY rg.id
              ORDER BY name";

        $grupos_tutoria = $DB->get_records_sql($sql, $params);

        $sql = "SELECT u.id as user_id, CONCAT(u.firstname,' ',u.lastname) as fullname
                  FROM {relationship_groups} rg
             LEFT JOIN {relationship_members} rm
                    ON (rg.id=rm.relationshipgroupid AND rm.relationshipcohortid=:cohort_id)
             LEFT JOIN {user} u
                    ON (u.id=rm.userid)
                 WHERE rg.relationshipid = :relationshipid
                   AND rg.id=:grupo_id
              GROUP BY rg.id
              ORDER BY name";

        $tutores = $DB->get_records_sql($sql, $params);

        $string = '<strong>'.$grupos_tutoria[$id]->name.'</strong>';
        if (empty($tutores)) {
            return $string." - Sem Tutor Responsável";
        } else {
            foreach ($tutores as $tutor) {
                $string .= ' - '.$tutor->fullname.' ';
            }
        }

        return $string;
    }

    /**
     * Retorna o relationship_cohort dos estudantes de um determinado relationship
     * @param $relationship_id
     * @return mixed
     */
    static function get_relationship_cohort_estudantes($relationship_id) {
        global $DB;

        $student_role = grupos_tutoria::get_papeis_estudantes();
        list($sqlfragment, $paramsfragment) = $DB->get_in_or_equal($student_role, SQL_PARAMS_NAMED, 'shortname');

        $sql = "SELECT rc.*
                  FROM {relationship_cohorts} rc
                  JOIN {role} r
                    ON (r.id=rc.roleid)
                 WHERE relationshipid=:relationship_id
                   AND r.shortname {$sqlfragment}";

        $params = array_merge($paramsfragment, array('relationship_id' => $relationship_id));
        $cohort = $DB->get_record_sql($sql, $params);

        if (!$cohort) {
            print_error('relationship_cohort_estudantes_not_available_error', 'local_tutores', '', null, "Relationship: {$relationship_id}");
        }

        return $cohort;
    }

    /**
     * Retorna o relationship_cohort dos tutores de um determinado relationship
     * @param $relationship_id
     * @return mixed
     */
    static function get_relationship_cohort_tutores($relationship_id) {
        global $DB;

        $tutor_role = grupos_tutoria::get_papeis_tutores();
        list($sqlfragment, $paramsfragment) = $DB->get_in_or_equal($tutor_role, SQL_PARAMS_NAMED, 'shortname');

        $sql = "SELECT rc.*
                  FROM {relationship_cohorts} rc
                  JOIN {role} r
                    ON (r.id=rc.roleid)
                 WHERE relationshipid=:relationship_id
                   AND r.shortname {$sqlfragment}";


        $params = array_merge($paramsfragment, array('relationship_id' => $relationship_id));
        $cohort = $DB->get_record_sql($sql, $params);

        if (!$cohort) {
            print_error('relationship_cohort_tutores_not_available_error', 'local_tutores', '', null, "Relationship: {$relationship_id}");
        }

        return $cohort;
    }


    #TODO Roberto: Remover AKI e trocar por get_relationship_tutoria_por_categoria_curso
    /**
     * Retorna o relationship que designa os grupos de tutoria de um determinado curso UFSC
     * @param $curso_ufsc
     * @return mixed
     */
    static function get_relationship_tutoria($curso_ufsc) {
        global $DB;

        $ufsc_category = self::get_category_from_curso_ufsc($curso_ufsc);

        $sql = "SELECT r.id, r.name as nome
                  FROM {relationship} r
                  JOIN (
                        SELECT ti.itemid as relationship_id
                          FROM {tag_instance} ti
                          JOIN {tag} t
                            ON (t.id=ti.tagid)
                         WHERE t.name='grupo_tutoria'
                       ) tr
                    ON (r.id=tr.relationship_id)
                  JOIN {context} ctx
                    ON (ctx.id=r.contextid)
                  JOIN {course_categories} cc
                    ON (ctx.instanceid = cc.id AND (cc.path LIKE '/$ufsc_category/%' OR cc.path LIKE '/$ufsc_category'))";

        $relationship = $DB->get_records_sql($sql);

        //Evita o csaso de um curso que retorne com mais de um relationship
        if (count($relationship) > 1) {
            print_error('relationship_tutoria_too_many_available_error', 'local_tutores');
        }

        $relationship = reset($relationship);
        if (!$relationship) {
            print_error('relationship_tutoria_not_available_error', 'local_tutores');
        }

        return $relationship;
    }

    /**
     * Retorna o relationship que designa os grupos de tutoria de uma determinada categoria de turma
     * @param $categoria_turma
     * @return mixed
     */
    static function get_relationship_tutoria_por_categoria_turma($categoria_turma) {
        global $DB;

        $sql = "SELECT r.id, r.name as nome
                  FROM {relationship} r
                  JOIN (
                        SELECT ti.itemid as relationship_id
                          FROM {tag_instance} ti
                          JOIN {tag} t
                            ON (t.id=ti.tagid)
                         WHERE t.name='grupo_tutoria'
                       ) tr
                    ON (r.id=tr.relationship_id)
                  JOIN {context} ctx
                    ON (ctx.id=r.contextid)
                  JOIN {course_categories} cc
                    ON (ctx.instanceid = cc.id AND (cc.path LIKE '%/$categoria_turma/%' OR cc.path LIKE '%/$categoria_turma'))";

        $relationship = $DB->get_records_sql($sql);

        //Evita o caso de um curso que retorne com mais de um relationship
        if (count($relationship) > 1) {
            print_error('relationship_tutoria_too_many_available_error', 'local_tutores');
        }

        $relationship = reset($relationship);
        if (!$relationship) {
            print_error('relationship_tutoria_not_available_error', 'local_tutores');
        }

        return $relationship;
    }

    #TODO Roberto: Remove AKI e trocar por [ get_tutores_categoria_curso_ufsc ]
    /**
     * Dado que alimenta a lista do filtro tutores
     *
     * @param $curso_ufsc
     * @return array
     */
    static function get_tutores_curso_ufsc($curso_ufsc) {
        global $DB;

        $relationship = self::get_relationship_tutoria($curso_ufsc);
        $cohort_tutores = self::get_relationship_cohort_tutores($relationship->id);

        $sql = "SELECT DISTINCT u.id, CONCAT(firstname,' ',lastname) AS fullname
                  FROM {user} u
                  JOIN {relationship_members} rm
                    ON (rm.userid=u.id AND rm.relationshipcohortid=:cohort_id)
                  JOIN {relationship_groups} rg
                    ON (rg.relationshipid=:relationship_id AND rg.id=rm.relationshipgroupid)
              ORDER BY u.firstname";

        $params = array('relationship_id' => $relationship->id, 'cohort_id' => $cohort_tutores->id);

        return $DB->get_records_sql_menu($sql, $params);
    }

    /**
     * Dado que alimenta a lista do filtro tutores
     *
     * @param $categoria_curso
     * @return arrays
     */
    static function get_tutores_categoria_curso_ufsc($categoria_curso) {
        global $DB;

        $relationship = self::get_relationship_tutoria_por_categoria_turma($categoria_curso);
        $cohort_tutores = self::get_relationship_cohort_tutores($relationship->id);

        $sql = "SELECT DISTINCT u.id, CONCAT(firstname,' ',lastname) AS fullname
                  FROM {user} u
                  JOIN {relationship_members} rm
                    ON (rm.userid=u.id AND rm.relationshipcohortid=:cohort_id)
                  JOIN {relationship_groups} rg
                    ON (rg.relationshipid=:relationship_id AND rg.id=rm.relationshipgroupid)
              ORDER BY u.firstname";

        $params = array('relationship_id' => $relationship->id, 'cohort_id' => $cohort_tutores->id);

        return $DB->get_records_sql_menu($sql, $params);
    }

    /**
     * Localiza uma categoria com base no curso UFSC informado
     *
     * @param int $curso_ufsc Código do Curso UFSC
     * @return mixed
     */
    static function get_category_from_curso_ufsc($curso_ufsc) {
        global $DB;

        $ufsc_category_sql = "
        SELECT cc.id
          FROM {course_categories} cc
         WHERE cc.idnumber=:curso_ufsc";

        return $DB->get_field_sql($ufsc_category_sql, array('curso_ufsc' => "curso_{$curso_ufsc}"));
    }

    /**
     * Recupera o código CursoUFSC a partir do "courseid" informado
     * A informação do curso UFSC está armazenada no campo idnumber da categoria principal (nivel 1)
     *
     * @param $courseid Moodle course id
     * @return bool|mixed
     */
    static function get_curso_ufsc_id($courseid) {
        global $DB;

        $course = $DB->get_record('course', array('id' => $courseid), 'category', MUST_EXIST);
        $category = $DB->get_record('course_categories', array('id' => $course->category), 'id, idnumber, depth, path', MUST_EXIST);

        if ($category->depth > 1) {
            // Pega o primeiro id do caminho
            preg_match('/^\/([0-9]+)\//', $category->path, $matches);
            $root_category = $matches[1];

            $category = $DB->get_record('course_categories', array('id' => $root_category), 'id, idnumber, depth, path', MUST_EXIST);
        }

        $curso_ufsc_id = str_replace('curso_', '', $category->idnumber, $count);
        return ($count) ? $curso_ufsc_id : false;
    }

    /**
     * Monta uma string para ser utilizada na clausula IN de uma SELECT
     * A informação será utilizada para a tabela CONTEXT com o campo "instanceid"
     *
     * @param $courseid Moodle course id
     * @return bool|mixed
     */
    static function get_path_category_by_course($courseid) {
        global $DB;

        $sql = "SELECT SUBSTRING(REPLACE(cc.path, '/', ', '),2) path_list
			      FROM {course} co
			      JOIN {course_categories} cc
			        ON (co.category = cc.id)
	 		     WHERE co.id = :courseid";

        return $DB->get_field_sql($sql, array('courseid' => $courseid));

    }

    /**
     * Recupera a categoria do CursoUFSC a partir do "courseid" informado
     * A informação ...
     *
     * @param $courseid Moodle course id
     * @return bool|mixed
     */
    static function get_categoria_curso_ufsc($courseid) {
        global $DB;

        $path_list = trim(self::get_path_category_by_course($courseid));

        $sql = "SELECT cc1.id AS course_id
	              FROM {course_categories} cc1
	              JOIN {context} c1
	                ON (cc1.id = c1.instanceid)
	             WHERE (c1.contextlevel = 40)
	               AND (cc1.id IN ($path_list))
                   AND (cc1.idnumber != '')
                 UNION
                SELECT instanceid
                  FROM {inscricoes_activities} ia
                  JOIN {context} c
                    ON (ia.contextid = c.id)
                 WHERE (ia.enable = 1)
                   AND (c.contextlevel = 40)
                   AND (c.instanceid IN ($path_list))";

        $categoria = $DB->get_field_sql($sql);

        return $categoria;
    }

    /**
     * Recupera a categoria do CursoUFSC a partir do "courseid" informado
     * A informação do curso UFSC está armazenada no campo
     *
     * @param $ufsc_category
     * @return bool|mixed
     */
    static function get_categoria_turma_ufsc($ufsc_category) {
        global $DB;

        $path_list = trim(self::get_path_category_by_course($ufsc_category));

        $sql = "SELECT ct.instanceid AS course_category_id
                  FROM {grade_curricular} gc
                  JOIN (SELECT *
    	                   FROM {context}
		                  WHERE instanceid IN ($path_list)
		                    AND contextlevel = 40
                       ) ct
                    ON (gc.contextid = ct.id)";

        $categoria = $DB->get_field_sql($sql);

        return $categoria;
    }


}