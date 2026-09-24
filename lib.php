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
//        if ($category_node) {
//            $category_node->add(
//                    get_string('tutorship_groups', 'local_tutores'),
//                    new moodle_url('/local/tutores/index.php', array('categoryid' => $PAGE->context->instanceid)),
//                    navigation_node::TYPE_SETTING, null, null, new pix_icon('icon', '', 'local_tutores'));
//        }
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
        // Plural: suporta múltiplos cohorts no papel estudante.
        $cohorts_estudantes = self::get_relationship_cohorts_estudantes($relationship->id);
        list($cohort_in, $cohort_params) = $DB->get_in_or_equal(
            array_keys($cohorts_estudantes), SQL_PARAMS_NAMED, 'cohortid');

        $sql = "SELECT DISTINCT u.id, CONCAT(firstname,' ',lastname) AS fullname
                  FROM {user} u
                  JOIN {relationship_members} rm
                    ON (rm.userid=u.id AND rm.relationshipcohortid {$cohort_in})
                  JOIN {relationship_groups} rg
                    ON (rg.relationshipid=:relationship_id AND rg.id=rm.relationshipgroupid)
              ORDER BY u.firstname";

        $params = array_merge($cohort_params, array('relationship_id' => $relationship->id));

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

        // Lado estudante: array de relationship_cohorts → IN (...).
        $cohorts_estudantes = self::get_relationship_cohorts_estudantes($relationship->id);
        list($cohortest_in, $cohortest_params) = $DB->get_in_or_equal(
            array_keys($cohorts_estudantes), SQL_PARAMS_NAMED, 'cohortest');

        // Lado responsável (tutor ou orientador): aceita array (multi-cohort) ou
        // stdClass (legado single-cohort). Normaliza para array de PKs e usa IN.
        if (is_array($cohort_responsavel)) {
            $cohort_responsavel_ids = array_keys($cohort_responsavel);
        } else {
            $cohort_responsavel_ids = array($cohort_responsavel->id);
        }
        list($cohortresp_in, $cohortresp_params) = $DB->get_in_or_equal(
            $cohort_responsavel_ids, SQL_PARAMS_NAMED, 'cohortresp');

        $sql = "SELECT DISTINCT u.id, u.username, CONCAT(firstname,' ',lastname) AS fullname
                  FROM {user} u
                  JOIN {relationship_members} rm
                    ON (rm.userid=u.id AND rm.relationshipcohortid {$cohortresp_in})
                  JOIN {relationship_groups} rg
                    ON (rg.relationshipid=:relationship_id1 AND rg.id=rm.relationshipgroupid)
                  JOIN (
                          SELECT DISTINCT rg.*
                            FROM {user} u
                            JOIN {relationship_members} rm
                              ON (rm.userid=u.id AND rm.relationshipcohortid {$cohortest_in})
                            JOIN {relationship_groups} rg
                              ON (rg.relationshipid=:relationship_id2 AND rg.id=rm.relationshipgroupid)
                           WHERE u.id=:estudante
                       ) grupo_estudante
                    ON (rg.id=grupo_estudante.id)";

        $params = array_merge($cohortest_params, $cohortresp_params, array(
            'relationship_id1' => $relationship->id,
            'relationship_id2' => $relationship->id,
            'estudante' => $student_userid));

        return $DB->get_record_sql($sql, $params);
    }


    /**
     * Retorna TODOS os relationship_cohorts do papel estudante de um determinado relationship.
     * Suporta a configuração nova de local_relationship onde o mesmo papel pode estar
     * associado a múltiplos cohorts (um por linha em {relationship_cohorts}).
     *
     * @param int $relationship_id
     * @return array [id => stdClass] indexado pelo id de relationship_cohorts
     */
    static function get_relationship_cohorts_estudantes($relationship_id) {
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
        $cohorts = $DB->get_records_sql($sql, $params);

        if (empty($cohorts)) {
            // Componente local_tutores, e nao report_unasus: aquele plugin nao esta
            // instalado nas arvores 4.x, e a mensagem saia como string faltando --
            // justo nos caminhos que o local_wstcc exercita. A string ja' existe aqui.
            throw new moodle_exception('relationship_cohort_estudantes_not_available_error', 'local_tutores', '', null, "Relationship: {$relationship_id}");
        }

        return $cohorts;
    }

    /**
     * Wrapper retrocompatível: retorna o primeiro relationship_cohort do papel estudante.
     * Use {@link get_relationship_cohorts_estudantes()} (plural) para suportar múltiplos
     * cohorts no mesmo papel. Esta versão singular dispara debugging() quando há mais
     * de um cohort para sinalizar pontos a migrar.
     *
     * @param int $relationship_id
     * @return stdClass
     */
    static function get_relationship_cohort_estudantes($relationship_id) {
        $cohorts = self::get_relationship_cohorts_estudantes($relationship_id);
        if (count($cohorts) > 1) {
            debugging('Relationship has multiple cohorts for the estudante role; caller is still using the singular accessor.', DEBUG_DEVELOPER);
        }
        return reset($cohorts);
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
            throw new moodle_exception("relationship_{$tag_name}_too_many_available_error", 'local_tutores');
        }

        $relationship = reset($relationship);
        if (!$relationship) {
            throw new moodle_exception("relationship_{$tag_name}_not_available_error", 'local_tutores');
        }

        return $relationship;
    }
}

