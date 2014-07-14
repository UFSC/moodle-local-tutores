<?php

define('CLI_SCRIPT', true);

require(__DIR__.'/../../../config.php');
require_once($CFG->libdir.'/clilib.php');
require_once(__DIR__.'/../lib.php');
require_once(__DIR__.'/../locallib.php');
require_once($CFG->dirroot.'/local/relationship/lib.php');
require_once($CFG->dirroot.'/local/relationship/locallib.php');

// Now get cli options.
list($options, $unrecognized) = cli_get_params(
        array(
                'group' => null,
                'cursoufsc' => null,
                'execute' => false,
                'list' => false,
                'help' => false,
        ),
        array(
                'l' => 'list',
                'h' => 'help',
        )
);

if (empty($CFG->version)) {
    cli_error(get_string('missingconfigversion', 'debug'));
}

if ($options['list']) {
    if (!isset($options['cursoufsc'])) {
        cli_error('Informe o CÓDIGO do Curso UFSC que receberá o grupo de tutoria: "--cursoufsc=CURSOID"');
    }

    local_tutores_cli_list($options);
} elseif ($options['execute']) {
    if (!isset($options['cursoufsc'])) {
        cli_error('Informe o CÓDIGO do Curso UFSC que receberá o grupo de tutoria: "--cursoufsc=CURSOID"');
    }

    local_tutores_cli_execute($options);
} else {
    local_tutores_cli_help();
}

function local_tutores_cli_help() {
    $help = <<<HELP
Migra os dados de alunos e tutores para relationship.

Options:
--cursoufsc=CURSOID   Curso UFSC que terá o grupo migrado.
--list                Apenas lista os participantes que serão migrados
--execute             Executa a migração (cria grupo e inscreve participantes)
-h, --help            Imprime esta ajuda.

Exemplo:
\$ sudo -u www-data /usr/bin/php local/tutores/cli/migrate.php
HELP;

    echo $help;
    exit(0);
}

function local_tutores_cli_list($options) {
    $curso_ufsc = $options['cursoufsc'];

    $grupos_tutoria = grupos_tutoria::get_grupos_tutoria($curso_ufsc);
    $category = get_category_context_from_curso_ufsc($curso_ufsc);

    $relationship = local_tutores_cli_get_relationship_tutoria($category->id);

    cli_heading("Categoria: {$category->id}");
    cli_heading("Grupos de Tutoria existentes:");
    foreach ($grupos_tutoria as $grupo) {
        echo "* {$grupo->nome} \n";
    }

    echo "\n";

    cli_heading("Relationships");

    if (!$relationship) {
        echo 'Nenhum relationship foi encontrado';
    } else {
        var_dump($relationship);
    }

    exit(0);
}

function local_tutores_cli_execute($options) {
    $curso_ufsc = $options['cursoufsc'];

    cli_heading(get_string('cliheading', 'local_tutores'));

    $category = get_category_context_from_curso_ufsc($curso_ufsc);
    $relationship = local_tutores_cli_get_relationship_tutoria($category->id);

    if (!$relationship) {
        cli_heading("Criando relationship para categoria: {$category->id}");

        $relationshipid = local_tutores_cli_create_relationship($category->id);
    } else {
        cli_heading("Relationship já existente: {$relationship->id}, categoria: {$category->id}");
        $relationshipid = $relationship->id;
    }

    $cohorts = relationship_get_cohorts($relationshipid);
    if (!$cohorts) {
        cli_heading("Criando cohorts para relationship: {$relationshipid}");

        local_tutores_cli_create_cohorts($relationshipid);
    } else {
        cli_heading("Cohorts já existentes para relationship: {$relationshipid}");
    }

    $grupos = relationship_get_groups($relationshipid);
    if (!$grupos) {
        cli_heading("Criando grupos para relationship: {$relationshipid}");

        $grupos_tutoria = grupos_tutoria::get_grupos_tutoria($curso_ufsc);
        local_tutores_cli_create_groups($relationshipid, $grupos_tutoria);
    } else {
        cli_heading("Grupos já existentes para relationship: {$relationshipid}");
    }

    exit(0);
}

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

function local_tutores_cli_create_cohorts($relationshipid) {
    global $DB;
    $role_student = local_tutores_cli_get_role_by_shortname('student');
    $role_tutor = local_tutores_cli_get_role_by_shortname('td');

    $cohort_student = $DB->get_record('cohort', array('idnumber' => 'alunos_curso:21000077'), '*', MUST_EXIST);
    $cohort_tutor = $DB->get_record('cohort', array('idnumber' => 'tutores_ufsc_curso:21000077'), '*', MUST_EXIST);

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

        local_tutores_cli_add_members_to_group($relationshipid, $oldgrupo->id, $groupid, $cohorts);
    }


}

function local_tutores_cli_add_members_to_group($relationshipid, $oldgroupid, $groupid, $cohorts) {
    $old_members = get_members_grupo_tutoria($oldgroupid);

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