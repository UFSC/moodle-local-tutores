<?php

defined('MOODLE_INTERNAL') || die;

if ($hassiteconfig) {
    $ADMIN->add('roles', new admin_externalpage('tooltutores', get_string('grupos_tutoria', 'tool_tutores'), "$CFG->wwwroot/$CFG->admin/tool/tutores/index.php"));
}
