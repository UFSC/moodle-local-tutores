<?php

defined('MOODLE_INTERNAL') || die();

class local_tutores_renderer extends plugin_renderer_base {

    public function __construct(moodle_page $page, $target) {
        parent::__construct($page, $target);

        $this->categoryid = required_param('categoryid', PARAM_INT);
    }

    public function create_confirmation($return_url, $confirm_url, $categoryid) {
        global $OUTPUT;

        $category = coursecat::get($categoryid);
        $formcontinue = new single_button($confirm_url, get_string('yes'));
        $formcancel = new single_button($return_url, get_string('no'), 'get');

        $output = $this->page_header('index');
        $output .= $OUTPUT->heading(get_string('create_confirmation', 'local_tutores'));
        $output .= $OUTPUT->confirm(get_string('confirm_relationship_creation', 'local_tutores', $category->name), $formcontinue, $formcancel);

        return $output;
    }

    public function page_header() {
        $output = '';

        // Imprime cabeçalho da página
        $output .= $this->header();

        return $output;
    }

    public function page_footer() {
        return $this->footer();
    }

}