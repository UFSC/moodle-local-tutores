<?php

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/user/selector/lib.php');

define('GRUPO_TUTORIA_TIPO_ESTUDANTE', 'E');
define('GRUPO_TUTORIA_TIPO_TUTOR', 'T');
define('GRUPO_ORIENTACAO_TIPO_ORIENTADOR', 'O');

function local_tutores_extend_settings_navigation(navigation_node $navigation) {
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

class local_tutores_base_group {

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
     * Listagem de estudantes que participam de uma categoria de turma de uma curso e estão relacionados a um tutor
     *
     * @param $categoria_turma
     * @return array [id][fullname]
     */
    static function get_estudantes($categoria_turma) {
        global $DB;

        $relationship = local_tutores_grupos_tutoria::get_relationship_tutoria($categoria_turma);
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
     * Retorna o tutor responsável em um curso_ufsc por um estudante
     *
     * @param $relationship
     * @param $cohort_responsavel
     * @param $student_userid
     * @throws dml_missing_record_exception
     * @throws dml_multiple_records_exception
     * @return bool|mixed
     */
    static function get_responsavel_estudante($relationship, $cohort_responsavel, $student_userid) {
        global $DB;

        $cohort_estudantes = self::get_relationship_cohort_estudantes($relationship->id);

        $sql = "SELECT DISTINCT u.id, u.username, CONCAT(firstname,' ',lastname) AS fullname
                  FROM {user} u
                  JOIN {relationship_members} rm
                    ON (rm.userid=u.id AND rm.relationshipcohortid=:cohort_responsavel)
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
            'cohort_responsavel' => $cohort_responsavel->id,
            'estudante' => $student_userid);

        return $DB->get_record_sql($sql, $params);
    }


    /**
     * Retorna o relationship_cohort dos estudantes de um determinado relationship
     * @param $relationship_id
     * @return mixed
     */
    static function get_relationship_cohort_estudantes($relationship_id) {
        global $DB;

        $student_role = self::get_papeis_estudantes();

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
            print_error('relationship_cohort_estudantes_not_available_error', 'report_unasus', '', null, "Relationship: {$relationship_id}");
        }

        return $cohort;
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
     * Retorna o relationship que designa os grupos de tutoria de uma determinada categoria de turma
     * @param $categoria_turma int
     * @param $tag_name string
     * @return mixed
     */
    static function get_relationship($categoria_turma, $tag_name) {
        global $DB;

        # FIXME: validar categoria-turma (não aceitar boolean)

        $sql = "SELECT r.id, r.name as nome
                  FROM {relationship} r
                  JOIN (
                        SELECT ti.itemid as relationship_id
                          FROM {tag_instance} ti
                          JOIN {tag} t
                            ON (t.id=ti.tagid)
                         WHERE t.name=:tag_name
                       ) tr
                    ON (r.id=tr.relationship_id)
                  JOIN {context} ctx
                    ON (ctx.id=r.contextid)
                  JOIN {course_categories} cc
                    ON (ctx.instanceid = cc.id AND (cc.path LIKE '%/$categoria_turma/%' OR cc.path LIKE '%/$categoria_turma'))";

        $relationship = $DB->get_records_sql($sql, array('tag_name' => $tag_name));

        // Evita o caso de um curso que retorne com mais de um relationship
        if (count($relationship) > 1) {
            print_error("relationship_{$tag_name}_too_many_available_error", 'local_tutores');
        }

        $relationship = reset($relationship);
        if (!$relationship) {
            print_error("relationship_{$tag_name}_not_available_error", 'local_tutores');
        }

        return $relationship;
    }
}

class local_tutores_grupo_orientacao extends local_tutores_base_group {

    static function get_estudantes($categoria_turma){
        global $DB;

        $relationship = self::get_relationship_orientacao($categoria_turma);

        $cohort_estudantes = self::get_relationship_cohort_estudantes($relationship->id);

        $sql = "SELECT DISTINCT u.id, CONCAT(firstname,' ',lastname) AS fullname
                  FROM {user} u
                  JOIN {relationship_members} rm
                    ON (rm.userid=u.id)
                  JOIN {relationship_groups} rg
                    ON (rg.id=rm.relationshipgroupid)
                 WHERE rm.relationshipcohortid=:cohort_id AND rg.relationshipid=:relationship_id
              ORDER BY u.firstname";

        $params = array('relationship_id' => $relationship->id, 'cohort_id' => $cohort_estudantes->id);

        return $DB->get_records_sql_menu($sql, $params);
    }

    /**
     * Retorna os papéis que estão sendo considerados como orientadores
     *
     * @static
     * @return array
     */
    static function get_papeis_orientadores() {
        global $CFG;

        return explode(',', $CFG->local_tutores_orientador_roles);
    }

    /**
     * Retorna o relationship_cohort dos orientadores de um determinado relationship
     * @param $relationship_id
     * @return mixed
     */
    static function get_relationship_cohort_orientadores($relationship_id) {
        global $DB;

        $orientador_role = self::get_papeis_orientadores();

        list($sqlfragment, $paramsfragment) = $DB->get_in_or_equal($orientador_role, SQL_PARAMS_NAMED, 'shortname');

        $sql = "SELECT rc.*
                  FROM {relationship_cohorts} rc
                  JOIN {role} r
                    ON (r.id=rc.roleid)
                 WHERE relationshipid=:relationship_id
                   AND r.shortname {$sqlfragment}";


        $params = array_merge($paramsfragment, array('relationship_id' => $relationship_id));
        $cohort = $DB->get_record_sql($sql, $params);

        if (!$cohort) {
            print_error('relationship_cohort_orientadores_not_available_error', 'report_unasus', '', null, "Relationship: {$relationship_id}");
        }

        return $cohort;
    }

    static function get_orientador_responsavel_estudante($categoria_turma, $student_userid){
        $relationship = self::get_relationship_orientacao($categoria_turma);
        $cohort_orientadores = self::get_relationship_cohort_orientadores($relationship->id);

        return self::get_responsavel_estudante($relationship, $cohort_orientadores, $student_userid);
    }

    /**
     * Retorna o relationship que designa os grupos de orientacao de um determinado curso UFSC
     * @param $categoria_turma int
     * @return mixed
     */
    static function get_relationship_orientacao($categoria_turma) {
        return self::get_relationship($categoria_turma, 'grupo_orientacao');
    }

    static function get_grupos_orientacao($categoria_turma, $orientadores = null) {
        global $DB;
        $relationship = self::get_relationship_orientacao($categoria_turma);

        if (is_null($orientadores)) {
            $sql = "SELECT rg.*
                      FROM {relationship_groups} rg
                     WHERE rg.relationshipid = :relationshipid
                  GROUP BY rg.id
                  ORDER BY name";
            $params = array('relationshipid' => $relationship->id);
        }

        else {

            $orientadores_sql = report_unasus_int_array_to_sql($orientadores);
            $cohort_orientadores = self::get_relationship_cohort_orientadores($relationship->id);

            $sql = "SELECT rg.*
                      FROM {relationship_groups} rg
                      JOIN {relationship_members} rm
                        ON (rg.id=rm.relationshipgroupid AND rm.relationshipcohortid=:cohort_id)
                     WHERE rg.relationshipid = :relationshipid
                       AND rm.userid IN ({$orientadores_sql})
                  GROUP BY rg.id
                  ORDER BY name";
            $params = array('relationshipid' => $relationship->id, 'cohort_id' => $cohort_orientadores->id);
        }

        return $DB->get_records_sql($sql, $params);
    }

    /**
     * Retorna a string que é utilizada no agrupamento por grupos de orientacao
     *
     * O padrão é $nome_do_grupo - Orientador(es) responsaveis
     * @param $categoria_turma
     * @param $id
     * @return string
     * @throws dml_read_exception
     */
    static function grupo_orientacao_to_string($categoria_turma, $id) {
        global $DB;

        $relationship = self::get_relationship_orientacao($categoria_turma);
        $cohort_orientadores = self::get_relationship_cohort_orientadores($relationship->id);

        $params = array('relationshipid' => $relationship->id, 'cohort_id' => $cohort_orientadores->id, 'grupo_id' => $id);

        $sql = "SELECT rg.*
                  FROM {relationship_groups} rg
             LEFT JOIN {relationship_members} rm
                    ON (rg.id=rm.relationshipgroupid AND rm.relationshipcohortid=:cohort_id)
                 WHERE rg.relationshipid = :relationshipid
                   AND rg.id=:grupo_id
              GROUP BY rg.id
              ORDER BY name";

        $grupos_orientacao = $DB->get_records_sql($sql, $params);

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

        $orientadores = $DB->get_records_sql($sql, $params);

        $string = '<strong>'.$grupos_orientacao[$id]->name.'</strong>';
        if (empty($orientadores)) {
            return $string." - Sem Orientador Responsável";
        } else {
            foreach ($orientadores as $orientador) {
                $string .= ' - '.$orientador->fullname.' ';
            }
        }

        return $string;
    }

    /**
     * Dado que alimenta a lista do filtro orientadores
     *
     * @param $categoria_turma int id da categoria
     * @return array [id, fullname]
     */
    static function get_orientadores($categoria_turma) {
        global $DB;

        $relationship = self::get_relationship_orientacao($categoria_turma);
        $cohort_orientadores = self::get_relationship_cohort_orientadores($relationship->id);

        $sql = "SELECT DISTINCT u.id, CONCAT(firstname,' ',lastname) AS fullname
                  FROM {user} u
                  JOIN {relationship_members} rm
                    ON (rm.userid=u.id AND rm.relationshipcohortid=:cohort_id)
                  JOIN {relationship_groups} rg
                    ON (rg.relationshipid=:relationship_id AND rg.id=rm.relationshipgroupid)
              ORDER BY u.firstname";

        $params = array('relationship_id' => $relationship->id, 'cohort_id' => $cohort_orientadores->id);

        return $DB->get_records_sql_menu($sql, $params);
    }

}

class local_tutores_grupos_tutoria extends local_tutores_base_group {

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
     * Retorna o tutor responsável em um curso_ufsc por um estudante
     *
     * @param string $categoria_turma
     * @param $student_userid
     * @return bool|mixed
     */
    static function get_tutor_responsavel_estudante($categoria_turma, $student_userid) {
        $relationship = self::get_relationship_tutoria($categoria_turma);
        $cohort_tutores = self::get_relationship_cohort_tutores($relationship->id);
        return self::get_responsavel_estudante($relationship, $cohort_tutores, $student_userid);
    }

    /**
     * Retorna lista de grupos de tutoria de um determinado curso ufsc
     *
     * @param string $categoria_turma
     * @param array $tutores
     * @return array
     */
    static function get_grupos_tutoria($categoria_turma, $tutores = null) {
        global $DB;
        $relationship = self::get_relationship_tutoria($categoria_turma);
        $cohort_tutores = self::get_relationship_cohort_tutores($relationship->id);
        $tutores_where = " ";

        if (!is_null($tutores)) {
            $tutores_sql = report_unasus_int_array_to_sql($tutores);
            $tutores_where = " AND rm.userid IN ({$tutores_sql}) ";
        }
        $sql = "SELECT rg.*
                  FROM {relationship_groups} rg
                  JOIN {relationship_members} rm
                    ON (rg.id=rm.relationshipgroupid AND rm.relationshipcohortid=:cohort_id)
                 WHERE rg.relationshipid = :relationshipid
               $tutores_where
              GROUP BY rg.id
              ORDER BY name";

        $params = array('relationshipid' => $relationship->id, 'cohort_id' => $cohort_tutores->id);

        return $DB->get_records_sql($sql, $params);
    }

    /**
     * Retorna lista de grupos de tutoria de um determinado curso ufsc
     *
     * @param string $categoria_turma
     * @param array $tutores
     * @return array
     */
    static function get_grupos_tutoria_new($categoria_turma, $grupos_tutoria = null) {
        global $DB;
        $relationship = self::get_relationship_tutoria($categoria_turma);
        $grupos_tutoria_where = " ";

        if (!is_null($grupos_tutoria)) {
            $grupos_tutoria_sql = report_unasus_int_array_to_sql($grupos_tutoria);
            $grupos_tutoria_where = " AND rg.id IN ({$grupos_tutoria_sql}) ";
        }
        $sql = "SELECT rg.*
                  FROM {relationship_groups} rg
                 WHERE rg.relationshipid = :relationshipid
               $grupos_tutoria_where
              ORDER BY name";

        $params = array('relationshipid' => $relationship->id);

        return $DB->get_records_sql($sql, $params);
    }

    /**
     * Retorna lista de grupos de tutoria de um determinado curso ufsc
     *
     * @param string $categoria_turma
     * @param array $tutores
     * @return array
     */
    static function get_grupos_tutoria_menu($categoria_turma) {
        global $DB;
        $relationship = self::get_relationship_tutoria($categoria_turma);
        $sql = "SELECT rg.id, rg.name AS fullname
                  FROM {relationship_groups} rg
                 WHERE rg.relationshipid = :relationshipid
              ORDER BY name";

        $params = array('relationshipid' => $relationship->id);

        return $DB->get_records_sql_menu($sql, $params);
    }

    /**
     * Retorna a string que é utilizada no agrupamento por grupos de tutoria
     *
     * O padrão é $nome_do_grupo - Tutor(es) responsaveis
     * @param $categoria_turma
     * @param $id
     * @return string
     * @throws dml_read_exception
     */
    static function grupo_tutoria_to_string($categoria_turma, $id) {
        global $DB;

        $relationship = self::get_relationship_tutoria($categoria_turma);
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
     * Retorna o relationship_cohort dos tutores de um determinado relationship
     * @param $relationship_id
     * @return mixed
     */
    static function get_relationship_cohort_tutores($relationship_id) {
        global $DB;

        $tutor_role = local_tutores_grupos_tutoria::get_papeis_tutores();
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

    /**
     * Retorna o relationship que designa os grupos de tutoria de uma determinada categoria de turma
     * @param $categoria_turma
     * @return mixed
     */
    static function get_relationship_tutoria($categoria_turma) {
        return self::get_relationship($categoria_turma, 'grupo_tutoria');
    }

    /**
     * Dado que alimenta a lista do filtro tutores
     *
     * @param $categoria_turma int id da categoria
     * @return array [id, fullname]
     */
    static function get_tutores($categoria_turma) {
        global $DB;

        $relationship = self::get_relationship_tutoria($categoria_turma);
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

    static function get_estudantes_grupo_tutoria($categoria_turma, $group_id)
    {
        global $DB;
        $relationship = self::get_relationship_tutoria($categoria_turma);
        $cohort_estudantes = self::get_relationship_cohort_estudantes($relationship->id);

        /* Query alunos */
        $query_alunos = query_alunos_relationship();
        $params = array(
            'tipo_aluno' => GRUPO_TUTORIA_TIPO_ESTUDANTE,
            'cohort_relationship_id' => $cohort_estudantes->id,
            'relationship_id' => $relationship->id,
            'grupo' => $group_id
        );
        $estudantes_grupo_tutoria = $DB->get_records_sql($query_alunos, $params);
        return $estudantes_grupo_tutoria;
    }

}
