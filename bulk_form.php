<?php

defined('MOODLE_INTERNAL') || die();

require_once $CFG->libdir.'/formslib.php';

/**
 * Upload a file CVS file with user information.
 */
class admin_bulk_tutores extends moodleform {
    function definition () {
        $mform = $this->_form;

        $mform->addElement('header', 'bulktutoresheader', get_string('upload'));

        $mform->addElement('filepicker', 'userfile', get_string('file'));
        $mform->addRule('userfile', null, 'required');

        $choices = csv_import_reader::get_delimiter_list();
        $mform->addElement('select', 'delimiter_name', get_string('csvdelimiter', 'tool_uploaduser'), $choices);
        if (array_key_exists('cfg', $choices)) {
            $mform->setDefault('delimiter_name', 'cfg');
        } else if (get_string('listsep', 'langconfig') == ';') {
            $mform->setDefault('delimiter_name', 'semicolon');
        } else {
            $mform->setDefault('delimiter_name', 'comma');
        }

        $choices = core_text::get_encodings();
        $mform->addElement('select', 'encoding', get_string('encoding', 'tool_uploaduser'), $choices);
        $mform->setDefault('encoding', 'UTF-8');

        $choices = array('10'=>10, '20'=>20, '100'=>100, '1000'=>1000, '100000'=>100000);
        $mform->addElement('select', 'previewrows', get_string('rowpreviewnum', 'tool_uploaduser'), $choices);
        $mform->setType('previewrows', PARAM_INT);

        $this->add_action_buttons(false, get_string('uploadusers', 'tool_uploaduser'));
    }
}

class admin_bulk_tutores_confirmation extends moodleform {
    function definition() {
        global $OUTPUT;

        $mform = $this->_form;
        $data  = $this->_customdata['data'];
        $curso_ufsc = $this->_customdata['curso_ufsc'];
        $grupos_tutoria = get_grupos_tutoria_select($curso_ufsc);

        $mform->addElement('header', 'bulktutoresheader', get_string('upload'));
        $mform->addElement('html', $OUTPUT->heading(get_string('confirm_bulk_operation', 'local_tutores'), 3));

        $mform->addElement('select', 'grupotutoria', 'Grupo de Tutoria', $grupos_tutoria);

        // hidden fields
        $mform->addElement('hidden', 'iid');
        $mform->setType('iid', PARAM_INT);

        $this->set_data($data);

        $this->add_action_buttons(true, get_string('uploadusers', 'tool_uploaduser'))  ;
    }
}