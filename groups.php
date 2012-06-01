<?php
require_once('../../../config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once('creategroups_form.php');

require_login();
require_capability('moodle/site:config', get_context_instance(CONTEXT_SYSTEM));
admin_externalpage_setup('tooltutores');

$renderer = $PAGE->get_renderer('tool_tutores');

// Imprime o cabeçalho
echo $renderer->page_header('index');

// Conteúdo
$form = new create_groups_form();
$form->display();


// Imprime o restante da página
echo $renderer->page_footer();
?>