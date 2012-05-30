<?php
require_once('../../../config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once('locallib.php');
require_once('creategroups_form.php');

require_login();
require_capability('moodle/site:config', get_context_instance(CONTEXT_SYSTEM));

// Imprime o cabeçalho
admin_externalpage_setup('tooltutores');
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('grupos_tutoria', 'tool_tutores'));

$currenttab = 'assign';
include_once('tabs.php');

echo $OUTPUT->heading('Por favor, escolha um grupo para atribuir participantes', 3);

// Conteúdo
$table = new html_table();
$table->head = array(get_string('grupos_tutoria', 'tool_tutores'), get_string('member_count', 'tool_tutores'));
$table->tablealign = 'center';
#$table->width = '90%';
$table->data = array();

// Dummy data
$table->data[] = array('Grupo 1', '50');
$table->data[] = array('Grupo 2', '40');
$table->data[] = array('Grupo 3', '45');
$table->data[] = array('Grupo 4', '48');
$table->data[] = array('Grupo 5', '20');

echo html_writer::table($table);



// Imprime o restante da página
echo $OUTPUT->footer();
?>