<?php

require_once('../../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once('locallib.php');

require_login();
require_capability('moodle/site:config', get_context_instance(CONTEXT_SYSTEM));
admin_externalpage_setup('tooltutores');

$renderer = $PAGE->get_renderer('tool_tutores');
$curso_ufsc = get_curso_ufsc_id();

if (empty($curso_ufsc)) {
    echo $renderer->choose_curso_ufsc_page('/admin/tool/tutores/index.php');
} else {
    $grupos = get_grupos_tutoria_with_members_count($curso_ufsc);
    echo $renderer->list_groups_page($grupos);
}