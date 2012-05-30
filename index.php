<?php
require_once('../../../config.php');
require_once($CFG->libdir.'/adminlib.php');

require_login();
require_capability('moodle/site:config', get_context_instance(CONTEXT_SYSTEM));

// Imprime o cabeçalho
admin_externalpage_setup('tooltutores');
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('gerenciar_tutores', 'tool_tutores'));

// Conteúdo


// Imprime o restante da página
echo $OUTPUT->footer();
?>