class local_tutores_grupo_orientacao extends local_tutores_base_group {

    static function get_estudantes($categoria_turma){
        global $DB;

        $relationship = self::get_relationship_orientacao($categoria_turma);

        // Plural: suporta múltiplos cohorts no papel estudante.
        $cohorts_estudantes = self::get_relationship_cohorts_estudantes($relationship->id);
        list($cohort_in, $cohort_params) = $DB->get_in_or_equal(
            array_keys($cohorts_estudantes), SQL_PARAMS_NAMED, 'cohortid');

        $sql = "SELECT DISTINCT u.id, CONCAT(firstname,' ',lastname) AS fullname
                  FROM {user} u
                  JOIN {relationship_members} rm
                    ON (rm.userid=u.id)
                  JOIN {relationship_groups} rg
                    ON (rg.id=rm.relationshipgroupid)
                 WHERE rm.relationshipcohortid {$cohort_in} AND rg.relationshipid=:relationship_id
              ORDER BY u.firstname";

        $params = array_merge($cohort_params, array('relationship_id' => $relationship->id));

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
     * Returns the role shortnames of the orientation support.
     *
     * Reads local_wstcc_suporte_roles, the same setting local_wstcc uses, so both plugins agree on
     * who the support is. The default mirrors local_wstcc_papeis_configurados(): a restored dump
     * can drop the config row, and an empty value must not silently hide every support member.
     *
     * @return string[]
     */
    public static function get_papeis_suporte() {
        $value = get_config('moodle', 'local_wstcc_suporte_roles');
        if ($value === false || trim($value) === '') {
            $value = 'suporteorientacao';
        }
        return array_values(array_filter(array_map('trim', explode(',', $value)), 'strlen'));
    }

    /**
     * Returns the relationship cohorts of the orientation support role.
     *
     * The support cohort is optional, so an empty array is a valid answer and not an error.
     *
     * @param int $relationshipid Id of the orientation relationship.
     * @return array [id => stdClass] keyed by relationship_cohorts id
     */
    public static function get_relationship_cohorts_suportes($relationshipid) {
        global $DB;

        $shortnames = self::get_papeis_suporte();
        if (empty($shortnames)) {
            // A setting made only of separators names no role: there is no support cohort.
            return [];
        }
        [$rolein, $roleparams] = $DB->get_in_or_equal($shortnames, SQL_PARAMS_NAMED, 'shortname');

        $sql = "SELECT rc.*
                  FROM {relationship_cohorts} rc
                  JOIN {role} r ON (r.id = rc.roleid)
                 WHERE rc.relationshipid = :relationshipid
                   AND r.shortname {$rolein}";

        return $DB->get_records_sql($sql, array_merge($roleparams, ['relationshipid' => $relationshipid]));
    }

    /**
     * Retorna TODOS os relationship_cohorts do papel orientador de um determinado relationship.
     *
     * @param int $relationship_id
     * @return array [id => stdClass] indexado pelo id de relationship_cohorts
     */
    static function get_relationship_cohorts_orientadores($relationship_id) {
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
        $cohorts = $DB->get_records_sql($sql, $params);

        if (empty($cohorts)) {
            // Ver a nota em get_relationship_cohorts_estudantes: mesmo defeito,
            // mesmo conserto.
            throw new moodle_exception('relationship_cohort_orientadores_not_available_error', 'local_tutores', '', null, "Relationship: {$relationship_id}");
        }

        return $cohorts;
    }

    /**
     * Wrapper retrocompatível: retorna o primeiro relationship_cohort do papel orientador.
     * @param int $relationship_id
     * @return stdClass
     */
    static function get_relationship_cohort_orientadores($relationship_id) {
        $cohorts = self::get_relationship_cohorts_orientadores($relationship_id);
        if (count($cohorts) > 1) {
            debugging('Relationship has multiple cohorts for the orientador role; caller is still using the singular accessor.', DEBUG_DEVELOPER);
        }
        return reset($cohorts);
    }

    static function get_orientador_responsavel_estudante($categoria_turma, $student_userid){
        $relationship = self::get_relationship_orientacao($categoria_turma);
        // Plural: get_responsavel_estudante aceita array.
        $cohorts_orientadores = self::get_relationship_cohorts_orientadores($relationship->id);

        return self::get_responsavel_estudante($relationship, $cohorts_orientadores, $student_userid);
    }

    /**
     * Retorna o relationship que designa os grupos de orientacao de um determinado curso UFSC
     * @param $categoria_turma int
     * @return mixed
     */
    static function get_relationship_orientacao($categoria_turma) {
        return self::get_relationship($categoria_turma, 'grupo_orientacao');
    }

    /**
     * Returns the orientation groups of a course category.
     *
     * With no filter, returns every group. With a filter, matches only the groups where the given
     * user id(s) belong to an advisor cohort OR a support cohort (issue #20): the orientation
     * support was previously invisible to this search, since only advisor cohorts were joined.
     *
     * @param int $categoria_turma
     * @param null|int|int[] $orientadores User id, array of user ids, or null for no filter.
     * @return array [id => stdClass] keyed by relationship_groups id
     */
    static function get_grupos_orientacao_by_userid($categoria_turma, $orientadores = null) {
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
            $userids = is_array($orientadores) ? $orientadores : [$orientadores];
            if (empty($userids)) {
                // An empty filter selects no group. Building "IN ()" is invalid SQL (issue #20).
                return [];
            }
            [$userin, $userparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'userid');

            // Advisors and orientation support both see the groups they belong to (issue #20).
            // Keys are relationship_cohorts ids, so "+" keeps each cohort once.
            $cohorts = self::get_relationship_cohorts_orientadores($relationship->id)
                + self::get_relationship_cohorts_suportes($relationship->id);
            [$cohortin, $cohortparams] = $DB->get_in_or_equal(
                array_keys($cohorts),
                SQL_PARAMS_NAMED,
                'cohortid'
            );

            $sql = "SELECT rg.*
                      FROM {relationship_groups} rg
                      JOIN {relationship_members} rm
                        ON (rg.id=rm.relationshipgroupid AND rm.relationshipcohortid {$cohortin})
                     WHERE rg.relationshipid = :relationshipid
                       AND rm.userid {$userin}
                  GROUP BY rg.id
                  ORDER BY name";
            $params = array_merge($cohortparams, $userparams, ['relationshipid' => $relationship->id]);
        }

        return $DB->get_records_sql($sql, $params);
    }

