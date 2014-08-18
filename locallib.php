<?php

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/moodlelib.php');
require_once($CFG->libdir . '/coursecatlib.php');
require_once($CFG->dirroot . '/local/tutores/middlewarelib.php');
require_once($CFG->dirroot . '/local/tutores/lib.php');

function get_category_context_from_curso_ufsc($curso_ufsc) {
    global $DB;

    $categoryid = $DB->get_field('course_categories', 'id', array('idnumber' => "curso_{$curso_ufsc}"));
    return context_coursecat::instance($categoryid);
}

function get_curso_ufsc_id() {
    $categoryid = required_param('categoryid', PARAM_INT);
    $category = coursecat::get($categoryid);

    if (!$category->idnumber) {
        return false;
    }

    return str_replace('curso_', '', $category->idnumber, $count);
}

/**
 * Retorna a lista de participantes de um grupo de tutoria
 *
 * @param int $grupo id do grupo de tutoria
 * @return array lista de participantes ou false em caso de falha
 */
function local_tutores_cli_get_members_grupo_tutoria($grupo) {
    $middleware = Middleware::singleton();

    $sql = "SELECT u.*, pg.tipo as grupo_tutoria_tipo
              FROM {user} u
              JOIN {table_PessoasGruposTutoria} pg
                ON (u.username=pg.matricula)
             WHERE pg.grupo=:grupo";

    $params = array('grupo' => $grupo);
    return $middleware->get_records_sql($sql, $params);
}


// Methods for the CLI Migration

function local_tutores_cli_create_relationship($contextid) {
    $new_relationship = new stdClass();
    $new_relationship->name = 'Grupos de Tutoria';
    $new_relationship->contextid = $contextid;
    $new_relationship->description = 'Criado automáticamente pela ferramenta de migração de Grupos de Tutoria';
    //$new_relationship->component = 'local_tutores';
    //$new_relationship->idnumber = "local_tutores_{$contextid}";
    $new_relationship->tags = array('grupo_tutoria');

    return relationship_add_relationship($new_relationship);
}

function local_tutores_cli_create_cohorts($relationshipid, $curso_ufsc) {
    global $DB;
    $role_student = local_tutores_cli_get_role_by_shortname('student');
    $role_tutor = local_tutores_cli_get_role_by_shortname('td');

    $cohort_student = $DB->get_record('cohort', array('idnumber' => "alunos_curso:{$curso_ufsc}"), '*', MUST_EXIST);
    $cohort_tutor = $DB->get_record('cohort', array('idnumber' => "tutores_ufsc_curso:{$curso_ufsc}"), '*', MUST_EXIST);

    $cohort_alunos = new stdClass();
    $cohort_alunos->relationshipid = $relationshipid;
    $cohort_alunos->cohortid = $cohort_student->id;
    $cohort_alunos->roleid = $role_student->id;
    relationship_add_cohort($cohort_alunos);

    $cohort_tutores = new stdClass();
    $cohort_tutores->relationshipid = $relationshipid;
    $cohort_tutores->cohortid = $cohort_tutor->id;
    $cohort_tutores->roleid = $role_tutor->id;
    relationship_add_cohort($cohort_tutores);
}

function local_tutores_cli_create_groups($relationshipid, $grupos_tutoria) {

    $cohorts = array();
    $cohorts['E'] = local_tutores_cli_get_relationship_cohort_by_shortname($relationshipid, 'student');
    $cohorts['T'] = local_tutores_cli_get_relationship_cohort_by_shortname($relationshipid, 'td');

    // Juntamente com a criação do grupo é feito a importação pois precisamos do ID do grupo
    // E não há garantias de que o nome é único,
    // portanto daqui pra frente perdemos a referência do ID antigo
    foreach ($grupos_tutoria as $oldgrupo) {
        $new_group = new stdClass();
        $new_group->relationshipid = $relationshipid;
        $new_group->name = $oldgrupo->nome;

        echo "\n";
        cli_heading("Criando grupo: {$oldgrupo->nome}");
        $groupid = relationship_add_group($new_group);

        local_tutores_cli_add_members_to_group($oldgrupo->id, $groupid, $cohorts);
    }


}

function local_tutores_cli_add_members_to_group($oldgroupid, $groupid, $cohorts) {
    $old_members = local_tutores_cli_get_members_grupo_tutoria($oldgroupid);

    foreach($old_members as $old_member) {
        echo " * Cadastrando membro: {$old_member->username} ({$old_member->grupo_tutoria_tipo})\n";

        relationship_add_member($groupid, $cohorts[$old_member->grupo_tutoria_tipo]->id, $old_member->id);
    }
}

function local_tutores_cli_get_relationship_cohort_by_shortname($relationshipid, $shortname) {
    global $DB;

    $sql = "SELECT rc.*
              FROM {relationship_cohorts} rc
              JOIN {role} r
                ON (r.id=rc.roleid)
             WHERE relationshipid=:relationshipid
               AND r.shortname = :shortname";

    $params = array('relationshipid' => $relationshipid, 'shortname' => $shortname);
    return $DB->get_record_sql($sql, $params);
}

function local_tutores_cli_get_role_by_shortname($shortname) {
    global $DB;

    $sql = "SELECT r.*
              FROM {role} r
              JOIN {role_context_levels} rctx
                ON (rctx.roleid = r.id)
             WHERE r.shortname = :shortname
               AND rctx.contextlevel = :contextlevel
    ";

    $params = array('shortname' => $shortname, 'contextlevel' => CONTEXT_COURSE);

    return $DB->get_record_sql($sql, $params);
}

function local_tutores_cli_get_relationship_tutoria($contextid) {
    global $DB;

    $sql = "SELECT r.*
              FROM {relationship} r
              JOIN (
                    SELECT ti.itemid as relationship_id
                      FROM {tag_instance} ti
                      JOIN {tag} t
                        ON (t.id=ti.tagid)
                     WHERE t.name='grupo_tutoria'
                   ) tr
                ON (r.id=tr.relationship_id)
              WHERE r.contextid=:contextid";

    $params = array('contextid' => $contextid);

    return $DB->get_record_sql($sql, $params);
}

/**
 * Retorna lista de grupos de tutoria de um determinado curso ufsc
 *
 * @param string $curso_ufsc
 * @param array $tutores
 * @return array
 */
function local_tutores_cli_get_grupos_tutoria($curso_ufsc, $tutores = null) {
    $middleware = Middleware::singleton();

    if (is_null($tutores)) {
        $sql = "SELECT * FROM {table_GruposTutoria} WHERE curso=:curso_ufsc ORDER BY nome";
    } else {
        $tutores = int_array_to_sql($tutores);
        $sql = "SELECT gt.id, gt.curso, gt.nome
                      FROM {table_GruposTutoria} gt
                      JOIN {table_PessoasGruposTutoria} pg
                        ON (gt.id=pg.grupo AND gt.curso=:curso_ufsc)
                      JOIN {user} u
                        ON (pg.matricula=u.username AND pg.tipo=:tipo)
                     WHERE u.id IN ({$tutores})";
    }

    return $middleware->get_records_sql($sql, array('curso_ufsc' => $curso_ufsc, 'tipo' => GRUPO_TUTORIA_TIPO_TUTOR));
}