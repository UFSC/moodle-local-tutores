<?php

require_once('../../config.php');
require_once($CFG->libdir . '/adminlib.php');
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

$renderer = $PAGE->get_renderer('local_tutores');
$curso_ufsc = get_curso_ufsc_id($categoryid);

if (empty($curso_ufsc)) {
    error('Não é possível habilitar o Grupo de Tutoria neste curso');
} else {
    // FIXME: é necessário adaptar essa lógica para nova estrutura que não depende de curso_ufsc
    $category = get_category_context_from_curso_ufsc($curso_ufsc);
    $relationship = grupos_tutoria::get_relationship_tutoria($category->id);

    if (!$relationship) {
        //echo $renderer->create_confirmation($base_url, $base_url, $curso_ufsc);
        error('Não existe um Grupo de Tutoria habilitado para este curso');
    } else {
        redirect(new moodle_url('/local/relationship/groups.php', array('relationshipid' => $relationship->id)));
    }
}