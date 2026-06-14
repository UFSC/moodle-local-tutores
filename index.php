<?php

require_once('../../config.php');
require_once('locallib.php');

$categoryid = required_param('categoryid', PARAM_INT);
$context = context_coursecat::instance($categoryid);
$base_url = new moodle_url("/local/tutores/index.php", array('categoryid' => $categoryid));

$PAGE->set_url($base_url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('pluginname', 'local_tutores'));
$PAGE->set_heading(get_string('pluginname', 'local_tutores'));

require_login();
require_capability('local/tutores:manage', $context);

$curso_ufsc = local_tutores_get_curso_ufsc_id($categoryid);

if (empty($curso_ufsc)) {
    print_error('curso_ufsc_nao_encontrado_error', 'local_tutores');
}

// FIXME: adaptar para a estrutura baseada em tag/categoria (turma_ufsc), sem curso_ufsc.
// get_category_context_from_curso_ufsc() devolve um context_coursecat; get_relationship_tutoria()
// espera o id da CATEGORIA (instanceid). A própria get_relationship_tutoria() já dispara
// print_error() se não houver (ou houver mais de um) relationship — então aqui sempre há um.
$categorycontext = local_tutores_get_category_context_from_curso_ufsc($curso_ufsc);
$relationship = local_tutores_grupos_tutoria::get_relationship_tutoria($categorycontext->instanceid);
redirect(new moodle_url('/local/relationship/groups.php', array('relationshipid' => $relationship->id)));