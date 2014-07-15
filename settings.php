<?php

defined('MOODLE_INTERNAL') || die;

if ($hassiteconfig) {
    require_once($CFG->dirroot.'/local/tutores/lib.php');

    $available_roles = $DB->get_records('role');
    if ($available_roles) {
        $available_roles = role_fix_names($available_roles, null, ROLENAME_ORIGINAL);
        $assignable_roles = get_roles_for_contextlevels(CONTEXT_COURSE);

        $roles = array();
        foreach ($assignable_roles as $assignable) {
            $role = $available_roles[$assignable];
            $roles[$role->shortname] = $role->localname;
        }

        $ADMIN->add('users', new admin_category('grupostutoria', get_string('grupos_tutoria', 'local_tutores')));

        $settings = new admin_settingpage('grupos_tutoria_settings', get_string('grupos_tutoria_settings', 'local_tutores'));
        $settings->add(new admin_setting_configmultiselect('local_tutores_student_roles',
                get_string('settings_estudantes_allowed_roles', 'local_tutores'),
                get_string('description_estudantes_allowed_roles', 'local_tutores'), null, $roles));
        $settings->add(new admin_setting_configmultiselect('local_tutores_tutor_roles',
                get_string('settings_tutores_allowed_roles', 'local_tutores'),
                get_string('description_tutores_allowed_roles', 'local_tutores'), null, $roles));

        $ADMIN->add('grupostutoria', $settings);
    }

}
