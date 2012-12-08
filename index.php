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
require_capability('tool/tutores:manage', $context);

$renderer = $PAGE->get_renderer('local_tutores');
$curso_ufsc = get_curso_ufsc_id($categoryid);

if (empty($curso_ufsc)) {
    echo $renderer->choose_curso_ufsc_page('/local/tutores/index.php');
} else {
    $grupos = get_grupos_tutoria_with_members_count($curso_ufsc);
    echo $renderer->list_groups_page($grupos);
}