    /**
     * Returns the orientation groups of a course category, optionally filtered by group id.
     *
     * @param int $categoriaturma Id of the class course category.
     * @param null|int|int[] $gruposorientacao Group id, array of group ids, or null for no filter.
     * @return array [id => stdClass] keyed by relationship_groups id
     */
    public static function get_grupos_orientacao_new($categoriaturma, $gruposorientacao = null) {
        global $DB;
        $relationship = self::get_relationship_orientacao($categoriaturma);

        $gruposorientacaowhere = " ";
        $grupoparams = [];

        if (!is_null($gruposorientacao)) {
            $grupoids = is_array($gruposorientacao) ? $gruposorientacao : [$gruposorientacao];
            if (empty($grupoids)) {
                // An empty filter selects no group. Building "IN ()" is invalid SQL (issue #20).
                return [];
            }
            [$grupoin, $grupoparams] = $DB->get_in_or_equal($grupoids, SQL_PARAMS_NAMED, 'grupoid');
            $gruposorientacaowhere = " AND rg.id {$grupoin} ";
        }
        $sql = "SELECT rg.*
                  FROM {relationship_groups} rg
                 WHERE rg.relationshipid = :relationshipid
               $gruposorientacaowhere
              ORDER BY name";

        $params = array_merge($grupoparams, ['relationshipid' => $relationship->id]);

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
        // Plural: suporta múltiplos cohorts no papel orientador.
        $cohorts_orientadores = self::get_relationship_cohorts_orientadores($relationship->id);
        list($cohort_in, $cohort_params) = $DB->get_in_or_equal(
            array_keys($cohorts_orientadores), SQL_PARAMS_NAMED, 'cohortid');

        $params = array_merge($cohort_params, array(
            'relationshipid' => $relationship->id,
            'grupo_id' => $id));

        $sql = "SELECT rg.*
                  FROM {relationship_groups} rg
             LEFT JOIN {relationship_members} rm
                    ON (rg.id=rm.relationshipgroupid AND rm.relationshipcohortid {$cohort_in})
                 WHERE rg.relationshipid = :relationshipid
                   AND rg.id=:grupo_id
              GROUP BY rg.id
              ORDER BY name";

        $grupos_orientacao = $DB->get_records_sql($sql, $params);

        // JOIN interno (não LEFT) e sem GROUP BY rg.id: um grupo sem responsáveis
        // devolve zero linhas — assim o ramo "Sem Orientador Responsável" abaixo
        // passa a ser alcançável — e um grupo com vários orientadores devolve todos.
        $sql = "SELECT u.id as user_id, CONCAT(u.firstname,' ',u.lastname) as fullname
                  FROM {relationship_groups} rg
                  JOIN {relationship_members} rm
                    ON (rg.id=rm.relationshipgroupid AND rm.relationshipcohortid {$cohort_in})
                  JOIN {user} u
                    ON (u.id=rm.userid)
                 WHERE rg.relationshipid = :relationshipid
                   AND rg.id=:grupo_id
              ORDER BY u.firstname, u.lastname";

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
        // Plural: suporta múltiplos cohorts no papel orientador.
        $cohorts_orientadores = self::get_relationship_cohorts_orientadores($relationship->id);
        list($cohort_in, $cohort_params) = $DB->get_in_or_equal(
            array_keys($cohorts_orientadores), SQL_PARAMS_NAMED, 'cohortid');

        $sql = "SELECT DISTINCT u.id, CONCAT(firstname,' ',lastname) AS fullname
                  FROM {user} u
                  JOIN {relationship_members} rm
                    ON (rm.userid=u.id AND rm.relationshipcohortid {$cohort_in})
                  JOIN {relationship_groups} rg
                    ON (rg.relationshipid=:relationship_id AND rg.id=rm.relationshipgroupid)
              ORDER BY u.firstname";

        $params = array_merge($cohort_params, array('relationship_id' => $relationship->id));

        return $DB->get_records_sql_menu($sql, $params);
    }

