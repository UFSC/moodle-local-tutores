<?php

defined('MOODLE_INTERNAL') || die;

function xmldb_local_tutores_upgrade($oldversion) {
    global $DB;

    if ($oldversion < 2014071500) {
        $DB->delete_records_list('config', 'name', array('estudantes_allowed_roles', 'tutores_allowed_roles', 'coordenadores_allowed_roles'));
    }

    return true;
}