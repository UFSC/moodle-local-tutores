<?php

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.'); // It must be included from a Moodle page
}

$toprow = array();
$toprow[] = new tabobject('index', new moodle_url('/admin/tool/tutores/index.php'), get_string('gerenciar_tutores', 'tool_tutores'));
$toprow[] = new tabobject('assign', new moodle_url('/admin/tool/tutores/assign.php'), get_string('designar_participantes', 'tool_tutores'));
$tabs = array($toprow);

print_tabs($tabs, $currenttab);