    /**
     * Dado que alimenta a lista do filtro grupos de orientação
     *
     * @param $categoria_turma int id da categoria
     * @return array [id, fullname]
     */
    static function get_orientadores_grupos($categoria_turma) {
        global $DB;

        $relationship = self::get_relationship_orientacao($categoria_turma);

        $sql = "SELECT rg.id,
                       rg.name AS fullname
                  FROM {relationship_groups} rg
                 WHERE (rg.relationshipid=:relationship_id)
              ORDER BY rg.name";

        $params = array('relationship_id' => $relationship->id);

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
        // Plural: get_responsavel_estudante aceita array.
        $cohorts_tutores = self::get_relationship_cohorts_tutores($relationship->id);
        return self::get_responsavel_estudante($relationship, $cohorts_tutores, $student_userid);
    }

    /**
     * Retorna lista de grupos de tutoria de um determinado curso ufsc
     *
     * @param string $categoria_turma
     * @param null|int|int[] $tutores User id, array of user ids, or null for no filter.
     * @return array
     */
    static function get_grupos_tutoria_by_userid($categoria_turma, $tutores = null) {
        global $DB;
        $relationship = self::get_relationship_tutoria($categoria_turma);
        // Plural: suporta múltiplos cohorts no papel tutor.
        $cohortstutores = self::get_relationship_cohorts_tutores($relationship->id);
        [$cohortin, $cohortparams] = $DB->get_in_or_equal(
            array_keys($cohortstutores),
            SQL_PARAMS_NAMED,
            'cohortid'
        );

        $tutoreswhere = " ";
        $userparams = [];
        if (!is_null($tutores)) {
            $userids = is_array($tutores) ? $tutores : [$tutores];
            if (empty($userids)) {
                // An empty filter selects no group. Building "IN ()" is invalid SQL (issue #20).
                return [];
            }
            [$userin, $userparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'userid');
            $tutoreswhere = " AND rm.userid {$userin} ";
        }
        $sql = "SELECT rg.*
                  FROM {relationship_groups} rg
                  JOIN {relationship_members} rm
                    ON (rg.id=rm.relationshipgroupid AND rm.relationshipcohortid {$cohortin})
                 WHERE rg.relationshipid = :relationshipid
               $tutoreswhere
              GROUP BY rg.id
              ORDER BY name";

        $params = array_merge($cohortparams, $userparams, ['relationshipid' => $relationship->id]);

        return $DB->get_records_sql($sql, $params);
    }

