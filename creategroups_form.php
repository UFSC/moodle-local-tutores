<?php

defined('MOODLE_INTERNAL') || die;

require_once $CFG->libdir.'/formslib.php';

class create_groups_form extends moodleform {
    
    function definition() {
        $category = 'Saúde da Família';
        $mform = $this->_form;

        $mform->addElement('header', 'grupos_tutoria', get_string('grupos_tutoria', 'tool_tutores'));
        $mform->addElement('text', 'nome', get_string('nome_grupo', 'tool_tutores'), array('size' => 80));
        $mform->addElement('static', 'curso', get_string('curso', 'tool_tutores'), $category);

        $this->add_action_buttons(true, get_string('add'));
    }
}