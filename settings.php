<?php

defined('MOODLE_INTERNAL') || die;

$ADMIN->add('users', new admin_category('grupostutoria', get_string('grupos_tutoria', 'tool_tutores')));
$ADMIN->add('grupostutoria', new admin_externalpage('tooltutores', get_string('grupos_tutoria', 'tool_tutores'),
    "$CFG->wwwroot/$CFG->admin/tool/tutores/index.php"));

if ($hassiteconfig) {
    require_once("{$CFG->dirroot}/admin/tool/tutores/locallib.php");
    $papeis = get_papeis_disponiveis();

    $settings = new admin_settingpage('grupos_tutoria_settings', get_string('grupos_tutoria_settings', 'tool_tutores'));
    $settings->add(new admin_setting_configmultiselect('tutores_allowed_roles', get_string('settings_allowed_roles',
        'tool_tutores'), '', null, $papeis));

    $ADMIN->add('grupostutoria', $settings);
}