    /**
     * Retorna lista de grupos de tutoria de um determinado curso ufsc
     *
     * @param string $categoriaturma Id of the class course category.
     * @param null|int|int[] $grupostutoria Group id, array of group ids, or null for no filter.
     * @return array
     */
    public static function get_grupos_tutoria_new($categoriaturma, $grupostutoria = null) {
        global $DB;
        $relationship = self::get_relationship_tutoria($categoriaturma);
        $grupostutoriawhere = " ";
        $grupoparams = [];

        if (!is_null($grupostutoria)) {
            $grupoids = is_array($grupostutoria) ? $grupostutoria : [$grupostutoria];
            if (empty($grupoids)) {
                // An empty filter selects no group. Building "IN ()" is invalid SQL (issue #20).
                return [];
            }
            [$grupoin, $grupoparams] = $DB->get_in_or_equal($grupoids, SQL_PARAMS_NAMED, 'grupoid');
            $grupostutoriawhere = " AND rg.id {$grupoin} ";
        }
        $sql = "SELECT rg.*
                  FROM {relationship_groups} rg
                 WHERE rg.relationshipid = :relationshipid
               $grupostutoriawhere
              ORDER BY name";

        $params = array_merge($grupoparams, ['relationshipid' => $relationship->id]);

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
        // Plural: suporta múltiplos cohorts no papel tutor.
        $cohorts_tutores = self::get_relationship_cohorts_tutores($relationship->id);
        list($cohort_in, $cohort_params) = $DB->get_in_or_equal(
            array_keys($cohorts_tutores), SQL_PARAMS_NAMED, 'cohortid');

        $params = array_merge($cohort_params, array(
            'relationshipid' => $relationship->id,
            'grupo_id' => $id));

        $sql = "SELECT rg.*
                  FROM {relationship_groups} rg
             LEFT JOIN {relationship_members} rm
                    ON (rg.id=rm.relationshipgroupid AND rm.relationshipcohortid {$cohort_in})
                 WHERE rg.relationshipid = :relationshipid
                   AND rg.id=:grupo_id
              GROUP BY rg.id
              ORDER BY name";

        $grupos_tutoria = $DB->get_records_sql($sql, $params);

        // JOIN interno (não LEFT) e sem GROUP BY rg.id: um grupo sem responsáveis
        // devolve zero linhas — assim o ramo "Sem Tutor Responsável" abaixo passa
        // a ser alcançável — e um grupo com vários tutores devolve todos eles.
        $sql = "SELECT u.id as user_id, CONCAT(u.firstname,' ',u.lastname) as fullname
                  FROM {relationship_groups} rg
                  JOIN {relationship_members} rm
                    ON (rg.id=rm.relationshipgroupid AND rm.relationshipcohortid {$cohort_in})
                  JOIN {user} u
                    ON (u.id=rm.userid)
                 WHERE rg.relationshipid = :relationshipid
                   AND rg.id=:grupo_id
              ORDER BY u.firstname, u.lastname";

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
     * Retorna TODOS os relationship_cohorts do papel tutor de um determinado relationship.
     *
     * @param int $relationship_id
     * @return array [id => stdClass] indexado pelo id de relationship_cohorts
     */
    static function get_relationship_cohorts_tutores($relationship_id) {
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
        $cohorts = $DB->get_records_sql($sql, $params);

        if (empty($cohorts)) {
            throw new moodle_exception('relationship_cohort_tutores_not_available_error', 'local_tutores', '', null, "Relationship: {$relationship_id}");
        }

        return $cohorts;
    }

    /**
     * Wrapper retrocompatível: retorna o primeiro relationship_cohort do papel tutor.
     * @param int $relationship_id
     * @return stdClass
     */
    static function get_relationship_cohort_tutores($relationship_id) {
        $cohorts = self::get_relationship_cohorts_tutores($relationship_id);
        if (count($cohorts) > 1) {
            debugging('Relationship has multiple cohorts for the tutor role; caller is still using the singular accessor.', DEBUG_DEVELOPER);
        }
        return reset($cohorts);
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
        // Plural: suporta múltiplos cohorts no papel tutor.
        $cohorts_tutores = self::get_relationship_cohorts_tutores($relationship->id);
        list($cohort_in, $cohort_params) = $DB->get_in_or_equal(
            array_keys($cohorts_tutores), SQL_PARAMS_NAMED, 'cohortid');

        $sql = "SELECT DISTINCT u.id, CONCAT(firstname,' ',lastname) AS fullname
                  FROM {user} u
                  JOIN {relationship_members} rm
                    ON (rm.userid=u.id AND rm.relationshipcohortid {$cohort_in})
                  JOIN {relationship_groups} rg
                    ON (rg.relationshipid=:relationship_id AND rg.id=rm.relationshipgroupid)
              ORDER BY u.firstname";

        $params = array_merge($cohort_params, array('relationship_id' => $relationship->id));

        return $DB->get_records_sql_menu($sql, $params);
    }

    static function get_estudantes_grupo_tutoria($categoria_turma, $group_id)
    {
        global $DB;
        $relationship = self::get_relationship_tutoria($categoria_turma);
        // Plural: query_alunos_relationship aceita array e embute o filtro IN.
        $cohort_estudantes = self::get_relationship_cohorts_estudantes($relationship->id);

        /* Query alunos */
        $query_alunos = query_alunos_relationship($cohort_estudantes);
        $params = array(
            'tipo_aluno' => GRUPO_TUTORIA_TIPO_ESTUDANTE,
            'relationship_id' => $relationship->id,
            'grupo' => $group_id
        );
        $estudantes_grupo_tutoria = $DB->get_records_sql($query_alunos, $params);
        return $estudantes_grupo_tutoria;
    }

}
