<?php

defined('MOODLE_INTERNAL') || die;

if ($hassiteconfig) {
    require_once($CFG->dirroot . '/local/tutores/lib.php');

    $papeis = grupos_tutoria::get_papeis_ufsc();

    if (!empty($papeis)) {

        $ADMIN->add('users', new admin_category('grupostutoria', get_string('grupos_tutoria', 'local_tutores')));

        $settings = new admin_settingpage('grupos_tutoria_settings', get_string('grupos_tutoria_settings', 'local_tutores'));
        $settings->add(new admin_setting_configmultiselect('estudantes_allowed_roles',
            get_string('settings_estudantes_allowed_roles', 'local_tutores'),
            get_string('description_estudantes_allowed_roles', 'local_tutores'), null, $papeis));
        $settings->add(new admin_setting_configmultiselect('tutores_allowed_roles',
            get_string('settings_tutores_allowed_roles', 'local_tutores'),
            get_string('description_tutores_allowed_roles', 'local_tutores'), null, $papeis));
        $settings->add(new admin_setting_configmultiselect('coordenadores_allowed_roles',
            get_string('settings_coordenadores_allowed_roles', 'local_tutores'),
            get_string('description_coordenadores_allowed_roles', 'local_tutores'), null, $papeis));

        $ADMIN->add('grupostutoria', $settings);
    }
}