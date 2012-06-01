<?php

require_once('../../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once('locallib.php');

require_login();
require_capability('moodle/site:config', get_context_instance(CONTEXT_SYSTEM));
admin_externalpage_setup('tooltutores');

$renderer = $PAGE->get_renderer('tool_tutores');
echo $renderer->index_page();